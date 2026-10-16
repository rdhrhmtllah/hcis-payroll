<?php

namespace App\Services\KPI;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Apakah masih ada penjadwalan BERJALAN yang membebani seorang karyawan.
 *
 * Dipakai untuk memilih label "Belum Update/Submit" (masih ada kewajiban yang
 * berjalan) versus "Tidak Update/Submit" (waktunya sudah habis).
 *
 * ── Cacat yang digantikan ───────────────────────────────────────────────────
 *
 * Sebelumnya labelnya diturunkan dari SATU boolean se-scope: `isScopeExpired()`
 * menuntut SELURUH penjadwalan periode itu sudah lewat. Satu jadwal yang masih
 * terbuka karena itu menyeret label semua orang pada periode yang sama —
 * termasuk orang yang jadwalnya sendiri sudah lama tutup.
 *
 * Terukur di produksi 16 Sep 2026 untuk periode Juli 2026: dari 90 baris
 * "belum/tidak update", hanya 3 orang yang benar-benar peserta jadwal #32
 * (perorangan, berakhir 15 Sep 21:00). 87 sisanya hanya dibebani jadwal #26
 * yang sudah tutup 31 Agustus, tetapi sepanjang 1–15 September mereka tetap
 * berlabel "Belum Update". Tiga orang menyeret label delapan puluh tujuh.
 *
 * ── Kenapa Flag_Aktif dilihat DI SINI, tapi tidak di universe ───────────────
 *
 * Universe monitoring sengaja mengabaikan `Flag_Aktif` supaya riwayat sebuah
 * periode tidak lenyap begitu penjadwalannya ditutup (lihat catatan panjang di
 * KpiEnforcementController::fetchYears). Itu keputusan tentang APA YANG
 * TERLIHAT, dan tetap berlaku.
 *
 * Label berbicara soal hal yang berbeda: apakah orangnya MASIH PUNYA WAKTU.
 * Penjadwalan yang dinonaktifkan jelas tidak lagi memberi waktu. Tanpa
 * pembedaan ini, menonaktifkan jadwal tidak berefek apa pun — dan admin tidak
 * punya satu pun tuas untuk menutup periode selain menunggu jam dinding lewat.
 * Diukur pada hari yang sama: ke-37 peserta jadwal #32 sudah `Flag_Aktif='T'`,
 * dan tak satu pun dari tiga lapis (jadwal, peserta, label) membacanya.
 *
 * `Jadwal_Akhir` kosong pada jadwal yang masih aktif diperlakukan BERJALAN:
 * label "Tidak Update" mengklaim tenggatnya sudah lewat, dan tanpa tanggal
 * akhir klaim itu tidak bisa dibuktikan. Menutupnya dilakukan dengan
 * menonaktifkan jadwalnya — tuas yang sekarang benar-benar bekerja.
 */
final class KpiJadwalBerjalan
{
    /** @var array<string,true>|null */
    private ?array $kodeTerbebani = null;

    /**
     * @param  int[]  $idPeroranganBerjalan
     */
    private function __construct(
        private bool $adaGlobalBerjalan,
        private array $idPeroranganBerjalan,
    ) {}

    /**
     * @param  \Illuminate\Support\Collection<int,object>  $schedules
     *                                                                 baris dengan Id_Master_Penjadwalan, Flag_Perorangan, Flag_Aktif, Jadwal_Akhir
     */
    public static function dariJadwal($schedules): self
    {
        $berjalan = $schedules->filter(fn ($s) => self::sedangBerjalan($s));

        return new self(
            $berjalan->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y'),
            $berjalan
                ->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
                ->pluck('Id_Master_Penjadwalan')
                ->map(fn ($v) => (int) $v)
                ->all(),
        );
    }

    /**
     * Bentuk yang dititipkan ke payload job export, supaya berkas memakai label
     * yang sama persis dengan layar. Job lama yang payload-nya dibuat sebelum
     * kunci ini ada memulangkan null -> pemanggil memakai cadangannya sendiri.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function dariMeta(array $meta): ?self
    {
        if (! array_key_exists('global_berjalan', $meta)) {
            return null;
        }

        return new self(
            (bool) $meta['global_berjalan'],
            array_map('intval', (array) ($meta['perorangan_berjalan_ids'] ?? [])),
        );
    }

    /** @return array<string,mixed> */
    public function untukMeta(): array
    {
        return [
            'global_berjalan' => $this->adaGlobalBerjalan,
            'perorangan_berjalan_ids' => $this->idPeroranganBerjalan,
        ];
    }

    /**
     * Apakah karyawan ini masih punya penjadwalan berjalan yang membebaninya.
     */
    public function membebani(?string $kodeKaryawan): bool
    {
        if ($this->adaGlobalBerjalan) {
            return true;
        }

        if ($kodeKaryawan === null || $kodeKaryawan === '' || empty($this->idPeroranganBerjalan)) {
            return false;
        }

        return isset($this->pesertaBerjalan()[$kodeKaryawan]);
    }

    /**
     * Peserta penjadwalan perorangan yang masih berjalan. Peserta yang
     * dinonaktifkan (`Flag_Aktif <> 'Y'`) tidak lagi dibebani — di situlah
     * pencabutan per orang oleh admin akhirnya terbaca.
     *
     * @return array<string,true>
     */
    private function pesertaBerjalan(): array
    {
        if ($this->kodeTerbebani !== null) {
            return $this->kodeTerbebani;
        }

        $this->kodeTerbebani = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->whereIn('Id_Master_Penjadwalan', $this->idPeroranganBerjalan)
            ->where('Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('Kode_Karyawan')
            ->filter()
            ->flip()
            ->map(fn () => true)
            ->all();

        return $this->kodeTerbebani;
    }

    private static function sedangBerjalan(object $s): bool
    {
        if (($s->Flag_Aktif ?? 'T') !== 'Y') {
            return false;
        }

        $akhir = $s->Jadwal_Akhir ?? null;

        return $akhir === null || Carbon::parse($akhir)->isFuture();
    }
}
