<?php

namespace Tests\Feature\ESS\Employee;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Employee\DataCollection\CakupanKampanye;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\StatusKaryawanResolver;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PengumpulanDataHalamanTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Baris config tersisip dalam keadaan MATI (Is_Aktif='T') supaya fase 1-2
     * aman di produksi. Test halaman karena itu harus menyalakannya lebih dulu,
     * kalau tidak semua permintaan tertolak 403 sebelum sempat diuji.
     * DatabaseTransactions mengembalikannya setelah test selesai.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL di .env dev berisi 'n8n.ajt.my.id' TANPA skema, sehingga
        // Laravel memperlakukannya sebagai path: setiap permintaan test menjadi
        // http://localhost/n8n.ajt.my.id/... lalu 404. Menyetel $this->baseUrl
        // TIDAK menolong — versi Laravel ini membangun URL test lewat helper
        // url(), jadi root URL-nya yang harus dipaksa. Dikunci di sini supaya
        // test tidak bergantung pada isi .env mesin siapa pun.
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'Y']);
        app(KonfigurasiKampanye::class)->bersihkanCache();
    }

    private function userAktif(): User
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereNotNull('UserID_Web')
            ->first(['Kode_Karyawan', 'UserID_Web']);

        $this->assertNotNull($karyawan, 'Butuh karyawan aktif yang punya akun web.');

        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();
        app(StatusPengumpulanService::class)->bersihkanCache($karyawan->Kode_Karyawan);

        return User::where('Id_Users', $karyawan->UserID_Web)->firstOrFail();
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

    public function test_halaman_wizard_dapat_dibuka_dan_membawa_skema(): void
    {
        // Header X-Inertia membuat respons berupa JSON, bukan blade root yang
        // memanggil @vite. Tanpa ini test ikut bergantung pada hasil `npm run
        // build` — kebergantungan yang tidak ada hubungannya dengan yang diuji.
        $this->actingAs($this->userAktif())
            ->withHeaders($this->kepalaInertia())
            ->get('/pengumpulan-data')
            ->assertOk()
            // JSON diperiksa langsung, bukan lewat assertInertia: helper itu
            // menuntut kunci amplop (encryptHistory/clearHistory) yang berbeda
            // antar versi paket, dan itu tidak ada hubungannya dengan yang diuji.
            ->assertJsonPath('component', 'ESS/Employee/DataCollection/Wizard')
            ->assertJsonCount(4, 'props.skema.langkah')
            ->assertJsonPath('props.status.status', 'BELUM')
            ->assertJsonStructure(['props' => ['skema' => ['versi', 'langkah', 'field'], 'status', 'draft', 'pilihanDivisi']]);
    }

    public function test_tunda_menaikkan_pencacah(): void
    {
        $user = $this->userAktif();

        $this->actingAs($user)->postJson('/pengumpulan-data/tunda')->assertOk();

        $kode = $user->karyawan->Kode_Karyawan;
        $this->assertSame(
            1,
            (int) DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->value('Jumlah_Tunda'),
        );
    }

    public function test_endpoint_tampil_mencatat_penanda_ke_tabel_bukan_ke_browser(): void
    {
        $user = $this->userAktif();
        $kode = $user->karyawan->Kode_Karyawan;

        $this->actingAs($user)->postJson('/pengumpulan-data/tampil')->assertOk();

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kode)
            ->first(['Terakhir_Tampil_At', 'Terakhir_Tampil_Sesi']);

        $this->assertNotNull($baris->Terakhir_Tampil_At, 'Waktu tampil harus tercatat di tabel.');
        $this->assertNotNull($baris->Terakhir_Tampil_Sesi, 'Id sesi harus tercatat di tabel.');
    }

    public function test_prop_membawa_keputusan_tampil_dari_server(): void
    {
        // Frekuensi dipatok di dalam test. Membiarkannya mengikuti config berarti
        // test ini diam-diam bergantung pada setelan produksi — dan memang itu
        // yang membuatnya pecah saat setelannya diganti ke HALAMAN.
        config(['pengumpulan_data.frekuensi_modal' => 'SESI']);

        $user = $this->userAktif();
        $this->actingAs($user);

        $prop = (new HandleInertiaRequests)->share(Request::create('/homepage', 'GET'))['pengumpulanData'];

        $this->assertTrue(
            $prop()['tampilkanModal'],
            'Karyawan yang belum pernah dilihatkan modal harus mendapat keputusan tampil dari server.',
        );

        // Setelah dicatat pada sesi yang sama, keputusan servernya berbalik —
        // tanpa satu pun penanda di browser.
        app(StatusPengumpulanService::class)->catatTampil(
            $user->karyawan->Kode_Karyawan,
            session()->getId(),
        );

        $propBaru = (new HandleInertiaRequests)->share(Request::create('/homepage', 'GET'))['pengumpulanData'];

        $this->assertFalse($propBaru()['tampilkanModal']);
    }

    public function test_mode_halaman_mengabaikan_penanda_yang_sudah_tercatat(): void
    {
        config(['pengumpulan_data.frekuensi_modal' => 'HALAMAN']);

        $user = $this->userAktif();
        $this->actingAs($user);

        app(StatusPengumpulanService::class)->catatTampil(
            $user->karyawan->Kode_Karyawan,
            session()->getId(),
        );

        $prop = (new HandleInertiaRequests)->share(Request::create('/homepage', 'GET'))['pengumpulanData'];

        $this->assertTrue(
            $prop()['tampilkanModal'],
            'Mode HALAMAN harus tetap menampilkan modal walau penandanya sudah tercatat.',
        );
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/pengumpulan-data')->assertRedirect();
    }

    /**
     * Prop ini dievaluasi di SETIAP render Inertia. Klaim desainnya: kampanye
     * yang mati berbiaya nol — sakelar (satu baris ber-cache) diperiksa sebelum
     * tabel status disentuh. Test ini yang menjaga urutan itu tidak terbalik
     * saat kode diubah kemudian.
     */
    public function test_kampanye_mati_tidak_menyentuh_tabel_status(): void
    {
        $user = $this->userAktif();

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        app(KonfigurasiKampanye::class)->bersihkanCache();

        // actingAs WAJIB: closure share() membaca Auth::user(), bukan user
        // resolver milik Request. Versi pertama test ini hanya memasang
        // setUserResolver, sehingga Auth::user() null dan prop selalu null —
        // test lulus tanpa membuktikan apa pun tentang sakelar kampanye.
        $this->actingAs($user);
        $request = Request::create('/homepage', 'GET');

        $prop = (new HandleInertiaRequests)->share($request)['pengumpulanData'];

        $menyentuhStatus = 0;
        DB::listen(function ($query) use (&$menyentuhStatus) {
            if (str_contains($query->sql, 'N_HRIS_Pengumpulan_Data_Status')) {
                $menyentuhStatus++;
            }
        });

        $hasil = $prop();

        $this->assertNull($hasil, 'Kampanye mati harus menghasilkan prop kosong.');
        $this->assertSame(0, $menyentuhStatus, 'Kampanye mati tidak boleh menyentuh tabel status sama sekali.');
    }

    /**
     * Pasangan wajib dari test di atas. Tanpa ini, "prop bernilai null" bisa
     * lulus karena alasan yang sama sekali salah — dan itu persis yang terjadi
     * pada versi pertama test tersebut.
     */
    public function test_kampanye_menyala_mengisi_prop_untuk_karyawan_yang_belum_mengisi(): void
    {
        $user = $this->userAktif();
        $this->actingAs($user);

        $prop = (new HandleInertiaRequests)->share(Request::create('/homepage', 'GET'))['pengumpulanData'];
        $hasil = $prop();

        $this->assertNotNull($hasil, 'Kampanye menyala + karyawan belum mengisi harus menghasilkan prop.');
        $this->assertSame('BELUM', $hasil['status']['status']);
        $this->assertSame(3, $hasil['status']['sisa_tunda']);
        $this->assertArrayHasKey('judul', $hasil['pengumuman']);
        $this->assertContains($hasil['frekuensi'], ['SESI', 'HARIAN', 'HALAMAN']);
    }

    public function test_kampanye_mati_menutup_halaman_dan_endpoint_kirim(): void
    {
        $user = $this->userAktif();

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        app(KonfigurasiKampanye::class)->bersihkanCache();

        $this->actingAs($user)->get('/pengumpulan-data')->assertForbidden();
        $this->actingAs($user)->postJson('/pengumpulan-data/kirim', ['data' => []])->assertForbidden();
    }

    /**
     * Tombol kirim yang terkunci di wizard hanya penjaga di browser; POST
     * langsung tanpa centang pernyataan harus tetap ditolak server.
     */
    public function test_kirim_tanpa_centang_pernyataan_ditolak_dan_tidak_menyimpan(): void
    {
        $this->bukaCakupan();
        $user = $this->userAktif();
        $kode = $user->karyawan->Kode_Karyawan;

        foreach ([[], ['konfirmasi' => false]] as $tambahan) {
            $this->actingAs($user)
                ->postJson('/pengumpulan-data/kirim', ['data' => []] + $tambahan)
                ->assertStatus(422)
                ->assertJsonValidationErrors('konfirmasi');
        }

        $this->assertSame(0, DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $kode)->count());
    }

    public function test_centang_pernyataan_lolos_dari_penjaga_konfirmasi(): void
    {
        $this->bukaCakupan();

        $respons = $this->actingAs($this->userAktif())
            ->postJson('/pengumpulan-data/kirim', ['data' => [], 'konfirmasi' => true]);

        // 403 juga tak punya galat 'konfirmasi' — tanpa ini uji hijau palsu.
        $this->assertNotSame(403, $respons->status());
        $respons->assertJsonMissingValidationErrors('konfirmasi');
    }

    /**
     * Cakupan lokasi kampanye bisa tidak mencakup karyawan acak dari data dev;
     * uji penjaga konfirmasi harus sampai melewati pintu itu.
     */
    private function bukaCakupan(): void
    {
        $this->app->instance(CakupanKampanye::class, new class extends CakupanKampanye
        {
            public function __construct() {}

            public function mencakup(string $kodeKaryawan): bool
            {
                return true;
            }
        });
    }

    public function test_cari_karyawan_ditolak_bagi_yang_bukan_phl(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(false);

        $this->actingAs($this->userAktif())
            ->getJson('/pengumpulan-data/cari-karyawan?q=ab')
            ->assertForbidden();
    }

    /**
     * Sejak 19 Sep 2026 atasan PHL ditulis sebagai teks bebas. Selama
     * pencarian disembunyikan, endpoint-nya tertutup juga bagi PHL — bukan
     * dibiarkan terbuka tanpa ada kotak yang memakainya.
     */
    public function test_cari_karyawan_tertutup_bagi_phl_selama_pencarian_disembunyikan(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(true);

        $this->actingAs($this->userAktif())
            ->getJson('/pengumpulan-data/cari-karyawan?q=ab')
            ->assertForbidden();
    }

    public function test_cari_karyawan_menemukan_karyawan_aktif_untuk_phl(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(true);
        $this->nyalakanPencarianAtasan();
        $user = $this->userAktif();

        $sasaran = $this->karyawanBerlevel([8, 10], $user->karyawan->Kode_Karyawan)->first(['k.Kode_Karyawan', 'k.Nama']);

        $this->actingAs($user)
            ->getJson('/pengumpulan-data/cari-karyawan?q='.urlencode($sasaran->Kode_Karyawan))
            ->assertOk()
            ->assertJsonFragment(['kode' => (string) $sasaran->Kode_Karyawan])
            ->assertJsonStructure(['data' => [['kode', 'nama', 'keterangan']]]);

        // Kurang dari dua huruf tidak menyentuh tabel karyawan sama sekali.
        $this->actingAs($user)
            ->getJson('/pengumpulan-data/cari-karyawan?q=a')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_cari_karyawan_tertutup_bila_kedua_pertanyaan_atasan_dimatikan(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(true);
        config([
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_spv' => false,
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_dept_head' => false,
        ]);

        $this->actingAs($this->userAktif())
            ->getJson('/pengumpulan-data/cari-karyawan?q=ab')
            ->assertForbidden();
    }

    /** Level di luar daftar (baku 3-12) tidak boleh muncul sebagai saran atasan. */
    public function test_cari_karyawan_tidak_menyarankan_level_yang_tidak_diizinkan(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(true);
        $this->nyalakanPencarianAtasan();
        $user = $this->userAktif();

        $staf = $this->karyawanBerlevel([2], $user->karyawan->Kode_Karyawan)->value('k.Kode_Karyawan');
        $this->assertNotNull($staf, 'Butuh karyawan aktif level 2.');

        $this->actingAs($user)
            ->getJson('/pengumpulan-data/cari-karyawan?q='.urlencode($staf))
            ->assertOk()
            ->assertJsonMissing(['kode' => (string) $staf]);

        config(['services.hcis.pengumpulan_data.atasan_id_level' => [2]]);

        $this->actingAs($user)
            ->getJson('/pengumpulan-data/cari-karyawan?q='.urlencode($staf))
            ->assertOk()
            ->assertJsonFragment(['kode' => (string) $staf]);
    }

    private function karyawanBerlevel(array $level, string $kecuali): \Illuminate\Database\Query\Builder
    {
        return DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where('k.Kode_Karyawan', '<>', $kecuali)
            ->whereIn('v.ID_Level', $level)
            ->orderBy('k.Kode_Karyawan');
    }

    /**
     * Modal sudah menyaring yang dikecualikan; pintu formulirnya harus sama.
     * Tanpa itu, orang yang dikecualikan admin tetap bisa mengirim lewat URL.
     */
    public function test_yang_dikecualikan_tidak_bisa_membuka_maupun_mengirim(): void
    {
        $this->bukaCakupan();
        $user = $this->userAktif();
        $kode = $user->karyawan->Kode_Karyawan;

        DB::table('N_HRIS_Pengumpulan_Data_Status')->insert([
            'Kode_Karyawan' => $kode, 'Status' => 'BELUM', 'Jumlah_Tunda' => 0, 'Is_Dikecualikan' => 'Y',
            'Created_At' => now(), 'Updated_At' => now(),
        ]);
        app(StatusPengumpulanService::class)->bersihkanCache($kode);

        $this->actingAs($user)->get('/pengumpulan-data')->assertForbidden();
        $this->actingAs($user)->postJson('/pengumpulan-data/kirim', ['data' => [], 'konfirmasi' => true])->assertForbidden();
        $this->assertSame('BELUM', DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->value('Status'));
    }

    /**
     * Akun tanpa data karyawan (mis. akun admin) tidak punya Kode_Karyawan.
     * Bila kampanye dibuka untuk semua lokasi, dulu ia bisa menulis baris
     * status dan kiriman ber-Kode_Karyawan kosong.
     */
    public function test_akun_tanpa_data_karyawan_ditolak_walau_semua_lokasi_dibuka(): void
    {
        $this->bukaCakupan();

        $id = DB::table('KPI_Users as u')
            ->whereNotExists(fn ($q) => $q->from('Karyawan as k')->whereColumn('k.UserID_Web', 'u.Id_Users'))
            ->value('u.Id_Users');
        if (! $id) {
            $this->markTestSkipped('Tidak ada akun tanpa data karyawan di DB ini.');
        }
        $akun = User::where('Id_Users', $id)->firstOrFail();

        $this->actingAs($akun)->postJson('/pengumpulan-data/draft', ['data' => ['x' => 1], 'langkah' => 0])->assertForbidden();
        $this->actingAs($akun)->postJson('/pengumpulan-data/kirim', ['data' => [], 'konfirmasi' => true])->assertForbidden();

        $this->assertSame(0, DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', '')->count());
        $this->assertSame(0, DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', '')->count());
    }

    public function test_cari_karyawan_dengan_q_berbentuk_array_tidak_500(): void
    {
        $this->bukaCakupan();
        $this->anggapPhl(true);
        $this->nyalakanPencarianAtasan();

        $this->actingAs($this->userAktif())
            ->getJson('/pengumpulan-data/cari-karyawan?q[]=ab')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    /** Pencarian atasan disembunyikan sejak 19 Sep 2026; ujinya tetap menjaga jalur itu. */
    private function nyalakanPencarianAtasan(): void
    {
        config(['services.hcis.pengumpulan_data.atasan_pakai_pencarian' => true]);
    }

    private function anggapPhl(bool $phl): void
    {
        $this->app->instance(StatusKaryawanResolver::class, new class($phl) extends StatusKaryawanResolver
        {
            public function __construct(private bool $phl) {}

            public function isPhl(string $kodeKaryawan): bool
            {
                return $this->phl;
            }
        });
    }
}
