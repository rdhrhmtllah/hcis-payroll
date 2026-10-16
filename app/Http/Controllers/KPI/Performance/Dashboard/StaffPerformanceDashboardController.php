<?php

namespace App\Http\Controllers\KPI\Performance\Dashboard;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\KpiScheduleHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KPI\Performance\Dashboard\Concerns\DetailPerPeriode;
use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\KPI\KpiAkumulasiYtdService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class StaffPerformanceDashboardController extends Controller
{
    use DetailPerPeriode;

    /**
     * Ganti periode di modal detail My Performance. Selalu milik user yang
     * login — tak ada parameter karyawan, jadi tak bisa dipakai mengintip orang
     * lain (endpoint detail yang ada pun membatasi dengan cara yang sama).
     */
    public function detailPerPeriode(Request $request)
    {
        $validated = $this->validasiPeriodeDetail($request);

        return $this->susunDetailPerPeriode((int) Auth::user()->Id_Users, (int) $validated['tahun'], (string) $validated['periode']);
    }

    /**
     * Resolusi band performa (KPI_Master_Performance) untuk sebuah nilai skor secara
     * IN-MEMORY. Tabel band kecil & jarang berubah → di-cache 10 menit, menggantikan
     * pola N+1 (sebelumnya 1 query per baris parameter di dalam ->map()). Mengembalikan
     * band dengan Range_Awal tertinggi yang <= $value (setara SQL lama:
     * where Range_Awal <= value order by Range_Awal desc, first).
     */
    private function resolvePerformanceBand($value): ?object
    {
        $bands = Cache::remember('kpi.master_performance.bands.v1', 600, function () {
            return DB::table('KPI_Master_Performance')
                ->select('Range_Awal', 'Keterangan', 'Warna', 'Kode')
                ->orderBy('Range_Awal', 'desc')
                ->get();
        });

        $numeric = (float) $value;
        foreach ($bands as $band) { // sudah terurut Range_Awal DESC
            if ($numeric >= (float) $band->Range_Awal) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dataPerformance = DB::table('KPI_Master_Performance')
            ->orderBy('Range_Akhir', 'DESC')
            ->first();

        // dd($dataPerformance);
        return inertia('KPI/Performance/Dashboard/Staff/StaffPerformanceDashboardPage', ['warnaNetral' => $dataPerformance->Warna]);
    }

    private function calculateFormulaStream($actual, $target, $weight, $idMasterFormula, $formulas)
    {
        // Terpusat di KpiScoringService (acuan kanonik). Perilaku identik.
        return app(\App\Services\KPI\KpiScoringService::class)
            ->scoreParameterById($actual, $target, $weight, $idMasterFormula, $formulas);
    }

    public function getDataInformasiByUserId(Request $request)
    {
        // dd($request->all());
        try {

            $pengguna = Auth::user();
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 5);

            $bulan = $request->input('bulan');
            $tahun = $request->input('tahun');

            if (! $tahun || strtolower($tahun) === 'all' || ! $bulan) {
                // Periode bawaan HARUS memakai saringan yang sama dengan seluruh
                // isi halaman di bawahnya. Tanpa Flag_Aktif + Status NULL, baris
                // yang sudah DIBATALKAN ikut menentukan periode yang dibuka —
                // lalu setiap kueri berikutnya (yang memang menyaringnya) tidak
                // menemukan apa pun, dan layar menampilkan "N/A" beserta nol di
                // semua tempat meski orangnya punya nilai terverifikasi.
                //
                // Terjadi pada A4: satu-satunya baris periode 2026-09 sudah
                // dibatalkan, tetapi ia tetap terpilih sebagai periode bawaan
                // sehingga dashboard pribadinya tampak kosong sama sekali.
                // Pengajuan induknya ikut dituntut hidup, sama seperti $filteredQuery
                // di bawah. Periode yang pengajuannya sudah digantikan tetap punya
                // header terverifikasi, tetapi parameternya tak bisa lagi diambil —
                // hasilnya halaman terbuka pada periode yang pasti kosong.
                $latestPeriod = DB::table('KPI_Update_Performance as up')
                    ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
                    ->where('up.Id_User', $pengguna->Id_Users)
                    ->where('up.Flag_Approval', 'Y')
                    ->where('up.Flag_Verifikasi', 'Y')
                    ->where('up.Flag_Aktif', 'Y')
                    ->whereNull('up.Status')
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('p.Flag_Approval', 'Y')
                    ->whereNull('p.Status')
                    ->orderBy('up.Periode', 'desc')
                    ->value('up.Periode');

                if ($latestPeriod) {
                    $latestDate = Carbon::createFromFormat('Y-m', $latestPeriod);
                    if (! $tahun || strtolower($tahun) === 'all') {
                        $tahun = $latestDate->year;
                    }
                    if (! $bulan) {
                        $bulan = $latestDate->format('m');
                    }
                } else {
                    if (! $tahun || strtolower($tahun) === 'all') {
                        $tahun = date('Y');
                    }
                    if (! $bulan) {
                        $bulan = date('m');
                    }
                }
            }

            $safeBulan = $bulan ? strtolower($bulan) : null;
            $safeTahun = $tahun ? strtolower($tahun) : null;

            $BaseChart = DB::table('KPI_Update_Performance')
                ->join('Karyawan as k', 'KPI_Update_Performance.Id_User', '=', 'k.UserID_Web')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.ID_Level_Jabatan')
                ->whereNull('KPI_Update_Performance.Status')
                ->where('KPI_Update_Performance.Status_Pengajuan', 'disetujui')
                ->where('KPI_Update_Performance.Flag_Approval', 'Y')
                ->where('KPI_Update_Performance.Id_User', $pengguna->Id_Users)
                // Tanpa Flag_Aktif, header lama yang sudah digantikan revisi ikut terhitung
                // (penggantian hanya menyetel Flag_Aktif='T', Status tetap NULL).
                ->where('KPI_Update_Performance.Flag_Aktif', 'Y')
                ->where('KPI_Update_Performance.Flag_Verifikasi', 'Y');

            // YTD SECTION
            $selectedYear = (int) ($safeTahun ?? date('Y'));

            // Batas bulan YTD mengikuti PENJADWALAN pengisian update performance
            // (Flag_Penilaian_KPI), bukan sekadar kalender: kalau bulan berjalan-1 belum
            // terjadwal, mundur ke bulan terjadwal terakhir. 0 = belum ada → YTD kosong.
            $kodeKaryawan = DB::table('Karyawan')
                ->where('UserID_Web', $pengguna->Id_Users)
                ->value('Kode_Karyawan');
            $YTDMonth = KpiScheduleHelper::resolveYtdCapMonth($selectedYear, $kodeKaryawan);

            // Format ke 2 digit
            $safeYTDMonth = str_pad($YTDMonth, 2, '0', STR_PAD_LEFT);
            // dd($safeYTDMonth);

            // capMonth: 'all' (Full-Year) = 12 bulan penuh; 'ytd' = sampai bulan berjalan
            $capMonth = ($safeBulan === 'ytd') ? $safeYTDMonth : '12';

            $filteredQuery = DB::table('KPI_Pengajuan_Detail as pd')
                ->join('KPI_Pengajuan as p', function ($join) {
                    $join->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')
                        ->where('p.Flag_Approval', 'Y')
                        ->whereNull('p.Status');
                })
                // Scope pengajuan ke tahun terpilih (Tahun ada di KPI_Master_Penjadwalan, bukan KPI_Pengajuan)
                ->join('KPI_Master_Penjadwalan as mp', function ($join) use ($safeTahun) {
                    $join->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                        ->where('mp.Tahun', (string) $safeTahun);
                })
                ->leftJoin('KPI_Pengajuan_Det as pdet', function ($join) use ($safeTahun, $safeBulan, $safeYTDMonth) {
                    $join->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail');
                    // SARGABLE: Periode 'YYYY-MM' → prefix/exact agar bisa index seek.
                    // LEFT/RIGHT(kolom) membungkus kolom = non-sargable (paksa scan).
                    $join->where('pdet.Periode', 'like', $safeTahun.'-%');

                    if ($safeBulan === 'ytd') {
                        $join->whereBetween('pdet.Periode', [$safeTahun.'-01', $safeTahun.'-'.$safeYTDMonth]);
                    } elseif ($safeBulan && $safeBulan !== 'all') {
                        $join->where('pdet.Periode', $safeTahun.'-'.$safeBulan);
                    }
                })
                ->leftJoin('KPI_Update_Performance_Detail as upd', function ($join) {
                    $join->on('upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det');
                })
                ->join('KPI_Update_Performance as up', function ($join) {
                    $join->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                        ->where('up.Flag_Aktif', 'Y')
                        ->where('up.Flag_Approval', 'Y')
                        ->where('up.Flag_Verifikasi', 'Y')
                        ->whereNull('up.Status');
                })
                ->join('KPI_Master_Target as mt', 'mt.Id_Master_Target', 'pd.Id_Master_Target')
                ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', 'pd.Id_Master_Formula')
                ->join('KPI_Master_Bsc_Kategori as mbk', 'mbk.Id_Master_Bsc_Kategori', 'pd.Id_Bsc_Kategori')
                ->join('KPI_Master_Satuan as  ms', 'ms.Id_Master_Satuan', 'pd.Id_Master_Satuan')
                ->where('p.Id_User', $pengguna->Id_Users);

            // Skor akumulasi (dan jepitannya per parameter) dari KpiAkumulasiYtdService.

            // Builder kumulatif: agregasi Σtarget/Σactual sampai bulan tertentu (cap)
            $buildCumulativeQuery = function ($capBulan) use ($safeTahun, $pengguna) {
                return DB::table('KPI_Pengajuan_Detail as pd')
                    ->join('KPI_Pengajuan as p', function ($join) {
                        $join->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                            ->where('p.Flag_Aktif', 'Y')
                            ->where('p.Flag_Approval', 'Y')
                            ->whereNull('p.Status');
                    })
                    // Scope pengajuan ke tahun terpilih (Tahun ada di KPI_Master_Penjadwalan)
                    ->join('KPI_Master_Penjadwalan as mp', function ($join) use ($safeTahun) {
                        $join->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                            ->where('mp.Tahun', (string) $safeTahun);
                    })
                    ->leftJoin('KPI_Pengajuan_Det as pdet', function ($join) use ($safeTahun, $capBulan) {
                        $join->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail');
                        // SARGABLE: (tahun AND bulan<=cap) untuk 'YYYY-MM' setara BETWEEN
                        // 'YYYY-01'..'YYYY-cap' (urutan leksikal = kronologis).
                        $join->whereBetween('pdet.Periode', [$safeTahun.'-01', $safeTahun.'-'.$capBulan]);
                    })
                    ->leftJoin('KPI_Update_Performance_Detail as upd', function ($join) {
                        $join->on('upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det');
                    })
                    ->join('KPI_Update_Performance as up', function ($join) {
                        $join->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                            ->where('up.Flag_Aktif', 'Y')
                            ->where('up.Flag_Approval', 'Y')
                            ->where('up.Flag_Verifikasi', 'Y')
                            ->whereNull('up.Status');
                    })
                    ->join('KPI_Master_Target as mt', 'mt.Id_Master_Target', 'pd.Id_Master_Target')
                    ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', 'pd.Id_Master_Formula')
                    ->join('KPI_Master_Bsc_Kategori as mbk', 'mbk.Id_Master_Bsc_Kategori', 'pd.Id_Bsc_Kategori')
                    ->join('KPI_Master_Satuan as  ms', 'ms.Id_Master_Satuan', 'pd.Id_Master_Satuan')
                    ->where('p.Id_User', $pengguna->Id_Users);
            };

            // Angka akumulasi (agregat utama & titik YTD grafik) dari SATU sumber.
            // Kueri kumulatif di bawah tetap dipakai untuk IDENTITAS baris
            // (nama, satuan, kategori); angkanya diambil dari layanan ini.
            $ytdSvc = app(KpiAkumulasiYtdService::class);
            // Agregat utama: Full-Year (12) atau YTD (bulan berjalan)
            $cumulativeMainQuery = $buildCumulativeQuery($capMonth);

            $recentKpiQuery = clone $filteredQuery;

            if ($safeBulan && ! in_array($safeBulan, ['all', 'ytd'], true)) {
                $resultKPI = $recentKpiQuery
                    ->select(
                        // perbaikan untuk detail
                        'pd.Id_Pengajuan',
                        'upd.Id_Update_Performance',
                        // akhir perbaikan detail
                        'pd.Id_Pengajuan_Detail',
                        'pd.Bobot',
                        // PERBAIKAN: Gunakan MAX(CAST(...)) untuk kolom text/ntext
                        DB::raw('MAX(CAST(pd.Alasan_Tolak AS VARCHAR(MAX))) AS Alasan_Tolak'),
                        DB::raw('MAX(CAST(pd.Deskripsi AS VARCHAR(MAX))) AS Deskripsi'),
                        'pd.Nama_Parameter as name',
                        DB::raw('COALESCE(upd.Nilai_Actual, 0) AS Total_Actual'),
                        DB::raw('COALESCE(pdet.Nilai_Target, 0) AS Total_Target'),
                        DB::raw('COALESCE(upd.Achievement, 0) AS Achievement'),
                        DB::raw('COALESCE(upd.score, 0) AS score'),
                        DB::raw('MAX(CAST(mf.Rumus AS VARCHAR(MAX))) AS Rumus'),
                        'mf.Id_Master_Formula',
                        'ms.Simbol_Satuan',
                        'mbk.Nama_Kategori',
                        'ms.Format_Input',
                        'mt.Nama_Target'
                    )
                    ->groupBy(
                        // perbaikan untuk detail
                        'pd.Id_Pengajuan',
                        'upd.Id_Update_Performance',
                        // akhir perbaikan detail
                        'pd.Id_Pengajuan_Detail',
                        'pd.Nama_Parameter',
                        'pd.Bobot',
                        // 'pd.Alasan_Tolak',  <-- DIHAPUS DARI GROUP BY
                        // 'pd.Deskripsi',    <-- DIHAPUS DARI GROUP BY
                        'upd.Nilai_Actual',
                        'upd.Achievement',
                        'pdet.Nilai_Target',
                        'upd.score',
                        'ms.Format_Input',
                        'mf.Id_Master_Formula',
                        'ms.Simbol_Satuan',
                        'mbk.Nama_Kategori',
                        'mt.Nama_Target'
                    )
                    ->get()
                    ->map(function ($item) {
                        // CATATAN kolom DB (nama tertukar dgn isinya, lihat UpdatePerformanceController@store):
                        //   kolom `Score`       = ratio×100  (achievement %)
                        //   kolom `Achievement` = Bobot×ratio (weighted score)
                        // Frontend expects: $item->score = achievement % (progress bar + display)
                        //                  $item->achievement = Bobot×ratio (internal, utk widget)
                        $achievementPct = round((float) ($item->score ?? 0), 2);   // kolom `score` = achievement %
                        $weightedScore = (float) ($item->Achievement ?? 0);        // kolom `Achievement` = weighted

                        // Ambil data Keterangan dari KPI_Master_Performance berdasarkan achievement %
                        $keterangan = $this->resolvePerformanceBand($achievementPct);

                        $item->Keterangan = $keterangan->Keterangan ?? 'Belum Ada Rentang';
                        $item->Warna = $keterangan->Warna ?? '#999999';

                        // Setelah swap: $item->score = achievement % (utk frontend), $item->achievement = bobot×ratio
                        $item->achievement = $weightedScore;
                        $item->score = $achievementPct;
                        $item->Id_Update_Performance = Hashids::connection('custom')->encode($item->Id_Update_Performance);
                        $item->Id_Pengajuan = Hashids::connection('custom')->encode($item->Id_Pengajuan);

                        // Baris dari query utama pasti punya baris target di periode ini
                        // (lolos INNER JOIN `up` yang mensyaratkan `pdet` non-null) → AKTIF.
                        $item->is_active = true;

                        return $item;
                    });

                $widgetQuery = clone $filteredQuery;
                $latestPerformanceData = $widgetQuery
                    ->join('KPI_Master_Performance as kmp', function ($join) {
                        $join->where('kmp.Range_Awal', '<=', DB::raw('up.Total_Score'))
                            ->where('kmp.Range_Akhir', '>=', DB::raw('up.Total_Score'));
                    })
                    ->select(
                        'up.Total_Score',
                        'kmp.Keterangan as Performance_Keterangan',
                        'kmp.Warna as Performance_Warna'
                    )
                    ->orderBy('up.Periode', 'desc')
                    ->orderBy('up.Tanggal', 'desc')
                    ->first();

            } else {
                $rincianUtama = (int) $capMonth > 0
                    ? ($ytdSvc->rincianPerUser(
                        [$pengguna->Id_Users],
                        $selectedYear,
                        $ytdSvc->periodeSampai($selectedYear, (int) $capMonth),
                    )[(string) $pengguna->Id_Users] ?? null)
                    : null;
                $rincianUtamaPerParam = collect($rincianUtama['parameter'] ?? [])->keyBy(fn ($p) => (string) $p['id']);

                $resultKPI = $cumulativeMainQuery
                    ->select(
                        // perbaikan untuk detail
                        'pd.Id_Pengajuan',
                        DB::raw('0 AS Id_Update_Performance'),
                        // akhir perbaikan detail
                        'pd.Id_Pengajuan_Detail',
                        'pd.Nama_Parameter as name',
                        // Sama dengan mode bulanan: teks tidak bisa masuk GROUP BY.
                        DB::raw('MAX(CAST(pd.Deskripsi AS VARCHAR(MAX))) AS Deskripsi'),
                        DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) AS Total_Actual'),

                        // Ambil semua target (full year)
                        DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) AS Total_Target'),

                        // Backup total target penuh (bisa dihapus jika redundant)
                        DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) AS Total_Target2'),

                        'pd.Bobot',
                        DB::raw('MAX(CAST(mf.Rumus AS VARCHAR(MAX))) AS Rumus'),
                        'mf.Id_Master_Formula',
                        'ms.Simbol_Satuan',
                        'ms.Format_Input',
                        'mbk.Nama_Kategori',
                        'mt.Nama_Target',
                    )
                    ->groupBy(
                        'pd.Id_Pengajuan',

                        'pd.Id_Pengajuan_Detail',
                        'pd.Nama_Parameter',
                        'mf.Id_Master_Formula',
                        'pd.Bobot',
                        'ms.Format_Input',
                        'mf.Id_Master_Formula',
                        'ms.Simbol_Satuan',
                        'mbk.Nama_Kategori',
                        'mt.Nama_Target',
                    )
                    ->get()
                    ->map(function ($item) use ($rincianUtamaPerParam) {
                        // Angka per parameter dari KpiAkumulasiYtdService (jepit PER
                        // PARAMETER 50–125). Awas penamaan terbalik di layar ini:
                        // $item->achievement = skor berbobot, $item->score = capaian %.
                        $rincian = $rincianUtamaPerParam->get((string) $item->Id_Pengajuan_Detail);
                        if ($rincian) {
                            $item->Total_Target = $rincian['sum_target'];
                            $item->Total_Actual = $rincian['sum_actual'];
                            $item->achievement = (float) $rincian['score'];
                            $item->score = (float) $rincian['achievement'];
                        } else {
                            $item->achievement = 0.0;
                            $item->score = 0.0;
                        }
                        // dd($item->achievement   );

                        // Ambil level performance
                        $keterangan = $this->resolvePerformanceBand($item->score);

                        $item->Keterangan = $keterangan->Keterangan ?? 'Belum Ada Rentang';
                        $item->Warna = $keterangan->Warna ?? '#999999';

                        $item->Id_Update_Performance = Hashids::connection('custom')->encode($item->Id_Update_Performance);
                        $item->Id_Pengajuan = Hashids::connection('custom')->encode($item->Id_Pengajuan);

                        // Baris dari query utama pasti punya baris target dalam rentang → AKTIF.
                        $item->is_active = true;

                        return $item;
                    });

                // $resultKPI = $recentKpiQuery
                // ->select(
                //     // perbaikan untuk detail
                //     'pd.Id_Pengajuan',
                //     DB::raw('0 AS Id_Update_Performance'),
                //     // akhir perbaikan detail
                //     'pd.Id_Pengajuan_Detail',
                //     'pd.Nama_Parameter as name',
                //     DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) AS Total_Actual'),

                //     // Ambil semua target (full year)
                //     DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) AS Total_Target'),

                //     // Backup total target penuh (bisa dihapus jika redundant)
                //     DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) AS Total_Target2'),

                //     'pd.Bobot',
                //     DB::raw('MAX(CAST(mf.Rumus AS VARCHAR(MAX))) AS Rumus'),
                //     'mf.Id_Master_Formula',
                //     'ms.Simbol_Satuan',
                //     'ms.Format_Input',
                //     'mbk.Nama_Kategori',
                //     'mt.Nama_Target',
                // )
                // ->groupBy(
                //     // perbaikan untuk detail
                //     'pd.Id_Pengajuan',
                //     // 'upd.Id_Update_Performance',
                //     // akhir perbaikan detail
                //     'pd.Id_Pengajuan_Detail',
                //     'pd.Nama_Parameter',
                //     'mf.Id_Master_Formula',
                //     'pd.Bobot',
                //     'ms.Format_Input',
                //     'mf.Id_Master_Formula',
                //     'ms.Simbol_Satuan',
                //     'mbk.Nama_Kategori',
                //     'mt.Nama_Target',
                // )
                // ->get()
                // ->map(function ($item) {
                //     // Hitung achievement & score
                //     if ($item->Total_Target > 0) {
                //         // $achievement = ($item->Total_Actual / $item->Total_Target) * 100;
                //         // $score = $achievement * ($item->Bobot / 100);
                //             $formulas = [
                //             $item->Id_Master_Formula => $item->Rumus
                //         ];
                //         $formulas;
                //         $calc = $this->calculateFormulaStream($item->Total_Actual, $item->Total_Target, $item->Bobot, $item->Id_Master_Formula, $formulas);

                //         $achievement = $calc['score'];
                //         $score = $calc['achievement'];
                //     } else {
                //         $achievement = 0;
                //         $score = 0;
                //     }

                //     $item->achievement = (float) $achievement;
                //     $item->score = (float ) $score;
                //     // dd($item->achievement   );

                //     // Ambil level performance
                //     $keterangan = DB::table('KPI_Master_Performance as z')
                //         ->where('z.Range_Awal', '<=', $item->score)
                //         ->where('z.Range_Akhir', '>=', $item->score)
                //         ->select('z.Keterangan', 'z.Warna')
                //         ->first();

                //     $item->Keterangan = $keterangan->Keterangan ?? 'Belum Ada Rentang';
                //     $item->Warna = $keterangan->Warna ?? '#999999';
                //     $item->Id_Update_Performance = Hashids::connection('custom')->encode($item->Id_Update_Performance);
                //     $item->Id_Pengajuan = Hashids::connection('custom')->encode($item->Id_Pengajuan);

                //     return $item;
                // });

                // Total dari sumber yang sama dengan rinciannya.
                $totalAchievement = (float) ($rincianUtama['skor'] ?? 0);
                // dd($resultKPI,$totalBobot, $totalAchievement);
                $performanceLevel = DB::table('KPI_Master_Performance as kmp')
                    ->where('kmp.Range_Awal', '<=', $totalAchievement)
                    ->where('kmp.Range_Akhir', '>=', $totalAchievement)
                    ->select('kmp.Keterangan', 'kmp.Warna')
                    ->first();

                $latestPerformanceData = (object) [
                    'Total_Score' => $totalAchievement ?? 0,
                    'Performance_Keterangan' => $performanceLevel->Keterangan ?? 'N/A',
                    'Performance_Warna' => $performanceLevel->Warna ?? '#000000',
                ];

            }
            $performanceWidget = null;
            $achievementWidget = 0.0;
            // dd($latestPerformanceData);
            if ($latestPerformanceData) {
                $performanceWidget = [
                    'Keterangan' => $latestPerformanceData->Performance_Keterangan,
                    'Warna' => $latestPerformanceData->Performance_Warna,
                ];
                $achievementWidget = (float) $latestPerformanceData->Total_Score;
            } else {
                $performanceWidget = ['Keterangan' => 'N/A', 'Warna' => 'rgba(128, 128, 128, 1)'];
            }

            // 🔹 PICA Data
            $pica = clone $filteredQuery;
            $pica
                ->join('KPI_Update_Performance_Det as updet', 'updet.Id_Update_Performance_Detail', '=', 'upd.Id_Update_Performance_Detail')
                ->select(
                    'pd.Id_Pengajuan_Detail',
                    'upd.Id_Update_Performance_Detail',
                    'up.Periode',
                    'pd.Nama_Parameter',
                    'updet.Identifikasi',
                    'updet.Koreksi_Aksi',
                    'upd.Score',
                    'updet.Tanggal_Pelaksanaan'
                )
                ->orderBy('up.Periode', 'desc')
                ->orderBy('updet.Tanggal_Pelaksanaan', 'desc');

            $paginatedData = $pica->paginate($limit, ['*'], 'page', $page);
            // dd($paginatedData);
            // 🔹 Transform Data
            $transformedItems = $paginatedData->through(function ($item) {
                $formattedPeriode = '';
                if ($item->Periode) {
                    try {
                        $formattedPeriode = 'Periode '.Carbon::createFromFormat('Y-m', $item->Periode)->format('F Y');
                    } catch (\Exception $e) {
                        $formattedPeriode = 'Periode '.$item->Periode;
                    }
                }

                if ($item->Score != 0) {
                    $performanceLevelPica = DB::table('KPI_Master_Performance as kmp')
                        ->where('kmp.Range_Awal', '<=', $item->Score)
                        ->where('kmp.Range_Akhir', '>=', $item->Score)
                        ->select('kmp.Keterangan', 'kmp.Warna')
                        ->first();

                    if ($performanceLevelPica) {
                        $item->Keterangan = $performanceLevelPica->Keterangan;
                        $item->Warna = $performanceLevelPica->Warna;
                    }
                }

                // dd($item->Keterangan , $item->Warna);
                return [
                    'id' => bin2hex(random_bytes(16)).Str::random(16),
                    'Id_Update_Performance_Detail' => Hashids::connection('custom')->encode($item->Id_Update_Performance_Detail),
                    'Id_Pengajuan_Detail' => Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail),
                    'name' => $item->Nama_Parameter,
                    'achievement' => round($item->achievement ?? 0, 1),
                    'Keterangan' => $item->Keterangan ?? 'NOT PERFORM',
                    'Warna' => $item->Warna ?? '#999999',
                    'identifikasi' => $item->Identifikasi,
                    'koreksi_aksi' => $item->Koreksi_Aksi,
                    'Tanggal_Pelaksanaan' => $item->Tanggal_Pelaksanaan,
                    'periode' => $formattedPeriode,
                ];
            });

            // dd($transformedDetails);

            $resultRecent = [
                'data' => $transformedItems->items(),
                'page' => $paginatedData->currentPage(),
                'total_page' => $paginatedData->lastPage(),
                'total_data' => $paginatedData->total(),
            ];

            // 🔹 Chart Line
            $chartLineQuery = clone $BaseChart;
            $monthlyPerformance = $chartLineQuery
                ->select(
                    DB::raw('RIGHT(KPI_Update_Performance.Periode, 2) as month'),
                    DB::raw('Total_Score as average_score'),
                    DB::raw(
                        'ROW_NUMBER() OVER (
                            PARTITION BY RIGHT(KPI_Update_Performance.Periode, 2)
                            ORDER BY Total_Score DESC
                        ) as rn'
                    )
                )
                ->whereRaw('LEFT(KPI_Update_Performance.Periode, 4) = ?', [$safeTahun])
                ->get()
                ->where('rn', 1)
                ->keyBy('month');

            // $chartLine = ['labels' => [], 'series' => []];
            // if ($monthlyPerformance->isNotEmpty()) {
            //     $latestMonth = (int) $monthlyPerformance->keys()->max();
            //     $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            //     for ($i = 1; $i <= $latestMonth; $i++) {
            //         $monthKey = sprintf('%02d', $i);
            //         $chartLine['labels'][] = $monthNames[$i - 1];
            //         $score = isset($monthlyPerformance[$monthKey])
            //             ? round($monthlyPerformance[$monthKey]->average_score, 1)
            //             : 0;
            //         $chartLine['series'][] = (fmod($score, 1) === 0.0) ? (int)$score : $score;
            //     }
            //     $totalCountYtd = round($YTDcalc->sum('achievement'), 1);
            //        if ($totalCountYtd > 0) {
            //             $chartLine['labels'][] = 'YTD';
            //             $chartLine['series'][] = $totalCountYtd;
            //         }
            // }

            // Ambil bulan yang ada data saja
            $chartLine = ['labels' => [], 'series' => []];
            // dd($monthlyPerformance);
            if ($monthlyPerformance->isNotEmpty()) {
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

                foreach ($monthlyPerformance as $monthKey => $data) {
                    $monthIndex = (int) $monthKey - 1;
                    $chartLine['labels'][] = $monthNames[$monthIndex] ?? $monthKey;

                    $score = round($data->average_score, 1);
                    $chartLine['series'][] = (fmod($score, 1) === 0.0) ? (int) $score : $score;
                }

                // Titik YTD dari SATU sumber (KpiAkumulasiYtdService), sama dengan
                // Monitoring, dashboard CEO, homepage, dan `kpi:verify-ytd`.
                $totalCountYtd = $YTDMonth > 0
                    ? round((float) ($ytdSvc->skorPerUser(
                        [$pengguna->Id_Users],
                        $selectedYear,
                        $ytdSvc->periodeSampai($selectedYear, $YTDMonth),
                    )[(string) $pengguna->Id_Users] ?? 0), 1)
                    : 0;
                if ($totalCountYtd > 0) {
                    $chartLine['labels'][] = 'YTD';
                    $chartLine['series'][] = $totalCountYtd;

                    // Bulan yang BENAR-BENAR masuk akumulasi (saringan sama dengan
                    // KpiAkumulasiYtdService), supaya label layar menyebut rentang
                    // dan jumlah bulan yang dihitung — bukan Jan..batas yang bisa
                    // saja sebagian besar kosong.
                    $chartLine['ytd_bulan_terhitung'] = DB::table('KPI_Update_Performance as up')
                        ->join('KPI_Pengajuan as p', function ($j) {
                            $j->on('p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
                                ->where('p.Flag_Aktif', 'Y')
                                ->where('p.Flag_Approval', 'Y')
                                ->whereNull('p.Status');
                        })
                        ->where('up.Id_User', $pengguna->Id_Users)
                        ->where('up.Flag_Aktif', 'Y')
                        ->where('up.Flag_Approval', 'Y')
                        ->where('up.Flag_Verifikasi', 'Y')
                        ->whereNull('up.Status')
                        ->whereIn('up.Periode', $ytdSvc->periodeSampai($selectedYear, $YTDMonth))
                        ->distinct()
                        ->pluck('up.Periode')
                        ->map(fn ($p) => (int) substr($p, 5, 2))
                        ->sort()
                        ->values()
                        ->all();
                    $chartLine['ytd_tahun'] = $selectedYear;
                }
            }

            // dd($recentKpiQuery->toSql());

            // 🔹 Pengajuan "current": pengajuan yang punya update performance TER-APPROVE pada
            // periode terpilih (inilah yang menghasilkan baris di query utama). Dipakai untuk
            // membatasi merge di bawah agar TIDAK menarik pengajuan lama yang kebetulan masih
            // Flag_Aktif='Y' (anomali data: 1 user bisa punya >1 pengajuan aktif utk tahun sama)
            // → mencegah parameter tampil dobel.
            $currentPengajuanIds = DB::table('KPI_Update_Performance as up')
                ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
                ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                ->where('up.Id_User', $pengguna->Id_Users)
                ->where('up.Status_Pengajuan', 'disetujui')
                ->where('up.Flag_Approval', 'Y')
                ->where('up.Flag_Verifikasi', 'Y')
                ->where('up.Flag_Aktif', 'Y')
                ->whereNull('up.Status')
                ->where('mp.Tahun', (string) $safeTahun)
                ->when($safeBulan && ! in_array($safeBulan, ['all', 'ytd'], true),
                    fn ($q) => $q->where('up.Periode', $safeTahun.'-'.$safeBulan))
                ->when(! $safeBulan || in_array($safeBulan, ['all', 'ytd'], true),
                    fn ($q) => $q->where('up.Periode', '<=', $safeTahun.'-'.$capMonth))
                ->distinct()
                ->pluck('p.Id_Pengajuan')
                ->map(fn ($id) => (int) $id)
                ->values();

            // Fallback (belum ada update sama sekali): pakai pengajuan aktif TERBARU utk tahun ini,
            // agar merge tetap menampilkan parameter dari satu pengajuan saja (bukan dobel).
            if ($currentPengajuanIds->isEmpty()) {
                $latestPengajuanId = DB::table('KPI_Pengajuan as p')
                    ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                    ->where('p.Id_User', $pengguna->Id_Users)
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('p.Flag_Approval', 'Y')
                    ->whereNull('p.Status')
                    ->where('mp.Tahun', (string) $safeTahun)
                    ->max('p.Id_Pengajuan');
                if ($latestPengajuanId) {
                    $currentPengajuanIds = collect([(int) $latestPengajuanId]);
                }
            }

            // 🔹 Merge parameter inactive agar tetap muncul di daftar (disabled + "Belum Aktif")
            $allParamIds = DB::table('KPI_Pengajuan_Detail as pd')
                ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                // Scope ke pengajuan tahun terpilih agar parameter tahun lain tidak bocor
                ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                ->where('p.Id_User', $pengguna->Id_Users)
                ->where('p.Flag_Aktif', 'Y')
                ->where('p.Flag_Approval', 'Y')
                ->whereNull('p.Status')
                ->where('mp.Tahun', (string) $safeTahun)
                // Hanya pengajuan current → cegah duplikasi dari pengajuan lama yg masih aktif
                ->when($currentPengajuanIds->isNotEmpty(),
                    fn ($q) => $q->whereIn('p.Id_Pengajuan', $currentPengajuanIds->all()))
                ->pluck('pd.Id_Pengajuan_Detail')
                ->map(fn ($id) => (int) $id);

            $existingIds = $resultKPI->pluck('Id_Pengajuan_Detail')->map(fn ($id) => (int) $id);
            $missingIds = $allParamIds->diff($existingIds);

            if ($missingIds->isNotEmpty()) {
                // Ambil target per periode filter untuk param inactive
                $targetsForMissing = DB::table('KPI_Pengajuan_Det')
                    ->whereIn('Id_Pengajuan_Detail', $missingIds->values())
                    ->where('Periode', 'like', $safeTahun.'-%')
                    ->when($safeBulan && ! in_array($safeBulan, ['all', 'ytd'], true),
                        fn ($q) => $q->where('Periode', $safeTahun.'-'.$safeBulan))
                    ->when(! $safeBulan || in_array($safeBulan, ['all', 'ytd'], true),
                        fn ($q) => $q->where('Periode', '<=', $safeTahun.'-'.$capMonth))
                    ->groupBy('Id_Pengajuan_Detail')
                    ->select('Id_Pengajuan_Detail', DB::raw('SUM(Nilai_Target) as Total_Target'))
                    ->pluck('Total_Target', 'Id_Pengajuan_Detail');

                $missingParams = DB::table('KPI_Pengajuan_Detail as pd')
                    ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                    ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                    ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                    ->leftJoin('KPI_Master_Bsc_Kategori as mbk', 'mbk.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                    ->leftJoin('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'pd.Id_Master_Target')
                    ->whereIn('pd.Id_Pengajuan_Detail', $missingIds->values())
                    ->select(
                        'pd.Id_Pengajuan_Detail',
                        'pd.Nama_Parameter',
                        DB::raw('CAST(pd.Deskripsi AS VARCHAR(MAX)) AS Deskripsi'),
                        'pd.Bobot',
                        'pd.Id_Master_Formula',
                        'p.Id_Pengajuan',
                        'mf.Rumus',
                        'ms.Simbol_Satuan',
                        'ms.Format_Input',
                        'mbk.Nama_Kategori',
                        'mt.Nama_Target',
                    )
                    ->get()
                    ->map(function ($param) use ($targetsForMissing) {
                        $paramTarget = (float) ($targetsForMissing[$param->Id_Pengajuan_Detail] ?? 0);

                        // AKTIF = parameter punya baris target di periode ini. Key pada
                        // $targetsForMissing (hasil groupBy) = adanya baris target, walau nilainya 0.
                        // Pakai ->has() (bukan isset) agar aman bila SUM(Nilai_Target) NULL.
                        $hasTargetPeriode = $targetsForMissing->has($param->Id_Pengajuan_Detail);

                        // Baris merge = parameter yang BELUM ada update performance ter-approve di
                        // periode ini. Karena belum dinilai, JANGAN hitung rumus (Actual=0 pada
                        // rumus meta-nol IF(Actual==0,1,0) akan keliru jadi 100%). Tampilkan skor 0
                        // / band terendah (NOT PERFORM) — konsisten dgn "belum ada penilaian".
                        // Skor asli baru muncul setelah user mengisi & di-approve (lewat query utama).
                        $band = $hasTargetPeriode ? $this->resolvePerformanceBand(0) : null;

                        return (object) [
                            'Id_Pengajuan' => Hashids::connection('custom')->encode($param->Id_Pengajuan),
                            'Id_Update_Performance' => Hashids::connection('custom')->encode(0),
                            'Id_Pengajuan_Detail' => $param->Id_Pengajuan_Detail,
                            'name' => $param->Nama_Parameter,
                            'Deskripsi' => $param->Deskripsi,
                            'Bobot' => (float) $param->Bobot,
                            'Total_Target' => $paramTarget,
                            'Total_Target2' => $paramTarget,
                            'Total_Actual' => 0,
                            'achievement' => 0,
                            'score' => 0,
                            'is_active' => $hasTargetPeriode,
                            'Keterangan' => $hasTargetPeriode ? ($band?->Keterangan ?? 'Belum Ada Rentang') : 'Belum Aktif',
                            'Warna' => $hasTargetPeriode ? ($band?->Warna ?? '#999999') : '#9ca3af',
                            'Rumus' => $param->Rumus ?? '',
                            'Id_Master_Formula' => $param->Id_Master_Formula,
                            'Simbol_Satuan' => $param->Simbol_Satuan ?? '',
                            'Format_Input' => $param->Format_Input ?? '',
                            'Nama_Kategori' => $param->Nama_Kategori ?? '',
                            'Nama_Target' => $param->Nama_Target ?? '',
                        ];
                    });

                $resultKPI = $resultKPI->concat($missingParams);
            }

            // 🔹 Final Result
            $resultAkhir = [
                'Widget' => [
                    'Performance' => $performanceWidget,
                    'Achievement' => round($achievementWidget, 1),
                ],
                'kpis' => $resultKPI,
                'Chart_Line' => $chartLine,
                'Recent_Kpi_Update' => $resultRecent,
                'selectedMonth' => $bulan,
                'selectedYear' => $tahun,
                'is_full_year' => ($safeBulan && ! in_array($safeBulan, ['all', 'ytd'], true)) ? false : true,
                'is_ytd' => $safeBulan === 'ytd',
            ];

            return ResponseHelper::success($resultAkhir, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('dashboardStaffController')->error('Error STORE: '.$e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // public function recentKpiUserDetailMyPerfomance($id_pengajuan, $id_update_performance)
    // {
    //     $pengguna = Auth::user();
    //     $decodedPengajuanId = Hashids::connection('custom')->decode($id_pengajuan);
    //     $decodedUpdatePerformanceId = Hashids::connection('custom')->decode($id_update_performance);

    //     if (empty($decodedPengajuanId) || empty($decodedUpdatePerformanceId)) {
    //         return ResponseHelper::error("ID Pengajuan atau ID Update Performance tidak valid.", 400);
    //     }

    //     $pengajuanId = $decodedPengajuanId[0];
    //     $updatePerformanceId = $decodedUpdatePerformanceId[0];

    //     $performanceHeader = DB::table('KPI_Update_Performance as kup')
    //         ->join('KPI_Master_Performance as kmp', 'kup.Id_Performance', '=', 'kmp.Id_Master_Performance')
    //         ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
    //         ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.Id_Level_Jabatan')
    //         ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
    //         ->select(
    //             'kup.*',
    //             'kmp.Keterangan as Status_Performance',
    //             'kmp.Warna',
    //             'k.Nama as Nama_Lengkap',
    //             'k.Email',
    //             'k.HP as Kontak',
    //             'vgsglj.nama_jabatan',
    //             'vdss.nama_sub_divisi'
    //         )
    //         ->where('kup.Id_Update_Performance', $updatePerformanceId)
    //         ->where('kup.Id_Pengajuan', $pengajuanId)
    //         ->where('kup.Id_User', $pengguna->Id_Users)
    //         ->first();

    //     if (!$performanceHeader) {
    //         return ResponseHelper::error("Data performance KPI tidak ditemukan.", 404);
    //     }

    //     $periode = Carbon::parse($performanceHeader->Periode);
    //     $periodeBulan = $periode->month;
    //     $periodeTahun = $periode->year;

    //     $allDetails = DB::table('KPI_Pengajuan_Detail as kpi_main')
    //         ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
    //         ->leftJoin('KPI_Pengajuan_Det as kpd', function ($join) use ($periodeBulan, $periodeTahun) {
    //             $join->on('kpi_main.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
    //                 ->whereRaw('LEFT(kpd.Periode, 4) = ?', [$periodeTahun])
    //                 ->whereRaw('SUBSTRING(kpd.Periode, 6, 2) = ?', [str_pad($periodeBulan, 2, '0', STR_PAD_LEFT)]);
    //         })
    //         ->leftJoin('KPI_Update_Performance_Detail as kupd', function ($join) use ($updatePerformanceId) {
    //             $join->on('kpd.Id_Pengajuan_Det', '=', 'kupd.Id_Pengajuan_Det')
    //                 ->where('kupd.Id_Update_Performance', $updatePerformanceId);
    //         })
    //         ->where('kpi_main.Id_Pengajuan', $pengajuanId)
    //         ->select(
    //             'kpi_main.Id_Pengajuan_Detail',
    //             'kpi_main.Nama_Parameter',
    //             'kpi_main.Bobot',
    //             'kms.Simbol_Satuan',
    //             'kpd.Id_Pengajuan_Det',
    //             'kpd.Nilai_Target',
    //             'kupd.Id_Update_Performance_Detail',
    //             'kupd.Nilai_Actual',
    //             'kupd.Score',
    //             'kupd.Achievement',
    //             'kupd.File_Berkas',
    //             'kupd.Alasan_Reject',
    //             'kupd.Flag_Pica'
    //         )
    //         ->get();

    //     $transformedDetails = $allDetails->map(function($item) {
    //         $newItem = new \stdClass();

    //         $isInputPeriod = !is_null($item->Id_Pengajuan_Det);
    //         $wasSubmitted = !is_null($item->Id_Update_Performance_Detail);

    //         $newItem->id = $wasSubmitted
    //             ? Hashids::connection('custom')->encode($item->Id_Update_Performance_Detail)
    //             : ($isInputPeriod
    //                 ? Hashids::connection('custom')->encode($item->Id_Pengajuan_Det)
    //                 : Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail));

    //         $newItem->parameter = $item->Nama_Parameter;
    //         $newItem->Bobot = (float) $item->Bobot;
    //         $newItem->targetValue = $isInputPeriod ? (float) $item->Nilai_Target : null;
    //         $newItem->targetUnit = $item->Simbol_Satuan;
    //         $newItem->actual = $wasSubmitted ? (float) $item->Nilai_Actual : null;
    //         $newItem->achievement = $wasSubmitted ? (float) $item->Achievement : null;
    //         $newItem->score = $wasSubmitted ? (float) $item->Score : null;
    //         $newItem->is_input_period = $isInputPeriod;
    //         $newItem->dataSourceFile = $wasSubmitted && !empty($item->File_Berkas) ? (object)['name' => $item->File_Berkas] : null;
    //         $newItem->File_Berkas_Url = $wasSubmitted && !empty($item->File_Berkas) ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20)) : null;
    //         $newItem->isRejected = $wasSubmitted && !is_null($item->Alasan_Reject);
    //         $newItem->alasan_penolakan = $wasSubmitted ? $item->Alasan_Reject : null;
    //         $newItem->pica = null;
    //         if ($wasSubmitted && $item->Flag_Pica === 'Y') {
    //             $picaData = DB::table('KPI_Update_Performance_Det')->where('Id_Update_Performance_Detail', $item->Id_Update_Performance_Detail)->first();
    //             if ($picaData) {
    //                 $newItem->pica = [
    //                     'problem' => $picaData->Identifikasi,
    //                     'action' => $picaData->Koreksi_Aksi,
    //                     'targetDate' => FormatTanggalHelper::format($picaData->Tanggal_Pelaksanaan, true),
    //                 ];
    //             }
    //         }
    //         return $newItem;
    //     });

    //     $periode = $performanceHeader->Periode;
    //     $periodeFormatted = '';

    //     if (!empty($periode)) {
    //         try {
    //             $date = Carbon::createFromFormat('Y-m', $periode);
    //             $periodeFormatted = 'Periode ' . $date->format('M Y');
    //         } catch (\Exception $e) {
    //             $periodeFormatted = 'Periode Tidak Valid';
    //         }
    //     }
    //     $formattedHeader = (object)[
    //         'Nama_Lengkap' => $performanceHeader->Nama_Lengkap,
    //         'Nama_Jabatan' => $performanceHeader->nama_jabatan,
    //         'Nama_Departemen' => $performanceHeader->nama_sub_divisi,
    //         'Status_Pengajuan' => $performanceHeader->Status_Pengajuan,
    //         'Achievement' => (float) $performanceHeader->Total_Score,
    //         'Status' => $performanceHeader->Status_Performance,
    //         'Warna' => $performanceHeader->Warna,
    //         'Email' => $performanceHeader->Email,
    //         'Kontak' => $performanceHeader->Kontak,
    //         'Jam' => $performanceHeader->Jam,
    //         'Tanggal' => $performanceHeader->Tanggal,
    //         'Flag_Aktif' => $performanceHeader->Flag_Aktif,
    //         'Periode' => $periodeFormatted,
    //         'Flag_Pindah' => 'Y',
    //     ];

    //     return ResponseHelper::success(['header' => $formattedHeader, 'details' => $transformedDetails], "Data KPI Detail Ditemukan!", 200);
    // }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function recentKpiUserDetail($id_pengajuan, $id_update_performance, $isyear)
    {
        // dd($id_pengajuan, $id_update_performance, $isyear);
        try {
            $isYtd = is_string($isyear) && strtolower($isyear) === 'ytd';
            $isYearBool = $isYtd ? true : filter_var($isyear, FILTER_VALIDATE_BOOLEAN);

            $decodedPengajuanId = Hashids::connection('custom')->decode(trim($id_pengajuan));
            $decodedUpdatePerformanceId = Hashids::connection('custom')->decode(trim($id_update_performance));
            // dd($decodedPengajuanId, $decodedUpdatePerformanceId,$isYearBool);
            if (empty($decodedPengajuanId) || empty($decodedUpdatePerformanceId)) {
                return ResponseHelper::error('ID Pengajuan atau ID Update Performance tidak valid.', 400);
            }

            $pengajuanId = $decodedPengajuanId[0];
            $updatePerformanceId = $decodedUpdatePerformanceId[0];

            // Halaman ini hanya untuk performa milik sendiri. Tanpa penguncian ini,
            // menukar hashid di URL akan menampilkan data KPI karyawan lain.
            $penggunaId = Auth::user()->Id_Users;

            // Ambil formula SEKALI, di luar if/else
            $formulas = DB::table('KPI_Master_Formula')
                ->where('Flag_Aktif', 'Y')
                ->pluck('Rumus', 'Id_Master_Formula')
                ->all();

            // Optimasi query header referensi
            if ($isYearBool === true) {
                $performanceHeaderRef = DB::table('KPI_Update_Performance as kup')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->where('kup.Id_Pengajuan', $pengajuanId)
                    ->where('kup.Id_User', $penggunaId)
                    ->select('kup.Id_User', 'k.Nama as Nama_Lengkap', DB::raw('LEFT(kup.Periode, 4) as Periode'))
                    ->first();
            } else {
                $performanceHeaderRef = DB::table('KPI_Update_Performance as kup')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->where('kup.Id_Update_Performance', $updatePerformanceId)
                    ->where('kup.Id_Pengajuan', $pengajuanId)
                    ->where('kup.Id_User', $penggunaId)
                    ->select('kup.Id_User', 'k.Nama as Nama_Lengkap', 'kup.Periode')
                    ->first();
            }

            if (! $performanceHeaderRef) {
                return ResponseHelper::error('Data performance KPI referensi tidak ditemukan.', 404);
            }
            $userId = $performanceHeaderRef->Id_User;
            $periodeLengkap = $performanceHeaderRef->Periode;
            // dd($periodeLengkap);
            $periodeTahun = substr($periodeLengkap, 0, 4);
            // dd($periodeTahun);

            // $performanceRanges = DB::table('KPI_Master_Performance')
            //     ->orderBy('Range_Awal', 'DESC')
            //     ->get();

            // $getPerformanceLevel = function ($scoreValue) use ($performanceRanges) {

            //     if (is_null($scoreValue)) return (object)['Keterangan' => null, 'Warna' => null, 'Kode' => null];
            //     foreach ($performanceRanges as $range) {
            //         if ($scoreValue >= $range->Range_Awal && $scoreValue <= $range->Range_Akhir) {
            //             return (object)['Keterangan' => $range->Keterangan, 'Warna' => $range->Warna, 'Kode' => $range->Kode];
            //         }
            //     }
            //     $lastRange = $performanceRanges->last();
            //     return $lastRange ? (object)['Keterangan' => $lastRange->Keterangan, 'Warna' => $lastRange->Warna, 'Kode' => $lastRange->Kode] : (object)['Keterangan' => 'N/A', 'Warna' => '#808080', 'Kode' => 'NA'];
            // };

            $performanceRanges = DB::table('KPI_Master_Performance')
                ->orderBy('Range_Awal', 'DESC')
                ->get();

            $getPerformanceLevel = function ($scoreValue) use ($performanceRanges) {

                // null safety
                if ($scoreValue === null) {
                    return (object) [
                        'Keterangan' => null,
                        'Warna' => null,
                        'Kode' => null,
                    ];
                }

                foreach ($performanceRanges as $range) {
                    if ($scoreValue >= (float) $range->Range_Awal) {
                        return (object) [
                            'Keterangan' => $range->Keterangan,
                            'Warna' => $range->Warna,
                            'Kode' => $range->Kode,
                        ];
                    }
                }

                // fallback terakhir (harusnya tidak pernah kena)
                return (object) [
                    'Keterangan' => 'N/A',
                    'Warna' => '#808080',
                    'Kode' => 'NA',
                ];
            };

            if ($isYearBool !== true) {
                $performanceHeaderMonthly = DB::table('KPI_Update_Performance as kup')
                    ->join('KPI_Master_Performance as kmp', 'kup.Id_Performance', '=', 'kmp.Id_Master_Performance')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.Id_Level_Jabatan')
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->select(
                        'kup.*', 'kmp.Keterangan as Status_Performance', 'kmp.Warna',
                        'k.Nama as Nama_Lengkap', 'k.Email', 'k.HP as Kontak',
                        'vgsglj.nama_jabatan', 'vdss.nama_sub_divisi'
                    )
                    ->where('kup.Id_Update_Performance', $updatePerformanceId)
                    ->where('kup.Id_Pengajuan', $pengajuanId)
                    ->first();

                if (! $performanceHeaderMonthly) {
                    return ResponseHelper::error('Data performance KPI bulanan tidak ditemukan.', 404);
                }

                $allDetailsMonthly = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->leftJoin('KPI_Pengajuan_Det as kpd', function ($join) use ($periodeLengkap) {
                        $join->on('kpi_main.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
                            ->where('kpd.Periode', $periodeLengkap);
                    })
                    ->leftJoin('KPI_Update_Performance_Detail as kupd', function ($join) use ($updatePerformanceId) {
                        $join->on('kpd.Id_Pengajuan_Det', '=', 'kupd.Id_Pengajuan_Det')
                            ->where('kupd.Id_Update_Performance', $updatePerformanceId);
                    })
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select(
                        'kpi_main.Id_Pengajuan_Detail',
                        'kpi_main.Nama_Parameter',
                        'kpi_main.Deskripsi',
                        'kpi_main.Bobot',
                        'kpi_main.Id_Master_Formula',
                        'kms.Simbol_Satuan',
                        'kpd.Id_Pengajuan_Det',
                        'kpd.Nilai_Target',
                        'kupd.Id_Update_Performance_Detail',
                        'kupd.Nilai_Actual',
                        'kupd.File_Berkas',
                        'kupd.Alasan_Reject',
                        'kupd.Flag_Pica'
                    )
                    ->get();

                $picaDetailIds = $allDetailsMonthly->where('Flag_Pica', 'Y')->whereNotNull('Id_Update_Performance_Detail')->pluck('Id_Update_Performance_Detail')->unique();
                $allPicasMonthly = collect();
                if ($picaDetailIds->isNotEmpty()) {
                    $allPicasMonthly = DB::table('KPI_Update_Performance_Det')
                        ->whereIn('Id_Update_Performance_Detail', $picaDetailIds)
                        ->get()
                        ->keyBy('Id_Update_Performance_Detail');
                }

                $transformedDetailsMonthly = $allDetailsMonthly->map(function ($item) use ($getPerformanceLevel, $allPicasMonthly, $formulas) {
                    $newItem = new \stdClass;
                    $isInputPeriod = ! is_null($item->Id_Pengajuan_Det);
                    $wasSubmitted = ! is_null($item->Id_Update_Performance_Detail);

                    $newItem->id = $wasSubmitted ? Hashids::connection('custom')->encode($item->Id_Update_Performance_Detail) : ($isInputPeriod ? Hashids::connection('custom')->encode($item->Id_Pengajuan_Det) : Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail));
                    $newItem->parameter = $item->Nama_Parameter;
                    // Deskripsi parameter: dipakai kartu detail, dipotong dua baris di UI.
                    $newItem->deskripsi = trim((string) ($item->Deskripsi ?? '')) ?: null;
                    $newItem->Bobot = (float) $item->Bobot;

                    $targetMonthly = $isInputPeriod ? (float) $item->Nilai_Target : null;
                    $actualMonthly = $wasSubmitted ? (float) $item->Nilai_Actual : null;

                    $calculation = $this->calculateFormulaStream(
                        $actualMonthly,
                        $targetMonthly,
                        $newItem->Bobot,
                        $item->Id_Master_Formula,
                        $formulas
                    );

                    $achievementMonthly = $wasSubmitted ? $calculation['achievement'] : null;
                    $scoreMonthly = $wasSubmitted ? $calculation['score'] : null;

                    $newItem->targetValue = $targetMonthly;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = $actualMonthly;
                    $newItem->achievement = $achievementMonthly;
                    $newItem->score = $scoreMonthly;

                    $performanceMonthly = $getPerformanceLevel($newItem->achievement);
                    $newItem->statusKeterangan = $performanceMonthly->Keterangan;
                    $newItem->statusWarna = $performanceMonthly->Warna;

                    $newItem->is_input_period = $isInputPeriod;
                    $newItem->formula = \App\Helpers\KpiFormulaHelper::toDisplayText(
                        $formulas[$item->Id_Master_Formula] ?? ''
                    );
                    $newItem->dataSourceFile = $wasSubmitted && ! empty($item->File_Berkas) ? (object) ['name' => $item->File_Berkas] : null;
                    $newItem->File_Berkas_Url = $wasSubmitted && ! empty($item->File_Berkas) ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20)) : null;
                    $newItem->isRejected = $wasSubmitted && ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $wasSubmitted ? $item->Alasan_Reject : null;
                    $newItem->pica = null;

                    if ($wasSubmitted && $item->Flag_Pica === 'Y') {
                        $picaDataMonthly = $allPicasMonthly->get($item->Id_Update_Performance_Detail);
                        if ($picaDataMonthly) {
                            $newItem->pica = (object) ['problem' => $picaDataMonthly->Identifikasi, 'action' => $picaDataMonthly->Koreksi_Aksi, 'targetDate' => FormatTanggalHelper::format($picaDataMonthly->Tanggal_Pelaksanaan, true)];
                        }
                    }

                    return $newItem;
                });

                $periodeMonthly = Carbon::parse($performanceHeaderMonthly->Periode);
                $periodeFormattedMonthly = 'Periode '.$periodeMonthly->format('M Y');
                $formattedHeaderMonthly = (object) [
                    'Nama_Lengkap' => $performanceHeaderMonthly->Nama_Lengkap,
                    'Nama_Jabatan' => $performanceHeaderMonthly->nama_jabatan,
                    'Nama_Departemen' => $performanceHeaderMonthly->nama_sub_divisi,
                    'Status_Pengajuan' => $performanceHeaderMonthly->Status_Pengajuan,
                    'Achievement' => (float) round($performanceHeaderMonthly->Total_Score, 1),
                    'Status' => $performanceHeaderMonthly->Status_Performance,
                    'Warna' => $performanceHeaderMonthly->Warna,
                    'Email' => $performanceHeaderMonthly->Email,
                    'Kontak' => $performanceHeaderMonthly->Kontak,
                    'Jam' => $performanceHeaderMonthly->Jam,
                    'Tanggal' => $performanceHeaderMonthly->Tanggal,
                    'Flag_Aktif' => $performanceHeaderMonthly->Flag_Aktif,
                    'Periode' => $periodeFormattedMonthly,
                    'Flag_Pindah' => 'Y',
                    'is_year' => false,
                ];

                return ResponseHelper::success(['header' => $formattedHeaderMonthly, 'details' => $transformedDetailsMonthly], 'Data KPI Detail Bulanan Ditemukan!', 200);

            } else {
                $kpiParams = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select(
                        'kpi_main.Id_Pengajuan_Detail',
                        'kpi_main.Nama_Parameter',
                        'kpi_main.Deskripsi',
                        'kpi_main.Bobot',
                        'kms.Simbol_Satuan',
                        'kpi_main.Id_Master_Formula'
                    )
                    ->get();

                $paramIds = $kpiParams->pluck('Id_Pengajuan_Detail');

                // YTD: batas bulan mengikuti PENJADWALAN pengisian update performance
                // (Flag_Penilaian_KPI) karyawan ybs, bukan sekadar kalender. Full-Year = 12.
                if ($isYtd) {
                    $kodeKaryawanDetail = DB::table('Karyawan')
                        ->where('UserID_Web', $userId)
                        ->value('Kode_Karyawan');
                    $bulanYangSudahLewatUntukBetween = KpiScheduleHelper::resolveYtdCapMonth((int) $periodeTahun, $kodeKaryawanDetail);
                } else {
                    $bulanYangSudahLewatUntukBetween = 12;
                }
                // Pad 2 digit agar perbandingan string BETWEEN benar (mis. '2026-05' bukan '2026-5').
                $batasBulanDetail = str_pad((string) $bulanYangSudahLewatUntukBetween, 2, '0', STR_PAD_LEFT);

                $allTargets = DB::table('KPI_Pengajuan_Det')
                    ->join('KPI_Pengajuan_Detail as kpd', 'KPI_Pengajuan_Det.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
                    ->join('KPI_Pengajuan as kp', 'kpd.Id_Pengajuan', '=', 'kp.Id_Pengajuan')
                    ->whereIn('KPI_Pengajuan_Det.Id_Pengajuan_Detail', $paramIds)
                    ->whereBetween('KPI_Pengajuan_Det.Periode', [$periodeTahun.'-01', $periodeTahun.'-'.$batasBulanDetail])
                    ->whereNull('kp.Status')
                    ->where('kp.Flag_Aktif', 'Y')
                    ->groupBy('KPI_Pengajuan_Det.Id_Pengajuan_Detail')
                    ->select('KPI_Pengajuan_Det.Id_Pengajuan_Detail', DB::raw('SUM(ISNULL(KPI_Pengajuan_Det.Nilai_Target, 0)) as Total_Target'))
                    ->pluck('Total_Target', 'KPI_Pengajuan_Det.Id_Pengajuan_Detail');

                // Angka akumulasi dari SATU sumber (KpiAkumulasiYtdService) — sama
                // dengan dashboard CEO, Monitoring, homepage, dan `kpi:verify-ytd`.
                // Kueri lama di sini tidak menyaring Flag_Aktif sehingga realisasi
                // yang sudah digantikan revisi ikut terjumlah. Parameter tanpa
                // realisasi tetap ditampilkan, tidak ikut total.
                $ytdSvc = app(KpiAkumulasiYtdService::class);
                $rincianYtd = $bulanYangSudahLewatUntukBetween > 0
                    ? ($ytdSvc->rincianPerUser(
                        [$userId],
                        (int) $periodeTahun,
                        $ytdSvc->periodeSampai((int) $periodeTahun, (int) $bulanYangSudahLewatUntukBetween),
                    )[(string) $userId] ?? null)
                    : null;
                $rincianPerParam = collect($rincianYtd['parameter'] ?? [])->keyBy(fn ($p) => (string) $p['id']);

                $allPicas = DB::table('KPI_Update_Performance_Det as pica_det')
                    ->join('KPI_Update_Performance_Detail as kupd', 'pica_det.Id_Update_Performance_Detail', '=', 'kupd.Id_Update_Performance_Detail')
                    ->join('KPI_Update_Performance as kup', 'kupd.Id_Update_Performance', '=', 'kup.Id_Update_Performance')
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->where('kup.Id_User', $userId)
                    ->where('kupd.Flag_Pica', 'Y')
                    ->where('kup.Status_Pengajuan', 'disetujui')
                    ->where('kup.Flag_Approval', 'Y')
                    ->where('kup.Flag_Verifikasi', 'Y')
                    ->where('kup.Flag_Aktif', 'Y')
                    ->whereNull('kup.Status')
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeTahun.'-'.$batasBulanDetail])
                    ->select(
                        'kpd.Id_Pengajuan_Detail',
                        'pica_det.Identifikasi',
                        'pica_det.Koreksi_Aksi',
                        'pica_det.Tanggal_Pelaksanaan',
                        'kup.Periode'
                    )
                    ->orderBy('kup.Periode')
                    ->get();

                $groupedPicas = $allPicas->groupBy('Id_Pengajuan_Detail');

                $allDataSources = DB::table('KPI_Update_Performance_Detail as kupd')
                    ->join('KPI_Update_Performance as kup', 'kupd.Id_Update_Performance', '=', 'kup.Id_Update_Performance')
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->whereIn('kpd.Id_Pengajuan_Detail', $paramIds)
                    ->where('kup.Id_User', $userId)
                    ->where('kup.Status_Pengajuan', 'disetujui')
                    ->where('kup.Flag_Approval', 'Y')
                    ->where('kup.Flag_Verifikasi', 'Y')
                    ->whereNull('kup.Status')
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeTahun.'-'.$batasBulanDetail])
                    ->whereNotNull('kupd.File_Berkas')
                    ->where('kupd.File_Berkas', '!=', '')
                    ->select(
                        'kpd.Id_Pengajuan_Detail',
                        'kupd.File_Berkas',
                        'kup.Periode'
                    )
                    ->orderBy('kup.Periode')
                    ->get();

                $checkKPIAktif = DB::table('KPI_Pengajuan_Det')
                    ->join('KPI_Pengajuan_Detail as kpd', 'KPI_Pengajuan_Det.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
                    ->join('KPI_Pengajuan as kp', 'kpd.Id_Pengajuan', '=', 'kp.Id_Pengajuan')
                    ->whereIn('KPI_Pengajuan_Det.Id_Pengajuan_Detail', $paramIds)
                    ->whereBetween('KPI_Pengajuan_Det.Periode', [$periodeTahun.'-01', $periodeTahun.'-'.$batasBulanDetail])
                    ->whereNull('kp.Status')
                    ->where('kp.Flag_Aktif', 'Y')
                    ->groupBy('KPI_Pengajuan_Det.Id_Pengajuan_Detail')
                    ->select('KPI_Pengajuan_Det.Id_Pengajuan_Detail')
                    ->pluck('KPI_Pengajuan_Det.Id_Pengajuan_Detail');
                // dd($kpiParams);

                $groupedDataSources = $allDataSources->groupBy('Id_Pengajuan_Detail');

                $transformedDetailsYearly = $kpiParams->map(function ($item) use ($checkKPIAktif, $allTargets, $rincianPerParam, $groupedPicas, $groupedDataSources, $getPerformanceLevel, $formulas) {

                    $newItem = new \stdClass;
                    $paramId = $item->Id_Pengajuan_Detail;
                    $rincian = $rincianPerParam->get((string) $paramId);

                    if ($rincian) {
                        $target = $rincian['sum_target'];
                        $actual = $rincian['sum_actual'];
                        $achievementKPI = $rincian['achievement'];
                        $score = $rincian['score'];
                    } else {
                        // Tanpa realisasi di rentang ini: hanya untuk ditampilkan.
                        $target = $allTargets->get($paramId, 0);
                        $actual = 0;
                        $calculation = $this->calculateFormulaStream(
                            $actual, $target, $item->Bobot, $item->Id_Master_Formula, $formulas
                        );
                        $achievementKPI = $calculation['achievement'];
                        $score = $calculation['score'];
                    }
                    $newItem->id = Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail);
                    $newItem->parameter = $item->Nama_Parameter;
                    // Deskripsi parameter: dipakai kartu detail, dipotong dua baris di UI.
                    $newItem->deskripsi = trim((string) ($item->Deskripsi ?? '')) ?: null;
                    $newItem->Bobot = (float) $item->Bobot;
                    $newItem->targetValue = (float) $target;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = (float) $actual;
                    $newItem->achievement = (float) $achievementKPI;
                    $newItem->score = (float) $score;
                    $performanceYearlyParam = $getPerformanceLevel($newItem->achievement);
                    $newItem->statusKeterangan = $performanceYearlyParam->Keterangan;
                    $newItem->statusWarna = $performanceYearlyParam->Warna;
                    $newItem->is_input_period = $checkKPIAktif->contains($paramId);
                    $sourcesForThisParam = $groupedDataSources->get($paramId);
                    if ($sourcesForThisParam && $sourcesForThisParam->isNotEmpty()) {
                        $newItem->dataSourceFile = $sourcesForThisParam->map(fn ($s) => (object) ['name' => $s->File_Berkas, 'month' => Carbon::parse($s->Periode)->format('M'), 'periode' => Carbon::parse($s->Periode)->format('M Y')])->all();
                        $newItem->File_Berkas_Url = $sourcesForThisParam->map(fn ($s) => (object) ['url' => Storage::disk('gcs')->temporaryUrl($s->File_Berkas, now()->addMinutes(20)), 'month' => Carbon::parse($s->Periode)->format('M'), 'periode' => Carbon::parse($s->Periode)->format('M Y')])->filter()->all();
                    } else {
                        $newItem->dataSourceFile = [];
                        $newItem->File_Berkas_Url = [];
                    }
                    $newItem->isRejected = false;
                    $newItem->alasan_penolakan = null;
                    $newItem->formula = \App\Helpers\KpiFormulaHelper::toDisplayText(
                        $formulas[$item->Id_Master_Formula] ?? ''
                    );
                    $picasForThisParam = $groupedPicas->get($paramId);
                    if ($picasForThisParam && $picasForThisParam->isNotEmpty()) {
                        $newItem->pica = $picasForThisParam->map(fn ($p) => (object) ['problem' => $p->Identifikasi, 'action' => $p->Koreksi_Aksi, 'targetDate' => FormatTanggalHelper::format($p->Tanggal_Pelaksanaan, true), 'periode' => Carbon::parse($p->Periode)->format('M Y')])->all();
                    } else {
                        $newItem->pica = null;
                    }

                    return $newItem;
                });

                // Total dari sumber yang sama dengan rinciannya.
                $roundedTotalScore = round((float) ($rincianYtd['skor'] ?? 0), 1);

                // dd($roundedTotalScore);
                $yearlyPerformanceOverall = $getPerformanceLevel($roundedTotalScore);

                $lastMonthlyHeader = DB::table('KPI_Update_Performance as kup')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.Id_Level_Jabatan')
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->where('kup.Id_User', $userId)
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeTahun.'-12'])
                    ->orderBy('kup.Periode', 'desc')
                    ->select(
                        'vgsglj.nama_jabatan', 'vdss.nama_sub_divisi',
                        'k.Email', 'k.HP as Kontak', 'kup.Jam',
                        'kup.Tanggal', 'kup.Flag_Aktif'
                    )
                    ->first();

                $formattedHeaderYearly = (object) [
                    'Nama_Lengkap' => $performanceHeaderRef->Nama_Lengkap,
                    'Nama_Jabatan' => $lastMonthlyHeader->nama_jabatan ?? 'N/A',
                    'Nama_Departemen' => $lastMonthlyHeader->nama_sub_divisi ?? 'N/A',
                    'Status_Pengajuan' => 'disetujui',
                    'Achievement' => $roundedTotalScore,
                    'Status' => $yearlyPerformanceOverall->Keterangan,
                    'Warna' => $yearlyPerformanceOverall->Warna,
                    'Email' => $lastMonthlyHeader->Email ?? 'N/A',
                    'Kontak' => $lastMonthlyHeader->Kontak ?? 'N/A',
                    'Jam' => $lastMonthlyHeader->Jam ?? null,
                    'Tanggal' => $lastMonthlyHeader->Tanggal ?? null,
                    'Flag_Aktif' => $lastMonthlyHeader->Flag_Aktif ?? 'Y',
                    'Periode' => ($isYtd ? 'YTD ' : 'Full Year ').$periodeTahun,
                    'Flag_Pindah' => 'Y',
                    'is_year' => true,
                    'is_ytd' => $isYtd,
                ];

                return ResponseHelper::success(['header' => $formattedHeaderYearly, 'details' => $transformedDetailsYearly], 'Data KPI Detail Tahunan Ditemukan!', 200);
            }

        } catch (\Exception $e) {
            Log::channel('RecentKpiDetail')->error('Error in recentKpiUserDetail: '.$e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
                'id_pengajuan' => $id_pengajuan, 'id_update' => $id_update_performance, 'isyear' => $isyear,
            ]);

            return ResponseHelper::error('Terjadi Kesalahan Pada Server Saat Mengambil Detail KPI.', 500);
        }
    }
}
