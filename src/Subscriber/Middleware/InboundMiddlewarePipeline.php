<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use LaravelNats\Subscriber\InboundMessage;

/**
 * Runs inbound middleware then the terminal handler.
 */
final class InboundMiddlewarePipeline
{
    /**
     * @param list<InboundMiddleware> $middleware
     */
    public function __construct(
        private readonly array $middleware,
    ) {
    }

    public function dispatch(InboundMessage $message, Closure $handler): void
    {
        $next = $handler;

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = function () use ($middleware, $message, $next): void {
                $middleware->handle($message, $next);
            };
        }

        $next();
    }
}
