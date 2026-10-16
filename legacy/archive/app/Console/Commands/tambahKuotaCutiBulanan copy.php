<?php

namespace App\Console\Commands;

use App\Console\Commands\ESS\Attendance\Leave\Admin\tambahKuotaCutiBulanan;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class tambahKuotaCutiBulanan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:tambahCutisssssssssxxxxxx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perintah ini berfunsi untuk menambah kuota cuti karyawan secara otomatis sesuai dengan golongan nya (BULANAN)';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     // $now = now();
    //     $now = Carbon::parse('2025-12-01');
    //     $hari = $now->toDateString();

    //     $this->info('Proses Penambahan Kuota cuti bulanan berlangsung...');
    //     Log::channel('tambahKuotaCutiBulanan')->info("Proses Penambahan Kuota cuti bulanan berlangsung...{$hari}");

    //     $bulanIni = $now->format('F Y');

    //     // $Karyawans = Karyawan::where('Tanggal_Resign', null)->get(); //Konfirmasi dulu apakah saat sudah aktif T maka tidak perlu lagi tambahkan cuti dan harus tambahkan filter status karyawan
    //     $Karyawans = Karyawan::where('Tanggal_Resign', null)->where('Kode_Karyawan', 'H45')->get(); //Konfirmasi dulu apakah saat sudah aktif T maka tidak perlu lagi tambahkan cuti dan harus tambahkan filter status karyawan
    //     foreach($Karyawans as $Karyawan){
    //         $TanggalMasuk = $Karyawan->Tgl_Masuk;

    //         if (Carbon::parse($TanggalMasuk)->diffInDays($now) < 30) {
    //             $this->info("{$Karyawan->Nama} status PKTT masih kurang 30 hari sehingga di skip");
    //             continue;
    //         }

    //         $CutiTerakhir = DB::table('HRIS_buku_cuti')->where('Kode_Karyawan', $Karyawan->Kode_Karyawan)
    //                         ->where('notes', 'like', 'Kuota cuti rutin bulanan%')
    //                         ->where('Flag_Aktif', 'Y')
    //                         ->latest('tanggal_diberikan')->first();

    //         if($CutiTerakhir && Carbon::parse($CutiTerakhir->tanggal_diberikan)->isSameMonth($now, true)){
    //             $this->warn("{$Karyawan->Nama} Sudah mendapatkan kuota cuti bulan {$bulanIni}");
    //             Log::channel('tambahKuotaCutiBulanan')->info("Sudah mendapatkan kuota cuti bulan $bulanIni...{$hari}");

    //             continue;
    //         }

    //         $TambahHari = 1;

    //         if($Karyawan->level->ID_Golongan == 6 && $Karyawan->status_kontrak == 'PKWTT'){
    //             if($now->month >= 7){
    //                 $TambahHari = 2;
    //             }
    //         }

    //         $activated_at = $now->clone();

    //         if ($Karyawan->status_kontrak == 'PKWT') {
    //             if (Carbon::parse($TanggalMasuk)->diffInYears($now) < 1) {
    //                 $MasaBerlakuCutiPKWT = Carbon::parse($TanggalMasuk)->addYear();
    //                 $activated_at = $MasaBerlakuCutiPKWT;
    //             }
    //         } else {
    //             if (Carbon::parse($TanggalMasuk)->diffInMonths($now) < 3) {
    //                 $MasaBerlakuCutiPKWT = Carbon::parse($TanggalMasuk)->addMonths(3);
    //                 $activated_at = $MasaBerlakuCutiPKWT;
    //             }
    //         }

    //         DB::table('HRIS_buku_cuti')->insert([
    //             'Kode_Karyawan' => $Karyawan->Kode_Karyawan,
    //             'tipe_cuti' => 'Tahunan',
    //             'tanggal_diberikan' => $now->toDateString(),
    //             'tanggal_expired' => $now->clone()->addYear()->endOfYear()->toDateString(),
    //             'jumlah_awal_cuti' => $TambahHari,
    //             'sisa_cuti' => $TambahHari,
    //             'Activated_At' => $activated_at,
    //             'notes' => 'Kuota cuti rutin bulanan '. $now->format('F Y'),
    //             'Flag_Aktif' => 'Y'
    //         ]);

    //         $this->line("Berhasil Menambahkan {$TambahHari} Cuti bulanan pada #{$Karyawan->Nama} ");
    //         Log::channel('tambahKuotaCutiBulanan')->info("Berhasil Menambahkan {$TambahHari} Cuti bulanan pada #{$Karyawan->Nama}...{$hari}");

    //     }
    //     $this->info('Proses penambahan cuti bulanan telah selesai');
    //     Log::channel('tambahKuotaCutiBulanan')->info("Proses penambahan cuti bulanan telah selesai...{$hari}");

    //     return 0;

    // }

    public function handle()
    {
        // $now = now(); // Gunakan ini untuk production
        $now = Carbon::parse('2025-12-01');
        $hari = $now->toDateString();
        $bulanIni = $now->format('F Y');
        $tahunIni = $now->year;

        $ID_CUTI_TAHUNAN = 1;

        $this->info("Proses Penambahan Kuota Cuti Bulanan Periode $bulanIni dimulai...");
        Log::channel('tambahKuotaCutiBulanan')->info("Start Process... {$hari}");

        $query = Karyawan::whereNull('Tanggal_Resign');

        // $query->where('Kode_Karyawan', 'H45');

        $query->chunk(100, function ($karyawans) use ($now, $bulanIni, $ID_CUTI_TAHUNAN) {
            foreach ($karyawans as $karyawan) {

                if (Carbon::parse($karyawan->Tgl_Masuk)->diffInDays($now) < 30) {
                    $this->line("SKIP: {$karyawan->Nama} masa kerja < 30 hari.");

                    continue;
                }

                $sudahDapat = DB::table('N_HRIS_Log_Buku_Cuti')
                    ->join('HRIS_Buku_Cuti', 'N_HRIS_Log_Buku_Cuti.Id_Buku_Cuti', '=', 'HRIS_Buku_Cuti.Id_Buku_Cuti')
                    ->where('HRIS_Buku_Cuti.Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('N_HRIS_Log_Buku_Cuti.Kategori_Transaksi', 'AKRUAL_BULANAN')
                    ->whereMonth('N_HRIS_Log_Buku_Cuti.Created_At', $now->month)
                    ->whereYear('N_HRIS_Log_Buku_Cuti.Created_At', $now->year)
                    ->exists();

                if ($sudahDapat) {
                    $this->warn("SKIP: {$karyawan->Nama} sudah dapat jatah bulan ini.");

                    continue;
                }

                $tambahHari = 1;
                // Logic Golongan 6 + PKWTT (Jatah double di semester 2)
                // if ($karyawan->level->ID_Golongan == 6 && $karyawan->Status_Kepegawaian == 'Tetap') {
                //     if ($now->month >= 7) {
                //         $tambahHari = 2;
                //     }
                // }

                if ($karyawan->level->ID_Golongan == 6 && $karyawan->Status_Kepegawaian == 'Tetap') { // Pastikan string 'Tetap' atau 'PKWTT' sesuai DB Anda

                    $tglMasuk = Carbon::parse($karyawan->Tgl_Masuk);

                    $bulanTargetBonus = $tglMasuk->copy()->addMonths(6)->month;

                    if ($now->month == $bulanTargetBonus) {
                        $tambahHari = 2;
                        $this->info("TAHUNAN CUTI Gol 6: {$karyawan->Nama} masuk bulan ke-7 bekerja (Dapat 2 Hari).");
                    }
                }

                DB::beginTransaction();
                try {
                    // D. STRATEGI INSERT/UPDATE (LOGIKA PRIORITAS)

                    // Prioritas 1: Cek Hutang (Saldo Minus)
                    $rowHutang = DB::table('HRIS_Buku_Cuti')
                        ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                        ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
                        ->where('sisa_cuti', '<', 0) // Cari yang minus
                        ->where('Flag_Aktif', 'Y')
                        ->orderBy('tanggal_expired', 'asc') // Bayar hutang terlama dulu
                        ->first();

                    // Prioritas 2: Cek Saldo Aktif Tahun Ini (Untuk diakumulasi)
                    $rowAktif = DB::table('HRIS_Buku_Cuti')
                        ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                        ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
                        ->where('sisa_cuti', '>=', 0)
                        ->where('Flag_Aktif', 'Y')
                        ->whereDate('tanggal_expired', '>=', $now)
                        ->whereYear('tanggal_expired', $now->year + 1) // Asumsi expired tahun depan
                        ->first();

                    $targetIdBuku = null;
                    $saldoAwalLog = 0;
                    $saldoAkhirLog = 0;

                    if ($rowHutang) {
                        $targetIdBuku = $rowHutang->Id_Buku_Cuti;
                        $saldoAwalLog = $rowHutang->sisa_cuti;

                        DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $targetIdBuku)
                            ->update([
                                'sisa_cuti' => DB::raw("sisa_cuti + $tambahHari"),
                                'updated_at' => now(),
                            ]);

                        DB::table('N_HRIS_Log_Buku_Cuti')->insert([
                            'Id_Buku_Cuti' => $targetIdBuku,
                            'No_Transaksi_Ref' => null,
                            'Jenis_Mutasi' => 'CREDIT',
                            'Kategori_Transaksi' => 'PELUNASAN_HUTANG_AUTO',
                            'Jumlah' => $tambahHari,
                            'Saldo_Sebelum' => $saldoAwalLog,
                            'Saldo_Sesudah' => $saldoAwalLog + $tambahHari,
                            'Keterangan' => "Pelunasan Hutang Otomatis (Jatah Bulanan $bulanIni)",
                            'Created_By' => 'SYSTEM_SCHEDULER',
                            'Created_At' => now(),
                        ]);

                        $this->info("PAY DEBT: {$karyawan->Nama} membayar hutang (+{$tambahHari})");

                    } elseif ($rowAktif) {
                        // === AKUMULASI KE SALDO TAHUN INI ===
                        $targetIdBuku = $rowAktif->Id_Buku_Cuti;
                        $saldoAwalLog = $rowAktif->sisa_cuti;

                        DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $targetIdBuku)
                            ->update([
                                'jumlah_awal_cuti' => DB::raw("jumlah_awal_cuti + $tambahHari"), // Plafon naik
                                'sisa_cuti' => DB::raw("sisa_cuti + $tambahHari"), // Saldo naik
                                'updated_at' => now(),
                            ]);

                    } else {
                        $activated_at = $now->clone();
                        if ($karyawan->status_kontrak == 'PKWT') {
                            if (Carbon::parse($karyawan->Tgl_Masuk)->diffInYears($now) < 1) {
                                $activated_at = Carbon::parse($karyawan->Tgl_Masuk)->addYear();
                            }
                        } else {
                            if (Carbon::parse($karyawan->Tgl_Masuk)->diffInMonths($now) < 3) {
                                $activated_at = Carbon::parse($karyawan->Tgl_Masuk)->addMonths(3);
                            }
                        }

                        // Expired: Akhir tahun depan (Sesuai logic Anda addYear->endOfYear)
                        $expiredDate = $now->clone()->addYear()->endOfYear()->toDateString();

                        $targetIdBuku = DB::table('HRIS_Buku_Cuti')->insertGetId([
                            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                            'Id_Jenis_Cuti' => $ID_CUTI_TAHUNAN, // Pakai ID Int
                            'Periode' => $now->year, // Tambahan kolom Periode
                            'tanggal_diberikan' => $now->toDateString(),
                            'tanggal_expired' => $expiredDate,
                            'jumlah_awal_cuti' => $tambahHari,
                            'sisa_cuti' => $tambahHari,
                            'jumlah_terpakai' => 0,
                            'Activated_At' => $activated_at,
                            'notes' => 'Akrual Tahunan '.$now->year,
                            'Flag_Aktif' => 'Y',
                            'created_at' => now(),
                        ]);

                        $saldoAwalLog = 0;
                    }

                    // E. INSERT LOG MUTASI (WAJIB)
                    // Apapun skenarionya (Update Hutang, Update Saldo, Insert Baru), log tetap dibuat
                    DB::table('N_HRIS_Log_Buku_Cuti')->insert([
                        'Id_Buku_Cuti' => $targetIdBuku,
                        'No_Transaksi_Ref' => null, // System generated
                        'Jenis_Mutasi' => 'CREDIT',
                        'Kategori_Transaksi' => 'AKRUAL_BULANAN',
                        'Jumlah' => $tambahHari,
                        'Saldo_Sebelum' => $saldoAwalLog,
                        'Saldo_Sesudah' => $saldoAwalLog + $tambahHari,
                        'Keterangan' => "Jatah Bulanan $bulanIni",
                        'Created_By' => 'SYSTEM_SCHEDULER',
                        'Created_At' => now(),
                    ]);

                    DB::commit();
                    Log::channel('tambahKuotaCutiBulanan')->info("SUCCESS: {$karyawan->Nama} (+{$tambahHari})");

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel('tambahKuotaCutiBulanan')->error("ERROR {$karyawan->Nama}: ".$e->getMessage());
                }
            }
        });

        $this->info('Proses Selesai.');

        return 0;
    }
}
