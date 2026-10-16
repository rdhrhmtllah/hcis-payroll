<?php

namespace Tests\Feature\ESS\Attendance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Parameter yang kurang dijawab 422, bukan 500 — jalur Shift.
 *
 * Ditemukan saat menyapu 61 rute GET ESS · Attendance · Shift: tiga menjawab
 * 500, dan ketiganya sebab yang sama — parameter wajib DIBACA TANPA
 * DIVALIDASI SAMA SEKALI.
 *
 * Ini bukan cacat yang sama dengan "validasi tertelan catch generik"
 * (DisiplinValidasiTertelanTest). Di sana validasinya ada tapi hasilnya
 * tertelan; di sini validasinya memang tidak pernah ditulis, sehingga nilai
 * kosong mengalir sampai jauh ke dalam:
 *
 *   swapShift/isLembur        whereIn() menerima null, foreach berjalan atas null
 *   swapShift/getExport       strtotime(null) = false -> tanggal 1970-01-01
 *                             dipakai memanggil prosedur absensi
 *   .../shift-rule/group-detail  getGroupConfig() melempar InvalidArgumentException
 *                             yang tak ditangkap siapa pun -> "Server Error"
 *
 * Yang dijaga bukan sekadar "422 muncul", melainkan DUA sisi sekaligus:
 * permintaan yang kurang parameter ditolak dengan jelas, DAN permintaan yang
 * benar tetap dilayani. Menambahkan validasi itu murah; menambahkannya terlalu
 * ketat sehingga layar yang sudah jalan ikut tertolak itu mahal, dan hanya sisi
 * kedua yang bisa menangkapnya.
 */
class ShiftParameterWajibTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public static function ruteTanpaParameter(): array
    {
        return [
            'group detail shift rule' => ['/leader-shift/admin/shift-rule/group-detail'],
            'export jadwal shift' => ['/swapShift/getExport'],
            'cek lembur bentrok' => ['/swapShift/isLembur'],
        ];
    }

    /**
     * @dataProvider ruteTanpaParameter
     */
    public function test_tanpa_parameter_wajib_dijawab_422(string $uri): void
    {
        $res = $this->getJson($uri);

        $this->assertSame(
            422,
            $res->getStatusCode(),
            "{$uri} menjawab {$res->getStatusCode()} untuk permintaan tanpa parameter. "
            .'Parameter yang kurang adalah kesalahan PEMANGGIL (422), bukan kegagalan '
            ."SISTEM (500).\n\nBadan jawaban: ".substr((string) $res->getContent(), 0, 300)
        );
    }

    /** view_by di luar daftar ditolak, bukan meledak di getGroupConfig(). */
    public function test_view_by_asal_dijawab_422(): void
    {
        $res = $this->getJson('/leader-shift/admin/shift-rule/group-detail?view_by=bukan_sumbu');

        $res->assertStatus(422);
    }

    /**
     * Sisi yang menangkap validasi yang KETERLALUAN.
     *
     * Tanpa ini, aturan yang salah tulis — misal menuntut medan yang tak pernah
     * dikirim layar — akan membuat semua uji di atas tetap hijau sementara
     * halamannya rusak total.
     */
    public function test_permintaan_yang_benar_tetap_dilayani(): void
    {
        $res = $this->getJson('/leader-shift/admin/shift-rule/group-detail?view_by=id_divisi&group_value=__NULL__&flag_aktif=');
        $this->assertSame(200, $res->getStatusCode(), 'group-detail menolak permintaan yang sah: '
            .substr((string) $res->getContent(), 0, 300));

        $res = $this->getJson('/swapShift/getExport?start=2026-09-01&end=2026-09-07');
        $this->assertNotSame(422, $res->getStatusCode(), 'getExport menolak rentang tanggal yang sah: '
            .substr((string) $res->getContent(), 0, 300));

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->value('Kode_Karyawan');
        if ($karyawan === null) {
            $this->markTestSkipped('Tidak ada karyawan aktif di data dev.');
        }

        $res = $this->getJson('/swapShift/isLembur?employees[]='.urlencode((string) $karyawan).'&dates[]=2026-09-01');
        $this->assertNotSame(422, $res->getStatusCode(), 'isLembur menolak payload yang sah: '
            .substr((string) $res->getContent(), 0, 300));
    }

    /**
     * Daftar sumbu yang sah tidak boleh berpisah dari peta yang memakainya.
     *
     * VIEW_BY_SAH menjaga pintu; getGroupConfig() memegang petanya. Kalau suatu
     * hari sumbu baru ditambahkan ke salah satunya saja, gejalanya diam-diam:
     * sumbu baru ditolak 422 walau petanya sudah siap, atau sebaliknya lolos
     * pintu lalu meledak di dalam. Pola "aturan cuma dipasang di satu dari dua
     * tempat setara" sudah berkali-kali menggigit repo ini.
     */
    public function test_daftar_sumbu_sah_sejalan_dengan_petanya(): void
    {
        $berkas = base_path('app/Http/Controllers/ESS/Attendance/Shift/Rules/ShiftRuleAdminController.php');
        $isi = (string) file_get_contents($berkas);

        $kelas = new \ReflectionClass(\App\Http\Controllers\ESS\Attendance\Shift\Rules\ShiftRuleAdminController::class);
        $sah = $kelas->getConstant('VIEW_BY_SAH');
        $this->assertIsArray($sah, 'Konstanta VIEW_BY_SAH tidak ditemukan.');

        // Kunci peta di getGroupConfig(): baris "'nama' => [" di dalam $map.
        $potongan = substr($isi, (int) strpos($isi, 'function getGroupConfig'));
        $potongan = substr($potongan, 0, (int) strpos($potongan, 'return $map['));
        preg_match_all("~^\s{12}'([a-z_]+)' => \[~m", $potongan, $m);
        $petaKunci = $m[1] ?? [];

        $this->assertNotEmpty($petaKunci, 'Kunci peta getGroupConfig() tak terbaca — penjaga ini perlu disesuaikan.');

        // 'kode_karyawan' memang ditangani sebelum peta dipakai, jadi ia sah
        // tanpa harus ada di peta.
        $diharapkan = array_values(array_diff($sah, ['kode_karyawan']));
        sort($diharapkan);
        sort($petaKunci);

        $this->assertSame(
            $petaKunci,
            $diharapkan,
            "VIEW_BY_SAH dan peta getGroupConfig() sudah berbeda isi.\n"
            .'  VIEW_BY_SAH (tanpa kode_karyawan): '.implode(', ', $diharapkan)."\n"
            .'  kunci peta                       : '.implode(', ', $petaKunci)."\n\n"
            .'Sumbu yang ada di pintu tapi tak ada di peta akan meledak di dalam; '
            .'sumbu yang ada di peta tapi tak ada di pintu ditolak 422 walau siap dipakai.'
        );
    }
}
