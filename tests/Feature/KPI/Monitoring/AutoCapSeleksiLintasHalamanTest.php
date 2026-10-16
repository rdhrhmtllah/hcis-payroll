<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Jobs\KPI\Monitoring\Enforcement\DispatchEnforceKpiBatchJob;
use App\Jobs\KPI\Monitoring\Enforcement\EnforceKpiCapJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiEnforcementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * "Centang semua" di tab Auto Cap harus berarti SELURUH hasil filter.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * `toggleSelectAll` dulu hanya menyentuh `unfilledRows` — baris yang kebetulan
 * tampil di halaman aktif. Admin yang mencentang judul kolom lalu menekan
 * Enforce mengira memproses seluruh karyawan yang cocok filternya, padahal
 * hanya satu halaman yang terkirim; sisanya tertinggal tanpa satu pun tanda.
 * Diukur di produksi 16 Sep 2026: periode Juli punya 97 kandidat di 7 halaman,
 * jadi satu klik memproses 15 dan meninggalkan 82.
 *
 * ── Yang ditegaskan ─────────────────────────────────────────────────────────
 *
 * Fixture-nya SENGAJA berisi lebih banyak karyawan daripada satu halaman
 * (`per_page` minimum 5). Kalau `ids_only` kembali terikat ke satu halaman,
 * jumlahnya tidak akan sama dengan `total_data` dan uji ini merah.
 *
 * Batas eksekusi ikut dijaga: sejak centang-semua bekerja lintas halaman, satu
 * klik bisa menjadwalkan ribuan job dan menabrak batas 2.100 parameter SQL
 * Server. Batas itu harus ditolak sebagai pesan, bukan sebagai galat basis data.
 */
class AutoCapSeleksiLintasHalamanTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2036;

    private const PERIODE = '2036-07';

    /** Lebih banyak dari per_page minimum (5) supaya beda halaman benar-benar ada. */
    private const JUMLAH = 6;

    /** @var string[] */
    private array $kode = [];

    protected function setUp(): void
    {
        parent::setUp();

        $perusahaan = DB::table('Karyawan')->value('Kode_Perusahaan');
        $idTarget = DB::table('KPI_Master_Target')->min('Id_Master_Target');
        $userIdDasar = (int) DB::table('Karyawan')->max('UserID_Web') + 3000;

        if (! $perusahaan || ! $idTarget) {
            $this->markTestSkipped('Butuh minimal satu Karyawan dan satu KPI_Master_Target sebagai acuan.');
        }

        $jadwalForm = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji seleksi lintas halaman — form',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-01-01 00:00:00',
            'Jadwal_Akhir' => self::TAHUN.'-01-31 23:59:59',
        ], 'Id_Master_Penjadwalan');

        for ($i = 1; $i <= self::JUMLAH; $i++) {
            $kode = 'ZS'.Str::upper(Str::random(6));
            $idUser = $userIdDasar + $i;
            $this->kode[] = $kode;

            DB::table('Karyawan')->insert([
                'Kode_Karyawan' => $kode,
                'Kode_Perusahaan' => $perusahaan,
                'Nama' => 'Uji seleksi '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'UserID_Web' => $idUser,
                'Aktif' => 'Y',
            ]);

            $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => 'UJI-SLH-'.$idUser,
                'Id_Penjadwalan' => $jadwalForm,
                'Id_User' => $idUser,
                'Tanggal' => self::TAHUN.'-01-10 00:00:00',
                'Jam' => '09:00:00',
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => 'Y',
                'Status' => null,
            ], 'Id_Pengajuan');

            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $idPengajuan,
                'Nama_Parameter' => 'Parameter uji seleksi',
                'Id_Master_Target' => $idTarget,
                'Bobot' => 100,
                'Kode_Karyawan' => $kode,
            ], 'Id_Pengajuan_Detail');

            DB::table('KPI_Pengajuan_Det')->insert([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target '.self::PERIODE,
                'Nilai_Target' => 100,
                'Periode' => self::PERIODE,
            ]);
        }
    }

    /** @param array<string,mixed> $params */
    private function panggil(array $params): array
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        app()->instance('request', Request::create('/x', 'GET'));
        Auth::setUser($admin);

        $req = Request::create('/x', 'GET', $params + ['periode' => self::PERIODE]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $res = app(KpiEnforcementController::class)->getUnfilledEmployees($req);

        $this->assertSame(200, $res->getStatusCode(), 'Daftar Auto Cap gagal dimuat: '.$res->getContent());

        return json_decode($res->getContent(), true);
    }

    public function test_ids_only_memulangkan_seluruh_hasil_filter_bukan_satu_halaman(): void
    {
        $halaman = $this->panggil(['page' => 1, 'per_page' => 5]);
        $barisHalaman = $halaman['result']['rows'] ?? $halaman['result'] ?? [];

        $this->assertSame(
            5,
            count($barisHalaman),
            'Prasyarat fixture gagal: satu halaman seharusnya tidak memuat seluruh karyawan uji.',
        );
        $this->assertGreaterThan(1, (int) $halaman['total_page'], 'Fixture hanya menghasilkan satu halaman.');

        $ids = $this->panggil(['ids_only' => 1])['result'];

        $this->assertSame(
            (int) $halaman['total_data'],
            (int) $ids['total'],
            'Jumlah id yang dipulangkan tidak sama dengan jumlah baris di tabel. "Centang semua" '.
            'kembali terikat ke halaman aktif, sehingga admin meng-enforce satu halaman sambil '.
            'mengira memproses seluruh hasil filter.',
        );
        $this->assertCount(
            (int) $ids['total'],
            $ids['items'],
            'total dan items berselisih — layar akan menampilkan angka yang tidak bisa dipercaya.',
        );

        $kodeTerpilih = array_column($ids['items'], 'kode_karyawan');
        foreach ($this->kode as $kode) {
            $this->assertContains($kode, $kodeTerpilih, "Karyawan {$kode} tidak ikut tercentang.");
        }
    }

    public function test_ids_only_membawa_identitas_untuk_baris_di_halaman_lain(): void
    {
        $items = $this->panggil(['ids_only' => 1])['result']['items'];

        $this->assertNotEmpty($items);
        foreach (array_slice($items, 0, 3) as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('nama', $item);
            $this->assertArrayHasKey(
                'kode_karyawan',
                $item,
                'Baris terpilih bisa berada di halaman lain, jadi identitasnya tidak bisa dibaca '.
                'dari tabel dan harus ikut dikirim.',
            );
        }
    }

    public function test_batas_eksekusi_ditolak_sebagai_pesan_bukan_galat_basis_data(): void
    {
        $terlaluBanyak = range(1, KpiEnforcementService::MAX_ENFORCE_ITEMS + 1);

        $req = Request::create('/x', 'POST', [
            'periode' => self::PERIODE,
            'id_users' => $terlaluBanyak,
        ]);

        $this->expectException(ValidationException::class);

        app(KpiEnforcementController::class)->enforceCap($req);
    }

    public function test_preview_memakai_batas_yang_sama_dengan_eksekusi(): void
    {
        $terlaluBanyak = range(1, KpiEnforcementService::MAX_ENFORCE_ITEMS + 1);

        $req = Request::create('/x', 'POST', [
            'periode' => self::PERIODE,
            'id_users' => $terlaluBanyak,
        ]);

        $this->expectException(ValidationException::class);

        // Batas di preview harus sama dengan di eksekusi. Kalau preview lebih longgar,
        // admin melihat daftar yang sah lalu ditolak saat menekan tombolnya.
        app(KpiEnforcementController::class)->previewEnforcement($req);
    }

    public function test_pilihan_sebanyak_batas_masih_diterima(): void
    {
        // Menjaga batasnya dari "kelewat satu": tepat MAX harus lolos validasi.
        // Id-nya sengaja milik karyawan uji + pengisi, jadi hasilnya boleh kosong —
        // yang diuji adalah TIDAK adanya ValidationException.
        $ids = array_pad(
            DB::table('Karyawan')->whereIn('Kode_Karyawan', $this->kode)->pluck('UserID_Web')->all(),
            KpiEnforcementService::MAX_ENFORCE_ITEMS,
            0,
        );

        $req = Request::create('/x', 'POST', [
            'periode' => self::PERIODE,
            'id_users' => $ids,
        ]);

        $res = app(KpiEnforcementController::class)->previewEnforcement($req);

        $this->assertSame(200, $res->getStatusCode(), 'Pilihan tepat sebanyak batas ikut ditolak.');
    }

    public function test_eksekusi_dengan_id_sah_menjadwalkan_job_bukan_galat_basis_data(): void
    {
        // Controller mendispatch 1 job induk (DispatchEnforceKpiBatchJob)
        // agar respons langsung 202 tanpa risiko HTTP timeout.
        Bus::fake([DispatchEnforceKpiBatchJob::class, EnforceKpiCapJob::class]);

        $ids = DB::table('Karyawan')->whereIn('Kode_Karyawan', $this->kode)->pluck('UserID_Web')->all();

        $req = Request::create('/x', 'POST', [
            'periode' => self::PERIODE,
            'id_users' => $ids,
        ]);

        $res = app(KpiEnforcementController::class)->enforceCap($req);

        $this->assertSame(202, $res->getStatusCode(), 'Eksekusi auto-cap gagal: '.$res->getContent());
        Bus::assertDispatched(DispatchEnforceKpiBatchJob::class, function ($job) use ($ids) {
            return count($ids) === self::JUMLAH;
        });

        // Verifikasi job induk ketika handle() mendispatch EnforceKpiCapJob per karyawan
        $jobInduk = new DispatchEnforceKpiBatchJob('test-batch-1', $ids, self::PERIODE);
        $jobInduk->handle(app(KpiEnforcementService::class));
        Bus::assertDispatchedTimes(EnforceKpiCapJob::class, self::JUMLAH);
    }
}
