<?php

namespace Kitar\Dynamodb;

use Illuminate\Support\ServiceProvider;

class DynamodbServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        //
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('dynamodb', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });
    }
}
