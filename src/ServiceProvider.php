<?php

namespace Attla\Dynamodb;

use Attla\Dynamodb\Pagination\Paginator;
use Attla\Dynamodb\Validation\DatabasePresenceVerifier;
use Illuminate\Support\Arr;

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
        $this->registerPagination();
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
     * Register the paginator
     *
     * @return void
     */
    protected function registerPagination()
    {
        $request = $this->app['request'];
        Paginator::viewFactoryResolver(fn () => $this->app['view']);
        Paginator::currentPageResolver(fn ($page = '') => $request->input('page'));
        Paginator::queryStringResolver(fn () => $request->query());
        Paginator::pageSizeResolver(fn () => $request->input('pageSize'));
        // Paginator::currentPathResolver(fn () => explode('?', $request->getRequestUri())[0] ?? '/');
        Paginator::currentPathResolver(function () use ($request) {
            $baseUrl = explode('?', $request->getRequestUri())[0] ?? '/';

            $filtered = Arr::except($request->query(), 'page');
            if ($query = Arr::query($filtered)){
                $baseUrl .= '?' . $query;
            }

            return $baseUrl;
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
