<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/** Daftar rombongan harus sampai ke proyeksi gerbang dan kedua pembaca tamu. */
class DaftarAnggotaProyeksiTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_daftar_sampai_ke_proyeksi_tamu(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $json = DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $nomor)
            ->value('Daftar_Anggota_Json');
        $daftar = app(DaftarAnggotaRombongan::class)->dariJson($json);

        $this->assertCount(2, $daftar);
        $this->assertSame('Siti Aminah', $daftar[0]['nama']);
    }

    public function test_jumlah_di_proyeksi_sama_dengan_jumlah_tamu(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->assertSame(
            3,
            (int) DB::table('N_VISITOR_Tamu_Efektif')
                ->where('No_Transaksi', $nomor)
                ->value('Jumlah_Tamu'),
        );
    }

    public function test_kedua_base_tamu_query_membawa_kolomnya(): void
    {
        $nomor = $this->buatTamuBerdaftar();

        $dariService = app(VisitorPermitQueryService::class)
            ->baseTamuQuery()
            ->where('h.No_Transaksi', $nomor)
            ->first();

        $controller = app(GuestRegistrationController::class);
        $method = new ReflectionMethod($controller, 'baseTamuQuery');
        $dariController = $method->invoke($controller)
            ->where('h.No_Transaksi', $nomor)
            ->first();

        $this->assertNotNull($dariService->Daftar_Anggota_Json);
        $this->assertNotNull($dariController->Daftar_Anggota_Json);
    }

    public function test_registrasi_lama_tanpa_daftar_tetap_terbaca(): void
    {
        $nomor = $this->buatTamuBerdaftar(tanpaDaftar: true);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $baris = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNull($baris->Daftar_Anggota_Json);
        $this->assertSame(4, (int) $baris->Jumlah_Tamu);
        $this->assertSame([], app(DaftarAnggotaRombongan::class)->dariJson($baris->Daftar_Anggota_Json));
    }

    private function buatTamuBerdaftar(bool $tanpaDaftar = false): string
    {
        $nomor = 'VSR-ANG-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            // Kode tersimpan sejak DDL-2026-121 (dulu 'TERJADWAL').
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-15',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ANG-'.Str::upper(Str::random(20)),
        ]);

        $service = app(DaftarAnggotaRombongan::class);
        $daftar = $service->susunApaAdanya([['nama' => 'Siti Aminah'], ['nama' => 'Budi']]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Rudi Hartono',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => $tanpaDaftar ? 4 : 3,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
            'Waktu_Mulai_Kunjungan' => '2026-09-15 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-15 17:00:00',
            'Daftar_Anggota_Json' => $tanpaDaftar ? null : $service->keJson($daftar),
        ]);

        return $nomor;
    }
}
