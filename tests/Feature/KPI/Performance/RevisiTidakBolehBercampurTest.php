<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Revisi seorang anggota tidak boleh membawa anggota lain.
 *
 * Menyimpan pengajuan tim MEMBATALKAN pengajuan lama yang masih hidup di tahun
 * yang sama (cariPengajuanParameterDigantikan). Itu memang yang diinginkan saat
 * memperbaiki orang yang ditolak. Tapi bila orang lain ikut terbawa dalam
 * kiriman yang sama, dua hal rusak sekaligus: template milik satu orang
 * tertulis ke orang lain, dan pengajuan mereka yang sedang menunggu approver
 * ikut dibatalkan tanpa ada yang menyadari.
 *
 * Penjagaannya ada di server dan bukan hanya di layar, karena kunci di layar
 * hanya menghalangi jalur yang lewat tombol.
 */
class RevisiTidakBolehBercampurTest extends TestCase
{
    use DatabaseTransactions;

    private function panggilPenjaga($kodeKaryawan, ?string $hashJadwal)
    {
        $metode = new ReflectionMethod(FormKpiController::class, 'tolakRevisiBercampur');
        $metode->setAccessible(true);

        return $metode->invoke(new FormKpiController, collect($kodeKaryawan), $hashJadwal);
    }

    /**
     * @return array{hash: string, tahun: int, kode_punya: string}|null
     */
    private function skenario(): ?array
    {
        $hidup = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->orderByDesc('kp.Id_Pengajuan')
            ->select('k.Kode_Karyawan', 'mp.Tahun', 'kp.Id_Penjadwalan')
            ->first();

        if (! $hidup) {
            return null;
        }

        return [
            'hash' => Hashids::connection('custom')->encode($hidup->Id_Penjadwalan),
            'tahun' => (int) $hidup->Tahun,
            'kode_punya' => $hidup->Kode_Karyawan,
        ];
    }

    /** Kode karyawan yang TIDAK punya pengajuan hidup di tahun tersebut. */
    private function kodeTanpaPengajuan(int $tahun, string $kecuali): ?string
    {
        return DB::table('Karyawan as k')
            ->where('k.Kode_Karyawan', '!=', $kecuali)
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) use ($tahun) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan as kp')
                    ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
                    ->whereColumn('kp.Id_User', 'k.UserID_Web')
                    ->where('kp.Flag_Aktif', 'Y')
                    ->whereNull('kp.Status')
                    ->where('mp.Tahun', $tahun);
            })
            ->value('k.Kode_Karyawan');
    }

    public function test_revisi_bercampur_anggota_lain_ditolak(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya pengajuan parameter yang masih hidup.');
        }

        $lain = $this->kodeTanpaPengajuan($s['tahun'], $s['kode_punya']);

        if (! $lain) {
            $this->markTestSkipped('Tidak ada karyawan tanpa pengajuan hidup untuk dijadikan pembanding.');
        }

        $hasil = $this->panggilPenjaga([$s['kode_punya'], $lain], $s['hash']);

        $this->assertNotNull($hasil, 'Kiriman campuran seharusnya ditolak, tapi lolos.');
        $this->assertSame(422, $hasil->getStatusCode());
        $this->assertStringContainsString('Revisi harus dikirim sendiri', $hasil->getContent());
    }

    public function test_revisi_sendirian_diizinkan(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya pengajuan parameter yang masih hidup.');
        }

        $this->assertNull(
            $this->panggilPenjaga([$s['kode_punya']], $s['hash']),
            'Revisi satu orang seharusnya lolos — itu justru alur yang dituju.',
        );
    }

    public function test_pengisian_massal_tanpa_revisi_tetap_boleh(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya pengajuan parameter yang masih hidup.');
        }

        $a = $this->kodeTanpaPengajuan($s['tahun'], $s['kode_punya']);
        $b = DB::table('Karyawan')
            ->whereNotIn('Kode_Karyawan', array_filter([$s['kode_punya'], $a]))
            ->whereNotNull('UserID_Web')
            ->whereNotExists(function ($q) use ($s) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan as kp')
                    ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
                    ->whereColumn('kp.Id_User', 'Karyawan.UserID_Web')
                    ->where('kp.Flag_Aktif', 'Y')
                    ->whereNull('kp.Status')
                    ->where('mp.Tahun', $s['tahun']);
            })
            ->value('Kode_Karyawan');

        if (! $a || ! $b) {
            $this->markTestSkipped('Tidak cukup karyawan tanpa pengajuan hidup.');
        }

        // Inti penjagaan ini adalah REVISI, bukan melarang pengisian banyak
        // orang. Tanpa test ini, aturannya bisa diperketat berlebihan tanpa
        // ketahuan dan melumpuhkan alur utama form tim.
        $this->assertNull(
            $this->panggilPenjaga([$a, $b], $s['hash']),
            'Pengisian massal untuk anggota yang belum punya pengajuan seharusnya tetap boleh.',
        );
    }

    public function test_tanpa_jadwal_penjaga_tidak_menebak(): void
    {
        $s = $this->skenario();

        if (! $s) {
            $this->markTestSkipped('Data dev tidak punya pengajuan parameter yang masih hidup.');
        }

        // Tanpa jadwal, "pengajuan hidup di tahun yang sama" tidak terdefinisi.
        // Penjaga harus diam dan menyerahkan penolakan pada validasi jadwal,
        // bukan menebak-nebak lalu memblokir kiriman yang sah.
        $this->assertNull($this->panggilPenjaga([$s['kode_punya'], 'APAPUN'], null));
    }
}
