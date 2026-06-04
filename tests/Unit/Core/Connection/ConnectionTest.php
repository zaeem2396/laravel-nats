<?php

declare(strict_types=1);

use LaravelNats\Core\Connection\Connection;
use LaravelNats\Core\Connection\ConnectionConfig;
use LaravelNats\Exceptions\ConnectionException;

beforeEach(function (): void {
    $this->connection = new Connection(ConnectionConfig::local());
});

it('starts disconnected with no server info', function (): void {
    expect($this->connection->isConnected())->toBeFalse()
        ->and($this->connection->getServerInfo())->toBeNull()
        ->and($this->connection->getFailedPingCount())->toBe(0);
});

it('allows disconnect when never connected', function (): void {
    $this->connection->disconnect();

    expect($this->connection->isConnected())->toBeFalse();
});

it('reports health check not due before connect', function (): void {
    expect($this->connection->isHealthCheckDue())->toBeFalse()
        ->and($this->connection->healthCheck())->toBeFalse();
});

it('throws when writing while disconnected', function (): void {
    $this->connection->write('PING');
})->throws(ConnectionException::class);

it('throws when reading while disconnected', function (): void {
    $this->connection->read();
})->throws(ConnectionException::class);

it('exposes parser and command builder', function (): void {
    expect($this->connection->getParser())->not->toBeNull()
        ->and($this->connection->getCommandBuilder())->not->toBeNull();
});

it('fails connect to closed port', function (): void {
    $config = ConnectionConfig::fromArray([
        'host' => '127.0.0.1',
        'port' => 1,
        'timeout' => 0.2,
    ]);

    set_error_handler(static fn (): bool => true);

    try {
        expect(fn () => (new Connection($config))->connect())
            ->toThrow(ConnectionException::class);
    } finally {
        restore_error_handler();
    }
});
