# Request-ID and correlation headers (NatsV2)

This package documents a **small convention** for tracing messages across HTTP handlers, NATS publishers, and subscribers.

## Header names

| Header | Role |
|--------|------|
| **`X-Request-Id`** | Per-request identifier (often from a gateway or middleware). |
| **`Nats-Correlation-Id`** | Optional end-to-end correlation id (you may reuse `X-Request-Id` or propagate an upstream `X-Correlation-Id`). |

Names are configurable under **`nats_basis.correlation.request_id_header`** and **`correlation_id_header`**.

## Publish (HPUB)

`NatsPublisher` sends **string** headers on the wire when non-empty.

When **`nats_basis.correlation.inject_on_publish`** is **`true`**, and Laravel has a current **HTTP request**, `NatsV2::publish` merges:

- **`X-Request-Id`** from the request (`X-Request-Id`, `X-Request-ID`, or `Request-Id`), or a **UUID** when **`generate_when_missing`** is true and none is present.
- **`Nats-Correlation-Id`** from the request if present (`Nats-Correlation-Id`, `X-Correlation-Id`, or `X-Correlation-ID`).

**Explicit headers** passed to `NatsV2::publish(..., $headers)` **win** (case-insensitive); nothing is overwritten.

Env toggles: **`NATS_CORRELATION_INJECT`**, **`NATS_REQUEST_ID_HEADER`**, **`NATS_CORRELATION_ID_HEADER`**, **`NATS_CORRELATION_GENERATE_REQUEST_ID`**.

## Subscribe

`InboundMessage` exposes:

- **`requestId()`** - reads **`X-Request-Id`** (or your configured name).
- **`correlationId()`** - reads **`Nats-Correlation-Id`** (or your configured name).

Optional middleware **`CorrelationLogInboundMiddleware`** forwards those into **`Log::shareContext()`** when supported.

## JetStream note

The basis **JetStream** publish path in this package is **body-first**; use **`NatsV2::publish`** with headers for correlation on subjects captured by a stream, or embed ids in **`data`**.

## See also

- [GUIDE.md](GUIDE.md) - headers on publish
- [SUBSCRIBER.md](SUBSCRIBER.md) - inbound middleware list
- [IDEMPOTENCY.md](IDEMPOTENCY.md) - `Nats-Idempotency-Key` and subscriber deduplication
