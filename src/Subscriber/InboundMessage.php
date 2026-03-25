<?php

declare(strict_types=1);

namespace LaravelNats\Subscriber;

use Basis\Nats\Message\Payload;
use LaravelNats\Support\CorrelationHeaders;

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
     * @return array{id: string, type: string, version: string, data: mixed}|null
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

    private function headerIgnoringCase(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }
            if (! is_string($value) || $value === '') {
                return null;
            }

            return $value;
        }

        return null;
    }
}
