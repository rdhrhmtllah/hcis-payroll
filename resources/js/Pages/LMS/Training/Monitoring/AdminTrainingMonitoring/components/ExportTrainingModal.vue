<template>
    <Modal
        :show="show"
        title="Export Data Training"
        subtitle="Pilih karyawan & training yang ingin di-export, lalu pilih format."
        size="fullscreen"
        body-class="export-modal-body"
        @close="$emit('close')"
    >
        <template #body>
            <div class="export-wrap">
                <!-- ===== Filter bar ===== -->
                <div class="export-filters">
                    <div class="filter-header-row">
                        <span class="filter-title"><i class="bi bi-funnel"></i> Filter Data</span>
                        <div class="filter-header-actions">
                            <button
                                class="btn-toggle-advanced"
                                type="button"
                                @click="showAdvancedFilters = !showAdvancedFilters"
                            >
                                <i class="bi" :class="showAdvancedFilters ? 'bi-chevron-up' : 'bi-sliders'"></i>
                                {{ showAdvancedFilters ? 'Sembunyikan Filter Lanjutan' : 'Tampilkan Filter Lanjutan' }}
                            </button>
                            <button
                                v-if="hasActiveFilters"
                                class="filter-clear-all"
                                @click="resetFilters"
                                title="Hapus semua filter"
                            >
                                <i class="bi bi-x-circle"></i> Clear All
                            </button>
                        </div>
                    </div>

                    <FilterPanel
                        :config="filterConfig"
                        :filters="filters"
                        :results-count="pagination.total"
                        @filters-update="handleFiltersUpdate"
                    />
                </div>

                <!-- ===== Quick select all ===== -->
                <div class="quick-select" v-if="employees.length && !loading">
                    <button
                        class="quick-btn"
                        @click="selectAllAcrossPages"
                        :disabled="allAcrossSelected || selectingAll"
                        type="button"
                    >
                        <i class="bi" :class="selectingAll ? 'bi-arrow-repeat spin' : 'bi-check-all'"></i>
                        {{ selectingAll ? 'Memilih...' : `Pilih Semua Karyawan (${pagination.total})` }}
                    </button>
                    <button class="quick-btn" @click="deselectAll" v-if="selectedEmployeeCount > 0" type="button">
                        <i class="bi bi-x-circle"></i>
                        Hapus Semua Pilihan
                    </button>
                </div>

                <!-- ===== Employee list ===== -->
                <div class="export-list" ref="listContainer">
                    <!-- Loading State -->
                    <div v-if="loading" class="el-loading">
                        <div class="loading-animation">
                            <div class="loading-spinner"></div>
                        </div>
                        <div class="loading-text">Memuat data karyawan...</div>
                        <div class="loading-sub">Mohon tunggu sebentar</div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!employees.length" class="el-empty">
                        <div class="empty-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <div class="empty-text">Tidak ada karyawan sesuai filter</div>
                        <div class="empty-sub">Coba ubah atau hapus filter yang diterapkan</div>
                        <button class="empty-btn" @click="resetFilters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset Filter
                        </button>
                    </div>

                    <!-- Employee Grid -->
                    <template v-else>
                        <div class="employee-grid">
                            <div v-for="(col, colIdx) in gridColumns" :key="colIdx" class="grid-column">
                                <div
                                    v-for="emp in col"
                                    :key="emp.kode_karyawan"
                                    :data-employee="emp.kode_karyawan"
                                    class="el-card"
                                    :class="{
                                        'is-expanded': expanded[emp.kode_karyawan],
                                        'is-selected': isEmployeeChecked(emp) || isEmployeeIndeterminate(emp),
                                    }"
                                >
                                    <div class="el-body" @click="handleBodyClick(emp)">
                                        <div class="el-profile-wrap">
                                            <label class="el-check" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    :checked="isEmployeeChecked(emp)"
                                                    :indeterminate.prop="isEmployeeIndeterminate(emp)"
                                                    @change="toggleEmployee(emp, $event.target.checked)"
                                                />
                                                <span class="checkmark"></span>
                                            </label>

                                            <div class="el-profile">
                                                <div
                                                    class="el-avatar"
                                                    :style="{ background: getAvatarColor(emp.nama) }"
                                                >
                                                    {{ initials(emp.nama) }}
                                                </div>

                                                <div class="el-identity">
                                                    <div class="el-name-row">
                                                        <span class="el-name" :title="emp.nama">{{ emp.nama }}</span>
                                                        <span
                                                            class="el-badge"
                                                            :title="'Kode Karyawan: ' + emp.kode_karyawan"
                                                            >{{ emp.kode_karyawan }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="el-job-title"
                                                        v-if="emp.jabatan && emp.jabatan !== '-'"
                                                        :title="emp.jabatan"
                                                    >
                                                        {{ emp.jabatan }}
                                                    </div>
                                                    <div
                                                        class="el-dept-div"
                                                        v-if="getDeptDivText(emp)"
                                                        :title="getDeptDivText(emp)"
                                                    >
                                                        <i class="bi bi-building"></i>
                                                        <span>{{ getDeptDivText(emp) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Training detail (inside el-body, below profile row) -->
                                        <transition name="expand">
                                            <div v-if="expanded[emp.kode_karyawan]" class="el-trainings" @click.stop>
                                                <div v-if="trainingLoading[emp.kode_karyawan]" class="el-tloading">
                                                    <div class="loading-skeleton">
                                                        <div class="skeleton-item" v-for="n in 3" :key="n">
                                                            <div class="skeleton-line w-70"></div>
                                                            <div class="skeleton-line w-30"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    v-else-if="!(trainingCache[emp.kode_karyawan] || []).length"
                                                    class="el-tempty"
                                                >
                                                    <i class="bi bi-journal-x"></i>
                                                    <span>Tidak ada training untuk karyawan ini</span>
                                                </div>

                                                <div v-else class="training-list">
                                                    <label
                                                        v-for="t in trainingCache[emp.kode_karyawan]"
                                                        :key="t.id_penjadwalan"
                                                        class="el-titem"
                                                        :class="{ 'is-checked': isTrainingChecked(emp, t) }"
                                                    >
                                                        <div class="titem-check">
                                                            <input
                                                                type="checkbox"
                                                                :checked="isTrainingChecked(emp, t)"
                                                                @change="toggleTraining(emp, t, $event.target.checked)"
                                                            />
                                                            <span class="checkmark sm"></span>
                                                        </div>

                                                        <div class="titem-content">
                                                            <div class="titem-main">
                                                                <span class="el-tjudul">{{ t.judul }}</span>
                                                                <span
                                                                    class="el-tstatus"
                                                                    :class="
                                                                        t.status === 'completed' ? 's-done' : 's-prog'
                                                                    "
                                                                >
                                                                    <i
                                                                        :class="
                                                                            t.status === 'completed'
                                                                                ? 'bi bi-check-circle-fill'
                                                                                : 'bi bi-arrow-repeat'
                                                                        "
                                                                    ></i>
                                                                    {{
                                                                        t.status === 'completed'
                                                                            ? 'Selesai'
                                                                            : 'Berlangsung'
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div class="titem-meta">
                                                                <span class="el-tdate">
                                                                    <i class="bi bi-calendar3"></i>
                                                                    {{ formatDate(t.tanggal_enroll) }}
                                                                </span>
                                                                <span class="el-tkat">
                                                                    <i class="bi bi-tag"></i>
                                                                    {{ t.kategori }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </transition>
                                    </div>

                                    <div class="el-footer" @click="toggleExpand(emp)">
                                        <div class="el-counts">
                                            <span class="stat-badge in-progress" title="Training Berlangsung">
                                                <i class="bi bi-hourglass-split"></i>
                                                <span class="stat-lbl">Berlangsung</span>
                                                <strong class="stat-val">{{
                                                    countByStatus(emp, 'in-progress')
                                                }}</strong>
                                            </span>
                                            <span class="stat-badge completed" title="Training Selesai">
                                                <i class="bi bi-check-circle"></i>
                                                <span class="stat-lbl">Selesai</span>
                                                <strong class="stat-val">{{ countByStatus(emp, 'completed') }}</strong>
                                            </span>
                                        </div>

                                        <button
                                            class="el-expand-btn"
                                            type="button"
                                            :title="expanded[emp.kode_karyawan] ? 'Tutup detail' : 'Lihat detail'"
                                        >
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pagination.total_pages > 1" class="el-pager">
                            <div class="pager-info">
                                Menampilkan {{ (pagination.current_page - 1) * limit + 1 }}-{{
                                    Math.min(pagination.current_page * limit, pagination.total)
                                }}
                                dari {{ pagination.total }} data
                            </div>
                            <div class="pager-controls">
                                <button
                                    class="pager-btn"
                                    :disabled="pagination.current_page <= 1"
                                    @click="goPage(1)"
                                    title="Halaman pertama"
                                >
                                    <i class="bi bi-chevron-double-left"></i>
                                </button>
                                <button
                                    class="pager-btn"
                                    :disabled="pagination.current_page <= 1"
                                    @click="goPage(pagination.current_page - 1)"
                                >
                                    <i class="bi bi-chevron-left"></i>
                                </button>

                                <div class="pager-pages">
                                    <button
                                        v-for="page in getPageNumbers()"
                                        :key="page"
                                        class="pager-num"
                                        :class="{ active: page === pagination.current_page, dots: page === '...' }"
                                        :disabled="page === '...'"
                                        @click="page !== '...' && goPage(page)"
                                    >
                                        {{ page }}
                                    </button>
                                </div>

                                <button
                                    class="pager-btn"
                                    :disabled="pagination.current_page >= pagination.total_pages"
                                    @click="goPage(pagination.current_page + 1)"
                                >
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                                <button
                                    class="pager-btn"
                                    :disabled="pagination.current_page >= pagination.total_pages"
                                    @click="goPage(pagination.total_pages)"
                                    title="Halaman terakhir"
                                >
                                    <i class="bi bi-chevron-double-right"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ===== Selection summary (Sticky Footer) ===== -->
                <transition name="slide-up">
                    <div v-if="totalSelectedTrainings > 0" class="export-summary-sticky">
                        <div class="es-info">
                            <div class="es-icon-wrap">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div class="es-details">
                                <div class="es-main">
                                    <strong>{{ totalSelectedTrainings }}</strong> training terpilih dari
                                    <strong>{{ selectedEmployeeCount }}</strong> karyawan
                                </div>
                                <div class="es-sub" v-if="selectedEmployeeCount > 0">
                                    {{ getSelectedSummary() }}
                                </div>
                            </div>
                        </div>
                        <div class="es-actions">
                            <label class="es-format">
                                <i class="bi bi-tag"></i>
                                Nama
                                <input
                                    v-model="exportNama"
                                    type="text"
                                    maxlength="120"
                                    class="ef-select sm"
                                    placeholder="opsional"
                                />
                            </label>
                            <label class="es-format">
                                <i class="bi bi-file-earmark"></i>
                                Format
                                <select v-model="format" class="ef-select sm">
                                    <option value="excel">Excel (.xlsx)</option>
                                    <option value="csv">CSV (.csv)</option>
                                    <option value="pdf">PDF (.pdf)</option>
                                </select>
                            </label>
                            <button
                                class="es-export-btn"
                                type="button"
                                :disabled="totalSelectedTrainings === 0 || requesting"
                                @click="submitExport"
                            >
                                <span v-if="requesting" class="spin-mini"></span>
                                <i v-else class="bi bi-cloud-download"></i>
                                {{ requesting ? 'Menyiapkan...' : 'Export Data' }}
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '@/components/legacy/modalV2-noir.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';
import axios from 'axios';
import { ElMessage, ElNotification } from 'element-plus';
import 'element-plus/dist/index.css';
import { useActivityCenter } from '../../../../../../composables/useActivityCenter.js';

const BASE = '/lms/admin/monitoring';

export default {
    name: 'ExportTrainingModal',
    components: { Modal, FilterPanel },
    props: {
        show: { type: Boolean, default: false },
    },
    emits: ['close', 'dispatched'],
    data() {
        return {
            loading: false,
            requesting: false,
            format: 'excel',
            exportNama: '', // label opsional yang tampil di panel aktivitas
            showAdvancedFilters: false,

            filters: {
                search: '',
                lokasi: [],
                dept: [],
                divisi: [],
                level: [],
                jabatan: [],
                kategori: [],
                nama_training: [],
                periode: null,
                periode_mode: 'enroll',
            },
            options: { lokasi: [], departemen: [], divisi: [], level: [], jabatan: [], kategori: [] },
            trainingOptions: [],
            trainingTotal: 0,
            trainingTruncated: false,
            trainingSearchLoading: false,
            trainingSearchSeq: 0,
            trainingSearchTimer: null,

            employees: [],
            pagination: { current_page: 1, total_pages: 1, total: 0 },
            limit: 15,

            expanded: {},
            trainingCache: {},
            trainingLoading: {},

            selection: {},

            // UI States
            selectAllMode: false,
            selectingAll: false,
            avatarColors: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'],
            windowWidth: typeof window !== 'undefined' ? window.innerWidth : 1200,
        };
    },
    computed: {
        /**
         * Opsi dropdown training. Hasil pencarian server digabung dengan nilai
         * yang sudah terpilih supaya pilihan lama tidak hilang ketika daftar
         * opsi berganti mengikuti kata kunci baru.
         */
        trainingSelectOptions() {
            const fetched = this.trainingOptions || [];
            const terpilih = (this.filters.nama_training || []).filter((val) => !fetched.includes(val));
            const opsi = [...fetched, ...terpilih].map((val) => ({ value: val, label: val }));

            if (this.trainingTruncated) {
                const sisa = Math.max(this.trainingTotal - fetched.length, 0);
                opsi.push({
                    value: '__training_lainnya__',
                    label: `+${sisa} training lain — ketik untuk mencari`,
                    disabled: true,
                });
            }

            return opsi;
        },

        filterConfig() {
            const namaTrainingFilter = {
                key: 'nama_training',
                label: 'Nama Training',
                type: 'select',
                placeholder: 'Pilih satu atau beberapa training...',
                filterable: true,
                clearable: true,
                multiple: true,
                maxCollapseTags: 2,
                size: 'default',
                layout: 'top',
                // Pencarian dilakukan di server: daftar training melebihi batas
                // yang dikirim sekali jalan, jadi menyaring di browser saja akan
                // menyembunyikan training di luar potongan pertama.
                remote: true,
                remoteMethod: this.onTrainingSearch,
                loading: this.trainingSearchLoading,
                options: this.trainingSelectOptions,
            };

            const basicFilters = [namaTrainingFilter];

            const advancedFilters = [
                namaTrainingFilter,
                // Baris 1: atribut karyawan
                {
                    key: 'lokasi',
                    label: 'Lokasi Kerja',
                    type: 'select',
                    placeholder: 'Semua Lokasi',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.lokasi || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                {
                    key: 'dept',
                    label: 'Departemen',
                    type: 'select',
                    placeholder: 'Semua Departemen',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.departemen || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                {
                    key: 'divisi',
                    label: 'Divisi',
                    type: 'select',
                    placeholder: 'Semua Divisi',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.divisi || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                // Baris 2: atribut karyawan + kategori
                {
                    key: 'level',
                    label: 'Level',
                    type: 'select',
                    placeholder: 'Semua Level',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.level || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                {
                    key: 'jabatan',
                    label: 'Jabatan',
                    type: 'select',
                    placeholder: 'Semua Jabatan',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.jabatan || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                {
                    key: 'kategori',
                    label: 'Kategori Training',
                    type: 'select',
                    placeholder: 'Semua Kategori',
                    filterable: true,
                    clearable: true,
                    multiple: true,
                    maxCollapseTags: 1,
                    size: 'default',
                    options: (this.options.kategori || []).map((val) => ({ value: val, label: val })),
                    col: 4,
                },
                // Baris 3: periode (mode + rentang tanggal yang lebih lebar)
                {
                    key: 'periode_mode',
                    label: 'Mode Tanggal',
                    type: 'select',
                    placeholder: 'Mode Tanggal',
                    clearable: false,
                    size: 'default',
                    options: [
                        { value: 'enroll', label: 'Tanggal Enroll' },
                        { value: 'created', label: 'Tanggal Ditugaskan' },
                    ],
                    col: 4,
                },
                {
                    key: 'periode',
                    label: 'Periode',
                    type: 'daterange',
                    dateType: 'daterange',
                    placeholder: 'Pilih Periode',
                    clearable: true,
                    size: 'default',
                    valueFormat: 'YYYY-MM-DD',
                    col: 8,
                },
            ];

            return {
                searchable: true,
                searchPlaceholder: 'Cari nama / kode karyawan...',
                showResultsCount: false,
                showActiveFilters: false,
                showActions: false,
                showReset: false,
                showClearAll: false,
                mobileTrigger: false,
                filters: this.showAdvancedFilters ? advancedFilters : basicFilters,
            };
        },
        selectedEmployeeCount() {
            return Object.values(this.selection).filter((s) => s && s.size > 0).length;
        },
        totalSelectedTrainings() {
            return Object.values(this.selection).reduce((acc, s) => acc + (s ? s.size : 0), 0);
        },
        hasActiveFilters() {
            return Object.entries(this.filters).some(([key, val]) => {
                if (key === 'periode_mode') return false; // mode selector, bukan filter aktif
                if (Array.isArray(val)) return val.length > 0; // multi-select & periode
                return val !== '' && val !== null && val !== undefined;
            });
        },
        allVisibleSelected() {
            if (!this.employees.length) return false;
            return this.employees.every((emp) => this.isEmployeeChecked(emp));
        },
        allAcrossSelected() {
            return this.pagination.total > 0 && this.selectedEmployeeCount >= this.pagination.total;
        },
        hasTrainingFilter() {
            return (
                this.filters.nama_training?.length > 0 ||
                this.filters.kategori?.length > 0 ||
                (Array.isArray(this.filters.periode) && this.filters.periode.length > 0)
            );
        },
        columnCount() {
            if (this.windowWidth >= 1025) return 3;
            if (this.windowWidth >= 768) return 2;
            return 1;
        },
        gridColumns() {
            const count = this.columnCount;
            const cols = Array.from({ length: count }, () => []);
            this.employees.forEach((emp, index) => {
                cols[index % count].push(emp);
            });
            return cols;
        },
    },
    watch: {
        show(val) {
            if (val) {
                this.init();
                this.$nextTick(() => {
                    // Focus on search when modal opens
                    const searchInput = this.$el.querySelector('.search-input-enhanced input');
                    if (searchInput) searchInput.focus();
                });
            } else {
                this.cleanup();
            }
        },
    },
    mounted() {
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.handleResize);
        }
    },
    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.handleResize);
        }
        this.cleanup();
    },
    methods: {
        handleResize() {
            if (typeof window !== 'undefined') {
                this.windowWidth = window.innerWidth;
            }
        },

        getDeptDivText(emp) {
            const dept = emp.departemen ? emp.departemen.trim() : '';
            const div = emp.divisi ? emp.divisi.trim() : '';

            if (dept === '-' && div === '-') return '';
            if (dept === '' && div === '') return '';
            if (dept.toLowerCase() === div.toLowerCase()) {
                return dept === '-' ? '' : dept;
            }
            const cleanDept = dept === '-' ? '' : dept;
            const cleanDiv = div === '-' ? '' : div;
            if (cleanDept && cleanDiv) {
                return `${cleanDept} • ${cleanDiv}`;
            }
            return cleanDept || cleanDiv;
        },

        cleanup() {
            clearTimeout(this.trainingSearchTimer);
            this.trainingSearchTimer = null;
        },

        async init() {
            this.loading = true;
            this.selection = {};
            this.expanded = {};
            this.trainingCache = {};
            this.showAdvancedFilters = false;
            await Promise.all([this.fetchOptions(), this.fetchEmployees(1)]);
        },

        async fetchOptions() {
            try {
                const { data } = await axios.get(`${BASE}/filter-options`);
                if (data.success) {
                    this.options = {
                        lokasi: data.result.lokasi || [],
                        departemen: data.result.departemen || [],
                        divisi: data.result.divisi || [],
                        level: data.result.level || [],
                        jabatan: data.result.jabatan || [],
                        kategori: data.result.kategori || [],
                    };
                }
            } catch (e) {
                console.error('Failed to fetch options:', e);
            }

            await this.fetchTrainings('');
        },

        onTrainingSearch(query) {
            clearTimeout(this.trainingSearchTimer);
            this.trainingSearchTimer = setTimeout(() => {
                this.fetchTrainings(query || '');
            }, 300);
        },

        async fetchTrainings(q) {
            // Balasan bisa datang tidak berurutan; hanya yang terbaru yang dipakai.
            const seq = ++this.trainingSearchSeq;
            this.trainingSearchLoading = true;

            try {
                const { data } = await axios.get(`${BASE}/export/trainings/search`, { params: { q } });
                if (seq !== this.trainingSearchSeq) return;

                if (data.success) {
                    const hasil = data.result || {};
                    this.trainingOptions = hasil.items || [];
                    this.trainingTotal = hasil.total || 0;
                    this.trainingTruncated = !!hasil.truncated;
                }
            } catch (e) {
                if (seq === this.trainingSearchSeq) console.error('Failed to search trainings:', e);
            } finally {
                if (seq === this.trainingSearchSeq) this.trainingSearchLoading = false;
            }
        },

        queryParams(page) {
            const start = this.filters.periode?.[0] || '';
            const end = this.filters.periode?.[1] || '';
            return {
                page,
                limit: this.limit,
                q: this.filters.search || '',
                lokasi: this.filters.lokasi,
                dept: this.filters.dept,
                divisi: this.filters.divisi,
                level: this.filters.level,
                jabatan: this.filters.jabatan,
                kategori: this.filters.kategori,
                nama_training: this.filters.nama_training,
                periode_start: start,
                periode_end: end,
                periode_mode: this.filters.periode_mode || 'enroll',
            };
        },

        async fetchEmployees(page = 1) {
            this.loading = true;
            try {
                const { data } = await axios.get(`${BASE}/export/employees`, {
                    params: this.queryParams(page),
                });
                if (data.success) {
                    this.employees = data.result || [];
                    this.pagination = {
                        current_page: data.pagination?.current_page || page,
                        total_pages: data.pagination?.total_pages || 1,
                        total: data.pagination?.total || 0,
                    };
                }
            } catch (e) {
                ElMessage.error('Gagal memuat data karyawan.');
            } finally {
                this.loading = false;
            }
        },

        handleFiltersUpdate(newFilters) {
            this.filters = { ...newFilters };
            // Pertahankan pilihan karyawan/training yang sudah dipilih saat filter/search berubah.
            // Selection di-key oleh kode_karyawan sehingga aman meski daftar yang tampil berubah.
            this.expanded = {};
            this.trainingCache = {};
            this.fetchEmployees(1);
        },

        resetFilters() {
            this.filters = {
                search: '',
                lokasi: [],
                dept: [],
                divisi: [],
                level: [],
                jabatan: [],
                kategori: [],
                nama_training: [],
                periode: null,
                periode_mode: 'enroll',
            };
            this.showAdvancedFilters = false;
            this.selection = {};
            this.expanded = {};
            this.trainingCache = {};
            this.fetchEmployees(1);
        },

        goPage(p) {
            if (p >= 1 && p <= this.pagination.total_pages) {
                const currentSelection = { ...this.selection };
                this.fetchEmployees(p).then(() => {
                    this.selection = currentSelection;
                });
            }
        },

        getPageNumbers() {
            const pages = [];
            const total = this.pagination.total_pages;
            const current = this.pagination.current_page;

            if (total <= 7) {
                for (let i = 1; i <= total; i++) pages.push(i);
            } else {
                pages.push(1);
                if (current > 3) pages.push('...');

                for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
                    pages.push(i);
                }

                if (current < total - 2) pages.push('...');
                pages.push(total);
            }

            return pages;
        },

        async toggleExpand(emp) {
            const k = emp.kode_karyawan;
            const wasExpanded = this.expanded[k];
            this.expanded = { ...this.expanded, [k]: !this.expanded[k] };

            if (!wasExpanded && !this.trainingCache[k]) {
                await this.loadTrainings(emp);
            }

            if (!wasExpanded) {
                this.$nextTick(() => {
                    const card = this.$el.querySelector(`[data-employee="${k}"]`);
                    if (card) {
                        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            }
        },

        handleBodyClick(emp) {
            const isChecked = this.isEmployeeChecked(emp);
            this.toggleEmployee(emp, !isChecked);
        },

        async loadTrainings(emp) {
            const k = emp.kode_karyawan;
            this.trainingLoading = { ...this.trainingLoading, [k]: true };
            try {
                const { data } = await axios.get(`${BASE}/export/employees/${encodeURIComponent(k)}/trainings`, {
                    params: this.queryParams(1),
                });
                if (data.success) {
                    this.trainingCache = {
                        ...this.trainingCache,
                        [k]: data.result.trainings || [],
                    };
                }
            } catch (e) {
                this.trainingCache = { ...this.trainingCache, [k]: [] };
                ElMessage.error('Gagal memuat data training.');
            } finally {
                this.trainingLoading = { ...this.trainingLoading, [k]: false };
            }
        },

        // Pilih SEMUA karyawan yang lolos filter, lintas halaman (bukan hanya halaman
        // yang sedang tampil). Mengambil seluruh Id_Penjadwalan dari server sesuai filter.
        async selectAllAcrossPages() {
            this.selectingAll = true;
            try {
                const { data } = await axios.get(`${BASE}/export/selection-ids`, {
                    params: this.queryParams(1),
                });
                if (data.success && data.result) {
                    const sel = {};
                    Object.entries(data.result).forEach(([kode, ids]) => {
                        sel[kode] = new Set(ids || []);
                    });
                    this.selection = sel;

                    const empCount = Object.keys(sel).length;
                    const trainingCount = Object.values(sel).reduce((acc, s) => acc + s.size, 0);
                    if (empCount === 0) {
                        ElMessage.warning('Tidak ada karyawan yang cocok dengan filter saat ini.');
                    } else {
                        ElMessage.success(`${empCount} karyawan (${trainingCount} training) terpilih.`);
                    }
                }
            } catch (e) {
                if (e?.response?.status === 422 && e.response.data?.over_limit) {
                    ElMessage.warning(e.response.data.message);
                } else {
                    ElMessage.error('Gagal memilih semua karyawan.');
                }
            } finally {
                this.selectingAll = false;
            }
        },

        deselectAll() {
            this.selection = {};
        },

        getSelectedSummary() {
            const total = this.totalSelectedTrainings;
            const employees = this.selectedEmployeeCount;
            return `${employees} karyawan, ${total} training`;
        },

        empSet(emp) {
            const k = emp.kode_karyawan;
            if (!this.selection[k]) {
                this.selection = { ...this.selection, [k]: new Set() };
            }
            return this.selection[k];
        },

        allIds(emp) {
            return (emp.penjadwalan_ids || []).slice();
        },

        isEmployeeChecked(emp) {
            const s = this.selection[emp.kode_karyawan];
            const total = this.allIds(emp).length;
            return total > 0 && s && s.size === total;
        },

        isEmployeeIndeterminate(emp) {
            const s = this.selection[emp.kode_karyawan];
            const total = this.allIds(emp).length;
            return s && s.size > 0 && s.size < total;
        },

        toggleEmployee(emp, checked) {
            const k = emp.kode_karyawan;
            const set = new Set();
            if (checked) {
                this.allIds(emp).forEach((id) => set.add(id));
            }
            this.selection = { ...this.selection, [k]: set };
        },

        isTrainingChecked(emp, t) {
            const s = this.selection[emp.kode_karyawan];
            return s ? s.has(t.id_penjadwalan) : false;
        },

        toggleTraining(emp, t, checked) {
            const k = emp.kode_karyawan;
            const set = new Set(this.selection[k] ? Array.from(this.selection[k]) : []);
            if (checked) {
                set.add(t.id_penjadwalan);
            } else {
                set.delete(t.id_penjadwalan);
            }
            this.selection = { ...this.selection, [k]: set };
        },

        countByStatus(emp, status) {
            const s = this.selection[emp.kode_karyawan];
            const list = this.trainingCache[emp.kode_karyawan];

            if (!list) {
                const total = status === 'completed' ? emp.training_selesai : emp.training_berlangsung;
                if (!s || s.size === 0) return total || 0;
                return total || 0;
            }

            const matched = list.filter((t) => t.status === status);
            if (!s || s.size === 0) return matched.length;
            return matched.filter((t) => s.has(t.id_penjadwalan)).length;
        },

        async submitExport() {
            const selection = [];
            Object.values(this.selection).forEach((set) => {
                if (set) set.forEach((id) => selection.push(id));
            });

            if (!selection.length) {
                ElMessage.warning('Pilih minimal satu training untuk di-export.');
                return;
            }

            this.requesting = true;
            try {
                const { data } = await axios.post(`${BASE}/export/request`, {
                    format: this.format,
                    nama: (this.exportNama || '').trim() || undefined,
                    selection,
                    filters: this.filters,
                });

                if (data.success) {
                    ElNotification({
                        title: 'Export Diproses',
                        message: `
                            <div style="text-align: left; margin-top: 5px;">
                                <p>File export sedang disiapkan oleh sistem.</p>
                                <p style="margin-top: 8px; font-size: 12px; color: #64748b;">
                                    <i class="bi bi-info-circle" style="color: #4f46e5; margin-right: 4px;"></i>
                                    <strong>Tip:</strong> Pantau progress di 
                                    <strong>Pusat Aktivitas</strong> (ikon awan di topbar).
                                </p>
                            </div>
                        `,
                        type: 'success',
                        dangerouslyUseHTMLString: true,
                        duration: 6000,
                        position: 'top-right',
                    });

                    useActivityCenter().notifyNewTask();
                    this.$emit('dispatched', data.result?.export_id);
                    this.$emit('close');
                }
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || 'Gagal menyiapkan export.');
            } finally {
                this.requesting = false;
            }
        },

        getAvatarColor(name) {
            if (!name) return '#667eea';
            const index = name.length % this.avatarColors.length;
            return this.avatarColors[index];
        },

        initials(name) {
            if (!name) return '?';
            return name
                .split(' ')
                .slice(0, 2)
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },

        formatDate(d) {
            if (!d) return '-';
            const date = new Date(d);
            if (isNaN(date)) return d;
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
    },
};
</script>

<style scoped>
/* ===== Global Styles ===== */
.export-modal-body {
    padding: 0 !important;
    background: var(--hcis-slate-50);
    overflow: hidden !important;
}

.export-wrap {
    display: flex;
    flex-direction: column;
    height: 78vh;
    position: relative;
}

/* ===== Filter Bar ===== */
.export-filters {
    padding: 12px 20px;
    background: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-200);
}

.filter-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.filter-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--hcis-slate-800);
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-title i {
    color: var(--hcis-indigo-600);
}

.filter-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-toggle-advanced {
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}

.btn-toggle-advanced:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-800);
}

.filter-clear-all {
    background: transparent;
    border: none;
    color: var(--hcis-red-500);
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;
    padding: 4px 10px;
    border-radius: 6px;
    transition: all 0.2s;
}

.filter-clear-all:hover {
    background: var(--hcis-red-50);
}

/* Override Filter Panel nested default borders and margins */
.export-filters :deep(.filter-panel) {
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
    margin-bottom: 0 !important;
    background: transparent !important;
}

/* Align Search and Top-Level Filter side-by-side on desktop */
@media (min-width: 768px) {
    .export-filters :deep(.filter-search .row) {
        display: flex !important;
        align-items: flex-end !important;
    }
    .export-filters :deep(.filter-search .row > div:nth-child(1)) {
        flex: 0 0 65% !important;
        max-width: 65% !important;
    }
    .export-filters :deep(.filter-search .row > div:nth-child(2)) {
        flex: 0 0 35% !important;
        max-width: 35% !important;
    }
}

/* Tidy up label margins and sizes */
.export-filters :deep(.filter-label) {
    font-size: 10.5px !important;
    font-weight: 600 !important;
    color: var(--hcis-slate-500) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    margin-bottom: 4px !important;
}

.export-filters :deep(.desktop-filters-grid) {
    margin-top: 10px !important;
    margin-bottom: 0 !important;
}

/* Unified custom style for inputs and select menus to look neat */
.export-filters :deep(.el-select) {
    width: 100% !important;
}
.export-filters :deep(.el-date-editor) {
    width: 100% !important;
}

/* Force Search Input to match the default height (32px) of other filters */
.export-filters :deep(.search-input-enhanced .el-input__wrapper) {
    height: 32px !important;
    line-height: 32px !important;
}
.export-filters :deep(.search-input-enhanced .el-input__inner) {
    height: 32px !important;
}

.export-filters :deep(.el-input__wrapper),
.export-filters :deep(.el-select .el-input__wrapper),
.export-filters :deep(.el-date-editor.el-input__wrapper) {
    border-radius: 6px !important;
    background-color: var(--hcis-slate-50) !important;
    border: 1.5px solid var(--hcis-slate-200) !important;
    box-shadow: none !important;
    transition: all 0.2s !important;
}

.export-filters :deep(.el-input__wrapper:hover),
.export-filters :deep(.el-select .el-input__wrapper:hover),
.export-filters :deep(.el-date-editor.el-input__wrapper:hover) {
    border-color: var(--hcis-legacy-violet-400) !important;
    background-color: var(--hcis-white) !important;
}

.export-filters :deep(.el-input__wrapper.is-focus),
.export-filters :deep(.el-select .el-input__wrapper.is-focus),
.export-filters :deep(.el-date-editor.el-input__wrapper.is-focus) {
    border-color: var(--hcis-legacy-violet-400) !important;
    background-color: var(--hcis-white) !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12) !important;
}

.ef-select {
    padding: 8px 10px;
    border: 1.5px solid var(--hcis-slate-200);
    border-radius: 6px;
    font-size: 12px;
    background: var(--hcis-slate-50);
    width: 100%;
    transition: all 0.2s;
    color: var(--hcis-slate-800);
}

.ef-select:focus {
    outline: none;
    border-color: var(--hcis-legacy-violet-400);
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.ef-select.sm {
    padding: 6px 8px;
    font-size: 11px;
    width: auto;
}

/* ===== Summary Bar (Sticky Footer) ===== */
.export-summary-sticky {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-top: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.04);
    z-index: 10;
}

.es-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.es-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(79, 70, 229, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--hcis-indigo-600);
}

.es-details {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.es-main {
    font-size: 13px;
    color: var(--hcis-indigo-800);
    font-weight: 500;
}

.es-main strong {
    color: var(--hcis-indigo-600);
}

.es-sub {
    font-size: 10.5px;
    color: var(--hcis-indigo-500);
}

.es-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.es-format {
    font-size: 11px;
    color: var(--hcis-slate-600);
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.es-format i {
    color: var(--hcis-indigo-500);
    font-size: 14px;
}

.es-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.2);
}

.es-export-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--hcis-indigo-700) 0%, var(--hcis-indigo-600) 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
}

.es-export-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ===== Quick Select ===== */
.quick-select {
    display: flex;
    gap: 6px;
    padding: 8px 20px;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--hcis-slate-200);
}

.quick-btn {
    padding: 5px 12px;
    border: 1.5px solid var(--hcis-slate-200);
    background: white;
    border-radius: 6px;
    font-size: 11px;
    color: var(--hcis-slate-600);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    font-weight: 500;
}

.quick-btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-legacy-violet-400);
    color: var(--hcis-indigo-600);
}

.quick-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ===== Employee List ===== */
.export-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px 20px 80px;
    background: var(--hcis-slate-50);
}

/* Loading State */
.el-loading {
    text-align: center;
    padding: 40px 20px;
}

.loading-animation {
    margin-bottom: 12px;
}

.loading-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid var(--hcis-slate-200);
    border-top-color: var(--hcis-legacy-violet-400);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto;
}

.loading-text {
    font-size: 13px;
    font-weight: 600;
    color: var(--hcis-slate-800);
    margin-bottom: 4px;
}

.loading-sub {
    font-size: 11px;
    color: var(--hcis-slate-400);
}

/* Empty State */
.el-empty {
    text-align: center;
    padding: 40px 20px;
}

.empty-icon {
    font-size: 36px;
    color: var(--hcis-slate-300);
    margin-bottom: 12px;
}

.empty-text {
    font-size: 13px;
    font-weight: 600;
    color: var(--hcis-slate-800);
    margin-bottom: 4px;
}

.empty-sub {
    font-size: 11px;
    color: var(--hcis-slate-400);
    margin-bottom: 12px;
}

.empty-btn {
    padding: 6px 14px;
    border: 1.5px solid var(--hcis-slate-200);
    background: white;
    border-radius: 6px;
    font-size: 12px;
    color: var(--hcis-slate-600);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.empty-btn:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-legacy-violet-400);
    color: var(--hcis-indigo-600);
}

/* Employee Cards & Grid Layout */
.employee-grid {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    width: 100%;
}

.grid-column {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.el-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    overflow: hidden;
    background: white;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    height: auto;
}

.el-card:hover {
    border-color: var(--hcis-slate-300);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}

.el-card.is-expanded {
    border-color: var(--hcis-indigo-400);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.08);
}

.el-card.is-selected {
    border-color: var(--hcis-indigo-300);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-white) 100%);
}

.el-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    cursor: pointer;
    transition: background 0.15s;
    flex: 1;
}

.el-body:hover {
    background: #fafbfc;
}

.el-profile-wrap {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    width: 100%;
}

.el-profile {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.el-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    color: white;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
    margin-top: 1px;
}

.el-identity {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
}

.el-name-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    width: 100%;
    margin-bottom: 3px;
}

.el-name {
    font-weight: 600;
    font-size: 13.5px;
    color: var(--hcis-slate-900);
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 160px;
}

.el-badge {
    font-size: 9px;
    font-weight: 700;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
    padding: 1px 5px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    flex-shrink: 0;
    text-transform: uppercase;
}

.el-job-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--hcis-slate-600);
    margin-bottom: 4px;
    line-height: 1.3;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.el-dept-div {
    font-size: 10px;
    font-weight: 500;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.el-dept-div i {
    color: var(--hcis-slate-400);
    font-size: 11px;
    flex-shrink: 0;
}

.el-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 14px;
    border-top: 1px solid var(--hcis-slate-100);
    background: var(--hcis-slate-50);
    cursor: pointer;
    transition: background 0.15s;
}

.el-footer:hover {
    background: var(--hcis-slate-100);
}

.el-counts {
    display: flex;
    gap: 8px;
    align-items: center;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.stat-badge.in-progress {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border-color: var(--hcis-amber-200);
}

.stat-badge.completed {
    background: var(--hcis-green-50);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.stat-lbl {
    font-size: 9px;
    opacity: 0.85;
    font-weight: 500;
}

.stat-val {
    font-size: 10px;
    font-weight: 700;
    margin-left: 1px;
}

.el-expand-btn {
    border: 1px solid var(--hcis-slate-200);
    background: white;
    color: var(--hcis-slate-500);
    cursor: pointer;
    font-size: 11px;
    padding: 0;
    border-radius: 6px;
    transition: all 0.2s;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.el-expand-btn:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-800);
    border-color: var(--hcis-slate-300);
}

.el-expand-btn i {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.el-card.is-expanded .el-expand-btn i {
    transform: rotate(180deg);
}

/* Checkboxes */
.el-check {
    position: relative;
    display: inline-flex;
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 2px;
}

.el-check input,
.titem-check input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    width: 18px;
    height: 18px;
    border: 2px solid var(--hcis-slate-300);
    border-radius: 5px;
    display: inline-block;
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
}

.checkmark.sm {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.el-check input:checked ~ .checkmark,
.titem-check input:checked ~ .checkmark {
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
}

.el-check input:indeterminate ~ .checkmark {
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
}

.el-check input:checked ~ .checkmark::after,
.titem-check input:checked ~ .checkmark::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 1px;
    width: 4px;
    height: 9px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkmark.sm::after {
    left: 4px !important;
    top: 0px !important;
    width: 4px !important;
    height: 8px !important;
}

.el-check input:indeterminate ~ .checkmark::after {
    content: '';
    position: absolute;
    left: 4px;
    top: 6px;
    width: 6px;
    height: 2px;
    background: #fff;
    border: none;
    transform: none;
}

/* Trainings */
.el-trainings {
    border-top: 1px solid var(--hcis-slate-100);
    background: var(--hcis-slate-50);
    padding: 8px 0;
}

.training-list {
    padding: 8px 14px 10px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    border-left: 2px solid var(--hcis-slate-200);
    margin-left: 32px;
}

.el-tloading {
    padding: 16px;
}

.loading-skeleton {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.skeleton-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.skeleton-line {
    height: 10px;
    background: linear-gradient(90deg, var(--hcis-slate-200) 0%, var(--hcis-slate-100) 50%, var(--hcis-slate-200) 100%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 3px;
}

.skeleton-line.w-70 {
    width: 70%;
}

.skeleton-line.w-30 {
    width: 30%;
}

@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

.el-tempty {
    padding: 20px;
    text-align: center;
    font-size: 12px;
    color: var(--hcis-slate-400);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.el-titem {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.el-titem:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    transform: translateX(3px);
}

.el-titem.is-checked {
    background: #faf5ff;
    border-color: var(--hcis-purple-300);
    box-shadow: 0 1px 4px rgba(168, 85, 247, 0.04);
}

.titem-check {
    flex-shrink: 0;
    position: relative;
    display: inline-flex;
    cursor: pointer;
}

.titem-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.titem-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.el-tjudul {
    font-size: 12px;
    color: var(--hcis-slate-800);
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.titem-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    color: var(--hcis-slate-500);
    font-weight: 500;
}

.el-tdate,
.el-tkat {
    display: flex;
    align-items: center;
    gap: 3px;
}

.el-tstatus {
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 600;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid transparent;
}

.el-tstatus.s-done {
    background: var(--hcis-green-50);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.el-tstatus.s-prog {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border-color: var(--hcis-amber-200);
}

/* Pagination */
.el-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0 8px;
    font-size: 12px;
    color: var(--hcis-slate-500);
    flex-wrap: wrap;
    gap: 10px;
}

.pager-info {
    font-size: 11px;
    color: var(--hcis-slate-400);
}

.pager-controls {
    display: flex;
    align-items: center;
    gap: 3px;
}

.pager-btn {
    border: 1.5px solid var(--hcis-slate-200);
    background: white;
    border-radius: 6px;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    color: var(--hcis-slate-500);
}

.pager-btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-legacy-violet-400);
    color: var(--hcis-indigo-600);
}

.pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pager-pages {
    display: flex;
    gap: 3px;
    margin: 0 3px;
}

.pager-num {
    border: 1.5px solid var(--hcis-slate-200);
    background: white;
    border-radius: 6px;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 12px;
    font-weight: 500;
    color: var(--hcis-slate-500);
}

.pager-num:hover:not(:disabled):not(.dots) {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-legacy-violet-400);
    color: var(--hcis-indigo-600);
}

.pager-num.active {
    background: var(--hcis-indigo-600);
    color: white;
    border-color: var(--hcis-indigo-600);
}

.pager-num.dots {
    border: none;
    cursor: default;
}

/* Transitions */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(100%);
}

.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
    max-height: 1000px;
}

/* Spinner */
.spin-mini {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

.spin {
    display: inline-block;
    animation: spin 0.7s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Responsive Design */
@media (max-width: 992px) {
    .export-list {
        padding-bottom: 80px;
    }
}

@media (max-width: 768px) {
    .export-summary-sticky {
        flex-direction: column;
        align-items: stretch;
    }

    .export-list {
        padding-bottom: 130px;
    }

    .es-actions {
        justify-content: space-between;
    }

    .el-head {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .el-head-right {
        justify-content: space-between;
    }

    .el-pager {
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .employee-grid {
        gap: 8px;
    }

    .training-list {
        padding: 6px 8px 10px 12px;
    }
}

@media (max-width: 480px) {
    .export-list {
        padding-bottom: 170px;
    }

    .es-actions {
        flex-direction: column;
        gap: 6px;
    }

    .es-export-btn {
        width: 100%;
        justify-content: center;
    }

    .el-counts {
        flex-direction: column;
        gap: 3px;
    }

    .titem-main {
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
    }
}

/* Custom Scrollbar */
.export-list::-webkit-scrollbar {
    width: 6px;
}

.export-list::-webkit-scrollbar-track {
    background: var(--hcis-slate-100);
    border-radius: 10px;
}

.export-list::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 10px;
}

.export-list::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}
</style>
