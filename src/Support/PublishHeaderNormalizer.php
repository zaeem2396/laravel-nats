<?php

declare(strict_types=1);

namespace LaravelNats\Support;

/**
 * Normalizes publish header arrays for {@see \LaravelNats\Message\MultiHeaderPayload}.
 */
final class PublishHeaderNormalizer
{
    /**
     * @param array<string, mixed> $headers Stringable scalars or list of strings per key
     *
     * @return array<string, list<string>>
     */
    public static function toNamedValues(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (is_array($value)) {
                $list = [];
                foreach ($value as $item) {
                    if ($item === null) {
                        continue;
                    }
                    $list[] = is_string($item) ? $item : (string) $item;
                }
                if ($list !== []) {
                    $out[$key] = $list;
                }

                continue;
            }
            if ($value === null) {
                $out[$key] = [''];

                continue;
            }
            $out[$key] = [is_string($value) ? $value : (string) $value];
        }

        return $out;
    }
}
