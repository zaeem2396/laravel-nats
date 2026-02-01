<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * Show JetStream stream information.
 */
class NatsStreamInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:stream:info
                            {stream : Stream name}
                            {--connection= : NATS connection name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show JetStream stream information';

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

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $info = $js->getStreamInfo($streamName);
            $config = $info->getConfig();
            $state = $info->getState();

            $this->line('<info>Stream:</info> ' . $config->getName());
            $this->newLine();
            $this->line('<comment>Configuration</comment>');
            $this->table(
                ['Option', 'Value'],
                [
                    ['Subjects', implode(', ', $config->getSubjects())],
                    ['Retention', $config->getRetention()],
                    ['Storage', $config->getStorage()],
                    ['Max messages', (string) ($config->getMaxMessages() ?? '—')],
                    ['Max bytes', (string) ($config->getMaxBytes() ?? '—')],
                    ['Max age (s)', (string) ($config->getMaxAge() ?? '—')],
                ]
            );
            $this->newLine();
            $this->line('<comment>State</comment>');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Messages', (string) $info->getMessageCount()],
                    ['Bytes', (string) $info->getByteCount()],
                    ['First sequence', (string) ($info->getFirstSequence() ?? '—')],
                    ['Last sequence', (string) ($info->getLastSequence() ?? '—')],
                    ['Consumers', (string) $info->getConsumerCount()],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
