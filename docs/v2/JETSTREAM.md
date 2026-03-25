# NatsV2 JetStream (basis-company/nats)

JetStream on the **v2 stack** uses **`Basis\Nats\Api`**, **`Stream`**, and **`Consumer`** from [basis-company/nats](https://github.com/basis-company/nats.php). This package adds Laravel wiring: **`NatsV2::jetstream()`**, publish/pull helpers, optional **stream presets** in `config/nats_basis.php`, and thin Artisan commands.

**Legacy:** `Nats::jetstream()` still uses `LaravelNats\Core\JetStream\JetStreamClient` (native wire client). Prefer **`NatsV2`** for new JetStream work on the basis client.

## Prerequisites

- NATS Server with JetStream enabled, e.g. `docker run ... nats:2.10 --jetstream` (see server docs for your environment).
- **`config/nats_basis.php`** merged (publish with `php artisan vendor:publish --tag=nats-config`).

## API overview

| Task | Entry point |
|------|-------------|
| Low-level Api / Stream | `NatsV2::jetstream($connection?)->stream('MY_STREAM')` |
| Account INFO | `NatsV2::jetstream()->accountInfo()` |
| Stream names | `NatsV2::jetstream()->streamNames()` |
| Publish (envelope or raw JSON) | `NatsV2::jetStreamPublish($stream, $subject, $payload, ...)` |
| One-shot pull batch | `NatsV2::jetStreamPull($stream, $consumer, $batch?, $expires?, $connection?)` |
| Provision from config preset | `NatsV2::jetStreamProvisionPreset('example_events')` |

### Publish

`jetStreamPublish` wraps `Basis\Nats\Stream\Stream::publish()` (wait for ack) or `put()` (core publish without JS ack). With **`$useEnvelope = true`** (default), the body is the same JSON envelope as **`NatsV2::publish`**.

```php
use LaravelNats\Laravel\Facades\NatsV2;

NatsV2::jetStreamPublish(
    stream: 'ORDERS',
    subject: 'orders.created',
    payload: ['id' => 1],
    useEnvelope: true,
    waitForAck: true,
);
```

**Headers:** The basis JetStream publish path in this package is body-focused today. For **HPUB** headers (including correlation IDs), use **`NatsV2::publish`** to the same subject, or embed trace metadata in **`data`**. See [CORRELATION.md](CORRELATION.md).

### Pull consumers

`jetStreamPull` creates the durable consumer if needed, pulls up to **`batch`** messages, **acks** each in the **`nats:v2:jetstream:pull`** command. In your own code, ack/nak explicitly:

```php
foreach (NatsV2::jetStreamPull('ORDERS', 'worker-1', batch: 5) as $msg) {
    // $msg instanceof \Basis\Nats\Message\Msg
    $msg->ack();
}
```

Defaults: **`nats_basis.jetstream.pull.default_batch`** and **`default_expires`** (seconds).

### Presets

Under **`nats_basis.jetstream.presets`**, named arrays describe streams for **`jetStreamProvisionPreset($key)`** or **`nats:v2:jetstream:provision`**.

Example (from package defaults; adjust in your app config):

- **`example_events`**: stream `EXAMPLE_EVENTS`, subjects `example.events.>`, file storage, limits retention.

## Artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan nats:v2:jetstream:info` | Print `$JS.API.INFO` JSON |
| `php artisan nats:v2:jetstream:streams` | List stream names |
| `php artisan nats:v2:jetstream:pull {stream} {consumer}` | Pull, print body, ack |
| `php artisan nats:v2:jetstream:provision {preset}` | Create stream from preset (`--force` uses `create()`) |

All support **`--connection=`** for named basis connections.

## See also

- [GUIDE.md](GUIDE.md) - config and dual stack
- [MIGRATION.md](MIGRATION.md) - legacy JetStream vs NatsV2
