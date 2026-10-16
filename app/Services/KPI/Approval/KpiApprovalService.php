<?php

namespace App\Services\KPI\Approval;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowResult;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use Illuminate\Support\Facades\DB;

/**
 * Titik integrasi tunggal approval KPI ke sistem terpusat (HRIS_Approval_Request).
 *
 * Service tipis: tidak menduplikasi logika, hanya mendelegasikan ke service
 * generik yang sudah ada (dipakai Cuti/Shift/Izin) dengan Kode_Config KPI.
 *
 * Dipakai oleh controller KPI (FormKpiController, UpdatePerformanceController).
 *
 * Kode_Config:
 *   - KPI_PARAM_STD : approval pengajuan parameter KPI
 *   - KPI_PERF_STD  : approval update performance KPI
 *
 * CATATAN PENTING soal sumber approver.
 *
 * CENTRAL memiliki request dan state-nya (HRIS_Approval_Request), TAPI daftar
 * SIAPA approver-nya masih dibaca dari tabel flow lama lewat jenis approval
 * bertipe KPI_TABLE. Migrasinya memang baru separuh, dan itu disengaja.
 *
 * Yang mudah menyesatkan: KEDUA config menunjuk ke tabel yang sama,
 * KPI_Approval_Flow_Performance — termasuk KPI_PARAM_STD. Itu bukan salah
 * ketik. Approver parameter dan performance adalah orang yang sama, jadi satu
 * tabel melayani keduanya. Tabel KPI_Approval_Flow_Parameter sudah tidak
 * dipakai dan nol baris hidup; mengarahkan config ke sana justru akan membuat
 * SEMUA karyawan kehilangan approver.
 *
 * Jangan "memperbaiki" config itu tanpa memindahkan datanya lebih dulu.
 */
class KpiApprovalService
{
    public const CONFIG_PARAM = 'KPI_PARAM_STD';

    public const CONFIG_PERF = 'KPI_PERF_STD';

    public function __construct(
        protected ApprovalFlowService $flowService,
        protected ApprovalRequestService $requestService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * Build flow approval untuk pengajuan PARAMETER KPI.
     *
     * @param  string  $kodeKaryawanRequester  Kode ANGGOTA yang dinilai (bukan leader untuk pengajuan tim)
     * @param  int|null  $approvalLimit  Expired_At dalam N hari (opsional)
     */
    public function createParamFlow(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        ?int $approvalLimit = null,
    ): ApprovalFlowResult {
        return $this->flowService->createFlowForKpi(
            $noTransaksi,
            $kodeKaryawanRequester,
            self::CONFIG_PARAM,
            $approvalLimit,
        );
    }

    /**
     * Build flow approval untuk UPDATE PERFORMANCE KPI.
     *
     * @param  string  $kodeKaryawanRequester  Kode ANGGOTA yang dinilai (bukan leader untuk pengajuan tim)
     */
    public function createPerfFlow(
        string $noTransaksi,
        string $kodeKaryawanRequester,
        ?int $approvalLimit = null,
    ): ApprovalFlowResult {
        return $this->flowService->createFlowForKpi(
            $noTransaksi,
            $kodeKaryawanRequester,
            self::CONFIG_PERF,
            $approvalLimit,
        );
    }

    /**
     * Approve satu step KPI. Delegasi penuh ke ApprovalService (engine terpusat).
     */
    public function approve(string $noTransaksi, string $approverKode, ?string $catatan = null)
    {
        return $this->approvalService->approve($noTransaksi, $approverKode, $catatan);
    }

    /**
     * Reject satu step KPI. Delegasi penuh ke ApprovalService.
     */
    public function reject(string $noTransaksi, string $rejectorKode, string $alasan, bool $forceRejectHalt = false)
    {
        return $this->approvalService->reject($noTransaksi, $rejectorKode, $alasan, $forceRejectHalt);
    }

    /**
     * Deteksi engine approval untuk satu transaksi KPI — TANPA kolom penanda.
     *
     * Sumber kebenaran = keberadaan baris di HRIS_Approval_Request dalam domain KPI.
     *   - Ada baris  → 'CENTRAL' (sudah memakai sistem terpusat)
     *   - Tidak ada  → 'LEGACY'  (masih di KPI_Approval_Request_Parameter/_Performance)
     */
    public function engineFor(string $noTransaksi): string
    {
        $isCentral = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->whereIn('Id_Jenis_Approval', $this->kpiJenisIds())
            ->exists();

        return $isCentral ? 'CENTRAL' : 'LEGACY';
    }

    public function isCentral(string $noTransaksi): bool
    {
        return $this->engineFor($noTransaksi) === 'CENTRAL';
    }

    /**
     * Id_Jenis_Approval milik domain KPI (guard agar deteksi engine tidak
     * salah-positif terhadap transaksi modul lain yang kebetulan senama).
     *
     * @return array<int,int>
     */
    public function kpiJenisIds(): array
    {
        return DB::table('N_HRIS_Jenis_Approval')
            ->whereIn('Source_Reference', ['KPI_Approval_Flow_Parameter', 'KPI_Approval_Flow_Performance', 'ROLE_KPI_VERIF'])
            ->pluck('Id_Jenis_Approval')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
