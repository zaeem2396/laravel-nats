<?php

declare(strict_types=1);

namespace LaravelNats\Core\Connection;

use LaravelNats\Contracts\Connection\ConnectionConfigInterface;

/**
 * ConnectionConfig holds all configuration for a NATS connection.
 *
 * This is a value object that encapsulates connection parameters.
 * It's immutable once created, ensuring configuration consistency
 * throughout the connection lifecycle.
 *
 * Configuration sources:
 * - Direct instantiation in code
 * - From Laravel config/nats.php (via ConnectionConfig::fromArray)
 * - From environment variables (through Laravel config)
 */
final class ConnectionConfig implements ConnectionConfigInterface
{
    /**
     * Default NATS port.
     */
    private const DEFAULT_PORT = 4222;

    /**
     * Default connection timeout in seconds.
     */
    private const DEFAULT_TIMEOUT = 5.0;

    /**
     * Default ping interval in seconds.
     */
    private const DEFAULT_PING_INTERVAL = 120.0;

    /**
     * Default max pings outstanding.
     */
    private const DEFAULT_MAX_PINGS_OUT = 2;

    /**
     * Create a new connection configuration.
     *
     * @param string $host The NATS server hostname
     * @param int $port The NATS server port
     * @param string|null $user Username for authentication
     * @param string|null $password Password for authentication
     * @param string|null $token Token for authentication
     * @param float $timeout Connection timeout in seconds
     * @param bool $tlsEnabled Whether to use TLS
     * @param array<string, mixed> $tlsOptions TLS context options
     * @param string $clientName Client name for identification
     * @param bool $verbose Enable verbose mode
     * @param bool $pedantic Enable pedantic mode
     * @param float $pingInterval Ping interval in seconds
     * @param int $maxPingsOut Max pings without pong
     */
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = self::DEFAULT_PORT,
        private readonly ?string $user = null,
        private readonly ?string $password = null,
        private readonly ?string $token = null,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        private readonly bool $tlsEnabled = false,
        private readonly array $tlsOptions = [],
        private readonly string $clientName = 'laravel-nats',
        private readonly bool $verbose = false,
        private readonly bool $pedantic = false,
        private readonly float $pingInterval = self::DEFAULT_PING_INTERVAL,
        private readonly int $maxPingsOut = self::DEFAULT_MAX_PINGS_OUT,
    ) {
    }

    /**
     * Create a configuration from an array.
     *
     * This is typically used to create config from Laravel's config() helper.
     *
     * @param array<string, mixed> $config Configuration array
     *
     * @return self
     */
    public static function fromArray(array $config): self
    {
        // Parse host and port from URL if provided
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? self::DEFAULT_PORT;

        // Support URL format: nats://host:port
        if (isset($config['url'])) {
            $parsed = parse_url($config['url']);
            if ($parsed !== false) {
                $host = $parsed['host'] ?? $host;
                $port = $parsed['port'] ?? $port;
            }
        }

        return new self(
            host: $host,
            port: (int) $port,
            user: $config['user'] ?? $config['auth']['user'] ?? null,
            password: $config['password'] ?? $config['auth']['password'] ?? null,
            token: $config['token'] ?? $config['auth']['token'] ?? null,
            timeout: (float) ($config['timeout'] ?? self::DEFAULT_TIMEOUT),
            tlsEnabled: (bool) ($config['tls']['enabled'] ?? $config['tls'] ?? false),
            tlsOptions: $config['tls']['options'] ?? [],
            clientName: $config['client_name'] ?? $config['name'] ?? 'laravel-nats',
            verbose: (bool) ($config['verbose'] ?? false),
            pedantic: (bool) ($config['pedantic'] ?? false),
            pingInterval: (float) ($config['ping_interval'] ?? self::DEFAULT_PING_INTERVAL),
            maxPingsOut: (int) ($config['max_pings_out'] ?? self::DEFAULT_MAX_PINGS_OUT),
        );
    }

    /**
     * Create a configuration for a local development server.
     *
     * @return self
     */
    public static function local(): self
    {
        return new self();
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function isTlsEnabled(): bool
    {
        return $this->tlsEnabled;
    }

    public function getTlsOptions(): array
    {
        return $this->tlsOptions;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isPedantic(): bool
    {
        return $this->pedantic;
    }

    public function getPingInterval(): float
    {
        return $this->pingInterval;
    }

    public function getMaxPingsOut(): int
    {
        return $this->maxPingsOut;
    }

    /**
     * Get the full server address.
     *
     * @return string E.g., "localhost:4222"
     */
    public function getAddress(): string
    {
        return $this->host . ':' . $this->port;
    }

    /**
     * Check if authentication is configured.
     *
     * @return bool
     */
    public function hasAuth(): bool
    {
        return $this->user !== null || $this->token !== null;
    }

    public function toConnectArray(): array
    {
        $options = [
            'verbose' => $this->verbose,
            'pedantic' => $this->pedantic,
            'name' => $this->clientName,
            'lang' => 'php',
            'version' => '0.1.0', // Package version
            'protocol' => 1,
            'echo' => true, // Receive own messages
        ];

        // Add authentication if configured
        if ($this->user !== null && $this->password !== null) {
            $options['user'] = $this->user;
            $options['pass'] = $this->password;
        } elseif ($this->token !== null) {
            $options['auth_token'] = $this->token;
        }

        // Indicate TLS requirement
        if ($this->tlsEnabled) {
            $options['tls_required'] = true;
        }

        return $options;
    }
}
