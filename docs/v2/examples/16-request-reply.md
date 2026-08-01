# Example: request / reply with no-responders handling

`NatsV2::request()` waits for a reply. Timeouts and NATS **503 no responders** map to typed exceptions.

### Requester

```php
use Basis\Nats\Message\Payload;
use LaravelNats\Exceptions\NatsNoRespondersException;
use LaravelNats\Exceptions\NatsRequestTimeoutException;
use LaravelNats\Laravel\Facades\NatsV2;

try {
    /** @var Payload $reply */
    $reply = NatsV2::request('math.add', json_encode(['a' => 2, 'b' => 3]), 2.0);
    $data = json_decode($reply->body, true);
} catch (NatsNoRespondersException $e) {
    // No interest on the subject (Status-Code 503)
} catch (NatsRequestTimeoutException $e) {
    // Timed out waiting for a reply
}
```

Responders typically subscribe on the same subject with the basis client (or a worker that publishes to `$message->replyTo`). See [CLIENT_FEATURES.md](../CLIENT_FEATURES.md).

### Bounded drain before shutdown

```php
NatsV2::drainConnection(2.0); // process inbound briefly, then disconnect
```

---

Protocol notes: [CLIENT_FEATURES.md](../CLIENT_FEATURES.md).
