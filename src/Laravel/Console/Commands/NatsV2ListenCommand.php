<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Long-running listener for the v2 subscriber stack (basis-company/nats via {@see NatsV2}).
 */
final class NatsV2ListenCommand extends Command
{
    protected $signature = 'nats:v2:listen
                            {subject : NATS subject (wildcards * and > allowed)}
                            {--queue= : Optional queue group for load-balanced consumers}
                            {--connection= : NATS basis connection name}
                            {--timeout=1 : Blocking timeout (seconds) passed to each process() iteration}';

    protected $description = 'Listen for NATS messages on a subject using the v2 subscriber (basis-company/nats wrapper)';

    private bool $shouldQuit = false;

    public function handle(): int
    {
        $subject = is_string($this->argument('subject')) ? trim($this->argument('subject')) : '';
        if ($subject === '') {
            $this->error('Subject is required.');

            return self::FAILURE;
        }

        $queue = $this->option('queue');
        $queueGroup = is_string($queue) && $queue !== '' ? $queue : null;

        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;

        $timeout = (float) $this->option('timeout');
        if ($timeout < 0) {
            $timeout = 1.0;
        }

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->shouldQuit = true;
            });
            pcntl_signal(SIGTERM, function (): void {
                $this->shouldQuit = true;
            });
        }

        $this->info("Listening on [{$subject}] (v2 subscriber). Ctrl+C to stop.");

        $sid = NatsV2::subscribe($subject, function (InboundMessage $message): void {
            $this->line($message->body);
        }, $queueGroup, $conn);

        while (! $this->shouldQuit) {
            NatsV2::process($conn, $timeout);
        }

        NatsV2::unsubscribe($sid);

        return self::SUCCESS;
    }
}
