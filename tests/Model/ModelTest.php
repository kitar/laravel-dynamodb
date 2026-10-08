<?php

namespace Kitar\Dynamodb\Tests\Model;

use Aws\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Illuminate\Database\ConnectionResolver;
use Kitar\Dynamodb\Model\KeyMissingException;
use BadMethodCallException;
use Kitar\Dynamodb\Helpers\Collection;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ModelTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown() :void
    {
        UserA::unsetEventDispatcher();
        Carbon::setTestNow();
        m::close();
    }

    /**
     * Model lifecycle events fired since recordModelEvents() was called.
     *
     * @var array
     */
    protected $firedEvents = [];

    /**
     * Set a fresh event dispatcher that records model lifecycle events
     * (e.g. "saving") into $firedEvents. Other events such as "retrieved"
     * are ignored. A listener that halts an event also stops this recorder,
     * so the halted event itself is not recorded.
     */
    protected function recordModelEvents()
    {
        $this->firedEvents = [];
        $dispatcher = new Dispatcher;
        $dispatcher->listen('eloquent.*', function ($name) {
            $event = Str::between($name, 'eloquent.', ':');
            if (in_array($event, ['saving', 'saved', 'creating', 'created', 'updating', 'updated', 'deleting', 'deleted'])) {
                $this->firedEvents[] = $event;
            }
        });
        UserA::setEventDispatcher($dispatcher);
    }

    protected function setConnectionResolver($connection)
    {
        $connectionResolver = new ConnectionResolver;
        $connectionResolver->addConnection('dynamodb', $connection);
        $connectionResolver->setDefaultConnection('dynamodb');
        UserA::setConnectionResolver($connectionResolver);
        UserB::setConnectionResolver($connectionResolver);
        UserC::setConnectionResolver($connectionResolver);
        UserE::setConnectionResolver($connectionResolver);
        UserF::setConnectionResolver($connectionResolver);
    }

    protected function newConnectionMock()
    {
        $connection = m::mock('Kitar\Dynamodb\Connection[clientQuery]', [[]]);

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
                'statuscode' => 200
            ]
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

    #[Test]
    public function it_can_set_default_sort_key()
    {
        $user = new UserC;

        $expected = [
            'sort' => 'sort_default'
        ];

        $this->assertEquals($expected, $user->attributesToArray());
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_can_process_get_key_with_primary_key()
    {
        $user1 = new UserA(['partition' => 'p']);
        $user2 = new UserA(['partition' => "0"]);
        $user3 = new UserA(['partition' => 0]);

        $this->assertEquals(['partition' => 'p'], $user1->getKey());
        $this->assertEquals(['partition' => "0"], $user2->getKey());
        $this->assertEquals(['partition' => 0], $user3->getKey());
    }

    #[Test]
    public function it_can_process_get_key_with_primary_key_and_sort_key()
    {
        $user1 = new UserB(['partition' => 'p', 'sort' => 's']);
        $user2 = new UserB(['partition' => 'p', 'sort' => "0"]);
        $user3 = new UserB(['partition' => 'p', 'sort' => 0]);

        $this->assertEquals(['partition' => 'p', 'sort' => 's'], $user1->getKey());
        $this->assertEquals(['partition' => 'p', 'sort' => "0"], $user2->getKey());
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
        $user = new UserB();

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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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
            'TableName' => 'User'
        ];

        $return = new Result([
            'Items' => [
                ['name' => ['S' => 'User 1']],
                ['name' => ['S' => 'User 2']]
            ]
        ]);

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('scan')->with($params)->andReturn($return)->once();
        $this->setConnectionResolver($connection);

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
            'TableName' => 'User'
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('scan')->with($params)->andReturn($this->sampleAwsResult())->once();
        $this->setConnectionResolver($connection);

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
                    'S' => 'p'
                ]
             ],
             'ConditionExpression' => 'attribute_not_exists(#1)',
             'ExpressionAttributeNames' => [
                 '#1' => 'partition'
             ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with($params)->once();
        $this->setConnectionResolver($connection);

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
                    'S' => 'p'
                ]
            ],
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'partition'
            ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with($params)->once();
        $this->setConnectionResolver($connection);

        UserD::create(['partition' => 'p']);
    }

    #[Test]
    public function it_cannot_save_new_instance_without_required_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

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
                    'S' => 'p'
                ]
            ],
            'UpdateExpression' => 'set #1 = :1',
            'ReturnValues' => 'UPDATED_NEW',
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
        $connection->shouldReceive('updateItem')->with($params)->andReturn($this->sampleAwsResultEmpty())->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);
        $user->timestamps = false;
        $user->name = 'foo';
        $user->save();
    }

    #[Test]
    public function it_cannot_save_existing_instance_without_required_key()
    {
        $connection = $this->newConnectionMock();
        $this->setConnectionResolver($connection);

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
                    'S' => 'p'
                ]
            ]
        ];

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('deleteItem')->with($params)->once();
        $this->setConnectionResolver($connection);

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
                    'S' => 'p'
                ]
            ]
        ])->once();
        $this->setConnectionResolver($connection);

        UserA::putItem([
            'partition' => 'p'
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
                '#2' => 'status'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test'
                ],
                ':2' => [
                    'S' => 'active'
                ]
            ]
        ])->andReturn($this->sampleAwsResult())->once();
        $this->setConnectionResolver($connection);

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
                '#2' => 'name'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test'
                ],
                ':2' => [
                    'S' => 'John'
                ]
            ]
        ])->andReturn($this->sampleAwsResult())->once();
        $this->setConnectionResolver($connection);

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
                '#3' => 'name'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'test'
                ],
                ':2' => [
                    'S' => 'active'
                ],
                ':3' => [
                    'S' => 'John'
                ]
            ]
        ])->andReturn($this->sampleAwsResult())->once();
        $this->setConnectionResolver($connection);

        UserA::keyCondition('partition', '=', 'test')->active()->byName('John')->query();
    }

    #[Test]
    public function it_can_chain_scopes_that_return_nothing()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('clientQuery')->with([
            'TableName' => 'User',
            'KeyConditionExpression' => '#1 = :1',
            'FilterExpression' => '#2 = :2 and #3 = :3',
            'ExpressionAttributeNames' => [
                '#1' => 'partition',
                '#2' => 'status',
                '#3' => 'name'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['S' => 'test'],
                ':2' => ['S' => 'active'],
                ':3' => ['S' => 'John']
            ]
        ])->andReturn($this->sampleAwsResult())->once();
        $this->setConnectionResolver($connection);

        UserA::keyCondition('partition', '=', 'test')->voidActive()->byName('John')->query();
    }

    #[Test]
    public function it_cannot_call_non_existent_scope_on_model()
    {
        $this->expectException(BadMethodCallException::class);

        UserA::nonExistentScope();
    }

    #[Test]
    public function it_fires_events_when_saving_new_instance()
    {
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->once();
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);
        $user->timestamps = false;

        $this->assertTrue($user->save());
        $this->assertSame(['saving', 'creating', 'created', 'saved'], $this->firedEvents);
        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertFalse($user->isDirty());
    }

    #[Test]
    public function it_does_not_save_new_instance_when_saving_event_returns_false()
    {
        $this->recordModelEvents();
        UserA::saving(function () {
            return false;
        });

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('putItem');
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);

        $this->assertFalse($user->save());
        $this->assertSame([], $this->firedEvents);
        $this->assertFalse($user->exists);
    }

    #[Test]
    public function it_does_not_save_new_instance_when_creating_event_returns_false()
    {
        $this->recordModelEvents();
        UserA::creating(function () {
            return false;
        });

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('putItem');
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);

        $this->assertFalse($user->save());
        $this->assertSame(['saving'], $this->firedEvents);
        $this->assertFalse($user->exists);
    }

    #[Test]
    public function it_sets_timestamps_when_saving_new_instance()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with([
            'TableName' => 'User',
            'Item' => [
                'partition' => ['S' => 'p'],
                'updated_at' => ['S' => '2026-01-02 03:04:05'],
                'created_at' => ['S' => '2026-01-02 03:04:05'],
            ],
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'partition'
            ]
        ])->once();
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);
        $user->save();

        $this->assertSame('2026-01-02 03:04:05', $user->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-02 03:04:05', $user->updated_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_fires_events_when_saving_existing_instance()
    {
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->andReturn($this->sampleAwsResultEmpty())->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);
        $user->timestamps = false;
        $user->name = 'foo';

        $this->assertTrue($user->save());
        $this->assertSame(['saving', 'updating', 'updated', 'saved'], $this->firedEvents);
        $this->assertFalse($user->wasRecentlyCreated);
        $this->assertFalse($user->isDirty());
        $this->assertSame(['name' => 'foo'], $user->getChanges());
    }

    #[Test]
    public function it_does_not_update_existing_instance_when_updating_event_returns_false()
    {
        $this->recordModelEvents();
        UserA::updating(function () {
            return false;
        });

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('updateItem');
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);
        $user->name = 'foo';

        $this->assertFalse($user->save());
        $this->assertSame(['saving'], $this->firedEvents);
        $this->assertTrue($user->isDirty('name'));
    }

    #[Test]
    public function it_does_not_call_dynamodb_when_saving_clean_existing_instance()
    {
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('updateItem');
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);

        $this->assertTrue($user->save());
        $this->assertSame(['saving', 'saved'], $this->firedEvents);
    }

    #[Test]
    public function it_updates_timestamp_when_saving_existing_instance()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => ['S' => 'p']
            ],
            'UpdateExpression' => 'set #1 = :1, #2 = :2',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'name',
                '#2' => 'updated_at'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['S' => 'foo'],
                ':2' => ['S' => '2026-01-02 03:04:05']
            ]
        ])->andReturn($this->sampleAwsResultEmpty())->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'created_at' => '2025-01-01 00:00:00']);
        $user->name = 'foo';
        $user->save();

        $this->assertSame('2025-01-01 00:00:00', $user->created_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_fires_events_when_deleting_existing_instance()
    {
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('deleteItem')->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);

        $this->assertTrue($user->delete());
        $this->assertSame(['deleting', 'deleted'], $this->firedEvents);
        $this->assertFalse($user->exists);
    }

    #[Test]
    public function it_does_not_delete_existing_instance_when_deleting_event_returns_false()
    {
        $this->recordModelEvents();
        UserA::deleting(function () {
            return false;
        });

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('deleteItem');
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p']);

        $this->assertFalse($user->delete());
        $this->assertSame([], $this->firedEvents);
        $this->assertTrue($user->exists);
    }

    #[Test]
    public function it_can_increment_existing_instance()
    {
        // The model takes the values returned by DynamoDB, and the incremented
        // column and updated_at are synced to the original attributes.
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => ['S' => 'p']
            ],
            'UpdateExpression' => 'set #1 = #1 + :1, #2 = :2',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'count',
                '#2' => 'updated_at'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['N' => '2'],
                ':2' => ['S' => '2026-01-02 03:04:05']
            ]
        ])->andReturn(new Result([
            'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['S' => '2026-01-02 03:04:05']],
            '@metadata' => ['statusCode' => 200],
        ]))->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1]);
        $result = $user->increment('count', 2);

        $this->assertSame(['count' => 10, 'updated_at' => '2026-01-02 03:04:05'], $result['Attributes']);
        $this->assertSame(['updating', 'updated'], $this->firedEvents);
        $this->assertSame(10, $user->count);
        $this->assertSame(['count' => 10, 'updated_at' => '2026-01-02 03:04:05'], $user->getChanges());
        $this->assertFalse($user->isDirty());
        $this->assertSame(10, $user->getOriginal('count'));
        $this->assertSame('2026-01-02 03:04:05', $user->updated_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_keeps_extra_columns_dirty_after_increment()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => ['S' => 'p']
            ],
            'UpdateExpression' => 'set #1 = #1 + :1, #2 = :2, #3 = :3',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'count',
                '#2' => 'updated_at',
                '#3' => 'status'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['N' => '2'],
                ':2' => ['S' => '2026-01-02 03:04:05'],
                ':3' => ['S' => 'active']
            ]
        ])->andReturn(new Result([
            'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['S' => '2026-01-02 03:04:05'], 'status' => ['S' => 'active']],
            '@metadata' => ['statusCode' => 200],
        ]))->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1, 'status' => 'inactive']);
        $user->name = 'unsaved';
        $user->increment('count', 2, ['status' => 'active']);

        $this->assertFalse($user->isDirty('count'));
        $this->assertFalse($user->isDirty('updated_at'));
        $this->assertTrue($user->isDirty('status'));
        $this->assertSame('inactive', $user->getOriginal('status'));
        $this->assertTrue($user->isDirty('name'));
    }

    #[Test]
    public function it_writes_updated_at_through_mutator_on_save_and_increment()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));
        $timestamp = Carbon::parse('2026-01-02 03:04:05')->timestamp;

        $sent = [];
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->andReturnUsing(function ($params) use (&$sent, $timestamp) {
            $sent[] = $params;

            return new Result([
                'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['N' => (string) $timestamp]],
                '@metadata' => ['statusCode' => 200],
            ]);
        })->twice();
        $this->setConnectionResolver($connection);

        $user = (new UserF)->newFromBuilder(['partition' => 'p', 'count' => 1]);
        $user->name = 'foo';
        $user->save();
        $user->increment('count');

        $savedAt = $sent[0]['ExpressionAttributeValues'][':2'];
        $incrementedAt = $sent[1]['ExpressionAttributeValues'][':2'];
        $this->assertSame(['N' => (string) $timestamp], $savedAt);
        $this->assertSame($savedAt, $incrementedAt);

        // The stored value from DynamoDB is applied as is, without running the mutator again.
        $this->assertSame($timestamp, $user->getAttributes()['updated_at']);
        $this->assertSame($timestamp, $user->getRawOriginal('updated_at'));
        $this->assertFalse($user->isDirty());
    }

    #[Test]
    public function it_does_not_write_updated_at_on_increment_when_timestamps_are_disabled()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with(m::on(function ($params) {
            return $params['UpdateExpression'] === 'set #1 = #1 + :1'
                && ! isset($params['ExpressionAttributeNames']['#2']);
        }))->andReturn(new Result([
            'Attributes' => ['count' => ['N' => '10']],
            '@metadata' => ['statusCode' => 200],
        ]))->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1]);
        $user->timestamps = false;
        $user->increment('count');

        $this->assertSame(10, $user->count);
        $this->assertFalse($user->isDirty());
    }

    #[Test]
    public function it_does_not_overwrite_updated_at_passed_in_extra_on_increment()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with(m::on(function ($params) {
            return $params['UpdateExpression'] === 'set #1 = #1 + :1, #2 = :2'
                && $params['ExpressionAttributeNames']['#2'] === 'updated_at'
                && $params['ExpressionAttributeValues'][':2'] === ['S' => '2020-05-06 07:08:09'];
        }))->andReturn(new Result([
            'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['S' => '2020-05-06 07:08:09']],
            '@metadata' => ['statusCode' => 200],
        ]))->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1]);
        $user->increment('count', 1, ['updated_at' => '2020-05-06 07:08:09']);

        $this->assertSame('2020-05-06 07:08:09', $user->getAttributes()['updated_at']);
        $this->assertTrue($user->isDirty('updated_at'));
    }

    #[Test]
    public function it_keeps_mutable_cast_object_obtained_before_increment()
    {
        $sent = [];
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->andReturnUsing(function ($params) use (&$sent) {
            $sent[] = $params;

            return new Result([
                'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['S' => '2026-01-02 03:04:05']],
                '@metadata' => ['statusCode' => 200],
            ]);
        })->twice();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1, 'options' => '{"a":1}']);
        $user->mergeCasts(['options' => AsArrayObject::class]);

        $options = $user->options;
        $user->increment('count');
        $options['color'] = 'blue';

        $user->timestamps = false;
        $user->save();

        $this->assertSame('set #1 = :1', $sent[1]['UpdateExpression']);
        $this->assertSame(['options'], array_values($sent[1]['ExpressionAttributeNames']));
        $this->assertSame(['S' => '{"a":1,"color":"blue"}'], $sent[1]['ExpressionAttributeValues'][':1']);
    }

    #[Test]
    public function it_does_not_increment_existing_instance_when_updating_event_returns_false()
    {
        $this->recordModelEvents();
        UserA::updating(function () {
            return false;
        });

        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('updateItem');
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'count' => 1]);

        $this->assertFalse($user->increment('count'));
        $this->assertSame([], $this->firedEvents);
        $this->assertSame(1, $user->count);
    }

    #[Test]
    public function it_increments_new_instance_without_firing_events()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 03:04:05'));
        $this->recordModelEvents();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => ['S' => 'p']
            ],
            'UpdateExpression' => 'set #1 = #1 + :1, #2 = :2',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'count',
                '#2' => 'updated_at'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['N' => '1'],
                ':2' => ['S' => '2026-01-02 03:04:05']
            ]
        ])->andReturn(new Result([
            'Attributes' => ['count' => ['N' => '10'], 'updated_at' => ['S' => '2026-01-02 03:04:05']],
            '@metadata' => ['statusCode' => 200],
        ]))->once();
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);
        $result = $user->increment('count');

        $this->assertSame(['count' => 10, 'updated_at' => '2026-01-02 03:04:05'], $result['Attributes']);
        $this->assertSame([], $this->firedEvents);
        $this->assertNull($user->count);
    }

    #[Test]
    public function it_cannot_increment_new_instance_without_key()
    {
        // Unlike Laravel, which would update the whole table, the key is required.
        $connection = $this->newConnectionMock();
        $connection->shouldNotReceive('updateItem');
        $this->setConnectionResolver($connection);

        $this->expectException(KeyMissingException::class);

        (new UserA)->increment('count');
    }

    #[Test]
    public function it_returns_null_when_deleting_new_instance_without_key()
    {
        $this->assertNull((new UserA)->delete());
    }

    #[Test]
    public function it_sets_unique_id_when_saving_new_instance()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with(m::on(function ($params) {
            return Str::isUuid($params['Item']['partition']['S'])
                && $params['Item']['name'] === ['S' => 'foo'];
        }))->once();
        $this->setConnectionResolver($connection);

        $user = new UserE(['name' => 'foo']);
        $user->save();

        $this->assertTrue(Str::isUuid($user->partition));
    }

    #[Test]
    public function it_does_not_overwrite_unique_id_when_already_set()
    {
        $id = (string) Str::uuid();

        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with(m::on(function ($params) use ($id) {
            return $params['Item']['partition'] === ['S' => $id];
        }))->once();
        $this->setConnectionResolver($connection);

        $user = new UserE(['partition' => $id]);
        $user->save();

        $this->assertSame($id, $user->partition);
    }

    #[Test]
    public function it_saves_mutated_cast_object_on_new_instance()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('putItem')->with([
            'TableName' => 'User',
            'Item' => [
                'partition' => ['S' => 'p'],
                'options' => ['S' => '{"a":1}'],
            ],
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'partition'
            ]
        ])->once();
        $this->setConnectionResolver($connection);

        $user = new UserA(['partition' => 'p']);
        $user->timestamps = false;
        $user->mergeCasts(['options' => AsArrayObject::class]);
        $user->options = [];
        $user->options['a'] = 1;
        $user->save();
    }

    #[Test]
    public function it_saves_mutated_cast_object_on_existing_instance()
    {
        $connection = $this->newConnectionMock();
        $connection->shouldReceive('updateItem')->with([
            'TableName' => 'User',
            'Key' => [
                'partition' => ['S' => 'p']
            ],
            'UpdateExpression' => 'set #1 = :1',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'options'
            ],
            'ExpressionAttributeValues' => [
                ':1' => ['S' => '{"a":1,"b":2}']
            ]
        ])->andReturn($this->sampleAwsResultEmpty())->once();
        $this->setConnectionResolver($connection);

        $user = (new UserA)->newFromBuilder(['partition' => 'p', 'options' => '{"a":1}']);
        $user->timestamps = false;
        $user->mergeCasts(['options' => AsArrayObject::class]);
        $user->options['b'] = 2;
        $user->save();
    }
}
