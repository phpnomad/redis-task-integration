<?php

namespace PHPNomad\Redis\Tasks\Integration\Strategies;

use PHPNomad\Redis\Tasks\Integration\Registries\RedisTaskHandlerRegistry;
use PHPNomad\Redis\Tasks\Integration\Services\RedisWorkerService;
use PHPNomad\Tasks\Interfaces\Task;
use PHPNomad\Tasks\Interfaces\TaskStrategy;
use Redis;

class RedisTaskStrategy implements TaskStrategy
{
    public function __construct(
        protected Redis $redis,
        protected RedisTaskHandlerRegistry $registry
    ) {}

    public function dispatch(object $task): void
    {
        if (!$task instanceof Task) {
            throw new \InvalidArgumentException('Task must implement Task interface');
        }

        $this->redis->rPush(RedisWorkerService::QUEUE, serialize($task));
    }

    public function attach(string $taskClass, callable $handler): void
    {
        $this->registry->attach($taskClass, $handler);
    }
}