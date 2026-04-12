# Example: wildcard subjects

Use NATS wildcards in the subject string: `orders.*`, `events.>`.

Validation still applies to the string length configured under `nats_basis.subscriber`.

---

**v2.6:** Wildcard subjects must be allowed by `SubjectPrefixMatcher` rules—test edge cases ([SECURITY.md](../SECURITY.md)).
