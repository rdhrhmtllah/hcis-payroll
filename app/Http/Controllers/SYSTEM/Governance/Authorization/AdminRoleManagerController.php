<?php

namespace App\Http\Controllers\SYSTEM\Governance\Authorization;

use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\AturanDelegasi;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminRoleManagerController extends Controller
{
    private const ADMIN_CONTEXT_CODE = 'admin';

    private const ADMIN_PAGE_KEY = 'systemAuthManagerPage';

    /**
     * Halaman B: Module Role Manager (Super Admin per Modul)
     * Route: /admin/role-manager
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! AuthorizationScopeResolver::canManageAuthorization($request->user())) {
            abort(403, 'Anda tidak memiliki akses ke halaman manajemen otorisasi.');
        }

        return Inertia::render('SYSTEM/Governance/Authorization/PageAccess/PagesAccessSystem/index', [
            'scopeMetadata' => $this->buildScopeMetadata($request->user()),
            // RBAC Fase 5: banner "Kini dikelola di Akses & Peran".
            'modulBaru' => app(ModeAkses::class)->namaModulBaru(),
            // Layar SYSTEM: tombol ke Akses & Peran SYSTEM hanya bagi System Admin.
            'tautanAksesPeran' => app(KeputusanAkses::class)->apakahSystemAdmin($request->user()) ? '/system/akses-peran' : null,
        ]);
    }

    /**
     * Halaman A: System Authorization Manager (System Admin)
     * Route: /admin/system-manager
     */
    public function indexSystem(Request $request)
    {
        if (! $request->user() || ! AuthorizationScopeResolver::canManageAuthorization($request->user())) {
            abort(403, 'Anda tidak memiliki akses ke halaman manajemen otorisasi.');
        }

        return Inertia::render('SYSTEM/Governance/Authorization/SystemManager/index', [
            'scopeMetadata' => $this->buildScopeMetadata($request->user()),
            // RBAC Fase 5: banner "Kini dikelola di Akses & Peran".
            'modulBaru' => app(ModeAkses::class)->namaModulBaru(),
            // Layar SYSTEM: tombol ke Akses & Peran SYSTEM hanya bagi System Admin.
            'tautanAksesPeran' => app(KeputusanAkses::class)->apakahSystemAdmin($request->user()) ? '/system/akses-peran' : null,
        ]);
    }

    /**
     * Build scope payload for initial Inertia props
     */
    private function buildScopeMetadata($user): array
    {
        if (! $user) {
            $defaultMetadata = [
                'adminTier' => 'ADMIN_BIASA',
                'scopeTier' => 'UNSCOPED',
                'isSystemAdmin' => false,
                'manageableGroupIds' => [],
                'usablePageTypes' => [],
                'visibleTabs' => [],
                'allTabs' => [],
                'tabVisibility' => [],
                'visibleMenus' => [],
                'deniedMenus' => [],
                'capabilities' => [
                    'canView' => false,
                    'canEdit' => false,
                    'canAssign' => false,
                    'canDelegate' => false,
                    'allowedResources' => [],
                ],
            ];

            return $defaultMetadata;
        }

        return AuthorizationScopeResolver::buildScopeMetadata($user, [
            'contextCode' => self::ADMIN_CONTEXT_CODE,
            'pageType' => self::ADMIN_PAGE_KEY,
            'resourceType' => 'tab',
        ]);
    }

    // ========================================================================
    // LEGACY ENDPOINTS (tetap dipertahankan untuk backward compatibility)
    // ========================================================================

    public function getInitialData()
    {
        try {
            $admins = DB::table('KPI_Users')
                ->join('Karyawan', 'Karyawan.UserID_Web', '=', 'KPI_Users.Id_Users')
                ->where('Role', '!=', 'user')
                ->select('Id_Users as UserID_Web', 'Karyawan.Nama as Nama_User', 'Role')
                ->orderBy('Karyawan.Nama')
                ->get();

            $menus = DB::table('N_HRIS_Menus')
                ->select('Nama_Menu', 'Jenis_page', 'Header_Label')
                ->orderBy('Header_Label')
                ->orderBy('Nama_Menu')
                ->get();

            $actions = DB::table('N_HRIS_Klasifikasi_Aksi')->select('Id_Klasifikasi_Actions', 'Nama_Aksi')->get();

            $featureGroups = DB::table('N_HRIS_Master_Grup_Fitur')->select('Id_Grup_Fitur', 'Kode_Grup_Fitur')->get();

            $contentTabs = [
                ['id' => 'TAB_PENGAJUAN', 'label' => 'Tab Pengajuan'],
                ['id' => 'TAB_RIWAYAT', 'label' => 'Tab Riwayat'],
                ['id' => 'TAB_JENIS_CUTI', 'label' => 'Tab Jenis Cuti'],
                ['id' => 'TAB_CUTI_BERSAMA', 'label' => 'Tab Cuti Bersama'],
                ['id' => 'TAB_KUOTA', 'label' => 'Tab Kuota Karyawan'],
            ];

            return response()->json([
                'admins' => $admins,
                'menus' => $menus,
                'actions' => $actions,
                'featureGroups' => $featureGroups,
                'contentTabs' => $contentTabs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil data awal: '.$e->getMessage()], 500);
        }
    }

    public function getAdminPermissions($userId)
    {
        try {
            $pageAccesses = DB::table('HRIS_Page_Access')
                ->where('UserID_Web', $userId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Jenis_page', 'Id_Page_Access');

            $pages = $pageAccesses->values()->all();
            $pageAccessIds = $pageAccesses->keys()->all();

            $actionPermissions = DB::table('N_HRIS_Role_Menu_Access')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Id_Page_Access', $pageAccessIds)
                ->get(['Id_Page_Access', 'Id_Aksi']);

            $contentPermissions = DB::table('N_HRIS_Role_Konten_Access')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Id_Page_Access', $pageAccessIds)
                ->get(['Id_Page_Access', 'Id_Grup_Fitur', 'Kategori']);

            $actions = [];
            foreach ($actionPermissions as $permission) {
                $jenisPage = $pageAccesses[$permission->Id_Page_Access] ?? null;
                if ($jenisPage) {
                    if (! isset($actions[$jenisPage])) {
                        $actions[$jenisPage] = [];
                    }
                    $actions[$jenisPage][] = $permission->Id_Aksi;
                }
            }

            $featureGroups = [];
            $contentTabs = [];
            foreach ($contentPermissions as $permission) {
                if ($permission->Id_Grup_Fitur) {
                    $featureGroups[] = $permission->Id_Grup_Fitur;
                }
                if ($permission->Kategori) {
                    $contentTabs[] = $permission->Kategori;
                }
            }

            return response()->json([
                'pages' => $pages,
                'actions' => $actions,
                'featureGroups' => array_unique($featureGroups),
                'contentTabs' => array_unique($contentTabs),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil izin admin: '.$e->getMessage()], 500);
        }
    }

    public function saveAdminPermissions(Request $request, $userId)
    {
        $validated = $request->validate([
            'pages' => 'present|array',
            'actions' => 'present|array',
            'featureGroups' => 'present|array',
            'contentTabs' => 'present|array',
        ]);

        // RBAC (review tingkat peran I4): grant halaman lewat layar lama ikut aturan delegasi.
        if (! app(KeputusanAkses::class)->apakahSystemAdmin($request->user())) {
            $sebelum = DB::table('HRIS_Page_Access')->where('UserID_Web', $userId)->where('Flag_Aktif', 'Y')->pluck('Jenis_page')
                ->map(fn ($p) => HibahTataKelola::normalisasi((string) $p))->unique();
            $sesudah = collect($validated['pages'])->map(fn ($p) => HibahTataKelola::normalisasi((string) $p))->unique();
            try {
                $aturan = app(AturanDelegasi::class);
                foreach ($validated['pages'] as $p) {
                    if (! $sebelum->contains(HibahTataKelola::normalisasi((string) $p))) {
                        $aturan->periksaGrant($request->user(), (string) $p, (int) $userId);
                    }
                }
                foreach ($sebelum->diff($sesudah) as $p) {
                    $aturan->periksaGrant($request->user(), (string) $p, (int) $userId, false);
                }
            } catch (AksesDitolak $e) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
        }

        DB::beginTransaction();
        try {
            $halamanSebelum = DB::table('HRIS_Page_Access')->where('UserID_Web', $userId)->where('Flag_Aktif', 'Y')
                ->orderBy('Jenis_page')->pluck('Jenis_page')->unique()->values()->all();

            $oldPageAccessIds = DB::table('HRIS_Page_Access')
                ->where('Flag_Aktif', 'Y')
                ->where('UserID_Web', $userId)
                ->pluck('Id_Page_Access');

            if ($oldPageAccessIds->isNotEmpty()) {
                DB::table('N_HRIS_Role_Menu_Access')
                    ->whereIn('Id_Page_Access', $oldPageAccessIds)
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
                DB::table('N_HRIS_Role_Konten_Access')
                    ->whereIn('Id_Page_Access', $oldPageAccessIds)
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
            }

            DB::table('HRIS_Page_Access')
                ->where('UserID_Web', $userId)
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

            $pageAccessMapping = [];
            $pengguna = DB::table('Karyawan as a')
                ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
                ->where('a.UserID_Web', $userId)
                ->select('b.ID_Divisi', 'c.ID_Level')
                ->first();

            if (! $pengguna) {
                throw new \Exception("Data karyawan tidak ditemukan untuk User ID: {$userId}");
            }

            $idDivisi = $pengguna->ID_Divisi ?? 0;
            $idLevel = $pengguna->ID_Level ?? 0;

            foreach ($validated['pages'] as $page) {
                $idPageAccess = DB::table('HRIS_Page_Access')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'Flag_Aktif' => 'Y',
                    'UserID_Web' => $userId,
                    'ID_Divisi' => $idDivisi,
                    'ID_Level' => $idLevel,
                    'Jenis_page' => $page,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $pageAccessMapping[$page] = $idPageAccess;
            }

            $menuAccessToInsert = [];
            foreach ($validated['actions'] as $page => $actions) {
                if (isset($pageAccessMapping[$page]) && is_array($actions)) {
                    foreach ($actions as $action) {
                        $menuAccessToInsert[] = [
                            'Id_Page_Access' => $pageAccessMapping[$page],
                            'Id_Aksi' => $action,
                            'Flag_Aktif' => 'Y',
                            'Created_At' => now(),
                            'Updated_At' => now(),
                        ];
                    }
                }
            }
            if (! empty($menuAccessToInsert)) {
                PenyisipanBerbatasParameter::sisipkan('N_HRIS_Role_Menu_Access', $menuAccessToInsert);
            }

            $kontenAccessToInsert = [];
            foreach ($pageAccessMapping as $idPageAccess) {
                foreach ($validated['featureGroups'] as $featureGroupId) {
                    $kontenAccessToInsert[] = [
                        'Id_Page_Access' => $idPageAccess,
                        'Id_Grup_Fitur' => $featureGroupId,
                        'Kategori' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'Flag_Aktif' => 'Y',
                    ];
                }
                foreach ($validated['contentTabs'] as $kategori) {
                    $kontenAccessToInsert[] = [
                        'Id_Page_Access' => $idPageAccess,
                        'Id_Grup_Fitur' => null,
                        'Kategori' => $kategori,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'Flag_Aktif' => 'Y',
                    ];
                }
            }
            if (! empty($kontenAccessToInsert)) {
                PenyisipanBerbatasParameter::sisipkan('N_HRIS_Role_Konten_Access', $kontenAccessToInsert);
            }

            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'GRANT', (string) $userId, 'SIMPAN_IZIN_ADMIN', $halamanSebelum,
                collect($validated['pages'])->map(fn ($p) => (string) $p)->unique()->sort()->values()->all(), (int) $userId,
            );

            DB::commit();

            return response()->json(['message' => 'Hak akses berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan izin: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    // ========================================================================
    // ENDPOINT BARU — Unified Authorization Management System
    // ========================================================================

    /**
     * Helper: Ambil Grup Fitur yang boleh dikelola admin yang login.
     * System Admin → return SEMUA; Super Admin → return hanya modul-nya.
     */
    private function getAllowedGrupFitur()
    {
        $user = auth()->user();
        if ($user) {
            $scope = AuthorizationScopeResolver::resolve($user);
            if ($scope['isSystemAdmin']) {
                return DB::table('N_HRIS_Master_Grup_Fitur')->pluck('Id_Grup_Fitur');
            }
        }

        $userId = $user ? $user->Id_Users : null;

        $userRoles = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($join) {
                $join->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', '=', 'Y');
            })
            ->where('ru.UserId_Web', $userId)
            ->where('ru.Flag_Aktif', 'Y')
            ->pluck('rf.Id_Grup_Fitur')
            ->unique()
            ->values();

        if ($userRoles->isEmpty()) {
            return DB::table('N_HRIS_Master_Grup_Fitur')->pluck('Id_Grup_Fitur');
        }

        return $userRoles;
    }

    /**
     * Dashboard stats per modul
     * GET /api/v1/admin/role-manager/dashboard-stats
     */
    public function getDashboardStats()
    {
        try {
            $allowed = $this->getAllowedGrupFitur();

            $modules = DB::table('N_HRIS_Master_Grup_Fitur')->whereIn('Id_Grup_Fitur', $allowed)->get();

            $stats = $modules->map(function ($module) {
                $roleIds = DB::table('N_HRIS_Role_Functional')
                    ->where('Id_Grup_Fitur', $module->Id_Grup_Fitur)
                    ->where('Flag_Aktif', 'Y')
                    ->pluck('Id_Role');

                return [
                    'id' => $module->Id_Grup_Fitur,
                    'kode' => $module->Kode_Grup_Fitur,
                    'nama' => $module->Nama_Grup_Fitur ?? $module->Kode_Grup_Fitur,
                    'users' => DB::table('N_HRIS_Role_User')
                        ->whereIn('Id_Role', $roleIds)
                        ->where('Flag_Aktif', 'Y')
                        ->distinct('UserId_Web')
                        ->count('UserId_Web'),
                    'roles' => $roleIds->count(),
                    'scopes' => DB::table('N_HRIS_Role_Scope')
                        ->whereIn('Id_Role', $roleIds)
                        ->where('Flag_Aktif', 'Y')
                        ->distinct('Id_Scope')
                        ->count('Id_Scope'),
                    'admins' => DB::table('N_HRIS_Role_User as ru')
                        ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                        ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                        ->whereIn('ru.Id_Role', $roleIds)
                        ->where('ru.Flag_Aktif', 'Y')
                        ->where('rf.Flag_Aktif', 'Y')
                        ->select(
                            'ru.UserId_Web as user_id',
                            'k.Nama as user_nama',
                            'k.Kode_Karyawan as user_kode',
                            'rf.Role_Name as role_name',
                        )
                        ->distinct()
                        ->orderBy('k.Nama')
                        ->limit(50)
                        ->get(),
                ];
            });

            $legacyOnlyCount = DB::table('HRIS_Page_Access as pa')
                ->leftJoin('N_HRIS_Role_User as ru', function ($join) {
                    $join->on('pa.UserID_Web', '=', 'ru.UserId_Web')->where('ru.Flag_Aktif', '=', 'Y');
                })
                ->where('pa.Flag_Aktif', 'Y')
                ->whereNull('ru.Id')
                ->distinct('pa.UserID_Web')
                ->count('pa.UserID_Web');

            $recentActivity = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                ->orderByDesc('ru.Assigned_At')
                ->limit(10)
                ->select('k.Nama as user_nama', 'rf.Role_Code', 'rf.Role_Name', 'ru.Assigned_At', 'ru.Flag_Aktif')
                ->get();

            return response()->json([
                'modules' => $stats,
                'legacyOnlyCount' => $legacyOnlyCount,
                'recentActivity' => $recentActivity,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat dashboard: '.$e->getMessage()], 500);
        }
    }

    /**
     * List users + search + filter modul
     * GET /api/v1/admin/role-manager/users?search=&module=&page=1
     */
    public function getUsers(Request $request)
    {
        try {
            $allowed = $this->getAllowedGrupFitur();
            $search = $request->input('search', '');
            $moduleFilter = $request->input('module', null);

            $query = DB::table('Karyawan as k')
                ->join('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
                ->leftJoin('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_DIVISI_SUB_DIVISI')
                ->where('k.Aktif', 'Y');

            // Filter modul di level SQL (sebelum paginate)
            if ($moduleFilter) {
                $query->whereExists(function ($q) use ($moduleFilter) {
                    $q->select(DB::raw(1))
                        ->from('N_HRIS_Role_User as ru_filter')
                        ->join('N_HRIS_Role_Functional as rf_filter', 'rf_filter.Id_Role', '=', 'ru_filter.Id_Role')
                        ->whereRaw('ru_filter.UserId_Web = k.UserID_Web')
                        ->where('rf_filter.Id_Grup_Fitur', $moduleFilter)
                        ->where('ru_filter.Flag_Aktif', 'Y')
                        ->where('rf_filter.Flag_Aktif', 'Y');
                });
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")->orWhere('k.Kode_Karyawan', 'LIKE', "%{$search}%");
                });
            }

            $users = $query
                ->select(
                    'k.UserID_Web as id',
                    'k.Nama as nama',
                    'k.Kode_Karyawan as kode',
                    'vdsd.nama_divisi as divisi',
                )
                ->orderBy('k.Nama')
                ->paginate(20);

            $userIds = collect($users->items())->pluck('id');

            $roleAssignments = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->whereIn('ru.UserId_Web', $userIds)
                ->where('ru.Flag_Aktif', 'Y')
                ->where('rf.Flag_Aktif', 'Y')
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                ->select('ru.UserId_Web', 'rf.Role_Code', 'rf.Role_Name', 'rf.Id_Grup_Fitur')
                ->get()
                ->groupBy('UserId_Web');

            $legacyAccess = DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $userIds)
                ->where('Flag_Aktif', 'Y')
                ->distinct('UserID_Web')
                ->pluck('UserID_Web');

            $enrichedUsers = collect($users->items())->map(function ($user) use ($roleAssignments, $legacyAccess) {
                $roles = $roleAssignments->get($user->id, collect());
                $hasLegacy = $legacyAccess->contains($user->id);
                $hasNewRoles = $roles->isNotEmpty();

                return array_merge((array) $user, [
                    'roles' => $roles->map(fn ($r) => ['code' => $r->Role_Code, 'name' => $r->Role_Name])->values(),
                    'status' => $hasNewRoles ? 'migrated' : ($hasLegacy ? 'legacy' : 'none'),
                ]);
            });

            return response()->json([
                'data' => $enrichedUsers->values(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                    'prev_page_url' => $users->previousPageUrl(),
                    'next_page_url' => $users->nextPageUrl(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat users: '.$e->getMessage()], 500);
        }
    }

    /**
     * Load role assignment untuk user tertentu
     * GET /api/v1/admin/role-manager/user-roles/{userId}
     */
    public function getUserRoles($userId)
    {
        try {
            $allowed = $this->getAllowedGrupFitur();

            $assignedRoleIds = DB::table('N_HRIS_Role_User')
                ->where('UserId_Web', $userId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Role');

            $allRoles = DB::table('N_HRIS_Role_Functional as rf')
                ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                ->where('rf.Flag_Aktif', 'Y')
                ->select(
                    'rf.Id_Role',
                    'rf.Role_Code',
                    'rf.Role_Name',
                    'rf.Description',
                    'rf.Id_Grup_Fitur',
                    'gf.Kode_Grup_Fitur as module_code',
                )
                ->orderBy('gf.Kode_Grup_Fitur')
                ->orderBy('rf.Role_Name')
                ->get();

            $enrichedRoles = $allRoles->map(function ($role) use ($assignedRoleIds) {
                $scopes = DB::table('N_HRIS_Role_Scope as rs')
                    ->join('N_HRIS_Scope_Definition as sd', 'sd.Id_Scope', '=', 'rs.Id_Scope')
                    ->where('rs.Id_Role', $role->Id_Role)
                    ->where('rs.Flag_Aktif', 'Y')
                    ->select('sd.Scope_Name', 'sd.Scope_Value')
                    ->get();

                $permissions = DB::table('N_HRIS_Role_Permission')
                    ->where('Id_Role', $role->Id_Role)
                    ->pluck('Permission_Code');

                return [
                    'id' => $role->Id_Role,
                    'code' => $role->Role_Code,
                    'name' => $role->Role_Name,
                    'deskripsi' => $role->Description,
                    'module' => $role->module_code,
                    'module_id' => $role->Id_Grup_Fitur,
                    'assigned' => $assignedRoleIds->contains($role->Id_Role),
                    'scopes' => $scopes,
                    'permissions' => $permissions,
                ];
            });

            return response()->json([
                'roles' => $enrichedRoles->groupBy('module'),
                'assignedIds' => $assignedRoleIds,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat roles user: '.$e->getMessage()], 500);
        }
    }

    /**
     * Save role assignment per user (sync N_HRIS_Role_User)
     * POST /api/v1/admin/role-manager/user-roles/{userId}
     */
    /** @return \Illuminate\Http\JsonResponse|null 403 bila salah satu perubahan melanggar aturan delegasi */
    private function periksaUbahPeranLama(Request $request, int $userId, array $roleIds)
    {
        $aktor = $request->user();
        if (app(KeputusanAkses::class)->apakahSystemAdmin($aktor)) {
            return null;
        }
        $aktif = DB::table('N_HRIS_Role_User')->where('UserId_Web', $userId)->where('Flag_Aktif', 'Y')->pluck('Id_Role')->map(fn ($v) => (int) $v);
        $dikelola = DB::table('N_HRIS_Role_Functional')->whereIn('Id_Grup_Fitur', $this->getAllowedGrupFitur())->where('Flag_Aktif', 'Y')
            ->pluck('Id_Role')->map(fn ($v) => (int) $v);
        $aturan = app(AturanDelegasi::class);
        try {
            foreach (collect($roleIds)->diff($aktif) as $idRole) {
                $aturan->periksaPenugasan($aktor, (int) $idRole, $userId, []);
            }
            foreach ($aktif->intersect($dikelola)->diff($roleIds) as $idRole) {
                $aturan->periksaCabut($aktor, (object) ['UserId_Web' => $userId, 'Id_Role' => $idRole]);
            }
        } catch (AksesDitolak $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return null;
    }

    public function saveUserRoles(Request $request, $userId)
    {
        $validated = $request->validate([
            'roleIds' => 'present|array',
            'roleIds.*' => 'integer',
        ]);

        // RBAC (review tingkat peran I4): layar lama memakai aturan delegasi yang SAMA dengan
        // Akses & Peran — tanpa ini pemegang halaman Role Manager bisa mengangkat dirinya
        // Super Admin. Peran yang ditambah dicek seperti tugaskan, yang dicabut seperti cabut.
        if ($tolak = $this->periksaUbahPeranLama($request, (int) $userId, array_map('intval', $validated['roleIds']))) {
            return $tolak;
        }

        DB::beginTransaction();
        try {
            $allowed = $this->getAllowedGrupFitur();
            $adminId = auth()->user()->Id_Users;

            $validRoleIds = DB::table('N_HRIS_Role_Functional')
                ->whereIn('Id_Role', $validated['roleIds'])
                ->whereIn('Id_Grup_Fitur', $allowed)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Role');

            $oldRoleIds = DB::table('N_HRIS_Role_Functional')
                ->whereIn('Id_Grup_Fitur', $allowed)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Role');

            $kodeSebelum = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.UserId_Web', $userId)->where('ru.Flag_Aktif', 'Y')
                ->orderBy('rf.Role_Code')->pluck('rf.Role_Code')->all();

            // RBAC Fase 3b (review I3): hanya cabut yang tak lagi dipilih dan tambah
            // yang baru. Penugasan yang tetap dipilih TIDAK dibuat ulang — supaya
            // cakupan, tanggung jawab, dan kedaluwarsa dari layar Akses & Peran
            // tidak hilang (dulu membuatnya tanpa batas & permanen).
            $sudahAktif = DB::table('N_HRIS_Role_User')->where('UserId_Web', $userId)->where('Flag_Aktif', 'Y')->pluck('Id_Role')
                ->map(fn ($v) => (int) $v);
            DB::table('N_HRIS_Role_User')
                ->where('UserId_Web', $userId)
                ->whereIn('Id_Role', $oldRoleIds->diff($validRoleIds)->values())
                ->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T']);

            foreach ($validRoleIds->map(fn ($v) => (int) $v)->diff($sudahAktif) as $roleId) {
                DB::table('N_HRIS_Role_User')->insert([
                    'UserId_Web' => $userId,
                    'Id_Role' => $roleId,
                    'Flag_Aktif' => 'Y',
                    'Assigned_At' => now(),
                    'Assigned_By' => $adminId,
                ]);
            }

            $kodeSesudah = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.UserId_Web', $userId)->where('ru.Flag_Aktif', 'Y')
                ->orderBy('rf.Role_Code')->pluck('rf.Role_Code')->all();
            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'PENUGASAN', (string) $userId, 'UBAH_ROLE', $kodeSebelum, $kodeSesudah, (int) $userId,
            );

            DB::commit();

            $target = \App\Models\ESS\Employee\Models\User::where('Id_Users', $userId)->first();
            if ($target) {
                \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::clearCache($target);
            }

            return response()->json(['message' => 'Role berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan role: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    // ========================================================================
    // MASTER CRUD — Roles
    // ========================================================================

    public function getRoles()
    {
        try {
            $allowed = $this->getAllowedGrupFitur();
            // dd($allowed);
            $roles = DB::table('N_HRIS_Role_Functional as rf')
                ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                ->where('rf.Flag_Aktif', 'Y')
                ->select('rf.*', 'gf.Kode_Grup_Fitur as module_code')
                ->orderBy('gf.Kode_Grup_Fitur')
                ->orderBy('rf.Role_Name')
                ->get();

            // dd($roles);
            $roleIds = $roles->pluck('Id_Role');

            // Pre-fetch counts to avoid N+1
            $userCounts = DB::table('N_HRIS_Role_User')
                ->whereIn('Id_Role', $roleIds)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Role', DB::raw('COUNT(*) as cnt'))
                ->groupBy('Id_Role')
                ->pluck('cnt', 'Id_Role');

            $scopeCounts = DB::table('N_HRIS_Role_Scope')
                ->whereIn('Id_Role', $roleIds)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Role', DB::raw('COUNT(*) as cnt'))
                ->groupBy('Id_Role')
                ->pluck('cnt', 'Id_Role');

            $permissionCounts = DB::table('N_HRIS_Role_Permission')
                ->whereIn('Id_Role', $roleIds)
                ->select('Id_Role', DB::raw('COUNT(*) as cnt'))
                ->groupBy('Id_Role')
                ->pluck('cnt', 'Id_Role');

            $enriched = $roles->map(function ($role) use ($userCounts, $scopeCounts, $permissionCounts) {
                return [
                    'id' => $role->Id_Role,
                    'code' => $role->Role_Code,
                    'name' => $role->Role_Name,
                    'deskripsi' => $role->Description,
                    'module' => $role->module_code,
                    'module_id' => $role->Id_Grup_Fitur,
                    'user_count' => $userCounts[$role->Id_Role] ?? 0,
                    'scope_count' => $scopeCounts[$role->Id_Role] ?? 0,
                    'permission_count' => $permissionCounts[$role->Id_Role] ?? 0,
                ];
            });

            return response()->json(['roles' => $enriched->groupBy('module')]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat roles: '.$e->getMessage()], 500);
        }
    }

    public function createRole(Request $request)
    {
        $validated = $request->validate([
            'Role_Code' => 'required|string|max:50',
            'Role_Name' => 'required|string|max:100',
            'Description' => 'nullable|string|max:255',
            'Id_Grup_Fitur' => 'required|integer',
            'scopeIds' => 'nullable|array',
            'permissionCodes' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $allowed = $this->getAllowedGrupFitur();
            if (! $allowed->contains($validated['Id_Grup_Fitur'])) {
                DB::rollBack();

                return response()->json(['message' => 'Anda tidak memiliki akses ke modul ini.'], 403);
            }

            $roleId = DB::table('N_HRIS_Role_Functional')->insertGetId([
                'Role_Code' => $validated['Role_Code'],
                'Role_Name' => $validated['Role_Name'],
                'Description' => $validated['Description'] ?? null,
                'Id_Grup_Fitur' => $validated['Id_Grup_Fitur'],
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
            ]);

            if (! empty($validated['scopeIds'])) {
                foreach ($validated['scopeIds'] as $scopeId) {
                    DB::table('N_HRIS_Role_Scope')->insert([
                        'Id_Role' => $roleId,
                        'Id_Scope' => $scopeId,
                        'Flag_Aktif' => 'Y',
                    ]);
                }
            }

            if (! empty($validated['permissionCodes'])) {
                foreach ($validated['permissionCodes'] as $code) {
                    DB::table('N_HRIS_Role_Permission')->insert([
                        'Id_Role' => $roleId,
                        'Permission_Code' => $code,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Role berhasil dibuat.', 'id' => $roleId]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat role: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function updateRole(Request $request, $id)
    {
        $validated = $request->validate([
            'Role_Code' => 'required|string|max:50',
            'Role_Name' => 'required|string|max:100',
            'Description' => 'nullable|string|max:255',
            'scopeIds' => 'nullable|array',
            'permissionCodes' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $allowed = $this->getAllowedGrupFitur();
            $role = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $id)->first();
            if (! $role || ! $allowed->contains($role->Id_Grup_Fitur)) {
                DB::rollBack();

                return response()->json(['message' => 'Role tidak ditemukan atau Anda tidak punya akses.'], 403);
            }

            DB::table('N_HRIS_Role_Functional')
                ->where('Id_Role', $id)
                ->update([
                    'Role_Code' => $validated['Role_Code'],
                    'Role_Name' => $validated['Role_Name'],
                    'Description' => $validated['Description'] ?? null,
                ]);

            if (isset($validated['scopeIds'])) {
                DB::table('N_HRIS_Role_Scope')
                    ->where('Id_Role', $id)
                    ->update(['Flag_Aktif' => 'T']);
                foreach ($validated['scopeIds'] as $scopeId) {
                    DB::table('N_HRIS_Role_Scope')->insert([
                        'Id_Role' => $id,
                        'Id_Scope' => $scopeId,
                        'Flag_Aktif' => 'Y',
                    ]);
                }
            }

            if (isset($validated['permissionCodes'])) {
                DB::table('N_HRIS_Role_Permission')->where('Id_Role', $id)->delete();
                foreach ($validated['permissionCodes'] as $code) {
                    DB::table('N_HRIS_Role_Permission')->insert([
                        'Id_Role' => $id,
                        'Permission_Code' => $code,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Role berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal memperbarui role: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function deleteRole($id)
    {
        try {
            $allowed = $this->getAllowedGrupFitur();
            $role = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $id)->first();
            if (! $role || ! $allowed->contains($role->Id_Grup_Fitur)) {
                return response()->json(['message' => 'Role tidak ditemukan atau Anda tidak punya akses.'], 403);
            }

            $userCount = DB::table('N_HRIS_Role_User')->where('Id_Role', $id)->where('Flag_Aktif', 'Y')->count();
            if ($userCount > 0) {
                return response()->json(
                    [
                        'message' => "Role ini digunakan oleh {$userCount} user. Hapus assignment terlebih dahulu.",
                        'user_count' => $userCount,
                    ],
                    422,
                );
            }

            /*
             * TIGA TULISAN, SATU KEPUTUSAN — karena itu satu transaksi.
             *
             * Yang ketiga adalah DELETE permanen, bukan soft delete: gagal di
             * tengah meninggalkan role yang masih Flag_Aktif='Y' tetapi
             * permission-nya sudah lenyap. Role yang tampak hidup dengan izin
             * kosong lebih buruk daripada role yang gagal dihapus, sebab ia
             * tak melaporkan apa pun — pemakainya hanya menemukan akses yang
             * diam-diam tak berlaku, dan tak ada jejak yang menjelaskannya.
             *
             * Urutannya pun disengaja: yang PERMANEN dikerjakan terakhir, jadi
             * kegagalan yang paling mungkin (dua update di atas) terjadi
             * sebelum ada yang tak bisa dikembalikan.
             */
            DB::transaction(function () use ($id) {
                DB::table('N_HRIS_Role_Functional')
                    ->where('Id_Role', $id)
                    ->update(['Flag_Aktif' => 'T']);
                DB::table('N_HRIS_Role_Scope')
                    ->where('Id_Role', $id)
                    ->update(['Flag_Aktif' => 'T']);
                DB::table('N_HRIS_Role_Permission')->where('Id_Role', $id)->delete();
            });

            return response()->json(['message' => 'Role berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus role: '.$e->getMessage()], 500);
        }
    }

    public function getRoleDetail($id)
    {
        try {
            $role = DB::table('N_HRIS_Role_Functional as rf')
                ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                ->where('rf.Id_Role', $id)
                ->select('rf.*', 'gf.Kode_Grup_Fitur as module_code')
                ->first();

            if (! $role) {
                return response()->json(['message' => 'Role tidak ditemukan.'], 404);
            }

            $scopes = DB::table('N_HRIS_Role_Scope as rs')
                ->join('N_HRIS_Scope_Definition as sd', 'sd.Id_Scope', '=', 'rs.Id_Scope')
                ->where('rs.Id_Role', $id)
                ->where('rs.Flag_Aktif', 'Y')
                ->select('sd.*')
                ->get();

            $permissions = DB::table('N_HRIS_Role_Permission')->where('Id_Role', $id)->get();

            $users = DB::table('N_HRIS_Role_User as ru')
                ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                ->where('ru.Id_Role', $id)
                ->where('ru.Flag_Aktif', 'Y')
                ->select('k.UserID_Web as id', 'k.Nama as nama', 'k.Kode_Karyawan as kode', 'ru.Assigned_At')
                ->get();

            return response()->json([
                'role' => $role,
                'scopes' => $scopes,
                'permissions' => $permissions,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat detail role: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // MASTER CRUD — Scopes
    // ========================================================================

    public function getScopes()
    {
        try {
            $scopes = DB::table('N_HRIS_Scope_Definition')->where('Flag_Aktif', 'Y')->get();

            // Pre-fetch all role-scope mappings to avoid N+1
            $scopeIds = $scopes->pluck('Id_Scope');
            $allRoleScopeMappings = DB::table('N_HRIS_Role_Scope as rs')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'rs.Id_Role')
                ->whereIn('rs.Id_Scope', $scopeIds)
                ->where('rs.Flag_Aktif', 'Y')
                ->where('rf.Flag_Aktif', 'Y')
                ->select('rs.Id_Scope', 'rf.Role_Code', 'rf.Role_Name')
                ->get()
                ->groupBy('Id_Scope');

            $enriched = $scopes->map(function ($scope) use ($allRoleScopeMappings) {
                $usedByRoles = $allRoleScopeMappings->get($scope->Id_Scope, collect());

                return [
                    'id' => $scope->Id_Scope,
                    'name' => $scope->Scope_Name,
                    'value' => $scope->Scope_Value,
                    'filter_column' => $scope->Filter_Column,
                    'custom_condition' => $scope->Custom_Condition,
                    'used_by_roles' => $usedByRoles
                        ->map(fn ($r) => ['Role_Code' => $r->Role_Code, 'Role_Name' => $r->Role_Name])
                        ->values(),
                    'usage_count' => $usedByRoles->count(),
                ];
            });

            return response()->json(['scopes' => $enriched]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat scopes: '.$e->getMessage()], 500);
        }
    }

    public function createScope(Request $request)
    {
        $validated = $request->validate([
            'Scope_Name' => 'required|string|max:100',
            'Source_Object' => 'nullable|string|max:255',
            'Filter_Column' => 'nullable|string|max:100',
            'Filter_Operator' => 'nullable|string|max:50',
            'Scope_Value' => 'nullable|string|max:255',
            'Custom_Condition' => 'nullable|string',
            'Scope_Code' => 'nullable|string|max:100',
        ]);

        try {
            $id = DB::table('N_HRIS_Scope_Definition')->insertGetId([
                'Scope_Code' => $validated['Scope_Code'] ?? null,
                'Scope_Name' => $validated['Scope_Name'],
                'Source_Object' => $validated['Source_Object'] ?? null,
                'Filter_Column' => $validated['Filter_Column'] ?? null,
                'Filter_Operator' => $validated['Filter_Operator'] ?? null,
                'Scope_Value' => $validated['Scope_Value'] ?? null,
                'Custom_Condition' => $validated['Custom_Condition'] ?? null,
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
            ]);

            return response()->json(['message' => 'Scope berhasil dibuat.', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat scope: '.$e->getMessage()], 500);
        }
    }

    public function updateScope(Request $request, $id)
    {
        $validated = $request->validate([
            'Scope_Name' => 'required|string|max:100',
            'Source_Object' => 'nullable|string|max:255',
            'Filter_Column' => 'nullable|string|max:100',
            'Filter_Operator' => 'nullable|string|max:50',
            'Scope_Value' => 'nullable|string|max:255',
            'Custom_Condition' => 'nullable|string',
            'Scope_Code' => 'nullable|string|max:100',
        ]);

        try {
            DB::table('N_HRIS_Scope_Definition')
                ->where('Id_Scope', $id)
                ->update([
                    'Scope_Name' => $validated['Scope_Name'],
                    'Source_Object' => $validated['Source_Object'] ?? null,
                    'Filter_Column' => $validated['Filter_Column'] ?? null,
                    'Filter_Operator' => $validated['Filter_Operator'] ?? null,
                    'Scope_Value' => $validated['Scope_Value'] ?? null,
                    'Custom_Condition' => $validated['Custom_Condition'] ?? null,
                ]);

            return response()->json(['message' => 'Scope berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memperbarui scope: '.$e->getMessage()], 500);
        }
    }

    public function deleteScope($id)
    {
        try {
            $usageCount = DB::table('N_HRIS_Role_Scope')->where('Id_Scope', $id)->where('Flag_Aktif', 'Y')->count();
            if ($usageCount > 0) {
                return response()->json(
                    [
                        'message' => "Scope ini terhubung ke {$usageCount} role. Hapus mapping terlebih dahulu.",
                        'usage_count' => $usageCount,
                    ],
                    422,
                );
            }

            DB::table('N_HRIS_Scope_Definition')
                ->where('Id_Scope', $id)
                ->update(['Flag_Aktif' => 'T']);

            return response()->json(['message' => 'Scope berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus scope: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // Permissions & Activity Log
    // ========================================================================

    public function getPermissions()
    {
        try {
            $allowed = $this->getAllowedGrupFitur();

            $permissions = DB::table('N_HRIS_Role_Permission as rp')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'rp.Id_Role')
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                // N_HRIS_Role_Permission has no Flag_Aktif column
                ->where('rf.Flag_Aktif', 'Y')
                ->select('rp.*', 'rf.Role_Code', 'rf.Role_Name')
                ->get();

            $grouped = $permissions
                ->groupBy('Permission_Code')
                ->map(function ($items, $code) {
                    return [
                        'code' => $code,
                        'used_by_roles' => $items
                            ->map(fn ($i) => ['role_code' => $i->Role_Code, 'role_name' => $i->Role_Name])
                            ->unique('role_code')
                            ->values(),
                        'usage_count' => $items->unique('Id_Role')->count(),
                    ];
                })
                ->values();

            return response()->json(['permissions' => $grouped]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat permissions: '.$e->getMessage()], 500);
        }
    }

    public function getActivityLog()
    {
        try {
            $allowed = $this->getAllowedGrupFitur();

            $logs = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                ->leftJoin('Karyawan as admin', function ($join) {
                    $join->on('admin.UserID_Web', '=', DB::raw('TRY_CAST(ru.Assigned_By AS INT)'));
                })
                ->whereIn('rf.Id_Grup_Fitur', $allowed)
                ->orderByDesc('ru.Assigned_At')
                ->limit(50)
                ->select(
                    'k.Nama as user_nama',
                    'rf.Role_Code',
                    'rf.Role_Name',
                    'ru.Flag_Aktif',
                    'ru.Assigned_At',
                    DB::raw('COALESCE(admin.Nama, ru.Assigned_By) as admin_nama'),
                )
                ->get();

            return response()->json(['logs' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat activity log: '.$e->getMessage()], 500);
        }
    }

    /**
     * Get authorization scope metadata for current user
     * Used by frontend to determine visible tabs and features
     *
     * PHASE 2.1: Frontend Authorization Integration
     * Route: GET /api/v1/admin/role-manager/scope-metadata
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getScopeMetadata(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Use AuthorizationScopeResolver service
            return response()->json(
                AuthorizationScopeResolver::buildScopeMetadata($user, [
                    'contextCode' => self::ADMIN_CONTEXT_CODE,
                    'pageType' => self::ADMIN_PAGE_KEY,
                    'resourceType' => 'tab',
                ]),
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Gagal memuat scope metadata: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * SQL Server has max 2100 bound parameters per statement.
     * This helper chunks bulk inserts automatically based on inserted column count.
     */
}
