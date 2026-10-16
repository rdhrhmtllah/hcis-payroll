<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\Executive\DashboardCeoController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua cacat data yang membuat dashboard tim berbohong tentang jumlah orang.
 *
 * 1. BARIS KEMBAR. Satu karyawan bisa punya lebih dari satu update hidup pada
 *    periode yang sama — keduanya Flag_Aktif='Y', Status NULL, disetujui, dan
 *    terverifikasi, jadi tidak ada saringan yang membedakannya. Di Web_HRIS
 *    (19 Sep 2026) ada 12 pasangan (karyawan, periode) seperti itu. Akibatnya
 *    orang yang sama muncul dua batang di grafik, dan widget berselisih dengan
 *    dirinya sendiri: "Total Karyawan" menghitung ORANG, "Mencapai/Di Bawah
 *    Target" menghitung BARIS.
 *
 * 2. POINTER BAND BASI. Id_Performance bisa menunjuk baris master yang sudah
 *    tidak ada (535 dari 552 update 2026 di dev). Jalur bulanan dulu
 *    meng-INNER JOIN master, sehingga orang berpointer basi HILANG dari daftar
 *    dan peringkat — tetapi tetap terhitung di widget yang tidak ikut join.
 */
class DashboardTimBarisKembarTest extends TestCase
{
    use DatabaseTransactions;

    private const PERIODE = '2019-03';

    private function controller(): object
    {
        return new class extends DashboardCeoController
        {
            public function ujiSatuUpdatePerPeriode($query): void
            {
                $this->satuUpdatePerPeriode($query);
            }

            public function ujiBandDariSkor($skor)
            {
                return $this->bandDariSkor($skor);
            }
        };
    }

    /** Dua update hidup untuk SATU orang pada periode yang sama. */
    private function semaiKembar(int $idUser, float $skorLama, float $skorBaru, int $idPerformanceBaru): array
    {
        $buat = function (float $skor, int $idPerformance) use ($idUser) {
            return (int) DB::table('KPI_Update_Performance')->insertGetId([
                'No_Transaksi' => 'UJI-KEMBAR-'.bin2hex(random_bytes(4)),
                'Id_Pengajuan' => 0,
                'Id_User' => $idUser,
                'Periode' => self::PERIODE,
                'Status_Pengajuan' => 'disetujui',
                'Flag_Approval' => 'Y',
                'Flag_Verifikasi' => 'Y',
                'Flag_Aktif' => 'Y',
                'Status' => null,
                'Total_Score' => $skor,
                'Id_Performance' => $idPerformance,
                'Tanggal' => '2019-03-20 00:00:00',
                'Jam' => '10:00:00',
            ], 'Id_Update_Performance');
        };

        $band = DB::table('KPI_Master_Performance')->value('Id_Master_Performance');

        return [$buat($skorLama, (int) $band), $buat($skorBaru, $idPerformanceBaru)];
    }

    private function kueriPeriode(): \Illuminate\Database\Query\Builder
    {
        return DB::table('KPI_Update_Performance')
            ->where('KPI_Update_Performance.Periode', self::PERIODE)
            ->whereNull('KPI_Update_Performance.Status')
            ->where('KPI_Update_Performance.Status_Pengajuan', 'disetujui')
            ->where('KPI_Update_Performance.Flag_Approval', 'Y')
            ->where('KPI_Update_Performance.Flag_Aktif', 'Y')
            ->where('KPI_Update_Performance.Flag_Verifikasi', 'Y');
    }

    public function test_baris_kembar_dihitung_sekali_dan_yang_dipakai_yang_terbaru(): void
    {
        $idUser = (int) DB::table('KPI_Users')->value('Id_Users');
        [$lama, $baru] = $this->semaiKembar($idUser, 80, 102, 9);

        // Tanpa penjaga: dua baris — itulah bentuk cacatnya.
        $this->assertSame(2, (clone $this->kueriPeriode())->where('Id_User', $idUser)->count());

        $kueri = $this->kueriPeriode()->where('Id_User', $idUser);
        $this->controller()->ujiSatuUpdatePerPeriode($kueri);
        $baris = $kueri->get(['Id_Update_Performance', 'Total_Score']);

        $this->assertCount(1, $baris, 'Satu orang pada satu periode harus menyisakan satu baris.');
        $this->assertSame($baru, (int) $baris[0]->Id_Update_Performance, 'Yang dipakai harus kiriman terakhir.');
        $this->assertNotSame($lama, (int) $baris[0]->Id_Update_Performance);
    }

    /**
     * Band dibaca dari RENTANG skor, jadi pointer basi tidak lagi menentukan
     * apa pun — dan orangnya tidak bisa hilang karenanya.
     */
    public function test_band_diturunkan_dari_skor_bukan_dari_pointer(): void
    {
        $master = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();

        if ($master->isEmpty()) {
            $this->markTestSkipped('Butuh master performance.');
        }

        foreach ($master as $band) {
            $tengah = ((float) $band->Range_Awal + (float) $band->Range_Akhir) / 2;
            $this->assertSame(
                $band->Kode,
                $this->controller()->ujiBandDariSkor($tengah)->Kode,
                "Skor {$tengah} harus masuk band {$band->Kode}.",
            );
        }

        // Skor dari baris berpointer basi tetap menemukan band-nya.
        $idUser = (int) DB::table('KPI_Users')->value('Id_Users');
        $this->semaiKembar($idUser, 80, 102, 999999);
        $this->assertNotNull($this->controller()->ujiBandDariSkor(102)->Keterangan);
    }
}
