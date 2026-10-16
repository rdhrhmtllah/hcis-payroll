<?php

namespace App\Console\Commands\SYSTEM\Governance;

use App\Services\SYSTEM\Governance\Authorization\Akses\SinkronHakRole;
use Illuminate\Console\Command;

/** RBAC Fase 3a: samakan N_HRIS_Role_Hak dengan kondisi LAMA (modul LAMA/BAYANGAN). */
class SinkronHakRoleCommand extends Command
{
    protected $signature = 'rbac:sinkron-hak {--kering : Hanya hitung, tanpa mengubah data}';

    protected $description = 'Sinkronkan hak role (N_HRIS_Role_Hak) dengan hibah role yang berlaku sekarang.';

    public function handle(SinkronHakRole $sinkron): int
    {
        try {
            $h = $sinkron->jalankan((bool) $this->option('kering'));
        } catch (\App\Services\SYSTEM\Governance\Authorization\Akses\SinkronSedangBerjalan $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }
        $this->info(sprintf('%stambah=%d aktifkan=%d nonaktif=%d', $this->option('kering') ? '[kering] ' : '', $h['tambah'], $h['aktifkan'], $h['nonaktif']));

        return self::SUCCESS;
    }
}
