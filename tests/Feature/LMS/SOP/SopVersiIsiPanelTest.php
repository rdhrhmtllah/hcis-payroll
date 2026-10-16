<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\SopVersiLampiranService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panel versi Builder menyebut ISI tiap versi (10 Okt 2026): jenis & ukuran
 * berkas utama, dan form kosong yang terpasang pada FORM.
 *
 * Panelnya punya dua sumber — versi hidup (SopItemVersionService::byItem) dan
 * overlay draft paket (SopPaketDraftService::versions). Keduanya memakai
 * SopVersiLampiranService::lengkapiIsi(); uji di sini memastikan helpernya
 * benar DAN kedua cabang benar-benar memanggilnya.
 */
class SopVersiIsiPanelTest extends TestCase
{
    use DatabaseTransactions;

    private function kontenPdf(): int
    {
        $id = DB::table('N_LMS_Master_Konten as k')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            ->where('k.Tipe_Konten', 'document')
            ->where(fn ($w) => $w->where('b.Type_File', 'like', '%pdf%')->orWhere('b.File_Path', 'like', '%.pdf'))
            ->orderBy('k.Id_Master_Konten')
            ->value('k.Id_Master_Konten');

        if (! $id) {
            $this->markTestSkipped('Library Konten tak punya berkas PDF.');
        }

        return (int) $id;
    }

    public function test_helper_menyebut_jenis_berkas_utama_dan_form_kosong(): void
    {
        $lampiranSvc = app(SopVersiLampiranService::class);
        if (! $lampiranSvc->tersedia()) {
            $this->markTestSkipped('Tabel lampiran (DDL-2026-158) belum ada di database ini.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'UJI ISI PANEL '.substr((string) microtime(true), -5), 'Tipe' => 'FORM'], 1);
        $idVersi = (int) $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);
        $konten = $this->kontenPdf();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update(['Id_Master_Konten' => $konten]);
        $lampiranSvc->simpan($idVersi, [['Id_Master_Konten' => $konten, 'Urutan' => 0]], 1);

        $baris = (object) ['Id_SOP_Item_Versi' => $idVersi, 'Id_Master_Konten' => $konten];
        $lampiranSvc->lengkapiIsi([$baris]);

        $this->assertSame('PDF', $baris->Konten_Ext, 'Jenis berkas utama tak disebut.');
        $this->assertCount(1, $baris->Lampiran, 'Form kosong yang terpasang tak ikut.');
        $this->assertSame(['Nama', 'Ext', 'Ukuran'], array_keys($baris->Lampiran[0]));
        $this->assertSame('PDF', $baris->Lampiran[0]['Ext']);
    }

    public function test_versi_tanpa_berkas_tetap_berbentuk_lengkap(): void
    {
        // Versi draft yang belum memilih berkas: kuncinya tetap ada, nilainya kosong —
        // layar tak perlu menebak antara "tak ada" dan "tak dikirim".
        $baris = (object) ['Id_SOP_Item_Versi' => 0, 'Id_Master_Konten' => null];

        app(SopVersiLampiranService::class)->lengkapiIsi([$baris]);

        $this->assertNull($baris->Konten_Ext);
        $this->assertNull($baris->Ukuran_File);
        $this->assertSame([], $baris->Lampiran);
    }

    public function test_cabang_versi_hidup_mengirim_isi(): void
    {
        $idItem = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', 'FORM')->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_SOP_Paket_Item');

        if (! $idItem) {
            $this->markTestSkipped('Tak ada item FORM aktif.');
        }

        $rows = app(SopItemVersionService::class)->byItem((int) $idItem);
        $this->assertNotEmpty($rows, 'Item FORM tanpa satu pun versi — fixture tak mewakili.');

        foreach ($rows as $v) {
            $this->assertTrue(property_exists($v, 'Konten_Ext'), 'byItem() tak mengirim Konten_Ext.');
            $this->assertTrue(property_exists($v, 'Lampiran'), 'byItem() tak mengirim Lampiran.');
        }
    }

    public function test_cabang_overlay_draft_mengirim_isi(): void
    {
        $idDraftItem = DB::table('N_SOP_Paket_Draft_Item')
            ->where('Tipe_Item', 'FORM')->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_SOP_Draft_Item');

        if (! $idDraftItem) {
            $this->markTestSkipped('Tak ada item FORM di draft paket.');
        }

        $rows = app(SopPaketDraftService::class)->versions((int) $idDraftItem, true);
        if ($rows->isEmpty()) {
            $this->markTestSkipped('Item FORM draft belum punya versi terbit.');
        }

        foreach ($rows as $v) {
            $this->assertTrue(property_exists($v, 'Konten_Ext'), 'versions() tak mengirim Konten_Ext.');
            $this->assertTrue(property_exists($v, 'Lampiran'), 'versions() tak mengirim Lampiran.');
        }
    }
}
