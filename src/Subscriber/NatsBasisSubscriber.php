<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber;

use Basis\Nats\Message\Payload;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Laravel\Events\NatsInboundMessageReceived;
use LaravelNats\Security\SubjectAclChecker;
use LaravelNats\Subscriber\Contracts\NatsSubscriberContract;
use LaravelNats\Subscriber\Exceptions\SubscriptionConflictException;
use LaravelNats\Subscriber\Exceptions\SubscriptionNotFoundException;
use LaravelNats\Subscriber\Middleware\InboundMiddleware;
use LaravelNats\Subscriber\Middleware\InboundMiddlewarePipeline;

final class NatsBasisSubscriber implements NatsSubscriberContract
{
    /**
     * @var array<string, array{subject: string, connection: string, queueGroup: ?string}>
     */
    private array $registry = [];

    /** @var array<string, string> */
    private array $keyToId = [];

    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly Repository $config,
        private readonly SubjectValidator $subjects,
        private readonly Container $container,
        private readonly ?Dispatcher $events = null,
    ) {
    }

    public function subscribe(string $subject, callable $handler, ?string $queueGroup = null, ?string $connection = null): string
    {
        $this->subjects->validate($subject);

        $connName = $connection ?? $this->connections->getDefaultConnection();
        $key = $this->subscriptionKey($connName, $subject, $queueGroup);

        if (isset($this->keyToId[$key])) {
            throw SubscriptionConflictException::duplicate($subject, $queueGroup, $connName);
        }

        $id = $this->newSubscriptionId();
        $client = $this->connections->connection($connection);

        $pipeline = new InboundMiddlewarePipeline($this->resolveMiddleware());

        $wrapped = function (mixed $payload, mixed $replyTo) use ($handler, $pipeline): void {
            if (! $payload instanceof Payload) {
                return;
            }

            $reply = is_string($replyTo) || $replyTo === null ? $replyTo : null;
            $inbound = InboundMessage::fromPayload($payload, $reply);

            $terminal = function () use ($handler, $inbound): void {
                $this->maybeDispatchEvent($inbound);
                $handler($inbound);
            };

            $pipeline->dispatch($inbound, $terminal);
        };

        if ($queueGroup !== null && $queueGroup !== '') {
            $client->subscribeQueue($subject, $queueGroup, $wrapped);
        } else {
            $client->subscribe($subject, $wrapped);
        }

        $this->registry[$id] = [
            'subject' => $subject,
            'connection' => $connName,
            'queueGroup' => $queueGroup,
        ];
        $this->keyToId[$key] = $id;

        return $id;
    }

    public function unsubscribe(string $subscriptionId): void
    {
        if (! isset($this->registry[$subscriptionId])) {
            throw SubscriptionNotFoundException::forId($subscriptionId);
        }

        $row = $this->registry[$subscriptionId];
        $client = $this->connections->connection($row['connection']);
        $client->unsubscribe($row['subject']);

        $key = $this->subscriptionKey($row['connection'], $row['subject'], $row['queueGroup']);
        unset($this->registry[$subscriptionId], $this->keyToId[$key]);
    }

    public function unsubscribeAll(?string $connection = null): void
    {
        $ids = array_keys($this->registry);

        foreach ($ids as $id) {
            if (! isset($this->registry[$id])) {
                continue;
            }

            if ($connection !== null && $this->registry[$id]['connection'] !== $connection) {
                continue;
            }

            $this->unsubscribe($id);
        }
    }

    public function process(?string $connection = null, int|float|null $timeout = 0): mixed
    {
        return $this->connections->connection($connection)->process($timeout);
    }

    private function subscriptionKey(string $connection, string $subject, ?string $queueGroup): string
    {
        return $connection . "\0" . $subject . "\0" . ($queueGroup ?? '');
    }

    private function newSubscriptionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return list<InboundMiddleware>
     */
    private function resolveMiddleware(): array
    {
        /** @var mixed $raw */
        $raw = $this->config->get('nats_basis.subscriber.middleware', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $class) {
            if (! is_string($class) || $class === '') {
                continue;
            }

            if (! is_subclass_of($class, InboundMiddleware::class)) {
                continue;
            }

            $out[] = $this->container->make($class);
        }

        return $out;
    }

    private function maybeDispatchEvent(InboundMessage $inbound): void
    {
        if ($this->events === null) {
            return;
        }

        if ($this->config->get('nats_basis.subscriber.dispatch_events', false) !== true) {
            return;
        }

        $this->events->dispatch(new NatsInboundMessageReceived($inbound));
    }
}
