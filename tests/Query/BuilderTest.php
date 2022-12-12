<?php

namespace Kitar\Dynamodb\Tests\Query;

use Aws\Result;
use BadMethodCallException;
use Mockery as m;
use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Model\Model;
use Kitar\Dynamodb\Query\Builder;
use Kitar\Dynamodb\Query\Grammar;
use Kitar\Dynamodb\Query\Processor;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class Product extends Model
{
}

class BuilderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected $connection;

    protected function setUp() :void
    {
        $this->connection = new Connection([]);
    }

    protected function tearDown() :void
    {
        m::close();
    }

    protected function newQuery($table_name)
    {
        return $this->connection->table($table_name)->dryRun();
    }

    /** @test */
    public function dry_run_is_disabled_by_default()
    {
        $builder = (new Connection([]))->table('ProductCatalog');

        $this->assertFalse($builder->dry_run);
    }

    /** @test */
    public function it_can_set_index()
    {
        $params = [
            'TableName' => 'Reply',
            'IndexName' => 'PostedBy-Message-index',
            'KeyConditionExpression' => '#1 = :1 and #2 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'PostedBy',
                '#2' => 'Message'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'User A'
                ],
                ':2' => [
                    'S' => 'DynamoDB Thread 1 Reply 1 text'
                ]
            ]
        ];

        $query = $this->newQuery('Reply')
                      ->index('PostedBy-Message-index')
                      ->keyCondition('PostedBy', '=', 'User A')
                      ->keyCondition('Message', '=', 'DynamoDB Thread 1 Reply 1 text')
                      ->query();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_key()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'Key' => [
                'Id' => [
                    'N' => '101'
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')->key(['Id' => 101])->getItem();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_limit()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'Limit' => 5
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->limit(5)
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_scan_index_forward()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'ScanIndexForward' => false,
            'Key' => [
                'Id' => [
                    'N' => '101'
                ]
            ]
        ];
        $query = $this->newQuery('ProductCatalog')
                      ->scanIndexForward(false)
                      ->getItem(['Id'=> 101]);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_exclusive_start_key()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'ExclusiveStartKey' => [
                'Id' => [
                    'N' => '101'
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->ExclusiveStartKey([
                        'Id' => [
                            'N' => '101'
                        ]
                      ])->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_consistent_read()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'ConsistentRead' => true,
            'Key' => [
                'Id' => [
                    'N' => '101'
                ]
            ]
        ];
        $query = $this->newQuery('ProductCatalog')
                      ->consistentRead()
                      ->getItem(['Id'=> 101]);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_set_model_class()
    {
        $query = $this->newQuery('ProductCatalog')
                      ->usingModel(Product::class);

        $this->assertEquals(Product::class, $query->model_class);
    }

    /** @test */
    public function it_can_process_filter()
    {
        $params = [
            'TableName' => 'Thread',
            'FilterExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'ForumName'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Amazon DynamoDB'
                ]
            ]
        ];
        $query = $this->newQuery('Thread')
                      ->filter('ForumName', '=', 'Amazon DynamoDB')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_filter_with_short_syntax()
    {
        $params = [
            'TableName' => 'Thread',
            'FilterExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'ForumName'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Amazon DynamoDB'
                ]
            ]
        ];
        $query = $this->newQuery('Thread')
                      ->filter('ForumName', 'Amazon DynamoDB')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_condition()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'ConditionExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Id'
            ],
            'Item' => [
                'Id' => [
                    'N' => '101'
                ]
            ]
        ];
        $query = $this->newQuery('ProductCatalog')
                      ->condition('Id', 'attribute_not_exists')
                      ->putItem(['Id' => 101]);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_key_condition()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'KeyConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'Id'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '101'
                ]
            ]
        ];
        $query = $this->newQuery('ProductCatalog')
                      ->keyCondition('Id', '=', 101)
                      ->query();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_key_condition_and_filter_at_the_same_time()
    {
        $params = [
            'TableName' => 'Thread',
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

        $query = $this->newQuery('Thread')
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
            'TableName' => 'ProductCatalog',
            'FilterExpression' => '#1 = :1 or #1 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'BicycleType'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Mountain'
                ],
                ':2' => [
                    'S' => 'Hybrid'
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->filter('BicycleType', '=', 'Mountain')
                      ->orFilter('BicycleType', '=', 'Hybrid')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_or_condition()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'ConditionExpression' => 'attribute_not_exists(#1) or attribute_not_exists(#2)',
            'Item' => [
                'Id' => [
                    'N' => '101'
                ]
            ],
            'ExpressionAttributeNames' => [
                '#1' => 'Id',
                '#2' => 'Price'
            ]
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->condition('Id', 'attribute_not_exists')
                      ->orCondition('Price', 'attribute_not_exists')
                      ->putItem(['Id' => 101]);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_filter_in()
    {
        $params = [
            'TableName' => 'ProductCatalog',
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

        $query = $this->newQuery('ProductCatalog')
                      ->filterIn('Id', [101, 102, 201])
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_filter_between()
    {
        $params = [
            'TableName' => 'ProductCatalog',
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

        $query = $this->newQuery('ProductCatalog')
                      ->filterBetween('Id', [101, 103])
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_nested_filters()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'FilterExpression' => '(((#1 = :1 and #2 = :2) or (#1 = :3 and #3 < :4)) or #3 >= :5)',
            'ExpressionAttributeNames' => [
                '#1' => 'ProductCategory',
                '#2' => 'Brand',
                '#3' => 'Price'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Bicycle'
                ],
                ':2' => [
                    'S' => 'Mountain A'
                ],
                ':3' => [
                    'S' => 'Book'
                ],
                ':4' => [
                    'N' => 10
                ],
                ':5' => [
                    'N' => 500
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')->filter(function ($query) {
            $query->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('ProductCategory', 'Bicycle');
                    $query->where('Brand', 'Mountain A');
                });
                $query->orWhere(function ($query) {
                    $query->where('ProductCategory', 'Book');
                    $query->where('Price', '<', 10);
                });
            });
            $query->orWhere('Price', '>=', 500);
        })->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_attribute_exists_function()
    {
        $params = [
            'TableName' => 'Forum',
            'FilterExpression' => 'attribute_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Messages'
            ]
        ];

        $query = $this->newQuery('Forum')
                      ->filter('Messages', 'attribute_exists')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_attribute_not_exists_function()
    {
        $params = [
            'TableName' => 'Forum',
            'FilterExpression' => 'attribute_not_exists(#1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Messages'
            ]
        ];

        $query = $this->newQuery('Forum')
                      ->filter('Messages', 'attribute_not_exists')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_attribute_type_function()
    {
        $params = [
            'TableName' => 'Forum',
            'FilterExpression' => 'attribute_type(#1, :1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Messages'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'N'
                ]
            ]
        ];

        $query = $this->newQuery('Forum')
                      ->filter('Messages', 'attribute_type', 'N')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_begins_with_function()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'FilterExpression' => 'begins_with(#1, :1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Title'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Book'
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->filter('Title', 'begins_with', 'Book')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_contains_function()
    {
        $params = [
            'TableName' => 'ProductCatalog',
            'FilterExpression' => 'contains(#1, :1)',
            'ExpressionAttributeNames' => [
                '#1' => 'Title'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'Bike'
                ]
            ]
        ];

        $query = $this->newQuery('ProductCatalog')
                      ->filter('Title', 'contains', 'Bike')
                      ->scan();

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_get_item()
    {
        $method = 'getItem';
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => ['S' => 'Amazon DynamoDB'],
                'Subject' => ['S' => 'DynamoDB Thread 1']
            ]
        ];
        $processor = 'processSingleItem';

        $query = $this->newQuery('Thread')
                      ->key(['ForumName' => 'Amazon DynamoDB', 'Subject' => 'DynamoDB Thread 1'])
                      ->getItem();

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_process_get_item_with_key()
    {
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => ['S' => 'Amazon DynamoDB'],
                'Subject' => ['S' => 'DynamoDB Thread 1']
            ]
        ];

        $query = $this->newQuery('Thread')
                      ->getItem(['ForumName' => 'Amazon DynamoDB', 'Subject' => 'DynamoDB Thread 1']);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_get_item_with_expressions()
    {
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => ['S' => 'Amazon DynamoDB'],
                'Subject' => ['S' => 'DynamoDB Thread 1']
            ],
            'ProjectionExpression' => '#1, #2',
            'ExpressionAttributeNames' => [
                '#1' => 'LastPostedBy',
                '#2' => 'LastPostedDateTime'
            ]
        ];

        $query = $this->newQuery('Thread')
                      ->select(['LastPostedBy', 'LastPostedDateTime'])
                      ->getItem(['ForumName' => 'Amazon DynamoDB', 'Subject' => 'DynamoDB Thread 1']);

        $this->assertEquals($params, $query['params']);
    }

    /** @test */
    public function it_can_process_put_item()
    {
        $method = 'putItem';
        $params = [
            'TableName' => 'Thread',
            'Item' => [
                'ForumName' => [
                    'S' => 'Laravel'
                ],
                'Subject' => [
                    'S' => 'Laravel Thread 1'
                ]
            ]
        ];
        $query = $this->newQuery('Thread')
                      ->putItem([
                          'ForumName' => 'Laravel',
                          'Subject' => 'Laravel Thread 1'
                      ]);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertNull($query['processor']);
    }

    /** @test */
    public function it_can_process_delete_item()
    {
        $method = 'deleteItem';
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => [
                    'S' => 'Laravel'
                ],
                'Subject' => [
                    'S' => 'Laravel Thread 1'
                ]
            ]
        ];
        $query = $this->newQuery('Thread')
                      ->deleteItem([
                          'ForumName' => 'Laravel',
                          'Subject' => 'Laravel Thread 1'
                      ]);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertNull($query['processor']);
    }

    /** @test */
    public function it_can_process_update_item()
    {
        $method = 'updateItem';
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => [
                    'S' => 'Laravel'
                ],
                'Subject' => [
                    'S' => 'Laravel Thread 1'
                ]
            ],
            'UpdateExpression' => 'set #1 = :1, #2 = :2 remove #3, #4',
            'ReturnValues' => 'UPDATED_NEW',
            'ExpressionAttributeNames' => [
                '#1' => 'LastPostedBy',
                '#2' => 'Replies',
                '#3' => 'Tags',
                '#4' => 'Views'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'S' => 'User A'
                ],
                ':2' => [
                    'N' => '1'
                ]
            ]
        ];
        $processor = 'processSingleItem';

        $query = $this->newQuery('Thread')
             ->key([
                 'ForumName' => 'Laravel',
                 'Subject' => 'Laravel Thread 1'
             ])->updateItem([
                'LastPostedBy' => 'User A',
                'Replies' => 1,
                'Tags' => null,
                'Views' => null
             ]);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_increment_value_of_attribute()
    {
        $method = 'updateItem';
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => [
                    'S' => 'Laravel'
                ],
                'Subject' => [
                    'S' => 'Laravel Thread 1'
                ]
            ],
            'UpdateExpression' => 'set #1 = #1 + :1, #2 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'Replies',
                '#2' => 'LastPostedBy'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '2'
                ],
                ':2' => [
                    'S' => 'User A'
                ]
            ],
            'ReturnValues' => 'UPDATED_NEW'
        ];

        $query = $this->newQuery('Thread')
            ->key([
                'ForumName' => 'Laravel',
                'Subject' => 'Laravel Thread 1'
            ])->increment('Replies', 2, [
                'LastPostedBy' => 'User A'
            ]);

        $processor = 'processSingleItem';

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_decrement_value_of_attribute()
    {
        $method = 'updateItem';
        $params = [
            'TableName' => 'Thread',
            'Key' => [
                'ForumName' => [
                    'S' => 'Laravel'
                ],
                'Subject' => [
                    'S' => 'Laravel Thread 1'
                ]
            ],
            'UpdateExpression' => 'set #1 = #1 - :1, #2 = :2',
            'ExpressionAttributeNames' => [
                '#1' => 'Replies',
                '#2' => 'LastPostedBy'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '2'
                ],
                ':2' => [
                    'S' => 'User A'
                ]
            ],
            'ReturnValues' => 'UPDATED_NEW'
        ];

        $processor = 'processSingleItem';

        $query = $this->newQuery('Thread')
            ->key([
                'ForumName' => 'Laravel',
                'Subject' => 'Laravel Thread 1'
            ])->decrement('Replies', 2, [
                'LastPostedBy' => 'User A'
            ]);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_set_single_attribute()
    {
        $query = $this->newQuery('Thread')
             ->key([
                 'ForumName' => 'Laravel',
                 'Subject' => 'Laravel Thread 1'
             ])->updateItem([
                'LastPostedBy' => 'User A',
             ]);

        $this->assertEquals(
            $query['params']['UpdateExpression'],
            'set #1 = :1'
        );
    }

    /** @test */
    public function it_can_set_multiple_attributes()
    {
        $query = $this->newQuery('Thread')
             ->key([
                 'ForumName' => 'Laravel',
                 'Subject' => 'Laravel Thread 1'
             ])->updateItem([
                'LastPostedBy' => 'User A',
                'Replies' => 1,
             ]);

        $this->assertEquals(
            $query['params']['UpdateExpression'],
            'set #1 = :1, #2 = :2'
        );
    }

    /** @test */
    public function it_can_remove_single_attribute()
    {
        $query = $this->newQuery('Thread')
             ->key([
                 'ForumName' => 'Laravel',
                 'Subject' => 'Laravel Thread 1'
             ])->updateItem([
                'Tags' => null,
             ]);

        $this->assertEquals(
            $query['params']['UpdateExpression'],
            'remove #1'
        );
    }

    /** @test */
    public function it_can_remove_multiple_attributes()
    {
        $query = $this->newQuery('Thread')
             ->key([
                 'ForumName' => 'Laravel',
                 'Subject' => 'Laravel Thread 1'
             ])->updateItem([
                'Tags' => null,
                'Views' => null
             ]);

        $this->assertEquals(
            $query['params']['UpdateExpression'],
            'remove #1, #2'
        );
    }

    /** @test */
    public function it_can_process_query()
    {
        $method = 'clientQuery';
        $params = [
            'TableName' => 'ProductCatalog',
            'KeyConditionExpression' => '#1 = :1',
            'ExpressionAttributeNames' => [
                '#1' => 'Id'
            ],
            'ExpressionAttributeValues' => [
                ':1' => [
                    'N' => '101'
                ]
            ]
        ];
        $processor = 'processMultipleItems';
        $query = $this->newQuery('ProductCatalog')
                      ->keyCondition('Id', '=', 101)
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
            'TableName' => 'Forum'
        ];
        $processor = 'processMultipleItems';

        $query = $this->newQuery('Forum')
                      ->scan();

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_process_scan_with_columns_specified()
    {
        $method = 'scan';
        $params = [
            'TableName' => 'Forum',
            'ProjectionExpression' => '#1, #2',
            'ExpressionAttributeNames' => [
                '#1' => 'foo',
                '#2' => 'bar'
            ]
        ];
        $processor = 'processMultipleItems';

        $query = $this->newQuery('Forum')
                      ->scan(['foo', 'bar']);

        $this->assertEquals($method, $query['method']);
        $this->assertEquals($params, $query['params']);
        $this->assertEquals($processor, $query['processor']);
    }

    /** @test */
    public function it_can_process_process()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('scan')
                   ->with(['TableName' => 'Forum'])
                   ->andReturn(new Result(['Items' => []]))
                   ->once();

        $query = new Builder($connection, new Grammar, new Processor);

        $query->from('Forum')->scan();
    }

    /** @test */
    public function it_can_process_process_with_no_processor()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('putItem')
                   ->with([
                       'TableName' => 'Thread',
                       'Item' => [
                           'ForumName' => [
                               'S' => 'Laravel'
                           ],
                           'Subject' => [
                               'S' => 'Laravel Thread 1'
                           ]
                       ]
                    ])->andReturn(new Result(['Items' => []]))->once();

        $query = new Builder($connection, new Grammar, new Processor);

        $query->from('Thread')->putItem([
            'ForumName' => 'Laravel',
            'Subject' => 'Laravel Thread 1'
        ]);
    }

    /** @test */
    public function it_can_forward_call_to_unknown_method()
    {
        $query = $this->newQuery('Thread');

        $this->expectException(BadMethodCallException::class);

        $query->filterNotIn(['foo', 'bar']);
    }

    /** @test */
    public function it_returns_prefixed_builder()
    {
        $connection = new Connection(['prefix' => 'my_table_prefix_']);

        $result = $connection
            ->table('some_table')
            ->dryRun()
            ->getItem(['id' => 'hello']);

        $this->assertEquals(
            'my_table_prefix_some_table',
            $result['params']['TableName']
        );
    }
}
