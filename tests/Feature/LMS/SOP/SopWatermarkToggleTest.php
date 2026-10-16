<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopSecurePdfAccessService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Watermark selalu aktif secara default, dan HANYA boleh mati bila item yang
 * dibuka (bukan sekadar file-nya) eksplisit mengizinkannya. Kelas ini
 * read-only terhadap DB dev, mengikuti konvensi test SOP lain — tidak ada
 * baris yang ditulis/diubah.
 */
class SopWatermarkToggleTest extends TestCase
{
    private function svc(): SopSecurePdfAccessService
    {
        return app(SopSecurePdfAccessService::class);
    }

    /** @return array{0:int,1:int} [idItem, realBerkasId] item DOKUMEN aktif nyata dengan versi & berkas. */
    private function itemDokumenNyata(): array
    {
        $row = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Tipe_Item', 'DOKUMEN')
            ->first(['i.Id_SOP_Paket_Item', 'b.Id_Master_Konten_Berkas']);

        $this->assertNotNull($row, 'Butuh minimal satu item DOKUMEN aktif dengan versi & berkas di DB.');

        return [(int) $row->Id_SOP_Paket_Item, (int) $row->Id_Master_Konten_Berkas];
    }

    /**
     * Kode karyawan fiktif (bukan baris nyata). Sengaja dipakai untuk semua
     * kasus di bawah: ketiganya menegaskan watermark tetap ON, dan itu benar
     * baik lewat cabang "flag ON dan berwenang" maupun cabang fail-safe
     * (item/berkas tak cocok, atau tak berwenang) — otorisasi nyata per
     * karyawan diuji terpisah di tests/Unit/SOP/SopWatermarkFlagTest.php
     * untuk bagian yang tak butuh DB.
     */
    private function kodeDummy(): string
    {
        return 'TEST-WM-DUMMY';
    }

    public function test_default_column_value_keeps_watermark_on(): void
    {
        [$idItem, $realBerkasId] = $this->itemDokumenNyata();

        $this->assertTrue($this->svc()->watermarkAktif($this->kodeDummy(), $realBerkasId, $idItem));
    }

    public function test_missing_item_context_fails_safe_to_watermark_on(): void
    {
        [, $realBerkasId] = $this->itemDokumenNyata();

        $this->assertTrue($this->svc()->watermarkAktif($this->kodeDummy(), $realBerkasId, null));
    }

    public function test_item_and_berkas_mismatch_fails_safe_to_watermark_on(): void
    {
        [$idItem] = $this->itemDokumenNyata();

        // Berkas id yang hampir pasti tidak ada / tidak berkaitan dengan $idItem.
        $this->assertTrue($this->svc()->watermarkAktif($this->kodeDummy(), 999999999, $idItem));
    }
}
