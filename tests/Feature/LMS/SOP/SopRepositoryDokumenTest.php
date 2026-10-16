<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perpustakaan diproyeksikan per dokumen (`katalogDokumen`).
 *
 * SATU HAL YANG DIJAGA DI ATAS SEGALANYA: dokumen TIDAK punya gerbang sendiri.
 * Ia muncul di sini semata-mata karena ada paket PUBLIK yang memuatnya — sama
 * seperti daftar Dokumen di SOP Saya muncul karena ada penugasan. Setiap uji di
 * bawah pada dasarnya menanyakan pertanyaan yang sama dari sisi berbeda:
 * "apakah keterlihatan dokumen benar-benar mengikuti paketnya?"
 *
 * Pengukuran dilakukan RELATIF terhadap keadaan sebelum fixture dibuat. Basis
 * data pengembangan berisi paket milik pekerjaan lain, dan menuntut angka
 * mutlak membuat uji ini merah karena hal yang tak ada hubungannya.
 */
class SopRepositoryDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopRepositoryReadService
    {
        return app(SopRepositoryReadService::class);
    }

    private function judulTampil(): array
    {
        return array_column($this->svc()->katalogDokumen(), 'Judul');
    }

    private function baris(string $judul): ?array
    {
        foreach ($this->svc()->katalogDokumen() as $r) {
            if ($r['Judul'] === $judul) {
                return $r;
            }
        }

        return null;
    }

    private function tanda(): string
    {
        return substr((string) microtime(true), -6);
    }

    private function buatDokumen(array $ubah = []): int
    {
        return (int) DB::table('N_SOP_Dokumen')->insertGetId(array_merge([
            'Judul' => 'UJI PUSTAKA '.$this->tanda(),
            'Tipe' => 'DOKUMEN',
            'Flag_Aktif' => 'Y',
            'Created_By' => 1,
            'Created_At' => now(),
        ], $ubah), 'Id_SOP_Dokumen');
    }

    private function buatPaket(array $ubah = []): int
    {
        return (int) DB::table('N_SOP_Master_Paket')->insertGetId(array_merge([
            'Nama_Paket' => 'UJI PAKET '.$this->tanda(),
            'Status' => 'PUBLISHED',
            'Flag_Aktif' => 'Y',
            'Mode_Distribusi' => 'PUBLIK',
            'Mode_Urutan_Item' => 'BEBAS',
            'Flag_Absensi' => 'T',
            'Tanggal_Publish' => now(),
            'Created_By' => 1,
            'Created_At' => now(),
        ], $ubah), 'Id_SOP_Paket');
    }

    private function pasangItem(int $idPaket, int $idDokumen, array $ubah = []): int
    {
        return (int) DB::table('N_SOP_Paket_Item')->insertGetId(array_merge([
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Dokumen' => $idDokumen,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item Uji',
            'Urutan' => 1,
            'Flag_Wajib' => 'Y',
            'Flag_Aktif' => 'Y',
            'Created_By' => 1,
            'Created_At' => now(),
        ], $ubah), 'Id_SOP_Paket_Item');
    }

    /* ================= KETERLIHATAN IKUT PAKET ================= */

    public function test_dokumen_muncul_karena_ada_paket_publik_yang_memuatnya(): void
    {
        $judul = 'UJI MUNCUL '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);

        $this->assertNotContains($judul, $this->judulTampil(),
            'Dokumen tanpa paket sudah muncul — berarti ada gerbang dokumen yang tak seharusnya ada.');

        $this->pasangItem($this->buatPaket(), $id);

        $this->assertContains($judul, $this->judulTampil());
    }

    public function test_dokumen_tanpa_paket_tak_pernah_muncul(): void
    {
        /* Inti keputusannya. Dokumen tidak bisa "diterbitkan" ke perpustakaan
           sendirian; satu-satunya jalan adalah lewat paket. */
        $judul = 'UJI YATIM '.$this->tanda();
        $this->buatDokumen(['Judul' => $judul]);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_paket_terbatas_tak_membawa_dokumennya_ke_perpustakaan(): void
    {
        $judul = 'UJI TERBATAS '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(['Mode_Distribusi' => 'TERBATAS']), $id);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_paket_draft_tak_membawa_dokumennya(): void
    {
        $judul = 'UJI DRAFT '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(['Status' => 'DRAFT']), $id);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_paket_nonaktif_tak_membawa_dokumennya(): void
    {
        $judul = 'UJI PAKET MATI '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(['Flag_Aktif' => 'T']), $id);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_item_nonaktif_tak_membawa_dokumennya(): void
    {
        $judul = 'UJI ITEM MATI '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(), $id, ['Flag_Aktif' => 'T']);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_dokumen_nonaktif_tak_muncul_meski_paketnya_publik(): void
    {
        $judul = 'UJI DOK MATI '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(), $id);

        $this->assertContains($judul, $this->judulTampil());

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->update(['Flag_Aktif' => 'T']);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    public function test_mencabut_paket_dari_publik_menarik_dokumennya_juga(): void
    {
        /* Keterlihatan mengikuti paket TERUS-MENERUS, bukan hanya saat pertama
           kali dipasang. Kalau tidak, dokumen akan tertinggal terbuka setelah
           paketnya ditarik — dan tak ada satu pun layar yang menunjukkannya. */
        $judul = 'UJI CABUT '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $idPaket = $this->buatPaket();
        $this->pasangItem($idPaket, $id);

        $this->assertContains($judul, $this->judulTampil());

        DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)
            ->update(['Mode_Distribusi' => 'TERBATAS']);

        $this->assertNotContains($judul, $this->judulTampil());
    }

    /* ================= BENTUK BARIS ================= */

    public function test_baris_menyebut_paket_induknya(): void
    {
        $judul = 'UJI INDUK '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $idPaket = $this->buatPaket();
        $this->pasangItem($idPaket, $id);

        $baris = $this->baris($judul);
        $nama = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');

        $this->assertNotNull($baris);
        $this->assertSame($nama, $baris['Nama_Paket']);
        $this->assertNotEmpty($baris['Id_SOP_Paket']);
        $this->assertNotEmpty($baris['Id_SOP_Paket_Item']);
    }

    public function test_sumbu_dikirim_sebagai_daftar_seperti_sop_saya(): void
    {
        /* Bentuk daftar dipertahankan meski di sini isinya selalu nol atau satu:
           satu modul sumbu di klien melayani dua halaman, dan bentuk yang
           berbeda memaksa cabang di dalamnya. */
        $dep = DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', 'Y')->value('Id_Departemen');
        $jenis = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Flag_Aktif', 'Y')->value('Id_SOP_Jenis_Dokumen');
        $this->assertNotNull($dep);
        $this->assertNotNull($jenis);

        $judul = 'UJI SUMBU '.$this->tanda();
        $id = $this->buatDokumen([
            'Judul' => $judul,
            'Departemen_Id' => $dep,
            'Jenis_Dokumen_Id' => $jenis,
        ]);
        $this->pasangItem($this->buatPaket(), $id);

        $baris = $this->baris($judul);

        $this->assertIsArray($baris['Departemen']);
        $this->assertIsArray($baris['Jenis_Dokumen']);
        $this->assertIsArray($baris['Apqc']);
        $this->assertCount(1, $baris['Departemen']);
        $this->assertSame((int) $dep, $baris['Departemen'][0]['id']);
        $this->assertSame([], $baris['Apqc'], 'Sumbu kosong harus daftar kosong, bukan null.');
    }

    public function test_dokumen_di_dua_paket_publik_muncul_sekali_per_penempatan(): void
    {
        /* Satu baris = satu dokumen DI DALAM satu paket, persis seperti SOP
           Saya. Menggabungkannya jadi satu baris akan menghapus keterangan
           paket induk — padahal itulah yang membuat barisnya bisa dibuka. */
        $judul = 'UJI DUA PAKET '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(), $id);
        $this->pasangItem($this->buatPaket(), $id);

        $cocok = array_filter($this->judulTampil(), fn ($j) => $j === $judul);

        $this->assertCount(2, $cocok);
    }

    public function test_item_tanpa_versi_aktif_ditandai(): void
    {
        $judul = 'UJI TANPA VERSI '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(), $id);

        $this->assertTrue($this->baris($judul)['Tanpa_Versi']);
    }

    public function test_baris_tak_membawa_dimensi_kemajuan(): void
    {
        /* Perpustakaan berisi rujukan, bukan tugas. Ketiganya tetap dikirim
           karena bangunGrup() di klien membacanya, tapi nilainya harus netral —
           angka kemajuan palsu di sini akan tampil sebagai tunggakan. */
        $judul = 'UJI NETRAL '.$this->tanda();
        $id = $this->buatDokumen(['Judul' => $judul]);
        $this->pasangItem($this->buatPaket(), $id);

        $baris = $this->baris($judul);

        $this->assertSame(0, $baris['Persen']);
        $this->assertSame(0, $baris['Item_Selesai']);
        $this->assertSame(1, $baris['Total_Item']);
    }
}
