# Laravel queue on the basis client (`nats_basis`)

**Package 1.4.0+** adds a queue connection driver **`nats_basis`** that uses **`Basis\Nats\Client`** via **`ConnectionManager`** (same stack as **`NatsV2`**). Job payloads match the **legacy `nats` driver** JSON shape so **`php artisan queue:work`** behaves the same (retries, `failed_jobs`, DLQ routing).

The legacy **`nats`** driver ( **`LaravelNats\Core\Client`** ) is unchanged.

## When to use which driver

| Driver | Wire client | Use when |
|--------|-------------|----------|
| **`nats`** | `LaravelNats\Core\Client` | You already run workers on the legacy stack. |
| **`nats_basis`** | `Basis\Nats\Client` | New or migrated workers should use the v2 connection manager and **`config/nats_basis.php`**. |

## Configuration

### `config/queue.php`

Add (or switch) a connection:

```php
'connections' => [
    'nats_basis' => [
        'driver' => 'nats_basis',
        'queue' => env('NATS_BASIS_QUEUE', 'default'),
        'retry_after' => (int) env('NATS_BASIS_QUEUE_RETRY_AFTER', 60),
        'tries' => (int) env('NATS_BASIS_QUEUE_TRIES', 3),
        'block_for' => (float) env('NATS_BASIS_QUEUE_BLOCK_FOR', 0.1),
        'prefix' => env('NATS_BASIS_QUEUE_PREFIX', 'laravel.queue.'),
        'dead_letter_queue' => env('NATS_BASIS_QUEUE_DLQ'), // optional full subject or short name
        // 'max_in_flight' => (int) env('NATS_BASIS_QUEUE_MAX_IN_FLIGHT', 0), // optional; 0 = unlimited
        // Optional: named basis connection (defaults to nats_basis.default)
        // 'nats_basis_connection' => 'secondary',
    ],
],
```

Set **`QUEUE_CONNECTION=nats_basis`** (or dispatch to this connection explicitly).

You can set **`max_in_flight`** on the connection (or rely on **`nats_basis.queue.max_in_flight`**). When set to a positive integer, **`pop()`** returns **`null`** while the internal counter is at or above that limit. The counter increments when a message is delivered to a **`NatsJob`** and decrements when the job is **deleted**, **released**, or **failed** (via **`NatsJobQueueBridge::notifyJobHandled()`**).

This is **per PHP worker process**, not cluster-wide. Standard **`queue:work`** runs one job at a time, so a limit greater than **1** only matters if you run custom code that holds multiple jobs without completing them, or future async worker modes. Omit **`max_in_flight`** (or use **0**) for unlimited behaviour.

### Defaults in `config/nats_basis.php`

The **`queue`** key supplies fallbacks when options are omitted from **`queue.php`**:

| Key | Env | Role |
|-----|-----|------|
| `prefix` | `NATS_BASIS_QUEUE_PREFIX` | Subject prefix; job subjects are `{prefix}{queue_name}`. |
| `retry_after` | `NATS_BASIS_QUEUE_RETRY_AFTER` | Seconds before a reserved job is released (worker visibility). |
| `tries` | `NATS_BASIS_QUEUE_TRIES` | Default max attempts when building jobs. |
| `block_for` | `NATS_BASIS_QUEUE_BLOCK_FOR` | Seconds the driver blocks while draining one message in **`pop()`**. |
| `max_in_flight` | `NATS_BASIS_QUEUE_MAX_IN_FLIGHT` | Optional cap on jobs popped but not yet finished (delete/release) **per worker process**; see **Configuration** above. |

## Requirements

- **`NatsServiceProvider`** registered (auto-discovery).
- **`ConnectionManager`** bound (done by the provider).
- **`config/nats_basis.php`** connections must reach your NATS server (same as **`NatsV2`**).

## Dead letter queue (DLQ)

Set **`dead_letter_queue`** on the queue connection (or rely on package defaults you merge in). Behavior matches the legacy driver:

- If the value contains **`.`**, it is treated as a **full NATS subject**.
- Otherwise it is prefixed with **`prefix`** (e.g. `failed` → `laravel.queue.failed`).

Failed jobs are still recorded via Laravel’s **`failed_jobs`** pipeline when configured; the DLQ subject receives an augmented JSON copy of the payload for downstream consumers.

## Workers and process managers

Use the same commands as for the legacy NATS queue:

```bash
php artisan queue:work nats_basis --queue=default --tries=3
```

The package also provides **`php artisan nats:work`** for the **`nats`** connection; for **`nats_basis`** use **`queue:work`** with the connection name you configured.

**Supervisor** (example — adjust paths and user):

```ini
[program:laravel-worker-nats-basis]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work nats_basis --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

## Limitations (current release)

- **`later()` / delayed dispatch** are not backed by JetStream on **`nats_basis`**; the driver **pushes immediately** (same pattern as NATS Core on the legacy driver without delayed JetStream).
- **Queue depth** (`size()`) always returns **0** (NATS Core does not expose a durable depth).
- **Cross-process backpressure** (global cap across all workers) is not built in; use **`max_in_flight`** only for per-process limits or external coordination (e.g. Redis) if you need cluster-wide throttling.

## See also

- [MIGRATION.md](MIGRATION.md) — dual stack and moving queue workers to **`nats_basis`**
- [GUIDE.md](GUIDE.md) — **`NatsV2`** and **`ConnectionManager`**
- [README](README.md) — v2 doc index
