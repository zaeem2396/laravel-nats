# Example: transactional outbox drain

**Package 1.6.0+.** Your app owns storage and transactions; the package drains pending rows through `NatsV2::dispatchOutbox()`.

### Store sketch

```php
use LaravelNats\Outbox\Contracts\NatsOutboxStoreContract;
use LaravelNats\Outbox\NatsOutboxMessage;
use Throwable;

final class DatabaseNatsOutboxStore implements NatsOutboxStoreContract
{
    public function nextBatch(int $limit): iterable
    {
        // SELECT pending rows LIMIT $limit → yield NatsOutboxMessage
        return [];
    }

    public function markPublished(NatsOutboxMessage $message): void
    {
        // Mark row sent
    }

    public function markFailed(NatsOutboxMessage $message, Throwable $exception): void
    {
        // Persist failure for retry / alerting
    }
}
```

### Schedule a drain

```php
use LaravelNats\Laravel\Facades\NatsV2;

$result = NatsV2::dispatchOutbox(app(DatabaseNatsOutboxStore::class));

logger()->info('NATS outbox drained', [
    'published' => $result->published(),
    'failed' => $result->failed(),
    'succeeded' => $result->succeeded(),
]);
```

```env
NATS_OUTBOX_BATCH_SIZE=100
NATS_OUTBOX_STOP_ON_FAILURE=true
```

Insert outbox rows in the **same database transaction** as the business write you are protecting.

---

Full recipe: [OUTBOX.md](../OUTBOX.md).
