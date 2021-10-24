<?php

namespace Kitar\Dynamodb\Query;

use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Str;
use Kitar\Dynamodb\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammer;

class Grammar extends BaseGrammer
{
    /**
     * The marshaler.
     * @var Aws\DynamoDb\Marshaler
     */
    protected $marshaler;

    /**
     * The operators for FilterExpression
     * @var array
     */
    protected $operators = [
        '=',
        '<>',
        '<',
        '<=',
        '>',
        '>='
    ];

    /**
     * The functions for FilterExpression
     * @var array
     */
    protected $functions = [
        'attribute_exists',
        'attribute_not_exists',
        'attribute_type',
        'begins_with',
        'contains',
        'size'
    ];

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    /**
     * Compile the TableName attribute.
     *
     * @param string $table_name
     * @return array
     */
    public function compileTableName($table_name)
    {
        return [
            'TableName' => $this->tablePrefix . $table_name
        ];
    }

    /**
     * Compile the IndexName attribute.
     *
     * @param string $index
     * @return array
     */
    public function compileIndexName($index)
    {
        if (empty($index)) {
            return [];
        }
        return [
            'IndexName' => $index
        ];
    }

    /**
     * Compile the Key attribute.
     *
     * @param array $key
     * @return array
     */
    public function compileKey($key)
    {
        if (empty($key)) {
            return [];
        }
        return [
            'Key' => $this->marshaler->marshalItem($key)
        ];
    }

    /**
     * Compile the Item attribute.
     *
     * @param array $key
     * @return array
     */
    public function compileItem($item)
    {
        if (empty($item)) {
            return [];
        }
        return [
            'Item' => $this->marshaler->marshalItem($item)
        ];
    }

    /**
     * Compile the Updates attribute.
     *
     * @param array $updates
     * @return array
     */
    public function compileUpdates($updates)
    {
        $expressions = [];

        if (! empty($updates['set'])) {
            $expressions[] = 'set ' . implode(', ', $updates['set']);
        }

        if (! empty($updates['remove'])) {
            $expressions[] = 'remove ' . implode(', ', $updates['remove']);
        }

        if (empty($expressions)) {
            return [];
        }

        return [
            'UpdateExpression' => implode(' ', $expressions),
            'ReturnValues' => 'UPDATED_NEW',
        ];
    }

    /**
     * Compile the Limit attribute.
     *
     * @param int|null $limit
     * @return array
     */
    public function compileDynamodbLimit($limit)
    {
        if ($limit === null) {
            return [];
        }

        return [
            'Limit' => $limit
        ];
    }

    public function compileExclusiveStartKey($key)
    {
        if (empty($key)) {
            return [];
        }

        return [
            'ExclusiveStartKey' => $key
        ];
    }

    /**
     * Compile the ConsistentRead attribute.
     *
     * @param bool $bool
     * @return array
     */
    public function compileConsistentRead($bool)
    {
        if ($bool == null) {
            return [];
        }
        return [
            'ConsistentRead' => $bool
        ];
    }

    /**
     * Compile the ProjectionExpression attribute.
     *
     * @return void
     */
    public function compileProjectionExpression($columns, $expression_attributes)
    {
        if (empty($columns)) {
            return [];
        }

        $projections = [];

        foreach ($columns as $column) {
            $projections[] = $expression_attributes->addName($column);
        }

        return [
            'ProjectionExpression' => implode(', ', $projections)
        ];
    }

    /**
     * Compile a ExpressionAttriute* attributes.
     *
     * @param ExpressionAttributes $expression_attributes
     * @return array
     */
    public function compileExpressionAttributes(ExpressionAttributes $expression_attributes)
    {
        $params = [];

        if ($expression_attributes->hasName()) {
            $params['ExpressionAttributeNames'] = $expression_attributes->names();
        }
        if ($expression_attributes->hasValue()) {
            $params['ExpressionAttributeValues'] = $expression_attributes->values();
        }

        return $params;
    }

    /**
     * Compile the FilterExpression/ConditionExpression/KeyConditionExpression attribute.
     *
     * @param Builder $query
     * @return array
     */
    public function compileConditions(Builder $query)
    {
        if (empty($query->wheres)) {
            return [];
        }

        $key = $query->getWhereAs();

        return [
            $key => preg_replace('/^where\s/', '', $this->compileWheres($query))
        ];
    }

    /**
     * @inheritdoc
     */
    protected function whereBasic($query, $where)
    {
        // if operator specified, compile to simple condition string.
        if (in_array($where['operator'], $this->operators)) {
            return "{$where['column']} {$where['operator']} {$where['value']}";
        }

        // if function name specified, run individual compile functions.
        if (in_array($where['operator'], $this->functions)) {
            $function = 'compile' . Str::studly($where['operator']) . 'Condition';
            return $this->$function($where);
        }
    }

    /**
     * Compile a attribute_exists condition.
     *
     * @param array $where
     * @return string
     */
    protected function compileAttributeExistsCondition($where)
    {
        return "attribute_exists({$where['column']})";
    }

    /**
     * Compile a attribute_not_exists condition.
     *
     * @param array $where
     * @return string
     */
    protected function compileAttributeNotExistsCondition($where)
    {
        return "attribute_not_exists({$where['column']})";
    }

    /**
     * Compile a attribute_type condition.
     *
     * @param array $where
     * @return string
     */
    protected function compileAttributeTypeCondition($where)
    {
        return "attribute_type({$where['column']}, {$where['value']})";
    }

    /**
     * Compile a begins_with condition.
     *
     * @param array $where
     * @return string
     */
    protected function compileBeginsWithCondition($where)
    {
        return "begins_with({$where['column']}, {$where['value']})";
    }

    /**
     * Compile a contains condition.
     *
     * @param array $where
     * @return string
     */
    protected function compileContainsCondition($where)
    {
        return "contains({$where['column']}, {$where['value']})";
    }

    /**
     * @inheritdoc
     */
    protected function whereBetween($query, $where)
    {
        $min = reset($where['values']);

        $max = end($where['values']);

        return "({$where['column']} between {$min} and {$max})";
    }

    /**
     * @inheritdoc
     */
    protected function whereIn($query, $where)
    {
        $values = implode(', ', $where['values']);

        return "({$where['column']} in ({$values}))";
    }

    /**
     * @inheritdoc
     */
    public function getOperators()
    {
        return array_merge($this->operators, $this->functions);
    }
}
