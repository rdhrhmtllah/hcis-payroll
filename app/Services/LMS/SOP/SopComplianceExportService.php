<?php

namespace App\Services\LMS\SOP;

use App\Jobs\LMS\SOP\Export\GenerateSopComplianceExportJob;
use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export async SOP (reuse AbstractExportService + N_HRIS_Async_Tasks + Activity Center).
 *
 * Jenis via Payload_Json['jenis']:
 *   - 'kartu'    : Kartu Kepatuhan seorang karyawan (payload['kode_karyawan'])
 *   - 'coverage' : Coverage per paket (admin)
 *   - 'karyawan' : Kepatuhan per karyawan (memakai payload['filters'])
 *   - 'atestasi' : Bukti atestasi seorang karyawan
 *   - 'dokumen'  : Register kontrol dokumen
 *   - 'risiko'   : Papan risiko
 * Format: xlsx (default) / csv / pdf.
 *
 * CATATAN RANCANGAN — yang disimpan adalah SARINGAN, bukan daftar id.
 * Monitoring LMS menyimpan hingga 25.000 id terenkripsi per permintaan, yang berarti
 * 25.000 panggilan Crypt, payload multi-MB, dan mode gagal "terlalu banyak, persempit
 * saringan". Di sini job menjalankan ulang service yang sama dengan yang dipakai layar,
 * jadi hasilnya dijamin cocok dan tak ada batas yang bisa ditabrak.
 */
class SopComplianceExportService extends AbstractExportService
{
    /** Batas keras baris per berkas — penjaga memori worker, bukan pembatas fitur. */
    private const MAKS_BARIS = 50000;

    /**
     * Warna pita kelompok (paket berselang-seling dengan putih).
     *
     * Cukup pekat untuk terlihat batas bloknya sekali lihat, masih cukup muda
     * untuk tulisan hitam tetap terbaca dan untuk tidak menenggelamkan sorotan
     * merah di kolom Terlambat / Perlu Baca Ulang.
     */
    private const PITA_WARNA = 'D6E4F7';

    /** Ukuran halaman saat menyusun dataset besar secara bertahap. */
    private const UKURAN_HALAMAN = 500;

    /** Progres dilaporkan tiap sekian baris; per-baris hanya menambah tulisan sia-sia. */
    private const LANGKAH_PROGRES = 250;

    protected function moduleKey(): string
    {
        return 'sop-compliance';
    }

    protected function exportIdPrefix(): string
    {
        return 'sop-exp';
    }

    protected function storageDir(): string
    {
        return 'sop-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateSopComplianceExportJob::dispatch($exportId);
    }

    /** Label per jenis untuk panel Pusat Aktivitas. */
    private const LABEL = [
        'kartu' => 'Kartu Kepatuhan Dokumen',
        'coverage' => 'Export Tingkat Penyelesaian Dokumen',
        'karyawan' => 'Kepatuhan per Karyawan',
        'rinci' => 'Rincian per Item Dokumen',
        'atestasi' => 'Bukti Konfirmasi Dokumen',
        'dokumen' => 'Register Kontrol Dokumen',
        'risiko' => 'Papan Risiko Dokumen',
    ];

    protected function listLabel(object $job): string
    {
        $jenis = $this->payload($job)['jenis'] ?? 'kartu';

        return (self::LABEL[$jenis] ?? 'Export Dokumen').' · '.strtoupper((string) $job->Format);
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);

        return match ($p['jenis'] ?? 'kartu') {
            'coverage' => $this->datasetCoverage($job, $p),
            'karyawan' => $this->datasetKaryawan($job, $p),
            'rinci' => $this->datasetRinci($job, $p),
            'atestasi' => $this->datasetAtestasi($job, $p),
            'dokumen' => $this->datasetDokumen($job, $p),
            'risiko' => $this->datasetRisiko($job, $p),
            default => $this->datasetKartu($job, $p),
        };
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return match ($job->Format) {
            'csv' => $this->renderCsv($job, $dataset),
            'pdf' => $this->renderPdf($job, $dataset),
            default => $this->renderExcel($job, $dataset),
        };
    }

    /* ================= DATASET ================= */

    /** Kartu Kepatuhan: daftar SOP yang ditugaskan ke karyawan + statusnya. */
    private function datasetKartu(object $job, array $p): array
    {
        $kode = (string) ($p['kode_karyawan'] ?? '');

        $details = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as mp', 'mp.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            // Penugasan yang aksesnya sudah dicabut bukan lagi kewajiban karyawan.
            // Tanpa saringan ini Kartu Kepatuhan mencetaknya sebagai tunggakan
            // BELUM/PROGRESS, padahal seluruh layar Monitoring sudah mengecualikannya.
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            // SOP TERBATAS adalah penugasan resmi; SOP PUBLIK hanya jika sudah pernah diakses/dibuka.
            ->where(fn ($w) => SopGerbangPaket::pasang($w, 'mp', 'pj', 'd'))
            ->orderBy('mp.Nama_Paket')
            ->get([
                'mp.Nama_Paket', 'd.Status_Paket', 'd.Tanggal_Selesai',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
            ]);

        $total = $details->count();
        $rows = [];
        foreach ($details->values() as $i => $d) {
            $rows[] = [
                'no' => $i + 1,
                'paket' => $d->Nama_Paket,
                'status' => $d->Status_Paket,
                'tenggat' => $d->Tipe_Tenggat === 'DEADLINE' && $d->Tanggal_Deadline
                    ? Carbon::parse($d->Tanggal_Deadline)->format('Y-m-d') : 'Tanpa tenggat',
                'selesai' => $d->Tanggal_Selesai ? Carbon::parse($d->Tanggal_Selesai)->format('Y-m-d H:i') : '-',
            ];
            $this->bumpProgress($job->Export_Id, $i + 1, $total);
        }

        return [
            'title' => 'KARTU KEPATUHAN DOKUMEN — '.$kode,
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'paket', 'label' => 'Nama Dokumen', 'width' => 40],
                ['key' => 'status', 'label' => 'Status', 'width' => 14, 'center' => true],
                ['key' => 'tenggat', 'label' => 'Tenggat', 'width' => 18, 'center' => true],
                ['key' => 'selesai', 'label' => 'Tgl Selesai', 'width' => 20, 'center' => true],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Coverage per paket (admin).
     *
     * Mendelegasikan ke SopMonitoringService::coveragePaket() — sebelumnya query-nya
     * DISALIN di sini, dan salinannya sudah menyimpang: kolom `Terlambat` yang tampil
     * di layar tak pernah ikut terekspor. Satu sumber, satu angka.
     */
    private function datasetCoverage(object $job, array $p): array
    {
        [$paket, $total] = app(SopMonitoringService::class)
            ->coveragePaket($p['filters']['q'] ?? null, 1, self::MAKS_BARIS);

        $rows = [];
        foreach ($paket->values() as $i => $pk) {
            $rows[] = [
                'no' => $i + 1,
                'paket' => $pk->Nama_Paket,
                'prioritas' => $pk->Prioritas,
                'target' => (int) $pk->Total_Target,
                'selesai' => (int) $pk->Selesai,
                'progress' => (int) $pk->Progress,
                'belum' => (int) $pk->Belum,
                'terlambat' => (int) $pk->Terlambat,
                'persen' => $pk->Persen.'%',
            ];
            $this->bumpKalaPerlu($job->Export_Id, $i + 1, $total);
        }

        return [
            'title' => 'TINGKAT PENYELESAIAN DOKUMEN',
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'paket', 'label' => 'Nama Dokumen', 'width' => 38],
                ['key' => 'prioritas', 'label' => 'Prioritas', 'width' => 12, 'center' => true],
                ['key' => 'target', 'label' => 'Target', 'width' => 10, 'center' => true],
                ['key' => 'selesai', 'label' => 'Selesai', 'width' => 10, 'center' => true],
                ['key' => 'progress', 'label' => 'Progress', 'width' => 10, 'center' => true],
                ['key' => 'belum', 'label' => 'Belum', 'width' => 10, 'center' => true],
                ['key' => 'terlambat', 'label' => 'Terlambat', 'width' => 11, 'center' => true],
                ['key' => 'persen', 'label' => 'Penyelesaian', 'width' => 14, 'center' => true],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Kepatuhan per karyawan — memakai SARINGAN tersimpan, bukan daftar id.
     * Job menjalankan ulang service yang sama dengan yang dipakai layar, jadi hasil
     * export dijamin sama persis dengan yang dilihat admin saat menekan tombol.
     */
    private function datasetKaryawan(object $job, array $p): array
    {
        $f = $p['filters'] ?? [];
        $svc = app(SopMonitoringPeopleService::class);

        $rows = [];
        $no = 0;
        $halaman = 1;
        do {
            [$batch, $total] = $svc->employees($f, $halaman, self::UKURAN_HALAMAN);
            foreach ($batch as $r) {
                $rows[] = [
                    'no' => ++$no,
                    'kode' => $r->Kode_Karyawan,
                    'nama' => $r->Nama,
                    'no_induk' => $r->No_Induk_Karyawan ?? '-',
                    'lokasi' => $r->Lokasi ?? '-',
                    'divisi' => $r->Divisi ?? '-',
                    'departemen' => $r->Departemen ?? '-',
                    'jabatan' => $r->Jabatan ?? '-',
                    'total_sop' => $r->total_sop,
                    'selesai' => $r->selesai,
                    'progress' => $r->progress,
                    'belum' => $r->belum,
                    'terlambat' => $r->terlambat,
                    'persen' => $r->persen.'%',
                    'tenggat' => $r->tenggat_terdekat ?? '-',
                ];
                $this->bumpKalaPerlu($job->Export_Id, $no, $total);
            }
            $halaman++;
        } while ($batch->count() === self::UKURAN_HALAMAN && $no < self::MAKS_BARIS);

        return [
            'title' => 'KEPATUHAN DOKUMEN PER KARYAWAN',
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'kode', 'label' => 'Kode', 'width' => 10],
                ['key' => 'nama', 'label' => 'Nama Karyawan', 'width' => 26],
                ['key' => 'no_induk', 'label' => 'No. Induk', 'width' => 14],
                ['key' => 'lokasi', 'label' => 'Lokasi Kerja', 'width' => 16],
                ['key' => 'divisi', 'label' => 'Divisi', 'width' => 22],
                ['key' => 'departemen', 'label' => 'Departemen', 'width' => 22],
                ['key' => 'jabatan', 'label' => 'Jabatan', 'width' => 22],
                ['key' => 'total_sop', 'label' => 'Total Dokumen', 'width' => 10, 'center' => true],
                ['key' => 'selesai', 'label' => 'Selesai', 'width' => 9, 'center' => true],
                ['key' => 'progress', 'label' => 'Progress', 'width' => 9, 'center' => true],
                ['key' => 'belum', 'label' => 'Belum', 'width' => 9, 'center' => true],
                ['key' => 'terlambat', 'label' => 'Terlambat', 'width' => 10, 'center' => true],
                ['key' => 'persen', 'label' => 'Penyelesaian', 'width' => 14, 'center' => true],
                ['key' => 'tenggat', 'label' => 'Tenggat Terdekat', 'width' => 16, 'center' => true],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Rincian sampai tingkat ITEM — satu baris per (karyawan × paket × item).
     *
     * Ini berkas terlebar yang dihasilkan modul SOP, dan sengaja begitu: identitas
     * orang serta paket diulang di tiap baris supaya bisa langsung di-pivot dan
     * disaring di Excel tanpa membereskan sel gabungan lebih dulu.
     *
     * Kolom dan isinya datang dari SopExportRinciService, sumber yang sama dengan
     * pratinjau di layar — jadi apa yang dilihat admin sebelum menekan Export
     * adalah bentuk berkas yang benar-benar ia terima.
     *
     * Bila hasil saringan melampaui MAKS_BARIS, pemotongan DITULIS di judul berkas.
     * Berkas yang diam-diam terpotong lebih berbahaya daripada berkas yang gagal:
     * angkanya tetap terbaca masuk akal.
     */
    private function datasetRinci(object $job, array $p): array
    {
        $svc = app(SopExportRinciService::class);
        $f = $p['filters'] ?? [];

        $tersedia = $svc->count($f);
        $target = min($tersedia, $this->batasBaris());

        $rows = [];
        $halaman = 1;
        while (count($rows) < $target) {
            $batch = $svc->rows($f, $halaman, self::UKURAN_HALAMAN);
            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $r) {
                if (count($rows) >= $target) {
                    break;
                }
                $rows[] = $r;
                $this->bumpKalaPerlu($job->Export_Id, count($rows), max(1, $target));
            }
            $halaman++;
        }

        $judul = 'RINCIAN KEPATUHAN DOKUMEN PER ITEM';
        if ($tersedia > $target) {
            $judul .= sprintf(' — DIPOTONG PADA %s DARI %s BARIS', number_format($target), number_format($tersedia));
        }

        return [
            'title' => $judul,
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => $svc->columns((array) ($p['kolom'] ?? [])),
            'rows' => $rows,

            // Petunjuk baca untuk penulis Excel. Berkas ini mengulang identitas
            // orang di tiap baris supaya bisa di-pivot; konsekuensinya mata sulit
            // menemukan di mana satu paket berakhir dan paket berikutnya mulai.
            // Kunci di bawah dipakai untuk memberi pita warna dan garis pemisah.
            'pita' => ['kode', 'paket'], // warna berganti saat paketnya berganti
            'pemisah' => 'kode',            // garis tebal saat orangnya berganti
            'sorot' => ['terlambat', 'perlu_ulang'], // kolom yang bernilai "Ya" ditebalkan
        ];
    }

    /** Bukti atestasi — dokumen audit: siapa mengonfirmasi versi mana, kapan, dari mana. */
    private function datasetAtestasi(object $job, array $p): array
    {
        $kode = (string) ($p['kode_karyawan'] ?? '');
        $data = app(SopMonitoringPeopleService::class)->atestasi($kode);

        $rows = [];
        foreach ($data->values() as $i => $r) {
            $rows[] = [
                'no' => $i + 1,
                'paket' => $r->Nama_Paket,
                'item' => $r->Judul_Item,
                'nomor' => $r->Nomor_SOP_Resmi ?? '-',
                'versi' => $r->Versi_Ke !== null ? 'v'.$r->Versi_Ke : '-',
                'pengesahan' => $r->Nomor_Pengesahan ?? '-',
                'disahkan' => $r->Disahkan_Oleh ?? '-',
                'buka' => $r->Waktu_Buka ? Carbon::parse($r->Waktu_Buka)->format('Y-m-d H:i') : '-',
                'konfirmasi' => $r->Waktu_Konfirmasi ? Carbon::parse($r->Waktu_Konfirmasi)->format('Y-m-d H:i') : '-',
                'durasi' => $r->Durasi_Detik !== null ? $this->durasiRingkas((int) $r->Durasi_Detik) : '-',
                'ip' => $r->Konfirmasi_IP ?? '-',
                'perangkat' => $r->Konfirmasi_User_Agent ? mb_substr($r->Konfirmasi_User_Agent, 0, 80) : '-',
            ];
            $this->bumpKalaPerlu($job->Export_Id, $i + 1, $data->count());
        }

        return [
            'title' => 'BUKTI KONFIRMASI DOKUMEN — '.$kode,
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'paket', 'label' => 'Nama Dokumen', 'width' => 30],
                ['key' => 'item', 'label' => 'Item', 'width' => 30],
                ['key' => 'nomor', 'label' => 'No. Dokumen', 'width' => 16],
                ['key' => 'versi', 'label' => 'Versi', 'width' => 8, 'center' => true],
                ['key' => 'pengesahan', 'label' => 'No. Pengesahan', 'width' => 18],
                ['key' => 'disahkan', 'label' => 'Disahkan Oleh', 'width' => 22],
                ['key' => 'buka', 'label' => 'Dibuka', 'width' => 18, 'center' => true],
                ['key' => 'konfirmasi', 'label' => 'Dikonfirmasi', 'width' => 18, 'center' => true],
                ['key' => 'durasi', 'label' => 'Durasi', 'width' => 12, 'center' => true],
                ['key' => 'ip', 'label' => 'IP', 'width' => 16],
                ['key' => 'perangkat', 'label' => 'Perangkat', 'width' => 40],
            ],
            'rows' => $rows,
        ];
    }

    /** Register kontrol dokumen — pengesahan & kesehatan berkas + dampaknya. */
    private function datasetDokumen(object $job, array $p): array
    {
        [$data, $total] = app(SopDocumentControlService::class)
            ->register($p['filters'] ?? [], 1, self::MAKS_BARIS);

        $rows = [];
        foreach ($data->values() as $i => $r) {
            $rows[] = [
                'no' => $i + 1,
                'paket' => $r->Nama_Paket,
                'item' => $r->Judul_Item,
                'nomor' => $r->Nomor_SOP_Resmi ?? '-',
                'versi' => 'v'.$r->Versi_Ke,
                'berlaku' => $r->Tanggal_Berlaku ?? '-',
                'disahkan' => $r->Disahkan_Oleh ?? '-',
                'pengesahan' => $r->Nomor_Pengesahan ?? '-',
                'dampak' => $r->Terdampak_Dikonfirmasi.'/'.$r->Terdampak_Target,
                'temuan' => implode(', ', $r->Bucket),
            ];
            $this->bumpKalaPerlu($job->Export_Id, $i + 1, $total);
        }

        return [
            'title' => 'REGISTER KONTROL DOKUMEN',
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'paket', 'label' => 'Nama Dokumen', 'width' => 30],
                ['key' => 'item', 'label' => 'Dokumen', 'width' => 30],
                ['key' => 'nomor', 'label' => 'No. Dokumen', 'width' => 16],
                ['key' => 'versi', 'label' => 'Versi', 'width' => 8, 'center' => true],
                ['key' => 'berlaku', 'label' => 'Berlaku', 'width' => 13, 'center' => true],
                ['key' => 'disahkan', 'label' => 'Disahkan Oleh', 'width' => 22],
                ['key' => 'pengesahan', 'label' => 'No. Pengesahan', 'width' => 18],
                ['key' => 'dampak', 'label' => 'Sudah Konfirmasi', 'width' => 17, 'center' => true],
                ['key' => 'temuan', 'label' => 'Temuan', 'width' => 34],
            ],
            'rows' => $rows,
        ];
    }

    /** Papan risiko — urutan yang bisa ditelusuri, bukan skor gabungan. */
    private function datasetRisiko(object $job, array $p): array
    {
        $data = app(SopMonitoringAnalyticsService::class)->papanRisiko($p['filters'] ?? [], 200);

        $rows = [];
        foreach ($data->values() as $i => $r) {
            $rows[] = [
                'no' => $i + 1,
                'paket' => $r->Nama_Paket,
                'prioritas' => $r->Prioritas,
                'target' => (int) $r->target,
                'selesai' => (int) $r->selesai,
                'belum' => (int) $r->belum,
                'terlambat' => (int) $r->terlambat,
                'telat_maks' => (int) $r->telat_maks_hari.' hari',
                'persen' => $r->persen.'%',
            ];
            $this->bumpKalaPerlu($job->Export_Id, $i + 1, $data->count());
        }

        return [
            'title' => 'PAPAN RISIKO KEPATUHAN DOKUMEN',
            'generated_at' => now()->format('Y-m-d H:i'),
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'width' => 6, 'center' => true],
                ['key' => 'paket', 'label' => 'Nama Dokumen', 'width' => 34],
                ['key' => 'prioritas', 'label' => 'Prioritas', 'width' => 12, 'center' => true],
                ['key' => 'target', 'label' => 'Target', 'width' => 10, 'center' => true],
                ['key' => 'selesai', 'label' => 'Selesai', 'width' => 10, 'center' => true],
                ['key' => 'belum', 'label' => 'Belum', 'width' => 10, 'center' => true],
                ['key' => 'terlambat', 'label' => 'Terlambat', 'width' => 11, 'center' => true],
                ['key' => 'telat_maks', 'label' => 'Telat Terlama', 'width' => 14, 'center' => true],
                ['key' => 'persen', 'label' => 'Penyelesaian', 'width' => 14, 'center' => true],
            ],
            'rows' => $rows,
        ];
    }

    /* ================= UTILITAS ================= */

    /**
     * Plafon baris per berkas. Bisa ditimpa supaya jalur pemotongan bisa diuji
     * tanpa harus menyiapkan 50.000 baris sungguhan — cabang yang tak pernah
     * dijalankan tes adalah cabang yang tak pernah diketahui rusak.
     */
    protected function batasBaris(): int
    {
        return self::MAKS_BARIS;
    }

    /**
     * Lapor progres tiap LANGKAH_PROGRES baris, bukan tiap baris.
     * Pada 7 paket bedanya nol; pada 50.000 baris karyawan itu 50.000 UPDATE
     * yang tak seorang pun lihat.
     */
    private function bumpKalaPerlu(string $exportId, int $ke, int $total): void
    {
        if ($ke % self::LANGKAH_PROGRES === 0 || $ke === $total) {
            $this->bumpProgress($exportId, $ke, max(1, $total));
        }
    }

    private function durasiRingkas(int $detik): string
    {
        if ($detik < 60) {
            return $detik.' dtk';
        }
        if ($detik < 3600) {
            return round($detik / 60).' mnt';
        }
        if ($detik < 86400) {
            return round($detik / 3600, 1).' jam';
        }

        return round($detik / 86400, 1).' hari';
    }

    /* ================= RENDER ================= */

    private function renderExcel(object $job, array $dataset): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dokumen');

        $columns = $dataset['columns'];
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));

        $sheet->setCellValue('A1', $dataset['title']);
        $sheet->setCellValue('A2', 'Dibuat: '.$dataset['generated_at']);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '235C8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 3;
        foreach ($columns as $i => $col) {
            $sheet->setCellValue([$i + 1, $headerRow], $col['label']);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($col['width']);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '235C8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = $headerRow + 1;
        foreach ($dataset['rows'] as $row) {
            foreach ($columns as $i => $col) {
                $sheet->setCellValueExplicit([$i + 1, $r], (string) ($row[$col['key']] ?? ''), DataType::TYPE_STRING);
            }
            $r++;
        }
        $lastDataRow = $r - 1;

        if ($lastDataRow >= $headerRow + 1) {
            $sheet->getStyle("A1:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C3D6']]],
            ]);
            foreach ($columns as $i => $col) {
                if (! empty($col['center'])) {
                    $letter = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getStyle("{$letter}".($headerRow + 1).":{$letter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }

            $this->tandaiKelompok($sheet, $dataset, $headerRow, $lastCol, $lastDataRow);

            // Saringan bawaan Excel. Pada berkas 38 kolom ini bedanya besar:
            // admin bisa menyaring sendiri tanpa mengulang export.
            $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        }

        // Bekukan kepala tabel DAN kolom identitas — menggulir ke kanan pada 38
        // kolom tanpa ini membuat baris kehilangan pemiliknya.
        $sheet->freezePane($this->selBeku($columns, $headerRow + 1));

        $tmp = tempnam(sys_get_temp_dir(), 'sopexp');
        (new Xlsx($spreadsheet))->save($tmp);
        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->fileName($job, 'xlsx'), $size];
    }

    /**
     * Pita warna per kelompok + garis pemisah antar orang + penebalan nilai "Ya".
     *
     * Kenapa per KELOMPOK, bukan zebra per baris: pada berkas ini satu paket bisa
     * memakan 2 baris atau 30. Zebra baris-genap-baris-ganjil justru memotong
     * paket di tengah dan membuatnya makin sulit dibaca. Pita berganti hanya saat
     * paketnya berganti, jadi satu blok warna = satu paket.
     *
     * Gaya dipasang per RENTANG, bukan per sel: jumlah panggilan mengikuti jumlah
     * paket, bukan jumlah baris. Mewarnai 50.000 baris satu per satu akan
     * menghabiskan memori worker sebelum berkasnya sempat ditulis.
     *
     * Prasyaratnya barisnya sudah terurut per (orang, penugasan) — dijamin oleh
     * ORDER BY di SopExportRinciService. Tanpa itu pita akan pecah-pecah.
     */
    private function tandaiKelompok($sheet, array $dataset, int $headerRow, string $lastCol, int $lastDataRow): void
    {
        $pita = $dataset['pita'] ?? null;
        if (empty($pita) || empty($dataset['rows'])) {
            return;
        }

        $kunci = fn (array $r, array $bidang) => implode('|', array_map(fn ($k) => (string) ($r[$k] ?? ''), $bidang));

        $pemisah = $dataset['pemisah'] ?? null;
        $mulai = $headerRow + 1;
        $sebelumnya = null;
        $sebelumnyaOrang = null;
        $terang = false;
        $rows = array_values($dataset['rows']);

        foreach ($rows as $i => $r) {
            $baris = $headerRow + 1 + $i;
            $k = $kunci($r, $pita);

            if ($sebelumnya !== null && $k !== $sebelumnya) {
                if ($terang) {
                    $this->warnaiRentang($sheet, $mulai, $baris - 1, $lastCol);
                }
                $terang = ! $terang;
                $mulai = $baris;
            }
            $sebelumnya = $k;

            if ($pemisah) {
                $orang = (string) ($r[$pemisah] ?? '');
                if ($sebelumnyaOrang !== null && $orang !== $sebelumnyaOrang) {
                    $sheet->getStyle("A{$baris}:{$lastCol}{$baris}")->applyFromArray([
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']]],
                    ]);
                }
                $sebelumnyaOrang = $orang;
            }
        }
        if ($terang) {
            $this->warnaiRentang($sheet, $mulai, $lastDataRow, $lastCol);
        }

        $this->soroti($sheet, $dataset, $headerRow, $lastDataRow);
    }

    private function warnaiRentang($sheet, int $dari, int $sampai, string $lastCol): void
    {
        if ($sampai < $dari) {
            return;
        }

        $sheet->getStyle("A{$dari}:{$lastCol}{$sampai}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PITA_WARNA]],
        ]);
    }

    /**
     * Kolom peringatan ditebalkan saat bernilai "Ya".
     *
     * Memakai conditional formatting, bukan mewarnai sel satu per satu: aturannya
     * dipasang SEKALI per kolom apa pun jumlah barisnya. Sengaja hanya warna huruf
     * — latar akan bertabrakan dengan pita kelompok di atas.
     */
    private function soroti($sheet, array $dataset, int $headerRow, int $lastDataRow): void
    {
        $sorot = $dataset['sorot'] ?? [];
        if (empty($sorot)) {
            return;
        }

        foreach ($dataset['columns'] as $i => $col) {
            if (! in_array($col['key'], (array) $sorot, true)) {
                continue;
            }

            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $aturan = new Conditional;
            $aturan->setConditionType(Conditional::CONDITION_CELLIS);
            $aturan->setOperatorType(Conditional::OPERATOR_EQUAL);
            $aturan->addCondition('"Ya"');
            $aturan->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B91C1C');

            $sheet->getStyle("{$huruf}".($headerRow + 1).":{$huruf}{$lastDataRow}")
                ->setConditionalStyles([$aturan]);
        }
    }

    /**
     * Sel titik beku: kepala tabel selalu, ditambah kolom identitas bila memang
     * dicetak. Kalau admin mematikan kelompok kolom identitas, tak ada yang perlu
     * dibekukan ke samping — jadi ini dihitung, bukan dipatok 'D'.
     */
    private function selBeku(array $columns, int $barisData): string
    {
        $identitas = ['no', 'kode', 'nama'];
        $terakhir = 0;
        foreach ($columns as $i => $col) {
            if (in_array($col['key'], $identitas, true)) {
                $terakhir = $i + 1;
            }
        }

        return Coordinate::stringFromColumnIndex($terakhir + 1).$barisData;
    }

    private function fileName(object $job, string $ext): string
    {
        $jenis = $this->payload($job)['jenis'] ?? 'kartu';
        $prefix = match ($jenis) {
            'coverage' => 'Tingkat_Penyelesaian_SOP',
            'rinci' => 'Rincian_Item_SOP',
            'karyawan' => 'Kepatuhan_Karyawan_SOP',
            'dokumen' => 'Register_Dokumen_SOP',
            'risiko' => 'Papan_Risiko_SOP',
            'atestasi' => 'Bukti_Konfirmasi_SOP',
            default => 'Kartu_Kepatuhan_SOP',
        };

        return $prefix.'_'.now()->format('Ymd_His').'.'.$ext;
    }

    private function renderCsv(object $job, array $dataset): array
    {
        $columns = $dataset['columns'];
        $tmp = tempnam(sys_get_temp_dir(), 'sopexp');
        $fh = fopen($tmp, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map(fn ($c) => $c['label'], $columns));
        foreach ($dataset['rows'] as $row) {
            fputcsv($fh, array_map(fn ($c) => (string) ($row[$c['key']] ?? ''), $columns));
        }
        fclose($fh);

        $path = $this->filePathFor($job, 'csv');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->fileName($job, 'csv'), $size];
    }

    /**
     * PDF — untuk lampiran audit yang perlu dibaca, bukan diolah.
     * Landscape karena register dokumen & kepatuhan per karyawan sama-sama lebar.
     */
    private function renderPdf(object $job, array $dataset): array
    {
        $pdf = Pdf::loadView('exports.sop-compliance-pdf', ['dataset' => $dataset])
            ->setPaper('a4', 'landscape');

        $path = $this->filePathFor($job, 'pdf');
        $size = $this->putFile($path, $pdf->output());

        return [$path, $this->fileName($job, 'pdf'), $size];
    }
}
