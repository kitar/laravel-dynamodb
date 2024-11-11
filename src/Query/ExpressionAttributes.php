<?php

namespace Attla\Dynamodb\Query;

use Aws\DynamoDb\Marshaler;
use Attla\Dynamodb\Helpers\NumberIterator;

class ExpressionAttributes
{
    protected $marshaler;
    protected $names = [];
    protected $values = [];
    protected $name_keys_iterator;
    protected $value_keys_iterator;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
        $this->name_keys_iterator = new NumberIterator(1, '#');
        $this->value_keys_iterator = new NumberIterator(1, ':');
    }

    public function addName($name)
    {
        if (!in_array($name, $this->names, true)) {
            $this->names[$this->makeNameKey()] = $name;
        }

        return array_search($name, $this->names, true);
    }

    public function addValue($value)
    {
        if (!in_array($value, $this->values, true)) {
            $this->values[$this->makeValueKey()] = $value;
        }

        return array_search($value, $this->values, true);
    }

    public function makeNameKey()
    {
        $current = $this->name_keys_iterator->current();
        $this->name_keys_iterator->next();
        return $current;
    }

    public function makeValueKey()
    {
        $current = $this->value_keys_iterator->current();
        $this->value_keys_iterator->next();
        return $current;
    }

    public function hasName()
    {
        return !empty($this->names);
    }

    public function hasValue()
    {
        return !empty($this->values);
    }

    public function names()
    {
        return $this->names;
    }

    public function values()
    {
        return $this->marshaler->marshalItem($this->values);
    }
}
