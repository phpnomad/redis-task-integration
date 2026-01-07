<?php

namespace PHPNomad\Redis\Tasks\Integration\Services;


use PHPNomad\Auth\Interfaces\SecretProvider;
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Redis\Tasks\Integration\Registries\RedisTaskHandlerRegistry;
use PHPNomad\Tasks\Exceptions\TaskCreateFailedException;
use PHPNomad\Tasks\Interfaces\IdempotencyStore;
use PHPNomad\Tasks\Interfaces\IsIdempotent;
use PHPNomad\Tasks\Interfaces\Task;
use Redis;

class RedisWorkerService
{

    public const QUEUE = 'phpnomad.tasks';

    public function __construct(
        protected Redis $redis,
        protected RedisTaskHandlerRegistry $registry,
        protected LoggerStrategy $logger,
        protected SecretProvider $secretProvider,
        protected IdempotencyStore $idempotencyStore
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

                $decoded = json_decode($data[1], true);

                if (!is_array($decoded) || count($decoded) !== 3) {
                    $this->logger->error('Task ignored because data format is invalid');
                    continue;
                }

                [$payload, $taskClass, $sig] = $decoded;

                if (!is_subclass_of($taskClass, Task::class)) {
                    $this->logger->error('Task ignored because it is not a subclass of Task', ['taskClass' => $taskClass]);
                    continue;
                }

                $expected = $this->generateSignature($taskClass, $payload);
                if ($expected === null || !hash_equals($expected, $sig)) {
                    $this->logger->notice('Invalid task signature for ' . $taskClass::getId());
                    continue;
                }

                try {
                    $task = $taskClass::fromPayload($payload);
                } catch (TaskCreateFailedException $e) {
                    $this->logger->logException($e);
                    continue;
                }

                $this->handleTask($task);
            } catch (\Throwable $e) {
                $this->logger->logException($e);
                sleep(1);
            }
        }
    }

    /**
     * Handle task execution with idempotency support
     *
     * @param Task $task
     * @return void
     */
    protected function handleTask(Task $task): void
    {
        $locked = false;

        try {
            if ($task instanceof IsIdempotent) {
                if ($this->idempotencyStore->isDone($task)) {
                    return;
                }

                if (!$this->idempotencyStore->acquire($task, 600)) {
                    return;
                }

                $locked = true;
            }

            foreach ($this->registry->getHandlers($task) as $handler) {
                try {
                    $this->logger->info('Running handler for task', ['task' => $task::getId()]);
                    $handler($task);
                } catch (\Throwable $e) {
                    $this->logger->logException($e);
                    throw $e;
                }
            }

            if ($task instanceof IsIdempotent) {
                $this->idempotencyStore->markDone($task, $task->idempotencyTtlSeconds());
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            if ($locked && $task instanceof IsIdempotent) {
                $this->idempotencyStore->release($task);
            }
        }
    }

    /**
     * @param class-string<Task> $taskClass
     * @param array $payload
     * @return string|null
     */
    private function generateSignature(string $taskClass, array $payload): ?string
    {
        $json = json_encode($payload);
        if ($json === false) {
            return null;
        }

        $message = $taskClass . '|' . $taskClass . '|' . $json;

        return hash_hmac('sha256', $message, $this->secretProvider->getSecret());
    }
}