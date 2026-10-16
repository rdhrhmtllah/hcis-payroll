<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\KartuPermitQr;
use App\Services\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * API cek kartu permit untuk layanan cek kartu publik — lewat hris.api.guard
 * SUNGGUHAN (tidak dilewati): permintaan ditandatangani persis seperti yang
 * dilakukan layanan publik, sehingga uji ini juga kontrak penandatanganannya.
 */
class KartuPermitPublikApiTest extends TestCase
{
    use DatabaseTransactions;

    private const POLA = '/api/v1/hcis/visitor/kartu-permit/{kode}';

    private string $public;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->public = 'pub-kartu-'.Str::random(16);
        $this->secret = 'sec-kartu-'.Str::random(40);

        $grup = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'VISITOR')->value('Id_Grup_Fitur')
            ?: (int) DB::table('N_HRIS_Api_Aplikasi')->value('Id_Grup_Fitur');

        $idApp = (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => $grup, 'Nama_Aplikasi' => 'Uji Cek Kartu '.Str::random(6),
            'Api_Public' => $this->public, 'Api_Secret' => $this->secret,
            'Arah' => 'IN', 'Created_At' => now(), 'Flag_Aktif' => 'Y',
        ]);

        $idEndpoint = DB::table('N_HRIS_Api_Endpoint')->where('Url_Route', self::POLA)->where('Method', 'GET')->value('Id_Api_Endpoint')
            ?: DB::table('N_HRIS_Api_Endpoint')->insertGetId([
                'Id_Grup_Fitur' => $grup, 'Url_Route' => self::POLA, 'Method' => 'GET', 'Arah' => 'IN',
                'Deskripsi' => 'Uji cek kartu publik', 'Created_At' => now(), 'Flag_Aktif' => 'Y',
            ]);

        DB::table('N_HRIS_Api_Akses_Endpoint')->insert([
            'Id_Api_Aplikasi' => $idApp, 'Id_Api_Endpoint' => $idEndpoint, 'Created_At' => now(), 'Flag_Aktif' => 'Y',
        ]);

        RateLimiter::clear('kartu-publik-app:'.$idApp);
    }

    /** Penandatanganan kanonis HrisApiGuard — sama persis dengan layanan publik. */
    private function panggil(string $jalur, ?string $secret = null): TestResponse
    {
        $timestamp = now()->toIso8601String();
        $nonce = (string) Str::uuid();
        $bodyHash = hash('sha256', '');
        $payload = "GET\n{$jalur}\n\n{$timestamp}\n{$nonce}\n{$bodyHash}\n{$this->public}";

        return $this->withHeaders([
            'X-HC-Public' => $this->public,
            'X-HC-Timestamp' => $timestamp,
            'X-HC-Nonce' => $nonce,
            'X-HC-Body-Hash' => $bodyHash,
            'X-HC-Signature' => hash_hmac('sha512', $payload, $secret ?? $this->secret),
            'Accept' => 'application/json',
        ])->get($jalur); // GET tanpa body: getJson() mengirim "[]" dan mengubah hash body.
    }

    private function kodeKartuPermitAktif(): string
    {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')->where('h.Flag_Approval', 'Y')
            ->whereNotNull('d.Plat_Nomor_Angka')
            ->whereDate('d.Tanggal_Kadaluarsa', '>=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('pe.Status_Efektif')->orWhere('pe.Status_Efektif', '<>', 'DIBATALKAN'))
            ->value('h.Id_Transaksi_Permit');

        if (! $id) {
            $this->markTestSkipped('Tidak ada permit aktif berplat di DB uji.');
        }

        return app(KartuPermitQr::class)->kode((int) $id);
    }

    public function test_kartu_aktif_dijawab_dengan_data_minimal(): void
    {
        $kode = $this->kodeKartuPermitAktif();

        $isi = $this->panggil("/api/v1/hcis/visitor/kartu-permit/{$kode}")->assertOk()->json('result');

        $this->assertTrue($isi['sah']);
        $this->assertSame('AKTIF', $isi['status']);
        $this->assertStringContainsString('••', (string) $isi['plat'], 'Plat harus disamarkan.');
        $this->assertSame(
            ['sah', 'status', 'judul', 'sebab', 'kategori', 'plat', 'berlaku_sejak', 'berlaku_sampai', 'dicek'],
            array_keys($isi),
            'Jawaban publik tidak boleh bertambah kolom (nama, NIK, departemen, nomor transaksi, Id).',
        );
    }

    public function test_kartu_dicabut_terbaca_tidak_berlaku_tanpa_catatan_internal(): void
    {
        $kode = $this->kodeKartuPermitAktif();
        $id = app(KartuPermitQr::class)->bacaId($kode);
        $no = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Id_Transaksi_Permit', $id)->value('No_Transaksi');
        DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)
            ->update(['Status_Efektif' => 'DIBATALKAN', 'Alasan_Status' => 'CATATAN INTERNAL RAHASIA']);

        $isi = $this->panggil("/api/v1/hcis/visitor/kartu-permit/{$kode}")->assertOk()->json('result');

        $this->assertFalse($isi['sah']);
        $this->assertSame('DICABUT', $isi['status']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($isi));
    }

    public function test_kode_tak_dikenal_dijawab_tidak_dikenali(): void
    {
        $isi = $this->panggil('/api/v1/hcis/visitor/kartu-permit/'.str_repeat('0', 32))->assertOk()->json('result');

        $this->assertFalse($isi['sah']);
        $this->assertSame('TIDAK_DIKENALI', $isi['status']);
    }

    public function test_tanda_tangan_salah_ditolak(): void
    {
        $this->panggil('/api/v1/hcis/visitor/kartu-permit/'.str_repeat('0', 32), 'rahasia-lain')->assertStatus(401);
    }

    public function test_bentuk_kode_bukan_32_heksa_tidak_punya_rute(): void
    {
        $this->panggil('/api/v1/hcis/visitor/kartu-permit/bukan-kode')->assertNotFound();
    }

    public function test_batas_laju_dihitung_per_aplikasi(): void
    {
        config(['services.visitor.kartu_publik_per_menit' => 2]);
        $jalur = '/api/v1/hcis/visitor/kartu-permit/'.str_repeat('0', 32);

        $this->panggil($jalur)->assertOk();
        $this->panggil($jalur)->assertOk();
        $this->panggil($jalur)->assertStatus(429);
    }

    public function test_plat_disamarkan_dua_digit_terakhir(): void
    {
        $this->assertSame('BG 12•• XY', VerifikasiKartuPermitService::samarkanPlat('BG', '1234', 'XY'));
        $this->assertSame('B •• A', VerifikasiKartuPermitService::samarkanPlat('B', '12', 'A'));
        $this->assertNull(VerifikasiKartuPermitService::samarkanPlat(null, null, null));
    }

    public function test_isi_qr_memakai_url_publik_dan_pemindai_hcis_membaca_keduanya(): void
    {
        $kode = str_repeat('a', 32);

        config(['services.visitor.kartu_publik_url' => '']);
        $this->assertSame($kode, KartuPermitQr::isiQr($kode));

        config(['services.visitor.kartu_publik_url' => 'https://cek-kartu.contoh.id/k/']);
        $this->assertSame('https://cek-kartu.contoh.id/k/'.$kode, KartuPermitQr::isiQr($kode));

        $this->assertSame($kode, KartuPermitQr::kodeDariIsiQr('https://cek-kartu.contoh.id/k/'.$kode));
        $this->assertSame($kode, KartuPermitQr::kodeDariIsiQr('https://cek-kartu.contoh.id/k/'.$kode.'?utm=x#y'));
        $this->assertSame($kode, KartuPermitQr::kodeDariIsiQr("  {$kode}  "));
    }
}
