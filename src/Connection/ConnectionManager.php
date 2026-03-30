<?php

declare(strict_types=1);

namespace LaravelNats\Connection;

use Basis\Nats\Client;
use Basis\Nats\Configuration as BasisConfiguration;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Manages named Basis\Nats\Client instances from Laravel config (`nats_basis`).
 *
 * Supports optional seed {@see NatsServerEndpoint} lists and merging {@code connect_urls} from INFO
 * for bootstrap failover when establishing a client.
 *
 * @see \Basis\Nats\Client
 * @see \LaravelNats\Laravel\Facades\NatsV2
 * @see \LaravelNats\JetStream\BasisJetStreamManager
 * @see \LaravelNats\Subscriber\NatsBasisSubscriber
 */
final class ConnectionManager
{
    /**
     * @var array<string, Client>
     */
    private array $clients = [];

    /**
     * @var array<string, list<NatsServerEndpoint>> Endpoints learned from INFO (per logical connection name)
     */
    private array $discoveredByConnection = [];

    public function __construct(
        private readonly Repository $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param list<NatsServerEndpoint> $endpoints
     *
     * @return list<NatsServerEndpoint>
     */
    private static function dedupeEndpoints(array $endpoints): array
    {
        $seen = [];
        $out = [];
        foreach ($endpoints as $e) {
            $k = $e->toKey();
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $e;
        }

        return $out;
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
            $this->discoveredByConnection = [];

            return;
        }

        if (isset($this->clients[$name])) {
            $this->clients[$name]->disconnect();
            unset($this->clients[$name]);
        }
        unset($this->discoveredByConnection[$name]);
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

        $endpoints = $this->resolveCandidateEndpoints($name, $c);
        $lastThrowable = null;

        foreach ($endpoints as $endpoint) {
            try {
                $configuration = $this->buildBasisConfiguration($c, $endpoint);
                $client = new Client(
                    configuration: $configuration,
                    logger: $this->logger,
                    connection: null,
                );

                try {
                    if (! $client->ping()) {
                        $lastThrowable = new InvalidArgumentException(
                            sprintf(
                                'NATS ping failed for connection [%s] at %s',
                                $name,
                                $endpoint->toKey(),
                            ),
                        );
                        $client->disconnect();

                        continue;
                    }
                } catch (Throwable $pingThrowable) {
                    $lastThrowable = $pingThrowable;

                    try {
                        $client->disconnect();
                    } catch (Throwable) {
                        // ignore disconnect errors after failed ping
                    }

                    continue;
                }

                $this->mergeInfoConnectUrls($name, $client, $c);

                return $client;
            } catch (Throwable $e) {
                $lastThrowable = $e;
            }
        }

        if ($lastThrowable !== null) {
            throw new InvalidArgumentException(
                sprintf('Could not connect to NATS for connection [%s]: %s', $name, $lastThrowable->getMessage()),
                0,
                $lastThrowable,
            );
        }

        throw new InvalidArgumentException("Could not connect to NATS for connection [{$name}] (no endpoints).");
    }

    /**
     * @param array<string, mixed> $c
     *
     * @return list<NatsServerEndpoint>
     */
    private function resolveCandidateEndpoints(string $name, array $c): array
    {
        /** @var array<string, bool> $seen */
        $seen = [];
        $list = [];

        $add = static function (NatsServerEndpoint $e) use (&$seen, &$list): void {
            $k = $e->toKey();
            if (isset($seen[$k])) {
                return;
            }
            $seen[$k] = true;
            $list[] = $e;
        };

        $primary = new NatsServerEndpoint(
            (string) ($c['host'] ?? '127.0.0.1'),
            (int) ($c['port'] ?? 4222),
        );
        $add($primary);

        /** @var mixed $servers */
        $servers = $c['servers'] ?? null;
        if (is_array($servers)) {
            foreach ($servers as $entry) {
                if (is_string($entry)) {
                    $p = NatsServerEndpoint::parse($entry);
                    if ($p !== null) {
                        $add($p);
                    }
                }
            }
        } elseif (is_string($servers) && trim($servers) !== '') {
            foreach (array_map(trim(...), explode(',', $servers)) as $part) {
                if ($part === '') {
                    continue;
                }
                $p = NatsServerEndpoint::parse($part);
                if ($p !== null) {
                    $add($p);
                }
            }
        }

        foreach ($this->discoveredByConnection[$name] ?? [] as $e) {
            $add($e);
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $c
     */
    private function mergeInfoConnectUrls(string $name, Client $client, array $c): void
    {
        if (! filter_var($c['merge_info_connect_urls'] ?? false, FILTER_VALIDATE_BOOL)) {
            return;
        }

        try {
            if (! $client->ping()) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $bc = $client->connection;
        if ($bc === null) {
            return;
        }

        $info = $bc->getInfoMessage();
        $urls = $info->connect_urls ?? null;
        if (! is_array($urls)) {
            return;
        }

        $merged = $this->discoveredByConnection[$name] ?? [];
        foreach ($urls as $u) {
            if (! is_string($u)) {
                continue;
            }
            $p = NatsServerEndpoint::parse($u);
            if ($p !== null) {
                $merged[] = $p;
            }
        }

        $this->discoveredByConnection[$name] = self::dedupeEndpoints($merged);
    }

    /**
     * @param array<string, mixed> $c
     */
    private function buildBasisConfiguration(array $c, NatsServerEndpoint $endpoint): BasisConfiguration
    {
        return new BasisConfiguration(
            host: $endpoint->host,
            port: $endpoint->port,
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
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
