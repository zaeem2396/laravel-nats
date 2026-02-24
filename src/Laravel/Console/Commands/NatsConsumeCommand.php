<?php

declare(strict_types=1);

namespace LaravelNats\Laravel\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Contracts\Messaging\MessageHandlerInterface;
use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Laravel\NatsManager;

/**
 * Subject-based NATS consumer command (Phase 4.2 — Subject-Based Consumer).
 *
 * Subscribes to one or more subjects (with optional queue group) and
 * dispatches each message to a handler class or prints to console.
 * Supports wildcards: * (single token) and > (one or more tokens).
 */
class NatsConsumeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nats:consume
                            {subject : Subject pattern to consume (e.g. orders.* or events.>)}
                            {--connection= : NATS connection name}
                            {--queue= : Queue group name for load-balanced consumption}
                            {--handler= : Handler class implementing MessageHandlerInterface}
                            {--subjects= : Comma-separated additional subjects to consume}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from NATS subject(s) with optional handler and queue group (Phase 4.2 — Subject-Based Consumer)';

    /**
     * Whether the consumer should stop (e.g. on SIGTERM).
     *
     * @var bool
     */
    protected bool $shouldQuit = false;

    public function __construct(
        protected NatsManager $nats,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $subject = is_string($this->argument('subject')) ? trim($this->argument('subject')) : '';
        if ($subject === '') {
            $this->error('Subject is required.');

            return self::FAILURE;
        }

        $connectionName = $this->option('connection');
        $connName = is_string($connectionName) ? $connectionName : null;
        $queue = $this->option('queue');
        $queueGroup = is_string($queue) && $queue !== '' ? $queue : null;
        $handlerClass = $this->option('handler');
        $handlerClassName = is_string($handlerClass) && $handlerClass !== '' ? $handlerClass : null;
        $subjectsOption = $this->option('subjects');
        $additionalSubjects = is_string($subjectsOption) && $subjectsOption !== ''
            ? array_map('trim', explode(',', $subjectsOption))
            : [];

        $subjects = array_merge([$subject], $additionalSubjects);
        $subjects = array_values(array_filter($subjects, static fn (string $s): bool => $s !== ''));

        if ($subjects === []) {
            $this->error('At least one subject is required.');

            return self::FAILURE;
        }

        $client = $this->nats->connection($connName);

        if (! $client->isConnected()) {
            $client->connect();
        }

        $this->registerSignalHandlers();

        $handler = $handlerClassName !== null ? $this->resolveHandler($handlerClassName) : null;

        $callback = function (MessageInterface $message) use ($handler): void {
            if ($handler !== null) {
                $handler->handle($message);
            } else {
                $this->line(sprintf('[%s] %s', $message->getSubject(), $message->getPayload()));
            }
        };

        $sids = [];
        foreach ($subjects as $sub) {
            if ($queueGroup !== null) {
                $sids[] = $client->queueSubscribe($sub, $queueGroup, $callback);
            } else {
                $sids[] = $client->subscribe($sub, $callback);
            }
        }

        $this->info(sprintf(
            'Consuming %s (queue group: %s). Ctrl+C to stop.',
            implode(', ', $subjects),
            $queueGroup ?? 'none',
        ));

        while (! $this->shouldQuit) {
            $client->process(1.0);
        }

        foreach ($sids as $sid) {
            try {
                $client->unsubscribe($sid);
            } catch (\Throwable) {
                // ignore on shutdown
            }
        }

        return self::SUCCESS;
    }

    /**
     * Resolve handler from container (supports dependency injection).
     *
     * @param string $class Handler class name (must implement MessageHandlerInterface)
     *
     * @return MessageHandlerInterface
     *
     * @throws \InvalidArgumentException If the class does not implement the interface
     */
    protected function resolveHandler(string $class): MessageHandlerInterface
    {
        $instance = $this->laravel->make($class);

        if (! $instance instanceof MessageHandlerInterface) {
            throw new \InvalidArgumentException(sprintf('Handler %s must implement %s.', $class, MessageHandlerInterface::class));
        }

        return $instance;
    }

    /**
     * Register signal handlers for graceful shutdown (SIGTERM, SIGINT).
     * No-op when pcntl extension is not available.
     */
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
