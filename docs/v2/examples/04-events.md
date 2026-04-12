# Example: dispatch Laravel events

Set `NATS_SUBSCRIBER_DISPATCH_EVENTS=true` or `subscriber.dispatch_events` in config.

Listen with `Event::listen(NatsInboundMessageReceived::class, ...)`.

---

**v2.6:** Events fire for messages that passed inbound ACL checks when enabled ([SECURITY.md](../SECURITY.md)).
