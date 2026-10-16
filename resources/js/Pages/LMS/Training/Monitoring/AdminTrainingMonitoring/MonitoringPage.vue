<template>
    <div class="monitoring-app-container">
        <!-- Header -->
        <HeaderKpi
            title="Monitoring Training"
            subtitle="Pantau progress pelatihan dan ujian seluruh karyawan."
            :dayNight="dayNight"
            :formattedDate="formattedDate"
            :buttonAction="false"
        />

        <div class="mb-4">
            <QuickStat :stats="quickStatField" />
        </div>

        <div class="main-content-wrapper">
            <div class="content-container">
                <!-- <div class="p-4 rounded-4 mb-4" style="background: var(--surface-soft)"> -->
                <!-- <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <h2 class="h5 fw-bold text-dark mb-1">Daftar Pelatihan Karyawan</h2>
                            <p class="text-muted text-sm mb-0">
                                Pantau progress pelatihan dan ujian yang telah di-assign ke karyawan.
                            </p>
                        </div> -->
                <!-- <div class="d-flex gap-2 align-items-center">
                            <div class="view-toggle d-flex" style="background: white; padding: 4px; border-radius: 8px; border: 1px solid var(--border)">
                                <button
                                    class="btn btn-sm"
                                    :class="viewMode === 'table' ? 'btn-primary text-white fw-bold' : 'btn-light text-muted'"
                                    @click="viewMode = 'table'"
                                    style="border-radius: 6px"
                                >
                                    <i class="bi bi-table me-1"></i> Tabel
                                </button>
                                <button
                                    class="btn btn-sm"
                                    :class="viewMode === 'card' ? 'btn-primary text-white fw-bold' : 'btn-light text-muted'"
                                    @click="viewMode = 'card'"
                                    style="border-radius: 6px"
                                >
                                    <i class="bi bi-grid-3x3-gap me-1"></i> Kartu
                                </button>
                            </div>
                            <button class="stitch-action-btn" :disabled="loading" type="button" @click="fetchEmployees(1)">
                                <i
                                    class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center"
                                    :class="{ spin: loading }"
                                ></i>
                            </button>
                        </div> -->
                <!-- </div> -->
                <!-- </div> -->

                <!-- Filter Section -->
                <FilterPanel
                    :config="filterConfig"
                    :filters="tableFilters"
                    :results-count="pagination.total"
                    @filters-update="handleFiltersUpdate"
                    @search="handleSearch"
                >
                    <template #actions>
                        <button
                            class="stitch-btn primary text-sm fw-bold d-inline-flex align-items-center gap-2"
                            type="button"
                            @click="exportModalOpen = true"
                        >
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <span>Export Data</span>
                        </button>
                    </template>
                </FilterPanel>

                <ModernDataTable
                    v-if="viewMode === 'table'"
                    :data="employees"
                    :columns="tableColumns"
                    :mobile-columns="[
                        { key: 'departemen', title: 'Departemen' },
                        { key: 'divisi', title: 'Divisi' },
                        { key: 'jabatan', title: 'Jabatan' },
                        { key: 'training_berlangsung', title: 'Berlangsung' },
                        { key: 'training_selesai', title: 'Selesai' },
                    ]"
                    :fit-to-container="true"
                    :clickable="true"
                    card-status-key="total_training"
                    :loading="loading"
                    :show-pagination="false"
                    :current-page="pagination.current_page"
                    :total-pages="pagination.total_pages"
                    :total-data="pagination.total"
                    @page-change="changePage"
                    @row-click="openSlidePanel"
                    empty-message="Tidak ada karyawan ditemukan"
                    empty-action-label="Reset Filter"
                    @empty-action="
                        tableFilters.search = '';
                        tableFilters.status = 'all';
                        fetchEmployees(1);
                    "
                >
                    <!-- Custom Mobile Card Header -->
                    <template #card-header="{ item }">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div
                                class="bg-primary-subtle text-primary p-2 rounded-3 text-center d-flex flex-column lh-1 fw-bold align-items-center justify-content-center"
                                style="width: 42px; height: 42px"
                            >
                                {{ getInitials(item.nama) }}
                            </div>
                            <div>
                                <div class="text-muted small">{{ item.no_induk }}</div>
                                <div class="fw-bold text-dark">{{ item.nama }}</div>
                            </div>
                        </div>
                    </template>

                    <!-- Custom Mobile Card Status -->
                    <template #card-status="{ item }"> Total {{ item.total_training }} Training </template>

                    <!-- Table Columns -->
                    <template #column-karyawan="{ item }">
                        <div class="d-flex align-items-center gap-3">
                            <div
                                class="thumb-box rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center"
                                style="width: 40px; height: 40px; flex-shrink: 0"
                            >
                                {{ getInitials(item.nama) }}
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.9rem">{{ item.nama }}</div>
                                <div class="text-muted" style="font-size: 0.75rem">
                                    {{ item.no_induk }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #column-departemen="{ item }">
                        <span class="text-dark fw-medium" style="font-size: 0.85rem">{{ item.departemen || '-' }}</span>
                    </template>

                    <template #mobile-departemen="{ item }">
                        <span class="text-dark fw-medium" style="font-size: 0.85rem">{{ item.departemen || '-' }}</span>
                    </template>

                    <template #column-divisi="{ item }">
                        <span class="text-muted" style="font-size: 0.8rem">{{ item.divisi || '-' }}</span>
                    </template>

                    <template #mobile-divisi="{ item }">
                        <span class="text-muted" style="font-size: 0.8rem">{{ item.divisi || '-' }}</span>
                    </template>

                    <template #column-training_berlangsung="{ item }">
                        <div class="text-center">
                            <span
                                v-if="item.training_berlangsung > 0"
                                class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill"
                                style="font-size: 0.8rem"
                            >
                                {{ item.training_berlangsung }}
                            </span>
                            <span v-else class="text-muted fw-bold">-</span>
                        </div>
                    </template>

                    <template #mobile-training_berlangsung="{ item }">
                        <span
                            v-if="item.training_berlangsung > 0"
                            class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill"
                            style="font-size: 0.75rem"
                        >
                            {{ item.training_berlangsung }}
                        </span>
                        <span v-else class="text-muted fw-bold">-</span>
                    </template>

                    <template #column-training_selesai="{ item }">
                        <div class="text-center">
                            <span
                                v-if="item.training_selesai > 0"
                                class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"
                                style="font-size: 0.8rem"
                            >
                                {{ item.training_selesai }}
                            </span>
                            <span v-else class="text-muted fw-bold">-</span>
                        </div>
                    </template>

                    <template #mobile-training_selesai="{ item }">
                        <span
                            v-if="item.training_selesai > 0"
                            class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"
                            style="font-size: 0.75rem"
                        >
                            {{ item.training_selesai }}
                        </span>
                        <span v-else class="text-muted fw-bold">-</span>
                    </template>

                    <!-- Aksi Column Slot -->
                    <template #column-actions="{ item }">
                        <div class="d-flex align-items-center gap-2 justify-content-center">
                            <button
                                class="stitch-action-btn text-primary p-2 rounded-lg"
                                @click.stop="openSlidePanel(item)"
                                v-tooltip="'Lihat Detail Training'"
                                style="background: var(--surface-soft); border: 1px solid var(--border)"
                            >
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </template>

                    <!-- Mobile Action Slot -->
                    <template #card-actions="{ item }">
                        <div class="d-flex gap-2 w-100">
                            <button
                                class="stitch-action-btn flex-grow-1 text-primary py-2 px-3 rounded-lg d-flex align-items-center justify-content-center fw-bold w-100"
                                style="background: var(--surface-soft); border: 1px solid var(--border)"
                                @click.stop="openSlidePanel(item)"
                            >
                                <i class="bi bi-eye"></i>
                                <span class="ms-1">Lihat Detail</span>
                            </button>
                        </div>
                    </template>
                </ModernDataTable>

                <!-- Card View (Manual Toggle) -->
                <!-- <div v-else class="card-view-container">
                    <div v-if="loading" class="text-center p-5 loading-state">
                        <div class="loading-spinner">
                            <i class="bi bi-arrow-repeat spin"></i>
                        </div>
                        <p>Memuat data karyawan...</p>
                    </div>

                    <div
                        v-else-if="employees.length === 0"
                        style="min-height: 200px"
                        class="text-center p-5 text-muted bg-white rounded-3 shadow-sm border d-flex justify-content-center align-items-center flex-column"
                    >
                        <i class="bi bi-inbox fs-1 mb-2"></i>
                        <h4 class="h6 mb-2">Tidak ada karyawan ditemukan</h4>
                        <p class="small text-muted mb-3">Coba ubah filter atau kata kunci pencarian Anda</p>
                        <button
                            class="stitch-btn primary small"
                            @click="
                                tableFilters.search = '';
                                tableFilters.status = 'all';
                                fetchEmployees(1);
                            "
                        >
                            <i class="bi bi-arrow-clockwise me-2 d-flex justify-content-center align-items-center"></i>
                            Reset Filter
                        </button>
                    </div>

                    <div v-else class="cards-grid row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                        <div class="col" v-for="item in employees" :key="item.no_induk">
                            <div
                                class="stitch-card p-0 overflow-hidden border shadow-sm h-100"
                                @click="openSlidePanel(item)"
                                style="cursor: pointer"
                            >
                                <div
                                    class="card-header border-bottom p-3 bg-white"
                                    style="border-left: 4px solid var(--primary)"
                                >
                                    <div class="d-flex align-items-center gap-3">
                                        <div
                                            class="bg-primary-subtle text-primary p-2 rounded-3 text-center d-flex flex-column lh-1 fw-bold align-items-center justify-content-center"
                                            style="width: 48px; height: 48px; font-size: 1.2rem"
                                        >
                                            {{ getInitials(item.nama) }}
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-muted small">{{ item.no_induk }}</div>
                                            <div class="fw-bold text-dark">{{ item.nama }}</div>
                                        </div>
                                        <div>
                                            <span
                                                class="badge rounded-pill fw-semibold border bg-primary-subtle text-primary border-primary"
                                            >
                                                Total {{ item.total_training }} Training
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-3 bg-white">
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <div class="text-muted text-xs mb-1">
                                                <i class="bi bi-building me-1"></i>Departemen
                                            </div>
                                            <div
                                                class="fw-medium text-dark small"
                                                style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap"
                                                :title="item.departemen || '-'"
                                            >
                                                {{ item.departemen || '-' }}
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted text-xs mb-1">
                                                <i class="bi bi-diagram-3 me-1"></i>Divisi
                                            </div>
                                            <div
                                                class="text-muted small"
                                                style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap"
                                                :title="item.divisi || '-'"
                                            >
                                                {{ item.divisi || '-' }}
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted text-xs mb-1">
                                                <i class="bi bi-hourglass-split me-1 text-warning"></i>Berlangsung
                                            </div>
                                            <div>
                                                <span
                                                    v-if="item.training_berlangsung > 0"
                                                    class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill"
                                                >
                                                    {{ item.training_berlangsung }}
                                                </span>
                                                <span v-else class="text-muted fw-bold small">-</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted text-xs mb-1">
                                                <i class="bi bi-check-circle-fill me-1 text-success"></i>Selesai
                                            </div>
                                            <div>
                                                <span
                                                    v-if="item.training_selesai > 0"
                                                    class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"
                                                >
                                                    {{ item.training_selesai }}
                                                </span>
                                                <span v-else class="text-muted fw-bold small">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        class="stitch-action-btn w-100 text-primary py-2 rounded-lg d-flex align-items-center justify-content-center fw-bold"
                                        style="background: var(--surface-soft); border: 1px solid var(--border)"
                                    >
                                        <i class="bi bi-eye"></i><span class="ms-2">Lihat Detail</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Standardized Pagination -->
                <div
                    v-if="pagination.total_pages > 1"
                    class="d-flex justify-content-between align-items-center mt-4 p-3 bg-white rounded-4 border shadow-sm"
                >
                    <div class="text-muted small d-none d-md-block">
                        Menampilkan <span class="fw-bold text-dark">{{ employees.length }}</span> dari
                        <span class="fw-bold text-dark">{{ pagination.total }}</span> karyawan
                    </div>
                    <div class="pagination-wrapper d-flex gap-2">
                        <button
                            class="stitch-btn secondary small rounded-pill px-3"
                            :disabled="pagination.current_page === 1"
                            @click="changePage(pagination.current_page - 1)"
                        >
                            <i class="bi bi-chevron-left me-1"></i> Prev
                        </button>

                        <div class="d-flex gap-1 d-none d-sm-flex">
                            <button
                                v-for="page in getPaginationRange()"
                                :key="page"
                                class="stitch-btn small rounded-circle p-0 d-flex align-items-center justify-content-center"
                                :class="page === pagination.current_page ? 'primary' : 'secondary outline'"
                                style="width: 32px; height: 32px"
                                @click="changePage(page)"
                            >
                                {{ page }}
                            </button>
                        </div>

                        <span class="d-sm-none align-self-center px-2 small fw-bold">
                            {{ pagination.current_page }} / {{ pagination.total_pages }}
                        </span>

                        <button
                            class="stitch-btn secondary small rounded-pill px-3"
                            :disabled="pagination.current_page === pagination.total_pages"
                            @click="changePage(pagination.current_page + 1)"
                        >
                            Next <i class="bi bi-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide Panel -->
        <div v-if="panelOpen" class="slide-backdrop" @click="closePanel"></div>
        <transition name="slide">
            <div v-if="panelOpen" class="slide-panel d-flex flex-column h-100 bg-white">
                <!-- Panel Header -->
                <div class="p-4 border-bottom position-sticky top-0 bg-white" style="z-index: 2">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div
                                class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5"
                                style="width: 50px; height: 50px"
                            >
                                {{ selectedEmployee ? getInitials(selectedEmployee.nama) : '' }}
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">{{ selectedEmployee?.nama }}</h5>
                                <div class="text-muted" style="font-size: 0.85rem">
                                    {{ selectedEmployee?.departemen }} &bull; {{ selectedEmployee?.divisi }}
                                </div>
                            </div>
                        </div>
                        <button
                            class="btn btn-sm btn-light rounded-circle"
                            @click="closePanel"
                            style="
                                width: 32px;
                                height: 32px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            "
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Panel Tabs -->
                    <ul class="nav nav-pills nav-fill gap-2 p-1 bg-light rounded-pill border">
                        <li class="nav-item">
                            <button
                                class="nav-link rounded-pill py-2 w-100 fw-medium"
                                :class="panelFilter === 'all' ? 'active shadow-sm' : 'text-muted'"
                                @click="panelFilter = 'all'"
                            >
                                Semua
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link rounded-pill py-2 w-100 fw-medium"
                                :class="
                                    panelFilter === 'in-progress'
                                        ? 'active bg-warning text-dark shadow-sm'
                                        : 'text-muted'
                                "
                                @click="panelFilter = 'in-progress'"
                            >
                                Berlangsung
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                class="nav-link rounded-pill py-2 w-100 fw-medium"
                                :class="
                                    panelFilter === 'completed'
                                        ? 'active bg-success text-white shadow-sm'
                                        : 'text-muted'
                                "
                                @click="panelFilter = 'completed'"
                            >
                                Selesai
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Panel Content -->
                <div class="p-4 flex-grow-1 overflow-auto bg-light position-relative">
                    <div v-if="navigatingToDetail" class="panel-navigation-overlay">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span>Menyiapkan detail training...</span>
                    </div>
                    <div v-if="panelLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div v-else-if="filteredTrainings.length === 0" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 mb-3 text-secondary" style="opacity: 0.5"></i>
                        <p>Tidak ada training ditemukan.</p>
                    </div>
                    <div v-else class="d-flex flex-column gap-3">
                        <div
                            v-for="t in filteredTrainings"
                            :key="t.id_penjadwalan"
                            class="stitch-card bg-white p-3 rounded-4 border shadow-sm item-hoverable"
                            :class="{ 'is-disabled-card': navigatingToDetail }"
                            style="cursor: pointer; transition: all 0.2s"
                            @click="!navigatingToDetail && goToDetail(t)"
                        >
                            <div class="d-flex gap-3">
                                <!-- Thumbnail -->
                                <div
                                    class="bg-light rounded-3 overflow-hidden flex-shrink-0"
                                    style="width: 80px; height: 80px"
                                >
                                    <img
                                        v-if="t.thumbnail_url"
                                        :src="t.thumbnail_url"
                                        class="w-100 h-100"
                                        style="object-fit: cover"
                                    />
                                    <div
                                        v-else
                                        class="w-100 h-100 d-flex align-items-center justify-content-center bg-primary-subtle text-primary"
                                    >
                                        <i class="bi bi-mortarboard fs-4"></i>
                                    </div>
                                </div>
                                <!-- Info -->
                                <div class="flex-grow-1 overflow-hidden d-flex flex-column">
                                    <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size: 0.95rem">
                                        {{ t.judul }}
                                    </h6>
                                    <div
                                        class="text-muted d-flex align-items-center flex-wrap gap-2 mb-2"
                                        style="font-size: 0.75rem"
                                    >
                                        <span class="d-inline-flex align-items-center gap-1" title="Tanggal Enroll">
                                            <i class="bi bi-calendar-event"></i>
                                            Enroll: {{ formatDate(t.tanggal_enroll) }}
                                        </span>
                                        <span class="d-inline-flex align-items-center gap-1" title="Tanggal Ditugaskan">
                                            <i class="bi bi-calendar-check"></i>
                                            Ditugaskan:
                                            {{ t.tanggal_ditugaskan ? formatDate(t.tanggal_ditugaskan) : '-' }}
                                        </span>
                                        <span
                                            v-if="
                                                t.status === 'completed' &&
                                                (t.total_score_display || t.final_score_display)
                                            "
                                            class="training-score-chip"
                                            :title="
                                                t.total_score_display
                                                    ? `Total score: ${t.total_score_display}`
                                                    : t.final_score_tooltip || `Skor akhir: ${t.final_score_display}`
                                            "
                                        >
                                            Skor {{ t.total_score_display || t.final_score_display }}
                                        </span>
                                        <span
                                            v-if="t.status === 'completed' && t.score_learning_gain_display"
                                            class="training-learning-gain-chip"
                                            :title="`Learning gain: ${t.score_learning_gain_display}`"
                                        >
                                            LG {{ t.score_learning_gain_display }}
                                        </span>
                                    </div>

                                    <div v-if="t.attendance" class="attendance-row mb-2">
                                        <span class="attendance-pill" :class="getAttendanceClass(t)">
                                            <i class="bi me-1" :class="getAttendanceIcon(t)"></i>
                                            {{ getAttendanceLabel(t) }}
                                        </span>
                                    </div>

                                    <!-- Progress & Status -->
                                    <div class="d-flex align-items-center justify-content-between mt-auto">
                                        <div class="d-flex align-items-center gap-2 flex-grow-1 me-3">
                                            <div
                                                class="progress flex-grow-1"
                                                style="height: 6px; background-color: #e2e8f0"
                                            >
                                                <div
                                                    class="progress-bar rounded-pill"
                                                    :class="t.progress === 100 ? 'bg-success' : 'bg-primary'"
                                                    :style="{ width: t.progress + '%' }"
                                                ></div>
                                            </div>
                                            <span class="text-dark fw-bold" style="font-size: 0.75rem"
                                                >{{ t.progress }}%</span
                                            >
                                        </div>
                                        <span
                                            v-if="t.status === 'completed'"
                                            class="badge bg-success-subtle text-success rounded-pill px-2 py-1"
                                            ><i class="bi bi-check-circle-fill me-1"></i>Selesai</span
                                        >
                                        <span
                                            v-else-if="t.status === 'not-started'"
                                            class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1"
                                            ><i class="bi bi-dash-circle me-1"></i>Belum</span
                                        >
                                        <span v-else class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1"
                                            ><i class="bi bi-hourglass-split me-1"></i>Berlangsung</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Export modal & progress panel -->
        <ExportTrainingModal
            :show="exportModalOpen"
            @close="exportModalOpen = false"
            @dispatched="onExportDispatched"
        />
        <!-- <ExportProgressPanel ref="exportPanel" /> -->
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import ModernDataTable from '@/components/legacy/table-noir.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';
import ExportTrainingModal from './components/ExportTrainingModal.vue';
import ExportProgressPanel from './components/ExportProgressPanel.vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

export default {
    name: 'AdminTrainingMonitoringAdmin',
    components: {
        HeaderKpi,
        QuickStat,
        ModernDataTable,
        FilterPanel,
        ExportTrainingModal,
        ExportProgressPanel,
    },
    data() {
        return {
            viewMode: 'table',
            employees: [],
            loading: false,
            pagination: { current_page: 1, total_pages: 1, total: 0 },
            summary: { total_karyawan: 0, total_berlangsung: 0, total_selesai: 0 },

            tableFilters: { search: '', dept: [], divisi: [], lokasi: [], status: '', date_mode: 'enroll', date_range: [] },
            deptOptions: [],
            divisiOptions: [],
            lokasiOptions: [],
            limit: 15,

            tableColumns: [
                { key: 'karyawan', title: 'Karyawan', width: '280px', sortable: false },
                { key: 'divisi', title: 'Divisi', sortable: false },
                { key: 'departemen', title: 'Departemen', sortable: false },
                { key: 'jabatan', title: 'Jabatan', sortable: false },
                { key: 'training_berlangsung', title: 'Berlangsung', align: 'center', width: '120px', sortable: false },
                { key: 'training_selesai', title: 'Selesai', align: 'center', width: '100px', sortable: false },
                { key: 'actions', title: 'Aksi', align: 'center', width: '100px', sortable: false },
            ],

            // Export State
            exportModalOpen: false,

            // Slide Panel State
            panelOpen: false,
            panelLoading: false,
            navigatingToDetail: false,
            selectedEmployee: null,
            employeeTrainings: [],
            panelFilter: 'all',
        };
    },
    computed: {
        dayNight() {
            const hour = new Date().getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: 'Asia/Jakarta',
            };
            return new Date().toLocaleDateString('id-ID', options);
        },
        quickStatField() {
            return [
                {
                    key: 'karyawan',
                    title: 'Total Karyawan',
                    value: this.summary.total_karyawan,
                    icon: 'bi bi-people-fill',
                    colorClass: 'text-primary',
                    bgClass: 'bg-primary-subtle border-primary-subtle',
                    size: 'col-4',
                },
                {
                    key: 'berlangsung',
                    title: 'Training Berlangsung',
                    value: this.summary.total_berlangsung,
                    icon: 'bi bi-hourglass-split',
                    colorClass: 'text-warning',
                    bgClass: 'bg-warning-subtle border-warning-subtle',
                    size: 'col-4',
                },
                {
                    key: 'selesai',
                    title: 'Training Selesai',
                    value: this.summary.total_selesai,
                    icon: 'bi bi-check-circle-fill',
                    colorClass: 'text-success',
                    bgClass: 'bg-success-subtle border-success-subtle',
                    size: 'col-4',
                },
            ];
        },
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: 'Cari nama...',
                showViewToggle: false,
                showActiveFilters: true,
                showActions: true,
                showResultsCount: true,
                showReset: true,
                showClearAll: true,
                filters: [
                    {
                        key: 'dept',
                        label: 'Departemen',
                        type: 'select',
                        placeholder: 'Semua Departemen',
                        filterable: true,
                        clearable: true,
                        multiple: true,
                        maxCollapseTags: 1,
                        options: this.deptOptions,
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
                        options: this.divisiOptions,
                        col: 4,
                    },
                    {
                        key: 'lokasi',
                        label: 'Lokasi Kerja',
                        type: 'select',
                        placeholder: 'Semua Lokasi',
                        filterable: true,
                        clearable: true,
                        multiple: true,
                        maxCollapseTags: 1,
                        options: this.lokasiOptions,
                        col: 4,
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        placeholder: 'Semua Status',
                        options: [
                            { value: 'all', label: 'Semua Status' },
                            { value: 'in-progress', label: 'Ada yg Berlangsung' },
                            { value: 'completed', label: 'Selesai Semua' },
                        ],
                        col: 4,
                    },
                    {
                        key: 'date_mode',
                        label: 'Mode Tanggal',
                        type: 'select',
                        clearable: false,
                        options: [
                            { value: 'enroll', label: 'Tanggal Enroll' },
                            { value: 'created', label: 'Tanggal Ditugaskan' },
                        ],
                        col: 4,
                    },
                    {
                        key: 'date_range',
                        label: 'Rentang Tanggal',
                        type: 'daterange',
                        dateType: 'daterange',
                        valueFormat: 'YYYY-MM-DD',
                        format: 'DD MMM YYYY',
                        placeholder: 'Pilih rentang tanggal',
                        col: 4,
                    },
                ],
            };
        },
        filteredTrainings() {
            if (this.panelFilter === 'all') return this.employeeTrainings || [];
            return this.employeeTrainings.filter((t) => t.status === this.panelFilter) || [];
        },
    },
    mounted() {
        this.fetchEmployees();
        this.fetchFilterOptions();
    },
    methods: {
        getInitials(name) {
            if (!name) return '';
            return name
                .split(' ')
                .slice(0, 2)
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            if (isNaN(date)) return dateStr;
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
        async fetchFilterOptions() {
            try {
                const response = await axios.get('/lms/admin/monitoring/filter-options');
                if (response.data.success) {
                    this.deptOptions = (response.data.result.departemen || []).map((d) => ({
                        value: d,
                        label: d,
                    }));
                    this.divisiOptions = (response.data.result.divisi || []).map((d) => ({
                        value: d,
                        label: d,
                    }));
                    this.lokasiOptions = (response.data.result.lokasi || []).map((d) => ({
                        value: d,
                        label: d,
                    }));
                }
            } catch (error) {
                console.error('Error fetching filter options', error);
            }
        },
        async fetchEmployees(page = 1) {
            this.loading = true;
            try {
                const response = await axios.get('/lms/admin/monitoring/employees', {
                    params: {
                        page: page,
                        limit: this.limit,
                        q: this.tableFilters.search,
                        dept: this.tableFilters.dept,
                        divisi: this.tableFilters.divisi,
                        lokasi: this.tableFilters.lokasi,
                        status: this.tableFilters.status !== 'all' ? this.tableFilters.status : '',
                        date_mode: this.tableFilters.date_mode || 'enroll',
                        date_start: this.tableFilters.date_range?.[0] || '',
                        date_end: this.tableFilters.date_range?.[1] || '',
                    },
                });

                if (response.data.success) {
                    this.employees = response.data.result;
                    if (response.data.pagination) {
                        this.pagination = {
                            current_page: response.data.pagination.current_page,
                            total_pages: response.data.pagination.total_pages,
                            total: response.data.pagination.total,
                        };
                    } else {
                        this.pagination = {
                            current_page: response.data.page,
                            total_pages: response.data.total_page,
                            total: response.data.total_data,
                        };
                    }
                    this.summary = response.data.summary;
                }
            } catch (error) {
                console.error('Error fetching employees', error);
                ElNotification({
                    title: 'Error',
                    message: 'Gagal mengambil data karyawan.',
                    type: 'error',
                    position: 'top-right',
                });
            } finally {
                this.loading = false;
            }
        },
        handleSearch(query) {
            this.tableFilters.search = query;
            this.fetchEmployees(1);
        },
        handleFiltersUpdate(filters) {
            this.tableFilters = { ...this.tableFilters, ...filters };
            this.fetchEmployees(1);
        },
        onExportDispatched() {
            // Tampilkan & refresh panel progress agar entri baru langsung muncul.
            if (this.$refs.exportPanel) {
                this.$refs.exportPanel.refresh();
            }
        },
        changePage(page) {
            if (page >= 1 && page <= this.pagination.total_pages) {
                this.fetchEmployees(page);
            }
        },
        getPaginationRange() {
            const current = this.pagination.current_page;
            const total = this.pagination.total_pages;
            const range = [];
            const delta = 2;

            for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
                range.push(i);
            }
            return range;
        },

        // --- Panel Methods ---
        async openSlidePanel(employee) {
            this.selectedEmployee = employee;
            this.panelOpen = true;
            this.panelFilter = 'all';
            this.employeeTrainings = [];
            this.panelLoading = true;
            document.body.style.overflow = 'hidden';

            try {
                const response = await axios.get(
                    `/lms/admin/monitoring/employees/${employee.kode_karyawan}/trainings`,
                    {
                        params: {
                            date_mode: this.tableFilters.date_mode || 'enroll',
                            date_start: this.tableFilters.date_range?.[0] || '',
                            date_end: this.tableFilters.date_range?.[1] || '',
                        },
                    },
                );
                if (response.data.success) {
                    this.employeeTrainings = response.data.result.trainings;
                }
            } catch (error) {
                console.error('Error fetching trainings', error);
            } finally {
                this.panelLoading = false;
            }
        },
        closePanel() {
            this.panelOpen = false;
            setTimeout(() => {
                this.selectedEmployee = null;
                document.body.style.overflow = '';
            }, 300);
        },
        goToDetail(training) {
            if (this.navigatingToDetail) {
                return;
            }

            if (!training.id_penjadwalan) {
                ElNotification({
                    title: 'Oops',
                    message: 'Penjadwalan tidak tersedia untuk training ini.',
                    type: 'warning',
                    position: 'top-right',
                });
                return;
            }

            this.navigatingToDetail = true;
            this.$inertia.visit(
                `/lms/admin/monitoring/training/schedule/${training.id_penjadwalan}/${this.selectedEmployee.kode_karyawan}`,
                {
                    onFinish: () => {
                        this.navigatingToDetail = false;
                    },
                },
            );
        },
        getAttendanceLabel(training) {
            const attendance = training?.attendance;
            if (!attendance) {
                return 'Absensi tidak tersedia';
            }

            return attendance.label || `Check-in ${attendance.checked_in || 0}/${attendance.total || 0}`;
        },
        getAttendanceClass(training) {
            const status = training?.attendance?.status;
            if (status === 'completed') return 'is-completed';
            if (status === 'partial') return 'is-partial';
            if (status === 'not-started') return 'is-not-started';
            return 'is-not-required';
        },
        getAttendanceIcon(training) {
            const status = training?.attendance?.status;
            if (status === 'completed') return 'bi-check-circle-fill';
            if (status === 'partial') return 'bi-hourglass-split';
            if (status === 'not-started') return 'bi-dash-circle';
            return 'bi-info-circle';
        },
    },
};
</script>

<style scoped>
.monitoring-app-container {
    /* padding: 1.5rem; */
    /* background-color: #f8f9fa; */
    min-height: 100vh;
}
@media (max-width: 768px) {
    .monitoring-app-container {
        padding: 1rem;
    }
}

/* Export trigger di samping filter */
.filter-with-export {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.fwe-filter {
    flex: 1;
    min-width: 0;
}
.export-trigger-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--hcis-indigo-600);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
    margin-top: 2px;
    transition: background 0.15s;
}
.export-trigger-btn:hover {
    background: var(--hcis-indigo-700);
}
@media (max-width: 768px) {
    .filter-with-export {
        flex-direction: column;
    }
    .export-trigger-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Slide Panel Styles */
.slide-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(2px);
    z-index: 1040;
}
.slide-panel {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 460px;
    max-width: 100vw;
    z-index: 1050;
    box-shadow: -4px 0 24px rgba(0, 0, 0, 0.1);
}
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.item-hoverable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
    border-color: var(--hcis-slate-300) !important;
}

.panel-navigation-overlay {
    position: absolute;
    inset: 1rem;
    z-index: 10;
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.92);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: var(--hcis-slate-700);
    font-size: 0.9rem;
    font-weight: 600;
}

.is-disabled-card {
    pointer-events: none;
    opacity: 0.75;
}

.nav-pills .nav-link {
    font-size: 0.85rem;
    letter-spacing: 0.2px;
}

.training-score-chip {
    display: inline-flex;
    align-items: center;
    margin-left: auto;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: var(--hcis-emerald-50);
    color: var(--hcis-green-700);
    border: 1px solid var(--hcis-green-200);
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.training-learning-gain-chip {
    display: inline-flex;
    align-items: center;
    margin-left: 0.25rem;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border: 1px solid var(--hcis-indigo-200);
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
}

.attendance-row {
    display: flex;
    align-items: center;
}

.attendance-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.52rem;
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
}

.attendance-pill.is-completed {
    background: var(--hcis-emerald-50);
    color: var(--hcis-green-700);
    border-color: var(--hcis-green-200);
}

.attendance-pill.is-partial {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}

.attendance-pill.is-not-started {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border-color: var(--hcis-slate-200);
}

.attendance-pill.is-not-required {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-color: var(--hcis-indigo-200);
}
</style>
