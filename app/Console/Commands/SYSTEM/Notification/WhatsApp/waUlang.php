<?php

namespace App\Console\Commands\SYSTEM\Notification\WhatsApp;

use App\Helpers\Whatsapp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class waUlang extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'izin:waUlang';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command ini berfungsi untuk memberi notifikasi ulang approver pada hari kemudian';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hari = now()->toDateString();

        $this->info('Memulai proses pencarian approval (H+1 & H+2) yang perlu notifikasi.');
        Log::channel('waUlangLog')->info("Memulai proses pencarian approval (H+1 & H+2) yang perlu notifikasi pada {$hari}");

        try {
            // Tentukan tanggal kemarin dan lusa dari hari ini
            $kemarin = Carbon::yesterday()->toDateString();
            $lusa = Carbon::today()->subDays(2)->toDateString();
            // dd($lusa);
            $datasPending = DB::table('HRIS_Approval_Request as a')
                ->join('HRIS_Approval_Flow as b', 'a.Flow_Id', '=', 'b.id')
                ->whereNull('a.Flag_Approval')
                ->whereNull('b.Status')
                ->where(function ($query) use ($kemarin, $lusa) {
                    $query->whereDate('a.created_at', $kemarin)
                        ->orWhereDate('a.created_at', $lusa);
                })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('HRIS_Approval_Request as prev')
                        ->join('HRIS_Approval_Flow as prev_flow', 'prev.Flow_Id', '=', 'prev_flow.id')
                        ->whereColumn('prev.No_Transaksi', 'a.No_Transaksi')
                        ->whereRaw('prev_flow.order_flow < b.order_flow')
                        ->where(function ($q) {
                            $q->whereNull('prev.Flag_Approval')
                                ->whereNull('prev_flow.Status')
                                ->orWhere('prev.Flag_Approval', '!=', 'Y');
                        });
                })
                ->whereIn('a.id', function ($query) {
                    $query->selectRaw('MIN(t1.id)')
                        ->from('HRIS_Approval_Request as t1')
                        ->join('HRIS_Approval_Flow as t2', 't1.Flow_Id', '=', 't2.id')
                        ->whereNull('t1.Flag_Approval')
                        ->whereNull('t2.Status')
                        ->groupBy('t1.No_Transaksi');
                })

            // Cek apakah di 3 tabel transaksi ada yang statusnya 'Y'
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from(DB::raw("
                        (
                            SELECT No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat WHERE Status = 'Y'
                            UNION ALL
                            SELECT No_Transaksi FROM Transaksi_Sakit_Izin WHERE Status = 'Y'
                            UNION ALL
                            SELECT No_Transaksi FROM Transaksi_Cuti WHERE Status = 'Y'
                        ) as t_check
                    "))
                        ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                })
                ->select('a.*', 'b.order_flow')
                ->orderBy('a.No_Transaksi')
                ->get();

            // dd($datasPending);

            if ($datasPending->isEmpty()) {
                $this->warn('Tidak ada approval dari H+1 atau H+2 yang perlu diberi notifikasi.');
                Log::channel('waUlangLog')->info("Tidak ada approval dari H+1 atau H+2 yang perlu diberi notifikasi {$hari}");

                return 0;
            }

            $groupedApprovals = $datasPending->groupBy('Flow_Id');

            foreach ($groupedApprovals as $flowId => $requests) {

                $approver = DB::table('HRIS_Approval_Flow as a')
                    ->join('Karyawan as b', 'a.Kode_Karyawan', '=', 'b.Kode_Karyawan')
                    ->where('a.id', $flowId)
                    ->whereNull('a.Status')
                    ->first();

                if (! $approver) {
                    Log::channel('waUlangLog')->warning("Approver tidak ditemukan  pada {$hari}.", ['flow_id' => $flowId]);

                    continue;
                }

                $nomorHP = $approver->HP;
                if (empty($nomorHP) || $nomorHP === '-') {
                    $this->warn("Nomor HP tidak valid untuk {$approver->Nama}.", ['flow_id' => $flowId]);
                    Log::channel('waUlangLog')->info("Nomor HP tidak valid untuk {$approver->Nama}. pada {$hari}", ['flow_id' => $flowId]);

                    continue;
                }
                $requestIds = $requests->pluck('No_Transaksi')->toArray();
                $requestIdsString = implode(', ', $requestIds);

                DB::table('HRIS_Approval_Request')
                    ->where('Flow_Id', $flowId)
                    ->whereIn('No_Transaksi', $requestIds)
                    ->update(['angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]); // COALESCE handle jika nilainya NULL

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $nomorHP,
                    'type' => 'template',
                    'template' => [
                        'name' => 'notif_approver_ulang',
                        'language' => ['code' => 'id', 'policy' => 'deterministic'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $approver->Nama, // Parameter pertama untuk nama approver
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $requestIdsString, // Parameter pertama untuk nama approver
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

                try {
                    // ... Sisa kode pengiriman dan logging Anda tetap sama ...
                    $response = Whatsapp::send_message($pesan, [
                        'kode_karyawan' => $approver->Kode_Karyawan ?? null,
                        'nama' => $approver->Nama ?? null,
                        'hp' => $nomorHP ?? $approver->HP ?? $pesan['to'] ?? null,
                        'no_transaksi' => $requestIdsString ?? null,
                        'module' => 'HCIS',
                        'source_context' => 'WA_ULANG_COMMAND',
                    ]);
                    if (isset($response['error'])) {
                        Log::channel('waUlangLog')->warning("Gagal mengirim WhatsApp  pada {$hari}.", [/* data log */]);
                    } else {
                        Log::channel('waUlangLog')->info("WhatsApp berhasil dikirim  pada {$hari}.", [/* data log */]);
                    }
                } catch (\Exception $e) {
                    Log::channel('waUlangLog')->error("Pengecualian saat mengirim WhatsApp  pada {$hari}", [/* data log */]);
                }
            }
        } catch (\Exception $e) {
            Log::channel('waUlangLog')->error("Kesalahan umum proses notifikasi.  pada {$hari}", [
                'pesan_pengecualian' => $e->getMessage(),
            ]);

            return 1;
        }

        $this->info('Proses notifikasi ulang WhatsApp (H+1 & H+2) selesai.');
        Log::channel('waUlangLog')->info("Proses notifikasi ulang wa (H+1 dan H+2 selesai) pada {$hari}");

        return 0;
    }
}
