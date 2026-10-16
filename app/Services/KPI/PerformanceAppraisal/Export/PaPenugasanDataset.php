<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dataset penugasan penilai Performance Appraisal untuk Export Studio.
 *
 * Grain: satu baris = satu penugasan penilai (satu N_PA_Assignment).
 *
 * Identitas organisasi peserta dibaca dari kolom `..._Saat_Cycle` milik
 * N_PA_Participant, BUKAN dari master karyawan hidup — supaya laporan
 * historis tetap menampilkan posisi peserta pada saat siklus berjalan,
 * bukan posisinya hari ini. Karena alasan yang sama, dataset ini TIDAK
 * boleh join ke View_Divisi_Sub_Divisi: view itu berbaris satu per
 * pasangan divisi x sub-divisi, sehingga orang yang bernaung di dua
 * divisi akan tergandakan.
 */
class PaPenugasanDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'penugasan';
    }

    public function label(): string
    {
        return 'Penugasan Penilai Performance Appraisal';
    }

    public function grain(): string
    {
        return 'Satu baris = satu penugasan penilai.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('kode_cycle', 'Kode Siklus', 'c.Kode_Cycle', 'teks', 'siklus'),
            new Dimensi('nama_cycle', 'Nama Siklus', 'c.Nama_Cycle', 'teks', 'siklus'),
            new Dimensi('tahun', 'Tahun', 'c.Tahun', 'angka', 'siklus'),
            new Dimensi('kode_karyawan', 'Kode Karyawan', 'p.Kode_Karyawan', 'teks', 'peserta'),
            new Dimensi('nama_karyawan', 'Nama Karyawan', 'p.Nama_Karyawan_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('level', 'Level', 'p.Nama_Level_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('divisi', 'Divisi', 'p.Nama_Divisi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('sub_divisi', 'Sub Divisi', 'p.Nama_Sub_Divisi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('jabatan', 'Jabatan', 'p.Nama_Jabatan_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('lokasi', 'Lokasi', 'p.Lokasi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('kode_karyawan_penilai', 'Kode Karyawan Penilai', 'a.Kode_Karyawan_Penilai', 'teks', 'penilai'),
            new Dimensi('nama_penilai', 'Nama Penilai', 'a.Nama_Penilai_Saat_Cycle', 'teks', 'penilai'),
            new Dimensi('kategori_penilai', 'Kategori Penilai', 'rc.Nama_Kategori', 'teks', 'penilai'),
            new Dimensi('sumber_penugasan', 'Sumber Penugasan', 'a.Sumber_Penugasan', 'teks', 'penugasan'),
            new Dimensi('alasan_manual', 'Alasan Manual', 'a.Alasan_Manual', 'teks', 'penugasan'),
            new Dimensi('bobot_kustom', 'Bobot Kustom', 'a.Bobot_Kustom', 'teks', 'penugasan', false, 'ya_tidak'),
            new Dimensi('status_penugasan', 'Status Penugasan', 'a.Status_Penugasan', 'teks', 'penugasan'),
            new Dimensi('due_at', 'Tenggat', 'a.Due_At', 'tanggal', 'penugasan'),
            new Dimensi('started_at', 'Dimulai Pada', 'a.Started_At', 'tanggal', 'penugasan', false, 'tanggal_jam'),
            new Dimensi('finished_at', 'Selesai Pada', 'a.Finished_At', 'tanggal', 'penugasan', false, 'tanggal_jam'),
        ];
    }

    public function ukuran(): array
    {
        $sudah = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::SUDAH_DIKERJAKAN);
        $amanTerlambat = PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);

        return [
            new Ukuran('jumlah_penugasan', 'Jumlah Penugasan', 'COUNT(*)', 'angka'),
            new Ukuran('sudah_terisi', 'Sudah Terisi',
                "SUM(CASE WHEN a.Status_Penugasan IN ({$sudah}) THEN 1 ELSE 0 END)", 'angka'),
            new Ukuran('belum_terisi', 'Belum Terisi',
                "SUM(CASE WHEN a.Status_Penugasan NOT IN ({$sudah}) THEN 1 ELSE 0 END)", 'angka'),
            new Ukuran('terlambat', 'Terlambat',
                'SUM(CASE WHEN a.Due_At IS NOT NULL AND a.Due_At < CAST(GETDATE() AS date) '
                ."AND a.Status_Penugasan NOT IN ({$amanTerlambat}) THEN 1 ELSE 0 END)", 'angka'),
            new Ukuran('persen_kemajuan', 'Persen Kemajuan',
                "CAST(100.0 * SUM(CASE WHEN a.Status_Penugasan IN ({$sudah}) THEN 1 ELSE 0 END) "
                .'/ NULLIF(COUNT(*), 0) AS decimal(5,2))', 'persen', false, Ukuran::TOTAL_ABAIKAN),
        ];
    }

    public function saringan(): array
    {
        return [
            new Saringan('kode_cycle', 'Kode Siklus', 'c.Kode_Cycle', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('tahun', 'Tahun', 'c.Tahun', 'angka', ['sama_dengan', 'salah_satu']),
            new Saringan('divisi', 'Divisi', 'p.Nama_Divisi_Saat_Cycle', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('sub_divisi', 'Sub Divisi', 'p.Nama_Sub_Divisi_Saat_Cycle', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('level', 'Level', 'p.Nama_Level_Saat_Cycle', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('jabatan', 'Jabatan', 'p.Nama_Jabatan_Saat_Cycle', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('lokasi', 'Lokasi', 'p.Lokasi_Saat_Cycle', 'teks', ['sama_dengan', 'salah_satu', 'mengandung']),
            new Saringan('kategori_penilai', 'Kategori Penilai', 'rc.Nama_Kategori', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('sumber_penugasan', 'Sumber Penugasan', 'a.Sumber_Penugasan', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('status_penugasan', 'Status Penugasan', 'a.Status_Penugasan', 'teks', ['sama_dengan', 'salah_satu']),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoin('N_PA_Rater_Category as rc', 'rc.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.Flag_Aktif', '=', 'Y');
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
    }

    public function opsiSaringan(): array
    {
        return [
            'status_penugasan' => $this->opsi(PaStatusPenugasan::SEMUA),
            'sumber_penugasan' => $this->opsi($this->querySumberPenugasanUnik()->pluck('Sumber_Penugasan')->all()),
            'kategori_penilai' => $this->opsi($this->queryKategoriPenilaiUnik()->pluck('Nama_Kategori')->all()),
            'divisi' => $this->opsi($this->queryDivisiUnik()->pluck('Nama_Divisi_Saat_Cycle')->all()),
            'sub_divisi' => $this->opsi($this->queryOpsiSnapshot('Nama_Sub_Divisi_Saat_Cycle')->pluck('Nama_Sub_Divisi_Saat_Cycle')->all()),
            'level' => $this->opsi($this->queryOpsiSnapshot('Nama_Level_Saat_Cycle')->pluck('Nama_Level_Saat_Cycle')->all()),
            'jabatan' => $this->opsi($this->queryOpsiSnapshot('Nama_Jabatan_Saat_Cycle')->pluck('Nama_Jabatan_Saat_Cycle')->all()),
            'lokasi' => $this->opsi($this->queryOpsiSnapshot('Lokasi_Saat_Cycle')->pluck('Lokasi_Saat_Cycle')->all()),
            'kode_cycle' => $this->opsi($this->queryKodeCycleUnik()->pluck('Kode_Cycle')->all()),
        ];
    }

    /**
     * Dipisah jadi method sendiri (mengembalikan Builder, belum dieksekusi) supaya
     * saringan Flag_Aktif bisa diperiksa lewat toSql() tanpa menyentuh basis data.
     *
     * Flag_Aktif WAJIB di sini: N_PA_Assignment meninggalkan baris lama
     * ber-Flag_Aktif='T' setiap kali penugasan dibentuk ulang. Tanpa saringan
     * ini, sumbernya tetap muncul di dropdown lalu memilihnya menghasilkan nol
     * baris tanpa satu pun galat.
     */
    public function querySumberPenugasanUnik(): Builder
    {
        return DB::table('N_PA_Assignment')
            ->where('Flag_Aktif', '=', 'Y')
            ->whereNotNull('Sumber_Penugasan')
            ->where('Sumber_Penugasan', '!=', '')
            ->distinct()->orderBy('Sumber_Penugasan');
    }

    /**
     * TIDAK menyaring Flag_Aktif — dan itu sengaja, bukan lupa.
     *
     * baseQuery() meng-leftJoin N_PA_Rater_Category TANPA syarat Flag_Aktif, jadi
     * kategori yang sudah dinonaktifkan tetap bisa keluar di hasil selama masih ada
     * penugasan aktif (a.Flag_Aktif='Y') yang menunjuknya. Kalau opsi ini disaring
     * Flag_Aktif='Y' sementara kuerinya tidak, opsinya jadi LEBIH SEMPIT daripada
     * data yang sebenarnya bisa muncul — kategori yang barisnya ada di hasil jadi tak
     * bisa dipilih sama sekali di dropdown.
     *
     * PERINGATAN: bila kelak diputuskan kategori nonaktif harus dibuang dari laporan,
     * baseQuery() DAN method ini wajib diubah BERSAMAAN.
     */
    public function queryKategoriPenilaiUnik(): Builder
    {
        return DB::table('N_PA_Rater_Category')
            ->whereNotNull('Nama_Kategori')
            ->where('Nama_Kategori', '!=', '')
            ->distinct()->orderBy('Nama_Kategori');
    }

    public function queryDivisiUnik(): Builder
    {
        return DB::table('N_PA_Participant')
            ->where('Flag_Aktif', '=', 'Y')
            ->whereNotNull('Nama_Divisi_Saat_Cycle')
            ->where('Nama_Divisi_Saat_Cycle', '!=', '')
            ->distinct()->orderBy('Nama_Divisi_Saat_Cycle');
    }

    /**
     * Opsi umum untuk kolom identitas snapshot (`..._Saat_Cycle`) milik
     * N_PA_Participant: sub_divisi, level, jabatan, lokasi.
     *
     * Sama seperti queryDivisiUnik(): dibaca dari snapshot saat siklus (BUKAN
     * master karyawan hidup) dan disaring Flag_Aktif='Y' -- pembentukan ulang
     * penugasan meninggalkan baris participant lama ber-Flag_Aktif='T' yang
     * nilai snapshotnya tetap tersimpan. Tanpa saringan ini, nilai milik baris
     * mati tetap muncul di dropdown lalu memilihnya menghasilkan nol baris
     * tanpa satu pun galat.
     *
     * Mengembalikan Builder BELUM dieksekusi supaya saringannya bisa diperiksa
     * lewat toSql() tanpa menyentuh basis data. select() dibatasi ke $kolom
     * sehingga ->pluck()->all() langsung mengembalikan daftar nilai, bukan objek.
     */
    public function queryOpsiSnapshot(string $kolom): Builder
    {
        return DB::table('N_PA_Participant')
            ->where('Flag_Aktif', '=', 'Y')
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->distinct()->orderBy($kolom)
            ->select($kolom);
    }

    /**
     * TIDAK menyaring Flag_Aktif — dan itu sengaja, bukan lupa. Sama seperti
     * PaHasilDataset::queryKodeCycleUnik(): baseQuery() meng-leftJoin N_PA_Cycle
     * TANPA syarat Flag_Aktif, jadi baris hasil milik siklus yang sudah
     * dinonaktifkan tetap bisa keluar. Menyaring Flag_Aktif di sini saja akan
     * membuat opsi lebih sempit daripada data yang sebenarnya bisa muncul.
     *
     * PERINGATAN: bila kelak diputuskan siklus nonaktif harus dibuang dari laporan,
     * baseQuery() DAN method ini (dan PaHasilDataset::queryKodeCycleUnik()) wajib
     * diubah BERSAMAAN.
     */
    public function queryKodeCycleUnik(): Builder
    {
        return DB::table('N_PA_Cycle')
            ->whereNotNull('Kode_Cycle')
            ->where('Kode_Cycle', '!=', '')
            ->distinct()->orderBy('Kode_Cycle');
    }

    private function opsi(array $nilai): array
    {
        return array_values(array_map(
            fn ($item) => ['value' => (string) $item, 'label' => (string) $item],
            $nilai
        ));
    }
}
