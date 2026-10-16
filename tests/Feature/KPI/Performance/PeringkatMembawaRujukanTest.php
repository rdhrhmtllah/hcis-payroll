<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\Executive\DashboardCeoController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baris papan peringkat harus membawa rujukan transaksinya.
 *
 * Modal detail dibuka dari `Id_Update_Performance`: dari situ ia menurunkan
 * karyawannya, merakit isi, dan menyusun strip 12 bulan. Daftar update sejak
 * awal mengirimkannya, tetapi Top/Under Performers dulu hanya mengirim nama,
 * skor, divisi, dan departemen. Akibatnya klik pada baris peringkat membuka
 * modal yang judulnya berisi nama orangnya — nama itu memang ada di barisnya —
 * sementara profil, capaian, dan seluruh parameternya kosong: jalur lamanya
 * dipanggil tanpa satu pun id.
 *
 * Kegagalannya diam: tak ada galat, tak ada jejak, hanya modal kosong yang
 * terbaca seperti "karyawan ini memang belum punya data".
 */
class PeringkatMembawaRujukanTest extends TestCase
{
    use DatabaseTransactions;

    private function pengakses(): User
    {
        /* Butuh akun yang cakupannya lebar: yang tak punya akses dijawab
           "Data Tidak Ditemukan" dan papan peringkatnya kosong — hijau palsu. */
        $id = DB::table('KPI_User_Akses_Divisi as ad')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'ad.Id_User')
            ->whereNotExists(fn ($q) => $q->from('KPI_Karyawan_Team as t')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Leader')
                ->whereColumn('k.UserID_Web', 'ad.Id_User'))
            ->groupBy('ad.Id_User')
            ->orderByRaw('COUNT(*) DESC')
            ->value('ad.Id_User');

        if (! $id || ! ($user = User::find($id))) {
            $this->markTestSkipped('Butuh akun berakses divisi tanpa tim sendiri.');
        }

        return $user;
    }

    private function hasil(User $user, string $bulan): array
    {
        $this->actingAs($user);

        $r = Request::create('/api/v2/dashboard-ceo/all', 'GET', [
            'page' => 1, 'limit' => 5, 'tahun' => (string) now()->year, 'bulan' => $bulan,
            'divisi' => 'ALL', 'departemen' => 'ALL', 'level' => 'ALL',
            'levelTop' => 'ALL', 'levelUnder' => 'ALL',
            'heatmap_page' => 1, 'heatmap_limit' => 5,
        ]);
        $r->setUserResolver(fn () => $user);

        return app(DashboardCeoController::class)->getDataInformasiV2($r)->getData(true)['result'] ?? [];
    }

    /**
     * Tiga mode periode dirakit tiga jalur berbeda di controllernya — bulanan,
     * Full-Year, dan YTD — jadi ketiganya diuji; dulu hanya satu yang benar.
     */
    public function test_papan_peringkat_membawa_rujukan_di_tiga_mode(): void
    {
        $user = $this->pengakses();
        $adaBaris = false;

        foreach (['ALL', 'YTD', str_pad((string) now()->month, 2, '0', STR_PAD_LEFT)] as $bulan) {
            $hasil = $this->hasil($user, $bulan);

            foreach (['Top_3_Performers', 'Under_3_Performers'] as $papan) {
                foreach ($hasil[$papan] ?? [] as $baris) {
                    $adaBaris = true;

                    $this->assertArrayHasKey('Id_Update_Performance', $baris,
                        "{$papan} pada bulan={$bulan} tak membawa rujukan transaksi.");

                    /* Boleh null (orangnya tak punya update hidup di periode itu);
                       yang tak boleh adalah kuncinya hilang sama sekali, sebab
                       layar memakai ada-tidaknya nilai itu untuk memutuskan
                       barisnya boleh diklik atau tidak. */
                    if ($baris['Id_Update_Performance'] !== null) {
                        $this->assertIsString($baris['Id_Update_Performance']);
                        $this->assertNotSame('', $baris['Id_Update_Performance']);
                    }
                }
            }
        }

        if (! $adaBaris) {
            $this->markTestSkipped('Tak ada baris peringkat pada tahun berjalan di basis data ini.');
        }
    }

    /** Rujukannya harus ter-hash, sebentuk dengan yang dipakai daftar update. */
    public function test_rujukan_peringkat_sebentuk_dengan_daftar_update(): void
    {
        $user = $this->pengakses();
        $hasil = $this->hasil($user, 'ALL');

        $daftar = collect($hasil['Recent_Kpi_Update']['data'] ?? [])
            ->pluck('Id_Update_Performance')->filter()->values();
        $peringkat = collect($hasil['Top_3_Performers'] ?? [])
            ->pluck('Id_Update_Performance')->filter()->values();

        if ($daftar->isEmpty() || $peringkat->isEmpty()) {
            $this->markTestSkipped('Butuh daftar update dan papan peringkat yang sama-sama berisi.');
        }

        /* Id mentah berupa angka; yang ter-hash tidak. Kalau peringkat mengirim
           angka mentah, modal akan gagal membuka detail DAN id internal bocor
           ke browser. */
        foreach ($peringkat as $ref) {
            $this->assertFalse(ctype_digit((string) $ref), "Rujukan peringkat {$ref} masih id mentah.");
        }
        $this->assertFalse(ctype_digit((string) $daftar->first()));
    }
}
