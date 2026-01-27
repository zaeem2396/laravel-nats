<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

/**
 * StreamInfo represents the current state and configuration of a JetStream stream.
 *
 * This class encapsulates the information returned by JetStream API
 * when querying stream information, including configuration, state,
 * and statistics.
 */
final class StreamInfo
{
    /**
     * Stream configuration.
     */
    private StreamConfig $config;

    /**
     * Stream state information.
     *
     * @var array<string, mixed>
     */
    private array $state;

    /**
     * Create a new stream info instance.
     *
     * @param StreamConfig $config Stream configuration
     * @param array<string, mixed> $state Stream state
     */
    public function __construct(StreamConfig $config, array $state = [])
    {
        $this->config = $config;
        $this->state = $state;
    }

    /**
     * Create from JetStream API response.
     *
     * @param array<string, mixed> $data API response data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $configData = $data['config'] ?? [];
        $config = StreamConfig::fromArray($configData);

        $state = $data['state'] ?? [];

        return new self($config, $state);
    }

    /**
     * Get the stream configuration.
     *
     * @return StreamConfig
     */
    public function getConfig(): StreamConfig
    {
        return $this->config;
    }

    /**
     * Get the stream state.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Get the number of messages in the stream.
     *
     * @return int
     */
    public function getMessageCount(): int
    {
        return (int) ($this->state['messages'] ?? 0);
    }

    /**
     * Get the number of bytes stored.
     *
     * @return int
     */
    public function getByteCount(): int
    {
        return (int) ($this->state['bytes'] ?? 0);
    }

    /**
     * Get the first sequence number.
     *
     * @return int|null
     */
    public function getFirstSequence(): ?int
    {
        $seq = $this->state['first_seq'] ?? null;

        return $seq !== null ? (int) $seq : null;
    }

    /**
     * Get the last sequence number.
     *
     * @return int|null
     */
    public function getLastSequence(): ?int
    {
        $seq = $this->state['last_seq'] ?? null;

        return $seq !== null ? (int) $seq : null;
    }

    /**
     * Get the number of consumers.
     *
     * @return int
     */
    public function getConsumerCount(): int
    {
        return (int) ($this->state['consumer_count'] ?? 0);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'config' => $this->config->toArray(),
            'state' => $this->state,
        ];
    }
}
