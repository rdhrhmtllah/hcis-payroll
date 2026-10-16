<?php

namespace Tests\Feature\ESS\Attendance\Monitoring;

use App\Http\Middleware\CheckJabatan;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Monitoring\KategoriKehadiran as K;
use App\Services\ESS\Attendance\Monitoring\MonitoringHariIniService;
use App\Services\ESS\Attendance\Monitoring\RekapKehadiranExportService;
use App\Services\ESS\Attendance\Monitoring\RekapPeriodeService;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fikstur: baris snapshot untuk karyawan nyata pada tanggal yang belum punya
 * data di dev (2031), di dalam transaksi. Angka diukur RELATIF terhadap fikstur
 * dengan saringan pencarian kode, supaya baris dev lain tak ikut terhitung.
 */
class MonitoringKehadiranTest extends TestCase
{
    use DatabaseTransactions;

    private const HARI = '2031-03-10';

    /** @var list<string> */
    private array $kode = [];

    protected function setUp(): void
    {
        parent::setUp();

        $karyawan = DB::table('Karyawan')
            ->where('Kode_Perusahaan', '001')->where('Aktif', 'Y')
            ->whereNotNull('UserID_Web')->whereNotNull('ID_Divisi_Sub_Divisi')
            ->orderBy('Kode_Karyawan')->limit(6)->get(['Kode_Karyawan', 'UserID_Web']);

        if ($karyawan->count() < 6) {
            $this->markTestSkipped('Data dev kurang dari 6 karyawan aktif ber-user.');
        }

        DB::table('N_HRIS_Absen_Snapshot')->where('Kode_Perusahaan', '001')
            ->whereBetween('Tanggal', ['2031-03-01', '2031-03-31'])->delete();

        // [StatusHari, Jam_Masuk, CheckIn, CheckOut, RealLate, Cuti, Sakit, Koefisien_OT, Flag_Generate_Ulang]
        $pola = [
            ['WORK', '08:00', '07:55', '17:00', 0, '-', '-', 2, 'T'],   // tepat waktu + lembur
            ['WORK', '08:00', '08:20', null, 20, '-', '-', 0, 'T'],     // terlambat, tanpa check-out
            ['WORK', '08:00', null, null, 0, '-', '-', 0, 'T'],         // belum absen (jam acuan 10:00)
            ['WORK', '13:00', null, null, 0, '-', '-', 0, 'Y'],         // belum jam masuk, sedang diperbarui
            ['OFF-Default_Shift', null, null, null, 0, '-', '-', 0, 'T'],
            ['WORK', '08:00', null, null, 0, 'CT/2031/1', '-', 0, 'T'], // cuti
        ];

        foreach ($karyawan as $i => $k) {
            [$status, $masuk, $in, $out, $late, $cuti, $sakit, $ot, $kotor] = $pola[$i];
            $this->kode[] = (string) $k->Kode_Karyawan;

            foreach (['2031-03-09', self::HARI] as $tgl) {
                DB::table('N_HRIS_Absen_Snapshot')->insert([
                    'Kode_Perusahaan' => '001',
                    'UserID' => (int) $k->UserID_Web,
                    'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Tanggal' => $tgl,
                    'StatusHari' => $status,
                    'Nama_Shift' => 'UJI-MONITORING',
                    'Jam_Masuk' => $masuk,
                    'CheckIn' => $in ? "{$tgl} {$in}:00" : null,
                    'CheckOut' => $out ? "{$tgl} {$out}:00" : null,
                    'RealLate' => $late,
                    'RealEarly' => 0,
                    'Koefisien_OT' => $ot,
                    'No_Transaksi_Cuti' => $cuti,
                    'No_Transaksi_Sakit' => $sakit,
                    'No_Transaksi_Izin' => '-',
                    // Seperti production: snapshot menandai alpha sejak pagi, termasuk hari ini.
                    'Flag_Alpha' => $status === 'WORK' && ! $in && $cuti === '-' ? 'Y' : 'T',
                    'Flag_Generate_Ulang' => $tgl === self::HARI ? $kotor : 'T',
                    'Flag_Proses' => 'T',
                    'Last_Calculated_At' => "{$tgl} 09:30:00",
                ]);
            }
        }

        Carbon::setTestNow(Carbon::parse(self::HARI.' 10:00:00'));
        FunctionalRole::clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Peran dengan cakupan yang dikendalikan uji; null = tanpa batas. */
    private function peran(?array $kode = null): FunctionalRole
    {
        return new class($kode) extends FunctionalRole
        {
            public function __construct(private ?array $kode)
            {
                parent::__construct(1);
            }

            public function applyScope($query)
            {
                return $this->kode === null ? $query : $query->whereIn('Karyawan.Kode_Karyawan', $this->kode);
            }

            public function currentUserId(): int
            {
                return 1;
            }
        };
    }

    private function saringanFikstur(): array
    {
        return ['shift' => ['UJI-MONITORING']];
    }

    public function test_kategori_hari_ini_sesuai_fikstur(): void
    {
        $r = app(MonitoringHariIniService::class)->ringkasan(self::HARI, $this->saringanFikstur(), $this->peran());

        $this->assertSame(6, $r['total']);
        $this->assertSame(1, $r['kategori'][K::TEPAT_WAKTU]);
        $this->assertSame(1, $r['kategori'][K::TERLAMBAT]);
        $this->assertSame(1, $r['kategori'][K::BELUM_ABSEN]);
        $this->assertSame(1, $r['kategori'][K::BELUM_JAM]);
        $this->assertSame(1, $r['kategori'][K::LIBUR]);
        $this->assertSame(1, $r['kategori'][K::CUTI]);
        $this->assertSame(1, $r['tanda']['lembur']);
        $this->assertSame(1, $r['tanda']['diperbarui']);
        // Hari ini belum bisa disebut lupa check-out.
        $this->assertSame(0, $r['tanda']['tanpa_checkout']);
    }

    public function test_angka_kartu_sama_dengan_jumlah_baris_daftar(): void
    {
        $svc = app(MonitoringHariIniService::class);
        $ringkasan = $svc->ringkasan(self::HARI, $this->saringanFikstur(), $this->peran());

        foreach (K::URUTAN as $kategori) {
            $daftar = $svc->daftar(self::HARI, $this->saringanFikstur() + ['kategori' => $kategori, 'limit' => 100], $this->peran());

            $this->assertSame($ringkasan['kategori'][$kategori], $daftar['total'], "Kartu {$kategori}");
            $this->assertCount($daftar['total'], $daftar['items']);
            foreach ($daftar['items'] as $baris) {
                $this->assertSame($kategori, $baris['kategori']);
            }
        }
    }

    public function test_tanggal_kemarin_tak_punya_belum_jam_masuk(): void
    {
        $r = app(MonitoringHariIniService::class)->ringkasan('2031-03-09', $this->saringanFikstur(), $this->peran());

        $this->assertSame(0, $r['kategori'][K::BELUM_JAM]);
        $this->assertSame(2, $r['kategori'][K::BELUM_ABSEN]);
        $this->assertSame(1, $r['tanda']['tanpa_checkout']);
    }

    public function test_cakupan_admin_membatasi_baris(): void
    {
        $svc = app(MonitoringHariIniService::class);
        $terbatas = $svc->daftar(self::HARI, $this->saringanFikstur(), $this->peran([$this->kode[0], $this->kode[1]]));

        $this->assertSame(2, $terbatas['total']);
        $this->assertEqualsCanonicalizing([$this->kode[0], $this->kode[1]], array_column($terbatas['items'], 'kode'));
    }

    public function test_rekap_karyawan_sama_dengan_penjumlahan_rincian_harian(): void
    {
        $svc = app(RekapPeriodeService::class);
        $rekap = $svc->rekapKaryawan('2031-03-01', '2031-03-15', $this->saringanFikstur(), $this->peran(), semua: true);

        $this->assertSame(6, $rekap['total']);
        $this->assertSame(1, $rekap['diperbarui']);

        foreach ($rekap['items'] as $baris) {
            $hari = $svc->rincian($baris['kode'], '2031-03-01', '2031-03-15', $this->peran())['hari'];

            $this->assertSame(count(array_filter($hari, fn ($h) => $h['status_hari'] === 'WORK')), $baris['terjadwal']);
            $this->assertSame(array_sum(array_column($hari, 'terlambat_menit')), $baris['terlambat_menit']);
            $this->assertSame(array_sum(array_column($hari, 'lembur_jam')), $baris['lembur_jam']);
            $this->assertSame(count(array_filter($hari, fn ($h) => $h['tanpa_checkout'])), $baris['lupa_checkout']);
        }

        $terlambat = collect($rekap['items'])->firstWhere('kode', $this->kode[1]);
        $this->assertSame(2, $terlambat['terlambat_kali']);
        $this->assertSame(40, $terlambat['terlambat_menit']);
        // 9 Mar lampau tanpa check-out; 10 Mar = hari ini, belum dihitung lupa.
        $this->assertSame(1, $terlambat['lupa_checkout']);

        // Alpha hari ini belum final: hanya 9 Mar yang terhitung.
        $this->assertSame(1, collect($rekap['items'])->firstWhere('kode', $this->kode[2])['alpha']);
    }

    public function test_rekap_kelompok_menghitung_karyawan_yang_sama(): void
    {
        $svc = app(RekapPeriodeService::class);
        $kelompok = $svc->rekapKelompok('2031-03-01', '2031-03-15', 'lokasi', $this->saringanFikstur(), $this->peran());

        $this->assertSame(6, array_sum(array_column($kelompok, 'karyawan')));
        // Hadir = hari WORK ber-check-in: dua karyawan x dua hari.
        $this->assertSame(4, array_sum(array_column($kelompok, 'hadir')));
    }

    public function test_grafik_rekap_sama_dengan_tabel_rekap(): void
    {
        $svc = app(RekapPeriodeService::class);
        $f = $this->saringanFikstur();
        $grafik = $svc->grafik('2031-03-01', '2031-03-15', 'lokasi', $f, $this->peran());
        $rekap = $svc->rekapKaryawan('2031-03-01', '2031-03-15', $f, $this->peran(), semua: true)['items'];

        // Tanggal depan tidak ikut: periode dihitung sampai hari ini.
        $this->assertSame(['2031-03-09', self::HARI], array_column($grafik['tren'], 'tanggal'));
        foreach (['terjadwal', 'hadir', 'sakit', 'izin', 'cuti', 'alpha'] as $kolom) {
            $this->assertSame(array_sum(array_column($rekap, $kolom)), array_sum(array_column($grafik['tren'], $kolom)), "Tren {$kolom}");
        }
        $this->assertSame(2, array_sum(array_column($grafik['tren'], 'hadir_terlambat')));

        foreach ($grafik['kelompok'] as $unit) {
            $selUnit = array_filter($grafik['sel'], fn ($c) => $c['kelompok'] === $unit['kelompok']);
            $this->assertSame($unit['terjadwal'], array_sum(array_column($selUnit, 'terjadwal')), "Sel {$unit['kelompok']}");
            $this->assertSame($unit['hadir'], array_sum(array_column($selUnit, 'hadir')));
            $this->assertSame($unit['alpha'], array_sum(array_column($selUnit, 'alpha')));
        }

        // Pohon hierarki (Lokasi -> Divisi -> Departemen -> Karyawan)
        $pohon = $grafik['hierarki'];
        $this->assertSame(6, $pohon['karyawan_count']);
        $this->assertSame(array_sum(array_column($rekap, 'terjadwal')), $pohon['terjadwal']);
        $this->assertSame(array_sum(array_column($rekap, 'hadir')), $pohon['hadir']);
        $this->assertNotEmpty($pohon['children']);
        $this->assertSame('lokasi', $pohon['children'][0]['tingkat']);
    }

    public function test_sebaran_check_in_per_15_menit(): void
    {
        $g = app(MonitoringHariIniService::class)->grafik(self::HARI, $this->saringanFikstur(), $this->peran());

        // 07:55 -> 07:45 (465), 08:20 -> 08:15 (495).
        $this->assertSame([['menit' => 465, 'jumlah' => 1], ['menit' => 495, 'jumlah' => 1]], $g['sebaran']);
        $this->assertSame('08:00', $g['jam_masuk_umum']);
    }

    public function test_rincian_karyawan_di_luar_cakupan_kosong(): void
    {
        $rincian = app(RekapPeriodeService::class)->rincian($this->kode[5], '2031-03-01', '2031-03-15', $this->peran([$this->kode[0]]));

        $this->assertNull($rincian);
    }

    public function test_ekspor_tanpa_sesi_login_memakai_cakupan_pemohon(): void
    {
        $pemohon = DB::table('N_HRIS_Role_User')->where('Flag_Aktif', 'Y')->value('UserId_Web');
        if (! $pemohon) {
            $this->markTestSkipped('Data dev tak punya pemegang role.');
        }

        $harapan = app(RekapPeriodeService::class)
            ->rekapKaryawan('2031-03-01', '2031-03-15', $this->saringanFikstur(), new FunctionalRole((int) $pemohon), semua: true);

        Auth::logout();
        FunctionalRole::clearCache();

        $ekspor = app(RekapKehadiranExportService::class);
        $metode = new \ReflectionMethod($ekspor, 'buildDataset');
        $dataset = $metode->invoke($ekspor, (object) ['Payload_Json' => json_encode([
            'dari' => '2031-03-01', 'sampai' => '2031-03-15',
            'filter' => $this->saringanFikstur(), 'user_id' => (int) $pemohon,
        ])]);

        $this->assertSame(array_column($harapan['items'], 'kode'), array_column($dataset['rows'], 'kode'));
    }

    public function test_tanpa_hak_halaman_semua_endpoint_403(): void
    {
        $user = User::query()->whereNotNull('Id_Users')->limit(20)->get()
            ->first(fn ($u) => ! $u->memilikiJabatan('essMonitoringKehadiranPage'));
        if (! $user) {
            $this->markTestSkipped('Semua pengguna dev memegang halaman ini.');
        }

        foreach (['', '/data/hari-ini', '/data/rekap?dari=2031-03-01&sampai=2031-03-15', '/data/rekap-kelompok', '/data/rincian/X', '/ekspor'] as $jalur) {
            $this->actingAs($user)->getJson('/admin/monitoring-kehadiran'.$jalur)->assertStatus(403);
        }
        $this->actingAs($user)->postJson('/admin/monitoring-kehadiran/ekspor', [])->assertStatus(403);
    }

    public function test_rentang_lebih_dari_62_hari_ditolak_422(): void
    {
        $user = User::query()->whereNotNull('Id_Users')->first();
        $this->withoutMiddleware(CheckJabatan::class);

        $this->actingAs($user)->getJson('/admin/monitoring-kehadiran/data/rekap?dari=2031-01-01&sampai=2031-03-04')
            ->assertStatus(422)->assertJsonPath('error_code', 'ESS_MONITORING_RENTANG_INVALID');
        $this->actingAs($user)->postJson('/admin/monitoring-kehadiran/ekspor', ['dari' => '2031-03-15', 'sampai' => '2031-03-01'])
            ->assertStatus(422);
    }
}
