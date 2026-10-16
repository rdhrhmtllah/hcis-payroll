<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopTipeItem as T;
use PHPUnit\Framework\TestCase;

/**
 * Profil tipe: blok mana yang berlaku bagi tipe item mana.
 *
 * Lahir dari pengukuran 20 Agustus 2026: 0 dari 17 item non-dokumen pernah
 * punya Nomor_SOP_Resmi, sementara 47% versi FORM punya Nomor_Pengesahan.
 * Bedanya bukan kebutuhan — layar yang satu meminta, yang lain tidak.
 */
class SopProfilTipeTest extends TestCase
{
    public function test_nomor_resmi_hanya_milik_dokumen_dan_pengesahan_wajib_hanya_untuk_dokumen(): void
    {
        $this->assertTrue(T::blokBerlaku(T::DOKUMEN, T::BLOK_NOMOR_RESMI));
        $this->assertTrue(T::blokWajib(T::DOKUMEN, T::BLOK_PENGESAHAN));

        /* FORM ikut disahkan sejak 5 Okt 2026, tetapi OPSIONAL. */
        $this->assertTrue(T::blokBerlaku(T::FORM, T::BLOK_PENGESAHAN));
        $this->assertFalse(T::blokWajib(T::FORM, T::BLOK_PENGESAHAN));
        $this->assertFalse(T::blokBerlaku(T::FORM, T::BLOK_NOMOR_RESMI));

        foreach ([T::VIDEO, T::KUIS] as $tipe) {
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_NOMOR_RESMI), $tipe);
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_PENGESAHAN), $tipe);
        }
    }

    public function test_berkas_untuk_tipe_file_dan_ujian_untuk_kuis(): void
    {
        foreach ([T::DOKUMEN, T::FORM, T::VIDEO] as $tipe) {
            $this->assertTrue(T::blokBerlaku($tipe, T::BLOK_BERKAS), $tipe);
            $this->assertFalse(T::blokBerlaku($tipe, T::BLOK_UJIAN), $tipe);
        }

        $this->assertFalse(T::blokBerlaku(T::KUIS, T::BLOK_BERKAS));
        $this->assertTrue(T::blokBerlaku(T::KUIS, T::BLOK_UJIAN));
    }

    public function test_blok_yang_berlaku_untuk_dokumen_juga_wajib(): void
    {
        $this->assertTrue(T::blokWajib(T::DOKUMEN, T::BLOK_NOMOR_RESMI));
        $this->assertTrue(T::blokWajib(T::KUIS, T::BLOK_UJIAN));
        $this->assertFalse(T::blokWajib(T::FORM, T::BLOK_PENGESAHAN));
    }

    /**
     * Masa berlaku SOP sudah dicabut lintas-lapis 15 Agustus 2026. Profil tipe
     * tidak boleh jadi pintu masuknya kembali.
     */
    public function test_profil_tidak_mengenal_masa_berlaku(): void
    {
        foreach (T::VERSIONED as $tipe) {
            $blok = array_keys(T::profil($tipe));
            $this->assertNotContains('kadaluarsa', $blok, $tipe);
            $this->assertNotContains('review_berikutnya', $blok, $tipe);
        }
    }

    /** Tipe non-versi tidak memberlakukan blok apa pun. */
    public function test_tipe_non_versi_tidak_punya_blok(): void
    {
        foreach ([T::TAUTAN, T::PARAGRAF, T::ABSENSI] as $tipe) {
            $this->assertSame([], T::profil($tipe), $tipe);
        }
    }
}
