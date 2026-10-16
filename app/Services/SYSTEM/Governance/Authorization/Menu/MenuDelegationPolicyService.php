<?php

namespace App\Services\SYSTEM\Governance\Authorization\Menu;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuDelegationPolicyService
{
    public const TABLE = 'N_HRIS_Menu_Delegation_Policy';

    public function policiesForGroup(User $user, int $grupId): Collection
    {
        $isSystemAdmin = ! empty(AuthorizationScopeResolver::resolve($user)['isSystemAdmin']);
        $roleIds = $this->activeRoleIdsForGroup($user, $grupId);
        $policies = SkemaTersedia::tabel(self::TABLE)
            ? DB::table(self::TABLE)->where('Id_Grup_Fitur', $grupId)->where('Flag_Aktif', 'Y')->get()->keyBy('Jenis_page')
            : collect();

        $restrictedPages = $policies->where('Policy_Mode', 'RESTRICTED')->keys();
        $allowedRestricted = $roleIds->isEmpty() || $restrictedPages->isEmpty()
            ? collect()
            : DB::table('N_HRIS_Role_Resource_Access')
                ->whereIn('Id_Role_Functional', $roleIds->all())
                ->where('Context_Code', 'admin')
                ->where('Jenis_page', 'menuApprovalMatrixPage')
                ->where('Resource_Type', 'menu-delegation')
                ->whereIn('Resource_Code', $restrictedPages->all())
                ->where('Flag_Diizinkan', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->pluck('Resource_Code')
                ->unique();

        return DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y')
            ->select('m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label')
            ->get()
            ->map(function ($menu) use ($policies, $isSystemAdmin, $allowedRestricted) {
                $mode = strtoupper((string) ($policies->get($menu->Jenis_page)->Policy_Mode ?? 'OPEN'));
                // RBAC Fase 0: halaman pengatur akses hanya didelegasikan System
                // Admin, apa pun kebijakan barisnya (satu sumber: HibahTataKelola).
                $tataKelola = HibahTataKelola::apakahHalamanTataKelola((string) $menu->Jenis_page);
                $canDelegate = $isSystemAdmin
                    || (! $tataKelola && ($mode === 'OPEN'
                        || ($mode === 'RESTRICTED' && $allowedRestricted->contains($menu->Jenis_page))));

                $menu->policy_mode = $mode;
                $menu->can_delegate = $canDelegate;
                $menu->lock_reason = $canDelegate ? null : ($tataKelola || $mode === 'SYSTEM_ONLY'
                    ? 'Hanya System Admin yang dapat mengubah menu ini.'
                    : 'Role Anda tidak memiliki hak delegasi menu ini.');

                return $menu;
            });
    }

    public function configurationForGroup(User $user, int $grupId): array
    {
        abort_unless(! empty(AuthorizationScopeResolver::resolve($user)['isSystemAdmin']), 403, 'Hanya System Admin yang dapat mengatur kebijakan delegasi menu.');

        $groupRoleIds = DB::table('N_HRIS_Role_Functional')
            ->where('Id_Grup_Fitur', $grupId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Role');

        $items = $this->policiesForGroup($user, $grupId)->map(function ($menu) use ($groupRoleIds) {
            $allowedRoleIds = SkemaTersedia::tabel('N_HRIS_Role_Resource_Access')
                ? DB::table('N_HRIS_Role_Resource_Access')
                    ->whereIn('Id_Role_Functional', $groupRoleIds->all())
                    ->where('Context_Code', 'admin')
                    ->where('Jenis_page', 'menuApprovalMatrixPage')
                    ->where('Resource_Type', 'menu-delegation')
                    ->where('Resource_Code', $menu->Jenis_page)
                    ->where('Flag_Diizinkan', 'Y')
                    ->where('Flag_Aktif', 'Y')
                    ->pluck('Id_Role_Functional')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                : collect();

            return [
                'jenis_page' => $menu->Jenis_page,
                'nama_menu' => $menu->Nama_Menu,
                'header_label' => $menu->Header_Label,
                'mode' => $menu->policy_mode,
                'allowed_role_ids' => $allowedRoleIds,
            ];
        })->values();

        return [
            'ddl_ready' => SkemaTersedia::tabel(self::TABLE),
            'items' => $items,
            'roles' => DB::table('N_HRIS_Role_Functional')
                ->where('Id_Grup_Fitur', $grupId)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Role_Name')
                ->get(['Id_Role as id', 'Role_Code as code', 'Role_Name as name']),
        ];
    }

    public function assertPagesAllowed(User $user, int $grupId, array|Collection $pages): void
    {
        $requested = collect($pages)->map(fn ($page) => trim((string) $page))->filter()->unique();
        $policies = $this->policiesForGroup($user, $grupId);
        $outside = $requested->diff($policies->pluck('Jenis_page'));
        abort_if($outside->isNotEmpty(), 422, 'Menu di luar grup aktif: '.$outside->implode(', '));

        $locked = $policies
            ->whereIn('Jenis_page', $requested->all())
            ->where('can_delegate', false)
            ->pluck('Nama_Menu');

        abort_if($locked->isNotEmpty(), 403, 'Anda tidak berwenang mengubah menu: '.$locked->implode(', '));
    }

    public function savePolicies(User $user, int $grupId, array $items): void
    {
        abort_unless(! empty(AuthorizationScopeResolver::resolve($user)['isSystemAdmin']), 403, 'Hanya System Admin yang dapat mengatur kebijakan delegasi menu.');
        abort_unless(SkemaTersedia::tabel(self::TABLE), 422, 'Tabel kebijakan delegasi menu belum tersedia. Jalankan DDL terlebih dahulu.');

        DB::transaction(function () use ($user, $grupId, $items) {
            $groupRoleIds = DB::table('N_HRIS_Role_Functional')
                ->where('Id_Grup_Fitur', $grupId)
                ->pluck('Id_Role');

            foreach ($items as $item) {
                $page = trim((string) $item['jenis_page']);
                $mode = strtoupper(trim((string) $item['mode']));
                $roleIds = collect($item['allowed_role_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

                abort_unless(in_array($mode, ['OPEN', 'RESTRICTED', 'SYSTEM_ONLY'], true), 422, 'Mode kebijakan delegasi tidak valid.');
                abort_unless(DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $grupId)->where('Jenis_page', $page)->exists(), 422, 'Menu tidak terikat pada grup aktif.');

                DB::table(self::TABLE)->updateOrInsert(
                    ['Id_Grup_Fitur' => $grupId, 'Jenis_page' => $page],
                    [
                        'Policy_Mode' => $mode,
                        'Flag_Aktif' => 'Y',
                        'Updated_By' => $user->getKey(),
                        'Updated_At' => now(),
                        'Created_At' => now(),
                    ],
                );

                DB::table('N_HRIS_Role_Resource_Access')
                    ->whereIn('Id_Role_Functional', $groupRoleIds->all())
                    ->where('Context_Code', 'admin')
                    ->where('Jenis_page', 'menuApprovalMatrixPage')
                    ->where('Resource_Type', 'menu-delegation')
                    ->where('Resource_Code', $page)
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

                if ($mode === 'RESTRICTED') {
                    $validRoleIds = DB::table('N_HRIS_Role_Functional')
                        ->where('Id_Grup_Fitur', $grupId)
                        ->where('Flag_Aktif', 'Y')
                        ->whereIn('Id_Role', $roleIds->all())
                        ->pluck('Id_Role');

                    foreach ($validRoleIds as $roleId) {
                        DB::table('N_HRIS_Role_Resource_Access')->insert([
                            'Id_Role_Functional' => $roleId,
                            'Context_Code' => 'admin',
                            'Jenis_page' => 'menuApprovalMatrixPage',
                            'Resource_Type' => 'menu-delegation',
                            'Resource_Code' => $page,
                            'Resource_Name' => 'Delegasi menu '.$page,
                            'Flag_Diizinkan' => 'Y',
                            'Flag_Aktif' => 'Y',
                            'Sort_Order' => 0,
                            'Created_At' => now(),
                            'Updated_At' => now(),
                        ]);
                    }
                }
            }
        });
    }

    private function activeRoleIdsForGroup(User $user, int $grupId): Collection
    {
        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.UserId_Web', $user->getKey())
            ->where('ru.Flag_Aktif', 'Y')
            ->tap(fn ($q) => \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->where('rf.Flag_Aktif', 'Y')
            ->where('rf.Id_Grup_Fitur', $grupId)
            ->pluck('rf.Id_Role')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
