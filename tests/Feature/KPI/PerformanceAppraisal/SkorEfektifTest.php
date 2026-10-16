<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Satu pintu skor efektif (spec §4.4, §5.4): kalibrasi terbaru menang atas skor asli HC
 * Learn, skor asli tidak pernah ditimpa, dan perhitungan memakai skor efektif.
 */
class SkorEfektifTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    public function test_tanpa_kalibrasi_skor_efektif_sama_dengan_asli(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85]]);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']];

        $this->assertCount(2, $butir);
        $this->assertSame([70.0, 85.0], $butir->pluck('Skor')->all());
        $this->assertSame([70.0, 85.0], $butir->pluck('Skor_Asli')->all());
        $this->assertSame([false, false], $butir->pluck('Terkalibrasi')->all());
    }

    public function test_kalibrasi_terbaru_menang_dan_skor_asli_tidak_tersentuh(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85]]);
        $k = $l['kompetensi'][0];

        $this->kalibrasiLangsung($l['assignment_id'], $k, 70, 60);
        $this->kalibrasiLangsung($l['assignment_id'], $k, 60, 65);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']]
            ->keyBy('PA_Competency_Id');

        $this->assertSame(65.0, $butir[$k]->Skor, 'Yang berlaku harus kalibrasi TERAKHIR.');
        $this->assertSame(70.0, $butir[$k]->Skor_Asli);
        $this->assertTrue($butir[$k]->Terkalibrasi);
        $this->assertSame(85.0, $butir[$l['kompetensi'][1]]->Skor, 'Kompetensi lain tidak boleh ikut berubah.');

        $this->assertEquals(70, (float) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])
            ->where('PA_Competency_Id', $k)
            ->value('Skor'), 'N_PA_Assignment_Score tidak boleh ditimpa.');
    }

    public function test_payload_terakhir_yang_berlaku(): void
    {
        $l = $this->lembarTerisi(['skor' => [70]]);
        $integrasi = (int) DB::table('N_PA_Assignment_Payload')
            ->where('Id_PA_Assignment_Payload', $l['payload_id'])
            ->value('PA_Assignment_Integration_Id');

        $baru = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
            'PA_Assignment_Integration_Id' => $integrasi,
            'Payload_Json' => '{}',
            'Payload_Hash' => hash('sha256', random_bytes(16)),
            'Flag_Terpeta' => 'Y',
            'Mapper_Version' => 1,
            'Status_Skor' => 'FINAL',
        ], 'Id_PA_Assignment_Payload');

        DB::table('N_PA_Assignment_Score')->insert([
            'PA_Assignment_Payload_Id' => $baru,
            'PA_Competency_Id' => $l['kompetensi'][0],
            'Kode_Soal_Sumber' => 'UJI1',
            'Skor' => 90,
            'Skor_Maksimal' => 100,
            'Mapper_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']];

        $this->assertSame([90.0], $butir->pluck('Skor')->all());
    }

    /**
     * Kalibrasi atas isian LAMA gugur begitu ada kiriman terpeta yang lebih baru (14 Okt 2026):
     * penilai yang mengisi ulang lewat "Akses ulang" tidak boleh tertimpa revisi lamanya.
     */
    public function test_kalibrasi_lebih_tua_dari_kiriman_terbaru_gugur(): void
    {
        $l = $this->lembarTerisi(['skor' => [70]]);
        $k = $l['kompetensi'][0];
        $kalibrasi = $this->kalibrasiLangsung($l['assignment_id'], $k, 70, 60);
        DB::table('N_PA_Kalibrasi_Penilai')->where('Id_PA_Kalibrasi_Penilai', $kalibrasi)
            ->update(['Created_At' => now()->subHour()->format('Y-m-d H:i:s')]);

        $integrasi = (int) DB::table('N_PA_Assignment_Payload')->where('Id_PA_Assignment_Payload', $l['payload_id'])
            ->value('PA_Assignment_Integration_Id');
        DB::table('N_PA_Assignment_Payload')->where('Id_PA_Assignment_Payload', $l['payload_id'])
            ->update(['Diterima_At' => now()->subHours(2)->format('Y-m-d H:i:s')]);

        $this->assertSame(60.0, app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']]->first()->Skor,
            'Kalibrasi sesudah kiriman yang berlaku tetap menang.');

        $baru = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
            'PA_Assignment_Integration_Id' => $integrasi,
            'Payload_Json' => '{}',
            'Payload_Hash' => hash('sha256', random_bytes(16)),
            'Diterima_At' => now()->format('Y-m-d H:i:s'),
            'Flag_Terpeta' => 'Y',
            'Mapper_Version' => 1,
            'Status_Skor' => 'FINAL',
        ], 'Id_PA_Assignment_Payload');
        DB::table('N_PA_Assignment_Score')->insert([
            'PA_Assignment_Payload_Id' => $baru, 'PA_Competency_Id' => $k, 'Kode_Soal_Sumber' => 'UJI1',
            'Skor' => 90, 'Skor_Maksimal' => 100, 'Mapper_Version' => 1, 'Flag_Aktif' => 'Y',
        ]);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']]->first();
        $this->assertSame(90.0, $butir->Skor, 'Isian ulang tidak boleh tertimpa kalibrasi atas isian lama.');
        $this->assertFalse($butir->Terkalibrasi);
    }

    public function test_jawaban_naratif_dari_payload_terakhir(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'naratif' => 'Konsisten menepati tenggat.']);

        $naratif = app(PaSkorEfektif::class)->jawabanNaratif([$l['assignment_id']]);

        $this->assertSame('Konsisten menepati tenggat.', $naratif[$l['assignment_id']][0]['jawaban']);
    }

    public function test_daftar_kosong_tidak_menyentuh_basis_data(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame([], app(PaSkorEfektif::class)->butirBerskor([]));
        $this->assertSame([], DB::getQueryLog());
    }

    /**
     * Titik sambung perhitungan diuji langsung lewat refleksi. `hitung()` penuh menuntut
     * salinan aturan, pita nilai, dan KPI — menyiapkannya menguji jauh lebih banyak dari
     * sambungan yang dimaksud, dan kegagalannya tidak akan menunjuk ke sini.
     */
    public function test_perhitungan_memakai_skor_efektif(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85], 'status_penugasan' => 'SELESAI']);
        $this->kalibrasiLangsung($l['assignment_id'], $l['kompetensi'][0], 70, 50);

        $m = new \ReflectionMethod(PaResultCalculationService::class, 'penugasanDenganNilai');
        $m->setAccessible(true);
        $hasil = $m->invoke(app(PaResultCalculationService::class), $l['participant_id']);

        $penilai = $hasil[$l['kategori_id']]['penilai'][0];

        // Tanpa bobot butir: rata-rata biasa atas skor ternormalisasi, (50 + 85) / 2.
        $this->assertEqualsWithDelta(67.5, $penilai['nilai'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $penilai['kompetensi'][$l['kompetensi'][0]], 0.0001);
    }
}
