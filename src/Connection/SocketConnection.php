<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;

/**
 * Socket transport
 */
class SocketConnection implements ConnectionInterface
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var resource
     */
    private $socket;

    /**
     * SocketConnection constructor.
     *
     * @param string $host
     * @param int $port
     */
    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
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
        $this->getSocket();
    }

}