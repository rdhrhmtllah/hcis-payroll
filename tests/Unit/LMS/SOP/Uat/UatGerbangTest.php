<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatGerbang;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Gerbang sengaja: otomasi yang menulis ke database dev tidak boleh bisa
 * terpicu oleh `php artisan test` biasa, oleh IDE yang menjalankan seluruh
 * folder tests, atau oleh CI yang tidak tahu apa-apa tentang UAT.
 *
 * Berkas terpisah bukan kerapian. Kelas uji kedua yang menumpang di berkas
 * milik kelas lain TIDAK DIJALANKAN PHPUnit — ia hanya memuat kelas yang
 * namanya cocok dengan nama berkas. Uji yang tak pernah berjalan tetap
 * menghasilkan laporan hijau, dan hijau yang tak membuktikan apa pun lebih
 * buruk daripada merah.
 */
class UatGerbangTest extends TestCase
{
    private ?string $semula;

    protected function setUp(): void
    {
        parent::setUp();
        $v = getenv(UatGerbang::PENANDA);
        $this->semula = $v === false ? null : (string) $v;
    }

    protected function tearDown(): void
    {
        $this->setel($this->semula);
        parent::tearDown();
    }

    private function setel(?string $nilai): void
    {
        if ($nilai === null) {
            putenv(UatGerbang::PENANDA);
            unset($_ENV[UatGerbang::PENANDA], $_SERVER[UatGerbang::PENANDA]);

            return;
        }

        putenv(UatGerbang::PENANDA.'='.$nilai);
        $_ENV[UatGerbang::PENANDA] = $nilai;
        $_SERVER[UatGerbang::PENANDA] = $nilai;
    }

    public function test_tertutup_bila_penanda_tidak_disetel(): void
    {
        $this->setel(null);

        $this->assertFalse(UatGerbang::terbuka());
    }

    /**
     * Menerima "true"/"yes"/"on" terasa ramah, tapi keramahan itu memperbesar
     * peluang gerbang terbuka karena kebetulan — dan gerbang yang terbuka
     * karena kebetulan bukan gerbang.
     */
    public function test_terbuka_hanya_untuk_nilai_satu_yang_tegas(): void
    {
        foreach (['0', '', 'ya', 'true', 'YES', 'on', '2', ' 1'] as $nilai) {
            $this->setel($nilai);
            $this->assertFalse(UatGerbang::terbuka(),
                "Nilai '{$nilai}' seharusnya tidak membuka gerbang.");
        }

        $this->setel('1');
        $this->assertTrue(UatGerbang::terbuka());
    }

    public function test_pastikan_terbuka_menyebut_cara_membukanya(): void
    {
        $this->setel(null);

        try {
            UatGerbang::pastikanTerbuka();
            $this->fail('Seharusnya melempar saat gerbang tertutup.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(UatGerbang::PENANDA, $e->getMessage(),
                'Pesan harus menyebut penandanya, kalau tidak yang membacanya '
                .'akan menebak-nebak atau menyerah.');
        }
    }

    public function test_pastikan_terbuka_lewat_tanpa_melempar_saat_gerbang_terbuka(): void
    {
        $this->setel('1');

        UatGerbang::pastikanTerbuka();
        $this->assertTrue(true, 'Tidak melempar saat gerbang terbuka.');
    }
}
