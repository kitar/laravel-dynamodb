<?php

namespace Kitar\Dynamodb\Tests\Integration;

use Kitar\Dynamodb\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class MarshalingTest extends IntegrationTestCase
{
    #[Test]
    public function it_round_trips_string_values()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-string',
            'name' => 'Alice',
        ]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-string']);

        $this->assertSame('Alice', $result['Item']['name']);
        $this->assertIsString($result['Item']['name']);
    }

    #[Test]
    public function it_round_trips_numeric_values()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-numeric',
            'age' => 30,
            'score' => 99.5,
        ]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-numeric']);

        $this->assertEquals(30, $result['Item']['age']);
        $this->assertEquals(99.5, $result['Item']['score']);
    }

    #[Test]
    public function it_round_trips_boolean_values()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-bool',
            'active' => true,
            'deleted' => false,
        ]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-bool']);

        $this->assertTrue($result['Item']['active']);
        $this->assertFalse($result['Item']['deleted']);
    }

    #[Test]
    public function it_round_trips_list_values()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-list',
            'tags' => ['php', 'laravel', 'dynamodb'],
        ]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-list']);

        $this->assertEquals(['php', 'laravel', 'dynamodb'], $result['Item']['tags']);
    }

    #[Test]
    public function it_round_trips_map_values()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-map',
            'address' => ['city' => 'Tokyo', 'zip' => '100-0001'],
        ]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-map']);

        $this->assertEquals('Tokyo', $result['Item']['address']['city']);
        $this->assertEquals('100-0001', $result['Item']['address']['zip']);
    }

    #[Test]
    public function it_round_trips_null_removal_via_update()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('SimpleUser')->putItem([
            'partition' => 'test-null',
            'name' => 'Alice',
            'extra' => 'to-remove',
        ]);

        // Passing null removes the attribute via REMOVE expression
        $connection->table('SimpleUser')
            ->key(['partition' => 'test-null'])
            ->updateItem(['extra' => null]);

        $result = $connection->table('SimpleUser')->getItem(['partition' => 'test-null']);

        $this->assertArrayNotHasKey('extra', $result['Item']);
        $this->assertSame('Alice', $result['Item']['name']);
    }
}
