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

        return NatsHeaders::putIfMissing($headers, $name, $idempotencyKey);
    }
}
