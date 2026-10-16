<template>
    <div class="stitch-card p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h5 class="text-dark fw-bold m-0">
                    <i class="bi bi-list-ol me-2"></i>Step Matrix — {{ config.Kode_Config }}
                </h5>
                <p class="small text-muted m-0">
                    Step bertanda <span class="pill pill-success">Editable</span> bisa dikelola assignment approver-nya
                    (requester → approver). Step lain di-resolve otomatis oleh sistem.
                </p>
            </div>
            <el-button v-if="capabilities.isSystemAdmin" type="primary" size="small" @click="openStepModal(null)">
                <i class="bi bi-plus-lg me-1"></i> Tambah Step
            </el-button>
        </div>

        <!-- Alur Persetujuan Visualizer (Timeline) -->
        <div v-if="details && details.length" class="border rounded-3 p-3 bg-white shadow-sm mb-4">
            <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-bezier2 me-1 text-indigo"></i> Visualisasi Alur Approval
            </h6>
            
            <!-- Horizontal pipeline for desktop, vertical for mobile -->
            <div class="d-flex flex-column flex-md-row align-items-stretch justify-content-start gap-3 py-2 overflow-x-auto visual-pipeline">
                <div 
                    v-for="(item, index) in sortedDetails" 
                    :key="item.Id"
                    class="d-flex align-items-center flex-grow-1 position-relative pipeline-step-wrapper"
                >
                    <!-- Step Card Node -->
                    <div 
                        class="pipeline-card p-3 rounded-3 border flex-grow-1 position-relative d-flex align-items-start gap-2.5 shadow-xs"
                        :class="item.Flag_Aktif === 'Y' ? 'border-indigo-subtle bg-indigo-50/10' : 'bg-light text-muted'"
                    >
                        <!-- Step Circle badge -->
                        <div 
                            class="pipeline-node-circle d-flex align-items-center justify-content-center fw-bold rounded-circle"
                            :class="item.Flag_Aktif === 'Y' ? 'bg-indigo text-white' : 'bg-secondary text-white'"
                            style="width: 28px; height: 28px; font-size: 0.78rem; flex-shrink: 0;"
                        >
                            {{ item.Approval_Order }}
                        </div>
                        
                        <div class="flex-grow-1 min-w-0">
                            <!-- Approver Name / Code -->
                            <div class="fw-bold text-sm text-dark text-truncate">
                                {{ item.Custom_Approver_Kode ? `Custom: ${item.Custom_Approver_Kode}` : item.Nama_Approval || "—" }}
                            </div>
                            <div class="text-xs text-muted text-truncate" v-if="item.Source_Reference">
                                {{ item.Source_Reference }}
                            </div>
                            
                            <!-- Attribute Badges -->
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span :class="['pill', sourceBadgeClass(item)]" style="font-size: 0.6rem; padding: 0.1rem 0.4rem;">
                                    {{ item.approver_mode }}
                                </span>
                                <span v-if="item.editable" class="pill pill-success" style="font-size: 0.6rem; padding: 0.1rem 0.4rem;">
                                    Editable
                                </span>
                                <span v-if="item.Is_Mandatory === 'Y'" class="pill pill-warning" style="font-size: 0.6rem; padding: 0.1rem 0.4rem;">
                                    Mandatory
                                </span>
                                <span v-if="item.Stop_If_Rejected === 'Y'" class="pill pill-danger-soft" style="font-size: 0.6rem; padding: 0.1rem 0.4rem;">
                                    Stop-on-Reject
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connector arrow (visible only on desktop between steps) -->
                    <div 
                        v-if="index < sortedDetails.length - 1" 
                        class="d-none d-md-flex align-items-center justify-content-center text-muted px-1 connector-arrow"
                    >
                        <i class="bi bi-arrow-right fs-4 text-indigo" style="opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>

        <TableNoir
            :data="details"
            :columns="stepColumns"
            :loading="loading"
            :showActions="false"
            :showDelete="false"
            :showPagination="false"
            :fullPagination="[]"
            rowKey="Id"
            emptyMessage="Config ini belum memiliki step approval."
        >
            <template #column-Approval_Order="{ item }">
                <div class="text-center">
                    <span class="order-badge">{{ item.Approval_Order }}</span>
                </div>
            </template>

            <template #column-approver="{ item }">
                <div class="fw-semibold">
                    {{ item.Custom_Approver_Kode ? `Custom: ${item.Custom_Approver_Kode}` : item.Nama_Approval || "—" }}
                </div>
                <div class="small text-muted" v-if="item.Source_Reference">{{ item.Source_Reference }}</div>
            </template>

            <template #column-mode="{ item }">
                <span :class="['pill', sourceBadgeClass(item)]">{{ item.approver_mode }}</span>
                <span v-if="item.editable" class="pill pill-success ms-1">Editable</span>
            </template>

            <template #column-Is_Mandatory="{ item }">
                <div class="text-center">
                    <i :class="flagIcon(item.Is_Mandatory)"></i>
                </div>
            </template>

            <template #column-Stop_If_Rejected="{ item }">
                <div class="text-center">
                    <i :class="flagIcon(item.Stop_If_Rejected)"></i>
                </div>
            </template>

            <template #column-Order_Approver_Limit="{ item }">
                <div class="text-center fw-semibold text-muted" style="font-size: 0.75rem;">
                    {{ item.Order_Approver_Limit || "Semua" }}
                </div>
            </template>

            <template #column-Flag_Aktif="{ item }">
                <div class="text-center">
                    <span :class="['pill', item.Flag_Aktif === 'Y' ? 'pill-success' : 'pill-muted']">
                        {{ item.Flag_Aktif === "Y" ? "Aktif" : "Nonaktif" }}
                    </span>
                </div>
            </template>

            <template #column-aksi="{ item }">
                <div class="d-flex gap-1 flex-wrap">
                    <button
                        v-if="item.editable && capabilities.approvalMatrix.canAssign"
                        class="btn-kelola"
                        @click="openAssignmentModal(item)"
                    >
                        <i class="bi bi-people me-1"></i>Kelola Approver
                    </button>
                    <button
                        v-else-if="item.editable"
                        class="btn-kelola btn-kelola-view"
                        @click="openAssignmentModal(item)"
                    >
                        <i class="bi bi-eye me-1"></i>Lihat Approver
                    </button>
                    <template v-if="capabilities.isSystemAdmin">
                        <button class="btn-action" title="Edit step" @click="openStepModal(item)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button
                            v-if="item.Flag_Aktif === 'Y'"
                            class="btn-action btn-danger-soft"
                            title="Nonaktifkan step"
                            @click="deactivateStep(item)"
                        >
                            <i class="bi bi-slash-circle"></i>
                        </button>
                    </template>
                </div>
            </template>
        </TableNoir>

        <StepCrudModal
            :show="showStepModal"
            :configId="config.Id"
            :grupId="grupId"
            :step="editingStep"
            @close="showStepModal = false"
            @saved="onStepSaved"
        />
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage, ElMessageBox } from "element-plus";
import TableNoir from "@/components/legacy/table-noir.vue";
import StepCrudModal from "./StepCrudModal.vue";

export default {
    name: "ConfigStepMatrix",
    components: { TableNoir, StepCrudModal },
    props: {
        config: { type: Object, required: true },
        capabilities: { type: Object, required: true },
        grupId: { type: Number, required: true },
    },
    emits: ["refresh-configs", "manage-step"],
    data() {
        return {
            loading: false,
            details: [],
            showStepModal: false,
            editingStep: null,
        };
    },
    computed: {
        stepColumns() {
            return [
                { key: "Approval_Order", title: "Urutan", width: "80px" },
                { key: "approver", title: "Approver / Jenis Approval" },
                { key: "mode", title: "Mode", width: "180px" },
                { key: "Is_Mandatory", title: "Mandatory", width: "100px" },
                { key: "Stop_If_Rejected", title: "Stop Jika Ditolak", width: "130px" },
                { key: "Order_Approver_Limit", title: "Limit Level", width: "100px" },
                { key: "Flag_Aktif", title: "Status", width: "100px" },
                { key: "aksi", title: "Aksi", width: "220px" },
            ];
        },
        sortedDetails() {
            if (!this.details) return [];
            return [...this.details].sort((a, b) => (a.Approval_Order || 0) - (b.Approval_Order || 0));
        },
    },
    mounted() {
        this.fetchMatrix();
    },
    methods: {
        async fetchMatrix() {
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/configs/${this.config.Id}/matrix`, {
                    params: { grup_id: this.grupId },
                });
                this.details = data.details || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memuat step matrix.");
            } finally {
                this.loading = false;
            }
        },
        sourceBadgeClass(item) {
            if (item.approver_mode === "CUSTOM") return "pill-purple";
            if (item.editable) return "pill-info";
            return "pill-neutral";
        },
        flagIcon(flag) {
            return flag === "Y" ? "bi bi-check-circle-fill text-success" : "bi bi-dash-circle text-muted";
        },
        openAssignmentModal(detail) {
            this.$emit("manage-step", { detail });
        },
        openStepModal(step) {
            this.editingStep = step;
            this.showStepModal = true;
        },
        onStepSaved() {
            this.showStepModal = false;
            this.fetchMatrix();
            this.$emit("refresh-configs");
        },
        async deactivateStep(item) {
            try {
                await ElMessageBox.confirm(
                    `Nonaktifkan step urutan ${item.Approval_Order}? Flow baru tidak akan melewati step ini.`,
                    "Konfirmasi",
                    { confirmButtonText: "Nonaktifkan", cancelButtonText: "Batal", type: "warning" },
                );
            } catch {
                return;
            }

            try {
                const { data } = await axios.delete(`/api/v1/menu-approval-matrix/config-details/${item.Id}`, {
                    data: { grup_id: this.grupId },
                });
                ElMessage.success(data.message || "Step dinonaktifkan.");
                this.fetchMatrix();
                this.$emit("refresh-configs");
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menonaktifkan step.");
            }
        },
    },
};
</script>

<style scoped>
.stitch-card {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
}
.order-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-weight: 700;
    font-size: 0.8rem;
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
.pill-purple {
    background: #faf5ff;
    color: var(--hcis-purple-600);
}
.btn-kelola {
    border: 1px solid var(--hcis-indigo-500);
    background: var(--hcis-indigo-500);
    color: #fff;
    border-radius: 8px;
    padding: 0.3rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
}
.btn-kelola:hover {
    background: var(--hcis-indigo-600);
}
.btn-kelola-view {
    background: #fff;
    color: var(--hcis-indigo-500);
}
.btn-kelola-view:hover {
    background: var(--hcis-indigo-50);
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

/* Pipeline Visualizer Styles */
.visual-pipeline {
    scrollbar-width: thin;
    padding-bottom: 8px;
}
.pipeline-card {
    transition: all 0.3s ease;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    min-width: 200px;
}
.pipeline-step-wrapper:hover .pipeline-card {
    border-color: var(--hcis-indigo-500);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
}
.border-indigo-subtle {
    border-color: var(--hcis-indigo-200) !important;
}
.bg-indigo-50\/10 {
    background-color: rgba(238, 242, 255, 0.3) !important;
}
.bg-indigo {
    background-color: var(--hcis-indigo-500) !important;
}
.shadow-xs {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.gap-2\.5 {
    gap: 0.625rem;
}
.pill-danger-soft {
    background: var(--hcis-red-50);
    color: var(--hcis-red-500);
    border: 1px solid rgba(239, 68, 68, 0.1);
}
</style>
