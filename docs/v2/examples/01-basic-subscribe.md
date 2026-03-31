# Example: basic subscribe

```php
use LaravelNats\Laravel\Facades\NatsV2;
use LaravelNats\Subscriber\InboundMessage;

NatsV2::subscribe('hello.world', function (InboundMessage $m): void {
    logger()->info($m->body);
});

while (true) {
    NatsV2::process(null, 1.0);
}
```
