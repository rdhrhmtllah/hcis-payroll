<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Riwayat approval Status Hari harus menyebut siapa yang menolak.
 *
 * Pengajuan Status Hari tidak pernah membuat baris HRIS_Approval_Request_Detail,
 * padahal processStatusHariApproval() mencoba memperbaruinya per tanggal (dan
 * diam-diam mencatat "Approval detail record not found"). Di development pada
 * 5 Oktober 2026 jumlah barisnya nol. Akibatnya riwayat selalu jatuh ke jalur
 * cadangan yang tidak tahu siapa yang memutuskan, dan modal tidak pernah
 * menampilkan "Ditolak oleh".
 */
class RiwayatApprovalStatusHariTest extends TestCase
{
    use DatabaseTransactions;

    private const SOURCE_VIEW = 'N_HRIS_VW_SRC_STATUS_HARI';

    public function test_pengajuan_membuat_baris_tracking_per_approver_sekali_saja(): void
    {
        $f = $this->fixture(['approver_kedua' => true]);

        $service = app(ShiftRequestService::class);
        $service->buatDetailTrackingApproval($f->no, self::SOURCE_VIEW);
        $service->buatDetailTrackingApproval($f->no, self::SOURCE_VIEW);

        $baris = DB::table('HRIS_Approval_Request_Detail')
            ->whereIn('Id_Approval_Request', $f->idRequests)
            ->get();

        // Dua approver x dua tanggal, dan panggilan kedua tidak menggandakan.
        $this->assertCount(4, $baris);
        $this->assertSame([self::SOURCE_VIEW], $baris->pluck('Source_View')->unique()->values()->all());
        $this->assertContains("{$f->no}|{$f->idDetButuh}", $baris->pluck('Source_Row_Key')->all());
    }

    public function test_riwayat_dari_tracking_menyebut_approver_dan_jenis_pengajuan(): void
    {
        $f = $this->fixture();

        app(ShiftRequestService::class)->buatDetailTrackingApproval($f->no, self::SOURCE_VIEW);
        DB::table('N_HRIS_Transaksi_Status_Hari_Det')->where('Id', $f->idDetButuh)->update(['Flag_Approval' => 'T']);
        DB::table('HRIS_Approval_Request_Detail')
            ->where('Id_Approval_Request', $f->idRequests[0])
            ->where('Source_Row_Key', "{$f->no}|{$f->idDetButuh}")
            ->update(['Flag_Approval' => 'T', 'Processed_By_Kode' => $f->approver->kode, 'Processed_At' => now()]);

        $details = $this->detailRiwayat($f);

        // Tanggal yang langsung diterapkan (Needs_Approval = T) tidak ikut, sama
        // seperti jalur cadangan.
        $this->assertCount(1, $details);
        $d = $details->first();
        $this->assertEquals($f->idDetButuh, $d->Id_Det);
        $this->assertSame('BACKDATE', $d->Jenis_Pengajuan);
        $this->assertSame('OFF', $d->Status_Hari);
        $this->assertSame('T', $d->Flag_Approval);
        $this->assertSame($f->approver->nama, trim((string) $d->Processed_By_Nama));
    }

    public function test_riwayat_lama_tanpa_tracking_satu_approver_menyebut_approver(): void
    {
        $f = $this->fixture();
        DB::table('N_HRIS_Transaksi_Status_Hari_Det')->where('Id', $f->idDetButuh)->update(['Flag_Approval' => 'T']);

        $d = $this->detailRiwayat($f)->first();

        $this->assertSame('T', $d->Flag_Approval);
        $this->assertSame($f->approver->kode, $d->Processed_By_Kode);
        $this->assertSame($f->approver->nama, trim((string) $d->Processed_By_Nama));
    }

    public function test_riwayat_lama_tanpa_tracking_bertingkat_tidak_menebak_nama(): void
    {
        $f = $this->fixture(['approver_kedua' => true]);
        DB::table('N_HRIS_Transaksi_Status_Hari_Det')->where('Id', $f->idDetButuh)->update(['Flag_Approval' => 'T']);

        $d = $this->detailRiwayat($f)->first();

        // Tanpa catatan per tanggal tidak ada cara tahu langkah mana yang
        // menolak; lebih baik kosong daripada salah orang.
        $this->assertSame('T', $d->Flag_Approval);
        $this->assertNull($d->Processed_By_Nama ?? null);
    }

    private function detailRiwayat(object $f)
    {
        $hasil = app(ShiftRequestService::class)->getApproverHistory($f->approver->kode, [
            'page' => 1,
            'limit' => 10,
            'filters' => ['search' => $f->no, 'transaction_type' => 'STATUS_HARI'],
        ]);

        $transaksi = collect($hasil['data'])->firstWhere('No_Transaksi', $f->no);
        $this->assertNotNull($transaksi, 'Fixture tidak terbaca oleh getApproverHistory.');

        return collect($transaksi->details);
    }

    private function fixture(array $opsi = []): object
    {
        $orang = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('Nama')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->limit(3)
            ->get(['Kode_Karyawan', 'Nama', 'UserID_Web']);

        if ($orang->count() < 3) {
            $this->markTestSkipped('Butuh tiga karyawan aktif ber-UserID_Web di data ini.');
        }

        $orang = $orang->map(fn ($o) => (object) [
            'kode' => trim((string) $o->Kode_Karyawan),
            'nama' => trim((string) $o->Nama),
            'web' => $o->UserID_Web,
        ]);
        [$karyawan, $approver, $approverKedua] = [$orang[0], $orang[1], $orang[2]];

        $no = 'UJI-TSH-'.Str::upper(Str::random(8));

        DB::table('N_HRIS_Transaksi_Status_Hari')->insert([
            'No_Transaksi' => $no,
            'Kode_Perusahaan' => '001',
            'Jenis_Transaksi' => 'STATUS_HARI',
            'Alasan' => 'uji riwayat',
            'Created_At' => now(),
            'Created_By' => $karyawan->web,
        ]);

        $idDetail = DB::table('N_HRIS_Transaksi_Status_Hari_Detail')->insertGetId([
            'No_Transaksi' => $no,
            'Kode_Perusahaan' => '001',
            'Kode_Karyawan' => $karyawan->kode,
            'Target_Table' => 'SEMENTARA',
        ]);

        $det = fn (string $tanggal, bool $butuh) => DB::table('N_HRIS_Transaksi_Status_Hari_Det')->insertGetId([
            'Id_Detail' => $idDetail,
            'No_Transaksi' => $no,
            'Tanggal' => $tanggal,
            'Status_Hari' => 'OFF',
            'Jenis_Pengajuan' => $butuh ? 'BACKDATE' : 'FUTURE',
            'Needs_Approval' => $butuh ? 'Y' : 'T',
            'Flag_Approval' => $butuh ? null : 'Y',
            'Flag_Applied' => $butuh ? 'T' : 'Y',
        ]);
        $idDetButuh = $det('2026-09-25', true);
        $det('2026-10-10', false);

        $request = fn (object $siapa, int $urutan, ?string $flag) => DB::table('HRIS_Approval_Request')->insertGetId([
            'Kode_Karyawan' => $karyawan->kode,
            'No_Transaksi' => $no,
            'Id_Jenis_Approval' => 8,
            'Request_Order_Flow' => $urutan,
            'Approver_Karyawan_Kode' => $siapa->kode,
            'Approved_By_Kode' => $flag ? $siapa->kode : null,
            'Flag_Approval' => $flag,
            'Tanggal_Approval' => $flag ? now() : null,
            'update_at' => now(),
        ]);
        $idRequests = [$request($approver, 1, 'T')];
        if ($opsi['approver_kedua'] ?? false) {
            $idRequests[] = $request($approverKedua, 2, 'Y');
        }

        return (object) compact('no', 'idDetButuh', 'idRequests', 'karyawan', 'approver');
    }
}
