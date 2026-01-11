<?php

declare(strict_types=1);

/**
 * ============================================================================
 * CONNECTION CONFIG UNIT TESTS
 * ============================================================================
 *
 * These tests verify the connection configuration correctly parses
 * and provides access to all connection parameters.
 *
 * ConnectionConfig is a value object that:
 * - Encapsulates all connection parameters
 * - Provides default values for optional settings
 * - Parses configuration from arrays (Laravel config)
 * - Generates CONNECT command options
 * ============================================================================
 */

use LaravelNats\Core\Connection\ConnectionConfig;

describe('constructor defaults', function (): void {
    it('has sensible defaults', function (): void {
        $config = new ConnectionConfig();

        expect($config->getHost())->toBe('localhost')
            ->and($config->getPort())->toBe(4222)
            ->and($config->getUser())->toBeNull()
            ->and($config->getPassword())->toBeNull()
            ->and($config->getToken())->toBeNull()
            ->and($config->getTimeout())->toBe(5.0)
            ->and($config->isTlsEnabled())->toBeFalse()
            ->and($config->getClientName())->toBe('laravel-nats')
            ->and($config->isVerbose())->toBeFalse()
            ->and($config->isPedantic())->toBeFalse();
    });
});

describe('fromArray', function (): void {
    it('parses basic configuration', function (): void {
        $config = ConnectionConfig::fromArray([
            'host' => 'nats.example.com',
            'port' => 4223,
            'timeout' => 10.0,
        ]);

        expect($config->getHost())->toBe('nats.example.com')
            ->and($config->getPort())->toBe(4223)
            ->and($config->getTimeout())->toBe(10.0);
    });

    it('parses URL format', function (): void {
        $config = ConnectionConfig::fromArray([
            'url' => 'nats://nats.example.com:4223',
        ]);

        expect($config->getHost())->toBe('nats.example.com')
            ->and($config->getPort())->toBe(4223);
    });

    it('parses inline auth', function (): void {
        $config = ConnectionConfig::fromArray([
            'user' => 'admin',
            'password' => 'secret',
        ]);

        expect($config->getUser())->toBe('admin')
            ->and($config->getPassword())->toBe('secret');
    });

    it('parses nested auth', function (): void {
        $config = ConnectionConfig::fromArray([
            'auth' => [
                'user' => 'admin',
                'password' => 'secret',
            ],
        ]);

        expect($config->getUser())->toBe('admin')
            ->and($config->getPassword())->toBe('secret');
    });

    it('parses token auth', function (): void {
        $config = ConnectionConfig::fromArray([
            'token' => 'my-secret-token',
        ]);

        expect($config->getToken())->toBe('my-secret-token');
    });

    it('parses TLS options', function (): void {
        $config = ConnectionConfig::fromArray([
            'tls' => [
                'enabled' => true,
                'options' => [
                    'verify_peer' => true,
                    'cafile' => '/path/to/ca.pem',
                ],
            ],
        ]);

        expect($config->isTlsEnabled())->toBeTrue()
            ->and($config->getTlsOptions())->toBe([
                'verify_peer' => true,
                'cafile' => '/path/to/ca.pem',
            ]);
    });

    it('parses boolean TLS', function (): void {
        $config = ConnectionConfig::fromArray([
            'tls' => true,
        ]);

        expect($config->isTlsEnabled())->toBeTrue();
    });

    it('parses ping configuration', function (): void {
        $config = ConnectionConfig::fromArray([
            'ping_interval' => 60.0,
            'max_pings_out' => 3,
        ]);

        expect($config->getPingInterval())->toBe(60.0)
            ->and($config->getMaxPingsOut())->toBe(3);
    });
});

describe('local factory', function (): void {
    it('creates localhost config', function (): void {
        $config = ConnectionConfig::local();

        expect($config->getHost())->toBe('localhost')
            ->and($config->getPort())->toBe(4222);
    });
});

describe('helper methods', function (): void {
    it('returns full address', function (): void {
        $config = new ConnectionConfig(host: 'nats.example.com', port: 4223);

        expect($config->getAddress())->toBe('nats.example.com:4223');
    });

    it('detects user/pass auth', function (): void {
        $config = new ConnectionConfig(user: 'admin', password: 'secret');

        expect($config->hasAuth())->toBeTrue();
    });

    it('detects token auth', function (): void {
        $config = new ConnectionConfig(token: 'my-token');

        expect($config->hasAuth())->toBeTrue();
    });

    it('detects no auth', function (): void {
        $config = new ConnectionConfig();

        expect($config->hasAuth())->toBeFalse();
    });
});

describe('toConnectArray', function (): void {
    it('includes basic options', function (): void {
        $config = new ConnectionConfig(clientName: 'my-app', verbose: true);

        $connectArray = $config->toConnectArray();

        expect($connectArray)->toHaveKey('name', 'my-app')
            ->and($connectArray)->toHaveKey('verbose', true)
            ->and($connectArray)->toHaveKey('lang', 'php')
            ->and($connectArray)->toHaveKey('protocol', 1);
    });

    it('includes user/pass when configured', function (): void {
        $config = new ConnectionConfig(user: 'admin', password: 'secret');

        $connectArray = $config->toConnectArray();

        expect($connectArray)->toHaveKey('user', 'admin')
            ->and($connectArray)->toHaveKey('pass', 'secret');
    });

    it('includes token when configured', function (): void {
        $config = new ConnectionConfig(token: 'my-token');

        $connectArray = $config->toConnectArray();

        expect($connectArray)->toHaveKey('auth_token', 'my-token');
    });

    it('includes TLS flag when enabled', function (): void {
        $config = new ConnectionConfig(tlsEnabled: true);

        $connectArray = $config->toConnectArray();

        expect($connectArray)->toHaveKey('tls_required', true);
    });
});
