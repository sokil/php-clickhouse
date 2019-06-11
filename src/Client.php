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
     * @param string $query
     *
     * @todo set to private, resurt result object
     */
    public function execute(string $query)
    {
        $query = $query . ' FORMAT JSON';

        $response = $this->connection->execute($query);

        $response = \json_decode($response, true);

        return $response;
    }

    /**
     * Check if connection alive
     *
     * @return bool
     */
    public function ping(): bool
    {
        $response = $this->execute('SELECT 1');

        return ($response['data'][0][1] ?? null) === "1";
    }

    /**
     * Persist document to storage
     *
     * @param $document
     */
    public function persist($document): void
    {

    }
}