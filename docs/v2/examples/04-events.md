# Example: dispatch Laravel events

Set `NATS_SUBSCRIBER_DISPATCH_EVENTS=true` or `subscriber.dispatch_events` in config.

Listen with `Event::listen(NatsInboundMessageReceived::class, ...)`.
