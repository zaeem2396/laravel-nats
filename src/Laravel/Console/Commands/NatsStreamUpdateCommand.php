<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\NatsManager;

/**
 * Update a JetStream stream's configuration.
 */
class NatsStreamUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:stream:update
                            {stream : Stream name}
                            {--connection= : NATS connection name}
                            {--description= : Stream description}
                            {--storage= : Storage type (file or memory)}
                            {--retention= : Retention policy (limits, interest, workqueue)}
                            {--max-messages= : Maximum number of messages to keep}
                            {--max-bytes= : Maximum bytes to store}
                            {--max-age= : Maximum age of messages in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a JetStream stream configuration';

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

            $description = $this->option('description');
            if (is_string($description)) {
                $config = $config->withDescription($description === '' ? null : $description);
            }

            $storage = $this->option('storage');
            if (is_string($storage) && $storage !== '') {
                $config = $config->withStorage(
                    $storage === 'memory' ? StreamConfig::STORAGE_MEMORY : StreamConfig::STORAGE_FILE,
                );
            }

            $retention = $this->option('retention');
            if (is_string($retention) && $retention !== '') {
                $config = $config->withRetention($retention);
            }

            $maxMessages = $this->option('max-messages');
            if (is_string($maxMessages) && $maxMessages !== '') {
                $config = $config->withMaxMessages((int) $maxMessages);
            }

            $maxBytes = $this->option('max-bytes');
            if (is_string($maxBytes) && $maxBytes !== '') {
                $config = $config->withMaxBytes((int) $maxBytes);
            }

            $maxAge = $this->option('max-age');
            if (is_string($maxAge) && $maxAge !== '') {
                $config = $config->withMaxAge((int) $maxAge);
            }

            $js->updateStream($config);
            $this->info(sprintf('Stream "%s" updated.', $streamName));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
