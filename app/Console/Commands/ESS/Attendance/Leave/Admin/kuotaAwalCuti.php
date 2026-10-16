<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class kuotaAwalCuti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:kuotaAwalCuti';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menambahkan 12 kuota cuti di 1 tahun pertama karyawan (HARIAN)';

    /**
     * Execute the console command.
     */
    public function __construct(TransactionService $transactionService)
    {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    public function handle()
    {
        //
        $hari = now()->toDateString();
        $now = Carbon::now();
        $ID_CUTI_TAHUNAN = 1;
        Log::channel('kuotaAwalCuti')->info(
            "Proses cuti PKWT untuk 1 tahun Pemberian 12 kuota cuti pada {$hari} telah Dimulai!...{$hari}",
        );

        // Cari karyawan yang masa kerjanya sudah 1 tahun pada hari ini dan -2 hari seblum sebagai proteksi down server
        // Mitigasi jika server mati dalam jangka panjang, diharuskan jalankan manual command ini dengan range start dan end tanggal yang sesuai
        $start = $now->clone()->subYear()->subDays(2)->toDateString();
        $end = $now->clone()->toDateString();

        $Karyawans = Karyawan::where('Aktif', 'Y')
            ->where('Status_Kontrak', 'PKWT')
            ->whereBetween('Tgl_Masuk', [$start, $end])
            ->get();

        if ($Karyawans->isEmpty()) {
            $this->info("Tidak ada Karyawan yang pas 1 tahun pada {$hari} !");
            Log::channel('kuotaAwalCuti')->info("Tidak ada Karyawan yang pas 1 tahun pada...{$hari}");

            return 0;
        }

        /*
         * Siapa yang SUDAH pernah diberi kuota awal ditanyakan sekali untuk
         * seluruh daftar, bukan satu exists() per karyawan. Transaksi per
         * karyawan di bawah tetap dipertahankan: keatomikannya memang per orang.
         */
        $sudahPernahDiberi = DB::table('N_HRIS_Log_Buku_Cuti')
            ->join('HRIS_Buku_Cuti', 'N_HRIS_Log_Buku_Cuti.Id_Buku_Cuti', '=', 'HRIS_Buku_Cuti.Id_Buku_Cuti')
            ->whereIn('HRIS_Buku_Cuti.Kode_Karyawan', $Karyawans->pluck('Kode_Karyawan')->all())
            ->where('N_HRIS_Log_Buku_Cuti.Kategori_Transaksi', 'CUTI_AWAL_TAHUN_PERTAMA')
            ->distinct()
            ->pluck('HRIS_Buku_Cuti.Kode_Karyawan')
            ->flip();

        foreach ($Karyawans as $Karyawan) {
            DB::beginTransaction();
            try {
                $sudahDiberi = $sudahPernahDiberi->has($Karyawan->Kode_Karyawan);
                if ($sudahDiberi) {
                    DB::rollBack();
                    $this->warn("SKIP!!,12 Kuota Pertama #{$Karyawan->Kode_Karyawan} telah diberikan, dilewati");
                    Log::channel('kuotaAwalCuti')->info(
                        "SKIP!!, 12 Kuota Pertama #{$Karyawan->Kode_Karyawan} telah diberikan, dilewati...{$hari}",
                    );

                    continue;
                }

                $cutiGranted = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $Karyawan->Kode_Karyawan)
                    ->where('jumlah_awal_cuti', '>', 0)
                    ->where('tanggal_expired', '>=', $now->toDateString())
                    ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
                    ->where('Flag_Aktif', 'Y');
                $totalGrated = $cutiGranted->sum('jumlah_awal_cuti');
                $sisaToAdd = 12;
                $bulanTahun = $now->clone()->format('m-y');
                $refNo = $this->transactionService->getNoTransaction(
                    'N_HRIS_Log_Buku_Cuti',
                    'NBC',
                    'No_Transaksi_Ref',
                    $bulanTahun,
                );
                $expiredDate = $now->clone()->addYear()->endOfYear()->toDateString();
                $periode = $now->year;

                if ($totalGrated > 0) {
                    if ($totalGrated >= $sisaToAdd) {
                        DB::rollBack();

                        $this->warn(
                            "SKIP!!,12 Kuota Pertama #{$Karyawan->Kode_Karyawan} sudah memiliki kuota cuti tahunan, dilewati",
                        );
                        Log::channel('kuotaAwalCuti')->info(
                            "SKIP!!, 12 Kuota Pertama #{$Karyawan->Kode_Karyawan} sudah memiliki kuota cuti tahunan, dilewati...{$hari}",
                        );

                        continue;
                    }
                    $sisaToAdd = $sisaToAdd - $totalGrated;

                    $dompetCuti = $cutiGranted->get();

                    if (! empty($dompetCuti)) {
                        foreach ($dompetCuti as $cuti) {
                            if ($cuti->Activated_At && Carbon::parse($cuti->Activated_At) > $now) {
                                $oldActivatedAt = $cuti->Activated_At;
                                DB::table('HRIS_Buku_Cuti')
                                    ->where('Id_Buku_Cuti', $cuti->Id_Buku_Cuti)
                                    ->update([
                                        'Activated_At' => $now->toDateString(),
                                    ]);

                                // Audit trail: unlock Activated_At sebelumnya tak berjejak (gap A4).
                                $this->transactionService->recordWalletMaturityChange(
                                    (int) $cuti->Id_Buku_Cuti,
                                    $oldActivatedAt,
                                    $now->toDateString(),
                                    'SYSTEM_SCHEDULER',
                                );
                            }
                        }
                    }

                    $this->transactionService->creditBalance(
                        $Karyawan->Kode_Karyawan,
                        $ID_CUTI_TAHUNAN,
                        $sisaToAdd,
                        $refNo,
                        'CUTI_AWAL_TAHUN_PERTAMA',
                        '12 Kuota cuti tahun pertama',
                        'SYSTEM_SCHEDULER',
                        $now->clone()->toDateString(),
                        $expiredDate,
                        $periode,
                    );
                } else {
                    $this->transactionService->creditBalance(
                        $Karyawan->Kode_Karyawan,
                        $ID_CUTI_TAHUNAN,
                        $sisaToAdd,
                        $refNo,
                        'CUTI_AWAL_TAHUN_PERTAMA',
                        '12 Kuota cuti tahun pertama',
                        'SYSTEM_SCHEDULER',
                        $now->clone()->toDateString(),
                        $expiredDate,
                        $periode,
                    );
                }

                $this->info("Berhasil memberikan 12 kuota cuti pertama pada {$Karyawan->Nama}.");
                Log::channel('kuotaAwalCuti')->info(
                    "Berhasil memberikan 12 kuota cuti pertama pada {$Karyawan->Nama}...{$hari}",
                );
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                Log::channel('kuotaAwalCuti')->error("Gagal memberikan kuota cuti untuk {$Karyawan->Kode_Karyawan}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("Gagal proses cuti untuk {$Karyawan->Kode_Karyawan}, lanjut karyawan berikutnya");

                continue;
            }
        }
        $this->info("Proses Pemberian 12 kuota cuti pada {$hari} telah selesai!");
        Log::channel('kuotaAwalCuti')->info("Proses Pemberian 12 kuota cuti pada {$hari} telah selesai!...{$hari}");

        return 0;
    }

    public function Kartab()
    {
        $this->info('Dilakukan Pencarian Karyawan yang masa probation ny sudah selesai/sudah diangkat kartab');
    }
}
