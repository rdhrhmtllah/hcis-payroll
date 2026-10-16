<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use App\Jobs\ESS\Attendance\Leave\Notification\SendExpiredIzinWhatsappJob;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpiredIzin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'izin:expiredIzin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'command ini berfungsi untuk memflag izin yang sudah lebih dari 2 hari tidak di acc oleh approver (HARIAN)';

    public function __construct(private readonly AttendanceDirtyResolverService $attendanceDirtyResolver)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hari = now()->toDateString();
        $KmrnLusa = Carbon::today()->subDay(2);
        // dd($KmrnLusa);
        $this->info("Memulai proses pencarian approval yang perlu diexpiredkan untuk tanggal {$KmrnLusa->toDateString()} Kebelakang.");
        Log::channel('expiredIzin')->info("Memulai proses pencarian approval yang perlu diexpiredkan untuk tanggal {$KmrnLusa->toDateString()} pada {$hari}.");

        try {
            $datasKemarin = DB::table('HRIS_Approval_Request')
                ->whereNull('Flag_Approval')
                ->whereNull('Expired_At')
                ->where(function ($q) {
                    $q->where('No_Transaksi', 'like', 'TP%')
                        ->orWhere('No_Transaksi', 'like', 'IS%');
                })
                ->where('created_at', '<=', now()->subDays(2))
                // ->whereDate("created_at", '>=', '2026-01-21')
                ->get();

            // ->where("angka_wa", '>',2)
            // dd($datasKemarin);
            if ($datasKemarin->isEmpty()) {
                $this->warn("Tidak ada Transaksi yang expired pada tanggal {$KmrnLusa} ");
                Log::channel('expiredIzin')->info("Tidak ada Transaksi yang expired pada tanggal {$KmrnLusa}, hari {$hari}");

                return 0;
            }

            $groupedNo_Transaksi = $datasKemarin->groupBy('No_Transaksi');
            foreach ($groupedNo_Transaksi as $No_TransaksiId => $requests) {
                $prefix = substr($No_TransaksiId, 0, 2);
                if ($prefix == 'IS') {
                    $targetRow = DB::table('Transaksi_Sakit_Izin_Detail as b')
                        ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                        ->where('a.No_Transaksi', $No_TransaksiId)
                        ->whereNull('a.Status')
                        ->whereNull('b.Flag_Approval')
                        ->first();

                    if (! $targetRow) {
                        // Biar gak ke cek lagi dihari kemudian, langsung update Expired_At di HRIS_Approval_Request
                        DB::table('HRIS_Approval_Request')
                            ->where('No_Transaksi', $No_TransaksiId)
                            ->update([
                                'Expired_At' => now(),
                            ]);

                        $this->warn("Data detail izin tidak ditemukan atau sudah diapprove/tolak/sudah Expired {$No_TransaksiId} created_at pada {$requests->first()->created_at}!");
                        Log::channel('expiredIzin')->warning('Data detail izin tidak ditemukan atau sudah diapprove/tolak/sudah Expired', [
                            'No_Transaksi' => $No_TransaksiId,
                        ]);

                        continue;
                    }

                    $updated = DB::table('Transaksi_Sakit_Izin_Detail as b')
                        ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                        ->where('a.No_Transaksi', $No_TransaksiId)
                        ->whereNull('a.Status')
                        ->whereNull('b.Flag_Approval')
                        ->update(['b.Flag_Approval' => 'P']);

                    if (! $updated) {
                        $this->warn("Gagal memperbarui status terlambat pulang cepat menjadi 'P' untuk Expired {$No_TransaksiId}!");
                        Log::channel('expiredIzin')->warning("Gagal memperbarui status izin menjadi 'P' untuk Expired", [
                            'No_Transaksi' => $No_TransaksiId,
                        ]);

                        continue;
                    }

                    $this->info("Transaksi {$No_TransaksiId} Sudah Diexpiredkan karena sudah lebih dari 3 hari tanpa di approve oleh approver!");
                    Log::channel('expiredIzin')->info('Transaksi sudah diexpiredkan karena sudah lebih dari 3 hari tanpa di approve oleh approver', [
                        'No_Transaksi' => $No_TransaksiId,
                    ]);
                    $detail = DB::table('Transaksi_Sakit_Izin_Detail')
                        ->where('No_Transaksi', $No_TransaksiId)
                        ->first();

                } elseif ($prefix == 'TP') {

                    $targetRow = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                        ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                        ->where('a.No_Transaksi', $No_TransaksiId)
                        ->whereNull('a.Status')
                        ->whereNull('b.Flag_Approval')
                        ->first();

                    if (! $targetRow) {
                        // Biar gak ke cek lagi dihari kemudian, langsung update Expired_At di HRIS_Approval_Request
                        DB::table('HRIS_Approval_Request')
                            ->where('No_Transaksi', $No_TransaksiId)
                            ->update([
                                'Expired_At' => now(),
                            ]);

                        $this->warn("Data detail izin tidak ditemukan atau sudah diapprove/tolak/sudah Expired {$No_TransaksiId} created_at pada {$requests->first()->created_at}!");
                        Log::channel('expiredIzin')->warning('Data detail terlambat pulang cepat tidak ditemukan atau sudah diapprove/tolak/sudah Expired', [
                            'No_Transaksi' => $No_TransaksiId,
                        ]);

                        continue;
                    }
                    $updated = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                        ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                        ->where('a.No_Transaksi', $No_TransaksiId)
                        ->whereNull('a.Status')
                        ->whereNull('b.Flag_Approval')
                        ->update(['b.Flag_Approval' => 'P']);

                    if (! $updated) {
                        $this->warn("Gagal memperbarui status terlambat pulang cepat menjadi 'P' untuk Expired {$No_TransaksiId}!");
                        Log::channel('expiredIzin')->warning("Gagal memperbarui status terlambat pulang cepat menjadi 'P' untuk Expired", [
                            'No_Transaksi' => $No_TransaksiId,
                        ]);

                        continue;
                    }

                    $this->info("Transaksi {$No_TransaksiId} Sudah Diexpiredkan karena sudah lebih dari 3 hari tanpa di approve oleh approver!");
                    Log::channel('expiredIzin')->info('Transaksi sudah diexpiredkan karena sudah lebih dari 3 hari tanpa di approve oleh approver', [
                        'No_Transaksi' => $No_TransaksiId,
                    ]);

                    $detail = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                        ->where('No_Transaksi', $No_TransaksiId)
                        ->first();
                }

                if (! $detail) {
                    Log::channel('expiredIzin')->warning('detail tidak ditemukan', [
                        'No_Transaksi' => $No_TransaksiId,
                    ]);

                    continue;
                }

                DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $No_TransaksiId)
                    ->update([
                        'Expired_At' => now(),
                    ]);

                $this->attendanceDirtyResolver->markFromTransaction(
                    $No_TransaksiId,
                    $prefix === 'TP' ? 'TP_EXPIRED' : 'IZIN_EXPIRED',
                    'COMMAND_EXPIRED_IZIN',
                    1,
                );

                $batasTanggal = now()->subMonthNoOverflow()->day(21)->startOfDay();

                $isOldData = Carbon::parse($requests->first()->created_at)->lt($batasTanggal);

                if ($isOldData) {
                    Log::channel('expiredIzin')->info('Skip kirim WA karena data lama', [
                        'No_Transaksi' => $No_TransaksiId,
                        'created_at' => $requests->first()->created_at,
                        'batas' => $batasTanggal,
                    ]);

                    continue;
                }

                $izinID = $detail->Kode_Karyawan;

                $requester = DB::table('Karyawan')
                    ->where('Kode_Karyawan', $izinID)
                    ->first();
                if (! $requester) {
                    Log::channel('expiredIzin')->warning('Karyawan tidak ditemukan', [
                        'Kode_Karyawan' => $izinID,
                        'No_Transaksi' => $No_TransaksiId,
                    ]);

                    continue;
                }

                $nomorHP = $requester->HP;

                if (empty($nomorHP) || $nomorHP === '-') {
                    $this->warn("Tidak dapat mengirim pesan ke {$requester->Nama} karena tidak ada nomor HP yang valid untuk Transaksi: {$No_TransaksiId}!");
                    Log::channel('expiredIzin')->warning('Nomor HP tidak ada atau tidak valid untuk approver.', [
                        'nama_approver' => $requester->Nama,
                        'kode_karyawan_approver' => $requester->Kode_Karyawan,
                        'nomor_hp_mentah' => $requester->HP,
                        'No_Transaksi' => $No_TransaksiId,
                    ]);

                    continue;
                }
                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $nomorHP,
                    'type' => 'template', // Menggunakan template jika Anda sudah punya template yang cocok
                    'template' => [
                        'name' => 'confirm_izin_tolak', // Nama template Anda
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
                                        'text' => $requester->Nama, // Parameter pertama untuk nama approver
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $No_TransaksiId, // Parameter pertama untuk nama approver
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Sistem karena lewat dari 3 hari', // Parameter pertama untuk nama approver
                                    ],
                                    // Anda mungkin perlu menambahkan parameter lain di sini
                                    // sesuai dengan template 'pakai_absen' Anda
                                    // Jika template Anda bisa menerima list ID, tambahkan di sini
                                    // Contoh:
                                    // [
                                    //     "type" => "text",
                                    //     "text" => $requestIdsString
                                    // ]
                                ],
                            ],
                        ],
                    ],
                ];

                // Kirim WA via queue (1 job per pesan). Panggilan sinkron di
                // loop (timeout 10s/pesan) berisiko menabrak cap ~300s request
                // schedule:run saat transaksi expired banyak.
                SendExpiredIzinWhatsappJob::dispatch(
                    (string) ($requester->Kode_Karyawan ?? $izinID),
                    (string) ($requester->Nama ?? '-'),
                    (string) $nomorHP,
                    (string) $No_TransaksiId,
                    $pesan,
                );

                Log::channel('expiredIzin')->info('Pesan WhatsApp expired izin di-dispatch ke queue.', [
                    'No_Transaksi' => $No_TransaksiId,
                    'Kode_Karyawan' => $izinID,
                ]);

            }
        } catch (\Exception $e) {
            Log::channel('expiredIzin')->error('Kesalahan umum selama proses notifikasi WhatsApp.', [
                'tanggal_proses' => $KmrnLusa->toDateString(),
                'pesan_pengecualian' => $e->getMessage(),
                'kode_pengecualian' => $e->getCode(),
                'trace_pengecualian' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        $this->info("Selesai memproses Expired izin untuk tanggal {$KmrnLusa->toDateString()}.");
        Log::channel('expiredIzin')->info("Selesai memproses Expired izin untuk tanggal {$KmrnLusa->toDateString()}, hari {$hari}.");

        return 0;

    }
}
