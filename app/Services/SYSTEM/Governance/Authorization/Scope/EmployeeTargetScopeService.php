<?php

namespace App\Services\SYSTEM\Governance\Authorization\Scope;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeTargetScopeService
{
    private const GLOBAL_SCOPE_CODES = ['GLOBAL_ALL', 'SCOPE_HC_ALL_ACTIVE'];

    private const SUPPORTED_SCOPE_CODES = [
        'GLOBAL_ALL',
        'SCOPE_HC_ALL_ACTIVE',
        'SCOPE_GA_PHL',
        'DIVISIONAL_FILTER',
        'LEADER_DYNAMIC_TEAM',
    ];

    public function resolve(User $user, int $grupId): array
    {
        $authScope = AuthorizationScopeResolver::resolve($user);
        if (! empty($authScope['isSystemAdmin'])) {
            return [
                'mode' => 'SYSTEM',
                'is_system_admin' => true,
                'scope_codes' => ['GLOBAL_ALL'],
                'labels' => ['Seluruh karyawan aktif'],
                'unsupported_codes' => [],
            ];
        }

        $rows = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($join) use ($grupId) {
                $join->on('rf.Id_Role', '=', 'ru.Id_Role')
                    ->where('rf.Flag_Aktif', 'Y')
                    ->where('rf.Id_Grup_Fitur', $grupId);
            })
            ->join('N_HRIS_Role_Scope as rs', function ($join) {
                $join->on('rs.Id_Role', '=', 'rf.Id_Role')->where('rs.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Scope_Definition as sd', function ($join) {
                $join->on('sd.Id_Scope', '=', 'rs.Id_Scope')->where('sd.Flag_Aktif', 'Y');
            })
            ->where('ru.UserId_Web', $user->getKey())
            ->where('ru.Flag_Aktif', 'Y')
            ->select('sd.Scope_Code', 'sd.Scope_Name')
            ->distinct()
            ->get();

        $codes = $rows->pluck('Scope_Code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        $unsupported = $codes->diff(self::SUPPORTED_SCOPE_CODES)->values();
        $legacyGlobal = $codes->isEmpty() && ! config('approval_matrix.enforce_target_scope', false);

        return [
            'mode' => $legacyGlobal ? 'LEGACY_GLOBAL' : ($codes->isEmpty() ? 'DENIED' : 'SCOPED'),
            'is_system_admin' => false,
            'scope_codes' => $codes->all(),
            'labels' => $legacyGlobal
                ? ['Legacy global · scope belum dikonfigurasi']
                : $rows->pluck('Scope_Name')->filter()->unique()->values()->all(),
            'unsupported_codes' => $unsupported->all(),
        ];
    }

    public function metadata(User $user, int $grupId): array
    {
        $resolved = $this->resolve($user, $grupId);
        $query = DB::table('Karyawan as k')->where('k.Aktif', 'Y');
        $this->applyToQuery($query, $user, $grupId, 'k');

        return [
            'mode' => $resolved['mode'],
            'labels' => $resolved['labels'],
            'scope_codes' => $resolved['scope_codes'],
            'unsupported_codes' => $resolved['unsupported_codes'],
            'target_count' => $query->distinct()->count('k.UserID_Web'),
        ];
    }

    public function applyToQuery(Builder $query, User $user, int $grupId, string $employeeAlias = 'k'): Builder
    {
        $resolved = $this->resolve($user, $grupId);
        if ($resolved['is_system_admin']
            || $resolved['mode'] === 'LEGACY_GLOBAL'
            || collect($resolved['scope_codes'])->intersect(self::GLOBAL_SCOPE_CODES)->isNotEmpty()) {
            return $query;
        }

        $supported = collect($resolved['scope_codes'])->intersect(self::SUPPORTED_SCOPE_CODES)->values();
        if ($supported->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $actor = $this->actorProfile($user);
        $applicable = $supported->filter(fn (string $scopeCode) => match ($scopeCode) {
            'SCOPE_GA_PHL' => true,
            'DIVISIONAL_FILTER' => $actor['division_sub_id'] !== null,
            'LEADER_DYNAMIC_TEAM' => $actor['employee_code'] !== null && SkemaTersedia::tabel('HRIS_Karyawan_Team'),
            default => false,
        })->values();
        if ($applicable->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($applicable, $employeeAlias, $actor) {
            foreach ($applicable as $scopeCode) {
                if ($scopeCode === 'SCOPE_GA_PHL') {
                    $scopeQuery->orWhereExists(function (Builder $levelQuery) use ($employeeAlias) {
                        $levelQuery->selectRaw('1')
                            ->from('View_Golongan_Sub_Golongan_Level_Jabatan as target_level')
                            ->whereColumn('target_level.ID_Level_Jabatan', $employeeAlias.'.ID_Level_Jabatan')
                            ->where('target_level.ID_Level', 21);
                    });
                } elseif ($scopeCode === 'DIVISIONAL_FILTER' && $actor['division_sub_id'] !== null) {
                    $scopeQuery->orWhere($employeeAlias.'.ID_Divisi_Sub_Divisi', $actor['division_sub_id']);
                } elseif ($scopeCode === 'LEADER_DYNAMIC_TEAM' && $actor['employee_code'] !== null) {
                    $scopeQuery->orWhereExists(function (Builder $teamQuery) use ($employeeAlias, $actor) {
                        $teamQuery->selectRaw('1')
                            ->from('HRIS_Karyawan_Team as target_team')
                            ->whereColumn('target_team.Kode_Karyawan', $employeeAlias.'.Kode_Karyawan')
                            ->where('target_team.Kode_Karyawan_Team', $actor['employee_code']);
                    });
                }
            }
        });
    }

    public function assertUserIdsAllowed(User $user, int $grupId, array|Collection $userIds): void
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $query = DB::table('Karyawan as k')->whereIn('k.UserID_Web', $ids->all())->where('k.Aktif', 'Y');
        $this->applyToQuery($query, $user, $grupId, 'k');
        $allowed = $query->pluck('k.UserID_Web')->map(fn ($id) => (int) $id);
        $denied = $ids->diff($allowed);

        abort_if($denied->isNotEmpty(), 403, 'Terdapat karyawan di luar target scope Anda.');
    }

    public function assertEmployeeCodesAllowed(User $user, int $grupId, array|Collection $employeeCodes): void
    {
        $codes = collect($employeeCodes)->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return;
        }

        $query = DB::table('Karyawan as k')->whereIn('k.Kode_Karyawan', $codes->all())->where('k.Aktif', 'Y');
        $this->applyToQuery($query, $user, $grupId, 'k');
        $allowed = $query->pluck('k.Kode_Karyawan');
        $denied = $codes->diff($allowed);

        abort_if($denied->isNotEmpty(), 403, 'Terdapat karyawan di luar target scope Anda: '.$denied->implode(', '));
    }

    public function readiness(): Collection
    {
        return DB::table('N_HRIS_Role_Functional as rf')
            ->leftJoin('N_HRIS_Role_Scope as rs', function ($join) {
                $join->on('rs.Id_Role', '=', 'rf.Id_Role')->where('rs.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_HRIS_Scope_Definition as sd', function ($join) {
                $join->on('sd.Id_Scope', '=', 'rs.Id_Scope')->where('sd.Flag_Aktif', 'Y');
            })
            ->where('rf.Flag_Aktif', 'Y')
            ->whereExists(function (Builder $query) {
                $query->selectRaw('1')
                    ->from('N_HRIS_Role_Resource_Access as rra')
                    ->whereColumn('rra.Id_Role_Functional', 'rf.Id_Role')
                    ->where('rra.Jenis_page', 'menuApprovalMatrixPage')
                    ->where('rra.Flag_Aktif', 'Y')
                    ->where('rra.Flag_Diizinkan', 'Y');
            })
            ->select('rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', 'rf.Id_Grup_Fitur', 'sd.Scope_Code', 'sd.Scope_Name')
            ->get()
            ->groupBy('Id_Role')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $codes = $rows->pluck('Scope_Code')->filter()->unique()->values();

                return [
                    'role_id' => (int) $first->Id_Role,
                    'role_code' => $first->Role_Code,
                    'role_name' => $first->Role_Name,
                    'grup_id' => (int) $first->Id_Grup_Fitur,
                    'scope_codes' => $codes,
                    'scope_names' => $rows->pluck('Scope_Name')->filter()->unique()->values(),
                    'ready' => $codes->isNotEmpty() && $codes->diff(self::SUPPORTED_SCOPE_CODES)->isEmpty(),
                ];
            })
            ->values();
    }

    private function actorProfile(User $user): array
    {
        $row = DB::table('Karyawan')
            ->where('UserID_Web', $user->getKey())
            ->first(['Kode_Karyawan', 'ID_Divisi_Sub_Divisi']);

        return [
            'employee_code' => $row?->Kode_Karyawan ? trim((string) $row->Kode_Karyawan) : null,
            'division_sub_id' => $row?->ID_Divisi_Sub_Divisi !== null ? (int) $row->ID_Divisi_Sub_Divisi : null,
        ];
    }
}
