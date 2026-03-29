<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use LaravelNats\Observability\EnvelopeDataRedactor;
use LaravelNats\Subscriber\InboundMessage;
use Psr\Log\LoggerInterface;

/**
 * Debug-logs v2 envelope metadata with redacted `data` (see `nats_basis.observability.redact_key_substrings`).
 *
 * Register in `nats_basis.subscriber.middleware` when you need payload-shaped logs without secrets.
 */
final class RedactedEnvelopeLogInboundMiddleware implements InboundMiddleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
    ) {
    }

    public function handle(InboundMessage $message, Closure $next): void
    {
        $envelope = $message->envelopePayload();
        if ($envelope === null) {
            $next();

            return;
        }

        /** @var list<string> $fragments */
        $fragments = $this->config->get('nats_basis.observability.redact_key_substrings', []);
        if (! is_array($fragments)) {
            $fragments = [];
        }

        $data = $envelope['data'];
        if (is_array($data)) {
            $data = EnvelopeDataRedactor::redact($data, $fragments);
        }

        $this->logger->debug('NATS v2 inbound envelope', [
            'subject' => $message->subject,
            'envelope_id' => $envelope['id'],
            'envelope_type' => $envelope['type'],
            'envelope_version' => $envelope['version'],
            'data' => $data,
        ]);

        $next();
    }
}
