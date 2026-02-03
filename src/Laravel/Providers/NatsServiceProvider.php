<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use LaravelNats\Core\Client;
use LaravelNats\Laravel\Console\Commands\NatsConsumerCreateCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerDeleteCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerListCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamCreateCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamDeleteCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamListCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamPurgeCommand;
use LaravelNats\Laravel\NatsManager;
use LaravelNats\Laravel\Queue\NatsConnector;

/**
 * NatsServiceProvider registers NATS services with the Laravel container.
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
        $this->registerQueueDriver();
        $this->registerJetStreamCommands();
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
     * Register JetStream Artisan commands.
     */
    protected function registerJetStreamCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            NatsStreamListCommand::class,
            NatsStreamInfoCommand::class,
            NatsStreamCreateCommand::class,
            NatsStreamDeleteCommand::class,
            NatsStreamPurgeCommand::class,
            NatsConsumerListCommand::class,
            NatsConsumerInfoCommand::class,
            NatsConsumerCreateCommand::class,
            NatsConsumerDeleteCommand::class,
        ]);
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

    /**
     * Register the NATS queue driver.
     */
    protected function registerQueueDriver(): void
    {
        $this->app->afterResolving('queue', function (\Illuminate\Queue\QueueManager $manager): void {
            $manager->addConnector('nats', function () {
                return new NatsConnector();
            });
        });
    }
}
