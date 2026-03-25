<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Creates a stream from a named preset in config/nats_basis.php (jetstream.presets).
 *
 * @see \LaravelNats\JetStream\BasisStreamProvisioner
 * @see docs/v2/JETSTREAM.md
 */
final class NatsV2JetStreamProvisionCommand extends Command
{
    protected $signature = 'nats:v2:jetstream:provision
                            {preset : Preset key under nats_basis.jetstream.presets}
                            {--connection= : NATS basis connection name}
                            {--force : Call create() even if stream may already exist}';

    protected $description = 'Provision a JetStream stream from a nats_basis.jetstream.presets entry';

    public function handle(): int
    {
        $preset = is_string($this->argument('preset')) ? trim($this->argument('preset')) : '';
        if ($preset === '') {
            $this->error('Preset key is required.');

            return self::FAILURE;
        }

        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;

        $createIfNotExists = ! (bool) $this->option('force');

        try {
            $stream = NatsV2::jetStreamProvisionPreset($preset, $createIfNotExists, $conn);
            $this->info('Stream ['.$stream->getName().'] provisioned.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
