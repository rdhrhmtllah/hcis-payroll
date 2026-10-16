<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopDokumenKelompok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kontrak HTTP Export Studio.
 *
 * Akun uji dicari dari HRIS_Page_Access, mengikuti pola SopMasterPrioritasTest —
 * tidak mengarang user, dan di-skip dengan pesan jelas bila database dev tak
 * punya akun yang cocok. Membiarkannya merah akan menormalkan suite merah.
 *
 * ALAMATNYA BERUBAH sejak Studio memuat dua dataset: setiap endpoint kini
 * berada di bawah /{studioDataset}. Itu bukan kosmetik — galeri template,
 * katalog, dan seluruh aksi template ikut terscope ke Studio yang sedang
 * dibuka, dan uji di bawah menuntut scope itu benar-benar bekerja, bukan
 * sekadar ada di alamat.
 */
class SopExportStudioApiTest extends TestCase
{
    private const BASE = '/api/v1/sop/export-studio';

    private const KEPATUHAN = self::BASE.'/kepatuhan';

    private const DOKUMEN = self::BASE.'/dokumen';

    private function adminSop(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopMonitoringPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopMonitoringPage di database ini.');
        }

        return $u;
    }

    protected function tearDown(): void
    {
        DB::table('N_HRIS_Report_Template')->where('Nama', 'like', 'UJI API%')->delete();
        parent::tearDown();
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->getJson(self::KEPATUHAN.'/katalog/kepatuhan')->assertStatus(401);
    }

    /** Halamannya benar-benar bisa dibuka, bukan cuma route-nya terdaftar. */
    public function test_halaman_studio_bisa_dibuka(): void
    {
        $this->actingAs($this->adminSop())
            ->get('/sop/admin/export-studio')
            ->assertOk();
    }

    public function test_katalog_mengembalikan_dimensi_ukuran_dan_saringan(): void
    {
        $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/katalog/kepatuhan')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['result' => ['kunci', 'label', 'grain', 'dimensi', 'ukuran', 'saringan']]);
    }

    public function test_dataset_tak_dikenal_membalas_kode_stabil(): void
    {
        $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/katalog/tidak-ada')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'REPORT_DATASET_UNKNOWN');
    }

    public function test_galeri_memuat_template_resmi(): void
    {
        $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/template')
            ->assertOk()
            ->assertJsonStructure(['result' => ['resmi', 'saya', 'dibagikan']])
            ->assertJsonPath('result.resmi.0.kode', 'sop-sistem-rinci');
    }

    public function test_definisi_tak_sah_membalas_422_dengan_errors_per_field(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/template', [
                'nama' => 'UJI API Tak Sah',
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['tidak_ada'],
                    'ukuran' => [],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'REPORT_DEFINITION_INVALID')
            ->assertJsonStructure(['errors' => ['dimensi', 'ukuran']]);
    }

    public function test_simpan_template_sah(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/template', [
                'nama' => 'UJI API Kepatuhan Divisi',
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['divisi'],
                    'ukuran' => ['jml_karyawan', 'persen_selesai'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['result' => ['kode']]);
    }

    public function test_pratinjau_memakai_compiler_yang_sama_dengan_export(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/pratinjau', [
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['divisi'],
                    'ukuran' => ['jml_karyawan'],
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['result' => ['title', 'generated_at', 'columns', 'rows', 'catatan']]);
    }

    public function test_jalankan_mengembalikan_task_id(): void
    {
        Queue::fake();

        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/jalankan', [
                'format' => 'csv',
                'template_kode' => 'sop-sistem-rinci',
            ])
            ->assertStatus(202)
            ->assertJsonStructure(['result' => ['task_id']]);
    }

    public function test_template_tak_dikenal_membalas_403(): void
    {
        Queue::fake();

        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/jalankan', [
                'format' => 'csv',
                'template_kode' => 'sop-tidak-ada-9999',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'REPORT_TEMPLATE_FORBIDDEN');
    }

    /*
    |--------------------------------------------------------------------------
    | Lapis dokumen & versi pada dataset kepatuhan
    |--------------------------------------------------------------------------
    */

    /**
     * Katalog kepatuhan benar-benar MEMBAWA lapis baru.
     *
     * Diperiksa lewat kunci-kunci yang dipakai template resmi. Sebuah kunci
     * yang hilang dari katalog tidak menggagalkan apa pun saat template
     * disimpan — ia digugurkan diam-diam oleh validator, dan berkas yang
     * dihasilkan sekadar kehilangan kolomnya tanpa satu pun pesan.
     */
    public function test_katalog_kepatuhan_membawa_lapis_dokumen_dan_versi(): void
    {
        $r = $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/katalog/kepatuhan')
            ->assertOk()
            ->json('result');

        $dimensi = array_column($r['dimensi'], 'key');
        foreach (['no_dokumen', 'jenis_dokumen', 'apqc', 'dept_pemilik',
            'versi_ke', 'status_versi', 'tahap_versi',
            'status_masa_berlaku', 'status_review', 'nomor_pengesahan', 'baca_ulang'] as $k) {
            $this->assertContains($k, $dimensi, "Dimensi '{$k}' hilang dari katalog kepatuhan.");
        }

        $ukuran = array_column($r['ukuran'], 'key');
        foreach (['jml_dokumen', 'jml_item_tanpa_versi', 'jml_item_kadaluarsa'] as $k) {
            $this->assertContains($k, $ukuran, "Ukuran '{$k}' hilang dari katalog kepatuhan.");
        }

        $saringan = array_column($r['saringan'], 'key');
        foreach (['jenis_dokumen', 'apqc', 'status_masa_berlaku', 'status_review'] as $k) {
            $this->assertContains($k, $saringan, "Saringan '{$k}' hilang dari katalog kepatuhan.");
        }
    }

    /**
     * Setiap saringan baru punya isian.
     *
     * Saringan tanpa opsi tampil di layar sebagai daftar kosong, dan yang
     * membukanya akan menyimpulkan datanya memang tak ada. Ini juga yang
     * menangkap kunci cache yang lupa dinaikkan.
     */
    public function test_opsi_saringan_kepatuhan_terisi_untuk_lapis_baru(): void
    {
        $opsi = $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/katalog/kepatuhan')
            ->assertOk()
            ->json('result.opsi_saringan');

        foreach (['jenis_dokumen', 'apqc', 'dept_pemilik', 'status_versi',
            'tahap_versi', 'status_masa_berlaku', 'status_review', 'baca_ulang'] as $k) {
            $this->assertArrayHasKey($k, $opsi, "Opsi saringan '{$k}' tidak dikirim katalog.");
        }

        // Yang berasal dari konstanta WAJIB terisi apa pun keadaan datanya.
        $this->assertNotEmpty($opsi['status_versi']);
        $this->assertNotEmpty($opsi['status_masa_berlaku']);
        $this->assertNotEmpty($opsi['status_review']);
    }

    /**
     * Pratinjau memakai kolom lapis baru tanpa menggagalkan kueri.
     *
     * Inilah uji yang benar-benar menyentuh SQL Server: dimensi berbentuk CASE
     * dan ukuran berbentuk SUM(CASE ...) sah di mode rinci tetapi bisa
     * ditolak begitu masuk GROUP BY. Yang diperiksa BUKAN jumlah barisnya —
     * data dev bisa kosong — melainkan bahwa kuerinya berjalan.
     */
    public function test_pratinjau_kepatuhan_mengelompokkan_lapis_versi(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::KEPATUHAN.'/pratinjau', [
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['jenis_dokumen', 'status_masa_berlaku', 'status_review'],
                    'ukuran' => ['jml_dokumen', 'jml_item', 'jml_item_tanpa_versi', 'jml_item_kadaluarsa'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Dataset dokumen
    |--------------------------------------------------------------------------
    */

    public function test_katalog_dokumen_tersedia(): void
    {
        $r = $this->actingAs($this->adminSop())
            ->getJson(self::DOKUMEN.'/katalog/dokumen')
            ->assertOk()
            ->assertJsonPath('result.kunci', 'dokumen')
            ->json('result');

        $this->assertStringContainsString('satu dokumen', mb_strtolower($r['grain']));
        $this->assertContains('kelompok', array_column($r['dimensi'], 'key'));
        $this->assertContains('jml_dokumen', array_column($r['ukuran'], 'key'));
    }

    /**
     * Pratinjau dokumen berjalan DAN benar-benar membaca register.
     *
     * Sengaja tidak sekadar memeriksa status 200: dataset yang salah rakit
     * tetap membalas 200 dengan nol baris. Karena N_SOP_Dokumen berisi data di
     * dev, jumlah barisnya harus > 0 — dan bila kelak database uji dikosongkan,
     * uji ini di-skip dengan alasan yang terbaca, bukan lulus dengan diam.
     */
    public function test_pratinjau_dokumen_membaca_register(): void
    {
        $jml = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', 'Y')->count();
        if ($jml === 0) {
            $this->markTestSkipped('Tak ada dokumen aktif di database ini.');
        }

        $hasil = $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/pratinjau', [
                'definisi' => [
                    'dataset' => 'dokumen',
                    'mode' => 'ringkasan',
                    'dimensi' => ['kelompok'],
                    'ukuran' => ['jml_dokumen', 'jml_tanpa_versi', 'persen_beredar'],
                ],
            ])
            ->assertOk()
            ->json('result');

        $this->assertNotEmpty($hasil['rows'], 'Register dokumen terbaca kosong padahal ada dokumen aktif.');
    }

    /**
     * KETIGA KELOMPOK MENJUMLAH TEPAT KE TOTAL.
     *
     * Syarat yang sama dipegang dashboard, dan diperiksa lagi di sini karena
     * ekspresinya berbeda bentuk: dashboard menggabungkan di PHP, dataset
     * memakai LEFT JOIN. Bila keduanya menyimpang, selisihnya muncul di sini
     * lebih dulu daripada di mata pengguna.
     */
    public function test_kelompok_dokumen_menjumlah_tepat_ke_total(): void
    {
        $total = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', 'Y')->count();
        if ($total === 0) {
            $this->markTestSkipped('Tak ada dokumen aktif di database ini.');
        }

        $rows = $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/pratinjau', [
                'definisi' => [
                    'dataset' => 'dokumen',
                    'mode' => 'ringkasan',
                    'dimensi' => ['kelompok'],
                    'ukuran' => ['jml_dokumen'],
                ],
            ])
            ->assertOk()
            ->json('result.rows');

        $jumlah = 0;
        foreach ($rows as $r) {
            $jumlah += (int) ($r['jml_dokumen'] ?? 0);
        }

        $this->assertSame($total, $jumlah, 'Jumlah per kelompok tidak sama dengan jumlah dokumen aktif.');

        // Label yang muncul harus berasal dari daftar resmi, bukan teks lepas.
        $label = array_column($rows, 'kelompok');
        foreach ($label as $l) {
            $this->assertContains($l, array_values(SopDokumenKelompok::LABEL), "Label kelompok tak dikenal: {$l}");
        }
    }

    public function test_galeri_dokumen_hanya_memuat_template_dokumen(): void
    {
        $galeri = $this->actingAs($this->adminSop())
            ->getJson(self::DOKUMEN.'/template')
            ->assertOk()
            ->json('result');

        $this->assertNotEmpty($galeri['resmi'], 'Tak ada satu pun template resmi berbutir dokumen.');
        foreach ($galeri['resmi'] as $tpl) {
            $this->assertSame('dokumen', $tpl['dataset']);
        }
    }

    /**
     * Galeri kepatuhan TIDAK kebocoran template dokumen.
     *
     * Pasangan uji di atas. Menguji satu arah saja membuat saringan yang
     * kebetulan membuang segalanya tetap terbaca lulus.
     */
    public function test_galeri_kepatuhan_tidak_memuat_template_dokumen(): void
    {
        $galeri = $this->actingAs($this->adminSop())
            ->getJson(self::KEPATUHAN.'/template')
            ->assertOk()
            ->json('result');

        $this->assertNotEmpty($galeri['resmi']);
        foreach ($galeri['resmi'] as $tpl) {
            $this->assertSame('kepatuhan', $tpl['dataset']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Penjaga silang dataset
    |--------------------------------------------------------------------------
    | Setiap uji di bawah menuntut PENOLAKAN. Penjaga yang tak pernah menolak
    | apa pun tak bisa dibedakan dari penjaga yang tak terpasang.
    */

    public function test_definisi_dataset_lain_ditolak_saat_pratinjau(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/pratinjau', [
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['divisi'],
                    'ukuran' => ['jml_karyawan'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'REPORT_DATASET_MISMATCH');
    }

    public function test_definisi_dataset_lain_ditolak_saat_simpan(): void
    {
        $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/template', [
                'nama' => 'UJI API Silang Dataset',
                'definisi' => [
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['divisi'],
                    'ukuran' => ['jml_karyawan'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'REPORT_DATASET_MISMATCH');
    }

    public function test_template_dataset_lain_tak_bisa_dijalankan(): void
    {
        Queue::fake();

        $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/jalankan', [
                'format' => 'csv',
                'template_kode' => 'sop-sistem-rinci',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'REPORT_TEMPLATE_FORBIDDEN');
    }

    public function test_template_dokumen_bisa_dijalankan_dari_studio_dokumen(): void
    {
        Queue::fake();

        $this->actingAs($this->adminSop())
            ->postJson(self::DOKUMEN.'/jalankan', [
                'format' => 'csv',
                'template_kode' => 'sop-sistem-register-dokumen',
            ])
            ->assertStatus(202)
            ->assertJsonStructure(['result' => ['task_id']]);
    }

    public function test_studio_dataset_karangan_tak_punya_route(): void
    {
        $this->actingAs($this->adminSop())
            ->getJson(self::BASE.'/karangan/katalog/kepatuhan')
            ->assertStatus(404);
    }
}
