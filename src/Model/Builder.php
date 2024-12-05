<?php

namespace Attla\Dynamodb\Model;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Arr;
use Attla\Dynamodb\Exceptions\KeyMissingException;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Builder extends EloquentBuilder
{
    /**
     * The methods that should be returned from query builder
     *
     * @var string[]
     */
    protected $passthru = [
        'average',
        'avg',
        'count',
        'dd',
        'doesntexist',
        'dump',
        'exists',
        'getbindings',
        'getconnection',
        'getgrammar',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertusing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'tomql',
    ];

    /** @inheritdoc */
    public function setModel(EloquentModel $model)
    {
        $this->model = $model;
        $this->query
            ->from($model->getTable())
            ->withKeys($model->getKeySchema());

        return $this;
    }

    /** @inheritdoc */
    public function find($key, $columns = ['*'])
    {
        if (empty($key)) {
            throw new KeyMissingException("Primary (Partition) key has no value.");
        }

        if (is_array($key) && Arr::isList($key)) {
            $key = array_combine($this->model->getKeySchema(), $key);
        } else if (is_string($key) || is_numeric($key)) {
            $model = $this->newModelInstance();
            $model->setAttribute($model->getKeyName(), $key);
            $key = $model->getKey();
        }

        return $this->query->getItem($key);
    }

    /** @inheritdoc */
    public function update($values = null)
    {
        $result = $this->query->updateItem(is_array($values) ? $values : func_get_args());
        return count(Arr::get($result, '@metadata.transferStats'));
    }

    /** @inheritdoc */
    public function delete($ids = null)
    {
        $result = $this->query->deleteItem(is_array($ids) ? $ids : func_get_args());
        return count(Arr::get($result, '@metadata.transferStats'));
    }

    /** @inheritdoc */
    public function insert(array $values)
    {
        $result = $this->query->putItem(is_array($values) ? $values : func_get_args());
        return count(Arr::get($result, '@metadata.transferStats')) > 0;
    }

    /** @inheritdoc */
    public function query($columns = [])
    {
        return $this->query->query(is_array($columns) ? $columns : func_get_args());
    }

    /** @inheritdoc */
    public function scan($columns = [])
    {
        return $this->query->scan(is_array($columns) ? $columns : func_get_args());
    }

    /** @inheritdoc */
    public function get($columns = [])
    {
        return $this->query->get(is_array($columns) ? $columns : func_get_args());
    }

    public function first($columns = [])
    {
        $this->limit(1);
        $this->offset(10);
        return $this->scan(is_array($columns) ? $columns : func_get_args());
    }

    /** @inheritdoc */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->query->filter($column, $operator, $value, $boolean);
        return $this;
    }

    /** @inheritdoc */
    public function orWhere($column, $operator = null, $value = null)
    {
        $this->query->orFilter($column, $operator, $value);
        return $this;
    }

    /** @inheritdoc */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $this->query->filterIn($column, $values, $boolean, $not);
        return $this;
    }

    /** @inheritdoc */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        $this->query->filterBetween($column, $values, $boolean, $not);
        return $this;
    }

    /** @inheritdoc */
    public function __get($key)
    {
        // if (in_array($key, ['orWhere', 'whereNot', 'orWhereNot'])) {
        //     return new HigherOrderBuilderProxy($this, $key);
        // }

        if (in_array($key, $this->propertyPassthru)) {
            return $this->toBase()->{$key};
        }

        throw new \Exception("Property [{$key}] does not exist on the Eloquent builder instance.");
    }
}
