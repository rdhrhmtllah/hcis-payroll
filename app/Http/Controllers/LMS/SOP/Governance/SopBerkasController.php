<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\SopKontenViewService;
use App\Services\LMS\SOP\SopPaketDraftService;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — pembacaan VERSI item: daftar versi dan isi kontennya.
 *
 * BACA SAJA sejak 27 Agu 2026. Membuat, menyiapkan rilis, menerbitkan, dan
 * menghapus versi seluruhnya milik Master Dokumen (SopAdminDokumenController +
 * wizard lima langkah). Kelas ini hanya menyuapi panel versi read-only di
 * Builder.
 *
 * Dulu kelas ini juga memegang unggah berkas 3-fase. Jalur itu sudah dibuang:
 * Builder memilih konten dari Library SOP, dan reader membaca dari
 * N_LMS_Master_Konten_Berkas lewat Id_Master_Konten pada versi.
 */
class SopBerkasController extends Controller
{
    public function __construct(
        private SopItemVersionService $versi,
        private SopKontenViewService $kontenView,
        private SopPaketDraftService $paketDraft,
    ) {}

    private function decode($id): ?int
    {
        if (is_string($id) && preg_match('/^(?:di|dv)_(.+)$/', $id, $m)) {
            $id = $m[1];
        }
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    private function isDraftToken($id, string $type): bool
    {
        return is_string($id) && str_starts_with($id, $type.'_');
    }

    /** GET /api/v1/sop/admin/berkas/item/{idItem}/versi */
    public function listVersi($idItem)
    {
        $decodedItem = $this->decode($idItem);
        if (! $decodedItem) {
            return ResponseHelper::error('ID item tidak valid', 400);
        }

        try {
            /* ?ringkas=1 → hanya versi yang berlaku. Panel Builder memintanya
               saat lahir; riwayat lengkapnya baru diambil kalau accordionnya
               dibuka. Bedanya nyata: satu baris tanpa blok pengesah, versus
               seluruh riwayat dokumen berikut blok tiap versinya — dan panel itu
               dirender untuk SETIAP item berkas di editor.

               DITERUSKAN KE KEDUA CABANG. Panel ringkas membaca baris pertama
               apa adanya sebagai "versi berlaku"; tanpa saringan ini cabang
               overlay menyerahkan versi bernomor tertinggi — yang bisa OBSOLETE
               atau masih kandidat — dan layar menyebutnya berlaku. */
            $ringkas = request()->boolean('ringkas');

            if ($this->isDraftToken($idItem, 'di')) {
                return ResponseHelper::success(
                    $this->sanitizeJsonPayload($this->paketDraft->versions($decodedItem, $ringkas)), 'Data Ditemukan');
            }

            return ResponseHelper::success(
                $this->sanitizeJsonPayload($this->versi->byItem($decodedItem, $ringkas)), 'Data Ditemukan');
        } catch (\Throwable $e) {
            Log::error('SOP berkas listVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    private function sanitizeJsonPayload($value)
    {
        if (is_string($value)) {
            return $this->sanitizeUtf8($value);
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn ($item) => $this->sanitizeJsonPayload($item));
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeJsonPayload($item);
            }

            return $value;
        }

        if (is_object($value)) {
            foreach ($value as $key => $item) {
                $value->{$key} = $this->sanitizeJsonPayload($item);
            }
        }

        return $value;
    }

    private function sanitizeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }

    /* storeVersi(), setReady(), publishVersi(), rollbackVersi(), dan
     * destroyVersi() DIHAPUS (27 Agu 2026) bersama rutenya.
     *
     * Siklus versi milik Master Dokumen seutuhnya: /sop/admin/dokumen/{id}/versi
     * dengan lima langkahnya. Selama pintu ini masih ada, sebuah versi bisa
     * lahir dan terbit dari dalam Builder tanpa pernah melewati Pengajuan,
     * Review, maupun Pengesahan — dan yang terbit lewat sana adalah aturan kerja
     * yang wajib dibaca orang.
     *
     * Yang tersisa di kelas ini hanya membaca: daftar versi dan isi kontennya,
     * dipakai panel versi Builder yang sekarang read-only.
     */

    /** GET /api/v1/sop/admin/berkas/versi/{id}/konten */
    public function viewKonten($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID versi tidak valid', 400);
        }

        try {
            if ($this->isDraftToken($id, 'dv')) {
                $draftVersion = $this->paketDraft->draftVersion($decoded);
                if (! $draftVersion) {
                    return ResponseHelper::error('Versi draft tidak ditemukan', 404);
                }
                $payload = $this->kontenView->forKonten((int) ($draftVersion->Id_Master_Konten ?? 0));
            } else {
                $payload = $this->kontenView->forVersi($decoded);
            }
            if (isset($payload['_error'])) {
                return ResponseHelper::error($payload['_error'], (int) ($payload['_code'] ?? 500));
            }

            return ResponseHelper::success($payload, 'Konten berhasil dimuat');
        } catch (\Throwable $e) {
            Log::error('SOP berkas viewKonten gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
