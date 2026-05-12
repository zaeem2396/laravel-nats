<?php

declare(strict_types=1);

namespace LaravelNats\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;

/**
 * Lightweight W3C trace context propagation for NatsV2 publishes.
 *
 * @see https://www.w3.org/TR/trace-context/
 */
final class TraceContextHeaders
{
    public const TRACEPARENT = 'traceparent';

    public const TRACESTATE = 'tracestate';

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public static function mergeForPublish(Repository $config, array $headers): array
    {
        if (! filter_var($config->get('nats_basis.trace_context.inject_on_publish', false), FILTER_VALIDATE_BOOL)) {
            return $headers;
        }

        if (! function_exists('app') || ! app()->bound('request')) {
            return $headers;
        }

        $request = request();
        if (! $request instanceof Request) {
            return $headers;
        }

        $traceparentHeader = self::headerName($config, 'traceparent_header', self::TRACEPARENT);
        $tracestateHeader = self::headerName($config, 'tracestate_header', self::TRACESTATE);

        $traceparent = $request->headers->get($traceparentHeader);
        $tracestate = $request->headers->get($tracestateHeader);

        $out = $headers;
        if (is_string($traceparent) && self::isValidTraceParent($traceparent)) {
            $out = NatsHeaders::putIfMissing($out, $traceparentHeader, $traceparent);
        }
        if (is_string($tracestate) && trim($tracestate) !== '') {
            $out = NatsHeaders::putIfMissing($out, $tracestateHeader, $tracestate);
        }

        return $out;
    }

    public static function isValidTraceParent(string $value): bool
    {
        return preg_match('/^[\da-f]{2}-[\da-f]{32}-[\da-f]{16}-[\da-f]{2}$/', strtolower($value)) === 1
            && substr($value, 3, 32) !== '00000000000000000000000000000000'
            && substr($value, 36, 16) !== '0000000000000000';
    }

    private static function headerName(Repository $config, string $key, string $default): string
    {
        $name = $config->get('nats_basis.trace_context.' . $key, $default);

        return is_string($name) && $name !== '' ? $name : $default;
    }
}
