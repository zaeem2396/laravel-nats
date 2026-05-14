# Connection Selection

`NatsV2` methods still accept an explicit `$connection` argument. From package **1.6.0** (v2.7), you can also configure subject-prefix routing so common subjects automatically choose a named `nats_basis` connection.

## Configure

```env
NATS_CONNECTION_SUBJECT_PREFIXES="orders.:orders,billing.:billing"
```

Or publish `config/nats_basis.php` and set:

```php
'connection_selection' => [
    'subject_prefixes' => [
        'orders.eu.' => 'orders-eu',
        'orders.' => 'orders',
    ],
],
```

The selector uses the **longest matching prefix**. Explicit method arguments still win:

```php
NatsV2::publish('orders.created', ['id' => 1]); // uses "orders"
NatsV2::publish('orders.created', ['id' => 1], connection: 'default'); // explicit wins
```

## Helper

```php
$connection = NatsV2::selectConnection('orders.eu.created');
```

`null` means no rule matched, so the default `nats_basis.default` connection is used.

## See also

- [CLIENT_FEATURES.md](CLIENT_FEATURES.md)
- [GUIDE.md](GUIDE.md)
- [examples/07-named-connection.md](examples/07-named-connection.md)
