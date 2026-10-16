<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

class PaSystemTemplates
{
    public static function semua(): array
    {
        return [
            'pa-rekap-nilai-akhir' => self::rinci(
                'pa-rekap-nilai-akhir',
                'Rekap Nilai Akhir Siklus',
                'Rincian hasil peserta per siklus: identitas, status hasil, grade final, kuadran, dan nilai (KPI, kompetensi, akhir) milik peserta itu sendiri.',
                'hasil',
                // *_baris = nilai MENTAH milik baris/peserta ini, bertipe Dimensi.
                // BUKAN nilai_kpi/nilai_kompetensi/final_score -- ketiga kunci itu
                // adalah Ukuran agregat (AVG(...) lintas baris) di PaHasilDataset,
                // dan 'kolom' mode rinci divalidasi HANYA terhadap dimensi(),
                // tidak pernah terhadap ukuran(). Kunci sengaja dibedakan (bukan
                // dipakai ulang) supaya penyusun laporan tidak bingung memilih
                // antara "nilai baris ini" dan "rata-rata lintas baris".
                /* `kode_cycle` di depan: tanpanya baris dari banyak siklus tercampur dan satu
                   karyawan muncul berkali-kali tanpa pembeda. `nama_grade` di samping kodenya,
                   supaya pembaca tidak menerjemahkan `B_PLUS` sendiri. */
                ['kode_cycle', 'kode_karyawan', 'nama_karyawan', 'divisi', 'jabatan', 'status_hasil', 'grade_final', 'nama_grade', 'kuadran', 'nilai_kpi_baris', 'nilai_kompetensi_baris', 'nilai_akhir_baris']
            ),
            'pa-papan-kemajuan' => self::ringkas(
                'pa-papan-kemajuan',
                'Papan Kemajuan Pengisian',
                'Kemajuan pengisian penilai per divisi dan kategori penilai: jumlah penugasan, sudah/belum terisi, terlambat, dan persen kemajuan.',
                'penugasan',
                ['divisi', 'kategori_penilai'],
                ['jumlah_penugasan', 'sudah_terisi', 'belum_terisi', 'terlambat', 'persen_kemajuan']
            ),
            'pa-gap-kompetensi' => self::ringkas(
                'pa-gap-kompetensi',
                'Gap Kompetensi per Departemen',
                'Rata-rata nilai kompetensi per divisi, jenis, dan kompetensi: berapa peserta yang dinilai dan berapa rata-rata nilai pada tiap kompetensi.',
                'kompetensi',
                ['divisi', 'jenis_kompetensi', 'nama_kompetensi'],
                ['jumlah_peserta', 'rata_rata_nilai']
            ),
            'pa-audit-cakupan' => self::rinci(
                'pa-audit-cakupan',
                'Audit Cakupan Siklus',
                'Audit cakupan peserta per siklus: identitas, organisasi snapshot, status, alasan dikecualikan, waktu snapshot, dan jumlah penugasan tiap peserta.',
                'peserta',
                ['kode_cycle', 'nama_cycle', 'kode_karyawan', 'nama_karyawan', 'divisi', 'sub_divisi', 'jabatan', 'status_peserta', 'alasan_dikecualikan', 'snapshot_at', 'jumlah_penugasan_baris']
            ),
        ];
    }

    public static function untuk(string $dataset): array
    {
        return array_filter(
            self::semua(),
            fn (array $template) => $template['dataset'] === $dataset
        );
    }

    private static function rinci(string $kode, string $nama, string $deskripsi, string $dataset, array $kolom): array
    {
        return self::template($kode, $nama, $deskripsi, $dataset, 'rinci', [], [], $kolom);
    }

    private static function ringkas(string $kode, string $nama, string $deskripsi, string $dataset, array $dimensi, array $ukuran): array
    {
        return self::template($kode, $nama, $deskripsi, $dataset, 'ringkasan', $dimensi, $ukuran, []);
    }

    private static function template(string $kode, string $nama, string $deskripsi, string $dataset, string $mode, array $dimensi, array $ukuran, array $kolom): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => $dataset,
            'mode' => $mode,
            'definisi' => [
                'versi' => 1,
                'dataset' => $dataset,
                'mode' => $mode,
                'dimensi' => $dimensi,
                'ukuran' => $ukuran,
                'kolom' => $kolom,
                'saringan' => [],
                'urut' => [],
                'opsi' => ['grand_total' => false],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }
}
