<?php

namespace Tests\Feature\ESS\Attendance;

use App\Http\Controllers\ESS\Attendance\Shift\Management\ShiftController;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Export shift (/swapShift/getExport) tidak pernah bisa berhasil.
 *
 * ── Dua cacat berlapis ──────────────────────────────────────────────────────
 *
 * 1. Baris pertama di dalam try mengambil $divisi lalu TIDAK PERNAH memakainya
 *    sekali pun — sementara pembacaan itu sendirilah yang menjatuhkan seluruh
 *    permintaan. Pada log 10 September 2026 ia gagal empat kali dengan
 *    'Attempt to read property "divisionKaryawan" on null'.
 *
 * 2. Di bawahnya, hasil pemanggilan service disimpan ke $dataAbsen sementara
 *    perulangannya membaca $resultData — nama lama dari sebelum perpindahan ke
 *    service. Variabel itu tidak pernah ada. Artinya seandainya cacat pertama
 *    diperbaiki sendirian, permintaannya tetap 500, hanya dengan pesan berbeda:
 *    memperbaiki satu saja memindahkan kegagalan, bukan menghilangkannya.
 *
 * Keduanya tersembunyi di balik catch yang memulangkan 500 generik, jadi dari
 * layar keduanya terlihat persis sama.
 */
class ExportShiftTerpanggilTest extends TestCase
{
    public function test_export_shift_memulangkan_data_bukan_500(): void
    {
        $leader = $this->leaderBeranggota();

        $this->swap(HrisAttendanceSnapshotService::class, new class extends HrisAttendanceSnapshotService
        {
            public function getAttendance(
                string $kodePerusahaan,
                string $tglAwal,
                string $tglAkhir,
                string $userIdList,
                string $executionMode = 'AUTO',
                int $maxSyncUserDate = 4500,
                string $requestSource = 'WEB',
                ?string $requestedBy = null,
                ?int $priority = null,
            ): array {
                return [
                    'success' => true,
                    'data' => [
                        (object) ['Tanggal' => '2026-03-02', 'Kode_Karyawan' => 'X1'],
                        (object) ['Tanggal' => '2026-03-02', 'Kode_Karyawan' => 'X2'],
                        (object) ['Tanggal' => '2026-03-04', 'Kode_Karyawan' => 'X1'],
                    ],
                ];
            }
        });

        Auth::setUser($leader);

        $req = Request::create('/swapShift/getExport', 'GET', [
            'start' => '2026-03-02',
            'end' => '2026-03-04',
        ]);
        $req->setUserResolver(fn () => Auth::user());
        app()->instance('request', $req);

        $respons = app(ShiftController::class)->getExport($req);
        $isi = json_decode($respons->getContent(), true);

        $this->assertSame(
            200,
            $respons->getStatusCode(),
            'Export shift memulangkan 500. Isi respons: '.$respons->getContent(),
        );

        // Bukan sekadar 200: pastikan perulangan pengelompokannya benar-benar
        // jalan. Tanpa ini, early-return "tidak ada anggota" ikut lolos dan
        // penjaga ini tidak menguji apa pun.
        $this->assertIsArray($isi['data'] ?? null, 'Respons tidak membawa data.');
        $this->assertSame(
            ['2026-03-02', '2026-03-03', '2026-03-04'],
            array_keys($isi['data']),
            'Rentang tanggalnya tidak terbentuk — perulangan pengelompokan tidak dijalankan.',
        );
        $this->assertCount(2, $isi['data']['2026-03-02'], 'Baris 2 Maret tidak terkelompok.');
        $this->assertCount(0, $isi['data']['2026-03-03'], 'Hari kosong seharusnya tetap ada sebagai array kosong.');
    }

    public function test_export_shift_tidak_membaca_divisi_yang_tidak_dipakai(): void
    {
        $isi = file_get_contents(
            base_path('app/Http/Controllers/ESS/Attendance/Shift/Management/ShiftController.php'),
        );

        $awal = strpos($isi, 'public function getExport(Request $request)');
        $this->assertNotFalse($awal, 'Metode getExport tidak ditemukan lagi.');

        $akhir = strpos($isi, 'Gagal Mengambil data getExport', $awal);
        $badan = substr($isi, $awal, $akhir - $awal);

        $this->assertStringNotContainsString(
            '->divisionKaryawan',
            $badan,
            'getExport membaca divisionKaryawan lagi. Nilainya tidak dipakai di mana pun, '.
            'sementara pembacaannya sendiri sudah terbukti menjatuhkan seluruh export.',
        );
    }

    /**
     * Baris yang sama disalin ke sepuluh metode; delapan di antaranya tidak
     * pernah memakai nilainya.
     *
     * Pola ini sudah berulang di proyek ini: aturan yang hanya diperbaiki di
     * satu dari sekian jalur setara akan menyimpang di jalur lainnya. Jadi yang
     * dijaga bukan getExport saja, melainkan bentuknya — sebuah pembacaan yang
     * bisa melempar tetapi hasilnya dibuang.
     */
    public function test_tidak_ada_lagi_pembacaan_divisi_yang_hasilnya_dibuang(): void
    {
        $mati = [];

        foreach ([
            'app/Http/Controllers/ESS/Attendance/Shift/Management/ShiftController.php',
            'app/Http/Controllers/ESS/Attendance/Overtime/Management/OvertimeController.php',
        ] as $berkas) {
            $isi = file_get_contents(base_path($berkas));

            preg_match_all('/^    (?:public|private|protected)\s+function\s+(\w+)/m', $isi, $m, PREG_OFFSET_CAPTURE);
            $awal = $m[0];
            $nama = $m[1];

            foreach ($awal as $i => $tanda) {
                $mulai = $tanda[1];
                $akhir = $awal[$i + 1][1] ?? strlen($isi);
                $badan = substr($isi, $mulai, $akhir - $mulai);

                if (! preg_match('/\$divisi = Auth\(\)->user\(\)->divisionKaryawan->/', $badan)) {
                    continue;
                }

                // Komentar dibuang lebih dulu supaya "// dd($divisi)" tidak
                // terhitung sebagai pemakaian.
                $bersih = preg_replace('#//[^\n]*#', '', $badan);

                if (preg_match_all('/\$divisi\b/', $bersih) === 1) {
                    $mati[] = basename($berkas).'::'.$nama[$i][0];
                }
            }
        }

        $this->assertSame(
            [],
            $mati,
            'Ada pembacaan $divisi yang hasilnya tidak dipakai sama sekali, padahal pembacaannya '.
            'sendiri bisa melempar dan menjatuhkan seluruh permintaan: '.implode(', ', $mati),
        );
    }

    private function leaderBeranggota(): User
    {
        $baris = DB::selectOne("
            SELECT TOP 1 d.Kode_Karyawan_Team AS leader
            FROM Karyawan a
            JOIN HRIS_Shift_Per_Karyawan b ON a.Kode_Karyawan = b.Kode_Karyawan
            JOIN View_Divisi_Sub_Divisi c ON a.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
            JOIN HRIS_Karyawan_Team d ON a.Kode_Karyawan = d.Kode_Karyawan
            WHERE a.UserID_Absen IS NOT NULL
              AND a.Aktif = 'Y'
              AND a.Kode_Karyawan <> d.Kode_Karyawan_Team
            GROUP BY d.Kode_Karyawan_Team
            ORDER BY COUNT(*) DESC
        ");

        if (! $baris) {
            $this->markTestSkipped('Tidak ada leader shift beranggota di lingkungan ini.');
        }

        $idWeb = DB::table('Karyawan')->where('Kode_Karyawan', $baris->leader)->value('UserID_Web');
        $user = $idWeb ? User::find((int) $idWeb) : null;

        if (! $user) {
            $this->markTestSkipped('Leader shift tidak punya akun web.');
        }

        return $user;
    }
}
