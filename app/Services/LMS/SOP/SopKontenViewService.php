<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\Bunny\BunnyStreamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Resolusi "lihat konten" sebuah versi SOP → URL tampil (dokumen: signed GCS URL,
 * video: embed Bunny). Dipakai admin (SopBerkasController) & karyawan (reader).
 */
class SopKontenViewService
{
    /**
     * @param  bool  $paksaUnduh  Signed URL diberi response-content-disposition=attachment,
     *                            supaya browser MENGUNDUH berkas alih-alih menampilkannya.
     *                            Dipakai item tipe FORM (xlsx/csv/docx) yang tak punya penampil.
     * @return array payload {type,judul,url,...} ATAU ['_error'=>msg,'_code'=>int].
     */
    public function forVersi(int $idVersi, bool $paksaUnduh = false): array
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->first();
        if (! $versi || empty($versi->Id_Master_Konten)) {
            return ['_error' => 'Versi ini belum punya konten.', '_code' => 404];
        }

        return $this->forKonten((int) $versi->Id_Master_Konten, $paksaUnduh);
    }

    public function forKonten(int $idKonten, bool $paksaUnduh = false): array
    {
        if (! $idKonten) {
            return ['_error' => 'Versi ini belum punya konten.', '_code' => 404];
        }

        $k = DB::table('N_LMS_Master_Konten')
            ->where('Id_Master_Konten', $idKonten)
            ->where('Scope', 'SOP')
            ->first();
        if (! $k) {
            return ['_error' => 'Konten tidak ditemukan.', '_code' => 404];
        }

        // VIDEO → embed Bunny (ditonton) ATAU berkas .mp4 (diunduh, item FORM)
        if (strtolower($k->Tipe_Konten ?? '') === 'video') {
            $media = DB::table('N_LMS_Master_Konten_Media')->where('Id_Master_Konten', $idKonten)->first();
            $embed = null;
            if ($media && ! empty($media->Provider_Media_Id)) {
                try {
                    $meta = (new BunnyStreamService)->getVideoMetadata((string) $media->Provider_Media_Id);
                    $embed = $meta['embed_url'] ?? null;
                } catch (\Throwable $e) {
                    Log::error('SOP konten view forKonten video gagal', ['exception' => $e]);
                }
            }

            /*
             * Item FORM: video pun harus DIUNDUH, tak boleh diputar.
             *
             * Embed dan HLS tak bisa dipakai — HLS adalah potongan streaming,
             * bukan satu berkas. Yang dipakai adalah berkas play_*.mp4 hasil
             * MP4 Fallback Bunny, ditandatangani token CDN.
             *
             * Yang terunduh adalah HASIL TRANSKODE Bunny, bukan berkas asli yang
             * diunggah admin — konsekuensi memilih menyimpan video di Bunny.
             */
            if ($paksaUnduh) {
                $unduh = ($media && ! empty($media->Provider_Media_Id))
                    ? (new BunnyStreamService)->mp4DownloadUrl(
                        (string) $media->Provider_Media_Id,
                        $meta['available_resolutions'] ?? null,
                    )
                    : null;

                if (! $unduh) {
                    return [
                        '_error' => 'Video ini belum siap diunduh. Pastikan MP4 Fallback aktif di library Bunny.',
                        '_code' => 404,
                    ];
                }

                return [
                    'type' => 'unduhan',
                    'judul' => $k->Judul_Konten,
                    'nama_berkas' => $this->namaBerkas('x.mp4', $k->Judul_Konten),
                    'url' => $unduh,
                    'mime' => 'video/mp4',
                ];
            }

            if (! $embed) {
                return ['_error' => 'Video belum siap ditonton.', '_code' => 404];
            }

            return [
                'type' => 'video',
                'judul' => $k->Judul_Konten,
                'url' => $embed,
                // Gerbang tonton: durasi dari metadata Bunny + ambang dari N_SOP_Setting.
                // durasi null (metadata gagal) → viewer tak memasang gerbang, perilaku
                // jatuh kembali ke seperti semula.
                'durasi_detik' => isset($meta) ? ($meta['length_seconds'] ?? null) : null,
                'ambang_persen' => \App\Services\LMS\SOP\Support\SopSetting::int('video_ambang_persen', 90),
            ];
        }

        // DOKUMEN/FORM → signed URL berkas (15 mnt)
        $b = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten', $idKonten)->first();
        if (! $b || empty($b->File_Path)) {
            return ['_error' => 'Berkas konten tidak ditemukan.', '_code' => 404];
        }
        $namaUnduh = $this->namaBerkas($b->File_Path, $k->Judul_Konten);
        $opsi = [];
        if ($paksaUnduh) {
            // filename* (RFC 5987) menjaga nama non-ASCII; filename= untuk browser lama.
            $disposition = sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                preg_replace('/[^\x20-\x7E]/', '_', str_replace('"', '', $namaUnduh)),
                rawurlencode($namaUnduh)
            );
            // WAJIB di-encode sendiri: SigningHelper::buildQueryString() merangkai
            // query "as is" (lihat catatan di method itu — tidak meng-urlencode nilai).
            // Tanpa ini spasi/koma/kutip masuk mentah ke URL → URL cacat, dan nilai
            // yang ditandatangani beda dengan yang dikirim browser (SignatureDoesNotMatch).
            $opsi['responseDisposition'] = rawurlencode($disposition);

            // Content-Disposition saja tidak cukup. Sebagian peramban tetap
            // merender inline bila tipe kontennya sesuatu yang bisa ditampilkan
            // (PDF, gambar), dan item FORM justru tak boleh pernah tampil di
            // layar — kontraknya "diunduh". octet-stream menutup jalur itu:
            // tak ada peramban yang merender tipe ini.
            $opsi['responseType'] = 'application/octet-stream';
        }

        try {
            $url = Storage::disk('gcs')->temporaryUrl($b->File_Path, now()->addMinutes(15), $opsi);
        } catch (\Throwable $e) {
            Log::error('SOP konten view forKonten signed url gagal', ['exception' => $e]);

            return ['_error' => 'Gagal memuat berkas.', '_code' => 500];
        }

        return [
            'type' => 'document',
            'judul' => $k->Judul_Konten,
            'nama_berkas' => $namaUnduh,
            'url' => $url,
            'mime' => $b->Type_File,
            'total_halaman' => $b->Total_Halaman,
            'status_render' => $b->Status_Render,
            // Gerbang baca per halaman — dari N_SOP_Setting, bukan angka keras di
            // browser. Nilai dari server juga berarti mengubah kebijakan tak perlu
            // deploy ulang frontend.
            'dwell_min_dtk' => \App\Services\LMS\SOP\Support\SopSetting::int('reader_dwell_min_dtk', 3),
            // untuk reader secure-PDF SOP (reuse endpoint halaman LMS)
            'berkas_id' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($b->Id_Master_Konten_Berkas),
        ];
    }

    /**
     * Nama berkas untuk diunduh. File_Path di GCS memakai nama acak/hash,
     * jadi yang dipakai judul konten + ekstensi asli agar karyawan menerima
     * berkas bernama wajar ("Formulir Klaim.xlsx", bukan "a91f3c.xlsx").
     */
    private function namaBerkas(string $path, ?string $judul): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $base = trim((string) $judul);
        if ($base === '') {
            $base = pathinfo($path, PATHINFO_FILENAME) ?: 'berkas';
        }
        // karakter yang dilarang di nama berkas Windows/macOS
        $base = preg_replace('#[\\\\/:*?"<>|]+#', '-', $base);
        $base = trim(preg_replace('/\s+/', ' ', $base));

        return $ext ? $base.'.'.$ext : $base;
    }
}
