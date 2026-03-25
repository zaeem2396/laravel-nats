# `LaravelNats\JetStream`

Wrappers around **basis-company/nats** JetStream types (`Api`, `Stream`, `Consumer`) used by **`NatsV2`** and **`NatsV2Gateway`**.

- **`BasisJetStreamManager`** - `client()`, `api()`, `stream()`, `accountInfo()`, `streamNames()`
- **`BasisJetStreamPublisher`** - envelope or raw JSON via `Stream::publish` / `put`
- **`PullConsumerBatch`** - one-shot `fetch()` with subscription cleanup
- **`BasisStreamProvisioner`** - create streams from **`nats_basis.jetstream.presets`**

See [docs/v2/JETSTREAM.md](../../docs/v2/JETSTREAM.md).
