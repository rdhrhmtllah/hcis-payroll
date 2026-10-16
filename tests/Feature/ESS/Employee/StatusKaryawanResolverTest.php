<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\StatusKaryawanResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusKaryawanResolverTest extends TestCase
{
    private function resolver(): StatusKaryawanResolver
    {
        return app(StatusKaryawanResolver::class);
    }

    private function ambilKode(bool $phl): ?string
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->when($phl,
                fn ($q) => $q->where('lvl.ID_Level', 21),
                fn ($q) => $q->where(fn ($w) => $w->where('lvl.ID_Level', '<>', 21)->orWhereNull('lvl.ID_Level')),
            )
            ->value('k.Kode_Karyawan');
    }

    public function test_karyawan_level_21_dikenali_sebagai_phl(): void
    {
        $kode = $this->ambilKode(phl: true);
        $this->assertNotNull($kode, 'Butuh minimal satu karyawan PHL aktif di DB untuk uji ini.');

        $this->assertTrue($this->resolver()->isPhl($kode));
    }

    public function test_karyawan_biasa_bukan_phl(): void
    {
        $kode = $this->ambilKode(phl: false);
        $this->assertNotNull($kode, 'Butuh minimal satu karyawan non-PHL aktif di DB untuk uji ini.');

        $this->assertFalse($this->resolver()->isPhl($kode));
    }

    public function test_kode_tidak_dikenal_dianggap_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->isPhl('KODE-TIDAK-ADA-XYZ'));
    }

    /**
     * Penjaga konvensi. ID_Level 21 di-hardcode di tiga tempat (di sini,
     * SopPenjadwalanTargetService, picker LMS). Kolom Flag_Harian adalah
     * penanda semantiknya. Bila keduanya berpisah, salah satu modul sedang
     * memakai definisi PHL yang berbeda — dan test ini yang memberi tahu.
     */
    public function test_level_21_masih_bertepatan_dengan_flag_harian(): void
    {
        $tidakCocok = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('ID_Level', 21)->where(fn ($x) => $x->where('Flag_Harian', '<>', 'Y')->orWhereNull('Flag_Harian')))
                    ->orWhere(fn ($w) => $w->where('Flag_Harian', 'Y')->where('ID_Level', '<>', 21));
            })
            ->count();

        $this->assertSame(0, $tidakCocok, 'ID_Level 21 dan Flag_Harian=Y tidak lagi menunjuk populasi yang sama — definisi PHL perlu ditinjau ulang.');
    }
}
