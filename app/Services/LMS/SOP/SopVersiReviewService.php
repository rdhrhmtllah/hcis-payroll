<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopHtml;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Catatan review pada sebuah versi dokumen SOP.
 *
 * Satu versi bisa direview berkali-kali sebelum disetujui, dan tiap putaran
 * punya tanggal, hasil, serta lampirannya sendiri.
 *
 * BEDA DARI Tanggal_Respon pada identitas versi: yang itu SATU tanggal ringkas
 * — kapan hasil review keluar — dan tercap otomatis saat tahap bergeser. Yang di
 * sini adalah catatannya: putaran ke berapa, apa isinya, dan lampiran apa yang
 * menyertainya.
 *
 * SANITASI DI SERVER. Hasil_Review disaring SopHtml::sanitize() — daftar putih
 * 15 tag yang tak memuat <img>. Editor di layar juga menyaring, tetapi itu
 * kenyamanan, bukan penjagaan: panggilan API langsung tak pernah melewati layar.
 */
class SopVersiReviewService
{
    private string $table = 'N_SOP_Item_Versi_Review';

    private string $tableLampiran = 'N_SOP_Item_Versi_Review_Lampiran';

    /** Batas satu lampiran. Sama dengan logo entitas — 2 MB sebelum base64. */
    private const MAKS_BYTE = 2 * 1024 * 1024;

    private const TIPE_SAH = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * Seluruh review sebuah versi, terurut, beserta lampirannya.
     *
     * @return array<int, array>
     */
    public function daftar(int $idVersi): array
    {
        $rows = DB::table($this->table)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Review_Ke')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $lampiran = $this->lampiranPerReview($rows->pluck('Id_Review')->all());

        return $rows->map(fn ($r) => [
            'Id_Review' => Hashids::connection('custom')->encode($r->Id_Review),
            'Review_Ke' => (int) $r->Review_Ke,
            'Tanggal_Review' => $r->Tanggal_Review,
            'Hasil_Review' => $r->Hasil_Review,
            'Lampiran' => $lampiran[(int) $r->Id_Review] ?? [],
        ])->all();
    }

    /**
     * Tulis ulang seluruh review sebuah versi.
     *
     * DITULIS ULANG, bukan ditambahi: form mengirim daftar yang dimaksud, dan
     * menambah saja akan meninggalkan review yang sudah dihapus admin. Pola yang
     * sama dipakai blok pengesah pada versi.
     *
     * Baris lama dinonaktifkan, bukan dihapus — lampirannya menunjuk berkas di
     * GCS, dan baris yang lenyap membuat berkas itu yatim tanpa jejak.
     *
     * @param  array<int, array>  $daftar
     */
    public function simpan(int $idVersi, array $daftar, int $userId): int
    {
        return DB::transaction(function () use ($idVersi, $daftar, $userId) {
            DB::table($this->table)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()]);

            $urut = 0;
            foreach ($daftar as $baris) {
                $urut++;
                $idReview = DB::table($this->table)->insertGetId([
                    'Id_SOP_Item_Versi' => $idVersi,
                    /* Nomor putaran dihormati bila dikirim — "Review 2" tetap
                       bernama Review 2 setelah Review 1 dihapus. */
                    'Review_Ke' => (int) ($baris['Review_Ke'] ?? $urut),
                    'Tanggal_Review' => $baris['Tanggal_Review'] ?? null,
                    'Hasil_Review' => SopHtml::sanitize($baris['Hasil_Review'] ?? null),
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_By' => $userId,
                    'Created_At' => now(),
                ], 'Id_Review');

                $this->simpanLampiran($idReview, $baris['Lampiran'] ?? [], $userId);
            }

            SopLog::catat(
                'VERSI_REVIEW',
                'Catatan review versi disimpan: '.count($daftar).' putaran.',
                null, null, $idVersi, null,
                [
                    'id_dokumen' => DB::table('N_SOP_Item_Versi')
                        ->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen'),
                    'oleh_id_users' => $userId,
                    'jumlah' => count($daftar),
                ],
            );

            return count($daftar);
        });
    }

    /**
     * Simpan lampiran sebuah review.
     *
     * Menerima DUA bentuk, sama seperti logo entitas: data URI hasil unggah, dan
     * path/URL yang sudah tersimpan sebelumnya. Bentuk kedua diteruskan apa
     * adanya — tanpa itu, menyunting review akan mengunggah ulang gambar yang
     * sama setiap kali disimpan.
     *
     * @param  array<int, array|string>  $lampiran
     */
    private function simpanLampiran(int $idReview, array $lampiran, int $userId): void
    {
        $urut = 0;
        foreach ($lampiran as $l) {
            $data = is_array($l) ? $l : ['Berkas' => $l];
            $berkas = (string) ($data['Berkas'] ?? $data['Path_Berkas'] ?? '');
            if ($berkas === '') {
                continue;
            }

            $urut++;
            $path = $this->pathBerkas($berkas, $idReview);
            if ($path === null) {
                continue;
            }

            DB::table($this->tableLampiran)->insert([
                'Id_Review' => $idReview,
                'Path_Berkas' => $path,
                'Nama_Berkas' => $data['Nama_Berkas'] ?? null,
                'Tipe_Berkas' => $data['Tipe_Berkas'] ?? null,
                'Ukuran_Byte' => isset($data['Ukuran_Byte']) ? (int) $data['Ukuran_Byte'] : null,
                'Urutan' => $urut,
                'Flag_Aktif' => SopStatus::AKTIF,
                'Created_By' => $userId,
                'Created_At' => now(),
            ]);
        }
    }

    /**
     * Data URI -> path GCS. Path/URL yang sudah ada diteruskan apa adanya.
     *
     * Mengembalikan null bila berkasnya ditolak — jenis di luar daftar, kosong,
     * atau melebihi batas. Ditolak DIAM-DIAM per berkas, bukan menggagalkan
     * seluruh penyimpanan: satu gambar yang bermasalah tak boleh membuang
     * catatan review yang sudah diketik panjang.
     */
    private function pathBerkas(string $berkas, int $idReview): ?string
    {
        if (! preg_match('/^data:image\\/([\\w+]+);base64,/', $berkas, $m)) {
            return $berkas;
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        if (! in_array($ext, self::TIPE_SAH, true)) {
            return null;
        }

        $binary = base64_decode(substr($berkas, strpos($berkas, ',') + 1)) ?: '';
        if ($binary === '' || strlen($binary) > self::MAKS_BYTE) {
            return null;
        }

        $path = 'sop/review-lampiran/'.now()->format('Y/m').'/'
            .$idReview.'-'.Str::uuid().'.'.$ext;

        Storage::disk('gcs')->put($path, $binary);

        return $path;
    }

    /**
     * URL siap tampil untuk sebuah lampiran.
     *
     * Yang sudah berupa URL diteruskan; sisanya ditandatangani sementara. Sama
     * seperti logo entitas, dan gagal-tandatangan mengembalikan null alih-alih
     * melempar — satu gambar yang tak bisa ditampilkan tak boleh menjatuhkan
     * seluruh daftar review.
     */
    public static function lampiranUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::warning('Lampiran review gagal ditandatangani: '.$e->getMessage(), ['path' => $path]);

            return null;
        }
    }

    /** @return array<int, array> */
    private function lampiranPerReview(array $idReview): array
    {
        if ($idReview === []) {
            return [];
        }

        return DB::table($this->tableLampiran)
            ->whereIn('Id_Review', $idReview)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Id_Review')
            ->map(fn ($g) => $g->map(fn ($l) => [
                'Id_Lampiran' => Hashids::connection('custom')->encode($l->Id_Lampiran),
                'Path_Berkas' => $l->Path_Berkas,
                'Url' => self::lampiranUrl($l->Path_Berkas),
                'Nama_Berkas' => $l->Nama_Berkas,
                'Tipe_Berkas' => $l->Tipe_Berkas,
                'Ukuran_Byte' => $l->Ukuran_Byte !== null ? (int) $l->Ukuran_Byte : null,
            ])->values()->all())
            ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
            ->all();
    }
}
