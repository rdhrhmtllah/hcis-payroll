<?php

namespace Tests\Unit\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\StatusKaryawanResolver;
use Tests\TestCase;

/**
 * Aturan PHL diuji terpisah dari database.
 *
 * Alasannya bukan kerapian: di data nyata SEMUA karyawan level 21 kebetulan
 * bercabang EMI, jadi kombinasi "level 21 tapi cabang lain" — yaitu justru
 * jaring pengaman yang diminta — mustahil dibuktikan lewat query. Tanpa uji
 * murni ini, kriteria cabang bisa saja tidak berpengaruh apa-apa dan tak ada
 * yang tahu.
 */
class KriteriaPhlTest extends TestCase
{
    private function resolver(): StatusKaryawanResolver
    {
        return app(StatusKaryawanResolver::class);
    }

    public function test_level_dan_cabang_cocok_maka_phl(): void
    {
        $this->assertTrue($this->resolver()->cocokKriteria(21, 'EMI'));
    }

    public function test_level_cocok_tapi_cabang_lain_maka_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->cocokKriteria(21, 'CELENTANG'));
        $this->assertFalse($this->resolver()->cocokKriteria(21, 'HEAD OFFICE EVO'));
    }

    public function test_cabang_cocok_tapi_level_lain_maka_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->cocokKriteria(2, 'EMI'));
        $this->assertFalse($this->resolver()->cocokKriteria(10, 'EMI'));
    }

    public function test_dua_duanya_meleset_maka_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->cocokKriteria(4, 'CELENTANG'));
    }

    public function test_nilai_kosong_dianggap_bukan_phl(): void
    {
        $this->assertFalse($this->resolver()->cocokKriteria(null, null));
        $this->assertFalse($this->resolver()->cocokKriteria(21, null));
        $this->assertFalse($this->resolver()->cocokKriteria(null, 'EMI'));
        $this->assertFalse($this->resolver()->cocokKriteria(21, ''));
    }

    public function test_cabang_dicocokkan_tanpa_peduli_besar_kecil_huruf_dan_spasi_pinggir(): void
    {
        // Data cabang diketik manusia di sistem induk; 'emi' dan ' EMI ' adalah
        // orang yang sama, dan salah menilainya berarti field atasan hilang
        // dari orang yang seharusnya mengisinya.
        $this->assertTrue($this->resolver()->cocokKriteria(21, 'emi'));
        $this->assertTrue($this->resolver()->cocokKriteria(21, ' EMI '));
    }
}
