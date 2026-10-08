# Upgrade Guide

## Upgrading to 2.0 from 1.x

v2 re-ports its Eloquent overrides from Laravel 13, so the model behaves more like a regular Eloquent model. Review the changes below.

### Requirements

- PHP 8.3 or later
- Laravel 13

Update `kitar/laravel-dynamodb` to `^2.0` together with Laravel 13. If you are on Laravel 12 or earlier, keep using v1.x.

### Model

#### `increment()` and `decrement()` update `updated_at`

If the model uses timestamps, `increment()` and `decrement()` now also write `updated_at`, as Eloquent does. To disable it, set `$timestamps` to `false` on the model. To set the value yourself, pass `updated_at` in the extra attributes.

After the update, the incremented attribute and the automatically set `updated_at` are no longer marked as dirty, so a following `save()` won't send them again.

#### Items are addressed by their original key

> If your code changes a key attribute on a loaded model and then calls `delete()`, `increment()`, or `decrement()`, it now affects the original item. DynamoDB doesn't allow updating key attributes, so to change the key, create a new item and delete the old one.

### Query Builder

#### `Grammar` requires a connection

If you create `Kitar\Dynamodb\Query\Grammar` yourself, pass the connection to the constructor:

```php
$grammar = new Grammar($connection);
```
