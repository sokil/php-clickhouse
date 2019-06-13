<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Result;

class QueryResult
{
    /**
     * @var string
     */
    private $serverDisplayName;

    /**
     * @var string
     */
    private $queryId;

    /**
     * @var array
     */
    private $rows;

    /**
     * QueryResult constructor.
     *
     * @param string $serverDisplayName
     * @param string $queryId
     * @param array $rows
     */
    public function __construct(string $serverDisplayName, string $queryId, array $rows)
    {
        $this->serverDisplayName = $serverDisplayName;
        $this->queryId = $queryId;
        $this->rows = $rows;
    }

    /**
     * @return string
     */
    public function getServerDisplayName(): string
    {
        return $this->serverDisplayName;
    }

    /**
     * @return string
     */
    public function getQueryId(): string
    {
        return $this->queryId;
    }

    /**
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}