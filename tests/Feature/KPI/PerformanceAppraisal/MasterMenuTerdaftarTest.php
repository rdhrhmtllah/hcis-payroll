<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaMasterReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master PA yang dilayani server harus punya pintu — menu, pemetaan grup, dan hak akses.
 *
 * ── Kenapa dijaga ─────────────────────────────────────────────────────────────
 *
 * Halaman master di modul ini lahir dari empat bagian yang terpisah tempatnya: katalog di
 * `PaMasterReadService`, daftar izin di `paApi.js`, tab di `master.vue`, dan baris menu di
 * basis data. Tiga yang pertama ada di dalam repositori dan ikut ter-review; yang keempat
 * hidup di `N_HRIS_Menus` dan tidak ikut ke mana-mana. Halaman yang lengkap di tiga tempat
 * dan kosong di tempat keempat TIDAK akan memerahkan uji apa pun — ia hanya diam, dan
 * satu-satunya cara membukanya adalah mengetik URL-nya.
 *
 * Itu yang terjadi pada `kategori-penilai`: backend, penulis, daftar izin, fixture, dan
 * uji halamannya sudah ada sejak lama, sementara `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`,
 * dan `HRIS_Page_Access` ketiganya NOL. Ketahuan hanya karena ada yang bertanya kenapa
 * menunya tidak ada. DML-2026-119 memberinya pintu; uji ini menjaga supaya master
 * berikutnya tidak mengulanginya.
 *
 * ── Kenapa ada daftar pengecualian ────────────────────────────────────────────
 *
 * `kuadran-hav` memang belum punya menu, dan itu keadaan yang sudah ada sebelum penjaga ini
 * ditulis — bukan sesuatu yang boleh bertambah. Daftarnya DIBEKUKAN: master baru tanpa menu
 * membuat uji ini merah, dan `kuadran-hav` yang kelak diberi menu JUGA membuatnya merah
 * sampai barisnya dihapus dari daftar. Utangnya tidak bisa bertambah diam-diam dan tidak
 * bisa lenyap dari catatan.
 *
 * ── Cara membuatnya merah ─────────────────────────────────────────────────────
 *
 * Nonaktifkan baris menu `PaMasterKategoriPenilaiPage`, atau tambah entri `lingkup=master`
 * baru di `PaMasterReadService::KATALOG` tanpa DML menunya.
 */
class MasterMenuTerdaftarTest extends TestCase
{
    /**
     * Master berlingkup `master` yang SENGAJA belum punya menu.
     * Jangan tambah baris di sini: buatkan DML menunya.
     */
    private const BELUM_BERMENU = ['kuadran-hav'];

    /** @return array<string, array<string, mixed>> */
    private function masterBerlingkupMaster(): array
    {
        $katalog = (new \ReflectionClass(PaMasterReadService::class))->getConstant('KATALOG');

        $this->assertIsArray($katalog, 'Katalog master tidak terbaca — penjaga ini tak menguji apa pun.');

        return array_filter(
            $katalog,
            fn ($k) => is_array($k) && ($k['lingkup'] ?? null) === 'master' && isset($k['halaman']),
        );
    }

    public function test_setiap_master_punya_baris_menu_di_sidebar(): void
    {
        $tanpaMenu = [];

        foreach ($this->masterBerlingkupMaster() as $slug => $k) {
            $ada = DB::table('N_HRIS_Menus')
                ->where('Jenis_page', $k['halaman'])
                ->where('Is_Show_Sidebar', 'Y')
                ->exists();

            if (! $ada) {
                $tanpaMenu[] = $slug;
            }
        }

        $baru = array_values(array_diff($tanpaMenu, self::BELUM_BERMENU));

        $this->assertSame([], $baru,
            'Master ini dilayani server tetapi tidak punya baris menu: satu-satunya cara membukanya '
            .'adalah mengetik URL-nya. Buatkan DML menunya, jangan tambahkan ke BELUM_BERMENU.');

        /* Sisi ratchet: yang sudah diberi menu harus keluar dari daftar, supaya daftar ini
           tidak menjadi tempat sampah abadi. */
        $sudahBeres = array_values(array_diff(self::BELUM_BERMENU, $tanpaMenu));

        $this->assertSame([], $sudahBeres,
            'Master ini sudah punya menu. Hapus barisnya dari BELUM_BERMENU.');
    }

    public function test_master_bermenu_juga_punya_pemetaan_grup_dan_hak_akses(): void
    {
        /* Menu tanpa dua ini adalah menu yang terdaftar tetapi tidak terlihat oleh siapa pun:
           `N_HRIS_Grup_Fitur_Map` yang menentukan ia muncul di grup fitur mana, dan
           `HRIS_Page_Access` yang menentukan siapa boleh membukanya. Ketiganya lahir dari
           DML yang sama, dan yang paling mudah terlupa justru dua yang belakangan. */
        $cacat = [];

        foreach ($this->masterBerlingkupMaster() as $slug => $k) {
            if (in_array($slug, self::BELUM_BERMENU, true)) {
                continue;
            }

            if (! DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', $k['halaman'])->where('Flag_Aktif', 'Y')->exists()) {
                $cacat[] = "{$slug}: tidak ada N_HRIS_Grup_Fitur_Map aktif";
            }

            if (! DB::table('HRIS_Page_Access')->where('Jenis_page', $k['halaman'])->where('Flag_Aktif', 'Y')->exists()) {
                $cacat[] = "{$slug}: tidak ada HRIS_Page_Access aktif";
            }
        }

        $this->assertSame([], $cacat,
            'Menu ada tetapi tidak akan terlihat: pemetaan grup fitur atau hak aksesnya kosong.');
    }

    public function test_jenis_penilai_tertutup_untuk_tambah_dan_nonaktif(): void
    {
        /* Menunya baru dibuka, jadi penutupannya perlu ditegaskan DI SINI — bukan
           diandaikan. Keempat kodenya disebut langsung di belasan tempat lintas service;
           kategori baru tanpa aturan di setiap level tidak akan pernah muncul, dan
           menonaktifkan salah satunya memutus aturan siklus yang sedang berjalan. */
        $k = PaMasterReadService::katalog('kategori-penilai');

        $this->assertFalse($k['dapat_ditambah'], 'Kategori penilai tidak boleh bisa ditambah dari layar.');
        $this->assertFalse($k['dapat_dinonaktifkan'], 'Kategori penilai tidak boleh bisa dinonaktifkan dari layar.');
    }
}
