<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Messaging;

/**
 * MessageInterface defines the contract for NATS messages.
 *
 * A NATS message consists of:
 * - Subject: The destination/topic for the message
 * - Payload: The message body (typically JSON or binary data)
 * - Reply-to: Optional subject for request/reply pattern
 * - Headers: Optional key-value metadata (NATS 2.2+)
 *
 * Messages are immutable once created to ensure thread safety
 * and prevent accidental modifications during processing.
 */
interface MessageInterface
{
    /**
     * Get the message subject.
     *
     * The subject is the destination where the message was published.
     * Subjects are hierarchical, separated by dots (e.g., "orders.created").
     *
     * @return string The message subject
     */
    public function getSubject(): string;

    /**
     * Get the message payload.
     *
     * The payload is the actual message content. It can be any binary data,
     * but is typically JSON-encoded for structured data.
     *
     * @return string The raw message payload
     */
    public function getPayload(): string;

    /**
     * Get the decoded payload.
     *
     * Decodes the payload using the configured serializer.
     * For JSON payloads, this returns an array or object.
     *
     * @return mixed The decoded payload
     */
    public function getDecodedPayload(): mixed;

    /**
     * Get the reply-to subject.
     *
     * In the request/reply pattern, this subject is where the
     * responder should send its reply.
     *
     * @return string|null The reply subject, or null if not a request
     */
    public function getReplyTo(): ?string;

    /**
     * Get message headers.
     *
     * Headers are key-value metadata attached to the message.
     * Requires NATS 2.2+ server.
     *
     * @return array<string, string> The message headers
     */
    public function getHeaders(): array;

    /**
     * Get a specific header value.
     *
     * @param string $key The header key
     * @param string|null $default Default value if header not found
     *
     * @return string|null The header value or default
     */
    public function getHeader(string $key, ?string $default = null): ?string;

    /**
     * Check if the message has headers.
     *
     * @return bool True if the message has headers
     */
    public function hasHeaders(): bool;

    /**
     * Get the subscription ID this message was received on.
     *
     * @return string|null The subscription ID
     */
    public function getSid(): ?string;

    /**
     * Get the size of the payload in bytes.
     *
     * @return int The payload size
     */
    public function getSize(): int;

    /**
     * Check if this message expects a reply.
     *
     * @return bool True if the message has a reply-to subject
     */
    public function expectsReply(): bool;
}
