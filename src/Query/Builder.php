<?php

namespace Attla\Dynamodb\Query;

use Attla\Dynamodb\Connection;
use Illuminate\Support\{ Arr, Str };
use Illuminate\Database\Query\{
    Expression,
    Builder as BaseBuilder
};
use Attla\Dynamodb\Helpers\Collection;

class Builder extends BaseBuilder
{
    /**
     * Name of the index
     *
     * @var string|null
     */
    public $index;

    /**
     * The key
     *
     * @var array
     */
    public $key = [];

    /**
     * The item
     *
     * @var array
     */
    public $item = [];

    /**
     * The key/values to update
     *
     * @var array
     */
    public $updates = [
        'set' => [],
        'remove' => []
    ];

    /**
     * Keys array for BatchGetItem
     * https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_BatchGetItem.html
     *
     * @var array
     */
    public $batch_get_keys = [];

    /**
     * RequestItems array for BatchWriteItem
     * https://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_BatchWriteItem.html
     *
     * @var array
     */
    public $batch_write_request_items = [];

    /**
     * ScanIndexForward option
     *
     * @var bool
     */
    public $scan_index_forward;

    /**
     * LastEvaluatedKey option
     *
     * @var array|null
     */
    public $exclusive_start_key;

    /**
     * ConsistentRead option
     *
     * @var boolean|null
     */
    public $consistent_read;

    /**
     * dry run option
     *
     * @var boolean
     */
    public $dry_run = false;

    /**
     * The model class name used to transform the DynamoDB responses
     *
     * @var string|null
     */
    public $model_class;

    /**
     * The ExpressionAttributes object
     *
     * @var \Attla\Dynamodb\Query\ExpressionAttributes
     */
    protected $expression_attributes;

    /**
     * Available where methods which will pass to dedicated queries
     *
     * @var array
     */
    protected $available_wheres;

    /**
     * The attribute name to place compiled wheres
     *
     * @var string
     */
    protected $where_as;

    /**
     * Dedicated query for building FilterExpression
     *
     * @var \Attla\Dynamodb\Query\Builder
     */
    protected $filter_query;

    /**
     * Dedicated query for building ConditionExpression
     *
     * @var \Attla\Dynamodb\Query\Builder
     */
    protected $condition_query;

    /**
     * Dedicated query for building KeyConditionExpression
     *
     * @var \Attla\Dynamodb\Query\Builder
     */
    protected $key_condition_query;

    /**
     * Model keys
     *
     * @var string[]
     */
    protected $modelKeys = [];

    /**
     * Create a new query builder instance
     *
     * @param \Attla\Dynamodb\Connection $connection
     * @param \Attla\Dynamodb\Query\Grammar $grammar
     * @param \Attla\Dynamodb\Query\Processor $processor
     * @param \Attla\Dynamodb\Query\ExpressionAttributes|null $expression_attributes
     * @param bool $is_nested_query
     * @return void
     */
    public function __construct(
        Connection $connection,
        Grammar $grammar,
        Processor $processor,
        $expression_attributes = null,
        $is_nested_query = false
    ) {
        $this->connection = $connection;
        $this->grammar = $grammar;
        $this->processor = $processor;
        $this->expression_attributes = $expression_attributes ?? new ExpressionAttributes();

        if (!$is_nested_query) {
            $this->initializeDedicatedQueries();
        }
    }

    /**
     * Set the index name
     *
     * @param string $index
     * @return $this
     */
    public function index(string $index)
    {
        $this->index = $index;
        return $this;
    }

    /**
     * Set the key
     *
     * @param array $key
     * @return $this
     */
    public function key(array $key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Set the ScanIndexForward option
     *
     * @param bool $bool
     * @return $this
     */
    public function scanIndexForward($bool)
    {
        $this->scan_index_forward = $bool;
        return $this;
    }

    /**
     * Set the ExclusiveStartKey option
     *
     * @param array $key
     * @return $this
     */
    public function exclusiveStartKey($key)
    {
        $this->exclusive_start_key = $key;
        return $this;
    }

    /**
     * Set the ConsistentRead option
     *
     * @param bool $active
     * @return $this
     */
    public function consistentRead($active = true)
    {
        $this->consistent_read = $active;
        return $this;
    }

    /**
     * Set the dry run option
     *
     * @param bool $active
     * @return $this
     */
    public function dryRun($active = true)
    {
        $this->dry_run = $active;
        return $this;
    }

    /**
     * If set, response items will be converted to the model instance by using:
     * (new $model_class)->newFromBuilder($item)
     *
     * @var string
     * @return $this
     */
    public function usingModel($class_name)
    {
        $this->model_class = $class_name;
        return $this;
    }

    /**
     * Set key name of wheres. eg. FilterExpression
     *
     * @param string $condition_key_name
     * @return $this
     */
    protected function whereAs($condition_key_name)
    {
        $this->where_as = $condition_key_name;
        return $this;
    }

    /**
     * Get the where_as attribute
     *
     * @return string
     */
    public function getWhereAs()
    {
        return $this->where_as;
    }

    /**
     * Get item
     *
     * @param array|null $key
     * @return array|null
     */
    public function getItem($key = null)
    {
        $key && $this->key($key);
        return $this->process('getItem', 'processSingleItem');
    }

    /**
     * Put item
     *
     * @param array $item
     * @return \Aws\Result
     */
    public function putItem($item)
    {
        $this->item = $item;
        return $this->process('putItem', null);
    }

    /**
     * Delete item
     *
     * @param array $key
     * @return \Aws\Result
     */
    public function deleteItem($key)
    {
        $this->key($key);

        return $this->process('deleteItem', null);
    }

    /**
     * Update item
     *
     * @param array $item
     * @return array|null
     */
    public function updateItem($item)
    {
        foreach ($item as $name => $value) {
            $name = $this->expression_attributes->addName($name);

            // If value is null, it will pass to REMOVE actions
            if ($value === null) {
                $this->updates['remove'][] = $name;

            // If value set, it will pass to SET actions
            } else {
                $value = $this->expression_attributes->addValue($value);
                $this->updates['set'][] = "{$name} = {$value}";
            }
        }

        return $this->process('updateItem', 'processSingleItem');
    }

    // TODO: document..
    public function batchGetItem($keys)
    {
        $this->batch_get_keys = $keys;

        return $this->process('batchGetItem', 'processBatchGetItems');
    }

    // TODO: document..
    public function batchPutItem($items)
    {
        $this->batch_write_request_items = collect($items)->map(function ($item) {
            return [
                'PutRequest' => [
                    'Item' => $item,
                ],
            ];
        })->toArray();

        return $this->batchWriteItem();
    }

    // TODO: document..
    public function batchDeleteItem($keys)
    {
        $this->batch_write_request_items = collect($keys)->map(function ($key) {
            return [
                'DeleteRequest' => [
                    'Key' => $key,
                ],
            ];
        })->toArray();

        return $this->batchWriteItem();
    }

    // TODO: document..
    public function batchWriteItem($request_items = [])
    {
        if (!empty($request_items)) {
            $this->batch_write_request_items = $request_items;
        }

        return $this->process('batchWriteItem', null);
    }

    /** @inheritdoc */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, '+', $amount, $extra);
    }

    /** @inheritdoc */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, '-', $amount, $extra);
    }

    /**
     * Increment or decrement column's value by a given amount
     *
     * @param $column
     * @param $symbol
     * @param int $amount
     * @param array $extra
     * @return array|\Aws\Result|Aws\Result|\Illuminate\Support\Collection
     */
    protected function incrementOrDecrement($column, $symbol, $amount = 1, array $extra = [])
    {
        $name = $this->expression_attributes->addName($column);
        $value = $this->expression_attributes->addValue($amount);
        $this->updates['set'][] = "{$name} = {$name} {$symbol} {$value}";

        return $this->updateItem($extra);
    }

    /**
     * Query
     *
     * @return \Illuminate\Support\Collection|array
     */
    public function query($columns = [])
    {
        !empty($columns) && $this->select($columns);
        return $this->process('clientQuery', 'processMultipleItems');
    }

    /**
     * Scan
     *
     * @param  array $columns
     * @return \Illuminate\Support\Collection|array
     */
    public function scan($columns = [])
    {
        !empty($columns) && $this->select($columns);
        return $this->process('scan', 'processMultipleItems');
    }

    /** @inheritdoc */
    public function get($columns = [])
    {
        $hasKey = Arr::first($this->modelKeys, fn($key) => $this->expression_attributes->hasName($key));
        return $hasKey ? $this->query($columns) : $this->scan($columns);
    }

    /**
     * Set model keys
     *
     * @param string|string[] $keys
     * @return $this
     */
    public function withKeys($keys)
    {
        $this->modelKeys = Arr::wrap($keys);
        return $this;
    }

    /**
     * Make individual Builder instance for condition types
     *
     * @return void
     */
    protected function initializeDedicatedQueries()
    {
        // Set builder instances
        $this->filter_query = $this->newQuery()->whereAs('FilterExpression');
        $this->condition_query = $this->newQuery()->whereAs('ConditionExpression');
        $this->key_condition_query = $this->newQuery()->whereAs('KeyConditionExpression');

        // Make method map
        foreach (['filter', 'condition', 'key_condition'] as $query_type) {
            foreach (['', 'or'] as $boolean) {
                foreach (['', 'in', 'between'] as $where_type) {
                    $target_query = $query_type . '_query';
                    $source_method = Str::camel(implode('_', [$boolean, $query_type, $where_type]));
                    $target_method = Str::camel(implode('_', [$boolean, 'where', $where_type]));

                    $this->available_wheres[$source_method] = [$target_query, $target_method];
                }
            }
        }
    }

    /**
     * Perform where methods within dedicated queries
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function __call($method, $parameters)
    {
        if (isset($this->available_wheres[$method])) {
            $target_query = $this->available_wheres[$method][0];
            $target_method = $this->available_wheres[$method][1];

            $this->$target_query = call_user_func_array([$this->$target_query, $target_method], $parameters);

            return $this;
        }

        throw new \BadMethodCallException('Call to undefined method ' . static::class . "::{$method}()");
    }

    /** @inheritdoc */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis
        // We'll add that Closure to the query then return back out immediately
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // Convert column and value to ExpressionAttributes
        [$column, $value] = $this->expression_attributes->addColumn($column, $value);
        if ($column === false || $value === false) {
            return $this;
        }

        $type = 'Basic';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed
        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        if (!$value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /** @inheritdoc */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /** @inheritdoc */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $column = $this->expression_attributes->addName($column);
        foreach ($values as &$value) {
            $value = $this->expression_attributes->addValue($value);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /** @inheritdoc */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        $column = $this->expression_attributes->addName($column);
        foreach ($values as &$value) {
            $value = $this->expression_attributes->addValue($value);
        }

        return parent::whereBetween($column, $values, $boolean, $not);
    }

    /** @inheritdoc */
    public function newQuery()
    {
        return new static(
            $this->connection,
            $this->grammar,
            $this->processor,
            $this->expression_attributes,
            true
        );
    }

    /**
     * Execute DynamoDB call and returns processed result
     *
     * @param string $query_method
     * @param array $params
     * @param string $processor_method
     * @return array|\Illuminate\Support\Collection|\Aws\Result
     */
    protected function process($query_method, $processor_method)
    {
        // Compile columns and wheres attributes
        // These attributes needs to interact with ExpressionAttributes during compile,
        // so it need to run before compileExpressionAttributes
        $params = array_merge(
            $this->grammar->compileProjectionExpression($this->columns, $this->expression_attributes),
            $this->grammar->compileConditions($this->filter_query),
            $this->grammar->compileConditions($this->condition_query),
            $this->grammar->compileConditions($this->key_condition_query)
        );

        // Compile rest of attributes
        $params = array_merge(
            $params,
            $this->grammar->compileTableName($this->from),
            $this->grammar->compileIndexName($this->index),
            $this->grammar->compileKey($this->key),
            $this->grammar->compileItem($this->item),
            $this->grammar->compileUpdates($this->updates),
            $this->grammar->compileBatchGetRequestItems($this->from, $this->batch_get_keys),
            $this->grammar->compileBatchWriteRequestItems($this->from, $this->batch_write_request_items),
            $this->grammar->compileDynamodbLimit($this->limit),
            $this->grammar->compileScanIndexForward($this->scan_index_forward),
            $this->grammar->compileExclusiveStartKey($this->exclusive_start_key),
            $this->grammar->compileConsistentRead($this->consistent_read),
            $this->grammar->compileExpressionAttributes($this->expression_attributes)
        );

        // Dry run
        if ($this->dry_run) {
            return [
                'method' => $query_method,
                'params' => $params,
                'processor' => $processor_method
            ];
        }

        // Execute
        $response = $this->connection->$query_method($params);

        // Process
        return $processor_method
            ? $this->processor->$processor_method($response, $this->model_class)
            : $response;
    }

    /** @inheritdoc */
    public function delete($ids = null)
    {
        $ids = is_null($ids) ? $this->key : (is_array($ids) ? $ids : func_get_args());
        $result = $this->deleteItem($ids);

        return count(Arr::get($result, '@metadata.transferStats'));
    }

    /** @inheritdoc */
    public function update($values = null)
    {
        $result = $this->updateItem(is_array($values) ? $values : func_get_args());
        return count(Arr::get($result, '@metadata.transferStats'));
    }

    /** @inheritdoc */
    public function count($columns = [])
    {
        $result = $this->query($columns);
        return $result instanceof Collection ? $result->count() : 0;
    }

    /** @inheritdoc */
    public function countScan($columns = [])
    {
        $result = $this->scan($columns);
        return $result instanceof Collection ? $result->count() : 0;
    }
}
