<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dataset kompetensi Performance Appraisal untuk Export Studio.
 *
 * Grain: satu baris = satu peserta × satu kompetensi, memakai HANYA baris
 * agregat N_PA_Result_Competency (PA_Rater_Category_Id IS NULL). Baris
 * ber-kategori ("kompetensi menurut bawahan", dll.) sengaja TIDAK ikut:
 * kalau ikut, satu peserta muncul beberapa kali per kompetensi dan rata-rata
 * lintas baris menjadi angka tanpa arti.
 *
 * Identitas organisasi peserta dibaca dari kolom `..._Saat_Cycle` milik
 * N_PA_Participant, BUKAN dari master karyawan hidup — laporan historis wajib
 * menampilkan posisi peserta saat siklus berjalan. Karena alasan yang sama,
 * dataset ini TIDAK join ke View_Divisi_Sub_Divisi (berbaris satu per pasangan
 * divisi x sub-divisi).
 */
class PaKompetensiDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'kompetensi';
    }

    public function label(): string
    {
        return 'Kompetensi Performance Appraisal';
    }

    public function grain(): string
    {
        return 'Satu baris = satu peserta muncul berulang untuk setiap kompetensi yang dinilai.';
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
            new Dimensi('jenis_kompetensi', 'Jenis Kompetensi', 'kt.Nama_Type', 'teks', 'kompetensi'),
            new Dimensi('kode_kompetensi', 'Kode Kompetensi', 'k.Kode_Competency', 'teks', 'kompetensi'),
            new Dimensi('nama_kompetensi', 'Nama Kompetensi', 'k.Nama_Competency', 'teks', 'kompetensi'),
            // Nilai MENTAH milik baris peserta×kompetensi ini, BUKAN rata-rata
            // lintas baris. Kunci sengaja *_baris supaya mode rinci bisa membaca
            // nilai per baris tanpa menimpa metadata agregat rata_rata_nilai.
            new Dimensi('nilai_baris', 'Nilai Kompetensi (Baris)', 'rc.Nilai', 'angka', 'kompetensi', false, 'angka'),
            new Dimensi('jumlah_penilai_baris', 'Jumlah Penilai (Baris)', 'rc.Jumlah_Penilai', 'angka', 'kompetensi'),
        ];
    }

    public function ukuran(): array
    {
        return [
            // Satu baris = satu pasangan peserta×kompetensi (baris agregat).
            new Ukuran('jumlah_pasang', 'Jumlah Pasangan', 'COUNT(*)', 'angka'),
            // COUNT(DISTINCT), bukan COUNT(*): satu peserta muncul di banyak
            // kompetensi. Dijumlahkan lintas baris grand total ia akan menggandakan
            // orang yang sama, jadi totalisasinya diabaikan.
            new Ukuran('jumlah_peserta', 'Jumlah Peserta Dinilai', 'COUNT(DISTINCT p.Id_PA_Participant)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            // AVG atas decimal berperilaku beda dari float; CAST wajib di sini.
            // Rata-rata dari rata-rata bukan rata-rata, jadi total diabaikan.
            new Ukuran('rata_rata_nilai', 'Rata-rata Nilai', 'AVG(CAST(rc.Nilai AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jumlah_penilai', 'Jumlah Penilai', 'SUM(COALESCE(rc.Jumlah_Penilai, 0))', 'angka'),
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
            new Saringan('jenis_kompetensi', 'Jenis Kompetensi', 'kt.Nama_Type', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('kode_kompetensi', 'Kode Kompetensi', 'k.Kode_Competency', 'teks', ['sama_dengan', 'salah_satu']),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_PA_Result_Competency as rc')
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.Id_PA_Result', '=', 'rc.PA_Result_Id')
                    ->where('r.Flag_Aktif', '=', 'Y');
            })
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y')
                    // Peserta DIKECUALIKAN bukan bagian hasil siklus; dulu ikut terekspor.
                    ->where('p.Status_Peserta', '=', 'DIIKUTKAN');
            })
            ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->join('N_PA_Competency as k', function ($j) {
                $j->on('k.Id_PA_Competency', '=', 'rc.PA_Competency_Id')
                    ->where('k.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_PA_Competency_Type as kt', function ($j) {
                $j->on('kt.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
                    ->where('kt.Flag_Aktif', '=', 'Y');
            })
            ->where('rc.Flag_Aktif', '=', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id');
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
    }

    public function opsiSaringan(): array
    {
        return [
            'kode_cycle' => $this->opsi($this->queryKodeCycleUnik()->pluck('Kode_Cycle')->all()),
            'divisi' => $this->opsi($this->queryDivisiUnik()->pluck('Nama_Divisi_Saat_Cycle')->all()),
            'sub_divisi' => $this->opsi($this->queryOpsiSnapshot('Nama_Sub_Divisi_Saat_Cycle')->pluck('Nama_Sub_Divisi_Saat_Cycle')->all()),
            'level' => $this->opsi($this->queryOpsiSnapshot('Nama_Level_Saat_Cycle')->pluck('Nama_Level_Saat_Cycle')->all()),
            'jabatan' => $this->opsi($this->queryOpsiSnapshot('Nama_Jabatan_Saat_Cycle')->pluck('Nama_Jabatan_Saat_Cycle')->all()),
            'lokasi' => $this->opsi($this->queryOpsiSnapshot('Lokasi_Saat_Cycle')->pluck('Lokasi_Saat_Cycle')->all()),
            'jenis_kompetensi' => $this->opsi($this->queryJenisUnik()->pluck('Nama_Type')->all()),
            'kode_kompetensi' => $this->opsi($this->queryKodeKompetensiUnik()->pluck('Kode_Competency')->all()),
        ];
    }

    /**
     * Basis bersama untuk opsi yang disaring Flag_Aktif + agregat global.
     *
     * Mengembalikan Builder BELUM dieksekusi supaya saringannya bisa diperiksa
     * lewat toSql() tanpa menyentuh basis data. Seluruh opsi WAJIB lahir dari
     * populasi yang sama dengan baseQuery(): baris agregat
     * (PA_Rater_Category_Id IS NULL) ber-Flag_Aktif='Y'. Tanpa itu, dropdown
     * menawarkan nilai yang memilihnya menghasilkan nol baris tanpa satu pun
     * galat — kegagalan paling sulit dilacak karena tampak seperti data memang
     * kosong.
     */
    private function queryPopulasi(): Builder
    {
        return DB::table('N_PA_Result_Competency as rc')
            ->where('rc.Flag_Aktif', '=', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id');
    }

    public function queryDivisiUnik(): Builder
    {
        return $this->queryPopulasi()
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.Id_PA_Result', '=', 'rc.PA_Result_Id')
                    ->where('r.Flag_Aktif', '=', 'Y');
            })
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y')
                    // Peserta DIKECUALIKAN bukan bagian hasil siklus; dulu ikut terekspor.
                    ->where('p.Status_Peserta', '=', 'DIIKUTKAN');
            })
            ->whereNotNull('p.Nama_Divisi_Saat_Cycle')
            ->where('p.Nama_Divisi_Saat_Cycle', '!=', '')
            ->distinct()->orderBy('p.Nama_Divisi_Saat_Cycle')
            ->select('p.Nama_Divisi_Saat_Cycle');
    }

    /**
     * Opsi umum untuk kolom identitas snapshot peserta pada populasi kompetensi
     * (sub_divisi, level, jabatan, lokasi).
     *
     * Sama seperti queryDivisiUnik(): lahir dari populasi yang SAMA dengan
     * baseQuery() -- baris agregat (PA_Rater_Category_Id IS NULL) ber-Flag_Aktif='Y'
     * -- lalu menelusuri result dan participant. Dibaca dari snapshot saat siklus,
     * BUKAN master karyawan hidup, dan menyaring Flag_Aktif di setiap lapisan yang
     * sama dengan baseQuery(). Tanpa itu, dropdown menawarkan nilai milik baris mati
     * atau butiran per-kategori yang memilihnya menghasilkan nol baris tanpa galat.
     *
     * select() dibatasi ke $kolom sehingga ->pluck()->all() mengembalikan daftar
     * nilai, bukan objek.
     */
    public function queryOpsiSnapshot(string $kolom): Builder
    {
        return $this->queryPopulasi()
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.Id_PA_Result', '=', 'rc.PA_Result_Id')
                    ->where('r.Flag_Aktif', '=', 'Y');
            })
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y')
                    // Peserta DIKECUALIKAN bukan bagian hasil siklus; dulu ikut terekspor.
                    ->where('p.Status_Peserta', '=', 'DIIKUTKAN');
            })
            ->whereNotNull("p.{$kolom}")
            ->where("p.{$kolom}", '!=', '')
            ->distinct()->orderBy("p.{$kolom}")
            ->select("p.{$kolom}");
    }

    public function queryJenisUnik(): Builder
    {
        return $this->queryPopulasi()
            ->join('N_PA_Competency as k', function ($j) {
                $j->on('k.Id_PA_Competency', '=', 'rc.PA_Competency_Id')
                    ->where('k.Flag_Aktif', '=', 'Y');
            })
            ->join('N_PA_Competency_Type as kt', function ($j) {
                $j->on('kt.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
                    ->where('kt.Flag_Aktif', '=', 'Y');
            })
            ->whereNotNull('kt.Nama_Type')
            ->where('kt.Nama_Type', '!=', '')
            ->distinct()->orderBy('kt.Nama_Type')
            ->select('kt.Nama_Type');
    }

    public function queryKodeKompetensiUnik(): Builder
    {
        return $this->queryPopulasi()
            ->join('N_PA_Competency as k', function ($j) {
                $j->on('k.Id_PA_Competency', '=', 'rc.PA_Competency_Id')
                    ->where('k.Flag_Aktif', '=', 'Y');
            })
            ->whereNotNull('k.Kode_Competency')
            ->where('k.Kode_Competency', '!=', '')
            ->distinct()->orderBy('k.Kode_Competency')
            ->select('k.Kode_Competency');
    }

    /**
     * TIDAK menyaring Flag_Aktif N_PA_Cycle — dan itu sengaja, bukan lupa.
     * baseQuery() leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, jadi baris hasil
     * milik siklus yang sudah dinonaktifkan tetap bisa keluar. Kalau daftar opsi
     * ini disaring Flag_Aktif='Y' sementara kuerinya tidak, opsinya jadi LEBIH
     * SEMPIT daripada data yang sebenarnya bisa muncul — siklus yang barisnya
     * ada di hasil jadi tak bisa dipilih sama sekali di dropdown.
     *
     * PERINGATAN: bila kelak siklus nonaktif harus dibuang dari laporan,
     * baseQuery() DAN method ini wajib diubah BERSAMAAN.
     */
    public function queryKodeCycleUnik(): Builder
    {
        return $this->queryPopulasi()
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.Id_PA_Result', '=', 'rc.PA_Result_Id')
                    ->where('r.Flag_Aktif', '=', 'Y');
            })
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y')
                    // Peserta DIKECUALIKAN bukan bagian hasil siklus; dulu ikut terekspor.
                    ->where('p.Status_Peserta', '=', 'DIIKUTKAN');
            })
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->whereNotNull('c.Kode_Cycle')
            ->where('c.Kode_Cycle', '!=', '')
            ->distinct()->orderBy('c.Kode_Cycle')
            ->select('c.Kode_Cycle');
    }

    private function opsi(array $nilai): array
    {
        return array_values(array_map(
            fn ($item) => ['value' => (string) $item, 'label' => (string) $item],
            $nilai
        ));
    }
}
