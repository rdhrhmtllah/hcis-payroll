<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Alur persetujuan di langkah 3 harus membicarakan PERIODE YANG SEDANG DIBUKA.
 *
 * Sampai 4 Sep 2026 tidak. getApprovalFlow() mencari pengajuan final hanya
 * dengan Id_User + Flag_Aktif + ada No_Transaksi, lalu mengambil yang terakhir
 * menurut Tanggal — tanpa menyentuh kolom Periode yang justru ada di tabel yang
 * sama. Akibatnya keliru ke DUA arah, dan keduanya benar-benar terjadi di dev:
 *
 *   Id_User 642  periode baru menyodorkan "3 dari 3 approver telah menyetujui"
 *                milik periode 2026-05 yang sudah lama selesai
 *   Id_User 634  empat periode yang SUDAH disetujui tampil "belum diajukan",
 *                karena baris terakhirnya kebetulan periode lain yang ditolak
 *
 * Arah kedua yang lebih berbahaya: progres yang nyata jadi tak terlihat.
 *
 * Bahan ujinya DITEMUKAN dari data, bukan dipatok. Satu pengajuan disisipkan
 * di dalam transaksi supaya bentuk yang diuji pasti ada tanpa bergantung pada
 * kebetulan isi dev hari ini.
 */
class AlurApprovalTerikatPeriodeTest extends TestCase
{
    use DatabaseTransactions;

    private function panggil(int $idUser, ?string $hashJadwal): array
    {
        $permintaan = new Request(
            $hashJadwal !== null ? ['Id_Master_Penjadwalan' => $hashJadwal] : [],
        );

        $respons = app(UpdatePerformanceController::class)->getApprovalFlow(
            $permintaan,
            Hashids::connection('custom')->encode($idUser),
        );

        return json_decode($respons->getContent(), true)['result'] ?? [];
    }

    /** Hash sebuah jadwal yang periodenya persis $periode. */
    private function hashJadwalBerperiode(string $periode): ?string
    {
        $id = DB::table('KPI_Master_Penjadwalan')
            ->whereRaw('CONVERT(varchar(7), Periode, 126) = ?', [$periode])
            ->value('Id_Master_Penjadwalan');

        return $id ? Hashids::connection('custom')->encode($id) : null;
    }

    /** Seorang karyawan yang punya pengajuan final, beserta periodenya. */
    private function pengajuanNyata(): ?object
    {
        return DB::table('KPI_Update_Performance as u')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_User')
            ->where('u.Flag_Aktif', 'Y')
            ->whereNotNull('u.No_Transaksi')
            ->whereNotNull('u.Periode')
            ->orderByDesc('u.Id_Update_Performance')
            ->first(['u.Id_User', 'u.Periode', 'u.No_Transaksi', 'u.Status_Pengajuan']);
    }

    /**
     * Periode yang PUNYA jadwal tetapi TIDAK punya pengajuan milik orang ini.
     * Itulah bentuk "baru mau mengajukan" yang dilaporkan.
     */
    private function periodeTanpaPengajuan(int $idUser): ?string
    {
        $dipakai = DB::table('KPI_Update_Performance')
            ->where('Id_User', $idUser)
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('No_Transaksi')
            ->pluck('Periode')
            ->filter()
            ->all();

        return DB::table('KPI_Master_Penjadwalan')
            ->whereNotNull('Periode')
            ->whereRaw("CONVERT(varchar(7), Periode, 126) NOT IN ('".implode("','", $dipakai ?: ['-'])."')")
            ->orderBy('Periode')
            ->selectRaw('CONVERT(varchar(7), Periode, 126) AS p')
            ->value('p');
    }

    public function test_periode_tanpa_pengajuan_tidak_meminjam_alur_periode_lain(): void
    {
        $ada = $this->pengajuanNyata();
        if (! $ada) {
            $this->markTestSkipped('Tidak ada pengajuan final di data dev.');
        }

        $periodeKosong = $this->periodeTanpaPengajuan((int) $ada->Id_User);
        if (! $periodeKosong) {
            $this->markTestSkipped('Orang ini punya pengajuan di setiap periode berjadwal.');
        }

        $hash = $this->hashJadwalBerperiode($periodeKosong);
        $this->assertNotNull($hash, "Jadwal untuk periode {$periodeKosong} harus ada.");

        $hasil = $this->panggil((int) $ada->Id_User, $hash);

        $this->assertFalse(
            $hasil['submitted'] ?? null,
            "Periode {$periodeKosong} belum punya pengajuan, tetapi layar melaporkan sudah diajukan — ".
            "alurnya dipinjam dari periode lain (orang ini punya {$ada->No_Transaksi} di {$ada->Periode}).",
        );

        // Yang tampil harus PREVIEW: semua tahap menunggu, tidak ada yang hijau.
        foreach ($hasil['steps'] ?? [] as $tahap) {
            $this->assertSame('pending', $tahap['status'] ?? null,
                'Preview alur tidak boleh memuat tahap yang sudah berstatus.');
        }
    }

    public function test_periode_yang_sudah_diajukan_tetap_menampilkan_alurnya_sendiri(): void
    {
        $ada = $this->pengajuanNyata();
        if (! $ada) {
            $this->markTestSkipped('Tidak ada pengajuan final di data dev.');
        }

        // 'ditolak' memang sengaja tampil sebagai preview (user sedang merevisi),
        // jadi ia bukan bahan uji untuk sisi ini.
        if ($ada->Status_Pengajuan === 'ditolak') {
            $ada = DB::table('KPI_Update_Performance')
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('No_Transaksi')
                ->whereNotNull('Periode')
                ->where('Status_Pengajuan', '<>', 'ditolak')
                ->orderByDesc('Id_Update_Performance')
                ->first(['Id_User', 'Periode', 'No_Transaksi', 'Status_Pengajuan']);

            if (! $ada) {
                $this->markTestSkipped('Tidak ada pengajuan non-ditolak.');
            }
        }

        $periode = substr((string) $ada->Periode, 0, 7);
        $hash = $this->hashJadwalBerperiode($periode);
        if (! $hash) {
            $this->markTestSkipped("Tidak ada jadwal berperiode {$periode}.");
        }

        $hasil = $this->panggil((int) $ada->Id_User, $hash);

        $this->assertTrue(
            $hasil['submitted'] ?? null,
            "Periode {$periode} punya pengajuan {$ada->No_Transaksi} ({$ada->Status_Pengajuan}), ".
            'tetapi layar melaporkan belum diajukan — progres yang nyata jadi tak terlihat.',
        );
    }

    /**
     * Arah kedua, dan yang paling merugikan: pengajuan periode LAIN yang lebih
     * baru tidak boleh menutupi periode yang sedang dibuka.
     *
     * Bentuknya disisipkan sendiri supaya pasti ada: satu pengajuan 'ditolak'
     * berperiode lain, bertanggal lebih baru daripada pengajuan yang diuji.
     * Sebelum perbaikan, baris inilah yang terpilih dan seluruh periode — juga
     * yang sudah disetujui — jatuh ke tampilan "belum diajukan".
     */
    public function test_pengajuan_periode_lain_yang_lebih_baru_tidak_menutupinya(): void
    {
        $ada = DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('No_Transaksi')
            ->whereNotNull('Periode')
            ->where('Status_Pengajuan', '<>', 'ditolak')
            ->orderByDesc('Id_Update_Performance')
            ->first();

        if (! $ada) {
            $this->markTestSkipped('Tidak ada pengajuan non-ditolak di data dev.');
        }

        $periodeDiuji = substr((string) $ada->Periode, 0, 7);
        $periodeLain = $this->periodeTanpaPengajuan((int) $ada->Id_User);
        if (! $periodeLain) {
            $this->markTestSkipped('Tidak ada periode lain yang bebas untuk disisipi.');
        }

        $baris = (array) $ada;
        unset($baris['Id_Update_Performance']);
        $baris['No_Transaksi'] = 'UJI-PERIODE-LAIN';
        $baris['Periode'] = $periodeLain;
        $baris['Status_Pengajuan'] = 'ditolak';
        $baris['Tanggal'] = now()->addYear();   // dijamin paling baru
        DB::table('KPI_Update_Performance')->insert($baris);

        $hash = $this->hashJadwalBerperiode($periodeDiuji);
        if (! $hash) {
            $this->markTestSkipped("Tidak ada jadwal berperiode {$periodeDiuji}.");
        }

        $hasil = $this->panggil((int) $ada->Id_User, $hash);

        $this->assertTrue(
            $hasil['submitted'] ?? null,
            "Periode {$periodeDiuji} punya pengajuan {$ada->No_Transaksi}, tetapi tertutup oleh ".
            "pengajuan ditolak di periode {$periodeLain} yang tanggalnya lebih baru.",
        );
    }
}
