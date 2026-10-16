<template>
    <div class="container-fluid px-0">
        <!-- Header dengan Breadcrumb -->
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <div class="mb-1 w-100">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div
                                        class="icon-container d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-pencil-square d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Multi KPI Form
                                        </h2>
                                        <p class="subtitle">
                                            Input KPI untuk multiple anggota tim
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div
                                        class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3"
                                    >
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i
                                                v-else
                                                class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <span class="fs-md-6 text-header">{{
                                                formatDateString(currentDate)
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <nav class="page-navigation">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a
                                href="#"
                                @click.prevent="
                                    $router.push('/kpi-team/dashboard')
                                "
                            >
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a
                                href="#"
                                @click.prevent="
                                    $router.push('/kpi-team/management')
                                "
                                >Team Management</a
                            >
                        </li>
                        <li class="breadcrumb-item active">Multi KPI Form</li>
                    </ol>

                    <div class="navigation-actions">
                        <button
                            class="btn-modern btn-secondary"
                            @click="$router.back()"
                        >
                            <i class="bi bi-arrow-left"></i> Kembali
                        </button>
                        <button
                            class="btn-modern btn-primary"
                            @click="saveAllProgress"
                        >
                            <i class="bi bi-cloud-arrow-up"></i> Simpan Semua
                        </button>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Multi Form Container -->
        <div class="multi-kpi-form-container">
            <!-- Team Selection Section -->
            <div class="form-control-section" v-if="!isSingleMemberMode">
                <div class="section-card">
                    <div class="section-header">
                        <h5><i class="bi bi-people"></i> Pilih Anggota Tim</h5>
                        <div class="selection-actions">
                            <button
                                class="btn-selection"
                                @click="selectAllMembers"
                            >
                                <i class="bi bi-check-all"></i> Pilih Semua
                            </button>
                            <button
                                class="btn-selection"
                                @click="clearSelection"
                            >
                                <i class="bi bi-x-circle"></i> Hapus Pilihan
                            </button>
                        </div>
                    </div>

                    <div class="member-selection-grid">
                        <div
                            v-for="member in availableMembers"
                            :key="member.id"
                            class="member-selection-card"
                            :class="{
                                selected: isMemberSelected(member.id),
                                'has-data': hasKPIData(member.id),
                            }"
                            @click="toggleMemberSelection(member.id)"
                        >
                            <div class="selection-checkbox">
                                <input
                                    type="checkbox"
                                    :checked="isMemberSelected(member.id)"
                                    @change="toggleMemberSelection(member.id)"
                                />
                                <span class="checkmark"></span>
                            </div>
                            <div class="member-avatar-sm">
                                {{ getInitials(member.name) }}
                            </div>
                            <div class="member-details">
                                <strong>{{ member.name }}</strong>
                                <span>{{ member.position }}</span>
                                <small>{{ member.department }}</small>
                            </div>
                            <div class="member-status-indicator">
                                <span
                                    :class="`status-dot status-${member.kpiStatus}`"
                                    :title="getStatusText(member.kpiStatus)"
                                ></span>
                                <div
                                    class="progress-indicator"
                                    v-if="member.kpiProgress > 0"
                                >
                                    <div class="mini-progress-bar">
                                        <div
                                            class="mini-progress-fill"
                                            :style="{
                                                width: member.kpiProgress + '%',
                                            }"
                                        ></div>
                                    </div>
                                    <small>{{ member.kpiProgress }}%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi Form Interface -->
            <div class="multi-form-interface" v-if="selectedMembers.length > 0">
                <!-- Member Navigation Tabs -->
                <div class="member-tabs-container">
                    <div class="member-tabs">
                        <div
                            v-for="member in selectedMembers"
                            :key="member.id"
                            class="member-tab"
                            :class="{
                                active: currentMemberId === member.id,
                                'has-changes': hasUnsavedChanges(member.id),
                                completed: member.kpiProgress === 100,
                            }"
                            @click="switchToMember(member.id)"
                        >
                            <div class="tab-avatar">
                                {{ getInitials(member.name) }}
                                <span
                                    class="change-indicator"
                                    v-if="hasUnsavedChanges(member.id)"
                                    >•</span
                                >
                            </div>
                            <div class="tab-content">
                                <span class="tab-name">{{ member.name }}</span>
                                <span
                                    class="tab-status"
                                    :class="`status-${member.kpiStatus}`"
                                >
                                    {{ getStatusText(member.kpiStatus) }}
                                </span>
                            </div>
                            <button
                                class="tab-close"
                                @click.stop="
                                    removeMemberFromSelection(member.id)
                                "
                            >
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <div class="tabs-actions">
                        <button
                            class="btn-tab-action"
                            @click="prevMember"
                            :disabled="!hasPrevMember"
                        >
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="tabs-counter">
                            {{ currentMemberIndex + 1 }} /
                            {{ selectedMembers.length }}
                        </span>
                        <button
                            class="btn-tab-action"
                            @click="nextMember"
                            :disabled="!hasNextMember"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Form Content Area -->
                <div class="form-content-area">
                    <!-- Current Member Header -->
                    <div class="current-member-header">
                        <div class="member-info-large">
                            <div class="avatar-lg">
                                {{ getInitials(currentMember.name) }}
                            </div>
                            <div class="member-details-large">
                                <h4>{{ currentMember.name }}</h4>
                                <p>
                                    {{ currentMember.position }} •
                                    {{ currentMember.department }}
                                </p>
                                <div class="member-meta">
                                    <span class="meta-item">
                                        <i class="bi bi-envelope"></i>
                                        {{ currentMember.email }}
                                    </span>
                                    <span class="meta-item">
                                        <i class="bi bi-telephone"></i>
                                        {{ currentMember.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="member-progress-indicator">
                            <div class="progress-stats">
                                <span class="progress-label">Progress KPI</span>
                                <span class="progress-value"
                                    >{{ currentMemberProgress }}%</span
                                >
                            </div>
                            <div class="progress-bar-lg">
                                <div
                                    class="progress-fill-lg"
                                    :style="{
                                        width: currentMemberProgress + '%',
                                    }"
                                    :class="
                                        getProgressBarClass(
                                            currentMemberProgress
                                        )
                                    "
                                ></div>
                            </div>
                            <div class="progress-details">
                                <span
                                    >{{ getCurrentMemberKPIs().length }} KPI •
                                    {{ calculateCurrentMemberWeight() }}%
                                    Bobot</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- KPI Form Component -->
                    <div class="kpi-form-wrapper">
                        <KpiForm
                            :modelValue="getCurrentMemberKPI()"
                            @update:modelValue="updateCurrentMemberKPI"
                            :fields="kpiFields"
                            :kpis="getCurrentMemberKPIs()"
                            :activeFormIndex="activeFormIndex"
                            :totalWeight="calculateCurrentMemberWeight()"
                            :remainingWeight="
                                calculateCurrentMemberRemainingWeight()
                            "
                            :statusTransaksi="currentMember.kpiStatus"
                            @saveAndClose="saveCurrentMember"
                            @switchToForm="switchKPIForm"
                            @saveAndNew="addNewKPIToCurrentMember"
                            @removeKpi="removeKPIFromCurrentMember"
                            @triggerTarget="triggerTargetForCurrentMember"
                        />
                    </div>
                </div>
            </div>

            <!-- Quick Actions Panel -->
            <div class="quick-actions-panel" v-if="selectedMembers.length > 0">
                <div class="actions-header">
                    <h6><i class="bi bi-lightning"></i> Quick Actions</h6>
                </div>

                <div class="actions-content">
                    <!-- Member Quick Stats -->
                    <div class="quick-stats">
                        <div class="stat-item">
                            <div class="stat-value">{{ completedMembers }}</div>
                            <div class="stat-label">Selesai</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                {{ inProgressMembers }}
                            </div>
                            <div class="stat-label">Proses</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">{{ averageProgress }}%</div>
                            <div class="stat-label">Rata-rata</div>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bulk-actions">
                        <button
                            class="btn-bulk-action"
                            @click="applyTemplateToAll"
                        >
                            <i class="bi bi-file-earmark-text"></i>
                            Terapkan Template
                        </button>
                        <button class="btn-bulk-action" @click="copyKPIToAll">
                            <i class="bi bi-files"></i>
                            Salin KPI
                        </button>
                        <button
                            class="btn-bulk-action"
                            @click="validateAllMembers"
                        >
                            <i class="bi bi-check-circle"></i>
                            Validasi Semua
                        </button>
                    </div>

                    <!-- Save Actions -->
                    <div class="save-actions">
                        <button
                            class="btn-save-all"
                            @click="saveAllMembers"
                            :disabled="!hasUnsavedChangesAny"
                        >
                            <i class="bi bi-cloud-check"></i>
                            Simpan Semua Perubahan
                        </button>
                        <button
                            class="btn-submit-all"
                            @click="submitAllToApproval"
                            :disabled="!canSubmitAll"
                            :class="{ ready: canSubmitAll }"
                        >
                            <i class="bi bi-send-check"></i>
                            Submit Semua ke Approval
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state-form">
                <div class="empty-content">
                    <i class="bi bi-people empty-icon"></i>
                    <h4>Belum Ada Anggota Terpilih</h4>
                    <p>
                        Pilih satu atau lebih anggota tim dari daftar di atas
                        untuk mulai mengisi KPI
                    </p>
                    <div class="empty-actions">
                        <button
                            class="btn-modern btn-primary"
                            @click="$router.push('/kpi-team/management')"
                        >
                            <i class="bi bi-arrow-left"></i> Kembali ke Team
                            Management
                        </button>
                        <button
                            class="btn-modern btn-secondary"
                            @click="loadSampleMembers"
                        >
                            <i class="bi bi-magic"></i> Muat Contoh Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unsaved Changes Modal -->
        <div v-if="showUnsavedModal" class="modal-overlay">
            <div class="modal-content unsaved-modal">
                <div class="modal-header">
                    <h3 class="modal-title">Perubahan Belum Disimpan</h3>
                </div>
                <div class="modal-body">
                    <p>
                        Anda memiliki perubahan yang belum disimpan. Apa yang
                        ingin Anda lakukan?
                    </p>
                    <div class="unsaved-members-list">
                        <div
                            v-for="member in unsavedMembers"
                            :key="member.id"
                            class="unsaved-member-item"
                        >
                            <span class="member-name">{{ member.name }}</span>
                            <span class="changes-count"
                                >{{ member.changes }} perubahan</span
                            >
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        class="btn-modern btn-secondary"
                        @click="discardChanges"
                    >
                        Abaikan Perubahan
                    </button>
                    <button
                        class="btn-modern btn-primary"
                        @click="saveBeforeLeave"
                    >
                        Simpan dan Lanjutkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox } from "element-plus";
import KpiForm from "../../../../components/form/form-dynamic.vue";

export default {
    name: "MultiKpiForm",
    components: {
        KpiForm,
    },
    data() {
        return {
            currentDate: new Date(),
            availableMembers: [
                {
                    id: 1,
                    name: "Ahmad Wijaya",
                    position: "Sales Executive",
                    department: "Sales",
                    email: "ahmad.wijaya@company.com",
                    phone: "+62 812-3456-7890",
                    kpiStatus: "completed",
                    kpiProgress: 100,
                },
                {
                    id: 2,
                    name: "Siti Rahma",
                    position: "Marketing Specialist",
                    department: "Marketing",
                    email: "siti.rahma@company.com",
                    phone: "+62 813-4567-8901",
                    kpiStatus: "in-progress",
                    kpiProgress: 75,
                },
                {
                    id: 3,
                    name: "Budi Santoso",
                    position: "Sales Manager",
                    department: "Sales",
                    email: "budi.santoso@company.com",
                    phone: "+62 814-5678-9012",
                    kpiStatus: "not-started",
                    kpiProgress: 0,
                },
                {
                    id: 4,
                    name: "Dewi Lestari",
                    position: "Account Executive",
                    department: "Sales",
                    email: "dewi.lestari@company.com",
                    phone: "+62 815-6789-0123",
                    kpiStatus: "in-progress",
                    kpiProgress: 50,
                },
            ],
            selectedMembers: [],
            currentMemberId: null,
            memberKPIs: {},
            activeFormIndex: 0,
            kpiFields: [
                // Your existing KPI fields configuration
                {
                    name: "parameter",
                    label: "Parameter KPI",
                    type: "text",
                    required: true,
                    icon: "bi bi-bullseye",
                },
                {
                    name: "weight",
                    label: "Bobot",
                    type: "persen",
                    required: true,
                    icon: "bi bi-speedometer2",
                },
                {
                    name: "bscCategory",
                    label: "Kategori BSC",
                    type: "select",
                    required: true,
                    icon: "bi bi-diagram-3",
                },
                {
                    name: "formula",
                    label: "Rumus Perhitungan",
                    type: "select",
                    required: true,
                    icon: "bi bi-calculator",
                },
                {
                    name: "unit",
                    label: "Satuan",
                    type: "select",
                    required: true,
                    icon: "bi bi-rulers",
                },
                {
                    name: "target",
                    label: "Target",
                    type: "popup",
                    required: true,
                    icon: "bi bi-bullseye",
                },
                {
                    name: "description",
                    label: "Deskripsi",
                    type: "textarea",
                    required: false,
                    icon: "bi bi-card-text",
                },
            ],
            unsavedChanges: {},
            showUnsavedModal: false,
            pendingNavigation: null,
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        currentMember() {
            return (
                this.availableMembers.find(
                    (m) => m.id === this.currentMemberId
                ) || this.selectedMembers[0]
            );
        },
        currentMemberIndex() {
            return this.selectedMembers.findIndex(
                (m) => m.id === this.currentMemberId
            );
        },
        hasPrevMember() {
            return this.currentMemberIndex > 0;
        },
        hasNextMember() {
            return this.currentMemberIndex < this.selectedMembers.length - 1;
        },
        isSingleMemberMode() {
            return;
        },
        currentMemberProgress() {
            const memberKPI = this.getCurrentMemberKPI();
            return memberKPI.kpis
                ? Math.min(100, (memberKPI.kpis.length / 5) * 100)
                : 0;
        },
        completedMembers() {
            return this.selectedMembers.filter(
                (member) => this.getMemberKPIProgress(member.id) === 100
            ).length;
        },
        inProgressMembers() {
            return this.selectedMembers.filter((member) => {
                const progress = this.getMemberKPIProgress(member.id);
                return progress > 0 && progress < 100;
            }).length;
        },
        averageProgress() {
            if (this.selectedMembers.length === 0) return 0;
            const total = this.selectedMembers.reduce(
                (sum, member) => sum + this.getMemberKPIProgress(member.id),
                0
            );
            return Math.round(total / this.selectedMembers.length);
        },
        hasUnsavedChangesAny() {
            return Object.keys(this.unsavedChanges).length > 0;
        },
        unsavedMembers() {
            return this.selectedMembers
                .filter((member) => this.unsavedChanges[member.id])
                .map((member) => ({
                    id: member.id,
                    name: member.name,
                    changes: this.unsavedChanges[member.id],
                }));
        },
        canSubmitAll() {
            return this.selectedMembers.every(
                (member) => this.getMemberKPIProgress(member.id) === 100
            );
        },
    },
    methods: {
        formatDateString(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },
        getStatusText(status) {
            const statusMap = {
                "not-started": "Belum Mulai",
                "in-progress": "Dalam Proses",
                completed: "Selesai",
                approved: "Disetujui",
            };
            return statusMap[status] || status;
        },
        getProgressBarClass(progress) {
            if (progress < 30) return "low";
            if (progress < 70) return "medium";
            return "high";
        },

        // Member Selection Methods
        isMemberSelected(memberId) {
            return this.selectedMembers.some((m) => m.id === memberId);
        },
        toggleMemberSelection(memberId) {
            const index = this.selectedMembers.findIndex(
                (m) => m.id === memberId
            );
            if (index > -1) {
                this.selectedMembers.splice(index, 1);
                if (this.currentMemberId === memberId) {
                    this.currentMemberId = this.selectedMembers[0]?.id || null;
                }
            } else {
                const member = this.availableMembers.find(
                    (m) => m.id === memberId
                );
                if (member) {
                    this.selectedMembers.push({ ...member });
                    if (!this.currentMemberId) {
                        this.currentMemberId = memberId;
                    }
                }
            }
        },
        selectAllMembers() {
            this.selectedMembers = [...this.availableMembers];
            if (this.selectedMembers.length > 0 && !this.currentMemberId) {
                this.currentMemberId = this.selectedMembers[0].id;
            }
            ElMessage.success(
                `Semua ${this.availableMembers.length} anggota dipilih`
            );
        },
        clearSelection() {
            if (this.hasUnsavedChangesAny) {
                this.pendingNavigation = "clear";
                this.showUnsavedModal = true;
                return;
            }
            this.selectedMembers = [];
            this.currentMemberId = null;
        },
        removeMemberFromSelection(memberId) {
            if (this.unsavedChanges[memberId]) {
                ElMessage.warning(
                    "Simpan perubahan terlebih dahulu sebelum menghapus anggota"
                );
                return;
            }
            this.toggleMemberSelection(memberId);
        },
        hasKPIData(memberId) {
            return (
                this.memberKPIs[memberId] &&
                this.memberKPIs[memberId].kpis?.length > 0
            );
        },

        // Member Navigation
        switchToMember(memberId) {
            if (this.currentMemberId !== memberId) {
                // Check for unsaved changes
                if (this.unsavedChanges[this.currentMemberId]) {
                    this.pendingNavigation = memberId;
                    this.showUnsavedModal = true;
                    return;
                }
                this.currentMemberId = memberId;
                this.activeFormIndex = 0;
            }
        },
        prevMember() {
            if (this.hasPrevMember) {
                const prevIndex = this.currentMemberIndex - 1;
                this.switchToMember(this.selectedMembers[prevIndex].id);
            }
        },
        nextMember() {
            if (this.hasNextMember) {
                const nextIndex = this.currentMemberIndex + 1;
                this.switchToMember(this.selectedMembers[nextIndex].id);
            }
        },

        // KPI Data Management
        getCurrentMemberKPI() {
            if (!this.currentMemberId) return { kpis: [] };
            if (!this.memberKPIs[this.currentMemberId]) {
                this.memberKPIs[this.currentMemberId] = { kpis: [] };
            }
            return this.memberKPIs[this.currentMemberId];
        },
        getCurrentMemberKPIs() {
            return this.getCurrentMemberKPI().kpis || [];
        },
        updateCurrentMemberKPI(newData) {
            if (!this.currentMemberId) return;

            this.memberKPIs[this.currentMemberId] = { ...newData };

            // Track unsaved changes
            if (!this.unsavedChanges[this.currentMemberId]) {
                this.unsavedChanges[this.currentMemberId] = 0;
            }
            this.unsavedChanges[this.currentMemberId]++;
        },
        calculateCurrentMemberWeight() {
            const kpis = this.getCurrentMemberKPIs();
            return kpis.reduce(
                (total, kpi) => total + (parseFloat(kpi.weight) || 0),
                0
            );
        },
        calculateCurrentMemberRemainingWeight() {
            return 100 - this.calculateCurrentMemberWeight();
        },
        getMemberKPIProgress(memberId) {
            const kpis = this.memberKPIs[memberId]?.kpis || [];
            return kpis.length >= 5
                ? 100
                : Math.min(100, (kpis.length / 5) * 100);
        },
        hasUnsavedChanges(memberId) {
            return !!this.unsavedChanges[memberId];
        },

        // KPI Form Actions
        switchKPIForm(index) {
            this.activeFormIndex = index;
        },
        addNewKPIToCurrentMember() {
            const kpis = this.getCurrentMemberKPIs();
            const newKPI = {
                id: Date.now(),
                parameter: "",
                weight: "",
                bscCategory: "",
                formula: "",
                unit: "",
                target: "",
                description: "",
            };
            kpis.push(newKPI);
            this.activeFormIndex = kpis.length - 1;
            this.updateCurrentMemberKPI({
                ...this.getCurrentMemberKPI(),
                kpis,
            });
        },
        removeKPIFromCurrentMember(index) {
            const kpis = this.getCurrentMemberKPIs();
            if (kpis.length > 1) {
                kpis.splice(index, 1);
                this.activeFormIndex = Math.min(
                    this.activeFormIndex,
                    kpis.length - 1
                );
                this.updateCurrentMemberKPI({
                    ...this.getCurrentMemberKPI(),
                    kpis,
                });
            } else {
                ElMessage.warning("Minimal harus ada 1 KPI");
            }
        },
        triggerTargetForCurrentMember() {
            ElMessage.info("Membuka modal target untuk KPI saat ini");
        },

        // Save Operations
        saveCurrentMember() {
            if (!this.currentMemberId) return;

            // Simulate API call
            setTimeout(() => {
                delete this.unsavedChanges[this.currentMemberId];
                ElMessage.success(
                    `KPI untuk ${this.currentMember.name} berhasil disimpan`
                );
            }, 500);
        },
        saveAllMembers() {
            const membersToSave = this.selectedMembers.filter(
                (member) => this.unsavedChanges[member.id]
            );

            if (membersToSave.length === 0) {
                ElMessage.info("Tidak ada perubahan yang perlu disimpan");
                return;
            }

            // Simulate bulk save
            membersToSave.forEach((member) => {
                delete this.unsavedChanges[member.id];
            });

            ElMessage.success(
                `Data ${membersToSave.length} anggota berhasil disimpan`
            );
        },
        saveAllProgress() {
            this.saveAllMembers();
        },

        // Bulk Operations
        applyTemplateToAll() {
            ElMessage.info("Menerapkan template ke semua anggota terpilih");
        },
        copyKPIToAll() {
            ElMessage.info("Menyalin KPI ke anggota lainnya");
        },
        validateAllMembers() {
            const invalidMembers = this.selectedMembers.filter(
                (member) => this.getMemberKPIProgress(member.id) < 100
            );

            if (invalidMembers.length > 0) {
                ElMessage.warning(
                    `${invalidMembers.length} anggota belum lengkap`
                );
            } else {
                ElMessage.success("Semua KPI sudah valid dan lengkap");
            }
        },
        submitAllToApproval() {
            if (!this.canSubmitAll) {
                ElMessage.warning(
                    "Beberapa anggota belum menyelesaikan KPI mereka"
                );
                return;
            }

            ElMessageBox.confirm(
                `Anda akan mengajukan KPI untuk ${this.selectedMembers.length} anggota. Lanjutkan?`,
                "Konfirmasi Submit",
                {
                    confirmButtonText: "Ya, Submit",
                    cancelButtonText: "Batal",
                    type: "warning",
                }
            ).then(() => {
                ElMessage.success(
                    `KPI untuk ${this.selectedMembers.length} anggota berhasil diajukan`
                );
                this.$router.push("/kpi-team/approval");
            });
        },

        // Unsaved Changes Handling
        saveBeforeLeave() {
            this.saveAllMembers();
            this.showUnsavedModal = false;
            if (this.pendingNavigation === "clear") {
                this.selectedMembers = [];
                this.currentMemberId = null;
            } else if (typeof this.pendingNavigation === "number") {
                this.currentMemberId = this.pendingNavigation;
            }
            this.pendingNavigation = null;
        },
        discardChanges() {
            Object.keys(this.unsavedChanges).forEach((memberId) => {
                delete this.unsavedChanges[memberId];
            });
            this.showUnsavedModal = false;
            if (this.pendingNavigation === "clear") {
                this.selectedMembers = [];
                this.currentMemberId = null;
            } else if (typeof this.pendingNavigation === "number") {
                this.currentMemberId = this.pendingNavigation;
            }
            this.pendingNavigation = null;
        },

        // Utility Methods
        loadSampleMembers() {
            this.selectAllMembers();
            ElMessage.info("Contoh data dimuat");
        },
    },
    mounted() {
        // Handle single member mode from URL
        if (this.$route.query.member) {
            const memberId = parseInt(this.$route.query.member);
            const member = this.availableMembers.find((m) => m.id === memberId);
            if (member) {
                this.selectedMembers = [member];
                this.currentMemberId = memberId;
            }
        }
    },

    beforeRouteLeave(to, from, next) {
        if (this.hasUnsavedChangesAny) {
            ElMessageBox.confirm(
                "Anda memiliki perubahan yang belum disimpan. Yakin ingin meninggalkan halaman?",
                "Konfirmasi",
                {
                    confirmButtonText: "Ya, Tinggalkan",
                    cancelButtonText: "Tetap di Sini",
                    type: "warning",
                }
            )
                .then(() => {
                    next();
                })
                .catch(() => {
                    next(false);
                });
        } else {
            next();
        }
    },
};
</script>

<style scoped>
/* Multi Form Specific Styles */
.page-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--surface);
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.breadcrumb {
    margin: 0;
    background: none;
    padding: 0;
}

.breadcrumb-item a {
    color: var(--primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.breadcrumb-item.active {
    color: var(--text-muted);
}

.navigation-actions {
    display: flex;
    gap: 0.75rem;
}

/* Form Control Section */
.form-control-section {
    margin-bottom: 1.5rem;
}

.section-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.section-header h5 {
    margin: 0;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.selection-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-selection {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-selection:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Member Selection Grid */
.member-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.member-selection-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.member-selection-card:hover {
    background: var(--surface);
    border-color: var(--primary-light);
}

.member-selection-card.selected {
    background: var(--surface);
    border-color: var(--primary);
    box-shadow: 0 0 0 1px var(--primary);
}

.member-selection-card.has-data {
    border-left: 4px solid var(--success);
}

.selection-checkbox {
    position: relative;
    width: 20px;
    height: 20px;
}

.selection-checkbox input {
    opacity: 0;
    position: absolute;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    width: 20px;
    height: 20px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 4px;
    transition: all 0.3s ease;
}

.selection-checkbox input:checked + .checkmark {
    background: var(--primary);
    border-color: var(--primary);
}

.selection-checkbox input:checked + .checkmark::after {
    content: "✓";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.member-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.member-details {
    flex: 1;
}

.member-details strong {
    display: block;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.member-details span {
    display: block;
    color: var(--text);
    font-size: 0.8rem;
    margin-bottom: 0.125rem;
}

.member-details small {
    color: var(--text-muted);
    font-size: 0.75rem;
}

.member-status-indicator {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-dot.status-not-started {
    background: var(--secondary);
}
.status-dot.status-in-progress {
    background: var(--warning);
}
.status-dot.status-completed {
    background: var(--success);
}
.status-dot.status-approved {
    background: var(--primary);
}

.progress-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mini-progress-bar {
    width: 40px;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
}

.mini-progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 2px;
    transition: width 0.3s ease;
}

/* Member Tabs */
.member-tabs-container {
    background: var(--surface);
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.5rem;
    box-shadow: var(--shadow);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.member-tabs {
    display: flex;
    gap: 0.5rem;
    flex: 1;
    overflow-x: auto;
}

.member-tab {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
    min-width: 200px;
    position: relative;
}

.member-tab:hover {
    background: var(--surface);
    border-color: var(--border);
}

.member-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.member-tab.has-changes {
    border-color: var(--warning);
}

.member-tab.completed {
    border-left: 3px solid var(--success);
}

.tab-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: currentColor;
    color: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.7rem;
    position: relative;
}

.member-tab.active .tab-avatar {
    background: rgba(255, 255, 255, 0.2);
}

.change-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    color: var(--warning);
    font-size: 1.2rem;
    line-height: 1;
}

.tab-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.tab-name {
    font-weight: 600;
    font-size: 0.85rem;
}

.tab-status {
    font-size: 0.75rem;
    opacity: 0.8;
}

.member-tab.active .tab-status {
    color: rgba(255, 255, 255, 0.9);
}

.tab-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    opacity: 0.7;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.tab-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
}

.tabs-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.btn-tab-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-tab-action:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-tab-action:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.tabs-counter {
    font-size: 0.85rem;
    color: var(--text-muted);
    font-weight: 600;
    min-width: 40px;
    text-align: center;
}

/* Form Content Area */
.form-content-area {
    background: var(--surface);
    border-radius: 0 0 12px 12px;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.current-member-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2rem;
    border-bottom: 1px solid var(--border);
    gap: 2rem;
}

.member-info-large {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    flex: 1;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.member-details-large h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.5rem;
}

.member-details-large p {
    margin: 0 0 1rem 0;
    color: var(--text);
    font-size: 1rem;
}

.member-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.member-progress-indicator {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 200px;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-label {
    color: var(--text);
    font-weight: 500;
}

.progress-value {
    color: var(--dark);
    font-weight: 600;
    font-size: 1.1rem;
}

.progress-bar-lg {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill-lg {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill-lg.low {
    background: var(--danger);
}
.progress-fill-lg.medium {
    background: var(--warning);
}
.progress-fill-lg.high {
    background: var(--success);
}

.progress-details {
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.kpi-form-wrapper {
    padding: 0 2rem 2rem;
}

/* Quick Actions Panel */
.quick-actions-panel {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.actions-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
}

.actions-header h6 {
    margin: 0;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.actions-content {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    text-align: center;
}

.stat-item {
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.bulk-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.btn-bulk-action {
    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    justify-content: center;
}

.btn-bulk-action:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.save-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-save-all,
.btn-submit-all {
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    justify-content: center;
}

.btn-save-all {
    background: var(--warning);
    color: white;
}

.btn-save-all:hover:not(:disabled) {
    background: #eab308;
    transform: translateY(-1px);
}

.btn-save-all:disabled {
    background: var(--border);
    color: var(--text-muted);
    cursor: not-allowed;
    transform: none;
}

.btn-submit-all {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 2px solid transparent;
}

.btn-submit-all.ready {
    background: var(--success);
    color: white;
}

.btn-submit-all.ready:hover {
    background: var(--hcis-emerald-600);
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-form {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    padding: 4rem 2rem;
    text-align: center;
}

.empty-content {
    max-width: 400px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.empty-state-form h4 {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.empty-state-form p {
    color: var(--text-muted);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Unsaved Changes Modal */
.unsaved-modal {
    width: min(500px, 90vw);
}

.unsaved-members-list {
    margin-top: 1rem;
    max-height: 200px;
    overflow-y: auto;
}

.unsaved-member-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.member-name {
    font-weight: 500;
    color: var(--dark);
}

.changes-count {
    color: var(--warning);
    font-weight: 600;
    font-size: 0.85rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-navigation {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .navigation-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .member-selection-grid {
        grid-template-columns: 1fr;
    }

    .member-tabs-container {
        flex-direction: column;
        gap: 1rem;
    }

    .member-tabs {
        width: 100%;
    }

    .member-tab {
        min-width: 180px;
    }

    .current-member-header {
        flex-direction: column;
        gap: 1.5rem;
    }

    .member-info-large {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .quick-stats,
    .bulk-actions {
        grid-template-columns: 1fr;
    }

    .empty-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .section-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .selection-actions {
        width: 100%;
        justify-content: space-between;
    }

    .member-tab {
        min-width: 160px;
    }

    .kpi-form-wrapper {
        padding: 0 1rem 1rem;
    }
}
</style>

<style scoped>
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
    border-top: 3px solid var(--primary);
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
    box-shadow: 0 0 10px rgba(255, 107, 53, 0.3),
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
    content: "⚠️";
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
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
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
    content: "";
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
    box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 12px rgba(255, 107, 53, 0.15);
    animation: pulse-revision 2s ease-in-out infinite;

    /* Pastikan revision tetap interaktif */
    cursor: pointer;
    pointer-events: auto;
    opacity: 1;
    filter: none;
}

/* Badge/indicator revision */
.compact-card.revision::before {
    content: "⚠";
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
        box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 12px rgba(255, 107, 53, 0.15);
    }
    50% {
        box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 20px rgba(255, 107, 53, 0.3);
    }
}

/* Hover effect untuk revision */
.compact-card.revision:hover {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff1eb 100%);
    border-color: #ff5722;
    transform: translateY(-2px);
    box-shadow: 0 0 0 1px #ff5722 inset, 0 6px 16px rgba(255, 87, 34, 0.25);
}

/* Active state untuk revision */
.compact-card.revision:active {
    transform: translateY(0);
    box-shadow: 0 0 0 1px #ff6b35 inset, 0 2px 8px rgba(255, 107, 53, 0.2);
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
    content: "🚫";
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
    background: var(--surface-soft);
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
