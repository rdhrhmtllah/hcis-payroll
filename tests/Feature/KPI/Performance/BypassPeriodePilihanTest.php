<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Halaman by-pass harus membuka periode yang DIPILIH operator.
 *
 * Daftar by-pass berbasis BULAN (dropdown bulan/tahun), sedangkan halaman
 * detailnya berbasis JADWAL. Tanpa jembatan di antara keduanya, pilihan
 * operator hilang di tengah jalan: baris muncul karena April belum terisi,
 * lalu yang terbuka justru Mei — periode acuan global dari
 * KPI_Get_Date_Dummy2.
 *
 * Gejalanya menyesatkan karena halamannya tetap terbuka dan tetap berisi data
 * yang sah; hanya bulannya yang bukan yang diminta.
 */
class BypassPeriodePilihanTest extends TestCase
{
    use DatabaseTransactions;

    private function propsDari($response): array
    {
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('props');
        $prop->setAccessible(true);

        return $prop->getValue($response);
    }

    private function periodeYangTerbuka(string $hashUser, array $query): ?string
    {
        request()->replace($query);

        $props = $this->propsDari(
            (new UpdatePerformanceController)->showUpdatePerformanceByPass($hashUser),
        );

        $id = Hashids::connection('custom')->decode($props['IdMasterPenjadwalan'])[0] ?? null;

        return $id
            ? DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $id)->value('Periode')
            : null;
    }

    /** @return array{user_hash: string, periode: string}|null */
    private function skenario(): ?array
    {
        // Periode penilaian yang BUKAN periode acuan by-pass — di situlah
        // perbedaannya bisa terlihat.
        $acuan = DB::table('KPI_Get_Date_Dummy2 as d')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'd.Id_Master_Penjadwalan')
            ->where('d.Flag_Aktif', 'Y')
            ->value('m.Periode');

        $lain = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->whereNotNull('Periode')
            ->when($acuan, fn ($q) => $q->where('Periode', '!=', $acuan))
            ->orderByRaw("CASE WHEN Flag_Aktif = 'Y' THEN 0 ELSE 1 END")
            ->value('Periode');

        // Karyawan yang PUNYA target jatuh tempo di periode itu: tanpa target,
        // halaman by-pass sengaja menampilkan status "Tidak Ada Target"
        // (UpdatePerformanceTanpaTargetTest), bukan formulir yang diuji di sini.
        $user = $lain ? DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'j.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->join('KPI_Pengajuan_Detail as d', 'd.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->join('KPI_Pengajuan_Det as t', 't.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'p.Id_User')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->where('j.Tahun', substr($lain, 0, 4))
            ->where('t.Periode', 'like', substr($lain, 0, 7).'%')
            ->value('p.Id_User') : null;

        if (! $lain || ! $user) {
            return null;
        }

        return [
            'user_hash' => Hashids::connection('custom')->encode($user),
            'periode' => $lain,
        ];
    }

    public function test_periode_pilihan_menentukan_jadwal_yang_dibuka(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya periode penilaian pembanding.');
        }

        $this->actingAs(User::whereNotNull('Id_Users')->first());

        $this->assertSame(
            $s['periode'],
            $this->periodeYangTerbuka($s['user_hash'], ['periode' => $s['periode']]),
            'Halaman by-pass membuka periode yang berbeda dari yang dipilih operator.',
        );
    }

    public function test_tanpa_parameter_perilaku_lama_dipertahankan(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya periode penilaian pembanding.');
        }

        $acuan = DB::table('KPI_Get_Date_Dummy2 as d')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'd.Id_Master_Penjadwalan')
            ->where('d.Flag_Aktif', 'Y')
            ->value('m.Periode');

        if (! $acuan) {
            $this->markTestSkipped('Tidak ada periode acuan by-pass yang aktif.');
        }

        // Tanpa parameter, halaman harus tetap memakai acuan global seperti
        // sebelumnya — perbaikan ini aditif, bukan penggantian perilaku.
        $this->actingAs(User::whereNotNull('Id_Users')->first());

        $this->assertSame($acuan, $this->periodeYangTerbuka($s['user_hash'], []));
    }

    public function test_periode_tanpa_jadwal_ditolak_bukan_dibelokkan(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya periode penilaian pembanding.');
        }

        $this->actingAs(User::whereNotNull('Id_Users')->first());

        // Membelokkan ke periode lain persis itulah bug yang diperbaiki di sini,
        // jadi periode tanpa jadwal harus BERHENTI, bukan diam-diam mundur.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->periodeYangTerbuka($s['user_hash'], ['periode' => '1999-01']);
    }
}
