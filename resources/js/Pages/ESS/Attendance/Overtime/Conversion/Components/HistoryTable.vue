<template>
    <div class="">
        <ModernDataTable
            :data="filteredHistory"
            :columns="tableColumns"
            :mobile-columns="mobileColumns"
            :loading="isLoading"
            :fit-to-container="true"
            :clickable="true"
            :show-pagination="false"
            :show-actions="true"
            :show-delete="false"
            :current-page="1"
            :total-pages="1"
            :total-data="filteredHistory.length"
            :full-pagination="[]"
            card-title-key="No_Transaksi"
            card-subtitle-key="Jenis_Konversi"
            card-status-key="Status"
            empty-message="Riwayat konversi lembur belum tersedia"
            empty-action-label="Refresh"
            @row-click="openDetail"
            @empty-action="fetchHistory"
        >
            <template #column-No_Transaksi="{ item }">
                <div class="history-transaction-cell">
                    <div class="history-transaction-avatar">
                        {{ getTransactionInitial(item.No_Transaksi) }}
                    </div>
                    <div>
                        <div class="fw-bold text-dark text-sm">
                            {{ item.No_Transaksi }}
                        </div>
                        <div class="text-muted text-xs">
                            {{ getTypeLabel(item.Jenis_Konversi) }}
                        </div>
                    </div>
                </div>
            </template>

            <template #column-Tanggal_Lembur="{ item }">
                <div class="d-flex flex-column">
                    <span class="fw-semibold text-dark">
                        {{ formatDate(item.Tanggal_Lembur) }}
                    </span>
                    <small class="text-muted">
                        Dibuat {{ formatDate(item.Created_At) }}
                    </small>
                </div>
            </template>

            <template #column-Target_Off="{ item }">
                <span
                    v-if="item.Jenis_Konversi === 'DAY_OFF'"
                    class="badge bg-info-subtle text-info border border-info-subtle"
                >
                    {{ formatDate(item.Target_Off) }}
                </span>
                <span v-else class="text-muted text-xs">{{ getTargetLabel(item.Jenis_Konversi) }}</span>
            </template>

            <template #column-Durasi_Jam="{ item }">
                <span class="badge bg-primary-subtle text-primary">
                    {{ formatDuration(item.Durasi_Jam) }}
                </span>
            </template>

            <template #column-Status="{ item }">
                <span :class="getStatusClass(item.Status)">
                    <i :class="getStatusIcon(item.Status)"></i>
                    {{ getStatusLabel(item.Status) }}
                </span>
            </template>

            <template #mobile-Tanggal_Lembur="{ item }">
                {{ formatDate(item.Tanggal_Lembur) }}
            </template>

            <template #mobile-Target_Off="{ item }">
                {{ item.Jenis_Konversi === "DAY_OFF" ? formatDate(item.Target_Off) : getTargetLabel(item.Jenis_Konversi) }}
            </template>

            <template #mobile-Status="{ item }">
                {{ getStatusLabel(item.Status) }}
            </template>

            <!-- Custom slot for Actions (Desktop) -->
            <template #column-actions="{ item }">
                <div
                    class="d-flex align-items-center gap-2 justify-content-center"
                >
                    <button
                        class="stitch-action-btn text-primary p-2 rounded-lg"
                        @click.stop="openDetail(item)"
                        style="
                            background: var(--surface-soft);
                            border: 1px solid var(--border);
                        "
                        title="Lihat Detail"
                    >
                        <i class="bi bi-eye"></i>
                    </button>
                    <button
                        v-if="canCancelItem(item)"
                        class="stitch-action-btn text-danger p-2 rounded-lg"
                        @click.stop="cancelAlertButton(item)"
                        :disabled="!canCancelItem(item)"
                        style="
                            background: var(--surface-soft);
                            border: 1px solid var(--border);
                        "
                        title="Batalkan Pengajuan"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </template>

            <!-- Custom actions for Card View (Mobile) -->
            <template #card-actions="{ item }">
                <div class="d-flex gap-2">
                    <button
                        class="stitch-action-btn flex-grow-1 text-primary py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                        style="
                            background: var(--surface-soft);
                            border: 1px solid var(--border);
                        "
                        @click.stop="openDetail(item)"
                    >
                        <i class="bi bi-eye"></i>
                    </button>
                    <button
                        v-if="canCancelItem(item)"
                        class="stitch-action-btn flex-grow-1 text-danger py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                        style="
                            background: var(--surface-soft);
                            border: 1px solid var(--border);
                        "
                        @click.stop="cancelAlertButton(item)"
                        :disabled="!canCancelItem(item)"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </template>
        </ModernDataTable>

        <!-- History Detail Modal -->
        <Modal
            :show="!!selectedHistory"
            title="Detail Riwayat Konversi"
            size="large"
            @close="closeDetail"
        >
            <template #body>
                <div
                    v-if="selectedHistory"
                    class="detail-modal-content fade-in"
                >
                    <div class="detail-modal-grid">
                        <div class="detail-left-column">
                            <div class="detail-transaksi-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="detail-transaksi-avatar">
                                        {{
                                            getTransactionInitial(
                                                selectedHistory.No_Transaksi,
                                            )
                                        }}
                                    </div>
                                    <div class="detail-transaksi-type">
                                        <div class="text-white-50 text-xs mb-1">
                                            No Transaksi
                                        </div>
                                        <div class="detail-transaksi-number">
                                            {{ selectedHistory.No_Transaksi }}
                                        </div>
                                        <span class="text-white-50 text-xs">
                                            Dibuat
                                            {{
                                                formatDate(
                                                    selectedHistory.Created_At,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-status-section">
                                <div class="detail-status-label">
                                    STATUS TRANSAKSI
                                </div>
                                <div
                                    class="detail-status-badge"
                                    :class="
                                        getHistoryStatusPillClass(
                                            getStatusLabel(
                                                selectedHistory.Status,
                                            ),
                                        )
                                    "
                                >
                                    {{ getStatusLabel(selectedHistory.Status) }}
                                </div>
                                <div class="detail-status-date">
                                    <i class="bi bi-calendar3"></i>
                                    Diajukan
                                    {{ formatDate(selectedHistory.Created_At) }}
                                </div>
                            </div>

                            <div class="detail-status-section">
                                <div class="detail-status-label">
                                    TIPE PENGAJUAN
                                </div>
                                <div class="detail-type-badge">
                                    <i class="bi bi-clock-history"></i>
                                    KONVERSI
                                    {{
                                        String(
                                            getTypeLabel(
                                                selectedHistory.Jenis_Konversi,
                                            ),
                                        ).toUpperCase()
                                    }}
                                </div>
                            </div>

                            <div class="detail-approval-section">
                                <div class="detail-section-title">
                                    <i class="bi bi-diagram-3"></i>
                                    Alur Persetujuan
                                </div>
                                <div class="detail-approval-list">
                                    <div
                                        v-for="(
                                            step, idx
                                        ) in selectedHistory.approval_steps"
                                        :key="idx"
                                        class="detail-approval-item"
                                    >
                                        <div
                                            class="detail-approval-indicator"
                                            :class="
                                                step.status === 'approved'
                                                    ? 'approved'
                                                    : step.status === 'rejected'
                                                      ? 'rejected'
                                                      : 'pending'
                                            "
                                        >
                                            <i
                                                v-if="
                                                    step.status === 'approved'
                                                "
                                                class="bi bi-check-lg"
                                            ></i>
                                            <i
                                                v-else-if="
                                                    step.status === 'rejected'
                                                "
                                                class="bi bi-x-lg"
                                            ></i>
                                            <i v-else class="bi bi-clock"></i>
                                        </div>
                                        <div class="detail-approval-info">
                                            <div
                                                class="detail-approval-name"
                                                :class="{
                                                    'text-success':
                                                        step.status ===
                                                        'approved',
                                                    'text-danger':
                                                        step.status ===
                                                        'rejected',
                                                }"
                                            >
                                                {{ step.actor || "-" }}
                                            </div>
                                            <div class="detail-approval-date">
                                                {{
                                                    step.status === "approved"
                                                        ? "Disetujui"
                                                        : step.status ===
                                                            "rejected"
                                                          ? "Ditolak"
                                                          : step.status ===
                                                              "pending"
                                                            ? "Menunggu review"
                                                            : "Belum aktif"
                                                }}
                                                <span v-if="step.approved_at">
                                                    - {{ step.approved_at }}
                                                </span>
                                            </div>
                                            <div
                                                v-if="step.note"
                                                class="detail-approval-note"
                                            >
                                                <i
                                                    class="bi bi-chat-left-text"
                                                ></i>
                                                {{ step.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            !selectedHistory.approval_steps ||
                                            selectedHistory.approval_steps
                                                .length === 0
                                        "
                                        class="detail-approval-empty"
                                    >
                                        <i class="bi bi-check-circle-fill"></i>
                                        Auto Approved
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-right-column">
                            <div class="detail-info-section">
                                <div
                                    class="detail-section-title mb-3 text-uppercase text-xs fw-bold text-muted"
                                >
                                    <i class="bi bi-clock-history me-2"></i>
                                    Detail Konversi Lembur
                                </div>

                                <div class="detail-history-card">
                                    <div class="detail-info-grid">
                                        <div class="detail-info-item">
                                            <small>Tanggal Lembur</small>
                                            <span class="detail-shift-date">
                                                {{
                                                    formatDate(
                                                        selectedHistory.Tanggal_Lembur,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div class="detail-info-item">
                                            <small>Jenis Konversi</small>
                                            <span>
                                                {{
                                                    getTypeLabel(
                                                        selectedHistory.Jenis_Konversi,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div class="detail-info-item">
                                            <small>Durasi Lembur</small>
                                            <span class="text-primary">
                                                {{
                                                    formatDuration(
                                                        selectedHistory.Durasi_Jam,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="
                                                selectedHistory.Jenis_Konversi ===
                                                'DAY_OFF'
                                            "
                                            class="detail-info-item"
                                        >
                                            <small>Target Konversi</small>
                                            <span>
                                                {{
                                                    formatDate(
                                                        selectedHistory.Target_Off,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-else-if="
                                                selectedHistory.Jenis_Konversi ===
                                                'TO_CUTI'
                                            "
                                            class="detail-info-item"
                                        >
                                            <small>Target Konversi</small>
                                            <span>Bayar hutang cuti</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="selectedHistory.Note"
                                class="detail-info-section"
                            >
                                <div class="detail-section-title">
                                    <i class="bi bi-chat-left-text"></i>
                                    Catatan / Alasan
                                </div>
                                <div class="detail-note-card">
                                    {{ selectedHistory.Note }}
                                </div>
                            </div>

                            <div
                                v-if="selectedHistory.Lampiran"
                                class="detail-info-section"
                            >
                                <div class="detail-section-title">
                                    <i class="bi bi-paperclip"></i>
                                    Lampiran Pendukung
                                </div>
                                <div class="detail-attachment-card">
                                    <a
                                        :href="selectedHistory.Lampiran.startsWith('http') ? selectedHistory.Lampiran : '/storage/' + selectedHistory.Lampiran"
                                        target="_blank"
                                        class="stitch-btn primary w-100 d-flex align-items-center justify-content-center"
                                    >
                                        <i class="bi bi-download me-2"></i>
                                        Download File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="w-100 d-flex justify-content-end gap-2">
                    <button
                        v-if="canCancel"
                        type="button"
                        class="btn-modern btn-danger"
                        :disabled="isCanceling"
                        @click="cancelAlertButton(selectedHistory)"
                    >
                        <i
                            v-if="isCanceling"
                            class="bi bi-arrow-repeat spin me-2"
                        ></i>
                        <i v-else class="bi bi-trash me-2"></i>
                        Batalkan Pengajuan
                    </button>
                    <button
                        type="button"
                        class="stitch-btn secondary"
                        @click="closeDetail"
                    >
                        Tutup
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Alert Modal Konfirmasi Batal -->
        <Modal
            v-if="showAlertModal"
            :show="showAlertModal"
            :title="titleAlertModal"
            size="medium"
            @close="closeAlertModal"
        >
            <template #body>
                <div class="confirmation-content text-center py-4">
                    <i
                        class="bi bi-exclamation-triangle fs-1 text-warning mb-3"
                    ></i>
                    <p class="mb-0">{{ bodyAlert }}</p>
                </div>
            </template>

            <template #footer>
                <div class="d-flex w-100 justify-content-end gap-2">
                    <button
                        class="stitch-btn secondary"
                        @click="closeAlertModal"
                        :disabled="isCanceling"
                    >
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-danger"
                        @click="confirmCancelTransaction"
                        :disabled="isCanceling"
                    >
                        <i
                            v-if="isCanceling"
                            class="bi bi-arrow-repeat spin me-2"
                        ></i>
                        <i v-else class="bi bi-check-lg me-2"></i>
                        Batalkan
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import axios from "axios";
import moment from "moment";
import ModernDataTable from "@/components/legacy/table-noir.vue";
import Modal from "@/components/legacy/modalV2-noir.vue";

import { ElMessage } from "element-plus";

export default {
    name: "HistoryTable",
    components: {
        ModernDataTable,
        Modal,
    },
    props: {
        filters: {
            type: Object,
            default: () => ({
                search: "",
                tanggal: [],
                status: "",
                target: "",
            }),
        },
    },
    data() {
        return {
            history: [],
            isLoading: false,
            selectedHistory: null,
            isCanceling: false,
            // Alert Modal State
            showAlertModal: false,
            titleAlertModal: "",
            bodyAlert: "",
            transactionToCancel: null,
        };
    },
    computed: {
        tableColumns() {
            return [
                {
                    key: "No_Transaksi",
                    title: "No Transaksi",
                    minWidth: "220px",
                },
                {
                    key: "Tanggal_Lembur",
                    title: "Tanggal Lembur",
                    minWidth: "180px",
                },
                { key: "Target_Off", title: "Target", minWidth: "160px" },
                {
                    key: "Durasi_Jam",
                    title: "Durasi Lembur",
                    minWidth: "130px",
                },
                { key: "Status", title: "Status", minWidth: "150px" },
                {
                    key: "actions",
                    title: "Aksi",
                    minWidth: "120px",
                    align: "center",
                },
            ];
        },
        mobileColumns() {
            return [
                { key: "Tanggal_Lembur", title: "Tanggal" },
                { key: "Target_Off", title: "Target" },
                { key: "Status", title: "Status" },
            ];
        },
        canCancel() {
            return this.canCancelItem(this.selectedHistory);
        },
        filteredHistory() {
            const search = String(this.filters?.search || "")
                .trim()
                .toLowerCase();
            const status = String(this.filters?.status || "")
                .trim()
                .toUpperCase();
            const target = String(this.filters?.target || "")
                .trim()
                .toUpperCase();
            const tanggal = Array.isArray(this.filters?.tanggal)
                ? this.filters.tanggal
                : [];
            const [startDate, endDate] = tanggal;

            return this.history.filter((item) => {
                const itemStatus = String(item?.Status || "").toUpperCase();
                const itemNo = String(item?.No_Transaksi || "").toLowerCase();
                const itemType = String(
                    item?.Jenis_Konversi || "",
                ).toLowerCase();
                const itemNote = String(item?.Note || "").toLowerCase();
                const itemDate = item?.Tanggal_Lembur
                    ? moment(item.Tanggal_Lembur).format("YYYY-MM-DD")
                    : "";

                if (
                    search &&
                    !itemNo.includes(search) &&
                    !itemType.includes(search) &&
                    !itemNote.includes(search)
                ) {
                    return false;
                }

                if (status && itemStatus !== status) {
                    return false;
                }

                if (target) {
                    const isDayOff = itemType === "day_off";
                    const isToCuti = itemType === "to_cuti";
                    if (target === "DAY_OFF" && !isDayOff) return false;
                    if (target === "MATERIAL" && (isDayOff || isToCuti)) return false;
                    if (target === "TO_CUTI" && !isToCuti) return false;
                }

                if (startDate && itemDate < startDate) {
                    return false;
                }

                if (endDate && itemDate > endDate) {
                    return false;
                }

                return true;
            });
        },
    },
    methods: {
        async fetchHistory() {
            this.isLoading = true;
            try {
                const response = await axios.get("/konversi-lembur/history");
                this.history = Array.isArray(response?.data?.data)
                    ? response.data.data
                    : [];
            } catch (error) {
                console.error(error);
                this.history = [];
            } finally {
                this.isLoading = false;
            }
        },
        openDetail(item) {
            this.selectedHistory = item;
        },
        closeDetail() {
            this.selectedHistory = null;
        },
        canCancelItem(item) {
            if (!item) return false;
            if (String(item.Status || "").toUpperCase() !== "SUBMITTED")
                return false;

            const steps = item.approval_steps || [];
            const hasAction = steps.some((step) => {
                const s = String(step.status || "").toUpperCase();
                return s === "APPROVED" || s === "REJECTED";
            });
            return !hasAction;
        },
        cancelAlertButton(item) {
            const noTransaksi =
                typeof item === "object" ? item?.No_Transaksi : item;

            if (!noTransaksi) return;

            this.transactionToCancel = noTransaksi;
            this.titleAlertModal = "Batalkan Pengajuan";
            this.bodyAlert = `Apakah Anda yakin ingin membatalkan pengajuan ${noTransaksi}?`;
            this.showAlertModal = true;
        },
        closeAlertModal() {
            this.showAlertModal = false;
            this.titleAlertModal = "";
            this.bodyAlert = "";
            this.transactionToCancel = null;
        },
        async confirmCancelTransaction() {
            if (!this.transactionToCancel) return;

            this.isCanceling = true;
            try {
                const response = await axios.post(
                    `/konversi-lembur/cancel/${this.transactionToCancel}`,
                );
                if (response.data.status) {
                    ElMessage({
                        type: "success",
                        message: "Pengajuan berhasil dibatalkan.",
                        duration: 3000,
                    });
                    window.dispatchEvent(
                        new Event("konversi-lembur:refresh-stats"),
                    );
                    this.fetchHistory();
                    this.closeDetail();
                    this.closeAlertModal();
                }
            } catch (error) {
                console.error(error);
                const msg =
                    error.response?.data?.message ||
                    "Gagal membatalkan pengajuan.";
                ElMessage({
                    type: "error",
                    message: msg,
                    duration: 3000,
                });
            } finally {
                this.isCanceling = false;
            }
        },
        formatDate(date) {
            if (!date) return "-";
            return moment(date).locale("id").format("DD MMM YYYY");
        },
        formatDuration(value) {
            const numeric = Number(value || 0);
            if (!Number.isFinite(numeric) || numeric <= 0) return "-";
            return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(1)} Jam`;
        },
        getTypeLabel(type) {
            const normalized = String(type || "DAY_OFF").toUpperCase();
            if (normalized === "TO_CUTI") return "Bayar Hutang Cuti";
            return normalized === "DAY_OFF" ? "Day Off" : "Material / Uang";
        },
        getTargetLabel(type) {
            return String(type || "DAY_OFF").toUpperCase() === "TO_CUTI"
                ? "Bayar Hutang Cuti"
                : "Material / Uang";
        },
        getStatusLabel(status) {
            const normalized = String(status || "").toUpperCase();
            if (normalized === "APPLIED") return "Applied";
            if (normalized === "APPROVED") return "Approved";
            if (normalized === "REJECTED") return "Rejected";
            if (normalized === "CANCELED") return "Canceled";
            return "Submitted";
        },
        getStatusClass(status) {
            switch (String(status || "").toUpperCase()) {
                case "APPLIED":
                    return "badge bg-success-subtle text-success py-2 px-3 rounded-pill text-xs border border-success border-opacity-25";
                case "APPROVED":
                    return "badge bg-primary-subtle text-primary py-2 px-3 rounded-pill text-xs border border-primary border-opacity-25";
                case "REJECTED":
                case "CANCELED":
                    return "badge bg-danger-subtle text-danger py-2 px-3 rounded-pill text-xs border border-danger border-opacity-25";
                default:
                    return "badge bg-warning-subtle text-warning py-2 px-3 rounded-pill text-xs border border-warning border-opacity-25";
            }
        },
        getStatusIcon(status) {
            switch (String(status || "").toUpperCase()) {
                case "APPLIED":
                    return "bi bi-check2-circle";
                case "APPROVED":
                    return "bi bi-patch-check-fill";
                case "REJECTED":
                case "CANCELED":
                    return "bi bi-x-circle-fill";
                default:
                    return "bi bi-hourglass-split";
            }
        },
        getHistoryStatusPillClass(status) {
            switch (String(status || "").toUpperCase()) {
                case "APPLIED":
                    return "is-applied";
                case "APPROVED":
                    return "is-approved";
                case "REJECTED":
                case "CANCELED":
                    return "is-rejected";
                default:
                    return "is-pending";
            }
        },
        getTransactionInitial(noTransaksi) {
            const raw = String(noTransaksi || "").replace(/[^A-Za-z0-9]/g, "");
            return raw.slice(0, 2).toUpperCase() || "KL";
        },
    },
    mounted() {
        this.fetchHistory();
        window.addEventListener(
            "konversi-lembur:refresh-history",
            this.fetchHistory,
        );
    },
    beforeUnmount() {
        window.removeEventListener(
            "konversi-lembur:refresh-history",
            this.fetchHistory,
        );
    },
};
</script>

<style scoped>
.history-wrap {
    position: relative;
}

/* BUTTONS */
.btn-modern {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.btn-modern.btn-primary {
    background: var(--hcis-indigo-600);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}

.btn-modern.btn-primary:hover {
    background: var(--hcis-indigo-700);
    transform: translateY(-1px);
}

.btn-modern.btn-secondary {
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #374151;
}

.btn-modern.btn-secondary:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}
.btn-modern.btn-danger {
    background: #dc3545;
    border: 1px solid #e5e7eb;
    color: #fff;
}

.btn-modern.btn-danger:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}

.stitch-action-btn {
    border: 1px solid #e5e7eb;
    background: white;
    color: #6b7280;
    transition: all 0.2s;
}

.stitch-action-btn:hover {
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

.is-applied {
    color: var(--hcis-indigo-600);
    background-color: var(--hcis-indigo-50);
    font-weight: 800;
}

.is-approved {
    color: #20c997;
    background-color: #d4edda;
    font-weight: 800;
}

.is-rejected {
    color: #dc3545;
    background-color: var(--hcis-legacy-bootstrap-danger);
    font-weight: 800;
}

.is-pending {
    color: #ffc107;
    background-color: #fff3cd;
    font-weight: 800;
}
.detail-status-badge {
    display: inline-block;
    padding: 0.625rem 2rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-status-badge.is-applied {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.detail-status-badge.is-pending {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.detail-status-badge.is-rejected {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.history-transaction-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.history-transaction-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.detail-modal-content {
    padding: 0;
}

.detail-modal-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1rem;
    min-height: 420px;
}

.detail-left-column {
    background: var(--hcis-slate-50);
    padding: 1.25rem;
    border-right: 1px solid var(--hcis-slate-200);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-right-column {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 560px;
}

.detail-transaksi-card {
    padding: 1rem;
    border-radius: 16px;
    color: white;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.22);
}

.detail-transaksi-avatar {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    background: white;
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
    flex-shrink: 0;
}

.detail-transaksi-number {
    font-size: 1rem;
    font-weight: 800;
    color: white;
    letter-spacing: 0.02em;
}

.detail-transaksi-type {
    min-width: 0;
}

.detail-status-section {
    text-align: center;
    background: white;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 1rem;
}

.detail-status-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.75rem;
}

.detail-status-date {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    margin-top: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
}

.detail-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 0.7rem 1rem;
    background: #fff;
    color: var(--hcis-slate-800);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
}

.detail-approval-section,
.detail-info-section {
    background: white;
    border-radius: 16px;
    padding: 1rem 1.1rem;
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.detail-section-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    margin-bottom: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.detail-section-title i {
    color: var(--hcis-indigo-600);
}

.detail-approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.detail-approval-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.detail-approval-indicator {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.detail-approval-indicator.approved {
    background: var(--hcis-emerald-500);
    color: white;
}

.detail-approval-indicator.rejected {
    background: var(--hcis-red-500);
    color: white;
}

.detail-approval-indicator.pending {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-500);
}

.detail-approval-info {
    flex: 1;
    min-width: 0;
}

.detail-approval-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.detail-approval-date {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    margin-top: 0.15rem;
}

.detail-approval-note {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}

.detail-approval-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--hcis-emerald-500);
}

.detail-history-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    padding: 1rem;
    background: #fcfdff;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.detail-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.75rem;
    border-radius: 12px;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
}

.detail-info-item small {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
    font-weight: 700;
}

.detail-info-item span {
    font-size: 0.9rem;
    color: var(--hcis-slate-900);
    font-weight: 700;
}

.detail-shift-date {
    color: var(--hcis-slate-900);
}

.detail-note-card,
.detail-attachment-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-slate-50);
    padding: 0.9rem 1rem;
}

.detail-note-card {
    color: var(--hcis-slate-700);
    line-height: 1.6;
    font-style: italic;
}

@media (max-width: 768px) {
    .detail-modal-grid {
        grid-template-columns: 1fr;
    }

    .detail-left-column {
        border-right: none;
        border-bottom: 1px solid var(--hcis-slate-200);
    }

    .detail-right-column {
        max-height: none;
    }

    .detail-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
