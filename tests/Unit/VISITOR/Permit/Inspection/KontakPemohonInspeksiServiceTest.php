<?php

namespace Tests\Unit\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Permit\Inspection\KontakPemohonInspeksiService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Kontak yang dihubungi SHE bila pemohon/tamu terlambat atau tidak datang ke
 * jadwal inspeksi (permintaan user 29 Sep 2026).
 */
class KontakPemohonInspeksiServiceTest extends TestCase
{
    private function svc(): KontakPemohonInspeksiService
    {
        return new KontakPemohonInspeksiService;
    }

    private function pesanDari(array $kontak): string
    {
        parse_str((string) parse_url($kontak['tautan_wa'], PHP_URL_QUERY), $q);

        return (string) ($q['text'] ?? '');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_permit_karyawan_satu_kontak_pemohon_dengan_pesan_terisi(): void
    {
        Carbon::setTestNow('2026-09-29 09:00:00');
        $kontak = $this->svc()->susun(
            ['nama' => 'Ridho', 'no_hp' => '0852-6980-5413'],
            jadwal: ['plat' => 'BA 1677 HJK', 'pos' => 'Klinik SHE', 'mulai' => '2026-09-29 08:30'],
        );

        $this->assertCount(1, $kontak);
        $this->assertSame('Ridho', $kontak[0]['nama']);
        $this->assertSame('tel:+6285269805413', $kontak[0]['tautan_telepon']);
        $this->assertStringStartsWith('https://wa.me/6285269805413?text=', $kontak[0]['tautan_wa']);

        $pesan = $this->pesanDari($kontak[0]);
        foreach (['Halo Ridho', 'BA 1677 HJK', 'di Klinik SHE', 'hari ini pukul 08.30 WIB', 'dalam perjalanan'] as $isi) {
            $this->assertStringContainsString($isi, $pesan);
        }
        // Bentuk sama dengan kontak bantuan: dirender KontakBantuanDaftar.
        $this->assertSame(['nama', 'keterangan', 'no_hp', 'email', 'jam_layanan', 'tautan_wa', 'tautan_telepon', 'tautan_email'], array_keys($kontak[0]));
    }

    public function test_permit_tamu_tamu_dulu_lalu_pic_dengan_pesan_berbeda(): void
    {
        $kontak = $this->svc()->susun(
            tamu: ['nama' => 'Jaka', 'no_hp' => '+62 853 4324 4324'],
            pic: ['nama' => 'Ridho', 'no_hp' => '6285269805413'],
            jadwal: ['plat' => 'BA 1231 ASD'],
        );

        $this->assertSame(['Jaka', 'Ridho'], array_column($kontak, 'nama'));
        $this->assertStringContainsString('Tamu', $kontak[0]['keterangan']);
        $this->assertStringContainsString('PIC', $kontak[1]['keterangan']);
        $this->assertStringContainsString('untuk tamu Anda, Jaka,', $this->pesanDari($kontak[1]));
        $this->assertStringNotContainsString('tamu Anda', $this->pesanDari($kontak[0]));
    }

    public function test_tanpa_nomor_atau_nama_tidak_ada_kontak(): void
    {
        $this->assertSame([], $this->svc()->susun(['nama' => 'Ridho', 'no_hp' => '-']));
        $this->assertSame([], $this->svc()->susun(['nama' => '', 'no_hp' => '0812']));
        // Tamu tanpa nomor: PIC tetap bisa dihubungi.
        $kontak = $this->svc()->susun(tamu: ['nama' => 'Jaka', 'no_hp' => null], pic: ['nama' => 'Ridho', 'no_hp' => '0812']);
        $this->assertSame(['Ridho'], array_column($kontak, 'nama'));
    }

    public function test_dari_baris_permit_mengikuti_jenisnya(): void
    {
        $karyawan = (object) ['Permit_Untuk_Tamu' => 'T', 'Nama_Pemohon' => 'Imam', 'No_HP_Pemohon' => '0811', 'No_Plat_Kendaraan' => 'BA 1 X'];
        $this->assertSame(['Imam'], array_column($this->svc()->untukPermit($karyawan, ['pos' => 'Pos A', 'mulai' => '2026-10-05 08:00']), 'nama'));

        $tamu = (object) ['Permit_Untuk_Tamu' => 'Y', 'Nama_Pemohon_Tampil' => 'Jaka', 'No_HP_Tamu' => '0822', 'Nama_Pemohon' => 'Ridho', 'No_HP_Pemohon' => '0811', 'No_Plat_Kendaraan' => 'BA 2 Y'];
        $this->assertSame(['Jaka', 'Ridho'], array_column($this->svc()->untukPermit($tamu), 'nama'));
    }
}
