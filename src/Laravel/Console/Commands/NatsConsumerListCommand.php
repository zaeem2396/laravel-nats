<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * List JetStream consumers for a stream.
 */
class NatsConsumerListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:consumer:list
                            {stream : Stream name}
                            {--connection= : NATS connection name}
                            {--offset=0 : Pagination offset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List JetStream consumers for a stream';

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
        $offset = (int) $this->option('offset');

        if ($streamName === '') {
            $this->error('Stream name is required.');

            return self::FAILURE;
        }

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $result = $js->listConsumers($streamName, $offset);

            if ($result['consumers'] === []) {
                $this->info(sprintf('No consumers found for stream "%s".', $streamName));

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($result['consumers'] as $consumer) {
                $rows[] = [$consumer->getName(), (string) $consumer->getNumPending(), (string) $consumer->getNumAckPending()];
            }

            $this->table(['Consumer', 'Pending', 'Ack pending'], $rows);
            $this->line(sprintf('Total: %d', $result['total']));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
