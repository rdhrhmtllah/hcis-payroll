<?php

namespace Tests\Unit\SYSTEM\Application\Shell;

use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use PHPUnit\Framework\TestCase;

/**
 * Tingkat "bagian" di atas grup menu sidebar bersifat opsional: modul yang tak
 * mengisinya harus tampil persis seperti sebelum fitur ini ada.
 */
class SusunBagianMenuTest extends TestCase
{
    private static function grup(string $id, ?string $bagian = null, int $urut = 999, ?string $judul = null): array
    {
        return [
            'id' => $id,
            'title' => $id,
            'items' => [],
            'sectionId' => $bagian,
            'sectionTitle' => $judul ?? $bagian,
            'sectionIcon' => $bagian ? 'bi bi-graph-up' : null,
            'sortSection' => $urut,
        ];
    }

    public function test_modul_tanpa_bagian_tidak_berubah_sama_sekali(): void
    {
        $grup = [self::grup('dashboard'), self::grup('kpi'), self::grup('admin')];

        [$hasil, $bagian] = InertiaShellService::susunBagian($grup);

        $this->assertSame(['dashboard', 'kpi', 'admin'], array_column($hasil, 'id'));
        $this->assertSame([], $bagian);
    }

    public function test_grup_diurutkan_per_bagian_dengan_urutan_asal_dipertahankan_di_dalamnya(): void
    {
        $grup = [
            self::grup('pa__appraisal', 'pa', 2, 'Performance Appraisal'),
            self::grup('kpi__dashboard', 'kpi', 1, 'Key Performance Indicator'),
            self::grup('admin__kpi', 'admin', 9, 'Administrasi'),
            self::grup('kpi__individu', 'kpi', 1, 'Key Performance Indicator'),
        ];

        [$hasil, $bagian] = InertiaShellService::susunBagian($grup);

        $this->assertSame(['kpi__dashboard', 'kpi__individu', 'pa__appraisal', 'admin__kpi'], array_column($hasil, 'id'));
        $this->assertSame(['kpi', 'pa', 'admin'], array_column($bagian, 'id'));
        $this->assertSame('Key Performance Indicator', $bagian[0]['title']);
        $this->assertSame(['kpi__dashboard', 'kpi__individu'], $bagian[0]['groupIds']);
        $this->assertSame('bi bi-graph-up', $bagian[0]['icon']);
    }

    /** Menu baru di modul berbagian yang lupa diberi bagian tetap terlihat, paling atas. */
    public function test_grup_tanpa_bagian_di_modul_berbagian_tetap_tampil_paling_atas(): void
    {
        $grup = [
            self::grup('kpi__dashboard', 'kpi', 1),
            self::grup('lainnya'),
        ];

        [$hasil, $bagian] = InertiaShellService::susunBagian($grup);

        $this->assertSame(['lainnya', 'kpi__dashboard'], array_column($hasil, 'id'));
        $this->assertSame(['kpi'], array_column($bagian, 'id'));
    }
}
