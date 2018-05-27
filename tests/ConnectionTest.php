<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testConnect()
    {
        $client = new Client();
        $this->assertTrue($client->ping());
    }

    public function testQuery()
    {
        $client = new Client();

        $tableName = 'test3';

        $client->execute(sprintf('CREATE TABLE %s (id Int32) ENGINE=Memory', $tableName));
        $client->execute(sprintf('INSERT INTO %s VALUES (1), (2), (3)', $tableName));
        $response = $client->execute(sprintf('SELECT * FROM %s', $tableName));
        $client->execute(sprintf('DROP TABLE %s', $tableName));

        $this->assertEquals([1, 2, 3], $response);
    }
}