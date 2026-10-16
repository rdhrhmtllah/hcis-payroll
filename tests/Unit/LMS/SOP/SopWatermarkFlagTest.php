<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\SopSecurePdfAccessService;
use PHPUnit\Framework\TestCase;

/**
 * SopSecurePdfAccessService::watermarkFlagOn() adalah satu-satunya baris yang
 * benar-benar bisa mematikan watermark ('T' -> false). Test feature yang ada
 * (SopWatermarkToggleTest) hanya menembak jalur fail-safe/default-ON lewat DB
 * dev read-only, jadi tidak pernah menyentuh cabang "mati" ini. Test unit ini
 * murni (tanpa DB) supaya jalur itu tetap tercakup.
 */
class SopWatermarkFlagTest extends TestCase
{
    private function svc(): SopSecurePdfAccessService
    {
        return new SopSecurePdfAccessService;
    }

    public function test_inaktif_flag_turns_watermark_off(): void
    {
        $this->assertFalse($this->svc()->watermarkFlagOn('T'));
    }

    public function test_aktif_flag_keeps_watermark_on(): void
    {
        $this->assertTrue($this->svc()->watermarkFlagOn('Y'));
    }

    public function test_null_flag_fails_safe_to_watermark_on(): void
    {
        $this->assertTrue($this->svc()->watermarkFlagOn(null));
    }
}
