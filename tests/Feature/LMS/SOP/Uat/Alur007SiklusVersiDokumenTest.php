<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDokumenFanoutService;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Siklus hidup versi dokumen terkendali — jalur normal.
 *
 * UAT-SOP-007 mendaftarkan dokumen · 010 menandai siap terbit · 011 melihat
 * dampak sebelum terbit · 012 menerbitkan dan menggeser penunjuk aktif ·
 * 014 mengembalikan ke versi sebelumnya.
 *
 * Kelima-limanya satu rantai, dan diuji sebagai rantai. Menguji "terbit" tanpa
 * pernah menerbitkan versi KEDUA melewatkan satu-satunya hal yang membuat
 * penerbitan sulit: apa yang terjadi pada versi yang sedang berlaku.
 */
class Alur007SiklusVersiDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function dokumenBerversi(): array
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }
        $this->userId = (int) $admin->Id_Users;

        $konten = UatDunia::kontenDokumenCocok();
        if (! $konten) {
            $this->markTestSkipped(
                'Tidak ada versi DOKUMEN terbit yang menautkan berkas, sehingga tak ada '
                .'contoh berkas bertipe cocok untuk membangun dokumen uji.'
            );
        }

        $tanda = 'UAT-AUTO-'.substr(md5(uniqid('', true)), 0, 6);
        $idDokumen = $this->svc()->buat([
            'Judul' => $tanda.' Dokumen Siklus',
            'Nomor_SOP_Resmi' => $tanda,
            'Tipe' => 'DOKUMEN',
            'Deskripsi' => 'Dibuat otomatis oleh uji UAT.',
        ], $this->userId);

        return [$idDokumen, $konten, $tanda];
    }

    /**
     * Versi baru yang SUDAH berdiri di ambang terbit.
     *
     * Uji-uji di berkas ini ditulis ketika sebuah versi bisa diterbitkan
     * langsung dari Drafting. DML-2026-048 memberi APPROVED langkah Pemberkasan
     * tersendiri dan DML-2026-051 menambahkan Tinjauan Akhir, sehingga
     * terbitkanVersi() kini menolak versi yang tahapnya belum mengarah ke
     * FILING_REGISTERED.
     *
     * Perjalanan tahapnya dijalankan DI SINI, bukan di tiap uji: yang sedang
     * diuji berkas ini adalah akibat penerbitan — penunjuk aktif bergeser,
     * versi lama pensiun, kembalikan bekerja — bukan alur tahapnya, yang punya
     * berkas ujinya sendiri (SopTahapVersiTest).
     */
    private function tambahVersi(int $idDokumen, int $konten): int
    {
        $idVersi = (int) $this->svc()->tambahVersi($idDokumen, [
            'Id_Master_Konten' => $konten,
            'Ringkasan_Perubahan' => 'Revisi uji.',
            'Tanggal_Berlaku' => now()->toDateString(),
            'Pengesah' => UatDunia::PENGESAH_UJI,
        ], $this->userId);

        UatTahapVersi::bawaKeAmbangTerbit($idVersi, $this->userId);

        return $idVersi;
    }

    #[Group('UAT-SOP-007')]
    public function test_dokumen_baru_tercatat_tanpa_versi_aktif(): void
    {
        [$idDokumen, , $tanda] = $this->dokumenBerversi();

        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->first();

        $this->assertSame($tanda, $dok->Nomor_SOP_Resmi);
        $this->assertNull($dok->Id_Versi_Aktif,
            'Dokumen yang baru didaftarkan sudah punya penunjuk versi aktif. Penunjuk itu '
            .'hanya boleh menunjuk versi yang SUDAH sah; menunjuk sesuatu yang belum '
            .'terbit berarti menyajikan draft kepada karyawan.');
    }

    /**
     * UAT-SOP-010 — blok pengesahan dilengkapi, lalu versinya terbit.
     *
     * Skenario ini semula berbunyi "tandai versi sebagai siap terbit". Keadaan
     * itu mustahil: CHECK constraint CK_N_SOP_Versi_Status hanya mengizinkan
     * DRAFT/PUBLISHED/OBSOLETE, sedangkan setReady() menulis 'READY' — jadi
     * endpoint PATCH .../versi/{id}/ready selalu ditolak basis data, dan data
     * produksi berisi nol baris READY bukan karena tak dipakai melainkan karena
     * tak bisa. Dokumen UAT sudah diperbaiki; langkah yang diuji sekarang adalah
     * langkah yang memang ada.
     *
     * Uji ini ikut menjaga agar catatan itu tak menjadi basi: bila suatu hari
     * constraint-nya diubah untuk mengizinkan READY, assertion di bawah gagal
     * dan memaksa peninjauan ulang — termasuk atas skenario 010 di dokumen.
     */
    #[Group('UAT-SOP-010')]
    public function test_blok_pengesahan_dilengkapi_lalu_versinya_terbit(): void
    {
        [$idDokumen, $konten] = $this->dokumenBerversi();
        $idVersi = $this->tambahVersi($idDokumen, $konten);

        $pengesah = DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->orderBy('Urutan')
            ->get(['Peran', 'Nama']);

        $this->assertCount(count(UatDunia::PENGESAH_UJI), $pengesah,
            'Blok pengesahan tidak tersimpan lengkap.');
        $this->assertSame('Disiapkan oleh', $pengesah[0]->Peran);

        $this->assertTrue($this->svc()->terbitkanVersi($idVersi, $this->userId));

        $this->assertSame(UatDunia::PENGESAH_UJI[0]['Nama'],
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Disahkan_Oleh'),
            'Kolom cermin Disahkan_Oleh tidak terisi dari blok penanda tangan, sehingga '
            .'register kepatuhan akan melaporkan dokumen ini sebagai belum disahkan.');
    }

    /**
     * Status "siap terbit" tetap mustahil — dan itu memang keputusannya.
     *
     * Dijaga di sini supaya perubahan skema yang menghidupkan READY tidak lolos
     * tanpa disadari: seluruh pembacaan yang menyaring status harus ditinjau
     * lebih dulu, kalau tidak versi READY akan tampak hilang di sebagian layar.
     */
    #[Group('UAT-SOP-010')]
    public function test_status_ready_tetap_ditolak_basis_data(): void
    {
        $definisi = DB::selectOne(
            "SELECT definition FROM sys.check_constraints WHERE name = 'CK_N_SOP_Versi_Status'"
        );

        if (! $definisi) {
            $this->markTestSkipped('Constraint CK_N_SOP_Versi_Status tidak ada di database ini.');
        }

        $this->assertStringNotContainsString(SopStatus::VERSI_READY, $definisi->definition,
            'Constraint kini mengizinkan READY. Sebelum itu dipakai, tinjau seluruh '
            .'pembacaan yang menyaring Status — dan tinjau ulang skenario 010 pada '
            .'dokumen UAT, yang saat ini ditulis untuk alur DRAFT → PUBLISHED langsung.');
    }

    /**
     * Endpoint "siapkan rilis" milik Builder SUDAH TAK ADA.
     *
     * Riwayat skenario ini panjang dan patut dicatat. Mula-mula endpointnya
     * memanggil SopItemVersionService::setReady(), yang menulis Status = 'READY'
     * ke N_SOP_Item_Versi dan selalu ditolak CK_N_SOP_Versi_Status — yang sampai
     * ke admin sebuah 500. Lalu ia diperbaiki agar menolak dengan 422 beserta
     * alasannya. Sejak 27 Agu 2026 seluruh siklus tulis versi dicabut dari
     * Builder, jadi pintunya bukan lagi "menolak dengan sopan" melainkan tidak
     * ada sama sekali.
     *
     * Yang dijaga tetap sama arahnya: tak ada jalur dari Builder yang bisa
     * menggeser status versi. 404 di sini adalah bentuk terkuatnya — permintaan
     * yang tak sampai ke controller mana pun tak bisa menulis apa pun.
     */
    #[Group('UAT-SOP-010')]
    public function test_siapkan_rilis_dari_builder_sudah_tak_ada_pintunya(): void
    {
        [$idDokumen, $konten] = $this->dokumenBerversi();
        $idVersi = $this->tambahVersi($idDokumen, $konten);

        $this->assertSame(SopStatus::VERSI_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'),
            'Prasyarat uji meleset: versinya bukan DRAFT, sehingga jalur yang diuji tak tersentuh.');

        $hash = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs(UatAkun::admin())
            ->patchJson("/api/v1/sop/admin/berkas/versi/{$hash}/ready", ['ready' => true]);

        $res->assertStatus(404);

        $this->assertSame(SopStatus::VERSI_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'),
            'Statusnya bergeser padahal rutenya sudah dicabut.');
    }

    #[Group('UAT-SOP-011')]
    public function test_dampak_dilaporkan_sebelum_terbit_dan_menghitung_paket_pemakai(): void
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            $d = UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Dampak')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $sebelum = app(SopDokumenFanoutService::class)->hitungDampak($d->idDokumen);
        $lain = $d->paketTambahan('Paket Dampak Kedua');
        $d->terbitkanPaketLain($lain['paket']);
        $sesudah = app(SopDokumenFanoutService::class)->hitungDampak($d->idDokumen);

        $this->assertGreaterThan((int) $sebelum['paket'], (int) $sesudah['paket'],
            'Menambah paket pemakai tidak menaikkan hitungan dampak. Admin akan '
            .'menerbitkan revisi tanpa tahu berapa banyak paket yang ikut berubah.');
        $this->assertGreaterThan((int) $sebelum['item'], (int) $sesudah['item']);
    }

    #[Group('UAT-SOP-012')]
    public function test_terbit_versi_kedua_memensiunkan_versi_pertama(): void
    {
        [$idDokumen, $konten] = $this->dokumenBerversi();

        $v1 = $this->tambahVersi($idDokumen, $konten);
        $this->svc()->terbitkanVersi($v1, $this->userId);

        $v2 = $this->tambahVersi($idDokumen, $konten);
        $this->svc()->terbitkanVersi($v2, $this->userId);

        $status = DB::table('N_SOP_Item_Versi')
            ->whereIn('Id_SOP_Item_Versi', [$v1, $v2])
            ->pluck('Status', 'Id_SOP_Item_Versi');

        $this->assertSame('OBSOLETE', $status[$v1],
            'Versi lama masih berstatus terbit setelah penggantinya terbit. Dua versi '
            .'yang sama-sama mengaku berlaku membuat "versi mana yang sah" tak punya '
            .'jawaban.');
        $this->assertSame(SopStatus::PAKET_PUBLISHED, $status[$v2]);
        $this->assertSame($v2,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif'));
    }

    #[Group('UAT-SOP-014')]
    public function test_versi_lama_bisa_dikembalikan_dan_penunjuk_dokumen_ikut_mundur(): void
    {
        [$idDokumen, $konten] = $this->dokumenBerversi();

        $v1 = $this->tambahVersi($idDokumen, $konten);
        $this->svc()->terbitkanVersi($v1, $this->userId);
        $v2 = $this->tambahVersi($idDokumen, $konten);
        $this->svc()->terbitkanVersi($v2, $this->userId);

        $this->assertTrue($this->svc()->kembalikanVersi($v1, $this->userId));

        $this->assertSame(SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v1)->value('Status'));
        $this->assertSame($v1,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif'),
            'Penunjuk dokumen tidak ikut mundur. Dokumen akan menyebut versi lain yang '
            .'berlaku daripada yang benar-benar disajikan ke karyawan.');
        $this->assertSame($this->userId,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v1)->value('Updated_By'),
            'Jejak siapa yang mengembalikan tidak tercatat.');
    }

    /**
     * Dua penolakan yang membuat "kembalikan" punya arti. Tanpa keduanya, tombol
     * itu menjadi cara lain untuk menerbitkan — dan jejak auditnya akan berbunyi
     * "dikembalikan" untuk peristiwa yang sebenarnya penerbitan.
     */
    #[Group('UAT-SOP-014')]
    public function test_versi_berlaku_dan_versi_draft_menolak_dikembalikan(): void
    {
        [$idDokumen, $konten] = $this->dokumenBerversi();

        $v1 = $this->tambahVersi($idDokumen, $konten);
        $this->svc()->terbitkanVersi($v1, $this->userId);
        $draft = $this->tambahVersi($idDokumen, $konten);

        try {
            $this->svc()->kembalikanVersi($v1, $this->userId);
            $this->fail('Versi yang sedang berlaku bisa "dikembalikan".');
        } catch (DomainException $e) {
            $this->assertStringContainsString('berlaku', strtolower($e->getMessage()));
        }

        try {
            $this->svc()->kembalikanVersi($draft, $this->userId);
            $this->fail('Versi draft bisa "dikembalikan" padahal belum pernah berlaku.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('draft', strtolower($e->getMessage()));
        }
    }
}
