<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthorizationAuditLogger
{
    public static function logMutation(array $payload): void
    {
        if (! config('authorization.audit.enabled', true)) {
            return;
        }

        Log::channel(config('authorization.audit.channel', 'stack'))->info('authorization.mutation', [
            'event' => $payload['event'] ?? 'authorization.mutation',
            'actor_user_id' => $payload['actor_user_id'] ?? null,
            'target_user_id' => $payload['target_user_id'] ?? null,
            'grup_id' => $payload['grup_id'] ?? null,
            'page_access_id' => $payload['page_access_id'] ?? null,
            'jenis_page' => $payload['jenis_page'] ?? null,
            'action_id' => $payload['action_id'] ?? null,
            'old_value' => $payload['old_value'] ?? null,
            'new_value' => $payload['new_value'] ?? null,
            'affected_count' => $payload['affected_count'] ?? null,
            'details' => $payload['details'] ?? null,
            'source' => $payload['source'] ?? 'authorization-governance',
            'occurred_at' => now()->toDateTimeString(),
        ]);

        // RBAC Fase 1: mutasi juga masuk tabel audit bila sudah ada (DDL-2026-164).
        // Pemberian/override menu PER USER (menu_access.*, menu_permission.*)
        // adalah GRANT; perubahan bawaan halaman (menu_capabilities.*) KATALOG.
        try {
            $event = (string) ($payload['event'] ?? 'authorization.mutation');
            $jenis = preg_match('/^menu_(access|permission)\./', $event) ? 'GRANT' : 'KATALOG';
            $subjek = $payload['jenis_page'] ?? $payload['page_access_id'] ?? $payload['grup_id'] ?? null;
            $sesudah = array_filter([
                'nilai' => $payload['new_value'] ?? null,
                'details' => $payload['details'] ?? null,
                'affected_count' => $payload['affected_count'] ?? null,
                'action_id' => $payload['action_id'] ?? null,
                'grup_id' => $payload['grup_id'] ?? null,
            ], fn ($v) => $v !== null);

            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                $jenis,
                $subjek !== null ? (string) $subjek : null,
                $event,
                $payload['old_value'] ?? null,
                $sesudah !== [] ? $sesudah : null,
                isset($payload['target_user_id']) ? (int) $payload['target_user_id'] : null,
                null,
                (string) ($payload['source'] ?? 'authorization-governance'),
                isset($payload['actor_user_id']) ? (int) $payload['actor_user_id'] : null,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function logDecision(array $payload): void
    {
        if (! config('authorization.audit.enabled', true)) {
            return;
        }

        $logPayload = [
            'user_id' => $payload['userId'] ?? null,
            'context_code' => $payload['contextCode'] ?? null,
            'jenis_page' => $payload['pageType'] ?? null,
            'resource_type' => $payload['resourceType'] ?? null,
            'resource_code' => $payload['resourceCode'] ?? null,
            'action' => $payload['action'] ?? null,
            'allowed' => (bool) ($payload['allowed'] ?? false),
            'reason' => $payload['reason'] ?? 'n/a',
            'source' => $payload['source'] ?? 'authorization-resolver',
            'occurred_at' => now()->toDateTimeString(),
        ];

        $canWriteDb = config('authorization.audit.db_enabled', false) && SkemaTersedia::tabel('N_HRIS_Authorization_Audit');

        if ($canWriteDb) {
            DB::table('N_HRIS_Authorization_Audit')->insert([
                'UserID_Web' => $logPayload['user_id'],
                'Context_Code' => $logPayload['context_code'],
                'Jenis_page' => $logPayload['jenis_page'],
                'Resource_Type' => $logPayload['resource_type'],
                'Resource_Code' => $logPayload['resource_code'],
                'Action_Code' => $logPayload['action'],
                'Decision' => $logPayload['allowed'] ? 'ALLOW' : 'DENY',
                'Reason' => $logPayload['reason'],
                'Source' => $logPayload['source'],
                'Created_At' => now(),
            ]);

            return;
        }

        Log::channel(config('authorization.audit.channel', 'stack'))->info('authorization.decision', $logPayload);
    }
}
