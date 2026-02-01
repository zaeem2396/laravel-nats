<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * Delete a JetStream stream.
 */
class NatsStreamDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:stream:delete
                            {stream : Stream name}
                            {--connection= : NATS connection name}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a JetStream stream';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $streamArg = $this->argument('stream');
        $streamName = is_string($streamArg) ? $streamArg : '';
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;

        if ($streamName === '') {
            $this->error('Stream name is required.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete stream "%s"?', $streamName))) {
            return self::SUCCESS;
        }

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $js->deleteStream($streamName);
            $this->info(sprintf('Stream "%s" deleted.', $streamName));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
