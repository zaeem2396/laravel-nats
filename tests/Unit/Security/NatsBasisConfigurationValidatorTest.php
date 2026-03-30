<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use LaravelNats\Security\Exceptions\NatsConfigurationException;
use LaravelNats\Security\NatsBasisConfigurationValidator;

it('passes for valid default-shaped connection when forced', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'connections' => [
                'default' => [
                    'host' => '127.0.0.1',
                    'port' => 4222,
                    'timeout' => 1.0,
                ],
            ],
        ],
    ]);

    $v = new NatsBasisConfigurationValidator();
    $v->validate($config, $this->app, true);

    expect(true)->toBeTrue();
});

it('rejects empty host when forced', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'connections' => [
                'default' => [
                    'host' => '  ',
                    'port' => 4222,
                    'timeout' => 1.0,
                ],
            ],
        ],
    ]);

    $v = new NatsBasisConfigurationValidator();

    expect(fn () => $v->validate($config, $this->app, true))
        ->toThrow(NatsConfigurationException::class);
});

it('rejects invalid port when forced', function (): void {
    $config = new Repository([
        'nats_basis' => [
            'connections' => [
                'default' => [
                    'host' => 'localhost',
                    'port' => 70000,
                    'timeout' => 1.0,
                ],
            ],
        ],
    ]);

    $v = new NatsBasisConfigurationValidator();

    expect(fn () => $v->validate($config, $this->app, true))
        ->toThrow(NatsConfigurationException::class);
});

it('requires TLS material in production when tls.require_in_production is true', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    $config = new Repository([
        'nats_basis' => [
            'connections' => [
                'default' => [
                    'host' => 'nats.example',
                    'port' => 4222,
                    'timeout' => 2.0,
                    'tlsCaFile' => null,
                    'tlsCertFile' => null,
                    'tlsKeyFile' => null,
                    'tlsHandshakeFirst' => false,
                ],
            ],
            'security' => [
                'tls' => [
                    'require_in_production' => true,
                ],
            ],
        ],
    ]);

    $v = new NatsBasisConfigurationValidator();

    expect(fn () => $v->validate($config, $this->app, true))
        ->toThrow(NatsConfigurationException::class);
});

it('allows production when tls ca is set and require_in_production is true', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    $config = new Repository([
        'nats_basis' => [
            'connections' => [
                'default' => [
                    'host' => 'nats.example',
                    'port' => 4222,
                    'timeout' => 2.0,
                    'tlsCaFile' => '/path/ca.pem',
                    'tlsCertFile' => null,
                    'tlsKeyFile' => null,
                    'tlsHandshakeFirst' => false,
                ],
            ],
            'security' => [
                'tls' => [
                    'require_in_production' => true,
                ],
            ],
        ],
    ]);

    $v = new NatsBasisConfigurationValidator();
    $v->validate($config, $this->app, true);

    expect(true)->toBeTrue();
});
