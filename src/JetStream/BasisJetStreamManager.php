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
        $result = $this->client($connection)->api('INFO');
        if ($result === null) {
            throw new \RuntimeException('JetStream INFO returned no result.');
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function streamNames(?string $connection = null): array
    {
        $raw = $this->api($connection)->getStreamNames();
        if (! is_array($raw)) {
            return [];
        }

        /** @var list<string> */
        return array_values($raw);
    }
}
