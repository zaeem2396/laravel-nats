# Client protocol features (v2 + legacy)

This page summarizes **connection and messaging behaviors** exposed or documented by **laravel-nats**, including items that map to NATS protocol concepts (echo, headers, clustering seeds, request/reply, graceful shutdown).

## NatsV2 / `basis-company/nats` (recommended)

| Topic | Behavior in this package |
|--------|---------------------------|
| **Automatic reconnect** | Handled inside **basis-company/nats** when `nats_basis.connections.*.reconnect` is true (default). The wrapper does not add a second reconnect loop. |
| **Bootstrap failover / INFO `connect_urls`** | Optional `servers` list (or `NATS_BASIS_SERVERS`) plus `merge_info_connect_urls` (`NATS_MERGE_INFO_CONNECT_URLS`): `ConnectionManager` tries seed endpoints in order when multiple are configured; merging INFO runs a **ping** to read the pool (see config comments in `config/nats_basis.php`). |
| **Synchronous request + no responders** | `NatsV2::request($subject, $payload, $timeout, $connection?)` waits for a reply; if the reply payload has header **Status-Code `503`**, `LaravelNats\Exceptions\NatsNoRespondersException` is thrown. Timeouts raise `LaravelNats\Exceptions\NatsRequestTimeoutException`. Enable server/account **no responders** behavior per NATS docs for best results. |
| **Graceful shutdown helper** | `NatsV2::drainConnection($seconds, $connection?)` runs `process()` for a bounded time, then **disconnects**. This is an **application-level** drain (flush inbound work briefly), not a separate wire opcode. |
| **Multi-value HPUB headers** | Publish with `headers` as `list<string>` per key (e.g. `['X-Trace' => ['a', 'b']]`) to emit repeated header lines per ADR-4. Implementation: `LaravelNats\Message\MultiHeaderPayload`. |
| **CONNECT `echo` / `no_responders`** | The basis client’s CONNECT JSON is built by **basis-company/nats**; this package does not patch those flags today. Use **legacy** `config/nats.php` `echo` if you need an explicit CONNECT echo toggle on the native client. |

## Legacy `LaravelNats\Core\Client`

| Topic | Behavior |
|--------|-----------|
| **Echo** | Configurable via `config/nats.php` **`echo`** / **`NATS_ECHO`** (default true). Passed through `ConnectionConfig::toConnectArray()`. |
| **Multi-value HPUB headers** | `CommandBuilder::publishWithHeaders` accepts **`array<string, string\|list<string>>`**. |
| **Reconnect** | `NatsManager::reconnect()` is **manual** (disconnect + new connection), not an automatic loop. |

