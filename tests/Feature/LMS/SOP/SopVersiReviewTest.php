<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopVersiReviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Catatan review pada versi dokumen SOP.
 *
 * Yang dijaga di sini: isinya BERSIH sebelum tersimpan, daftarnya ditulis ulang
 * bukan ditambahi, dan satu lampiran bermasalah tak membuang catatan yang sudah
 * diketik.
 */
class SopVersiReviewTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopVersiReviewService
    {
        return app(SopVersiReviewService::class);
    }

    private function versiBaru(): int
    {
        $id = app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI REVIEW '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = app(SopDokumenService::class)->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        if (! $idVersi) {
            $this->markTestSkipped('Versi gagal dibuat.');
        }

        return $idVersi;
    }

    /* ── Skema ──────────────────────────────────────────────────────────── */

    public function test_tabel_review_dan_lampiran_ada(): void
    {
        foreach (['N_SOP_Item_Versi_Review', 'N_SOP_Item_Versi_Review_Lampiran'] as $t) {
            $this->assertNotNull(
                DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?', [$t]),
                "Tabel {$t} belum ada — jalankan DDL-2026-038.",
            );
        }
    }

    /* ── Sanitasi ───────────────────────────────────────────────────────── */

    public function test_gambar_dibuang_dari_isi_review(): void
    {
        /* Inti permintaannya: gambar tak boleh menyusup lewat isi teks. <img>
           tak ada di daftar putih SopHtml, jadi ia terbuang di SERVER — bukan
           hanya di editor, yang bisa dilewati panggilan API langsung. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Hasil_Review' => '<p>Sebelum</p><img src="http://jahat/x.png"><p>Sesudah</p>',
        ]], 1);

        $isi = (string) DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Hasil_Review');

        $this->assertStringNotContainsString('<img', $isi);
        $this->assertStringContainsString('Sebelum', $isi);
        $this->assertStringContainsString('Sesudah', $isi);
    }

    public function test_script_dibuang_beserta_isinya(): void
    {
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Hasil_Review' => '<p>Aman</p><script>alert(1)</script>',
        ]], 1);

        $isi = (string) DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Hasil_Review');

        $this->assertStringNotContainsString('script', strtolower($isi));
        $this->assertStringNotContainsString('alert', $isi);
    }

    public function test_pemformatan_yang_sah_dipertahankan(): void
    {
        /* Sanitasi yang membuang segalanya sama tak bergunanya dengan yang tak
           membuang apa pun — hasil review memang ditulis dengan penekanan. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Hasil_Review' => '<p><strong>Wajib</strong> diperbaiki</p><ul><li>Poin satu</li></ul>',
        ]], 1);

        $isi = (string) DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Hasil_Review');

        $this->assertStringContainsString('<strong>', $isi);
        $this->assertStringContainsString('<li>', $isi);
    }

    /* ── Berulang & ditulis ulang ───────────────────────────────────────── */

    public function test_beberapa_putaran_review_tersimpan_berurutan(): void
    {
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [
            ['Review_Ke' => 1, 'Tanggal_Review' => '2026-01-10', 'Hasil_Review' => '<p>Putaran satu</p>'],
            ['Review_Ke' => 2, 'Tanggal_Review' => '2026-02-20', 'Hasil_Review' => '<p>Putaran dua</p>'],
            ['Review_Ke' => 3, 'Tanggal_Review' => '2026-03-30', 'Hasil_Review' => '<p>Putaran tiga</p>'],
        ], 1);

        $daftar = $this->svc()->daftar($idVersi);

        $this->assertCount(3, $daftar);
        $this->assertSame([1, 2, 3], array_column($daftar, 'Review_Ke'));
        $this->assertStringContainsString('Putaran dua', $daftar[1]['Hasil_Review']);
    }

    public function test_daftar_ditulis_ulang_bukan_ditambahi(): void
    {
        /* Form mengirim daftar yang DIMAKSUD. Menambah saja akan meninggalkan
           review yang sudah dihapus admin, dan ia muncul lagi setiap kali
           layar dimuat. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [
            ['Review_Ke' => 1, 'Hasil_Review' => '<p>Satu</p>'],
            ['Review_Ke' => 2, 'Hasil_Review' => '<p>Dua</p>'],
        ], 1);
        $this->svc()->simpan($idVersi, [
            ['Review_Ke' => 1, 'Hasil_Review' => '<p>Satu direvisi</p>'],
        ], 1);

        $daftar = $this->svc()->daftar($idVersi);

        $this->assertCount(1, $daftar);
        $this->assertStringContainsString('direvisi', $daftar[0]['Hasil_Review']);
    }

    public function test_baris_lama_dinonaktifkan_bukan_dihapus(): void
    {
        /* Lampirannya menunjuk berkas di GCS; baris yang lenyap membuat berkas
           itu yatim tanpa jejak siapa pun. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [['Review_Ke' => 1, 'Hasil_Review' => '<p>Awal</p>']], 1);
        $this->svc()->simpan($idVersi, [], 1);

        $this->assertSame(0, count($this->svc()->daftar($idVersi)));
        $this->assertGreaterThan(0,
            DB::table('N_SOP_Item_Versi_Review')->where('Id_SOP_Item_Versi', $idVersi)->count(),
            'Barisnya harus tetap ada dengan Flag_Aktif = T.');
    }

    public function test_nomor_putaran_dihormati_bukan_diurut_ulang(): void
    {
        /* "Review 2" tetap bernama Review 2 setelah Review 1 dihapus — nomornya
           menunjuk putaran, bukan posisi baris. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [
            ['Review_Ke' => 2, 'Hasil_Review' => '<p>Putaran dua</p>'],
            ['Review_Ke' => 5, 'Hasil_Review' => '<p>Putaran lima</p>'],
        ], 1);

        $this->assertSame([2, 5], array_column($this->svc()->daftar($idVersi), 'Review_Ke'));
    }

    /* ── Lampiran ───────────────────────────────────────────────────────── */

    public function test_lampiran_berupa_path_diteruskan_tanpa_diunggah_ulang(): void
    {
        /* Menyunting review berkali-kali tak boleh mengunggah ulang gambar yang
           sama — itulah sebabnya path yang sudah tersimpan dikirim balik apa
           adanya, bukan sebagai data URI. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Hasil_Review' => '<p>Ada lampiran</p>',
            'Lampiran' => [['Berkas' => 'sop/review-lampiran/2026/01/contoh.png', 'Nama_Berkas' => 'contoh.png']],
        ]], 1);

        $daftar = $this->svc()->daftar($idVersi);

        $this->assertCount(1, $daftar[0]['Lampiran']);
        $this->assertSame('sop/review-lampiran/2026/01/contoh.png', $daftar[0]['Lampiran'][0]['Path_Berkas']);
    }

    public function test_lampiran_berjenis_terlarang_ditolak_tanpa_membuang_catatannya(): void
    {
        /* Satu berkas bermasalah tak boleh membuang catatan review yang sudah
           diketik panjang — ditolak per berkas, bukan menggagalkan penyimpanan. */
        $idVersi = $this->versiBaru();

        $this->svc()->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Hasil_Review' => '<p>Catatan panjang yang tak boleh hilang</p>',
            'Lampiran' => [['Berkas' => 'data:image/svg+xml;base64,'.base64_encode('<svg/>')]],
        ]], 1);

        $daftar = $this->svc()->daftar($idVersi);

        $this->assertCount(1, $daftar, 'Catatannya tetap tersimpan.');
        $this->assertStringContainsString('tak boleh hilang', $daftar[0]['Hasil_Review']);
        $this->assertSame([], $daftar[0]['Lampiran'], 'SVG bukan jenis yang diizinkan.');
    }

    public function test_url_lampiran_yang_sudah_berupa_url_diteruskan_apa_adanya(): void
    {
        $this->assertSame('https://contoh/x.png', SopVersiReviewService::lampiranUrl('https://contoh/x.png'));
        $this->assertNull(SopVersiReviewService::lampiranUrl(null));
    }

    /* ── Pintu HTTP ─────────────────────────────────────────────────────── */

    private function adminMaster(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    public function test_endpoint_menyimpan_dan_membaca_kembali(): void
    {
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $this->actingAs($this->adminMaster())
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$enc}/review", [
                'Review' => [
                    ['Review_Ke' => 1, 'Tanggal_Review' => '2026-04-01', 'Hasil_Review' => '<p>Lewat pintu</p>'],
                ],
            ])
            ->assertStatus(200);

        $res = $this->actingAs($this->adminMaster())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/review");

        $res->assertStatus(200);
        $this->assertSame(1, $res->json('result.0.Review_Ke'));
        $this->assertStringContainsString('Lewat pintu', $res->json('result.0.Hasil_Review'));
    }

    public function test_endpoint_ikut_menyanitasi(): void
    {
        /* Sanitasi hidup di service, jadi ia berlaku bagi SEMUA pemanggil —
           termasuk yang tak pernah menyentuh editor di layar. */
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $this->actingAs($this->adminMaster())
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$enc}/review", [
                'Review' => [['Review_Ke' => 1, 'Hasil_Review' => '<img src=x onerror=alert(1)><p>Isi</p>']],
            ])
            ->assertStatus(200);

        $res = $this->actingAs($this->adminMaster())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/review");

        $this->assertStringNotContainsString('<img', (string) $res->json('result.0.Hasil_Review'));
        $this->assertStringNotContainsString('onerror', (string) $res->json('result.0.Hasil_Review'));
    }
}
