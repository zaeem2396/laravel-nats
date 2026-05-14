<?php

declare(strict_types=1);

namespace LaravelNats\Support;

/**
 * Small case-insensitive helpers for NATS/HPUB header arrays.
 */
final class NatsHeaders
{
    /**
     * @param array<string, string> $headers
     */
    public static function has(array $headers, string $name): bool
    {
        return self::findKey($headers, $name) !== null;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function get(array $headers, string $name): ?string
    {
        $key = self::findKey($headers, $name);
        if ($key === null) {
            return null;
        }

        $value = $headers[$key];

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public static function putIfMissing(array $headers, string $name, ?string $value): array
    {
        if ($name === '' || $value === null || $value === '' || self::has($headers, $name)) {
            return $headers;
        }

        $headers[$name] = $value;

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function findKey(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $_) {
            if (strcasecmp((string) $key, $name) === 0) {
                return (string) $key;
            }
        }

        return null;
    }
}
