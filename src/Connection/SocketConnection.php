<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\ExecuteError;

/**
 * Socket transport
 */
class SocketConnection implements ConnectionInterface
{
    private const DEFAULT_CONNECTION_TIMEOUT_MS = 3000;

    private const DEFAULT_REQUEST_TIMEOUT_MS = 3000;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * Connection timeout in milliseconds
     *
     * @var int
     */
    private $connectionTimeoutMs;

    /**
     * @var int
     */
    private $requestTimeoutMs;

    /**
     * @var resource
     */
    private $socket;

    /**
     * SocketConnection constructor.
     *
     * @param string $host
     * @param int $port
     * @param int $connectionTimeoutMs Connection timeout in milliseconds
     * @param int $requestTimeoutMs Timeout of total request time in milliseconds
     */
    public function __construct(
        string $host,
        int $port,
        int $connectionTimeoutMs = null,
        int $requestTimeoutMs = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeoutMs = $connectionTimeoutMs ?? self::DEFAULT_CONNECTION_TIMEOUT_MS;
        $this->requestTimeoutMs = $requestTimeoutMs ?? self::DEFAULT_REQUEST_TIMEOUT_MS;
    }


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

            $seconds = (int)floor($this->requestTimeoutMs / 1e6);
            $microseconds = $this->requestTimeoutMs - $seconds * 1e6;

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

            if (!socket_connect($this->socket, $this->host, $this->port)) {
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
        $socket = $this->getSocket();

        if (!socket_write($socket, $query, mb_strlen($query))) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ExecuteError(
                sprintf('Send request error: %s', $errorMessage),
                $errorCode
            );
        }

        $read = [$this->socket];
        $write = [];
        $except = [];

        $selectResult = socket_select($read, $write, $except, 10);

        if ($selectResult === false) {
            $errorCode = socket_last_error();
            $errorMessage = socket_strerror($errorCode);
            throw new ExecuteError(
                sprintf('Receive response error: %s', $errorMessage),
                $errorCode
            );
        }

        if ($selectResult === 0) {
            throw new ExecuteError('Select timeout');
        }

        $result = '';

        while (true) {
            $buffer = socket_read($this->socket, 1024);

            if ($buffer === false) {
                $errorCode = socket_last_error();
                $errorMessage = socket_strerror($errorCode);
                throw new ExecuteError(
                    sprintf('Receive response error: %s', $errorMessage),
                    $errorCode
                );
            }

            if ($buffer === '') {
                break;
            }

            $result .= $buffer;
        }

        return $result;
    }

}