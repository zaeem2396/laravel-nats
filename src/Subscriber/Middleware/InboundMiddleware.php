<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use LaravelNats\Subscriber\InboundMessage;

interface InboundMiddleware
{
    /**
     * @param Closure(): void $next
     */
    public function handle(InboundMessage $message, Closure $next): void;
}
