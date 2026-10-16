<?php

namespace Tests\Feature\ESS\Employee;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminPengumpulanDataHalamanTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGE = 'adminPengumpulanDataPage';

    protected function setUp(): void
    {
        parent::setUp();

        // Lihat catatan di PengumpulanDataHalamanTest: APP_URL di .env dev tanpa
        // skema membuat setiap permintaan test jatuh ke 404.
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        $this->lupakanCacheAkses();
    }

    /**
     * User::$menuAccessCache adalah properti STATIC yang hidup selama proses PHP.
     * Di produksi tiap permintaan punya prosesnya sendiri, tapi satu proses di
     * sini menjalankan banyak test — tanpa reset, hasil pemeriksaan akses bocor
     * antar test ke DUA arah: test negatif bisa lulus palsu karena membawa
     * pemberian akses milik test lain, dan sebaliknya.
     */
    private function lupakanCacheAkses(): void
    {
        $cache = new \ReflectionProperty(User::class, 'menuAccessCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    private function user(): User
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereNotNull('UserID_Web')
            ->first(['UserID_Web']);

        $this->assertNotNull($karyawan);

        return User::where('Id_Users', $karyawan->UserID_Web)->firstOrFail();
    }

    private function beriAkses(User $user): void
    {
        // HRIS_Page_Access memberi akses lewat dua jalur: UserID_Web langsung,
        // atau pasangan ID_Divisi + ID_Level. Keduanya kolom NOT NULL, jadi
        // nilainya diambil dari karyawan yang bersangkutan supaya jalur mana pun
        // yang dibaca sistem tetap cocok.
        $konteks = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.UserID_Web', $user->Id_Users)
            ->first(['vds.ID_Divisi', 'lvl.ID_Level']);

        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001',
            'Jenis_page' => self::PAGE,
            'UserID_Web' => $user->Id_Users,
            'ID_Divisi' => $konteks->ID_Divisi ?? 0,
            'ID_Level' => $konteks->ID_Level ?? 0,
            'Flag_Aktif' => 'Y',
        ]);

        $this->lupakanCacheAkses();
    }

    public function test_pengguna_tanpa_akses_ditolak(): void
    {
        $this->actingAs($this->user())
            ->get('/admin/pengumpulan-data')
            ->assertForbidden();
    }

    /**
     * Versi aset ikut dikirim bersama X-Inertia.
     *
     * Tanpa versinya Inertia membalas 409 — permintaan reload, bukan halaman —
     * dan assertOk() gagal dengan cara yang menyamar jadi "halaman rusak".
     * Versinya diambil dari middleware, sebab Inertia::getVersion() masih
     * kosong selama uji.
     */
    private function kepalaInertia(): array
    {
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $versi];
    }

    public function test_pengguna_berakses_melihat_ringkasan_dan_daftar(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        $this->actingAs($user)
            ->withHeaders($this->kepalaInertia())
            ->get('/admin/pengumpulan-data')
            ->assertOk()
            ->assertJsonPath('component', 'ESS/Employee/DataCollection/Admin/Dashboard')
            ->assertJsonStructure(['props' => [
                'ringkasan' => ['total', 'sudah', 'belum', 'dikecualikan', 'persen', 'per_departemen'],
                'daftar' => ['items', 'total', 'halaman'],
                'skema',
                'pengumuman',
                'pilihanLokasi',
            ]]);
    }

    public function test_endpoint_json_tidak_bocor_ke_pengguna_tanpa_akses(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/admin/pengumpulan-data/daftar')->assertForbidden();
        $this->actingAs($user)->getJson('/admin/pengumpulan-data/kualitas')->assertForbidden();
        $this->actingAs($user)->postJson('/admin/pengumpulan-data/sakelar', ['aktif' => true])->assertForbidden();

        // Cakupan memutuskan siapa yang wajib mengisi seluruh perusahaan;
        // endpoint-nya harus sekeras sakelar, bukan sekadar ikut terdaftar.
        $this->actingAs($user)->postJson('/admin/pengumpulan-data/cakupan', ['lokasi' => []])->assertForbidden();
    }

    public function test_rute_statik_tidak_ditelan_rute_dinamis(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        // /daftar, /kualitas, /audit harus mengembalikan bentuknya sendiri —
        // bukan tertangkap sebagai {kodeKaryawan} milik endpoint detail.
        $this->actingAs($user)->getJson('/admin/pengumpulan-data/daftar')->assertOk()->assertJsonStructure(['items']);
        $this->actingAs($user)->getJson('/admin/pengumpulan-data/kualitas')->assertOk()->assertJsonStructure(['temuan']);
        $this->actingAs($user)->getJson('/admin/pengumpulan-data/audit')->assertOk()->assertJsonStructure(['items']);
    }

    public function test_sakelar_dapat_dinyalakan_dari_halaman_admin(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        $this->actingAs($user)
            ->postJson('/admin/pengumpulan-data/sakelar', ['aktif' => true])
            ->assertOk()
            ->assertJsonPath('aktif', true);

        $this->assertSame('Y', DB::table('N_HRIS_Pengumpulan_Data_Config')->value('Is_Aktif'));
        $this->assertTrue(
            DB::table('N_HRIS_Pengumpulan_Data_Audit')
                ->where('Aksi', 'UBAH_SAKELAR')
                ->where('Dilakukan_Oleh', $user->Username)
                ->exists(),
        );
    }

    public function test_cakupan_dapat_disimpan_dan_dikosongkan_dari_halaman_admin(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        $this->actingAs($user)
            ->postJson('/admin/pengumpulan-data/cakupan', ['lokasi' => [' emi ', 'EMI']])
            ->assertOk()
            ->assertJsonPath('cakupan_lokasi', ['EMI']);

        $this->assertSame('["EMI"]', DB::table('N_HRIS_Pengumpulan_Data_Config')->value('Cakupan_Lokasi'));

        // Daftar kosong adalah cara membatalkan pembukaan bertahap. Kalau ia
        // ditolak sebagai 'required', admin terkurung pada cakupan terakhirnya.
        $this->actingAs($user)
            ->postJson('/admin/pengumpulan-data/cakupan', ['lokasi' => []])
            ->assertOk()
            ->assertJsonPath('cakupan_lokasi', []);

        $this->assertNull(DB::table('N_HRIS_Pengumpulan_Data_Config')->value('Cakupan_Lokasi'));
    }

    public function test_cakupan_menolak_bentuk_yang_bukan_daftar(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        $this->actingAs($user)->postJson('/admin/pengumpulan-data/cakupan', [])->assertStatus(422);
        $this->actingAs($user)->postJson('/admin/pengumpulan-data/cakupan', ['lokasi' => 'EMI'])->assertStatus(422);
    }

    public function test_buka_kunci_menuntut_alasan(): void
    {
        $user = $this->user();
        $this->beriAkses($user);

        $this->actingAs($user)
            ->postJson('/admin/pengumpulan-data/buka-kunci', ['kode_karyawan' => 'A2'])
            ->assertStatus(422);
    }
}
