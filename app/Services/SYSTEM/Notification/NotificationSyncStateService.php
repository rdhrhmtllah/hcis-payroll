<?php

namespace App\Services\SYSTEM\Notification;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationSyncStateService
{
    private const TABLE = 'N_HRIS_Notification_Sync_State';

    public function getState(string $kodeKaryawan, string $channel, string $scope, string $periodKey): ?object
    {
        return DB::table(self::TABLE)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Channel', $channel)
            ->where('Scope', $scope)
            ->where('Period_Key', $periodKey)
            ->first();
    }

    public function upsertState(
        string $kodeKaryawan,
        string $channel,
        string $scope,
        string $periodKey,
        Carbon|string|null $processedUntil,
        array $meta = [],
    ): void {
        $now = now();
        $processedDate = $processedUntil instanceof Carbon
            ? $processedUntil->toDateString()
            : ($processedUntil ? Carbon::parse($processedUntil)->toDateString() : null);
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $existing = $this->getState($kodeKaryawan, $channel, $scope, $periodKey);
        if ($existing) {
            DB::table(self::TABLE)
                ->where('Id_Sync_State', $existing->Id_Sync_State)
                ->update([
                    'Processed_Until' => $processedDate,
                    'Last_Synced_At' => $now,
                    'Meta_JSON' => $metaJson,
                    'Updated_At' => $now,
                ]);

            return;
        }

        DB::table(self::TABLE)->insert([
            'Kode_Karyawan' => $kodeKaryawan,
            'Channel' => $channel,
            'Scope' => $scope,
            'Period_Key' => $periodKey,
            'Processed_Until' => $processedDate,
            'Last_Synced_At' => $now,
            'Meta_JSON' => $metaJson,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);
    }
}
