<?php

namespace Tests\Feature\ESS\Employee;

use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Employee\DataCollection\CakupanKampanye;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Gerbang "terkunci": aplikasinya jadi formulir.
 *
 * Sebelumnya orang yang jatah tundanya habis dihadang MODAL di setiap halaman —
 * modal tanpa tombol tutup, tanpa Esc, tanpa klik-luar, yang satu-satunya
 * tombolnya menuju halaman yang sedang ia buka. Satu salah langkah kecil di
 * gerbang itu mengurung orangnya sampai tidak bisa memakai HCIS sama sekali.
 *
 * Sekarang keputusannya diambil SERVER dan bentuknya perpindahan halaman biasa:
 * selama terkunci, alamat mana pun berujung di formulir. Tidak ada lapisan yang
 * bisa gagal menutup, karena tidak ada lapisan.
 */
class GerbangPengumpulanDataTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Is_Aktif' => 'Y',
            // Cakupan dinetralkan di setiap uji berkas ini: penjaga "terkunci"
            // tidak boleh lulus atau gagal gara-gara gelombang pembukaan yang
            // kebetulan tersimpan di baris konfigurasi dev.
            'Cakupan_Lokasi' => null,
        ]);
        app(KonfigurasiKampanye::class)->bersihkanCache();
    }

    private function bukaHanyaLokasi(string $lokasi): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Cakupan_Lokasi' => json_encode([CakupanKampanye::normalkan($lokasi)]),
        ]);

        app(KonfigurasiKampanye::class)->bersihkanCache();
    }

    private function karyawanAktif(): object
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereNotNull('UserID_Web')
            ->first(['Kode_Karyawan', 'UserID_Web']);

        $this->assertNotNull($karyawan, 'Butuh karyawan aktif yang punya akun web.');

        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();

        return $karyawan;
    }

    private function user(object $karyawan): User
    {
        app(StatusPengumpulanService::class)->bersihkanCache($karyawan->Kode_Karyawan);

        return User::where('Id_Users', $karyawan->UserID_Web)->firstOrFail();
    }

    private function kunci(object $karyawan): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Status')->updateOrInsert(
            ['Kode_Karyawan' => $karyawan->Kode_Karyawan],
            ['Status' => 'TERKUNCI', 'Jumlah_Tunda' => 3, 'Is_Dikecualikan' => 'T', 'Created_At' => now(), 'Updated_At' => now()],
        );

        app(StatusPengumpulanService::class)->bersihkanCache($karyawan->Kode_Karyawan);
    }

    public function test_yang_terkunci_diarahkan_ke_formulir_dari_halaman_mana_pun(): void
    {
        $karyawan = $this->karyawanAktif();
        $this->kunci($karyawan);

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertRedirect('/pengumpulan-data');
    }

    /**
     * Penjaga paling penting di berkas ini. Kalau halaman formulirnya sendiri
     * ikut diarahkan, orangnya berputar tanpa henti — kurungan yang sama,
     * hanya berganti rupa.
     */
    public function test_halaman_formulir_tidak_mengarahkan_ke_dirinya_sendiri(): void
    {
        $karyawan = $this->karyawanAktif();
        $this->kunci($karyawan);

        $this->actingAs($this->user($karyawan))
            ->get('/pengumpulan-data')
            ->assertOk();
    }

    public function test_yang_belum_terkunci_tidak_diarahkan(): void
    {
        $karyawan = $this->karyawanAktif();

        DB::table('N_HRIS_Pengumpulan_Data_Status')->updateOrInsert(
            ['Kode_Karyawan' => $karyawan->Kode_Karyawan],
            ['Status' => 'BELUM', 'Jumlah_Tunda' => 2, 'Is_Dikecualikan' => 'T', 'Created_At' => now(), 'Updated_At' => now()],
        );

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertOk();
    }

    public function test_yang_dikecualikan_tidak_diarahkan_walau_berstatus_terkunci(): void
    {
        $karyawan = $this->karyawanAktif();

        DB::table('N_HRIS_Pengumpulan_Data_Status')->updateOrInsert(
            ['Kode_Karyawan' => $karyawan->Kode_Karyawan],
            ['Status' => 'TERKUNCI', 'Jumlah_Tunda' => 3, 'Is_Dikecualikan' => 'Y', 'Created_At' => now(), 'Updated_At' => now()],
        );

        app(StatusPengumpulanService::class)->bersihkanCache($karyawan->Kode_Karyawan);

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertOk();
    }

    public function test_kampanye_mati_membuka_kembali_seluruh_aplikasi(): void
    {
        $karyawan = $this->karyawanAktif();
        $this->kunci($karyawan);

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Is_Aktif' => 'T']);
        app(KonfigurasiKampanye::class)->bersihkanCache();

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertOk();
    }

    /**
     * Permintaan latar (polling notifikasi, simpan draf, unduhan) TIDAK boleh
     * dijawab dengan pengalihan halaman: yang menerimanya bukan peramban yang
     * berpindah, melainkan kode yang menunggu JSON. Mengalihkannya membuat
     * jawaban HTML masuk ke pemanggil yang mengharapkan data.
     */
    public function test_permintaan_yang_menunggu_json_tidak_dialihkan(): void
    {
        $karyawan = $this->karyawanAktif();
        $this->kunci($karyawan);

        $balasan = $this->actingAs($this->user($karyawan))
            ->getJson('/api/v1/notifications/unread-count');

        $this->assertNotEquals(302, $balasan->getStatusCode());
    }

    /**
     * Cakupan lokasi adalah gerbang yang sama tegasnya dengan sakelar, dan
     * gerbang yang hanya dipasang di satu dari dua pintu setara adalah pola bug
     * yang sudah berulang di repo ini. Empat uji berikut menjaga ketiga pintu
     * sisi karyawan sekaligus: pengalihan halaman, prop modal, dan formulirnya.
     */
    private function karyawanAktifBerlokasi(): object
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereNotNull('UserID_Web')
            ->whereNotNull('Cabang_Untuk_Gaji')
            ->whereRaw("LTRIM(RTRIM(Cabang_Untuk_Gaji)) <> ''")
            ->first(['Kode_Karyawan', 'UserID_Web', 'Cabang_Untuk_Gaji']);

        $this->assertNotNull($karyawan, 'Butuh karyawan aktif berakun web yang punya lokasi kerja.');

        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $karyawan->Kode_Karyawan)->delete();

        return $karyawan;
    }

    public function test_lokasi_di_luar_cakupan_tidak_diarahkan_walau_berstatus_terkunci(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->kunci($karyawan);
        $this->bukaHanyaLokasi('ZZ-LOKASI-TIDAK-ADA');

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertOk();
    }

    /** Pasangan penolakan: orang yang sama, cakupan yang memuat lokasinya. */
    public function test_lokasi_di_dalam_cakupan_tetap_diarahkan_saat_terkunci(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->kunci($karyawan);
        $this->bukaHanyaLokasi($karyawan->Cabang_Untuk_Gaji);

        $this->actingAs($this->user($karyawan))
            ->get('/homepage')
            ->assertRedirect('/pengumpulan-data');
    }

    public function test_formulir_tertutup_bagi_lokasi_yang_belum_dibuka(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->bukaHanyaLokasi('ZZ-LOKASI-TIDAK-ADA');

        $this->actingAs($this->user($karyawan))
            ->get('/pengumpulan-data')
            ->assertForbidden();

        $this->actingAs($this->user($karyawan))
            ->postJson('/pengumpulan-data/tunda')
            ->assertForbidden();
    }

    public function test_formulir_terbuka_bagi_lokasi_yang_dibuka(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->bukaHanyaLokasi($karyawan->Cabang_Untuk_Gaji);

        $this->actingAs($this->user($karyawan))
            ->get('/pengumpulan-data')
            ->assertOk();
    }

    /**
     * Prop diperiksa lewat JSON dengan header X-Inertia, bukan assertInertia:
     * helper itu menuntut kunci amplop yang berbeda antar versi paket, dan
     * versinya harus datang dari middleware karena Inertia::getVersion() masih
     * kosong selama uji — tanpa itu jawabannya 409, bukan halaman.
     */
    private function kepalaInertia(): array
    {
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $versi];
    }

    public function test_ajakan_tidak_dikirim_ke_lokasi_yang_belum_dibuka(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->bukaHanyaLokasi('ZZ-LOKASI-TIDAK-ADA');

        $this->actingAs($this->user($karyawan))
            ->withHeaders($this->kepalaInertia())
            ->get('/homepage')
            ->assertOk()
            ->assertJsonPath('props.pengumpulanData', null);
    }

    public function test_ajakan_tetap_dikirim_ke_lokasi_yang_dibuka(): void
    {
        $karyawan = $this->karyawanAktifBerlokasi();
        $this->bukaHanyaLokasi($karyawan->Cabang_Untuk_Gaji);

        $this->actingAs($this->user($karyawan))
            ->withHeaders($this->kepalaInertia())
            ->get('/homepage')
            ->assertOk()
            ->assertJsonPath('props.pengumpulanData.status.status', 'BELUM');
    }

    public function test_logout_tetap_bisa_dijangkau_saat_terkunci(): void
    {
        $karyawan = $this->karyawanAktif();
        $this->kunci($karyawan);

        $balasan = $this->actingAs($this->user($karyawan))->post('/logout');

        $this->assertNotSame('http://localhost/pengumpulan-data', $balasan->headers->get('Location'));
    }
}
