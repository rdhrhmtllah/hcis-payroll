# Repository-wide grouping manifest

Status: `BACKEND_ALIGNED_PENDING_BEHAVIOR_REGRESSION`

> **Catatan 2026-08-18.** Bagian "Remaining direct-root inventory" di bawah
> adalah snapshot historis dari pass sebelumnya dan sudah tidak berlaku.
> Kondisi terkini beserta hasil verifikasinya ada di
> `structure-alignment-final-status.md`. Ownership modul telah diverifikasi
> read-only dari `N_HRIS_Master_Grup_Fitur`: lima grup aktif adalah `ESS`,
> `KPI`, `LMS`, `VISITOR`, `SYSTEM`. Tidak ada grup `HRIS` maupun `SOP`.

This manifest is an inventory baseline for the user-approved repository-wide grouping. It does not replace the live database registry. Active ownership is constrained to ESS, KPI, LMS, VISITOR, and SYSTEM, verified from the development database on the current task date.

## Counts
- `app/Http/Controllers`: 135 files
- `app/Http/Requests`: 2 files
- `app/Models`: 39 files
- `app/Services`: 163 files
- `app/Jobs`: 23 files
- `app/Console/Commands`: 54 files
- `resources/js/Pages`: 449 files
- `resources/js/components`: 9 files
- `resources/js/composables`: 9 files
- `routes`: 37 files
- `tests`: 134 files

## Classification rules

1. Active source is grouped under `Module/Submodule/Feature/Layer`.
2. Cross-module code is grouped under an explicit shared boundary, not left anonymously at a generic root.
3. Snapshot, backup, copy, stable, and generated artifacts are separated from active source and never treated as runtime source.
4. Existing route URLs, names, middleware, payloads, Inertia keys, database contracts, and behavior remain unchanged during moves.
5. Uncertain ownership is recorded as `REVIEW_REQUIRED`; it is not silently assigned to a new module.

## Archive candidates

- `app/Http/Controllers/DashboardCeoController copy.php`
- `app/Http/Controllers/DashboardCeoController-stable.php`
- `app/Http/Controllers/backup/CrossDivisionReviewController.php`
- `app/Http/Controllers/backup/ReviewController.php`
- `app/Http/Controllers/snapshot/LeaderShiftController - stable - sblm refactor(centeralize + dynamic approval).php`
- `app/Http/Controllers/snapshot/ShiftRequestController_before_3tier_migration.php`
- `app/Http/Controllers/snapshot/shift/ShiftApprovalController-stable-sblm dynamic approval.php`
- `app/Http/Controllers/snapshot/shift/ShiftController - stable v2.5.5 - seblm di refactor.php`
- `app/Http/Controllers/snapshot/shift/ShiftRequestController-stable-sblm dynamic approval.php`
- `app/Http/Controllers/snapshot/uDash/uDashController-stable-sblm pola shifting baru.php`
- `app/Services/Lembur/snapshot/LemburRequestService-stable-sblm fix n+1.php`
- `app/Services/snapshot/approval/ApprovalFlowService - stable - sblm refactor dynamic approval.php`
- `app/Services/snapshot/shift/ShiftRequestService - stable - sblm refactor dynamic approval.php`
- `app/Services/snapshot/shift/ShiftRequestService - stable v2.5.5 - sblm refactor.php`
- `app/Services/snapshot/shift/ShiftRequestService-stable- sblm terapkan centralize satatus hari.php`
- `app/Jobs/ProcessBulkPenjadwalan copy.php`
- `app/Jobs/SendWhatsappMessage copy.php`
- `app/Console/Commands/tambahKuotaCutiBulanan copy.php`
- `resources/js/Pages/ESS/Attendance/Leave/Cuti/Admin/Index copy.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Cuti/Admin/KuotaManagement copy.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Cuti/Karyawan/Index copy.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Cuti/Karyawan/Index oldstable.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Cuti/Karyawan/Index-stable.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Izin/IzinAtasanApproval copy.vue`
- `resources/js/Pages/ESS/Attendance/Leave/Izin/IzinAtasanApproval-stable.vue`
- `resources/js/Pages/KPI/Performance/Parameter/FormKpi/team/FormKPITeam copy 2.vue`
- `resources/js/Pages/KPI/Performance/Parameter/FormKpi/team/FormKPITeam copy.vue`
- `resources/js/Pages/KPI/components/form/form-dynamic copy.vue`
- `resources/js/Pages/KPI/components/ui/modalV2-noir copy.vue`
- `resources/js/Pages/KPI/modules/approval-kpi/approvalKPI copy 2.vue`
- `resources/js/Pages/KPI/modules/approval-performance/performanceApproval copy form-dynmic.vue`
- `resources/js/Pages/KPI/modules/approval-performance/performanceApproval copy terakhir.vue`
- `resources/js/Pages/KPI/modules/approval-performance/performanceApproval copy.vue`
- `resources/js/Pages/KPI/modules/dashboard/ceo/dashboardCeo copy.vue`
- `resources/js/Pages/KPI/modules/dashboard/staff/dashboardStaff copy.vue`
- `resources/js/Pages/LMS/Training/Catalog/data/mockData2 copy.js`
- `resources/js/Pages/Modules/Administrator/ManajemenApprovalKPI copy.vue`
- `resources/js/Pages/Modules/Administrator/ManajemenKaryawanTimKPI copy.vue`
- `resources/js/Pages/Overtime copy 2.vue`
- `resources/js/Pages/Overtime-unstable.vue`
- `resources/js/Pages/backup/SummaryReview.vue`
- `resources/js/Pages/homepage/homepage copy 2.vue`
- `resources/js/Pages/homepage/homepage copy.vue`
- `resources/js/Pages/snapshot/shift/userShift - stable 2.5.5 - sebelum di refactor.vue`
- `resources/js/Pages/snapshot/uDash/uDash-stable-sblm pola shifting baru.vue`
- `resources/js/components/Shell/AppSidebar copy.vue`

## Remaining direct-root inventory

### `app/Http/Controllers` (55)
- `app/Http/Controllers/AboutController.php`
- `app/Http/Controllers/ActivityCenterController.php`
- `app/Http/Controllers/AdminShiftController.php`
- `app/Http/Controllers/ApprovalFlowShiftController.php`
- `app/Http/Controllers/ApprovalKPIController.php`
- `app/Http/Controllers/AttendanceQueueWorkerController.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/BscKategoriKpiController.php`
- `app/Http/Controllers/CaseController.php`
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/CrossDivisionReviewController.php`
- `app/Http/Controllers/DashboardCeoController copy.php`
- `app/Http/Controllers/DashboardCeoController-stable.php`
- `app/Http/Controllers/DashboardCeoController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/DivisionController.php`
- `app/Http/Controllers/EmployeeController.php`
- `app/Http/Controllers/EvaluationController.php`
- `app/Http/Controllers/GetFotoController.php`
- `app/Http/Controllers/KaryawanTimController.php`
- `app/Http/Controllers/KonversiLemburController.php`
- `app/Http/Controllers/KpiApprovalController.php`
- `app/Http/Controllers/KpiEnforcementController.php`
- `app/Http/Controllers/KpiKaryawanTeamController.php`
- `app/Http/Controllers/KpiVerifikasiController.php`
- `app/Http/Controllers/LeaderShiftController.php`
- `app/Http/Controllers/LemburController.php`
- `app/Http/Controllers/LevelController.php`
- `app/Http/Controllers/MasterPeriodeController.php`
- `app/Http/Controllers/NewsController.php`
- `app/Http/Controllers/OvertimeController.php`
- `app/Http/Controllers/PageAccessController.php`
- `app/Http/Controllers/PenjadwalanKpiController.php`
- `app/Http/Controllers/PerformanceApprovalController.php`
- `app/Http/Controllers/PerformanceKpiController.php`
- `app/Http/Controllers/PergantianHariController.php`
- `app/Http/Controllers/ProjectController.php`
- `app/Http/Controllers/ReviewController.php`
- `app/Http/Controllers/SatuanFormulaKpiController.php`
- `app/Http/Controllers/SatuanKpiController.php`
- `app/Http/Controllers/ShiftApprovalController.php`
- `app/Http/Controllers/ShiftController.php`
- `app/Http/Controllers/ShiftRequestController.php`
- `app/Http/Controllers/ShiftRuleAdminController.php`
- `app/Http/Controllers/TargetKpiController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Controllers/TicketController.php`
- `app/Http/Controllers/UpdatePerformanceController.php`
- `app/Http/Controllers/UserAksesDivisiController.php`
- `app/Http/Controllers/UserAksesLevelController.php`
- `app/Http/Controllers/karyawanTeamController.php`
- `app/Http/Controllers/parseResume.php`
- `app/Http/Controllers/perbaikiCheckinoutController.php`
- `app/Http/Controllers/uDashController.php`
- `app/Http/Controllers/userController.php`
### `app/Models` (39)
- `app/Models/CHECKINOUT.php`
- `app/Models/Cases.php`
- `app/Models/CategoryCross.php`
- `app/Models/CategoryEvaluations.php`
- `app/Models/CrossReviewDetails.php`
- `app/Models/CrossReviewPeriode.php`
- `app/Models/CrossReviews.php`
- `app/Models/Division.php`
- `app/Models/Evaluation.php`
- `app/Models/EvaluationCross.php`
- `app/Models/EvaluationsGlobal.php`
- `app/Models/GlobalReview.php`
- `app/Models/GlobalReviewDetail.php`
- `app/Models/Karyawan.php`
- `app/Models/KpiKaryawanTeam.php`
- `app/Models/Lembur.php`
- `app/Models/LemburDet.php`
- `app/Models/LemburDetail.php`
- `app/Models/Level.php`
- `app/Models/MasterPeriode.php`
- `app/Models/Progress.php`
- `app/Models/Project.php`
- `app/Models/ReponseTicket.php`
- `app/Models/RevisionTicketLog.php`
- `app/Models/Shift_Kerja.php`
- `app/Models/Shift_Kerja_Detail.php`
- `app/Models/Shift_Per_Karyawan.php`
- `app/Models/Shift_Sementara.php`
- `app/Models/SubmitTicketLog.php`
- `app/Models/SystemDatabaseChange.php`
- `app/Models/SystemTaskOutbox.php`
- `app/Models/Task.php`
- `app/Models/Ticket.php`
- `app/Models/TicketDetailReview.php`
- `app/Models/TicketFile.php`
- `app/Models/User.php`
- `app/Models/View_Divisi_Sub_Divisi.php`
- `app/Models/View_Golongan_Sub_Golongan_Level_Jabatan.php`
- `app/Models/Waktu_Kerja.php`
### `app/Services` (18)
- `app/Services/AdminLogNumberService.php`
- `app/Services/ApprovalFlowShiftService.php`
- `app/Services/ApprovalService.php`
- `app/Services/AuthorizationAuditLogger.php`
- `app/Services/AuthorizationResourceRegistryService.php`
- `app/Services/AuthorizationScopeResolver.php`
- `app/Services/CutiProcessBatchService.php`
- `app/Services/CutiService.php`
- `app/Services/EmployeeTargetScopeService.php`
- `app/Services/KonversiLemburService.php`
- `app/Services/MembershipManagementService.php`
- `app/Services/MenuAccessService.php`
- `app/Services/MenuBindingService.php`
- `app/Services/MenuDelegationPolicyService.php`
- `app/Services/ScopeApplicationEngine.php`
- `app/Services/ShiftRequestService.php`
- `app/Services/TransactionNumberService.php`
- `app/Services/TransactionService.php`
### `app/Jobs` (16)
- `app/Jobs/DeleteExportFileJob.php`
- `app/Jobs/EnforceKpiCapJob.php`
- `app/Jobs/GenerateKpiExportJob.php`
- `app/Jobs/GenerateLmsTrainingExportJob.php`
- `app/Jobs/GenerateSopComplianceExportJob.php`
- `app/Jobs/LeaderDashboardSyncJob.php`
- `app/Jobs/MarkAttendanceDirtyJob.php`
- `app/Jobs/ProcessBulkPenjadwalan copy.php`
- `app/Jobs/ProcessBulkPenjadwalan.php`
- `app/Jobs/SendExpiredIzinWhatsappJob.php`
- `app/Jobs/SendKpiNotifWhatsappJob.php`
- `app/Jobs/SendPenjadwalanNotificationJob.php`
- `app/Jobs/SendWhatsappJob.php`
- `app/Jobs/SendWhatsappMessage copy.php`
- `app/Jobs/SendWhatsappMessage.php`
- `app/Jobs/SyncPersonalNeedsActionJob.php`
### `app/Console/Commands` (51)
- `app/Console/Commands/ApiNoncePrune.php`
- `app/Console/Commands/AuditCutiOutOfBand.php`
- `app/Console/Commands/AuditCutiProvenance.php`
- `app/Console/Commands/BackfillCutiProvenance.php`
- `app/Console/Commands/BackfillTrainingFinalScore.php`
- `app/Console/Commands/BatasCutiAkhirTahun.php`
- `app/Console/Commands/CekBandPerformance.php`
- `app/Console/Commands/CekTargetMenyimpang.php`
- `app/Console/Commands/CheckMaturedBalances.php`
- `app/Console/Commands/CleanupExports.php`
- `app/Console/Commands/DbHealthSnapshot.php`
- `app/Console/Commands/ExpireVisitorEfektif.php`
- `app/Console/Commands/ExpiredIzin.php`
- `app/Console/Commands/HakCutiTambahan sssscopy.php`
- `app/Console/Commands/HakCutiTambahan.php`
- `app/Console/Commands/HrisAbsenMaintenanceCommand.php`
- `app/Console/Commands/HrisAbsenPlanCustomCommand.php`
- `app/Console/Commands/HrisAbsenPlanCutoffCommand.php`
- `app/Console/Commands/HrisAbsenPlanRollingCommand.php`
- `app/Console/Commands/HrisAbsenProcessQueueCommand.php`
- `app/Console/Commands/KontenVerifikasiUlang.php`
- `app/Console/Commands/KpiNotifUpdatePerformance.php`
- `app/Console/Commands/LeaderDashboardPrewarmCommand.php`
- `app/Console/Commands/MakeFullController.php`
- `app/Console/Commands/MigratePdfToImagesCommand.php`
- `app/Console/Commands/RapikanTargetSebelumMulai.php`
- `app/Console/Commands/SopBebanDunia.php`
- `app/Console/Commands/SopBebanJalan.php`
- `app/Console/Commands/SopBebanLaporan.php`
- `app/Console/Commands/SopDokumenBackfill.php`
- `app/Console/Commands/SopDokumenBaseline.php`
- `app/Console/Commands/SopDokumenKembar.php`
- `app/Console/Commands/SopDokumenVerify.php`
- `app/Console/Commands/SopReminderScan.php`
- `app/Console/Commands/SopRollupRecalc.php`
- `app/Console/Commands/SopRuleScan.php`
- `app/Console/Commands/SopSeedDemo.php`
- `app/Console/Commands/SopUatBersihkan.php`
- `app/Console/Commands/SopUatDunia.php`
- `app/Console/Commands/SopUatLaporan.php`
- `app/Console/Commands/SopUatSesi.php`
- `app/Console/Commands/VerifyCutiBalance.php`
- `app/Console/Commands/VerifyKpiYtd.php`
- `app/Console/Commands/WarmLeaderDashboardCache.php`
- `app/Console/Commands/cutiHangusHarian.php`
- `app/Console/Commands/flagCheckinout.php`
- `app/Console/Commands/kuotaAwalCuti.php`
- `app/Console/Commands/promotedEmployees.php`
- `app/Console/Commands/tambahKuotaCutiBulanan copy.php`
- `app/Console/Commands/tambahKuotaCutiBulanan.php`
- `app/Console/Commands/waUlang.php`
### `resources/js/Pages` (46)
- `resources/js/Pages/ApprovalFlowShift.vue`
- `resources/js/Pages/CrossDivisionReview.vue`
- `resources/js/Pages/CrossSummary.vue`
- `resources/js/Pages/DetailTicket.vue`
- `resources/js/Pages/Error.vue`
- `resources/js/Pages/GeneralReview.vue`
- `resources/js/Pages/GeneralRiview2.vue`
- `resources/js/Pages/LeaderShift.vue`
- `resources/js/Pages/LemburForm.vue`
- `resources/js/Pages/LemburFormDinamisImage.vue`
- `resources/js/Pages/LemburFormModern.vue`
- `resources/js/Pages/LemburFormSingle.vue`
- `resources/js/Pages/Overtime copy 2.vue`
- `resources/js/Pages/Overtime-unstable.vue`
- `resources/js/Pages/Overtime.vue`
- `resources/js/Pages/OvertimeAdmin.vue`
- `resources/js/Pages/SelfShift.vue`
- `resources/js/Pages/ShiftApproval.vue`
- `resources/js/Pages/ShiftRuleAdmin.vue`
- `resources/js/Pages/SummaryReview.vue`
- `resources/js/Pages/Tes.vue`
- `resources/js/Pages/Ticket.vue`
- `resources/js/Pages/TicketReview.vue`
- `resources/js/Pages/Untitled-1.vue`
- `resources/js/Pages/absensiSales2.vue`
- `resources/js/Pages/addInformation.vue`
- `resources/js/Pages/addKaryawanTeam.vue`
- `resources/js/Pages/feedbackDetail.vue`
- `resources/js/Pages/izinPage benar.vue`
- `resources/js/Pages/izinPage salah.vue`
- `resources/js/Pages/izinPage.vue`
- `resources/js/Pages/izinPage3.vue`
- `resources/js/Pages/izinPageAdmin.vue`
- `resources/js/Pages/izinPageDH.vue`
- `resources/js/Pages/izinPageRILL.vue`
- `resources/js/Pages/parseResume.vue`
- `resources/js/Pages/perbaikanCheckInOut.vue`
- `resources/js/Pages/perbaikanCheckInOut3.vue`
- `resources/js/Pages/shiftAdmin.vue`
- `resources/js/Pages/summaryGlobalDetail.vue`
- `resources/js/Pages/testUnix.vue`
- `resources/js/Pages/timeline.vue`
- `resources/js/Pages/uDash.vue`
- `resources/js/Pages/uDash2.vue`
- `resources/js/Pages/userProfile.vue`
- `resources/js/Pages/userShift.vue`
### `resources/js/composables` (9)
- `resources/js/composables/tourRegistry.js`
- `resources/js/composables/useActivityCenter.js`
- `resources/js/composables/useActivityHandshake.js`
- `resources/js/composables/useHomepageTour.js`
- `resources/js/composables/useLeaderDashboardTour.js`
- `resources/js/composables/useMediaUpload.js`
- `resources/js/composables/useRequestLifecycle.js`
- `resources/js/composables/useRequestLifecycle.test.js`
- `resources/js/composables/useShellState.js`
### `routes` (4)
- `routes/api.php`
- `routes/channels.php`
- `routes/console.php`
- `routes/web.php`
### `tests` (2)
- `tests/CreatesApplication.php`
- `tests/TestCase.php`

## Latest grouping checkpoint

Active runtime source has been grouped across Controllers, Services, Jobs, Console Commands, Models, Pages, and module route files. Generic roots intentionally retained are only Laravel/framework loaders/base classes and shared frontend infrastructure.

```text
app/Http/Controllers direct : Controller.php only
app/Services direct         : none
app/Jobs direct             : none
app/Console/Commands direct : none
app/Models direct           : none
resources/js/Pages direct   : Error.vue only
routes direct               : Laravel loaders only
legacy archive              : legacy/archive/
```

The remaining work is behavior regression, route/runtime verification, and correcting defects exposed by tests. Structural grouping itself is not a claim that every feature is behaviorally bug-free.
