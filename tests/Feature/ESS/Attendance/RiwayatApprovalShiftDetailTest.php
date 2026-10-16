<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Riwayat approval shift: detail tanggal harus menyebut shift-nya.
 *
 * Detail riwayat dibangun dari N_HRIS_VW_Approval_Tracking_Generic. Untuk SHIFT,
 * kolom `Deskripsi` di view itu adalah `Alasan` milik baris Det — hampir selalu
 * NULL (21 dari 21 baris di development pada 5 Oktober 2026). Ketika `Nama_Shift`
 * dipetakan dari `Deskripsi`, modal "Review Pengajuan" di tab Riwayat hanya
 * menampilkan tanggal dan "-": shift lama, shift baru, dan penanda
 * Backdate/Future hilang, padahal tab Inbox menampilkan semuanya.
 *
 * "Ditolak oleh" pun harus menyebut approver. View tracking tidak punya kolom
 * nama approver; membaca `Approver_Nama` selalu NULL, lalu jatuh ke
 * `Karyawan_Nama` — nama karyawan yang DIAJUKAN, bukan yang memutuskan.
 */
class RiwayatApprovalShiftDetailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detail_riwayat_menyebut_shift_dan_approvernya(): void
    {
        $orang = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('Nama')
            ->orderBy('Kode_Karyawan')
            ->limit(2)
            ->get(['Kode_Karyawan', 'Kode_Perusahaan', 'Nama']);
        $shift = DB::table('HRIS_Shift_Kerja')->orderBy('ID_Shift')->limit(2)->get(['ID_Shift', 'Nama']);

        if ($orang->count() < 2 || $shift->count() < 2) {
            $this->markTestSkipped('Butuh dua karyawan aktif dan dua shift kerja di data ini.');
        }

        [$karyawan, $approver] = [$orang[0], $orang[1]];
        $kodeApprover = trim((string) $approver->Kode_Karyawan);

        [$lama, $baru] = [$shift[0], $shift[1]];
        $kode = trim((string) $karyawan->Kode_Karyawan);
        $perusahaan = trim((string) ($karyawan->Kode_Perusahaan ?: '001'));
        $no = 'UJI-HIST-'.Str::upper(Str::random(8));

        DB::table('N_HRIS_Transaksi_Shift')->insert([
            'No_Transaksi' => $no,
            'Kode_Perusahaan' => $perusahaan,
            'Jenis_Transaksi' => 'LEADER_SHIFT',
        ]);

        $idDetail = DB::table('N_HRIS_Transaksi_Shift_Detail')->insertGetId([
            'Kode_Perusahaan' => $perusahaan,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => $kode,
            'Target_Table' => 'SEMENTARA',
        ]);

        $idDet = DB::table('N_HRIS_Transaksi_Shift_Det')->insertGetId([
            'Kode_Perusahaan' => $perusahaan,
            'Id_Detail' => $idDetail,
            'No_Transaksi' => $no,
            'Tanggal_Shift' => '2026-09-26',
            'Id_Shift_Sebelumnya' => $lama->ID_Shift,
            'Id_Shift_Diminta' => $baru->ID_Shift,
            'Jenis_Pengajuan' => 'BACKDATE',
            'Alasan' => null,
            'Needs_Approval' => 'Y',
            'Flag_Approval' => 'Y',
        ]);

        $idRequest = DB::table('HRIS_Approval_Request')->insertGetId([
            'Kode_Karyawan' => $kode,
            'No_Transaksi' => $no,
            'Approved_By_Kode' => $kodeApprover,
            'Flag_Approval' => 'Y',
            'Tanggal_Approval' => now(),
            'update_at' => now(),
        ]);

        DB::table('HRIS_Approval_Request_Detail')->insert([
            'Id_Approval_Request' => $idRequest,
            'Id_Jenis_Approval' => 8,
            'Source_Row_Key' => "{$perusahaan}|{$no}|{$idDet}",
            'Source_View' => 'N_HRIS_VW_SRC_SHIFT',
            'Kode_Karyawan' => $kode,
            'Tanggal_Item' => '2026-09-26',
            'Flag_Approval' => 'T',
            'Processed_By_Kode' => $kodeApprover,
            'Processed_At' => now(),
            'Created_At' => now(),
        ]);

        $hasil = app(ShiftRequestService::class)->getApproverHistory($kodeApprover, [
            'page' => 1,
            'limit' => 10,
            'filters' => ['search' => $no],
        ]);

        $transaksi = collect($hasil['data'])->firstWhere('No_Transaksi', $no);
        $this->assertNotNull($transaksi, 'Fixture tidak terbaca oleh getApproverHistory.');

        $detail = collect($transaksi->details)->first();
        $this->assertNotNull($detail, 'Riwayat tidak memuat detail tanggal.');

        $this->assertSame(trim((string) $baru->Nama), trim((string) $detail->Nama_Shift));
        $this->assertSame(trim((string) $lama->Nama), trim((string) $detail->Nama_Shift_Sebelumnya));
        $this->assertSame('BACKDATE', $detail->Jenis_Pengajuan);
        $this->assertEquals($idDet, $detail->Id_Det);
        $this->assertSame('T', $detail->Flag_Approval);
        $this->assertSame($kodeApprover, $detail->Processed_By_Kode);
        $this->assertSame(trim((string) $approver->Nama), trim((string) $detail->Processed_By_Nama));
    }
}
