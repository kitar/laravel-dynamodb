<?php

namespace Kitar\Dynamodb\Model;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Kitar\Dynamodb\Model\KeyMissingException;

class Model extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The Partition Key.
     * @var string
     */
    protected $primaryKey;

    /**
     * The Sort Key.
     * @var string|null
     */
    protected $sortKey;

    /**
     * The default value of the Sort Key.
     * @var string|null
     */
    protected $sortKeyDefault;

    /**
     * The @metadata attribute of AWS\Result response.
     * @var mixed
     */
    protected $meta;

    /**
     * @inheritdoc
     */
    public function __construct(array $attributes = [])
    {
        $this->incrementing = false;

        if ($this->sortKey && $this->sortKeyDefault && ! isset($attributes[$this->sortKey])) {
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

        $key = [];

        $key[$this->primaryKey] = $this->getAttribute($this->primaryKey);

        if ($this->sortKey) {
            $key[$this->sortKey] = $this->getAttribute($this->sortKey);
        }

        $missingKeys = [];

        foreach ($key as $name => $value) {
            if (! isset($value) || $value === '') {
                $missingKeys[] = $name;
            }
        }

        if (! empty($missingKeys)) {
            $keyNames = implode(', ', $missingKeys);
            throw new KeyMissingException("Some required key(s) has no value: {$keyNames}");
        }

        return $key;
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return \Kitar\Dynamodb\Query\Builder
     */
    public function newQuery()
    {
        return $this->getConnection()->table($this->getTable())->usingModel(static::class);
    }

    /**
     * Find a model by its primary (partition) key or key array.
     *
     * @param string|array $key
     * @return static|null
     */
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
     * @param  array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function all($columns = [])
    {
        return static::scan($columns);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array  $fillables
     * @param  array  $options
     * @return \Kitar\Dynamodb\Model\Model|$this
     */
    public static function create(array $fillables = [], array $options = [])
    {
        $instance = new static($fillables);
        $instance->save($options);
        return $instance;
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $this->mergeAttributesFromCachedCasts();

        // newModelQuery() would build the SQL query builder.
        $query = $this->newQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ?
                $this->performUpdate($query) : true;
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query);

            if (! $this->getConnectionName() &&
                $connection = $query->getConnection()) {
                $this->setConnection($connection->getName());
            }
        }

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * @inheritdoc
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        // The key is required even for an unsaved instance: without it Laravel would update every row.
        $query = $this->newQuery()->key($this->exists ? $this->getKeyForSaveQuery() : $this->getKey());

        // Like Eloquent's query builder, write updated_at together with the
        // incremented column unless the caller already passed it.
        $syncColumns = [$column];

        if ($this->usesTimestamps() && ! is_null($updatedAt = $this->getUpdatedAtColumn()) && ! array_key_exists($updatedAt, $extra)) {
            $timestamp = $this->freshTimestampString();

            if ($this->hasSetMutator($updatedAt) || $this->hasAttributeSetMutator($updatedAt) || $this->hasCast($updatedAt)) {
                $timestamp = $this->newInstance()->forceFill([$updatedAt => $timestamp])->getAttributes()[$updatedAt] ?? $timestamp;
            }

            $extra = array_merge([$updatedAt => $timestamp], $extra);
            $syncColumns[] = $updatedAt;
        }

        if (! $this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // A cast that deviates the value (e.g. stores cents) needs the amount in its stored form.
        if ($this->isClassDeviable($column)) {
            $amount = (clone $this)->setAttribute($column, $amount)->getAttributeFromArray($column);
        }

        // The values are computed by DynamoDB, so the model takes them from the response
        // instead of calculating them before the update. They are already stored values,
        // so they are set as raw attributes without running mutators or casts again.
        return tap($query->{$method}($column, $amount, $extra), function ($response) use ($syncColumns) {
            // setRawAttributes() clears the whole cast cache. Keep the cached cast objects of the
            // attributes that are not in the response, so that objects obtained before stay attached.
            $attributes = array_replace($this->getAttributes(), $response['Attributes']);
            $classCastCache = array_diff_key($this->classCastCache, $response['Attributes']);
            $attributeCastCache = array_diff_key($this->attributeCastCache, $response['Attributes']);

            $this->setRawAttributes($attributes);

            $this->classCastCache = $classCastCache;
            $this->attributeCastCache = $attributeCastCache;

            $this->syncChanges();

            $this->fireModelEvent('updated', false);

            $this->syncOriginalAttributes($syncColumns);
        });
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Kitar\Dynamodb\Query\Builder  $query
     * @return bool
     */
    protected function performUpdate($query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirtyForUpdate();

        if (count($dirty) > 0) {
            // updateItem() with the key replaces setKeysForSaveQuery()->update(): DynamoDB has no where-based update.
            $query->key($this->getKeyForSaveQuery())->updateItem($dirty);

            // refreshSavedAttributes() is omitted: it reads back through the SQL query builder.
            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Get the key that addresses the stored item: the original key values, not the current
     * attribute values (which may be changed or cast). Like Laravel's getKeyForSaveQuery().
     *
     * @return array
     */
    protected function getKeyForSaveQuery()
    {
        $key = $this->getKey();

        foreach (array_keys($key) as $name) {
            $key[$name] = $this->original[$name] ?? $key[$name];
        }

        return $key;
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Kitar\Dynamodb\Query\Builder  $query
     * @return bool
     */
    protected function performInsert($query)
    {
        if ($this->usesUniqueIds()) {
            $this->setUniqueIds();
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();

        // DynamoDB has no incrementing key, so we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        if (empty($attributes)) {
            return true;
        }

        // Prevent overwrites of an existing item.
        foreach (array_keys($this->getKey()) as $keyName) {
            $query = $query->condition($keyName, 'attribute_not_exists');
        }

        $query->putItem($attributes);

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        // refreshSavedAttributes() is omitted: it reads back through the SQL query builder.
        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     */
    public function delete()
    {
        $this->mergeAttributesFromCachedCasts();

        // The LogicException for a missing primary key name is omitted: getKey() throws KeyMissingException.

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (! $this->exists) {
            return;
        }

        $key = $this->getKeyForSaveQuery();

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // touchOwners() is omitted because relationships are not supported, and
        // deleteItem() replaces performDeleteOnModel(): DynamoDB has no where-based delete.
        $this->newQuery()->deleteItem($key);

        $this->exists = false;

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    public function meta()
    {
        return $this->meta;
    }

    public function setMeta(array $meta)
    {
        $this->meta = $meta;
    }

    /**
     * @inheritdoc
     */
    public function __call($method, $parameters)
    {
        $allowedBuilderMethods = [
            "select",
            "count",
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

        // Relation resolvers and the incrementEach() family are omitted: relations are not
        // supported, and incrementEach() would run SQL.
        if (in_array($method, ['increment', 'decrement', 'incrementQuietly', 'decrementQuietly'])) {
            return $this->$method(...$parameters);
        }

        if (! in_array($method, $allowedBuilderMethods) && ! $this->hasNamedScope($method)) {
            static::throwBadMethodCallException($method);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }
}
