<?php

declare(strict_types=1);

namespace LaravelNats\Core\JetStream;

/**
 * JetStreamConfig holds configuration for JetStream operations.
 *
 * This includes domain settings for multi-tenancy, default
 * stream/consumer configurations, and API timeouts.
 */
final class JetStreamConfig
{
    /**
     * Default API request timeout (seconds).
     */
    private const DEFAULT_TIMEOUT = 5.0;

    /**
     * JetStream domain for multi-tenancy.
     *
     * When set, API subjects use: $JS.<domain>.API.*
     * When null, uses default: $JS.API.*
     */
    private ?string $domain = null;

    /**
     * Default API request timeout.
     */
    private float $timeout = self::DEFAULT_TIMEOUT;

    /**
     * Create a new JetStream configuration.
     *
     * @param string|null $domain JetStream domain
     * @param float $timeout API request timeout
     */
    public function __construct(
        ?string $domain = null,
        float $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->domain = $domain;
        $this->timeout = $timeout;
    }

    /**
     * Create configuration from array.
     *
     * @param array<string, mixed> $config Configuration array
     *
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self(
            domain: $config['domain'] ?? null,
            timeout: (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT),
        );
    }

    /**
     * Get the JetStream domain.
     *
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Set the JetStream domain.
     *
     * @param string|null $domain
     *
     * @return self New instance with updated domain
     */
    public function withDomain(?string $domain): self
    {
        $new = clone $this;
        $new->domain = $domain;

        return $new;
    }

    /**
     * Get the API request timeout.
     *
     * @return float Timeout in seconds
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Set the API request timeout.
     *
     * @param float $timeout Timeout in seconds
     *
     * @return self New instance with updated timeout
     */
    public function withTimeout(float $timeout): self
    {
        $new = clone $this;
        $new->timeout = $timeout;

        return $new;
    }

    /**
     * Convert to array for configuration storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'timeout' => $this->timeout,
        ];
    }
}
