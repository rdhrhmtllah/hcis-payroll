<?php

namespace App\Services\SYSTEM\Governance\Authorization\Menu;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalMatrixService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menu binding (N_HRIS_Grup_Fitur_Map) yang di-scope per grup kelolaan user.
 *
 * Berbeda dengan SystemAuthController::saveMenuBinding (delete-insert buta),
 * save di sini berbasis diff: baris yang tetap dipertahankan apa adanya
 * (Sort_Order / Is_Show_Sidebar / Is_Default_Landing / Flag_Aktif tidak hilang),
 * hanya baris yang dilepas yang dihapus dan baris baru yang ditambahkan.
 */
class MenuBindingService
{
    public function __construct(
        private readonly ApprovalMatrixService $matrixService,
        private readonly EmployeeTargetScopeService $targetScopeService,
        private readonly MenuDelegationPolicyService $delegationPolicyService,
    ) {}

    public function getBinding(User $user, int $grupId, ?int $targetUserId = null): array
    {
        $this->assertCanView($user, $grupId);

        // Fetch menus bound to the group
        $query = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $menuPolicies = $this->delegationPolicyService->policiesForGroup($user, $grupId)->keyBy('Jenis_page');
        $allMenus = $query->select('m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label')
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get()
            ->map(function ($menu) use ($menuPolicies) {
                $policy = $menuPolicies->get($menu->Jenis_page);
                $menu->policy_mode = $policy?->policy_mode ?? 'OPEN';
                $menu->can_delegate = $policy?->can_delegate ?? true;
                $menu->lock_reason = $policy?->lock_reason;

                return $menu;
            });

        $boundPages = [];
        $employeeDetails = null;
        if ($targetUserId !== null) {
            $this->targetScopeService->assertUserIdsAllowed($user, $grupId, [$targetUserId]);
            $groupPages = $allMenus->pluck('Jenis_page')->all();
            if (! empty($groupPages)) {
                $boundPages = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $targetUserId)
                    ->where('Flag_Aktif', 'Y')
                    ->whereIn('Jenis_page', $groupPages)
                    ->pluck('Jenis_page')
                    ->all();
            }

            // Fetch target employee profile details
            $emp = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
                ->where('k.UserID_Web', $targetUserId)
                ->select('k.Nama', 'k.Kode_Karyawan', 'k.UserId_Absen as NIK', 'd.nama_divisi', 'l.nama_level')
                ->first();

            if ($emp) {
                // Fetch active functional roles
                $roles = DB::table('N_HRIS_Role_User as ru')
                    ->join('N_HRIS_Role_Functional as rf', 'ru.Id_Role', '=', 'rf.Id_Role')
                    ->where('ru.UserId_Web', $targetUserId)
                    ->where('ru.Flag_Aktif', 'Y')
                    ->where('rf.Flag_Aktif', 'Y')
                    ->pluck('rf.Role_Name')
                    ->toArray();

                $employeeDetails = [
                    'Nama' => $emp->Nama,
                    'Kode_Karyawan' => $emp->Kode_Karyawan,
                    'NIK' => $emp->NIK,
                    'Divisi' => $emp->nama_divisi ?? '—',
                    'Level' => $emp->nama_level ?? '—',
                    'Roles' => $roles,
                ];
            }
        }

        return [
            'allMenus' => $allMenus,
            'boundPages' => $boundPages,
            'employeeDetails' => $employeeDetails,
        ];
    }

    public function saveBinding(User $user, int $grupId, int $targetUserId, array $pages): array
    {
        $this->assertCanAssign($user, $grupId);
        $this->targetScopeService->assertUserIdsAllowed($user, $grupId, [$targetUserId]);

        $karyawan = DB::table('Karyawan')
            ->where('UserID_Web', $targetUserId)
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama', 'UserID_Web']);

        if (! $karyawan) {
            abort(404, 'Karyawan tidak ditemukan atau tidak aktif.');
        }

        $groupPagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $groupPages = $groupPagesQuery->pluck('gfm.Jenis_page');

        $pages = collect($pages)
            ->map(fn ($page) => trim((string) $page))
            ->filter()
            ->unique()
            ->values();

        $outside = $pages->diff($groupPages);
        if ($outside->isNotEmpty()) {
            abort(422, 'Menu di luar grup fitur ini: '.$outside->implode(', '));
        }

        $currentlyGrantedForPolicy = DB::table('HRIS_Page_Access')
            ->where('UserID_Web', $targetUserId)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Jenis_page', $groupPages->all())
            ->pluck('Jenis_page');
        $changedPages = $pages->diff($currentlyGrantedForPolicy)->merge($currentlyGrantedForPolicy->diff($pages))->unique();
        $this->delegationPolicyService->assertPagesAllowed($user, $grupId, $changedPages);

        return DB::transaction(function () use ($user, $grupId, $targetUserId, $pages, $groupPages, $karyawan) {
            $currentlyGranted = empty($groupPages->all())
                ? collect()
                : DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $targetUserId)
                    ->where('Flag_Aktif', 'Y')
                    ->whereIn('Jenis_page', $groupPages)
                    ->pluck('Jenis_page');

            $toGrant = $pages->diff($currentlyGranted);
            $toRevoke = $currentlyGranted->diff($pages);

            $orgSnapshot = $this->resolveOrgSnapshot($karyawan->Kode_Karyawan);

            // Grant access
            foreach ($toGrant as $jenisPage) {
                $exists = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $targetUserId)
                    ->where('Jenis_page', $jenisPage)
                    ->first();

                if ($exists) {
                    $idPageAccess = $exists->Id_Page_Access;
                    DB::table('HRIS_Page_Access')
                        ->where('Id_Page_Access', $idPageAccess)
                        ->update([
                            'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                            'ID_Level' => $orgSnapshot['ID_Level'],
                            'Kode_Perusahaan' => '001',
                            'Updated_At' => now(),
                            'Flag_Aktif' => 'Y',
                        ]);
                } else {
                    $idPageAccess = DB::table('HRIS_Page_Access')->insertGetId([
                        'UserID_Web' => $targetUserId,
                        'Jenis_page' => $jenisPage,
                        'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                        'ID_Level' => $orgSnapshot['ID_Level'],
                        'Kode_Perusahaan' => '001',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                        'Flag_Aktif' => 'Y',
                    ]);
                }

                // Seed only missing capabilities; preserve existing user decisions
                $this->syncDefaultCapabilities($idPageAccess, $jenisPage);
            }

            // Revoke access
            if ($toRevoke->isNotEmpty()) {
                $pageAccessIds = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $targetUserId)
                    ->whereIn('Jenis_page', $toRevoke->all())
                    ->where('Flag_Aktif', 'Y')
                    ->pluck('Id_Page_Access');

                if ($pageAccessIds->isNotEmpty()) {
                    DB::table('N_HRIS_Role_Menu_Access')
                        ->whereIn('Id_Page_Access', $pageAccessIds)
                        ->where('Flag_Aktif', 'Y')
                        ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

                    DB::table('HRIS_Page_Access')
                        ->whereIn('Id_Page_Access', $pageAccessIds)
                        ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
                }
            }

            DB::afterCommit(function () use ($user, $grupId, $targetUserId, $toGrant, $toRevoke): void {
                if ($toGrant->isNotEmpty()) {
                    AuthorizationAuditLogger::logMutation([
                        'event' => 'menu_access.granted',
                        'actor_user_id' => $user->Id_Users ?? $user->id ?? null,
                        'target_user_id' => $targetUserId,
                        'grup_id' => $grupId,
                        'affected_count' => $toGrant->count(),
                        'details' => ['pages' => $toGrant->values()->all()],
                    ]);
                }

                if ($toRevoke->isNotEmpty()) {
                    AuthorizationAuditLogger::logMutation([
                        'event' => 'menu_access.revoked',
                        'actor_user_id' => $user->Id_Users ?? $user->id ?? null,
                        'target_user_id' => $targetUserId,
                        'grup_id' => $grupId,
                        'affected_count' => $toRevoke->count(),
                        'details' => ['pages' => $toRevoke->values()->all()],
                    ]);
                }
            });

            return [
                'di_grant' => $toGrant->values()->all(),
                'di_revoke' => $toRevoke->values()->all(),
            ];
        });
    }

    public function bulkAssign(\App\Models\ESS\Employee\Models\User $user, int $grupId, array $targetUserIds, array $pages): array
    {
        $this->assertCanAssign($user, $grupId);
        $this->targetScopeService->assertUserIdsAllowed($user, $grupId, $targetUserIds);

        // Fetch menus bound to the group
        $groupPagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $groupPages = $groupPagesQuery->pluck('gfm.Jenis_page');

        // Sanitize & validate target pages
        $pages = collect($pages)
            ->map(fn ($page) => trim((string) $page))
            ->filter()
            ->unique()
            ->values();

        $outside = $pages->diff($groupPages);
        if ($outside->isNotEmpty()) {
            abort(422, 'Menu di luar grup fitur ini: '.$outside->implode(', '));
        }
        $this->delegationPolicyService->assertPagesAllowed($user, $grupId, $pages);

        return DB::transaction(function () use ($user, $grupId, $targetUserIds, $pages) {
            $result = [
                'applied' => [],
                'skipped' => [],
                'failed' => [],
            ];

            /*
             * TIGA kueri untuk seluruh karyawan, bukan tiga PER karyawan.
             *
             * Bentuk lama membaca Karyawan, HRIS_Page_Access, dan snapshot
             * organisasi satu per satu di dalam perulangan ini — 100 karyawan
             * berarti 300 perjalanan, seluruhnya DI DALAM transaksi yang
             * mengunci HRIS_Page_Access. Tabel itu dibaca hampir setiap
             * permintaan dan basis data ini berjalan tanpa RCSI, jadi selama
             * penetapan massal berlangsung pengguna lain ikut menunggu hanya
             * untuk tahu halaman apa yang boleh ia buka.
             *
             * Penyisipan per halaman DIBIARKAN apa adanya: ia membaca lalu
             * menulis tabel yang sama di dalam perulangan, dan pra-ambil di
             * sana justru melewatkan baris yang baru lahir iterasi sebelumnya.
             */
            $petaKaryawan = DB::table('Karyawan')
                ->whereIn('UserID_Web', $targetUserIds)
                ->where('Aktif', 'Y')
                ->get(['Kode_Karyawan', 'Nama', 'UserID_Web'])
                ->keyBy('UserID_Web');

            $petaTerpasang = DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $targetUserIds)
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Jenis_page', $pages)
                ->get(['UserID_Web', 'Jenis_page'])
                ->groupBy('UserID_Web');

            $this->siapkanOrgSnapshot(
                $petaKaryawan->pluck('Kode_Karyawan')->filter()->all(),
            );

            foreach ($targetUserIds as $targetUserId) {
                $karyawan = $petaKaryawan[$targetUserId] ?? null;

                if (! $karyawan) {
                    $result['failed'][] = [
                        'user_id' => $targetUserId,
                        'reason' => 'Karyawan tidak ditemukan atau tidak aktif.',
                    ];

                    continue;
                }

                // Diambil dari peta yang sudah dimuat sekali di atas.
                $currentlyGranted = collect($petaTerpasang[$targetUserId] ?? [])
                    ->pluck('Jenis_page');

                $toGrant = $pages->diff($currentlyGranted);

                if ($toGrant->isEmpty()) {
                    $result['skipped'][] = [
                        'user_id' => $targetUserId,
                        'Nama' => $karyawan->Nama,
                        'reason' => 'Karyawan sudah memiliki semua menu yang dipilih.',
                    ];

                    continue;
                }

                $orgSnapshot = $this->resolveOrgSnapshot($karyawan->Kode_Karyawan);
                $addedForThisUser = [];

                foreach ($toGrant as $jenisPage) {
                    $exists = DB::table('HRIS_Page_Access')
                        ->where('UserID_Web', $targetUserId)
                        ->where('Jenis_page', $jenisPage)
                        ->first();

                    if ($exists) {
                        $idPageAccess = $exists->Id_Page_Access;
                        DB::table('HRIS_Page_Access')
                            ->where('Id_Page_Access', $idPageAccess)
                            ->update([
                                'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                                'ID_Level' => $orgSnapshot['ID_Level'],
                                'Kode_Perusahaan' => '001',
                                'Updated_At' => now(),
                                'Flag_Aktif' => 'Y',
                            ]);
                    } else {
                        $idPageAccess = DB::table('HRIS_Page_Access')->insertGetId([
                            'UserID_Web' => $targetUserId,
                            'Jenis_page' => $jenisPage,
                            'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                            'ID_Level' => $orgSnapshot['ID_Level'],
                            'Kode_Perusahaan' => '001',
                            'Created_At' => now(),
                            'Updated_At' => now(),
                            'Flag_Aktif' => 'Y',
                        ]);
                    }

                    // Seed only missing capabilities; preserve existing user decisions
                    $this->syncDefaultCapabilities($idPageAccess, $jenisPage);
                    $addedForThisUser[] = $jenisPage;
                }

                $result['applied'][] = [
                    'user_id' => $targetUserId,
                    'Nama' => $karyawan->Nama,
                    'added' => $addedForThisUser,
                ];
            }

            DB::afterCommit(function () use ($user, $grupId, $result): void {
                foreach ($result['applied'] as $item) {
                    if (empty($item['added'])) {
                        continue;
                    }

                    AuthorizationAuditLogger::logMutation([
                        'event' => 'menu_access.bulk_granted',
                        'actor_user_id' => $user->Id_Users ?? $user->id ?? null,
                        'target_user_id' => $item['user_id'] ?? null,
                        'grup_id' => $grupId,
                        'affected_count' => count($item['added']),
                        'details' => ['pages' => $item['added']],
                    ]);
                }
            });

            return $result;
        });
    }

    public function getHistory(\App\Models\ESS\Employee\Models\User $user, int $grupId): array
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        if (! $capabilities['menu']['canView']) {
            abort(403, 'Anda tidak memiliki akses untuk melihat riwayat menu.');
        }

        $this->matrixService->assertCanManageGrup($user, $grupId);

        $logs = DB::table('HRIS_Transaksi_Log_Admin as t')
            ->join('HRIS_Transaksi_Log_Admin_Detail as d', 't.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('HRIS_Transaksi_Log_Admin_Det as det', 'd.Urut_Oto', '=', 'det.Urut_Detail')
            ->leftJoin('Karyawan as k_op', 't.UserID', '=', 'k_op.Kode_Karyawan')
            ->leftJoin('Karyawan as k_tar', 'd.Kode_Karyawan', '=', 'k_tar.Kode_Karyawan')
            ->where(function ($q) use ($grupId) {
                $q->where('d.Deskripsi', 'like', "%/menu-approval-matrix/menu-binding/{$grupId}")
                    ->orWhere('d.Deskripsi', 'like', "%/menu-approval-matrix/menu-binding/{$grupId}/%")
                    ->orWhere('d.Deskripsi', 'like', "%/system-auth/menu-binding/{$grupId}")
                    ->orWhere('d.Deskripsi', 'like', "%/system-auth/menu-binding/{$grupId}/%");
            });
        $logs->whereExists(function ($query) use ($user, $grupId) {
            $query->selectRaw('1')
                ->from('Karyawan as scoped_target')
                ->whereColumn('scoped_target.Kode_Karyawan', 'd.Kode_Karyawan');
            $this->targetScopeService->applyToQuery($query, $user, $grupId, 'scoped_target');
        });
        $logs = $logs
            ->select([
                't.No_Transaksi',
                't.Tanggal',
                't.Jam',
                't.UserID as Operator_Kode',
                'k_op.Nama as Operator_Nama',
                'd.Kode_Karyawan as Target_Kode',
                'k_tar.Nama as Target_Nama',
                'd.Deskripsi as Action_Path',
                'det.Field_Diubah',
                'det.Nilai_Awal',
                'det.Nilai_Setelah',
            ])
            ->orderBy('t.Tanggal', 'desc')
            ->orderBy('t.Jam', 'desc')
            ->limit(1000)
            ->get();

        $menuNames = DB::table('N_HRIS_Menus')->pluck('Nama_Menu', 'Jenis_page');

        return $logs
            ->groupBy('No_Transaksi')
            ->map(function ($rows, $transactionId) use ($menuNames) {
                $first = $rows->first();
                $targets = $rows
                    ->groupBy('Target_Kode')
                    ->map(function ($targetRows, $targetKode) use ($menuNames) {
                        $target = $targetRows->first();

                        return [
                            'kode' => $targetKode,
                            'nama' => $target->Target_Nama ?: $targetKode,
                            'changes' => $targetRows
                                ->filter(fn ($row) => ! empty($row->Field_Diubah))
                                ->map(fn ($row) => [
                                    'field' => $row->Field_Diubah,
                                    'before' => $row->Nilai_Awal,
                                    'after' => $this->enrichMenuAuditValue($row->Nilai_Setelah, $row->Field_Diubah, $menuNames),
                                ])
                                ->unique(fn ($change) => json_encode($change))
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values();

                $isBulk = $targets->count() > 1 || str_contains(strtolower((string) $first->Action_Path), 'bulk');
                $tanggal = $first->Tanggal instanceof \DateTimeInterface
                    ? $first->Tanggal->format('Y-m-d')
                    : substr((string) $first->Tanggal, 0, 10);

                return [
                    'id' => (string) $transactionId,
                    'transaction_id' => (string) $transactionId,
                    'type' => $isBulk ? 'Bulk Assignment Menu' : 'Perubahan Akses Menu',
                    'actor_code' => $first->Operator_Kode,
                    'actor_name' => $first->Operator_Nama ?: $first->Operator_Kode,
                    'occurred_at' => trim($tanggal.' '.substr((string) $first->Jam, 0, 8)),
                    'target_count' => $targets->count(),
                    'change_count' => $targets->sum(fn ($target) => count($target['changes'])),
                    'summary' => $isBulk
                        ? 'Akses menu beberapa karyawan diperbarui.'
                        : 'Akses menu karyawan diperbarui.',
                    'targets' => $targets->all(),
                ];
            })
            ->take(100)
            ->values()
            ->all();
    }

    private function enrichMenuAuditValue($value, ?string $field, Collection $menuNames)
    {
        if (! str_contains((string) $field, 'menu_access_change')) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return $value;
        }

        foreach (['added', 'removed'] as $key) {
            $decoded[$key] = collect($decoded[$key] ?? [])
                ->map(fn ($page) => [
                    'code' => (string) $page,
                    'name' => $menuNames->get($page) ?: (string) $page,
                ])
                ->values()
                ->all();
        }

        return $decoded;
    }

    public function getSummary(\App\Models\ESS\Employee\Models\User $user, int $grupId): array
    {
        $this->assertCanView($user, $grupId);

        // Fetch menus bound to the group
        $groupPagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $pages = $groupPagesQuery->pluck('gfm.Jenis_page')->toArray();
        $totalMenus = count($pages);

        $activeEmployeeQuery = DB::table('Karyawan as k')->where('k.Aktif', 'Y');
        $this->targetScopeService->applyToQuery($activeEmployeeQuery, $user, $grupId, 'k');
        $totalActiveEmployees = $activeEmployeeQuery->distinct()->count('k.UserID_Web');

        $employeesWithAccess = 0;
        if (! empty($pages)) {
            $employeesWithAccessQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->where('k.Aktif', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($employeesWithAccessQuery, $user, $grupId, 'k');
            $employeesWithAccess = $employeesWithAccessQuery->distinct('pa.UserID_Web')->count('pa.UserID_Web');
        }

        $employeesWithoutAccess = $totalActiveEmployees - $employeesWithAccess;

        $logsTodayQuery = DB::table('HRIS_Transaksi_Log_Admin as t')
            ->join('HRIS_Transaksi_Log_Admin_Detail as d', 't.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as scoped_target', 'scoped_target.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->whereDate('t.Tanggal', date('Y-m-d'))
            ->where(function ($q) use ($grupId) {
                $q->where('d.Deskripsi', 'like', "%/menu-approval-matrix/menu-binding/{$grupId}%")
                    ->orWhere('d.Deskripsi', 'like', "%/system-auth/menu-binding/{$grupId}%");
            });
        $this->targetScopeService->applyToQuery($logsTodayQuery, $user, $grupId, 'scoped_target');
        $logsToday = $logsTodayQuery->count();

        $menuPopularity = [];
        if (! empty($pages)) {
            $popularityQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->join('N_HRIS_Menus as m', 'pa.Jenis_page', '=', 'm.Jenis_page')
                ->where('k.Aktif', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($popularityQuery, $user, $grupId, 'k');
            $popularityQuery = $popularityQuery
                ->select('pa.Jenis_page', 'm.Nama_Menu', DB::raw('count(distinct pa.UserID_Web) as employee_count'))
                ->groupBy('pa.Jenis_page', 'm.Nama_Menu')
                ->orderBy('employee_count', 'desc')
                ->get();

            $assignedPages = $popularityQuery->pluck('Jenis_page')->toArray();
            $unassignedPages = array_diff($pages, $assignedPages);

            $unassignedMenus = DB::table('N_HRIS_Menus')
                ->whereIn('Jenis_page', $unassignedPages)
                ->select('Jenis_page', 'Nama_Menu')
                ->get()
                ->map(fn ($m) => (object) [
                    'Jenis_page' => $m->Jenis_page,
                    'Nama_Menu' => $m->Nama_Menu,
                    'employee_count' => 0,
                ]);

            $menuPopularity = $popularityQuery->concat($unassignedMenus)
                ->sortByDesc('employee_count')
                ->values()
                ->toArray();
        }

        $staleCount = 0;
        $staleEmployees = [];
        if (! empty($pages)) {
            $staleQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->where('k.Aktif', '!=', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($staleQuery, $user, $grupId, 'k');
            $staleCount = $staleQuery->distinct('pa.UserID_Web')->count('pa.UserID_Web');

            $staleEmployeesQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
                ->where('k.Aktif', '!=', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($staleEmployeesQuery, $user, $grupId, 'k');
            $staleEmployees = $staleEmployeesQuery
                ->select(
                    'k.UserID_Web',
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'k.UserId_Absen as NIK',
                    'd.nama_divisi as Divisi',
                    'l.nama_level as Level'
                )
                ->distinct()
                ->get()
                ->toArray();
        }

        $divisionCoverage = [];
        if (! empty($pages)) {
            $divisionCoverageQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('k.Aktif', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($divisionCoverageQuery, $user, $grupId, 'k');
            $divisionCoverage = $divisionCoverageQuery
                ->select('d.nama_divisi as name', DB::raw('count(distinct k.UserID_Web) as count'))
                ->groupBy('d.nama_divisi')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->toArray();
        }

        $headerDistribution = [];
        if (! empty($pages)) {
            $allHeaders = DB::table('N_HRIS_Menus')
                ->whereIn('Jenis_page', $pages)
                ->distinct()
                ->pluck('Header_Label')
                ->filter()
                ->toArray();

            $distQuery = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->join('N_HRIS_Menus as m', 'pa.Jenis_page', '=', 'm.Jenis_page')
                ->where('k.Aktif', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages);
            $this->targetScopeService->applyToQuery($distQuery, $user, $grupId, 'k');
            $distQuery = $distQuery
                ->select('m.Header_Label as name', DB::raw('count(distinct k.UserID_Web) as count'))
                ->groupBy('m.Header_Label')
                ->get();

            $activeHeaders = $distQuery->pluck('name')->toArray();
            $inactiveHeaders = array_diff($allHeaders, $activeHeaders);

            $inactiveList = [];
            foreach ($inactiveHeaders as $ih) {
                $inactiveList[] = (object) [
                    'name' => $ih,
                    'count' => 0,
                ];
            }

            $headerDistribution = $distQuery->concat($inactiveList)
                ->sortByDesc('count')
                ->values()
                ->toArray();
        }

        $recentActivity = array_slice($this->getHistory($user, $grupId), 0, 5);

        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $weeklyQuery = DB::table('HRIS_Transaksi_Log_Admin as t')
                ->join('HRIS_Transaksi_Log_Admin_Detail as d', 't.No_Transaksi', '=', 'd.No_Transaksi')
                ->join('Karyawan as scoped_target', 'scoped_target.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                ->whereDate('t.Tanggal', $date)
                ->where(function ($q) use ($grupId) {
                    $q->where('d.Deskripsi', 'like', "%/menu-approval-matrix/menu-binding/{$grupId}%")
                        ->orWhere('d.Deskripsi', 'like', "%/system-auth/menu-binding/{$grupId}%");
                });
            $this->targetScopeService->applyToQuery($weeklyQuery, $user, $grupId, 'scoped_target');
            $count = $weeklyQuery->count();

            $weeklyTrend[] = [
                'date' => date('d M', strtotime($date)),
                'count' => $count,
            ];
        }

        return [
            'stats' => [
                'totalMenus' => $totalMenus,
                'employeesWithAccess' => $employeesWithAccess,
                'employeesWithoutAccess' => $employeesWithoutAccess,
                'actionsToday' => $logsToday,
                'staleCount' => $staleCount,
            ],
            'menuPopularity' => $menuPopularity,
            'divisionCoverage' => $divisionCoverage,
            'headerDistribution' => $headerDistribution,
            'recentActivity' => $recentActivity,
            'weeklyTrend' => $weeklyTrend,
            'staleEmployees' => $staleEmployees,
            'target_scope' => $this->targetScopeService->metadata($user, $grupId),
        ];
    }

    public function getIssueCount(\App\Models\ESS\Employee\Models\User $user, int $grupId): int
    {
        $this->assertCanView($user, $grupId);
        $pagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $pages = $pagesQuery->pluck('gfm.Jenis_page')->all();
        if (empty($pages)) {
            return 0;
        }

        $activeEmployeeQuery = DB::table('Karyawan as k')->where('k.Aktif', 'Y');
        $this->targetScopeService->applyToQuery($activeEmployeeQuery, $user, $grupId, 'k');
        $activeEmployees = $activeEmployeeQuery->distinct()->count('k.UserID_Web');
        $employeesWithAccessQuery = DB::table('HRIS_Page_Access as pa')
            ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where('pa.Flag_Aktif', 'Y')
            ->whereIn('pa.Jenis_page', $pages);
        $this->targetScopeService->applyToQuery($employeesWithAccessQuery, $user, $grupId, 'k');
        $employeesWithAccess = $employeesWithAccessQuery->distinct('pa.UserID_Web')->count('pa.UserID_Web');
        $staleEmployeesQuery = DB::table('HRIS_Page_Access as pa')
            ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
            ->where('k.Aktif', '!=', 'Y')
            ->where('pa.Flag_Aktif', 'Y')
            ->whereIn('pa.Jenis_page', $pages);
        $this->targetScopeService->applyToQuery($staleEmployeesQuery, $user, $grupId, 'k');
        $staleEmployees = $staleEmployeesQuery->distinct('pa.UserID_Web')->count('pa.UserID_Web');

        $issueCount = 0;
        if ($activeEmployees > $employeesWithAccess) {
            $issueCount++;
        }
        if ($staleEmployees > 0) {
            $issueCount++;
        }

        return $issueCount;
    }

    /**
     * Daftar karyawan aktif yang memiliki minimal satu menu aktif pada header
     * tertentu di dalam grup fitur yang sedang dikelola.
     */
    public function getHeaderUsers(\App\Models\ESS\Employee\Models\User $user, int $grupId, string $headerName): array
    {
        $this->assertCanView($user, $grupId);

        $headerName = trim($headerName);
        if ($headerName === '') {
            abort(422, 'Nama header wajib diisi.');
        }

        $groupPagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y')
            ->where('m.Header_Label', $headerName);

        $headerPages = $groupPagesQuery
            ->select('m.Jenis_page', 'm.Nama_Menu')
            ->distinct()
            ->get();

        if ($headerPages->isEmpty()) {
            abort(404, 'Header tidak ditemukan pada grup fitur ini atau berada di luar akses Anda.');
        }

        $menuByPage = $headerPages->keyBy('Jenis_page');
        $rowsQuery = DB::table('HRIS_Page_Access as pa')
            ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
            ->where('pa.Flag_Aktif', 'Y')
            ->where('k.Aktif', 'Y')
            ->whereIn('pa.Jenis_page', $headerPages->pluck('Jenis_page')->all());
        $this->targetScopeService->applyToQuery($rowsQuery, $user, $grupId, 'k');
        $rows = $rowsQuery
            ->select(
                'k.UserID_Web',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserId_Absen as NIK',
                'd.nama_divisi as Divisi',
                'l.nama_level as Level',
                'pa.Jenis_page',
            )
            ->orderBy('k.Nama')
            ->get();

        return $rows
            ->groupBy('UserID_Web')
            ->map(function (Collection $userRows) use ($menuByPage) {
                $employee = $userRows->first();

                return [
                    'UserID_Web' => $employee->UserID_Web,
                    'Kode_Karyawan' => $employee->Kode_Karyawan,
                    'Nama' => $employee->Nama,
                    'NIK' => $employee->NIK,
                    'Divisi' => $employee->Divisi ?: '—',
                    'Level' => $employee->Level ?: '—',
                    'Pages' => $userRows
                        ->pluck('Jenis_page')
                        ->unique()
                        ->map(function ($jenisPage) use ($menuByPage) {
                            $menu = $menuByPage->get($jenisPage);

                            return [
                                'Jenis_page' => $jenisPage,
                                'Nama_Menu' => $menu?->Nama_Menu ?: $jenisPage,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('Nama')
            ->values()
            ->all();
    }

    public function purgeStaleBinding(\App\Models\ESS\Employee\Models\User $user, int $grupId, array $targetUserIds): array
    {
        $this->assertCanAssign($user, $grupId);

        $groupPagesQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');

        $pages = $groupPagesQuery->pluck('gfm.Jenis_page')->toArray();

        if (empty($pages) || empty($targetUserIds)) {
            return ['purged_count' => 0];
        }
        $this->targetScopeService->assertUserIdsAllowed($user, $grupId, $targetUserIds);

        return DB::transaction(function () use ($targetUserIds, $pages) {
            $pageAccessIds = DB::table('HRIS_Page_Access as pa')
                ->join('Karyawan as k', 'pa.UserID_Web', '=', 'k.UserID_Web')
                ->where('k.Aktif', '!=', 'Y')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.UserID_Web', $targetUserIds)
                ->whereIn('pa.Jenis_page', $pages)
                ->pluck('pa.Id_Page_Access');

            if ($pageAccessIds->isNotEmpty()) {
                DB::table('N_HRIS_Role_Menu_Access')
                    ->whereIn('Id_Page_Access', $pageAccessIds)
                    ->where('Flag_Aktif', 'Y')
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

                DB::table('HRIS_Page_Access')
                    ->whereIn('Id_Page_Access', $pageAccessIds)
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
            }

            return [
                'purged_count' => count($pageAccessIds),
            ];
        });
    }

    /**
     * Seed menu capability defaults without overwriting an existing decision.
     * Existing rows may contain an explicit user deny/grant and must survive
     * page reactivation or menu re-grant. Newly introduced capabilities are
     * still seeded from the current menu default.
     */
    private function syncDefaultCapabilities(int $idPageAccess, string $jenisPage): void
    {
        $caps = DB::table('N_HRIS_Menu_Capabilities')
            ->where('Jenis_page', $jenisPage)
            ->where('Flag_Aktif', 'Y')
            ->get();

        foreach ($caps as $cap) {
            $existingRoleAccess = DB::table('N_HRIS_Role_Menu_Access')
                ->where('Id_Page_Access', $idPageAccess)
                ->where('Id_Aksi', $cap->Id_Aksi)
                ->first();

            if ($existingRoleAccess) {
                DB::table('N_HRIS_Role_Menu_Access')
                    ->where('Id_Role_Menu_Access', $existingRoleAccess->Id_Role_Menu_Access)
                    ->update([
                        // Deliberately preserve Flag_Diizinkan.
                        'Flag_Aktif' => 'Y',
                        'Updated_At' => now(),
                    ]);

                continue;
            }

            DB::table('N_HRIS_Role_Menu_Access')->insert([
                'Id_Page_Access' => $idPageAccess,
                'Id_Aksi' => $cap->Id_Aksi,
                'Flag_Diizinkan' => $cap->Is_Active_Default,
                'Flag_Access_Konten' => null,
                'Created_At' => now(),
                'Updated_At' => now(),
                'Flag_Aktif' => 'Y',
            ]);
        }
    }

    /**
     * Divisi & level karyawan, dibaca SEKALI per kode selama satu permintaan.
     *
     * Bentuk lama menembak satu JOIN tiga tabel per karyawan, dan pemanggilnya
     * adalah perulangan atas karyawan pada penetapan menu MASSAL — jadi
     * biayanya persis sebanyak orang yang ditetapkan.
     *
     * Memo saja tak menolong di sini: perulangannya memang atas karyawan yang
     * berbeda-beda, jadi tak ada yang berulang untuk di-cache. Yang menolong
     * siapkanOrgSnapshot(): satu kueri untuk SELURUH kode sebelum perulangan
     * dimulai. Memo tetap ada sebagai jaring — kode yang belum ter-prime tetap
     * dijawab, hanya dengan kueri sendiri.
     *
     * @var array<string,array{ID_Divisi:mixed,ID_Level:mixed}>
     */
    private array $memoOrgSnapshot = [];

    /**
     * Satu kueri untuk semua kode sekaligus.
     *
     * @param  list<string>  $kodeKaryawan
     */
    private function siapkanOrgSnapshot(array $kodeKaryawan): void
    {
        $belum = array_values(array_diff(
            array_filter(array_map('strval', $kodeKaryawan)),
            array_keys($this->memoOrgSnapshot),
        ));

        if ($belum === []) {
            return;
        }

        // Batas 2.100 parameter SQL Server: dipotong, bukan diandalkan kecil.
        foreach (array_chunk($belum, 1000) as $potongan) {
            $baris = DB::table('Karyawan as a')
                ->leftJoin('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
                ->whereIn('a.Kode_Karyawan', $potongan)
                ->get(['a.Kode_Karyawan', 'b.ID_Divisi', 'c.ID_Level'])
                ->keyBy(fn ($r) => trim((string) $r->Kode_Karyawan));

            foreach ($potongan as $k) {
                $r = $baris[trim($k)] ?? null;
                $this->memoOrgSnapshot[$k] = [
                    'ID_Divisi' => $r->ID_Divisi ?? null,
                    'ID_Level' => $r->ID_Level ?? null,
                ];
            }
        }
    }

    private function resolveOrgSnapshot(string $kodeKaryawan): array
    {
        if (array_key_exists($kodeKaryawan, $this->memoOrgSnapshot)) {
            return $this->memoOrgSnapshot[$kodeKaryawan];
        }

        $row = DB::table('Karyawan as a')
            ->leftJoin('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
            ->where('a.Kode_Karyawan', $kodeKaryawan)
            ->first(['b.ID_Divisi', 'c.ID_Level']);

        return $this->memoOrgSnapshot[$kodeKaryawan] = [
            'ID_Divisi' => $row->ID_Divisi ?? null,
            'ID_Level' => $row->ID_Level ?? null,
        ];
    }

    private function assertCanView(User $user, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        abort_unless($capabilities['menu']['canView'], 403, 'Anda tidak memiliki akses tab Menu.');
    }

    private function assertCanAssign(User $user, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        abort_unless($capabilities['menu']['canAssign'], 403, 'Anda tidak memiliki hak mengubah akses Menu.');
    }
}
