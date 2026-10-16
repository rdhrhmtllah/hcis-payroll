<?php

namespace Tests\Feature\ESS\Dashboard;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kalender homepage harus benar-benar mengembalikan data.
 *
 * Dua rute — homepage/calendar-data dan uDash/getCalendarData — menjawab 500
 * untuk siapa pun, setiap kali. Sebabnya `HomepageController::getCalendarData()`
 * memakai `$this->attendanceService` yang tidak pernah disuntikkan di
 * konstruktor. Kegagalannya tertelan `catch (\Throwable)` dan keluar sebagai
 * "Tidak bisa mengambil data kalender", sehingga selama ini terbaca sebagai
 * gangguan data, bukan cacat pemrograman.
 *
 * DisiplinPropertiControllerTest menjaga BENTUK kodenya; uji ini menuntut
 * hasilnya benar-benar terlihat di jaringan. Bentuk yang benar belum tentu
 * berarti jawabannya benar — misalnya bila properti disuntikkan tapi salah
 * kelas.
 */
class KalenderHomepageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        /* withoutMiddleware() mematikan autentikasi, sedangkan controllernya
           membaca Auth::user()->karyawan. Penggunanya dipilih yang punya
           UserID_Absen — tanpa itu, prosedur absensi tak punya siapa pun untuk
           dicari dan kalendernya kosong karena alasan yang bukan urusan uji ini. */
        $aktor = User::query()
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->select('KPI_Users.Id_Users')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.UserID_Absen')
            ->orderBy('KPI_Users.Id_Users')
            ->first();

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada karyawan aktif ber-UserID_Absen di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login(User::find($aktor->Id_Users));
    }

    public static function ruteKalender(): array
    {
        return [
            'homepage' => ['/homepage/calendar-data'],
            'uDash' => ['/uDash/getCalendarData'],
        ];
    }

    /**
     * @dataProvider ruteKalender
     */
    public function test_kalender_menjawab_200_bukan_500(string $uri): void
    {
        $res = $this->getJson($uri);

        $this->assertSame(
            200,
            $res->getStatusCode(),
            "{$uri} menjawab {$res->getStatusCode()}.\n\nBadan: "
            .substr((string) $res->getContent(), 0, 300)
            ."\n\nBila pesannya \"Tidak bisa mengambil data kalender\", periksa dulu apakah "
            .'ada properti yang dibaca tapi tak pernah disuntikkan — kegagalan seperti itu '
            .'tertelan catch generik dan menyamar jadi gangguan data.'
        );
    }

    /**
     * Sisi kedua: 200 yang kosong bukan kalender yang bekerja.
     *
     * Tanpa ini, mengganti isi getCalendarData() dengan `return []` akan lolos
     * kedua uji di atas.
     */
    public function test_kalender_membawa_kejadian_yang_terbentuk_utuh(): void
    {
        $res = $this->getJson('/homepage/calendar-data');
        $res->assertOk();

        $data = $res->json('data');
        $this->assertIsArray($data);

        if ($data === []) {
            $this->markTestSkipped('Karyawan uji tidak punya jadwal pada periode berjalan.');
        }

        $pertama = $data[0];
        foreach (['id', 'title', 'start', 'extendedProps'] as $medan) {
            $this->assertArrayHasKey($medan, $pertama, "Kejadian kalender kehilangan medan '{$medan}'.");
        }
        $this->assertNotSame('', (string) $pertama['title'], 'Kejadian kalender tanpa judul.');
    }

    /**
     * Prosedur absensi harus benar-benar dipanggil.
     *
     * Kalau suatu hari jalur ini diganti menjadi jawaban statis, kedua uji di
     * atas tetap hijau. Yang membedakan kalender sungguhan dari kalender palsu
     * adalah ia menyentuh sumber absensinya.
     */
    public function test_kalender_menyentuh_sumber_absensi(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $this->getJson('/homepage/calendar-data')->assertOk();

        $menyentuh = false;
        foreach ($kueri as $sql) {
            if (stripos($sql, 'N_HRIS_SP_GET_ABSEN_WEB_WRAPPER') !== false) {
                $menyentuh = true;
                break;
            }
        }

        $this->assertTrue(
            $menyentuh,
            'Kalender dijawab tanpa memanggil prosedur absensi sama sekali — '
            .'jawabannya tidak bersumber dari data absensi.'
        );
    }
}
