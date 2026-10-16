<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Http\Middleware\HrisApiGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteObjek;
use ReflectionMethod;
use Tests\TestCase;

/**
 * HrisApiGuard memakai satu nilai jalur untuk DUA peran: mencari whitelist dan
 * menyusun payload tanda tangan. Untuk rute statis keduanya sama, jadi cacatnya
 * tak pernah terlihat — satu-satunya konsumen guard sebelum ini (callback kuis
 * SOP) jalurnya statis.
 *
 * Begitu ada rute berparameter, route()->uri() menghasilkan POLA
 * ".../tahun/{tahun}" sedangkan klien menandatangani jalur sebenarnya
 * ".../tahun/2026". Tanda tangan tidak akan pernah cocok dan setiap permintaan
 * ditolak 401 tanpa petunjuk penyebabnya.
 *
 * Spec §7.1: docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md
 */
class HrisApiGuardJalurBertandaTanganTest extends TestCase
{
    /** @return array{pola:string, tandaTangan:string} */
    private function jalur(string $polaRute, string $jalurSebenarnya): array
    {
        $request = Request::create($jalurSebenarnya, 'GET');
        $request->setRouteResolver(fn () => new RouteObjek(['GET'], $polaRute, fn () => null));

        $metode = new ReflectionMethod(HrisApiGuard::class, 'jalurPermintaan');
        $metode->setAccessible(true);

        return $metode->invoke(new HrisApiGuard, $request);
    }

    /** @test */
    public function rute_statis_menghasilkan_jalur_tanda_tangan_yang_sama_seperti_sebelumnya(): void
    {
        // Jaring pengaman untuk konsumen guard yang sudah ada: untuk rute statis
        // kedua peran menghasilkan nilai identik, jadi perubahan ini tidak
        // mengubah apa pun bagi mereka.
        $jalur = $this->jalur(
            'api/v1/hris/third-party/sop/exam/complete',
            '/api/v1/hris/third-party/sop/exam/complete',
        );

        $this->assertSame('/api/v1/hris/third-party/sop/exam/complete', $jalur['pola']);
        $this->assertSame('/api/v1/hris/third-party/sop/exam/complete', $jalur['tandaTangan']);
        $this->assertSame($jalur['pola'], $jalur['tandaTangan']);
    }

    /** @test */
    public function rute_berparameter_menandatangani_jalur_sebenarnya_bukan_polanya(): void
    {
        $jalur = $this->jalur(
            'api/v1/hcis/kalender-operasional/tahun/{tahun}',
            '/api/v1/hcis/kalender-operasional/tahun/2026',
        );

        $this->assertSame(
            '/api/v1/hcis/kalender-operasional/tahun/2026',
            $jalur['tandaTangan'],
            'Klien menandatangani jalur yang benar-benar dipanggilnya.',
        );

        $this->assertSame(
            '/api/v1/hcis/kalender-operasional/tahun/{tahun}',
            $jalur['pola'],
            'Whitelist tetap dicocokkan ke pola, supaya satu baris registry cukup untuk semua tahun.',
        );

        $this->assertNotSame($jalur['pola'], $jalur['tandaTangan']);
    }
}
