<template>
    <SopModal
        :show="show"
        title="Pilih Dokumen"
        subtitle="Pilih dokumen master resmi untuk ditautkan pada item ini."
        icon="bi-journal-bookmark-fill"
        size="lg"
        @close="$emit('close')"
    >
        <div class="dp-stack">
            <!-- ── BARIS PENCARIAN & AKSI FILTER ── -->
            <div class="dp-header-bar">
                <!-- Search Box -->
                <div class="dp-search-box">
                    <i class="bi bi-search dp-search-icon"></i>
                    <input
                        v-model="filters.q"
                        type="search"
                        class="sop-field dp-search-input"
                        placeholder="Cari judul atau nomor dokumen…"
                        @input="cariDebounce"
                    />
                    <button
                        v-if="filters.q"
                        type="button"
                        class="dp-search-clear"
                        title="Hapus kata kunci pencarian"
                        @click="clearSearch"
                    >
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>

                <!-- Action Badges & Filter Toggle Button -->
                <div class="dp-header-actions">
                    <button
                        type="button"
                        class="dp-filter-toggle-btn"
                        :class="{ 'is-active': showFilters || activeFilterCount > 0 }"
                        :title="showFilters ? 'Sembunyikan filter' : 'Buka opsi filter'"
                        @click="showFilters = !showFilters"
                    >
                        <i class="bi bi-sliders2"></i>
                        <span>Filter</span>
                        <span v-if="activeFilterCount > 0" class="dp-filter-badge">
                            {{ activeFilterCount }}
                        </span>
                    </button>

                    <span v-if="tipe" class="dp-type-chip" :title="`Item ini memerlukan dokumen tipe ${tipe}`">
                        <i class="bi" :class="tipeIcon(tipe)"></i>
                        <span>{{ tipe }}</span>
                    </span>
                </div>
            </div>

            <!-- ── EXPANDABLE FILTER TOOLBAR ── -->
            <transition name="dp-expand">
                <div v-if="showFilters" class="dp-filter-panel">
                    <div class="row g-2 align-items-center">
                        <!-- Departemen Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="dp-filter-label">Departemen</label>
                            <select
                                v-model="filters.departemen_id"
                                class="sop-field sm w-100"
                                @change="onFilterChange"
                            >
                                <option value="">Semua Departemen</option>
                                <option
                                    v-for="dept in departemenOptions"
                                    :key="dept.value"
                                    :value="dept.value"
                                >
                                    {{ dept.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Jenis Dokumen Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="dp-filter-label">Jenis Dokumen</label>
                            <select
                                v-model="filters.jenis_id"
                                class="sop-field sm w-100"
                                @change="onFilterChange"
                            >
                                <option value="">Semua Jenis</option>
                                <option
                                    v-for="j in jenisOptions"
                                    :key="j.value"
                                    :value="j.value"
                                >
                                    {{ j.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Status Kesiapan Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="dp-filter-label">Status Dokumen</label>
                            <select
                                v-model="filters.status_versi"
                                class="sop-field sm w-100"
                                @change="onFilterChange"
                            >
                                <option value="all">Semua Status</option>
                                <option value="SIAP">Siap Pakai (Ada Versi)</option>
                                <option value="TANPA_VERSI">Belum Ada Versi</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="dp-filter-label">Urutan</label>
                            <select
                                v-model="filters.sort"
                                class="sop-field sm w-100"
                                @change="onFilterChange"
                            >
                                <option value="judul_asc">Judul (A - Z)</option>
                                <option value="judul_desc">Judul (Z - A)</option>
                                <option value="nomor_asc">Nomor Dokumen</option>
                                <option value="terbaru">Terbaru Ditambahkan</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Footer Reset Button -->
                    <div v-if="activeFilterCount > 0" class="d-flex justify-content-end mt-2">
                        <button
                            type="button"
                            class="stitch-btn ghost sm"
                            @click="resetFilters"
                        >
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Filter
                        </button>
                    </div>
                </div>
            </transition>

            <!-- ── QUICK STATUS PILLS ── -->
            <div class="dp-quick-pills">
                <button
                    type="button"
                    class="dp-quick-pill"
                    :class="{ active: filters.status_versi === 'all' }"
                    @click="setQuickStatus('all')"
                >
                    Semua
                    <span v-if="pagination.total_data" class="dp-pill-count">{{ pagination.total_data }}</span>
                </button>
                <button
                    type="button"
                    class="dp-quick-pill"
                    :class="{ active: filters.status_versi === 'SIAP' }"
                    @click="setQuickStatus('SIAP')"
                >
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    Siap Digunakan
                </button>
                <button
                    type="button"
                    class="dp-quick-pill"
                    :class="{ active: filters.status_versi === 'TANPA_VERSI' }"
                    @click="setQuickStatus('TANPA_VERSI')"
                >
                    <i class="bi bi-hourglass-split me-1 text-warning"></i>
                    Belum Ada Versi
                </button>
            </div>

            <!-- ── SKELETON LOADING ── -->
            <div v-if="loading" class="dp-list" aria-busy="true">
                <div v-for="n in 4" :key="n" class="dp-card dp-card-skel">
                    <span class="dp-radio-indicator"></span>
                    <div class="dp-card-content">
                        <div class="sop-skel dp-skel-title"></div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div class="sop-skel dp-skel-pill"></div>
                            <div class="sop-skel dp-skel-pill-sm"></div>
                        </div>
                    </div>
                    <div class="sop-skel dp-skel-badge"></div>
                </div>
            </div>

            <!-- ── EMPTY STATE ── -->
            <div v-else-if="!rows.length" class="dp-empty-state">
                <div class="dp-empty-icon-wrap">
                    <i class="bi bi-journal-x"></i>
                </div>
                <h6 class="dp-empty-title">
                    {{ hasActiveFilter ? 'Dokumen tidak ditemukan' : 'Belum ada dokumen yang tersedia' }}
                </h6>
                <p class="dp-empty-desc">
                    <template v-if="hasActiveFilter">
                        Tidak ada dokumen yang cocok dengan kombinasi kata kunci dan filter saat ini.
                    </template>
                    <template v-else-if="tipe">
                        Hanya dokumen aktif bertipe <strong>{{ tipe }}</strong> yang ditampilkan pada pilihan item ini.
                    </template>
                    <template v-else>
                        Dokumen resmi yang disahkan akan otomatis tampil pada daftar ini.
                    </template>
                </p>
                <button
                    v-if="hasActiveFilter"
                    type="button"
                    class="stitch-btn secondary sm mt-2"
                    @click="resetFilters"
                >
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset Pencarian & Filter
                </button>
            </div>

            <!-- ── DAFTAR DOKUMEN ── -->
            <div v-else class="dp-list" role="listbox" aria-label="Daftar Dokumen Master">
                <button
                    v-for="d in rows"
                    :key="d.Id_SOP_Dokumen"
                    type="button"
                    role="option"
                    class="dp-card"
                    :class="{
                        'is-selected': terpilih === d.Id_SOP_Dokumen,
                        'is-disabled': !siap(d),
                        'is-current': iniSekarang(d),
                    }"
                    :aria-selected="terpilih === d.Id_SOP_Dokumen"
                    :aria-disabled="!siap(d)"
                    :disabled="!siap(d)"
                    @click="terpilih = d.Id_SOP_Dokumen"
                    @dblclick="pilihLalu(d)"
                >
                    <!-- Radio / Check Indicator -->
                    <span class="dp-radio-indicator">
                        <i class="bi bi-check"></i>
                    </span>

                    <!-- Card Body -->
                    <div class="dp-card-content">
                        <div class="dp-card-header-row">
                            <span class="dp-card-title text-truncate" :title="d.Judul">
                                {{ d.Judul }}
                            </span>
                        </div>

                        <div class="dp-card-meta-row">
                            <!-- Nomor Dokumen -->
                            <span v-if="d.Nomor_SOP_Resmi" class="dp-meta-pill code" :title="`Nomor: ${d.Nomor_SOP_Resmi}`">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>{{ d.Nomor_SOP_Resmi }}</span>
                            </span>
                            <span v-else class="dp-meta-pill muted">
                                Tanpa nomor
                            </span>

                            <!-- Versi Aktif -->
                            <span v-if="d.Versi_String" class="dp-meta-pill versi" :title="`Versi rilis aktif: v${d.Versi_String}`">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>v{{ d.Versi_String }}</span>
                            </span>

                            <!-- Departemen -->
                            <span v-if="d.Nama_Departemen" class="dp-meta-pill dept text-truncate" :title="d.Nama_Departemen">
                                <i class="bi bi-building"></i>
                                <span class="text-truncate">{{ d.Nama_Departemen }}</span>
                            </span>

                            <!-- Jenis Dokumen -->
                            <span v-if="d.Nama_Jenis" class="dp-meta-pill jenis text-truncate" :title="d.Nama_Jenis">
                                <i class="bi bi-tag"></i>
                                <span class="text-truncate">{{ d.Nama_Jenis }}</span>
                            </span>

                            <!-- Status Badge -->
                            <span v-if="iniSekarang(d)" class="dp-status-pill current" title="Dokumen ini sedang terpasang pada item ini">
                                <i class="bi bi-pin-angle-fill"></i> Terpasang Saat Ini
                            </span>
                            <span v-else-if="sudahDipakai(d)" class="dp-status-pill warn" title="Sudah dipakai pada section/item lain dalam paket ini">
                                <i class="bi bi-exclamation-circle-fill"></i> Sudah Ada di Paket
                            </span>
                            <span v-else-if="!d.Id_Versi_Aktif" class="dp-status-pill muted" title="Belum memiliki versi terbit di Master Dokumen">
                                <i class="bi bi-hourglass-split"></i> Belum Ada Versi
                            </span>
                        </div>
                    </div>

                    <!-- Pemakaian di Paket Lain -->
                    <div class="dp-card-side">
                        <span
                            class="dp-usage-badge"
                            :class="{ 'is-shared': d.Jumlah_Paket_Pemakai > 0 }"
                            :title="d.Jumlah_Paket_Pemakai > 0 ? `Dokumen ini dipakai di ${d.Jumlah_Paket_Pemakai} paket SOP` : 'Baru dipakai di paket ini'"
                        >
                            <i class="bi" :class="d.Jumlah_Paket_Pemakai > 0 ? 'bi-diagram-3-fill' : 'bi-box-seam'"></i>
                            <span>{{ d.Jumlah_Paket_Pemakai ? `${d.Jumlah_Paket_Pemakai} paket` : 'Baru' }}</span>
                        </span>
                    </div>
                </button>
            </div>

            <!-- ── PAGINATION CONTROLS ── -->
            <div v-if="pagination.total_page > 1" class="dp-pagination-bar">
                <div class="dp-pagination-info text-muted small">
                    Halaman <strong>{{ pagination.current_page }}</strong> dari <strong>{{ pagination.total_page }}</strong>
                    <span class="d-none d-sm-inline">({{ pagination.total_data }} dokumen)</span>
                </div>

                <div class="dp-pagination-nav">
                    <button
                        type="button"
                        class="dp-page-btn"
                        :disabled="pagination.current_page <= 1 || loading"
                        title="Halaman sebelumnya"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <template v-for="p in visiblePages" :key="p">
                        <button
                            v-if="p !== '...'"
                            type="button"
                            class="dp-page-btn num"
                            :class="{ 'is-active': p === pagination.current_page }"
                            :disabled="loading"
                            @click="goToPage(p)"
                        >
                            {{ p }}
                        </button>
                        <span v-else class="dp-page-dots">…</span>
                    </template>

                    <button
                        type="button"
                        class="dp-page-btn"
                        :disabled="pagination.current_page >= pagination.total_page || loading"
                        title="Halaman berikutnya"
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- ── RINGKASAN DOKUMEN TERPILIH ── -->
            <div v-if="dokumenTerpilih" class="dp-selected-strip">
                <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                    <div class="dp-selected-icon">
                        <i class="bi bi-check2"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="dp-selected-name text-truncate">
                            {{ dokumenTerpilih.Judul }}
                        </div>
                        <div class="dp-selected-sub text-muted small">
                            <span v-if="dokumenTerpilih.Nomor_SOP_Resmi">{{ dokumenTerpilih.Nomor_SOP_Resmi }} · </span>
                            <span v-if="dokumenTerpilih.Versi_String">v{{ dokumenTerpilih.Versi_String }} · </span>
                            <span v-if="dokumenTerpilih.Jumlah_Paket_Pemakai > 0">
                                Terhubung ke {{ dokumenTerpilih.Jumlah_Paket_Pemakai }} paket lain
                            </span>
                            <span v-else>Mandiri</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="dp-foot">
                <div class="dp-foot-left">
                    <span v-if="dokumenTerpilih" class="dp-foot-selection">
                        <i class="bi bi-check-circle-fill text-primary me-1"></i>
                        1 dokumen siap ditautkan
                    </span>
                    <span v-else class="text-muted small">
                        Pilih salah satu dokumen dari daftar di atas
                    </span>
                </div>

                <div class="dp-foot-actions">
                    <button type="button" class="stitch-btn secondary" @click="$emit('close')">
                        Batal
                    </button>
                    <button
                        type="button"
                        class="stitch-btn primary"
                        :disabled="!terpilih"
                        @click="pakai"
                    >
                        <i class="bi bi-check2"></i> Tautkan Dokumen
                    </button>
                </div>
            </div>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import SopModal from './SopModal.vue';

const API = '/api/v1/sop/admin/builder';

/**
 * Pemilih Dokumen untuk Document Builder.
 * UI/UX Ringkas, Responsif, dengan Server-side Search, Pagination, dan Multi-filter.
 */
export default {
    name: 'SopDokumenPickerModal',
    components: { SopModal },
    props: {
        show: { type: Boolean, default: false },
        tipe: { type: String, default: '' },
        /** Hashid dokumen yang SUDAH dipakai item lain di paket yang sama */
        dipakai: { type: Array, default: () => [] },
        /** Dokumen yang SEDANG dipakai item ini */
        sekarang: { type: String, default: '' },
    },
    emits: ['close', 'pilih'],
    data() {
        return {
            filters: {
                q: '',
                departemen_id: '',
                jenis_id: '',
                status_versi: 'all',
                sort: 'judul_asc',
                page: 1,
                per_page: 8,
            },
            showFilters: false,
            rows: [],
            pagination: {
                current_page: 1,
                per_page: 8,
                total_page: 1,
                total_data: 0,
            },
            departemenOptions: [],
            jenisOptions: [],
            loading: false,
            terpilih: null,
            timer: null,
        };
    },
    computed: {
        dokumenTerpilih() {
            return this.rows.find((d) => d.Id_SOP_Dokumen === this.terpilih) || null;
        },
        hasActiveFilter() {
            return (
                !!this.filters.q ||
                !!this.filters.departemen_id ||
                !!this.filters.jenis_id ||
                this.filters.status_versi !== 'all' ||
                this.filters.sort !== 'judul_asc'
            );
        },
        activeFilterCount() {
            let count = 0;
            if (this.filters.departemen_id) count++;
            if (this.filters.jenis_id) count++;
            if (this.filters.status_versi !== 'all') count++;
            if (this.filters.sort !== 'judul_asc') count++;
            return count;
        },
        visiblePages() {
            const current = this.pagination.current_page;
            const total = this.pagination.total_page;
            if (total <= 5) {
                return Array.from({ length: total }, (_, i) => i + 1);
            }
            if (current <= 3) {
                return [1, 2, 3, 4, '...', total];
            }
            if (current >= total - 2) {
                return [1, '...', total - 3, total - 2, total - 1, total];
            }
            return [1, '...', current - 1, current, current + 1, '...', total];
        },
    },
    watch: {
        show(v) {
            if (v) {
                this.terpilih = this.sekarang || null;
                this.filters.page = 1;
                this.muat();
            }
        },
    },
    methods: {
        tipeIcon(t) {
            return (
                {
                    DOKUMEN: 'bi-file-earmark-pdf',
                    FORM: 'bi-ui-checks-grid',
                    VIDEO: 'bi-play-circle',
                    KUIS: 'bi-patch-question',
                    TAUTAN: 'bi-link-45deg',
                    PARAGRAF: 'bi-text-paragraph',
                }[t] || 'bi-journal-text'
            );
        },
        siap(d) {
            return !!d?.Id_Versi_Aktif && !this.sudahDipakai(d) && !this.iniSekarang(d);
        },
        iniSekarang(d) {
            return !!this.sekarang && d?.Id_SOP_Dokumen === this.sekarang;
        },
        sudahDipakai(d) {
            return this.dipakai.includes(d?.Id_SOP_Dokumen);
        },
        cariDebounce() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.filters.page = 1;
                this.muat();
            }, 250);
        },
        clearSearch() {
            this.filters.q = '';
            this.filters.page = 1;
            this.muat();
        },
        onFilterChange() {
            this.filters.page = 1;
            this.muat();
        },
        setQuickStatus(status) {
            if (this.filters.status_versi === status) return;
            this.filters.status_versi = status;
            this.filters.page = 1;
            this.muat();
        },
        resetFilters() {
            this.filters.q = '';
            this.filters.departemen_id = '';
            this.filters.jenis_id = '';
            this.filters.status_versi = 'all';
            this.filters.sort = 'judul_asc';
            this.filters.page = 1;
            this.muat();
        },
        goToPage(page) {
            if (page < 1 || page > this.pagination.total_page || page === this.pagination.current_page) return;
            this.filters.page = page;
            this.muat();
        },
        async muat() {
            this.loading = true;
            try {
                const params = {
                    q: this.filters.q || undefined,
                    tipe: this.tipe || undefined,
                    departemen_id: this.filters.departemen_id || undefined,
                    jenis_id: this.filters.jenis_id || undefined,
                    status_versi: this.filters.status_versi !== 'all' ? this.filters.status_versi : undefined,
                    sort: this.filters.sort || undefined,
                    page: this.filters.page || 1,
                    per_page: this.filters.per_page || 8,
                };
                const r = await axios.get(`${API}/dokumen/options`, { params });
                const res = r.data?.result || {};
                this.rows = res.rows || [];
                if (res.pagination) {
                    this.pagination = res.pagination;
                } else {
                    this.pagination.total_data = res.total || this.rows.length;
                    this.pagination.total_page = Math.ceil(this.pagination.total_data / this.filters.per_page) || 1;
                    this.pagination.current_page = this.filters.page;
                }
                if (res.options?.departemen) {
                    this.departemenOptions = res.options.departemen;
                }
                if (res.options?.jenis) {
                    this.jenisOptions = res.options.jenis;
                }
            } catch (e) {
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },
        pakai() {
            if (!this.dokumenTerpilih) return;
            this.$emit('pilih', this.dokumenTerpilih);
            this.$emit('close');
        },
        pilihLalu(d) {
            if (!this.siap(d)) return;
            this.terpilih = d.Id_SOP_Dokumen;
            this.pakai();
        },
    },
};
</script>

<style scoped>
.dp-stack {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* ── BARIS PENCARIAN & AKSI ─────────────────────────────────────── */
.dp-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.dp-search-box {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.dp-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
    pointer-events: none;
}

.dp-search-input {
    width: 100%;
    padding-left: 2.3rem;
    padding-right: 2.2rem;
    height: 2.45rem;
    border-radius: 10px;
    font-size: 0.84rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.16s ease;
}

.dp-search-input:focus {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.dp-search-clear {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    color: var(--hcis-slate-400);
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.14s ease;
}

.dp-search-clear:hover {
    color: var(--hcis-slate-500);
}

.dp-header-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.dp-filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.75rem;
    height: 2.45rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.16s ease;
}

.dp-filter-toggle-btn:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
}

.dp-filter-toggle-btn.is-active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
}

.dp-filter-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background: var(--hcis-indigo-500);
    color: var(--hcis-white);
    font-size: 0.65rem;
    font-weight: 800;
}

.dp-type-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    padding: 0.42rem 0.75rem;
    height: 2.45rem;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.18);
}

/* ── EXPANDABLE FILTER PANEL ────────────────────────────────────── */
.dp-filter-panel {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.85rem;
    animation: sopFadeIn 0.18s ease-out;
}

.dp-filter-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* ── QUICK STATUS PILLS ─────────────────────────────────────────── */
.dp-quick-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.15rem;
}

.dp-quick-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    border: 1px solid transparent;
    color: var(--hcis-slate-500);
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.14s ease;
}

.dp-quick-pill:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-700);
}

.dp-quick-pill.active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
    font-weight: 700;
}

.dp-pill-count {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.06);
    font-size: 0.68rem;
}

/* ── DAFTAR DOKUMEN ──────────────────────────────────────────────── */
.dp-list {
    display: flex;
    flex-direction: column;
    gap: 0.42rem;
    min-height: 240px;
    max-height: min(48vh, 25rem);
    overflow-y: auto;
    padding-right: 0.25rem;
    margin-right: -0.25rem;
}

.dp-list::-webkit-scrollbar {
    width: 6px;
}
.dp-list::-webkit-scrollbar-track {
    background: transparent;
}
.dp-list::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 999px;
}
.dp-list::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

/* ── CARD ITEM ───────────────────────────────────────────────────── */
.dp-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    text-align: left;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.65rem 0.85rem;
    cursor: pointer;
    transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.dp-card:hover:not(.is-disabled) {
    border-color: var(--hcis-indigo-300);
    background: #fbfdff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.08);
}

.dp-card.is-selected {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.04);
    box-shadow: 0 0 0 1px var(--hcis-indigo-500), 0 4px 12px -2px rgba(99, 102, 241, 0.12);
}

.dp-card.is-disabled {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-200);
    cursor: not-allowed;
    opacity: 0.7;
}

.dp-card.is-current {
    border-color: var(--hcis-indigo-200);
    background: #fafbff;
    opacity: 0.9;
}

/* ── RADIO INDICATOR ─────────────────────────────────────────────── */
.dp-radio-indicator {
    flex-shrink: 0;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    display: grid;
    place-items: center;
    color: var(--hcis-white);
    font-size: 0.75rem;
    transition: all 0.15s ease;
}

.dp-card.is-selected .dp-radio-indicator {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-500);
}

.dp-card.is-selected .dp-radio-indicator i {
    transform: scale(1);
    opacity: 1;
}

.dp-radio-indicator i {
    transform: scale(0.5);
    opacity: 0;
    transition: all 0.15s ease;
}

/* ── CONTENT BODY ────────────────────────────────────────────────── */
.dp-card-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.dp-card-title {
    font-size: 0.87rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    line-height: 1.3;
}

.dp-card.is-selected .dp-card-title {
    color: var(--hcis-indigo-800);
}

.dp-card-meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.dp-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.71rem;
    font-weight: 500;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 0.18rem 0.45rem;
    border-radius: 6px;
    line-height: 1.1;
}

.dp-meta-pill.code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-weight: 600;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-200);
}

.dp-meta-pill.versi {
    font-weight: 600;
    color: #0f766e;
    background: #ccfbf1;
}

.dp-meta-pill.dept {
    max-width: 140px;
}

.dp-meta-pill.jenis {
    max-width: 130px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.dp-meta-pill.muted {
    font-style: italic;
    color: var(--hcis-slate-400);
    background: transparent;
}

.dp-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.18rem 0.45rem;
    border-radius: 6px;
    line-height: 1.1;
}

.dp-status-pill.warn {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
}

.dp-status-pill.current {
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
}

.dp-status-pill.muted {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}

/* ── SIDE USAGE ──────────────────────────────────────────────────── */
.dp-card-side {
    flex-shrink: 0;
}

.dp-usage-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.71rem;
    font-weight: 600;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
    white-space: nowrap;
}

.dp-usage-badge.is-shared {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #dbeafe;
}

/* ── PAGINATION BAR ──────────────────────────────────────────────── */
.dp-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.5rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    flex-wrap: wrap;
}

.dp-pagination-nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.dp-page-btn {
    min-width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
    font-size: 0.78rem;
    font-weight: 600;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: all 0.14s ease;
    padding: 0 0.4rem;
}

.dp-page-btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-800);
}

.dp-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.dp-page-btn.num.is-active {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-white);
}

.dp-page-dots {
    font-size: 0.8rem;
    color: var(--hcis-slate-400);
    padding: 0 0.2rem;
}

/* ── SELECTED STRIP ──────────────────────────────────────────────── */
.dp-selected-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.55rem 0.8rem;
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-green-200);
    border-radius: 10px;
    animation: sopFadeIn 0.18s ease-out;
}

.dp-selected-icon {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--hcis-green-600);
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.dp-selected-name {
    font-size: 0.83rem;
    font-weight: 700;
    color: var(--hcis-green-900);
    line-height: 1.2;
}

.dp-selected-sub {
    font-size: 0.72rem;
    line-height: 1.2;
}

/* ── EMPTY STATE ─────────────────────────────────────────────────── */
.dp-empty-state {
    text-align: center;
    padding: 2.2rem 1.2rem;
}

.dp-empty-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
    display: grid;
    place-items: center;
    font-size: 1.5rem;
    margin: 0 auto 0.75rem;
}

.dp-empty-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    margin-bottom: 0.35rem;
}

.dp-empty-desc {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    max-width: 28rem;
    margin: 0 auto;
    line-height: 1.45;
}

/* ── SKELETON ────────────────────────────────────────────────────── */
.dp-card-skel {
    pointer-events: none;
}

.dp-skel-title {
    height: 0.85rem;
    width: 65%;
    border-radius: 6px;
}

.dp-skel-pill {
    height: 0.7rem;
    width: 90px;
    border-radius: 6px;
}

.dp-skel-pill-sm {
    height: 0.7rem;
    width: 60px;
    border-radius: 6px;
}

.dp-skel-badge {
    height: 1.2rem;
    width: 55px;
    border-radius: 999px;
}

/* ── FOOTER ──────────────────────────────────────────────────────── */
.dp-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
}

.dp-foot-selection {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-indigo-700);
}

.dp-foot-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@keyframes sopFadeIn {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dp-expand-enter-active,
.dp-expand-leave-active {
    transition: all 0.2s ease-out;
}
.dp-expand-enter-from,
.dp-expand-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 575.98px) {
    .dp-foot-left {
        display: none;
    }
    .dp-foot-actions {
        width: 100%;
    }
    .dp-foot-actions .stitch-btn {
        flex: 1;
    }
}
</style>
