<?php

namespace App\Http\Controllers\ESS\Attendance\Overtime\Management;

use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Attendance\Overtime\Models\Lembur;
use App\Models\ESS\Attendance\Overtime\Models\LemburDet;
use App\Models\ESS\Attendance\Overtime\Models\LemburDetail;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;
use Vinkla\Hashids\Facades\Hashids;

class OvertimeController extends Controller
{
    /**
     * Grace period pembatalan lembur (jam) setelah estimasi akhir lembur.
     * Leader boleh membatalkan selama now <= (Tanggal_Lembur_Sampai + grace).
     * Admin melewati batas ini sepenuhnya.
     */
    private const CANCEL_GRACE_HOURS = 7;

    public function __construct(
        HrisAttendanceSnapshotService $attendanceSnapshotService,
        private readonly AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->attendanceService = $attendanceSnapshotService;
    }

    public function index()
    {
        try {
            $title = 'INI LEMBUR';
            $karyawanAuth = Auth()->user()->karyawan->Kode_Karyawan;
            // $karyawanID = Auth()->user()->Id_Users;
            $karyawanID = Auth()->user()->karyawan->Kode_Karyawan;

            // Ini jika jam ny cutouff nya hanya 1 tanpa melihat hari
            // $cutoffHour = 4;

            // $now = now();

            // if ((int)$now->format('H') < $cutoffHour) {
            //     $startDate = $now->subDay()->format('Y-m-d');
            // } else {
            //     $startDate = $now->format('Y-m-d');
            // }

            // $week = [];
            // $date = $startDate;
            // for ($i = 0; $i <= 2; $i++) {
            //     $week[] = $date;
            //     $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
            // }
            // ini jika jam cutoff ny lbih dari satu dan melihat hari
            $now = now();
            // $now = now()->setTime(2, 0); // untuk testing

            // $now = Carbon::createFromFormat('Y-m-d H:i:s', '2025-08-31 02:30:00');

            $dayName = $now->format('l'); // Monday, Tuesday, ..., Saturday, Sunday

            // Tentukan cutoff jam berdasarkan hari
            if (in_array($dayName, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])) {
                $cutoffHour = 11; // jam 3 pagi
            } elseif ($dayName === 'Saturday') {
                $cutoffHour = 11; // jam 3 pagi
            } else {
                $cutoffHour = 11; // jam 5 pagi
            }

            // Logika tentukan start date
            if ((int) $now->format('H') < $cutoffHour) {
                $startDate = $now->subDay()->format('Y-m-d');
            } else {
                $startDate = $now->format('Y-m-d');
            }

            // Generate list tanggal
            $week = [];
            $date = $startDate;
            for ($i = 0; $i <= 2; $i++) {
                $week[] = $date;
                $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
            }

            // dd($week);
            // dd($dayName, $cutoffHour, $now->format('Y-m-d H:i:s'), $week);

            // $currentDate = '2025-07-21';
            // $today = date('Y-m-d');
            // $week = [];

            // // Perulangan akan terus berjalan selama $currentDate kurang dari atau sama dengan $today
            // while (strtotime($currentDate) <= strtotime($today)) {
            //     $week[] = $currentDate;
            //     $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));
            // }

            /* Query shift DIHAPUS bersama payload-nya: halaman Overtime dan
               OvertimeAdmin punya data.shiftList bernama sama, dan di Vue data
               menang atas props — nilai ini tak pernah terbaca. Daftar shift di
               kedua layar itu selalu diambil ulang lewat API. */

            $query = "SELECT
                        ISNULL(
                            (
                                SELECT TOP (1)
                                    CASE
                                        -- Jika CHECKTIME tidak NULL, status 'COMPLETED' dan tampilkan waktu checkout
                                        WHEN z.CHECKTIME IS NOT NULL THEN
                                            CONVERT(VARCHAR, z.CHECKTIME, 120)

                                        -- Jika CHECKTIME NULL dan Tanggal_Lembur_Sampai sudah lewat (EXPIRED)
                                        WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN
                                            'EXPIRED'

                                        -- Jika CHECKTIME NULL dan Tanggal_Lembur_Sampai masih berlaku (IN PROGRESS)
                                        WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= CAST(GETDATE() AS DATETIME) THEN
                                            'IN PROGRESS'

                                        ELSE
                                            'UNKNOWN'
                                    END AS CHECKOUT
                                FROM
                                    HRIS_Shift_Kerja v
                                    INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
                                    INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
                                    INNER JOIN HRIS_Shift_Per_Karyawan y ON v.ID_Shift = y.ID_Shift
                                    INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
                                WHERE
                                    y.Kode_Perusahaan = d.Kode_Perusahaan
                                    AND y.Kode_Karyawan = d.Kode_Karyawan
                                    AND z.CHECKTIME BETWEEN
                                        CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar)
                                        AND CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar)
                            ),
                            CASE
                            WHEN CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN  'EXPIRED'
                            else 'IN PROGRESS'
                            END

                        ) AS CHECKOUT,
                        (a.Tanggal + a.Jam) AS T_Pengaju,
                        a.UserID AS Pengaju,
                        a.No_Transaksi,
                        d.Nama AS name,
                        d.UserID_Absen As userId,
                        c.Tanggal_Lembur_Dari AS start_time,
                        c.Tanggal_Lembur_Sampai AS end_time,
                        c.Alasan AS reason
                    FROM
                        Transaksi_Lembur a
                        INNER JOIN Transaksi_Lembur_Det b ON a.No_Transaksi = b.No_Transaksi
                        INNER JOIN Transaksi_Lembur_Detail c ON a.No_Transaksi = c.No_Transaksi
                        INNER JOIN Karyawan d ON b.Kode_Karyawan = d.Kode_Karyawan
                        where a.status is NULL
                    GROUP BY
                        a.UserID,
                        d.Nama,
                        a.Tanggal,
                        a.Jam,
                        c.Tanggal_Lembur_Dari,
                        c.Tanggal_Lembur_Sampai,
                        c.Alasan,
                        d.Kode_Karyawan,
                        d.UserID,
                        d.Kode_Perusahaan,
                        a.No_Transaksi,
                        d.UserID_Absen,
                        d.UserID_Absen
                    ORDER BY c.Tanggal_Lembur_Dari
                        ";

            $resultPerUser = DB::select($query);
            $queryNoTransaksi = '
                select
                a.No_Transaksi,
                a.Kode_Perusahaan,
                a.Tanggal,
                a.Jam,
                b.Nama
                from
                Transaksi_Lembur a,
                Karyawan b,
                View_Divisi_Sub_Divisi c,
                KPI_Users d
                where
                a.UserID = b.Kode_Karyawan
                and b.UserID_Web = d.Id_Users
                and b.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                and a.UserID = ?
                and a.status is null
                order by a.Tanggal desc, a.jam desc';
            $resultNo_Result = DB::select($queryNoTransaksi, [$karyawanID]);

            // $queryUser = "SELECT a.Nama as name, a.UserID_Absen as userId FROM Karyawan a, HRIS_Shift_Per_Karyawan b
            //         WHERE a.Kode_Karyawan = b.Kode_Karyawan AND a.UserID_Absen IS NOT NULL group by a.Nama, a.UserID_Absen ";
            // $users = DB::select($queryUser);
            return inertia('ESS/Attendance/Overtime/Management/Overtime', [
                'title' => $title,
                'data' => $resultNo_Result,
                'tanggal' => $week,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal Mengambil data'.$e->getMessage());

            Alert::error('error', 'Terjadi Error saat menampilkan page lembur!');

            return back();
        }
    }

    public function index2()
    {
        return inertia('ESS/Attendance/Overtime/Management/Overtime');
    }

    public function indexAdmin()
    {
        try {
            $title = 'INI LEMBUR';
            $karyawanAuth = Auth()->user()->karyawan->Kode_Karyawan;
            // $karyawanID = Auth()->user()->Id_Users;
            $karyawanID = Auth()->user()->karyawan->Kode_Karyawan;
            $date = date('Y-m-d');

            $week = [];
            for ($i = 0; $i <= 2; $i++) {
                $week[] = $date;

                $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
            }
            /* Query shift DIHAPUS bersama payload-nya: halaman Overtime dan
               OvertimeAdmin punya data.shiftList bernama sama, dan di Vue data
               menang atas props — nilai ini tak pernah terbaca. Daftar shift di
               kedua layar itu selalu diambil ulang lewat API. */

            $query = "SELECT
                        ISNULL(
                            (
                                SELECT TOP (1)
                                    CASE
                                        -- Jika CHECKTIME tidak NULL, status 'COMPLETED' dan tampilkan waktu checkout
                                        WHEN z.CHECKTIME IS NOT NULL THEN
                                            CONVERT(VARCHAR, z.CHECKTIME, 120)

                                        -- Jika CHECKTIME NULL dan Tanggal_Lembur_Sampai sudah lewat (EXPIRED)
                                        WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN
                                            'EXPIRED'

                                        -- Jika CHECKTIME NULL dan Tanggal_Lembur_Sampai masih berlaku (IN PROGRESS)
                                        WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= CAST(GETDATE() AS DATETIME) THEN
                                            'IN PROGRESS'

                                        ELSE
                                            'UNKNOWN'
                                    END AS CHECKOUT
                                FROM
                                    HRIS_Shift_Kerja v
                                    INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
                                    INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
                                    INNER JOIN HRIS_Shift_Per_Karyawan y ON v.ID_Shift = y.ID_Shift
                                    INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
                                WHERE
                                    y.Kode_Perusahaan = d.Kode_Perusahaan
                                    AND y.Kode_Karyawan = d.Kode_Karyawan
                                    AND z.CHECKTIME BETWEEN
                                        CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar)
                                        AND CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar)
                            ),
                            CASE
                            WHEN CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN  'EXPIRED'
                            else 'IN PROGRESS'
                            END

                        ) AS CHECKOUT,
                        (a.Tanggal + a.Jam) AS T_Pengaju,
                        a.UserID AS Pengaju,
                        a.No_Transaksi,
                        d.Nama AS name,
                        d.UserID_Absen As userId,
                        c.Tanggal_Lembur_Dari AS start_time,
                        c.Tanggal_Lembur_Sampai AS end_time,
                        c.Alasan AS reason
                    FROM
                        Transaksi_Lembur a
                        INNER JOIN Transaksi_Lembur_Det b ON a.No_Transaksi = b.No_Transaksi
                        INNER JOIN Transaksi_Lembur_Detail c ON a.No_Transaksi = c.No_Transaksi
                        INNER JOIN Karyawan d ON b.Kode_Karyawan = d.Kode_Karyawan
                        where a.status is NULL
                    GROUP BY
                        a.UserID,
                        d.Nama,
                        a.Tanggal,
                        a.Jam,
                        c.Tanggal_Lembur_Dari,
                        c.Tanggal_Lembur_Sampai,
                        c.Alasan,
                        d.Kode_Karyawan,
                        d.UserID,
                        d.Kode_Perusahaan,
                        a.No_Transaksi,
                        d.UserID_Absen,
                        d.UserID_Absen
                    ORDER BY c.Tanggal_Lembur_Dari
                        ";

            $resultPerUser = DB::select($query);
            $queryNoTransaksi = '
                select
                a.No_Transaksi,
                a.Kode_Perusahaan,
                a.Tanggal,
                a.Jam,
                b.Nama
                from
                Transaksi_Lembur a,
                Karyawan b,
                View_Divisi_Sub_Divisi c,
                KPI_Users d
                where
                a.UserID = b.Kode_Karyawan
                and b.UserID_Web = d.Id_Users
                and b.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                and a.status is null
                order by a.Tanggal desc, a.jam desc';
            $resultNo_Result = DB::select($queryNoTransaksi);
            $queryUser = 'SELECT a.Nama as name, a.UserID_Absen as userId FROM Karyawan a, HRIS_Shift_Per_Karyawan b
                    WHERE a.Kode_Karyawan = b.Kode_Karyawan AND a.UserID_Absen IS NOT NULL group by a.Nama, a.UserID_Absen ';
            $users = DB::select($queryUser);

            return inertia('ESS/Attendance/Overtime/Management/OvertimeAdmin', [
                'title' => $title,
                'data' => $resultNo_Result,
                'dataUser' => $resultPerUser,
                'tanggal' => $week,
                'userChoose' => $users,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal Mengambil data'.$e->getMessage());

            Alert::error('error', 'Terjadi Error saat menampilkan page lembur!');

            return back();
        }

        // dd($resultNo_Result);

        //    $test = "
        //         select Nama, UserID_Web from karyawan where nama like '%ridho%'
        //     ";

        //     $rsTest = DB::select($test);
        //     $collection = collect($rsTest);

        //     $hashedData = $collection->map(function ($item) {
        //         if (isset($item->UserID_Web)) {
        //             $item->UserID_Web = Hashids::connection('custom')->encode($item->UserID_Web);
        //         }
        //         if (isset($item->Nama)) {
        //             $item->Nama = Crypt::encryptString($item->Nama);
        //         }
        //         return $item;
        //     });

        //     $unhashedData = $hashedData->map(function ($item){
        //        if (isset($item->UserID_Web)) {
        //             $item->UserID_Web = Hashids::connection('custom')->decode($item->UserID_Web);
        //         }
        //         if (isset($item->Nama)) {
        //             $item->Nama = Crypt::decryptString($item->Nama);
        //         }
        //         return $item;
        //     });

        //     dd($hashedData);
    }

    public function getData(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $tanggalFilter = $validated['tanggalFilter'] ?? null;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            // === QUERY UTAMA ===
            $baseQuery = "
                SELECT
                    a.No_Transaksi,
                    a.Kode_Perusahaan,
                    a.Tanggal,
                    a.Jam,
                    a.UserID as Nama,
                    a.Status,
                    a.Alasan_Pembatalan,
                    (
                        SELECT STRING_AGG(b2.Nama, ', ')
                        FROM Transaksi_Lembur_Detail da2
                        JOIN Karyawan b2 ON da2.Kode_Karyawan = b2.Kode_Karyawan
                        WHERE da2.No_Transaksi = a.No_Transaksi
                    ) AS Assignment
                     ,( SELECT top 1 Tanggal_Lembur_Sampai
                        FROM Transaksi_Lembur_Detail da2
                        JOIN Karyawan b2 ON da2.Kode_Karyawan = b2.Kode_Karyawan
                        WHERE da2.No_Transaksi = a.No_Transaksi order by Tanggal_Lembur_Sampai desc) as Tanggal_Assign
                FROM Transaksi_Lembur a
                WHERE a.UserID = ?
                AND (a.status IS NULL OR a.status = 'Y')
            ";

            $queryCount = "
                SELECT COUNT(*) AS total
                FROM Transaksi_Lembur a
                WHERE a.UserID = ?
                AND (a.status IS NULL OR a.status = 'Y')
            ";

            $params = [$Kode_Karyawan];
            $countParams = [$Kode_Karyawan];

            // === FILTER SEARCH ===
            if ($search) {
                $baseQuery .= '
                    AND EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail da
                        JOIN Karyawan b ON da.Kode_Karyawan = b.Kode_Karyawan
                        WHERE da.No_Transaksi = a.No_Transaksi
                        AND b.Nama LIKE ?
                    )
                ';

                $queryCount .= '
                    AND EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail da
                        JOIN Karyawan b ON da.Kode_Karyawan = b.Kode_Karyawan
                        WHERE da.No_Transaksi = a.No_Transaksi
                        AND b.Nama LIKE ?
                    )
                ';

                $searchParam = '%'.$search.'%';
                $params[] = $searchParam;
                $countParams[] = $searchParam;
            }

            // === FILTER TANGGAL ===
            if ($tanggalFilter) {
                $tanggalFilter = preg_replace('/\s*\(.*\)$/', '', trim($tanggalFilter));

                try {
                    $tanggalSQL = Carbon::parse($tanggalFilter)->format('Y-m-d');
                } catch (\Exception $e) {
                    $tanggalSQL = null;
                }

                if ($tanggalSQL) {
                    $baseQuery .= '
                        AND EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail da
                            WHERE da.No_Transaksi = a.No_Transaksi
                            AND CAST(? AS DATE) BETWEEN
                                CAST(da.Tanggal_Lembur_Dari AS DATE)
                                AND CAST(da.Tanggal_Lembur_Sampai AS DATE)
                        )
                    ';

                    $queryCount .= '
                        AND EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail da
                            WHERE da.No_Transaksi = a.No_Transaksi
                            AND CAST(? AS DATE) BETWEEN
                                CAST(da.Tanggal_Lembur_Dari AS DATE)
                                AND CAST(da.Tanggal_Lembur_Sampai AS DATE)
                        )
                    ';

                    $params[] = $tanggalSQL;
                    $countParams[] = $tanggalSQL;
                }
            }

            // === HITUNG TOTAL ===
            $countResult = DB::selectOne($queryCount, $countParams);
            $total = $countResult->total ?? 0;

            // === DATA DENGAN PAGINATION ===
            $queryData =
                $baseQuery.
                '
                ORDER BY a.Tanggal DESC, a.Jam DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ';

            $params[] = $offset;
            $params[] = $perPage;

            $dataFinal = DB::select($queryData, $params);

            // === PAGINATION ===
            $final_page = new LengthAwarePaginator($dataFinal, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $final_page,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data lembur: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Terjadi kesalahan saat mengambil data lembur',
                ],
                500,
            );
        }
    }

    public function getDataAdmin(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $tanggalFilter = $validated['tanggalFilter'] ?? null;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            // === QUERY UTAMA ===
            $baseQuery = "
                SELECT
                    a.No_Transaksi,
                    a.Kode_Perusahaan,
                    a.Tanggal,
                    a.Jam,
                    a.UserID as Nama,
                    a.Status,
                    a.Alasan_Pembatalan,
                    (
                        SELECT STRING_AGG(b2.Nama, ', ')
                        FROM Transaksi_Lembur_Detail da2
                        JOIN Karyawan b2 ON da2.Kode_Karyawan = b2.Kode_Karyawan
                        WHERE da2.No_Transaksi = a.No_Transaksi
                    ) AS Assignment,
                    CASE WHEN a.UserID = ? THEN 'TRUE' ELSE 'FALSE' END as canDelete
                FROM Transaksi_Lembur a
                WHERE (a.status IS NULL OR a.status = 'Y')
            ";

            $queryCount = "
                SELECT COUNT(*) AS total
                FROM Transaksi_Lembur a
                WHERE (a.status IS NULL OR a.status = 'Y')
            ";

            $params = [$Kode_Karyawan];
            $countParams = [];

            // === FILTER SEARCH ===
            if ($search) {
                $baseQuery .= '
                    AND EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail da
                        JOIN Karyawan b ON da.Kode_Karyawan = b.Kode_Karyawan
                        WHERE da.No_Transaksi = a.No_Transaksi
                        AND b.Nama LIKE ?
                    )
                ';

                $queryCount .= '
                    AND EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail da
                        JOIN Karyawan b ON da.Kode_Karyawan = b.Kode_Karyawan
                        WHERE da.No_Transaksi = a.No_Transaksi
                        AND b.Nama LIKE ?
                    )
                ';

                $searchParam = '%'.$search.'%';
                $params[] = $searchParam;
                $countParams[] = $searchParam;
            }

            // === FILTER TANGGAL ===
            if ($tanggalFilter) {
                $tanggalFilter = preg_replace('/\s*\(.*\)$/', '', trim($tanggalFilter));

                try {
                    $tanggalSQL = Carbon::parse($tanggalFilter)->format('Y-m-d');
                } catch (\Exception $e) {
                    $tanggalSQL = null;
                }

                if ($tanggalSQL) {
                    $baseQuery .= '
                        AND EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail da
                            WHERE da.No_Transaksi = a.No_Transaksi
                            AND CAST(? AS DATE) BETWEEN
                                CAST(da.Tanggal_Lembur_Dari AS DATE)
                                AND CAST(da.Tanggal_Lembur_Sampai AS DATE)
                        )
                    ';

                    $queryCount .= '
                        AND EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail da
                            WHERE da.No_Transaksi = a.No_Transaksi
                            AND CAST(? AS DATE) BETWEEN
                                CAST(da.Tanggal_Lembur_Dari AS DATE)
                                AND CAST(da.Tanggal_Lembur_Sampai AS DATE)
                        )
                    ';

                    $params[] = $tanggalSQL;
                    $countParams[] = $tanggalSQL;
                }
            }

            // === HITUNG TOTAL ===
            $countResult = DB::selectOne($queryCount, $countParams);
            $total = $countResult->total ?? 0;

            // === DATA DENGAN PAGINATION ===
            $queryData =
                $baseQuery.
                '
                ORDER BY a.Tanggal DESC, a.Jam DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ';

            $params[] = $offset;
            $params[] = $perPage;

            $dataFinal = DB::select($queryData, $params);

            // === PAGINATION ===
            $final_page = new LengthAwarePaginator($dataFinal, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $final_page,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal mengambil data lembur Admin: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Terjadi kesalahan saat mengambil data lembur Admin',
                ],
                500,
            );
        }
    }

    public function getWaktuShift(Request $request)
    {
        try {
            $ID_shift = $request->shift;
            $Hari = date('N', strtotime($request->date));

            // if ($Hari == 6) {
            //     $Hari = 7;
            // } elseif ($Hari == 7) {
            //     $Hari = 1;
            // }
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }

            // dd($Hari);

            $query = "
                select
                a.Nama,
                c.Kode_Waktu_Kerja,
                b.Hari,
                c.Jam_Keluar,
                c.Flag_CO_Lintas_Hari,
                COALESCE(NULLIF(c.Jam_Selesai_Lintas_Hari, ''), NULLIF(c.Jam_Selesai_Absen_Keluar, '')) AS Jam_selesai_absen
                from
                HRIS_Shift_Kerja a,
                HRIS_Shift_Kerja_Detail b,
                HRIS_Waktu_Kerja c
                where
                a.ID_Shift = b.ID_Shift
                and b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
                and b.Hari = :Hari
                and a.ID_Shift = :Shift

            ";

            $result = DB::select($query, ['Hari' => $Hari, 'Shift' => $ID_shift]);

            // dd($result);

            if (empty($result)) {
                return response()->json(
                    [
                        'status' => 200,
                        'data' => [],
                    ],
                    404,
                );
            }

            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data waktu shift: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'error' => 'Terjadi error saat mengambil data shift',
                ],
                404,
            );
        }
    }

    public function getShift(Request $request)
    {
        try {
            $tanggal = $request['tanggal'];
            // dd(date('Y-m-d',strtotime($tanggal)) <= date('Y-m-d'));
            if (date('Y-m-d', strtotime($tanggal)) == date('Y-m-d')) {
                $Hari = date('N', strtotime($tanggal));
                if ($Hari <= 6) {
                    $Hari += 1;
                    $time = date('H:i:s');
                    $query = "
            select
            a.ID_Shift,
            a.Nama as Shift,
            b.Hari,
            c.Jam_Masuk,
            c.Jam_Keluar,
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
            AND c.Jam_Keluar >= ?
            ";
                    $result = DB::select($query, [$Hari, $time]);
                } elseif ($Hari == 7) {
                    $Hari = 1;
                }
            } else {
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
            ";
                $result = DB::select($query, [$Hari]);
            }

            // dd($result);
            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data shift: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'error' => 'Terjadi error saat mengambil data shift',
                ],
                404,
            );
        }
    }

    public function userActive(Request $request)
    {
        // param
        try {
            // dd($request->all());
            $Kode_Perusahaan = '001';
            // $ID_shift = $request->input('shift');
            $Hari = date('N', strtotime($request->input('date')));
            $time = date('H:i:s', strtotime($request->input('time')));
            $DateAndtime = date('Y-m-d H:i:s', strtotime($request->input('date').' '.$request->input('time')));
            $Shift = $request->shift;
            // dd($time);
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }
            $Tanggal = $request->input('date');
            // dd($divisi);
            $karyawan = Auth()->user()->karyawan->Kode_Karyawan;

            $whitelistLevel = config('services.hcis.overtime_level_list');
            // dd($karyawan);

            // $query = "
            //     WITH KaryawanShiftPermanen AS (
            //         SELECT
            //             e.Kode_Karyawan,
            //             f.nama_divisi,
            //             f.nama_sub_divisi,
            //             e.UserID_Absen,
            //             c.Jam_Masuk,
            //             c.Jam_Keluar,
            //             e.Nama,
            //             a.ID_Shift,
            //             a.Nama as Nama_Shift,
            //             d.Periode,
            //             COALESCE(NULLIF(c.Jam_Selesai_Lintas_Hari, ''), NULLIF(c.Jam_Selesai_Absen_Keluar, '')) AS Jam_selesai_absen,
            //             ROW_NUMBER() OVER (
            //                 PARTITION BY e.Kode_Karyawan
            //                 ORDER BY d.Periode DESC, d.Urut DESC
            //             ) as rn_periode
            //         FROM HRIS_Shift_Kerja a
            //         INNER JOIN HRIS_Shift_Kerja_Detail b ON a.ID_Shift = b.ID_Shift
            //         INNER JOIN HRIS_Waktu_Kerja c ON b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
            //         INNER JOIN HRIS_Shift_Per_Karyawan d ON a.ID_Shift = d.ID_Shift
            //         INNER JOIN Karyawan e ON d.Kode_Karyawan = e.Kode_Karyawan
            //                             AND d.Kode_Perusahaan = e.Kode_Perusahaan
            //         INNER JOIN HRIS_Karyawan_Team h ON h.Kode_Karyawan = e.Kode_Karyawan
            //         INNER JOIN View_Divisi_Sub_Divisi f ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI
            //         INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g ON e.ID_Level_Jabatan = g.ID_Level_Jabatan
            //         WHERE e.Kode_Perusahaan = ?
            //         AND g.ID_Level  IN (1,2,21)
            //         AND e.Aktif = 'Y'
            //         AND b.Hari = ?
            //         AND h.Kode_Karyawan_Team = ?
            //         AND d.Periode <= ?

            //     ),
            //     ShiftPermanen AS (
            //         SELECT
            //             Kode_Karyawan,
            //             nama_divisi,
            //             nama_sub_divisi,
            //             UserID_Absen,
            //             Nama,
            //             Jam_Masuk,
            //             Jam_Keluar,
            //             ID_Shift,
            //             Nama_Shift,
            //             'Permanen' as Jenis_Shift,
            //             Periode,
            //             Jam_selesai_absen
            //         FROM KaryawanShiftPermanen
            //         WHERE rn_periode = 1
            //     ),
            //    ShiftSementaraRaw AS (
            //     SELECT
            //         e.Kode_Karyawan,
            //         f.nama_divisi,
            //         f.nama_sub_divisi,
            //         e.UserID_Absen,
            //         e.Nama,
            //         c.Jam_Masuk,
            //         c.Jam_Keluar,
            //         a.ID_Shift,
            //         a.Nama as Nama_Shift,
            //         'Sementara' as Jenis_Shift,
            //         d.Tanggal as Periode,
            //         COALESCE(NULLIF(c.Jam_Selesai_Lintas_Hari, ''), NULLIF(c.Jam_Selesai_Absen_Keluar, '')) AS Jam_selesai_absen,
            //         ROW_NUMBER() OVER (
            //             PARTITION BY e.Kode_Karyawan
            //             ORDER BY d.Urut DESC, d.Tanggal DESC
            //         ) AS rn
            //     FROM HRIS_Shift_Kerja a
            //     INNER JOIN HRIS_Shift_Kerja_Detail b
            //         ON a.ID_Shift = b.ID_Shift
            //     INNER JOIN HRIS_Waktu_Kerja c
            //         ON b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
            //     INNER JOIN HRIS_Shift_Sementara d
            //         ON a.ID_Shift = d.ID_Shift
            //     INNER JOIN Karyawan e
            //         ON d.Kode_Perusahaan = e.Kode_Perusahaan
            //     AND d.Kode_Karyawan = e.Kode_Karyawan
            //     INNER JOIN HRIS_Karyawan_Team h
            //         ON h.Kode_Karyawan = e.Kode_Karyawan
            //     INNER JOIN View_Divisi_Sub_Divisi f
            //         ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI
            //     INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g
            //         ON e.ID_Level_Jabatan = g.ID_Level_Jabatan
            //     WHERE e.Kode_Perusahaan = ?
            //     AND g.ID_Level IN (1,2,21)
            //     AND e.Aktif = 'Y'
            //     AND b.Hari = ?
            //     AND h.Kode_Karyawan_Team = ?
            //     AND d.Tanggal = ?
            // ),
            // ShiftSementara AS (
            //     SELECT *
            //     FROM ShiftSementaraRaw
            //     WHERE rn = 1
            // )

            //     SELECT
            //     CASE
            //     -- Kondisi shift lintas malam (Jam_Keluar <= Jam_Masuk)
            //     WHEN CONVERT(TIME, COALESCE(ss.Jam_Keluar, sp.Jam_Keluar))
            //         <= CONVERT(TIME, COALESCE(ss.Jam_Masuk, sp.Jam_Masuk))
            //     THEN
            //         CASE
            //             WHEN DATEADD(DAY, 1, CAST(? AS DATETIME)) BETWEEN
            //                 DATEADD(HOUR, 1, CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,?), 120)
            //                                 + ' ' + COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) AS DATETIME))
            //                 AND
            //                 DATEADD(HOUR, -2, CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,?), 120)
            //                                 + ' ' + COALESCE(ss.Jam_Masuk, sp.Jam_Masuk) AS DATETIME))
            //             THEN 'TRUE' ELSE 'FALSE'
            //         END

            //     -- Kondisi normal (tidak lintas malam)
            //     ELSE
            //         CASE
            //             WHEN CAST(? AS DATETIME) <
            //                 CAST(CONVERT(VARCHAR(10), ?, 120) + ' ' + COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) AS DATETIME)
            //             THEN
            //                 CASE
            //                     WHEN DATEADD(DAY, 1, CAST(? AS DATETIME)) BETWEEN
            //                         DATEADD(HOUR, 1, CAST(CONVERT(VARCHAR(10), ?, 120)
            //                                         + ' ' + COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) AS DATETIME))
            //                         AND
            //                         DATEADD(HOUR, -2, CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,?), 120)
            //                                         + ' ' + COALESCE(ss.Jam_Masuk, sp.Jam_Masuk) AS DATETIME))
            //                     THEN 'TRUE' ELSE 'FALSE'
            //                 END
            //             ELSE
            //                 CASE
            //                     WHEN CAST(? AS DATETIME) BETWEEN
            //                         DATEADD(HOUR, 1, CAST(CONVERT(VARCHAR(10), ?, 120)
            //                                         + ' ' + COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) AS DATETIME))
            //                         AND
            //                         DATEADD(HOUR, -2, CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,?), 120)
            //                                         + ' ' + COALESCE(ss.Jam_Masuk, sp.Jam_Masuk) AS DATETIME))
            //                     THEN 'TRUE' ELSE 'FALSE'
            //                 END
            //         END
            //     END AS JamComparison,
            //     CASE
            //             WHEN EXISTS (
            //                 SELECT 1
            //                 FROM Transaksi_Lembur_Detail a
            //                 JOIN Transaksi_Lembur b ON a.No_Transaksi = b.No_Transaksi
            //                 WHERE
            //                     a.Kode_Karyawan = COALESCE(ss.Kode_Karyawan, sp.Kode_Karyawan)
            //                     AND (
            //                         (
            //                             CONVERT(TIME, COALESCE(ss.Jam_Keluar, sp.Jam_Keluar))
            //                             <= CONVERT(TIME, COALESCE(ss.Jam_Masuk, sp.Jam_Masuk))
            //                             AND DATEADD(DAY,1, ?) = CAST(a.Tanggal_Lembur_Dari AS DATE)
            //                         )
            //                         OR
            //                         (
            //                             CONVERT(TIME, COALESCE(ss.Jam_Keluar, sp.Jam_Keluar))
            //                             > CONVERT(TIME, COALESCE(ss.Jam_Masuk, sp.Jam_Masuk))
            //                             AND ? = CAST(a.Tanggal_Lembur_Dari AS DATE)
            //                         )
            //                     )
            //                     AND b.Status IS NULL
            //             )
            //             THEN 'TRUE'
            //             ELSE 'FALSE'
            //         END AS lemburPernah,

            //         COALESCE(ss.Kode_Karyawan, sp.Kode_Karyawan) as userId,
            //         COALESCE(ss.UserID_Absen, sp.UserID_Absen) as UserID_Absen,
            //         COALESCE(ss.nama_divisi, sp.nama_divisi) as Divisi,
            //         COALESCE(ss.nama_sub_divisi, sp.nama_sub_divisi) as Sub_Divisi,
            //         COALESCE(ss.Nama, sp.Nama) as name,
            //         COALESCE(ss.Jam_Masuk, sp.Jam_Masuk) as Jam_Masuk,
            //         COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) as Jam_Keluar,
            //         COALESCE(ss.ID_Shift, sp.ID_Shift) as ID_Shift,
            //         COALESCE(ss.Nama_Shift, sp.Nama_Shift) as Nama_Shift,
            //         COALESCE(ss.Jam_selesai_absen, sp.Jam_selesai_absen) as Jam_selesai_absen,
            //         COALESCE(ss.Jenis_Shift, sp.Jenis_Shift) as Jenis_Shift
            //     FROM ShiftPermanen sp
            //     FULL OUTER JOIN ShiftSementara ss ON sp.Kode_Karyawan = ss.Kode_Karyawan
            //     where COALESCE(ss.Kode_Karyawan, sp.Kode_Karyawan) <> ?
            //     ORDER BY JamComparison desc
            //     ";

            //     // Parameter array harus berurutan sesuai dengan urutan ? dalam query
            //     $result = DB::select($query, [
            //         $Kode_Perusahaan,  // Parameter 1: KaryawanShiftPermanen.Kode_Perusahaan
            //         $Hari,             // Parameter 2: KaryawanShiftPermanen.Hari
            //         $karyawan,
            //         $Tanggal,

            //         $Kode_Perusahaan,  // Parameter 5: ShiftSementara.Kode_Perusahaan
            //         $Hari,             // Parameter 6: ShiftSementara.Hari
            //         $karyawan,
            //         $Tanggal,
            //         $DateAndtime,
            //         $Tanggal,
            //         $Tanggal,
            //         $DateAndtime,
            //         $Tanggal,
            //         $DateAndtime,
            //         $Tanggal,
            //         $Tanggal,
            //         $DateAndtime,
            //         $Tanggal,
            //         $Tanggal,
            //         $Tanggal,
            //         $Tanggal,
            //         $karyawan,           // Parameter 8: ShiftSementara.Tanggal

            //     ]);

            $query = "
                SELECT
                    -- ========================================
                    -- JAM COMPARISON
                    -- ========================================
                    CASE
                        WHEN sh.Jam_Keluar IS NULL THEN 'FALSE'

                        -- SHIFT LINTAS MALAM
                        WHEN CONVERT(TIME, sh.Jam_Keluar)
                            <= CONVERT(TIME, sh.Jam_Masuk)
                        THEN
                            CASE
                                WHEN DATEADD(DAY, 1, CAST(:datetime AS DATETIME)) BETWEEN
                                    DATEADD(HOUR, 1,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Keluar AS DATETIME))
                                AND
                                    DATEADD(HOUR, -2,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Masuk AS DATETIME))
                                THEN 'TRUE'
                                ELSE 'FALSE'
                            END

                        -- SHIFT NORMAL
                        ELSE
                            CASE
                                WHEN CAST(:datetime AS DATETIME) BETWEEN
                                    DATEADD(HOUR, 1,
                                        CAST(CONVERT(VARCHAR(10), :tanggal,120)
                                        + ' ' + sh.Jam_Keluar AS DATETIME))
                                AND
                                    DATEADD(HOUR, -2,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Masuk AS DATETIME))
                                THEN 'TRUE'
                                ELSE 'FALSE'
                            END
                    END AS JamComparison,

                    -- ========================================
                    -- LEMBUR PERNAH
                    -- ========================================
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail a
                            JOIN Transaksi_Lembur b
                                ON a.No_Transaksi = b.No_Transaksi
                            WHERE a.Kode_Karyawan = e.Kode_Karyawan
                            AND b.Status IS NULL
                            AND CAST(a.Tanggal_Lembur_Dari AS DATE) =
                                    CASE
                                        WHEN CONVERT(TIME, sh.Jam_Keluar)
                                            <= CONVERT(TIME, sh.Jam_Masuk)
                                        THEN DATEADD(DAY,1,:tanggal)
                                        ELSE :tanggal
                                    END
                        )
                        THEN 'TRUE'
                        ELSE 'FALSE'
                    END AS lemburPernah,

                    -- ========================================
                    -- DATA USER
                    -- ========================================
                    e.Kode_Karyawan AS userId,
                    e.UserID_Absen,
                    f.nama_divisi AS Divisi,
                    f.nama_sub_divisi AS Sub_Divisi,
                    e.Nama AS name,

                    sh.Jam_Masuk,
                    sh.Jam_Keluar,
                    sh.ID_Shift,
                    sh.Nama_Shift,
                    sh.ECOFX AS Jam_selesai_absen,
                    sh.Jenis_Shift,
                    sh.StatusHari

                FROM Karyawan e

                INNER JOIN HRIS_Karyawan_Team h
                    ON h.Kode_Karyawan = e.Kode_Karyawan

                INNER JOIN View_Divisi_Sub_Divisi f
                    ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI

                INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g
                    ON e.ID_Level_Jabatan = g.ID_Level_Jabatan

                OUTER APPLY (
                    SELECT TOP 1 *
                    FROM dbo.fn_GetShiftLengkap_Range(
                        :kode_perusahaan,
                        e.Kode_Karyawan,
                        :tanggal,
                        :tanggal
                    )
                ) sh

                WHERE e.Kode_Perusahaan = :kode_perusahaan
                AND e.Aktif = 'Y'
                AND g.ID_Level IN (
                    SELECT CAST(value AS INT)
                    FROM STRING_SPLIT(:whitelistLevel, ',')
                )
                AND h.Kode_Karyawan_Team = :kode_team
                AND e.Kode_Karyawan <> :exclude

                ORDER BY JamComparison DESC
                ";

            $result = DB::select($query, [
                'kode_perusahaan' => $Kode_Perusahaan,
                'tanggal' => $Tanggal,
                'datetime' => $DateAndtime,
                'kode_team' => $karyawan,
                'exclude' => $karyawan,
                'whitelistLevel' => implode(',', $whitelistLevel),
            ]);

            // dd($result);
            if (empty($result)) {
                return response()->json(
                    [
                        'status' => 404,
                        'message' => 'Data tidak ditemukan',
                    ],
                    404,
                );
            }

            $resultCollect = collect($result);
            $hashResult = $resultCollect->map(function ($item) {
                if (isset($item->userId)) {
                    $item->userId = Crypt::encryptString($item->userId);
                }

                return $item;
            });

            return response()->json([
                'status' => 200,
                'data' => $hashResult,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data userActive: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'error' => 'Terjadi error saat mengambil data userActive',
                ],
                404,
            );
        }
    }

    public function userActiveAll(Request $request)
    {
        // param
        // dd($request->all());
        try {
            $Kode_Perusahaan = '001';
            // $ID_shift = $request->input('shift');
            $Hari = date('N', strtotime($request->date));
            $time = date('H:i:s', strtotime($request->input('time')));
            $DateAndtime = date('Y-m-d H:i:s', strtotime($request->input('date').' '.$request->input('time')));
            $Shift = $request->shift;
            // dd($time);
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }
            $Tanggal = $request->date;
            // dd($divisi);
            $karyawan = Auth()->user()->karyawan->Kode_Karyawan;
            // dd($Tanggal);

            $query = "
                SELECT
                    -- ========================================
                    -- JAM COMPARISON
                    -- ========================================
                    CASE
                        WHEN sh.Jam_Keluar IS NULL THEN 'FALSE'

                        -- SHIFT LINTAS MALAM
                        WHEN CONVERT(TIME, sh.Jam_Keluar)
                            <= CONVERT(TIME, sh.Jam_Masuk)
                        THEN
                            CASE
                                WHEN DATEADD(DAY, 1, CAST(:datetime AS DATETIME)) BETWEEN
                                    DATEADD(HOUR, 1,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Keluar AS DATETIME))
                                AND
                                    DATEADD(HOUR, -2,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Masuk AS DATETIME))
                                THEN 'TRUE'
                                ELSE 'FALSE'
                            END

                        -- SHIFT NORMAL
                        ELSE
                            CASE
                                WHEN CAST(:datetime AS DATETIME) BETWEEN
                                    DATEADD(HOUR, 1,
                                        CAST(CONVERT(VARCHAR(10), :tanggal,120)
                                        + ' ' + sh.Jam_Keluar AS DATETIME))
                                AND
                                    DATEADD(HOUR, -2,
                                        CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,:tanggal),120)
                                        + ' ' + sh.Jam_Masuk AS DATETIME))
                                THEN 'TRUE'
                                ELSE 'FALSE'
                            END
                    END AS JamComparison,

                    -- ========================================
                    -- LEMBUR PERNAH
                    -- ========================================
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM Transaksi_Lembur_Detail a
                            JOIN Transaksi_Lembur b
                                ON a.No_Transaksi = b.No_Transaksi
                            WHERE a.Kode_Karyawan = e.Kode_Karyawan
                            AND b.Status IS NULL
                            AND CAST(a.Tanggal_Lembur_Dari AS DATE) =
                                    CASE
                                        WHEN CONVERT(TIME, sh.Jam_Keluar)
                                            <= CONVERT(TIME, sh.Jam_Masuk)
                                        THEN DATEADD(DAY,1,:tanggal)
                                        ELSE :tanggal
                                    END
                        )
                        THEN 'TRUE'
                        ELSE 'FALSE'
                    END AS lemburPernah,

                    -- ========================================
                    -- DATA USER
                    -- ========================================
                    e.Kode_Karyawan AS userId,
                    e.UserID_Absen,
                    f.nama_divisi AS Divisi,
                    f.nama_sub_divisi AS Sub_Divisi,
                    e.Nama AS name,

                    sh.Jam_Masuk,
                    sh.Jam_Keluar,
                    sh.ID_Shift,
                    sh.Nama_Shift,
                    sh.ECOFX AS Jam_selesai_absen,
                    sh.Jenis_Shift,
                    sh.StatusHari

                FROM Karyawan e

                INNER JOIN HRIS_Karyawan_Team h
                    ON h.Kode_Karyawan = e.Kode_Karyawan

                INNER JOIN View_Divisi_Sub_Divisi f
                    ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI

                INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g
                    ON e.ID_Level_Jabatan = g.ID_Level_Jabatan

                OUTER APPLY (
                    SELECT TOP 1 *
                    FROM dbo.fn_GetShiftLengkap_Range(
                        :kode_perusahaan,
                        e.Kode_Karyawan,
                        :tanggal,
                        :tanggal
                    )
                ) sh

                WHERE e.Kode_Perusahaan = :kode_perusahaan
                AND e.Aktif = 'Y'
                AND g.ID_Level IN (
                    SELECT CAST(value AS INT)
                    FROM STRING_SPLIT(:whitelistLevel, ',')
                )
                AND h.Kode_Karyawan_Team = :kode_team

                ORDER BY JamComparison DESC
                ";

            $result = DB::select($query, [
                'kode_perusahaan' => $Kode_Perusahaan,
                'tanggal' => $Tanggal,
                'datetime' => $DateAndtime,
                'kode_team' => $karyawan,
                'whitelistLevel' => implode(',', $whitelistLevel),
            ]);

            // dd($result);
            if (empty($result)) {
                return response()->json(
                    [
                        'status' => 404,
                        'message' => 'Data tidak ditemukan',
                    ],
                    404,
                );
            }

            $resultCollect = collect($result);
            $hashResult = $resultCollect->map(function ($item) {
                if (isset($item->userId)) {
                    $item->userId = Crypt::encryptString($item->userId);
                }

                return $item;
            });

            return response()->json([
                'status' => 200,
                'data' => $hashResult,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data userActiveAll: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'error' => 'Terjadi error saat mengambil data userActiveAll',
                ],
                404,
            );
        }
    }

    public function submitAdmin(Request $request)
    {
        // dd($request->params['dataUsers']);
        $format_tanggal_sekarang = date('m').'-'.date('y');
        // dd($format_tanggal_sekarang);

        try {
            if ($request->params['dataUsers']) {
                $userData = $request->params['dataUsers'];

                // dd($no_faktur);
                DB::beginTransaction();

                $userSPV = Auth()->user()->karyawan->Kode_Karyawan;
                $namaSPV = Auth()->user()->karyawan->Nama;
                // dd($userSPV);
                $Kode_Perusahaan = '001';
                $Tanggal_Sekarang = date('Y-m-d');
                $Jam_Sekarang = date('H:i:s');

                // no Transaksi via COUNTER ATOMIK (bebas race duplikat + tanpa scan
                // non-sargable). Self-seeding dari MAX existing saat prefix pertama dipakai.
                $prefix = 'LM'.$format_tanggal_sekarang.'-';
                $no_faktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                    $prefix,
                    fn () => DB::table('Transaksi_Lembur')
                        ->where('No_Transaksi', 'like', $prefix.'%')
                        ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
                );

                // Lembur::insert([
                //     'Kode_Perusahaan' => $Kode_Perusahaan,
                //     'No_Transaksi' => $no_faktur,
                //     'Tanggal' => $Tanggal_Sekarang,
                //     'Jam' => $Jam_Sekarang,
                //     'UserID' => $userSPV
                // ]);

                foreach ($userData as $user) {
                    //
                    $jenis = 'LEMBUR';
                    $Tanggal_lembur_saja = date('Y-m-d H:i:s', strtotime($user['tanggal']));
                    $Kode_Karyawan = Crypt::decryptString($user['id']);
                    // dd($Kode_Karyawan);

                    if (strtotime($user['waktu']) <= strtotime($user['Jam_MasukShift'])) {
                        $tanggalKeluarLembur = date(
                            'Y-m-d H:i:s',
                            strtotime('+1 day', strtotime($user['tanggal'].' '.$user['waktu'])),
                        );
                    } else {
                        $tanggalKeluarLembur = date('Y-m-d H:i:s', strtotime($user['tanggal'].' '.$user['waktu']));
                    }

                    if (strtotime($user['masuk']) <= strtotime($user['Jam_MasukShift'])) {
                        $tanggalMasukLembur = date(
                            'Y-m-d H:i:s',
                            strtotime('+1 day', strtotime($user['tanggal'].' '.$user['masuk'])),
                        );
                    } else {
                        $tanggalMasukLembur = date('Y-m-d H:i:s', strtotime($user['tanggal'].' '.$user['masuk']));
                    }

                    // dd($user['waktu'], $user['masuk'], $user['Jam_MasukShift']);
                    // dd($tanggalMasukLembur.' - ' .$tanggalKeluarLembur);
                    // dd($tanggalMasukLembur.' - ' .$tanggalKeluarLembur);
                    $alasan = str_replace(["\n", "\t"], ' ', $user['reason']);
                    /* $userInsert dan $userInsertNoHp di bawah tidak pernah dibaca di
                       metode ini -- dua kueri Karyawan per baris lembur yang hasilnya
                       dibuang. Pemakainya ada di submit(), bukan di sini. */
                    $dataDet = LemburDetail::insertGetId([
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'No_Transaksi' => $no_faktur,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Jenis' => $jenis,
                        'Tanggal_Lembur_Dari' => $tanggalMasukLembur,
                        'Tanggal_Lembur_Sampai' => $tanggalKeluarLembur,
                        'Alasan' => $alasan,
                    ]);

                    // dd($dataDet);
                    $urutDetail = $dataDet;

                    $finalInsert = LemburDet::create([
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'No_Transaksi' => $no_faktur,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Urut_Detail' => $urutDetail,
                        'Tanggal_Lembur' => $Tanggal_lembur_saja,
                    ]);

                }
                DB::commit();
                $this->attendanceDirtyResolver->markFromTransaction(
                    $no_faktur,
                    'LEMBUR_CREATED_LEGACY',
                    'OVERTIME_LEGACY_SUBMIT_ADMIN',
                    1,
                );

                // Alert::success('Success', 'Lembur Berhasil Disimpan!');
                return response()->json([
                    'status' => 200,
                    'message' => 'Berhasil Menambahkan lembur!',
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('lemburLog')->error('Gagal simpan lembur: '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Failed to Save Lembur',
                    'error' => 'An error occurred while saving the cross review, Please Try Again'.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function submit(Request $request)
    {
        // dd($request->all());
        $format_tanggal_sekarang = date('m').'-'.date('y');
        // dd($format_tanggal_sekarang);

        try {
            if ($request->params['dataUsers']) {
                $userData = $request->params['dataUsers'];

                // dd($no_faktur);
                DB::beginTransaction();

                $userSPV = Auth()->user()->karyawan->Kode_Karyawan;
                $namaSPV = Auth()->user()->karyawan->Nama;
                // dd($userSPV);
                $Kode_Perusahaan = '001';
                $Tanggal_Sekarang = date('Y-m-d');
                $Jam_Sekarang = date('H:i:s');

                // no Transaksi via COUNTER ATOMIK (bebas race duplikat + tanpa scan
                // non-sargable). Self-seeding dari MAX existing saat prefix pertama dipakai.
                $prefix = 'LM'.$format_tanggal_sekarang.'-';
                $no_faktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                    $prefix,
                    fn () => DB::table('Transaksi_Lembur')
                        ->where('No_Transaksi', 'like', $prefix.'%')
                        ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
                );

                Lembur::insert([
                    'Kode_Perusahaan' => $Kode_Perusahaan,
                    'No_Transaksi' => $no_faktur,
                    'Tanggal' => $Tanggal_Sekarang,
                    'Jam' => $Jam_Sekarang,
                    'UserID' => $userSPV,
                ]);

                // Preload Karyawan SEKALI (whereIn) sebelum loop → hilangkan N+1
                // (2 query/user) di dalam transaksi.
                $overtimeKaryawanMap = collect($userData)
                    ->map(function ($u) {
                        try {
                            return Crypt::decryptString($u['id']);
                        } catch (\Throwable) {
                            return null;
                        }
                    })
                    ->filter()
                    ->unique()
                    ->pipe(fn ($kodes) => Karyawan::whereIn('Kode_Karyawan', $kodes->all())->get()->keyBy('Kode_Karyawan'));

                foreach ($userData as $user) {
                    //
                    $jenis = 'LEMBUR';
                    $Tanggal_lembur_saja = date('Y-m-d H:i:s', strtotime($user['tanggal']));
                    $Kode_Karyawan = Crypt::decryptString($user['id']);
                    // dd($Kode_Karyawan);

                    if (strtotime($user['waktu']) <= strtotime($user['Jam_MasukShift'])) {
                        $tanggalKeluarLembur = date(
                            'Y-m-d H:i:s',
                            strtotime('+1 day', strtotime($user['tanggal'].' '.$user['waktu'])),
                        );
                    } else {
                        $tanggalKeluarLembur = date('Y-m-d H:i:s', strtotime($user['tanggal'].' '.$user['waktu']));
                    }

                    if (strtotime($user['masuk']) <= strtotime($user['Jam_MasukShift'])) {
                        $tanggalMasukLembur = date(
                            'Y-m-d H:i:s',
                            strtotime('+1 day', strtotime($user['tanggal'].' '.$user['masuk'])),
                        );
                    } else {
                        $tanggalMasukLembur = date('Y-m-d H:i:s', strtotime($user['tanggal'].' '.$user['masuk']));
                    }

                    // dd($tanggalMasukLembur.' - ' .$tanggalKeluarLembur);
                    $alasan = str_replace(["\n", "\t"], ' ', $user['reason']);
                    $userInsert = $overtimeKaryawanMap->get($Kode_Karyawan);
                    // dd($userInsert->No_Hp);
                    $dataDet = LemburDetail::insertGetId([
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'No_Transaksi' => $no_faktur,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Jenis' => $jenis,
                        'Tanggal_Lembur_Dari' => $tanggalMasukLembur,
                        'Tanggal_Lembur_Sampai' => $tanggalKeluarLembur,
                        'Alasan' => $alasan,
                    ]);

                    // dd($dataDet);
                    $urutDetail = $dataDet;

                    $finalInsert = LemburDet::create([
                        'Kode_Perusahaan' => $Kode_Perusahaan,
                        'No_Transaksi' => $no_faktur,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Urut_Detail' => $urutDetail,
                        'Tanggal_Lembur' => $Tanggal_lembur_saja,
                    ]);

                    $userInsertNoHp = $userInsert?->HP ?? null;
                    if ($userInsert && $finalInsert && $userInsertNoHp && $userInsertNoHp != '-') {
                        $userInsertNama = $userInsert->Nama;

                        $tangalMasukUser = date('H:i d M Y', strtotime($tanggalMasukLembur));
                        $tangalKeluarUser = date('H:i d M Y', strtotime($tanggalKeluarLembur));
                        // whatsapp message to user
                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $userInsertNoHp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'notif_lembur',
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
                                                'text' => $userInsertNama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $no_faktur,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $this->formatTanggalWaktu(
                                                    $tangalMasukUser,
                                                    $tangalKeluarUser,
                                                ),
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $alasan,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ];

                        // Kirim WA ditunda sampai transaksi commit agar HTTP (~10-20 dtk)
                        /* Diantrekan lewat pintu terpusat, bukan dikirim di dalam request.
                         * afterCommit sudah menyelamatkan lock, tetapi HTTP-nya tetap
                         * dijalankan satu per satu DI DALAM perulangan karyawan: N karyawan
                         * berarti N x (10-20 detik) sebelum respons kembali. SendWhatsappJob
                         * membawa afterCommit-nya sendiri, jadi jaminan "tidak ada WA untuk
                         * transaksi yang rollback" tetap berlaku. */
                        WhatsappLogHelper::sendAndLog(
                            $Kode_Karyawan ?? ($userInsert->Kode_Karyawan ?? null),
                            $userInsertNama ?? ($userInsert->Nama ?? null),
                            $userInsertNoHp ?? ($userInsert->HP ?? null),
                            $no_faktur ?? null,
                            $pesan,
                        );
                    }
                }
                DB::commit();
                $this->attendanceDirtyResolver->markFromTransaction(
                    $no_faktur,
                    'LEMBUR_CREATED_LEGACY',
                    'OVERTIME_LEGACY_SUBMIT',
                    1,
                );

                // Alert::success('Success', 'Lembur Berhasil Disimpan!');
                return response()->json([
                    'status' => 200,
                    'message' => 'Berhasil Menambahkan lembur!',
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('lemburLog')->error('Gagal simpan lembur: '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Failed to Save Lembur',
                    'error' => 'An error occurred while saving the cross review, Please Try Again'.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function formatTanggalWaktu($startDateTime, $endDateTime)
    {
        Carbon::setLocale('id');

        $start = Carbon::parse($startDateTime);
        $end = Carbon::parse($endDateTime);

        if ($start->isSameDay($end)) {
            // Kalau masih di hari yang sama
            return $start->translatedFormat('d M Y').' '.$start->format('H:i').' - '.$end->format('H:i');
        } else {
            // Kalau beda hari
            return $start->translatedFormat('d M Y H:i').' - '.$end->translatedFormat('d M Y H:i');
        }
    }

    public function destroy(Request $request)
    {
        $admin = ($request['params']['admin'] ?? 'false') == 'true' ? true : false;

        // dd($admin);
        $Authen = User::where('Id_Users', Auth()->user()->Id_Users)->exists(); // true
        if (! $Authen) {
            return response()->json(
                [
                    'message' => 'Failed Destroy Item',
                    'error' => 'Pls Try again',
                ],
                500,
            );
        }

        try {
            DB::beginTransaction();

            $lembur = Lembur::where('No_Transaksi', $request['params']['No_Transaksi'])->first();

            if (! $lembur) {
                return response()->json(
                    [
                        'status' => 404,
                        'message' => 'Data tidak ditemukan',
                    ],
                    404,
                );
            }

            // Estimasi akhir lembur = MAX(Tanggal_Lembur_Sampai) antar semua detail.
            // Leader hanya diblok bila now sudah lewat +CANCEL_GRACE_HOURS jam dari akhir
            // itu; admin melewati batas ini (kapan pun).
            $endMax = LemburDetail::where('No_Transaksi', $lembur->No_Transaksi)->max('Tanggal_Lembur_Sampai');
            if (! $admin && $endMax && Carbon::parse($endMax)->addHours(self::CANCEL_GRACE_HOURS)->lt(now())) {
                DB::rollBack();

                return response()->json(
                    [
                        'status' => 400,
                        'message' => 'Transaksi sudah lewat lebih dari '.self::CANCEL_GRACE_HOURS.' jam dari estimasi akhir lembur, tidak bisa dibatalkan!',
                    ],
                    400,
                );
            }

            // Update status + alasan pembatalan
            $alasanPembatalan = trim((string) ($request['params']['Alasan_Pembatalan'] ?? ''));
            $updateData = ['Status' => 'Y'];
            if ($alasanPembatalan !== '') {
                $updateData['Alasan_Pembatalan'] = mb_substr($alasanPembatalan, 0, 100);
                $updateData['Updated_By'] = Auth::user()->Id_Users;
                $updateData['Updated_At'] = date('Y-m-d H:i:s');
            }
            Lembur::where('No_Transaksi', $request['params']['No_Transaksi'])->update($updateData);

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $request['params']['No_Transaksi'],
                'LEMBUR_CANCELLED_LEGACY',
                'OVERTIME_LEGACY_DESTROY',
                1,
            );

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menghapus data!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('lemburLog')->error('Gagal menghapus Item destroy '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Terjadi kesalahan menghapus data destroy',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // public function destroyUser(Request $request){
    //     // dd($request['params']['urut_Oto']);
    //     try{
    //         DB::BeginTransaction();
    //         $detail = LemburDetail::where('Urut_Oto', $request['params']['urut_Oto'])->first();
    //         if(!$detail){
    //             DB::rollBack();
    //             return response()->json([
    //             'message' => 'Failed Destroy Item',
    //             'error' => 'Data tidak ditemukan'
    //             ],404);
    //         }

    //         $detail->delete();
    //         LemburDet::where('Urut_Detail', $request['params']['urut_Oto'])->delete();

    //         $cekIsiLemburDetail = LemburDetail::where('No_Transaksi', $detail->No_Transaksi)->count();
    //         $cekIsiLemburDet = LemburDet::where('No_Transaksi', $detail->No_Transaksi)->count();
    //         if($cekIsiLemburDetail == 0 || $cekIsiLemburDet == 0){
    //             Lembur::where('No_Transaksi', $detail->No_Transaksi)->update(['Status' => 'Y']);
    //         }

    //         //  LemburDet::where('No_Transaksi', $request['params']['No_Transaksi'])->update(['Status' => 'Y']);
    //         //  LemburDetail::where('No_Transaksi', $request['params']['No_Transaksi'])->update(['Status' => 'Y']);
    //         DB::commit();
    //         Alert::success('Success', 'User Berhasil DiHapus dari Transaksi!');
    //         return response()->json([
    //             'status' => 200,
    //             'message' => "Berhasi Menghapus data!"
    //         ]);

    //     }catch(\throwable $e){
    //         DB::rollBack();
    //         Log::channel('lemburLog')->error('Gagal menghapus Item destroy'. $e->getMessage());
    //         return response()->json([
    //             'message' => 'Terjadi kesalahan menghapus data destroy',
    //             'error' => 'Pls Try again'
    //         ],500);
    //     }

    //     // $item =  $re
    // }

    public function destroyUser(Request $request)
    {
        $admin = ($request['params']['admin'] ?? 'false') == 'true' ? true : false;

        try {
            DB::beginTransaction();

            $detail = LemburDetail::where('Urut_Oto', $request['params']['urut_Oto'])->first();

            if (! $detail) {
                DB::rollBack();

                return response()->json(
                    [
                        'message' => 'Failed Destroy Item',
                        'error' => 'Data tidak ditemukan',
                    ],
                    404,
                );
            }

            // ✅ Ambil transaksi induk dari tabel Lembur
            // $lembur = Lembur::where('No_Transaksi', $detail->No_Transaksi)->first();

            // if (!$lembur) {
            //     DB::rollBack();
            //     return response()->json([
            //         'status'  => 404,
            //         'message' => 'Data lembur induk tidak ditemukan'
            //     ], 404);
            // }

            // Leader diblok hanya bila now sudah lewat +CANCEL_GRACE_HOURS jam dari akhir
            // lembur detail ini; admin melewati batas ini (kapan pun).
            if (! $admin && $detail->Tanggal_Lembur_Sampai
                && Carbon::parse($detail->Tanggal_Lembur_Sampai)->addHours(self::CANCEL_GRACE_HOURS)->lt(now())) {
                DB::rollBack();

                return response()->json(
                    [
                        'status' => 400,
                        'message' => 'Transaksi sudah lewat lebih dari '.self::CANCEL_GRACE_HOURS.' jam dari estimasi akhir lembur, tidak bisa dibatalkan!',
                    ],
                    400,
                );
            }

            // // ✅ Cek tanggal lembur (berpaku pada tabel Lembur)
            // if ($lembur->Tanggal < now()->startOfDay()) {
            //     DB::rollBack();
            //     return response()->json([
            //         'status'  => 400,
            //         'message' => 'Transaksi sudah lewat dari hari ini, tidak bisa dihapus!'
            //     ], 400);
            // }

            // ✅ Hapus detail & det (langsung delete, tidak update status)
            $detail->delete();
            LemburDet::where('Urut_Detail', $request['params']['urut_Oto'])->delete();

            // ✅ Kalau detail/det habis, update status Lembur
            $cekIsiLemburDetail = LemburDetail::where('No_Transaksi', $detail->No_Transaksi)->count();
            $cekIsiLemburDet = LemburDet::where('No_Transaksi', $detail->No_Transaksi)->count();

            if ($cekIsiLemburDetail == 0 || $cekIsiLemburDet == 0) {
                Lembur::where('No_Transaksi', $detail->No_Transaksi)->update(['Status' => 'Y']);
            }

            DB::commit();
            $this->attendanceDirtyResolver->markByKodeKaryawan(
                $detail->Kode_Karyawan,
                $detail->Tanggal_Lembur_Dari,
                $detail->Tanggal_Lembur_Sampai,
                'LEMBUR_DETAIL_DELETED_LEGACY',
                'OVERTIME_LEGACY_DESTROY_USER',
                1,
            );

            // Alert::success('Success', 'User Berhasil Dihapus dari Transaksi!');
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil Menghapus data!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('lemburLog')->error('Gagal menghapus Item destroy '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Terjadi kesalahan menghapus data destroy',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function getDetails(Request $request)
    {
        try {
            $No_Transaksi = $request->No_Transaksi;

            // $query ="
            // SELECT
            // COALESCE(
            //     ShiftSementara.CHECKOUT_Status,
            //     ShiftPerKaryawan.CHECKOUT_Status,
            //     CASE
            //     WHEN CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN 'EXPIRED'
            //     ELSE 'IN PROGRESS'
            //     END
            // ) AS CHECKOUT,
            // d.Nama AS name,
            // c.Urut_Oto,
            // d.UserID_Absen AS userId,
            // c.Tanggal_Lembur_Dari AS start_time,
            // c.Tanggal_Lembur_Sampai AS end_time,
            // c.Alasan AS reason
            // FROM
            // Transaksi_Lembur_Detail c
            // INNER JOIN Karyawan d ON c.Kode_Karyawan = d.Kode_Karyawan
            // OUTER APPLY (
            //     SELECT
            //     TOP (1) CASE
            //     -- If CHECKTIME is not NULL, status 'COMPLETED' and display checkout time
            //         WHEN z.CHECKTIME IS NOT NULL THEN CONVERT(VARCHAR, z.CHECKTIME, 120)
            //         -- If CHECKTIME is NULL and Tanggal_Lembur_Sampai is already past (EXPIRED)
            //         WHEN z.CHECKTIME IS NULL
            //         AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN 'EXPIRED'
            //         -- If CHECKTIME is NULL and Tanggal_Lembur_Sampai is still valid (IN PROGRESS)
            //         WHEN z.CHECKTIME IS NULL
            //         AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= CAST(GETDATE() AS DATETIME) THEN 'IN PROGRESS'
            //         ELSE 'UNKNOWN'
            //     END AS CHECKOUT_Status
            //     FROM
            //     HRIS_Shift_Kerja v
            //     INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
            //     INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
            //     INNER JOIN HRIS_Shift_Sementara y ON v.ID_Shift = y.ID_Shift
            //     INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
            //     WHERE
            //     y.Kode_Perusahaan = d.Kode_Perusahaan
            //     AND y.Kode_Karyawan = d.Kode_Karyawan
            //     AND z.CHECKTIME BETWEEN CONVERT(
            //         DATETIME,
            //         CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar
            //     ) AND CONVERT(
            //         DATETIME,
            //         CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar
            //     )
            //     ORDER BY
            //     y.Tanggal DESC,
            //     y.Urut DESC
            // ) AS ShiftSementara
            // OUTER APPLY (
            //     SELECT
            //     TOP (1) CASE
            //     -- If CHECKTIME is not NULL, status 'COMPLETED' and display checkout time
            //         WHEN z.CHECKTIME IS NOT NULL THEN CONVERT(VARCHAR, z.CHECKTIME, 120)
            //         -- If CHECKTIME is NULL and Tanggal_Lembur_Sampai is already past (EXPIRED)
            //         WHEN z.CHECKTIME IS NULL
            //         AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < CAST(GETDATE() AS DATETIME) THEN 'EXPIRED'
            //         -- If CHECKTIME is NULL and Tanggal_Lembur_Sampai is still valid (IN PROGRESS)
            //         WHEN z.CHECKTIME IS NULL
            //         AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= CAST(GETDATE() AS DATETIME) THEN 'IN PROGRESS'
            //         ELSE 'UNKNOWN'
            //     END AS CHECKOUT_Status
            //     FROM
            //     HRIS_Shift_Kerja v
            //     INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
            //     INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
            //     INNER JOIN HRIS_Shift_Per_Karyawan y ON v.ID_Shift = y.ID_Shift
            //     INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
            //     WHERE
            //     y.Kode_Perusahaan = d.Kode_Perusahaan
            //     AND y.Kode_Karyawan = d.Kode_Karyawan
            //     AND z.CHECKTIME BETWEEN CONVERT(
            //         DATETIME,
            //         CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar
            //     ) AND CONVERT(
            //         DATETIME,
            //         CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar
            //     )
            //     ORDER BY
            //     y.Periode DESC,
            //     y.Urut DESC
            // ) AS ShiftPerKaryawan
            // WHERE
            // c.No_Transaksi = ?
            // ORDER BY
            // c.Tanggal_Lembur_Dari DESC;

            // ";
            $query = '
                  SELECT
                    d.Nama AS name,
                    c.Urut_Oto,
                    d.UserID_Absen AS userId,
                    c.Tanggal_Lembur_Dari AS start_time,
                    c.Tanggal_Lembur_Sampai AS end_time,
                    e.Tanggal_Lembur,
                    c.Alasan AS reason
                FROM
                    Transaksi_Lembur_Detail c
                    INNER JOIN Transaksi_Lembur_Det e ON e.No_Transaksi = c.No_Transaksi
                    INNER JOIN Karyawan d ON c.Kode_Karyawan = d.Kode_Karyawan
                WHERE
                    c.No_Transaksi = ?
                Group BY 
                   d.Nama ,
                    c.Urut_Oto,
                    e.Tanggal_Lembur,
                    d.UserID_Absen ,
                    c.Tanggal_Lembur_Dari ,
                    c.Tanggal_Lembur_Sampai,
                    c.Alasan
                ORDER BY
                    c.Tanggal_Lembur_Dari DESC;
            ';

            // $query = "
            //     SELECT
            //         d.Nama AS name,
            //         c.Urut_Oto,
            //         d.UserID_Absen AS userId,
            //         c.Tanggal_Lembur_Dari AS start_time,
            //         c.Tanggal_Lembur_Sampai AS end_time,
            //         c.Alasan AS reason
            //     FROM
            //         Transaksi_Lembur_Detail c
            //         INNER JOIN Karyawan d ON c.Kode_Karyawan = d.Kode_Karyawan
            //     WHERE
            //         c.No_Transaksi = ?
            //     ORDER BY
            //         c.Tanggal_Lembur_Dari DESC;
            // ";

            $resultData = DB::select($query, [$No_Transaksi]);

            // C3: hilangkan N+1 SP absen. Sebelumnya SP getAttendance dipanggil SEKALI
            // PER baris detail. Kini dipanggil SEKALI untuk semua (userId, tanggal) lalu
            // dipetakan per baris. Tetap 'SYNC' → kesegaran & hasil identik.
            $rows = collect($resultData);
            $sourceReuqest = 'overtime-controller-getDetails';
            $requestedBy = Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest;

            $tanggalList = $rows
                ->map(fn ($e) => Carbon::parse($e->Tanggal_Lembur)->format('Y-m-d'))
                ->filter()
                ->values();
            $userIdList = $rows->pluck('userId')->filter()->unique()->values();

            $absenMap = [];
            if ($userIdList->isNotEmpty() && $tanggalList->isNotEmpty()) {
                $response = $this->attendanceService->getAttendance(
                    kodePerusahaan: '001',
                    tglAwal: $tanggalList->min(),
                    tglAkhir: $tanggalList->max(),
                    userIdList: $userIdList->implode(','),
                    executionMode: 'SYNC',
                    requestSource: $sourceReuqest,
                    requestedBy: $requestedBy,
                    priority: 1,
                );

                if (! $response['success']) {
                    throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
                }

                foreach ($response['data'] ?? [] as $absen) {
                    $uid = (string) ($absen->UserID ?? $absen->UserId ?? '');
                    $tgl = ! empty($absen->Tanggal) ? Carbon::parse($absen->Tanggal)->format('Y-m-d') : null;
                    if ($uid !== '' && $tgl !== null) {
                        $absenMap[$uid][$tgl] = $absen;
                    }
                }
            }

            $finalData = $rows->map(function ($e) use ($absenMap) {
                $tanggal = Carbon::parse($e->Tanggal_Lembur)->format('Y-m-d');
                $absen = $absenMap[(string) $e->userId][$tanggal] ?? null;
                $checkOut = $absen->CheckOut ?? null;

                if ($checkOut) {
                    $e->CHECKOUT = Carbon::parse($checkOut)->format('d F Y H:i');
                } else {
                    $now = Carbon::now();
                    $end = Carbon::parse($e->end_time);

                    $e->CHECKOUT = $end->lt($now) ? 'EXPIRED' : 'IN PROGRESS';
                }

                return $e;
            });

            // dd($finalData);

            // dd($resultData);
            return response()->json([
                'status' => 200,
                'data' => $finalData,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal Mengambil data getDetails'.$e->getMessage());

            return response()->json([
                'status' => 404,
                'error' => 'Tidak bisa mengambil data getDetails',
            ]);
        }
    }

    public function getExportAdmin(Request $request)
    {
        try {
            $start = $request->input('start');
            $end = $request->input('end');
            $karyawanAuth = Auth()->user()->karyawan->Kode_Karyawan;

            $queryMaster = '
                SELECT
                    a.No_Transaksi, a.Kode_Perusahaan, a.Tanggal, a.Jam, b.Nama
                FROM
                    Transaksi_Lembur a, Karyawan b, View_Divisi_Sub_Divisi c
                WHERE
                    a.UserID = b.Kode_Karyawan
                    AND b.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI

                    AND a.status IS NULL
                    AND CAST(a.Tanggal AS DATE) BETWEEN ? AND ?
                ORDER BY
                    a.Tanggal DESC, a.Jam DESC
            ';
            $transaksiMaster = DB::select($queryMaster, [$start, $end]);
            if (empty($transaksiMaster)) {
                return response()->json([
                    'status' => 200,
                    'data' => [],
                ]);
            }

            $nomorTransaksi = [];
            foreach ($transaksiMaster as $tran) {
                $nomorTransaksi[] = $tran->No_Transaksi;
            }

            $placeholders = implode(',', array_fill(0, count($nomorTransaksi), '?'));

            // 4. Query Kedua: Ambil SEMUA detail dalam satu kali jalan
            // Query ini mengambil semua detail yang No_Transaksi-nya ada di dalam daftar $nomorTransaksi
            $queryDetail = "
                SELECT
                    c.No_Transaksi,
                    ISNULL(
                        (
                            SELECT TOP (1) CASE
                                WHEN z.CHECKTIME IS NOT NULL THEN CONVERT(VARCHAR, z.CHECKTIME, 120)
                                WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < GETDATE() THEN 'EXPIRED'
                                WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= GETDATE() THEN 'IN PROGRESS'
                                ELSE 'UNKNOWN'
                            END
                            FROM HRIS_Shift_Kerja v
                            INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
                            INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
                            INNER JOIN HRIS_Shift_Per_Karyawan y ON v.ID_Shift = y.ID_Shift
                            INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
                            WHERE y.Kode_Perusahaan = d.Kode_Perusahaan
                                AND y.Kode_Karyawan = d.Kode_Karyawan
                                AND z.CHECKTIME BETWEEN
                                    CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar) AND
                                    CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar)
                        ),
                        CASE
                            WHEN CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < GETDATE() THEN 'EXPIRED'
                            ELSE 'IN PROGRESS'
                        END
                    ) AS CHECKOUT,
                    d.Nama AS name, c.Urut_Oto, d.UserID_Absen As userId,
                    c.Tanggal_Lembur_Dari AS start_time, c.Tanggal_Lembur_Sampai AS end_time, c.Alasan AS reason, e.nama_divisi AS dapartment , e.nama_sub_divisi AS divisi
                FROM
                    Transaksi_Lembur_Detail c
                    INNER JOIN Karyawan d ON c.Kode_Karyawan = d.Kode_Karyawan
                    INNER JOIN View_Divisi_Sub_Divisi e ON e.ID_DIVISI_SUB_DIVISI = d.ID_Divisi_Sub_Divisi
                WHERE
                    c.No_Transaksi IN ({$placeholders})
                ORDER BY
                    c.Tanggal_Lembur_Dari DESC
            ";
            $allDetails = DB::select($queryDetail, $nomorTransaksi);

            $detailsGrouped = [];
            foreach ($allDetails as $detail) {
                $detailsGrouped[$detail->No_Transaksi][] = $detail;
            }

            $finalData = [];
            foreach ($transaksiMaster as $master) {
                $currentDate = date('Y-m-d', strtotime($master->Tanggal));
                $currentNoTrans = $master->No_Transaksi;

                $finalData[$currentDate][$currentNoTrans] = [
                    'No_Transaksi' => $master->No_Transaksi,
                    'Kode_Perusahaan' => $master->Kode_Perusahaan,
                    'Tanggal' => $master->Tanggal,
                    'Jam' => $master->Jam,
                    'Nama' => $master->Nama,
                    // Jika tidak ada detail , berikan array kosong.
                    'details' => $detailsGrouped[$currentNoTrans] ?? [],
                ];
            }

            // dd($finalData);

            return response()->json([
                'status' => 200,
                'data' => $finalData,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data exportAdmin: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Terjadi error saat mengambil data exportAdmin',
                ],
                500,
            );
        }
    }

    public function getExport(Request $request)
    {
        try {
            $start = $request->input('start');
            $end = $request->input('end');
            $karyawanAuth = Auth()->user()->karyawan->Kode_Karyawan;

            $queryMaster = '
                SELECT
                    a.No_Transaksi, a.Kode_Perusahaan, a.Tanggal, a.Jam, b.Nama
                FROM
                    Transaksi_Lembur a, Karyawan b, View_Divisi_Sub_Divisi c
                WHERE
                    a.UserID = b.Kode_Karyawan
                    AND b.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                    AND a.UserID = ?
                    AND a.status IS NULL
                    AND CAST(a.Tanggal AS DATE) BETWEEN ? AND ?
                ORDER BY
                    a.Tanggal DESC, a.Jam DESC
            ';
            $transaksiMaster = DB::select($queryMaster, [$karyawanAuth, $start, $end]);
            if (empty($transaksiMaster)) {
                return response()->json([
                    'status' => 200,
                    'data' => [],
                ]);
            }

            $nomorTransaksi = [];
            foreach ($transaksiMaster as $tran) {
                $nomorTransaksi[] = $tran->No_Transaksi;
            }

            $placeholders = implode(',', array_fill(0, count($nomorTransaksi), '?'));

            // 4. Query Kedua: Ambil SEMUA detail dalam satu kali jalan
            // Query ini mengambil semua detail yang No_Transaksi-nya ada di dalam daftar $nomorTransaksi
            $queryDetail = "
                SELECT
                    c.No_Transaksi,
                    ISNULL(
                        (
                            SELECT TOP (1) CASE
                                WHEN z.CHECKTIME IS NOT NULL THEN CONVERT(VARCHAR, z.CHECKTIME, 120)
                                WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < GETDATE() THEN 'EXPIRED'
                                WHEN z.CHECKTIME IS NULL AND CAST(c.Tanggal_Lembur_Sampai AS DATETIME) >= GETDATE() THEN 'IN PROGRESS'
                                ELSE 'UNKNOWN'
                            END
                            FROM HRIS_Shift_Kerja v
                            INNER JOIN HRIS_Shift_Kerja_Detail w ON v.ID_Shift = w.ID_Shift
                            INNER JOIN HRIS_Waktu_Kerja x ON w.ID_Waktu_Kerja = x.ID_Waktu_Kerja
                            INNER JOIN HRIS_Shift_Per_Karyawan y ON v.ID_Shift = y.ID_Shift
                            INNER JOIN CHECKINOUT z ON z.USERID = d.UserID_Absen
                            WHERE y.Kode_Perusahaan = d.Kode_Perusahaan
                                AND y.Kode_Karyawan = d.Kode_Karyawan
                                AND z.CHECKTIME BETWEEN
                                    CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Dari, 120) + ' ' + x.Jam_Mulai_Absen_Keluar) AND
                                    CONVERT(DATETIME, CONVERT(VARCHAR(10), c.Tanggal_Lembur_Sampai, 120) + ' ' + x.Jam_Selesai_Absen_Keluar)
                        ),
                        CASE
                            WHEN CAST(c.Tanggal_Lembur_Sampai AS DATETIME) < GETDATE() THEN 'EXPIRED'
                            ELSE 'IN PROGRESS'
                        END
                    ) AS CHECKOUT,
                    d.Nama AS name, c.Urut_Oto, d.UserID_Absen As userId,
                    c.Tanggal_Lembur_Dari AS start_time, c.Tanggal_Lembur_Sampai AS end_time, c.Alasan AS reason
                FROM
                    Transaksi_Lembur_Detail c
                    INNER JOIN Karyawan d ON c.Kode_Karyawan = d.Kode_Karyawan
                WHERE
                    c.No_Transaksi IN ({$placeholders})
                ORDER BY
                    c.Tanggal_Lembur_Dari DESC
            ";
            $allDetails = DB::select($queryDetail, $nomorTransaksi);

            $detailsGrouped = [];
            foreach ($allDetails as $detail) {
                $detailsGrouped[$detail->No_Transaksi][] = $detail;
            }

            $finalData = [];
            foreach ($transaksiMaster as $master) {
                $currentDate = date('Y-m-d', strtotime($master->Tanggal));
                $currentNoTrans = $master->No_Transaksi;

                $finalData[$currentDate][$currentNoTrans] = [
                    'No_Transaksi' => $master->No_Transaksi,
                    'Kode_Perusahaan' => $master->Kode_Perusahaan,
                    'Tanggal' => $master->Tanggal,
                    'Jam' => $master->Jam,
                    'Nama' => $master->Nama,
                    // Jika tidak ada detail , berikan array kosong.
                    'details' => $detailsGrouped[$currentNoTrans] ?? [],
                ];
            }

            // dd($finalData);

            return response()->json([
                'status' => 200,
                'data' => $finalData,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->error('Gagal megambil data getExport: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Terjadi error saat mengambil data getExport',
                ],
                500,
            );
        }
    }
}
