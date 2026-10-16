<?php

namespace Tests\Feature\KPI\Monitoring;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sebuah transaksi tidak boleh HIDUP dan DIBATALKAN sekaligus.
 *
 * Pembatalan menulis `Flag_Aktif='T'` DAN `Status='Y'` bersama-sama, sehingga
 * berlaku invarian: `Flag_Aktif='Y'` berarti `Status IS NULL`.
 *
 * Kenapa ini pantas dijaga sendiri. Puluhan kueri di modul KPI menyaring
 * keaktifan hanya lewat SALAH SATU kolom — sebagian `Flag_Aktif='Y'` saja,
 * sebagian `Status IS NULL` saja. Semuanya benar HANYA selama kedua kolom itu
 * selalu dibalik bersama. Begitu ada satu jalur tulis yang memecah pasangan
 * itu, kueri-kueri tersebut mulai salah menghitung — tanpa error, tanpa gejala,
 * dan di tempat yang jauh dari penyebabnya.
 *
 * Menambal puluhan kueri itu satu per satu justru berbahaya: sebagian memang
 * sengaja tidak menyaring (KpiCancellationService mencari baris yang
 * dibatalkan; pencarian lewat No_Transaksi menunjuk satu baris tertentu).
 * Satu penjaga di invariannya melindungi semuanya sekaligus, dan menyala di
 * tempat yang benar — pada jalur tulis yang memecahnya, bukan pada pembaca
 * yang menjadi korbannya.
 *
 * Latar: bug nyata pada getDataKaryawanBelumAdaKpi() yang menyaring KEDUANYA
 * tidak sama sekali, sehingga pengajuan yang dibatalkan tetap mengunci
 * karyawan dari penjadwalan ulang.
 */
class InvarianHidupDanDibatalkanTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: string, 1: string}> */
    public static function tabelTransaksi(): array
    {
        return [
            'parameter' => ['KPI_Pengajuan', 'Id_Pengajuan'],
            'performance' => ['KPI_Update_Performance', 'Id_Update_Performance'],
        ];
    }

    /**
     * @dataProvider tabelTransaksi
     */
    public function test_tidak_ada_baris_yang_hidup_sekaligus_dibatalkan(string $tabel, string $pk): void
    {
        $melanggar = DB::table($tabel)
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('Status')
            ->limit(20)
            ->pluck($pk)
            ->all();

        $this->assertSame(
            [],
            $melanggar,
            sprintf(
                "%s memuat baris yang HIDUP sekaligus DIBATALKAN: %s\n\n".
                'Puluhan kueri di modul KPI menyaring keaktifan hanya lewat salah satu '.
                'kolom, dan semuanya benar HANYA selama Flag_Aktif dan Status dibalik '.
                'bersama. Baris seperti ini membuat sebagian kueri menghitungnya sebagai '.
                "hidup dan sebagian lagi sebagai batal, tanpa error di mana pun.\n\n".
                'Cari jalur tulis yang menyetel Status tanpa menyentuh Flag_Aktif.',
                $tabel,
                implode(', ', $melanggar),
            ),
        );
    }

    /**
     * Prasyarat penjaga di atas: populasinya harus cukup untuk bermakna.
     *
     * Tabel kosong membuat assertSame([], ...) lolos tanpa memeriksa apa pun —
     * hijau sempurna yang tidak membuktikan apa-apa.
     *
     * @dataProvider tabelTransaksi
     */
    public function test_populasinya_cukup_untuk_bermakna(string $tabel, string $pk): void
    {
        $hidup = DB::table($tabel)->where('Flag_Aktif', 'Y')->whereNull('Status')->count();
        $batal = DB::table($tabel)->where('Flag_Aktif', 'T')->whereNotNull('Status')->count();

        $this->assertGreaterThan(0, $hidup, "{$tabel} tidak punya satu pun baris hidup; penjaganya tak menguji apa pun.");
        $this->assertGreaterThan(0, $batal, "{$tabel} tidak punya satu pun baris dibatalkan; invariannya belum pernah diuji kenyataan.");
    }
}
