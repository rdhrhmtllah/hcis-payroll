<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Management;

use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappMessage;
use App\Models\ESS\Attendance\Overtime\Models\LemburDetail;
use App\Models\ESS\Attendance\Shift\Models\Shift_Kerja;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;

class ShiftController extends Controller
{
    //

    public function __construct(
        HrisAttendanceSnapshotService $attendanceSnapshotService,
        private readonly AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->attendanceService = $attendanceSnapshotService;
    }

    public function index()
    {
        try {
            $title = 'Change User Shift';
            $query = "select a.Kode_Karyawan as NAMA_KARYAWAN, b.Nama as SHIFT_PERMANEN ,
        isnull((select TOP 1 y.Nama + '(SHIFT SEMENTARA)' from HRIS_Shift_Sementara x, HRIS_Shift_Kerja y where x.ID_Shift = y.ID_Shift and x.Kode_Karyawan = a.Kode_karyawan and x.ID_Shift <> a.ID_Shift),b.Nama) as SHIFT_SEMENTARA
        from HRIS_Shift_Per_Karyawan a JOIN HRIS_Shift_Kerja b ON a.ID_Shift = b.ID_Shift   group by Kode_Karyawan, b.Nama, a.ID_Shift
        ";
            $result = DB::select($query);

            $divisi = Auth()->user()->divisionKaryawan->nama_divisi;
            // dd($divisi);
            // Ambil tanggal hari ini
            $today = date('Y-m-d');
            $hPlus2 = date('Y-m-d', strtotime('+2 day', strtotime($today)));
            // Menghitung tanggal hari Minggu (awal minggu)
            $startOfWeek = date('Y-m-d', strtotime('last sunday', strtotime($today)));

            // Menghitung tanggal Sabtu (akhir minggu)
            $endOfWeek = date('Y-m-d', strtotime('next saturday', strtotime($startOfWeek)));

            // Menghasilkan array tanggal per minggu
            $weekDates = [];
            $currentDate = $today;
            //    $currentDate = '2025-07-21';
            $today = date('Y-m-d');
            $weekDates = [];
            for ($i = 0; $i <= 14; $i++) {
                $weekDates[] = $currentDate;

                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }
            // Perulangan akan terus berjalan selama $currentDate kurang dari atau sama dengan $today
            // while (strtotime($currentDate) <= strtotime($today)) {
            //     $weekDates[] = $currentDate;
            //     $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));

            // dd($weekDates);
            $querShift = '
            select
            a.ID_Shift,
            a.Nama,
            b.Hari,
            c.Jam_Masuk,
            c.Jam_Keluar,
            c.Flag_CO_Lintas_Hari,
            c.Jam_Selesai_Lintas_Hari
            from
            HRIS_Shift_Kerja a,
            HRIS_Shift_Kerja_Detail b,
            HRIS_Waktu_Kerja c
            where
            a.ID_Shift = b.ID_Shift
            and b.ID_Waktu_Kerja = c.ID_Waktu_Kerja

        ';
            $jenisShift = DB::select($querShift);
            // dd($jenisShift);

            return inertia('ESS/Attendance/Shift/Personal/userShift', [
                'title' => $title,
                'semuaShift' => $jenisShift,
                'divisi' => $divisi,
                'tanggalLoop' => $weekDates,
            ]);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal menampilkan page shift: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page shift!');

            return back();
        }
    }

    public function indexAdmin()
    {
        try {
            $title = 'Change User Shift';
            $query = "select a.Kode_Karyawan as NAMA_KARYAWAN, b.Nama as SHIFT_PERMANEN ,
        isnull((select TOP 1 y.Nama + '(SHIFT SEMENTARA)' from HRIS_Shift_Sementara x, HRIS_Shift_Kerja y where x.ID_Shift = y.ID_Shift and x.Kode_Karyawan = a.Kode_karyawan and x.ID_Shift <> a.ID_Shift),b.Nama) as SHIFT_SEMENTARA
        from HRIS_Shift_Per_Karyawan a JOIN HRIS_Shift_Kerja b ON a.ID_Shift = b.ID_Shift   group by Kode_Karyawan, b.Nama, a.ID_Shift
        ";
            $result = DB::select($query);

            $divisi = Auth()->user()->divisionKaryawan->nama_divisi;
            // dd($divisi);
            // Ambil tanggal hari ini
            $today = date('Y-m-d');
            $hPlus2 = date('Y-m-d', strtotime('+2 day', strtotime($today)));
            // Menghitung tanggal hari Minggu (awal minggu)
            $startOfWeek = date('Y-m-d', strtotime('last sunday', strtotime($today)));

            // Menghitung tanggal Sabtu (akhir minggu)
            $endOfWeek = date('Y-m-d', strtotime('next saturday', strtotime($startOfWeek)));

            // Menghasilkan array tanggal per minggu
            $weekDates = [];
            $currentDate = $hPlus2;

            for ($i = 0; $i < 14; $i++) {
                $weekDates[] = $currentDate;

                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }
            // dd($weekDates);
            $querShift = '
            select
            a.ID_Shift,
            a.Nama,
            b.Hari,
            c.Jam_Masuk,
            c.Jam_Keluar,
            c.Flag_CO_Lintas_Hari,
            c.Jam_Selesai_Lintas_Hari
            from
            HRIS_Shift_Kerja a,
            HRIS_Shift_Kerja_Detail b,
            HRIS_Waktu_Kerja c
            where
            a.ID_Shift = b.ID_Shift
            and b.ID_Waktu_Kerja = c.ID_Waktu_Kerja

        ';
            $jenisShift = DB::select($querShift);
            // dd($jenisShift);

            return inertia('ESS/Attendance/Shift/Admin/shiftAdmin', [
                'title' => $title,
                'semuaShift' => $jenisShift,
                'divisi' => $divisi,
                'tanggalLoop' => $weekDates,
            ]);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal menampilkan page shiftAdmin: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page shiftAdmin!');

            return back();
        }
    }

    public function getShift(Request $request)
    {
        // dd($request->all());
        try {
            if ($request['type'] === 'temporary') {
                $tanggal = $request['tanggal'][0];
            } else {
                $tanggal = $request['tanggal'];
            }
            // dd($tanggal);
            // dd(date('Y-m-d',strtotime($tanggal)) <= date('Y-m-d'));
            if (date('Y-m-d', strtotime($tanggal)) == date('Y-m-d')) {
                // dd('masuk1');
                $Hari = date('N', strtotime($tanggal));
                if ($Hari <= 6) {
                    $Hari += 1;
                } elseif ($Hari == 7) {
                    $Hari = 1;
                }

                $time = date('H:i:s');
                $query = "
        select
        a.ID_Shift,
        a.Nama as Shift,
        b.Hari,
        c.Jam_Masuk,
        c.Jam_Keluar,
        c.Flag_Longshift as flag_longshift,
        c.Durasi_Lembur as durasi_longshift,
        COALESCE(
            NULLIF(c.Jam_Selesai_Lintas_Hari, ''),
            NULLIF(c.Jam_Selesai_Absen_Keluar, '')
        ) AS Jam_selesai_absen
        from
        HRIS_Shift_Kerja a,
        HRIS_Shift_Kerja_Detail b,
        HRIS_Waktu_Kerja c
        where
        a.ID_Shift = b.ID_Shift
        and b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
        and b.Hari = ?
        and a.Non_Shift = 'T'

        ";

                $result = DB::select($query, [$Hari]);
            } else {
                // dd('masuk2');

                // dd($tanggal);
                $Hari = date('N', strtotime($tanggal));
                if ($Hari <= 6) {
                    $Hari += 1;
                } elseif ($Hari == 7) {
                    $Hari = 1;
                }

                // $TanggalSekarang = date();
                // dd($Hari);
                $query = "
        select
        a.ID_Shift,
        a.Nama as Shift,
        b.Hari,
        c.Jam_Masuk,
        c.Jam_Keluar,
        c.Flag_Longshift as flag_longshift,
        c.Durasi_Lembur as durasi_longshift,
        COALESCE(
            NULLIF(c.Jam_Selesai_Lintas_Hari, ''),
            NULLIF(c.Jam_Selesai_Absen_Keluar, '')
        ) AS Jam_selesai_absen
        from
        HRIS_Shift_Kerja a,
        HRIS_Shift_Kerja_Detail b,
        HRIS_Waktu_Kerja c
        where
        a.ID_Shift = b.ID_Shift
        and b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
        and b.Hari = ?
        and a.Non_Shift = 'T'
        ";
                $result = DB::select($query, [$Hari]);
            }

            // dd($result);
            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getShift'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data getShift',
                ],
                500,
            );
        }
    }

    public function getTeam(Request $request)
    {
        try {
            $karyawan_Auth = Auth()->user()->karyawan->Kode_Karyawan;
            $query = "
            SELECT
                        e.Kode_Karyawan,
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
						c.nama_sub_divisi as nama_divisi
                        FROM
                        HRIS_Karyawan_Team e
                        LEFT JOIN Karyawan a ON e.Kode_Karyawan = a.Kode_Karyawan
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND e.Kode_Karyawan_Team = ?
                        AND e.Kode_Karyawan <> ?
                        AND a.Aktif = 'Y'
                        group by
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
                        e.Kode_Karyawan,
						c.nama_sub_divisi
        ";

            $result = DB::select($query, [$karyawan_Auth, $karyawan_Auth]);

            return ResponseHelper::success($result, 'Data Ditemukan', 200);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getTeam'.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getTeamAdmin(Request $request)
    {
        try {
            $karyawan_Auth = Auth()->user()->karyawan->Kode_Karyawan;
            $query = "
            SELECT
                        a.Kode_Karyawan,
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
						c.nama_sub_divisi as nama_divisi
                        FROM
                        Karyawan a
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND a.Aktif = 'Y'
                        group by
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
                        a.Kode_Karyawan,
						c.nama_sub_divisi
        ";

            $result = DB::select($query, [$karyawan_Auth, $karyawan_Auth]);

            return ResponseHelper::success($result, 'Data Ditemukan', 200);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getTeam'.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getWeek(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $searchColoms = ['a.Nama', 'c.nama_divisi', 'c.nama_sub_divisi', 'd.nama_jabatan'];

            $startDate = $request->input('start_date', date('Y-m-d'));
            $startOfWeek = date('Y-m-d', strtotime('last Sunday', strtotime($startDate)));

            $endOfWeek = date('Y-m-d', strtotime('Saturday', strtotime($startOfWeek)));
            $karyawan_Auth = Auth()->user()->karyawan->Kode_Karyawan;

            $queryCount = "
                    SELECT
                        count(e.Kode_Karyawan) as total
                        FROM
                        HRIS_Karyawan_Team e
                        LEFT JOIN Karyawan a ON e.Kode_Karyawan = a.Kode_Karyawan
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND e.Kode_Karyawan_Team = ?
                        AND e.Kode_Karyawan <> ?
                        AND a.Aktif = 'Y'
        ";

            $queryUser = "
                    SELECT
                        e.Kode_Karyawan,
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
						c.nama_sub_divisi as nama_divisi
                        FROM
                        HRIS_Karyawan_Team e
                        LEFT JOIN Karyawan a ON e.Kode_Karyawan = a.Kode_Karyawan
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND e.Kode_Karyawan_Team = ?
                        AND e.Kode_Karyawan <> ?
                        AND a.Aktif = 'Y'


        ";

            $params = [];
            $params[] = $karyawan_Auth;
            $params[] = $karyawan_Auth;
            if ($search) {
                $searchParam = '%'.$search.'%';
                $likeParts = [];
                foreach ($searchColoms as $col) {
                    $likeParts[] = "$col Like ?";
                    $params[] = $searchParam;
                }

                $likeQuery = implode(' OR ', $likeParts);
                $queryCount .= " AND ($likeQuery) ";
                $queryUser .= " AND ($likeQuery) ";
            }

            $total = DB::selectOne($queryCount, $params)->total;

            $queryUser .= ' group by
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
                        e.Kode_Karyawan,
						c.nama_sub_divisi
                        ORDER BY a.Nama OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                        ';
            $params[] = $offset;
            $params[] = $perPage;

            $users = DB::select($queryUser, $params);
            // dd($users, $total);
            if (! $users) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }
            // Buat array tanggal minggu
            $userData = [];
            $weekDates = [];
            $dateRange = [];
            $currentDate = $startOfWeek;

            // Generate semua tanggal dalam minggu
            for ($i = 0; $i < 7; $i++) {
                $dateRange[] = $currentDate;
                array_push($weekDates, $currentDate);
                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }
            // dd($dateRange);

            // Buat placeholder untuk UserIDs
            $userIds = array_column($users, 'UserID_Absen');
            $userIdPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
            $datePlaceholders = implode(',', array_fill(0, count($dateRange), '?'));
            $userQuery = implode(',', $userIds);

            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','$startOfWeek','$endOfWeek','$userQuery', '', ''";
            // $resultData =  DB::select($query);

            $sourceReuqest = 'shift-controller-getweek';
            $tglMulai = $startOfWeek;
            $tglSelesai = $endOfWeek;
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

            foreach ($resultData as $idx => $itemUser) {
                // Menyimpan nama dan struktur data kosong untuk setiap user

                $userData[$itemUser->Kode_Karyawan] = ['Kode_Karyawan' => $itemUser->Kode_Karyawan, 'data' => []];
            }
            // dd($resultData);

            foreach ($userData as $parent) {
                foreach ($resultData as $idx => $shiftIDX) {
                    // Mengecek kecocokan Kode_Karyawan Shift dengan Kode_Karyawan User

                    if ($shiftIDX->Kode_Karyawan == $parent['Kode_Karyawan']) {
                        // Menambahkan data ke dalam array data berdasarkan Kode_Karyawan User
                        $userData[$parent['Kode_Karyawan']]['data'][$idx] = [
                            'Tanggal' => date('Y-m-d', strtotime($shiftIDX->Tanggal)), // Format tanggal
                            'Nama_Shift' => $shiftIDX->Nama_Shift,
                            'hari' => date('N', strtotime($shiftIDX->Tanggal)), // Hari dalam angka (1 = Senin, 7 = Minggu)
                            'UserID_Absen' => $shiftIDX->UserId ?? $shiftIDX->UserID,
                            'Nama' => $shiftIDX->Nama,
                            'Kode_Karyawan' => $shiftIDX->Kode_Karyawan,
                        ];
                    }
                }
            }

            $final_page = new LengthAwarePaginator(collect($userData), $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            // dd($final_page);

            $encodedData = [
                'week_dates' => $final_page,
                'Tanggal' => $weekDates,
                'start_of_week' => $startOfWeek,
                'end_of_week' => $endOfWeek,
                'dataAll' => $users,
            ];

            // dd($encodedData);
            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getWeek'.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getWeekAdmin(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $searchColoms = ['a.Nama', 'c.nama_divisi', 'c.nama_sub_divisi', 'd.nama_jabatan'];

            $startDate = $request->input('start_date', date('Y-m-d'));
            $startOfWeek = date('Y-m-d', strtotime('last Sunday', strtotime($startDate)));

            $endOfWeek = date('Y-m-d', strtotime('Saturday', strtotime($startOfWeek)));
            $karyawan_Auth = Auth()->user()->karyawan->Kode_Karyawan;

            $queryCount = "
                    SELECT
                        count(a.Kode_Karyawan) as total
                        FROM
                       Karyawan a
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND a.Aktif = 'Y'
                        AND 1=1
        ";

            $queryUser = "
                    SELECT
                        a.Kode_Karyawan,
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
						c.nama_sub_divisi as nama_divisi
                        FROM
                        Karyawan a
                        LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON  a.ID_Level_Jabatan = d.ID_Level_Jabatan
                        WHERE
                        a.UserID_Absen IS NOT NULL
                        AND a.Aktif = 'Y'
                        AND 1=1


        ";

            $params = [];

            if ($search) {
                $searchParam = '%'.$search.'%';
                $likeParts = [];
                foreach ($searchColoms as $col) {
                    $likeParts[] = "$col Like ?";
                    $params[] = $searchParam;
                }

                $likeQuery = implode(' OR ', $likeParts);
                $queryCount .= " AND ($likeQuery) ";
                $queryUser .= " AND ($likeQuery) ";
            }

            $total = DB::selectOne($queryCount, $params)->total;

            $queryUser .= ' group by
                        a.Nama,
                        a.UserID_Absen,
                        c.ID_Divisi,
                        a.Kode_Karyawan,
						c.nama_sub_divisi
                        ORDER BY a.Nama OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                        ';
            $params[] = $offset;
            $params[] = $perPage;

            $users = DB::select($queryUser, $params);
            // dd($users, $total);
            if (! $users) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }
            // Buat array tanggal minggu
            $userData = [];
            $weekDates = [];
            $dateRange = [];
            $currentDate = $startOfWeek;

            // Generate semua tanggal dalam minggu
            for ($i = 0; $i < 7; $i++) {
                $dateRange[] = $currentDate;
                array_push($weekDates, $currentDate);
                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }
            // dd($dateRange);

            // Buat placeholder untuk UserIDs
            $userIds = array_column($users, 'UserID_Absen');
            $userIdPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
            $datePlaceholders = implode(',', array_fill(0, count($dateRange), '?'));
            $userQuery = implode(',', $userIds);

            // $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','$startOfWeek','$endOfWeek','$userQuery', '', ''";
            // $resultData = DB::select($query);
            $sourceReuqest = 'shift-controller-admingetweek';
            $tglMulai = $startOfWeek;
            $tglSelesai = $endOfWeek;
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

            foreach ($resultData as $idx => $itemUser) {
                // Menyimpan nama dan struktur data kosong untuk setiap user

                $userData[$itemUser->Kode_Karyawan] = ['Kode_Karyawan' => $itemUser->Kode_Karyawan, 'data' => []];
            }

            foreach ($userData as $parent) {
                foreach ($resultData as $idx => $shiftIDX) {
                    // Mengecek kecocokan Kode_Karyawan Shift dengan Kode_Karyawan User

                    if ($shiftIDX->Kode_Karyawan == $parent['Kode_Karyawan']) {
                        // Menambahkan data ke dalam array data berdasarkan Kode_Karyawan User
                        $userData[$parent['Kode_Karyawan']]['data'][$idx] = [
                            'Tanggal' => date('Y-m-d', strtotime($shiftIDX->Tanggal)), // Format tanggal
                            'Nama_Shift' => $shiftIDX->Nama_Shift,
                            'hari' => date('N', strtotime($shiftIDX->Tanggal)), // Hari dalam angka (1 = Senin, 7 = Minggu)
                            'UserID_Absen' => $shiftIDX->UserId ?? $shiftIDX->UserID,
                            'Nama' => $shiftIDX->Nama,
                            'Kode_Karyawan' => $shiftIDX->Kode_Karyawan,
                        ];
                    }
                }
            }

            $final_page = new LengthAwarePaginator(collect($userData), $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            // dd($final_page);

            $encodedData = [
                'week_dates' => $final_page,
                'Tanggal' => $weekDates,
                'start_of_week' => $startOfWeek,
                'end_of_week' => $endOfWeek,
                'dataAll' => $users,
            ];

            // dd($encodedData);
            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getWeek'.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // public function getWeekAdmin(Request $request)
    // {
    //     try{

    //         $startDate = $request->input('start_date', date('Y-m-d'));
    //         $startOfWeek = date('Y-m-d', strtotime('last Sunday', strtotime($startDate)));

    //         $endOfWeek = date('Y-m-d', strtotime('Saturday', strtotime($startOfWeek)));
    //         $divisi =  Auth()->user()->divisionKaryawan->ID_Divisi;
    //         $karyawan_Auth =Auth()->user()->karyawan->Kode_Karyawan;
    //         // $queryUser = "
    //         //             SELECT
    //         //                 a.Kode_Karyawan,
    //         //                 a.Nama,
    //         //                 a.UserID_Absen,
    //         //                 c.ID_Divisi,
    //         //                 c.nama_divisi
    //         //                 FROM

    //         //                 Karyawan a,
    //         //                 View_Divisi_Sub_Divisi c,
    //         //                 View_Golongan_Sub_Golongan_Level_Jabatan d

    //         //                 WHERE
    //         //                 a.ID_Level_Jabatan = d.ID_Level_Jabatan
    //         //                 AND a.UserID_Absen IS NOT NULL
    //         //                 AND a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
    //         //                 group by
    //         //                 a.Nama,
    //         //                 a.UserID_Absen,
    //         //                 c.ID_Divisi,
    //         //                 a.Kode_Karyawan,
    //         //                 c.nama_divisi

    //         // ";

    //         $queryUser = "
    //         SELECT
    //         a.Kode_Karyawan,
    //         a.Nama,
    //         a.UserID_Absen,
    //         c.ID_Divisi,
    //         c.nama_sub_divisi as nama_divisi
    //         FROM
    //         Karyawan a
    //         LEFT JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
    //         LEFT JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON a.ID_Level_Jabatan = d.ID_Level_Jabatan
    //         WHERE
    //         a.UserID_Absen IS NOT NULL
    //         group by
    //         a.Nama,
    //         a.UserID_Absen,
    //         c.ID_Divisi,
    //         a.Kode_Karyawan,
    //         c.nama_sub_divisi

    //         ";

    //         $users = DB::select($queryUser);
    //         // dd($users);
    //         if(!$users){
    //             return response()->json([
    //                 'status' => 404,
    //                 'message' => 'Tidak Menemukan User yang mempunyai shift'
    //             ]);

    //         }
    //         // Buat array tanggal minggu
    //         $userData = [];
    //         $weekDates = [];
    //         $dateRange = [];
    //         $currentDate = $startOfWeek;

    //         // Generate semua tanggal dalam minggu
    //         for ($i = 0; $i < 7; $i++) {
    //             $dateRange[] = $currentDate;
    //             array_Push($weekDates, $currentDate);
    //             $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));
    //         }
    //         // dd($dateRange);

    //         // Buat placeholder untuk UserIDs
    //         $userIds = array_column($users, 'UserID_Absen');
    //         $userIdPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
    //         $datePlaceholders = implode(',', array_fill(0, count($dateRange), '?'));
    //         $userQuery = implode(',', $userIds);

    //         $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','$startOfWeek','$endOfWeek','$userQuery', ''";
    //         $resultData =  DB::select($query);
    //         //     $resultData = collect($resultData);  // Mengonversi array of stdClass menjadi koleksi

    //         // $found = $resultData->where('Nama', 'Garix')->first();  // Mencari objek dengan Nama 'Garix'

    //         // dd($found);

    //         // dd($users);

    //             foreach ($resultData as $idx => $itemUser) {
    //             // Menyimpan nama dan struktur data kosong untuk setiap user

    //                 $userData[$itemUser->Kode_Karyawan] = ['Kode_Karyawan' => $itemUser->Kode_Karyawan, 'data' => []];

    //             }

    //         foreach ($userData as $parent) {
    //             foreach ($resultData as $idx => $shiftIDX) {

    //                 // Mengecek kecocokan Kode_Karyawan Shift dengan Kode_Karyawan User

    //                 if ($shiftIDX->Kode_Karyawan == $parent['Kode_Karyawan']) {
    //                     // Menambahkan data ke dalam array data berdasarkan Kode_Karyawan User
    //                     $userData[$parent['Kode_Karyawan']]['data'][$idx] = [
    //                         'Tanggal' => date('Y-m-d', strtotime($shiftIDX->Tanggal)), // Format tanggal
    //                         'Nama_Shift' => $shiftIDX->Nama_Shift,
    //                         'hari' => date('N', strtotime($shiftIDX->Tanggal)), // Hari dalam angka (1 = Senin, 7 = Minggu)
    //                         'UserID_Absen' => $shiftIDX->UserId ?? $shiftIDX->UserID,
    //                         'Nama' => $shiftIDX->Nama,
    //                         'Kode_Karyawan' => $shiftIDX->Kode_Karyawan
    //                     ];
    //                 }
    //             }
    //         }

    //             // Mengembalikan data dalam format JSON
    //             return response()->json([
    //                 'week_dates' => $userData,
    //                 'Tanggal' => $weekDates,
    //                 'dataExtend' => $users,
    //                 'start_of_week' => $startOfWeek,
    //                 'end_of_week' => $endOfWeek,
    //                 'dataAll' => $users
    //             ]);
    //         }catch(\Throwable $e){
    //         Log::channel('shiftLog')->error('Gagal Mengambil data getWeekAdmin'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data getWeekAdmin"
    //         ], 500);
    //     }
    //     }

    public function getExport(Request $request)
    {
        /* start & end dibaca tanpa diperiksa sebelumnya: strtotime(null) bernilai
           false, date('Y-m-d', false) memberi 1970-01-01, dan prosedur absensi
           dijalankan atas rentang yang tak masuk akal sampai akhirnya gagal —
           dilaporkan 500, padahal parameternya yang kurang. */
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        try {
            /* $divisi dulu diambil di sini lalu tidak pernah dipakai sekali pun,
               dan pembacaannya sendiri yang menjatuhkan seluruh export: pada log
               10 September 2026 ia gagal empat kali dengan 'Attempt to read
               property "divisionKaryawan" on null'. Baris mati tidak bisa
               dibenarkan, hanya bisa dihapus. */
            $karyawan_Auth = Auth()->user()->karyawan->Kode_Karyawan;
            $startOfWeek = date('Y-m-d', strtotime($validated['start']));
            $endOfWeek = date('Y-m-d', strtotime($validated['end']));

            $queryUser = "
                SELECT a.Nama, a.UserID_Absen, c.ID_Divisi
                FROM Karyawan a
                JOIN HRIS_Shift_Per_Karyawan b ON a.Kode_Karyawan = b.Kode_Karyawan
                JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                JOIN HRIS_Karyawan_Team d ON a.Kode_Karyawan = d.Kode_Karyawan
                WHERE a.UserID_Absen IS NOT NULL
                AND a.Kode_Karyawan <> ?
                AND a.Aktif = 'Y'
                AND d.Kode_Karyawan_Team = ?
                GROUP BY a.Nama, a.UserID_Absen, c.ID_Divisi
            ";
            $users = DB::select($queryUser, [$karyawan_Auth, $karyawan_Auth]);

            if (empty($users)) {
                return response()->json(['status' => 200, 'data' => []]);
            }

            $userIds = array_column($users, 'UserID_Absen');
            $userListString = implode(',', $userIds);

            // $query = "EXEC HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, ?, ''";
            // $resultData = DB::select($query, ['001', $startOfWeek, $endOfWeek, $userListString, '']);

            $sourceReuqest = 'shift-controller-export';
            $tglMulai = $startOfWeek;
            $tglSelesai = $endOfWeek;
            $listUserId = $userListString;
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

            $groupedData = [];
            foreach ($resultData as $item) {
                $date = date('Y-m-d', strtotime($item->Tanggal));
                $groupedData[$date][] = $item;
            }

            $finalData = [];
            $currentDate = $startOfWeek;
            while ($currentDate <= $endOfWeek) {
                $finalData[$currentDate] = $groupedData[$currentDate] ?? [];
                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }

            return response()->json([
                'status' => 200,
                'data' => $finalData,
            ]);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Gagal Mengambil data getExport'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data getExport',
                ],
                500,
            );
        }
    }

    public function isLembur(Request $request)
    {
        /* employees & dates dibaca tanpa diperiksa sebelumnya: bila tak dikirim,
           whereIn() menerima null dan foreach berjalan atas null — dilaporkan
           500 "Terjadi kesalahan saat mencek data isLembur", padahal itu
           kesalahan pemanggil yang bisa disebutkan dengan jelas. */
        $validated = $request->validate([
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['required', 'string'],
            'dates' => ['required', 'array', 'min:1'],
            'dates.*' => ['required', 'date'],
        ]);

        try {
            $employees = $validated['employees'];
            $dates = $validated['dates'];

            $data = LemburDetail::select(
                'Karyawan.Nama',
                'Transaksi_Lembur.No_Transaksi',
                'Tanggal_Lembur_Dari',
                'Tanggal_Lembur_Sampai',
            )
                ->whereIn('Transaksi_Lembur_Detail.Kode_Karyawan', $employees)
                ->join('Transaksi_Lembur', 'Transaksi_Lembur_Detail.No_Transaksi', '=', 'Transaksi_Lembur.No_Transaksi')
                ->join('Karyawan', 'Transaksi_Lembur_Detail.Kode_Karyawan', '=', 'Karyawan.Kode_Karyawan')
                ->where('Transaksi_Lembur.Status', null)
                ->where(function (Builder $query) use ($dates) {
                    foreach ($dates as $date) {
                        $startOfDay = $date.' 00:00:00';
                        $endOfDay = $date.' 23:59:59';
                        $query->orWhere(function (Builder $q) use ($startOfDay, $endOfDay) {
                            $q->where('Tanggal_Lembur_Dari', '<=', $endOfDay)->where(
                                'Tanggal_Lembur_Sampai',
                                '>=',
                                $startOfDay,
                            );
                        });
                    }
                })
                ->get();

            $dataIzin = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                ->select('Karyawan.Nama', 'Transaksi_Terlambat_Pulang_Cepat.No_Transaksi', 'Tanggal_Masuk_Pulang')
                ->join(
                    'Transaksi_Terlambat_Pulang_Cepat',
                    'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi',
                    '=',
                    'Transaksi_Terlambat_Pulang_Cepat.No_Transaksi',
                )
                ->join(
                    'Karyawan',
                    'Transaksi_Terlambat_Pulang_Cepat_Detail.Kode_Karyawan',
                    '=',
                    'Karyawan.Kode_Karyawan',
                )
                ->whereIn('Transaksi_Terlambat_Pulang_Cepat_Detail.Kode_Karyawan', $employees) // ['A4']
                ->whereNull('Transaksi_Terlambat_Pulang_Cepat.Status')
                ->where(function ($q) {
                    $q->where('Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_Approval', '=', 'Y')->orWhereNull(
                        'Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_Approval',
                    );
                })
                ->whereIn('Tanggal_Masuk_Pulang', $dates) // ['2025-09-15']
                ->get();

            // dd($dataIzin);
            return response()->json([
                'status' => 200,
                'data' => $data,
                'dataIzin' => $dataIzin,
            ]);
        } catch (\Throwable $e) {
            Log::channel('shiftLog')->error('Terjadi kesalahan saat mencek data isLembur'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'Message' => 'Terjadi kesalahan saat mencek data isLembur',
                ],
                500,
            );
        }
    }

    public function submitAdmin(Request $request)
    {
        $data = $request->All();
        $employees = $data['params']['AssignShift']['employees'];
        $Kode_Perusahaan = '001';
        $Id_Shift = $data['params']['AssignShift']['shift'];
        $UserId = Auth::user()->karyawan->Kode_Karyawan;
        // dd($UserId);
        DB::beginTransaction();
        try {
            // checking Lembur
            // dd($request->all());
            /*
             * Empat kueri per karyawan di sini hasilnya TIDAK PERNAH DIBACA:
             * $lembur, $shiftKerja, $userInsert, dan $userInsertNoHp. Yang
             * memakainya adalah submit(), metode lain yang badannya nyaris
             * kembar dan memang mengirim WhatsApp; submitAdmin() tidak pernah
             * mengirim apa pun, jadi yang tertinggal di sini hanya biayanya.
             * $userInsert dan $userInsertNoHp bahkan menjalankan kueri yang
             * sama persis dua kali berturut-turut.
             *
             * Semuanya berjalan di dalam satu transaksi. Dengan RCSI mati di
             * Web_HRIS, setiap kueri mubazir itu memperpanjang waktu kunci
             * ditahan untuk SELURUH penugasan, bukan hanya untuk barisnya.
             *
             * Sisipannya dikumpulkan lalu ditulis sekali lewat
             * PenyisipanBerbatasParameter, yang memotong sendiri agar tidak
             * menabrak batas 2.100 parameter SQL Server.
             */
            if ($data['params']['AssignShift']['shiftType'] == 'permanent') {
                $Tanggal = date('Y-m-d H:i:s', strtotime($data['params']['AssignShift']['dates']['start']));

                $barisShift = [];
                foreach ($employees as $item) {
                    $barisShift[] = [
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'Kode_Karyawan' => $item,
                        'ID_Shift' => $Id_Shift,
                        'Periode' => $Tanggal,
                        'UserID' => $UserId,
                    ];
                }

                PenyisipanBerbatasParameter::sisipkan('HRIS_Shift_Per_Karyawan', $barisShift);
            } elseif ($data['params']['AssignShift']['shiftType'] == 'temporary') {
                $Dates = $data['params']['AssignShift']['dates'];

                $barisShift = [];
                foreach ($employees as $item) {
                    foreach ($Dates as $date) {
                        $barisShift[] = [
                            'Kode_Perusahaan' => $Kode_Perusahaan,
                            'Kode_Karyawan' => $item,
                            'ID_Shift' => $Id_Shift,
                            'Tanggal' => $date,
                            'UserID' => $UserId,
                        ];
                    }
                }

                PenyisipanBerbatasParameter::sisipkan('HRIS_Shift_Sementara', $barisShift);
            }
            DB::commit();
            $this->markLegacyShiftDirty($request, 'SHIFT_LEGACY_CHANGED_ADMIN');
            Alert::success('Success', 'Shift Berhasil Disimpan!');

            return response()->json([
                'status' => 200,
                'Message' => 'Berhasil Menyimpan Pergantian Shift!',
            ]);
        } catch (\Throwable $e) {
            DB::rollback();

            Log::channel('shiftLog')->error('Error simpan Lembur submit'.$e->getMessage());

            return response()->json([
                'status' => 500,
                'Message' => 'Terjadi Kesalahan Menyimpan, Silahkan Coba beberapa saat lagi! submit',
            ]);
        }
    }

    public function submit(Request $request)
    {
        $data = $request->All();
        $UserId = Auth::user()->karyawan->Kode_Karyawan;
        $employees = $data['params']['AssignShift']['employees'];
        $Kode_Perusahaan = '001';
        $Id_Shift = $data['params']['AssignShift']['shift'];

        /*
         * Data karyawan untuk pesan WhatsApp diambil SEKALI, sebelum transaksi
         * dibuka. Sebelumnya satu kueri per karyawan di dalam kedua cabang di
         * bawah, dan keduanya berjalan di dalam transaksi -- jadi tiap kueri
         * memperpanjang waktu kunci ditahan untuk SELURUH penugasan.
         *
         * Kunci petanya Kode_Karyawan. Kueri lamanya pun hanya menyaring kolom
         * itu tanpa Kode_Perusahaan, jadi peta ini mewarisi asumsi keunikan yang
         * sudah dipikulnya, bukan menambah yang baru. Skema memang tidak
         * menjaminnya (PK_Karyawan komposit, indeks Kode_Karyawan tidak unik),
         * tetapi datanya per 9 September 2026 bersih: 965 baris, 965
         * Kode_Karyawan berbeda, nol kembar.
         */
        $karyawanPerKode = Karyawan::whereIn('Kode_Karyawan', array_values((array) $employees))
            ->get()
            ->keyBy('Kode_Karyawan');

        DB::beginTransaction();
        try {
            // checking Lembur
            // dd($request->all());
            if ($data['params']['AssignShift']['shiftType'] == 'permanent') {
                $Tanggal = date('Y-m-d H:i:s', strtotime($data['params']['AssignShift']['dates']['start']));

                $shiftKerja = Shift_Kerja::select('Nama')->Where('ID_Shift', $Id_Shift)->first()->Nama;
                // dd($shiftKerja);
                foreach ($employees as $item) {
                    $Kode_Karyawan = $item;
                    /* Kueri "cek lembur" satu per karyawan dibuang: hasilnya tidak
                       pernah dibaca -- yang tertinggal hanya bekas dd()-nya. */

                    $finalInsert = DB::table('HRIS_Shift_Per_Karyawan')->insert([
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'ID_Shift' => $Id_Shift,
                        'Periode' => $Tanggal,
                        'UserID' => $UserId,
                    ]);
                    // whatsapp message to user
                    $userInsert = $karyawanPerKode[$Kode_Karyawan] ?? null;
                    /* kueri yang sama persis sudah dijalankan sebaris di atas */
                    $userInsertNoHp = $userInsert->HP ?? null;

                    if ($userInsert && $finalInsert && $userInsertNoHp && $userInsertNoHp != '-') {
                        $TanggalKirim = date('D, d M Y ', strtotime($data['params']['AssignShift']['dates']['start']));

                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $userInsertNoHp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'notif_ganti_shift',
                                'language' => [
                                    'code' => 'id',
                                    'policy' => 'deterministic',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => $userInsert->Nama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => 'Permanen',
                                            ],

                                            [
                                                'type' => 'text',
                                                'text' => $shiftKerja,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => Auth()->user()->karyawan->Nama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $TanggalKirim.' s/d Seterusnya',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ];

                        //    SendWhatsappMessage::dispatch($item, $pesan)->onQueue('default')->delay(now()->addSeconds(30));
                        //   SendWhatsappMessage::dispatch($item, $pesan)
                        //         ->onConnection('database')
                        //         ->onQueue('default')
                        //         ->delay(now()->addSeconds(10));
                        /* Diantrekan lewat pintu terpusat, bukan dikirim di dalam request.
                         * afterCommit sudah menyelamatkan lock, tetapi HTTP-nya tetap
                         * dijalankan satu per satu DI DALAM perulangan karyawan: N karyawan
                         * berarti N x (10-20 detik) sebelum respons kembali. SendWhatsappJob
                         * membawa afterCommit-nya sendiri, jadi jaminan "tidak ada WA untuk
                         * transaksi yang rollback" tetap berlaku. */
                        WhatsappLogHelper::sendAndLog(
                            $userInsert->Kode_Karyawan ?? $Kode_Karyawan ?? $item ?? null,
                            $userInsert->Nama ?? null,
                            $userInsertNoHp ?? $userInsert->HP ?? null,
                            null,
                            $pesan,
                        );
                    }
                }
            } elseif ($data['params']['AssignShift']['shiftType'] == 'temporary') {
                $Dates = $data['params']['AssignShift']['dates'];

                /* Tidak bergantung pada karyawan yang sedang diproses: $Id_Shift
                   tetap sama sepanjang perulangan, jadi kueri ini dulu diulang
                   sekali per karyawan untuk jawaban yang selalu sama. */
                $shiftKerja = Shift_Kerja::select('Nama')->Where('ID_Shift', $Id_Shift)->first()->Nama;

                foreach ($employees as $item) {
                    foreach ($Dates as $date) {
                        $finalInsert = DB::table('HRIS_Shift_Sementara')->insert([
                            'Kode_Perusahaan' => $Kode_Perusahaan,
                            'Kode_Karyawan' => $item,
                            'ID_Shift' => $Id_Shift,
                            'Tanggal' => $date,
                            'UserID' => $UserId,
                        ]);
                    }
                    // whatsapp message to user
                    $Kode_Karyawan = $item;
                    $userInsert = $karyawanPerKode[$Kode_Karyawan] ?? null;
                    /* kueri yang sama persis sudah dijalankan sebaris di atas */
                    $userInsertNoHp = $userInsert->HP ?? null;
                    // dd($userInsertNoHp);
                    // dd($userInsert);
                    if ($userInsert && $finalInsert && $userInsertNoHp && $userInsertNoHp != '-') {
                        if (count($Dates) > 1) {
                            $temp = [];
                            foreach ($Dates as $date) {
                                $temp[] = date('d', strtotime($date));
                            }
                            $hari = implode(',', $temp);
                            $bulanTahun = date('M Y', strtotime($Dates[0]));
                            $parseDates = $hari.' '.$bulanTahun;
                        } else {
                            foreach ($Dates as $date) {
                                $parseDates = date('D, d M Y', strtotime($date));
                            }
                        }
                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $userInsertNoHp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'notif_ganti_shift',
                                'language' => [
                                    'code' => 'id',
                                    'policy' => 'deterministic',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => $userInsert->Nama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => 'Sementara',
                                            ],

                                            [
                                                'type' => 'text',
                                                'text' => $shiftKerja,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => Auth()->user()->karyawan->Nama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $parseDates,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ];

                        /* Diantrekan lewat pintu terpusat, bukan dikirim di dalam request.
                         * afterCommit sudah menyelamatkan lock, tetapi HTTP-nya tetap
                         * dijalankan satu per satu DI DALAM perulangan karyawan: N karyawan
                         * berarti N x (10-20 detik) sebelum respons kembali. SendWhatsappJob
                         * membawa afterCommit-nya sendiri, jadi jaminan "tidak ada WA untuk
                         * transaksi yang rollback" tetap berlaku. */
                        WhatsappLogHelper::sendAndLog(
                            $userInsert->Kode_Karyawan ?? $Kode_Karyawan ?? $item ?? null,
                            $userInsert->Nama ?? null,
                            $userInsertNoHp ?? $userInsert->HP ?? null,
                            null,
                            $pesan,
                        );
                    }
                }
            }
            DB::commit();
            $this->markLegacyShiftDirty($request, 'SHIFT_LEGACY_CHANGED');
            Alert::success('Success', 'Shift Berhasil Disimpan!');

            return response()->json([
                'status' => 200,
                'Message' => 'Berhasil Menyimpan Pergantian Shift!',
            ]);
        } catch (\Throwable $e) {
            DB::rollback();

            Log::channel('shiftLog')->error('Error simpan Lembur submit'.$e->getMessage());

            return response()->json([
                'status' => 500,
                'Message' => 'Terjadi Kesalahan Menyimpan, Silahkan Coba beberapa saat lagi! submit',
            ]);
        }
    }

    public function updateAdmin(Request $request)
    {
        DB::BeginTransaction();

        try {
            $employees = $request->params['employee']['Kode_Karyawan'];
            $UserId = Auth::user()->karyawan->Kode_Karyawan;

            // dd($request->all());
            $Tanggal = date('Y-m-d H:i:s', strtotime($request->params['date']));
            $finalInsert = DB::table('HRIS_Shift_Sementara')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $employees,
                'ID_Shift' => $request->params['shift'],
                'Tanggal' => $Tanggal,
                'UserID' => $UserId,
            ]);

            // wa
            $Kode_Karyawan = $employees;
            $userInsert = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->first() ?? null;
            $shiftKerja = Shift_Kerja::select('Nama')->Where('ID_Shift', $request->params['shift'])->first()->Nama;
            /* kueri yang sama persis sudah dijalankan sebaris di atas */
            $userInsertNoHp = $userInsert->HP ?? null;

            DB::commit();
            $this->markLegacyShiftDirty($request, 'SHIFT_LEGACY_CHANGED_ADMIN');
            Alert::success('Success', 'Shift Berhasil Disimpan!');

            return response()->json([
                'status' => 200,
                'Message' => 'Berhasil Menyimpan Shift',
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::channel('shiftLog')->error('Terjadi kesalahan saat mengupdate shift update'.$e->getMessage());

            return response()->json([
                'status' => 500,
                'Message' => 'Terjadi kesalahan saat mengupdate shift update!',
            ]);
        }
    }

    public function update(Request $request)
    {
        DB::BeginTransaction();

        try {
            $employees = $request->params['employee']['Kode_Karyawan'];
            $UserId = Auth::user()->karyawan->Kode_Karyawan;

            // dd($request->all());
            $Tanggal = date('Y-m-d H:i:s', strtotime($request->params['date']));
            $finalInsert = DB::table('HRIS_Shift_Sementara')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $employees,
                'ID_Shift' => $request->params['shift'],
                'Tanggal' => $Tanggal,
                'UserID' => $UserId,
            ]);

            // wa
            $Kode_Karyawan = $employees;
            $userInsert = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->first() ?? null;
            $shiftKerja = Shift_Kerja::select('Nama')->Where('ID_Shift', $request->params['shift'])->first()->Nama;
            /* kueri yang sama persis sudah dijalankan sebaris di atas */
            $userInsertNoHp = $userInsert->HP ?? null;

            if ($userInsert && $finalInsert && $userInsertNoHp && $userInsertNoHp != '-') {
                $TanggalKirim = date('D, d M Y ', strtotime($request->params['date']));

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $userInsertNoHp,
                    'type' => 'template',
                    'template' => [
                        'name' => 'notif_ganti_shift',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $userInsert->Nama,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Sementara',
                                    ],

                                    [
                                        'type' => 'text',
                                        'text' => $shiftKerja,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => Auth()->user()->karyawan->Nama,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $TanggalKirim,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                /* Diantrekan lewat pintu terpusat, bukan dikirim di dalam request.
                 * afterCommit sudah menyelamatkan lock, tetapi HTTP-nya tetap
                 * dijalankan satu per satu DI DALAM perulangan karyawan: N karyawan
                 * berarti N x (10-20 detik) sebelum respons kembali. SendWhatsappJob
                 * membawa afterCommit-nya sendiri, jadi jaminan "tidak ada WA untuk
                 * transaksi yang rollback" tetap berlaku. */
                WhatsappLogHelper::sendAndLog(
                    $userInsert->Kode_Karyawan ?? $Kode_Karyawan ?? $item ?? null,
                    $userInsert->Nama ?? null,
                    $userInsertNoHp ?? $userInsert->HP ?? null,
                    null,
                    $pesan,
                );
            }

            DB::commit();
            $this->markLegacyShiftDirty($request, 'SHIFT_LEGACY_CHANGED');
            Alert::success('Success', 'Shift Berhasil Disimpan!');

            return response()->json([
                'status' => 200,
                'Message' => 'Berhasil Menyimpan Shift',
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::channel('shiftLog')->error('Terjadi kesalahan saat mengupdate shift update'.$e->getMessage());

            return response()->json([
                'status' => 500,
                'Message' => 'Terjadi kesalahan saat mengupdate shift update!',
            ]);
        }
    }

    private function markLegacyShiftDirty(Request $request, string $reason): void
    {
        $params = (array) $request->input('params', []);
        $assign = (array) ($params['AssignShift'] ?? []);
        $employees = $params['employees'] ?? $params['karyawan'] ?? $params['Kode_Karyawan'] ?? $params['employee'] ?? null;

        if (! $employees && isset($assign['selectedUsers'])) {
            $employees = $assign['selectedUsers'];
        }

        if (! $employees && isset($assign['employees'])) {
            $employees = $assign['employees'];
        }

        $employees = collect(is_array($employees) ? $employees : [$employees])
            ->filter()
            ->values();

        $dateRanges = [];
        if (isset($assign['dates'])) {
            $rawDates = $assign['dates'];
            if (is_array($rawDates) && isset($rawDates['start'])) {
                $dateRanges[] = [
                    'tgl_awal' => $rawDates['start'],
                    'tgl_akhir' => $rawDates['end'] ?? $rawDates['start'],
                ];
            } elseif (is_array($rawDates)) {
                foreach ($rawDates as $date) {
                    if (! is_array($date)) {
                        $dateRanges[] = [
                            'tgl_awal' => $date,
                            'tgl_akhir' => $date,
                        ];
                    }
                }
            } else {
                $dateRanges[] = [
                    'tgl_awal' => $rawDates,
                    'tgl_akhir' => $rawDates,
                ];
            }
        }

        foreach (['tanggal', 'Tanggal', 'date', 'Tanggal_Shift'] as $key) {
            if (! empty($params[$key])) {
                $dateRanges[] = [
                    'tgl_awal' => $params[$key],
                    'tgl_akhir' => $params[$key],
                ];
            }
        }

        $dateRanges = collect($dateRanges)
            ->filter(fn ($range) => ! empty($range['tgl_awal']))
            ->values();

        if ($employees->isEmpty() || $dateRanges->isEmpty()) {
            return;
        }

        $targets = [];
        foreach ($employees as $employee) {
            foreach ($dateRanges as $range) {
                $targets[] = [
                    'kode_karyawan' => $employee,
                    'tgl_awal' => $range['tgl_awal'],
                    'tgl_akhir' => $range['tgl_akhir'],
                ];
            }
        }

        $this->attendanceDirtyResolver->markMany($targets, $reason, 'SHIFT_LEGACY_CONTROLLER', 1);
    }
}
