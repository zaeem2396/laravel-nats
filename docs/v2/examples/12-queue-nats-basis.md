# Example: Laravel queue on `nats_basis`

**Package 1.4.0+.** Use the basis-client queue driver for new workers.

### `config/queue.php`

```php
'connections' => [
    'nats_basis' => [
        'driver' => 'nats_basis',
        'queue' => env('NATS_BASIS_QUEUE', 'default'),
        'retry_after' => (int) env('NATS_BASIS_QUEUE_RETRY_AFTER', 60),
        'tries' => (int) env('NATS_BASIS_QUEUE_TRIES', 3),
        'block_for' => (float) env('NATS_BASIS_QUEUE_BLOCK_FOR', 0.1),
        'prefix' => env('NATS_BASIS_QUEUE_PREFIX', 'laravel.queue.'),
    ],
],
```

### Dispatch and work

```php
use App\Jobs\ProcessOrder;

ProcessOrder::dispatch($order)->onConnection('nats_basis');
```

```bash
QUEUE_CONNECTION=nats_basis php artisan queue:work
```

### Optional named NATS connection

```php
'nats_basis' => [
    'driver' => 'nats_basis',
    // ...
    'nats_basis_connection' => 'orders',
],
```

---

Subject ACL (`NATS_ACL_*`) does **not** wrap this driver’s internal publishes — enforce queue subjects with NATS server auth. Full reference: [QUEUE.md](../QUEUE.md).
