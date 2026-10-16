<?php

namespace Tests\Integration\SYSTEM\Governance;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lingkup wewenang: System Admin, Super Admin, dan batas delegasinya.
 *
 * BERKAS INI TIDAK PERNAH SEKALI PUN BERJALAN sebelum diperbaiki. Ia ditulis
 * terhadap skema yang tak pernah ada — id_role_functional, nama_role, id_user,
 * id_grup_fitur — sedangkan tabelnya memakai Id_Role, Role_Name, UserId_Web,
 * Id_Grup_Fitur. Kegagalan pertamanya menyamar sebagai masalah factory, dan
 * karena berhenti di baris pertama, sisanya tak pernah terlihat.
 *
 * ISOLASI TRANSAKSI ITU WAJIB, dan sebelumnya tidak ada. Berkas ini membuat
 * pengguna, membuat peran fungsional, lalu MEMBERIKAN peran itu kepada
 * penggunanya — tiga tabel otorisasi yang dipakai bersama seluruh HCIS. Tanpa
 * rollback, setiap kali suite berjalan basis data dev bertambah satu rombongan
 * pengguna karangan yang benar-benar memegang peran. Selama ini itu tak terjadi
 * hanya karena kebetulan factory-nya rusak; memperbaiki factory tanpa memasang
 * isolasi ini akan MEMULAI pengotoran itu, bukan mencegahnya.
 *
 * BERKAS INI BUKAN UNIT TEST, dan dulu tinggal di tests/Unit. Ia menyambung ke
 * basis data sungguhan: nomor grup dibaca dari N_HRIS_Master_Grup_Fitur saat
 * berjalan, dan isolasinya bersandar pada transaksi milik koneksi. CI tidak
 * punya basis data, jadi di sana kesembilan ujinya jatuh pada PDO — bukan
 * karena kode yang diuji salah, melainkan karena suite Unit dipakai CI sebagai
 * suite yang harus jalan tanpa layanan apa pun. Selama ia butuh koneksi,
 * tempatnya di sini.
 */
class AuthorizationScopeResolverTest extends TestCase
{
    use DatabaseTransactions;

    /** Grup fitur yang DIMILIKI pengguna uji, dan grup yang bukan miliknya. */
    private int $grupMilik;

    private int $grupAsing;

    protected function setUp(): void
    {
        parent::setUp();

        /* Nomor grup diambil dari master saat berjalan, bukan ditulis tetap.
           Versi lama memakai 10, 12, dan 99 — ketiganya tak pernah ada; master
           hanya berisi empat grup, dan foreign key menolak setiap penyisipan.
           Angka yang dikarang membuat uji gagal pada penyiapannya sendiri, dan
           kegagalan itu terbaca seolah datang dari kode yang diuji. */
        $grup = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Grup_Fitur')
            ->pluck('Id_Grup_Fitur');

        if ($grup->count() < 2) {
            $this->markTestSkipped(
                'Butuh minimal dua grup fitur aktif untuk menguji batas "grup yang bukan milikmu".'
            );
        }

        $this->grupMilik = (int) $grup[0];
        $this->grupAsing = (int) $grup[1];

        /* Resolver menyimpan lingkup per proses, berkunci id pengguna. Id itu
           berasal dari IDENTITY yang terus naik, jadi bentrokan antar-test tak
           mungkin — tapi sekali dibersihkan di sini, satu sumber kebingungan
           hilang seluruhnya bila suatu saat kuncinya berubah. */
        AuthorizationScopeResolver::clearAllCache();
    }

    /**
     * Peran yang benar-benar memberi wewenang kelola-grup, lalu diberikan ke user.
     *
     * TIGA baris, bukan dua. Memberi peran saja TIDAK cukup: resolver hanya
     * menghitung peran yang punya entri N_HRIS_Role_Resource_Access pada halaman
     * administratif (menuApprovalMatrixPage / systemAuthManagerPage). Peran
     * operasional biasa tidak memberi hak mengelola grupnya. Uji versi lama
     * melewatkan syarat ini, sehingga mengandaikan aturan yang tak pernah ada.
     *
     * Perannya DIBUAT SENDIRI, tidak meminjam peran yang sudah ada di data dev.
     * Meminjam membuat hasil uji bergantung pada isi basis data, dan menautkan
     * peran sungguhan ke pengguna karangan.
     */
    private function beriPeranPengelola(User $user, int $idGrupFitur, string $flagAktifPemberian = 'Y'): int
    {
        $idRole = DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI-'.$idGrupFitur.'-'.Str::upper(Str::random(6)),
            'Role_Name' => "Peran Uji Grup {$idGrupFitur}",
            'Flag_Aktif' => 'Y',
            'Id_Grup_Fitur' => $idGrupFitur,
            'Created_At' => now(),
        ], 'Id_Role');

        DB::table('N_HRIS_Role_Resource_Access')->insert([
            'Id_Role_Functional' => $idRole,
            'Context_Code' => 'admin',
            'Resource_Type' => 'tab',
            'Resource_Code' => 'uji-lingkup',
            'Flag_Diizinkan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jenis_page' => 'systemAuthManagerPage',
        ]);

        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $user->Id_Users,
            'Id_Role' => $idRole,
            'Flag_Aktif' => $flagAktifPemberian,
        ]);

        return (int) $idRole;
    }

    /**
     * Halaman milik satu grup fitur; yang dikembalikan NAMA Jenis_page-nya.
     *
     * Bukan Id_Grup_Map. canDelegatePage() menafsirkan argumen numerik sebagai
     * HRIS_Page_Access.Id_Page_Access — tabel yang sama sekali lain — sehingga
     * menyodorkan Id_Grup_Map ke sana selalu berakhir "halaman tak ditemukan",
     * lalu terbaca sebagai penolakan wewenang. Uji versi lama tersandung ini,
     * dan saya mengulanginya sekali sebelum menyadarinya.
     */
    private function buatHalaman(int $idGrupFitur): string
    {
        $jenis = 'ujiHalaman'.$idGrupFitur.Str::upper(Str::random(4));

        DB::table('N_HRIS_Grup_Fitur_Map')->insert([
            'Id_Grup_Fitur' => $idGrupFitur,
            'Jenis_page' => $jenis,
            'Is_Default_Landing' => 'T',
            'Is_Show_Sidebar' => 'Y',
            'Flag_Aktif' => 'Y',
        ]);

        return $jenis;
    }

    /**
     * Grup yang boleh dikelola, sebagai integer.
     *
     * Resolver mengembalikannya sebagai STRING — driver sqlsrv menyerahkan
     * angka apa adanya, dan resolver meneruskannya tanpa mengubah tipe.
     * Selama pembanding di sisi lain juga datang dari basis data, perbandingan
     * ketat di dalam resolver tetap cocok. Yang berisiko pemanggil yang
     * menyodorkan integer: ApprovalMatrixService sudah memasang
     * array_map('intval', ...) sebelum in_array ketatnya, jadi bahayanya bukan
     * dugaan. Uji ini menyamakan tipe di sini, bukan menuntut resolver berubah.
     */
    private function grupTerkelola(array $scope): array
    {
        return array_map('intval', $scope['manageableGroupIds'] ?? []);
    }

    private function pengguna(string $systemAdmin): User
    {
        return User::factory()->create(['Is_System_Admin' => $systemAdmin]);
    }

    public function test_system_admin_punya_lingkup_penuh(): void
    {
        $scope = AuthorizationScopeResolver::resolve($this->pengguna('Y'));

        $this->assertTrue($scope['isSystemAdmin']);
        $this->assertNull($scope['manageableGroupIds'],
            'null berarti TANPA BATAS. Array kosong berarti "tidak boleh apa-apa" — '
            .'dua keadaan yang berlawanan arti, dan mudah tertukar.');
        $this->assertNull($scope['usablePageTypes']);
    }

    public function test_super_admin_dengan_satu_grup(): void
    {
        $user = $this->pengguna('T');
        $this->beriPeranPengelola($user, $this->grupMilik);

        $scope = AuthorizationScopeResolver::resolve($user);

        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertIsArray($scope['manageableGroupIds']);
        $this->assertContains($this->grupMilik, $this->grupTerkelola($scope));
        $this->assertIsArray($scope['usablePageTypes']);
    }

    public function test_super_admin_dengan_beberapa_grup(): void
    {
        $user = $this->pengguna('T');
        $this->beriPeranPengelola($user, $this->grupMilik);
        $this->beriPeranPengelola($user, $this->grupAsing);

        $scope = AuthorizationScopeResolver::resolve($user);

        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertContains($this->grupMilik, $this->grupTerkelola($scope));
        $this->assertContains($this->grupAsing, $this->grupTerkelola($scope));
    }

    public function test_super_admin_tanpa_peran_tidak_mengelola_apa_pun(): void
    {
        $scope = AuthorizationScopeResolver::resolve($this->pengguna('T'));

        $this->assertFalse($scope['isSystemAdmin']);
        $this->assertEmpty($scope['manageableGroupIds']);
        $this->assertIsArray($scope['usablePageTypes']);
    }

    public function test_pemberian_peran_nonaktif_tidak_dihitung(): void
    {
        $user = $this->pengguna('T');
        $this->beriPeranPengelola($user, $this->grupMilik, 'T');

        $scope = AuthorizationScopeResolver::resolve($user);

        $this->assertEmpty($scope['manageableGroupIds'],
            'Pemberian peran yang sudah dicabut masih memberi wewenang mengelola grup.');
    }

    public function test_delegasi_peran_dibatasi_grup_yang_dimiliki(): void
    {
        $user = $this->pengguna('T');
        $peranMilik = $this->beriPeranPengelola($user, $this->grupMilik);

        // Peran di grup lain, yang TIDAK diberikan kepada user ini.
        $peranAsing = DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI-ASING-'.Str::upper(Str::random(6)),
            'Role_Name' => 'Peran Uji Grup Lain',
            'Flag_Aktif' => 'Y',
            'Id_Grup_Fitur' => $this->grupAsing,
            'Created_At' => now(),
        ], 'Id_Role');

        $this->assertTrue(AuthorizationScopeResolver::canDelegateRole($user, $peranMilik));
        $this->assertFalse(AuthorizationScopeResolver::canDelegateRole($user, $peranAsing),
            'Super Admin bisa mendelegasikan peran di grup yang bukan miliknya — '
            .'wewenang menyebar ke luar batas yang diberikan kepadanya.');
    }

    public function test_delegasi_halaman_dibatasi_grup_yang_dimiliki(): void
    {
        $user = $this->pengguna('T');
        $this->beriPeranPengelola($user, $this->grupMilik);

        $this->assertTrue(AuthorizationScopeResolver::canDelegatePage($user, $this->buatHalaman($this->grupMilik)));
        $this->assertFalse(AuthorizationScopeResolver::canDelegatePage($user, $this->buatHalaman($this->grupAsing)),
            'Super Admin bisa mendelegasikan halaman di grup yang bukan miliknya.');
    }

    public function test_system_admin_boleh_mendelegasikan_apa_pun(): void
    {
        $user = $this->pengguna('Y');

        $peran = DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI-BEBAS-'.Str::upper(Str::random(6)),
            'Role_Name' => 'Peran Uji Mana Saja',
            'Flag_Aktif' => 'Y',
            'Id_Grup_Fitur' => $this->grupAsing,
            'Created_At' => now(),
        ], 'Id_Role');

        $this->assertTrue(AuthorizationScopeResolver::canDelegateRole($user, $peran));
        $this->assertTrue(AuthorizationScopeResolver::canDelegatePage($user, $this->buatHalaman($this->grupMilik)));
    }

    public function test_lingkup_sesudah_cache_dibersihkan_tetap_sama(): void
    {
        $user = $this->pengguna('T');
        $this->beriPeranPengelola($user, $this->grupMilik);

        $awal = AuthorizationScopeResolver::resolve($user);
        AuthorizationScopeResolver::clearCache($user);
        $ulang = AuthorizationScopeResolver::resolve($user);

        $this->assertEquals($awal, $ulang);
        $this->assertContains($this->grupMilik, $this->grupTerkelola($ulang),
            'Pembersihan cache mengosongkan lingkup, bukan menghitungnya ulang.');
    }
}
