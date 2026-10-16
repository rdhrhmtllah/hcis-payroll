<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use App\Http\Middleware\HrisApiGuard;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\SuntikAplikasiApi;
use Tests\TestCase;

/**
 * Status dan pembatalan permit mitra.
 *
 * Uji terpenting di berkas ini adalah ISOLASI: mitra A tidak boleh membaca
 * maupun membatalkan permit mitra B. Kegagalannya tidak menyalakan galat apa
 * pun — yang terjadi adalah data satu pelanggan terbaca pelanggan lain.
 * Balasannya 404, bukan 403, karena 403 mengonfirmasi bahwa nomornya ada.
 */
class PermitTamuApiStatusBatalTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HrisApiGuard::class);

        // Harus NAMA KELAS, bukan closure: closure yang didorong ke grup
        // middleware membuat resolver melempar "Illegal offset type in isset or
        // empty" — galat yang tidak menyebut closure sama sekali.
        $this->app['router']->pushMiddlewareToGroup('api', SuntikAplikasiApi::class);
    }

    protected function tearDown(): void
    {
        SuntikAplikasiApi::reset();

        parent::tearDown();
    }

    public function test_status_permit_sendiri_terbaca(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();
        $this->buatLangkahApproval($no);

        $isi = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json();

        $this->assertTrue($isi['sukses']);
        $this->assertSame('PENDING', $isi['result']['status']);
        $this->assertSame($no, $isi['result']['no_transaksi']);
        $this->assertNotNull($isi['result']['tanggal_berlaku']);
        $this->assertSame('1 Hari', $isi['result']['durasi']);
    }

    public function test_status_internal_tidak_bocor_ke_mitra(): void
    {
        // Permit tanpa satu pun langkah approval berstatus NO_FLOW di dalam
        // HCIS. Itu kosakata internal yang tidak pernah dijanjikan ke mitra;
        // membocorkannya memaksa mereka menangani nilai yang tak terdokumentasi,
        // dan mengunci kita dari mengubah nama status internal kelak.
        [$app, $no] = $this->permitMilikAplikasiBaru();

        // ->first(), bukan ->value(): value() mengganti daftar select dengan
        // satu kolom, dan Status_Permit adalah ALIAS ekspresi CASE — bukan kolom
        // yang bisa dipilih sendirian. Gejalanya bukan galat, melainkan nilai
        // kolom lain yang terbaca.
        $internal = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
            ->basePermitQuery()->where('h.No_Transaksi', $no)->first();

        $this->assertSame(
            'NO_FLOW',
            $internal->Status_Permit,
            'Prasyarat berubah: permit tanpa alur approval tidak lagi NO_FLOW.',
        );

        $this->sebagai($app)->getJson($this->url($no))
            ->assertStatus(200)
            ->assertJsonPath('result.status', 'PENDING');
    }

    public function test_status_yang_dijanjikan_saja_yang_pernah_muncul(): void
    {
        $sah = ['PENDING', 'APPROVED', 'REJECTED', 'REVOKED', 'EXPIRED'];

        foreach ([null, 'Y', 'T'] as $flag) {
            [$app, $no] = $this->permitMilikAplikasiBaru();
            $this->buatLangkahApproval($no);

            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)
                ->update(['Flag_Approval' => $flag]);

            if ($flag === 'Y') {
                DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->update([
                    'Tanggal_Mulai_Berlaku' => now()->toDateString(),
                    'Tanggal_Kadaluarsa' => now()->addDay()->toDateString(),
                ]);
            }

            $status = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json('result.status');

            $this->assertContains($status, $sah, "Status '{$status}' tidak ada dalam kontrak mitra.");
        }
    }

    public function test_status_permit_mitra_lain_kini_terbaca(): void
    {
        // Keputusan user 2026-09-14: pengecekan status melayani SEMUA permit,
        // bukan hanya milik pemanggilnya. Yang dibuka hanya KEADAAN permitnya.
        [, $no] = $this->permitMilikAplikasiBaru();
        $lain = $this->buatAplikasi();

        $this->sebagai($lain)->getJson($this->url($no))->assertStatus(200);
    }

    public function test_status_tidak_memulangkan_identitas_siapa_pun(): void
    {
        // Inilah batas yang menggantikan penyaring pemilik. Kalau penjaga ini
        // merah, melonggarkan cakupan endpoint berubah menjadi kebocoran data
        // karyawan ke aplikasi pihak ketiga — dan tidak ada galat yang
        // menyalakannya.
        $app = $this->buatAplikasi();
        $no = $this->buatPermitHcis();

        $hasil = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json('result');

        $this->assertSame(
            ['no_transaksi', 'status', 'alasan', 'tanggal_berlaku', 'durasi', 'sumber', 'referensi_mitra'],
            array_keys($hasil),
            'Bentuk respons status berubah; periksa apakah ada kolom identitas yang ikut terbuka.',
        );
        $this->assertSame('KARYAWAN', $hasil['sumber']);
    }

    public function test_sumber_permit_membedakan_ketiga_jenisnya(): void
    {
        [$app, $noMitra] = $this->permitMilikAplikasiBaru();

        $this->assertSame(
            'API',
            $this->sebagai($app)->getJson($this->url($noMitra))->assertStatus(200)->json('result.sumber'),
        );
        $this->assertSame(
            'KARYAWAN',
            $this->sebagai($app)->getJson($this->url($this->buatPermitHcis()))->assertStatus(200)->json('result.sumber'),
        );
    }

    public function test_pembatalan_permit_mitra_lain_dibalas_404_dan_tidak_menyentuhnya(): void
    {
        [, $no] = $this->permitMilikAplikasiBaru();
        $lain = $this->buatAplikasi();

        $this->sebagai($lain)->postJson($this->url($no).'/batal')->assertStatus(404);

        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
            'Permit mitra lain ikut tersentuh.',
        );
    }

    public function test_pembatalan_saat_pending_menutup_alurnya(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();

        $this->sebagai($app)->postJson($this->url($no).'/batal')->assertStatus(200);

        $this->assertSame(
            'T',
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
        );
    }

    public function test_pembatalan_saat_approved_mencabut_izin_efektifnya(): void
    {
        [$app, $no] = $this->permitMilikAplikasiBaru();

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)
            ->update(['Flag_Approval' => 'Y', 'Approved_At' => now()]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->update([
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->toDateString(),
        ]);
        app(VisitorEfektifService::class)->syncPermitEfektif($no);

        $this->sebagai($app)->postJson($this->url($no).'/batal')->assertStatus(200);

        $this->assertSame(
            'DIBATALKAN',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_permit_ditolak_membawa_alasan_penolakannya(): void
    {
        // Spec menjanjikan `alasan` terisi untuk REJECTED; dulu selalu null karena
        // hanya alasan pencabutan yang dibaca (audit M9). Termasuk penutupan otomatis.
        [$app, $no] = $this->permitMilikAplikasiBaru();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'T']);
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no, 'Kode_Karyawan' => 'A1', 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => 'A1', 'Flag_Approval' => 'T', 'Catatan' => 'Kendaraan tidak layak',
            'Tanggal_Approval' => now(), 'created_at' => now(), 'update_at' => now(),
        ]);

        $hasil = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json('result');

        $this->assertSame('REJECTED', $hasil['status']);
        $this->assertSame('Kendaraan tidak layak', $hasil['alasan']);
    }

    public function test_alasan_pada_permit_karyawan_tidak_dibuka_ke_mitra(): void
    {
        // Endpoint ini sengaja melayani semua nomor, tetapi catatan internal
        // verifikator tentang permit KARYAWAN bukan urusan mitra mana pun (audit M2).
        [$app] = $this->permitMilikAplikasiBaru();
        $no = $this->buatPermitHcis();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'Y']);
        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1',
            'Nama_Pemohon' => 'Uji', 'Status_Efektif' => 'DIBATALKAN', 'Jenis_Akses' => 'PERMIT',
            'Alasan_Status' => 'Catatan internal pencabutan',
            'Tanggal_Mulai_Berlaku' => now()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addMonth()->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now(), 'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(), 'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Flag_Sync' => 'Y', 'Updated_At' => now(),
        ]);

        $hasil = $this->sebagai($app)->getJson($this->url($no))->assertStatus(200)->json('result');

        $this->assertSame('KARYAWAN', $hasil['sumber']);
        $this->assertSame('REVOKED', $hasil['status']);
        $this->assertNull($hasil['alasan']);
    }

    public function test_nomor_yang_tidak_ada_dibalas_404(): void
    {
        $app = $this->buatAplikasi();

        $this->sebagai($app)->getJson($this->url('PMT-TIDAK-ADA'))->assertStatus(404);
    }

    public function test_permit_karyawan_tidak_bisa_dibatalkan_lewat_api(): void
    {
        // Membaca keadaan sebuah nomor tidak merugikan siapa pun; mencabut izin
        // akses fisik milik orang lain adalah kerusakan yang baru ketahuan
        // ketika pemiliknya sudah berdiri di gerbang. Karena itu pembatalan
        // TIDAK ikut dilonggarkan bersama status.
        $app = $this->buatAplikasi();
        $no = $this->buatPermitHcis();

        $this->sebagai($app)->postJson($this->url($no).'/batal')->assertStatus(404);

        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'),
            'Permit karyawan ikut tersentuh lewat endpoint mitra.',
        );
    }

    private function url(string $no): string
    {
        return '/api/v1/hcis/visitor/permit-tamu/'.$no;
    }

    private function sebagai(int $idAplikasi): self
    {
        SuntikAplikasiApi::$idAplikasi = $idAplikasi;

        return $this;
    }

    /** @return array{0:int,1:string} */
    private function permitMilikAplikasiBaru(): array
    {
        $app = $this->buatAplikasi();

        return [$app, $this->buatPermit($app)];
    }

    private function buatPermitHcis(): string
    {
        return $this->buatPermit(null);
    }

    private function buatPermit(?int $idAplikasi): string
    {
        $nomor = 'PMT-API-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => $idAplikasi ? 'API' : 'HCIS',
            'Id_Api_Aplikasi' => $idAplikasi,
            'Referensi_Mitra' => $idAplikasi ? 'GUEST-'.Str::random(6) : null,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'API-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'MT',
            'Nama_Tamu' => $idAplikasi ? 'Tamu Mitra' : null,
            'Instansi_Tamu' => $idAplikasi ? 'PT Mitra' : null,
            'Snapshot_Nama_Durasi' => $idAplikasi ? '1 Hari' : null,
            'Tanggal_Mulai_Berlaku' => $idAplikasi ? now()->toDateString() : null,
            'Tanggal_Kadaluarsa' => $idAplikasi ? now()->toDateString() : null,
            'Waktu_Mulai_Berlaku' => $idAplikasi ? now()->startOfDay()->format('Y-m-d H:i:s') : null,
            'Waktu_Berakhir' => $idAplikasi ? now()->endOfDay()->format('Y-m-d H:i:s') : null,
        ]);

        return $nomor;
    }

    /**
     * Satu langkah approval yang masih menunggu.
     *
     * Tanpa baris ini permit berstatus NO_FLOW, bukan PENDING — dan fixture
     * yang tidak punya langkah approval tidak menyerupai keadaan produksi,
     * karena createFlow() selalu membuatnya saat pengajuan diterima.
     */
    private function buatLangkahApproval(string $noTransaksi): void
    {
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan' => self::PIC,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => self::PIC,
            'Flag_Approval' => null,
            'created_at' => now(),
            'update_at' => now(),
        ]);
    }

    private function buatAplikasi(): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => self::PIC,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }
}
