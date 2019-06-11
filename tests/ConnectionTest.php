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

        $tableName = 'test';

        $client->execute(sprintf(
            'DROP TABLE %s',
            $tableName
        ));

        $client->execute(sprintf(
            'CREATE TABLE %s (id Int32, value Int32) ENGINE=Memory',
            $tableName
        ));

        $client->execute(sprintf(
            'INSERT INTO %s VALUES 
              (10000000, 1), 
              (2, 20000000), 
              (30000000, 3)',
            $tableName
        ));

        $response = $client->execute(sprintf(
            'SELECT * FROM %s',
            $tableName
        ));

        $client->execute(sprintf(
            'DROP TABLE %s',
            $tableName
        ));

        $this->assertEquals(
            [[10000000, 1], [2, 20000000], [30000000, 3]],
            $response
        );
    }
}