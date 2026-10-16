<template>
    <Modal
        :show="show"
        title="Jenis Approval"
        subtitle="Akar resolusi approver — menentukan dari mana approver diambil (tabel, role, atau karyawan langsung)."
        size="xlarge"
        :showFooter="false"
        :skeleton="false"
        @close="$emit('close')"
    >
        <template #body>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <el-input
                    v-model="search"
                    placeholder="Cari nama / source reference..."
                    clearable
                    style="max-width: 320px"
                    @input="debouncedFetch"
                >
                    <template #prefix><i class="bi bi-search"></i></template>
                </el-input>
                <el-button type="primary" size="small" class="ms-auto" @click="openForm(null)">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Jenis Approval
                </el-button>
            </div>

            <!-- Form inline -->
            <div v-if="showForm" class="form-card mb-3">
                <div class="fw-bold mb-2">{{ editing ? "Edit" : "Tambah" }} Jenis Approval</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-sm">Nama Approval <span class="text-danger">*</span></label>
                        <el-input v-model="form.Nama_Approval" maxlength="100" placeholder="cth: Atasan Langsung" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Source Type <span class="text-danger">*</span></label>
                        <el-select v-model="form.Source_Type" style="width: 100%">
                            <el-option v-for="t in sourceTypes" :key="t.value" :label="t.label" :value="t.value" />
                        </el-select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Source Reference</label>
                        <el-select
                            v-if="isTableType"
                            v-model="form.Source_Reference"
                            filterable
                            allow-create
                            clearable
                            placeholder="Pilih tabel whitelist..."
                            style="width: 100%"
                        >
                            <el-option
                                v-for="t in sourceTables"
                                :key="t.table"
                                :label="t.table + (t.exists ? '' : ' (tabel tidak ditemukan!)')"
                                :value="t.table"
                            />
                        </el-select>
                        <el-input
                            v-else
                            v-model="form.Source_Reference"
                            maxlength="100"
                            :placeholder="referencePlaceholder"
                        />
                        <div class="hint">{{ referenceHint }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Keterangan</label>
                        <el-input v-model="form.Keterangan" maxlength="255" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-sm">Auto Approve</label>
                        <div><el-switch v-model="autoApprove" active-text="Ya" inactive-text="Tidak" /></div>
                    </div>
                    <div class="col-md-4" v-if="editing">
                        <label class="form-label-sm">Status</label>
                        <div><el-switch v-model="flagAktif" active-text="Aktif" inactive-text="Nonaktif" /></div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <el-button size="small" @click="showForm = false" :disabled="saving">Batal</el-button>
                    <el-button type="primary" size="small" :loading="saving" :disabled="!form.Nama_Approval" @click="save">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </el-button>
                </div>
            </div>

            <!-- List -->
            <div v-if="loading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div v-else class="jenis-list">
                <div v-for="j in items" :key="j.Id_Jenis_Approval" class="jenis-row">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ j.Nama_Approval }}</span>
                            <span class="pill pill-info">{{ j.Source_Type }}</span>
                            <span v-if="j.Source_Reference" class="pill pill-neutral font-monospace">{{ j.Source_Reference }}</span>
                            <span :class="['pill', j.Flag_Aktif === 'Y' ? 'pill-success' : 'pill-muted']">
                                {{ j.Flag_Aktif === "Y" ? "Aktif" : "Nonaktif" }}
                            </span>
                            <span v-if="j.source_validation && !j.source_validation.valid" class="pill pill-danger" :title="j.source_validation.pesan">
                                <i class="bi bi-exclamation-triangle me-1"></i>Perlu dicek
                            </span>
                            <span v-else-if="j.source_validation && j.source_validation.editable" class="pill pill-success">
                                Assignment editable
                            </span>
                        </div>
                        <div class="small text-muted" v-if="j.Keterangan">{{ j.Keterangan }}</div>
                        <div class="small text-danger" v-if="j.source_validation?.pesan">{{ j.source_validation.pesan }}</div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn-action" title="Edit" @click="openForm(j)"><i class="bi bi-pencil"></i></button>
                        <button
                            v-if="j.Flag_Aktif === 'Y'"
                            class="btn-action btn-danger-soft"
                            title="Nonaktifkan"
                            @click="deactivate(j)"
                        >
                            <i class="bi bi-slash-circle"></i>
                        </button>
                    </div>
                </div>
                <div v-if="!items.length" class="empty-state">
                    <i class="bi bi-bezier2"></i>
                    <p>Belum ada jenis approval.</p>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";
import { ElMessage, ElMessageBox } from "element-plus";
import Modal from "@/components/legacy/modalV2-noir.vue";

const emptyForm = () => ({
    Nama_Approval: "",
    Source_Type: "TABLE",
    Source_Reference: "",
    Auto_Approve: "T",
    Keterangan: "",
    Flag_Aktif: "Y",
});

export default {
    name: "JenisApprovalModal",
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
    },
    emits: ["close"],
    data() {
        return {
            loading: false,
            saving: false,
            items: [],
            sourceTables: [],
            search: "",
            showForm: false,
            editing: null,
            form: emptyForm(),
            sourceTypes: [
                { value: "TABLE", label: "TABLE — tabel flow (requester → approver)" },
                { value: "KPI_TABLE", label: "KPI_TABLE — tabel flow KPI legacy" },
                { value: "GROUP_TABLE", label: "GROUP_TABLE — grup paralel" },
                { value: "DIRECT_EMPLOYEE", label: "DIRECT_EMPLOYEE — satu karyawan tetap" },
                { value: "ROLE", label: "ROLE — role functional sebagai pool" },
            ],
        };
    },
    computed: {
        isTableType() {
            return ["TABLE", "KPI_TABLE"].includes(this.form.Source_Type);
        },
        referencePlaceholder() {
            if (this.form.Source_Type === "ROLE") return "Role_Code dari N_HRIS_Role_Functional";
            if (this.form.Source_Type === "DIRECT_EMPLOYEE") return "Kode_Karyawan approver tetap";
            return "Referensi sumber";
        },
        referenceHint() {
            if (this.isTableType) return "Hanya tabel whitelist yang assignment-nya bisa dikelola dari halaman ini.";
            if (this.form.Source_Type === "ROLE") return "Anggota role akan menjadi pool approver (paralel).";
            if (this.form.Source_Type === "DIRECT_EMPLOYEE") return "Approver tunggal yang selalu sama.";
            return "";
        },
        autoApprove: {
            get() {
                return this.form.Auto_Approve === "Y";
            },
            set(value) {
                this.form.Auto_Approve = value ? "Y" : "T";
            },
        },
        flagAktif: {
            get() {
                return this.form.Flag_Aktif === "Y";
            },
            set(value) {
                this.form.Flag_Aktif = value ? "Y" : "T";
            },
        },
    },
    watch: {
        show(value) {
            if (value) {
                this.showForm = false;
                this.fetchItems();
            }
        },
    },
    created() {
        this.debouncedFetch = debounce(this.fetchItems, 400);
    },
    methods: {
        async fetchItems() {
            this.loading = true;
            try {
                const { data } = await axios.get("/api/v1/menu-approval-matrix/jenis-approval", {
                    params: { search: this.search || undefined },
                });
                this.items = data.items || [];
                this.sourceTables = data.sourceTables || [];
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memuat jenis approval.");
            } finally {
                this.loading = false;
            }
        },
        openForm(jenis) {
            this.editing = jenis;
            this.form = jenis
                ? {
                      Nama_Approval: jenis.Nama_Approval || "",
                      Source_Type: jenis.Source_Type || "TABLE",
                      Source_Reference: jenis.Source_Reference || "",
                      Auto_Approve: jenis.Auto_Approve || "T",
                      Keterangan: jenis.Keterangan || "",
                      Flag_Aktif: jenis.Flag_Aktif || "Y",
                  }
                : emptyForm();
            this.showForm = true;
        },
        async save() {
            this.saving = true;
            try {
                const payload = {
                    ...this.form,
                    Source_Reference: this.form.Source_Reference || null,
                    Keterangan: this.form.Keterangan || null,
                };

                const { data } = this.editing
                    ? await axios.put(`/api/v1/menu-approval-matrix/jenis-approval/${this.editing.Id_Jenis_Approval}`, payload)
                    : await axios.post("/api/v1/menu-approval-matrix/jenis-approval", payload);

                ElMessage.success(data.message || "Jenis approval disimpan.");
                this.showForm = false;
                this.fetchItems();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menyimpan jenis approval.");
            } finally {
                this.saving = false;
            }
        },
        async deactivate(jenis) {
            try {
                await ElMessageBox.confirm(
                    `Nonaktifkan jenis approval "${jenis.Nama_Approval}"?`,
                    "Konfirmasi",
                    { confirmButtonText: "Nonaktifkan", cancelButtonText: "Batal", type: "warning" },
                );
            } catch {
                return;
            }

            try {
                const { data } = await axios.delete(`/api/v1/menu-approval-matrix/jenis-approval/${jenis.Id_Jenis_Approval}`);
                ElMessage.success(data.message || "Jenis approval dinonaktifkan.");
                this.fetchItems();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menonaktifkan jenis approval.");
            }
        },
    },
};
</script>

<style scoped>
.form-card {
    border: 1px solid var(--hcis-indigo-200);
    background: #f5f7ff;
    border-radius: 12px;
    padding: 1rem;
}
.form-label-sm {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-700);
    margin-bottom: 0.25rem;
    display: block;
}
.hint {
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
    margin-top: 0.25rem;
}
.jenis-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-height: 50vh;
    overflow-y: auto;
}
.jenis-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    background: #fff;
}
.pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.55rem;
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
.pill-danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-600);
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
}
.btn-action:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-500);
}
.btn-danger-soft:hover {
    border-color: var(--hcis-red-500);
    color: var(--hcis-red-500);
}
.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--hcis-slate-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}
.min-w-0 {
    min-width: 0;
}
</style>
