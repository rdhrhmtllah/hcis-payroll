<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar update performance harus membawa Kode_Karyawan.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Baris di daftar tim maupun bypass hanya membawa nama, jabatan, dan divisi.
 * Ketika dua orang bernama sama berada di divisi yang sama, tak ada satu pun
 * hal di layar yang membedakan mereka — sementara yang dipilih menentukan
 * SIAPA yang dinilai. Salahnya tidak melempar galat; ia hanya menilai orang
 * yang keliru.
 *
 * Nama kembar bukan hipotesis: di Web_HRIS 10 September 2026 ada puluhan nama
 * yang dipakai lebih dari satu karyawan aktif.
 *
 * Untuk daftar TIM, kolomnya bahkan sudah lama ikut diambil kuerinya —
 * `karyawan.Kode_Karyawan as kode_karyawan` — lalu dibuang saat menyusun baris.
 * Jadi datanya ada sejak awal, hanya tak pernah sampai ke layar.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * Kedua daftar memulangkan kode_karyawan yang TERISI. Dijaga bersama dalam satu
 * berkas karena keduanya dua pintu untuk pekerjaan yang sama, dan sudah terbukti
 * di proyek ini bahwa aturan yang hanya dipasang di satu pintu akan menyimpang
 * di pintu lainnya.
 */
class DaftarUpdatePerformanceBawaKodeKaryawanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daftar_bypass_membawa_kode_karyawan(): void
    {
        $ada = DB::table('KPI_Pengajuan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->exists();

        if (! $ada) {
            $this->markTestSkipped('Tak ada pengajuan disetujui di data ini.');
        }

        $respons = app(UpdatePerformanceController::class)->getDataKpiItemsByPass(
            Request::create('/api/v1/update-performance/list-Kpi-bypass', 'GET', ['page' => 1, 'limit' => 5]),
        );

        $isi = json_decode($respons->getContent(), true);
        $baris = $isi['result'] ?? [];

        if ($baris === []) {
            $this->markTestSkipped('Daftar bypass kosong pada data ini.');
        }

        foreach ($baris as $b) {
            $this->assertArrayHasKey(
                'kode_karyawan',
                $b,
                'Baris daftar bypass tak membawa kode_karyawan; dua orang bernama sama di '.
                'divisi yang sama tak bisa dibedakan di layar.',
            );
            $this->assertNotEmpty(
                trim((string) $b['kode_karyawan']),
                'kode_karyawan ada tetapi kosong — sama saja tak ada pembeda.',
            );
        }
    }

    public function test_daftar_tim_membawa_kode_karyawan(): void
    {
        // Disemai sendiri, tidak menumpang data yang kebetulan ada: ketiga
        // pengirim tim di basis data ini memulangkan daftar KOSONG karena timnya
        // sudah selesai diupdate, dan penjaga yang bergantung pada kebetulan
        // seperti itu akan dilewati tanpa pernah menyentuh yang dijaganya.
        $karyawan = DB::table('karyawan')
            ->join(
                'View_Divisi_Sub_Divisi as vd',
                'karyawan.ID_Divisi_Sub_Divisi',
                '=',
                'vd.ID_DIVISI_SUB_DIVISI',
            )
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'karyawan.ID_Level_Jabatan',
                '=',
                'vg.ID_Level_Jabatan',
            )
            ->whereNotNull('karyawan.UserID_Web')
            ->where('karyawan.Aktif', 'Y')
            ->first(['karyawan.UserID_Web', 'karyawan.Kode_Karyawan']);

        if (! $karyawan) {
            $this->markTestSkipped('Tak ada karyawan yang lengkap divisi & jabatannya di data ini.');
        }

        // Keanggotaan tim sekarang ditentukan roster KPI_Karyawan_Team, bukan lagi
        // "siapa yang mengirim pengajuan". Leader karangan tanpa baris Karyawan
        // karena itu tidak lagi punya anggota siapa pun, dan fixture-nya harus
        // mengikuti kontrak yang berlaku — bukan kontrak yang sudah tidak ada.
        $leaderKaryawan = DB::table('karyawan')
            ->whereNotNull('UserID_Web')
            ->where('Kode_Karyawan', '!=', $karyawan->Kode_Karyawan)
            ->first(['UserID_Web', 'Kode_Karyawan']);

        if (! $leaderKaryawan) {
            $this->markTestSkipped('Tak ada karyawan lain yang bisa berperan sebagai leader.');
        }

        $leader = (int) $leaderKaryawan->UserID_Web;

        DB::table('KPI_Karyawan_Team')->insert([
            'Kode_Karyawan_Leader' => $leaderKaryawan->Kode_Karyawan,
            'Kode_Karyawan_Team' => $karyawan->Kode_Karyawan,
            'Flag_Aktif' => 'Y',
            'create_at' => now(),
        ]);

        $idJadwal = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji daftar tim',
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3)->format('Y-m-d'),
            'Jadwal_Akhir' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-'.bin2hex(random_bytes(4)),
            'Id_Penjadwalan' => $idJadwal,
            'Id_User' => $karyawan->UserID_Web,
            'Id_User_Sender' => $leader,
            'Flag_Team' => 'Y',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Tanggal' => now(),
        ], 'Id_Pengajuan');

        // Anggota tanpa target jatuh tempo tidak lagi muncul di To Update
        // (UpdatePerformanceTanpaTargetTest), jadi fixture-nya harus punya satu
        // parameter bertarget tepat di periode jadwal.
        $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
            'Id_Pengajuan' => $idPengajuan,
            'Nama_Parameter' => 'Parameter uji daftar tim',
            'Id_Master_Target' => DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target'),
            'Bobot' => 100,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
        ], 'Id_Pengajuan_Detail');

        DB::table('KPI_Pengajuan_Det')->insert([
            'Id_Pengajuan_Detail' => $idDetail,
            'Nama_Target' => 'Target '.date('Y-m'),
            'Nilai_Target' => 100,
            'Periode' => date('Y-m'),
        ]);

        $metode = new \ReflectionMethod(UpdatePerformanceController::class, 'buildToUpdatePairs');
        $metode->setAccessible(true);

        $pasangan = $metode->invoke(app(UpdatePerformanceController::class), $leader, null);

        $this->assertTrue(
            $pasangan->isNotEmpty(),
            'Fixture tidak menghasilkan satu pun anggota untuk diupdate — penjaga ini tak menguji apa pun.',
        );

        foreach ($pasangan->take(5) as $b) {
            $this->assertArrayHasKey('kode_karyawan', $b, 'Baris daftar tim tak membawa kode_karyawan.');
            $this->assertNotEmpty(trim((string) $b['kode_karyawan']), 'kode_karyawan tim kosong.');
        }
    }
}
