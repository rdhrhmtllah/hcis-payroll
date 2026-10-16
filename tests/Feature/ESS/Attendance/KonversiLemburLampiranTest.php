<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Keberadaan lampiran riwayat konversi lembur diperiksa per DIREKTORI.
 *
 * `Storage::disk('gcs')->exists()` bukan SQL — ia permintaan metadata ke
 * penyimpanan objek, dan karena itu TAK TERLIHAT oleh alat mana pun yang
 * menghitung kueri. Rute `konversi-lembur/history` tercatat hanya 2 kueri,
 * sementara biayanya sebenarnya ada di sini: **diukur 100 ms per panggilan**,
 * satu panggilan per baris berlampiran.
 *
 * Lampiran disimpan di `konversi-lembur/YYYY/MM/`, jadi satu halaman riwayat
 * biasanya hanya menyentuh beberapa direktori — jauh lebih sedikit daripada
 * jumlah barisnya. Diukur pada riwayat nyata 24 baris / 7 lampiran:
 * **1.965 ms → 1.409 ms**, dengan jawaban yang identik.
 *
 * `temporaryUrl()` sengaja TIDAK diutak-atik: ia penandatanganan lokal, diukur
 * 2,7 ms, dan bukan panggilan jaringan.
 */
class KonversiLemburLampiranTest extends TestCase
{
    use DatabaseTransactions;

    private const BERKAS = 'app/Services/ESS/Attendance/Overtime/Conversion/KonversiLemburService.php';

    /**
     * Peta per direktori harus menjawab sama dengan `exists()` per berkas.
     *
     * Kalau tidak, lampiran yang ada bisa hilang dari layar — kegagalan yang
     * tak menghasilkan galat apa pun, hanya kolom kosong.
     */
    public function test_peta_direktori_sama_dengan_pemeriksaan_per_berkas(): void
    {
        $jalur = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
            ->whereNotNull('File_Path')
            ->where('File_Path', '<>', '')
            ->distinct()
            ->limit(6)
            ->pluck('File_Path')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        if (count($jalur) < 2) {
            $this->markTestSkipped('Lampiran di data dev kurang dari dua; tak bisa membedakan massal dari per-berkas.');
        }

        $m = new ReflectionMethod(KonversiLemburService::class, 'petaKeberadaanLampiran');
        $m->setAccessible(true);
        $peta = $m->invoke(app(KonversiLemburService::class), $jalur);

        $disk = Storage::disk('gcs');
        $adaYangBenarAda = 0;

        foreach ($jalur as $j) {
            $lama = $disk->exists($j);

            $this->assertSame(
                $lama,
                (bool) ($peta[$j] ?? false),
                "Keberadaan '{$j}' berbeda: exists() menjawab ".var_export($lama, true)
                .', peta direktori menjawab '.var_export((bool) ($peta[$j] ?? false), true).'.'
            );

            if ($lama) {
                $adaYangBenarAda++;
            }
        }

        /* Premis: kalau SEMUA berkas ternyata tak ada, kedua sisi sama-sama
           false dan perbandingan di atas tak membuktikan apa pun. */
        $this->assertGreaterThan(
            0,
            $adaYangBenarAda,
            'Tak satu pun lampiran benar-benar ada di penyimpanan, jadi perbandingan di atas hanya '
            .'mencocokkan dua kali "tidak ada". Pilih data dev yang lampirannya utuh.'
        );
    }

    /**
     * Sifat yang menjaga perbaikannya: pemeriksaan per berkas tidak boleh
     * kembali masuk ke dalam pemetaan baris.
     *
     * Uji nilai di atas tetap hijau meski `exists()` dikembalikan ke dalam
     * `map()` — jawabannya kan memang sama. Yang berbeda hanya berapa kali
     * jaringan disentuh, dan itu hanya bisa dinyatakan dengan membaca bentuknya.
     */
    public function test_exists_tidak_dipanggil_di_dalam_pemetaan_baris(): void
    {
        $isi = (string) file_get_contents(base_path(self::BERKAS));
        $baris = explode("\n", $isi);

        $mulai = null;
        foreach ($baris as $i => $l) {
            if (str_contains($l, 'public function getHistoryForUser(')) {
                $mulai = $i;
                break;
            }
        }

        $this->assertNotNull($mulai, 'getHistoryForUser() tidak ditemukan — penjaga ini kehilangan sasarannya.');

        $kedalaman = 0;
        $akhir = null;
        for ($j = $mulai; $j < count($baris); $j++) {
            $kedalaman += substr_count($baris[$j], '{') - substr_count($baris[$j], '}');
            if ($j > $mulai && $kedalaman <= 0) {
                $akhir = $j;
                break;
            }
        }

        /* Penyaring komentar harus sadar BLOK, bukan cuma awalan baris.
           Versi pertama penjaga ini menuduh sebuah baris di dalam komentar
           `/* ... *\/` yang saya tulis sendiri — barisnya diawali backtick,
           bukan bintang, jadi lolos dari penyaring sederhana. */
        $pelanggaran = [];
        $dalamKomentarBlok = false;

        for ($j = $mulai; $j <= ($akhir ?? $mulai); $j++) {
            $l = $baris[$j];
            $bersih = ltrim($l);

            if ($dalamKomentarBlok) {
                if (str_contains($l, '*/')) {
                    $dalamKomentarBlok = false;
                }

                continue;
            }

            if (str_starts_with($bersih, '/*') && ! str_contains($l, '*/')) {
                $dalamKomentarBlok = true;

                continue;
            }

            if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*') || str_starts_with($bersih, '/*')) {
                continue;
            }

            if (str_contains($l, "Storage::disk('gcs')->exists(")) {
                $pelanggaran[] = ($j + 1).': '.trim($l);
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "exists() dipanggil di dalam getHistoryForUser():\n  ".implode("\n  ", $pelanggaran)
            ."\n\nSatu panggilan per baris berarti 100 ms per baris, dan penghitung kueri tak akan "
            .'pernah menunjukkannya. Pakai petaKeberadaanLampiran().'
        );
    }
}
