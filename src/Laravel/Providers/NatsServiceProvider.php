<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use LaravelNats\Core\Client;
use LaravelNats\Laravel\NatsManager;

/**
 * NatsServiceProvider registers NATS services with the Laravel container.
 *
 * This provider:
 * - Publishes the config file
 * - Registers the NatsManager as a singleton
 * - Provides facade access
 * - Implements deferred loading for performance
 */
class NatsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/nats.php',
            'nats',
        );

        $this->registerManager();
        $this->registerBindings();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfig();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'nats',
            NatsManager::class,
            Client::class,
        ];
    }

    /**
     * Register the NATS manager.
     */
    protected function registerManager(): void
    {
        $this->app->singleton('nats', function ($app) {
            return new NatsManager($app);
        });

        $this->app->alias('nats', NatsManager::class);
    }

    /**
     * Register additional container bindings.
     */
    protected function registerBindings(): void
    {
        // Bind the Client class to resolve from the default connection
        $this->app->bind(Client::class, function ($app) {
            return $app['nats']->connection();
        });
    }

    /**
     * Publish the configuration file.
     */
    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config/nats.php' => config_path('nats.php'),
            ], 'nats-config');
        }
    }
}
