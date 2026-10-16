<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatBebanFase;
use PHPUnit\Framework\TestCase;

class UatBebanFaseTest extends TestCase
{
    public function test_delta_wait_menghitung_selisih_per_jenis(): void
    {
        $d = UatBebanFase::deltaWait(
            ['LCK_M_S' => 1000.0, 'LCK_M_X' => 50.0],
            ['LCK_M_S' => 1750.0, 'LCK_M_X' => 50.0],
        );

        $this->assertSame(750.0, $d['LCK_M_S']);
        $this->assertSame(0.0, $d['LCK_M_X']);
    }

    public function test_jenis_wait_yang_baru_muncul_dihitung_penuh(): void
    {
        $d = UatBebanFase::deltaWait([], ['LCK_M_U' => 300.0]);

        $this->assertSame(300.0, $d['LCK_M_U'],
            'Jenis wait yang belum ada di potret awal harus terhitung penuh, bukan hilang.');
    }

    public function test_penurunan_dijepit_ke_nol(): void
    {
        $d = UatBebanFase::deltaWait(['LCK_M_S' => 500.0], ['LCK_M_S' => 100.0]);

        $this->assertSame(0.0, $d['LCK_M_S'],
            'dm_os_wait_stats bisa ter-reset di tengah; nilai negatif akan terbaca sebagai '
            .'"beban justru menurunkan penantian" — mustahil, dan menyesatkan ke arah yang '
            .'menenangkan.');
    }
}
