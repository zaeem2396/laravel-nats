# v2 FAQ

**Is this a separate NATS client?** No. v2 is a **Laravel wrapper** on [basis-company/nats](https://packagist.org/packages/basis-company/nats): this package adds config, facades, and the envelope; the dependency owns the protocol.

**NatsV2 subscribe (1.3.0+)?** Use `NatsV2::subscribe` with `InboundMessage` and a `process()` loop, or `php artisan nats:v2:listen`. See [SUBSCRIBER](SUBSCRIBER.md). Legacy `Nats::subscribe` remains available.

**Why @deprecated on `Nats`?** Soft deprecation only: new **publish** code should use `NatsV2`. For **new** subscribe code on the basis stack, prefer `NatsV2::subscribe`. Queue and JetStream on legacy remain until parity. Details in [MIGRATION](MIGRATION.md).

**Mix facades?** Yes, per subject or service boundary.

**Is the envelope required?** Only if you use `NatsV2::publish`.

**JetStream on NatsV2?** Yes from **1.4.0+**: `NatsV2::jetstream()`, publish/pull helpers, and `nats:v2:jetstream:*` commands ([JETSTREAM.md](JETSTREAM.md)). Legacy `Nats::jetstream()` remains available.

**Overhead?** One JSON encode and UUID per publish; negligible vs network.
