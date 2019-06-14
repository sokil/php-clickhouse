<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use PHPUnit\Framework\TestCase;
use Sokil\ClickHouse\Connection\CurlConnection;

class ClientTest extends TestCase
{
    public const TABLE_NAME = 'test';

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = new Client(new CurlConnection('http://localhost:8123/'));

        $this->client->execute(
            sprintf(
                'DROP TABLE IF EXISTS %s',
                self::TABLE_NAME
            )
        );
    }

    public function testPing()
    {
        $this->assertTrue($this->client->ping());
    }

    /**
     * @expectedException \Sokil\ClickHouse\Connection\Exception\ConnectError
     * @expectedExceptionMessage Error connecting ClickHouse at http://server.com/
     * @expectedExceptionCode 0
     */
    public function testConnectError()
    {
        $client = new Client(
            new CurlConnection(
                'http://server.com',
                100
            )
        );

        $client->ping();
    }

    /**
     * @expectedException \Sokil\ClickHouse\Connection\Exception\ExecuteError
     * @expectedExceptionMessage someUnknownTable doesn't exist
     * @expectedExceptionCode 404
     */
    public function testExecuteError()
    {
        $this->client->execute('DROP TABLE someUnknownTable');
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
            $response->getRows()
        );
    }
}