# Example: request / reply with no-responders handling

`NatsV2::request()` waits for a reply. Timeouts and NATS **503 no responders** map to typed exceptions.

### Responder

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('math.add', function (InboundMessage $m): void {
    $a = (int) ($m->payload()['a'] ?? 0);
    $b = (int) ($m->payload()['b'] ?? 0);
    // Reply using the inbound reply subject when present on the wire message.
    // Prefer documenting your app’s reply convention; see CLIENT_FEATURES.md.
});

while (true) {
    NatsV2::process(null, 1.0);
}
```

### Requester

```php
use LaravelNats\Exceptions\NatsNoRespondersException;
use LaravelNats\Exceptions\NatsRequestTimeoutException;
use LaravelNats\Laravel\Facades\NatsV2;

try {
    $reply = NatsV2::request('math.add', ['a' => 2, 'b' => 3], 2.0);
    // $reply is the raw reply body from the basis client
} catch (NatsNoRespondersException $e) {
    // No interest on the subject (Status-Code 503)
} catch (NatsRequestTimeoutException $e) {
    // Timed out waiting for a reply
}
```

### Bounded drain before shutdown

```php
NatsV2::drainConnection(2.0); // process inbound briefly, then disconnect
```

---

Protocol notes: [CLIENT_FEATURES.md](../CLIENT_FEATURES.md).
