<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Uat\UatGerbang;
use App\Services\LMS\SOP\Uat\UatPembangunDunia;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dunia uji BERSKALA untuk uji beban — satu paket terbit, ditugaskan ke puluhan
 * karyawan sekaligus.
 *
 * Bedanya dengan sop:uat-dunia hanya jumlah sasarannya, dan itulah intinya:
 * ambang penjadwalan_async_ambang bernilai 30, sehingga penugasan di bawah 31
 * berjalan inline sedangkan di atasnya lewat antrean. Dua paket produksi
 * terbesar (58 dan 34 karyawan) menempuh jalur antrean itu, dan jalur itu belum
 * pernah tersentuh uji mana pun.
 */
class SopBebanDunia extends Command
{
    protected $signature = 'sop:beban-dunia
        {--karyawan=50 : Jumlah karyawan aktif yang disasar penugasan}
        {--item=2 : Jumlah item dokumen di dalam paket}
        {--admin= : NIK admin pembangun; bawaannya diambil dari UAT_NIK_ADMIN}';

    protected $description = 'Bangun dunia SOP berskala (puluhan penugasan) untuk uji beban';

    public function handle(UatPembangunDunia $pembangun): int
    {
        try {
            UatGerbang::pastikanTerbuka();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $adminNik = $this->option('admin') ?: env('UAT_NIK_ADMIN');
        $admin = $this->userDari((string) $adminNik);

        if (! $admin) {
            $this->error("Admin '{$adminNik}' tidak ditemukan atau tak tertaut akun login.");

            return self::FAILURE;
        }

        $diminta = max(1, (int) $this->option('karyawan'));
        $sasaran = $pembangun->pilihKaryawanAktif($diminta);

        if ($sasaran === []) {
            $this->error('Tidak ada karyawan aktif untuk disasar.');

            return self::FAILURE;
        }

        /* Dilaporkan, bukan dipangkas diam-diam. Populasi dev terbatas; kalau
           yang tersedia kurang dari yang diminta dan jatuhnya di bawah 31, uji
           bebannya mengukur jalur inline sementara laporannya menyangka jalur
           antrean ikut terbukti. */
        if (count($sasaran) < $diminta) {
            $this->warn(sprintf(
                'Diminta %d karyawan, tersedia %d.%s',
                $diminta,
                count($sasaran),
                count($sasaran) <= 30
                    ? ' DI BAWAH AMBANG ASYNC 30 — penugasan akan berjalan inline, jalur antrean TIDAK tersentuh.'
                    : ''
            ));
        }

        $judul = [];
        for ($i = 1; $i <= max(1, (int) $this->option('item')); $i++) {
            $judul[] = "Dokumen {$i}";
        }

        try {
            $dunia = $pembangun->bangun((int) $admin->Id_Users, $sasaran, $judul);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($dunia, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function userDari(string $nik): ?User
    {
        $k = Karyawan::where('Kode_Karyawan', $nik)->first();

        return $k ? User::where('Id_Users', $k->UserID_Web)->first() : null;
    }
}
