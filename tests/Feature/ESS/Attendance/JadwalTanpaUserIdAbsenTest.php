<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Karyawan tanpa UserID_Absen tidak boleh menjatuhkan halaman.
 *
 * ── Kejadiannya di production ────────────────────────────────────────────────
 *
 * Log production:
 *
 *   getPersonalUpcomingSchedule error: HrisAttendanceSnapshotService::
 *   getAttendance(): Argument #4 ($userIdList) must be of type string, null
 *   given          {"kode_karyawan": "LIUS"}
 *
 * getWeekSchedule() dan getUserShiftForDate() sudah menjaga "karyawannya tidak
 * ada", tetapi tidak menjaga "karyawannya ada tanpa UserID_Absen". Nilai null
 * itu lalu diteruskan ke parameter bertipe string dan melempar TypeError —
 * halaman menjawab 500, bukan "tidak ada jadwal".
 *
 * ── Kenapa ini bukan kasus pinggiran ─────────────────────────────────────────
 *
 * 900 dari 965 baris Karyawan di salinan development tidak punya UserID_Absen.
 * Siapa pun di antara mereka yang membuka homepage akan memicunya.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Kedua metode pulang dengan nilai "tak ada data"-nya sendiri ([] dan null),
 * sama seperti ketika karyawannya memang tidak ada — bukan melempar.
 */
class JadwalTanpaUserIdAbsenTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode;

    protected function setUp(): void
    {
        parent::setUp();

        $contoh = DB::table('Karyawan')->first();
        if (! $contoh) {
            $this->markTestSkipped('Tabel Karyawan kosong.');
        }

        // Karyawan NYATA tanpa UserID_Absen. Disemai sendiri supaya penjaga ini
        // tidak bergantung pada kebetulan isi data: kalau kelak semua karyawan
        // punya UserID_Absen, uji ini tetap menguji hal yang sama.
        $this->kode = 'ZZ'.Str::upper(Str::random(6));

        DB::table('Karyawan')->insert([
            'Kode_Karyawan' => $this->kode,
            'Kode_Perusahaan' => trim((string) ($contoh->Kode_Perusahaan ?? '001')),
            'Nama' => 'Uji Tanpa Absen',
            'UserID_Absen' => null,
            'Aktif' => 'Y',
        ]);
    }

    public function test_jadwal_mingguan_pulang_kosong_bukan_melempar(): void
    {
        $hasil = app(ShiftRequestService::class)
            ->getWeekSchedule($this->kode, Carbon::now('Asia/Jakarta')->startOfWeek());

        $this->assertSame(
            [],
            $hasil,
            'getWeekSchedule() harus memulangkan jadwal kosong untuk karyawan tanpa '.
            'UserID_Absen, bukan melempar TypeError yang menjatuhkan homepage.',
        );
    }

    public function test_shift_satu_tanggal_pulang_null_bukan_melempar(): void
    {
        $hasil = app(ShiftRequestService::class)
            ->getUserShiftForDate($this->kode, Carbon::now('Asia/Jakarta'));

        $this->assertNull(
            $hasil,
            'getUserShiftForDate() harus memulangkan null untuk karyawan tanpa UserID_Absen.',
        );
    }
}
