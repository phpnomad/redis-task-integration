<?php

namespace PHPNomad\Redis\Tasks\Integration\Strategies;

use PHPNomad\Auth\Interfaces\SecretProvider;
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Redis\Tasks\Integration\Registries\RedisTaskHandlerRegistry;
use PHPNomad\Redis\Tasks\Integration\Services\RedisWorkerService;
use PHPNomad\Tasks\Exceptions\TaskDispatchFailedException;
use PHPNomad\Tasks\Interfaces\IdempotencyStore;
use PHPNomad\Tasks\Interfaces\Task;
use PHPNomad\Tasks\Interfaces\TaskStrategy;
use Redis;

class RedisTaskStrategy implements TaskStrategy
{
    public function __construct(
        protected Redis $redis,
        protected RedisTaskHandlerRegistry $registry,
        protected LoggerStrategy $logger,
        protected SecretProvider $secretProvider,
        protected IdempotencyStore $idempotencyStore
    ) {}

    /**
     * @inheritDoc
     * @throws TaskDispatchFailedException
     */
    public function dispatch(object $task): void
    {
        if (!$task instanceof Task) {
            throw new TaskDispatchFailedException('Task must implement Task interface');
        }

        $payload = $task->toPayload();
        $taskClass = $task::class;

        $sig = $this->generateSignature($taskClass, $payload);

        if (!$sig) {
            throw new TaskDispatchFailedException('Task payload is not JSON-encodable.');
        }

        $data = json_encode([$payload, $taskClass, $sig]);

        if ($data === false) {
            throw new TaskDispatchFailedException('Failed to encode task data.');
        }

        $this->redis->rPush(RedisWorkerService::QUEUE, $data);
    }

    public function attach(string $taskClass, callable $handler): void
    {
        $this->registry->attach($taskClass, $handler);
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