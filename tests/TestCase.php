<?php

namespace Kitar\Dynamodb\Tests;

use Kitar\Dynamodb\DynamodbServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DynamodbServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'dynamodb');
        $app['config']->set('database.connections.dynamodb', [
            'driver' => 'dynamodb',
            'region' => 'us-east-1',
            'access_key' => 'dummy',
            'secret_key' => 'dummy',
        ]);
    }
}
