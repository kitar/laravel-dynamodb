<?php

namespace Attla\Dynamodb\Tests\Query;

use Aws\Result;
use Attla\Dynamodb\Model\Model;
use Attla\Dynamodb\Query\Processor;
use PHPUnit\Framework\TestCase;

class User extends Model
{
}

class ProcessorTest extends TestCase
{
    protected $processor;

    protected $mocks = [
        'single_item_result' => '{"Item":{"Threads":{"N":"2"},"Category":{"S":"Amazon Web Services"},"Messages":{"N":"4"},"Views":{"N":"1000"},"Name":{"S":"Amazon DynamoDB"}},"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'single_item_processed' => '{"Item":{"Threads":2,"Category":"Amazon Web Services","Messages":4,"Views":1000,"Name":"Amazon DynamoDB"},"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'single_item_empty_result' => '{"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'single_item_empty_processed' => '{"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'multiple_items_result' => '{"Items":[{"Category":{"S":"Amazon Web Services"},"Name":{"S":"Amazon S3"}},{"Threads":{"N":"2"},"Category":{"S":"Amazon Web Services"},"Messages":{"N":"4"},"Views":{"N":"1000"},"Name":{"S":"Amazon DynamoDB"}}],"Count":2,"ScannedCount":2,"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'multiple_items_processed' => '{"Items":[{"Category":"Amazon Web Services","Name":"Amazon S3"},{"Threads":2,"Category":"Amazon Web Services","Messages":4,"Views":1000,"Name":"Amazon DynamoDB"}],"Count":2,"ScannedCount":2,"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'multiple_items_empty_result' => '{"Items":[],"Count":0,"ScannedCount":2,"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'multiple_items_empty_processed' => '{"Items":[],"Count":0,"ScannedCount":2,"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'batch_get_items_result' => '{"Responses":{"Thread":[{"Replies":{"N":"0"},"Answered":{"N":"0"},"Views":{"N":"0"},"ForumName":{"S":"Amazon DynamoDB"},"Subject":{"S":"DynamoDB Thread 1"}},{"Replies":{"N":"0"},"Answered":{"N":"0"},"Views":{"N":"0"},"ForumName":{"S":"Amazon DynamoDB"},"Subject":{"S":"DynamoDB Thread 2"}}]},"UnprocessedKeys":[],"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'batch_get_items_processed' => '{"Responses":{"Thread":[{"Replies":0,"Answered":0,"Views":0,"ForumName":"Amazon DynamoDB","Subject":"DynamoDB Thread 1"},{"Replies":0,"Answered":0,"Views":0,"ForumName":"Amazon DynamoDB","Subject":"DynamoDB Thread 2"}]},"UnprocessedKeys":[],"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'batch_get_items_empty_result' => '{"Responses":{"Thread":[]},"UnprocessedKeys":[],"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
        'batch_get_items_empty_processed' => '{"Responses":{"Thread":[]},"UnprocessedKeys":[],"@metadata":{"statusCode":200,"effectiveUri":"https:\/\/dynamodb.ap-northeast-1.amazonaws.com","transferStats":{"http":[[]]}}}',
    ];

    protected function setUp() :void
    {
        $this->processor = new Processor;
    }

    /** @test */
    public function it_can_process_single_item_result()
    {
        $expected = json_decode($this->mocks['single_item_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['single_item_result'], true));

        $item = $this->processor->processSingleItem($awsResult, null);

        $this->assertEquals($expected, $item);
    }

    /** @test */
    public function it_can_process_single_item_empty_result()
    {
        $expected = json_decode($this->mocks['single_item_empty_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['single_item_empty_result'], true));

        $item = $this->processor->processSingleItem($awsResult, null);

        $this->assertEquals($expected, $item);
    }

    /** @test */
    public function it_can_process_multiple_items_result()
    {
        $expected = json_decode($this->mocks['multiple_items_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['multiple_items_result'], true));

        $items = $this->processor->processMultipleItems($awsResult, null);

        $this->assertEquals($expected, $items);
    }

    /** @test */
    public function it_can_process_multiple_items_empty_result()
    {
        $expected = json_decode($this->mocks['multiple_items_empty_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['multiple_items_empty_result'], true));

        $items = $this->processor->processMultipleItems($awsResult, null);

        $this->assertEquals($expected, $items);
    }

    /** @test */
    public function it_can_process_batch_get_items_result()
    {
        $expected = json_decode($this->mocks['batch_get_items_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['batch_get_items_result'], true));

        $items = $this->processor->processBatchGetItems($awsResult, null);

        $this->assertEquals($expected, $items);
    }

    /** @test */
    public function it_can_process_batch_get_items_empty_result()
    {
        $expected = json_decode($this->mocks['batch_get_items_empty_processed'], true);

        $awsResult = new Result(json_decode($this->mocks['batch_get_items_empty_result'], true));

        $items = $this->processor->processBatchGetItems($awsResult, null);

        $this->assertEquals($expected, $items);
    }

    /** @test */
    public function it_can_convert_single_result_to_model_instance()
    {
        $awsResult = new Result(json_decode($this->mocks['single_item_result'], true));

        $item = $this->processor->processSingleItem($awsResult, User::class);

        $this->assertEquals(User::class, get_class($item));
        $this->assertEquals([
            'Threads' => 2,
            'Category' => 'Amazon Web Services',
            'Messages' => 4,
            'Views' => 1000,
            'Name' => 'Amazon DynamoDB'
        ], $item->toArray());
        $this->assertEquals(200, $item->meta()['@metadata']['statusCode']);
    }

    /** @test */
    public function it_can_convert_multiple_results_to_model_instance()
    {
        $awsResult = new Result(json_decode($this->mocks['multiple_items_result'], true));

        $items = $this->processor->processMultipleItems($awsResult, User::class);

        $item = $items->first();

        $this->assertEquals(User::class, get_class($item));
        $this->assertEquals([
            'Category' => 'Amazon Web Services',
            'Name' => 'Amazon S3'
        ], $item->toArray());
        $this->assertEquals(200, $item->meta()['@metadata']['statusCode']);
    }

    /** @test */
    public function it_can_convert_batch_get_results_to_model_instance()
    {
        $awsResult = new Result(json_decode($this->mocks['batch_get_items_result'], true));

        $items = $this->processor->processBatchGetItems($awsResult, User::class);

        $item = $items->first();

        $this->assertEquals(User::class, get_class($item));
        $this->assertEquals([
            'Replies' => 0,
            'Answered' => 0,
            'Views' => 0,
            'ForumName' => 'Amazon DynamoDB',
            'Subject' => 'DynamoDB Thread 1',
        ], $item->toArray());
        $this->assertEquals(200, $item->meta()['@metadata']['statusCode']);
    }
}
