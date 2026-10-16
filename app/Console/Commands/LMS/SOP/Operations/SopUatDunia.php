<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Uat\UatGerbang;
use App\Services\LMS\SOP\Uat\UatPembangunDunia;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bangun dunia SOP untuk lapis peramban, atau lengkapi jurnalnya sesudah dipakai.
 *
 * Dipanggil Playwright sebelum dan sesudah spesifikasi berjalan. Barisnya
 * dicatat ke jurnal jalan-uji, dan dihapus kembali oleh `sop:uat-bersihkan`.
 */
class SopUatDunia extends Command
{
    protected $signature = 'sop:uat-dunia
        {nik : Kode_Karyawan yang akan ditugaskan}
        {--admin= : NIK admin pembangun; bawaannya diambil dari UAT_NIK_ADMIN}
        {--item=2 : Jumlah item dokumen di dalam paket}
        {--urutan=BEBAS : BEBAS atau SEKUENSIAL}
        {--draft : Paket berhenti di DRAFT dan tidak ditugaskan ke siapa pun}
        {--revisi : Sesudah terbit, siapkan satu revisi dokumen yang menunggu rilis}
        {--lengkapi= : Id jalan-uji yang jurnalnya dilengkapi (butuh --penjadwalan)}
        {--penjadwalan= : Id penjadwalan, dipakai bersama --lengkapi}
        {--paket= : Id paket, dipakai bersama --lengkapi untuk menyapu baris hasil penerbitan}';

    protected $description = 'Bangun dunia SOP ber-jurnal untuk otomasi peramban UAT';

    public function handle(UatPembangunDunia $pembangun): int
    {
        try {
            UatGerbang::pastikanTerbuka();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('lengkapi')) {
            try {
                $n = $pembangun->lengkapiJurnal(
                    (string) $this->option('lengkapi'),
                    (int) $this->option('penjadwalan'),
                    (int) $this->option('paket')
                );
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->line(json_encode(['dicatat' => $n], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $adminNik = $this->option('admin') ?: env('UAT_NIK_ADMIN');
        $admin = $this->userDari((string) $adminNik);

        if (! $admin) {
            $this->error("Admin '{$adminNik}' tidak ditemukan atau tak tertaut akun login.");

            return self::FAILURE;
        }

        $judul = [];
        for ($i = 1; $i <= max(1, (int) $this->option('item')); $i++) {
            $judul[] = "Dokumen {$i}";
        }

        try {
            $dunia = $pembangun->bangun(
                (int) $admin->Id_Users,
                trim((string) $this->argument('nik')),
                $judul,
                strtoupper((string) $this->option('urutan')) === 'SEKUENSIAL' ? 'SEKUENSIAL' : 'BEBAS',
                ! $this->option('draft'),
                (bool) $this->option('revisi')
            );
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
