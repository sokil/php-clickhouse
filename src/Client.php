<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use Sokil\ClickHouse\Connection\AbstractConnection;
use Sokil\ClickHouse\Result\QueryResult;
use Sokil\ClickHouse\Result\Result;

/**
 * Connection to ClickHouse
 */
class Client
{
    /**
     * @var AbstractConnection
     */
    private $connection;

    /**
     * Client constructor.
     *
     * @param AbstractConnection $connection
     */
    public function __construct(AbstractConnection $connection
    ) {
        $this->connection = $connection;
    }

    /**
     * Perform SELECT requet
     *
     * @param string $query
     *
     * @return QueryResult
     */
    public function query(string $query) : QueryResult
    {
        $query = $query . ' FORMAT JSONCompact';

        $result = $this->execute($query);

        $body = \json_decode($result->getBody(), true);

        return new QueryResult(
            $result->getHeader('x-clickhouse-server-display-name'),
            $result->getHeader('x-clickhouse-query-id'),
            $body['data']
        );
    }

    /**
     * Perform INSERT, UPDATE or DELETE requet
     *
     * @param string $query
     *
     * @return Result
     */
    public function execute(string $query) : Result
    {
        $response = $this->connection->execute($query);

        [$headers, $body] = explode("\r\n\r\n", $response);

        $headers = explode("\r\n", $headers);

        // remove response code "HTTP/1.1 200 OK"
        array_shift($headers);

        // build headers array
        $headers = array_reduce(
            $headers,
            function(array $carry, string $header) {
                $header = array_map('trim', explode(':', $header, 2));
                $carry[strtolower($header[0])] = $header[1];

                return $carry;
            },
            []
        );

        return new Result(
            $headers,
            $body
        );
    }

    /**
     * Check if connection alive
     *
     * @return bool
     */
    public function ping(): bool
    {
        $result = $this->query('SELECT 1');

        return ($result->getRows()[0][0] ?? null) === 1;
    }
}