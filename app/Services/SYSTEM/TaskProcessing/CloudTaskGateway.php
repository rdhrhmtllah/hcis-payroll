<?php

namespace App\Services\SYSTEM\TaskProcessing;

interface CloudTaskGateway
{
    /** @return string The provider-generated Cloud Task name. */
    public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string;
}
