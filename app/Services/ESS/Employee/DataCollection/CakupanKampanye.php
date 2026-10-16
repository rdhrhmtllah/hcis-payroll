<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cakupan kampanye: lokasi kerja mana yang pembaruan datanya sedang dibuka.
 *
 * SATU tempat, karena "siapa yang wajib mengisi" di fitur ini tidak pernah
 * berupa tabel target — ia dihitung ulang di sembilan tempat yang dulu menyalin
 * predikatnya sendiri-sendiri (lima kueri admin, ekspor, dan tiga pintu sisi
 * karyawan). Aturan yang disalin adalah aturan yang kelak hanya diperbaiki di
 * sebagian salinannya; repo ini sudah pernah membayar itu.
 *
 * Cakupan BUKAN pengecualian. Pengecualian adalah keputusan admin atas satu
 * orang dan hidup sebagai Is_Dikecualikan di baris status. Cakupan adalah
 * gelombang pembukaan dan hidup sebagai satu kolom di baris konfigurasi —
 * menggesernya tidak menyentuh satu baris status pun.
 *
 * Daftar kosong = seluruh lokasi, yaitu perilaku sebelum fitur ini ada.
 */
class CakupanKampanye
{
    /**
     * Lokasi seorang karyawan nyaris tidak pernah berubah, jadi pembacaannya
     * ditahan lama — jalur ini disentuh di SETIAP kunjungan halaman, dan repo
     * ini punya riwayat "Login timeout expired" akibat koneksi jenuh.
     *
     * Kunci diberi versi: mengubah cara lokasi dinormalkan tanpa menaikkannya
     * membuat orang dinilai dengan aturan lama sampai TTL habis.
     */
    private const TTL_LOKASI_JAM = 6;

    private const PREFIKS_CACHE_LOKASI = 'pengumpulan-data:lokasi:v1:';

    public function __construct(private KonfigurasiKampanye $konfigurasi) {}

    /** @return string[] */
    public function lokasiTerpilih(): array
    {
        return $this->konfigurasi->cakupanLokasi();
    }

    public function terbukaUntukSemuaLokasi(): bool
    {
        return $this->lokasiTerpilih() === [];
    }

    /**
     * Menyempitkan kueri populasi ke lokasi yang dibuka.
     *
     * Perbandingannya dinormalkan di SQL, bukan disamakan mentah-mentah:
     * Cabang_Untuk_Gaji diketik manusia di sistem induk, dan satu spasi ekor
     * di sana akan mengeluarkan orangnya dari kampanye tanpa pesan apa pun.
     * Biaya non-sargable-nya nihil pada populasi fitur ini (< 1.000 karyawan
     * aktif), dan kueri-kueri ini memang sudah memindai tabel Karyawan penuh.
     *
     * NULL tidak pernah cocok dengan whereIn, jadi karyawan tanpa lokasi
     * otomatis berada di luar cakupan — itu memang yang diinginkan: orang tidak
     * boleh diwajibkan mengisi hanya karena kolom lokasinya kebetulan kosong.
     *
     * @param  Builder  $query
     */
    public function terapkan($query, string $alias = 'k'): void
    {
        $lokasi = $this->lokasiTerpilih();

        if ($lokasi === []) {
            return;
        }

        $query->whereIn(DB::raw($this->ekspresiLokasi($alias)), $lokasi);
    }

    /**
     * Apakah satu orang berada dalam cakupan?
     *
     * Dipakai tiga pintu sisi karyawan (prop modal, pengalihan halaman,
     * penjagaan formulir). Konfigurasinya dibaca segar setiap kali — hanya
     * lokasi miliknya yang di-cache — sehingga cakupan yang baru disimpan admin
     * langsung berlaku tanpa menunggu TTL apa pun habis.
     */
    public function mencakup(string $kodeKaryawan): bool
    {
        $lokasi = $this->lokasiTerpilih();

        if ($lokasi === []) {
            return true;
        }

        if ($kodeKaryawan === '') {
            return false;
        }

        $milik = $this->lokasiKaryawan($kodeKaryawan);

        return $milik !== '' && in_array($milik, $lokasi, true);
    }

    public function lokasiKaryawan(string $kodeKaryawan): string
    {
        if ($kodeKaryawan === '') {
            return '';
        }

        return Cache::remember(
            self::PREFIKS_CACHE_LOKASI.$kodeKaryawan,
            now()->addHours(self::TTL_LOKASI_JAM),
            function () use ($kodeKaryawan): string {
                $nilai = DB::table('Karyawan')
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->value('Cabang_Untuk_Gaji');

                return self::normalkan($nilai);
            },
        );
    }

    /**
     * Dipanggil saat lokasi seorang karyawan diperbaiki di master data dan
     * perbaikannya harus berlaku sekarang, bukan setelah enam jam.
     */
    public function bersihkanCacheLokasi(string $kodeKaryawan): void
    {
        Cache::forget(self::PREFIKS_CACHE_LOKASI.$kodeKaryawan);
    }

    public static function normalkan(?string $nilai): string
    {
        return mb_strtoupper(trim((string) $nilai));
    }

    /**
     * Satu bentuk kanonis untuk daftar: ternormalkan, tanpa yang kosong, tanpa
     * kembar, dan berurut. Bentuk yang sama dipakai saat menyimpan maupun saat
     * membaca, supaya isi kolom tidak pernah bergantung pada urutan centang.
     *
     * @param  array<int, mixed>  $daftar
     * @return string[]
     */
    public static function normalkanDaftar(array $daftar): array
    {
        $bersih = [];

        foreach ($daftar as $nilai) {
            if (! is_string($nilai) && ! is_numeric($nilai)) {
                continue;
            }

            $normal = self::normalkan((string) $nilai);

            if ($normal !== '') {
                $bersih[$normal] = true;
            }
        }

        $hasil = array_keys($bersih);
        sort($hasil);

        return $hasil;
    }

    /**
     * Alias hanya boleh datang dari kode kita sendiri. Penjagaan ini murah dan
     * menutup satu-satunya celah interpolasi di berkas ini.
     */
    private function ekspresiLokasi(string $alias): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new \InvalidArgumentException("Alias tabel tidak sah: {$alias}");
        }

        return "UPPER(LTRIM(RTRIM({$alias}.Cabang_Untuk_Gaji)))";
    }
}
