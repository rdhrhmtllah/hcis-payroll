<?php

namespace Tests\Feature\SYSTEM;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemangkas tabel diagnostik.
 *
 * ── Yang dijaga, dan kenapa justru itu ───────────────────────────────────────
 *
 * Bukan "ada yang terhapus" — itu bagian yang mudah. Yang dijaga adalah
 * BATASNYA. Isi N_HRIS_Request_Diagnostics adalah satu-satunya catatan insiden
 * production yang kita punya: tiap baris di sana adalah permintaan yang benar-
 * benar lambat atau gagal, dan tak ada sumber lain yang bisa memulihkannya.
 * Predikat yang terbalik atau ambang yang salah tidak melempar galat apa pun —
 * ia hanya menghapus bukti yang sedang dicari orang.
 *
 * Karena itu tiap uji di bawah menegaskan DUA sisi sekaligus: yang lama hilang
 * DAN yang baru tetap ada. Menegaskan sisi pertama saja akan tetap hijau pada
 * command yang mengosongkan seluruh tabel.
 *
 * Kedua tabel diuji terpisah karena kolom waktunya berbeda nama (Created_At vs
 * Captured_At) — satu peta yang salah pasang hanya terlihat lewat tabel yang
 * salah itu.
 */
class DiagnostikPruneTest extends TestCase
{
    use DatabaseTransactions;

    /** Penanda unik supaya uji ini tak pernah menilai baris milik orang lain. */
    private string $tanda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tanda = 'uji-prune-'.bin2hex(random_bytes(6));
    }

    public function test_hanya_baris_lewat_masa_simpan_yang_dihapus(): void
    {
        DB::table('N_HRIS_Request_Diagnostics')->insert([
            $this->baris('lama', now()->subDays(120)),
            $this->baris('batas_aman', now()->subDays(89)),
            $this->baris('baru', now()->subHour()),
        ]);

        $this->artisan('hris:diagnostik-prune', ['--hari' => 90])->assertExitCode(0);

        $this->assertSame(
            ['baru', 'batas_aman'],
            $this->tersisa(),
            'Pemangkas menghapus baris di sisi yang salah dari ambang. Baris diagnostik '.
            'adalah satu-satunya catatan insiden production yang ada — tak ada yang bisa '.
            'memulihkannya setelah terhapus.',
        );
    }

    public function test_tabel_potret_kesehatan_dipangkas_lewat_kolomnya_sendiri(): void
    {
        // Captured_At, bukan Created_At. Peta kolom yang salah pasang membuat
        // command gagal diam-diam di satu tabel saja.
        DB::table('N_HRIS_DB_Health_Snapshot')->insert([
            ['Captured_At' => now()->subDays(120), 'Session_Id' => 991, 'Blocking_Session_Id' => 0],
            ['Captured_At' => now()->subHour(), 'Session_Id' => 992, 'Blocking_Session_Id' => 0],
        ]);

        $this->artisan('hris:diagnostik-prune', ['--hari' => 90])->assertExitCode(0);

        $sesi = array_map('intval', DB::table('N_HRIS_DB_Health_Snapshot')
            ->whereIn('Session_Id', [991, 992])
            ->pluck('Session_Id')
            ->all());

        $this->assertSame([992], $sesi, 'Potret kesehatan tidak dipangkas lewat Captured_At.');
    }

    public function test_mode_kering_tidak_menghapus_apa_pun(): void
    {
        DB::table('N_HRIS_Request_Diagnostics')->insert([$this->baris('lama', now()->subDays(120))]);

        $this->artisan('hris:diagnostik-prune', ['--hari' => 90, '--kering' => true])->assertExitCode(0);

        $this->assertSame(['lama'], $this->tersisa(), 'Mode kering tetap menghapus.');
    }

    public function test_masa_simpan_nol_ditolak(): void
    {
        DB::table('N_HRIS_Request_Diagnostics')->insert([$this->baris('baru', now()->subHour())]);

        // Tanpa penolakan ini, `--hari=0` mengosongkan seluruh tabel — dan itu
        // bentuk salah ketik yang paling gampang terjadi saat orang sedang
        // buru-buru membereskan tabel yang membesar.
        $this->artisan('hris:diagnostik-prune', ['--hari' => 0])->assertExitCode(1);

        $this->assertSame(['baru'], $this->tersisa(), '--hari=0 diterima dan menghapus baris.');
    }

    /** @return array<string,mixed> */
    private function baris(string $label, \DateTimeInterface $waktu): array
    {
        return [
            'Trace_Id' => $this->tanda,
            'Path' => $label,
            'Method' => 'GET',
            'Http_Status' => 500,
            'Total_Ms' => 4000,
            'Db_Query_Count' => 1,
            'Db_Query_Ms' => 10,
            'Slowest_Query_Ms' => 10,
            'Created_At' => $waktu,
        ];
    }

    /** @return array<int,string> Label baris milik uji ini yang masih hidup, terurut. */
    private function tersisa(): array
    {
        $sisa = DB::table('N_HRIS_Request_Diagnostics')
            ->where('Trace_Id', $this->tanda)
            ->orderBy('Path')
            ->pluck('Path')
            ->all();

        return array_map('strval', $sisa);
    }
}
