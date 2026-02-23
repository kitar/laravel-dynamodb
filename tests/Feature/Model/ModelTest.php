<?php

namespace Kitar\Dynamodb\Tests\Feature\Model;

use Aws\Result;
use BadMethodCallException;
use Kitar\Dynamodb\Helpers\Collection;
use Kitar\Dynamodb\Model\KeyMissingException;
use Kitar\Dynamodb\Tests\Model\UserA;
use Kitar\Dynamodb\Tests\Model\UserB;
use Kitar\Dynamodb\Tests\Model\UserC;
use Kitar\Dynamodb\Tests\Model\UserD;
use Kitar\Dynamodb\Tests\Model\UserX;
use Kitar\Dynamodb\Tests\TestCase;
use Mockery as m;
use PHPUnit\Framework\Attributes\Test;

class ModelTest extends TestCase
{
    protected function newConnectionMock()
    {
        $connection = m::mock('Kitar\Dynamodb\Connection[clientQuery]', [
            $this->app['config']->get('database.connections.dynamodb'),
        ]);

        $this->app['db']->extend('dynamodb', fn () => $connection);
        $this->app['db']->purge('dynamodb');

        return $connection;
    }

    protected function sampleAwsResult()
    {
        return new Result([
            'Items' => [],
            'LastEvaluatedKey' => ['id' => ['S' => '1']],
            '@metadata' => ['statusCode' => 200],
        ]);
    }

    protected function sampleAwsResultEmpty()
    {
        return new Result([
            '@metadata' => [
                'statuscode' => 200,
            ],
        ]);
    }

    #[Test]
    public function it_can_create_new_instance()
    {
        $user = new UserA;

        $this->assertFalse($user->incrementing);
        $this->assertFalse($user->exists);
        $this->assertFalse($user->wasRecentlyCreated);
        $this->assertTrue($user->timestamps);
        $this->assertEquals([], $user->attributesToArray());
    }

    #[Test]
    public function it_can_create_new_instance_with_attributes()
    {
        $user = new UserB([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n',
            'unknowun' => 'u',
        ]);

        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n',
        ], $user->attributesToArray());

        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's',
        ], $user->getKey());
    }

    #[Test]
    public function it_can_set_default_sort_key()
    {
        $user = new UserC;

        $expected = [
            'sort' => 'sort_default',
        ];

        $this->assertEquals($expected, $user->attributesToArray());
    }

    #[Test]
    public function it_can_create_new_instance_with_overriding_sort_key()
    {
        $user = new UserC([
            'partition' => 'p',
            'sort' => 's',
        ]);

        $expected = [
            'partition' => 'p',
            'sort' => 's',
        ];

        $this->assertEquals($expected, $user->attributesToArray());
        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's',
        ], $user->getKey());
    }

    #[Test]
    public function it_can_create_instance_with_existing_data()
    {
        // partition key only
        $userA = (new UserA)->newFromBuilder([
            'partition' => 'p',
            'name' => 'n',
        ]);

        $this->assertFalse($userA->incrementing);
        $this->assertTrue($userA->exists);
        $this->assertFalse($userA->wasRecentlyCreated);
        $this->assertTrue($userA->timestamps);
        $this->assertEquals([
            'partition' => 'p',
            'name' => 'n',
        ], $userA->attributesToArray());
        $this->assertEquals([
            'partition' => 'p',
        ], $userA->getKey());

        // sort key required, with sort key
        $userC = (new UserC)->newFromBuilder([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n',
        ]);

        $this->assertEquals([
            'partition' => 'p',
            'name' => 'n',
            'sort' => 's',
        ], $userC->attributesToArray());
        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's',
        ], $userC->getKey());

        // sort key required, without sort key but don't use default value
        $userC2 = (new UserC)->newFromBuilder([
            'partition' => 'p',
        ]);

        $this->assertEquals([
            'partition' => 'p',
        ], $userC2->attributesToArray());
    }

    #[Test]
    public function it_can_process_get_key_with_primary_key()
    {
        $user1 = new UserA(['partition' => 'p']);
        $user2 = new UserA(['partition' => '0']);
        $user3 = new UserA(['partition' => 0]);

        $this->assertEquals(['partition' => 'p'], $user1->getKey());
        $this->assertEquals(['partition' => '0'], $user2->getKey());
        $this->assertEquals(['partition' => 0], $user3->getKey());
    }

    #[Test]
    public function it_can_process_get_key_with_primary_key_and_sort_key()
    {
        $user1 = new UserB(['partition' => 'p', 'sort' => 's']);
        $user2 = new UserB(['partition' => 'p', 'sort' => '0']);
        $user3 = new UserB(['partition' => 'p', 'sort' => 0]);

        $this->assertEquals(['partition' => 'p', 'sort' => 's'], $user1->getKey());
        $this->assertEquals(['partition' => 'p', 'sort' => '0'], $user2->getKey());
        $this->assertEquals(['partition' => 'p', 'sort' => 0], $user3->getKey());
    }

    #[Test]
    public function get_key_raise_exception_if_primary_key_is_not_defined()
    {
        $user = new UserX;

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Primary (Partition) key is not defined.');

        $user->getKey();
    }

    #[Test]
    public function get_key_raise_exception_if_primary_key_is_missing()
    {
        $user = new UserA;

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: partition');

        $user->getKey();
    }

    #[Test]
    public function get_key_raise_exception_if_sort_key_is_missing()
    {
        $user = new UserB(['partition' => 'p']);

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: sort');

        $user->getKey();
    }

    #[Test]
    public function get_key_raise_exception_if_primary_and_sort_key_is_missing()
    {
        $user = new UserB;

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: partition, sort');

        $user->getKey();
    }

    #[Test]
    public function it_can_process_find()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);

        $user = UserA::find('p');
        $this->assertInstanceOf(UserA::class, $user);
    }

    #[Test]
    public function it_can_process_find_with_primary_key_and_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 's',
                ],
            ],
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 's',
                ],
            ],
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);

        $user = UserB::find(['partition' => 'p', 'sort' => 's']);
        $this->assertInstanceOf(UserB::class, $user);
    }

    #[Test]
    public function it_can_process_find_with_primary_key_and_default_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 'sort_default',
                ],
            ],
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 'sort_default',
                ],
            ],
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);

        $user = UserC::find('p');
        $this->assertInstanceOf(UserC::class, $user);
    }

    #[Test]
    public function it_can_process_find_with_overrided_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 's',
                ],
            ],
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
                'sort' => [
                    'S' => 's',
                ],
            ],
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);

        $user = UserC::find([
            'partition' => 'p',
            'sort' => 's',
        ]);
        $this->assertInstanceOf(UserC::class, $user);
    }

    #[Test]
    public function it_can_process_find_with_keys_not_exists()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo',
                ],
            ],
        ];

        $return = new Result([]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);

        $user = UserA::find('foo');
        $this->assertNull($user);
    }

    #[Test]
    public function it_cannot_process_find_with_empty_argument()
    {
        $this->expectException(KeyMissingException::class);
        UserA::find(null);

        $this->expectException(KeyMissingException::class);
        UserA::find('');
    }

    #[Test]
    public function it_can_process_all()
    {
        $params = [
            'TableName' => 'User',
        ];

        $return = new Result([
            'Items' => [
                ['name' => ['S' => 'User 1']],
                ['name' => ['S' => 'User 2']],
            ],
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('scan')->with($params)->andReturn($return)->once();

        $res = UserA::all();

        $this->assertSame(2, $res->count());
        $this->assertInstanceOf(Collection::class, $res);
        $this->assertInstanceOf(UserA::class, $res->first());
        $this->assertSame('User 1', $res->first()->name);
        $this->assertNull($res->getLastEvaluatedKey());
    }

    #[Test]
    public function it_can_get_last_evaluated_key()
    {
        $params = [
            'TableName' => 'User',
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('scan')->with($params)->andReturn($this->sampleAwsResult())->once();

        $res = UserA::all();

        $this->assertSame(['id' => ['S' => '1']], $res->getLastEvaluatedKey());
    }

    #[Test]
    public function it_can_save_new_instance()
    {
        $params = [
            'TableName' => 'User',
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
            ],
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with($params)->once();

        $user = new UserA(['partition' => 'p']);
        $user->timestamps = false;
        $user->save();
    }

    #[Test]
    public function it_can_static_create_new_instance()
    {
        $params = [
            'TableName' => 'User',
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
            ],
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with($params)->once();

        UserD::create(['partition' => 'p']);
    }

    #[Test]
    public function it_cannot_save_new_instance_without_required_key()
    {
        $this->newConnectionMock();

        $user = new UserA(['name' => 'foo']);

        $this->expectException(KeyMissingException::class);

        $user->save();
    }

    #[Test]
    public function it_can_save_existing_instance()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
            'UpdateExpression' => 'set #1 = :1',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'name',
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'foo',
                ],
            ],
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with($params)->andReturn($this->sampleAwsResultEmpty())->once();

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);
        $user->timestamps = false;
        $user->name = 'foo';
        $user->save();
    }

    #[Test]
    public function it_cannot_save_existing_instance_without_required_key()
    {
        $this->newConnectionMock();

        $user = (new UserA)->newFromBuilder([]);
        $user->name = 'foo';

        $this->expectException(KeyMissingException::class);

        $user->save();
    }

    #[Test]
    public function it_can_delete_existing_instance()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('deleteItem')->with($params)->once();

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);

        $user->delete();
    }

    #[Test]
    public function it_cannot_delete_without_primary_keys()
    {
        $user = (new UserC)->newFromBuilder(['sort' => 's']);

        $this->expectException(KeyMissingException::class);

        $user->delete();
    }

    #[Test]
    public function it_cannot_delete_without_sort_keys()
    {
        $user = (new UserC)->newFromBuilder(['partition' => 'p']);

        $this->expectException(KeyMissingException::class);

        $user->delete();
    }

    #[Test]
    public function it_cannot_delete_new_instance()
    {
        $user = new UserA(['partition' => 'p']);

        $result = $user->delete();

        $this->assertNull($result);
    }

    #[Test]
    public function it_can_call_allowed_builder_method()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with([
            'TableName' => 'User',
            'Item' => [
                'partition' => [
                    'S' => 'p',
                ],
            ],
        ])->once();

        UserA::putItem([
            'partition' => 'p',
        ]);
    }

    #[Test]
    public function it_cannot_call_disallowed_builder_method()
    {
        $this->expectException(BadMethodCallException::class);

        UserA::clientQuery();
    }

    #[Test]
    public function it_can_call_named_scopes_on_model()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('clientQuery')->with([
            'TableName' => 'User',
            'KeyConditionExpression' => '#1 = :1',
            'FilterExpression' => '#2 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
                '#2' => 'status',
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test',
                ],
                ':2' => [
                    'S' => 'active',
                ],
            ],
        ])->andReturn($this->sampleAwsResult())->once();

        UserA::keyCondition('partition', '=', 'test')->active()->query();
    }

    #[Test]
    public function it_can_call_named_scopes_with_parameters_on_model()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('clientQuery')->with([
            'TableName' => 'User',
            'KeyConditionExpression' => '#1 = :1',
            'FilterExpression' => '#2 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
                '#2' => 'name',
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test',
                ],
                ':2' => [
                    'S' => 'John',
                ],
            ],
        ])->andReturn($this->sampleAwsResult())->once();

        UserA::keyCondition('partition', '=', 'test')->byName('John')->query();
    }

    #[Test]
    public function it_can_chain_multiple_scopes()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('clientQuery')->with([
            'TableName' => 'User',
            'KeyConditionExpression' => '#1 = :1',
            'FilterExpression' => '#2 = :2 and #3 = :3',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
                '#2' => 'status',
                '#3' => 'name',
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test',
                ],
                ':2' => [
                    'S' => 'active',
                ],
                ':3' => [
                    'S' => 'John',
                ],
            ],
        ])->andReturn($this->sampleAwsResult())->once();

        UserA::keyCondition('partition', '=', 'test')->active()->byName('John')->query();
    }

    #[Test]
    public function it_cannot_call_non_existent_scope_on_model()
    {
        $this->expectException(BadMethodCallException::class);

        UserA::nonExistentScope();
    }
}
