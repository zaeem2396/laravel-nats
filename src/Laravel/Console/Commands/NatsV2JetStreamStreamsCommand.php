<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Lists JetStream stream names via basis Api::getStreamNames().
 *
 * @see docs/v2/JETSTREAM.md
 */
final class NatsV2JetStreamStreamsCommand extends Command
{
    protected $signature = 'nats:v2:jetstream:streams {--connection= : NATS basis connection name}';

    protected $description = 'List JetStream stream names (NatsV2 / basis-company/nats)';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;

        try {
            $names = NatsV2::jetstream($conn)->streamNames($conn);
            foreach ($names as $name) {
                $this->line($name);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
