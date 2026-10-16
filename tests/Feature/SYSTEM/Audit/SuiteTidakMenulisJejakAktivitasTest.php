<?php

namespace Tests\Feature\SYSTEM\Audit;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Suite ini tidak boleh meninggalkan jejak di tabel aktivitas.
 *
 * ── Apa yang rusak kalau penjaga ini lepas ───────────────────────────────────
 *
 * TrackUserActivity terpasang di grup 'web' DAN 'api', dan
 * activity-tracker.enabled baku true. Tanpa sakelar di phpunit.xml, setiap
 * panggilan HTTP di suite menulis baris NYATA ke N_HRIS_Activity_Event pada
 * basis data yang sedang dituju.
 *
 * Terukur 9 September 2026 di Web_HRIS sebelum sakelar itu dipasang:
 *
 *   Ip_Address        baris
 *   ------------------------
 *   127.0.0.1        27.115   <- suite ini
 *   101.128.78.221    4.819
 *   sisanya             523
 *
 * 84% isi tabel itu jejak uji, dengan rute prosesLogin tercatat 3.922 kali.
 *
 * Kerusakannya bukan sekadar tabel kotor. sys.dm_db_index_usage_stats ikut
 * terisi trafik uji, sehingga pertanyaan "indeks ini pernah di-seek atau
 * tidak?" dijawab oleh robot, bukan oleh orang — dan keputusan membuang indeks
 * diambil dari angka yang tidak mewakili apa pun. Itu kekeliruan yang tidak
 * pernah melempar galat; ia hanya membuat kesimpulan yang salah terasa terukur.
 *
 * ── Kenapa diuji lewat EFEK ──────────────────────────────────────────────────
 *
 * Menegaskan config('activity-tracker.enabled') === false hanya membaca ulang
 * berkas yang sama dengan yang dijaga. Yang ditegaskan di sini adalah bahwa
 * memanggil jalur tulisnya benar-benar tidak menghasilkan baris.
 */
class SuiteTidakMenulisJejakAktivitasTest extends TestCase
{
    use DatabaseTransactions;

    public function test_memulai_sesi_tidak_menulis_baris_apa_pun(): void
    {
        // WAJIB pengguna NYATA — dua jebakan sekaligus, keduanya membuat penjaga
        // ini hijau palsu dan keduanya sudah saya tabrak saat menulisnya:
        //
        //   1. tanpa pengguna terautentikasi, IdentityResolver pulang kosong dan
        //      startSession() keluar sebelum menyentuh DB;
        //   2. dengan pengguna karangan, Kode_Karyawan jadi NULL — kolom itu NOT
        //      NULL, insert-nya gagal, dan kegagalannya DITELAN try/catch di
        //      startSession(). Tidak ada baris, tidak ada galat, uji lulus.
        //
        // Karena itu Id_Users diambil dari baris Karyawan yang benar-benar ada.
        $this->actingAs($this->penggunaUji());

        $tracker = app(ActivityTracker::class);

        $sebelumSesi = DB::table('N_HRIS_Activity_Session')->count();
        $sebelumEvent = DB::table('N_HRIS_Activity_Event')->count();

        // startSession() adalah jalur yang menulis PALING sinkron: ia menyisipkan
        // baris sesi lalu langsung memanggil insertAuthEventNow(), sengaja tanpa
        // queue supaya event login tak hilang bila worker mati. Kalau tracker
        // hidup saat uji, dua baris muncul di sini.
        $tracker->startSession(request());
        $tracker->flush();

        $this->assertSame(
            [$sebelumSesi, $sebelumEvent],
            [
                DB::table('N_HRIS_Activity_Session')->count(),
                DB::table('N_HRIS_Activity_Event')->count(),
            ],
            'Suite uji menulis baris ke tabel aktivitas. Selain mengotori data, ini '.
            'membuat sys.dm_db_index_usage_stats mencatat trafik robot — dan '.
            'keputusan indeks yang diambil darinya jadi salah tanpa terlihat salah.',
        );
    }

    /**
     * Pengguna yang UserID_Web-nya benar-benar ada di Karyawan, supaya
     * IdentityResolver memulangkan Kode_Karyawan dan insert-nya bisa lolos.
     */
    private function penggunaUji(): User
    {
        $userId = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->where('UserID_Web', '>', 0)
            ->value('UserID_Web');

        if ($userId === null) {
            $this->markTestSkipped(
                'Tak ada baris Karyawan ber-UserID_Web di basis data ini, jadi jalur '.
                'tulis tracker tidak bisa dicapai — penjaga ini akan hijau tanpa menguji apa pun.',
            );
        }

        $user = new User;
        $user->forceFill(['Id_Users' => (int) $userId, 'Username' => 'penjaga-jejak']);
        $user->exists = true;

        return $user;
    }

    public function test_tracker_mati_sepanjang_suite(): void
    {
        $this->assertFalse(
            app(ActivityTracker::class)->enabled(),
            'ACTIVITY_TRACKER_ENABLED hidup saat uji. Setiap panggilan HTTP di suite '.
            'akan menulis satu baris ke N_HRIS_Activity_Event pada basis data yang dituju.',
        );
    }
}
