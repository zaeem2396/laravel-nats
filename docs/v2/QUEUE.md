# Laravel queue on the basis client (`nats_basis`)

**Package 1.5.0+** adds a queue connection driver **`nats_basis`** that uses **`Basis\Nats\Client`** via **`ConnectionManager`** (same stack as **`NatsV2`**). Job payloads match the **legacy `nats` driver** JSON shape so **`php artisan queue:work`** behaves the same (retries, `failed_jobs`, DLQ routing).

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
        // Optional: named basis connection (defaults to nats_basis.default)
        // 'nats_basis_connection' => 'secondary',
    ],
],
```

Set **`QUEUE_CONNECTION=nats_basis`** (or dispatch to this connection explicitly).

### Defaults in `config/nats_basis.php`

The **`queue`** key supplies fallbacks when options are omitted from **`queue.php`**:

| Key | Env | Role |
|-----|-----|------|
| `prefix` | `NATS_BASIS_QUEUE_PREFIX` | Subject prefix; job subjects are `{prefix}{queue_name}`. |
| `retry_after` | `NATS_BASIS_QUEUE_RETRY_AFTER` | Seconds before a reserved job is released (worker visibility). |
| `tries` | `NATS_BASIS_QUEUE_TRIES` | Default max attempts when building jobs. |
| `block_for` | `NATS_BASIS_QUEUE_BLOCK_FOR` | Seconds the driver blocks while draining one message in **`pop()`**. |

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
- **Max in-flight / backpressure** knobs from the roadmap are not implemented yet; treat as a future minor.

## See also

- [MIGRATION.md](MIGRATION.md) — dual stack and moving queue workers to **`nats_basis`**
- [GUIDE.md](GUIDE.md) — **`NatsV2`** and **`ConnectionManager`**
- [README](README.md) — v2 doc index
