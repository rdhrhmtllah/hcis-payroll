<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Dataset permit kendaraan yang telah efektif setelah approval/auto-approval. */
class VisitorPermitDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'permit';
    }

    public function label(): string
    {
        return 'Permit Kendaraan Efektif';
    }

    public function grain(): string
    {
        return 'Satu baris = satu permit kendaraan yang telah efektif.';
    }

    /**
     * Akhir masa berlaku yang JUJUR: jendela jam bila ada, tanggal bila tidak.
     *
     * Sejak verifikator menetapkan jendelanya sendiri, "kedaluwarsa hari ini"
     * bisa berarti pukul delapan pagi. Menghitung sisa dari kolom tanggal saja
     * membuat izin yang sudah mati sejak pagi tetap terbaca "sisa 0 hari" —
     * sama seperti yang masih hidup sampai tengah malam.
     */
    private const AKHIR_SQL = 'COALESCE(e.Waktu_Berakhir, DATEADD(day, 1, CAST(e.Tanggal_Kadaluarsa AS datetime2(0))))';

    /** Tiga foto pemeriksaan yang diwajibkan sebelum permit tamu disetujui. */
    public const JENIS_FOTO_PEMERIKSAAN = ['FOTO_KENDARAAN', 'STNK', 'SIM'];

    /**
     * Booking inspeksi TERAKHIR per permit (satu baris per permit — grain
     * dataset ini). Pemindahan jadwal meninggalkan baris DIPINDAH lama; yang
     * relevan untuk laporan adalah jadwal yang berlaku terakhir.
     */
    public static function inspeksiTerakhirSql(): string
    {
        return 'SELECT No_Transaksi_Permit, Snapshot_Nama_Pos, Waktu_Mulai, Status, Diinspeksi_Oleh, Diinspeksi_At,'
            .' ROW_NUMBER() OVER (PARTITION BY No_Transaksi_Permit ORDER BY Id_Inspeksi_Booking DESC) AS rn'
            .' FROM N_VISITOR_Inspeksi_Booking';
    }

    /**
     * Per permit: jumlah jenis foto pemeriksaan yang benar-benar terunggah, dan
     * ID dokumen TERBARU tiap jenis (id_foto_kendaraan, id_stnk, id_sim) untuk
     * kolom tautan berkas. Sudah GROUP BY, jadi satu baris per permit.
     */
    public static function fotoPemeriksaanSql(): string
    {
        $id = implode(', ', array_map(
            fn ($jenis) => "MAX(CASE WHEN Jenis_Dokumen = '{$jenis}' THEN Id_Permit_Dokumen END) AS id_".strtolower($jenis),
            self::JENIS_FOTO_PEMERIKSAAN,
        ));

        return "SELECT No_Transaksi, COUNT(DISTINCT Jenis_Dokumen) AS n, {$id} FROM N_EMI_VISITOR_Transaksi_Permit_Dokumen"
            ." WHERE Jenis_Dokumen IN ('".implode("','", self::JENIS_FOTO_PEMERIKSAAN)."')"
            ." AND LTRIM(RTRIM(ISNULL(Path_File_URL, ''))) <> '' GROUP BY No_Transaksi";
    }

    /**
     * Kolom tautan foto pemeriksaan (bertanda tangan, 1 hari, lewat domain
     * HCIS — TautanBerkasVisitor). Sensitif: STNK dan SIM dokumen pribadi.
     *
     * @return Dimensi[]
     */
    public static function dimensiTautanFoto(string $alias, string $awalanKunci = 'tautan', string $akhiranLabel = ''): array
    {
        $label = ['FOTO_KENDARAAN' => 'Foto Kendaraan', 'STNK' => 'STNK', 'SIM' => 'SIM'];

        return array_map(
            fn ($jenis) => new Dimensi(
                $awalanKunci.'_'.strtolower($jenis),
                'Tautan '.$label[$jenis].$akhiranLabel,
                "CAST({$alias}.id_".strtolower($jenis).' AS varchar(20))',
                'teks',
                'inspeksi',
                true,
                'tautan:visitor_dokumen_permit',
            ),
            self::JENIS_FOTO_PEMERIKSAAN,
        );
    }

    /** 'Lengkap' / 'Sebagian' / 'Tidak Ada' dari alias turunan fotoPemeriksaanSql(). */
    public static function statusFotoSql(string $alias): string
    {
        $jumlah = count(self::JENIS_FOTO_PEMERIKSAAN);

        return "CASE WHEN ISNULL({$alias}.n, 0) >= {$jumlah} THEN 'Lengkap'"
            ." WHEN ISNULL({$alias}.n, 0) = 0 THEN 'Tidak Ada' ELSE 'Sebagian' END";
    }

    /** Disetujui (inspeksi SELESAI) sebelum jam slotnya — boleh sejak 29 Sep 2026. */
    public static function sebelumJadwalSql(string $alias): string
    {
        return "CASE WHEN {$alias}.No_Transaksi_Permit IS NULL THEN 'Tanpa Jadwal'"
            ." WHEN {$alias}.Status = 'SELESAI' AND {$alias}.Diinspeksi_At < {$alias}.Waktu_Mulai THEN 'Ya'"
            ." ELSE 'Tidak' END";
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('no_transaksi', 'No. Transaksi', 'e.No_Transaksi', 'teks', 'identitas'),
            new Dimensi('kode_pemohon', 'Kode Karyawan', 'e.Kode_Karyawan_Pemohon', 'teks', 'pemohon'),
            new Dimensi('nama_pemohon', 'Nama Pemohon', 'e.Nama_Pemohon', 'teks', 'pemohon'),
            // PEMEGANG izin — yang benar-benar lewat gerbang.
            //
            // Kolomnya sudah ada di proyeksi sejak permit tamu lahir, tetapi
            // tidak pernah dijadikan dimensi: laporan izin gerbang selama ini
            // hanya bisa menyebut PIC-nya, yaitu orang yang justru tidak datang.
            new Dimensi(
                'nama_pemegang',
                'Nama Pemegang Izin',
                "COALESCE(NULLIF(LTRIM(RTRIM(e.Nama_Pemegang)), ''), e.Nama_Pemohon, '-')",
                'teks',
                'pemohon',
            ),
            new Dimensi('instansi_pemegang', 'Instansi Pemegang', 'e.Instansi_Pemegang', 'teks', 'pemohon'),
            new Dimensi('nik_pemohon', 'NIK Pemohon', 'e.NIK', 'teks', 'pemohon', true),
            // Nomor yang dipakai SHE untuk menghubungi pemohon yang terlambat
            // inspeksi (tombol Hubungi, 29 Sep 2026). Sensitif, seperti NIK.
            new Dimensi('no_hp_pemohon', 'No. HP Pemohon', 'e.No_HP_Pemohon', 'teks', 'pemohon', true),
            // Nomor kartu InBio yang dibaca palang (DDL-2026-153).
            new Dimensi('nfc_inbio', 'NFC InBio', 'e.NFC_Inbio', 'teks', 'pemohon'),
            new Dimensi('departemen', 'Departemen', 'e.Departemen', 'teks', 'pemohon'),
            new Dimensi('jabatan', 'Jabatan', 'e.Jabatan', 'teks', 'pemohon'),
            new Dimensi('keterangan_pengajuan', 'Keterangan Pengajuan', 'e.Keterangan_Pengajuan', 'teks', 'permit'),
            new Dimensi('no_plat', 'Nomor Plat', 'e.No_Plat_Kendaraan', 'teks', 'kendaraan'),
            new Dimensi('kategori_permit', 'Kategori Permit', 'e.Nama_Kategori_Permit', 'teks', 'permit'),
            new Dimensi('deskripsi_akses', 'Deskripsi Akses', 'e.Deskripsi_Akses', 'teks', 'permit'),
            new Dimensi('tipe_kendaraan', 'Tipe Kendaraan', 'e.Nama_Vehicle_Type', 'teks', 'kendaraan'),
            new Dimensi('model_kendaraan', 'Model Kendaraan', 'e.Nama_Model_Kendaraan', 'teks', 'kendaraan'),
            new Dimensi('warna_kendaraan', 'Warna Kendaraan', 'e.Warna_Kendaraan', 'teks', 'kendaraan'),
            new Dimensi('tahun_kendaraan', 'Tahun Kendaraan', 'e.Tahun_Kendaraan', 'angka', 'kendaraan'),
            new Dimensi('sumber_permit', 'Sumber Permit', "ISNULL(e.Sumber_Permit, 'KARYAWAN')", 'teks', 'permit'),
            // Asal-usul: kunjungan mana yang melahirkan izin ini, dan mitra mana
            // yang mengajukannya. Tanpa ini laporan izin tamu tidak bisa
            // ditelusuri balik ke kunjungannya sama sekali.
            new Dimensi('no_transaksi_tamu', 'No. Kunjungan Asal', 't.No_Transaksi_Tamu', 'teks', 'permit'),
            new Dimensi('tanggal_kunjungan_asal', 'Tanggal Kunjungan Asal', 'th.Tanggal_Kunjungan', 'tanggal', 'permit', false, 'tanggal'),
            // Jam yang diajukan tamunya — pembanding jendela permit yang
            // ditetapkan verifikator (bawaan: sampai 23:59 hari terakhir).
            new Dimensi('estimasi_masuk_tamu', 'Estimasi Masuk Tamu', 'td.Waktu_Mulai_Kunjungan', 'tanggal', 'permit', false, 'tanggal_jam'),
            new Dimensi('estimasi_keluar_tamu', 'Estimasi Keluar Tamu', 'td.Waktu_Selesai_Kunjungan', 'tanggal', 'permit', false, 'tanggal_jam'),
            new Dimensi('referensi_mitra', 'Referensi Mitra', 't.Referensi_Mitra', 'teks', 'permit'),
            new Dimensi('nama_mitra', 'Aplikasi Mitra', 'ap.Nama_Aplikasi', 'teks', 'permit'),
            new Dimensi('nama_durasi', 'Durasi Permit', 'e.Nama_Durasi', 'teks', 'masa_berlaku'),
            new Dimensi('waktu_mulai_berlaku', 'Mulai Berlaku (Jam)', 'e.Waktu_Mulai_Berlaku', 'tanggal', 'masa_berlaku', false, 'tanggal_jam'),
            new Dimensi('waktu_berakhir', 'Berakhir (Jam)', 'e.Waktu_Berakhir', 'tanggal', 'masa_berlaku', false, 'tanggal_jam'),
            new Dimensi('tanggal_mulai', 'Tanggal Mulai Berlaku', 'e.Tanggal_Mulai_Berlaku', 'tanggal', 'masa_berlaku', false, 'tanggal'),
            new Dimensi('tanggal_kadaluarsa', 'Tanggal Kadaluarsa', 'e.Tanggal_Kadaluarsa', 'tanggal', 'masa_berlaku', false, 'tanggal'),
            // Dihitung dari AKHIR yang sebenarnya, bukan dari kolom tanggal.
            new Dimensi('sisa_hari', 'Sisa Hari', 'DATEDIFF(day, GETDATE(), '.self::AKHIR_SQL.')', 'angka', 'masa_berlaku'),
            new Dimensi('sisa_jam', 'Sisa Jam', 'DATEDIFF(hour, GETDATE(), '.self::AKHIR_SQL.')', 'angka', 'masa_berlaku'),
            // Membedakan izin berjendela jam (tamu/mitra) dari izin bulanan
            // karyawan. Keduanya bercampur di satu laporan dan aturan masa
            // berlakunya sama sekali berbeda.
            new Dimensi(
                'berjendela_jam',
                'Pakai Jendela Jam',
                "CASE WHEN e.Waktu_Berakhir IS NULL THEN 'Tidak' ELSE 'Ya' END",
                'teks',
                'masa_berlaku',
            ),
            // Inspeksi kendaraan: jadwal terakhir dan hasilnya.
            new Dimensi('pos_inspeksi', 'Pos Inspeksi', 'ib.Snapshot_Nama_Pos', 'teks', 'inspeksi'),
            new Dimensi('jadwal_inspeksi', 'Jadwal Inspeksi', 'ib.Waktu_Mulai', 'tanggal', 'inspeksi', false, 'tanggal_jam'),
            new Dimensi('status_inspeksi', 'Status Inspeksi', 'ib.Status', 'teks', 'inspeksi'),
            new Dimensi('diinspeksi_oleh', 'Diinspeksi Oleh', 'ib.Diinspeksi_Oleh', 'teks', 'inspeksi'),
            new Dimensi('diinspeksi_pada', 'Diinspeksi Pada', 'ib.Diinspeksi_At', 'tanggal', 'inspeksi', false, 'tanggal_jam'),
            new Dimensi('disetujui_sebelum_jadwal', 'Disetujui Sebelum Jadwal', self::sebelumJadwalSql('ib'), 'teks', 'inspeksi'),
            new Dimensi('foto_pemeriksaan', 'Foto Pemeriksaan', self::statusFotoSql('fd'), 'teks', 'inspeksi'),
            new Dimensi('jumlah_foto_pemeriksaan', 'Jumlah Foto Pemeriksaan', 'ISNULL(fd.n, 0)', 'angka', 'inspeksi'),
            ...self::dimensiTautanFoto('fd'),

            new Dimensi('status', 'Status Efektif', 'e.Status_Efektif', 'teks', 'lifecycle'),
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
            new Ukuran('jumlah_permit', 'Jumlah Permit', 'COUNT(*)', 'angka'),
            // Angka yang menjawab pertanyaan operasional, bukan sekadar
            // menghitung baris. Status dibaca dari Status_Efektif DAN jendela
            // waktunya: pencabutan menulis ke Status_Efektif, sedangkan
            // kedaluwarsa bisa terjadi sebelum penjadwal sempat berjalan.
            new Ukuran(
                'permit_berlaku',
                'Masih Berlaku',
                "SUM(CASE WHEN e.Status_Efektif = 'AKTIF' AND ".self::AKHIR_SQL.' >= GETDATE() THEN 1 ELSE 0 END)',
                'angka',
            ),
            new Ukuran(
                'permit_kedaluwarsa',
                'Kedaluwarsa',
                "SUM(CASE WHEN e.Status_Efektif <> 'DIBATALKAN' AND ".self::AKHIR_SQL.' < GETDATE() THEN 1 ELSE 0 END)',
                'angka',
            ),
            new Ukuran(
                'permit_dicabut',
                'Dicabut',
                "SUM(CASE WHEN e.Status_Efektif = 'DIBATALKAN' THEN 1 ELSE 0 END)",
                'angka',
            ),
            new Ukuran(
                'permit_tamu',
                'Izin Pemegang Tamu',
                "SUM(CASE WHEN ISNULL(e.Sumber_Permit, 'KARYAWAN') <> 'KARYAWAN' THEN 1 ELSE 0 END)",
                'angka',
            ),
        ];
    }

    public function saringan(): array
    {
        return [
            new Saringan('tanggal_mulai', 'Tanggal Mulai Berlaku', 'e.Tanggal_Mulai_Berlaku', 'tanggal', ['antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama']),
            new Saringan('tanggal_kadaluarsa', 'Tanggal Kadaluarsa', 'e.Tanggal_Kadaluarsa', 'tanggal', ['antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama']),
            new Saringan('status', 'Status Efektif', 'e.Status_Efektif', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('kategori_permit', 'Kategori Permit', 'e.Nama_Kategori_Permit', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('departemen', 'Departemen', 'e.Departemen', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('tipe_kendaraan', 'Tipe Kendaraan', 'e.Nama_Vehicle_Type', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            // Tanpa saringan ini, setiap laporan permit bercampur antara permit
            // kendaraan karyawan dan permit akses tamu — dan yang kedua jauh
            // lebih banyak, satu per tamu yang didaftarkan.
            new Saringan('sumber_permit', 'Sumber Permit', "ISNULL(e.Sumber_Permit, 'KARYAWAN')", 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('nama_pemegang', 'Nama Pemegang Izin', 'e.Nama_Pemegang', 'teks', ['sama_dengan', 'mengandung']),
            new Saringan('instansi_pemegang', 'Instansi Pemegang', 'e.Instansi_Pemegang', 'teks', ['sama_dengan', 'mengandung']),
            new Saringan('nama_mitra', 'Aplikasi Mitra', 'ap.Nama_Aplikasi', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('status_inspeksi', 'Status Inspeksi', 'ib.Status', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('foto_pemeriksaan', 'Foto Pemeriksaan', self::statusFotoSql('fd'), 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('disetujui_sebelum_jadwal', 'Disetujui Sebelum Jadwal', self::sebelumJadwalSql('ib'), 'teks', ['sama_dengan']),
            new Saringan(
                'berjendela_jam',
                'Pakai Jendela Jam',
                "CASE WHEN e.Waktu_Berakhir IS NULL THEN 'Tidak' ELSE 'Ya' END",
                'teks',
                ['sama_dengan'],
            ),
        ];
    }

    /**
     * Proyeksi gerbang tetap sumbernya, ditambah asal-usul pengajuannya.
     *
     * Tidak satu pun sambungan boleh melipatgandakan baris — grain dataset ini
     * satu permit, dan baris ganda menggandakan setiap ukurannya tanpa gejala:
     *
     *  - t  : satu baris per No_Transaksi permit.
     *  - th : satu baris per nomor kunjungan; NULL untuk permit karyawan.
     *  - ap : satu baris per Id_Api_Aplikasi.
     *  - td : satu baris detail per nomor kunjungan (sama dengan dataset tamu).
     *  - ib : booking inspeksi TERAKHIR saja (rn = 1).
     *  - fd : turunan yang sudah GROUP BY No_Transaksi.
     */
    public function baseQuery(): Builder
    {
        return DB::table('N_VISITOR_Permit_Efektif as e')
            // Baris akses sementara (DDL-2026-132) bukan permit dan tidak boleh
            // ikut terekspor: ia lahir saat pemohon memesan slot inspeksi, jauh
            // sebelum ada keputusan apa pun untuk dilaporkan.
            ->where('e.Jenis_Akses', VisitorEfektifService::JENIS_PERMIT)
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit as t', 't.No_Transaksi', '=', 'e.No_Transaksi')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu as th', 'th.No_Transaksi', '=', 't.No_Transaksi_Tamu')
            ->leftJoin('N_HRIS_Api_Aplikasi as ap', 'ap.Id_Api_Aplikasi', '=', 't.Id_Api_Aplikasi')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as td', 'td.No_Transaksi', '=', 't.No_Transaksi_Tamu')
            ->leftJoin(DB::raw('('.self::inspeksiTerakhirSql().') as ib'), function ($join) {
                $join->on('ib.No_Transaksi_Permit', '=', 'e.No_Transaksi')->where('ib.rn', '=', 1);
            })
            ->leftJoin(DB::raw('('.self::fotoPemeriksaanSql().') as fd'), 'fd.No_Transaksi', '=', 'e.No_Transaksi');
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
    }

    public function opsiSaringan(): array
    {
        return [
            'status' => $this->opsi(['AKTIF', 'DIBATALKAN', 'EXPIRED']),
            'kategori_permit' => $this->nilaiUnik('Nama_Kategori_Permit'),
            // 'API' WAJIB ada di sini.
            //
            // syncPermitEfektif() sudah menulisnya untuk permit yang diajukan
            // aplikasi mitra, tetapi di development belum ada satu pun yang
            // terbit — permit mitra baru bisa disetujui sejak masa berlakunya
            // ditetapkan verifikator. Daftar yang menunggu datanya muncul dulu
            // akan menyembunyikan seluruh permit mitra pada hari pertama ia ada,
            // dan tidak ada yang menghubungkan keduanya.
            'sumber_permit' => $this->opsi(['KARYAWAN', 'TAMU', 'API']),
            'berjendela_jam' => $this->opsi(['Ya', 'Tidak']),
            'status_inspeksi' => $this->opsi(['TERJADWAL', 'SELESAI', 'TIDAK_HADIR', 'DIBATALKAN', 'DIPINDAH', 'DIPINDAH_PETUGAS', 'KEDALUWARSA']),
            'foto_pemeriksaan' => $this->opsi(['Lengkap', 'Sebagian', 'Tidak Ada']),
            'disetujui_sebelum_jadwal' => $this->opsi(['Ya', 'Tidak', 'Tanpa Jadwal']),
            'nama_mitra' => $this->opsi(
                DB::table('N_HRIS_Api_Aplikasi')->orderBy('Nama_Aplikasi')->pluck('Nama_Aplikasi')->all(),
            ),
            'departemen' => $this->nilaiUnik('Departemen'),
            'tipe_kendaraan' => $this->nilaiUnik('Nama_Vehicle_Type'),
        ];
    }

    private function nilaiUnik(string $kolom): array
    {
        return $this->opsi(
            DB::table('N_VISITOR_Permit_Efektif')
                ->whereNotNull($kolom)
                ->where($kolom, '!=', '')
                ->distinct()->orderBy($kolom)->pluck($kolom)->all()
        );
    }

    private function opsi(array $nilai): array
    {
        return array_values(array_map(
            fn ($item) => ['value' => (string) $item, 'label' => (string) $item],
            $nilai
        ));
    }
}
