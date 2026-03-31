# Security & config hardening (v2.6)

This page covers **optional** Laravel-side controls shipped in **package 1.5.0+**: boot-time validation of `nats_basis` connections, a **TLS-oriented production check**, and **client-side subject ACLs** for publish/subscribe. These layers **do not replace** NATS server authentication, authorization, or network policies.

## Contents

1. [Threat model (short)](#threat-model-short)
2. [Secrets: env, rotation, and encryption](#secrets-env-rotation-and-encryption)
3. [Boot-time config validation](#boot-time-config-validation)
4. [TLS in production](#tls-in-production)
5. [Subject ACL (allowlists)](#subject-acl-allowlists)
6. [Artisan: `nats:v2:config:validate`](#artisan-natsv2configvalidate)
7. [Related docs](#related-docs)

---

## Threat model (short)

- **Server trust:** Clients must connect to the correct NATS cluster and use credentials the server accepts (user/password, token, JWT, NKey, TLS client certs as configured on the server).
- **Application bugs:** Misconfigured hosts, missing TLS, or accidental publish/subscribe to unintended subjects can leak data or cause incidents. Validation and ACLs reduce **footguns** in app code.
- **Defense in depth:** Use **NATS server** authz (accounts, users, imports/exports) as the source of truth; use this package’s ACL as an **extra** guardrail where it helps (e.g. multi-tenant apps with strict subject namespaces).

---

## Secrets: env, rotation, and encryption

### Environment variables

`config/nats_basis.php` maps standard `NATS_*` env vars to connection options (`user`, `pass`, `token`, `jwt`, `nkey`, TLS file paths). Prefer **short-lived** credentials where your NATS deployment supports it (e.g. JWT with bounded claims, tokens rotated via your secret manager).

### Rotation

1. Provision new credentials in NATS (or your identity system).
2. Update env / secret store; deploy so new pods/processes pick up values.
3. Revoke old credentials on the server once traffic is clean.

### Laravel encrypted env

For `.env` values, you may use Laravel’s **`env:encrypt` / `env:decrypt`** so committed encrypted blobs are not plaintext. This protects **at rest** copies of env files; runtime still needs decrypted values in memory. Pair with **restricted** CI and production secret injection (Vault, KMS, platform secrets).

---

## Boot-time config validation

When **`nats_basis.security.validate_on_boot`** is `true` (env **`NATS_BASIS_VALIDATE_CONFIG=true`**), the service provider runs **`NatsBasisConfigurationValidator`** during **`boot()`**:

- `nats_basis.connections` must be a **non-empty** array.
- Each connection: non-empty **`host`**, **`port`** in `1..65535`, **`timeout` > 0**.

On failure, a **`LaravelNats\Security\Exceptions\NatsConfigurationException`** is thrown so the app fails fast instead of connecting with invalid settings.

---

## TLS in production

When **`nats_basis.security.tls.require_in_production`** is `true` (**`NATS_TLS_REQUIRE_IN_PRODUCTION=true`**) **and** `APP_ENV=production`, each connection must satisfy **at least one** of:

- **`tlsCaFile`** set (non-empty), or
- **`tlsCertFile` + `tlsKeyFile`** (client certificate), or
- **`tlsHandshakeFirst`** `true` (use when your topology requires TLS handshake before other negotiation—still ensure you trust the endpoint).

This does **not** verify certificate hostnames for you; it only ensures you did not leave plaintext-only settings on production by mistake. Use correct CA bundles and server configs in real deployments.

---

## Subject ACL (allowlists)

When **`nats_basis.acl.enabled`** is `true` (**`NATS_ACL_ENABLED=true`**):

- **`NatsPublisher`**, **`BasisJetStreamPublisher`**, and **`NatsBasisSubscriber`** call **`SubjectAclChecker`** before publish/subscribe.
- **`allowed_publish_prefixes`** and **`allowed_subscribe_prefixes`** are lists of strings (env: comma-separated **`NATS_ACL_PUBLISH_PREFIXES`**, **`NATS_ACL_SUBSCRIBE_PREFIXES`**).

### Matching rules

- **`SubjectPrefixMatcher`** applies each rule:
  - **Trailing `.`** means **prefix**: `orders.` allows `orders.created` but not `orders` alone.
  - **No trailing dot** allows that segment as a **namespace prefix**: `orders` allows `orders` and `orders.x`.
- **Exact** subjects can be listed as full literals (e.g. `system.health`).
- If ACL is **enabled** and a list is **empty**, **all** publish or subscribe operations in that direction are **denied** (`SubjectNotAllowedException`).

### Server-side authorization

NATS must still enforce authoritative rules. Client ACLs catch mistakes early and can mirror your naming conventions; they can be bypassed if code bypasses these publishers/subscribers.

### What ACL does not cover

The **`nats_basis`** queue driver publishes job payloads with **`Basis\Nats\Client::publish`** directly (not **`NatsPublisher`**). **Subject ACL in this package does not apply** to queue push/pop subjects. Restrict those with **NATS server** authorization and careful queue prefix configuration ([QUEUE.md](QUEUE.md)).

---

## Artisan: `nats:v2:config:validate`

Runs the same validator with **`force: true`**, so checks run even when **`validate_on_boot`** is off. Use in CI or before deploy:

```bash
php artisan nats:v2:config:validate
```

Exit code is non-zero when validation fails.

---

## Related docs

- [GUIDE.md](GUIDE.md) — overview of `NatsV2` and config
- [MIGRATION.md](MIGRATION.md) — upgrade notes including v2.6
- [SUBSCRIBER.md](SUBSCRIBER.md) — subscribe API (ACL applies here)
- [JETSTREAM.md](JETSTREAM.md) — JetStream publish (ACL applies)
- [QUEUE.md](QUEUE.md) — queue driver (server auth still primary)
- [OBSERVABILITY.md](OBSERVABILITY.md) — metrics and health
- [FAQ.md](FAQ.md)
