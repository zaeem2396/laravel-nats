# Observability (v2.5, package 1.4.0+)

This release adds **optional publish metrics** (`NatsMetricsContract`), **HTTP correlation context** for application logs, **redaction helpers** for envelope `data` when logging inbound messages, and a **`nats:ping`** Artisan command for readiness checks. Pair redaction and metrics with the **v2.6** TLS and ACL guidance in [SECURITY.md](SECURITY.md) for production rollouts.

**Related:** [SECURITY.md](SECURITY.md) (1.5.0+) for boot validation, TLS production checks, and optional subject ACL alongside operational hardening.

## Metrics

When **`nats_basis.observability.metrics_enabled`** is `true` (`NATS_OBSERVABILITY_METRICS`), `NatsPublisher` increments:

- **`laravel_nats.publish.total`** — labels: `connection` (basis connection name), `outcome` (`success` | `failure`).

Optional latency histogram (off by default):

- **`laravel_nats.publish.latency_ms`** — when **`nats_basis.observability.publish_latency_histogram`** is `true` (`NATS_OBSERVABILITY_PUBLISH_LATENCY_MS`), labels: `connection`.

The default container binding is **`NullNatsMetrics`** (no overhead). **Rebind** `LaravelNats\Observability\Contracts\NatsMetricsContract` to your implementation (Prometheus client, OpenTelemetry meter, StatsD, etc.). Keep label **cardinality low**; do not use raw subjects as label values.

### Example: OpenTelemetry-style bridge (sketch)

```php
use LaravelNats\Observability\Contracts\NatsMetricsContract;

$this->app->singleton(NatsMetricsContract::class, function () {
    return new class implements NatsMetricsContract {
        public function incrementCounter(string $name, array $labels = [], int $delta = 1): void
        {
            // $meter->createCounter($name)->add($delta, $labels);
        }

        public function observeHistogram(string $name, float $value, array $labels = []): void
        {
            // $histogram->record($value, $labels);
        }
    };
});
```

For local inspection, bind **`LaravelNats\Observability\InMemoryNatsMetrics`** in a non-production provider.

## Correlation IDs in application logs

**`LaravelNats\Observability\CorrelationLogContext::fromRequest($request, $config)`** returns `nats_request_id` and `nats_correlation_id` using the same header names as **`nats_basis.correlation`**. Use with Laravel’s **`Log::withContext()`** in HTTP middleware so downstream `NatsV2::publish` logs and subscriber middleware share identifiers. See [CORRELATION.md](CORRELATION.md).

## Redaction for envelope `data`

**`EnvelopeDataRedactor::redact(array $data, array $keySubstrings)`** replaces values whose **keys** contain any configured substring (case-insensitive). Defaults are set in **`nats_basis.observability.redact_key_substrings`** (override with comma-separated **`NATS_REDACT_KEY_SUBSTRINGS`**).

**`RedactedEnvelopeLogInboundMiddleware`** logs a debug line with envelope metadata and **redacted** `data` for v2 JSON envelopes. Register it in **`nats_basis.subscriber.middleware`** when you need structured inbound logs without leaking secrets.

## Health: `nats:ping` and readiness routes

**`php artisan nats:ping`** calls **`Basis\Nats\Client::ping()`** on the default (or **`--connection=`**) basis client. Exit code **0** when the server answers PONG in time, **1** otherwise. **`--json`** prints a small JSON object for scripts.

### Optional Laravel readiness route

```php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/health/nats', function () {
    $code = Artisan::call('nats:ping', ['--json' => true]);
    $payload = json_decode(Artisan::output(), true);

    return response()->json($payload, $code === 0 ? 200 : 503);
});
```

For production, prefer a **dedicated** health port or internal-only route; keep timeouts aligned with **`nats_basis.connections.*.timeout`**.

## Configuration reference

| Config key | Env | Purpose |
|------------|-----|---------|
| `observability.metrics_enabled` | `NATS_OBSERVABILITY_METRICS` | Record publish counters (and optional histogram). |
| `observability.publish_latency_histogram` | `NATS_OBSERVABILITY_PUBLISH_LATENCY_MS` | Record `laravel_nats.publish.latency_ms` on success. |
| `observability.redact_key_substrings` | `NATS_REDACT_KEY_SUBSTRINGS` | Comma-separated substrings for key-based redaction. |

## See also

- [SECURITY.md](SECURITY.md) — boot validation, TLS, ACLs
- [CORRELATION.md](CORRELATION.md) — HPUB headers and subscriber IDs  
- [SUBSCRIBER.md](SUBSCRIBER.md) — middleware registration  
- [GUIDE.md](GUIDE.md) — config overview  
- [Roadmap](../ROADMAP_V2_NATSPHP.md) — v2.5 vs v2.6 scope
