<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

class StreamConnection extends AbstractConnection
{
    private const READ_BUFFER_LENGTH = 1024;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @return resource
     */
    public function getSocket()
    {
        if ($this->socket) {
            return $this->socket;
        }

        $socket = stream_socket_client(
            sprintf('tcp://%s:%s', $this->getHost(), $this->getPort()),
            $errorCode,
            $errorMessage,
            $this->getConnectionTimeoutMs()
        );

        if ($socket === false) {
            throw new ConnectError(
                sprintf('Connection error: %s', $errorMessage),
                $errorCode
            );
        }

        stream_set_blocking($socket, true);

        $this->socket = $socket;

        return $this->socket;
    }

    public function execute(string $query): string
    {
        $query .= "\r\n";

        // create socket
        $socket = $this->getSocket();

        // prepare HTTP QUERY
        $request =
            'POST / HTTP/1.1' .
            "\r\n" .
            implode(
                "\r\n",
                [
                    'Content-Length: ' . mb_strlen($query),
                    'Connection: Keep-Alive',
                    'User-Agent: SOKIL/PHP-CLICKHOUSE:0.0.1',
                    'Content-Type: text/plain; charset=UTF-8',
                ]
            ) .
            "\r\n" .
            "\r\n" .
            $query;

        // send request
        if (fwrite($socket, $request) === false) {
            throw new ConnectError('Socket write error');
        }

        // read response
        $response = '';
        while (!feof($socket)) {
            $responseChunk = fgets($socket, self::READ_BUFFER_LENGTH);

            if ($responseChunk === false) {
                throw new ConnectError('Socket read error');
            }

            $response .= $responseChunk;
        }

        // get response code
        if (preg_match('~HTTP/\d\.\d (\d{3})~', mb_substr($response, 0, 12), $matches)) {
            $responseCode = (int)$matches[1];

            if ($responseCode !== 200) {
                throw new QueryError($response, $responseCode);
            }
        } else {
            throw new ConnectError('Not HTTP response');
        }

        return $response;
    }

    /**
     * Wait socket
     *
     * @param resource $socket
     */
    private function wait($socket) : void
    {
        $read = [$socket];
        $write = [$socket];
        $except = [];

        $changedSocketCount = socket_select($read, $write, $except, 10);

        if ($changedSocketCount === false) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ConnectError(
                sprintf('Receive response error: %s', $errorMessage),
                $errorCode
            );
        }

        if ($changedSocketCount === 0) {
            throw new ConnectError('Socket select timeout');
        }
    }
}