<?php

namespace App\Services\SYSTEM\TaskProcessing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsappProviderClient
{
    public function send(array $payload): array
    {
        $token = (string) config('services.project_config.whatsapp.api_token', env('API_TOKEN', ''));
        $phoneId = (string) config('services.project_config.whatsapp.phone_id', env('Phone_Id', ''));
        if ($token === '' || $phoneId === '') {
            throw new PermanentTaskException('Konfigurasi WhatsApp API belum lengkap.');
        }

        $connectTimeout = (int) config('services.project_config.whatsapp.connect_timeout', env('WHATSAPP_CONNECT_TIMEOUT', 5));
        $timeout = (int) config('services.project_config.whatsapp.timeout', env('WHATSAPP_REQUEST_TIMEOUT', 10));
        $endpoint = (string) config('services.project_config.whatsapp.endpoint', $phoneId);
        $response = Http::connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->post($endpoint, $payload);

        return $this->normalizeResponse($response);
    }

    private function normalizeResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];
        $statusCode = $response->status();

        if ($response->successful()) {
            $messageId = data_get($body, 'messages.0.id');
            $providerStatus = data_get($body, 'messages.0.message_status', 'accepted');
            if (! $messageId) {
                throw new PermanentTaskException('WhatsApp response sukses tidak memiliki message ID.');
            }

            return ['name' => 'WHATSAPP_META', 'status' => (string) $providerStatus, 'message_id' => (string) $messageId, 'response' => $body];
        }

        $errorMessage = (string) (data_get($body, 'error.message') ?: $response->body());
        if (in_array($statusCode, [408, 425, 429], true) || $statusCode >= 500) {
            throw new RetryableTaskException('WhatsApp temporary failure (HTTP '.$statusCode.'): '.$errorMessage);
        }

        throw new PermanentTaskException('WhatsApp permanent failure (HTTP '.$statusCode.'): '.$errorMessage);
    }
}
