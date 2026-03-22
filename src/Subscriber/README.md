# Subscriber (v2)

The v2 subscriber API will wrap `Basis\Nats\Client::subscribe` / `subscribeQueue` through this package’s **Laravel wrapper** (same pattern as the v2 publisher: config + lifecycle, [basis-company/nats](https://packagist.org/packages/basis-company/nats) for the wire protocol).

## Next: subscriber module

`Basis\Nats\Client::subscribe` integration is planned as the next major v2 feature area in this repository.
