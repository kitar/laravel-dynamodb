<?php

namespace Kitar\Dynamodb\Tests;

use Aws\DynamoDb\DynamoDbClient;
use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Query\Builder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection([]);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    #[Test]
    public function it_creates_connection()
    {
        $this->assertInstanceOf(Connection::class, $this->connection);
    }

    #[Test]
    public function it_returns_dynamodb_client()
    {
        $this->assertInstanceOf(
            DynamoDbClient::class,
            $this->connection->getClient()
        );
    }

    #[Test]
    public function it_returns_driver_name()
    {
        $this->assertEquals(
            'dynamodb',
            $this->connection->getDriverName()
        );
    }

    #[Test]
    public function it_keeps_dynamodb_client_on_disconnect()
    {
        $this->connection->disconnect();

        $this->assertNotNull($this->connection->getClient());
    }

    #[Test]
    public function it_returns_query_builder_instance()
    {
        $query = $this->connection->table('test');

        $this->assertInstanceOf(Builder::class, $query);

        $this->assertEquals('test', $query->from);
    }

    #[Test]
    public function it_can_call_client_query()
    {
        $client = m::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->with([
            'TableName' => 'User',
        ])->once();

        $connection = new Connection([]);
        $connection->setClient($client);
        $connection->clientQuery([
            'TableName' => 'User',
        ]);
    }

    #[Test]
    public function it_can_forward_call_to_dynamodb_client()
    {
        $client = m::mock(DynamoDbClient::class);
        $client->shouldReceive('getItem')->with([
            'TableName' => 'User',
        ])->once();

        $connection = new Connection([]);
        $connection->setClient($client);
        $connection->getItem([
            'TableName' => 'User',
        ]);
    }

    #[Test]
    public function it_prepends_default_protocol_if_not_given()
    {
        $connection = new Connection(['endpoint' => 'examples.com']);
        $this->assertEquals($connection->getClient()->getEndpoint()->getScheme(), 'https');
        $this->assertEquals($connection->getClient()->getEndpoint()->getHost(), 'examples.com');
        $this->assertEquals($this->connection->getClient()->getEndpoint()->getScheme(), 'https');
        $this->assertEquals($this->connection->getClient()->getEndpoint()->getHost(), 'dynamodb.us-east-1.amazonaws.com');
    }

    #[Test]
    public function it_dont_prepends_default_protocol_if_http_given()
    {
        $connection = new Connection(['endpoint' => 'http://examples.com']);
        $this->assertEquals($connection->getClient()->getEndpoint()->getScheme(), 'http');
        $this->assertEquals($connection->getClient()->getEndpoint()->getHost(), 'examples.com');
    }

    #[Test]
    public function it_dont_prepends_default_protocol_if_https_given()
    {
        $connection = new Connection(['endpoint' => 'https://examples.com']);
        $this->assertEquals($connection->getClient()->getEndpoint()->getScheme(), 'https');
        $this->assertEquals($connection->getClient()->getEndpoint()->getHost(), 'examples.com');
    }
}
