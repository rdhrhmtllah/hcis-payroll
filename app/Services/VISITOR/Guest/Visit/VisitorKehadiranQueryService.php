<?php

namespace App\Services\VISITOR\Guest\Visit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu definisi "kehadiran tamu" untuk seluruh aplikasi.
 *
 * KEHADIRAN TIDAK PERNAH DITULIS HCIS. Aplikasi palang di gerbang menuliskan
 * Waktu_Aktual_Masuk/Keluar langsung ke N_VISITOR_Tamu_Efektif, dan
 * VisitorEfektifService::syncTamuEfektif() sengaja tidak menyentuh kedua kolom
 * itu. Kolom serupa di tabel Detail hanya jalur CADANGAN untuk catatan manual
 * saat palang tidak menandai.
 *
 * Dua kekeliruan yang berulang muncul sebelum kelas ini ada, masing-masing di
 * tempat berbeda dan masing-masing membuat orang yang benar-benar masuk menjadi
 * tak terlihat:
 *
 *  1. Membaca kolom Detail saja, tanpa menyentuh baris efektif.
 *  2. Memercayai Status_Kunjungan sebagai status kehadiran. Kolom itu status
 *     DAUR HIDUP pengajuan (lihat StatusKunjunganTamu::TERSIMPAN); tamu yang
 *     lewat palang tetap SCHEDULED di sana selamanya.
 *
 * Kehadiran karena itu selalu DITURUNKAN dari dua kolom waktu, tidak pernah
 * disimpan terpisah: keadaan turunan tidak bisa drift, duplikat yang disimpan
 * selalu bisa.
 */
class VisitorKehadiranQueryService
{
    public const TABEL_EFEKTIF = 'N_VISITOR_Tamu_Efektif';

    /** Kartu ditempel di palang dan terbaca. Jalur normal. */
    public const SUMBER_TAPPING = 'TAPPING';

    /**
     * Kartu gagal terbaca dan satpam membuka palang manual. Palang menyalakan
     * Flag_ByPass_CheckIn/Out dan menuliskan siapa yang membukanya.
     */
    public const SUMBER_BYPASS = 'BYPASS';

    /**
     * Jamnya sama sekali tidak datang dari gerbang, melainkan dicatat operator
     * di HCIS (lihat GuestRegistrationController::checkIn) dan mendarat di
     * kolom tabel Detail.
     */
    public const SUMBER_MANUAL = 'MANUAL';

    /**
     * Pasang join ke baris efektif. Wajib dipanggil sebelum memakai fragmen SQL
     * di bawah, karena semuanya menyebut alias efektif.
     */
    public function join(Builder $query, string $headerAlias = 'h', string $alias = 'e'): Builder
    {
        return $query->leftJoin(
            self::TABEL_EFEKTIF.' as '.$alias,
            $alias.'.No_Transaksi',
            '=',
            $headerAlias.'.No_Transaksi',
        );
    }

    /** Waktu masuk: palang menang, catatan manual cadangan. */
    public function masukSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "COALESCE({$efektif}.Waktu_Aktual_Masuk, {$detail}.Waktu_Aktual_Masuk)";
    }

    public function keluarSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "COALESCE({$efektif}.Waktu_Aktual_Keluar, {$detail}.Waktu_Aktual_Keluar)";
    }

    /**
     * ASAL-USUL jam masuk: TAPPING, BYPASS, atau MANUAL. NULL bila belum masuk.
     *
     * Tiga cabang, bukan dua. Penanda bypass saja hanya memisahkan "kartu
     * terbaca" dari "satpam membukakan", dan menenggelamkan kasus ketiga:
     * jam yang tidak pernah menyentuh gerbang karena diketik operator di HCIS.
     * Tanpa cabang MANUAL, catatan manual tampil persis seperti tapping yang
     * sah — yang justru pertanyaan yang ingin dijawab penanda ini.
     *
     * Urutan cabangnya WAJIB mengikuti masukSql(): baris efektif menang, dan
     * kolom Detail hanya terbaca bila gerbang tidak menuliskan apa pun. Dua
     * urutan yang berbeda untuk satu pasangan kolom membuat jam dan asal-usul
     * yang ditampilkan berasal dari sumber yang berlainan.
     */
    public function sumberMasukSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "CASE
            WHEN {$efektif}.Waktu_Aktual_Masuk IS NOT NULL AND {$efektif}.Flag_ByPass_CheckIn = 'Y' THEN '".self::SUMBER_BYPASS."'
            WHEN {$efektif}.Waktu_Aktual_Masuk IS NOT NULL THEN '".self::SUMBER_TAPPING."'
            WHEN {$detail}.Waktu_Aktual_Masuk IS NOT NULL THEN '".self::SUMBER_MANUAL."'
            ELSE NULL END";
    }

    public function sumberKeluarSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "CASE
            WHEN {$efektif}.Waktu_Aktual_Keluar IS NOT NULL AND {$efektif}.Flag_ByPass_CheckOut = 'Y' THEN '".self::SUMBER_BYPASS."'
            WHEN {$efektif}.Waktu_Aktual_Keluar IS NOT NULL THEN '".self::SUMBER_TAPPING."'
            WHEN {$detail}.Waktu_Aktual_Keluar IS NOT NULL THEN '".self::SUMBER_MANUAL."'
            ELSE NULL END";
    }

    /**
     * Nama petugas yang membuka palang, HANYA bila barisnya memang bypass.
     *
     * Kolom penandanya dan kolom namanya ditulis palang secara terpisah, dan
     * tidak ada yang mengosongkan nama ketika bypass dibatalkan atau ditimpa
     * tapping berikutnya. Membaca kolom nama mentah-mentah karena itu bisa
     * menuduh satpam membuka palang untuk kunjungan yang tidak pernah ia
     * sentuh. Penanda yang memutuskan, bukan ada-tidaknya isi nama.
     */
    public function bypassMasukOlehSql(string $efektif = 'e'): string
    {
        return "CASE WHEN {$efektif}.Flag_ByPass_CheckIn = 'Y' THEN {$efektif}.User_Bypass_CheckIn END";
    }

    public function bypassKeluarOlehSql(string $efektif = 'e'): string
    {
        return "CASE WHEN {$efektif}.Flag_ByPass_CheckOut = 'Y' THEN {$efektif}.User_Bypass_CheckOut END";
    }

    /**
     * Penyaring "sudah masuk" dalam bentuk SARGABLE.
     *
     * `COALESCE(a, b) IS NOT NULL` setara secara logika tetapi membungkus kolom
     * dalam fungsi, sehingga IX_VISITOR_Tamu_Efektif_Kehadiran tidak akan pernah
     * terpakai. Bentuk OR di bawah membiarkan kedua indeks tetap bisa dipakai.
     */
    public function adaMasukSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "({$efektif}.Waktu_Aktual_Masuk IS NOT NULL OR {$detail}.Waktu_Aktual_Masuk IS NOT NULL)";
    }

    public function belumMasukSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "({$efektif}.Waktu_Aktual_Masuk IS NULL AND {$detail}.Waktu_Aktual_Masuk IS NULL)";
    }

    public function adaKeluarSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "({$efektif}.Waktu_Aktual_Keluar IS NOT NULL OR {$detail}.Waktu_Aktual_Keluar IS NOT NULL)";
    }

    public function belumKeluarSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return "({$efektif}.Waktu_Aktual_Keluar IS NULL AND {$detail}.Waktu_Aktual_Keluar IS NULL)";
    }

    /**
     * Sedang berada di dalam area: sudah masuk, belum keluar, dan kunjungannya
     * belum berakhir di gerbang. Tanpa syarat terakhir, tamu yang tak pernah
     * tercatat keluar terhitung "di dalam" selamanya — padahal siklusnya
     * sudah KELUAR_TAK_TERCATAT (lihat siklusKunjunganSql).
     */
    public function diDalamSql(string $efektif = 'e', string $detail = 'd'): string
    {
        return $this->adaMasukSql($efektif, $detail).' AND '.$this->belumKeluarSql($efektif, $detail)
            ." AND ISNULL({$efektif}.Status_Efektif, '') <> 'EXPIRED'";
    }

    /**
     * Estimasi Keluar efektif. Baris lama tanpa estimasi mendapat akhir hari
     * kunjungannya — jendela yang sama dengan yang dipakai expiry proyeksi.
     */
    public function estimasiKeluarSql(string $header = 'h', string $detail = 'd'): string
    {
        return "COALESCE({$detail}.Waktu_Selesai_Kunjungan, DATEADD(day, 1, CAST({$header}.Tanggal_Kunjungan AS datetime2(0))))";
    }

    public function estimasiMasukSql(string $header = 'h', string $detail = 'd'): string
    {
        return "COALESCE({$detail}.Waktu_Mulai_Kunjungan, CAST({$header}.Tanggal_Kunjungan AS datetime2(0)))";
    }

    /**
     * Rentang estimasi beririsan dengan satu hari kalender. Pengganti
     * `Tanggal_Kunjungan = hari itu`: kunjungan MITRA berlangsung beberapa hari
     * dan kunjungan VISITOR bisa melewati tengah malam.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public function beririsanHariSql(string $tanggal, string $header = 'h', string $detail = 'd'): array
    {
        return [
            $this->estimasiMasukSql($header, $detail).' < DATEADD(day, 1, CAST(? AS date)) AND '
                .$this->estimasiKeluarSql($header, $detail).' > CAST(? AS date)',
            [$tanggal, $tanggal],
        ];
    }

    /**
     * Tanggal berakhirnya kunjungan dalam bentuk `date`.
     *
     * Berisi tanggal dari Waktu_Selesai_Kunjungan bila ditentukan, atau
     * Tanggal_Kunjungan bila registrasi lama belum punya jam detail — KECUALI
     * akhir akses tamu di baris efektif lebih lambat. Sejak 29 Sep 2026 kolom
     * itu memuat jendela permit COKLAT yang disetujui verifikator (keputusan
     * user: masa berlaku izin tamu ikut admin; lihat
     * VisitorEfektifService::jendelaAksesTamu). Tanpanya kunjungan yang
     * izinnya diperpanjang terbaca "batal" begitu tanggal kunjungan aslinya
     * lewat, padahal izinnya masih berlaku.
     *
     * Sengaja TANPA sub-kueri: ekspresi siklus dipakai di dalam COUNT(CASE …)
     * dan GROUP BY (statistik jadwal kedatangan, Export Studio), dan SQL Server
     * menolak sub-kueri di sana. Baris efektif selalu disinkron ulang oleh
     * setiap penulis jam kunjungan dan setiap sinkron permit COKLAT.
     */
    public function tanggalSelesaiSql(string $header = 'h', string $detail = 'd', string $efektif = 'e'): string
    {
        $kunjungan = "COALESCE({$detail}.Waktu_Selesai_Kunjungan, {$header}.Tanggal_Kunjungan)";

        return "CAST(CASE WHEN {$efektif}.Waktu_Estimasi_Keluar > {$kunjungan}"
            ." THEN {$efektif}.Waktu_Estimasi_Keluar ELSE {$kunjungan} END AS date)";
    }

    /**
     * Status TAMPIL sebuah kunjungan — satu nilai yang menjawab "sampai mana".
     * Kodenya milik StatusKunjunganTamu.
     *
     * Status_Efektif menyatakan barisnya HIDUP DI GERBANG, bukan bahwa orangnya
     * berada di dalam; karena itu kehadiran diturunkan dari jam palang, bukan
     * dari status proyeksi.
     *
     * Urutannya menentukan dan bukan selera:
     *
     *  - KEPUTUSAN AKHIR yang tersimpan (menunggu, ditolak, ditarik, dicabut)
     *    menang atas segalanya. Proyeksi berstatus DIBATALKAN tanpa header
     *    REVOKED (pencabutan sebelum DDL-2026-121) tetap dibaca REVOKED.
     *  - KEHADIRAN NYATA (sudah keluar / di dalam) menang atas jadwal, karena
     *    itulah fakta yang tercatat palang.
     *  - Baru sesudahnya jadwal: Tanggal selesai kunjungan lewat tanpa pernah masuk
     *    berarti CANCELLED, bukan "terjadwal" selamanya. Berubah menjadi CANCELLED
     *    hanya setelah tanggal berakhirnya terlewati (bukan sekadar lewat jam di hari yang sama).
     */
    public function siklusKunjunganSql(string $header = 'h', string $efektif = 'e', string $detail = 'd'): string
    {
        $masuk = $this->masukSql($efektif, $detail);
        $keluar = $this->keluarSql($efektif, $detail);
        $akhirTersimpan = StatusKunjunganTamu::sqlDaftar(StatusKunjunganTamu::AKHIR_TERSIMPAN);
        $tanggalSelesai = $this->tanggalSelesaiSql($header, $detail, $efektif);

        return "CASE
            WHEN {$header}.Status_Kunjungan IN ({$akhirTersimpan}) THEN {$header}.Status_Kunjungan
            WHEN {$efektif}.Status_Efektif = 'DIBATALKAN' THEN '".StatusKunjunganTamu::REVOKED."'
            WHEN {$keluar} IS NOT NULL THEN '".StatusKunjunganTamu::CHECKED_OUT."'
            WHEN {$masuk} IS NOT NULL AND {$efektif}.Status_Efektif = 'EXPIRED' THEN '".StatusKunjunganTamu::KELUAR_TAK_TERCATAT."'
            WHEN {$masuk} IS NOT NULL THEN '".StatusKunjunganTamu::CHECKED_IN."'
            WHEN {$tanggalSelesai} < CAST(SYSDATETIME() AS date) THEN '".StatusKunjunganTamu::CANCELLED."'
            ELSE '".StatusKunjunganTamu::SCHEDULED."' END";
    }

    /**
     * Jam kunjungan satu tamu untuk detail permit COKLAT: kapan diperkirakan
     * masuk dan keluar (dari pengajuan tamu), plus jam aktual bila sudah
     * lewat palang. Admin SHE memakainya untuk berjaga menyambut tamu yang
     * akan diinspeksi — permit tamu tidak memakai slot inspeksi.
     *
     * @return array{estimasi_masuk: ?string, estimasi_keluar: ?string, aktual_masuk: ?string,
     *               aktual_keluar: ?string, status: ?string}|null
     */
    public function ringkasanKunjungan(?string $noTransaksiTamu): ?array
    {
        if (! $noTransaksiTamu) {
            return null;
        }

        $baris = $this->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $noTransaksiTamu)
        )
            ->selectRaw('CONVERT(varchar(16), '.$this->estimasiMasukSql().', 120) as estimasi_masuk')
            ->selectRaw('CONVERT(varchar(16), '.$this->estimasiKeluarSql().', 120) as estimasi_keluar')
            ->selectRaw('CONVERT(varchar(16), '.$this->masukSql().', 120) as aktual_masuk')
            ->selectRaw('CONVERT(varchar(16), '.$this->keluarSql().', 120) as aktual_keluar')
            ->selectRaw($this->siklusKunjunganSql().' as status')
            ->first();

        return $baris ? (array) $baris : null;
    }
}
