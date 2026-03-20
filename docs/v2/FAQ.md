# v2 FAQ

**Why @deprecated on `Nats`?** Soft deprecation only: new **publish** code should use `NatsV2`. Subscribe, queue, and JetStream remain on `Nats` until v2.2+ parity. Details in [MIGRATION](MIGRATION.md).

**Mix facades?** Yes, per subject or service boundary.

**Is the envelope required?** Only if you use `NatsV2::publish`.

**JetStream on v2?** Planned v2.2; legacy `Nats::jetstream()` unchanged.

**Overhead?** One JSON encode and UUID per publish; negligible vs network.
