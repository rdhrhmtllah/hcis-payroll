<?php

namespace Tests\Feature\KPI\Planning;

use App\Http\Controllers\KPI\Planning\Scheduling\PenjadwalanKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Membatalkan pengajuan parameter harus MEMBUKA kembali penjadwalan orangnya.
 *
 * Pembatalan di Monitoring menulis Flag_Aktif='T' DAN Status='Y' pada
 * KPI_Pengajuan. Layar "karyawan belum ada KPI" menyimpulkan sudah-mengisi dari
 * ada tidaknya Id_Pengajuan hasil join — dan bila join itu tidak menyaring
 * keaktifan, baris yang sudah dibatalkan tetap terhitung. Orangnya lalu
 * terkunci sebagai "Sudah Mengisi Pengajuan" dan tak bisa dijadwalkan ulang,
 * padahal justru pembatalan itu yang dimaksudkan membebaskannya.
 *
 * Berkas yang sama sudah melakukannya dengan benar di getDataKaryawan():
 * join di sana menyaring Flag_Aktif='Y' dan Status NULL, lengkap dengan
 * komentar yang menyatakan alasannya. Aturannya dipasang di satu dari dua pintu.
 */
class PembatalanMembukaJadwalUlangTest extends TestCase
{
    use DatabaseTransactions;

    /** Satu pengajuan HIDUP pada jadwal Form KPI yang aktif. */
    private function pengajuanHidup(): ?object
    {
        return DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('m.Flag_Form_KPI', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->orderByDesc('kp.Id_Pengajuan')
            ->select('kp.Id_User', 'm.Tahun', 'k.Kode_Karyawan')
            ->first();
    }

    /** Baris karyawan tsb pada daftar "belum ada KPI". */
    private function barisKaryawan(int $tahun, string $kodeKaryawan): ?array
    {
        $res = app(PenjadwalanKpiController::class)
            ->getDataKaryawanBelumAdaKpi(new Request(['tahun' => $tahun]));

        $this->assertSame(200, $res->getStatusCode(), 'Daftar karyawan gagal dimuat: '.$res->getContent());

        $isi = json_decode($res->getContent(), true);

        return collect($isi['result']['data'] ?? $isi['result'] ?? [])
            ->first(fn ($r) => is_array($r) && ($r['kode_karyawan'] ?? null) === $kodeKaryawan);
    }

    public function test_pembatalan_membuat_karyawan_bisa_dijadwalkan_ulang(): void
    {
        $p = $this->pengajuanHidup();

        if (! $p) {
            $this->markTestSkipped('Tidak ada pengajuan parameter hidup di data dev.');
        }

        $tahun = (int) $p->Tahun;
        $kode = (string) $p->Kode_Karyawan;

        // PRASYARAT: selagi pengajuannya hidup, ia memang terhitung sudah mengisi.
        // Tanpa memeriksa ini, uji di bawah bisa hijau hanya karena orangnya
        // kebetulan tidak muncul di daftar sama sekali.
        $sebelum = $this->barisKaryawan($tahun, $kode);

        if (! $sebelum) {
            $this->markTestSkipped("Karyawan {$kode} tidak muncul di daftar tahun {$tahun}.");
        }

        $this->assertTrue(
            (bool) $sebelum['is_filled'],
            'Prasyarat gagal: pengajuannya hidup tapi tidak terhitung sudah mengisi.',
        );

        // Batalkan PERSIS seperti Monitoring: Flag_Aktif='T' DAN Status='Y',
        // pada seluruh pengajuannya di tahun itu.
        DB::table('KPI_Pengajuan')
            ->where('Id_User', $p->Id_User)
            ->whereIn('Id_Penjadwalan', function ($q) use ($tahun) {
                $q->select('Id_Master_Penjadwalan')
                    ->from('KPI_Master_Penjadwalan')
                    ->where('Tahun', $tahun);
            })
            ->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        $sesudah = $this->barisKaryawan($tahun, $kode);

        $this->assertNotNull($sesudah, 'Setelah dibatalkan, karyawan harus tetap muncul di daftar.');
        $this->assertFalse(
            (bool) $sesudah['is_filled'],
            'Pengajuannya sudah dibatalkan, jadi karyawan ini harus bisa dijadwalkan ulang.',
        );
        $this->assertSame('Belum Mengisi Pengajuan', $sesudah['status_kpi']);
    }
}
