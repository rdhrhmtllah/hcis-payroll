<?php

namespace Tests\Feature\LMS\SOP\Uat;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\TestCase;

/**
 * Penghapusan konten — penjaga pemakaian dan batas Scope.
 *
 * Menutup celah yang ditemukan saat menyiapkan UAT-SOP-006.
 * ContentManagementController::deleteKonten menghapus berkas fisik dari Google
 * Cloud Storage dan Bunny secara PERMANEN, sementara barisnya hanya soft delete.
 * Dua akibatnya:
 *
 *   1. Konten yang masih dirujuk versi SOP atau materi LMS bisa dihapus, dan
 *      dokumen yang sah menurut catatan menjadi kosong menurut kenyataan.
 *   2. Soft delete-nya disaring `Scope`, penghapusan berkasnya TIDAK — sehingga
 *      penghapusan dari sisi SOP dapat melenyapkan berkas milik LMS, dengan
 *      barisnya tetap hidup karena scope tak cocok.
 *
 * SELURUH UJI DI SINI MEMAKAI Storage::fake. Tidak ada satu pun berkas sungguhan
 * yang boleh tersentuh oleh test — kalau perbaikannya belum ada, uji ini justru
 * yang akan memicu penghapusan yang sedang dibuktikan.
 */
class HapusKontenAmanTest extends TestCase
{
    use DatabaseTransactions;

    private function adminAtauSkip()
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        return $admin;
    }

    /** Konten uji + satu baris berkas berjalur berkas palsu. */
    private function buatKonten(string $scope, string $jalur): int
    {
        $kategori = DB::table('N_LMS_Master_Konten')->value('Id_Kategori_Konten');

        $id = (int) DB::table('N_LMS_Master_Konten')->insertGetId([
            'Id_Kategori_Konten' => $kategori,
            'Judul_Konten' => 'UAT-AUTO Konten '.$scope,
            'Tipe_Konten' => 'PDF',
            'Flag_Aktif' => 'Y',
            'Scope' => $scope,
        ], 'Id_Master_Konten');

        DB::table('N_LMS_Master_Konten_Berkas')->insert([
            'Id_Master_Konten' => $id,
            'Media_Key' => 'uat-auto-'.$id,
            'Type_File' => 'pdf',
            'File_Path' => $jalur,
        ]);

        Storage::disk('gcs')->put($jalur, 'isi berkas uji');

        return $id;
    }

    private function hapusDariSisiSop($admin, int $idKonten)
    {
        return $this->actingAs($admin)
            ->postJson('/sop/admin/konten/delete-konten', ['ids' => [(string) $idKonten]]);
    }

    /**
     * CACAT PALING BERAT: penghapusan dari sisi SOP tidak boleh menyentuh berkas
     * milik LMS. Barisnya memang tak terhapus karena scope tak cocok — dan
     * justru itu yang membuatnya berbahaya: tak ada gejala apa pun di layar,
     * sementara berkasnya sudah tidak ada.
     */
    #[Group('UAT-SOP-006')]
    public function test_penghapusan_dari_sisi_sop_tidak_melenyapkan_berkas_milik_lms(): void
    {
        Storage::fake('gcs');
        $admin = $this->adminAtauSkip();

        $jalur = 'uat-auto/berkas-lms.pdf';
        $idLms = $this->buatKonten('LMS', $jalur);

        $this->hapusDariSisiSop($admin, $idLms);

        $this->assertSame('Y',
            DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $idLms)->value('Flag_Aktif'),
            'Baris konten LMS ikut dinonaktifkan dari sisi dokumen.');

        Storage::disk('gcs')->assertExists($jalur);
    }

    /**
     * BATCH CAMPURAN — di sinilah kebocoran Scope sesungguhnya muncul.
     *
     * Menghapus SATU konten LMS dari sisi SOP tidak pernah sampai ke kode
     * penghapusan berkas: daftar dalam-scope kosong dan permintaannya berhenti
     * lebih awal. Kebocorannya baru terjadi ketika batch memuat KEDUANYA —
     * konten SOP yang memang boleh dihapus, dan konten LMS yang menumpang di
     * daftar id yang sama. Saat itu daftar dalam-scope tidak kosong, kodenya
     * berjalan sampai akhir, dan berkas LMS ikut dihapus.
     *
     * Ini persis kejadian yang wajar di layar: admin mencentang beberapa berkas
     * sekaligus. Uji satu-id yang lulus tanpa menyentuh jalur berbahaya adalah
     * uji yang menjaga pintu yang salah.
     */
    #[Group('UAT-SOP-006')]
    public function test_batch_campuran_hanya_menghapus_berkas_milik_scope_sendiri(): void
    {
        Storage::fake('gcs');
        $admin = $this->adminAtauSkip();

        $jalurSop = 'uat-auto/campur-sop.pdf';
        $jalurLms = 'uat-auto/campur-lms.pdf';
        $idSop = $this->buatKonten('SOP', $jalurSop);
        $idLms = $this->buatKonten('LMS', $jalurLms);

        $this->actingAs($admin)->postJson('/sop/admin/konten/delete-konten', [
            'ids' => [(string) $idSop, (string) $idLms],
        ])->assertOk();

        $this->assertSame('T',
            DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $idSop)->value('Flag_Aktif'));
        $this->assertSame('Y',
            DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $idLms)->value('Flag_Aktif'),
            'Baris konten LMS ikut dinonaktifkan dari sisi dokumen.');

        Storage::disk('gcs')->assertMissing($jalurSop);
        Storage::disk('gcs')->assertExists($jalurLms);
    }

    /**
     * Konten yang masih dirujuk versi SOP tidak boleh dihapus. Versi yang sudah
     * terbit adalah catatan yang sudah diteken; membuat berkasnya lenyap berarti
     * auditor yang bertanya "apa yang dibaca karyawan" dijawab dengan berkas
     * yang tak ada.
     */
    #[Group('UAT-SOP-006')]
    public function test_konten_yang_masih_dirujuk_versi_sop_menolak_dihapus(): void
    {
        Storage::fake('gcs');
        $admin = $this->adminAtauSkip();

        $jalur = 'uat-auto/berkas-terpakai.pdf';
        $idKonten = $this->buatKonten('SOP', $jalur);

        $idDokumen = app(\App\Services\LMS\SOP\SopDokumenService::class)->buat([
            'Judul' => 'UAT-AUTO Dokumen Pemakai Berkas',
            'Nomor_SOP_Resmi' => 'UAT-AUTO-PAKAI',
            'Tipe' => 'DOKUMEN',
        ], (int) $admin->Id_Users);

        app(\App\Services\LMS\SOP\SopDokumenService::class)->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $idKonten,
        ], (int) $admin->Id_Users);

        $res = $this->hapusDariSisiSop($admin, $idKonten);

        $this->assertSame('Y',
            DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $idKonten)->value('Flag_Aktif'),
            'Konten yang masih dirujuk versi dokumen tetap dinonaktifkan.');

        Storage::disk('gcs')->assertExists($jalur);

        $this->assertGreaterThanOrEqual(400, $res->getStatusCode(),
            'Penghapusan yang ditolak dijawab sukses, sehingga admin mengira berkasnya '
            .'sudah hilang padahal masih ada — atau sebaliknya.');
    }

    /**
     * Sisi positif. Penjaga yang menolak SEGALANYA lulus kedua uji di atas
     * dengan sempurna sambil membuat Library mustahil dirapikan: konten salah
     * unggah pun tak bisa dibuang.
     */
    #[Group('UAT-SOP-006')]
    public function test_konten_sop_yang_tidak_dipakai_siapa_pun_tetap_bisa_dihapus(): void
    {
        Storage::fake('gcs');
        $admin = $this->adminAtauSkip();

        $jalur = 'uat-auto/berkas-yatim.pdf';
        $idKonten = $this->buatKonten('SOP', $jalur);

        $res = $this->hapusDariSisiSop($admin, $idKonten);

        $res->assertOk();
        $this->assertSame('T',
            DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $idKonten)->value('Flag_Aktif'),
            'Konten yang tak dipakai siapa pun justru tak bisa dihapus; Library tak akan '
            .'pernah bisa dirapikan.');

        Storage::disk('gcs')->assertMissing($jalur);
    }
}
