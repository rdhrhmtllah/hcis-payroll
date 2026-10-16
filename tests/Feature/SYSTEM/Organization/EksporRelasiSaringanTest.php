<?php

namespace Tests\Feature\SYSTEM\Organization;

use App\Services\SYSTEM\Organization\OrganizationRelationExportService;
use App\Services\SYSTEM\Organization\OrganizationRelationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saringan subjek di modal Export Relasi: angka "N karyawan cocok" yang dilihat admin harus sama
 * dengan subjek yang benar-benar diekspor. Hanya membaca data dev.
 */
class EksporRelasiSaringanTest extends TestCase
{
    private function relasiAktifUntuk(array $kode): int
    {
        return DB::table('N_HRIS_Organization_Relationship')->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')
            ->whereIn('Kode_Karyawan_Subjek', $kode)->count();
    }

    public function test_semua_yang_cocok_saringan_sama_dengan_daftar_kandidat(): void
    {
        $opsi = app(OrganizationRelationService::class)->opsiFilter();
        $divisi = array_slice($opsi['divisi'] ?? [], 0, 2);
        $level = array_slice(array_column($opsi['level'] ?? [], 'id'), 0, 2);
        if (! $divisi) {
            $this->markTestSkipped('Data dev tanpa divisi.');
        }
        $saringan = ['divisi' => $divisi, 'id_level' => $level];
        $kode = app(OrganizationRelationService::class)->kandidatQuery($saringan)->pluck('k.Kode_Karyawan')->all();

        $hasil = app(OrganizationRelationExportService::class)->buat(['subjek_divisi' => $divisi, 'subjek_level' => $level], 'uji');

        $this->assertSame($this->relasiAktifUntuk($kode), $hasil['jumlah']);
    }

    public function test_karyawan_terpilih_hanya_mengekspor_relasi_mereka(): void
    {
        $dua = DB::table('N_HRIS_Organization_Relationship')->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')
            ->select('Kode_Perusahaan_Subjek', 'Kode_Karyawan_Subjek')->distinct()->limit(2)->get();
        if ($dua->count() < 2) {
            $this->markTestSkipped('Relasi dev kurang.');
        }
        $hasil = app(OrganizationRelationExportService::class)->buat([
            'karyawan' => $dua->map(fn ($r) => trim($r->Kode_Perusahaan_Subjek).'|'.trim($r->Kode_Karyawan_Subjek))->all(),
        ], 'uji');

        $this->assertSame($this->relasiAktifUntuk($dua->pluck('Kode_Karyawan_Subjek')->all()), $hasil['jumlah']);
    }

    public function test_tanpa_saringan_mengekspor_semua_relasi_aktif(): void
    {
        $hasil = app(OrganizationRelationExportService::class)->buat([], 'uji');
        $this->assertSame(
            DB::table('N_HRIS_Organization_Relationship')->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')->count(),
            $hasil['jumlah'],
        );
    }
}
