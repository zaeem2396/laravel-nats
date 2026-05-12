# Client protocol features (v2 + legacy)

This page summarizes **connection and messaging behaviors** exposed or documented by **laravel-nats**, including items that map to NATS protocol concepts (echo, headers, clustering seeds, request/reply, graceful shutdown).

## NatsV2 / `basis-company/nats` (recommended)

| Topic | Behavior in this package |
|--------|---------------------------|
| **Automatic reconnect** | Handled inside **basis-company/nats** when `nats_basis.connections.*.reconnect` is true (default). The wrapper does not add a second reconnect loop. |
| **Bootstrap failover / INFO `connect_urls`** | Optional `servers` list (or `NATS_BASIS_SERVERS`) plus `merge_info_connect_urls` (`NATS_MERGE_INFO_CONNECT_URLS`): `ConnectionManager` verifies the chosen server with a **ping** before returning the client (including a single host/port). With multiple seeds it tries in order until one answers. When merge is enabled, an additional ping may run to read INFO `connect_urls` into the pool (see `config/nats_basis.php`). |
| **Synchronous request + no responders** | `NatsV2::request($subject, $payload, $timeout, $connection?)` waits for a reply; if the reply payload has header **Status-Code `503`**, `LaravelNats\Exceptions\NatsNoRespondersException` is thrown. Timeouts raise `LaravelNats\Exceptions\NatsRequestTimeoutException`. Enable server/account **no responders** behavior per NATS docs for best results. |
| **Graceful shutdown helper** | `NatsV2::drainConnection($seconds, $connection?)` runs `process()` for a bounded time, then **disconnects**. This is an **application-level** drain (flush inbound work briefly), not a separate wire opcode. |
| **Multi-value HPUB headers** | Publish with `headers` as `list<string>` per key (e.g. `['X-Trace' => ['a', 'b']]`) to emit repeated header lines per ADR-4. Implementation: `LaravelNats\Message\MultiHeaderPayload`. |
| **Header helpers + trace context (v2.7)** | `LaravelNats\Support\NatsHeaderBag` builds common headers and repeated values; optional `NATS_TRACE_CONTEXT_INJECT` copies valid W3C `traceparent` / `tracestate` from the active request. See [TRACE_CONTEXT.md](TRACE_CONTEXT.md). |
| **Connection selection (v2.7)** | `NATS_CONNECTION_SUBJECT_PREFIXES` maps subject prefixes to named connections, and `NatsV2::selectConnection()` exposes the selection result. Explicit connection arguments still win. See [CONNECTION_SELECTION.md](CONNECTION_SELECTION.md). |
| **Outbox recipe (v2.7)** | `NatsOutboxDispatcher` and `NatsV2::dispatchOutbox()` drain an app-provided `NatsOutboxStoreContract`; storage stays in your application. See [OUTBOX.md](OUTBOX.md). |
| **Config validation (v2.6)** | `php artisan nats:v2:config:validate` and optional `NATS_BASIS_VALIDATE_CONFIG` run `NatsBasisConfigurationValidator` before traffic. See [SECURITY.md](SECURITY.md). |
| **CONNECT `echo` / `no_responders`** | The basis client’s CONNECT JSON is built by **basis-company/nats**; this package does not patch those flags today. Use **legacy** `config/nats.php` `echo` if you need an explicit CONNECT echo toggle on the native client. |

## Legacy `LaravelNats\Core\Client`

| Topic | Behavior |
|--------|-----------|
| **Echo** | Configurable via `config/nats.php` **`echo`** / **`NATS_ECHO`** (default true). Passed through `ConnectionConfig::toConnectArray()`. |
| **Multi-value HPUB headers** | `CommandBuilder::publishWithHeaders` accepts **`array<string, string\|list<string>>`**. |
| **Reconnect** | `NatsManager::reconnect()` is **manual** (disconnect + new connection), not an automatic loop. |

## See also

- [GUIDE.md](GUIDE.md) · [MIGRATION.md](MIGRATION.md) · [SECURITY.md](SECURITY.md) · [IDEMPOTENCY.md](IDEMPOTENCY.md) · [TRACE_CONTEXT.md](TRACE_CONTEXT.md) · [CONNECTION_SELECTION.md](CONNECTION_SELECTION.md) · [OUTBOX.md](OUTBOX.md)
