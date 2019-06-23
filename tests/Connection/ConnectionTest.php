<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public const TABLE_NAME = 'test';

    public function getConnections()
    {
        return [
            'socket' => [
                'connection' => new SocketConnection('localhost', 8123)
            ],
            'curl' => [
                'connection' => new CurlConnection('localhost', 8123)
            ],
        ];
    }

    /**
     * @dataProvider getConnections
     *
     * @param AbstractConnection $connection
     */
    public function testExecute(AbstractConnection $connection)
    {
        $connection->execute(
            sprintf(
                'DROP TABLE IF EXISTS %s',
                self::TABLE_NAME
            )
        );

        $connection->execute(
            sprintf(
                'CREATE TABLE %s (id Int32, value Int32) ENGINE=Memory;',
                self::TABLE_NAME
            )
        );

        $connection->execute(
            sprintf(
                'INSERT INTO %s VALUES (10000000, 1), (2, 20000000), (30000000, 3)',
                self::TABLE_NAME
            )
        );+

        $response = $connection->execute(sprintf(
            'SELECT * FROM %s',
            self::TABLE_NAME
        ));

        $connection->execute(sprintf(
            'DROP TABLE %s',
            self::TABLE_NAME
        ));

        $this->assertSame(
            'HTTP/1.1 200 OK',
            substr($response, 0, 15)
        );
    }
}