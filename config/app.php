<?php

use App\Providers\AppServiceProvider;
use App\Providers\DockerComposeServiceProvider;
use App\Providers\EnvServiceProvider;
use App\Providers\SSLServiceProvider;
use App\Recipes\Angular\AngularRecipeServiceProvider;
use App\Recipes\Laravel\LaravelRecipeServiceProvider;
use App\Recipes\PlainPhp\PlainPhpRecipeServiceProvider;
use App\Recipes\ReverseProxy\ReverseProxyRecipeServiceProvider;
use App\Recipes\Wordpress\WordpressRecipeServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => 'Dock',

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value determines the "version" your application is currently running
    | in. You may want to follow the "Semantic Versioning" - Given a version
    | number MAJOR.MINOR.PATCH when an update happens: https://semver.org.
    |
    */

    'version' => app('git.version'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. This can be overridden using
    | the global command line "--env" option when calling commands.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        AppServiceProvider::class,
        DockerComposeServiceProvider::class,
        ReverseProxyRecipeServiceProvider::class,
        LaravelRecipeServiceProvider::class,
        PlainPhpRecipeServiceProvider::class,
        WordpressRecipeServiceProvider::class,
        AngularRecipeServiceProvider::class,
        EnvServiceProvider::class,
        SSLServiceProvider::class,
    ],

];
