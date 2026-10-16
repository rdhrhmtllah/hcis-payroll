<?php

namespace App\Jobs\SYSTEM\Audit\Activity;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoLookupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $ip) {}

    public function handle(): void
    {
        $ip = trim($this->ip);
        if ($ip === '' || $this->isPrivate($ip)) {
            return;
        }

        $conn = (string) config('activity-tracker.db_connection', config('database.default'));
        $driver = (string) config('activity-tracker.geo.driver', 'ipapi');

        $data = null;
        try {
            $data = match ($driver) {
                'ipapi' => $this->lookupIpapi($ip),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::debug('Geo lookup error', ['ip' => $ip, 'error' => $e->getMessage()]);

            return;
        }

        if (! $data) {
            return;
        }

        $now = now();
        $expires = $now->copy()->addDays((int) config('activity-tracker.geo.cache_days', 30));

        try {
            DB::connection($conn)->table('N_HRIS_Activity_Geo_Cache')->updateOrInsert(
                ['Ip_Address' => $ip],
                [
                    'Country' => $data['country'] ?? null,
                    'City' => $data['city'] ?? null,
                    'Region' => $data['region'] ?? null,
                    'Isp' => $data['isp'] ?? null,
                    'Latitude' => $data['latitude'] ?? null,
                    'Longitude' => $data['longitude'] ?? null,
                    'Source' => $driver,
                    'Resolved_At' => $now,
                    'Expires_At' => $expires,
                ],
            );

            // Backfill sesi yang IP-nya ini & masih kosong geo-nya
            DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->where('Ip_Address', $ip)
                ->whereNull('Ip_Country')
                ->update([
                    'Ip_Country' => $data['country'] ?? null,
                    'Ip_City' => $data['city'] ?? null,
                    'Ip_Isp' => $data['isp'] ?? null,
                    'Geo_Source' => 'ip',
                    'Updated_At' => $now,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Geo lookup persist failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }
    }

    protected function lookupIpapi(string $ip): ?array
    {
        $timeout = (int) config('activity-tracker.geo.timeout_sec', 3);
        $resp = Http::timeout($timeout)->acceptJson()->get("https://ipapi.co/{$ip}/json/");
        if (! $resp->ok()) {
            return null;
        }
        $json = $resp->json();
        if (! is_array($json) || isset($json['error'])) {
            return null;
        }

        return [
            'country' => $json['country_code'] ?? null,
            'city' => $json['city'] ?? null,
            'region' => $json['region'] ?? null,
            'isp' => $json['org'] ?? null,
            'latitude' => isset($json['latitude']) ? (float) $json['latitude'] : null,
            'longitude' => isset($json['longitude']) ? (float) $json['longitude'] : null,
        ];
    }

    protected function isPrivate(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
