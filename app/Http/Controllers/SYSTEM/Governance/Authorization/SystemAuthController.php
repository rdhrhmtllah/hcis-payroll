<?php

namespace App\Http\Controllers\SYSTEM\Governance\Authorization;

use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuDelegationPolicyService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationAuditLogger;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationResourceRegistryService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemAuthController extends Controller
{
    private const MENU_ACTIVE_FLAG_COLUMNS = ['Flag_Aktif', 'Flag_Active'];

    public function __construct(
        private readonly EmployeeTargetScopeService $targetScopeService,
        private readonly MenuDelegationPolicyService $menuDelegationPolicyService,
        private readonly AuthorizationResourceRegistryService $resourceRegistryService,
    ) {}

    public function getMenuDelegationPolicies(Request $request, int $grupId)
    {
        return response()->json(
            $this->menuDelegationPolicyService->configurationForGroup($request->user(), $grupId),
        );
    }

    public function saveMenuDelegationPolicies(Request $request, int $grupId)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.jenis_page' => 'required|string|max:150',
            'items.*.mode' => 'required|in:OPEN,RESTRICTED,SYSTEM_ONLY',
            'items.*.allowed_role_ids' => 'nullable|array',
            'items.*.allowed_role_ids.*' => 'integer|min:1',
        ]);

        $this->menuDelegationPolicyService->savePolicies($request->user(), $grupId, $validated['items']);

        return response()->json(['message' => 'Kebijakan delegasi menu berhasil disimpan.']);
    }

    public function getTargetScopeReadiness(Request $request)
    {
        abort_unless(! empty(AuthorizationScopeResolver::resolve($request->user())['isSystemAdmin']), 403, 'Hanya System Admin yang dapat melihat readiness target scope.');

        return response()->json(['items' => $this->targetScopeService->readiness()]);
    }

    // ========================================================================
    // MODULE GOVERNANCE — Grup Fitur CRUD
    // ========================================================================

    public function getGrupFitur()
    {
        try {
            $grupFitur = DB::table('N_HRIS_Master_Grup_Fitur as gf')
                ->select(
                    'gf.Id_Grup_Fitur',
                    'gf.Kode_Grup_Fitur',
                    'gf.Nama_Grup_Fitur',
                    DB::raw(
                        '(SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Id_Grup_Fitur = gf.Id_Grup_Fitur) as menu_count',
                    ),
                )
                ->orderBy('gf.Kode_Grup_Fitur')
                ->get();

            return response()->json(['data' => $grupFitur]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat Grup Fitur: '.$e->getMessage()], 500);
        }
    }

    public function createGrupFitur(Request $request)
    {
        $validated = $request->validate([
            'Kode_Grup_Fitur' => 'required|string|max:50',
            'Nama_Grup_Fitur' => 'required|string|max:100',
        ]);

        try {
            $id = DB::table('N_HRIS_Master_Grup_Fitur')->insertGetId([
                'Kode_Grup_Fitur' => $validated['Kode_Grup_Fitur'],
                'Nama_Grup_Fitur' => $validated['Nama_Grup_Fitur'],
            ]);

            InertiaShellService::flushMenuCache();

            return response()->json(['message' => 'Grup Fitur berhasil dibuat.', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat Grup Fitur: '.$e->getMessage()], 500);
        }
    }

    public function updateGrupFitur(Request $request, $id)
    {
        $validated = $request->validate([
            'Kode_Grup_Fitur' => 'required|string|max:50',
            'Nama_Grup_Fitur' => 'required|string|max:100',
        ]);

        try {
            DB::table('N_HRIS_Master_Grup_Fitur')
                ->where('Id_Grup_Fitur', $id)
                ->update([
                    'Kode_Grup_Fitur' => $validated['Kode_Grup_Fitur'],
                    'Nama_Grup_Fitur' => $validated['Nama_Grup_Fitur'],
                ]);

            InertiaShellService::flushMenuCache();

            return response()->json(['message' => 'Grup Fitur berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memperbarui Grup Fitur: '.$e->getMessage()], 500);
        }
    }

    public function deleteGrupFitur($id)
    {
        try {
            $mapCount = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $id)->count();
            if ($mapCount > 0) {
                return response()->json(
                    [
                        'message' => "Grup Fitur ini terhubung ke {$mapCount} menu. Hapus mapping terlebih dahulu.",
                    ],
                    422,
                );
            }

            DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', $id)->delete();

            InertiaShellService::flushMenuCache();

            return response()->json(['message' => 'Grup Fitur berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus Grup Fitur: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // MODULE GOVERNANCE — Menu Binding
    // ========================================================================

    public function getMenuBinding($grupId)
    {
        try {
            $allMenus = DB::table('N_HRIS_Menus')
                ->select('Jenis_page', 'Nama_Menu', 'Header_Label')
                ->orderBy('Header_Label')
                ->orderBy('Nama_Menu')
                ->get();

            // Hanya baris aktif yang tampil terikat; memilih halaman nonaktif mengaktifkannya lagi.
            $boundPages = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $grupId)->where('Flag_Aktif', 'Y')->pluck('Jenis_page');

            return response()->json([
                'allMenus' => $allMenus,
                'boundPages' => $boundPages,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat menu binding: '.$e->getMessage()], 500);
        }
    }

    public function saveMenuBinding(Request $request, $grupId)
    {
        $validated = $request->validate([
            'pages' => 'present|array',
            'pages.*' => 'string|max:100',
        ]);

        DB::beginTransaction();
        try {
            // RBAC Fase 5: simpan berbasis selisih. Dulu semua baris dihapus lalu
            // disisipkan ulang, sehingga Flag_Hibah_Role='T' (DDL-2026-110),
            // Sort_Order, dan landing kembali ke bawaan — hibah yang sengaja
            // dimatikan hidup lagi. Baris yang tetap dipilih kini tak disentuh.
            $kunci = fn ($p) => HibahTataKelola::normalisasi((string) $p);
            $dipilih = collect($validated['pages'])->keyBy($kunci);
            $ada = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $grupId)->get(['Id_Grup_Map', 'Jenis_page', 'Flag_Aktif']);

            // Baris nonaktif tak tampil terikat, jadi tak "dilepas" — dibiarkan apa adanya.
            $dilepas = $ada->where('Flag_Aktif', 'Y')->reject(fn ($r) => $dipilih->has($kunci($r->Jenis_page)))->pluck('Id_Grup_Map')->all();
            if ($dilepas !== []) {
                DB::table('N_HRIS_Grup_Fitur_Map')->whereIn('Id_Grup_Map', $dilepas)->delete();
            }

            $diaktifkan = $ada->where('Flag_Aktif', '<>', 'Y')->filter(fn ($r) => $dipilih->has($kunci($r->Jenis_page)))->pluck('Id_Grup_Map')->all();
            if ($diaktifkan !== []) {
                DB::table('N_HRIS_Grup_Fitur_Map')->whereIn('Id_Grup_Map', $diaktifkan)->update(['Flag_Aktif' => 'Y']);
            }

            $sudah = $ada->keyBy(fn ($r) => $kunci($r->Jenis_page));
            $inserts = $dipilih->reject(fn ($p, $k) => $sudah->has($k))
                ->map(fn ($page) => ['Id_Grup_Fitur' => $grupId, 'Jenis_page' => $page])
                ->values()->all();
            if ($inserts !== []) {
                PenyisipanBerbatasParameter::sisipkan('N_HRIS_Grup_Fitur_Map', $inserts);
            }

            DB::commit();
            InertiaShellService::flushMenuCache();
            ModeAkses::lupakan(); // peta ini juga menentukan modul pemilik halaman

            return response()->json(['message' => 'Menu binding berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan binding: '.$e->getMessage()], 500);
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
    // MODULE GOVERNANCE — Menu Capabilities
    // ========================================================================

    public function getMenuCapabilities($jenisPage)
    {
        try {
            $allActions = DB::table('N_HRIS_Klasifikasi_Aksi')
                ->select('Id_Klasifikasi_Actions', 'Nama_Aksi')
                ->orderBy('Id_Klasifikasi_Actions')
                ->get();

            $capabilities = DB::table('N_HRIS_Menu_Capabilities')
                ->where('Jenis_page', $jenisPage)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->keyBy('Id_Aksi');

            $menuInfo = DB::table('N_HRIS_Menus')
                ->where('Jenis_page', $jenisPage)
                ->first(['Jenis_page', 'Nama_Menu', 'Header_Label']);

            $matrix = $allActions->map(
                fn ($action) => [
                    'id_aksi' => $action->Id_Klasifikasi_Actions,
                    'nama_aksi' => $action->Nama_Aksi,
                    'enabled' => $capabilities->has($action->Id_Klasifikasi_Actions),
                    'is_active_default' => $capabilities->has($action->Id_Klasifikasi_Actions)
                        ? $capabilities[$action->Id_Klasifikasi_Actions]->Is_Active_Default
                        : 'T',
                ],
            );

            return response()->json([
                'menu' => $menuInfo,
                'capabilities' => $matrix,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat capabilities: '.$e->getMessage()], 500);
        }
    }

    public function saveMenuCapabilities(Request $request, $jenisPage)
    {
        $actor = $request->user();
        abort_unless(
            $actor && ! empty(AuthorizationScopeResolver::resolve($actor)['isSystemAdmin']),
            403,
            'Hanya System Admin yang dapat mengubah default capabilities menu.',
        );

        $validated = $request->validate([
            'capabilities' => 'present|array',
            'capabilities.*.id_aksi' => 'required|integer|min:1',
            'capabilities.*.enabled' => 'required|boolean',
            'capabilities.*.is_active_default' => 'required|string|in:Y,T',
        ]);

        $menuExists = DB::table('N_HRIS_Menus')
            ->where('Jenis_page', $jenisPage)
            ->exists();
        abort_unless($menuExists, 404, 'Menu tidak ditemukan.');

        $actionIds = collect($validated['capabilities'])
            ->pluck('id_aksi')
            ->map(fn ($id) => (int) $id);

        abort_unless(
            $actionIds->count() === $actionIds->unique()->count(),
            422,
            'Satu action tidak boleh dikirim lebih dari satu kali.',
        );

        if ($actionIds->isNotEmpty()) {
            $knownActionIds = DB::table('N_HRIS_Klasifikasi_Aksi')
                ->whereIn('Id_Klasifikasi_Actions', $actionIds->all())
                ->pluck('Id_Klasifikasi_Actions')
                ->map(fn ($id) => (int) $id);

            $unknownActionIds = $actionIds->diff($knownActionIds)->values();
            abort_unless(
                $unknownActionIds->isEmpty(),
                422,
                'Action tidak terdaftar: '.$unknownActionIds->implode(', '),
            );
        }

        // Review RBAC Fase 5: apakah halaman ini sudah punya aksi tulis sebelum disimpan.
        $idView = (int) DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        $adaTulisSebelum = DB::table('N_HRIS_Menu_Capabilities')->where('Jenis_page', $jenisPage)
            ->where('Flag_Aktif', 'Y')->where('Id_Aksi', '<>', $idView)->exists();

        DB::beginTransaction();
        try {
            // Soft-delete all existing
            DB::table('N_HRIS_Menu_Capabilities')
                ->where('Jenis_page', $jenisPage)
                ->update(['Flag_Aktif' => 'T']);

            // Insert enabled ones
            $inserts = [];
            foreach ($validated['capabilities'] as $cap) {
                if ($cap['enabled']) {
                    $inserts[] = [
                        'Jenis_page' => $jenisPage,
                        'Id_Aksi' => $cap['id_aksi'],
                        'Is_Active_Default' => $cap['is_active_default'],
                        'Flag_Aktif' => 'Y',
                    ];
                }
            }

            if (! empty($inserts)) {
                PenyisipanBerbatasParameter::sisipkan('N_HRIS_Menu_Capabilities', $inserts);
            }

            DB::commit();

            // Modul BARU: pemegang VIEW jangan tiba-tiba terkunci dari aksi tulis.
            app(\App\Services\SYSTEM\Governance\Authorization\AksesPeran\KatalogAksesService::class)->pertahankanTulis(
                $jenisPage,
                $adaTulisSebelum,
                collect($inserts)->pluck('Id_Aksi')->map(fn ($a) => (int) $a)->reject(fn ($a) => $a === $idView)->values()->all(),
            );

            AuthorizationAuditLogger::logMutation([
                'event' => 'menu_capabilities.default_updated',
                'actor_user_id' => $actor->Id_Users ?? $actor->id ?? null,
                'jenis_page' => $jenisPage,
                'affected_count' => count($inserts),
                'new_value' => 'default_configuration_saved',
            ]);

            return response()->json(['message' => 'Capabilities berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan capabilities: '.$e->getMessage()], 500);
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
    // RESOURCE ACCESS — Role Resource Access CRUD
    // ========================================================================

    public function getResourceAccessFilters(Request $request)
    {
        try {
            $scope = $this->resolveResourceAccessScope($request->user());
            if (! $scope['isSystemAdmin'] && empty($scope['allowedRoleIds'])) {
                return response()->json(
                    ['message' => 'Anda tidak memiliki group kelolaan untuk resource access.'],
                    403,
                );
            }

            $roleQuery = DB::table('N_HRIS_Role_Functional as rf')
                ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                ->where('rf.Flag_Aktif', 'Y')
                ->select(
                    'rf.Id_Role',
                    'rf.Role_Code',
                    'rf.Role_Name',
                    'rf.Id_Grup_Fitur',
                    'gf.Kode_Grup_Fitur as module_code',
                    'gf.Nama_Grup_Fitur as module_name',
                )
                ->orderBy('gf.Kode_Grup_Fitur')
                ->orderBy('rf.Role_Name');

            if (! $scope['isSystemAdmin']) {
                $roleQuery->whereIn('rf.Id_Role', $scope['allowedRoleIds']);
            }

            $contexts = DB::table('N_HRIS_Role_Resource_Access')
                ->select('Context_Code')
                ->where('Flag_Aktif', 'Y')
                ->distinct()
                ->orderBy('Context_Code')
                ->pluck('Context_Code')
                ->filter()
                ->values()
                ->toArray();

            if (! in_array('admin', $contexts, true)) {
                array_unshift($contexts, 'admin');
            }

            $resourceTypes = [
                ['value' => 'tab', 'label' => 'Tab'],
                ['value' => 'capability', 'label' => 'Capability Aksi'],
                ['value' => 'page', 'label' => 'Page'],
                ['value' => 'menu', 'label' => 'Menu'],
                ['value' => 'feature', 'label' => 'Feature'],
                ['value' => 'menu-delegation', 'label' => 'Delegasi Menu'],
            ];

            return response()->json([
                'scope' => $scope,
                'roles' => $roleQuery->get(),
                'contexts' => $contexts,
                'resourceTypes' => $resourceTypes,
                'menus' => DB::table('N_HRIS_Menus')
                    ->select('Jenis_page', 'Nama_Menu', 'Header_Label')
                    ->orderBy('Header_Label')
                    ->orderBy('Nama_Menu')
                    ->get(),
                'resourceRegistry' => $this->resourceRegistryService->options(),
                'hasJenisPageColumn' => SkemaTersedia::kolom('N_HRIS_Role_Resource_Access', 'Jenis_page'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat filter resource access: '.$e->getMessage()], 500);
        }
    }

    public function getResourceAccess(Request $request)
    {
        try {
            $scope = $this->resolveResourceAccessScope($request->user());
            if (! $scope['isSystemAdmin'] && empty($scope['allowedRoleIds'])) {
                return response()->json(
                    ['message' => 'Anda tidak memiliki group kelolaan untuk resource access.'],
                    403,
                );
            }

            $validated = $request->validate([
                'context_code' => 'nullable|string|max:50',
                'jenis_page' => 'nullable|string|max:150',
                'resource_type' => 'nullable|string|max:50',
                'role_id' => 'nullable|integer',
                'search' => 'nullable|string|max:100',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $hasJenisPageColumn = SkemaTersedia::kolom('N_HRIS_Role_Resource_Access', 'Jenis_page');
            $query = DB::table('N_HRIS_Role_Resource_Access as rra')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'rra.Id_Role_Functional')
                ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                ->where('rra.Flag_Aktif', 'Y')
                ->select(
                    'rra.Id_Role_Resource_Access',
                    'rra.Id_Role_Functional',
                    'rra.Context_Code',
                    'rra.Resource_Type',
                    'rra.Resource_Code',
                    'rra.Resource_Name',
                    'rra.Flag_Diizinkan',
                    'rra.Flag_Aktif',
                    'rra.Sort_Order',
                    'rra.Created_At',
                    'rra.Updated_At',
                    'rf.Role_Code',
                    'rf.Role_Name',
                    'gf.Kode_Grup_Fitur as module_code',
                    'gf.Nama_Grup_Fitur as module_name',
                    $hasJenisPageColumn ? 'rra.Jenis_page' : DB::raw('NULL as Jenis_page'),
                    $hasJenisPageColumn ? 'm.Nama_Menu as menu_name' : DB::raw('NULL as menu_name'),
                    $hasJenisPageColumn ? 'm.Header_Label as menu_header' : DB::raw('NULL as menu_header'),
                )
                ->orderBy('rra.Sort_Order')
                ->orderBy('rf.Role_Name')
                ->orderBy('rra.Resource_Code');

            if ($hasJenisPageColumn) {
                $query->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'rra.Jenis_page');
            }

            if (! $scope['isSystemAdmin']) {
                $query->whereIn('rra.Id_Role_Functional', $scope['allowedRoleIds']);
            }

            if (! empty($validated['context_code'])) {
                $query->where('rra.Context_Code', $validated['context_code']);
            }

            if ($hasJenisPageColumn && ! empty($validated['jenis_page'])) {
                $query->where('rra.Jenis_page', $validated['jenis_page']);
            }

            if (! empty($validated['resource_type'])) {
                $query->where('rra.Resource_Type', $validated['resource_type']);
            }

            if (! empty($validated['role_id'])) {
                $query->where('rra.Id_Role_Functional', $validated['role_id']);
            }

            if (! empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('rra.Resource_Code', 'LIKE', "%{$search}%")
                        ->orWhere('rra.Resource_Name', 'LIKE', "%{$search}%")
                        ->orWhere('rf.Role_Code', 'LIKE', "%{$search}%")
                        ->orWhere('rf.Role_Name', 'LIKE', "%{$search}%");
                });
            }

            $result = $query->paginate((int) ($validated['per_page'] ?? 20));
            /* Keanggotaan registry diambil SEKALI untuk seluruh halaman.
               isRegistered() menembak satu kueri `exists` tiap kali dipanggil,
               jadi memanggilnya di dalam map() menghasilkan 20 kueri untuk satu
               halaman — dan angkanya naik lurus mengikuti ukuran halaman. */
            $barisRegistry = collect($result->items())
                ->map(fn ($item) => [
                    $item->Jenis_page ?? null,
                    (string) $item->Resource_Type,
                    (string) $item->Resource_Code,
                ])
                ->all();
            $terdaftar = $this->resourceRegistryService->terdaftarMassal($barisRegistry);

            $items = collect($result->items())->map(function ($item) use ($terdaftar) {
                $item->master_registered = $terdaftar[AuthorizationResourceRegistryService::kunciTerdaftar(
                    $item->Jenis_page ?? null,
                    (string) $item->Resource_Type,
                    (string) $item->Resource_Code,
                )] ?? false;

                return $item;
            })->values();

            return response()->json([
                'data' => $items,
                'pagination' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            /* Validasi tak dinaikkan ke luar try supaya gerbang 403 di atas tetap
               dijawab lebih dulu — penanya yang tak berhak tak perlu tahu bentuk
               parameternya. Tanpa klausa ini ia tertangkap catch \Exception di
               bawah dan input yang salah dilaporkan 500, bukan 422. */
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat resource access: '.$e->getMessage()], 500);
        }
    }

    public function saveResourceAccess(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'Id_Role_Functional' => 'required|integer',
            'Context_Code' => 'required|string|max:50',
            'Jenis_page' => 'nullable|string|max:150',
            'Resource_Type' => 'required|string|max:50',
            'Resource_Code' => 'nullable|string|max:255',
            'Resource_Codes' => 'nullable|array',
            'Resource_Codes.*' => 'nullable|string|max:255',
            'Resource_Name' => 'nullable|string|max:255',
            'Flag_Diizinkan' => 'required|string|in:Y,T',
            'Flag_Aktif' => 'required|string|in:Y,T',
            'Sort_Order' => 'nullable|integer',
        ]);

        try {
            $scope = $this->resolveResourceAccessScope($request->user());
            if (
                ! $scope['isSystemAdmin'] &&
                ! in_array((int) $validated['Id_Role_Functional'], $scope['allowedRoleIds'], true)
            ) {
                return response()->json(['message' => 'Anda tidak berwenang mengubah resource untuk role ini.'], 403);
            }

            $hasJenisPageColumn = SkemaTersedia::kolom('N_HRIS_Role_Resource_Access', 'Jenis_page');

            $normalizedCodes = collect($validated['Resource_Codes'] ?? [])
                ->map(fn ($code) => trim((string) $code))
                ->filter(fn ($code) => $code !== '')
                ->unique()
                ->values();

            if ($normalizedCodes->isEmpty() && ! empty($validated['Resource_Code'])) {
                $normalizedCodes = collect([trim((string) $validated['Resource_Code'])])->filter(
                    fn ($code) => $code !== '',
                );
            }

            if ($normalizedCodes->isEmpty()) {
                return response()->json(['message' => 'Resource code wajib diisi minimal 1 item.'], 422);
            }

            foreach ($normalizedCodes as $code) {
                $this->resourceRegistryService->assertStorableRegistered(
                    $request->user(),
                    $hasJenisPageColumn ? ($validated['Jenis_page'] ?? null) : null,
                    $validated['Resource_Type'],
                    (string) $code,
                    'save-resource-access',
                );
            }

            $buildName = static fn (string $code): string => strtoupper(str_replace('.', ' ', $code));

            if (! empty($validated['id'])) {
                $code = (string) $normalizedCodes->first();
                $payload = [
                    'Id_Role_Functional' => $validated['Id_Role_Functional'],
                    'Context_Code' => $validated['Context_Code'],
                    'Resource_Type' => $validated['Resource_Type'],
                    'Resource_Code' => $code,
                    'Resource_Name' => $buildName($code),
                    'Flag_Diizinkan' => $validated['Flag_Diizinkan'],
                    'Flag_Aktif' => $validated['Flag_Aktif'],
                    'Sort_Order' => $validated['Sort_Order'] ?? 0,
                    'Updated_At' => now(),
                ];

                if ($hasJenisPageColumn) {
                    $payload['Jenis_page'] = $validated['Jenis_page'] ?? null;
                }

                DB::table('N_HRIS_Role_Resource_Access')
                    ->where('Id_Role_Resource_Access', $validated['id'])
                    ->update($payload);

                return response()->json(['message' => 'Resource access berhasil diperbarui.']);
            }

            /*
             * SATU pemeriksaan untuk SELURUH kode, lalu satu transaksi pendek.
             *
             * Sebelumnya blok ini melakukan cek-lalu-tulis PER KODE di dalam
             * loop: satu SELECT dan satu tulis untuk masing-masing, jadi 2N
             * perjalanan ke basis data untuk satu klik "simpan".
             *
             * Dua akibatnya, dan yang kedua lebih serius:
             *
             *   - Biayanya tumbuh linear pada jumlah kode, dan di sistem ini
             *     perjalanan itulah yang mahal, bukan kuerinya.
             *   - Tidak ada transaksi sama sekali. Gagal di kode ke-5 dari 10
             *     meninggalkan peran dengan setengah set izin, dan admin tidak
             *     diberi tahu mana yang mendarat.
             *
             * Yang tetap dipertahankan: hitungan created/updated di responsnya,
             * karena layarnya menampilkannya.
             */
            $jenisPage = $hasJenisPageColumn ? ($validated['Jenis_page'] ?? null) : null;

            /*
             * Batas 2.100 parameter SQL Server: whereIn dipotong supaya jumlah
             * kode yang besar tidak menjadi galat yang menyamar sebagai galat
             * basis data.
             */
            $existingIds = [];
            foreach ($normalizedCodes->chunk(500) as $potongan) {
                $q = DB::table('N_HRIS_Role_Resource_Access')
                    ->where('Id_Role_Functional', $validated['Id_Role_Functional'])
                    ->where('Context_Code', $validated['Context_Code'])
                    ->where('Resource_Type', $validated['Resource_Type'])
                    ->whereIn('Resource_Code', $potongan->all());

                if ($hasJenisPageColumn) {
                    $q->where(function ($w) use ($jenisPage) {
                        if (! empty($jenisPage)) {
                            $w->where('Jenis_page', $jenisPage);
                        } else {
                            $w->whereNull('Jenis_page');
                        }
                    });
                }

                foreach ($q->get(['Id_Role_Resource_Access', 'Resource_Code']) as $row) {
                    $existingIds[(string) $row->Resource_Code] = $row->Id_Role_Resource_Access;
                }
            }

            $barisBaru = [];
            $perluDiperbarui = [];

            foreach ($normalizedCodes as $index => $code) {
                $payload = [
                    'Id_Role_Functional' => $validated['Id_Role_Functional'],
                    'Context_Code' => $validated['Context_Code'],
                    'Resource_Type' => $validated['Resource_Type'],
                    'Resource_Code' => $code,
                    'Resource_Name' => $buildName($code),
                    'Flag_Diizinkan' => $validated['Flag_Diizinkan'],
                    'Flag_Aktif' => $validated['Flag_Aktif'],
                    'Sort_Order' => ($validated['Sort_Order'] ?? 0) + $index,
                    'Updated_At' => now(),
                ];

                if ($hasJenisPageColumn) {
                    $payload['Jenis_page'] = $jenisPage;
                }

                if (isset($existingIds[(string) $code])) {
                    $perluDiperbarui[] = [$existingIds[(string) $code], $payload];

                    continue;
                }

                $payload['Created_At'] = now();
                $barisBaru[] = $payload;
            }

            $created = count($barisBaru);
            $updated = count($perluDiperbarui);

            /*
             * Transaksi membungkus HANYA penulisannya. Pemeriksaannya sudah
             * selesai di atas, jadi kunci ditahan sesingkat mungkin — penting
             * karena RCSI mati di basis data ini dan baris yang di-UPDATE
             * menahan pembaca.
             *
             * Balapan dengan permintaan lain tetap mungkin, dan itu memang
             * ditangani di lapisan lain: UQ_NHRIS_RRA_RoleContextTypeCode
             * menolak baris kembar, transaksinya batal seluruhnya, dan admin
             * mendapat galat alih-alih separuh set izin.
             */
            DB::transaction(function () use ($barisBaru, $perluDiperbarui) {
                // Ukuran potongan dihitung dari lebar barisnya (9-10 kolom di sini),
                // supaya aritmetikanya tidak basi saat kolomnya bertambah.
                PenyisipanBerbatasParameter::sisipkan('N_HRIS_Role_Resource_Access', $barisBaru);

                foreach ($perluDiperbarui as [$id, $payload]) {
                    DB::table('N_HRIS_Role_Resource_Access')
                        ->where('Id_Role_Resource_Access', $id)
                        ->update($payload);
                }
            });

            return response()->json([
                'message' => "Resource access berhasil diproses. Created: {$created}, Updated: {$updated}",
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan resource access: '.$e->getMessage()], 500);
        }
    }

    public function deleteResourceAccess($id)
    {
        try {
            $scope = $this->resolveResourceAccessScope(request()->user());
            $record = DB::table('N_HRIS_Role_Resource_Access')->where('Id_Role_Resource_Access', $id)->first();

            if (! $record) {
                return response()->json(['message' => 'Resource access tidak ditemukan.'], 404);
            }

            if (
                ! $scope['isSystemAdmin'] &&
                ! in_array((int) $record->Id_Role_Functional, $scope['allowedRoleIds'], true)
            ) {
                return response()->json(['message' => 'Anda tidak berwenang menghapus resource ini.'], 403);
            }

            DB::table('N_HRIS_Role_Resource_Access')
                ->where('Id_Role_Resource_Access', $id)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                ]);

            return response()->json(['message' => 'Resource access berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus resource access: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // ACCESS DISTRIBUTION
    // ========================================================================

    public function getDistributionFilters()
    {
        try {
            $divisi = DB::table('View_Divisi_Sub_Divisi')
                ->select('ID_Divisi', 'nama_divisi')
                ->distinct()
                ->orderBy('nama_divisi')
                ->get();

            $level = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                ->select('ID_Level', 'nama_level')
                ->distinct()
                ->orderBy('nama_level')
                ->get();

            $jabatan = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                ->select('ID_Jabatan', 'nama_jabatan')
                ->distinct()
                ->orderBy('nama_jabatan')
                ->get();

            $menus = DB::table('N_HRIS_Menus as m')
                ->leftJoin('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'm.Jenis_page')
                ->leftJoin('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
                ->select(
                    'm.Jenis_page',
                    'm.Nama_Menu',
                    'm.Header_Label',
                    'm.Header_Icon',
                    'm.Icon_Menu',
                    'gf.Id_Grup_Fitur',
                    'gf.Kode_Grup_Fitur',
                    DB::raw(
                        '(SELECT COUNT(*) FROM N_HRIS_Menu_Capabilities mc WHERE mc.Jenis_page = m.Jenis_page AND mc.Flag_Aktif = \'Y\') as cap_count',
                    ),
                )
                ->when($this->getMenuActiveFlagColumn() !== null, function ($query) {
                    $activeColumn = $this->getMenuActiveFlagColumn();
                    $query->where("m.{$activeColumn}", 'Y');
                })
                ->orderBy('m.Header_Label')
                ->orderBy('m.Nama_Menu')
                ->get();

            return response()->json([
                'divisi' => $divisi,
                'level' => $level,
                'jabatan' => $jabatan,
                'menus' => $menus,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat filter: '.$e->getMessage()], 500);
        }
    }

    public function getDistributionCurrentAccess(Request $request)
    {
        $validated = $request->validate([
            'grup_id' => 'nullable|integer|min:0',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer',
        ]);

        try {
            $userIds = collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $grupId = (int) ($validated['grup_id'] ?? 0);
            if ($grupId > 0) {
                $this->targetScopeService->assertUserIdsAllowed($request->user(), $grupId, $userIds);
            }

            $rows = DB::table('HRIS_Page_Access as pa')
                ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'pa.Jenis_page')
                ->whereIn('pa.UserID_Web', $userIds)
                ->where('pa.Flag_Aktif', 'Y')
                ->select(
                    'pa.UserID_Web',
                    'pa.Jenis_page',
                    'm.Nama_Menu',
                    'm.Header_Label',
                    'm.Header_Icon',
                    'm.Icon_Menu',
                )
                ->orderBy('pa.UserID_Web')
                ->orderBy('m.Header_Label')
                ->orderBy('m.Nama_Menu')
                ->get();

            $byUser = [];
            foreach ($userIds as $uid) {
                $byUser[(string) $uid] = [];
            }

            foreach ($rows as $row) {
                $userKey = (string) $row->UserID_Web;
                $byUser[$userKey][] = [
                    'Jenis_page' => $row->Jenis_page,
                    'Nama_Menu' => $row->Nama_Menu,
                    'Header_Label' => $row->Header_Label,
                    'Header_Icon' => $row->Header_Icon,
                    'Icon_Menu' => $row->Icon_Menu,
                ];
            }

            return response()->json(['accessByUser' => $byUser]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat akses existing: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // PAGE ACCESS MANAGER
    // ========================================================================

    public function getPageAccessMenus(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'header_code' => 'nullable|string|max:100',
            'is_global' => 'nullable|string|in:Y,T',
        ]);

        try {
            $activeFlag = $this->getMenuActiveFlagColumn();

            $query = DB::table('N_HRIS_Menus as m')->select(
                'm.Id_Menu',
                'm.Jenis_page',
                'm.Kode_Perusahaan',
                'm.Nama_Menu',
                'm.Icon_Menu',
                'm.Url_Menu',
                'm.Header_Code',
                'm.Header_Label',
                'm.Header_Icon',
                'm.Sort_Header',
                'm.Sort_Menu',
                'm.Is_Global_Menu',
            );

            if ($activeFlag !== null) {
                $query->where("m.{$activeFlag}", 'Y');
            }

            if (! empty($validated['search'])) {
                $search = trim((string) $validated['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('m.Jenis_page', 'LIKE', "%{$search}%")
                        ->orWhere('m.Nama_Menu', 'LIKE', "%{$search}%")
                        ->orWhere('m.Header_Label', 'LIKE', "%{$search}%")
                        ->orWhere('m.Header_Code', 'LIKE', "%{$search}%")
                        ->orWhere('m.Url_Menu', 'LIKE', "%{$search}%");
                });
            }

            if (! empty($validated['header_code'])) {
                $query->where('m.Header_Code', $validated['header_code']);
            }

            if (! empty($validated['is_global'])) {
                $query->where('m.Is_Global_Menu', $validated['is_global']);
            }

            $rows = $query
                ->orderByRaw('COALESCE(NULLIF(m.Sort_Header, 0), 999) ASC')
                ->orderByRaw('CASE WHEN m.Sort_Menu IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('m.Sort_Menu')
                ->orderBy('m.Nama_Menu')
                ->get();

            return response()->json([
                'data' => $rows,
                'headers' => $this->buildHeaderOptions($rows),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat menu page access: '.$e->getMessage()], 500);
        }
    }

    public function getPageAccessHeaders()
    {
        try {
            $activeFlag = $this->getMenuActiveFlagColumn();

            $query = DB::table('N_HRIS_Menus as m')
                ->select('m.Header_Code', 'm.Header_Label', 'm.Header_Icon', 'm.Sort_Header')
                ->whereNotNull('m.Header_Code');

            if ($activeFlag !== null) {
                $query->where("m.{$activeFlag}", 'Y');
            }

            $rows = $query->get();

            return response()->json(['data' => $this->buildHeaderOptions($rows)]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat header menu: '.$e->getMessage()], 500);
        }
    }

    public function updatePageAccessHeader(Request $request, $headerCode)
    {
        $validated = $request->validate([
            'Header_Code' => 'required|string|max:100',
            'Header_Label' => 'required|string|max:150',
            'Header_Icon' => 'nullable|string|max:100',
            'Sort_Header' => 'nullable|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $oldCode = $this->normalizeHeaderCode((string) $headerCode);
            $newCode = $this->normalizeHeaderCode((string) $validated['Header_Code']);
            $newLabel = trim((string) $validated['Header_Label']);
            $newIcon = $this->normalizeIconClass($validated['Header_Icon'] ?? null, 'bi bi-folder2-open');
            $newSortHeader = ! empty($validated['Sort_Header']) ? (int) $validated['Sort_Header'] : null;

            if ($oldCode === '' || $newCode === '' || $newLabel === '') {
                DB::rollBack();

                return response()->json(['message' => 'Header code dan header label wajib valid.'], 422);
            }

            $baseQuery = DB::table('N_HRIS_Menus')->where('Header_Code', $oldCode);
            $menuCount = (clone $baseQuery)->count();
            if ($menuCount === 0) {
                DB::rollBack();

                return response()->json(['message' => 'Header tidak ditemukan.'], 404);
            }

            if ($oldCode !== $newCode) {
                $newCodeExists = DB::table('N_HRIS_Menus')->where('Header_Code', $newCode)->exists();
                if ($newCodeExists) {
                    DB::rollBack();

                    return response()->json(['message' => 'Header code tujuan sudah digunakan header lain.'], 422);
                }
            }

            DB::table('N_HRIS_Menus')
                ->where('Header_Code', $oldCode)
                ->update([
                    'Header_Code' => $newCode,
                    'Header_Label' => $newLabel,
                    'Header_Icon' => $newIcon,
                    'Sort_Header' => $newSortHeader,
                ]);

            DB::commit();

            InertiaShellService::flushMenuCache();

            return response()->json([
                'message' => 'Header berhasil diperbarui untuk semua menu terkait.',
                'updated_menus' => $menuCount,
                'old_header_code' => $oldCode,
                'new_header_code' => $newCode,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal memperbarui header: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function createPageAccessMenu(Request $request)
    {
        $validated = $this->validatePageAccessMenuPayload($request);

        DB::beginTransaction();
        try {
            $normalized = $this->normalizePageAccessMenuPayload($validated);

            if ($normalized['Jenis_page'] === '') {
                DB::rollBack();

                return response()->json(['message' => 'Jenis page tidak valid setelah normalisasi.'], 422);
            }

            if (empty($normalized['Header_Label'])) {
                DB::rollBack();

                return response()->json(['message' => 'Header label wajib diisi.'], 422);
            }

            $existingJenis = DB::table('N_HRIS_Menus')
                ->whereRaw('LOWER(LTRIM(RTRIM(Jenis_page))) = ?', [strtolower($normalized['Jenis_page'])])
                ->exists();

            if ($existingJenis) {
                DB::rollBack();

                return response()->json(['message' => 'Jenis page sudah digunakan.'], 422);
            }

            if ($normalized['Sort_Menu'] === null) {
                $normalized['Sort_Menu'] = $this->nextSortMenu($normalized['Header_Code']);
            }

            $conflicts = $this->validateSortConflicts(
                $normalized['Header_Code'],
                $normalized['Sort_Menu'],
                null,
            );

            if (! empty($conflicts)) {
                DB::rollBack();

                return response()->json(['message' => 'Terdapat konflik urutan.', 'conflicts' => $conflicts], 422);
            }

            $id = DB::table('N_HRIS_Menus')->insertGetId($normalized);

            DB::commit();

            InertiaShellService::flushMenuCache();

            return response()->json([
                'message' => 'Menu page access berhasil dibuat.',
                'id' => $id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal membuat menu page access: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function updatePageAccessMenu(Request $request, $id)
    {
        $validated = $this->validatePageAccessMenuPayload($request, true);

        DB::beginTransaction();
        try {
            $existing = DB::table('N_HRIS_Menus')->where('Id_Menu', (int) $id)->first();
            if (! $existing) {
                DB::rollBack();

                return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
            }

            $normalized = $this->normalizePageAccessMenuPayload($validated);

            if ($normalized['Jenis_page'] === '') {
                DB::rollBack();

                return response()->json(['message' => 'Jenis page tidak valid setelah normalisasi.'], 422);
            }

            if (empty($normalized['Header_Label'])) {
                DB::rollBack();

                return response()->json(['message' => 'Header label wajib diisi.'], 422);
            }

            $duplicateJenis = DB::table('N_HRIS_Menus')
                ->where('Id_Menu', '!=', (int) $id)
                ->whereRaw('LOWER(LTRIM(RTRIM(Jenis_page))) = ?', [strtolower($normalized['Jenis_page'])])
                ->exists();

            if ($duplicateJenis) {
                DB::rollBack();

                return response()->json(['message' => 'Jenis page sudah digunakan menu lain.'], 422);
            }

            if ($normalized['Sort_Menu'] === null) {
                $normalized['Sort_Menu'] = $this->nextSortMenu($normalized['Header_Code'], (int) $id);
            }

            $conflicts = strtoupper(trim((string) ($existing->Is_Show_Sidebar ?? 'Y'))) === 'Y'
                ? $this->validateSortConflicts(
                    $normalized['Header_Code'],
                    $normalized['Sort_Menu'],
                    (int) $id,
                )
                : [];

            if (! empty($conflicts)) {
                DB::rollBack();

                return response()->json(['message' => 'Terdapat konflik urutan.', 'conflicts' => $conflicts], 422);
            }

            DB::table('N_HRIS_Menus')->where('Id_Menu', (int) $id)->update($normalized);

            DB::commit();

            InertiaShellService::flushMenuCache();

            return response()->json(['message' => 'Menu page access berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal memperbarui menu page access: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function validatePageAccessSort(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.Id_Menu' => 'required|integer',
            'rows.*.Header_Code' => 'required|string|max:100',
            'rows.*.Header_Label' => 'nullable|string|max:150',
            'rows.*.Sort_Header' => 'nullable|integer|min:1',
            'rows.*.Sort_Menu' => 'nullable|integer|min:1',
        ]);

        try {
            $rows = collect($validated['rows']);
            $rowIds = $rows
                ->pluck('Id_Menu')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $visibleRowIds = DB::table('N_HRIS_Menus')
                ->whereIn('Id_Menu', $rowIds)
                ->where('Is_Show_Sidebar', 'Y')
                ->pluck('Id_Menu')
                ->map(fn ($id) => (int) $id)
                ->all();

            $visibleRows = $rows->filter(
                fn ($row) => in_array((int) $row['Id_Menu'], $visibleRowIds, true),
            );

            $menuDup = $visibleRows
                ->filter(fn ($row) => ! empty($row['Sort_Menu']))
                ->groupBy(fn ($row) => trim((string) $row['Header_Code']).'|'.(int) $row['Sort_Menu'])
                ->filter(fn ($group) => $group->count() > 1)
                ->map(function ($group, $key) {
                    [$headerCode, $sortMenu] = explode('|', $key);

                    return [
                        'type' => 'menu_sort_duplicate_payload',
                        'header_code' => $headerCode,
                        'sort_menu' => (int) $sortMenu,
                        'menu_ids' => $group->pluck('Id_Menu')->values(),
                    ];
                })
                ->values();

            $dbConflicts = [];
            foreach ($visibleRows as $row) {
                $dbConflicts = array_merge(
                    $dbConflicts,
                    $this->validateSortConflicts(
                        trim((string) $row['Header_Code']),
                        ! empty($row['Sort_Menu']) ? (int) $row['Sort_Menu'] : null,
                        (int) $row['Id_Menu'],
                        $visibleRowIds,
                    ),
                );
            }

            $dbConflicts = array_values(
                collect($dbConflicts)
                    ->unique(fn ($conflict) => json_encode($conflict))
                    ->values()
                    ->all(),
            );

            return response()->json([
                'valid' => $menuDup->isEmpty() && empty($dbConflicts),
                'conflicts' => array_values(array_merge($menuDup->all(), $dbConflicts)),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal validasi sort: '.$e->getMessage()], 500);
        }
    }

    public function applyPageAccessSort(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.Id_Menu' => 'required|integer',
            'rows.*.Header_Code' => 'required|string|max:100',
            'rows.*.Header_Label' => 'nullable|string|max:150',
            'rows.*.Header_Icon' => 'nullable|string|max:100',
            'rows.*.Sort_Header' => 'nullable|integer|min:1',
            'rows.*.Sort_Menu' => 'nullable|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $rows = collect($validated['rows']);
            $validateResponse = $this->validatePageAccessSort($request);
            $payload = $validateResponse->getData(true);

            if (! ($payload['valid'] ?? false)) {
                DB::rollBack();

                return response()->json(
                    [
                        'message' => 'Terdapat konflik sort, periksa kembali angka urutan.',
                        'conflicts' => $payload['conflicts'] ?? [],
                    ],
                    422,
                );
            }

            foreach ($rows as $row) {
                DB::table('N_HRIS_Menus')
                    ->where('Id_Menu', (int) $row['Id_Menu'])
                    ->update([
                        'Header_Code' => $this->normalizeHeaderCode((string) $row['Header_Code']),
                        'Header_Label' => ! empty($row['Header_Label']) ? trim((string) $row['Header_Label']) : null,
                        'Header_Icon' => $this->normalizeIconClass($row['Header_Icon'] ?? null, 'bi bi-folder2-open'),
                        'Sort_Header' => ! empty($row['Sort_Header']) ? (int) $row['Sort_Header'] : null,
                        'Sort_Menu' => ! empty($row['Sort_Menu']) ? (int) $row['Sort_Menu'] : null,
                    ]);
            }

            DB::commit();

            InertiaShellService::flushMenuCache();

            return response()->json(['message' => 'Urutan header dan menu berhasil disimpan.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan urutan: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function getDistributionTargets(Request $request)
    {
        $validated = $request->validate([
            'grup_id' => 'nullable|integer|min:0',
            'filter_divisi' => 'nullable|array',
            'filter_divisi.*' => 'nullable|integer',
            'filter_level' => 'nullable|array',
            'filter_level.*' => 'nullable|integer',
            'filter_jabatan' => 'nullable|array',
            'filter_jabatan.*' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:300',
            'all' => 'nullable|boolean',
        ]);

        try {
            $perPage = (int) ($validated['per_page'] ?? 100);
            $search = trim((string) ($validated['search'] ?? ''));
            $fetchAll = filter_var($request->input('all', false), FILTER_VALIDATE_BOOLEAN);

            $query = DB::table('Karyawan as k')
                ->join('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vl.ID_Level_Jabatan',
                )
                ->where('k.Aktif', 'Y');
            $this->targetScopeService->applyToQuery($query, $request->user(), (int) ($validated['grup_id'] ?? 0), 'k');

            if (! empty($validated['filter_divisi'])) {
                $query->whereIn('vd.ID_Divisi', $validated['filter_divisi']);
            }

            if (! empty($validated['filter_level'])) {
                $query->whereIn('vl.ID_Level', $validated['filter_level']);
            }

            if (! empty($validated['filter_jabatan'])) {
                $query->whereIn('vl.ID_Jabatan', $validated['filter_jabatan']);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")
                        ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$search}%")
                        ->orWhere('u.Username', 'LIKE', "%{$search}%");
                });
            }

            $baseSelect = $query
                ->select(
                    'k.UserID_Web as id',
                    'k.Nama as nama',
                    'k.Kode_Karyawan as kode',
                    'vd.nama_divisi as divisi',
                    'vl.nama_level as level',
                    'vl.nama_jabatan as jabatan',
                )
                ->distinct()
                ->orderBy('k.Nama');

            if ($fetchAll) {
                $allRows = $baseSelect->get();

                return response()->json([
                    'data' => $allRows,
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $allRows->count(),
                        'total' => $allRows->count(),
                    ],
                ]);
            }

            $result = $baseSelect->paginate($perPage);

            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                ],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat target distribusi: '.$e->getMessage()], 500);
        }
    }

    public function previewDistribution(Request $request)
    {
        $validated = $request->validate([
            'grup_id' => 'nullable|integer|min:0',
            'mode' => 'required|in:individual,bulk',
            'user_ids' => 'required_if:mode,individual|array',
            'filter_divisi' => 'nullable|array',
            'filter_level' => 'nullable|array',
            'filter_jabatan' => 'nullable|array',
            'pages' => 'required|array|min:1',
            'pages.*' => 'string|max:100',
        ]);

        try {
            $userIds = $this->resolveTargetUsers($validated);
            $grupId = (int) ($validated['grup_id'] ?? 0);
            if ($grupId > 0) {
                $this->targetScopeService->assertUserIdsAllowed($request->user(), $grupId, $userIds);
                $this->menuDelegationPolicyService->assertPagesAllowed($request->user(), $grupId, $validated['pages']);
            }

            // Count how many already have access per page
            $existingCount = DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $userIds)
                ->whereIn('Jenis_page', $validated['pages'])
                ->where('Flag_Aktif', 'Y')
                ->count();

            $totalPossible = count($userIds) * count($validated['pages']);

            return response()->json([
                'user_count' => count($userIds),
                'menu_count' => count($validated['pages']),
                'total_records' => $totalPossible,
                'existing_skip' => $existingCount,
                'new_records' => $totalPossible - $existingCount,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal preview: '.$e->getMessage()], 500);
        }
    }

    public function executeDistribution(Request $request)
    {
        $validated = $request->validate([
            'grup_id' => 'nullable|integer|min:0',
            'mode' => 'required|in:individual,bulk',
            'user_ids' => 'required_if:mode,individual|array',
            'filter_divisi' => 'nullable|array',
            'filter_level' => 'nullable|array',
            'filter_jabatan' => 'nullable|array',
            'pages' => 'required|array|min:1',
            'pages.*' => 'string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $userIds = $this->resolveTargetUsers($validated);
            $pages = $validated['pages'];
            $grupId = (int) ($validated['grup_id'] ?? 0);
            if ($grupId > 0) {
                $this->targetScopeService->assertUserIdsAllowed($request->user(), $grupId, $userIds);
                $this->menuDelegationPolicyService->assertPagesAllowed($request->user(), $grupId, $pages);
            }
            $adminId = auth()->user()->Id_Users;

            // Pre-fetch capabilities for all target pages
            $capabilitiesMap = DB::table('N_HRIS_Menu_Capabilities')
                ->whereIn('Jenis_page', $pages)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy('Jenis_page');

            // Pre-fetch existing page access to skip
            $existingAccess = DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $userIds)
                ->whereIn('Jenis_page', $pages)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy(fn ($row) => $row->UserID_Web.'|'.$row->Jenis_page);

            // Pre-fetch user info for Divisi/Level
            $userInfo = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vl.ID_Level_Jabatan',
                )
                ->whereIn('k.UserID_Web', $userIds)
                ->select('k.UserID_Web', 'vd.ID_Divisi', 'vl.ID_Level')
                ->get()
                ->keyBy('UserID_Web');

            $pageAccessInserts = [];
            $createdCount = 0;
            $skippedCount = 0;

            // Build page access inserts
            foreach ($userIds as $userId) {
                $info = $userInfo->get($userId);
                foreach ($pages as $page) {
                    $key = $userId.'|'.$page;
                    if ($existingAccess->has($key)) {
                        $skippedCount++;

                        continue;
                    }

                    $pageAccessInserts[] = [
                        'Kode_Perusahaan' => '001',
                        'UserID_Web' => $userId,
                        'Jenis_page' => $page,
                        'ID_Divisi' => $info->ID_Divisi ?? 0,
                        'ID_Level' => $info->ID_Level ?? 0,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                    ];
                    $createdCount++;
                }
            }

            // Bulk insert Page Access in chunks, collecting IDs
            $menuAccessInserts = [];
            foreach (array_chunk($pageAccessInserts, 100) as $chunk) {
                foreach ($chunk as $record) {
                    $idPageAccess = DB::table('HRIS_Page_Access')->insertGetId($record);

                    // Auto-create Role_Menu_Access from capabilities
                    $caps = $capabilitiesMap->get($record['Jenis_page'], collect());
                    foreach ($caps as $cap) {
                        $menuAccessInserts[] = [
                            'Id_Page_Access' => $idPageAccess,
                            'Id_Aksi' => $cap->Id_Aksi,
                            'Flag_Diizinkan' => $cap->Is_Active_Default,
                            'Flag_Access_Konten' => null,
                            'Created_At' => now(),
                            'Updated_At' => now(),
                            'Flag_Aktif' => 'Y',
                        ];
                    }
                }
            }

            // Bulk insert Menu Access
            PenyisipanBerbatasParameter::sisipkan('N_HRIS_Role_Menu_Access', $menuAccessInserts);

            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'GRANT', mb_substr(implode(',', $pages), 0, 100), 'DISTRIBUSI_BERI',
                ['jumlah_user' => count($userIds)], ['halaman' => array_values($pages), 'user' => array_values($userIds)],
            );

            DB::commit();

            return response()->json([
                'message' => 'Distribusi akses berhasil.',
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'menu_access_created' => count($menuAccessInserts),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal distribusi: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function revokeDistribution(Request $request)
    {
        $validated = $request->validate([
            'grup_id' => 'nullable|integer|min:0',
            'mode' => 'required|in:individual,bulk',
            'user_ids' => 'required_if:mode,individual|array',
            'filter_divisi' => 'nullable|array',
            'filter_level' => 'nullable|array',
            'filter_jabatan' => 'nullable|array',
            'pages' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $userIds = $this->resolveTargetUsers($validated);
            $grupId = (int) ($validated['grup_id'] ?? 0);
            if ($grupId > 0) {
                $this->targetScopeService->assertUserIdsAllowed($request->user(), $grupId, $userIds);
                $this->menuDelegationPolicyService->assertPagesAllowed($request->user(), $grupId, $validated['pages']);
            }

            // Find page access IDs to revoke
            $pageAccessIds = DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $userIds)
                ->whereIn('Jenis_page', $validated['pages'])
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Page_Access');

            if ($pageAccessIds->isEmpty()) {
                DB::rollBack();

                return response()->json(['message' => 'Tidak ada akses yang perlu dicabut.', 'revoked' => 0]);
            }

            // Soft-delete Role_Menu_Access
            DB::table('N_HRIS_Role_Menu_Access')
                ->whereIn('Id_Page_Access', $pageAccessIds)
                ->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

            // Soft-delete Page Access
            DB::table('HRIS_Page_Access')
                ->whereIn('Id_Page_Access', $pageAccessIds)
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'GRANT', mb_substr(implode(',', $validated['pages']), 0, 100), 'DISTRIBUSI_CABUT',
                ['jumlah_user' => count($userIds)], ['halaman' => array_values($validated['pages']), 'user' => array_values($userIds)],
            );

            DB::commit();

            return response()->json([
                'message' => 'Akses berhasil dicabut.',
                'revoked' => $pageAccessIds->count(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal mencabut akses: '.$e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Seed existing HRIS_Page_Access dengan Menu_Access dari capabilities
     */
    public function seedExistingAccess()
    {
        DB::beginTransaction();
        try {
            // Get all active page accesses that don't have any menu access yet
            $pageAccesses = DB::table('HRIS_Page_Access as pa')
                ->leftJoin('N_HRIS_Role_Menu_Access as rma', function ($join) {
                    $join->on('rma.Id_Page_Access', '=', 'pa.Id_Page_Access')->where('rma.Flag_Aktif', '=', 'Y');
                })
                ->where('pa.Flag_Aktif', 'Y')
                ->whereNull('rma.Id_Role_Menu_Access')
                ->select('pa.Id_Page_Access', 'pa.Jenis_page')
                ->get();

            if ($pageAccesses->isEmpty()) {
                DB::rollBack();

                return response()->json(['message' => 'Semua akses sudah memiliki menu access.', 'created' => 0]);
            }

            // Pre-fetch all capabilities
            $allCaps = DB::table('N_HRIS_Menu_Capabilities')->where('Flag_Aktif', 'Y')->get()->groupBy('Jenis_page');

            $inserts = [];
            foreach ($pageAccesses as $pa) {
                $caps = $allCaps->get($pa->Jenis_page, collect());
                foreach ($caps as $cap) {
                    $inserts[] = [
                        'Id_Page_Access' => $pa->Id_Page_Access,
                        'Id_Aksi' => $cap->Id_Aksi,
                        'Flag_Diizinkan' => $cap->Is_Active_Default,
                        'Flag_Access_Konten' => null,
                        'Created_At' => now(),
                        'Updated_At' => now(),
                        'Flag_Aktif' => 'Y',
                    ];
                }
            }

            PenyisipanBerbatasParameter::sisipkan('N_HRIS_Role_Menu_Access', $inserts);

            DB::commit();

            return response()->json([
                'message' => 'Seed berhasil.',
                'page_access_processed' => $pageAccesses->count(),
                'menu_access_created' => count($inserts),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal seed: '.$e->getMessage()], 500);
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
    // PERMISSION MATRIX
    // ========================================================================

    public function getPermissionMatrix(Request $request)
    {
        try {
            $actor = $request->user();
            if (! $actor) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $scope = AuthorizationScopeResolver::resolve($actor);
            $manageableGroups = $scope['isSystemAdmin'] ? null : $scope['manageableGroupIds'] ?? [];

            if (! $scope['isSystemAdmin'] && empty($manageableGroups)) {
                return response()->json(['message' => 'Anda tidak memiliki group kelolaan untuk matrix.'], 403);
            }

            $search = $request->input('search', '');
            $pageFilter = $request->input('pageFilter', null);
            $userId = $request->input('userId', null);
            $perPage = $request->input('per_page', 20);

            // Get users with active page access
            $query = DB::table('Karyawan as k')
                ->join('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
                ->join('HRIS_Page_Access as pa', function ($join) {
                    $join->on('pa.UserID_Web', '=', 'k.UserID_Web')->where('pa.Flag_Aktif', '=', 'Y');
                })
                ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'pa.Jenis_page')
                ->where('k.Aktif', 'Y');

            if (! $scope['isSystemAdmin']) {
                $query->whereIn('gfm.Id_Grup_Fitur', $manageableGroups);
            }

            if ($pageFilter) {
                $query->where('pa.Jenis_page', $pageFilter);
            }

            if ($userId) {
                $query->where('k.UserID_Web', $userId);
            }

            if ($search && ! $userId) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")->orWhere('k.Kode_Karyawan', 'LIKE', "%{$search}%");
                });
            }

            $users = $query
                ->select(
                    'k.UserID_Web',
                    'k.Nama',
                    'k.Kode_Karyawan',
                    'pa.Id_Page_Access',
                    'pa.Jenis_page',
                    'gfm.Id_Grup_Fitur',
                )
                ->orderBy('k.Nama')
                ->paginate($perPage);

            $paIds = collect($users->items())->pluck('Id_Page_Access');

            // Get existing permissions for these page accesses
            $permissions = DB::table('N_HRIS_Role_Menu_Access')
                ->whereIn('Id_Page_Access', $paIds)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy('Id_Page_Access');

            // Get available capabilities for the pages in view
            $pagesInView = collect($users->items())->pluck('Jenis_page')->unique();
            $capabilities = DB::table('N_HRIS_Menu_Capabilities')
                ->whereIn('Jenis_page', $pagesInView)
                ->where('Flag_Aktif', 'Y')
                ->get()
                ->groupBy('Jenis_page');

            // All actions
            $actions = DB::table('N_HRIS_Klasifikasi_Aksi')->orderBy('Id_Klasifikasi_Actions')->get();

            // Build matrix
            $matrix = collect($users->items())->map(function ($row) use ($permissions, $capabilities, $actions) {
                $perms = $permissions->get($row->Id_Page_Access, collect());
                $caps = $capabilities->get($row->Jenis_page, collect());
                $capActionIds = $caps->pluck('Id_Aksi')->toArray();

                $actionMatrix = $actions->map(function ($action) use ($perms, $capActionIds) {
                    $hasCapability = in_array($action->Id_Klasifikasi_Actions, $capActionIds);
                    $perm = $perms->firstWhere('Id_Aksi', $action->Id_Klasifikasi_Actions);

                    return [
                        'id_aksi' => $action->Id_Klasifikasi_Actions,
                        'nama_aksi' => $action->Nama_Aksi,
                        'has_capability' => $hasCapability,
                        'flag_diizinkan' => $perm ? $perm->Flag_Diizinkan : 'T',
                        'id_role_menu_access' => $perm->Id_Role_Menu_Access ?? null,
                    ];
                });

                return [
                    'user_id' => $row->UserID_Web,
                    'nama' => $row->Nama,
                    'kode' => $row->Kode_Karyawan,
                    'jenis_page' => $row->Jenis_page,
                    'id_page_access' => $row->Id_Page_Access,
                    'actions' => $actionMatrix,
                ];
            });

            // Get menu list for filter dropdown
            $menuOptions = DB::table('N_HRIS_Menus')
                ->leftJoin('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'N_HRIS_Menus.Jenis_page')
                ->select(
                    'N_HRIS_Menus.Jenis_page',
                    'N_HRIS_Menus.Nama_Menu',
                    'N_HRIS_Menus.Header_Label',
                    'gfm.Id_Grup_Fitur',
                )
                ->when(! $scope['isSystemAdmin'], function ($q) use ($manageableGroups) {
                    $q->whereIn('gfm.Id_Grup_Fitur', $manageableGroups);
                })
                ->orderBy('Header_Label')
                ->orderBy('Nama_Menu')
                ->get();

            $selectedUser = null;
            if ($userId) {
                $selectedUser = DB::table('Karyawan as k')
                    ->leftJoin(
                        'View_Divisi_Sub_Divisi as vdsd',
                        'k.ID_Divisi_Sub_Divisi',
                        '=',
                        'vdsd.ID_DIVISI_SUB_DIVISI',
                    )
                    ->where('k.UserID_Web', $userId)
                    ->select(
                        'k.UserID_Web as id',
                        'k.Nama as nama',
                        'k.Kode_Karyawan as kode',
                        'vdsd.nama_divisi as divisi',
                    )
                    ->first();
            }

            return response()->json([
                'matrix' => $matrix,
                'actions' => $actions,
                'menuOptions' => $menuOptions,
                'selectedUser' => $selectedUser,
                'accessPath' => $userId
                    ? $this->buildUserAuthorizationPath((int) $userId, $manageableGroups)
                    : null,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat matrix: '.$e->getMessage()], 500);
        }
    }

    private function buildUserAuthorizationPath(int $userId, ?array $manageableGroups): array
    {
        $roleQuery = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
            ->where('ru.UserId_Web', $userId)
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->select(
                'rf.Id_Role',
                'rf.Role_Code',
                'rf.Role_Name',
                'rf.Id_Grup_Fitur',
                'gf.Kode_Grup_Fitur as module_code',
                'gf.Nama_Grup_Fitur as module_name',
            )
            ->distinct()
            ->orderBy('gf.Kode_Grup_Fitur')
            ->orderBy('rf.Role_Name');

        if ($manageableGroups !== null) {
            $roleQuery->whereIn('rf.Id_Grup_Fitur', empty($manageableGroups) ? [-1] : $manageableGroups);
        }

        $roles = $roleQuery->get();
        $roleIds = $roles->pluck('Id_Role')->map(fn ($id) => (int) $id)->all();

        $resources = empty($roleIds)
            ? collect()
            : DB::table('N_HRIS_Role_Resource_Access')
                ->whereIn('Id_Role_Functional', $roleIds)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Diizinkan', 'Y')
                ->orderBy('Sort_Order')
                ->orderBy('Resource_Type')
                ->orderBy('Resource_Code')
                ->get([
                    'Id_Role_Functional',
                    'Context_Code',
                    'Jenis_page',
                    'Resource_Type',
                    'Resource_Code',
                    'Resource_Name',
                ])
                ->groupBy('Id_Role_Functional');

        $scopes = empty($roleIds)
            ? collect()
            : DB::table('N_HRIS_Role_Scope as rs')
                ->join('N_HRIS_Scope_Definition as sd', 'sd.Id_Scope', '=', 'rs.Id_Scope')
                ->whereIn('rs.Id_Role', $roleIds)
                ->where('rs.Flag_Aktif', 'Y')
                ->where('sd.Flag_Aktif', 'Y')
                ->get(['rs.Id_Role', 'sd.Scope_Code', 'sd.Scope_Name', 'sd.Scope_Value'])
                ->groupBy('Id_Role');

        $pageQuery = DB::table('HRIS_Page_Access as pa')
            ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'pa.Jenis_page')
            ->leftJoin('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'pa.Jenis_page')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
            ->where('pa.UserID_Web', $userId)
            ->where('pa.Flag_Aktif', 'Y')
            ->select(
                'pa.Jenis_page',
                'm.Nama_Menu',
                'm.Header_Label',
                'gf.Id_Grup_Fitur',
                'gf.Kode_Grup_Fitur as module_code',
            )
            ->distinct()
            ->orderBy('gf.Kode_Grup_Fitur')
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu');

        if ($manageableGroups !== null) {
            $pageQuery->whereIn('gfm.Id_Grup_Fitur', empty($manageableGroups) ? [-1] : $manageableGroups);
        }

        /* Sama seperti di getResourceAccess(): keanggotaan registry diambil
           sekali untuk SEMUA resource dari SEMUA role, bukan satu kueri per
           baris di dalam dua perulangan bersarang. */
        $terdaftar = $this->resourceRegistryService->terdaftarMassal(
            $resources->flatten(1)
                ->map(fn ($r) => [
                    $r->Jenis_page ?? null,
                    (string) $r->Resource_Type,
                    (string) $r->Resource_Code,
                ])
                ->all()
        );

        $roleItems = $roles->map(function ($role) use ($resources, $scopes, $terdaftar) {
            $roleResources = $resources->get($role->Id_Role, collect())
                ->map(function ($resource) use ($terdaftar) {
                    $resource->master_registered = $terdaftar[AuthorizationResourceRegistryService::kunciTerdaftar(
                        $resource->Jenis_page ?? null,
                        (string) $resource->Resource_Type,
                        (string) $resource->Resource_Code,
                    )] ?? false;

                    return $resource;
                })
                ->values();
            $roleScopes = $scopes->get($role->Id_Role, collect())->values();
            $invalidResourceCount = $roleResources->where('master_registered', false)->count();

            return [
                'id' => (int) $role->Id_Role,
                'code' => $role->Role_Code,
                'name' => $role->Role_Name,
                'group_id' => (int) $role->Id_Grup_Fitur,
                'module_code' => $role->module_code,
                'module_name' => $role->module_name,
                'resources' => $roleResources,
                'scopes' => $roleScopes,
                'resource_count' => $roleResources->count(),
                'invalid_resource_count' => $invalidResourceCount,
                'scope_count' => $roleScopes->count(),
                'readiness' => [
                    'has_resources' => $roleResources->isNotEmpty(),
                    'has_scope' => $roleScopes->isNotEmpty(),
                    'ready' => $roleResources->isNotEmpty() && $roleScopes->isNotEmpty() && $invalidResourceCount === 0,
                ],
            ];
        })->values();

        $pages = $pageQuery->get()->values();

        return [
            'roles' => $roleItems,
            'pages' => $pages,
            'summary' => [
                'role_count' => $roleItems->count(),
                'page_count' => $pages->count(),
                'resource_count' => $roleItems->sum('resource_count'),
                'scope_count' => $roleItems->sum('scope_count'),
                'incomplete_role_count' => $roleItems->where('readiness.ready', false)->count(),
            ],
        ];
    }

    public function togglePermission(Request $request)
    {
        $validated = $request->validate([
            'id_page_access' => 'required|integer|min:1',
            'id_aksi' => 'required|integer|min:1',
            'flag_diizinkan' => 'required|string|in:Y,T',
        ]);

        $pageExists = DB::table('HRIS_Page_Access')
            ->where('Id_Page_Access', $validated['id_page_access'])
            ->where('Flag_Aktif', 'Y')
            ->exists();
        abort_unless($pageExists, 404, 'Page access tidak ditemukan atau tidak aktif.');

        $actionExists = DB::table('N_HRIS_Klasifikasi_Aksi')
            ->where('Id_Klasifikasi_Actions', $validated['id_aksi'])
            ->exists();
        abort_unless($actionExists, 422, 'Action tidak terdaftar.');

        try {
            $actor = $request->user();
            if (! $actor) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $scope = AuthorizationScopeResolver::resolve($actor);
            if (! $scope['isSystemAdmin']) {
                $allowedGroups = $scope['manageableGroupIds'] ?? [];
                if (empty($allowedGroups)) {
                    return response()->json(['message' => 'Anda tidak memiliki group kelolaan untuk matrix.'], 403);
                }

                $allowedPage = DB::table('HRIS_Page_Access as pa')
                    ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'pa.Jenis_page')
                    ->where('pa.Id_Page_Access', $validated['id_page_access'])
                    ->whereIn('gfm.Id_Grup_Fitur', $allowedGroups)
                    ->exists();

                if (! $allowedPage) {
                    return response()->json(['message' => 'Anda tidak berwenang mengubah permission menu ini.'], 403);
                }
            }

            $existing = DB::table('N_HRIS_Role_Menu_Access')
                ->where('Id_Page_Access', $validated['id_page_access'])
                ->where('Id_Aksi', $validated['id_aksi'])
                ->where('Flag_Aktif', 'Y')
                ->first();

            if ($existing) {
                DB::table('N_HRIS_Role_Menu_Access')
                    ->where('Id_Role_Menu_Access', $existing->Id_Role_Menu_Access)
                    ->update([
                        'Flag_Diizinkan' => $validated['flag_diizinkan'],
                        'Updated_At' => now(),
                    ]);
            } else {
                DB::table('N_HRIS_Role_Menu_Access')->insert([
                    'Id_Page_Access' => $validated['id_page_access'],
                    'Id_Aksi' => $validated['id_aksi'],
                    'Flag_Diizinkan' => $validated['flag_diizinkan'],
                    'Flag_Aktif' => 'Y',
                    'Created_At' => now(),
                    'Updated_At' => now(),
                ]);
            }

            AuthorizationAuditLogger::logMutation([
                'event' => 'menu_permission.override_updated',
                'actor_user_id' => $actor->Id_Users ?? $actor->id ?? null,
                'page_access_id' => $validated['id_page_access'],
                'action_id' => $validated['id_aksi'],
                'old_value' => $existing->Flag_Diizinkan ?? null,
                'new_value' => $validated['flag_diizinkan'],
            ]);

            return response()->json(['message' => 'Permission berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal toggle: '.$e->getMessage()], 500);
        }
    }

    public function resetPermissionToDefault(Request $request)
    {
        $validated = $request->validate([
            'id_page_access' => 'required|integer|min:1',
        ]);

        $actor = $request->user();
        if (! $actor) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $page = DB::table('HRIS_Page_Access as pa')
            ->where('pa.Id_Page_Access', $validated['id_page_access'])
            ->where('pa.Flag_Aktif', 'Y')
            ->first(['pa.Id_Page_Access', 'pa.UserID_Web', 'pa.Jenis_page']);
        abort_unless($page, 404, 'Page access tidak ditemukan atau tidak aktif.');

        $scope = AuthorizationScopeResolver::resolve($actor);
        if (! $scope['isSystemAdmin']) {
            $allowedGroups = $scope['manageableGroupIds'] ?? [];
            $allowed = ! empty($allowedGroups) && DB::table('N_HRIS_Grup_Fitur_Map as gfm')
                ->where('gfm.Jenis_page', $page->Jenis_page)
                ->whereIn('gfm.Id_Grup_Fitur', $allowedGroups)
                ->where('gfm.Flag_Aktif', 'Y')
                ->exists();

            abort_unless($allowed, 403, 'Anda tidak berwenang mereset permission menu ini.');
        }

        $defaults = DB::table('N_HRIS_Menu_Capabilities')
            ->where('Jenis_page', $page->Jenis_page)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_Aksi', 'Is_Active_Default']);
        $defaultActionIds = $defaults->pluck('Id_Aksi')->map(fn ($id) => (int) $id)->values();

        DB::transaction(function () use ($page, $defaults, $defaultActionIds): void {
            $existing = DB::table('N_HRIS_Role_Menu_Access')
                ->where('Id_Page_Access', $page->Id_Page_Access)
                ->get(['Id_Role_Menu_Access', 'Id_Aksi']);
            $existingByAction = $existing->keyBy('Id_Aksi');

            foreach ($defaults as $default) {
                $row = $existingByAction->get($default->Id_Aksi);
                if ($row) {
                    DB::table('N_HRIS_Role_Menu_Access')
                        ->where('Id_Role_Menu_Access', $row->Id_Role_Menu_Access)
                        ->update([
                            'Flag_Diizinkan' => $default->Is_Active_Default,
                            'Flag_Aktif' => 'Y',
                            'Updated_At' => now(),
                        ]);
                } else {
                    DB::table('N_HRIS_Role_Menu_Access')->insert([
                        'Id_Page_Access' => $page->Id_Page_Access,
                        'Id_Aksi' => $default->Id_Aksi,
                        'Flag_Diizinkan' => $default->Is_Active_Default,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                    ]);
                }
            }

            $query = DB::table('N_HRIS_Role_Menu_Access')
                ->where('Id_Page_Access', $page->Id_Page_Access)
                ->where('Flag_Aktif', 'Y');
            if ($defaultActionIds->isNotEmpty()) {
                $query->whereNotIn('Id_Aksi', $defaultActionIds->all());
            }
            $query->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
        });

        DB::afterCommit(function () use ($actor, $page, $defaultActionIds): void {
            AuthorizationAuditLogger::logMutation([
                'event' => 'menu_permission.reset_to_default',
                'actor_user_id' => $actor->Id_Users ?? $actor->id ?? null,
                'target_user_id' => $page->UserID_Web,
                'page_access_id' => $page->Id_Page_Access,
                'jenis_page' => $page->Jenis_page,
                'affected_count' => $defaultActionIds->count(),
                'new_value' => 'menu_defaults_applied',
                'details' => ['action_ids' => $defaultActionIds->all()],
            ]);
        });

        return response()->json(['message' => 'Permission berhasil direset ke default menu.']);
    }

    public function bulkSavePermissions(Request $request)
    {
        $validated = $request->validate([
            'changes' => 'required|array|min:1',
            'changes.*.id_page_access' => 'required|integer|min:1',
            'changes.*.id_aksi' => 'required|integer|min:1',
            'changes.*.flag_diizinkan' => 'required|string|in:Y,T',
        ]);

        $changeKeys = collect($validated['changes'])
            ->map(fn (array $change) => $change['id_page_access'].'|'.$change['id_aksi']);
        abort_unless(
            $changeKeys->count() === $changeKeys->unique()->count(),
            422,
            'Page access dan action tidak boleh dikirim duplikat.',
        );

        $pageAccessIds = collect($validated['changes'])
            ->pluck('id_page_access')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $knownPageAccessIds = DB::table('HRIS_Page_Access')
            ->whereIn('Id_Page_Access', $pageAccessIds->all())
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Page_Access')
            ->map(fn ($id) => (int) $id);
        $unknownPageAccessIds = $pageAccessIds->diff($knownPageAccessIds)->values();
        abort_unless(
            $unknownPageAccessIds->isEmpty(),
            404,
            'Page access tidak ditemukan atau tidak aktif: '.$unknownPageAccessIds->implode(', '),
        );

        $actionIds = collect($validated['changes'])
            ->pluck('id_aksi')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $knownActionIds = DB::table('N_HRIS_Klasifikasi_Aksi')
            ->whereIn('Id_Klasifikasi_Actions', $actionIds->all())
            ->pluck('Id_Klasifikasi_Actions')
            ->map(fn ($id) => (int) $id);
        $unknownActionIds = $actionIds->diff($knownActionIds)->values();
        abort_unless(
            $unknownActionIds->isEmpty(),
            422,
            'Action tidak terdaftar: '.$unknownActionIds->implode(', '),
        );

        DB::beginTransaction();
        try {
            $actor = $request->user();
            if (! $actor) {
                DB::rollBack();

                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $scope = AuthorizationScopeResolver::resolve($actor);
            if (! $scope['isSystemAdmin']) {
                $allowedGroups = $scope['manageableGroupIds'] ?? [];
                if (empty($allowedGroups)) {
                    DB::rollBack();

                    return response()->json(['message' => 'Anda tidak memiliki group kelolaan untuk matrix.'], 403);
                }

                $pageAccessIds = collect($validated['changes'])
                    ->pluck('id_page_access')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $allowedPageIds = DB::table('HRIS_Page_Access as pa')
                    ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'pa.Jenis_page')
                    ->whereIn('pa.Id_Page_Access', $pageAccessIds)
                    ->whereIn('gfm.Id_Grup_Fitur', $allowedGroups)
                    ->distinct()
                    ->pluck('pa.Id_Page_Access')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $allowedLookup = array_flip($allowedPageIds);
                foreach ($pageAccessIds as $pid) {
                    if (! isset($allowedLookup[(int) $pid])) {
                        DB::rollBack();

                        return response()->json(
                            ['message' => 'Terdapat perubahan di luar scope group kelolaan Anda.'],
                            403,
                        );
                    }
                }
            }

            foreach ($validated['changes'] as $change) {
                $existing = DB::table('N_HRIS_Role_Menu_Access')
                    ->where('Id_Page_Access', $change['id_page_access'])
                    ->where('Id_Aksi', $change['id_aksi'])
                    ->where('Flag_Aktif', 'Y')
                    ->first();

                if ($existing) {
                    DB::table('N_HRIS_Role_Menu_Access')
                        ->where('Id_Role_Menu_Access', $existing->Id_Role_Menu_Access)
                        ->update([
                            'Flag_Diizinkan' => $change['flag_diizinkan'],
                            'Updated_At' => now(),
                        ]);
                } else {
                    DB::table('N_HRIS_Role_Menu_Access')->insert([
                        'Id_Page_Access' => $change['id_page_access'],
                        'Id_Aksi' => $change['id_aksi'],
                        'Flag_Diizinkan' => $change['flag_diizinkan'],
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                    ]);
                }
            }

            DB::commit();

            AuthorizationAuditLogger::logMutation([
                'event' => 'menu_permission.bulk_override_updated',
                'actor_user_id' => $actor->Id_Users ?? $actor->id ?? null,
                'affected_count' => count($validated['changes']),
                'new_value' => 'bulk_configuration_saved',
            ]);

            return response()->json([
                'message' => 'Permission matrix berhasil disimpan.',
                'updated' => count($validated['changes']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menyimpan matrix: '.$e->getMessage()], 500);
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
    // MONITORING — Real overview of system access state
    // ========================================================================

    public function getMonitoringOverview()
    {
        try {
            $user = request()->user();
            if (! $user || ! AuthorizationScopeResolver::resolve($user)['isSystemAdmin']) {
                return response()->json(['message' => 'Monitoring hanya untuk System Admin.'], 403);
            }

            // Per-menu access count
            $menuAccessStats = DB::table('HRIS_Page_Access as pa')
                ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'pa.Jenis_page')
                ->where('pa.Flag_Aktif', 'Y')
                ->select(
                    'm.Jenis_page',
                    'm.Nama_Menu',
                    'm.Header_Label',
                    DB::raw('COUNT(DISTINCT pa.UserID_Web) as user_count'),
                    DB::raw(
                        '(SELECT COUNT(*) FROM N_HRIS_Menu_Capabilities mc WHERE mc.Jenis_page = m.Jenis_page AND mc.Flag_Aktif = \'Y\') as cap_count',
                    ),
                )
                ->groupBy('m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label')
                ->orderByDesc('user_count')
                ->get();

            // Per-group access summary
            $groupStats = DB::table('N_HRIS_Master_Grup_Fitur as gf')
                ->select(
                    'gf.Id_Grup_Fitur',
                    'gf.Kode_Grup_Fitur',
                    'gf.Nama_Grup_Fitur',
                    DB::raw(
                        '(SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map gfm WHERE gfm.Id_Grup_Fitur = gf.Id_Grup_Fitur) as menu_count',
                    ),
                    DB::raw(
                        '(SELECT COUNT(DISTINCT pa.UserID_Web) FROM HRIS_Page_Access pa INNER JOIN N_HRIS_Grup_Fitur_Map gfm ON gfm.Jenis_page = pa.Jenis_page WHERE gfm.Id_Grup_Fitur = gf.Id_Grup_Fitur AND pa.Flag_Aktif = \'Y\') as user_count',
                    ),
                )
                ->orderBy('gf.Kode_Grup_Fitur')
                ->get();

            // Scope coverage
            $scopeStats = DB::table('N_HRIS_Role_Scope as rs')
                ->join('N_HRIS_Scope_Definition as sd', 'sd.Id_Scope', '=', 'rs.Id_Scope')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'rs.Id_Role')
                ->where('rs.Flag_Aktif', 'Y')
                ->where('rf.Flag_Aktif', 'Y')
                ->select('sd.Scope_Name', 'sd.Scope_Value', DB::raw('COUNT(DISTINCT rf.Id_Role) as role_count'))
                ->groupBy('sd.Id_Scope', 'sd.Scope_Name', 'sd.Scope_Value')
                ->get();

            // Totals
            $totals = [
                'total_users_with_access' => DB::table('HRIS_Page_Access')
                    ->where('Flag_Aktif', 'Y')
                    ->distinct('UserID_Web')
                    ->count('UserID_Web'),
                'total_page_access' => DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')->count(),
                'total_menus' => DB::table('N_HRIS_Menus')->count(),
                'total_capabilities' => DB::table('N_HRIS_Menu_Capabilities')->where('Flag_Aktif', 'Y')->count(),
                'total_menu_access' => DB::table('N_HRIS_Role_Menu_Access')->where('Flag_Aktif', 'Y')->count(),
            ];

            return response()->json([
                'totals' => $totals,
                'menuStats' => $menuAccessStats,
                'groupStats' => $groupStats,
                'scopeStats' => $scopeStats,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat monitoring: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

    private function resolveTargetUsers(array $validated): array
    {
        if ($validated['mode'] === 'individual') {
            return array_map('intval', $validated['user_ids'] ?? []);
        }

        // Bulk mode: query Karyawan with filters
        $query = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
            ->where('k.Aktif', 'Y');

        if (! empty($validated['filter_divisi'])) {
            $query->whereExists(function ($q) use ($validated) {
                $q->select(DB::raw(1))
                    ->from('View_Divisi_Sub_Divisi as vd')
                    ->whereRaw('vd.ID_DIVISI_SUB_DIVISI = k.ID_Divisi_Sub_Divisi')
                    ->whereIn('vd.ID_Divisi', $validated['filter_divisi']);
            });
        }

        if (! empty($validated['filter_level'])) {
            $query->whereExists(function ($q) use ($validated) {
                $q->select(DB::raw(1))
                    ->from('View_Golongan_Sub_Golongan_Level_Jabatan as vl')
                    ->whereRaw('vl.ID_Level_Jabatan = k.ID_Level_Jabatan')
                    ->whereIn('vl.ID_Level', $validated['filter_level']);
            });
        }

        if (! empty($validated['filter_jabatan'])) {
            $query->whereExists(function ($q) use ($validated) {
                $q->select(DB::raw(1))
                    ->from('View_Golongan_Sub_Golongan_Level_Jabatan as vl')
                    ->whereRaw('vl.ID_Level_Jabatan = k.ID_Level_Jabatan')
                    ->whereIn('vl.ID_Jabatan', $validated['filter_jabatan']);
            });
        }

        return $query->pluck('k.UserID_Web')->toArray();
    }

    /**
     * Resolve access scope for resource access management.
     * System Admin → all roles and all groups.
     * Super Admin → roles only within managed groups.
     */
    private function resolveResourceAccessScope($user): array
    {
        if (! $user) {
            return [
                'isSystemAdmin' => false,
                'allowedGroupIds' => [],
                'allowedRoleIds' => [],
            ];
        }

        $scope = AuthorizationScopeResolver::resolve($user);
        if ($scope['isSystemAdmin']) {
            return [
                'isSystemAdmin' => true,
                'allowedGroupIds' => null,
                'allowedRoleIds' => null,
            ];
        }

        $allowedGroupIds = $scope['manageableGroupIds'] ?? [];
        if (empty($allowedGroupIds)) {
            return [
                'isSystemAdmin' => false,
                'allowedGroupIds' => [],
                'allowedRoleIds' => [],
            ];
        }

        $allowedRoleIds = DB::table('N_HRIS_Role_Functional')
            ->whereIn('Id_Grup_Fitur', $allowedGroupIds)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Role')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        return [
            'isSystemAdmin' => false,
            'allowedGroupIds' => $allowedGroupIds,
            'allowedRoleIds' => $allowedRoleIds,
        ];
    }

    /**
     * SQL Server has max 2100 bound parameters per statement.
     * This helper chunks bulk inserts automatically based on inserted column count.
     */
    private function getMenuActiveFlagColumn(): ?string
    {
        foreach (self::MENU_ACTIVE_FLAG_COLUMNS as $column) {
            if (SkemaTersedia::kolom('N_HRIS_Menus', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function normalizePageAccessMenuPayload(array $validated): array
    {
        $headerLabel = trim((string) ($validated['Header_Label'] ?? ''));
        $headerCode = ! empty($validated['Header_Code'])
            ? $this->normalizeHeaderCode((string) $validated['Header_Code'])
            : $this->normalizeHeaderCode($headerLabel);

        if ($headerCode === '') {
            $headerCode = 'general';
        }

        return [
            'Jenis_page' => $this->normalizeJenisPage((string) $validated['Jenis_page']),
            'Kode_Perusahaan' => trim((string) ($validated['Kode_Perusahaan'] ?? '001')) ?: '001',
            'Nama_Menu' => trim((string) ($validated['Nama_Menu'] ?? '')),
            'Icon_Menu' => $this->normalizeIconClass($validated['Icon_Menu'] ?? null, 'bi bi-grid-1x2'),
            'Url_Menu' => trim((string) ($validated['Url_Menu'] ?? '')),
            'Header_Code' => $headerCode,
            'Header_Label' => $headerLabel !== '' ? $headerLabel : null,
            'Header_Icon' => $this->normalizeIconClass($validated['Header_Icon'] ?? null, 'bi bi-folder2-open'),
            'Sort_Header' => ! empty($validated['Sort_Header']) ? (int) $validated['Sort_Header'] : null,
            'Is_Global_Menu' => strtoupper((string) ($validated['Is_Global_Menu'] ?? 'T')) === 'Y' ? 'Y' : 'T',
            'Sort_Menu' => ! empty($validated['Sort_Menu']) ? (int) $validated['Sort_Menu'] : null,
        ];
    }

    private function validatePageAccessMenuPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'Jenis_page' => 'required|string|max:100',
            'Kode_Perusahaan' => 'nullable|string|max:10',
            'Nama_Menu' => 'required|string|max:255',
            'Icon_Menu' => 'nullable|string|max:255',
            'Url_Menu' => 'nullable|string|max:255',
            'Header_Code' => 'nullable|string|max:100',
            'Header_Label' => 'required|string|max:150',
            'Header_Icon' => 'nullable|string|max:100',
            'Sort_Header' => 'nullable|integer|min:1',
            'Is_Global_Menu' => 'nullable|string|in:Y,T',
            'Sort_Menu' => 'nullable|integer|min:1',
        ];

        return $request->validate($rules);
    }

    private function normalizeJenisPage(string $jenisPage): string
    {
        $value = trim(strtolower($jenisPage));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value ?? '') ?? '';
        $value = trim($value, '-');

        return $value;
    }

    private function normalizeHeaderCode(string $headerCode): string
    {
        $value = trim(strtolower($headerCode));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value ?? '') ?? '';
        $value = trim($value, '-');

        return $value;
    }

    private function normalizeIconClass(?string $icon, string $fallback): string
    {
        $value = trim((string) $icon);
        if ($value === '') {
            return $fallback;
        }

        if (strpos($value, 'bi bi-') === 0) {
            return $value;
        }

        if (strpos($value, 'bi-') === 0) {
            return 'bi '.$value;
        }

        if (strpos($value, 'bi ') === 0) {
            return $value;
        }

        return 'bi bi-'.ltrim($value, '-');
    }

    private function nextSortMenu(string $headerCode, ?int $excludeId = null): int
    {
        $query = DB::table('N_HRIS_Menus')
            ->where('Header_Code', $headerCode)
            ->where('Is_Show_Sidebar', 'Y');
        $activeFlag = $this->getMenuActiveFlagColumn();
        if ($activeFlag !== null) {
            $query->where($activeFlag, 'Y');
        }

        if ($excludeId !== null) {
            $query->where('Id_Menu', '!=', $excludeId);
        }

        $maxSort = (int) ($query->max('Sort_Menu') ?? 0);

        return $maxSort + 1;
    }

    private function validateSortConflicts(
        string $headerCode,
        ?int $sortMenu,
        ?int $excludeId,
        array $excludeIds = []
    ): array {
        $conflicts = [];
        $activeFlag = $this->getMenuActiveFlagColumn();
        $excludedMenuIds = collect($excludeIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (! empty($sortMenu)) {
            $menuQuery = DB::table('N_HRIS_Menus')
                ->where('Header_Code', $headerCode)
                ->where('Sort_Menu', (int) $sortMenu)
                ->where('Is_Show_Sidebar', 'Y');

            if ($activeFlag !== null) {
                $menuQuery->where($activeFlag, 'Y');
            }

            if (! empty($excludeId)) {
                $menuQuery->where('Id_Menu', '!=', (int) $excludeId);
            }

            if (! empty($excludedMenuIds)) {
                $menuQuery->whereNotIn('Id_Menu', $excludedMenuIds);
            }

            $menuExists = $menuQuery->first(['Id_Menu', 'Nama_Menu']);
            if ($menuExists) {
                $conflicts[] = [
                    'type' => 'menu_sort_conflict',
                    'header_code' => $headerCode,
                    'sort_menu' => (int) $sortMenu,
                    'conflict_menu_id' => $menuExists->Id_Menu,
                    'conflict_menu_name' => $menuExists->Nama_Menu,
                ];
            }
        }

        return $conflicts;
    }

    private function buildHeaderOptions($rows): array
    {
        return collect($rows)
            ->groupBy(fn ($row) => (string) ($row->Header_Code ?? ''))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'Header_Code' => $first->Header_Code,
                    'Header_Label' => $first->Header_Label,
                    'Header_Icon' => $first->Header_Icon,
                    'Sort_Header' => $group
                        ->pluck('Sort_Header')
                        ->filter(fn ($v) => $v !== null)
                        ->map(fn ($v) => (int) $v)
                        ->min(),
                    'menu_count' => $group->count(),
                ];
            })
            ->values()
            ->sortBy(
                fn ($row) => sprintf(
                    '%05d_%s',
                    (int) ($row['Sort_Header'] ?? 999),
                    strtolower((string) ($row['Header_Label'] ?? '')),
                ),
            )
            ->values()
            ->all();
    }
}
