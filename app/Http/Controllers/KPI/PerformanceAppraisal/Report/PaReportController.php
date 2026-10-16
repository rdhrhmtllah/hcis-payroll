<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Report;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaAdminDashboardService;
use App\Services\KPI\PerformanceAppraisal\PaAuditService;
use App\Services\KPI\PerformanceAppraisal\PaDistribusiService;
use App\Services\KPI\PerformanceAppraisal\PaKirimanTinjauService;
use App\Services\KPI\PerformanceAppraisal\PaRiwayatService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Layar laporan admin Performance Appraisal — seluruhnya BACA.
 *
 * Dipisahkan dari `PaCycleAdminController` bukan karena controller itu kepanjangan,
 * melainkan karena isinya berbeda sifat: di sana setiap method mengubah keadaan siklus
 * dan melewati gerbang; di sini tidak ada satu pun yang menulis. Menyatukannya membuat
 * pembaca berikutnya harus memeriksa satu per satu untuk tahu mana yang berbahaya.
 */
class PaReportController extends Controller
{
    public function __construct(
        private readonly PaAdminDashboardService $dashboard,
        private readonly PaDistribusiService $distribusi,
        private readonly PaRiwayatService $riwayat,
        private readonly PaAuditService $audit,

        /* Kiriman HC Learn yang masuk tetapi tidak terpetakan penuh. Berdiri di controller
           LAPORAN karena pertanyaannya "apa yang perlu saya tinjau", bukan "ubah keadaan". */
        private readonly PaKirimanTinjauService $kiriman,
        private readonly PaKurvaService $kurva,
    ) {}

    public function ringkasanSiklus(Request $request, int $cycleId)
    {
        return $this->jawab(fn () => $this->dashboard->ringkasan($cycleId));
    }

    public function monitoring(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_divisi' => ['nullable', 'integer'],
            'id_level' => ['nullable', 'integer'],
            'pa_rater_category_id' => ['nullable', 'integer'],
            /* SELESAI & TERLAMBAT ikut sejak daftar penuh disajikan. Tanpa ini menyaring
               dijawab 422 oleh validasi, padahal pilihannya ditawarkan layar. */
            'status' => ['nullable', 'string', 'in:BELUM_MULAI,BERJALAN,BELUM_SELESAI,TERKUMPUL,MENUNGGU_VERIFIKASI,SELESAI,TERLAMBAT'],
            'q' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->dashboard->monitoring($cycleId, $data));
    }

    /** Pemantauan per penilai — satu baris per orang yang menilai, yang paling tertinggal dulu. */
    public function monitoringPenilai(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:BELUM_MULAI,SEDANG,TERKUMPUL,SELESAI'],
            'id_divisi' => ['nullable', 'integer'],
            'id_level' => ['nullable', 'integer'],
            'pa_rater_category_id' => ['nullable', 'integer'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->dashboard->monitoringPenilai($cycleId, $data));
    }

    /** Orang-orang yang dinilai satu penilai, beserta status tiap lembar. */
    public function rincianPenilai(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'kode_perusahaan' => ['required', 'string', 'max:10'],
            'kode_karyawan' => ['required', 'string', 'max:30'],
        ]);

        return $this->jawab(fn () => ['baris' => $this->dashboard->rincianPenilai($cycleId, $data['kode_perusahaan'], $data['kode_karyawan'])]);
    }

    /**
     * Isi satu keranjang keputusan admin.
     *
     * Jenisnya divalidasi di sini SEBAGAI DAFTAR PUTIH, dan ditolak lagi oleh service bila
     * lolos. Dua lapis karena yang menentukan isi daftar pekerjaan admin tidak boleh bisa
     * diubah lewat query string.
     */
    public function keranjang(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'jenis' => ['required', 'string',
                'in:siap_dihitung,tertahan,perlu_verifikasi,terbit,kiriman_susulan,needs_kpi,needs_rater,tertahan_lain,kpi_belum_lengkap,kategori_tidak_dihitung,needs_review_qualitative,reviewing_calibration'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
            // Cari nama/NIK di seluruh isi keranjang, bukan hanya di halaman yang terbuka.
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->jawab(fn () => $this->dashboard->isiKeranjang(
            $cycleId,
            $data['jenis'],
            (int) ($data['halaman'] ?? 1),
            (int) ($data['per_halaman'] ?? 25),
            $data['q'] ?? null,
        ));
    }

    public function distribusi(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['nullable', 'integer'],
            'id_divisi' => ['nullable', 'integer'],
        ]);

        return $this->jawab(fn () => $this->distribusi->distribusi($cycleId, $data));
    }

    /** Kurva grade siklus: per grup n, rata-rata, simpangan baku, pita, sebaran, peringatan. */
    public function kurva(int $cycleId)
    {
        return $this->jawab(fn () => $this->kurva->pratinjau($cycleId));
    }

    /** Rincian perhitungan kurva satu grup: anggota, langkah μ/σ, dan turunan batas grade. */
    public function kurvaGrup(int $cycleId, int $grupId)
    {
        return $this->jawab(fn () => $this->kurva->detailGrup($cycleId, $grupId));
    }

    public function exportDistribusiPdf(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['nullable', 'integer'],
            'id_divisi' => ['nullable', 'integer'],
        ]);

        $user = $request->user() ?? auth()->user();
        $penarik = [
            'nama' => $user?->karyawan?->Nama ?? $user?->Nama ?? $user?->name ?? 'Administrator HCIS',
            'nik' => $user?->karyawan?->Kode_Karyawan ?? $user?->UserID_Web ?? 'ADM-HCIS',
            'divisi' => $user?->karyawan?->Nama_Divisi ?? $user?->karyawan?->Nama_Unit_Kerja ?? 'Human Capital Management',
            'waktu' => now()->format('d F Y, H:i'),
        ];

        $pdf = $this->distribusi->exportPdf($cycleId, $data, $penarik);
        $fileName = 'Laporan_Eksekutif_Distribusi_PA_Cycle_'.$cycleId.'_'.date('Ymd_His').'.pdf';

        return $pdf->stream($fileName);
    }

    public function riwayat(Request $request)
    {
        $data = $request->validate([
            'tahun' => ['nullable', 'integer'],
            'cycle_id' => ['nullable', 'integer'],
            'id_divisi' => ['nullable', 'integer'],
            'id_level' => ['nullable', 'integer'],
            'kode_grade' => ['nullable', 'string', 'max:30'],
            'status_hasil' => ['nullable', 'string', 'max:30'],
            // Tanpa ini saringannya diterima service tetapi dibuang validasi, sehingga
            // layar mengirimnya dan tidak terjadi apa-apa — bentuk kegagalan yang paling
            // sulit dikenali karena balasannya tetap 200.
            'status_peserta' => ['nullable', 'string', 'in:DIIKUTKAN,DIKECUALIKAN,TERTUNDA,SEMUA'],
            'dikalibrasi' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->riwayat->riwayatAdmin($data));
    }

    /**
     * Lini masa audit satu siklus.
     *
     * `hanya_keputusan_manusia` BAKU false. Menyaring diam-diam akan membuat orang
     * menyimpulkan tidak ada kejadian pada hal yang justru disembunyikan.
     */
    public function auditSiklus(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'jenis' => ['nullable', 'string', 'in:'.implode(',', PaAuditService::JENIS)],
            'participant_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'hanya_keputusan_manusia' => ['nullable', 'boolean'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->audit->lini($cycleId, $data));
    }

    /**
     * Kiriman HC Learn yang perlu ditinjau.
     *
     * Sampai 10 September 2026 keadaan "masuk tetapi tidak terpetakan" hanya dikirim balik
     * ke HC Learn di badan respons callback; tidak satu pun layar HCIS menampilkannya.
     * Akibatnya penilaian yang sudah diisi orang bisa menggantung tanpa satu pun tanda —
     * dan yang memberi tahu akhirnya orangnya sendiri.
     */
    public function kirimanPerluDitinjau(Request $request, int $cycleId)
    {
        return $this->jawab(fn () => $this->kiriman->daftar($cycleId));
    }

    private function jawab(callable $kerja)
    {
        try {
            return ResponseHelper::success($kerja());
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
