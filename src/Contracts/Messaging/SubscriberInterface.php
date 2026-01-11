<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Messaging;

/**
 * SubscriberInterface defines the contract for subscribing to NATS subjects.
 *
 * Subscribers receive messages published to matching subjects. NATS supports:
 * - Exact match subscriptions: "orders.created"
 * - Wildcard subscriptions: "orders.*" (single token) or "orders.>" (multiple tokens)
 * - Queue groups: Load-balanced subscriptions where only one subscriber gets each message
 */
interface SubscriberInterface
{
    /**
     * Subscribe to a subject.
     *
     * The callback is invoked for each message received on the subject.
     * The subscription remains active until unsubscribed.
     *
     * @param string $subject The subject pattern to subscribe to
     * @param callable(MessageInterface): void $callback Handler for received messages
     *
     * @throws \LaravelNats\Exceptions\SubscriptionException When subscription fails
     *
     * @return string The subscription ID (SID)
     */
    public function subscribe(string $subject, callable $callback): string;

    /**
     * Subscribe to a subject with a queue group.
     *
     * Queue groups enable load balancing - only one subscriber in the group
     * receives each message. This is useful for horizontal scaling.
     *
     * @param string $subject The subject pattern to subscribe to
     * @param string $queue The queue group name
     * @param callable(MessageInterface): void $callback Handler for received messages
     *
     * @throws \LaravelNats\Exceptions\SubscriptionException When subscription fails
     *
     * @return string The subscription ID (SID)
     */
    public function queueSubscribe(string $subject, string $queue, callable $callback): string;

    /**
     * Unsubscribe from a subscription.
     *
     * @param string $sid The subscription ID to unsubscribe
     * @param int|null $maxMessages Optional: auto-unsubscribe after receiving N messages
     *
     * @throws \LaravelNats\Exceptions\SubscriptionException When unsubscription fails
     */
    public function unsubscribe(string $sid, ?int $maxMessages = null): void;

    /**
     * Process incoming messages.
     *
     * This method reads from the connection and dispatches messages
     * to their registered callbacks. It should be called in a loop
     * for continuous message processing.
     *
     * @param float $timeout Maximum seconds to wait for messages
     *
     * @return int Number of messages processed
     */
    public function process(float $timeout = 0.0): int;

    /**
     * Get all active subscriptions.
     *
     * @return array<string, array{subject: string, queue: string|null, callback: callable}> Active subscriptions indexed by SID
     */
    public function getSubscriptions(): array;

    /**
     * Check if a subscription exists.
     *
     * @param string $sid The subscription ID
     *
     * @return bool True if subscription exists
     */
    public function hasSubscription(string $sid): bool;

    /**
     * Get the count of active subscriptions.
     *
     * @return int Number of active subscriptions
     */
    public function getSubscriptionCount(): int;
}
