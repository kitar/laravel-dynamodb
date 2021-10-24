# Laravel DynamoDB

[![test](https://github.com/kitar/laravel-dynamodb/workflows/test/badge.svg)](https://github.com/kitar/laravel-dynamodb/actions)
[![codecov](https://codecov.io/gh/kitar/laravel-dynamodb/branch/master/graph/badge.svg)](https://codecov.io/gh/kitar/laravel-dynamodb/branch/master)

A DynamoDB based Eloquent model and Query builder for Laravel.

- [Motivation](#motivation)
- [Installation](#installation)
  * [Laravel](#laravel)
  * [Non-Laravel projects](#non-laravel-projects)
- [Sample data](#sample-data)
- [Model](#model)
  * [Extending the base model](#extending-the-base-model)
  * [Basic Usage](#basic-usage)
    + [Retrieving all models](#retrieving-all-models)
    + [Retrieving a model](#retrieving-a-model)
    + [save()](#save)
    + [update()](#update)
    + [delete()](#delete)
    + [increment() / decrement()](#increment--decrement)
  * [Advanced Queries](#advanced-queries)
- [Authentication with model](#authentication-with-model)
  * [Register custom user provider](#register-custom-user-provider)
  * [Change auth config](#change-auth-config)
- [Query Builder](#query-builder)
  * [Basic Usage](#basic-usage-1)
    + [getItem()](#getitem)
    + [putItem()](#putitem)
    + [updateItem()](#updateitem)
    + [deleteItem()](#deleteitem)
  * [Projection Expressions](#projection-expressions)
    + [select()](#select)
  * [Condition Expressions](#condition-expressions)
    + [condition()](#condition)
    + [conditionIn()](#conditionin)
    + [conditionBetween()](#conditionbetween)
  * [Working with Queries](#working-with-queries)
    + [query() and keyCondition()](#query-and-keycondition)
    + [keyConditionBetween()](#keyconditionbetween)
  * [Working with Scans](#working-with-scans)
    + [scan()](#scan)
  * [Filtering the Results](#filtering-the-results)
    + [filter()](#filter)
    + [filterIn()](#filterin)
    + [filterBetween()](#filterbetween)
  * [Paginating the Results](#paginating-the-results)
    + [exclusiveStartKey()](#exclusivestartkey)
  * [Using Global Secondary Indexes](#using-global-secondary-indexes)
    + [index()](#index)
  * [Atomic Counter](#atomic-counter)
  * [DynamoDB-specific operators for condition() and filter()](#dynamodb-specific-operators-for-condition-and-filter)
    + [Comparators](#comparators)
    + [functions](#functions)
- [Testing](#testing)

## Motivation

- I want to use DynamoDB with Laravel. (e.g., authenticate with custom user provider)
- I want to use a simple API which doesn't need to worry about cumbersome things like manually handling Expression Attributes.
- I want to extend Laravel's code as much as I can to:
    - Rely on Laravel's robust codes.
    - keep the additional implementation simple and maintainable.
- I don't want to make it fully compatible with Eloquent because DynamoDB is different from relational databases.
- I'm longing for [jessengers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb). What if we have that for DynamoDB?

## Installation

Install the package via Composer:

```
$ composer require kitar/laravel-dynamodb
```

### Laravel (6.x, 7.x, 8.x)

Add dynamodb configs to config/database.php:

```php
'connections' => [

    'dynamodb' => [
        'driver' => 'dynamodb',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'token' => env('AWS_SESSION_TOKEN', null),
        'endpoint' => env('DYNAMODB_ENDPOINT', null),
        'prefix' => '', // table prefix
    ],

    ...

],
```

### Non-Laravel projects

For usage outside Laravel, you can create the connection manually and start querying with [Query Builder](#query-builder).

```php
$connection = new Kitar\Dynamodb\Connection([
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'token' => env('AWS_SESSION_TOKEN', null),
    'endpoint' => env('DYNAMODB_ENDPOINT', null),
    'prefix' => '', // table prefix
]);

$connection->table('your-table')->...
```

## Sample data

Many of the example codes in this document are querying to [DynamoDB's official sample data](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/SampleData.LoadData.html). If you want to try these codes with actual DynamoDB tables, it's handy to load them to your tables before.

## Model

DynamoDB model extends Eloquent model so that we can use familiar features such as mutators, serialization, etc.

The main difference between Eloquent model and DynamoDB model is:

- Eloquent model
    - Can handle relations.
    - Forward calls to model (Eloquent) query builder. (e.g., `create`, `createOrFirst` `where` `with`)
- DynamoDB model
    - Cannot handle relations.
    - Forward calls to database (DynamoDB) query builder. (e.g., `getItem`, `putItem`, `scan`, `filter`)

### Extending the base model

Most of the attributes are the same as the original Eloquent model, but there are few DynamoDB-specific attributes.

| Name           | Required | Description                     |
|----------------|----------|---------------------------------|
| table          | yes      | Name of the Table.              |
| primaryKey     | yes      | Name of the Partition Key.      |
| sortKey        |          | Name of the Sort Key.           |
| sortKeyDefault |          | Default value for the Sort Key. |

For example, if our table has only partition key, the model will look like this:

```php
use Kitar\Dynamodb\Model\Model;

class ProductCatalog extends Model
{
    protected $table = 'ProductCatalog';
    protected $primaryKey = 'Id';
    protected $fillable = ['Id', 'Price', 'Title'];
}
```

If our table also has sort key:

```php
use Kitar\Dynamodb\Model\Model;

class Thread extends Model
{
    protected $table = 'Thread';
    protected $primaryKey = 'ForumName';
    protected $sortKey = 'Subject';
    protected $fillable = ['ForumName', 'Subject'];
}
```

If we set `sortKeyDefault`, it will be used when we instantiate or call `find` without sort key.

```php
use Kitar\Dynamodb\Model\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'User';
    protected $primaryKey = 'email';
    protected $sortKey = 'type';
    protected $sortKeyDefault = 'profile';
    protected $fillable = [
        'name', 'email', 'password', 'type',
    ];
}
```

> Note that this model is implementing `Illuminate\Contracts\Auth\Authenticatable` and using `Illuminate\Auth\Authenticatable`. This is optional, but if we use them, we can use this model with authentication as well. For authentication, please refer to [Authentication section](#authentication-with-model)) for more details.

### Basic Usage

#### Retrieving all models

```php
$products = ProductCatalog::scan();
```

or alternatively,

```php
$products = ProductCatalog::all();
```

> DynamoDB can only handle result set up to 1MB per call, so we have to paginate if there are more results. see [Paginating the Results](#paginating-the-results) for more details.

#### Retrieving a model

If the model has only partition key:

```php
ProductCatalog::find(101);
```

If the model also has sort key:

```php
Thread::find([
    'ForumName' => 'Amazon DynamoDB', // Partition key
    'Subject' => 'DynamoDB Thread 1' // Sort key
]);
```

If the model has sort key and `sortKeyDefault` is defined:

```php
User::find('foo@bar.com'); // Partition key. sortKeyDefault will be used for Sort key.
```

#### save()

```php
$user = new User([
    'email' => 'foo@bar.com',
    'type' => 'profile' // Sort key. If we don't specify this, sortKeyDefault will be used.
]);

$user->save();
```

```php
$user->name = 'foo';
$user->save();
```

#### update()

```php
$user->update([
    'name' => 'foobar'
]);
```

#### delete()

```php
$user->delete();
```

#### increment() / decrement()

When we call `increment()` and `decrement()`, the [Atomic Counter](#atomic-counter) will be used under the hood.

```php
$user->increment('views', 1);
$user->decrement('views', 1);
```

We can also pass additional attributes to update.

```php
$user->increment('views', 1, [
    'last_viewed_at' => '...',
]);
```

### Advanced Queries
We can use Query Builder functions through model such as `query` `scan` `filter` `condition` `keyCondition` etc.

For example:

```php
Thread::keyCondition('ForumName', '=', 'Amazon DynamoDB')
        ->keyCondition('Subject', 'begins_with', 'DynamoDB')
        ->filter('Views', '=', 0)
        ->query();
```

Please refer to [Query Builder](#query-builder) for the details.

## Authentication with model

We can create a Custom User Provider to authenticate with DynamoDB. For the detail, please refer to [Laravel's official document](https://laravel.com/docs/8.x/authentication#adding-custom-user-providers).

To use authentication with the model, the model should implement `Illuminate\Contracts\Auth\Authenticatable` contract. In this section, we'll use the example `User` model above.

### Register custom user provider

After we prepare authenticatable model, we need to make the custom user provider. We can make it own (it's simple), but we'll use `Kitar\Dynamodb\Model\AuthUserProvider` in this section.

To register custom user provider, add codes below in `App/Providers/AuthServiceProvider.php`.

```php
use Kitar\Dynamodb\Model\AuthUserProvider;
...
public function boot()
{
    $this->registerPolicies();

    Auth::provider('dynamodb', function ($app, array $config) {
        return new AuthUserProvider(
            $app['hash'],
            $config['model'],
            $config['api_token_name'] ?? null,
            $config['api_token_index'] ?? null
        );
    });
}
```

### Change auth config

Then specify driver and model name for authentication in `config/auth.php`.

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
        'api_token_name' => 'api_token',
        'api_token_index' => 'api_token-index'
    ],
],
```

`api_token_name` and `api_token_index` are optional, but we need them if we use api token authentication.

### Registration Controller

You might need to modify the registration controller. For example, if we use Laravel Breeze, the modification looks like below.

```php
class RegisteredUserController extends Controller
{
    ...

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', function ($attribute, $value, $fail) {
                if (User::find($value)) {
                    $fail('The '.$attribute.' has already been taken.');
                }
            }],
            'password' => 'required|string|confirmed|min:8',
        ]);

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->save();

        Auth::login($user);

        event(new Registered($user));

        return redirect(RouteServiceProvider::HOME);
    }
}
```

There are two modifications. The first one is adding the closure validator for `email` instead of `unique` validator. The second one is using the `save()` method to create user instead of the `create()` method.

## Query Builder

We can use Query Builder without model.

```php
$result = DB::table('Thread')->scan();
```

Or even outside Laravel.

```php
$connection = new Kitar\Dynamodb\Connection([
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'token' => env('AWS_SESSION_TOKEN', null),
    'endpoint' => env('DYNAMODB_ENDPOINT', null),
    'prefix' => '', // table prefix
]);

$result = $connection->table('Thread')->scan();

```

If we query through the model, we don't need to specify the table name, and the response will be the model instance(s).

```php
$threads = Thread::scan();
```

### Basic Usage

#### getItem()

```php
$response = DB::table('ProductCatalog')
                ->getItem(['Id' => 101]);
```

> Instead of marshaling manually, pass a plain array. `Kitar\Dynamodb\Query\Grammar` will automatically marshal them before querying.

#### putItem()

```php
DB::table('Thread')
    ->putItem([
        'ForumName' => 'Amazon DynamoDB',
        'Subject' => 'New discussion thread',
        'Message' => 'First post in this thread',
        'LastPostedBy' => 'fred@example.com',
        'LastPostedDateTime' => '201603190422'
    ]);
```

#### updateItem()

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

Currently, we only support simple `SET` and `REMOVE` actions. If the attribute has value, it will be passed to `SET` action. If the value is null, it will be passed to `REMOVE` action.

#### deleteItem()

```php
DB::table('Thread')
    ->deleteItem([
        'ForumName' => 'Amazon DynamoDB',
        'Subject' => 'New discussion thread'
    ]);
```

### Projection Expressions

A Projection Expression is a string that identifies the attributes that web want. (It's like `select` statement for SQL)

#### select()

We can specify Projection Expressions in the same manner as the original `select` clause.

```php
$response = DB::table('ProductCatalog')
                ->select('Price', 'Title')
                ->getItem(['Id' => 101]);
```

### Condition Expressions

When we manipulate data in Amazon DynamoDB table, we use `putItem`, `updateItem` and `DeleteItem`. We can use Condition Expressions to determine which items should be modified.

#### condition()

To specify Condition Expression, we use `condition` clause. This works basically same as the original `where` clause, but it's for Condition Expressions.

```php
DB::table('ProductCatalog')
    ->condition('Id', 'attribute_not_exists')
    ->putItem([
        'Id' => 101,
        'ProductCategory' => 'Can I overwrite?'
    ]);
```

> Note that we specify `attribute_not_exists` for the operator of condition. This is DynamoDB-specific operator which called `function`. See [DynamoDB-specific operators for condition() and filter()](#dynamodb-specific-operators-for-condition-and-filter) for more details.

OR statements

```php
DB::table('ProductCatalog')
    ->condition('Id', 'attribute_not_exists')
    ->orCondition('Price', 'attribute_not_exists)
    ->putItem([...]);
```

AND statements

```php
DB::table('ProductCatalog')
    ->condition('Id', 'attribute_not_exists')
    ->condition('Price', 'attribute_not_exists)
    ->putItem([...]);
```

#### conditionIn()

```php
ProductCatalog::key(['Id' => 101])
                ->conditionIn('ProductCategory', ['Book', 'Bicycle'])
                ->updateItem([
                    'Description' => 'updated!'
                ]);
```

#### conditionBetween()

```php
ProductCatalog::key(['Id' => 101])
                ->conditionBetween('Price', [0, 10])
                ->updateItem([
                    'Description' => 'updated!'
                ]);
```

### Working with Queries

The Query operation in Amazon DynamoDB finds items based on primary key values.

#### query() and keyCondition()

When we `query`, we must specify `keyCondition` as well.

We can use some comparison operators for sort key, but we must use the equality condition for the partition key.

```php
$response = DB::table('Thread')
                ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                ->keyCondition('Subject', 'begins_with', 'DynamoDB')
                ->query();
```

#### keyConditionBetween()

```php
$response = DB::table('Thread')
                ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
                ->keyConditionBetween('Subject', ['DynamoDB Thread 1', 'DynamoDB Thread 2'])
                ->query();
```

### Working with Scans

#### scan()

```php
$response = DB::table('Thread')->scan();
```

### Filtering the Results

When we `query` or `scan`, we can filter results with Filter Expressions before it returned.

It can't reduce the amount of read capacity, but it can reduce the size of traffic data.

#### filter()

```php
$response = DB::table('Thread')
                ->filter('LastPostedBy', '=', 'User A')
                ->scan();
```

OR statement

```php
$response = DB::table('Thread')
                ->filter('LastPostedBy', '=', 'User A')
                ->orFilter('LastPostedBy', '=', 'User B')
                ->scan();
```

AND statement

```php
$response = DB::table('Thread')
                ->filter('LastPostedBy', '=', 'User A')
                ->filter('Subject', 'begins_with', 'DynamoDB')
                ->scan();
```

#### filterIn()

```php
$response = DB::table('Thread')
                ->filterIn('LastPostedBy', ['User A', 'User B'])
                ->scan();
```

#### filterBetween()

```php
$response = DB::table('ProductCatalog')
                ->filterBetween('Price', [0, 100])
                ->scan();
```

### Paginating the Results

A single `query` or `scan` only returns a result set that fits within the 1 MB size limit. If there are more results, we need to paginate.

#### exclusiveStartKey()

If there are more results, the response contains `LastEvaluatedKey`.

```php
$response = DB::table('ProductCatalog')
                ->limit(5)
                ->scan();

$response['LastEvaluatedKey']; // array
```

We can pass this key to `exclusiveStartKey` to get next results.

```php
$response = DB::table('ProductCatalog')
                ->exclusiveStartKey($response['LastEvaluatedKey'])
                ->limit(5)
                ->scan();
```

If you are using Query Builder through model, you can access to `exclusiveStartKey` by:

```php
$products = ProductCatalog::limit(5)->scan();

$products->first()->meta()['LastEvaluatedKey']; // array
```

### Using Global Secondary Indexes

Some applications might need to perform many kinds of queries, using a variety of different attributes as query criteria. To support these requirements, you can create one or more global secondary indexes and issue `query` requests against these indexes in Amazon DynamoDB.

#### index()

Use `index` clause to specify Global Secondary Index name.

```php
$response = DB::table('Reply')
                ->index('PostedBy-Message-index')
                ->keyCondition('PostedBy', '=', 'User A')
                ->keyCondition('Message', '=', 'DynamoDB Thread 2 Reply 1 text')
                ->query();
```

### Atomic Counter

DynamoDB [supports Atomic Counter](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/WorkingWithItems.html#WorkingWithItems.AtomicCounters). When we call `increment()` and `decrement()` [through Model](#increment--decrement) or Query Builder, Atomic Counter will be used under the hood.

```php
DB::('Thread')->key([
    'ForumName' => 'Laravel',
    'Subject' => 'Laravel Thread 1'
])->increment('Replies', 2);
```

We can also pass additional attributes to update.

```php
DB::('Thread')->key([
    'ForumName' => 'Laravel',
    'Subject' => 'Laravel Thread 1'
])->increment('Replies', 2, [
    'LastPostedBy' => 'User A',
]);
```

### DynamoDB-specific operators for condition() and filter()

For `condition` and `filter` clauses, we can use DynamoDB's comparators and functions.

#### Comparators

`=` `<>` `<` `<=` `>` `>=` can be used in the form of:

```php
filter($key, $comparator, $value);
```

#### functions

Available functions are:

```php
filter($key, 'attribute_exists');
filter($key, 'attribute_not_exists');
filter($key, 'attribute_type', $type);
filter($key, 'begins_with', $value);
filter($key, 'contains', $value);
```

> `size` function is not supported at this time.

## Testing

```
$ ./vendor/bin/phpunit
```
