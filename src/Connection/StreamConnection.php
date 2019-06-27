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
    private $stream;

    /**
     * @return resource
     *
     * @throws ConnectError
     */
    private function getStream()
    {
        if ($this->stream) {
            return $this->stream;
        }

        // suppress warning when connection now allowed
        set_error_handler(
            function ($type, $errorMessage) {
                throw new ConnectError(
                    sprintf('Connection error: %s', $errorMessage)
                );
            }
        );

        try {
            $socket = stream_socket_client(
                sprintf('tcp://%s:%s', $this->getHost(), $this->getPort()),
                $errorCode,
                $errorMessage,
                $this->getConnectionTimeoutMs(),
                STREAM_CLIENT_CONNECT
            );

            if ($socket === false) {
                throw new ConnectError(
                    sprintf('Connection error: %s', $errorMessage),
                    $errorCode
                );
            }
        } finally {
            restore_error_handler();
        }

        // blocking mode
        stream_set_blocking($socket, true);

        // read/write timeout
        $seconds = (int)floor($this->getRequestTimeoutMs() / 1e6);
        $microseconds = $this->getRequestTimeoutMs() - $seconds * 1e6;
        stream_set_timeout($socket, (int)$seconds, (int)$microseconds);

        $this->stream = $socket;

        return $this->stream;
    }

    public function execute(string $query): string
    {
        $query .= "\r\n";

        // create socket
        $stream = $this->getStream();

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

        $this->wait($stream);

        // send request
        if (fwrite($stream, $request) === false) {
            throw new ConnectError('Socket write error');
        }

        $this->wait($stream);

        // read response
        $response = '';
        while (true) {
            $responseChunk = fread($stream, self::READ_BUFFER_LENGTH);
            if ($responseChunk === false || $responseChunk === '') {
                break;
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
            throw new ConnectError(sprintf('Invalid HTTP response: "%s"', $response));
        }

        return $response;
    }

    /**
     * Wait socket
     *
     * @param resource $stream
     */
    private function wait($stream) : void
    {
        $read = [$stream];
        $write = [$stream];
        $except = [];

        $requestTimeoutSeconds = (int)floor($this->getRequestTimeoutMs() / 1e6);
        $requestTimeoutMicroseconds = (int)($this->getRequestTimeoutMs() - $requestTimeoutSeconds * 1e6);

        $changedSocketCount = stream_select(
            $read,
            $write,
            $except,
            $requestTimeoutSeconds,
            $requestTimeoutMicroseconds
        );

        if ($changedSocketCount === false) {
            throw new ConnectError('Receive response error');
        }

        if ($changedSocketCount === 0) {
            throw new ConnectError('Socket select timeout');
        }
    }
}