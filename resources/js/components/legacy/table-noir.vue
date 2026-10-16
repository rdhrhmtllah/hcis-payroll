<template>
    <div class="modern-data-table-container">
        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
            <div class="loading-animation">
                <div class="loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <p class="loading-text">Memuat data...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="!data || data.length === 0" class="empty-state">
            <div class="empty-icon">
                <i
                    class="bi bi-inbox d-flex justify-content-center align-items-center"
                ></i>
            </div>
            <h4>Tidak Ada Data</h4>
            <p>{{ emptyMessage }}</p>
            <slot name="empty-action">
                <button
                    class="btn-modern rounded-4"
                    @click="$emit('empty-action')"
                >
                    <i
                        class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center"
                    ></i>
                    {{ emptyActionLabel }}
                </button>
            </slot>
        </div>

        <!-- Data Display -->
        <div v-else>
            <!-- Desktop Table View -->
            <div v-if="!isMobile" class="table-view-container" key="table">
                <div
                    class="table-responsive-wrapper"
                    :class="{ 'fit-to-container': fitToContainer }"
                >
                    <table
                        class="modern-table"
                        :class="{ 'fit-to-container': fitToContainer }"
                    >
                        <thead>
                            <tr>
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    :class="[
                                        'header-cell',
                                        column.sticky && 'sticky-column',
                                        column.align && `align-${column.align}`,
                                    ]"
                                    :style="getColumnStyle(column)"
                                >
                                    <slot :name="`header-${column.key}`" :column="column">
                                        <div class="header-cell-content">
                                            <i
                                                v-if="column.icon"
                                                :class="
                                                    column.icon +
                                                    ' me-2 d-flex justify-content-center align-items-center'
                                                "
                                            ></i>
                                            <span>
                                                {{ column.title }}
                                                <small
                                                    v-if="
                                                        column.showCount &&
                                                        totalData
                                                    "
                                                    class="fw-light"
                                                >
                                                    ({{ totalData }})
                                                </small>
                                            </span>
                                        </div>
                                    </slot>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, rowIndex) in data"
                                :key="getRowKey(item, rowIndex)"
                                :class="['table-row', { clickable: clickable }]"
                                @click="clickable && $emit('row-click', item)"
                            >
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    :class="[
                                        column.sticky && 'sticky-column',
                                        column.align && `align-${column.align}`,
                                    ]"
                                    :style="getColumnStyle(column)"
                                >
                                    <slot
                                        :name="`column-${column.key}`"
                                        :item="item"
                                        :value="
                                            getNestedValue(item, column.key)
                                        "
                                        :index="rowIndex"
                                    >
                                        <div :class="getCellClass(column)">
                                            {{ formatCellValue(item, column) }}
                                        </div>
                                    </slot>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div v-else class="card-view-container" key="card">
                <div class="card-grid">
                    <div
                        v-for="(item, index) in data"
                        :key="getRowKey(item, index)"
                        class="data-card position-relative overflow-hidden mb-3"
                        :class="{ clickable: clickable }"
                        @click="clickable && $emit('row-click', item)"
                    >
                        <div
                            class="card-status-indicator"
                            :class="getCardHeaderClass(item)"
                        ></div>
                        <div
                            class="card-header border-bottom pb-2 mb-2.5 bg-transparent p-0 d-flex justify-content-between align-items-start gap-2"
                        >
                            <div class="card-main-info flex-grow-1">
                                <slot
                                    name="card-header"
                                    :item="item"
                                    :index="index"
                                >
                                    <!-- Default card header content -->
                                    <div
                                        class="d-flex align-items-center gap-2 mb-1"
                                    >
                                        <div
                                            class="bg-primary-subtle text-primary p-2 rounded text-center d-flex flex-column lh-1"
                                        >
                                            <i
                                                class="bi bi-file-earmark-text mb-1"
                                            ></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">
                                                No. Transaksi
                                            </div>
                                            <div class="fw-bold text-dark">
                                                {{ getCardTitle(item) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="card-subtitle text-muted mt-2 ps-1"
                                    >
                                        <small>{{
                                            getCardSubtitle(item)
                                        }}</small>
                                    </div>
                                </slot>
                            </div>
                            <div class="card-status mt-1">
                                <span
                                    class="badge rounded-pill fw-semibold border"
                                    :class="
                                        getCardStatusBadgeClass(
                                            getCardStatus(item),
                                        )
                                    "
                                >
                                    <slot
                                        name="card-status"
                                        :item="item"
                                        :index="index"
                                    >
                                        {{ getCardStatus(item) }}
                                    </slot>
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="row g-2" :class="{ 'mb-3': showActions }">
                                <div
                                    v-for="column in mobileColumns"
                                    :key="column.key"
                                    :class="[column.col ? `col-${column.col}` : 'col-6', 'mb-2']"
                                >
                                    <div class="mobile-col-title">
                                        {{ column.title }}
                                    </div>
                                    <div class="mobile-col-value">
                                        <slot
                                            :name="`mobile-${column.key}`"
                                            :item="item"
                                            :value="
                                                getNestedValue(item, column.key)
                                            "
                                        >
                                            {{
                                                formatMobileValue(item, column)
                                            }}
                                        </slot>
                                    </div>
                                </div>
                            </div>

                            <!-- Action buttons for mobile -->
                            <div
                                v-if="showActions"
                                class="d-flex gap-2 w-100 justify-content-end"
                            >
                                <slot
                                    name="card-actions"
                                    :item="item"
                                    :index="index"
                                >
                                    <!-- Default actions -->
                                    <button
                                        class="btn-modern btn-danger py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                                        @click.stop="
                                            $emit('action-delete', item)
                                        "
                                        v-if="showDelete"
                                    >
                                        <i
                                            class="bi bi-trash d-flex justify-content-center align-items-center me-2"
                                        ></i>
                                        Hapus
                                    </button>
                                </slot>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="
                    showPagination && totalPages > 1 && fullPagination != null
                "
                class="pagination-container"
            >
                <button
                    class="page-btn"
                    @click="
                        $emit(
                            'page-change',
                            fullPagination.prev_page_url
                                ? fullPagination.prev_page_url
                                : currentPage - 1,
                        )
                    "
                    :disabled="
                        currentPage === 1 || !fullPagination.prev_page_url
                    "
                >
                    <i
                        class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                    ></i>
                </button>

                <div class="page-info">
                    Halaman {{ currentPage }} dari {{ totalPages }}
                </div>

                <button
                    class="page-btn"
                    @click="
                        $emit(
                            'page-change',
                            fullPagination.next_page_url
                                ? fullPagination.next_page_url
                                : currentPage + 1,
                        )
                    "
                    :disabled="
                        currentPage === totalPages ||
                        !fullPagination.next_page_url
                    "
                >
                    <i
                        class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                    ></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { tanggalPanjang } from '@/utils/tanggal';
export default {
    name: "ModernDataTable",
    props: {
        // Data
        data: {
            type: Array,
            default: () => [],
        },
        columns: {
            type: Array,
            required: true,
            /* `title` cukup ADA, tidak harus berisi. Kolom checkbox dan kolom
               aksi memang berjudul kosong supaya kepala tabelnya bersih, dan
               validator lama menolaknya — peringatannya muncul di setiap tabel
               yang punya kolom pilih. */
            validator: (cols) => cols.every((col) => col.key && col.title != null),
        },
        loading: {
            type: Boolean,
            default: false,
        },

        // Configuration
        clickable: {
            type: Boolean,
            default: false,
        },
        showActions: {
            type: Boolean,
            default: true,
        },
        showDelete: {
            type: Boolean,
            default: true,
        },
        showPagination: {
            type: Boolean,
            default: false,
        },

        // Mobile specific
        mobileColumns: {
            type: Array,
            default: null,
        },
        cardTitleKey: {
            type: String,
            default: "",
        },
        cardSubtitleKey: {
            type: String,
            default: "",
        },
        cardStatusKey: {
            type: String,
            default: "",
        },

        // Pagination
        currentPage: {
            type: Number,
            default: 1,
        },
        /* Tipenya menyesuaikan PEMAKAIAN, bukan sebaliknya: template komponen
           ini membaca `fullPagination.prev_page_url` dan `.next_page_url`,
           yaitu bentuk objek paginator Laravel. Deklarasi lamanya `Array`
           sehingga setiap pemanggil yang mengirim paginator asli — tujuh
           halaman — memicu peringatan tipe, sementara yang mengirim `[]`
           lolos tipe lalu membaca properti yang tak pernah ada.

           `required: true` juga dibuang: berpasangan dengan `default` keduanya
           saling meniadakan, dan pemanggil yang sengaja tak memakai paginasi
           dipaksa mengarang nilai. Array tetap diterima supaya pemanggil yang
           mengirim `[]` tidak berubah perilakunya. */
        fullPagination: {
            type: [Object, Array],
            default: () => ({}),
        },
        totalPages: {
            type: Number,
            default: 1,
        },
        totalData: {
            type: Number,
            default: 0,
        },

        // Empty state
        emptyMessage: {
            type: String,
            default: "Tidak ada data yang ditemukan.",
        },
        emptyActionLabel: {
            type: String,
            default: "Tambah Data",
        },

        // Custom row key
        rowKey: {
            type: [String, Function],
            default: "id",
        },
        fitToContainer: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            windowWidth: window.innerWidth,
        };
    },

    computed: {
        isMobile() {
            return this.windowWidth < 768;
        },
    },

    methods: {
        getRowKey(item, index) {
            if (typeof this.rowKey === "function") {
                return this.rowKey(item, index);
            }
            // console.log(item);
            return item && item[this.rowKey] ? item[this.rowKey] : index;
        },

        getNestedValue(obj, path) {
            return path.split(".").reduce((current, key) => {
                return current && current[key] !== undefined
                    ? current[key]
                    : null;
            }, obj);
        },

        formatCellValue(item, column) {
            const value = this.getNestedValue(item, column.key);

            if (column.formatter && typeof column.formatter === "function") {
                return column.formatter(value, item, column);
            }

            if (column.type === "date" && value) {
                return this.formatDate(value);
            }

            if (column.type === "boolean") {
                return value ? "Ya" : "Tidak";
            }

            return value || "-";
        },

        formatMobileValue(item, column) {
            const value = this.getNestedValue(item, column.key);

            if (
                column.mobileFormatter &&
                typeof column.mobileFormatter === "function"
            ) {
                return column.mobileFormatter(value, item, column);
            }

            return this.formatCellValue(item, column);
        },

        getCellClass(column) {
            const baseClass = column.cellClass || "";
            const typeClass = column.type ? `cell-${column.type}` : "";
            return `${baseClass} ${typeClass}`.trim();
        },
        getColumnStyle(column) {
            const style = {};
            if (column?.width) style.width = String(column.width);
            if (column?.minWidth) style.minWidth = String(column.minWidth);
            if (column?.maxWidth) style.maxWidth = String(column.maxWidth);
            if (column?.align) style.textAlign = String(column.align);
            return style;
        },

        getCardHeaderClass(item) {
            if (this.cardStatusKey) {
                const status = this.getNestedValue(item, this.cardStatusKey);
                const normalizedStatus =
                    typeof status === "string"
                        ? status.toLowerCase()
                        : status != null
                          ? String(status).toLowerCase()
                          : "default";
                return `header-${normalizedStatus}`;
            }
            return "header-default";
        },

        getCardTitle(item) {
            return this.cardTitleKey
                ? this.getNestedValue(item, this.cardTitleKey)
                : "";
        },

        getCardSubtitle(item) {
            return this.cardSubtitleKey
                ? this.getNestedValue(item, this.cardSubtitleKey)
                : "";
        },

        getCardStatus(item) {
            return this.cardStatusKey
                ? this.getNestedValue(item, this.cardStatusKey)
                : "";
        },

        getCardStatusBadgeClass(status) {
            if (!status) return "bg-secondary text-white";

            const normalizedStatus = status.toString().toUpperCase().trim();

            const statusMap = {
                APPROVED: "bg-success-subtle text-success border-success",
                DISETUJUI: "bg-success-subtle text-success border-success",
                PENDING: "bg-warning-subtle text-warning border-warning",
                MENUNGGU: "bg-warning-subtle text-warning border-warning",
                REJECTED: "bg-danger-subtle text-danger border-danger",
                DITOLAK: "bg-danger-subtle text-danger border-danger",
                DRAFT: "bg-secondary-subtle text-secondary border-secondary",
            };

            for (const [key, bgClass] of Object.entries(statusMap)) {
                if (normalizedStatus.includes(key)) {
                    return bgClass;
                }
            }
            return "bg-primary-subtle text-primary border-primary";
        },

        formatDate(dateString) {
            return tanggalPanjang(dateString);
        },

        updateWindowWidth() {
            this.windowWidth = window.innerWidth;
        },
    },

    mounted() {
        window.addEventListener("resize", this.updateWindowWidth);
    },

    beforeUnmount() {
        window.removeEventListener("resize", this.updateWindowWidth);
    },

    emits: ["row-click", "action-delete", "page-change", "empty-action"],
};
</script>

<style scoped>
/* Base */
.modern-data-table-container {
    background: var(--surface);
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
}

/* Loading & Empty */
.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 240px;
    padding: 2.5rem 1.25rem;
}

.loading-dots {
    display: flex;
    justify-content: center;
    gap: 0.45rem;
    margin-top: 0.8rem;
}

.loading-dots span {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--primary);
    animation: bounce 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.loading-text {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.86rem;
}

.empty-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    color: #94a3b8;
    border-radius: 50%;
    font-size: 1.6rem;
    margin-bottom: 1.1rem;
    border: 1px dashed #cbd5e1;
}

.empty-state h4 {
    margin: 0 0 0.45rem;
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

.empty-state p {
    margin: 0 0 1.25rem;
    max-width: 380px;
    font-size: 0.82rem;
    color: #64748b;
    line-height: 1.5;
}

.empty-state .btn-modern {
    background: #1e293b;
    color: #ffffff;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.5rem 1.1rem;
    border: 1px solid #1e293b;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.empty-state .btn-modern:hover {
    background: #0f172a;
    border-color: #0f172a;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

.empty-state .btn-modern:active {
    transform: translateY(0);
}

.empty-state .btn-modern i {
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}

.empty-state .btn-modern:hover i {
    transform: rotate(30deg);
}

/* Table */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-responsive-wrapper {
    min-width: 760px;
}

.table-responsive-wrapper.fit-to-container {
    min-width: 100%;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
    color: #334155;
}

.modern-table.fit-to-container {
    table-layout: fixed;
}

.modern-table th,
.modern-table td {
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
    padding: 0.85rem 1rem;
    vertical-align: middle;
}

.modern-table.fit-to-container th,
.modern-table.fit-to-container td {
    padding: 0.7rem 0.8rem;
}

.modern-table.fit-to-container td {
    overflow: hidden;
}

.modern-table th {
    position: sticky;
    top: 0;
    z-index: 8;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    border-bottom: 1px solid #e2e8f0;
}

.header-cell {
    white-space: nowrap;
}

.header-cell-content {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.modern-table th.align-center .header-cell-content {
    justify-content: center;
}

.modern-table th.align-right .header-cell-content {
    justify-content: flex-end;
}

.sticky-column {
    position: sticky;
    left: 0;
    z-index: 9;
    background: #ffffff;
    box-shadow: 2px 0 6px -2px rgba(15, 23, 42, 0.06);
}

.modern-table th.sticky-column {
    background: #f8fafc;
}

.table-row {
    transition: all 0.15s ease;
}

.table-row.clickable {
    cursor: pointer;
}

.table-row.clickable:hover td {
    background-color: #fafbfc;
}

.table-row.clickable:hover td.sticky-column {
    background-color: #fafbfc;
}

/* Card View */
.card-view-container {
    padding: 0.75rem;
}

.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.85rem;
}

.data-card {
    transition: all 0.2s ease;
}

.data-card.clickable {
    cursor: pointer;
}

.data-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08) !important;
}

/* Status Indicator Strip (left border effect) */
.card-status-indicator {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
}

.header-default {
    background: var(--primary);
}

.header-approved {
    background: var(--success);
}

.header-applied {
    background: var(--primary);
}

.header-pending {
    background: var(--primary);
}

.header-rejected {
    background: var(--danger);
}

.header-draft {
    background: #64748b;
}

.header-waiting {
    background: #f59e0b;
}

.data-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
}

.card-header {
    padding: 0.8rem 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.7rem;
}

.card-main-info {
    min-width: 0;
    flex: 1;
}

.card-title {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.3;
}

.card-subtitle {
    margin-top: 0.15rem;
    font-size: 0.74rem;
    opacity: 0.95;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.35;
}

.card-status {
    font-size: 0.68rem;
    white-space: nowrap;
}

.card-status .badge {
    padding: 0.35rem 0.6rem;
    font-size: 0.68rem;
}

.card-body {
    padding: 0.82rem 0.9rem 0.9rem;
    flex-grow: 1;
}

.mobile-col-title {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.15rem;
}

.mobile-col-value {
    font-size: 0.78rem;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.4;
    word-break: break-word;
}

.card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 0.7rem;
}

.card-btn {
    border: none;
    border-radius: 8px;
    padding: 0.46rem 0.84rem;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.card-btn.delete-btn {
    color: #dc2626;
    background: #fee2e2;
}

.card-btn.delete-btn:hover {
    background: #fecaca;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.38rem;
    border-top: 1px solid #e2e8f0;
    padding: 0.85rem;
}

.page-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #d0d9e5;
    background: #fff;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.page-btn:hover:not(:disabled) {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4f46e5;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    margin: 0 0.4rem;
    font-size: 0.78rem;
    color: #64748b;
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

@media (max-width: 768px) {
    .card-grid {
        grid-template-columns: 1fr;
    }

    .card-row {
        flex-direction: column;
        gap: 0.12rem;
        margin-bottom: 0.48rem;
    }

    .card-label {
        width: auto;
    }

    .card-actions {
        flex-direction: column;
    }

    .card-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
