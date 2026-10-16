<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\LmsSecurePdfService;
use App\Services\LMS\SOP\SopSecurePdfAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SOP — Secure PDF handshake (token) untuk reader dokumen karyawan.
 *
 * Reuse penuh sistem secure-PDF LMS: setelah token diterbitkan, FE memakai
 * endpoint LMS manifest/grant/image (gambar per-halaman + watermark) yang hanya
 * bergantung pada handshake cache + signature — TIDAK mengecek ulang akses LMS.
 * Kita hanya menerbitkan handshake yang KOMPATIBEL, dengan otorisasi SOP.
 *
 * PENTING: format cache key & struktur payload di bawah HARUS SAMA dengan
 * LmsMateriController (securePdfTokenCacheKey / consumeSecurePdfHandshake),
 * store 'database', TTL 20 dtk. Jangan diubah tanpa menyamakan di sana.
 */
class SopSecurePdfController extends Controller
{
    private const HANDSHAKE_TTL = 20; // = SECURE_PDF_HANDSHAKE_TTL_SECONDS (LMS)

    public function __construct(private SopSecurePdfAccessService $access) {}

    /** POST /api/v1/sop/secure-pdf/token/{berkasId} — pintu KARYAWAN. */
    public function token(Request $request, string $berkasId)
    {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }
        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $kode = optional($user->karyawan)->Kode_Karyawan ? trim($user->karyawan->Kode_Karyawan) : null;
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $realId = $this->access->decodeBerkasId($berkasId);
        if (! $realId) {
            return ResponseHelper::error('Berkas tidak valid', 400);
        }

        if (! $this->access->canAccess($kode, $realId)) {
            return ResponseHelper::error('Anda tidak berhak mengakses dokumen ini.', 403);
        }

        return $this->terbitkan($request, $user, $realId, $berkasId, $kode);
    }

    /**
     * POST /api/v1/sop/admin/secure-pdf/token/{berkasId} — pintu ADMIN.
     *
     * Pratinjau Master Dokumen & Builder memakai pembaca yang SAMA, tapi tak
     * boleh memakai otorisasi yang sama: yang dibuka admin justru versi DRAFT
     * (belum terbit) dan versi OBSOLETE (sudah dicabut) — dua keadaan yang
     * memang ditolak lensa karyawan. Tanpa pintu ini, tombol "lihat isi" di
     * layar admin selalu berbalas 403 tanpa satu pun kesalahan data.
     *
     * Rutenya didaftarkan di KEDUA gerbang admin dan menunjuk metode ini,
     * bukan menyalinnya — aturan yang cuma dipasang di satu pintu adalah cacat
     * yang sudah berulang di modul ini.
     */
    public function tokenAdmin(Request $request, string $berkasId)
    {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }
        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $realId = $this->access->decodeBerkasId($berkasId);
        if (! $realId) {
            return ResponseHelper::error('Berkas tidak valid', 400);
        }

        if (! $this->access->bolehAdmin($realId)) {
            return ResponseHelper::error('Berkas ini bukan milik modul DMS.', 403);
        }

        /* Admin boleh tak punya data karyawan sama sekali. Kode kosong membuat
           watermarkAktif() jatuh ke baku AMAN — watermark MENYALA — dan itu
           yang benar untuk pratinjau: mematikannya menuntut kecocokan item
           berikut otorisasi nyata, bukan sekadar kewenangan halaman. */
        $kode = optional($user->karyawan)->Kode_Karyawan
            ? trim($user->karyawan->Kode_Karyawan) : '';

        return $this->terbitkan($request, $user, $realId, $berkasId, $kode);
    }

    /**
     * Penerbit handshake — satu badan untuk kedua pintu.
     *
     * Sengaja tidak digandakan per pintu: bentuk cache key, TTL, dan struktur
     * payload harus PERSIS sama dengan LmsMateriController, dan dua salinan
     * pasti menyimpang pada perubahan berikutnya.
     */
    private function terbitkan(Request $request, object $user, int $realId, string $berkasId, string $kode)
    {
        $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();
        if (! $berkas || empty($berkas->File_Path)) {
            return ResponseHelper::error('Berkas dokumen tidak ditemukan', 404);
        }

        try {
            $tokenRaw = Str::random(40);
            $signature = LmsSecurePdfService::generate($tokenRaw);

            $idItem = $this->access->decodeBerkasId((string) $request->input('id_item', ''));
            $watermark = $this->access->watermarkAktif($kode, $realId, $idItem)
                ? $this->buildWatermark($request, $user)
                : [];

            Cache::store('database')->put(
                'lms_pdf_token_'.(int) $user->Id_Users.'_'.$realId.'_'.hash('sha256', $tokenRaw),
                [
                    'user_id' => (int) $user->Id_Users,
                    'berkas_id' => (int) $realId,
                    'materi_id' => 0,
                    'materi_det_id' => 0,
                    'penjadwalan_id' => 0,
                    'watermark' => $watermark,
                ],
                self::HANDSHAKE_TTL,
            );

            $manifestUrl = url("/api/v1/lms/materi/secure-pdf/pages/{$berkasId}/manifest")
                .'?'.http_build_query(['x_signature' => $signature]);

            return ResponseHelper::success([
                'signature' => $signature,
                'expires_in' => self::HANDSHAKE_TTL,
                'page_manifest_url' => $manifestUrl,
                'watermark' => $watermark,
            ]);
        } catch (\Throwable $e) {
            Log::error('SOP secure-pdf terbitkan gagal', ['exception' => $e]);

            return ResponseHelper::error('Gagal menyiapkan dokumen aman', 500);
        }
    }

    /** Watermark personal (mirror LmsMateriController::buildSecurePdfWatermark). */
    private function buildWatermark(Request $request, object $user): array
    {
        $ip = $request->header('X-Forwarded-For')
            ? trim(explode(',', $request->header('X-Forwarded-For'))[0])
            : $request->ip();

        return [
            'user' => 'User : '.$this->wmUser($user),
            'ip' => $this->maskIp($ip),
            'date' => now()->format('Y-m-d'),
            'Property' => 'Property of Evo Group',
        ];
    }

    private function wmUser(object $user): string
    {
        foreach (['Nickname', 'Nama_User', 'Username', 'name'] as $k) {
            $v = data_get($user, $k);
            if (filled($v)) {
                return trim((string) $v);
            }
        }

        return 'Unknown User';
    }

    private function maskIp(?string $ip): string
    {
        if (! filled($ip)) {
            return '-';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $s = explode('.', $ip);

            return "{$s[0]}.{$s[1]}.xxx.xxx";
        }

        return '-';
    }
}
