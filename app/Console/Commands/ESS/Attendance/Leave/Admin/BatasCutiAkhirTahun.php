<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BatasCutiAkhirTahun extends Command
{
    // Nomor transaksi:
    // BMLY: Bypass (Audit Jelas)
    // CLB: Close Balance (Penutupan Periode Lama)
    // CO: Carry Over (Pembukaan Saldo Baru)
    // MLY: Move Locked Yearly (Pindahan Saldo Terkunci)

    // Yang mesti dilindungi dari cutt off ini adalah:
    // - Cuti yang sudah diaktivasi tapi tanggal aktivasi-nya di masa depan (terkunci) ✅
    // - Hutang cuti (negatif)

    protected $signature = 'cuti:tutupBukuCuti
        {--dry-run : Preview keputusan grace/carry-over TANPA menulis (rollback)}
        {--kode= : Batasi ke 1 Kode_Karyawan (uji terisolasi)}';

    protected $description = 'Menghanguskan dan membatasi sisa cuti akhir tahun (dengan grace tahun pertama).';

    protected $transactionService;

    // 2. Inject Service
    public function __construct(TransactionService $transactionService)
    {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    /**
     * Set (lookup) Kode_Karyawan yang berhak GRACE tahun pertama pada tahun-tutup ini.
     * Grace = cap carry-over (max 6) TIDAK berlaku pada tutup-buku PERTAMA karyawan.
     *
     * Sinyal:
     *  - YEAR(MIN(Activated_At)) atas wallet Tahunan (Id_Jenis_Cuti=1, jumlah_awal_cuti>0) == $lastYear
     *    → jatah tahunan pertama muncul di tahun yang ditutup.
     *  - Backstop masa kerja: Tgl_Masuk >= (tanggal-tutup − 18 bulan) → tenure ≤ 1,5 th.
     *  - Hard-gate migrasi: kecualikan karyawan yang punya wallet Is_Migrated='Y' / Migration_Basis
     *    (data migrasi/senior tidak boleh salah dapat grace). Guarded Schema::hasColumn.
     *
     * @return array<string,bool> lookup untuk cek O(1)
     */
    private function graceEligibleKodes(int $lastYear, string $cutoff18mo, ?string $onlyKode = null): array
    {
        $q = DB::table('HRIS_Buku_Cuti as b')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'b.Kode_Karyawan')
            ->where('b.Id_Jenis_Cuti', 1)
            ->where('b.jumlah_awal_cuti', '>', 0)
            ->whereDate('k.Tgl_Masuk', '>=', $cutoff18mo)
            ->groupBy('b.Kode_Karyawan')
            ->havingRaw('YEAR(MIN(b.Activated_At)) = ?', [$lastYear]);
        if ($onlyKode) {
            $q->where('b.Kode_Karyawan', $onlyKode);
        }
        $kandidat = $q->pluck('b.Kode_Karyawan');

        // Hard-gate migrasi (guarded — kolom hasil hardening, DDL manual database/sql/cuti/01).
        if (Schema::hasColumn('HRIS_Buku_Cuti', 'Is_Migrated')) {
            $migrated = DB::table('HRIS_Buku_Cuti')
                ->where(function ($w) {
                    $w->where('Is_Migrated', 'Y')->orWhereNotNull('Migration_Basis');
                })
                ->distinct()->pluck('Kode_Karyawan');
            $kandidat = $kandidat->diff($migrated);
        } else {
            $this->warn('Kolom Is_Migrated belum ada — eksklusi migrasi grace TIDAK aktif (jalankan DDL database/sql/cuti/01).');
        }

        return array_fill_keys($kandidat->all(), true);
    }

    // Arsip Versi 1

    // public function handle()
    // {
    //     $now = FormatTanggalHelper::getCurrentTime('cutiTutupBuku');
    //     $lastYear = $now->copy()->subYear()->year;
    //     $currentYear = $now->year;
    //     $bulanTahun = $now->format('ym');

    //     $ID_CUTI_TAHUNAN = 1;
    //     $MAX_CARRY_OVER = 6;
    //     $CARRY_OVER_EXPIRED = $now->copy()->endOfYear()->toDateString();
    //     $CUTOFF_DATE = Carbon::create($lastYear, 12, 31)->startOfDay();

    //     $bypassList = DB::table('N_HRIS_Bypass_Cuti_Tutup_Buku')
    //         ->where('Flag_Aktif', 'Y')
    //         ->where('Tahun_Target', $lastYear)
    //         ->pluck('Kode_Karyawan')
    //         ->toArray();
    //     // dd($bypassList);

    //     $this->info("=== Memulai Tutup Buku Tahun $lastYear ===");

    //     $karyawanList = DB::table('HRIS_Buku_Cuti')
    //         ->select('Kode_Karyawan')
    //         ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
    //         ->where('Periode', $lastYear)
    //         ->where('sisa_cuti', '>', 0)
    //         ->where('Flag_Aktif', 'Y')
    //         ->groupBy('Kode_Karyawan')
    //         ->get();

    //     $bar = $this->output->createProgressBar($karyawanList->count());

    //     foreach ($karyawanList as $karyawan) {
    //         $allRows = DB::table('HRIS_Buku_Cuti')
    //                 ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
    //                 ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
    //                 ->where('Periode', $lastYear)
    //                 ->where('sisa_cuti', '>', 0)
    //                 ->where('Flag_Aktif', 'Y')
    //                 ->get();
    //         if ($allRows->isEmpty()) {
    //             $bar->advance();
    //             continue;
    //         }

    //         DB::beginTransaction(); // Transaksi dimulai di sini untuk semua skenario
    //         try {
    //             // --- LOGIKA AUDIT BYPASS ---
    //             if (in_array($karyawan->Kode_Karyawan, $bypassList)) {
    //                 $noTransaksiBypass = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'BMLY', 'No_Transaksi_Ref', $bulanTahun);
    //                 // dd($noTransaksiBypass);

    //                 // Ambil sisa cuti total untuk dicatat di log
    //                 $totalSisa = $allRows->sum('sisa_cuti');
    //                 $sampleRow = $allRows->sortByDesc('Activated_At')->first();

    //                 DB::table('N_HRIS_Log_Buku_Cuti')->insert([
    //                     'Id_Buku_Cuti' => $sampleRow->Id_Buku_Cuti,
    //                     'No_Transaksi_Ref' => $noTransaksiBypass,
    //                     'Jenis_Mutasi' => 'CREDIT',
    //                     'Kategori_Transaksi' => 'BMLY',
    //                     'Jumlah' => $totalSisa,
    //                     'Saldo_Sebelum' => $totalSisa,
    //                     'Saldo_Sesudah' => $totalSisa,
    //                     'Keterangan' => 'Bypass Tutup Buku Cuti',
    //                     'Created_By' => 'SYSTEM Admin',
    //                     'Created_At' => now(),
    //                 ]);
    //                 $this->info("Audit Tutup Buku: Karyawan {$karyawan->Kode_Karyawan} di-BYPASS.");
    //                 Log::info("Audit Tutup Buku: Karyawan {$karyawan->Kode_Karyawan} di-BYPASS.");
    //                 DB::commit();
    //                 $bar->advance();
    //                 continue;
    //             }

    //             // --- PROSES NORMAL ---
    //             $rowsTerkunci = $allRows->filter(fn($i) => $i->Activated_At >= $now->toDateString());
    //             $rowsAktif = $allRows->filter(fn($i) => $i->Activated_At < $now->toDateString());

    //             foreach ($rowsTerkunci as $locked) {
    //                 $refMove = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'MLY', 'No_Transaksi_Ref', $bulanTahun);
    //                 $newExp = $now->copy()->addYear()->endOfYear()->toDateString();

    //                 $this->transactionService->creditBalance(
    //                     $karyawan->Kode_Karyawan, $ID_CUTI_TAHUNAN, $locked->sisa_cuti, $refMove,
    //                     'Saldo_Cuti', "Pindahan Saldo Terkunci dari $lastYear", 'SYSTEM_YEAR_END',
    //                     $locked->Activated_At, $newExp, $currentYear
    //                 );

    //                 $refClose = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'CLB', 'No_Transaksi_Ref', $bulanTahun);
    //                 $this->transactionService->closeBalance($locked->Id_Buku_Cuti, "Moved Locked to $currentYear", 'SYSTEM_YEAR_END', $refClose);
    //             }

    //             $totalSisaValid = 0;
    //             foreach ($rowsAktif as $row) {
    //                 $tglExpired = Carbon::parse($row->tanggal_expired);
    //                 $refCloseAct = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'CLB', 'No_Transaksi_Ref', $bulanTahun);

    //                 if ($tglExpired->lt($CUTOFF_DATE)) {
    //                     $this->transactionService->closeBalance($row->Id_Buku_Cuti, "Expired Mid-Year ($row->tanggal_expired)", 'SYSTEM_YEAR_END', $refCloseAct);
    //                 } else {
    //                     $totalSisaValid += $row->sisa_cuti;
    //                     $this->transactionService->closeBalance($row->Id_Buku_Cuti, "Closed Year $lastYear", 'SYSTEM_YEAR_END', $refCloseAct);
    //                 }
    //             }

    //             if ($totalSisaValid > 0) {
    //                 $carryOver = min($totalSisaValid, $MAX_CARRY_OVER);
    //                 $refCO = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'CO', 'No_Transaksi_Ref', $bulanTahun);

    //                 $this->transactionService->creditBalance(
    //                     $karyawan->Kode_Karyawan, $ID_CUTI_TAHUNAN, $carryOver, $refCO,
    //                     'OPENING_BALANCE_CARRY', "Carry Over (Sisa Tahun $lastYear)", 'SYSTEM_YEAR_END',
    //                     $now->toDateString(), $CARRY_OVER_EXPIRED, $currentYear
    //                 );
    //             }

    //             DB::commit();
    //             $bar->advance();

    //         } catch (\Exception $e) {
    //             DB::rollBack();
    //             Log::error("Error Closing {$karyawan->Kode_Karyawan}: " . $e->getMessage());
    //         }
    //     }
    //     $bar->finish();
    //     return 0;
    // }

    // stable v2

    public function handle()
    {
        // 1. Inisialisasi Waktu & Konstanta
        $now = FormatTanggalHelper::getCurrentTime('cutiTutupBuku');
        $lastYear = $now->copy()->subYear()->year;
        $currentYear = $now->year;
        $bulanTahun = $now->format('ym');

        $ID_CUTI_TAHUNAN = 1;
        $MAX_CARRY_OVER = 6;
        $CARRY_OVER_EXPIRED = $now->copy()->endOfYear()->toDateString();
        $CUTOFF_DATE = Carbon::create($lastYear, 12, 31)->startOfDay();

        // Opsi & precompute grace tahun pertama
        $dryRun = (bool) $this->option('dry-run');
        $onlyKode = $this->option('kode') ?: null;
        $cutoff18mo = $now->copy()->subMonths(18)->toDateString();          // backstop masa kerja ≤ 1,5 th
        $graceSet = $this->graceEligibleKodes($lastYear, $cutoff18mo, $onlyKode);
        if ($dryRun) {
            $this->warn('=== DRY-RUN: tidak ada yang ditulis (semua di-rollback) ===');
        }

        // 2. Ambil Daftar Bypass
        $bypassList = DB::table('N_HRIS_Bypass_Cuti_Tutup_Buku')
            ->where('Flag_Aktif', 'Y')
            ->where('Tahun_Target', $lastYear)
            ->pluck('Kode_Karyawan')
            ->toArray();

        // 3. Struktur Laporan JSON
        $summary = [
            'meta' => [
                'tahun_target' => $lastYear,
                'tahun_tujuan' => $currentYear,
                'eksekusi_pada' => now()->toDateTimeString(),
                'max_carry_over' => $MAX_CARRY_OVER,
            ],
            'stats' => [
                'total_karyawan_scan' => 0,
                'total_karyawan_bypass' => 0,
                'total_karyawan_processed' => 0,
                'total_karyawan_grace' => 0,
                'total_saldo_awal_all' => 0, // Ini adalah total sisa_cuti 2025
                'total_pindah_all' => 0,     // Total yang berhasil ke 2026 (CO + LM)
                'total_hangus_all' => 0,     // Total yang tidak terbawa
            ],
            'data_per_orang' => [],
        ];

        $this->info("=== Memulai Tutup Buku Tahun $lastYear ===");

        $karyawanList = DB::table('HRIS_Buku_Cuti')
            ->select('Kode_Karyawan')
            ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
            ->where('Periode', $lastYear)
            ->where('sisa_cuti', '>', 0)
            ->where('Flag_Aktif', 'Y')
            ->when($onlyKode, fn ($q) => $q->where('Kode_Karyawan', $onlyKode))
            ->groupBy('Kode_Karyawan')
            ->get();

        $bar = $this->output->createProgressBar($karyawanList->count());

        /*
         * Baris buku cuti diambil SEKALI untuk seluruh karyawan dalam daftar,
         * lalu dikelompokkan. Sebelumnya satu kueri per karyawan di dalam
         * perintah tutup-buku yang memproses seluruh karyawan sekaligus --
         * saringannya pun sama persis dengan kueri yang menyusun daftar itu.
         */
        $barisPerKaryawan = DB::table('HRIS_Buku_Cuti')
            ->whereIn('Kode_Karyawan', $karyawanList->pluck('Kode_Karyawan')->all())
            ->where('Id_Jenis_Cuti', $ID_CUTI_TAHUNAN)
            ->where('Periode', $lastYear)
            ->where('sisa_cuti', '>', 0)
            ->where('Flag_Aktif', 'Y')
            ->get()
            ->groupBy('Kode_Karyawan');

        foreach ($karyawanList as $karyawan) {
            $allRows = $barisPerKaryawan[$karyawan->Kode_Karyawan] ?? collect();

            if ($allRows->isEmpty()) {
                $bar->advance();

                continue;
            }

            // SALDO AWAL diambil dari TOTAL SISA_CUTI 2025
            $saldoAwalKaryawan = (float) $allRows->sum('sisa_cuti');
            $summary['stats']['total_karyawan_scan']++;
            $summary['stats']['total_saldo_awal_all'] += $saldoAwalKaryawan;

            $itemReport = [
                'id' => $allRows->sortByDesc('Activated_At')->first()->Id_Buku_Cuti,
                'n' => $karyawan->Kode_Karyawan,
                'sa' => $saldoAwalKaryawan,
                'co' => 0, // Sisa Cuti yang di Carry Over (Max 6)
                'lm' => 0, // Sisa Cuti yang Locked Moved (100%)
                'h' => 0, // Sisa Cuti yang Hangus
                'st' => 'PROCESSED',
                'ket' => '',
                'grace' => 'N', // Grace tahun pertama (tanpa cap) — diisi di branch normal
            ];

            DB::beginTransaction();
            try {
                if (in_array($karyawan->Kode_Karyawan, $bypassList)) {
                    $itemReport['st'] = 'BYPASSED';
                    $itemReport['co'] = $saldoAwalKaryawan;
                    $itemReport['ket'] = 'Dikecualikan (Tabel Bypass)';
                    $summary['stats']['total_karyawan_bypass']++;

                    // Log Bypass (Tetap di Periode 2025)
                    $refBypass = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'BMLY', 'No_Transaksi_Ref', $bulanTahun);
                    $this->transactionService->recordBalanceMutation(
                        $itemReport['id'],
                        $refBypass,
                        'CREDIT',
                        'BMLY',
                        $saldoAwalKaryawan,
                        $saldoAwalKaryawan,
                        $saldoAwalKaryawan,
                        'Bypass Tutup Buku Cuti',
                        'SYSTEM Admin',
                        null,
                        $now,
                    );
                } else {
                    $summary['stats']['total_karyawan_processed']++;
                    $isGrace = isset($graceSet[$karyawan->Kode_Karyawan]);
                    if ($isGrace) {
                        $itemReport['grace'] = 'Y';
                        $summary['stats']['total_karyawan_grace']++;
                    }
                    $rowsTerkunci = $allRows->filter(fn ($i) => $i->Activated_At >= $now->toDateString());
                    $rowsAktif = $allRows->filter(fn ($i) => $i->Activated_At < $now->toDateString());

                    // 1. Locked Balance (Dipindahkan 100% dari sisa_cuti)
                    foreach ($rowsTerkunci as $locked) {
                        $itemReport['lm'] += (float) $locked->sisa_cuti;
                        $refMove = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'MLY', 'No_Transaksi_Ref', $bulanTahun);
                        $expired = Carbon::parse($locked->Activated_At)->copy()->addYear()->endOfYear()->toDateString();
                        // dd($expired);

                        //
                        $this->transactionService->creditBalance($karyawan->Kode_Karyawan, $ID_CUTI_TAHUNAN, $locked->sisa_cuti, $refMove, 'Saldo_Cuti', 'Pindahan Terkunci', 'SYSTEM', $locked->Activated_At, $expired, $currentYear);
                        $this->transactionService->closeBalance($locked->Id_Buku_Cuti, 'Moved Locked', 'SYSTEM', $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'CLB', 'No_Transaksi_Ref', $bulanTahun));
                    }

                    // 2. Active Balance (Check Expired & Carry Over dari sisa_cuti)
                    $totalSisaAktifValid = 0;
                    $hangusExpired = 0;
                    foreach ($rowsAktif as $row) {
                        $refCLB = $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', 'CLB', 'No_Transaksi_Ref', $bulanTahun);
                        if (Carbon::parse($row->tanggal_expired)->lt($CUTOFF_DATE)) {
                            $hangusExpired += (float) $row->sisa_cuti;
                            $this->transactionService->closeBalance($row->Id_Buku_Cuti, 'Expired Mid-Year', 'SYSTEM', $refCLB);
                        } else {
                            $totalSisaAktifValid += (float) $row->sisa_cuti;
                            $this->transactionService->closeBalance($row->Id_Buku_Cuti, "Closed Year $lastYear", 'SYSTEM', $refCLB);
                        }
                    }

                    if ($totalSisaAktifValid > 0) {
                        // GRACE: tutup-buku pertama karyawan → tanpa cap (bawa 100% sisa valid).
                        $cap = $isGrace ? $totalSisaAktifValid : $MAX_CARRY_OVER;
                        $itemReport['co'] = (float) min($totalSisaAktifValid, $cap);
                        $notes = $itemReport['co'] >= 0 ? 'Saldo_Cuti' : 'Hutang_Cuti';
                        $reason = $isGrace ? 'Carry Over (Grace Tahun Pertama)' : 'Carry Over';
                        $refPrefix = $isGrace ? 'COG' : 'CO';
                        $this->transactionService->creditBalance($karyawan->Kode_Karyawan, $ID_CUTI_TAHUNAN, $itemReport['co'], $this->transactionService->getNoTransaction('N_HRIS_Log_Buku_Cuti', $refPrefix, 'No_Transaksi_Ref', $bulanTahun), $notes, $reason, 'SYSTEM', $now->toDateString(), $CARRY_OVER_EXPIRED, $currentYear);
                    }

                    // Hitung Hangus (Sisa yang tidak terbawa ke CO atau LM)
                    $itemReport['h'] = (float) ($saldoAwalKaryawan - ($itemReport['co'] + $itemReport['lm']));

                    // --- LOGIKA KETERANGAN AUDIT DINAMIS ---
                    $reasons = [];
                    if ($isGrace) {
                        $reasons[] = 'Grace Tahun Pertama (100% tanpa cap)';
                    }
                    if ($itemReport['lm'] > 0) {
                        $reasons[] = 'Saldo Belum aktif';
                    }
                    if ($hangusExpired > 0) {
                        $reasons[] = "Hangus Expired ($hangusExpired)";
                    }
                    if (! $isGrace && $totalSisaAktifValid > $MAX_CARRY_OVER) {
                        $reasons[] = "Dipotong Limit > $MAX_CARRY_OVER";
                    }
                    $itemReport['ket'] = empty($reasons) ? 'Full Carry Over' : implode(' & ', $reasons);
                }

                $summary['stats']['total_pindah_all'] += ($itemReport['co'] + $itemReport['lm']);
                $summary['stats']['total_hangus_all'] += $itemReport['h'];
                $summary['data_per_orang'][] = $itemReport;

                // Dry-run: jalankan logika nyata lalu ROLLBACK → nol tulisan, keputusan akurat.
                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Gagal {$karyawan->Kode_Karyawan}: ".$e->getMessage());
            } catch (\Throwable $e) {
                /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                   jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                   supaya handler global tetap mencatat stack trace-nya dan responsnya
                   tak berubah sedikit pun. */
                DB::rollBack();

                throw $e;
            }
            $bar->advance();
        }
        $bar->finish();

        // 5. Tabel ringkas per-karyawan (selalu ditampilkan; utama untuk --dry-run)
        if (! empty($summary['data_per_orang'])) {
            $this->line('');
            $this->table(
                ['Kode', 'Grace', 'Saldo Awal', 'Carry (co)', 'Locked (lm)', 'Hangus (h)', 'Status', 'Keterangan'],
                array_map(fn ($r) => [
                    $r['n'], ($r['grace'] ?? 'N') === 'Y' ? 'YA' : '-',
                    $r['sa'], $r['co'], $r['lm'], $r['h'], $r['st'], $r['ket'],
                ], $summary['data_per_orang'])
            );
            $s = $summary['stats'];
            $this->line("Scan: {$s['total_karyawan_scan']} | Grace: {$s['total_karyawan_grace']} | Bypass: {$s['total_karyawan_bypass']} | Pindah: {$s['total_pindah_all']} | Hangus: {$s['total_hangus_all']}");
        }

        // 6. Simpan Master Report JSON (dilewati saat dry-run)
        if (! $dryRun && ! empty($summary['data_per_orang'])) {
            $reportRef = 'REP-'.$lastYear.'-'.date('His');
            DB::table('N_HRIS_Log_Buku_Cuti')->insert([
                'Id_Buku_Cuti' => $summary['data_per_orang'][0]['id'],
                'No_Transaksi_Ref' => $reportRef,
                'Jenis_Mutasi' => 'DEBIT',
                'Kategori_Transaksi' => 'YEAR_END_REPORT',
                'Jumlah' => $summary['stats']['total_pindah_all'],
                'Saldo_Sebelum' => $summary['stats']['total_saldo_awal_all'],
                'Saldo_Sesudah' => $summary['stats']['total_pindah_all'],
                'Keterangan' => json_encode($summary),
                'Created_By' => 'SYSTEM',
                'Created_At' => now(),
            ]);
            $this->info("\n✅ Selesai! Laporan: $reportRef");
        } elseif ($dryRun) {
            $this->warn("\n=== DRY-RUN selesai — tidak ada perubahan disimpan ===");
        }

        return 0;
    }
}
