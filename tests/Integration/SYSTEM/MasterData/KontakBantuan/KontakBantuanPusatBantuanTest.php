<?php

namespace Tests\Integration\SYSTEM\MasterData\KontakBantuan;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kontak bantuan di Pusat Bantuan: satu kelompok per modul · fitur, kelompok
 * modul halaman aktif di atas, dan cadangan PIC lama hanya untuk kelompok yang
 * belum punya baris di tabel (keputusan user 29 Sep 2026).
 */
class KontakBantuanPusatBantuanTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KontakBantuanService
    {
        return app(KontakBantuanService::class);
    }

    private function kontak(string $grup, string $fitur, string $nama, string $aktif = 'Y', int $urutan = 0): void
    {
        DB::table(KontakBantuanService::TABEL)->insert([
            'Kode_Grup_Fitur' => $grup, 'Kode_Fitur' => $fitur, 'Nama_Kontak' => $nama,
            'Email' => strtolower(str_replace(' ', '.', $nama)).'@contoh.id',
            'Urutan_Tampil' => $urutan, 'Flag_Aktif' => $aktif, 'Created_By' => 'UJI',
        ]);
    }

    /** Halaman nyata milik modul tertentu. */
    private function halamanModul(string $kode): ?string
    {
        return DB::table('N_HRIS_Grup_Fitur_Map as m')
            ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'm.Id_Grup_Fitur')
            ->where('g.Kode_Grup_Fitur', $kode)->where('m.Flag_Aktif', 'Y')
            ->value('m.Jenis_page');
    }

    private function kosongkan(): void
    {
        DB::table(KontakBantuanService::TABEL)->delete(); // dibatalkan lagi oleh DatabaseTransactions
    }

    public function test_satu_kelompok_per_modul_dengan_bagian_per_fitur(): void
    {
        $this->kosongkan();
        $this->kontak('VISITOR', 'PERMIT', 'Admin Permit B', 'Y', 2);
        $this->kontak('VISITOR', 'PERMIT', 'Admin Permit A', 'Y', 1);
        $this->kontak('VISITOR', 'PERMIT', 'Admin Permit Nonaktif', 'T');
        $this->kontak('VISITOR', 'TAMU', 'Resepsionis');
        $this->kontak('VISITOR', 'UMUM', 'Helpdesk VMS');

        $kelompok = collect($this->svc()->kelompokPusatBantuan())->keyBy('kunci');

        $vms = $kelompok['visitor'];
        $this->assertSame('Bantuan VMS', $vms['judul']);
        $this->assertSame(4, $vms['jumlah'], 'Kontak nonaktif ikut dihitung.');
        $this->assertSame(['Permit', 'Tamu'], $vms['fitur']);
        // Umum dulu, lalu fitur urut abjad.
        $this->assertSame(['Umum', 'Permit', 'Tamu'], array_column($vms['bagian'], 'label'));
        $this->assertSame(['Admin Permit A', 'Admin Permit B'], array_column($vms['bagian'][1]['kontak'], 'nama'));
        $this->assertStringStartsWith('mailto:', $vms['bagian'][0]['kontak'][0]['tautan_email']);

        // PIC lama (belum ada di tabel) tetap tampil sebagai cadangan, per modul.
        $this->assertSame('Bantuan HCIS', $kelompok['ess']['judul']);
        $this->assertSame('Bantuan IT HCIS', $kelompok['system']['judul']);
        $this->assertSame([], $kelompok['system']['fitur'], 'IT adalah bantuan umum modul IT HCIS, bukan fitur.');
        $this->assertSame([], $kelompok['ess']['fitur']);
        $this->assertTrue($kelompok->has('kpi') && $kelompok->has('lms'));
    }

    public function test_modul_halaman_aktif_paling_atas(): void
    {
        $halaman = $this->halamanModul('VISITOR');
        if (! $halaman) {
            $this->markTestSkipped('Tidak ada halaman VISITOR di peta grup fitur.');
        }
        $this->kosongkan();
        $this->kontak('VISITOR', 'PERMIT', 'Admin Permit');

        $kelompok = $this->svc()->kelompokPusatBantuan($halaman);

        $this->assertSame('visitor', $kelompok[0]['kunci']);
        $this->assertTrue($kelompok[0]['relevan']);
        $this->assertSame([false], array_values(array_unique(array_column(array_slice($kelompok, 1), 'relevan'))));

        // Tanpa halaman: tak ada yang relevan, urut modul (ESS dulu).
        $this->assertSame('ess', $this->svc()->kelompokPusatBantuan()[0]['kunci']);
    }

    public function test_cadangan_tidak_menghidupkan_kelompok_yang_dimatikan_admin(): void
    {
        $this->kosongkan();
        $this->kontak('ESS', 'UMUM', 'PIC HCIS Lama', 'T');
        $this->kontak('KPI', 'UMUM', 'PIC KPI Baru');

        $kelompok = collect($this->svc()->kelompokPusatBantuan())->keyBy('kunci');

        $this->assertFalse($kelompok->has('ess'), 'Modul yang semua kontaknya dinonaktifkan hidup lagi lewat cadangan.');
        $this->assertSame(['PIC KPI Baru'], array_column($kelompok['kpi']['bagian'][0]['kontak'], 'nama'), 'Cadangan menggandakan kontak yang sudah ada di tabel.');
    }

    public function test_endpoint_butuh_login_dan_mengabaikan_nama_halaman_aneh(): void
    {
        $this->getJson('/bantuan/kontak')->assertUnauthorized();

        $pengguna = User::query()->where('Flag_Active', 'Y')->first();
        $this->actingAs($pengguna)->getJson('/bantuan/kontak?halaman='.rawurlencode("x'; drop--"))
            ->assertOk()
            ->assertJsonStructure(['result' => [['kunci', 'judul', 'subjudul', 'relevan', 'jumlah', 'fitur', 'bagian' => [['kode_fitur', 'label', 'jumlah', 'kontak']]]]]);
    }
}
