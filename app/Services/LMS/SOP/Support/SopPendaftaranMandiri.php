<?php

namespace App\Services\LMS\SOP\Support;

use App\Services\LMS\SOP\SopPenjadwalanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pendaftaran mandiri pada SOP berdistribusi PUBLIK.
 *
 * SOP PUBLIK boleh dibuka siapa saja — paketBolehDiakses() meloloskannya tanpa
 * penugasan. Tapi seluruh jejak kerja karyawan (N_SOP_Progress, absensi, skor
 * kuis) berkunci pada Id_SOP_Penjadwalan_Detail, sehingga tanpa penugasan tak
 * ada tempat menyimpannya. Jalur kuis sudah memecahkan ini lebih dulu dengan
 * membuatkan penugasan MANDIRI; helper ini mengangkat logika yang sama supaya
 * jalur baca dokumen dan konfirmasi item memakai aturan yang persis sama —
 * bukan menyalinnya dan berisiko menyimpang di kemudian hari.
 *
 * Paket TERBATAS tetap ditolak: helper ini melebarkan akses HANYA sejauh yang
 * memang sudah dijanjikan Mode_Distribusi.
 */
class SopPendaftaranMandiri
{
    /**
     * Id penugasan aktif milik karyawan pada paket; dibuatkan bila paket PUBLIK
     * dan karyawan belum terdaftar.
     *
     * @return int|null null bila paket bukan PUBLIK/PUBLISHED atau pendaftaran gagal.
     */
    public static function pastikanDetailId(string $kode, int $idPaket): ?int
    {
        if ($id = self::detailAktif($kode, $idPaket)) {
            return $id;
        }
        if (! self::bolehDaftarMandiri($idPaket)) {
            return null;
        }

        try {
            app(SopPenjadwalanService::class)->assign(
                $idPaket,
                [$kode],
                ['sumber' => 'MANDIRI', 'tipe_tenggat' => 'SAMPAI_DICABUT'],
                null, // Created_By NULL: tak ada admin yang menugaskan.
            );
        } catch (\Throwable $e) {
            /*
             * Gagal belum tentu berarti tak terdaftar.
             *
             * Dua permintaan dari orang yang sama bisa sama-sama mendapati dirinya
             * belum terdaftar lalu sama-sama mendaftar. Yang kalah cepat menerima
             * galat — dan begitu ada unique index atas penugasan aktif, galat itu
             * pasti duplicate-key. Menyerah di sini membuat orang tersebut ditolak
             * NOT_ASSIGNED padahal barisnya JUSTRU sudah ada, dibuat oleh
             * permintaannya sendiri yang lain.
             *
             * Jadi keberadaan penugasan diperiksa ulang dulu; hanya bila memang
             * tak ada barulah ini benar-benar kegagalan.
             */
            if ($id = self::detailAktif($kode, $idPaket)) {
                return $id;
            }

            Log::error('SOP pendaftaran mandiri pastikanDetailId gagal', [
                'kode' => $kode, 'paket' => $idPaket, 'exception' => $e,
            ]);

            return null;
        }

        return self::detailAktif($kode, $idPaket);
    }

    /** Paket layak didaftari sendiri: terbit, aktif, dan berdistribusi PUBLIK. */
    public static function bolehDaftarMandiri(int $idPaket): bool
    {
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $idPaket)
            ->first(['Status', 'Flag_Aktif', 'Mode_Distribusi']);

        // Mode_Distribusi NULL diperlakukan PUBLIK, sejalan dengan paketBolehDiakses().
        return $paket
            && $paket->Status === SopStatus::PAKET_PUBLISHED
            && $paket->Flag_Aktif === SopStatus::AKTIF
            && ($paket->Mode_Distribusi ?? 'PUBLIK') !== 'TERBATAS';
    }

    /** Penugasan aktif yang aksesnya belum dicabut; null bila tak ada. */
    public static function detailAktif(string $kode, int $idPaket): ?int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->value('d.Id_SOP_Penjadwalan_Detail');

        return $id ? (int) $id : null;
    }
}
