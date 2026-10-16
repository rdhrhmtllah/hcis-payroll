<?php

namespace App\Services\VISITOR\Workflow\Approval;

use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitorApprovalPolicyService
{
    public function __construct(
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    public function config(string $module): array
    {
        $defaults = [
            'enabled' => true,
            'feature_code' => strtoupper($module),
            'needs_approval' => true,
            'approver_limit' => 2,
            'approval_expire_limit' => 2,
            'allow_submit_without_flow' => false,
            'approval_label' => 'Approval Visitor',
        ];

        if (in_array($module, ['tamu', 'tamu_b2b'], true)) {
            $defaults['check_in_requires_approval'] = true;
        }

        if ($module === 'permit') {
            $defaults['checklist_required'] = true;
            // Satu jatah menutupi pengajuan yang masih diproses DAN permit yang
            // sudah hidup. 0 = tanpa batas. Baku 2, bukan 0, supaya penjaganya
            // menyala sendiri alih-alih menunggu satu env yang bisa terlupa.
            $defaults['max_pengajuan_aktif'] = 2;
        }

        $config = array_merge($defaults, config("services.visitor.{$module}", []));
        $config['approver_limit'] = (int) ($config['approver_limit'] ?? $config['approval_limit'] ?? 2);
        $config['approval_expire_limit'] = max((int) ($config['approval_expire_limit'] ?? 2), 0);

        return $config;
    }

    public function requesterKode(?object $user): ?string
    {
        return $user?->Kode_Karyawan
            ?? $user?->karyawan?->Kode_Karyawan
            ?? null;
    }

    public function pageProps(string $module, ?object $user): array
    {
        $config = $this->config($module);
        $kodeKaryawan = $this->requesterKode($user);
        $approvers = collect();
        $flowError = null;

        if ($kodeKaryawan && ! empty($config['feature_code']) && (bool) $config['needs_approval']) {
            try {
                $approvers = $this->resolveApprovers($kodeKaryawan, $config);
            } catch (\Throwable $e) {
                $flowError = $e->getMessage();
            }
        }

        return [
            'config' => $config,
            'approverFlows' => $approvers->values(),
            'approvalPolicy' => [
                'enabled' => (bool) $config['enabled'],
                'needs_approval' => (bool) $config['needs_approval'],
                'feature_code' => $config['feature_code'],
                'approval_label' => $config['approval_label'],
                'approver_limit' => (int) $config['approver_limit'],
                'approval_expire_limit' => (int) $config['approval_expire_limit'],
                'has_approver' => $approvers->isNotEmpty(),
                'allow_submit_without_flow' => (bool) $config['allow_submit_without_flow'],
                'flow_error' => $flowError,
            ],
        ];
    }

    public function resolveApprovers(string $kodeKaryawan, array $config): Collection
    {
        $approvers = collect($this->approvalFlowService->getApproverFlow($kodeKaryawan, $config['feature_code']));
        $limit = max((int) ($config['approver_limit'] ?? 0), 0);

        if ($limit > 0) {
            $approvers = $approvers->take($limit);
        }

        $kodeApprovers = $approvers
            ->pluck('kode_karyawan')
            ->filter()
            ->unique()
            ->values();

        $profiles = $kodeApprovers->isEmpty()
            ? collect()
            : DB::table('Karyawan as k')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
                ->whereIn('k.Kode_Karyawan', $kodeApprovers)
                ->select('k.Kode_Karyawan', 'k.Nama', 'l.nama_jabatan')
                ->get()
                ->keyBy('Kode_Karyawan');

        return $approvers
            ->map(function ($approver) use ($profiles) {
                $kode = $approver->kode_karyawan ?? null;
                $profile = $kode ? $profiles->get($kode) : null;
                $isRolePool = strtoupper((string) ($approver->source_type ?? '')) === 'ROLE';
                $approver->Nama = $profile->Nama
                    ?? ($isRolePool ? ($approver->source_reference ?? 'Role Approval') : ($kode ?? '-'));
                $approver->Jabatan = $profile->nama_jabatan
                    ?? $profile->Jabatan
                    ?? ($isRolePool ? 'Role Approval Pool' : '-');

                return $approver;
            })
            ->values();
    }

    public function createFlow(string $module, string $noTransaksi, string $kodeKaryawan): void
    {
        $config = $this->config($module);

        if (! (bool) $config['needs_approval']) {
            return;
        }

        $result = $this->approvalFlowService->createFlowFromApprovalConfig(
            $noTransaksi,
            $kodeKaryawan,
            $config['feature_code'],
            (int) $config['approval_expire_limit'],
        );

        if (! $result->success && ! (bool) $config['allow_submit_without_flow']) {
            throw new \Exception($result->error ?: 'Approval flow visitor tidak dapat dibuat.');
        }
    }

    public function approvalStatus(string $noTransaksi, bool $autoApprovedWhenNoFlow = false): string
    {
        $requests = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->select('Flag_Approval', 'Expired_At')
            ->get();

        if ($requests->isEmpty()) {
            return $autoApprovedWhenNoFlow ? 'APPROVED' : 'NO_FLOW';
        }

        if ($requests->contains(fn ($row) => $row->Flag_Approval === 'T')) {
            return 'REJECTED';
        }

        if ($requests->contains(fn ($row) => $row->Flag_Approval === null && $this->isExpired($row->Expired_At ?? null))) {
            return 'EXPIRED';
        }

        if ($requests->contains(fn ($row) => $row->Flag_Approval === null)) {
            return 'PENDING';
        }

        return 'APPROVED';
    }

    protected function isExpired(mixed $expiredAt): bool
    {
        if (! $expiredAt) {
            return false;
        }

        try {
            return Carbon::parse((string) $expiredAt)->lt(now());
        } catch (\Throwable) {
            return false;
        }
    }

    public function isApproved(string $noTransaksi, bool $autoApprovedWhenNoFlow = false): bool
    {
        return $this->approvalStatus($noTransaksi, $autoApprovedWhenNoFlow) === 'APPROVED';
    }
}
