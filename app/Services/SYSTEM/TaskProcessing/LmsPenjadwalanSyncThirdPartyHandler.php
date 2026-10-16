<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Helpers\SignatureHelper;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Menyelaraskan satu aktivitas ujian penjadwalan ke HC Learn — DI LUAR transaksi.
 *
 * KENAPA HANDLER, BUKAN PANGGILAN LANGSUNG.
 * `TrainingSchedulingController::update()` dulu memanggil HC Learn di dalam
 * perulangan bersarang, DI DALAM transaksi yang sudah terbuka sejak awal metode.
 * Selama panggilan itu berlangsung seluruh kunci transaksi tetap dipegang, dan
 * lamanya ditentukan pihak lain: `timeout` 8 detik dikali jumlah aktivitas.
 * Pada basis data tanpa RCSI, setiap pembaca baris yang sama menunggu selama
 * itu. Itu temuan #2 di register audit.
 *
 * Sekarang controller hanya MENITIPKAN pekerjaan ke outbox — satu baris, di
 * dalam transaksi yang sama dengan perubahan datanya, sehingga keduanya jadi
 * atau batal bersama-sama dan tak pernah ada task yatim. Panggilan keluarnya
 * dikerjakan di sini, sesudah commit, tanpa satu kunci pun tertahan, dan
 * dicoba ulang bila gagal sementara.
 *
 * DUA MODE, SATU HANDLER.
 * - `update` : aktivitas sudah punya token; HC Learn diminta memperbarui.
 * - `store`  : aktivitas baru; HC Learn diminta menerbitkan token pertama.
 * Keduanya berakhir sama — menuliskan `Id_Ujian_Token` dan `Payload_Json` ke
 * baris integrasi yang bersangkutan.
 */
class LmsPenjadwalanSyncThirdPartyHandler implements TaskHandler
{
    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        $mode = (string) ($payload['mode'] ?? '');
        if (! in_array($mode, ['update', 'store'], true)) {
            throw new PermanentTaskException("Mode sinkron tidak dikenal: '{$mode}'.");
        }

        $idIntegrasi = (int) ($payload['id_integrasi'] ?? 0);
        if ($idIntegrasi < 1) {
            throw new PermanentTaskException('id_integrasi wajib ada pada payload.');
        }

        /* Baris integrasinya dibaca ULANG di sini, bukan dipercaya dari payload.
           Antara transaksi ditutup dan task ini berjalan, penjadwalannya bisa
           saja sudah disunting lagi atau dicabut — dan menimpa token milik
           keadaan yang sudah berubah lebih buruk daripada tidak menulis
           apa pun. */
        $integrasi = DB::table('N_LMS_Penjadwalan_Integrasi')
            ->where('Id_Penjadwalan_Integrasi', $idIntegrasi)
            ->first();

        if (! $integrasi) {
            throw new PermanentTaskException(
                "Baris integrasi {$idIntegrasi} sudah tidak ada; penjadwalannya kemungkinan dicabut."
            );
        }

        $endpoint = $mode === 'update'
            ? 'api/v1/penjadwalan/training/update/'.$payload['token_lama']
            : 'api/v1/penjadwalan/training/store';

        $domain = rtrim((string) config('services.frans.hclearn_api.domain', config('app.url')), '/');
        $url = $domain.'/'.ltrim($endpoint, '/');
        $appId = (string) ($payload['app_id'] ?? config('app.key'));
        $authEmployee = (string) ($payload['kode_karyawan_auth'] ?? 'SYSTEM');

        $apiPayload = $mode === 'update'
            ? [
                'Id_Master_Ujian' => $payload['id_master_ujian'],
                'Waktu_Mulai' => $payload['waktu_mulai'],
                'Waktu_Akhir' => $payload['waktu_akhir'] ?? null,
                'User_Agent' => $payload['user_agent'] ?? '',
                'Flag_Camera' => ! empty($payload['flag_camera']) ? true : null,
                'Deleted_Tokens' => $payload['deleted_tokens'] ?? [],
            ]
            : [
                'Id_Integrasi' => $idIntegrasi,
                'Id_Master_Ujian' => $payload['id_master_ujian'],
                'Id_Karyawan' => [$payload['kode_karyawan']],
                'Data_Karyawan' => $payload['data_karyawan'] ?? [],
                'Waktu_Mulai' => $payload['waktu_mulai'],
                'Waktu_Akhir' => $payload['waktu_akhir'] ?? null,
                'Jenis_User' => 'internal',
                'Flag_Camera' => ! empty($payload['flag_camera']) ? true : null,
                'User_Agent' => $payload['user_agent'] ?? '',
                'Deleted_Tokens' => $payload['deleted_tokens'] ?? [],
            ];

        try {
            $auth = SignatureHelper::store($endpoint, $apiPayload, $appId, $authEmployee);
            $response = Http::connectTimeout((int) config('services.lms_third_party.connect_timeout', 5))
                ->timeout((int) config('services.lms_third_party.timeout', 15))
                ->withHeaders($auth['headers'])
                ->withBody($auth['body'], 'application/json')
                ->post($url);
        } catch (Throwable $exception) {
            throw new RetryableTaskException(
                'HC Learn tidak dapat dihubungi saat sinkron penjadwalan: '.$exception->getMessage()
            );
        }

        if (! $response->successful()) {
            $pesan = substr($response->body(), 0, 4000);

            /* Gagal SEMENTARA dicoba ulang; gagal karena permintaannya sendiri
               salah tidak — mengulanginya hanya menghabiskan percobaan dan
               menunda pesan galat yang sebenarnya. */
            if (in_array($response->status(), [408, 425, 429], true) || $response->serverError()) {
                throw new RetryableTaskException(
                    'HC Learn gagal sementara (HTTP '.$response->status().'): '.$pesan
                );
            }

            throw new PermanentTaskException(
                'HC Learn menolak permintaan (HTTP '.$response->status().'): '.$pesan
            );
        }

        $badan = $response->json();
        $butir = $badan['result'] ?? ($badan['data'] ?? []);
        $item = collect(is_array($butir) ? $butir : [])
            ->first(fn ($v) => is_array($v) && ! empty($v['Id_Ujian_Token']));

        if (! is_array($item) || empty($item['Id_Ujian_Token'])) {
            throw new PermanentTaskException('HC Learn tidak mengembalikan Id_Ujian_Token.');
        }

        $tokenBaru = (string) $item['Id_Ujian_Token'];
        $payloadJson = is_array($item['Payload_Json'] ?? null)
            ? json_encode($item['Payload_Json'], JSON_UNESCAPED_UNICODE)
            : ($item['Payload_Json'] ?? null);

        $nilai = ['Id_Ujian_Token' => $tokenBaru];
        if ($payloadJson !== null) {
            $nilai['Payload_Json'] = $payloadJson;
        }

        $terubah = DB::table('N_LMS_Penjadwalan_Integrasi')
            ->where('Id_Penjadwalan_Integrasi', $idIntegrasi)
            ->update($nilai);

        if ($terubah !== 1) {
            throw new PermanentTaskException(
                "Baris integrasi {$idIntegrasi} tidak terperbarui; datanya kemungkinan sudah berubah."
            );
        }

        return ['mode' => $mode, 'id_integrasi' => $idIntegrasi, 'token' => $tokenBaru];
    }
}
