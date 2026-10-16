<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiException;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Esai PER SOAL (DDL-2026-140, DDL-2026-119 r3).
 *
 * Sejak 26 September 2026 HC Learn mengirim `Esai[]` per bagian: satu entri per soal, dengan
 * pertanyaan dan jawabannya sendiri, sementara `Jawaban_Teks` bagian dikosongkan. Tanpa
 * pembaca baru, esai format ini hilang dari seluruh layar dan jenis kompetensi kualitatifnya
 * terbaca "tidak tercakup". Rancangan: docs/superpowers/specs/2026-09-26-pa-esai-per-soal-design.md
 */
class EsaiPerSoalTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private const KODE = 'UJI-ESAI';

    private function lembarPerSoal(array $soal = []): array
    {
        return $this->lembarTerisi([
            'skor' => [70],
            'boleh_kalibrasi' => 'Y',
            'esai_per_soal' => $soal ?: [
                ['id' => 101, 'pertanyaan' => 'Ceritakan pekerjaan yang menumpuk.', 'jawaban' => 'Saya urutkan menurut tenggat.'],
                ['id' => 102, 'pertanyaan' => 'Bagaimana bila rekan tidak kooperatif?', 'jawaban' => 'Saya ajak bicara empat mata.'],
            ],
        ]);
    }

    private function naratif(array $l): array
    {
        return app(PaSkorEfektif::class)->jawabanNaratif([$l['assignment_id']])[$l['assignment_id']] ?? [];
    }

    public function test_naratif_satu_entri_per_soal_beserta_pertanyaannya(): void
    {
        $l = $this->lembarPerSoal();

        $n = $this->naratif($l);

        $this->assertCount(2, $n, 'Setiap soal esai harus menjadi satu jawaban tersendiri.');
        $this->assertSame([self::KODE, 101, 'Ceritakan pekerjaan yang menumpuk.', 'Saya urutkan menurut tenggat.'],
            [$n[0]['kode_soal'], $n[0]['id_master_soal'], $n[0]['pertanyaan'], $n[0]['jawaban']]);
        $this->assertSame(102, $n[1]['id_master_soal']);
        $this->assertTrue($n[0]['per_soal']);
    }

    public function test_format_lama_tetap_satu_entri_per_bagian_tanpa_pertanyaan(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'naratif' => 'Jawaban lama satu bagian.']);

        $n = $this->naratif($l);

        $this->assertCount(1, $n);
        $this->assertNull($n[0]['id_master_soal']);
        $this->assertNull($n[0]['pertanyaan']);
        $this->assertFalse($n[0]['per_soal']);
    }

    public function test_soal_direvisi_terpisah_meski_satu_bagian(): void
    {
        $l = $this->lembarPerSoal();

        $hasil = app(PaKalibrasiPenilaiService::class)->simpan($l['penilai'], $l['assignment_id'], [], 'Diperjelas', 7, [
            ['kode_soal' => self::KODE, 'id_master_soal' => 102, 'jawaban' => 'Saya dengarkan dulu alasannya.'],
        ]);

        $this->assertSame(1, $hasil['diubah_jawaban']);
        $this->assertSame(102, (int) DB::table('N_PA_Kalibrasi_Penilai_Jawaban')
            ->where('PA_Kalibrasi_Penilai_Id', $hasil['kalibrasi_id'])->value('Id_Master_Soal'));

        $n = $this->naratif($l);
        $this->assertSame('Saya urutkan menurut tenggat.', $n[0]['jawaban'], 'Soal lain di bagian yang sama ikut berubah.');
        $this->assertFalse($n[0]['terkalibrasi']);
        $this->assertSame('Saya dengarkan dulu alasannya.', $n[1]['jawaban']);
        $this->assertTrue($n[1]['terkalibrasi']);
        $this->assertSame('Saya ajak bicara empat mata.', $n[1]['jawaban_asli']);
    }

    public function test_soal_per_soal_tanpa_id_master_soal_ditolak(): void
    {
        $l = $this->lembarPerSoal();

        try {
            app(PaKalibrasiPenilaiService::class)->simpan($l['penilai'], $l['assignment_id'], [], 'Diperjelas', 7, [
                ['kode_soal' => self::KODE, 'jawaban' => 'Tanpa menyebut soal yang mana.'],
            ]);
            $this->fail('Revisi tanpa id soal pada bagian per soal harus ditolak.');
        } catch (PaKalibrasiPenilaiException $e) {
            $this->assertSame(PaKalibrasiPenilaiException::SOAL_TIDAK_DI_LEMBAR, $e->errorCode);
        }

        $this->assertSame(0, DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count());
    }

    public function test_http_menerima_dua_soal_dari_bagian_yang_sama(): void
    {
        $l = $this->lembarPerSoal();
        $this->actingAs($this->penilaiUji());

        $r = $this->postJson('/api/v1/performance-appraisal/saya/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan_jawaban' => [
                ['kode_soal' => self::KODE, 'id_master_soal' => 101, 'jawaban' => 'Revisi pertama.'],
                ['kode_soal' => self::KODE, 'id_master_soal' => 102, 'jawaban' => 'Revisi kedua.'],
            ],
            'alasan' => 'Diperjelas',
        ]);

        $this->assertSame(200, $r->status(), (string) $r->json('message'));
        $this->assertSame(2, $r->json('result.diubah_jawaban'));

        $naratif = $r->json('result.pratinjau.orang.0.naratif');
        $this->assertSame(['Ceritakan pekerjaan yang menumpuk.', 'Bagaimana bila rekan tidak kooperatif?'],
            array_column($naratif, 'pertanyaan'));
        $this->assertSame([101, 102], array_column($naratif, 'id_master_soal'));
    }

    public function test_jenis_kualitatif_tercakup_lewat_esai_per_soal(): void
    {
        $l = $this->lembarPerSoal();

        $jenis = (int) DB::table('N_PA_Competency')->where('Id_PA_Competency', DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])->where('Kode_Soal_Sumber', self::KODE)
            ->value('PA_Competency_Id'))->value('PA_Competency_Type_Id');

        $baca = new ReflectionMethod(PaResultCalculationService::class, 'jenisTerjawabTanpaAngka');
        $baca->setAccessible(true);

        $this->assertContains($jenis, $baca->invoke(app(PaResultCalculationService::class), $l['participant_id']),
            'Jenis kualitatif yang dijawab lewat Esai[] terbaca tidak tercakup; hasilnya akan TERTAHAN.');
    }

    public function test_catatan_hasil_satu_per_soal_dengan_pertanyaannya(): void
    {
        $l = $this->lembarPerSoal([
            ['id' => 101, 'pertanyaan' => 'Apa <kekuatan> utamanya?', 'jawaban' => 'Rapi & teliti.'],
            ['id' => 102, 'pertanyaan' => null, 'jawaban' => 'Soal ini sudah dihapus di HC Learn.'],
        ]);
        $this->ikatKePaket($l);

        $resultId = (int) DB::table('N_PA_Result')->insertGetId([
            'PA_Participant_Id' => $l['participant_id'],
            'Status_Hasil' => 'DIHITUNG',
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Result');

        $simpan = new ReflectionMethod(PaResultCalculationService::class, 'simpanCatatanDariPenilai');
        $simpan->setAccessible(true);
        $simpan->invoke(app(PaResultCalculationService::class), $resultId, $l['participant_id'], null);

        $this->assertSame([
            '<p><strong>Apa &lt;kekuatan&gt; utamanya?</strong></p><p>Rapi &amp; teliti.</p>',
            '<p>Soal ini sudah dihapus di HC Learn.</p>',
        ], DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')->pluck('Isi')->all());
    }

    public function test_detail_admin_membawa_pertanyaan_per_jawaban(): void
    {
        $l = $this->lembarPerSoal();

        $baca = new ReflectionMethod(PaParticipantReadService::class, 'jawabanNaratif');
        $baca->setAccessible(true);
        $baris = $baca->invoke(app(PaParticipantReadService::class), $l['participant_id']);

        $this->assertSame(['Ceritakan pekerjaan yang menumpuk.', 'Bagaimana bila rekan tidak kooperatif?'],
            array_column($baris, 'Pertanyaan'));
        $this->assertSame(['Saya urutkan menurut tenggat.', 'Saya ajak bicara empat mata.'],
            array_column($baris, 'Jawaban_Teks'));
    }

    /*
     | TEKS KAYA DARI HC LEARN (kontrak 28 Sep 2026).
     |
     | `Esai[].Pertanyaan` dan `Esai[].Jawaban_Peserta` kini HTML apa adanya dari editor Quill,
     | tidak dibersihkan HC Learn. Disimpan utuh, dibersihkan saat dibaca: daftar dan
     | penebalan adalah bagian dari jawaban, sedangkan skrip dan atribut tidak boleh lolos.
     */
    private function lembarKaya(): array
    {
        return $this->lembarPerSoal([
            ['id' => 201, 'pertanyaan' => '<p>Ceritakan <strong>cara Anda</strong> menyelesaikan pekerjaan yang menumpuk.</p>',
                'jawaban' => '<ul><li>Saya urutkan menurut <strong>tenggat</strong>.</li><li onclick="x()">Yang mendesak lebih dulu.</li></ul><script>alert(1)</script>'],
            ['id' => 202, 'pertanyaan' => 'Apakah 2 < 3?', 'jawaban' => 'Ya, 2 < 3 & itu benar.'],
        ]);
    }

    public function test_jawaban_html_hclearn_tampil_sebagai_html_aman_bukan_tag_mentah(): void
    {
        $n = $this->naratif($this->lembarKaya());

        $this->assertSame('<ul><li>Saya urutkan menurut <strong>tenggat</strong>.</li><li>Yang mendesak lebih dulu.</li></ul>',
            $n[0]['jawaban_html'], 'Daftar dan penebalan harus tetap, skrip dan atribut dibuang.');
        $this->assertSame("Saya urutkan menurut tenggat.\nYang mendesak lebih dulu.", $n[0]['jawaban']);
        $this->assertSame("Saya urutkan menurut tenggat.\nYang mendesak lebih dulu.", $n[0]['jawaban_asli'],
            'Jawaban asli ditampilkan sebagai teks; tag mentahnya tidak boleh terbaca orang.');
        $this->assertSame('Ceritakan cara Anda menyelesaikan pekerjaan yang menumpuk.', $n[0]['pertanyaan']);
    }

    public function test_teks_polos_hclearn_tetap_di_escape(): void
    {
        $n = $this->naratif($this->lembarKaya());

        $this->assertSame('<p>Ya, 2 &lt; 3 &amp; itu benar.</p>', $n[1]['jawaban_html']);
        $this->assertSame('Ya, 2 < 3 & itu benar.', $n[1]['jawaban']);
        $this->assertSame('Apakah 2 < 3?', $n[1]['pertanyaan']);
    }

    public function test_kalibrasi_jawaban_html_mencatat_sebelum_sebagai_html_aman(): void
    {
        $l = $this->lembarKaya();

        $hasil = app(PaKalibrasiPenilaiService::class)->simpan($l['penilai'], $l['assignment_id'], [], 'Diperjelas', 7, [
            ['kode_soal' => self::KODE, 'id_master_soal' => 201, 'jawaban' => '<p>Saya susun prioritas harian.</p>'],
        ]);

        $baris = DB::table('N_PA_Kalibrasi_Penilai_Jawaban')->where('PA_Kalibrasi_Penilai_Id', $hasil['kalibrasi_id'])->first();
        $this->assertSame('<ul><li>Saya urutkan menurut <strong>tenggat</strong>.</li><li>Yang mendesak lebih dulu.</li></ul>', $baris->Jawaban_Sebelum);
        $this->assertSame('<p>Saya susun prioritas harian.</p>', $baris->Jawaban_Sesudah);
    }

    public function test_catatan_dan_detail_admin_tidak_membawa_tag_mentah(): void
    {
        $l = $this->lembarKaya();
        $this->ikatKePaket($l);

        $resultId = (int) DB::table('N_PA_Result')->insertGetId([
            'PA_Participant_Id' => $l['participant_id'], 'Status_Hasil' => 'DIHITUNG', 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
        ], 'Id_PA_Result');
        $simpan = new ReflectionMethod(PaResultCalculationService::class, 'simpanCatatanDariPenilai');
        $simpan->setAccessible(true);
        $simpan->invoke(app(PaResultCalculationService::class), $resultId, $l['participant_id'], null);

        $this->assertSame(
            '<p><strong>Ceritakan cara Anda menyelesaikan pekerjaan yang menumpuk.</strong></p>'
            .'<ul><li>Saya urutkan menurut <strong>tenggat</strong>.</li><li>Yang mendesak lebih dulu.</li></ul>',
            DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)->orderBy('Urutan_Tampil')->value('Isi'));

        $baca = new ReflectionMethod(PaParticipantReadService::class, 'jawabanNaratif');
        $baca->setAccessible(true);
        $baris = $baca->invoke(app(PaParticipantReadService::class), $l['participant_id']);
        $this->assertSame('Ceritakan cara Anda menyelesaikan pekerjaan yang menumpuk.', $baris[0]['Pertanyaan']);
        $this->assertSame("Saya urutkan menurut tenggat.\nYang mendesak lebih dulu.", $baris[0]['Jawaban_Teks']);
    }

    /** Catatan hasil hanya dibentuk dari butir yang terikat ke paket soal siklus. */
    private function ikatKePaket(array $l): void
    {
        $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
            'PA_Cycle_Id' => $l['cycle_id'],
            'ID_Level' => (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level'),
            'PA_Rater_Category_Id' => $l['kategori_id'],
            'Id_Master_Ujian' => '900902',
            'Nama_Ujian_Sumber' => 'Uji esai per soal',
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle_Question_Package');

        DB::table('N_PA_Cycle_Question_Package_Item')->insert([
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Id_Jenis_Indikator' => '900912',
            'Id_Jenis_Soal' => '900931',
            'Kode_Soal_Sumber' => self::KODE,
            'Nama_Alat_Tes' => 'Uji esai per soal',
            'Nama_Bagian_Sumber' => 'Esai',
            'PA_Competency_Id' => DB::table('N_PA_Assignment_Score')->where('PA_Assignment_Payload_Id', $l['payload_id'])
                ->where('Kode_Soal_Sumber', self::KODE)->value('PA_Competency_Id'),
            'Urutan_Tampil' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $l['assignment_id'])
            ->update(['PA_Cycle_Question_Package_Id' => $paketId]);
    }
}
