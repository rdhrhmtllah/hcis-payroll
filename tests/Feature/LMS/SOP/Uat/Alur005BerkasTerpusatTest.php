<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;

/**
 * UAT-SOP-005 — berkas SOP tersimpan terpusat dan dapat dipakai berulang.
 *
 * BATAS UJI INI, dinyatakan terus terang. Yang diperiksa adalah janji bisnis
 * skenarionya: berkas yang terdaftar dapat ditemukan kembali, dapat dipilih
 * saat membuat versi dokumen, dan SATU berkas dapat dipakai banyak dokumen
 * tanpa digandakan.
 *
 * Yang TIDAK diuji di sini adalah transport unggahnya. Jalur unggah yang aktif
 * berbasis tiket — peramban mengunggah langsung ke Google Cloud Storage dengan
 * tiket bertanda tangan, lalu endpointnya mendaftarkan barisnya. Mengujinya
 * ujung-ke-ujung berarti menembak penyimpanan sungguhan, dan setelah temuan
 * pada deleteKonten saya tidak menjalankan apa pun yang menulis ke penyimpanan
 * nyata dari dalam test. Bagian itu milik lapis peramban (Tahap 3), dan
 * batasnya dicatat di sini supaya tak dikira sudah tercakup.
 */
class Alur005BerkasTerpusatTest extends TestCase
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

    /** Konten SOP terdaftar, tanpa menyentuh penyimpanan sama sekali. */
    private function daftarkanKonten(string $judul): int
    {
        return (int) DB::table('N_LMS_Master_Konten')->insertGetId([
            'Id_Kategori_Konten' => DB::table('N_LMS_Master_Konten')->value('Id_Kategori_Konten'),
            'Judul_Konten' => $judul,
            'Tipe_Konten' => 'PDF',
            'Flag_Aktif' => 'Y',
            'Scope' => 'SOP',
        ], 'Id_Master_Konten');
    }

    #[Group('UAT-SOP-005')]
    public function test_berkas_terdaftar_bisa_ditemukan_kembali_pada_lingkup_sop(): void
    {
        $this->adminAtauSkip();

        $judul = 'UAT-AUTO Berkas '.substr(md5(uniqid('', true)), 0, 6);
        $id = $this->daftarkanKonten($judul);

        $ketemu = DB::table('N_LMS_Master_Konten')
            ->where('Id_Master_Konten', $id)
            ->where('Scope', 'SOP')
            ->where('Flag_Aktif', 'Y')
            ->value('Judul_Konten');

        $this->assertSame($judul, $ketemu,
            'Berkas yang baru terdaftar tak ditemukan pada lingkup SOP. Ia akan tak '
            .'terlihat di pemilih berkas saat admin membuat versi dokumen.');
    }

    /**
     * Inti "terpusat": satu berkas dipakai DUA dokumen berbeda, dan keduanya
     * menunjuk baris konten yang sama — bukan dua salinan.
     *
     * Berkas yang tergandakan adalah akar seluruh persoalan yang melahirkan
     * Master Dokumen: satu berkas fisik yang sama tersimpan sebagai beberapa
     * salinan tata kelola yang perlahan menyimpang.
     */
    #[Group('UAT-SOP-005')]
    public function test_satu_berkas_bisa_dipakai_banyak_dokumen_tanpa_digandakan(): void
    {
        $admin = $this->adminAtauSkip();
        $userId = (int) $admin->Id_Users;

        $konten = UatDunia::kontenDokumenCocok();
        if (! $konten) {
            $this->markTestSkipped('Tidak ada berkas bertipe cocok untuk dokumen di database ini.');
        }

        $svc = app(SopDokumenService::class);
        $versi = [];

        foreach (['A', 'B'] as $sufiks) {
            $tanda = 'UAT-AUTO-'.substr(md5(uniqid('', true)), 0, 6);
            $idDokumen = $svc->buat([
                'Judul' => "{$tanda} Dokumen {$sufiks}",
                'Nomor_SOP_Resmi' => $tanda,
                'Tipe' => 'DOKUMEN',
            ], $userId);

            $idVersi = (int) $svc->tambahVersi($idDokumen, [
                'Id_Master_Konten' => $konten,
                'Pengesah' => UatDunia::PENGESAH_UJI,
            ], $userId);

            /* Yang diuji di sini adalah BERKAS yang dipakai bersama, bukan alur
               tahapnya; tetapi kedua dokumen tetap harus benar-benar terbit agar
               hitungan pemakai di bawah membaca keadaan yang sungguhan. Sejak
               DML-2026-048/051 penerbitan hanya sah dari langkah Pemberkasan. */
            UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId);

            $svc->terbitkanVersi($idVersi, $userId);
            $versi[] = $idVersi;
        }

        $kontenDipakai = DB::table('N_SOP_Item_Versi')
            ->whereIn('Id_SOP_Item_Versi', $versi)
            ->pluck('Id_Master_Konten')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values();

        $this->assertCount(1, $kontenDipakai,
            'Dua dokumen yang memakai berkas yang sama justru menunjuk konten berbeda.');
        $this->assertSame($konten, $kontenDipakai[0]);

        /* Dan berkas itu memang terbaca sebagai dipakai lebih dari satu dokumen —
           angka inilah yang dipakai layar untuk memperingatkan admin sebelum ia
           menghapus sesuatu yang masih dibutuhkan. */
        $pemakai = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_Master_Konten', $konten)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->count('d.Id_SOP_Dokumen');

        $this->assertGreaterThanOrEqual(2, $pemakai,
            'Berkas yang dipakai dua dokumen terbaca dipakai kurang dari dua.');
    }
}
