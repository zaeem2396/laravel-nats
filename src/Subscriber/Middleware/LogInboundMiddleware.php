<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use LaravelNats\Subscriber\InboundMessage;
use Psr\Log\LoggerInterface;

/**
 * Logs each inbound subject at debug level. Register in `nats_basis.subscriber.middleware` if desired.
 */
final class LogInboundMiddleware implements InboundMiddleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(InboundMessage $message, Closure $next): void
    {
        $this->logger->debug('NATS v2 inbound message', [
            'subject' => $message->subject,
            'reply_to' => $message->replyTo,
        ]);

        $next();
    }
}
