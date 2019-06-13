<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public const TABLE_NAME = 'test';

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = new Client();

        $this->client->execute(
            sprintf(
                'DROP TABLE IF EXISTS %s',
                self::TABLE_NAME
            )
        );
    }

    public function testConnect()
    {
        $this->assertTrue($this->client->ping());
    }

    public function testQuery()
    {
        $this->client->execute(
            sprintf(
            'CREATE TABLE %s (id Int32, value Int32) ENGINE=Memory',
                self::TABLE_NAME
            )
        );

        $this->client->execute(
            sprintf(
                'INSERT INTO %s VALUES (10000000, 1), (2, 20000000), (30000000, 3)',
                self::TABLE_NAME
            )
        );

        $response = $this->client->query(sprintf(
            'SELECT * FROM %s',
            self::TABLE_NAME
        ));

        $this->client->execute(sprintf(
            'DROP TABLE %s',
            self::TABLE_NAME
        ));

        $this->assertEquals(
            [[10000000, 1], [2, 20000000], [30000000, 3]],
            $response['data']
        );
    }
}