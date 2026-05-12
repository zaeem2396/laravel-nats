<?php

declare(strict_types=1);

namespace LaravelNats\Support;

use InvalidArgumentException;

/**
 * Fluent helper for building NatsV2 publish headers, including repeated HPUB lines.
 */
final class NatsHeaderBag
{
    /**
     * @param array<string, mixed> $headers
     */
    private function __construct(
        private array $headers = [],
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $headers
     */
    public static function from(array $headers): self
    {
        return new self($headers);
    }

    public function with(string $name, string|int|float|bool|null $value): self
    {
        $copy = clone $this;
        $copy->headers[$name] = $value;

        return $copy;
    }

    /**
     * @param list<string|int|float|bool|null> $values
     */
    public function withMany(string $name, array $values): self
    {
        $copy = clone $this;
        $copy->headers[$name] = $values;

        return $copy;
    }

    public function withRequestId(string $value, string $name = CorrelationHeaders::DEFAULT_REQUEST_ID): self
    {
        return $this->with($name, $value);
    }

    public function withCorrelationId(string $value, string $name = CorrelationHeaders::DEFAULT_CORRELATION_ID): self
    {
        return $this->with($name, $value);
    }

    public function withTraceParent(string $value, string $name = TraceContextHeaders::TRACEPARENT): self
    {
        if (! TraceContextHeaders::isValidTraceParent($value)) {
            throw new InvalidArgumentException('traceparent must match the W3C trace context format.');
        }

        return $this->with($name, $value);
    }

    public function withTraceState(string $value, string $name = TraceContextHeaders::TRACESTATE): self
    {
        return $this->with($name, $value);
    }

    public function withIdempotencyKey(string $value, string $name = IdempotencyHeaders::DEFAULT_HEADER): self
    {
        return $this->with($name, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, list<string>>
     */
    public function toNamedValues(): array
    {
        return PublishHeaderNormalizer::toNamedValues($this->headers);
    }
}
