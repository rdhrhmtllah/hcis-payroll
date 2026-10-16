<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaMasterReadService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Expected behaviour pada kompetensi — DDL-2026-166.
 *
 * Disimpan satu perilaku per baris; layar memecahnya menjadi butir. Yang dijaga di sini:
 * penanda butir yang terketik dirapikan, isian kosong menjadi NULL (bukan string kosong),
 * dan menyunting kompetensi dengan formulir lama yang belum mengenal kolom ini tidak
 * menghapus perilaku yang sudah ditulis HC.
 */
class KompetensiExpectedBehaviourTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tersimpan_satu_perilaku_per_baris_dengan_penanda_butir_dirapikan(): void
    {
        $id = $this->buat(['expected_behaviour' => "- Memeriksa data dulu\r\n\r\n2. Mencari akar masalah \n•  Menjelaskan alasan keputusan\n   "]);

        $this->assertSame(
            "Memeriksa data dulu\nMencari akar masalah\nMenjelaskan alasan keputusan",
            DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Expected_Behaviour'),
        );
    }

    public function test_isian_kosong_tersimpan_null(): void
    {
        $id = $this->buat(['expected_behaviour' => "  \n - \n"]);

        $this->assertNull(DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Expected_Behaviour'));
    }

    public function test_lebih_dari_dua_ribu_karakter_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->buat(['expected_behaviour' => str_repeat('a', 2001)]);
    }

    public function test_menyunting_tanpa_field_ini_tidak_menghapus_perilaku(): void
    {
        $id = $this->buat(['expected_behaviour' => "Satu\nDua"]);
        $kode = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Kode_Competency');

        $this->tulis()->simpan('kompetensi', $id, [
            'pa_competency_type_id' => $this->tipe(),
            'kode_competency' => $kode,
            'nama_competency' => 'Nama Baru',
        ], 1);

        $this->assertSame("Satu\nDua", DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Expected_Behaviour'));
    }

    public function test_menyunting_dengan_isian_kosong_memang_mengosongkan(): void
    {
        $id = $this->buat(['expected_behaviour' => 'Satu']);
        $kode = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Kode_Competency');

        $this->tulis()->simpan('kompetensi', $id, [
            'pa_competency_type_id' => $this->tipe(),
            'kode_competency' => $kode,
            'nama_competency' => 'Nama Baru',
            'expected_behaviour' => '',
        ], 1);

        $this->assertNull(DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Expected_Behaviour'));
    }

    /**
     * Sejak 10 Okt 2026 Expected Behaviour diisi PER TINGKAT; yang menempel di kompetensi tidak
     * lagi dibaca katalog (dan tidak tampil di layar), walau kolomnya tetap ada.
     */
    public function test_katalog_baca_tidak_lagi_memulangkan_expected_behaviour_kompetensi(): void
    {
        $id = $this->buat(['expected_behaviour' => 'Satu']);

        $baris = $this->app->make(PaMasterReadService::class)->detail('kompetensi', $id)['baris'];

        $this->assertArrayNotHasKey('Expected_Behaviour', (array) $baris);
    }

    /** @param  array<string, mixed>  $tambahan */
    private function buat(array $tambahan): int
    {
        $kode = strtoupper('UJI'.substr(bin2hex(random_bytes(3)), 0, 5));

        return (int) $this->tulis()->simpan('kompetensi', null, array_merge([
            'pa_competency_type_id' => $this->tipe(),
            'kode_competency' => $kode,
            'nama_competency' => 'Kompetensi Uji Perilaku',
            'urutan_tampil' => 99,
        ], $tambahan), 1)['id'];
    }

    private function tipe(): int
    {
        return (int) DB::table('N_PA_Competency_Type')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency_Type')->value('Id_PA_Competency_Type');
    }

    private function tulis(): PaMasterWriteService
    {
        return $this->app->make(PaMasterWriteService::class);
    }
}
