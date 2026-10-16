<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mengubah daftar harus memperbarui transaksi, jumlah, dan proyeksi gerbang. */
class UbahDaftarAnggotaTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_mengubah_daftar_memperbarui_transaksi_dan_proyeksi(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['daftar_anggota' => [['nama' => 'Siti Aminah']]]);

        $service = app(DaftarAnggotaRombongan::class);
        $transaksi = $service->dariJson(DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json'));
        $proyeksi = $service->dariJson(DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json'));

        $this->assertCount(1, $transaksi);
        $this->assertSame($transaksi, $proyeksi);
    }

    public function test_jumlah_ikut_diturunkan_ulang(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, [
            'daftar_anggota' => [['nama' => 'Siti'], ['nama' => 'Budi'], ['nama' => 'Andi']],
        ]);

        $this->assertSame(4, (int) DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'));
        $this->assertSame(4, (int) DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $nomor)->value('Jumlah_Tamu'));
    }

    public function test_permintaan_tanpa_daftar_tidak_menghapus_daftar_yang_ada(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['tanggal_kunjungan' => '2026-09-20']);

        $daftar = app(DaftarAnggotaRombongan::class)->dariJson(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('No_Transaksi', $nomor)
                ->value('Daftar_Anggota_Json'),
        );

        $this->assertCount(2, $daftar);
    }

    public function test_daftar_tidak_sah_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        [, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['daftar_anggota' => [['nama' => '  ']]]);
    }

    public function test_pic_lain_tidak_bisa_mengubah_daftar(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json');

        $request = Request::create('/tamu/'.$id, 'PUT', [
            'daftar_anggota' => [['nama' => 'Penyusup']],
        ]);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => 'PIC-LAIN']);

        $response = app(GuestRegistrationController::class)->update($request, (string) $id);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($sebelum, DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)->value('Daftar_Anggota_Json'));
    }

    /** @param array<string, mixed> $payload */
    private function ubah(int $id, array $payload): void
    {
        $request = Request::create('/tamu/'.$id, 'PUT', $payload);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        app(GuestRegistrationController::class)->update($request, (string) $id);
    }

    /** @return array{0: string, 1: int} */
    private function buatTamuEfektif(): array
    {
        $nomor = 'VSR-UBA-'.Str::upper(Str::random(8));
        $service = app(DaftarAnggotaRombongan::class);
        $daftar = $service->susunApaAdanya([['nama' => 'Siti Aminah'], ['nama' => 'Budi']]);

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
            'Idempotency_Key' => 'UBA-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Rudi Hartono',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 3,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
            'Waktu_Mulai_Kunjungan' => '2026-09-15 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-15 17:00:00',
            'Daftar_Anggota_Json' => $service->keJson($daftar),
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        return [$nomor, (int) $id];
    }
}
