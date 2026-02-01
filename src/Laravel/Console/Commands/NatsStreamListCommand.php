<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;

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
    public function handle(): int
    {
        return self::SUCCESS;
    }
}
