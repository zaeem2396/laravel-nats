<?php

declare(strict_types=1);

namespace LaravelNats\Connection;

use Basis\Nats\Client;
use Basis\Nats\Configuration as BasisConfiguration;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Manages named Basis\Nats\Client instances from Laravel config (`nats_basis`).
 *
 * @see \Basis\Nats\Client
 * @see \LaravelNats\Laravel\Facades\NatsV2
 * @see \LaravelNats\Subscriber\NatsBasisSubscriber
 */
final class ConnectionManager
{
    /**
     * @var array<string, Client>
     */
    private array $clients = [];

    public function __construct(
        private readonly Repository $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getDefaultConnection(): string
    {
        $name = $this->config->get('nats_basis.default', 'default');

        return is_string($name) && $name !== '' ? $name : 'default';
    }

    /**
     * @throws InvalidArgumentException
     */
    public function connection(?string $name = null): Client
    {
        $resolved = $name ?? $this->getDefaultConnection();

        if (! isset($this->clients[$resolved])) {
            $this->clients[$resolved] = $this->createClient($resolved);
        }

        return $this->clients[$resolved];
    }

    public function disconnect(?string $name = null): void
    {
        if ($name === null) {
            foreach (array_keys($this->clients) as $connectionName) {
                if (! is_string($connectionName)) {
                    continue;
                }
                $this->clients[$connectionName]->disconnect();
                unset($this->clients[$connectionName]);
            }

            return;
        }

        if (isset($this->clients[$name])) {
            $this->clients[$name]->disconnect();
            unset($this->clients[$name]);
        }
    }

    public function disconnectAll(): void
    {
        $this->disconnect(null);
    }

    /**
     * @return array<string, Client>
     */
    public function getConnections(): array
    {
        return $this->clients;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function createClient(string $name): Client
    {
        /** @var mixed $connections */
        $connections = $this->config->get('nats_basis.connections', []);

        if (! is_array($connections) || ! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw new InvalidArgumentException("NATS basis connection [{$name}] is not defined.");
        }

        /** @var array<string, mixed> $c */
        $c = $connections[$name];

        $configuration = new BasisConfiguration(
            host: (string) ($c['host'] ?? '127.0.0.1'),
            port: (int) ($c['port'] ?? 4222),
            user: $this->nullableString($c['user'] ?? null),
            jwt: $this->nullableString($c['jwt'] ?? null),
            pass: $this->nullableString($c['pass'] ?? null),
            token: $this->nullableString($c['token'] ?? null),
            nkey: $this->nullableString($c['nkey'] ?? null),
            tlsKeyFile: $this->nullableString($c['tlsKeyFile'] ?? null),
            tlsCertFile: $this->nullableString($c['tlsCertFile'] ?? null),
            tlsCaFile: $this->nullableString($c['tlsCaFile'] ?? null),
            tlsHandshakeFirst: (bool) ($c['tlsHandshakeFirst'] ?? false),
            pedantic: (bool) ($c['pedantic'] ?? false),
            reconnect: (bool) ($c['reconnect'] ?? true),
            verbose: (bool) ($c['verbose'] ?? false),
            timeout: (float) ($c['timeout'] ?? 1.0),
            pingInterval: (int) ($c['pingInterval'] ?? 2),
            lang: (string) ($c['lang'] ?? 'php'),
            version: (string) ($c['version'] ?? 'laravel-nats'),
        );

        return new Client(
            configuration: $configuration,
            logger: $this->logger,
            connection: null,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
