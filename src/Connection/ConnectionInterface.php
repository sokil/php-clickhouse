<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

interface ConnectionInterface
{
    /**
     * @param string $query
     *
     * @return array
     */
    public function execute(string $query): array;
}