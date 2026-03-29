<?php

declare(strict_types=1);

namespace LaravelNats\Idempotency\Contracts;

/**
 * Reserves an idempotency key for {@see $ttlSeconds}. Implementations should be atomic (e.g. cache ADD).
 */
interface IdempotencyStoreContract
{
    /**
     * Try to reserve {@see $key}. Returns true if this is the first time the key was seen in the TTL window.
     */
    public function reserve(string $key, int $ttlSeconds): bool;
}
