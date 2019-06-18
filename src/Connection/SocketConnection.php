<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\RequestError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

/**
 * Socket transport
 */
class SocketConnection extends AbstractConnection
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
        if (!$this->socket) {
            $this->socket = socket_create(
                AF_INET,
                SOCK_STREAM,
                SOL_TCP
            );

            $seconds = (int)floor($this->getRequestTimeoutMs() / 1e6);
            $microseconds = $this->getRequestTimeoutMs() - $seconds * 1e6;

            socket_set_option(
                $this->socket,
                SOL_SOCKET,
                SO_RCVTIMEO,
                [
                    'sec' => $seconds,
                    'usec' => $microseconds
                ]
            );

            socket_set_option(
                $this->socket,
                SOL_SOCKET,
                SO_SNDTIMEO,
                [
                    'sec' => $seconds,
                    'usec' => $microseconds
                ]
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
        }

        return $this->socket;
    }

    public function execute(string $query): string
    {
        // prepare HTTP QUERY
        $requestURI = 'POST / HTTP/1.1';
        $requestHeaders = [
            'Content-Length: ' . mb_strlen($query),
        ];
        $requestBODY = $query;

        $request = implode("\r\n", [
            $requestURI,
            implode("\r\n", $requestHeaders),
            "\n", $requestBODY
        ]);

        // create socket
        $socket = $this->getSocket();

        // send request
        if (!socket_write($socket, $request, mb_strlen($request))) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ConnectError(
                sprintf('Send request error: %s', $errorMessage),
                $errorCode
            );
        }

        // wait read
        $this->waitRead();

        // read response
        $response = '';

        while (true) {
            $responseChunk = socket_read($this->socket, self::READ_BUFFER_LENGTH);

            if ($responseChunk === false) {
                $errorCode = socket_last_error();
                $errorMessage = socket_strerror($errorCode);
                throw new ConnectError(
                    sprintf('Receive response error: %s', $errorMessage),
                    $errorCode
                );
            }

            if ($responseChunk === '') {
                break;
            }

            $response .= $responseChunk;

            if (mb_strlen($responseChunk) < self::READ_BUFFER_LENGTH) {
                break;
            }
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
     * wait response
     */
    private function waitRead() : void
    {
        $read = [$this->socket];
        $write = [];
        $except = [];

        $selectResult = socket_select($read, $write, $except, 10);

        if ($selectResult === false) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ConnectError(
                sprintf('Receive response error: %s', $errorMessage),
                $errorCode
            );
        }

        if ($selectResult === 0) {
            throw new ConnectError('Socket select timeout');
        }
    }

}