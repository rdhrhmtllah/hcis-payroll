<?php

namespace App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix;

use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalMatrixService;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Membership\MembershipManagementService;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuBindingService;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixExportService;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use App\Services\SYSTEM\MasterData\GrupFitur\KodeGrupFitur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman admin "Menu & Approval Matrix" per grup fitur.
 *
 * Permission:
 * - Route group: check.jabatan:menuApprovalMatrixPage
 * - Granular per role via N_HRIS_Role_Resource_Access
 *   (tab/menu, tab/approval-matrix, capability/MENU_ACCESS.ASSIGN,
 *    capability/APPROVAL_MATRIX.ASSIGN) — dievaluasi di ApprovalMatrixService.
 * - Aksi struktur (config/detail/jenis approval/binding grup) khusus
 *   System Admin, di-assert di service.
 */
class MenuApprovalMatrixController extends Controller
{
    public function __construct(
        private readonly ApprovalMatrixService $matrixService,
        private readonly MenuBindingService $menuBindingService,
        private readonly MembershipManagementService $membershipService,
        private readonly EmployeeTargetScopeService $targetScopeService,
    ) {}

    public function indexEss(Request $request): Response
    {
        return $this->renderForGrup($request, 'ESS');
    }

    public function indexKpi(Request $request): Response
    {
        return $this->renderForGrup($request, 'KPI');
    }

    public function indexLms(Request $request): Response
    {
        return $this->renderForGrup($request, 'LMS');
    }

    public function indexVisitor(Request $request): Response
    {
        return $this->renderForGrup($request, 'VISITOR');
    }

    private function renderForGrup(Request $request, string $grupCode): Response
    {
        $user = $request->user();
        $activeGrup = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Flag_Aktif', 'Y')
            // Production menamai KPI/VISITOR sebagai PMS/VMS; lihat KodeGrupFitur.
            ->whereIn(DB::raw('UPPER(Kode_Grup_Fitur)'), KodeGrupFitur::varian($grupCode))
            ->select('Id_Grup_Fitur', 'Kode_Grup_Fitur', 'Nama_Grup_Fitur', 'Icon_Grup_Fitur')
            ->first();

        abort_if(! $activeGrup, 404, 'Grup fitur tidak ditemukan atau tidak aktif.');

        $grupId = (int) $activeGrup->Id_Grup_Fitur;
        $this->matrixService->assertCanManageGrup($user, $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);

        if (! $capabilities['menu']['canView'] && ! $capabilities['approvalMatrix']['canView'] && ! $capabilities['membership']['canView']) {
            abort(403, 'Anda tidak memiliki akses ke halaman Menu & Approval Matrix.');
        }

        return Inertia::render('SYSTEM/Governance/Authorization/MenuApprovalMatrix/menusnApprovals', [
            'matrixCapabilities' => $capabilities,
            'activeGrup' => $activeGrup,
            // RBAC Fase 5: banner "Kini dikelola di Akses & Peran".
            'modulBaru' => app(ModeAkses::class)->namaModulBaru(),
            // Menu & Matrix per modul: tombol ke Akses & Peran modul yang sama.
            'tautanAksesPeran' => app(KeputusanAkses::class)->bolehKelolaModul($user, $grupId) ? '/admin/'.strtolower($activeGrup->Kode_Grup_Fitur).'/akses-peran' : null,
            'modeGrup' => app(ModeAkses::class)->modeModul($grupId),
        ]);
    }

    public function bootstrap(Request $request, int $grupId): JsonResponse
    {
        $user = $request->user();
        $this->matrixService->assertCanManageGrup($user, $grupId);

        $activeGrup = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Id_Grup_Fitur', $grupId)
            ->where('Flag_Aktif', 'Y')
            ->select('Id_Grup_Fitur', 'Kode_Grup_Fitur', 'Nama_Grup_Fitur', 'Icon_Grup_Fitur')
            ->first();

        abort_if(! $activeGrup, 404, 'Grup fitur tidak ditemukan atau tidak aktif.');

        return response()->json([
            'capabilities' => $this->matrixService->buildMatrixCapabilities($user, $grupId),
            'activeGrup' => $activeGrup,
        ]);
    }

    public function getIssueCounts(Request $request, int $grupId): JsonResponse
    {
        $user = $request->user();
        $this->matrixService->assertCanManageGrup($user, $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        $issues = [];
        $resolve = function (string $domain, callable $callback) use (&$issues): void {
            try {
                $issues[$domain] = $callback();
            } catch (\Throwable $e) {
                report($e);
            }
        };

        if ($capabilities['menu']['canView']) {
            $resolve('menu', fn () => $this->menuBindingService->getIssueCount($user, $grupId));
        }
        if ($capabilities['approvalMatrix']['canView']) {
            $resolve('approvalMatrix', fn () => $this->matrixService->getApprovalIssueCount($user, $grupId));
        }
        if ($capabilities['membership']['canView']) {
            $resolve('membership', fn () => $this->membershipService->getIssueCount($user, $grupId));
        }

        return response()->json(['issues' => $issues]);
    }

    // ========================================================================
    // TAB MENU — binding menu per grup
    // ========================================================================

    public function getMenuBinding(Request $request, int $grupId): JsonResponse
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : null;
        try {
            return response()->json($this->menuBindingService->getBinding($request->user(), $grupId, $userId));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat menu binding: '.$e->getMessage()], 500);
        }
    }

    public function saveMenuBinding(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'pages' => 'present|array',
            'pages.*' => 'string|max:150',
        ]);

        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['menu']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign menu.'], 403);
        }

        try {
            $result = $this->menuBindingService->saveBinding(
                $request->user(),
                $grupId,
                (int) $validated['user_id'],
                $validated['pages'],
            );

            return response()->json([
                'message' => 'Akses menu karyawan berhasil disimpan.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan menu binding: '.$e->getMessage()], 500);
        }
    }

    public function bulkAssignMenu(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'pages' => 'required|array|min:1',
            'pages.*' => 'string|max:150',
        ]);

        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['menu']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign menu.'], 403);
        }

        try {
            $result = $this->menuBindingService->bulkAssign(
                $request->user(),
                $grupId,
                $validated['user_ids'],
                $validated['pages'],
            );

            return response()->json([
                'message' => 'Bulk assignment menu berhasil diproses.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal bulk assign menu: '.$e->getMessage()], 500);
        }
    }

    public function getMenuHistory(Request $request, int $grupId): JsonResponse
    {
        try {
            return response()->json([
                'items' => $this->menuBindingService->getHistory($request->user(), $grupId),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat riwayat menu binding: '.$e->getMessage()], 500);
        }
    }

    public function getMenuSummary(Request $request, int $grupId): JsonResponse
    {
        try {
            return response()->json($this->menuBindingService->getSummary($request->user(), $grupId));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat summary menu: '.$e->getMessage()], 500);
        }
    }

    public function purgeStaleMenuBinding(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['menu']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign menu.'], 403);
        }

        try {
            $result = $this->menuBindingService->purgeStaleBinding(
                $request->user(),
                $grupId,
                $validated['user_ids'],
            );

            return response()->json([
                'message' => 'Akses stale karyawan berhasil dibersihkan.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membersihkan akses stale: '.$e->getMessage()], 500);
        }
    }

    public function getHeaderUsers(Request $request, int $grupId): JsonResponse
    {
        $headerName = $request->query('header');
        if (empty($headerName)) {
            return response()->json(['message' => 'Parameter header wajib diisi.'], 400);
        }

        try {
            $data = $this->menuBindingService->getHeaderUsers($request->user(), $grupId, $headerName);

            return response()->json($data);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat detail sebaran karyawan: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // MATRIX MENU x KARYAWAN
    // ========================================================================

    private function filterMatrix(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'cabang' => 'nullable|string|max:100',
            'divisi_id' => 'nullable|integer',
            'sub_divisi_id' => 'nullable|integer',
            'level_id' => 'nullable|integer',
            'jabatan_id' => 'nullable|integer',
            'menu_page' => 'nullable|string|max:100',
            'menu_status' => 'nullable|in:missing_menu,has_menu,belum,sudah',
            // Diterima sebagai teks: "false" dari query string ditolak aturan boolean.
            'hanya_belum' => 'nullable|in:0,1,true,false',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:'.MenuKaryawanMatrixService::PER_HALAMAN_MAKS,
        ]);

        return [
            'search' => isset($validated['search']) ? strip_tags($validated['search']) : null,
            'cabang' => $validated['cabang'] ?? null,
            'divisi_id' => $validated['divisi_id'] ?? null,
            'sub_divisi_id' => $validated['sub_divisi_id'] ?? null,
            'level_id' => $validated['level_id'] ?? null,
            'jabatan_id' => $validated['jabatan_id'] ?? null,
            'menu_page' => $validated['menu_page'] ?? null,
            'menu_status' => in_array($validated['menu_status'] ?? 'missing_menu', ['has_menu', 'sudah'], true) ? 'has_menu' : 'missing_menu',
            'hanya_belum' => in_array($validated['hanya_belum'] ?? null, ['1', 'true'], true),
        ];
    }

    public function getMenuKaryawanMatrix(Request $request, int $grupId, MenuKaryawanMatrixService $matrix): JsonResponse
    {
        $filter = $this->filterMatrix($request);

        return response()->json($matrix->halaman(
            $request->user(),
            $grupId,
            $filter,
            (int) $request->query('page', 1),
            (int) $request->query('per_page', 25),
        ));
    }

    public function exportMenuKaryawanMatrix(
        Request $request,
        int $grupId,
        MenuKaryawanMatrixService $matrix,
        MenuKaryawanMatrixExportService $ekspor,
    ): JsonResponse {
        $filter = $this->filterMatrix($request);
        $user = $request->user();
        $matrix->assertCanView($user, $grupId);

        $jumlah = $matrix->hitungKaryawan($user, $grupId, $filter);
        if ($jumlah === 0) {
            return response()->json(['message' => 'Tidak ada karyawan yang cocok dengan saringan ini.'], 422);
        }
        if ($ekspor->melebihiPlafon($jumlah, $matrix->menuGrup($grupId)->count())) {
            return response()->json([
                'message' => "{$jumlah} karyawan terlalu banyak untuk satu berkas Excel. Persempit saringan (mis. per divisi) lalu coba lagi.",
            ], 422);
        }

        $kodeGrup = $ekspor->kodeGrup($grupId);
        $exportId = $ekspor->makeExportId();
        $ekspor->startJob($exportId, 'xlsx', [
            'judul' => 'Matrix Menu × Karyawan '.$kodeGrup,
            'grup_id' => $grupId,
            'grup_kode' => $kodeGrup,
            'id_users' => (int) $user->Id_Users,
            'filter' => $filter,
        ], $jumlah, optional($user->karyawan)->Kode_Karyawan);
        $ekspor->dispatchJob($exportId);

        return response()->json(['ok' => true, 'export_id' => $exportId]);
    }

    // ========================================================================
    // TAB KEANGGOTAAN
    // ========================================================================

    public function getMembershipOverview(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'config_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
        ]);

        return response()->json($this->membershipService->getOverview(
            $request->user(),
            $grupId,
            isset($validated['config_id']) ? (int) $validated['config_id'] : null,
            $validated['search'] ?? null,
        ));
    }

    public function searchMembershipEmployees(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:leader,member',
            'config_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'only_unassigned' => 'nullable|boolean',
            'divisi' => 'nullable|string|max:50',
            'cabang' => 'nullable|string|max:100',
            'assignment_status' => 'nullable|string|in:all,assigned,unassigned,leader,other',
            'leader_code' => 'nullable|string|max:50',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        return response()->json([
            'items' => $this->membershipService->searchEmployees(
                $request->user(),
                $grupId,
                $validated['type'],
                $validated['search'] ?? null,
                isset($validated['config_id']) ? (int) $validated['config_id'] : null,
                $validated,
            ),
            'filter_options' => $validated['type'] === 'member'
                ? $this->membershipService->getEmployeeFilterOptions($request->user(), $grupId)
                : null,
        ]);
    }

    public function previewMembership(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'config_id' => 'required|integer',
            'action' => 'required|string|in:assign,remove',
            'mode' => 'nullable|string|in:add,move,remove',
            'leader_code' => 'nullable|string|max:50',
            'source_leader_code' => 'nullable|string|max:50',
            'member_codes' => 'required|array|min:1',
            'member_codes.*' => 'required|string|max:50',
        ]);

        return response()->json($this->membershipService->preview(
            $request->user(),
            $grupId,
            (int) $validated['config_id'],
            $validated['action'],
            $validated['member_codes'],
            $validated['leader_code'] ?? null,
            $validated['mode'] ?? null,
            $validated['source_leader_code'] ?? null,
        ));
    }

    public function assignMembership(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'config_id' => 'required|integer',
            'leader_code' => 'required|string|max:50',
            'source_leader_code' => 'nullable|string|max:50',
            'mode' => 'nullable|string|in:add,move',
            'member_codes' => 'required|array|min:1',
            'member_codes.*' => 'required|string|max:50',
            'note' => 'nullable|string|max:500',
            'expected_assignments' => 'nullable|array',
            'expected_assignments.*' => 'nullable|string|max:50',
            'release_assignments' => 'nullable|array',
            'release_assignments.*' => 'array',
            'release_assignments.*.*' => 'required|string|max:50',
        ]);

        return response()->json([
            'message' => 'Keanggotaan berhasil disimpan.',
            'detail' => $this->membershipService->assign(
                $request->user(),
                $grupId,
                (int) $validated['config_id'],
                $validated['leader_code'],
                $validated['member_codes'],
                $validated['note'] ?? null,
                $validated['expected_assignments'] ?? null,
                $validated['mode'] ?? 'move',
                $validated['source_leader_code'] ?? null,
                $validated['release_assignments'] ?? [],
            ),
        ]);
    }

    public function removeMembership(Request $request, int $grupId): JsonResponse
    {
        $validated = $request->validate([
            'config_id' => 'required|integer',
            'source_leader_code' => 'nullable|string|max:50',
            'member_codes' => 'required|array|min:1',
            'member_codes.*' => 'required|string|max:50',
            'note' => 'nullable|string|max:500',
            'expected_assignments' => 'nullable|array',
            'expected_assignments.*' => 'nullable|string|max:50',
        ]);

        return response()->json([
            'message' => 'Anggota berhasil dilepas dari tim.',
            'detail' => $this->membershipService->remove(
                $request->user(),
                $grupId,
                (int) $validated['config_id'],
                $validated['member_codes'],
                $validated['note'] ?? null,
                $validated['expected_assignments'] ?? null,
                $validated['source_leader_code'] ?? null,
            ),
        ]);
    }

    public function getMembershipHistory(Request $request, int $grupId): JsonResponse
    {
        return response()->json(['items' => $this->membershipService->history($request->user(), $grupId)]);
    }

    public function rollbackMembership(Request $request, int $grupId, string $transactionNo): JsonResponse
    {
        $validated = $request->validate(['execute' => 'nullable|boolean']);

        return response()->json($this->membershipService->rollback(
            $request->user(),
            $grupId,
            $transactionNo,
            (bool) ($validated['execute'] ?? false),
        ));
    }

    // ========================================================================
    // TAB APPROVAL MATRIX — configs & step matrix
    // ========================================================================

    public function getConfigs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'search' => 'nullable|string|max:100',
        ]);
        $this->assertApprovalCanView($request, (int) $validated['grup_id']);

        try {
            return response()->json(
                $this->matrixService->getConfigs(
                    $request->user(),
                    isset($validated['grup_id']) ? (int) $validated['grup_id'] : null,
                    $validated['search'] ?? null,
                ),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat approval config: '.$e->getMessage()], 500);
        }
    }

    public function getApprovalSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
        ]);
        $this->assertApprovalCanView($request, (int) $validated['grup_id']);

        try {
            return response()->json(
                $this->matrixService->getApprovalSummary($request->user(), (int) $validated['grup_id']),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat ringkasan approval: '.$e->getMessage()], 500);
        }
    }

    public function getConfigMatrix(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->assertApprovalCanView($request, $grupId);
        $this->matrixService->assertConfigBelongsToGrup($request->user(), $id, $grupId);
        try {
            return response()->json($this->matrixService->getConfigMatrix($request->user(), $id));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat matrix config: '.$e->getMessage()], 500);
        }
    }

    public function getRoleMembers(Request $request, int $configDetailId): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->assertApprovalCanView($request, $grupId);
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), $configDetailId, $grupId);
        try {
            return response()->json($this->matrixService->getRoleMembers($request->user(), $configDetailId));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat anggota role: '.$e->getMessage()], 500);
        }
    }

    // ========================================================================
    // ASSIGNMENTS — requester -> approver per step
    // ========================================================================

    public function getAssignments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'config_detail_id' => 'required|integer',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $this->assertApprovalCanView($request, (int) $validated['grup_id']);
        $this->matrixService->assertConfigDetailBelongsToGrup(
            $request->user(),
            (int) $validated['config_detail_id'],
            (int) $validated['grup_id'],
        );

        try {
            return response()->json(
                $this->matrixService->getAssignments(
                    $request->user(),
                    (int) $validated['config_detail_id'],
                    $validated,
                ),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat assignment approver: '.$e->getMessage()], 500);
        }
    }

    public function replaceAssignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'config_detail_id' => 'required|integer',
            'kode_requester' => 'required|string|max:50',
            'approvers' => 'present|array',
            'approvers.*.kode' => 'required|string|max:50',
            'approvers.*.order_flow' => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:500',
        ]);

        $grupId = (int) $validated['grup_id'];
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), (int) $validated['config_detail_id'], $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['approvalMatrix']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign approval matrix.'], 403);
        }

        try {
            $result = $this->matrixService->replaceRequesterFlow(
                $request->user(),
                (int) $validated['config_detail_id'],
                $validated['kode_requester'],
                $validated['approvers'],
                $validated['catatan'] ?? null,
            );

            return response()->json([
                'message' => 'Assignment approver berhasil disimpan.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan assignment: '.$e->getMessage()], 500);
        }
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'config_detail_id' => 'required|integer',
            'kode_approver' => 'required|string|max:50',
            'requester_kodes' => 'required|array|min:1',
            'requester_kodes.*' => 'string|max:50',
            'order_flow' => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:500',
        ]);

        $grupId = (int) $validated['grup_id'];
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), (int) $validated['config_detail_id'], $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['approvalMatrix']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign approval matrix.'], 403);
        }

        try {
            $result = $this->matrixService->bulkAssign(
                $request->user(),
                (int) $validated['config_detail_id'],
                $validated['kode_approver'],
                $validated['requester_kodes'],
                (int) $validated['order_flow'],
                $validated['catatan'] ?? null,
            );

            return response()->json([
                'message' => 'Bulk assign selesai. Berhasil: '.
                    count($result['applied']).
                    ', dilewati: '.
                    count($result['skipped']).
                    '.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal bulk assign: '.$e->getMessage()], 500);
        }
    }

    public function bulkReplaceAssignments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'config_detail_id' => 'required|integer',
            'requester_kodes' => 'required|array|min:1',
            'requester_kodes.*' => 'string|max:50',
            'approvers' => 'present|array',
            'approvers.*.kode' => 'required|string|max:50',
            'approvers.*.order_flow' => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:500',
        ]);

        $grupId = (int) $validated['grup_id'];
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), (int) $validated['config_detail_id'], $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['approvalMatrix']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign approval matrix.'], 403);
        }

        try {
            $result = $this->matrixService->bulkReplaceRequesterFlows(
                $request->user(),
                (int) $validated['config_detail_id'],
                $validated['requester_kodes'],
                $validated['approvers'],
                $validated['catatan'] ?? null,
            );

            return response()->json([
                'message' => 'Bulk replace alur approver selesai.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal melakukan bulk replace alur: '.$e->getMessage()], 500);
        }
    }

    public function bulkSubstituteAssignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'config_detail_id' => 'required|integer',
            'old_approver_kode' => 'required|string|max:50',
            'new_approver_kode' => 'required|string|max:50',
            'catatan' => 'nullable|string|max:500',
        ]);

        $grupId = (int) $validated['grup_id'];
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), (int) $validated['config_detail_id'], $grupId);
        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        if (! $capabilities['approvalMatrix']['canAssign']) {
            return response()->json(['message' => 'Anda tidak memiliki hak assign approval matrix.'], 403);
        }

        try {
            $result = $this->matrixService->bulkSubstituteApprover(
                $request->user(),
                (int) $validated['config_detail_id'],
                $validated['old_approver_kode'],
                $validated['new_approver_kode'],
                $validated['catatan'] ?? null,
            );

            return response()->json([
                'message' => 'Substitusi approver selesai. Berhasil memperbarui '.$result['updated_count'].' alur.',
                'detail' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal melakukan substitusi approver: '.$e->getMessage()], 500);
        }
    }

    public function getAssignmentHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'config_detail_id' => 'nullable|integer',
            'grup_id' => 'required|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        if (! empty($validated['config_detail_id'])) {
            $this->matrixService->assertConfigDetailBelongsToGrup(
                $request->user(),
                (int) $validated['config_detail_id'],
                (int) $validated['grup_id'],
            );
        }

        try {
            return response()->json(
                $this->matrixService->getAssignmentHistory(
                    $request->user(),
                    isset($validated['config_detail_id']) ? (int) $validated['config_detail_id'] : null,
                    $validated,
                ),
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat riwayat: '.$e->getMessage()], 500);
        }
    }

    public function getFilterOptions(Request $request): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        try {
            $targetQuery = DB::table('Karyawan as scoped')->where('scoped.Aktif', 'Y')->select('scoped.Kode_Karyawan');
            $this->targetScopeService->applyToQuery($targetQuery, $request->user(), $grupId, 'scoped');
            $targetCodes = $targetQuery->pluck('scoped.Kode_Karyawan');

            // Lokasi (Cabang_Untuk_Gaji)
            $cabang = \Illuminate\Support\Facades\DB::table('Karyawan')
                ->select('Cabang_Untuk_Gaji as value', 'Cabang_Untuk_Gaji as label')
                ->whereNotNull('Cabang_Untuk_Gaji')
                ->where('Cabang_Untuk_Gaji', '!=', '')
                ->whereIn('Kode_Karyawan', $targetCodes->all())
                ->groupBy('Cabang_Untuk_Gaji')
                ->orderBy('Cabang_Untuk_Gaji')
                ->get();

            // Divisi
            $divisi = \Illuminate\Support\Facades\DB::table('View_Divisi_Sub_Divisi')
                ->join('Karyawan as scoped_employee', 'scoped_employee.ID_Divisi_Sub_Divisi', '=', 'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI')
                ->select('ID_Divisi as value', 'nama_divisi as label')
                ->whereNotNull('ID_Divisi')
                ->whereIn('scoped_employee.Kode_Karyawan', $targetCodes->all())
                ->groupBy('ID_Divisi', 'nama_divisi')
                ->orderBy('nama_divisi')
                ->get();

            // Sub Divisi (Department)
            $subDivisi = \Illuminate\Support\Facades\DB::table('View_Divisi_Sub_Divisi')
                ->join('Karyawan as scoped_employee', 'scoped_employee.ID_Divisi_Sub_Divisi', '=', 'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI')
                ->select('View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI as value', 'View_Divisi_Sub_Divisi.nama_sub_divisi as label', 'View_Divisi_Sub_Divisi.ID_Divisi as divisi_id')
                ->whereNotNull('View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI')
                ->whereIn('scoped_employee.Kode_Karyawan', $targetCodes->all())
                ->groupBy('View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI', 'View_Divisi_Sub_Divisi.nama_sub_divisi', 'View_Divisi_Sub_Divisi.ID_Divisi')
                ->orderBy('View_Divisi_Sub_Divisi.nama_sub_divisi')
                ->get();

            // Level
            $level = \Illuminate\Support\Facades\DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                ->join('Karyawan as scoped_employee', 'scoped_employee.ID_Level_Jabatan', '=', 'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan')
                ->select('ID_Level as value', 'nama_level as label')
                ->whereNotNull('ID_Level')
                ->whereIn('scoped_employee.Kode_Karyawan', $targetCodes->all())
                ->groupBy('ID_Level', 'nama_level')
                ->orderBy('nama_level')
                ->get();

            // Jabatan
            $jabatan = \Illuminate\Support\Facades\DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                ->join('Karyawan as scoped_employee', 'scoped_employee.ID_Level_Jabatan', '=', 'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan')
                ->select('ID_Jabatan as value', 'nama_jabatan as label', 'ID_Level as level_id')
                ->whereNotNull('ID_Jabatan')
                ->whereIn('scoped_employee.Kode_Karyawan', $targetCodes->all())
                ->groupBy('ID_Jabatan', 'nama_jabatan', 'ID_Level')
                ->orderBy('nama_jabatan')
                ->get();

            return response()->json([
                'cabang' => $cabang,
                'divisi' => $divisi,
                'subDivisi' => $subDivisi,
                'level' => $level,
                'jabatan' => $jabatan,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat filter options: '.$e->getMessage()], 500);
        }
    }

    public function filterKaryawan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'cabang' => 'nullable|string',
            'divisi_id' => 'nullable|integer',
            'sub_divisi_id' => 'nullable|integer',
            'level_id' => 'nullable|integer',
            'jabatan_id' => 'nullable|integer',
        ]);

        try {
            $query = \Illuminate\Support\Facades\DB::table('Karyawan as a')
                ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
                ->where('a.Aktif', 'Y');
            $this->targetScopeService->applyToQuery($query, $request->user(), (int) $validated['grup_id'], 'a');

            if (! empty($validated['cabang'])) {
                $query->where('a.Cabang_Untuk_Gaji', $validated['cabang']);
            }
            if (! empty($validated['divisi_id'])) {
                $query->where('b.ID_Divisi', $validated['divisi_id']);
            }
            if (! empty($validated['sub_divisi_id'])) {
                $query->where('a.ID_Divisi_Sub_Divisi', $validated['sub_divisi_id']);
            }
            if (! empty($validated['level_id'])) {
                $query->where('c.ID_Level', $validated['level_id']);
            }
            if (! empty($validated['jabatan_id'])) {
                $query->where('c.ID_Jabatan', $validated['jabatan_id']);
            }

            $items = $query
                ->select([
                    'a.UserID_Web',
                    'a.Kode_Karyawan',
                    'a.Nama',
                    'a.UserId_Absen as NIK',
                    'b.nama_divisi as Divisi',
                    'b.nama_sub_divisi as SubDivisi',
                    'c.nama_level as Level',
                    'c.nama_jabatan as Jabatan',
                    'a.Cabang_Untuk_Gaji',
                ])
                ->orderBy('a.Nama')
                ->limit(300)
                ->get();

            return response()->json([
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memfilter karyawan: '.$e->getMessage()], 500);
        }
    }

    public function searchKaryawan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grup_id' => 'required|integer|min:1',
            'purpose' => 'required|in:target,approver',
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        return response()->json([
            'items' => $this->matrixService->searchKaryawan(
                $request->user(),
                (int) $validated['grup_id'],
                $validated['purpose'],
                $validated['search'] ?? null,
                (int) ($validated['limit'] ?? 20),
            ),
        ]);
    }

    // ========================================================================
    // SYSTEM ADMIN — approval config CRUD
    // ========================================================================

    public function createConfig(Request $request): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $validated = $this->validateConfigPayload($request);
        $validated['Id_Grup_Fitur'] = $grupId;

        try {
            $id = $this->matrixService->createConfig($request->user(), $validated);

            return response()->json(['message' => 'Approval config berhasil dibuat.', 'id' => $id]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat approval config: '.$e->getMessage()], 500);
        }
    }

    public function updateConfig(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigBelongsToGrup($request->user(), $id, $grupId);
        $validated = $this->validateConfigPayload($request);
        $validated['Id_Grup_Fitur'] = $grupId;

        try {
            $this->matrixService->updateConfig($request->user(), $id, $validated);

            return response()->json(['message' => 'Approval config berhasil diperbarui.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memperbarui approval config: '.$e->getMessage()], 500);
        }
    }

    public function deleteConfig(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigBelongsToGrup($request->user(), $id, $grupId);
        try {
            $this->matrixService->deleteConfig($request->user(), $id);

            return response()->json(['message' => 'Approval config berhasil dinonaktifkan.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menonaktifkan approval config: '.$e->getMessage()], 500);
        }
    }

    public function bindConfigGrupFitur(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigBelongsToGrup($request->user(), $id, $grupId, true);

        try {
            $this->matrixService->bindConfigGrupFitur($request->user(), $id, $grupId);

            return response()->json(['message' => 'Binding grup fitur berhasil disimpan.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan binding grup fitur: '.$e->getMessage()], 500);
        }
    }

    private function validateConfigPayload(Request $request): array
    {
        return $request->validate([
            'Kode_Config' => 'required|string|max:100',
            'Nama_Config' => 'nullable|string|max:200',
            'Deskripsi' => 'nullable|string|max:500',
            'Skip_If_Requester_Level_Id' => 'nullable|integer',
            'grup_id' => 'required|integer|min:1',
            'Flag_Aktif' => 'nullable|string|in:Y,T',
        ]);
    }

    // ========================================================================
    // SYSTEM ADMIN — config detail (step) CRUD
    // ========================================================================

    public function createConfigDetail(Request $request, int $configId): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigBelongsToGrup($request->user(), $configId, $grupId);
        $validated = $this->validateConfigDetailPayload($request);

        try {
            $id = $this->matrixService->createConfigDetail($request->user(), $configId, $validated);

            return response()->json(['message' => 'Step approval berhasil dibuat.', 'id' => $id]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat step approval: '.$e->getMessage()], 500);
        }
    }

    public function updateConfigDetail(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), $id, $grupId);
        $validated = $this->validateConfigDetailPayload($request);

        try {
            $this->matrixService->updateConfigDetail($request->user(), $id, $validated);

            return response()->json(['message' => 'Step approval berhasil diperbarui.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memperbarui step approval: '.$e->getMessage()], 500);
        }
    }

    public function deleteConfigDetail(Request $request, int $id): JsonResponse
    {
        $grupId = $this->requireGrupContext($request);
        $this->matrixService->assertConfigDetailBelongsToGrup($request->user(), $id, $grupId);
        try {
            $this->matrixService->deleteConfigDetail($request->user(), $id);

            return response()->json(['message' => 'Step approval berhasil dinonaktifkan.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menonaktifkan step approval: '.$e->getMessage()], 500);
        }
    }

    private function validateConfigDetailPayload(Request $request): array
    {
        return $request->validate([
            'Approval_Order' => 'required|integer|min:1',
            'Custom_Approver_Kode' => 'nullable|string|max:50',
            'Id_Jenis_Approval' => 'nullable|integer',
            'Order_Approver_Limit' => 'nullable|integer|min:1',
            'Is_Mandatory' => 'nullable|string|in:Y,T',
            'Stop_If_Rejected' => 'nullable|string|in:Y,T',
            'Auto_Approve_After_Days' => 'nullable|integer|min:1',
            'Flag_Aktif' => 'nullable|string|in:Y,T',
        ]);
    }

    private function requireGrupContext(Request $request): int
    {
        $validated = $request->validate(['grup_id' => 'required|integer|min:1']);
        $grupId = (int) $validated['grup_id'];
        $this->matrixService->assertCanManageGrup($request->user(), $grupId);

        return $grupId;
    }

    private function assertApprovalCanView(Request $request, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($request->user(), $grupId);
        abort_unless($capabilities['approvalMatrix']['canView'], 403, 'Anda tidak memiliki akses approval matrix pada grup ini.');
    }

    // ========================================================================
    // SYSTEM ADMIN — jenis approval CRUD
    // ========================================================================

    public function getJenisApproval(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => 'nullable|string|max:100']);

        try {
            return response()->json([
                'items' => $this->matrixService->getJenisApproval($request->user(), $validated['search'] ?? null),
                'sourceTables' => $this->matrixService->getSourceTableOptions(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat jenis approval: '.$e->getMessage()], 500);
        }
    }

    public function createJenisApproval(Request $request): JsonResponse
    {
        $validated = $this->validateJenisApprovalPayload($request);

        try {
            $id = $this->matrixService->createJenisApproval($request->user(), $validated);

            return response()->json(['message' => 'Jenis approval berhasil dibuat.', 'id' => $id]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat jenis approval: '.$e->getMessage()], 500);
        }
    }

    public function updateJenisApproval(Request $request, int $id): JsonResponse
    {
        $validated = $this->validateJenisApprovalPayload($request);

        try {
            $this->matrixService->updateJenisApproval($request->user(), $id, $validated);

            return response()->json(['message' => 'Jenis approval berhasil diperbarui.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memperbarui jenis approval: '.$e->getMessage()], 500);
        }
    }

    public function deleteJenisApproval(Request $request, int $id): JsonResponse
    {
        try {
            $this->matrixService->deleteJenisApproval($request->user(), $id);

            return response()->json(['message' => 'Jenis approval berhasil dinonaktifkan.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menonaktifkan jenis approval: '.$e->getMessage()], 500);
        }
    }

    private function validateJenisApprovalPayload(Request $request): array
    {
        return $request->validate([
            'Nama_Approval' => 'required|string|max:100',
            'Source_Type' => 'required|string|in:TABLE,KPI_TABLE,GROUP_TABLE,DIRECT_EMPLOYEE,ROLE',
            'Source_Reference' => 'nullable|string|max:100',
            'Auto_Approve' => 'nullable|string|in:Y,T',
            'Keterangan' => 'nullable|string|max:255',
            'Flag_Aktif' => 'nullable|string|in:Y,T',
        ]);
    }
}
