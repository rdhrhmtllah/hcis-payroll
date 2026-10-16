<?php

namespace App\Http\Controllers\LMS\Content\Verification;

use App\Http\Controllers\Controller;
use App\Services\ESS\Activity\Center\UploadTaskService;
use App\Services\LMS\Content\Verification\KontenVerifierService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Cloud Task untuk verifikasi mendalam berkas Library Konten.
 *
 * Dipanggil Google Cloud Tasks (bukan browser) → dijaga bearer token, tanpa
 * session/CSRF, rutenya di grup api.
 *
 * Di sinilah pemeriksaan yang menuntut SELURUH isi berkas dikerjakan — struktur
 * arsip OOXML, makro, aksi otomatis PDF, bom dekompresi gambar. Semuanya sengaja
 * di luar jalur permintaan: membaca berkas 50 MB di dalam request akan menahan
 * satu worker selama pemeriksaan.
 *
 * Berkasnya berada di bucket KARANTINA sampai lolos. Kalau lolos ia dipindahkan
 * ke bucket penyajian; kalau tidak, objeknya dihapus dan alasannya dicatat.
 */
class KontenVerifikasiWebhookController extends Controller
{
    public function __construct(private KontenVerifierService $verifier) {}

    /** POST /api/v1/tasks/konten/verifikasi */
    public function verifikasi(Request $request)
    {
        if ($request->bearerToken() !== config('services.project_config.SCHEDULER_BEARER_TOKEN')) {
            Log::warning('Akses tak sah ke webhook verifikasi konten');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        set_time_limit(0);

        $berkasId = (int) $request->input('berkas_id');
        $logId = $request->input('_cloud_task_log_id');

        if (! $berkasId || ! $logId) {
            return response()->json(['message' => 'Payload tidak lengkap'], 400);
        }

        $tasks = app(UploadTaskService::class);

        try {
            CloudTaskService::markProcessing($logId);

            $berkas = DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->first(['Id_Master_Konten_Berkas', 'Id_Master_Konten', 'File_Path', 'Ext', 'Status_Verifikasi']);

            if (! $berkas) {
                // Barisnya lenyap (dihapus admin sementara task antre). Bukan
                // kegagalan yang perlu diulang.
                CloudTaskService::markCompleted($logId, 0, 0, 0, ['catatan' => 'berkas sudah tidak ada']);

                return response()->json(['success' => true, 'catatan' => 'berkas tidak ada'], 200);
            }

            if ($berkas->Status_Verifikasi === 'SIAP') {
                // Sudah pernah lolos — task diulang Cloud Tasks setelah promosi
                // berhasil tapi responsnya tak sampai. Idempoten: tak ada yang
                // perlu dikerjakan lagi.
                CloudTaskService::markCompleted($logId, 1, 1, 0, ['catatan' => 'sudah terverifikasi']);

                return response()->json(['success' => true, 'catatan' => 'sudah terverifikasi'], 200);
            }

            $tasks->tandaiTahap($berkasId, 'Memindai isi', 78);

            $hasil = $this->verifier->verifikasiDalam(
                (string) $berkas->File_Path,
                (string) $berkas->Ext,
                KontenVerifierService::DISK_KARANTINA,
            );

            /*
             * Objek tak terjangkau BUKAN berkas yang ditolak — itu gangguan
             * penyimpanan. Balas 500 supaya Cloud Tasks mengulang, dan biarkan
             * statusnya tetap MENUNGGU. Mencapnya DITOLAK di sini berarti satu
             * gangguan GCS sesaat memusnahkan unggahan yang sehat.
             */
            if (! $hasil['ok'] && ! $hasil['terjangkau']) {
                CloudTaskService::markFailed($logId, $hasil['alasan']);

                return response()->json(['success' => false, 'message' => $hasil['alasan']], 500);
            }

            if (! $hasil['ok']) {
                return $this->tolak($berkas, $hasil['alasan'], $logId, $tasks);
            }

            /*
             * ── Lapisan antivirus ────────────────────────────────────────
             *
             * Boleh belum ada. Selama KONTEN_AV_URL kosong DAN KONTEN_AV_WAJIB
             * mati, tahap ini dilewati diam-diam — bukan dijalankan lalu gagal.
             * Membedakan keduanya penting: "belum dipasang" adalah keadaan yang
             * dipilih sadar, sedangkan "dipasang tapi tak menjawab" adalah
             * gangguan yang harus menahan berkasnya (tundaPemindaian di bawah).
             */
            if ($this->avDipakai()) {
                $tasks->tandaiTahap($berkasId, 'Memindai virus', 88);
                $pindai = $this->verifier->pindaiVirus((string) $berkas->File_Path, KontenVerifierService::DISK_KARANTINA);

                if ($pindai['status'] === 'KOTOR') {
                    return $this->tolak($berkas, $pindai['alasan'], $logId, $tasks, $pindai['tanda_tangan']);
                }

                if ($pindai['status'] === 'TAK_TERSEDIA') {
                    if (config('services.konten.av.wajib')) {
                        return $this->tundaPemindaian($berkas, $pindai['alasan'], $logId, $tasks);
                    }

                    // Saklar darurat: layanannya ADA tapi tak menjawab, dan
                    // wajibnya dimatikan. Dicatat tegas supaya tak ada yang lupa
                    // bahwa sebuah berkas pernah lolos TANPA dipindai.
                    Log::warning('KONTEN_AV_WAJIB dimatikan — berkas diloloskan tanpa dipindai', [
                        'berkas_id' => $berkasId,
                        'path' => $berkas->File_Path,
                        'alasan' => $pindai['alasan'],
                    ]);
                }
            }

            /*
             * Promosi ke bucket penyajian. Gagal di sini BUKAN salah berkasnya —
             * ini kegagalan infrastruktur, jadi balas 500 supaya Cloud Tasks
             * mengulang. Menandainya SIAP tanpa objeknya berpindah akan membuat
             * konten yang tak bisa dibuka terlihat siap pakai.
             */
            if (! $this->verifier->promosikan((string) $berkas->File_Path)) {
                CloudTaskService::markFailed($logId, 'Promosi objek dari karantina gagal.');

                return response()->json(['success' => false, 'message' => 'Promosi objek gagal'], 500);
            }

            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update([
                    'Status_Verifikasi' => 'SIAP',
                    'Alasan_Tolak' => null,
                    'Verified_At' => now(),
                    'Updated_At' => now(),
                ]);

            // Render halaman baru boleh jalan SEKARANG: mesin render membaca dari
            // bucket penyajian, dan objeknya baru saja sampai di sana.
            $perluRender = strtolower((string) $berkas->Ext) === 'pdf';
            if ($perluRender) {
                $this->dispatchRender($berkasId, (int) $berkas->Id_Master_Konten);
            } else {
                DB::table('N_LMS_Master_Konten_Berkas')
                    ->where('Id_Master_Konten_Berkas', $berkasId)
                    ->update(['Status_Render' => 'DONE']);
            }

            $tasks->selesaiVerifikasi($berkasId, $perluRender);
            CloudTaskService::markCompleted($logId, 1, 1, 0, ['status' => 'SIAP', 'render' => $perluRender]);

            return response()->json(['success' => true, 'status' => 'SIAP'], 200);
        } catch (\Throwable $e) {
            Log::error('Verifikasi konten meledak', ['berkas_id' => $berkasId, 'error' => $e->getMessage()]);
            CloudTaskService::markFailed($logId, $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Apakah lapisan antivirus ikut dijalankan pada permintaan ini.
     *
     * URL kosong + wajib mati = layanannya memang belum dipasang; lewati.
     * URL kosong + wajib menyala = salah konfigurasi yang berbahaya (mengira
     * ada pemindai padahal tak ada), jadi tetap dijalankan supaya berhenti di
     * TERTUNDA_PEMINDAIAN dan kelihatan, bukan lolos diam-diam.
     */
    private function avDipakai(): bool
    {
        return trim((string) config('services.konten.av.url')) !== ''
            || (bool) config('services.konten.av.wajib');
    }

    /**
     * Berkas ditolak: objeknya dihapus, alasannya dicatat, tasknya ditutup gagal.
     *
     * Balasannya 200, bukan 500 — verifikasi ini BERHASIL dijalankan, hasilnya
     * saja negatif. Membalas 500 akan membuat Cloud Tasks mengulanginya berkali-
     * kali untuk berkas yang sudah pasti ditolak.
     */
    private function tolak(object $berkas, ?string $alasan, $logId, UploadTaskService $tasks, ?string $tandaTangan = null)
    {
        $alasan = $alasan ?: 'Berkas tidak lolos verifikasi.';
        $berkasId = (int) $berkas->Id_Master_Konten_Berkas;

        $this->verifier->hapusObjek((string) $berkas->File_Path, KontenVerifierService::DISK_KARANTINA);

        DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $berkasId)
            ->update([
                'Status_Verifikasi' => 'DITOLAK',
                'Alasan_Tolak' => mb_substr($alasan, 0, 500),
                'Status_Render' => 'FAILED',
                'Verified_At' => now(),
                'Updated_At' => now(),
            ]);

        $tasks->gagalVerifikasi($berkasId, $alasan);

        Log::warning('Konten ditolak verifikasi dalam', [
            'berkas_id' => $berkasId,
            'path' => $berkas->File_Path,
            'alasan' => $alasan,
            'tanda_tangan_virus' => $tandaTangan,
        ]);

        CloudTaskService::markCompleted($logId, 1, 0, 1, [
            'status' => 'DITOLAK',
            'alasan' => $alasan,
            'tanda_tangan_virus' => $tandaTangan,
        ]);

        return response()->json([
            'success' => true, 'status' => 'DITOLAK', 'alasan' => $alasan, 'tanda_tangan' => $tandaTangan,
        ], 200);
    }

    /**
     * Pemindai tak terjangkau — FAIL-CLOSED.
     *
     * Berkas berhenti di TERTUNDA_PEMINDAIAN dan TIDAK PERNAH naik ke SIAP:
     * meloloskannya karena pemindainya kebetulan mati sama saja dengan tidak
     * punya lapisan ini sama sekali, justru pada saat ia paling dibutuhkan.
     *
     * Balasannya 500 supaya Cloud Tasks mengulang dengan backoff — objeknya
     * tetap di karantina menunggu percobaan berikutnya.
     */
    private function tundaPemindaian(object $berkas, ?string $alasan, $logId, UploadTaskService $tasks)
    {
        $berkasId = (int) $berkas->Id_Master_Konten_Berkas;
        $alasan = $alasan ?: 'Pemindaian antivirus tidak tersedia.';

        DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $berkasId)
            ->update([
                'Status_Verifikasi' => 'TERTUNDA_PEMINDAIAN',
                'Alasan_Tolak' => mb_substr($alasan, 0, 500),
                'Updated_At' => now(),
            ]);

        $tasks->tandaiTahap($berkasId, 'Menunggu pindai', 88);

        Log::warning('Verifikasi tertunda: pemindai tak tersedia', [
            'berkas_id' => $berkasId, 'alasan' => $alasan,
        ]);

        CloudTaskService::markFailed($logId, $alasan);

        return response()->json(['success' => false, 'status' => 'TERTUNDA_PEMINDAIAN', 'message' => $alasan], 500);
    }

    private function dispatchRender(int $berkasId, int $kontenId): void
    {
        try {
            // Bukan config('app.url').$path: APP_URL boleh ditulis tanpa skema,
            // dan Cloud Tasks menolak URL semacam itu — tasknya tak pernah
            // terbuat dan render menggantung di PENDING tanpa galat di layar.
            (new CloudTaskService)->dispatch(
                url: CloudTaskService::absoluteUrl('/api/v1/tasks/lms/process-pdf'),
                queue: config('services.project_config.gcp.queue_pdf', 'lms-pdf-queue'),
                payload: ['berkas_id' => $berkasId],
                taskType: 'LMS_PDF_RENDER',
                referenceId: $berkasId,
                referenceNo: 'KONTEN-'.$kontenId,
            );
        } catch (\Throwable $e) {
            // Berkasnya sudah SIAP dan bisa diunduh; yang tertunda hanya reader
            // per-halaman. Biarkan Status_Render tetap PENDING agar dapat
            // di-retry lewat tombol render ulang.
            Log::error('Gagal membuat Cloud Task render setelah verifikasi', [
                'berkas_id' => $berkasId, 'error' => $e->getMessage(),
            ]);
        }
    }
}
