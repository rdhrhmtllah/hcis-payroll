<template>
    <div class="approval-matrix-tab overflow-hidden" style="background: var(--surface-soft, #f8fafc)">
        <!-- Belum pilih grup -->
        <div v-if="!grupId" class="empty-state p-4 text-center">
            <i class="bi bi-arrow-up-circle fs-2 d-block mb-2 text-muted"></i>
            <p class="text-muted">Pilih Grup Fitur di atas untuk melihat dan mengelola alur persetujuan.</p>
        </div>

        <div v-else>
            <approval-tabs
                layout="sidebar"
                title="Opsi Matrix"
                :tabs="sidebarTabs"
                :activeTab="activeSubTab"
                @tab-change="setActiveSubTab"
            >
                <!-- ============ TAB: SUMMARY ============ -->
                <template #summary>
                    <ApprovalDashboard
                        :summary="approvalSummary"
                        :loading="summaryLoading"
                        :error="summaryError"
                        :featureName="selectedFeatureName"
                        :isSystemAdmin="capabilities.isSystemAdmin"
                        :canAssign="capabilities.approvalMatrix.canAssign"
                        @refresh="fetchApprovalSummary(true)"
                        @open-flow="openDashboardFlow"
                        @manage-step="openDashboardStep"
                        @open-config="openDashboardConfig"
                        @open-history="setActiveSubTab('history')"
                    />
                </template>

                <!-- ============ TAB: DAFTAR CONFIGS ============ -->
                <template #configs>
                    <div v-if="capabilities.isSystemAdmin" class="stitch-card p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                            <div>
                                <h5 class="text-dark fw-bold m-0">
                                    <i class="bi bi-diagram-3 me-2"></i>Daftar Approval Config
                                </h5>
                                <p class="small text-muted m-0">
                                    Pilih config untuk melihat step matrix & kelola assignment approver.
                                    <span v-if="!capabilities.approvalMatrix.canAssign" class="badge-view-only ms-1"
                                        >view-only</span
                                    >
                                </p>
                            </div>
                            <div v-if="capabilities.isSystemAdmin" class="d-flex gap-2">
                                <el-button size="small" @click="showJenisModal = true">
                                    <i class="bi bi-bezier2 me-1"></i> Jenis Approval
                                </el-button>
                                <el-button type="primary" size="small" @click="openConfigModal(null)">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Config
                                </el-button>
                            </div>
                        </div>

                        <ModernFilter
                            :config="searchFilterConfig"
                            :filters="searchFilter"
                            :resultsCount="configs.length"
                            @filters-update="handleSearch"
                        />

                        <div class="alert-notice mb-3">
                            <strong>Approval Config adalah konteks alur, Source Assignment adalah penyimpanan datanya.</strong>
                            Jika beberapa config memakai source table yang sama, perubahan assignment akan berlaku pada
                            semua config tersebut.
                        </div>

                        <div v-if="unboundConfigs.length" class="unbound-panel mb-3">
                            <div>
                                <strong>Config Belum Terikat</strong>
                                <p class="small text-muted mb-0">Pilih config yang memang akan digunakan oleh {{ selectedFeatureName }}.</p>
                            </div>
                            <div class="unbound-list">
                                <div v-for="item in unboundConfigs" :key="item.Id" class="unbound-item">
                                    <div>
                                        <div class="fw-semibold">{{ item.Kode_Config }}</div>
                                        <div class="small text-muted">{{ item.Nama_Config || `${item.total_step} step approval` }}</div>
                                    </div>
                                    <el-button size="small" type="primary" plain @click="bindUnboundConfig(item)">
                                        Gunakan pada Grup Ini
                                    </el-button>
                                </div>
                            </div>
                        </div>

                        <TableNoir
                            :data="configs"
                            :columns="configColumns"
                            :loading="loading"
                            :showActions="false"
                            :showDelete="false"
                            :showPagination="false"
                            :fullPagination="[]"
                            rowKey="Id"
                            emptyMessage="Belum ada approval config untuk grup ini."
                            :clickable="true"
                            @row-click="selectConfig"
                        >
                            <template #column-Kode_Config="{ item }">
                                <div class="fw-semibold">{{ item.Kode_Config }}</div>
                                <div v-if="item.Nama_Config" class="small text-muted">{{ item.Nama_Config }}</div>
                            </template>

                            <template #column-grup="{ item }">
                                <span v-if="item.unassigned" class="pill pill-warning">Belum terikat</span>
                                <span v-else class="pill pill-info">{{
                                    item.Nama_Grup_Fitur || item.Kode_Grup_Fitur
                                }}</span>
                            </template>

                            <template #column-source_tables="{ item }">
                                <div v-if="item.source_tables?.length" class="d-flex flex-column gap-1">
                                    <div v-for="table in item.source_tables" :key="table" class="d-flex gap-1 align-items-center">
                                        <span class="pill pill-neutral font-monospace">{{ table }}</span>
                                    </div>
                                </div>
                                <span v-else class="small text-muted">Tidak ada source assignment</span>
                            </template>

                            <template #column-total_step="{ item }">
                                <span class="pill pill-neutral">{{ item.total_step }} step</span>
                            </template>

                            <template #column-Flag_Aktif="{ item }">
                                <span :class="['pill', item.Flag_Aktif === 'Y' ? 'pill-success' : 'pill-muted']">
                                    {{ item.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </template>

                            <template #column-aksi="{ item }">
                                <div class="d-flex gap-1 flex-wrap" @click.stop>
                                    <button class="btn-action" title="Lihat matrix" @click="selectConfig(item)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <template v-if="capabilities.isSystemAdmin">
                                        <button class="btn-action" title="Edit config" @click="openConfigModal(item)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            v-if="item.Flag_Aktif === 'Y'"
                                            class="btn-action btn-danger-soft"
                                            title="Nonaktifkan config"
                                            @click="deactivateConfig(item)"
                                        >
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </TableNoir>

                        <!-- Step matrix config terpilih -->
                        <div v-if="selectedConfig" class="mt-4 border-top pt-4">
                            <ConfigStepMatrix
                                :key="selectedConfig.Id"
                                :config="selectedConfig"
                                :capabilities="capabilities"
                                :grupId="grupId"
                                @refresh-configs="onConfigStructureChanged"
                                @manage-step="handleManageStep"
                            />
                        </div>
                    </div>
                    <div v-else class="stitch-card p-4">
                        <FeatureApprovalFlow
                            :configs="activeConfigs"
                            :featureName="selectedFeatureName"
                            :selectedStepId="selectedStepIdForAssignment"
                            :canEdit="capabilities.approvalMatrix.canAssign"
                            :grupId="grupId"
                            @manage-step="handleManageStep"
                        />
                    </div>
                </template>

                <!-- ============ TAB: SINGLE ASSIGNMENT ============ -->
                <template #single>
                    <div class="stitch-card p-4">
                        <div class="mb-4">
                            <h5 class="text-dark fw-bold m-0">
                                <i class="bi bi-person-fill me-2 text-indigo"></i>Single Assignment Approver
                            </h5>
                            <p class="small text-muted m-0">
                                Atur approver spesifik (requester -> approver) pada posisi alur yang dipilih.
                            </p>
                        </div>

                        <!-- Context selectors for System Admin -->
                        <div v-if="capabilities.isSystemAdmin && !selectedStepDetail" class="context-selector-bar mb-3 p-3 bg-light rounded-3 border">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label-sm fw-bold">Pilih Approval Config</label>
                                    <el-select
                                        v-model="selectedConfigIdForAssignment"
                                        class="w-100"
                                        placeholder="Pilih config..."
                                        filterable
                                        @change="onConfigChangeForAssignment"
                                    >
                                        <el-option
                                            v-for="c in configs"
                                            :key="c.Id"
                                            :label="configOptionLabel(c)"
                                            :value="c.Id"
                                        />
                                    </el-select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label-sm fw-bold">Pilih Step / Posisi Alur</label>
                                    <el-select
                                        v-model="selectedStepIdForAssignment"
                                        class="w-100"
                                        placeholder="Pilih step..."
                                        filterable
                                        :disabled="!stepsForAssignment.length"
                                        @change="onStepChangeForAssignment"
                                    >
                                        <el-option
                                            v-for="s in stepsForAssignment"
                                            :key="s.Id"
                                            :label="stepOptionLabel(s)"
                                            :value="s.Id"
                                        />
                                    </el-select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-outline-secondary w-100" @click="clearAssignmentContext">
                                        <i class="bi bi-x-circle me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>


                        <AssignmentContextCard
                            :config="selectedAssignmentConfig"
                            :step="selectedStepDetail"
                            :featureName="selectedFeatureName"
                            :isSystemAdmin="capabilities.isSystemAdmin"
                            :activeMode="activeSubTab"
                            :canAssign="capabilities.approvalMatrix.canAssign"
                            @change-mode="setActiveSubTab"
                        />

                        <div v-if="!selectedStepDetail" class="empty-state py-4 text-center text-muted">
                            <i class="bi bi-diagram-3-fill fs-2 mb-2 d-block" style="opacity: 0.5"></i>
                            <p class="small">
                                Pilih posisi pada alur persetujuan fitur untuk mulai mengelola approver per requester.
                            </p>
                        </div>

                        <div v-else>
                            <!-- SINGLE EDIT MODE (Draft Editor) -->
                            <div v-if="singleEditMode" class="edit-panel border rounded-3 p-4 bg-white shadow-xs animate__animated animate__fadeIn">
                                <div class="edit-header mb-4 border-bottom pb-3 d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <button class="btn-back" @click="singleEditMode = false">
                                            <i class="bi bi-arrow-left"></i>
                                        </button>
                                        <div>
                                            <span class="badge bg-indigo-soft text-indigo mb-1 text-uppercase" style="font-size: 0.65rem;">Requester Target</span>
                                            <h5 class="fw-bold text-dark m-0">{{ editing.nama }}</h5>
                                            <div class="small text-muted font-monospace text-xs">ID Karyawan: {{ editing.kode }}</div>
                                        </div>
                                    </div>
                                    <div class="text-end" v-if="selectedStepDetail">
                                        <span class="badge bg-light text-secondary border">Step {{ selectedStepDetail.Approval_Order }} Alur</span>
                                    </div>
                                </div>

                                <div class="alert-custom-info p-3 mb-4 small d-flex gap-2">
                                    <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                                    <div>
                                        Urutan kartu alur di bawah menggambarkan jalur persetujuan. Klik tombol panah untuk memindahkan alur atau tombol silang untuk menghapus.
                                    </div>
                                </div>

                                <!-- Vertical Visual Timeline stack -->
                                <div class="timeline-workflow mb-4" v-if="draftApprovers.length">
                                    <div
                                        v-for="(approver, idx) in draftApprovers"
                                        :key="idx"
                                        class="timeline-node d-flex align-items-stretch"
                                    >
                                        <!-- Vertical connector dot -->
                                        <div class="timeline-node-dot">
                                            <span class="dot-inner">{{ idx + 1 }}</span>
                                        </div>
                                        
                                        <!-- Timeline node card -->
                                        <div class="timeline-node-card flex-grow-1 p-3 border rounded-3 d-flex align-items-center justify-content-between ms-3 mb-3 shadow-xs">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="user-avatar-initials-sm">
                                                    {{ getUserInitials(approver.nama) }}
                                                </div>
                                                <div>
                                                    <span class="badge-level-approver mb-1">Level {{ idx + 1 }} Approver</span>
                                                    <div class="fw-bold text-dark text-sm">{{ approver.nama || approver.kode }}</div>
                                                    <div class="text-muted text-xxs font-monospace">{{ approver.kode }}</div>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-1">
                                                <button
                                                    class="btn-action border-0 rounded-2 hover-bg-indigo"
                                                    :disabled="idx === 0"
                                                    title="Pindahkan ke Level Atas"
                                                    @click="moveDraft(idx, -1)"
                                                >
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                                <button
                                                    class="btn-action border-0 rounded-2 hover-bg-indigo"
                                                    :disabled="idx === draftApprovers.length - 1"
                                                    title="Pindahkan ke Level Bawah"
                                                    @click="moveDraft(idx, 1)"
                                                >
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                                <button
                                                    class="btn-action border-0 rounded-2 hover-bg-danger text-danger"
                                                    title="Hapus Dari Alur"
                                                    @click="draftApprovers.splice(idx, 1)"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="empty-state empty-timeline-state py-4 border rounded-3 bg-light mb-3"
                                >
                                    <i class="bi bi-diagram-3 fs-2 opacity-50 mb-2"></i>
                                    <span class="small text-muted d-block fst-italic">Belum ada approver dalam alur persetujuan ini.</span>
                                    <span class="text-xxs text-muted d-block mt-1">Gunakan dropdown di bawah untuk menambahkan approver alur level 1.</span>
                                </div>

                                <!-- Add Approver Card -->
                                <div class="add-approver-panel mb-4 shadow-xs">
                                    <h6 class="fw-bold text-dark text-xs mb-2">
                                        <i class="bi bi-plus-circle-fill text-success me-1"></i>Tambah Approver Baru ke Alur
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <el-select
                                            v-model="addApproverKode"
                                            filterable
                                            remote
                                            clearable
                                            placeholder="Ketik nama / Kode Karyawan untuk mencari..."
                                            :remote-method="searchKaryawanOptions"
                                            :loading="karyawanLoading"
                                            style="width: 100%; max-width: 400px;"
                                            @change="addApprover"
                                        >
                                            <el-option
                                                v-for="k in karyawanOptions"
                                                :key="k.Kode_Karyawan"
                                                :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                :value="k.Kode_Karyawan"
                                                :disabled="
                                                    k.Kode_Karyawan === editing.kode ||
                                                    draftApprovers.some((a) => a.kode === k.Kode_Karyawan)
                                                "
                                            />
                                        </el-select>
                                    </div>
                                </div>

                                <!-- Logs Note -->
                                <div class="mb-4">
                                    <label class="form-label-sm fw-bold">Catatan Audit Perubahan (Opsional):</label>
                                    <el-input
                                        v-model="catatan"
                                        type="textarea"
                                        :rows="2"
                                        maxlength="500"
                                        placeholder="Tulis alasan perubahan alur approval ini..."
                                    />
                                </div>

                                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                    <el-button @click="singleEditMode = false" :disabled="assignmentSaving">Batal</el-button>
                                    <el-button type="primary" :loading="assignmentSaving" @click="saveFlow">
                                        <i class="bi bi-check-lg me-1"></i>Simpan Alur
                                    </el-button>
                                </div>
                            </div>

                            <!-- VIEW ASSIGNMENTS LIST MODE -->
                            <div v-else>
                                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                    <el-input
                                        v-model="assignmentSearch"
                                        placeholder="Cari requester (nama / kode / NIK)..."
                                        clearable
                                        style="max-width: 320px"
                                        @input="debouncedFetch"
                                    >
                                        <template #prefix><i class="bi bi-search"></i></template>
                                    </el-input>

                                    <el-select
                                        v-if="capabilities.approvalMatrix.canAssign"
                                        v-model="newRequesterKode"
                                        filterable
                                        remote
                                        clearable
                                        placeholder="+ Atur requester baru..."
                                        :remote-method="searchRequesterOptions"
                                        :loading="requesterLoading"
                                        style="max-width: 320px; width: 100%"
                                        @change="startEditNewRequester"
                                    >
                                        <el-option
                                            v-for="k in requesterOptions"
                                            :key="k.Kode_Karyawan"
                                            :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                            :value="k.Kode_Karyawan"
                                        />
                                    </el-select>

                                    <span class="ms-auto small text-muted"
                                        >{{ assignmentPagination.total }} requester</span
                                    >
                                </div>

                                <div v-if="assignmentLoading" class="text-center py-4">
                                    <div
                                        class="spinner-border spinner-border-sm text-indigo"
                                        style="color: #6366f1"
                                    ></div>
                                    <p class="text-muted small mt-2">Memuat assignment...</p>
                                </div>

                                <div
                                    v-else-if="!assignmentItems.length"
                                    class="empty-state text-center py-4 text-muted border rounded bg-white"
                                >
                                    <i class="bi bi-inbox fs-2 mb-2 d-block"></i>
                                    <p class="small">Belum ada assignment approver di step ini.</p>
                                </div>

                                <div v-else class="requester-list">
                                    <div
                                        v-for="item in assignmentItems"
                                        :key="item.requester.kode"
                                        class="requester-row p-3 border rounded-3 bg-white mb-2 d-flex align-items-center gap-3 shadow-xs"
                                    >
                                        <div class="d-flex align-items-center gap-2" style="min-width: 200px">
                                            <div class="user-avatar-initials-xs">
                                                {{ getUserInitials(item.requester.nama) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark text-sm">{{ item.requester.nama }}</div>
                                                <div class="small text-muted font-monospace text-xs">{{ item.requester.kode }}</div>
                                            </div>
                                        </div>
                                        <div class="approver-chips flex-grow-1 d-flex align-items-center flex-wrap gap-2">
                                            <template v-for="(approver, idx) in item.approvers" :key="approver.pk">
                                                <i v-if="idx > 0" class="bi bi-chevron-right text-muted mx-1" style="font-size: 0.7rem;"></i>
                                                <span
                                                    class="chip bg-indigo-soft text-indigo border shadow-xs"
                                                    :class="{ 'chip-inactive': approver.approver_aktif !== 'Y' }"
                                                    :title="approver.approver_aktif !== 'Y' ? 'Karyawan tidak aktif: ' + (approver.approver_nama || approver.approver_kode) : (approver.approver_nama || approver.approver_kode)"
                                                >
                                                    <span class="chip-order">{{ approver.order_flow }}</span>
                                                    <span class="chip-name">{{ approver.approver_nama || approver.approver_kode }}</span>
                                                </span>
                                            </template>
                                        </div>
                                        <button
                                            v-if="capabilities.approvalMatrix.canAssign"
                                            class="btn-edit-flow"
                                            @click="startEdit(item)"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <div
                                    v-if="assignmentPagination.total_pages > 1"
                                    class="d-flex justify-content-center gap-2 mt-4"
                                >
                                    <el-button
                                        size="small"
                                        :disabled="assignmentPagination.page <= 1"
                                        @click="goPage(assignmentPagination.page - 1)"
                                    >
                                        <i class="bi bi-chevron-left"></i>
                                    </el-button>
                                    <span class="small align-self-center text-muted">
                                        Hal {{ assignmentPagination.page }} / {{ assignmentPagination.total_pages }}
                                    </span>
                                    <el-button
                                        size="small"
                                        :disabled="assignmentPagination.page >= assignmentPagination.total_pages"
                                        @click="goPage(assignmentPagination.page + 1)"
                                    >
                                        <i class="bi bi-chevron-right"></i>
                                    </el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ============ TAB: BULK ASSIGNMENT ============ -->
                <template #bulk>
                    <div class="stitch-card p-4">
                        <div class="mb-4">
                            <h5 class="text-dark fw-bold m-0">
                                <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Bulk Assignment Approver
                            </h5>
                            <p class="small text-muted m-0">
                                Assign satu approver ke banyak requester sekaligus pada tahapan terpilih.
                            </p>
                        </div>

                        <!-- Context selectors for System Admin -->
                        <div v-if="capabilities.isSystemAdmin && !selectedStepDetail" class="context-selector-bar mb-3 p-3 bg-light rounded-3 border">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label-sm fw-bold">Pilih Approval Config</label>
                                    <el-select
                                        v-model="selectedConfigIdForAssignment"
                                        class="w-100"
                                        placeholder="Pilih config..."
                                        filterable
                                        @change="onConfigChangeForAssignment"
                                    >
                                        <el-option
                                            v-for="c in configs"
                                            :key="c.Id"
                                            :label="configOptionLabel(c)"
                                            :value="c.Id"
                                        />
                                    </el-select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label-sm fw-bold">Pilih Step / Posisi Alur</label>
                                    <el-select
                                        v-model="selectedStepIdForAssignment"
                                        class="w-100"
                                        placeholder="Pilih step..."
                                        filterable
                                        :disabled="!stepsForAssignment.length"
                                        @change="onStepChangeForAssignment"
                                    >
                                        <el-option
                                            v-for="s in stepsForAssignment"
                                            :key="s.Id"
                                            :label="stepOptionLabel(s)"
                                            :value="s.Id"
                                        />
                                    </el-select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-outline-secondary w-100" @click="clearAssignmentContext">
                                        <i class="bi bi-x-circle me-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>


                        <AssignmentContextCard
                            :config="selectedAssignmentConfig"
                            :step="selectedStepDetail"
                            :featureName="selectedFeatureName"
                            :isSystemAdmin="capabilities.isSystemAdmin"
                            :activeMode="activeSubTab"
                            :canAssign="capabilities.approvalMatrix.canAssign"
                            @change-mode="setActiveSubTab"
                        />
                        <div v-if="!selectedStepDetail" class="empty-state py-4 text-center text-muted">
                            <i class="bi bi-lightning-charge-fill fs-2 mb-2 d-block" style="opacity: 0.5"></i>
                            <p class="small">Pilih posisi pada alur persetujuan fitur untuk mulai bulk assignment.</p>
                        </div>

                        <div v-else class="bulk-panel bg-transparent p-0 border-0">
                            <!-- Toggle Action Modes -->
                            <div class="mb-4">
                                <label class="form-label-sm fw-bold">Pilih Tipe Aksi Bulk:</label>
                                <div class="btn-group w-100 border rounded-3 p-1 bg-light shadow-xs" role="group">
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-2 px-3 fw-bold text-xs"
                                        :class="bulkActionMode === 'sequence' ? 'bg-white shadow-xs text-indigo' : 'text-secondary'"
                                        @click="bulkActionMode = 'sequence'"
                                    >
                                        <i class="bi bi-diagram-3-fill me-1 text-primary"></i> Assign Paket Alur (Sequence)
                                    </button>
                                    <button
                                        type="button"
                                        class="btn border-0 rounded-2 py-2 px-3 fw-bold text-xs"
                                        :class="bulkActionMode === 'substitute' ? 'bg-white shadow-xs text-indigo' : 'text-secondary'"
                                        @click="bulkActionMode = 'substitute'"
                                    >
                                        <i class="bi bi-arrow-left-right me-1 text-success"></i> Ganti Approver (Resign / Mutasi)
                                    </button>
                                </div>
                            </div>

                            <!-- Mode: Sequence Assign (2-column layout) -->
                            <div v-if="bulkActionMode === 'sequence'" class="row g-4 animate__animated animate__fadeIn">
                                <!-- Left side: Target Requesters -->
                                <div class="col-lg-7">
                                    <div class="border rounded-3 p-3 bg-white shadow-xs">
                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                            <h6 class="fw-bold text-dark m-0">
                                                <i class="bi bi-people-fill text-indigo me-1"></i>Karyawan Penerima (Requester)
                                            </h6>
                                            <!-- Toggle Mode -->
                                            <div class="btn-group btn-group-sm border rounded-2 p-1 bg-light" role="group">
                                                <button
                                                    type="button"
                                                    class="btn border-0 rounded-2 py-1 px-3 fw-bold text-xs"
                                                    :class="bulkSelectMode === 'manual' ? 'bg-white shadow-xs text-indigo' : 'text-secondary'"
                                                    @click="bulkSelectMode = 'manual'"
                                                >
                                                    <i class="bi bi-cursor-fill me-1"></i> Pilih Manual
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn border-0 rounded-2 py-1 px-3 fw-bold text-xs"
                                                    :class="bulkSelectMode === 'filter' ? 'bg-white shadow-xs text-indigo' : 'text-secondary'"
                                                    @click="bulkSelectMode = 'filter'"
                                                >
                                                    <i class="bi bi-funnel-fill me-1"></i> Filter Kategori
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Mode: Pilih Manual -->
                                        <div v-if="bulkSelectMode === 'manual'" class="py-2">
                                            <div class="alert alert-info border-0 rounded-3 p-2.5 mb-3 small d-flex align-items-start gap-2">
                                                <i class="bi bi-info-circle-fill text-info fs-5"></i>
                                                <div>Pilih satu atau lebih karyawan penerima alur approval ini secara manual.</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label-sm fw-bold">Pilih Karyawan Target:</label>
                                                <el-select
                                                    v-model="bulk.requesterKodes"
                                                    multiple
                                                    filterable
                                                    remote
                                                    placeholder="Ketik nama / Kode / NIK..."
                                                    :remote-method="searchRequesterOptions"
                                                    :loading="requesterLoading"
                                                    style="width: 100%"
                                                >
                                                    <el-option
                                                        v-for="k in requesterOptions"
                                                        :key="k.Kode_Karyawan"
                                                        :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                        :value="k.Kode_Karyawan"
                                                        :disabled="bulkDraftApprovers.some((a) => a.kode === k.Kode_Karyawan)"
                                                    />
                                                </el-select>
                                                <div class="form-text small text-muted mt-2" v-if="bulk.requesterKodes.length">
                                                    Terpilih: <strong class="text-indigo">{{ bulk.requesterKodes.length }}</strong> karyawan.
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mode: Filter Kategori -->
                                        <div v-else class="py-2 animate__animated animate__fadeIn">
                                            <div
                                                v-if="filterOptionsEmpty"
                                                class="alert alert-warning border-0 rounded-3 p-3 mb-3 small shadow-xs"
                                            >
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                Opsi filter kosong — kemungkinan scope role Anda untuk grup ini belum
                                                dikonfigurasi.
                                            </div>
                                            <!-- Filter Grid -->
                                            <div class="filter-panel-grid border rounded-3 p-3 bg-light mb-3 shadow-xs">
                                                <div class="row g-2">
                                                    <div class="col-sm-6">
                                                        <label class="form-label-sm fw-semibold">Lokasi (Cabang)</label>
                                                        <el-select
                                                            v-model="bulkFilters.cabang"
                                                            filterable
                                                            clearable
                                                            placeholder="Semua Lokasi"
                                                            style="width: 100%"
                                                            @change="applyKaryawanFilters"
                                                        >
                                                            <el-option
                                                                v-for="c in filterOptions.cabang"
                                                                :key="c.value"
                                                                :label="c.label"
                                                                :value="c.value"
                                                            />
                                                        </el-select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label-sm fw-semibold">Divisi</label>
                                                        <el-select
                                                            v-model="bulkFilters.divisi_id"
                                                            filterable
                                                            clearable
                                                            placeholder="Semua Divisi"
                                                            style="width: 100%"
                                                            @change="() => { bulkFilters.sub_divisi_id = ''; applyKaryawanFilters(); }"
                                                        >
                                                            <el-option
                                                                v-for="d in filterOptions.divisi"
                                                                :key="d.value"
                                                                :label="d.label"
                                                                :value="d.value"
                                                            />
                                                        </el-select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label-sm fw-semibold">Department / Sub-divisi</label>
                                                        <el-select
                                                            v-model="bulkFilters.sub_divisi_id"
                                                            filterable
                                                            clearable
                                                            placeholder="Semua Department"
                                                            style="width: 100%"
                                                            @change="applyKaryawanFilters"
                                                        >
                                                            <el-option
                                                                v-for="sd in filteredSubDivisiOptions"
                                                                :key="sd.value"
                                                                :label="sd.label"
                                                                :value="sd.value"
                                                            />
                                                        </el-select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label-sm fw-semibold">Level</label>
                                                        <el-select
                                                            v-model="bulkFilters.level_id"
                                                            filterable
                                                            clearable
                                                            placeholder="Semua Level"
                                                            style="width: 100%"
                                                            @change="() => { bulkFilters.jabatan_id = ''; applyKaryawanFilters(); }"
                                                        >
                                                            <el-option
                                                                v-for="l in filterOptions.level"
                                                                :key="l.value"
                                                                :label="l.label"
                                                                :value="l.value"
                                                            />
                                                        </el-select>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label-sm fw-semibold">Jabatan</label>
                                                        <el-select
                                                            v-model="bulkFilters.jabatan_id"
                                                            filterable
                                                            clearable
                                                            placeholder="Semua Jabatan"
                                                            style="width: 100%"
                                                            @change="applyKaryawanFilters"
                                                        >
                                                            <el-option
                                                                v-for="j in filteredJabatanOptions"
                                                                :key="j.value"
                                                                :label="j.label"
                                                                :value="j.value"
                                                            />
                                                        </el-select>
                                                    </div>
                                                    <div class="col-sm-6 d-flex align-items-end">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-secondary w-100 py-1.5 rounded-2"
                                                            @click="resetBulkFilters"
                                                        >
                                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Bersihkan Filter
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Filter Results Table -->
                                            <div class="filter-results-box border rounded-3 bg-white overflow-hidden shadow-xs">
                                                <div class="p-2.5 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <span class="text-dark fw-bold text-xs">
                                                        Hasil: <span class="badge bg-indigo-soft text-indigo">{{ searchedFilterResults.length }}</span> karyawan cocok
                                                    </span>
                                                    <input
                                                        v-model="filterResultsSearch"
                                                        type="text"
                                                        placeholder="Cari di hasil..."
                                                        class="form-control form-control-xs py-0.5 px-2 border"
                                                        style="max-width: 140px; font-size: 0.7rem; height: 26px;"
                                                    />
                                                </div>

                                                <div v-if="filterLoading" class="text-center py-4">
                                                    <div class="spinner-border spinner-border-sm text-indigo"></div>
                                                    <p class="text-muted small mt-2">Memfilter...</p>
                                                </div>

                                                <div v-else-if="!filterResults.length" class="empty-state py-4 text-center text-muted small">
                                                    <i class="bi bi-funnel fs-3 mb-1 d-block opacity-50"></i>
                                                    Pilih filter di atas untuk mencari karyawan.
                                                </div>

                                                <div v-else class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                                                    <table class="table table-hover align-middle mb-0" style="font-size: 0.72rem;">
                                                        <thead class="table-light sticky-top" style="z-index: 10;">
                                                            <tr>
                                                                <th class="ps-3" style="width: 38px;">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="form-check-input"
                                                                        :checked="isAllFilteredSelected"
                                                                        @change="(e) => selectAllFiltered(e.target.checked)"
                                                                    />
                                                                </th>
                                                                <th>Karyawan</th>
                                                                <th>Jabatan & Organisasi</th>
                                                                <th class="pe-3">Lokasi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="emp in searchedFilterResults"
                                                                :key="emp.Kode_Karyawan"
                                                                class="cursor-pointer"
                                                                @click="toggleFilteredSelection(emp)"
                                                            >
                                                                <td class="ps-3" @click.stop>
                                                                    <input
                                                                        type="checkbox"
                                                                        class="form-check-input"
                                                                        :value="emp.Kode_Karyawan"
                                                                        v-model="bulk.requesterKodes"
                                                                    />
                                                                </td>
                                                                <td>
                                                                    <div class="fw-bold text-dark text-xs">{{ emp.Nama }}</div>
                                                                    <div class="text-muted text-xxs font-monospace">{{ emp.Kode_Karyawan }}</div>
                                                                </td>
                                                                <td>
                                                                    <div class="text-dark text-xxs">{{ emp.Jabatan }}</div>
                                                                    <div class="text-muted text-xxs">{{ emp.Divisi }}</div>
                                                                </td>
                                                                <td class="pe-3">
                                                                    <span class="badge bg-light text-secondary border text-xxs">{{ emp.Cabang_Untuk_Gaji || '—' }}</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right side: Config setup & Preview -->
                                <div class="col-lg-5">
                                    <div class="border rounded-3 p-3 bg-white shadow-xs d-flex flex-column gap-3">
                                        <h6 class="fw-bold text-dark m-0 border-bottom pb-2">
                                            <i class="bi bi-gear-fill text-warning me-1"></i>Pengaturan Paket Alur Bulk
                                        </h6>

                                        <!-- Vertical visual timeline stack -->
                                        <div class="timeline-workflow mb-2" v-if="bulkDraftApprovers.length">
                                            <div
                                                v-for="(approver, idx) in bulkDraftApprovers"
                                                :key="idx"
                                                class="timeline-node d-flex align-items-stretch"
                                            >
                                                <!-- Vertical connector dot -->
                                                <div class="timeline-node-dot">
                                                    <span class="dot-inner">{{ idx + 1 }}</span>
                                                </div>
                                                
                                                <!-- Timeline node card -->
                                                <div class="timeline-node-card flex-grow-1 p-3 border rounded-3 d-flex align-items-center justify-content-between ms-3 mb-3 shadow-xs">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="user-avatar-initials-sm">
                                                            {{ getUserInitials(approver.nama) }}
                                                        </div>
                                                        <div>
                                                            <span class="badge-level-approver mb-1">Level {{ idx + 1 }} Approver</span>
                                                            <div class="fw-bold text-dark text-sm">{{ approver.nama || approver.kode }}</div>
                                                            <div class="text-muted text-xxs font-monospace">{{ approver.kode }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex align-items-center gap-1">
                                                        <button
                                                            class="btn-action border-0 rounded-2 hover-bg-indigo"
                                                            :disabled="idx === 0"
                                                            title="Pindahkan ke Level Atas"
                                                            @click="moveBulkDraft(idx, -1)"
                                                        >
                                                            <i class="bi bi-arrow-up"></i>
                                                        </button>
                                                        <button
                                                            class="btn-action border-0 rounded-2 hover-bg-indigo"
                                                            :disabled="idx === bulkDraftApprovers.length - 1"
                                                            title="Pindahkan ke Level Bawah"
                                                            @click="moveBulkDraft(idx, 1)"
                                                        >
                                                            <i class="bi bi-arrow-down"></i>
                                                        </button>
                                                        <button
                                                            class="btn-action border-0 rounded-2 hover-bg-danger text-danger"
                                                            title="Hapus Dari Alur"
                                                            @click="bulkDraftApprovers.splice(idx, 1)"
                                                        >
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="empty-state empty-timeline-state py-4 border rounded-3 bg-light mb-3"
                                        >
                                            <i class="bi bi-diagram-3 fs-2 opacity-50 mb-2"></i>
                                            <span class="small text-muted d-block fst-italic">Belum ada approver dalam paket alur ini.</span>
                                            <span class="text-xxs text-muted d-block mt-1">Gunakan dropdown di bawah untuk menambahkan approver level 1.</span>
                                        </div>

                                        <!-- Add Approver Card -->
                                        <div class="add-approver-panel mb-3 shadow-xs">
                                            <h6 class="fw-bold text-dark text-xs mb-2">
                                                <i class="bi bi-plus-circle-fill text-success me-1"></i>Tambah Approver ke Paket Alur
                                            </h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <el-select
                                                    v-model="bulkAddApproverKode"
                                                    filterable
                                                    remote
                                                    clearable
                                                    placeholder="Ketik nama / Kode Karyawan..."
                                                    :remote-method="searchKaryawanOptions"
                                                    :loading="karyawanLoading"
                                                    style="width: 100%;"
                                                    @change="addBulkDraftApprover"
                                                >
                                                    <el-option
                                                        v-for="k in karyawanOptions"
                                                        :key="k.Kode_Karyawan"
                                                        :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                        :value="k.Kode_Karyawan"
                                                        :disabled="bulkDraftApprovers.some((a) => a.kode === k.Kode_Karyawan)"
                                                    />
                                                </el-select>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label-sm fw-bold">Catatan Perubahan (Opsional):</label>
                                            <el-input
                                                v-model="bulk.catatan"
                                                type="textarea"
                                                :rows="2"
                                                maxlength="500"
                                                placeholder="Catatan penugasan massal..."
                                            />
                                        </div>

                                        <!-- Preview Box -->
                                        <div class="preview-box border border-warning border-opacity-25 rounded-3 p-3 bg-warning-soft d-flex flex-column justify-content-between" style="min-height: 160px;">
                                            <div>
                                                <span class="badge bg-warning text-dark uppercase mb-2" style="font-size: 0.65rem;">Ringkasan Aksi</span>
                                                <p class="small text-dark mb-2 leading-relaxed" v-if="bulkDraftApprovers.length && bulk.requesterKodes.length">
                                                    Assign paket alur persetujuan: <strong class="text-indigo">{{ bulkDraftApprovers.map((a, idx) => `Level ${idx+1}: ${a.nama}`).join(' → ') }}</strong> untuk <strong>{{ bulk.requesterKodes.length }}</strong> requester terpilih.
                                                </p>
                                                <p class="small text-muted mb-0" v-else>
                                                    Tambahkan minimal 1 approver ke paket alur dan pilih requester untuk melihat pratinjau.
                                                </p>

                                                <!-- Collapsible list of affected targets -->
                                                <div v-if="bulk.requesterKodes.length" class="mt-2 pt-2 border-top border-warning border-opacity-25">
                                                    <a href="javascript:void(0)" @click="showBulkTargetsPreview = !showBulkTargetsPreview" class="text-xs text-indigo fw-bold d-flex align-items-center justify-content-between text-decoration-none">
                                                        <span>Lihat {{ bulk.requesterKodes.length }} Target Penerima</span>
                                                        <i :class="showBulkTargetsPreview ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                                                    </a>
                                                    <div v-if="showBulkTargetsPreview" class="mt-1.5 overflow-auto bg-white border rounded p-2 text-start" style="max-height: 100px;">
                                                        <div v-for="kode in bulk.requesterKodes" :key="kode" class="text-xxs text-dark font-monospace mb-1 last-border-none border-bottom pb-0.5">
                                                            {{ getRequesterNameByKode(kode) }} ({{ kode }})
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="pt-3 border-top border-warning border-opacity-10 text-end mt-3">
                                                <el-button
                                                    type="primary"
                                                    :loading="assignmentSaving"
                                                    :disabled="!bulkDraftApprovers.length || !bulk.requesterKodes.length"
                                                    @click="confirmAndApplyBulkSequence"
                                                >
                                                    <i class="bi bi-lightning me-1"></i>Terapkan Paket Alur
                                                </el-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mode: Ganti Approver -->
                            <div v-else-if="bulkActionMode === 'substitute'" class="col-12 animate__animated animate__fadeIn">
                                <div class="border rounded-3 p-4 bg-white shadow-xs mx-auto" style="max-width: 600px;">
                                    <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                                        <i class="bi bi-arrow-left-right text-success me-1"></i>Substitusi Approver Massal (Resign / Mutasi)
                                    </h6>
                                    
                                    <div class="alert alert-warning border-0 rounded-3 p-3 mb-4 small d-flex align-items-start gap-2.5">
                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                        <div>
                                            Fitur ini mencari semua alur persetujuan aktif pada step terpilih (<strong>{{ selectedStepDetail.Nama_Approval }}</strong>) yang memiliki <strong>Approver Lama</strong>, lalu menggantinya dengan <strong>Approver Baru</strong>.
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label-sm fw-bold">1. Approver Lama (X) yang Ingin Diganti:</label>
                                            <el-select
                                                v-model="bulkSubstitute.oldApproverKode"
                                                filterable
                                                remote
                                                clearable
                                                placeholder="Cari approver lama..."
                                                :remote-method="searchKaryawanOptions"
                                                :loading="karyawanLoading"
                                                style="width: 100%"
                                            >
                                                <el-option
                                                    v-for="k in karyawanOptions"
                                                    :key="k.Kode_Karyawan"
                                                    :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                    :value="k.Kode_Karyawan"
                                                />
                                            </el-select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label-sm fw-bold">2. Approver Baru (Y) sebagai Pengganti:</label>
                                            <el-select
                                                v-model="bulkSubstitute.newApproverKode"
                                                filterable
                                                remote
                                                clearable
                                                placeholder="Cari approver baru..."
                                                :remote-method="searchKaryawanOptions"
                                                :loading="karyawanLoading"
                                                style="width: 100%"
                                            >
                                                <el-option
                                                    v-for="k in karyawanOptions"
                                                    :key="k.Kode_Karyawan"
                                                    :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                                    :value="k.Kode_Karyawan"
                                                    :disabled="k.Kode_Karyawan === bulkSubstitute.oldApproverKode"
                                                />
                                            </el-select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label-sm fw-bold">3. Catatan Perubahan (Opsional):</label>
                                            <el-input
                                                v-model="bulkSubstitute.catatan"
                                                type="textarea"
                                                :rows="3"
                                                maxlength="500"
                                                placeholder="Contoh: Penggantian karena karyawan resign..."
                                            />
                                        </div>
                                    </div>

                                    <!-- Preview Box -->
                                    <div class="preview-box border border-success border-opacity-25 rounded-3 p-3 bg-success-soft d-flex flex-column justify-content-between" style="min-height: 120px;">
                                        <div>
                                            <span class="badge bg-success text-white uppercase mb-2" style="font-size: 0.65rem;">Ringkasan Substitusi</span>
                                            <p class="small text-dark mb-0 leading-relaxed" v-if="bulkSubstitute.oldApproverKode && bulkSubstitute.newApproverKode">
                                                Mengganti seluruh penugasan <strong class="text-indigo">{{ getApproverNameByKode(bulkSubstitute.oldApproverKode) }}</strong> dengan <strong class="text-success">{{ getApproverNameByKode(bulkSubstitute.newApproverKode) }}</strong> pada step <strong>{{ selectedStepDetail.Nama_Approval }}</strong>.
                                            </p>
                                            <p class="small text-muted mb-0" v-else>
                                                Pilih Approver Lama dan Approver Baru untuk melihat ringkasan penggantian.
                                            </p>
                                        </div>

                                        <div class="pt-3 border-top border-success border-opacity-10 text-end mt-3">
                                            <el-button
                                                type="success"
                                                :loading="assignmentSaving"
                                                :disabled="!bulkSubstitute.oldApproverKode || !bulkSubstitute.newApproverKode"
                                                @click="confirmAndApplyBulkSubstitute"
                                            >
                                                <i class="bi bi-arrow-left-right me-1"></i>Lakukan Substitusi
                                            </el-button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ============ TAB: RIWAYAT PERUBAHAN ============ -->
                <template #history>
                    <TransactionHistory
                        mode="approval"
                        :items="historyItems"
                        :loading="historyLoading"
                        :notice="historyNotice"
                        :featureName="selectedFeatureName"
                        @refresh="fetchStepHistory"
                    />
                </template>
            </approval-tabs>
        </div>

        <!-- Modal CRUD config (system admin) -->
        <ConfigCrudModal
            :show="showConfigModal"
            :config="editingConfig"
            :grupId="grupId"
            :activeGrup="activeGrup"
            @close="showConfigModal = false"
            @saved="onConfigSaved"
        />

        <!-- Modal jenis approval (system admin) -->
        <JenisApprovalModal :show="showJenisModal" @close="showJenisModal = false" />
    </div>
</template>

<script>
import axios from 'axios';
import { debounce } from 'lodash';
import { ElMessage, ElMessageBox } from 'element-plus';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import TableNoir from '@/components/legacy/table-noir.vue';
import ConfigStepMatrix from './ConfigStepMatrix.vue';
import ConfigCrudModal from './ConfigCrudModal.vue';
import JenisApprovalModal from './JenisApprovalModal.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import AssignmentContextCard from './AssignmentContextCard.vue';
import FeatureApprovalFlow from './FeatureApprovalFlow.vue';
import ApprovalDashboard from './ApprovalDashboard.vue';
import TransactionHistory from './TransactionHistory.vue';

export default {
    name: 'ApprovalMatrixTab',
    components: {
        ModernFilter,
        TableNoir,
        ConfigStepMatrix,
        ConfigCrudModal,
        JenisApprovalModal,
        ApprovalTabs,
        AssignmentContextCard,
        FeatureApprovalFlow,
        ApprovalDashboard,
        TransactionHistory,
    },
    props: {
        grupId: { type: Number, default: null },
        capabilities: { type: Object, required: true },
        activeGrup: { type: Object, required: true },
    },
    data() {
        return {
            activeSubTab: 'summary', // "summary", "configs", "single", "bulk", "history"
            loading: false,
            configs: [],
            unboundConfigs: [],
            notice: null,
            approvalSummary: null,
            summaryLoading: false,
            summaryError: null,
            summaryDirty: true,
            summaryRequestId: 0,
            search: '',
            selectedConfig: null,
            showConfigModal: false,
            editingConfig: null,
            showJenisModal: false,

            // Requester assignment sub-tabs states
            selectedConfigIdForAssignment: null,
            selectedStepIdForAssignment: null,
            stepsForAssignment: [],
            selectedStepDetail: null,
            assignmentLoading: false,
            assignmentSaving: false,
            assignmentItems: [],
            assignmentPagination: { page: 1, per_page: 15, total: 0, total_pages: 1 },
            assignmentSearch: '',
            karyawanOptions: [],
            karyawanLoading: false,
            requesterOptions: [],
            requesterLoading: false,
            newRequesterKode: null,
            editing: { kode: null, nama: null },
            draftApprovers: [],
            addApproverKode: null,
            catatan: '',
            bulk: { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: '' },
            bulkActionMode: 'sequence', // 'sequence' vs 'substitute'
            bulkDraftApprovers: [],
            bulkAddApproverKode: null,
            bulkSubstitute: { oldApproverKode: null, newApproverKode: null, catatan: '' },
            historyItems: [],
            historyLoading: false,
            historyNotice: null,
            singleEditMode: false,

            // Bulk Assignment Category Filters
            bulkSelectMode: 'manual', // 'manual' vs 'filter'
            bulkFilters: {
                cabang: '',
                divisi_id: '',
                sub_divisi_id: '',
                level_id: '',
                jabatan_id: '',
            },
            filterOptions: {
                cabang: [],
                divisi: [],
                subDivisi: [],
                level: [],
                jabatan: [],
            },
            filterOptionsLoaded: false,
            filterResults: [],
            filterLoading: false,
            filterResultsSearch: '',
            showBulkTargetsPreview: false,
        };
    },
    computed: {
        configColumns() {
            return [
                { key: 'Kode_Config', title: 'Kode Config' },
                { key: 'source_tables', title: 'Source Assignment', width: '320px' },
                { key: 'total_step', title: 'Step', width: '100px' },
                { key: 'Flag_Aktif', title: 'Status', width: '110px' },
                { key: 'aksi', title: 'Aksi', width: '140px' },
            ];
        },
        searchFilterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari kode config...',
                showActions: false,
                showActiveFilters: false,
                showResultsCount: false,
                showReset: false,
                showClearAll: false,
                mobileTrigger: false,
                filters: [],
            };
        },
        searchFilter() {
            return { search: this.search };
        },
        activeConfigsCount() {
            if (!this.configs) return 0;
            return this.configs.filter((c) => c.Flag_Aktif === 'Y').length;
        },
        totalStepsCount() {
            if (!this.configs) return 0;
            return this.configs.reduce((acc, c) => acc + (c.total_step || 0), 0);
        },
        selectedAssignmentConfig() {
            return this.configs.find((config) => config.Id === this.selectedConfigIdForAssignment) || null;
        },
        activeConfigs() {
            return this.configs.filter((config) => config.Flag_Aktif === 'Y');
        },
        selectedFeatureName() {
            return this.activeGrup?.Nama_Grup_Fitur || this.activeGrup?.Kode_Grup_Fitur || 'Fitur terpilih';
        },
        sidebarTabs() {
            const tabs = [
                {
                    id: 'summary',
                    label: 'Ringkasan',
                    icon: 'bi bi-grid-1x2-fill',
                    count: null,
                    section: 'Kelola Matrix',
                },
                {
                    id: 'configs',
                    label: this.capabilities.isSystemAdmin ? 'Daftar Config' : 'Alur Fitur',
                    icon: 'bi bi-diagram-3-fill',
                    count: null,
                    section: 'Kelola Matrix',
                },
                {
                    id: 'history',
                    label: 'Riwayat Matrix',
                    icon: 'bi bi-clock-history',
                    count: null,
                    section: 'Audit Trail',
                },
            ];
            if (!this.capabilities.isSystemAdmin) {
                return tabs.filter((tab) => {
                    if (tab.id === 'summary' || tab.id === 'configs') return true;
                    if (tab.id === 'history' && this.capabilities.approvalMatrix.canView) return true;
                    return false;
                });
            }
            // System Admin gets direct access to Single & Bulk assignment tabs
            tabs.push(
                {
                    id: 'single',
                    label: 'Single Assignment',
                    icon: 'bi bi-person-fill',
                    count: null,
                    section: 'Assignment Approver',
                },
                {
                    id: 'bulk',
                    label: 'Bulk Assignment',
                    icon: 'bi bi-lightning-charge-fill',
                    count: null,
                    section: 'Assignment Approver',
                },
            );
            return tabs;
        },
        filteredSubDivisiOptions() {
            if (!this.filterOptions.subDivisi) return [];
            if (!this.bulkFilters.divisi_id) return this.filterOptions.subDivisi;
            return this.filterOptions.subDivisi.filter((item) => item.divisi_id === this.bulkFilters.divisi_id);
        },
        filteredJabatanOptions() {
            if (!this.filterOptions.jabatan) return [];
            if (!this.bulkFilters.level_id) return this.filterOptions.jabatan;
            return this.filterOptions.jabatan.filter((item) => item.level_id === this.bulkFilters.level_id);
        },
        filterOptionsEmpty() {
            const f = this.filterOptions || {};
            return (
                this.filterOptionsLoaded &&
                !(f.cabang?.length || f.divisi?.length || f.subDivisi?.length || f.level?.length || f.jabatan?.length)
            );
        },
        isAllFilteredSelected() {
            if (!this.filterResults.length) return false;
            return this.filterResults.every((item) => this.bulk.requesterKodes.includes(item.Kode_Karyawan));
        },
        searchedFilterResults() {
            if (!this.filterResultsSearch) return this.filterResults;
            const s = this.filterResultsSearch.toLowerCase();
            return this.filterResults.filter(
                (emp) => emp.Nama?.toLowerCase().includes(s) || 
                         emp.Kode_Karyawan?.toLowerCase().includes(s) ||
                         emp.Jabatan?.toLowerCase().includes(s)
            );
        },
    },
    watch: {
        grupId: {
            immediate: true,
            handler() {
                this.selectedConfig = null;
                this.selectedConfigIdForAssignment = null;
                this.selectedStepIdForAssignment = null;
                this.stepsForAssignment = [];
                this.selectedStepDetail = null;
                this.approvalSummary = null;
                this.summaryError = null;
                this.summaryDirty = true;
                this.activeSubTab = 'summary';
                // Paksa muat ulang opsi filter grup ini (hindari opsi grup lama yang stale).
                this.filterOptionsLoaded = false;
                this.filterOptions = { cabang: [], divisi: [], subDivisi: [], level: [], jabatan: [] };
                this.fetchConfigs();
                this.fetchApprovalSummary();
            },
        },
        activeSubTab(tab) {
            if (tab === 'summary') {
                this.fetchApprovalSummary();
            } else if (tab === 'single') {
                if (this.selectedStepIdForAssignment) {
                    this.fetchAssignments();
                } else if (this.capabilities.isSystemAdmin && this.configs.length === 0) {
                    this.fetchConfigs();
                }
            } else if (tab === 'bulk') {
                this.bulk = { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: '' };
                this.bulkDraftApprovers = [];
                this.resetBulkFilters();
                this.fetchFilterOptions();
                if (this.capabilities.isSystemAdmin && this.configs.length === 0) {
                    this.fetchConfigs();
                }
                if (this.selectedStepIdForAssignment) {
                    this.searchKaryawanOptions('');
                    this.searchRequesterOptions('');
                }
            } else if (tab === 'history') {
                this.fetchStepHistory();
            }
        },
    },
    created() {
        this.debouncedFetch = debounce(() => {
            this.assignmentPagination.page = 1;
            this.fetchAssignments();
        }, 400);
    },
    methods: {
        async fetchApprovalSummary(force = false) {
            if (this.grupId === null || (!force && this.approvalSummary && !this.summaryDirty)) return;
            const requestId = ++this.summaryRequestId;
            const grupId = this.grupId;
            this.summaryLoading = true;
            this.summaryError = null;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/approval-summary', {
                    params: { grup_id: grupId },
                });
                if (requestId !== this.summaryRequestId || Number(grupId) !== Number(this.grupId)) return;
                this.approvalSummary = data;
                this.summaryDirty = false;
            } catch (e) {
                if (requestId !== this.summaryRequestId) return;
                this.summaryError = e.response?.data?.message || 'Terjadi kendala saat membaca kondisi approval.';
            } finally {
                if (requestId === this.summaryRequestId) this.summaryLoading = false;
            }
        },
        markSummaryDirty() {
            this.summaryDirty = true;
        },
        findDashboardTarget(item) {
            const config = this.configs.find((candidate) => Number(candidate.Id) === Number(item.config_id));
            const detail = config?.flow_details?.find((candidate) => Number(candidate.Id) === Number(item.config_detail_id));
            return { config, detail };
        },
        openDashboardFlow(item = null) {
            if (item?.config_id) {
                const { config, detail } = this.findDashboardTarget(item);
                if (config) {
                    this.selectedConfig = config;
                    this.selectedConfigIdForAssignment = config.Id;
                    this.stepsForAssignment = config.flow_details || [];
                }
                if (detail) {
                    this.selectedStepIdForAssignment = detail.Id;
                    this.selectedStepDetail = detail;
                }
            }
            this.activeSubTab = 'configs';
        },
        openDashboardStep(item) {
            const { config, detail } = this.findDashboardTarget(item);
            if (config && detail?.editable) {
                this.handleManageStep({ config, detail });
                return;
            }
            this.openDashboardFlow(item);
        },
        openDashboardConfig(item) {
            const { config } = this.findDashboardTarget(item);
            if (config) {
                this.selectedConfig = config;
                this.selectedConfigIdForAssignment = config.Id;
            }
            this.activeSubTab = 'configs';
        },
        configOptionLabel(config) {
            const name = config.Nama_Config ? ` - ${config.Nama_Config}` : '';
            const sources = config.source_tables?.length ? ` | Source: ${config.source_tables.join(', ')}` : '';
            return `${config.Kode_Config}${name}${sources}`;
        },
        stepOptionLabel(step) {
            const name = step.Nama_Approval || step.Custom_Approver_Kode || 'Approver';
            const source = this.capabilities.isSystemAdmin && step.Source_Reference ? ` | Source: ${step.Source_Reference}` : '';
            const readOnly = !step.editable ? ' | Read-only' : '';
            return `Step ${step.Approval_Order}: ${name}${source}${readOnly}`;
        },
        async fetchConfigs() {
            if (this.grupId === null) {
                this.configs = [];
                return;
            }
            this.loading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/configs', {
                    params: { grup_id: this.grupId, search: this.search || undefined },
                });
                this.configs = data.configs || [];
                this.unboundConfigs = data.unboundConfigs || [];
                this.notice = data.notice || null;

                if (this.selectedConfig) {
                    this.selectedConfig = this.configs.find((c) => c.Id === this.selectedConfig.Id) || null;
                }

                if (!this.capabilities.isSystemAdmin && !this.selectedAssignmentConfig && this.activeConfigs.length) {
                    this.selectedConfig = this.activeConfigs[0];
                    this.selectedConfigIdForAssignment = this.selectedConfig.Id;
                    this.stepsForAssignment = this.selectedConfig.flow_details || [];
                }
            } catch (e) {
                ElMessage.error(
                    e.response?.data?.message ||
                        (this.capabilities.isSystemAdmin ? 'Gagal memuat approval config.' : 'Gagal memuat alur persetujuan fitur.'),
                );
            } finally {
                this.loading = false;
            }
        },
        handleSearch(filters) {
            this.search = filters.search || '';
            this.fetchConfigs();
        },
        selectConfig(item) {
            this.selectedConfig = item;
            this.selectedConfigIdForAssignment = item.Id;
            this.loadStepsForAssignment(item.Id);
        },
        openConfigModal(config) {
            this.editingConfig = config;
            this.showConfigModal = true;
        },
        onConfigSaved() {
            this.showConfigModal = false;
            this.markSummaryDirty();
            this.fetchConfigs();
        },
        onConfigStructureChanged() {
            this.markSummaryDirty();
            this.fetchConfigs();
        },
        async deactivateConfig(item) {
            try {
                await ElMessageBox.confirm(
                    `Nonaktifkan approval config "${item.Kode_Config}"? Flow baru tidak bisa dibuat dari config nonaktif.`,
                    'Konfirmasi',
                    { confirmButtonText: 'Nonaktifkan', cancelButtonText: 'Batal', type: 'warning' },
                );
            } catch {
                return;
            }

            try {
                const { data } = await axios.delete(`/api/v1/menu-approval-matrix/configs/${item.Id}`, {
                    data: { grup_id: this.grupId },
                });
                ElMessage.success(data.message || 'Config dinonaktifkan.');
                this.markSummaryDirty();
                this.fetchConfigs();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menonaktifkan config.');
            }
        },
        async bindUnboundConfig(item) {
            try {
                await axios.put(`/api/v1/menu-approval-matrix/configs/${item.Id}/grup-fitur`, {
                    grup_id: this.grupId,
                });
                ElMessage.success(`${item.Kode_Config} sekarang digunakan pada ${this.selectedFeatureName}.`);
                this.markSummaryDirty();
                this.fetchConfigs();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengikat config ke grup aktif.');
            }
        },

        // Requester Assignment methods
        setActiveSubTab(tabId) {
            this.activeSubTab = tabId;
        },
        async handleManageStep({ config, detail }) {
            const targetConfig = config || this.selectedConfig;
            if (!targetConfig) return;
            this.selectedConfig = targetConfig;
            this.selectedConfigIdForAssignment = targetConfig.Id;
            this.stepsForAssignment = targetConfig.flow_details || [];
            this.selectedStepIdForAssignment = detail.Id;
            this.selectedStepDetail = detail;
            this.singleEditMode = false;
            this.activeSubTab = 'single';
        },
        async onConfigChangeForAssignment(configId) {
            this.selectedStepIdForAssignment = null;
            this.selectedStepDetail = null;
            this.singleEditMode = false;
            this.assignmentItems = [];
            if (configId) {
                await this.loadStepsForAssignment(configId);
            } else {
                this.stepsForAssignment = [];
            }
        },
        onStepChangeForAssignment(stepId) {
            this.singleEditMode = false;
            const picked = this.stepsForAssignment.find((s) => s.Id === stepId);
            this.selectedStepDetail = picked || null;

            if (picked) {
                if (this.activeSubTab === 'single') {
                    this.paginationPage = 1;
                    this.fetchAssignments();
                }
            }
        },
        clearAssignmentContext() {
            this.selectedConfigIdForAssignment = null;
            this.selectedStepIdForAssignment = null;
            this.selectedStepDetail = null;
            this.stepsForAssignment = [];
            this.assignmentItems = [];
            this.singleEditMode = false;
            this.selectedConfig = null;
        },
        async loadStepsForAssignment(configId) {
            const cachedConfig = this.configs.find((config) => Number(config.Id) === Number(configId));
            if (cachedConfig?.flow_details?.length) {
                this.stepsForAssignment = cachedConfig.flow_details;
                return;
            }
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/configs/${configId}/matrix`, {
                    params: { grup_id: this.grupId },
                });
                this.stepsForAssignment = data.details || [];
            } catch (e) {
                ElMessage.error(
                    this.capabilities.isSystemAdmin ? 'Gagal memuat tahapan approval config.' : 'Gagal memuat posisi approval.',
                );
                this.stepsForAssignment = [];
            }
        },
        async fetchAssignments() {
            if (!this.selectedStepIdForAssignment) return;
            this.assignmentLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/assignments', {
                    params: {
                        config_detail_id: this.selectedStepIdForAssignment,
                        grup_id: this.grupId,
                        search: this.assignmentSearch || undefined,
                        page: this.assignmentPagination.page,
                        per_page: this.assignmentPagination.per_page,
                    },
                });
                this.assignmentItems = data.items || [];
                this.assignmentPagination = { ...this.assignmentPagination, ...(data.pagination || {}) };
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat assignment.');
            } finally {
                this.assignmentLoading = false;
            }
        },
        goPage(page) {
            this.assignmentPagination.page = page;
            this.fetchAssignments();
        },
        async searchKaryawanOptions(query) {
            this.karyawanLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'approver', search: query || undefined },
                });
                this.karyawanOptions = data.items || [];
            } catch {
                this.karyawanOptions = [];
            } finally {
                this.karyawanLoading = false;
            }
        },
        async searchRequesterOptions(query) {
            this.requesterLoading = true;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/search', {
                    params: { grup_id: this.grupId, purpose: 'target', search: query || undefined },
                });
                this.requesterOptions = data.items || [];
            } catch {
                this.requesterOptions = [];
            } finally {
                this.requesterLoading = false;
            }
        },
        startEdit(item) {
            this.editing = { kode: item.requester.kode, nama: item.requester.nama };
            this.draftApprovers = item.approvers.map((a) => ({
                kode: a.approver_kode,
                nama: a.approver_nama,
            }));
            this.catatan = '';
            this.addApproverKode = null;
            this.singleEditMode = true;
        },
        startEditNewRequester(kode) {
            if (!kode) return;
            const picked = this.karyawanOptions.find((k) => k.Kode_Karyawan === kode);
            const existing = this.assignmentItems.find((i) => i.requester.kode === kode);
            if (existing) {
                this.startEdit(existing);
            } else {
                this.editing = { kode, nama: picked?.Nama || kode };
                this.draftApprovers = [];
                this.catatan = '';
                this.addApproverKode = null;
                this.singleEditMode = true;
            }
            this.newRequesterKode = null;
        },
        addApprover(kode) {
            if (!kode) return;
            const picked = this.karyawanOptions.find((k) => k.Kode_Karyawan === kode);
            this.draftApprovers.push({ kode, nama: picked?.Nama || kode });
            this.addApproverKode = null;
        },
        moveDraft(idx, delta) {
            const target = idx + delta;
            if (target < 0 || target >= this.draftApprovers.length) return;
            const copy = [...this.draftApprovers];
            [copy[idx], copy[target]] = [copy[target], copy[idx]];
            this.draftApprovers = copy;
        },
        async saveFlow() {
            this.assignmentSaving = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/assignments/replace', {
                    grup_id: this.grupId,
                    config_detail_id: this.selectedStepIdForAssignment,
                    kode_requester: this.editing.kode,
                    approvers: this.draftApprovers.map((a, idx) => ({ kode: a.kode, order_flow: idx + 1 })),
                    catatan: this.catatan || null,
                });
                ElMessage.success(data.message || 'Assignment disimpan.');
                this.markSummaryDirty();
                this.singleEditMode = false;
                this.fetchAssignments();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menyimpan assignment.');
            } finally {
                this.assignmentSaving = false;
            }
        },
        async applyBulk() {
            this.assignmentSaving = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/assignments/bulk', {
                    grup_id: this.grupId,
                    config_detail_id: this.selectedStepIdForAssignment,
                    kode_approver: this.bulk.approverKode,
                    requester_kodes: this.bulk.requesterKodes,
                    order_flow: this.bulk.orderFlow,
                    catatan: this.bulk.catatan || null,
                });
                ElMessage.success(data.message || 'Bulk assign selesai.');
                this.markSummaryDirty();
                if (data.detail?.skipped?.length) {
                    const reasons = data.detail.skipped.map((s) => `${s.requester}: ${s.alasan}`).join('; ');
                    ElMessage.warning(`Dilewati — ${reasons}`);
                }
                this.bulk = { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: '' };
                this.activeSubTab = 'single';
                this.fetchAssignments();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal bulk assign.');
            } finally {
                this.assignmentSaving = false;
            }
        },
        addBulkDraftApprover(kode) {
            if (!kode) return;
            const picked = this.karyawanOptions.find((k) => k.Kode_Karyawan === kode);
            this.bulkDraftApprovers.push({ kode, nama: picked?.Nama || kode });
            this.bulkAddApproverKode = null;
        },
        moveBulkDraft(idx, dir) {
            const target = idx + dir;
            if (target < 0 || target >= this.bulkDraftApprovers.length) return;
            const copy = [...this.bulkDraftApprovers];
            const temp = copy[idx];
            copy[idx] = copy[target];
            copy[target] = temp;
            this.bulkDraftApprovers = copy;
        },
        async confirmAndApplyBulkSequence() {
            if (!this.bulkDraftApprovers.length || !this.bulk.requesterKodes.length) return;
            try {
                await ElMessageBox.confirm(
                    `Anda akan menetapkan paket alur persetujuan (${this.bulkDraftApprovers.length} level) untuk ${this.bulk.requesterKodes.length} karyawan. Alur lama mereka pada step ini akan digantikan seluruhnya. Lanjutkan?`,
                    'Konfirmasi Paket Alur Massal',
                    {
                        confirmButtonText: 'Terapkan',
                        cancelButtonText: 'Batal',
                        type: 'warning'
                    }
                );
            } catch {
                return;
            }
            this.applyBulkSequence();
        },
        async applyBulkSequence() {
            this.assignmentSaving = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/assignments/bulk-replace', {
                    grup_id: this.grupId,
                    config_detail_id: this.selectedStepIdForAssignment,
                    requester_kodes: this.bulk.requesterKodes,
                    approvers: this.bulkDraftApprovers.map((a, idx) => ({ kode: a.kode, order_flow: idx + 1 })),
                    catatan: this.bulk.catatan || null,
                });
                ElMessage.success(data.message || 'Paket alur massal berhasil diterapkan.');
                this.markSummaryDirty();
                
                // Clear state
                this.bulk = { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: '' };
                this.bulkDraftApprovers = [];
                this.activeSubTab = 'single';
                this.fetchAssignments();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menerapkan paket alur massal.');
            } finally {
                this.assignmentSaving = false;
            }
        },
        async confirmAndApplyBulkSubstitute() {
            if (!this.bulkSubstitute.oldApproverKode || !this.bulkSubstitute.newApproverKode) return;
            const oldName = this.getApproverNameByKode(this.bulkSubstitute.oldApproverKode);
            const newName = this.getApproverNameByKode(this.bulkSubstitute.newApproverKode);
            try {
                await ElMessageBox.confirm(
                    `Anda akan mengganti semua penugasan ${oldName} dengan ${newName} pada step "${this.selectedStepDetail.Nama_Approval}". Proses ini tidak dapat dibatalkan secara otomatis. Lanjutkan?`,
                    'Konfirmasi Substitusi Approver Massal',
                    {
                        confirmButtonText: 'Ya, Substitusi',
                        cancelButtonText: 'Batal',
                        type: 'warning'
                    }
                );
            } catch {
                return;
            }
            this.applyBulkSubstitute();
        },
        async applyBulkSubstitute() {
            this.assignmentSaving = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/assignments/bulk-substitute', {
                    grup_id: this.grupId,
                    config_detail_id: this.selectedStepIdForAssignment,
                    old_approver_kode: this.bulkSubstitute.oldApproverKode,
                    new_approver_kode: this.bulkSubstitute.newApproverKode,
                    catatan: this.bulkSubstitute.catatan || null,
                });
                ElMessage.success(data.message || 'Substitusi approver massal selesai.');
                this.markSummaryDirty();
                if (data.detail?.skipped?.length) {
                    const reasons = data.detail.skipped.map((s) => `${s.requester}: ${s.alasan}`).join('; ');
                    ElMessageBox.alert(
                        `Beberapa data dilewati karena kendala validasi: ${reasons}`,
                        'Info Hasil Substitusi',
                        { confirmButtonText: 'OK' }
                    );
                }
                
                // Clear state
                this.bulkSubstitute = { oldApproverKode: null, newApproverKode: null, catatan: '' };
                this.activeSubTab = 'single';
                this.fetchAssignments();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal melakukan substitusi approver massal.');
            } finally {
                this.assignmentSaving = false;
            }
        },
        getUserInitials(name) {
            if (!name) return '?';
            const parts = name.split(' ').filter((p) => p.length > 0);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return parts[0][0].toUpperCase();
        },
        resetBulkFilters() {
            this.bulkFilters = {
                cabang: '',
                divisi_id: '',
                sub_divisi_id: '',
                level_id: '',
                jabatan_id: '',
            };
            this.filterResults = [];
            this.filterResultsSearch = '';
        },
        async fetchFilterOptions() {
            if (this.filterOptionsLoaded) return;
            try {
                const { data } = await axios.get('/api/v1/menu-approval-matrix/karyawan/filter-options', {
                    params: { grup_id: this.grupId },
                });
                this.filterOptions = {
                    cabang: data.cabang || [],
                    divisi: data.divisi || [],
                    subDivisi: data.subDivisi || [],
                    level: data.level || [],
                    jabatan: data.jabatan || [],
                };
                this.filterOptionsLoaded = true;
            } catch (e) {
                ElMessage.error('Gagal memuat opsi filter karyawan.');
            }
        },
        async applyKaryawanFilters() {
            this.filterLoading = true;
            try {
                const { data } = await axios.post('/api/v1/menu-approval-matrix/karyawan/filter-search', {
                    grup_id: this.grupId,
                    cabang: this.bulkFilters.cabang || null,
                    divisi_id: this.bulkFilters.divisi_id || null,
                    sub_divisi_id: this.bulkFilters.sub_divisi_id || null,
                    level_id: this.bulkFilters.level_id || null,
                    jabatan_id: this.bulkFilters.jabatan_id || null,
                });
                this.filterResults = data.items || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memfilter karyawan.');
                this.filterResults = [];
            } finally {
                this.filterLoading = false;
            }
        },
        toggleFilteredSelection(employee) {
            const idx = this.bulk.requesterKodes.indexOf(employee.Kode_Karyawan);
            if (idx > -1) {
                this.bulk.requesterKodes.splice(idx, 1);
            } else {
                this.bulk.requesterKodes.push(employee.Kode_Karyawan);
            }
        },
        selectAllFiltered(val) {
            if (val) {
                this.filterResults.forEach((emp) => {
                    if (!this.bulk.requesterKodes.includes(emp.Kode_Karyawan)) {
                        this.bulk.requesterKodes.push(emp.Kode_Karyawan);
                    }
                });
            } else {
                const resultKodes = this.filterResults.map((emp) => emp.Kode_Karyawan);
                this.bulk.requesterKodes = this.bulk.requesterKodes.filter((kode) => !resultKodes.includes(kode));
            }
        },
        getApproverNameByKode(kode) {
            const found = this.karyawanOptions.find(k => k.Kode_Karyawan === kode);
            return found ? found.Nama : kode;
        },
        getRequesterNameByKode(kode) {
            const found = this.karyawanOptions.find(k => k.Kode_Karyawan === kode) 
                       || this.filterResults.find(k => k.Kode_Karyawan === kode);
            return found ? found.Nama : kode;
        },
        async confirmAndApplyBulk() {
            if (!this.bulk.approverKode || !this.bulk.requesterKodes.length) return;
            const approverName = this.getApproverNameByKode(this.bulk.approverKode);
            try {
                await ElMessageBox.confirm(
                    `Anda akan menetapkan ${approverName} sebagai Approver Level ${this.bulk.orderFlow} untuk ${this.bulk.requesterKodes.length} karyawan. Approver lama di alur level ini akan digantikan. Lanjutkan?`,
                    'Konfirmasi Bulk Assignment',
                    {
                        confirmButtonText: 'Terapkan',
                        cancelButtonText: 'Batal',
                        type: 'warning'
                    }
                );
            } catch {
                return;
            }
            this.applyBulk();
        },
        async fetchStepHistory() {
            this.historyLoading = true;
            try {
                const params = { limit: 50 };
                if (this.grupId) {
                    params.grup_id = this.grupId;
                } else {
                    this.historyItems = [];
                    return;
                }
                const { data } = await axios.get('/api/v1/menu-approval-matrix/assignments/history', { params });
                this.historyItems = data.items || [];
                this.historyNotice = data.notice || null;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat riwayat.');
            } finally {
                this.historyLoading = false;
            }
        },
        summarizeSnapshot(json) {
            try {
                const rows = JSON.parse(json || '[]');
                if (!rows.length) return '(kosong)';
                return rows.map((r) => `${r.order_flow}.${r.Kode_Karyawan_Approver || r.Kode_Karyawan}`).join(' → ');
            } catch {
                return '(tidak terbaca)';
            }
        },
        formatDateTime(value) {
            if (!value) return '-';
            return new Date(value).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.stitch-card {
    margin: 0.5rem;

    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
}
.alert-notice {
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
}
.unbound-panel {
    display: grid;
    gap: 0.75rem;
    padding: 0.9rem;
    border: 1px solid #facc15;
    border-radius: 12px;
    background: var(--hcis-amber-50);
}
.unbound-list {
    display: grid;
    gap: 0.5rem;
}
.unbound-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    background: #fff;
}
@media (max-width: 576px) {
    .unbound-item {
        align-items: stretch;
        flex-direction: column;
    }
}
.badge-view-only {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 20px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    text-transform: uppercase;
}
.pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    white-space: nowrap;
}
.pill-info {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.pill-warning {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
}
.pill-success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}
.pill-muted {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
}
.pill-neutral {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
}
.btn-action {
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 8px;
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.15s;
}
.btn-action:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
}
.btn-danger-soft:hover {
    border-color: var(--hcis-red-500);
    color: var(--hcis-red-500);
}
.card-metric {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}
.card-metric:hover {
    transform: translateY(-2px);
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.05),
        0 2px 4px -1px rgba(0, 0, 0, 0.025);
}
.bg-primary-soft {
    background-color: #eff6ff !important;
    color: var(--hcis-blue-600) !important;
    border: 1px solid rgba(37, 99, 235, 0.1) !important;
}
.bg-success-soft {
    background-color: var(--hcis-emerald-50) !important;
    color: var(--hcis-emerald-600) !important;
    border: 1px solid rgba(5, 150, 105, 0.1) !important;
}
.bg-warning-soft {
    background-color: var(--hcis-amber-50) !important;
    color: var(--hcis-amber-600) !important;
    border: 1px solid rgba(217, 119, 6, 0.1) !important;
}
.bg-info-soft {
    background-color: var(--hcis-sky-50) !important;
    color: var(--hcis-sky-600) !important;
    border: 1px solid rgba(2, 132, 199, 0.1) !important;
}
.text-xs {
    font-size: 0.72rem !important;
}
.uppercase {
    text-transform: uppercase;
}
.tracking-wider {
    letter-spacing: 0.05em;
}

/* Requester Flow / List Custom Styles */
.requester-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.requester-row {
    border: 1px solid var(--hcis-slate-200);
    transition: border-color 0.2s;
}
.requester-row:hover {
    border-color: var(--hcis-slate-300);
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem 0.2rem 0.25rem;
    border-radius: 20px;
}
.chip-inactive {
    background: var(--hcis-red-50) !important;
    color: var(--hcis-red-700) !important;
    border: 1px solid rgba(185, 28, 28, 0.15) !important;
}
.chip-order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    color: var(--hcis-indigo-600);
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid var(--hcis-indigo-200);
}
.btn-edit-flow {
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.2s;
}
.btn-edit-flow:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.1);
}
.btn-back {
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 8px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-back:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
}
.btn-mini {
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    border-radius: 6px;
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    cursor: pointer;
    font-size: 0.7rem;
}
.btn-mini:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.btn-mini:hover:not(:disabled) {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
}
.btn-mini-danger:hover {
    border-color: var(--hcis-red-500) !important;
}
.form-label-sm {
    font-size: 0.78rem;
    color: var(--hcis-slate-700);
    margin-bottom: 0.25rem;
    display: block;
}
.empty-state {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
    padding: 2rem !important;
    color: var(--hcis-slate-400) !important;
}
.empty-state i {
    font-size: 2rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-bottom: 0.5rem !important;
}
.history-row {
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.2s;
}
.history-row:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.history-diff {
    font-family: monospace;
    font-size: 0.72rem;
}
.italic {
    font-style: italic;
}
.last-border-none:last-child {
    border-bottom: none !important;
}
.hover-bg-indigo:hover {
    background-color: var(--hcis-slate-50) !important;
    border-color: var(--hcis-indigo-500) !important;
}
.bg-danger-soft {
    background-color: var(--hcis-red-50) !important;
    color: var(--hcis-red-700) !important;
}
.bg-indigo-soft {
    background-color: var(--hcis-indigo-50) !important;
    color: var(--hcis-indigo-600) !important;
    border: 1px solid rgba(79, 70, 229, 0.1) !important;
}

/* Vertical timeline styles */
.timeline-workflow {
    position: relative;
    padding-left: 2.5rem;
    margin-top: 1rem;
}
.timeline-workflow::before {
    content: '';
    position: absolute;
    top: 20px;
    bottom: 40px;
    left: 17px;
    width: 3px;
    background: linear-gradient(180deg, var(--hcis-indigo-500) 0%, var(--hcis-purple-500) 100%);
    border-radius: 4px;
    opacity: 0.8;
}
.timeline-node {
    position: relative;
}
.timeline-node-dot {
    position: absolute;
    left: -2.5rem;
    top: 14px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid var(--hcis-indigo-500);
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
}
.dot-inner {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
}
.timeline-node-card {
    margin-left: 1rem;
    transition: all 0.2s;
    background: var(--hcis-white) !important;
    border-color: var(--hcis-slate-200) !important;
}
.timeline-node-card:hover {
    border-color: var(--hcis-indigo-200) !important;
    background: var(--hcis-slate-50) !important;
}
.empty-timeline-state {
    border-style: dashed !important;
    border-width: 2px !important;
    border-color: var(--hcis-slate-300) !important;
}
.hover-bg-danger:hover {
    background-color: var(--hcis-red-50) !important;
    border-color: var(--hcis-red-500) !important;
}
.form-control-xs {
    padding: 0.2rem 0.4rem;
    font-size: 0.72rem;
    border-radius: 4px;
}

/* Custom Level Badge */
.badge-level-approver {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 6px;
    background-color: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(79, 70, 229, 0.15);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Custom Initials Avatar */
.user-avatar-initials-sm {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    border-radius: 50%;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
    flex-shrink: 0;
}

/* Connectors and timeline line color gradient */
.timeline-workflow::before {
    background: linear-gradient(180deg, var(--hcis-indigo-500) 0%, var(--hcis-purple-500) 100%) !important;
}

/* Premium Card hover and shadow transitions */
.timeline-node-card {
    margin-left: 1rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    background: var(--hcis-white) !important;
    border: 1px solid var(--hcis-slate-200) !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02) !important;
}
.timeline-node-card:hover {
    border-color: var(--hcis-indigo-400) !important;
    transform: translateY(-2px) translateX(2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08) !important;
    background: var(--hcis-white) !important;
}

/* Alert styling */
.alert-custom-info {
    background-color: #f0f5ff !important;
    border-left: 4px solid var(--hcis-indigo-600) !important;
    color: var(--hcis-indigo-950) !important;
    border-radius: 8px;
    font-size: 0.8rem;
    display: flex;
    align-items: start;
    gap: 0.5rem;
}

/* Add Approver Panel style */
.add-approver-panel {
    background: #fdfdfd;
    border: 1px dashed var(--hcis-slate-300) !important;
    border-radius: 12px;
    padding: 1.25rem;
}

/* Custom Initials Avatar XS (for list rows) */
.user-avatar-initials-xs {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem;
    border-radius: 50%;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
    flex-shrink: 0;
}

/* Custom Chip Name Truncation */
.chip {
    max-width: 240px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}
.chip-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 150px;
}

/* Hover effects for requester row */
.requester-row {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.requester-row:hover {
    border-color: var(--hcis-indigo-400) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05) !important;
}
</style>
