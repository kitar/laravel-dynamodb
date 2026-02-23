# Architecture Reference

This document details which methods override Laravel base classes and how they differ. Consult this when updating overrides for a new Laravel version.

## Override Inventory

### Connection (`src/Kitar/Dynamodb/Connection.php`)

Extends: `Illuminate\Database\Connection`

| Method | Override Type | Notes |
|--------|-------------|-------|
| `__construct($config)` | **Full replacement** | Bypasses PDO. Accepts config array, initializes DynamoDB client, grammar, processor. |
| `table($table, $as)` | **Adapted** | Uses `$this->query()->from()` pattern (matches Laravel 12.x). |
| `query()` | **New (matching parent pattern)** | Returns `Query\Builder`. Separated from `table()` in Laravel 12.x. |
| `getDriverName()` | **Simple override** | Returns `'dynamodb'`. |
| `disconnect()` | **No-op** | DynamoDB is HTTP-based, no persistent connection. |
| `getDefaultPostProcessor()` | **Simple override** | Returns `Query\Processor`. |
| `getDefaultQueryGrammar()` | **Simple override** | Returns `Query\Grammar($this)`. Grammar requires Connection since Laravel 12.x. |

DynamoDB-only: `getClient()`, `setClient()`, `createClient()`, `clientQuery()`, `__call()` (forwards to DynamoDB client).

---

### Query\Builder (`src/Kitar/Dynamodb/Query/Builder.php`)

Extends: `Illuminate\Database\Query\Builder`

| Method | Override Type | Intentionally Omitted Laravel 12.x Features |
|--------|-------------|---------------------------------------------|
| `__construct(...)` | **Extended** — calls `parent::__construct()`, adds ExpressionAttributes + dedicated queries | — |
| `where($column, $op, $val, $bool)` | **Full replacement** — converts args to ExpressionAttribute placeholders | ConditionExpression interface, array-of-wheres, subqueries, JSON booleans, bitwise ops, null→whereNull |
| `orWhere(...)` | **Delegates to `where()`** | — |
| `whereIn($column, $values, ...)` | **Extended** — converts args, then calls `parent::whereIn()` | Subqueries, Arrayable handling |
| `whereBetween($column, $values, ...)` | **Extended** — converts args, then calls `parent::whereBetween()` | Subqueries, DatePeriod handling |
| `increment($column, $amount, $extra)` | **Full replacement** — DynamoDB `UpdateExpression SET` | Parent uses `incrementEach()` → SQL `UPDATE` |
| `decrement($column, $amount, $extra)` | **Full replacement** — same as increment | Same |
| `newQuery()` | **Full replacement** — shares `ExpressionAttributes` across nested queries | — |
| `forNestedWhere()` | **Full replacement** — uses `newQuery()->from()` | — |
| `__call($method, $params)` | **Full replacement** — routes filter/condition/keyCondition methods | Macros, dynamicWhere |

DynamoDB-only: `index()`, `key()`, `scanIndexForward()`, `exclusiveStartKey()`, `consistentRead()`, `dryRun()`, `usingModel()`, `whereAs()`, `getWhereAs()`, `getItem()`, `putItem()`, `deleteItem()`, `updateItem()`, `batchGetItem()`, `batchPutItem()`, `batchDeleteItem()`, `batchWriteItem()`, `query()` (DynamoDB Query op), `scan()`, `count()`, `incrementOrDecrement()`, `initializeDedicatedQueries()`, `process()`.

---

### Query\Grammar (`src/Kitar/Dynamodb/Query/Grammar.php`)

Extends: `Illuminate\Database\Query\Grammars\Grammar`

| Method | Override Type | Notes |
|--------|-------------|-------|
| `__construct(Connection)` | **Extended** — calls `parent::__construct()`, adds Marshaler | Parent requires Connection since Laravel 12.x. |
| `whereBasic($query, $where)` | **Full replacement** | Handles DynamoDB operators (`=`, `<>`, etc.) and functions (`begins_with`, `contains`, etc.). No SQL wrapping. |
| `whereBetween($query, $where)` | **Full replacement** | Values are already expression placeholders. No "NOT BETWEEN" support. |
| `whereIn($query, $where)` | **Full replacement** | Values are already expression placeholders. |
| `getOperators()` | **Extended** | Returns `operators` + `functions` merged (so `begins_with` etc. are valid operators). |

DynamoDB-only: All `compile*()` methods — `compileTableName()`, `compileIndexName()`, `compileKey()`, `compileItem()`, `compileUpdates()`, `compileBatchGetRequestItems()`, `compileBatchWriteRequestItems()`, `compileDynamodbLimit()`, `compileScanIndexForward()`, `compileExclusiveStartKey()`, `compileConsistentRead()`, `compileProjectionExpression()`, `compileExpressionAttributes()`, `compileConditions()`, and individual function compilers (`compileAttributeExistsCondition()`, etc.).

---

### Query\Processor (`src/Kitar/Dynamodb/Query/Processor.php`)

Extends: `Illuminate\Database\Query\Processors\Processor`

No overrides. All methods are DynamoDB-specific: `unmarshal()`, `processSingleItem()`, `processMultipleItems()`, `processBatchGetItems()`.

---

### Model (`src/Kitar/Dynamodb/Model/Model.php`)

Extends: `Illuminate\Database\Eloquent\Model`

| Method | Override Type | Intentionally Omitted Laravel 12.x Features |
|--------|-------------|---------------------------------------------|
| `__construct($attributes)` | **Extended** — sets `incrementing=false`, handles sortKey defaults, calls parent | — |
| `getKey()` | **Full replacement** — returns associative array `[partitionKey => val, sortKey => val]` | Parent returns scalar |
| `newQuery()` | **Full replacement** — returns `Query\Builder` directly | Eloquent Builder, scopes, eager loading |
| `find($key)` | **New static** — uses `getItem()` for key-based lookup | Parent delegates to Eloquent Builder |
| `all($columns)` | **Full replacement** — uses `scan()` | Parent uses `query()->get()` |
| `create($fillables, $options)` | **New static** — creates instance + `save()` | Parent on Eloquent Builder |
| `save($options)` | **Adapted from Laravel 12.x** — uses `newQuery()` not `newModelQuery()` | Connection name setting after insert |
| `incrementOrDecrement(...)` | **Full replacement** — uses `key()` + builder's increment/decrement | `setKeysForSaveQuery()`, `newQueryWithoutScopes()`, `isClassDeviable()` |
| `performUpdate($query)` | **Adapted** — uses `key()` + `updateItem()` | `getDirtyForUpdate()` (uses `getDirty()`), `setKeysForSaveQuery()` |
| `performInsert($query)` | **Adapted** — uses `putItem()` + `attribute_not_exists` condition | `usesUniqueIds()`, `insertGetId`, `getAttributesForInsert()` |
| `delete()` | **Adapted from Laravel 12.x** — uses `deleteItem()` with key | `touchOwners()`, `performDeleteOnModel()` |
| `__call($method, $params)` | **Full replacement** — allowlist-based forwarding | Relation resolvers, `through()` |

DynamoDB-only: `meta()`, `setMeta()`.

---

### Other Classes (no significant overrides)

- **`AuthUserProvider`** — implements `UserProvider` contract. Uses Model's `find()` and `keyCondition()` for auth.
- **`DynamodbServiceProvider`** — registers `'dynamodb'` connection driver.
- **`Helpers\Collection`** — extends `Illuminate\Support\Collection`, adds `setMeta()`, `getMeta()`, `getLastEvaluatedKey()`.
- **`Helpers\NumberIterator`** — standalone `Iterator` for placeholder numbering.
- **`ExpressionAttributes`** — standalone utility, no base class.
- **`KeyMissingException`** — extends `RuntimeException`.

## Data Flow

```
Model::find(['id' => '1'])
  → Model::getItem()
    → Model::__call() forwards to Query\Builder
      → Builder::getItem()
        → Builder::process('getItem', 'processSingleItem')
          → Grammar compiles params (TableName, Key, ExpressionAttributes, ...)
          → Connection::__call() → DynamoDbClient::getItem($params)
          → Processor::processSingleItem() → unmarshal → Model::newFromBuilder()
```

```
Model::save() [new model]
  → Model::performInsert($query)
    → $query->condition('id', 'attribute_not_exists')  // via __call → condition_query
    → $query->putItem($attributes)
      → Builder::process('putItem', null)
        → Grammar compiles (TableName, Item, ConditionExpression, ExpressionAttributes)
        → Connection::__call() → DynamoDbClient::putItem($params)
```
