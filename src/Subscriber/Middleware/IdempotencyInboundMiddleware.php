<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use LaravelNats\Idempotency\Contracts\IdempotencyStoreContract;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Skips the subscriber handler when an idempotency key was already processed within TTL (cache ADD).
 *
 * Enable `nats_basis.idempotency.enabled` and register this class in `nats_basis.subscriber.middleware`.
 */
final class IdempotencyInboundMiddleware implements InboundMiddleware
{
    public function __construct(
        private readonly Repository $config,
        private readonly IdempotencyStoreContract $store,
    ) {
    }

    /**
     * @param Closure(): void $next
     */
    public function handle(InboundMessage $message, Closure $next): void
    {
        if (! filter_var($this->config->get('nats_basis.idempotency.enabled', false), FILTER_VALIDATE_BOOL)) {
            $next();

            return;
        }

        $headerName = (string) $this->config->get('nats_basis.idempotency.header_name', '');
        $key = $message->idempotencyKey($headerName !== '' ? $headerName : null);
        if ($key === null || $key === '') {
            $next();

            return;
        }

        $ttl = (int) $this->config->get('nats_basis.idempotency.ttl_seconds', 86400);
        $ttl = max(1, $ttl);

        if (! $this->store->reserve($key, $ttl)) {
            return;
        }

        $next();
    }
}
