# phpnomad/redis-task-integration

[![Latest Version](https://img.shields.io/packagist/v/phpnomad/redis-task-integration.svg)](https://packagist.org/packages/phpnomad/redis-task-integration)
[![Total Downloads](https://img.shields.io/packagist/dt/phpnomad/redis-task-integration.svg)](https://packagist.org/packages/phpnomad/redis-task-integration)
[![PHP Version](https://img.shields.io/packagist/php-v/phpnomad/redis-task-integration.svg)](https://packagist.org/packages/phpnomad/redis-task-integration)
[![License](https://img.shields.io/packagist/l/phpnomad/redis-task-integration.svg)](https://packagist.org/packages/phpnomad/redis-task-integration)

Integrates Redis with the task queue abstractions in [`phpnomad/tasks`](https://packagist.org/packages/phpnomad/tasks). It ships a `TaskStrategy` that serializes each dispatched task, signs the payload with HMAC-SHA256, and pushes it onto a Redis list. A paired worker service blocks on the same list, verifies the signature on each payload, and runs the matching handlers. Tasks that implement `IsIdempotent` are coordinated through the configured `IdempotencyStore` so they are not double-run when multiple workers are consuming the queue.

## Installation

```bash
composer require phpnomad/redis-task-integration
```

The package depends on the native PHP Redis extension (`ext-redis`) and a reachable Redis server.

## What This Provides

- `RedisTaskStrategy`, a `phpnomad/tasks` `TaskStrategy` implementation that serializes, signs, and pushes tasks onto a Redis list named `phpnomad.tasks`.
- `RedisWorkerService`, the long-running worker that blocks on the queue, verifies the signature on each payload, reconstructs the task via `fromPayload`, and invokes the matching handlers.
- `RedisTaskHandlerRegistry`, the in-memory map of task ids to callable handlers shared between the strategy and the worker.

## Requirements

- `phpnomad/tasks ^2.1`
- `phpnomad/auth ^1.0` (the worker and strategy sign payloads using `SecretProvider`)
- `phpnomad/logger ^1.0` (the worker logs lifecycle events and exceptions via `LoggerStrategy`)
- The `ext-redis` PHP extension
- A reachable Redis server

## Usage

Bind `RedisTaskStrategy` as the concrete implementation of `TaskStrategy` in your application's DI container so every `dispatch()` call goes through the Redis queue instead of an in-process fallback.

```php
use PHPNomad\Redis\Tasks\Integration\Strategies\RedisTaskStrategy;
use PHPNomad\Tasks\Interfaces\TaskStrategy;

class MyAppInitializer
{
    public function getBindings(): array
    {
        return [
            TaskStrategy::class => RedisTaskStrategy::class,
        ];
    }
}
```

Run the worker as a long-lived CLI process. A simple entry point resolves `RedisWorkerService` from the container and calls `watch()`, which blocks on the queue until a task arrives.

```php
#!/usr/bin/env php
<?php

use MyApp\Application;
use PHPNomad\Redis\Tasks\Integration\Services\RedisWorkerService;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application();
$app->load();

$worker = $app->getContainer()->get(RedisWorkerService::class);
$worker->watch();
```

Deploy the entry point under a process supervisor (systemd, supervisord, a Docker container, or the platform equivalent) so the worker is restarted if it exits.

## Documentation

Full PHPNomad documentation lives at [phpnomad.com](https://phpnomad.com). Reference for the underlying PHP Redis extension lives at [php.net/manual/en/book.redis.php](https://www.php.net/manual/en/book.redis.php).

## License

MIT. See [LICENSE.txt](LICENSE.txt).
