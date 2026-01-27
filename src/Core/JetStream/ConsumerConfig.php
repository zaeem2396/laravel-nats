<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

/**
 * ConsumerConfig holds configuration for a JetStream consumer.
 *
 * Used when creating durable or ephemeral consumers. Encapsulates
 * deliver policy, ack policy, filter subjects, and related options.
 */
final class ConsumerConfig
{
    /**
     * Deliver policy: all messages from the stream.
     */
    public const DELIVER_ALL = 'all';

    /**
     * Deliver policy: only the last message.
     */
    public const DELIVER_LAST = 'last';

    /**
     * Deliver policy: last message per subject.
     */
    public const DELIVER_LAST_PER_SUBJECT = 'last_per_subject';

    /**
     * Deliver policy: only new messages from now.
     */
    public const DELIVER_NEW = 'new';

    /**
     * Deliver policy: from a given sequence.
     */
    public const DELIVER_BY_START_SEQUENCE = 'by_start_sequence';

    /**
     * Deliver policy: from a given time.
     */
    public const DELIVER_BY_START_TIME = 'by_start_time';

    /**
     * Ack policy: no acks required.
     */
    public const ACK_NONE = 'none';

    /**
     * Ack policy: ack all prior when acking one.
     */
    public const ACK_ALL = 'all';

    /**
     * Ack policy: explicit ack per message.
     */
    public const ACK_EXPLICIT = 'explicit';

    /**
     * Replay policy: deliver as fast as possible.
     */
    public const REPLAY_INSTANT = 'instant';

    /**
     * Replay policy: preserve original timing.
     */
    public const REPLAY_ORIGINAL = 'original';

    /**
     * Durable consumer name (null for ephemeral).
     */
    private ?string $durableName = null;

    /**
     * Filter subject (e.g. "orders.>" or "orders.created").
     */
    private ?string $filterSubject = null;

    /**
     * Deliver policy.
     */
    private string $deliverPolicy = self::DELIVER_ALL;

    /**
     * Ack policy.
     */
    private string $ackPolicy = self::ACK_EXPLICIT;

    /**
     * Ack wait time in seconds (converted to ns in API).
     */
    private ?float $ackWait = null;

    /**
     * Max deliver (redelivery) attempts.
     */
    private ?int $maxDeliver = null;

    /**
     * Replay policy.
     */
    private string $replayPolicy = self::REPLAY_INSTANT;

    /**
     * Deliver subject for push consumers.
     */
    private ?string $deliverSubject = null;

    /**
     * Start sequence when deliver_policy is by_start_sequence.
     */
    private ?int $optStartSeq = null;

    /**
     * Start time when deliver_policy is by_start_time (RFC3339 string).
     */
    private ?string $optStartTime = null;

    /**
     * Create a new consumer configuration.
     *
     * @param string|null $durableName Durable name (null for ephemeral)
     */
    public function __construct(?string $durableName = null)
    {
        $this->durableName = $durableName === '' ? null : $durableName;
    }

    /**
     * Create from array (e.g. API response or config).
     *
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $durable = isset($data['durable_name']) ? (string) $data['durable_name'] : null;
        $config = new self($durable === '' ? null : $durable);

        if (isset($data['filter_subject'])) {
            $config->filterSubject = (string) $data['filter_subject'];
        }
        if (isset($data['deliver_policy'])) {
            $config->deliverPolicy = (string) $data['deliver_policy'];
        }
        if (isset($data['ack_policy'])) {
            $config->ackPolicy = (string) $data['ack_policy'];
        }
        if (isset($data['ack_wait'])) {
            $config->ackWait = (float) $data['ack_wait'];
        }
        if (isset($data['max_deliver'])) {
            $config->maxDeliver = (int) $data['max_deliver'];
        }
        if (isset($data['replay_policy'])) {
            $config->replayPolicy = (string) $data['replay_policy'];
        }
        if (isset($data['deliver_subject'])) {
            $config->deliverSubject = (string) $data['deliver_subject'];
        }
        if (isset($data['opt_start_seq'])) {
            $config->optStartSeq = (int) $data['opt_start_seq'];
        }
        if (isset($data['opt_start_time'])) {
            $config->optStartTime = (string) $data['opt_start_time'];
        }

        return $config;
    }

    public function getDurableName(): ?string
    {
        return $this->durableName;
    }

    public function withDurableName(?string $name): self
    {
        $new = clone $this;
        $new->durableName = $name === '' ? null : $name;

        return $new;
    }

    public function getFilterSubject(): ?string
    {
        return $this->filterSubject;
    }

    public function withFilterSubject(?string $subject): self
    {
        $new = clone $this;
        $new->filterSubject = $subject;

        return $new;
    }

    public function getDeliverPolicy(): string
    {
        return $this->deliverPolicy;
    }

    public function withDeliverPolicy(string $policy): self
    {
        $new = clone $this;
        $new->deliverPolicy = $policy;

        return $new;
    }

    public function getAckPolicy(): string
    {
        return $this->ackPolicy;
    }

    public function withAckPolicy(string $policy): self
    {
        $new = clone $this;
        $new->ackPolicy = $policy;

        return $new;
    }

    public function getAckWait(): ?float
    {
        return $this->ackWait;
    }

    public function withAckWait(?float $seconds): self
    {
        $new = clone $this;
        $new->ackWait = $seconds;

        return $new;
    }

    public function getMaxDeliver(): ?int
    {
        return $this->maxDeliver;
    }

    public function withMaxDeliver(?int $n): self
    {
        $new = clone $this;
        $new->maxDeliver = $n;

        return $new;
    }

    public function getReplayPolicy(): string
    {
        return $this->replayPolicy;
    }

    public function withReplayPolicy(string $policy): self
    {
        $new = clone $this;
        $new->replayPolicy = $policy;

        return $new;
    }

    public function getDeliverSubject(): ?string
    {
        return $this->deliverSubject;
    }

    public function withDeliverSubject(?string $subject): self
    {
        $new = clone $this;
        $new->deliverSubject = $subject;

        return $new;
    }

    public function getOptStartSeq(): ?int
    {
        return $this->optStartSeq;
    }

    public function withOptStartSeq(?int $seq): self
    {
        $new = clone $this;
        $new->optStartSeq = $seq;

        return $new;
    }

    public function getOptStartTime(): ?string
    {
        return $this->optStartTime;
    }

    public function withOptStartTime(?string $time): self
    {
        $new = clone $this;
        $new->optStartTime = $time;

        return $new;
    }

    /**
     * Convert to JetStream API payload format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'deliver_policy' => $this->deliverPolicy,
            'ack_policy' => $this->ackPolicy,
            'replay_policy' => $this->replayPolicy,
        ];

        if ($this->durableName !== null) {
            $data['durable_name'] = $this->durableName;
        }
        if ($this->filterSubject !== null) {
            $data['filter_subject'] = $this->filterSubject;
        }
        if ($this->ackWait !== null) {
            $data['ack_wait'] = (int) ($this->ackWait * 1_000_000_000);
        }
        if ($this->maxDeliver !== null) {
            $data['max_deliver'] = $this->maxDeliver;
        }
        if ($this->deliverSubject !== null) {
            $data['deliver_subject'] = $this->deliverSubject;
        }
        if ($this->optStartSeq !== null) {
            $data['opt_start_seq'] = $this->optStartSeq;
        }
        if ($this->optStartTime !== null) {
            $data['opt_start_time'] = $this->optStartTime;
        }

        return $data;
    }
}
