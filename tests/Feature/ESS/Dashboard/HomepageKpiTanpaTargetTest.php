<?php

namespace Tests\Feature\ESS\Dashboard;

use App\Services\ESS\Dashboard\Homepage\HomepageKpiService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Homepage tidak boleh menyuruh update KPI saat tak ada target jatuh tempo.
 *
 * Halaman Update KPI menampilkan "Tidak Ada Target" untuk orang yang tidak punya
 * target bulan itu (UpdatePerformanceTanpaTargetTest), dan monitoring melabelinya
 * sama. Kartu homepage dulu tetap berbunyi "Update performance belum diisi" dan
 * menampilkan tenggat — menyuruh orang mengisi sesuatu yang tidak ada. Hanya
 * kartu ber-tone warning yang masuk pusat tindakan (homepage.vue), jadi tone
 * itulah yang dijaga.
 */
class HomepageKpiTanpaTargetTest extends TestCase
{
    private function jadwal(): object
    {
        return (object) ['Periode' => '2036-05', 'Jadwal_Akhir' => '2036-06-10 23:59:59'];
    }

    private function panggil(string $metode, ...$argumen)
    {
        $refleksi = new \ReflectionMethod(HomepageKpiService::class, $metode);
        $refleksi->setAccessible(true);

        return $refleksi->invoke(app(HomepageKpiService::class), ...$argumen);
    }

    private function kartuPerformance(int $jatuhTempo): ?array
    {
        $kartu = $this->panggil('buildPendingItems', null, $this->jadwal(), true, false, $jatuhTempo);

        return collect($kartu)->firstWhere('id', 'performance');
    }

    public function test_tanpa_target_jatuh_tempo_kartunya_info_bukan_peringatan(): void
    {
        $kartu = $this->kartuPerformance(0);

        $this->assertNotNull($kartu);
        $this->assertSame('info', $kartu['tone'], 'Orang tanpa target masih diperingatkan untuk update di homepage.');
        $this->assertStringNotContainsString('belum diisi', $kartu['title']);
    }

    public function test_ada_target_jatuh_tempo_tetap_peringatan(): void
    {
        $kartu = $this->kartuPerformance(3);

        $this->assertSame('warning', $kartu['tone']);
        $this->assertStringContainsString('3 parameter jatuh tempo', $kartu['description']);
    }

    /**
     * Status "Sudah Mengisi ✓" harus milik periode yang ditanyakan.
     *
     * Ditemukan saat uji manual (Aisyah, periode Oktober): tak ada baris
     * Oktober, tetapi resolver jatuh ke baris September yang disetujui dan
     * homepage menampilkan "Sudah Mengisi ✓" untuk Oktober. Berlaku bagi siapa
     * pun yang belum mengisi bulan berjalan, bukan hanya yang tanpa target.
     */
    public function test_status_approval_performance_terikat_periode_yang_ditanyakan(): void
    {
        $idUser = (int) DB::table('Karyawan')->whereNotNull('UserID_Web')->value('UserID_Web');
        if (! $idUser) {
            $this->markTestSkipped('Tak ada karyawan acuan.');
        }

        DB::beginTransaction();
        try {
            DB::table('KPI_Update_Performance')->insert([
                'No_Transaksi' => 'UJI-HP-'.bin2hex(random_bytes(4)),
                'Id_Pengajuan' => DB::table('KPI_Pengajuan')->value('Id_Pengajuan'),
                'Id_User' => $idUser,
                'Periode' => '2036-09',
                'Status_Pengajuan' => 'disetujui',
                'Flag_Approval' => 'Y',
                'Flag_Aktif' => 'Y',
                'Status' => null,
                'Tanggal' => now(),
            ]);

            $status = fn ($periode) => $this->panggil('resolvePerformanceApprovalBatch', [$idUser], 2036, $periode)[$idUser]['status'];

            $this->assertSame('none', $status('2036-10'), 'Baris September menutupi Oktober yang belum diisi.');
            $this->assertSame('none', $status([(string) $idUser => '2036-10']), 'Peta periode per orang tidak dihormati.');
            $this->assertSame('approved', $status('2036-09'));
            $this->assertSame('approved', $status(null), 'Tanpa periode, baris terbaru tetap dipakai seperti sebelumnya.');
        } finally {
            DB::rollBack();
        }
    }

    public function test_tenggat_update_tidak_ditampilkan_tanpa_target(): void
    {
        $this->assertNull(
            $this->panggil('buildDeadlineText', null, $this->jadwal(), true, false, 0),
            'Tenggat update performance masih ditampilkan untuk orang tanpa target.',
        );
        $this->assertNotNull($this->panggil('buildDeadlineText', null, $this->jadwal(), true, false, 2));
    }
}
