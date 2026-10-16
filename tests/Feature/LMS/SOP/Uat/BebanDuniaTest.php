<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatPembangunDunia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemilihan sasaran untuk uji beban.
 *
 * Dipisahkan dari pembangunan dunianya karena inilah bagian yang menentukan
 * apakah jalur ANTREAN tersentuh: ambang async 30, dan populasi dev hanya 58
 * karyawan aktif. Salah memilih di sini membuat seluruh uji beban diam-diam
 * mengukur jalur inline, lalu melaporkannya seolah jalur antrean aman.
 */
class BebanDuniaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_memilih_karyawan_aktif_sebanyak_yang_diminta(): void
    {
        $kode = app(UatPembangunDunia::class)->pilihKaryawanAktif(10);

        $this->assertCount(10, $kode);
        $this->assertSame(array_values(array_unique($kode)), $kode, 'Ada kode karyawan kembar.');

        $nonaktif = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $kode)
            ->where('Aktif', '<>', 'Y')
            ->count();

        $this->assertSame(0, $nonaktif, 'Karyawan nonaktif ikut terpilih.');
    }

    public function test_meminta_lebih_banyak_dari_yang_ada_memberi_sebanyak_yang_ada(): void
    {
        $tersedia = DB::table('Karyawan')->where('Aktif', 'Y')->count();

        $kode = app(UatPembangunDunia::class)->pilihKaryawanAktif($tersedia + 50);

        $this->assertCount($tersedia, $kode,
            'Permintaan berlebih harus mengembalikan sebanyak yang ada, bukan melempar '
            .'atau diam-diam memotong ke angka lain.');
    }

    public function test_pilihannya_sama_pada_pemanggilan_berulang(): void
    {
        $svc = app(UatPembangunDunia::class);

        $this->assertSame($svc->pilihKaryawanAktif(12), $svc->pilihKaryawanAktif(12),
            'Sasaran berganti antar-jalan membuat hasil dua jalan-uji tak bisa dibandingkan.');
    }
}
