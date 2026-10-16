<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\TestCase;

/**
 * UAT-SOP-038 & UAT-SOP-039 — kepatuhan tim, dan batas lingkupnya.
 *
 * 039 adalah skenario KONTROL, dan yang dijaganya bukan sekadar kerapian:
 * data kepatuhan SOP menyatakan siapa yang lalai membaca aturan kerja. Terbuka
 * lintas tim, ia menjadi bahan penilaian orang oleh atasan yang bukan atasannya.
 *
 * Test ini MEMBACA data yang sudah ada, tidak membuat penugasan baru. Menyemai
 * tim palsu berarti menulis ke struktur organisasi — jauh di luar wilayah SOP,
 * dan tepat jenis penulisan yang kontrak keselamatan otomasi UAT larang.
 */
class Kontrol039LingkupTimLeaderTest extends TestCase
{
    use DatabaseTransactions;

    private function leaderAtauSkip(): string
    {
        $leader = UatAkun::leader(2);
        if (! $leader) {
            $this->markTestSkipped(
                'Tidak ada leader dengan minimal dua bawahan pada HRIS_Karyawan_Team. '
                .'Skenario 038/039 butuh tim yang benar-benar berisi: dengan satu anggota, '
                .'"leader hanya melihat anggotanya" tak bisa dibedakan dari kebetulan.'
            );
        }

        return $leader;
    }

    #[Group('UAT-SOP-038')]
    public function test_leader_melihat_seluruh_anggota_timnya(): void
    {
        $leader = $this->leaderAtauSkip();
        $harusnya = UatAkun::anggotaTim($leader);

        $ringkasan = app(SopTeamService::class)->ringkasan($leader);

        $this->assertTrue($ringkasan['is_leader'],
            'Leader dengan bawahan seharusnya dikenali sebagai leader.');

        $tampil = array_column($ringkasan['anggota'], 'Kode_Karyawan');

        $this->assertSame([], array_diff($harusnya, $tampil),
            'Ada anggota tim yang tidak muncul pada ringkasan kepatuhan. Anggota yang '
            .'hilang dari layar adalah anggota yang tak pernah diingatkan.');
        $this->assertSame(count($harusnya), $ringkasan['kpi']['anggota']);
    }

    #[Group('UAT-SOP-039')]
    public function test_leader_hanya_melihat_anggotanya_sendiri(): void
    {
        $leader = $this->leaderAtauSkip();
        $anggota = UatAkun::anggotaTim($leader);

        $ringkasan = app(SopTeamService::class)->ringkasan($leader);
        $tampil = array_column($ringkasan['anggota'], 'Kode_Karyawan');

        $this->assertSame([], array_diff($tampil, $anggota),
            'Ringkasan memuat orang yang bukan anggota tim leader ini.');
    }

    #[Group('UAT-SOP-039')]
    public function test_menelusuri_orang_di_luar_tim_ditolak(): void
    {
        $leader = $this->leaderAtauSkip();
        $luar = UatAkun::luarTim($leader);

        if (! $luar) {
            $this->markTestSkipped(
                'Tidak ditemukan karyawan bertugas dokumen di luar tim leader ini, '
                .'sehingga batas lingkupnya tak bisa dibuktikan.'
            );
        }

        $svc = app(SopTeamService::class);

        $this->assertNull($svc->anggota($leader, $luar),
            "Leader dapat menelusuri {$luar} yang bukan anggota timnya.");

        /* Sisi positifnya diuji di baris yang sama supaya penolakan tidak lulus
           hanya karena metodenya menolak SEMUA ORANG — cacat yang akan membuat
           halaman Kepatuhan Tim kosong bagi setiap leader, dan lulus uji
           penolakan dengan sempurna. */
        $this->assertNotNull($svc->anggota($leader, $anggota = UatAkun::anggotaTim($leader)[0]),
            "Leader justru tidak bisa menelusuri {$anggota}, yang merupakan anggotanya sendiri.");
    }
}
