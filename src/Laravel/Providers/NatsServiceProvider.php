<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Log\LogManager;
use Illuminate\Queue\Worker;
use Illuminate\Support\ServiceProvider;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Core\Client;
use LaravelNats\Laravel\Console\Commands\NatsConsumeCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerCreateCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerDeleteCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerListCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumeStreamCommand;
use LaravelNats\Laravel\Console\Commands\NatsJetStreamStatusCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamCreateCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamDeleteCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamListCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamPurgeCommand;
use LaravelNats\Laravel\Console\Commands\NatsStreamUpdateCommand;
use LaravelNats\Laravel\Console\Commands\NatsV2JetStreamInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsV2JetStreamProvisionCommand;
use LaravelNats\Laravel\Console\Commands\NatsV2JetStreamPullCommand;
use LaravelNats\Laravel\Console\Commands\NatsV2JetStreamStreamsCommand;
use LaravelNats\Laravel\Console\Commands\NatsV2ListenCommand;
use LaravelNats\Laravel\Console\Commands\NatsWorkCommand;
use LaravelNats\JetStream\BasisJetStreamPublisher;
use LaravelNats\JetStream\BasisStreamProvisioner;
use LaravelNats\JetStream\PullConsumerBatch;
use LaravelNats\Laravel\NatsManager;
use LaravelNats\Laravel\NatsV2Gateway;
use LaravelNats\Laravel\Queue\NatsConnector;
use LaravelNats\Publisher\Contracts\NatsPublisherContract;
use LaravelNats\Publisher\NatsPublisher;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\NatsBasisSubscriber;
use LaravelNats\Subscriber\SubjectValidator;

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

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/nats_basis.php',
            'nats_basis',
        );

        $this->registerManager();
        $this->registerBasisNatsV2();
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
            'nats.v2',
            NatsManager::class,
            NatsV2Gateway::class,
            ConnectionManager::class,
            NatsPublisher::class,
            NatsPublisherContract::class,
            NatsSubscriberContract::class,
            NatsBasisSubscriber::class,
            SubjectValidator::class,
            BasisJetStreamPublisher::class,
            PullConsumerBatch::class,
            BasisStreamProvisioner::class,
            Client::class,
        ];
    }

    /**
     * Register Artisan commands (nats:work and JetStream stream/consumer commands).
     */
    protected function registerJetStreamCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Alias Worker::class to queue.worker so NatsWorkCommand::handle(Worker $worker, ...) can
        // resolve. QueueServiceProvider (deferred) binds queue.worker when it is first resolved.
        $this->app->alias('queue.worker', Worker::class);

        $this->commands([
            NatsWorkCommand::class,
            NatsV2ListenCommand::class,
            NatsV2JetStreamInfoCommand::class,
            NatsV2JetStreamStreamsCommand::class,
            NatsV2JetStreamPullCommand::class,
            NatsV2JetStreamProvisionCommand::class,
            NatsConsumeCommand::class,
            NatsConsumeStreamCommand::class,
            NatsStreamListCommand::class,
            NatsStreamInfoCommand::class,
            NatsStreamCreateCommand::class,
            NatsStreamDeleteCommand::class,
            NatsStreamPurgeCommand::class,
            NatsStreamUpdateCommand::class,
            NatsConsumerListCommand::class,
            NatsConsumerInfoCommand::class,
            NatsConsumerCreateCommand::class,
            NatsConsumerDeleteCommand::class,
            NatsJetStreamStatusCommand::class,
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
     * Register basis-company/nats stack (v2.0): ConnectionManager, publisher, NatsV2 gateway.
     */
    protected function registerBasisNatsV2(): void
    {
        $this->app->singleton(ConnectionManager::class, function ($app) {
            $config = $app->make('config');
            $logger = null;
            if ($config->get('nats_basis.logging.enabled', false) === true) {
                $channel = $config->get('nats_basis.logging.channel', 'stack');
                $channel = is_string($channel) && $channel !== '' ? $channel : 'stack';
                /** @var LogManager $log */
                $log = $app->make('log');
                $logger = $log->channel($channel);
            }

            return new ConnectionManager($config, $logger);
        });

        $this->app->singleton(NatsPublisher::class, function ($app) {
            return new NatsPublisher(
                $app->make(ConnectionManager::class),
                $app->make('config'),
            );
        });

        $this->app->bind(NatsPublisherContract::class, NatsPublisher::class);

        $this->app->singleton(SubjectValidator::class, function ($app) {
            return new SubjectValidator($app->make('config'));
        });

        $this->app->singleton(NatsBasisSubscriber::class, function ($app) {
            return new NatsBasisSubscriber(
                $app->make(ConnectionManager::class),
                $app->make('config'),
                $app->make(SubjectValidator::class),
                $app,
                $app->bound('events') ? $app->make('events') : null,
            );
        });

        $this->app->bind(NatsSubscriberContract::class, NatsBasisSubscriber::class);

        $this->app->singleton(BasisJetStreamPublisher::class, function ($app) {
            return new BasisJetStreamPublisher(
                $app->make(ConnectionManager::class),
                $app->make('config'),
            );
        });

        $this->app->singleton(PullConsumerBatch::class, function ($app) {
            return new PullConsumerBatch($app->make(ConnectionManager::class));
        });

        $this->app->singleton(BasisStreamProvisioner::class, function ($app) {
            return new BasisStreamProvisioner(
                $app->make(ConnectionManager::class),
                $app->make('config'),
            );
        });

        $this->app->singleton('nats.v2', function ($app) {
            return new NatsV2Gateway(
                $app->make(ConnectionManager::class),
                $app->make(NatsPublisherContract::class),
                $app->make(NatsSubscriberContract::class),
                $app->make(BasisJetStreamPublisher::class),
                $app->make(PullConsumerBatch::class),
                $app->make(BasisStreamProvisioner::class),
                $app->make('config'),
            );
        });

        $this->app->alias('nats.v2', NatsV2Gateway::class);
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
                __DIR__ . '/../Config/nats_basis.php' => config_path('nats_basis.php'),
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
