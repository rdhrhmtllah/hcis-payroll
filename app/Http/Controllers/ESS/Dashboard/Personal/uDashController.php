<?php

namespace App\Http\Controllers\ESS\Dashboard\Personal;

use App\Helpers\FormatTanggalHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService as CutiBersamaService;
use App\Services\ESS\Attendance\Leave\Shared\KalenderCutiBersama;
use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;

class uDashController extends Controller
{
    public function __construct(
        protected UDashReadModelService $uDashReadModelService,
        protected CutiBersamaService $cutiBersamaService,
        protected HrisAttendanceSnapshotService $attendanceService,
    ) {}

    public function index()
    {
        // dd('index');
        $user = Auth::user();
        $karyawan = $user?->karyawan;
        $Kode_KaryawanReal = $karyawan?->Kode_Karyawan ?? null;
        try {
            $batasHariPengajuan = config('services.hcis.batas_approve_izin');
            $batasApproveCuti = config('services.hcis.batas_approve_cuti');
            $title = 'uDash for You';
            $cutoffRange = $this->uDashReadModelService->getCutoffRange(Carbon::now());
            $startWeek = $cutoffRange['start_date'];
            $endWeek = $cutoffRange['end_date'];

            if (! $karyawan) {
                throw new \RuntimeException('Data karyawan tidak ditemukan untuk user login.');
            }

            $division = $karyawan->division;
            $level = $karyawan->level;

            if (! $division || ! $level) {
                throw new \RuntimeException(
                    'Relasi divisi/jabatan kosong untuk Kode_Karyawan '.($karyawan->Kode_Karyawan ?? '-'),
                );
            }

            $UserID_Absen = $karyawan->UserID_Absen;
            $UserID_Web = $karyawan->UserID_Web;
            $Id_Division = $division->ID_Divisi;
            $Id_Level = $level->ID_Level;
            $Kode_Karyawan = $karyawan->Kode_Karyawan;
            // dd($Id_Level);
            // dd($Id_Division, $Id_Level, $UserID_Web);
            // Tentukan rentang tanggal
            // Ambil data absensi utama dari stored procedure
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";

            $sourceReuqest = 'uDash-controller-ringkasan-team';
            $tglMulai = $startWeek;
            $tglSelesai = $endWeek;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absen_data = collect($response['data']) ?? collect([]);

            // dd($absen_data);
            // Ambil semua tanggal unik dari data absen untuk dijadikan filter
            $tanggal_absens = $absen_data->pluck('Tanggal')->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
            $CHECKIN = $absen_data->pluck('CheckIn')->map(fn ($date) => Carbon::parse($date)->format('Y-m-d h:i:s'));

            $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->where('b.Jenis', 'pulangCepat')
                ->where('b.Flag_Approval', 'Y')
                ->where('a.Status', null)
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
                ->get();

            $total_keterlambatan_menit = (int) round($absen_data->sum(fn ($item) => (float) ($item->RealLate ?? 0)));

            // dd($total_keterlambatan_menit);
            // Konversi ke menit
            // $total_keterlambatan_menit = round($total_keterlambatan_detik / 60, 2);

            // --- Pre-load Semua Data Transaksi dalam Beberapa Query ---

            $total_lembur_jam = (float) round($absen_data->sum(fn ($item) => (float) ($item->Koefisien_OT ?? 0)), 2);
            // dd($total_lembur_jam);
            $today = Carbon::today()->toDateString();
            $tomorrow = Carbon::tomorrow()->toDateString();

            $absen_hari_ini = $absen_data->first(function ($item) use ($today) {
                if (is_null($item->Tanggal)) {
                    return false;
                }

                // Bandingkan tanggal dari item dengan tanggal hari ini
                return Carbon::parse($item->Tanggal)->toDateString() === $today;
            });

            $tanggalBesok = Carbon::tomorrow();
            // $queryBesok = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$tanggalBesok}', '{$tanggalBesok}', '{$UserID_Absen}', '', ''";
            // $absen_besok_raw = DB::Select($queryBesok);
            $sourceReuqest = 'uDash-controller-getShiftBesok';
            $tglMulai = $tanggalBesok;
            $tglSelesai = $tanggalBesok;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absen_besok_raw = $response['data'] ?? [];

            $absen_besok = collect($absen_besok_raw)->first();
            // dd($absen_besok->Nama_Shift);
            // $absen_besok = $absen_data->first(function ($item) use ($tomorrow) {
            //     if (is_null($item->Tanggal)) {
            //         return false;
            //     }

            //     return Carbon::parse($item->Tanggal)->toDateString() === $tomorrow;
            // });

            $accessPage = DB::table('HRIS_Page_Access')
                ->select('Jenis_page')
                ->where('Flag_Aktif', 'Y')
                ->where('UserID_Web', $UserID_Web)
                ->get();
            // dd($accessPage);
            $sisaCuti = (int) Auth::user()->karyawan->sisa_cuti();
            // dd($sisaCuti);
            $namaKaryawan = Auth::user()->karyawan->Nama;

            $berita = DB::table('HRIS_News_Dashboard')->orderBy('Id_News', 'desc')->first();

            $nama_anggota = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('t.Kode_Karyawan_Team', $Kode_KaryawanReal)
                ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
                ->groupBy('k.HP', 'd.nama_divisi ', 'k.Nama')
                ->get();

            $nama_tergabung = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
                ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
                ->groupBy('k.Nama', 'k.HP', 'd.nama_divisi')
                ->where('t.Kode_Karyawan_Team', '<>', $Kode_KaryawanReal)
                ->where('t.Kode_Karyawan_Team', '<>', 'RIDHO RAHMAT')
                ->get();

            $nama_Approver = $this->uDashReadModelService->getApproverChainFromConfig($Kode_KaryawanReal);

            if ($nama_Approver->isEmpty()) {
                $nama_Approver = DB::table('HRIS_Approval_Flow as t')
                    ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                    ->where('t.Kode_Karyawan_Requester', $Kode_KaryawanReal)
                    ->select('k.Nama', 't.order_flow')
                    ->groupBy('k.Nama', 't.order_flow')
                    ->where('t.Kode_Karyawan', '<>', $Kode_KaryawanReal)
                    ->get();
            }

            $nama_mengapprove = DB::table('HRIS_Approval_Flow as t')
                ->join('Karyawan as k', 't.Kode_Karyawan_Requester', '=', 'k.Kode_Karyawan')
                ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
                ->select('k.Nama')
                ->groupBy('k.Nama')
                ->where('t.Kode_Karyawan_Requester', '<>', $Kode_KaryawanReal)
                ->get();

            // dd($nama_anggota);

            $LeaderTeam = DB::table('HRIS_Karyawan_Team')->where('Kode_Karyawan_Team', $Kode_KaryawanReal)->exists();
            if ($Kode_KaryawanReal == 'RIDHO RAHMAT' || $Kode_KaryawanReal == 'H45') {
                $LeaderTeam = false;
            }
            // dd($LeaderTeam);
            $datasPending = 0;
            if ($LeaderTeam) {
                $datasPendingV2 = $this->uDashReadModelService->countPendingApprovalsV2($Kode_KaryawanReal);
                // dd($datasPendingV2);
                // if ($datasPendingV2 !== null) {
                //     $datasPending = $datasPendingV2;
                // } else {
                //     $datasPending = DB::table('HRIS_Approval_Request as a')
                //         ->join('HRIS_Approval_Flow as b', 'a.Flow_Id', '=', 'b.id')
                //         ->whereNull('a.Flag_Approval')
                //         ->whereNull('b.Status')
                //         ->where('b.Kode_Karyawan', $Kode_KaryawanReal)

                //         ->whereNotExists(function ($sub) {
                //             $sub->select(DB::raw(1))
                //                 ->from('HRIS_Approval_Request as prev')
                //                 ->join('HRIS_Approval_Flow as prev_flow', 'prev.Flow_Id', '=', 'prev_flow.id')
                //                 ->whereColumn('prev.No_Transaksi', 'a.No_Transaksi')
                //                 ->whereRaw('prev_flow.order_flow < b.order_flow')
                //                 ->where(function ($q) {
                //                     $q->whereNull('prev.Flag_Approval')
                //                         ->whereNull('prev_flow.Status')
                //                         ->orWhere('prev.Flag_Approval', '!=', 'Y');
                //                 });
                //         })
                //         ->whereIn('a.id', function ($query) {
                //             $query
                //                 ->selectRaw('MIN(t1.id)')
                //                 ->from('HRIS_Approval_Request as t1')
                //                 ->join('HRIS_Approval_Flow as t2', 't1.Flow_Id', '=', 't2.id')
                //                 ->whereNull('t1.Flag_Approval')
                //                 ->whereNull('t2.Status')
                //                 ->groupBy('t1.No_Transaksi');
                //         })
                //         ->whereNotExists(function ($sub) {
                //             $sub->select(DB::raw(1))
                //                 ->from(
                //                     DB::raw("
                //                         (
                //                             SELECT No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat WHERE Status = 'Y'
                //                             UNION ALL
                //                             SELECT No_Transaksi FROM Transaksi_Sakit_Izin WHERE Status = 'Y'
                //                             UNION ALL
                //                             SELECT No_Transaksi FROM Transaksi_Cuti WHERE Status = 'Y'
                //                         ) as t_check
                //                     "),
                //                 )
                //                 ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                //         })
                //         ->whereNotExists(function ($sub) {
                //             $sub->select(DB::raw(1))
                //                 ->from(
                //                     DB::raw("
                //                         (
                //                             SELECT No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat_Detail b WHERE b.Flag_Approval IN ('Y','P')
                //                             UNION ALL
                //                             SELECT No_Transaksi FROM Transaksi_Sakit_Izin_Detail b WHERE b.Flag_Approval IN ('Y','P')
                //                             UNION ALL
                //                             SELECT No_Transaksi FROM Transaksi_Cuti_Detail b WHERE b.Flag_Approval IN ('Y','P')
                //                         ) as t_check
                //                     "),
                //                 )
                //                 ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                //         })
                //         ->whereNotExists(function ($sub) use ($batasHariPengajuan) {
                //             $sub->select(DB::raw(1))
                //                 ->from(
                //                     DB::raw("
                //                         (
                //                             SELECT No_Transaksi, Tanggal, Jam FROM Transaksi_Terlambat_Pulang_Cepat
                //                             UNION ALL
                //                             SELECT No_Transaksi, Tanggal, Jam FROM Transaksi_Sakit_Izin
                //                         ) as t
                //                     "),
                //                 )
                //                 ->whereColumn('t.No_Transaksi', 'a.No_Transaksi')
                //                 ->whereRaw(
                //                     "
                //                         DATEDIFF(
                //                             DAY,
                //                             CAST(t.Tanggal AS DATETIME) + CAST(t.Jam AS DATETIME),
                //                             GETDATE()
                //                         ) >= ?
                //                     ",
                //                     [$batasHariPengajuan],
                //                 );
                //         })

                //         ->select('a.*', 'b.order_flow')
                //         ->orderBy('a.No_Transaksi')
                //         ->count();
                // }
                // dd($datasPending);
            } else {
            }

            // dd($Id_Division, $Id_Level, $UserID_Web);

            $allDynamicMenusQuery = DB::table('N_HRIS_Menus as m')
                ->where('m.Kode_Perusahaan', '001')
                ->orderByRaw('COALESCE(NULLIF(Sort_Header, 0), 999) ASC')
                ->orderByRaw('CASE WHEN Sort_Menu IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('Sort_Menu')
                ->orderBy('Id_Menu')
                ->where(function ($query) use ($UserID_Web) {
                    $query
                        ->whereRaw("UPPER(LTRIM(RTRIM(COALESCE(m.Is_Global_Menu, '')))) = 'Y'")
                        ->orWhereExists(function ($subQuery) use ($UserID_Web) {
                            $subQuery
                                ->select(DB::raw(1))
                                ->from('HRIS_Page_Access as pa')
                                ->where('pa.Flag_Aktif', 'Y')
                                ->where('pa.UserID_Web', $UserID_Web)
                                ->whereRaw(
                                    "LOWER(REPLACE(LTRIM(RTRIM(COALESCE(pa.Jenis_page, ''))), ' ', '')) = LOWER(REPLACE(LTRIM(RTRIM(COALESCE(m.Jenis_page, ''))), ' ', ''))",
                                );
                        });
                });

            if (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Aktif')) {
                $allDynamicMenusQuery->where('m.Flag_Aktif', 'Y');
            } elseif (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Active')) {
                $allDynamicMenusQuery->where('m.Flag_Active', 'Y');
            }

            $allDynamicMenus = $allDynamicMenusQuery->get();

            // dd($allDynamicMenus);
            // dd($allDynamicMenus, $accessPage, $nama_Approver, $nama_mengapprove, $datasPendingV2);
            return inertia('ESS/Dashboard/Personal/uDash', [
                'title' => $title,
                'LeaderTeam' => $LeaderTeam,
                'accessPage' => $accessPage ?? [],
                'allDynamicMenus' => $allDynamicMenus,
                'namaKaryawan' => $namaKaryawan,
                'shiftHariIni' => $absen_hari_ini->Nama_Shift ?? 'NO SHIFT',
                'Jam_Masuk' => $absen_hari_ini->Jam_Masuk ?? '00:00',
                'Jam_Keluar' => $absen_hari_ini->Jam_Keluar ?? '00:00',
                'shiftBesok' => $absen_besok->Nama_Shift ?? 'NO SHIFT',
                'totalTerlambat' => $total_keterlambatan_menit ?? 0,
                'totalLembur' => $total_lembur_jam ?? 0,
                'sisaCuti' => $sisaCuti ?? 0,
                'judulBerita' => $berita->Judul,
                'isiBerita' => $berita->Content,
                'nama_anggota' => $nama_anggota,
                'nama_tergabung' => $nama_tergabung,
                'nama_Approver' => $nama_Approver,
                'nama_mengapprove' => $nama_mengapprove,
                'jumlahAntrianPengajuan' => $datasPendingV2 ?? 0,
                'cutoffPeriod' => $this->uDashReadModelService->serializeCutoffRange($cutoffRange),
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error(
                'Gagal menampilkan page Dashboard: '.$e->getMessage().'Karyawan : '.$Kode_KaryawanReal,
            );
            Alert::error('error', 'Terjadi Error saat menampilkan page Dashboard!');

            return abort(500);
        }
    }

    public function getRingkasanTeam(Request $request)
    {
        // dd($request);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'search' => ['nullable', 'string'],
        ]);

        try {
            $currentPage = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 5);
            $search = trim((string) ($validated['search'] ?? ''));

            $cutoffRange = $this->uDashReadModelService->getCutoffRange(Carbon::now());
            $startWeek = $cutoffRange['start_date'];
            $endWeek = $cutoffRange['end_date'];

            $Kode_KaryawanReal = Auth::user()->karyawan->Kode_Karyawan ?? null;

            $namaAnggotaQuery = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->where('t.Kode_Karyawan_Team', $Kode_KaryawanReal)
                ->where('k.Aktif', 'Y')
                ->where('t.Kode_Karyawan', '<>', $Kode_KaryawanReal)
                ->select('k.UserID_Absen', 'k.Kode_Karyawan', 'k.Nama');

            if ($search !== '') {
                $namaAnggotaQuery->where('k.Nama', 'like', "%{$search}%");
            }

            $totalAnggota = (clone $namaAnggotaQuery)->distinct()->count('k.Kode_Karyawan');

            $nama_anggota = $namaAnggotaQuery->distinct()->orderBy('k.Nama')->forPage($currentPage, $perPage)->get();

            if ($nama_anggota->isEmpty()) {
                $lastPage = (int) max(1, (int) ceil($totalAnggota / $perPage));

                return response()->json([
                    'status' => 200,
                    'data' => [],
                    'pagination' => [
                        'current_page' => min($currentPage, $lastPage),
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $totalAnggota,
                    ],
                ]);
            }

            $kode_anggota = $nama_anggota->pluck('Kode_Karyawan')->toArray();
            $IDAbsen_anggota = $nama_anggota->pluck('UserID_Absen')->toArray();
            // dd($IDAbsen_anggota);

            // $userIds = array_column($users, 'UserID_Absen');
            $userIds = $IDAbsen_anggota;
            // $datePlaceholders = implode(',', array_fill(0, count($dateRange), '?'));
            $userQuery = implode(',', $userIds);
            // dd($startWeek, $endWeek, $userQuery);
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','$startWeek','$endWeek','$userQuery', '', ''";
            // $resultData = DB::select($query);
            $sourceReuqest = 'uDash-controller-ringkasan-team';
            $tglMulai = $startWeek;
            $tglSelesai = $endWeek;
            $listUserId = $userQuery;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $resultData = $response['data'] ?? [];

            $daftar_anggota = collect($resultData)
                ->pluck('Nama') // ambil semua kolom Tanggal

                ->unique() // buang duplikat
                ->values(); // reset key biar rapi

            // dd($daftar_anggota);

            $tanggal_absens = collect($resultData)
                ->pluck('Tanggal') // ambil semua kolom Tanggal
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d')) // format ke Y-m-d
                ->unique() // buang duplikat
                ->values(); // reset key biar rapi

            // dd($tanggal_absens);

            // pre-load data terlambat
            $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal', 'b.Kode_Karyawan')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('b.Jenis', 'pulangCepat')
                ->where('b.Flag_Approval', 'Y')
                ->where('a.Status', null)
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
                ->get();

            // --- Ambil data sakit per karyawan
            $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
                ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('Jenis', 'sakit')
                ->where('b.Flag_Approval', 'Y')
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
                })
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_sakit'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_sakit', 'b.Kode_Karyawan'); // hasil: [kode_karyawan => total]

            // --- Ambil data izin per karyawan
            $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
                ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('Jenis', 'izinFull')
                ->where('b.Flag_Approval', 'Y')
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
                })
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_izin'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_izin', 'b.Kode_Karyawan');

            // --- Ambil data cuti per karyawan
            $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
                ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->join('Transaksi_Cuti_Det as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('b.Flag_Approval', 'Y')
                // ->where(function($query) use ($tanggal_absens) {
                //     $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
                //         ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
                // })
                ->where(function ($query) use ($tanggal_absens) {
                    $query->whereIn(DB::raw('CAST(c.Tanggal_Cuti AS DATE)'), $tanggal_absens);
                })
                ->whereNull('a.status')
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_cuti'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_cuti', 'b.Kode_Karyawan');

            // dd($cuti_data);

            $groupedByKaryawan = collect($resultData)->groupBy('Kode_Karyawan');

            // sebelum membuat $rekap — tentukan batas perhitungan (pilih yg lebih awal: endWeek atau sekarang)
            $endDateForCalculation = $endWeek->lte(Carbon::now())
                ? $endWeek->copy()->endOfDay()
                : Carbon::now()->endOfDay();

            // kemudian saat membuat $rekap, tambahkan use $endDateForCalculation
            // $today = Carbon::today();
            $today = Carbon::now();
            $hariLibur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->count();
            // dd($today);
            $rekap = collect($resultData)
                ->groupBy('Kode_Karyawan')
                ->map(function ($records, $kodeKaryawan) use (
                    $pulang_data,
                    $sakit_data,
                    $izin_data,
                    $cuti_data,
                    $endDateForCalculation,
                    $hariLibur,
                    $today,
                ) {
                    $total_keterlambatan_menit = (int) round(
                        $records->sum(fn ($record) => (float) ($record->RealLate ?? ($record->Real_Late ?? 0))),
                    );
                    $total_pulang_cepat_menit = 0;
                    $total_lembur_jam = (float) round(
                        $records->sum(fn ($record) => (float) ($record->Koefisien_OT ?? ($record->KoefisienOT ?? 0))),
                        2,
                    );

                    foreach ($records as $record) {
                        $tanggal = Carbon::parse($record->Tanggal)->toDateString();

                        if (is_null($record->CheckIn)) {
                            $sakit = $sakit_data[$kodeKaryawan] ?? 0;
                            $izin = $izin_data[$kodeKaryawan] ?? 0;
                            $cuti = $cuti_data[$kodeKaryawan] ?? 0;

                            if ($sakit > 0 || $izin > 0 || $cuti > 0) {
                                continue;
                            }

                            continue; // dianggap alpha
                        }
                        // 2. pulang cepat
                        if (! is_null($record->CheckOut) && ! empty($record->Jam_Keluar)) {
                            $checkout_carbon = Carbon::parse($record->CheckOut);
                            $format_jam_pulang = strlen($record->Jam_Keluar) === 5 ? 'H:i' : 'H:i:s';
                            $jam_pulang_carbon = Carbon::createFromFormat(
                                'Y-m-d '.$format_jam_pulang,
                                $checkout_carbon->format('Y-m-d').' '.$record->Jam_Keluar,
                            );

                            $pengajuan_pulang = collect($pulang_data)->firstWhere('Tanggal', $record->Tanggal);

                            if (! $pengajuan_pulang && $checkout_carbon < $jam_pulang_carbon) {
                                $total_pulang_cepat_menit += $checkout_carbon
                                    ->copy()
                                    ->seconds(0)
                                    ->diffInMinutes($jam_pulang_carbon->copy()->seconds(0));
                            }
                        }
                    }

                    // --- hitung hari & hadir sampai endDateForCalculation ---
                    $recordsUpToNow = $records->filter(
                        fn ($r) => Carbon::parse($r->Tanggal)->lte($endDateForCalculation),
                    );

                    // $total_hari = $recordsUpToNow->reject(function ($r) use ($excludedShifts) {
                    //     $shift = strtoupper(trim((string) ($r->Nama_Shift ?? '')));
                    //     return in_array($shift, $excludedShifts, true);
                    // })->count();

                    // $total_hadir = min($total_hari, $recordsUpToNow->filter(fn($r) => !is_null($r->CheckIn) && $r->CheckIn !== '')->count() +  $hariLibur);

                    // Total hari kerja berdasarkan StatusHari dari SP (exclude OFF)
                    $total_hari = $recordsUpToNow
                        ->filter(fn ($r) => ! Str::startsWith(strtoupper(trim((string) ($r->StatusHari ?? ''))), 'OFF'))
                        ->count();

                    // Total hadir: CheckIn pada hari kerja yang sama (berdasarkan StatusHari)
                    $total_hadir = $recordsUpToNow
                        ->filter(fn ($r) => ! Str::startsWith(strtoupper(trim((string) ($r->StatusHari ?? ''))), 'OFF'))
                        ->filter(fn ($r) => ! is_null($r->CheckIn) && $r->CheckIn !== '')
                        ->count();

                    // Jika mau tambahkan hari libur
                    $total_hadir = min($total_hari, $total_hadir + $hariLibur);

                    // ==============================
                    // Ambil shift & status hari ini
                    // ==============================
                    $recordToday = $records->filter(fn ($r) => Carbon::parse($r->Tanggal)->isSameDay($today))->first();

                    // dd($recordToday);

                    if ($recordToday) {
                        $shiftHariIni = $recordToday->Nama_Shift ?? 'NO SHIFT';
                        $statusHariIni = $recordToday->CheckIn ? 'Hadir' : 'Tidak Hadir';
                        $jamMasukHariIni = $recordToday->Jam_Masuk ?? '00:00';
                        $jamKeluarHariIni = $recordToday->Jam_Keluar ?? '00:00';

                        // kalau tidak ada CheckIn & tidak ada izin/sakit/cuti → anggap ALPHA
                        if (
                            is_null($recordToday->CheckIn) &&
                            ($sakit_data[$kodeKaryawan] ?? 0) == 0 &&
                            ($izin_data[$kodeKaryawan] ?? 0) == 0 &&
                            ($cuti_data[$kodeKaryawan] ?? 0) == 0
                        ) {
                            $statusHariIni = 'Tidak Hadir';
                        }
                    } else {
                        $shiftHariIni = 'NO SHIFT';
                        $statusHariIni = 'Tidak Hadir';
                        $jamMasukHariIni = '00:00';
                        $jamKeluarHariIni = '00:00';
                    }

                    return [
                        'kode_karyawan' => $kodeKaryawan,
                        'nama' => $records->first()->Nama ?? null,
                        'total_keterlambatan_menit' => $total_keterlambatan_menit,
                        'total_pulang_cepat_menit' => $total_pulang_cepat_menit,

                        'total_lembur_jam' => $total_lembur_jam,
                        'total_sakit' => $sakit_data[$kodeKaryawan] ?? 0,
                        'total_izin' => $izin_data[$kodeKaryawan] ?? 0,
                        'total_cuti' => $cuti_data[$kodeKaryawan] ?? 0,
                        'total_hadir' => $total_hadir,
                        'total_hari' => $total_hari,
                        'label_hadir' => "{$total_hadir}/{$total_hari} Hadir",
                        // tambahan baru
                        'shift_hari_ini' => $shiftHariIni,
                        'status_hari_ini' => $statusHariIni,
                        'jam_masuk_hari_ini' => $jamMasukHariIni,
                        'jam_keluar_hari_ini' => $jamKeluarHariIni,
                    ];
                })
                ->values();

            $lastPage = (int) max(1, (int) ceil($totalAnggota / $perPage));

            return response()->json([
                'status' => 200,
                'data' => $rekap,
                'pagination' => [
                    'current_page' => min($currentPage, $lastPage),
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $totalAnggota,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data data Absens getRingkasanTeam'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data data Absens',
                ],
                500,
            );
        }
    }

    // public function getDateAbsen(Request $request){
    //     // Dapatkan data karyawan yang sedang login
    //     try{
    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         $requestDate = $request->tanggal;
    //         $nowDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

    //         if ($requestDate && $request->jenis == 'next') {
    //             // Mendapatkan Senin minggu depan
    //             $date = $nowDate->next(Carbon::MONDAY)->next(Carbon::MONDAY);
    //         } else if ($requestDate && $request->jenis == 'prev') {
    //             // Mendapatkan Senin minggu lalu
    //             $date = $nowDate->previous(Carbon::MONDAY);
    //         } else {
    //             $date = Carbon::now();
    //         }
    //         $startWeek = $date->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
    //         $endWeek = $date->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
    //         // Tentukan rentang tanggal

    //         // Ambil data absensi utama dari stored procedure
    //         $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
    //         $absen_data = collect(DB::select($query));

    //         // Ambil semua tanggal unik dari data absen untuk dijadikan filter
    //         $tanggal_absens = $absen_data->pluck('Tanggal')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));

    //          $hariLibur = DB::table('HRIS_Hari_Libur')
    //         ->whereBetween('Tanggal', [$startWeek, $endWeek])
    //         ->get();
    //         // --- Pre-load Semua Data Transaksi dalam Beberapa Query ---

    //         // 1. Pre-load data lembur
    //         $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Lembur_dari as mulai', 'b.Tanggal_Lembur_Sampai as selesai')
    //             ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();
    //         // 2. Pre-load data sakit/izin
    //         $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Sakit_Izin_dari as mulai', 'b.Tanggal_Sakit_Izin_Sampai as selesai')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "sakit")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();
    //         $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Sakit_Izin_dari as mulai', 'b.Tanggal_Sakit_Izin_Sampai as selesai')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "izinFull")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //         // 3. Pre-load data cuti
    //         $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Cuti_dari as mulai', 'b.Tanggal_Cuti_Sampai as selesai')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("b.Jenis", "cuti")
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //         $cutiHutang_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Cuti_dari as mulai', 'b.Tanggal_Cuti_Sampai as selesai')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "cutiHutang")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //             // 4. Pre-load data terlambat/pulang cepat
    //             $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //                 ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
    //                 ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //                 ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //                 ->where("b.Jenis", "pulangCepat")
    //                 ->where('b.Flag_Approval', 'Y')
    //                 ->where("a.Status", null)
    //                 ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //                 ->get();

    //             $terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //                 ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
    //                 ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //                 ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //                 ->where('b.Flag_Approval', 'Y')
    //                 ->where("b.Jenis", "terlambat")
    //                 ->where("a.Status", null)
    //                 ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //                 ->get();

    //             // --- Proses Data Absen dengan Data yang Sudah Di-preload ---

    //             $result = $absen_data->map(function ($item) use ($hariLibur,$lembur_data, $sakit_data,$izin_data, $cuti_data,$cutiHutang_data, $pulang_data, $terlambat_data) {
    //             $tanggal_formatted = Carbon::parse($item->Tanggal)->format('Y-m-d');

    //             // Cari data di dalam Collection yang sudah di-load di awal
    //             $lembur = $lembur_data->where(function($item) use ($tanggal_formatted){

    //             $mulai = Carbon::parse($item->mulai)->format('Y-m-d');
    //             $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //             return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $sakit = $sakit_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             // dd($sakit);
    //             $izin = $izin_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $cuti = $cuti_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $cutiHutang = $cutiHutang_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $pulang = $pulang_data
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });

    //             $terlambat = $terlambat_data
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });
    //             $Libur = $hariLibur
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });
    //             // dd($sakit);

    //             return [
    //                 'Nama_Shift' => $item->Nama_Shift,
    //                 'CheckIn' => $item->CheckIn,
    //                 'CheckOut' => $item->CheckOut,
    //                 'Jam_Masuk' => $item->Jam_Masuk,
    //                 'Jam_Keluar' => $item->Jam_Keluar,
    //                 'Tanggal' => $item->Tanggal,
    //                 'Lembur_No_Transaksi' => $lembur->No_Transaksi ?? null,
    //                 'Lembur_Mulai' => $lembur->mulai ?? null,
    //                 'Lembur_Selesai' => $lembur->selesai ?? null,
    //                 'Sakit_No_Transaksi' => $sakit->No_Transaksi ?? null,
    //                 'Sakit_Mulai' => $sakit->mulai ?? null,
    //                 'Sakit_Selesai' => $sakit->selesai ?? null,
    //                 'Izin_No_Transaksi' => $izin->No_Transaksi ?? null,
    //                 'Izin_Mulai' => $izin->mulai ?? null,
    //                 'Izin_Selesai' => $izin->selesai ?? null,
    //                 'Cuti_No_Transaksi' => $cuti->No_Transaksi ?? null,
    //                 'Cuti_Mulai' => $cuti->mulai ?? null,
    //                 'Cuti_Selesai' => $cuti->selesai ?? null,
    //                 'CutiHutang_No_Transaksi' => $cutiHutang->No_Transaksi ?? null,
    //                 'CutiHutang_Mulai' => $cutiHutang->mulai ?? null,
    //                 'CutiHutang_Selesai' => $cutiHutang->selesai ?? null,
    //                 'Pulang_No_Transaksi' => $pulang->No_Transaksi ?? null,
    //                 'Pulang_Tanggal' => $pulang->Tanggal ?? null,
    //                 'Terlambat_No_Transaksi' => $terlambat->No_Transaksi ?? null,
    //                 'Terlambat_Tanggal' => $terlambat->Tanggal ?? null,
    //                 'hariLibur' => $Libur->Nama_Hari_Libur ?? null
    //             ];
    //         });

    //         // dd($result);

    //         return response()->json([
    //             'status' => 200,
    //             'data' => $result ?? []
    //         ]);
    //     }catch(\Throwable $e){
    //         Log::channel('uDashLog')->error('Gagal Mengambil data data Absens getDateAbsen'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data data Absens"
    //         ], 500);
    //     }
    // }

    public function getDateAbsen(Request $request)
    {
        try {
            $user = Auth::user();
            $UserID_Absen = $user->karyawan->UserID_Absen;
            $Kode_Karyawan = $user->karyawan->Kode_Karyawan;

            // 1. Setup periode kalender mingguan (Minggu-Sabtu).
            $requestDate = $request->tanggal;
            $baseDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

            $startWeek = $baseDate->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();
            $endWeek = $baseDate->copy()->endOfWeek(Carbon::SATURDAY)->toDateString();
            $cutoffRange = $this->uDashReadModelService->getCutoffRange($baseDate);

            // 2. Ambil Data Absensi Utama (SP)
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            // $absen_data = collect(DB::select($query));
            $sourceReuqest = 'uDash-controller-getDateAbsen';
            $tglMulai = $startWeek;
            $tglSelesai = $endWeek;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absen_data = collect($response['data']) ?? collect([]);

            // 3. Ambil Hari Libur
            $hariLibur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->get();

            $cutiBersamaByDate = $this->getCutiBersamaByDateForKaryawan($Kode_Karyawan, $startWeek, $endWeek);

            // 4. Future-Proof & Efisien: Ambil SEMUA Transaksi Approved dari VIEW
            // Daripada query 5 tabel berbeda, kita ambil sekali saja dari View.
            // View 'N_HRIS_VW_All_Transactions' diasumsikan sudah berisi tanggal detail (Mulai & Selesai) yang benar.

            $allTransactions = $this->uDashReadModelService->getApprovedTransactionsForCalendar(
                $Kode_Karyawan,
                $startWeek,
                $endWeek,
            );

            if ($allTransactions === null) {
                $allTransactions = DB::table('N_HRIS_VW_All_Transactions')
                    ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->where(function ($q) use ($startWeek, $endWeek) {
                        $q->whereDate('Tanggal_Mulai', '<=', $endWeek)->whereDate('Tanggal_Selesai', '>=', $startWeek);
                    })
                    ->where(function ($q) {
                        $q->where('Flag_Approval', 'Y')->orWhere('Flag_Approval', 'Y');
                    })
                    ->get();
            }

            // 5. Mapping Data (Looping Absen Harian)
            $result = $absen_data->map(function ($item) use ($hariLibur, $allTransactions, $cutiBersamaByDate) {
                $currentDate = Carbon::parse($item->Tanggal)->format('Y-m-d');

                // Cari Libur
                $libur = $hariLibur->first(fn ($l) => Carbon::parse($l->Tanggal)->format('Y-m-d') === $currentDate);
                $DayOff = str_starts_with($item->StatusHari, 'OFF') ? true : false;
                $liburTitle = $libur->Nama_Hari_Libur ?? null;

                if ($DayOff) {
                    $keterangan = explode('-', $item->StatusHari)[1] ?? $item->StatusHari;

                    if ($keterangan === 'Cuti_Bersama') {
                        $liburTitle = $cutiBersamaByDate[$currentDate] ?? $item->StatusHari;
                    }

                    $libur = (object) [
                        'Nama_Hari_Libur' => $keterangan == 'Hari_Libur'
                                ? $libur->Nama_Hari_Libur ?? $item->StatusHari
                                : $item->StatusHari,
                        'Nama_Event_Libur' => $liburTitle,
                    ];
                }
                // Filter transaksi yang mencakup hari ini ($currentDate)

                $todaysTx = $allTransactions->filter(function ($tx) use ($currentDate) {
                    if (! empty($tx->Tanggal_Item)) {
                        return Carbon::parse($tx->Tanggal_Item)->format('Y-m-d') === $currentDate;
                    }

                    $tanggalTransaksi = TanggalTransaksiIzin::ringkas($tx->No_Transaksi, false)['tanggal'];
                    if (empty($tanggalTransaksi)) {
                        return false;
                    }

                    return collect($tanggalTransaksi)->contains(function ($tgl) use ($currentDate) {
                        return Carbon::parse($tgl)->format('Y-m-d') === $currentDate;
                    });
                });

                // Helper untuk mengambil transaksi berdasarkan Tipe & SubTipe
                // Kita pakai first() karena asumsinya 1 hari cuma ada 1 transaksi per tipe
                $getTx = fn ($tipe, $subTipe = null) => $todaysTx->first(function ($tx) use ($tipe, $subTipe) {
                    $allowedTypes = collect((array) $tipe)
                        ->map(fn ($value) => strtolower(trim((string) $value)))
                        ->filter()
                        ->values()
                        ->all();
                    $allowedSubTypes = collect((array) $subTipe)
                        ->map(fn ($value) => strtolower(trim((string) $value)))
                        ->filter()
                        ->values()
                        ->all();

                    // Sesuaikan nama kolom Tipe/SubTipe dengan View Anda (misal: Tipe_Transaksi, Sub_Tipe)
                    $txType = strtolower(trim((string) ($tx->Tipe_Transaksi ?? '')));
                    $txSubType = strtolower(trim((string) ($tx->Sub_Tipe ?? '')));
                    $matchTipe = in_array($txType, $allowedTypes, true);
                    if (! empty($allowedSubTypes)) {
                        return $matchTipe && in_array($txSubType, $allowedSubTypes, true);
                    }

                    return $matchTipe;
                });

                // Mapping spesifik sesuai kunci JSON lama
                // Note: Pastikan 'Sub_Tipe' di View sesuai dengan value 'Jenis' di tabel detail lama

                $lembur = $getTx('Lembur');
                $sakit =
                    $getTx(['Sakit', 'IZIN_SAKIT'], ['sakit', 'sakit_izin']) ?? $getTx('Izin', ['sakit', 'sakit_izin']); // Handle jika view mengelompokkan beda
                $izin = $getTx('Izin', 'izinFull') ?? $getTx('Sakit', 'izinFull');
                $cuti = $getTx('Cuti');
                $cutiHutang = $getTx('Cuti', 'cutiHutang');

                // Untuk Terlambat/Pulang Cepat, biasanya Sub_Tipe nya 'pulangCepat' / 'terlambat'
                $pulang = $getTx(['Terlambat_Pulang_Cepat', 'TERLAMBAT'], 'pulangCepat');
                $terlambat = $getTx(['Terlambat_Pulang_Cepat', 'TERLAMBAT'], 'terlambat');

                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'CheckIn' => $item->CheckIn,
                    'CheckOut' => $item->CheckOut,
                    'Jam_Masuk' => $item->Jam_Masuk,
                    'Jam_Keluar' => $item->Jam_Keluar,
                    'RealLate' => (float) ($item->RealLate ?? ($item->Real_Late ?? 0)),
                    'RealEarly' => (float) ($item->RealEarly ?? ($item->Real_Early ?? 0)),
                    'Tanggal' => $item->Tanggal,

                    // Mapping Data Transaksi
                    'Lembur_No_Transaksi' => $lembur->No_Transaksi ?? null,
                    'Lembur_Mulai' => $lembur->Tanggal_Mulai ?? null,
                    'Lembur_Selesai' => $lembur->Tanggal_Selesai ?? null,

                    'Sakit_No_Transaksi' => $sakit->No_Transaksi ?? null,
                    'Sakit_Mulai' => $sakit->Tanggal_Mulai ?? null,
                    'Sakit_Selesai' => $sakit->Tanggal_Selesai ?? null,

                    'Izin_No_Transaksi' => $izin->No_Transaksi ?? null,
                    'Izin_Mulai' => $izin->Tanggal_Mulai ?? null,
                    'Izin_Selesai' => $izin->Tanggal_Selesai ?? null,

                    'Cuti_No_Transaksi' => $cuti->No_Transaksi ?? null,
                    'Cuti_Mulai' => $cuti->Tanggal_Mulai ?? null,
                    'Cuti_Selesai' => $cuti->Tanggal_Selesai ?? null,

                    'CutiHutang_No_Transaksi' => $cutiHutang->No_Transaksi ?? null,
                    'CutiHutang_Mulai' => $cutiHutang->Tanggal_Mulai ?? null,
                    'CutiHutang_Selesai' => $cutiHutang->Tanggal_Selesai ?? null,

                    'Pulang_No_Transaksi' => $pulang->No_Transaksi ?? null,
                    'Pulang_Tanggal' => $pulang->Tanggal_Mulai ?? null, // Pulang cepat biasanya 1 hari (start=end)

                    'Terlambat_No_Transaksi' => $terlambat->No_Transaksi ?? null,
                    'Terlambat_Tanggal' => $terlambat->Tanggal_Mulai ?? null,

                    'hariLibur' => $libur->Nama_Hari_Libur ?? null,
                    'hariLiburTitle' => $libur->Nama_Event_Libur ?? ($libur->Nama_Hari_Libur ?? null),
                ];
            });

            return response()->json([
                'status' => 200,
                'data' => $result ?? [],
                'period' => $this->uDashReadModelService->serializeCutoffRange($cutoffRange),
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data Absens getDateAbsen'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data Absens',
                ],
                500,
            );
        }
    }

    /**
     * Peta tanggal => judul cuti bersama untuk kalender karyawan.
     *
     * Isinya dipindahkan ke KalenderCutiBersama. Sampai perubahan ini metode ini
     * ADA DUA — di sini dan di AbsensiController — identik 1.174 byte sama persis,
     * dan keduanya memberi makan kalender yang dilihat karyawan.
     */
    private function getCutiBersamaByDateForKaryawan(string $kodeKaryawan, string $rangeStart, string $rangeEnd): array
    {
        return app(KalenderCutiBersama::class)->untukKaryawan($kodeKaryawan, $rangeStart, $rangeEnd);
    }

    private function parseJsonStringArray($raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($v) => is_scalar($v) && trim((string) $v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->values()
            ->all();
    }

    private function isKaryawanTargetedForCutiBersama(object $cb, $karyawanProfile, string $kodeKaryawan): bool
    {
        $filterKode = $this->parseJsonStringArray($cb->Filter_Kode_Karyawan ?? null);
        $filterCabang = $this->parseJsonStringArray($cb->Filter_Cabang ?? null);
        $filterDivisi = $this->parseJsonStringArray($cb->Filter_Divisi ?? null);
        $filterSubDivisi = $this->parseJsonStringArray($cb->Filter_Sub_Divisi ?? null);

        $hasKodeFilter = ! empty($filterKode);
        $hasUnitFilter = ! empty($filterCabang) || ! empty($filterDivisi) || ! empty($filterSubDivisi);

        $inKode = in_array($kodeKaryawan, $filterKode, true);

        $cabang = (string) ($karyawanProfile->Cabang_Untuk_Gaji ?? '');
        $divisi = (string) ($karyawanProfile->ID_Divisi ?? '');
        $subDivisi = (string) ($karyawanProfile->ID_Sub_Divisi ?? '');

        $inCabang = empty($filterCabang) || in_array($cabang, $filterCabang, true);
        $inDivisi = empty($filterDivisi) || in_array($divisi, $filterDivisi, true);
        $inSubDivisi = empty($filterSubDivisi) || in_array($subDivisi, $filterSubDivisi, true);

        $inUnit = $hasUnitFilter && $inCabang && $inDivisi && $inSubDivisi;
        $allTarget = ! $hasKodeFilter && ! $hasUnitFilter;

        return $inKode || $inUnit || $allTarget;
    }

    private function extractCutiBersamaDatesInRange(object $cb, string $rangeStart, string $rangeEnd): array
    {
        $start = Carbon::parse($rangeStart)->startOfDay();
        $end = Carbon::parse($rangeEnd)->startOfDay();
        $dates = [];

        $selectedDates = $this->parseJsonStringArray($cb->Selected_Dates ?? null);
        if (! empty($selectedDates)) {
            foreach ($selectedDates as $dateStr) {
                try {
                    $d = Carbon::parse($dateStr)->startOfDay();
                } catch (\Throwable $e) {
                    continue;
                }

                if ($d->betweenIncluded($start, $end)) {
                    $dates[] = $d->format('Y-m-d');
                }
            }

            return array_values(array_unique($dates));
        }

        $cbStart = Carbon::parse($cb->Tanggal_Mulai)->startOfDay();
        $cbEnd = Carbon::parse($cb->Tanggal_Selesai)->startOfDay();
        $iterStart = $cbStart->greaterThan($start) ? $cbStart->copy() : $start->copy();
        $iterEnd = $cbEnd->lessThan($end) ? $cbEnd->copy() : $end->copy();

        for ($d = $iterStart->copy(); $d->lte($iterEnd); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        return $dates;
    }

    public function getDataShift(Request $request)
    {
        try {
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $requestDate = $request->tanggal;
            $nowDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

            if ($requestDate && $request->jenis == 'next') {
                // Mendapatkan Senin minggu depan
                $date = $nowDate->next(Carbon::MONDAY)->next(Carbon::MONDAY);
            } elseif ($requestDate && $request->jenis == 'prev') {
                // Mendapatkan Senin minggu lalu
                $date = $nowDate->previous(Carbon::MONDAY);
            } else {
                $date = Carbon::now();
            }
            $startWeek = $date->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $endWeek = $date->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
            // Tentukan rentang tanggal

            // Ambil data absensi utama dari stored procedure
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";

            $sourceReuqest = 'uDash-controller-getDataShift';
            $tglMulai = $startWeek;
            $tglSelesai = $endWeek;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $query = $response['data'] ?? [];

            $absen_data = collect($query)->map(function ($item) {
                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'Jam_Masuk' => $item->Jam_Masuk,
                    'Jam_Keluar' => $item->Jam_Keluar,
                    'Tanggal' => $item->Tanggal,
                ];
            });

            return response()->json([
                'status' => 200,
                'data' => $absen_data ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data shift getDataShift'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data shift',
                ],
                500,
            );
        }
    }

    // public function getAllIzin(){
    //     try{

    //         $perPage = 4;
    //         $currentPage = request()->get('page', 1);
    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         $query = "
    //                     WITH
    //                         cte AS (
    //                         SELECT
    //                             CAST(CONCAT(CAST(cti.Tanggal AS DATE),' ', cti.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             ct.No_Transaksi,
    //                             ct.Jenis as Tipe_Izin,
    //                             ct.Tanggal_Cuti_Dari AS Tanggal_Mulai,
    //                             ct.Tanggal_Cuti_Sampai AS Tanggal_Selesai,
    //                             ct.Alasan,
    //                             ct.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = ct.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN ct.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN ct.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN ct.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN ct.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             NULL AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = ct.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Cuti_Detail AS ct ON k.Kode_Karyawan = ct.Kode_Karyawan
    //                             INNER JOIN Transaksi_Cuti AS cti ON ct.No_Transaksi = cti.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ?
    //                             and cti.Status is null
    //                             UNION ALL
    //                             -- Query untuk Transaksi_Sakit_Izin_Detail
    //                             SELECT
    //                             CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             tsi.No_Transaksi,
    //                             tsi.Jenis as Tipe_Izin,
    //                             tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                             tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                             tsi.Alasan,
    //                             tsi.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = tsi.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN tsi.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             NULL AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = tsi.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                             INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ?
    //                             and tsip.Status is null
    //                             UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                             SELECT
    //                             CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             ttp.No_Transaksi,
    //                             ttp.Jenis as Tipe_Izin,
    //                             ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                             NULL AS Tanggal_Selesai,
    //                             ttp.Alasan,
    //                             ttp.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = ttp.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN ttp.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             ttp.Jam AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = ttp.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                             INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ? and ttpp.Status is null
    //                         )
    //                         SELECT
    //                         *
    //                         FROM
    //                         cte
    //                         where
    //                         MaxFlow <> 0
    //                         ORDER BY
    //                         tanggal_create desc
    //         ";
    //         $result = DB::select($query, [$Kode_Karyawan, $Kode_Karyawan, $Kode_Karyawan]);
    //         $collection = collect($result);
    //         $slice_collect = $collection->slice(($currentPage-1) * $perPage, $perPage)->values();

    //         $final_page = new LengthAwarePaginator(
    //             $slice_collect,
    //             $collection->count(),
    //             $perPage,
    //             $currentPage,
    //             ['path' => request()->url()]
    //         );

    //         return response()->json($final_page);
    //     }catch(\Throwable $e){
    //         Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data izin "
    //         ], 500);
    //     }
    // }

    public function getAllIzin()
    {
        try {
            $perPage = 4;
            $page = request()->get('page', 1);
            $user = Auth::user();
            $kodeKaryawan = $user->karyawan->Kode_Karyawan;

            $v2Paginator = $this->uDashReadModelService->getAllIzinV2($kodeKaryawan, (int) $page, $perPage);
            if ($v2Paginator !== null) {
                return response()->json($v2Paginator);
            }

            // 1. Query Utama ke View Transaksi
            // Ini menggantikan UNION ALL yang panjang. View ini harus berisi semua jenis izin/cuti.
            $query = DB::table('N_HRIS_VW_All_Transactions')->where('Kode_Karyawan_Requester', $kodeKaryawan);

            // Hitung total untuk pagination
            $total = $query->count();

            // Ambil data sesuai halaman (Offset & Limit di Database, bukan di PHP)
            $items = $query->orderBy('Created_At', 'DESC')->forPage($page, $perPage)->get();

            // 2. Eager Load Approval Data
            // Ambil detail approval hanya untuk transaksi yang tampil di halaman ini
            $transactionIds = $items->pluck('No_Transaksi')->toArray();
            $approvals = collect();

            if (! empty($transactionIds)) {
                $approvals = DB::table('N_HRIS_VW_Approval_Request')
                    ->whereIn('No_Transaksi', $transactionIds)
                    ->orderBy('Request_Order_Flow', 'ASC')
                    ->get()
                    ->groupBy('No_Transaksi');
            }

            // 3. Mapping Data (Replikasi Logika SQL Lama ke PHP)
            // Kita manipulasi agar output JSON key-nya sama persis dengan query lama
            $formattedData = $items->map(function ($item) use ($approvals) {
                $currApprovals = $approvals->get($item->No_Transaksi, collect());

                // --- REPLIKASI LOGIKA 'MAGIC' DARI QUERY LAMA ---

                // Hitung Max Flow
                $maxFlow = $currApprovals->max('Request_Order_Flow') ?? 0;

                // Hitung Current Flow
                $lastAction = $currApprovals->whereNotNull('Flag_Approval')->sortByDesc('Request_Order_Flow')->first();
                $currentFlow = $lastAction ? $lastAction->Request_Order_Flow : 0;

                // Tentukan Status ('Selesai', 'Ditolak', 'Diproses', 'NO')
                $statusText = 'NO';
                // Cek apakah ada reject
                $rejector = $currApprovals->firstWhere('Flag_Approval', 'T');
                // Cek apakah semua approved
                $isFullyApproved =
                    $currApprovals->isNotEmpty() && $currApprovals->every(fn ($a) => $a->Flag_Approval == 'Y');
                // Cek pending (yang null flagnya)
                $pendingApprover = $currApprovals->sortBy('Request_Order_Flow')->firstWhere('Flag_Approval', null);

                // Logika SQL: CASE WHEN Flag='Y' THEN 'Selesai' ...
                // Karena di View header biasanya sudah ada status final, kita pakai logika detail approval agar akurat 100% dgn lama
                if ($isFullyApproved || $item->Flag_Approval == 'Y') {
                    $statusText = 'Selesai';
                } elseif ($rejector || $item->Flag_Approval == 'T') {
                    $statusText = 'Ditolak';
                } elseif ($pendingApprover) {
                    $statusText = 'Diproses';
                }

                // Tentukan Field 'Approver' (Logic unik query lama)
                // SQL Lama: Y='DiSetujui', T=NamaPenolak, Else=NamaPendingApprover
                $approverField = null;
                if ($statusText == 'Selesai') {
                    $approverField = 'DiSetujui';
                } elseif ($rejector) {
                    $approverField = $rejector->Current_Approver_Kode; // Nama yang menolak
                } elseif ($pendingApprover) {
                    $approverField = $pendingApprover->Current_Approver_Kode; // Nama yang sedang menunggu
                }

                // Status List (String Aggregation)
                $statusList = $currApprovals
                    ->map(function ($row) {
                        $flag = $row->Flag_Approval ?? 'N';

                        return "{$row->Current_Approver_Kode}: {$flag}";
                    })
                    ->implode(', ');

                // Return array dengan Key yang SAMA PERSIS dengan Query SQL Lama
                return [
                    'tanggal_create' => $item->Created_At,
                    'Kode_Karyawan' => $item->Kode_Karyawan_Requester,
                    'Nama' => $item->Nama_Requester,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Tipe_Izin' => $item->Sub_Tipe, // Asumsi nama kolom di View
                    'Tanggal_Mulai' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Alasan' => $item->Deskripsi, // Asumsi Deskripsi adalah Alasan
                    'Lampiran' => $item->File_Path,
                    'currentFlow' => $currentFlow,
                    'MaxFlow' => $maxFlow,
                    'status' => $statusText,
                    'Approver' => $approverField,
                    'Jam_Mulai' => $item->Jam_Diajukan, // Null jika cuti, ada isinya jika pulang cepat
                    'StatusList' => $statusList,
                ];
            });

            // 4. Buat Paginator
            $final_page = new LengthAwarePaginator($formattedData, $total, $perPage, $page, [
                'path' => request()->url(),
            ]);

            return response()->json($final_page);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin',
                ],
                500,
            );
        }
    }

    public function getRiwayatAbsen(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'tanggal' => ['nullable', 'date'], // filter dari tanggal
            // 'end_date'   => ['nullable', 'date'], // filter sampai tanggal
        ]);

        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $perPage = $validated['per_page'] ?? 7;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;

            $riwayatPaginator = $this->uDashReadModelService->getRiwayatAbsenData(
                $Kode_Karyawan,
                $request->tanggal,
                (int) $page,
                (int) $perPage,
            );

            if ($riwayatPaginator !== null) {
                return response()->json([
                    'status' => 200,
                    'data' => $riwayatPaginator,
                ]);
            }

            // dd($page);
            // --- COUNT distinct tanggal ---

            $queryCount = '
                SELECT COUNT(DISTINCT CAST(a.CheckTime AS DATE)) AS total
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsCount = [$Kode_Karyawan];

            if ($request->tanggal) {
                $queryCount .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsCount[] = $request->tanggal;
                // $paramsCount[] = $request->end_date;
            }

            $total = (int) DB::selectOne($queryCount, $paramsCount)->total;

            // --- DATA ---
            $queryData = '
                SELECT
                    CAST(a.CheckTime AS DATE) AS Tanggal,
                    COUNT(*) AS JumlahAbsensi
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsData = [$Kode_Karyawan];

            if ($request->tanggal) {
                $queryData .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsData[] = $request->tanggal;
                // $paramsData[] = $request->end_date;
            }

            $queryData .= '
                GROUP BY CAST(a.CheckTime AS DATE)
                ORDER BY Tanggal DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ';

            $paramsData[] = $offset;
            $paramsData[] = $perPage;

            $results = DB::select($queryData, $paramsData);

            $final_page = new \Illuminate\Pagination\LengthAwarePaginator(collect($results), $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $final_page,
            ]);
        } catch (\Exception $e) {
            \Log::channel('uDashLog')->error('Gagal ambil riwayat absen: '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Terjadi kesalahan saat mengambil data',
                ],
                500,
            );
        }
    }

    public function getChartLembur(Request $request)
    {
        try {
            $tahun = (int) ($request->year ?? Carbon::now()->format('Y'));
            $userIdAbsen = (string) (Auth::user()->karyawan->UserID_Absen ?? '');
            $firstPeriod = $this->uDashReadModelService->getCutoffRangeForMonth($tahun, 1);
            $lastPeriod = $this->uDashReadModelService->getCutoffRangeForMonth($tahun, 12);
            $rangeStart = $firstPeriod['start_date']->toDateString();
            $rangeEnd = $lastPeriod['end_date']->toDateString();

            $sourceReuqest = 'uDash-controller-chart-lembur';
            $tglMulai = $rangeStart;
            $tglSelesai = $rangeEnd;
            $listUserId = $userIdAbsen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );
            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absen_data_raw = $response['data'] ?? [];
            $absenData =
                $userIdAbsen === ''
                    ? collect()
                    : collect(
                        // DB::select("Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''", [
                        //     $rangeStart,
                        //     $rangeEnd,
                        //     $userIdAbsen,
                        // ]),
                        $absen_data_raw,
                    );

            $perCutoff = $absenData
                ->filter(fn ($item) => ! empty($item->Tanggal))
                ->groupBy(fn ($item) => $this->uDashReadModelService->getCutoffPeriodKey((string) $item->Tanggal))
                ->map(fn ($items) => round(collect($items)->sum(fn ($row) => (float) ($row->Koefisien_OT ?? 0)), 2));

            $labels = [];
            $dataChart = [];
            $ranges = [];

            for ($i = 1; $i <= 12; $i++) {
                $period = $this->uDashReadModelService->getCutoffRangeForMonth($tahun, $i);
                $start = $period['start_date'];
                $end = $period['end_date'];

                $periode = $start->format('Y-m');
                $labels[] = $start->format('M');
                $dataChart[] = round((float) $perCutoff->get($periode, 0), 2);
                $ranges[] = $start->translatedFormat('d M').' - '.$end->translatedFormat('d M');
            }

            return response()->json([
                'status' => 200,
                'labels' => $labels,
                'data' => $dataChart,
                'ranges' => $ranges,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getDataCHartLembur'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin ',
                ],
                500,
            );
        }
    }

    public function getAbsenDetail(Request $request)
    {
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $tanggal = $request->tanggal;
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;

            $absenDetail = $this->uDashReadModelService->getAbsenDetailData(
                $Kode_Karyawan,
                (string) $UserID_Absen,
                (string) $tanggal,
            );

            if ($absenDetail !== null) {
                return response()->json([
                    'status' => 200,
                    'tanggal' => $tanggal,
                    'data' => $absenDetail['data'],
                    'checkInOut' => $absenDetail['checkInOut'],
                ]);
            }

            $query = '
                SELECT a.CheckTime
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
                AND CAST(a.CheckTime AS DATE) = ?
                ORDER BY a.CheckTime ASC
            ';
            $results = DB::select($query, [$Kode_Karyawan, $tanggal]);

            // $queryCheckInOut = 'exec HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, ?, ?';
            // $resultCheckInOut = DB::select($queryCheckInOut, ['001', $tanggal, $tanggal, $UserID_Absen, '', '']);
            $sourceReuqest = 'uDash-controller-detail-absen';
            $tglMulai = $tanggal;
            $tglSelesai = $tanggal;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $resultCheckInOut = $response['data'] ?? [];

            $resultCheckInOut = collect($resultCheckInOut)->map(function ($item) {
                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'CheckIn' => $item->CheckIn,
                    'CheckOut' => $item->CheckOut,
                    'Jam_Masuk' => Carbon::parse(
                        Carbon::parse($item->Tanggal)->format('Y-m-d').
                            ' '.
                            Carbon::parse($item->Jam_Masuk)->format('H:i:s'),
                    ),
                    'Jam_Keluar' => $item->Jam_Keluar < $item->Jam_Masuk
                            ? Carbon::parse(
                                Carbon::parse($item->Tanggal)->format('Y-m-d').
                                    ' '.
                                    Carbon::parse($item->Jam_Keluar)->format('H:i:s'),
                            )->addDay()
                            : Carbon::parse(
                                Carbon::parse($item->Tanggal)->format('Y-m-d').
                                    ' '.
                                    Carbon::parse($item->Jam_Keluar)->format('H:i:s'),
                            ),
                    'Tanggal' => $item->Tanggal,
                ];
            });

            return response()->json([
                'status' => 200,
                'tanggal' => $tanggal,
                'data' => $results,
                'checkInOut' => $resultCheckInOut,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin ',
                ],
                500,
            );
        }
    }

    public function getAllLembur()
    {
        try {
            // per_page boleh diminta klien, TAPI berplafon.
            //
            // Kartu memerlukan seluruh item satu periode sekaligus supaya
            // halamannya bisa dipindah di browser tanpa perjalanan baru ke
            // server — paginasi server tidak menghemat apa pun di sini, sebab
            // SP absensi bekerja per RENTANG TANGGAL dan komponen lembur tetap
            // dipekarkan dari seluruh hari. Yang berkurang hanya JSON-nya.
            //
            // Plafon 200 bukan hiasan: satu hari paling banyak melahirkan 3
            // komponen, jadi satu cutoff (31 hari) mentok di 93 item. 200
            // memberi ruang untuk rentang yang lebih lebar tanpa membiarkan
            // siapa pun meminta muatan raksasa.
            $perPage = max(5, min(200, (int) request()->get('per_page', 7)));
            $currentPage = max(1, (int) request()->get('page', 1));
            $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
            $userIdAbsen = (string) (Auth::user()->karyawan->UserID_Absen ?? '');

            // Sumber kebenaran = SNAPSHOT (hari mana saja karyawan punya lembur), BUKAN tabel
            // pengajuan. Ini penting karena LEMBUR_SHIFT (long shift) tidak punya transaksi
            // pengajuan — kalau hanya query Transaksi_Lembur, hari shift-only akan hilang.
            //
            // Periode default: 1 CUTOFF berjalan (start..end), supaya tanggal yang muncul
            // di list penugasan rapi dalam satu batas periode (bukan 60 hari acak).
            $cutoffRange = $this->uDashReadModelService->getCutoffRange(Carbon::now('Asia/Jakarta'));
            $minDate = request()->get('tgl_awal', $cutoffRange['start_date']->toDateString());
            $maxDate = request()->get('tgl_akhir', $cutoffRange['end_date']->toDateString());

            if ($userIdAbsen === '') {
                return response()->json(new LengthAwarePaginator([], 0, $perPage, $currentPage, [
                    'path' => request()->url(),
                ]));
            }

            // Hasil BANGUNAN di-cache, bukan hasil paginasinya.
            //
            // Yang mahal di sini adalah panggilan SP absensi (±600 ms, dan ia
            // menyegarkan snapshot untuk seluruh rentang), bukan pengirisannya.
            // Dengan cache di tingkat ini, membuka kartu berulang atau berpindah
            // halaman tidak memanggil SP lagi.
            //
            // Umurnya sengaja PENDEK. Data ini berubah ketika pengajuan lembur
            // disetujui atau absensi dihitung ulang; 60 detik cukup meredam
            // pembukaan beruntun tanpa membuat persetujuan terasa tak tercatat.
            $kunciCache = sprintf('lembur:penugasan:%s:%s:%s', $kodeKaryawan, $minDate, $maxDate);

            $items = Cache::remember($kunciCache, 60, function () use ($minDate, $maxDate, $userIdAbsen, $kodeKaryawan) {
                $response = $this->attendanceService->getAttendance(
                    kodePerusahaan: '001',
                    tglAwal: $minDate,
                    tglAkhir: $maxDate,
                    userIdList: $userIdAbsen,
                    executionMode: 'SYNC',
                    requestSource: 'uDash-controller-lembur',
                    requestedBy: $kodeKaryawan ?? 'web-uDash-controller-lembur',
                    priority: 1,
                );
                if (! $response['success']) {
                    throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
                }

                // Index transaksi pengajuan per (tanggal + jenis) untuk melengkapi komponen
                // HK / Awal / Libur dengan alasan, jam, status, & no transaksi.
                $transaksiIndex = $this->buildLemburTransaksiIndex($kodeKaryawan, $minDate, $maxDate);

                // Index batas lembur per shift: Id_Shift -> Durasi_Lembur (cap "Up To" untuk
                // long shift) + Flag_Longshift. Dipakai oleh komponen LEMBUR_SHIFT.
                $shiftCapIndex = $this->buildShiftLemburCapIndex(collect($response['data'] ?? []));

                // Bangun item LEVEL-KOMPONEN dari tiap hari snapshot yang punya lembur.
                return collect($response['data'] ?? [])
                    ->filter(fn ($absen) => ! empty($absen->Tanggal))
                    ->flatMap(fn ($absen) => $this->buildLemburDayComponents($absen, $transaksiIndex, $shiftCapIndex))
                    ->sortByDesc('Tanggal_Kerja')
                    ->values()
                    ->all();
            });

            $items = collect($items);

            $sliceCollect = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $finalPage = new LengthAwarePaginator($sliceCollect, $items->count(), $perPage, $currentPage, [
                'path' => request()->url(),
            ]);

            // per_page & rentang ikut dibawa ke prev_page_url / next_page_url.
            //
            // Tanpa ini URL halaman berikutnya hanya membawa 'page', sehingga
            // pemanggil yang meminta per_page 200 lalu mengikuti next_page_url
            // diam-diam kembali ke 7 — dan rentang tanggalnya melompat balik ke
            // cutoff berjalan. uDash.vue memang bernavigasi lewat URL itu.
            $finalPage->appends(array_filter([
                'per_page' => $perPage,
                'tgl_awal' => request()->get('tgl_awal'),
                'tgl_akhir' => request()->get('tgl_akhir'),
            ]));

            return response()->json($finalPage);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data lembur getAllLembur'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data lembur',
                ],
                500,
            );
        }
    }

    /**
     * Index transaksi pengajuan lembur per "Y-m-d|JENIS" agar bisa ditempel ke komponen
     * hari dari snapshot. JENIS dinormalkan: LEMBUR_HK -> LEMBUR.
     */
    private function buildLemburTransaksiIndex(string $kodeKaryawan, string $minDate, string $maxDate): array
    {
        $rows = DB::table('Transaksi_Lembur as h')
            ->join('Transaksi_Lembur_Detail as d', function ($join) {
                $join
                    ->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join
                    ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status')
            ->whereBetween('det.Tanggal_Lembur', [$minDate, $maxDate])
            ->orderByDesc('h.Tanggal')
            ->orderByDesc('h.Jam')
            ->get([
                'h.No_Transaksi',
                'h.Flag_Selesai',
                'd.Tanggal_Lembur_Dari',
                'd.Tanggal_Lembur_Sampai',
                'det.Tanggal_Lembur',
                'd.Alasan',
                'd.Jenis',
            ]);

        $index = [];
        foreach ($rows as $row) {
            $dateKey = Carbon::parse((string) $row->Tanggal_Lembur)->format('Y-m-d');
            $jenis = strtoupper((string) ($row->Jenis ?? 'LEMBUR'));
            $jenis = $jenis === 'LEMBUR_HK' ? 'LEMBUR' : $jenis;
            // Simpan transaksi pertama saja per (tanggal+jenis); urutan sudah desc.
            $index[$dateKey.'|'.$jenis] ??= $row;
        }

        return $index;
    }

    /**
     * Index batas lembur per shift untuk komponen LEMBUR_SHIFT (long shift). Memetakan
     * Id_Shift dari baris snapshot ke Durasi_Lembur (jam, batas maksimum lembur shift /
     * "Up To") via:
     *   HRIS_Shift_Kerja_Detail.ID_Shift -> HRIS_Waktu_Kerja.ID_Waktu_Kerja
     * Diambil MAX(Durasi_Lembur) per shift karena satu shift bisa punya beberapa baris
     * detail (per hari). Mengembalikan map: Id_Shift => ['durasi' => float, 'nama' => string].
     *
     * @param  \Illuminate\Support\Collection<int, object>  $absenRows
     * @return array<string, array{durasi: float, nama: string}>
     */
    private function buildShiftLemburCapIndex($absenRows): array
    {
        $shiftIds = $absenRows
            ->pluck('Id_Shift')
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '' && $id !== '0')
            ->unique()
            ->values();

        if ($shiftIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('HRIS_Shift_Kerja_Detail as d')
            ->join('HRIS_Waktu_Kerja as w', 'd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->join('HRIS_Shift_Kerja as s', 'd.ID_Shift', '=', 's.ID_Shift')
            ->whereIn('d.ID_Shift', $shiftIds->all())
            ->where('w.Flag_Longshift', 'Y')
            ->groupBy('d.ID_Shift', 's.Nama')
            ->selectRaw('d.ID_Shift as id_shift, s.Nama as nama, MAX(w.Durasi_Lembur) as durasi')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[trim((string) $row->id_shift)] = [
                'durasi' => (float) ($row->durasi ?? 0),
                'nama' => trim((string) ($row->nama ?? '')),
            ];
        }

        return $index;
    }

    /**
     * Dari satu baris snapshot harian, hasilkan beberapa item lembur LEVEL-KOMPONEN
     * (Hari Kerja / Awal / Libur / Shift) yang durasinya > 0. Komponen Shift tidak
     * memiliki transaksi pengajuan (otomatis dari long shift).
     */
    private function buildLemburDayComponents(object $absen, array $transaksiIndex, array $shiftCapIndex = []): array
    {
        $dateKey = Carbon::parse((string) $absen->Tanggal)->format('Y-m-d');

        $overtimeHk = (int) round((float) ($absen->Overtime ?? 0));
        $overtimeAwal = (int) round((float) ($absen->Overtime_Before ?? 0));
        $overtimeShift = (int) round((float) ($absen->Overtime_Shift ?? 0));
        $overtimeGross = (int) round((float) ($absen->Overtime_Gross ?? 0));
        $overtimeNet = (int) round((float) ($absen->Overtime_Calculated ?? 0));
        $realLate = (int) round((float) ($absen->RealLate ?? 0));
        $koefisienHour = (float) round((float) str_replace(',', '.', (string) ($absen->Koefisien_OT ?? 0)), 2);
        $checkIn = $absen->CheckIn ?? null;
        $checkOut = $absen->CheckOut ?? null;
        $flagLemburLibur = strtoupper((string) ($absen->Flag_Lembur_Libur ?? 'T')) === 'Y';

        // Konteks shift untuk komponen LEMBUR_SHIFT (long shift): batas "Up To" dari
        // HRIS_Waktu_Kerja.Durasi_Lembur, nama shift, dan aktual jam yang user dapat.
        $idShift = trim((string) ($absen->Id_Shift ?? ''));
        $namaShift = trim((string) ($absen->Nama_Shift ?? '')) ?: '-';
        $shiftCap = $shiftCapIndex[$idShift] ?? null;
        $shiftUpToHours = $shiftCap ? (float) $shiftCap['durasi'] : 0.0;
        if (($shiftCap['nama'] ?? '') !== '') {
            $namaShift = $shiftCap['nama'];
        }
        $shiftAktualHours = round($overtimeShift / 60, 2); // aktual lembur shift (jam)
        $shiftMeta = [
            'Shift_Nama' => $namaShift,
            'Shift_Up_To_Jam' => $shiftUpToHours,
            'Shift_Aktual_Jam' => $shiftAktualHours,
        ];

        // Konteks HARI (dikirim ke tiap komponen agar header grup punya datanya)
        $hari = [
            'Tanggal_Kerja' => $absen->Tanggal,
            'Hari_Overtime_Gross_Menit' => $overtimeGross,
            'Hari_Overtime_Calculated_Menit' => $overtimeNet,
            'Hari_Overtime_Shift_Menit' => $overtimeShift,
            'Hari_Koefisien_OT_Jam' => $koefisienHour,
            'Hari_Real_Late_Menit' => $realLate,
            'Hari_Ada_Shift' => $overtimeShift > 0 ? 'Y' : 'T',
        ];

        $components = [];

        // Komponen Hari Kerja / Libur (kolom Overtime). Jenis ditentukan oleh Flag_Lembur_Libur.
        if ($overtimeHk > 0) {
            $jenis = $flagLemburLibur ? 'LEMBUR_LIBUR' : 'LEMBUR';
            $components[] = $this->buildLemburComponentItem(
                $dateKey, $jenis, $overtimeHk, $checkIn, $checkOut,
                $transaksiIndex[$dateKey.'|'.$jenis] ?? null, $hari,
            );
        }

        // Komponen Lembur Awal (kolom Overtime_Before)
        if ($overtimeAwal > 0) {
            $components[] = $this->buildLemburComponentItem(
                $dateKey, 'LEMBUR_AWAL', $overtimeAwal, $checkIn, $checkOut,
                $transaksiIndex[$dateKey.'|LEMBUR_AWAL'] ?? null, $hari,
            );
        }

        // Komponen Lembur Shift (otomatis, tanpa transaksi pengajuan)
        if ($overtimeShift > 0) {
            $components[] = $this->buildLemburComponentItem(
                $dateKey, 'LEMBUR_SHIFT', $overtimeShift, $checkIn, $checkOut, null, $hari, $shiftMeta,
            );
        }

        return $components;
    }

    /**
     * Bangun satu item komponen lembur (durasi murni jenis tertentu) + konteks hari.
     */
    private function buildLemburComponentItem(
        string $dateKey,
        string $jenis,
        int $durasiMurniMenit,
        $checkIn,
        $checkOut,
        ?object $trx,
        array $hari,
        array $shiftMeta = [],
    ): array {
        $isAutoShift = $jenis === 'LEMBUR_SHIFT';

        // Sumber per-row: lembur ber-transaksi pakai No_Transaksi; lembur shift otomatis
        // pakai nama shift sebagai sumber (tidak ada nomor transaksi).
        $shiftNama = (string) ($shiftMeta['Shift_Nama'] ?? '-');
        $shiftUpTo = (float) ($shiftMeta['Shift_Up_To_Jam'] ?? 0);
        $shiftAktual = (float) ($shiftMeta['Shift_Aktual_Jam'] ?? 0);

        $noTransaksi = $trx->No_Transaksi ?? ($isAutoShift ? 'AUTO-SHIFT-'.$dateKey : '-');
        $status = $isAutoShift
            ? 'Otomatis'
            : (strtoupper((string) ($trx->Flag_Selesai ?? 'Y')) === 'Y' ? 'Selesai' : 'Proses');
        $alasan = $isAutoShift
            ? 'Dihitung otomatis dari durasi long shift (tanpa pengajuan).'
            : ($trx->Alasan ?? '-');

        // Label sumber yang ditampilkan di tiap row penugasan.
        $sourceLabel = $isAutoShift
            ? ('Shift '.($shiftNama !== '' && $shiftNama !== '-' ? $shiftNama : 'long shift'))
            : ($noTransaksi !== '-' ? $noTransaksi : 'Tanpa nomor transaksi');

        // Validasi kelengkapan absensi (hanya untuk komponen ber-transaksi)
        $isAbsensiLengkap = true;
        $requiredLabel = '';
        if (! $isAutoShift) {
            $missing = [];
            if (in_array($jenis, ['LEMBUR', 'LEMBUR_HK'], true)) {
                if (empty($checkOut)) {
                    $missing[] = 'Check-Out';
                }
                $requiredLabel = 'Butuh Check-Out untuk hitung lembur hari kerja';
            } elseif ($jenis === 'LEMBUR_AWAL') {
                if (empty($checkIn)) {
                    $missing[] = 'Check-In';
                }
                $requiredLabel = 'Butuh Check-In untuk hitung lembur awal';
            } elseif ($jenis === 'LEMBUR_LIBUR') {
                if (empty($checkIn)) {
                    $missing[] = 'Check-In';
                }
                if (empty($checkOut)) {
                    $missing[] = 'Check-Out';
                }
                $requiredLabel = 'Butuh Check-In & Check-Out untuk hitung lembur libur';
            }
            $isAbsensiLengkap = count($missing) === 0;
            if (! $isAbsensiLengkap) {
                $requiredLabel = 'Belum lengkap: '.implode(', ', $missing);
            }
        }

        $tglDari = $trx->Tanggal_Lembur_Dari ?? null;
        $tglSampai = $trx->Tanggal_Lembur_Sampai ?? null;

        return array_merge($hari, [
            'No_Transaksi' => $noTransaksi,
            'Tanggal_Lembur_Dari' => $tglDari,
            'Tanggal_Lembur_Sampai' => $tglSampai,
            'Alasan' => $alasan,
            'Status' => $status,
            'Jenis_Lembur' => $jenis,
            'Jenis_Lembur_Label' => $this->mapLemburJenisLabel($jenis),
            'CheckIn' => $checkIn,
            'CheckOut' => $checkOut,
            'Is_Auto_Shift' => $isAutoShift ? 'Y' : 'T',

            // Sumber per-row + konteks shift (untuk LEMBUR_SHIFT)
            'Source_Label' => $sourceLabel,
            'Shift_Nama' => $shiftNama,
            'Shift_Up_To_Jam' => $shiftUpTo,
            'Shift_Aktual_Jam' => $shiftAktual,

            'Absensi_Lengkap' => $isAutoShift ? 'Y' : ($isAbsensiLengkap ? 'Y' : 'T'),
            'Absensi_Status' => $isAutoShift
                ? 'Otomatis'
                : ($isAbsensiLengkap ? 'Absensi lengkap' : $requiredLabel),
            'Absensi_Requirement' => $isAutoShift
                ? 'Lembur shift dihitung otomatis dari durasi long shift.'
                : ($isAbsensiLengkap ? 'Lembur sudah terhitung dari absensi.' : $requiredLabel),

            // Durasi MURNI komponen ini (menit, dari SP)
            'Durasi_Murni_Menit' => $durasiMurniMenit,

            'Assigned_Range_Label' => $isAutoShift
                ? 'Otomatis (long shift)'
                : $this->buildDateTimeRangeLabel($tglDari, $tglSampai),
            'Actual_Range_Label' => $this->buildDateTimeRangeLabel($checkIn, $checkOut),
        ]);
    }

    private function mapLemburJenisLabel(string $jenis): string
    {
        return match (strtoupper($jenis)) {
            'LEMBUR_AWAL' => 'Lembur Awal',
            'LEMBUR_LIBUR' => 'Lembur Minggu/Libur',
            'LEMBUR_SHIFT' => 'Lembur Shift',
            'LEMBUR_HK', 'LEMBUR' => 'Lembur Hari Kerja',
            default => 'Lembur',
        };
    }

    private function buildDateTimeRangeLabel($startRaw, $endRaw): string
    {
        if (empty($startRaw) || empty($endRaw)) {
            return '-';
        }

        try {
            $start = Carbon::parse((string) $startRaw);
            $end = Carbon::parse((string) $endRaw);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }

            $tanggalPart = FormatTanggalHelper::formatTanggalMultiple([$start->format('Y-m-d'), $end->format('Y-m-d')]);

            return sprintf('%s, %s - %s', $tanggalPart, $start->format('H:i'), $end->format('H:i'));
        } catch (\Throwable $e) {
            return '-';
        }
    }

    // public function getChartData(Request $request){
    //     // dd($request->all());
    //     try {
    //         $title = 'uDash for You';

    //         // Ambil bulan & tahun dari request
    //         $input = $request->bulan . '-' . $request->tahun;
    //         $today = Carbon::today();

    //         // tanggal awal bulan input
    //         $inputDate = Carbon::createFromFormat('m-Y', $input)->day(1);

    //         if ($inputDate->month == $today->month && $inputDate->year == $today->year) {
    //             // bulan & tahun input adalah sekarang
    //             if ($today->day > 20) {
    //                 // sudah lewat tanggal 20 → cutoff bulan ini
    //                 $cutoffMonth = $inputDate->month;
    //                 $cutoffYear  = $inputDate->year;
    //             } else {
    //                 // masih <= 20 → cutoff bulan sebelumnya
    //                 $cutoffMonth = $inputDate->copy()->subMonthNoOverflow()->month;
    //                 $cutoffYear  = $inputDate->copy()->subMonthNoOverflow()->year;
    //             }
    //         } else {
    //             // bukan bulan sekarang → cutoff langsung pakai bulan input
    //             $cutoffMonth = $inputDate->month;
    //             $cutoffYear  = $inputDate->year;
    //         }

    //         // start = 21 cutoffMonth
    //         $startWeek = Carbon::createFromDate($cutoffYear, $cutoffMonth, 21);
    //         // end   = 20 bulan berikutnya
    //         $endWeek   = $startWeek->copy()->addMonthNoOverflow()->day(20);

    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

    //         $hariLibur = DB::table('HRIS_Hari_Libur')
    //         ->whereBetween('Tanggal', [$startWeek, $endWeek])
    //         ->count();
    //         // dd($hariLibur);
    //         // Tentukan range berdasarkan jenis request
    //         // $startWeek = Carbon::create($tahun, $bulan, 21);
    //         // $endWeek   = Carbon::create($tahun, $bulan, 21)->addMonth()->subDay();
    //         // dd($startWeek, $endWeek);
    //         // --- Ambil data absen dari SP ---
    //         $query = "exec HRIS_SP_GET_ABSEN_NEW_V3s '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
    //         // dd($query->toSql());
    //         $absen_data = collect(DB::select($query));
    //         $tanggal_absens = $absen_data
    //             ->pluck('Tanggal')
    //             ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));

    //         // Hitung jumlah CheckIn
    //         $jumlah_checkin = $absen_data->whereNotNull('CheckIn')->count();
    //         // dd($UserID_Absen);
    //         // 1. Lembur
    //         $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
    //             ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 2. Sakit
    //         $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("Jenis", "sakit")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 3. Izin
    //         $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("Jenis", "izinFull")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 4. Cuti
    //         $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->join('Transaksi_Cuti_Det as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 // $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                 //     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);

    //                 $query->whereIn(DB::raw('CAST(c.Tanggal_Cuti AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 5. Pulang cepat
    //         $pulangCepat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //             ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "pulangCepat")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //             ->count();

    //         // 6. Terlambat
    //         $Terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //             ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "terlambat")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //             ->count();

    //         $endDateForCalculation = Carbon::now()->min($endWeek);
    //         $totalHariKerjaYangBerlalu = $absen_data
    //         ->filter(function ($item) use ($endDateForCalculation) {
    //             return Carbon::parse($item->Tanggal)->lte($endDateForCalculation);
    //         })
    //         // Filter untuk mengecualikan hari libur atau hari tanpa jadwal kerja
    //         ->whereNotIn('Nama_Shift', ['LIBUR', 'NO SHIFT', 'HARI MINGGU']) // <-- SESUAIKAN INI
    //         ->count();

    //         $totalKehadiran = $jumlah_checkin  + $izin_data + $sakit_data + $cuti_data + $hariLibur;

    //         // dd($totalHariKerjaYangBerlalu, $jumlah_checkin);

    //             return response()->json([
    //                 'status' => 200,
    //                 'data' => [
    //                     'kehadiran'   => $jumlah_checkin,
    //                     'terlambat'   => $Terlambat_data,
    //                     'pulangCepat' => $pulangCepat_data,
    //                     'izin'        => $izin_data,
    //                     'sakit'       => $sakit_data,
    //                     'cuti'        => $cuti_data,
    //                     // kalau mau tambahkan lembur juga:
    //                     // 'lembur'      => $lembur_data,
    //                 ],
    //                 'summary' => [
    //                     'total' => $jumlah_checkin + $Terlambat_data + $pulangCepat_data + $izin_data + $sakit_data + $cuti_data,
    //                     'kehadiran_percent' => ($totalHariKerjaYangBerlalu > 0)
    //                         ? min(100, round(($totalKehadiran / $totalHariKerjaYangBerlalu) * 100))
    //                         : 0,

    //                 ]
    //             ]);

    //     } catch (\Throwable $e) {
    //         Log::channel('uDashLog')->error('Gagal Mengambil data chart getChartData'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data chart"
    //         ], 500);
    //     }
    // }

    public function getChartData(Request $request)
    {
        try {
            $title = 'uDash for You';
            $user = Auth::user();
            $UserID_Absen = $user->karyawan->UserID_Absen;
            $Kode_Karyawan = $user->karyawan->Kode_Karyawan;

            // ---------------------------------------------------------
            // 1. LOGIKA CUTOFF PAYROLL
            // ---------------------------------------------------------
            $input = $request->bulan.'-'.$request->tahun;
            $today = Carbon::today();
            $inputDate = Carbon::createFromFormat('m-Y', $input)->day(1);

            if ($inputDate->month == $today->month && $inputDate->year == $today->year) {
                $cutoffRange = $this->uDashReadModelService->getCutoffRange($today);
            } else {
                $cutoffRange = $this->uDashReadModelService->getCutoffRangeForMonth(
                    (int) $inputDate->year,
                    (int) $inputDate->month,
                );
            }

            $startWeek = $cutoffRange['start_date'];
            $endWeek = $cutoffRange['end_date'];

            // ---------------------------------------------------------
            // 2. DATA PENDUKUNG (SP)
            // ---------------------------------------------------------

            // Ambil data absen dari SP (Raw Data Fingerprint)
            // Penting: Kita butuh list tanggal dari SP untuk validasi transaksi nanti
            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";

            $sourceReuqest = 'uDash-controller-chartData';
            $tglMulai = $startWeek;
            $tglSelesai = $endWeek;
            $listUserId = $UserID_Absen;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absen_data = collect($response['data']) ?? collect([]);

            $endDateForCalculation = Carbon::now()->min($endWeek);

            $validWorkDates = $absen_data
                ->filter(function ($item) use ($endDateForCalculation) {
                    if (empty($item->Tanggal)) {
                        return false;
                    }

                    $tanggal = Carbon::parse($item->Tanggal);
                    if ($tanggal->gt($endDateForCalculation)) {
                        return false;
                    }

                    $shift = strtoupper(trim((string) ($item->Nama_Shift ?? '')));
                    $statusHari = strtoupper(trim((string) ($item->StatusHari ?? '')));
                    $invalidShifts = ['NO SHIFT', 'LIBUR', 'HARI MINGGU', 'OFF', 'DAY OFF'];
                    $hasValidShift = $shift !== '' && ! in_array($shift, $invalidShifts, true);
                    $isDayOff =
                        $statusHari === 'DAY OFF' || $statusHari === 'OFF' || Str::startsWith($statusHari, 'OFF');

                    return $hasValidShift && ! $isDayOff;
                })
                ->map(fn ($item) => Carbon::parse($item->Tanggal)->format('Y-m-d'))
                ->unique()
                ->values();

            $validWorkDateSet = array_flip($validWorkDates->all());

            // Hitung jumlah CheckIn hanya untuk hari kerja valid yang sudah berlalu.
            $checkInDates = $absen_data
                ->filter(function ($item) use ($validWorkDateSet) {
                    if (empty($item->Tanggal) || trim((string) ($item->CheckIn ?? '')) === '') {
                        return false;
                    }

                    return isset($validWorkDateSet[Carbon::parse($item->Tanggal)->format('Y-m-d')]);
                })
                ->map(fn ($item) => Carbon::parse($item->Tanggal)->format('Y-m-d'))
                ->unique()
                ->values();
            $jumlah_checkin = $checkInDates->count();

            // ---------------------------------------------------------
            // 3. OPTIMASI: AMBIL SEMUA TRANSAKSI DARI VIEW (1 QUERY)
            // ---------------------------------------------------------

            $allTransactions = $this->uDashReadModelService->getApprovedTransactionsForCalendar(
                $Kode_Karyawan,
                $startWeek->format('Y-m-d'),
                $endWeek->format('Y-m-d'),
            );

            if ($allTransactions === null) {
                $allTransactions = DB::table('N_HRIS_VW_All_Transactions')
                    ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->where(function ($q) use ($startWeek, $endWeek) {
                        $q->whereDate('Tanggal_Mulai', '<=', $endWeek)->whereDate('Tanggal_Selesai', '>=', $startWeek);
                    })
                    ->where(function ($q) {
                        $q->where('Flag_Approval', 'Y')->orWhere('Flag_Approval', 'Y');
                    })
                    ->get();
            }

            // ---------------------------------------------------------
            // 4. MAPPING & COUNTING DI PHP
            // ---------------------------------------------------------

            // Helper untuk mengambil tanggal transaksi yang valid.
            // Syarat valid: tipe cocok dan tanggal transaksi ada di hari kerja valid yang sudah berlalu.
            $getTxDates = function ($tipe, $subTipe = null) use ($allTransactions, $validWorkDateSet) {
                $allowedTypes = collect((array) $tipe)
                    ->map(fn ($value) => strtolower(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all();
                $allowedSubTypes = collect((array) $subTipe)
                    ->map(fn ($value) => strtolower(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all();

                return $allTransactions
                    ->flatMap(function ($tx) use ($allowedTypes, $allowedSubTypes, $validWorkDateSet) {
                        $txType = strtolower(trim((string) ($tx->Tipe_Transaksi ?? '')));
                        $txSubType = strtolower(trim((string) ($tx->Sub_Tipe ?? '')));

                        // 1. Cek Tipe
                        if (! in_array($txType, $allowedTypes, true)) {
                            return [];
                        }

                        // 2. Cek Sub Tipe
                        if (! empty($allowedSubTypes) && ! in_array($txSubType, $allowedSubTypes, true)) {
                            return [];
                        }

                        // 3. Ambil tanggal detail (V2 normalized row atau fallback legacy)
                        if (! empty($tx->Tanggal_Item)) {
                            $tanggalItem = Carbon::parse($tx->Tanggal_Item)->format('Y-m-d');

                            return isset($validWorkDateSet[$tanggalItem]) ? [$tanggalItem] : [];
                        }

                        $dataDetail = TanggalTransaksiIzin::ringkas($tx->No_Transaksi, false);
                        if (empty($dataDetail) || ! isset($dataDetail['tanggal'])) {
                            return [];
                        }

                        $hariValid = [];

                        foreach ($dataDetail['tanggal'] as $tgl) {
                            $tglFormatted = Carbon::parse($tgl)->format('Y-m-d');
                            if (isset($validWorkDateSet[$tglFormatted])) {
                                $hariValid[] = $tglFormatted;
                            }
                        }

                        return $hariValid;
                    })
                    ->unique()
                    ->values();
            };

            // --- Eksekusi Counting ---

            // 1. Lembur
            $lembur_data = $getTxDates('Lembur')->count();

            // 2. Sakit (view baru memakai Tipe Izin + Sub_Tipe sakit_izin)
            $sakitDates = $getTxDates(['Sakit', 'IZIN_SAKIT'], ['sakit', 'sakit_izin'])
                ->merge($getTxDates('Izin', ['sakit', 'sakit_izin']))
                ->unique()
                ->values();
            $sakit_data = $sakitDates->count();

            // 3. Izin (izinFull)
            $izinDates = $getTxDates('Izin', 'izinFull')->merge($getTxDates('Sakit', 'izinFull'))->unique()->values();
            $izin_data = $izinDates->count();

            // 4. Cuti (Semua jenis Cuti)
            $cutiDates = $getTxDates('Cuti');
            $cuti_data = $cutiDates->count();

            // 5. Pulang Cepat
            $pulangCepat_data = $getTxDates(['TERLAMBAT', 'Terlambat_Pulang_Cepat'], 'pulangCepat')->count();

            // 6. Terlambat
            $Terlambat_data = $getTxDates(['TERLAMBAT', 'Terlambat_Pulang_Cepat'], 'terlambat')->count();

            // ---------------------------------------------------------
            // 5. KALKULASI PERSENTASE & RETURN (TETAP SAMA)
            // ---------------------------------------------------------

            $totalHariKerjaYangBerlalu = $validWorkDates->count();

            // Total kredit kehadiran berbasis tanggal unik, tanpa hari libur/OFF.
            $totalKehadiran = $checkInDates
                ->merge($izinDates)
                ->merge($sakitDates)
                ->merge($cutiDates)
                ->unique()
                ->count();
            $totalKehadiran = min($totalHariKerjaYangBerlalu, $totalKehadiran);
            $tidakMasuk = max($totalHariKerjaYangBerlalu - $totalKehadiran, 0);

            return response()->json([
                'status' => 200,
                'data' => [
                    'kehadiran' => $jumlah_checkin,
                    'terlambat' => $Terlambat_data,
                    'pulangCepat' => $pulangCepat_data,
                    'izin' => $izin_data,
                    'sakit' => $sakit_data,
                    'cuti' => $cuti_data,
                    'tidakMasuk' => $tidakMasuk,
                    // 'lembur'   => $lembur_data,
                ],
                'summary' => [
                    'total' => $jumlah_checkin +
                        $Terlambat_data +
                        $pulangCepat_data +
                        $izin_data +
                        $sakit_data +
                        $cuti_data +
                        $tidakMasuk,
                    'total_hari_kerja_berlalu' => $totalHariKerjaYangBerlalu,
                    'total_kredit_kehadiran' => $totalKehadiran,
                    'kehadiran_percent' => $totalHariKerjaYangBerlalu > 0
                            ? min(100, round(($totalKehadiran / $totalHariKerjaYangBerlalu) * 100))
                            : 0,
                ],
                'period' => $this->uDashReadModelService->serializeCutoffRange($cutoffRange),
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data chart getChartData'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data chart',
                ],
                500,
            );
        }
    }
}
