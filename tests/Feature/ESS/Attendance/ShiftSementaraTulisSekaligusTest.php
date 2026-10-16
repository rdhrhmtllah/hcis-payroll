<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Penerapan shift SEMENTARA menulis sekali untuk seluruh tanggal.
 *
 * ── Skalanya, dari metrik production ─────────────────────────────────────────
 *
 * LeaderShiftController mencatat sendiri di log 9 September 2026:
 *
 *   submitMetrics {"method":"submit","duration_ms":113239.36,
 *                  "jenis":"SEMENTARA","member_count":45,"date_count":6}
 *
 * 45 x 6 = 270 pasangan, 3.289 kueri, 114 detik — dan sesi itu menjadi pemblokir
 * AKAR bagi delapan sesi lain (LCK_M_S, satu di antaranya 53.999 ms).
 *
 * Sebabnya: applyShiftDirect() mengerjakan SATU tanggal per panggilan, dan tiap
 * panggilan membuka transaksinya sendiri lalu menjalankan satu UPDATE
 * penonaktifan dan satu INSERT. Seluruh 270 urutan itu berjalan di dalam satu
 * transaksi tulis yang menahan lock.
 *
 * ── Kenapa aman disatukan ────────────────────────────────────────────────────
 *
 * Penonaktifan menyaring `Tanggal` yang tepat sama, jadi satu `whereIn`
 * memulangkan himpunan baris yang identik. Penyisipannya saling bebas, jadi satu
 * INSERT massal menghasilkan baris yang identik pula. Yang TIDAK disatukan:
 * jalur PERMANEN (semantiknya rentang `Tanggal >= x`) dan overlay hari-OFF.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Jumlah penulisan tidak tumbuh mengikuti jumlah tanggal, DAN hasil datanya
 * tetap benar: satu baris aktif per tanggal, tak ada yang terlewat.
 */
class ShiftSementaraTulisSekaligusTest extends TestCase
{
    use DatabaseTransactions;

    private const JUMLAH_TANGGAL = 6;

    public function test_tulis_tidak_tumbuh_per_tanggal_dan_datanya_utuh(): void
    {
        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan']);
        $idShift = DB::table('HRIS_Shift_Kerja')->value('ID_Shift');

        if (! $karyawan || ! $idShift) {
            $this->markTestSkipped('Butuh karyawan aktif dan master shift di data ini.');
        }

        $kode = trim((string) $karyawan->Kode_Karyawan);
        $no = 'UJI-SMT-'.Str::upper(Str::random(6));

        $dateItems = [];
        $statusHari = [];
        for ($i = 1; $i <= self::JUMLAH_TANGGAL; $i++) {
            // Jauh di depan supaya tak bertabrakan dengan data absensi nyata.
            $t = now()->addYears(6)->addDays($i)->format('Y-m-d');
            $dateItems[] = ['tanggal' => $t, 'id_shift' => (int) $idShift];
            $statusHari[$t] = 'KERJA'; // bukan OFF -> overlay tidak ikut terpicu
        }

        $tulis = 0;
        DB::listen(function ($q) use (&$tulis) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (! str_contains($s, 'hris_shift_sementara')) {
                return;
            }
            if (str_starts_with($s, 'update') || str_starts_with($s, 'insert')) {
                $tulis++;
            }
        });

        app(ShiftRequestService::class)->applyShiftDirectBatchForExistingTransaction(
            $no,
            $kode,
            $dateItems,
            'SEMENTARA',
            null,
            true,
            $statusHari,
        );

        $this->assertSame(
            2,
            $tulis,
            "Penerapan shift sementara memakai {$tulis} penulisan untuk ".self::JUMLAH_TANGGAL.
            ' tanggal, seharusnya 2 (satu UPDATE penonaktifan, satu INSERT massal). '.
            'Bentuk per-tanggal inilah yang membuat submit 45x6 memakan 114 detik '.
            'sambil menahan lock.',
        );

        // Hemat kueri tak ada gunanya kalau ada tanggal yang tak jadi tertulis.
        $tersimpan = DB::table('HRIS_Shift_Sementara')
            ->where('No_Transaksi_Reference', $no)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Tanggal')
            ->map(fn ($t) => substr((string) $t, 0, 10))
            ->sort()
            ->values()
            ->all();

        $harapan = array_map(fn ($d) => $d['tanggal'], $dateItems);
        sort($harapan);

        $this->assertSame($harapan, $tersimpan, 'Ada tanggal yang tidak tertulis.');
    }
}
