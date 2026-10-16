<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Penandaan absen-kotor: sekali per KARYAWAN, bukan sekali per tanggal.
 *
 * ── Kejadian yang menjadi asal penjaga ini ───────────────────────────────────
 *
 * Production 9 September 2026, 20:26-20:28. Rantai lengkapnya terkunci lewat
 * Context_Info yang sama antara N_HRIS_DB_Health_Snapshot dan
 * N_HRIS_Request_Diagnostics:
 *
 *   sesi 62 = leader-shift/submit — 3.289 kueri, 114 detik, program apache2,
 *             statement terakhirnya EXEC N_HRIS_SP_MARK_ABSEN_DIRTY
 *     -> sesi 71 menunggu 53.999 ms pada LCK_M_S
 *        -> tujuh sesi lain menunggu di belakang 71
 *           (satu 49.957 ms dengan CPU 12 ms — murni menunggu)
 *
 * applyShiftDirect() memanggil penandaan itu SEKALI PER TANGGAL, dan seluruh
 * perulangannya berada di dalam satu transaksi tulis. MarkAttendanceDirtyJob
 * dibuat persis untuk mencegah ini — docblock-nya menyebut LCK_M_S secara
 * harfiah — tetapi hanya dipasang di jalur approval, bukan di jalur penerapan
 * shift ini.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Berapa pun tanggal yang diterapkan untuk satu karyawan, EXEC dirty-mark
 * berjalan SEKALI, dan rentangnya menutupi tanggal paling awal sampai paling
 * akhir sehingga tak ada tanggal yang luput ditandai.
 */
class DirtyMarkSekaliPerKaryawanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dirty_mark_sekali_untuk_banyak_tanggal(): void
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'Kode_Perusahaan']);

        if (! $karyawan) {
            $this->markTestSkipped('Tidak ada karyawan aktif di data ini.');
        }

        $idShift = DB::table('HRIS_Shift_Kerja')->value('ID_Shift');
        if (! $idShift) {
            $this->markTestSkipped('Tidak ada master shift di data ini.');
        }

        $kode = trim((string) $karyawan->Kode_Karyawan);
        $no = 'UJI-DIRTY-'.Str::upper(Str::random(6));

        $tanggal = [];
        $dateItems = [];
        $statusHari = [];
        for ($i = 1; $i <= 5; $i++) {
            $t = now()->addYears(5)->addDays($i)->format('Y-m-d');
            $tanggal[] = $t;
            $dateItems[] = ['tanggal' => $t, 'id_shift' => (int) $idShift];
            $statusHari[$t] = 'KERJA';
        }

        $exec = [];
        DB::listen(function ($q) use (&$exec) {
            if (str_contains(strtoupper($q->sql), 'N_HRIS_SP_MARK_ABSEN_DIRTY')) {
                $exec[] = $q->bindings;
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

        $this->assertCount(
            1,
            $exec,
            'EXEC N_HRIS_SP_MARK_ABSEN_DIRTY berjalan '.count($exec).' kali untuk 5 tanggal, '.
            'seharusnya 1. Bentuk per-tanggal inilah yang menjadikan leader-shift/submit '.
            'pemblokir akar bagi delapan sesi di production.',
        );

        // Rentangnya harus menutupi seluruh tanggal — hemat kueri tak ada gunanya
        // kalau ada tanggal yang jadi tidak ditandai.
        $ikatan = array_map(fn ($b) => substr((string) $b, 0, 10), $exec[0]);

        $this->assertContains(min($tanggal), $ikatan, 'Tanggal paling awal tidak ikut ditandai.');
        $this->assertContains(max($tanggal), $ikatan, 'Tanggal paling akhir tidak ikut ditandai.');
    }
}
