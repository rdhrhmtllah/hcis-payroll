<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopSecurePdfAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Pratinjau berkas di layar admin punya PINTU SENDIRI.
 *
 * Master Dokumen memakai pembaca PDF yang sama dengan karyawan, tapi tak boleh
 * memakai otorisasi yang sama. Lensa karyawan (canAccess) menuntut versi
 * PUBLISHED pada paket yang ditugaskan; yang dibuka admin justru DRAFT yang
 * sedang disusun dan OBSOLETE yang sedang ditinjau. Selama pratinjau admin
 * menumpang rute karyawan, ia SELALU berbalas 403 — tanpa satu pun kesalahan
 * data, sehingga terbaca seperti kerusakan.
 *
 * Yang dijaga pintu admin bukan "berhak membaca SOP ini" melainkan batas
 * modulnya: berkas harus milik konten ber-Scope SOP. Kewenangan halamannya
 * sudah diperiksa gerbang rute.
 */
class SopPratinjauAdminPdfTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Berkas SOP yang TIDAK dipakai satu pun versi terbit.
     *
     * Inilah bentuk yang tadi 403: konten yang hanya dirujuk versi DRAFT dan
     * OBSOLETE. Sengaja dicari yang begini, bukan berkas SOP sembarang —
     * berkas yang kebetulan punya versi terbit akan lolos lensa karyawan juga,
     * sehingga ujinya tak membuktikan apa pun.
     */
    private function berkasTanpaVersiTerbit(): int
    {
        $id = DB::table('N_LMS_Master_Konten_Berkas as b')
            ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where('k.Scope', 'SOP')
            ->whereExists(fn ($q) => $q->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_Master_Konten', 'b.Id_Master_Konten'))
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Item_Versi as v2')
                ->whereColumn('v2.Id_Master_Konten', 'b.Id_Master_Konten')
                ->where('v2.Status', 'PUBLISHED'))
            ->whereNotNull('b.File_Path')
            ->orderByDesc('b.Id_Master_Konten_Berkas')
            ->value('b.Id_Master_Konten_Berkas');

        if (! $id) {
            $this->markTestSkipped('Tak ada berkas dokumen yang hanya dirujuk versi draft/cabutan.');
        }

        return (int) $id;
    }

    /** User yang benar-benar memegang kunci halaman Master Dokumen. */
    private function adminMaster(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    /* ── pintu admin ───────────────────────────────────────────────── */

    public function test_pintu_admin_menerima_berkas_sop_tanpa_versi_terbit(): void
    {
        $this->assertTrue(
            app(SopSecurePdfAccessService::class)->bolehAdmin($this->berkasTanpaVersiTerbit()),
            'Pratinjau admin ditolak untuk berkas yang justru cuma bisa dilihat admin.',
        );
    }

    /**
     * Batas modul tetap dijaga: rute admin SOP bukan pintu ke berkas LMS.
     *
     * Tanpa syarat Scope, siapa pun yang memegang kunci halaman SOP bisa
     * memanen seluruh materi pelatihan lewat rute ini.
     */
    public function test_pintu_admin_menolak_berkas_di_luar_scope_sop(): void
    {
        $lms = DB::table('N_LMS_Master_Konten_Berkas as b')
            ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where(fn ($q) => $q->where('k.Scope', '<>', 'SOP')->orWhereNull('k.Scope'))
            ->value('b.Id_Master_Konten_Berkas');

        if (! $lms) {
            $this->markTestSkipped('Tak ada berkas non-SOP untuk diuji.');
        }

        $this->assertFalse(
            app(SopSecurePdfAccessService::class)->bolehAdmin((int) $lms),
            'Rute admin SOP membuka berkas milik LMS.',
        );
    }

    /* ── dua pintu, dua jawaban ────────────────────────────────────── */

    /**
     * Yang membuktikan pintunya memang harus terpisah.
     *
     * Berkas yang sama: pintu admin melayani, pintu karyawan menolak. Kalau
     * suatu saat keduanya menjawab sama, salah satu lensanya sudah bergeser —
     * entah pratinjau admin kembali mati, atau lensa karyawan bocor.
     */
    public function test_berkas_yang_sama_ditolak_lensa_karyawan(): void
    {
        $berkas = $this->berkasTanpaVersiTerbit();
        $svc = app(SopSecurePdfAccessService::class);

        $kode = DB::table('N_SOP_Penjadwalan_Detail')->where('Flag_Aktif', 'Y')->value('Kode_Karyawan');
        if (! $kode) {
            $this->markTestSkipped('Tak ada penugasan aktif untuk diuji.');
        }

        $this->assertFalse($svc->canAccess(trim($kode), $berkas),
            'Lensa karyawan melayani berkas yang tak punya versi terbit.');
        $this->assertTrue($svc->bolehAdmin($berkas),
            'Pintu admin ikut menolak — pratinjau Master Dokumen mati lagi.');
    }

    /* ── lewat HTTP, bukan lewat servicenya saja ───────────────────── */

    public function test_rute_admin_menerbitkan_handshake(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $hash = Hashids::connection('custom')->encode($this->berkasTanpaVersiTerbit());

        $res = $this->actingAs($this->adminMaster())
            ->withHeader('X-App-Source', 'vue-client')
            ->postJson("/api/v1/sop/admin/secure-pdf/token/{$hash}");

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('result.signature'), 'Handshake tanpa signature tak bisa dipakai membaca.');
        $this->assertNotEmpty($res->json('result.page_manifest_url'));
    }

    /**
     * Gerbangnya SATU pendaftaran dengan DUA kunci, bukan dua pendaftaran.
     *
     * URI yang sama didaftar dua kali di dua grup middleware akan saling
     * menimpa: yang tersisa hanya gerbang terakhir, dan pemegang kunci yang
     * lain justru tertolak. Cacatnya tak terlihat di layar mana pun sampai
     * seseorang yang cuma punya satu kunci mencoba membuka pratinjau.
     */
    public function test_gerbangnya_menerima_kedua_kunci_admin(): void
    {
        $cocok = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/sop/admin/secure-pdf/token/{berkasId}')
            ->values();

        $this->assertCount(1, $cocok,
            'Rute pratinjau admin didaftar lebih dari sekali — pendaftaran terakhir menimpa yang lain.');

        $mw = implode(' ', $cocok[0]->gatherMiddleware());
        $this->assertStringContainsString('menuSopDokumenPage', $mw);
        $this->assertStringContainsString('menuSopBuilderPage', $mw,
            'Builder kehilangan akses pratinjau — aturan yang cuma dipasang di satu pintu.');
    }
}
