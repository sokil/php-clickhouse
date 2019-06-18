<?php
declare(strict_types=1);

namespace Sokil\ClickHouse;

use PHPUnit\Framework\TestCase;
use Sokil\ClickHouse\Connection\CurlConnection;
use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

class ClientTest extends TestCase
{
    public const TABLE_NAME = 'test';

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $this->client = new Client(new CurlConnection('localhost', 8123));

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

    public function testConnectError()
    {
        $this->expectException(ConnectError::class);
        $this->expectExceptionMessage('Error connecting ClickHouse at server.com:100');
        $this->expectExceptionCode(0);

        $client = new Client(
            new CurlConnection(
                'server.com',
                100
            )
        );

        $client->ping();
    }

    public function testExecuteError()
    {
        $this->expectException(QueryError::class);
        $this->expectExceptionMessage('someUnknownTable doesn\'t exist');
        $this->expectExceptionCode(404);

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