<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Contracts\JetStream\JetStreamMessageHandlerInterface;
use LaravelNats\Core\JetStream\ConsumerConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\JetStream\JetStreamConsumedMessage;
use LaravelNats\Laravel\NatsManager;

/**
 * JetStream pull consumer worker command (Phase 4.3 — JetStream Consumer Worker).
 *
 * Consumes messages from a JetStream stream via a durable pull consumer.
 * Supports optional handler class, batch size, timeout, no-wait, and auto-create consumer.
 */
class NatsConsumeStreamCommand extends Command
{
    protected $signature = 'nats:consume:stream
                            {stream : JetStream stream name}
                            {--connection= : NATS connection name}
                            {--consumer= : Durable consumer name (required unless --auto-create)}
                            {--handler= : Handler class implementing JetStreamMessageHandlerInterface}
                            {--batch=1 : Max messages to fetch per cycle (batch size)}
                            {--timeout=5 : Fetch timeout in seconds}
                            {--no-wait : Do not wait when no message available}
                            {--auto-create : Create durable consumer if it does not exist}';

    protected $description = 'Consume messages from a JetStream stream via pull consumer (Phase 4.3 — JetStream Consumer Worker)';

    protected bool $shouldQuit = false;

    public function __construct(
        protected NatsManager $nats,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $stream = is_string($this->argument('stream')) ? trim($this->argument('stream')) : '';
        if ($stream === '') {
            $this->error('Stream is required.');

            return self::FAILURE;
        }

        $connectionName = $this->option('connection');
        $connName = is_string($connectionName) && $connectionName !== '' ? $connectionName : null;
        $consumerOption = $this->option('consumer');
        $consumerName = is_string($consumerOption) && $consumerOption !== '' ? $consumerOption : null;
        $autoCreate = (bool) $this->option('auto-create');

        if ($consumerName === null) {
            if (! $autoCreate) {
                $this->error('Consumer name is required (--consumer=) or use --auto-create.');

                return self::FAILURE;
            }
            $consumerName = 'consume-stream-' . $stream . '-' . uniqid('', true);
        }

        $handlerClass = $this->option('handler');
        $handlerClassName = is_string($handlerClass) && $handlerClass !== '' ? $handlerClass : null;
        $batch = (int) $this->option('batch');
        $batch = $batch < 1 ? 1 : $batch;
        $timeout = (float) $this->option('timeout');
        $noWait = (bool) $this->option('no-wait');

        $js = $this->nats->jetstream($connName);
        if (! $js->isAvailable()) {
            $this->error('JetStream is not available on this connection.');

            return self::FAILURE;
        }

        if ($autoCreate) {
            $this->ensureConsumerExists($js, $stream, $consumerName);
        }

        $handler = $handlerClassName !== null ? $this->resolveHandler($handlerClassName) : null;
        $this->registerSignalHandlers();

        $this->info(sprintf(
            'Consuming stream "%s" consumer "%s" (batch=%d). Ctrl+C to stop.',
            $stream,
            $consumerName,
            $batch,
        ));

        while (! $this->shouldQuit) {
            $fetched = 0;
            for ($i = 0; $i < $batch && ! $this->shouldQuit; $i++) {
                $message = $js->fetchNextMessage($stream, $consumerName, $timeout, $noWait || $i > 0, $batch);
                if ($message === null) {
                    break;
                }
                $this->processMessage($message, $handler, $js);
                $fetched++;
            }
            if ($fetched === 0 && $noWait) {
                usleep(500_000); // 0.5s when no-wait to avoid busy loop
            }
        }

        return self::SUCCESS;
    }

    protected function processMessage(JetStreamConsumedMessage $message, ?JetStreamMessageHandlerInterface $handler, JetStreamClient $js): void
    {
        try {
            if ($handler !== null) {
                $handler->handle($message);
            } else {
                $this->line(sprintf('[%s/%s] %s', $message->getStreamName(), $message->getConsumerName(), $message->getPayload()));
            }
            $js->ack($message);
        } catch (\Throwable $e) {
            $this->error(sprintf('Handler error: %s', $e->getMessage()));
            $js->nak($message);
        }
    }

    protected function ensureConsumerExists(\LaravelNats\Core\JetStream\JetStreamClient $js, string $streamName, string $consumerName): void
    {
        try {
            $js->getConsumerInfo($streamName, $consumerName);

            return;
        } catch (\Throwable) {
            // Consumer does not exist; create it
        }

        $config = (new ConsumerConfig($consumerName))
            ->withDeliverPolicy(ConsumerConfig::DELIVER_ALL)
            ->withAckPolicy(ConsumerConfig::ACK_EXPLICIT)
            ->withFilterSubject('>');

        $js->createConsumer($streamName, $consumerName, $config);
        $this->line(sprintf('Created durable consumer "%s" on stream "%s".', $consumerName, $streamName));
    }

    protected function resolveHandler(string $class): JetStreamMessageHandlerInterface
    {
        $instance = $this->laravel->make($class);

        if (! $instance instanceof JetStreamMessageHandlerInterface) {
            throw new \InvalidArgumentException(sprintf(
                'Handler %s must implement %s.',
                $class,
                JetStreamMessageHandlerInterface::class,
            ));
        }

        return $instance;
    }

    protected function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->shouldQuit = true;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->shouldQuit = true;
        });
    }
}
