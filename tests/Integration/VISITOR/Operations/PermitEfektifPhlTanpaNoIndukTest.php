<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Karyawan PHL: punya Kode_Karyawan, tetapi tidak punya No_Induk_Karyawan.
 *
 * Pertanyaan user 6 Okt 2026: kartu NFC PHL dipasangkan lewat kode karyawan,
 * jadi permit efektifnya harus tetap terbit dengan Kode_Karyawan_Pemohon dan
 * NFC_Inbio terisi, sementara NIK (= No_Induk_Karyawan) kosong. Di development
 * tak ada karyawan aktif tanpa nomor induk, jadi uji ini membuat PHL-nya
 * sendiri dalam transaksi yang di-rollback.
 */
class PermitEfektifPhlTanpaNoIndukTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phl_tanpa_no_induk_tetap_terbit_dengan_kode_dan_kartu_nfc(): void
    {
        // Karyawan aktif tanpa binding sama sekali, lalu dijadikan PHL.
        $kode = (string) DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotExists(fn ($q) => $q->from(KartuNfcPemohon::T_BINDING.' as b')->whereColumn('b.kode_karyawan', 'k.Kode_Karyawan'))
            ->orderBy('k.Kode_Karyawan')
            ->value('k.Kode_Karyawan');
        $this->assertNotSame('', $kode, 'Tak ada karyawan aktif tanpa binding NFC.');
        DB::table('Karyawan')->where('Kode_Karyawan', $kode)->update(['No_Induk_Karyawan' => null]);

        // Kartu dipasangkan lewat KODE karyawan — satu-satunya pengenal PHL.
        $inbio = (string) random_int(90000000, 99999999);
        $idNfc = DB::table(KartuNfcPemohon::T_MASTER)->insertGetId([
            'nfc_hex' => strtoupper(Str::random(8)), 'nfc_inbio' => $inbio,
            'flag_validasi_sistem' => 'Y', 'flag_aktif' => 'Y', 'created_by' => 'UJI', 'created_at' => now(),
        ]);
        DB::table(KartuNfcPemohon::T_BINDING)->insert([
            'id_nfc' => $idNfc, 'kode_karyawan' => $kode, 'nama' => 'PHL Uji', 'flag_aktif' => 'Y',
            'created_by' => 'UJI', 'created_at' => now(),
        ]);

        // 1) Boleh mengajukan: syarat ID Card terpenuhi tanpa nomor induk.
        $cek = app(KartuNfcPemohon::class)->periksa($kode);
        $this->assertTrue($cek['boleh'], 'PHL ditolak mengajukan: '.$cek['status']);

        // 2) Permit disetujui lalu di-sync ke proyeksi efektif.
        $nomor = 'PMT-PHL-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_Pemohon' => $kode, 'Flag_Aktif' => 'Y', 'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(), 'Created_At' => now()->subDays(2), 'Created_By' => $kode,
            'Idempotency_Key' => 'PHL-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor, 'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => '7788', 'Plat_Nomor_Suffix' => 'PH',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $efektif = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNotNull($efektif, 'Permit efektif PHL tidak terbit.');
        $this->assertSame($kode, trim((string) $efektif->Kode_Karyawan_Pemohon));
        $this->assertNull($efektif->NIK, 'NIK harus kosong untuk PHL, bukan disalin dari kode.');
        $this->assertSame($inbio, (string) $efektif->NFC_Inbio, 'Nomor kartu PHL tidak ikut ke permit efektif.');
        $this->assertSame('KARYAWAN', $efektif->Sumber_Permit);
        $this->assertSame('AKTIF', $efektif->Status_Efektif);
    }
}
