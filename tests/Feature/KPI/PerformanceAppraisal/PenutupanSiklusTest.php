<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaPenutupanSiklusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/**
 * Alur penutupan siklus (spec 2026-10-02): keadaan tiap tahap diturunkan dari data, dan
 * penghambatnya memakai aturan yang sama dengan gerbang yang akan menolak aksinya.
 */
class PenutupanSiklusTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    public function test_siklus_open_hanya_pengisian_yang_berjalan(): void
    {
        $l = $this->lembarTerisi(['status_siklus' => 'OPEN', 'status_penugasan' => 'SELESAI']);

        $alur = $this->alur($l['cycle_id']);

        $this->assertSame('BERJALAN', $this->keadaan($alur, 'pengisian'));
        $this->assertSame('MENUNGGU', $this->keadaan($alur, 'verifikasi'));
        $this->assertSame('MENUNGGU', $this->keadaan($alur, 'balancing'));
        $this->assertSame('MENUNGGU', $this->keadaan($alur, 'terbit'));
        $this->assertSame('pengisian', $alur['tahap_disarankan']);
        $this->assertSame(1, $alur['cacah']['penugasan_selesai']);

        /* Gerbang TERBITKAN/RILIS_LEADER sengaja menerima OPEN; kalimatnya tidak boleh
           menyangkal pintu yang terbuka (koreksi 8 Okt 2026). */
        $terbit = implode(' ', $this->tahap($alur, 'terbit')['penghambat']);
        $this->assertStringNotContainsString('dibuka sesudah pengisian ditutup', $terbit);
        $this->assertStringContainsString('per orang', $terbit);
    }

    /**
     * LOCKED berarti penyiapan SUDAH selesai: kalimatnya menunjuk langkah berikutnya (buka
     * pengisian), bukan menyuruh kembali menyiapkan — dan menyebut siklus lain yang masih
     * OPEN, sebab itulah yang akan membuat server menolak pembukaannya.
     */
    public function test_siklus_locked_menunjuk_buka_pengisian_dan_siklus_lain_yang_terbuka(): void
    {
        $l = $this->lembarTerisi(['status_siklus' => 'LOCKED', 'status_penugasan' => 'BELUM_MULAI']);
        // Siklus OPEN yang akan disebut: yang sudah ada di database, atau dibuat bila belum ada.
        $penyiapan = app(\App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService::class);
        if ($penyiapan->siklusSedangTerbuka($l['cycle_id']) === null) {
            $this->siklusLembar('OPEN');
        }
        $kodeLain = $penyiapan->siklusSedangTerbuka($l['cycle_id'])['kode'];

        $pengisian = $this->tahap($this->alur($l['cycle_id']), 'pengisian');
        $hambat = implode(' ', $pengisian['penghambat']);

        $this->assertSame('MENUNGGU', $pengisian['keadaan']);
        $this->assertStringNotContainsString('Selesaikan penyiapan', $hambat);
        $this->assertStringContainsString('Buka Pengisian', $hambat);
        $this->assertStringContainsString($kodeLain, $hambat);
    }

    /** Siklus ditutup dengan penugasan kosong tetap SELESAI, tetapi tidak bisu: ada catatannya. */
    public function test_siklus_closed_dengan_penugasan_belum_selesai_membawa_catatan(): void
    {
        $l = $this->lembarTerisi(['status_siklus' => 'CLOSED', 'status_penugasan' => 'BELUM_MULAI']);

        $pengisian = $this->tahap($this->alur($l['cycle_id']), 'pengisian');

        $this->assertSame('SELESAI', $pengisian['keadaan']);
        $this->assertSame([], $pengisian['penghambat'], 'Catatan bukan penghambat.');
        $this->assertStringContainsString('Ditutup dengan 0 dari 1 penugasan selesai', (string) $pengisian['catatan']);

        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $l['assignment_id'])->update(['Status_Penugasan' => 'SELESAI']);
        $this->assertNull($this->tahap($this->alur($l['cycle_id']), 'pengisian')['catatan']);
    }

    public function test_kurva_terkunci_dengan_hasil_tertahan_tidak_tampil_selesai_bisu(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [50, 60, 70, 80]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$level][0])->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);
        app(PaKurvaService::class)->kunci($s['cycle_id'], null, 'Tertahan permanen, tanpa akun KPI');

        $kurva = $this->tahap($this->alur($s['cycle_id']), 'kurva');

        $this->assertSame('BERJALAN', $kurva['keadaan']);
        $this->assertStringContainsString('1 tertahan tidak ikut membentuk kurva yang terkunci', implode(' ', $kurva['penghambat']));

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $s['peserta'][$level][0])->update(['Status_Peserta' => 'DIKECUALIKAN']);
        $this->assertSame('SELESAI', $this->keadaan($this->alur($s['cycle_id']), 'kurva'));
    }

    public function test_hasil_tertahan_menahan_verifikasi_dan_disebut_sebagai_penghambat(): void
    {
        $a = $this->pesertaDihitung();
        $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'status_hasil' => 'TERTAHAN']);

        $alur = $this->alur($a['cycle_id']);
        $verifikasi = $this->tahap($alur, 'verifikasi');

        $this->assertSame('SELESAI', $this->keadaan($alur, 'pengisian'));
        $this->assertSame('BERJALAN', $verifikasi['keadaan']);
        $this->assertStringContainsString('1 hasil TERTAHAN', implode(' ', $verifikasi['penghambat']));
        $this->assertSame('verifikasi', $alur['tahap_disarankan']);
        $this->assertSame(1, $alur['cacah']['hasil_dihitung']);
        $this->assertSame(1, $alur['cacah']['hasil_tertahan']);
    }

    public function test_metode_pita_melewati_kurva_dan_kurva_terbuka_berjalan(): void
    {
        $p = $this->pesertaDihitung();
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Metode_Grade' => 'PITA']);
        $this->assertSame('DILEWATI', $this->keadaan($this->alur($p['cycle_id']), 'kurva'));

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Metode_Grade' => 'KURVA']);
        $alur = $this->alur($p['cycle_id']);

        $this->assertSame('BERJALAN', $this->keadaan($alur, 'kurva'));
        // Verifikasi tuntas (semua DIHITUNG) → tahap berikut yang disarankan adalah kurva.
        $this->assertSame('kurva', $alur['tahap_disarankan']);
        $this->assertContains('Kurva belum dikunci; grade belum resmi.', $this->tahap($alur, 'terbit')['penghambat']);
    }

    public function test_balancing_opsional_dilewati_tanpa_sesi_dan_berjalan_saat_sesi_terbuka(): void
    {
        $p = $this->pesertaDihitung();
        $this->assertSame('DILEWATI', $this->keadaan($this->alur($p['cycle_id']), 'balancing'));

        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');
        $alur = $this->alur($p['cycle_id']);
        $balancing = $this->tahap($alur, 'balancing');

        $this->assertSame('BERJALAN', $balancing['keadaan']);
        $this->assertStringContainsString('1 sesi masih terbuka', implode(' ', $balancing['penghambat']));
        $this->assertContains('Balancing belum tuntas (sesi terbuka atau belum dihitung ulang).', $this->tahap($alur, 'terbit')['penghambat']);
        $this->assertSame(1, $alur['cacah']['sesi_terbuka']);
    }

    public function test_sesi_final_belum_dihitung_ulang_tetap_berjalan_lalu_selesai_sesudah_hitung_ulang(): void
    {
        $p = $this->pesertaDihitung();
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'FINAL');

        $this->assertSame('BERJALAN', $this->keadaan($this->alur($p['cycle_id']), 'balancing'));

        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->update(['Hitung_Ulang_At' => now()]);
        $alur = $this->alur($p['cycle_id']);

        $this->assertSame('SELESAI', $this->keadaan($alur, 'balancing'));
        $this->assertSame([], $this->tahap($alur, 'balancing')['penghambat']);
    }

    public function test_published_menyelesaikan_tahap_terbit(): void
    {
        $p = $this->pesertaDihitung(['status_hasil' => 'TERBIT']);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Status_Cycle' => 'PUBLISHED', 'Published_At' => now()]);

        $alur = $this->alur($p['cycle_id']);

        $this->assertSame('SELESAI', $this->keadaan($alur, 'terbit'));
        $this->assertSame([], $this->tahap($alur, 'terbit')['penghambat']);
        $this->assertSame('Penerbitan ditutup (PUBLISHED)', $this->tahap($alur, 'terbit')['aktivitas']['aksi']);
    }

    public function test_api_mengembalikan_enam_tahap(): void
    {
        $p = $this->pesertaDihitung();

        $this->actingAs($this->penggunaHasil())
            ->getJson("/api/v1/performance-appraisal/cycles/{$p['cycle_id']}/penutupan")
            ->assertOk()
            ->assertJsonPath('result.tahap.0.kode', 'pengisian')
            ->assertJsonPath('result.tahap.2.kode', 'kualitatif')
            ->assertJsonPath('result.tahap.5.kode', 'terbit')
            ->assertJsonCount(6, 'result.tahap');
    }

    /** Kesimpulan kualitatif tidak pernah menghambat; dilewati bila tak ada jawaban esai. */
    public function test_tahap_kualitatif_dilewati_berjalan_lalu_selesai(): void
    {
        $tanpa = $this->pesertaDihitung();
        $this->assertSame('DILEWATI', $this->keadaan($this->alur($tanpa['cycle_id']), 'kualitatif'));

        $p = $this->pesertaDihitung(['naratif' => 'Sangat teliti.']);
        $alur = $this->alur($p['cycle_id']);
        $kualitatif = $this->tahap($alur, 'kualitatif');

        $this->assertSame('BERJALAN', $kualitatif['keadaan']);
        $this->assertSame([], $kualitatif['penghambat']);
        $this->assertStringContainsString('0 dari 1 peserta sudah disimpulkan', $kualitatif['ringkas']);
        $this->assertSame('kualitatif', $alur['tahap_disarankan']);

        app(\App\Services\KPI\PerformanceAppraisal\PaResultNoteService::class)
            ->simpanCatatanAdmin($p['participant_id'], ['QL01' => ['Teliti.']], null);

        $this->assertSame('SELESAI', $this->keadaan($this->alur($p['cycle_id']), 'kualitatif'));
    }

    /* ------------------------------------------------------------------ */

    private function alur(int $cycleId): array
    {
        return app(PaPenutupanSiklusService::class)->alur($cycleId);
    }

    private function tahap(array $alur, string $kode): array
    {
        return collect($alur['tahap'])->firstWhere('kode', $kode);
    }

    private function keadaan(array $alur, string $kode): string
    {
        return $this->tahap($alur, $kode)['keadaan'];
    }
}
