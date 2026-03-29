<?php

declare(strict_types=1);

namespace LaravelNats\Observability;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use LaravelNats\Support\CorrelationHeaders;

/**
 * Builds structured log context (`nats_request_id`, `nats_correlation_id`) from an HTTP request using `nats_basis.correlation` header names.
 *
 * Pair with {@see \Illuminate\Support\Facades\Log::withContext()} or subscriber {@see \LaravelNats\Subscriber\Middleware\CorrelationLogInboundMiddleware}.
 */
final class CorrelationLogContext
{
    /**
     * @return array<string, string>
     */
    public static function fromRequest(Request $request, Repository $config): array
    {
        $requestIdHeader = (string) $config->get('nats_basis.correlation.request_id_header', CorrelationHeaders::DEFAULT_REQUEST_ID);
        $correlationHeader = (string) $config->get('nats_basis.correlation.correlation_id_header', CorrelationHeaders::DEFAULT_CORRELATION_ID);

        return array_filter([
            'nats_request_id' => self::firstHeader($request, [
                $requestIdHeader,
                'X-Request-ID',
                'Request-Id',
            ]),
            'nats_correlation_id' => self::firstHeader($request, [
                $correlationHeader,
                'X-Correlation-Id',
                'X-Correlation-ID',
            ]),
        ], static fn (?string $v): bool => $v !== null && $v !== '');
    }

    /**
     * @param list<string> $names
     */
    private static function firstHeader(Request $request, array $names): ?string
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
