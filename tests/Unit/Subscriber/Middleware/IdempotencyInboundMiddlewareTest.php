<?php

declare(strict_types=1);

use Illuminate\Config\Repository as ConfigRepository;
use LaravelNats\Idempotency\CacheIdempotencyStore;
use LaravelNats\Subscriber\InboundMessage;
use LaravelNats\Subscriber\Middleware\IdempotencyInboundMiddleware;

describe('IdempotencyInboundMiddleware', function (): void {
    it('calls next when idempotency is disabled', function (): void {
        $config = new ConfigRepository([
            'nats_basis.idempotency.enabled' => false,
        ]);
        $repo = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $store = new CacheIdempotencyStore($repo);
        $middleware = new IdempotencyInboundMiddleware($config, $store);

        $called = false;
        $message = new InboundMessage('s', '{"id":"1","type":"s","version":"v1","data":{}}', [], null);

        $middleware->handle($message, function () use (&$called): void {
            $called = true;
        });

        expect($called)->toBeTrue();
    });

    it('skips next when key was already seen', function (): void {
        $config = new ConfigRepository([
            'nats_basis.idempotency.enabled' => true,
            'nats_basis.idempotency.header_name' => 'Nats-Idempotency-Key',
            'nats_basis.idempotency.ttl_seconds' => 3600,
        ]);
        $repo = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $store = new CacheIdempotencyStore($repo);
        $middleware = new IdempotencyInboundMiddleware($config, $store);

        $body = json_encode([
            'id' => '1',
            'type' => 's',
            'version' => 'v1',
            'data' => [],
            'idempotency_key' => 'dup',
        ]);

        $first = false;
        $middleware->handle(new InboundMessage('s', $body, [], null), function () use (&$first): void {
            $first = true;
        });
        expect($first)->toBeTrue();

        $second = false;
        $middleware->handle(new InboundMessage('s', $body, [], null), function () use (&$second): void {
            $second = true;
        });
        expect($second)->toBeFalse();
    });
});
