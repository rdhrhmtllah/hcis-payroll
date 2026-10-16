<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dataset hasil Performance Appraisal untuk Export Studio.
 *
 * Grain: satu baris = satu peserta pada satu siklus (satu N_PA_Result).
 *
 * Identitas organisasi peserta dibaca dari kolom `..._Saat_Cycle` milik
 * N_PA_Participant, BUKAN dari master karyawan hidup — supaya laporan
 * historis tetap menampilkan posisi peserta pada saat siklus berjalan,
 * bukan posisinya hari ini. Karena alasan yang sama, dataset ini TIDAK
 * boleh join ke View_Divisi_Sub_Divisi: view itu berbaris satu per
 * pasangan divisi x sub-divisi, sehingga orang yang bernaung di dua
 * divisi akan tergandakan.
 */
class PaHasilDataset implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'hasil';
    }

    public function label(): string
    {
        return 'Hasil Performance Appraisal';
    }

    public function grain(): string
    {
        return 'Satu baris = satu peserta pada satu siklus.';
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
            new Dimensi('status_hasil', 'Status Hasil', 'r.Status_Hasil', 'teks', 'hasil'),
            new Dimensi('alasan_tertahan', 'Alasan Tertahan', 'r.Alasan_Tertahan', 'teks', 'hasil'),
            new Dimensi('grade_sistem', 'Grade Sistem', 'r.Kode_Grade_Sistem', 'teks', 'hasil'),
            new Dimensi('grade_final', 'Grade Final', 'r.Kode_Grade_Final', 'teks', 'hasil'),
            new Dimensi('nama_grade', 'Nama Grade', 'sr.Nama_Range', 'teks', 'hasil'),
            // Nilai MENTAH milik baris/peserta ini -- BUKAN rata-rata lintas
            // baris. Kunci sengaja *_baris, berbeda dari kunci Ukuran agregat
            // di ukuran() (nilai_kpi, nilai_kompetensi, final_score): mode
            // rinci butuh nilai per baris, dan memakai ulang kunci ukuran akan
            // membuat metaKolom() di ReportRunner (yang menggabungkan dimensi()
            // dan ukuran() ke satu peta ber-kunci sama) salah menimpa metadata
            // kolom ini dengan metadata agregatnya.
            //
            // TIDAK di-CAST ke float: kolom sumbernya sudah decimal(6,2)
            // (DDL-2026-080-performance-appraisal-result), dimensi hanya
            // membaca nilai apa adanya. CAST float hanya perlu di ukuran()
            // karena AVG() atas decimal berperilaku beda dari AVG() atas float.
            // Format 'angka' dioper eksplisit (bukan bawaan 'teks') supaya
            // ReportRunner::formatNilai() membulatkannya, bukan meloloskannya
            // sebagai teks mentah -- kolom ini ada justru supaya skor per
            // peserta bisa dibaca.
            new Dimensi('nilai_kpi_baris', 'Nilai KPI (Peserta)', 'r.Nilai_KPI', 'angka', 'hasil', false, 'angka'),
            new Dimensi('nilai_kompetensi_baris', 'Nilai Kompetensi (Peserta)', 'r.Nilai_Kompetensi', 'angka', 'hasil', false, 'angka'),
            new Dimensi('nilai_akhir_baris', 'Nilai Akhir (Peserta)', 'r.Final_Score', 'angka', 'hasil', false, 'angka'),
            new Dimensi('dikalibrasi', 'Dikalibrasi', 'r.Flag_Dikalibrasi', 'teks', 'hasil', false, 'ya_tidak'),
            new Dimensi('kuadran', 'Kuadran', 'hav.Nama_Kuadran', 'teks', 'hav'),
            new Dimensi('nomor_box', 'Nomor Box', 'hav.Nomor_Box', 'angka', 'hav'),
            new Dimensi('calculated_at', 'Dihitung Pada', 'r.Calculated_At', 'tanggal', 'audit', false, 'tanggal_jam'),
            new Dimensi('published_at', 'Diterbitkan Pada', 'r.Published_At', 'tanggal', 'audit', false, 'tanggal_jam'),
        ];
    }

    public function ukuran(): array
    {
        return [
            new Ukuran('jumlah_peserta', 'Jumlah Peserta', 'COUNT(*)', 'angka'),
            new Ukuran('nilai_kpi', 'Nilai KPI', 'AVG(CAST(r.Nilai_KPI AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('nilai_kompetensi', 'Nilai Kompetensi', 'AVG(CAST(r.Nilai_Kompetensi AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('final_score', 'Nilai Akhir', 'AVG(CAST(r.Final_Score AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('rater_diharapkan', 'Penilai Diharapkan', 'SUM(COALESCE(r.Jumlah_Rater_Diharapkan, 0))', 'angka'),
            new Ukuran('rater_mengisi', 'Penilai Mengisi', 'SUM(COALESCE(r.Jumlah_Rater_Mengisi, 0))', 'angka'),
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
            new Saringan('status_hasil', 'Status Hasil', 'r.Status_Hasil', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('grade_final', 'Grade Final', 'r.Kode_Grade_Final', 'teks', ['sama_dengan', 'salah_satu']),
            new Saringan('dikalibrasi', 'Dikalibrasi', 'r.Flag_Dikalibrasi', 'teks', ['sama_dengan']),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.Flag_Aktif', '=', 'Y')
                    // Peserta DIKECUALIKAN bukan bagian hasil siklus; dulu ikut terekspor.
                    ->where('p.Status_Peserta', '=', 'DIIKUTKAN');
            })
            ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoin('N_PA_Hav_Quadrant as hav', 'hav.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->leftJoin('N_PA_Cycle_Score_Range_Snapshot as sr', function ($j) {
                // Salinan yang DIBEKUKAN per siklus, bukan master hidup (N_PA_Score_Range).
                // Master boleh berubah/dinonaktifkan setelah siklus berjalan; laporan
                // historis wajib mengikuti nama pita yang berlaku SAAT siklus itu
                // disiapkan -- lihat PaCalibrationService::pastikanGradeSah() untuk
                // alasan yang sama. Pembaca hasil PA lain (PaParticipantReadService,
                // PaResultCalculationService, PaVerifyCommand) semuanya membaca dari
                // snapshot ini, bukan dari master.
                //
                // Empat kunci, bukan tiga. PA_Cycle_Id WAJIB: snapshot berbaris satu
                // per (siklus x pita), jadi tanpa kunci ini setiap peserta tergandakan
                // sebanyak jumlah siklus yang pernah membekukan pita bergrade sama.
                $j->on('sr.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')
                    ->on('sr.Kode_Range', '=', 'r.Kode_Grade_Final')
                    ->where('sr.Score_Type', '=', 'FINAL')
                    ->where('sr.Score_Usage', '=', 'GRADE')
                    ->where('sr.Flag_Aktif', '=', 'Y');
            })
            ->where('r.Flag_Aktif', '=', 'Y');
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $lingkup->adminPenuh ? $q : $q->whereRaw('1 = 0');
    }

    public function opsiSaringan(): array
    {
        return [
            'status_hasil' => $this->opsiStatusHasil(),
            'dikalibrasi' => $this->opsi(['Y', 'T']),
            'divisi' => $this->opsi($this->queryDivisiUnik()->pluck('Nama_Divisi_Saat_Cycle')->all()),
            'sub_divisi' => $this->opsi($this->queryOpsiSnapshot('Nama_Sub_Divisi_Saat_Cycle')->pluck('Nama_Sub_Divisi_Saat_Cycle')->all()),
            'level' => $this->opsi($this->queryOpsiSnapshot('Nama_Level_Saat_Cycle')->pluck('Nama_Level_Saat_Cycle')->all()),
            'jabatan' => $this->opsi($this->queryOpsiSnapshot('Nama_Jabatan_Saat_Cycle')->pluck('Nama_Jabatan_Saat_Cycle')->all()),
            'lokasi' => $this->opsi($this->queryOpsiSnapshot('Lokasi_Saat_Cycle')->pluck('Lokasi_Saat_Cycle')->all()),
            'grade_final' => $this->opsi($this->queryGradeFinalUnik()->pluck('Kode_Grade_Final')->all()),
            'kode_cycle' => $this->opsi($this->queryKodeCycleUnik()->pluck('Kode_Cycle')->all()),
        ];
    }

    /**
     * Nilai kanonis dari PaResultCalculationService — bukan teks ditulis ulang,
     * supaya opsi dropdown tidak bisa diam-diam menyimpang dari status yang
     * benar-benar dipakai mesin perhitungan.
     */
    public function opsiStatusHasil(): array
    {
        return $this->opsi([
            PaResultCalculationService::STATUS_BELUM,
            PaResultCalculationService::STATUS_DIHITUNG,
            PaResultCalculationService::STATUS_TERTAHAN,
            PaResultCalculationService::STATUS_TERBIT,
        ]);
    }

    /**
     * Dipisah jadi method sendiri (mengembalikan Builder, belum dieksekusi) supaya
     * saringan Flag_Aktif bisa diperiksa lewat toSql() tanpa menyentuh basis data.
     *
     * Flag_Aktif WAJIB di sini: perhitungan ulang meninggalkan baris N_PA_Participant/
     * N_PA_Result lama ber-Flag_Aktif='T'. Tanpa saringan ini, divisi/grade miliknya
     * tetap muncul di dropdown lalu memilihnya menghasilkan nol baris tanpa satu pun
     * galat — kegagalan paling sulit dilacak karena tampak seperti data memang kosong.
     */
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
     * master karyawan hidup) dan disaring Flag_Aktif='Y' -- perhitungan ulang
     * meninggalkan baris participant lama ber-Flag_Aktif='T' yang nilai
     * snapshotnya tetap tersimpan. Tanpa saringan ini, nilai milik baris mati
     * tetap muncul di dropdown lalu memilihnya menghasilkan nol baris tanpa
     * satu pun galat.
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

    public function queryGradeFinalUnik(): Builder
    {
        return DB::table('N_PA_Result')
            ->where('Flag_Aktif', '=', 'Y')
            ->whereNotNull('Kode_Grade_Final')
            ->where('Kode_Grade_Final', '!=', '')
            ->distinct()->orderBy('Kode_Grade_Final');
    }

    /**
     * TIDAK menyaring Flag_Aktif — dan itu sengaja, bukan lupa.
     *
     * baseQuery() meng-leftJoin N_PA_Cycle TANPA syarat Flag_Aktif, jadi baris hasil
     * milik siklus yang sudah dinonaktifkan tetap bisa keluar dari kueri utama. Kalau
     * daftar opsi ini disaring Flag_Aktif='Y' sementara kuerinya tidak, opsinya jadi
     * LEBIH SEMPIT daripada data yang sebenarnya bisa muncul — siklus yang barisnya
     * ada di hasil jadi tak bisa dipilih sama sekali di dropdown.
     *
     * PERINGATAN: bila kelak diputuskan siklus nonaktif harus dibuang dari laporan,
     * baseQuery() DAN method ini wajib diubah BERSAMAAN. Menambah syaratnya di salah
     * satu saja akan membuat opsi dan hasil kembali tidak sinkron, hanya arahnya
     * terbalik dari sekarang.
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
