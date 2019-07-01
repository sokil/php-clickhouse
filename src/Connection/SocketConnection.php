<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

/**
 * Socket transport
 */
class SocketConnection extends AbstractConnection
{
    private const READ_BUFFER_LENGTH = 2048;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @return resource
     */
    private function getSocket()
    {
        if ($this->socket) {
            return $this->socket;
        }

        $this->socket = socket_create(
            AF_INET,
            SOCK_STREAM,
            SOL_TCP
        );

        $sendReadTimeout = $this->getTimevalStruct($this->getRequestTimeoutMs());

        socket_set_option(
            $this->socket,
            SOL_SOCKET,
            SO_SNDTIMEO,
            [
                'sec' => $sendReadTimeout->getSeconds(),
                'usec' => $sendReadTimeout->getMicroSeconds(),
            ]
        );

        socket_set_option(
            $this->socket,
            SOL_SOCKET,
            SO_RCVTIMEO,
            [
                'sec' => $sendReadTimeout->getSeconds(),
                'usec' => $sendReadTimeout->getMicroSeconds(),
            ]
        );

        socket_set_option(
            $this->socket,
            SOL_SOCKET,
            SO_KEEPALIVE,
            1
        );


        // Disable Nagle's algorithm, which optimises sending of small packets
        socket_set_option(
            $this->socket,
            SOL_TCP,
            TCP_NODELAY,
            1
        );

        socket_set_block($this->socket);

        if (!socket_connect($this->socket, $this->getHost(), $this->getPort())) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ConnectError(
                sprintf('Connection error: %s', $errorMessage),
                $errorCode
            );
        }

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
                    'Content-Length: ' . strlen($query),
                    'Connection: Keep-Alive',
                    'Content-Type: text/plain; charset=UTF-8',
                ]
            ) .
            "\r\n" .
            "\r\n" .
            $query;

        $requestLength = strlen($request);

        // send request
        $bytesSent = socket_send(
            $socket,
            $request,
            $requestLength,
            0
        );

        if ($bytesSent === false) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ConnectError(
                sprintf('Socket write error: %s', $errorMessage),
                $errorCode
            );
        }

        if ($bytesSent < $requestLength) {
            throw new ConnectError('Socket write error: partial request sent to server');
        }

        // read response
        $response = '';


        while (true) {
            $bytesReceived = socket_recv(
                $socket,
                $responseChunk,
                self::READ_BUFFER_LENGTH,
                0
            );

            if ($bytesReceived === false) {
                $errorCode = socket_last_error();
                $errorMessage = socket_strerror($errorCode);
                throw new ConnectError(
                    sprintf('Socket read error: %s', $errorMessage),
                    $errorCode
                );
            }

            if ($bytesReceived === 0) {
                break;
            }

            $response .= $responseChunk;

            if (strlen($responseChunk) < self::READ_BUFFER_LENGTH) {
                break;
            }

            usleep(10);
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

        $sendReceiveTimeout = $this->getTimevalStruct($this->getRequestTimeoutMs());

        $changedSocketCount = socket_select(
            $read,
            $write,
            $except,
            $sendReceiveTimeout->getSeconds(),
            $sendReceiveTimeout->getMicroSeconds()
        );

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