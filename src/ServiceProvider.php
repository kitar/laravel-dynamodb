<?php

namespace Attla\Dynamodb;

use Attla\Dynamodb\Validation\DatabasePresenceVerifier;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application events
     */
    public function boot()
    {
        //
    }

    /**
     * Register the service provider
     */
    public function register()
    {
        $this->registerDynamodb();
        // laravel 11 validation rewriter
        $this->registerPresenceVerifier();
        $this->registerUncompromisedVerifier();
        $this->registerValidationFactory();
    }

    protected function registerDynamodb() {
        $this->app->resolving('db', function ($db) {
            $db->extend('dynamodb', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });
    }

    /**
     * Register the database presence verifier
     *
     * @return void
     */
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app) {
            return new DatabasePresenceVerifier($app['db']);
        });
    }

    /**
     * Register the uncompromised password verifier
     *
     * @return void
     */
    protected function registerUncompromisedVerifier()
    {
        // TODO: impement..
        // $this->app->singleton(UncompromisedVerifier::class, function ($app) {
        //     return new NotPwnedVerifier($app[HttpFactory::class]);
        // });
    }

    /**
     * Register the validation factory
     *
     * @return void
     */
    protected function registerValidationFactory()
    {
        $this->app->resolving('validator', function ($validator, $app) {
            $validator->setPresenceVerifier(new DatabasePresenceVerifier($app['db']));
        });
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides()
    {
        return ['validator', 'validation.presence'];
    }
}
