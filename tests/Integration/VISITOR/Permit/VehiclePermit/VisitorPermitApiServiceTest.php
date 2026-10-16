<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aturan yang hanya berlaku bagi pengajuan permit dari aplikasi mitra.
 *
 * Dua di antaranya menggantikan mesin permit karyawan yang tidak masuk akal di
 * sini: kuota per karyawan (seluruh permit satu mitra memakai satu PIC, jadi
 * permit ketiga akan ditolak "jatah penuh") dan durasi bulanan.
 */
class VisitorPermitApiServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: proyeksi permit menulis kolom nama yang
     * NOT NULL dan me-resolve isinya lewat JOIN ke tabel Karyawan.
     */
    private const PIC = 'A1';

    public function test_pic_dipakai_bila_pendaftarannya_menunjuknya(): void
    {
        $id = $this->buatAplikasi(self::PIC);

        $identitas = app(VisitorPermitApiService::class)->identitasPemohon($id);

        $this->assertSame(self::PIC, $identitas['kode']);
        $this->assertNotSame('', $identitas['nama']);
    }

    public function test_tanpa_pic_pemohonnya_adalah_aplikasinya(): void
    {
        // PIC opsional. Permit mitra memang tidak diajukan karyawan mana pun,
        // jadi aplikasinya sendiri yang jadi pemohon — bukan karyawan karangan,
        // dan bukan penolakan.
        $id = $this->buatAplikasi(null);
        $nama = DB::table('N_HRIS_Api_Aplikasi')->where('Id_Api_Aplikasi', $id)->value('Nama_Aplikasi');

        $identitas = app(VisitorPermitApiService::class)->identitasPemohon($id);

        $this->assertSame('API#'.$id, $identitas['kode']);
        $this->assertSame($nama, $identitas['nama']);
    }

    public function test_penanda_pemohon_muat_di_kolom_proyeksi(): void
    {
        // Kode_Karyawan_Pemohon di N_VISITOR_Permit_Efektif varchar(20) dan NOT
        // NULL. Penanda yang kepanjangan akan terpotong senyap atau menggagalkan
        // sisipan saat permit pertama terbit, bukan saat kode ini ditulis.
        $id = $this->buatAplikasi(null);

        $this->assertLessThanOrEqual(
            20,
            strlen(app(VisitorPermitApiService::class)->identitasPemohon($id)['kode']),
        );
    }

    public function test_aplikasi_yang_tidak_ada_ditolak(): void
    {
        $this->expectException(\DomainException::class);
        app(VisitorPermitApiService::class)->identitasPemohon(999999);
    }

    public function test_hitungan_permit_aktif_hanya_milik_aplikasi_itu(): void
    {
        $a = $this->buatAplikasi(self::PIC);
        $b = $this->buatAplikasi(self::PIC);

        $this->buatPermitApi($a, null);   // PENDING — menahan jatah
        $this->buatPermitApi($a, 'Y');    // APPROVED — menahan jatah
        $this->buatPermitApi($a, 'T');    // REJECTED — membebaskan jatah
        $this->buatPermitApi($b, null);

        $svc = app(VisitorPermitApiService::class);

        $this->assertSame(2, $svc->permitAktif($a), 'Jatah bocor lintas aplikasi.');
        $this->assertSame(1, $svc->permitAktif($b));
    }

    public function test_pengajuan_pending_ikut_terhitung(): void
    {
        // Flag_Approval NULL. Kalau penyaringnya memakai whereIn, NULL tidak
        // pernah cocok dan seluruh antrean pending luput dihitung — batasnya
        // jadi tidak pernah mengikat.
        $a = $this->buatAplikasi(self::PIC);
        $this->buatPermitApi($a, null);

        $this->assertSame(1, app(VisitorPermitApiService::class)->permitAktif($a));
    }

    public function test_permit_yang_dihapus_tidak_menahan_jatah(): void
    {
        $a = $this->buatAplikasi(self::PIC);
        $no = $this->buatPermitApi($a, null);

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Aktif' => 'N']);

        $this->assertSame(0, app(VisitorPermitApiService::class)->permitAktif($a));
    }

    public function test_permit_disetujui_yang_sudah_berakhir_tidak_menahan_jatah(): void
    {
        // Flag_Approval tetap 'Y' selamanya; yang menentukan adalah status
        // kanonisnya. Dulu permit kedaluwarsa & dicabut terus menahan jatah
        // sampai mitra ditolak 429 permanen (audit ulang 28 Sep).
        $a = $this->buatAplikasi(self::PIC);

        $habis = $this->buatPermitApi($a, 'Y');
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $habis)->update([
            'Tanggal_Mulai_Berlaku' => now()->subDays(2)->toDateString(),
            'Tanggal_Kadaluarsa' => now()->subDay()->toDateString(),
            'Waktu_Mulai_Berlaku' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $berlaku = $this->buatPermitApi($a, 'Y');
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $berlaku)->update([
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(1, app(VisitorPermitApiService::class)->permitAktif($a));
    }

    private function buatAplikasi(?string $pic): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => $pic,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function buatPermitApi(int $idAplikasi, ?string $flagApproval): string
    {
        $nomor = 'PMT-API-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $flagApproval,
            'Sumber_Pengajuan' => 'API',
            'Id_Api_Aplikasi' => $idAplikasi,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'API-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Mitra',
            'Instansi_Tamu' => 'PT Mitra',
            'Waktu_Mulai_Berlaku' => now()->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        return $nomor;
    }
}
