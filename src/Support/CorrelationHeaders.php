<?php

declare(strict_types=1);

namespace LaravelNats\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Request-ID and correlation IDs on NATS messages (HPUB headers on {@see \LaravelNats\Publisher\NatsPublisher}).
 *
 * Default header names follow common HTTP practice and a package-specific second id:
 * - {@see self::DEFAULT_REQUEST_ID} (`X-Request-Id`)
 * - {@see self::DEFAULT_CORRELATION_ID} (`Nats-Correlation-Id`) for cross-service correlation
 */
final class CorrelationHeaders
{
    public const DEFAULT_REQUEST_ID = 'X-Request-Id';

    public const DEFAULT_CORRELATION_ID = 'Nats-Correlation-Id';

    /**
     * Merge correlation headers from the active HTTP request when enabled in config.
     * Existing entries in {@see $headers} win (case-insensitive keys).
     *
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public static function mergeForPublish(Repository $config, array $headers): array
    {
        if (! filter_var($config->get('nats_basis.correlation.inject_on_publish', false), FILTER_VALIDATE_BOOL)) {
            return $headers;
        }

        if (! function_exists('app') || ! app()->bound('request')) {
            return $headers;
        }

        $request = request();
        if (! $request instanceof Request) {
            return $headers;
        }

        $requestIdHeader = (string) $config->get('nats_basis.correlation.request_id_header', self::DEFAULT_REQUEST_ID);
        $correlationHeader = (string) $config->get('nats_basis.correlation.correlation_id_header', self::DEFAULT_CORRELATION_ID);
        $generate = filter_var($config->get('nats_basis.correlation.generate_when_missing', true), FILTER_VALIDATE_BOOL);

        $out = $headers;

        if (! self::hasHeaderKey($out, $requestIdHeader)) {
            $rid = self::firstRequestHeader($request, [
                $requestIdHeader,
                'X-Request-ID',
                'Request-Id',
            ]);
            if (($rid === null || $rid === '') && $generate) {
                $rid = (string) Str::uuid();
            }
            if ($rid !== null && $rid !== '') {
                $out[$requestIdHeader] = $rid;
            }
        }

        if (! self::hasHeaderKey($out, $correlationHeader)) {
            $cid = self::firstRequestHeader($request, [
                $correlationHeader,
                'X-Correlation-Id',
                'X-Correlation-ID',
            ]);
            if ($cid !== null && $cid !== '') {
                $out[$correlationHeader] = $cid;
            }
        }

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

    /**
     * @param list<string> $names
     */
    private static function firstRequestHeader(Request $request, array $names): ?string
    {
        foreach ($names as $name) {
            $v = $request->headers->get($name);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }
}
