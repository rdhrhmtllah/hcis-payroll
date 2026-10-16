<?php

namespace Tests\Unit\KPI\Monitoring;

use App\Services\KPI\KpiKepegawaianPeriode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penurunan batas periode pada KpiKepegawaianPeriode.
 *
 * Fungsi murni + pemeriksaan bentuk SQL; tidak ada kueri yang dieksekusi.
 * Yang dijaga bukan "ada fungsinya", melainkan empat keputusan yang kalau salah
 * akan MENGHILANGKAN atau MEMUNCULKAN orang tanpa satu pun galat:
 *
 *  1. Batasnya HARI TERAKHIR periode, bukan hari pertama. Kalau hari pertama,
 *     orang yang resign 15 Juli tetap muncul di laporan Juli padahal ia tidak
 *     menjalani bulan itu penuh — persis yang diminta dihilangkan.
 *
 *  2. Scope YTD adalah DAFTAR periode dan batasnya diambil dari periode
 *     TERAWAL. Bila diambil dari bulan terakhir, orang yang dinilai Februari
 *     lalu resign Maret lenyap dari akumulasi Jan..N — padahal angkanya ikut
 *     terhitung di dalamnya.
 *
 *  3. Scope submission adalah TAHUN ('2026'), bukan bulan. Bila string empat
 *     digit itu diperlakukan sebagai tanggal biasa, hasilnya bukan Januari.
 *
 *  4. Scope yang tak terbaca harus MELEBARKAN, bukan menyempitkan. Penyaring
 *     yang gagal ke arah "sembunyikan" menghapus orang diam-diam begitu ada
 *     pemanggil baru yang lupa mengisi scope.
 */
class KepegawaianPeriodeBatasScopeTest extends TestCase
{
    public function test_scope_bulan_jadi_hari_terakhir_bulan_itu(): void
    {
        $this->assertSame(
            '2026-07-31',
            KpiKepegawaianPeriode::batasDariScope('2026-07')->toDateString(),
            'Batas jatuh di awal bulan. Dengan batas awal bulan, karyawan yang resign '.
            '15 Juli tetap ikut terhitung sebagai "belum update" Juli, padahal ia tidak '.
            'menjalani bulan itu penuh.',
        );
    }

    public function test_scope_tahun_jadi_akhir_januari_bukan_tanggal_hari_ini(): void
    {
        $this->assertSame(
            '2026-01-31',
            KpiKepegawaianPeriode::batasDariScope('2026')->toDateString(),
            "Scope submission berupa '2026' harus dibaca sebagai TAHUN. Bila dibaca ".
            'sebagai tanggal, batasnya meleset dan universe setahun penuh ikut bergeser.',
        );
    }

    public function test_scope_ytd_memakai_periode_terawal_bukan_terakhir(): void
    {
        $periods = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'];

        $this->assertSame(
            '2026-01-31',
            KpiKepegawaianPeriode::batasDariScope($periods)->toDateString(),
            'Batas YTD diambil dari bulan TERAKHIR. Orang yang dinilai Januari lalu '.
            'resign Februari akan hilang dari akumulasi Jan–Mei, padahal skornya ikut '.
            'dijumlahkan di dalam angka yang sama.',
        );
    }

    public function test_daftar_ytd_tidak_bergantung_pada_urutan_masukan(): void
    {
        $acak = ['2026-05', '2026-02', '2026-08', '2026-01'];

        $this->assertSame(
            '2026-01-31',
            KpiKepegawaianPeriode::batasDariScope($acak)->toDateString(),
        );
    }

    public function test_scope_kosong_melebar_bukan_menyempit(): void
    {
        $batas = KpiKepegawaianPeriode::batasDariScope('');

        $this->assertTrue(
            $batas->year <= 1900,
            'Scope kosong menghasilkan batas masa kini. Penyaring yang gagal ke arah '.
            '"sembunyikan" akan menghapus karyawan dari laporan tanpa jejak begitu ada '.
            'pemanggil baru yang lupa mengisi scope.',
        );
    }

    public function test_februari_kabisat_tidak_dipotong_ke_28(): void
    {
        $this->assertSame(
            '2028-02-29',
            KpiKepegawaianPeriode::batasDariScope('2028-02')->toDateString(),
            'Akhir bulan dihitung dengan angka tetap, bukan dari kalender. Pada tahun '.
            'kabisat batasnya mundur sehari dan orang yang resign 29 Februari ikut hilang.',
        );
    }

    public function test_penyaring_menerima_yang_masih_aktif_maupun_yang_resign_sesudah_batas(): void
    {
        // Tidak dieksekusi: hanya bentuk SQL + binding yang diperiksa.
        $query = KpiKepegawaianPeriode::terapkan(
            DB::table('Karyawan as k'),
            Carbon::parse('2026-07-31'),
        );

        $sql = $query->toSql();

        $this->assertStringContainsString('Aktif', $sql);
        $this->assertStringContainsString('Tanggal_Resign', $sql);
        $this->assertStringContainsString(
            ' or ',
            strtolower($sql),
            'Penyaring tidak memakai OR. Tanpa cabang kedua, seluruh karyawan yang sudah '.
            'resign hilang termasuk yang menjalani periodenya penuh.',
        );

        // Binding tanggal bisa berupa Carbon atau string ber-jam, tergantung versi
        // grammar. Yang ditegaskan tanggalnya, bukan bentuk penyimpanannya.
        $bindings = array_map(
            fn ($v) => $v instanceof Carbon ? $v->toDateTimeString() : (string) $v,
            $query->getBindings(),
        );

        $this->assertContains('Y', $bindings);
        $this->assertTrue(
            (bool) array_filter($bindings, fn ($v) => str_starts_with($v, '2026-07-31')),
            'Batas yang dikirim ke SQL bukan tanggal yang diminta pemanggil. Binding: '
            .json_encode($bindings),
        );
    }
}
