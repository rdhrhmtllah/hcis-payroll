<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KonfigurasiKampanyeTest extends TestCase
{
    use DatabaseTransactions;

    private function konfigurasi(): KonfigurasiKampanye
    {
        return app(KonfigurasiKampanye::class);
    }

    public function test_membaca_sakelar_dari_baris_config(): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'Y']);
        $this->konfigurasi()->bersihkanCache();

        $this->assertTrue($this->konfigurasi()->aktif());

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        $this->konfigurasi()->bersihkanCache();

        $this->assertFalse($this->konfigurasi()->aktif());
    }

    public function test_pengumuman_membawa_judul_dan_teks(): void
    {
        $pengumuman = $this->konfigurasi()->pengumuman();

        $this->assertArrayHasKey('judul', $pengumuman);
        $this->assertArrayHasKey('teks', $pengumuman);
        $this->assertNotSame('', $pengumuman['judul']);
    }
}
