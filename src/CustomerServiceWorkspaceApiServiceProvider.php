<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Registers the routes and nothing else. It binds no timeline source and no action gateway. */
final class CustomerServiceWorkspaceApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/customer-service-workspace-api.php', 'customer-service-workspace-api');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/customer-service-workspace-api.php' => $this->app->configPath('customer-service-workspace-api.php'),
        ], 'customer-service-workspace-api-config');

        $prefix = Config::get('customer-service-workspace-api.route.prefix', 'api/customer-service');

        Route::group([
            'prefix' => is_string($prefix) ? $prefix : 'api/customer-service',
            'middleware' => (array) Config::get('customer-service-workspace-api.route.middleware', []),
            'domain' => Config::get('customer-service-workspace-api.route.domain'),
            'as' => 'customer-service-api.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/api.php'));
    }
}
