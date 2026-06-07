<?php

declare(strict_types=1);

use LaravelNats\Core\Serialization\PhpSerializer;
use LaravelNats\Exceptions\SerializationException;

beforeEach(function (): void {
    $this->serializer = new PhpSerializer;
});

describe('serialize', function (): void {
    it('serializes arrays', function (): void {
        $raw = $this->serializer->serialize(['id' => 1, 'name' => 'test']);

        expect(unserialize($raw))->toBe(['id' => 1, 'name' => 'test']);
    });

    it('serializes false', function (): void {
        expect($this->serializer->serialize(false))->toBe(serialize(false));
    });

    it('returns php content type', function (): void {
        expect($this->serializer->getContentType())->toBe('application/x-php-serialized');
    });
});

describe('deserialize', function (): void {
    it('deserializes serialized data', function (): void {
        $data = ['ok' => true];
        $raw = serialize($data);

        expect($this->serializer->deserialize($raw))->toBe($data);
    });

    it('returns null for empty string', function (): void {
        expect($this->serializer->deserialize(''))->toBeNull();
    });

    it('deserializes false', function (): void {
        expect($this->serializer->deserialize(serialize(false)))->toBeFalse();
    });

    it('throws when serialize fails', function (): void {
        $obj = new class
        {
            public function __serialize(): array
            {
                throw new RuntimeException('cannot serialize');
            }
        };

        expect(fn () => $this->serializer->serialize($obj))
            ->toThrow(SerializationException::class);
    });

    it('respects allowed classes whitelist', function (): void {
        $serializer = new PhpSerializer([stdClass::class]);
        $obj = new stdClass;
        $obj->x = 1;
        $raw = serialize($obj);

        expect($serializer->deserialize($raw))->toBeInstanceOf(stdClass::class);
    });
});

describe('round trip', function (): void {
    it('preserves nested structures', function (): void {
        $original = ['items' => [['sku' => 'A']], 'count' => 2];
        $roundTrip = $this->serializer->deserialize($this->serializer->serialize($original));

        expect($roundTrip)->toBe($original);
    });
});
