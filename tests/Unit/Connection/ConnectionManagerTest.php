<?php

declare(strict_types=1);

use Basis\Nats\Client;
use Illuminate\Config\Repository;
use LaravelNats\Connection\ConnectionManager;
use Psr\Log\LoggerInterface;

it('throws when basis connection name is missing', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'default' => 'missing',
            'connections' => [
                'default' => ['host' => '127.0.0.1', 'port' => 4222],
            ],
        ],
    ]);

    $manager = new ConnectionManager($config);

    expect(fn () => $manager->connection('missing'))->toThrow(InvalidArgumentException::class);
});

it('returns default connection name from config', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'default' => 'primary',
            'connections' => [
                'primary' => ['host' => '127.0.0.1', 'port' => 4222],
            ],
        ],
    ]);

    $manager = new ConnectionManager($config);

    expect($manager->getDefaultConnection())->toBe('primary');
});

it('accepts an optional PSR-3 logger for the basis client', function (): void {
    $logger = Mockery::mock(LoggerInterface::class);
    $config = new Repository([
        'nats_basis' => [
            'default' => 'default',
            'connections' => [
                'default' => ['host' => '127.0.0.1', 'port' => 4222],
            ],
        ],
    ]);

    $manager = new ConnectionManager($config, $logger);

    expect($manager->connection('default'))->toBeInstanceOf(Client::class);
});
