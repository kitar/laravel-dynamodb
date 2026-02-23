<?php

namespace Kitar\Dynamodb\Tests\Integration;

use Kitar\Dynamodb\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

class QueryOperationsTest extends IntegrationTestCase
{
    protected function seedThreads(): void
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('Thread')->putItem([
            'ForumName' => 'Amazon DynamoDB',
            'Subject' => 'DynamoDB Thread 1',
            'Views' => 10,
            'Replies' => 5,
        ]);

        $connection->table('Thread')->putItem([
            'ForumName' => 'Amazon DynamoDB',
            'Subject' => 'DynamoDB Thread 2',
            'Views' => 3,
            'Replies' => 1,
        ]);

        $connection->table('Thread')->putItem([
            'ForumName' => 'Amazon S3',
            'Subject' => 'S3 Thread 1',
            'Views' => 20,
            'Replies' => 8,
        ]);
    }

    #[Test]
    public function it_can_query_by_key_condition()
    {
        $this->seedThreads();

        $results = $this->app['db']->connection('dynamodb')
            ->table('Thread')
            ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
            ->query();

        $this->assertCount(2, $results['Items']);
    }

    #[Test]
    public function it_can_scan_with_filter()
    {
        $this->seedThreads();

        $results = $this->app['db']->connection('dynamodb')
            ->table('Thread')
            ->filter('Views', '>', 5)
            ->scan();

        // Thread 1 (Views=10) and S3 Thread 1 (Views=20) match
        $this->assertCount(2, $results['Items']);
    }

    #[Test]
    public function it_can_query_with_limit()
    {
        $this->seedThreads();

        $results = $this->app['db']->connection('dynamodb')
            ->table('Thread')
            ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
            ->limit(1)
            ->query();

        $this->assertCount(1, $results['Items']);
    }

    #[Test]
    public function it_can_query_with_key_condition_begins_with()
    {
        $this->seedThreads();

        $results = $this->app['db']->connection('dynamodb')
            ->table('Thread')
            ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
            ->keyCondition('Subject', 'begins_with', 'DynamoDB')
            ->query();

        $this->assertCount(2, $results['Items']);
    }

    #[Test]
    public function it_can_scan_with_projection()
    {
        $this->seedThreads();

        $results = $this->app['db']->connection('dynamodb')
            ->table('Thread')
            ->scan(['ForumName', 'Subject']);

        $firstItem = $results['Items'][0];
        $this->assertArrayNotHasKey('Views', $firstItem);
        $this->assertArrayNotHasKey('Replies', $firstItem);
    }

    #[Test]
    public function it_can_batch_put_and_batch_get_items()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('Thread')->batchPutItem([
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 1'],
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 2'],
        ]);

        $results = $connection->table('Thread')->batchGetItem([
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 1'],
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 2'],
        ]);

        $this->assertCount(2, $results['Responses']['Thread']);
    }

    #[Test]
    public function it_can_batch_delete_items()
    {
        $connection = $this->app['db']->connection('dynamodb');

        $connection->table('Thread')->batchPutItem([
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 1'],
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 2'],
        ]);

        $connection->table('Thread')->batchDeleteItem([
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 1'],
            ['ForumName' => 'Forum A', 'Subject' => 'Thread 2'],
        ]);

        $results = $connection->table('Thread')->scan();
        $this->assertCount(0, $results['Items']);
    }
}
