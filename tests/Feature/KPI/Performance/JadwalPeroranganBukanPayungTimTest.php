<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use App\Services\KPI\KpiStartPeriodeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jadwal PERORANGAN tidak boleh jadi payung pengisian TIM.
 *
 * Rosternya hanya memuat sebagian karyawan. Anggota di luar roster tidak punya
 * bulan mulai di sana, sehingga resolver jatuh ke `Periode` jadwal itu — yang
 * pada jadwal perorangan biasanya NULL — dan normalkan(null) mengubahnya jadi
 * Januari. Tidak ada galat, tidak ada peringatan; hanya sebelas bulan target
 * yang tak pernah dijadwalkan ikut tersimpan lalu menekan pencapaian orangnya
 * di YTD.
 *
 * Ini pernah terjadi sungguhan: PKPI08-26-0054 (Dhea) dan -0055 (Anita)
 * tersimpan mulai 2026-01 lewat jadwal 57, sementara Amelia yang ada di
 * rosternya tersimpan benar mulai 2026-02.
 */
class JadwalPeroranganBukanPayungTimTest extends TestCase
{
    use DatabaseTransactions;

    public function test_jadwal_perorangan_ditolak_sebagai_payung_tim(): void
    {
        [$perorangan, $global] = $this->buatDuaJadwal();

        $this->assertFalse(
            $this->bolehJadiPayungTim($perorangan),
            'Jadwal perorangan masih bisa dipakai sebagai jadwal tim.',
        );
        $this->assertTrue($this->bolehJadiPayungTim($global));
    }

    public function test_pemilihan_otomatis_melewati_jadwal_perorangan_walau_id_nya_lebih_baru(): void
    {
        // Pemilihannya ORDER BY Id DESC, jadi jadwal perorangan yang dibuat
        // BELAKANGAN akan menang kalau tidak disaring. Fixture ini sengaja
        // membuat yang perorangan terakhir supaya penjaganya benar-benar diuji.
        [$perorangan, $global] = $this->buatDuaJadwal(peroranganTerakhir: true);

        $this->assertGreaterThan($global, $perorangan, 'Fixture tidak menguji apa pun: perorangan harus ber-Id lebih besar.');

        $terpilih = $this->queryTim()
            ->whereDate('Jadwal_Mulai', '<=', now()->toDateString())
            ->whereDate('Jadwal_Akhir', '>=', now()->toDateString())
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        $this->assertNotSame(
            $perorangan,
            (int) $terpilih->Id_Master_Penjadwalan,
            'Jadwal perorangan terbaru masih memenangi pemilihan otomatis.',
        );
    }

    public function test_anggota_di_luar_roster_tidak_jatuh_ke_januari(): void
    {
        // Inti bug-nya. Dengan payung yang benar (global ber-Periode), anggota
        // tanpa jadwal perorangan mengikuti Periode global — bukan Januari.
        [$perorangan, $global] = $this->buatDuaJadwal();

        $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $global)->first();
        $jadwalPerorangan = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $perorangan)->first();
        $resolver = app(KpiStartPeriodeResolver::class);

        $luarRoster = 'ZZ-LUAR-ROSTER';

        $this->assertSame(
            $jadwalGlobal->Tahun.'-05',
            $resolver->resolve($luarRoster, $jadwalGlobal)['mulai'],
            'Anggota tanpa jadwal perorangan harus mengikuti Periode jadwal global.',
        );

        // Dulu di sinilah Januari lahir: payung perorangan ber-Periode NULL
        // membuat siapa pun di luar rosternya mulai dari bulan 1. Assertion ini
        // dahulu MENEGASKAN Januari, sebagai bukti bahaya yang menjadi alasan
        // larangan payung perorangan dipasang — dan pesannya sendiri meminta
        // ditinjau ulang bila prasyaratnya berubah.
        //
        // Prasyaratnya memang sudah berubah. KpiStartPeriodeResolver kini
        // memulangkan 'tidak_tercakup' alih-alih sebuah bulan, sehingga
        // bahayanya tertutup di sumbernya. Yang dijaga di sini karena itu
        // dibalik: Januari TIDAK BOLEH muncul lagi.
        $hasilPerorangan = $resolver->resolve($luarRoster, $jadwalPerorangan);

        $this->assertNotSame(
            $jadwalPerorangan->Tahun.'-01',
            $hasilPerorangan['mulai'],
            'Payung perorangan kembali memberi Januari kepada orang di luar rosternya.',
        );
        $this->assertFalse($hasilPerorangan['tercakup'], 'Ketidaktercakupan harus dinyatakan.');
        $this->assertNull($hasilPerorangan['mulai']);
    }

    public function test_payung_tim_tanpa_periode_tidak_pernah_lolos_penyaring(): void
    {
        // Penjaga lapis ketiga bekerja saat menyimpan; yang bisa diuji murni di
        // sini adalah bahwa tidak ada jadwal tim sah yang Periode-nya kosong,
        // sehingga penjaga itu tidak akan memblokir pengajuan yang benar.
        $tanpaPeriode = $this->queryTim()->whereNull('Periode')->count();

        $this->assertSame(0, $tanpaPeriode, 'Ada jadwal tim tanpa Periode — pengajuannya akan ditolak penjaga.');
    }

    // ---------------------------------------------------------------- helpers

    private function queryTim()
    {
        $metode = new \ReflectionMethod(FormKpiController::class, 'jadwalTimQuery');
        $metode->setAccessible(true);

        return $metode->invoke(app(FormKpiController::class));
    }

    private function bolehJadiPayungTim(int $id): bool
    {
        return $this->queryTim()->where('Id_Master_Penjadwalan', $id)->exists();
    }

    /**
     * @return array{0:int,1:int} [id perorangan, id global]
     */
    private function buatDuaJadwal(bool $peroranganTerakhir = false): array
    {
        $baris = [
            'perorangan' => [
                'Nama_Kegiatan' => 'Uji perorangan',
                'Tahun' => '2026',
                // Persis bentuk jadwal 57: perorangan, TANPA Periode.
                'Periode' => null,
                'Flag_Perorangan' => 'Y',
                'Flag_Aktif' => 'Y',
                'Flag_Form_KPI' => 'Y',
                'Jadwal_Mulai' => now()->subDay(),
                'Jadwal_Akhir' => now()->addDay(),
            ],
            'global' => [
                'Nama_Kegiatan' => 'Uji global',
                'Tahun' => '2026',
                'Periode' => '2026-05',
                'Flag_Perorangan' => 'T',
                'Flag_Aktif' => 'Y',
                'Flag_Form_KPI' => 'Y',
                'Jadwal_Mulai' => now()->subDay(),
                'Jadwal_Akhir' => now()->addDay(),
            ],
        ];

        $urutan = $peroranganTerakhir ? ['global', 'perorangan'] : ['perorangan', 'global'];
        $id = [];

        foreach ($urutan as $kunci) {
            $id[$kunci] = DB::table('KPI_Master_Penjadwalan')->insertGetId($baris[$kunci]);
        }

        return [$id['perorangan'], $id['global']];
    }
}
