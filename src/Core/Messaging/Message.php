<?php

declare(strict_types=1);

namespace LaravelNats\Core\Messaging;

use LaravelNats\Contracts\Messaging\MessageInterface;
use LaravelNats\Contracts\Serialization\SerializerInterface;

/**
 * Message represents a NATS message.
 *
 * This is an immutable value object that encapsulates all parts of a NATS message:
 * - Subject: Where the message was published
 * - Payload: The message content (raw bytes)
 * - Reply-To: Optional subject for request/reply pattern
 * - Headers: Optional key-value metadata
 * - SID: Subscription ID (for received messages)
 *
 * Messages are immutable to ensure:
 * - Thread safety in concurrent processing
 * - No accidental modifications during handling
 * - Clear ownership semantics
 */
final class Message implements MessageInterface
{
    /**
     * Cached decoded payload.
     */
    private mixed $decodedPayload = null;

    /**
     * Whether payload has been decoded.
     */
    private bool $payloadDecoded = false;

    /**
     * Create a new message.
     *
     * @param string $subject The message subject
     * @param string $payload The raw message payload
     * @param string|null $replyTo Optional reply-to subject
     * @param array<string, string> $headers Message headers
     * @param string|null $sid Subscription ID
     * @param SerializerInterface|null $serializer Serializer for decoding
     */
    public function __construct(
        private readonly string $subject,
        private readonly string $payload,
        private readonly ?string $replyTo = null,
        private readonly array $headers = [],
        private readonly ?string $sid = null,
        private readonly ?SerializerInterface $serializer = null,
    ) {
    }

    /**
     * Create a message for publishing.
     *
     * @param string $subject The subject to publish to
     * @param mixed $payload The payload (will be serialized)
     * @param SerializerInterface $serializer The serializer to use
     * @param array<string, string> $headers Optional headers
     *
     * @return self
     */
    public static function create(
        string $subject,
        mixed $payload,
        SerializerInterface $serializer,
        array $headers = [],
    ): self {
        return new self(
            subject: $subject,
            payload: $serializer->serialize($payload),
            headers: $headers,
            serializer: $serializer,
        );
    }

    /**
     * Create a message from raw received data.
     *
     * @param string $subject The message subject
     * @param string $payload The raw payload
     * @param string|null $replyTo Reply-to subject
     * @param string $sid Subscription ID
     * @param array<string, string> $headers Message headers
     * @param SerializerInterface|null $serializer Serializer for decoding
     *
     * @return self
     */
    public static function fromReceived(
        string $subject,
        string $payload,
        ?string $replyTo,
        string $sid,
        array $headers = [],
        ?SerializerInterface $serializer = null,
    ): self {
        return new self(
            subject: $subject,
            payload: $payload,
            replyTo: $replyTo,
            headers: $headers,
            sid: $sid,
            serializer: $serializer,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * {@inheritdoc}
     */
    public function getDecodedPayload(): mixed
    {
        if ($this->payloadDecoded) {
            return $this->decodedPayload;
        }

        if ($this->serializer !== null) {
            $this->decodedPayload = $this->serializer->deserialize($this->payload);
        } else {
            // Try JSON decode as default
            $decoded = json_decode($this->payload, true);
            $this->decodedPayload = json_last_error() === JSON_ERROR_NONE ? $decoded : $this->payload;
        }

        $this->payloadDecoded = true;

        return $this->decodedPayload;
    }

    /**
     * {@inheritdoc}
     */
    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeaders(): bool
    {
        return $this->headers !== [];
    }

    /**
     * {@inheritdoc}
     */
    public function getSid(): ?string
    {
        return $this->sid;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): int
    {
        return strlen($this->payload);
    }

    /**
     * {@inheritdoc}
     */
    public function expectsReply(): bool
    {
        return $this->replyTo !== null;
    }

    /**
     * Create a reply message to this message.
     *
     * @param mixed $payload The reply payload
     * @param SerializerInterface $serializer Serializer for the reply
     * @param array<string, string> $headers Optional reply headers
     *
     * @return self|null The reply message, or null if no reply-to
     */
    public function createReply(
        mixed $payload,
        SerializerInterface $serializer,
        array $headers = [],
    ): ?self {
        if ($this->replyTo === null) {
            return null;
        }

        return self::create($this->replyTo, $payload, $serializer, $headers);
    }
}
