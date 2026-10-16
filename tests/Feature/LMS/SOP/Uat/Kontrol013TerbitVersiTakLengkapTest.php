<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;

/**
 * UAT-SOP-013 — dokumen yang belum lengkap tidak bisa diterbitkan.
 *
 * Skenario KONTROL, dan yang dijaganya adalah inti gagasan "dokumen
 * terkendali": versi yang terbit disajikan ke karyawan sebagai aturan kerja
 * yang sah. Terbit setengah jadi berarti karyawan mengkonfirmasi telah membaca
 * sesuatu yang belum pernah disahkan siapa pun.
 *
 * Diuji di lapis SERVICE, bukan lewat controller. Penerbitan punya DUA pintu —
 * Master Dokumen lewat controllernya, dan publish paket di Builder yang
 * memanggil terbitkanVersi() langsung. Menguji satu controller saja akan tetap
 * hijau seandainya pintu satunya kehilangan penjaganya, dan itu justru cara
 * cacat semacam ini biasanya muncul di modul ini.
 */
class Kontrol013TerbitVersiTakLengkapTest extends TestCase
{
    use DatabaseTransactions;

    private function idUser(): int
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        return (int) $admin->Id_Users;
    }

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function buatDokumen(string $judul): int
    {
        return $this->svc()->buat([
            'Judul' => $judul,
            'Nomor_SOP_Resmi' => 'UAT-AUTO-'.substr(md5($judul), 0, 8),
            'Tipe' => 'DOKUMEN',
            'Deskripsi' => 'Dibuat otomatis oleh uji UAT.',
        ], $this->idUser());
    }

    /**
     * Konten yang PASTI cocok untuk tipe DOKUMEN: diambil dari versi SOP yang
     * sudah terbit. Mengarang Id_Master_Konten akan tersandung penjaga
     * kecocokan tipe, sehingga uji "tanpa pengesahan" gagal karena alasan yang
     * sama sekali lain — dan menuduh penjaga yang salah.
     */
    private function kontenCocokAtauSkip(): int
    {
        $id = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('d.Tipe', 'DOKUMEN')
            ->whereNotNull('v.Id_Master_Konten')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->value('v.Id_Master_Konten');

        if (! $id) {
            $this->markTestSkipped(
                'Tidak ada versi DOKUMEN terbit yang menautkan berkas, sehingga tak ada '
                .'contoh berkas bertipe cocok untuk dipakai uji.'
            );
        }

        return (int) $id;
    }

    #[Group('UAT-SOP-013')]
    public function test_versi_tanpa_berkas_menolak_diterbitkan(): void
    {
        $userId = $this->idUser();
        $idDokumen = $this->buatDokumen('UAT-AUTO Dokumen Tanpa Berkas');

        $idVersi = $this->svc()->tambahVersi($idDokumen, [
            'Ringkasan_Perubahan' => 'Versi tanpa berkas.',
            /* Pengesahnya SENGAJA ada. Yang diuji di sini satu hal saja —
               ketiadaan berkas — dan versi tanpa pengesah tak akan pernah
               sampai ke langkah Pemberkasan, sehingga yang menolaknya adalah
               gerbang tahap, bukan gerbang berkas. Penjaga yang tak pernah
               disentuh membuat uji ini hijau tanpa menguji apa pun. */
            'Disahkan_Oleh' => 'Pengesah Uji',
        ], $userId);

        $this->assertNotNull($idVersi, 'Versi draft uji gagal dibuat.');

        /* Versi dibawa sampai ambang terbit (APPROVED/Pemberkasan) supaya
           penolakannya benar-benar datang dari pastikanIsiSah(), bukan dari
           penjaga tahap DML-2026-048 yang berdiri di depannya. */
        $this->assertSame('APPROVED', UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId),
            'Versi berpengesah tak sampai ke langkah Pemberkasan, sehingga uji ini '
            .'menguji gerbang tahap alih-alih gerbang berkas.');

        try {
            $this->svc()->terbitkanVersi($idVersi, $userId);
            $this->fail('Versi tanpa berkas berhasil diterbitkan — dokumen kosong akan '
                .'tersaji ke karyawan sebagai aturan kerja yang sah.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('berkas', strtolower($e->getMessage()));
        }

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'),
            'Penerbitan ditolak tapi statusnya sudah terlanjur berubah.');
        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif'),
            'Penunjuk versi aktif bergeser ke versi yang penerbitannya ditolak.');
    }

    /**
     * Sisi positifnya. Penjaga yang menolak SEGALANYA lulus uji di atas dengan
     * sempurna sambil membuat modul mustahil dipakai.
     */
    #[Group('UAT-SOP-013')]
    public function test_versi_berberkas_berhasil_diterbitkan_dan_menggeser_penunjuk_aktif(): void
    {
        $userId = $this->idUser();
        $konten = $this->kontenCocokAtauSkip();
        $idDokumen = $this->buatDokumen('UAT-AUTO Dokumen Berberkas');

        $idVersi = $this->svc()->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $konten,
            'Ringkasan_Perubahan' => 'Versi lengkap.',
            'Pengesah' => UatDunia::PENGESAH_UJI,
        ], $userId);

        UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId);

        $this->assertTrue($this->svc()->terbitkanVersi($idVersi, $userId));

        $this->assertSame(SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'));
        $this->assertSame($idVersi,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif'),
            'Penunjuk versi aktif harus menunjuk versi yang baru terbit.');
    }

    /**
     * Menerbitkan ulang versi yang SUDAH terbit adalah pengembalian yang menyamar
     * sebagai penerbitan: penunjuk mundur ke revisi lama sementara jejak
     * auditnya berbunyi "versi baru diterbitkan".
     */
    #[Group('UAT-SOP-013')]
    public function test_versi_yang_sudah_terbit_menolak_diterbitkan_ulang(): void
    {
        $userId = $this->idUser();
        $konten = $this->kontenCocokAtauSkip();
        $idDokumen = $this->buatDokumen('UAT-AUTO Dokumen Terbit Ulang');

        $idVersi = $this->svc()->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => UatDunia::PENGESAH_UJI,
        ], $userId);
        UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId);
        $this->svc()->terbitkanVersi($idVersi, $userId);

        $this->expectException(DomainException::class);
        $this->svc()->terbitkanVersi($idVersi, $userId);
    }

    /**
     * Separuh kedua janji skenario 013: tanpa pengesahan pun ditolak.
     *
     * Yang membedakan dokumen TERKENDALI dari sekadar berkas adalah adanya orang
     * yang bertanggung jawab menandatanganinya. Versi yang terbit tanpa pengesah
     * adalah aturan kerja yang tak seorang pun akui — sementara karyawan tetap
     * diminta mengkonfirmasi telah membacanya.
     */
    #[Group('UAT-SOP-013')]
    public function test_versi_tanpa_pengesahan_menolak_diterbitkan(): void
    {
        $userId = $this->idUser();
        $konten = $this->kontenCocokAtauSkip();
        $idDokumen = $this->buatDokumen('UAT-AUTO Dokumen Tanpa Pengesahan');

        $idVersi = $this->svc()->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => [],   // blok penanda tangan sengaja kosong
        ], $userId);

        /* Sejak DML-2026-048 gerbang pengesahan menyala SATU LANGKAH lebih awal:
           syarat REALISASI_PENGESAH menjadi syarat MASUK ke langkah Pemberkasan,
           bukan lagi syarat penerbitan saja. Versi tanpa penanda tangan karena
           itu mentok di Pengesahan dan tak pernah sampai ke ambang terbit —
           dan itulah bentuk terkuat dari janji skenario 013. */
        $this->assertSame('APPROVAL', UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId),
            'Versi tanpa pengesah berhasil melewati langkah Pengesahan. Gerbang '
            .'REALISASI_PENGESAH pada langkah Pemberkasan tidak lagi menggigit.');

        try {
            $this->svc()->terbitkanVersi($idVersi, $userId);
            $this->fail('Versi tanpa pengesah berhasil diterbitkan.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('pengesah', strtolower($e->getMessage()));
        }

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'));
        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif'));
    }

    /**
     * Kolom cermin Disahkan_Oleh diterima sebagai bukti pengesahan.
     *
     * Versi-versi lama ditulis sebelum blok penanda tangan ada, dan hanya
     * mengisi kolom tunggal itu. Menuntut baris blok tanpa kecuali akan menolak
     * revisi atas dokumen-dokumen lama — penjaga yang benar berubah menjadi
     * penghalang kerja.
     */
    #[Group('UAT-SOP-013')]
    public function test_pengesahan_lewat_kolom_cermin_saja_sudah_cukup(): void
    {
        $userId = $this->idUser();
        $konten = $this->kontenCocokAtauSkip();
        $idDokumen = $this->buatDokumen('UAT-AUTO Dokumen Pengesahan Cermin');

        $idVersi = $this->svc()->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $konten,
            'Disahkan_Oleh' => 'Pengesah Lama',
        ], $userId);

        /* Kolom cermin harus cukup untuk MELEWATI gerbang tahap juga, bukan
           hanya untuk pastikanIsiSah(): syarat REALISASI_PENGESAH membaca blok
           penanda tangan ATAU kolom cermin. Kalau ia hanya membaca bloknya,
           dokumen lama akan mentok di Pengesahan dan tak bisa lagi direvisi. */
        UatTahapVersi::bawaKeAmbangTerbit($idVersi, $userId);

        $this->assertTrue($this->svc()->terbitkanVersi($idVersi, $userId),
            'Versi yang pengesahannya tercatat pada kolom cermin ikut ditolak, sehingga '
            .'dokumen-dokumen lama tak bisa lagi direvisi.');
    }
}
