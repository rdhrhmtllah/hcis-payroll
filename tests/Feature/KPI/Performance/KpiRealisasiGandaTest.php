<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Satu karyawan hanya boleh punya SATU realisasi hidup per periode.
 *
 * Penjaga lama hanya mencegah draft yang sama difinalisasi dua kali dan
 * menggantikan baris yang ditunjuk No_Transaksi_Ref. Draft BARU untuk periode
 * yang sudah terisi lolos, dan itu menghasilkan dua realisasi hidup pada satu
 * periode dengan skor berbeda.
 *
 * Hidup = Flag_Aktif 'Y' DAN Status NULL. Kuncinya (Id_User, Periode) lintas
 * semua pengajuan, bukan per Id_Pengajuan.
 */
class KpiRealisasiGandaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function panggilPenjaga($idUser, ?string $periode): void
    {
        $metode = new ReflectionMethod(UpdatePerformanceController::class, 'assertBelumAdaRealisasiHidup');
        $metode->setAccessible(true);
        $metode->invoke(new UpdatePerformanceController, $idUser, $periode);
    }

    private function cariRealisasiHidup(): ?object
    {
        return DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->select('Id_Update_Performance', 'Id_User', 'Periode', 'No_Transaksi')
            ->first();
    }

    public function test_periode_yang_sudah_terisi_ditolak(): void
    {
        $hidup = $this->cariRealisasiHidup();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/sudah memiliki update performance yang aktif/');

        $this->panggilPenjaga($hidup->Id_User, $hidup->Periode);
    }

    public function test_pesan_penolakan_menyebut_nomor_transaksi(): void
    {
        $hidup = $this->cariRealisasiHidup();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        try {
            $this->panggilPenjaga($hidup->Id_User, $hidup->Periode);
            $this->fail('Penjaga tidak menolak padahal periodenya sudah terisi.');
        } catch (\Exception $e) {
            $this->assertStringContainsString(
                $hidup->No_Transaksi,
                $e->getMessage(),
                'Pesan penolakan tidak menyebut transaksi yang bentrok, jadi sulit ditindak.',
            );
        }
    }

    public function test_periode_kosong_lolos(): void
    {
        $hidup = $this->cariRealisasiHidup();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        // Periode yang pasti belum pernah dipakai.
        $this->panggilPenjaga($hidup->Id_User, '1999-01');

        $this->assertTrue(true, 'Periode kosong seharusnya lolos tanpa pengecualian.');
    }

    public function test_periode_null_tidak_bikin_error(): void
    {
        $this->panggilPenjaga(999999, null);

        $this->assertTrue(true, 'Periode null seharusnya dilewati, bukan melempar error.');
    }

    /** Baris yang sudah dibatalkan (Flag_Aktif 'T', Status 'Y') tidak boleh menghalangi. */
    public function test_realisasi_dibatalkan_tidak_menghalangi(): void
    {
        $hidup = $this->cariRealisasiHidup();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $hidup->Id_Update_Performance)
            ->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        $this->panggilPenjaga($hidup->Id_User, $hidup->Periode);

        $this->assertTrue(true, 'Realisasi yang dibatalkan seharusnya tidak menghalangi pengiriman baru.');
    }

    /**
     * Anomali Flag_Aktif 'Y' TAPI Status 'Y' juga tidak boleh menghalangi —
     * itu bukan baris hidup menurut definisi dua-syarat.
     */
    public function test_anomali_aktif_dan_dibatalkan_tidak_menghalangi(): void
    {
        $hidup = $this->cariRealisasiHidup();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $hidup->Id_Update_Performance)
            ->update(['Status' => 'Y']); // Flag_Aktif tetap 'Y'

        $this->panggilPenjaga($hidup->Id_User, $hidup->Periode);

        $this->assertTrue(true, 'Baris Flag_Aktif=Y + Status=Y bukan baris hidup.');
    }

    private function cariPengajuanDigantikan($idUser, $tahun)
    {
        $metode = new ReflectionMethod(
            \App\Http\Controllers\KPI\Performance\Parameter\FormKpiController::class,
            'cariPengajuanParameterDigantikan',
        );
        $metode->setAccessible(true);

        return $metode->invoke(new \App\Http\Controllers\KPI\Performance\Parameter\FormKpiController, $idUser, $tahun);
    }

    /**
     * Pengajuan parameter di TAHUN LAIN tidak boleh ikut digantikan.
     *
     * Inilah perilaku yang dulu salah: pemeriksaannya hanya Id_User + Flag_Aktif
     * + Status, tanpa ikatan tahun, sehingga mengajukan parameter 2027
     * menonaktifkan parameter 2026 milik orang yang sama.
     */
    public function test_pengajuan_tahun_lain_tidak_ikut_digantikan(): void
    {
        $hidup = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            // Batas yang SAMA dengan cariPengajuanParameterDigantikan. Tanpa ini
            // kandidatnya bisa bernaung di jadwal update performance, yang memang
            // tidak pernah terjaring — dan test gagal seolah-olah aturan tahunnya
            // yang rusak. Alasan lengkapnya ada di
            // test_tidak_ada_dua_pengajuan_parameter_hidup_dalam_satu_tahun().
            ->where('mp.Flag_Form_KPI', 'Y')
            ->orderByDesc('kp.Id_Pengajuan')
            ->select('kp.Id_User', 'kp.No_Transaksi', 'mp.Tahun')
            ->first();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada pengajuan parameter hidup di data dev.');
        }

        $tahunSama = $this->cariPengajuanDigantikan($hidup->Id_User, $hidup->Tahun);
        $tahunLain = $this->cariPengajuanDigantikan($hidup->Id_User, (int) $hidup->Tahun + 1);

        $this->assertTrue(
            $tahunSama->contains($hidup->No_Transaksi),
            'Pengajuan di tahun yang sama seharusnya ikut digantikan.',
        );

        $this->assertFalse(
            $tahunLain->contains($hidup->No_Transaksi),
            'Pengajuan tahun '.$hidup->Tahun.' ikut digantikan saat mengajukan tahun berikutnya.',
        );
    }

    /** Yang sudah dibatalkan tidak perlu digantikan lagi. */
    public function test_pengajuan_dibatalkan_tidak_ikut_digantikan(): void
    {
        // Batas Flag_Form_KPI wajib ada di sini juga, dan bukan demi kerapian:
        // tanpanya kandidatnya bisa berupa pengajuan yang memang tak pernah
        // terjaring, sehingga assertFalse di bawah lolos tanpa membuktikan
        // apa pun tentang pembatalan.
        $hidup = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->orderByDesc('kp.Id_Pengajuan')
            ->select('kp.Id_Pengajuan', 'kp.Id_User', 'kp.No_Transaksi', 'mp.Tahun')
            ->first();

        if (! $hidup) {
            $this->markTestSkipped('Tidak ada pengajuan parameter hidup di data dev.');
        }

        DB::table('KPI_Pengajuan')
            ->where('Id_Pengajuan', $hidup->Id_Pengajuan)
            ->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        $this->assertFalse(
            $this->cariPengajuanDigantikan($hidup->Id_User, $hidup->Tahun)->contains($hidup->No_Transaksi),
            'Pengajuan yang sudah dibatalkan masih ikut terjaring.',
        );
    }

    /**
     * Invarian data: satu karyawan tidak boleh punya dua pengajuan parameter
     * hidup untuk TAHUN JADWAL yang sama.
     *
     * "Pengajuan parameter" dibatasi pada jadwal PENGISIAN PARAMETER
     * (Flag_Form_KPI = 'Y'), sama seperti aturan produksinya
     * (cariPengajuanParameterDigantikan). Jadwal update performance hidup di
     * tahun yang sama dan pengajuan yang bernaung di sana bukan pengajuan
     * parameter — ia tidak digantikan saat parameter dikirim ulang, jadi
     * menghitungnya di sini akan melaporkan bentrok yang tidak pernah ada.
     */
    public function test_tidak_ada_dua_pengajuan_parameter_hidup_dalam_satu_tahun(): void
    {
        $bentrok = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->select('kp.Id_User', 'mp.Tahun', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('kp.Id_User', 'mp.Tahun')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertCount(
            0,
            $bentrok,
            'Ada karyawan dgn >1 pengajuan parameter hidup di tahun yang sama: '.$bentrok->toJson(),
        );
    }
}
