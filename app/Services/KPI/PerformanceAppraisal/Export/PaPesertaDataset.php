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
 * Dataset peserta Performance Appraisal untuk Export Studio.
 *
 * Grain: satu baris = satu peserta yang terdaftar pada satu siklus.
 *
 * Berbasis N_PA_Participant, BUKAN N_PA_Result — supaya peserta DIKECUALIKAN
 * dan TERTUNDA (yang tidak punya baris hasil sama sekali) tetap bisa
 * dilaporkan. Jumlah penugasan diambil lewat LEFT JOIN ke turunan yang sudah
 * diagregasi per participant: peserta tanpa tugas tetap muncul (satu baris,
 * jumlah_penugasan NULL) dan peserta dengan banyak tugas TIDAK tergandakan.
 *
 * Identitas organisasi peserta dibaca dari kolom `..._Saat_Cycle` milik
 * N_PA_Participant, BUKAN dari master karyawan hidup — laporan historis wajib
 * menampilkan posisi peserta saat siklus berjalan. Karena alasan yang sama,
 * dataset ini TIDAK join ke View_Divisi_Sub_Divisi.
 */
class PaPesertaDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    public const STATUS_DIIKUTKAN = 'DIIKUTKAN';

    public const STATUS_DIKECUALIKAN = 'DIKECUALIKAN';

    public const STATUS_TERTUNDA = 'TERTUNDA';

    public function kunci(): string
    {
        return 'peserta';
    }

    public function label(): string
    {
        return 'Peserta Performance Appraisal';
    }

    public function grain(): string
    {
        return 'Satu baris = satu peserta yang terdaftar pada satu siklus.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('kode_cycle', 'Kode Siklus', 'c.Kode_Cycle', 'teks', 'siklus'),
            new Dimensi('nama_cycle', 'Nama Siklus', 'c.Nama_Cycle', 'teks', 'siklus'),
            new Dimensi('tahun', 'Tahun', 'c.Tahun', 'angka', 'siklus'),
            new Dimensi('periode_dari', 'Periode Dinilai Dari', 'c.Periode_Dinilai_Dari', 'tanggal', 'siklus'),
            new Dimensi('periode_sampai', 'Periode Dinilai Sampai', 'c.Periode_Dinilai_Sampai', 'tanggal', 'siklus'),
            new Dimensi('kode_karyawan', 'Kode Karyawan', 'p.Kode_Karyawan', 'teks', 'peserta'),
            new Dimensi('nama_karyawan', 'Nama Karyawan', 'p.Nama_Karyawan_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('level', 'Level', 'p.Nama_Level_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('divisi', 'Divisi', 'p.Nama_Divisi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('sub_divisi', 'Sub Divisi', 'p.Nama_Sub_Divisi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('jabatan', 'Jabatan', 'p.Nama_Jabatan_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('lokasi', 'Lokasi', 'p.Lokasi_Saat_Cycle', 'teks', 'peserta'),
            new Dimensi('status_peserta', 'Status Peserta', 'p.Status_Peserta', 'teks', 'peserta'),
            new Dimensi('alasan_dikecualikan', 'Alasan Dikecualikan', 'p.Alasan_Dikecualikan', 'teks', 'peserta'),
            new Dimensi('snapshot_at', 'Dibekukan Pada', 'p.Snapshot_At', 'tanggal', 'peserta', false, 'tanggal_jam'),
            // Nilai MENTAH milik baris peserta ini (dari turunan agregat).
            // Kunci *_baris supaya mode rinci membaca per baris tanpa menimpa
            // metadata agregat jumlah_penugasan.
            new Dimensi('jumlah_penugasan_baris', 'Jumlah Penugasan (Baris)', 'at.jumlah_penugasan', 'angka', 'penugasan'),
        ];
    }

    public function ukuran(): array
    {
        return [
            new Ukuran('jumlah_orang', 'Jumlah Orang', 'COUNT(*)', 'angka'),
            // SUM dari kolom turunan, COALESCE ke 0 supaya peserta tanpa tugas
            // menyumbang 0 (bukan NULL yang menggagalkan penjumlahan).
            new Ukuran('jumlah_penugasan', 'Jumlah Penugasan', 'SUM(COALESCE(at.jumlah_penugasan, 0))', 'angka'),
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
            new Saringan('status_peserta', 'Status Peserta', 'p.Status_Peserta', 'teks', ['sama_dengan', 'salah_satu']),
        ];
    }

    public function baseQuery(): Builder
    {
        $jumlahPenugasan = DB::table('N_PA_Assignment')
            ->select('PA_Participant_Id', DB::raw('COUNT(*) AS jumlah_penugasan'))
            ->where('Flag_Aktif', '=', 'Y')
            ->groupBy('PA_Participant_Id');

        return DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoinSub($jumlahPenugasan, 'at', 'at.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.Flag_Aktif', '=', 'Y');
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
            'status_peserta' => $this->opsiStatusPeserta(),
        ];
    }

    /**
     * Satu konstanta daftar: label dropdown dan nilai filter lahir dari sumber
     * yang sama. Ditulis terpisah, memilih opsi membandingkan teks yang tak
     * pernah dihasilkan kueri — nol baris tanpa satu pun galat.
     *
     * @return list<string>
     */
    public function daftarStatusPeserta(): array
    {
        return [self::STATUS_DIIKUTKAN, self::STATUS_DIKECUALIKAN, self::STATUS_TERTUNDA];
    }

    public function opsiStatusPeserta(): array
    {
        return $this->opsi($this->daftarStatusPeserta());
    }

    public function queryDivisiUnik(): Builder
    {
        return DB::table('N_PA_Participant as p')
            ->where('p.Flag_Aktif', '=', 'Y')
            ->whereNotNull('p.Nama_Divisi_Saat_Cycle')
            ->where('p.Nama_Divisi_Saat_Cycle', '!=', '')
            ->distinct()->orderBy('p.Nama_Divisi_Saat_Cycle')
            ->select('p.Nama_Divisi_Saat_Cycle');
    }

    /**
     * Opsi umum untuk kolom identitas snapshot (`..._Saat_Cycle`) milik
     * N_PA_Participant: sub_divisi, level, jabatan, lokasi.
     *
     * Sama seperti queryDivisiUnik(): dibaca dari snapshot saat siklus (BUKAN
     * master karyawan hidup) dan disaring Flag_Aktif='Y' -- kolom snapshot milik
     * baris participant lama ber-Flag_Aktif='T' tetap tersimpan. Tanpa saringan
     * ini, nilai milik baris mati tetap muncul di dropdown lalu memilihnya
     * menghasilkan nol baris tanpa satu pun galat.
     *
     * Mengembalikan Builder BELUM dieksekusi supaya saringannya bisa diperiksa
     * lewat toSql() tanpa menyentuh basis data. select() dibatasi ke $kolom
     * sehingga ->pluck()->all() langsung mengembalikan daftar nilai, bukan objek.
     */
    public function queryOpsiSnapshot(string $kolom): Builder
    {
        return DB::table('N_PA_Participant as p')
            ->where('p.Flag_Aktif', '=', 'Y')
            ->whereNotNull("p.{$kolom}")
            ->where("p.{$kolom}", '!=', '')
            ->distinct()->orderBy("p.{$kolom}")
            ->select("p.{$kolom}");
    }

    /**
     * TIDAK menyaring Flag_Aktif N_PA_Cycle — dan itu sengaja, bukan lupa.
     * baseQuery() leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, jadi baris
     * peserta milik siklus yang sudah dinonaktifkan tetap bisa keluar. Kalau
     * daftar opsi ini disaring Flag_Aktif='Y' sementara kuerinya tidak, opsinya
     * jadi LEBIH SEMPIT daripada data yang sebenarnya bisa muncul.
     *
     * PERINGATAN: bila kelak siklus nonaktif harus dibuang dari laporan,
     * baseQuery() DAN method ini wajib diubah BERSAMAAN.
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
