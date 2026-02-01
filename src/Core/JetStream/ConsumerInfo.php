<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

/**
 * ConsumerInfo represents the current state and configuration of a JetStream consumer.
 *
 * Returned by getConsumerInfo() and encapsulates the consumer config
 * and state (pending, ack pending, etc.) from the JetStream API.
 */
final class ConsumerInfo
{
    /**
     * Consumer configuration.
     */
    private ConsumerConfig $config;

    /**
     * Consumer state (num_pending, num_ack_pending, num_waiting, etc.).
     *
     * @var array<string, mixed>
     */
    private array $state;

    /**
     * Stream name this consumer belongs to.
     */
    private string $streamName;

    /**
     * Consumer name.
     */
    private string $name;

    /**
     * @param string $streamName Stream name
     * @param string $name Consumer name
     * @param ConsumerConfig $config Consumer configuration
     * @param array<string, mixed> $state Consumer state
     */
    public function __construct(
        string $streamName,
        string $name,
        ConsumerConfig $config,
        array $state = [],
    ) {
        $this->streamName = $streamName;
        $this->name = $name;
        $this->config = $config;
        $this->state = $state;
    }

    /**
     * Create from JetStream API consumer info response.
     *
     * @param array<string, mixed> $data API response (must contain stream_name, name, config, state)
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $streamName = (string) ($data['stream_name'] ?? $data['name'] ?? '');
        $name = (string) ($data['name'] ?? '');
        $configData = $data['config'] ?? [];
        $config = ConsumerConfig::fromArray($configData);
        $state = $data['state'] ?? [];

        return new self($streamName, $name, $config, $state);
    }

    public function getStreamName(): string
    {
        return $this->streamName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): ConsumerConfig
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }

    public function getNumPending(): int
    {
        return (int) ($this->state['num_pending'] ?? 0);
    }

    public function getNumAckPending(): int
    {
        return (int) ($this->state['num_ack_pending'] ?? 0);
    }

    public function getNumWaiting(): int
    {
        return (int) ($this->state['num_waiting'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stream_name' => $this->streamName,
            'name' => $this->name,
            'config' => $this->config->toArray(),
            'state' => $this->state,
        ];
    }
}
