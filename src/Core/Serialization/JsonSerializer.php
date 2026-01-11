<?php

declare(strict_types=1);

namespace LaravelNats\Core\Serialization;

use LaravelNats\Contracts\Serialization\SerializerInterface;
use LaravelNats\Exceptions\SerializationException;

/**
 * JsonSerializer serializes data to/from JSON format.
 *
 * This is the default serializer used by the package. JSON is chosen because:
 * - Human-readable for debugging
 * - Cross-language compatibility
 * - Native browser support
 * - Good performance
 *
 * Configuration options:
 * - encode_options: JSON encoding flags (default: preserve floats, throw on error)
 * - decode_options: JSON decoding flags (default: throw on error)
 * - associative: Whether to decode objects as arrays (default: true)
 */
final class JsonSerializer implements SerializerInterface
{
    /**
     * Default JSON encode options.
     *
     * JSON_PRESERVE_ZERO_FRACTION: Keep 1.0 as 1.0, not 1
     * JSON_THROW_ON_ERROR: Throw exception on failure
     * JSON_UNESCAPED_UNICODE: Don't escape Unicode characters
     */
    private const DEFAULT_ENCODE_OPTIONS = JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;

    /**
     * Default JSON decode options.
     */
    private const DEFAULT_DECODE_OPTIONS = JSON_THROW_ON_ERROR;

    /**
     * Create a new JSON serializer.
     *
     * @param int $encodeOptions JSON encoding options
     * @param int $decodeOptions JSON decoding options
     * @param bool $associative Whether to decode to associative arrays
     * @param int<1, 2147483647> $depth Maximum nesting depth (must be >= 1)
     */
    public function __construct(
        private readonly int $encodeOptions = self::DEFAULT_ENCODE_OPTIONS,
        private readonly int $decodeOptions = self::DEFAULT_DECODE_OPTIONS,
        private readonly bool $associative = true,
        private readonly int $depth = 512,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(mixed $data): string
    {
        // Handle strings specially - they may already be JSON or need encoding
        if (is_string($data)) {
            return $data;
        }

        try {
            /** @var int<1, max> $depth */
            $depth = $this->depth;
            $result = json_encode($data, $this->encodeOptions, $depth);

            // With JSON_THROW_ON_ERROR, json_encode never returns false
            // but we cast to satisfy static analysis
            return (string) $result;
        } catch (\JsonException $e) {
            throw SerializationException::serializeFailed($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize(string $data): mixed
    {
        // Empty string handling
        if ($data === '') {
            return null;
        }

        try {
            /** @var int<1, max> $depth */
            $depth = $this->depth;

            return json_decode($data, $this->associative, $depth, $this->decodeOptions);
        } catch (\JsonException $e) {
            // If JSON decoding fails, return the raw string
            // This handles cases where the payload is plain text
            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        return 'application/json';
    }
}
