<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;
use Sokil\ClickHouse\Connection\Exception\ConnectionError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

/**
 * HTTP transport
 */
class CurlConnection implements ConnectionInterface
{
    private const DEFAULT_DSN = 'http://localhost:8123/';

    private const DEFAULT_CONNECTION_TIMEOUT_MS = '3000';

    private const DEFAULT_REQUEST_TIMEOUT_MS = '3000';

    /**
     * Connection string
     *
     * @var string
     */
    private $dsn;

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
     * @param string|null $dsn Connection string
     * @param int $connectionTimeoutMs Connection timeout in milliseconds
     * @param int $requestTimeoutMs Timeout of total request time in milliseconds
     *
     * @throws \InvalidArgumentException When DSN is invalid
     */
    public function __construct(
        $dsn = null,
        int $connectionTimeoutMs = null,
        int $requestTimeoutMs = null
    ) {
        if ($dsn == null) {
            $this->dsn = self::DEFAULT_DSN;
        } elseif (filter_var($dsn, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('ClickHouse DSN is invalid');
        } else {
            $this->dsn = rtrim($dsn, '/') . '/';
        }

        $this->connectionTimeoutMs = $connectionTimeoutMs ?? self::DEFAULT_CONNECTION_TIMEOUT_MS;

        $this->requestTimeoutMs = $requestTimeoutMs ?? self::DEFAULT_REQUEST_TIMEOUT_MS;
    }

    /**
     * @var resource
     */
    private $curlSession;

    /**
     * @return resource
     */
    private function getCurlSession()
    {
        if ($this->curlSession === null) {
            $this->curlSession = curl_init();

            curl_setopt_array(
                $this->curlSession,
                [
                    CURLOPT_URL => $this->dsn,
                    CURLOPT_POST => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT_MS => $this->connectionTimeoutMs,
                    CURLOPT_TIMEOUT_MS => $this->requestTimeoutMs,
                    CURLOPT_HTTPHEADER => [
                        'Content-type' => 'application/x-www-form-urlencoded',
                    ],
                ]
            );
        }

        return $this->curlSession;
    }

    /**
     * Execution of native ClickHouse SQL query
     *
     * @param string $query
     *
     * @return string
     * @throws ConnectionError When connect error occured
     * @throws QueryError When error occurs when executing query
     */
    public function execute(string $query): string
    {
        $curlSession = $this->getCurlSession();

        curl_setopt(
            $curlSession,
            CURLOPT_POSTFIELDS,
            $query
        );

        $response = curl_exec($curlSession);
        $responseCode = curl_getinfo($curlSession, CURLINFO_RESPONSE_CODE);

        if ($response === false) {
            throw new ConnectionError(
                sprintf(
                    'Error connecting ClickHouse at %s',
                    $this->dsn
                ),
                $responseCode
            );
        }

        if ($responseCode === 500) {
            throw new QueryError($response, $responseCode);
        }

        return $response;
    }
}