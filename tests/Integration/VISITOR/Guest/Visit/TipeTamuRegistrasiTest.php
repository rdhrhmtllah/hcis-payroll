<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

/**
 * Pengujian Tipe Tamu REGULAR vs B2B pada registrasi tamu.
 *
 * Sejak DDL-2026-121 tipe DITETAPKAN RUTE (visitor.tipe-tamu), bukan isian
 * `tipe_tamu`, dan kedua tipe wajib membawa Estimasi Masuk/Keluar. Permit
 * COKLAT TIDAK boleh terbit untuk Tamu REGULAR. Tamu B2B wajib berkendaraan
 * dan berplat.
 */
class TipeTamuRegistrasiTest extends TestCase
{
    use DatabaseTransactions;
    use PermintaanRegistrasiTamu;

    private const PIC = 'A1';

    public function test_registrasi_tamu_regular_membawa_estimasi_dan_tanpa_permit_coklat(): void
    {
        $masuk = now()->addDay()->setTime(9, 0);

        $nomor = $this->daftarkan(TipeTamu::REGULAR, [
            'nama_tamu' => 'Tamu Regular',
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->setTime(15, 0)->format('Y-m-d H:i:s'),
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('REGULAR', $detail->Tipe_Tamu);
        // DDL-2026-121: Regular tidak lagi "tanpa jam" — Estimasi Masuk/Keluar
        // wajib dan tersimpan; kategori diturunkan dari rentangnya.
        $this->assertSame($masuk->format('Y-m-d H:i:s'), substr((string) $detail->Waktu_Mulai_Kunjungan, 0, 19));
        $this->assertSame($masuk->copy()->setTime(15, 0)->format('Y-m-d H:i:s'), substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
        $this->assertSame(TipeTamu::KATEGORI_VISITOR, $detail->Tamu_Kategori);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->assertNull(
            app(PermitCoklatTamuService::class)->sinkron($nomor),
            'Tamu Regular tidak boleh mengajukan permit COKLAT.',
        );
        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi_Tamu', $nomor)->count(),
            'Tamu Regular tidak boleh punya transaksi permit COKLAT.',
        );
    }

    public function test_registrasi_tamu_b2b_tersimpan_dengan_tipe_b2b(): void
    {
        [$jenisKendaraan, $platPrefix] = $this->masterKendaraanB2b();

        // Tipe dari rute B2B (DDL-2026-121), bukan dari isian tipe_tamu.
        $nomor = $this->daftarkan(TipeTamu::B2B, [
            'nama_tamu' => 'Tamu B2B Mitra',
            'jenis_kendaraan' => $jenisKendaraan,
            'plat_prefix' => $platPrefix,
            'plat_angka' => '1234',
            'plat_suffix' => 'UJ',
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('B2B', $detail->Tipe_Tamu);
        $this->assertSame($platPrefix, $detail->Plat_Nomor_Prefix);
        $this->assertSame('1234', $detail->Plat_Nomor_Angka);
    }

    /** @param array<string,mixed> $ubah */
    private function daftarkan(string $tipe, array $ubah): string
    {
        $masuk = now()->addDay()->setTime(9, 0);

        $payload = array_merge([
            'nama_tamu' => 'Tamu Uji',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'waktu_mulai_kunjungan' => $masuk->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $masuk->copy()->setTime(15, 0)->format('Y-m-d H:i:s'),
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'daftar_anggota' => [],
        ], $ubah);

        $request = $this->permintaanRegistrasiTamu(
            $payload,
            self::PIC,
            'TPT-'.Str::upper(Str::random(20)),
            $tipe,
        );

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    /**
     * Jenis kendaraan & kode plat AKTIF dari master — store() menolak nilai
     * yang tak terdaftar, dan B2B wajib berkendaraan (DDL-2026-121).
     *
     * @return array{0: string, 1: string}
     */
    private function masterKendaraanB2b(): array
    {
        $jenis = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']);
            })
            ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tidak%')
            ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%tanpa%')
            ->where('Nama_Tipe_Kendaraan', 'NOT LIKE', '%kaki%')
            ->value('Nama_Tipe_Kendaraan');
        $plat = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->value('Kode_Plat');

        $this->assertNotNull($jenis, 'Master jenis kendaraan aktif untuk tamu tidak tersedia.');
        $this->assertNotNull($plat, 'Master kode plat aktif tidak tersedia.');

        return [trim((string) $jenis), strtoupper(trim((string) $plat))];
    }
}
