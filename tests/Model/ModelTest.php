<?php

namespace Kitar\Dynamodb\Tests\Model;

use Aws\Result;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Illuminate\Database\ConnectionResolver;
use Kitar\Dynamodb\Model\KeyMissingException;
use BadMethodCallException;

class ModelTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown() :void
    {
        m::close();
    }

    protected function setConnectionResolver($connection)
    {
        $connectionResolver = new ConnectionResolver;
        $connectionResolver->addConnection('dynamodb', $connection);
        $connectionResolver->setDefaultConnection('dynamodb');
        UserA::setConnectionResolver($connectionResolver);
        UserB::setConnectionResolver($connectionResolver);
        UserC::setConnectionResolver($connectionResolver);
    }

    protected function newConnectionMock()
    {
        $connection = m::mock('Kitar\Dynamodb\Connection[clientQuery]', [[]]);

        return $connection;
    }

    /** @test */
    public function it_can_create_new_instance()
    {
        $user = new UserA;

        $this->assertFalse($user->incrementing);
        $this->assertFalse($user->exists);
        $this->assertFalse($user->wasRecentlyCreated);
        $this->assertTrue($user->timestamps);
        $this->assertEquals([], $user->attributesToArray());
    }

    /** @test */
    public function it_can_create_new_instance_with_attributes()
    {
        $user = new UserB([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n',
            'unknowun' => 'u'
        ]);

        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n'
        ], $user->attributesToArray());

        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's'
        ], $user->getKey());
    }

    /** @test */
    public function it_can_set_default_sort_key()
    {
        $user = new UserC;

        $expected = [
            'sort' => 'sort_default'
        ];

        $this->assertEquals($expected, $user->attributesToArray());
    }

    /** @test */
    public function it_can_create_new_instance_with_overriding_sort_key()
    {
        $user = new UserC([
            'partition' => 'p',
            'sort' => 's'
        ]);

        $expected = [
            'partition' => 'p',
            'sort' => 's'
        ];

        $this->assertEquals($expected, $user->attributesToArray());
        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's'
        ], $user->getKey());
    }

    /** @test */
    public function it_can_create_instance_with_existing_data()
    {
        // partition key only
        $userA = (new UserA)->newFromBuilder([
            'partition' => 'p',
            'name' => 'n'
        ]);

        $this->assertFalse($userA->incrementing);
        $this->assertTrue($userA->exists);
        $this->assertFalse($userA->wasRecentlyCreated);
        $this->assertTrue($userA->timestamps);
        $this->assertEquals([
            'partition' => 'p',
            'name' => 'n'
        ], $userA->attributesToArray());
        $this->assertEquals([
            'partition' => 'p'
        ], $userA->getKey());

        // sort key required, with sort key
        $userC = (new UserC)->newFromBuilder([
            'partition' => 'p',
            'sort' => 's',
            'name' => 'n'
        ]);

        $this->assertEquals([
            'partition' => 'p',
            'name' => 'n',
            'sort' => 's'
        ], $userC->attributesToArray());
        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's'
        ], $userC->getKey());

        // sort key required, without sort key but don't use default value
        $userC2 = (new UserC)->newFromBuilder([
            'partition' => 'p'
        ]);

        $this->assertEquals([
            'partition' => 'p'
        ], $userC2->attributesToArray());
    }

    /** @test */
    public function it_can_process_get_key_with_primary_key()
    {
        $user = new UserA(['partition' => 'p']);

        $this->assertEquals(['partition' => 'p'], $user->getKey());
    }


    /** @test */
    public function it_can_process_get_key_with_primary_key_and_sort_key()
    {
        $user = new UserB(['partition' => 'p', 'sort' => 's']);

        $this->assertEquals([
            'partition' => 'p',
            'sort' => 's'
        ], $user->getKey());
    }

    /** @test */
    public function get_key_raise_exception_if_primary_key_is_not_defined()
    {
        $user = new UserX;

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Primary (Partition) key is not defined.');

        $user->getKey();
    }

    /** @test */
    public function get_key_raise_exception_if_primary_key_is_missing()
    {
        $user = new UserA;

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: partition');

        $user->getKey();
    }

    /** @test */
    public function get_key_raise_exception_if_sort_key_is_missing()
    {
        $user = new UserB(['partition' => 'p']);

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: sort');

        $user->getKey();
    }

    /** @test */
    public function get_key_raise_exception_if_primary_and_sort_key_is_missing()
    {
        $user = new UserB();

        $this->expectException(KeyMissingException::class);
        $this->expectExceptionMessage('Some required key(s) has no value: partition, sort');

        $user->getKey();
    }

    /** @test */
    public function it_can_process_find()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ]
            ]
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ]
            ]
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        $user = UserA::find('p');
        $this->assertInstanceOf(UserA::class, $user);
    }

    /** @test */
    public function it_can_process_find_with_primary_key_and_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 's'
                ]
            ]
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 's'
                ]
            ]
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        $user = UserB::find(['partition' => 'p', 'sort' => 's']);
        $this->assertInstanceOf(UserB::class, $user);
    }

    /** @test */
    public function it_can_process_find_with_primary_key_and_default_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 'sort_default'
                ]
            ]
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 'sort_default'
                ]
            ]
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        $user = UserC::find('p');
        $this->assertInstanceOf(UserC::class, $user);
    }

    /** @test */
    public function it_can_process_find_with_overrided_sort_key()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 's'
                ]
            ]
        ];

        $return = new Result([
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ],
                'sort' => [
                    'S' => 's'
                ]
            ]
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        $user = UserC::find([
            'partition' => 'p',
            'sort' => 's'
        ]);
        $this->assertInstanceOf(UserC::class, $user);
    }

    /** @test */
    public function it_can_process_find_with_keys_not_exists()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'foo'
                ]
            ]
        ];

        $return = new Result([]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('getItem')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        $user = UserA::find('foo');
        $this->assertNull($user);
    }

    /** @test */
    public function it_cannot_process_find_with_empty_argument()
    {
        $this->expectException(KeyMissingException::class);
        UserA::find(null);

        $this->expectException(KeyMissingException::class);
        UserA::find('');
    }

    /** @test */
    public function it_can_process_all()
    {
        $params = [
            'TableName' => 'User'
        ];

        $return = new Result([
            'Items' => []
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('scan')->with($params)->andReturn($return);
        $this->setConnectionResolver($connection);

        UserA::all();
    }

    /** @test */
    public function it_can_save_new_instance()
    {
        $params = [
            'TableName' => 'User',
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ]
             ],
             'ConditionExpression' => 'attribute_not_exists(#1)',
             'ExpressionAttributeNames' => [
                 '#1' => 'partition'
             ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with($params);
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);
        $user->timestamps = false;
        $user->save();
    }

    /** @test */
    public function it_cannot_save_new_instance_without_required_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

        $user = new UserA(['name' => 'foo']);

        $this->expectException(KeyMissingException::class);

        $user->save();
    }

    /** @test */
    public function it_can_save_existing_instance()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ]
            ],
            'UpdateExpression' => 'set #1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'name'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'foo'
                ]
            ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with($params);
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);
        $user->timestamps = false;
        $user->name = 'foo';
        $user->save();
    }

    /** @test */
    public function it_cannot_save_existing_instance_without_required_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder([]);
        $user->name = 'foo';

        $this->expectException(KeyMissingException::class);

        $user->save();
    }

    /** @test */
    public function it_can_delete_existing_instance()
    {
        $params = [
            'TableName' => 'User',
            'Key' => [
                'partition' => [
                    'S' => 'p'
                ]
            ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('deleteItem')->with($params);
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);

        $user->delete();
    }

    /** @test */
    public function it_cannot_delete_without_primary_keys()
    {
        $user = (new UserC)->newFromBuilder(['sort' => 's']);

        $this->expectException(KeyMissingException::class);

        $user->delete();
    }

    /** @test */
    public function it_cannot_delete_without_sort_keys()
    {
        $user = (new UserC)->newFromBuilder(['partition' => 'p']);

        $this->expectException(KeyMissingException::class);

        $user->delete();
    }

    /** @test */
    public function it_cannot_delete_new_instance()
    {
        $user = new UserA(['partition' => 'p']);

        $result = $user->delete();

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_call_allowed_builder_method()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with([
            'TableName' => 'User',
            'Item' => [
                'partition' => [
                    'S' => 'p'
                ]
            ]
        ]);
        $this->setConnectionResolver($connection);

        UserA::putItem([
            'partition' => 'p'
        ]);
    }

    /** @test */
    public function it_cannot_call_disallowed_builder_method()
    {
        $this->expectException(BadMethodCallException::class);

        UserA::clientQuery();
    }
}
