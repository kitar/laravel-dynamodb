# CLAUDE.md

## Commands

```bash
composer install                                    # Install deps
./vendor/bin/phpunit                                # All tests
./vendor/bin/phpunit tests/Path/To/TestFile.php     # Single file
./vendor/bin/phpunit --filter testMethodName        # Single method
```

## What This Package Does

A Laravel package that adapts Laravel's database layer (Connection, Query Builder, Grammar, Processor, Eloquent Model) to work with AWS DynamoDB. It overrides SQL-oriented parent methods with DynamoDB API calls.

**Targets:** PHP ^8.2, Laravel ^12.0, AWS SDK ^3.0

## Critical Concepts for Making Changes

### Override Strategy

Every class in `src/` extends a Laravel base class and overrides specific methods. When modifying any override:

1. **Check the current Laravel 12.x parent** (`vendor/illuminate/database/...`) for the method signature and behavior
2. **Preserve DynamoDB-specific adaptations** (see `docs/architecture.md` for the full override inventory)
3. **Document intentionally omitted features** with comments explaining why (e.g., subqueries, relationships, bitwise ops)

### ExpressionAttributes — The Key Abstraction

`ExpressionAttributes` replaces SQL parameter binding. It generates placeholders (`#1`, `#2` for names; `:1`, `:2` for values) that DynamoDB requires. All where/filter/condition methods must convert column names and values through this object **before** building the where clause. A single `ExpressionAttributes` instance is shared across the main builder and all nested/dedicated queries to ensure placeholder uniqueness.

### Three Dedicated Query Builders

`Query\Builder` maintains three internal sub-builders, each compiling to a different DynamoDB expression:
- `filter_query` → `FilterExpression` (post-query filtering)
- `condition_query` → `ConditionExpression` (write preconditions)
- `key_condition_query` → `KeyConditionExpression` (partition/sort key conditions)

Methods like `filter()`, `condition()`, `keyCondition()` (and their `In`/`Between` variants) are routed to these via `__call()`. This is the most complex part of the codebase.

### Model Bypasses Eloquent Builder

`Model::newQuery()` returns a `Query\Builder` directly (not an Eloquent Builder). This means:
- No scopes, eager loading, or relationships
- `find()`, `create()`, `all()` are implemented directly on Model as static methods
- `__call()` uses an allowlist to restrict which builder methods are forwarded

### DynamoDB Keys ≠ SQL Primary Keys

Models have `$primaryKey` (partition key) + optional `$sortKey`. `getKey()` returns an associative array, not a scalar. This affects all key-dependent operations (find, update, delete, increment).

## Testing Patterns

- **Mock the DynamoDB client** via Mockery (see existing tests for patterns)
- **Use `dryRun()`** to test query building — returns compiled params without API calls
- **Test models** (UserA–UserD, UserX in `tests/Model/`) have different key configurations (with/without sort key, with defaults)

## File Map

```
src/Kitar/Dynamodb/
├── Connection.php            # Extends DB Connection (no PDO, DynamoDB client)
├── DynamodbServiceProvider.php  # Registers 'dynamodb' driver
├── Query/
│   ├── Builder.php           # Core: overrides where/increment + adds DynamoDB ops
│   ├── Grammar.php           # Compiles queries to DynamoDB API params
│   ├── Processor.php         # Unmarshals DynamoDB responses to models/collections
│   └── ExpressionAttributes.php  # Placeholder manager (#n, :n)
├── Model/
│   ├── Model.php             # Eloquent adapter with composite key support
│   ├── AuthUserProvider.php  # DynamoDB-based auth (find by ID or API token via GSI)
│   └── KeyMissingException.php
└── Helpers/
    ├── Collection.php        # Adds metadata (LastEvaluatedKey for pagination)
    └── NumberIterator.php    # Infinite counter for placeholder generation
```

## Detailed Reference

For the full override inventory (which methods override which parent, what's DynamoDB-specific), see `docs/architecture.md`.
