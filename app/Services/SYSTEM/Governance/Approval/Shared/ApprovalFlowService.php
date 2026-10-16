<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
/**
 * Main service for creating approval flows
 *
 * Provides 4 methods for different approval flow patterns:
 * 1. createFlowForLegacy() - Uses HRIS_Approval_Flow hierarchy
 * 2. createFlowWithHC() - Legacy + HC Ops at the end
 * 3. createFlowFromConfig() - Uses feature-specific config table
 * 4. createFlowFromJenisApproval() - Direct jenis approval IDs
 *
 * All methods insert records into HRIS_Approval_Request with proper re-indexing.
 */
class ApprovalFlowService
{
    protected ApproverResolverService $resolver;

    public function __construct(ApproverResolverService $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Method 1: Create flow from legacy HRIS_Approval_Flow
     *
     * Use case: Shift, and other features that use existing supervisor hierarchy.
     *
     * @param  string  $noTransaksi  Transaction number
     * @param  string  $kodeKaryawanRequester  Requester's kode karyawan
     */
    public function createFlowForLegacy(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        int $idJenisApproval = 2,
    ): ApprovalFlowResult {
        return DB::transaction(function () use ($noTransaksi, $kodeKaryawanRequester, $idJenisApproval) {
            $approvers = DB::table('HRIS_Approval_Flow')
                ->where('Kode_Karyawan_Requester', $kodeKaryawanRequester)
                ->whereNull('Status')
                ->orderBy('order_flow', 'asc')
                ->get();

            if ($approvers->isEmpty()) {
                return ApprovalFlowResult::failed('Approver tidak ditemukan untuk karyawan ini.');
            }

            return $this->insertApprovalRequests(
                $noTransaksi,
                $kodeKaryawanRequester,
                $approvers->map(
                    fn ($a) => (object) [
                        'type' => 'SERIAL',
                        'id_jenis_approval' => $idJenisApproval,
                        'kode_karyawan' => $a->Kode_Karyawan,
                        'flow_id' => $a->id,
                        'group_id' => null,
                    ],
                ),
            );
        });
    }

    public function createFlowForLegacySingleAppro(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        int $idJenisApproval = 3,
    ): ApprovalFlowResult {
        return DB::transaction(function () use ($noTransaksi, $kodeKaryawanRequester, $idJenisApproval) {
            $approvers = DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('Kode_Karyawan_Requester', $kodeKaryawanRequester)
                ->whereNull('Status')
                ->orderBy('order_flow', 'asc')
                ->get();

            if ($approvers->isEmpty()) {
                return ApprovalFlowResult::failed('Approver tidak ditemukan untuk karyawan ini.');
            }

            return $this->insertApprovalRequests(
                $noTransaksi,
                $kodeKaryawanRequester,
                $approvers->map(
                    fn ($a) => (object) [
                        'type' => 'SERIAL',
                        'id_jenis_approval' => $idJenisApproval,
                        'kode_karyawan' => $a->Kode_Karyawan,
                        'flow_id' => $a->id,
                        'group_id' => null,
                    ],
                ),
            );
        });
    }

    /**
     * Method 2: Legacy flow + HC Ops at the end
     *
     * Use case: Features that require final approval from HC team.
     *
     * @param  int  $hcGroupId  HC Ops group ID (default: 100)
     * @param  int  $idJenisApprovalAtasan  Jenis approval for supervisors (default: 8)
     * @param  int  $idJenisApprovalHC  Jenis approval for HC (default: 1)
     */
    public function createFlowWithHC(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        int $hcGroupId = 100,
        int $idJenisApprovalAtasan = 8,
        int $idJenisApprovalHC = 1,
    ): ApprovalFlowResult {
        $approvers = DB::table('HRIS_Approval_Flow')
            ->where('Kode_Karyawan_Requester', $kodeKaryawanRequester)
            ->whereNull('Status')
            ->orderBy('order_flow', 'asc')
            ->get();

        if ($approvers->isEmpty()) {
            return ApprovalFlowResult::failed('Approver tidak ditemukan.');
        }

        // Build list: legacy approvers + HC at end
        $allApprovers = $approvers->map(
            fn ($a) => (object) [
                'type' => 'SERIAL',
                'id_jenis_approval' => $idJenisApprovalAtasan,
                'kode_karyawan' => $a->Kode_Karyawan,
                'flow_id' => $a->id,
                'group_id' => null,
            ],
        );

        // Add HC Ops as final step
        $allApprovers->push(
            (object) [
                'type' => 'GROUP',
                'id_jenis_approval' => $idJenisApprovalHC,
                'kode_karyawan' => null,
                'flow_id' => null,
                'group_id' => $hcGroupId,
            ],
        );

        return $this->insertApprovalRequests($noTransaksi, $kodeKaryawanRequester, $allApprovers);
    }

    /**
     * Method 3: Create flow from config table
     *
     * Use case: Cuti (N_HRIS_Cuti_Approval), and features with complex config.
     *
     * @param  string  $configTable  Config table name (e.g., 'N_HRIS_Cuti_Approval')
     * @param  string  $configIdColumn  Config table ID column (e.g., 'Id_Jenis_Cuti')
     * @param  int  $configIdValue  Config ID value to filter
     */
    public function createFlowFromConfig(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        string $configTable,
        string $configIdColumn,
        int $configIdValue,
    ): ApprovalFlowResult {
        $hasOrderApproverLimit = SkemaTersedia::kolom($configTable, 'Order_Approver_Limit');

        // Get config from feature-specific table
        $configSelects = ['ja.*', 'cfg.Order_Flow'];
        if ($hasOrderApproverLimit) {
            $configSelects[] = 'cfg.Order_Approver_Limit';
        }

        $configs = DB::table($configTable.' as cfg')
            ->join('N_HRIS_Jenis_Approval as ja', 'cfg.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where("cfg.{$configIdColumn}", $configIdValue)
            ->where('cfg.Flag_Aktif', 'Y')
            ->where('ja.Flag_Aktif', 'Y')
            ->orderBy('cfg.Order_Flow', 'asc')
            ->select($configSelects)
            ->get();

        if ($configs->isEmpty()) {
            return ApprovalFlowResult::failed('Konfigurasi approval tidak ditemukan.');
        }

        // Resolve all approvers
        $allApprovers = collect();
        foreach ($configs as $config) {
            $orderApproverLimit = $hasOrderApproverLimit ? ($config->Order_Approver_Limit ?? null) : null;
            $resolved = $this->resolver->resolve(
                $config->Id_Jenis_Approval,
                $config->Source_Type,
                $config->Source_Reference,
                $kodeKaryawanRequester,
                $orderApproverLimit,
            );

            if ($resolved->isEmpty()) {
                return ApprovalFlowResult::failed(
                    'Resolver gagal menemukan approver untuk step '.
                        ($config->Order_Flow ?? '-').
                        ' ('.
                        ($config->Nama_Approval ?? 'Approval').
                        ') dengan Source_Type='.
                        ($config->Source_Type ?? '-').
                        ' dan Source_Reference='.
                        ($config->Source_Reference ?? '-').
                        '. Pastikan konfigurasi approval aktif dan source reference valid.',
                );
            }

            if ($orderApproverLimit !== null && (int) $orderApproverLimit > 0 && $resolved->count() < (int) $orderApproverLimit) {
                return ApprovalFlowResult::failed(
                    'Approver untuk step '.
                        ($config->Order_Flow ?? '-').
                        ' ('.
                        ($config->Nama_Approval ?? 'Approval').
                        ') kurang. Ditemukan '.
                        $resolved->count().
                        ' dari '.
                        (int) $orderApproverLimit.
                        ' level yang diharapkan.',
                );
            }

            $allApprovers = $allApprovers->merge($resolved);
        }

        if ($allApprovers->isEmpty()) {
            return ApprovalFlowResult::failed('Tidak ada approver ditemukan dari konfigurasi.');
        }

        return $this->insertApprovalRequests($noTransaksi, $kodeKaryawanRequester, $allApprovers);
    }

    /**
     * Method 4: Create flow from direct jenis approval IDs
     *
     * Use case: Simple/custom flows with specific jenis approval sequence.
     *
     * @param  array  $jenisApprovalIds  Array of jenis approval IDs in order
     */
    public function createFlowFromJenisApproval(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        array $jenisApprovalIds,
    ): ApprovalFlowResult {
        $jenisApprovals = DB::table('N_HRIS_Jenis_Approval')
            ->whereIn('Id_Jenis_Approval', $jenisApprovalIds)
            ->where('Flag_Aktif', 'Y')
            ->get()
            ->keyBy('Id_Jenis_Approval');

        $allApprovers = collect();
        foreach ($jenisApprovalIds as $id) {
            if (! isset($jenisApprovals[$id])) {
                continue;
            }

            $ja = $jenisApprovals[$id];
            $resolved = $this->resolver->resolve(
                $ja->Id_Jenis_Approval,
                $ja->Source_Type,
                $ja->Source_Reference,
                $kodeKaryawanRequester,
            );
            $allApprovers = $allApprovers->merge($resolved);
        }

        if ($allApprovers->isEmpty()) {
            return ApprovalFlowResult::failed('Tidak ada approver ditemukan.');
        }

        return $this->insertApprovalRequests($noTransaksi, $kodeKaryawanRequester, $allApprovers);
    }

    /**
     * Core: Insert approval requests with re-indexing
     *
     * Inserts records into HRIS_Approval_Request with proper Request_Order_Flow.
     */
    protected function insertApprovalRequests(
        string $noTransaksi,
        string $kodeRequester,
        Collection $approvers,
        ?int $idApprovalConfig = null,
        ?int $approval_limit = null,
    ): ApprovalFlowResult {
        return DB::transaction(function () use (
            $noTransaksi,
            $kodeRequester,
            $approvers,
            $idApprovalConfig,
            $approval_limit,
        ) {
            $insertedIds = collect();
            $order = 1;
            $firstApprover = null;
            $approvalRequestColumns = collect(Schema::getColumnListing('HRIS_Approval_Request'))
                ->map(fn ($column) => strtolower($column))
                ->flip();

            foreach ($approvers as $approver) {
                if ($order === 1) {
                    $firstApprover = $approver;
                }

                $data = [
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan' => $kodeRequester,
                    'Id_Jenis_Approval' => $approver->id_jenis_approval,
                    'Request_Order_Flow' => $order,
                    'created_at' => now(),
                    'update_at' => now(),
                ];

                if ($approvalRequestColumns->has('id_approval_config')) {
                    $data['Id_Approval_Config'] = $idApprovalConfig;
                }

                if ($approvalRequestColumns->has('stop_if_rejected')) {
                    $data['Stop_If_Rejected'] = $approver->stop_if_rejected ?? 'Y';
                }

                if ($approvalRequestColumns->has('is_mandatory')) {
                    $data['Is_Mandatory'] = $approver->is_mandatory ?? 'Y';
                }

                if ($approver->type === 'GROUP') {
                    $data['Flag_Group'] = 'Y';
                    $data['Group_Reference_Id'] = $approver->group_id;
                    $data['Approver_Karyawan_Kode'] = null;
                    $data['Flow_Id'] = null;
                } else {
                    $data['Flag_Group'] = 'T';
                    $data['Group_Reference_Id'] = null;
                    $data['Approver_Karyawan_Kode'] = $approver->kode_karyawan;
                    $data['Flow_Id'] = $approver->flow_id;
                }

                if ($approval_limit && $approval_limit > 0 && $approvalRequestColumns->has('expired_at')) {
                    $data['Expired_At'] = now()->addDays($approval_limit)->endOfDay();
                }

                $id = DB::table('HRIS_Approval_Request')->insertGetId($data);
                $insertedIds->push($id);
                $order++;
            }

            return ApprovalFlowResult::success($insertedIds, $firstApprover);
        });
    }

    public function createFlowFromApprovalConfig(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        string $kodeConfig,
        ?int $approval_limit = null,
    ): ApprovalFlowResult {
        return DB::transaction(function () use ($noTransaksi, $kodeKaryawanRequester, $kodeConfig, $approval_limit) {
            // 1️⃣ Ambil header config
            $config = DB::table('N_HRIS_Approval_Config')
                ->where('Kode_Config', $kodeConfig)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $config) {
                return ApprovalFlowResult::failed('Approval config tidak ditemukan untuk kode: '.$kodeConfig);
            }

            // 2️⃣ Ambil detail config
            $details = DB::table('N_HRIS_Approval_Config_Detail as d')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
                ->where('d.Id_Approval_Config', $config->Id)
                ->where('d.Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('d.Id_Jenis_Approval')->orWhere('ja.Flag_Aktif', 'Y');
                })
                ->orderBy('d.Approval_Order', 'asc')
                ->select('d.*', 'ja.Source_Type', 'ja.Source_Reference')
                ->get();

            if ($details->isEmpty()) {
                return ApprovalFlowResult::failed('Detail approval config tidak ditemukan untuk kode: '.$kodeConfig);
            }

            $allApprovers = collect();

            // 3️⃣ Resolve tiap step
            foreach ($details as $detail) {
                // Path A: Custom Approver (hardcoded approver code)
                if (! empty($detail->Custom_Approver_Kode)) {
                    $approver = (object) [
                        'type' => 'SERIAL',
                        'id_jenis_approval' => null,
                        'kode_karyawan' => $detail->Custom_Approver_Kode,
                        'flow_id' => null,
                        'group_id' => null,
                        'stop_if_rejected' => $detail->Stop_If_Rejected ?? 'Y',
                        'is_mandatory' => $detail->Is_Mandatory ?? 'Y',
                    ];
                    $allApprovers->push($approver);

                    continue;
                }

                // Path B: Jenis Approval Resolver (dynamic approver resolution)
                if (empty($detail->Id_Jenis_Approval)) {
                    continue;
                }

                $resolved = collect(
                    $this->resolver->resolve(
                        $detail->Id_Jenis_Approval,
                        $detail->Source_Type,
                        $detail->Source_Reference,
                        $kodeKaryawanRequester,
                        $detail->Order_Approver_Limit,
                    ),
                );

                // dd($resolved);
                // NO APPROVERS RESOLVED - This is an error condition
                if ($resolved->isEmpty()) {
                    return ApprovalFlowResult::failed(
                        'Resolver gagal menemukan approver untuk Jenis Approval ID '.
                            $detail->Id_Jenis_Approval.
                            ' dengan Source_Type='.
                            $detail->Source_Type.
                            ' dan Source_Reference='.
                            $detail->Source_Reference.
                            ' (Order '.
                            $detail->Approval_Order.
                            ')',
                    );
                }

                // VALIDATE: Check if found approvers match the limit
                if (! empty($detail->Order_Approver_Limit)) {
                    $expectedCount = (int) $detail->Order_Approver_Limit;
                    $foundCount = $resolved->count();

                    if ($foundCount < $expectedCount) {
                        $missingCount = $expectedCount - $foundCount;

                        return ApprovalFlowResult::failed(
                            'Approver untuk step '.
                                $detail->Approval_Order.
                                ' kurang '.
                                $missingCount.
                                ' level (ditemukan '.
                                $foundCount.
                                ' dari '.
                                $expectedCount.
                                ' yang diharapkan)',
                        );
                    }
                }

                // Add metadata dari config detail ke setiap resolved approver
                foreach ($resolved as $approver) {
                    $approver->stop_if_rejected = $detail->Stop_If_Rejected ?? 'Y';
                    $approver->is_mandatory = $detail->Is_Mandatory ?? 'Y';

                    $allApprovers->push($approver);
                }
            }

            // Final validation: must have at least 1 approver
            if ($allApprovers->isEmpty()) {
                return ApprovalFlowResult::failed('Tidak ada approver ditemukan untuk kode: '.$kodeConfig);
            }

            // dd($noTransaksi, $kodeKaryawanRequester, $kodeConfig, $config, $details, $allApprovers);
            // 4️⃣ Insert dengan snapshot + Id_Config
            return $this->insertApprovalRequests(
                $noTransaksi,
                $kodeKaryawanRequester,
                $allApprovers,
                $config->Id,
                $approval_limit,
            );
        });
    }

    /**
     * KPI-specific flow builder.
     *
     * Wrapper tipis di atas createFlowFromApprovalConfig() khusus domain KPI.
     * Memberi titik integrasi tunggal untuk controller KPI (parameter & performance),
     * sekaligus menjadi pilihan ke-2 (jalur "gaya KPI") di samping flow HCIS generik.
     *
     * Catatan tim: untuk pengajuan tim (leader mengisikan atas nama anggota),
     * $kodeKaryawanRequester HARUS kode karyawan ANGGOTA yang dinilai — bukan leader —
     * agar approver yang ter-resolve adalah approver milik anggota tersebut.
     *
     * @param  string  $kodeKaryawanRequester  Kode karyawan pemilik flow (anggota, bukan leader)
     * @param  string  $kodeConfig  'KPI_PARAM_STD' | 'KPI_PERF_STD'
     * @param  int|null  $approval_limit  Expired_At dalam N hari (opsional)
     */
    public function createFlowForKpi(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        string $kodeConfig,
        ?int $approval_limit = null,
    ): ApprovalFlowResult {
        return $this->createFlowFromApprovalConfig(
            $noTransaksi,
            $kodeKaryawanRequester,
            $kodeConfig,
            $approval_limit,
        );
    }

    /**
     * getApproverFlow() untuk BANYAK requester sekaligus.
     *
     * Config dan detail-nya tidak bergantung pada requester, jadi keduanya
     * dibaca SEKALI di sini alih-alih sekali per orang. Resolusi approver
     * diteruskan ke resolveBatch(), yang menyatukan pembacaan tabel flow
     * menjadi satu query whereIn.
     *
     * Jalur tunggal getApproverFlow() sengaja dibiarkan apa adanya — ini
     * tambahan, bukan pengganti, supaya modul lain tidak ikut terpengaruh.
     *
     * Beda perilaku yang disengaja: getApproverFlow() melempar exception bila
     * config atau detailnya tidak ada. Di sini exception itu tetap dilempar
     * (salah konfigurasi memang harus berisik), tapi requester yang sekadar
     * tidak punya approver akan mendapat collection kosong, persis seperti
     * jalur tunggal.
     *
     * @param  array<int, string>  $kodeRequesters
     * @return array<string, \Illuminate\Support\Collection>
     */
    public function getApproverFlowBatch(array $kodeRequesters, $kodeConfig): array
    {
        $kodeRequesters = array_values(array_unique(array_filter($kodeRequesters)));

        if (empty($kodeRequesters)) {
            return [];
        }

        $config = DB::table('N_HRIS_Approval_Config')
            ->where('Kode_Config', $kodeConfig)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $config) {
            throw new \Exception('Approval config tidak ditemukan untuk kode: '.$kodeConfig);
        }

        $details = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where('d.Id_Approval_Config', $config->Id)
            ->where('d.Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('d.Id_Jenis_Approval')->orWhere('ja.Flag_Aktif', 'Y');
            })
            ->orderBy('d.Approval_Order', 'asc')
            ->select('d.*', 'ja.Source_Type', 'ja.Source_Reference')
            ->get();

        if ($details->isEmpty()) {
            throw new \Exception('Detail approval config tidak ditemukan untuk kode: '.$kodeConfig);
        }

        $hasil = [];
        foreach ($kodeRequesters as $kode) {
            $hasil[$kode] = collect();
        }

        // Urutan step dipertahankan: tiap detail digabungkan ke hasil setiap
        // requester dengan urutan yang sama seperti jalur tunggal.
        foreach ($details as $detail) {
            if (! empty($detail->Custom_Approver_Kode)) {
                foreach ($kodeRequesters as $kode) {
                    $hasil[$kode] = $hasil[$kode]->push((object) [
                        'type' => 'SERIAL',
                        'id_jenis_approval' => null,
                        'kode_karyawan' => $detail->Custom_Approver_Kode,
                        'flow_id' => null,
                        'group_id' => null,
                        'stop_if_rejected' => $detail->Stop_If_Rejected ?? 'Y',
                        'is_mandatory' => $detail->Is_Mandatory ?? 'Y',
                    ]);
                }

                continue;
            }

            if (empty($detail->Id_Jenis_Approval)) {
                continue;
            }

            $perRequester = $this->resolver->resolveBatch(
                $detail->Id_Jenis_Approval,
                $detail->Source_Type,
                $detail->Source_Reference,
                $kodeRequesters,
                $detail->Order_Approver_Limit ?? null,
            );

            foreach ($kodeRequesters as $kode) {
                $hasil[$kode] = $hasil[$kode]->merge($perRequester[$kode] ?? collect());
            }
        }

        return $hasil;
    }

    public function getApproverFlow($kodeKaryawanRequester, $kodeConfig, bool $withMeta = false)
    {
        $config = DB::table('N_HRIS_Approval_Config')
            ->where('Kode_Config', $kodeConfig)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $config) {
            throw new \Exception('Approval config tidak ditemukan untuk kode: '.$kodeConfig);
        }

        $skipIfRequesterLevelId = $config->Skip_If_Requester_Level_Id ?? null;
        $requesterLevelId = null;
        $isSkippedByRequesterLevel = false;
        if ($skipIfRequesterLevelId !== null && $skipIfRequesterLevelId !== '') {
            $requester = $this->getKaryawan($kodeKaryawanRequester);
            $requesterLevelId = (int) ($requester->ID_Level ?? 0);
            $isSkippedByRequesterLevel = $requesterLevelId >= (int) $skipIfRequesterLevelId;
        }

        $details = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where('d.Id_Approval_Config', $config->Id)
            ->where('d.Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('d.Id_Jenis_Approval')->orWhere('ja.Flag_Aktif', 'Y');
            })
            ->orderBy('d.Approval_Order', 'asc')
            ->select('d.*', 'ja.Source_Type', 'ja.Source_Reference')
            ->get();

        if ($details->isEmpty()) {
            throw new \Exception('Detail approval config tidak ditemukan untuk kode: '.$kodeConfig);
        }

        $allApprovers = collect();

        // 3️⃣ Resolve tiap step
        foreach ($details as $detail) {
            // Path A: Custom Approver (hardcoded approver code)
            if (! empty($detail->Custom_Approver_Kode)) {
                $approver = (object) [
                    'type' => 'SERIAL',
                    'id_jenis_approval' => null,
                    'kode_karyawan' => $detail->Custom_Approver_Kode,
                    'flow_id' => null,
                    'group_id' => null,
                    'stop_if_rejected' => $detail->Stop_If_Rejected ?? 'Y',
                    'is_mandatory' => $detail->Is_Mandatory ?? 'Y',
                ];
                $allApprovers->push($approver);

                continue;
            }

            // Path B: Jenis Approval Resolver (dynamic approver resolution)
            if (empty($detail->Id_Jenis_Approval)) {
                continue;
            }

            $resolved = collect(
                $this->resolver->resolve(
                    $detail->Id_Jenis_Approval,
                    $detail->Source_Type,
                    $detail->Source_Reference,
                    $kodeKaryawanRequester,
                    $detail->Order_Approver_Limit,
                ),
            );
            $allApprovers = $allApprovers->merge($resolved);
        }

        // Final validation: must have at least 1 approver
        if ($allApprovers->isEmpty()) {
            if ($withMeta) {
                return [
                    'approvers' => collect(),
                    'policy' => [
                        'kode_config' => $kodeConfig,
                        'skip_if_requester_level_id' => $skipIfRequesterLevelId !== null && $skipIfRequesterLevelId !== ''
                                ? (int) $skipIfRequesterLevelId
                                : null,
                        'requester_level_id' => $requesterLevelId,
                        'is_skipped_by_requester_level' => $isSkippedByRequesterLevel,
                    ],
                ];
            }

            return collect();

            // throw new \Exception('Tidak ada approver ditemukan untuk kode: ' . $kodeConfig);
        }

        if ($withMeta) {
            return [
                'approvers' => $allApprovers,
                'policy' => [
                    'kode_config' => $kodeConfig,
                    'skip_if_requester_level_id' => $skipIfRequesterLevelId !== null && $skipIfRequesterLevelId !== ''
                            ? (int) $skipIfRequesterLevelId
                            : null,
                    'requester_level_id' => $requesterLevelId,
                    'is_skipped_by_requester_level' => $isSkippedByRequesterLevel,
                ],
            ];
        }
        // dd($allApprovers);

        return $allApprovers;
    }

    protected function findRule(array $context)
    {
        $rule = DB::table('N_HRIS_Approval_Config')
            ->where('Kode_Config', $context['approval_type'])
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $rule) {
            throw new \Exception("Approval config {$context['approval_type']} belum diset");
        }

        return $rule;
    }

    protected function getKaryawan(string $kode)
    {
        $karyawan = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
            ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_Divisi_Sub_Divisi')
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'l.ID_Level',
                'l.nama_level',
                'l.Id_Jabatan',
                'l.nama_jabatan',
                'd.Id_Divisi',
                'd.nama_divisi',
                'k.Cabang_Untuk_Gaji as Lokasi',
            )
            ->where('Kode_Karyawan', $kode)
            ->first();

        if (! $karyawan) {
            throw new \Exception("Karyawan not found: {$kode}");
        }

        return $karyawan;
    }

    public function decide(array $context)
    {
        // 1️⃣ rule dari tanggal
        if (isset($context['classification']) && $context['classification']['needs_approval'] === false) {
            return [
                'action' => 'DIRECT',
                'reason' => 'Allowed by date policy',
                'notes' => 'Auto apply berdasarkan policy tanggal ('.$context['classification']['jenis_pengajuan'].')',
                'config_id' => null,
                'config_name' => null,
            ];
        }

        // 2️⃣ skip karena jabatan
        $config = $this->findRule($context);
        // dd($config);

        if ($config->Skip_If_Requester_Level_Id !== null) {
            $requester = $this->getKaryawan($context['requester_kode']);

            if ($requester->ID_Level >= $config->Skip_If_Requester_Level_Id) {
                return [
                    'action' => 'DIRECT',
                    'reason' => 'Skip by requester level',
                    'notes' => 'Auto approve karena level requester memenuhi rule skip',
                    'config_id' => $config->Id,
                    'config_name' => $config->Nama_Config,
                ];
            }
        }

        // 3️⃣ perlu approval
        return [
            'action' => 'APPROVAL',
            'reason' => 'Requires approval flow',
            'notes' => 'Transaksi mengikuti flow approval: '.$config->Nama_Config,
            'config_id' => $config->Id,
            'config_name' => $config->Nama_Config,
        ];
    }
}
