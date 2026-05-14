# Outbox Recipe

From package **1.6.0** (v2.7), outbox support is intentionally storage-agnostic. Your application owns the database table and transaction boundaries; this package provides DTOs and a dispatcher that publishes pending rows through `NatsPublisherContract`.

## Pieces

- `LaravelNats\Outbox\NatsOutboxMessage` - DTO for one pending publish.
- `LaravelNats\Outbox\Contracts\NatsOutboxStoreContract` - app-provided storage adapter.
- `LaravelNats\Outbox\NatsOutboxDispatcher` - drains a store batch and marks rows published or failed.
- `NatsV2::dispatchOutbox($store, $limit = null, $stopOnFailure = null)` - facade helper using `nats_basis.outbox` defaults.

## Config

```env
NATS_OUTBOX_BATCH_SIZE=100
NATS_OUTBOX_STOP_ON_FAILURE=true
```

## Store Contract

```php
use LaravelNats\Outbox\Contracts\NatsOutboxStoreContract;
use LaravelNats\Outbox\NatsOutboxMessage;

final class DatabaseNatsOutboxStore implements NatsOutboxStoreContract
{
    public function nextBatch(int $limit): iterable
    {
        // Return pending rows as NatsOutboxMessage instances.
    }

    public function markPublished(NatsOutboxMessage $message): void
    {
        // Mark the row sent.
    }

    public function markFailed(NatsOutboxMessage $message, Throwable $exception): void
    {
        // Record failure details for retry/alerting.
    }
}
```

## Dispatch

```php
$result = NatsV2::dispatchOutbox(app(DatabaseNatsOutboxStore::class));

logger()->info('NATS outbox drained', [
    'published' => $result->published(),
    'failed' => $result->failed(),
    'succeeded' => $result->succeeded(),
]);
```

For a scheduler, call the dispatcher from an Artisan command or scheduled closure. Keep inserts into your outbox table inside the same database transaction as the business row you are protecting.

## See also

- [GUIDE.md](GUIDE.md)
- [OBSERVABILITY.md](OBSERVABILITY.md)
- [SECURITY.md](SECURITY.md)
