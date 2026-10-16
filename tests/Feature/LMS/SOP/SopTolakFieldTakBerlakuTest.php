<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Konsumen ketiga profil tipe: server.
 *
 * Menyembunyikan field di UI saja tidak cukup. Builder, impor, dan endpoint
 * yang belum ada tetap bisa menuliskan pengesahan ke sebuah FORM — dan persis
 * begitulah 47% versi FORM mendapat nomor pengesahan yang tak pernah
 * dibutuhkan.
 *
 * BACA-SAJA: penjaga melempar sebelum menyentuh apa pun.
 */
class SopTolakFieldTakBerlakuTest extends TestCase
{
    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function periksa(string $tipe, array $data): void
    {
        $m = new \ReflectionMethod($this->svc(), 'pastikanFieldSesuaiTipe');
        $m->setAccessible(true);
        $m->invoke($this->svc(), $tipe, $data);
    }

    public function test_form_menolak_nomor_pengesahan(): void
    {
        $this->expectException(\DomainException::class);
        $this->periksa(SopTipeItem::FORM, ['Nomor_Pengesahan' => 'SK-001/2026']);
    }

    /**
     * Builder meneruskan blok tanda tangan sebagai ARRAY `Pengesah`, bukan
     * kolom skalar. Penjaga yang hanya mengenal kolom skalar meninggalkan pintu
     * terbuka lebar-lebar untuk jalur "buat dokumen baru" di Builder.
     */
    public function test_form_menolak_blok_pengesah_dari_builder(): void
    {
        $this->expectException(\DomainException::class);
        $this->periksa(SopTipeItem::FORM, [
            'Pengesah' => [['Peran' => 'Disetujui', 'Nama' => 'Manager HSE']],
        ]);
    }

    public function test_form_menerima_blok_pengesah_kosong(): void
    {
        $this->periksa(SopTipeItem::FORM, ['Pengesah' => []]);
        $this->addToAssertionCount(1);
    }

    public function test_kuis_menolak_nomor_sop_resmi(): void
    {
        $this->expectException(\DomainException::class);
        $this->periksa(SopTipeItem::KUIS, ['Nomor_SOP_Resmi' => 'SOP-01']);
    }

    public function test_dokumen_menerima_keduanya(): void
    {
        $this->periksa(SopTipeItem::DOKUMEN, [
            'Nomor_Pengesahan' => 'SK-001/2026',
            'Nomor_SOP_Resmi' => 'SOP-01',
        ]);
        $this->addToAssertionCount(1);
    }

    /**
     * Nilai kosong bukan pelanggaran: payload lama yang masih mengirim kunci
     * kosong tidak boleh ditolak. Yang dilarang MENGISInya, bukan menyebutnya.
     */
    public function test_field_kosong_tidak_dianggap_pelanggaran(): void
    {
        $this->periksa(SopTipeItem::FORM, ['Nomor_Pengesahan' => null, 'Disahkan_Oleh' => '']);
        $this->addToAssertionCount(1);
    }

    /**
     * Membuktikan penjaganya BENAR-BENAR DIPASANG, bukan sekadar ada.
     * Uji yang hanya memanggil helper privat tidak membuktikan ia terpakai.
     */
    public function test_ubah_versi_form_menolak_payload_pengesahan(): void
    {
        $idVersi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('d.Tipe', SopTipeItem::FORM)
            ->where('v.Status', 'DRAFT')
            ->value('v.Id_SOP_Item_Versi');

        $this->assertNotNull($idVersi, 'Tidak ada versi FORM berstatus DRAFT untuk diuji.');

        $this->expectException(\DomainException::class);
        $this->svc()->ubahVersi((int) $idVersi, ['Nomor_Pengesahan' => 'SK-999/2026'], 1);
    }
}
