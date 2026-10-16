<template>
    <Modal
        :show="show"
        :title="modalTitle"
        :subtitle="detail ? `Step ${detail.Approval_Order} • Sumber: ${detail.Source_Reference}` : ''"
        size="xlarge"
        :showFooter="false"
        :skeleton="false"
        @close="handleClose"
    >
        <template #body>
            <div v-if="detail" class="assignment-body">
                <!-- Mode switcher -->
                <div class="mode-switch mb-3">
                    <button :class="['mode-btn', { active: mode === 'list' }]" @click="switchMode('list')">
                        <i class="bi bi-people me-1"></i>Per Requester
                    </button>
                    <button
                        v-if="canAssign"
                        :class="['mode-btn', { active: mode === 'bulk' }]"
                        @click="switchMode('bulk')"
                    >
                        <i class="bi bi-lightning me-1"></i>Bulk Assign
                    </button>
                    <button :class="['mode-btn', { active: mode === 'history' }]" @click="switchMode('history')">
                        <i class="bi bi-clock-history me-1"></i>Riwayat
                    </button>
                </div>

                <!-- ============ MODE: LIST ============ -->
                <div v-if="mode === 'list'">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <el-input
                            v-model="search"
                            placeholder="Cari requester (nama / kode / NIK)..."
                            clearable
                            style="max-width: 320px"
                            @input="debouncedFetch"
                        >
                            <template #prefix><i class="bi bi-search"></i></template>
                        </el-input>

                        <el-select
                            v-if="canAssign"
                            v-model="newRequesterKode"
                            filterable
                            remote
                            clearable
                            placeholder="+ Atur requester baru..."
                            :remote-method="searchKaryawanOptions"
                            :loading="karyawanLoading"
                            style="max-width: 320px"
                            @change="startEditNewRequester"
                        >
                            <el-option
                                v-for="k in karyawanOptions"
                                :key="k.Kode_Karyawan"
                                :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                :value="k.Kode_Karyawan"
                            />
                        </el-select>

                        <span class="ms-auto small text-muted">{{ pagination.total }} requester</span>
                    </div>

                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="text-muted small mt-2">Memuat assignment...</p>
                    </div>

                    <div v-else-if="!items.length" class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Belum ada assignment approver di step ini.</p>
                    </div>

                    <div v-else class="requester-list">
                        <div v-for="item in items" :key="item.requester.kode" class="requester-row">
                            <div class="requester-info-wrapper d-flex align-items-center gap-2">
                                <div class="user-avatar-initials-xs">
                                    {{ getUserInitials(item.requester.nama) }}
                                </div>
                                <div class="requester-info">
                                    <div class="fw-semibold text-dark text-sm">{{ item.requester.nama }}</div>
                                    <div class="small text-muted font-monospace text-xs">{{ item.requester.kode }}</div>
                                </div>
                            </div>
                            <div class="approver-chips">
                                <template v-for="(approver, idx) in item.approvers" :key="approver.pk">
                                    <i v-if="idx > 0" class="bi bi-chevron-right text-muted mx-1" style="font-size: 0.7rem;"></i>
                                    <span
                                        class="chip"
                                        :class="{ 'chip-inactive': approver.approver_aktif !== 'Y' }"
                                        :title="approver.approver_aktif !== 'Y' ? 'Karyawan tidak aktif: ' + (approver.approver_nama || approver.approver_kode) : (approver.approver_nama || approver.approver_kode)"
                                    >
                                        <span class="chip-order">{{ approver.order_flow }}</span>
                                        <span class="chip-name">{{ approver.approver_nama || approver.approver_kode }}</span>
                                    </span>
                                </template>
                            </div>
                            <button v-if="canAssign" class="btn-edit-flow" @click="startEdit(item)">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Pagination sederhana -->
                    <div v-if="pagination.total_pages > 1" class="d-flex justify-content-center gap-2 mt-3">
                        <el-button size="small" :disabled="pagination.page <= 1" @click="goPage(pagination.page - 1)">
                            <i class="bi bi-chevron-left"></i>
                        </el-button>
                        <span class="small align-self-center text-muted">
                            Hal {{ pagination.page }} / {{ pagination.total_pages }}
                        </span>
                        <el-button
                            size="small"
                            :disabled="pagination.page >= pagination.total_pages"
                            @click="goPage(pagination.page + 1)"
                        >
                            <i class="bi bi-chevron-right"></i>
                        </el-button>
                    </div>
                </div>

                <!-- ============ MODE: EDIT FLOW ============ -->
                <div v-else-if="mode === 'edit'" class="edit-panel">
                    <div class="edit-header mb-3">
                        <button class="btn-back" @click="switchMode('list')"><i class="bi bi-arrow-left"></i></button>
                        <div>
                            <div class="fw-bold">{{ editing.nama }}</div>
                            <div class="small text-muted font-monospace">{{ editing.kode }}</div>
                        </div>
                    </div>

                    <div class="small text-muted mb-2">
                        Urutan level menentukan siapa approve duluan. Kosongkan semua untuk menghapus flow requester ini.
                    </div>

                    <div class="draft-list mb-3">
                        <div v-for="(approver, idx) in draftApprovers" :key="idx" class="draft-row">
                            <span class="chip-order">{{ idx + 1 }}</span>
                            <span class="flex-grow-1">{{ approver.nama || approver.kode }}</span>
                            <button class="btn-mini" :disabled="idx === 0" title="Naik" @click="moveDraft(idx, -1)">
                                <i class="bi bi-arrow-up"></i>
                            </button>
                            <button
                                class="btn-mini"
                                :disabled="idx === draftApprovers.length - 1"
                                title="Turun"
                                @click="moveDraft(idx, 1)"
                            >
                                <i class="bi bi-arrow-down"></i>
                            </button>
                            <button class="btn-mini btn-mini-danger" title="Hapus" @click="draftApprovers.splice(idx, 1)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div v-if="!draftApprovers.length" class="small text-muted fst-italic py-2">
                            Belum ada approver — simpan untuk menghapus flow.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <el-select
                            v-model="addApproverKode"
                            filterable
                            remote
                            clearable
                            placeholder="Tambah approver..."
                            :remote-method="searchKaryawanOptions"
                            :loading="karyawanLoading"
                            style="max-width: 320px"
                            @change="addApprover"
                        >
                            <el-option
                                v-for="k in karyawanOptions"
                                :key="k.Kode_Karyawan"
                                :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                :value="k.Kode_Karyawan"
                                :disabled="k.Kode_Karyawan === editing.kode || draftApprovers.some((a) => a.kode === k.Kode_Karyawan)"
                            />
                        </el-select>
                    </div>

                    <el-input
                        v-model="catatan"
                        type="textarea"
                        :rows="2"
                        maxlength="500"
                        placeholder="Catatan perubahan (opsional)..."
                        class="mb-3"
                    />

                    <div class="d-flex justify-content-end gap-2">
                        <el-button @click="switchMode('list')" :disabled="saving">Batal</el-button>
                        <el-button type="primary" :loading="saving" @click="saveFlow">
                            <i class="bi bi-check-lg me-1"></i>Simpan Flow
                        </el-button>
                    </div>
                </div>

                <!-- ============ MODE: BULK ============ -->
                <div v-else-if="mode === 'bulk'" class="bulk-panel">
                    <div class="small text-muted mb-3">
                        Pasang satu approver di level tertentu untuk banyak requester sekaligus. Approver lama di level
                        tersebut akan digantikan.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-sm">Approver</label>
                            <el-select
                                v-model="bulk.approverKode"
                                filterable
                                remote
                                clearable
                                placeholder="Pilih approver..."
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
                        <div class="col-md-6">
                            <label class="form-label-sm">Level (order_flow)</label>
                            <el-input-number v-model="bulk.orderFlow" :min="1" :max="10" style="width: 100%" />
                        </div>
                        <div class="col-12">
                            <label class="form-label-sm">Requester</label>
                            <el-select
                                v-model="bulk.requesterKodes"
                                multiple
                                filterable
                                remote
                                clearable
                                placeholder="Pilih satu atau lebih requester..."
                                :remote-method="searchKaryawanOptions"
                                :loading="karyawanLoading"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="k in karyawanOptions"
                                    :key="k.Kode_Karyawan"
                                    :label="`${k.Nama} (${k.Kode_Karyawan})`"
                                    :value="k.Kode_Karyawan"
                                    :disabled="k.Kode_Karyawan === bulk.approverKode"
                                />
                            </el-select>
                        </div>
                        <div class="col-12">
                            <el-input
                                v-model="bulk.catatan"
                                type="textarea"
                                :rows="2"
                                maxlength="500"
                                placeholder="Catatan (opsional)..."
                            />
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <el-button
                            type="primary"
                            :loading="saving"
                            :disabled="!bulk.approverKode || !bulk.requesterKodes.length"
                            @click="applyBulk"
                        >
                            <i class="bi bi-lightning me-1"></i>Terapkan Bulk Assign
                        </el-button>
                    </div>
                </div>

                <!-- ============ MODE: HISTORY ============ -->
                <div v-else-if="mode === 'history'">
                    <div v-if="historyLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <div v-else-if="historyNotice" class="alert-notice">{{ historyNotice }}</div>
                    <div v-else-if="!historyItems.length" class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        <p>Belum ada riwayat perubahan untuk step ini.</p>
                    </div>
                    <div v-else class="history-list">
                        <div v-for="h in historyItems" :key="h.Id" class="history-row">
                            <div class="d-flex justify-content-between flex-wrap gap-1">
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <span class="pill pill-info">{{ h.Jenis_Transaksi }}</span>
                                    <span v-if="h.Source_Type" class="badge bg-indigo-soft text-indigo border border-indigo border-opacity-25 font-monospace text-xxs px-1.5 py-0.5 rounded">
                                        {{ h.Source_Type }}
                                    </span>
                                    <span v-if="h.Source_Table" class="badge bg-light text-secondary border font-monospace text-xxs px-1.5 py-0.5 rounded">
                                        {{ h.Source_Table }}
                                    </span>
                                </div>
                                <span class="small text-muted">{{ formatDateTime(h.Created_At) }}</span>
                            </div>
                            <div class="small mt-1">
                                Requester <b class="font-monospace">{{ h.Kode_Karyawan_Requester }}</b>
                                oleh <b>{{ h.Created_By_Nama || h.Created_By }}</b>
                            </div>
                            <div class="small text-muted" v-if="h.Catatan">"{{ h.Catatan }}"</div>
                            <div class="small mt-1 history-diff">
                                <span class="text-danger">Sebelum: {{ summarizeSnapshot(h.Data_Sebelum) }}</span>
                                <span class="text-success ms-2">Sesudah: {{ summarizeSnapshot(h.Data_Sesudah) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import { ElMessage } from "element-plus";
import Modal from "@/components/ui/modalV2-noir.vue";

export default {
    name: "AssignmentEditorModal",
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        detail: { type: Object, default: null },
        canAssign: { type: Boolean, default: false },
    },
    emits: ["close"],
    data() {
        return {
            mode: "list",
            loading: false,
            saving: false,
            items: [],
            pagination: { page: 1, per_page: 15, total: 0, total_pages: 1 },
            search: "",
            karyawanOptions: [],
            karyawanLoading: false,
            newRequesterKode: null,
            editing: { kode: null, nama: null },
            draftApprovers: [],
            addApproverKode: null,
            catatan: "",
            bulk: { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: "" },
            historyItems: [],
            historyLoading: false,
            historyNotice: null,
        };
    },
    computed: {
        modalTitle() {
            if (!this.detail) return "Kelola Approver";
            return `${this.canAssign ? "Kelola" : "Lihat"} Approver — ${this.detail.Nama_Approval || ""}`;
        },
    },
    watch: {
        show(value) {
            if (value && this.detail) {
                this.resetState();
                this.fetchAssignments();
            }
        },
    },
    created() {
        this.debouncedFetch = debounce(() => {
            this.pagination.page = 1;
            this.fetchAssignments();
        }, 400);
    },
    methods: {
        resetState() {
            this.mode = "list";
            this.search = "";
            this.items = [];
            this.pagination = { page: 1, per_page: 15, total: 0, total_pages: 1 };
            this.newRequesterKode = null;
            this.bulk = { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: "" };
        },
        handleClose() {
            this.$emit("close");
        },
        switchMode(mode) {
            this.mode = mode;
            if (mode === "history") this.fetchHistory();
            if (mode === "list") this.fetchAssignments();
        },
        async fetchAssignments() {
            if (!this.detail) return;
            this.loading = true;
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/assignments", {
                    params: {
                        config_detail_id: this.detail.Id,
                        search: this.search || undefined,
                        page: this.pagination.page,
                        per_page: this.pagination.per_page,
                    },
                });
                this.items = data.items || [];
                this.pagination = { ...this.pagination, ...(data.pagination || {}) };
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memuat assignment.");
            } finally {
                this.loading = false;
            }
        },
        goPage(page) {
            this.pagination.page = page;
            this.fetchAssignments();
        },
        async searchKaryawanOptions(query) {
            this.karyawanLoading = true;
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/karyawan/search", {
                    params: { search: query || undefined },
                });
                this.karyawanOptions = data.items || [];
            } catch {
                this.karyawanOptions = [];
            } finally {
                this.karyawanLoading = false;
            }
        },
        startEdit(item) {
            this.editing = { kode: item.requester.kode, nama: item.requester.nama };
            this.draftApprovers = item.approvers.map((a) => ({
                kode: a.approver_kode,
                nama: a.approver_nama,
            }));
            this.catatan = "";
            this.addApproverKode = null;
            this.mode = "edit";
        },
        startEditNewRequester(kode) {
            if (!kode) return;
            const picked = this.karyawanOptions.find((k) => k.Kode_Karyawan === kode);
            const existing = this.items.find((i) => i.requester.kode === kode);
            if (existing) {
                this.startEdit(existing);
            } else {
                this.editing = { kode, nama: picked?.Nama || kode };
                this.draftApprovers = [];
                this.catatan = "";
                this.addApproverKode = null;
                this.mode = "edit";
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
            this.saving = true;
            try {
                const { data } = await axios.post("/api/v1/menu-approval-matrix/assignments/replace", {
                    config_detail_id: this.detail.Id,
                    kode_requester: this.editing.kode,
                    approvers: this.draftApprovers.map((a, idx) => ({ kode: a.kode, order_flow: idx + 1 })),
                    catatan: this.catatan || null,
                });
                ElMessage.success(data.message || "Assignment disimpan.");
                this.switchMode("list");
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menyimpan assignment.");
            } finally {
                this.saving = false;
            }
        },
        async applyBulk() {
            this.saving = true;
            try {
                const { data } = await axios.post("/api/v1/menu-approval-matrix/assignments/bulk", {
                    config_detail_id: this.detail.Id,
                    kode_approver: this.bulk.approverKode,
                    requester_kodes: this.bulk.requesterKodes,
                    order_flow: this.bulk.orderFlow,
                    catatan: this.bulk.catatan || null,
                });
                ElMessage.success(data.message || "Bulk assign selesai.");
                if (data.detail?.skipped?.length) {
                    const reasons = data.detail.skipped.map((s) => `${s.requester}: ${s.alasan}`).join("; ");
                    ElMessage.warning(`Dilewati — ${reasons}`);
                }
                this.bulk = { approverKode: null, orderFlow: 1, requesterKodes: [], catatan: "" };
                this.switchMode("list");
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal bulk assign.");
            } finally {
                this.saving = false;
            }
        },
        async fetchHistory() {
            this.historyLoading = true;
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/assignments/history", {
                    params: { config_detail_id: this.detail.Id, limit: 30 },
                });
                this.historyItems = data.items || [];
                this.historyNotice = data.notice || null;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memuat riwayat.");
            } finally {
                this.historyLoading = false;
            }
        },
        summarizeSnapshot(json) {
            try {
                const rows = JSON.parse(json || "[]");
                if (!rows.length) return "(kosong)";
                return rows
                    .map((r) => `${r.order_flow}.${r.Kode_Karyawan_Approver || r.Kode_Karyawan}`)
                    .join(" → ");
            } catch {
                return "(tidak terbaca)";
            }
        },
        formatDateTime(value) {
            if (!value) return "-";
            return new Date(value).toLocaleString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        },
        getUserInitials(name) {
            if (!name) return "?";
            const parts = name.split(" ").filter((p) => p.length > 0);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return parts[0][0].toUpperCase();
        },
    },
};
</script>

<style scoped>
.mode-switch {
    display: flex;
    gap: 0.25rem;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 0.25rem;
    width: fit-content;
}
.mode-btn {
    border: none;
    background: transparent;
    padding: 0.4rem 0.9rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}
.mode-btn.active {
    background: #fff;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}
.requester-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 50vh;
    overflow-y: auto;
}
.requester-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}
.requester-info {
    min-width: 180px;
}
.approver-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    flex: 1;
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem 0.2rem 0.25rem;
    border-radius: 20px;
}
.chip-inactive {
    background: #fef2f2;
    color: #b91c1c;
}
.chip-order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    color: #4f46e5;
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid #c7d2fe;
}
.btn-edit-flow {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 8px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    cursor: pointer;
}
.btn-edit-flow:hover {
    border-color: #6366f1;
    color: #6366f1;
}
.edit-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.btn-back {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 8px;
    width: 32px;
    height: 32px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.draft-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.draft-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.45rem 0.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    font-size: 0.85rem;
}
.btn-mini {
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 6px;
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    cursor: pointer;
    font-size: 0.7rem;
}
.btn-mini:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.btn-mini-danger:hover {
    border-color: #ef4444;
    color: #ef4444;
}
.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: #334155;
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
    color: #94a3b8 !important;
}
.empty-state i {
    font-size: 2rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin-bottom: 0.5rem !important;
}
.alert-notice {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
}
.history-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 55vh;
    overflow-y: auto;
}
.history-row {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    background: #fff;
}
.history-diff {
    font-family: monospace;
    font-size: 0.72rem;
}
.pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
}
.pill-info {
    background: #eef2ff;
    color: #4f46e5;
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
    color: #ffffff;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
    border-color: #818cf8 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.05) !important;
}
</style>
