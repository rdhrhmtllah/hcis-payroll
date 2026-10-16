<template>
    <div class="container-fluid px-0 fade-in">
        <!-- Header -->
        <HeaderKpi
            title="Kelola Alur Persetujuan KPI"
            subtitle="Atur siapa yang akan menyetujui pengajuan KPI untuk setiap karyawan"
            :dayNight="dayNight"
            :formattedDate="formatDateString(currentDate)"
        />

        <!-- Main Content -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-10">
                <div class="approval-flow-panel">
                    <!-- Bulk Actions Section -->
                    <div
                        class="bulk-actions-section mb-4"
                        v-if="selectedEmployees.length > 0"
                    >
                        <div class="bulk-actions-card">
                            <div class="bulk-header">
                                <i class="bi bi-people-fill me-2"></i>
                                <h6 class="bulk-title">Aksi Massal</h6>
                                <span class="selected-count"
                                    >{{ selectedEmployees.length }} karyawan
                                    terpilih</span
                                >
                            </div>
                            <div class="bulk-content">
                                <div class="bulk-actions-grid">
                                    <!-- Set Approver untuk Semua -->
                                    <div class="bulk-action-item">
                                        <h6>Atur Approver untuk Semua</h6>
                                        <p>
                                            Tambahkan approver yang sama untuk
                                            semua karyawan terpilih
                                        </p>
                                        <div class="action-controls">
                                            <el-select
                                                v-model="bulkApprover"
                                                filterable
                                                remote
                                                :remote-method="searchApprover"
                                                reserve-keyword
                                                placeholder="Pilih approver..."
                                                class="w-100"
                                            >
                                                <el-option
                                                    v-for="item in approverOptions"
                                                    :key="item.hashed_id"
                                                    :label="item.Nama"
                                                    :value="item"
                                                >
                                                    <div
                                                        class="employee-option"
                                                    >
                                                        <span
                                                            class="employee-option-name"
                                                            >{{
                                                                item.Nama
                                                            }}</span
                                                        >
                                                        <span
                                                            class="employee-option-code"
                                                        >
                                                            {{
                                                                item.Kode_Karyawan
                                                            }}
                                                            •
                                                            {{
                                                                item.nama_jabatan
                                                            }}
                                                        </span>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                            <button
                                                class="btn-modern btn-primary btn-sm mt-2"
                                                @click="addBulkApprover"
                                                :disabled="!bulkApprover"
                                            >
                                                <i
                                                    class="bi bi-plus-circle me-1"
                                                ></i>
                                                Tambahkan
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Pindahkan Team -->
                                    <div class="bulk-action-item">
                                        <h6>Pindahkan Team</h6>
                                        <p>
                                            Pindahkan semua karyawan terpilih ke
                                            leader baru
                                        </p>
                                        <div class="action-controls">
                                            <el-select
                                                v-model="newLeader"
                                                filterable
                                                remote
                                                :remote-method="searchLeader"
                                                reserve-keyword
                                                placeholder="Pilih leader baru..."
                                                class="w-100"
                                            >
                                                <el-option
                                                    v-for="item in leaderOptions"
                                                    :key="item.hashed_id"
                                                    :label="item.Nama"
                                                    :value="item"
                                                >
                                                    <div
                                                        class="employee-option"
                                                    >
                                                        <span
                                                            class="employee-option-name"
                                                            >{{
                                                                item.Nama
                                                            }}</span
                                                        >
                                                        <span
                                                            class="employee-option-code"
                                                        >
                                                            {{
                                                                item.Kode_Karyawan
                                                            }}
                                                            •
                                                            {{
                                                                item.nama_jabatan
                                                            }}
                                                        </span>
                                                    </div>
                                                </el-option>
                                            </el-select>
                                            <button
                                                class="btn-modern btn-success btn-sm mt-2"
                                                @click="moveTeamToLeader"
                                                :disabled="!newLeader"
                                            >
                                                <i
                                                    class="bi bi-arrow-right-circle me-1"
                                                ></i>
                                                Pindahkan
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Hapus Selection -->
                                    <div class="bulk-action-item">
                                        <h6>Kelola Selection</h6>
                                        <p>Bersihkan atau simpan selection</p>
                                        <div class="action-controls">
                                            <button
                                                class="btn-modern btn-secondary btn-sm me-2"
                                                @click="clearSelection"
                                            >
                                                <i
                                                    class="bi bi-x-circle me-1"
                                                ></i>
                                                Bersihkan
                                            </button>
                                            <button
                                                class="btn-modern btn-info btn-sm"
                                                @click="saveSelection"
                                            >
                                                <i class="bi bi-save me-1"></i>
                                                Simpan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Selection untuk Bulk -->
                    <div class="selection-card mb-4">
                        <div class="selection-header-with-bulk">
                            <div>
                                <h6 class="selection-title">
                                    <i class="bi bi-people me-2"></i>
                                    Pilih Karyawan (Bulk Selection)
                                </h6>
                                <p class="selection-description">
                                    Pilih beberapa karyawan untuk aksi massal.
                                    Kosongkan untuk mengatur per individu.
                                </p>
                            </div>
                            <div class="bulk-toggle">
                                <label class="toggle-label">
                                    <input
                                        type="checkbox"
                                        v-model="bulkMode"
                                        @change="toggleBulkMode"
                                    />
                                    <span class="toggle-slider"></span>
                                    Mode Massal
                                </label>
                            </div>
                        </div>

                        <el-select
                            v-model="selectedEmployees"
                            filterable
                            remote
                            multiple
                            collapse-tags
                            :remote-method="searchRequester"
                            reserve-keyword
                            placeholder="Pilih karyawan untuk aksi massal..."
                            :loading="loading.requester"
                            class="w-100 custom-select"
                            value-key="hashed_id"
                        >
                            <el-option
                                v-for="item in requesterOptions"
                                :key="item.hashed_id"
                                :label="item.Nama"
                                :value="item"
                            >
                                <div class="employee-option">
                                    <span class="employee-option-name">{{
                                        item.Nama
                                    }}</span>
                                    <span class="employee-option-code">
                                        {{ item.Kode_Karyawan }} •
                                        {{ item.nama_jabatan }}
                                    </span>
                                </div>
                            </el-option>
                        </el-select>

                        <!-- Selected Employees Chips -->
                        <div
                            v-if="selectedEmployees.length > 0"
                            class="selected-chips mt-3"
                        >
                            <div class="chips-header">
                                <span>Karyawan Terpilih:</span>
                                <small
                                    >{{ selectedEmployees.length }} orang</small
                                >
                            </div>
                            <div class="chips-container">
                                <span
                                    v-for="employee in selectedEmployees"
                                    :key="employee.hashed_id"
                                    class="employee-chip"
                                >
                                    {{ employee.Nama }}
                                    <i
                                        class="bi bi-x"
                                        @click="removeFromSelection(employee)"
                                    ></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Pilih Karyawan (Individual) -->
                    <div class="selection-card mb-4" v-if="!bulkMode">
                        <h6 class="selection-title">
                            <i class="bi bi-person-check-fill me-2"></i>
                            Langkah 1: Pilih Karyawan (Requester)
                        </h6>
                        <p class="selection-description">
                            Pilih karyawan yang alur persetujuannya ingin Anda
                            atur.
                        </p>
                        <el-select
                            v-model="selectedRequester"
                            filterable
                            remote
                            :remote-method="searchRequester"
                            reserve-keyword
                            placeholder="Ketik nama atau kode karyawan..."
                            :loading="loading.requester"
                            class="w-100 custom-select"
                            @change="handleRequesterChange"
                            value-key="hashed_id"
                        >
                            <el-option
                                v-for="item in requesterOptions"
                                :key="item.hashed_id"
                                :label="item.Nama"
                                :value="item"
                            >
                                <div class="employee-option">
                                    <span class="employee-option-name">{{
                                        item.Nama
                                    }}</span>
                                    <span class="employee-option-code">
                                        {{ item.Kode_Karyawan }} •
                                        {{ item.nama_jabatan }}
                                    </span>
                                </div>
                            </el-option>
                        </el-select>
                    </div>

                    <!-- Step 2: Atur Approver -->
                    <div
                        class="selection-card"
                        :class="{
                            'disabled-card': !selectedRequester && !bulkMode,
                        }"
                    >
                        <h6 class="selection-title">
                            <i class="bi bi-diagram-3-fill me-2"></i>
                            Langkah 2: Atur Alur Persetujuan (Approver)
                        </h6>
                        <p class="selection-description">
                            <span
                                v-if="bulkMode && selectedEmployees.length > 0"
                            >
                                Tambah, hapus, dan urutkan approver untuk
                                <strong
                                    >{{ selectedEmployees.length }} karyawan
                                    terpilih</strong
                                >.
                            </span>
                            <span v-else-if="selectedRequester">
                                Tambah, hapus, dan urutkan approver untuk
                                <strong>{{ selectedRequester.Nama }}</strong
                                >.
                            </span>
                            <span v-else>
                                Pilih karyawan terlebih dahulu untuk mengatur
                                alur persetujuan.
                            </span>
                            Urutan menentukan alur persetujuan.
                        </p>

                        <div v-if="loading.flow" class="loading-state-compact">
                            <div
                                class="spinner-border spinner-border-sm"
                                role="status"
                            ></div>
                            <span>Memuat alur persetujuan...</span>
                        </div>

                        <div v-else>
                            <!-- Daftar Approver -->
                            <div
                                v-if="approverList.length > 0"
                                class="approver-list"
                            >
                                <draggable
                                    v-model="approverList"
                                    item-key="hashed_id"
                                    handle=".drag-handle"
                                    ghost-class="ghost"
                                >
                                    <template #item="{ element, index }">
                                        <div class="approver-item">
                                            <div class="approver-info">
                                                <i
                                                    class="bi bi-grip-vertical drag-handle"
                                                ></i>
                                                <span class="approver-order">{{
                                                    index + 1
                                                }}</span>
                                                <div class="avatar-sm">
                                                    {{
                                                        getInitials(
                                                            element.Nama_Approver
                                                        )
                                                    }}
                                                </div>
                                                <div class="approver-details">
                                                    <h6 class="approver-name">
                                                        {{
                                                            element.Nama_Approver
                                                        }}
                                                    </h6>
                                                    <p class="approver-meta">
                                                        {{
                                                            element.Jabatan_Approver
                                                        }}
                                                        •
                                                        {{
                                                            element.Divisi_Approver
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                            <button
                                                class="btn-action btn-remove"
                                                @click="removeApprover(index)"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </template>
                                </draggable>
                            </div>

                            <!-- State Kosong -->
                            <div v-else class="empty-state-compact">
                                <i class="bi bi-person-x"></i>
                                <p>Belum ada approver yang ditambahkan.</p>
                            </div>

                            <!-- Tambah Approver -->
                            <div class="add-approver-section mt-3">
                                <el-select
                                    v-model="newApprover"
                                    filterable
                                    remote
                                    :remote-method="searchApprover"
                                    reserve-keyword
                                    placeholder="Cari untuk menambah approver..."
                                    :loading="loading.approver"
                                    class="w-100 custom-select"
                                    @change="addApprover"
                                    value-key="hashed_id"
                                >
                                    <el-option
                                        v-for="item in approverOptions"
                                        :key="item.hashed_id"
                                        :label="item.Nama"
                                        :value="item"
                                    >
                                        <div class="employee-option">
                                            <span
                                                class="employee-option-name"
                                                >{{ item.Nama }}</span
                                            >
                                            <span class="employee-option-code">
                                                {{ item.Kode_Karyawan }} •
                                                {{ item.nama_jabatan }}
                                            </span>
                                        </div>
                                    </el-option>
                                </el-select>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="action-footer mt-4">
                        <div
                            class="action-info"
                            v-if="bulkMode && selectedEmployees.length > 0"
                        >
                            <i class="bi bi-info-circle me-2"></i>
                            <span
                                >Akan diterapkan ke
                                {{ selectedEmployees.length }} karyawan</span
                            >
                        </div>
                        <button
                            class="btn-modern btn-primary"
                            @click="saveFlow"
                            :disabled="!canSave || loading.saving"
                        >
                            <i
                                v-if="!loading.saving"
                                class="bi bi-save me-2"
                            ></i>
                            <div
                                v-else
                                class="spinner-border spinner-border-sm me-2"
                                role="status"
                            ></div>
                            {{
                                bulkMode
                                    ? "Simpan untuk Semua"
                                    : "Simpan Perubahan"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElSelect, ElOption, ElNotification, ElMessageBox } from "element-plus";
import draggable from "vuedraggable";
import axios from "axios";
import HeaderKpi from "@/components/ui/header.vue";

export default {
    name: "ApprovalKPIFlow",
    components: {
        HeaderKpi,
        ElSelect,
        ElOption,
        draggable,
    },
    data() {
        return {
            currentDate: new Date(),
            loading: {
                requester: false,
                approver: false,
                flow: false,
                saving: false,
                bulk: false,
            },
            selectedRequester: null,
            requesterOptions: [],
            approverList: [],
            newApprover: null,
            approverOptions: [],
            searchTimeout: null,

            // New properties for bulk features
            bulkMode: false,
            selectedEmployees: [],
            bulkApprover: null,
            newLeader: null,
            leaderOptions: [],
            savedSelections: [],
        };
    },
    computed: {
        canSave() {
            if (this.bulkMode) {
                return (
                    this.selectedEmployees.length > 0 &&
                    this.approverList.length > 0
                );
            }
            return this.selectedRequester && this.approverList.length > 0;
        },
    },
    methods: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },

        formatDateString(date) {
            if (!date) return "";
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
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

        // Search Methods
        async searchRequester(query) {
            if (query && query.length >= 2) {
                this.loading.requester = true;
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-employees?q=${query}`
                    );
                    this.requesterOptions = response.data.result;
                } catch (error) {
                    console.error("Error searching requester:", error);
                    this.requesterOptions = [];
                } finally {
                    this.loading.requester = false;
                }
            } else {
                this.requesterOptions = [];
            }
        },

        async searchApprover(query) {
            if (query && query.length >= 2) {
                this.loading.approver = true;
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-employees?q=${query}`
                    );
                    this.approverOptions = response.data.result;
                } catch (error) {
                    console.error("Error searching approver:", error);
                    this.approverOptions = [];
                } finally {
                    this.loading.approver = false;
                }
            } else {
                this.approverOptions = [];
            }
        },

        async searchLeader(query) {
            if (query && query.length >= 2) {
                try {
                    const response = await axios.get(
                        `/api/v1/approval-kpi-admin/search-leaders?q=${query}`
                    );
                    this.leaderOptions = response.data.result;
                } catch (error) {
                    console.error("Error searching leader:", error);
                    this.leaderOptions = [];
                }
            } else {
                this.leaderOptions = [];
            }
        },

        // Individual Flow Methods
        async handleRequesterChange() {
            if (this.selectedRequester) {
                await this.loadApprovalFlow();
            } else {
                this.approverList = [];
            }
        },

        async loadApprovalFlow() {
            if (!this.selectedRequester) return;

            this.loading.flow = true;
            try {
                const response = await axios.get(
                    `/api/v1/approval-kpi-admin/flow/${this.selectedRequester.hashed_id}`
                );
                this.approverList = response.data.result || [];
            } catch (error) {
                console.error("Error loading approval flow:", error);
                this.approverList = [];
            } finally {
                this.loading.flow = false;
            }
        },

        addApprover(approver) {
            if (!approver) return;

            const isDuplicate = this.approverList.some(
                (item) => item.hashed_id === approver.hashed_id
            );

            if (isDuplicate) {
                ElNotification({
                    title: "Peringatan",
                    message: "Approver sudah ditambahkan",
                    type: "warning",
                });
                return;
            }

            const newApprover = {
                hashed_id: approver.hashed_id,
                Nama_Approver: approver.Nama,
                Jabatan_Approver: approver.nama_jabatan,
                Divisi_Approver: approver.nama_divisi || "-",
            };

            this.approverList.push(newApprover);
            this.newApprover = null;
            this.approverOptions = [];
        },

        removeApprover(index) {
            this.approverList.splice(index, 1);
        },

        // Bulk Operations Methods
        toggleBulkMode() {
            if (!this.bulkMode) {
                this.selectedEmployees = [];
                this.selectedRequester = null;
                this.approverList = [];
            }
        },

        removeFromSelection(employee) {
            this.selectedEmployees = this.selectedEmployees.filter(
                (emp) => emp.hashed_id !== employee.hashed_id
            );
        },

        async addBulkApprover() {
            if (!this.bulkApprover) return;

            const isDuplicate = this.approverList.some(
                (item) => item.hashed_id === this.bulkApprover.hashed_id
            );

            if (isDuplicate) {
                ElNotification({
                    title: "Peringatan",
                    message: "Approver sudah ditambahkan",
                    type: "warning",
                });
                return;
            }

            const newApprover = {
                hashed_id: this.bulkApprover.hashed_id,
                Nama_Approver: this.bulkApprover.Nama,
                Jabatan_Approver: this.bulkApprover.nama_jabatan,
                Divisi_Approver: this.bulkApprover.nama_divisi || "-",
            };

            this.approverList.push(newApprover);
            this.bulkApprover = null;
        },

        async moveTeamToLeader() {
            if (!this.newLeader || this.selectedEmployees.length === 0) return;

            try {
                await ElMessageBox.confirm(
                    `Anda akan memindahkan ${this.selectedEmployees.length} karyawan ke ${this.newLeader.Nama}?`,
                    "Konfirmasi Pindah Team",
                    {
                        confirmButtonText: "Ya, Pindahkan",
                        cancelButtonText: "Batal",
                        type: "warning",
                    }
                );

                this.loading.bulk = true;

                const payload = {
                    new_leader_id: this.newLeader.hashed_id,
                    employee_ids: this.selectedEmployees.map(
                        (emp) => emp.hashed_id
                    ),
                };

                const response = await axios.post(
                    "/api/v1/approval-kpi-admin/move-team",
                    payload
                );

                ElNotification({
                    title: "Sukses",
                    message: `Berhasil memindahkan ${this.selectedEmployees.length} karyawan ke ${this.newLeader.Nama}`,
                    type: "success",
                });

                this.selectedEmployees = [];
                this.newLeader = null;
            } catch (error) {
                if (error !== "cancel") {
                    console.error("Error moving team:", error);
                    ElNotification({
                        title: "Error",
                        message: "Gagal memindahkan team",
                        type: "error",
                    });
                }
            } finally {
                this.loading.bulk = false;
            }
        },

        clearSelection() {
            this.selectedEmployees = [];
        },

        saveSelection() {
            if (this.selectedEmployees.length === 0) return;

            this.savedSelections.push({
                id: Date.now(),
                employees: [...this.selectedEmployees],
                timestamp: new Date(),
            });

            ElNotification({
                title: "Sukses",
                message: `Selection ${this.selectedEmployees.length} karyawan disimpan`,
                type: "success",
            });
        },

        // Save Flow (Individual & Bulk)
        async saveFlow() {
            if (!this.canSave) return;

            this.loading.saving = true;
            try {
                if (this.bulkMode) {
                    // Bulk save for multiple employees
                    const payload = {
                        employee_ids: this.selectedEmployees.map(
                            (emp) => emp.hashed_id
                        ),
                        approvers: this.approverList.map((approver, index) => ({
                            approver_id: approver.hashed_id,
                            order: index + 1,
                        })),
                    };

                    await axios.post(
                        "/api/v1/approval-kpi-admin/bulk-save",
                        payload
                    );

                    ElNotification({
                        title: "Sukses",
                        message: `Alur persetujuan berhasil disimpan untuk ${this.selectedEmployees.length} karyawan`,
                        type: "success",
                    });

                    this.selectedEmployees = [];
                    this.bulkMode = false;
                } else {
                    // Individual save
                    const payload = {
                        requester_id: this.selectedRequester.hashed_id,
                        approvers: this.approverList.map((approver, index) => ({
                            approver_id: approver.hashed_id,
                            order: index + 1,
                        })),
                    };

                    await axios.post(
                        "/api/v1/approval-kpi-admin/save",
                        payload
                    );

                    ElNotification({
                        title: "Sukses",
                        message: "Alur persetujuan berhasil disimpan",
                        type: "success",
                    });
                }
            } catch (error) {
                console.error("Error saving approval flow:", error);
                ElNotification({
                    title: "Error",
                    message: "Gagal menyimpan alur persetujuan",
                    type: "error",
                });
            } finally {
                this.loading.saving = false;
            }
        },

        showSimpleAlert(message, type = "warning") {
            this.$message({
                type: type,
                message: message,
            });
        },
    },
    async mounted() {
        // Load saved selections from localStorage if needed
        const saved = localStorage.getItem("savedSelections");
        if (saved) {
            this.savedSelections = JSON.parse(saved);
        }
    },
    beforeDestroy() {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
    },
};
</script>

<style scoped>
/* Enhanced CSS Variables */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --border-radius: 12px;
}

/* Bulk Actions Section */
.bulk-actions-section {
    animation: slideIn 0.3s ease;
}

.bulk-actions-card {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px solid var(--primary-light);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.bulk-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.bulk-title {
    color: var(--primary);
    font-weight: 600;
    margin: 0;
    flex: 1;
}

.selected-count {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.bulk-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.bulk-action-item {
    background: var(--surface);
    padding: 1.25rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.bulk-action-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.bulk-action-item h6 {
    color: var(--dark);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.bulk-action-item p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.action-controls {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Selection Header with Bulk Toggle */
.selection-header-with-bulk {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.bulk-toggle {
    display: flex;
    align-items: center;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 500;
    color: var(--text);
    font-size: 0.9rem;
}

.toggle-label input {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 24px;
    background: var(--border);
    border-radius: 12px;
    position: relative;
    transition: all 0.3s ease;
}

.toggle-slider::before {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: all 0.3s ease;
}

.toggle-label input:checked + .toggle-slider {
    background: var(--success);
}

.toggle-label input:checked + .toggle-slider::before {
    transform: translateX(20px);
}

/* Selected Chips */
.selected-chips {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.chips-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-muted);
}

.chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.employee-chip {
    display: inline-flex;
    align-items: center;
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.4rem 0.8rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.employee-chip:hover {
    background: var(--primary);
    color: white;
}

.employee-chip i {
    margin-left: 0.5rem;
    cursor: pointer;
    font-size: 0.8rem;
    opacity: 0.7;
}

.employee-chip i:hover {
    opacity: 1;
}

/* Action Info */
.action-info {
    display: flex;
    align-items: center;
    background: var(--info);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-right: 1rem;
}

/* Enhanced Responsive Design */
@media (max-width: 768px) {
    .bulk-actions-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .selection-header-with-bulk {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .bulk-toggle {
        justify-content: flex-end;
    }

    .action-footer {
        flex-direction: column;
        gap: 1rem;
    }

    .action-info {
        margin-right: 0;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .bulk-actions-card {
        padding: 1rem;
    }

    .bulk-action-item {
        padding: 1rem;
    }

    .chips-container {
        max-height: 120px;
        overflow-y: auto;
    }
}

/* Animation for new elements */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Button Styles */
.btn-success {
    background: var(--success);
}

.btn-success:hover:not(:disabled) {
    background: #059669;
}

.btn-info {
    background: var(--info);
}

.btn-info:hover:not(:disabled) {
    background: #0891b2;
}

.btn-secondary {
    background: var(--secondary);
}

.btn-secondary:hover:not(:disabled) {
    background: #475569;
}

/* Existing styles remain the same, enhanced with new features */
/* ... (previous CSS styles) ... */

/* Dark mode support for new elements */
@media (prefers-color-scheme: dark) {
    .bulk-actions-card {
        background: linear-gradient(135deg, #334155 0%, #475569 100%);
    }

    .bulk-action-item {
        background: var(--surface);
    }

    .employee-chip {
        background: var(--primary-dark);
        color: var(--primary-light);
    }
}

/* High contrast support */
@media (prefers-contrast: high) {
    .bulk-actions-card {
        border-width: 3px;
    }

    .employee-chip {
        border: 1px solid var(--primary-dark);
    }
}
</style>
<style scoped>
/* CSS Variables */
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --border-radius: 12px;
}

/* Animations */
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
    animation: fadeIn 0.5s ease-out;
}

/* Main Panel */
.approval-flow-panel {
    background: var(--surface);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

/* Selection Cards */
.selection-card {
    background: var(--surface-soft);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.selection-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.selection-card.disabled-card {
    opacity: 0.6;
    pointer-events: none;
}

.selection-title {
    color: var(--dark);
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    font-size: 1rem;
}

.selection-title i {
    color: var(--primary);
}

.selection-description {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 1rem;
    line-height: 1.5;
}

/* Custom Select Styles */
:deep(.custom-select .el-select__wrapper) {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
    box-shadow: none !important;
}

:deep(.custom-select .el-select__wrapper:hover) {
    border-color: var(--primary-light);
}

:deep(.custom-select .el-select__wrapper.is-focus) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

:deep(.custom-select .el-input__inner) {
    color: var(--text);
    font-size: 0.9rem;
}

/* Employee Option */
.employee-option {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 0;
}

.employee-option-name {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.employee-option-code {
    font-size: 0.8rem;
    color: var(--text-muted);
}

/* Loading State */
.loading-state-compact {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Approver List */
.approver-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.approver-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    transition: all 0.3s ease;
    animation: slideIn 0.3s ease;
}

.approver-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
    transform: translateY(-1px);
}

.approver-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.drag-handle {
    color: var(--text-muted);
    cursor: grab;
    font-size: 1.1rem;
    transition: color 0.2s ease;
}

.drag-handle:hover {
    color: var(--primary);
}

.approver-order {
    background: var(--primary);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.approver-details {
    flex: 1;
    min-width: 0;
}

.approver-name {
    color: var(--dark);
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
}

.approver-meta {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.4;
}

/* Action Buttons */
.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.btn-remove {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-compact {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-state-compact i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-compact p {
    margin: 0;
    font-size: 0.9rem;
}

/* Add Approver Section */
.add-approver-section {
    border-top: 1px solid var(--border);
    padding-top: 1.5rem;
}

/* Action Footer */
.action-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
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

.btn-modern:disabled::before {
    display: none;
}

.btn-primary {
    background-color: var(--primary);
}

.btn-primary:hover:not(:disabled) {
    background-color: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Draggable Ghost Class */
.ghost {
    opacity: 0.5;
    background: var(--primary-light);
    border-radius: 10px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .approval-flow-panel {
        padding: 1.5rem;
        margin: 0 -1rem;
        border-radius: 0;
    }

    .selection-card {
        padding: 1.25rem;
    }

    .approver-item {
        padding: 0.875rem;
    }

    .approver-info {
        gap: 0.75rem;
    }

    .avatar-sm {
        width: 36px;
        height: 36px;
        font-size: 0.75rem;
    }

    .approver-name {
        font-size: 0.9rem;
    }

    .approver-meta {
        font-size: 0.75rem;
    }

    .action-footer {
        justify-content: center;
    }

    .btn-modern {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .approval-flow-panel {
        padding: 1rem;
    }

    .selection-card {
        padding: 1rem;
    }

    .selection-title {
        font-size: 0.9rem;
    }

    .selection-description {
        font-size: 0.85rem;
    }

    .approver-info {
        gap: 0.5rem;
    }

    .approver-order {
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.7rem;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }

    .empty-state-compact {
        padding: 2rem 1rem;
    }

    .empty-state-compact i {
        font-size: 2.5rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --surface: #1e293b;
        --surface-soft: #334155;
        --dark: #f8fafc;
        --text: #e2e8f0;
        --text-muted: #94a3b8;
        --border: #475569;
        --light: #0f172a;
    }
}

/* High contrast support */
@media (prefers-contrast: high) {
    :root {
        --primary: #0000ff;
        --primary-dark: #0000cc;
        --border: #000000;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }

    .btn-modern::before {
        display: none;
    }
}
</style>

<style scoped>
/* Menggunakan variabel CSS dari file lain untuk konsistensi */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

.tab-content-improved {
    padding: 1.5rem;
    background-color: var(--surface-soft);
    border-radius: var(--radius);
}

/* Filter & Search Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.search-box {
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Employee List Table */
.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    background-color: var(--surface-soft);
    font-weight: 600;
    color: var(--text-muted);
}

.modern-table tr:hover {
    background-color: var(--surface-soft);
}

.employee-info-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.employee-details .employee-name {
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.employee-details .employee-code {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin: 0;
}

.badge-access-count {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Manage Access Tab */
.manage-access-container {
    background: var(--surface);
    padding: 2rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.selection-card {
    background: var(--surface-soft);
    padding: 1.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    height: 100%;
    transition: all 0.3s ease;
}

.selection-card.disabled-card {
    opacity: 0.5;
    pointer-events: none;
    background: #d6d6d6;
}

.selection-title {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.selection-description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.all-employees-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2rem 1rem;
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    background-color: var(--surface);
    color: var(--text-muted);
}

.all-employees-info i {
    font-size: 2.5rem;
    color: var(--success);
    margin-bottom: 1rem;
}

.all-employees-info p {
    margin: 0;
    font-size: 0.9rem;
}

.selected-items-summary {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.btn-clear-selection {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-weight: 600;
}

.selected-chips-container {
    max-height: 150px;
    overflow-y: auto;
}
.form-group {
    margin-bottom: 1rem;
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.page-browser {
    background-color: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
}

.search-input-sm {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text);
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.search-input-sm:focus {
    outline: none;
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.page-groups-container {
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.page-group {
    margin-bottom: 1rem;
}

.page-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background-color: var(--surface-soft);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.module-title {
    font-weight: 600;
    color: var(--text);
    margin: 0;
    font-size: 1rem;
}

.page-group-header .form-check {
    margin: 0;
}

.page-group-body {
    padding: 0 0.75rem;
}

.page-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border);
}

.page-item:last-child {
    margin-left: 0;
    border-bottom: none;
}

.page-item .form-check-label {
    font-size: 0.9rem;
    color: var(--text-muted);
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.8rem;
    border-radius: 16px;
    font-size: 0.85rem;
    font-weight: 500;
    margin: 0.25rem;
}

.chip i {
    margin-left: 0.5rem;
    cursor: pointer;
    opacity: 0.7;
}
.chip i:hover {
    opacity: 1;
}
.action-footer {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-summary {
    color: var(--text-muted);
    margin: 0;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Buttons */
.btn-action {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-view:hover {
    background: var(--border);
}

.btn-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-success {
    background: var(--success);
    color: white;
}
.btn-success:hover:not(:disabled) {
    background: #059669;
}

.btn-danger {
    background: var(--danger);
    color: white;
}
.btn-danger:hover:not(:disabled) {
    background: #dc2626;
}

.btn-secondary {
    background: var(--secondary);
    color: white;
}
.btn-secondary:hover:not(:disabled) {
    background: #475569;
}

/* Modal Detail */
.detail-employee-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.avatar-md {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.5rem;
}

.detail-access-list h6 {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 1rem;
}

.access-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.access-tag {
    display: inline-flex;
    align-items: center;
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.empty-state-modal {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.empty-state-modal i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

/* General States */
.loading-state,
.empty-state-admin {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
    text-align: center;
}

.loading-spinner .bi-arrow-repeat {
    font-size: 2.5rem;
    color: var(--primary);
    animation: spin 1.5s linear infinite;
}

.empty-state-admin i {
    font-size: 4rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    cursor: pointer;
    transition: all 0.2s ease;
}
.page-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    margin: 0 1rem;
    font-size: 0.9rem;
    color: var(--text-muted);
}

@media (max-width: 991px) {
    .action-footer {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    .action-buttons {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .tab-content-improved {
        padding: 1rem;
    }
    .manage-access-container {
        padding: 1.5rem;
    }
    .modern-table {
        display: block;
        overflow-x: auto;
    }
}
</style>
