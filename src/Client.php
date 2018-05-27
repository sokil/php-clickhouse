<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use Sokil\ClickHouse\Connection\ConnectionInterface;
use Sokil\ClickHouse\Connection\CurlConnection;

/**
 * Connection to ClickHouse
 */
class Client
{
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection = null)
    {
        $this->connection = $connection ?? $this->buildDefaultConnection();
    }

    /**
     * @return ConnectionInterface
     */
    private function buildDefaultConnection(): ConnectionInterface
    {
        return new CurlConnection();
    }

    /**
     * Check if connection alive
     *
     * @return bool
     */
    public function ping(): bool
    {
        $response = $this->connection->execute('SELECT 1');

        return current($response) === "1";
    }

    /**
     * Persist document to storage
     *
     * @param $document
     */
    public function persist($document): void
    {

    }

    /**
     * Query request
     *
     * @param string $query
     *
     * @return array
     */
    public function execute(string $query): array
    {
        $response = $this->connection->execute($query);

        return $response;
    }
}