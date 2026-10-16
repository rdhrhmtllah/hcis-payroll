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
 * Kalibrasi Penilai atas jawaban ESAI (DDL-2026-119 r2).
 *
 * Keputusan pemilik proyek 18 September 2026: esai boleh direvisi tetapi tidak boleh
 * dikosongkan, ikut penanda dan alasan yang sama dengan skor, dan versi terbaru dipakai di
 * mana-mana — catatan hasil, detail admin, pratinjau — sementara teks asli HC Learn tetap utuh
 * dan terbaca di riwayat.
 */
class KalibrasiJawabanEsaiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private const KODE_ESAI = 'UJI-TEKS';

    private function layanan(): PaKalibrasiPenilaiService
    {
        return app(PaKalibrasiPenilaiService::class);
    }

    private function lembarBeresai(string $asli = 'Jawaban asli dari HC Learn.'): array
    {
        return $this->lembarTerisi(['skor' => [70], 'naratif' => $asli, 'boleh_kalibrasi' => 'Y']);
    }

    private function revisiEsai(array $l, string $teks, string $alasan = 'Diperjelas'): array
    {
        return $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [], $alasan, 7,
            [['kode_soal' => self::KODE_ESAI, 'jawaban' => $teks]]);
    }

    private function kompetensiEsai(array $l): int
    {
        return (int) DB::table('N_PA_Assignment_Score')->where('PA_Assignment_Payload_Id', $l['payload_id'])
            ->where('Kode_Soal_Sumber', self::KODE_ESAI)->value('PA_Competency_Id');
    }

    private function kodeGalat(callable $kerja): ?string
    {
        try {
            $kerja();

            return null;
        } catch (PaKalibrasiPenilaiException $e) {
            return $e->errorCode;
        }
    }

    public function test_esai_direvisi_tanpa_menyentuh_teks_asli(): void
    {
        $l = $this->lembarBeresai();

        $hasil = $this->revisiEsai($l, '  Jawaban yang sudah diperjelas.  ');

        $this->assertSame(0, $hasil['diubah']);
        $this->assertSame(1, $hasil['diubah_jawaban']);

        $baris = DB::table('N_PA_Kalibrasi_Penilai_Jawaban')->where('PA_Kalibrasi_Penilai_Id', $hasil['kalibrasi_id'])->first();
        $this->assertSame(self::KODE_ESAI, $baris->Kode_Soal_Sumber);
        // Keduanya HTML aman: teks polos (HC Learn maupun masukan tanpa tag) menjadi paragraf.
        $this->assertSame('<p>Jawaban asli dari HC Learn.</p>', $baris->Jawaban_Sebelum);
        $this->assertSame('<p>Jawaban yang sudah diperjelas.</p>', $baris->Jawaban_Sesudah, 'Spasi di tepi harus dipangkas.');

        $this->assertSame('Jawaban asli dari HC Learn.', DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])->where('Kode_Soal_Sumber', self::KODE_ESAI)
            ->value('Jawaban_Teks'), 'Teks asli HC Learn tidak boleh ditimpa.');

        $naratif = app(PaSkorEfektif::class)->jawabanNaratif([$l['assignment_id']])[$l['assignment_id']][0];
        $this->assertSame('Jawaban yang sudah diperjelas.', $naratif['jawaban']);
        $this->assertSame('<p>Jawaban yang sudah diperjelas.</p>', $naratif['jawaban_html']);
        $this->assertSame('Jawaban asli dari HC Learn.', $naratif['jawaban_asli']);
        $this->assertTrue($naratif['terkalibrasi']);
    }

    public function test_rantai_revisi_esai_membawa_teks_sebelum_dari_revisi_sebelumnya(): void
    {
        $l = $this->lembarBeresai();

        $this->revisiEsai($l, 'Revisi pertama.');
        $kedua = $this->revisiEsai($l, 'Revisi kedua.');

        $this->assertSame('<p>Revisi pertama.</p>', DB::table('N_PA_Kalibrasi_Penilai_Jawaban')
            ->where('PA_Kalibrasi_Penilai_Id', $kedua['kalibrasi_id'])->value('Jawaban_Sebelum'));
        $this->assertSame('Revisi kedua.', app(PaSkorEfektif::class)
            ->jawabanNaratif([$l['assignment_id']])[$l['assignment_id']][0]['jawaban']);
    }

    public function test_penolakan_esai_membawa_kode_stabil_dan_tidak_menyimpan_apa_pun(): void
    {
        $l = $this->lembarBeresai();
        $sebelum = DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count();

        $this->assertSame(PaKalibrasiPenilaiException::JAWABAN_KOSONG, $this->kodeGalat(fn () => $this->revisiEsai($l, "   \n ")));
        // Editor teks kaya mengirim paragraf kosong untuk kotak yang dikosongkan.
        $this->assertSame(PaKalibrasiPenilaiException::JAWABAN_KOSONG, $this->kodeGalat(fn () => $this->revisiEsai($l, '<p><br></p>')));
        $this->assertSame(PaKalibrasiPenilaiException::JAWABAN_KOSONG, $this->kodeGalat(fn () => $this->revisiEsai($l, '<script>alert(1)</script>')));
        $this->assertSame(PaKalibrasiPenilaiException::JAWABAN_TERLALU_PANJANG,
            $this->kodeGalat(fn () => $this->revisiEsai($l, str_repeat('a', PaKalibrasiPenilaiService::PANJANG_JAWABAN + 1))));
        $this->assertSame(PaKalibrasiPenilaiException::TIDAK_ADA_PERUBAHAN,
            $this->kodeGalat(fn () => $this->revisiEsai($l, ' Jawaban asli dari HC Learn. ')));
        $this->assertSame(PaKalibrasiPenilaiException::SOAL_TIDAK_DI_LEMBAR, $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [], 'x', null,
                [['kode_soal' => 'TIDAK-ADA', 'jawaban' => 'Apa pun']])));

        $this->assertSame($sebelum, DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count());
    }

    public function test_kategori_tertutup_juga_menutup_esai(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'naratif' => 'Asli', 'boleh_kalibrasi' => 'T']);

        $this->assertSame(PaKalibrasiPenilaiException::KALIBRASI_DITUTUP, $this->kodeGalat(fn () => $this->revisiEsai($l, 'Baru')));
    }

    public function test_skor_dan_esai_satu_tindakan_satu_alasan(): void
    {
        $l = $this->lembarBeresai();

        $hasil = $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
            [['competency_id' => $l['kompetensi'][0], 'skor' => 60]], 'Keduanya diselaraskan', null,
            [['kode_soal' => self::KODE_ESAI, 'jawaban' => 'Teks baru']]);

        $this->assertSame(1, $hasil['diubah']);
        $this->assertSame(1, $hasil['diubah_jawaban']);
        $this->assertSame(1, DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count());
    }

    public function test_riwayat_memuat_tindakan_yang_hanya_mengubah_esai(): void
    {
        $l = $this->lembarBeresai();
        $this->revisiEsai($l, 'Teks baru', 'Hanya esai');

        $riwayat = $this->layanan()->riwayatPeserta($l['participant_id']);

        $this->assertCount(1, $riwayat, 'Tindakan tanpa rincian skor tidak boleh hilang dari riwayat.');
        $this->assertSame([], $riwayat[0]['perubahan']);
        $this->assertSame('Hanya esai', $riwayat[0]['alasan']);
        $kompetensiId = $this->kompetensiEsai($l);

        $this->assertSame([[
            'kode_soal' => self::KODE_ESAI,
            // Null: jawaban format lama, satu teks untuk seluruh bagian (DDL-2026-119 r3).
            'id_master_soal' => null,
            'competency_id' => $kompetensiId,
            'nama' => DB::table('N_PA_Competency')->where('Id_PA_Competency', $kompetensiId)->value('Nama_Competency'),
            'jawaban_sebelum' => '<p>Jawaban asli dari HC Learn.</p>',
            'jawaban_sesudah' => '<p>Teks baru</p>',
        ]], $riwayat[0]['perubahan_jawaban']);
    }

    public function test_catatan_hasil_memakai_jawaban_efektif(): void
    {
        $l = $this->lembarBeresai();

        // Catatan hasil hanya dibentuk dari butir yang terikat ke paket soal siklus.
        $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
            'PA_Cycle_Id' => $l['cycle_id'],
            'ID_Level' => (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level'),
            'PA_Rater_Category_Id' => $l['kategori_id'],
            'Id_Master_Ujian' => '900901',
            'Nama_Ujian_Sumber' => 'Uji esai',
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle_Question_Package');
        DB::table('N_PA_Cycle_Question_Package_Item')->insert([
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Id_Jenis_Indikator' => '900911',
            'Id_Jenis_Soal' => '900930',
            'Kode_Soal_Sumber' => self::KODE_ESAI,
            'Nama_Alat_Tes' => 'Uji esai',
            'Nama_Bagian_Sumber' => 'Esai',
            'PA_Competency_Id' => $this->kompetensiEsai($l),
            'Urutan_Tampil' => 1,
            'Flag_Aktif' => 'Y',
        ]);
        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $l['assignment_id'])
            ->update(['PA_Cycle_Question_Package_Id' => $paketId]);

        $resultId = (int) DB::table('N_PA_Result')->insertGetId([
            'PA_Participant_Id' => $l['participant_id'],
            'Status_Hasil' => 'DIHITUNG',
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Result');

        $this->revisiEsai($l, 'Catatan yang sudah direvisi penilai.');

        $simpan = new ReflectionMethod(PaResultCalculationService::class, 'simpanCatatanDariPenilai');
        $simpan->setAccessible(true);
        $simpan->invoke(app(PaResultCalculationService::class), $resultId, $l['participant_id'], null);

        $this->assertSame(['<p>Catatan yang sudah direvisi penilai.</p>'], DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $resultId)->where('Flag_Aktif', 'Y')->pluck('Isi')->all());
    }

    public function test_detail_admin_menampilkan_jawaban_efektif_dengan_bentuk_tetap(): void
    {
        $l = $this->lembarBeresai();
        $this->revisiEsai($l, 'Teks revisi untuk admin.');

        $baca = new ReflectionMethod(PaParticipantReadService::class, 'jawabanNaratif');
        $baca->setAccessible(true);
        $baris = $baca->invoke(app(PaParticipantReadService::class), $l['participant_id']);

        $this->assertCount(1, $baris);
        $this->assertSame('Teks revisi untuk admin.', $baris[0]['Jawaban_Teks']);
        /* Teks asli tetap tidak ikut: ia terbaca di riwayat kalibrasi. Satu-satunya kunci baru
           adalah `Pertanyaan` (26 Sep 2026), null pada jawaban format lama seperti ini. */
        $this->assertSame(
            ['Kode_Kategori', 'Nama_Kategori', 'Nama_Penilai_Saat_Cycle', 'Kode_Soal_Sumber', 'Nama_Competency', 'Pertanyaan', 'Jawaban_Teks'],
            array_keys($baris[0]),
        );
        $this->assertNull($baris[0]['Pertanyaan']);
    }

    public function test_html_berbahaya_disaring_sebelum_disimpan(): void
    {
        $l = $this->lembarBeresai();
        $this->actingAs($this->penilaiUji());

        $this->postJson('/api/v1/performance-appraisal/saya/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan_jawaban' => [[
                'kode_soal' => self::KODE_ESAI,
                'jawaban' => '<p onclick="curi()">Baik <strong>sekali</strong><img src=x onerror=alert(1)><script>alert(2)</script></p>',
            ]],
            'alasan' => 'Diperjelas',
        ])->assertOk();

        $this->assertSame('<p>Baik <strong>sekali</strong></p>', DB::table('N_PA_Kalibrasi_Penilai_Jawaban as j')
            ->join('N_PA_Kalibrasi_Penilai as k', 'k.Id_PA_Kalibrasi_Penilai', '=', 'j.PA_Kalibrasi_Penilai_Id')
            ->where('k.PA_Assignment_Id', $l['assignment_id'])->value('j.Jawaban_Sesudah'));
    }

    public function test_teks_polos_hclearn_tidak_pernah_terbaca_sebagai_markup(): void
    {
        $l = $this->lembarBeresai('Skornya <b>tinggi</b> & konsisten');

        $naratif = app(PaSkorEfektif::class)->jawabanNaratif([$l['assignment_id']])[$l['assignment_id']][0];

        $this->assertSame('Skornya <b>tinggi</b> & konsisten', $naratif['jawaban']);
        $this->assertSame('<p>Skornya &lt;b&gt;tinggi&lt;/b&gt; &amp; konsisten</p>', $naratif['jawaban_html']);
    }

    public function test_http_hanya_esai_dan_esai_kosong_ditolak_berkode(): void
    {
        $l = $this->lembarBeresai();
        $this->actingAs($this->penilaiUji());
        $api = '/api/v1/performance-appraisal/saya/kalibrasi';

        $r = $this->postJson($api, [
            'assignment_id' => $l['assignment_id'],
            'perubahan_jawaban' => [['kode_soal' => self::KODE_ESAI, 'jawaban' => 'Lewat HTTP']],
            'alasan' => 'Diperjelas',
        ])->assertOk();

        $naratif = $r->json('result.pratinjau.orang.0.naratif.0');
        $this->assertSame(self::KODE_ESAI, $naratif['kode_soal']);
        $this->assertSame('Lewat HTTP', $naratif['jawaban']);
        $this->assertSame('<p>Lewat HTTP</p>', $naratif['jawaban_html']);
        $this->assertSame('Jawaban asli dari HC Learn.', $naratif['jawaban_asli']);
        $this->assertTrue($naratif['terkalibrasi']);
        $this->assertSame(1, $r->json('result.diubah_jawaban'));

        $this->postJson($api, [
            'assignment_id' => $l['assignment_id'],
            'perubahan_jawaban' => [['kode_soal' => self::KODE_ESAI, 'jawaban' => '']],
            'alasan' => 'Dikosongkan',
        ])->assertStatus(422)->assertJsonPath('error_code', PaKalibrasiPenilaiException::JAWABAN_KOSONG);

        // Tanpa skor maupun esai: ditolak validasi, bukan diam-diam lolos.
        $this->postJson($api, ['assignment_id' => $l['assignment_id'], 'alasan' => 'x'])->assertStatus(422);
    }
}
