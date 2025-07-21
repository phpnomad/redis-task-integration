<?php

namespace PHPNomad\Redis\Tasks\Integration\Registries;

use PHPNomad\Tasks\Interfaces\Task;

class RedisTaskHandlerRegistry
{
    protected array $handlers = [];

    /**
     * @param class-string<Task> $taskClass
     * @param callable $handler
     * @return void
     */
    public function attach(string $taskClass, callable $handler): void
    {
        $this->handlers[$taskClass::getId()][] = $handler;
    }

    public function getHandlers(Task $task): array
    {
        return $this->handlers[$task::getId()] ?? [];
    }
}