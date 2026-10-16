<?php

namespace App\Services\SYSTEM\TaskProcessing;

use RuntimeException;

class TaskHandlerRegistry
{
    /** @var array<string, TaskHandler> */
    private array $handlers = [];

    public function register(string $taskType, TaskHandler $handler): void
    {
        if ($taskType === '') {
            throw new RuntimeException('Task type handler tidak boleh kosong.');
        }

        $this->handlers[$taskType] = $handler;
    }

    public function resolve(string $taskType): TaskHandler
    {
        if (! isset($this->handlers[$taskType])) {
            throw new PermanentTaskException('Tidak ada handler untuk task type: '.$taskType);
        }

        return $this->handlers[$taskType];
    }
}
