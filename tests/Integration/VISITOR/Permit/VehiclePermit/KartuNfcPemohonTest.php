<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MemesanSlotInspeksi;
use Tests\TestCase;

/**
 * Syarat kartu NFC sebelum mengajukan permit kendaraan karyawan.
 *
 * Fixture dibuat sendiri di dalam transaksi uji; baris kartu milik pemohon uji
 * yang sudah ada (mis. data dummy DML-2026-141) disingkirkan dulu supaya hasil
 * tidak bergantung pada isi database hari itu.
 */
class KartuNfcPemohonTest extends TestCase
{
    use DatabaseTransactions;
    use MemesanSlotInspeksi;

    private string $kode;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.visitor.permit.wajib_kartu_nfc' => true]);

        // Belum resign: pemohon yang lewat hari terakhirnya ditolak sebelum penjaga kartu.
        $karyawan = DB::table('Karyawan')->whereNotNull('UserID_Web')->where('Aktif', 'Y')->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')->first(['Kode_Karyawan', 'UserID_Web']);
        $this->assertNotNull($karyawan, 'Tidak ada karyawan aktif ber-UserID_Web.');

        $this->kode = trim((string) $karyawan->Kode_Karyawan);
        $this->user = User::find($karyawan->UserID_Web);

        DB::table(KartuNfcPemohon::T_BINDING)->where('kode_karyawan', $this->kode)->delete();
    }

    private function periksa(): array
    {
        return app(KartuNfcPemohon::class)->periksa($this->kode);
    }

    private function kartu(string $validasi = 'Y', string $aktif = 'Y'): int
    {
        $hex = 'UJ'.Str::upper(Str::random(6));

        return (int) DB::table(KartuNfcPemohon::T_MASTER)->insertGetId([
            'nfc_hex' => $hex, 'nfc_inbio' => random_int(1, 999999999),
            'flag_validasi_sistem' => $validasi, 'flag_aktif' => $aktif,
            'created_by' => 'UJI', 'created_at' => now(),
        ]);
    }

    private function pasang(int $idKartu, ?string $kode, string $aktif = 'Y', ?string $noMitra = null): void
    {
        DB::table(KartuNfcPemohon::T_BINDING)->insert([
            'id_nfc' => $idKartu, 'kode_karyawan' => $kode, 'no_mitra' => $noMitra,
            'nama' => 'Uji', 'flag_aktif' => $aktif, 'created_by' => 'UJI', 'created_at' => now(),
        ]);
    }

    public function test_kartu_terpasang_aktif_dan_tervalidasi_boleh_mengajukan(): void
    {
        $this->pasang($this->kartu(), $this->kode);

        $hasil = $this->periksa();

        $this->assertTrue($hasil['boleh']);
        $this->assertSame(KartuNfcPemohon::TERDAFTAR, $hasil['status']);
        $this->assertNull($hasil['pesan']);
    }

    public function test_belum_pairing_ditolak_dan_diarahkan_ke_it_support(): void
    {
        $hasil = $this->periksa();

        $this->assertFalse($hasil['boleh']);
        $this->assertSame(KartuNfcPemohon::BELUM_PAIRING, $hasil['status']);
        $this->assertStringContainsString('IT support', (string) $hasil['pesan']);
        // Istilah di layar "ID Card"; "NFC" hanya nama teknis.
        $this->assertStringContainsString('ID Card', (string) $hasil['pesan']);
        $this->assertStringNotContainsString('NFC', (string) $hasil['pesan']);
    }

    public function test_pairing_yang_dicabut_sama_dengan_belum_pairing(): void
    {
        $this->pasang($this->kartu(), $this->kode, 'T');

        $this->assertSame(KartuNfcPemohon::BELUM_PAIRING, $this->periksa()['status']);
    }

    public function test_kartu_diblokir_ditolak(): void
    {
        $this->pasang($this->kartu('Y', 'T'), $this->kode);

        $this->assertSame(KartuNfcPemohon::KARTU_DIBLOKIR, $this->periksa()['status']);
    }

    public function test_kartu_belum_divalidasi_ditolak(): void
    {
        // Keputusan user 28 Sep 2026.
        $this->pasang($this->kartu('T'), $this->kode);

        $this->assertSame(KartuNfcPemohon::MENUNGGU_VALIDASI, $this->periksa()['status']);
    }

    public function test_satu_kartu_dua_pemegang_aktif_ditolak(): void
    {
        // Keputusan user 28 Sep 2026: ditolak sampai IT membereskannya. Pemegang
        // lain bisa karyawan maupun mitra.
        $kartu = $this->kartu();
        $this->pasang($kartu, $this->kode);
        $this->pasang($kartu, null, 'Y', 'MTR-UJI-1');

        $this->assertSame(KartuNfcPemohon::KARTU_GANDA, $this->periksa()['status']);
    }

    public function test_pemegang_lama_yang_sudah_nonaktif_bukan_kartu_ganda(): void
    {
        $kartu = $this->kartu();
        $this->pasang($kartu, 'ZZ-UJI-LAMA', 'T');
        $this->pasang($kartu, $this->kode);

        $this->assertTrue($this->periksa()['boleh']);
    }

    public function test_ganti_kartu_memakai_kartu_pengganti(): void
    {
        $this->pasang($this->kartu('Y', 'T'), $this->kode, 'T');
        $this->pasang($this->kartu(), $this->kode);

        $this->assertTrue($this->periksa()['boleh']);
    }

    public function test_satu_kartu_sah_cukup_walau_kartu_lain_bermasalah(): void
    {
        $this->pasang($this->kartu('Y', 'T'), $this->kode);
        $this->pasang($this->kartu(), $this->kode);

        $this->assertTrue($this->periksa()['boleh']);
    }

    public function test_syarat_dimatikan_lewat_config(): void
    {
        config(['services.visitor.permit.wajib_kartu_nfc' => false]);

        $this->assertTrue($this->periksa()['boleh']);
    }

    public function test_uid_kartu_tidak_dikirim_utuh(): void
    {
        $kartu = $this->kartu();
        $this->pasang($kartu, $this->kode);
        $hex = (string) DB::table(KartuNfcPemohon::T_MASTER)->where('id', $kartu)->value('nfc_hex');

        $tampil = (string) $this->periksa()['nfc_hex'];

        $this->assertStringEndsWith(substr($hex, -4), $tampil);
        $this->assertStringNotContainsString($hex, $tampil);
    }

    public function test_pengajuan_tanpa_kartu_ditolak_server_tanpa_menyisakan_baris(): void
    {
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Kode_Karyawan_Pemohon', $this->kode)->count();

        $response = app(VehiclePermitController::class)->store($this->permintaan());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('VISITOR_PERMIT_KARTU_NFC_BELUM_PAIRING', json_decode($response->getContent(), true)['error_code'] ?? null);
        $this->assertSame($sebelum, DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Kode_Karyawan_Pemohon', $this->kode)->count());
    }

    public function test_pemohon_berkartu_lolos_penjaga_kartu(): void
    {
        // Master data di permintaan ini sengaja tidak valid: yang dibuktikan
        // hanya bahwa penolakannya BUKAN karena kartu.
        $this->pasang($this->kartu(), $this->kode);

        $response = app(VehiclePermitController::class)->store($this->permintaan());

        $this->assertStringStartsNotWith(
            'VISITOR_PERMIT_KARTU_NFC_',
            (string) (json_decode($response->getContent(), true)['error_code'] ?? ''),
        );
    }

    private function permintaan(): Request
    {
        $request = Request::create('/transaksipermit', 'POST', [
            'plat_prefix' => 'ZZ', 'plat_angka' => '9999', 'plat_suffix' => 'ZZZ',
            'id_vehicle_type' => 999999, 'id_vehicle_model' => 999999, 'id_vehicle_color' => 999999,
            'tahun_kendaraan' => 2020, 'id_kategori_permit' => 999999,
        ] + $this->slotInspeksiUji(), [], [
            'foto_kendaraan' => UploadedFile::fake()->image('kendaraan.jpg'),
            'foto_stnk' => UploadedFile::fake()->image('stnk.jpg'),
            'foto_sim' => UploadedFile::fake()->image('sim.jpg'),
        ], ['HTTP_IDEMPOTENCY_KEY' => 'NFC-'.Str::upper(Str::random(16))]);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }
}
