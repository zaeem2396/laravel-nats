<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Adds `nats_request_id` and `nats_correlation_id` to {@see Log::shareContext()} when available (Laravel 11+).
 *
 * Register in `nats_basis.subscriber.middleware` if desired.
 */
final class CorrelationLogInboundMiddleware implements InboundMiddleware
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function handle(InboundMessage $message, Closure $next): void
    {
        $context = array_filter([
            'nats_request_id' => $message->requestId(),
            'nats_correlation_id' => $message->correlationId(),
        ], static fn (?string $v): bool => $v !== null && $v !== '');

        if ($context !== []) {
            $log = $this->app->make('log');
            if (is_object($log) && method_exists($log, 'shareContext')) {
                $log->shareContext($context);
            }
        }

        $next();
    }
}
