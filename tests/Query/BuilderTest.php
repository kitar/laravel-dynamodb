<?php

namespace Kitar\Dynamodb\Tests\Query;

use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Model\Model;
use PHPUnit\Framework\TestCase;

class Product extends Model
{
}

class BuilderTest extends TestCase
{
    protected $connection;

    protected function setUp() :void
    {
        $this->connection = new Connection([]);
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
        $this->assertNull($query['processor']);
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
}
