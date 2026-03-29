<?php

declare(strict_types=1);

namespace LaravelNats\Support;

/**
 * Standard outbound message envelope for v2 publisher (see `docs/v2/GUIDE.md`). Pairs with subscriber `InboundMessage::envelopePayload()` on consume.
 *
 * @phpstan-type EnvelopeArray array{id: string, type: string, version: string, data: array<string, mixed>, idempotency_key?: string}
 */
final class MessageEnvelope
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $version,
        private readonly array $data,
        private readonly ?string $idempotencyKey = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data Application payload (becomes "data")
     */
    public static function create(string $subject, array $data, string $version = 'v1', ?string $idempotencyKey = null): self
    {
        return new self(
            id: Uuid::v4(),
            type: $subject,
            version: $version,
            data: $data,
            idempotencyKey: $idempotencyKey !== null && $idempotencyKey !== '' ? $idempotencyKey : null,
        );
    }

    /**
     * @return array{id: string, type: string, version: string, data: array<string, mixed>, idempotency_key?: string}
     */
    public function toArray(): array
    {
        $base = [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'data' => $this->data,
        ];

        if ($this->idempotencyKey !== null && $this->idempotencyKey !== '') {
            $base['idempotency_key'] = $this->idempotencyKey;
        }

        return $base;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }
}
