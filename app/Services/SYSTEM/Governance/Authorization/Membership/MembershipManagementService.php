<?php

namespace App\Services\SYSTEM\Governance\Authorization\Membership;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalMatrixService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MembershipManagementService
{
    public function __construct(
        private readonly ApprovalMatrixService $matrixService,
        private readonly EmployeeTargetScopeService $targetScopeService,
    ) {}

    public function getIssueCount(User $user, int $grupId): int
    {
        $this->assertCanView($user, $grupId);
        $configs = $this->configsForGroup($grupId);
        if ($configs->isEmpty()) {
            return 1;
        }

        $activeEmployeeQuery = DB::table('Karyawan as employee')
            ->where('employee.Aktif', 'Y')
            ->whereNotNull('employee.Kode_Karyawan');
        $this->targetScopeService->applyToQuery($activeEmployeeQuery, $user, $grupId, 'employee');
        $activeEmployeeCodes = $activeEmployeeQuery
            ->pluck('employee.Kode_Karyawan')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique();
        $activeLookup = $activeEmployeeCodes->flip();
        $issueCount = 0;

        foreach ($configs as $config) {
            $this->validateSource($config);
            $rows = $this->activeMembershipRows($config)
                ->filter(fn ($row) => $activeLookup->has(trim((string) $row->member_code))
                    && $activeLookup->has(trim((string) $row->leader_code)));
            $memberCodes = $rows->pluck('member_code')->map(fn ($code) => trim((string) $code))->filter();
            $leaderCodes = $rows->pluck('leader_code')->map(fn ($code) => trim((string) $code))->filter();
            $assignedActive = $memberCodes->unique()->filter(fn ($code) => $activeLookup->has($code))->count();

            if ($activeEmployeeCodes->count() > $assignedActive) {
                $issueCount++;
            }
            if ($memberCodes->merge($leaderCodes)->unique()->contains(fn ($code) => ! $activeLookup->has($code))) {
                $issueCount++;
            }
        }

        return $issueCount;
    }

    public function getOverview(User $user, int $grupId, ?int $configId = null, ?string $search = null): array
    {
        $this->assertCanView($user, $grupId);
        $configs = $this->configsForGroup($grupId);

        if ($configs->isEmpty()) {
            return [
                'setup_required' => true,
                'configs' => [],
                'selected_config_id' => null,
                'metrics' => ['leaders' => 0, 'members' => 0, 'unassigned' => 0, 'active_employees' => 0, 'coverage_percent' => 0],
                'issues' => [],
                'unassigned_employees' => [],
                'recent_activity' => [],
                'leaders' => [],
            ];
        }

        $config = $configs->firstWhere('Id', $configId) ?? $configs->first();
        $this->validateSource($config);
        $scopedEmployeesQuery = DB::table('Karyawan as employee')
            ->where('employee.Aktif', 'Y')
            ->select('employee.Kode_Karyawan', 'employee.Nama', 'employee.Cabang_Untuk_Gaji', 'employee.Kode_Divisi');
        $this->targetScopeService->applyToQuery($scopedEmployeesQuery, $user, $grupId, 'employee');
        $activeEmployees = $scopedEmployeesQuery->get();
        $scopedCodes = $activeEmployees->pluck('Kode_Karyawan')->filter()->unique();
        $memberRows = $this->activeMembershipRows($config)
            ->filter(fn ($row) => $scopedCodes->contains($row->member_code) && $scopedCodes->contains($row->leader_code))
            ->values();
        $leaderCodes = $memberRows->pluck('leader_code')->filter()->unique()->values();
        $memberCodes = $memberRows->pluck('member_code')->filter()->unique()->values();
        $employees = $this->employeesByCode($leaderCodes->merge($memberCodes)->unique());
        $activeEmployeeCodes = $activeEmployees->pluck('Kode_Karyawan');
        $activeMemberCodes = $memberCodes->intersect($activeEmployeeCodes)->values();
        $unassignedEmployees = $activeEmployees
            ->reject(fn ($employee) => $activeMemberCodes->contains($employee->Kode_Karyawan))
            ->map(fn ($employee) => [
                'kode' => $employee->Kode_Karyawan,
                'nama' => $employee->Nama,
                'lokasi' => $employee->Cabang_Untuk_Gaji,
            ])
            ->sortBy('nama')
            ->values();
        $duplicateMemberCodes = $memberRows->groupBy('member_code')->filter(fn (Collection $rows) => $rows->count() > 1)->keys();
        $inactiveMemberCodes = $memberCodes->reject(fn ($code) => ($employees->get($code)->Aktif ?? null) === 'Y')->values();
        $inactiveLeaderCodes = $leaderCodes->reject(fn ($code) => ($employees->get($code)->Aktif ?? null) === 'Y')->values();

        $allLeaders = $memberRows
            ->groupBy('leader_code')
            ->map(function (Collection $rows, string $leaderCode) use ($employees) {
                $leader = $employees->get($leaderCode);
                $members = $rows->map(function ($row) use ($employees) {
                    $employee = $employees->get($row->member_code);

                    return [
                        'kode' => $row->member_code,
                        'nama' => $employee->Nama ?? $row->member_code,
                        'aktif' => $employee->Aktif ?? null,
                    ];
                })->values();

                return [
                    'kode' => $leaderCode,
                    'nama' => $leader->Nama ?? $leaderCode,
                    'member_count' => $members->count(),
                    'members' => $members,
                ];
            })
            ->sortBy('nama')
            ->values();
        $leaders = $allLeaders
            ->when($search, fn (Collection $items) => $items->filter(
                fn (array $item) => Str::contains(Str::lower($item['kode'].' '.$item['nama']), Str::lower($search))
                    || collect($item['members'])->contains(fn (array $member) => Str::contains(
                        Str::lower($member['kode'].' '.$member['nama']),
                        Str::lower($search),
                    )),
            ))
            ->values();

        $activeEmployeeCount = $activeEmployees->count();
        $largestTeam = $allLeaders->max('member_count') ?? 0;
        $coveragePercent = $activeEmployeeCount > 0
            ? (int) round(($activeMemberCodes->count() / $activeEmployeeCount) * 100)
            : 0;

        $issues = collect();
        if ($unassignedEmployees->isNotEmpty()) {
            $issues->push([
                'type' => 'unassigned',
                'severity' => 'warning',
                'title' => $unassignedEmployees->count().' karyawan belum memiliki tim',
                'description' => 'Lengkapi keanggotaan agar akses dan alur berbasis leader berjalan konsisten.',
                'count' => $unassignedEmployees->count(),
                'action' => 'bulk',
            ]);
        }
        if ($duplicateMemberCodes->isNotEmpty()) {
            $issues->push([
                'type' => 'duplicate',
                'severity' => 'info',
                'title' => $duplicateMemberCodes->count().' anggota terhubung ke lebih dari satu leader',
                'description' => 'Kondisi ini diperbolehkan. Pastikan setiap keanggotaan memang dibutuhkan dan tidak tercatat tanpa sengaja.',
                'count' => $duplicateMemberCodes->count(),
                'action' => 'review',
                'affected_codes' => $duplicateMemberCodes->take(20)->values(),
            ]);
        }
        if ($inactiveMemberCodes->isNotEmpty() || $inactiveLeaderCodes->isNotEmpty()) {
            $issues->push([
                'type' => 'inactive',
                'severity' => 'critical',
                'title' => ($inactiveMemberCodes->count() + $inactiveLeaderCodes->count()).' relasi melibatkan karyawan nonaktif',
                'description' => 'Lepaskan anggota nonaktif atau pindahkan tim dari leader yang sudah tidak aktif.',
                'count' => $inactiveMemberCodes->count() + $inactiveLeaderCodes->count(),
                'action' => 'structure',
                'affected_codes' => $inactiveMemberCodes->merge($inactiveLeaderCodes)->unique()->take(20)->values(),
            ]);
        }

        return [
            'setup_required' => false,
            'configs' => $configs->map(fn ($item) => $this->configPayload($item))->values(),
            'selected_config_id' => (int) $config->Id,
            'metrics' => [
                'leaders' => $leaderCodes->count(),
                'members' => $activeMemberCodes->count(),
                'unassigned' => $unassignedEmployees->count(),
                'active_employees' => $activeEmployeeCount,
                'coverage_percent' => $coveragePercent,
                'average_team_size' => $leaderCodes->count() ? round($activeMemberCodes->count() / $leaderCodes->count(), 1) : 0,
                'largest_team' => $largestTeam,
                'duplicate_members' => $duplicateMemberCodes->count(),
                'inactive_relations' => $inactiveMemberCodes->count() + $inactiveLeaderCodes->count(),
            ],
            'issues' => $issues->values(),
            'unassigned_employees' => $unassignedEmployees->take(100)->values(),
            'recent_activity' => $this->recentActivity($user, $grupId),
            'leaders' => $leaders,
        ];
    }

    public function searchEmployees(User $user, int $grupId, string $type, ?string $search, ?int $configId = null, array $filters = []): Collection
    {
        $this->assertCanView($user, $grupId);
        $config = $this->findConfig($grupId, $configId);
        $membershipRows = $this->activeMembershipRows($config);
        $assignments = $membershipRows->groupBy('member_code');
        $targetLeaderCode = $filters['leader_code'] ?? null;
        $targetMemberCodes = $targetLeaderCode
            ? $membershipRows->where('leader_code', $targetLeaderCode)->pluck('member_code')->unique()->flip()
            : collect();
        $leaderNames = $this->employeesByCode($membershipRows->pluck('leader_code')->filter()->unique())
            ->mapWithKeys(fn ($employee) => [$employee->Kode_Karyawan => $employee->Nama]);

        $query = DB::table('Karyawan as employee')
            ->leftJoin('View_Divisi_Sub_Divisi as division', fn ($join) => $join
                ->on('division.ID_DIVISI_SUB_DIVISI', '=', 'employee.ID_Divisi_Sub_Divisi')
                ->on('division.kode_perusahaan', '=', 'employee.Kode_Perusahaan'))
            ->where('employee.Aktif', 'Y')
            ->select('employee.Kode_Karyawan as kode', 'employee.Nama as nama', 'employee.Cabang_Untuk_Gaji as lokasi', 'division.nama_divisi as divisi')
            ->orderBy('employee.Nama')
            ->limit((int) ($filters['limit'] ?? 50));
        $this->targetScopeService->applyToQuery($query, $user, $grupId, 'employee');

        if ($type === 'member' && ! empty($filters['only_unassigned'])) {
            $assigned = $assignments->keys()->all();
            $query->whereNotIn('employee.Kode_Karyawan', empty($assigned) ? ['__NONE__'] : $assigned);
        }
        if ($type === 'member' && ! empty($filters['assignment_status'])) {
            $status = $filters['assignment_status'];
            $leaderCode = $filters['leader_code'] ?? null;
            $assignedCodes = $assignments->keys()->all();
            if ($status === 'unassigned') {
                $query->whereNotIn('employee.Kode_Karyawan', empty($assignedCodes) ? ['__NONE__'] : $assignedCodes);
            } elseif ($status === 'assigned') {
                $query->whereIn('employee.Kode_Karyawan', empty($assignedCodes) ? ['__NONE__'] : $assignedCodes);
            } elseif ($status === 'leader' && $leaderCode) {
                $codes = $membershipRows->where('leader_code', $leaderCode)->pluck('member_code')->unique()->all();
                $query->whereIn('employee.Kode_Karyawan', empty($codes) ? ['__NONE__'] : $codes);
            } elseif ($status === 'other' && $leaderCode) {
                $codes = $membershipRows->where('leader_code', '!=', $leaderCode)->pluck('member_code')->unique()->all();
                $query->whereIn('employee.Kode_Karyawan', empty($codes) ? ['__NONE__'] : $codes);
            }
        }
        if (! empty($filters['cabang'])) {
            $query->where('employee.Cabang_Untuk_Gaji', $filters['cabang']);
        }
        if (! empty($filters['divisi'])) {
            $query->where('division.ID_Divisi', $filters['divisi']);
        }
        if ($search) {
            $query->where(fn ($q) => $q
                ->where('employee.Kode_Karyawan', 'like', '%'.$search.'%')
                ->orWhere('employee.Nama', 'like', '%'.$search.'%'));
        }

        return $query->get()->map(function ($employee) use ($assignments, $leaderNames, $targetMemberCodes, $targetLeaderCode) {
            $employeeAssignments = collect($assignments->get($employee->kode, collect()));
            $currentLeaderCodes = $employeeAssignments->pluck('leader_code')->filter()->unique()->values();
            $currentLeaders = $currentLeaderCodes->map(fn ($code) => [
                'code' => $code,
                'name' => $leaderNames->get($code) ?? $code,
            ])->values();
            $employee->assigned = $currentLeaders->isNotEmpty();
            $employee->in_target_team = $targetMemberCodes->has($employee->kode);
            $employee->is_target_leader = $targetLeaderCode !== null && $employee->kode === $targetLeaderCode;
            $employee->current_leader_code = $currentLeaderCodes->first();
            $employee->current_leader_codes = $currentLeaderCodes;
            $employee->current_leader_name = $currentLeaders->first()['name'] ?? null;
            $employee->current_leaders = $currentLeaders;
            $employee->current_leader_names = $currentLeaders->pluck('name')->values();

            return $employee;
        })->filter(function ($employee) use ($filters) {
            $status = $filters['assignment_status'] ?? 'all';
            $leaderCode = $filters['leader_code'] ?? null;
            if ($status === 'unassigned' && $employee->assigned) {
                return false;
            }
            if ($status === 'assigned' && ! $employee->assigned) {
                return false;
            }
            if ($status === 'leader' && ! collect($employee->current_leader_codes)->contains($leaderCode)) {
                return false;
            }
            if ($status === 'other' && (! $employee->assigned || collect($employee->current_leader_codes)->contains($leaderCode))) {
                return false;
            }

            return true;
        })->values();
    }

    public function getEmployeeFilterOptions(User $user, int $grupId): array
    {
        $this->assertCanView($user, $grupId);

        $targetQuery = DB::table('Karyawan as employee')
            ->where('employee.Aktif', 'Y')
            ->select('employee.Kode_Karyawan');
        $this->targetScopeService->applyToQuery($targetQuery, $user, $grupId, 'employee');
        $targetCodes = $targetQuery->pluck('employee.Kode_Karyawan')->all();

        return [
            'divisions' => DB::table('Karyawan as employee')
                ->join('View_Divisi_Sub_Divisi as division', fn ($join) => $join
                    ->on('division.ID_DIVISI_SUB_DIVISI', '=', 'employee.ID_Divisi_Sub_Divisi')
                    ->on('division.kode_perusahaan', '=', 'employee.Kode_Perusahaan'))
                ->where('employee.Aktif', 'Y')
                ->whereIn('employee.Kode_Karyawan', $targetCodes)
                ->whereNotNull('division.ID_Divisi')
                ->whereNotNull('division.nama_divisi')
                ->select('division.ID_Divisi as value', 'division.nama_divisi as label')
                ->distinct()
                ->orderBy('division.nama_divisi')
                ->get()
                ->values(),
            'locations' => DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->whereIn('Kode_Karyawan', $targetCodes)
                ->whereNotNull('Cabang_Untuk_Gaji')
                ->where('Cabang_Untuk_Gaji', '!=', '')
                ->distinct()
                ->orderBy('Cabang_Untuk_Gaji')
                ->pluck('Cabang_Untuk_Gaji')
                ->values(),
        ];
    }

    public function preview(User $user, int $grupId, int $configId, string $action, array $memberCodes, ?string $leaderCode = null, ?string $mode = null, ?string $sourceLeaderCode = null): array
    {
        $this->assertCanAssign($user, $grupId);
        $config = $this->findConfig($grupId, $configId);
        abort_unless(in_array($action, ['assign', 'remove'], true), 422, 'Aksi keanggotaan tidak valid.');
        abort_if($action === 'assign' && ! $leaderCode, 422, 'Leader tujuan wajib dipilih.');
        if ($action === 'assign') {
            $this->validateEmployeeCodes(array_merge([$leaderCode], $memberCodes));
        }
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            $grupId,
            array_merge($memberCodes, array_filter([$leaderCode]), $this->leaderLepasYangAktif([$sourceLeaderCode])),
        );

        return $this->buildOperationPreview($config, $action, $memberCodes, $leaderCode, $mode ?? ($action === 'remove' ? 'remove' : 'move'), $sourceLeaderCode);
    }

    public function assign(User $user, int $grupId, int $configId, string $leaderCode, array $memberCodes, ?string $note, ?array $expectedAssignments = null, string $mode = 'move', ?string $sourceLeaderCode = null, array $releaseAssignments = []): array
    {
        $this->assertCanAssign($user, $grupId);
        abort_unless(in_array($mode, ['add', 'move'], true), 422, 'Mode assignment keanggotaan tidak valid.');
        $config = $this->findConfig($grupId, $configId);
        $this->validateEmployeeCodes(array_merge([$leaderCode], $memberCodes));
        $releaseLeaderCodes = collect($releaseAssignments)->flatten()->filter()->all();
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            $grupId,
            array_merge($memberCodes, [$leaderCode], $this->leaderLepasYangAktif(array_merge($releaseLeaderCodes, [$sourceLeaderCode]))),
        );
        $memberLookup = collect($memberCodes)->filter()->unique()->flip();
        $releaseAssignments = collect($releaseAssignments)
            ->filter(fn ($leaders, $memberCode) => $memberLookup->has($memberCode) && is_array($leaders))
            ->map(fn (array $leaders) => collect($leaders)->filter()->map(fn ($code) => trim((string) $code))->filter(fn ($code) => $code !== $leaderCode)->unique()->values()->all())
            ->filter()
            ->all();
        $transactionNo = 'MEM-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
        $affected = 0;
        $skipped = 0;
        $conflicts = [];

        DB::transaction(function () use ($user, $grupId, $config, $leaderCode, $memberCodes, $note, $transactionNo, $expectedAssignments, $mode, $sourceLeaderCode, $releaseAssignments, &$affected, &$skipped, &$conflicts) {
            foreach (array_values(array_unique($memberCodes)) as $memberCode) {
                $before = $this->findActiveMembership($config, $memberCode, $mode === 'move' ? $sourceLeaderCode : null);
                $beforeLeader = $before?->leader_code;
                if ($memberCode === $leaderCode) {
                    $conflicts[] = $this->conflictPayload($memberCode, $beforeLeader, $beforeLeader);

                    continue;
                }
                if ($mode !== 'add' && $expectedAssignments !== null && array_key_exists($memberCode, $expectedAssignments) && $expectedAssignments[$memberCode] !== $beforeLeader) {
                    $conflicts[] = $this->conflictPayload($memberCode, $expectedAssignments[$memberCode], $beforeLeader);

                    continue;
                }
                if ($this->membershipExists($config, $leaderCode, $memberCode)) {
                    $skipped++;

                    continue;
                }
                if ($mode === 'add') {
                    $this->insertMembership($config, $leaderCode, $memberCode);
                    $this->writeAudit($user, $transactionNo, $config, $grupId, 'ASSIGN', $memberCode, null, $leaderCode, $note);
                    $affected++;
                    foreach ($releaseAssignments[$memberCode] ?? [] as $releaseLeaderCode) {
                        if (! $this->membershipExists($config, $releaseLeaderCode, $memberCode)) {
                            $conflicts[] = $this->conflictPayload($memberCode, $releaseLeaderCode, null);

                            continue;
                        }
                        $this->removeMembership($config, $memberCode, $releaseLeaderCode);
                        $this->writeAudit($user, $transactionNo, $config, $grupId, 'REMOVE', $memberCode, $releaseLeaderCode, null, $note);
                        $affected++;
                    }

                    continue;
                }
                $this->upsertMembership($config, $leaderCode, $memberCode, $before);
                $this->writeAudit(
                    $user,
                    $transactionNo,
                    $config,
                    $grupId,
                    $beforeLeader ? ($beforeLeader === $leaderCode ? 'ASSIGN' : 'MOVE') : 'ASSIGN',
                    $memberCode,
                    $beforeLeader,
                    $leaderCode,
                    $note,
                );
                $affected++;
            }
        });

        return ['transaction_no' => $affected ? $transactionNo : null, 'affected' => $affected, 'skipped' => $skipped, 'conflicts' => $conflicts];
    }

    public function remove(User $user, int $grupId, int $configId, array $memberCodes, ?string $note, ?array $expectedAssignments = null, ?string $sourceLeaderCode = null): array
    {
        $this->assertCanAssign($user, $grupId);
        $config = $this->findConfig($grupId, $configId);
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            $grupId,
            array_merge($memberCodes, $this->leaderLepasYangAktif([$sourceLeaderCode])),
        );
        $transactionNo = 'MEM-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
        $affected = 0;
        $skipped = 0;
        $conflicts = [];

        DB::transaction(function () use ($user, $grupId, $config, $memberCodes, $note, $transactionNo, $expectedAssignments, $sourceLeaderCode, &$affected, &$skipped, &$conflicts) {
            foreach (array_values(array_unique($memberCodes)) as $memberCode) {
                $before = $this->findActiveMembership($config, $memberCode, $sourceLeaderCode);
                $beforeLeader = $before?->leader_code;
                if ($expectedAssignments !== null && array_key_exists($memberCode, $expectedAssignments) && $expectedAssignments[$memberCode] !== $beforeLeader) {
                    $conflicts[] = $this->conflictPayload($memberCode, $expectedAssignments[$memberCode], $beforeLeader);

                    continue;
                }
                if (! $before) {
                    $skipped++;

                    continue;
                }
                $this->removeMembership($config, $memberCode, $before->leader_code);
                $this->writeAudit($user, $transactionNo, $config, $grupId, 'REMOVE', $memberCode, $before->leader_code, null, $note);
                $affected++;
            }
        });

        return ['transaction_no' => $affected ? $transactionNo : null, 'affected' => $affected, 'skipped' => $skipped, 'conflicts' => $conflicts];
    }

    public function rollback(User $user, int $grupId, string $transactionNo, bool $execute = false): array
    {
        $this->assertCanAssign($user, $grupId);
        abort_unless(SkemaTersedia::tabel('N_HRIS_Transaksi_Membership'), 404, 'Riwayat keanggotaan tidak tersedia.');
        $rows = DB::table('N_HRIS_Transaksi_Membership')
            ->where('Id_Grup_Fitur', $grupId)
            ->where('No_Transaksi', $transactionNo)
            ->orderBy('Id')
            ->get();
        abort_if($rows->isEmpty(), 404, 'Transaksi keanggotaan tidak ditemukan.');
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            $grupId,
            $rows->flatMap(fn ($row) => [
                $row->Kode_Karyawan_Member,
                $row->Leader_Sebelum,
                $row->Leader_Sesudah,
            ])->filter()->unique()->values()->all(),
        );

        $configIds = $rows->pluck('Id_Membership_Config')->unique();
        abort_if($configIds->count() !== 1, 422, 'Transaksi lintas sumber tidak dapat dibatalkan.');
        $config = $this->findConfig($grupId, (int) $configIds->first());
        $employees = $this->employeesByCode($rows->pluck('Kode_Karyawan_Member')->unique());
        $items = $rows->map(function ($row) use ($config, $employees) {
            $beforeExists = $row->Leader_Sebelum
                ? $this->membershipExists($config, $row->Leader_Sebelum, $row->Kode_Karyawan_Member)
                : false;
            $afterExists = $row->Leader_Sesudah
                ? $this->membershipExists($config, $row->Leader_Sesudah, $row->Kode_Karyawan_Member)
                : false;
            $ready = match ($row->Jenis_Transaksi) {
                'ASSIGN' => $afterExists,
                'MOVE' => $afterExists && ! $beforeExists,
                'REMOVE' => ! $beforeExists,
                default => false,
            };

            return [
                'member_code' => $row->Kode_Karyawan_Member,
                'member_name' => $employees->get($row->Kode_Karyawan_Member)->Nama ?? $row->Kode_Karyawan_Member,
                'action' => $row->Jenis_Transaksi,
                'expected_current' => $row->Leader_Sesudah,
                'current_leader' => $afterExists ? $row->Leader_Sesudah : ($beforeExists ? $row->Leader_Sebelum : null),
                'restore_to' => $row->Leader_Sebelum,
                'status' => $ready ? 'ready' : 'conflict',
            ];
        })->values();

        $result = [
            'transaction_no' => $transactionNo,
            'ready' => $items->where('status', 'ready')->count(),
            'conflicts' => $items->where('status', 'conflict')->count(),
            'items' => $items,
        ];
        if (! $execute) {
            return $result;
        }

        $rollbackNo = 'MEM-RB-'.now()->format('ymd-His').'-'.strtoupper(Str::random(4));
        $affected = 0;
        DB::transaction(function () use ($user, $grupId, $config, $items, $transactionNo, $rollbackNo, &$affected) {
            foreach ($items->where('status', 'ready') as $item) {
                if ($item['action'] === 'ASSIGN') {
                    $this->removeMembership($config, $item['member_code'], $item['expected_current']);
                    $action = 'REMOVE';
                    $auditBefore = $item['expected_current'];
                    $auditAfter = null;
                } elseif ($item['action'] === 'MOVE') {
                    $existing = $this->findActiveMembership($config, $item['member_code'], $item['expected_current']);
                    if (! $existing) {
                        continue;
                    }
                    $this->upsertMembership($config, $item['restore_to'], $item['member_code'], $existing);
                    $action = 'MOVE';
                    $auditBefore = $item['expected_current'];
                    $auditAfter = $item['restore_to'];
                } else {
                    $this->insertMembership($config, $item['restore_to'], $item['member_code']);
                    $action = 'ASSIGN';
                    $auditBefore = null;
                    $auditAfter = $item['restore_to'];
                }
                $this->writeAudit($user, $rollbackNo, $config, $grupId, $action, $item['member_code'], $auditBefore, $auditAfter, 'Pembatalan transaksi '.$transactionNo);
                $affected++;
            }
        });
        $result['rollback_transaction_no'] = $affected ? $rollbackNo : null;
        $result['affected'] = $affected;

        return $result;
    }

    public function history(User $user, int $grupId): Collection
    {
        $this->assertCanView($user, $grupId);
        if (! SkemaTersedia::tabel('N_HRIS_Transaksi_Membership')) {
            return collect();
        }

        $query = DB::table('N_HRIS_Transaksi_Membership as t')
            ->leftJoin('Karyawan as actor', 'actor.Kode_Karyawan', '=', 't.Created_By')
            ->leftJoin('Karyawan as member', 'member.Kode_Karyawan', '=', 't.Kode_Karyawan_Member')
            ->leftJoin('Karyawan as old_leader', 'old_leader.Kode_Karyawan', '=', 't.Leader_Sebelum')
            ->leftJoin('Karyawan as new_leader', 'new_leader.Kode_Karyawan', '=', 't.Leader_Sesudah')
            ->where('t.Id_Grup_Fitur', $grupId)
            ->orderByDesc('t.Created_At')
            ->limit(300);
        $this->targetScopeService->applyToQuery($query, $user, $grupId, 'member');

        return $query->get([
            't.*',
            'actor.Nama as Actor_Name',
            'member.Nama as Member_Name',
            'old_leader.Nama as Old_Leader_Name',
            'new_leader.Nama as New_Leader_Name',
        ])
            ->groupBy('No_Transaksi')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'transaction_no' => $first->No_Transaksi,
                    'config_id' => (int) $first->Id_Membership_Config,
                    'actor' => $first->Actor_Name ?? $first->Created_By,
                    'created_at' => $first->Created_At,
                    'count' => $rows->count(),
                    'actions' => $rows->groupBy('Jenis_Transaksi')->map->count(),
                    'details' => $rows->map(fn ($row) => [
                        'action' => $row->Jenis_Transaksi,
                        'member_code' => $row->Kode_Karyawan_Member,
                        'member_name' => $row->Member_Name ?? $row->Kode_Karyawan_Member,
                        'before' => $row->Old_Leader_Name ?? $row->Leader_Sebelum,
                        'after' => $row->New_Leader_Name ?? $row->Leader_Sesudah,
                        'note' => $row->Catatan,
                    ])->values(),
                ];
            })
            ->values();
    }

    private function configsForGroup(int $grupId): Collection
    {
        if (! SkemaTersedia::tabel('N_HRIS_Membership_Config')) {
            return collect();
        }

        return DB::table('N_HRIS_Membership_Config')
            ->where('Id_Grup_Fitur', $grupId)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama_Config')
            ->get();
    }

    private function findConfig(int $grupId, ?int $configId): object
    {
        $config = $configId
            ? $this->configsForGroup($grupId)->firstWhere('Id', $configId)
            : $this->configsForGroup($grupId)->first();
        abort_unless($config, 404, 'Config keanggotaan aktif tidak ditemukan untuk grup ini.');
        $this->validateSource($config);

        return $config;
    }

    private function validateSource(object $config): void
    {
        abort_unless(SkemaTersedia::tabel($config->Source_Table), 422, 'Source table config tidak ditemukan.');
        $columns = Schema::getColumnListing($config->Source_Table);
        foreach (array_filter([$config->Leader_Column, $config->Member_Column, $config->Primary_Key_Column, $config->Active_Status_Column]) as $column) {
            abort_unless(in_array($column, $columns, true), 422, 'Kolom source config tidak valid: '.$column);
        }
    }

    private function activeMembershipRows(object $config, ?Collection $memberCodes = null): Collection
    {
        if ($memberCodes !== null && $memberCodes->isEmpty()) {
            return collect();
        }

        $query = DB::table($config->Source_Table)
            ->selectRaw($config->Leader_Column.' as leader_code, '.$config->Member_Column.' as member_code');
        if ($memberCodes !== null) {
            $query->whereIn($config->Member_Column, $memberCodes->all());
        }
        if ($config->Active_Status_Column) {
            $query->where($config->Active_Status_Column, $config->Active_Status_Value);
        }

        return $query->whereNotNull($config->Leader_Column)->whereNotNull($config->Member_Column)->get();
    }

    private function findActiveMembership(object $config, string $memberCode, ?string $leaderCode = null): ?object
    {
        $query = DB::table($config->Source_Table)
            ->where($config->Member_Column, $memberCode)
            ->selectRaw($config->Leader_Column.' as leader_code');
        if ($leaderCode !== null) {
            $query->where($config->Leader_Column, $leaderCode);
        }
        if ($config->Primary_Key_Column) {
            $query->addSelect($config->Primary_Key_Column.' as source_id');
        }
        if ($config->Active_Status_Column) {
            $query->where($config->Active_Status_Column, $config->Active_Status_Value);
        }

        return $query->first();
    }

    private function membershipExists(object $config, string $leaderCode, string $memberCode): bool
    {
        $query = DB::table($config->Source_Table)
            ->where($config->Leader_Column, $leaderCode)
            ->where($config->Member_Column, $memberCode);
        if ($config->Active_Status_Column) {
            $query->where($config->Active_Status_Column, $config->Active_Status_Value);
        }

        return $query->exists();
    }

    private function insertMembership(object $config, string $leaderCode, string $memberCode): void
    {
        $payload = [$config->Leader_Column => $leaderCode, $config->Member_Column => $memberCode];
        if ($config->Active_Status_Column) {
            $payload[$config->Active_Status_Column] = $config->Active_Status_Value;
            $inactive = DB::table($config->Source_Table)
                ->where($config->Leader_Column, $leaderCode)
                ->where($config->Member_Column, $memberCode)
                ->first();
            if ($inactive) {
                $query = DB::table($config->Source_Table);
                if ($config->Primary_Key_Column && isset($inactive->{$config->Primary_Key_Column})) {
                    $query->where($config->Primary_Key_Column, $inactive->{$config->Primary_Key_Column});
                } else {
                    $query->where($config->Leader_Column, $leaderCode)->where($config->Member_Column, $memberCode);
                }
                $query->update($payload);

                return;
            }
        }
        $columns = Schema::getColumnListing($config->Source_Table);
        if (in_array('create_at', $columns, true)) {
            $payload['create_at'] = now();
        }
        if (in_array('update_at', $columns, true)) {
            $payload['update_at'] = now();
        }
        DB::table($config->Source_Table)->insert($payload);
    }

    private function upsertMembership(object $config, string $leaderCode, string $memberCode, ?object $existing): void
    {
        if ($existing) {
            $query = DB::table($config->Source_Table);
            if ($config->Primary_Key_Column && isset($existing->source_id)) {
                $query->where($config->Primary_Key_Column, $existing->source_id);
            } else {
                $query->where($config->Member_Column, $memberCode)->where($config->Leader_Column, $existing->leader_code);
            }
            $query->update([$config->Leader_Column => $leaderCode]);

            return;
        }

        $this->insertMembership($config, $leaderCode, $memberCode);
    }

    private function removeMembership(object $config, string $memberCode, ?string $leaderCode = null): void
    {
        $query = DB::table($config->Source_Table)->where($config->Member_Column, $memberCode);
        if ($leaderCode !== null) {
            $query->where($config->Leader_Column, $leaderCode);
        }
        if ($config->Active_Status_Column) {
            $query->update([$config->Active_Status_Column => 'T']);
        } else {
            $query->delete();
        }
    }

    private function employeesByCode(Collection $codes): Collection
    {
        if ($codes->isEmpty()) {
            return collect();
        }

        return DB::table('Karyawan')->whereIn('Kode_Karyawan', $codes->all())->get()->keyBy('Kode_Karyawan');
    }

    /**
     * Leader yang anggotanya DILEPAS (leader asal / tim lain) hanya diperiksa
     * scope-nya bila masih aktif. Pemeriksaan scope hanya meloloskan karyawan
     * aktif, sehingga leader yang sudah keluar selalu terbaca "di luar scope"
     * dan anggota aktif tak pernah bisa dilepas dari timnya. Yang diubah hanya
     * baris milik anggota, dan anggota tetap wajib masuk scope.
     */
    private function leaderLepasYangAktif(array $codes): array
    {
        $codes = collect($codes)->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return [];
        }

        return DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $codes->all())
            ->where('Aktif', 'Y')
            ->pluck('Kode_Karyawan')
            ->map(fn ($code) => trim((string) $code))
            ->unique()
            ->values()
            ->all();
    }

    private function validateEmployeeCodes(array $codes): void
    {
        $unique = array_values(array_unique($codes));
        $count = DB::table('Karyawan')->whereIn('Kode_Karyawan', $unique)->count();
        abort_unless($count === count($unique), 422, 'Terdapat leader atau anggota yang tidak valid.');
    }

    private function buildOperationPreview(object $config, string $action, array $memberCodes, ?string $leaderCode, string $mode, ?string $sourceLeaderCode): array
    {
        $codes = collect($memberCodes)->filter()->unique()->values();
        $employees = $this->employeesByCode($codes);
        $leaderCodes = collect([$leaderCode, $sourceLeaderCode])->filter();
        $memberRows = $this->activeMembershipRows($config, $codes)->groupBy('member_code');
        $items = $codes->map(function (string $memberCode) use ($action, $mode, $leaderCode, $sourceLeaderCode, $employees, $memberRows, &$leaderCodes) {
            $currentLeaders = collect($memberRows->get($memberCode, collect()))->pluck('leader_code')->filter()->unique()->values();
            $current = $sourceLeaderCode && $currentLeaders->contains($sourceLeaderCode) ? $sourceLeaderCode : $currentLeaders->first();
            $leaderCodes->push(...$currentLeaders);
            if ($action === 'remove') {
                $impact = $sourceLeaderCode && ! $currentLeaders->contains($sourceLeaderCode) ? 'skip' : ($current ? 'remove' : 'skip');
            } elseif ($memberCode === $leaderCode) {
                $impact = 'conflict';
            } elseif ($currentLeaders->contains($leaderCode)) {
                $impact = 'skip';
            } elseif ($mode === 'add') {
                $impact = 'add';
            } elseif ($sourceLeaderCode && ! $currentLeaders->contains($sourceLeaderCode)) {
                $impact = 'conflict';
            } else {
                $impact = $current ? 'move' : 'add';
            }
            $retainedLeaders = $currentLeaders
                ->reject(fn ($code) => match ($impact) {
                    'remove', 'move' => $code === $current,
                    'skip' => $code === $leaderCode,
                    default => false,
                })
                ->values();
            $skipReason = null;
            if ($impact === 'skip') {
                $skipReason = $action === 'remove'
                    ? 'Relasi dengan leader asal sudah tidak ditemukan.'
                    : 'Anggota sudah berada pada leader tujuan.';
            } elseif ($impact === 'conflict') {
                $skipReason = $memberCode === $leaderCode
                    ? 'Leader tidak dapat menjadi anggota dirinya sendiri.'
                    : 'Relasi dengan leader asal sudah berubah atau tidak ditemukan.';
            }

            return [
                'member_code' => $memberCode,
                'member_name' => $employees->get($memberCode)->Nama ?? $memberCode,
                'current_leader_code' => $current,
                'current_leader_codes' => $currentLeaders,
                'target_leader_code' => $action === 'assign' ? $leaderCode : null,
                'source_leader_code' => $sourceLeaderCode,
                'impact' => $impact,
                'has_other_teams' => $currentLeaders->isNotEmpty(),
                'retained_leader_codes' => $retainedLeaders,
                'skip_reason' => $skipReason,
            ];
        })->values();
        $leaderNames = $this->employeesByCode($leaderCodes->filter()->unique())
            ->mapWithKeys(fn ($employee) => [$employee->Kode_Karyawan => $employee->Nama]);
        $items = $items->map(function (array $item) use ($leaderNames) {
            $item['current_leader_name'] = $item['current_leader_code'] ? ($leaderNames->get($item['current_leader_code']) ?? $item['current_leader_code']) : null;
            $item['current_leader_names'] = collect($item['current_leader_codes'])->map(fn ($code) => $leaderNames->get($code) ?? $code)->values();
            $item['target_leader_name'] = $item['target_leader_code'] ? ($leaderNames->get($item['target_leader_code']) ?? $item['target_leader_code']) : null;
            $item['source_leader_name'] = $item['source_leader_code'] ? ($leaderNames->get($item['source_leader_code']) ?? $item['source_leader_code']) : null;
            $item['retained_leader_names'] = collect($item['retained_leader_codes'])->map(fn ($code) => $leaderNames->get($code) ?? $code)->values();
            $item['current_leaders'] = collect($item['current_leader_codes'])->map(fn ($code) => [
                'code' => $code,
                'name' => $leaderNames->get($code) ?? $code,
            ])->values();

            return $item;
        });

        return [
            'action' => $action,
            'mode' => $mode,
            'counts' => [
                'add' => $items->where('impact', 'add')->count(),
                'move' => $items->where('impact', 'move')->count(),
                'remove' => $items->where('impact', 'remove')->count(),
                'skip' => $items->where('impact', 'skip')->count(),
                'conflict' => $items->where('impact', 'conflict')->count(),
            ],
            'expected_assignments' => $items->mapWithKeys(fn (array $item) => [$item['member_code'] => $item['current_leader_code']])->all(),
            'items' => $items,
        ];
    }

    private function conflictPayload(string $memberCode, ?string $expected, ?string $current): array
    {
        $employee = DB::table('Karyawan')->where('Kode_Karyawan', $memberCode)->first();

        return [
            'member_code' => $memberCode,
            'member_name' => $employee->Nama ?? $memberCode,
            'expected_leader' => $expected,
            'current_leader' => $current,
        ];
    }

    private function configPayload(object $config): array
    {
        return [
            'id' => (int) $config->Id,
            'code' => $config->Kode_Config,
            'name' => $config->Nama_Config,
            'source' => $config->Source_Table,
            'leader_column' => $config->Leader_Column,
            'member_column' => $config->Member_Column,
        ];
    }

    private function recentActivity(User $user, int $grupId): Collection
    {
        if (! SkemaTersedia::tabel('N_HRIS_Transaksi_Membership')) {
            return collect();
        }

        $query = DB::table('N_HRIS_Transaksi_Membership as t')
            ->leftJoin('Karyawan as actor', 'actor.Kode_Karyawan', '=', 't.Created_By')
            ->leftJoin('Karyawan as member', 'member.Kode_Karyawan', '=', 't.Kode_Karyawan_Member')
            ->where('t.Id_Grup_Fitur', $grupId)
            ->orderByDesc('t.Created_At')
            ->limit(6);
        $this->targetScopeService->applyToQuery($query, $user, $grupId, 'member');

        return $query->get([
            't.No_Transaksi as transaction_no',
            't.Jenis_Transaksi as action',
            't.Created_At as created_at',
            't.Created_By as actor_code',
            'actor.Nama as actor_name',
        ]);
    }

    private function assertCanView(User $user, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        abort_unless($capabilities['membership']['canView'], 403, 'Anda tidak memiliki akses keanggotaan.');
        $this->matrixService->assertCanManageGrup($user, $grupId);
    }

    private function assertCanAssign(User $user, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        abort_unless($capabilities['membership']['canAssign'], 403, 'Anda tidak memiliki hak mengubah keanggotaan.');
        $this->matrixService->assertCanManageGrup($user, $grupId);
    }

    private function writeAudit(User $user, string $transactionNo, object $config, int $grupId, string $action, string $member, ?string $before, ?string $after, ?string $note): void
    {
        if (! SkemaTersedia::tabel('N_HRIS_Transaksi_Membership')) {
            return;
        }
        $userId = $user->getKey();
        $actor = DB::table('Karyawan')->where('UserID_Web', $userId)->value('Kode_Karyawan') ?? ('UID:'.$userId);
        DB::table('N_HRIS_Transaksi_Membership')->insert([
            'No_Transaksi' => $transactionNo,
            'Id_Membership_Config' => $config->Id,
            'Id_Grup_Fitur' => $grupId,
            'Jenis_Transaksi' => $action,
            'Kode_Karyawan_Member' => $member,
            'Leader_Sebelum' => $before,
            'Leader_Sesudah' => $after,
            'Catatan' => $note,
            'Created_At' => now(),
            'Created_By' => $actor,
        ]);
    }
}
