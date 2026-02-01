<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * Delete a JetStream consumer.
 */
class NatsConsumerDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:consumer:delete
                            {stream : Stream name}
                            {consumer : Consumer name}
                            {--connection= : NATS connection name}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a JetStream consumer';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $streamArg = $this->argument('stream');
        $streamName = is_string($streamArg) ? $streamArg : '';
        $consumerArg = $this->argument('consumer');
        $consumerName = is_string($consumerArg) ? $consumerArg : '';
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;

        if ($streamName === '' || $consumerName === '') {
            $this->error('Stream name and consumer name are required.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete consumer "%s" on stream "%s"?', $consumerName, $streamName))) {
            return self::SUCCESS;
        }

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $js->deleteConsumer($streamName, $consumerName);
            $this->info(sprintf('Consumer "%s" deleted from stream "%s".', $consumerName, $streamName));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
