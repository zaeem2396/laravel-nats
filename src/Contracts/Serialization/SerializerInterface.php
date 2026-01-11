<?php

declare(strict_types=1);

namespace LaravelNats\Contracts\Serialization;

/**
 * SerializerInterface defines the contract for message serialization.
 *
 * Serializers convert PHP data structures to/from string format for
 * transmission over NATS. The default implementation uses JSON, but
 * custom serializers can be created for other formats (MessagePack, Protobuf, etc.).
 *
 * Serializers should be stateless and thread-safe.
 */
interface SerializerInterface
{
    /**
     * Serialize data to a string.
     *
     * @param mixed $data The data to serialize
     *
     * @throws \LaravelNats\Exceptions\SerializationException When serialization fails
     *
     * @return string The serialized string
     */
    public function serialize(mixed $data): string;

    /**
     * Deserialize a string back to data.
     *
     * @param string $data The serialized string
     *
     * @throws \LaravelNats\Exceptions\SerializationException When deserialization fails
     *
     * @return mixed The deserialized data
     */
    public function deserialize(string $data): mixed;

    /**
     * Get the content type for this serializer.
     *
     * This is used in message headers to indicate the format.
     * Examples: "application/json", "application/x-msgpack"
     *
     * @return string The MIME content type
     */
    public function getContentType(): string;
}
