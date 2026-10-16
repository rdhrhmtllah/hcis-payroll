<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N_VISITOR_Permit_Efektif.NFC_Inbio (DDL-2026-153): nomor kartu yang dibaca
 * palang, dari ID Card aktif karyawan pemohon (Binding_NFC -> Master_NFC).
 */
class PermitEfektifNfcInbioTest extends TestCase
{
    use DatabaseTransactions;

    private string $karyawan;

    protected function setUp(): void
    {
        parent::setUp();
        $kolom = array_column(DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_VISITOR_Permit_Efektif'"), 'COLUMN_NAME');
        $this->assertContains('NFC_Inbio', $kolom, 'Kolom NFC_Inbio belum ada — jalankan database/ddl/DDL-2026-153-visitor-permit-efektif-nfc-inbio/forward.sql');

        // Karyawan tanpa binding sama sekali, supaya kartunya milik uji ini saja.
        $this->karyawan = (string) DB::table('Karyawan as k')
            ->whereNotExists(fn ($q) => $q->from(KartuNfcPemohon::T_BINDING.' as b')->whereColumn('b.kode_karyawan', 'k.Kode_Karyawan'))
            ->orderBy('k.Kode_Karyawan')
            ->value('k.Kode_Karyawan');
    }

    private function kartu(string $kode, array $master = [], string $bindingAktif = 'Y'): string
    {
        $inbio = (string) random_int(90000000, 99999999);
        $idNfc = DB::table(KartuNfcPemohon::T_MASTER)->insertGetId($master + [
            'nfc_hex' => strtoupper(Str::random(8)), 'nfc_inbio' => $inbio,
            'flag_validasi_sistem' => 'Y', 'flag_aktif' => 'Y', 'created_by' => 'UJI', 'created_at' => now(),
        ]);
        DB::table(KartuNfcPemohon::T_BINDING)->insert([
            'id_nfc' => $idNfc, 'kode_karyawan' => $kode, 'nama' => 'Uji', 'flag_aktif' => $bindingAktif,
            'created_by' => 'UJI', 'created_at' => now(),
        ]);

        return $inbio;
    }

    private function permit(string $pemohon, array $header = []): string
    {
        $nomor = 'PMT-NFC-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_Pemohon' => $pemohon, 'Flag_Aktif' => 'Y', 'Flag_Approval' => 'Y',
            'Created_At' => now(), 'Created_By' => $pemohon, 'Idempotency_Key' => 'NFC-'.Str::upper(Str::random(20)),
        ] + $header);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor, 'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => '4321', 'Plat_Nomor_Suffix' => 'NF',
            'Tanggal_Mulai_Berlaku' => now()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        return $nomor;
    }

    private function nfcDiEfektif(string $nomor): ?string
    {
        $nilai = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('NFC_Inbio');

        return $nilai !== null ? (string) $nilai : null;
    }

    public function test_sinkron_permit_karyawan_menulis_nfc_inbio_kartu_aktifnya(): void
    {
        $inbio = $this->kartu($this->karyawan);
        $nomor = $this->permit($this->karyawan);

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame($inbio, $this->nfcDiEfektif($nomor));
    }

    public function test_akses_inspeksi_juga_membawa_nfc_inbio(): void
    {
        $inbio = $this->kartu($this->karyawan);
        $nomor = $this->permit($this->karyawan);

        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, now()->addHour(), now()->addHours(2));

        $this->assertSame($inbio, $this->nfcDiEfektif($nomor));
    }

    public function test_kartu_diblokir_belum_divalidasi_atau_binding_nonaktif_tidak_ditulis(): void
    {
        foreach ([
            [['flag_aktif' => 'T'], 'Y'],
            [['flag_validasi_sistem' => 'T'], 'Y'],
            [[], 'T'],
        ] as [$master, $binding]) {
            DB::table(KartuNfcPemohon::T_BINDING)->where('kode_karyawan', $this->karyawan)->delete();
            $this->kartu($this->karyawan, $master, $binding);
            $nomor = $this->permit($this->karyawan);

            app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

            $this->assertNull($this->nfcDiEfektif($nomor), 'Kartu yang tidak berlaku ikut tertulis: '.json_encode([$master, $binding]));
        }
    }

    public function test_kartu_ganda_tidak_ditulis_dan_kartu_baru_menang(): void
    {
        $lama = $this->kartu($this->karyawan);
        DB::table(KartuNfcPemohon::T_BINDING)->where('kode_karyawan', $this->karyawan)->update(['created_at' => now()->subDay()]);
        $baru = $this->kartu($this->karyawan);
        $this->assertSame($baru, app(KartuNfcPemohon::class)->nfcInbio($this->karyawan), 'Binding terbaru harus menang.');

        // Kartu baru ikut dipegang orang lain: jatuh ke kartu lama yang masih sah.
        $idBaru = DB::table(KartuNfcPemohon::T_MASTER)->where('nfc_inbio', $baru)->value('id');
        DB::table(KartuNfcPemohon::T_BINDING)->insert([
            'id_nfc' => $idBaru, 'kode_karyawan' => 'ORANG-LAIN', 'nama' => 'Lain', 'flag_aktif' => 'Y', 'created_by' => 'UJI', 'created_at' => now(),
        ]);
        $this->assertSame($lama, app(KartuNfcPemohon::class)->nfcInbio($this->karyawan));
    }

    public function test_sinkron_ulang_mengikuti_kartu_terbaru(): void
    {
        $this->kartu($this->karyawan);
        $nomor = $this->permit($this->karyawan);
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        // Kartu diganti: binding lama dinonaktifkan, kartu baru dipasang.
        DB::table(KartuNfcPemohon::T_BINDING)->where('kode_karyawan', $this->karyawan)->update(['flag_aktif' => 'T']);
        $baru = $this->kartu($this->karyawan);
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame($baru, $this->nfcDiEfektif($nomor));
        $this->assertSame('T', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('Flag_Sync'), 'Palang tidak diberi tahu ada perubahan.');
    }

    public function test_izin_mitra_tidak_membawa_kartu_karyawan_pengajunya(): void
    {
        // Pemegang izin mitra bukan karyawan pengaju: kartunya tidak boleh ikut membuka palang.
        $this->kartu($this->karyawan);
        $nomor = $this->permit($this->karyawan, ['Sumber_Pengajuan' => 'API']);

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame('API', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('Sumber_Permit'));
        $this->assertNull($this->nfcDiEfektif($nomor));
    }
}
