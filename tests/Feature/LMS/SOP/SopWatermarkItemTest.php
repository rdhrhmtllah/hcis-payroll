<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopSecurePdfAccessService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setelan watermark per item harus benar-benar sampai ke pembaca.
 *
 * Admin mematikan watermark di Builder (`Flag_Watermark = 'T'`), lalu karyawan
 * membuka dokumennya dan watermark tetap terbakar di setiap halaman. Setelannya
 * tersimpan benar; yang tak pernah terbaca.
 *
 * SEBABNYA, dan kenapa uji ini memakai versi milik DOKUMEN. `watermarkAktif()`
 * dulu mencari itemnya lewat `N_SOP_Item_Versi.Id_SOP_Paket_Item`. Sejak versi
 * SOP menjadi milik dokumen, kolom itu tak pernah lagi diisi — NOL dari seluruh
 * baris. Join-nya karena itu tak pernah cocok, jatuh ke gerbang "default aman",
 * dan mengembalikan "watermark menyala" untuk SETIAP dokumen, apa pun
 * setelannya. Bukan kadang-kadang: selalu.
 *
 * Karena itu fikstur di sini sengaja meniru kenyataan — `Id_SOP_Paket_Item`
 * dibiarkan NULL dan versinya diikat lewat `Id_SOP_Dokumen`. Fikstur yang
 * mengisi kolom lama akan hijau di atas kode yang rusak.
 *
 * SISI SEBALIKNYA IKUT DIJAGA. Gerbang "default aman" itu ada karena alasan
 * nyata: tanpa kecocokan eksplisit item+berkas, sebuah id item asing bisa
 * dipakai memadamkan watermark dokumen lain. Memperbaiki cacat di atas dengan
 * melonggarkan gerbangnya berarti menukar satu cacat dengan lubang keamanan,
 * jadi uji ketiga menuntut gerbang itu tetap menutup.
 *
 * @see \App\Services\LMS\SOP\SopSecurePdfAccessService::watermarkAktif()
 */
class SopWatermarkItemTest extends TestCase
{
    use DatabaseTransactions;

    private const KODE = 'UJI001';

    /** Sebuah berkas konten yang sudah ada — dipinjam, bukan dibuat. */
    private function berkasAda(): object
    {
        $b = DB::table('N_LMS_Master_Konten_Berkas')
            ->whereNotNull('Id_Master_Konten')
            ->first(['Id_Master_Konten_Berkas', 'Id_Master_Konten']);

        if (! $b) {
            $this->markTestSkipped('Tak ada satu pun berkas konten di basis data ini.');
        }

        return $b;
    }

    /**
     * Paket PUBLIK terbit + item + versi MILIK DOKUMEN.
     *
     * Paketnya publik supaya otorisasi lolos lewat jalur publik dan uji ini
     * tidak ikut menguji penugasan — yang diuji hanya pembacaan setelan.
     *
     * @return array{item:int, berkas:int}
     */
    private function siapkan(string $flagWatermark): array
    {
        $berkas = $this->berkasAda();

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET WM '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => 'PUBLIK',
        ], 'Id_SOP_Paket');

        $idDokumen = DB::table('N_SOP_Dokumen')->insertGetId([
            'Judul' => 'DOK WM '.substr((string) microtime(true), -6),
            'Tipe' => 'DOKUMEN',
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Dokumen');

        /* Id_SOP_Paket_Item SENGAJA TIDAK DIISI — persis seperti seluruh baris
           nyata sejak versi menjadi milik dokumen. */
        $idVersi = DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Dokumen' => $idDokumen,
            'Id_Master_Konten' => $berkas->Id_Master_Konten,
            'Versi_Ke' => 1,
            'Status' => 'PUBLISHED',
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Item_Versi');

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item WM',
            'Id_SOP_Dokumen' => $idDokumen,
            'Id_Item_Versi_Aktif' => $idVersi,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Watermark' => $flagWatermark,
        ], 'Id_SOP_Paket_Item');

        return ['item' => $idItem, 'berkas' => (int) $berkas->Id_Master_Konten_Berkas];
    }

    private function svc(): SopSecurePdfAccessService
    {
        return app(SopSecurePdfAccessService::class);
    }

    /** Inilah cacatnya: setelan admin tak pernah terbaca. */
    public function test_watermark_dimatikan_benar_benar_mati(): void
    {
        ['item' => $item, 'berkas' => $berkas] = $this->siapkan(SopStatus::INAKTIF);

        $this->assertFalse(
            $this->svc()->watermarkAktif(self::KODE, $berkas, $item),
            'Admin mematikan watermark pada item ini, tetapi pembaca tetap menyalakannya. '
            .'Setelan yang tak pernah terbaca lebih buruk daripada setelan yang tak ada — '
            .'admin percaya ia sudah berlaku.'
        );
    }

    /** Sisi normal: dinyalakan tetap menyala. */
    public function test_watermark_dinyalakan_tetap_menyala(): void
    {
        ['item' => $item, 'berkas' => $berkas] = $this->siapkan(SopStatus::AKTIF);

        $this->assertTrue(
            $this->svc()->watermarkAktif(self::KODE, $berkas, $item),
            'Watermark yang dinyalakan admin harus menyala.'
        );
    }

    /**
     * KEAMANAN: id item yang tak memiliki berkas itu tak boleh memadamkannya.
     *
     * Tanpa penjaga ini, memperbaiki uji pertama dengan melonggarkan join akan
     * membuat siapa pun bisa menyebut id item ber-watermark-mati miliknya
     * sendiri untuk membuka dokumen lain tanpa tanda air.
     */
    public function test_item_asing_tidak_bisa_memadamkan_watermark_berkas_lain(): void
    {
        ['item' => $itemMati] = $this->siapkan(SopStatus::INAKTIF);

        /* Berkas milik konten LAIN — bukan yang dirujuk versi item di atas. */
        $lain = DB::table('N_LMS_Master_Konten_Berkas')
            ->whereNotNull('Id_Master_Konten')
            ->where('Id_Master_Konten', '<>', DB::table('N_SOP_Item_Versi as v')
                ->join('N_SOP_Paket_Item as i', 'i.Id_Item_Versi_Aktif', '=', 'v.Id_SOP_Item_Versi')
                ->where('i.Id_SOP_Paket_Item', $itemMati)
                ->value('v.Id_Master_Konten'))
            ->value('Id_Master_Konten_Berkas');

        if (! $lain) {
            $this->markTestSkipped('Butuh minimal dua konten berbeda untuk menguji pemalsuan id item.');
        }

        $this->assertTrue(
            $this->svc()->watermarkAktif(self::KODE, (int) $lain, $itemMati),
            'Id item yang tidak memiliki berkas ini berhasil memadamkan watermarknya. '
            .'Gerbang default-aman harus menutup ketika item dan berkas tak cocok.'
        );
    }
}
