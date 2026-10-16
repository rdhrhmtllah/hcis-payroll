<?php

namespace Tests\Feature\ESS\Attendance;

use App\Console\Commands\ESS\Attendance\Leave\Admin\BatasCutiAkhirTahun;
use App\Helpers\FormatTanggalHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Test fitur GRACE tahun pertama pada cuti:tutupBukuCuti.
 *
 *  Deteksi (graceEligibleKodes):
 *   - tahun-pertama (MIN(Activated_At)=tahun-tutup + Tgl_Masuk ≤ 1,5 th) → eligible.
 *   - wallet Is_Migrated='Y' → ter-eksklusi (hard-gate migrasi).
 *   - masa kerja > 1,5 th → tidak grace.
 *  Integrasi (jalankan command NYATA, scoped --kode):
 *   - grace → carry-over 100% (bukan cap 6).
 *   - non-grace → dipotong cap 6.
 *
 * Env-gated, self-cleaning, kode + Karyawan sintetis (Aktif='T'). Data diselaraskan
 * ke tahun dari dummy clock 'cutiTutupBuku' agar command benar-benar memproses.
 *   RUN_REAL_CUTI_ENGINE_TEST=true php artisan test --filter CutiTutupBukuGrace
 */
class CutiTutupBukuGraceTest extends TestCase
{
    private string $kode;

    private int $lastYear;

    private int $currentYear;

    private string $cutoff18mo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_REAL_CUTI_ENGINE_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_ENGINE_TEST=true untuk menjalankan test grace (menulis DB dev).');
        }

        // Selaraskan dengan jam yang dipakai command.
        $now = FormatTanggalHelper::getCurrentTime('cutiTutupBuku');
        $this->lastYear = $now->copy()->subYear()->year;
        $this->currentYear = $now->year;
        $this->cutoff18mo = $now->copy()->subMonths(18)->toDateString();

        $this->kode = 'ZZGRACE_'.substr(md5(uniqid('', true)), 0, 10);

        $kodePerusahaan = DB::table('Karyawan')->value('Kode_Perusahaan') ?? 'ZZ';
        DB::table('Karyawan')->insert([
            'Kode_Perusahaan' => $kodePerusahaan,
            'Kode_Karyawan' => $this->kode,
            'Tgl_Masuk' => $now->copy()->subMonths(15)->toDateString(), // ≤ 1,5 th → lolos backstop
            'Aktif' => 'T',
        ]);

        // Wallet Tahunan tahun-pertama: Periode & Activated_At = $lastYear, sisa 9 (>cap 6).
        DB::table('HRIS_Buku_Cuti')->insert([
            'Kode_Karyawan' => $this->kode,
            'Id_Jenis_Cuti' => 1,
            'Periode' => $this->lastYear,
            'tipe_cuti' => 'Tahunan',
            'tanggal_diberikan' => Carbon::create($this->lastYear, 3, 1)->toDateString(),
            'tanggal_expired' => Carbon::create($this->lastYear + 1, 12, 31)->toDateString(),
            'Activated_At' => Carbon::create($this->lastYear, 3, 15)->toDateString(),
            'jumlah_awal_cuti' => 9,
            'sisa_cuti' => 9,
            'jumlah_terpakai' => 0,
            'notes' => 'Saldo_Cuti',
            'Flag_Aktif' => 'Y',
            'created_at' => Carbon::now(),
        ]);
    }

    protected function tearDown(): void
    {
        $ids = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)->pluck('Id_Buku_Cuti');
        if ($ids->isNotEmpty()) {
            DB::table('N_HRIS_Log_Buku_Cuti')->whereIn('Id_Buku_Cuti', $ids)->delete();
            DB::table('HRIS_Buku_Cuti')->whereIn('Id_Buku_Cuti', $ids)->delete();
        }
        DB::table('Karyawan')->where('Kode_Karyawan', $this->kode)->delete();

        parent::tearDown();
    }

    private function graceSet(): array
    {
        $m = new ReflectionMethod(BatasCutiAkhirTahun::class, 'graceEligibleKodes');
        $m->setAccessible(true);

        return $m->invoke(app(BatasCutiAkhirTahun::class), $this->lastYear, $this->cutoff18mo, $this->kode);
    }

    private function carryOverWallet(): ?object
    {
        return DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->kode)
            ->where('Periode', $this->currentYear)
            ->where('Flag_Aktif', 'Y')
            ->first();
    }

    // ---- Deteksi ----

    /** @test */
    public function karyawan_tahun_pertama_masuk_set_grace(): void
    {
        $this->assertArrayHasKey($this->kode, $this->graceSet(), 'Karyawan tahun-pertama seharusnya berhak grace.');
    }

    /** @test */
    public function wallet_migrasi_tereksklusi_dari_grace(): void
    {
        $this->assertArrayHasKey($this->kode, $this->graceSet());
        DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)
            ->update(['Is_Migrated' => 'Y', 'Migration_Basis' => 'no_opening_ledger']);
        $this->assertArrayNotHasKey($this->kode, $this->graceSet(), 'Wallet migrasi TIDAK boleh dapat grace.');
    }

    /** @test */
    public function masa_kerja_lebih_dari_18_bulan_tidak_grace(): void
    {
        DB::table('Karyawan')->where('Kode_Karyawan', $this->kode)
            ->update(['Tgl_Masuk' => Carbon::parse($this->cutoff18mo)->subMonths(12)->toDateString()]);
        $this->assertArrayNotHasKey($this->kode, $this->graceSet(), 'Masa kerja > 1,5 th tidak boleh dapat grace.');
    }

    // ---- Integrasi (command nyata) ----

    /** @test */
    public function grace_membawa_100_persen_tanpa_cap(): void
    {
        $this->artisan('cuti:tutupBukuCuti', ['--kode' => $this->kode])->assertExitCode(0);

        // Wallet lama ditutup.
        $old = DB::table('HRIS_Buku_Cuti')->where('Kode_Karyawan', $this->kode)
            ->where('Periode', $this->lastYear)->first();
        $this->assertSame('T', $old->Flag_Aktif);
        $this->assertEqualsWithDelta(0.0, (float) $old->sisa_cuti, 0.001);

        // Carry-over 100% = 9 (BUKAN cap 6).
        $new = $this->carryOverWallet();
        $this->assertNotNull($new, 'Harus ada wallet carry-over baru periode berjalan.');
        $this->assertEqualsWithDelta(9.0, (float) $new->sisa_cuti, 0.001, 'Grace harus membawa 100% (9), bukan cap 6.');
    }

    /** @test */
    public function tanpa_grace_dipotong_cap_6(): void
    {
        // Jadikan senior → gagal backstop → tidak grace.
        DB::table('Karyawan')->where('Kode_Karyawan', $this->kode)
            ->update(['Tgl_Masuk' => Carbon::parse($this->cutoff18mo)->subMonths(12)->toDateString()]);

        $this->artisan('cuti:tutupBukuCuti', ['--kode' => $this->kode])->assertExitCode(0);

        $new = $this->carryOverWallet();
        $this->assertNotNull($new);
        $this->assertEqualsWithDelta(6.0, (float) $new->sisa_cuti, 0.001, 'Tanpa grace harus dipotong cap 6.');
    }
}
