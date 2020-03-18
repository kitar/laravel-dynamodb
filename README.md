# Laravel DynamoDB (under development)

This package provides QueryBuilder for DynamoDB.

- [Motivation](#motivation)
- [Installation](#installation)
    - [Laravel](#laravel)
    - [Non-Laravel Projects](#non-laravel-projects)
- [Example Usage](#example-usage)
    - [Sample Data](#sample-data)
    - [Working with Items and Attributes](#working-with-items-and-attributes)
        - [GetItem](#getitem)
        - [PutItem](#putitem)
        - [UpdateItem](#updateitem)
        - [DeleteItem](#deleteitem)
    - [Projection Expressions](#projection-expressions)
    - [Condition Expressions](#condition-expressions)
        - [Sample Item](#sample-item)
        - [Preventing Overwrites of an Existing Item](#preventing-overwrites-of-an-existing-item)
        - [Checking for Attributes in an Item](#checking-for-attributes-in-an-item)
    - [Working with Queries in DynamoDB](#working-with-queries-in-dynamodb)
        - [Key Condition Expression](#key-condition-expression)
        - [Filter Expressions for Query](#filter-expressions-for-query)
    - [Working with Scans in DynamoDB](#working-with-scans-in-dynamodb)
        - [Filter Expressions for Scan](#filter-expressions-for-scan)
    - [Using Global Secondary Indexes in DynamoDB](#using-global-secondary-indexes-in-dynamodb)
        - [Querying a Global Secondary Index](#querying-a-global-secondary-index)
- [Models](#models)
    - [Basic Usage of Model](#basic-usage-of-model)
        - [Calling Builder methods through model](#calling-builder-methods-through-model)
        - [CRUD operations](#crud-operations)
- [Authentication](#authentication)
    - [Make user model](#make-user-model)
    - [Make custom user provider](#make-custom-user-provider)
    - [Register custom user provider](#register-custom-user-provider)
    - [Add config for user provider](#add-config-for-user-provider)

## Motivation

I started trying to make simple QueryBuilder because:

- I want to use DynamoDB with Laravel. (e.g., authenticate with custom user provider)
- I don't want to extend Eloquent Query Builder because DynamoDB looks quite different from relational databases.
- I want to use a simple API which doesn't need to worry about cumbersome things like manually handling Expression Attributes.
- I'm longing for [jessengers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb). What if we have that for DynamoDB?

## Installation

Install the package via Composer:

```
$ composer require kitar/laravel-dynamodb
```

### Laravel

Add dynamodb configs to config/database.php:

```php
'connections' => [

    'dynamodb' => [
        'driver' => 'dynamodb',
        'region' => 'your-region',
        'access_key' => 'your-access-key',
        'secret_key' => 'your-secret-key'
    ],

    ...

],
```

In case your Laravel version does NOT autoload the packages, add the service provider to config/app.php:

```php
Kitar\Dynamodb\DynamodbServiceProvider::class
```

### Non-Laravel projects

For usage outside Laravel, you can create the connection manually and start querying.

```php
$connection = new Kitar\Dynamodb\Connection([
    'region' => 'your-region',
    'access_key' => 'your-access-key',
    'secret_key' => 'your-secret-key'
]);

$connection->table('your-table')->...
```

## Example Usage

In this section, we'll make queries similar to examples in [DynamoDB official document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/WorkingWithDynamo.html).

### Sample Data

If you want to try these commands with actual DynamoDB tables, it's handy to use [DynamoDB's sample data](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/SampleData.LoadData.html).

### Working with Items and Attributes

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/WorkingWithItems.html)

#### GetItem

```php
$response = DB::table('ProductCatalog')
                ->getItem(['Id' => 101]);
```

> Instead of marshaling manually, pass a plain array. `Kitar\Dynamodb\Query\Grammar` will automatically marshal them before querying.

#### PutItem

```php
$response = DB::table('Thread')
                ->putItem([
                    'ForumName' => 'Amazon DynamoDB',
                    'Subject' => 'New discussion thread',
                    'Message' => 'First post in this thread',
                    'LastPostedBy' => 'fred@example.com',
                    'LastPostedDateTime' => '201603190422'
              ]);
```

#### UpdateItem

Currently, we only support simple SET and REMOVE actions.

If value is set, `updateItem` will SET them.

```php
DB::table('Thread')
    ->key([
        'ForumName' => 'Laravel',
        'Subject' => 'Laravel Thread 1'
    ])->updateItem([
        'LastPostedBy' => 'User A', // SET
        'Replies' => 1 // SET
    ]);
```

If value is null, `updateItem` will REMOVE them.

```php
DB::table('Thread')
    ->key([
        'ForumName' => 'Laravel',
        'Subject' => 'Laravel Thread 1'
    ])->updateItem([
        'LastPostedBy' => null, // REMOVE
        'Replies' => null, // REMOVE
        'Message' => 'Updated' // SET
    ]);
```

#### DeleteItem

```php
DB::table('Thread')
    ->deleteItem([
        'ForumName' => 'Amazon DynamoDB',
        'Subject' => 'New discussion thread'
    ]);
```

### Projection Expressions

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Expressions.ProjectionExpressions.html)

Use `select` clause for Projection Expressions.

```php
$response = DB::table('ProductCatalog')
                ->select('Price', 'Title')
                ->getItem(['Id' => 101]);
```

### Condition Expressions

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Expressions.ConditionExpressions.html)

#### Sample item

This section uses an additional sample item. Use this command to add it.

```php
DB::table('ProductCatalog')
    ->putItem([
        'Id' => 456,
        'ProductCategory' => 'Sporting Goods',
        'Price' => 650
    ]);
```

#### Preventing Overwrites of an Existing Item

Use `condition` clause to build Condition Expressions. This works basically same as original `where` clause, but it's for the ConditionExpression.

```php
DB::table('ProductCatalog')
    ->condition('Id', 'attribute_not_exists')
    ->putItem([
        'Id' => 456,
        'ProductCategory' => 'Can I overwrite?'
    ]);
```

#### Checking for Attributes in an Item

```php
DB::table('ProductCatalog')
    ->condition('Price', 'attribute_not_exists')
    ->deleteItem([
        'Id' => 456
    ]);
```

> We can also specify functions instead of operators in `where` clause. In the case above, `attriute_not_exists`.

### Working with Queries in DynamoDB

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Query.html)

#### Key Condition Expression

Use `keyCondition` clause to build Key Conditions.

```php
$response = DB::table('Thread')
                ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                ->query();
```

```php
$response = DB::table('Thread')
                ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                ->keyCondition('Subject', '=', 'DynamoDB Thread 1')
                ->query();
```

```php
$response = DB::table('Reply')
                ->keyCondition('Id', '=', 'Amazon DynamoDB#DynamoDB Thread 1')
                ->keyCondition('ReplyDateTime', 'begins_with', '2015-09')
                ->query();
```

#### Filter Expressions for Query

Use `filter` clause to build Filter Conditions.

For `query`, KeyConditionExprssion is required, so we specify both KeyConditionExpression and FilterExpression.

```php
$response = DB::table('Thread')
                ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                ->keyCondition('Subject', '=', 'DynamoDB Thread 1')
                ->filter('Views', '>', 3)
                ->query();
```

### Working with Scans in DynamoDB

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Scan.html)

#### Filter Expressions for Scan

```php
$response = DB::table('Thread')
                ->filter('LastPostedBy', '=', 'User A')
                ->scan();
```

### Using Global Secondary Indexes in DynamoDB

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/GSI.html)

#### Querying a Global Secondary Index

Use `index` clause to specify IndexName.

```php
$response = DB::table('Reply')
                ->index('PostedBy-Message-index')
                ->keyCondition('PostedBy', '=', 'User A')
                ->keyCondition('Message', '=', 'DynamoDB Thread 2 Reply 1 text')
                ->query();
```

## Models

`Kitar\Dynamodb\Model\Model` is an experimental Model implementation for this package.

It works like Eloquent Model, but instead of forwarding calls to "Model" Query, we forward call directly to "DynamoDB" Query.

Because there is no "Model" Query, we can't use handy methods like `find` `create` `firstOrCreate` or something like that. We only have `save` `update` and `delete` for the Model methods to interact with DynamoDB. However, when we query through Model, DynamoDB's response items will be automatically converted to the model instance.

### Basic Usage of Model

Let's say we have some Authenticatable User model. (we'll use this example in the Authentication section as well)

Most attributes are the same as the original Eloquent Model, but there are few DynamoDB specific attributes. `table` `primaryKey` `sortKey` and `sortKeyDefault`.

```php
<?php

namespace App;

use Kitar\Dynamodb\Model\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'table_name';

    /**
     * The Primary (Partition) Key.
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The Sort Key.
     * @var string|null
     */
    protected $sortKey = 'type';

    /**
     * The default value of the Sort Key.
     * @var string|null
     */
    protected $sortKeyDefault = 'profile';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'type'
    ];
}
```

#### Calling Builder methods through model

```php
$response = User::getItem([
    'id' => 'foo@bar.com',
    'type' => 'profile'
]);

$user = $response['Item'];
```

```php
$response = User::filter('type', '=', 'profile')
                  ->scan();

$users = $response['Items'];
```

#### CRUD operations

```php
// create
$newUser = new User([
    'id' => 'foo@bar.com',
    'type' => 'profile'
]);

$newUser->save();

// read
$existingUser = User::getItem([
    'id' => 'foo@bar.com',
    'type' => 'profile'
])['Item'];

// update
$existingUser->foo = 'bar';
$existingUser->save();

$existingUser->update([
    'foo' => 'barbar'
]);

// delete
$existingUser->delete();
```

## Authentication

We can create Custom User Provider to authenticate with DynamoDB. For the detail, please refer to [Laravel's official document](https://laravel.com/docs/6.x/authentication#adding-custom-user-providers).

In this section, we'll use example `App\User` model above to implement DynamoDB authentication.

### Make user model

`Kitar\Dynamodb\Model\Model` is compatible to `Illuminate\Auth\Authenticatable`, so our `App\User` class is just using it.

### Make custom user provider

We'll use `Kitar\Dynamodb\Model\AuthUserProvider` for this time.

### Register custom user provider

Then we register them at `boot()` method in `App/Providers/AuthServiceProvider.php`.

```php
use Kitar\Dynamodb\Model\AuthUserProvider;
...
public function boot()
{
    $this->registerPolicies();

    Auth::provider('dynamodb', function ($app, array $config) {
        return new AuthUserProvider(new $config['model']);
    });
}
```

### Add config for user provider

Finally, we specify model class name in config at `config/database.php`.

```php
'providers' => [
    // Eloquent
    // 'users' => [
    //     'driver' => 'eloquent',
    //     'model' => App\User::class,
    // ],

    // DynamoDB
    'users' => [
        'driver' => 'dynamodb',
        'model' => App\User::class,
    ],
],
```

## Testing

```
$ ./vendor/bin/phpunit
```
