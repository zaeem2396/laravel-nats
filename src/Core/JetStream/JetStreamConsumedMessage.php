<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

use LaravelNats\Contracts\Messaging\MessageInterface;

/**
 * JetStreamConsumedMessage represents a message consumed from a JetStream pull consumer.
 *
 * It wraps the NATS message received from CONSUMER.MSG.NEXT and provides:
 * - Stream and consumer names
 * - Stream and consumer sequence numbers
 * - The ack subject (reply-to) for sending ACK, NAK, TERM, or IN_PROGRESS
 * - The message payload
 *
 * Use JetStreamClient::ack(), nak(), term(), or inProgress() to acknowledge the message.
 */
final class JetStreamConsumedMessage
{
    /**
     * Ack type: positive acknowledgment (message processed successfully).
     */
    public const ACK = '+ACK';

    /**
     * Ack type: negative acknowledgment (redeliver the message).
     */
    public const NAK = '-NAK';

    /**
     * Ack type: terminate (do not redeliver, discard).
     */
    public const TERM = '+TERM';

    /**
     * Ack type: work in progress (extend ack wait, do not redeliver yet).
     */
    public const IN_PROGRESS = '+WPI';

    /**
     * The subject to publish acknowledgments to (reply-to of the consumed message).
     */
    private string $ackSubject;

    /**
     * Stream name.
     */
    private string $streamName;

    /**
     * Consumer name.
     */
    private string $consumerName;

    /**
     * Stream sequence number.
     */
    private ?int $streamSequence = null;

    /**
     * Consumer sequence number.
     */
    private ?int $consumerSequence = null;

    /**
     * Raw message payload.
     */
    private string $payload;

    /**
     * Message headers (e.g. Nats-Stream, Nats-Sequence).
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * The underlying NATS message.
     */
    private MessageInterface $natsMessage;

    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        string $ackSubject,
        string $streamName,
        string $consumerName,
        ?int $streamSequence,
        ?int $consumerSequence,
        string $payload,
        array $headers,
        MessageInterface $natsMessage,
    ) {
        $this->ackSubject = $ackSubject;
        $this->streamName = $streamName;
        $this->consumerName = $consumerName;
        $this->streamSequence = $streamSequence;
        $this->consumerSequence = $consumerSequence;
        $this->payload = $payload;
        $this->headers = $headers;
        $this->natsMessage = $natsMessage;
    }

    /**
     * Create from a NATS message received from CONSUMER.MSG.NEXT.
     *
     * Parses the reply-to subject ($JS.ACK.<stream>.<consumer>.<delivered>.<sseq>.<cseq>.<tm>.<pending>)
     * and optional Nats-* headers to populate stream, consumer, and sequences.
     *
     * @param MessageInterface $message The message returned by request() to CONSUMER.MSG.NEXT
     *
     * @return self
     */
    public static function fromNatsMessage(MessageInterface $message): self
    {
        $replyTo = $message->getReplyTo();
        if ($replyTo === null || $replyTo === '') {
            throw new \InvalidArgumentException('JetStream consumed message must have a reply-to (ack) subject');
        }

        $streamName = '';
        $consumerName = '';
        $streamSeq = null;
        $consumerSeq = null;

        // Parse ack subject: $JS.ACK.<stream>.<consumer>.<delivered>.<sseq>.<cseq>.<tm>.<pending> (9 tokens)
        // With domain: $JS.ACK.<domain>.<account>.<stream>.<consumer>.<delivered>.<sseq>.<cseq>.<tm>.<pending>.<token> (12 tokens)
        $tokens = explode('.', $replyTo);
        if (count($tokens) >= 9 && $tokens[0] === '$JS' && $tokens[1] === 'ACK') {
            if (count($tokens) >= 12) {
                // With domain: 2=domain, 3=account, 4=stream, 5=consumer, 6=delivered, 7=sseq, 8=cseq
                $streamName = $tokens[4];
                $consumerName = $tokens[5];
                if (is_numeric($tokens[7])) {
                    $streamSeq = (int) $tokens[7];
                }
                if (is_numeric($tokens[8])) {
                    $consumerSeq = (int) $tokens[8];
                }
            } else {
                // Without domain: 2=stream, 3=consumer, 4=delivered, 5=sseq, 6=cseq
                $streamName = $tokens[2];
                $consumerName = $tokens[3];
                if (is_numeric($tokens[5])) {
                    $streamSeq = (int) $tokens[5];
                }
                if (is_numeric($tokens[6])) {
                    $consumerSeq = (int) $tokens[6];
                }
            }
        }

        // Prefer headers if present (Nats-Stream, Nats-Sequence from republish headers)
        if ($message->getHeader('Nats-Stream') !== null) {
            $streamName = (string) $message->getHeader('Nats-Stream');
        }
        if ($message->getHeader('Nats-Sequence') !== null && is_numeric($message->getHeader('Nats-Sequence'))) {
            $streamSeq = (int) (string) $message->getHeader('Nats-Sequence');
        }

        return new self(
            ackSubject: $replyTo,
            streamName: $streamName,
            consumerName: $consumerName,
            streamSequence: $streamSeq,
            consumerSequence: $consumerSeq,
            payload: $message->getPayload(),
            headers: $message->getHeaders(),
            natsMessage: $message,
        );
    }

    public function getAckSubject(): string
    {
        return $this->ackSubject;
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    public function getConsumerName(): string
    {
        return $this->consumerName;
    }

    public function getStreamSequence(): ?int
    {
        return $this->streamSequence;
    }

    public function getConsumerSequence(): ?int
    {
        return $this->consumerSequence;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getNatsMessage(): MessageInterface
    {
        return $this->natsMessage;
    }

    /**
     * Decode payload using the underlying message's serializer (if any).
     */
    public function getDecodedPayload(): mixed
    {
        return $this->natsMessage->getDecodedPayload();
    }
}
