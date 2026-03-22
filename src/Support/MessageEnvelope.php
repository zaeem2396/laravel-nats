<?php

declare(strict_types=1);

namespace LaravelNats\Support;

/**
 * Standard outbound message envelope for v2 publisher (see `docs/v2/GUIDE.md`).
 *
 * @phpstan-type EnvelopeArray array{id: string, type: string, version: string, data: array<string, mixed>}
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
    ) {
    }

    /**
     * @param array<string, mixed> $data Application payload (becomes "data")
     */
    public static function create(string $subject, array $data, string $version = 'v1'): self
    {
        return new self(
            id: Uuid::v4(),
            type: $subject,
            version: $version,
            data: $data,
        );
    }

    /**
     * @return EnvelopeArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'data' => $this->data,
        ];
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
}
