<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\LMS\SOP\SopDocumentControlService;
use App\Services\LMS\SOP\SopDokumenKesehatanService;
use App\Services\LMS\SOP\SopJangkauanPublikService;
use App\Services\LMS\SOP\SopMonitoringAnalyticsService;
use App\Services\LMS\SOP\SopMonitoringService;
use App\Services\LMS\SOP\SopRingkasanAgregatService;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Admin Monitoring & Coverage.
 * KPI + coverage per paket + drill (per-item rollup & per-karyawan).
 */
class SopAdminMonitoringController extends Controller
{
    public function __construct(
        private SopMonitoringService $service,
        private SopComplianceExportService $exportSvc
    ) {}

    /** GET /sop/admin/monitoring */
    public function index()
    {
        return inertia('LMS/SOP/Governance/Admin/SopMonitoring');
    }

    /** GET /sop/admin/paket/{id} or /sop/admin/monitoring/paket/{id} */
    public function paketDetailPage(string $id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            abort(404);
        }

        return inertia('LMS/SOP/Governance/Admin/SopMonitoringPaketDetail', [
            'idPaket' => $id,
            'idPaketRaw' => (int) $d[0],
        ]);
    }

    /**
     * Saringan tab Ringkasan — paket, prioritas, atribut organisasi,
     * serta rentang tanggal global yang berlaku serentak untuk seluruh tab Ringkasan.
     */
    private function saringan(Request $request): array
    {
        $arr = fn (string $k) => array_values(array_filter(
            is_array($request->query($k)) ? $request->query($k) : array_filter(explode(',', (string) $request->query($k, ''))),
            fn ($x) => trim((string) $x) !== '',
        ));

        $dateStart = $request->query('date_start') ?: $request->query('dari');
        $dateEnd = $request->query('date_end') ?: $request->query('sampai');
        $dateMode = $request->query('date_mode', 'assign');

        return [
            'paket' => $arr('paket'),
            'dept' => $arr('dept'),
            'divisi' => $arr('divisi'),
            'level' => $arr('level'),
            'jabatan' => $arr('jabatan'),
            'lokasi' => $arr('lokasi'),
            'prioritas' => $request->query('prioritas'),
            /* Sumber penugasan: MANUAL/AUTO_RULE = kewajiban, MANDIRI = minat
               sukarela pada SOP publik. Kosong berarti keduanya, sehingga
               pemanggil lama tak berubah perilakunya. */
            'sumber' => $arr('sumber'),
            'date_start' => $dateStart ? substr((string) $dateStart, 0, 10) : null,
            'date_end' => $dateEnd ? substr((string) $dateEnd, 0, 10) : null,
            'date_mode' => $dateMode,
            'dari' => $dateStart ? substr((string) $dateStart, 0, 10) : null,
            'sampai' => $dateEnd ? substr((string) $dateEnd, 0, 10) : null,
        ];
    }

    /**
     * GET /api/v1/sop/admin/monitoring/dokumen/jangkauan-publik
     *
     * Lapis analisis tab PUBLIK. Pertanyaannya berbeda dari tab Fungsional,
     * bukan sekadar penyaringnya: tak ada kewajiban yang bisa dilanggar, jadi
     * tenggat, keterlambatan, dan tingkat penyelesaian tak punya arti di sini.
     * Yang diukur adalah minat — siapa yang membuka, dan mana yang tak
     * disentuh siapa pun.
     */
    public function dokumenJangkauanPublik(Request $request)
    {
        try {
            return ResponseHelper::success(
                app(SopJangkauanPublikService::class)->jangkauan($this->saringan($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring dokumenJangkauanPublik gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/monitoring/dokumen/kesehatan
     *
     * Lapis DOKUMEN pada dashboard: komposisi tiga kelompok, piramid jenis,
     * sebaran APQC, kepemilikan unit, versi yang menggantung.
     *
     * Berbeda sumber dari /dokumen/ringkasan yang sudah ada, dan bedanya
     * disengaja: yang lama berbutir BARIS PAKET (satu dokumen di tiga paket
     * terhitung tiga kali, dan dokumen tanpa paket tak punya baris sama
     * sekali), yang ini berbutir DOKUMEN. Keduanya sementara hidup
     * berdampingan karena register masih memakai butiran lama untuk daftar
     * barisnya — lihat catatan di SopDokumenKesehatanService.
     */
    public function dokumenKesehatan(Request $request)
    {
        try {
            return ResponseHelper::success(
                app(SopDokumenKesehatanService::class)->kesehatan($this->saringan($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring dokumenKesehatan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/monitoring/analitik/heatmap-sumbu
     *
     * Pilihan sumbu X beserta satuannya. Dibaca dari master di service, bukan
     * dihafal layar: menambah dimensi baru cukup satu baris di SUMBU_X, dan
     * pemilihnya ikut tanpa satu baris Vue pun berubah.
     */
    public function heatmapSumbu()
    {
        return ResponseHelper::success(SopMonitoringAnalyticsService::sumbuX(), 'Data Ditemukan');
    }

    /**
     * GET /api/v1/sop/admin/monitoring/paket-options
     *
     * Daftar paket terbit untuk palet perintah di layar Monitoring.
     *
     * RUTENYA TAK PERNAH ADA. SopMonitoring.vue sudah memanggil alamat ini
     * sejak lama, gagal 404, lalu menelan galatnya sendiri (`// Non-fatal`) —
     * sehingga pencarian paket di palet selalu memulangkan hasil kosong tanpa
     * satu pun tanda bahwa ada yang rusak.
     *
     * TIDAK MEMAKAI /filter-options yang sudah ada: endpoint itu memulangkan
     * NAMA paket saja, sedangkan palet berpindah lewat Id_SOP_Paket. Memaksa
     * paletnya menyaring dengan nama akan membuat dua paket bernama mirip
     * saling tertukar.
     *
     * Idnya di-hashid seperti seluruh id yang keluar dari modul ini; saringan
     * monitoring sudah men-decode-nya di terapkanSaringanPaketRingkas().
     */
    public function paketOptions()
    {
        try {
            $rows = DB::table('N_SOP_Master_Paket')
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Status', SopStatus::PAKET_PUBLISHED)
                ->whereNotNull('Nama_Paket')
                ->orderBy('Nama_Paket')
                ->get(['Id_SOP_Paket', 'Nama_Paket', 'Kode_Paket'])
                ->map(function ($p) {
                    $p->Id_SOP_Paket = Hashids::connection('custom')->encode($p->Id_SOP_Paket);

                    return $p;
                })
                ->all();

            return ResponseHelper::success($rows, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring paketOptions gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/monitoring/summary */
    public function summary(Request $request)
    {
        try {
            return ResponseHelper::success($this->service->summary($this->saringan($request)));
        } catch (\Exception $e) {
            Log::error('SOP monitoring summary gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/monitoring/coverage */
    public function coverage(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 10)));
            $q = $request->query('q');

            $arr = fn (string $k) => array_values(array_filter(
                is_array($request->query($k)) ? $request->query($k) : array_filter(explode(',', (string) $request->query($k, ''))),
                fn ($x) => trim((string) $x) !== '',
            ));

            $filters = [
                'prioritas' => $request->query('prioritas'),
                'status' => $request->query('status'),
            ];

            [$rows, $total] = $this->service->coveragePaket($q, $filters, $page, $limit);

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring coverage gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= KONTROL DOKUMEN ================= */

    /** GET /dokumen/ringkasan — hitungan per ember untuk chip & kartu triase. */
    public function dokumenRingkasan(Request $request)
    {
        try {
            return ResponseHelper::success(app(SopDocumentControlService::class)->ringkasan(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring dokumenRingkasan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /dokumen — register kontrol dokumen (versi AKTIF saja). */
    public function dokumen(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));

            [$rows, $total] = app(SopDocumentControlService::class)->register([
                'bucket' => $request->query('bucket'),
                'q' => $request->query('q'),
                'prioritas' => $request->query('prioritas'),
            ], $page, $limit);

            // 200 + array kosong (bukan 404 seperti /coverage lama) — endpoint baru
            // memakai konvensi yang lebih lazim; /coverage sengaja tak diubah.
            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring dokumen gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= ANALITIK ================= */

    /** GET /risiko — papan risiko, urutan yang bisa ditelusuri. */
    public function risiko(Request $request)
    {
        try {
            $rows = app(SopMonitoringAnalyticsService::class)->papanRisiko(
                $this->saringan($request),
                min(100, max(5, (int) $request->query('limit', 25))),
            );

            return ResponseHelper::success($rows, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring risiko gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /pulse — denyut kepatuhan untuk kokpit: arah 7 hari, sparkline 14 hari,
     * dan (opsional) apa yang berubah sejak kunjungan terakhir admin.
     */
    public function pulse(Request $request)
    {
        try {
            return ResponseHelper::success(
                app(SopMonitoringAnalyticsService::class)->pulse($request->query('sejak'), $this->saringan($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring pulse gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/tren — deret kepatuhan dari ledger (bisa membalas cukup_data:false). */
    public function tren(Request $request)
    {
        try {
            $s = $this->saringan($request);
            $dari = $request->query('dari') ?: ($s['date_start'] ?? null);
            $sampai = $request->query('sampai') ?: ($s['date_end'] ?? null);
            $d = app(SopMonitoringAnalyticsService::class)->tren(
                $dari,
                $sampai,
                $request->query('granular', 'day') === 'week' ? 'week' : 'day',
                $s,
            );

            return ResponseHelper::success($d, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring tren gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/waktu-selesai — sebaran durasi buka→konfirmasi. */
    public function waktuSelesai(Request $request)
    {
        try {
            return ResponseHelper::success(app(SopMonitoringAnalyticsService::class)->distribusiWaktuSelesai($this->saringan($request)), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring waktuSelesai gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/aging — umur keterlambatan × prioritas. */
    public function aging(Request $request)
    {
        try {
            return ResponseHelper::success(app(SopMonitoringAnalyticsService::class)->agingTerlambat($this->saringan($request)), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring aging gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/heatmap — coverage departemen × sumbu X terpilih. */
    public function heatmap(Request $request)
    {
        try {
            return ResponseHelper::success(
                app(SopMonitoringAnalyticsService::class)->heatmap(
                    $this->saringan($request),
                    (string) $request->query('x', 'prioritas'),
                    (int) $request->query('halaman', 1),
                    (int) $request->query('per_halaman', 12),
                ),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring heatmap gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= AGREGAT TAB RINGKASAN =================
       Lapis kedua di atas analitik: pertanyaan yang tak bisa dijawab satu angka.
       Seluruhnya read-only dan memakai penafsiran saringan yang sama
       (trait SaringanMonitoring), supaya panel bertetangga tak pernah bicara
       tentang himpunan yang berbeda di layar yang sama. */

    /** GET /analitik/momentum — mengejar atau tertinggal, plus proyeksi tuntas. */
    public function momentum(Request $request)
    {
        try {
            $minggu = (int) $request->query('minggu', 12);

            return ResponseHelper::success(
                app(SopRingkasanAgregatService::class)->momentum($this->saringan($request), $minggu),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring momentum gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/peringkat-unit — papan peringkat organisasi + delta 30 hari. */
    public function peringkatUnit(Request $request)
    {
        try {
            $dimensi = (string) $request->query('dimensi', 'divisi');
            $limit = (int) $request->query('limit', 12);

            return ResponseHelper::success(
                app(SopRingkasanAgregatService::class)->peringkatUnit($dimensi, $this->saringan($request), $limit),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring peringkatUnit gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/konsentrasi — tunggakan menyebar atau terpusat (Pareto). */
    public function konsentrasi(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);

            return ResponseHelper::success(
                app(SopRingkasanAgregatService::class)->konsentrasi($this->saringan($request), $limit),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring konsentrasi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/titik-henti — dokumen tempat orang berhenti membaca. */
    public function titikHenti(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);

            return ResponseHelper::success(
                app(SopRingkasanAgregatService::class)->titikHenti($this->saringan($request), $limit),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring titikHenti gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /analitik/pemahaman — kualitas baca, kuis, dan penyerapan versi aktif. */
    public function pemahaman(Request $request)
    {
        try {
            return ResponseHelper::success(
                app(SopRingkasanAgregatService::class)->pemahaman($this->saringan($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring pemahaman gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /kepatuhan/kilat — konfirmasi yang terlalu cepat untuk masuk akal. */
    public function kilat(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));

            [$rows, $total] = app(SopMonitoringAnalyticsService::class)->konfirmasiKilat(
                $this->saringan($request) + ['rasio' => $request->query('rasio'), 'q' => $request->query('q')],
                $page,
                $limit,
            );

            return ResponseHelper::successWithPagination(SopKaryawanToken::sematkan($rows), $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring kilat gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /kepatuhan/mandek — dibuka tapi tak kunjung dikonfirmasi. */
    public function mandek(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));
            $hari = min(365, max(1, (int) $request->query('hari', 7)));

            [$rows, $total] = app(SopMonitoringAnalyticsService::class)->mandek($hari, $page, $limit, $this->saringan($request));

            return ResponseHelper::successWithPagination(SopKaryawanToken::sematkan($rows), $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring mandek gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= DRILL PAKET ================= */

    /**
     * GET /dokumen/{id}/detail — satu dokumen terkendali: identitasnya, paket
     * yang memakainya, dan riwayat versinya.
     *
     * Baca-saja di Fase 5, dan itu disengaja. Menyunting identitas dokumen
     * sekarang akan menghasilkan DUA nama untuk satu dokumen: item masih
     * membawa Judul_Item & Nomor_SOP_Resmi sendiri, dan itulah yang dilihat
     * karyawan. Penyuntingan pindah ke sini di Fase 6, saat item berhenti
     * membawa identitasnya sendiri.
     */
    public function dokumenDetail($id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $idDokumen = (int) $d[0];

            $dokumen = DB::table('N_SOP_Dokumen')
                ->where('Id_SOP_Dokumen', $idDokumen)
                ->first(['Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'Judul', 'Tipe', 'Deskripsi',
                    'Id_Versi_Aktif', 'Flag_Aktif']);
            if (! $dokumen) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            $svc = app(SopDocumentControlService::class);

            return ResponseHelper::success([
                'dokumen' => $dokumen,
                'paket_pemakai' => $svc->paketPemakai($idDokumen),
                'riwayat_versi' => $svc->riwayatVersi($idDokumen),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring dokumenDetail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /paket/{id}/section — coverage per bagian. */
    public function sectionCoverage($id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            return ResponseHelper::success($this->service->sectionCoverage((int) $d[0]), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring sectionCoverage gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /paket/{id}/funnel — corong ditugaskan → dibuka → dikonfirmasi. */
    public function funnel($id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            return ResponseHelper::success($this->service->funnelPaket((int) $d[0]), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring funnel gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/monitoring/kuis-tertahan — kuis yang jatahnya habis tapi belum lulus. */
    public function kuisTertahan(Request $request)
    {
        try {
            $rows = $this->service->kuisTertahan($request->query('q'));

            return ResponseHelper::success($rows, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring kuisTertahan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/monitoring/kuis/{id}/reset — kembalikan jatah percobaan. */
    public function resetKuis($id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $res = $this->service->resetPercobaanKuis((int) $d[0], Auth::user()->Id_Users ?? null);

            return $res['ok']
                ? ResponseHelper::success(null, $res['message'])
                : ResponseHelper::error($res['message'], 422);
        } catch (\Exception $e) {
            Log::error('SOP monitoring resetKuis gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/monitoring/paket/{id} */
    public function detail($id)
    {
        $d = Hashids::connection('custom')->decode($id);
        if (empty($d)) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $detail = $this->service->detailPaket($d[0]);
            // Tabel karyawan di TabPaket.vue menautkan ke halaman monitoring
            // per orang, yang alamatnya bertoken — bukan Kode_Karyawan.
            $detail['karyawan'] = SopKaryawanToken::sematkan($detail['karyawan'] ?? []);

            return ResponseHelper::success($detail, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring detail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/monitoring/export — export coverage async (Activity Center) */
    public function export(Request $request)
    {
        try {
            $total = DB::table('N_SOP_Master_Paket')
                ->where('Status', 'PUBLISHED')->where('Flag_Aktif', 'Y')->count();

            $requestedBy = optional(Auth::user()->karyawan)->Kode_Karyawan;

            $exportId = $this->exportSvc->makeExportId();
            $this->exportSvc->startJob(
                $exportId,
                $request->input('format', 'xlsx'),
                ['jenis' => 'coverage', 'nama' => 'Coverage_SOP'],
                $total,
                $requestedBy
            );
            $this->exportSvc->dispatchJob($exportId);

            return ResponseHelper::success(['task_id' => $exportId], 'Export coverage diproses. Cek panel Aktivitas.', 202);
        } catch (\Exception $e) {
            Log::error('SOP monitoring export gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
