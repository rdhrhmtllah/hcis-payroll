<?php

namespace App\Services\VISITOR\Workflow\Reporting\Dashboard;

use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Baris 3-5 dashboard Visitor: tren, jam sibuk, permit, kesehatan data.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Dashboard.
 *
 * Waktu masuk/keluar TIDAK ditulis HCIS. Aplikasi palang di gerbang menuliskannya
 * langsung ke N_VISITOR_Tamu_Efektif, dan syncTamuEfektif sengaja tidak menyentuh
 * kedua kolom itu. Karena itu kehadiran dibaca COALESCE(efektif, detail) — sama
 * seperti tiga controller lain; kolom detail hanya cadangan catatan manual.
 *
 * Permit membaca N_VISITOR_Permit_Efektif karena tanggal berlakunya memang
 * ditulis ke sana dan tidak berubah setelahnya.
 */
class VisitorDashboardAnalitikService
{
    private const T_H = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_D = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    private const T_E = 'N_VISITOR_Tamu_Efektif';

    /** Kehadiran: baris efektif dulu (ditulis palang), detail sebagai cadangan. */
    private const MASUK = 'COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk)';

    private const KELUAR = 'COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar)';

    /**
     * Bentuk SARGABLE dari "MASUK IS NOT NULL" — setara secara logika, tetapi
     * masih bisa memakai indeks bila kelak ada. `COALESCE(a, b) IS NOT NULL`
     * membungkus kolomnya dalam fungsi sehingga indeks apa pun diabaikan, dan
     * kerugiannya tidak terlihat sekarang justru karena kedua kolom ini memang
     * belum berindeks — jebakan yang menyala belakangan.
     */
    private const ADA_MASUK = '(e.Waktu_Aktual_Masuk IS NOT NULL OR d.Waktu_Aktual_Masuk IS NOT NULL)';

    /**
     * @param  string|null  $domain  'tamu' | 'permit' | null (kedua ranah)
     */
    public function ringkasan(string $dari, string $sampai, ?string $domain = null, ?string $tipeTamu = null): array
    {
        // Ranah tetangga tidak dikueri sama sekali, bukan sekadar tidak
        // dirender: analitik permit dan tamu sama-sama menyapu tabel transaksi,
        // dan menghitung keduanya untuk satu tab berarti separuh biayanya
        // dibuang. Tanpa argumen perilakunya tetap seperti sebelumnya.
        $tamu = $domain !== 'permit';
        $permit = $domain !== 'tamu';

        return array_merge(
            $tamu ? [
                'tren' => $this->tren($dari, $sampai, $tipeTamu),
                'sebaran_jam' => $this->sebaranJam($dari, $sampai, $tipeTamu),
                'kesehatan_data' => $this->kesehatanData($dari, $sampai, $tipeTamu),
            ] : [],
            $permit ? [
                'permit_tren' => $this->permitTren($dari, $sampai),
                'permit_kategori' => $this->permitPerKategori(),
            ] : [],
        );
    }

    /**
     * Tren permit dalam rentang: diajukan, disetujui, ditolak per hari.
     *
     * Tiga peristiwa dengan TIGA kolom tanggal berbeda, jadi tidak bisa satu
     * GROUP BY: pengajuan bertanggal Created_At, persetujuan Approved_At, dan
     * penolakan hanya punya Updated_At (Approved_At tetap NULL saat ditolak —
     * terverifikasi pada data dev). Menyamakan ketiganya ke satu kolom akan
     * menaruh penolakan pada hari pengajuannya.
     *
     * @return array<int, array{tanggal:string, diajukan:int, disetujui:int, ditolak:int}>
     */
    public function permitTren(string $dari, string $sampai): array
    {
        $hitung = [
            'diajukan' => $this->permitPerHari($dari, $sampai, 'h.Created_At'),
            'disetujui' => $this->permitPerHari($dari, $sampai, 'h.Approved_At', 'Y'),
            'ditolak' => $this->permitPerHari($dari, $sampai, 'COALESCE(h.Updated_At, h.Created_At)', 'T'),
        ];

        // Hari tanpa peristiwa tetap dikirim sebagai nol. Kalau harinya
        // dilewati, sumbu waktunya merapat dan grafik dua pengajuan berjarak
        // sepekan terbaca seolah terjadi berturut-turut.
        $hasil = [];

        for ($hari = strtotime($dari); $hari <= strtotime($sampai); $hari = strtotime('+1 day', $hari)) {
            $tanggal = date('Y-m-d', $hari);
            $hasil[] = [
                'tanggal' => $tanggal,
                'diajukan' => $hitung['diajukan'][$tanggal] ?? 0,
                'disetujui' => $hitung['disetujui'][$tanggal] ?? 0,
                'ditolak' => $hitung['ditolak'][$tanggal] ?? 0,
            ];
        }

        return $hasil;
    }

    /**
     * @param  string  $kolom  ekspresi tanggal peristiwa
     * @param  string|null  $flag  nilai Flag_Approval yang dihitung; null = semua
     * @return array<string, int> tanggal => jumlah
     */
    private function permitPerHari(string $dari, string $sampai, string $kolom, ?string $flag = null): array
    {
        $query = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            // Pengajuan yang dibatalkan tidak dihitung, sejalan dengan seluruh
            // modul yang menyaring Flag_Aktif='Y'.
            ->where('h.Flag_Aktif', 'Y')
            ->whereRaw("{$kolom} IS NOT NULL")
            ->whereRaw("CAST({$kolom} AS date) >= ?", [$dari])
            ->whereRaw("CAST({$kolom} AS date) <= ?", [$sampai]);

        if ($flag !== null) {
            $query->where('h.Flag_Approval', $flag);
        }

        return $query
            ->groupBy(DB::raw("CAST({$kolom} AS date)"))
            ->select(DB::raw("CAST({$kolom} AS date) as tanggal"), DB::raw('COUNT(*) as jumlah'))
            ->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->tanggal, 0, 10) => (int) $r->jumlah])
            ->all();
    }

    /** Dua seri terpisah: rombongan membuat transaksi dan orang jauh berbeda. */
    public function tren(string $dari, string $sampai, ?string $tipeTamu = null): array
    {
        return $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai, $tipeTamu)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->groupBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'))
            ->orderBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'))
            ->select(
                DB::raw('CAST(h.Tanggal_Kunjungan AS date) as tanggal'),
                DB::raw('COUNT(*) as transaksi'),
                DB::raw('SUM(CASE WHEN ISNULL(d.Jumlah_Tamu, 1) < 1 THEN 1 ELSE ISNULL(d.Jumlah_Tamu, 1) END) as orang'),
            )
            ->get()
            ->map(fn ($r) => [
                'tanggal' => substr((string) $r->tanggal, 0, 10),
                'transaksi' => (int) $r->transaksi,
                'orang' => (int) $r->orang,
            ])
            ->all();
    }

    /**
     * Sebaran jam kedatangan. Dikelompokkan per tanggal + jam lalu dipetakan ke
     * hari di PHP, supaya hasilnya tidak bergantung pada SET DATEFIRST server.
     */
    public function sebaranJam(string $dari, string $sampai, ?string $tipeTamu = null): array
    {
        return $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai, $tipeTamu)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin(self::T_E.' as e', 'e.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->whereRaw(self::ADA_MASUK)
            ->groupBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'), DB::raw('DATEPART(hour, '.self::MASUK.')'))
            ->select(
                DB::raw('CAST(h.Tanggal_Kunjungan AS date) as tanggal'),
                DB::raw('DATEPART(hour, '.self::MASUK.') as jam'),
                DB::raw('COUNT(*) as jumlah'),
            )
            ->get()
            ->map(fn ($r) => [
                'tanggal' => substr((string) $r->tanggal, 0, 10),
                'jam' => (int) $r->jam,
                'jumlah' => (int) $r->jumlah,
            ])
            ->all();
    }

    /**
     * Sebaran permit aktif per kategori.
     *
     * Sejak permit COKLAT, satu tabel ini memuat dua populasi yang besarnya
     * jauh berbeda: permit kendaraan karyawan (puluhan) dan permit akses tamu
     * (satu per tamu, jadi ratusan). Menyembunyikan salah satunya membuat
     * grafik "per kategori" berbohong tentang kategori yang ada; menggabungkan
     * keduanya tanpa penanda membuat angka permit karyawan tenggelam.
     *
     * Karena itu barisnya dipecah per (kategori, sumber) dan membawa kunci
     * 'sumber'. Kunci 'kategori' dan 'jumlah' sengaja dipertahankan apa adanya
     * supaya konsumen lama tidak perlu berubah bersamaan.
     *
     * @return array<int, array{kategori:string, sumber:string, jumlah:int}>
     */
    public function permitPerKategori(): array
    {
        return DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            // Akses sementara menuju pos inspeksi bukan permit; menghitungnya
            // di sini membuat grafik melaporkan izin yang belum pernah
            // disetujui siapa pun (DDL-2026-132).
            ->where('Jenis_Akses', VisitorEfektifService::JENIS_PERMIT)
            ->whereRaw('(Tanggal_Kadaluarsa IS NULL OR Tanggal_Kadaluarsa >= CAST(GETDATE() AS date))')
            // Izin berjam yang jendelanya sudah habis pagi ini bukan izin aktif,
            // walau tanggalnya masih hari ini dan penyapu belum jalan (audit M10).
            ->whereRaw('(Waktu_Berakhir IS NULL OR Waktu_Berakhir >= GETDATE())')
            ->groupBy('Nama_Kategori_Permit', 'Sumber_Permit')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(
                DB::raw("ISNULL(Nama_Kategori_Permit, '-') as kategori"),
                // ISNULL, bukan perbandingan langsung: kolomnya nullable dan
                // baris yang belum ter-backfill akan mengelompok sebagai NULL
                // tersendiri — satu kategori hantu di grafik.
                DB::raw("ISNULL(Sumber_Permit, 'KARYAWAN') as sumber"),
                DB::raw('COUNT(*) as jumlah'),
                DB::raw("MAX(ISNULL(Kode_Warna_UI, '')) as kode_warna"),
            )
            ->get()
            ->map(fn ($r) => [
                'kategori' => (string) $r->kategori,
                'sumber' => (string) $r->sumber,
                'jumlah' => (int) $r->jumlah,
                'kode_warna' => (string) $r->kode_warna,
            ])
            ->all();
    }

    public function kesehatanData(string $dari, string $sampai, ?string $tipeTamu = null): array
    {
        $row = $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai, $tipeTamu)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin(self::T_E.' as e', 'e.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN '.self::MASUK.' IS NOT NULL THEN 1 ELSE 0 END) as sudah_masuk')
            ->selectRaw('SUM(CASE WHEN '.self::MASUK.' IS NOT NULL AND '.self::KELUAR.' IS NULL THEN 1 ELSE 0 END) as tanpa_waktu_keluar')
            ->selectRaw("SUM(CASE WHEN d.No_Identitas IS NULL OR LTRIM(RTRIM(d.No_Identitas)) = '' THEN 1 ELSE 0 END) as tanpa_identitas")
            ->selectRaw("SUM(CASE WHEN d.Nomor_Handphone IS NULL OR LTRIM(RTRIM(d.Nomor_Handphone)) = '' THEN 1 ELSE 0 END) as tanpa_hp")
            ->first();

        $sudahMasuk = (int) ($row->sudah_masuk ?? 0);
        $tanpaKeluar = (int) ($row->tanpa_waktu_keluar ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'sudah_masuk' => $sudahMasuk,
            'tanpa_waktu_keluar' => $tanpaKeluar,
            'tanpa_identitas' => (int) ($row->tanpa_identitas ?? 0),
            'tanpa_hp' => (int) ($row->tanpa_hp ?? 0),
            'persen_tanpa_waktu_keluar' => $sudahMasuk > 0 ? round($tanpaKeluar / $sudahMasuk * 100, 1) : 0.0,
        ];
    }

    /** Rentang setengah terbuka: Tanggal_Kunjungan bertipe datetime, bukan date. */
    private function dalamRentang(Builder $q, string $dari, string $sampai, ?string $tipeTamu = null): Builder
    {
        $q->whereRaw('h.Tanggal_Kunjungan >= ?', [$dari])
            ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$sampai]);

        // Grafik halaman Regular dan B2B dipisah per tipe (audit ulang 28 Sep).
        // Disaring lewat EXISTS pada detail: kueri pemanggil men-join detail
        // SESUDAH rentang ini dirakit.
        if ($tipeTamu !== null) {
            $q->whereExists(function ($s) use ($tipeTamu) {
                $s->select(DB::raw(1))
                    ->from(self::T_D.' as dt')
                    ->whereColumn('dt.No_Transaksi', 'h.No_Transaksi');
                TipeTamu::saring($s, $tipeTamu, 'dt');
            });
        }

        return $q;
    }
}
