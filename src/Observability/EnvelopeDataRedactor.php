<?php

declare(strict_types=1);

namespace LaravelNats\Observability;

/**
 * Recursively redacts array values whose **keys** contain configured substrings (case-insensitive).
 *
 * Use before logging envelope `data` or similar structures.
 */
final class EnvelopeDataRedactor
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param list<string> $keySubstrings
     */
    public static function redact(mixed $value, array $keySubstrings): mixed
    {
        if ($keySubstrings === []) {
            return $value;
        }

        return self::walk($value, $keySubstrings);
    }

    /**
     * @param list<string> $keySubstrings
     */
    private static function walk(mixed $value, array $keySubstrings): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $keyStr = (string) $k;
            if ($keyStr !== '' && self::keyMatches($keyStr, $keySubstrings)) {
                $out[$k] = self::REDACTED;

                continue;
            }
            $out[$k] = self::walk($v, $keySubstrings);
        }

        return $out;
    }

    /**
     * @param list<string> $keySubstrings
     */
    private static function keyMatches(string $key, array $keySubstrings): bool
    {
        $lower = strtolower($key);
        foreach ($keySubstrings as $fragment) {
            if (! is_string($fragment) || $fragment === '') {
                continue;
            }
            if (str_contains($lower, strtolower($fragment))) {
                return true;
            }
        }

        return false;
    }
}
