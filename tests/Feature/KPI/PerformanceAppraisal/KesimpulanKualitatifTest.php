<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use App\Services\KPI\PerformanceAppraisal\PaAdminDashboardService;
use App\Services\KPI\PerformanceAppraisal\PaKesimpulanKualitatifService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use App\Services\KPI\PerformanceAppraisal\PaResultNoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/** Tahap Kesimpulan Kualitatif: daftar peserta berjawaban esai, status kesimpulan, ruang kerja. */
class KesimpulanKualitatifTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private function layanan(): PaKesimpulanKualitatifService
    {
        return app(PaKesimpulanKualitatifService::class);
    }

    public function test_daftar_hanya_peserta_berjawaban_dan_status_mengikuti_catatan_admin(): void
    {
        $p = $this->pesertaDihitung(['naratif' => '<p>Selalu <b>teliti</b>.</p>']);
        // Peserta tanpa jawaban esai di siklus yang sama tidak masuk daftar.
        $this->pesertaDihitung(['cycle_id' => $p['cycle_id']]);

        $d = $this->layanan()->daftar($p['cycle_id'], ['dengan_opsi' => 1]);

        $this->assertSame(1, $d['total']);
        $this->assertSame(['total' => 1, 'sudah' => 0, 'belum' => 1], array_intersect_key($d['ringkas'], array_flip(['total', 'sudah', 'belum'])));
        $baris = $d['peserta'][0];
        $this->assertSame($p['participant_id'], $baris['participant_id']);
        $this->assertSame('BELUM', $baris['status']);
        $this->assertSame([1, 1, 1, 0], [$baris['jumlah_jawaban'], $baris['jumlah_penilai'], $baris['kategori_total'], $baris['kategori_tersimpul']]);
        $this->assertArrayHasKey('opsi', $d);
        $this->assertSame(0, $this->layanan()->daftar($p['cycle_id'], ['status' => 'SUDAH'])['total']);

        app(PaResultNoteService::class)->simpanCatatanAdmin($p['participant_id'], ['QL01' => ['<p>Teliti.</p>']], null);

        $sudah = $this->layanan()->daftar($p['cycle_id'], ['status' => 'SUDAH']);
        $this->assertSame(1, $sudah['total']);
        $this->assertSame(['SUDAH', 1], [$sudah['peserta'][0]['status'], $sudah['peserta'][0]['kategori_tersimpul']]);
        $this->assertSame(0, $this->layanan()->daftar($p['cycle_id'], ['status' => 'BELUM'])['total']);
    }

    public function test_ruang_kerja_mengelompokkan_jawaban_per_kategori_dan_membawa_kesimpulan(): void
    {
        $p = $this->pesertaDihitung(['naratif' => '<p>Selalu <b>teliti</b>.</p><script>alert(1)</script>']);
        app(PaResultNoteService::class)->simpanCatatanAdmin($p['participant_id'], ['QL01' => ['<p>Teliti.</p>']], null);

        $w = $this->layanan()->ruangKerja($p['participant_id']);

        $this->assertFalse($w['terkunci']);
        $this->assertSame(['QL01'], array_column($w['kategori'], 'kode'));
        $j = $w['kategori'][0]['jawaban'][0];
        $this->assertSame('ATASAN', $j['kategori_penilai']);
        $this->assertStringContainsString('teliti', $j['html']);
        // Jawaban dibersihkan server sebelum dirender dengan v-html.
        $this->assertStringNotContainsString('<script', $j['html']);
        $this->assertSame('<p>Teliti.</p>', $w['kategori'][0]['catatan']);
    }

    public function test_hasil_terbit_terkunci_di_ruang_kerja(): void
    {
        $p = $this->pesertaDihitung(['naratif' => 'Rapi.', 'status_hasil' => 'TERBIT']);

        $this->assertTrue($this->layanan()->ruangKerja($p['participant_id'])['terkunci']);
        $this->assertTrue($this->layanan()->daftar($p['cycle_id'])['peserta'][0]['terkunci']);

        // TERBIT tanpa kesimpulan tidak bisa ditulis lagi: bukan "belum", supaya tahap bisa selesai.
        $r = $this->layanan()->ringkas($p['cycle_id']);
        $this->assertSame([1, 0, 0, 1], [$r['total'], $r['sudah'], $r['belum'], $r['terkunci']]);
        $this->assertSame(0, $this->layanan()->daftar($p['cycle_id'], ['status' => 'BELUM'])['total']);
        $this->assertSame('SELESAI', collect(app(\App\Services\KPI\PerformanceAppraisal\PaPenutupanSiklusService::class)
            ->alur($p['cycle_id'])['tahap'])->firstWhere('kode', 'kualitatif')['keadaan']);
    }

    public function test_api_daftar_dan_ruang_kerja(): void
    {
        $p = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        $api = '/api/v1/performance-appraisal';

        $this->actingAs($this->penggunaHasil())
            ->getJson("{$api}/cycles/{$p['cycle_id']}/kesimpulan-kualitatif?status=BELUM&halaman=1&per_halaman=10")
            ->assertOk()
            ->assertJsonPath('result.total', 1)
            ->assertJsonPath('result.peserta.0.status', 'BELUM');

        $this->actingAs($this->penggunaHasil())
            ->getJson("{$api}/participants/{$p['participant_id']}/kesimpulan-kualitatif")
            ->assertOk()
            ->assertJsonPath('result.kategori.0.kode', 'QL01');

        $this->actingAs($this->penggunaHasil())
            ->getJson("{$api}/cycles/{$p['cycle_id']}/kesimpulan-kualitatif?status=SALAH")
            ->assertStatus(422);
    }

    /** Dialog Buat Sesi Balancing memperingatkan peserta berjawaban yang belum disimpulkan saja. */
    public function test_kandidat_balancing_hanya_menandai_yang_berjawaban_tanpa_kesimpulan(): void
    {
        $p = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        $tanpaJawaban = $this->pesertaDihitung(['cycle_id' => $p['cycle_id']]);
        $kandidat = fn (int $pid) => collect(app(PaBalancingKandidatService::class)->daftar($p['cycle_id']))->firstWhere('participant_id', $pid);

        $this->assertTrue($kandidat($p['participant_id'])['perlu_kesimpulan']);
        // Tidak ada jawaban kualitatif = tidak ada yang perlu disimpulkan, jadi tidak diperingatkan.
        $this->assertFalse($kandidat($tanpaJawaban['participant_id'])['perlu_kesimpulan']);

        app(PaResultNoteService::class)->simpanCatatanAdmin($p['participant_id'], ['QL01' => ['Teliti.']], null);

        $this->assertFalse($kandidat($p['participant_id'])['perlu_kesimpulan']);
    }

    /* Koreksi tahap Rilis & Terbit (7 Okt 2026) */

    public function test_pengingat_sebelum_terbit_hanya_dihitung_berjawaban_tanpa_kesimpulan(): void
    {
        $a = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'naratif' => 'Teliti.']);
        $this->pesertaDihitung(['cycle_id' => $a['cycle_id']]); // tanpa jawaban kualitatif
        app(PaResultNoteService::class)->simpanCatatanAdmin($b['participant_id'], ['QL01' => ['Teliti.']], null);

        $semua = $this->layanan()->belumSebelumTerbit($a['cycle_id']);
        $this->assertSame(1, $semua['belum']);

        $this->assertSame(0, $this->layanan()->belumSebelumTerbit($a['cycle_id'], [$b['participant_id']])['belum']);
        $this->assertSame(1, $this->layanan()->belumSebelumTerbit($a['cycle_id'], [$a['participant_id']])['belum']);
    }

    public function test_api_penerbitan_membawa_syarat_dan_pengingat(): void
    {
        $p = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        $api = '/api/v1/performance-appraisal';

        $r = $this->actingAs($this->penggunaHasil())->getJson("{$api}/cycles/{$p['cycle_id']}/penerbitan")->assertOk();
        $this->assertIsBool($r->json('result.syarat.boleh_terbit'));
        $this->assertIsBool($r->json('result.syarat.boleh_rilis'));
        $this->assertSame(1, $r->json('result.kualitatif_belum.belum'));

        $this->actingAs($this->penggunaHasil())
            ->postJson("{$api}/cycles/{$p['cycle_id']}/kesimpulan-kualitatif/periksa", ['participant_ids' => [$p['participant_id']]])
            ->assertOk()
            ->assertJsonPath('result.belum', 1);
    }

    public function test_syarat_publish_menolak_kurva_yang_belum_dikunci(): void
    {
        $p = $this->pesertaDihitung();
        \Illuminate\Support\Facades\DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Metode_Grade' => 'KURVA']);

        $s = app(\App\Services\KPI\PerformanceAppraisal\PaResultPublishService::class)->syaratPublish($p['cycle_id']);

        $this->assertFalse($s['boleh_terbit']);
        $this->assertFalse($s['boleh_rilis']);
        $this->assertStringContainsString('belum dikunci', $s['alasan']);
    }

    /* Koreksi tab Hitung & Verifikasi (8 Okt 2026) */

    /** Komentar penilai pada butir BERSKOR (mis. CV03) diberi teks pada baris skornya. */
    private function komentarButirBerskor(int $assignmentId, string $teks = 'Komentar butir berskor.'): void
    {
        $scoreId = DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as pl', 'pl.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'pl.PA_Assignment_Integration_Id')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 's.PA_Competency_Id')
            ->where('i.PA_Assignment_Id', $assignmentId)
            ->where('k.Flag_Berskor', 'Y')
            ->orderBy('s.Id_PA_Assignment_Score')
            ->value('s.Id_PA_Assignment_Score');
        $this->assertNotNull($scoreId, 'Lembar uji tidak punya butir berskor.');

        DB::table('N_PA_Assignment_Score')->where('Id_PA_Assignment_Score', $scoreId)->update(['Jawaban_Teks' => $teks]);
    }

    /**
     * Komentar pada butir berskor bukan kategori catatan. Dulu ia menjadi kotak catatan di
     * dialog hasil, lalu seluruh simpanan ditolak ("Kategori catatan 'CV03' tidak dikenal").
     */
    public function test_komentar_butir_berskor_bukan_kategori_catatan_dan_simpanan_lolos(): void
    {
        $p = $this->pesertaDihitung(['naratif' => 'Teliti.']);
        $this->komentarButirBerskor($p['assignment_id']);

        $kategori = app(PaParticipantReadService::class)->detailHasil($p['participant_id'])['kategori_catatan'];

        $this->assertSame(['QL01'], array_column($kategori, 'Kode_Competency'));

        // Persis yang dikirim dialog: SEMUA kategori yang ditampilkan.
        $isi = array_fill_keys(array_column($kategori, 'Kode_Competency'), ['Teliti.']);
        $this->assertSame(1, app(PaResultNoteService::class)->simpanCatatanAdmin($p['participant_id'], $isi, null)['ditulis']);
    }

    /**
     * Antrean "Ulasan Qualitative Questions" dan tahap Kesimpulan Kualitatif memakai SATU
     * predikat: komentar butir berskor dan jawaban dari integrasi nonaktif tidak dihitung.
     */
    public function test_antrean_ulasan_kualitatif_sama_dengan_tahap_kesimpulan(): void
    {
        $a = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        // Hanya komentar pada butir berskor: tidak ada yang bisa disimpulkan.
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id']]);
        $this->komentarButirBerskor($b['assignment_id']);
        // Jawaban kualitatif dari integrasi yang sudah nonaktif: tidak berlaku lagi.
        $c = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'naratif' => 'Lama.']);
        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $c['assignment_id'])->update(['Flag_Aktif' => 'T']);

        $dasbor = app(PaAdminDashboardService::class);
        $isi = $dasbor->isiKeranjang($a['cycle_id'], 'needs_review_qualitative');

        $this->assertSame(1, $dasbor->keranjang($a['cycle_id'])['needs_review_qualitative']);
        $this->assertSame([$a['participant_id']], array_map('intval', array_column($isi['baris'], 'Id_PA_Participant')));
        $this->assertSame(1, $this->layanan()->ringkas($a['cycle_id'])['belum']);
        // Dua sisanya tidak hilang: mereka menunggu verifikasi biasa.
        $this->assertSame(2, $dasbor->keranjang($a['cycle_id'])['reviewing_calibration']);
    }

    /** Pencarian antrean di SERVER, atas seluruh isi keranjang — bukan baris halaman yang terbuka. */
    public function test_api_keranjang_mencari_di_server_lintas_halaman(): void
    {
        $a = $this->pesertaDihitung(['naratif' => 'Rapi.']);
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'naratif' => 'Teliti.']);
        $nikB = (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $b['participant_id'])->value('Kode_Karyawan');
        $api = "/api/v1/performance-appraisal/cycles/{$a['cycle_id']}/keranjang";

        $this->actingAs($this->penggunaHasil())
            ->getJson("{$api}?jenis=needs_review_qualitative&halaman=1&per_halaman=1")
            ->assertOk()
            ->assertJsonPath('result.total', 2);

        $r = $this->actingAs($this->penggunaHasil())
            ->getJson("{$api}?jenis=needs_review_qualitative&halaman=1&per_halaman=1&q=".urlencode($nikB))
            ->assertOk()
            ->assertJsonPath('result.total', 1);
        $this->assertSame($b['participant_id'], (int) $r->json('result.baris.0.Id_PA_Participant'));
    }
}
