<?php

namespace Kitar\Dynamodb\Tests\Query;

use Kitar\Dynamodb\Connection;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    protected $builder;

    protected function setUp() :void
    {
        $this->builder = (new Connection([]))->table('test')->dryRun();
    }

    /** @test */
    public function dry_run_is_disabled_by_default()
    {
        $builder = (new Connection([]))->table('test');
        $this->assertFalse($builder->dry_run);
    }

    /** @test */
    public function it_can_set_index()
    {
        $params = [
            'TableName' => 'test',
            'IndexName' => 'test_index'
        ];

        $query = $this->builder->index('test_index')->query();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_key()
    {
        $params = [
            'TableName' => 'test',
            'Key' => [
                'foo' => [
                    'S' => 'bar'
                ]
            ]
        ];

        $query = $this->builder->key(['foo' => 'bar'])->query();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_consistent_read()
    {
        $query = $this->builder
                      ->consistentRead()
                      ->getItem(['foo' => 'bar']);

        $this->assertTrue($query['params']['ConsistentRead']);
    }

    /** @test */
    public function it_can_process_filter()
    {
        $params = [
            'TableName' => 'test',
            'FilterExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'foo'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $query = $this->builder
                      ->filter('foo', '=', 'bar')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_condition()
    {
        $params = [
            'TableName' => 'test',
            'ConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'foo'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $query = $this->builder
                      ->condition('foo', '=', 'bar')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_key_condition()
    {
        $params = [
            'TableName' => 'test',
            'KeyConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'foo'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $query = $this->builder
                      ->keyCondition('foo', '=', 'bar')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_key_condition_and_filter_at_the_same_time()
    {
        $params = [
            'TableName' => 'test',
            'KeyConditionExpression' => '#1 = :1 and #2 = :2',
            'FilterExpression' => '#3 > :3',
            'ExpressionAttributeNames' => [
                '#1' => 'ForumName',
                '#2' => 'Subject',
                '#3' => 'Views'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Amazon DynamoDB'
                ],
                ':2' => [
                    'S' => 'DynamoDB Thread 1'
                ],
                ':3' => [
                    'N' => '3'
                ]
            ]
        ];

        $query = $this->builder
                      ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                      ->keyCondition('Subject', '=', 'DynamoDB Thread 1')
                      ->filter('Views', '>', 3)
                      ->query();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_or_filter()
    {
        $params = [
            'TableName' => 'test',
            'FilterExpression' => '#1 > :1 or #1 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'Views'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '3'
                ],
                ':2' => [
                    'N' => '0'
                ]
            ]
        ];

        $query = $this->builder
                      ->filter('Views', '>', 3)
                      ->orFilter('Views', '=', 0)
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_or_condition()
    {
        $params = [
            'TableName' => 'test',
            'ConditionExpression' => '#1 > :1 or #1 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'Views'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '3'
                ],
                ':2' => [
                    'N' => '0'
                ]
            ]
        ];

        $query = $this->builder
                      ->condition('Views', '>', 3)
                      ->orCondition('Views', '=', 0)
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_filter_in()
    {
        $params = [
            'TableName' => 'test',
            'FilterExpression' => '(#1 in (:1, :2, :3))',
            'ExpressionAttributeNames' => [
                '#1' => 'Id'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '101'
                ],
                ':2' => [
                    'N' => '102'
                ],
                ':3' => [
                    'N' => '201'
                ]
            ]
        ];

        $query = $this->builder
                      ->filterIn('Id', [101, 102, 201])
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_filter_between()
    {
        $params = [
            'TableName' => 'test',
            'FilterExpression' => '(#1 between :1 and :2)',
            'ExpressionAttributeNames' => [
                '#1' => 'Id'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '101'
                ],
                ':2' => [
                    'N' => '103'
                ]
            ]
        ];

        $query = $this->builder
                      ->filterBetween('Id', [101, 103])
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_get_item()
    {
        $method = 'getItem';
        $params = [
            'TableName' => 'test',
            'Key' => [
                'foo' => ['S' => 'bar'],
                'baz' => ['N' => 123]
            ]
        ];
        $processor = 'processSingleItem';

        $query = $this->builder
                      ->key(['foo' => 'bar', 'baz' => 123])
                      ->getItem();

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_process_get_item_with_key()
    {
        $params = [
            'TableName' => 'test',
            'Key' => [
                'foo' => ['S' => 'bar'],
            ]
        ];

        $query = $this->builder
                      ->getItem(['foo' => 'bar']);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_get_item_with_expressions()
    {
        $params = [
            'TableName' => 'test',
            'Key' => [
                'foo' => ['S' => 'bar'],
            ],
            'ProjectionExpression' => '#1, #2',
            'ExpressionAttributeNames' => [
                '#1' => 'id',
                '#2' => 'name'
            ]
        ];

        $query = $this->builder
                      ->select(['id', 'name'])
                      ->getItem(['foo' => 'bar']);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_put_item()
    {
        $method = 'putItem';
        $params = [
            'TableName' => 'test',
            'Item' => [
                'foo' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $query = $this->builder
                      ->putItem(['foo' => 'bar']);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertNull($query['processor']);
    }

    /** @test */
    public function it_can_process_delete_item()
    {
        $method = 'deleteItem';
        $params = [
            'TableName' => 'test',
            'Key' => [
                'foo' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $query = $this->builder
                      ->deleteItem(['foo' => 'bar']);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertNull($query['processor']);
    }

    /** @test */
    public function it_can_process_query()
    {
        $method = 'clientQuery';
        $params = [
            'TableName' => 'test',
            'KeyConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'foo',
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'bar'
                ]
            ]
        ];
        $processor = 'processMultipleItems';

        $query = $this->builder
                      ->keyCondition('foo', '=', 'bar')
                      ->query();

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_process_scan()
    {
        $method = 'scan';
        $params = [
            'TableName' => 'test'
        ];
        $processor = 'processMultipleItems';

        $query = $this->builder
                      ->scan();

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }
}
