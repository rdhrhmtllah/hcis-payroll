<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Halaman Prioritas SOP berdiri di kunci izinnya sendiri.
 *
 * Halaman ini sejak awal punya controller, service, halaman Vue, dan rute
 * sendiri. Yang tak pernah ada adalah TIGA BARIS pendaftarannya di database
 * (N_HRIS_Menus, N_HRIS_Grup_Fitur_Map, HRIS_Page_Access), sehingga rutenya
 * terpaksa ikut menerima menuSopKategoriPage sebagai tongkat — kalau tidak,
 * halaman yang sudah jadi itu menolak semua orang.
 *
 * Utang itu tak terlihat selama Kategori masih hidup. Begitu Kategori dicabut,
 * ia langsung berubah jadi 403 untuk semua orang. Uji ini yang menjaga tongkat
 * itu benar-benar sudah dilepas, dan menjaganya tidak diam-diam dipasang lagi
 * oleh orang yang bingung kenapa halamannya menolak.
 *
 * KENAPA DUA BENTUK UJI. Klaim "kunci Kategori tidak lagi diterima" adalah
 * klaim tentang deklarasi rutenya, jadi ia diuji pada deklarasi rute. Menguji-
 * nya lewat permintaan HTTP mustahil jujur: sesudah DML tahap 1, setiap
 * pemegang kunci Kategori JUGA memegang kunci Prioritas, sehingga permintaan
 * yang lolos tak membuktikan kunci mana yang meloloskannya.
 */
class SopIzinPrioritasMandiriTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    /** Middleware check.jabatan pada satu nama rute, sebagai daftar kunci. */
    private function kunciRute(string $nama): array
    {
        $rute = Route::getRoutes()->getByName($nama);
        $this->assertNotNull($rute, "Rute {$nama} tidak ditemukan.");

        foreach ($rute->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'check.jabatan:')) {
                return array_map('trim', explode(',', substr($m, strlen('check.jabatan:'))));
            }
        }

        $this->fail("Rute {$nama} tidak dijaga check.jabatan sama sekali.");
    }

    /**
     * Kunci Kategori tidak lagi membuka halaman Prioritas.
     *
     * Ini penjaga arah-MERAH-nya: sebelum tongkatnya dilepas, daftar kunci
     * rute ini memuat menuSopKategoriPage dan asersi ini gagal.
     */
    public function test_kunci_kategori_tidak_lagi_membuka_prioritas(): void
    {
        $kunci = $this->kunciRute('sop.admin.prioritas');

        $this->assertNotContains('menuSopKategoriPage', $kunci,
            'Rute Prioritas masih menerima kunci Kategori. Tongkatnya belum dilepas, '
            .'dan mencabut Kategori akan mematikan halaman ini.');
        $this->assertSame(['menuSopPrioritasPage'], $kunci,
            'Prioritas harus dijaga kuncinya sendiri, satu-satunya.');
    }

    /**
     * Baris pendaftarannya benar-benar ada di database.
     *
     * Tanpa ketiganya kunci Prioritas tak dipegang siapa pun, dan rute yang
     * sudah dimandirikan di atas berubah jadi 403 untuk seluruh organisasi.
     * Pemetaan grup fitur yang paling mudah terlewat: sidebar dibangun dari
     * JOIN Grup_Fitur_Map, jadi baris menu yang tak terpetakan tersaring habis
     * tanpa galat apa pun.
     */
    public function test_tiga_baris_pendaftarannya_tertanam(): void
    {
        $this->assertSame(1,
            DB::table('N_HRIS_Menus')->where('Jenis_page', 'menuSopPrioritasPage')->count(),
            'Baris menu menuSopPrioritasPage belum ditanam.');

        $peta = DB::table('N_HRIS_Grup_Fitur_Map')
            ->where('Jenis_page', 'menuSopPrioritasPage')->first();
        $this->assertNotNull($peta, 'Pemetaan grup fitur belum ada — menunya tak akan pernah muncul di sidebar.');
        $this->assertSame('Y', $peta->Flag_Aktif);

        $this->assertGreaterThan(0,
            DB::table('HRIS_Page_Access')
                ->where('Jenis_page', 'menuSopPrioritasPage')
                ->where('Flag_Aktif', 'Y')->count(),
            'Tak seorang pun memegang menuSopPrioritasPage.');
    }

    /**
     * Prioritas dipetakan ke grup fitur yang SAMA dengan halaman SOP lainnya.
     *
     * Skrip yang menanamnya menyalin Id_Grup_Fitur dari baris Kategori, jadi
     * angkanya tak ditulis tangan. Yang dijaga di sini bukan angka 7-nya
     * melainkan kesamaannya: satu saja yang meleset membuat menunya lahir di
     * kelompok yang salah, atau tak muncul sama sekali.
     */
    public function test_dipetakan_ke_grup_yang_sama_dengan_halaman_sop_lain(): void
    {
        $grupPrioritas = DB::table('N_HRIS_Grup_Fitur_Map')
            ->where('Jenis_page', 'menuSopPrioritasPage')->value('Id_Grup_Fitur');

        $grupTetangga = DB::table('N_HRIS_Grup_Fitur_Map')
            ->where('Jenis_page', 'menuSopBuilderPage')->value('Id_Grup_Fitur');

        $this->assertNotNull($grupTetangga, 'Halaman SOP pembanding tak ditemukan.');
        $this->assertSame((int) $grupTetangga, (int) $grupPrioritas,
            'Prioritas dipetakan ke grup fitur yang berbeda dari halaman SOP lainnya.');
    }

    /** Halamannya benar-benar terbuka bagi pemegang kunci barunya. */
    public function test_pemegang_kunci_prioritas_bisa_membuka_halamannya(): void
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'menuSopPrioritasPage')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $uid ? User::find($uid) : null;
        $this->assertNotNull($user, 'Tak ada user pemegang menuSopPrioritasPage — uji ini tak menguji apa pun.');

        $this->actingAs($user)->get('/sop/admin/prioritas')->assertOk();
    }
}
