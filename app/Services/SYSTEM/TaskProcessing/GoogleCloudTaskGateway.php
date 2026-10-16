<?php

namespace App\Services\SYSTEM\TaskProcessing;

use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\CreateTaskRequest;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use RuntimeException;

class GoogleCloudTaskGateway implements CloudTaskGateway
{
    private ?CloudTasksClient $client = null;

    public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
    {
        if ($queue === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $url)) {
            throw new RuntimeException('Queue atau URL Cloud Task tidak valid.');
        }

        $project = (string) config('services.project_config.gcp.project_id', '');
        $location = (string) config('services.project_config.gcp.location', '');
        $handlerToken = (string) config('services.project_config.OUTBOX_HANDLER_BEARER_TOKEN', '');
        if ($project === '' || $location === '' || $handlerToken === '') {
            throw new RuntimeException('Konfigurasi project/location/token Outbox Google Cloud belum tersedia.');
        }

        $request = new CreateTaskRequest;
        $request->setParent(CloudTasksClient::queueName($project, $location, $queue));

        $http = new HttpRequest;
        $http->setUrl($url);
        $http->setHttpMethod(1); // POST
        $http->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.config('services.project_config.OUTBOX_HANDLER_BEARER_TOKEN'),
        ]);
        $http->setBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $task = new Task;
        $task->setHttpRequest($http);
        if ($availableAt !== null) {
            $timestamp = new Timestamp;
            $timestamp->setSeconds($availableAt->getTimestamp());
            $task->setScheduleTime($timestamp);
        }
        $request->setTask($task);

        $created = $this->client()->createTask($request);
        $name = trim((string) $created->getName());
        if ($name === '') {
            throw new RuntimeException('Google Cloud Tasks tidak mengembalikan nama task.');
        }

        return $name;
    }

    private function client(): CloudTasksClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $options = [];
        $keyPath = config('services.project_config.gcp.key_path');
        if (is_string($keyPath) && $keyPath !== '' && file_exists($keyPath)) {
            $options['credentials'] = $keyPath;
        }

        return $this->client = new CloudTasksClient($options);
    }
}
