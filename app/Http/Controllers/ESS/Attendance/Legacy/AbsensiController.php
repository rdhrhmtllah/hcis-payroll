<?php

namespace App\Http\Controllers\ESS\Attendance\Legacy;

use App\Helpers\ResponseHelper;
use App\Helpers\Whatsapp;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService as CutiBersamaService;
use App\Services\ESS\Attendance\Leave\Personal\IzinCancellationService;
use App\Services\ESS\Attendance\Leave\Shared\KalenderCutiBersama;
use App\Services\ESS\Attendance\Leave\Shared\KueriExportIzin;
use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\Governance\Approval\Shared\AturanKedaluwarsaApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RealRashid\SweetAlert\Facades\Alert;
use Vinkla\Hashids\Facades\Hashids;

class AbsensiController extends Controller
{
    protected ShiftRequestService $shiftRequestService;

    protected TransactionService $transactionService;

    protected CutiBersamaService $cutiBersamaService;

    protected AttendanceDirtyResolverService $attendanceDirtyResolver;

    public function __construct(
        ShiftRequestService $shiftRequestService,
        TransactionService $transactionService,
        CutiBersamaService $cutiBersamaService,
        AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->shiftRequestService = $shiftRequestService;
        $this->transactionService = $transactionService;
        $this->cutiBersamaService = $cutiBersamaService;
        $this->attendanceDirtyResolver = $attendanceDirtyResolver;
    }

    public function indexAbsensi()
    {
        try {
            $Kode_Perusahaan = '001';
            $user = Auth::user();
            $karyawan = Auth::user()->karyawan;
            $Tanggal = date('Y-m-d');
            // Filter ke karyawan yang login. Tanpa WHERE, query ini men-join SELURUH
            // tabel Karyawan × view lalu mengambil baris ACAK (divisi milik orang lain,
            // sekaligus scan tak perlu). Ambil hanya kolom yang dipakai.
            $KaryawanSelect = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('k.Kode_Karyawan', $karyawan->Kode_Karyawan)
                ->select('d.nama_divisi')
                ->first();

            $hashedUser = (object) [
                'Kode_Karyawan' => isset($user->Kode_Users) ? Crypt::encryptString($user->Kode_Users) : null,
                'Id_Users' => isset($user->Id_Users) ? Hashids::connection('custom')->encode($user->Id_Users) : null,
                'UserID_Absen' => isset($karyawan->UserID_Absen) ? Crypt::encryptString($karyawan->UserID_Absen) : null,
                'nama' => $karyawan->Nama ?? null,
                'email' => $user->email ?? null,
                'HP' => $karyawan->HP ?? null,
            ];

            // $contoh = DB::table('Transaksi_Absensi_Detail')->get();

            // $solveFile = collect($contoh)->map(function ($item){
            //     if(isset($item->filePath)){
            //         $item->filePath = Storage::disk('gcs')->temporaryUrl($item->filePath, now()->addMinutes(10)) ??  null;
            //     }
            //     return $item;
            // });
            $query = "Select * from N_HRIS_FN_GetStatusHariRange('001', ?, ?, ?)";
            $Hari = date('N', strtotime($Tanggal));
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }
            $data = DB::select($query, [$karyawan->Kode_Karyawan, $Tanggal, $Tanggal]);
            // dd($data);
            $dataFinal = collect($data)
                ->map(function ($item) use ($karyawan, $KaryawanSelect) {
                    if (! empty($item->StatusHari) && str_starts_with($item->StatusHari, 'OFF')) {
                        $item->Jam_Mulai_Absen_Masuk = '00:00';
                        $item->Jam_Selesai_Absen_Masuk = '00:00';
                        $item->Jam_Mulai_Absen_Keluar = '00:00';
                        $item->Jam_Selesai_Absen_Keluar = '00:00';
                        $item->Nama_Shift = 'OFF';
                        $item->Nama_Hari_Kerja = 'OFF';
                        $item->Jam_Masuk = '00:00';
                        $item->Jam_Keluar = '00:00';
                    } else {
                        if (! empty($item->Kode_Karyawan)) {
                            $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                            $item->UserID_Absen = Crypt::encryptString($karyawan->UserID_Absen);
                        }

                        if (! empty($item->ID_Shift)) {
                            // Gabung 3 query (1 per kolom) -> 1 query. Ketiga kolom Jam_*_Absen_*
                            // ada di HRIS_Waktu_Kerja (c), bukan HRIS_Shift_Kerja (a). Join a->b->c
                            // via ID_Shift lalu ID_Waktu_Kerja untuk sampai ke baris waktu kerja.
                            $shift = DB::table('HRIS_Shift_Kerja as a')
                                ->select('c.Jam_Mulai_Absen_Masuk', 'c.Jam_Selesai_Absen_Masuk', 'c.Jam_Mulai_Absen_Keluar')
                                ->join('HRIS_Shift_Kerja_Detail as b', 'a.ID_Shift', '=', 'b.ID_Shift')
                                ->join('HRIS_Waktu_Kerja as c', 'b.ID_Waktu_Kerja', '=', 'c.ID_Waktu_Kerja')
                                ->where('a.ID_Shift', $item->ID_Shift)
                                ->first();

                            if ($shift) {
                                $item->Jam_Mulai_Absen_Masuk = $shift->Jam_Mulai_Absen_Masuk;
                                $item->Jam_Selesai_Absen_Masuk = $shift->Jam_Selesai_Absen_Masuk;
                                $item->Jam_Mulai_Absen_Keluar = $shift->Jam_Mulai_Absen_Keluar;
                            }
                        }
                        if (! empty($item->Jam_selesai_absen)) {
                            $item->Jam_Selesai_Absen_Keluar = $item->Jam_selesai_absen;
                        }
                    }

                    $item->Nama = $karyawan->Nama;
                    $item->nama_divisi = $KaryawanSelect->nama_divisi ?? null;

                    return $item;
                })
                ->first();
            // dd($dataFinal);

            if (! $dataFinal) {
                $dataFinal = (object) ['Tanggal' => $Tanggal];
            }

            $statusHariInfo = $this->getStatusHariInfo($karyawan->Kode_Karyawan, $Tanggal);
            $dataFinal->status_hari = $statusHariInfo['status_hari'];
            $dataFinal->status_hari_label = $statusHariInfo['status_hari_label'];
            $dataFinal->is_day_off = $statusHariInfo['is_day_off'];

            // dd($hashedUser);
            return inertia('ESS/Attendance/Personal/AbsensiSalesPage', [
                'userLogin' => $hashedUser,
                'TodayData' => $dataFinal,
            ]);
        } catch (\Throwable $e) {
            Log::channel('absensiLog')->error('Gagal menampilkan page absensi: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page absensi!');

            return back();
        }
    }

    public function confirmIzin(Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            $No_Transaksi = $request->No_Transaksi;
            $requester = Crypt::decryptString($request->requester);
            $approver = Crypt::decryptString($request->approver);
            $tipeConfirm = $request->tipeConfirm;

            // Snapshot-based: baca approver & status dari HRIS_Approval_Request
            // (bukan join ke HRIS_Approval_Flow config live) agar perubahan/hapus
            // config approval tidak menghilangkan/menghentikan transaksi berjalan.
            $query = DB::table('HRIS_Approval_Request as a')
                ->whereNull('a.Status')
                ->where('a.No_Transaksi', $No_Transaksi)
                ->where('a.Kode_Karyawan', $requester)
                ->where('a.Approver_Karyawan_Kode', $approver);
            // dd($query);
            if (! $query->exists()) {
                DB::rollBack();
                Log::channel('izinLog')->error('Data transaksi izin tidak ditemukan atau tidak sesuai');

                return response()->json(
                    [
                        'status' => 404,
                        'message' => 'Data transaksi izin tidak ditemukan atau tidak sesuai.',
                    ],
                    404,
                );
            }

            // Cek apakah approval sudah expired berdasarkan Expired_At
            $isExpired = DB::table('HRIS_Approval_Request as a')
                ->whereNull('a.Status')
                ->where('a.No_Transaksi', $No_Transaksi)
                ->where('a.Kode_Karyawan', $requester)
                ->where('a.Approver_Karyawan_Kode', $approver)
                ->whereNotNull('a.Expired_At')
                ->where('a.Expired_At', '<', now())
                ->exists();

            if ($isExpired) {
                DB::rollBack();
                Log::channel('izinLog')->warning('Approval sudah expired: '.$No_Transaksi);

                return response()->json(
                    [
                        'status' => 400,
                        'message' => 'Approval sudah expired. Tidak dapat diproses.',
                    ],
                    400,
                );
            }

            $updateValue = $tipeConfirm == 'Terima' ? 'Y' : 'T';
            $successMessage = $tipeConfirm == 'Terima' ? 'Berhasil Menerima Izin!' : 'Berhasil Menolak Izin!';

            $insertRequest = $query->update(['Flag_Approval' => $updateValue]);

            if ($insertRequest) {
                // dd($updateValue);
                if ($updateValue == 'T') {
                    $prefix = substr($No_Transaksi, 0, 2);
                    if ($prefix == 'IS') {
                        $Izin = DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $No_Transaksi);
                        $Izin->update(['Flag_Approval' => 'T']);

                        $spekIzin = DB::table('Transaksi_Sakit_Izin_Detail')
                            ->select(
                                'Kode_Karyawan',
                                'Jenis',
                                'Tanggal_Sakit_Izin_Dari as dari',
                                'Tanggal_Sakit_Izin_Sampai as sampai',
                            )
                            ->where('No_Transaksi', $No_Transaksi)
                            ->first();

                        $user = DB::table('Karyawan as a')
                            ->join('Transaksi_Sakit_Izin_Detail as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
                            ->where('b.No_Transaksi', $No_Transaksi)
                            ->first();
                        $userInsertNoHp = $user->HP;
                    } elseif ($prefix == 'TP') {
                        $Izin = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')->where(
                            'No_Transaksi',
                            $No_Transaksi,
                        );
                        $Izin->update(['Flag_Approval' => 'T']);

                        $spekIzin = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                            ->select(
                                'Kode_Karyawan',
                                'Jenis',
                                'Tanggal_Masuk_Pulang as dari',
                                DB::raw('null as sampai'),
                            )
                            ->where('No_Transaksi', $No_Transaksi)
                            ->first();

                        $user = DB::table('Karyawan as a')
                            ->join(
                                'Transaksi_Terlambat_Pulang_Cepat_Detail as b',
                                'a.Kode_Karyawan',
                                '=',
                                'b.Kode_Karyawan',
                            )
                            ->where('b.No_Transaksi', $No_Transaksi)
                            ->first();
                        $userInsertNoHp = $user->HP;
                    } elseif ($prefix == 'CT') {
                        $Izin = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $No_Transaksi);
                        $Izin->update(['Flag_Approval' => 'T']);

                        $spekIzin = DB::table('Transaksi_Cuti_Detail')
                            ->select(
                                'Kode_Karyawan',
                                'Jenis',
                                'Tanggal_Cuti_Dari as dari',
                                'Tanggal_Cuti_Sampai as sampai',
                            )
                            ->where('No_Transaksi', $No_Transaksi)
                            ->first();

                        $user = DB::table('Karyawan as a')
                            ->join('Transaksi_Cuti_Detail as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
                            ->where('b.No_Transaksi', $No_Transaksi)
                            ->first();
                        $userInsertNoHp = $user->HP;
                    }
                    // dd($user);

                    if ($user != null && $userInsertNoHp != '-' && $userInsertNoHp != ' ') {
                        // whatsapp message to user
                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $userInsertNoHp,
                            'type' => 'template',
                            'template' => [
                                'name' => 'confirm_izin_tolak',
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
                                                'text' => $user->Nama,
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $No_Transaksi.' Pengajuan '.$this->parseIzin($spekIzin->Jenis),
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => Auth::user()->karyawan->Nama,
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
                            $user->Kode_Karyawan ?? null,
                            $user->Nama ?? null,
                            $userInsertNoHp ?? ($user->HP ?? null),
                            $No_Transaksi ?? null,
                            $pesan,
                        );
                    }
                } elseif ($updateValue == 'Y') {
                    // dd("hidup jokowi");
                    $flowSekarang = DB::table('HRIS_Approval_Request as a')
                        ->whereNull('a.Status')
                        ->where('a.No_Transaksi', $No_Transaksi)
                        ->where('a.Kode_Karyawan', $requester)
                        ->where('a.Approver_Karyawan_Kode', $approver)
                        ->first();
                    // dd($flowSekarang);

                    $angkaFlowSekarang = $flowSekarang ? $flowSekarang->Request_Order_Flow : null;
                    if (! $angkaFlowSekarang) {
                        Log::channel('izinLog')->error('Gagal Meyimpan keran angka flow tidak ada');

                        return response()->json(
                            [
                                'status' => 500,
                                'message' => 'Gagal Meyimpan keran angka flow tidak ada',
                            ],
                            500,
                        );
                    }
                    if ($angkaFlowSekarang) {
                        $maxAngkaOrder = DB::table('HRIS_Approval_Request as a')
                            ->whereNull('a.Status')
                            ->where('a.No_Transaksi', $No_Transaksi)
                            ->where('a.Kode_Karyawan', $requester)
                            ->max('a.Request_Order_Flow');

                        if ($angkaFlowSekarang == $maxAngkaOrder) {
                            $prefix = substr($No_Transaksi, 0, 2);
                            if ($prefix == 'IS') {
                                $Izin = DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $No_Transaksi);
                                $Izin->update(['Flag_Approval' => 'Y']);

                                $user = DB::table('Karyawan as a')
                                    ->join(
                                        'Transaksi_Sakit_Izin_Detail as b',
                                        'a.Kode_Karyawan',
                                        '=',
                                        'b.Kode_Karyawan',
                                    )
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            } elseif ($prefix == 'TP') {
                                $Izin = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')->where(
                                    'No_Transaksi',
                                    $No_Transaksi,
                                );
                                $Izin->update(['Flag_Approval' => 'Y']);

                                $user = DB::table('Karyawan as a')
                                    ->join(
                                        'Transaksi_Terlambat_Pulang_Cepat_Detail as b',
                                        'a.Kode_Karyawan',
                                        '=',
                                        'b.Kode_Karyawan',
                                    )
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            } elseif ($prefix == 'CT') {
                                $Izin = DB::table('Transaksi_Cuti_Detail')
                                    ->where('No_Transaksi', $No_Transaksi)
                                    ->first();

                                if (! $Izin) {
                                    throw new \Exception("Data cuti tidak ditemukan: {$No_Transaksi}");
                                }

                                $jumlahHariCuti =
                                    Carbon::parse($Izin->Tanggal_Cuti_Dari)->diffInDays(
                                        Carbon::parse($Izin->Tanggal_Cuti_Sampai),
                                    ) + 1;
                                $idJenisCuti = (int) ($Izin->Id_Jenis_Cuti ?? TransactionService::ID_TAHUNAN);
                                $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')
                                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                                    ->first();

                                if ($jenisCuti && $jenisCuti->Memotong_Saldo_Cuti == 'Y') {
                                    $this->transactionService->debitBalanceWithDebt(
                                        $Izin->Kode_Karyawan,
                                        $idJenisCuti,
                                        (float) $jumlahHariCuti,
                                        $Izin->No_Transaksi,
                                        'PENGAJUAN_CUTI',
                                        'Potong Cuti Approved',
                                        'SYSTEM',
                                    );
                                }

                                DB::table('Transaksi_Cuti_Detail')
                                    ->where('No_Transaksi', $No_Transaksi)
                                    ->update(['Flag_Approval' => 'Y']);

                                $user = DB::table('Karyawan as a')
                                    ->join('Transaksi_Cuti_Detail as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            }

                            if ($user != null && $userInsertNoHp != '-' && $userInsertNoHp != ' ') {
                                // dd($user);
                                $izinPesan = method_exists($Izin, 'first') ? $Izin->first() : $Izin;

                                // whatsapp message to user
                                $pesan = [
                                    'messaging_product' => 'whatsapp',
                                    'to' => $userInsertNoHp,
                                    'type' => 'template',
                                    'template' => [
                                        'name' => 'confirm_izin_diterima',
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
                                                        'text' => $user->Nama,
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $this->parseIzin($izinPesan->Jenis ?? '').
                                                            ' anda No. '.
                                                            $No_Transaksi,
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
                                    $user->Kode_Karyawan ?? null,
                                    $user->Nama ?? null,
                                    $userInsertNoHp ?? ($user->HP ?? null),
                                    $No_Transaksi ?? null,
                                    $pesan,
                                );
                            }
                        } else {
                            $prefix = substr($No_Transaksi, 0, 2);
                            if ($prefix == 'IS') {
                                $spekIzin = DB::table('Transaksi_Sakit_Izin_Detail')
                                    ->select(
                                        'Kode_Karyawan',
                                        'Jenis',
                                        'Tanggal_Sakit_Izin_Dari as dari',
                                        DB::raw('null as Jam'),
                                        'Tanggal_Sakit_Izin_Sampai as sampai',
                                    )
                                    ->where('No_Transaksi', $No_Transaksi)
                                    ->first();

                                $user = DB::table('Karyawan as a')
                                    ->join(
                                        'Transaksi_Sakit_Izin_Detail as b',
                                        'a.Kode_Karyawan',
                                        '=',
                                        'b.Kode_Karyawan',
                                    )
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            } elseif ($prefix == 'TP') {
                                $spekIzin = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                                    ->select(
                                        'Kode_Karyawan',
                                        'Jenis',
                                        'Tanggal_Masuk_Pulang as dari',
                                        'Jam',
                                        DB::raw('null as sampai'),
                                    )
                                    ->where('No_Transaksi', $No_Transaksi)
                                    ->first();

                                $user = DB::table('Karyawan as a')
                                    ->join(
                                        'Transaksi_Terlambat_Pulang_Cepat_Detail as b',
                                        'a.Kode_Karyawan',
                                        '=',
                                        'b.Kode_Karyawan',
                                    )
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            } elseif ($prefix == 'CT') {
                                $spekIzin = DB::table('Transaksi_Cuti_Detail')
                                    ->select(
                                        'Kode_Karyawan',
                                        'Jenis',
                                        'Tanggal_Cuti_Dari as dari',
                                        DB::raw('null as Jam'),
                                        'Tanggal_Cuti_Sampai as sampai',
                                    )
                                    ->where('No_Transaksi', $No_Transaksi)
                                    ->first();

                                $user = DB::table('Karyawan as a')
                                    ->join('Transaksi_Cuti_Detail as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
                                    ->where('b.No_Transaksi', $No_Transaksi)
                                    ->first();
                                $userInsertNoHp = $user->HP;
                            }

                            $nextFlow = DB::table('HRIS_Approval_Request as a')
                                ->select('a.Approver_Karyawan_Kode as Kode_Karyawan')
                                ->whereNull('a.Status')
                                ->where('a.No_Transaksi', $No_Transaksi)
                                ->where('a.Kode_Karyawan', $requester)
                                ->where('a.Request_Order_Flow', $flowSekarang->Request_Order_Flow + 1)
                                ->first();

                            $nextApprover = DB::table('Karyawan')
                                ->where('Kode_Karyawan', $nextFlow->Kode_Karyawan)
                                ->first();
                            // dd($nextApprover);
                            if (
                                ! $nextApprover ||
                                ($nextApprover->HP == '' || $nextApprover->HP == ' ' || $nextApprover->HP == '-')
                            ) {
                                Log::channel('waIzinPage')->warning('Pesan Error', [
                                    'pesan' => "Karyawan {$nextApprover->Nama} Tidak ada Nomor Hp untuk noitifikasi Transaksi {$No_Transaksi}",
                                ]);
                            } else {
                                $TanggalWa =
                                    $spekIzin->Jam != null
                                        ? $this->formatTanggal($spekIzin->dari, null, $spekIzin->Jam)
                                        : $this->formatTanggal($spekIzin->dari, $spekIzin->sampai);
                                $namaRequest = DB::table('Karyawan')
                                    ->where('Kode_Karyawan', $spekIzin->Kode_Karyawan)
                                    ->first();
                                // dd($nextApprover);
                                // whatsapp message to user
                                $pesan = [
                                    'messaging_product' => 'whatsapp',
                                    'to' => $nextApprover->HP,
                                    'type' => 'template',
                                    'template' => [
                                        'name' => 'notif_approver_hcis',
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
                                                        'text' => $nextApprover->Nama,
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $No_Transaksi,
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $this->parseIzin($spekIzin->Jenis),
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $TanggalWa,
                                                    ],
                                                    [
                                                        'type' => 'text',
                                                        'text' => $namaRequest->Nama,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ];

                                // WA + update penanda angka_wa ditunda sampai commit agar HTTP
                                // tidak menahan lock DB. angka_wa hanya metrik penghitung WA
                                // terkirim, aman di-update setelah commit.
                                DB::afterCommit(function () use ($pesan, $user, $nextApprover, $userInsertNoHp, $No_Transaksi) {
                                    try {
                                        $response = Whatsapp::send_message($pesan, [
                                            'kode_karyawan' => $user->Kode_Karyawan ?? ($nextApprover->Kode_Karyawan ?? null),
                                            'nama' => $user->Nama ?? ($nextApprover->Nama ?? null),
                                            'hp' => $userInsertNoHp ?? ($user->HP ?? ($nextApprover->HP ?? null)),
                                            'no_transaksi' => $No_Transaksi ?? null,
                                            'module' => 'HCIS',
                                            'source_context' => 'ABSENSI_CONTROLLER',
                                        ]);
                                        Log::channel('waIzinLog')->info('WA izin terkirim.', [
                                            'response' => $response,
                                        ]);
                                    } catch (\Throwable $e) {
                                        Log::channel('waIzinLog')->warning('Gagal mengirim WA izin: '.$e->getMessage(), [
                                            'no_transaksi' => $No_Transaksi ?? null,
                                        ]);
                                        $response = null;
                                    }

                                    if ($response) {
                                        DB::table('HRIS_Approval_Request as a')
                                            ->whereNull('a.Status')
                                            ->where('a.No_Transaksi', $No_Transaksi)
                                            ->where('a.Approver_Karyawan_Kode', $nextApprover->Kode_Karyawan)
                                            ->update([
                                                'angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1'),
                                            ]);
                                    }
                                });
                            }
                        }
                    }
                }
            }

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $No_Transaksi,
                $updateValue == 'Y' ? 'IZIN_TRANSACTION_APPROVED' : 'IZIN_TRANSACTION_REJECTED',
                'ABSENSI_CONFIRM_IZIN',
                1,
            );

            // Setelah atasan approve cuti, kalau step berikutnya adalah ROLE
            // pool (mis. Verifikasi HC Ops Cuti), kirim notif IN_APP ke seluruh
            // member role pool. Tidak ada WA untuk role pool — sesuai keputusan.
            if ($updateValue === 'Y' && substr((string) $No_Transaksi, 0, 2) === 'CT') {
                try {
                    app(\App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService::class)->notifyRolePoolForCurrentStep(
                        $No_Transaksi,
                    );
                } catch (\Throwable $notifEx) {
                    Log::channel('izinLog')->warning(
                        'Gagal kirim notif role pool cuti: '.
                            $notifEx->getMessage().
                            ' - Transaksi: '.
                            $No_Transaksi,
                    );
                }
            }

            return response()->json([
                'status' => 200,
                'message' => $successMessage,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('izinLog')->error(
                'Gagal menyimpan data confirmIzin: '.$e->getMessage().' - Transaksi: '.($No_Transaksi ?? 'N/A'),
            );

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan internal server, silakan coba lagi beberapa saat. Detail: '.
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function getDataToday()
    {
        try {
            $Kode_Perusahaan = '001';
            $Tanggal = date('Y-m-d');
            $karyawan = Auth::user()->karyawan;
            $query = 'exec sp_getShiftAbsen ?, ?, ?, ?';

            $Hari = date('N', strtotime($Tanggal));
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }
            $data = DB::select($query, [$Tanggal, $karyawan->Kode_Karyawan, $Kode_Perusahaan, $Hari]);

            $dataFinal = collect($data)
                ->map(function ($item) {
                    if (! empty($item->Kode_Karyawan)) {
                        $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                    }
                    if (! empty($item->UserID_Absen)) {
                        $item->UserID_Absen = Crypt::encryptString($item->UserID_Absen);
                    }

                    return $item;
                })
                ->first();

            if (! $dataFinal) {
                $dataFinal = (object) ['Tanggal' => $Tanggal];
            }

            $statusHariInfo = $this->getStatusHariInfo($karyawan->Kode_Karyawan, $Tanggal);
            $dataFinal->status_hari = $statusHariInfo['status_hari'];
            $dataFinal->status_hari_label = $statusHariInfo['status_hari_label'];
            $dataFinal->is_day_off = $statusHariInfo['is_day_off'];

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil Mengambil data',
                'data' => $dataFinal,
            ]);
        } catch (\Throwable $e) {
            Log::channel('absensiLog')->error('Gagal Menambil data getDataToday'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    // public function getDataIzinAdmin(Request $request){
    //     try{
    //         $validated = $request->validate([
    //             'search' => ['nullable', 'string', 'max:100'],
    //             'tipeFilter' => ['nullable', 'string', 'max:100'],
    //             'tanggalFilter' => ['nullable', 'string', 'max:100'],
    //             'page' => ['nullable', 'integer', 'min:1'],
    //         ]);

    //         $search = strip_tags($validated['search'] ?? '');
    //         $perPage = 10;
    //         $page = $validated['page'] ?? 1;
    //         $offset = ($page - 1) * $perPage;
    //         $searchColoms = [
    //             'Nama',
    //             'No_Transaksi',
    //             'Alasan',
    //             'Tipe_Izin'
    //         ];
    //         $tipeFilter = $validated['tipeFilter'] ?? null;
    //         $tanggalFilter = $validated['tanggalFilter'] ?? null;
    //         $karyawan_Auth = Auth::user()->karyawan->Kode_Karyawan;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         $baseQuery ="
    //                     WITH
    //                     cte AS (

    //                         -- Query untuk Transaksi_Sakit_Izin_Detail
    //                         SELECT
    //                         CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         tsi.No_Transaksi,
    //                         tsi.Jenis as Tipe_Izin,
    //                         tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                         tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                         tsi.Alasan,
    //                         tsi.filePath as Lampiran,
    // 						(select Nama from Karyawan z where z.Kode_Karyawan = tsi.Kode_Karyawan) as nama_requester,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND af.Status is null
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = tsi.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is Null
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN tsi.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = tsi.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                            -- and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                         INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                         WHERE
    //                          tsip.Status is null
    //                         UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                         SELECT
    //                         CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ttp.No_Transaksi,
    //                         ttp.Jenis as Tipe_Izin,
    //                         ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                         NULL AS Tanggal_Selesai,
    //                         ttp.Alasan,
    //                         ttp.filePath as Lampiran,
    // 						(select Nama from Karyawan z where z.Kode_Karyawan = ttp.Kode_Karyawan) as nama_requester,

    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ttp.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ttp.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 ---AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         ttp.Jam AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ttp.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                         WHERE
    //                        ttpp.Status is null
    //                     )
    //                     SELECT
    //                     *
    //                     FROM
    //                     cte
    //                     where
    //                     MaxFlow <> 0

    //         ";
    //         $queryCount ="
    //                     WITH
    //                     cte AS (

    //                         -- Query untuk Transaksi_Sakit_Izin_Detail
    //                         SELECT
    //                         CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         tsi.No_Transaksi,
    //                         tsi.Jenis as Tipe_Izin,
    //                         tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                         tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                         tsi.Alasan,
    //                         tsi.filePath as Lampiran,
    // 						(select Nama from Karyawan z where z.Kode_Karyawan = tsi.Kode_Karyawan) as nama_requester,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND af.Status is null
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = tsi.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is Null
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN tsi.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = tsi.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                            -- and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                         INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                         WHERE
    //                          tsip.Status is null
    //                         UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                         SELECT
    //                         CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ttp.No_Transaksi,
    //                         ttp.Jenis as Tipe_Izin,
    //                         ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                         NULL AS Tanggal_Selesai,
    //                         ttp.Alasan,
    //                         ttp.filePath as Lampiran,
    // 						(select Nama from Karyawan z where z.Kode_Karyawan = ttp.Kode_Karyawan) as nama_requester,

    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ttp.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ttp.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 ---AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         ttp.Jam AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ttp.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                         WHERE
    //                        ttpp.Status is null
    //                     )
    //                     SELECT
    //                     count(No_Transaksi) as total
    //                     FROM
    //                     cte
    //                     where
    //                     MaxFlow <> 0

    //         ";
    //         // $result = DB::select($query);
    //         // $dataFinal =  collect($result)->map(function ($item) {
    //         //     if (!empty($item->Kode_Karyawan)) {
    //         //         $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);

    //         //     }

    //         //     return $item;
    //         // });

    //         $params = [];
    //         if($search){
    //             $searchParam = '%' . $search .'%';
    //             $likeParts = [];
    //             foreach($searchColoms as $col){
    //                 $likeParts[]= "$col Like ?";
    //                 $params[] = $searchParam;
    //             }

    //             $likeQuery = implode(' OR ', $likeParts);
    //             $queryCount .= " AND ($likeQuery) ";
    //             $baseQuery .= " AND ($likeQuery) ";

    //         }

    //         if($tipeFilter){
    //             $queryCount .= " AND Tipe_Izin = ?";
    //             $baseQuery .= " AND Tipe_Izin = ?";
    //             $params[] = $tipeFilter;
    //         }

    //         if ($tanggalFilter) {
    //             $queryCount .= " AND (
    //                 cast(? as date) between cast(Tanggal_Mulai as date)
    //                 and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
    //             )";
    //             $baseQuery .= " AND (
    //                 cast(? as date) between cast(Tanggal_Mulai as date)
    //                 and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
    //             )";

    //             $params[] = $tanggalFilter;
    //         }

    //         $total = DB::selectOne($queryCount, $params)->total;

    //         $queryData = $baseQuery . " ORDER BY tanggal_create DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    //         $params[]=$offset;
    //         $params[]=$perPage;

    //         $result = DB::select($queryData, $params);

    //         $dataFinal = collect($result)->map(function ($item) {
    //             if (!empty($item->Kode_Karyawan)) {
    //                 $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
    //             }
    //             return $item;
    //         });

    //         // Buat paginator
    //         $final_page = new LengthAwarePaginator(
    //             $dataFinal,
    //             $total,
    //             $perPage,
    //             $page,
    //             [
    //                 'path' => request()->url(),
    //                 'query' => request()->query(),
    //             ]
    //         );

    //         $dataCuti= (object) [
    //             'Sisa_Cuti' => (int) Auth::user()->karyawan->sisa_cuti(),
    //             'Hutang_Cuti'=>(int) Auth::user()->karyawan->hutang_cuti()

    //         ];
    //         // dd($dataCuti);
    //         $TanggalCutiSudahDipakai = DB::table('Transaksi_Cuti_Detail as a')
    //                                     ->join("Transaksi_Cuti as b", "a.No_Transaksi", '=', 'b.No_Transaksi')
    //                                     ->whereNull("b.Status")
    //                                     ->where("a.Kode_Karyawan", $Kode_Karyawan)
    //                                     ->where(function($query) {
    //                                         $query->where("a.Flag_Approval", "<>", "T")
    //                                         ->orWhereNull('a.Flag_Approval');

    //                                     })
    //                                     ->get();

    //         $TanggalSakitIzinSudahDipakai =  DB::table('Transaksi_Sakit_Izin_Detail as a')
    //                                 ->join("Transaksi_Sakit_Izin as b", "a.No_Transaksi", '=', 'b.No_Transaksi')
    //                                 ->whereNull("b.Status")
    //                                 ->where("Kode_Karyawan", $Kode_Karyawan)
    //                                 ->where(function($query) {
    //                                         $query->where("a.Flag_Approval", "<>", "T")
    //                                         ->orWhereNull('a.Flag_Approval');

    //                                     })
    //                                 ->get();
    //         // dd($TanggalSakitIzinSudahDipakai);
    //         $TanggalPulangCepatSudahDipakai =  DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as a')
    //                                 ->join("Transaksi_Terlambat_Pulang_Cepat as b", "a.No_Transaksi", '=', 'b.No_Transaksi')
    //                                 ->whereNull("b.Status")
    //                                 ->where("Kode_Karyawan", $Kode_Karyawan)
    //                                 ->where(function($query) {
    //                                     $query->where("a.Flag_Approval", "<>", "T")
    //                                     ->orWhereNull('a.Flag_Approval');

    //                                 })
    //                                 ->get();

    //         $HariLibur = collect(DB::table('HRIS_Hari_Libur')->get())->map(function ($item){
    //             if(!empty($item->Tanggal)){
    //                 $item->date = Carbon::parse($item->Tanggal)->format('Y-m-d');
    //             }
    //             if(!empty($item->Nama_Hari_Libur)){
    //                 // Assuming Nama_Hari_Libur is also a date string that needs formatting.
    //                 // If it's not a date, this line will cause an error.
    //                 $item->description = $item->Nama_Hari_Libur;
    //             }
    //             return $item; // It is important to return the modified item
    //         });

    //         return response()->json([
    //             'status' => 200,
    //             'message' => 'Berhasil mengambil data',
    //             'data' => $final_page,
    //             'dataCuti' => $dataCuti,
    //             'TanggalCuti' => $TanggalCutiSudahDipakai,
    //             'TanggalSakitIzin' => $TanggalSakitIzinSudahDipakai,
    //             'TanggalPulangTerlambat' => $TanggalPulangCepatSudahDipakai,
    //             'HariLibur' => $HariLibur
    //         ]);
    //     }catch(\Throwable $e){
    //         Log::channel('izinLog')->error('Gagal Menambil data getDataIzin'.$e->getMessage());
    //         return response()->json([
    //             'status' => 404,
    //             'message' => 'Terjadi kesalahan coba lagi beberapa saat..'
    //         ], 404);
    //     }
    // }

    public function getDataIzinAdmin(Request $request)
    {
        $batasHariPengajuan = config('services.hcis.batas_approve_izin');
        $batasApproveCuti = config('services.hcis.batas_approve_cuti');

        // 1. Validasi
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tipeFilter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            // Hanya status yang bisa dijawab SQL. Diproses/Selesai/Ditolak diturunkan
            // di PHP setelah halaman diambil, jadi memfilternya di sini akan merusak
            // hitungan paginasi.
            'statusFilter' => ['nullable', 'string', 'in:Aktif,Dibatalkan'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $user = Auth::user();
            $kodeKaryawanAuth = $user->karyawan->Kode_Karyawan;

            $limit = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $search = strip_tags($validated['search'] ?? '');
            $tipeFilter = $validated['tipeFilter'] ?? null;
            $tanggalFilter = $validated['tanggalFilter'] ?? null;
            $statusFilter = $validated['statusFilter'] ?? null;

            // View khusus admin: SATU-SATUNYA yang ikut memuat transaksi dibatalkan
            // (Status='Y'). View biasa memfilternya, supaya inbox approver & dashboard
            // tetap bersih.
            $query = DB::table('N_HRIS_VW_All_Transactions_Admin');
            // ->where('Kode_Karyawan_Requester', $kodeKaryawanAuth); // Filter milik user login

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Requester', 'like', "%{$search}%")
                        ->orWhere('No_Transaksi', 'like', "%{$search}%")
                        ->orWhere('Deskripsi', 'like', "%{$search}%") // Alasan
                        ->orWhere('Sub_Tipe', 'like', "%{$search}%") // Tipe Izin
                        ->orWhere('Tipe_Transaksi', 'like', "%{$search}%"); // Tipe Izin
                });
            }

            if ($tipeFilter) {
                $query->where('Sub_Tipe', $tipeFilter)->orWhere('Tipe_Transaksi', $tipeFilter);
            }

            if ($tanggalFilter) {
                $query->where(function ($q) use ($tanggalFilter) {
                    $q->whereRaw(
                        'cast(? as date) between cast(Tanggal_Mulai as date) and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)',
                        [$tanggalFilter],
                    );
                });
            }

            if ($statusFilter === 'Dibatalkan') {
                $query->where('Status_Header', 'Y');
            } elseif ($statusFilter === 'Aktif') {
                $query->whereNull('Status_Header');
            }

            $excludedPrefixes = ['ISX%', 'CTX%', 'TPX%'];

            $query->where(function ($q) use ($excludedPrefixes) {
                foreach ($excludedPrefixes as $prefix) {
                    $q->where('No_Transaksi', 'NOT LIKE', $prefix);
                }
            });
            $total = $query->count();

            $items = $query
                ->orderBy('Created_At', 'DESC') // Order by View column
                ->offset($offset)
                ->limit($limit)
                ->get();

            $transactionIds = $items->pluck('No_Transaksi')->toArray();

            $approvals = collect();
            if (! empty($transactionIds)) {
                $approvals = DB::table('N_HRIS_VW_Approval_Request')
                    ->whereIn('No_Transaksi', $transactionIds)
                    ->orderBy('Request_Order_Flow', 'ASC') // Pastikan urut step 1, 2, 3
                    ->get()
                    ->groupBy('No_Transaksi');
            }

            // $cari = "'" . implode("', '", $transactionIds) . "'";
            // dd($cari);

            $dataFinal = $items->map(function ($item) use ($approvals) {
                // dd($approvals);
                $currApprovals = $approvals->get($item->No_Transaksi, collect());
                // dd($currApprovals);

                // A. Cari Max Flow & Current Flow
                $maxFlow = $currApprovals->max('Request_Order_Flow') ?? 0;

                $lastAction = $currApprovals->whereNotNull('Flag_Approval')->sortByDesc('Request_Order_Flow')->first();
                $currentFlow = $lastAction
                    ? $lastAction->Request_Order_Flow
                    : $currApprovals->first()->Request_Order_Flow ?? 0;
                // dd($currentFlow);
                $isRejected = $currApprovals->contains('Flag_Approval', 'T');
                $isFullyApproved = $currApprovals->every(fn ($a) => $a->Flag_Approval == 'Y');

                // dd($item);
                // Status Header
                $statusHeader = 'NO';
                $itemApproval = $this->parseFlagApproval(
                    $item->Flag_Approval,
                    $item->Created_At,
                    $item->Tipe_Transaksi,
                );
                // dd($itemApproval);
                $isDibatalkan = $item->Status_Header === 'Y';

                // Pembatalan mengalahkan status approval apa pun: transaksi yang sudah
                // disetujui pun bisa dibatalkan admin, dan yang harus dilihat admin
                // adalah kondisi akhirnya.
                if ($isDibatalkan) {
                    $statusHeader = 'Dibatalkan';
                } elseif ($itemApproval == 'Y' || ($isFullyApproved && count($currApprovals) > 0)) {
                    $statusHeader = 'Selesai';
                } elseif ($itemApproval == 'T' || $itemApproval == 'P' || $isRejected) {
                    $statusHeader = 'Ditolak';
                } else {
                    // Jika belum selesai dan belum ditolak, cari apakah ada yang pending
                    $pendingExist = $currApprovals->contains(fn ($a) => is_null($a->Flag_Approval));
                    if ($pendingExist) {
                        $statusHeader = 'Diproses';
                    }
                }

                $approverColumn = null;

                $rejector = $currApprovals->firstWhere('Flag_Approval', 'T');

                $pendingApprover = $currApprovals->sortBy('Request_Order_Flow')->firstWhere('Flag_Approval', null);

                // dd($currApprovals);
                if ($itemApproval == 'Y' || ($isFullyApproved && count($currApprovals) > 0)) {
                    $approverColumn = 'DiSetujui';
                } elseif ($itemApproval == 'P') {
                    $approverColumn = 'Sistem';
                } elseif ($rejector) {
                    $approverColumn = $rejector->Current_Approver_Kode; // Kode Karyawan Penolak
                } elseif ($pendingApprover) {
                    // $approverColumn = $pendingApprover->Nama_Approver; // Nama Approver yang sedang menunggu
                    $approverColumn = $pendingApprover->Current_Approver_Kode; // Nama Approver yang sedang menunggu
                } else {
                    $approverColumn = null;
                }

                $statusListStr = $currApprovals
                    ->map(function ($row) use ($item) {
                        // dd($row);
                        $flag =
                            $this->parseFlagApproval(
                                $row->Flag_Approval,
                                $row->Tanggal_Request,
                                $item->Tipe_Transaksi,
                            ) ?? 'N';

                        return "{$row->Current_Approver_Kode}: {$flag}";
                    })
                    ->implode(', ');

                // dd($statusListStr);s

                $tanggalReal = TanggalTransaksiIzin::teks($item->No_Transaksi);

                return (object) [
                    'tanggal_create' => $item->Created_At, // Sesuaikan kolom View
                    'Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),
                    'Nama' => $item->Nama_Requester,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Tipe_Izin' => $item->Sub_Tipe ?? $item->Tipe_Transaksi, // Sesuaikan View
                    'Tanggal_Mulai' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Alasan' => $item->Deskripsi, // Sesuaikan View (Alasan/Deskripsi)
                    'Lampiran' => $item->File_Path,
                    'nama_requester' => $item->Nama_Requester,
                    'currentFlow' => $currentFlow,
                    'MaxFlow' => $maxFlow,
                    'status' => $statusHeader,
                    'Approver' => $approverColumn,
                    'Jam_Mulai' => $item->Jam_Diajukan ?? null, // Sesuaikan View
                    // 'StatusList'        => $statusListStr
                    'STATUSLIST' => $statusListStr,
                    'TanggalReal' => $tanggalReal,
                    'is_dibatalkan' => $isDibatalkan,
                    // Status='Y' warisan lama (soft-delete karyawan) tak punya jejak
                    // pelaku/alasan -> field ini null, UI menampilkannya sebagai data lama.
                    'dibatalkan_oleh' => $item->Cancelled_By_Nama ?? null,
                    'alasan_pembatalan' => $item->Cancel_Reason ?? null,
                    'tanggal_pembatalan' => $item->Cancelled_At ?? null,
                ];
            });

            // Buat Paginator
            $final_page = new LengthAwarePaginator($dataFinal, $total, $limit, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            // Data Cuti User
            $dataCuti = (object) [
                'Sisa_Cuti' => (int) $user->karyawan->sisa_cuti(),
                'Hutang_Cuti' => (int) $user->karyawan->hutang_cuti(),
            ];

            // Helper Query agar tidak berulang
            $filterApproval = function ($q) {
                $q->where(function ($sub) {
                    $sub->where('a.Flag_Approval', '<>', 'T')->orWhereNull('a.Flag_Approval');
                });
            };

            $TanggalCutiSudahDipakai = DB::table('Transaksi_Cuti_Det as det')
                ->join('Transaksi_Cuti_Detail as a', function ($join) {
                    $join->on('det.No_Transaksi', '=', 'a.No_Transaksi')->on('det.Urut_Detail', '=', 'a.Urut_Oto');
                })
                ->join('Transaksi_Cuti as b', 'det.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('det.Kode_Karyawan', $kodeKaryawanAuth)
                ->tap($filterApproval)
                ->select([
                    'a.No_Transaksi',
                    'a.Kode_Karyawan',
                    'a.Jenis',
                    'a.Flag_Approval',
                    'a.Id_Jenis_Cuti',
                    'a.Tanggal_Cuti_Dari as Tanggal_Cuti_Dari_Range',
                    'a.Tanggal_Cuti_Sampai as Tanggal_Cuti_Sampai_Range',
                    'det.Tanggal_Cuti as Tanggal_Cuti_Aktual',
                    DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Dari'),
                    DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Sampai'),
                ])
                ->orderBy('det.Tanggal_Cuti')
                ->get();

            $TanggalSakitIzinSudahDipakai = DB::table('Transaksi_Sakit_Izin_Detail as a')
                ->join('Transaksi_Sakit_Izin as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Kode_Karyawan', $kodeKaryawanAuth)
                ->tap($filterApproval)
                ->get();

            $TanggalPulangCepatSudahDipakai = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as a')
                ->join('Transaksi_Terlambat_Pulang_Cepat as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Kode_Karyawan', $kodeKaryawanAuth)
                ->tap($filterApproval)
                ->select([
                    'a.*',
                    DB::raw(
                        'CAST(COALESCE(a.Tanggal_Hari_Kerja, a.Tanggal_Masuk_Pulang) AS DATE) as Tanggal_Aktual_Izin',
                    ),
                ])
                ->get();

            $HariLibur = DB::table('HRIS_Hari_Libur')
                ->get()
                ->map(function ($item) {
                    $item->date = ! empty($item->Tanggal) ? Carbon::parse($item->Tanggal)->format('Y-m-d') : null;
                    $item->description = $item->Nama_Hari_Libur ?? '';

                    return $item;
                });

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $final_page, // Struktur dalam (items) sudah disamakan
                'dataCuti' => $dataCuti,
                'TanggalCuti' => $TanggalCutiSudahDipakai,
                'TanggalSakitIzin' => $TanggalSakitIzinSudahDipakai,
                'TanggalPulangTerlambat' => $TanggalPulangCepatSudahDipakai,
                'HariLibur' => $HariLibur,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    /**
     * Terjemahan Flag_Approval mentah menjadi status tampilan.
     *
     * Isinya dipindahkan ke AturanKedaluwarsaApproval. Sampai perubahan ini,
     * metode ini ADA DUA — di sini dan di IzinAtasanApprovalController — identik
     * 1.209 byte sama persis, sementara jalur yang MENULIS Expired_At membaca
     * konfigurasi yang sama dengan arti yang berlawanan.
     */
    private function parseFlagApproval($flag, $tanggal, $jenis)
    {
        return AturanKedaluwarsaApproval::statusFlag($flag, $tanggal, $jenis);
    }

    /**
     * Map No_Transaksi => status final ('APPROVED'|'REJECTED'|'PENDING'|'EXPIRED'|null)
     * diturunkan dari Dashboard_Status (AllTransactionsReadService) sebagai sumber
     * kebenaran tunggal. Dipakai untuk mencegah salah-tandai "Ditolak/Sistem" hanya
     * karena Expired_At lewat padahal flow approval sudah selesai.
     *
     * @param  array<int,string>  $noTransaksiList
     * @return array<string,string>
     */
    private function buildDashboardStatusMapIzin(array $noTransaksiList): array
    {
        if (empty($noTransaksiList)) {
            return [];
        }

        return (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->statusByNoTransaksi($noTransaksiList)
            ->groupBy('No_Transaksi')
            ->map(function ($rows) {
                $statuses = $rows->map(fn ($r) => strtoupper((string) ($r->Dashboard_Status ?? '')));
                if ($statuses->contains('REJECTED') || $statuses->contains('VOID')) {
                    return 'REJECTED';
                }
                if ($statuses->contains('PENDING') || $statuses->contains('FUTURE')) {
                    return 'PENDING';
                }
                // EXPIRED harus dicek SEBELUM APPROVED: pada alur multi-approver yang
                // sebagian step-nya sudah di-approve tapi step mandatory berikutnya
                // kedaluwarsa (Dashboard_Status campuran APPROVED+EXPIRED), alur TIDAK
                // pernah selesai -> gugur/EXPIRED, bukan "Disetujui". Ini menyelaraskan
                // dengan homepage dashboard (MAX(Dashboard_Status) => 'EXPIRED').
                if ($statuses->contains('EXPIRED')) {
                    return 'EXPIRED';
                }
                if ($statuses->contains('APPROVED')) {
                    return 'APPROVED';
                }

                return null;
            })
            ->all();
    }

    public function getDataIzin(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tipeFilter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $searchColoms = ['Nama', 'No_Transaksi', 'Alasan', 'Tipe_Izin'];
            $tipeFilter = $validated['tipeFilter'] ?? null;
            $tanggalFilter = $validated['tanggalFilter'] ?? null;
            $karyawan_Auth = Auth::user()->karyawan->Kode_Karyawan;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $batasHariPengajuan = config('services.hcis.batas_approve_izin');
            $batasApproveCuti = config('services.hcis.batas_approve_cuti');
            // dd($tipeFilter, $tanggalFilter);

            /*
             * CTE-nya ditulis SEKALI. Dulu ~233 baris ini tersalin ke $baseQuery
             * DAN $queryCount; keduanya cuma berbeda spasi, jadi salinannya murni
             * beban — sekaligus pintu terbuka bagi kueri hitungan untuk menyimpang
             * dari kueri halamannya tanpa ketahuan, yang berarti jumlah halaman
             * berhenti cocok dengan isinya.
             *
             * Versi $baseQuery yang dipakai sebagai kanon: teks SQL kueri halaman
             * tidak berubah sedikit pun. $batasHariPengajuan tetap terurai karena
             * $cte disusun sesudah variabel itu ada.
             */
            $cte = "
               WITH
                        cte AS (
                            -- Query untuk Transaksi_Sakit_Izin_Detail
                            SELECT
                            CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
                            k.Kode_Karyawan,
                            k.Nama,
                            tsi.No_Transaksi,
                            tsi.Jenis as Tipe_Izin,
                            tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
                            tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
                            tsi.Alasan,
                            tsi.filePath as Lampiran,
                            ISNULL(
                                (
                                SELECT
                                    TOP 1 ar.Request_Order_Flow
                                FROM
                                    HRIS_Approval_Request ar
                                WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
                                    AND ar.Flag_Approval IS NOT NULL
                                ORDER BY
                                    ar.Request_Order_Flow DESC
                                ),
                                0
                            ) as currentFlow,
                            ISNULL(
                                (
                                select top 1 b.Request_Order_Flow from HRIS_Approval_Request b where b.No_Transaksi = tsi.No_Transaksi order by b.Request_Order_Flow desc
                                ),
                                0
                            ) as MaxFlow,
                            CASE
                                WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
                                WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
                                WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
                                WHEN tsi.Flag_Approval IS NULL AND DATEDIFF(DAY,CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME), GETDATE()) >= $batasHariPengajuan THEN 'Ditolak'
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 'Diproses'
                                    FROM
                                    HRIS_Approval_Request ar
                                    WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    ar.Request_Order_Flow
                                ),
                                'NO'
                                )
                            END as status,
                            CASE
                                WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
                                WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
                                WHEN tsi.Flag_Approval IS NULL AND DATEDIFF(DAY,CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME), GETDATE()) >= $batasHariPengajuan THEN 'Sistem'
                                WHEN tsi.Flag_Approval = 'T' THEN (
                                select
                                    top 1 ar.Approver_Karyawan_Kode
                                FROM
                                    HRIS_Approval_Request ar
                                WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
                                    AND ar.Flag_Approval = 'T'
                                    --AND af.Status is NULL
                                ORDER BY
                                    ar.Request_Order_Flow
                                )
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 ka.Nama
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN
                                        Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                    WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    ar.Request_Order_Flow
                                ),
                                null
                                )
                            END AS Approver,
                            NULL AS Jam_Mulai,
                            (
                                select
                                STRING_AGG(
                                    CONCAT(
                                    a.Approver_Karyawan_Kode,
                                    ': ',
                                    CASE
                                        WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                        ELSE 'N'
                                    END
                                    ),
                                    ', '
                                ) WITHIN GROUP (
                                    ORDER BY
                                    a.Request_Order_Flow
                                ) AS StatusList
                                from HRIS_Approval_Request a where a.No_Transaksi = tsi.No_Transaksi
                               -- and b.Status is NULL
                            ) AS STATUSLIST
                            FROM
                            Karyawan AS k
                            INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
                            INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
                            WHERE
                            k.Kode_Karyawan = ?
                            and tsip.Status is null
                            UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
                            SELECT
                            CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
                            k.Kode_Karyawan,
                            k.Nama,
                            ttp.No_Transaksi,
                            ttp.Jenis as Tipe_Izin,
                            ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
                            NULL AS Tanggal_Selesai,
                            ttp.Alasan,
                            ttp.filePath as Lampiran,
                            ISNULL(
                                (
                                SELECT
                                    TOP 1 ar.Request_Order_Flow
                                FROM
                                    HRIS_Approval_Request ar
                                WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
                                    AND ar.Flag_Approval IS NOT NULL
                                    --AND af.Status is NULL
                                ORDER BY
                                    ar.Request_Order_Flow DESC
                                ),
                                0
                            ) as currentFlow,
                            ISNULL(
                                (
                                select top 1 b.Request_Order_Flow from HRIS_Approval_Request b where b.No_Transaksi = ttp.No_Transaksi order by b.Request_Order_Flow desc
                                ),
                                0
                            ) as MaxFlow,
                            CASE
                                WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
                                WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
                                WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
                                WHEN ttp.Flag_Approval IS NULL AND DATEDIFF(DAY,CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME), GETDATE()) >= $batasHariPengajuan THEN 'Ditolak'
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 'Diproses'
                                    FROM
                                    HRIS_Approval_Request ar
                                    WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    ar.Request_Order_Flow
                                ),
                                'NO'
                                )
                            END as status,
                            CASE
                                WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
                                WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
                                WHEN ttp.Flag_Approval IS NULL AND DATEDIFF(DAY,CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME), GETDATE()) >= $batasHariPengajuan THEN 'Sistem'
                                WHEN ttp.Flag_Approval = 'T' THEN (
                                select
                                    top 1 ar.Approver_Karyawan_Kode
                                FROM
                                    HRIS_Approval_Request ar
                                WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Flag_Approval = 'T'
                                    --AND af.Status is NULL
                                ORDER BY
                                    ar.Request_Order_Flow
                                )
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 ka.Nama
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN
                                        Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                    WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Flag_Approval IS NULL
                                    ---AND af.Status is NULL
                                    ORDER BY
                                    ar.Request_Order_Flow
                                ),
                                null
                                )
                            END AS Approver,
                            ttp.Jam AS Jam_Mulai,
                            (
                                select
                                STRING_AGG(
                                    CONCAT(
                                    a.Approver_Karyawan_Kode,
                                    ': ',
                                    CASE
                                        WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                        ELSE 'N'
                                    END
                                    ),
                                    ', '
                                ) WITHIN GROUP (
                                    ORDER BY
                                    a.Request_Order_Flow
                                ) AS StatusList
                                from HRIS_Approval_Request a where a.No_Transaksi = ttp.No_Transaksi
                                --and b.Status is NULL
                            ) AS STATUSLIST
                            FROM
                            Karyawan AS k
                            INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
                            INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
                            WHERE
                            k.Kode_Karyawan = ? and ttpp.Status is null
                        )";

            $baseQuery = $cte.'
                        SELECT
                        *
                        FROM
                        cte
                        where
                        MaxFlow <> 0
            ';

            $queryCount = $cte.'
                        SELECT
                        count(No_Transaksi) as total
                        FROM
                        cte
                        where
                        MaxFlow <> 0
            ';

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
                $baseQuery .= " AND ($likeQuery) ";
            }

            if ($tipeFilter) {
                $queryCount .= ' AND Tipe_Izin = ?';
                $baseQuery .= ' AND Tipe_Izin = ?';
                $params[] = $tipeFilter;
            }

            if ($tanggalFilter) {
                $queryCount .= ' AND (
                    cast(? as date) between cast(Tanggal_Mulai as date)
                    and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
                )';
                $baseQuery .= ' AND (
                    cast(? as date) between cast(Tanggal_Mulai as date)
                    and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
                )';

                $params[] = $tanggalFilter;
            }

            $total = DB::selectOne($queryCount, $params)->total;

            $queryData = $baseQuery.' ORDER BY tanggal_create DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';

            $params[] = $offset;
            $params[] = $perPage;

            $result = DB::select($queryData, $params);
            // dd($result);

            // Ambil keterangan penolakan (Catatan dari baris approver yang menolak)
            // untuk seluruh transaksi pada halaman ini dalam satu query.
            $noTransaksiList = collect($result)->pluck('No_Transaksi')->filter()->unique()->values()->all();

            $keteranganPenolakanMap = [];
            if (! empty($noTransaksiList)) {
                $keteranganPenolakanMap = DB::table('HRIS_Approval_Request')
                    ->whereIn('No_Transaksi', $noTransaksiList)
                    ->where('Flag_Approval', 'T')
                    ->orderBy('Tanggal_Approval', 'DESC')
                    ->get(['No_Transaksi', 'Catatan'])
                    ->groupBy('No_Transaksi')
                    ->map(fn ($rows) => $rows->first()->Catatan)
                    ->all();
            }

            // Batch lookup Expired_At dari HRIS_Approval_Request (sumber otoritatif)
            $expiredAtMap = [];
            if (! empty($noTransaksiList)) {
                $expiredAtMap = DB::table('HRIS_Approval_Request')
                    ->whereIn('No_Transaksi', $noTransaksiList)
                    ->whereNotNull('Expired_At')
                    ->get(['No_Transaksi', 'Expired_At'])
                    ->groupBy('No_Transaksi')
                    ->map(fn ($rows) => $rows->first()->Expired_At)
                    ->all();
            }

            // Status final per-transaksi dari SUMBER KEBENARAN TUNGGAL (Dashboard_Status).
            // Hindari salah-tandai "Ditolak/Sistem" hanya karena Expired_At lewat padahal
            // flow approval sudah selesai disetujui/ditolak.
            $dashboardStatusMap = $this->buildDashboardStatusMapIzin($noTransaksiList);

            $dataFinal = collect($result)->map(function ($item) use ($keteranganPenolakanMap, $expiredAtMap, $dashboardStatusMap) {
                $item->Keterangan_Penolakan = $keteranganPenolakanMap[$item->No_Transaksi] ?? null;

                // Tambah expired_at dari DB
                $item->expired_at = $expiredAtMap[$item->No_Transaksi] ?? null;

                // Override status berdasarkan Dashboard_Status (sumber kebenaran tunggal).
                // Hanya tandai "Ditolak/Sistem" (expired) jika flow MEMANG belum selesai.
                $dash = $dashboardStatusMap[$item->No_Transaksi] ?? null;
                if ($dash === 'APPROVED') {
                    $item->status = 'Selesai';
                } elseif ($dash === 'REJECTED') {
                    $item->status = 'Ditolak';
                } elseif ($dash === 'EXPIRED') {
                    $item->status = 'Ditolak';
                    $item->Approver = 'Sistem';
                    $item->currentFlow = $item->MaxFlow ?? $item->currentFlow ?? 0;
                } elseif ($dash === 'PENDING') {
                    // masih diproses; biarkan status dari SQL (Diproses)
                } elseif ($item->expired_at && Carbon::parse($item->expired_at)->isPast()) {
                    // Fallback lama (transaksi tak ada di Dashboard_Status): pakai Expired_At
                    $item->status = 'Ditolak';
                    $item->Approver = 'Sistem';
                    $item->currentFlow = $item->MaxFlow ?? $item->currentFlow ?? 0;
                }

                if (! empty($item->Kode_Karyawan)) {
                    $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                }

                return $item;
            });

            // Collect day-off dates using status-hari function (source of truth).
            $DayOffDates = [];
            try {
                $kodePerusahaan = Auth::user()->karyawan->Kode_Perusahaan ?? '001';
                $startDate = Carbon::now()->startOfYear()->toDateString();
                $endDate = Carbon::now()->endOfYear()->toDateString();
                $cutiBersamaByDate = $this->getCutiBersamaByDateForKaryawan($Kode_Karyawan, $startDate, $endDate);

                // TVF GetStatusHariRange berat & rentang setahun penuh; status-hari
                // deterministik per hari -> cache pendek per (perusahaan, karyawan, rentang).
                $statusHariRows = \Illuminate\Support\Facades\Cache::remember(
                    'statushari.range:'.$kodePerusahaan.':'.$Kode_Karyawan.':'.$startDate.':'.$endDate.':OFF',
                    300,
                    fn () => DB::select(
                        "SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) WHERE StatusHari LIKE 'OFF%'",
                        [$kodePerusahaan, $Kode_Karyawan, $startDate, $endDate],
                    ),
                );

                $DayOffDates = collect($statusHariRows)
                    ->map(function ($row) use ($cutiBersamaByDate) {
                        if (empty($row->Tanggal)) {
                            return null;
                        }

                        $formattedDate = Carbon::parse($row->Tanggal)->format('Y-m-d');
                        $statusHari = $row->StatusHari ?? 'OFF';
                        $normalizedStatus = str_replace('_', '-', (string) $statusHari);
                        $isCutiBersama = stripos($normalizedStatus, 'Cuti-Bersama') !== false;

                        if ($isCutiBersama && ! isset($cutiBersamaByDate[$formattedDate])) {
                            return null;
                        }

                        $description = $isCutiBersama
                            ? $cutiBersamaByDate[$formattedDate]
                            : "Tanggal ini {$statusHari}. Jika perlu izin, ajukan dulu perubahan ke WORK ke atasan.";

                        return [
                            'date' => $formattedDate,
                            'status_hari' => $statusHari,
                            'description' => $description,
                        ];
                    })
                    ->filter()
                    ->unique('date')
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::channel('izinLog')->error('Gagal mengambil DayOffDates: '.$e->getMessage());
            }

            // Konversi lembur -> Day Off yang masih PENDING (menunggu approval) = kandidat libur.
            // Tanggal ini belum jadi OFF di status-hari, jadi di-disable eksplisit di kalender izin.
            $KonversiLemburPending = [];
            try {
                $startDate = Carbon::now()->startOfYear()->toDateString();
                $endDate = Carbon::now()->endOfYear()->toDateString();
                $KonversiLemburPending = app(KonversiLemburService::class)
                    ->getPendingDayOffTargetDates($Kode_Karyawan, $startDate, $endDate);
            } catch (\Throwable $e) {
                Log::channel('izinLog')->error('Gagal mengambil KonversiLemburPending: '.$e->getMessage());
            }

            // Buat paginator
            $final_page = new LengthAwarePaginator($dataFinal, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            $dataCuti = (object) [
                'Sisa_Cuti' => (int) Auth::user()->karyawan->sisa_cuti(),
                'Hutang_Cuti' => (int) Auth::user()->karyawan->hutang_cuti(),
            ];

            // $TanggalCutiSudahDipakai = DB::table('Transaksi_Cuti_Detail as a')
            //     ->join("Transaksi_Cuti as b", "a.No_Transaksi", '=', 'b.No_Transaksi')
            //     ->whereNull("b.Status")
            //     ->where("a.Kode_Karyawan", $Kode_Karyawan)
            //     ->where(DB::raw('DATEDIFF(DAT, CAST(b.Tanggal+ ' ' + b.Jam AS DATETIME), GETDATE())'), '<', 3)
            //     ->where(function ($query) {
            //         $query->where("a.Flag_Approval", "<>", "T")
            //             ->orWhereNull('a.Flag_Approval');
            //     })
            //     ->get();

            if (! $batasApproveCuti) {
                $TanggalCutiSudahDipakai = DB::table('Transaksi_Cuti_Det as det')
                    ->join('Transaksi_Cuti_Detail as a', function ($join) {
                        $join->on('det.No_Transaksi', '=', 'a.No_Transaksi')->on('det.Urut_Detail', '=', 'a.Urut_Oto');
                    })
                    ->join('Transaksi_Cuti as b', 'det.No_Transaksi', '=', 'b.No_Transaksi')
                    ->whereNull('b.Status')
                    ->where('det.Kode_Karyawan', $Kode_Karyawan)
                    ->where(function ($query) {
                        $query->where('a.Flag_Approval', '<>', 'T')->orWhereNull('a.Flag_Approval');
                    })
                    ->select([
                        'a.No_Transaksi',
                        'a.Kode_Karyawan',
                        'a.Jenis',
                        'a.Flag_Approval',
                        'a.Id_Jenis_Cuti',
                        'a.Tanggal_Cuti_Dari as Tanggal_Cuti_Dari_Range',
                        'a.Tanggal_Cuti_Sampai as Tanggal_Cuti_Sampai_Range',
                        'det.Tanggal_Cuti as Tanggal_Cuti_Aktual',
                        DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Dari'),
                        DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Sampai'),
                    ])
                    ->orderBy('det.Tanggal_Cuti')
                    ->get();
            } else {
                $TanggalCutiSudahDipakai = DB::table('Transaksi_Cuti_Det as det')
                    ->join('Transaksi_Cuti_Detail as a', function ($join) {
                        $join->on('det.No_Transaksi', '=', 'a.No_Transaksi')->on('det.Urut_Detail', '=', 'a.Urut_Oto');
                    })
                    ->join('Transaksi_Cuti as b', 'det.No_Transaksi', '=', 'b.No_Transaksi')
                    ->whereNull('b.Status')
                    ->where('det.Kode_Karyawan', $Kode_Karyawan)
                    ->whereRaw(
                        "
                    DATEDIFF(
                        DAY,
                        TRY_CONVERT(
                            DATETIME,
                            CONCAT(
                                CONVERT(
                                    VARCHAR,
                                    CONVERT(DATE, b.Tanggal, 105),
                                    23
                                ),
                                ' ',
                                b.Jam
                            ),
                            120
                        ),
                        GETDATE()
                    ) < $batasApproveCuti
                ",
                    )
                    ->where(function ($query) {
                        $query->where('a.Flag_Approval', '<>', 'T')->orWhereNull('a.Flag_Approval');
                    })
                    ->select([
                        'a.No_Transaksi',
                        'a.Kode_Karyawan',
                        'a.Jenis',
                        'a.Flag_Approval',
                        'a.Id_Jenis_Cuti',
                        'a.Tanggal_Cuti_Dari as Tanggal_Cuti_Dari_Range',
                        'a.Tanggal_Cuti_Sampai as Tanggal_Cuti_Sampai_Range',
                        'det.Tanggal_Cuti as Tanggal_Cuti_Aktual',
                        DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Dari'),
                        DB::raw('CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Cuti_Sampai'),
                    ])
                    ->orderBy('det.Tanggal_Cuti')
                    ->get();
            }

            $TanggalSakitIzinSudahDipakai = DB::table('Transaksi_Sakit_Izin_Detail as a')
                ->join('Transaksi_Sakit_Izin as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Kode_Karyawan', $Kode_Karyawan)
                // ->whereRaw("
                //     DATEDIFF(
                //         DAY,
                //         CAST(CONCAT(b.Tanggal, ' ', b.Jam) AS DATETIME),
                //         GETDATE()
                //     ) < 3
                // ")
                ->whereRaw(
                    "
                    DATEDIFF(
                        DAY,
                        TRY_CONVERT(
                            DATETIME,
                            CONCAT(
                                CONVERT(
                                    VARCHAR,
                                    CONVERT(DATE, b.Tanggal, 105),
                                    23
                                ),
                                ' ',
                                b.Jam
                            ),
                            120
                        ),
                        GETDATE()
                    ) < $batasHariPengajuan
                ",
                )

                ->where(function ($query) {
                    $query->where('a.Flag_Approval', '<>', 'T')->orWhereNull('a.Flag_Approval');
                })
                ->get();

            $TanggalPulangCepatSudahDipakai = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as a')
                ->join('Transaksi_Terlambat_Pulang_Cepat as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Kode_Karyawan', $Kode_Karyawan)
                // ->whereRaw("
                //     DATEDIFF(
                //         DAY,
                //         CAST(CONCAT(b.Tanggal, ' ', b.Jam) AS DATETIME),
                //         GETDATE()
                //     ) < 3
                // ")
                ->whereRaw(
                    "
                    DATEDIFF(
                        DAY,
                        TRY_CONVERT(
                            DATETIME,
                            CONCAT(
                                CONVERT(
                                    VARCHAR,
                                    CONVERT(DATE, b.Tanggal, 105),
                                    23
                                ),
                                ' ',
                                b.Jam
                            ),
                            120
                        ),
                        GETDATE()
                    ) < $batasHariPengajuan
                ",
                )

                ->where(function ($query) {
                    $query->where('a.Flag_Approval', '<>', 'T')->orWhereNull('a.Flag_Approval');
                })
                ->select([
                    'a.*',
                    DB::raw(
                        'CAST(COALESCE(a.Tanggal_Hari_Kerja, a.Tanggal_Masuk_Pulang) AS DATE) as Tanggal_Aktual_Izin',
                    ),
                ])
                ->get();

            $HariLibur = collect(DB::table('HRIS_Hari_Libur')->get())->map(function ($item) {
                if (! empty($item->Tanggal)) {
                    $item->date = Carbon::parse($item->Tanggal)->format('Y-m-d');
                }
                if (! empty($item->Nama_Hari_Libur)) {
                    $item->description = $item->Nama_Hari_Libur;
                }

                return $item;
            });

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $final_page,
                'dataCuti' => $dataCuti,
                'TanggalCuti' => $TanggalCutiSudahDipakai,
                'TanggalSakitIzin' => $TanggalSakitIzinSudahDipakai,
                'TanggalPulangTerlambat' => $TanggalPulangCepatSudahDipakai,
                'HariLibur' => $HariLibur,
                'DayOffDates' => $DayOffDates,
                'KonversiLemburPending' => $KonversiLemburPending,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzin: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan, coba lagi beberapa saat...',
                ],
                404,
            );
        }
    }

    public function getExportDH(Request $request)
    {
        try {
            $start = Carbon::parse($request->start)->startOfDay(); // 2025-08-19 00:00:00
            $end = Carbon::parse($request->end); // 2025-08-19 23:59:59

            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $query = "
                WITH BaseData_Terpulang AS (
                    SELECT
                        CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                        d.Kode_Karyawan AS Approver_Kode_Karyawan,
                        (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                        g.ID_Divisi AS Division_ID_From_View,
                        k.ID_Level AS Level_ID_From_View,
                        d.id AS Flow_Id,
                        d.order_flow,
                        e.No_Transaksi,
                        e.Flag_Approval AS Current_Flag_Approval,
                        f.Kode_Karyawan AS Requester_Kode_Karyawan,
                        f.Jenis AS Request_Jenis,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'Disetujui'
                            WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                            WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 'Diproses'
                                    FROM HRIS_Approval_Request ar
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY ar.Request_Order_Flow
                                ),
                                'NO'
                            )
                        END as status,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'Disetujui'
                            WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                            WHEN f.Flag_Approval = 'T' THEN (
                                select top 1 a.Nama
                                FROM HRIS_Approval_Request ar
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Flag_Approval = 'T'
                                ORDER BY ar.Request_Order_Flow
                            )
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 ka.Nama
                                    FROM HRIS_Approval_Request ar
                                    JOIN Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY ar.Request_Order_Flow
                                ),
                                null
                            )
                        END AS Approver,
                        format(f.Tanggal_Masuk_Pulang, 'dd MMMM yyyy') + ' ' + LEFT(CONVERT(varchar, CAST(f.Jam AS time), 108), 5) AS TanggalIzin,
                        f.filePath,
                        f.Alasan AS Request_Alasan,
                        ISNULL(
                            (
                                SELECT TOP 1 ar.Request_Order_Flow
                                FROM HRIS_Approval_Request ar
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Flag_Approval IS NOT NULL
                                ORDER BY ar.Request_Order_Flow DESC
                            ),
                            0
                        ) as currentFlow,
                        ISNULL(
                            (
                                select top 1 order_flow
                                from HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where a.id = b.Flow_Id
                                and b.No_Transaksi = f.No_Transaksi
                                order by a.order_flow desc
                            ),
                            0
                        ) as MaxFlow,
                        (
                            select STRING_AGG(
                                CONCAT(b.Kode_Karyawan, ': ',
                                    CASE WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval ELSE 'N' END),
                                ', '
                            ) WITHIN GROUP (ORDER BY b.order_flow) AS StatusList
                            from HRIS_Approval_Request a
                            JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                            where a.No_Transaksi = f.No_Transaksi
                            AND a.Kode_Karyawan = b.Kode_Karyawan_Requester
                        ) AS STATUSLIST,

                        -- START / END sebagai DATETIME (untuk overlap)
                        CAST(f.Tanggal_Masuk_Pulang AS DATETIME) AS TanggalIzin_Start,
                        CAST(f.Tanggal_Masuk_Pulang AS DATETIME) AS TanggalIzin_End
                    FROM
                        Karyawan AS a
                        INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                        INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                        INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                        INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS f ON e.No_Transaksi = f.No_Transaksi and d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                        d.Kode_Karyawan = ?
                        and fa.Status is null
                ),
                BaseData_Cuti AS (
                    SELECT
                        CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                        d.Kode_Karyawan AS Approver_Kode_Karyawan,
                        (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                        g.ID_Divisi AS Division_ID_From_View,
                        k.ID_Level AS Level_ID_From_View,
                        d.id AS Flow_Id,
                        d.order_flow,
                        e.No_Transaksi,
                        e.Flag_Approval AS Current_Flag_Approval,
                        f.Kode_Karyawan AS Requester_Kode_Karyawan,
                        f.Jenis AS Request_Jenis,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'Selesai'
                            WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                            WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 'Diproses'
                                    FROM HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY af.order_flow
                                ),
                                'NO'
                            )
                        END as status,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'DiSetujui'
                            WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                            WHEN f.Flag_Approval = 'T' THEN (
                                select top 1 a.Nama
                                FROM HRIS_Approval_Request ar
                                JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                AND ar.Flag_Approval = 'T'
                                ORDER BY af.order_flow
                            )
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 ka.Nama
                                    FROM HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    JOIN Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY af.order_flow
                                ),
                                null
                            )
                        END AS Approver,
                        CASE
                            WHEN FORMAT(f.Tanggal_Cuti_Dari, 'dd MMMM yyyy') = FORMAT(f.Tanggal_Cuti_Sampai, 'dd MMMM yyyy')
                            THEN FORMAT(f.Tanggal_Cuti_Dari, 'dd MMMM yyyy')
                            ELSE FORMAT(f.Tanggal_Cuti_Dari, 'dd') + '-' + FORMAT(f.Tanggal_Cuti_Sampai, 'dd MMMM yyyy')
                        END AS TanggalIzin,
                        f.filePath,
                        f.Alasan AS Request_Alasan,
                        ISNULL(
                            (
                                SELECT TOP 1 af.order_flow
                                FROM HRIS_Approval_Request ar
                                JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                AND ar.Flag_Approval IS NOT NULL
                                ORDER BY af.order_flow DESC
                            ),
                            0
                        ) as currentFlow,
                        ISNULL(
                            (
                                select top 1 order_flow
                                from HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where a.id = b.Flow_Id
                                and b.No_Transaksi = f.No_Transaksi
                                and a.Kode_Karyawan_Requester = b.Kode_Karyawan
                                order by a.order_flow desc
                            ),
                            0
                        ) as MaxFlow,
                        (
                            select STRING_AGG(
                                CONCAT(b.Kode_Karyawan, ': ',
                                    CASE WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval ELSE 'N' END),
                                ', '
                            ) WITHIN GROUP (ORDER BY b.order_flow) AS StatusList
                            from HRIS_Approval_Request a
                            JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                            where a.No_Transaksi = f.No_Transaksi
                            and a.Kode_Karyawan = b.Kode_Karyawan_Requester
                        ) AS STATUSLIST,

                        -- START/END cuti sebagai DATETIME
                        CAST(f.Tanggal_Cuti_Dari AS DATETIME) AS TanggalIzin_Start,
                        CAST(ISNULL(f.Tanggal_Cuti_Sampai, f.Tanggal_Cuti_Dari) AS DATETIME) AS TanggalIzin_End
                    FROM
                        Karyawan AS a
                        INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                        INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                        INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                        INNER JOIN Transaksi_Cuti_Detail AS f ON e.No_Transaksi = f.No_Transaksi
                        INNER JOIN Transaksi_Cuti AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                        d.Kode_Karyawan = ?
                        and fa.Status is null
                ),
                BaseData_Sakit AS (
                    SELECT
                        CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                        d.Kode_Karyawan AS Approver_Kode_Karyawan,
                        (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                        g.ID_Divisi AS Division_ID_From_View,
                        k.ID_Level AS Level_ID_From_View,
                        d.id AS Flow_Id,
                        d.order_flow,
                        e.No_Transaksi,
                        e.Flag_Approval AS Current_Flag_Approval,
                        f.Kode_Karyawan AS Requester_Kode_Karyawan,
                        f.Jenis AS Request_Jenis,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'Selesai'
                            WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                            WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 'Diproses'
                                    FROM HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY af.order_flow
                                ),
                                'NO'
                            )
                        END as status,
                        CASE
                            WHEN f.Flag_Approval = 'Y' THEN 'DiSetujui'
                            WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                            WHEN f.Flag_Approval = 'T' THEN (
                                select top 1 a.Nama
                                FROM HRIS_Approval_Request ar
                                JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                AND ar.Flag_Approval = 'T'
                                ORDER BY af.order_flow
                            )
                            ELSE ISNULL(
                                (
                                    SELECT TOP 1 ka.Nama
                                    FROM HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    JOIN Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
                                    WHERE ar.No_Transaksi = f.No_Transaksi
                                    AND ar.Kode_Karyawan  = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    ORDER BY af.order_flow
                                ),
                                null
                            )
                        END AS Approver,
                        CASE
                            WHEN FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd MMMM yyyy') = FORMAT(f.Tanggal_Sakit_Izin_Sampai, 'dd MMMM yyyy')
                            THEN FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd MMMM yyyy')
                            ELSE FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd') + '-' + FORMAT(f.Tanggal_Sakit_Izin_Sampai, 'dd MMMM yyyy')
                        END AS TanggalIzin,
                        f.filePath,
                        f.Alasan AS Request_Alasan,
                        ISNULL(
                            (
                                SELECT TOP 1 af.order_flow
                                FROM HRIS_Approval_Request ar
                                JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE ar.No_Transaksi = f.No_Transaksi
                                AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                AND ar.Flag_Approval IS NOT NULL
                                ORDER BY af.order_flow DESC
                            ),
                            0
                        ) as currentFlow,
                        ISNULL(
                            (
                                select top 1 order_flow
                                from HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where a.id = b.Flow_Id
                                and b.No_Transaksi = f.No_Transaksi
                                and a.Kode_Karyawan_Requester = b.Kode_Karyawan
                                order by a.order_flow desc
                            ),
                            0
                        ) as MaxFlow,
                        (
                            select STRING_AGG(
                                CONCAT(b.Kode_Karyawan, ': ',
                                    CASE WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval ELSE 'N' END),
                                ', '
                            ) WITHIN GROUP (ORDER BY b.order_flow) AS StatusList
                            from HRIS_Approval_Request a
                            JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                            where a.No_Transaksi = f.No_Transaksi
                            AND a.Kode_Karyawan = b.Kode_Karyawan_Requester
                        ) AS STATUSLIST,

                        -- START/END sakit sebagai DATETIME
                        CAST(f.Tanggal_Sakit_Izin_Dari AS DATETIME) AS TanggalIzin_Start,
                        CAST(ISNULL(f.Tanggal_Sakit_Izin_Sampai, f.Tanggal_Sakit_Izin_Dari) AS DATETIME) AS TanggalIzin_End
                    FROM
                        Karyawan AS a
                        INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                        INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                        INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                        INNER JOIN Transaksi_Sakit_Izin_Detail AS f ON e.No_Transaksi = f.No_Transaksi and d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN Transaksi_Sakit_Izin AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                        d.Kode_Karyawan = ?
                        and fa.Status is null
                ),
                ApprovalStepValidation AS (
                    SELECT
                        bd.*,
                        TRY_CAST(bd.order_flow AS INT) AS Numeric_Order_Flow,
                        PrevApproval.Flag_Approval AS Previous_Step_Flag_Approval
                    FROM
                        (SELECT * FROM BaseData_Terpulang
                        UNION ALL
                        SELECT * FROM BaseData_Sakit
                        UNION ALL
                        SELECT * FROM BaseData_Cuti
                        ) AS bd
                        LEFT JOIN HRIS_Approval_Flow AS prev_flow
                            ON prev_flow.Kode_Karyawan_Requester = bd.Requester_Kode_Karyawan
                            AND prev_flow.order_flow = TRY_CAST(bd.order_flow AS INT) - 1
                            AND TRY_CAST(bd.order_flow AS INT) > 1
                        LEFT JOIN HRIS_Approval_Request AS PrevApproval
                            ON PrevApproval.Flow_Id = prev_flow.id
                            AND PrevApproval.No_Transaksi = bd.No_Transaksi
                )
                SELECT
                    apv.tanggal_create,
                    apv.No_Transaksi,
                    apv.Approver_Kode_Karyawan,
                    apv.requester_Nama,
                    apv.Requester_Kode_Karyawan,
                    apv.Request_Jenis as Tipe_Izin,
                    apv.Request_Alasan as Alasan,
                    apv.TanggalIzin,
                    apv.filePath as Lampiran,
                    apv.STATUSLIST,
                    apv.MaxFlow,
                    apv.currentFlow,
                    apv.status,
                    apv.Approver,
                    apv.Current_Flag_Approval AS Status_Approval_Saat_Ini,
                    apv.Previous_Step_Flag_Approval AS Status_Approval_Langkah_Sebelumnya,
                    apv.order_flow
                FROM ApprovalStepValidation AS apv
                WHERE
                    apv.Approver_Kode_Karyawan = ?
                    AND (
                        apv.Numeric_Order_Flow = 1
                        OR (
                            apv.Numeric_Order_Flow > 1
                            AND apv.Previous_Step_Flag_Approval = 'Y'
                        )
                    )

                    -- overlap check yang lebih robust:
                                AND (
                    CAST(apv.TanggalIzin_Start AS DATETIME) <= CAST(DATEADD(SECOND, 86399, ? ) AS DATETIME) -- filter_end + 23:59:59
                    AND CAST(apv.TanggalIzin_End   AS DATETIME) >= CAST(? AS DATETIME)  -- filter_start
                )

                ORDER BY apv.tanggal_create desc
            ";

            $params = [
                $Kode_Karyawan, // BaseData_Terpulang WHERE d.Kode_Karyawan = ?
                $Kode_Karyawan, // BaseData_Cuti   WHERE d.Kode_Karyawan = ?
                $Kode_Karyawan, // BaseData_Sakit  WHERE d.Kode_Karyawan = ?
                $Kode_Karyawan, // main WHERE apv.Approver_Kode_Karyawan = ?

                $end, // untuk CAST(? AS DATETIME) di kondisi pertama (apv.TanggalIzin_Start <= ?)
                $start, // untuk CAST(? AS DATETIME) di kondisi kedua (apv.TanggalIzin_End >= ?)
            ];

            $result = DB::select($query, $params);
            $dataFinal = collect($result)->map(function ($item) {
                if (! empty($item->Approver_Kode_Karyawan)) {
                    $item->Approver_Kode_Karyawan = Crypt::encryptString($item->Approver_Kode_Karyawan);
                }
                if (! empty($item->Requester_Kode_Karyawan)) {
                    $item->Requester_Kode_Karyawan = Crypt::encryptString($item->Requester_Kode_Karyawan);
                }

                return $item;
            });

            // dd($dataFinal);
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $dataFinal,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzinDH'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    public function getExport(Request $request)
    {
        try {
            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $query = KueriExportIzin::sql(false);
            $result = DB::select($query, [$Kode_Karyawan, $Kode_Karyawan, $Kode_Karyawan, $start, $end, $end, $start]);
            $dataFinal = collect($result)->map(function ($item) {
                if (! empty($item->Kode_Karyawan)) {
                    $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                }

                return $item;
            });

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $dataFinal,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    public function getExportAdmin(Request $request)
    {
        try {
            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $query = KueriExportIzin::sql(true);
            $result = DB::select($query, [$start, $end, $end, $start]);
            $dataFinal = collect($result)->map(function ($item) {
                if (! empty($item->Kode_Karyawan)) {
                    $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                }

                return $item;
            });

            // dd($dataFinal);
            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $dataFinal,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    /**
     * Peta tanggal => judul cuti bersama untuk kalender karyawan.
     *
     * Isinya dipindahkan ke KalenderCutiBersama. Sampai perubahan ini metode ini
     * ADA DUA — di sini dan di uDashController — identik 1.174 byte sama persis,
     * dan keduanya memberi makan kalender yang dilihat karyawan.
     */
    private function getCutiBersamaByDateForKaryawan(string $kodeKaryawan, string $rangeStart, string $rangeEnd): array
    {
        return app(KalenderCutiBersama::class)->untukKaryawan($kodeKaryawan, $rangeStart, $rangeEnd);
    }

    public function getDataIzinDH(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tipeFilter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $searchColoms = ['apv.requester_Nama', 'apv.No_Transaksi', 'apv.Request_Jenis', 'apv.Request_Alasan'];
            $tipeFilter = $validated['tipeFilter'] ?? null;
            $tanggalFilter = $validated['tanggalFilter'] ?? null;
            $karyawan_Auth = Auth::user()->karyawan->Kode_Karyawan;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            /*
             * CTE-nya ditulis SEKALI. Dulu 413 baris ini tersalin utuh ke
             * $baseQuery DAN $queryCount, identik byte per byte, jadi tiap
             * koreksi harus dikerjakan dua kali — dan kueri hitungan bisa
             * diam-diam berbeda dari kueri halamannya, yang berarti jumlah
             * halaman tidak lagi cocok dengan isinya.
             *
             * Yang berbeda antara keduanya cuma daftar SELECT-nya.
             */
            $cte = "
                WITH BaseData_Terpulang AS (
                SELECT
                    CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                    d.Kode_Karyawan AS Approver_Kode_Karyawan,
                    (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                    g.ID_Divisi AS Division_ID_From_View,
                    k.ID_Level AS Level_ID_From_View,
                    d.id AS Flow_Id,
                    d.order_flow,
                    e.No_Transaksi,
                    e.Flag_Approval AS Current_Flag_Approval,
                    f.Kode_Karyawan AS Requester_Kode_Karyawan,
                    f.Jenis AS Request_Jenis,
                    CASE
                                    WHEN f.Flag_Approval = 'Y' THEN 'Disetujui'
                                    WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                                    WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                                    ELSE ISNULL(
                                    (
                                        SELECT
                                        TOP 1 'Diproses'
                                        FROM
                                        HRIS_Approval_Request ar
                                        WHERE
                                        ar.No_Transaksi = f.No_Transaksi
                                        --AND af.Status is Null
                                        AND ar.Flag_Approval IS NULL
                                        ORDER BY
                                        ar.Request_Order_Flow
                                    ),
                                    'NO'
                                    )
                                END as status,
                                CASE
                                    WHEN f.Flag_Approval = 'Y' THEN 'Disetujui'
                                    WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                                    WHEN f.Flag_Approval = 'T' THEN (
                                    select
                                        top 1 a.Nama
                                    FROM
                                        HRIS_Approval_Request ar
                                    WHERE
                                        ar.No_Transaksi = f.No_Transaksi
                                        --AND af.Status is NULL
                                        AND ar.Flag_Approval = 'T'
                                    ORDER BY
                                        ar.Request_Order_Flow
                                    )
                                    ELSE ISNULL(
                                    (
                                        SELECT
                                        TOP 1 ka.Nama
                                        FROM
                                        HRIS_Approval_Request ar
                                        JOIN Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                        WHERE
                                        ar.No_Transaksi = f.No_Transaksi
                                        AND ar.Flag_Approval IS NULL
                                        --AND af.Status is NULL
                                        ORDER BY
                                        ar.Request_Order_Flow
                                    ),
                                    null
                                    )
                                END AS Approver,
                    format(f.Tanggal_Masuk_Pulang, 'dd MMMM yyyy') + ' ' +  LEFT(CONVERT(varchar, CAST(f.Jam AS time), 108), 5) AS TanggalIzin,
                    f.filePath,

                    f.Alasan AS Request_Alasan,
                    ISNULL(
                                    (
                                    SELECT
                                        TOP 1 ar.Request_Order_Flow
                                    FROM
                                        HRIS_Approval_Request ar
                                    WHERE
                                        ar.No_Transaksi = f.No_Transaksi
                                        --AND af.Status is NULL

                                        AND ar.Flag_Approval IS NOT NULL
                                    ORDER BY
                                        ar.Request_Order_Flow DESC
                                    ),
                                    0
                                ) as currentFlow,
                                ISNULL(
                                    (
                                    select
                                        top 1 b.Request_Order_Flow
                                    from
                                        HRIS_Approval_Request b
                                    where
                                        b.No_Transaksi = f.No_Transaksi
                                    order by
                                        b.Request_Order_Flow desc
                                    ),
                                    0
                                ) as MaxFlow,
                    (
                                    select
                                    STRING_AGG(
                                        CONCAT(
                                    a.Approver_Karyawan_Kode,
                                        ': ',
                                        CASE
                                            WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                            ELSE 'N'
                                        END
                                        ),
                                        ', '
                                    ) WITHIN GROUP (
                                        ORDER BY
                                        a.Request_Order_Flow
                                    ) AS StatusList
                                    from HRIS_Approval_Request a where a.No_Transaksi = f.No_Transaksi
                                    --AND b.Status is NULL
                                ) AS STATUSLIST
                FROM
                    Karyawan AS a

                    INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                    INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                    INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                    INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                    INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS f ON e.No_Transaksi = f.No_Transaksi and d.Kode_Karyawan = a.Kode_Karyawan
                    INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                    d.Kode_Karyawan = ?
                    and fa.Status is null
                    --and d.Status is NULL
                ),
                BaseData_Cuti AS (
                    SELECT
                        CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                        d.Kode_Karyawan AS Approver_Kode_Karyawan,
                        (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                        g.ID_Divisi AS Division_ID_From_View,
                        k.ID_Level AS Level_ID_From_View,
                        d.id AS Flow_Id,
                        d.order_flow,
                        e.No_Transaksi,
                        e.Flag_Approval AS Current_Flag_Approval,
                        f.Kode_Karyawan AS Requester_Kode_Karyawan,

                        f.Jenis AS Request_Jenis,
                        CASE
                                        WHEN f.Flag_Approval = 'Y' THEN 'Selesai'
                                        WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                                        WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                                        ELSE ISNULL(
                                        (
                                            SELECT
                                            TOP 1 'Diproses'
                                            FROM
                                            HRIS_Approval_Request ar
                                            WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            --AND af.Status is NULL
                                            AND ar.Flag_Approval IS NULL
                                            ORDER BY
                                            ar.Request_Order_Flow
                                        ),
                                        'NO'
                                        )
                                    END as status,
                                    CASE
                                        WHEN f.Flag_Approval = 'Y' THEN 'DiSetujui'
                                        WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                                        WHEN f.Flag_Approval = 'T' THEN (
                                        select
                                            top 1 a.Nama
                                        FROM
                                            HRIS_Approval_Request ar
                                        WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            --AND af.Status is NULL
                                            AND ar.Flag_Approval = 'T'
                                        ORDER BY
                                            ar.Request_Order_Flow
                                        )
                                        ELSE ISNULL(
                                        (
                                            SELECT
                                            TOP 1 ka.Nama
                                            FROM
                                            HRIS_Approval_Request ar
                                            JOIN Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                            WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            AND ar.Flag_Approval IS NULL
                                            --AND af.Status is NULL
                                            ORDER BY
                                            ar.Request_Order_Flow
                                        ),
                                        null
                                        )
                                    END AS Approver,
                        CASE
                            WHEN FORMAT(f.Tanggal_Cuti_Dari, 'dd MMMM yyyy') = FORMAT(f.Tanggal_Cuti_Sampai, 'dd MMMM yyyy')
                            THEN FORMAT(f.Tanggal_Cuti_Dari, 'dd MMMM yyyy')
                            ELSE
                                FORMAT(f.Tanggal_Cuti_Dari, 'dd') + '-' + FORMAT(f.Tanggal_Cuti_Sampai, 'dd MMMM yyyy')
                        END AS TanggalIzin,
                        f.filePath,
                        f.Alasan AS Request_Alasan,
                        ISNULL(
                                        (
                                        SELECT
                                            TOP 1 ar.Request_Order_Flow
                                        FROM
                                            HRIS_Approval_Request ar
                                        WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            --AND af.Status is NULL
                                            AND ar.Flag_Approval IS NOT NULL
                                        ORDER BY
                                            ar.Request_Order_Flow DESC
                                        ),
                                        0
                                    ) as currentFlow,
                                    ISNULL(
                                        (
                                        select top 1 b.Request_Order_Flow from HRIS_Approval_Request b where b.No_Transaksi = f.No_Transaksi order by b.Request_Order_Flow desc
                                        ),
                                        0
                                    ) as MaxFlow,
                        (
                                        select
                                        STRING_AGG(
                                            CONCAT(
                                    a.Approver_Karyawan_Kode,
                                            ': ',
                                            CASE
                                                WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                                ELSE 'N'
                                            END
                                            ),
                                            ', '
                                        ) WITHIN GROUP (
                                            ORDER BY
                                            a.Request_Order_Flow
                                        ) AS StatusList
                                        from HRIS_Approval_Request a where a.No_Transaksi = f.No_Transaksi
                                        --and b.Status is NULL
                                    ) AS STATUSLIST
                    FROM
                        Karyawan AS a

                        INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                        INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                        INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                        INNER JOIN Transaksi_Cuti_Detail AS f ON e.No_Transaksi = f.No_Transaksi
                        INNER JOIN Transaksi_Cuti AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                        d.Kode_Karyawan = ?
                        and fa.Status is null
                    --and d.Status is NULL

                ),
                BaseData_Sakit AS (
                    SELECT
                        CAST(CONCAT(CAST(fa.Tanggal AS DATE),' ', fa.Jam) AS DATETIME) AS tanggal_create,
                        d.Kode_Karyawan AS Approver_Kode_Karyawan,
                        (select Nama from Karyawan where Kode_Karyawan = f.Kode_Karyawan) as requester_Nama,
                        g.ID_Divisi AS Division_ID_From_View,
                        k.ID_Level AS Level_ID_From_View,
                        d.id AS Flow_Id,
                        d.order_flow,
                        e.No_Transaksi,
                        e.Flag_Approval AS Current_Flag_Approval,
                        f.Kode_Karyawan AS Requester_Kode_Karyawan,

                        f.Jenis AS Request_Jenis,
                        CASE
                                        WHEN f.Flag_Approval = 'Y' THEN 'Selesai'
                                        WHEN f.Flag_Approval = 'T' THEN 'Ditolak'
                                        WHEN f.Flag_Approval = 'P' THEN 'Ditolak'
                                        ELSE ISNULL(
                                        (
                                            SELECT
                                            TOP 1 'Diproses'
                                            FROM
                                            HRIS_Approval_Request ar
                                            WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            --AND af.Status is NULL
                                            AND ar.Flag_Approval IS NULL
                                            ORDER BY
                                            ar.Request_Order_Flow
                                        ),
                                        'NO'
                                        )
                                    END as status,
                                    CASE
                                        WHEN f.Flag_Approval = 'Y' THEN 'DiSetujui'
                                        WHEN f.Flag_Approval = 'P' THEN 'Sistem'
                                        WHEN f.Flag_Approval = 'T' THEN (
                                        select
                                            top 1 a.Nama
                                        FROM
                                            HRIS_Approval_Request ar
                                        WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            --AND af.Status is NULL
                                            AND ar.Flag_Approval = 'T'
                                        ORDER BY
                                            ar.Request_Order_Flow
                                        )
                                        ELSE ISNULL(
                                        (
                                            SELECT
                                            TOP 1 ka.Nama
                                            FROM
                                            HRIS_Approval_Request ar
                                            JOIN Karyawan ka ON ar.Approver_Karyawan_Kode = ka.Kode_Karyawan
                                            WHERE
                                            ar.No_Transaksi = f.No_Transaksi
                                            AND ar.Flag_Approval IS NULL
                                            --AND af.Status is NULL
                                            ORDER BY
                                            ar.Request_Order_Flow
                                        ),
                                        null
                                        )
                                    END AS Approver,
                        CASE
                            WHEN FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd MMMM yyyy') = FORMAT(f.Tanggal_Sakit_Izin_Sampai, 'dd MMMM yyyy')
                            THEN FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd MMMM yyyy')
                            ELSE
                                FORMAT(f.Tanggal_Sakit_Izin_Dari, 'dd') + '-' + FORMAT(f.Tanggal_Sakit_Izin_Sampai, 'dd MMMM yyyy')
                        END AS TanggalIzin,
                        f.filePath,
                        f.Alasan AS Request_Alasan,
                        ISNULL(
                                        (
                                        SELECT
                                            TOP 1 ar.Request_Order_Flow
                                        FROM
                                            HRIS_Approval_Request ar
                                        WHERE
                                            ar.No_Transaksi = f.No_Transaksi

                                            AND ar.Flag_Approval IS NOT NULL
                                            --AND af.Status is NULL
                                        ORDER BY
                                            ar.Request_Order_Flow DESC
                                        ),
                                        0
                                    ) as currentFlow,
                                    ISNULL(
                                        (
                                        select top 1 b.Request_Order_Flow from HRIS_Approval_Request b where b.No_Transaksi = f.No_Transaksi order by b.Request_Order_Flow desc
                                        ),
                                        0
                                    ) as MaxFlow,
                        (
                                        select
                                        STRING_AGG(
                                            CONCAT(
                                    a.Approver_Karyawan_Kode,
                                            ': ',
                                            CASE
                                                WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                                ELSE 'N'
                                            END
                                            ),
                                            ', '
                                        ) WITHIN GROUP (
                                            ORDER BY
                                            a.Request_Order_Flow
                                        ) AS StatusList
                                        from HRIS_Approval_Request a where a.No_Transaksi = f.No_Transaksi
                                        --AND b.Status is NULL
                                    ) AS STATUSLIST
                    FROM
                        Karyawan AS a

                        INNER JOIN HRIS_Approval_Flow AS d ON d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN View_Divisi_Sub_Divisi AS g ON g.ID_DIVISI_SUB_DIVISI  = a.ID_Divisi_Sub_Divisi
                        INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan AS k ON k.ID_Level_Jabatan = a.ID_Level_Jabatan
                        INNER JOIN HRIS_Approval_Request AS e ON d.id = e.Flow_Id
                        INNER JOIN Transaksi_Sakit_Izin_Detail AS f ON e.No_Transaksi = f.No_Transaksi and d.Kode_Karyawan = a.Kode_Karyawan
                        INNER JOIN Transaksi_Sakit_Izin AS fa ON fa.No_Transaksi = f.No_Transaksi
                    WHERE
                        d.Kode_Karyawan = ?
                        and fa.Status is null
                        --and d.Status is null

                ),
                ApprovalStepValidation AS (
                    SELECT
                        bd.*,
                        TRY_CAST(bd.order_flow AS INT) AS Numeric_Order_Flow,
                        PrevApproval.Flag_Approval AS Previous_Step_Flag_Approval
                    FROM
                        (SELECT * FROM BaseData_Terpulang
                        UNION ALL
                        SELECT * FROM BaseData_Sakit
                        UNION ALL
                        SELECT * FROM BaseData_Cuti
                        ) AS bd
                        LEFT JOIN HRIS_Approval_Flow AS prev_flow
                            ON prev_flow.Kode_Karyawan_Requester = bd.Requester_Kode_Karyawan AND
                            prev_flow.order_flow = TRY_CAST(bd.order_flow AS INT) - 1
                            AND TRY_CAST(bd.order_flow AS INT) > 1
                        LEFT JOIN HRIS_Approval_Request AS PrevApproval
                            ON PrevApproval.Flow_Id = prev_flow.id
                            AND PrevApproval.No_Transaksi = bd.No_Transaksi
                            --AND prev_flow.Status is NULL
                )
                -- Query utama untuk menampilkan hasil
                SELECT";

            $baseQuery = $cte."
                    apv.tanggal_create,
                    apv.No_Transaksi,
                    apv.Approver_Kode_Karyawan,
                    apv.requester_Nama,
                    apv.Requester_Kode_Karyawan,
                    apv.Request_Jenis as Tipe_Izin,
                    apv.Request_Alasan as Alasan,
                    apv.TanggalIzin,
                    apv.filePath as Lampiran,
                    apv.STATUSLIST,
                    apv.MaxFlow,
                    apv.currentFlow,
                    apv.status,
                    apv.Approver,
                    apv.Current_Flag_Approval AS Status_Approval_Saat_Ini,
                    apv.Previous_Step_Flag_Approval AS Status_Approval_Langkah_Sebelumnya,
                    apv.order_flow
                FROM
                    ApprovalStepValidation AS apv
                WHERE
                    apv.Approver_Kode_Karyawan = ?

                    AND (
                        apv.Numeric_Order_Flow = 1
                        OR (
                            apv.Numeric_Order_Flow > 1
                            AND  apv.Previous_Step_Flag_Approval = 'Y'

                        )
                    )

            ";

            $queryCount = $cte."
                    count(apv.No_Transaksi) as total

                FROM
                    ApprovalStepValidation AS apv
                WHERE
                    apv.Approver_Kode_Karyawan = ?

                    AND (
                        apv.Numeric_Order_Flow = 1
                        OR (
                            apv.Numeric_Order_Flow > 1
                            AND  apv.Previous_Step_Flag_Approval = 'Y'

                        )
                    )

            ";

            $params = [];
            $params[] = $karyawan_Auth;
            $params[] = $karyawan_Auth;
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
                $baseQuery .= " AND ($likeQuery) ";
            }

            if ($tipeFilter) {
                $queryCount .= ' AND apv.Request_Jenis = ?';
                $baseQuery .= ' AND apv.Request_Jenis = ?';
                $params[] = $tipeFilter;
            }

            if ($tanggalFilter) {
                $sqlParse = " AND CAST(? AS datetime) BETWEEN
                (
                CASE WHEN apv.TanggalIzin LIKE '%-%' THEN
                    TRY_PARSE(
                    LTRIM(RTRIM(
                        LEFT(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) - 1)
                        + ' ' +
                        CASE
                        WHEN CHARINDEX(' ', LTRIM(RTRIM(SUBSTRING(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) + 1, LEN(apv.TanggalIzin))))) > 0
                        THEN SUBSTRING(
                                LTRIM(RTRIM(SUBSTRING(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) + 1, LEN(apv.TanggalIzin)))),
                                CHARINDEX(' ', LTRIM(RTRIM(SUBSTRING(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) + 1, LEN(apv.TanggalIzin))))) + 1,
                                LEN(apv.TanggalIzin)
                            )
                        ELSE LTRIM(RTRIM(SUBSTRING(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) + 1, LEN(apv.TanggalIzin))))
                        END
                    ))
                    AS datetime USING 'en-US')
                ELSE TRY_PARSE(LTRIM(RTRIM(apv.TanggalIzin)) AS datetime USING 'en-US')
                END
                )
                AND
                (
                CASE WHEN apv.TanggalIzin LIKE '%-%' THEN
                    TRY_PARSE(LTRIM(RTRIM(SUBSTRING(apv.TanggalIzin, CHARINDEX('-', apv.TanggalIzin) + 1, LEN(apv.TanggalIzin)))) AS datetime USING 'en-US')
                ELSE TRY_PARSE(LTRIM(RTRIM(apv.TanggalIzin)) AS datetime USING 'en-US')
                END
                )";

                $queryCount .= $sqlParse;
                $baseQuery .= $sqlParse;
                $params[] = $tanggalFilter;
            }

            $total = DB::selectOne($queryCount, $params)->total;

            $queryData = $baseQuery.' ORDER BY apv.tanggal_create DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';

            $params[] = $offset;
            $params[] = $perPage;

            $result = DB::select($queryData, $params);

            // Batch lookup Expired_At dari HRIS_Approval_Request
            $noTransaksiList = collect($result)->pluck('No_Transaksi')->filter()->unique()->values()->all();
            $expiredAtMap = [];
            if (! empty($noTransaksiList)) {
                $expiredAtMap = DB::table('HRIS_Approval_Request')
                    ->whereIn('No_Transaksi', $noTransaksiList)
                    ->whereNotNull('Expired_At')
                    ->get(['No_Transaksi', 'Expired_At'])
                    ->groupBy('No_Transaksi')
                    ->map(fn ($rows) => $rows->first()->Expired_At)
                    ->all();
            }

            // Status final dari SUMBER KEBENARAN TUNGGAL (Dashboard_Status) agar tidak
            // salah-tandai "Ditolak/Sistem" hanya karena Expired_At lewat padahal sudah selesai.
            $dashboardStatusMap = $this->buildDashboardStatusMapIzin($noTransaksiList);

            $dataFinal = collect($result)->map(function ($item) use ($expiredAtMap, $dashboardStatusMap) {
                if (! empty($item->Approver_Kode_Karyawan)) {
                    $item->Approver_Kode_Karyawan = Crypt::encryptString($item->Approver_Kode_Karyawan);
                }
                if (! empty($item->Requester_Kode_Karyawan)) {
                    $item->Requester_Kode_Karyawan = Crypt::encryptString($item->Requester_Kode_Karyawan);
                }

                // Tambah expired_at dari DB
                $item->expired_at = $expiredAtMap[$item->No_Transaksi] ?? null;

                $dash = $dashboardStatusMap[$item->No_Transaksi] ?? null;
                if ($dash === 'APPROVED') {
                    $item->status = 'Selesai';
                } elseif ($dash === 'REJECTED') {
                    $item->status = 'Ditolak';
                } elseif ($dash === 'EXPIRED') {
                    $item->status = 'Ditolak';
                    $item->Approver = 'Sistem';
                    $item->currentFlow = $item->MaxFlow ?? $item->currentFlow ?? 0;
                } elseif ($dash === 'PENDING') {
                    // masih diproses; biarkan status dari SQL
                } elseif ($item->expired_at && Carbon::parse($item->expired_at)->isPast()) {
                    // Fallback lama bila transaksi tak ada di Dashboard_Status
                    $item->status = 'Ditolak';
                    $item->Approver = 'Sistem';
                    $item->currentFlow = $item->MaxFlow ?? $item->currentFlow ?? 0;
                }

                return $item;
            });

            // Buat paginator
            $final_page = new LengthAwarePaginator($dataFinal, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengambil data',
                'data' => $final_page,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Menambil data getDataIzinDH'.$e->getMessage());

            return response()->json(
                [
                    'status' => 404,
                    'message' => 'Terjadi kesalahan coba lagi beberapa saat..',
                ],
                404,
            );
        }
    }

    public function getAttachmentUrl(Request $request)
    {
        try {
            $filePath = $request->input('path');

            if (! $filePath) {
                return response()->json(['message' => 'Path lampiran tidak diberikan.'], 400);
            }

            // Get file content dari GCS
            $fileContent = Storage::disk('gcs')->get($filePath);
            $fileName = basename($filePath);

            // Tentukan mime type
            $mimeType = Storage::disk('gcs')->mimeType($filePath);

            return response($fileContent, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($fileContent),
            ]);
        } catch (\Exception $e) {
            Log::channel('izinLog')->error('Error downloading file getAttachmentUrl: '.$e->getMessage());

            return response()->json(['message' => 'Gagal mendownload file.'], 500);
        }
    }

    public function parseIzin($jenis)
    {
        // dd($jenis);
        if ($jenis == 'izinFull') {
            return 'Izin Pribadi';
        } elseif ($jenis == 'sakit') {
            return 'Sakit';
        } elseif ($jenis == 'pulangCepat') {
            return 'Izin Pulang Cepat';
        } elseif ($jenis == 'terlambat') {
            return 'Izin Terlambat';
        } elseif ($jenis == 'CUTI') {
            return 'Izin Cuti';
        } elseif ($jenis == 'cuti') {
            return 'Izin Cuti';
        } elseif ($jenis == 'cutiHutang') {
            return 'Izin Cuti';
        } else {
            return 'Undefined!';
        }
    }

    public function parseJam($time)
    {
        $formats = ['H:i:s', 'H:i']; // coba dua-duanya
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $time);
            } catch (InvalidArgumentException $e) {
                // lanjut ke format berikutnya
            }
        }
        throw new InvalidArgumentException("Format jam tidak dikenali: $time");
    }

    /**
     * Status hari untuk sederet tanggal sekaligus.
     *
     * Bentuk tunggalnya di bawah ini dipakai di tiga tempat lain yang memang
     * hanya butuh satu tanggal; ia dibiarkan apa adanya. Yang butuh bentuk
     * jamak cuma `getUserShift()`, yang memanggilnya tujuh kali dalam satu
     * permintaan — tujuh panggilan fungsi skalar plus tujuh pencarian hari
     * libur.
     *
     * `N_HRIS_FN_GetStatusHariRange` menjawab sama persis dengan
     * `N_HRIS_FN_GetStatusHari` per hari; diperiksa pada 25 karyawan x 4
     * rentang x 7 hari, 700 cocok dan nol beda.
     *
     * @param  array<int,?string>  $tanggalList
     * @return array<string,array{status_hari:string,status_hari_label:?string,is_day_off:bool}>
     */
    protected function getStatusHariInfoRentang(string $kodeKaryawan, array $tanggalList): array
    {
        $tanggal = [];
        foreach ($tanggalList as $t) {
            if (empty($t)) {
                continue;
            }
            $tanggal[Carbon::parse($t)->format('Y-m-d')] = true;
        }

        if ($tanggal === []) {
            return [];
        }

        $tanggal = array_keys($tanggal);
        sort($tanggal);
        $awal = $tanggal[0];
        $akhir = $tanggal[count($tanggal) - 1];

        $status = [];
        foreach (DB::select('SELECT Tanggal, StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
            '001',
            $kodeKaryawan,
            $awal,
            $akhir,
        ]) as $row) {
            $status[Carbon::parse($row->Tanggal)->format('Y-m-d')] = $row->StatusHari;
        }

        /* Kolomnya datetime, jadi perbandingannya di-cast seperti bentuk
           tunggalnya (whereDate). Tabelnya 63 baris, jadi biaya pemindaian di
           sini tak berarti. */
        $libur = [];
        foreach (
            DB::table('HRIS_Hari_Libur')
                ->where('Kode_Perusahaan', '001')
                ->whereBetween(DB::raw('cast(Tanggal as date)'), [$awal, $akhir])
                ->select('Tanggal', 'Nama_Hari_Libur')
                ->get() as $row
        ) {
            $kunci = Carbon::parse($row->Tanggal)->format('Y-m-d');
            if (! array_key_exists($kunci, $libur)) {
                $libur[$kunci] = $row->Nama_Hari_Libur;
            }
        }

        $hasil = [];
        foreach ($tanggal as $t) {
            $statusHari = $status[$t] ?? 'WORK';
            $isDayOff = str_starts_with($statusHari, 'OFF');

            $hasil[$t] = [
                'status_hari' => $statusHari,
                'status_hari_label' => $isDayOff ? ($libur[$t] ?? null) : null,
                'is_day_off' => $isDayOff,
            ];
        }

        return $hasil;
    }

    protected function getStatusHariInfo(string $kodeKaryawan, string $tanggal): array
    {
        $statusRow = DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari', [
            '001',
            $kodeKaryawan,
            $tanggal,
        ]);

        $statusHari = $statusRow->status_hari ?? 'WORK';
        $isDayOff = str_starts_with($statusHari, 'OFF');
        $statusHariLabel = null;

        if ($isDayOff) {
            $holiday = DB::table('HRIS_Hari_Libur')
                ->where('Kode_Perusahaan', '001')
                ->whereDate('Tanggal', $tanggal)
                ->select('Nama_Hari_Libur')
                ->first();

            if ($holiday) {
                $statusHariLabel = $holiday->Nama_Hari_Libur;
            }
        }

        return [
            'status_hari' => $statusHari,
            'status_hari_label' => $statusHariLabel,
            'is_day_off' => $isDayOff,
        ];
    }

    protected function expandTanggalRange(array $tanggalValues): array
    {
        $tanggalValues = array_values(array_filter($tanggalValues));
        if (count($tanggalValues) === 0) {
            return [];
        }

        if (count($tanggalValues) === 1) {
            return [$tanggalValues[0]];
        }

        if (count($tanggalValues) === 2) {
            $start = Carbon::parse($tanggalValues[0]);
            $end = Carbon::parse($tanggalValues[1]);
            if ($end->lt($start)) {
                return [$tanggalValues[0]];
            }

            $dates = [];
            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }

            return $dates;
        }

        return array_values(array_unique($tanggalValues));
    }

    public function submitIzin(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'jenisIzin' => 'required|string',
            'tanggal' => 'required|array',
            'tanggal.*' => 'required',
            'waktu' => 'required|string',
            'Alasan' => 'required|string|max:300',
            'file' => 'sometimes|nullable|mimes:jpeg,png,jpg,gif,pdf',
        ]);

        if ($validator->fails()) {
            Log::channel('izinLog')->error('data Izin tidka valid'.$validator->errors());

            return response()->json(
                [
                    'message' => 'Data tidak valid.',
                    'errors' => $validator->errors(),
                ],
                422,
            );
        }

        $validatedData = $validator->validated();
        $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
        $tanggalList = $this->expandTanggalRange($validatedData['tanggal']);

        foreach ($tanggalList as $tanggal) {
            if ($this->shiftRequestService->isOffDay($kodeKaryawan, $tanggal)) {
                return response()->json(
                    [
                        'status' => 400,
                        'message' => 'Tanggal yang dipilih adalah hari OFF/Libur. Pengajuan izin tidak dapat dilakukan pada tanggal tersebut.',
                    ],
                    400,
                );
            }
        }
        // $approver = DB::table('HRIS_Approval_Flow as a')
        //     ->join('Karyawan as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
        //     ->whereNull('a.Status')
        //     ->where('b.Aktif', 'Y')
        //     ->whereNull('b.Tanggal_Resign')
        //     ->where('a.Kode_Karyawan_Requester', Auth::user()->karyawan->Kode_Karyawan)
        //     ->get();

        // dd($approver);

        // if ($approver->isEmpty()) {
        //     Log::channel('uDashLog')->error('Tidak ada approver. Hubungi admin untuk menugaskan approver divisi anda!');

        //     return response()->json([
        //         'message' => 'Tidak ada approver. Hubungi admin untuk menugaskan approver divisi anda!.',
        //         'errors' => 'Tidak ada approver. Hubungi admin untuk menugaskan approver divisi anda!.'
        //     ], 404);
        // }
        $approverAll = DB::table('HRIS_Approval_Flow as a')
            ->join('Karyawan as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
            ->whereNull('a.Status')
            ->where('a.Kode_Karyawan_Requester', Auth::user()->karyawan->Kode_Karyawan)
            ->select(
                'a.*',

                'b.Aktif',
                'b.Tanggal_Resign',
            )
            ->get();

        // jika tidak ada approver sama sekali
        if ($approverAll->isEmpty()) {
            Log::channel('uDashLog')->error('Tidak ada approver sama sekali!');

            return response()->json(
                [
                    'message' => 'Tidak ada approver. Hubungi admin untuk menugaskan approver izin anda!.',
                    'errors' => 'Tidak ada approver. Hubungi admin untuk menugaskan approver izin anda!.',
                ],
                404,
            );
        }

        // cek apakah semua approver aktif
        $allActive = $approverAll->every(function ($item) {
            return $item->Aktif === 'Y' && is_null($item->Tanggal_Resign);
        });

        // kalau ada 1 saja yang nonaktif/resign → blok
        if (! $allActive) {
            Log::channel('uDashLog')->error('Terdapat approver nonaktif/resign!');

            return response()->json(
                [
                    'message' => 'Terdapat approver nonaktif. Hubungi admin untuk memperbarui approver izin anda!.',
                    'errors' => 'Terdapat approver nonaktif. Hubungi admin untuk memperbarui approver izin anda!.',
                ],
                404,
            );
        }

        // lanjut kalau semua approver valid
        $approver = $approverAll;

        // dd($approver);

        try {
            $uploadedIzinPath = null;
            $transactionStarted = false;

            /*
             * Kedaluwarsa approval dihitung satu tempat. Sebelumnya baris cuti
             * berbunyi (int) (config(...) ?? 0): karena batas_approve_cuti memang
             * null, hasilnya 0 hari, dan Expired_At ditulis addDays(0) — persis
             * pada detik pengajuan dibuat. Padahal jalur BACA (parseFlagApproval)
             * membaca null yang sama sebagai "tidak pernah kedaluwarsa", dan
             * seluruh 122 baris approval CT yang ada memang ber-Expired_At NULL.
             *
             * kedaluwarsaPada() mengembalikan null bila tak ada batas, sehingga
             * kolomnya tetap NULL — bentuk yang sudah dipahami semua pembacanya.
             */
            $kedaluwarsaIzin = AturanKedaluwarsaApproval::kedaluwarsaPada('izin');
            $kedaluwarsaCuti = AturanKedaluwarsaApproval::kedaluwarsaPada('cuti');

            $jenisIzinRequest = $validatedData['jenisIzin'];
            if (in_array($jenisIzinRequest, ['izinFull', 'sakit', 'sakitTibaTiba'], true)) {
                $prefix = 'IS'.Carbon::now()->format('m-y').'-';
                $noFaktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate($prefix, fn () => DB::table('Transaksi_Sakit_Izin')->where('No_Transaksi', 'like', $prefix.'%')->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')));
            } elseif (in_array($jenisIzinRequest, ['cuti', 'cutiHutang'], true)) {
                $prefix = 'CT'.Carbon::now()->format('m-y').'-';
                $noFaktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate($prefix, fn () => DB::table('Transaksi_Cuti')->where('No_Transaksi', 'like', $prefix.'%')->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')));
            } else {
                $prefix = 'TP'.Carbon::now()->format('m-y').'-';
                $noFaktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate($prefix, fn () => DB::table('Transaksi_Terlambat_Pulang_Cepat')->where('No_Transaksi', 'like', $prefix.'%')->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')));
            }
            $uploadedIzinPath = $this->uploadIzinAttachment($request);
            DB::beginTransaction();
            $transactionStarted = true;

            if (
                $validatedData['jenisIzin'] == 'izinFull' ||
                $validatedData['jenisIzin'] == 'sakit' ||
                $validatedData['jenisIzin'] == 'sakitTibaTiba'
            ) {

                $kodePerusahaan = '001';
                $tanggalSaatIni = date('Y-m-d');
                $jamSaatIni = date('H:i:s');
                $userID = Auth::id();
                $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
                $jenisIzin = $validatedData['jenisIzin'];
                $tanggalSakitIzinDari = $validatedData['tanggal'][0];
                $tanggalSakitIzinSampai = $validatedData['tanggal'][1];
                $alasan = $validatedData['Alasan'];

                $filePath = $uploadedIzinPath;

                $transaksi = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Tanggal' => $tanggalSaatIni,
                    'Jam' => $jamSaatIni,
                    'UserID' => $userID,
                ];

                $transaksi_detail = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Jenis' => $jenisIzin,
                    'Tanggal_Sakit_Izin_Dari' => $tanggalSakitIzinDari,
                    'Tanggal_Sakit_Izin_Sampai' => $tanggalSakitIzinSampai,
                    'Alasan' => $alasan,
                    'filePath' => $filePath != '' ? $filePath : null,
                ];
                $Division_Id = Auth::user()->divisionKaryawan;
                $flow = DB::table('HRIS_Approval_flow')
                    ->whereNull('Status')
                    ->where('Kode_Karyawan_Requester', $kodeKaryawan)
                    ->get()
                    ->map(function ($item) use ($kodeKaryawan, $noFaktur, $kedaluwarsaIzin) {
                        return [
                            'Kode_Karyawan' => $kodeKaryawan,
                            'update_at' => date('Y-m-d H:i:s'),
                            'Flow_Id' => $item->id,
                            'No_Transaksi' => $noFaktur,
                            'Approver_Karyawan_Kode' => $item->Kode_Karyawan,
                            'Flag_Group' => 'T',
                            'Request_Order_Flow' => $item->order_flow,
                            'Created_at' => date('Y-m-d H:i:s'),
                            'Expired_At' => $kedaluwarsaIzin,
                        ];
                    });

                $inserFlowtoRequest = DB::table('HRIS_Approval_Request')->insert($flow->toArray());

                if ($inserFlowtoRequest) {
                    $this->notifikasiApproverIzin($kodeKaryawan, $noFaktur, $jenisIzin, $this->formatTanggal($validatedData['tanggal'][0], $validatedData['tanggal'][1]));
                }
                DB::table('Transaksi_Sakit_Izin')->insert($transaksi);
                $Detail_Id = DB::table('Transaksi_Sakit_Izin_Detail')->insertGetId($transaksi_detail);

                if ($tanggalSakitIzinDari != $tanggalSakitIzinSampai) {
                    $start = Carbon::createFromFormat('Y-m-d', $tanggalSakitIzinDari);
                    $end = Carbon::createFromFormat('Y-m-d', $tanggalSakitIzinSampai);

                    $currentDate = $start->copy();
                    while ($currentDate->lte($end)) {
                        $transaksi_det = [
                            'Kode_Perusahaan' => $kodePerusahaan,
                            'No_Transaksi' => $noFaktur,
                            'Kode_Karyawan' => $kodeKaryawan,
                            'Urut_Detail' => $Detail_Id,
                            'Tanggal_Sakit_Izin' => $currentDate,
                        ];
                        DB::table('Transaksi_Sakit_Izin_Det')->insert($transaksi_det);
                        $currentDate->addDay();
                    }
                } else {
                    $transaksi_det = [
                        'Kode_Perusahaan' => $kodePerusahaan,
                        'No_Transaksi' => $noFaktur,
                        'Kode_Karyawan' => $kodeKaryawan,
                        'Urut_Detail' => $Detail_Id,
                        'Tanggal_Sakit_Izin' => $tanggalSakitIzinDari,
                    ];
                    DB::table('Transaksi_Sakit_Izin_Det')->insert($transaksi_det);
                }
            } elseif ($validatedData['jenisIzin'] == 'cuti' || $validatedData['jenisIzin'] == 'cutiHutang') {

                $kodePerusahaan = '001';
                $tanggalSaatIni = date('Y-m-d');
                $jamSaatIni = date('H:i:s');
                $userID = Auth::id();
                $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
                $jenisIzin = $validatedData['jenisIzin'];
                $tanggalSakitIzinDari = $validatedData['tanggal'][0];
                $tanggalSakitIzinSampai = $validatedData['tanggal'][1];
                $alasan = $validatedData['Alasan'];

                $filePath = $uploadedIzinPath;

                $transaksi = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Tanggal' => $tanggalSaatIni,
                    'Jam' => $jamSaatIni,
                    'UserID' => $userID,
                ];

                $transaksi_detail = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Jenis' => $jenisIzin,
                    'Tanggal_Cuti_Dari' => $tanggalSakitIzinDari,
                    'Tanggal_Cuti_Sampai' => $tanggalSakitIzinSampai,
                    'Alasan' => $alasan,
                    'filePath' => $filePath != '' ? $filePath : null,
                ];
                $Division_Id = Auth::user()->divisionKaryawan;
                $flow = DB::table('HRIS_Approval_flow')
                    ->whereNull('Status')
                    ->where('Kode_Karyawan_Requester', $kodeKaryawan)
                    ->get()
                    ->map(function ($item) use ($kodeKaryawan, $noFaktur, $kedaluwarsaCuti) {
                        return [
                            'Kode_Karyawan' => $kodeKaryawan,
                            'update_at' => date('Y-m-d H:i:s'),
                            'Flow_Id' => $item->id,
                            'No_Transaksi' => $noFaktur,
                            'Approver_Karyawan_Kode' => $item->Kode_Karyawan,
                            'Flag_Group' => 'T',
                            'Request_Order_Flow' => $item->order_flow,
                            'Created_at' => date('Y-m-d H:i:s'),
                            'Expired_At' => $kedaluwarsaCuti,
                        ];
                    });

                $inserFlowtoRequest = DB::table('HRIS_Approval_Request')->insert($flow->toArray());
                if ($inserFlowtoRequest) {
                    $this->notifikasiApproverIzin($kodeKaryawan, $noFaktur, $jenisIzin, $this->formatTanggal($validatedData['tanggal'][0], $validatedData['tanggal'][1]));
                }
                DB::table('Transaksi_Cuti')->insert($transaksi);
                $Detail_Id = DB::table('Transaksi_Cuti_Detail')->insertGetId($transaksi_detail);

                if ($tanggalSakitIzinDari != $tanggalSakitIzinSampai) {
                    $start = Carbon::createFromFormat('Y-m-d', $tanggalSakitIzinDari);
                    $end = Carbon::createFromFormat('Y-m-d', $tanggalSakitIzinSampai);

                    $currentDate = $start->copy();
                    while ($currentDate->lte($end)) {
                        $transaksi_det = [
                            'Kode_Perusahaan' => $kodePerusahaan,
                            'No_Transaksi' => $noFaktur,
                            'Kode_Karyawan' => $kodeKaryawan,
                            'Urut_Detail' => $Detail_Id,
                            'Tanggal_Cuti' => $currentDate,
                        ];
                        DB::table('Transaksi_Cuti_Det')->insert($transaksi_det);
                        $currentDate->addDay();
                    }
                } else {
                    $transaksi_det = [
                        'Kode_Perusahaan' => $kodePerusahaan,
                        'No_Transaksi' => $noFaktur,
                        'Kode_Karyawan' => $kodeKaryawan,
                        'Urut_Detail' => $Detail_Id,
                        'Tanggal_Cuti' => $tanggalSakitIzinDari,
                    ];
                    DB::table('Transaksi_Cuti_Det')->insert($transaksi_det);
                }
            } else {
                $Jam_Masuk_Real = $request->jam_masuk ?? null;
                $Jam_Keluar_Real = $request->jam_keluar ?? null;

                $kodePerusahaan = '001';
                $tanggalSaatIni = date('Y-m-d');
                $jamSaatIni = date('H:i:s');
                $userID = Auth::id();
                $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
                $jenisIzin = $validatedData['jenisIzin'];
                $alasan = $validatedData['Alasan'];

                // $if($Jam_Keluar_Real < $Jam_Masuk_Real){
                //     $tanggalSakitIzinDari = $validatedData['tanggal'][0] ;

                // }else{

                // }

                // $jamMasuk   = Carbon::createFromFormat('H:i', $Jam_Masuk_Real);
                // $jamKeluar  = Carbon::createFromFormat('H:i', $Jam_Keluar_Real);
                // $jamIzin    = Carbon::createFromFormat('H:i', $validatedData['waktu']);
                $jamMasuk = $this->parseJam($Jam_Masuk_Real);
                $jamKeluar = $this->parseJam($Jam_Keluar_Real);
                $jamIzin = $this->parseJam($validatedData['waktu']);

                $tanggal = Carbon::parse($validatedData['tanggal'][0]);
                // dd($jamMasuk, $jamKeluar, $jamIzin, $tanggal);
                // dd($tanggal);
                if ($jamKeluar->lessThan($jamMasuk)) {
                    // ✅ Shift malam (jam keluar < jam masuk, misalnya 19:00 – 01:00)
                    if ($jamIzin->lessThan($jamKeluar)) {
                        // izin jam lewat tengah malam → tambah 1 hari
                        $tanggalSakitIzinDari = $tanggal->copy()->addDay()->format('Y-m-d');
                    } else {
                        // izin jam di hari yang sama
                        $tanggalSakitIzinDari = $tanggal->format('Y-m-d');
                    }
                } else {
                    // ✅ Shift normal (jam keluar > jam masuk, misalnya 08:00 – 17:00)
                    $tanggalSakitIzinDari = $tanggal->format('Y-m-d');
                }

                // dd($tanggalSakitIzinDari, $validatedData['waktu']);

                // dd($tanggalSakitIzinDari);
                $tanggalSakitIzinSampai = $validatedData['tanggal'][1].' '.$validatedData['waktu'];
                $filePath = $uploadedIzinPath;

                $transaksi = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Tanggal' => $tanggalSaatIni,
                    'Jam' => $jamSaatIni,
                    'UserID' => $userID,
                ];

                $transaksi_detail = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noFaktur,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Jenis' => $jenisIzin,
                    'Tanggal_Masuk_Pulang' => $tanggalSakitIzinDari,
                    'Tanggal_Hari_Kerja' => $tanggal->format('Y-m-d'),
                    'Jam' => $validatedData['waktu'],
                    'Alasan' => $alasan,
                    'filePath' => $filePath != '' ? $filePath : null,
                ];

                $Division_Id = Auth::user()->divisionKaryawan;
                $flow = DB::table('HRIS_Approval_flow')
                    ->whereNull('Status')
                    ->where('Kode_Karyawan_Requester', $kodeKaryawan)
                    ->get()
                    ->map(function ($item) use ($kodeKaryawan, $noFaktur, $kedaluwarsaIzin) {
                        return [
                            'Kode_Karyawan' => $kodeKaryawan,
                            'update_at' => date('Y-m-d H:i:s'),
                            'Flow_Id' => $item->id,
                            'No_Transaksi' => $noFaktur,
                            'Approver_Karyawan_Kode' => $item->Kode_Karyawan,
                            'Flag_Group' => 'T',
                            'Request_Order_Flow' => $item->order_flow,
                            'Created_at' => date('Y-m-d H:i:s'),
                            'Expired_At' => $kedaluwarsaIzin,
                        ];
                    });

                $inserFlowtoRequest = DB::table('HRIS_Approval_Request')->insert($flow->toArray());
                if ($flow) {
                    $this->notifikasiApproverIzin($kodeKaryawan, $noFaktur, $jenisIzin, $this->formatTanggal($tanggalSakitIzinDari, null, $validatedData['waktu']));
                }
                DB::table('Transaksi_Terlambat_Pulang_Cepat')->insert($transaksi);
                DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')->insert($transaksi_detail);
            }

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $noFaktur,
                'IZIN_TRANSACTION_SUBMITTED',
                'ABSENSI_SUBMIT_IZIN',
                3,
            );

            return response()->json(
                [
                    'status' => 200,
                    'message' => 'Data izin berhasil disimpan.',
                    'no_transaksi' => $noFaktur,
                ],
                200,
            );
        } catch (\Throwable $e) {
            if ($transactionStarted && DB::transactionLevel() > 0) {
                DB::rollback();
            }
            if ($uploadedIzinPath) {
                Storage::disk('gcs')->delete($uploadedIzinPath);
            }
            Log::channel('izinLog')->error('Gagal Menyimpan data submitIzin', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),

                'user_id' => Auth::id(),
                'kode_karyawan' => Auth::user()->karyawan->Kode_Karyawan ?? null,

                'no_transaksi' => $noFaktur ?? null,

                'request_input' => request()->all(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan pada server. Coba lagi. ',
                ],
                500,
            );
        }
    }

    /**
     * Beri tahu approver PERTAMA yang masih menunggu, lewat WhatsApp.
     *
     * ── Kenapa ini punya rumah ───────────────────────────────────────────────
     *
     * Blok ini tersalin TIGA KALI di metode yang sama — sekali untuk pengajuan
     * sakit/izin, sekali untuk cuti, sekali untuk terlambat/pulang cepat.
     * Masing-masing membawa kuerinya sendiri, muatan WhatsApp 60 baris yang
     * sama, penundaan afterCommit-nya sendiri, dan kenaikan angka_wa-nya
     * sendiri.
     *
     * ── Penjaga yang hilang di dua dari tiga salinan ─────────────────────────
     *
     * Salinan sakit/izin menulis:
     *
     *     if (tidak ada nomor HP) { catat } else { susun pesan; kirim }
     *
     * Dua salinan lainnya kehilangan `else`-nya:
     *
     *     if (tidak ada nomor HP) { catat }
     *     susun pesan; kirim          <- tetap jalan
     *
     * Jadi untuk pengajuan CUTI dan TERLAMBAT/PULANG CEPAT, ketika approver
     * tidak punya nomor HP, sistem mencatat "tidak ada nomor HP" lalu tetap
     * mengirim WhatsApp ke tujuan kosong — dan bila API-nya menjawab apa pun,
     * angka_wa ikut dinaikkan seolah pesannya sampai.
     *
     * Diukur di basis data pengembangan hari ini: 0 dari 958 karyawan aktif
     * tanpa nomor HP, jadi cabang itu LATEN, bukan sedang menyala. Ia menyala
     * pada karyawan pertama yang masuk tanpa nomor HP.
     *
     * Kanal log: kedua salinan tanpa penjaga mencatat hasil kirim ke
     * 'waIzinPage'; salinan yang berpenjaga memakai 'waIzinLog'. Rumah bersama
     * ini memakai 'waIzinLog' untuk hasil kirim, jadi catatan dari dua jalur
     * itu berpindah berkas. Peringatan "tidak ada nomor HP" tetap di
     * 'waIzinPage' seperti semula, di ketiga jalur.
     *
     * @param  string  $tanggalTampil  teks tanggal yang sudah diformat — inilah
     *                                 satu-satunya bagian muatan yang memang
     *                                 berbeda antara ketiga jenis pengajuan.
     */
    private function notifikasiApproverIzin(
        string $kodeKaryawan,
        string $noFaktur,
        string $jenisIzin,
        string $tanggalTampil,
    ): void {
        $HPflow = DB::table('HRIS_Approval_flow as a')
            ->select('b.HP', 'b.Nama', 'b.Kode_Karyawan')
            ->join('Karyawan as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
            ->where('a.Kode_Karyawan_Requester', $kodeKaryawan)
            ->whereNull('a.Status')
            ->orderBy('a.order_flow')
            ->first();

        $user = DB::table('Karyawan')->where('Kode_Karyawan', $HPflow->Kode_Karyawan)->first();
        $userInsertNoHp = $user->HP ?? null;
        if (! $userInsertNoHp || $userInsertNoHp == ' ' || $userInsertNoHp == '-') {
            Log::channel('waIzinPage')->warning('Pesan Error', [
                'pesan' => "Karyawan {$user->Nama} Tidak ada Nomor Hp untuk noitifikasi Transaksi {$noFaktur}",
            ]);

            return;
        }

        // whatsapp message to user
        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $userInsertNoHp,
            'type' => 'template',
            'template' => [
                'name' => 'notif_approver_hcis',
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
                                'text' => $user->Nama,
                            ],
                            [
                                'type' => 'text',
                                'text' => $noFaktur,
                            ],
                            [
                                'type' => 'text',
                                'text' => $this->parseIzin($jenisIzin),
                            ],
                            [
                                'type' => 'text',
                                'text' => $tanggalTampil,
                            ],
                            [
                                'type' => 'text',
                                'text' => Auth::user()->karyawan->Nama,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        /*
         * ── Kenapa lewat sendAndLog, bukan Whatsapp::send_message ────────────
         *
         * Jalur ini satu-satunya notifikasi approver di aplikasi yang memanggil
         * Whatsapp::send_message LANGSUNG — HTTP POST di dalam permintaan web.
         * Semua modul lain (cuti, shift, lembur, KPI, approval izin, admin cuti)
         * memakai WhatsappLogHelper::sendAndLog, yang men-dispatch
         * SendWhatsappJob ke antrean.
         *
         * Gejala yang dilaporkan: submit izin tak pernah memunculkan apa pun di
         * Cloud Tasks. Memang tak pernah ada job yang dibuat. Yang tak terlihat
         * lebih penting:
         *
         *   - tanpa percobaan ulang. SendWhatsappJob punya tries=3; panggilan
         *     langsung hanya punya WHATSAPP_RETRY_TIMES (baku 1) lalu menyerah,
         *     dan kegagalannya cuma jadi baris log.
         *   - nomor HP tak divalidasi bentuknya. sendAndLog menormalkan lalu
         *     menuntut pola ^62…; jalur ini hanya menolak kosong dan '-'.
         *   - pengguna menunggu HTTP-nya: 5 dtk koneksi + 10 dtk permintaan
         *     melekat pada respons halaman.
         *
         * Penundaan sampai commit TIDAK hilang, hanya berpindah tempat dan jadi
         * lebih kuat: SendWhatsappJob memanggil afterCommit() di konstruktornya,
         * jadi dispatch-nya menunggu commit DAN HTTP-nya dijalankan worker.
         *
         * Kenaikan `angka_wa` sengaja tidak dibawa serta. Kolom itu ditulis di
         * sepuluh tempat dan TIDAK PERNAH dibaca di mana pun — satu-satunya
         * pembacaan di repo sudah dikomentari (ExpiredIzin.php: `// ->where(
         * "angka_wa", '>', 2)`). Itu sebabnya sendAndLog sudah lebih dulu
         * melepas callback yang mengurusnya untuk seluruh modul lain. Jumlah WA
         * per approver tetap bisa diturunkan dari Whatsapp_Log bila kelak
         * dibutuhkan.
         */
        WhatsappLogHelper::sendAndLog(
            $user->Kode_Karyawan ?? null,
            $user->Nama ?? null,
            $userInsertNoHp,
            $noFaktur,
            $pesan,
        );
    }

    public function formatTanggal($start, $end, $waktu = null)
    {
        Carbon::setLocale('id');

        // Kalau ada waktu, berarti single date + time
        if (! empty($waktu)) {
            return Carbon::parse($start)->translatedFormat('d M Y').' '.$waktu;
        }

        // Kalau tidak ada waktu → berarti range
        if (! empty($start) && ! empty($end)) {
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);

            if ($startDate->equalTo($endDate)) {
                // Hanya satu hari
                return $startDate->translatedFormat('d M Y');
            } elseif ($startDate->month === $endDate->month && $startDate->year === $endDate->year) {
                // Range dalam bulan & tahun yang sama
                return $startDate->format('d').'-'.$endDate->format('d').' '.$endDate->translatedFormat('M Y');
            } elseif ($startDate->year === $endDate->year) {
                // Range beda bulan tapi masih di tahun yang sama
                return $startDate->translatedFormat('d M').' - '.$endDate->translatedFormat('d M Y');
            } else {
                // Range beda tahun
                return $startDate->translatedFormat('d M Y').' - '.$endDate->translatedFormat('d M Y');
            }
        }

        return null;
    }

    public function getUserShift(Request $request)
    {
        try {
            $Kode_Perusahaan = '001';
            $chooseDate = Carbon::parse($request->tanggal) ?? now()->toDateString();
            switch ($request->jenis) {
                case 'next':
                    $date = Carbon::parse($chooseDate)->copy()->addWeek()->format('Y-m-d');
                    break;
                case 'prev':
                    $date = Carbon::parse($chooseDate)->copy()->subWeek()->format('Y-m-d');
                    break;
                default:
                    $date = $chooseDate;
                    break;
            }

            $karyawan = Auth()->user()->Karyawan->Kode_Karyawan;

            $startOfWeek = Carbon::parse($date)->copy()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $endOfWeek = Carbon::parse($date)->copy()->startOfWeek(Carbon::SATURDAY)->format('Y-m-d');

            $currentDate = $startOfWeek;
            $data = [];

            for ($i = 0; $i < 7; $i++) {
                $Tanggal = date('Y-m-d', strtotime($currentDate));

                $Hari = date('N', strtotime($Tanggal));
                if ($Hari <= 6) {
                    $Hari += 1;
                } elseif ($Hari == 7) {
                    $Hari = 1;
                }

                $query = 'exec sp_getShiftAbsen ?, ?, ?, ?';
                $result = DB::select($query, [$Tanggal, $karyawan, $Kode_Perusahaan, $Hari]);

                if ($result) {
                    $data[] = $result[0] ?? null;
                } else {
                    $data[] = (object) ['Tanggal' => $currentDate];
                }

                $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
            }

            /* Status hari untuk ketujuh tanggal diambil SEKALI, bukan satu
               panggilan fungsi skalar plus satu pencarian hari libur per hari. */
            $statusHariRentang = $this->getStatusHariInfoRentang(
                $karyawan,
                collect($data)->map(fn ($item) => $item->Tanggal ?? null)->all(),
            );

            $dataFinal = collect($data)->map(function ($item) use ($karyawan, $statusHariRentang) {
                $tanggalValue = $item->Tanggal ?? null;
                $rawKodeKaryawan = $item->Kode_Karyawan ?? null;
                $jenisCheckin = isset($item->jenisCheckin) ? trim((string) $item->jenisCheckin) : null;
                $jenisCheckOut = isset($item->jenisCheckOut) ? trim((string) $item->jenisCheckOut) : null;

                $isWebMasuk = $jenisCheckin === '180';
                $isWebKeluar = $jenisCheckOut === '180';

                $needsWebMasukPath = $isWebMasuk && empty($item->filePath_Masuk);
                $needsWebKeluarPath = $isWebKeluar && empty($item->filePath_Keluar);

                if (! empty($rawKodeKaryawan) && ! empty($tanggalValue) && ($isWebMasuk || $isWebKeluar)) {
                    $webAttendanceMedia = $this->getWebAbsensiMediaByDate(
                        $rawKodeKaryawan,
                        $tanggalValue,
                        $item->CheckIn ?? null,
                        $item->CheckOut ?? null,
                    );

                    if ($isWebMasuk && ! empty($webAttendanceMedia['masuk']['filePath'])) {
                        $item->filePath_Masuk = $webAttendanceMedia['masuk']['filePath'];
                    }

                    if ($isWebKeluar && ! empty($webAttendanceMedia['keluar']['filePath'])) {
                        $item->filePath_Keluar = $webAttendanceMedia['keluar']['filePath'];
                    }

                    if ($isWebMasuk && ! empty($webAttendanceMedia['masuk']['No_Transaksi'])) {
                        $item->No_Transaksi_Masuk = $webAttendanceMedia['masuk']['No_Transaksi'];
                    }

                    if ($isWebKeluar && ! empty($webAttendanceMedia['keluar']['No_Transaksi'])) {
                        $item->No_Transaksi_Keluar = $webAttendanceMedia['keluar']['No_Transaksi'];
                    }

                    if ($isWebMasuk && ! empty($webAttendanceMedia['masuk']['Alasan'])) {
                        $item->Alasan_Masuk = $webAttendanceMedia['masuk']['Alasan'];
                    }

                    if ($isWebKeluar && ! empty($webAttendanceMedia['keluar']['Alasan'])) {
                        $item->Alasan_Keluar = $webAttendanceMedia['keluar']['Alasan'];
                    }
                }

                if ($tanggalValue) {
                    $statusHariInfo = $statusHariRentang[Carbon::parse($tanggalValue)->format('Y-m-d')]
                        ?? $this->getStatusHariInfo($karyawan, $tanggalValue);
                    $item->status_hari = $statusHariInfo['status_hari'];
                    $item->status_hari_label = $statusHariInfo['status_hari_label'];
                    $item->is_day_off = $statusHariInfo['is_day_off'];
                }
                if (! empty($item->Kode_Karyawan)) {
                    $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
                }
                if (! empty($item->UserID_Absen)) {
                    $item->UserID_Absen = Crypt::encryptString($item->UserID_Absen);
                }
                if (! empty($item->filePath_Masuk)) {
                    $item->filePath_Masuk = str_starts_with((string) $item->filePath_Masuk, 'http')
                        ? $item->filePath_Masuk
                        : Storage::disk('gcs')->temporaryUrl($item->filePath_Masuk, now()->addMinutes(10)) ?? null;
                }
                if (! empty($item->filePath_Keluar)) {
                    $item->filePath_Keluar = str_starts_with((string) $item->filePath_Keluar, 'http')
                        ? $item->filePath_Keluar
                        : Storage::disk('gcs')->temporaryUrl($item->filePath_Keluar, now()->addMinutes(10)) ?? null;
                }

                return $item;
            });

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil Mendapatkan data',
                'data' => $dataFinal,
            ]);
        } catch (\Exception $e) {
            Log::channel('absensiLog')->error('Error saat mengambil data shift absen: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Gagal mengambil data. Silakan periksa koneksi Anda. ',
                ],
                500,
            );
        }
    }

    private function getWebAbsensiMediaByDate(
        string $kodeKaryawan,
        string $tanggal,
        ?string $checkIn,
        ?string $checkOut,
    ): array {
        $result = [
            'masuk' => [
                'filePath' => null,
                'No_Transaksi' => null,
                'Alasan' => null,
            ],
            'keluar' => [
                'filePath' => null,
                'No_Transaksi' => null,
                'Alasan' => null,
            ],
        ];

        $records = DB::table('Transaksi_Absensi_Detail as d')
            ->join('Transaksi_Absensi as h', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->where('d.Kode_Perusahaan', '001')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            /* Rentang setengah terbuka menggantikan CONVERT(date, h.Tanggal):
               membungkus kolomnya mematikan pemakaian indeks. */
            ->where('h.Tanggal', '>=', Carbon::parse($tanggal)->startOfDay())
            ->where('h.Tanggal', '<', Carbon::parse($tanggal)->startOfDay()->addDay())
            ->whereIn('d.Jenis', ['masuk', 'keluar'])
            ->orderByDesc('d.Tanggal_Absensi')
            ->select('d.Jenis', 'd.No_Transaksi', 'd.Alasan', 'd.filePath', 'd.Tanggal_Absensi')
            ->get();

        $groupedRecords = [
            'masuk' => [],
            'keluar' => [],
            'all' => [],
        ];

        foreach ($records as $record) {
            $jenis = strtolower((string) $record->Jenis);
            if (! isset($groupedRecords[$jenis])) {
                $groupedRecords['all'][] = $record;

                continue;
            }

            $groupedRecords[$jenis][] = $record;
            $groupedRecords['all'][] = $record;
        }

        // if ($tanggal == '2026-04-09') {
        //     dd($records, $groupedRecords, $checkIn, $checkOut);
        // }

        // dd($groupedRecords, $checkIn, $checkOut);
        $selectedMasuk =
            $this->findWebAbsensiRecordByExactTimestamp($kodeKaryawan, $checkIn) ??
            $this->pickClosestWebAttendanceRecord($groupedRecords['masuk'], $checkIn, 'masuk');

        $selectedKeluar =
            $this->findWebAbsensiRecordByExactTimestamp($kodeKaryawan, $checkOut) ??
            $this->pickClosestWebAttendanceRecord($groupedRecords['keluar'], $checkOut, 'keluar');

        // Fallback jika data Jenis di detail tidak konsisten:
        // pilih record dengan waktu paling dekat ke target dari seluruh transaksi absensi web di tanggal tsb.
        if (! $selectedMasuk && ! empty($groupedRecords['all'])) {
            $selectedMasuk = $this->pickClosestWebAttendanceRecord($groupedRecords['all'], $checkIn, 'masuk');
        }

        if (! empty($groupedRecords['all'])) {
            $selectedKeluarFromAll = $this->pickClosestWebAttendanceRecord($groupedRecords['all'], $checkOut, 'keluar');

            if (
                $selectedKeluarFromAll &&
                (! $selectedKeluar ||
                    $this->isRecordCloserToTarget(
                        $selectedKeluarFromAll->Tanggal_Absensi ?? null,
                        $selectedKeluar->Tanggal_Absensi ?? null,
                        $checkOut,
                    ))
            ) {
                $selectedKeluar = $selectedKeluarFromAll;
            }
        }

        if ($selectedMasuk) {
            $result['masuk']['No_Transaksi'] = $selectedMasuk->No_Transaksi ?? null;
            $result['masuk']['Alasan'] = $selectedMasuk->Alasan ?? null;
            $result['masuk']['filePath'] = $selectedMasuk->filePath ?? null;
        }

        if ($selectedKeluar) {
            $result['keluar']['No_Transaksi'] = $selectedKeluar->No_Transaksi ?? null;
            $result['keluar']['Alasan'] = $selectedKeluar->Alasan ?? null;
            $result['keluar']['filePath'] = $selectedKeluar->filePath ?? null;
        }

        return $result;
    }

    private function pickClosestWebAttendanceRecord(array $records, ?string $targetDateTime, string $jenis): ?object
    {
        if (empty($records)) {
            return null;
        }

        // dd($records);

        $recordsWithPath = array_values(
            array_filter($records, function ($record) {
                return ! empty($record->filePath);
            }),
        );

        $candidateRecords = ! empty($recordsWithPath) ? $recordsWithPath : $records;

        if (empty($targetDateTime)) {
            return $candidateRecords[0];
        }

        try {
            $target = Carbon::parse($targetDateTime);
        } catch (\Throwable $e) {
            return $candidateRecords[0];
        }

        // Prioritas 1: timestamp sama persis hingga level detik.
        $targetSecond = $target->copy()->format('Y-m-d H:i:s');
        foreach ($candidateRecords as $record) {
            if (empty($record->Tanggal_Absensi)) {
                continue;
            }

            try {
                $recordTime = Carbon::parse($record->Tanggal_Absensi);
            } catch (\Throwable $e) {
                continue;
            }

            if ($recordTime->format('Y-m-d H:i:s') === $targetSecond) {
                return $record;
            }
        }

        // Prioritas 2 (arah waktu):
        // - keluar: ambil web absensi terakhir sebelum/sama dengan checkout (plus toleransi 2 menit)
        // - masuk : ambil web absensi pertama setelah/sama dengan checkin (minus toleransi 2 menit)
        $directionCandidate = null;
        $directionTs = null;
        $toleranceSeconds = 120;

        foreach ($candidateRecords as $record) {
            if (empty($record->Tanggal_Absensi)) {
                continue;
            }

            try {
                $recordTime = Carbon::parse($record->Tanggal_Absensi);
            } catch (\Throwable $e) {
                continue;
            }

            if ($jenis === 'keluar') {
                if ($recordTime->greaterThan($target->copy()->addSeconds($toleranceSeconds))) {
                    continue;
                }

                $currentTs = $recordTime->getTimestamp();
                if ($directionCandidate === null || $currentTs > $directionTs) {
                    $directionCandidate = $record;
                    $directionTs = $currentTs;
                }
            } else {
                if ($recordTime->lessThan($target->copy()->subSeconds($toleranceSeconds))) {
                    continue;
                }

                $currentTs = $recordTime->getTimestamp();
                if ($directionCandidate === null || $currentTs < $directionTs) {
                    $directionCandidate = $record;
                    $directionTs = $currentTs;
                }
            }
        }

        if ($directionCandidate !== null) {
            return $directionCandidate;
        }

        $bestRecord = null;
        $bestDiff = null;
        $bestTimestamp = null;

        foreach ($candidateRecords as $record) {
            if (empty($record->Tanggal_Absensi)) {
                continue;
            }

            try {
                $recordTime = Carbon::parse($record->Tanggal_Absensi);
            } catch (\Throwable $e) {
                continue;
            }

            $diff = abs($recordTime->diffInSeconds($target, false));
            $timestamp = $recordTime->getTimestamp();

            if ($bestRecord === null || $diff < $bestDiff || ($diff === $bestDiff && $timestamp > $bestTimestamp)) {
                $bestRecord = $record;
                $bestDiff = $diff;
                $bestTimestamp = $timestamp;
            }
        }

        return $bestRecord ?? $candidateRecords[0];
    }

    private function isRecordCloserToTarget(?string $candidateTime, ?string $currentTime, ?string $targetTime): bool
    {
        if (empty($candidateTime)) {
            return false;
        }

        if (empty($targetTime)) {
            return ! empty($candidateTime) && empty($currentTime);
        }

        try {
            $target = Carbon::parse($targetTime);
            $candidate = Carbon::parse($candidateTime);
            $candidateDiff = abs($candidate->diffInSeconds($target, false));

            if (empty($currentTime)) {
                return true;
            }

            $current = Carbon::parse($currentTime);
            $currentDiff = abs($current->diffInSeconds($target, false));

            return $candidateDiff < $currentDiff;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findWebAbsensiRecordByExactTimestamp(string $kodeKaryawan, ?string $targetDateTime): ?object
    {
        if (empty($targetDateTime)) {
            return null;
        }

        try {
            $targetCarbon = Carbon::parse($targetDateTime)->startOfSecond();
        } catch (\Throwable $e) {
            return null;
        }

        return DB::table('Transaksi_Absensi_Detail as d')
            ->where('d.Kode_Perusahaan', '001')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNotNull('d.filePath')
            /* Sebelumnya kolomnya diubah jadi teks gaya 120 lalu dibandingkan
               persis -- mematikan indeks, dan bergantung pada format teks.
               Rentang satu detik memberi hasil yang sama: gaya 120 memang
               memotong pada detik. */
            ->where('d.Tanggal_Absensi', '>=', $targetCarbon)
            ->where('d.Tanggal_Absensi', '<', $targetCarbon->copy()->addSecond())
            ->orderByDesc('d.Urut_Oto')
            ->select('d.Jenis', 'd.No_Transaksi', 'd.Alasan', 'd.filePath', 'd.Tanggal_Absensi')
            ->first();
    }

    public function getJamKerja(Request $request)
    {
        try {
            $Tanggal = date('Y-m-d', strtotime($request[0]));
            $Hari = date('N', strtotime($Tanggal));
            if ($Hari <= 6) {
                $Hari += 1;
            } elseif ($Hari == 7) {
                $Hari = 1;
            }

            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            // $query = "
            //     WITH KaryawanShiftPermanen AS (
            //         SELECT
            //             e.Kode_Karyawan,
            //             c.Jam_Masuk,
            //             c.Jam_Keluar,
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
            //         WHERE e.Kode_Perusahaan = '001'
            //         AND b.Hari = ?
            //         AND e.Kode_Karyawan = ?
            //         AND d.Periode <= ?
            //     ),
            //     ShiftPermanen AS (
            //         SELECT
            //             Kode_Karyawan,
            //             Jam_Masuk,
            //             Jam_Keluar
            //         FROM KaryawanShiftPermanen
            //         WHERE rn_periode = 1
            //     ),
            //     ShiftSementara AS (
            //         SELECT TOP 1
            //             e.Kode_Karyawan,
            //             c.Jam_Masuk,
            //             c.Jam_Keluar
            //         FROM HRIS_Shift_Kerja a
            //         INNER JOIN HRIS_Shift_Kerja_Detail b ON a.ID_Shift = b.ID_Shift
            //         INNER JOIN HRIS_Waktu_Kerja c ON b.ID_Waktu_Kerja = c.ID_Waktu_Kerja
            //         INNER JOIN HRIS_Shift_Sementara d ON a.ID_Shift = d.ID_Shift
            //         INNER JOIN Karyawan e ON d.Kode_Perusahaan = e.Kode_Perusahaan
            //                             AND d.Kode_Karyawan = e.Kode_Karyawan
            //         WHERE e.Kode_Perusahaan = '001'
            //         AND b.Hari = ?
            //         AND e.Kode_Karyawan = ?
            //         AND d.Tanggal = ?
            //         ORDER BY d.Urut DESC, d.Tanggal DESC
            //     )
            //     SELECT
            //         COALESCE(ss.Kode_Karyawan, sp.Kode_Karyawan) as Kode_Karyawan,
            //         COALESCE(ss.Jam_Masuk, sp.Jam_Masuk) as Jam_Masuk,
            //         COALESCE(ss.Jam_Keluar, sp.Jam_Keluar) as Jam_Keluar
            //     FROM ShiftPermanen sp
            //     FULL OUTER JOIN ShiftSementara ss ON sp.Kode_Karyawan = ss.Kode_Karyawan
            // ";

            $query = "select 
            Kode_Karyawan,
            CAST(Jam_Masuk AS VARCHAR) as Jam_Masuk,
            CAST(Jam_Keluar AS VARCHAR) as Jam_Keluar
            from N_HRIS_FN_GetStatusHariRange('001', ?, ?, ?)";

            $result = DB::select($query, [$Kode_Karyawan, $Tanggal, $Tanggal]);

            $dataFinal = collect($result)->first();
            // dd($dataFinal);
            $Jam_Masuk = $dataFinal->Jam_Masuk ?? '08:30';
            $Jam_Keluar = $dataFinal->Jam_Keluar ?? '16:30';

            return response()->json([
                'status' => 200,
                'data' => [
                    'Jam_Masuk' => $Jam_Masuk,
                    'Jam_Keluar' => $Jam_Keluar,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal Mengambil data jam Kerja getJamKerja '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data jam Kerja ',
                ],
                500,
            );
        }
    }

    /**
     * Shift hari ini untuk satu karyawan, sebagaimana dikirim ke ketiga halaman izin.
     *
     * ── Kenapa ini punya rumah ───────────────────────────────────────────────
     *
     * indexIzinAdmin(), indexIzin() dan indexIzinDH() menuliskan kueri yang sama
     * beserta pemetaannya masing-masing — tiga salinan, dan ketiganya juga
     * membawa delapan baris hitungan $Hari yang tidak pernah dibaca siapa pun.
     *
     * ── Perbedaan yang DULU tersembunyi, sekarang jadi parameter ─────────────
     *
     * Hanya jalur admin yang memperkaya hasilnya dengan status hari (dan
     * memberi nilai cadangan kalau kueri shift tidak mengembalikan baris).
     * Selama aturannya tersebar di tiga salinan, perbedaan itu tak terbaca
     * sebagai keputusan — ia terbaca seperti kelalaian. Sekarang ia sebuah
     * argumen, jadi siapa pun yang membacanya langsung melihat ada dua mode.
     *
     * Dan begitu terlihat, satu hal jadi kentara: pemasangannya TERBALIK.
     * izinPageAdmin.vue tidak pernah menyentuh status_hari maupun is_day_off,
     * sedangkan izinPage.vue justru bergantung padanya —
     * `:disabled="isTodayOff"` pada tombol "Ajukan Izin" dan spanduk "Hari ini
     * adalah Day Off … Pengajuan izin dinonaktifkan". Karena indexIzin() tidak
     * pernah mengirim kuncinya, isTodayOff selalu false dan spanduk itu tidak
     * pernah muncul.
     *
     * Pemasangan itu SENGAJA dibiarkan apa adanya di sini: menyalakannya berarti
     * mulai memblokir tombol pengajuan pada hari libur, dan itu keputusan
     * produk, bukan perapian kode.
     *
     * @param  bool  $denganStatusHari  true = tambahkan status_hari,
     *                                  status_hari_label, is_day_off, dan beri
     *                                  objek cadangan kalau kueri kosong.
     *                                  false = kembalikan apa adanya, termasuk
     *                                  null bila tidak ada baris.
     */
    private function shiftHariIni(string $kodeKaryawan, string $tanggal, bool $denganStatusHari): ?object
    {
        $query = " select 
                a.Kode_Karyawan,
                v.nama_divisi,
                v.nama_sub_divisi,
                k.UserID_Absen,
                k.Nama,
                a.Jam_Masuk,
                a.Jam_Keluar,
                a.ID_Shift,
                a.Nama_Shift,
                a.JENIS_SHIFT_DIPILIH as Jenis_Shift,
                a.Tanggal as Periode,
                a.ECOFX AS Jam_selesai_absen
            from N_HRIS_FN_GetStatusHariRange('001', ?, ?, ?) a JOIN Karyawan k ON a.Kode_Karyawan = k.Kode_Karyawan JOIN View_Divisi_Sub_Divisi v ON k.ID_Divisi_Sub_Divisi = v.ID_DIVISI_SUB_DIVISI";

        $result = DB::select($query, [$kodeKaryawan, $tanggal, $tanggal]);

        $dataFinal = collect($result)
            ->map(function ($item) {
                if (! empty($item->UserID_Absen)) {
                    $item->UserID_Absen = Crypt::encryptString($item->UserID_Absen);
                }

                return $item;
            })
            ->first();

        if (! $denganStatusHari) {
            return $dataFinal;
        }

        if (! $dataFinal) {
            $dataFinal = (object) ['Periode' => $tanggal];
        }

        $statusHariInfo = $this->getStatusHariInfo($kodeKaryawan, $tanggal);
        $dataFinal->status_hari = $statusHariInfo['status_hari'];
        $dataFinal->status_hari_label = $statusHariInfo['status_hari_label'];
        $dataFinal->is_day_off = $statusHariInfo['is_day_off'];

        return $dataFinal;
    }

    /**
     * Rantai approver izin milik seorang pemohon, terurut menurut order_flow.
     *
     * Dipakai halaman admin dan halaman izin pribadi; indexIzinDH() memang tidak
     * memerlukannya, dan itu sebabnya ia tidak memanggilnya.
     */
    private function approverIzin(string $kodeKaryawan): \Illuminate\Support\Collection
    {
        return DB::table('HRIS_Approval_Flow as h')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan')
            ->where('h.Kode_Karyawan_Requester', $kodeKaryawan)
            ->whereNull('h.Status')
            ->select('h.*', 'k.Nama')
            ->orderBy('h.order_flow')
            ->get();
    }

    public function indexIzinAdmin()
    {
        try {
            $tanggal = date('Y-m-d');
            $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
            $namaKaryawan = Auth::user()->karyawan->Nama;

            return inertia('ESS/Attendance/Leave/Admin/izinPageAdmin', [
                'dataShift' => $this->shiftHariIni($kodeKaryawan, $tanggal, true),
                'namaKaryawan' => $namaKaryawan,
                'IzinApprover' => $this->approverIzin($kodeKaryawan),
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal menampilkan page izin: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page izin!');

            return back();
        }
    }

    public function indexIzin()
    {
        try {
            $tanggal = date('Y-m-d');
            $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
            $namaKaryawan = Auth::user()->karyawan->Nama;

            return inertia('ESS/Attendance/Leave/Personal/izinPage', [
                'dataShift' => $this->shiftHariIni($kodeKaryawan, $tanggal, false),
                'namaKaryawan' => $namaKaryawan,
                'IzinApprover' => $this->approverIzin($kodeKaryawan),
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal menampilkan page izin: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page izin!');

            return back();
        }
    }

    public function indexIzinDH()
    {
        try {
            $tanggal = date('Y-m-d');
            $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;
            $namaKaryawan = Auth::user()->karyawan->Nama;

            return inertia('ESS/Attendance/Leave/Personal/izinPageDH', [
                'dataShift' => $this->shiftHariIni($kodeKaryawan, $tanggal, false),
                'namaKaryawan' => $namaKaryawan,
            ]);
        } catch (\Throwable $e) {
            Log::channel('izinLog')->error('Gagal menampilkan page izinDH: '.$e->getMessage());
            Alert::error('error', 'Terjadi Error saat menampilkan page izinDH!');

            return back();
        }
    }

    public function indexLembur()
    {
        return inertia('ESS/Attendance/Overtime/Request/LemburFormModern');
    }

    public function addLembur(Request $request)
    {
        $entries = $request->input('data');

        foreach ($entries as $entry) {
            $validator = Validator::make($entry, [
                'alasan' => 'required|string',
                'start' => 'required|date_format:H:i',
                'end' => 'required|date_format:H:i|after:start',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors(),
                    ],
                    422,
                );
            }

            // Lembur::create([
            //     'alasan' => $entry['alasan'],
            //     'start' => $entry['start'],
            //     'end' => $entry['end'],
            //     'date' => $entry['date'],
            // ]);
        }

        return response()->json($entries);
    }

    public function updateApproval(Request $request)
    {
        try {
        } catch (\Exception $dbException) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Error menyimpan data absensi ke DB: '.$dbException->getMessage(), [
                'file' => $dbException->getFile(),
                'line' => $dbException->getLine(),
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan saat menyimpan data absensi. Silakan coba lagi.'.
                        $dbException->getMessage(),
                ],
                500,
            );
        }
    }

    public function submitAbsen(Request $request)
    {
        $image = $request->input('image');
        $randomHash = $request->input('watermark_hash');
        $imageHash = $request->input('image_hash');

        $calculatedHash = hash('sha256', $image.'|'.$randomHash);

        if ($calculatedHash !== $imageHash) {
            return response()->json(['error' => 'Hash tidak valid. Foto mungkin dimanipulasi.'], 400);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ]);

        if (! $request->hasFile('photo')) {
            Log::channel('absensiLog')->error('Tidak ada foto yang diupload');

            return response()->json(
                [
                    'status' => 400,
                    'message' => 'Tidak ada foto yang diupload.',
                ],
                400,
            );
        }
        $lang = $request->input('location_longitude');
        $lat = $request->input('location_latitude');
        $accuracy = $request->input('accuracy');
        // if(!$lang || $lang == 0 || !$lat || !$lat == 0 || !$accuracy){
        //     Log::channel('absensiLog')->error('Tidak menyalakan lokasi');
        //     return response()->json([
        //         'status' => 400,
        //         'message' => 'Harap Nyalakan Lokasi Anda!'
        //     ], 400);
        // }
        $Tanggal = date('Y-m-d');
        $Kode_Karyawan = Crypt::decryptString($request->Kode_Karyawan);
        // if ($this->shiftRequestService->isOffDay($Kode_Karyawan, $Tanggal)) {
        //     return response()->json([
        //         'status' => 400,
        //         'message' => 'Hari ini adalah OFF/Libur. Absensi tidak dapat dilakukan pada tanggal tersebut.'
        //     ], 400);
        // }

        $file = $request->file('photo');
        $photoPath = null; // Inisialisasi variabel untuk path foto di GCS

        if (! $file->isValid()) {
            Log::channel('absensiLog')->error('File foto absensi tidak valid', [
                'upload_error_code' => $file->getError(),
            ]);

            return response()->json(
                [
                    'status' => 400,
                    'message' => 'File foto absensi tidak valid.',
                ],
                400,
            );
        }

        $temporaryPath = $file->getRealPath() ?: $file->getPathname();

        try {
            // Path di GCS: absensi/tahun/bulan/uuid.ext
            $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
            $fileName = Str::uuid().'.'.$extension;
            $folderPath = 'absensi/'.date('Y/m');
            $photoPath = $folderPath.'/'.$fileName;

            try {
                $storedPath = $file->storeAs($folderPath, $fileName, 'gcs');

                if (! is_string($storedPath) || trim($storedPath) === '') {
                    throw new \RuntimeException('Path file hasil upload tidak valid.');
                }

                $photoPath = trim($storedPath);
            } catch (\ValueError $uploadException) {
                if (stripos($uploadException->getMessage(), 'Path cannot be empty') === false) {
                    throw $uploadException;
                }

                if (! is_string($temporaryPath) || trim($temporaryPath) === '') {
                    throw new \RuntimeException('File upload tidak memiliki temporary path yang valid.');
                }

                $stream = fopen($temporaryPath, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('Gagal membaca file temporary untuk upload.');
                }

                try {
                    $stored = Storage::disk('gcs')->put($photoPath, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if (! $stored) {
                    throw new \RuntimeException('Gagal menyimpan foto absensi ke storage.');
                }
            }

            DB::beginTransaction();
            try {
                $format_tanggal_sekarang = date('m').'-'.date('y');

                // no Transaksi via COUNTER ATOMIK (bebas race duplikat + tanpa scan non-sargable).
                $prefix = 'AB'.$format_tanggal_sekarang.'-';
                $no_faktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                    $prefix,
                    fn () => DB::table('Transaksi_Absensi')
                        ->where('No_Transaksi', 'like', $prefix.'%')
                        ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
                );

                $Tanggal = date('Y-m-d');
                $Jam = date('H:i:s');

                $UserID = Hashids::connection('custom')->decode($request->UserID);
                $UserID_Absen = (int) Crypt::decryptString($request->UserID_Absen);
                // dd($Kode_Karyawan);
                if (! $UserID || ! $UserID_Absen || ! $Kode_Karyawan) {
                    Log::channel('absenLog')->error('Tidak ada data User');

                    DB::rollBack();

                    // dd($UserID, $UserID_Absen, $Kode_Karyawan);
                    return response()->json(
                        [
                            'status' => 500,
                            'message' => 'Tidak ada Data User',
                        ],
                        400,
                    );
                }
                $tanggal_absen = now()->format('Y-m-d H:i:s');
                DB::table('Transaksi_Absensi')->insert([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $no_faktur,
                    'Tanggal' => $Tanggal,
                    'Jam' => $Jam,
                    'UserID' => $UserID[0],
                ]);

                DB::table('Transaksi_Absensi_Detail')->insert([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $no_faktur,
                    'Kode_Karyawan' => $Kode_Karyawan,
                    'Jenis' => $request->Jenis,
                    'Tanggal_Absensi' => $tanggal_absen,
                    'Alasan' => preg_replace('/\s+/', ' ', $request->alasan),
                    'filePath' => $photoPath,
                    'image_hash' => $imageHash,
                    'watermark_hash' => $randomHash,
                    'lat' => $lat,
                    'lang' => $lang,
                    'accuracy' => $accuracy,
                ]);

                DB::table('CHECKINOUT ')->insert([
                    'USERID' => $UserID_Absen,
                    'CHECKTIME' => $tanggal_absen,
                    'VERIFYCODE' => '255',
                    'SENSORID' => '180',
                    'sn' => '1',
                ]);

                DB::commit();
                $this->attendanceDirtyResolver->markByKodeKaryawan(
                    $Kode_Karyawan,
                    $Tanggal,
                    $Tanggal,
                    'CHECKINOUT_CREATED',
                    'ABSENSI_SUBMIT_ABSEN',
                    1,
                );
                Alert::success('Success', 'Absen Berhasil Disimpan!');

                return response()->json([
                    'status' => 200,
                    'message' => 'Absensi berhasil disimpan!',
                    'photo_url' => $photoPath,
                ]);
            } catch (\Exception $dbException) {
                DB::rollback();

                Log::channel('absensiLog')->error('Error menyimpan data absensi ke DB: '.$dbException->getMessage(), [
                    'file' => $dbException->getFile(),
                    'line' => $dbException->getLine(),
                ]);

                // Jika foto sudah terupload tapi data DB gagal, kita perlu menghapus fotonya
                if ($photoPath && Storage::disk('gcs')->exists($photoPath)) {
                    Storage::disk('gcs')->delete($photoPath);
                    Log::warning('Foto GCS dihapus karena kegagalan DB: '.$photoPath);
                }

                return response()->json(
                    [
                        'status' => 500,
                        'message' => 'Terjadi kesalahan saat menyimpan data absensi. Silakan coba lagi.'.
                            $dbException->getMessage(),
                    ],
                    500,
                );
            } catch (\Throwable $dbThrowable) {
                /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                   jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                   supaya handler global tetap mencatat stack trace-nya dan responsnya
                   tak berubah sedikit pun. */
                DB::rollBack();

                throw $dbThrowable;
            }
        } catch (\Exception $e) {
            // Ini akan menangkap error dari proses upload ke GCS (atau error lain sebelum DB transaction)
            Log::channel('absensiLog')->error('Error saat upload foto absensi ke GCS: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(), // Tambahkan trace untuk debugging lebih lanjut
            ]);

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Gagal mengupload foto absensi. Silakan periksa kredensial GCS atau koneksi Anda.',
                ],
                500,
            );
        }
    }

    public function destroyIzin(Request $request)
    {
        try {
            DB::beginTransaction();
            $pengguna = Auth::user()->karyawan;
            $userid_web = Auth::user()->Id_Users;

            $sudahAdaApprove = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $request->No_Transaksi)
                ->whereNotNull('Flag_Approval')
                ->exists();

            if ($sudahAdaApprove) {
                DB::rollBack();

                return response()->json(
                    [
                        'status' => 400,
                        'message' => 'Data tidak bisa dihapus karena sudah ada approver yang menyetujui.',
                    ],
                    400,
                );
            }

            $recordsDeleted = 0;

            $recordsDeleted += DB::table('Transaksi_Sakit_Izin')
                ->where('No_Transaksi', $request->No_Transaksi)
                ->update([
                    'Status' => 'Y',
                    'Updated_By' => $userid_web,
                    'Updated_At' => now(),
                ]);

            $recordsDeleted += DB::table('Transaksi_Terlambat_Pulang_Cepat')
                ->where('No_Transaksi', $request->No_Transaksi)
                ->update([
                    'Status' => 'Y',
                    'Updated_By' => $userid_web,
                    'Updated_At' => now(),
                ]);

            $recordsDeleted += DB::table('Transaksi_Cuti')
                ->where('No_Transaksi', $request->No_Transaksi)
                ->update([
                    'Status' => 'Y',
                    'Updated_By' => $userid_web,
                    'Updated_At' => now(),
                ]);

            if ($recordsDeleted === 0) {
                DB::rollBack();
                Log::channel('izinLog')->error('Data tidak ditemukan');

                return response()->json(
                    [
                        'status' => 404,
                        'message' => 'Data tidak ditemukan.',
                    ],
                    404,
                );
            }

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $request->No_Transaksi,
                'IZIN_TRANSACTION_CANCELLED',
                'ABSENSI_DESTROY_IZIN',
                1,
            );

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menghapus data!',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('izinLog')->error('Gagal menghapus data: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'message' => 'Terjadi kesalahan pada server. Silakan coba lagi. Detail: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Ringkasan dampak pembatalan (termasuk berapa hari cuti yang akan dikembalikan)
     * untuk ditampilkan di modal konfirmasi sebelum admin benar-benar membatalkan.
     */
    public function previewCancelIzinAdmin(Request $request, IzinCancellationService $service)
    {
        $validated = $request->validate([
            'No_Transaksi' => ['required', 'string', 'max:30'],
        ]);

        $hasil = $service->buildPreview($validated['No_Transaksi']);

        if (! $hasil['ok']) {
            return ResponseHelper::error($hasil['message'], 404);
        }

        return ResponseHelper::success($hasil['preview'], 'Preview pembatalan berhasil dibuat');
    }

    /**
     * Batalkan transaksi izin/cuti/terlambat. Berbeda dengan destroy lama, transaksi
     * yang SUDAH DISETUJUI pun boleh dibatalkan — saldo cuti yang terlanjur terpotong
     * dikembalikan oleh IzinCancellationService.
     */
    public function cancelIzinAdmin(Request $request, IzinCancellationService $service)
    {
        $validated = $request->validate(
            [
                'No_Transaksi' => ['required', 'string', 'max:30'],
                'alasan' => ['required', 'string', 'min:'.IzinCancellationService::MIN_REASON, 'max:500'],
                'fingerprint' => ['required', 'string', 'max:64'],
            ],
            [
                'alasan.required' => 'Alasan pembatalan wajib diisi.',
                'alasan.min' => 'Alasan pembatalan minimal '.IzinCancellationService::MIN_REASON.' karakter.',
            ],
        );

        $outcome = $service->execute(
            $validated['No_Transaksi'],
            $validated['alasan'],
            $validated['fingerprint'],
            Auth::user()->karyawan->Kode_Karyawan,
        );

        if (! $outcome['ok']) {
            return ResponseHelper::error($outcome['message'], $outcome['code']);
        }

        return ResponseHelper::success($outcome['result'], 'Transaksi berhasil dibatalkan');
    }

    protected function uploadIzinAttachment(Request $request): ?string
    {
        if (! $request->hasFile('file') || ! $request->file('file')->isValid()) {
            return null;
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = 'izinSakitCuti/'.date('Y/m').'/'.Str::uuid().'.'.$extension;
        $temporaryPath = $file->getRealPath() ?: $file->getPathname();
        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Gagal membaca lampiran izin.');
        }
        try {
            if (! Storage::disk('gcs')->put($path, $stream)) {
                throw new \RuntimeException('Gagal menyimpan lampiran izin ke GCS.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }
}
