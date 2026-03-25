<?php

declare(strict_types=1);

namespace LaravelNats\JetStream;

use Basis\Nats\Api;
use Basis\Nats\Client;
use Basis\Nats\Stream\Stream;
use LaravelNats\Connection\ConnectionManager;

/**
 * Laravel entry point for JetStream via basis-company/nats {@see Api}.
 */
final class BasisJetStreamManager
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly ?string $connectionName = null,
    ) {
    }

    public function client(?string $connection = null): Client
    {
        return $this->connections->connection($connection ?? $this->connectionName);
    }

    public function api(?string $connection = null): Api
    {
        return $this->client($connection)->getApi();
    }

    public function stream(string $name, ?string $connection = null): Stream
    {
        return $this->api($connection)->getStream($name);
    }

    /**
     * JetStream account summary ($JS.API.INFO).
     */
    public function accountInfo(?string $connection = null): object
    {
        return $this->client($connection)->api('INFO');
    }

    /**
     * @return list<string>
     */
    public function streamNames(?string $connection = null): array
    {
        $names = $this->api($connection)->getStreamNames();

        return is_array($names) ? array_values($names) : [];
    }
}
