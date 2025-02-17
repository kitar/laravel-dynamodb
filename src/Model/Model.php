<?php

namespace Attla\Dynamodb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Support\Arr;
use Attla\Dynamodb\Concerns\{ HasAttributeFactory, HasCompactAttributes, HasMeta };
use Attla\Dynamodb\Exceptions\KeyMissingException;

class Model extends BaseModel
{
    use HasMeta;
    use HasAttributeFactory;
    use HasCompactAttributes;

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
     * The Eloquent query builder class to use for the model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Builder<*>>
     */
    protected static string $builder = Builder::class;

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
            && !isset($attributes[$this->sortKey])
        ) {
            $this[$this->sortKey] = $this->sortKeyDefault;
        }

        parent::__construct($attributes);
    }

    /**
     * Get the default short key.
     *
     * @return string
     */
    public function getDefaultSortKey()
    {
        return $this->sortKeyDefault ?? null;
    }

    /**
     * Get the key names.
     *
     * @return array
     */
    public function getKeySchema()
    {
        return array_filter([$this->primaryKey, $this->sortKey]);
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
    public function qualifyColumn($column)
    {
        return $column;
    }

    /** @inheritdoc */
    protected function newBaseQueryBuilder()
    {
        return $this->getConnection()->table($this->getTable())->usingModel(static::class);
    }

    /** @inheritdoc */
    public function newQueryWithoutScopes()
    {
        return $this->newModelQuery();
    }

    /** @inheritdoc */
    protected function setKeysForSaveQuery($query)
    {
        return $this->newBaseQueryBuilder()->key($this->getKey());
    }

    /** @inheritdoc */
    protected function getAttributesForInsert()
    {
        return array_merge(
            Arr::except( $this->getAttributes(),$this->getGuarded()),
            $this->getKey()
        );
    }


    /** @inheritdoc */
    public static function all($columns = [])
    {
        return static::scan(
            is_array($columns) ? $columns : func_get_args()
        );
    }

    /** @inheritdoc */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        $query = $this->newQuery()->key($this->getKey());

        if (!$this->exists) {
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
}
