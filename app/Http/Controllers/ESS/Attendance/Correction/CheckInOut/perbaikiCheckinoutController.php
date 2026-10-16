<?php

namespace App\Http\Controllers\ESS\Attendance\Correction\CheckInOut;

use App\Helpers\FormatTanggalHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class perbaikiCheckinoutController extends Controller
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
        $data = DB::table('Karyawan as a')
            ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
            ->select(
                'a.Kode_Karyawan',
                'a.Nama',
                'a.UserID_Absen',
                'b.ID_Divisi',
                'b.nama_divisi',
                'b.nama_sub_divisi',
                'c.nama_jabatan',
            )
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->get();
        $divisi = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_Divisi', 'nama_divisi')
            ->groupBy('ID_Divisi', 'nama_divisi')
            ->get();

        $tanggal = now()->format('d M Y');
        $Nama = Auth::user()->karyawan->Nama;

        // dd($tanggal);
        return inertia('ESS/Attendance/Correction/CheckInOut/perbaikanCheckInOut', [
            'namaKaryawan' => $Nama,
            'Tanggal' => $tanggal,
            'dataKaryawan' => $data,
            'allDivisi' => $divisi,
        ]);
    }

    public function getCheckInOut(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'Kode_Karyawan' => 'required',
        ]);

        $tanggalDipilih = Carbon::parse($validated['date']);

        // Buat array gabungan tanggal
        $gabunganTanggal = [
            $tanggalDipilih->copy()->subDay()->toDateString(),
            $tanggalDipilih->toDateString(),
            $tanggalDipilih->copy()->addDay()->toDateString(),
        ];
        // dd($validated);
        try {
            $result = DB::table('CHECKINOUT as a')
                ->join('Karyawan as b', 'a.USERID', '=', 'b.UserID_Absen')
                ->select('a.Checktime', 'a.USERID', 'a.Untuk_Tanggal', 'a.Hasil')
                ->where('b.Kode_Karyawan', $validated['Kode_Karyawan'])
                ->whereIn(DB::raw('CAST(a.CHECKTIME AS DATE)'), $gabunganTanggal)
                ->get();

            // dd($result);
            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::channel('CreateUserLog')->error('Error eksekusi attendance', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function attendance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|array',
            'employee_id.UserID_Absen' => 'required',
            'date' => 'required|array|size:2',
            'date.0' => 'required|date',
            'date.1' => 'required|date|after_or_equal:date.0',
        ]);

        try {
            $start = $validated['date'][0];
            $end = $validated['date'][1];
            $UserID_Absen = $validated['employee_id']['UserID_Absen'];

            $ambilTransaksi = DB::table('Transaksi_Perbaikan_Absensi_Detail')
                ->join(
                    'Transaksi_Perbaikan_Absensi',
                    'Transaksi_Perbaikan_Absensi_Detail.No_Transaksi',
                    '=',
                    'Transaksi_Perbaikan_Absensi.No_Transaksi',
                )
                ->join('Karyawan', 'Transaksi_Perbaikan_Absensi.UserID', '=', 'Karyawan.Userid_Web')
                ->select(
                    'Transaksi_Perbaikan_Absensi_Detail.*',
                    'Transaksi_Perbaikan_Absensi.Tanggal',
                    'Transaksi_Perbaikan_Absensi.Jam',
                    'Karyawan.Nama',
                )
                ->where('Transaksi_Perbaikan_Absensi_Detail.UserID_Absen', $validated['employee_id']['UserID_Absen'])
                ->whereBetween('Transaksi_Perbaikan_Absensi_Detail.Untuk_Tanggal', [
                    $validated['date'][0],
                    $validated['date'][1],
                ])
                ->get();

            // dd($ambilTransaksi);

            // $query = "EXEC HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, ?, ''";
            // $params = ['001', $start, $end, $UserID_Absen, ''];

            $sourceReuqest = 'perbaikan-checkinout-controller-attendance';
            $tglMulai = $start;
            $tglSelesai = $end;
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

            $dataAbsen = $response['data'] ?? [];

            $data = collect($dataAbsen)->map(function ($row) use ($ambilTransaksi) {
                $row->Status_Perbaikan = 'N';
                $row->Admin_Yang_Mengubah = null;
                $row->Tanggal_Perbaikan = null;
                foreach ($ambilTransaksi as $transaksi) {
                    if ($transaksi->Untuk_Tanggal == $row->Tanggal) {
                        if (
                            ($transaksi->Jenis == 'checkin' && $transaksi->WaktuAbsenBaru == $row->CheckIn) ||
                            ($transaksi->Jenis == 'checkout' && $transaksi->WaktuAbsenBaru == $row->CheckOut)
                        ) {
                            $row->Status_Perbaikan = 'Y';
                            $row->Admin_Yang_Mengubah = $transaksi->Nama;
                            $row->Tanggal_Perbaikan = FormatTanggalHelper::formatDateTransaksi(
                                $transaksi->Tanggal,
                                $transaksi->Jam,
                            );
                            break;
                        }
                    }
                }

                return [
                    'Nama' => $row->Nama,
                    'Tanggal' => $row->Tanggal,
                    'Kode_Karyawan' => $row->Kode_Karyawan,
                    'Nama_Shift' => $row->Nama_Shift,
                    'CheckIn' => $row->CheckIn,
                    'CheckOut' => $row->CheckOut,
                    'Status_Kehadiran' => $row->Status_Kehadiran,
                    'Status_Perbaikan' => $row->Status_Perbaikan,
                    'Admin_Yang_Mengubah' => $row->Admin_Yang_Mengubah,
                    'Tanggal_Perbaikan' => $row->Tanggal_Perbaikan,
                ];
            });

            // dd($data);
            return response()->json([
                'status' => 200,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::channel('CreateUserLog')->error('Error eksekusi attendance', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function submit(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate([
            'tanggalAbsensi' => 'required|date',
            'Kode_Karyawan' => 'required|string',

            'perubahan' => 'required|array',
            'perubahan.*.jenis' => 'required|in:checkin,checkout',
            'perubahan.*.sebelum' => 'nullable|date',
            'perubahan.*.setelah' => 'nullable|date',
        ]);
        // dd($validated);

        try {
            DB::Begintransaction();
            $useridWebInsert = Auth::user()->karyawan->UserID_Web;
            $tanggal = now()->format('Y-m-d');
            $jam = now()->format('H:i');
            $Kode_Karyawan = $validated['Kode_Karyawan'];
            $UserID_Absen = DB::table('Karyawan')
                ->select('UserID_Absen')
                ->where('Kode_Karyawan', $Kode_Karyawan)
                ->first()->UserID_Absen;

            $formatTanggalSekarang = Carbon::now()->format('m-y');
            $prefix = 'PC'.$formatTanggalSekarang.'-';
            $checkLastFaktur = DB::table('Transaksi_Perbaikan_Absensi')
                ->select(DB::raw('RIGHT(No_Transaksi, 4) AS angka'))
                ->where('No_Transaksi', 'like', $prefix.'%')
                ->orderByDesc(DB::raw('RIGHT(No_Transaksi, 4)'))
                ->first();

            $angkaTerakhir = $checkLastFaktur ? (int) $checkLastFaktur->angka + 1 : 1;
            $noFaktur = $prefix.sprintf('%04d', $angkaTerakhir);

            $perubahan = $validated['perubahan'];

            $insertInduk = DB::table('Transaksi_Perbaikan_Absensi')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noFaktur,
                'Tanggal' => $tanggal,
                'Jam' => $jam,
                'UserID' => $useridWebInsert,
            ]);

            foreach ($perubahan as $item) {
                // dd($item['jenis']);
                if ($item['sebelum']) {
                    $checktimeOriginal = DB::table('CHECKINOUT')
                        ->where('CHECKTIME', $item['sebelum'])
                        ->where('USERID', $UserID_Absen)
                        ->first();
                    if ($checktimeOriginal && ! $checktimeOriginal->Untuk_Tanggal) {
                        DB::table('CHECKINOUT')
                            ->where('CHECKTIME', $item['sebelum'])
                            ->where('USERID', $UserID_Absen)
                            ->update([
                                'Untuk_Tanggal' => null,
                                // 'Hasil' => 'Unused',
                                'Hasil' => null,
                                'Alasan' => null,
                            ]);
                    } else {
                        DB::table('CHECKINOUT')
                            ->where('CHECKTIME', $item['sebelum'])
                            ->where('USERID', $UserID_Absen)
                            ->update([
                                'Untuk_Tanggal' => null,
                                'Hasil' => null,
                                'Alasan' => null,
                            ]);
                    }
                }

                // if($item['setelah']){
                if ($item['jenis'] == 'checkin') {
                    $Hasil = 'In';
                } else {
                    $Hasil = 'Out';
                }

                $insertDetail = DB::table('Transaksi_Perbaikan_Absensi_Detail')->insert([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noFaktur,
                    'Kode_Karyawan' => $Kode_Karyawan,
                    'Jenis' => $item['jenis'],
                    'UserID_Absen' => $UserID_Absen,
                    'WaktuAbsenLama' => $item['sebelum'],
                    'WaktuAbsenBaru' => $item['setelah'],
                    'Untuk_Tanggal' => $validated['tanggalAbsensi'],
                ]);

                if ($insertInduk && $insertDetail) {
                    DB::table('CHECKINOUT')
                        ->where('USERID', $UserID_Absen)
                        ->where('CHECKTIME', $item['setelah'])
                        ->update([
                            'Untuk_Tanggal' => $validated['tanggalAbsensi'],
                            'Hasil' => $Hasil,
                        ]);
                }

                // }
            }

            // dd($data);
            DB::commit();
            $targets = [
                [
                    'kode_karyawan' => $Kode_Karyawan,
                    'tanggal' => $validated['tanggalAbsensi'],
                ],
            ];

            foreach ($perubahan as $item) {
                if (! empty($item['sebelum'])) {
                    $targets[] = [
                        'kode_karyawan' => $Kode_Karyawan,
                        'tanggal' => $item['sebelum'],
                    ];
                }

                if (! empty($item['setelah'])) {
                    $targets[] = [
                        'kode_karyawan' => $Kode_Karyawan,
                        'tanggal' => $item['setelah'],
                    ];
                }
            }

            $this->attendanceDirtyResolver->markMany($targets, 'CHECKINOUT_CORRECTED', 'PERBAIKI_CHECKINOUT', 1);

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil update absensi',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::channel('CreateUserLog')->error('Error eksekusi absensi', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan: '.$e->getMessage(),
                ],
                500,
            );
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun.

               Titik ini nyaris tak pernah terlihat: transaksinya dibuka dengan
               ejaan `DB::Begintransaction()` (B kapital, t kecil), sehingga tak
               satu pun pencarian peka-huruf menemukannya. PHP tidak peka huruf
               besar-kecil pada nama metode, jadi kodenya sah — yang buta adalah
               alat pencarinya. */
            DB::rollback();

            throw $e;
        }
    }
}
