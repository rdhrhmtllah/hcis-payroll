<?php

namespace Tests\Feature\ESS\Dashboard;

use App\Services\ESS\Dashboard\Homepage\HomepagePersonalDashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bootstrap homepage hanya boleh menjalankan kueri kalender approved SEKALI.
 *
 * ── Apa yang dijaga, dan kenapa ──────────────────────────────────────────────
 *
 * buildPersonalBootstrap() dulu memanggil getApprovedTransactionsForCalendar()
 * DUA KALI dengan argumen persis sama: sekali lewat buildAttendanceBundle(),
 * sekali lagi lewat getAttendanceCompositionSummary(). Kueri itu adalah union
 * 9 view sumber (N_HRIS_VW_SRC_*) — yang termahal di seluruh aplikasi.
 *
 * Terekam di production 9 September 2026: keluarga kueri ini 198 eksekusi
 * dengan total 883.904 ms waktu DB dalam SEHARI, sementara
 * homepage/personal/bootstrap menyumbang 178 dari 487 catatan permintaan
 * lambat (p95 52,6 detik, maksimum 104,7 detik). Satu dari dua panggilan itu
 * murni pengulangan — hasilnya dijamin sama karena argumennya sama.
 *
 * ── Kenapa dihitung, bukan diukur waktunya ───────────────────────────────────
 *
 * Waktu berubah menurut mesin dan isi cache; jumlah kueri tidak. Yang dijaga
 * adalah sifatnya: satu permintaan, satu kali kueri.
 *
 * ── Kenapa SATU instance ─────────────────────────────────────────────────────
 *
 * Memonya hidup pada instance, bukan pada proses. Menyelesaikan service dua kali
 * lewat app() akan memberi dua instance dan penjaga ini jadi hijau palsu — ia
 * akan mengukur dua permintaan berbeda, bukan pengulangan di dalam satu
 * permintaan. Karena itu instansnya sengaja ditahan di satu variabel.
 */
class BootstrapKalenderSekaliKueriTest extends TestCase
{
    use DatabaseTransactions;

    /** Kueri union 9 view sumber dikenali dari dua penanda yang hanya ada di sana. */
    private function hitungKueriUnion(callable $aksi): int
    {
        $n = 0;
        DB::listen(function ($q) use (&$n) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (str_contains($s, 'bypass admin') && str_contains($s, 'source_row_key')) {
                $n++;
            }
        });

        $aksi();

        return $n;
    }

    /** @return array{0:string,1:string} */
    private function karyawanUji(): array
    {
        $row = DB::table('Karyawan')
            ->whereNotNull('Kode_Karyawan')
            ->whereNotNull('UserID_Absen')
            ->first(['Kode_Karyawan', 'UserID_Absen']);

        if ($row === null) {
            $this->markTestSkipped('Tak ada karyawan ber-UserID_Absen; jalur bootstrap tak bisa dicapai.');
        }

        return [trim((string) $row->Kode_Karyawan), trim((string) $row->UserID_Absen)];
    }

    public function test_bootstrap_menjalankan_kueri_kalender_sekali_saja(): void
    {
        [$kode, $absen] = $this->karyawanUji();

        // SATU instance — lihat catatan kelas.
        $svc = app(HomepagePersonalDashboardService::class);

        $n = $this->hitungKueriUnion(fn () => $svc->buildPersonalBootstrap($kode, $absen, 0));

        $this->assertSame(
            1,
            $n,
            "Bootstrap menjalankan kueri union 9 view sumber {$n} kali, seharusnya 1. ".
            'Kueri ini yang paling mahal di aplikasi (883.904 ms waktu DB sehari di '.
            'production); setiap pengulangan dikalikan seluruh pengguna di setiap muat halaman.',
        );
    }

    public function test_lupakan_memo_benar_benar_membuang(): void
    {
        [$kode, $absen] = $this->karyawanUji();

        $svc = app(HomepagePersonalDashboardService::class);

        // Proses berumur panjang (artisan/worker) menahan satu instance lintas
        // iterasi. Kalau lupakanMemoKalender() tidak bekerja, iterasi kedua
        // membaca jawaban milik iterasi pertama — basi tanpa galat.
        $n = $this->hitungKueriUnion(function () use ($svc, $kode, $absen) {
            $svc->buildPersonalBootstrap($kode, $absen, 0);
            $svc->lupakanMemoKalender();
            $svc->buildPersonalBootstrap($kode, $absen, 0);
        });

        $this->assertSame(
            2,
            $n,
            "Setelah lupakanMemoKalender(), kueri seharusnya dijalankan lagi (2 total), bukan {$n}. ".
            'Memo yang tak bisa dibuang membuat proses berumur panjang menyajikan data basi.',
        );
    }
}
