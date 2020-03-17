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
- [Authentication (Custom User Provider)](#authentication-custom-user-provider)
    - [Make User model](#make-user-model)
    - [Make custom user provider](#make-custom-user-provider)
    - [Register our custom user provider](#register-our-custom-user-provider)
    - [Add config for user provider](#add-config-for-user-provider)

## Motivation

I started trying to make simple QueryBuilder because:

- I want to use DynamoDB with Laravel. (e.g., authenticate with custom user provider)
- I don't want to extend Eloquent because DynamoDB looks quite different from relational databases.
- I want to use a simple API which doesn't need to worry about cumbersome things like manually handling Expression Attributes.
- I'm longing for [jessengers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb). What if we have that for DynamoDB?

There's no Model for DynamoDB at this time, but I might add it if there's a good design idea.

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
$item = DB::table('Thread')
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
$item = DB::table('ProductCatalog')
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
$items = DB::table('Thread')
             ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
             ->query();
```

```php
$items = DB::table('Thread')
             ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
             ->keyCondition('Subject', '=', 'DynamoDB Thread 1')
             ->query();
```

```php
$items = DB::table('Reply')
             ->keyCondition('Id', '=', 'Amazon DynamoDB#DynamoDB Thread 1')
             ->keyCondition('ReplyDateTime', 'begins_with', '2015-09')
             ->query();
```

#### Filter Expressions for Query

Use `filter` clause to build Filter Conditions.

For `query`, KeyConditionExprssion is required, so we specify both KeyConditionExpression and FilterExpression.

```php
$itmes = DB::table('Thread')
           ->keyCondition('ForumName', '=', 'Amazon DynamoDB')
           ->keyCondition('Subject', '=', 'DynamoDB Thread 1')
           ->filter('Views', '>', 3)
           ->query();
```

### Working with Scans in DynamoDB

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Scan.html)

#### Filter Expressions for Scan

```php
$items = DB::table('Thread')
             ->filter('LastPostedBy', '=', 'User A')
             ->scan();
```

### Using Global Secondary Indexes in DynamoDB

[corresponding document](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/GSI.html)

#### Querying a Global Secondary Index

Use `index` clause to specify IndexName.

```php
$items = DB::table('Reply')
             ->index('PostedBy-Message-index')
             ->keyCondition('PostedBy', '=', 'User A')
             ->keyCondition('Message', '=', 'DynamoDB Thread 2 Reply 1 text')
             ->query();
```

## Authentication (Custom User Provider)

We can create Custom User Provider to authenticate with DynamoDB. For the detail, please refer to [Laravel's official document](https://laravel.com/docs/6.x/authentication#adding-custom-user-providers).

The Following codes are an example of custom user provider. It's simplified and not tested, so **don't use them in production**.

### Make User model

To bind with authentication, we need to prepare User model which implements `Illuminate\Contracts\Auth\Authenticatable`.

```php
namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Auth\Authenticatable as AuthAuthenticatable;

class User implements AuthAuthenticatable
{
    protected $params;

    public function __construct($params)
    {
        $this->params = collect($params);
    }

    public function __get($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->params['id'];
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->params['password'];
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
        return $this->params['remember_token'] ?? null;
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param  string  $value
     * @return void
     */
    public function setRememberToken($value)
    {
        $this->params['remember_token'] = $value;

        DB::table('User')->putItem($this->params->toArray());
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}
```

### Make custom user provider

Next, we'll make custom user provider.

```php
namespace App\Providers;

use App\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as BaseUserProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthUserProvider implements BaseUserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $item = DB::table('User')->getItem(['id' => $identifier]);

        return new User($item);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->retrieveById($identifier);

        if ($user->getRememberToken() == $token) {
            return $user;
        }
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        return $this->retrieveById($credentials['email']);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return Hash::check($credentials['password'], $user->password);
    }
}
```

### Register our custom user provider

Then we register them at `boot()` method in `App/Providers/AuthServiceProvider.php`.

```php
public function boot()
{
    $this->registerPolicies();

    Auth::provider('dynamodb', function ($app, array $config) {
        return new AuthUserProvider;
    });
}
```

### Add config for user provider

Finally, we can add config for our custom user provider at `config/database.php`.

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
    ],
],
```

## Testing

```
$ ./vendor/bin/phpunit
```
