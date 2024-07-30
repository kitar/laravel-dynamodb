<?php

namespace Kitar\Dynamodb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Kitar\Dynamodb\Concerns\HasMeta;
use Kitar\Dynamodb\Query\Builder as QueryBuilder;
use Kitar\Dynamodb\Exceptions\KeyMissingException;

class Model extends BaseModel
{
    use HasMeta;

    /**
     * The Partition Key.
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * The Sort Key.
     *
     * @var string|null
     */
    protected $sortKey;

    /**
     * The default value of the Sort Key.
     *
     * @var string|null
     */
    protected $sortKeyDefault;

    /** LARAVEL Attributies */
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID
     *
     * @var string
     */
    protected $keyType = 'string';

    /** @inheritdoc */
    public function __construct(array $attributes = [])
    {
        if (
            $this->sortKey
            && $this->sortKeyDefault
            && ! isset($attributes[$this->sortKey])
        ) {
            $this[$this->sortKey] = $this->sortKeyDefault;
        }

        parent::__construct($attributes);
    }

    /**
     * Get the key of the current item.
     *
     * @return array
     */
    public function getKey()
    {
        if (empty($this->primaryKey)) {
            throw new KeyMissingException("Primary (Partition) key is not defined.");
        }

        $keys = [];
        $keys[$this->primaryKey] = $this->getAttribute($this->primaryKey);

        if ($this->sortKey) {
            $keys[$this->sortKey] = $this->getAttribute($this->sortKey);
        }

        $missingKeys = array_keys(array_filter($keys, function ($value) {
            return !isset($value) || $value === '';
        }));

        if (!empty($missingKeys)) {
            $keyNames = implode(', ', $missingKeys);
            throw new KeyMissingException("Some required key(s) has no value: {$keyNames}");
        }

        return $keys;
    }

    /** @inheritdoc */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        $connection->table($this->getTable())->usingModel(static::class);

        return new QueryBuilder(
            $connection,
            $connection->getQueryGrammar(),
            $connection->getPostProcessor()
        );

    }

    /** @inheritdoc */
    protected function setKeysForSaveQuery($query)
    {
        return $this->newQuery()->key($this->getKey());
    }

    /**
     * Find a model by its primary (partition) key or key array.
     *
     * @param string|array $key
     * @return static|null
     */
    // TODO: remover/migrar para o builder
    public static function find($key)
    {
        if (empty($key)) {
            throw new KeyMissingException("Primary (Partition) key has no value.");
        }

        if (is_string($key) || is_numeric($key)) {
            $model = new static();
            $model->setAttribute($model->getKeyName(), $key);
            $key = $model->getKey();
        }

        return static::getItem($key);
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function all($columns = [])
    {
        return static::scan(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array  $fillables
     * @param  array  $options
     * @return \Kitar\Dynamodb\Model\Model|$this
     */
    // TODO: migrar para o builder
    public static function create(array $fillables = [], array $options = [])
    {
        $instance = new static($fillables);
        $instance->save($options);
        return $instance;
    }

    /**
     * @inheritdoc
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        $query = $this->newQuery()->key($this->getKey());

        if (! $this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        return tap($query->{$method}($column, $amount, $extra), function ($response) {
            $this->forceFill($response['Attributes']);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        });
    }

    /** @inheritdoc */
    public function __call($method, $parameters)
    {
        $allowedBuilderMethods = [
            "select",
            "take",
            "limit",
            "index",
            "key",
            "exclusiveStartKey",
            "consistentRead",
            "dryRun",
            "getItem",
            "putItem",
            "deleteItem",
            "updateItem",
            "batchGetItem",
            "batchPutItem",
            "batchDeleteItem",
            "batchWriteItem",
            "scan",
            "filter",
            "filterIn",
            "filterBetween",
            "condition",
            "conditionIn",
            "conditionBetween",
            "keyCondition",
            "keyConditionIn",
            "keyConditionBetween",
        ];

        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        if (! in_array($method, $allowedBuilderMethods)) {
            static::throwBadMethodCallException($method);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }
}
