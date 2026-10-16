<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Helpers\SignatureHelper;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class LmsReassignThirdPartyHandler implements TaskHandler
{
    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        $endpoint = 'api/v1/penjadwalan/training/reassign';
        $domain = rtrim((string) config('services.frans.hclearn_api.domain', config('app.url')), '/');
        $url = $domain.'/'.ltrim($endpoint, '/');
        $appId = (string) ($payload['app_id'] ?? config('app.key'));
        $authEmployee = (string) ($payload['kode_karyawan_auth'] ?? 'SYSTEM');

        $apiPayload = [
            'Id_Integrasi' => [[
                'Kode_Karyawan' => $payload['kode_karyawan'],
                'Id_Integrasi' => $payload['integration_id'],
            ]],
            'Id_Master_Ujian' => $payload['id_master_ujian'],
            'Id_Karyawan' => [$payload['kode_karyawan']],
            'Data_Karyawan' => $payload['data_karyawan'] ?? [],
            'Waktu_Mulai' => $payload['waktu_mulai'],
            'Waktu_Akhir' => $payload['waktu_akhir'] ?? null,
            'Jenis_User' => 'internal',
            'Flag_Camera' => ! empty($payload['flag_camera']) ? 'Y' : null,
            'User_Agent' => $payload['user_agent'] ?? '',
            'Id_Ujian_Token_Lama' => $payload['old_token'],
        ];

        try {
            $auth = SignatureHelper::store($endpoint, $apiPayload, $appId, $authEmployee);
            $response = Http::connectTimeout(5)->timeout(15)
                ->withHeaders($auth['headers'])
                ->withBody($auth['body'], 'application/json')
                ->post($url);
        } catch (Throwable $exception) {
            throw new RetryableTaskException('Third party reassign tidak dapat dihubungi: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            $message = substr($response->body(), 0, 4000);
            if ($response->status() === 408 || $response->status() === 425 || $response->status() === 429 || $response->serverError()) {
                throw new RetryableTaskException('Third party reassign temporary failure (HTTP '.$response->status().'): '.$message);
            }
            throw new PermanentTaskException('Third party reassign permanent failure (HTTP '.$response->status().'): '.$message);
        }

        $body = $response->json();
        $items = $body['result'] ?? ($body['data'] ?? []);
        $item = collect(is_array($items) ? $items : [])->first(fn ($value) => is_array($value) && ! empty($value['Id_Ujian_Token']));
        if (! is_array($item) || empty($item['Id_Ujian_Token'])) {
            throw new PermanentTaskException('Third party tidak mengembalikan Token baru.');
        }

        $newToken = (string) $item['Id_Ujian_Token'];
        $payloadJson = is_array($item['Payload_Json'] ?? null)
            ? json_encode($item['Payload_Json'], JSON_UNESCAPED_UNICODE)
            : ($item['Payload_Json'] ?? null);

        DB::transaction(function () use ($payload, $newToken, $payloadJson): void {
            $updated = DB::table('N_LMS_Penjadwalan_Integrasi')
                ->where('Id_Penjadwalan_Integrasi', $payload['integration_id'])
                ->where('Id_Penjadwalan_Det', $payload['id_penjadwalan_det'])
                ->where('Id_Ujian_Token', $payload['old_token'])
                ->update([
                    'Id_Master_Ujian' => $payload['id_master_ujian'],
                    'Waktu_Mulai' => $payload['waktu_mulai'],
                    'Waktu_Akhir' => $payload['waktu_akhir'] ?? null,
                    'Id_Ujian_Token' => $newToken,
                    'Payload_Json' => $payloadJson,
                ]);

            if ($updated !== 1) {
                throw new PermanentTaskException('Data integrasi berubah atau token lama tidak lagi cocok.');
            }

            DB::table('N_LMS_Penjadwalan_Det')
                ->where('Id_Penjadwalan_Det', $payload['id_penjadwalan_det'])
                ->update([
                    'Waktu_Mulai_Akses' => $payload['waktu_mulai'],
                    'Waktu_Batas_Akses' => $payload['waktu_akhir'] ?? null,
                ]);
        });

        return ['new_token' => $newToken, 'payload_json' => $payloadJson];
    }
}
