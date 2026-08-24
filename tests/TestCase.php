<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\CustomerServiceWorkspaceApiServiceProvider;
use Liberu\Ecommerce\CustomerServiceWorkspace\CustomerServiceWorkspaceServiceProvider;
use Liberu\PackageTestbench\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_unique(array_merge(
            [CustomerServiceWorkspaceServiceProvider::class, CustomerServiceWorkspaceApiServiceProvider::class],
            parent::getPackageProviders($app),
        )));
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', Fixtures\ApiActor::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Stands in for the host's channel middleware, which resolves the
        // storefront host to a merchant and the session to a person. Nothing a
        // client sends reaches the attribute bag on its own.
        $this->app->make(Kernel::class)->pushMiddleware(Fixtures\ResolveTestStorefront::class);
    }
}
