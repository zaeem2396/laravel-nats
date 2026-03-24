<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Contracts;

use LaravelNats\Subscriber\InboundMessage;

interface NatsSubscriberContract
{
    /**
     * Subscribe to a subject. Handler receives {@see InboundMessage}.
     *
     * One active subscription per (connection, subject, queue group) per process; duplicate subscribe throws.
     *
     * @param callable(InboundMessage): void $handler
     */
    public function subscribe(string $subject, callable $handler, ?string $queueGroup = null, ?string $connection = null): string;

    /**
     * Unsubscribe by id returned from {@see subscribe()}.
     */
    public function unsubscribe(string $subscriptionId): void;

    /**
     * Unsubscribe all tracked subscriptions, optionally scoped to a connection name.
     */
    public function unsubscribeAll(?string $connection = null): void;

    /**
     * Dispatch one iteration of the basis client message loop (blocking up to $timeout seconds).
     *
     * @return mixed Value from {@see \Basis\Nats\Client::process()}
     */
    public function process(?string $connection = null, int|float|null $timeout = 0): mixed;
}
