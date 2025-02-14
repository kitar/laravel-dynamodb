<?php

namespace Attla\Dynamodb\Validation;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\{
    PresenceVerifierInterface,
    DatabasePresenceVerifierInterface
};

class DatabasePresenceVerifier implements PresenceVerifierInterface, DatabasePresenceVerifierInterface
{
    /**
     * The database connection instance
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $db;

    /**
     * The database connection to use
     *
     * @var string
     */
    protected $connection;

    /**
     * Create a new database presence verifier
     *
     * @param \Illuminate\Database\ConnectionResolverInterface $db
     * @return void
     */
    public function __construct(ConnectionResolverInterface $db)
    {
        $this->db = $db;
    }

    /** @inheritdoc */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        $value = $value ?: $column;
        $column = $column ?: 'pk';
        $keySearch = in_array($column, $keys = ['pk', 'sk']);
        $method = $keySearch ? 'keyCondition' : 'filter';

        $query = $this->table($collection)
            ->$method($column, '=', $value);

        if (in_array($excludeId, $keys) && $excludeId !== 'NULL') {
            $query = $query->$method($excludeId ?: 'pk', '=', $idColumn);
        } else if (!is_null($excludeId) && $excludeId !== 'NULL') {
            $query = $query->$method($idColumn ?: 'pk', '<>', $excludeId);
        }

        $query = $this->addConditions($query, $extra, $keySearch);
        try {
            return $keySearch ? $query->count() : $query->countScan();
        } catch (\Throwable $e) {
            throw $e;
            return 0;
        }
    }

    /** @inheritdoc */
    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        // TODO: need to execute in one query...
        foreach ($values as $val) {
            if (!$count = $this->getCount($collection, $column, $val, null, null, $extra))
                return 0;
        }

        return count($values);
    }

    /**
     * Add the given conditions to the query
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $conditions
     * @param bool $keySearch
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addConditions($query, $conditions, bool $keySearch)
    {
        foreach ($conditions as $key => $val) {
            if ($val instanceof \Closure) {
                $query->where(fn ($query) => $val($query));
                // $query->where(function ($query) use ($value) {
                //     $value($query);
                // });
            } else {
                $this->addWhere($query, $key, $val, $keySearch);
            }
        }

        return $query;
    }

    /**
     * Add a "where" clause to the given query
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $key
     * @param string $extraValue
     * @return void
     */
    protected function addWhere($query, $key, $val, $keySearch)
    {
        $method = $keySearch && in_array($key, ['pk', 'sk']) ? 'keyCondition' : 'filter';

        if ($val === 'NULL') {
            $query = $query->$method . 'Null'($key); // TODO: implement..
            // $query->whereNull($key); // TODO: implement..
        } elseif ($val === 'NOT_NULL') {
            $query = $query->$method . 'NotNull'($key); // TODO: implement..
            // $query->whereNotNull($key); // TODO: implement..
        } elseif (str_starts_with($val, '!')) {
            $query = $query->$method($key, '!=', mb_substr($val, 1)); // TODO: implement..
            // $query->where($key, '!=', mb_substr($val, 1)); // TODO: implement..
        } else {
            $query = $query->$method($key, $val);
            // $query->where($key, $val);
        }
    }

    /**
     * Get a query builder for the given table
     *
     * @param string $table
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table($table)
    {
        return $this->db->connection($this->connection)->table($table);
    }

    /** @inheritdoc */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}
