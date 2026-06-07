<?php

declare(strict_types=1);

use LaravelNats\Core\Client;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Core\JetStream\JetStreamClient;
use LaravelNats\Core\Serialization\JsonSerializer;
use LaravelNats\Core\Serialization\PhpSerializer;
use LaravelNats\Exceptions\ConnectionException;

beforeEach(function (): void {
    $this->client = new Client(ConnectionConfig::local());
});

describe('Client construction', function (): void {
    it('starts disconnected', function (): void {
        expect($this->client->isConnected())->toBeFalse()
            ->and($this->client->getServerInfo())->toBeNull()
            ->and($this->client->getSubscriptionCount())->toBe(0);
    });

    it('allows serializer swap', function (): void {
        $php = new PhpSerializer;
        $this->client->setSerializer($php);

        expect($this->client->getSerializer())->toBe($php);
    });

    it('exposes JetStream client wrapper', function (): void {
        expect($this->client->getJetStream())->toBeInstanceOf(JetStreamClient::class);
    });
});

describe('Client guards when disconnected', function (): void {
    it('throws when publishing', function (): void {
        expect(fn () => $this->client->publish('test.subject', ['x' => 1]))
            ->toThrow(ConnectionException::class);
    });

    it('throws when publishing raw', function (): void {
        expect(fn () => $this->client->publishRaw('test.subject', 'payload'))
            ->toThrow(ConnectionException::class);
    });

    it('throws when requesting', function (): void {
        expect(fn () => $this->client->request('test.subject', 'payload', 0.1))
            ->toThrow(ConnectionException::class);
    });

    it('throws when subscribing', function (): void {
        expect(fn () => $this->client->subscribe('test.*', fn () => null))
            ->toThrow(ConnectionException::class);
    });
});

describe('Client subscription bookkeeping', function (): void {
    it('reports missing subscription ids', function (): void {
        expect($this->client->hasSubscription('missing'))->toBeFalse()
            ->and($this->client->getSubscriptions())->toBe([]);
    });
});

describe('Client publish validation', function (): void {
    it('uses default json serializer', function (): void {
        expect($this->client->getSerializer())->toBeInstanceOf(JsonSerializer::class);
    });
});
