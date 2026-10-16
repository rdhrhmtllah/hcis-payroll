<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringPeopleService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kepala kartu dan isinya harus menghitung himpunan yang sama.
 *
 * Studio export menampilkan ringkasan per karyawan (dihitung server DENGAN
 * saringan) lalu daftar paketnya di bawahnya. Selama daftar itu diambil dari
 * endpoint yang tak mengenal saringan, keduanya bicara tentang himpunan berbeda:
 * kepala kartu menyebut 1 SOP sementara isinya memperlihatkan 4. Invarian di
 * bawah inilah yang dulu dilanggar.
 */
class SopPaketTersaringTest extends TestCase
{
    private function svc(): SopMonitoringPeopleService
    {
        return app(SopMonitoringPeopleService::class);
    }

    private function kodeUji(): string
    {
        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->groupBy('Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu karyawan dengan penugasan dokumen aktif.');

        return $kode;
    }

    /** total_sop di kepala kartu, dihitung lewat jalur daftar karyawan. */
    private function totalDiKepala(string $kode, array $f): int
    {
        [$rows] = $this->svc()->employees($f + ['q' => $kode], 1, 50);
        $baris = $rows->firstWhere('Kode_Karyawan', $kode);

        return $baris ? (int) $baris->total_sop : 0;
    }

    public function test_package_list_length_matches_the_headline_count(): void
    {
        $kode = $this->kodeUji();

        $this->assertSame(
            $this->totalDiKepala($kode, []),
            count($this->svc()->paketTersaring($kode, [])),
        );
    }

    public function test_the_two_still_agree_once_a_package_filter_narrows_the_scope(): void
    {
        $kode = $this->kodeUji();
        $f = ['prioritas' => SopStatus::PRIORITAS_NORMAL];

        // Inilah keadaan yang memunculkan gejalanya: begitu ada saringan
        // berlingkup paket, daftar tanpa saringan langsung menyimpang.
        $this->assertSame(
            $this->totalDiKepala($kode, $f),
            count($this->svc()->paketTersaring($kode, $f)),
        );
    }

    public function test_completed_items_are_counted_once_per_item_not_per_version(): void
    {
        $kode = $this->kodeUji();

        foreach ($this->svc()->paketTersaring($kode, []) as $p) {
            // Satu item bisa punya beberapa baris progress bila versinya pernah
            // berganti; COUNT(*) akan membuat persen tembus 100 tanpa error.
            $this->assertLessThanOrEqual($p['Total_Item'], $p['Item_Selesai'], "Paket {$p['Nama_Paket']}");
            $this->assertLessThanOrEqual(100, $p['Persen'], "Paket {$p['Nama_Paket']}");
        }
    }

    public function test_package_identity_is_opaque_not_a_raw_id(): void
    {
        $paket = $this->svc()->paketTersaring($this->kodeUji(), []);

        $this->assertNotEmpty($paket);
        $this->assertFalse(ctype_digit((string) $paket[0]['Id_SOP_Paket']));
    }

    public function test_unknown_employee_yields_an_empty_list_not_an_error(): void
    {
        $this->assertSame([], $this->svc()->paketTersaring('ZZ-TIDAK-ADA', []));
    }
}
