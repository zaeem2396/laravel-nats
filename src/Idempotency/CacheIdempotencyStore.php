<?php

declare(strict_types=1);

namespace LaravelNats\Idempotency;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LaravelNats\Idempotency\Contracts\IdempotencyStoreContract;

/**
 * Laravel cache-backed idempotency (typically Redis or database cache for multi-worker safety).
 */
final class CacheIdempotencyStore implements IdempotencyStoreContract
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $keyPrefix = 'nats:idempotency:',
    ) {
    }

    public function reserve(string $key, int $ttlSeconds): bool
    {
        $key = trim($key);
        if ($key === '') {
            return true;
        }

        $ttlSeconds = max(1, $ttlSeconds);
        $cacheKey = $this->keyPrefix . hash('sha256', $key);

        return $this->cache->add($cacheKey, 1, $ttlSeconds);
    }
}
