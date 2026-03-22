# v2 FAQ

**Is this a separate NATS client?** No. v2 is a **Laravel wrapper** on [basis-company/nats](https://packagist.org/packages/basis-company/nats): this package adds config, facades, and the envelope; the dependency owns the protocol.

**v2.1 subscribe?** Use `NatsV2::subscribe` with `InboundMessage` and a `process()` loop, or `php artisan nats:v2:listen`. See [SUBSCRIBER](SUBSCRIBER.md). Legacy `Nats::subscribe` remains available.

**Why @deprecated on `Nats`?** Soft deprecation only: new **publish** code should use `NatsV2`. For **new** subscribe code on the basis stack, prefer `NatsV2::subscribe`. Queue and JetStream on legacy remain until parity. Details in [MIGRATION](MIGRATION.md).

**Mix facades?** Yes, per subject or service boundary.

**Is the envelope required?** Only if you use `NatsV2::publish`.

**JetStream on v2?** Planned v2.2; legacy `Nats::jetstream()` unchanged.

**Overhead?** One JSON encode and UUID per publish; negligible vs network.
