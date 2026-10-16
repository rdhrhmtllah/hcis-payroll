<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\MerekamKueriUji;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /*
     * Perekaman kueri per uji. MATI secara baku — menyala hanya bila
     * DIAGNOSTIK_REKAM_UJI=true, gerbang dev terbuka, dan sesi rekaman sudah
     * dimulai. Suite yang biasa membayar satu pemeriksaan boolean per uji dan
     * tidak lebih.
     */
    use MerekamKueriUji;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMerekamKueriUji();
        // RBAC: cache statis keputusan akses hidup sepanjang proses PHPUnit —
        // tanpa reset, mode/hak satu uji bocor ke uji berikutnya.
        \App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses::lupakanCache();
        \App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses::lupakanCache();
        \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::lupakan();
        $cacheAkses = new \ReflectionProperty(\App\Models\ESS\Employee\Models\User::class, 'menuAccessCache');
        $cacheAkses->setAccessible(true);
        $cacheAkses->setValue(null, []);
        foreach (['requestScopeCache', 'requestDecisionCache'] as $prop) {
            if (property_exists(\App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::class, $prop)) {
                $r = new \ReflectionProperty(\App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::class, $prop);
                $r->setAccessible(true);
                $r->setValue(null, []);
            }
        }
    }

    protected function tearDown(): void
    {
        // Ditulis SEBELUM parent::tearDown(): parent membongkar aplikasinya,
        // dan sesudah itu container tak bisa lagi menyediakan perekamnya.
        $this->tearDownMerekamKueriUji();

        parent::tearDown();
    }
}
