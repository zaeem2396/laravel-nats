<?php

declare(strict_types=1);

/**
 * ============================================================================
 * JSON SERIALIZER UNIT TESTS
 * ============================================================================
 *
 * These tests verify the JSON serializer correctly handles serialization
 * and deserialization of various PHP data types.
 *
 * The JSON serializer is the default for NATS messages because:
 * - Cross-language compatibility (Go, Node, Java can read it)
 * - Human-readable for debugging
 * - Widely supported
 * ============================================================================
 */

use LaravelNats\Core\Serialization\JsonSerializer;

beforeEach(function (): void {
    $this->serializer = new JsonSerializer();
});

describe('serialize', function (): void {
    it('serializes arrays', function (): void {
        $result = $this->serializer->serialize(['id' => 123, 'name' => 'Test']);

        expect($result)->toBe('{"id":123,"name":"Test"}');
    });

    it('serializes nested arrays', function (): void {
        $result = $this->serializer->serialize([
            'user' => [
                'id' => 1,
                'profile' => [
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        expect(json_decode($result, true))->toBe([
            'user' => [
                'id' => 1,
                'profile' => [
                    'email' => 'test@example.com',
                ],
            ],
        ]);
    });

    it('serializes integers', function (): void {
        $result = $this->serializer->serialize(42);

        expect($result)->toBe('42');
    });

    it('serializes floats preserving decimals', function (): void {
        $result = $this->serializer->serialize(3.14);

        expect($result)->toBe('3.14');
    });

    it('preserves 1.0 as float', function (): void {
        // JSON_PRESERVE_ZERO_FRACTION keeps 1.0 as 1.0, not 1
        $result = $this->serializer->serialize(1.0);

        expect($result)->toBe('1.0');
    });

    it('serializes booleans', function (): void {
        expect($this->serializer->serialize(true))->toBe('true');
        expect($this->serializer->serialize(false))->toBe('false');
    });

    it('serializes null', function (): void {
        $result = $this->serializer->serialize(null);

        expect($result)->toBe('null');
    });

    it('returns strings as-is', function (): void {
        // Strings are returned without additional encoding
        $result = $this->serializer->serialize('already a string');

        expect($result)->toBe('already a string');
    });

    it('handles unicode characters', function (): void {
        $result = $this->serializer->serialize(['emoji' => '🚀', 'text' => '日本語']);

        // JSON_UNESCAPED_UNICODE preserves unicode characters
        expect($result)->toContain('🚀')
            ->and($result)->toContain('日本語');
    });

    it('serializes objects with public properties', function (): void {
        $obj = new stdClass();
        $obj->name = 'Test';
        $obj->value = 100;

        $result = $this->serializer->serialize($obj);

        expect($result)->toBe('{"name":"Test","value":100}');
    });
});

describe('deserialize', function (): void {
    it('deserializes to associative array by default', function (): void {
        $result = $this->serializer->deserialize('{"id":123,"name":"Test"}');

        expect($result)->toBe(['id' => 123, 'name' => 'Test']);
    });

    it('deserializes integers', function (): void {
        $result = $this->serializer->deserialize('42');

        expect($result)->toBe(42);
    });

    it('deserializes floats', function (): void {
        $result = $this->serializer->deserialize('3.14');

        expect($result)->toBe(3.14);
    });

    it('deserializes booleans', function (): void {
        expect($this->serializer->deserialize('true'))->toBe(true);
        expect($this->serializer->deserialize('false'))->toBe(false);
    });

    it('deserializes null', function (): void {
        $result = $this->serializer->deserialize('null');

        expect($result)->toBeNull();
    });

    it('returns empty string as null', function (): void {
        $result = $this->serializer->deserialize('');

        expect($result)->toBeNull();
    });

    it('handles nested structures', function (): void {
        $json = '{"users":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}';

        $result = $this->serializer->deserialize($json);

        expect($result['users'])->toHaveCount(2)
            ->and($result['users'][0]['name'])->toBe('Alice');
    });

    it('returns invalid JSON as raw string', function (): void {
        // Invalid JSON returns the raw string (graceful degradation)
        $result = $this->serializer->deserialize('not valid json');

        expect($result)->toBe('not valid json');
    });
});

describe('content type', function (): void {
    it('returns application/json', function (): void {
        expect($this->serializer->getContentType())->toBe('application/json');
    });
});

describe('round trip', function (): void {
    it('preserves data through serialize/deserialize', function (): void {
        $original = [
            'id' => 123,
            'name' => 'Test Order',
            'total' => 99.99,
            'items' => [
                ['sku' => 'A001', 'qty' => 2],
                ['sku' => 'B002', 'qty' => 1],
            ],
            'active' => true,
            'metadata' => null,
        ];

        $serialized = $this->serializer->serialize($original);
        $deserialized = $this->serializer->deserialize($serialized);

        expect($deserialized)->toBe($original);
    });
});
