<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * One-shot pull consumer fetch (prints message bodies, acks each).
 */
final class NatsV2JetStreamPullCommand extends Command
{
    protected $signature = 'nats:v2:jetstream:pull
                            {stream : JetStream stream name}
                            {consumer : Durable consumer name}
                            {--connection= : NATS basis connection name}
                            {--batch= : Max messages (defaults from nats_basis.jetstream.pull)}
                            {--expires= : Pull expires seconds (defaults from config)}';

    protected $description = 'Pull up to N messages from a JetStream durable consumer (NatsV2)';

    public function handle(): int
    {
        $stream = is_string($this->argument('stream')) ? trim($this->argument('stream')) : '';
        $consumer = is_string($this->argument('consumer')) ? trim($this->argument('consumer')) : '';
        if ($stream === '' || $consumer === '') {
            $this->error('Stream and consumer are required.');

            return self::FAILURE;
        }

        $connection = $this->option('connection');
        $conn = is_string($connection) && $connection !== '' ? $connection : null;

        $batchOpt = $this->option('batch');
        $batch = is_numeric($batchOpt) ? (int) $batchOpt : null;

        $expOpt = $this->option('expires');
        $expires = is_numeric($expOpt) ? (float) $expOpt : null;

        try {
            $messages = NatsV2::jetStreamPull($stream, $consumer, $batch, $expires, $conn);
            foreach ($messages as $msg) {
                if ($msg->payload->isEmpty()) {
                    continue;
                }
                $this->line($msg->payload->body);
                $msg->ack();
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
