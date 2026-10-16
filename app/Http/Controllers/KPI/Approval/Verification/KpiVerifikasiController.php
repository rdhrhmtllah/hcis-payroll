<?php

namespace App\Http\Controllers\KPI\Approval\Verification;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KPI\Approval\Performance\PerformanceApprovalController;
use App\Services\KPI\Approval\KpiApprovalService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi KPI — page khusus step ROLE_KPI_VERIF (pool verifikator).
 *
 * Menampilkan pengajuan KPI (parameter & performance) yang SAAT INI berada di
 * step verifikator role, terpisah dari page approval atasan. Tiap baris diberi
 * penanda `tipe` (PARAMETER / PERFORMANCE) + filter berdasarkan tipe.
 *
 * Approve/reject didelegasikan ke KpiApprovalService (engine terpusat).
 */
class KpiVerifikasiController extends Controller
{
    /** Id_Jenis_Approval untuk step role verifikator KPI. */
    private function roleJenisId(): ?int
    {
        return DB::table('N_HRIS_Jenis_Approval')
            ->where('Source_Reference', 'ROLE_KPI_VERIF')
            ->where('Source_Type', 'ROLE')
            ->value('Id_Jenis_Approval');
    }

    public function index()
    {
        return inertia('KPI/Approval/Verification/verifikasiKPI');
    }

    /**
     * Daftar pengajuan di step verifikasi (pending) atau riwayat (history).
     */
    public function getApprovals(Request $request)
    {
        try {
            $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
            $roleJenisId = $this->roleJenisId();
            if (! $roleJenisId) {
                return ResponseHelper::error('Konfigurasi role verifikator KPI tidak ditemukan.', 404);
            }

            $search = strip_tags((string) $request->input('search', ''));
            $division = (string) $request->input('division', '');
            $level = (string) $request->input('level', '');
            $periode = trim((string) $request->input('periode', ''));
            $tipe = strtoupper((string) $request->input('tipe', '')); // PARAMETER | PERFORMANCE | ''
            $history = $request->boolean('history');
            $status = (string) $request->input('status', '');
            $perPage = 10;
            $page = max(1, (int) $request->input('page', 1));

            if ($history && ! in_array($tipe, ['PARAMETER', 'PERFORMANCE'], true)) {
                return ResponseHelper::success([
                    'data' => new LengthAwarePaginator(collect(), 0, $perPage, $page, [
                        'path' => request()->url(),
                        'query' => request()->query(),
                    ]),
                    'totalPending' => 0,
                    'totalApproved' => 0,
                    'totalReject' => 0,
                ], 'Pilih tipe riwayat terlebih dahulu', 200);
            }

            $rows = $this->collectVerifikasiRows($kodeKaryawan, $roleJenisId, $history, $status);

            // Filter tipe.
            if ($tipe === 'PARAMETER' || $tipe === 'PERFORMANCE') {
                $rows = $rows->where('tipe', $tipe)->values();
            }

            // Filter search.
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $rows = $rows->filter(function ($r) use ($needle) {
                    return str_contains(mb_strtolower((string) $r['No_Transaksi']), $needle)
                        || str_contains(mb_strtolower((string) $r['requester']['Nama']), $needle)
                        || str_contains(mb_strtolower((string) $r['requester']['Department']), $needle)
                        || str_contains(mb_strtolower((string) $r['requester']['Jabatan']), $needle);
                })->values();
            }

            if ($division !== '') {
                $rows = $rows->filter(fn ($r) => (string) ($r['requester']['Id_Divisi'] ?? '') === $division)->values();
            }

            if ($level !== '') {
                $rows = $rows->filter(fn ($r) => (string) ($r['requester']['Id_Level'] ?? '') === $level)->values();
            }

            if ($tipe === 'PERFORMANCE' && $periode !== '') {
                $rows = $rows->filter(fn ($r) => (string) ($r['Periode'] ?? '') === $periode)->values();
            }

            if ($tipe === 'PERFORMANCE') {
                $sortKey = $history ? 'update_at' : 'create_at';
                $rows = $rows->sortByDesc(function ($r) use ($sortKey) {
                    $value = $r[$sortKey] ?? null;
                    if ($value instanceof \DateTimeInterface) {
                        return $value->getTimestamp();
                    }

                    return $value ? strtotime((string) $value) : 0;
                })->values();
            }

            $total = $rows->count();
            $paged = $rows->slice(($page - 1) * $perPage, $perPage)->values();

            $final = new LengthAwarePaginator($paged, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            $counts = $this->collectCounts($kodeKaryawan, $roleJenisId);

            return ResponseHelper::success([
                'data' => $final,
                'totalPending' => $counts['pending'],
                'totalApproved' => $counts['approved'],
                'totalReject' => $counts['rejected'],
            ], 'Verifikasi list retrieved successfully', 200);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->error('ERROR VERIFIKASI_GET: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getParameterApprovals(Request $request)
    {
        $request->merge(['tipe' => 'PARAMETER']);

        return $this->getApprovals($request);
    }

    public function getPerformanceApprovals(Request $request)
    {
        $request->merge(['tipe' => 'PERFORMANCE']);

        return $this->getApprovals($request);
    }

    /**
     * Saring request agar hanya menyisakan yang HEADER sumbernya masih AKTIF
     * (KPI_Pengajuan / KPI_Update_Performance: Flag_Aktif='Y' & Status NULL).
     * Header yang dibatalkan (Status='Y') = transaksi batal -> tak boleh muncul di
     * antrian verifikator walau approval request-nya masih "menunggu" (cascade tidak
     * dilakukan; header adalah sumber kebenaran).
     *
     * @param  \Illuminate\Support\Collection  $requests  baris dgn properti No_Transaksi
     * @return \Illuminate\Support\Collection
     */
    private function filterActiveSourceHeaders($requests)
    {
        if ($requests->isEmpty()) {
            return $requests;
        }

        $noList = $requests->pluck('No_Transaksi')->unique()->all();

        $activeParam = DB::table('KPI_Pengajuan')
            ->whereIn('No_Transaksi', $noList)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->pluck('No_Transaksi');
        $activePerf = DB::table('KPI_Update_Performance')
            ->whereIn('No_Transaksi', $noList)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->pluck('No_Transaksi');

        $active = $activeParam->merge($activePerf)->unique()->flip();

        return $requests->filter(fn ($r) => isset($active[$r->No_Transaksi]))->values();
    }

    /**
     * Kumpulkan baris verifikasi (parameter + performance) berbentuk flat untuk frontend.
     */
    private function collectVerifikasiRows(string $kodeKaryawan, int $roleJenisId, bool $history, string $status)
    {
        $reqSvc = app(ApprovalRequestService::class);

        if ($history) {
            $base = collect(
                DB::table('N_HRIS_VW_Approval_Request_V2')
                    ->where('Flow_State', 'HISTORY')
                    ->where('Approved_By_Kode', $kodeKaryawan)
                    ->where('Id_Jenis_Approval', $roleJenisId)
                    ->get(),
            );
            if ($status === 'approved') {
                $base = $base->where('Flag_Approval', 'Y')->values();
            } elseif ($status === 'rejected') {
                $base = $base->where('Flag_Approval', 'T')->values();
            }
        } else {
            $base = $reqSvc->getPendingApprovalsForApprover($kodeKaryawan)
                ->filter(fn ($r) => (int) $r->Id_Jenis_Approval === $roleJenisId)
                ->values();
            // Buang transaksi yang HEADER sumbernya sudah dibatalkan (Status='Y' /
            // Flag_Aktif='T'). Header = sumber kebenaran: walau approval request masih
            // "menunggu", transaksi yang dibatalkan tak boleh tampil di antrian.
            $base = $this->filterActiveSourceHeaders($base);
        }

        // Satu transaksi bisa muncul >1 baris di view/pending (mis. >1 step utk role/
        // approver yg sama) -> tampilkan SEKALI per No_Transaksi agar tidak dobel.
        $base = $base->unique('No_Transaksi')->values();

        if ($base->isEmpty()) {
            return collect();
        }

        $noList = $base->pluck('No_Transaksi')->unique()->all();

        // Klasifikasi tipe + ambil header masing-masing domain.
        $paramHeaders = DB::table('KPI_Pengajuan')->whereIn('No_Transaksi', $noList)->get()->keyBy('No_Transaksi');
        $perfHeaders = DB::table('KPI_Update_Performance')->whereIn('No_Transaksi', $noList)->get()->keyBy('No_Transaksi');

        $kodeRequesters = $base->pluck('Kode_Karyawan_Requester')->unique()->all();
        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as c', 'k.ID_Divisi_Sub_Divisi', '=', 'c.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as d', 'd.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->whereIn('k.Kode_Karyawan', $kodeRequesters)
            ->select('k.Kode_Karyawan', 'k.Nama', 'd.nama_jabatan as Jabatan', 'c.nama_divisi as Department', 'c.ID_Divisi as Id_Divisi', 'd.Id_Level as Id_Level')
            ->get()
            ->keyBy('Kode_Karyawan');

        return $base->map(function ($r) use ($paramHeaders, $perfHeaders, $karyawan) {
            $no = $r->No_Transaksi;
            $kar = $karyawan->get($r->Kode_Karyawan_Requester);

            if ($param = $paramHeaders->get($no)) {
                $tipe = 'PARAMETER';
                $totalKPI = (int) DB::table('KPI_Pengajuan_Detail')->where('Id_Pengajuan', $param->Id_Pengajuan)->count();
                $bobot = (float) DB::table('KPI_Pengajuan_Detail')->where('Id_Pengajuan', $param->Id_Pengajuan)->sum('Bobot');
                $periode = null;
                $score = null;
                $idPengajuan = $param->Id_Pengajuan;
                $idUpdatePerformance = null;
                $picaCount = null;
            } elseif ($perf = $perfHeaders->get($no)) {
                $tipe = 'PERFORMANCE';
                $totalKPI = (int) DB::table('KPI_Pengajuan_Detail')->where('Id_Pengajuan', $perf->Id_Pengajuan)->count();
                $bobot = null;
                $periode = $perf->Periode;
                $score = $perf->Total_Score;
                $idPengajuan = $perf->Id_Pengajuan;
                $idUpdatePerformance = $perf->Id_Update_Performance;
                $picaCount = (int) DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $perf->Id_Update_Performance)
                    ->where('Flag_Pica', 'Y')
                    ->count();
            } else {
                return null; // bukan domain KPI yang dikenali
            }

            return [
                'tipe' => $tipe,
                'engine' => 'CENTRAL',
                'id_Approval_Request' => $r->Id_Approval_Request,
                'Id_Approval_Request' => $r->Id_Approval_Request,
                'No_Transaksi' => $no,
                'Id_Pengajuan' => $idPengajuan,
                'id_Performance_Approval' => $idUpdatePerformance,
                'Flag_Approval' => $r->Flag_Approval,
                'Alasan' => $r->Catatan ?? null,
                'create_at' => $r->Tanggal_Request,
                'update_at' => $r->Tanggal_Approval ?? $r->Tanggal_Request,
                'totalKPI' => $totalKPI,
                'kpi_count' => $totalKPI,
                'Bobot' => $bobot,
                'bobot' => $bobot,
                'Periode' => $periode,
                'total_score' => $score,
                'pica_count' => $picaCount,
                'Nama' => $kar->Nama ?? null,
                'Kode_Karyawan' => $r->Kode_Karyawan_Requester,
                'Jabatan' => $kar->Jabatan ?? null,
                'Department' => $kar->Department ?? null,
                'requester' => [
                    'Nama' => $kar->Nama ?? null,
                    'Kode_Karyawan' => $r->Kode_Karyawan_Requester,
                    'Jabatan' => $kar->Jabatan ?? null,
                    'Department' => $kar->Department ?? null,
                    'Id_Divisi' => $kar->Id_Divisi ?? null,
                    'Id_Level' => $kar->Id_Level ?? null,
                ],
            ];
        })->filter()->values();
    }

    private function collectCounts(string $kodeKaryawan, int $roleJenisId): array
    {
        $reqSvc = app(ApprovalRequestService::class);

        // Konsisten dgn list: hanya hitung pending yang HEADER sumbernya masih aktif
        // (transaksi yang dibatalkan tak ikut walau approval request masih "menunggu"),
        // dan unik per No_Transaksi (view bisa punya >1 baris per transaksi).
        $pending = $this->filterActiveSourceHeaders(
            $reqSvc->getPendingApprovalsForApprover($kodeKaryawan)
                ->filter(fn ($r) => (int) $r->Id_Jenis_Approval === $roleJenisId)
                ->values(),
        )->unique('No_Transaksi')->count();

        $processed = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Flow_State', 'HISTORY')
            ->where('Approved_By_Kode', $kodeKaryawan)
            ->where('Id_Jenis_Approval', $roleJenisId)
            ->get(['No_Transaksi', 'Flag_Approval']);

        return [
            'pending' => $pending,
            'approved' => $processed->where('Flag_Approval', 'Y')->unique('No_Transaksi')->count(),
            'rejected' => $processed->where('Flag_Approval', 'T')->unique('No_Transaksi')->count(),
        ];
    }

    /**
     * Opsi filter divisi / level (reuse view yang sama dengan page approval).
     */
    public function getFilterOptions($type)
    {
        try {
            if ($type === 'divisions') {
                $divisions = DB::table('View_Divisi_Sub_Divisi')
                    ->select('ID_Divisi as id', 'nama_divisi as name')
                    ->groupBy('ID_Divisi', 'nama_divisi')
                    ->orderBy('nama_divisi')
                    ->get();

                return ResponseHelper::success($divisions, 'Divisions retrieved successfully', 200);
            }

            if ($type === 'levels') {
                $levels = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                    ->select('ID_Level as id', 'nama_level as name')
                    ->groupBy('ID_Level', 'nama_level')
                    ->orderBy('nama_level')
                    ->get();

                return ResponseHelper::success($levels, 'Levels retrieved successfully', 200);
            }

            return ResponseHelper::error('Invalid filter type', 400);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->error('ERROR VERIFIKASI_FILTER: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Detail pengajuan (parameter / performance) by No_Transaksi.
     */
    public function getApprovalDetail(Request $request, $noTransaksi)
    {
        try {
            $param = DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->first();
            $perf = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->first();

            // ===== PERFORMANCE: tampilkan actual/target/score/achievement per periode =====
            if ($perf) {
                $kpiDetails = DB::table('KPI_Update_Performance_Detail as upd')
                    ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Det', '=', 'upd.Id_Pengajuan_Det')
                    ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'pdet.Id_Pengajuan_Detail')
                    ->join('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                    ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                    ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                    ->where('upd.Id_Update_Performance', $perf->Id_Update_Performance)
                    ->select(
                        'upd.Id_Update_Performance_Detail as id',
                        'pd.Nama_Parameter as parameter',
                        'pd.Id_Bsc_Kategori',
                        'bc.Nama_Kategori as bsc_category',
                        'pd.Deskripsi as Deskripsi',
                        'pd.Bobot as weight',
                        'pd.Id_Master_Target',
                        'pdet.Nilai_Target as Nilai_Target',
                        'pdet.Nama_Target as Nama_Target',
                        'upd.Nilai_Actual as actual_value',
                        'upd.Score as score',
                        'upd.Achievement as achievement',
                        // Jejak penjepitan per parameter. Panel verifikasi punya
                        // proyeksi SENDIRI, terpisah dari PerformanceApprovalController:
                        // melengkapi salah satunya saja membuat verifikator melihat
                        // angka yang sudah dipangkas plafon tanpa tanda apa pun,
                        // sementara approver di halaman sebelah melihat tandanya.
                        'upd.Score_Raw as score_raw',
                        'upd.Flag_Capped as flag_capped',
                        'upd.Flag_Pica as Flag_Pica',
                        'upd.File_Berkas as File_Berkas',
                        'upd.Alasan_Reject as Alasan_Tolak',
                        'mf.Rumus as rumus',
                        'ms.Simbol_Satuan as unit',
                        'ms.Format_Input as Format_Input',
                        DB::raw("'T' as Is_Disabled"),
                    )
                    ->orderBy('upd.Id_Update_Performance_Detail')
                    ->get();

                /*
                 * PICA per detail (problem/action/due date), diambil SEKALI.
                 * Sebelumnya satu kueri per baris detail; berkas ini sendiri
                 * sudah memakai pola whereIn(...)->keyBy(...) di tempat lain.
                 *
                 * Id_Update_Performance_Detail ikut diambil karena dibutuhkan
                 * untuk mengelompokkan, lalu dibuang lagi saat dirakit supaya
                 * bentuk tiap item pica tetap persis seperti sebelumnya.
                 */
                $picaPerDetail = DB::table('KPI_Update_Performance_Det')
                    ->whereIn('Id_Update_Performance_Detail', $kpiDetails->pluck('id')->all())
                    ->select(
                        'Id_Update_Performance_Detail',
                        'Identifikasi as problem_identification',
                        'Koreksi_Aksi as corrective_actions',
                        'Tanggal_Pelaksanaan as due_date',
                    )
                    ->get()
                    ->groupBy('Id_Update_Performance_Detail')
                    ->map(fn ($baris) => $baris->map(fn ($r) => (object) [
                        'problem_identification' => $r->problem_identification,
                        'corrective_actions' => $r->corrective_actions,
                        'due_date' => $r->due_date,
                    ])->values());

                foreach ($kpiDetails as $d) {
                    $d->pica = $picaPerDetail[$d->id] ?? collect();
                }

                return ResponseHelper::success([
                    'tipe' => 'PERFORMANCE',
                    'No_Transaksi' => $noTransaksi,
                    'Periode' => $perf->Periode,
                    'total_score' => $perf->Total_Score,
                    'pica_count' => $kpiDetails->where('Flag_Pica', 'Y')->count(),
                    'kpiDetails' => $kpiDetails,
                ], 'Detail retrieved', 200);
            }

            // ===== PARAMETER: tampilkan target breakdown =====
            if (! $param) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            // leftJoin (bukan inner) agar SEMUA detail tampil & index checkbox reject
            // sinkron dgn set detail saat write (write tidak nge-join).
            $kpiDetails = DB::table('KPI_Pengajuan_Detail as pd')
                ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->where('pd.Id_Pengajuan', $param->Id_Pengajuan)
                ->select(
                    'pd.Id_Pengajuan_Detail',
                    'pd.Nama_Parameter',
                    'pd.Id_Bsc_Kategori',
                    'pd.Deskripsi',
                    'pd.Bobot',
                    'bc.Nama_Kategori',
                    'pd.Alasan_Tolak',
                    'ms.Simbol_Satuan',
                    'ms.Format_Input',
                )
                ->orderBy('pd.Id_Pengajuan_Detail')
                ->get();

            /*
             * SATU kueri untuk semua parameter, bukan satu per parameter.
             *
             * Bentuk sebelumnya menjalankan SELECT di dalam perulangan: sebuah
             * pengajuan dengan empat parameter membayar empat perjalanan
             * bolak-balik hanya untuk mengambil daftar target. Jumlahnya memang
             * kecil (rata-rata 3,9 parameter per pengajuan, terbanyak 4), tetapi
             * perjalanan itulah yang paling mahal di lingkungan ini —
             * ASYNC_NETWORK_IO adalah 33,5% waktu tunggu server, dan basis data
             * dev berada di luar mesin.
             *
             * Bentuk batch ini menyalin pola yang SUDAH dipakai di repo ini pada
             * KpiEnforcementController::…() — whereIn lalu groupBy — supaya
             * keduanya tak menyimpang satu sama lain.
             *
             * Urutan target dalam tiap kelompok dipertahankan lewat orderBy,
             * sebab tanpa itu layar bisa menampilkan target dengan susunan yang
             * berubah-ubah antar-permintaan.
             */
            $idDetail = collect($kpiDetails)->pluck('Id_Pengajuan_Detail')->filter()->values();

            $targetPerDetail = $idDetail->isEmpty()
                ? collect()
                : DB::table('KPI_Pengajuan_Det')
                    ->whereIn('Id_Pengajuan_Detail', $idDetail->all())
                    ->orderBy('Id_Pengajuan_Det')
                    ->select('Id_Pengajuan_Detail', 'Nama_Target', 'Nilai_Target')
                    ->get()
                    ->groupBy('Id_Pengajuan_Detail');

            foreach ($kpiDetails as $d) {
                /* Bentuk nilainya dijaga tetap sama dengan sebelumnya: koleksi
                   objek ber-Nama_Target/Nilai_Target, dan koleksi KOSONG bila
                   parameter itu memang belum punya target — bukan null. */
                $d->targets = collect($targetPerDetail->get($d->Id_Pengajuan_Detail, collect()))
                    ->map(fn ($t) => (object) [
                        'Nama_Target' => $t->Nama_Target,
                        'Nilai_Target' => $t->Nilai_Target,
                    ])
                    ->values();
            }

            return ResponseHelper::success([
                'tipe' => 'PARAMETER',
                'No_Transaksi' => $noTransaksi,
                'Periode' => null,
                'total_score' => null,
                'alasan_reject_global' => (($param->Flag_Approval ?? null) === 'T')
                    ? ($param->Alasan_Reject ?? null)
                    : null,
                'kpiDetails' => $kpiDetails,
            ], 'Detail retrieved', 200);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->error('ERROR VERIFIKASI_DETAIL: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Approve step verifikasi. $id = PK HRIS_Approval_Request.
     */
    public function approve(Request $request, $id)
    {
        $row = DB::table('HRIS_Approval_Request')->where('id', $id)->first();
        if (! $row) {
            return ResponseHelper::error('Approval request not found', 404);
        }

        try {
            DB::beginTransaction();
            $kodeApprover = Auth::user()->karyawan->Kode_Karyawan;
            $noTransaksi = $row->No_Transaksi;

            app(KpiApprovalService::class)->approve($noTransaksi, $kodeApprover, $request->input('catatan'));

            // Finalisasi domain saat flow selesai.
            $next = app(ApprovalRequestService::class)->getCurrentApprovalRequest($noTransaksi);
            if (! $next) {
                $this->finalizeApproved($noTransaksi);
            }

            // Bersihkan notif role-pool KPI untuk transaksi ini (tray verifikator lain).
            app(\App\Services\KPI\Notification\KpiNotificationService::class)->resolveRolePoolNotifications($noTransaksi);

            DB::commit();

            return ResponseHelper::success(null, 'Verifikasi disetujui', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR VERIFIKASI_APPROVE: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }
    }

    /**
     * Reject step verifikasi. $id = PK HRIS_Approval_Request.
     */
    public function reject(Request $request, $id)
    {
        $row = DB::table('HRIS_Approval_Request')->where('id', $id)->first();
        if (! $row) {
            return ResponseHelper::error('Approval request not found', 404);
        }

        // Alasan GLOBAL wajib — konsisten dgn Approval Performance. Berlaku untuk
        // verifikasi parameter maupun performance.
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'kpi_comments' => 'nullable|array',
            'kpi_comments.*' => 'nullable|string|max:1000',
        ]);

        $noTransaksi = $row->No_Transaksi;
        $reason = $validated['reason'];
        $kpiComments = $validated['kpi_comments'] ?? null;
        $namaRejector = Auth::user()->karyawan->Nama ?? null;
        $isPerformance = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->exists();

        try {
            DB::beginTransaction();
            $kodeRejector = Auth::user()->karyawan->Kode_Karyawan;

            app(KpiApprovalService::class)->reject($noTransaksi, $kodeRejector, $reason);

            $this->finalizeRejected($noTransaksi, $kpiComments, $reason);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR VERIFIKASI_REJECT: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }

        // Pasca-COMMIT, best-effort: notifikasi requester (parameter & performance) +
        // bersihkan tray verifikator lain.
        $kpiNotif = app(\App\Services\KPI\Notification\KpiNotificationService::class);
        $jenisLabel = $isPerformance ? 'Update Performance KPI' : 'Pengajuan Parameter KPI';

        try {
            $kpiNotif->notifyRequesterRejected($noTransaksi, $reason, $kpiComments ?? []);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->warning('Gagal notif requester penolakan: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
            ]);
        }

        // WA penolakan (post-commit, best-effort). Diantre via Cloud Tasks oleh
        // WhatsappLogHelper — tidak menahan lock DB. Template sama utk parameter &
        // performance (hcis_tolak_pengajuan_kpi), beda hanya label jenis.
        try {
            $kpiNotif->notifyRequesterRejectedWhatsapp($noTransaksi, $jenisLabel, $namaRejector);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->warning('Gagal kirim WA penolakan: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
            ]);
        }

        try {
            $kpiNotif->resolveRolePoolNotifications($noTransaksi);
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->warning('Gagal bersihkan notif role-pool: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
            ]);
        }

        return ResponseHelper::success(null, 'Verifikasi ditolak', 200);
    }

    public function editAndApprovePerformance(Request $request, $id)
    {
        $row = DB::table('HRIS_Approval_Request')->where('id', $id)->first();
        if (! $row) {
            return ResponseHelper::error('Approval request not found', 404);
        }

        if (! DB::table('KPI_Update_Performance')->where('No_Transaksi', $row->No_Transaksi)->exists()) {
            return ResponseHelper::error('Endpoint ini hanya untuk verifikasi performance.', 422);
        }

        return app(PerformanceApprovalController::class)->editAndApprovePerformance($request, $id);
    }

    public function getPerformanceDetail(Request $request, $id, $periode)
    {
        return app(PerformanceApprovalController::class)->getApprovalDetail($request, $id, $periode);
    }

    public function getAttachmentUrl(Request $request)
    {
        return app(PerformanceApprovalController::class)->getAttachmentUrl($request);
    }

    private function finalizeApproved(string $noTransaksi): void
    {
        if (DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->exists()) {
            DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->update(['Flag_Approval' => 'Y']);
        } elseif (DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->exists()) {
            // Hitung ulang total dari detail faktual agar konsisten saat disetujui final
            // (sama seperti perhitungan di Performance Approval).
            app(PerformanceApprovalController::class)->recalcTotalsByNoTransaksi($noTransaksi);

            DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->update([
                'Status_Pengajuan' => 'disetujui',
                'Flag_Approval' => 'Y',
                'Flag_Verifikasi' => 'Y',
            ]);
        }
    }

    /**
     * PENTING — $kpiComments punya BENTUK KEY BERBEDA per domain, dan itu disengaja:
     *   - Parameter (KPI_Pengajuan)  : keyed by INDEX urutan detail (panel parameter).
     *   - Performance                : keyed by Id_Update_Performance_Detail (panel performance).
     * Jangan disatukan. persistPerformanceRejection() men-scope ke Id_Update_Performance,
     * jadi key yang tidak cocok akan terbuang dengan sendirinya.
     */
    private function finalizeRejected(string $noTransaksi, ?array $kpiComments, string $globalReason = ''): void
    {
        if ($param = DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->first()) {
            // Alasan GLOBAL disimpan di header (mirror KPI_Update_Performance.Alasan_Reject).
            DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->update([
                'Flag_Approval' => 'T',
                'Alasan_Reject' => trim($globalReason) !== '' ? $globalReason : null,
            ]);

            if (is_array($kpiComments)) {
                $details = DB::table('KPI_Pengajuan_Detail')
                    ->where('Id_Pengajuan', $param->Id_Pengajuan)
                    ->orderBy('Id_Pengajuan_Detail')
                    ->get();
                foreach ($details as $i => $d) {
                    $alasan = isset($kpiComments[$i]) && trim((string) $kpiComments[$i]) !== ''
                        ? $kpiComments[$i]
                        : null;
                    DB::table('KPI_Pengajuan_Detail')->where('Id_Pengajuan_Detail', $d->Id_Pengajuan_Detail)->update(['Alasan_Tolak' => $alasan]);
                }
            }
        } elseif (DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->exists()) {
            app(PerformanceApprovalController::class)
                ->persistPerformanceRejection($noTransaksi, $globalReason, $kpiComments ?? []);
        }
    }
}
