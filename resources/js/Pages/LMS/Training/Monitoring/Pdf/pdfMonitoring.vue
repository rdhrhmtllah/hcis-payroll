<template>
    <div class="pdf-mon container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1">Monitoring Render PDF</h4>
                <p class="text-muted mb-0 small">
                    Pantau status konversi dokumen PDF untuk Secure Viewer.
                </p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" @click="refreshAll" :disabled="loadingSummary">
                    <span v-if="loadingSummary" class="spinner-border spinner-border-sm me-1"></span>
                    Refresh
                </button>
                <button class="btn btn-primary btn-sm" @click="openMigrateModal">
                    Mulai Migrasi
                </button>
            </div>
        </div>

        <!-- RINGKASAN GLOBAL -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md">
                <div class="stat-card">
                    <div class="stat-label">Total PDF</div>
                    <div class="stat-value">{{ summary.total }}</div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="stat-card border-success">
                    <div class="stat-label text-success">Selesai (DONE)</div>
                    <div class="stat-value text-success">{{ summary.done }}</div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="stat-card border-info">
                    <div class="stat-label text-info">Proses</div>
                    <div class="stat-value text-info">{{ summary.processing }}</div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="stat-card border-warning">
                    <div class="stat-label text-warning">Menunggu</div>
                    <div class="stat-value text-warning">{{ summary.pending }}</div>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="stat-card border-danger">
                    <div class="stat-label text-danger">Gagal</div>
                    <div class="stat-value text-danger">{{ summary.failed }}</div>
                </div>
            </div>
        </div>

        <!-- PROGRESS BAR GLOBAL -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-semibold">Progress Global</span>
                    <span>{{ summary.percent }}% ({{ summary.done }}/{{ summary.total }})</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div
                        class="progress-bar bg-success"
                        :style="{ width: summary.percent + '%' }"
                    ></div>
                </div>
                <div v-if="summary.is_processing" class="small text-info mt-2">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Render sedang berlangsung — auto-refresh tiap {{ pollSeconds }}s.
                </div>
                <div v-else-if="summary.pending > 0" class="small text-warning mt-2">
                    {{ summary.pending }} dokumen menunggu untuk dirender. Klik "Mulai Migrasi" untuk mengantrikan ke proses.
                </div>
            </div>
        </div>

        <!-- FILTER + TABEL -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select v-model="filterStatus" class="form-select form-select-sm w-auto" @change="loadList(1)">
                        <option value="">Semua Status</option>
                        <option value="DONE">DONE</option>
                        <option value="PROCESSING">PROCESSING</option>
                        <option value="PENDING">PENDING</option>
                        <option value="FAILED">FAILED</option>
                    </select>
                    <input
                        v-model="filterSearch"
                        type="text"
                        class="form-control form-control-sm w-auto flex-grow-1"
                        placeholder="Cari judul dokumen..."
                        @keyup.enter="loadList(1)"
                    />
                    <button class="btn btn-outline-secondary btn-sm" @click="loadList(1)">Cari</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Judul Dokumen</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Halaman</th>
                                <th>Terakhir Update</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loadingList">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2"></span> Memuat...
                                </td>
                            </tr>
                            <tr v-else-if="items.length === 0">
                                <td colspan="5" class="text-center text-muted py-4">Tidak ada dokumen.</td>
                            </tr>
                            <tr v-for="item in items" :key="item.id" style="cursor: pointer;" @click="openDetail(item)">
                                <td>{{ item.judul }}</td>
                                <td class="text-center">
                                    <span class="badge" :class="statusBadge(item.status)">{{ item.status }}</span>
                                </td>
                                <td class="text-center">{{ item.total_halaman || '-' }}</td>
                                <td class="small text-muted">{{ formatDate(item.updated_at) }}</td>
                                <td class="text-end" @click.stop>
                                    <button
                                        v-if="item.status === 'FAILED' || item.status === 'DONE'"
                                        class="btn btn-outline-primary btn-sm"
                                        @click="retry(item)"
                                        :disabled="retryingId === item.berkas_id"
                                    >
                                        <span v-if="retryingId === item.berkas_id" class="spinner-border spinner-border-sm"></span>
                                        Render Ulang
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm ms-1" @click="openDetail(item)">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div v-if="pagination.last_page > 1" class="d-flex justify-content-end gap-1 mt-2">
                    <button
                        class="btn btn-sm btn-outline-secondary"
                        :disabled="pagination.current_page <= 1"
                        @click="loadList(pagination.current_page - 1)"
                    >&laquo;</button>
                    <span class="btn btn-sm disabled">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                    <button
                        class="btn btn-sm btn-outline-secondary"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="loadList(pagination.current_page + 1)"
                    >&raquo;</button>
                </div>
            </div>
        </div>

        <!-- MODAL DETAIL -->
        <div v-if="detailOpen" class="mon-modal-backdrop" @click.self="detailOpen = false">
            <div class="mon-modal">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">{{ detailTitle }}</h6>
                        <span class="badge" :class="statusBadge(detail.status)">{{ detail.status }}</span>
                    </div>
                    <button class="btn-close" @click="detailOpen = false"></button>
                </div>

                <div v-if="loadingDetail" class="text-center py-4 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span> Memeriksa halaman...
                </div>

                <div v-else>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Halaman ter-render</span>
                        <span>{{ detail.halaman_selesai }}/{{ detail.total_halaman }} ({{ detail.percent }}%)</span>
                    </div>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar bg-success" :style="{ width: detail.percent + '%' }"></div>
                    </div>

                    <div v-if="detail.error_message" class="alert alert-danger small py-2">
                        {{ detail.error_message }}
                    </div>

                    <div class="page-grid">
                        <div
                            v-for="p in detail.pages"
                            :key="p.page"
                            class="page-chip"
                            :class="p.rendered ? 'rendered' : 'pending'"
                            :title="p.rendered ? 'Sudah dirender' : 'Belum dirender'"
                        >
                            {{ p.page }}
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button class="btn btn-outline-primary btn-sm" @click="retry({ berkas_id: detail.berkas_id, id: detailHashId })">
                            Render Ulang Dokumen Ini
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL MIGRASI -->
        <div v-if="migrateOpen" class="mon-modal-backdrop" @click.self="migrateOpen = false">
            <div class="mon-modal">
                <h6 class="fw-bold mb-3">Mulai Migrasi Render PDF</h6>
                <p class="small text-muted">Pilih cakupan dokumen yang akan dirender ke versi terbaru.</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" value="pending" v-model="migrateScope" id="scopePending" />
                    <label class="form-check-label" for="scopePending">
                        Yang <b>MENUNGGU (PENDING)</b> — {{ summary.pending }} dokumen
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" value="failed" v-model="migrateScope" id="scopeFailed" />
                    <label class="form-check-label" for="scopeFailed">
                        Yang <b>GAGAL (FAILED)</b> — {{ summary.failed }} dokumen
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" value="all_unrendered" v-model="migrateScope" id="scopeUnrendered" />
                    <label class="form-check-label" for="scopeUnrendered">
                        Semua yang <b>belum selesai</b> (PENDING + FAILED + PROCESSING)
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" value="all_done" v-model="migrateScope" id="scopeDone" />
                    <label class="form-check-label" for="scopeDone">
                        Semua dokumen <b>DONE</b> (render ULANG massal — gunakan setelah ganti versi render)
                    </label>
                </div>
                <div v-if="migrateScope === 'all_done'" class="alert alert-warning small py-2 mt-2">
                    Ini akan mengantrikan ULANG semua dokumen DONE. Pastikan memang ingin render ulang massal.
                </div>
                <div class="text-end mt-3">
                    <button class="btn btn-light btn-sm me-2" @click="migrateOpen = false">Batal</button>
                    <button class="btn btn-primary btn-sm" @click="runMigration" :disabled="migrating">
                        <span v-if="migrating" class="spinner-border spinner-border-sm me-1"></span>
                        Mulai
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

const BASE = "/lms/admin/pdf-monitoring";

export default {
    name: "PdfMonitoring",
    data() {
        return {
            summary: { total: 0, done: 0, processing: 0, pending: 0, failed: 0, percent: 0, in_progress: false, is_processing: false },
            items: [],
            pagination: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
            filterStatus: "",
            filterSearch: "",
            loadingSummary: false,
            loadingList: false,
            retryingId: null,

            detailOpen: false,
            loadingDetail: false,
            detail: { berkas_id: null, status: "", total_halaman: 0, halaman_selesai: 0, percent: 0, error_message: null, pages: [] },
            detailTitle: "",
            detailHashId: null,

            migrateOpen: false,
            migrateScope: "failed",
            migrating: false,

            pollSeconds: 6,
            pollTimer: null,
        };
    },

    mounted() {
        this.refreshAll();
        this.startPolling();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        async refreshAll() {
            await Promise.all([this.loadSummary(), this.loadList(this.pagination.current_page)]);
        },

        async loadSummary() {
            this.loadingSummary = true;
            try {
                const { data } = await axios.get(`${BASE}/summary`);
                if (data.success) this.summary = data.result;
            } catch (e) {
                console.error("Gagal memuat ringkasan", e);
            } finally {
                this.loadingSummary = false;
            }
        },

        async loadList(page = 1) {
            this.loadingList = true;
            try {
                const { data } = await axios.get(`${BASE}/list`, {
                    params: { page, status: this.filterStatus, search: this.filterSearch, limit: this.pagination.per_page },
                });
                if (data.success) {
                    this.items = data.result || data.data || [];
                    const p = data.pagination || {};
                    this.pagination = {
                        current_page: p.current_page || page,
                        last_page: p.last_page || 1,
                        per_page: p.per_page || this.pagination.per_page,
                        total: p.total || this.items.length,
                    };
                }
            } catch (e) {
                console.error("Gagal memuat daftar", e);
            } finally {
                this.loadingList = false;
            }
        },

        async openDetail(item) {
            this.detailOpen = true;
            this.loadingDetail = true;
            this.detailTitle = item.judul;
            this.detailHashId = item.id;
            try {
                const { data } = await axios.get(`${BASE}/detail/${item.id}`);
                if (data.success) this.detail = data.result;
            } catch (e) {
                console.error("Gagal memuat detail", e);
            } finally {
                this.loadingDetail = false;
            }
        },

        openMigrateModal() {
            // Default ke scope yang paling mungkin ada isinya.
            if (this.summary.pending > 0) this.migrateScope = "pending";
            else if (this.summary.failed > 0) this.migrateScope = "failed";
            else this.migrateScope = "all_unrendered";
            this.migrateOpen = true;
        },

        async runMigration() {
            this.migrating = true;
            try {
                const { data } = await axios.post(`${BASE}/migrate`, { scope: this.migrateScope });
                if (data.success) {
                    this.migrateOpen = false;
                    this.refreshAll();
                    alert(data.message || "Migrasi dimulai.");
                } else {
                    alert(data.message || "Gagal memulai migrasi.");
                }
            } catch (e) {
                alert(e?.response?.data?.message || "Gagal memulai migrasi.");
            } finally {
                this.migrating = false;
            }
        },

        async retry(item) {
            this.retryingId = item.berkas_id;
            try {
                const { data } = await axios.post(`${BASE}/retry/${item.id}`);
                if (data.success) {
                    this.refreshAll();
                    if (this.detailOpen) this.detailOpen = false;
                } else {
                    alert(data.message || "Gagal render ulang.");
                }
            } catch (e) {
                alert(e?.response?.data?.message || "Gagal render ulang.");
            } finally {
                this.retryingId = null;
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => {
                // Hanya auto-refresh saat ada proses berjalan (hemat request).
                if (this.summary.in_progress) {
                    this.loadSummary();
                    this.loadList(this.pagination.current_page);
                }
            }, this.pollSeconds * 1000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        statusBadge(status) {
            return {
                "bg-success": status === "DONE",
                "bg-info": status === "PROCESSING",
                "bg-warning text-dark": status === "PENDING",
                "bg-danger": status === "FAILED",
                "bg-secondary": !["DONE", "PROCESSING", "PENDING", "FAILED"].includes(status),
            };
        },

        formatDate(val) {
            if (!val) return "-";
            try {
                return new Date(val).toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" });
            } catch {
                return val;
            }
        },
    },
};
</script>

<style scoped>
.stat-card {
    background: #fff;
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-left-width: 4px;
    border-radius: 8px;
    padding: 14px 16px;
    height: 100%;
}
.stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 600; }
.stat-value { font-size: 1.6rem; font-weight: 700; }

.mon-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.45);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
.mon-modal {
    background: #fff; border-radius: 12px; padding: 22px;
    width: 100%; max-width: 540px; max-height: 85vh; overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.page-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(38px, 1fr)); gap: 6px; }
.page-chip {
    aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 0.75rem; font-weight: 600;
}
.page-chip.rendered { background: #d1e7dd; color: #0f5132; }
.page-chip.pending { background: var(--hcis-legacy-bootstrap-danger); color: #842029; }
</style>
