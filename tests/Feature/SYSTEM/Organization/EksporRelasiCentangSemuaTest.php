<?php

namespace Tests\Feature\SYSTEM\Organization;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Modal Export: daftar berhalaman, "Centang semua yang cocok", dan unduhan POST untuk ratusan karyawan. */
class EksporRelasiCentangSemuaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::find(4);
        if ($admin === null) {
            $this->markTestSkipped('User admin dev (4) tidak ada.');
        }
        $this->actingAs($admin);
    }

    public function test_cari_karyawan_berhalaman_tanpa_tumpang_tindih(): void
    {
        $h1 = $this->getJson('/system/organisasi/relasi/cari-karyawan?limit=50&halaman=1')->assertOk()->json('result');
        $h2 = $this->getJson('/system/organisasi/relasi/cari-karyawan?limit=50&halaman=2')->assertOk()->json('result');
        if ($h1['total'] <= 50) {
            $this->markTestSkipped('Karyawan dev kurang dari dua halaman.');
        }
        $kode = fn ($b) => array_map(fn ($r) => $r['kode_perusahaan'].'|'.$r['kode_karyawan'], $b['baris']);
        $this->assertCount(50, $h1['baris']);
        $this->assertSame([], array_intersect($kode($h1), $kode($h2)), 'Halaman 2 tidak mengulang halaman 1.');
        $this->assertSame(2, $h2['halaman']);
    }

    public function test_semua_yang_cocok_memulangkan_seluruhnya_dan_bisa_diekspor_lewat_post(): void
    {
        $semua = $this->getJson('/system/organisasi/relasi/cari-karyawan/semua')->assertOk()->json('result');
        $this->assertSame($semua['total'], count($semua['baris']));
        $this->assertGreaterThan(200, $semua['total'], 'Lebih dari batas lama 200.');

        $karyawan = array_map(fn ($r) => trim($r['kode_perusahaan']).'|'.trim($r['kode_karyawan']), $semua['baris']);
        $res = $this->postJson('/system/organisasi/relasi/ekspor', ['karyawan' => $karyawan]);
        $res->assertOk();

        $harap = DB::table('N_HRIS_Organization_Relationship')->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')
            ->whereIn('Kode_Karyawan_Subjek', array_map(fn ($r) => trim($r['kode_karyawan']), $semua['baris']))->count();
        $this->assertSame((string) $harap, $res->headers->get('X-Jumlah-Baris'));
    }
}
