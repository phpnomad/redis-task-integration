<?php

namespace PHPNomad\Redis\Tasks\Integration\Services;


use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Redis\Tasks\Integration\Registries\RedisTaskHandlerRegistry;
use PHPNomad\Tasks\Interfaces\Task;
use Redis;

class RedisWorkerService
{

    public const QUEUE = 'phpnomad.tasks';

    public function __construct(
        protected Redis $redis,
        protected RedisTaskHandlerRegistry $registry,
        protected LoggerStrategy $logger
    ) {}

    public function watch(): void
    {
        while (true) {
            try {
                $this->logger->info('Waiting for events...');
                $data = $this->redis->blPop([static::QUEUE], 0);
                if (!isset($data[1])) {
                    continue;
                }

                $task = unserialize($data[1]);

                if (!$task instanceof Task) {
                    $this->logger->error('Task ignored because it is not an instance of Task',['instance' => $task]);
                    continue;
                }

                foreach ($this->registry->getHandlers($task) as $handler) {
                    $this->logger->info('Running handler for task', ['task' => $task::getId(), 'handler' => $handler::class]);
                    $handler($task);
                }
            } catch (\Throwable $e) {
                $this->logger->logException($e);
                sleep(1);
            }
        }
    }
}