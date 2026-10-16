<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <div @click="console.log(formStatus)" class="row d-flex align-items-center justify-content-center mb-3 fade-in">
            <div class="mb-1">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <!-- Bagian Kiri: Informasi Utama -->
                            <div class="col-lg-8 col-md-7">
                                <div class="header-info">
                                    <div class="icon-container d-flex align-items-center justify-content-center">
                                        <i
                                            class="bi bi-collection-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2 class="title fw-bold text-white text-start">Form KPI</h2>
                                        <p class="subtitle">
                                            {{
                                                penjadwalan?.nama_kegiatan_label
                                                    ? penjadwalan?.nama_kegiatan_label
                                                    : 'Tidak ada Jadwal'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Bagian Kanan: Approver Section Super Compact -->
                            <div class="col-lg-4 col-md-5">
                                <div class="micro-approvers-section">
                                    <div class="micro-section-header d-flex align-items-center mb-1">
                                        <i
                                            class="bi bi-people-fill me-1 micro-approver-icon d-flex align-items-center justify-content-center"
                                        ></i>
                                        <h3 class="micro-section-title mb-0">Approver</h3>
                                    </div>

                                    <div v-if="approvers.length > 0" class="micro-approver-list">
                                        <div
                                            class="micro-approver-item"
                                            v-for="(approver, index) in approvers"
                                            :key="index"
                                        >
                                            <div class="micro-approver-avatar">
                                                <i
                                                    class="bi bi-person d-flex align-items-center justify-content-center"
                                                ></i>
                                            </div>
                                            <div class="micro-approver-info">
                                                <div class="micro-approver-name">
                                                    {{ approver.name }}
                                                </div>
                                                <div class="micro-approver-position">
                                                    <span class="micro-badge">
                                                        {{ approver.level }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="micro-approver-list">
                                        <div class="micro-approver-item">
                                            <div class="micro-approver-avatar">
                                                <i
                                                    class="bi bi-person d-flex align-items-center justify-content-center"
                                                ></i>
                                            </div>
                                            <div class="micro-approver-info">
                                                <div class="micro-approver-name">Belum ada Approver</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- quick stat -->
        <quick-stat v-if="hasActiveSchedule" :stats="quickStatFieldFinal" :loading="loading.checkStatus" />

        <!-- Stepper -->
        <div v-if="hasActiveSchedule" class="col-12 mb-3 fade-in">
            <Stepper
                :steps="stepperSteps"
                :currentStep="autoCurrentStep"
                activeColor="#6366f1"
                completedColor="#10b981"
                inactiveColor="#e5e7eb"
                lineGradientFrom="#667eea"
                lineGradientTo="#764ba2"
                @update:currentStep="handleStepChange"
            />
        </div>

        <!-- State 2: Loading -->
        <div v-if="isLoadingState" class="row justify-content-center fade-in">
            <div class="col-12">
                <div class="loading-state">
                    <div class="loading-spinner">
                        <i class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"></i>
                    </div>
                    <h4 class="loading-title">Memuat Status Form...</h4>
                    <p class="loading-description">Sedang memeriksa status pengajuan KPI</p>
                </div>
            </div>
        </div>

        <!-- State 3: Form Submitted -->
        <div v-else-if="isFormSubmitted" class="submission-layout fade-in">
            <!-- Desktop Layout -->
            <div class="d-none d-lg-block">
                <div class="row g-3">
                    <!-- KPI Cards - Sticky Sidebar -->
                    <div class="col-lg-8">
                        <div class="sticky-kpi-sidebar">
                            <!-- Submitted KPI List -->
                            <div class="compact-kpi-list">
                                <div class="section-header-compact d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="section-title-wrapper">
                                            <h4 class="section-title">KPI yang Disubmit</h4>
                                            <span class="section-badge">{{ submittedKpis.length }} KPI</span>
                                        </div>
                                        <p class="section-subtitle">Klik untuk melihat detail</p>
                                    </div>

                                    <div class="text-white rounded-4 p-2" :class="badgeStatus">
                                        <span class="p-2">{{ parseStatusKPI }}</span>
                                    </div>
                                </div>

                                <div class="kpi-grid d-flex gap-2 overflow-x-auto py-2">
                                    <div
                                        v-for="(kpi, index) in submittedKpis"
                                        :key="index"
                                        class="kpi-card-compact"
                                        :class="{ revision: isRevision(kpi) }"
                                        @click="viewKpiDetail(kpi)"
                                    >
                                        <div class="kpi-card-header justify-content-end">
                                            <!-- <span class="kpi-number">#{{ index + 1 }}</span> -->
                                            <div
                                                @click.stop="editForm"
                                                class="kpi-number"
                                                :class="{
                                                    disabled: !canEditForm,
                                                }"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </div>
                                        </div>
                                        <div class="kpi-card-body">
                                            <div class="kpi-icon-compact">
                                                <i
                                                    class="d-flex justify-content-center align-items-center"
                                                    :class="getKpiIcon(kpi.bscCategory)"
                                                ></i>
                                            </div>
                                            <div class="kpi-content">
                                                <h6 class="kpi-title">
                                                    {{ kpi.parameter || 'KPI Tanpa Nama' }}
                                                </h6>
                                                <div class="kpi-meta-compact">
                                                    <span class="weight-badge"
                                                        >{{ formatAchievement(kpi.weight) || 0 }}%</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kpi-card-footer">
                                            <div class="completion-indicator">
                                                <span class="category-badge">{{
                                                    formatBscCategory(kpi.bscCategory) || 'No Category'
                                                }}</span>
                                                <div
                                                    class="completion-dot"
                                                    :class="{
                                                        completed: isKpiComplete(kpi),
                                                    }"
                                                ></div>
                                                <span class="completion-text">
                                                    {{ isKpiComplete(kpi) ? 'Lengkap' : 'Belum Lengkap' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <!-- <div class="submission-actions-compact">
                                <button
                                    class="btn-action-compact secondary"
                                    @click="downloadPDF"
                                >
                                    <i class="bi bi-file-earmark-pdf"></i>
                                    PDF
                                </button>
                                <button
                                    class="btn-action-compact secondary"
                                    @click="viewHistory"
                                >
                                    <i class="bi bi-clock-history"></i>
                                    History
                                </button>
                                <button
                                    v-if="canEditForm"
                                    class="btn-action-compact primary"
                                    @click="editForm"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Edit
                                </button>
                                <button
                                    class="btn-action-compact secondary"
                                    @click="checkFormStatus"
                                    :disabled="loading.checkStatus"
                                >
                                    <i
                                        class="bi bi-arrow-repeat"
                                        :class="{ spin: loading.checkStatus }"
                                    ></i>
                                    Refresh
                                </button>
                            </div> -->

                            <!-- Approval Notes -->
                            <div v-if="approvalData?.notes" class="approval-notes">
                                <div class="notes-header">
                                    <i class="bi bi-chat-left-text"></i>
                                    <span>Catatan Approval</span>
                                </div>
                                <div class="notes-content">
                                    {{ approvalData.notes }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Content - Scrollable -->
                    <div class="col-lg-4">
                        <div class="submission-content">
                            <SubmissionSidebar activeApproval="true" :approvalData="sidebarApprovalData" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Layout -->
            <div class="d-block d-lg-none">
                <div class="mobile-submission-layout">
                    <!-- Submitted KPI List -->
                    <div class="compact-kpi-list">
                        <div class="section-header-compact d-flex justify-content-between align-items-center">
                            <div>
                                <div class="section-title-wrapper">
                                    <h4 class="section-title">KPI yang Disubmit</h4>
                                    <!-- <span class="section-badge p-0"
                                        >{{ submittedKpis.length }} KPI</span
                                    > -->
                                </div>
                                <div class="text-white rounded-4 p-2 max-w-fit mobileStatus" :class="badgeStatus">
                                    <span class="">{{ parseStatusKPI }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="kpi-grid d-flex gap-2 overflow-x-auto py-2">
                            <div
                                v-for="(kpi, index) in submittedKpis"
                                :key="index"
                                class="kpi-card-compact"
                                :class="{ revision: isRevision(kpi) }"
                                @click="viewKpiDetail(kpi)"
                            >
                                <div class="kpi-card-header justify-content-end">
                                    <!-- <span class="kpi-number">#{{ index + 1 }}</span> -->
                                    <div @click.stop="editForm" class="kpi-number" :class="{ disabled: !canEditForm }">
                                        <i class="bi bi-pencil"></i>
                                    </div>
                                </div>
                                <div class="kpi-card-body">
                                    <div class="kpi-icon-compact">
                                        <i
                                            class="d-flex justify-content-center align-items-center"
                                            :class="getKpiIcon(kpi.bscCategory)"
                                        ></i>
                                    </div>
                                    <div class="kpi-content">
                                        <h6 class="kpi-title">
                                            {{ kpi.parameter || 'KPI Tanpa Nama' }}
                                        </h6>
                                        <div class="kpi-meta-compact">
                                            <span class="weight-badge">{{ formatAchievement(kpi.weight) || 0 }}%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="kpi-card-footer">
                                    <div class="completion-indicator">
                                        <span class="category-badge">{{
                                            formatBscCategory(kpi.bscCategory) || 'No Category'
                                        }}</span>
                                        <div
                                            class="completion-dot"
                                            :class="{
                                                completed: isKpiComplete(kpi),
                                            }"
                                        ></div>
                                        <span class="completion-text">
                                            {{ isKpiComplete(kpi) ? 'Lengkap' : 'Belum Lengkap' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <!-- <div class="submission-actions-compact">
                        <button
                            class="btn-action-compact secondary"
                            @click="downloadPDF"
                        >
                            <i class="bi bi-file-earmark-pdf"></i>
                            PDF
                        </button>
                        <button
                            class="btn-action-compact secondary"
                            @click="viewHistory"
                        >
                            <i class="bi bi-clock-history"></i>
                            History
                        </button>
                        <button
                            v-if="canEditForm"
                            class="btn-action-compact primary"
                            @click="editForm"
                        >
                            <i class="bi bi-pencil"></i>
                            Edit
                        </button>
                        <button
                            class="btn-action-compact secondary"
                            @click="checkFormStatus"
                            :disabled="loading.checkStatus"
                        >
                            <i
                                class="bi bi-arrow-repeat"
                                :class="{ spin: loading.checkStatus }"
                            ></i>
                            Refresh
                        </button>
                    </div> -->

                    <!-- Approval Notes -->
                    <div v-if="approvalData?.notes" class="approval-notes">
                        <div class="notes-header">
                            <i class="bi bi-chat-left-text"></i>
                            <span>Catatan Approval</span>
                        </div>
                        <div class="notes-content">
                            {{ approvalData.notes }}
                        </div>
                    </div>

                    <!-- Progress & History -->
                    <SubmissionSidebar activeApproval="true" :approvalData="sidebarApprovalData" />
                </div>
            </div>
        </div>
        <!-- State: Active Form (Default) -->
        <div v-else-if="hasActiveSchedule" class="active-form-container">
            <div class="row justify-content-center mb-0 fade-in">
                <div class="col-12">
                    <!-- Alasan penolakan GLOBAL (komentar umum approver/verifikator) -->
                    <div
                        v-if="submissionStatus === 'rejected' && alasanRejectGlobal"
                        class="global-reject-banner"
                    >
                        <i class="bi bi-x-octagon-fill"></i>
                        <div class="global-reject-text">
                            <span class="global-reject-label">Alasan Penolakan (Umum)</span>
                            <p class="global-reject-value">{{ alasanRejectGlobal }}</p>
                        </div>
                    </div>

                    <div class="compact-dashboard">
                        <!-- Horizontal Scroll Cards -->
                        <div class="cards-scroll-container">
                            <div class="cards-scroll-wrapper">
                                <!-- Add New Card -->
                                <div
                                    v-if="submissionStatus != 'rejected'"
                                    class="compact-card add-new"
                                    @click="addNewKpi"
                                >
                                    <div class="card-main">
                                        <div class="add-icon">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-plus-lg"
                                            ></i>
                                        </div>
                                        <span class="card-title">New Parameter</span>
                                    </div>
                                    <div class="card-hint">Click to add</div>
                                </div>

                                <!-- KPI Cards -->
                                <div
                                    v-for="(kpi, index) in kpis"
                                    :key="index"
                                    class="compact-card"
                                    :class="{
                                        active: activeFormIndex === index,
                                        completed: isFormCompleted(kpi) && !isRevision(kpi),
                                        // Tandai (border merah) parameter yang diminta revisi, TAPI jangan
                                        // dikunci — user boleh merevisi semua parameter walau hanya satu ditolak.
                                        revision: isRevision(kpi) && this.isEditForm && submissionStatus == 'rejected',
                                    }"
                                    @click="switchToForm(index)"
                                >
                                    <div class="card-main">
                                        <div class="kpi-icon">
                                            <i
                                                class="d-flex justify-content-center align-items-center"
                                                :class="getKpiIcon(kpi.bscCategory)"
                                            ></i>
                                        </div>
                                        <div class="kpi-info">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h4 class="card-title">
                                                    {{ truncateText(kpi?.parameter, 9) || 'New Parameter' }}
                                                </h4>
                                                <div class="card-actions">
                                                    <button
                                                        v-if="kpis.length > 1 && submissionStatus != 'rejected'"
                                                        @click.stop="removeKpi(index)"
                                                        class="btn-remove"
                                                        title="Remove KPI"
                                                    >
                                                        <i
                                                            class="d-flex justify-content-center align-items-center bi bi-trash"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="kpi-meta">
                                                <span class="category">{{
                                                    formatBscCategory(kpi.bscCategory) || 'No Category'
                                                }}</span>
                                                <span class="weight" v-if="kpi.weight > 0"
                                                    >{{ formatAchievement(kpi.weight) }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div
                                                    class="progress-fill"
                                                    :style="{
                                                        width: calculateCompletion(kpi) + '%',
                                                    }"
                                                ></div>
                                            </div>
                                            <span class="progress-text">{{ calculateCompletion(kpi) }}%</span>
                                        </div>
                                        <span class="card-badge">#{{ index + 1 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact Form Section -->
            <KpiForm
                :oldDataComponent="oldData"
                :modelValue="activeKpi"
                :fields="fields"
                :kpis="kpis"
                :activeFormIndex="activeFormIndex"
                :formulas="formulas"
                :categories="categories"
                :availableUnits="this.activeKpi.availableUnits"
                :loading="loading"
                :showValidation="showValidation"
                :validationErrorsComponents="validationErrors"
                :showHeader="true"
                :showNavigation="true"
                :ApprovalDataComponent="approvalData"
                :showHeaderActions="true"
                :showQuickActions="true"
                :isEditFormComponent="isEditForm"
                :statusTransaksi="submissionStatus"
                :totalWeight="totalWeight"
                :remainingWeight="remainingWeight"
                :maxAllowedWeight="maxAllowedWeightForActive"
                @update:modelValue="updateActiveKpi"
                @saveAndClose="SaveAndClose"
                @closeForm="closeForm"
                @switchToForm="switchToForm"
                @saveAndNew="SaveAndNew"
                @removeKpi="removeKpi"
                @triggerTarget="triggerTarget"
                @onFormulaChange="FormulaChange"
                @weightExceeded="handleWeightExceeded"
            />

            <!-- Bottom Action Bar -->
            <div class="bottom-action-bar-integrated fade-in" v-if="kpis.length > 0">
                <div class="action-content-integrated">
                    <div class="action-info">
                        <span class="info-text">{{ kpis.length }} KPI Ready</span>
                        <span class="weight-info" :class="{ danger: totalWeight > 100 }">
                            Total Weight: {{ totalWeight }}%
                        </span>
                    </div>
                    <div class="action-buttons-integrated">
                        <button @click="validateAll" class="btn-action-integrated validate">
                            <i class="d-flex justify-content-center align-items-center bi bi-check-circle"></i>
                            Validate All
                        </button>
                        <button @click="submitAllKpis" class="btn-action-integrated submit">
                            <i class="d-flex justify-content-center align-items-center bi bi-send"></i>
                            Submit All
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- State 1: No Schedule Available -->
        <div v-else class="row justify-content-center fade-in">
            <div class="col-12">
                <div class="empty-state-schedule">
                    <div class="empty-icon-container">
                        <i class="bi bi-calendar-x-fill d-flex justify-content-center align-items-center"></i>
                    </div>
                    <div class="empty-content">
                        <h3 class="empty-title">Tidak Ada Penjadwalan Aktif</h3>
                        <p class="empty-description">
                            Saat ini tidak ada periode penjadwalan KPI yang aktif. Silakan hubungi administrator untuk
                            informasi lebih lanjut.
                        </p>
                        <div class="empty-actions">
                            <button class="btn-modern btn-primary" @click="refreshPage">
                                <i class="bi bi-arrow-clockwise d-flex justify-items-center align-items-center"></i>
                                Refresh Halaman
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Save -->
        <ModalV1
            v-if="showConfirmationModal"
            :show="showConfirmationModal"
            title="Confirm Submission"
            submitText="Submit All"
            :loading="loading.submitKpis"
            @close="closeConfirmationModal"
            @submit="confirmSubmit"
        >
            <div class="confirmation-content d-flex justify-content-center align-items-center flex-column">
                <i
                    class="bi bi-check-circle text-success fs-1 d-flex justify-content-center align-items-center mb-4"
                ></i>
                <p class="text-center mb-2">
                    Ready to submit <strong>{{ kpis.length }}</strong> KPI forms
                </p>
                <p class="text-center small text-muted">Total weight distribution: {{ totalWeight }}%</p>
            </div>
        </ModalV1>

        <!-- Modal Target -->
        <Modal
            v-if="showModalTarget"
            :show="showModalTarget"
            title="Target Breakdown"
            size="large"
            @close="closeModalTarget"
        >
            <template #body>
                <div class="modal-target-content">
                    <!-- Skala Target Section -->
                    <div class="target-scale-section mb-4">
                        <label class="form-label-optimized mb-3">
                            <div class="label-icon-container">
                                <i class="d-flex justify-content-center align-items-center bi bi-tag-fill"></i>
                            </div>
                            <div class="label-text">
                                <span class="label-title">Skala Target</span>
                                <span class="label-subtitle">Pilih periode target yang diinginkan</span>
                            </div>
                        </label>
                        <v-select
                            :options="TargetDatasField"
                            :searchable="false"
                            :filterable="false"
                            :clearable="false"
                            label="label"
                            :disabled="!kpis[activeFormIndex].unit"
                            placeholder="Pilih Skala Target..."
                            :reduce="(opt) => opt.value"
                            v-model="kpis[activeFormIndex].target"
                            :selectable="(option) => !option.disabled"
                            :optionDisabled="(opt) => opt.disabled"
                            @update:modelValue="targetDecision"
                            class="target-scale-select"
                        />
                    </div>

                    <!-- Range Info dari Formula -->
                    <div v-if="activeFormulaRangeData" class="formula-range-info mb-3">
                        <i class="bi bi-rulers me-2"></i>
                        <span class="fw-semibold">Range Aktual yang Diharapkan:</span>
                        <strong class="mx-1">{{ activeFormulaRangeData.Min_Range ?? '∞' }}</strong>
                        <span>—</span>
                        <strong class="mx-1">{{ activeFormulaRangeData.Max_Range ?? '∞' }}</strong>
                        <span class="text-muted">(rentang nilai aktual untuk parameter ini)</span>
                    </div>

                    <!-- Target Form Section menggunakan KpiForm -->
                    <div
                        v-if="choosedTarget && targetBreakdownDatas?.length > 0 && !loading.fillingTarget"
                        class="target-form-section"
                    >
                        <div class="section-header mb-3">
                            <div class="section-icon">
                                <i class="d-flex justify-content-center align-items-center bi bi-bullseye"></i>
                            </div>
                            <div class="section-text">
                                <h4 class="section-title">
                                    Detail Target
                                    {{ choosedTarget.Nama_Target }}
                                </h4>
                                <p class="section-subtitle">Isi nilai target untuk setiap periode</p>
                            </div>
                        </div>

                        <!-- Target Summary -->
                        <div class="target-summary mb-3" v-if="targetBreakdownDatas.length > 0">
                            <div class="summary-info">
                                <span class="summary-text">
                                    Total Field:
                                    {{ targetBreakdownDatas.filter((e) => e.isActive === true).length }}
                                    |
                                    <span class="d-none d-md-inline"> Terisi: {{ getFilledTargetCount() }} | </span>
                                    Status:
                                    {{
                                        getFilledTargetCount() ===
                                        targetBreakdownDatas.filter((e) => e.isActive === true).length
                                            ? 'Lengkap'
                                            : 'Belum Lengkap'
                                    }}
                                </span>
                                <span class="total-value">
                                    Total:
                                    {{
                                        truncateText(
                                            convertValueUnit(
                                                calculateTargetTotal(),
                                                getTargetUnitFormat,
                                                getTargetUnitSymbol,
                                            ),
                                            45 || 0,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Quick Action: Isi Cepat Periode -->
                        <div class="quick-fill-panel mb-4" v-if="targetBreakdownDatas.length > 1">
                            <div class="quick-fill-header">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="quick-fill-icon">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                    </div>
                                    <div>
                                        <span class="quick-fill-title">Isi Cepat Periode</span>
                                        <span class="quick-fill-subtitle text-muted">
                                            Terapkan nilai seragam atau bagi rata ke {{ targetBreakdownDatas.filter((e) => e.isActive === true).length }} periode aktif
                                        </span>
                                    </div>
                                </div>
                                <span v-if="getTargetUnitSymbol" class="badge-unit-pill">
                                    Satuan: <strong>{{ getTargetUnitSymbol }}</strong>
                                </span>
                            </div>

                            <div class="quick-fill-body">
                                <div class="quick-fill-controls">
                                    <div class="quick-fill-input-box">
                                        <i class="bi bi-pencil-fill input-icon"></i>
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            :value="quickTargetFormatted"
                                            @input="handleQuickTargetInput"
                                            class="quick-input"
                                            :placeholder="quickTargetPlaceholder"
                                            @keyup.enter="applyQuickValueToAllPeriods"
                                        />
                                        <span v-if="getTargetUnitSymbol" class="input-unit-tag">
                                            {{ getTargetUnitSymbol }}
                                        </span>
                                    </div>

                                    <div class="quick-fill-actions">
                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-primary"
                                            @click="applyQuickValueToAllPeriods"
                                            :disabled="quickTargetValue === '' || quickTargetValue === null"
                                            title="Isi seluruh periode aktif dengan nilai ini"
                                        >
                                            <i class="bi bi-check2-all"></i>
                                            <span>Terapkan ke Semua</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-secondary"
                                            @click="distributeQuickValueEvenly"
                                            :disabled="quickTargetValue === '' || quickTargetValue === null || Number(quickTargetValue) <= 0"
                                            title="Bagi rata nilai ini ke jumlah periode aktif"
                                        >
                                            <i class="bi bi-distribute-horizontal"></i>
                                            <span>Bagi Rata ({{ targetBreakdownDatas.filter((e) => e.isActive === true).length }}x)</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-quick btn-quick-ghost"
                                            @click="resetAllTargetPeriods"
                                            title="Kosongkan seluruh nilai target"
                                        >
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                            <span>Reset</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <KpiForm
                            :modelValue="activeKpi?.targetSimpan || {}"
                            :fields="targetModalFields"
                            :kpis="[activeKpi?.targetSimpan || {}]"
                            :activeFormIndex="0"
                            :availableUnits="this.activeKpi.availableUnits"
                            :loading="loading"
                            :showValidation="false"
                            :validationErrorsComponents="[]"
                            :showHeader="false"
                            :showNavigation="false"
                            :showHeaderActions="false"
                            :showQuickActions="false"
                            :isEditFormComponent="false"
                            :statusTransaksi="submissionStatus"
                            @update:modelValue="updateTargetValues"
                            class="compact-target-form"
                        />
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="choosedTarget && targetBreakdownDatas?.length == 0 && !loading.fillingTarget"
                        class="empty-state d-flex justify-content-center align-items-center flex-column"
                    >
                        <div class="empty-icon mb-4">
                            <i class="d-flex justify-content-center align-items-center bi bi-inbox mb-3"></i>
                        </div>
                        <p class="empty-text">Tidak ada field target yang tersedia</p>
                    </div>

                    <!-- loading State -->
                    <div v-else-if="choosedTarget && loading.fillingTarget" class="loading-container">
                        <div class="loading-spinner">
                            <i class="bi bi-arrow-repeat d-flex justify-content-center align-items-center"></i>
                        </div>
                        <h4 class="loading-title">Memuat Data Target...</h4>
                        <p class="loading-description">Sedang mengambil data target</p>
                    </div>

                    <!-- Instruction State -->
                    <div v-else class="instruction-state">
                        <div class="instruction-icon">
                            <i class="mb-3 d-flex justify-content-center align-items-center bi bi-arrow-up-circle"></i>
                        </div>
                        <h5 class="instruction-title">Pilih Skala Target</h5>
                        <p class="instruction-text">
                            Silakan pilih skala target terlebih dahulu untuk menampilkan form input
                        </p>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeModalTarget">Batal</button>
                    <button class="btn-modern btn-primary" @click="submitTarget" :disabled="!isTargetFull(activeKpi)">
                        Simpan Target
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Alert -->
        <!-- <el-dialog
            v-model="showAlertModal"
            title="Konfirmasi menyimpan data"
            width="400px"
            center
            :lock-scroll="true"
        >
            <div class="delete-modal-content">
                <i
                    class="bi bi-exclamation-triangle warning-icon d-flex justify-content-center align-items-center"
                ></i>
                <p>{{ Pesan }}</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <template #footer>
                <div class="delete-modal-footer">
                    <button
                        class="btn-modern"
                        style="color: #334155 !important"
                        @click="closeAlertModal"
                    >
                        Batal
                    </button>
                    <button class="btn-modern btn-success" @click="fnc">
                        {{ buttonAlert }}
                    </button>
                </div>
            </template>
        </el-dialog> -->

        <AlertModal
            :show="alertComponents.showAlertModal"
            :title="alertComponents.title"
            :message="alertComponents.content"
            :type="alertComponents.type"
            :confirmText="alertComponents.submit"
            :cancelText="alertComponents.cancel"
            @confirm="alertComponents.onSubmit"
            @close="alertComponents.onCancel"
        />

        <!-- Modal Validasi -->
        <Modal
            v-if="showValidationModal"
            :show="showValidationModal"
            title="Validasi Form KPI"
            size="large"
            @close="showValidationModal = false"
        >
            <template #body>
                <div class="kpi-validation-content">
                    <div class="kpi-validation-header">
                        <div class="kpi-validation-icon" :class="validationResults.allValid ? 'success' : 'error'">
                            <i
                                :class="
                                    validationResults.allValid ? 'bi bi-check-circle' : 'bi bi-exclamation-triangle'
                                "
                                class="d-flex justify-content-center align-items-center"
                            ></i>
                        </div>
                        <p class="kpi-validation-title">
                            {{
                                validationResults.allValid
                                    ? 'Seluruh form KPI valid!'
                                    : 'Beberapa form KPI belum lengkap:'
                            }}
                        </p>
                        <p v-if="!validationResults.allValid" class="kpi-validation-subtitle">
                            Silakan lengkapi bagian berikut:
                        </p>
                    </div>

                    <div v-if="!validationResults.allValid" class="kpi-validation-scroll-container">
                        <ul class="kpi-validation-list">
                            <li
                                v-for="(kpiError, index) in validationResults.kpiErrors"
                                :key="index"
                                class="kpi-validation-item"
                            >
                                <div class="kpi-member-validation">
                                    <div class="kpi-member-header">
                                        <i
                                            class="bi bi-graph-up kpi-member-icon d-flex justify-content-center align-items-center"
                                        ></i>
                                        <h3 class="kpi-member-name">
                                            KPI {{ index + 1 }}:
                                            {{ kpiError.parameter || 'Nama Parameter Belum Diisi' }}
                                        </h3>
                                    </div>

                                    <ul class="kpi-error-list">
                                        <li
                                            v-for="(error, errorIndex) in kpiError.errors"
                                            :key="errorIndex"
                                            class="kpi-error-item"
                                        >
                                            <i
                                                class="bi bi-x-circle kpi-error-icon d-flex justify-content-center align-items-center"
                                            ></i>
                                            <span class="kpi-error-text">{{ error }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>

                            <!-- Total Weight Error -->
                            <li v-if="validationResults.totalWeightError" class="kpi-validation-item">
                                <div class="kpi-member-validation">
                                    <div class="kpi-member-header">
                                        <i
                                            class="bi bi-percent kpi-member-icon d-flex justify-content-center align-items-center"
                                        ></i>
                                        <h3 class="kpi-member-name">Total Weight</h3>
                                    </div>
                                    <ul class="kpi-error-list">
                                        <li class="kpi-error-item">
                                            <i
                                                class="bi bi-x-circle kpi-error-icon d-flex justify-content-center align-items-center"
                                            ></i>
                                            <span class="kpi-error-text">{{ validationResults.totalWeightError }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="!validationResults.allValid" class="kpi-validation-summary">
                        <p class="kpi-summary-text">
                            Total
                            <strong>{{ validationResults.totalErrors }}</strong>
                            {{ validationResults.totalErrors > 1 ? 'errors' : 'error' }}
                            ditemukan
                        </p>
                        <p class="kpi-summary-text">
                            <strong>{{ validationResults.kpiErrors.length }}</strong>
                            {{ validationResults.kpiErrors.length > 1 ? 'KPI' : 'KPI' }}
                            memerlukan perbaikan
                        </p>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="kpi-modal-footer-actions">
                    <button class="kpi-btn kpi-btn-primary" @click="showValidationModal = false">
                        <i class="bi bi-check-lg d-flex justify-content-center align-items-center"></i>
                        {{ validationResults.allValid ? 'Lanjutkan' : 'Mengerti' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Modal Detail submit KPI -->
        <ModalV1
            v-if="showKpiDetailModal"
            :show="showKpiDetailModal"
            :title="`Detail KPI: ${selectedKpiDetail?.parameter}`"
            submitText="Tutup"
            @close="closeKpiDetailModal"
            @submit="closeKpiDetailModal"
        >
            <div class="kpi-detail-content" v-if="selectedKpiDetail">
                <div class="detail-section">
                    <div class="detail-row">
                        <div class="detail-label">Parameter</div>
                        <div class="detail-value">
                            {{ selectedKpiDetail.parameter }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Kategori BSC</div>
                        <div class="detail-value">
                            {{ formatBscCategory(selectedKpiDetail.bscCategory) }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Definisi</div>
                        <div class="detail-value">
                            {{ selectedKpiDetail.definition }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Formula</div>
                        <div class="detail-value">
                            {{ formatFormula(selectedKpiDetail.formula) }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Unit</div>
                        <div class="detail-value">
                            {{ selectedKpiDetail.Nama_Satuan }}
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Weight</div>
                        <div class="detail-value">{{ formatAchievement(selectedKpiDetail.weight) }}%</div>
                    </div>
                    <div class="detail-row" v-if="selectedKpiDetail.target">
                        <div class="detail-label">Target</div>
                        <div
                            class="detail-value cursor-pointer"
                            style="cursor: pointer !important"
                            @click="openTargetPreview"
                        >
                            {{ formatTarget(selectedKpiDetail.target) }}
                            <i class="bi bi-box-arrow-down-left ms-2"></i>
                        </div>
                    </div>
                    <div class="detail-row" v-if="selectedKpiDetail.Alasan_Tolak">
                        <div class="detail-label">Revisi</div>
                        <div class="detail-value">
                            <span class="p-2 bg-danger text-white rounded-5">{{ selectedKpiDetail.Alasan_Tolak }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </ModalV1>

        <TargetPreviewModal
            :show="showTargetPreview"
            :targetData="targetDataPreview"
            :targetScale="selectedScale"
            :targetUnit="selectedUnit"
            :valueFormat="format_input"
            :showActual="false"
            :size="fullscreen"
            :showActualToggle="false"
            @close="closeTargetPreview"
        />
    </div>
</template>

<script>
import { ElMessage, ElNotification, ElDialog } from 'element-plus';
import 'element-plus/dist/index.css';
import ModalV1 from '@/components/legacy/modalV1.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import TargetPreviewModal from '../../../components/ui/targetPreview.vue';
import KpiForm from '../../../components/form/form-dynamic.vue';
import FormV1 from '@/components/legacy/formV1.vue';
import quickStat from '@/components/legacy/quickStat.vue';
import Stepper from '../../../components/ui/stepper.vue';
import AlertModal from '../../../components/ui/ModalAlert.vue';
import SubmissionSidebar from '../../../components/ui/SubmissionSidebar.vue';
import axios from 'axios';
import vSelect from 'vue-select';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { tanggalPanjang } from '@/utils/tanggal';

const formatUtils = {
    parseNumeric: (value) => {
        if (value === null || value === undefined || value === '') return '';
        let str = value.toString().replace('%', '').replace(/,/g, '.');
        const parts = str.split('.');
        if (parts.length > 2) {
            str = parts[0] + '.' + parts.slice(1).join('');
        }
        return str.replace(/[^\d.]/g, '');
    },
    currency: {
        format: (value, locale = 'id-ID') => {
            if (value === null || value === undefined || value === '') return '';
            const strValue = value.toString();
            if (strValue.endsWith('.') && strValue.length > 1) {
                return new Intl.NumberFormat(locale).format(parseFloat(strValue)) + ',';
            }
            const numericValue = parseFloat(strValue) || 0;
            if (isNaN(numericValue)) return '';
            const parts = strValue.split('.');
            let decimals = parts.length > 1 ? parts[1].length : 0;
            return new Intl.NumberFormat(locale, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: 5,
            }).format(numericValue);
        },
        parse: (value) => {
            if (value === null || value === undefined || value === '') return '';
            let str = value.toString();
            str = str.replace(/\./g, '');
            str = str.replace(',', '.');
            return str.replace(/[^\d.]/g, '');
        },
    },
};

export default {
    name: 'KpiEntryFormOptimized',
    components: {
        KpiForm,
        vSelect,
        TargetPreviewModal,
        FormV1,
        AlertModal,
        Modal,
        ModalV1,
        DotLottieVue,
        ElMessage,
        ElNotification,
        ElDialog,
        quickStat,
        Stepper,
        SubmissionSidebar,
    },
    props: {
        penjadwalan: Object,
        approvers: { type: Array, defaullt: [] },
        targetList: { type: Array, default: [] },
    },
    data() {
        return {
            showValidationModal: false,
            validationResults: {
                allValid: false,
                kpiErrors: [],
                totalWeightError: '',
                totalErrors: 0,
            },
            showTargetPreview: false,
            windowWidth: window.innerWidth,
            targetBreakdownDatas: [],
            // Approval data structure
            approversDatas: [],
            // Activities data structure
            activities: [
                {
                    title: 'Submit KPI Forms',
                    description: 'Submit process below (S)',
                    date: '10 Jan 2025',
                    time: '10:14',
                },
                {
                    title: 'Revision Form',
                    description: 'Perbaikan pada target Q1',
                    date: '12 Jan 2025',
                    time: '14:30',
                    source: 'https://www.kepi.com/about/',
                },
                {
                    title: 'Revision Form2',
                    description: 'Perbaikan pada target Q1',
                    date: '12 Jan 2025',
                    time: '14:30',
                    source: 'https://www.kepi.com/about/',
                },
            ],
            currentStep: 1,
            isEditForm: false,
            stepperSteps: [
                {
                    id: 1,
                    title: 'Pengisian Form',
                    description: 'Isi dan submit form KPI',
                },
                {
                    id: 2,
                    title: 'Menunggu Approval',
                    description: 'KPI sedang ditinjau atasan',
                },
                {
                    id: 3,
                    title: 'Selesai',
                    description: 'KPI aktif dan terkunci',
                },
            ],
            showKpiDetailModal: false,
            selectedKpiDetail: null,
            statusRefreshInterval: null,
            showModalTarget: false,
            quickTargetValue: '',
            quickTargetFormatted: '',
            formulaRequest: null,
            BscKategoriDatas: [],
            TargetDatas: [],
            TargetDatasField: [],
            FormulaDatas: [],
            kpis: [
                {
                    Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                    parameter: '',
                    bscCategory: '',
                    definition: '',
                    formula: '',
                    unit: '',
                    weight: null,
                    target: '',
                    targetSimpan: {},
                    availableUnits: [],
                },
            ],
            activeFormIndex: 0,
            currentDate: new Date(),
            showConfirmationModal: false,
            showValidation: false,
            validationErrors: [],
            categories: [],
            formulas: [],
            loading: {
                submitKpis: false,
                listData: false,
                units: false,
                fillingTarget: false,
                checkStatus: false,
            },
            // availableUnits: [],
            alertComponents: {
                showAlertModal: false,
                content: '',
                title: '',
                submit: 'OK',
                cancel: 'Batal',
                type: 'info',
                onCancel: null,
                onSubmit: null,
            },
            formStatus: {
                Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                isSubmitted: false,
                status: null,
                submissionDate: null,
                submissionTime: null,
                kpis: [],
                approvalData: null,
            },
            // hasActiveSchedule: true, // Default true, akan diupdate berdasarkan data
            nextScheduleDate: null,
        };
    },
    watch: {
        'activeKpi.formula'(newVal, oldVal) {
            if (newVal) {
                // if (Object.keys(this.activeKpi.targetSimpan).length > 0) {
                //     this.confirmClearTarget();
                // }
                // console.log(newVal);
                this.onFormulaChange(newVal);
            }
        },
        'activeKpi.target'(newVal, oldVal) {
            if (newVal) {
                this.getTargetBreakdown(this.activeKpi.target);
            }
        },
        // "activeKpi.unit"(newVal) {
        //     if (newVal) {
        //         if (Object.keys(this.activeKpi.targetSimpan).length > 0) {
        //             this.confirmClearTarget();
        //         }
        //     }
        // },
        'activeKpi.availableUnits'(newVal) {
            if (this.activeKpi.formula && (!newVal || newVal.length === 0)) {
                this.onFormulaChange(this.activeKpi.formula);
            }
        },
    },
    computed: {
        activeFormulaRangeData() {
            const formulaId = this.kpis[this.activeFormIndex]?.formula;
            if (!formulaId) return null;
            const f = this.FormulaDatas.find((d) => d.Id_Master_Formula == formulaId);
            if (!f || !f.Has_Range) return null;
            return f;
        },
        getTargetUnitSymbol() {
            const activeKpi = this.activeKpi;
            if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

            const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

            if (!currentUnit) return null;
            return currentUnit.name;
        },
        getTargetUnitFormat() {
            const activeKpi = this.activeKpi;
            if (!activeKpi?.unit || !activeKpi?.availableUnits) return null;

            const currentUnit = activeKpi.availableUnits.find((unit) => unit.id === activeKpi.unit);

            if (!currentUnit) return null;
            return currentUnit.Format_Input;
        },
        quickTargetPlaceholder() {
            const format = this.getTargetUnitFormat;
            if (format === 'currency') return 'Contoh: 10.000.000';
            if (format === 'percentage') return 'Contoh: 100';
            return 'Ketik nilai target...';
        },
        targetDataPreview() {
            // console.log("ini data ne", this.selectedKpiDetail);
            return this.selectedKpiDetail?.targetDataPreview;
        },
        format_input() {
            return this.selectedKpiDetail?.Format_Input;
        },
        selectedScale() {
            return this.selectedKpiDetail?.selectedScale;
        },
        selectedUnit() {
            return this.selectedKpiDetail?.Nama_Satuan;
        },
        oldData() {
            return this.formStatus.kpis || null;
        },
        no_transaksi_ref() {
            return this.formStatus.approvalData?.No_Transaksi || null;
        },
        sidebarApprovalData() {
            // Contoh data dengan 4 status yang berbeda
            return this.approversDatas;
        },

        sidebarActivities() {
            const activities = [];

            // Add submission activity
            if (this.submissionDate) {
                activities.push({
                    title: 'Submit KPI Forms',
                    description: 'Submit process below (S)',
                    date: this.formatDateString(this.submissionDate),
                    time: this.submissionTime || '04:37:34',
                });
            }

            // Add revision activity if exists
            // if (this.approvalData?.notes) {
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            activities.push({
                title: 'Revision Form',
                description: this.approvalData.notes,
                date: '10 Jan 2025',
                time: '12:14',
                source: this.approvalData.source || 'https://www.kepi.com/about/',
            });
            // }

            return activities;
        },
        quickStatFieldFinal() {
            if (!this.isFormSubmitted) {
                return this.quickStatField;
            } else {
                return this.quickStatFieldSubmitted;
            }
        },
        currentFlow() {
            return this.approvalData.current_flow;
        },
        isRejected() {
            if (this.approvalData.approval == 'T') {
                return true;
            } else {
                return false;
            }
        },
        currentApprovalLabel() {
            const approvers = Array.isArray(this.formStatus?.approvers)
                ? this.formStatus.approvers
                : this.approversDatas || [];

            const preferredStatus = this.submissionStatus === 'rejected' ? 'rejected' : 'inprogress';
            const activeApprover = approvers.find((item) => item.status === preferredStatus)
                || approvers.find((item) => item.status === 'rejected')
                || approvers.find((item) => item.status === 'inprogress')
                || null;

            const label = activeApprover?.name
                || activeApprover?.role
                || this.approvalData?.approver
                || this.approvalData?.Approver
                || '';

            return typeof label === 'string' ? label.trim() : '';
        },
        parseStatusKPI() {
            const approverLabel = this.currentApprovalLabel;

            switch (this.submissionStatus) {
                case 'pending':
                    return approverLabel ? `Diproses ${approverLabel}` : 'Diproses';
                case 'approved':
                    return 'Disetujui';
                case 'rejected':
                    return approverLabel ? `Ditolak ${approverLabel}` : 'Ditolak';
                default:
                    return 'Gagal';
            }
        },
        badgeStatus() {
            switch (this.submissionStatus) {
                case 'pending':
                    return 'badge-primary';
                case 'approved':
                    return 'badge-success';
                case 'rejected':
                    return 'badge-danger';
                default:
                    return 'badge-primary';
            }
        },
        autoCurrentStep() {
            if (!this.hasActiveSchedule) return 1;

            if (this.isLoadingState) return 1;

            if (this.isFormSubmitted) {
                switch (this.submissionStatus) {
                    case 'pending':
                        return 2;
                    case 'approved':
                        return 3;
                    case 'rejected':
                        return 1;
                    default:
                        return 1;
                }
            }

            return 1; // Default untuk active form
        },
        hasActiveSchedule() {
            if (!this.penjadwalan) {
                return false;
            } else {
                return true;
            }
        },
        isFormSubmitted() {
            return this.formStatus.isSubmitted;
        },

        submittedKpis() {
            return this.formStatus.kpis || [];
        },

        submissionStatus() {
            return this.formStatus.status;
        },

        alasanRejectGlobal() {
            return this.formStatus.alasanRejectGlobal || null;
        },

        submissionDate() {
            return this.formStatus.submissionDate;
        },

        submissionTime() {
            return this.formStatus.submissionTime;
        },

        approvalData() {
            return this.formStatus.approvalData;
        },

        quickStatFieldSubmitted() {
            const statusConfig = {
                pending: {
                    text: 'Menunggu',
                    icon: 'bi bi-clock',
                    color: 'warning',
                },
                approved: {
                    text: 'Disetujui',
                    icon: 'bi bi-check-circle',
                    color: 'success',
                },
                rejected: {
                    text: 'Ditolak',
                    icon: 'bi bi-x-circle',
                    color: 'danger',
                },
            };

            const config = statusConfig[this.submissionStatus] || {
                text: '-',
                icon: 'bi bi-question-circle',
                color: 'secondary',
            };

            return [
                {
                    title: 'Total KPI',
                    value: this.submittedKpis.length || 0,
                    unit: '',
                    icon: 'bi bi-layers',
                    bgIcon: 'bg-primary',
                    sentiment: 'primary',
                    size: 'col-4',
                },
                {
                    title: 'Total Weight',
                    value: this.totalSubmittedWeight,
                    unit: '%',
                    icon: 'bi bi-speedometer2',
                    bgIcon: 'bg-warning',
                    sentiment: 'warning',
                    size: 'col-4',
                },
                {
                    title: 'Status',
                    value: config.text,
                    unit: '',
                    icon: config.icon,
                    bgIcon: config.color,
                    sentiment: config.color,
                    size: 'col-4',
                },
            ];
        },
        canEditForm() {
            return (
                (this.submissionStatus === 'pending' && this.currentFlow == 1) || this.submissionStatus === 'rejected'
            );
        },
        isLoadingState() {
            return this.loading.checkStatus;
        },
        totalSubmittedWeight() {
            return this.submittedKpis.reduce((sum, kpi) => sum + (parseFloat(kpi.weight) || 0), 0);
        },

        fields() {
            return [
                {
                    name: 'parameter',
                    type: 'text',
                    label: 'Parameter',
                    placeholder: 'Masukkan parameter KPI...',
                    icon: 'bi bi-tag',
                    size: 'col-12 col-md-6',
                    rowIndex: 0,
                    enabled: true,
                    required: true,
                },
                {
                    name: 'bscCategory',
                    type: 'select',
                    label: 'BSC Category',
                    placeholder: 'Select Category',
                    icon: 'bi bi-grid-3x3',
                    size: 'col-12 col-md-6',
                    rowIndex: 0,
                    enabled: true,
                    required: true,
                    loadingKey: 'listData',
                },
                {
                    name: 'definition',
                    type: 'textarea',
                    label: 'Definition',
                    placeholder: 'Detail deskripsi dari poin KPI...',
                    icon: 'bi bi-card-text',
                    size: 'col-12',
                    rowIndex: 1,
                    enabled: true,
                    required: true,
                    rowClass: 'full-width',
                },
                {
                    name: 'formula',
                    type: 'select',
                    label: 'Formula',
                    placeholder: 'Select Formula',
                    icon: 'bi bi-calculator',
                    size: 'col-12 col-md-6',
                    rowIndex: 2,
                    enabled: true,
                    loading: true,
                    loadingKey: 'listData',
                    onChange: this.FormulaChange,
                },
                {
                    name: 'unit',
                    type: 'select',
                    label: 'Unit of Measure',
                    placeholder: 'Select Unit',
                    icon: 'bi bi-rulers',
                    size: 'col-12 col-md-6',
                    rowIndex: 2,
                    enabled: true,
                    loading: true,
                    loadingKey: 'units',
                },
                {
                    name: 'weight',
                    type: 'persen',
                    label: 'Weight',
                    icon: 'bi bi-speedometer2',
                    size: 'col-12 col-md-6',
                    rowIndex: 3,
                    enabled: true,
                    required: true,
                    min: 0,
                    max: 100,
                    format: 'percentage',
                },
                {
                    name: 'target',
                    type: 'popup',
                    label: 'Target',
                    placeholder: this.getTargetStatus(this.choosedTarget?.Nama_Target) || 'Select Target',
                    icon: 'bi bi-bullseye',
                    size: 'col-12 col-md-6',
                    loading: true,
                    loadingKey: 'units',
                    rowIndex: 3,
                    enabled: true,
                    options: [],
                    onChange: (val, activeKpi) => {
                        this.fillingTarget(val);
                    },
                },
            ];
        },
        choosedTarget() {
            const selected = this.TargetDatas.find(
                (t) => t.Id_Master_Target === this.kpis[this.activeFormIndex]?.target,
            );
            if (!selected) return;
            return selected;
        },
        targetModalFields() {
            if (!this.choosedTarget) {
                this.loading.fillingTarget = false;
                return [];
            }

            // Dapatkan format berdasarkan unit yang dipilih
            const getTargetFormat = () => {
                if (!this.activeKpi.unit || !this.activeKpi.availableUnits) return null;

                const currentUnit = this.activeKpi.availableUnits.find((unit) => unit.id === this.activeKpi.unit);

                if (!currentUnit) return null;
                const unitName = currentUnit.Format_Input;
                // console.log(unitSymbol);
                // Mapping unit ke format
                if (unitName) {
                    if (unitName === 'percentage') {
                        return { type: 'percentage', params: [0] };
                    } else {
                        return {
                            type: unitName,
                            params: [],
                        };
                    }
                } else {
                    return {
                        type: 'number',
                        params: [0],
                    };
                }
            };
            const getTargetUnitSymbol = () => {
                if (!this.activeKpi.unit || !this.activeKpi.availableUnits) return null;

                const currentUnit = this.activeKpi.availableUnits.find((unit) => unit.id === this.activeKpi.unit);

                if (!currentUnit) return null;
                const unitSymbol = currentUnit.name;

                return unitSymbol;
            };

            const targetFormat = getTargetFormat();
            // console.log(targetFormat);

            let targetType = this.choosedTarget.Kode_Target;
            let unitSymbol = getTargetUnitSymbol();

            let totalData = this.targetBreakdownDatas?.length || 0;

            let colSize = 'col-lg-12';

            if (totalData > 1) {
                colSize = 'col-lg-6';
            }

            const rangeData = this.activeFormulaRangeData;
            const rangeMin = rangeData ? rangeData.Min_Range : null;
            const rangeMax = rangeData ? rangeData.Max_Range : null;

            let stackTargetBreakdownDatas = this.targetBreakdownDatas.map((item) => ({
                name: item.Id_Target_Breakdown,
                label: `${item.Nama_Breakdown}`,
                type: 'number',
                placeholder: `Masukkan nilai ${item.Nama_Breakdown.toLowerCase()}...`,
                icon: 'bi bi-calendar',
                size: colSize,
                enabled: true,
                disabledField: item.isActive ? false : true,
                format: targetFormat,
                unitSymbol: unitSymbol,
                required: true,
                id: item.Id_Target_Breakdown,
                urutan: item.Urutan,
                periode: item.Periode,
                rangeMin: rangeMin,
                rangeMax: rangeMax,
            }));
            // console.log("ini di map : " + stackTargetBreakdownDatas);
            this.loading.fillingTarget = false;
            return stackTargetBreakdownDatas;

            // switch (targetType) {
            //     case "Y":
            //         return [
            //             {
            //                 name: "yearly",
            //                 label: "Yearly Target",
            //                 type: "number",
            //                 placeholder: "Masukkan nilai yearly...",
            //                 icon: "bi bi-calendar",
            //                 size: "col-12",
            //                 enabled: true,
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 required: true,
            //             },
            //         ];

            //     case "Q":
            //         return [
            //             {
            //                 name: "q1",
            //                 label: "Q1 Target",
            //                 type: "number",
            //                 placeholder: "Nilai Q1...",
            //                 icon: "bi bi-1-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //             {
            //                 name: "q2",
            //                 label: "Q2 Target",
            //                 type: "number",
            //                 placeholder: "Nilai Q2...",
            //                 icon: "bi bi-2-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //             {
            //                 name: "q3",
            //                 label: "Q3 Target",
            //                 type: "number",
            //                 placeholder: "Nilai Q3...",
            //                 icon: "bi bi-3-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //             {
            //                 name: "q4",
            //                 label: "Q4 Target",
            //                 type: "number",
            //                 placeholder: "Nilai Q4...",
            //                 icon: "bi bi-4-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //         ];

            //     case "M":
            //         const months = [
            //             {
            //                 name: "january",
            //                 label: "January",
            //                 icon: "bi bi-1-square",
            //             },
            //             {
            //                 name: "february",
            //                 label: "February",
            //                 icon: "bi bi-2-square",
            //             },
            //             {
            //                 name: "march",
            //                 label: "March",
            //                 icon: "bi bi-3-square",
            //             },
            //             {
            //                 name: "april",
            //                 label: "April",
            //                 icon: "bi bi-4-square",
            //             },
            //             { name: "may", label: "May", icon: "bi bi-5-square" },
            //             { name: "june", label: "June", icon: "bi bi-6-square" },
            //             { name: "july", label: "July", icon: "bi bi-7-square" },
            //             {
            //                 name: "august",
            //                 label: "August",
            //                 icon: "bi bi-8-square",
            //             },
            //             {
            //                 name: "september",
            //                 label: "September",
            //                 icon: "bi bi-9-square",
            //             },
            //             {
            //                 name: "october",
            //                 label: "October",
            //                 icon: "bi bi-10-square",
            //             },
            //             {
            //                 name: "november",
            //                 label: "November",
            //                 icon: "bi bi-11-square",
            //             },
            //             {
            //                 name: "december",
            //                 label: "December",
            //                 icon: "bi bi-12-square",
            //             },
            //         ];
            //         return months.map((month, i) => ({
            //             name: month.name,
            //             label: month.label,
            //             type: "number",
            //             placeholder: `Nilai ${month.label}...`,
            //             icon: month.icon,
            //             size: "col-12 col-md-6 col-lg-4",
            //             format: targetFormat,
            //             unitSymbol: unitSymbol,
            //             enabled: true,
            //             required: true,
            //         }));

            //     case "S":
            //         return [
            //             {
            //                 name: "S1",
            //                 label: "S1 Target",
            //                 type: "number",
            //                 placeholder: "Nilai S1...",
            //                 icon: "bi bi-1-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //             {
            //                 name: "S2",
            //                 label: "S2 Target",
            //                 type: "number",
            //                 placeholder: "Nilai S1...",
            //                 icon: "bi bi-1-circle",
            //                 size: "col-12 col-md-6",
            //                 format: targetFormat,
            //                 unitSymbol: unitSymbol,
            //                 enabled: true,
            //                 required: true,
            //             },
            //         ];

            //     default:
            //         return [];
            // }
        },
        quickStatField() {
            return [
                {
                    title: 'Total KPI',
                    value: this.totalKpis,
                    unit: '',
                    icon: 'bi bi-layers',
                    bgIcon: 'bg-primary',
                    sentiment: 'primary',
                    size: 'col-4',
                },
                {
                    title: 'Completed',
                    value: this.completedForms,
                    unit: '',
                    icon: 'bi bi-check-circle',
                    bgIcon: 'bg-success',
                    sentiment: 'success',
                    size: 'col-4',
                },
                {
                    title: 'Total Weight',
                    value: this.totalWeight,
                    unit: '%',
                    icon: 'bi bi-speedometer2',
                    bgIcon: 'bg-warning',
                    sentiment: 'warning',
                    size: 'col-4',
                },
            ];
        },

        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        completedForms() {
            return this.kpis.filter((kpi) => this.isFormCompleted(kpi)).length;
        },
        totalKpis() {
            return this.kpis.length || 0;
        },
        activeKpi() {
            const index = Number(this.activeFormIndex);

            const defaultKpi = {
                Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                parameter: '',
                bscCategory: '',
                definition: '',
                formula: '',
                unit: '',
                weight: null,
                target: '',
                targetSimpan: {},
                availableUnits: [],
            };

            if (!Number.isInteger(index)) return defaultKpi;

            return this.kpis[index] || defaultKpi;
        },
        totalWeight() {
            return this.kpis.reduce((total, kpi) => {
                const weight = parseFloat(kpi.weight) || 0;
                return total + weight;
            }, 0);
        },

        remainingWeight() {
            return Math.max(0, 100 - this.totalWeight);
        },

        totalWeightExcludingActive() {
            if (this.activeFormIndex === null) return this.totalWeight;

            return this.kpis.reduce((total, kpi, index) => {
                if (index !== this.activeFormIndex) {
                    const weight = parseFloat(kpi.weight) || 0;
                    return total + weight;
                }
                return total;
            }, 0);
        },

        maxAllowedWeightForActive() {
            const max = 100 - this.totalWeightExcludingActive;
            return Math.max(0, max);
        },
        isWeightExceedingMax() {
            return (fieldName) => {
                const value = this.activeKpi[fieldName];
                const numericValue = parseFloat(value) || 0;
                return numericValue > this.maxAllowedWeight;
            };
        },

        getRemainingWeightClass() {
            return () => {
                if (this.remainingWeight <= 0) return 'text-danger';
                if (this.remainingWeight < 20) return 'text-warning';
                return 'text-success';
            };
        },

        getProgressBarClass() {
            return () => {
                if (this.totalWeight > 100) return 'bg-danger';
                if (this.totalWeight >= 80) return 'bg-warning';
                return 'bg-success';
            };
        },

        rejectedKpiIndexes() {
            return this.kpis
                .map((kpi, index) => (kpi.Alasan_Tolak && kpi.Alasan_Tolak.trim() !== '' ? index : null))
                .filter((index) => index !== null)
                .sort((a, b) => a - b); // urut dari kecil ke besar
        },
    },
    methods: {
        formatAchievement(value) {
            const num = Number(value);
            if (isNaN(num)) return '0';
            // cek apakah punya desimal
            return num % 1 === 0 ? num.toString() : num.toFixed(1);
        },
        convertValueUnit(value, format, simbol = null) {
            if (!format || !value) return '0';

            let simbolSatuan = simbol ? simbol?.trim() : '';
            // console.log(simbolSatuan);
            let decimals = 2;
            let result = '';

            if (format == 'currency') {
                result = new Intl.NumberFormat('id-ID').format(value);
            } else if (format == 'percentage') {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();

                const userInputDecimal = strValue.includes('.') || strValue.includes(',');

                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                }

                result = `${numericValue.toFixed(decimals)}`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == 'currency' ? simbolSatuan + '. ' + result : result + ' ' + simbolSatuan;
        },
        calculateTargetTotal() {
            if (!this.activeKpi.targetSimpan) return 0;

            let total = 0;
            if (Array.isArray(this.activeKpi.targetSimpan)) {
                total = this.activeKpi.targetSimpan.reduce((sum, item) => {
                    const value = parseFloat(item.Nilai_Target) || 0;
                    return sum + value;
                }, 0);
            } else if (typeof this.activeKpi.targetSimpan === 'object') {
                total = Object.values(this.activeKpi.targetSimpan).reduce((sum, val) => {
                    const value = parseFloat(val) || 0;
                    return sum + value;
                }, 0);
            }
            return total;
        },
        getFilledTargetCount() {
            if (!this.activeKpi.targetSimpan) return 0;

            if (Array.isArray(this.activeKpi.targetSimpan)) {
                return this.activeKpi.targetSimpan.filter(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                ).length;
            } else if (typeof this.activeKpi.targetSimpan === 'object') {
                return Object.values(this.activeKpi.targetSimpan).filter((val) => val !== null && val !== '').length;
            }
            return 0;
        },
        openTargetPreview() {
            this.showKpiDetailModal = false;
            this.showTargetPreview = true;
        },
        closeTargetPreview() {
            this.showKpiDetailModal = true;
            this.showTargetPreview = false;
        },
        isMobile() {
            return this.windowWidth < 768;
        },
        confirmClearTarget() {
            this.alertComponents = {
                showAlertModal: true,
                content: 'Kamu yakin untuk mengubah, tindakan ini akan menghilangkan target anda?',
                title: 'Merubah data!',
                submit: 'Hapus',
                cancel: 'Batal',
                type: 'warning',
                onCancel: this.closeAlertModal(),
                onSubmit: () => {
                    this.targetBreakdownDatas = [];
                    this.activeKpi.target = '';
                    this.activeKpi.targetSimpan = {};
                    this.closeAlertModal();
                },
            };
        },
        handleWeightExceeded(exceededWeight) {
            const maxAllowed = this.maxAllowedWeightForActive;
            ElMessage.warning(`Weight melebihi batas. Akan disesuaikan menjadi ${maxAllowed}%`);

            this.updateActiveKpi({
                ...this.activeKpi,
                weight: maxAllowed.toString(),
            });
        },
        isKpisChanged() {
            let status = false;

            for (let i = 0; i < this.kpis.length; i++) {
                const newKpi = this.kpis[i];
                const oldKpi = this.formStatus.kpis[i];

                for (let key in newKpi) {
                    if (key === 'availableUnits') continue;
                    if (key === 'Alasan_Tolak') continue;
                    if (key === 'Nama_Satuan') continue;
                    if (key === 'Id_Penjadwalan') continue;

                    // kalau targetSimpan object → cek stringify
                    if (key === 'targetSimpan') {
                        if (JSON.stringify(newKpi[key]) !== JSON.stringify(oldKpi[key])) {
                            status = true;
                        }
                    } else {
                        //console.log(
                        //   newKpi[key],
                        //  oldKpi[key],
                        //   "hasil " + key + (newKpi[key] !== oldKpi[key])
                        // );

                        if (newKpi[key] !== oldKpi[key]) {
                            status = true;
                        }
                    }
                }
            }
            return status;
        },
        formatFormula(formula) {
            const data = this.FormulaDatas.find((i) => i.Id_Master_Formula === formula);
            return data?.Rumus || 'Loading...';
        },
        formatUnit(unit, formula) {
            this.onFormulaChange(formula);
            const data = this.availableUnits.find((i) => i.id === unit);
            return data?.name || 'Loading...';
        },
        formatTarget(target) {
            const data = this.TargetDatas.find((i) => i.Id_Master_Target === target);
            return data?.Nama_Target || 'Loading...';
        },
        isRevision(kpi) {
            if (!this.isEditForm) return null;
            return kpi.Alasan_Tolak ? kpi.Alasan_Tolak : null;
        },
        handleStepChange(step) {
            this.currentStep = step;
            // Optional: Tambahkan logika navigasi jika diperlukan
            switch (step) {
                case 1:
                    // Navigasi ke form editing jika diperlukan
                    if (this.isFormSubmitted && this.canEditForm) {
                        this.editForm();
                    }
                    break;
                case 2:
                    // Refresh status untuk melihat approval
                    this.checkFormStatus();
                    break;
                case 3:
                    // Tampilkan form yang sudah approved
                    this.checkFormStatus();
                    break;
            }
        },
        isKpiComplete(kpi) {
            return kpi.parameter && kpi.bscCategory && kpi.definition && kpi.weight > 0 && kpi.formula && kpi.unit;
        },

        getStatusIcon(status) {
            const iconMap = {
                pending: 'bi bi-clock',
                approved: 'bi bi-check-circle',
                rejected: 'bi bi-x-circle',
            };
            return iconMap[status] || 'bi bi-question-circle';
        },

        getStatusText(status) {
            const statusMap = {
                pending: 'Menunggu',
                approved: 'Disetujui',
                rejected: 'Ditolak',
            };
            return statusMap[status] || 'Unknown';
        },

        getStatusDescription(status) {
            const descriptions = {
                pending: 'Form KPI sedang menunggu persetujuan atasan',
                approved: 'Form KPI telah disetujui dan terkunci',
                rejected: 'Form KPI ditolak, silakan perbaiki dan submit ulang',
            };
            return descriptions[status] || 'Status tidak diketahui';
        },

        // editForm() {
        //     if (this.canEditForm) {
        //         this.formStatus.isSubmitted = false;
        //         this.kpis = this.formStatus.kpis.map((kpi) => ({
        //             ...kpi,
        //             Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
        //             // No_Transaksi_Ref: kpi.No_Transaksi,
        //         }));

        //         if (this.submissionStatus == 'rejected') {
        //             this.activeFormIndex = this.rejectedKpiIndexes;
        //         } else {
        //             this.activeFormIndex = 0;
        //         }
        //         // this.onFormulaChange(this.submittedKpis[0].formula);
        //         this.showSimpleAlert('Form telah dibuka untuk editing', 'success');
        //     }
        // },
        editForm() {
            if (!this.canEditForm) return;

            this.formStatus.isSubmitted = false;

            this.kpis = (this.formStatus.kpis || []).map((kpi) => ({
                ...kpi,
                Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,

                // pastikan field wajib selalu ada
                targetSimpan: kpi.targetSimpan || {},
                availableUnits: kpi.availableUnits || [],
            }));

            if (this.submissionStatus === 'rejected') {
                const firstRejectedIndex = this.rejectedKpiIndexes[0];

                this.activeFormIndex = typeof firstRejectedIndex === 'number' ? firstRejectedIndex : 0;
            } else {
                this.activeFormIndex = 0;
            }

            this.showValidation = false;
            this.showSimpleAlert('Form telah dibuka untuk editing', 'success');
        },

        async checkFormStatus() {
            if (!this.penjadwalan?.Id_Master_Penjadwalan) {
                this.resetFormStatus();
                return;
            }

            this.loading.checkStatus = true;
            try {
                const response = await axios.get(`/api/v1/form-kpi/status/${this.penjadwalan.Id_Master_Penjadwalan}`);

                if (response.data?.result) {
                    this.updateFormStatus(response.data.result);
                } else {
                    this.resetFormStatus();
                }
            } catch (error) {
                console.error('Error checking form status:', error);
                this.resetFormStatus();
                this.showSimpleAlert('Gagal memuat status form', 'error');
            } finally {
                this.loading.checkStatus = false;
            }
        },

        updateFormStatus(result) {
            this.approversDatas = result.approvers || [];
            this.isEditForm = result.isSubmitted || false;
            this.formStatus = {
                Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                isSubmitted: result.isSubmitted || false,
                status: result.status || null,
                submissionDate: result.submissionDate || null,
                submissionTime: result.submissionTime || null,
                kpis: result.kpis || [],
                approvers: result.approvers || [],
                approvalData: result.approvalData || null,
                alasanRejectGlobal: result.alasan_reject_global || null,
            };
        },

        resetFormStatus() {
            this.formStatus = {
                isSubmitted: false,
                isEditForm: false,
                status: null,
                submissionDate: null,
                submissionTime: null,
                kpis: [],
                approvalData: null,
            };
        },

        viewKpiDetail(kpi) {
            this.selectedKpiDetail = kpi;
            // console.log(this.selectedKpiDetail);
            this.showKpiDetailModal = true;
        },

        closeKpiDetailModal() {
            this.showKpiDetailModal = false;
            this.selectedKpiDetail = null;
        },

        refreshPage() {
            window.location.reload();
        },

        downloadPDF() {
            console.log('Download PDF triggered');
        },

        viewHistory() {
            this.$router.push('/kpi-history');
        },

        async addNewKpi() {
            if (this.approvers?.length == 0) {
                ElMessage.warning('Anda belum ada approver, Silahkan hubungi admin!');
                return;
            }
            if (this.submissionStatus == 'rejected') return;

            if (this.activeFormIndex !== null && !this.validateCurrentFormSilent()) {
                this.showSimpleAlert('Tolong lengkapi KPI yang sekarang, sebelum menambahkan.');
                return;
            }
            const newKpi = {
                Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                parameter: '',
                bscCategory: '',
                definition: '',
                formula: '',
                unit: '',
                weight: null,
                target: '',
                targetSimpan: {},
                availableUnits: [],
            };
            this.kpis.push(newKpi);
            this.activeFormIndex = this.kpis.length - 1;
            this.scrollToNewCard();
        },

        async fetchListData() {
            this.loading.listData = true;
            try {
                const response = await axios.get('/api/v1/form-kpi/current', {
                    params: {
                        Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                    },
                });

                if (response.status === 200 && response.data?.result) {
                    this.BscKategoriDatas = response.data.result.dataBSCKategori;
                    this.TargetDatas = response.data.result.dataTarget;
                    this.FormulaDatas = response.data.result.dataFormula;

                    const bscField = this.fields.find((f) => f.name === 'bscCategory');
                    if (bscField) {
                        this.categories = this.BscKategoriDatas.map((item) => ({
                            id: item.Id_Master_Bsc_Kategori,
                            name: item.Nama_Kategori,
                        }));
                    }

                    this.TargetDatasField = this.TargetDatas.map((item) => ({
                        value: item.Id_Master_Target,
                        label:
                            item.is_active == 'Y'
                                ? item.Nama_Target?.toUpperCase()
                                : item.Nama_Target?.toUpperCase() + ' (' + item.reason + ')',
                        kode: item.Kode_Target,
                        disabled: item.is_active == 'Y' ? false : true,
                    }));

                    this.formulas = this.FormulaDatas.map((item) => ({
                        id: item.Id_Master_Formula,
                        name: item.Has_Range
                            ? `${item.Rumus}  ·  Range Aktual: ${item.Min_Range ?? '∞'} — ${item.Max_Range ?? '∞'}`
                            : item.Rumus,
                    }));
                }
            } catch (error) {
                console.error('Failed to fetch list data:', error);
            } finally {
                this.loading.listData = false;
            }
        },

        async getTargetBreakdown() {
            this.loading.fillingTarget = true;
            try {
                this.targetBreakdownDatas = [];
                const targetId = this.activeKpi.target;
                const response = await axios.get(
                    // `/api/v1/form-kpi/getTargetBreakdown/${targetId}`
                    `/api/v1/form-kpi/getTargetBreakdown`,
                    {
                        params: {
                            Id_Master_Target: targetId,
                            Id_Penjadwalan: this.penjadwalan?.Id_Master_Penjadwalan,
                        },
                    },
                );

                if (response.status === 200 && response.data?.result) {
                    this.targetBreakdownDatas = response.data.result;
                    this.fillTargetData();
                    // console.log(this.targetBreakdownDatas);
                    // console.log(response.data.result);
                }
            } catch (error) {
                this.targetBreakdownDatas = [];
                console.error('Failed to fetch list data:', error);
            } finally {
                this.loading.fillingTarget = false;
            }
        },

        fillTargetData() {
            if (!this.choosedTarget) return;
            if (Object.keys(this.activeKpi.targetSimpan).length > 0) return;

            // Inisialisasi nilai default berdasarkan format
            const targetFields = this.targetModalFields.filter((field) => !field.disabledField);
            // console.log("ini fillingtarget", targetFields);
            const initialValues = {};

            targetFields.forEach((field) => {
                initialValues[field.name] = ''; // atau nilai default lainnya
            });

            this.activeKpi.targetSimpan = { ...initialValues };
        },

        confirmChangeTarget() {
            if (Array.isArray(this.activeKpi.targetSimpan)) {
                return this.activeKpi.targetSimpan.some(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                );
            }

            if (typeof this.activeKpi.targetSimpan === 'object' && this.activeKpi.targetSimpan !== null) {
                return Object.values(this.activeKpi.targetSimpan).some((val) => val !== null && val !== '');
            }

            return false;
        },
        getTargetStatus(label = 'Target') {
            let total = 0;
            let filled = 0;

            if (Array.isArray(this.activeKpi.targetSimpan)) {
                total = this.activeKpi.targetSimpan.length;
                filled = this.activeKpi.targetSimpan.filter(
                    (item) => item.Nilai_Target !== null && item.Nilai_Target !== '',
                ).length;
            } else if (typeof this.activeKpi.targetSimpan === 'object' && this.activeKpi.targetSimpan !== null) {
                const values = Object.values(this.activeKpi.targetSimpan);
                total = values.length;
                filled = values.filter((val) => val !== null && val !== '').length;
            }

            const isFull = filled === total && total > 0;

            return `${label} - ${filled}/${total} Terisi ${isFull ? '✔️' : '❌'}`;
        },
        isTargetFull(target = null) {
            let total = 0;
            let filled = 0;

            // Jika target tidak diberikan, gunakan activeKpi
            const targetData = target ? target.targetSimpan : this.activeKpi.targetSimpan;
            // console.log(targetData);

            if (Array.isArray(targetData)) {
                total = targetData.length;
                filled = targetData.filter((item) => item.Nilai_Target !== null && item.Nilai_Target !== '').length;
            } else if (typeof targetData === 'object' && targetData !== null) {
                const values = Object.values(targetData);
                total = values.length;
                filled = values.filter((val) => val !== null && val !== '').length;
            }

            const isFull = filled === total && total > 0;
            return isFull;
        },

        validateTargetFields() {
            if (!this.activeKpi.targetSimpan) return false;

            const targetFields = this.targetModalFields.filter((field) => !field.disabledField);
            for (let field of targetFields) {
                const value = this.activeKpi.targetSimpan[field.name];
                if (value === undefined || value === null || value === '') return false;
                // Izinkan 0 jika range formula memang mencakup 0 (mis. zero defect: min=0, max=0)
                const zeroIsValid =
                    field.rangeMin !== null && field.rangeMin !== undefined &&
                    field.rangeMin <= 0 &&
                    (field.rangeMax === null || field.rangeMax === undefined || field.rangeMax >= 0);
                if (Number(value) === 0 && !zeroIsValid) return false;
            }
            return true;
        },

        async targetDecision() {
            if (this.confirmChangeTarget()) {
                const ok = confirm('Data target sebelumnya akan hilang. Yakin ingin mengganti?');
                if (!ok) return;
            }

            // Reset targetSimpan untuk memastikan format baru diterapkan
            this.activeKpi.targetSimpan = {};
            this.getTargetBreakdown();
        },

        formatQuickTargetDisplay(value) {
            if (value === null || value === undefined || value === '') return '';
            const format = this.getTargetUnitFormat;
            if (format === 'currency') {
                return formatUtils.currency.format(value);
            }
            return value.toString();
        },

        handleQuickTargetInput(event) {
            const input = event.target;
            const cursorPosition = input.selectionStart;
            let value = event.target.value;
            const format = this.getTargetUnitFormat;

            if (format === 'currency') {
                const originalValue = value;
                const originalLength = value.length;

                // 1. Parse ("1.000,5" -> "1000.5")
                const parsedValue = formatUtils.currency.parse(value);
                this.quickTargetValue = parsedValue;

                // 2. Format display ("1000.5" -> "1.000,5")
                const formattedValue = formatUtils.currency.format(parsedValue);
                this.quickTargetFormatted = formattedValue;

                // 3. Atur cursor
                this.$nextTick(() => {
                    const newLength = formattedValue.length;
                    let newCursorPosition = cursorPosition + (newLength - originalLength);
                    if (originalLength > newLength) {
                        newCursorPosition = cursorPosition - (originalLength - newLength) + 1;
                    }
                    const dotsAdded =
                        (formattedValue.match(/\./g) || []).length - (originalValue.match(/\./g) || []).length;
                    if (dotsAdded > 0) newCursorPosition += dotsAdded;

                    input.setSelectionRange(newCursorPosition, newCursorPosition);
                });
            } else {
                // Percentage, decimal, number
                const parsedValue = formatUtils.parseNumeric(value);
                this.quickTargetValue = parsedValue;
                this.quickTargetFormatted = parsedValue;
            }
        },

        async fillingTarget() {
            this.activeKpi.targetSimpan = {};
            this.quickTargetValue = '';
            this.quickTargetFormatted = '';
            this.fillTargetData();
            this.showModalTarget = true;
        },

        triggerTarget() {
            this.quickTargetValue = '';
            this.quickTargetFormatted = '';
            this.showModalTarget = true;
        },

        applyQuickValueToAllPeriods() {
            if (this.quickTargetValue === '' || this.quickTargetValue === null) {
                this.showSimpleAlert('Masukkan nilai target terlebih dahulu', 'warning');
                return;
            }

            const val = this.getTargetUnitFormat === 'currency'
                ? parseFloat(this.quickTargetValue)
                : (parseFloat(this.quickTargetValue) || this.quickTargetValue);

            const updatedTarget = { ...this.activeKpi.targetSimpan };

            this.targetModalFields.forEach((field) => {
                if (field.required !== false && !field.disabledField) {
                    updatedTarget[field.name] = val;
                }
            });

            this.activeKpi.targetSimpan = updatedTarget;
            const displayVal = this.quickTargetFormatted || this.quickTargetValue;
            this.showSimpleAlert(`Nilai ${displayVal} diterapkan ke semua periode aktif`, 'success');
        },

        distributeQuickValueEvenly() {
            if (this.quickTargetValue === '' || this.quickTargetValue === null) {
                this.showSimpleAlert('Masukkan total nilai target terlebih dahulu', 'warning');
                return;
            }

            const totalVal = parseFloat(this.quickTargetValue);
            if (isNaN(totalVal) || totalVal <= 0) {
                this.showSimpleAlert('Total nilai target harus lebih dari 0', 'warning');
                return;
            }

            const activeFields = this.targetModalFields.filter((f) => f.required !== false && !f.disabledField);

            if (activeFields.length === 0) return;

            const perPeriodVal = Math.round((totalVal / activeFields.length) * 100) / 100;
            const updatedTarget = { ...this.activeKpi.targetSimpan };

            activeFields.forEach((field) => {
                updatedTarget[field.name] = perPeriodVal;
            });

            this.activeKpi.targetSimpan = updatedTarget;
            const formattedTotal = this.formatQuickTargetDisplay(totalVal);
            const formattedPerPeriod = this.formatQuickTargetDisplay(perPeriodVal);
            this.showSimpleAlert(`Total ${formattedTotal} dibagi rata ke ${activeFields.length} periode (@${formattedPerPeriod})`, 'success');
        },

        resetAllTargetPeriods() {
            const updatedTarget = { ...this.activeKpi.targetSimpan };
            this.targetModalFields.forEach((field) => {
                if (field.required !== false && !field.disabledField) {
                    updatedTarget[field.name] = '';
                }
            });
            this.activeKpi.targetSimpan = updatedTarget;
            this.quickTargetValue = '';
            this.quickTargetFormatted = '';
            this.showSimpleAlert('Nilai target seluruh periode telah direset', 'info');
        },

        closeModalTarget() {
            this.showModalTarget = false;
        },
        updateTargetValues(updatedTarget) {
            this.activeKpi.targetSimpan = { ...updatedTarget };
        },
        submitTarget() {
            // Validasi kelengkapan field
            const isTargetValid = this.validateTargetFields();
            if (!isTargetValid) {
                this.showSimpleAlert('Harap isi semua field target (nilai tidak boleh kosong)', 'warning');
                return;
            }

            // Validasi range jika formula punya range
            const rangeData = this.activeFormulaRangeData;
            if (rangeData) {
                const rMin = rangeData.Min_Range;
                const rMax = rangeData.Max_Range;
                const activeFields = this.targetModalFields.filter((f) => !f.disabledField);
                for (const field of activeFields) {
                    const val = parseFloat(this.activeKpi.targetSimpan?.[field.name]);
                    if (isNaN(val)) continue;
                    if (rMax !== null && rMax !== undefined && val > rMax) {
                        this.showSimpleAlert(
                            `Nilai "${field.label}" (${val}) melebihi batas maksimum yang diizinkan (${rMax})`,
                            'warning',
                        );
                        return;
                    }
                    if (rMin !== null && rMin !== undefined && val < rMin) {
                        this.showSimpleAlert(
                            `Nilai "${field.label}" (${val}) kurang dari batas minimum yang diizinkan (${rMin})`,
                            'warning',
                        );
                        return;
                    }
                }
            }

            this.showModalTarget = false;
            this.showSimpleAlert('Target berhasil disimpan', 'success');
        },

        scrollToNewCard() {
            this.$nextTick(() => {
                const container = document.querySelector('.cards-scroll-wrapper');
                if (container) {
                    container.scrollLeft = container.scrollWidth;
                }
            });
        },

        // switchToForm(index) {
        //     if (!this.isRevision(this.kpis[index]) && this.isEditForm && this.submissionStatus == 'rejected') {
        //         this.showSimpleAlert('KPI tersebut tidak diminta untuk direvisi');
        //         return;
        //     }

        //     if (index >= 0 && index < this.kpis.length) {
        //         this.activeFormIndex = index;
        //         this.showValidation = false;
        //     }
        // },

        switchToForm(index) {
            const safeIndex = Number(index);

            if (!Number.isInteger(safeIndex) || safeIndex < 0 || safeIndex >= this.kpis.length) {
                return;
            }

            // Saat revisi penolakan, SEMUA parameter boleh dibuka/direvisi — bukan hanya
            // yang ditolak. Parameter yang ditolak tetap ditandai (border merah) via kelas
            // 'revision', tapi tidak dikunci.

            this.activeFormIndex = safeIndex;
            this.showValidation = false;
        },

        truncateText(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        },

        updateActiveKpi(value) {
            this.kpis[this.activeFormIndex] = value;
        },

        SaveAndClose() {
            if (this.validateCurrentForm()) {
                this.closeForm();
                this.showSimpleAlert('KPI berhasil disimpan');
            }
        },

        closeForm() {
            this.activeFormIndex = null;
            this.showValidation = false;
        },

        SaveAndNew() {
            if (this.validateCurrentForm()) {
                this.addNewKpi();
            }
        },

        removeKpi(index) {
            if (this.submissionStatus == 'rejected') return;
            if (this.kpis.length > 1) {
                this.alertComponents = {
                    showAlertModal: true,
                    content: 'Kamu yakin untuk menghapus KPI ini?',
                    title: `Hapus KPI Ke-${index + 1}`,
                    submit: 'Hapus',
                    cancel: 'Batal',
                    type: 'danger',
                    onCancel: () => this.closeAlertModal(),
                    onSubmit: () => {
                        this.kpis.splice(index, 1);
                        if (this.activeFormIndex === index) {
                            this.activeFormIndex = Math.max(0, index - 1);
                        } else if (this.activeFormIndex > index) {
                            this.activeFormIndex--;
                        }
                        this.showSimpleAlert('KPI berhasil dihapus!');
                        this.closeAlertModal();
                    },
                };
            }
        },

        FormulaChange(event) {
            this.onFormulaChange(event);
        },

        validateCurrentForm() {
            if (this.approvers?.length == 0) {
                ElMessage.warning('Anda belum ada approver, Silahkan hubungi admin!');
                return;
            }
            this.validationErrors = [];
            const kpi = this.activeKpi;

            if (!kpi.parameter.trim()) {
                this.validationErrors.push('Nama parameter dibutuhkan');
            }
            if (!kpi.unit) {
                this.validationErrors.push('Satuan dibutuhkan');
            }
            if (!kpi.formula) {
                this.validationErrors.push('Formula dibutuhkan');
            }
            if (!kpi.target) {
                this.validationErrors.push('Target dibutuhkan');
            }
            if (!this.isTargetFull()) {
                this.validationErrors.push('Target belum terpenuhi');
            }
            if (!kpi.bscCategory) {
                this.validationErrors.push('BSC Category dibutuhkan');
            }
            if (!kpi.definition.trim()) {
                this.validationErrors.push('deskripsi dibutuhkan');
            }
            if (kpi.weight < 1 || kpi.weight > 100) {
                this.validationErrors.push('Weight harus dari 1 sampai 100');
            }
            this.showValidation = this.validationErrors.length > 0;
            if (this.validationErrors.length > 0) {
                this.showSimpleAlert('Tolong selesaikan error validasi!');
                return false;
            }
            return true;
        },

        validateCurrentFormSilent() {
            const kpi = this.kpis[this.activeFormIndex];
            return (
                kpi.parameter.trim() &&
                kpi.bscCategory &&
                kpi.definition.trim() &&
                kpi.weight >= 1 &&
                kpi.weight <= 100 &&
                kpi.target &&
                kpi.unit &&
                kpi.formula &&
                this.isTargetFull()
            );
        },

        validateAll() {
            if (this.approvers?.length == 0) {
                ElMessage.warning('Anda belum ada approver, Silahkan hubungi admin!');
                return;
            }
            let allValid = true;
            const kpiErrors = [];
            const errors = [];

            this.kpis.forEach((kpi, index) => {
                const kpiError = {
                    parameter: kpi.parameter.trim(),
                    errors: [],
                };

                if (!kpi.parameter.trim()) {
                    kpiError.errors.push('Nama parameter dibutuhkan');
                    allValid = false;
                }
                if (!kpi.bscCategory) {
                    kpiError.errors.push('BSC Category dibutuhkan');
                    allValid = false;
                }
                if (!kpi.definition.trim()) {
                    kpiError.errors.push('Definition dibutuhkan');
                    allValid = false;
                }
                if (kpi.weight < 1 || kpi.weight > 100) {
                    kpiError.errors.push('Weight harus dari 1 sampai 100');
                    allValid = false;
                }
                if (!kpi.unit) {
                    kpiError.errors.push('Satuan dibutuhkan');
                    allValid = false;
                }
                if (!kpi.formula) {
                    kpiError.errors.push('Formula dibutuhkan');
                    allValid = false;
                }
                if (!kpi.target) {
                    kpiError.errors.push('Target dibutuhkan');
                    allValid = false;
                }
                if (!this.isTargetFull(kpi)) {
                    kpiError.errors.push('Target belum terpenuhi');
                    allValid = false;
                }

                if (kpiError.errors.length > 0) {
                    kpiErrors.push(kpiError);
                }
            });

            let totalWeightError = '';
            if (this.totalWeight != 100) {
                totalWeightError = 'Total Weight harus 100%';
                allValid = false;
            }

            // Hitung total errors
            const totalErrors =
                kpiErrors.reduce((total, kpiError) => total + kpiError.errors.length, 0) + (totalWeightError ? 1 : 0);

            // Set validation results untuk modal
            this.validationResults = {
                allValid,
                kpiErrors,
                totalWeightError,
                totalErrors,
            };

            if (totalErrors == 0) {
                ElMessage.success('Seluruh form sudah valid!');
            } else {
                this.showValidationModal = true;
            }

            // Tampilkan modal

            return allValid;
        },

        async onFormulaChange(id) {
            const currentForm = this.kpis[this.activeFormIndex];
            const unitChoose = currentForm.unit || null;
            // console.log(id);
            // if (currentForm.formula === id) return;

            currentForm.formula = id;
            this.loading.units = true;

            if (!this.isEditForm) {
                currentForm.unit = '';
            }

            try {
                const response = await axios.get(`/api/v1/form-kpi/by/${id}`);
                const data = Array.isArray(response.data.result) ? response.data.result : [];

                currentForm.availableUnits = data.map((item) => ({
                    id: item.Id_Master_Satuan,
                    name: item.Simbol_Satuan,
                    Format_Input: item.Format_Input,
                }));
                currentForm.unit = currentForm.availableUnits.find((e) => e.id == unitChoose)?.id || null;
            } catch (error) {
                console.error('Error loading units:', error);
                currentForm.availableUnits = [];
            } finally {
                this.loading.units = false;
            }
        },
        isFormCompleted(kpi) {
            return (
                kpi.parameter &&
                kpi.bscCategory &&
                kpi.definition &&
                kpi.unit &&
                kpi.formula &&
                kpi.target &&
                this.isTargetFull(kpi) &&
                kpi.weight > 0
            );
        },

        calculateCompletion(kpi) {
            let completedFields = 0;
            const totalFields = 8;
            if (kpi.parameter) completedFields++;
            if (kpi.bscCategory) completedFields++;
            if (kpi.definition) completedFields++;
            if (kpi.unit) completedFields++;
            if (kpi.formula) completedFields++;
            if (kpi.target) completedFields++;
            if (this.isTargetFull(kpi)) completedFields++;
            if (kpi.weight > 0) completedFields++;
            return Math.round((completedFields / totalFields) * 100);
        },

        formatBscCategory(category) {
            // console.log(this.BscKategoriDatas, category);
            const data = this.BscKategoriDatas.find((i) => i.Id_Master_Bsc_Kategori === category);
            return data?.Nama_Kategori || 'Loading...';
        },

        getKpiIcon(category) {
            const icons = {
                financial: 'bi bi-currency-dollar',
                customer: 'bi bi-people',
                internal: 'bi bi-gear',
                learning: 'bi bi-graph-up',
            };
            return icons[category] || 'bi bi-file-text';
        },

        showSimpleAlert(message, parameter = 'warning') {
            ElMessage({
                type: parameter,
                message: message,
            });
        },

        submitAllKpis() {
            if (this.isEditForm) {
                if (!this.isKpisChanged()) {
                    this.showSimpleAlert('Tidak ada perubahan pada KPI !');
                    return;
                } else {
                    if (this.validateAll()) {
                        this.showConfirmationModal = true;
                    }
                }
            } else {
                if (this.validateAll()) {
                    this.showConfirmationModal = true;
                }
            }
        },

        async confirmSubmit() {
            this.loading.submitKpis = true;
            try {
                // console.log(this.no_transaksi_ref);
                const payload = {
                    kpis: this.kpis,
                    no_transaksi: this.no_transaksi_ref,
                };

                const response = await axios.post('/api/v1/form-kpi/store', payload);

                if (response.data.success) {
                    this.showSimpleAlert(response.data.message);
                    this.closeConfirmationModal();
                    this.checkFormStatus();
                } else {
                    throw new Error(response.data.message || 'Gagal menyimpan data');
                }
            } catch (error) {
                this.showSimpleAlert(error?.response?.data?.message || 'Terjadi kesalahan', 'error');
            } finally {
                this.loading.submitKpis = false;
            }
        },

        closeConfirmationModal() {
            this.showConfirmationModal = false;
        },

        closeAlertModal() {
            ((this.alertComponents.showAlertModal = false), (this.alertComponents.content = ''));
            this.alertComponents.title = '';
            this.alertComponents.submit = '';
            this.alertComponents.cancel = '';
            this.alertComponents.type = '';
            this.alertComponents.cancelFnc = () => {};
            this.alertComponents.submitFnc = () => {};
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },

        Resize() {
            this.$forceUpdate();
        },
    },
    async mounted() {
        await this.checkFormStatus();
        await this.fetchListData();
        window.addEventListener('resize', this.Resize);

        // this.statusRefreshInterval = setInterval(() => {
        //     if (this.isFormSubmitted && this.submissionStatus === "pending") {
        //         this.checkFormStatus();
        //     }
        // }, 30000);
    },

    beforeUnmount() {
        if (this.statusRefreshInterval) {
            clearInterval(this.statusRefreshInterval);
        }
        window.removeEventListener('resize', this.Resize);
    },
};
</script>

<style scoped>
/* ===== Banner alasan penolakan global (revisi form KPI) ===== */
.global-reject-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-left: 4px solid var(--hcis-red-500);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
}
.global-reject-banner > i {
    color: var(--hcis-red-500);
    font-size: 1.15rem;
    margin-top: 1px;
}
.global-reject-text {
    min-width: 0;
}
.global-reject-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-red-700);
    margin-bottom: 0.2rem;
}
.global-reject-value {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.55;
    color: var(--hcis-red-900);
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

/* CSS Variables */
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.vs__dropdown-option--disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.micro-approvers-section {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 8px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    max-height: 120px;
    overflow-y: auto;
}

.micro-section-header {
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    margin-bottom: 4px;
}

.micro-approver-icon {
    font-size: 0.8rem;
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
    width: 18px;
    height: 18px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.micro-section-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
}

.micro-approver-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.micro-approver-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 3px 0;
}

.micro-approver-avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.micro-approver-avatar i {
    font-size: 0.65rem;
    color: #fff;
}

.micro-approver-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.micro-approver-name {
    font-size: 0.7rem;
    font-weight: 500;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1;
}

.micro-badge {
    font-size: 0.6rem;
    padding: 1px 4px;
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    border-radius: 3px;
    font-weight: 500;
    line-height: 1;
}

/* Scrollbar styling yang lebih kecil */
.micro-approvers-section::-webkit-scrollbar {
    width: 3px;
}

.micro-approvers-section::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 1px;
}

.micro-approvers-section::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .micro-approvers-section {
        max-height: 100px;
        padding: 6px;
    }

    .micro-approver-item {
        padding: 2px 0;
    }

    .micro-approver-name {
        font-size: 0.65rem;
    }

    .micro-badge {
        font-size: 0.55rem;
    }
}

/* Animation untuk hover effect yang minimal */
.micro-approver-item {
    transition: background-color 0.15s ease;
    border-radius: 4px;
    padding-left: 4px;
    padding-right: 4px;
}

.micro-approver-item:hover {
    background: rgba(255, 255, 255, 0.05);
}
.approver-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: var(--primary-color);
}

.approver-info {
    flex-grow: 1;
}

.approver-name {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.approver-position {
    font-size: 0.8rem;
    opacity: 0.9;
}
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    color: #6c757d;
}

.empty-icon {
    width: 64px;
    height: 64px;
    background-color: #f8f9fa;
    border-radius: 50%;
    margin-bottom: 1rem;
}

.empty-icon i {
    font-size: 1.5rem;
    color: #adb5bd;
}

.empty-title {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
}

.empty-description {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Modern Header Styling */
.modern-header {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-bottom: 1rem;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--gradient-primary);
    z-index: 1;
}

.header-content {
    position: relative;
    z-index: 2;
    /* padding: 1rem; */
    color: white;
}

/* Header Info Section */
.header-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.icon-container {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.icon-container i {
    font-size: 1.5rem;
    color: white;
}

.text-content .title {
    font-size: 1.75rem;
    margin-bottom: 0.25rem;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.text-content .subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

/* Approvers Section */
.approvers-section {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    /* padding: 1.25rem; */
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.section-header {
    color: white;
    margin-bottom: 1rem;
}

.section-header i {
    font-size: 1.25rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: white;
}

/* Approver List */
.approver-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.approver-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.approver-item:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.approver-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.approver-avatar i {
    font-size: 1.25rem;
    color: white;
}

.approver-info {
    flex: 1;
    min-width: 0; /* Untuk mencegah overflow */
}

.approver-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.approver-position {
    font-size: 0.8rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-left: 0.5rem;
}

.status-indicator.available {
    background-color: var(--success);
}

.status-indicator.busy {
    background-color: var(--warning);
}

.status-indicator.offline {
    background-color: var(--secondary);
}

.approver-action {
    flex-shrink: 0;
}

.btn-action {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-action:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        padding: 1rem;
    }

    .header-info {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .text-content .title {
        font-size: 1.5rem;
    }

    .approvers-section {
        padding: 1rem;
    }

    .approver-item {
        padding: 0.6rem;
    }
}

@media (max-width: 576px) {
    .approver-item {
        flex-wrap: wrap;
    }

    .approver-info {
        min-width: 120px;
    }

    .approver-action {
        margin-left: auto;
    }
}

/* Animasi Fade In */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.total-value {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8rem;
}

.approvers-section {
    background-color: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    padding: 20px;
    margin: 10px 0;
    backdrop-filter: blur(5px);
}

.section-title {
    font-size: 1.2rem;
    margin-bottom: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 10px;
}

.approver-list {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.approver-item {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 10px 15px;
    display: flex;
    align-items: center;
    min-width: 200px;
    transition: all 0.3s ease;
}

.approver-item:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-3px);
}

.approver-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: var(--primary-color);
}

.approver-info {
    flex-grow: 1;
}

.approver-name {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.approver-position {
    font-size: 0.8rem;
    opacity: 0.9;
}

/* Target Summary */
.target-summary {
    background: var(--surface-soft);
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.summary-text {
    color: var(--text);
    font-weight: 500;
}

/* Target Form Section */
.target-form-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    animation: slideIn 0.3s ease;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-400));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.section-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0.25rem 0 0 0;
}

.target-fields-grid {
    margin-top: 1rem;
}

/* Quick Fill Panel in Target Modal */
.quick-fill-panel {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 1.25rem;
    transition: all 0.2s ease;
}

.quick-fill-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #edf2f7;
}

.quick-fill-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #fef3c7;
    color: #d97706;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.quick-fill-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
    display: block;
    line-height: 1.2;
}

.quick-fill-subtitle {
    font-size: 0.75rem;
    color: #64748b;
    display: block;
    margin-top: 2px;
}

.badge-unit-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.6rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.75rem;
    color: #475569;
}

.quick-fill-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.quick-fill-input-box {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    padding: 0 0.5rem;
    transition: all 0.2s ease;
    flex: 1 1 200px;
    max-width: 260px;
    min-height: 36px;
}

.quick-fill-input-box:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.quick-fill-input-box .input-icon {
    color: #94a3b8;
    font-size: 0.75rem;
    margin-right: 0.4rem;
}

.quick-fill-input-box .quick-input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1e293b;
    width: 100%;
    padding: 0.4rem 0;
}

.quick-fill-input-box .input-unit-tag {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    margin-left: 0.35rem;
    white-space: nowrap;
}

.quick-fill-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.btn-quick {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.42rem 0.85rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-quick:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-quick-primary {
    background: #6366f1;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.25);
}

.btn-quick-primary:hover:not(:disabled) {
    background: #4f46e5;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.35);
}

.btn-quick-secondary {
    background: #ffffff;
    color: #4f46e5;
    border-color: #c7d2fe;
}

.btn-quick-secondary:hover:not(:disabled) {
    background: #eef2ff;
    border-color: #a5b4fc;
}

.btn-quick-ghost {
    background: transparent;
    color: #64748b;
}

.btn-quick-ghost:hover:not(:disabled) {
    background: #f1f5f9;
    color: #dc2626;
}

.wrapper-button-footer {
    display: flex;
    justify-content: end;
    align-items: center;
    padding: 0;
}

/* Validation modal */

.kpi-validation-content {
    padding: 0;
}

.kpi-validation-header {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
}

.kpi-validation-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.kpi-validation-icon.success {
    background: var(--success);
    color: white;
}

.kpi-validation-icon.error {
    background: var(--warning);
    color: white;
}

.kpi-validation-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 8px 0;
}

.kpi-validation-subtitle {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
}

.kpi-validation-scroll-container {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.kpi-validation-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.kpi-validation-item {
    margin-bottom: 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.kpi-member-validation {
    padding: 16px;
}

.kpi-member-header {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.kpi-member-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary-dark);
    font-size: 16px;
    margin-right: 12px;
}

.kpi-member-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.kpi-error-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.kpi-error-item {
    display: flex;
    align-items: flex-start;
    padding: 8px 0;
    border-bottom: 1px solid var(--surface-soft);
}

.kpi-error-item:last-child {
    border-bottom: none;
}

.kpi-error-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--danger);
    color: white;
    font-size: 12px;
    margin-right: 12px;
    flex-shrink: 0;
    margin-top: 1px;
}

.kpi-error-text {
    font-size: 14px;
    color: var(--text);
    line-height: 1.4;
}

.kpi-validation-summary {
    padding: 16px;
    background: var(--surface-soft);
    border-radius: 8px;
    text-align: center;
}

.kpi-summary-text {
    font-size: 14px;
    color: var(--text);
    margin: 0 0 8px 0;
}

.kpi-summary-text:last-child {
    margin-bottom: 0;
}

.kpi-modal-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.kpi-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.kpi-btn-primary {
    background: var(--primary);
    color: white;
}

.kpi-btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Scrollbar styling */
.kpi-validation-scroll-container::-webkit-scrollbar {
    width: 6px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 3px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 3px;
}

.kpi-validation-scroll-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Header Info */
.preview-header {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
}

.header-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-weight: 500;
}

.info-value {
    font-size: 1rem;
    color: var(--text);
    font-weight: 600;
}

.info-value.highlight {
    color: var(--primary);
    font-size: 1.125rem;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border);
}

.section-icon {
    width: 40px;
    height: 40px;
    background: var(--gradient-primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
}

.section-text {
    flex: 1;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.section-subtitle {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0;
}

/* Grid Layouts */
.monthly-target-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.quarterly-target-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.custom-target-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Card Styles */
.month-card,
.quarter-card,
.custom-target-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
}

.month-card:hover,
.quarter-card:hover,
.custom-target-item:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.month-header,
.quarter-header,
.custom-item-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.month-name,
.quarter-name,
.item-name {
    font-weight: 600;
    color: var(--text);
    font-size: 1rem;
}

.month-period,
.quarter-period,
.item-period {
    font-size: 0.875rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

/* Value Styles */
.month-values,
.quarter-values,
.custom-item-values {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

.target-value,
.actual-value,
.achievement-rate {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.value-label,
.rate-label {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.value-number,
.rate-value {
    font-weight: 600;
    font-size: 1rem;
}

/* Yearly Target */
.yearly-target-single {
    display: flex;
    justify-content: center;
}

.year-card {
    background: var(--surface);
    border: 2px solid var(--primary-light);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    max-width: 400px;
    width: 100%;
    box-shadow: var(--shadow-lg);
}

.year-header {
    margin-bottom: 1.5rem;
}

.year-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    display: block;
    margin-bottom: 0.5rem;
}

.year-period {
    font-size: 1rem;
    color: var(--text-muted);
}

.year-values {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.target-value-large,
.actual-value-large,
.achievement-rate-large {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}

.target-value-large .value-number,
.actual-value-large .value-number {
    font-size: 1.25rem;
    font-weight: 700;
}

.achievement-rate-large .rate-value {
    font-size: 1.125rem;
    font-weight: 700;
}

/* Performance Summary */
.performance-summary {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
    border: 1px solid var(--border);
}

.summary-header {
    margin-bottom: 1.5rem;
    text-align: center;
}

.summary-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
}

.summary-icon.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.summary-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.summary-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.summary-icon.primary {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
}

.summary-label {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Footer Actions */
.preview-footer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Text Colors */
.text-success {
    color: var(--success);
}
.text-warning {
    color: var(--warning);
}
.text-danger {
    color: var(--danger);
}
.text-muted {
    color: var(--text-muted);
}
.text-primary {
    color: var(--primary);
}

/* Loading & Empty States */
.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    text-align: center;
}

.loading-animation {
    margin-bottom: 1rem;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid var(--border);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.loading-dots {
    display: flex;
    gap: 0.25rem;
    justify-content: center;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: bounce 1.4s infinite ease-in-out;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.empty-icon,
.instruction-icon {
    width: 80px;
    height: 80px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    color: var(--text-muted);
    font-size: 2rem;
}

.empty-title,
.instruction-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.empty-text,
.instruction-text,
.loading-text {
    color: var(--text-muted);
    margin: 0;
}

/* Animations */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .summary-info {
        flex-direction: column;
        padding: 0rem;
    }

    .target-summary {
        padding: 0.3rem;
    }
    .monthly-target-grid {
        grid-template-columns: 1fr;
    }

    .wrapper-button-footer {
        padding: 1rem 0rem;
    }

    .quarterly-target-grid {
        grid-template-columns: 1fr;
    }

    .header-info {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .preview-footer-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .preview-footer-actions button {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .month-card,
    .quarter-card {
        padding: 1rem;
    }

    .year-card {
        padding: 1.5rem;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }
}

/* Loading Animation */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
}

.loading-animation {
    position: relative;
    margin-bottom: 1.5rem;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid var(--border);
    /* border-top: 3px solid var(--primary); */
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-dots {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background: var(--primary);
    border-radius: 50%;
    animation: bounce 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.loading-text {
    color: var(--text-muted);
    font-size: 1.1rem;
    margin: 0;
}

/* target */

/* CSS untuk target form dalam modal */
.compact-target-form .compact-form-section {
    padding: 0;
    margin-top: 0;
    box-shadow: none;
    background: transparent;
}

.compact-target-form .optimized-form-grid {
    gap: 0.75rem;
}

.compact-target-form .form-group-optimized {
    margin-bottom: 0;
}

.compact-target-form .form-label-optimized {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.compact-target-form .form-input-optimized {
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
}

/* Responsive untuk monthly targets */
@media (max-width: 768px) {
    .compact-target-form .form-label-optimized {
        font-size: 0.75rem;
    }

    .compact-target-form .form-input-optimized {
        padding: 0.5rem 0.6rem;
        font-size: 0.8rem;
    }
}

/* akhir target */

/* Submission Layout Styles */
.submission-layout {
    min-height: 600px;
}

.mobileStatus {
    width: fit-content !important;
    /* background: none !important; */
    /* color: var(--danger) !important; */
    padding: 0 1rem !important;
}

.sticky-kpi-sidebar {
    position: sticky;
    top: 1rem;
    height: fit-content;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

.submission-content {
    height: 100%;
    min-height: 600px;
}

.mobile-submission-layout {
    display: flex;
    flex-direction: column;
}

/* Compact KPI List Adjustments untuk sticky */
.compact-kpi-list {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .compact-kpi-list {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }

    .submission-actions-compact {
        margin-bottom: 0.75rem;
    }

    .approval-notes {
        margin-bottom: 0.75rem;
    }
}

/* Scrollbar styling untuk sticky sidebar */
.sticky-kpi-sidebar::-webkit-scrollbar {
    width: 4px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 2px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 2px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}
/* akhir form submitted */

.modern-stepper-container {
    margin-bottom: 0 !important;
}

/* Optional: Styling tambahan untuk stepper */
.stepper-container {
    margin-bottom: 2rem;
}

/* KPI Detail Modal */
.kpi-detail-content {
    padding: 0.5rem;
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-row {
    display: flex;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.75rem;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: var(--dark);
    width: 120px;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.detail-value {
    flex: 1;
    color: var(--text);
    font-size: 0.9rem;
}

/* Status Header */
.status-header {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.status-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    opacity: 0.95;
}

.badge-primary {
    background: var(--primary);
}
.badge-success {
    background: var(--success);
}

.badge-danger {
    border: 1px solid #ff6b35;
    background: var(--danger);
    box-shadow:
        0 0 10px rgba(255, 107, 53, 0.3),
        inset 0 0 0 1px rgba(255, 107, 53, 0.1);
    animation: pulse-revision 2s infinite;
}

@keyframes pulse-revision {
    0%,
    100% {
        box-shadow: 0 0 10px rgba(255, 107, 53, 0.3);
    }
    50% {
        box-shadow: 0 0 15px rgba(255, 107, 53, 0.6);
    }
}

.status-pending .status-background {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
}

.status-approved .status-background {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
}

.status-rejected .status-background {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
}

.status-content {
    position: relative;
    z-index: 2;
    padding: 1.5rem;
    color: white;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-info .icon-container {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.status-actions {
    display: flex;
    justify-content: flex-end;
}

.approval-info,
.submission-info {
    margin-top: 0.5rem;
    opacity: 0.9;
}

/* Compact KPI List */
.compact-kpi-list {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.section-header-compact {
    margin-bottom: 1.5rem;
}

.section-header-compact .section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.section-header-compact .section-subtitle {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.kpi-grid {
    width: 100%;
}

.kpi-card-compact {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    min-width: 15rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.kpi-card-compact.revision {
    border: 2px solid #ff6b35;
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff8f6 100%);
    box-shadow: 0 0 0 1px #ff6b35 inset;
    position: relative;
}

.kpi-card-compact.revision::before {
    content: '⚠️';
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.kpi-card-compact:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.kpi-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}

.kpi-number {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
}
.kpi-number.disabled {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    opacity: 30%;
    cursor: not-allowed;
}

.kpi-card-body {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.kpi-icon-compact {
    width: 46px;
    height: 46px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1rem;
    flex-shrink: 0;
}

.kpi-content {
    flex: 1;
    min-width: 0;
}

.kpi-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta-compact {
    display: flex;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.category-badge {
    background: var(--surface);
    color: var(--text-muted);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.weight-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.kpi-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.completion-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.completion-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--warning);
}

.completion-dot.completed {
    background: var(--success);
}

/* Compact Action Buttons */
.submission-actions-compact {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.btn-action-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-action-compact.primary {
    background: var(--primary);
    color: white;
}

.btn-action-compact.secondary {
    background: var(--surface-soft);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-action-compact:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Approval Notes */
.approval-notes {
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 1.25rem;
    border-left: 4px solid var(--info);
}

.notes-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--dark);
}

.notes-header i {
    color: var(--info);
}

.notes-content {
    color: var(--text);
    line-height: 1.5;
    font-size: 0.9rem;
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Empty KPI State */
.empty-kpi-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.empty-kpi-state .empty-icon {
    width: 80px;
    height: 80px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    font-size: 2rem;
}

.empty-kpi-state .empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.empty-kpi-state .empty-description {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

/* Section Header Compact dengan Badge */
.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.section-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* No Schedule State */
.empty-state-schedule {
    background: var(--surface);
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.empty-icon-container {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--warning), var(--hcis-amber-400));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    color: white;
    font-size: 2.5rem;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
}

.empty-description {
    color: var(--text-muted);
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.formula-range-info {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 0.85rem;
    color: #1d4ed8;
    flex-wrap: wrap;
}

/* modal target */
.modal-target-content {
    padding: 0.5rem;
}

.target-scale-section {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border);
}

.label-icon-container {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.label-text {
    flex: 1;
}

.label-title {
    display: block;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.95rem;
}

.label-subtitle {
    display: block;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.form-label-optimized {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem !important;
}

:deep(.target-scale-select .vs__dropdown-toggle) {
    padding: 0.75rem !important;
    border: 2px solid var(--border) !important;
    border-radius: 10px !important;
    background: var(--surface);
    transition: all 0.3s ease;
}

/* Menggunakan class 'vs--disabled' pada kontainer terluar */
:deep(.target-scale-select.vs--disabled .vs__dropdown-toggle) {
    background: #f8f8f8 !important; /* Gunakan !important jika diperlukan */
    /* Opsional: Terapkan gaya kursor non-aktif */
    pointer-events: none;
    cursor: not-allowed !important;
    cursor: not-allowed;
}

:deep(.target-scale-select .vs__dropdown-toggle:focus-within) {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

:deep(.target-scale-select .vs__selected) {
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
    border: none !important;
    border-radius: 6px !important;
    font-weight: 500;
}

/* Target Form Section */
.target-form-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    animation: slideIn 0.3s ease;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-400));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.section-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0.25rem 0 0 0;
}

.target-fields-grid {
    margin-top: 1rem;
}

:deep(.compact-form .form-group) {
    margin-bottom: 1rem;
}

:deep(.compact-form .form-label) {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

:deep(.compact-form .form-control) {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

:deep(.compact-form .form-control:focus) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 2px dashed var(--border);
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.empty-text {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.95rem;
}

/* Instruction State */
.instruction-state {
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 2px dashed var(--border);
}

.instruction-icon {
    font-size: 3rem;
    color: var(--primary);
    /* margin-bottom: 1rem; */
    opacity: 0.7;
}

.instruction-title {
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.instruction-text {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

/* General Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white !important;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    min-height: 140px;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gradient-primary);
    opacity: 0.95;
}

.header-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');
    animation: float 20s ease-in-out infinite;
}

.header-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: white;
}

.header-info {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 1.5rem;
}

.icon-container {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.icon-container i {
    font-size: 2.2rem;
    z-index: 2;
}

.title {
    font-size: 2rem;
    font-weight: 400;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
}

.subtitle {
    text-align: start;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
    font-weight: 400;
}

.header-actions {
    height: 100%;
}

.date-range {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.date-item {
    text-align: center;
    color: white;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: var(--text);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Compact Dashboard Styles */
.compact-dashboard {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 0.3rem 1rem;
    margin-bottom: 1rem;
}

/* Horizontal Scroll Cards */
.cards-scroll-container {
    position: relative;
    margin-top: 1rem;
}

.cards-scroll-wrapper {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding-bottom: 1rem;
    padding-top: 0.4rem;
}
/* Compact Card Base */
.compact-card {
    min-width: 260px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    flex-shrink: 0;
}

/* Active State */
.compact-card.active {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--surface) 0%, var(--hcis-slate-50) 100%);
}

/* Completed State - hanya apply jika bukan revision */
.compact-card.completed {
    border-left: 4px solid var(--success);
}

/* Hover Effect untuk kartu normal */
.compact-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

/* ========== REVISION STATE (Prioritas Tinggi) ========== */
.compact-card.revision {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff8f6 100%);
    border: 2px solid #ff6b35;
    box-shadow:
        0 0 0 1px #ff6b35 inset,
        0 4px 12px rgba(255, 107, 53, 0.15);
    animation: pulse-revision 2s ease-in-out infinite;

    /* Pastikan revision tetap interaktif */
    cursor: pointer;
    pointer-events: auto;
    opacity: 1;
    filter: none;
}

/* Badge/indicator revision */
.compact-card.revision::before {
    content: '⚠';
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    z-index: 10;
}

/* Pulse animation untuk revision */
@keyframes pulse-revision {
    0%,
    100% {
        box-shadow:
            0 0 0 1px #ff6b35 inset,
            0 4px 12px rgba(255, 107, 53, 0.15);
    }
    50% {
        box-shadow:
            0 0 0 1px #ff6b35 inset,
            0 4px 20px rgba(255, 107, 53, 0.3);
    }
}

/* Hover effect untuk revision */
.compact-card.revision:hover {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff1eb 100%);
    border-color: #ff5722;
    transform: translateY(-2px);
    box-shadow:
        0 0 0 1px #ff5722 inset,
        0 6px 16px rgba(255, 87, 34, 0.25);
}

/* Active state untuk revision */
.compact-card.revision:active {
    transform: translateY(0);
    box-shadow:
        0 0 0 1px #ff6b35 inset,
        0 2px 8px rgba(255, 107, 53, 0.2);
}

/* ========== DISABLED STATE (hanya apply jika BUKAN revision) ========== */
.compact-card.disabled:not(.revision) {
    background: var(--surface-disabled, #f5f5f5);
    border: 2px solid var(--border-disabled, #e0e0e0);
    border-radius: 12px;
    padding: 1rem;
    position: relative;
    flex-shrink: 0;

    /* Non-interactive state */
    cursor: not-allowed;
    pointer-events: none;

    /* Visual disabled effect */
    opacity: 0.6;
    filter: grayscale(0.3);

    /* Remove hover/focus transitions */
    transition: none;
}

/* Override hover untuk disabled */
.compact-card.disabled:not(.revision):hover,
.compact-card.disabled:not(.revision):focus {
    background: var(--surface-disabled, #f5f5f5);
    border-color: var(--border-disabled, #e0e0e0);
    transform: none;
    box-shadow: none;
}

/* Text styling untuk disabled */
.compact-card.disabled:not(.revision) .card-content {
    color: var(--text-disabled, #9e9e9e);
}

/* Overlay disabled icon */
.compact-card.disabled:not(.revision)::after {
    content: '🚫';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    opacity: 0.8;
    z-index: 2;
}

/* ========== ADD NEW CARD ========== */
.compact-card.add-new {
    background: linear-gradient(135deg, var(--surface-soft) 0%, var(--hcis-slate-100) 100%);
    border: 2px dashed var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}
.compact-card.add-new:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.card-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.card-actions {
    opacity: 1;
    transition: opacity 0.3s ease;
}

.btn-remove {
    background: var(--danger);
    color: white;
    border: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    cursor: pointer;
}

.card-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.add-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.kpi-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.kpi-info {
    flex: 1;
    min-width: 0;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.category {
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
}

.weight {
    color: var(--primary);
    font-weight: 600;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.progress-container {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 2px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    min-width: 30px;
}

.card-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
    margin-top: 0.5rem;
}

/* Bottom Action Bar */
.bottom-action-bar-integrated {
    position: sticky;
    bottom: 0;
    border-radius: 12px;
    background: var(--surface);
    border-top: 1px solid var(--border);
    padding: 1rem;
    margin-top: 1rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.action-content-integrated {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    flex-wrap: nowrap;
}

.action-info {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
}

.info-text {
    font-weight: 600;
    color: var(--dark);
    white-space: nowrap;
}

.weight-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    white-space: nowrap;
}

.weight-info.danger {
    color: var(--surface);
    background: var(--danger);
}

.action-buttons-integrated {
    display: flex;
    gap: 0.75rem;
    flex-wrap: nowrap;
}

.btn-action-integrated {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.btn-action-integrated.validate {
    background: var(--warning);
    color: white;
}

.btn-action-integrated.submit {
    background: var(--success);
    color: white;
}

.btn-action-integrated:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Modal Styles */
.delete-modal-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    text-align: center;
}

.warning-icon {
    font-size: 2rem;
    color: var(--warning);
}

.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.btn-secondary {
    background: var(--secondary);
    color: var(--text-muted);
}

.btn-success {
    background: var(--success);
    color: white;
}

/* Responsive Design */
@media (max-widt: 920px) {
    section-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .form-label-optimized {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .empty-state,
    .instruction-state {
        padding: 2rem 1rem;
    }
}
@media (max-width: 768px) {
    .detail-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-label {
        width: 100%;
    }

    .modern-header {
        min-height: 120px;
    }

    .header-content {
        padding: 1rem;
    }

    .header-info {
        gap: 0.75rem;
    }

    .icon-container {
        width: 50px;
        height: 50px;
    }

    .icon-container i {
        font-size: 1.5rem;
    }

    .title {
        font-size: 1.25rem;
    }

    .subtitle {
        font-size: 0.9rem;
    }

    .header-actions {
        flex-direction: row;
        justify-content: center;
        flex-wrap: wrap;
    }

    .date-range {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .btn-modern {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    .action-content-integrated {
        flex-direction: row;
        gap: 1rem;
    }

    .action-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .action-buttons-integrated {
        flex: 1;
        justify-content: flex-end;
    }

    .btn-action-integrated {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    .modal-target-content {
        padding: 0.25rem;
    }

    .target-scale-section {
        padding: 1rem;
    }

    .target-form-section {
        padding: 1rem;
    }

    /* .section-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .form-label-optimized {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .empty-state,
    .instruction-state {
        padding: 2rem 1rem;
    } */
}

@media (max-width: 576px) {
    .header-content {
        padding: 1rem;
    }

    .title {
        font-size: 1.1rem;
    }

    .subtitle {
        font-size: 0.85rem;
    }

    .header-actions {
        flex-direction: column;
        width: 100%;
    }

    .date-range,
    .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .action-content-integrated {
        flex-direction: column;
        gap: 0.75rem;
        text-align: center;
    }

    .action-info {
        flex-direction: row;
        justify-content: center;
        width: 100%;
    }

    .action-buttons-integrated {
        width: 100%;
        justify-content: center;
    }

    .btn-action-integrated {
        flex: 1;
        justify-content: center;
    }
}

/* Animations */
@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.compact-card {
    opacity: 0;
    animation: slideIn 0.2s ease forwards;
    will-change: transform, opacity;
}

.compact-card:nth-child(odd) {
    animation-delay: 0.1s;
}

.compact-card:nth-child(even) {
    animation-delay: 0.2s;
}

:deep(.vs__dropdown-toggle) {
    padding: 0.62rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
}

:deep(.v-select .vs__dropdown-toggle),
:deep(.v-select .vs__selected),
:deep(.v-select .vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
}

/* Grid layout untuk monthly targets */
:deep(.target-fields-grid .row) {
    margin: -0.5rem;
}

:deep(.target-fields-grid .col-6) {
    padding: 0.5rem;
}

:deep(.target-fields-grid .form-control) {
    background: var(--surface);
}
</style>
