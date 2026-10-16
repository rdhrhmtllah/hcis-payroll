<?php

namespace Tests\Feature\LMS;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\TrainingProgressReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Katalog training tidak boleh berbiaya per baris.
 *
 * `index()` memanggil `computeProgress()` dan `computeStatus()` di dalam
 * `map()`, dan masing-masing berbiaya 4–6 kueri. Sembilan baris menghasilkan
 * **61 kueri**, 58 di antaranya per-baris — biayanya naik lurus mengikuti
 * jumlah training yang diikuti seseorang.
 *
 * Sesudah keduanya dilayani `ringkasanMassal()`: **20 kueri**.
 *
 * YANG DIJAGA BENTUKNYA, BUKAN ANGKANYA.
 * Ambang mati akan basi begitu data dev berubah. Yang dituntut di sini: kueri
 * ringkasan progres — yang dulu berjalan sekali per baris — kini boleh berjalan
 * **paling banyak sekali** untuk setiap bentuknya, berapa pun barisnya.
 */
class KatalogTrainingKueriTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        /* Penggunanya dipilih yang BENAR-BENAR punya penjadwalan training.
           Karyawan pertama begitu saja menghasilkan katalog kosong, dan uji
           yang melewati dirinya sendiri tidak menguji apa pun. */
        $pemilik = DB::table('N_LMS_Penjadwalan')
            ->whereNotNull('Kode_Karyawan')
            ->groupBy('Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->selectRaw('Kode_Karyawan')
            ->first();

        $aktor = $pemilik
            ? User::query()
                ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
                ->select('KPI_Users.Id_Users')
                ->whereRaw('UPPER(LTRIM(RTRIM(k.Kode_Karyawan))) = ?', [strtoupper(trim((string) $pemilik->Kode_Karyawan))])
                ->first()
            : null;

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user dengan penjadwalan training di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login(User::find($aktor->Id_Users));
    }

    public function test_ringkasan_progres_tidak_diambil_per_baris(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson('/lms/training/catalog');
        $res->assertOk();

        $baris = count($res->json('data') ?? $res->json('result') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} baris katalog; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        /* Ciri kueri yang DULU berjalan per baris. Masing-masing kini harus
           berjalan paling banyak sekali untuk seluruh halaman. */
        $ciri = [
            'transaksi training' => 'N_LMS_Transaksi_Training',
            'progres training' => 'N_LMS_Training_Progress',
            'checkinout' => 'N_LMS_CHECKINOUT',
        ];

        foreach ($ciri as $label => $tabel) {
            $n = 0;
            foreach ($kueri as $sql) {
                if (stripos($sql, $tabel) !== false) {
                    $n++;
                }
            }

            $this->assertLessThanOrEqual(
                2,
                $n,
                "Kueri {$label} ({$tabel}) berjalan {$n}x untuk {$baris} baris katalog. "
                .'Ia harus diambil massal lewat ringkasanMassal(), bukan per baris. '
                .'Batas 2 memberi ruang untuk bentuk kueri yang memang berbeda '
                .'(mis. hitungan SELESAI dan penanda PROGRESS), bukan untuk pengulangan per baris.'
            );
        }
    }

    /**
     * Sisi kedua: memangkas kueri tak berguna kalau angkanya ikut berubah.
     *
     * Kalau pemetaan kunci Id_Penjadwalan meleset, progress semua baris jatuh
     * ke 0 dan statusnya jadi 'not-started' — dan jumlah kuerinya justru
     * terlihat makin bagus.
     */
    public function test_katalog_tetap_membawa_progres_dan_status(): void
    {
        $res = $this->getJson('/lms/training/catalog');
        $res->assertOk();

        $rows = $res->json('data') ?? $res->json('result') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Katalog kosong di data dev.');
        }

        $bergerak = 0;
        foreach ($rows as $baris) {
            $this->assertArrayHasKey('progress', $baris);
            $this->assertArrayHasKey('status', $baris);
            $this->assertIsInt($baris['progress']);
            $this->assertGreaterThanOrEqual(0, $baris['progress']);
            $this->assertLessThanOrEqual(100, $baris['progress']);

            if ($baris['progress'] > 0 || ($baris['status'] ?? 'not-started') !== 'not-started') {
                $bergerak++;
            }
        }

        /* VERSI PERTAMA UJI INI TIDAK MENGGIGIT: ia hanya menuntut progress
           berupa int 0–100, dan nol pun memenuhinya. Pemetaan kunci yang
           sengaja dimeleskan tetap lolos, padahal SELURUH baris jatuh ke nol.

           Premisnya diperiksa lebih dulu supaya tuntutan di bawah adil: di data
           dev, 2.206 dari 2.207 penjadwalan berstatus "sudah mulai". */
        $adaYangMulai = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where(function ($q) {
                $q->where('pdet.Flag_Checkin', 'Y')
                    ->orWhere('pdet.Flag_Selesai', 'Y')
                    ->orWhereNotNull('pdet.Waktu_Mulai_Akses');
            })
            ->exists();

        if (! $adaYangMulai) {
            $this->markTestSkipped('Tak ada aktivitas training yang pernah dimulai di data dev.');
        }

        $this->assertGreaterThan(
            0,
            $bergerak,
            'Seluruh '.count($rows).' baris katalog berprogres 0 dan berstatus not-started, padahal '
            .'basis data punya aktivitas yang sudah dimulai. Pemetaan kunci Id_Penjadwalan '
            .'kemungkinan meleset, sehingga semua baris jatuh ke nilai baku — gejala yang '
            .'justru membuat jumlah kueri tampak membaik.'
        );
    }

    /**
     * Bentuk massal harus SEPAKAT dengan bentuk tunggal.
     *
     * Keduanya hidup berdampingan: `computeProgressSnapshot()` masih dipakai
     * jalur lain. Kalau salah satu berubah sendirian, dua layar akan
     * menampilkan progres berbeda untuk training yang sama, dan tidak ada error
     * yang memberitahu.
     *
     * BATAS YANG DIAKUI: satu cabang TIDAK terjaga di sini.
     * `min($total, max($dariPenjadwalan, $dariProgres))` punya sisi yang hanya
     * hidup ketika hitungan dari PROGRES melebihi hitungan dari PENJADWALAN.
     * Di data dev itu **tak pernah terjadi** — nol dari 86 penjadwalan
     * bertransaksi aktif. Mengubah `max(a, b)` jadi `a` saja karena itu tetap
     * lolos uji ini, dan sudah dibuktikan begitu lewat sabotase.
     *
     * Ditulis apa adanya, bukan ditutupi: menaikkan jumlah sampel tidak
     * menolong, karena yang kurang adalah BENTUK datanya, bukan banyaknya.
     * Bila suatu hari sisi itu perlu dijaga, jalannya adalah fikstur yang
     * sengaja dibuat — bukan berharap data dev kebetulan menyentuhnya.
     */
    public function test_bentuk_massal_sepakat_dengan_bentuk_tunggal(): void
    {
        /* Sampelnya SENGAJA dipilih yang punya baris N_LMS_Training_Progress.
           Versi pertama mengambil lima penjadwalan terbaru begitu saja, dan
           uji ini TETAP HIJAU ketika `max($dariPenjadwalan, $dariProgres)`
           sengaja diubah jadi `$dariPenjadwalan` saja — kelima sampelnya
           kebetulan tak membedakan kedua sumber, jadi cabang yang disabotase
           tak pernah tersentuh. */
        $ids = DB::table('N_LMS_Transaksi_Training as t')
            ->join('N_LMS_Training_Progress as p', 'p.Id_Transaksi_Training', '=', 't.Id_Transaksi_Training')
            ->where('t.Flag_Aktif', 'Y')
            ->distinct()
            ->limit(8)
            ->pluck('t.Id_Penjadwalan')
            ->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('Terlalu sedikit penjadwalan ber-progres untuk dibandingkan.');
        }

        /** @var TrainingProgressReadService $svc */
        $svc = app(TrainingProgressReadService::class);
        $massal = $svc->ringkasanMassal($ids);

        foreach ($ids as $id) {
            $tunggal = $svc->computeProgressSnapshot((int) $id);
            $mulaiTunggal = $svc->hasStartedActivity((int) $id);
            $dariMassal = $massal[(int) $id] ?? null;

            $this->assertNotNull($dariMassal, "ringkasanMassal() tidak mengembalikan apa pun untuk penjadwalan {$id}.");

            foreach (['total_items', 'completed_items', 'progress_percent'] as $medan) {
                $this->assertSame(
                    $tunggal[$medan],
                    $dariMassal[$medan],
                    "Medan '{$medan}' berbeda untuk penjadwalan {$id}: tunggal={$tunggal[$medan]}, massal={$dariMassal[$medan]}."
                );
            }

            $this->assertSame(
                $mulaiTunggal,
                $dariMassal['has_started'],
                "has_started berbeda untuk penjadwalan {$id}."
            );
        }
    }
}
