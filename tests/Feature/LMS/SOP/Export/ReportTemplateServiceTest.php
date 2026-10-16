<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReportTemplateServiceTest extends TestCase
{
    private function svc(string $module = 'sop'): ReportTemplateService
    {
        return new ReportTemplateService(
            $module,
            app(DatasetRegistry::class),
            app(DefinisiValidator::class),
            $module === 'sop' ? SopTemplateSistem::semua() : [],
        );
    }

    private function definisiSah(): array
    {
        return [
            'nama' => 'Kepatuhan per Divisi',
            'definisi' => [
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'dimensi' => ['divisi'],
                'ukuran' => ['jml_karyawan'],
            ],
            'tingkat_bagi' => 'PRIBADI',
        ];
    }

    protected function tearDown(): void
    {
        DB::table('N_HRIS_Report_Template')->where('Kode_Karyawan_Pemilik', 'like', 'UJI%')->delete();
        parent::tearDown();
    }

    public function test_simpan_menghasilkan_kode_unik_berprefix_modul(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI001');

        $this->assertStringStartsWith('sop-', $kode);
        $this->assertDatabaseHas('N_HRIS_Report_Template', ['Module' => 'sop', 'Kode' => $kode]);
    }

    public function test_definisi_tak_sah_ditolak_saat_simpan(): void
    {
        $d = $this->definisiSah();
        $d['definisi']['ukuran'] = ['tidak_ada'];

        $this->expectException(RuntimeException::class);
        $this->svc()->simpan($d, 'UJI002');
    }

    public function test_template_modul_lain_tidak_bisa_diambil(): void
    {
        $kode = $this->svc('sop')->simpan($this->definisiSah(), 'UJI003');

        $this->expectException(RuntimeException::class);
        $this->svc('kpi')->ambil($kode, 'UJI003');
    }

    public function test_galeri_saya_hanya_memuat_milik_sendiri(): void
    {
        $this->svc()->simpan($this->definisiSah(), 'UJI004');
        $this->svc()->simpan($this->definisiSah(), 'UJI005');

        $saya = $this->svc()->galeri('UJI004')['saya'];

        $this->assertCount(1, $saya);
        $this->assertSame('UJI004', $saya[0]->Kode_Karyawan_Pemilik);
    }

    public function test_galeri_resmi_berasal_dari_kode_bukan_baris(): void
    {
        $resmi = $this->svc()->galeri('UJI006')['resmi'];

        $this->assertNotEmpty($resmi);
        $this->assertSame(0, DB::table('N_HRIS_Report_Template')->where('Tingkat_Bagi', 'SISTEM')->count());
    }

    public function test_duplikat_template_sistem_mencatat_asalnya(): void
    {
        $kodeBaru = $this->svc()->duplikat('sop-sistem-rinci', 'UJI007');
        $baris = DB::table('N_HRIS_Report_Template')->where('Kode', $kodeBaru)->first();

        $this->assertSame('sop-sistem-rinci', $baris->Kode_Template_Asal);
        $this->assertSame('UJI007', $baris->Kode_Karyawan_Pemilik);
    }

    public function test_nonaktifkan_tidak_menghapus_baris(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI008');
        $this->svc()->nonaktifkan($kode, 'UJI008');

        $this->assertDatabaseHas('N_HRIS_Report_Template', ['Kode' => $kode, 'Flag_Aktif' => 'T']);
    }

    public function test_orang_lain_tidak_bisa_menonaktifkan(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI009');

        $this->expectException(RuntimeException::class);
        $this->svc()->nonaktifkan($kode, 'UJI010');
    }

    /**
     * Penjaga celah akses: ambil() dipakai pratinjau, jalankan, dan duplikat.
     * Tanpa lingkup pemilik di dalamnya, siapa pun yang bisa membuka Studio
     * ikut membaca template PRIBADI orang lain berikut nilai saringannya.
     */
    public function test_template_pribadi_orang_lain_tidak_bisa_diambil(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI011');

        $this->expectException(RuntimeException::class);
        $this->svc()->ambil($kode, 'UJI012');
    }

    public function test_template_dibagikan_tetap_bisa_diambil_orang_lain(): void
    {
        $d = $this->definisiSah();
        $d['tingkat_bagi'] = 'DIBAGIKAN';
        $kode = $this->svc()->simpan($d, 'UJI013');

        $this->assertSame($kode, $this->svc()->ambil($kode, 'UJI014')['kode']);
    }

    public function test_duplikat_template_pribadi_orang_lain_ditolak(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI015');

        $this->expectException(RuntimeException::class);
        $this->svc()->duplikat($kode, 'UJI016');
    }

    public function test_template_sistem_tetap_terbuka_untuk_semua(): void
    {
        $this->assertTrue($this->svc()->ambil('sop-sistem-rinci', 'UJI017')['sistem']);
    }

    public function test_nonaktifkan_dua_kali_ditolak(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI018');
        $this->svc()->nonaktifkan($kode, 'UJI018');

        $this->expectException(RuntimeException::class);
        $this->svc()->nonaktifkan($kode, 'UJI018');
    }

    public function test_versi_definisi_ikut_tersimpan(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI019');

        $this->assertSame(1, (int) DB::table('N_HRIS_Report_Template')
            ->where('Kode', $kode)->value('Versi_Definisi'));
    }
}
