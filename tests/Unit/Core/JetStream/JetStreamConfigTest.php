<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\JetStreamConfig;

describe('JetStreamConfig', function (): void {
    describe('constructor', function (): void {
        it('creates instance with default values', function (): void {
            $config = new JetStreamConfig();

            expect($config->getDomain())->toBeNull();
            expect($config->getTimeout())->toBe(5.0);
        });

        it('creates instance with custom values', function (): void {
            $config = new JetStreamConfig('my-domain', 10.0);

            expect($config->getDomain())->toBe('my-domain');
            expect($config->getTimeout())->toBe(10.0);
        });
    });

    describe('fromArray', function (): void {
        it('creates instance from array', function (): void {
            $config = JetStreamConfig::fromArray([
                'domain' => 'test-domain',
                'timeout' => 7.5,
            ]);

            expect($config->getDomain())->toBe('test-domain');
            expect($config->getTimeout())->toBe(7.5);
        });

        it('uses defaults for missing values', function (): void {
            $config = JetStreamConfig::fromArray([]);

            expect($config->getDomain())->toBeNull();
            expect($config->getTimeout())->toBe(5.0);
        });

        it('handles null domain', function (): void {
            $config = JetStreamConfig::fromArray(['domain' => null]);

            expect($config->getDomain())->toBeNull();
        });
    });

    describe('withDomain', function (): void {
        it('returns new instance with updated domain', function (): void {
            $config = new JetStreamConfig();
            $newConfig = $config->withDomain('new-domain');

            expect($config->getDomain())->toBeNull();
            expect($newConfig->getDomain())->toBe('new-domain');
            expect($newConfig)->not->toBe($config);
        });

        it('allows setting domain to null', function (): void {
            $config = new JetStreamConfig('existing');
            $newConfig = $config->withDomain(null);

            expect($newConfig->getDomain())->toBeNull();
        });
    });

    describe('withTimeout', function (): void {
        it('returns new instance with updated timeout', function (): void {
            $config = new JetStreamConfig();
            $newConfig = $config->withTimeout(15.0);

            expect($config->getTimeout())->toBe(5.0);
            expect($newConfig->getTimeout())->toBe(15.0);
            expect($newConfig)->not->toBe($config);
        });
    });

    describe('toArray', function (): void {
        it('converts to array', function (): void {
            $config = new JetStreamConfig('my-domain', 8.5);
            $array = $config->toArray();

            expect($array)->toBe([
                'domain' => 'my-domain',
                'timeout' => 8.5,
            ]);
        });

        it('includes null domain in array', function (): void {
            $config = new JetStreamConfig();
            $array = $config->toArray();

            expect($array['domain'])->toBeNull();
            expect($array['timeout'])->toBe(5.0);
        });
    });
});
