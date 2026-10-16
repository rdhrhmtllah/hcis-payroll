<?php

namespace Tests\Feature\VISITOR\Workflow\Verification;

use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifikator melampirkan bukti pemeriksaan dalam SATU permintaan.
 *
 * Sebelumnya setiap gambar dikirim sendiri begitu dipilih: tiga foto berarti
 * tiga round-trip dan tiga unggahan GCS berurutan di tengah pemeriksaan. Kini
 * berkasnya ditahan di layar dan dikirim sekaligus lewat `dokumen[JENIS]`.
 *
 * Dua hal yang dijaga di sini tidak akan menyalakan galat kalau rusak:
 *
 *  - Ejaan jenis dokumen. Yang tersimpan di basis data adalah FOTO_KENDARAAN,
 *    STNK, dan SIM; validasi lama hanya mengenal FOTO_STNK/FOTO_SIM sehingga
 *    setiap penggantian dokumen STNK dibalas 422 tanpa penjelasan.
 *  - Tautan ke baris detail permit. Dokumen dengan Id_Detail_Permit kosong
 *    tertinggal ketika detailnya dibersihkan.
 */
class UnggahDokumenPermitTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    private ?User $verifikator = null;

    private const URL = '/verifikator-permit-upload-dokumen';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('gcs');
    }

    public function test_tiga_berkas_tersimpan_dalam_satu_permintaan(): void
    {
        [$no] = $this->permitMenungguReview();

        $this->berkasUji($no, [
            'FOTO_KENDARAAN' => UploadedFile::fake()->image('kendaraan.jpg'),
            'STNK' => UploadedFile::fake()->image('stnk.jpg'),
            'SIM' => UploadedFile::fake()->image('sim.jpg'),
        ])->assertStatus(200);

        $tersimpan = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
            ->where('No_Transaksi', $no)
            ->pluck('Jenis_Dokumen')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['FOTO_KENDARAAN', 'SIM', 'STNK'], $tersimpan);
    }

    public function test_ejaan_lama_jenis_dokumen_tetap_diterima_dan_diseragamkan(): void
    {
        [$no] = $this->permitMenungguReview();

        $this->berkasUji($no, ['FOTO_STNK' => UploadedFile::fake()->image('stnk.jpg')])->assertStatus(200);

        $this->assertSame(
            'STNK',
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->value('Jenis_Dokumen'),
            'Ejaan lama tersimpan apa adanya; dokumennya tidak akan pernah bertemu pembacanya.',
        );
    }

    public function test_dokumen_tertaut_ke_baris_detail_permitnya(): void
    {
        [$no, $idDetail] = $this->permitMenungguReview();

        $this->berkasUji($no, ['SIM' => UploadedFile::fake()->image('sim.jpg')])->assertStatus(200);

        $this->assertSame(
            $idDetail,
            (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->value('Id_Detail_Permit'),
        );
    }

    public function test_kiriman_kedua_menimpa_jenis_yang_sama_bukan_menggandakannya(): void
    {
        [$no] = $this->permitMenungguReview();

        $this->berkasUji($no, ['STNK' => UploadedFile::fake()->image('stnk-1.jpg')])->assertStatus(200);
        $this->berkasUji($no, ['STNK' => UploadedFile::fake()->image('stnk-2.jpg')])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->count(),
        );
    }

    public function test_permintaan_tanpa_berkas_ditolak(): void
    {
        [$no] = $this->permitMenungguReview();

        $this->actingAs($this->penggunaVerifikator())
            ->postJson(self::URL, ['no_transaksi' => $no])
            ->assertStatus(422);
    }

    public function test_jenis_dokumen_karangan_ditolak(): void
    {
        [$no] = $this->permitMenungguReview();

        $this->berkasUji($no, ['KTP_TAMU' => UploadedFile::fake()->image('ktp.jpg')])->assertStatus(422);

        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->count(),
        );
    }

    /** @param array<string, UploadedFile> $berkas */
    private function berkasUji(string $no, array $berkas): \Illuminate\Testing\TestResponse
    {
        $payload = ['no_transaksi' => $no];

        foreach ($berkas as $jenis => $file) {
            $payload['dokumen'][$jenis] = $file;
        }

        return $this->actingAs($this->penggunaVerifikator())->post(self::URL, $payload);
    }

    /**
     * Pengguna NYATA yang benar-benar berhak membuka meja verifikator.
     *
     * Dua syarat harus dipenuhi sekaligus dan keduanya gagal dengan diam-diam
     * kalau dikarang: rutenya dijaga `check.jabatan:visitorVerifikatorPage`
     * (gagal = 403, bukan pesan yang menyebut halamannya), dan requesterKode()
     * menurunkan kode karyawan lewat relasi `karyawan` pada model User (objek
     * palsu tidak punya relasi itu dan gagal sebelum menyentuh controller).
     */
    private function penggunaVerifikator(): User
    {
        if ($this->verifikator !== null) {
            return $this->verifikator;
        }

        $userId = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'visitorVerifikatorPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $this->markTestSkipped('Tak ada user aktif dengan visitorVerifikatorPage di database development.');
        }

        return $this->verifikator = $user;
    }

    /** Kode karyawan milik verifikator di atas; dialah approver fixture-nya. */
    private function kodeVerifikator(): string
    {
        return (string) app(VisitorApprovalPolicyService::class)->requesterKode($this->penggunaVerifikator());
    }

    /** @return array{0:string,1:int} */
    private function permitMenungguReview(): array
    {
        $no = 'PMT-DOK-'.Str::upper(Str::random(8));

        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Nama_Kategori', 'COKLAT')
            ->value('Id_Kategori_Permit');

        $headerId = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'DOK-'.Str::upper(Str::random(20)),
        ]);

        $idDetail = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
            'Id_Transaksi_Permit' => $headerId,
            'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'DK',
            'Id_Master_Kategori_Permit' => $kategori,
            'Snapshot_Kategori_Permit' => 'COKLAT',
        ]);

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no,
            'Kode_Karyawan' => self::PIC,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => $this->kodeVerifikator(),
            'Flag_Approval' => null,
            'created_at' => now(),
            'update_at' => now(),
        ]);

        return [$no, $idDetail];
    }
}
