<?php

declare(strict_types=1);

namespace LaravelNats\Core\Serialization;

use LaravelNats\Contracts\Serialization\SerializerInterface;
use LaravelNats\Exceptions\SerializationException;

/**
 * PhpSerializer uses PHP's native serialize/unserialize.
 *
 * This serializer is useful when:
 * - Communicating only with PHP services
 * - Need to preserve PHP-specific types (objects, closures)
 * - Maximum fidelity is required
 *
 * WARNING: PHP serialization can be a security risk when deserializing
 * untrusted data, as it can instantiate arbitrary objects. Only use
 * this serializer when you trust the message source.
 *
 * For cross-language communication, use JsonSerializer instead.
 */
final class PhpSerializer implements SerializerInterface
{
    /**
     * Create a new PHP serializer.
     *
     * @param array<class-string> $allowedClasses Classes allowed during unserialization, or empty for all
     */
    public function __construct(
        private readonly array $allowedClasses = [],
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(mixed $data): string
    {
        try {
            return serialize($data);
        } catch (\Throwable $e) {
            throw SerializationException::serializeFailed($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize(string $data): mixed
    {
        if ($data === '') {
            return null;
        }

        $options = [];

        if ($this->allowedClasses !== []) {
            $options['allowed_classes'] = $this->allowedClasses;
        }

        try {
            $result = @unserialize($data, $options);

            if ($result === false && $data !== serialize(false)) {
                throw SerializationException::deserializeFailed('Invalid serialized data');
            }

            return $result;
        } catch (\Throwable $e) {
            throw SerializationException::deserializeFailed($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        return 'application/x-php-serialized';
    }
}
