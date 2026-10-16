<?php

namespace App\Services\ESS\Dashboard\Homepage;

use Illuminate\Support\Facades\DB;

class HomepageRoleService
{
    /**
     * Dapatkan detail role dan tim berdasarkan Kode_Karyawan
     */
    public function getRoleDetails(string $kodeKaryawan): array
    {
        $hasTeamRelationship = DB::table('HRIS_Karyawan_Team')->where('Kode_Karyawan_Team', $kodeKaryawan)->exists();

        $teamCount = DB::table('HRIS_Karyawan_Team as team')
            ->join('Karyawan as k', 'team.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('team.Kode_Karyawan_Team', $kodeKaryawan)
            ->where('team.Kode_Karyawan', '<>', $kodeKaryawan)
            ->where('k.Aktif', 'Y')
            ->distinct()
            ->count('team.Kode_Karyawan');

        $isLeader = $hasTeamRelationship;
        $role = 'employee';

        if ($isLeader) {
            $role = 'user';
        }

        $app_env = strtolower(config('services.project_config.app_env'));
        if (($kodeKaryawan == 'H45' || $kodeKaryawan == 'RIDHO RAHMAT') && $app_env == 'production') {
            $isLeader = false;
            $role = 'user';
            $teamCount = 0;
        }

        return [
            'is_leader' => $isLeader,
            'role' => $role,
            'team_count' => $teamCount,
        ];
    }
}
