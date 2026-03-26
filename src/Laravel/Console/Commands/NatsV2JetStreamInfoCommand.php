<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * JetStream account summary via basis client ($JS.API.INFO).
 *
 * @see \LaravelNats\Laravel\NatsV2Gateway::jetstream()
 * @see docs/v2/JETSTREAM.md
 */
final class NatsV2JetStreamInfoCommand extends Command
{
    protected $signature = 'nats:v2:jetstream:info {--connection= : NATS basis connection name}';

    protected $description = 'Show JetStream account info (NatsV2 / basis-company/nats)';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;

        try {
            $info = NatsV2::jetstream($conn)->accountInfo($conn);
            $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
