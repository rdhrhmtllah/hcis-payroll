<?php

namespace App\Console\Commands\ESS\Attendance\Processing;

use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class flagCheckinout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'absen:flagCheckinout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command ini untuk flag checkin out untuk data terlambat dan pulang cepat';

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
        Log::channel('FlagCheckinout')->info("Proses Flaging Checkinout untuk terlambat dan pulang cepat pada {$hari} dimulai... ");
        try {
            $transaksiPulang = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as a')
                ->select('a.*')
                ->join('Transaksi_Terlambat_Pulang_Cepat as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Jenis', 'pulangCepat')
                ->where('a.Flag_Approval', 'Y')
                ->whereNull('Flag_Checkinout')->get();
            $transaksiTerlambat = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as a')
                ->select('a.*')
                ->join('Transaksi_Terlambat_Pulang_Cepat as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereNull('b.Status')
                ->where('a.Jenis', 'terlambat')
                ->where('a.Flag_Approval', 'Y')
                ->whereNull('Flag_Checkinout')->get();

            /*
             * Peta Kode_Karyawan -> UserID_Absen dibangun SEKALI untuk kedua
             * perulangan di bawah. Sebelumnya tiap baris menanyakan tabel
             * Karyawan sendiri-sendiri: satu kueri per transaksi terlambat dan
             * satu lagi per transaksi pulang cepat, di dalam perintah terjadwal
             * yang memproses seluruh transaksi tertunda sekaligus.
             *
             * Kueri lamanya menyaring hanya pada Kode_Karyawan tanpa
             * Kode_Perusahaan, jadi ia SUDAH memikul asumsi bahwa kode karyawan
             * unik lintas perusahaan. Peta ini mewarisi asumsi yang sama, tidak
             * menambah yang baru. Perlu dicatat skema tidak menjaminnya:
             * PK_Karyawan komposit (Kode_Perusahaan, Kode_Karyawan) dan
             * IX_Karyawan_Kode_Karyawan tidak unik. Pada basis data
             * pengembangan per 9 September 2026 hanya ada satu Kode_Perusahaan
             * ('001'), 965 baris dengan 965 Kode_Karyawan berbeda, nol kembar.
             *
             * whereIn dipotong pada BATAS_AMAN yang sama dengan penyisipan
             * berbatas, supaya batas 2.100 parameter SQL Server tidak ditabrak
             * bila transaksi tertunda menumpuk.
             */
            $kodeKaryawan = $transaksiPulang->pluck('Kode_Karyawan')
                ->merge($transaksiTerlambat->pluck('Kode_Karyawan'))
                ->filter()
                ->unique()
                ->values();

            $userAbsenPerKode = collect();
            foreach (array_chunk($kodeKaryawan->all(), PenyisipanBerbatasParameter::BATAS_AMAN) as $potongan) {
                $userAbsenPerKode = $userAbsenPerKode->merge(
                    DB::table('Karyawan')
                        ->whereIn('Kode_Karyawan', $potongan)
                        ->pluck('UserID_Absen', 'Kode_Karyawan')
                );
            }

            // dd($transaksi);

            if (! empty($transaksiTerlambat)) {
                foreach ($transaksiTerlambat as $t) {
                    $tanggal = date('Y-m-d', strtotime($t->Tanggal_Masuk_Pulang));

                    $UserID_Absen = $userAbsenPerKode[$t->Kode_Karyawan] ?? null;
                    $jamIzin = $t->Jam;

                    $Untuk_Tanggal = date('Y-m-d H:i:s', strtotime("$tanggal"));
                    // Range ±1 jam dari jam izin
                    $startTime = date('Y-m-d H:i:s', strtotime("$tanggal $jamIzin -1 hour"));
                    $endTime = date('Y-m-d H:i:s', strtotime("$tanggal $jamIzin +1 hour"));

                    $checkout = DB::table('CheckInOut')
                        ->where('UserID', $UserID_Absen)
                        ->whereBetween('CHECKTIME', [$startTime, $endTime])
                        ->orderBy('CHECKTIME', 'asc') // urutkan kalau mau ambil terdekat
                        ->first();
                    $checktime = $checkout->CHECKTIME ?? null;
                    // dd($checktime);

                    $insertChecktime = DB::table('CheckInOut')
                        ->where('UserID', $UserID_Absen)
                        ->whereBetween('CHECKTIME', [$startTime, $endTime])
                        ->orderBy('CHECKTIME', 'asc')
                        ->update([
                            'Alasan' => 'TERLAMBAT',
                            'Hasil' => 'In',
                            'Untuk_Tanggal' => $Untuk_Tanggal,
                        ]);

                    if ($insertChecktime) {
                        DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                            ->where('No_Transaksi', $t->No_Transaksi)
                            ->update(['Flag_Checkinout' => 'Y']);

                        $this->attendanceDirtyResolver->markByKodeKaryawan(
                            $t->Kode_Karyawan,
                            $tanggal,
                            $tanggal,
                            'CHECKINOUT_FLAGGED_FROM_TP',
                            'COMMAND_FLAG_CHECKINOUT',
                            1,
                        );
                    }

                    // Debug hasil
                    Log::channel('FlagCheckinout')->info("No Transaksi: {$t->No_Transaksi} :Checktime = {$checktime}");
                    $this->info("No Transaksi: {$t->No_Transaksi} :Checktime = {$checktime}");
                }
            }

            if (! empty($transaksiPulang)) {
                foreach ($transaksiPulang as $t) {
                    $tanggal = date('Y-m-d', strtotime($t->Tanggal_Masuk_Pulang));

                    $UserID_Absen = $userAbsenPerKode[$t->Kode_Karyawan] ?? null;
                    $jamIzin = $t->Jam;

                    $Untuk_Tanggal = date('Y-m-d H:i:s', strtotime("$tanggal"));
                    // Range ±1 jam dari jam izin
                    $startTime = date('Y-m-d H:i:s', strtotime("$tanggal $jamIzin -1 hour"));
                    $endTime = date('Y-m-d H:i:s', strtotime("$tanggal $jamIzin +1 hour"));

                    $checkout = DB::table('CheckInOut')
                        ->where('UserID', $UserID_Absen)
                        ->whereBetween('CHECKTIME', [$startTime, $endTime])
                        ->orderBy('CHECKTIME', 'asc') // urutkan kalau mau ambil terdekat
                        ->first();
                    $checktime = $checkout->CHECKTIME ?? null;
                    // dd($checktime);

                    $insertChecktime = DB::table('CheckInOut')
                        ->where('UserID', $UserID_Absen)
                        ->whereBetween('CHECKTIME', [$startTime, $endTime])
                        ->orderBy('CHECKTIME', 'asc')
                        ->update([
                            'Alasan' => 'PULANG CEPAT',
                            'Hasil' => 'Out',
                            'Untuk_Tanggal' => $Untuk_Tanggal,
                        ]);
                    if ($insertChecktime) {
                        DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                            ->where('No_Transaksi', $t->No_Transaksi)
                            ->update(['Flag_Checkinout' => 'Y']);

                        $this->attendanceDirtyResolver->markByKodeKaryawan(
                            $t->Kode_Karyawan,
                            $tanggal,
                            $tanggal,
                            'CHECKINOUT_FLAGGED_FROM_TP',
                            'COMMAND_FLAG_CHECKINOUT',
                            1,
                        );
                    }

                    // Debug hasil
                    Log::channel('FlagCheckinout')->info("No Transaksi: {$t->No_Transaksi} :Checktime = {$checktime}");
                    $this->info("No Transaksi: {$t->No_Transaksi} :Checktime = {$checktime}");
                }
            }

        } catch (\Exception $e) {
            Log::channel('FlagCheckinout')->error("Kesalahan umum proses notifikasi.  pada {$hari}", [
                'pesan_pengecualian' => $e->getMessage(),
            ]);

            return 1;
        }

        Log::channel('FlagCheckinout')->info("Proses Flaging Checkinout untuk terlambat dan pulang cepat pada {$hari} dimulai...");

        return 0;
    }
}
