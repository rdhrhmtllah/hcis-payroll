<template>
    <div class="kl-app-container pt-3">
        <HeaderKpi :title="pageTitle" :subtitle="pageSubtitle" :dayNight="dayNight" :formattedDate="formattedDate" />

        <div class="">
            <QuickStat :stats="quickStats" class="mb-4" />

            <div class="kl-tab-panel">
                <div class="stitch-card bg-white p-4 rounded-4 border">
                    <div class="mb-2">
                        <div class="catalog-breadcrumbs mb-3">
                            <span
                                class="crumb-link text-dark fw-bold"
                                style="cursor: pointer"
                                @click="handleCategoryChange('All')"
                                >Training</span
                            >
                            <span class="crumb-separator mx-2 text-muted">/</span>
                            <span class="crumb-current fw-bold text-primary">Training Catalog</span>
                        </div>

                        <ModernFilter
                            :config="filterConfig"
                            :filters="tableFilters"
                            :resultsCount="resultsCount"
                            @filters-update="handleFiltersUpdate"
                        />
                    </div>

                    <transition name="catalog-fade" mode="out-in">
                        <div v-if="loading" key="catalog-loading" class="catalog-loading-state">
                            <div class="catalog-loading-header">
                                <div class="loading-pill">
                                    <i class="bi bi-arrow-repeat"></i>
                                    <span>{{ loadingMessage }}</span>
                                </div>
                                <p class="loading-subtext">Kami sedang menyiapkan daftar training terbaik untuk Anda.</p>
                            </div>

                            <div class="row g-3">
                                <div v-for="skeleton in loadingSkeletons" :key="`skeleton-${skeleton}`" class="col-12 col-sm-6 col-xl-3">
                                    <div class="catalog-skeleton-card">
                                        <div class="catalog-skeleton-cover shimmer"></div>
                                        <div class="catalog-skeleton-body">
                                            <div class="catalog-skeleton-line shimmer w-75"></div>
                                            <div class="catalog-skeleton-line shimmer w-100"></div>
                                            <div class="catalog-skeleton-line shimmer w-50"></div>
                                            <div class="catalog-skeleton-meta">
                                                <div class="catalog-skeleton-chip shimmer"></div>
                                                <div class="catalog-skeleton-chip shimmer short"></div>
                                            </div>
                                            <div class="catalog-skeleton-progress shimmer"></div>
                                            <div class="catalog-skeleton-footer">
                                                <div class="catalog-skeleton-chip shimmer"></div>
                                                <div class="catalog-skeleton-chip shimmer short"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="pagedCourses.length" key="catalog-results" class="row g-3">
                            <div
                                v-for="course in pagedCourses"
                                :key="course.penjadwalan_id || course.id"
                                class="col-12 col-sm-6 col-xl-3"
                            >
                                <TrainingCourseCard
                                    :course="course"
                                    :loading="navigationLoading && openingCourseId === (course.penjadwalan_id || course.id)"
                                    @select="openDetails"
                                />
                            </div>
                        </div>

                        <div v-else key="catalog-empty" class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-search"></i>
                            </div>
                            <h3>Tidak ada training yang cocok</h3>
                            <p>Coba ubah kata kunci, filter kategori, atau status yang dipilih.</p>
                        </div>
                    </transition>

                    <TrainingCatalogPagination
                        :currentPage="currentPage"
                        :totalPages="totalPages"
                        :itemsPerPage="itemsPerPage"
                        @page-change="handlePageChange"
                    />
                </div>
            </div>
        </div>

        <TrainingCatalogDrawer :show="showDetails" :course="selectedCourse" @close="closeDetails" />
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import TrainingCourseCard from './components/ui/TrainingCourseCard.vue';
import TrainingCatalogDrawer from './components/ui/TrainingCatalogDrawer.vue';
import TrainingCatalogPagination from './components/ui/TrainingCatalogPagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { mockTrainingCatalog } from './data/mockData.js';
import axios from 'axios';

export default {
    name: 'TrainingCatalogPage',
    components: {
        HeaderKpi,
        QuickStat,
        ModernFilter,
        TrainingCourseCard,
        TrainingCatalogDrawer,
        TrainingCatalogPagination,
    },
    props: {
        trainingCatalog: {
            type: Array,
            default: () => [],
        },
        initialPagination: {
            type: Object,
            default: () => ({ total: 0, current_page: 1, total_pages: 1, per_page: 12 }),
        },
    },
    data() {
        return {
            pageTitle: 'Training',
            pageSubtitle: 'Training Katalog Center',
            formattedDate: '',
            dayNight: 'Siang',
            clockInterval: null,
            searchQuery: '',
            selectedCategory: '',
            selectedStatus: '',
            selectedSort: 'default',
            itemsPerPage: this.initialPagination?.per_page || 12,
            currentPage: this.initialPagination?.current_page || 1,
            showDetails: false,
            selectedCourse: null,
            tableFilters: {
                search: '',
                kategori: '',
                status: '',
                sort: 'default',
                perPage: this.initialPagination?.per_page || 12,
            },

            loading: false,
            loadingMessage: 'Memuat training...',
            navigationLoading: false,
            openingCourseId: null,
            serverCatalog: null,
            pagination: this.initialPagination || { total: 0, current_page: 1, total_pages: 1, per_page: 12 },
            filterOptions: {
                kategoris: [],
                statuses: [
                    { label: 'Belum Dimulai', value: 'not-started' },
                    { label: 'Kelas Berlangsung', value: 'in-progress' },
                    { label: 'Kelas Selesai', value: 'completed' },
                ],
            },
        };
    },
    computed: {
        sourceCourses() {
            if (Array.isArray(this.serverCatalog)) {
                return this.serverCatalog;
            }

            if (Array.isArray(this.trainingCatalog) && this.trainingCatalog.length) {
                return this.trainingCatalog;
            }

            return mockTrainingCatalog;
        },
        normalizedCourses() {
            return this.sourceCourses.map((course, index) => this.normalizeCourse(course, index));
        },
        sortedCourses() {
            const sortHandler = this.getSortHandler(this.tableFilters?.sort || this.selectedSort || 'default');
            return [...this.normalizedCourses].sort(sortHandler);
        },
        quickStats() {
            // Stats might be limited if only current page is loaded,
            // but for catalog it's usually okay or we can fetch full stats separately.
            const inProgress = this.normalizedCourses.filter((course) => course.status === 'in-progress').length;
            const completed = this.normalizedCourses.filter((course) => course.status === 'completed').length;

            return [
                {
                    title: 'Total Course',
                    value: String(this.pagination.total || this.normalizedCourses.length),
                    icon: 'bi bi-journal-bookmark',
                    bgIcon: 'bg-primary',
                    size: 'col-4',
                },
                {
                    title: 'Sedang Berjalan',
                    value: String(inProgress),
                    icon: 'bi bi-play-circle',
                    bgIcon: 'bg-warning',
                    size: 'col-4',
                },
                {
                    title: 'Selesai',
                    value: String(completed),
                    icon: 'bi bi-check2-circle',
                    bgIcon: 'bg-success',
                    size: 'col-4',
                },
            ];
        },
        categoryOptions() {
            // Keep existing logic or fetch from server
            const counts = this.normalizedCourses.reduce((summary, course) => {
                summary[course.category] = (summary[course.category] || 0) + 1;
                return summary;
            }, {});

            const normalizedObj = Object.keys(counts).map((label) => ({
                label,
                count: counts[label],
            }));

            return [{ label: 'All', count: this.pagination.total || this.normalizedCourses.length }, ...normalizedObj];
        },
        safeKategoriOptions() {
            return Array.isArray(this.filterOptions?.kategoris) ? this.filterOptions.kategoris : [];
        },
        safeStatusOptions() {
            return Array.isArray(this.filterOptions?.statuses) ? this.filterOptions.statuses : [];
        },
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari training...',
                showResultsCount: true,
                showActiveFilters: true,
                showActions: true,
                showReset: true,
                showClearAll: true,
                filters: [
                    {
                        key: 'kategori',
                        label: 'Kategori',
                        type: 'select',
                        options: [
                            { label: 'Semua Kategori', value: '' },
                            ...this.safeKategoriOptions.map((k) => ({
                                label: k.label,
                                value: k.value,
                            })),
                        ],
                        col: 4,
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        options: [
                            { label: 'Semua Status', value: '' },
                            ...this.safeStatusOptions.map((s) => ({
                                label: s.label,
                                value: s.value,
                            })),
                        ],
                        col: 4,
                    },
                    {
                        key: 'sort',
                        label: 'Urutkan',
                        type: 'select',
                        clearable: false,
                        options: [
                            { label: 'Default (Baru Dijadwalkan → Sedang Progress → Selesai)', value: 'default' },
                            { label: 'Terbaru', value: 'newest' },
                            { label: 'Sedang Progress Dahulu', value: 'in-progress-first' },
                            { label: 'Progress Tertinggi', value: 'progress-desc' },
                            { label: 'Progress Terendah', value: 'progress-asc' },
                            { label: 'Selesai Dahulu', value: 'completed-first' },
                            { label: 'Judul A-Z', value: 'title-asc' },
                            { label: 'Judul Z-A', value: 'title-desc' },
                        ],
                        col: 4,
                    },
                ],
            };
        },
        filteredCourses() {
            return this.sortedCourses;
        },
        totalPages() {
            return this.pagination.total_pages || 1;
        },
        resultsCount() {
            return this.pagination.total || this.filteredCourses.length;
        },
        loadingSkeletons() {
            return Array.from({ length: Math.min(this.itemsPerPage || 8, 8) }, (_, index) => index + 1);
        },
        pagedCourses() {
            return this.filteredCourses;
        },
    },
    watch: {
        filteredCourses() {
            if (this.currentPage > this.totalPages) {
                this.currentPage = this.totalPages;
            }
        },
    },
    mounted() {
        this.updateClock();
        this.clockInterval = setInterval(this.updateClock, 1000);
        this.fetchFilterOptions();
    },
    beforeUnmount() {
        if (this.clockInterval) {
            clearInterval(this.clockInterval);
        }
    },
    methods: {
        async fetchFilterOptions() {
            try {
                const response = await axios.get('/lms/training/filter-options', {
                    headers: { Accept: 'application/json' },
                });

                if (response.data.success && response.data.result) {
                    this.filterOptions = this.normalizeFilterOptions(response.data.result);
                }
            } catch (error) {
                console.error('Error fetching filter options', error);
                // Set default filter options on error
                this.filterOptions = this.normalizeFilterOptions();
            }
        },
        async fetchCatalog(page = 1) {
            this.loading = true;
            this.loadingMessage = this.resolveLoadingMessage(page);
            try {
                const response = await axios.get('/lms/training/catalog', {
                    params: {
                        page: page,
                        limit: this.itemsPerPage,
                        q: (this.searchQuery || '').trim(),
                        kategori: (this.selectedCategory || '').trim(),
                        status: (this.selectedStatus || '').trim(),
                        sort: (this.selectedSort || this.tableFilters?.sort || 'default').trim(),
                    },
                    headers: { Accept: 'application/json' },
                });

                if (response.data.success) {
                    this.serverCatalog = response.data.result;
                    this.pagination = response.data.pagination;
                    this.currentPage = this.pagination.current_page;
                }
            } catch (error) {
                console.error('Error fetching catalog', error);
                ElMessage.error('Gagal memuat data training catalog');
            } finally {
                this.loading = false;
                this.loadingMessage = 'Memuat training...';
            }
        },
        resolveLoadingMessage(page = 1) {
            if ((this.searchQuery || '').trim()) {
                return `Mencari "${this.searchQuery.trim()}"...`;
            }

            if ((this.selectedCategory || '').trim() || (this.selectedStatus || '').trim()) {
                return 'Menyaring training...';
            }

            if (Number(page) > 1) {
                return 'Memuat halaman berikutnya...';
            }

            return 'Memuat training...';
        },
        updateClock() {
            const now = new Date();
            this.formattedDate = now.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
            const hour = now.getHours();
            this.dayNight = hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        normalizeFilterOptions(rawOptions = {}) {
            const defaultStatuses = [
                { label: 'Belum Dimulai', value: 'not-started' },
                { label: 'Kelas Berlangsung', value: 'in-progress' },
                { label: 'Kelas Selesai', value: 'completed' },
            ];

            return {
                kategoris: this.normalizeOptionList(rawOptions.kategoris),
                statuses: this.normalizeOptionList(rawOptions.statuses, defaultStatuses),
            };
        },
        normalizeOptionList(options, fallback = []) {
            const source = Array.isArray(options) ? options : fallback;

            return source
                .map((option) => {
                    if (typeof option === 'string') {
                        const value = option.trim();
                        return value ? { label: value, value } : null;
                    }

                    if (!option || typeof option !== 'object') {
                        return null;
                    }

                    const label = String(option.label ?? option.nama ?? option.name ?? option.value ?? '').trim();
                    const value = String(option.value ?? option.id ?? option.kode ?? label).trim();

                    if (!label || !value) {
                        return null;
                    }

                    return { label, value };
                })
                .filter((option) => option && option.label && option.value)
                .filter(
                    (option, index, list) =>
                        list.findIndex((item) => item.value === option.value) === index,
                );
        },
        safeNormalizeFilterValue(value, fallback) {
            if (value === null || value === undefined) {
                return fallback;
            }

            const normalized = String(value).trim();
            return normalized || fallback;
        },
        normalizeCourse(course, index) {
            const createdAt = course.created_at || null;
            const enrollDate =
                course.start_at ||
                course.enrollDate ||
                course.dateStart ||
                course.date ||
                course.created_at ||
                course.scheduleDate ||
                null;
            const progress = this.normalizeProgress(course.progress);
            const status = this.normalizeStatus(course.status, progress, enrollDate);
            const displayDate = createdAt || enrollDate;
            const dateLabel = this.formatDateLabel(displayDate);
            const scheduleTimeLabel = this.formatTimeLabel(displayDate);
            const scheduleDateTimeLabel = this.formatDateTimeLabel(displayDate);

            const scheduleTimestamp = createdAt ? (new Date(createdAt).getTime() || 0) : (enrollDate ? (new Date(enrollDate).getTime() || 0) : 0);
            const scheduleId = Number(course.id_penjadwalan_raw || (typeof course.id === 'number' ? course.id : 0)) || 0;

            return {
                id: course.id || `course-${index + 1}`,
                penjadwalan_id: course.penjadwalan_id || null,
                id_penjadwalan_raw: scheduleId,
                title: course.title || course.name || 'Untitled Training',
                category: course.category || course.group || 'General',
                provider: course.provider || course.vendor || 'Internal Training',
                dateLabel,
                scheduleTimeLabel,
                scheduleDateTimeLabel,
                enrollDate,
                created_at: createdAt,
                sortDate: scheduleTimestamp,
                duration: course.duration || course.durationLabel || '45 min',
                level: course.level || 'General',
                progress,
                status,
                statusLabel: this.statusLabel(status),
                statusClass: `status-${status}`,
                image: course.image || course.thumbnail || course.thumbnailUrl || '',
                trainer: course.trainer || course.instructor || 'Learning Team',
                description:
                    course.description ||
                    'Training ini dirancang untuk membantu peserta memahami konteks kerja, standar operasional, dan target kompetensi yang diharapkan.',
                tags: Array.isArray(course.tags) ? course.tags : [],
            };
        },

        normalizeProgress(progress) {
            const value = Number(progress);
            if (!Number.isFinite(value)) {
                return 0;
            }
            return Math.min(100, Math.max(0, Math.round(value)));
        },
        normalizeStatus(status, progress, enrollDate) {
            const normalized = String(status || '').toLowerCase();
            if (['not-started', 'in-progress', 'completed'].includes(normalized)) {
                return normalized;
            }

            const safeProgress = this.normalizeProgress(progress);
            if (safeProgress >= 100) {
                return 'completed';
            }

            if (safeProgress > 0) {
                return 'in-progress';
            }

            return 'not-started';
        },
        statusLabel(status) {
            const labels = {
                'not-started': 'Belum Dimulai',
                'in-progress': 'Kelas Berlangsung',
                completed: 'Kelas Selesai',
            };

            return labels[status] || 'Belum Dimulai';
        },
        formatDateLabel(dateValue) {
            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) {
                return 'Tanggal belum tersedia';
            }

            return date.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        formatTimeLabel(dateValue) {
            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) {
                return 'Jadwal belum tersedia';
            }

            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatDateTimeLabel(dateValue) {
            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) {
                return 'jadwal belum tersedia';
            }

            return date.toLocaleString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        getSortHandler(sort) {
            const handlers = {
                'title-asc': (left, right) => (left.title || '').localeCompare(right.title || ''),
                'title-desc': (left, right) => (right.title || '').localeCompare(left.title || ''),
                'progress-desc': (left, right) => (right.progress - left.progress) || (right.sortDate - left.sortDate),
                'progress-asc': (left, right) => (left.progress - right.progress) || (right.sortDate - left.sortDate),
                newest: (left, right) => (right.sortDate - left.sortDate) || (left.title || '').localeCompare(right.title || ''),
                'in-progress-first': (left, right) => {
                    const getRank = (c) => {
                        if (c.status === 'in-progress' && (c.progress || 0) > 0) return 1;
                        if (c.status === 'in-progress') return 2;
                        if (c.status === 'not-started') return 3;
                        return 4;
                    };
                    return (getRank(left) - getRank(right)) || (right.sortDate - left.sortDate);
                },
                'completed-first': (left, right) => {
                    const getRank = (c) => {
                        if (c.status === 'completed' || (c.progress || 0) >= 100) return 1;
                        if (c.status === 'in-progress') return 2;
                        return 3;
                    };
                    return (getRank(left) - getRank(right)) || (right.sortDate - left.sortDate);
                },
                default: (left, right) => {
                    // 1: Baru dijadwalkan ke user (progress == 0 / belum mulai)
                    // 2: Sedang progress (progress > 0 && progress < 100)
                    // 3: Selesai (completed / progress >= 100)
                    const getRank = (c) => {
                        const status = c.status || 'not-started';
                        const prog = Number(c.progress) || 0;
                        if (status === 'completed' || prog >= 100) return 3;
                        if (prog > 0) return 2;
                        return 1;
                    };
                    const rankDiff = getRank(left) - getRank(right);
                    if (rankDiff !== 0) return rankDiff;

                    // Di dalam masing-masing kelompok: urutkan dari yang terakhir ditugaskan ke yang lama
                    const dateDiff = (right.sortDate || 0) - (left.sortDate || 0);
                    if (dateDiff !== 0) return dateDiff;

                    const idDiff = (right.id_penjadwalan_raw || 0) - (left.id_penjadwalan_raw || 0);
                    if (idDiff !== 0) return idDiff;

                    return (left.title || '').localeCompare(right.title || '');
                },
                featured: (left, right) => {
                    const getRank = (c) => {
                        const status = c.status || 'not-started';
                        const prog = Number(c.progress) || 0;
                        if (status === 'completed' || prog >= 100) return 3;
                        if (prog > 0) return 2;
                        return 1;
                    };
                    const rankDiff = getRank(left) - getRank(right);
                    if (rankDiff !== 0) return rankDiff;

                    // Di dalam masing-masing kelompok: urutkan dari yang terakhir ditugaskan ke yang lama
                    const dateDiff = (right.sortDate || 0) - (left.sortDate || 0);
                    if (dateDiff !== 0) return dateDiff;

                    const idDiff = (right.id_penjadwalan_raw || 0) - (left.id_penjadwalan_raw || 0);
                    if (idDiff !== 0) return idDiff;

                    return (left.title || '').localeCompare(right.title || '');
                },
            };

            return handlers[sort] || handlers.default;
        },
        handleFiltersUpdate(updated) {
            const nextFilters = {
                ...this.tableFilters,
                ...updated,
                search: this.safeNormalizeFilterValue(updated?.search, ''),
                kategori: this.safeNormalizeFilterValue(updated?.kategori, ''),
                status: this.safeNormalizeFilterValue(updated?.status, ''),
                sort: this.safeNormalizeFilterValue(updated?.sort, 'default'),
            };

            this.tableFilters = nextFilters;
            this.searchQuery = nextFilters.search;
            this.selectedCategory = nextFilters.kategori;
            this.selectedStatus = nextFilters.status;
            this.selectedSort = nextFilters.sort;
            this.fetchCatalog(1);
        },
        handleCategoryChange(category) {
            const safeCategory =
                String(category ?? '').trim().toLowerCase() === 'all'
                    ? ''
                    : this.safeNormalizeFilterValue(category, '');
            this.selectedCategory = safeCategory;
            this.tableFilters = {
                ...this.tableFilters,
                kategori: safeCategory,
            };
            this.fetchCatalog(1);
        },
        handlePageChange(page) {
            this.fetchCatalog(page);
        },
        openDetails(course) {
            const scheduleId = course?.penjadwalan_id;

            if (!scheduleId) {
                ElMessage.error('Data penjadwalan training belum tersedia. Silakan refresh halaman atau hubungi admin.');
                return;
            }

            if (course.status === 'not-started') {
                ElMessage({
                    message: `Kelas belum dimulai dan akan dibuka pada ${course.scheduleDateTimeLabel}.`,
                    type: 'warning',
                });
                return;
            }

            this.navigationLoading = true;
            this.openingCourseId = scheduleId;

            this.$inertia.visit('/lms/training/schedule/' + scheduleId, {
                onError: () => {
                    this.navigationLoading = false;
                    this.openingCourseId = null;
                },
                onCancel: () => {
                    this.navigationLoading = false;
                    this.openingCourseId = null;
                },
                onFinish: () => {
                    this.navigationLoading = false;
                    this.openingCourseId = null;
                },
            });
        },
        closeDetails() {
            this.showDetails = false;
            window.setTimeout(() => {
                this.selectedCourse = null;
            }, 200);
        },
    },
    created() {
        this.filterOptions = this.normalizeFilterOptions(this.filterOptions);
    },
};
</script>

<style scoped>
.kl-app-container {
    background: var(--hcis-slate-100);
    min-height: 100vh;
    /* padding-bottom: 3rem; */
    color: var(--hcis-slate-800);
}
.kl-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.kl-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.empty-state {
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.75rem;
    color: var(--hcis-slate-500);
}
.empty-state-icon {
    width: 72px;
    height: 72px;
    border-radius: 24px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    font-size: 1.7rem;
}
.empty-state h3 {
    margin: 0;
    color: var(--hcis-slate-800);
    font-weight: 800;
}
.empty-state p {
    margin: 0;
    max-width: 420px;
}

.catalog-loading-state {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-height: 320px;
}

.catalog-loading-header {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.loading-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    width: fit-content;
    padding: 0.7rem 1rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-weight: 700;
    box-shadow: 0 14px 30px -22px rgba(37, 99, 235, 0.85);
}

.loading-pill i {
    animation: catalog-spin 0.9s linear infinite;
}

.loading-subtext {
    margin: 0;
    color: var(--hcis-slate-500);
    font-size: 0.92rem;
}

.catalog-skeleton-card {
    overflow: hidden;
    border-radius: 16px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    box-shadow: 0 12px 30px -24px rgba(15, 23, 42, 0.2);
}

.catalog-skeleton-cover {
    aspect-ratio: 1.85 / 1;
    background: var(--hcis-slate-200);
}

.catalog-skeleton-body {
    padding: 1.25rem;
}

.catalog-skeleton-line {
    height: 12px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    margin-bottom: 0.75rem;
}

.catalog-skeleton-meta,
.catalog-skeleton-footer {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}

.catalog-skeleton-chip {
    height: 28px;
    width: 44%;
    border-radius: 999px;
    background: var(--hcis-slate-200);
}

.catalog-skeleton-chip.short {
    width: 28%;
}

.catalog-skeleton-progress {
    height: 8px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    margin-bottom: 1rem;
}

.shimmer {
    position: relative;
    overflow: hidden;
}

.shimmer::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    animation: shimmer-slide 1.35s infinite;
}

.catalog-fade-enter-active,
.catalog-fade-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.catalog-fade-enter-from,
.catalog-fade-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@keyframes shimmer-slide {
    100% {
        transform: translateX(100%);
    }
}

@keyframes catalog-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
