<?php

declare(strict_types=1);

namespace LaravelNats\Message;

use Basis\Nats\Message\Payload;

/**
 * HPUB payload that supports multiple lines per header name (NATS ADR-4).
 *
 * @see https://docs.nats.io/reference/reference-protocols/nats-protocol#hpub
 */
final class MultiHeaderPayload extends Payload
{
    /**
     * @param array<string, list<string>> $namedValues Each header name maps to one or more values (separate HPUB lines).
     */
    public function __construct(
        string $body,
        private array $namedValues = [],
        ?string $subject = null,
        ?int $timestampNanos = null,
    ) {
        parent::__construct($body, [], $subject, $timestampNanos);
    }

    public function render(): string
    {
        if ($this->namedValues === []) {
            return parent::render();
        }

        $headers = "NATS/1.0\r\n";
        foreach ($this->namedValues as $name => $values) {
            foreach ($values as $value) {
                $headers .= "{$name}: {$value}\r\n";
            }
        }
        $headers .= "\r\n";

        $crc = strlen($headers) . ' ' . strlen($headers . $this->body);

        return $crc . "\r\n" . $headers . $this->body;
    }
}
