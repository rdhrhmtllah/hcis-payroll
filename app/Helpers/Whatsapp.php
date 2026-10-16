<?php

namespace App\Helpers;

use App\Services\SYSTEM\Notification\WhatsappNotificationMirrorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Whatsapp
{
    public static function send_message($data, array $context = [])
    {
        // Peringatan anti-regresi (non-produksi): memanggil WA saat transaksi DB
        // masih terbuka menahan lock selama HTTP (~10-20 dtk) → penyebab
        // "Login timeout expired". Bungkus pemanggil dengan DB::afterCommit atau
        // kirim setelah DB::commit(). WhatsappLogHelper::sendAndLog sudah menunda
        // otomatis; peringatan ini menyasar pemanggil Whatsapp::send_message langsung.
        if (DB::transactionLevel() > 0 && ! app()->isProduction()) {
            Log::warning('Whatsapp::send_message dipanggil di dalam transaksi DB (menahan lock selama HTTP). Bungkus dengan DB::afterCommit.', [
                'transaction_level' => DB::transactionLevel(),
                'context' => $context,
            ]);
        }

        $mirror = self::mirrorAttempt((array) $data, $context);

        // Ambil URL dan API Token dari file .env
        $apiToken = env('API_TOKEN');
        $phoneId = env('Phone_Id');
        $connectTimeout = (int) env('WHATSAPP_CONNECT_TIMEOUT', 5);
        $requestTimeout = (int) env('WHATSAPP_REQUEST_TIMEOUT', 10);
        $retryTimes = (int) env('WHATSAPP_RETRY_TIMES', 1);
        $retrySleepMs = (int) env('WHATSAPP_RETRY_SLEEP_MS', 200);

        // dd($data, $apiToken, $phoneId);
        // Pastikan API Token dan Phone ID tersedia
        if (empty($apiToken) || empty($phoneId)) {
            $message = 'API Token or Phone Id is not configured properly in .env file.';
            self::mirrorFailed($mirror, $message);
            throw new \Exception($message);
        }

        $failedMarked = false;
        try {
            // Kirim permintaan POST ke API WhatsApp
            $response = Http::connectTimeout($connectTimeout)
                ->timeout($requestTimeout)
                ->retry($retryTimes, $retrySleepMs)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$apiToken,
                ])
                ->post($phoneId, $data);

            // Log response atau error
            if ($response->successful()) {
                $json = $response->json();
                self::mirrorSuccess($mirror, is_array($json) ? $json : []);

                return $json; // Kembalikan response yang berhasil
            }

            $message = 'Failed to send message: '.$response->body();
            self::mirrorFailed($mirror, $message);
            $failedMarked = true;
            \Log::error('WhatsApp API error: '.$response->body());
            throw new \Exception($message);
        } catch (\Throwable $e) {
            if (! $failedMarked) {
                self::mirrorFailed($mirror, $e->getMessage());
            }
            throw $e;
        }
    }

    protected static function mirrorAttempt(array $data, array $context): ?array
    {
        try {
            return app(WhatsappNotificationMirrorService::class)->mirrorAttempt($data, $context);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp mirror attempt failed: '.$e->getMessage());

            return null;
        }
    }

    protected static function mirrorSuccess(?array $mirror, array $response): void
    {
        try {
            app(WhatsappNotificationMirrorService::class)->markSuccess($mirror, $response);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp mirror success update failed: '.$e->getMessage());
        }
    }

    protected static function mirrorFailed(?array $mirror, string $reason): void
    {
        try {
            app(WhatsappNotificationMirrorService::class)->markFailed($mirror, $reason);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp mirror failed update failed: '.$e->getMessage());
        }
    }
}
