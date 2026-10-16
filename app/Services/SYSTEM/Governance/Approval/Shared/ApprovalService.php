<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use App\Helpers\WhatsappLogHelper;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApprovalService
{
    protected $transactionService;

    protected $konversiLemburService;

    protected $approvalRequestService;

    protected $whatsappMessageBuilder;

    protected $whatsappLogHelper;

    // Inject services
    public function __construct(
        TransactionService $transactionService,
        KonversiLemburService $konversiLemburService,
        ApprovalRequestService $approvalRequestService,
        WhatsappMessageBuilder $whatsappMessageBuilder,
        WhatsappLogHelper $whatsappLogHelper,
    ) {
        $this->transactionService = $transactionService;
        $this->konversiLemburService = $konversiLemburService;
        $this->approvalRequestService = $approvalRequestService;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
        $this->whatsappLogHelper = $whatsappLogHelper;
    }

    // public function approve(string $noTransaksi, string $approverKode, ?string $catatan = null)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $req = DB::table('HRIS_Approval_Request')
    //             ->where('No_Transaksi', $noTransaksi)
    //             ->whereNull('Flag_Approval')
    //             ->where(function($q) use ($approverKode) {
    //                 $q->where('Approver_Karyawan_Kode', $approverKode)
    //                   ->orWhere('Flag_Group', 'Y');
    //                   ->orWhere('')
    //             })
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$req) {
    //             throw new \Exception("Data approval tidak ditemukan atau sudah diproses.");
    //         }

    //         DB::table('HRIS_Approval_Request')
    //             ->where('id', $req->id)
    //             ->update([
    //                 'Flag_Approval' => 'Y',
    //                 'Approved_By_Kode' => $approverKode,
    //                 'Catatan' => $catatan,
    //                 'Tanggal_Approval' => now(),
    //                 'update_at' => now()
    //             ]);

    //         $isNotFinal = DB::table('HRIS_Approval_Request')
    //             ->where('No_Transaksi', $noTransaksi)
    //             ->where('Request_Order_Flow', '>', $req->Request_Order_Flow)
    //             ->exists();

    //         if (!$isNotFinal) {
    //             $this->transactionService->handleFinalApprove($noTransaksi, $approverKode);
    //         }else{
    //             $this->transactionService->handleNextApprove($noTransaksi, $approverKode);
    //         }

    //         DB::commit();
    //         return true;

    //     } catch (\Exception $e) {
    //         $this->safeRollback();

    //         Log::error("Approval Error [$noTransaksi]: " . $e->getMessage(), [
    //             'exception' => $e
    //         ]);
    //         throw $e;
    //     }
    // }

    public function approve(string $noTransaksi, string $approverKode, ?string $catatan = null)
    {
        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // STEP 1: VALIDATE APPROVER ACCESS using ApprovalRequestService
            // ------------------------------------------------------------------
            $validation = $this->approvalRequestService->validateApproverAccess($noTransaksi, $approverKode);

            if (! $validation['valid']) {
                throw new \Exception($validation['reason']);
            }

            $currentApproval = $this->approvalRequestService->getCurrentApprovalRequest($noTransaksi);

            if (! $currentApproval) {
                throw new \Exception('Tidak ada approval yang pending untuk transaksi ini.');
            }

            // ------------------------------------------------------------------
            // STEP 2: LOCK & UPDATE approval request
            // ------------------------------------------------------------------
            $req = DB::table('HRIS_Approval_Request')
                ->where('id', $currentApproval->Id_Approval_Request)
                ->lockForUpdate()
                ->first();

            if (! $req) {
                throw new \Exception('Data approval request tidak ditemukan.');
            }

            if ($req->Flag_Approval !== null) {
                throw new \Exception('Transaksi ini baru saja diproses oleh orang lain.');
            }

            if ($this->isExpiredApproval($req->Expired_At ?? null)) {
                throw new \Exception('Approval sudah expired.');
            }

            DB::table('HRIS_Approval_Request')
                ->where('id', $req->id)
                ->update([
                    'Flag_Approval' => 'Y',
                    'Approved_By_Kode' => $approverKode,
                    'Catatan' => $catatan,
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);

            // ------------------------------------------------------------------
            // STEP 3: DETERMINE NEXT ACTION using Stop_If_Rejected & Is_Mandatory
            // ------------------------------------------------------------------
            $nextAction = $this->approvalRequestService->determineNextAction($noTransaksi, 'APPROVE', $currentApproval);

            if ($nextAction['action'] === 'COMPLETE') {
                // All mandatory approvers done, finalize transaction
                $this->finalizeApprovedTransaction($noTransaksi, $approverKode);
            } elseif ($nextAction['action'] === 'CONTINUE') {
                // Next mandatory approver exists
                if ($nextAction['nextApproval'] && $this->shouldSendGenericNextApproverNotification($noTransaksi)) {
                    // Send notification to next approver
                    $this->sendNextApproverNotification($noTransaksi, $nextAction['nextApproval']);
                }
                $nextApproverKode = $nextAction['nextApproval']->Current_Approver ?? $approverKode;
                $this->handleNextApproveHook($noTransaksi, $nextApproverKode);
                $this->notifyRolePoolIfCurrentCutiStep($noTransaksi);
            } elseif ($nextAction['action'] === 'AUDIT_PASS') {
                // Current is optional audit only (Is_Mandatory='T')
                // Check if there are mandatory approvers still pending
                if (! $this->approvalRequestService->canAutoCompleteWithoutChain($noTransaksi)) {
                    // There are more mandatory approvers
                    $nextMandatory = $this->approvalRequestService->getNextMandatoryApproval(
                        $noTransaksi,
                        $currentApproval->Request_Order_Flow,
                    );
                    if ($nextMandatory) {
                        // Send notification to next approver
                        if ($this->shouldSendGenericNextApproverNotification($noTransaksi)) {
                            $this->sendNextApproverNotification($noTransaksi, $nextMandatory);
                        }
                        $nextApproverKode = $nextMandatory->Current_Approver ?? $approverKode;
                        $this->handleNextApproveHook($noTransaksi, $nextApproverKode);
                        $this->notifyRolePoolIfCurrentCutiStep($noTransaksi);
                    }
                } else {
                    // No mandatory approvers left, can complete
                    $this->finalizeApprovedTransaction($noTransaksi, $approverKode);
                }
            }

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            $this->safeRollback();
            Log::error("Approval Error [$noTransaksi]: ".$e->getMessage());
            throw $e;
        }
    }

    public function reject(string $noTransaksi, string $rejectorKode, string $alasan, bool $forceRejectHalt = false)
    {
        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // STEP 1: VALIDATE APPROVER ACCESS
            // ------------------------------------------------------------------
            $validation = $this->approvalRequestService->validateApproverAccess($noTransaksi, $rejectorKode);

            if (! $validation['valid']) {
                throw new \Exception($validation['reason']);
            }

            $currentApproval = $this->approvalRequestService->getCurrentApprovalRequest($noTransaksi);

            if (! $currentApproval) {
                throw new \Exception('Tidak ada approval yang pending untuk transaksi ini.');
            }

            // ------------------------------------------------------------------
            // STEP 2: LOCK & UPDATE approval request with Flag_Approval = 'T'
            // ------------------------------------------------------------------
            $req = DB::table('HRIS_Approval_Request')
                ->where('id', $currentApproval->Id_Approval_Request)
                ->lockForUpdate()
                ->first();

            if (! $req) {
                throw new \Exception('Data approval request tidak ditemukan.');
            }

            if ($req->Flag_Approval !== null) {
                throw new \Exception('Transaksi ini baru saja diproses oleh orang lain.');
            }

            if ($this->isExpiredApproval($req->Expired_At ?? null)) {
                throw new \Exception('Approval sudah expired.');
            }

            DB::table('HRIS_Approval_Request')
                ->where('id', $req->id)
                ->update([
                    'Flag_Approval' => 'T', // Rejection flag
                    'Approved_By_Kode' => $rejectorKode,
                    'Catatan' => $alasan,
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);

            // ------------------------------------------------------------------
            // STEP 3: DETERMINE NEXT ACTION based on Stop_If_Rejected
            // ------------------------------------------------------------------
            $nextAction = $this->approvalRequestService->determineNextAction($noTransaksi, 'REJECT', $currentApproval);

            if ($forceRejectHalt) {
                // Forced terminal reject for specific endpoint/flow
                $this->finalizeRejectedTransaction($noTransaksi, $rejectorKode, $alasan);
            } elseif ($nextAction['action'] === 'REJECT_HALT') {
                // Stop_If_Rejected = 'Y' → HALT ENTIRE FLOW
                // Call transaction service to handle rejection
                $this->finalizeRejectedTransaction($noTransaksi, $rejectorKode, $alasan);
            } elseif ($nextAction['action'] === 'REJECT_CONTINUE') {
                // Stop_If_Rejected = 'T' → CAN CONTINUE TO NEXT APPROVER
                if ($nextAction['nextApproval']) {
                    // There's a next approver, notify them
                    if ($this->shouldSendGenericNextApproverNotification($noTransaksi)) {
                        $this->sendNextApproverNotification($noTransaksi, $nextAction['nextApproval']);
                    }
                    $nextApproverKode = $nextAction['nextApproval']->Current_Approver ?? $rejectorKode;
                    $this->handleNextApproveHook($noTransaksi, $nextApproverKode);
                    $this->notifyRolePoolIfCurrentCutiStep($noTransaksi);
                } else {
                    // No more approvers after this rejection
                    $this->finalizeRejectedTransaction($noTransaksi, $rejectorKode, $alasan);
                }
            }

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            $this->safeRollback();
            Log::error("Reject Error [$noTransaksi]: ".$e->getMessage());
            throw $e;
        }
    }

    protected function isKonversiLemburTransaction(string $noTransaksi): bool
    {
        return DB::table('N_HRIS_Transaksi_Konversi_Lembur')->where('No_Transaksi', $noTransaksi)->exists();
    }

    protected function isExpiredApproval(mixed $expiredAt): bool
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

    protected function finalizeApprovedTransaction(string $noTransaksi, string $approverKode): void
    {
        if ($this->isKonversiLemburTransaction($noTransaksi)) {
            $this->konversiLemburService->applyApprovedTransaction($noTransaksi, $approverKode);
            $this->sendFinalRequesterNotification($noTransaksi, true);

            return;
        }

        $this->transactionService->handleFinalApprove($noTransaksi, $approverKode);
    }

    protected function finalizeRejectedTransaction(string $noTransaksi, string $rejectorKode, string $alasan): void
    {
        // dd($noTransaksi, $rejectorKode, $alasan);
        if ($this->isKonversiLemburTransaction($noTransaksi)) {
            $this->konversiLemburService->rejectTransaction($noTransaksi, $rejectorKode, $alasan);
            $this->sendFinalRequesterNotification($noTransaksi, false, $rejectorKode);

            return;
        }

        $this->transactionService->handleReject($noTransaksi, $rejectorKode, $alasan);
    }

    protected function handleNextApproveHook(string $noTransaksi, string $nextApproverKode): void
    {
        if ($this->isKonversiLemburTransaction($noTransaksi)) {
            return;
        }

        if (method_exists($this->transactionService, 'handleNextApprove')) {
            $this->transactionService->handleNextApprove($noTransaksi, $nextApproverKode);
        }
    }

    protected function notifyRolePoolIfCurrentCutiStep(string $noTransaksi): void
    {
        $prefix = strtoupper((string) $noTransaksi);

        try {
            // Cuti (CT*) -> CutiNotificationService.
            if (str_starts_with($prefix, 'CT')) {
                app(\App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService::class)
                    ->notifyRolePoolForCurrentStep($noTransaksi);

                return;
            }

            // KPI: parameter (PKPI*) atau performance (AKPI*) -> KpiNotificationService.
            if (str_starts_with($prefix, 'PKPI') || str_starts_with($prefix, 'AKPI')) {
                app(\App\Services\KPI\Notification\KpiNotificationService::class)
                    ->notifyRolePoolForCurrentStep($noTransaksi);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal kirim notif role pool dari ApprovalService', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send WhatsApp notification to next approver
     */
    protected function sendNextApproverNotification(string $noTransaksi, object $nextApproval): void
    {
        $approverKode = $nextApproval->Current_Approver ?? null;

        if (empty($approverKode)) {
            Log::info('Skip WA next approver: Current_Approver kosong', [
                'no_transaksi' => $noTransaksi,
                'next_approval' => $nextApproval,
            ]);

            return;
        }

        $approverData = DB::table('Karyawan')
            ->where('Kode_Karyawan', $approverKode)
            ->select('Kode_Karyawan', 'Nama', 'HP')
            ->first();

        if (! $approverData || empty($approverData->HP)) {
            Log::info('Skip WA next approver: data approver tidak valid / HP kosong', [
                'no_transaksi' => $noTransaksi,
                'approver_kode' => $approverKode,
                'has_approver_data' => (bool) $approverData,
            ]);

            return;
        }

        $requestData = DB::table('HRIS_Approval_Request as ar')
            ->join('Karyawan as k', 'ar.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->select('ar.Kode_Karyawan', 'k.Nama')
            ->first();
        // dd($requestData);

        $namaRequester = $requestData
            ? ($this->isKonversiLemburTransaction($noTransaksi)
                ? (string) $requestData->Nama
                : 'Tim '.$requestData->Nama)
            : 'Requester';
        $tipeTransaksi = $this->isKonversiLemburTransaction($noTransaksi)
            ? 'Konversi Lembur'
            : 'Pergantian Shift oleh Leader';
        $tanggalFormated = $this->isKonversiLemburTransaction($noTransaksi)
            ? $this->getKonversiLemburTanggalLabel($noTransaksi)
            : '-';

        $pesan = $this->whatsappMessageBuilder->Notif_Request_Transaksi_Tanggal(
            $approverData->HP,
            $approverData->Nama,
            $tipeTransaksi,
            $noTransaksi,
            $namaRequester,
            $tanggalFormated,
        );

        // dd($pesan);

        $updateWaCounter = function () use ($noTransaksi, $approverKode) {
            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Approver_Karyawan_Kode', $approverKode)
                ->whereNull('Flag_Approval')
                ->update([
                    'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                ]);
        };

        $this->whatsappLogHelper->sendAndLog(
            $approverKode,
            $approverData->Nama,
            $approverData->HP,
            $noTransaksi,
            $pesan,
            $updateWaCounter,
        );
    }

    protected function getKonversiLemburTanggalLabel(string $noTransaksi): string
    {
        $det = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
            ->where('No_Transaksi', $noTransaksi)
            ->orderByDesc('Id')
            ->select('Jenis_Konversi', 'Target_Off', 'Tanggal_Lembur')
            ->first();

        if (! $det) {
            return '-';
        }

        $jenisKonversi = strtoupper((string) ($det->Jenis_Konversi ?? 'DAY_OFF'));
        $rawDate = $jenisKonversi === 'DAY_OFF' ? ($det->Target_Off ?: $det->Tanggal_Lembur) : $det->Tanggal_Lembur;

        if (empty($rawDate)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $rawDate)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable $th) {
            return '-';
        }
    }

    protected function shouldSendGenericNextApproverNotification(string $noTransaksi): bool
    {
        if ($this->isKonversiLemburTransaction($noTransaksi)) {
            return true;
        }

        if (str_starts_with($noTransaksi, 'TSH')) {
            return true;
        }

        if (str_starts_with($noTransaksi, 'KL')) {
            return true;
        }

        $prefix = substr($noTransaksi, 0, 2);

        return in_array($prefix, ['SH', 'TH'], true);
    }

    protected function sendFinalRequesterNotification(
        string $noTransaksi,
        bool $isApproved,
        ?string $rejectorKode = null,
    ): void {
        try {
            $requestData = DB::table('HRIS_Approval_Request as ar')
                ->join('Karyawan as k', 'ar.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->select('ar.Kode_Karyawan', 'k.Nama', 'k.HP')
                ->first();

            if (! $requestData || empty($requestData->HP)) {
                return;
            }

            $namaRequester = $requestData->Nama;
            $tanggalFormated = $this->getKonversiLemburTanggalLabel($noTransaksi);
            $tipeTransaksi = 'Konversi Lembur';

            if ($isApproved) {
                // Final Approved -> use Notif_Selesai_Transaksi
                $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                    $requestData->HP,
                    $namaRequester,
                    $tipeTransaksi,
                    $noTransaksi,
                    $tanggalFormated,
                );
            } else {
                // Rejected -> matching confirm_izin_tolak format
                $rejectorName = 'Approver';
                if ($rejectorKode) {
                    $rejectorName =
                        DB::table('Karyawan')->where('Kode_Karyawan', $rejectorKode)->value('Nama') ?? 'Approver';
                }

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $requestData->HP,
                    'type' => 'template',
                    'template' => [
                        'name' => 'confirm_izin_tolak',
                        'language' => [
                            'code' => 'id',
                            'policy' => 'deterministic',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $namaRequester,
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $noTransaksi.' Pengajuan '.$tipeTransaksi,
                                    ],
                                    ['type' => 'text', 'text' => $rejectorName],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            $this->whatsappLogHelper->sendAndLog(
                $requestData->Kode_Karyawan,
                $namaRequester,
                $requestData->HP,
                $noTransaksi,
                $pesan,
            );
        } catch (\Exception $e) {
            Log::error('Failed to send Final WA Notification for Konversi Lembur: '.$e->getMessage());
        }
    }

    private function safeRollback(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
