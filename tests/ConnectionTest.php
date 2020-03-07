<?php

namespace Kitar\Dynamodb\Tests;

use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Query\Builder;
use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    protected $connection;

    protected function setUp() :void
    {
        $this->connection = new Connection([]);
    }

    /** @test */
    public function it_creates_connection()
    {
        $this->assertInstanceOf(Connection::class, $this->connection);
    }

    /** @test */
    public function it_returns_dynamodb_client()
    {
        $this->assertInstanceOf(
            DynamoDbClient::class,
            $this->connection->getDynamodbClient()
        );
    }

    /** @test */
    public function it_returns_driver_name()
    {
        $this->assertEquals(
            'dynamodb',
            $this->connection->getDriverName()
        );
    }

    /** @test */
    public function it_destroys_connection()
    {
        $this->connection->disconnect();

        $this->assertNull($this->connection->getDynamodbClient());
    }

    /** @test */
    public function it_returns_query_builder_instance()
    {
        $query = $this->connection->table('test');

        $this->assertInstanceOf(Builder::class, $query);

        $this->assertEquals('test', $query->from);
    }
}
