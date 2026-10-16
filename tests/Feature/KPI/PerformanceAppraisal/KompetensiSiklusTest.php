<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKompetensiSiklus;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use ReflectionMethod;
use RuntimeException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Salinan atribut kompetensi per siklus (DDL-2026-170): suntingan master sesudah siklus
 * dikunci tidak boleh menggeser KKM, radar, atau status berskor siklus itu.
 */
class KompetensiSiklusTest extends TestCase
{
    use DatabaseTransactions;

    private function siklus(): int
    {
        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-KS-'.bin2hex(random_bytes(3)),
            'Nama_Cycle' => 'Uji Salinan Kompetensi',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function baca(int $cycleId, int $kompetensiId): object
    {
        $q = DB::table('N_PA_Competency as c')->where('c.Id_PA_Competency', $kompetensiId);

        return PaKompetensiSiklus::gabung($q, 'c', $cycleId)->first(array_map(
            fn ($k) => PaKompetensiSiklus::kolom($k, 'c'), PaKompetensiSiklus::KOLOM));
    }

    public function test_siklus_bersalinan_tidak_ikut_suntingan_master_dan_ambang_null_tetap_null(): void
    {
        $k = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->first();
        if (! $k) {
            $this->markTestSkipped('Master kompetensi kosong.');
        }
        $id = (int) $k->Id_PA_Competency;
        DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)
            ->update(['Nilai_Ambang' => null, 'Flag_Radar' => 'Y', 'Flag_Berskor' => 'Y']);

        $beku = $this->siklus();
        $lama = $this->siklus();
        $this->assertGreaterThan(0, PaKompetensiSiklus::bekukan($beku, 1));
        $this->assertSame(0, PaKompetensiSiklus::bekukan($beku, 1), 'Pembekuan ulang tidak boleh menulis lagi.');

        DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)
            ->update(['Nilai_Ambang' => 3.5, 'Flag_Radar' => 'T', 'Flag_Berskor' => 'T']);

        $b = $this->baca($beku, $id);
        $this->assertNull($b->Nilai_Ambang, 'Ambang NULL di salinan berarti tanpa KKM, bukan "pakai master".');
        $this->assertSame('Y', $b->Flag_Radar);
        $this->assertSame('Y', $b->Flag_Berskor);

        $l = $this->baca($lama, $id);
        $this->assertEquals(3.5, (float) $l->Nilai_Ambang, 'Siklus tanpa salinan tetap membaca master.');
        $this->assertSame('T', $l->Flag_Radar);
        $this->assertSame('T', $l->Flag_Berskor);
    }

    public function test_penjaga_berskor_hanya_menahan_siklus_berjalan_yang_belum_bersalinan(): void
    {
        $siklusBerjalan = fn () => DB::table('N_PA_Cycle_Question_Package_Item as i')
            ->join('N_PA_Cycle_Question_Package as k', 'k.Id_PA_Cycle_Question_Package', '=', 'i.PA_Cycle_Question_Package_Id')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'k.PA_Cycle_Id')
            ->where('i.Flag_Aktif', 'Y')->where('c.Flag_Aktif', 'Y')
            ->whereIn('c.Status_Cycle', ['LOCKED', 'OPEN', 'CLOSED']);

        $baris = $siklusBerjalan()->whereNotNull('i.PA_Competency_Id')->first(['i.PA_Competency_Id']);
        if (! $baris) {
            $this->markTestSkipped('Tidak ada siklus berjalan berpaket di database uji.');
        }
        $id = (int) $baris->PA_Competency_Id;
        $siklusLain = $siklusBerjalan()->where('i.PA_Competency_Id', $id)->distinct()->pluck('c.Id_PA_Cycle');

        // PREPARED belum dikunci dan tak pernah bersalinan; dinetralkan agar uji hanya menimbang salinan.
        $dipakai = $siklusBerjalan()->where('i.PA_Competency_Id', $id)->count();
        $this->assertGreaterThan(0, $dipakai);
        DB::table('N_PA_Cycle')->where('Status_Cycle', 'PREPARED')->update(['Status_Cycle' => 'DRAFT']);
        DB::table(PaKompetensiSiklus::TABEL)->where('PA_Competency_Id', $id)->delete();

        $berskor = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Flag_Berskor');
        $sebaliknya = $berskor === 'Y' ? 'T' : 'Y';
        $penjaga = new ReflectionMethod(PaMasterWriteService::class, 'pastikanBerskorTidakBerubahDiSiklusBerjalan');
        $layanan = app(PaMasterWriteService::class);

        try {
            $penjaga->invoke($layanan, $id, $sebaliknya);
            $this->fail('Siklus berjalan tanpa salinan harus menahan perubahan status berskor.');
        } catch (RuntimeException) {
        }

        foreach ($siklusLain as $c) {
            PaKompetensiSiklus::bekukan((int) $c, 1);
        }

        $penjaga->invoke($layanan, $id, $sebaliknya);
        $this->addToAssertionCount(1);
    }
}
