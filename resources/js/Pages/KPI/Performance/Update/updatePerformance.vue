<template>
    <div class="container-fluid px-0">
        <HeaderKpi
            title="Update Performance KPI"
            subtitle="Ikuti langkah-langkah untuk memperbarui
                                  indikator kinerja Anda."
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />
        <div class="enterprise-container">
            <div class="enterprise-body">
                <div class="modern-stepper-container">
                    <Stepper
                        :steps="steps"
                        v-model:currentStep="currentStep"
                        :is-completed="isFinalized"
                        line-margin="10%"
                    />
                </div>

                <div class="content-card mb-3">
                    <input
                        type="file"
                        ref="itemFileInput"
                        @change="handleItemFileSelect"
                        hidden
                        accept=".jpg, .jpeg, .png, .pdf, .xlsx, .docx, .ppt, .pptx, .zip"
                    />
                    <div class="col-12">
                        <DynamicAlert
                            v-if="isFinalized && isResubmission"
                            variant="info"
                            title="Data Telah Diajukan Ulang"
                            description="Data revisi Anda telah berhasil dikirim dan sedang menunggu persetujuan. Anda hanya dapat melihat rincian data yang telah diajukan."
                            class="mb-4"
                        />

                        <DynamicAlert
                            v-else-if="isFinalized && statusPengajuan === 'menunggu'"
                            variant="info"
                            title="Data Menunggu Persetujuan"
                            description="Data Anda telah berhasil dikirim dan sedang menunggu persetujuan. Anda hanya dapat melihat rincian data yang telah diajukan."
                            class="mb-4"
                        />

                        <DynamicAlert
                            v-else-if="isFinalized && statusPengajuan === 'disetujui'"
                            variant="success"
                            title="Data Telah Disetujui"
                            description="Pengajuan KPI Anda telah disetujui dan tidak dapat diubah kembali. Anda hanya dapat melihat rincian data."
                            class="mb-4"
                        />

                        <AlertWarning
                            v-if="!isFinalized && isRejected"
                            title="Perlu Revisi"
                            description="Pengajuan KPI Anda sebelumnya telah ditolak. Mohon periksa komentar yang diberikan dan lakukan perbaikan yang diperlukan untuk pengajuan ulang."
                            icon="bi bi-exclamation-triangle-fill"
                            :reason="rejectionGlobalReason"
                        />
                    </div>
                    <SkeletonTable v-if="loading.kpiItems" />
                    <div v-else>
                        <div
                            v-if="!kpiItems.length"
                            class="d-flex flex-column align-items-center justify-content-center text-center py-5"
                        >
                            <transition name="fade">
                                <DotLottieVue
                                    style="height: 100px; width: 100px"
                                    autoplay
                                    loop
                                    src="/animation/empty-data.json"
                                />
                            </transition>
                            <p class="mt-3 mb-0 fw-bold">Data Tidak Ditemukan!</p>
                        </div>
                        <div v-else>
                            <transition name="fade-step" mode="out-in">
                                <div v-if="currentStep === 1" key="step1">
                                    <div class="step-content-header">
                                        <div>
                                            <h3>
                                                Langkah 1: Update Performance
                                                <span>
                                                    <el-tag class="custom-tag"
                                                        >Periode {{ headersUpdatePerformance.periode ?? '' }}</el-tag
                                                    >
                                                </span>
                                            </h3>
                                            <p>
                                                Masukkan nilai aktual dan unggah bukti pendukung untuk setiap parameter.
                                            </p>
                                        </div>
                                        <span class="step-badge">Langkah 1 dari {{ totalSteps }}</span>
                                    </div>
                                    <div class="table-responsive d-evo">
                                        <table class="table kpi-table" v-if="kpiItems.length > 0">
                                            <thead>
                                                <tr>
                                                    <th class="parameter-cell">Parameter</th>
                                                    <th class="bsc-col text-center">BSC</th>
                                                    <th class="weight-col text-center">Weight</th>
                                                    <th>Target</th>
                                                    <th class="actual-col-header text-center">Actual</th>

                                                    <th class="score-col text-center">Score</th>
                                                    <th class="achievement-col text-center">Achievement</th>
                                                    <th class="text-center">
                                                        Data Source
                                                        <div class="text-muted" style="font-size: 0.6rem">Max. 3MB</div>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template v-for="item in kpiItems" :key="item.id">
                                                    <tr
                                                        :class="{
                                                            'table-danger': item.isRejected,
                                                            'kpi-inactive-period': !item.is_input_period,
                                                        }"
                                                        :title="
                                                            !item.is_input_period
                                                                ? `Periode pengisian: ${item.periode}`
                                                                : ''
                                                        "
                                                    >
                                                        <td class="parameter-cell">
                                                            <div>
                                                                <small
                                                                    v-if="item.isRejected && item.alasan_penolakan"
                                                                    class="text-icontolak"
                                                                >
                                                                    <i class="bi bi-exclamation-triangle-fill"></i
                                                                ></small>
                                                                {{ item.parameter }}
                                                            </div>
                                                            <p
                                                                v-if="item.isRejected && item.alasan_penolakan"
                                                                class="text-penolakan text-danger mt-2"
                                                            >
                                                                Ditolak karena:
                                                                <strong>{{ item.alasan_penolakan }}</strong>
                                                            </p>
                                                        </td>
                                                        <td class="bsc-col text-center">
                                                            <el-tag class="custom-tag">{{ item.bsc }}</el-tag>
                                                        </td>
                                                        <td class="text-center">{{ item.weight }}%</td>
                                                        <td
                                                            v-html="
                                                                item.targetDescription !== null
                                                                    ? formatValueWithUnit(
                                                                          item.targetDescription,
                                                                          item.targetUnit,
                                                                      )
                                                                    : 'N/A'
                                                            "
                                                        ></td>

                                                        <td class="actual-col">
                                                            <div v-if="item.is_input_period" class="input-group-actual">
                                                                <input
                                                                    v-if="isRupiah(item.targetUnit)"
                                                                    type="text"
                                                                    class="form-control-enterprise currency-input"
                                                                    :value="formatCurrency(item.actual)"
                                                                    @input="handleCurrencyInput($event, item)"
                                                                    :placeholder="item.targetUnit"
                                                                    :disabled="isFinalized"
                                                                    :title="formatCurrency(item.actual)"
                                                                    @keypress="allowNumericOnly($event)"
                                                                />
                                                                <input
                                                                    v-else
                                                                    type="number"
                                                                    class="form-control-enterprise"
                                                                    v-model.number="item.actual"
                                                                    :placeholder="item.targetUnit"
                                                                    @input="updateKpiMetrics(item)"
                                                                    :disabled="isFinalized"
                                                                    @keypress="allowNumericOnly($event)"
                                                                />
                                                            </div>
                                                            <div v-else>
                                                                <span class="badge-not-active">
                                                                    <i class="bi bi-clock-history me-1"></i>
                                                                    Periode KPI belum aktif
                                                                </span>
                                                            </div>
                                                        </td>

                                                        <td class="achievement-col">
                                                            <small
                                                                class="badge"
                                                                :style="getAchievementBadge(item.achievement)"
                                                            >
                                                                {{ formatAchievement(item.achievement) }}%
                                                            </small>
                                                        </td>
                                                        <td class="score-col">
                                                            <span class="score-value"
                                                                >{{ formatAchievement(item.score) }}%</span
                                                            >
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="data-source-cell" v-if="item.is_input_period">
                                                                <div
                                                                    class="data-source-actions"
                                                                    v-if="item.dataSourceFile"
                                                                >
                                                                    <button
                                                                        @click="previewFile(item.dataSourceFile)"
                                                                        class="btn-action-icon btn-preview"
                                                                        title="Preview File"
                                                                    >
                                                                        <i class="bi bi-eye-fill"></i>
                                                                    </button>
                                                                    <button
                                                                        v-if="!isFinalized"
                                                                        @click="removeItemFile(item)"
                                                                        class="btn-action-icon btn-remove"
                                                                        title="Remove File"
                                                                    >
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                </div>
                                                                <button
                                                                    v-else
                                                                    @click="triggerItemFileUpload(item)"
                                                                    class="btn-upload-source"
                                                                    title="Upload Bukti Dukung"
                                                                    :disabled="isFinalized"
                                                                >
                                                                    <i
                                                                        class="bi bi-cloud-arrow-up-fill mt-customByFransIcon"
                                                                    ></i>
                                                                    <small>Upload</small>
                                                                </button>
                                                            </div>
                                                            <div v-else>
                                                                <span class="text-muted small"
                                                                    >Periode KPI belum aktif</span
                                                                >
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="kpi-list-mobile d-lg-none">
                                        <template v-if="loading.kpiItems">
                                            <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                        </template>
                                        <div
                                            v-else-if="kpiItems.length > 0"
                                            v-for="item in kpiItems"
                                            :key="item.id"
                                            class="kpi-card"
                                            :class="{
                                                'kpi-card-reject': item.isRejected,
                                                'kpi-inactive-period': !item.is_input_period,
                                            }"
                                            :title="
                                                !item.is_input_period
                                                    ? `Periode: ${formatDateString(
                                                          item.start_date,
                                                      )} - ${formatDateString(item.end_date)}`
                                                    : ''
                                            "
                                        >
                                            <div
                                                class="kpi-card-header"
                                                :class="item.isRejected ? 'kpi-card-header-reject' : ''"
                                            >
                                                <h5 class="kpi-parameter-title">
                                                    {{ item.parameter }}
                                                </h5>
                                                <span class="kpi-weight-badge">Weight: {{ item.weight }}%</span>
                                            </div>

                                            <div class="kpi-card-body">
                                                <div class="mb-0">
                                                    <div
                                                        v-if="item.isRejected && item.alasan_penolakan"
                                                        class="mt-3 p-2 rounded bg-rejected text-danger fw-bold"
                                                    >
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                        Alasan Penolakan:
                                                        {{ item.alasan_penolakan }}
                                                    </div>
                                                </div>
                                                <div class="mb-0">
                                                    <el-tag class="custom-tag">
                                                        {{ item.bsc }}
                                                    </el-tag>
                                                </div>
                                                <div class="kpi-input-section">
                                                    <label :for="'actual-' + item.id">Actual</label>
                                                    <div v-if="item.is_input_period" class="input-group-actual">
                                                        <input
                                                            v-if="isRupiah(item.targetUnit)"
                                                            type="text"
                                                            class="form-control-enterprise currency-input"
                                                            :value="formatCurrency(item.actual)"
                                                            @input="handleCurrencyInput($event, item)"
                                                            :placeholder="item.targetUnit"
                                                            :disabled="isFinalized"
                                                            :title="formatCurrency(item.actual)"
                                                            @keypress="allowNumericOnly($event)"
                                                        />
                                                        <input
                                                            v-else
                                                            type="number"
                                                            class="form-control-enterprise"
                                                            v-model.number="item.actual"
                                                            :placeholder="item.targetUnit"
                                                            @input="updateKpiMetrics(item)"
                                                            :disabled="isFinalized"
                                                            @keypress="allowNumericOnly($event)"
                                                        />
                                                    </div>
                                                    <div v-else>
                                                        <span class="badge-not-active">
                                                            <i class="bi bi-clock-history me-1"></i>
                                                            Periode KPI belum aktif
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="kpi-details-grid">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Target</span>
                                                        <p
                                                            class="detail-value"
                                                            v-html="
                                                                formatValueWithUnit(
                                                                    item.targetDescription,
                                                                    item.targetUnit,
                                                                )
                                                            "
                                                        ></p>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Score</span>
                                                        <p class="detail-value">
                                                            <span
                                                                class="badge"
                                                                :style="getAchievementBadge(item.achievement)"
                                                                >{{ formatAchievement(item.achievement) }}%</span
                                                            >
                                                        </p>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Achievment</span>
                                                        <p class="detail-value score-value">
                                                            {{ formatAchievement(item.score) }}%
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="kpi-evidence-section">
                                                    <label>Data Source</label>
                                                    <div v-if="item.is_input_period">
                                                        <div class="data-source-actions" v-if="item.dataSourceFile">
                                                            <span class="file-chip">
                                                                <i class="bi bi-paperclip mt-customByFransIcon"></i>
                                                                {{ item.dataSourceFile.path }}
                                                            </span>
                                                            <button
                                                                @click="previewFile(item.dataSourceFile)"
                                                                class="btn-action-icon btn-preview"
                                                                title="Preview File"
                                                            >
                                                                <i class="bi bi-eye-fill mt-customByFransIcon"></i>
                                                            </button>
                                                            <button
                                                                @click="removeItemFile(item)"
                                                                class="btn-action-icon btn-remove"
                                                                title="Remove File"
                                                                v-if="!isFinalized"
                                                            >
                                                                <i class="bi bi-x-lg mt-customByFransIcon"></i>
                                                            </button>
                                                        </div>
                                                        <button
                                                            v-else
                                                            @click="triggerItemFileUpload(item)"
                                                            class="btn-upload-source-mobile"
                                                            title="Upload Bukti Dukung"
                                                            :disabled="isFinalized"
                                                        >
                                                            <i
                                                                class="bi bi-cloud-arrow-up-fill mt-customByFransIcon"
                                                            ></i
                                                            ><span>Upload</span>
                                                        </button>
                                                        <div
                                                            class="text-muted"
                                                            style="
                                                                font-size: 0.5rem;
                                                                margin-top: 4px;
                                                                text-align: center;
                                                            "
                                                        >
                                                            Max. 3MB
                                                        </div>
                                                    </div>
                                                    <div v-else>
                                                        <span class="badge-not-active">Periode KPI belum aktif</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="currentStep === 2" key="step2">
                                    <div class="step-content-header">
                                        <div>
                                            <h3>
                                                Langkah 2: Review Kinerja & PICA
                                                <span>
                                                    <el-tag class="custom-tag"
                                                        >Periode {{ headersUpdatePerformance.periode ?? '' }}</el-tag
                                                    >
                                                </span>
                                            </h3>
                                            <p>Lihat ringkasan kinerja Anda dan isi PICA jika diperlukan.</p>
                                        </div>
                                        <span class="step-badge">Langkah 2 dari {{ totalSteps }}</span>
                                    </div>

                                    <PerformanceSummaryCard
                                        v-if="currentStep === 2 && performanceLevels.length > 0"
                                        :target-score="targetFinalScore"
                                        :actual-score="totalScore"
                                        :performance-status="finalPerformance"
                                        :is-capped="isScoreCapped"
                                        :cap-note="capNote"
                                    />

                                    <h4 class="detail-separator">Detail Kinerja per Parameter</h4>

                                    <div v-if="requiresPica">
                                        <p class="pica-instruction">
                                            Beberapa parameter KPI tidak mencapai target. Klik "Isi PICA" untuk membuat
                                            rencana perbaikan.
                                        </p>
                                        <div class="pica-list-container">
                                            <PicaCard
                                                v-for="item in redKpiItems"
                                                :key="item.id"
                                                :item="item"
                                                :performanceList="performanceLevels"
                                                :loadingPerformance="loading.kpiItems"
                                                type="danger"
                                                @edit-pica="editPica"
                                                @view-pica="viewPica"
                                            />
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p class="pica-instruction">
                                            Selamat! Semua parameter KPI Anda telah mencapai target kinerja minimum.
                                        </p>
                                        <div class="pica-list-container">
                                            <PicaCard
                                                v-for="item in greenKpiItems"
                                                :key="item.id"
                                                :item="item"
                                                type="success"
                                                :performanceList="performanceLevels"
                                                :loadingPerformance="loading.kpiItems"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-else-if="currentStep === 3" key="step-final">
                                    <div class="step-content-header">
                                        <div>
                                            <h3>
                                                Langkah 3: Konfirmasi & Finalisasi
                                                <span>
                                                    <el-tag class="custom-tag"
                                                        >Periode {{ headersUpdatePerformance.periode ?? '' }}</el-tag
                                                    >
                                                </span>
                                            </h3>
                                            <p>Pastikan semua data sudah benar</p>
                                        </div>
                                        <span class="step-badge">Langkah 3 dari {{ totalSteps }}</span>
                                    </div>
                                    <div class="mb-3">
                                        <PerformanceSummaryCard
                                            v-if="isFinalized"
                                            :target-score="targetFinalScore"
                                            :actual-score="totalScore"
                                            :performance-status="finalPerformance"
                                            :is-capped="isScoreCapped"
                                            :cap-note="capNote"
                                        />
                                    </div>
                                    <FinalReview
                                        v-model="isAgreed"
                                        @preview-file="previewFile"
                                        :kpi-items="kpiItems"
                                        :requires-pica="requiresPica"
                                        :is-finalized="isFinalized"
                                        :status-pengajuan="statusPengajuan"
                                        :grading-threshold="gradingThreshold"
                                        :performanceList="performanceLevels"
                                        :loadingPerformance="loading.kpiItems"
                                    />

                                    <div
                                        v-if="tampilkanSpandukTerkunci"
                                        class="alert d-flex align-items-start gap-2 mb-3"
                                        :class="
                                            terkunciMenunggu
                                                ? 'alert-info'
                                                : 'alert-success'
                                        "
                                    >
                                        <i class="bi mt-1" :class="terkunciIkon"></i>
                                        <div>
                                            <strong>{{ terkunciJudul }}</strong>
                                            <div class="small">
                                                {{ terkunciPenjelasan }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="approval-flow-section">
                                        <p class="approval-flow-intro">
                                            <i class="bi bi-diagram-3 me-1"></i>
                                            <template v-if="!approvalFlow.submitted">
                                                Alur persetujuan — setelah difinalisasi, pengajuan Anda akan diproses
                                                secara berurutan oleh approver berikut:
                                            </template>
                                            <template v-else-if="approvalFlow.summary">
                                                {{ approvalFlow.summary.completedSteps }} dari
                                                {{ approvalFlow.summary.totalSteps }} approver telah menyetujui.
                                                <template v-if="approvalFlow.summary.currentApproverName">
                                                    Saat ini sedang ditinjau oleh
                                                    <strong>{{ approvalFlow.summary.currentApproverName }}</strong>.
                                                </template>
                                            </template>
                                            <template v-else>
                                                Progres persetujuan pengajuan Anda:
                                            </template>
                                        </p>

                                        <div
                                            v-if="approvalFlow.submitted && approvalFlow.summary"
                                            class="approval-progress-track"
                                        >
                                            <div
                                                class="approval-progress-fill"
                                                :style="{ width: (approvalFlow.summary.progressPercent || 0) + '%' }"
                                            ></div>
                                        </div>

                                        <template v-if="loading.approvalFlow">
                                            <HcisSkeleton v-for="n in 5" :key="n" type="card" class="mb-3" />
                                        </template>
                                        <template
                                            v-else-if="approvalFlow.steps.length"
                                        >
                                            <!-- Pengajuan lama: urutan approver-nya
                                                 diambil dari approval config, tetapi
                                                 siapa menyetujui kapan memang tidak
                                                 pernah dicatat. Dikatakan apa adanya,
                                                 bukan disamarkan jadi "menunggu". -->
                                           
                                            <SubmissionSidebar
                                                :approvalData="approvalFlow.steps"
                                                active-approval
                                            />
                                        </template>
                                        <p v-else class="text-muted text-center py-3 mb-0">
                                            Alur persetujuan belum tersedia.
                                        </p>
                                    </div>
                                </div>
                            </transition>

                            <div class="action-bar">
                                <button
                                    class="btn-secondary"
                                    @click="prevStep"
                                    v-if="currentStep > 1"
                                    :disabled="processing"
                                >
                                    <i class="bi bi-arrow-left-circle mt-customByFransIcon"></i>
                                    Back
                                </button>
                                <div class="spacer"></div>

                                <button
                                    class="btn-primary"
                                    @click="nextStep"
                                    v-if="currentStep < totalSteps"
                                    :disabled="!isCurrentStepValid || loading.saveToDatabaseSementara"
                                >
                                    <span v-if="loading.saveToDatabaseSementara">
                                        <span
                                            class="spinner-border spinner-border-sm"
                                            role="status"
                                            aria-hidden="true"
                                        ></span>
                                        Loading...
                                    </span>
                                    <span v-else>
                                        Next
                                        <i class="bi bi-arrow-right-circle mt-customByFransIcon"></i>
                                    </span>
                                </button>
                                <div v-if="!isFinalized">
                                    <button
                                        class="btn-danger"
                                        @click="openModalFinalize"
                                        v-if="currentStep === totalSteps"
                                        :disabled="!isAgreed || processing"
                                    >
                                        <i class="bi bi-check-circle-fill mt-customByFransIcon"></i>
                                        Finalisasi
                                    </button>
                                </div>
                                <div v-else></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- <div class="modern-stepper-container">
                    <h6><i class="bi bi-activity me-2"></i>Activity History</h6>
                    <Timeline :history="historyKPI" />
                </div> -->
            </div>

            <div v-if="processing" class="loading-overlay">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
        </div>

        <ModalV1
            :show="showModal"
            :title="modalTitle"
            :submitText="modalSubmitText"
            :loading="loading.saveToDatabase"
            :is-read-only="picaModalMode === 'view' || isFinalized"
            @close="closeModal"
            @submit="submitPicaData"
        >
            <FormV2 v-model="form" :fields="formFields" />
        </ModalV1>

        <ModalV1
            v-if="!isFinalized"
            :show="showModalFinalize"
            :title="modalTitleFinalize"
            :submitText="modalSubmitTextFinalize"
            :loading="loading.finalisasiData"
            @close="closeModalFinalize"
            @submit="submitForm"
        >
            <div class="finalize-warning">
                <div class="warning-header">
                    <DotLottieVue style="height: 50px; width: 50px" autoplay loop src="/animation/Alert.lottie" />
                    <h2>Finalisasi Update Performance KPI</h2>
                </div>

                <div class="warning-content">
                    <p>
                        Harap diperhatikan dengan seksama. Proses
                        <b>finalisasi Update Performance KPI</b> adalah tahap terakhir yang
                        <span class="highlight">bersifat permanen</span>. Setelah tombol <b>Finalisasi</b> ditekan,
                        semua data yang telah dikirim akan terkunci dan <u>tidak dapat diubah kembali</u>.
                    </p>

                    <p>Dengan melakukan finalisasi, Anda menyatakan bahwa seluruh data yang dikirim telah:</p>

                    <div class="warning-box">
                        <ul>
                            <li>
                                ✅ <b>Data source</b> yang digunakan <span class="highlight">terbaca jelas</span>,
                                lengkap, dan tidak ada bagian yang hilang.
                            </li>
                            <li>
                                ✅ Apabila data source tidak terbaca, tidak lengkap, atau bermasalah, maka
                                <b>sistem tidak bertanggung jawab</b> atas hasilnya.
                            </li>
                            <li>
                                ✅ Nilai yang dimasukkan sesuai dengan kondisi
                                <b>aktual</b> dan benar-benar mencerminkan data sumber.
                            </li>
                            <li>✅ Tidak terdapat manipulasi, rekayasa, atau perubahan sepihak terhadap data.</li>
                            <li>
                                ✅ Informasi yang dikirim telah diverifikasi, dapat dipertanggungjawabkan, dan siap
                                dicatat secara resmi.
                            </li>
                        </ul>
                    </div>

                    <p>
                        Finalisasi ini dirancang agar seluruh pengguna dapat memahami alurnya secara
                        <b>jelas, transparan, dan nyaman dibaca</b>. Semua penjelasan dibuat agar tidak membingungkan
                        dan tetap memberikan rasa <span class="highlight">kepastian</span> bahwa data yang terkunci
                        adalah hasil akhir yang sah.
                    </p>

                    <p>
                        Pastikan Anda telah melakukan
                        <b>pengecekan ulang</b> sebelum melanjutkan. Setelah finalisasi dilakukan, data akan menjadi
                        bagian dari rekam jejak resmi penilaian kinerja dan tidak tersedia opsi untuk membatalkan.
                    </p>

                    <p class="reminder">
                        🚨 Tekan tombol <b>Finalisasi</b> hanya jika semua data benar-benar sudah sesuai. Sistem tidak
                        menyediakan fitur <i>undo</i> – keputusan ini bersifat
                        <span class="highlight">final dan permanen</span>.
                    </p>
                </div>
            </div>
        </ModalV1>
    </div>
</template>

<script>
import axios from 'axios';
import { BATAS_BAKU, batasDariConfig, hitungMetrikParameter } from '@/Pages/KPI/utils/kpiCap';
import HeaderKpi from './components/header.vue';
import { ElTag, ElNotification, ElMessage } from 'element-plus';
import ModalV1 from '@/components/legacy/modalV1.vue';
import FormV2 from '../../components/form/formV2.vue';
import Stepper from '../../components/ui/stepper.vue';
import AlertWarning from '../../components/ui/alertWarning.vue';
import PerformanceSummaryCard from '../../components/ui/performanceSummaryCard.vue';
import PicaCard from '../../components/ui/cardModern.vue';
import FinalReview from './components/finalReview.vue';
import Timeline from '../../components/ui/history/timeline.vue';
import SkeletonTable from '../../components/ui/skeletonTable.vue';
import { HcisSkeleton } from '@/components/ui';
import AlertUiV1 from '../../components/ui/alertUiV1.vue';
import SubmissionSidebar from '../../components/ui/SubmissionSidebar.vue';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { tanggalPanjang } from '@/utils/tanggal';
import { hitungRumus } from '@/utils/rumusKpi';
import { formatNilaiSatuanSorot } from '@/utils/nilaiSatuan';
/* Due Date dari server berformat tampilan ("12 Okt 2026 08:00"); form PICA
   membutuhkan "YYYY-MM-DD HH:mm:ss" untuk date picker dan untuk Carbon. */
import { keTeksTanggalJam } from '@/Pages/KPI/utils/tanggalJam';

export default {
    props: {
        IdMasterPenjadwalan: {
            type: String,
            required: true,
        },
        // Periode yang dibuka ternyata sudah punya pengajuan hidup. Dikatakan di
        // muka supaya tidak lagi berakhir sebagai penolakan di tombol terakhir.
        periodeTerkunci: {
            type: Boolean,
            default: false,
        },
        periodeTerkunciLabel: {
            type: String,
            default: null,
        },
        noTransaksiTerkunci: {
            type: String,
            default: null,
        },
        // 'menunggu' | 'disetujui' | null. Menentukan NADA pesannya: pengajuan
        // yang sedang berjalan adalah keadaan sehat, bukan sesuatu yang harus
        // dibatalkan.
        statusTerkunci: {
            type: String,
            default: null,
        },
    },

    components: {
        HeaderKpi,
        ElTag,
        ModalV1,
        FormV2,
        Stepper,
        AlertWarning,
        PerformanceSummaryCard,
        PicaCard,
        FinalReview,
        Timeline,
        SkeletonTable,
        HcisSkeleton,
        SubmissionSidebar,
        DotLottieVue,
        DynamicAlert: AlertUiV1,
    },
    name: 'KpiUpdateForm',
    data() {
        return {
            headersUpdatePerformance: null,
            isFinalized: false,
            isRejected: false,
            rejectionStatus: null,
            rejectionGlobalReason: null,
            temporaryData: null,
            showModal: false,
            isResubmission: false,
            statusPengajuan: null,
            approvalFlow: { submitted: false, status: null, summary: null, steps: [], jejakTersedia: true },
            showModalFinalize: false,
            isEditing: false,
            currentDate: new Date(),
            targetFinalScore: 100,
            currentStep: 1,
            processing: false,
            isAgreed: false,
            expandedPica: {},
            currentItemForPica: null,
            gradingThreshold: 100,
            kpiMaxScore: 125,
            kpiMinScore: 50,
            batasParameter: { ...BATAS_BAKU },
            picaModalMode: 'edit',
            historyKPI: [],
            kpiItems: [],
            performanceLevels: [],
            currentItemForUpload: null,
            form: {
                id: null,
                Nama_Parameter: '',
                Performance: '',
                Problem_Identification: '',
                Corrective_Actions: '',
                targetDate: '',
            },
            loading: {
                saveToDatabaseSementara: false,
                finalisasiData: false,
                kpiItems: true,
                historyKPI: true,
                saveToDatabase: false,
                editToDatabase: false,
                approvalFlow: false,
            },
        };
    },
    computed: {
        // Periode terkunci punya DUA sebab yang sama sekali berbeda rasanya:
        // pengajuannya sedang menunggu persetujuan (normal), atau sudah disetujui
        // (selesai). Keduanya sama-sama menghalangi kiriman baru, tetapi hanya
        // yang kedua yang masuk akal disusul kalimat "batalkan yang lama".
        // Jangan mengatakan dua kali hal yang sama.
        //
        // Ketika pengajuannya sedang menunggu persetujuan, panel "Data Telah
        // Terkirim" milik FinalReview sudah menyampaikannya — spanduk ini hanya
        // akan menjadi salinan kedua di layar yang sama. Yang tersisa untuk
        // disampaikan spanduk ini adalah keadaan yang TIDAK dicakup panel itu:
        // periode yang sudah DISETUJUI, atau pengajuan menggantung yang bukan
        // milik transaksi yang sedang dibuka halaman ini.
        tampilkanSpandukTerkunci() {
            if (! this.periodeTerkunci) {
                return false;
            }

            return ! (this.terkunciMenunggu && this.isFinalized);
        },
        terkunciMenunggu() {
            return (this.statusTerkunci || "").toLowerCase() === "menunggu";
        },
        terkunciIkon() {
            return this.terkunciMenunggu
                ? "bi-hourglass-split"
                : "bi-check-circle-fill";
        },
        terkunciJudul() {
            const no = this.noTransaksiTerkunci || "pengajuan Anda";
            return this.terkunciMenunggu
                ? `Pengajuan ${no} sedang menunggu persetujuan.`
                : `Periode ${this.periodeTerkunciLabel} sudah disetujui.`;
        },
        terkunciPenjelasan() {
            if (this.terkunciMenunggu) {
                return `Update performance periode ${this.periodeTerkunciLabel} sudah Anda kirim dan kini berjalan di alur persetujuan di bawah. Belum ada yang perlu dikerjakan di halaman ini — kiriman baru untuk periode yang sama akan ditolak selama pengajuan ini masih berjalan.`;
            }
            const no = this.noTransaksiTerkunci || "yang lama";
            return `Pengajuan ${no} sudah disetujui untuk periode ini. Bila memang perlu mengirim ulang, pengajuan tersebut harus dibatalkan lebih dulu lewat Monitoring KPI.`;
        },
        formFields() {
            const isReadonly = this.picaModalMode === 'view' || this.isFinalized;
            return [
                {
                    name: 'Nama_Parameter',
                    label: 'Parameter',
                    type: 'text',
                    icon: 'bi bi-journal-check',
                    col: 'col-6',
                    disabled: true,
                },
                {
                    name: 'Performance',
                    label: 'Target',
                    type: 'text',
                    icon: 'bi bi-graph-up-arrow',
                    col: 'col-6',
                    disabled: true,
                },
                {
                    name: 'Problem_Identification',
                    label: 'Problem Identification',
                    type: 'textarea',
                    placeholder: 'Jelaskan akar masalah...',
                    icon: 'bi bi-search',
                    col: 'col-12',
                    rows: 3,
                    disabled: isReadonly,
                },
                {
                    name: 'Corrective_Actions',
                    label: 'Corrective Actions',
                    type: 'textarea',
                    placeholder: 'Jelaskan langkah-langkah perbaikan...',
                    icon: 'bi bi-tools',
                    col: 'col-12',
                    rows: 3,
                    disabled: isReadonly,
                },
                {
                    name: 'targetDate',
                    label: 'Due Date',
                    type: 'datetime-local',
                    icon: 'bi bi-calendar-check',
                    col: 'col-6',
                    disabled: isReadonly,
                },
            ];
        },
        modalTitle() {
            if (this.picaModalMode === 'view') {
                return 'Detail Rencana Perbaikan (PICA)';
            }
            return this.isEditing ? 'Edit PICA' : 'Isi PICA';
        },
        modalTitleFinalize() {
            return '';
        },
        modalSubmitTextFinalize() {
            return 'Finalisasi';
        },
        modalSubmitText() {
            return 'Simpan PICA';
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        requiresPica() {
            return this.kpiItems.some((item) => item.actual !== null && item.achievement < this.gradingThreshold);
        },
        redKpiItems() {
            return this.kpiItems.filter((item) => item.actual !== null && item.achievement < this.gradingThreshold);
        },
        greenKpiItems() {
            return this.kpiItems.filter((item) => item.actual !== null && item.achievement >= this.gradingThreshold);
        },
        steps() {
            return [
                {
                    id: 1,
                    title: 'Update Performance',
                    description: 'Karyawan mengisi nilai',
                },
                {
                    id: 2,
                    title: 'Review & PICA',
                    description: 'Lihat kinerja & perbaikan',
                },
                {
                    id: 3,
                    title: 'Finalisasi Data',
                    description: 'Review dan submit',
                },
            ];
        },
        totalSteps() {
            return this.steps.length;
        },
        activeKpiItems() {
            return this.kpiItems.filter((item) => item.is_input_period);
        },
        currentTargetScore() {
            if (!this.kpiItems.length) return 100;
            return this.activeKpiItems.reduce((total, item) => total + item.weight, 0);
        },
        isStep1Valid() {
            if (this.activeKpiItems.length === 0) {
                return true;
            }
            return this.activeKpiItems.every(
                (item) => item.actual !== null && item.actual !== '' && item.dataSourceFile !== null,
            );
        },
        isPicaStepValid() {
            if (!this.requiresPica) {
                return true;
            }
            return this.redKpiItems.every(
                (item) =>
                    item.pica &&
                    item.pica.problem &&
                    item.pica.problem.trim() !== '' &&
                    item.pica.action &&
                    item.pica.action.trim() !== '' &&
                    !!item.pica.targetDate,
            );
        },
        isCurrentStepValid() {
            if (this.currentStep === 1) {
                return this.isStep1Valid;
            }
            if (this.currentStep === 2) {
                return this.isPicaStepValid;
            }
            return true;
        },
        totalScoreRaw() {
            // Total dari skor MENTAH (sebelum penjepitan per parameter), dipakai
            // keterangan "total tanpa penyesuaian". Bukan lagi sumber totalScore.
            const sumOfRaw = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.scoreRaw ?? item.score) || 0),
                0,
            );
            const sumOfActiveBobot = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.weight) || 0),
                0,
            );

            return sumOfActiveBobot === 0 ? 0 : (sumOfRaw / sumOfActiveBobot) * 100;
        },
        totalScore() {
            // Dari skor yang SUDAH dijepit per parameter, supaya total selalu sama
            // dengan rata-rata tertimbang baris yang ditampilkan. Penjepitan total
            // di bawah ini tinggal jaring pengaman; ia tak menggigit selama batas
            // per-parameter berlaku.
            const sumOfScores = this.activeKpiItems.reduce((carry, item) => carry + (parseFloat(item.score) || 0), 0);
            const sumOfActiveBobot = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.weight) || 0),
                0,
            );
            if (sumOfActiveBobot === 0) return 0;

            const raw = (sumOfScores / sumOfActiveBobot) * 100;
            return Math.max(this.kpiMinScore, Math.min(this.kpiMaxScore, raw));
        },
        jumlahParameterTerjepit() {
            return this.activeKpiItems.filter((item) => item.isCapped).length;
        },
        isScoreCapped() {
            return this.jumlahParameterTerjepit > 0 || this.totalScoreRaw !== this.totalScore;
        },
        capNote() {
            const n = this.jumlahParameterTerjepit;
            if (n === 0) return '';

            const batas = `${this.batasParameter.min}%–${this.batasParameter.max}%`;
            return `${n} parameter disesuaikan ke batas ${batas}. Total tanpa penyesuaian ${this.formatScore(
                this.totalScoreRaw,
            )}%.`;
        },
        finalPerformance() {
            const score = this.totalScore;

            if (!this.performanceLevels || this.performanceLevels.length === 0) {
                return { text: 'N/A', color: '#6c757d' };
            }

            const sortedLevels = [...this.performanceLevels].sort((a, b) => b.Range_Awal - a.Range_Awal);
            const level = sortedLevels.find((l) => score >= l.Range_Awal);

            if (level) {
                return {
                    text: level.Keterangan,
                    color: level.Warna,
                };
            }

            return {
                text: 'N/A',
                color: '#6c757d',
            };
        },
    },
    mounted() {
        this.initializeComponent();
    },
    methods: {
        async initializeComponent() {
            await this.fetchMasterGrading();
            await this.fetchPerformanceLevels();
            await this.fetchKpiCapConfig();
            await this.fetchTemporaryData();
            await this.fetchListData();
            this.fetchListHistoryData();
            this.fetchApprovalFlow();
        },
        async fetchApprovalFlow() {
            this.loading.approvalFlow = true;
            try {
                // Jadwalnya WAJIB ikut: tanpa itu server tidak tahu periode mana
                // yang sedang dibuka, dan alur yang tampil bisa milik periode lain
                // — pengajuan yang sudah lama disetujui muncul di periode baru.
                const response = await axios.get('/api/v1/update-performance/approval-flow', {
                    params: { Id_Master_Penjadwalan: this.IdMasterPenjadwalan },
                });
                if (response.status === 200 && response.data?.result) {
                    const r = response.data.result;
                    this.approvalFlow = {
                        submitted: !!r.submitted,
                        status: r.status ?? null,
                        summary: r.summary ?? null,
                        steps: Array.isArray(r.steps) ? r.steps : [],
                        // Server memakai true sebagai bawaan: hanya pengajuan
                        // engine legacy tanpa jejak yang mengirim false.
                        jejakTersedia: r.jejak_tersedia !== false,
                    };
                }
            } catch (error) {
                console.error('Failed to fetch approval flow:', error);
                this.approvalFlow = { submitted: false, status: null, summary: null, steps: [], jejakTersedia: true };
            } finally {
                this.loading.approvalFlow = false;
            }
        },
        async fetchTemporaryData() {
            try {
                const response = await axios.get('/api/v1/update-performance/temporary-actual-performance', {
                    params: { Id_Master_Penjadwalan: this.IdMasterPenjadwalan },
                });
                if (response.status === 200 && response.data?.result) {
                    this.temporaryData = response.data.result;
                    const header = this.temporaryData.header;
                    this.isFinalized = header.isFinalized;
                    this.isRejected = header.isRejected;
                    this.isResubmission = header.isResubmission;
                    this.statusPengajuan = header.Status_Pengajuan;
                    this.rejectionGlobalReason =
                        header.alasan_penolakan_global ?? null;
                }
            } catch (error) {
                if (error.response && error.response.status === 404) {
                    console.log('No temporary data found. Starting fresh.');
                    this.temporaryData = null;
                    this.isFinalized = false;
                    this.isRejected = false;
                    this.isResubmission = false;
                    this.statusPengajuan = null;
                    this.rejectionGlobalReason = null;
                } else {
                    console.error('Error fetching temporary data:', error);
                }
            }
        },
        async fetchMasterGrading() {
            try {
                const response = await axios.post('/api/v1/update-performance/master-grading/performance');
                if (response.status === 200 && response.data?.result) {
                    this.gradingThreshold = response.data.result;
                }
            } catch (error) {
                console.error('Gagal mengambil master grading:', error);
                this.gradingThreshold = 100;
            }
        },
        async fetchKpiCapConfig() {
            try {
                const response = await axios.get('/api/v1/update-performance/kpi-cap-config');
                if (response.status === 200 && response.data?.result) {
                    this.kpiMaxScore = response.data.result.max_achievement_score ?? 125;
                    this.kpiMinScore = response.data.result.min_achievement_score ?? 50;
                    this.batasParameter = batasDariConfig(response.data.result);
                }
            } catch (error) {
                console.error('Gagal mengambil konfigurasi cap KPI:', error);
            }
        },
        formatScore(value) {
            if (typeof value !== 'number' || !isFinite(value)) {
                return 0;
            }
            if (Number.isInteger(value)) {
                return value;
            }
            let rounded = parseFloat(value.toFixed(1));
            if (Number.isInteger(rounded)) {
                return rounded;
            }
            return rounded;
        },
        async fetchPerformanceLevels() {
            try {
                const response = await axios.get('/api/v1/update-performance/list-performance');
                if (response.status === 200 && response.data?.result) {
                    this.performanceLevels = response.data.result;
                }
            } catch (error) {
                console.error('Gagal mengambil data master level performance:', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal memuat konfigurasi level performance.',
                    type: 'error',
                });
            }
        },
        mergeTemporaryData() {
            if (!this.temporaryData || !this.temporaryData.details) return;

            if (this.temporaryData.header && this.temporaryData.header.Steps) {
                this.currentStep = parseInt(this.temporaryData.header.Steps, 10);
            }

            const tempDetails = this.temporaryData.details;

            this.kpiItems.forEach((item) => {
                const detail = tempDetails.find((d) => d.Id_Pengajuan_Det === item.idPengajuanDet);

                if (detail) {
                    item.idDetailSementara = detail.Id_Update_Performance_Detail_Sementara;
                    item.actual = detail.Nilai_Actual;
                    item.isRejected = detail.isRejected;
                    item.alasan_penolakan = detail.alasan_penolakan;
                    if (detail.File_Berkas) {
                        item.dataSourceFile = {
                            path: detail.File_Berkas,
                            url: detail.File_Berkas_Url,
                            isExisting: true,
                        };
                    }

                    if (detail.Flag_Pica === 'Y' && detail.Pica) {
                        item.pica = {
                            problem: detail.Pica.Identifikasi || '',
                            action: detail.Pica.Koreksi_Aksi || '',
                            targetDate: detail.Pica.Tanggal_Pelaksanaan || '',
                        };
                    } else {
                        item.pica = null;
                    }

                    this.updateKpiMetrics(item);
                }
            });
        },
        async fetchListData() {
            this.loading.kpiItems = true;
            try {
                const response = await axios.get('/api/v1/update-performance/list-Kpi', {
                    params: {
                        Id_Master_Penjadwalan: this.IdMasterPenjadwalan,
                    },
                });

                if (response.status === 200 && response.data?.result) {
                    this.kpiItems = response.data.result.details;
                    this.headersUpdatePerformance = response.data.result.header;

                    if (this.temporaryData) {
                        this.mergeTemporaryData();
                    }
                } else {
                    this.kpiItems = [];
                }
            } catch (error) {
                this.kpiItems = [];
                console.error('Failed to fetch list data:', error);
            } finally {
                this.loading.kpiItems = false;
            }
        },
        async fetchListHistoryData() {
            this.loading.historyKPI = true;
            try {
                const response = await axios.post('/api/v1/update-performance/list-history');

                if (response.status === 200 && response.data?.result) {
                    this.historyKPI = response.data.result;
                } else {
                    this.historyKPI = [];
                }
            } catch (error) {
                this.historyKPI = [];
                console.error('Failed to fetch list data:', error);
            } finally {
                this.loading.historyKPI = false;
            }
        },
        isRupiah(targetUnit) {
            if (typeof targetUnit !== 'string') return false;
            return targetUnit.trim().toLowerCase() === 'rp';
        },
        allowNumericOnly(event) {
            const char = event.key;

            const allowed =
                /[0-9.-]/.test(char) || ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(char);

            if (!allowed) {
                event.preventDefault();
            }

            if (char === ',' && event.target.value.includes(',')) {
                event.preventDefault();
            }

            if (char === '-' && (event.target.value.includes('-') || event.target.selectionStart !== 0)) {
                event.preventDefault();
            }
        },
        showPicaModal(item) {
            this.currentItemForPica = item;
            const picaData = item.pica || {};

            this.isEditing = !!picaData.problem;

            this.form = {
                id: item.id,
                Nama_Parameter: item.parameter,
                Performance: `${this.formatCurrency(item.actual)} ${
                    item.targetUnit
                } (Target: ${item.targetDescription})`,
                Problem_Identification: picaData.problem || '',
                Corrective_Actions: picaData.action || '',
                targetDate: keTeksTanggalJam(picaData.targetDate),
            };
            this.showModal = true;
        },
        openPicaModal(item) {
            this.currentItemForPica = item;
            this.isEditing = !!(item.pica && item.pica.problem);
            this.form = {
                id: item.id,
                Nama_Parameter: item.parameter,
                Performance: `${this.formatCurrency(item.actual)} ${
                    item.targetUnit
                } (Target: ${item.targetDescription})`,
                Problem_Identification: item.pica.problem || '',
                Corrective_Actions: item.pica.action || '',
                targetDate: keTeksTanggalJam(item.pica.targetDate),
            };
            this.showModal = true;
        },
        editPica(item) {
            this.picaModalMode = 'edit';
            this.showPicaModal(item);
        },
        viewPica(item) {
            this.picaModalMode = 'view';
            this.showPicaModal(item);
        },
        async submitPicaData() {
            if (!this.currentItemForPica) return;
            this.loading.saveToDatabase = true;
            try {
                const response = await axios.post('/api/v1/update-performance/store/temporary-pica', {
                    Id_Update_Performance_Detail_Sementara: this.currentItemForPica.idDetailSementara,
                    Identifikasi: this.form.Problem_Identification,
                    Koreksi_Aksi: this.form.Corrective_Actions,
                    Tanggal_Pelaksanaan: this.form.targetDate,
                });

                if (response.status === 200 || response.status === 201) {
                    ElNotification({
                        title: 'Sukses',
                        message: response.data.message,
                        type: 'success',
                    });

                    const itemIndex = this.kpiItems.findIndex((kpi) => kpi.id === this.currentItemForPica.id);
                    if (itemIndex !== -1) {
                        this.kpiItems[itemIndex].pica = {
                            problem: this.form.Problem_Identification,
                            action: this.form.Corrective_Actions,
                            targetDate: this.form.targetDate,
                        };
                    }
                    await this.fetchTemporaryData();
                    this.closeModal();
                }
            } catch (error) {
                ElNotification({
                    title: 'Error',
                    message: error.response?.data?.message || 'Terjadi kesalahan saat menyimpan PICA.',
                    type: 'error',
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        closeModal() {
            this.showModal = false;
            this.resetPicaForm();
        },
        openModalFinalize() {
            this.showModalFinalize = true;
        },
        closeModalFinalize() {
            this.showModalFinalize = false;
        },
        resetPicaForm() {
            this.form = {
                id: null,
                Nama_Parameter: '',
                Performance: '',
                Problem_Identification: '',
                Corrective_Actions: '',
                targetDate: '',
            };
            this.isEditing = false;
            this.currentItemForPica = null;
        },
        resetComponentState() {
            this.currentStep = 1;
            this.isAgreed = false;
            this.kpiItems.forEach((item) => {
                item.actual = null;
                item.achievement = 0;
                item.score = 0;
                item.dataSourceFile = null;
                item.isReviewed = false;
            });
        },
        async saveToDatabaseSementara(stepToSave) {
            this.loading.saveToDatabaseSementara = true;

            const formData = new FormData();
            formData.append('current_step', stepToSave || this.currentStep);
            formData.append('Id_Master_Penjadwalan', this.IdMasterPenjadwalan);
            if (this.isRejected && this.temporaryData?.header) {
                formData.append('rejected_transaction_ref', this.temporaryData.header.No_Transaksi_Sementara);
            }
            this.kpiItems.forEach((item, index) => {
                formData.append(`kpi_data[${index}][Id_Pengajuan_Detail]`, item.idPengajuanDetail);
                formData.append(`kpi_data[${index}][Id_Pengajuan_Det]`, item.idPengajuanDet);
                formData.append(`kpi_data[${index}][Id_Pengajuan]`, item.idPengajuan);
                formData.append(`kpi_data[${index}][Actual]`, item.actual !== null ? item.actual : '');
                formData.append(`kpi_data[${index}][is_input_period]`, item.is_input_period);

                if (item.dataSourceFile) {
                    if (item.dataSourceFile instanceof File) {
                        formData.append(`kpi_data[${index}][File_Berkas]`, item.dataSourceFile);
                    } else if (item.dataSourceFile.isExisting) {
                        formData.append(`kpi_data[${index}][File_Berkas]`, item.dataSourceFile.path);
                    }
                } else {
                    formData.append(`kpi_data[${index}][File_Berkas]`, '');
                }

                if (item.pica) {
                    formData.append(`kpi_data[${index}][pica]`, JSON.stringify(item.pica));
                }
            });

            try {
                const response = await axios.post('/api/v1/update-performance/store/temporary', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                });

                if (response.status === 201 && response.data) {
                    ElNotification({
                        title: 'Sukses',
                        message: 'Draf berhasil disimpan.',
                        type: 'success',
                    });
                    if (this.isRejected) {
                        this.isRejected = false;
                        this.isResubmission = false;
                        await this.fetchTemporaryData();
                    }
                    return true;
                }
            } catch (error) {
                ElNotification({
                    title: 'Error',
                    message: error.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.',
                    type: 'error',
                });
                this.loading.saveToDatabaseSementara = false;
                return false;
            }
        },
        async submitForm() {
            if (!this.temporaryData || !this.temporaryData.header?.Id_Update_Performance_Sementara) {
                ElNotification({
                    title: 'Error',
                    message: 'Data sesi tidak ditemukan. Silakan muat ulang halaman.',
                    type: 'error',
                });
                return;
            }

            this.processing = true;
            // Penjagaan yang sama dengan milik server, hanya dipindah ke depan.
            // Servernya tetap menolak; yang diperbaiki di sini adalah kapan
            // penolakan itu sampai ke pengguna.
            if (this.periodeTerkunci) {
                ElNotification({
                    title: this.terkunciMenunggu
                        ? "Sudah dikirim"
                        : "Tidak bisa difinalisasi",
                    message: this.terkunciMenunggu
                        ? `Pengajuan ${this.noTransaksiTerkunci || ""} untuk periode ${this.periodeTerkunciLabel} masih menunggu persetujuan, jadi belum bisa dikirim ulang.`
                        : `Periode ${this.periodeTerkunciLabel} sudah disetujui (${this.noTransaksiTerkunci || "-"}). Batalkan yang lama lebih dulu bila ingin mengirim ulang.`,
                    type: "warning",
                });
                return;
            }

            this.loading.finalisasiData = true;
            this.closeModalFinalize();
            try {
                const response = await axios.post('/api/v1/update-performance/finalize', {
                    Id_Update_Performance_Sementara: this.temporaryData.header.Id_Update_Performance_Sementara,
                });

                if (response.status === 201) {
                    ElNotification({
                        title: 'Sukses',
                        message: response.data.message || 'Data berhasil disimpan!',
                        type: 'success',
                    });
                    this.isFinalized = true;
                    this.initializeComponent();
                }
            } catch (error) {
                console.error(error);
                ElNotification({
                    title: 'Gagal',
                    message: error.response?.data?.message || 'Terjadi kesalahan saat mengirim data final.',
                    type: 'error',
                });
                this.isFinalized = false;
            } finally {
                this.processing = false;

                this.loading.finalisasiData = false;
            }
        },
        formatAchievement(value) {
            if (typeof value !== 'number' || !isFinite(value)) {
                return 0;
            }

            if (value % 1 === 0) {
                return value;
            } else {
                return value.toFixed(1);
            }
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return '';
            return new Intl.NumberFormat('id-ID').format(value);
        },
        handleCurrencyInput(event, item) {
            const rawValue = event.target.value.replace(/\D/g, '');
            item.actual = rawValue ? parseInt(rawValue, 10) : null;
            this.updateKpiMetrics(item);
        },
        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },
        async nextStep() {
            if (this.isFinalized) {
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                }
                return;
            }
            if (this.currentStep >= this.totalSteps || !this.isCurrentStepValid) {
                return;
            }

            this.loading.saveToDatabaseSementara = true;

            try {
                if (this.currentStep === 1) {
                    const success = await this.saveToDatabaseSementara(2);
                    if (success) {
                        await this.initializeComponent();
                    }
                } else if (this.currentStep === 2) {
                    if (!this.temporaryData || !this.temporaryData.header?.Id_Update_Performance_Sementara) {
                        throw new Error('Data sesi tidak ditemukan. Silakan muat ulang halaman.');
                    }

                    const response = await axios.post('/api/v1/update-performance/store/steps2', {
                        Id_Update_Performance_Sementara: this.temporaryData.header.Id_Update_Performance_Sementara,
                    });

                    if (response.status === 201 || response.status === 200) {
                        ElNotification({
                            title: 'Sukses',
                            message: response.data.message || 'Berhasil melanjutkan ke langkah berikutnya.',
                            type: 'success',
                        });
                        await this.initializeComponent();
                    }
                }
            } catch (error) {
                ElNotification({
                    title: 'Error',
                    message: error.response?.data?.message || 'Gagal melanjutkan ke langkah berikutnya.',
                    type: 'error',
                });
            } finally {
                this.loading.saveToDatabaseSementara = false;
            }
        },
        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
            }
        },
        evaluateKpiFormula(formulaWithActual) {
            /*
             * Daftar putih di klien, cermin KpiFormulaHelper::bentukAman di server.
             * Sampai perubahan ini rumus dari basis data diserahkan langsung ke
             * new Function tanpa validasi apa pun.
             */
            return hitungRumus(formulaWithActual);
        },
        updateKpiMetrics(item) {
            if (item.actual === null || item.actual === '' || !item.rumus) {
                item.achievement = 0;
                item.score = 0;
                return;
            }

            const actual = parseFloat(item.actual);
            if (isNaN(actual)) {
                item.achievement = 0;
                item.score = 0;
                return;
            }

            let achievementRatio = 0;
            try {
                const formula = item.rumus.replace(/\[actual\]/g, actual);
                const result = this.evaluateKpiFormula(formula);
                achievementRatio = (typeof result === 'number' && isFinite(result)) ? result : 0;
            } catch (e) {
                console.error('Error evaluating formula:', item.rumus, e);
                achievementRatio = 0;
            }

            const metrik = hitungMetrikParameter(achievementRatio, item.weight, this.batasParameter);
            item.achievement = metrik.achievement;
            item.score = metrik.score;
            item.achievementRaw = metrik.achievementRaw;
            item.scoreRaw = metrik.scoreRaw;
            item.isCapped = metrik.terjepit;

            // Sinkronisasi PICA: parameter yang sudah mencapai target tidak lagi
            // memerlukan PICA. Buang PICA basi (mis. sisa dari sebelum revisi) agar
            // tidak tampil di review maupun ikut tersimpan.
            if (item.achievement >= this.gradingThreshold) {
                item.pica = null;
            }
        },
        getAchievementBadge(achievement) {
            if (!this.performanceLevels || this.performanceLevels.length === 0) {
                return { backgroundColor: '#6c757d' };
            }

            // Band di KPI_Master_Performance punya celah (74->75, 89->90, 105->106),
            // jadi pencocokan tepat saja membuat nilai di celah kehilangan warnanya
            // dan tampak seperti "belum dinilai". Jatuhkan ke band terdekat di
            // bawahnya — aturan yang sama dengan getScoreBadge di berkas ini serta
            // KpiScoringService dan HomepageKpiService di sisi server.
            // Cek keutuhan band: php artisan kpi:cek-band-performance
            let perf = this.performanceLevels.find(
                (p) => achievement >= p.Range_Awal && achievement <= p.Range_Akhir,
            );

            if (!perf) {
                perf = [...this.performanceLevels]
                    .sort((a, b) => b.Range_Awal - a.Range_Awal)
                    .find((p) => achievement >= p.Range_Awal);
            }

            if (perf && perf.Warna) {
                return { backgroundColor: perf.Warna, color: '#fff' };
            }

            return { backgroundColor: '#6c757d', color: '#fff' };
        },

        triggerItemFileUpload(item) {
            this.currentItemForUpload = item;
            this.$refs.itemFileInput.click();
        },
        handleItemFileSelect(event) {
            const file = event.target.files[0];
            const input = this.$refs.itemFileInput;

            if (!file) {
                input.value = '';
                this.currentItemForUpload = null;
                return;
            }

            const allowedMimes = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'docx', 'ppt', 'pptx', 'zip'];
            const maxSize = 3 * 1024 * 1024;
            const fileExtension = file.name.split('.').pop().toLowerCase();

            if (!allowedMimes.includes(fileExtension)) {
                ElMessage({
                    message: `Tipe file tidak diizinkan. Gunakan: ${allowedMimes.join(', ')}.`,
                    type: 'error',
                    duration: 5000,
                });
                input.value = '';
                this.currentItemForUpload = null;
                return;
            }

            if (file.size > maxSize) {
                ElMessage({
                    message: 'Ukuran file terlalu besar. Ukuran maksimal adalah 3MB.',
                    type: 'error',
                    duration: 5000,
                });
                input.value = '';
                this.currentItemForUpload = null;
                return;
            }

            if (this.currentItemForUpload) {
                this.currentItemForUpload.dataSourceFile = file;
            }

            input.value = '';
            this.currentItemForUpload = null;
        },
        previewFile(file) {
            let urlToOpen = null;

            if (file) {
                if (file instanceof File) {
                    urlToOpen = URL.createObjectURL(file);
                } else if (file.isExisting) {
                    urlToOpen = file.url;
                }
            }

            if (urlToOpen) {
                window.open(urlToOpen, '_blank');
            } else {
                console.warn('Tidak ada file untuk ditampilkan.');
            }
        },
        removeItemFile(item) {
            item.dataSourceFile = null;
        },
        formatValueWithUnit(value, unit) {
            /*
             * Aturannya pindah ke utils/nilaiSatuan.js. Sebelum ini metode yang
             * sama tersalin di enam komponen dengan TIGA aturan berbeda soal
             * pemangkasan spasi dan pengenalan mata uang.
             *
             * Keluarannya dirender lewat v-html, jadi nama satuan -- yang berasal
             * dari master yang bisa disunting admin -- WAJIB di-escape.
             */
            return formatNilaiSatuanSorot(value, unit);
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary-color: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-100);
    --success-color: var(--hcis-emerald-500);
    --warning-color: var(--hcis-amber-500);
    --danger-color: var(--hcis-red-500);
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-400: #9ca3af;
    --gray-600: #4b5563;
    --gray-800: #1f2937;
    --text-color: #374151;
    --white-color: var(--hcis-white);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --border-radius: 0.75rem;
    --transition-speed: 0.3s;
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);

    /* Token tema untuk SubmissionSidebar (alur persetujuan) */
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.approval-flow-section {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--gray-200);
}
.approval-flow-intro {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 1rem;
}
.approval-flow-intro strong {
    color: var(--text-color);
}
.approval-progress-track {
    height: 8px;
    background-color: var(--gray-200);
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.approval-progress-fill {
    height: 100%;
    background: var(--gradient-primary);
    border-radius: 999px;
    transition: width var(--transition-speed) ease-in-out;
}

.finalize-warning {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    line-height: 1.6;
    padding: 15px;
}

.warning-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    border-bottom: 2px solid #f0ad4e;
    padding-bottom: 10px;
}

.warning-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #c9302c;
    margin: 0;
}

.icon-warning {
    font-size: 28px;
    color: #d9534f;
}

.warning-content p {
    margin-bottom: 15px;
    text-align: justify;
}

.warning-content .highlight {
    background: #fff3cd;
    padding: 2px 5px;
    border-radius: 4px;
    font-weight: 600;
}

.warning-box {
    background: #f9f9f9;
    border-left: 4px solid #f0ad4e;
    padding: 15px;
    margin: 20px 0;
    border-radius: 6px;
}

.warning-box ul {
    margin: 0;
    padding-left: 20px;
}

.warning-box li {
    margin-bottom: 10px;
    font-size: 14px;
}

.reminder {
    font-weight: 600;
    font-size: 15px;
    color: #b52b27;
    margin-top: 20px;
    text-align: center;
    background: #fdecea;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #f5c6cb;
}

.kpi-inactive-period {
    background-color: #f8f9fa;
    opacity: 0.7; /* Opacity bisa sedikit dinaikkan agar tulisan masih jelas */
}

/* BARU: Style untuk badge penanda periode belum aktif */
.badge-not-active {
    display: inline-flex;
    align-items: center;
    padding: 0.4em 0.8em;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1;
    color: #6c757d;
    background-color: var(--hcis-legacy-bootstrap-gray);
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
}
.table-danger-50 {
    background-color: #fceceb !important;
    color: #af312d !important;
}

.table-danger-100 {
    background-color: var(--hcis-legacy-bootstrap-danger) !important;
    color: #000 !important;
}

.parameter-cell {
    max-width: 300px;
    min-width: 250px;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    vertical-align: top;
}

.text-penolakan {
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 8px;
}

.text-icontolak {
    color: #ed0e21 !important;
    font-size: 10px;
    position: relative;
    top: -8px !important;
    left: -5px;
}

/* Soft Danger Palette */
.table-danger-50 {
    background-color: var(--hcis-red-50) !important;
    color: #7a1d1a !important;
} /* sangat lembut */
.table-danger-100 {
    background-color: #fde4e3 !important;
    color: #7a1d1a !important;
}
.table-danger-200 {
    background-color: #f8c8c7 !important;
    color: #6c1a18 !important;
}
.table-danger-300 {
    background-color: #f19a98 !important;
    color: #621716 !important;
}
.table-danger-400 {
    background-color: #e66c69 !important;
    color: #581413 !important;
}
.table-danger-500 {
    background-color: #d94845 !important;
    color: #fff !important;
} /* utama soft */
.table-danger-600 {
    background-color: #c0392b !important;
    color: #fff !important;
}
.table-danger-700 {
    background-color: #a83228 !important;
    color: #fff !important;
}
.table-danger-800 {
    background-color: #8a2a23 !important;
    color: #fff !important;
}
.table-danger-900 {
    background-color: #701f1a !important;
    color: #fff !important;
}

.custom-tag {
    background: var(--gradient-primary) !important;
    color: var(--hcis-white) !important; /* biar text-nya putih */
    border: none !important; /* hilangin border default */
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
    background: var(--gradient-primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

.btn-primary:hover {
    background: hsl(270, 92%, 23%);
    transform: translateY(-2px);
    color: var(--gray-100);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.modern-stepper-container {
    background-color: var(--white-color);
    padding: 1.5rem 2rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 2rem;
}
.modern-stepper {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: relative;
}
.stepper-line {
    position: absolute;
    top: 20px;
    left: 0;

    right: 0;
    height: 4px;
    background-color: var(--gray-200);
    margin: 0 10%;
    z-index: 0;
}
.stepper-line-progress {
    height: 100%;
    width: 0;
    background: var(--gradient-primary);
    transition: width var(--transition-speed) ease-in-out;
}
.step {
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    flex: 1;
    justify-content: center;
}
.step-circle {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    transition: all var(--transition-speed) ease;
    border: 3px solid var(--gray-200);
    background-color: var(--white-color);
}
.step-title {
    margin-top: -6px;
    font-weight: 700;
    color: var(--text-color);
    line-height: 30px;
}
.step-description {
    font-size: 0.875rem;
    color: var(--gray-600);
}
.step.is-upcoming {
    opacity: 0.6;
}
.step.is-active .step-circle {
    border-color: var(--primary-color);
    background-color: var(--primary-light);
    color: var(--primary-dark);
}
.step.is-active .step-title {
    color: var(--primary-dark);
}
.step.is-completed {
    cursor: pointer;
}

.mt-customByFransIcon {
    margin-top: -10px;
}
.step.is-completed .step-circle {
    border-color: var(--success-color);
    background-color: var(--success-color);
    color: var(--white-color);
}
.content-card {
    background: var(--white-color);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}
.step-content-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 1.5rem;
    gap: 1rem;
}
.step-content-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0;
}
.step-content-header p {
    color: var(--gray-600);
    margin: 0.25rem 0 0 0;
}
.step-badge {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.4rem 0.8rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}
.action-bar {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--gray-200);
}
.action-bar .spacer {
    flex-grow: 1;
}
.btn-primary,
.btn-secondary,
.btn-outline,
.btn-success,
.btn-danger {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.25rem;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
}
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-primary {
    background-color: var(--primary-color);
    color: var(--white-color);
}
.btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
}
.btn-secondary {
    background-color: var(--gray-200);
    color: var(--gray-800);
}
.btn-secondary:hover:not(:disabled) {
    background-color: var(--gray-400);
}
.btn-outline {
    background-color: transparent;
    color: var(--gray-800);
    border-color: var(--gray-200);
}
.btn-outline:hover:not(:disabled) {
    background-color: var(--gray-100);
}
.btn-success {
    background-color: var(--success-color);
    color: var(--white-color);
}
.btn-success:hover:not(:disabled) {
    background-color: var(--hcis-emerald-600);
}
.btn-danger {
    background-color: var(--danger-color);
    color: var(--white-color);
}
.btn-danger:hover:not(:disabled) {
    background-color: #d23939;
}
.loading-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 999;
}
.spinner {
    width: 48px;
    height: 48px;
    border: 5px solid var(--primary-light);
    border-bottom-color: var(--primary-color);
    border-radius: 50%;
    display: inline-block;
    animation: rotation 1s linear infinite;
    margin-bottom: 1rem;
}
@keyframes rotation {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
.fade-step-enter-active,
.fade-step-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}
.fade-step-enter-from {
    opacity: 0;
    transform: translateY(20px);
}
.fade-step-leave-to {
    opacity: 0;
}
.file-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gray-100);
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
}
.text-muted {
    color: var(--gray-400);
}

/* ======================================================= */
/* STYLE LANGKAH 1                                         */
/* ======================================================= */
.kpi-table .parameter-cell {
    width: 35%;
}
.kpi-table .bsc-col {
    width: 10%;
    white-space: nowrap;
}
.kpi-table .weight-col {
    width: 8%;
    text-align: center;
}
.kpi-table .actual-col-header {
    width: 20%;
}
.kpi-table .actual-col {
    width: 20%;
}
.kpi-table .achievement-col {
    width: 10%;
    text-align: center;
}
.kpi-table .score-col {
    width: 8%;
    text-align: center;
}

.actual-col .form-control-enterprise {
    width: 100% !important;
    text-align: right;
}
.actual-col .input-unit {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}
.actual-col .input-group-actual {
    position: relative;
}

:deep(.currency-lola) {
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.actual-col .currency-input {
    font-size: 0.6rem;
    font-variant-numeric: tabular-nums;
}

.kpi-table {
    width: 100%;
    border-collapse: collapse;
}
.kpi-table th,
.kpi-table td {
    text-align: left;
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
}
.kpi-table th {
    color: var(--gray-600);
    font-size: 0.875rem;
}
.kpi-table tbody tr:last-child td {
    border-bottom: none;
}
.actual-col {
    width: 180px;
}
.input-group-actual {
    display: flex;
    align-items: center;
}
.form-control-enterprise {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-200);
    border-radius: 0.375rem;
    transition: all var(--transition-speed);
}
.form-control-enterprise:focus {
    border-color: var(--hcis-indigo-500); /* ungu Bootstrap, bisa diganti sesuai selera */
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.25); /* ungu transparan */
    outline: none;
}

.input-group-actual .form-control-enterprise {
    width: 80px;
}
.input-unit {
    margin-left: 0.5rem;
    color: var(--gray-600);
}
.badge {
    padding: 0.4em 0.8em;
    border-radius: 2rem;
    font-weight: 600;
    color: var(--white-color);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.badge-success {
    background-color: var(--success-color);
}
.badge-warning {
    background-color: var(--warning-color);
}
.badge-danger {
    background-color: var(--danger-color);
}
.score-value {
    font-weight: 700;
    background: var(--gradient-primary);

    /* Compatibility */
    -webkit-background-clip: text; /* Untuk Chrome, Safari lama */
    background-clip: text; /* Properti standar */

    -webkit-text-fill-color: transparent; /* Supaya teks kelihatan transparan */
    color: transparent; /* Fallback standar */
}

.btn-upload-source {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--white-color);
    border: 1px solid var(--gray-200);
    color: var(--gray-600);
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    cursor: pointer;
    font-weight: 500;
    transition: all var(--transition-speed);
}
.btn-upload-source:hover {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    border-color: var(--primary-color);
}
.data-source-actions {
    display: flex;
    justify-content: center;
}
.btn-action-icon {
    background: none;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-speed);
}
.btn-preview {
    color: var(--primary-color);
}
.btn-preview:hover {
    background-color: var(--primary-light);
}
.btn-remove {
    color: var(--danger-color);
}
.btn-remove:hover {
    background-color: var(--hcis-red-100);
}

.detail-separator {
    text-align: center;
    color: var(--gray-400);
    font-weight: 500;
    margin-bottom: 1.5rem;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 1px;
}
.pica-instruction {
    margin-bottom: 1.5rem;
    color: var(--gray-600);
    text-align: center;
    background-color: var(--gray-50);
    padding: 0.75rem;
    border-radius: 0.5rem;
}
.pica-list-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

/* ======================================================= */
/* STYLE LANGKAH 3                                         */
/* ======================================================= */
.final-review-container {
    margin-bottom: 2.5rem;
}
.check-all-container {
    padding: 1rem;
    background-color: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}
.review-items-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.review-item-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    background-color: var(--white-color);
    transition: all var(--transition-speed) ease;
    border-left: 5px solid var(--gray-200);
}
.review-item-card.is-reviewed {
    border-left: 5px solid var(--success-color);
    background-color: var(--hcis-green-50);
}
.review-card-checkbox {
    flex-shrink: 0;
}
.review-card-main {
    flex-grow: 1;
}
.review-card-title {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-color);
}
.review-card-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}
.info-block span {
    font-size: 0.8rem;
    color: var(--gray-600);
    display: block;
    margin-bottom: 0.25rem;
}
.info-block p {
    margin: 0;
    font-weight: 500;
    display: flex;
    align-items: center;
}
.info-block .score-value {
    font-size: 1.1rem;
    font-weight: 700;
}
.info-block .file-chip {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}
.file-chip-button {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    cursor: pointer;
    text-align: left;
}
.file-chip-button .file-chip:hover {
    background-color: var(--gray-200);
}
.custom-checkbox {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.custom-checkbox .checkmark {
    position: relative;
    height: 22px;
    width: 22px;
    background-color: var(--gray-100);
    border: 2px solid var(--gray-400);
    border-radius: 0.375rem;
    transition: all var(--transition-speed);
}
.custom-checkbox:hover input ~ .checkmark {
    background-color: var(--gray-200);
}
.custom-checkbox input:checked ~ .checkmark {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
.custom-checkbox .checkmark:after {
    content: '';
    position: absolute;
    display: none;
    left: 7px;
    top: 2px;
    width: 6px;
    height: 12px;
    border: solid white;
    border-width: 0 3px 3px 0;
    transform: rotate(45deg);
}
.custom-checkbox input:checked ~ .checkmark:after {
    display: block;
}
.custom-checkbox .label-text {
    margin-left: 0.75rem;
    font-weight: 600;
    color: var(--text-color);
}
.review-section {
    margin-top: 2.5rem;
}
.review-section h4 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
}
.pica-review-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.pica-review-item {
    background-color: var(--gray-50);
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid var(--danger-color);
}
.pica-review-title {
    font-weight: bold;
    margin: 0 0 0.5rem 0;
}
.pica-review-item ul {
    list-style-type: none;
    padding-left: 0;
    margin: 0;
    font-size: 0.95rem;
}
.pica-review-item li {
    margin-bottom: 1rem;
}
.pica-review-item li strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-color);
}
.pica-text-container p {
    margin: 0;
    line-height: 1.6;
    color: var(--gray-600);
}
.btn-show-more {
    background: none;
    border: none;
    color: var(--primary-color);
    font-weight: 600;
    cursor: pointer;
    padding: 0.25rem 0;
    margin-top: 0.5rem;
    font-size: 0.875rem;
}
.btn-show-more:hover {
    text-decoration: underline;
}

/* ======================================================= */
/* [CSS BARU] STYLE TAMPILAN KARTU UNTUK MOBILE            */
/* ======================================================= */

/* Bootstrap-like classes untuk kontrol responsif, jika belum ada */
/* Anda mungkin sudah memiliki ini jika menggunakan Bootstrap */
@media (min-width: 992px) {
    .d-lg-none {
        display: none !important;
    }
}
@media (max-width: 991.98px) {
    .d-none {
        display: none !important;
    }
    .d-lg-block {
        display: block !important;
    }
}

.kpi-list-mobile {
    display: flex;
    flex-direction: column;
    gap: 1.5rem; /* Jarak antar kartu */
}

.kpi-card {
    background-color: var(--white-color);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    transition: box-shadow var(--transition-speed) ease;
    overflow: hidden;
    border-left: 5px solid var(--primary-color);
}

.kpi-card-reject {
    border-left: 5px solid var(--hcis-legacy-bootstrap-danger);
}

.kpi-card:focus-within {
    box-shadow: var(--shadow-md);
    border-left-color: var(--primary-dark);
}

.kpi-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background-color: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    gap: 1rem;
}

.kpi-parameter-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-color);
}

.kpi-weight-badge {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.kpi-card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.kpi-input-section,
.kpi-evidence-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kpi-input-section label,
.kpi-evidence-section label {
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.875rem;
}

.kpi-input-section .form-control-enterprise {
    width: 100%; /* Disesuaikan agar input mengambil lebar penuh */
    padding: 0.75rem;
    font-size: 1rem;
}

.kpi-input-section .input-group-actual {
    width: 100%;
}

.kpi-details-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    background-color: var(--gray-50);
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;
}

.detail-item .detail-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    display: block;
    margin-bottom: 0.25rem;
}

.detail-item .detail-value {
    margin: 0;
    font-weight: 600;
    color: var(--text-color);
    font-size: 1rem;
}

.detail-item .detail-value.score-value {
    font-size: 1.1rem;
    color: var(--primary-dark);
}

.btn-upload-source-mobile {
    width: 100%;
    padding: 0.75rem;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    border: 1px dashed var(--primary-color);
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-speed);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-upload-source-mobile:hover {
    background-color: var(--primary-color);
    color: var(--white-color);
    border-style: solid;
}

.kpi-evidence-section .data-source-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    background: var(--gray-100);
    padding: 0.5rem 0.5rem 0.5rem 1rem;
    border-radius: 0.5rem;
}

.kpi-evidence-section .file-chip {
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding-right: 0.5rem;
}

/* Penyesuaian lain untuk mobile */
@media (max-width: 991.98px) {
    .modern-stepper {
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
    }
    .d-evo {
        display: none;
    }
    .stepper-line {
        display: none;
    }
    .step {
        justify-content: flex-start;
        gap: 1rem;
    }
    .step-label {
        text-align: left;
    }
    .summary-metrics-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .summary-metric:not(:last-child)::after {
        display: none;
    }
    .content-card {
        padding: 1.5rem;
    }
    .pica-list-container {
        grid-template-columns: 1fr; /* Jadi full 100% (col-12) */
    }

    .kpi-card-header-reject {
        background: var(--hcis-legacy-bootstrap-danger);
    }

    .kpi-card-header-reject h5 {
        color: #000300 !important;
    }

    .bg-rejected {
        background-color: var(--hcis-legacy-bootstrap-danger) !important;
        color: #ed0e21 !important;
    }
}

/* ============================================================= */
/* PENYEMPURNAAN UI/UX — header per-step, tombol, & responsivitas */
/* ============================================================= */
.step-content-header {
    flex-wrap: wrap;
    gap: 0.75rem 1.5rem;
}
.step-content-header h3 {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 1.4rem;
    line-height: 1.3;
}
.step-badge {
    align-self: flex-start;
    box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.18);
    letter-spacing: 0.01em;
}

/* Tombol navigasi: micro-interaction + fokus aksesibel */
.action-bar .btn-primary,
.action-bar .btn-secondary,
.action-bar .btn-outline,
.action-bar .btn-success,
.action-bar .btn-danger {
    justify-content: center;
    box-shadow: var(--shadow-sm);
}
.action-bar button:active:not(:disabled) {
    transform: translateY(1px);
}
.action-bar .btn-primary:hover:not(:disabled) {
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.28);
}
.action-bar .btn-danger:hover:not(:disabled) {
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.28);
}
.action-bar button:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Scroll tabel yang halus di layar sempit */
.table-responsive {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

/* Tablet ke bawah: header step menumpuk rapi */
@media (max-width: 767.98px) {
    .step-content-header {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
    }
    .step-content-header h3 {
        font-size: 1.2rem;
    }
    .detail-separator {
        font-size: 1.05rem;
    }
}

/* Ponsel: action bar jadi bar bawah yang lengket & tombol seukuran jempol */
@media (max-width: 575.98px) {
    .content-card {
        padding: 1.1rem;
        border-radius: 0.9rem;
    }
    .action-bar {
        position: sticky;
        bottom: 0;
        margin: 1.5rem -1.1rem -1.1rem;
        padding: 0.85rem 1.1rem calc(0.85rem + env(safe-area-inset-bottom, 0px));
        background: var(--white-color);
        border-top: 1px solid var(--gray-200);
        box-shadow: 0 -6px 16px rgba(0, 0, 0, 0.06);
        gap: 0.75rem;
        flex-wrap: wrap;
        z-index: 20;
    }
    .action-bar .spacer {
        display: none;
    }
    .action-bar > button {
        flex: 1 1 45%;
    }
    .action-bar > div:not(.spacer) {
        flex: 1 1 45%;
    }
    .action-bar > div:not(.spacer):empty {
        display: none;
    }
    .action-bar > div:not(.spacer) > button {
        width: 100%;
    }
    .action-bar button {
        padding: 0.8rem 1rem;
        font-size: 0.95rem;
    }
}
</style>
