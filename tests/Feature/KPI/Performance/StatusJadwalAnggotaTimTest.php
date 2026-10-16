<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Leader harus bisa melihat cakupan penjadwalan TIAP anggota timnya.
 *
 * ── Kenapa ini ada ───────────────────────────────────────────────────────────
 *
 * Payung tim adalah SATU jadwal untuk seluruh tim: begitu tak ketemu, halaman
 * menutup untuk semua orang sekaligus tanpa memberi tahu apa pun. Padahal
 * penjadwalan perorangan pada dasarnya per orang — anggota A bisa terjadwal
 * mulai Maret sementara anggota B mulai Juni.
 *
 * Datanya sudah lama ada: N_KPI_Master_Penjadwalan_Perorangan menyimpan
 * Start_Periode per Kode_Karyawan. Yang belum ada hanyalah jalan bagi leader
 * untuk melihatnya, sehingga "tim saya sudah dijadwalkan" dan "halaman bilang
 * tidak ada penjadwalan" tak pernah bisa dipertemukan.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 *  1. Anggota yang TERDAFTAR muncul lengkap dengan Start_Periode-nya.
 *  2. Anggota yang TIDAK terdaftar tetap muncul, ditandai belum terjadwal —
 *     justru merekalah yang perlu dilihat leader.
 *  3. Biayanya tidak tumbuh per anggota.
 */
class StatusJadwalAnggotaTimTest extends TestCase
{
    use DatabaseTransactions;

    private string $leader;

    /** @var array<int,string> */
    private array $anggota = [];

    protected function setUp(): void
    {
        parent::setUp();

        $contoh = DB::table('Karyawan')->first(['Kode_Perusahaan']);
        if (! $contoh) {
            $this->markTestSkipped('Tabel Karyawan kosong.');
        }

        $perusahaan = trim((string) ($contoh->Kode_Perusahaan ?: '001'));
        $this->leader = 'ZL'.Str::upper(Str::random(6));

        foreach (['Terjadwal', 'Belum'] as $i => $label) {
            $kode = 'ZA'.Str::upper(Str::random(6));
            $this->anggota[$label] = $kode;

            DB::table('Karyawan')->insert([
                'Kode_Karyawan' => $kode,
                'Kode_Perusahaan' => $perusahaan,
                'Nama' => "Anggota {$label} {$i}",
                'Aktif' => 'Y',
            ]);

            DB::table('KPI_Karyawan_Team')->insert([
                'Kode_Karyawan_Leader' => $this->leader,
                'Kode_Karyawan_Team' => $kode,
                'Flag_Aktif' => 'Y',
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function statusAnggota(): array
    {
        $metode = new ReflectionMethod(FormKpiController::class, 'statusJadwalAnggotaTim');
        $metode->setAccessible(true);

        return $metode->invoke(app(FormKpiController::class), $this->leader, date('Y-m-d'));
    }

    public function test_anggota_terjadwal_dan_belum_terjadwal_sama_sama_terlihat(): void
    {
        $idJadwal = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji perorangan tim',
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'T',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDay()->format('Y-m-d'),
            'Jadwal_Akhir' => now()->addDay()->format('Y-m-d'),
        ]);

        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            'Id_Master_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $this->anggota['Terjadwal'],
            'Start_Periode' => '2026-03',
            'Keterangan' => 'uji',
            'Flag_Aktif' => 'Y',
        ]);

        $status = collect($this->statusAnggota())->keyBy('kode_karyawan');

        $this->assertCount(2, $status, 'Kedua anggota tim harus muncul, terjadwal maupun tidak.');

        $terjadwal = $status[$this->anggota['Terjadwal']];
        $this->assertTrue($terjadwal['terjadwal']);
        $this->assertSame('2026-03', $terjadwal['start_periode'], 'Start_Periode anggota tidak ikut ditampilkan.');
        $this->assertTrue($terjadwal['terbuka'], 'Jendelanya mencakup hari ini, seharusnya terbuka.');

        $belum = $status[$this->anggota['Belum']];
        $this->assertFalse(
            $belum['terjadwal'],
            'Anggota yang belum terdaftar harus tetap muncul dan ditandai — justru merekalah '.
            'yang perlu dilihat leader.',
        );
        $this->assertSame('Belum terdaftar di penjadwalan perorangan mana pun', $belum['keterangan']);
    }

    public function test_jendela_yang_lewat_ditandai_tertutup_beserta_tanggalnya(): void
    {
        $akhir = now()->subDays(4)->format('Y-m-d');

        $idJadwal = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji perorangan lewat',
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'T',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(20)->format('Y-m-d'),
            'Jadwal_Akhir' => $akhir,
        ]);

        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            'Id_Master_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $this->anggota['Terjadwal'],
            'Start_Periode' => '2026-06',
            'Keterangan' => 'uji',
            'Flag_Aktif' => 'Y',
        ]);

        $baris = collect($this->statusAnggota())->firstWhere('kode_karyawan', $this->anggota['Terjadwal']);

        $this->assertTrue($baris['terjadwal'], 'Anggotanya terdaftar, jadi harus tetap dianggap terjadwal.');
        $this->assertFalse($baris['terbuka']);
        $this->assertSame("Jendela pengisian sudah ditutup {$akhir}", $baris['keterangan']);
    }

    public function test_biaya_tidak_tumbuh_per_anggota(): void
    {
        $n = 0;
        DB::listen(function ($q) use (&$n) {
            if (str_contains(strtolower($q->sql), 'n_kpi_master_penjadwalan_perorangan')) {
                $n++;
            }
        });

        $this->statusAnggota();

        $this->assertLessThanOrEqual(
            1,
            $n,
            "Roster perorangan dibaca {$n} kali; daftar ini dihitung pada SETIAP muat halaman tim, ".
            'jadi bentuk per-anggota akan mengalikan biayanya dengan besar tim.',
        );
    }
}
