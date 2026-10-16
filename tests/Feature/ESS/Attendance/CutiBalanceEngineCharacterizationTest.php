<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Characterization test mesin saldo cuti (TransactionService).
 *
 * TUJUAN: mengunci PERILAKU SAAT INI dari creditBalance/debitBalance/closeBalance
 * + integritas ledger, agar refactor mendatang (single choke point `mutateWallet`,
 * konvergensi FIFO — Fase 2) bisa dibuktikan behavior-preserving: jalankan test ini
 * SEBELUM dan SESUDAH refactor, hasil harus identik.
 *
 * KARAKTER:
 *   - Env-gated (skip default) — menulis ke DB dev, jadi hanya jalan bila diminta:
 *       RUN_REAL_CUTI_ENGINE_TEST=true php artisan test --filter CutiBalanceEngine
 *   - Memakai Kode_Karyawan SINTETIS (tanpa baris Karyawan) → tidak terlihat di
 *     view saldo & tidak mengganggu data user.
 *   - Self-cleaning: tearDown menghapus ledger + wallet yang dibuatnya.
 */
class CutiBalanceEngineCharacterizationTest extends TestCase
{
    private TransactionService $svc;

    private string $kode;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_REAL_CUTI_ENGINE_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_ENGINE_TEST=true untuk menjalankan characterization test engine (menulis DB dev).');
        }

        $this->svc = app(TransactionService::class);
        // Kode sintetis unik per run — tidak ada di tabel Karyawan, jadi tak muncul di view.
        $this->kode = 'ZZENG_'.substr(md5(uniqid('', true)), 0, 12);
    }

    protected function tearDown(): void
    {
        // Hapus ledger lalu wallet milik kode sintetis ini (urutan penting: FK).
        $ids = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->pluck('Id_Buku_Cuti');
        if ($ids->isNotEmpty()) {
            DB::table('N_HRIS_Log_Buku_Cuti')->whereIn('Id_Buku_Cuti', $ids)->delete();
            DB::table('HRIS_Buku_Cuti')->whereIn('Id_Buku_Cuti', $ids)->delete();
        }

        parent::tearDown();
    }

    /** @test */
    public function credit_debit_close_menjaga_saldo_dan_rantai_ledger(): void
    {
        $today = Carbon::now()->toDateString();
        $expired = Carbon::now()->addYear()->toDateString();
        $periode = (int) Carbon::now()->format('Y');

        // ---- 1. CREDIT 5 (buat dompet baru) ----
        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 5,
            'ENGTEST-CR', 'AKRUAL_BULANAN', 'Characterization credit', 'TEST_ENGINE',
            $today, $expired, $periode,
        );

        $wallet = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->first();
        $this->assertNotNull($wallet, 'creditBalance harus membuat dompet baru.');
        $this->assertEqualsWithDelta(5.0, (float) $wallet->sisa_cuti, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $wallet->jumlah_awal_cuti, 0.001);

        $creditRow = $this->latestLedger($wallet->Id_Buku_Cuti);
        $this->assertSame('CREDIT', $creditRow->Jenis_Mutasi);
        $this->assertEqualsWithDelta(5.0, (float) $creditRow->Jumlah, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $creditRow->Saldo_Sebelum, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $creditRow->Saldo_Sesudah, 0.001);
        $this->assertSourceCategory($creditRow, 'accrual_monthly');

        // ---- 2. DEBIT 2 (FIFO) ----
        $this->svc->debitBalance(
            $this->kode, [TransactionService::ID_TAHUNAN], 2,
            'ENGTEST-DB', 'PENGAJUAN_CUTI', 'Characterization debit', 'TEST_ENGINE',
        );

        $wallet = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $wallet->Id_Buku_Cuti)->first();
        $this->assertEqualsWithDelta(3.0, (float) $wallet->sisa_cuti, 0.001);

        $debitRow = $this->latestLedger($wallet->Id_Buku_Cuti);
        $this->assertSame('DEBIT', $debitRow->Jenis_Mutasi);
        $this->assertEqualsWithDelta(-2.0, (float) $debitRow->Jumlah, 0.001, 'DEBIT disimpan sebagai nilai negatif.');
        $this->assertEqualsWithDelta(5.0, (float) $debitRow->Saldo_Sebelum, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $debitRow->Saldo_Sesudah, 0.001);

        // ---- 3. CLOSE (hangus sisa) ----
        $closed = $this->svc->closeBalance($wallet->Id_Buku_Cuti, 'Characterization close', 'TEST_ENGINE', 'ENGTEST-CL');
        $this->assertTrue((bool) $closed);

        $wallet = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $wallet->Id_Buku_Cuti)->first();
        $this->assertEqualsWithDelta(0.0, (float) $wallet->sisa_cuti, 0.001);
        $this->assertSame('T', $wallet->Flag_Aktif);

        // ---- 4. Integritas ledger: Σ Jumlah == sisa_cuti akhir (0), tip == sisa_cuti ----
        $rows = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Id_Buku_Cuti', $wallet->Id_Buku_Cuti)
            ->orderBy('Created_At')->orderBy('Id_Log')
            ->get();

        $sumJumlah = (float) $rows->sum(fn ($r) => (float) $r->Jumlah);
        $this->assertEqualsWithDelta(0.0, $sumJumlah, 0.001, 'Σ Jumlah ledger harus sama dengan saldo akhir (0).');

        $tip = (float) $rows->last()->Saldo_Sesudah;
        $this->assertEqualsWithDelta((float) $wallet->sisa_cuti, $tip, 0.001, 'Tip ledger harus == sisa_cuti (check-C).');

        // Rantai kontinu: row[n].Saldo_Sebelum == row[n-1].Saldo_Sesudah
        $prev = null;
        foreach ($rows as $r) {
            if ($prev !== null) {
                $this->assertEqualsWithDelta((float) $prev, (float) $r->Saldo_Sebelum, 0.001, 'Rantai ledger putus (check-B).');
            }
            // Aritmetika baris: Saldo_Sesudah == Saldo_Sebelum + Jumlah (check-A)
            $this->assertEqualsWithDelta(
                (float) $r->Saldo_Sebelum + (float) $r->Jumlah,
                (float) $r->Saldo_Sesudah,
                0.001,
                'Aritmetika baris ledger salah (check-A).',
            );
            $prev = $r->Saldo_Sesudah;
        }
    }

    /** @test */
    public function credit_kedua_dengan_parameter_sama_menambah_dompet_yang_sama(): void
    {
        $today = Carbon::now()->toDateString();
        $expired = Carbon::now()->addYear()->toDateString();
        $periode = (int) Carbon::now()->format('Y');

        // Credit pertama → buat dompet
        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 5,
            'ENGTEST-CR1', 'AKRUAL_BULANAN', 'Credit 1', 'TEST_ENGINE',
            $today, $expired, $periode,
        );
        // Credit kedua parameter sama → HARUS masuk dompet yang sama (bukan dompet baru)
        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 3,
            'ENGTEST-CR2', 'AKRUAL_BULANAN', 'Credit 2', 'TEST_ENGINE',
            $today, $expired, $periode,
        );

        $wallets = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->get();
        $this->assertCount(1, $wallets, 'Credit kedua dengan parameter sama tidak boleh membuat dompet baru.');

        $wallet = $wallets->first();
        $this->assertEqualsWithDelta(8.0, (float) $wallet->sisa_cuti, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $wallet->jumlah_awal_cuti, 0.001);

        $creditRow = $this->latestLedger($wallet->Id_Buku_Cuti);
        $this->assertSame('CREDIT', $creditRow->Jenis_Mutasi);
        $this->assertEqualsWithDelta(3.0, (float) $creditRow->Jumlah, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $creditRow->Saldo_Sebelum, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $creditRow->Saldo_Sesudah, 0.001);
    }

    /** @test */
    public function maturity_change_mencatat_baris_jumlah_nol_tanpa_ubah_saldo(): void
    {
        $today = Carbon::now()->toDateString();
        $expired = Carbon::now()->addYear()->toDateString();
        $periode = (int) Carbon::now()->format('Y');

        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 5,
            'ENGTEST-CR', 'AKRUAL_BULANAN', 'Credit', 'TEST_ENGINE',
            $today, $expired, $periode,
        );
        $wallet = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->first();
        $sisaSebelum = (float) $wallet->sisa_cuti;

        $this->svc->recordWalletMaturityChange(
            (int) $wallet->Id_Buku_Cuti,
            Carbon::now()->addMonths(3)->toDateString(),
            $today,
            'SYSTEM_SCHEDULER',
        );

        // Saldo wallet TIDAK berubah oleh maturity change.
        $walletAfter = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $wallet->Id_Buku_Cuti)->first();
        $this->assertEqualsWithDelta($sisaSebelum, (float) $walletAfter->sisa_cuti, 0.001);

        // Baris ledger: Jumlah=0, Saldo_Sebelum==Saldo_Sesudah (rantai tetap valid).
        $row = $this->latestLedger($wallet->Id_Buku_Cuti);
        $this->assertSame('MATURITY_ACTIVATED', $row->Kategori_Transaksi);
        $this->assertEqualsWithDelta(0.0, (float) $row->Jumlah, 0.001);
        $this->assertEqualsWithDelta((float) $row->Saldo_Sebelum, (float) $row->Saldo_Sesudah, 0.001);
        $this->assertSourceCategory($row, 'maturity_activated');
    }

    /** @test */
    public function debit_melebihi_saldo_membuat_hutang_lalu_repay_menutupnya(): void
    {
        $today = Carbon::now()->toDateString();
        $expired = Carbon::now()->addYear()->toDateString();
        $periode = (int) Carbon::now()->format('Y');

        // Saldo 3, lalu debit 5 → kurang 2 → hutang.
        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 3,
            'ENGTEST-CR', 'AKRUAL_BULANAN', 'Credit', 'TEST_ENGINE',
            $today, $expired, $periode,
        );
        $this->svc->debitBalanceWithDebt(
            $this->kode, TransactionService::ID_TAHUNAN, 5,
            'ENGTEST-DBT', 'PENGAJUAN_CUTI', 'Debit lebih besar dari saldo', 'TEST_ENGINE',
        );

        // Net saldo = 3 - 5 = -2 (hutang), dan ADA dompet negatif.
        $this->assertEqualsWithDelta(-2.0, $this->sumSisa(), 0.001, 'Net saldo setelah over-debit harus -2 (hutang).');
        $this->assertGreaterThan(0, DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->where('sisa_cuti', '<', 0)->count(), 'Harus ada dompet hutang (sisa_cuti negatif).');

        // Repay 2 → hutang lunas → net 0.
        $this->svc->repayDebt(
            $this->kode, 2, 'ENGTEST-PAY', 'PAY_DEBT', 'Pelunasan', 'TEST_ENGINE', TransactionService::ID_TAHUNAN,
        );
        $this->assertEqualsWithDelta(0.0, $this->sumSisa(), 0.001, 'Setelah repay 2, hutang lunas → net 0.');
    }

    /** @test */
    public function manual_adjustment_add_dan_sub_mengubah_net_saldo(): void
    {
        $today = Carbon::now()->toDateString();
        $expired = Carbon::now()->addYear()->toDateString();
        $periode = (int) Carbon::now()->format('Y');

        $this->svc->creditBalance(
            $this->kode, TransactionService::ID_TAHUNAN, 5,
            'ENGTEST-CR', 'AKRUAL_BULANAN', 'Credit', 'TEST_ENGINE',
            $today, $expired, $periode,
        );

        $this->svc->manualAdjustment($this->kode, TransactionService::ID_TAHUNAN, 'SUB', 2, 'test sub', 'TEST_ENGINE');
        $this->assertEqualsWithDelta(3.0, $this->sumSisa(), 0.001, 'Setelah SUB 2 dari 5 → net 3.');

        $this->svc->manualAdjustment($this->kode, TransactionService::ID_TAHUNAN, 'ADD', 4, 'test add', 'TEST_ENGINE');
        $this->assertEqualsWithDelta(7.0, $this->sumSisa(), 0.001, 'Setelah ADD 4 → net 7.');

        // Kategori adjustment tercatat di ledger.
        $kategoris = DB::table('N_HRIS_Log_Buku_Cuti as l')
            ->join('HRIS_Buku_Cuti as b', 'b.Id_Buku_Cuti', '=', 'l.Id_Buku_Cuti')
            ->where('b.Kode_Karyawan', $this->kode)
            ->pluck('l.Kategori_Transaksi')->map(fn ($k) => strtoupper((string) $k))->all();
        $this->assertContains('ADJUSTMENT_ADD', $kategoris);
        $this->assertTrue(
            in_array('ADJUSTMENT_SUB', $kategoris, true) || in_array('PENGAJUAN_CUTI', $kategoris, true) || in_array('ADJUSTMENT_DEBT', $kategoris, true),
            'Jejak pengurangan manual harus tercatat.',
        );
    }

    /** @test */
    public function backfill_provenance_tidak_pernah_mengubah_saldo_atau_status_wallet(): void
    {
        // Simulasi wallet migrasi = INSERT langsung TANPA ledger (orphan).
        $now = Carbon::now();
        $idBuku = DB::table('HRIS_Buku_Cuti')->insertGetId([
            'Kode_Karyawan' => $this->kode,
            'Id_Jenis_Cuti' => TransactionService::ID_TAHUNAN,
            'Periode' => 2025,
            'tipe_cuti' => 'Tahunan',
            'tanggal_diberikan' => $now->toDateString(),
            'tanggal_expired' => '2027-12-31',
            'Activated_At' => '2025-01-10 00:00:00',
            'jumlah_awal_cuti' => 7,
            'sisa_cuti' => 7,
            'jumlah_terpakai' => 0,
            'notes' => 'Saldo_Cuti',
            'Flag_Aktif' => 'Y',
            'created_at' => $now,
        ]);

        // Snapshot SEMUA kolom yang menentukan hak cuti (sebelum backfill).
        $before = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)
            ->first(['sisa_cuti', 'jumlah_awal_cuti', 'jumlah_terpakai', 'Flag_Aktif', 'Activated_At', 'tanggal_expired', 'Periode', 'Id_Jenis_Cuti']);

        // Jalankan backfill APPLY, di-scope HANYA ke kode sintetis ini.
        $this->artisan('cuti:backfill-provenance', ['--apply' => true, '--kode' => $this->kode])
            ->assertExitCode(0);

        $after = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)
            ->first(['sisa_cuti', 'jumlah_awal_cuti', 'jumlah_terpakai', 'Flag_Aktif', 'Activated_At', 'tanggal_expired', 'Periode', 'Id_Jenis_Cuti', 'Is_Migrated', 'Migration_Basis']);

        // JAMINAN INTI: tidak satu pun kolom saldo/status berubah.
        $this->assertEqualsWithDelta((float) $before->sisa_cuti, (float) $after->sisa_cuti, 0.0001, 'sisa_cuti TIDAK BOLEH berubah oleh backfill.');
        $this->assertEqualsWithDelta((float) $before->jumlah_awal_cuti, (float) $after->jumlah_awal_cuti, 0.0001, 'jumlah_awal_cuti tidak boleh berubah.');
        $this->assertEqualsWithDelta((float) $before->jumlah_terpakai, (float) $after->jumlah_terpakai, 0.0001, 'jumlah_terpakai tidak boleh berubah.');
        $this->assertSame($before->Flag_Aktif, $after->Flag_Aktif, 'Flag_Aktif tidak boleh berubah.');
        $this->assertSame((string) $before->Activated_At, (string) $after->Activated_At, 'Activated_At tidak boleh berubah.');
        $this->assertSame((string) $before->tanggal_expired, (string) $after->tanggal_expired, 'tanggal_expired tidak boleh berubah.');
        $this->assertSame((string) $before->Periode, (string) $after->Periode, 'Periode tidak boleh berubah.');
        $this->assertSame((string) $before->Id_Jenis_Cuti, (string) $after->Id_Jenis_Cuti, 'Id_Jenis_Cuti tidak boleh berubah.');

        // Yang BOLEH berubah: hanya label.
        $this->assertSame('Y', $after->Is_Migrated, 'Wallet orphan seharusnya ditandai migrasi.');
        $this->assertSame('no_opening_ledger', $after->Migration_Basis);

        // Baris opening: MENDOKUMENTASIKAN saldo (Saldo_Sesudah == sisa_cuti), bukan menambah.
        $opening = DB::table('N_HRIS_Log_Buku_Cuti')->where('Cuti_Key', 'OPENING::'.$idBuku)->first();
        $this->assertNotNull($opening, 'Baris opening harus disisipkan untuk wallet migrasi.');
        $this->assertEqualsWithDelta((float) $after->sisa_cuti, (float) $opening->Saldo_Sesudah, 0.0001, 'Opening harus mendokumentasikan saldo apa adanya.');
    }

    private function sumSisa(): float
    {
        return (float) DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->sum('sisa_cuti');
    }

    private function latestLedger(int $idBuku): object
    {
        return DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Id_Buku_Cuti', $idBuku)
            ->orderByDesc('Created_At')->orderByDesc('Id_Log')
            ->first();
    }

    private function assertSourceCategory(object $row, string $expected): void
    {
        if (! Schema::hasColumn('N_HRIS_Log_Buku_Cuti', 'Source_Category')) {
            return; // kolom belum ada (pre-DDL) — lewati assertion ini.
        }
        $this->assertSame($expected, $row->Source_Category, 'Source_Category dual-write tidak sesuai peta.');
    }
}
