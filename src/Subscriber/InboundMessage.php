<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber;

use Basis\Nats\Message\Payload;
use LaravelNats\Support\CorrelationHeaders;
use LaravelNats\Support\IdempotencyHeaders;
use LaravelNats\Support\NatsHeaders;
use LaravelNats\Support\TraceContextHeaders;

/**
 * Inbound NATS message for the v2 subscriber stack (decoupled from basis {@see Payload}).
 */
final class InboundMessage
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers,
        public readonly ?string $replyTo,
    ) {
    }

    public static function fromPayload(Payload $payload, ?string $replyTo): self
    {
        return new self(
            subject: $payload->subject ?? '',
            body: $payload->body,
            headers: $payload->headers,
            replyTo: $replyTo,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodedJson(): ?array
    {
        if ($this->body === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $out */
            $out = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);

            return $out;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * When the body matches the v2 publisher envelope, returns the decoded root array.
     *
     * @return array{id: string, type: string, version: string, data: mixed, idempotency_key?: string}|null
     */
    public function envelopePayload(): ?array
    {
        $d = $this->decodedJson();
        if (! is_array($d)) {
            return null;
        }
        if (! isset($d['id'], $d['type'], $d['version'], $d['data'])) {
            return null;
        }

        /** @var array{id: string, type: string, version: string, data: mixed} */
        return $d;
    }

    /**
     * First matching header (case-insensitive) for the configured request id name.
     */
    public function requestId(?string $headerName = null): ?string
    {
        return $this->headerIgnoringCase($headerName ?? CorrelationHeaders::DEFAULT_REQUEST_ID);
    }

    /**
     * First matching header (case-insensitive) for the configured correlation id name.
     */
    public function correlationId(?string $headerName = null): ?string
    {
        return $this->headerIgnoringCase($headerName ?? CorrelationHeaders::DEFAULT_CORRELATION_ID);
    }

    /**
     * Idempotency key from NATS headers first, then optional {@see envelopePayload()} `idempotency_key`.
     */
    public function idempotencyKey(?string $headerName = null): ?string
    {
        $name = $headerName ?? IdempotencyHeaders::DEFAULT_HEADER;
        $fromHeader = NatsHeaders::get($this->headers, $name);
        if ($fromHeader !== null && $fromHeader !== '') {
            return $fromHeader;
        }

        $env = $this->envelopePayload();
        if ($env === null) {
            return null;
        }

        $k = $env['idempotency_key'] ?? null;
        if (! is_string($k)) {
            return null;
        }

        $trimmed = trim($k);

        return $trimmed !== '' ? $trimmed : null;
    }

    public function traceParent(?string $headerName = null): ?string
    {
        $value = NatsHeaders::get($this->headers, $headerName ?? TraceContextHeaders::TRACEPARENT);

        return is_string($value) && TraceContextHeaders::isValidTraceParent($value) ? $value : null;
    }

    public function traceState(?string $headerName = null): ?string
    {
        return NatsHeaders::get($this->headers, $headerName ?? TraceContextHeaders::TRACESTATE);
    }

    private function headerIgnoringCase(string $name): ?string
    {
        return NatsHeaders::get($this->headers, $name);
    }
}
