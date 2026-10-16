<?php

namespace App\Console\Commands;

use App\Console\Commands\ESS\Attendance\Leave\Admin\HakCutiTambahan;
use App\Helpers\FormatTanggalHelper;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HakCutiTambahan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cuti:hakCutiTambahan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menambahkan Cuti Tambahan sesuai dengan lama join dan juga golongan (HARIAN)';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     $hari = now()->toDateString();
    //     $this->info('Mencari karaywan yang mendapatkan hak cuti tambahan...');
    //     Log::channel('HakCutiTambahan')->info("Mencari karaywan yang mendapatkan hak cuti tambahan...{$hari}");

    //     $rules = DB::table('HRIS_Hak_Cuti_Tambahan')->where('status', null)->get();

    //     if($rules->isEmpty()){
    //         $this->info('Tidak ada aturan hak cuti tambahan yang aktif');
    //         Log::channel('HakCutiTambahan')->info("idak ada aturan hak cuti tambahan yang aktif {$hari}");

    //         return 0;
    //     }

    //     foreach($rules as $rule){
    //         $this->line("Proses aturan {$rule->nama_rule} untuk lebih dari {$rule->tahun_minimal} tahun");
    //         Log::channel('HRIS_Hak_Cuti_Tambahan')->info("Proses aturan {$rule->nama_rule} untuk lebih dari {$rule->tahun_minimal} tahun...{$hari}");

    //         // $targetJoinDate = now()->subYears($rule->tahun_minimal)->toDateString();
    //         // $targetJoinDate = '2025-07-01';

    //         $Karyawans = DB::table('Karyawan AS a')
    //                         ->join("View_Golongan_Sub_Golongan_Level_Jabatan AS b", "a.ID_Level_Jabatan", "=", 'b.ID_Level_Jabatan')
    //                         ->where('a.Tanggal_Resign', null)
    //                         ->whereMonth("a.Tgl_Masuk", "<=", now()->month)
    //                         ->whereDay("a.Tgl_Masuk", now()->day)
    //                         ->where("a.Tgl_Masuk", "<=", now()->subYear($rule->tahun_minimal))
    //                         // ->where("status", 1) //Untuk Karyawnan tetap
    //                         ->whereBetween("b.ID_Golongan", [$rule->golongan_min_id, $rule->golongan_max_id])
    //                         ->where("Kode_Karyawan", "RIDHO RAHMAT")
    //                         ->get();
    //         // dd($Karyawans);

    //         if($Karyawans->isEmpty()){
    //             continue;
    //         }

    //         foreach($Karyawans as $Karyawan){
    //             $noteForThisYear =  $rule->nama_rule.'-'.now()->year;

    //             $sudahDikasih = DB::table('HRIS_Buku_Cuti')
    //                             ->where("notes", $noteForThisYear)
    //                             ->exists();
    //             if($sudahDikasih){
    //                 $this->warn("Karyawan #{$Karyawan->Nama} sudah diberikan hak tambahan tahun ini. skip...");
    //                 Log::channel('HRIS_Hak_Cuti_Tambahan')->info("Karyawan #{$Karyawan->Nama} sudah diberikan hak tambahan tahun ini. skip...{$hari}");

    //                 continue;
    //             }

    //              DB::table('HRIS_buku_cuti')->insert([
    //                 'Kode_Karyawan' => $Karyawan->Kode_Karyawan,
    //                 'tipe_cuti' => 'Tambahan',
    //                 'tanggal_diberikan' => now()->toDateString(),
    //                 'tanggal_expired' => now()->addMonths($rule->masa_berlaku_cuti)->toDateString(),
    //                 'jumlah_awal_cuti' => $rule->tambahan_hari,
    //                 'sisa_cuti' => $rule->tambahan_hari,
    //                 'notes' => $noteForThisYear
    //             ]);

    //             $this->info("Telah memberikan tambahan {$rule->nama_rule} pada karyawan #{$Karyawan->Nama}");
    //             Log::channel('HRIS_Hak_Cuti_Tambahan')->info("Telah memberikan tambahan {$rule->nama_rule} pada karyawan #{$Karyawan->Nama}...{$hari}");

    //         }

    //     }
    //     $this->info("Penambahan Hak cuti tambahan telah selesai di proses.");
    //     Log::channel('HRIS_Hak_Cuti_Tambahan')->info("Penambahan Hak cuti tambahan telah selesai di proses...{$hari}");

    //     return 0;
    // }

    public function handle()
    {
        $today = FormatTanggalHelper::getCurrentTime('HakCutiTambahan');

        $ID_CUTI_LOYALTY = 2;

        $this->info("Mencari karyawan Anniversary hari ini ({$today->toDateString()})...");

        // 1. AMBIL RULE (URUTKAN DARI TAHUN TERTINGGI)
        // Ini kunci agar range otomatis terbentuk
        $allRules = DB::table('HRIS_Hak_Cuti_Tambahan')
            // ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->orderBy('tahun_minimal', 'desc')
            ->get();

        if ($allRules->isEmpty()) {
            $this->info('Tidak ada aturan hak cuti tambahan yang aktif.');
            Log::channel('HakCutiTambahan')->info("Tidak ada aturan hak cuti tambahan yang aktif pada {$today->toDateString()}.");

            return 0;
        }

        // 2. CARI KARYAWAN YANG ULANG TAHUN KERJA HARI INI
        // Kita tidak filter tahun masuk di query SQL agar bisa menangkap semua angkatan
        $karyawans = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->whereNull('k.Tanggal_Resign')
            ->where('k.Status_Kepegawaian', 'Tetap') // Sesuai Poin 6.1
            ->whereMonth('k.Tgl_Masuk', $today->month)
            ->whereDay('k.Tgl_Masuk', $today->day)
            ->select('k.*', 'g.ID_Golongan')
            ->get();
        // dd($karyawans);
        foreach ($karyawans as $karyawan) {

            // A. HITUNG MASA KERJA SAAT INI (TAHUN KE-BERAPA?)
            $tglMasuk = Carbon::parse($karyawan->Tgl_Masuk);
            $masaKerjaTahun = $tglMasuk->diffInYears($today);

            // B. CARI RULE YANG SESUAI (LADDER LOGIC)
            // First() akan mengambil rule tertinggi yang lolos syarat
            $rule = $allRules->first(function ($r) use ($masaKerjaTahun, $karyawan) {
                return $masaKerjaTahun >= $r->tahun_minimal
                    && $karyawan->ID_Golongan >= $r->golongan_min_id
                    && $karyawan->ID_Golongan <= $r->golongan_max_id;
            });

            // dd($rule, $masaKerjaTahun);

            // Jika masa kerja belum sampai minimal (misal baru 2 tahun), skip
            if (! $rule) {
                Log::channel('HakCutiTambahan')->info("Tidak ada rule yang sesuai untuk {$karyawan->Nama} dengan masa kerja {$masaKerjaTahun} tahun.");
                $this->info("SKIP: {$karyawan->Nama} tidak ada rule yang sesuai untuk masa kerja {$masaKerjaTahun} tahun.");

                continue;
            }

            // C. CEK APAKAH SUDAH DAPAT UNTUK TAHUN KE-X INI?
            // Contoh: Apakah sudah dapat reward untuk "Tahun ke-7"?
            $logKey = 'LOYALTY_YEAR_'.$masaKerjaTahun.'_RULE_'.$rule->id_Hak_Cuti;

            $sudahDapat = DB::table('N_HRIS_Log_Buku_Cuti')
                ->join('HRIS_Buku_Cuti', 'N_HRIS_Log_Buku_Cuti.Id_Buku_Cuti', '=', 'HRIS_Buku_Cuti.Id_Buku_Cuti')
                ->where('HRIS_Buku_Cuti.Kode_Karyawan', $karyawan->Kode_Karyawan)
                ->where('N_HRIS_Log_Buku_Cuti.Kategori_Transaksi', $logKey)
                ->exists();

            if ($sudahDapat) {
                Log::channel('HakCutiTambahan')->info("Sudah pernah dapat reward untuk {$karyawan->Nama} pada tahun ke-$masaKerjaTahun dengan rule {$rule->nama_rule}.");
                $this->info("SKIP: {$karyawan->Nama} sudah dapat reward untuk tahun ke-$masaKerjaTahun.");

                continue;
            }

            // D. EKSEKUSI INSERT (ROW BARU)
            DB::beginTransaction();
            try {
                $expiredDate = $today->copy()->addMonths($rule->masa_berlaku_cuti)->toDateString();

                $idBuku = DB::table('HRIS_Buku_Cuti')->insertGetId([
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                    'Id_Jenis_Cuti' => $ID_CUTI_LOYALTY,
                    'Periode' => $today->year,
                    'tanggal_diberikan' => $today->toDateString(),
                    'tanggal_expired' => $expiredDate,
                    'jumlah_awal_cuti' => $rule->tambahan_hari,
                    'sisa_cuti' => $rule->tambahan_hari,
                    'jumlah_terpakai' => 0,
                    'tipe_cuti' => "Tambahan {$masaKerjaTahun} Thn",
                    'Activated_At' => $today->toDateString(),
                    'notes' => "Reward Masa Kerja {$masaKerjaTahun} Tahun ({$rule->nama_rule})",
                    'Flag_Aktif' => 'Y',
                    'created_at' => now(),
                ]);

                DB::table('N_HRIS_Log_Buku_Cuti')->insert([
                    'Id_Buku_Cuti' => $idBuku,
                    'Jenis_Mutasi' => 'CREDIT',
                    'Kategori_Transaksi' => $logKey, // Unik per tahun masa kerja
                    'Jumlah' => $rule->tambahan_hari,
                    'Saldo_Sebelum' => 0,
                    'Saldo_Sesudah' => $rule->tambahan_hari,
                    'Keterangan' => "Reward Anniversary ke-$masaKerjaTahun",
                    'Created_By' => 'SYSTEM_LOYALTY',
                    'Created_At' => now(),
                ]);

                DB::commit();
                $this->info("SUCCESS: {$karyawan->Nama} (Ke-$masaKerjaTahun) dpt {$rule->tambahan_hari} hari.");

            } catch (\Exception $e) {
                DB::rollBack();
                Log::channel('HakCutiTambahan')->error("Gagal memberikan cuti tambahan untuk {$karyawan->Nama}: ".$e->getMessage());
                $this->error("FAILED: {$karyawan->Nama} - ".$e->getMessage());
            }
        }

        return 0;
    }
}
