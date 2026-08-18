<?php

declare(strict_types=1);

namespace LaravelNats\Support;

/**
 * Narrow mixed config/JSON values to scalars and arrays without PHPStan level-9 casts.
 *
 * Used by {@see \LaravelNats\Core\Connection\ConnectionConfig::fromArray()} and other
 * array-shaped inputs where PHP cannot prove value types.
 */
final class MixedTypes
{
    public static function string(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value instanceof \Stringable) {
            $string = self::string($value);
            if ($string === '') {
                return null;
            }

            return $string;
        }

        return null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_bool($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || (is_string($value) && is_numeric($value))) {
            return self::int($value);
        }

        return null;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value) || is_bool($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $out[$key] = $item;
            } elseif (is_int($key)) {
                $out[(string) $key] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values($value);
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            } elseif (is_int($item) || is_float($item)) {
                $out[] = (string) $item;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $int = self::nullableInt($item);
            if ($int !== null) {
                $out[] = $int;
            }
        }

        return $out;
    }
}
