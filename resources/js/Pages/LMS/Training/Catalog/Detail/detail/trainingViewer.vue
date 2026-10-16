<template>
    <div class="viewer-layout" :class="{ 'fullscreen-mode': isFullscreen }">
        <div
            v-if="isNavigating"
            class="full-overlay-loading d-flex flex-column align-items-center justify-content-center"
        >
            <div
                class="spinner-border text-primary"
                style="width: 3.5rem; height: 3.5rem; border-width: 4px"
                role="status"
            ></div>
            <h5 class="mt-4 fw-bold text-dark" style="text-shadow: 0 2px 4px rgba(255, 255, 255, 0.8)">Memproses...</h5>
            <p class="text-secondary small fw-medium">Mohon tunggu sebentar</p>
        </div>

        <div class="glass-toolbar d-flex align-items-center justify-content-between px-3 px-md-4 py-2">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button
                    @click="goBack"
                    class="btn btn-light btn-sm fw-medium rounded-pill px-3"
                    title="Kembali ke Detail Training"
                >
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="d-flex flex-column min-w-0">
                    <div class="text-muted text-xs text-truncate" style="font-weight: 500">{{ trainingTitle }}</div>
                    <div
                        class="viewer-title text-truncate fw-bold text-dark"
                        style="font-size: 1.1rem; letter-spacing: -0.01em"
                    >
                        {{ itemData?.title || 'Memuat Konten...' }}
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button
                    v-if="!isAdminMode"
                    @click="markCompleteAndNext"
                    class="btn btn-success shadow-sm btn-sm rounded-pill px-3 hover-scale d-flex align-items-center gap-2"
                    :disabled="isNavigating"
                    :title="isCurrentItemCompleted ? 'Lanjutkan' : 'Tandai Selesai'"
                >
                    <i
                        class="bi"
                        :class="isCurrentItemCompleted ? 'bi-arrow-right-circle-fill' : 'bi-check-circle-fill'"
                    ></i>
                    <span class="d-none d-md-inline fw-medium">{{
                        isCurrentItemCompleted ? 'Lanjutkan' : 'Selesai'
                    }}</span>
                </button>
                <button
                    @click="toggleFullscreen"
                    class="btn btn-light btn-sm rounded-pill px-3"
                    style="background-color: #f1f5f9; border: none"
                    title="Toggle Fullscreen"
                >
                    <i
                        class="bi"
                        :class="isFullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"
                        style="color: #475569"
                    ></i>
                </button>
                <button
                    @click="togglePlaylist"
                    class="btn btn-primary shadow-sm btn-sm rounded-pill px-3 hover-scale d-flex align-items-center gap-2"
                    title="Daftar Aktivitas"
                >
                    <i class="bi bi-list-task fs-5"></i>
                    <span class="d-none d-md-inline fw-medium">Aktivitas</span>
                </button>
            </div>
        </div>

        <div
            class="viewer-body bg-slate-900 flex-grow-1 position-relative d-flex flex-column"
            style="background-color: #f1f5f9"
            @mousemove="handleViewerInteraction"
            @click="handleViewerInteraction"
            @touchstart.passive="handleViewerInteraction"
        >
            <div
                v-if="loading && !isNavigating"
                class="absolute-center text-secondary text-center z-index-5 w-100 d-flex flex-column align-items-center justify-content-center h-100"
            >
                <div
                    class="spinner-border text-primary mb-3"
                    style="width: 3rem; height: 3rem; border-width: 3px"
                    role="status"
                ></div>
                <h5 class="fw-semibold text-dark">Menyiapkan Konten...</h5>
            </div>

            <div
                v-else-if="errorMsg"
                class="absolute-center text-center text-danger bg-white p-4 rounded-4 shadow-lg mx-auto mt-5"
                style="max-width: 400px; z-index: 10"
            >
                <i class="bi bi-exclamation-triangle fs-1 mb-2 d-block"></i>
                <h5 class="fw-bold mb-2">Terjadi Kesalahan</h5>
                <p class="text-secondary small">{{ errorMsg }}</p>
                <button class="btn btn-primary rounded-pill px-4" @click="fetchKonten">Coba Lagi</button>
            </div>

            <template v-else-if="contentInfo">
                <div
                    v-if="contentInfo.type === 'video'"
                    class="viewer-media flex-grow-1 position-relative bg-black w-100"
                >
                    <iframe
                        :src="contentInfo.embed_url"
                        class="full-iframe w-100 h-100 border-0"
                        allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture"
                        allowfullscreen="true"
                    ></iframe>
                </div>

                <div
                    v-else-if="contentInfo.type === 'secure-pdf'"
                    class="viewer-media flex-grow-1 position-relative d-flex flex-column w-100 overflow-hidden"
                    style="background-color: #e2e8f0"
                >
                    <div
                        v-if="contentInfo.status === 'pending' || contentInfo.status === 'processing'"
                        class="absolute-center text-center p-5 rounded-4 bg-white shadow-sm"
                        style="max-width: 420px"
                    >
                        <div
                            class="spinner-border text-primary mb-3"
                            style="width: 3rem; height: 3rem"
                            role="status"
                        ></div>
                        <h5 class="fw-bold text-dark">Dokumen Sedang Disiapkan</h5>
                        <p class="text-secondary small mb-3">
                            Sistem sedang mengamankan dokumen ini agar nyaman dibaca.<br />
                            Proses ini butuh waktu beberapa saat.
                        </p>
                        <div class="progress mb-3" style="height: 6px; border-radius: 4px">
                            <div
                                class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                style="width: 100%"
                            ></div>
                        </div>
                        <p class="text-muted" style="font-size: 0.78rem">
                            Halaman akan otomatis terbuka saat siap
                            <span v-if="pdfPollCountdown > 0">(cek ulang dalam {{ pdfPollCountdown }}s)</span>
                        </p>
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3 mt-1" @click="fetchKonten">
                            <i class="bi bi-arrow-clockwise me-1"></i> Cek Sekarang
                        </button>
                    </div>

                    <div
                        v-else-if="contentInfo.status === 'failed'"
                        class="absolute-center text-center p-5 rounded-4 bg-white shadow-sm border border-danger-subtle"
                        style="max-width: 420px"
                    >
                        <i class="bi bi-shield-x fs-1 text-danger mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark">Gagal Memproses Dokumen</h5>
                        <p class="text-secondary small mb-4">{{ contentInfo.message }}</p>
                        <button class="btn btn-primary rounded-pill px-4" @click="retryPdfConversion">
                            <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                        </button>
                    </div>

                    <div
                        v-else-if="pdfLoading"
                        class="absolute-center d-flex flex-column align-items-center justify-content-center h-100 w-100 z-index-5"
                    >
                        <div
                            class="spinner-border text-primary mb-3"
                            style="width: 2.5rem; height: 2.5rem"
                            role="status"
                        ></div>
                        <div class="text-dark fw-medium small">{{ pdfStatusText }}</div>
                    </div>

                    <div
                        v-else-if="pdfErrorMsg"
                        class="absolute-center text-center p-4 bg-white rounded-4 shadow-sm border border-danger-subtle"
                        style="max-width: 350px"
                    >
                        <i class="bi bi-shield-lock fs-1 text-danger mb-2 d-block"></i>
                        <h6 class="fw-bold">Akses Ditolak</h6>
                        <p class="text-secondary small mb-3">{{ pdfErrorMsg }}</p>
                        <button
                            class="btn btn-primary btn-sm rounded-pill px-3"
                            @click="loadPdfSecurely(contentInfo.berkas_id)"
                        >
                            Coba Lagi
                        </button>
                    </div>

                    <div v-else class="d-flex flex-column h-100 unselectable" @contextmenu.prevent>
                        <div
                            class="pdf-glass-toolbar d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-white shadow-sm z-index-5"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold"
                                    style="letter-spacing: 0.5px"
                                >
                                    <i class="bi bi-shield-check"></i>
                                    <span class="d-none d-md-inline ms-1">SECURE PDF</span>
                                </span>
                                <span class="text-secondary fw-medium" style="font-size: 0.8rem">
                                    Hal {{ pdfCurrentPage || 1 }} / {{ pdfTotalPages }}
                                </span>
                            </div>
                            <div class="bg-light rounded-pill px-2 shadow-sm d-flex align-items-center border">
                                <button
                                    class="btn btn-sm btn-link text-secondary text-decoration-none"
                                    @click="changePdfScale(-0.15)"
                                    :disabled="pdfScale <= pdfMinScale"
                                >
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <span
                                    class="fw-bold mx-1"
                                    style="font-size: 0.8rem; min-width: 40px; text-align: center"
                                    >{{ Math.round(pdfScale * 100) }}%</span
                                >

                                <button
                                    class="btn btn-sm btn-link text-secondary text-decoration-none"
                                    @click="changePdfScale(0.15)"
                                    :disabled="pdfScale >= pdfMaxScale"
                                >
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <button
                                    class="btn btn-sm btn-link text-secondary text-decoration-none"
                                    @click="resetPdfScaleToFit"
                                    title="Reset Ukuran"
                                >
                                    <i class="bi bi-aspect-ratio"></i>
                                </button>
                                <button
                                    class="btn btn-sm btn-link text-secondary text-decoration-none"
                                    @click="rotatePdf(90)"
                                    title="Putar Kanan"
                                >
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>

                        <div
                            class="pdf-scroll-area flex-grow-1 overflow-auto py-4 position-relative"
                            ref="pdfScrollArea"
                            @scroll="onPdfScroll"
                            @contextmenu.prevent
                        >
                            <div
                                class="d-flex flex-column align-items-center pdf-pages-stack position-relative"
                                style="z-index: 5"
                            >
                                <div
                                    v-for="page in pdfTotalPages"
                                    :key="page"
                                    class="pdf-page-wrapper mb-4 shadow"
                                    :class="{ 'is-pending': !pdfRenderedPages[page] }"
                                    :style="getPdfPageWrapperStyle(page)"
                                    :ref="'pdfPageWrapper-' + page"
                                >
                                    <div v-if="!pdfRenderedPages[page]" class="pdf-page-placeholder">
                                        <div
                                            class="placeholder-spinner spinner-border text-primary"
                                            role="status"
                                        ></div>
                                        <span>{{ getPdfPagePlaceholderText(page) }}</span>
                                    </div>
                                    <canvas
                                        v-if="pdfPageImages[page]?.status === 'loaded'"
                                        class="pdf-page-canvas"
                                        :style="getPdfPageCanvasStyle(page)"
                                        :ref="'pdfPageCanvas-' + page"
                                        @contextmenu.prevent
                                    ></canvas>
                                    <div class="shield-layer" @contextmenu.prevent @dragstart.prevent></div>
                                    <div class="page-number-badge">{{ page }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div
                v-else
                class="absolute-center bg-white p-5 rounded-4 shadow-lg text-center z-index-5 mx-auto mt-5"
                style="max-width: 400px"
            >
                <div class="rounded-circle bg-light d-inline-flex mx-auto mb-3 p-4 text-warning">
                    <i class="bi bi-file-earmark-x fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Tanpa Efek Visual</h5>
                <p class="text-secondary small mb-4">Aktivitas ini tidak memiliki tayangan langsung.</p>
                <button
                    v-if="!isAdminMode"
                    class="btn rounded-pill px-4 py-2 fw-bold hover-scale shadow-sm text-white"
                    :class="isCurrentItemCompleted ? 'btn-success' : 'btn-primary'"
                    @click="markCompleteAndNext"
                    :disabled="isNavigating"
                >
                    <i class="bi bi-check-circle me-1" v-if="isCurrentItemCompleted"></i>
                    {{ isCurrentItemCompleted ? 'Lanjutkan' : 'Selesai & Lanjut' }}
                </button>
            </div>

            <div
                v-if="!loading && !isNavigating && contentInfo"
                class="floating-dock floating-dock-shell glass-dock px-3 py-2 px-md-4 py-md-3 d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 gap-md-3 mx-3 mx-md-4 mb-3 mb-md-4 rounded-4 shadow-lg border border-white"
                :class="{ 'is-visible': isDockVisible }"
                @mouseenter="pinDock"
                @mouseleave="unpinDock"
                @touchstart.stop="pinDock"
            >
                <div class="dock-text text-start min-w-0 flex-grow-1 text-dark">
                    <h5 class="dock-title mb-1 fw-bold">{{ itemData?.title }}</h5>
                    <div
                        class="dock-meta d-flex align-items-center flex-wrap gap-2 fw-medium"
                        style="font-size: 0.78rem"
                    >
                        <span
                            class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2"
                        >
                            <i class="bi bi-play-circle me-1"></i> {{ getContentTypeLabel(itemData?.type) }}
                        </span>
                        <span v-if="itemData?.description" class="dock-description text-truncate">{{
                            itemData.description
                        }}</span>
                    </div>
                </div>
                <button
                    v-if="!isAdminMode"
                    class="btn dock-action-btn rounded-pill px-4 py-2 fw-bold text-white shadow d-flex align-items-center gap-2 hover-scale flex-shrink-0 border-0 justify-content-center"
                    :style="
                        isCurrentItemCompleted
                            ? 'background: linear-gradient(135deg, #10b981, #059669);'
                            : 'background: linear-gradient(135deg, #6366f1, #4f46e5);'
                    "
                    @click="markCompleteAndNext"
                    :disabled="isNavigating"
                >
                    <i class="bi bi-check-circle fs-5" v-if="isCurrentItemCompleted"></i>
                    {{ isCurrentItemCompleted ? 'Lanjutkan' : 'Tandai Selesai' }}
                    <i class="bi bi-chevron-right fs-5" v-if="!isCurrentItemCompleted"></i>
                </button>
            </div>

            <button
                v-if="!loading && !isNavigating && contentInfo && !isDockVisible"
                class="dock-reveal-btn btn btn-primary rounded-pill shadow"
                @click="revealDock"
            >
                <i class="bi bi-chevron-up me-1"></i>Kontrol
            </button>
        </div>

        <div class="drawer-overlay" :class="{ 'is-open': isPlaylistVisible }" @click="togglePlaylist"></div>
        <div class="offcanvas-drawer bg-white shadow-lg d-flex flex-column" :class="{ 'is-open': isPlaylistVisible }">
            <div
                class="drawer-header px-4 py-3 border-bottom border-light d-flex align-items-center justify-content-between bg-white z-index-10"
            >
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center" style="font-size: 1.1rem">
                    <i class="bi bi-collection-play-fill text-primary me-2"></i> Daftar Materi
                </h6>
                <button
                    @click="togglePlaylist"
                    class="btn btn-light btn-sm rounded-circle d-flex align-items-center justify-content-center p-0 hover-scale"
                    style="width: 30px; height: 30px"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto p-3" style="background-color: #f8fafc">
                <template v-for="(section, sIdx) in sidebarSections" :key="section.id_materi_detail">
                    <div class="mb-3 drawer-section rounded-3 overflow-hidden bg-white border border-light shadow-sm">
                        <div
                            class="p-3 d-flex align-items-center justify-content-between cursor-pointer drawer-section-header"
                            @click="toggleSidebarSection(sIdx)"
                            :class="{ active: !collapsedSidebarSections[sIdx] }"
                        >
                            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center" style="font-size: 0.9rem">
                                <i
                                    class="bi me-2 text-primary transition-all"
                                    :class="collapsedSidebarSections[sIdx] ? 'bi-chevron-right' : 'bi-chevron-down'"
                                ></i>
                                {{ section.nama_sub }}
                            </h6>
                            <span class="badge bg-light text-secondary rounded-pill border">{{
                                section.items ? section.items.length : 0
                            }}</span>
                        </div>
                        <div v-show="!collapsedSidebarSections[sIdx]" class="drawer-section-body p-2 bg-white">
                            <template v-for="navItem in section.items" :key="navItem.id_materi_det">
                                <div
                                    class="playlist-item p-2 mb-1 rounded-3 d-flex gap-3 align-items-center position-relative"
                                    :class="{
                                        'is-active bg-primary-subtle text-primary border border-primary-subtle':
                                            navItem.id_materi_det == activeDetId,
                                        'is-completed':
                                            isItemCompleted(navItem) && navItem.id_materi_det != activeDetId,
                                        'cursor-pointer hover-bg': navItem.id_materi_det != activeDetId,
                                    }"
                                    @click="
                                        !isNavigating && navItem.id_materi_det != activeDetId
                                            ? loadItem(navItem.id_materi_det, getSidebarItemType(navItem))
                                            : null
                                    "
                                >
                                    <div v-if="navItem.id_materi_det == activeDetId" class="active-indicator"></div>

                                    <div
                                        class="item-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                        :class="{
                                            'bg-primary text-white shadow-sm': navItem.id_materi_det == activeDetId,
                                            'bg-success-subtle text-success':
                                                isItemCompleted(navItem) && navItem.id_materi_det != activeDetId,
                                            'bg-light text-secondary border':
                                                !isItemCompleted(navItem) && navItem.id_materi_det != activeDetId,
                                        }"
                                        style="width: 38px; height: 38px"
                                    >
                                        <div
                                            v-if="navItem.id_materi_det == activeDetId && isNavigating"
                                            class="spinner-border spinner-border-sm text-white"
                                            role="status"
                                        ></div>
                                        <i
                                            v-else-if="navItem.id_materi_det == activeDetId"
                                            class="bi bi-play-fill fs-5"
                                        ></i>
                                        <i v-else-if="isItemCompleted(navItem)" class="bi bi-check-lg fs-5"></i>
                                        <i
                                            v-else-if="getSidebarItemType(navItem) === 'VIDEO'"
                                            class="bi bi-camera-video-fill fs-6"
                                        ></i>
                                        <i
                                            v-else-if="getSidebarItemType(navItem) === 'MDL'"
                                            class="bi bi-file-earmark-pdf-fill fs-6"
                                        ></i>
                                        <i v-else class="bi bi-clipboard-check-fill fs-6"></i>
                                    </div>
                                    <div class="item-info min-w-0 flex-grow-1">
                                        <div
                                            class="fw-semibold text-truncate lh-sm mb-1"
                                            style="font-size: 0.85rem"
                                            :title="navItem.konten?.judul || navItem.nama_aktivitas"
                                            :class="
                                                navItem.id_materi_det == activeDetId
                                                    ? 'text-primary'
                                                    : isItemCompleted(navItem)
                                                      ? 'text-success'
                                                      : 'text-dark'
                                            "
                                        >
                                            {{ navItem.konten?.judul || navItem.nama_aktivitas || 'Materi' }}
                                        </div>
                                        <div class="text-secondary small fw-medium text-xs d-flex align-items-center">
                                            <i class="bi bi-clock me-1 text-muted" v-if="navItem.durasi"></i>
                                            {{ navItem.durasi ? navItem.durasi + ' Menit' : 'Lihat' }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'TrainingViewer',
    props: {
        materiId: {
            type: [Number, String],
            required: true,
        },
        materiDetId: {
            type: [Number, String],
            required: true,
        },
        penjadwalanId: {
            type: [Number, String],
            default: null,
        },
    },
    data() {
        return {
            loading: true,
            errorMsg: '',
            activeDetId: this.materiDetId,
            itemData: null,
            trainingTitle: '',
            contentInfo: null,
            flatSidebarItems: [],
            sidebarSections: [],
            collapsedSidebarSections: {},
            isFullscreen: false,
            skipAutoCompleteOnce: false,
            isNavigating: false,
            isPlaylistVisible: false,

            // PDF State
            pdfLoading: false,
            pdfStatusText: '',
            pdfErrorMsg: null,
            pdfTotalPages: 0,
            pdfCurrentPage: 1,
            pdfScale: 1.2,
            pdfAutoFitScale: 1.2,
            pdfRotation: 0,
            pdfManualScale: false,
            pdfMinScale: 0.2,
            pdfMaxScale: 3.0,
            pdfPlaceholderWidth: 720,
            pdfPlaceholderHeight: 960,
            // Secure session state (handshake → viewer_signature)
            pdfViewerSignature: null,
            pdfPageBaseUrl: null,
            pdfManifestPages: [], // [{page, width, height}]

            // State Canvas Internal
            pdfRenderedPages: {},
            pdfPageImages: {},

            isDockVisible: true,
            dockHideTimer: null,
            dockHideDelayMs: 2600,

            // PDF conversion polling
            pdfPollTimer: null,
            pdfPollCountdown: 0,
            pdfPollCountdownTimer: null,

            // PDF page load queue (throttle concurrent requests)
            pdfLoadQueue: [],        // [{page, priority}] — sorted by priority asc
            pdfActiveLoads: 0,
            PDF_MAX_CONCURRENT: 2,   // maks 2 request paralel (grant + image) ke server
            pdfScrollDebounceTimer: null,
            PDF_SCROLL_DEBOUNCE_MS: 120, // tunggu 120ms setelah scroll berhenti
        };
    },
    computed: {
        isAdminMode() {
            const params = new URLSearchParams(window.location.search);
            return params.get('mode') === 'admin';
        },
        adminTargetKodeKaryawan() {
            const params = new URLSearchParams(window.location.search);
            return params.get('target_kode_karyawan') || null;
        },
        adminBackUrl() {
            const params = new URLSearchParams(window.location.search);
            const returnTo = params.get('return_to');

            if (returnTo) {
                return returnTo;
            }

            if (this.activePenjadwalanId && this.adminTargetKodeKaryawan) {
                return `/lms/admin/monitoring/training/schedule/${this.activePenjadwalanId}/${this.adminTargetKodeKaryawan}`;
            }

            if (this.adminTargetKodeKaryawan) {
                return `/lms/admin/monitoring/training/${this.materiId}/${this.adminTargetKodeKaryawan}`;
            }

            return '/lms/admin/monitoring';
        },
        activePenjadwalanId() {
            const params = new URLSearchParams(window.location.search);
            return params.get('penjadwalan_id') || this.penjadwalanId || null;
        },
        userDetailUrl() {
            return this.activePenjadwalanId
                ? `/lms/training/schedule/${this.activePenjadwalanId}`
                : `/lms/training/${this.materiId}`;
        },
        isCurrentItemCompleted() {
            if (!this.sidebarSections || this.sidebarSections.length === 0) return false;
            for (const section of this.sidebarSections) {
                const item = section.items.find((i) => i.id_materi_det == this.activeDetId);
                if (item) return this.isItemCompleted(item);
            }
            return false;
        },
        isPdfZoomedIn() {
            return this.pdfScale > this.pdfAutoFitScale + 0.01;
        },
    },
    mounted() {
        this.fetchKonten();
        document.addEventListener('fullscreenchange', this.onFullscreenChange);
        window.addEventListener('resize', this.handleWindowResize);
        window.ondragstart = () => false;
        this.scheduleDockHide();
    },
    beforeUnmount() {
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        window.removeEventListener('resize', this.handleWindowResize);
        this.clearDockHideTimer();
        this.stopPdfPolling();
        if (this.pdfScrollDebounceTimer) clearTimeout(this.pdfScrollDebounceTimer);
    },
    methods: {
        // PDF conversion polling — auto-retry saat status pending/processing
        startPdfPolling() {
            this.stopPdfPolling();
            const POLL_INTERVAL = 5000;
            this.pdfPollCountdown = Math.round(POLL_INTERVAL / 1000);

            this.pdfPollCountdownTimer = setInterval(() => {
                if (this.pdfPollCountdown > 0) this.pdfPollCountdown--;
            }, 1000);

            this.pdfPollTimer = setTimeout(async () => {
                await this.fetchKonten();
            }, POLL_INTERVAL);
        },
        stopPdfPolling() {
            if (this.pdfPollTimer) {
                clearTimeout(this.pdfPollTimer);
                this.pdfPollTimer = null;
            }
            if (this.pdfPollCountdownTimer) {
                clearInterval(this.pdfPollCountdownTimer);
                this.pdfPollCountdownTimer = null;
            }
            this.pdfPollCountdown = 0;
        },

        // Dipanggil saat user klik "Coba Lagi" dari status FAILED.
        // fetchKonten akan trigger maybeDispatchPdfConversionJob(isRetry=true) di backend,
        // yang reset status ke PROCESSING dan dispatch job baru.
        async retryPdfConversion() {
            await this.fetchKonten();
            // Jika berhasil trigger (status jadi processing), polling sudah dimulai
            // otomatis oleh fetchKonten. Tidak perlu startPdfPolling() manual di sini.
        },

        buildViewerStateUrl(detId) {
            const baseUrl = `/api/v1/lms/materi/${this.materiId}/viewer/${detId}`;
            const params = new URLSearchParams();

            if (this.activePenjadwalanId) {
                params.set('penjadwalan_id', this.activePenjadwalanId);
            }

            if (!this.isAdminMode) {
                const query = params.toString();
                return query ? `${baseUrl}?${query}` : baseUrl;
            }

            const currentParams = new URLSearchParams(window.location.search);
            currentParams.forEach((value, key) => {
                if (!params.has(key)) {
                    params.set(key, value);
                }
            });
            params.set('mode', 'admin');
            if (this.adminTargetKodeKaryawan) {
                params.set('target_kode_karyawan', this.adminTargetKodeKaryawan);
            }
            if (!params.get('return_to')) {
                params.set('return_to', this.adminBackUrl);
            }

            return `${baseUrl}?${params.toString()}`;
        },
        clearDockHideTimer() {
            if (this.dockHideTimer) {
                clearTimeout(this.dockHideTimer);
                this.dockHideTimer = null;
            }
        },
        scheduleDockHide() {
            this.clearDockHideTimer();

            if (this.isPlaylistVisible || this.loading || this.isNavigating || !this.contentInfo) {
                this.isDockVisible = true;
                return;
            }

            this.dockHideTimer = setTimeout(() => {
                this.isDockVisible = false;
            }, this.dockHideDelayMs);
        },
        handleViewerInteraction() {
            if (this.loading || this.isNavigating || !this.contentInfo) {
                return;
            }
            this.isDockVisible = true;
            this.scheduleDockHide();
        },
        pinDock() {
            this.isDockVisible = true;
            this.clearDockHideTimer();
        },
        unpinDock() {
            this.scheduleDockHide();
        },
        revealDock() {
            this.isDockVisible = true;
            this.scheduleDockHide();
        },
        async recordViewerProgress(statusProgress = 'SELESAI', markCompleted = true) {
            if (this.isAdminMode) {
                return;
            }

            if (!this.itemData?.id_penjadwalan || !this.itemData?.id_penjadwalan_detail || !this.itemData?.id_materi) {
                return;
            }

            try {
                await axios.post('/api/v1/lms/absensi/training/activity/record', {
                    Id_Penjadwalan: this.itemData.id_penjadwalan,
                    Id_Penjadwalan_Detail: this.itemData.id_penjadwalan_detail,
                    Id_Penjadwalan_Det: this.itemData.id_penjadwalan_det || null,
                    Id_Materi: this.itemData.id_materi,
                    Id_Materi_Det: this.itemData.id_materi_det || this.activeDetId,
                    Urutan: this.itemData.urutan_materi || null,
                    Flag_Third_Party: this.itemData.aktivitas_third_party || 'T',
                    Status_Progress: statusProgress,
                    MarkStarted: true,
                    MarkCompleted: markCompleted,
                });
            } catch (error) {
                console.error('Failed to record viewer completion:', error);
            }
        },
        async fetchKonten() {
            this.loading = true;
            this.errorMsg = '';
            try {
                const contentParams = new URLSearchParams();
                if (this.activePenjadwalanId) {
                    contentParams.set('penjadwalan_id', this.activePenjadwalanId);
                }
                if (this.isAdminMode) {
                    contentParams.set('mode', 'admin');
                    if (this.adminTargetKodeKaryawan) {
                        contentParams.set('target_kode_karyawan', this.adminTargetKodeKaryawan);
                    }
                }
                const contentQuery = contentParams.toString();
                const contentUrl = `/api/v1/lms/materi/konten/${this.materiId}/${this.activeDetId}${
                    contentQuery ? `?${contentQuery}` : ''
                }`;

                const { data } = await axios.get(contentUrl);

                if (data.success) {
                    const result = data.result || data;
                    this.itemData = result.item;
                    this.trainingTitle = result.training_title || 'Training';
                    this.contentInfo = result.content;
                    this.flatSidebarItems = result.sidebar;

                    // Reset PDF state
                    this.pdfTotalPages = 0;
                    this.pdfCurrentPage = 1;
                    this.pdfErrorMsg = null;
                    this.pdfRenderedPages = {};
                    this.pdfPageImages = {};
                    this.pdfRotation = 0;
                    this.pdfViewerSignature = null;
                    this.pdfPageBaseUrl = null;
                    this.pdfManifestPages = [];
                    this.pdfLoadQueue = [];
                    this.pdfActiveLoads = 0;

                    // Jika konten berupa secure-pdf
                    if (this.contentInfo && this.contentInfo.type === 'secure-pdf') {
                        if (this.contentInfo.status === 'ready' && this.contentInfo.berkas_id) {
                            this.stopPdfPolling();
                            this.loadPdfSecurely(this.contentInfo.berkas_id);
                        } else if (
                            this.contentInfo.status === 'pending' ||
                            this.contentInfo.status === 'processing'
                        ) {
                            // Dokumen belum selesai dikonversi — polling otomatis setiap 5 detik
                            this.startPdfPolling();
                        } else {
                            // failed atau status lain — stop polling
                            this.stopPdfPolling();
                        }
                    }
                } else {
                    this.errorMsg = data.message || 'Gagal memuat konten.';
                }

                let detailUrl =
                    this.isAdminMode && this.adminTargetKodeKaryawan
                        ? this.activePenjadwalanId
                            ? `/lms/admin/monitoring/training/schedule/${this.activePenjadwalanId}/${this.adminTargetKodeKaryawan}/progress`
                            : `/lms/admin/monitoring/training/${this.materiId}/${this.adminTargetKodeKaryawan}/progress`
                        : `/api/v1/lms/materi/${this.materiId}`;
                if (!this.isAdminMode && this.activePenjadwalanId) {
                    detailUrl += `?penjadwalan_id=${encodeURIComponent(this.activePenjadwalanId)}`;
                }
                const { data: detailData } = await axios.get(detailUrl);
                if (detailData.success) {
                    const dResult = detailData.result || detailData;
                    if (dResult.sections) {
                        this.sidebarSections = dResult.sections;
                        if (Object.keys(this.collapsedSidebarSections).length === 0) {
                            this.sidebarSections.forEach((sec, idx) => {
                                const hasActive = sec.items.some((i) => i.id_materi_det == this.activeDetId);
                                this.collapsedSidebarSections[idx] = !hasActive;
                            });
                        }
                    }
                }
            } catch (err) {
                console.error(err);
                this.errorMsg = 'Terjadi kesalahan saat memuat konten.';
            } finally {
                this.loading = false;
                this.isDockVisible = true;
                this.scheduleDockHide();
            }
        },
        async loadPdfSecurely(berkasId) {
            this.pdfLoading = true;
            this.pdfStatusText = 'Menghubungkan...';
            this.pdfErrorMsg = null;
            this.pdfViewerSignature = null;
            this.pdfPageBaseUrl = null;
            this.pdfManifestPages = [];

            const qp = new URLSearchParams();
            if (this.activePenjadwalanId) qp.set('penjadwalan_id', this.activePenjadwalanId);
            qp.set('materi_id', String(this.materiId));
            qp.set('materi_det_id', String(this.activeDetId));
            const qs = qp.toString();

            try {
                // Step 1: Handshake — dapatkan encrypted signature (satu kali pakai)
                this.pdfStatusText = 'Memverifikasi akses...';
                const tokenRes = await axios.post(
                    `/api/v1/lms/materi/secure-pdf/token/${berkasId}${qs ? '?' + qs : ''}`,
                    {},
                    { headers: { 'X-App-Source': 'vue-client' } },
                );
                if (!tokenRes.data.success) {
                    this.pdfErrorMsg = tokenRes.data.message || 'Gagal mendapatkan akses dokumen.';
                    return;
                }
                const signature = tokenRes.data.result.signature;

                // Step 2: Consume handshake → dapatkan viewer_signature + manifest
                this.pdfStatusText = 'Menyiapkan dokumen...';
                const manifestUrl = `/api/v1/lms/materi/secure-pdf/pages/${berkasId}/manifest?x_signature=${encodeURIComponent(signature)}${qs ? '&' + qs : ''}`;
                const manifestRes = await axios.get(manifestUrl, {
                    headers: { 'X-App-Source': 'vue-client' },
                });
                if (!manifestRes.data.success) {
                    this.pdfErrorMsg = manifestRes.data.message || 'Gagal memuat manifest dokumen.';
                    return;
                }

                const result = manifestRes.data.result;
                this.pdfViewerSignature = result.viewer_signature;
                this.pdfPageBaseUrl = result.page_url_base;
                this.pdfTotalPages = result.total_pages;
                this.pdfManifestPages = result.pages || [];

                // Init status canvas per halaman
                this.pdfRenderedPages = Object.fromEntries(
                    Array.from({ length: this.pdfTotalPages }, (_, i) => [i + 1, false]),
                );

                this.pdfLoading = false;
                await this.$nextTick();

                if (this.pdfTotalPages >= 1) {
                    await this.ensurePdfPageLoaded(1);
                }
                if (this.pdfTotalPages > 1) {
                    this.prefetchNearbyPdfPages(1);
                }
            } catch (error) {
                console.error('Gagal memuat secure PDF:', error);
                this.pdfErrorMsg = 'Akses ditolak atau sesi berakhir.';
            } finally {
                this.pdfLoading = false;
            }
        },

        // --- SECURE CANVAS LOGIC ---
        async ensurePdfPageLoaded(pageNumber, allowRehandshake = true) {
            if (this.pdfRenderedPages[pageNumber]) return true;
            // Cegah double-load concurrent untuk halaman yang sama
            if (this.pdfPageImages[pageNumber]?.status === 'loading') return false;

            this.pdfPageImages[pageNumber] = { status: 'loading' };

            try {
                // Step A: Grant nonce untuk halaman ini (satu kali pakai)
                const grantRes = await axios.post(
                    `/api/v1/lms/materi/secure-pdf/pages/${this.contentInfo.berkas_id}/${pageNumber}/grant`,
                    { viewer_signature: this.pdfViewerSignature },
                    { headers: { 'X-App-Source': 'vue-client' } },
                );
                if (!grantRes.data.success) {
                    throw new Error(grantRes.data.message || 'Grant halaman gagal');
                }
                const pageNonce = grantRes.data.result.page_nonce;

                // Step B: Fetch blob gambar — server burn watermark, return raw bytes
                const imgRes = await axios.post(
                    `/api/v1/lms/materi/secure-pdf/pages/${this.contentInfo.berkas_id}/${pageNumber}`,
                    { page_nonce: pageNonce, viewer_signature: this.pdfViewerSignature },
                    {
                        headers: { 'X-App-Source': 'vue-client' },
                        responseType: 'blob',
                    },
                );

                // Step C: Blob → ObjectURL → Image → draw canvas → LANGSUNG revoke
                const objectUrl = URL.createObjectURL(imgRes.data);
                const img = new Image();

                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = () => reject(new Error('Gagal decode gambar'));
                    img.src = objectUrl;
                });

                // Revoke segera setelah image decoded — URL tidak bisa dipakai lagi
                URL.revokeObjectURL(objectUrl);

                this.pdfPageImages[pageNumber] = {
                    status: 'loaded',
                    image: img,
                    width: img.naturalWidth,
                    height: img.naturalHeight,
                };
                this.pdfRenderedPages[pageNumber] = true;

                if (pageNumber === 1) {
                    await this.fitPdfToContainer();
                }

                await this.$nextTick();
                this.drawPdfPageCanvas(pageNumber);
                return true;
            } catch (err) {
                const status = err?.response?.status;
                // Viewer session / nonce kedaluwarsa (bukan sesi browser habis).
                // Re-handshake SEKALI lalu coba lagi — mencegah "gagal diam" saat
                // signature transien hilang (mis. Cloud Run instance baru).
                if (allowRehandshake && (status === 401 || status === 403)) {
                    const refreshed = await this.refreshPdfHandshake();
                    if (refreshed) {
                        this.pdfPageImages[pageNumber] = { status: 'loading' };
                        return this.ensurePdfPageLoaded(pageNumber, false);
                    }
                }

                this.pdfPageImages[pageNumber] = { status: 'error' };
                console.error(`Error loading page ${pageNumber}`, err);
                return false;
            }
        },

        /**
         * Ambil ulang handshake (token → manifest → viewer_signature) tanpa
         * me-reset canvas yang sudah ter-render. Dipakai saat viewer_signature
         * kedaluwarsa di tengah sesi. Return true kalau berhasil.
         */
        async refreshPdfHandshake() {
            try {
                const berkasId = this.contentInfo?.berkas_id;
                if (!berkasId) return false;

                const qp = new URLSearchParams();
                if (this.activePenjadwalanId) qp.set('penjadwalan_id', this.activePenjadwalanId);
                qp.set('materi_id', String(this.materiId));
                qp.set('materi_det_id', String(this.activeDetId));
                const qs = qp.toString();

                const tokenRes = await axios.post(
                    `/api/v1/lms/materi/secure-pdf/token/${berkasId}${qs ? '?' + qs : ''}`,
                    {},
                    { headers: { 'X-App-Source': 'vue-client' } },
                );
                if (!tokenRes.data.success) return false;
                const signature = tokenRes.data.result.signature;

                const manifestUrl = `/api/v1/lms/materi/secure-pdf/pages/${berkasId}/manifest?x_signature=${encodeURIComponent(signature)}${qs ? '&' + qs : ''}`;
                const manifestRes = await axios.get(manifestUrl, {
                    headers: { 'X-App-Source': 'vue-client' },
                });
                if (!manifestRes.data.success) return false;

                this.pdfViewerSignature = manifestRes.data.result.viewer_signature;
                this.pdfPageBaseUrl = manifestRes.data.result.page_url_base;
                return true;
            } catch (e) {
                console.error('Re-handshake PDF gagal:', e);
                return false;
            }
        },
        getPdfPageMeta(pageNumber) {
            // Prefer actual rendered dimensions (tersedia setelah canvas selesai)
            const imgData = this.pdfPageImages[pageNumber];
            if (imgData?.status === 'loaded') {
                return { width: imgData.width, height: imgData.height };
            }
            // Fallback ke manifest dari server (dimensi asli PDF)
            const manifest = this.pdfManifestPages.find((p) => p.page === pageNumber);
            if (manifest) {
                return { width: manifest.width, height: manifest.height };
            }
            return { width: this.pdfPlaceholderWidth || 720, height: this.pdfPlaceholderHeight || 960 };
        },
        async fitPdfToContainer() {
            const scrollArea = this.$refs.pdfScrollArea;
            if (!scrollArea) return;

            const firstPage = this.getPdfPageMeta(1);
            if (!firstPage) return;

            const containerWidth = Math.max(240, scrollArea.clientWidth - 32);
            const pageBox = this.getPdfRotatedDimensions(firstPage.width, firstPage.height);
            // Fit WIDTH: halaman selalu pas selebar container (ujung ke ujung).
            // Dokumen tinggi memunculkan vertical scroll (wajar); horizontal scroll
            // hanya muncul saat user zoom manual.
            const fittedScale = containerWidth / pageBox.width;
            const normalizedScale = Math.max(
                this.pdfMinScale,
                Math.min(this.pdfMaxScale, parseFloat(fittedScale.toFixed(2))),
            );

            this.pdfScale = normalizedScale;
            this.pdfAutoFitScale = normalizedScale;
            this.pdfManualScale = false;
            this.pdfPlaceholderWidth = Math.max(240, Math.floor(pageBox.width * normalizedScale));
            this.pdfPlaceholderHeight = Math.max(320, Math.floor(pageBox.height * normalizedScale));
        },
        getPdfRotatedDimensions(width, height) {
            return this.pdfRotation % 180 === 0 ? { width, height } : { width: height, height: width };
        },
        getPdfPageWrapperStyle(pageNumber) {
            const pageMeta = this.getPdfPageMeta(pageNumber);
            const rotated = this.getPdfRotatedDimensions(pageMeta.width, pageMeta.height);
            const w = Math.max(240, Math.floor(rotated.width * this.pdfScale));
            const h = Math.max(320, Math.floor(rotated.height * this.pdfScale));
            return { width: `${w}px`, height: `${h}px` };
        },
        getPdfPageCanvasStyle(_pageNumber) {
            // Canvas fill wrapper via CSS (position absolute, 100%x100%).
            // Ukuran nyata (pixel buffer) diatur di drawPdfPageCanvas via canvas.width/height.
            return {};
        },
        drawPdfPageCanvas(pageNumber) {
            const pageState = this.pdfPageImages[pageNumber];
            const image = pageState?.image;
            const pageMeta = this.getPdfPageMeta(pageNumber);
            if (!image || !pageMeta) return;

            const canvasRef = this.$refs['pdfPageCanvas-' + pageNumber];
            const canvas = Array.isArray(canvasRef) ? canvasRef[0] : canvasRef;
            if (!canvas) return;

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const rotated = this.getPdfRotatedDimensions(pageMeta.width, pageMeta.height);
            const displayWidth = Math.max(1, Math.floor(rotated.width * this.pdfScale));
            const displayHeight = Math.max(1, Math.floor(rotated.height * this.pdfScale));
            const baseWidth = Math.max(1, Math.floor(pageMeta.width * this.pdfScale));
            const baseHeight = Math.max(1, Math.floor(pageMeta.height * this.pdfScale));
            const pixelWidth = Math.max(1, Math.floor(displayWidth * dpr));
            const pixelHeight = Math.max(1, Math.floor(displayHeight * dpr));

            if (canvas.width !== pixelWidth || canvas.height !== pixelHeight) {
                canvas.width = pixelWidth;
                canvas.height = pixelHeight;
            }
            // Canvas CSS size mengikuti wrapper (diatur via CSS 100%x100%) — jangan override.
            // Tapi perlu pastikan pixel buffer tidak blur dengan menyinkronkan display size.
            canvas.style.width = `${displayWidth}px`;
            canvas.style.height = `${displayHeight}px`;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.scale(dpr, dpr);

            ctx.translate(displayWidth / 2, displayHeight / 2);
            ctx.rotate((this.pdfRotation * Math.PI) / 180);
            ctx.drawImage(image, -baseWidth / 2, -baseHeight / 2, baseWidth, baseHeight);

            canvas.addEventListener('dragstart', (e) => e.preventDefault(), { capture: true });
        },
        redrawLoadedPdfPages() {
            Object.keys(this.pdfPageImages || {}).forEach((pageNumber) => {
                if (this.pdfPageImages[pageNumber]?.status === 'loaded') {
                    this.drawPdfPageCanvas(Number(pageNumber));
                }
            });
        },
        getPdfPagePlaceholderText(pageNumber) {
            return this.pdfPageImages[pageNumber]?.status === 'error'
                ? `Halaman ${pageNumber} gagal dimuat`
                : `Memuat halaman ${pageNumber}`;
        },
        getPdfScrollRatios() {
            const scrollArea = this.$refs.pdfScrollArea;
            if (!scrollArea) return { x: 0.5, y: 0 };
            const maxX = scrollArea.scrollWidth - scrollArea.clientWidth;
            const maxY = scrollArea.scrollHeight - scrollArea.clientHeight;
            return {
                x: maxX > 0 ? (scrollArea.scrollLeft + scrollArea.clientWidth / 2) / scrollArea.scrollWidth : 0.5,
                y: maxY > 0 ? scrollArea.scrollTop / maxY : 0,
            };
        },
        restorePdfScrollRatios(ratios) {
            const scrollArea = this.$refs.pdfScrollArea;
            if (!scrollArea) return;
            const maxX = scrollArea.scrollWidth - scrollArea.clientWidth;
            const maxY = scrollArea.scrollHeight - scrollArea.clientHeight;
            if (maxX > 0) {
                const nextCenter = scrollArea.scrollWidth * ratios.x;
                scrollArea.scrollLeft = Math.min(maxX, Math.max(0, nextCenter - scrollArea.clientWidth / 2));
            } else {
                scrollArea.scrollLeft = 0;
            }
            if (maxY > 0) {
                scrollArea.scrollTop = Math.min(maxY, Math.max(0, maxY * ratios.y));
            }
        },
        async changePdfScale(delta) {
            const newScale = parseFloat((this.pdfScale + delta).toFixed(2));
            if (newScale < this.pdfMinScale || newScale > this.pdfMaxScale || newScale === this.pdfScale) return;

            const ratios = this.getPdfScrollRatios();
            this.pdfScale = newScale;
            this.pdfManualScale = true;
            await this.$nextTick();
            this.redrawLoadedPdfPages();
            this.restorePdfScrollRatios(ratios);
        },
        async resetPdfScaleToFit() {
            const ratios = this.getPdfScrollRatios();
            await this.fitPdfToContainer();
            await this.$nextTick();
            this.redrawLoadedPdfPages();
            this.restorePdfScrollRatios(ratios);
        },
        async rotatePdf(delta) {
            const ratios = this.getPdfScrollRatios();
            this.pdfRotation = (((this.pdfRotation + delta) % 360) + 360) % 360;
            await this.fitPdfToContainer();
            await this.$nextTick();
            this.redrawLoadedPdfPages();
            this.restorePdfScrollRatios(ratios);
        },
        onPdfScroll() {
            // Debounce: hanya proses prefetch setelah scroll berhenti 120ms.
            // Saat scroll brutal, event ini terpanggil ratusan kali/detik —
            // tanpa debounce setiap scroll position akan enqueue halaman baru.
            if (this.pdfScrollDebounceTimer) {
                clearTimeout(this.pdfScrollDebounceTimer);
            }

            // Update halaman aktif langsung (untuk UI indicator) tanpa prefetch
            const scrollArea = this.$refs.pdfScrollArea;
            if (!scrollArea) return;

            this.pdfScrollDebounceTimer = setTimeout(() => {
                this.pdfScrollDebounceTimer = null;
                const viewportMiddle = scrollArea.scrollTop + scrollArea.clientHeight / 2;
                let activePage = 1;
                let closestDistance = Number.POSITIVE_INFINITY;

                for (let page = 1; page <= this.pdfTotalPages; page++) {
                    const wrapperRef = this.$refs['pdfPageWrapper-' + page];
                    const wrapper = Array.isArray(wrapperRef) ? wrapperRef[0] : wrapperRef;
                    if (!wrapper) continue;

                    const pageMiddle = wrapper.offsetTop + wrapper.offsetHeight / 2;
                    const distance = Math.abs(pageMiddle - viewportMiddle);
                    if (distance < closestDistance) {
                        closestDistance = distance;
                        activePage = page;
                    }
                }

                this.pdfCurrentPage = activePage;

                // Flush queue lama yang sudah tidak relevan dengan posisi scroll baru,
                // lalu enqueue halaman sekitar posisi sekarang
                this.pdfLoadQueue = this.pdfLoadQueue.filter(
                    (q) => Math.abs(q.page - activePage) <= 1,
                );
                this.prefetchNearbyPdfPages(activePage);
            }, this.PDF_SCROLL_DEBOUNCE_MS);
        },
        // Enqueue halaman terdekat dengan priority berdasarkan jarak dari centerPage.
        // Halaman yang sudah loaded / sudah di queue / sedang loading diabaikan.
        prefetchNearbyPdfPages(centerPage = 1) {
            // window: 1 sebelum + 3 sesudah (prioritas ke depan karena user scroll ke bawah)
            const candidates = [];
            for (let offset = -1; offset <= 3; offset++) {
                const page = centerPage + offset;
                if (page >= 1 && page <= this.pdfTotalPages) {
                    candidates.push({ page, priority: Math.abs(offset) });
                }
            }

            for (const { page, priority } of candidates) {
                if (this.pdfRenderedPages[page]) continue;
                if (this.pdfPageImages[page]?.status === 'loading') continue;
                if (this.pdfLoadQueue.some((q) => q.page === page)) continue;

                this.pdfLoadQueue.push({ page, priority });
            }

            // Sort queue: prioritas terkecil (terdekat dari viewport) duluan
            this.pdfLoadQueue.sort((a, b) => a.priority - b.priority);
            this.drainPdfQueue();
        },

        // Jalankan queue selama slot concurrent tersedia
        drainPdfQueue() {
            while (this.pdfActiveLoads < this.PDF_MAX_CONCURRENT && this.pdfLoadQueue.length > 0) {
                const item = this.pdfLoadQueue.shift();
                if (!item) break;

                // Skip jika sudah selesai di-load saat menunggu di queue
                if (this.pdfRenderedPages[item.page]) {
                    this.drainPdfQueue();
                    return;
                }

                this.pdfActiveLoads++;
                this.ensurePdfPageLoaded(item.page)
                    .catch((err) => console.error(`PDF queue error page ${item.page}:`, err))
                    .finally(() => {
                        this.pdfActiveLoads--;
                        this.drainPdfQueue();
                    });
            }
        },
        async handleWindowResize() {
            if (!this.pdfTotalPages || this.pdfLoading) return;

            if (!this.pdfManualScale) {
                await this.fitPdfToContainer();
                await this.$nextTick();
                this.redrawLoadedPdfPages();
            }
        },
        // --- END CANVAS LOGIC ---

        toggleSidebarSection(idx) {
            this.collapsedSidebarSections[idx] = !this.collapsedSidebarSections[idx];
        },
        togglePlaylist() {
            this.isPlaylistVisible = !this.isPlaylistVisible;

            if (this.isPlaylistVisible) {
                this.pinDock();
                return;
            }

            this.scheduleDockHide();
        },
        getSidebarItemType(navItem) {
            let type = navItem.kode_aktivitas;
            if (navItem.tipe_konten === 'video' && !['PRT', 'PTT', 'QIZ', 'ELS', 'ABS'].includes(type)) {
                type = 'VIDEO';
            }
            return type;
        },
        isItemCompleted(navItem) {
            if (navItem.flag_selesai === 'Y') return true;
            if (navItem.status_penjadwalan === 'done' || navItem.status_penjadwalan === 'passed') return true;
            return false;
        },
        async loadItem(id, type) {
            if (id === this.activeDetId) return;

            const isMedia = ['MDL', 'VIDEO'].includes(type) || !type || type === 'video';

            if (this.contentInfo && !this.skipAutoCompleteOnce) {
                await this.recordViewerProgress('SELESAI', true);
            }
            this.skipAutoCompleteOnce = false;

            if (isMedia) {
                this.activeDetId = id;
                window.history.pushState({}, '', this.buildViewerStateUrl(id));
                await this.fetchKonten();
                if (window.innerWidth < 992) {
                    this.isPlaylistVisible = false;
                }
            } else {
                this.isNavigating = true;
                if (this.isAdminMode) {
                    const joiner = this.adminBackUrl.includes('?') ? '&' : '?';
                    this.$inertia.visit(`${this.adminBackUrl}${joiner}scrollTo=${id}`);
                } else {
                    const joiner = this.userDetailUrl.includes('?') ? '&' : '?';
                    this.$inertia.visit(`${this.userDetailUrl}${joiner}scrollTo=${id}`);
                }
            }
        },
        async markCompleteAndNext() {
            await this.recordViewerProgress('SELESAI', true);

            const currentIndex = this.flatSidebarItems.findIndex((i) => i.id == this.activeDetId);
            if (currentIndex !== -1 && currentIndex < this.flatSidebarItems.length - 1) {
                const nextItem = this.flatSidebarItems[currentIndex + 1];
                this.skipAutoCompleteOnce = true;
                await this.loadItem(nextItem.id, nextItem.type);
            } else {
                this.skipAutoCompleteOnce = true;
                await this.goBack();
            }
        },
        async goBack() {
            if (this.contentInfo) {
                if (!this.skipAutoCompleteOnce) {
                    await this.recordViewerProgress('SELESAI', true);
                }
            }

            this.skipAutoCompleteOnce = false;
            this.isNavigating = true;
            if (this.isAdminMode) {
                this.$inertia.visit(this.adminBackUrl);
            } else {
                this.$inertia.visit(this.userDetailUrl);
            }
        },
        getContentTypeLabel(type) {
            if (!type) return 'Materi Singkat';
            if (type === 'video') return 'Video Streaming';
            if (type === 'pdf' || type === 'secure-pdf') return 'Dokumen PDF';
            return 'Aktivitas';
        },
        toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch((err) => {
                    console.log(`Error attempting to enable fullscreen: ${err.message}`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        },
        onFullscreenChange() {
            this.isFullscreen = !!document.fullscreenElement;
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.viewer-layout {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    background-color: var(--hcis-slate-50);
    overflow: hidden;
    font-family:
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        Helvetica,
        Arial,
        sans-serif;
}

/* Full Overlay Loading */
.full-overlay-loading {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 9999;
}

/* Main Toolbar */
.glass-toolbar {
    position: relative;
    flex-shrink: 0;
    z-index: 50;
    background-color: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-200);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.hover-scale {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.hover-scale:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
}

/* Player Body */
.viewer-body {
    flex-grow: 1;
    overflow: hidden;
}
.absolute-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.full-iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

.viewer-media {
    min-height: 0;
}

/* Floating Dock */
.glass-dock {
    background-color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.floating-dock-shell {
    /* Navigasi bawah disembunyikan — toolbar atas sudah cukup (Selesai + Lanjutkan) */
    display: none !important;
}

.dock-reveal-btn {
    display: none !important;
}

.dock-title {
    font-size: 1rem;
    line-height: 1.25;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dock-description {
    max-width: 320px;
}

.dock-action-btn {
    min-width: 184px;
}

.dock-reveal-btn {
    position: absolute;
    right: 1.1rem;
    bottom: 1rem;
    z-index: 11;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.45rem 0.8rem;
}

/* Offcanvas Drawer */
.offcanvas-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 360px;
    max-width: 100vw;
    z-index: 1050;
    transform: translateX(100%);
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.offcanvas-drawer.is-open {
    transform: translateX(0);
}
.drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1040;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.35s ease;
}
.drawer-overlay.is-open {
    opacity: 1;
    pointer-events: auto;
}

/* Drawer / Playlist styles */
.drawer-body::-webkit-scrollbar {
    width: 6px;
}
.drawer-body::-webkit-scrollbar-track {
    background: transparent;
}
.drawer-body::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 4px;
}
.drawer-body::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-400);
}

.drawer-section-header {
    transition: background 0.2s;
}
.drawer-section-header:hover {
    background-color: var(--hcis-slate-50);
}
.drawer-section-header.active {
    background-color: var(--hcis-slate-100);
}
.hover-bg {
    transition: background 0.2s;
}
.hover-bg:hover {
    background-color: var(--hcis-slate-100);
}
.active-indicator {
    position: absolute;
    left: -8px;
    top: 10px;
    bottom: 10px;
    width: 4px;
    background-color: var(--hcis-indigo-500);
    border-radius: 0 4px 4px 0;
}

/* PDF Area */
.unselectable {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
.pdf-scroll-area {
    /* Scroll vertikal & horizontal agar konten tidak terpotong saat zoom besar */
    overflow: auto !important;
}
.pdf-scroll-area::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.pdf-scroll-area::-webkit-scrollbar-track {
    background: var(--hcis-slate-200);
}
.pdf-scroll-area::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-400);
    border-radius: 4px;
}
.pdf-scroll-area::-webkit-scrollbar-thumb:hover {
    background: var(--hcis-slate-500);
}

/* Pages stack: min-width mengikuti konten agar horizontal scroll bekerja */
.pdf-pages-stack {
    min-width: min-content;
}

.pdf-page-wrapper {
    position: relative;
    background-color: #fff;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.pdf-page-canvas {
    /* Fill wrapper sepenuhnya — wrapper yang mengikuti ukuran halaman via JS style */
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    display: block;
    background: #fff;
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}

.pdf-page-placeholder {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: var(--hcis-slate-500);
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.96));
}

.shield-layer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: transparent;
    z-index: 10;
    cursor: default;
    user-select: none;
    -webkit-user-drag: none;
    -webkit-user-select: none;
}

.page-number-badge {
    position: absolute;
    bottom: 8px;
    right: 12px;
    z-index: 20;
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
    background: rgba(255, 255, 255, 0.85);
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

/* Utilities */
.text-primary {
    color: var(--hcis-indigo-500) !important;
}
.bg-primary {
    background-color: var(--hcis-indigo-500) !important;
}
.bg-primary-subtle {
    background-color: var(--hcis-indigo-50) !important;
}
.text-success {
    color: var(--hcis-emerald-500) !important;
}
.bg-success-subtle {
    background-color: var(--hcis-emerald-100) !important;
}
.min-w-0 {
    min-width: 0;
}
.transition-all {
    transition: all 0.2s ease;
}
.z-index-5 {
    z-index: 5;
}
.z-index-10 {
    z-index: 10;
}
</style>

<style scoped>
@media print {
    body {
        display: none !important;
    }
}
</style>
