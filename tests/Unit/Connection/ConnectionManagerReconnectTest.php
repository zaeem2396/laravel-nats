<?php

declare(strict_types=1);

use Basis\Nats\Client;
use Illuminate\Config\Repository;
use LaravelNats\Connection\ConnectionManager;
use LaravelNats\Tests\TestCase;

function basisManagerConfig(): Repository
{
    return new Repository([
        'nats_basis' => [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'host' => getenv('NATS_HOST') ?: 'localhost',
                    'port' => (int) (getenv('NATS_PORT') ?: '4222'),
                    'timeout' => 2.0,
                ],
            ],
        ],
    ]);
}

describe('ConnectionManager reconnect', function (): void {
    it('returns a fresh client after reconnect', function (): void {
        TestCase::skipUnlessNatsReachable();

        $manager = new ConnectionManager(basisManagerConfig());

        $first = $manager->connection('default');
        expect($first->ping())->toBeTrue();

        $reconnected = $manager->reconnect('default');

        expect($reconnected)->toBeInstanceOf(Client::class)
            ->and($reconnected->ping())->toBeTrue()
            ->and($manager->getConnections())->toHaveKey('default');

        $manager->disconnectAll();
    });

    it('drops cached client on disconnect so connection() creates a new one', function (): void {
        TestCase::skipUnlessNatsReachable();

        $manager = new ConnectionManager(basisManagerConfig());

        $manager->connection('default');
        $manager->disconnect('default');

        expect($manager->getConnections())->toBeEmpty();

        $again = $manager->connection('default');
        expect($again->ping())->toBeTrue();

        $manager->disconnectAll();
    });
});
