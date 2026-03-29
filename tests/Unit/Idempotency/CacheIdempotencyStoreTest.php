<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use LaravelNats\Idempotency\CacheIdempotencyStore;

describe('CacheIdempotencyStore', function (): void {
    it('returns true on first reserve and false on duplicate within ttl', function (): void {
        $repo = new Repository(new ArrayStore());
        $store = new CacheIdempotencyStore($repo, 'test:');

        expect($store->reserve('payment-1', 60))->toBeTrue()
            ->and($store->reserve('payment-1', 60))->toBeFalse();
    });

    it('allows same key after cache forget', function (): void {
        $repo = new Repository(new ArrayStore());
        $store = new CacheIdempotencyStore($repo, 'test:');

        expect($store->reserve('k', 60))->toBeTrue();
        $repo->forget('test:' . hash('sha256', 'k'));
        expect($store->reserve('k', 60))->toBeTrue();
    });

    it('treats empty key as always first', function (): void {
        $repo = new Repository(new ArrayStore());
        $store = new CacheIdempotencyStore($repo, 'test:');

        expect($store->reserve('', 60))->toBeTrue()
            ->and($store->reserve('', 60))->toBeTrue();
    });
});
