<?php

declare(strict_types=1);

namespace LaravelNats\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Optional idempotency key on v2 publishes: envelope field {@see MessageEnvelope} and HPUB header mirror.
 *
 * Default header name aligns with package-specific NATS headers (see {@see CorrelationHeaders}).
 */
final class IdempotencyHeaders
{
    public const DEFAULT_HEADER = 'Nats-Idempotency-Key';

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public static function mergeForPublish(Repository $config, array $headers, ?string $idempotencyKey): array
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $headers;
        }

        $name = (string) $config->get('nats_basis.idempotency.header_name', self::DEFAULT_HEADER);
        if ($name === '') {
            $name = self::DEFAULT_HEADER;
        }

        if (self::hasHeaderKey($headers, $name)) {
            return $headers;
        }

        $out = $headers;
        $out[$name] = $idempotencyKey;

        return $out;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasHeaderKey(array $headers, string $name): bool
    {
        foreach ($headers as $k => $_) {
            if (strcasecmp((string) $k, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
