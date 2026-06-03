<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelNats\Laravel\Console\Commands\NatsConsumeCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerCreateCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerDeleteCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerInfoCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumerListCommand;
use LaravelNats\Laravel\Console\Commands\NatsConsumeStreamCommand;
use LaravelNats\Laravel\Console\Commands\NatsJetStreamStatusCommand;
use LaravelNats\Laravel\Console\Commands\NatsPingCommand;
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
use LaravelNats\Laravel\Console\Commands\NatsValidateConfigCommand;
use LaravelNats\Laravel\Console\Commands\NatsWorkCommand;

/**
 * @return array<string, class-string>
 */
function artisanCommandMap(): array
{
    return [
        'nats:consume' => NatsConsumeCommand::class,
        'nats:consume:stream' => NatsConsumeStreamCommand::class,
        'nats:work' => NatsWorkCommand::class,
        'nats:ping' => NatsPingCommand::class,
        'nats:v2:config:validate' => NatsValidateConfigCommand::class,
        'nats:stream:create' => NatsStreamCreateCommand::class,
        'nats:stream:delete' => NatsStreamDeleteCommand::class,
        'nats:stream:list' => NatsStreamListCommand::class,
        'nats:stream:info' => NatsStreamInfoCommand::class,
        'nats:stream:purge' => NatsStreamPurgeCommand::class,
        'nats:stream:update' => NatsStreamUpdateCommand::class,
        'nats:consumer:create' => NatsConsumerCreateCommand::class,
        'nats:consumer:delete' => NatsConsumerDeleteCommand::class,
        'nats:consumer:list' => NatsConsumerListCommand::class,
        'nats:consumer:info' => NatsConsumerInfoCommand::class,
        'nats:jetstream:status' => NatsJetStreamStatusCommand::class,
        'nats:v2:listen' => NatsV2ListenCommand::class,
        'nats:v2:jetstream:info' => NatsV2JetStreamInfoCommand::class,
        'nats:v2:jetstream:streams' => NatsV2JetStreamStreamsCommand::class,
        'nats:v2:jetstream:pull' => NatsV2JetStreamPullCommand::class,
        'nats:v2:jetstream:provision' => NatsV2JetStreamProvisionCommand::class,
    ];
}

describe('Artisan command registration', function (): void {
    foreach (artisanCommandMap() as $name => $class) {
        it("registers {$name} command", function () use ($name, $class): void {
            $commands = Artisan::all();

            expect(array_key_exists($name, $commands))->toBeTrue()
                ->and($commands[$name])->toBeInstanceOf($class);
        });
    }
});
