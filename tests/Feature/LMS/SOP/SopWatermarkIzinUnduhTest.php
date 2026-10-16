<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Boleh unduh" dan "ber-watermark" tak bisa menyala bersamaan.
 *
 * Watermark disematkan oleh pembaca aman, halaman demi halaman. Begitu berkas
 * mentahnya boleh diunduh, pembaca itu bisa dilewati sepenuhnya — jadi
 * watermark yang menyala di sebelahnya bukan perlindungan, melainkan janji
 * yang tak ditepati siapa pun. Admin yang melihat keduanya menyala akan
 * mengira dokumennya bertanda air, padahal berkas yang diunduh bersih.
 *
 * DITEGAKKAN DI PENYIMPANAN, BUKAN HANYA DI LAYAR. Builder menonaktifkan
 * sakelarnya begitu izin unduh dipilih, tetapi layar bukan tempat aturan
 * bisnis dijaga: permintaan yang dirakit sendiri tetap sampai ke service.
 *
 * @see \App\Services\LMS\SOP\Support\SopAksesBerkas::watermarkTersimpan()
 */
class SopWatermarkIzinUnduhTest extends TestCase
{
    use DatabaseTransactions;

    private function paketBaru(): int
    {
        return DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET IZIN '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => 'TERBATAS',
        ], 'Id_SOP_Paket');
    }

    /** @return array<string, array{0: string, 1: bool, 2: string}> */
    public static function kombinasi(): array
    {
        return [
            // mode akses      diminta admin   yang WAJIB tersimpan
            'unduh + minta watermark' => ['VIEW_UNDUH', true, SopStatus::INAKTIF],
            'unduh + tanpa watermark' => ['VIEW_UNDUH', false, SopStatus::INAKTIF],
            'hanya lihat + watermark' => ['VIEW_ONLY', true, SopStatus::AKTIF],
            'hanya lihat + tanpa watermark' => ['VIEW_ONLY', false, SopStatus::INAKTIF],
        ];
    }

    /**
     * Aturannya sendiri, tanpa basis data.
     *
     * @dataProvider kombinasi
     */
    public function test_aturan_menormalkan_setiap_kombinasi(string $mode, bool $diminta, string $harap): void
    {
        $this->assertSame($harap, SopAksesBerkas::watermarkTersimpan($diminta, $mode));
    }

    /**
     * Yang benar-benar TERSIMPAN lewat item hidup.
     *
     * Tanpa uji ini, aturan di atas bisa saja benar dan tak dipakai satu pun
     * penulis — hijau yang tak menjaga apa pun.
     *
     * @dataProvider kombinasi
     */
    public function test_item_hidup_menyimpan_hasil_normalisasi(string $mode, bool $diminta, string $harap): void
    {
        $id = app(SopItemService::class)->create($this->paketBaru(), [
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item izin unduh',
            'Mode_Akses' => $mode,
            'Flag_Watermark' => $diminta,
        ], 1);

        $this->assertGreaterThan(0, $id, 'Itemnya gagal dibuat; ujinya tak menguji apa pun.');
        $this->assertSame($harap, DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $id)->value('Flag_Watermark'));
    }

    /** Dan lewat item DRAFT, yang justru dipakai Builder. */
    public function test_item_draft_menyimpan_hasil_normalisasi(): void
    {
        $idPaket = $this->paketBaru();

        $idDraft = DB::table('N_SOP_Paket_Draft')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Nama_Paket' => 'DRAFT IZIN '.substr((string) microtime(true), -6),
            'Flag_Absensi' => SopStatus::INAKTIF,
            'Status' => 'DRAFT',
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket_Draft');

        /* createItem() menolak item tanpa section yang sah — bukan cacat,
           melainkan syaratnya. Tanpa baris ini ia mengembalikan null dan
           ujinya lulus tanpa menyentuh satu pun baris. */
        $idSection = DB::table('N_SOP_Paket_Draft_Section')->insertGetId([
            'Id_SOP_Paket_Draft' => $idDraft,
            'Nama_Section' => 'Section uji',
            'Urutan' => 1,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Draft_Section');

        // Parameter pertamanya id PAKET, bukan id draft: service-nya sendiri
        // yang menemukan draft milik paket itu lewat ensure().
        $id = app(SopPaketDraftService::class)->createItem($idPaket, [
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item izin unduh draft',
            'Id_SOP_Draft_Section' => $idSection,
            'Mode_Akses' => 'VIEW_UNDUH',
            'Flag_Watermark' => true,
        ], 1);

        $this->assertNotNull($id, 'Item draftnya gagal dibuat; ujinya tak menguji apa pun.');
        $this->assertSame(SopStatus::INAKTIF, DB::table('N_SOP_Paket_Draft_Item')
            ->where('Id_SOP_Draft_Item', $id)->value('Flag_Watermark'),
            'Builder menulis lewat jalur draft. Aturan yang hanya dipasang di jalur item hidup '
            .'membiarkan justru jalur yang dipakai admin sehari-hari lolos.');
    }

    /** Menyunting item yang sudah ada tunduk pada aturan yang sama. */
    public function test_menyunting_ke_boleh_unduh_ikut_memadamkan_watermark(): void
    {
        $idPaket = $this->paketBaru();
        $svc = app(SopItemService::class);

        $id = $svc->create($idPaket, [
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Mula-mula hanya lihat',
            'Mode_Akses' => 'VIEW_ONLY',
            'Flag_Watermark' => true,
        ], 1);

        $this->assertSame(SopStatus::AKTIF, DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $id)->value('Flag_Watermark'),
            'Prasyarat: watermark harus menyala sebelum izin unduh dinyalakan.');

        $svc->update($id, [
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Kini boleh unduh',
            'Mode_Akses' => 'VIEW_UNDUH',
            'Flag_Watermark' => true,
        ], 1);

        $this->assertSame(SopStatus::INAKTIF, DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $id)->value('Flag_Watermark'),
            'Item yang diubah menjadi boleh diunduh harus ikut kehilangan watermarknya. '
            .'Aturan yang hanya berlaku saat pembuatan meninggalkan baris lama dalam keadaan '
            .'yang mustahil.');
    }
}
