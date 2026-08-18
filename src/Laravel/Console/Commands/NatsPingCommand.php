<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Support\MixedTypes;

/**
 * TCP-level NATS PING/PONG against the basis client (readiness / liveness helper).
 *
 * @see \Basis\Nats\Client::ping()
 */
final class NatsPingCommand extends Command
{
    protected $signature = 'nats:ping {--connection= : NATS basis connection name} {--json : Emit JSON for scripts}';

    protected $description = 'Ping NATS server (NatsV2 / basis-company/nats)';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;
        $asJson = (bool) $this->option('json');
        $cfg = $this->laravel->make('config');
        $defaultConn = 'default';
        if ($cfg instanceof Repository) {
            $defaultConn = MixedTypes::string($cfg->get('nats_basis.default', 'default'), 'default');
        }

        try {
            $ok = NatsV2::ping($conn);
            if ($asJson) {
                $this->line(json_encode([
                    'ok' => $ok,
                    'connection' => $conn ?? $defaultConn,
                ], JSON_THROW_ON_ERROR));
            } elseif ($ok) {
                $this->info('NATS ping succeeded.');
            } else {
                $this->error('NATS ping failed (no pong in time).');
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            if ($asJson) {
                $this->line(json_encode([
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'connection' => $conn ?? $defaultConn,
                ], JSON_THROW_ON_ERROR));
            } else {
                $this->error($e->getMessage());
            }

            return self::FAILURE;
        }
    }
}
