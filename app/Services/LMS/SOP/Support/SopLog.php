<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Penulis jejak admin ke N_SOP_Log.
 *
 * Tabel ini ada sejak awal modul tapi lama tak punya penulis; lalu hanya diisi
 * dua aksi monitoring (KUIS_RESET, INGATKAN). Aksi yang justru paling perlu
 * jejak — publish versi, rollback, reconcile, assign, cabut — tidak tercatat
 * sama sekali. Helper ini menyeragamkan formatnya supaya lima penulis tidak
 * menghasilkan lima gaya Meta_JSON yang berbeda.
 *
 * best-effort: kegagalan menulis log TIDAK BOLEH menggagalkan aksinya.
 * Jejak yang hilang satu baris lebih murah daripada publish yang batal
 * karena tabel log-nya sedang bermasalah.
 */
class SopLog
{
    public static function catat(
        string $aksi,
        string $keterangan,
        ?int $idPaket = null,
        ?int $idItem = null,
        ?int $idVersi = null,
        ?string $kodeKaryawan = null,
        array $meta = [],
    ): void {
        try {
            DB::table('N_SOP_Log')->insert([
                'Id_SOP_Paket' => $idPaket,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $idVersi,
                'Aksi' => substr($aksi, 0, 30),
                'Kode_Karyawan' => $kodeKaryawan,
                'Keterangan' => mb_substr($keterangan, 0, 1000),
                // Dua kunci ini dipromosikan dari Meta_JSON jadi kolom berindeks:
                // timeline per batch harus bisa jadi predikat query, dan
                // JSON_VALUE di WHERE non-sargable. Diambil dari $meta supaya
                // TAK ADA call site yang perlu berubah — semuanya sudah
                // mengirimkan kedua kunci ini.
                'Id_SOP_Penjadwalan' => isset($meta['id_penjadwalan']) ? (int) $meta['id_penjadwalan'] : null,
                'Oleh_Id_Users' => isset($meta['oleh_id_users']) ? (int) $meta['oleh_id_users'] : null,
                /* Sumbu DOKUMEN — dipromosikan dengan cara yang sama, dan
                   karena alasan yang sama. Riwayat satu dokumen mustahil
                   ditanyakan lewat Id_SOP_Item_Versi: peristiwa tingkat
                   dokumen (ganti nomor SOP, ganti judul) tak punya versi untuk
                   digantungkan sama sekali. DDL 32. */
                'Id_SOP_Dokumen' => isset($meta['id_dokumen']) ? (int) $meta['id_dokumen'] : null,
                'Meta_JSON' => $meta ? json_encode($meta) : null,
                'Created_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SopLog gagal menulis: '.$e->getMessage(), ['aksi' => $aksi]);
        }
    }
}
