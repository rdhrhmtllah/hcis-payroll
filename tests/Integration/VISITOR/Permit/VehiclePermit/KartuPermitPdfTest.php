<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kartu permit yang dicetak dan ditempel di dashboard mobil.
 *
 * Kartu ini dibaca petugas di gerbang, jadi yang dijaga di sini bukan
 * "PDF-nya jadi", melainkan SIAPA yang boleh mencetak apa:
 *
 * - Pemegang kartu hanya kartunya sendiri. Rutenya berkunci halaman
 *   self-service yang dipegang semua karyawan, sehingga kalau kepemilikannya
 *   tidak ikut dijaga, satu karyawan bisa mencetak kartu karyawan lain
 *   dengan menebak Id — tanpa satu pun galat.
 * - Admin boleh kartu siapa pun, tetapi lewat pintunya sendiri.
 * - Hanya permit AKTIF yang punya wujud cetak. Kartu yang tampak sah padahal
 *   permitnya sudah dicabut adalah kegagalan yang paling mahal di sini,
 *   sebab kertasnya tidak tahu apa-apa soal pencabutan.
 *
 * Cetak massal punya aturan yang mudah hilang saat disentuh lagi: baris tak
 * aktif DISARING DIAM-DIAM, bukan menggagalkan seluruh berkas. Admin
 * mencentang sepuluh baris, satu baru dicabut, dan yang dibutuhkannya tetap
 * sembilan kartu — bukan pesan galat.
 */
class KartuPermitPdfTest extends TestCase
{
    use DatabaseTransactions;

    private const KUNCI_PEMOHON = 'visitorPermitPage';

    private const KUNCI_ADMIN = 'visitorMonitoringPermitPage';

    /** Tiap uji memakai pengguna berbeda; lihat penggunaBerkunci(). */
    private static int $urutanPengguna = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL di lingkungan ini tak bermuatan skema, jadi url() menjadikannya
        // ruas path dan permintaan pertama tiap proses uji nyasar lalu 404.
        URL::forceRootUrl('http://localhost');
    }

    public function test_pemegang_kartu_bisa_mencetak_kartunya_sendiri(): void
    {
        [$user, $kode] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($kode);

        $response = $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf");

        $response->assertOk();
        $this->assertBerkasPdf($response);
    }

    public function test_karyawan_tidak_bisa_mencetak_kartu_milik_orang_lain(): void
    {
        // Kunci halamannya self-service dan dipegang semua karyawan, jadi
        // kepemilikan adalah SATU-SATUNYA yang menahan di pintu ini.
        [$user] = $this->pemohonBerkunci();
        $milikOrangLain = $this->buatPermitAktif($this->karyawanLain());

        $response = $this->actingAs($user)->get("/transaksipermit/{$milikOrangLain}/kartu-pdf");

        $response->assertStatus(404);
        $this->assertSame('VISITOR_PERMIT_NOT_FOUND', $response->json('code') ?? $response->json('error_code'));
    }

    public function test_permit_yang_belum_disetujui_tidak_punya_wujud_cetak(): void
    {
        [$user, $kode] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($kode, disetujui: false);

        $response = $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf");

        $response->assertStatus(422);
    }

    public function test_permit_yang_dicabut_tidak_bisa_dicetak_lagi(): void
    {
        // Kertasnya tidak tahu apa-apa soal pencabutan; pintunya yang harus tahu.
        [$user, $kode] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($kode);
        $this->cabut($id);

        $response = $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf");

        $response->assertStatus(422);
    }

    public function test_admin_bisa_mencetak_kartu_milik_siapa_pun(): void
    {
        $admin = $this->penggunaBerkunci(self::KUNCI_ADMIN);
        $id = $this->buatPermitAktif($this->karyawanLain());

        $response = $this->actingAs($admin)->get("/laporanvisitor-permit/{$id}/kartu-pdf");

        $response->assertOk();
        $this->assertBerkasPdf($response);
    }

    public function test_bawaan_memakai_template_dua_dan_template_satu_lewat_query(): void
    {
        // Dua tata letak hidup berdampingan. Yang mudah hilang saat salah
        // satunya disunting adalah MANA yang bawaan — dan itu terbaca user
        // sebagai "kartunya berubah sendiri".
        [$user, $kode] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($kode);

        $this->assertSame(
            'exports.visitor-permit-kartu-t2',
            $this->templateYangDipakai(fn () => $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf")),
        );

        $this->assertSame(
            'exports.visitor-permit-kartu-t1',
            $this->templateYangDipakai(fn () => $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf?template=1")),
        );
    }

    public function test_cetak_massal_menyaring_yang_tidak_aktif_tanpa_menggagalkan_berkas(): void
    {
        $admin = $this->penggunaBerkunci(self::KUNCI_ADMIN);
        $aktifSatu = $this->buatPermitAktif($this->karyawanLain());
        $aktifDua = $this->buatPermitAktif($this->karyawanLain());
        $dicabut = $this->buatPermitAktif($this->karyawanLain());
        $this->cabut($dicabut);

        $response = $this->actingAs($admin)
            ->post('/laporanvisitor-permit/kartu-pdf-batch', ['ids' => [$aktifSatu, $dicabut, $aktifDua]]);

        $response->assertOk();
        $this->assertBerkasPdf($response);

        // Jumlah kartu terbaca dari nama berkasnya — satu-satunya bagian isi
        // PDF yang bisa ditagih tanpa membongkar biner dompdf.
        $this->assertStringContainsString(
            'kartu-permit-2-kartu.pdf',
            (string) $response->headers->get('content-disposition'),
            'Baris yang dicabut ikut tercetak, atau yang aktif justru terbuang.',
        );
    }

    public function test_cetak_massal_karyawan_hanya_mencetak_kartunya_sendiri(): void
    {
        // Pintu karyawan dipegang SEMUA karyawan (menu global); tanpa saringan
        // pemilik, Id berurutan cukup untuk mengunduh kartu ber-QR sah milik
        // orang lain (audit 27 Sep 2026, H1).
        [$user, $kode] = $this->pemohonBerkunci();
        $milikSendiri = $this->buatPermitAktif($kode);
        $milikOrangLain = $this->buatPermitAktif($this->karyawanLain());

        $response = $this->actingAs($user)
            ->post('/transaksipermit-kartu-pdf-batch', ['ids' => [$milikSendiri, $milikOrangLain]]);

        $response->assertOk();
        $this->assertStringContainsString(
            'kartu-permit-1-kartu.pdf',
            (string) $response->headers->get('content-disposition'),
            'Kartu milik orang lain ikut tercetak lewat pintu karyawan.',
        );
    }

    public function test_cetak_massal_karyawan_menolak_bila_semuanya_milik_orang_lain(): void
    {
        [$user] = $this->pemohonBerkunci();
        $milikOrangLain = $this->buatPermitAktif($this->karyawanLain());

        $this->actingAs($user)
            ->post('/transaksipermit-kartu-pdf-batch', ['ids' => [$milikOrangLain]])
            ->assertStatus(422);

        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Id_Transaksi_Permit', $milikOrangLain)->value('Kode_Kartu'),
            'Kode kartu milik orang lain tercipta sebagai efek samping permintaan yang ditolak.',
        );
    }

    public function test_cetak_massal_menolak_bila_tidak_ada_yang_aktif(): void
    {
        $admin = $this->penggunaBerkunci(self::KUNCI_ADMIN);
        $dicabut = $this->buatPermitAktif($this->karyawanLain());
        $this->cabut($dicabut);

        $this->actingAs($admin)
            ->post('/laporanvisitor-permit/kartu-pdf-batch', ['ids' => [$dicabut]])
            ->assertStatus(422);
    }

    public function test_cetak_massal_dibatasi_enam_puluh_kartu(): void
    {
        // Batasnya bukan selera: 61 kartu berarti 61 QR dirender dalam satu
        // permintaan web, dan yang jatuh duluan adalah batas waktu PHP.
        $admin = $this->penggunaBerkunci(self::KUNCI_ADMIN);

        // postJson, bukan post: validate() pada permintaan non-JSON MENGALIHKAN
        // kembali ke halaman asal (302), bukan membalas 422. Yang ditagih di
        // sini batasnya, jadi bentuk permintaannya harus yang menampakkannya.
        $this->actingAs($admin)
            ->postJson('/laporanvisitor-permit/kartu-pdf-batch', ['ids' => range(1, 61)])
            ->assertStatus(422);
    }

    public function test_pintu_admin_tertutup_bagi_karyawan_biasa(): void
    {
        [$user] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($this->karyawanLain());

        $this->assertNotSame(
            200,
            $this->actingAs($user)->get("/laporanvisitor-permit/{$id}/kartu-pdf")->getStatusCode(),
            'Kunci halaman monitoring tidak menahan karyawan biasa di pintu admin.',
        );
    }

    public function test_font_disubset_sehingga_kartu_tidak_membawa_seluruh_berkas_font(): void
    {
        // laravel-dompdf mematikan penyubsetan font secara bawaan, dan
        // akibatnya SELURUH DejaVuSans.ttf tertanam di tiap PDF: 860 KB untuk
        // satu kartu yang isinya dua puluh baris teks. Kartu ini diunduh di
        // ponsel di gerbang.
        //
        // Diukur RELATIF terhadap berkas fontnya, bukan terhadap angka KB yang
        // dihafal: ambang mutlak ikut merah begitu ada yang menambah satu logo,
        // dan yang dijaga di sini bukan ukuran melainkan "fontnya utuh atau
        // tidak". Dua keadaannya berjauhan sekali (±22 KB versus ±860 KB),
        // jadi separuh ukuran font adalah garis yang tidak mungkin tersenggol
        // perubahan tata letak yang wajar.
        $berkasFont = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        $this->assertFileExists($berkasFont, 'DejaVu tidak ada; ambang uji ini kehilangan dasarnya.');

        $ambang = (int) (filesize($berkasFont) / 2);

        [$user, $kode] = $this->pemohonBerkunci();
        $id = $this->buatPermitAktif($kode);

        $response = $this->actingAs($user)->get("/transaksipermit/{$id}/kartu-pdf");
        $response->assertOk();

        $ukuran = strlen((string) $response->getContent());

        $this->assertLessThan(
            $ambang,
            $ukuran,
            sprintf(
                'Kartu %s KB, lebih besar dari separuh DejaVuSans.ttf — fontnya tertanam utuh, '
                .'penyubsetan mati. Periksa setOption(isFontSubsettingEnabled) di balasKartuPdf().',
                number_format($ukuran / 1024, 1),
            ),
        );
    }

    public function test_cetak_massal_tidak_menanam_font_berulang(): void
    {
        // Yang dijaga: ukuran batch tumbuh mengikuti JUMLAH KARTU, bukan
        // melompat karena fontnya ikut lagi. Kalau penyubsetan mati, empat
        // kartu tetap ±865 KB — tandanya biaya tetapnya font, bukan isinya.
        $admin = $this->penggunaBerkunci(self::KUNCI_ADMIN);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->buatPermitAktif($this->karyawanLain());
        }

        $response = $this->actingAs($admin)
            ->post('/laporanvisitor-permit/kartu-pdf-batch', ['ids' => $ids]);

        $response->assertOk();

        $ambang = (int) (filesize(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf')) / 2);
        $this->assertLessThan($ambang, strlen((string) $response->getContent()));
    }

    // ---------------------------------------------------------------- helpers

    private function assertBerkasPdf($response): void
    {
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        // Pdf::stream() dompdf mengembalikan Response biasa dengan isi penuh,
        // BUKAN StreamedResponse — streamedContent() melempar di sini.
        $this->assertStringStartsWith('%PDF', substr((string) $response->getContent(), 0, 4));
    }

    /**
     * Nama view yang benar-benar dirender. Blade memancarkan 'composing: {view}'
     * untuk setiap view yang disusun, jadi tidak perlu membongkar PDF-nya.
     *
     * @param  callable(): mixed  $aksi
     */
    private function templateYangDipakai(callable $aksi): ?string
    {
        $terpakai = null;

        foreach (['t1', 't2'] as $varian) {
            $nama = 'exports.visitor-permit-kartu-'.$varian;
            Event::listen('composing: '.$nama, function () use ($nama, &$terpakai) {
                $terpakai = $nama;
            });
        }

        $aksi();

        return $terpakai;
    }

    private function cabut(int $id): void
    {
        $nomor = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Id_Transaksi_Permit', $id)->value('No_Transaksi');

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => 'ZZKP01',
            'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'DIBATALKAN',
            'Flag_Batal' => 'Y',
            'Flag_Sync' => 'T',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subDay(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
        ]);
    }

    /** @return array{0: User, 1: string} */
    private function pemohonBerkunci(): array
    {
        $user = $this->penggunaBerkunci(self::KUNCI_PEMOHON);
        $kode = (string) DB::table('Karyawan')->where('UserID_Web', $user->Id_Users)->value('Kode_Karyawan');

        $this->assertNotSame('', $kode, 'Pengguna uji tidak tertaut ke Karyawan; kepemilikan permit tak bisa diuji.');

        return [$user, $kode];
    }

    private function penggunaBerkunci(string $kunci): User
    {
        // Hanya pengguna yang BERTAUT ke Karyawan: kepemilikan permit dan
        // Nama_Pemohon (NOT NULL) sama-sama lahir dari JOIN ke tabel itu.
        $user = User::query()
            ->whereIn('Id_Users', fn ($q) => $q->select('UserID_Web')->from('Karyawan')->whereNotNull('UserID_Web'))
            ->whereNotIn('Id_Users', fn ($q) => $q->select('UserId_Web')->from('N_HRIS_Role_User')->where('Flag_Aktif', 'Y'))
            ->orderBy('Id_Users')
            ->skip(self::$urutanPengguna++)
            ->first();

        $this->assertNotNull($user, 'Tidak ada user tanpa role yang bertaut ke Karyawan.');

        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001',
            'ID_Level' => 5,
            'ID_Divisi' => 22,
            'Jenis_page' => $kunci,
            'UserID_Web' => $user->Id_Users,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);

        return $user->fresh();
    }

    /** Karyawan mana pun selain pengguna uji, untuk permit "milik orang lain". */
    private function karyawanLain(): string
    {
        $kode = DB::table('Karyawan')->orderByDesc('Kode_Karyawan')->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Tidak ada karyawan untuk dijadikan pemilik pembanding.');

        return (string) $kode;
    }

    /**
     * Permit yang terbaca APPROVED oleh permitStatusSql(): disetujui, dan
     * masa berlakunya lengkap serta belum lewat.
     */
    private function buatPermitAktif(string $kodeKaryawan, bool $disetujui = true): int
    {
        $nomor = 'PMT-KRT-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $kodeKaryawan,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $disetujui ? 'Y' : null,
            'Approved_At' => $disetujui ? now()->subDay() : null,
            'Created_At' => now()->subDays(2),
            'Created_By' => $kodeKaryawan,
            'Idempotency_Key' => 'KRT-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'KP',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        return (int) $id;
    }
}
