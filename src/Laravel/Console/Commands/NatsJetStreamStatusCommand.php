<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\NatsManager;

/**
 * Show JetStream account status and usage.
 */
class NatsJetStreamStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:jetstream:status
                            {--connection= : NATS connection name}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show JetStream account status and usage';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;

        try {
            $js = $nats->jetstream($connectionName);

            if (! $js->isAvailable()) {
                $this->error('JetStream is not available on this server.');

                return self::FAILURE;
            }

            $info = $js->getAccountInfo();

            if ($this->option('json')) {
                $encoded = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $this->line($encoded !== false ? $encoded : '{}');

                return self::SUCCESS;
            }

            $this->table(
                ['Key', 'Value'],
                $this->formatInfoRows($info),
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Format account info array as table rows.
     *
     * @param array<string, mixed> $info
     *
     * @return list<array{string, string}>
     */
    private function formatInfoRows(array $info): array
    {
        $rows = [];

        foreach ($info as $key => $value) {
            if (is_array($value)) {
                $encoded = json_encode($value);
                $rows[] = [$key, $encoded !== false ? $encoded : '[]'];
            } else {
                $rows[] = [$key, (string) $value];
            }
        }

        return $rows;
    }
}
