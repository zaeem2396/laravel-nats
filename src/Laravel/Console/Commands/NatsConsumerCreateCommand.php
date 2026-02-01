<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Laravel\NatsManager;

/**
 * Create a JetStream durable consumer.
 */
class NatsConsumerCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:consumer:create
                            {stream : Stream name}
                            {name : Consumer (durable) name}
                            {--connection= : NATS connection name}
                            {--filter-subject= : Filter subject (e.g. events.>)}
                            {--deliver-policy=all : Deliver policy (all, last, new, etc.)}
                            {--ack-policy=explicit : Ack policy (none, all, explicit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a JetStream durable consumer';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(NatsManager $nats): int
    {
        $streamArg = $this->argument('stream');
        $streamName = is_string($streamArg) ? $streamArg : '';
        $nameArg = $this->argument('name');
        $consumerName = is_string($nameArg) ? $nameArg : '';
        $connection = $this->option('connection');
        $connectionName = is_string($connection) ? $connection : null;
        $filterSubject = $this->option('filter-subject');
        $deliverPolicy = is_string($this->option('deliver-policy')) ? $this->option('deliver-policy') : ConsumerConfig::DELIVER_ALL;
        $ackPolicy = is_string($this->option('ack-policy')) ? $this->option('ack-policy') : ConsumerConfig::ACK_EXPLICIT;

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

            $config = (new ConsumerConfig($consumerName))
                ->withDeliverPolicy($deliverPolicy)
                ->withAckPolicy($ackPolicy);

            if (is_string($filterSubject) && $filterSubject !== '') {
                $config = $config->withFilterSubject($filterSubject);
            }

            $js->createConsumer($streamName, $consumerName, $config);
            $this->info(sprintf('Consumer "%s" created on stream "%s".', $consumerName, $streamName));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
