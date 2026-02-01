<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * List JetStream streams.
 */
class NatsStreamListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:stream:list
                            {--connection= : NATS connection name}
                            {--offset=0 : Pagination offset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List JetStream streams';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;
        $offset = (int) $this->option('offset');

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $result = $js->listStreams($offset);

            if ($result['streams'] === []) {
                $this->info('No streams found.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($result['streams'] as $name) {
                $rows[] = [$name];
            }

            $this->table(['Stream'], $rows);
            $this->line(sprintf('Total: %d', $result['total']));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
