# PHPNomad Redis Task Integration

This package provides a Redis-based implementation of the `TaskStrategy` interface
from [`phpnomad/tasks`](https://github.com/phpnomad/tasks). It is designed for high-throughput task ingestion in
PHPNomad applications.

## Purpose

This library serves as a **fast, queue-first front layer** that receives and buffers task dispatches (
via `dispatch($task)`), and allows long-running workers to pick up and execute those tasks using the same `TaskStrategy`
interface.

It is especially useful in systems where:

- Requests must return quickly (e.g. Slack webhooks, user interactions, event triggers)
- Real work can be deferred
- Tasks must be chained, routed, or escalated downstream
- A separate job system (e.g. DB-backed orchestration) will handle full processing later

---

## Installation

```bash
composer require phpnomad/tasks-redis
