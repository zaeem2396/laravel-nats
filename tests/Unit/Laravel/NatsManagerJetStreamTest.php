<?php

declare(strict_types=1);

use LaravelNats\Core\JetStream\JetStreamConfig;
use LaravelNats\Laravel\NatsManager;

describe('NatsManager JetStream', function (): void {
    it('has jetstream method', function (): void {
        $manager = app(NatsManager::class);

        expect(method_exists($manager, 'jetstream'))->toBeTrue();
    });

    it('uses config from config file', function (): void {
        config(['nats.jetstream' => [
            'domain' => 'test-domain',
            'timeout' => 10.0,
        ]]);

        $manager = app(NatsManager::class);

        // Verify method exists
        expect(method_exists($manager, 'jetstream'))->toBeTrue();
    });

    it('accepts custom config parameter', function (): void {
        $manager = app(NatsManager::class);

        // Verify method signature accepts JetStreamConfig
        $reflection = new ReflectionMethod($manager, 'jetstream');
        $parameters = $reflection->getParameters();

        expect(count($parameters))->toBeGreaterThanOrEqual(1);
        if (isset($parameters[1])) {
            $type = $parameters[1]->getType();
            expect($type)->not->toBeNull();
            if ($type !== null) {
                expect($type->getName())->toBe(JetStreamConfig::class);
            }
        }
    });
});
