<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * Show JetStream consumer information.
 */
class NatsConsumerInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:consumer:info
                            {stream : Stream name}
                            {consumer : Consumer name}
                            {--connection= : NATS connection name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show JetStream consumer information';

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

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $info = $js->getConsumerInfo($streamName, $consumerName);
            $config = $info->getConfig();

            $this->line('<info>Stream:</info> ' . $info->getStreamName());
            $this->line('<info>Consumer:</info> ' . $info->getName());
            $this->newLine();
            $this->line('<comment>Configuration</comment>');
            $this->table(
                ['Option', 'Value'],
                [
                    ['Durable name', (string) ($config->getDurableName() ?? '—')],
                    ['Filter subject', (string) ($config->getFilterSubject() ?? '—')],
                    ['Deliver policy', $config->getDeliverPolicy()],
                    ['Ack policy', $config->getAckPolicy()],
                    ['Ack wait (s)', (string) ($config->getAckWait() ?? '—')],
                    ['Max deliver', (string) ($config->getMaxDeliver() ?? '—')],
                    ['Replay policy', $config->getReplayPolicy()],
                ],
            );
            $this->newLine();
            $this->line('<comment>State</comment>');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Pending', (string) $info->getNumPending()],
                    ['Ack pending', (string) $info->getNumAckPending()],
                    ['Waiting', (string) $info->getNumWaiting()],
                ],
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
