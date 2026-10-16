<template>
    <div class="kl-app-container" :class="{ 'sop-scope': isSop }">
        <!-- Header Hero untuk SOP (Menggunakan HcisHeader & HcisStatGroup Signature) -->
        <template v-if="isSop">
            <HcisHeader
                title="Library Konten"
                kicker="Tata Kelola · Media & Berkas"
                description="Pusat pustaka berkas, video, dan dokumen rujukan resmi untuk penyusunan dokumen."
                icon="folder-symlink-fill"
                sentiment="brand"
                size="md"
                animated
                class="mb-4"
            >
                <template #badge>
                    <HcisBadge
                        v-if="!isLoading"
                        :label="`${categories.length} Kategori · ${files.length} Konten`"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                        radar
                        dot
                    />
                </template>

                <template #actions>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="tools"
                            label="Document Builder"
                            href="/sop/admin/builder"
                            title="Buka Document Builder"
                            aria-label="Buka Document Builder"
                        />
                        <HcisButton
                            variant="glass"
                            size="md"
                            leading-icon="shield-check"
                            label="Otomasi"
                            href="/sop/admin/kebijakan"
                            title="Buka Otomasi Dokumen"
                            aria-label="Buka Otomasi Dokumen"
                        />
                        <div class="hcis-date-pill">
                            <HcisIcon name="calendar3" size="2xs" />
                            <span>{{ formattedDate }}</span>
                        </div>
                    </div>
                </template>
            </HcisHeader>

            <!-- Konsol Ringkasan KPI Konten -->
            <HcisStatGroup
                :cols="3"
                gap="md"
                layout="compact"
                appearance="crystal"
                class="mb-4"
            >
                <HcisStatCard
                    label="Total Kategori"
                    :value="categories.length || 0"
                    suffix="Kategori"
                    caption="Kategori pustaka berkas & media"
                    icon="folder2-open"
                    sentiment="brand"
                    appearance="crystal"
                    pattern="grid"
                    :loading="isLoading"
                />

                <HcisStatCard
                    label="Konten Terlihat"
                    :value="files.length || 0"
                    suffix="Berkas"
                    caption="Total materi rujukan siap pakai"
                    icon="file-earmark-play"
                    sentiment="teal"
                    appearance="crystal"
                    pattern="dots"
                    :loading="isLoading"
                />

                <HcisStatCard
                    label="Item Dipilih"
                    :value="selectedFileIds.length || 0"
                    suffix="Item"
                    caption="Konten terseleksi untuk aksi massal"
                    icon="check2-square"
                    sentiment="purple"
                    appearance="crystal"
                    pattern="rings"
                />
            </HcisStatGroup>
        </template>

        <!-- Header LMS Biasa -->
        <template v-else>
            <HeaderKpi :title="pageTitle" :subtitle="pageSubtitle" :dayNight="dayNight" :formattedDate="formattedDate" />
            <div class="mb-4">
                <QuickStat :stats="quickStatItems" />
            </div>
        </template>

        <div class="kl-main-wrapper">
            <ApprovalTabs :tabs="tabs" :activeTab="activeTab" @tab-change="handleTabChange">
                <template #konten>
                    <div class="kl-tab-panel">
                        <!-- Panel Header -->
                        <div class="kl-panel-header">
                            <div class="kl-panel-header-info">
                                <div class="avatar-sm bg-primary-subtle text-primary kl-panel-header-icon">
                                    <i class="bi bi-folder2-open"></i>
                                </div>
                                <div>
                                    <h2 class="kl-panel-title fs-6 fw-bold text-dark mb-0">
                                        {{ isSop ? 'Library Konten' : 'Manajemen Konten' }}
                                    </h2>
                                    <p class="kl-panel-subtitle text-xs text-muted mb-0">
                                        {{ isSop ? 'Kelola kategori dan berkas materi rujukan untuk Document Builder' : 'Kelola kategori dan materi pembelajaran untuk LMS' }}
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button
                                    v-if="currentCategory && selectedFileIds.length === 1"
                                    class="stitch-btn secondary text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="openRenameFileModal"
                                >
                                    <i class="bi bi-pencil-square text-xs"></i>
                                    <span>Rename Konten</span>
                                </button>

                                <button
                                    v-if="currentCategory && selectedFileIds.length > 0"
                                    class="stitch-btn danger text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="handleDeleteSelected"
                                >
                                    <i
                                        class="bi text-xs"
                                        :class="isCheckingUsage ? 'bi-arrow-repeat spin' : 'bi-trash3'"
                                    ></i>
                                    <span>
                                        {{
                                            isCheckingUsage
                                                ? 'Memeriksa penggunaan konten...'
                                                : `Hapus (${selectedFileIds.length})`
                                        }}
                                    </span>
                                </button>

                                <button
                                    v-if="currentCategory"
                                    class="stitch-btn secondary text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="openRenameCategoryModal"
                                >
                                    <i class="bi bi-pencil-square text-xs"></i>
                                    <span>Rename Kategori</span>
                                </button>

                                <button
                                    v-if="currentCategory"
                                    class="stitch-btn danger text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="openDeleteCategoryModal"
                                >
                                    <i
                                        class="bi text-xs"
                                        :class="isCheckingUsage ? 'bi-arrow-repeat spin' : 'bi-folder-x'"
                                    ></i>
                                    <span>{{
                                        isCheckingUsage ? 'Memeriksa penggunaan kategori...' : 'Hapus Kategori'
                                    }}</span>
                                </button>

                                <button
                                    v-if="!currentCategory"
                                    class="stitch-btn primary text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="handleAddAction('category')"
                                >
                                    <i class="bi bi-folder-plus text-xs"></i>
                                    <span>Kategori Baru</span>
                                </button>

                                <button
                                    v-if="currentCategory"
                                    class="stitch-btn primary text-xs"
                                    :disabled="isActionLoading || isCheckingUsage"
                                    @click="handleAddAction('upload')"
                                >
                                    <i class="bi bi-cloud-arrow-up text-xs"></i>
                                    <span>Upload Konten</span>
                                </button>
                            </div>
                        </div>

                        <!-- Breadcrumbs -->
                        <div class="stitch-card border-0 shadow-sm p-3" v-if="currentCategory">
                            <MediaBreadcrumbs :items="breadcrumbItems" @navigate="handleBreadcrumbNavigate" />
                        </div>

                        <!-- Filter Panel -->
                        <ModernFilter
                            :config="filterConfig"
                            :filters="tableFilters"
                            @filters-update="handleFiltersUpdate"
                        />

                        <!-- Main Content Container -->
                        <div class="overflow-hidden" style="min-height: 400px">
                            <!-- Loading State -->
                            <div v-if="isLoading" class="loading-state">
                                <div class="loading-spinner">
                                    <i class="bi bi-arrow-repeat spin"></i>
                                </div>
                                <p class="loading-description">Memuat data...</p>
                            </div>

                            <!-- Content Area -->
                            <ContentContainer
                                v-else
                                :categories="currentCategory === null ? filteredCategories : []"
                                :files="currentCategory !== null ? filteredContents : []"
                                :viewMode="viewMode"
                                :enableSelection="currentCategory !== null"
                                :selectedFileIds="selectedFileIds"
                                @item-click="handleItemClick"
                                @toggle-file-selection="toggleFileSelection"
                                @toggle-select-all="toggleSelectAllFiles"
                            />
                        </div>
                    </div>
                </template>
                <template #riwayat>
                    <div class="kl-tab-panel">
                        <div class="kl-panel-header">
                            <div class="kl-panel-header-info">
                                <div class="avatar-sm bg-warning-subtle text-warning kl-panel-header-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <h2 class="kl-panel-title fs-6 fw-bold text-dark mb-0">Riwayat Pembelajaran</h2>
                                    <p class="kl-panel-subtitle text-xs text-muted mb-0">
                                        Log aktivitas materi dan konten
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="stitch-card p-4 border-0 shadow-sm rounded-4 d-flex align-items-center justify-content-center text-muted"
                            style="min-height: 400px"
                        >
                            Belum ada riwayat pembelajaran.
                        </div>
                    </div>
                </template>
            </ApprovalTabs>
        </div>

        <!-- Details Panel -->
        <DetailsPanel
            :show="showDetails"
            :item="selectedItem"
            :mengunduh="mengunduh"
            @close="closeDetails"
            @open-viewer="openViewerFromDetails"
            @render-ulang="handleRenderUlang"
            @verifikasi-ulang="handleVerifikasiUlang"
            @unduh="handleUnduh"
        />

        <MediaViewerPanel
            :show="showMediaViewer"
            :item="viewerItem"
            :mengunduh="mengunduh"
            @close="closeMediaViewer"
            @unduh="handleUnduh"
        />

        <!-- Create Category Modal -->
        <CreateCategoryModal v-model:show="showCreateCategoryModal" @success="handleCategoryCreated" />

        <!-- Upload Konten Modal -->
        <UploadKontenModal
            v-model:show="showUploadKontenModal"
            :categoryId="currentCategory ? currentCategory.id : null"
            :categoryName="currentCategory ? currentCategory.name : ''"
            :scope="scope"
            @success="handleUploadSuccess"
        />

        <!-- Rename Modal -->
        <Modal
            :show="showRenameModal"
            :title="renameType === 'category' ? 'Rename Kategori' : 'Rename Konten'"
            size="small"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            cancelText="Batal"
            confirmText="Simpan"
            :loading="isRenaming"
            loadingText="Menyimpan..."
            :confirmDisabled="!renameInput.trim() || isRenaming"
            @close="closeRenameModal"
            @confirm="confirmRename"
        >
            <template #body>
                <div class="mb-3">
                    <label class="form-label text-xs fw-bold text-muted text-uppercase mb-2">Nama Baru</label>
                    <input
                        v-model="renameInput"
                        type="text"
                        class="form-control"
                        placeholder="Masukkan nama baru..."
                        @keyup.enter="renameInput.trim() && !isRenaming ? confirmRename() : null"
                        ref="renameInputRef"
                    />
                </div>
            </template>
        </Modal>

        <Modal
            :show="showDeleteSelectedModal"
            title="Konfirmasi Hapus Konten"
            subtitle="Pilih lanjut hanya jika yakin konten akan dihapus permanen dari tampilan aktif"
            size="medium"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            cancelText="Batal"
            confirmText="Hapus Konten"
            :loading="isDeletingSelected"
            loadingText="Menghapus konten..."
            :confirmDisabled="!deleteSelectedAcknowledge || isDeletingSelected"
            @close="closeDeleteSelectedModal"
            @confirm="confirmDeleteSelected"
        >
            <template #body>
                <div class="kl-modal-stack">
                    <div class="kl-warning-box">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <p class="mb-1 fw-semibold text-dark">{{ selectedFileIds.length }} konten akan dihapus.</p>
                            <small class="text-muted"
                                >Data yang dihapus tidak bisa dikembalikan dari daftar aktif.</small
                            >
                        </div>
                    </div>

                    <label class="kl-check-row">
                        <input v-model="deleteSelectedAcknowledge" type="checkbox" class="form-check-input" />
                        <span>Saya yakin untuk menghapus konten yang dipilih.</span>
                    </label>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showDeleteCategoryModal"
            title="Konfirmasi Hapus Kategori"
            :subtitle="`Kategori ${currentCategory ? currentCategory.name : ''} akan dihapus beserta isi kontennya.`"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="true"
            cancelText="Batal"
            confirmText="Hapus Kategori"
            :loading="isDeletingCategory"
            loadingText="Menghapus kategori dan konten..."
            :confirmDisabled="!canDeleteCategory"
            @close="closeDeleteCategoryModal"
            @confirm="confirmDeleteCategory"
        >
            <template #body>
                <div class="kl-modal-stack">
                    <div class="kl-warning-box">
                        <i class="bi bi-trash3-fill"></i>
                        <div>
                            <p class="mb-1 fw-semibold text-dark">Checklist semua konten yang ikut dihapus.</p>
                            <small class="text-muted"
                                >Semua item harus dicentang satu per satu sebelum tombol hapus aktif.</small
                            >
                        </div>
                    </div>

                    <div class="kl-delete-list" v-if="deleteCategoryFileItems.length > 0">
                        <label v-for="file in deleteCategoryFileItems" :key="file.id" class="kl-delete-list-item">
                            <input
                                :checked="isCategoryFileChecked(file.id)"
                                type="checkbox"
                                class="form-check-input"
                                @change="setCategoryFileCheck(file.id, $event.target.checked)"
                            />
                            <span class="kl-delete-list-title">{{ file.name }}</span>
                        </label>
                    </div>

                    <div v-else class="kl-empty-list">Tidak ada konten aktif pada kategori ini.</div>

                    <label class="kl-check-row">
                        <input v-model="deleteCategoryAcknowledge" type="checkbox" class="form-check-input" />
                        <span>Saya yakin untuk menghapus kategori ini beserta seluruh kontennya.</span>
                    </label>
                </div>
            </template>
        </Modal>

        <Modal
            :show="showFeedbackModal"
            :title="feedbackModal.title"
            :subtitle="feedbackModal.type === 'error' ? 'Terjadi kendala pada proses' : ''"
            size="small"
            :showFooter="true"
            :showCancel="false"
            :showConfirm="true"
            confirmText="Tutup"
            @close="closeFeedbackModal"
            @confirm="closeFeedbackModal"
        >
            <template #body>
                <div class="kl-feedback-body" :class="`is-${feedbackModal.type}`">
                    <i
                        class="bi"
                        :class="
                            feedbackModal.type === 'error'
                                ? 'bi-x-octagon-fill'
                                : feedbackModal.type === 'warning'
                                  ? 'bi-exclamation-triangle-fill'
                                  : 'bi-check-circle-fill'
                        "
                    ></i>
                    <p class="mb-0">{{ feedbackModal.message }}</p>
                </div>
            </template>
        </Modal>

        <!-- Usage Warning Modal (Content is still in use) -->
        <Modal
            :show="showUsageWarningModal"
            title="⚠️ Perhatian: Konten Sedang Digunakan"
            subtitle="Konten ini masih digunakan dalam training atau penjadwalan"
            size="large"
            :showFooter="true"
            :showCancel="true"
            :showConfirm="false"
            cancelText="Tutup"
            @close="closeUsageWarningModal"
        >
            <template #body>
                <div class="kl-modal-stack">
                    <div class="kl-warning-box">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <p class="mb-1 fw-semibold text-dark">Konten tidak dapat dihapus saat ini.</p>
                            <small class="text-muted">
                                Konten ini masih digunakan dalam {{ usageWarningData.total_trainings }} training dan
                                {{ usageWarningData.total_penjadwalan }} penugasan training.
                            </small>
                        </div>
                    </div>

                    <div v-if="usageWarningData.per_content && usageWarningData.per_content.length > 0" class="mb-3">
                        <h6 class="text-dark fw-bold mb-3">📌 Detail Konten Yang Masih Nyangkut:</h6>
                        <div class="usage-content-groups">
                            <div
                                v-for="group in usageWarningData.per_content"
                                :key="group.content?.id || group.content?.id_hash"
                                class="usage-content-group"
                            >
                                <div class="usage-content-header">
                                    <strong>{{ group.content?.name || '-' }}</strong>
                                    <small class="text-muted">
                                        {{ group.total_trainings || 0 }} training •
                                        {{ group.total_penjadwalan || 0 }} penugasan
                                    </small>
                                </div>

                                <div class="usage-inline-block">
                                    <p class="usage-inline-title mb-2">Training yang perlu diubah:</p>
                                    <div v-if="group.trainings && group.trainings.length > 0" class="usage-list-item">
                                        <div
                                            v-for="training in group.trainings"
                                            :key="training.id"
                                            class="usage-item usage-item-clickable"
                                            @click="openTrainingUsage(training)"
                                        >
                                            <div class="usage-item-header">
                                                <span class="usage-item-title">{{ training.name }}</span>
                                                <span class="usage-item-badge" v-if="training.scheduling_count > 0">
                                                    {{ training.scheduling_count }} penugasan
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-muted small">Tidak ada training aktif.</div>
                                </div>

                                <div class="usage-inline-block mt-3">
                                    <p class="usage-inline-title mb-2">Penugasan yang terdampak :</p>
                                    <div
                                        v-if="group.penjadwalan && group.penjadwalan.length > 0"
                                        class="usage-list-item"
                                    >
                                        <div
                                            v-for="pj in group.penjadwalan"
                                            :key="pj.id"
                                            class="usage-item usage-item-clickable"
                                            @click="openAssignmentUsage(pj)"
                                        >
                                            <div class="usage-item-header">
                                                <span class="usage-item-title">{{ pj.training_name }}</span>
                                                <span class="usage-status-badge">{{ pj.peserta_count }} peserta</span>
                                            </div>
                                            <small class="text-muted d-block">
                                                Ditugaskan: {{ pj.assigned_at || pj.jadwal_mulai || '-' }}
                                            </small>
                                            <small class="text-muted d-block">
                                                Karyawan: {{ pj.assigned_to_name || '-' }}
                                                <span v-if="pj.assigned_to_kode">({{ pj.assigned_to_kode }})</span>
                                            </small>
                                        </div>
                                    </div>
                                    <div v-else class="text-muted small">Tidak ada penugasan aktif.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle-fill"></i>
                        <small>
                            Untuk menghapus konten ini, silakan buka halaman <strong>Training Management</strong> atau
                            <strong>Penjadwalan Training</strong> untuk menonaktifkan penggunaan konten terlebih dahulu.
                        </small>
                    </div>
                </div>
            </template>
        </Modal>

        <div v-if="isCheckingUsage" class="kl-processing-overlay">
            <div class="kl-processing-card">
                <i class="bi bi-arrow-repeat spin"></i>
                <p class="mb-1 fw-semibold text-dark">Sedang memeriksa penggunaan konten...</p>
                <small class="text-muted">Mohon tunggu, request sedang diproses.</small>
            </div>
        </div>
    </div>
</template>

<script>
// No need for MediaToolbar, using ModernFilter instead
import DetailsPanel from './components/ui/DetailsPanel.vue';
import MediaViewerPanel from './components/ui/MediaViewerPanel.vue';
import ContentContainer from './components/ui/ContentContainer.vue';
import MediaBreadcrumbs from './components/ui/breadcrumb.vue';
import CreateCategoryModal from './components/ui/CreateCategoryModal.vue';

// Library konten dipakai bersama LMS & SOP — base path dari URL (SOP di /sop/*, LMS di /lms/*).
const kontenBasePath = () =>
    typeof window !== 'undefined' && window.location.pathname.startsWith('/sop')
        ? '/sop/admin/konten'
        : '/lms/konten-kategori';
import UploadKontenModal from './components/ui/UploadKontenModal.vue';
import HeaderKpi from '@/components/legacy/header.vue';
import QuickStat from '@/components/legacy/quickStat.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import ModernFilter from '@/components/legacy/Filter/modernFilter.vue';
import Modal from '@/components/legacy/modalV2-noir.vue';
import { HcisHeader, HcisBadge, HcisButton, HcisIcon, HcisStatGroup, HcisStatCard } from '@/components/ui';
import '@/Pages/LMS/SOP/Shared/support/sopTheme.css';
import axios from 'axios';

export default {
    name: 'ContentDashboard',
    components: {
        DetailsPanel,
        MediaViewerPanel,
        ContentContainer,
        MediaBreadcrumbs,
        CreateCategoryModal,
        UploadKontenModal,
        HeaderKpi,
        QuickStat,
        ApprovalTabs,
        ModernFilter,
        Modal,
        HcisHeader,
        HcisBadge,
        HcisButton,
        HcisIcon,
        HcisStatGroup,
        HcisStatCard,
    },
    props: {
        scope: { type: String, default: 'LMS' },
        basePath: { type: String, default: '/lms/konten-kategori' },
    },
    data() {
        return {
            pageTitle: 'Dashboard Konten',
            pageSubtitle: 'Kelola konten training',
            formattedDate: '',
            dayNight: '🌤️',
            clockInterval: null,

            // Tabs config
            tabs: [
                {
                    id: 'konten',
                    icon: 'bi bi-journal-text',
                    label: 'Konten & Kategori',
                    count: null,
                },
            ],
            activeTab: 'konten',

            // Filter status
            tableFilters: { search: '', viewMode: 'grid' },
            searchQuery: '',
            viewMode: 'grid',

            showDetails: false,
            showMediaViewer: false,
            mengunduh: false,
            selectedItem: null,
            viewerItem: null,
            currentCategory: null,
            showCreateCategoryModal: false,
            showUploadKontenModal: false,
            isLoading: false,
            isActionLoading: false,
            selectedFileIds: [],

            showRenameModal: false,
            renameType: '',
            renameTargetId: null,
            renameInput: '',
            isRenaming: false,

            showDeleteSelectedModal: false,
            deleteSelectedAcknowledge: false,
            isDeletingSelected: false,

            showDeleteCategoryModal: false,
            deleteCategoryChecks: {},
            deleteCategoryAcknowledge: false,
            isDeletingCategory: false,

            showFeedbackModal: false,
            feedbackModal: {
                type: 'success',
                title: 'Informasi',
                message: '',
            },

            // Usage warning modal
            showUsageWarningModal: false,
            usageWarningData: {
                per_content: [],
                trainings: [],
                penjadwalan: [],
                total_trainings: 0,
                total_penjadwalan: 0,
            },
            isCheckingUsage: false,
            pendingDeleteAction: null, // 'selected-files' or 'category'

            // Data dari API
            categories: [],
            files: [],
            renderPollTimer: null,
        };
    },
    computed: {
        isSop() {
            return this.scope === 'SOP' || (typeof window !== 'undefined' && window.location.pathname.startsWith('/sop'));
        },
        quickStatItems() {
            return [
                {
                    title: 'Total Kategori',
                    value: this.categories.length ?? 0,
                    icon: 'bi bi-folder2-open',
                    bgIcon: 'bg-primary-light',
                    size: 'col-4',
                },
                {
                    title: 'Konten Terlihat',
                    value: this.files.length ?? 0,
                    icon: 'bi bi-file-earmark-play',
                    bgIcon: 'bg-warning-light',
                    size: 'col-4',
                },
                {
                    title: 'Item Dipilih',
                    value: this.selectedFileIds.length ?? 0,
                    icon: 'bi bi-check2-square',
                    bgIcon: 'bg-success-light',
                    size: 'col-4',
                },
            ];
        },
        filterConfig() {
            return {
                searchable: true,
                searchPlaceholder: this.currentCategory ? 'Cari konten...' : 'Cari kategori...',
                showResultsCount: false,
                showActiveFilters: true,
                showActions: false,
                showReset: true,
                showClearAll: true,
                filters: [
                    {
                        key: 'viewMode',
                        label: 'Tampilan',
                        type: 'viewToggle',
                        col: 4,
                        options: [
                            { value: 'grid', icon: 'bi bi-grid-fill' },
                            { value: 'list', icon: 'bi bi-list-ul' },
                        ],
                    },
                ],
            };
        },
        breadcrumbItems() {
            let items = [{ label: 'Root', id: 'root', isRoot: true }];
            if (!this.currentCategory) return items;
            items.push({
                label: this.currentCategory.name,
                id: this.currentCategory.id,
            });
            return items;
        },
        filteredCategories() {
            if (this.currentCategory) return [];
            let result = this.categories;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter((c) => c.name.toLowerCase().includes(query));
            }
            return result;
        },
        filteredContents() {
            if (!this.currentCategory) return [];
            let result = this.files;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                result = result.filter((f) => f.name.toLowerCase().includes(query));
            }
            return result;
        },
        deleteCategoryFileItems() {
            return (this.files || []).map((file) => ({
                id: String(file.id),
                name: file.name || '-',
            }));
        },
        allCategoryFilesChecked() {
            if (this.deleteCategoryFileItems.length === 0) {
                return true;
            }

            return this.deleteCategoryFileItems.every((file) => !!this.deleteCategoryChecks[file.id]);
        },
        canDeleteCategory() {
            return this.deleteCategoryAcknowledge && this.allCategoryFilesChecked && !this.isDeletingCategory;
        },
    },
    async mounted() {
        await this.fetchKategori();
        // Auto-buka folder bila datang dari Pusat Aktivitas (?kategori=<hashid>).
        this.openCategoryFromQuery();
        this.updateClock();
        this.clockInterval = setInterval(this.updateClock, 1000);
    },
    beforeUnmount() {
        if (this.clockInterval) {
            clearInterval(this.clockInterval);
        }
        this.stopRenderStatusPolling();
    },
    methods: {
        updateClock() {
            const now = new Date();
            const options = {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            };
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            };

            const dateStr = now.toLocaleDateString('id-ID', options);
            const timeStr = now.toLocaleTimeString('id-ID', timeOptions);
            this.formattedDate = dateStr;

            const hour = now.getHours();
            if (hour >= 5 && hour < 11) {
                this.dayNight = 'Pagi 🌅';
            } else if (hour >= 11 && hour < 15) {
                this.dayNight = 'Siang ☀️';
            } else if (hour >= 15 && hour < 18) {
                this.dayNight = 'Sore 🌇';
            } else {
                this.dayNight = 'Malam 🌙';
            }
        },

        // ============ TABS & ACTIONS ============
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
        handleAddAction(actionType) {
            if (actionType === 'category') {
                this.showCreateCategoryModal = true;
            } else if (actionType === 'upload') {
                if (!this.currentCategory) {
                    this.openFeedbackModal({
                        type: 'warning',
                        title: 'Kategori Belum Dipilih',
                        message: 'Pilih kategori terlebih dahulu untuk mengupload konten.',
                    });
                    return;
                }
                this.showUploadKontenModal = true;
            }
        },
        handleFiltersUpdate(updated) {
            this.tableFilters = updated;
            this.searchQuery = updated.search || '';
            if (updated.viewMode) {
                this.viewMode = updated.viewMode;
            }
        },

        // ============ FETCH DATA ============
        async fetchKategori() {
            this.isLoading = true;
            try {
                const response = await axios.get(kontenBasePath() + '/kategori');
                if (response.data.success) {
                    this.categories = response.data.result.data || [];
                }
            } catch (error) {
                console.error('Error fetching kategori:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Memuat Kategori',
                    message: 'Data kategori tidak berhasil dimuat. Coba lagi beberapa saat.',
                });
            } finally {
                this.isLoading = false;
            }
        },
        async fetchKontenByKategori(categoryId) {
            this.isLoading = true;
            try {
                const response = await axios.get(`${kontenBasePath()}/konten/${categoryId}`);
                if (response.data.success) {
                    this.files = response.data.result.data || [];
                    this.selectedFileIds = [];
                    await this.preloadContentThumbnails(this.files);
                    this.scheduleRenderStatusPolling(categoryId);
                }
            } catch (error) {
                console.error('Error fetching konten:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Memuat Konten',
                    message: 'Data konten kategori tidak berhasil dimuat. Coba lagi beberapa saat.',
                });
            } finally {
                this.isLoading = false;
            }
        },

        // Apakah ada berkas PDF yang belum selesai dirender (perlu dipantau)?
        hasPendingRender() {
            return (this.files || []).some((f) => {
                const s = (f.renderStatus || (f.metadata && f.metadata.renderStatus) || '').toUpperCase();
                return s === 'PENDING' || s === 'PROCESSING';
            });
        },

        // Polling ringan (silent, tanpa loading spinner) untuk update badge status
        // render. Hanya jalan selama ada PDF PENDING/PROCESSING di kategori aktif.
        scheduleRenderStatusPolling(categoryId) {
            this.stopRenderStatusPolling();
            if (!this.hasPendingRender()) return;

            this.renderPollTimer = setInterval(async () => {
                // Berhenti bila pindah kategori.
                if (!this.currentCategory || this.currentCategory.id !== categoryId) {
                    this.stopRenderStatusPolling();
                    return;
                }
                try {
                    const res = await axios.get(`${kontenBasePath()}/konten/${categoryId}`);
                    if (res.data.success) {
                        this.files = res.data.result.data || [];
                        if (!this.hasPendingRender()) {
                            this.stopRenderStatusPolling();
                        }
                    }
                } catch (e) {
                    // Diamkan error transien polling; coba lagi tick berikutnya.
                }
            }, 6000);
        },

        stopRenderStatusPolling() {
            if (this.renderPollTimer) {
                clearInterval(this.renderPollTimer);
                this.renderPollTimer = null;
            }
        },
        handleBreadcrumbNavigate(item) {
            if (item.isRoot) {
                this.goToRoot();
            } else {
                this.currentCategory = this.categories.find((c) => c.id === item.id);
                this.searchQuery = '';
                this.tableFilters.search = '';
            }
        },
        // Buka kategori otomatis dari query string ?kategori=<hashid> (dipakai saat
        // user klik item upload di Pusat Aktivitas → "Buka Folder").
        openCategoryFromQuery() {
            const params = new URLSearchParams(window.location.search);
            const kategoriHash = params.get('kategori');
            if (!kategoriHash) return;

            const target = (this.categories || []).find((c) => String(c.id) === String(kategoriHash));
            if (!target) return;

            this.currentCategory = target;
            this.searchQuery = '';
            this.tableFilters.search = '';
            this.selectedFileIds = [];
            this.fetchKontenByKategori(target.id);

            // Bersihkan query agar refresh berikutnya tidak membuka ulang paksa.
            window.history.replaceState({}, '', window.location.pathname);
        },
        handleItemClick(item) {
            if (item.type === 'category') {
                this.currentCategory = item;
                this.searchQuery = '';
                this.tableFilters.search = '';
                this.selectedFileIds = [];
                this.fetchKontenByKategori(item.id);
            } else {
                this.selectedItem = item;
                this.showDetails = true;
                // PDF: metadata penuh (url/size) tidak diambil di list (hemat GCS).
                // Lazy-load saat panel detail dibuka, jika belum ada fileUrl.
                this.lazyLoadBerkasMetadata(item);
            }
        },

        async lazyLoadBerkasMetadata(item) {
            const isPdf =
                item.fileType === 'pdf' ||
                item.type === 'pdf' ||
                (item.metadata?.typeFile || '').toLowerCase().includes('pdf');
            if (!isPdf || item.fileUrl) return; // sudah ada / bukan PDF → skip

            try {
                const { data } = await axios.get(`${kontenBasePath()}/berkas/${item.id}/metadata`);
                if (data.success) {
                    const r = data.result;
                    // Patch objek item yang sedang ditampilkan di panel detail.
                    item.fileUrl = r.fileUrl;
                    item.previewUrl = item.previewUrl || r.previewUrl;
                    item.metadata = { ...(item.metadata || {}), ...(r.metadata || {}) };
                    // Pastikan reaktif (Vue 3 deteksi mutasi objek di array secara umum,
                    // tapi reassign selectedItem menjamin update panel).
                    if (this.selectedItem && this.selectedItem.id === item.id) {
                        this.selectedItem = { ...item };
                    }
                }
            } catch (e) {
                // Diamkan; preview opsional. Detail tetap tampil tanpa url.
            }
        },
        goToRoot() {
            this.stopRenderStatusPolling();
            this.currentCategory = null;
            this.files = [];
            this.searchQuery = '';
            this.tableFilters.search = '';
            this.selectedFileIds = [];
        },
        closeDetails() {
            this.showDetails = false;
            setTimeout(() => {
                this.selectedItem = null;
            }, 500);
        },
        openViewerFromDetails(item) {
            this.viewerItem = item;
            this.showMediaViewer = true;
        },
        closeMediaViewer() {
            this.showMediaViewer = false;
            setTimeout(() => {
                this.viewerItem = null;
            }, 250);
        },

        /*
         * Unduh berkas yang tak punya penyaji (docx/xlsx/pptx).
         *
         * URL-nya diminta ke server saat dibutuhkan, bukan disertakan di daftar
         * konten: URL bertanda tangan berumur pendek, dan menaruhnya di setiap
         * baris daftar berarti menerbitkan ratusan tautan unduh yang tak pernah
         * dipakai.
         */
        async handleUnduh(item) {
            if (!item || !item.id || this.mengunduh) return;
            this.mengunduh = true;

            try {
                const { data } = await axios.get(`${kontenBasePath()}/berkas/${item.id}/unduh`);
                if (data.success && data.result?.url) {
                    // Server sudah memaksa attachment + octet-stream, jadi ini
                    // mengunduh — bukan membuka tab yang menampilkan isinya.
                    window.location.assign(data.result.url);
                }
            } catch (e) {
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Mengunduh',
                    message: e?.response?.data?.message || 'Tautan unduh tidak dapat dibuat. Coba lagi.',
                });
            } finally {
                this.mengunduh = false;
            }
        },

        // Antrikan ulang verifikasi berkas yang tersangkut di karantina.
        async handleVerifikasiUlang(item) {
            if (!item || !item.id) return;
            try {
                const { data } = await axios.post(`${kontenBasePath()}/berkas/${item.id}/verifikasi-ulang`);
                if (data.success) {
                    this.openFeedbackModal({
                        type: 'success',
                        title: 'Verifikasi Diantrikan',
                        message: 'Berkas diantrikan ulang untuk diperiksa. Statusnya akan diperbarui otomatis.',
                    });
                    if (this.currentCategory) {
                        await this.fetchKontenByKategori(this.currentCategory.id);
                    }
                }
            } catch (error) {
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Mengantrikan',
                    message: error?.response?.data?.message || 'Verifikasi ulang tidak dapat diantrikan.',
                });
            }
        },

        // Render ulang sebuah konten PDF dari drawer detail.
        async handleRenderUlang(item) {
            if (!item || !item.id) return;
            try {
                const { data } = await axios.post(`${kontenBasePath()}/berkas/${item.id}/render-ulang`);
                if (data.success) {
                    // Update optimistik status di item yang sedang ditampilkan.
                    const newStatus = data.result?.render_status || 'PENDING';
                    if (this.selectedItem && this.selectedItem.id === item.id) {
                        this.selectedItem = {
                            ...this.selectedItem,
                            renderStatus: newStatus,
                            metadata: { ...(this.selectedItem.metadata || {}), renderStatus: newStatus },
                        };
                    }
                    this.openFeedbackModal({
                        type: 'success',
                        title: 'Render Diantrikan',
                        message: 'Konten diantrikan ulang untuk render. Status akan diperbarui otomatis.',
                    });
                    // Refresh list + pantau status sampai DONE (polling sudah ada).
                    if (this.currentCategory) {
                        await this.fetchKontenByKategori(this.currentCategory.id);
                    }
                }
            } catch (error) {
                console.error('Error render ulang:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Render Ulang',
                    message: error.response?.data?.message || 'Konten gagal diantrikan untuk render ulang.',
                });
            }
        },

        // ============ CALLBACKS ============
        handleCategoryCreated() {
            this.fetchKategori();
        },
        handleUploadSuccess() {
            if (this.currentCategory) {
                this.selectedFileIds = [];
                this.fetchKontenByKategori(this.currentCategory.id);
            }
        },

        toggleFileSelection(fileId) {
            const id = String(fileId);
            const exists = this.selectedFileIds.includes(id);

            if (exists) {
                this.selectedFileIds = this.selectedFileIds.filter((selectedId) => selectedId !== id);
                return;
            }

            this.selectedFileIds = [...this.selectedFileIds, id];
        },

        toggleSelectAllFiles() {
            const visibleFileIds = this.filteredContents
                .map((file) => String(file.id))
                .filter((id) => id && id !== 'undefined' && id !== 'null');

            if (visibleFileIds.length === 0) {
                this.selectedFileIds = [];
                return;
            }

            const isAllSelected = visibleFileIds.every((id) => this.selectedFileIds.includes(id));

            this.selectedFileIds = isAllSelected ? [] : [...visibleFileIds];
        },

        async handleDeleteSelected() {
            if (!this.currentCategory || this.selectedFileIds.length === 0) {
                this.openFeedbackModal({
                    type: 'warning',
                    title: 'Belum Ada Konten Dipilih',
                    message: 'Pilih konten yang ingin dihapus terlebih dahulu.',
                });
                return;
            }

            // Check usage before delete
            await this.checkContentUsageBeforeDelete(this.selectedFileIds, 'selected-files');
        },

        async checkContentUsageBeforeDelete(contentIds, action) {
            if (!contentIds || contentIds.length === 0) {
                return;
            }

            this.isCheckingUsage = true;
            this.pendingDeleteAction = action;

            try {
                const uniqueContentIds = Array.from(
                    new Set((contentIds || []).map((id) => String(id)).filter((id) => !!id)),
                );

                // Single request for all selected content IDs
                const response = await axios.post('/lms/api/content/usage-tracking-batch', {
                    ids: uniqueContentIds,
                });

                const result = response?.data?.result || {};
                const perContent = result.per_content || [];
                const allTrainings = result.trainings || [];
                const allPenjadwalan = result.penjadwalan || [];
                const totalTrainings = Number(result.total_trainings || allTrainings.length || 0);
                const totalPenjadwalan = Number(result.total_penjadwalan || allPenjadwalan.length || 0);

                // If there's usage, show warning modal
                if (totalTrainings > 0 || totalPenjadwalan > 0) {
                    this.usageWarningData = {
                        per_content: perContent,
                        trainings: allTrainings,
                        penjadwalan: allPenjadwalan,
                        total_trainings: totalTrainings,
                        total_penjadwalan: totalPenjadwalan,
                    };
                    this.showUsageWarningModal = true;
                } else {
                    // No usage, show normal delete confirmation
                    if (action === 'selected-files') {
                        this.deleteSelectedAcknowledge = false;
                        this.showDeleteSelectedModal = true;
                    } else if (action === 'category') {
                        this.showDeleteCategoryConfirmation();
                    }
                }
            } catch (error) {
                console.error('Error checking content usage:', error);
                // On error, allow delete to proceed with caution
                if (action === 'selected-files') {
                    this.deleteSelectedAcknowledge = false;
                    this.showDeleteSelectedModal = true;
                } else if (action === 'category') {
                    this.showDeleteCategoryConfirmation();
                }
            } finally {
                this.isCheckingUsage = false;
            }
        },

        showDeleteCategoryConfirmation() {
            this.deleteCategoryChecks = this.deleteCategoryFileItems.reduce((acc, file) => {
                acc[file.id] = false;
                return acc;
            }, {});
            this.deleteCategoryAcknowledge = false;
            this.showDeleteCategoryModal = true;
        },

        closeUsageWarningModal() {
            this.showUsageWarningModal = false;
            this.usageWarningData = {
                per_content: [],
                trainings: [],
                penjadwalan: [],
                total_trainings: 0,
                total_penjadwalan: 0,
            };
            this.pendingDeleteAction = null;
        },

        openTrainingUsage(training) {
            const trainingId = training?.id_hash || training?.id;
            if (!trainingId) {
                window.open('/lms/admin/training', '_blank');
                return;
            }
            window.open(`/lms/admin/training/edit/${trainingId}`, '_blank');
        },

        openAssignmentUsage(assignment) {
            const assignmentId = assignment?.id_hash || assignment?.id;
            if (!assignmentId) {
                window.open('/lms/penjadwalan', '_blank');
                return;
            }
            window.open(`/lms/penjadwalan?penugasan=${assignmentId}`, '_blank');
        },

        async confirmDeleteSelected() {
            if (!this.currentCategory || this.selectedFileIds.length === 0 || !this.deleteSelectedAcknowledge) {
                return;
            }

            this.isDeletingSelected = true;
            this.isActionLoading = true;
            this.isLoading = true;
            try {
                const response = await axios.post(kontenBasePath() + '/delete-konten', {
                    ids: this.selectedFileIds,
                    id_kategori_konten: this.currentCategory.id,
                });

                if (response.data.success) {
                    this.resetDeleteSelectedModalState();
                    this.selectedFileIds = [];
                    await this.fetchKontenByKategori(this.currentCategory.id);
                    this.openFeedbackModal({
                        type: 'success',
                        title: 'Berhasil',
                        message: 'Konten terpilih berhasil dihapus.',
                    });
                }
            } catch (error) {
                console.error('Error deleting konten:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Menghapus Konten',
                    message: error.response?.data?.message || 'Konten terpilih gagal dihapus.',
                });
            } finally {
                this.isDeletingSelected = false;
                this.isActionLoading = false;
                this.isLoading = false;
            }
        },

        closeDeleteSelectedModal() {
            if (this.isDeletingSelected) {
                return;
            }

            this.resetDeleteSelectedModalState();
        },

        resetDeleteSelectedModalState() {
            this.showDeleteSelectedModal = false;
            this.deleteSelectedAcknowledge = false;
        },

        openDeleteCategoryModal() {
            if (!this.currentCategory) {
                return;
            }

            // Check usage before opening delete modal
            const categoryContentIds = this.files
                .map((f) => f.id.toString())
                .filter((id) => id && id !== 'undefined' && id !== 'null');

            if (categoryContentIds.length > 0) {
                this.checkContentUsageBeforeDelete(categoryContentIds, 'category');
            } else {
                // No content in category, show normal delete modal
                this.showDeleteCategoryConfirmation();
            }
        },

        closeDeleteCategoryModal() {
            if (this.isDeletingCategory) {
                return;
            }

            this.resetDeleteCategoryModalState();
        },

        resetDeleteCategoryModalState() {
            this.showDeleteCategoryModal = false;
            this.deleteCategoryChecks = {};
            this.deleteCategoryAcknowledge = false;
        },

        openRenameCategoryModal() {
            if (!this.currentCategory) return;
            this.renameType = 'category';
            this.renameTargetId = this.currentCategory.id;
            this.renameInput = this.currentCategory.name;
            this.showRenameModal = true;
            this.$nextTick(() => {
                if (this.$refs.renameInputRef) this.$refs.renameInputRef.focus();
            });
        },

        openRenameFileModal() {
            if (this.selectedFileIds.length !== 1) return;
            const fileId = this.selectedFileIds[0];
            const file = this.files.find((f) => String(f.id) === String(fileId));
            if (!file) return;

            this.renameType = 'file';
            this.renameTargetId = file.id;
            this.renameInput = file.name;
            this.showRenameModal = true;
            this.$nextTick(() => {
                if (this.$refs.renameInputRef) this.$refs.renameInputRef.focus();
            });
        },

        closeRenameModal() {
            if (this.isRenaming) return;
            this.showRenameModal = false;
            this.renameInput = '';
            this.renameTargetId = null;
            this.renameType = '';
        },

        async confirmRename() {
            if (!this.renameInput.trim() || !this.renameTargetId) return;

            this.isRenaming = true;
            this.isActionLoading = true;

            try {
                const response = await axios.post(kontenBasePath() + '/rename', {
                    id: this.renameTargetId,
                    type: this.renameType,
                    name: this.renameInput.trim(),
                });

                if (response.data.success) {
                    if (this.renameType === 'category') {
                        this.currentCategory.name = this.renameInput.trim();
                        await this.fetchKategori();
                    } else if (this.renameType === 'file') {
                        await this.fetchKontenByKategori(this.currentCategory.id);
                        this.selectedFileIds = [];
                    }
                    this.openFeedbackModal({
                        type: 'success',
                        title: 'Berhasil',
                        message: `Nama ${this.renameType === 'category' ? 'kategori' : 'konten'} berhasil diubah.`,
                    });
                    this.isRenaming = false;
                    this.closeRenameModal();
                }
            } catch (error) {
                console.error('Error renaming:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Mengubah Nama',
                    message: error.response?.data?.message || 'Terjadi kesalahan saat mengubah nama.',
                });
            } finally {
                this.isRenaming = false;
                this.isActionLoading = false;
            }
        },

        isCategoryFileChecked(fileId) {
            return !!this.deleteCategoryChecks[String(fileId)];
        },

        setCategoryFileCheck(fileId, checked) {
            const id = String(fileId);
            this.deleteCategoryChecks = {
                ...this.deleteCategoryChecks,
                [id]: checked,
            };
        },

        async confirmDeleteCategory() {
            if (!this.currentCategory || !this.canDeleteCategory) {
                return;
            }

            this.isDeletingCategory = true;
            this.isActionLoading = true;
            this.isLoading = true;

            try {
                const response = await axios.post(kontenBasePath() + '/delete-kategori', {
                    id: this.currentCategory.id,
                });

                if (response.data.success) {
                    const deletedCount = response.data.result?.deleted_content_count ?? 0;
                    this.resetDeleteCategoryModalState();
                    this.goToRoot();
                    await this.fetchKategori();
                    this.openFeedbackModal({
                        type: 'success',
                        title: 'Kategori Dihapus',
                        message: `Kategori berhasil dihapus. Total konten ikut dihapus: ${deletedCount}.`,
                    });
                }
            } catch (error) {
                console.error('Error deleting kategori:', error);
                this.openFeedbackModal({
                    type: 'error',
                    title: 'Gagal Menghapus Kategori',
                    message: error.response?.data?.message || 'Kategori gagal dihapus.',
                });
            } finally {
                this.isDeletingCategory = false;
                this.isActionLoading = false;
                this.isLoading = false;
            }
        },

        openFeedbackModal({ type = 'success', title = 'Informasi', message = '' }) {
            this.feedbackModal = { type, title, message };
            this.showFeedbackModal = true;
        },

        closeFeedbackModal() {
            this.showFeedbackModal = false;
        },

        preloadImage(url, timeoutMs = 10000) {
            return new Promise((resolve) => {
                if (!url) {
                    resolve();
                    return;
                }

                const img = new Image();
                let resolved = false;

                const finish = () => {
                    if (resolved) return;
                    resolved = true;
                    clearTimeout(timer);
                    resolve();
                };

                const timer = setTimeout(() => {
                    finish();
                }, timeoutMs);

                img.onload = finish;
                img.onerror = finish;
                img.src = url;
            });
        },

        async preloadContentThumbnails(files) {
            const urls = Array.from(
                new Set(
                    (files || []).map((file) => file?.thumbnailUrl || file?.previewUrl || null).filter((url) => !!url),
                ),
            );

            if (urls.length === 0) {
                return;
            }

            await Promise.all(urls.map((url) => this.preloadImage(url)));
        },
    },
};
</script>

<style scoped>
/* ─── Design Tokens ─── */
:root {
    --kl-bg: var(--hcis-slate-100);
    --kl-surface: var(--hcis-white);
    --kl-border: var(--hcis-slate-200);
    --kl-text: var(--hcis-slate-800);
    --kl-muted: var(--hcis-slate-500);
    --kl-primary: var(--hcis-indigo-500);
    --kl-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    --kl-shadow-md: 0 4px 12px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
    --kl-radius: 16px;
}

.bi {
    display: inline-table !important;
}

/* ─── App Shell ─── */
.kl-app-container {
    background: var(--hcis-slate-100);
    min-height: 100vh;
    padding-bottom: 3rem;
    color: var(--hcis-slate-800);
}

.bi {
    display: inline-table;
}

/* ─── Tab Panel ─── */
.kl-tab-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ─── Panel Header ─── */
.kl-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.kl-panel-header-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.kl-panel-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.kl-panel-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    margin: 0;
}

.kl-panel-subtitle {
    margin: 0.15rem 0 0;
    color: var(--hcis-slate-500);
    font-size: 0.87rem;
}

/* ─── Loading State ─── */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
}

.loading-spinner {
    width: 64px;
    height: 64px;
    background: var(--hcis-indigo-50);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: var(--hcis-indigo-600);
    font-size: 1.5rem;
}

.loading-description {
    color: var(--hcis-slate-500);
    font-weight: 500;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* ─── Buttons ─── */
.stitch-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
}

.stitch-btn.primary {
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}

.stitch-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
}

.stitch-btn.danger {
    background: #fff1f2;
    color: var(--hcis-rose-600);
    border: 1px solid var(--hcis-rose-100);
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.1);
}

.stitch-btn.danger:hover {
    background: var(--hcis-rose-100);
    color: var(--hcis-rose-700);
    border-color: #fecdd3;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(225, 29, 72, 0.2);
}

.stitch-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.stitch-card {
    background: var(--hcis-white);
    border-radius: 16px;
    box-shadow:
        0 1px 3px rgba(15, 23, 42, 0.06),
        0 1px 2px rgba(15, 23, 42, 0.04);
}

.kl-modal-stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kl-warning-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    border: 1px solid var(--hcis-amber-200);
    background: var(--hcis-amber-50);
}

.kl-warning-box i {
    color: var(--hcis-amber-700);
    font-size: 1.1rem;
    margin-top: 2px;
}

.kl-check-row {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    font-size: 0.9rem;
    color: var(--hcis-slate-700);
}

.kl-delete-list {
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.5rem;
    background: var(--hcis-slate-50);
}

.kl-delete-list-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem;
    border-radius: 10px;
    color: var(--hcis-slate-700);
}

.kl-delete-list-item:hover {
    background: var(--hcis-indigo-50);
}

.kl-delete-list-title {
    font-weight: 500;
    font-size: 0.9rem;
}

.kl-empty-list {
    padding: 0.8rem 1rem;
    border-radius: 10px;
    border: 1px dashed var(--hcis-slate-300);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-size: 0.88rem;
}

.kl-feedback-body {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    font-size: 0.93rem;
    color: var(--hcis-slate-700);
}

.kl-feedback-body i {
    font-size: 1.1rem;
    margin-top: 2px;
}

.kl-feedback-body.is-error i {
    color: var(--hcis-red-600);
}

.kl-feedback-body.is-warning i {
    color: var(--hcis-amber-600);
}

.kl-feedback-body.is-success i {
    color: var(--hcis-green-600);
}

/* ─── Usage Warning Modal Styles ─── */
.usage-list-item {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.5rem;
    background: var(--hcis-slate-50);
}

.usage-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.usage-item-clickable {
    cursor: pointer;
}

.usage-item:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}

.usage-item-clickable:hover {
    border-color: #93c5fd;
    background: #eff6ff;
}

.usage-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.usage-item-title {
    font-weight: 600;
    font-size: 14px;
    color: var(--hcis-slate-800);
}

.usage-content-groups {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.usage-content-group {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: var(--hcis-white);
    padding: 12px;
}

.usage-content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}

.usage-inline-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--hcis-slate-700);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.usage-item-badge {
    display: inline-block;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 8px;
    white-space: nowrap;
}

.usage-status-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 8px;
    white-space: nowrap;
}

.alert {
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
    font-size: 13px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.alert i {
    font-size: 16px;
    flex-shrink: 0;
    margin-top: 2px;
}

.alert small {
    line-height: 1.5;
}

.alert-info {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1e40af;
}

.kl-processing-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(2px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.kl-processing-card {
    width: min(420px, 100%);
    background: var(--hcis-white);
    border-radius: 14px;
    border: 1px solid var(--hcis-slate-200);
    padding: 1rem 1.2rem;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.2);
    text-align: center;
}

.kl-processing-card i {
    font-size: 1.4rem;
    color: var(--hcis-indigo-600);
    margin-bottom: 0.35rem;
    display: inline-block;
}

/* Date Pill di Header */
.hcis-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.38rem 0.85rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
</style>
