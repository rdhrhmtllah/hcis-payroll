<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar bypass update performance tidak boleh mengangkut daftar id ke PHP.
 *
 * ── Bentuk yang dijaga, dan kenapa ───────────────────────────────────────────
 *
 * Versi lama menarik SELURUH Id_Pengajuan yang disetujui ke PHP, lalu
 * mengirimnya kembali sebagai IN(...) — dua kali — dan menutupnya dengan satu
 * IN(...) lagi berisi seluruh Id_User.
 *
 * Terukur di Web_HRIS 10 September 2026, pada endpoint yang sama:
 *
 *   sebelum : 770 ms waktu DB, kueri terbesar membawa 861 parameter
 *   sesudah : 136 ms waktu DB, kueri terbesar membawa 6 parameter
 *
 * Tiga akibat, dan yang ketiga bukan soal kecepatan:
 *
 *   1. Kueri pertamanya tidak menyaring apa pun — ia membaca seluruh
 *      KPI_Pengajuan yang disetujui, milik siapa pun. Keluhan "terasa seperti
 *      full scan" itu tepat; memang begitu adanya.
 *   2. Jumlah parameter yang selalu berubah memaksa SQL Server mengompilasi
 *      rencana baru untuk tiap jumlah yang berbeda.
 *   3. Batas keras SQL Server 2.100 parameter. Dengan 859 terpakai, sisa
 *      ruangnya menyusut setiap kali ada pengajuan baru disetujui — jadi ini
 *      bukan akan melambat, ini akan GAGAL, dan gagalnya menyamar jadi galat
 *      basis data alih-alih galat kapasitas.
 *
 * ── Kenapa ambangnya kecil, bukan sekadar di bawah 2.100 ─────────────────────
 *
 * Ambang di bawah 2.100 hanya akan menangkapnya setelah masalahnya nyaris
 * terjadi. Yang dijaga adalah BENTUKNYA: penyaringan tinggal di dalam basis
 * data, sehingga jumlah parameter tidak tumbuh mengikuti jumlah data sama
 * sekali.
 */
class BypassDaftarTanpaDaftarParameterTest extends TestCase
{
    use DatabaseTransactions;

    /** Nyatanya 6 (paginasi + periode). Ambang memberi ruang tumbuh yang wajar. */
    private const AMBANG_PARAMETER = 40;

    public function test_tidak_ada_kueri_yang_mengangkut_daftar_id(): void
    {
        $adaData = DB::table('KPI_Pengajuan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->exists();

        if (! $adaData) {
            $this->markTestSkipped('Tak ada pengajuan disetujui di data ini; jalur daftarnya tak tercapai.');
        }

        $terbesar = 0;
        $pelanggar = null;

        DB::listen(function ($q) use (&$terbesar, &$pelanggar) {
            $jumlah = count($q->bindings);
            if ($jumlah > $terbesar) {
                $terbesar = $jumlah;
                $pelanggar = substr(preg_replace('/\s+/', ' ', $q->sql), 0, 120);
            }
        });

        $respons = app(UpdatePerformanceController::class)->getDataKpiItemsByPass(
            Request::create('/api/v1/update-performance/list-Kpi-bypass', 'GET', ['page' => 1, 'limit' => 10]),
        );

        $this->assertSame(
            200,
            $respons->getStatusCode(),
            'Endpoint tidak menjawab 200, jadi jalur yang dijaga belum tentu dilewati.',
        );

        $this->assertLessThanOrEqual(
            self::AMBANG_PARAMETER,
            $terbesar,
            "Ada kueri yang membawa {$terbesar} parameter — daftar id kembali diangkut ke PHP ".
            "lalu dikirim balik sebagai IN(...).\n\nKueri: {$pelanggar}\n\n".
            'Selain lambat, bentuk ini menabrak batas keras 2.100 parameter SQL Server begitu '.
            'jumlah pengajuan yang disetujui bertambah.',
        );
    }
}
