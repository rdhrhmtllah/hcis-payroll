<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController;
use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Agenda inspeksi dan detail permit membawa kontak pemohon, supaya SHE bisa
 * menghubungi yang terlambat/tidak datang tanpa mencari nomornya di tempat lain.
 */
class KontakPemohonAgendaTest extends TestCase
{
    use DatabaseTransactions;

    private string $pemohon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pemohon = (string) DB::table('Karyawan')->whereNotNull('HP')->where('HP', '<>', '')->whereNull('Tanggal_Resign')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');
        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        if (! $this->pemohon || ! $admin) {
            $this->markTestSkipped('Butuh karyawan ber-HP dan System Admin aktif.');
        }
        Auth::login($admin);
    }

    private function permitBesok(): string
    {
        $nomor = 'PMT-KTK-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_Pemohon' => $this->pemohon, 'Flag_Aktif' => 'Y',
            'Flag_Approval' => null, 'Created_At' => now(), 'Created_By' => $this->pemohon,
            'Idempotency_Key' => 'KTK-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor, 'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => '7777', 'Plat_Nomor_Suffix' => 'KT',
            'Tanggal_Mulai_Berlaku' => now()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor, 'Kode_Karyawan' => $this->pemohon, 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => $this->pemohon, 'Flag_Approval' => null,
            'Expired_At' => now()->addDays(3), 'created_at' => now(), 'update_at' => now(),
        ]);
        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $nomor,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Kontak',
            'Waktu_Mulai' => now()->addDay()->setTime(10, 0), 'Waktu_Selesai' => now()->addDay()->setTime(10, 30),
            'Status' => JadwalInspeksiService::TERJADWAL, 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);

        return $nomor;
    }

    public function test_agenda_membawa_kontak_pemohon_siap_whatsapp(): void
    {
        $nomor = $this->permitBesok();
        $hp = (string) DB::table('Karyawan')->where('Kode_Karyawan', $this->pemohon)->value('HP');

        $res = app(AgendaInspeksiController::class)->data(Request::create('/agenda-inspeksi-data', 'GET', ['tanggal' => now()->addDay()->toDateString()]));
        $baris = collect(json_decode($res->getContent(), true)['result']['permit'])->firstWhere('No_Transaksi', $nomor);

        $this->assertNotNull($baris, 'Permit uji tidak muncul di agenda.');
        $this->assertCount(1, $baris['Kontak']);
        $this->assertSame(trim($hp), $baris['Kontak'][0]['no_hp']);
        $this->assertStringContainsString('BG 7777 KT', urldecode($baris['Kontak'][0]['tautan_wa']));
        $this->assertStringContainsString('Pos Uji Kontak', urldecode($baris['Kontak'][0]['tautan_wa']));
        $this->assertArrayNotHasKey('No_HP', $baris, 'Nomor mentah bocor di samping kontak yang sudah disusun.');
    }

    public function test_detail_permit_verifikator_membawa_kontak_pemohon(): void
    {
        $nomor = $this->permitBesok();

        $res = app(VerifikatorVisitorController::class)->showPermit($nomor);
        $isi = json_decode($res->getContent(), true);

        $this->assertTrue($isi['success']);
        $this->assertCount(1, $isi['result']['kontak_pemohon']);
        $this->assertStringContainsString('Pos Uji Kontak', urldecode($isi['result']['kontak_pemohon'][0]['tautan_wa']));
    }
}
