<?php

namespace Kitar\Dynamodb\Query;

use Closure;
use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Query\Grammar;
use Kitar\Dynamodb\Query\Processor;
use Kitar\Dynamodb\Query\ExpressionAttributes;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Builder as BaseBuilder;

class Builder extends BaseBuilder
{

    /**
     * Name of the index.
     * @var string|null
     */
    public $index;

    /**
     * The key.
     * @var array
     */
    public $key = [];

    /**
     * The item.
     * @var array
     */
    public $item = [];

    /**
     * ConsistentRead option.
     * @var boolean|null
     */
    public $consistent_read;

    /**
     * dry run option.
     * @var boolean
     */
    public $dry_run = false;

    /**
     * The attribute name to place compiled wheres.
     * @var string
     */
    public $where_as;

    /**
     * The ExpressionAttributes object.
     * @var Kitar\Dynamodb\Query\ExpressionAttributes
     */
    public $expression_attributes;

    /**
     * Query for building FilterExpression.
     * @var Kitar\Dynamodb\Query\Builder
     */
    public $filter_query;

    /**
     * Query for building ConditionExpression.
     * @var Kitar\Dynamodb\Query\Builder
     */
    public $condition_query;

    /**
     * Query for building KeyConditionExpression.
     * @var Kitar\Dynamodb\Query\Builder
     */
    public $key_condition_query;

    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection, Grammar $grammar, Processor $processor, $expression_attributes = null, $is_nested_query = false)
    {
        $this->connection = $connection;

        $this->grammar = $grammar;

        $this->processor = $processor;

        $this->expression_attributes = $expression_attributes ?? new ExpressionAttributes;

        if (! $is_nested_query) {
            $this->filter_query = $this->newQuery()->whereAs('FilterExpression');
            $this->condition_query = $this->newQuery()->whereAs('ConditionExpression');
            $this->key_condition_query = $this->newQuery()->whereAs('KeyConditionExpression');
        }
    }

    /**
     * Set the index name.
     * @param string $index
     * @return $this
     */
    public function index(string $index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Set the key.
     * @param array $key
     * @return $this
     */
    public function key(array $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the ConsistentRead option.
     * @param bool $active
     * @return $this
     */
    public function consistentRead($active = true)
    {
        $this->consistent_read = $active;

        return $this;
    }

    /**
     * Set the dry run option. It'll return compiled params instead of calling DynamoDB.
     * @param bool $active
     * @return $this
     */
    public function dryRun($active = true)
    {
        $this->dry_run = $active;

        return $this;
    }

    /**
     * where for FilterExpression.
     * @return $this
     */
    public function filter($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->filter_query = $this->filter_query->where($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * where for ConditionExpression.
     * @return $this
     */
    public function condition($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->condition_query = $this->condition_query->where($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * where for KeyConditionExpression.
     * @return $this
     */
    public function keyCondition($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->key_condition_query = $this->key_condition_query->where($column, $operator, $value, $boolean);

        return $this;
    }

    /**
     * Set key name of wheres. eg. FilterExpression
     * @param string $condition_key_name
     * @return $this
     */
    public function whereAs($condition_key_name)
    {
        $this->where_as = $condition_key_name;

        return $this;
    }

    /**
     * Get item.
     * @return Illuminate\Support\Collection|null
     */
    public function getItem($key = null)
    {
        if ($key) {
            $this->key($key);
        }

        return $this->process('getItem', 'processSingleItem');
    }

    /**
     * Put item.
     * @return \Aws\Result;
     */
    public function putItem($item)
    {
        $this->item = $item;

        return $this->process('putItem', null);
    }

    /**
     * Delete item.
     * @return \Aws\Result;
     */
    public function deleteItem($key)
    {
        if ($key) {
            $this->key($key);
        }

        return $this->process('deleteItem', null);
    }

    /**
     * Query.
     * @return Illuminate\Support\Collection
     */
    public function query()
    {
        return $this->process('clientQuery', 'processMultipleItems');
    }

    /**
     * Scan.
     * @return Illuminate\Support\Collection
     */
    public function scan()
    {
        return $this->process('scan', 'processMultipleItems');
    }

    /**
     * @inheritdoc
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Convert column and value to ExpressionAttributes.
        if (! $column instanceof Closure) {
            $column = $this->expression_attributes->addName($column);
            if (! empty($value)) {
                $value = $this->expression_attributes->addValue($value);
            }
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        $type = 'Basic';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor, $this->expression_attributes, true);
    }

    /**
     * Execute DynamoDB call and returns processed result.
     * @param string $query_method
     * @param array $params
     * @param string $processor_method
     * @return array|Illuminate\Support\Collection|Aws\Result
     */
    protected function process($query_method, $processor_method)
    {
        // Compile columns and wheres attributes.
        // These attributes needs to intaract with ExpressionAttributes during compile,
        // so it need to run before compileExpressionAttributes.
        $params = array_merge(
            $this->grammar->compileProjectionExpression($this->columns, $this->expression_attributes),
            $this->grammar->compileConditions($this->filter_query),
            $this->grammar->compileConditions($this->condition_query),
            $this->grammar->compileConditions($this->key_condition_query),
        );

        // Compile rest of attributes.
        $params = array_merge(
            $params,
            $this->grammar->compileTableName($this->from),
            $this->grammar->compileIndexName($this->index),
            $this->grammar->compileKey($this->key),
            $this->grammar->compileItem($this->item),
            $this->grammar->compileConsistentRead($this->consistent_read),
            $this->grammar->compileExpressionAttributes($this->expression_attributes),
        );

        // Dry run.
        if ($this->dry_run) {
            return [
                'method' => $query_method,
                'params' => $params,
                'processor' => $processor_method
            ];
        }

        // Execute.
        $response = $this->connection->$query_method($params);

        // Process.
        if ($processor_method) {
            return $this->processor->$processor_method($response);
        } else {
            return $response;
        }
    }
}
