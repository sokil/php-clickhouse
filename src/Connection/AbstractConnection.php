<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Struct\Timeval;

abstract class AbstractConnection
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
     * @param string $host
     * @param int $port
     * @param int $connectionTimeoutMs Connection timeout in milliseconds
     * @param int $requestTimeoutMs Timeout of total request time in milliseconds
     */
    public function __construct(
        string $host,
        int $port,
        int $connectionTimeoutMs = self::DEFAULT_CONNECTION_TIMEOUT_MS,
        int $requestTimeoutMs = self::DEFAULT_REQUEST_TIMEOUT_MS
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeoutMs = $connectionTimeoutMs;
        $this->requestTimeoutMs = $requestTimeoutMs;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    abstract public function execute(string $query): string;

    /**
     * @return string
     */
    protected function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    protected function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return int
     */
    protected function getConnectionTimeoutMs(): int
    {
        return $this->connectionTimeoutMs;
    }

    /**
     * @return int
     */
    protected function getRequestTimeoutMs(): int
    {
        return $this->requestTimeoutMs;
    }

    /**
     * Useful for socket's SO_SNDTIMEO/SO_RCVTIMEO timeout options or select() calls.
     *
     * @see timeval struct from sys/time.h
     *
     * @param int $timeMs
     *
     * @return Timeval
     */
    protected function getTimevalStruct(int $timeMs) : Timeval
    {
        $seconds = floor($timeMs / 1e3);
        $microSeconds = fmod($timeMs, 1e3) * 1e3;

        return new Timeval(
            (int)$seconds,
            (int)$microSeconds
        );
    }
}