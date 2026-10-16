<?php

namespace Tests\Feature\LMS;

use App\Services\LMS\TrainingExecutionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nilai akhir training: dari mana angkanya diambil, dan apa yang tidak boleh
 * membatalkannya.
 *
 * ── Aturannya ───────────────────────────────────────────────────────────────
 *
 * Ditetapkan pemilik proses:
 *
 *   • Total_Score = skor POST TEST bila training punya post test.
 *   • Kalau hanya ada PRE TEST, Total_Score = skor pre test itu.
 *   • Kalau ada dua-duanya, tetap POST TEST — bukan Learning Gain.
 *   • Learning Gain punya kolomnya sendiri (Score_Learning_Gain).
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * Total_Score disandera rumus Learning Gain, padahal ia sama sekali tidak
 * memakai rumus. computeFinalScoreUpdates() memulangkan null — sehingga TIDAK
 * ADA kolom skor yang ditulis — pada empat keadaan berbeda:
 *
 *   A. Id_Master_Rumus penjadwalan NULL (283 penjadwalan di Web_HRIS 10 Sep 2026)
 *   B. ada token rumus yang tak punya aktivitas, mis. rumus
 *      ([PTT]-[PRT])/[PRT]*100 pada training yang hanya ber-pre-test
 *   C. komponen rumus ada tapi belum dinilai
 *   D. rumus tak bisa dihitung — pre test 0 membuat pembaginya nol
 *
 * Semua header itu tetap memakai nilai awal 0 yang ditulis saat baris dibuat,
 * jadi layar menampilkan "Nilai Akhir 0" dengan yakin. Sapuan pada 10 September
 * 2026 menemukan 60 header dalam keadaan itu padahal nilai tesnya > 0.
 *
 * ── Kenapa diuji tanpa database ─────────────────────────────────────────────
 *
 * Yang dijaga adalah KEPUTUSANNYA, dan keputusan itu murni fungsi dari baris
 * aktivitas. Membangun fixture tujuh tabel hanya akan menambah cara uji ini
 * gagal karena hal yang tidak sedang diuji. Satu uji tambahan di akhir menyentuh
 * data dev untuk membuktikan jalur nyatanya masih tersambung.
 */
class NilaiAkhirTrainingTest extends TestCase
{
    private const RUMUS_LEARNING_GAIN = '([PTT]-[PRT])/[PRT]*100';

    private function mesin(): TrainingExecutionServiceTerbuka
    {
        return new TrainingExecutionServiceTerbuka;
    }

    private function aktivitas(array $daftar): \Illuminate\Support\Collection
    {
        return collect($daftar)->map(fn ($a) => (object) [
            'Kode_Aktivitas' => $a[0],
            'Flag_Selesai' => $a[1],
            'Skor_Akhir' => $a[2],
        ]);
    }

    private function rumus(?string $ekspresi): ?object
    {
        return $ekspresi === null ? null : (object) ['Rumus' => $ekspresi];
    }

    public function test_hanya_pre_test_memakai_nilai_pre_test(): void
    {
        $nilai = $this->mesin()->resolveTotalScore(
            $this->aktivitas([['ABS', 'Y', 0.0], ['PRT', 'Y', 20.0]]),
            true,
        );

        $this->assertSame(20.0, $nilai, 'Training tanpa post test harus memakai nilai pre test-nya.');
    }

    public function test_hanya_post_test_memakai_nilai_post_test(): void
    {
        $nilai = $this->mesin()->resolveTotalScore(
            $this->aktivitas([['MDL', 'Y', null], ['PTT', 'Y', 75.0]]),
            false,
        );

        $this->assertSame(75.0, $nilai);
    }

    public function test_bila_ada_dua_duanya_yang_dipakai_post_test(): void
    {
        $nilai = $this->mesin()->resolveTotalScore(
            $this->aktivitas([['PRT', 'Y', 20.0], ['PTT', 'Y', 80.0]]),
            true,
        );

        // 80 = post test. 300 = learning gain-nya. 20 = pre test-nya.
        // Yang benar hanya satu, dan bukan learning gain.
        $this->assertSame(80.0, $nilai, 'Nilai akhir harus post test, bukan pre test dan bukan learning gain.');
    }

    public function test_post_test_ada_tetapi_belum_dinilai_tidak_jatuh_ke_pre_test(): void
    {
        $nilai = $this->mesin()->resolveTotalScore(
            $this->aktivitas([['PRT', 'Y', 90.0], ['PTT', 'T', null]]),
            false,
        );

        // Menampilkan 90 sekarang lalu menggantinya begitu post test dinilai
        // lebih menyesatkan daripada belum menampilkan apa pun.
        $this->assertNull($nilai);
    }

    public function test_rumus_tidak_ikut_menentukan_nilai_akhir(): void
    {
        $mesin = $this->mesin();
        $baris = $this->aktivitas([['PRT', 'Y', 20.0], ['PTT', 'Y', 20.0]]);

        foreach ([null, self::RUMUS_LEARNING_GAIN, '[PRT]', 'rumus-ngawur'] as $ekspresi) {
            $this->assertSame(
                20.0,
                $mesin->resolveTotalScore($baris, true),
                'Nilai akhir berubah mengikuti rumus — padahal rumus milik Learning Gain.',
            );
            // Dipanggil supaya kegagalan rumus benar-benar terjadi di jalur yang sama.
            $mesin->hitungLearningGain($this->rumus($ekspresi), $baris, true);
        }
    }

    public function test_learning_gain_gagal_tidak_menghapus_nilai_akhir(): void
    {
        $mesin = $this->mesin();

        $kasus = [
            'rumus tidak ada' => [null, [['PTT', 'Y', 60.0]]],
            'token tanpa aktivitas' => [self::RUMUS_LEARNING_GAIN, [['PTT', 'Y', 60.0]]],
            'pembagi nol' => [self::RUMUS_LEARNING_GAIN, [['PRT', 'Y', 0.0], ['PTT', 'Y', 60.0]]],
        ];

        foreach ($kasus as $label => [$ekspresi, $daftar]) {
            $baris = $this->aktivitas($daftar);

            $this->assertNull(
                $mesin->hitungLearningGain($this->rumus($ekspresi), $baris, true),
                "{$label}: learning gain seharusnya memang tidak bisa dihitung.",
            );
            $this->assertSame(
                60.0,
                $mesin->resolveTotalScore($baris, true),
                "{$label}: kegagalan learning gain ikut membuang nilai akhir yang sah.",
            );
        }
    }

    public function test_learning_gain_tetap_dihitung_saat_datanya_memang_cukup(): void
    {
        $nilai = $this->mesin()->hitungLearningGain(
            $this->rumus(self::RUMUS_LEARNING_GAIN),
            $this->aktivitas([['PRT', 'Y', 20.0], ['PTT', 'Y', 80.0]]),
            true,
        );

        $this->assertSame(300.0, $nilai, '(80-20)/20*100 = 300');
    }

    /**
     * Diuji lewat computeFinalScoreUpdates(), bukan lewat resolveTotalScore().
     *
     * Menguji keputusannya saja ternyata tidak cukup: sabotase yang menaruh
     * learning gain ke Total_Score di dalam computeFinalScoreUpdates() lolos
     * seluruh uji keputusan tanpa satu pun berubah merah. Aturan "yang dipakai
     * post test, BUKAN learning gain" harus dijaga di tempat kolomnya benar-benar
     * disusun.
     */
    public function test_kolom_total_score_diisi_post_test_dan_learning_gain_kolomnya_sendiri(): void
    {
        $mesin = new TrainingExecutionServicePalsu(
            $this->aktivitas([['PRT', 'Y', 20.0], ['PTT', 'Y', 80.0]]),
            $this->rumus(self::RUMUS_LEARNING_GAIN),
        );

        $updates = $mesin->computeFinalScoreUpdates(1, 1, true);

        $this->assertNotNull($updates);
        $this->assertSame(
            80.0,
            (float) $updates['Total_Score'],
            'Total_Score terisi angka selain nilai post test — learning gain (300) tidak boleh masuk ke sini.',
        );
        $this->assertSame(
            300.0,
            (float) $updates['Score_Learning_Gain'],
            'Learning gain harus tetap tersimpan, di kolomnya sendiri.',
        );
    }

    /**
     * Jalur nyata: penjadwalan yang punya nilai tes > 0 tetapi headernya masih 0
     * harus sekarang menghasilkan kolom Total_Score untuk ditulis.
     */
    public function test_penjadwalan_nyata_yang_dulu_nol_kini_menghasilkan_nilai(): void
    {
        $kandidat = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->join('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->join('N_LMS_Transaksi_Training as trx', function ($j) {
                $j->on('trx.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')->where('trx.Flag_Aktif', '=', 'Y');
            })
            ->join('N_LMS_Transaksi_Training_Detail as td', function ($j) {
                $j->on('td.Id_Transaksi_Training', '=', 'trx.Id_Transaksi_Training')
                    ->on('td.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                    ->on('td.Id_Materi', '=', 'det.Id_Materi')
                    ->on('td.Id_Materi_Det', '=', 'det.Id_Materi_Det');
            })
            ->join('N_LMS_Transaksi_Training_Det as tdd',
                'tdd.Id_Transaksi_Training_Detail', '=', 'td.Id_Transaksi_Training_Detail')
            ->whereIn('akt.Kode_Aktivitas', ['PRT', 'PTT'])
            ->where('pdet.Flag_Selesai', 'Y')
            ->where('tdd.Skor_Akhir', '>', 0)
            ->where(function ($q) {
                $q->whereNull('trx.Total_Score')->orWhere('trx.Total_Score', 0);
            })
            ->select('pd.Id_Penjadwalan', 'det.Id_Materi')
            ->first();

        if (! $kandidat) {
            $this->markTestSkipped('Tidak ada penjadwalan bernilai > 0 dengan header 0 di data ini.');
        }

        $updates = app(TrainingExecutionService::class)->computeFinalScoreUpdates(
            (int) $kandidat->Id_Penjadwalan,
            (int) $kandidat->Id_Materi,
            true,
        );

        $this->assertNotNull(
            $updates,
            "Penjadwalan {$kandidat->Id_Penjadwalan} punya nilai tes > 0 tetapi mesin tetap tidak menulis apa pun.",
        );
        $this->assertArrayHasKey('Total_Score', $updates);
        $this->assertGreaterThan(0, $updates['Total_Score']);
    }
}

/** Membuka keputusan skor untuk diuji langsung, tanpa fixture tujuh tabel. */
class TrainingExecutionServiceTerbuka extends TrainingExecutionService
{
    public function resolveTotalScore($activityRows, bool $requireCompleted): ?float
    {
        return parent::resolveTotalScore($activityRows, $requireCompleted);
    }

    public function hitungLearningGain(?object $formula, $activityRows, bool $requireCompleted): ?float
    {
        return parent::hitungLearningGain($formula, $activityRows, $requireCompleted);
    }
}

/**
 * Mesin dengan sumber data dipalsukan: baris aktivitas dan rumus disuntik, tetapi
 * seluruh penyusunan kolom tetap kode produksi yang asli.
 */
class TrainingExecutionServicePalsu extends TrainingExecutionService
{
    public function __construct(
        private $baris,
        private ?object $rumusUji,
    ) {}

    protected function barisAktivitas(int $penjadwalanId, int $materiId)
    {
        return $this->baris;
    }

    protected function ambilRumus(?int $idMasterRumus): ?object
    {
        return $this->rumusUji;
    }

    protected function ensureNeedsLearningGain(int $penjadwalanId): bool
    {
        return true;
    }
}
