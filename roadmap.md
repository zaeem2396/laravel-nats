Laravel NATS Roadmap (Starting From v1.6.0)
Legend
Status
Meaning
🟢
Done
🟡
In Progress
🔴
Pending


v1.x Core NATS Completion
Current Stable: v1.6.0
v1.7.0
Release
Feature
Status
Prompt
v1.7.0
Message Headers
🔴
Implement full NATS headers support. Allow publishing headers, reading headers from incoming messages, request/reply headers, immutable header collections, validation, and complete test coverage.
v1.7.0
Flush API
🔴
Implement connection flush support with configurable timeout and exception handling.
v1.7.0
Ping API
🔴
Implement explicit ping functionality to validate server connectivity.


v1.8.0
Release
Feature
Status
Prompt
v1.8.0
Connection State Management
🔴
Add isConnected(), isClosed(), isReconnecting(), connectedServer(), connectedUrl(), lastError() methods.
v1.8.0
Reconnect Event Hooks
🔴
Add reconnect, disconnect, closed, error and discovered server callbacks.
v1.8.0
Connection Statistics
🔴
Expose bytes sent, bytes received, reconnect count, message count and uptime metrics.


v1.9.0
Release
Feature
Status
Prompt
v1.9.0
Subscription Drain
🔴
Implement graceful subscription draining allowing pending messages to complete processing before unsubscribe.
v1.9.0
Connection Drain
🔴
Implement graceful connection draining similar to nats.go.
v1.9.0
Cluster Discovery APIs
🔴
Expose discovered servers and cluster topology information.


v1.10.0
Release
Feature
Status
Prompt
v1.10.0
Advanced Authentication
🔴
Add JWT, NKey, Credentials Files, TLS, Mutual TLS and advanced authentication mechanisms supported by nats.go.
v1.10.0
Connection Builder
🔴
Implement fluent connection builder pattern for advanced configurations.
v1.10.0
Config Validation
🔴
Validate NATS configuration during Laravel boot process.


v2.x JetStream
v2.0.0
Release
Feature
Status
Prompt
v2.0.0
JetStream Foundation
🔴
Create JetStream abstraction layer, service container bindings, facades, contracts and configuration support aligned with nats.go.
v2.0.0
Stream CRUD
🔴
Implement create, update, delete and stream info operations.
v2.0.0
Publish Acknowledgements
🔴
Return stream sequence metadata and acknowledgements for published messages.


v2.1.0
Release
Feature
Status
Prompt
v2.1.0
Consumer CRUD
🔴
Implement consumer management APIs including create, update, delete, list and info.
v2.1.0
Durable Consumers
🔴
Support durable consumer creation and lifecycle management.
v2.1.0
Ephemeral Consumers
🔴
Support temporary consumers and automatic cleanup.


v2.2.0
Release
Feature
Status
Prompt
v2.2.0
Pull Consumers
🔴
Implement fetch(), next(), batch(), timeout() and iterator-based message retrieval.
v2.2.0
Message ACK Operations
🔴
Implement ack(), nak(), term(), inProgress() and doubleAck() methods.


v2.3.0
Release
Feature
Status
Prompt
v2.3.0
Async Publishing
🔴
Implement asynchronous publishing with publish futures and acknowledgement tracking.
v2.3.0
Push Consumers
🔴
Implement push consumer subscriptions.
v2.3.0
Consumer Replay Policies
🔴
Support replay instant and replay original modes.


v2.4.0
Release
Feature
Status
Prompt
v2.4.0
Ordered Consumers
🔴
Implement ordered consumer support with automatic recovery.
v2.4.0
Flow Control
🔴
Add flow control and heartbeat management.
v2.4.0
Consumer Monitoring
🔴
Add lag, pending ACK and delivery metrics.


v2.5.0
Release
Feature
Status
Prompt
v2.5.0
Stream Mirroring
🔴
Implement stream mirroring support.
v2.5.0
Stream Sources
🔴
Implement source stream replication support.
v2.5.0
Stream Snapshot & Restore
🔴
Implement stream backup and restoration operations.


v3.x Key Value Store
v3.0.0
Release
Feature
Status
Prompt
v3.0.0
Bucket Management
🔴
Implement createBucket(), updateBucket(), deleteBucket() and bucket info operations.
v3.0.0
KV CRUD Operations
🔴
Implement put(), get(), delete(), purge(), keys().


v3.1.0
Release
Feature
Status
Prompt
v3.1.0
Revision History
🔴
Support retrieval of historical key revisions.
v3.1.0
Atomic Updates
🔴
Implement compare-and-swap operations matching NATS semantics.
v3.1.0
Optimistic Locking
🔴
Implement revision-aware update protection.


v3.2.0
Release
Feature
Status
Prompt
v3.2.0
Watchers
🔴
Implement watch(), watchAll() and wildcard-based key watchers.
v3.2.0
Event Broadcasting
🔴
Dispatch Laravel events on KV mutations.


v3.3.0
Release
Feature
Status
Prompt
v3.3.0
Laravel Cache Driver
🔴
Create a Laravel cache driver backed by NATS KV buckets.
v3.3.0
Distributed Locking
🔴
Create Laravel-compatible distributed locks using KV CAS operations.


v4.x Object Store
v4.0.0
Release
Feature
Status
Prompt
v4.0.0
Object Store Foundation
🔴
Implement object store bucket management and metadata handling.
v4.0.0
Upload APIs
🔴
Support file, stream and binary uploads.
v4.0.0
Download APIs
🔴
Support file retrieval and streaming downloads.


v4.1.0
Release
Feature
Status
Prompt
v4.1.0
Object Watchers
🔴
Implement change monitoring for object stores.
v4.1.0
Metadata Operations
🔴
Support metadata retrieval and updates.
v4.1.0
Large File Chunking
🔴
Add chunked upload support for large files.


v4.2.0
Release
Feature
Status
Prompt
v4.2.0
Laravel Filesystem Driver
🔴
Build a Storage driver compatible with Laravel Storage facade.
v4.2.0
Signed URLs
🔴
Generate temporary URLs for object retrieval.


v5.x Laravel Native Ecosystem
v5.0.0
Release
Feature
Status
Prompt
v5.0.0
Artisan Consumer Workers
🔴
Create php artisan nats:consume command with worker lifecycle management.
v5.0.0
Retry Management
🔴
Add retries, dead-letter queues and poison message handling.
v5.0.0
Middleware Pipeline
🔴
Support middleware execution before message handlers.


v5.1.0
Release
Feature
Status
Prompt
v5.1.0
Job Bus Integration
🔴
Dispatch Laravel jobs through JetStream.
v5.1.0
Queue Driver
🔴
Build a first-class Laravel Queue driver backed by NATS.


v5.2.0
Release
Feature
Status
Prompt
v5.2.0
Eloquent Broadcasting
🔴
Automatically publish model events to NATS subjects.
v5.2.0
Event Broadcasting
🔴
Automatically publish Laravel events.


v5.3.0
Release
Feature
Status
Prompt
v5.3.0
Horizon Style Dashboard
🔴
Create monitoring dashboard showing streams, consumers, lag, ACK metrics and throughput.


v6.x NATS Micro Framework
v6.0.0
Release
Feature
Status
Prompt
v6.0.0
Service Framework
🔴
Implement service registration framework similar to nats.go micro package.
v6.0.0
Endpoint Registration
🔴
Register endpoints using PHP attributes.
v6.0.0
Service Discovery
🔴
Expose service metadata and discovery APIs.


v6.1.0
Release
Feature
Status
Prompt
v6.1.0
Health Checks
🔴
Built-in service health checks.
v6.1.0
Metrics Collection
🔴
Request counts, latency and error metrics.
v6.1.0
Validation Pipeline
🔴
Automatic request validation support.


v6.2.0
Release
Feature
Status
Prompt
v6.2.0
Service Generator
🔴
Generate microservices through Artisan commands.
v6.2.0
OpenAPI Export
🔴
Generate OpenAPI-like service documentation.


v7.x Enterprise & Observability
v7.0.0
Release
Feature
Status
Prompt
v7.0.0
OpenTelemetry
🔴
Add tracing support across publishers and consumers.
v7.0.0
Prometheus Metrics
🔴
Export metrics suitable for Prometheus scraping.
v7.0.0
Health Monitoring
🔴
Comprehensive health and readiness checks.


v7.1.0
Release
Feature
Status
Prompt
v7.1.0
Benchmark Suite
🔴
Create benchmark tooling comparing performance against nats.go and other clients.
v7.1.0
Multi-Tenant Support
🔴
Tenant-aware routing and isolation.
v7.1.0
Failover Testing Toolkit
🔴
Automated cluster failover testing framework.


Recommended Development Order
If your goal is Go parity as fast as possible, build in this order:
v1.7.0 → v1.10.0 (Finish Core NATS)
v2.0.0 → v2.5.0 (Complete JetStream)
v3.0.0 → v3.3.0 (KV Store)
v4.0.0 → v4.2.0 (Object Store)
v6.0.0 → v6.2.0 (Micro Framework)
v5.x Laravel Enhancements
v7.x Enterprise Features
This order mirrors the importance hierarchy of the Go client and will get you closest to functional parity the fastest.

