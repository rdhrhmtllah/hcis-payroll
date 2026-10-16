<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Satu nomor SOP resmi hanya boleh menunjuk satu dokumen.
 *
 * Nomor itulah rujukan kepatuhan yang dipakai DI LUAR sistem — dikutip di
 * audit, ditempel di dinding, disebut dalam temuan. Dua dokumen bernomor sama
 * membuat pertanyaan "PERDIN-001 versi berapa yang berlaku?" punya lebih dari
 * satu jawaban benar, dan tak ada cara memilih di antaranya.
 *
 * Per 14 Agu 2026 ada 4 kelompok bernomor kembar di dev, SEMUANYA lahir dari
 * satu penyalinan paket pada 8 Agu jam 08:16: createForItem() menyalin nomor
 * resmi apa adanya dan tak lewat buat(), jadi penjaga mana pun di pintu Master
 * tak pernah menyentuhnya.
 */
class SopNomorSopUnikTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    /** Nomor yang benar-benar sudah dipakai dokumen aktif. */
    private function nomorTerpakai(): string
    {
        $n = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Nomor_SOP_Resmi')->where('Nomor_SOP_Resmi', '<>', '')
            ->value('Nomor_SOP_Resmi');

        if (! $n) {
            $this->markTestSkipped('Tak ada dokumen bernomor untuk diuji.');
        }

        return $n;
    }

    /* ── pintu deklarasi ───────────────────────────────────────────── */

    public function test_nomor_kembar_ditolak_saat_dokumen_dibuat(): void
    {
        $nomor = $this->nomorTerpakai();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/sudah dipakai dokumen/i');

        $this->svc()->buat([
            'Judul' => 'SOP Uji Nomor Kembar',
            'Nomor_SOP_Resmi' => $nomor,
            'Tipe' => 'DOKUMEN',
        ], 1);
    }

    public function test_nomor_kosong_tidak_dianggap_bentrok(): void
    {
        /* Sebagian dokumen memang belum bernomor. Memperlakukan kekosongan
           sebagai bentrok akan mengunci semuanya kecuali yang pertama. */
        $a = $this->svc()->buat(['Judul' => 'SOP Uji Tanpa Nomor A', 'Nomor_SOP_Resmi' => '', 'Tipe' => 'DOKUMEN'], 1);
        $b = $this->svc()->buat(['Judul' => 'SOP Uji Tanpa Nomor B', 'Tipe' => 'DOKUMEN'], 1);

        $this->assertNotSame($a, $b);
    }

    /* ── pintu sunting ─────────────────────────────────────────────── */

    /**
     * Dokumen yang SUDAH kembar tetap bisa disunting.
     *
     * Aturannya tak berlaku surut: menolaknya pada penyuntingan biasa berarti
     * mengunci admin dari satu-satunya layar tempat kembarannya bisa dibereskan
     * — penjaga yang justru menyandera.
     */
    public function test_menyunting_tanpa_mengubah_nomor_tetap_boleh(): void
    {
        $nomor = $this->nomorTerpakai();
        $dok = DB::table('N_SOP_Dokumen')->where('Nomor_SOP_Resmi', $nomor)
            ->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Dokumen', 'Judul', 'Tipe']);

        $ok = $this->svc()->ubah((int) $dok->Id_SOP_Dokumen, [
            'Judul' => $dok->Judul,
            'Nomor_SOP_Resmi' => $nomor, // TIDAK berubah
            'Tipe' => $dok->Tipe,
            'Deskripsi' => 'Disunting tanpa menyentuh nomornya',
        ], 1);

        $this->assertTrue($ok);
    }

    public function test_mengubah_nomor_ke_milik_dokumen_lain_ditolak(): void
    {
        $nomor = $this->nomorTerpakai();
        $lain = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where(fn ($q) => $q->whereNull('Nomor_SOP_Resmi')->orWhere('Nomor_SOP_Resmi', '<>', $nomor))
            ->first(['Id_SOP_Dokumen', 'Judul', 'Tipe']);

        if (! $lain) {
            $this->markTestSkipped('Butuh dokumen kedua bernomor berbeda.');
        }

        $this->expectException(\DomainException::class);

        $this->svc()->ubah((int) $lain->Id_SOP_Dokumen, [
            'Judul' => $lain->Judul,
            'Nomor_SOP_Resmi' => $nomor,
            'Tipe' => $lain->Tipe,
        ], 1);
    }

    /* ── pintu ketiga: penyalinan paket ────────────────────────────── */

    /**
     * Inilah pintu yang selama ini tak terjaga.
     *
     * createForItem() tak lewat buat(), jadi penjaga di sana tak pernah
     * menyentuhnya — dan seluruh kembaran di dev lahir dari sini. Salinannya
     * tetap dibuat, tapi TANPA nomor: membatalkan penyalinan karena nomornya
     * bentrok akan mematahkan fitur yang sah.
     */
    public function test_salinan_tidak_membawa_nomor_yang_sudah_dipakai(): void
    {
        $nomor = $this->nomorTerpakai();

        $idBaru = $this->svc()->createForItem((object) [
            'Nomor_SOP_Resmi' => $nomor,
            'Judul_Item' => 'SOP Uji Salinan',
            'Tipe_Item' => 'DOKUMEN',
            'Deskripsi_Item' => null,
        ], 1);

        $this->assertNull(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idBaru)->value('Nomor_SOP_Resmi'),
            'Salinan membawa nomor dokumen yang sudah dipakai — rujukan kepatuhannya jadi ambigu.',
        );
    }

    public function test_backfill_tetap_membawa_nomor_yang_belum_dipakai(): void
    {
        // Sisi lain: item lama yang belum punya dokumen memang harus membawa
        // nomornya sendiri. Menjatuhkannya akan menghapus nomor SOP yang sah.
        $nomor = 'UJI-BELUM-DIPAKAI-'.substr(md5((string) random_int(1, 999999)), 0, 6);

        $idBaru = $this->svc()->createForItem((object) [
            'Nomor_SOP_Resmi' => $nomor,
            'Judul_Item' => 'SOP Uji Backfill',
            'Tipe_Item' => 'DOKUMEN',
            'Deskripsi_Item' => null,
        ], 1);

        $this->assertSame($nomor,
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idBaru)->value('Nomor_SOP_Resmi'));
    }
}
