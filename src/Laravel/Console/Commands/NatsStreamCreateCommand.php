<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Core\JetStream\StreamConfig;
use LaravelNats\Laravel\NatsManager;

/**
 * Create a JetStream stream.
 */
class NatsStreamCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:stream:create
                            {name : Stream name}
                            {subjects* : Subject patterns (e.g. events.>)}
                            {--connection= : NATS connection name}
                            {--description= : Stream description}
                            {--storage=file : Storage type (file or memory)}
                            {--retention=limits : Retention policy (limits, interest, workqueue)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a JetStream stream';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $nameArg = $this->argument('name');
        $streamName = is_string($nameArg) ? $nameArg : '';
        $subjectsArg = $this->argument('subjects');
        $subjects = is_array($subjectsArg) ? array_map('strval', $subjectsArg) : [];
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;
        $description = $this->option('description');
        $storage = is_string($this->option('storage')) ? $this->option('storage') : 'file';
        $retention = is_string($this->option('retention')) ? $this->option('retention') : 'limits';

        if ($streamName === '') {
            $this->error('Stream name is required.');

            return self::FAILURE;
        }

        if ($subjects === []) {
            $this->error('At least one subject pattern is required.');

            return self::FAILURE;
        }

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $config = (new StreamConfig($streamName, $subjects))
                ->withStorage($storage === 'memory' ? StreamConfig::STORAGE_MEMORY : StreamConfig::STORAGE_FILE)
                ->withRetention($retention);

            if (is_string($description) && $description !== '') {
                $config = $config->withDescription($description);
            }

            $js->createStream($config);
            $this->info(sprintf('Stream "%s" created successfully.', $streamName));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
