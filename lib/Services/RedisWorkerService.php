<?php

namespace PHPNomad\Redis\Tasks\Integration\Services;


use PHPNomad\Redis\Tasks\Integration\Registries\RedisTaskHandlerRegistry;
use PHPNomad\Tasks\Interfaces\Task;
use Redis;

class RedisWorkerService
{

    public const QUEUE = 'phpnomad.tasks';

    public function __construct(
        protected Redis $redis,
        protected RedisTaskHandlerRegistry $registry
    ) {}

    public function watch(): void
    {
        while (true) {
            $data = $this->redis->blPop([static::QUEUE], 0);
            if (!isset($data[1])) {
                continue;
            }

            $task = unserialize($data[1]);

            if (!$task instanceof Task) {
                continue;
            }

            foreach ($this->registry->getHandlers($task) as $handler) {
                $handler($task);
            }
        }
    }
}