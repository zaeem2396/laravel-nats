<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Messaging;

/**
 * PublisherInterface defines the contract for publishing messages to NATS.
 *
 * Publishers are responsible for sending messages to subjects. NATS uses
 * a fire-and-forget model for basic publishing - there's no acknowledgment
 * that subscribers received the message (use JetStream for guaranteed delivery).
 *
 * Publishing is very fast and doesn't block waiting for subscribers.
 */
interface PublisherInterface
{
    /**
     * Publish a message to a subject.
     *
     * This is a fire-and-forget operation. The message is sent to the
     * NATS server, which will distribute it to all matching subscribers.
     *
     * @param string $subject The subject to publish to
     * @param mixed $payload The message payload (will be serialized)
     * @param array<string, string> $headers Optional message headers
     *
     * @throws \LaravelNats\Exceptions\PublishException When publishing fails
     */
    public function publish(string $subject, mixed $payload, array $headers = []): void;

    /**
     * Publish a message and wait for a reply (request/reply pattern).
     *
     * This method publishes a message with an auto-generated reply subject,
     * then waits for a response on that subject.
     *
     * @param string $subject The subject to publish to
     * @param mixed $payload The message payload (will be serialized)
     * @param float $timeout Maximum seconds to wait for reply
     * @param array<string, string> $headers Optional message headers
     *
     * @throws \LaravelNats\Exceptions\TimeoutException When no reply received in time
     * @throws \LaravelNats\Exceptions\PublishException When publishing fails
     *
     * @return MessageInterface The reply message
     */
    public function request(string $subject, mixed $payload, float $timeout = 5.0, array $headers = []): MessageInterface;

    /**
     * Publish a raw message without serialization.
     *
     * Use this when you have pre-serialized data or binary content.
     *
     * @param string $subject The subject to publish to
     * @param string $payload The raw payload bytes
     * @param string|null $replyTo Optional reply-to subject
     * @param array<string, string> $headers Optional message headers
     *
     * @throws \LaravelNats\Exceptions\PublishException When publishing fails
     */
    public function publishRaw(string $subject, string $payload, ?string $replyTo = null, array $headers = []): void;
}
