# Idempotency (v2.4, package 1.6.0+)

Optional **idempotency keys** let subscribers skip duplicate work when the same logical event is delivered more than once (retries, at-least-once consumers, or producer retries).

## Publish: envelope + header

1. **Payload field** — include `idempotency_key` next to your application fields when calling **`NatsV2::publish()`** or **`NatsV2::jetStreamPublish()`** (with envelope). The publisher **strips** it from `data` and places it on the envelope root and as an HPUB header (default **`Nats-Idempotency-Key`**).

```php
use LaravelNats\Laravel\Facades\NatsV2;

NatsV2::publish('payments.captured', [
    'idempotency_key' => 'pay_evt_' . $paymentId,
    'payment_id' => $paymentId,
    'amount' => 1000,
]);
```

2. **Header only** — you may pass the same name in the `$headers` argument; it wins over auto-merge from the payload (existing header takes precedence, same rule as correlation headers).

3. **Envelope only** — consumers reading **`InboundMessage::envelopePayload()`** see optional **`idempotency_key`** on the root object.

## Subscribe: middleware

1. Use a **shared cache** store (e.g. **Redis**) for `cache` in production so all workers share reservations.

2. Set in **`.env`**:

```env
NATS_IDEMPOTENCY_ENABLED=true
NATS_IDEMPOTENCY_TTL=86400
# Optional: NATS_IDEMPOTENCY_CACHE_STORE=redis
# NATS_IDEMPOTENCY_HEADER=Nats-Idempotency-Key
```

3. Register **`LaravelNats\Subscriber\Middleware\IdempotencyInboundMiddleware`** in **`config/nats_basis.php`** → **`subscriber.middleware`**.

When enabled, the middleware:

- Reads **`InboundMessage::idempotencyKey()`** (header first, then envelope).
- If missing, runs your handler normally.
- If present, **`Cache::add()`**-style **reserve** runs; on duplicate within TTL, the handler is **not** invoked.

## Pluggable store

The default binding is **`LaravelNats\Idempotency\CacheIdempotencyStore`** using **`nats_basis.idempotency.store`** = **`cache`**. To use a custom backend, rebind **`LaravelNats\Idempotency\Contracts\IdempotencyStoreContract`** in your **`AppServiceProvider`** (your implementation must make **`reserve()`** atomic for your deployment).

## Key generation patterns

| Pattern | When to use |
|--------|-------------|
| **Stable business id** | `order:{id}:invoice_created` — same event redelivered maps to one key. |
| **Upstream id** | Pass through gateway request id or payment processor idempotency token. |
| **UUID per intent** | Client generates UUID once per user action; resubmits same UUID on retry. |

Avoid keys longer than your cache backend allows; the package hashes keys for the cache entry name.

**Do not** use only **`envelope id`** (message UUID) for deduplication — each publish gets a new **`id`**.

## Configuration reference

| Config key | Env | Purpose |
|------------|-----|---------|
| `nats_basis.idempotency.enabled` | `NATS_IDEMPOTENCY_ENABLED` | Master switch for middleware behaviour. |
| `nats_basis.idempotency.store` | `NATS_IDEMPOTENCY_STORE` | Only **`cache`** is built-in; else bind the contract manually. |
| `nats_basis.idempotency.cache_store` | `NATS_IDEMPOTENCY_CACHE_STORE` | Laravel cache store name (null = default). |
| `nats_basis.idempotency.cache_key_prefix` | `NATS_IDEMPOTENCY_CACHE_PREFIX` | Prefix for hashed cache keys. |
| `nats_basis.idempotency.ttl_seconds` | `NATS_IDEMPOTENCY_TTL` | TTL for reservations (seconds). |
| `nats_basis.idempotency.header_name` | `NATS_IDEMPOTENCY_HEADER` | HPUB header name (publish + read). |

## See also

- [SUBSCRIBER.md](SUBSCRIBER.md) — middleware pipeline
- [GUIDE.md](GUIDE.md) — envelope shape
- [CORRELATION.md](CORRELATION.md) — other NATS headers
