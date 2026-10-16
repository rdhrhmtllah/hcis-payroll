<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dataset transaksi tamu yang telah efektif setelah approval/auto-approval.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Export Studio.
 * Grain-nya satu transaksi, bukan satu orang; Jumlah_Tamu menjadi ukuran
 * terpisah supaya rombongan tidak dihitung sebagai satu orang.
 */
class VisitorGuestDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    /**
     * Waktu masuk resmi: palang menang, catatan manual jadi cadangan.
     *
     * Ditulis tetap di sini, bukan memanggil VisitorKehadiranQueryService,
     * karena dimensinya dipakai di dalam GROUP BY dan harus berupa teks SQL
     * yang tetap — bukan hasil panggilan yang bisa berubah bentuk antar baris.
     * Bentuknya tetap harus sama dengan milik service itu; penjaganya ada di
     * ExportStudioTamuDimensiTest.
     */
    private const MASUK_SQL = 'COALESCE(e.Waktu_Aktual_Masuk, td.Waktu_Aktual_Masuk)';

    private const KELUAR_SQL = 'COALESCE(e.Waktu_Aktual_Keluar, td.Waktu_Aktual_Keluar)';

    /**
     * Keadaan izin gerbang turunan kunjungan.
     *
     * Sengaja CASE biasa, bukan sub-kueri: SQL Server menolak sub-kueri di
     * dalam daftar GROUP BY, dan setiap dimensi Export Studio berakhir di sana.
     * Karena itu permitnya di-LEFT JOIN di baseQuery(), bukan dibaca inline.
     */
    // Urutan cabang mengikuti permitStatusSql (dicabut → keputusan → jendela),
    // tanpa sub-kueri: ditolak menang atas baris gerbang yang kedaluwarsa, dan
    // izin yang jendelanya lewat terbaca KEDALUWARSA walau penyapu belum jalan
    // (audit 27 Sep 2026, H4). Satu-satunya yang tak terjangkau tanpa
    // sub-kueri: approval yang kedaluwarsa sebelum diputus (tetap MENUNGGU).
    private const STATUS_PERMIT_SQL = "CASE
        WHEN p.No_Transaksi IS NULL THEN 'TANPA_PERMIT'
        WHEN pe.Status_Efektif = 'DIBATALKAN' THEN 'DICABUT'
        WHEN p.Flag_Approval = 'T' THEN 'DITOLAK'
        WHEN p.Flag_Approval = 'Y' AND pd.Waktu_Berakhir IS NOT NULL AND pd.Waktu_Berakhir < SYSDATETIME() THEN 'KEDALUWARSA'
        WHEN p.Flag_Approval = 'Y' AND pd.Waktu_Berakhir IS NULL AND pd.Tanggal_Kadaluarsa < CAST(SYSDATETIME() AS date) THEN 'KEDALUWARSA'
        WHEN pe.Status_Efektif = 'EXPIRED' AND p.Flag_Approval = 'Y' THEN 'KEDALUWARSA'
        WHEN p.Flag_Approval = 'Y' THEN 'AKTIF'
        ELSE 'MENUNGGU' END";

    /** Keadaan siklus kunjungan — urutannya sama dengan yang dipakai monitoring. */
    private static function siklusSql(): string
    {
        return (new VisitorKehadiranQueryService)->siklusKunjunganSql('h', 'e', 'td');
    }

    public function kunci(): string
    {
        return 'tamu';
    }

    public function label(): string
    {
        return 'Buku Tamu Efektif';
    }

    public function grain(): string
    {
        return 'Satu baris = satu transaksi kunjungan tamu yang telah efektif.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('no_transaksi', 'No. Transaksi', 'e.No_Transaksi', 'teks', 'identitas'),
            new Dimensi('nama_tamu', 'Nama Tamu', 'e.Nama_Tamu', 'teks', 'identitas'),
            new Dimensi('instansi_asal', 'Instansi / Asal', 'e.Instansi_Asal', 'teks', 'identitas'),
            new Dimensi('no_hp', 'Nomor HP', 'e.No_HP', 'teks', 'identitas', true),
            new Dimensi('jenis_identitas', 'Jenis Identitas', 'e.Jenis_Identitas', 'teks', 'identitas'),
            new Dimensi('no_identitas', 'Nomor Identitas', 'e.No_Identitas', 'teks', 'identitas', true),
            new Dimensi('keperluan', 'Keperluan', 'e.Keperluan', 'teks', 'kunjungan'),
            new Dimensi('tujuan_detail', 'Tujuan / PIC Detail', 'e.Keterangan_Tujuan_Detail', 'teks', 'kunjungan'),
            new Dimensi('keterangan_tambahan', 'Keterangan Tambahan', 'e.Keterangan_Tambahan', 'teks', 'kunjungan'),
            new Dimensi('kode_pic', 'Kode Karyawan PIC', 'e.Kode_Karyawan_PIC', 'teks', 'pic'),
            new Dimensi('nama_pic', 'Nama PIC', 'e.Nama_PIC', 'teks', 'pic'),
            new Dimensi('divisi_pic', 'Divisi PIC', 'e.Divisi_PIC', 'teks', 'pic'),
            new Dimensi('jenis_kendaraan', 'Jenis Kendaraan', 'e.Jenis_Kendaraan', 'teks', 'kendaraan'),
            new Dimensi('no_plat', 'Nomor Plat', 'e.No_Plat_Kendaraan', 'teks', 'kendaraan'),
            new Dimensi('tanggal_kunjungan', 'Tanggal Kunjungan', 'e.Tanggal_Kunjungan', 'tanggal', 'waktu', false, 'tanggal'),
            new Dimensi('bulan_kunjungan', 'Bulan Kunjungan', 'CONVERT(char(7), e.Tanggal_Kunjungan, 120)', 'teks', 'waktu'),
            new Dimensi('waktu_estimasi', 'Waktu Estimasi', 'e.Waktu_Estimasi_Kunjungan', 'teks', 'waktu'),
            new Dimensi('waktu_masuk', 'Waktu Aktual Masuk', 'e.Waktu_Aktual_Masuk', 'tanggal', 'waktu', false, 'tanggal_jam'),
            new Dimensi('waktu_keluar', 'Waktu Aktual Keluar', 'e.Waktu_Aktual_Keluar', 'tanggal', 'waktu', false, 'tanggal_jam'),
            new Dimensi('jumlah_tamu', 'Jumlah Tamu', 'e.Jumlah_Tamu', 'angka', 'kunjungan'),
            // Rombongan yang namanya benar-benar tercatat. Manifesnya sudah
            // tersimpan sejak registrasi tetapi tidak pernah bisa diekspor —
            // laporan hanya memuat angka "3" tanpa satu pun nama.
            new Dimensi('daftar_anggota', 'Anggota Rombongan', 'rb.Daftar_Anggota', 'teks', 'kunjungan'),
            new Dimensi('jumlah_anggota_tercatat', 'Anggota Tercatat Namanya', 'ISNULL(rb.Jumlah_Anggota, 0)', 'angka', 'kunjungan'),

            // ── Tipe & asal pendaftaran ──────────────────────────────────
            new Dimensi('tipe_tamu', 'Tipe Tamu', "ISNULL(e.Tipe_Tamu, 'REGULAR')", 'teks', 'identitas'),
            // VISITOR / MITRA — dihitung sistem dari rentang estimasi (> 24 jam = MITRA).
            new Dimensi('tamu_kategori', 'Kategori Tamu', 'td.Tamu_Kategori', 'teks', 'identitas'),
            new Dimensi('estimasi_masuk', 'Estimasi Masuk', 'td.Waktu_Mulai_Kunjungan', 'tanggal', 'kunjungan', false, 'tanggal_jam'),
            new Dimensi('estimasi_keluar', 'Estimasi Keluar', 'td.Waktu_Selesai_Kunjungan', 'tanggal', 'kunjungan', false, 'tanggal_jam'),
            // Jendela akses yang BENAR-BENAR dibaca palang. Sejak 29 Sep 2026
            // sama dengan jendela permit COKLAT bila sudah disetujui, sehingga
            // bisa berbeda dari estimasi yang diajukan di atas.
            new Dimensi('akses_mulai', 'Akses Gerbang Mulai', 'e.Waktu_Estimasi_Masuk', 'tanggal', 'kunjungan', false, 'tanggal_jam'),
            new Dimensi('akses_berakhir', 'Akses Gerbang Berakhir', 'e.Waktu_Estimasi_Keluar', 'tanggal', 'kunjungan', false, 'tanggal_jam'),
            new Dimensi('unit_b2b', 'Unit Pengaju B2B', 'e.B2B_Unit', 'teks', 'identitas'),
            new Dimensi(
                'sumber_pendaftaran',
                'Asal Pendaftaran',
                "CASE WHEN h.Id_Api_Aplikasi IS NULL THEN 'HCIS' ELSE 'MITRA' END",
                'teks',
                'identitas',
            ),
            new Dimensi('nama_mitra', 'Aplikasi Mitra', 'ap.Nama_Aplikasi', 'teks', 'identitas'),
            new Dimensi('referensi_mitra', 'Referensi Mitra', 'h.Referensi_Mitra', 'teks', 'identitas'),

            // ── Permit turunan kunjungan ─────────────────────────────────
            new Dimensi('no_permit', 'No. Transaksi Permit', 'p.No_Transaksi', 'teks', 'permit'),
            new Dimensi('status_permit', 'Status Permit', self::STATUS_PERMIT_SQL, 'teks', 'permit'),
            new Dimensi('kategori_permit', 'Kategori Permit', 'pd.Snapshot_Kategori_Permit', 'teks', 'permit'),
            new Dimensi('durasi_permit', 'Durasi Permit', 'pd.Snapshot_Nama_Durasi', 'teks', 'permit'),
            new Dimensi('permit_mulai', 'Permit Mulai Berlaku', 'pd.Waktu_Mulai_Berlaku', 'tanggal', 'permit', false, 'tanggal_jam'),
            new Dimensi('permit_berakhir', 'Permit Berakhir', 'pd.Waktu_Berakhir', 'tanggal', 'permit', false, 'tanggal_jam'),

            // Inspeksi kedatangan tamu B2B dan hasilnya (dicatat verifikator).
            new Dimensi('pos_inspeksi', 'Pos Inspeksi', 'ib.Snapshot_Nama_Pos', 'teks', 'inspeksi'),
            new Dimensi('status_inspeksi', 'Status Inspeksi', 'ib.Status', 'teks', 'inspeksi'),
            new Dimensi('diinspeksi_oleh', 'Diinspeksi Oleh', 'ib.Diinspeksi_Oleh', 'teks', 'inspeksi'),
            new Dimensi('diinspeksi_pada', 'Diinspeksi Pada', 'ib.Diinspeksi_At', 'tanggal', 'inspeksi', false, 'tanggal_jam'),
            new Dimensi('model_kendaraan_inspeksi', 'Model Kendaraan (Inspeksi)', 'pe.Nama_Model_Kendaraan', 'teks', 'inspeksi'),
            new Dimensi('warna_kendaraan_inspeksi', 'Warna Kendaraan (Inspeksi)', 'pe.Warna_Kendaraan', 'teks', 'inspeksi'),
            new Dimensi('tahun_kendaraan_inspeksi', 'Tahun Kendaraan (Inspeksi)', 'pe.Tahun_Kendaraan', 'angka', 'inspeksi'),
            new Dimensi('foto_pemeriksaan', 'Foto Pemeriksaan', VisitorPermitDataset::statusFotoSql('fd'), 'teks', 'inspeksi'),
            new Dimensi('jumlah_foto_pemeriksaan', 'Jumlah Foto Pemeriksaan', 'ISNULL(fd.n, 0)', 'angka', 'inspeksi'),
            // Tautan berkas bertanda tangan 1 hari lewat domain HCIS (30 Sep 2026).
            new Dimensi(
                'tautan_foto_kendaraan_tamu',
                'Tautan Foto Kendaraan Tamu',
                "CASE WHEN LTRIM(RTRIM(ISNULL(td.Path_Foto_Kendaraan, ''))) <> '' THEN CAST(td.Id_Transaksi_Tamu_Detail AS varchar(20)) END",
                'teks',
                'inspeksi',
                true,
                'tautan:visitor_foto_tamu',
            ),
            ...VisitorPermitDataset::dimensiTautanFoto('fd', 'tautan_permit', ' (Pemeriksaan Permit)'),

            new Dimensi('status', 'Status Efektif', 'e.Status_Efektif', 'teks', 'lifecycle'),
            // Keadaan SIKLUS — apa yang benar-benar terjadi pada kunjungannya.
            //
            // Status_Efektif di atas menyatakan barisnya hidup di gerbang, bukan
            // bahwa tamunya datang. Di development seluruh 10 barisnya bernilai
            // 'AKTIF', sehingga mengelompokkan laporan dengannya menghasilkan
            // satu baris berisi segalanya — termasuk tamu yang tidak pernah
            // muncul dan yang sudah pulang.
            new Dimensi('siklus_kunjungan', 'Keadaan Kunjungan', self::siklusSql(), 'teks', 'lifecycle'),
            new Dimensi('alasan_status', 'Alasan Status', 'e.Alasan_Status', 'teks', 'lifecycle'),
            new Dimensi('disetujui_oleh', 'Disetujui Oleh', 'e.Disetujui_Oleh_Nama', 'teks', 'audit'),
            new Dimensi('disetujui_pada', 'Disetujui Pada', 'e.Disetujui_At', 'tanggal', 'audit', false, 'tanggal_jam'),
            new Dimensi('dibatalkan_oleh', 'Dibatalkan Oleh', 'e.Dibatalkan_Oleh_Nama', 'teks', 'audit'),
            new Dimensi('dibatalkan_pada', 'Dibatalkan Pada', 'e.Dibatalkan_At', 'tanggal', 'audit', false, 'tanggal_jam'),
            new Dimensi('dibuat_oleh', 'Dibuat Oleh', 'e.Dibuat_Oleh_Nama', 'teks', 'audit'),
            new Dimensi('dibuat_pada', 'Dibuat Pada', 'e.Dibuat_At', 'tanggal', 'audit', false, 'tanggal_jam'),
        ];
    }

    public function ukuran(): array
    {
        return [
            new Ukuran('jumlah_kunjungan', 'Jumlah Kunjungan', 'COUNT(*)', 'angka'),
            new Ukuran('jumlah_tamu', 'Jumlah Tamu', 'SUM(COALESCE(e.Jumlah_Tamu, 1))', 'angka'),
            // Ukuran yang menjawab pertanyaan operasional, bukan sekadar
            // menghitung baris: berapa yang benar-benar datang, berapa yang
            // jadwalnya menguap, dan berapa yang izinnya masih tertahan.
            new Ukuran(
                'kunjungan_terealisasi',
                'Kunjungan Terealisasi',
                'SUM(CASE WHEN '.self::MASUK_SQL.' IS NOT NULL THEN 1 ELSE 0 END)',
                'angka',
            ),
            new Ukuran(
                'kunjungan_tidak_datang',
                'Tidak Datang',
                // Tanggal SELESAI kunjungan, bukan tanggal mulainya: kunjungan MITRA
                // berhari-hari belum "tidak datang" di hari keduanya (audit M10) —
                // sama dengan tanggalSelesaiSql() yang dipakai siklus kunjungan,
                // termasuk jendela permit COKLAT yang diperpanjang admin.
                'SUM(CASE WHEN '.self::MASUK_SQL.' IS NULL AND CAST(CASE WHEN e.Waktu_Estimasi_Keluar > COALESCE(td.Waktu_Selesai_Kunjungan, h.Tanggal_Kunjungan) THEN e.Waktu_Estimasi_Keluar ELSE COALESCE(td.Waktu_Selesai_Kunjungan, h.Tanggal_Kunjungan) END AS date) < CAST(GETDATE() AS date) THEN 1 ELSE 0 END)',
                'angka',
            ),
            new Ukuran(
                'permit_menunggu',
                'Permit Menunggu Verifikator',
                'SUM(CASE WHEN p.No_Transaksi IS NOT NULL AND p.Flag_Approval IS NULL THEN 1 ELSE 0 END)',
                'angka',
            ),
        ];
    }

    public function saringan(): array
    {
        return [
            new Saringan('tanggal_kunjungan', 'Tanggal Kunjungan', 'e.Tanggal_Kunjungan', 'tanggal', ['antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama']),
            new Saringan('status', 'Status Efektif', 'e.Status_Efektif', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('divisi_pic', 'Divisi PIC', 'e.Divisi_PIC', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('nama_pic', 'Nama PIC', 'e.Nama_PIC', 'teks', ['sama_dengan', 'mengandung']),
            new Saringan('instansi_asal', 'Instansi / Asal', 'e.Instansi_Asal', 'teks', ['sama_dengan', 'mengandung']),
            new Saringan('keperluan', 'Keperluan', 'e.Keperluan', 'teks', ['sama_dengan', 'mengandung']),
            new Saringan('jenis_kendaraan', 'Jenis Kendaraan', 'e.Jenis_Kendaraan', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('tipe_tamu', 'Tipe Tamu', "ISNULL(e.Tipe_Tamu, 'REGULAR')", 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('tamu_kategori', 'Kategori Tamu', 'td.Tamu_Kategori', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan(
                'sumber_pendaftaran',
                'Asal Pendaftaran',
                "CASE WHEN h.Id_Api_Aplikasi IS NULL THEN 'HCIS' ELSE 'MITRA' END",
                'teks',
                ['sama_dengan', 'salah_satu'],
            ),
            new Saringan('siklus_kunjungan', 'Keadaan Kunjungan', self::siklusSql(), 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('status_permit', 'Status Permit', self::STATUS_PERMIT_SQL, 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('nama_mitra', 'Aplikasi Mitra', 'ap.Nama_Aplikasi', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('unit_b2b', 'Unit Pengaju B2B', 'e.B2B_Unit', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('status_inspeksi', 'Status Inspeksi', 'ib.Status', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('foto_pemeriksaan', 'Foto Pemeriksaan', VisitorPermitDataset::statusFotoSql('fd'), 'teks', ['sama_dengan', 'salah_satu']),
        ];
    }

    /**
     * Proyeksi gerbang TETAP sumbernya, ditambah sambungan seperlunya.
     *
     * Tidak satu pun sambungan di bawah boleh melipatgandakan baris — grain
     * dataset ini satu transaksi, dan baris ganda akan menggandakan setiap
     * ukurannya tanpa gejala:
     *
     *  - h/td : satu baris per No_Transaksi (header dan detail kunjungan).
     *  - ap   : satu baris per Id_Api_Aplikasi.
     *  - p    : dijaga UX_VISITOR_Permit_Transaksi_Tamu, unik terfilter pada
     *           (No_Transaksi_Tamu) untuk baris aktif.
     *  - pd/pe: satu baris per permit.
     *  - rb   : sudah di-GROUP BY di dalam turunannya.
     *
     * Manifes rombongan dibongkar di TURUNAN, bukan sebagai sub-kueri berkorelasi,
     * dengan alasan yang sama: dimensinya berakhir di GROUP BY. ISJSON() wajib —
     * satu baris warisan yang JSON-nya rusak akan menggagalkan SELURUH ekspor,
     * dan kegagalan itu muncul sebagai galat basis data yang tak berhubungan
     * dengan laporan yang diminta.
     */
    public function baseQuery(): Builder
    {
        return DB::table('N_VISITOR_Tamu_Efektif as e')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu as h', 'h.No_Transaksi', '=', 'e.No_Transaksi')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as td', 'td.No_Transaksi', '=', 'e.No_Transaksi')
            ->leftJoin('N_HRIS_Api_Aplikasi as ap', 'ap.Id_Api_Aplikasi', '=', 'h.Id_Api_Aplikasi')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit as p', function ($join) {
                $join->on('p.No_Transaksi_Tamu', '=', 'e.No_Transaksi')
                    ->where('p.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as pd', 'pd.No_Transaksi', '=', 'p.No_Transaksi')
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'p.No_Transaksi')
            // Booking inspeksi TERAKHIR dan jumlah foto pemeriksaan: keduanya
            // turunan satu baris per permit, jadi grain satu kunjungan terjaga.
            ->leftJoin(DB::raw('('.VisitorPermitDataset::inspeksiTerakhirSql().') as ib'), function ($join) {
                $join->on('ib.No_Transaksi_Permit', '=', 'p.No_Transaksi')->where('ib.rn', '=', 1);
            })
            ->leftJoin(DB::raw('('.VisitorPermitDataset::fotoPemeriksaanSql().') as fd'), 'fd.No_Transaksi', '=', 'p.No_Transaksi')
            ->leftJoin(DB::raw('('.self::rombonganSql().') as rb'), 'rb.No_Transaksi', '=', 'e.No_Transaksi');
    }

    /** Manifes rombongan yang sudah dirangkum per transaksi. */
    private static function rombonganSql(): string
    {
        return "SELECT d.No_Transaksi,
                       STRING_AGG(CAST(j.nama AS nvarchar(200)), ', ') AS Daftar_Anggota,
                       COUNT(j.nama) AS Jumlah_Anggota
                  FROM N_EMI_VISITOR_Transaksi_Tamu_Detail d
                 CROSS APPLY OPENJSON(
                        CASE WHEN ISJSON(d.Daftar_Anggota_Json) = 1 THEN d.Daftar_Anggota_Json ELSE '[]' END
                 ) WITH (nama nvarchar(200) '$.nama') j
                 GROUP BY d.No_Transaksi";
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
    }

    public function opsiSaringan(): array
    {
        return [
            // Kosakata Status_Efektif yang BENAR-BENAR dipakai proyeksi.
            // Daftar lamanya memuat 'CHECKED_IN' dan 'CHECKED_OUT' — nilai yang
            // tidak pernah ditulis siapa pun, sehingga memilihnya memulangkan
            // laporan kosong yang terbaca sebagai "tidak ada datanya".
            'status' => $this->opsi(['AKTIF', 'DIBATALKAN', 'EXPIRED']),
            // Kode mentah (KELUAR_TAK_TERCATAT) tidak terbaca pengguna; label dari
            // sumber yang sama dengan layar monitoring.
            'siklus_kunjungan' => $this->opsi(StatusKunjunganTamu::TAMPIL, StatusKunjunganTamu::label(...)),
            'tipe_tamu' => $this->opsi(TipeTamu::SEMUA),
            'tamu_kategori' => $this->opsi([TipeTamu::KATEGORI_VISITOR, TipeTamu::KATEGORI_MITRA]),
            'unit_b2b' => $this->opsi(['PROCUREMENT', 'LOGISTIC']),
            'status_inspeksi' => $this->opsi(['SELESAI', 'TIDAK_HADIR']),
            'foto_pemeriksaan' => $this->opsi(['Lengkap', 'Sebagian', 'Tidak Ada']),
            'sumber_pendaftaran' => $this->opsi(['HCIS', 'MITRA']),
            'status_permit' => $this->opsi([
                'TANPA_PERMIT', 'MENUNGGU', 'AKTIF', 'DITOLAK', 'DICABUT', 'KEDALUWARSA',
            ]),
            'nama_mitra' => $this->opsi(
                DB::table('N_HRIS_Api_Aplikasi')->orderBy('Nama_Aplikasi')->pluck('Nama_Aplikasi')->all(),
            ),
            'divisi_pic' => $this->nilaiUnik('Divisi_PIC'),
            'jenis_kendaraan' => $this->nilaiUnik('Jenis_Kendaraan'),
        ];
    }

    private function nilaiUnik(string $kolom): array
    {
        return $this->opsi(
            DB::table('N_VISITOR_Tamu_Efektif')
                ->whereNotNull($kolom)
                ->where($kolom, '!=', '')
                ->distinct()->orderBy($kolom)->pluck($kolom)->all()
        );
    }

    private function opsi(array $nilai, ?callable $label = null): array
    {
        return array_values(array_map(
            fn ($item) => ['value' => (string) $item, 'label' => $label ? $label((string) $item) : (string) $item],
            $nilai
        ));
    }
}
