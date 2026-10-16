<template>
    <div class="cuti-app-container">
        <!-- Floating Overlay for Loading State during Navigation -->
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

        <HeaderKpi :title="pageTitle" :subtitle="pageSubtitle" :dayNight="dayNight" :formattedDate="formattedDate" />

        <div class="main-content-wrapper">
            <div class="content-container">
                <div class="detail-surface p-4 rounded-4">
                    <!-- Breadcrumb -->
                    <nav class="detail-breadcrumb">
                        <template v-if="isAdminMode">
                            <a href="/lms/admin/monitoring" class="bc-link">
                                <i class="bi bi-speedometer2 me-1"></i>Monitoring
                            </a>
                            <span class="bc-sep mx-2">/</span>
                            <span class="bc-current">{{ materiTitle }}</span>
                        </template>
                        <template v-else>
                            <a href="/lms/training/catalog" class="bc-link">
                                <i class="bi bi-house me-1"></i>Training
                            </a>
                            <span class="bc-sep mx-2">/</span>
                            <a href="/lms/training/catalog" class="bc-link">Training Catalog</a>
                            <span class="bc-sep mx-2">/</span>
                            <span class="bc-current">{{ materiTitle }}</span>
                        </template>
                    </nav>

                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2" style="font-size: 0.85rem">Memuat materi training...</p>
                    </div>

                    <!-- Error State -->
                    <div v-else-if="errorMsg" class="alert alert-warning text-center" style="font-size: 0.85rem">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        {{ errorMsg }}
                    </div>

                    <!-- Dynamic Content -->
                    <template v-else>
                        <!-- Header Hero -->
                        <TrainingDetailHeader :course="headerData" :viewMode="viewMode" :employeeName="employeeName" />

                        <section class="training-info-summary mb-4">
                            <div class="training-info-head">
                                <div>
                                    <p class="training-info-eyebrow mb-1">Training Info</p>
                                    <h5 class="training-info-title mb-0">Informasi Training</h5>
                                </div>
                                <button
                                    class="btn btn-sm btn-primary rounded-pill px-3 d-inline-flex align-items-center gap-2"
                                    @click="showTrainingInfoModal = true"
                                >
                                    <i class="bi bi-layout-text-window-reverse"></i>
                                    Lihat Detail
                                </button>
                            </div>
                            <p class="training-info-description mb-2">{{ shortDescription || '-' }}</p>
                            <div class="training-summary-grid mb-2">
                                <span class="summary-chip">
                                    Outline: {{ trainingInfo.outlineSession.length || 0 }} sesi
                                </span>
                                <span class="summary-chip"> Metode: {{ trainingInfo.metode.length || 0 }} item </span>
                                <span class="summary-chip">
                                    Durasi: {{ formatTotalDurasi(trainingInfo.totalDurasi) }}
                                </span>
                            </div>
                            <!-- <div class="training-info-note">
                                Ringkasan ditampilkan di sini. Klik <strong>Lihat Detail</strong> untuk melihat seluruh
                                informasi training.
                            </div> -->
                        </section>

                        <section v-if="isAdminMode" class="attendance-summary-section mb-4">
                            <div class="attendance-summary-head">
                                <div>
                                    <p class="attendance-eyebrow mb-1">Absensi User</p>
                                    <h5 class="attendance-title mb-0">
                                        Informasi Absensi {{ employeeName || 'Karyawan' }}
                                    </h5>
                                </div>
                                <div class="d-inline-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    <span
                                        class="attendance-status-pill"
                                        :class="`status-${attendanceInsight.status || 'not-required'}`"
                                    >
                                        <i class="bi" :class="getAttendanceStatusIcon(attendanceInsight.status)"></i>
                                        {{ attendanceInsight.status_label || 'Absensi tidak tersedia' }}
                                    </span>
                                    <button
                                        class="attendance-collapse-btn"
                                        type="button"
                                        @click="attendanceCollapsed = !attendanceCollapsed"
                                    >
                                        <i
                                            class="bi"
                                            :class="attendanceCollapsed ? 'bi-chevron-down' : 'bi-chevron-up'"
                                        ></i>
                                        {{ attendanceCollapsed ? 'Tampilkan' : 'Sembunyikan' }}
                                    </button>
                                </div>
                            </div>

                            <transition name="slide-fade">
                                <div v-show="!attendanceCollapsed">
                                    <div v-if="showAttendanceMetrics" class="attendance-metric-grid mb-3">
                                        <div class="attendance-metric-card">
                                            <span class="metric-label">Wajib Check-in</span>
                                            <span class="metric-value">{{
                                                attendanceInsight.total_required || 0
                                            }}</span>
                                        </div>
                                        <div class="attendance-metric-card">
                                            <span class="metric-label">Sudah Check-in</span>
                                            <span class="metric-value">{{
                                                attendanceInsight.total_checked_in || 0
                                            }}</span>
                                        </div>
                                        <div class="attendance-metric-card">
                                            <span class="metric-label">Progress Absensi</span>
                                            <span class="metric-value"
                                                >{{ attendanceInsight.completion_percent || 0 }}%</span
                                            >
                                        </div>
                                    </div>

                                    <div v-if="attendanceRecords.length" class="attendance-audit-grid">
                                        <article
                                            v-for="(row, idx) in attendanceRecords"
                                            :key="`attendance-${idx}`"
                                            class="attendance-audit-card"
                                        >
                                            <div class="attendance-audit-head">
                                                <div>
                                                    <div class="fw-semibold text-dark">
                                                        {{ row.label || 'Absensi' }}
                                                    </div>
                                                    <small v-if="row.batas_akses" class="text-muted"
                                                        >Batas akses: {{ formatDateTime(row.batas_akses) }}</small
                                                    >
                                                </div>
                                                <span class="attendance-row-pill" :class="`is-${row.status}`">
                                                    <i
                                                        class="bi"
                                                        :class="
                                                            row.status === 'checked-in'
                                                                ? 'bi-check-circle-fill'
                                                                : 'bi-dash-circle'
                                                        "
                                                    ></i>
                                                    {{ row.status_label }}
                                                </span>
                                            </div>

                                            <div class="attendance-audit-body">
                                                <div class="attendance-evidence-photo-wrap">
                                                    <a
                                                        v-if="row.foto_bukti"
                                                        :href="row.foto_bukti"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="attendance-evidence-photo-link"
                                                    >
                                                        <img
                                                            :src="row.foto_bukti"
                                                            alt="Bukti absensi"
                                                            class="attendance-evidence-photo"
                                                        />
                                                        <span class="attendance-photo-badge"
                                                            ><i class="bi bi-arrows-fullscreen me-1"></i>Lihat
                                                            Foto</span
                                                        >
                                                    </a>
                                                    <div v-else class="attendance-evidence-photo-empty">
                                                        <i class="bi bi-image"></i>
                                                        <span>Foto bukti tidak tersedia</span>
                                                    </div>
                                                </div>

                                                <div class="attendance-evidence-meta">
                                                    <div v-if="row.has_checkin_log" class="attendance-meta-item">
                                                        <span class="meta-key">Jam Check-in</span>
                                                        <span class="meta-value">{{
                                                            formatDateTime(row.waktu_checkin)
                                                        }}</span>
                                                    </div>
                                                    <div v-if="row.has_checkout_log" class="attendance-meta-item">
                                                        <span class="meta-key">Jam Check-out</span>
                                                        <span class="meta-value">{{
                                                            formatDateTime(row.waktu_checkout)
                                                        }}</span>
                                                    </div>
                                                    <div class="attendance-meta-item">
                                                        <span class="meta-key">Lokasi</span>
                                                        <span class="meta-value">{{ row.lokasi || '-' }}</span>
                                                    </div>
                                                    <div class="attendance-meta-item">
                                                        <span class="meta-key">Koordinat</span>
                                                        <span class="meta-value">{{ row.koordinat || '-' }}</span>
                                                    </div>
                                                    <a
                                                        v-if="row.latitude && row.longitude"
                                                        :href="buildMapsUrl(row.latitude, row.longitude)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="attendance-map-link"
                                                    >
                                                        <i class="bi bi-geo-alt me-1"></i>Buka Lokasi di Maps
                                                    </a>
                                                </div>
                                            </div>
                                        </article>
                                    </div>
                                    <div v-else class="attendance-empty">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Belum ada data absensi untuk training ini.
                                    </div>
                                </div>
                            </transition>
                        </section>

                        <!-- Render sections dynamically based on DB data -->
                        <template v-for="(section, sIdx) in sections" :key="section.id_materi_detail">
                            <!-- Sub-section heading (collapsible group) -->
                            <div
                                class="sub-section-header"
                                :class="{ 'is-collapsible': true }"
                                @click="toggleSection(sIdx)"
                            >
                                <h6 class="sub-section-title mb-0">
                                    <i
                                        class="bi me-2"
                                        :class="collapsedSections[sIdx] ? 'bi-chevron-right' : 'bi-chevron-down'"
                                    ></i>
                                    {{ section.nama_sub }}
                                </h6>
                                <span v-if="isSectionCompleted(sIdx)" class="sub-section-status is-complete">
                                    <i class="bi bi-check-circle-fill me-1"></i>Selesai
                                </span>
                                <span v-else class="sub-section-count">{{ section.items.length }} item</span>
                            </div>

                            <!-- Items within this section -->
                            <div v-show="!collapsedSections[sIdx]" class="sub-section-body">
                                <template v-for="item in section.renderedItems" :key="item._renderKey">
                                    <TrainingDetailSectionRenderer
                                        :id="
                                            item._singleItem
                                                ? 'item-' + item._singleItem.id_materi_det
                                                : item._materials.length
                                                  ? 'item-mdl-' + item._materials[0].id_materi_det
                                                  : null
                                        "
                                        :sectionType="item._sectionType"
                                        :item="item._singleItem"
                                        :materials="item._materials"
                                        :statusMap="itemStatuses"
                                        :resultSummary="trainingResult"
                                        :locked="isItemLocked(sIdx, item._orderIndex)"
                                        :status="getItemStatus(item._singleItem?.id_materi_det || item._renderKey)"
                                        :viewMode="viewMode"
                                        @view-test-detail="handleViewTestDetail"
                                        @start-test="
                                            handleTestComplete(
                                                item._singleItem?.id_materi_det,
                                                item._sectionType,
                                                item._singleItem,
                                            )
                                        "
                                        @view-material="handleMaterialView"
                                        @complete-video="handleVideoComplete"
                                        @complete-quiz="handleQuizComplete"
                                        @start-eval="
                                            handleEvalComplete(item._singleItem?.id_materi_det, item._singleItem)
                                        "
                                    />
                                </template>
                            </div>
                        </template>

                        <Modal
                            :show="showTrainingInfoModal"
                            title="Detail Informasi Training"
                            subtitle="Outline session, metode, durasi, target peserta, dan standar kelulusan"
                            size="large"
                            :showFooter="false"
                            @close="showTrainingInfoModal = false"
                            @update:show="showTrainingInfoModal = $event"
                        >
                            <template #body>
                                <div class="training-modal-table">
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Nama Modul</div>
                                        <div class="training-modal-value">
                                            {{ normalizedMateri?.title || '-' }}
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Deskripsi</div>
                                        <div class="training-modal-value">
                                            {{ normalizedMateri?.description || '-' }}
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Outline Session</div>
                                        <div class="training-modal-value">
                                            <ol
                                                v-if="trainingInfo.outlineSession.length"
                                                class="modal-list ordered mb-0"
                                            >
                                                <li
                                                    v-for="(item, idx) in trainingInfo.outlineSession"
                                                    :key="`outline-${idx}`"
                                                >
                                                    {{ item }}
                                                </li>
                                            </ol>
                                            <span v-else>-</span>
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Metode</div>
                                        <div class="training-modal-value">
                                            <ul v-if="trainingInfo.metode.length" class="modal-list mb-0">
                                                <li v-for="(item, idx) in trainingInfo.metode" :key="`metode-${idx}`">
                                                    {{ item }}
                                                </li>
                                            </ul>
                                            <span v-else>-</span>
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Durasi</div>
                                        <div class="training-modal-value">
                                            <ul v-if="trainingInfo.durasi.length" class="modal-list mb-0">
                                                <li v-for="(item, idx) in trainingInfo.durasi" :key="`durasi-${idx}`">
                                                    {{ item }}
                                                </li>
                                            </ul>
                                            <span v-else>-</span>
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Total Durasi</div>
                                        <div class="training-modal-value">
                                            {{ formatTotalDurasi(trainingInfo.totalDurasi) }}
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Sasaran Peserta</div>
                                        <div class="training-modal-value">
                                            <ul v-if="trainingInfo.sasaranPeserta.length" class="modal-list mb-0">
                                                <li
                                                    v-for="(item, idx) in trainingInfo.sasaranPeserta"
                                                    :key="`sasaran-${idx}`"
                                                >
                                                    {{ item }}
                                                </li>
                                            </ul>
                                            <span v-else>-</span>
                                        </div>
                                    </div>
                                    <div class="training-modal-row">
                                        <div class="training-modal-key">Standar Kelulusan</div>
                                        <div class="training-modal-value">
                                            {{ trainingInfo.standarKelulusan ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Modal>

                        <Modal
                            :show="showAchievementModal"
                            title="Selamat, Training Selesai!"
                            subtitle="Anda sudah menuntaskan seluruh aktivitas dan layak menerima pencapaian ini"
                            size="xlarge"
                            :showFooter="true"
                            :showCancel="true"
                            cancelText="Tutup"
                            :showConfirm="false"
                            :closeOnOverlay="true"
                            :closeOnEscape="true"
                            bodyClass="achievement-modal-body"
                            @close="closeAchievementModal"
                            @update:show="showAchievementModal = $event"
                        >
                            <template #body>
                                <div class="achievement-shell">
                                    <div class="achievement-glow achievement-glow-left"></div>
                                    <div class="achievement-glow achievement-glow-right"></div>

                                    <div class="achievement-confetti">
                                        <span
                                            v-for="piece in achievementConfetti"
                                            :key="piece"
                                            class="achievement-confetti-piece"
                                        ></span>
                                    </div>

                                    <div class="achievement-hero">
                                        <div class="achievement-artboard" aria-hidden="true">
                                            <DotLottieVue
                                                class="achievement-lottie"
                                                autoplay
                                                loop
                                                src="/animation/Ai.lottie"
                                            />
                                            <div class="achievement-badge-ring">
                                                <div class="achievement-badge-core">
                                                    <i class="bi bi-trophy-fill"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="achievement-hero-copy">
                                            <span class="achievement-eyebrow">
                                                <i class="bi bi-stars me-1"></i>Achievement Unlocked
                                            </span>
                                            <h2 class="achievement-title">{{ achievementTitle }}</h2>
                                            <p class="achievement-copy">
                                                {{ achievementMessage }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="achievement-grid"
                                        :class="{ 'achievement-grid-two-cols': !showAchievementFinalScore }"
                                    >
                                        <div class="achievement-stat-card achievement-stat-card-primary">
                                            <div class="achievement-stat-label">Progress</div>
                                            <div class="achievement-stat-value">100%</div>
                                            <div class="achievement-stat-caption">Semua aktivitas selesai</div>
                                        </div>
                                        <div class="achievement-stat-card">
                                            <div class="achievement-stat-label">Status</div>
                                            <div class="achievement-stat-value achievement-stat-value-soft">
                                                {{ achievementStatusLabel }}
                                            </div>
                                            <div class="achievement-stat-caption">{{ achievementStatusHint }}</div>
                                        </div>
                                        <div v-if="showAchievementFinalScore" class="achievement-stat-card">
                                            <div class="achievement-stat-label">Nilai Akhir</div>
                                            <div class="achievement-stat-value achievement-stat-value-soft">
                                                {{ achievementScoreLabel }}
                                            </div>
                                            <div class="achievement-stat-caption">
                                                {{ achievementScoreCaption }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="achievement-summary">
                                        <div class="achievement-summary-item">
                                            <i class="bi bi-mortarboard-fill text-primary"></i>
                                            <span>{{ materiTitle }}</span>
                                        </div>
                                        <div class="achievement-summary-item">
                                            <i class="bi bi-check2-circle text-success"></i>
                                            <span>{{ achievementCompletionLabel }}</span>
                                        </div>
                                        <div class="achievement-summary-item">
                                            <i class="bi bi-hourglass-split text-warning"></i>
                                            <span>{{ achievementTimingLabel }}</span>
                                        </div>
                                    </div>

                                    <div class="achievement-note">
                                        Klik <strong>Tutup</strong> untuk kembali membaca detail training atau meninjau
                                        ulang materi yang sudah Anda selesaikan.
                                    </div>
                                </div>
                            </template>
                            <template #footer>
                                <div class="achievement-footer-actions">
                                    <button class="btn btn-light rounded-pill px-4" @click="closeAchievementModal">
                                        <i class="bi bi-x-lg me-2"></i>Tutup
                                    </button>
                                    <button class="btn btn-success rounded-pill px-4" @click="reviewTrainingMaterial">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Lihat ulang materi
                                    </button>
                                </div>
                            </template>
                        </Modal>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import Modal from '../../../../components/ui/modal.vue';
import TrainingDetailHeader from './components/TrainingDetailHeader.vue';
import TrainingDetailSectionRenderer from './components/TrainingDetailSectionRenderer.vue';
import axios from 'axios';
import { DotLottieVue } from '@lottiefiles/dotlottie-vue';
import { ElMessage } from 'element-plus';

export default {
    name: 'TrainingDetailPage',
    components: {
        HeaderKpi,
        Modal,
        TrainingDetailHeader,
        TrainingDetailSectionRenderer,
        DotLottieVue,
        ElMessage,
    },
    props: {
        id: {
            type: [String, Number],
            required: false,
        },
        courseId: {
            type: [String, Number],
            required: false,
        },
        penjadwalanId: {
            type: [String, Number],
            default: null,
        },
        viewMode: {
            type: String,
            default: 'user',
        },
        targetKodeKaryawan: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            loading: true,
            isNavigating: false,
            errorMsg: '',
            materi: null,
            employeeName: null,
            attendanceInsight: null,
            trainingResult: null,
            attendanceCollapsed: true,
            sections: [],
            showTrainingInfoModal: false,
            showAchievementModal: false,
            achievementDismissedKey: null,
            // Track completion status per item: { [id_materi_det]: 'not-started' | 'in-progress' | 'passed' | 'done' }
            itemStatuses: {},
            collapsedSections: {},
            now: new Date(),
        };
    },
    computed: {
        isAdminMode() {
            return this.viewMode === 'admin';
        },
        normalizedMateri() {
            if (!this.materi) return null;

            const pick = (...keys) => {
                for (const key of keys) {
                    const value = this.materi?.[key];
                    if (value !== undefined && value !== null && value !== '') {
                        return value;
                    }
                }
                return null;
            };

            return {
                id: pick('id', 'Id_Training', 'id_training'),
                title: pick('judul', 'Judul_Training', 'judul_training'),
                description: pick('deskripsi', 'Deskripsi', 'deskripsi_training'),
                image: pick('thumbnail', 'Thumbnail_Training', 'thumbnail_training'),
                dateStart: pick('tanggal', 'Tanggal', 'tanggal_mulai'),
            };
        },
        materiTitle() {
            return this.normalizedMateri?.title || 'Training Detail';
        },
        materiId() {
            // Determine the materi ID from URL or props
            const urlParams = new URLSearchParams(window.location.search);
            let activeId = urlParams.get('id') || this.courseId || this.id || 1;
            // const match =
            //     window.location.pathname.match(/\/lms\/admin\/monitoring\/training\/(\d+)/) ||
            //     window.location.pathname.match(/\/lms\/training\/(\d+)/) ||
            //     window.location.pathname.match(/\/training-catalog\/(\d+)/) ||
            //     window.location.pathname.match(/\/training\/detail\/(\d+)/);

            // console.log('materiId URL match:', match);
            // if (match) {
            //     activeId = match[1];
            // }
            return activeId;
        },
        activePenjadwalanId() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('penjadwalan_id') || this.penjadwalanId || null;
        },
        headerData() {
            if (!this.normalizedMateri) return {};
            const totalItems = Object.keys(this.itemStatuses).length;
            const completedItems = Object.values(this.itemStatuses).filter((status) =>
                ['done', 'passed'].includes(status),
            ).length;
            const progress = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;

            return {
                id: this.normalizedMateri.id,
                title: this.normalizedMateri.title,
                description: this.normalizedMateri.description || '',
                image: this.normalizedMateri.image || '',
                dateStart: this.normalizedMateri.dateStart || '',
                category: '',
                progress,
                isCompleted: totalItems > 0 && completedItems === totalItems,
                coordinators: [],
                participants: [],
            };
        },
        trainingInfo() {
            if (!this.materi) {
                return {
                    outlineSession: [],
                    metode: [],
                    durasi: [],
                    totalDurasi: null,
                    sasaranPeserta: [],
                    standarKelulusan: null,
                };
            }

            const pick = (...keys) => {
                for (const key of keys) {
                    const value = this.materi?.[key];
                    if (value !== undefined && value !== null && value !== '') {
                        return value;
                    }
                }
                return null;
            };

            const toArray = (value) => {
                if (Array.isArray(value)) return value.filter(Boolean);
                if (typeof value === 'string') {
                    const trimmed = value.trim();
                    if (!trimmed) return [];

                    try {
                        const parsed = JSON.parse(trimmed);
                        if (Array.isArray(parsed)) {
                            return parsed
                                .filter(Boolean)
                                .map((entry) => String(entry).trim())
                                .filter(Boolean);
                        }
                    } catch (error) {
                        // Fallback to delimiter parser
                    }

                    return trimmed
                        .split(/\r?\n|;|,/)
                        .map((entry) => entry.trim())
                        .filter(Boolean);
                }
                return [];
            };

            return {
                outlineSession: toArray(pick('outline_session', 'Outline_Session')),
                metode: toArray(pick('metode', 'Metode')),
                durasi: toArray(pick('durasi', 'Durasi')),
                totalDurasi: pick('total_durasi', 'Total_Durasi'),
                sasaranPeserta: toArray(pick('sasaran_peserta', 'Sasaran_Peserta')),
                standarKelulusan: pick('standar_kelulusan', 'Standar_Kelulusan'),
            };
        },
        shortDescription() {
            const description = this.materi?.deskripsi || this.materi?.Deskripsi || '';
            if (description.length <= 150) {
                return description;
            }
            return `${description.slice(0, 150)}...`;
        },
        pageTitle() {
            return 'Training';
        },
        pageSubtitle() {
            return 'Detail materi pelatihan yang Anda ikuti.';
        },
        dayNight() {
            const h = this.now.getHours();
            return h >= 6 && h < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            const d = this.now;
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];
            return `${days[d.getDay()]}, ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
        },
        attendanceRecords() {
            return this.attendanceInsight?.records || [];
        },
        showAttendanceMetrics() {
            return Boolean(this.attendanceInsight?.has_full_attendance_pair);
        },
        completionProgress() {
            const totalItems = Object.keys(this.itemStatuses).length;
            const completedItems = Object.values(this.itemStatuses).filter((status) =>
                ['done', 'passed'].includes(status),
            ).length;

            return totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
        },
        isTrainingCompleted() {
            return (
                this.completionProgress >= 100 ||
                this.headerData?.isCompleted === true ||
                this.trainingResult?.kelulusan_status === 'passed'
            );
        },
        achievementTitle() {
            return this.trainingResult?.kelulusan_status === 'passed'
                ? 'Anda berhasil menyelesaikan training dengan baik'
                : 'Semua aktivitas telah dituntaskan';
        },
        achievementMessage() {
            const name = this.employeeName || 'Anda';
            return `${name} telah menuntaskan seluruh rangkaian materi. Ini adalah momen untuk merayakan pencapaian yang sudah diraih.`;
        },
        achievementStatusLabel() {
            if (this.trainingResult?.kelulusan_status === 'passed') {
                return 'Lulus';
            }

            if (this.trainingResult?.kelulusan_status === 'failed') {
                return 'Selesai';
            }

            return 'Completed';
        },
        achievementStatusHint() {
            if (this.trainingResult?.kelulusan_status === 'passed') {
                return 'Pencapaian final telah tercatat';
            }

            if (this.trainingResult?.kelulusan_status === 'failed') {
                return 'Training selesai, silakan cek detail hasil';
            }

            return 'Progress telah mencapai 100%';
        },
        hasPostTestActivity() {
            return this.sections.some((section) => {
                if (!Array.isArray(section?.items)) {
                    return false;
                }

                return section.items.some((item) => {
                    const activityCode = String(item?.kode_aktivitas || '')
                        .trim()
                        .toUpperCase();
                    return ['PTT', 'POST_TEST', 'POSTTEST'].includes(activityCode);
                });
            });
        },
        showAchievementFinalScore() {
            // Nilai akhir tidak lagi identik dengan "punya post test": training yang
            // hanya ber-pre-test pun punya nilai akhir, diambil dari pre-test-nya.
            // Yang menentukan tampil atau tidak adalah ADA-nya angka itu sendiri.
            const nilai = this.trainingResult?.total_score;
            return nilai !== null && nilai !== undefined && nilai !== '';
        },
        achievementScoreCaption() {
            // Menyebut asal angkanya, supaya peserta training ber-pre-test-saja
            // tidak mengira nilainya datang dari post test yang tak pernah ada.
            return this.trainingResult?.sumber_nilai === 'PRE_TEST'
                ? 'Diambil dari nilai pre test'
                : 'Diambil dari nilai post test';
        },
        achievementScoreLabel() {
            const score = this.trainingResult?.total_score_display || this.trainingResult?.total_score;
            return score !== null && score !== undefined && score !== '' ? score : '-';
        },
        achievementCompletionLabel() {
            return `${Object.values(this.itemStatuses).filter((status) => ['done', 'passed'].includes(status)).length} aktivitas selesai`;
        },
        achievementTimingLabel() {
            return this.formattedDate;
        },
        achievementConfetti() {
            return Array.from({ length: 18 }, (_, idx) => idx + 1);
        },
    },
    async created() {
        await this.fetchMateri();
    },
    mounted() {
        this._clockTimer = setInterval(() => {
            this.now = new Date();
        }, 60000);

        // this.achievementDismissedKey = `lms-training-achievement-dismissed-${this.materiId}`;
    },
    beforeUnmount() {
        clearInterval(this._clockTimer);
    },
    methods: {
        async fetchMateri() {
            this.loading = true;
            this.errorMsg = '';
            try {
                let url;
                if (this.isAdminMode && this.targetKodeKaryawan) {
                    url = this.activePenjadwalanId
                        ? `/lms/admin/monitoring/training/schedule/${this.activePenjadwalanId}/${this.targetKodeKaryawan}/progress`
                        : `/lms/admin/monitoring/training/${this.materiId}/${this.targetKodeKaryawan}/progress`;
                } else {
                    url = `/api/v1/lms/materi/${this.materiId}`;
                    if (this.activePenjadwalanId) {
                        url += `?penjadwalan_id=${encodeURIComponent(this.activePenjadwalanId)}`;
                    }
                }
                const { data } = await axios.get(url);
                if (data.success) {
                    const result = data.result || data;
                    this.materi = result.materi;
                    if (this.isAdminMode) {
                        this.employeeName = result.employee_name || null;
                        this.attendanceInsight = result.attendance_insight || null;
                    }
                    this.trainingResult = result.training_result || null;
                    this.sections = this.processSections(result.sections);

                    this.sections.forEach((section) => {
                        section.items.forEach((item) => {
                            // Use backend schedule status only, so UI remains deterministic during debugging.
                            const backendStatus = item.status_penjadwalan;
                            let status = 'not-started';
                            if (backendStatus && backendStatus !== 'not-started') {
                                status = backendStatus;
                            }
                            this.itemStatuses[item.id_materi_det] = status;
                        });
                    });

                    this.$nextTick(() => {
                        this.maybeShowAchievementModal();
                    });
                } else {
                    this.errorMsg = data.message || 'Gagal memuat data materi.';
                }
            } catch (err) {
                console.error('Failed to fetch materi:', err);
                this.errorMsg = 'Gagal memuat data materi. Silahkan coba lagi.';
            } finally {
                this.loading = false;

                this.$nextTick(() => {
                    const urlParams = new URLSearchParams(window.location.search);
                    const scrollToId = urlParams.get('scrollTo');
                    if (scrollToId) {
                        // Expand the section that contains the item automatically
                        this.sections.forEach((sec, idx) => {
                            const hasItem = sec.items.some((i) => i.id_materi_det == scrollToId);
                            if (hasItem) {
                                this.collapsedSections[idx] = false;
                            }
                        });

                        // Wait a tiny bit for Vue to render the expanded section, then scroll
                        setTimeout(() => {
                            const element =
                                document.getElementById('item-' + scrollToId) ||
                                document.getElementById('item-mdl-' + scrollToId);
                            if (element) {
                                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }, 200);
                    }
                });
            }
        },

        maybeShowAchievementModal() {
            if (this.isAdminMode || !this.isTrainingCompleted) {
                return;
            }

            if (this.achievementDismissedKey && sessionStorage.getItem(this.achievementDismissedKey) === '1') {
                return;
            }

            this.showAchievementModal = true;

            this.$nextTick(() => {
                this.playAchievementSound();
            });
        },

        closeAchievementModal() {
            if (this.achievementDismissedKey) {
                sessionStorage.setItem(this.achievementDismissedKey, '1');
            }

            this.showAchievementModal = false;
        },

        reviewTrainingMaterial() {
            this.closeAchievementModal();

            this.$nextTick(() => {
                const target =
                    this.$el?.querySelector('.training-info-summary') || this.$el?.querySelector('.sub-section-header');
                if (target?.scrollIntoView) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },

        playAchievementSound() {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;

                const audioContext = new AudioContextClass();
                const gainNode = audioContext.createGain();
                gainNode.gain.value = 0.02;
                gainNode.connect(audioContext.destination);

                const notes = [523.25, 659.25, 783.99];
                let startAt = audioContext.currentTime + 0.02;

                notes.forEach((frequency, index) => {
                    const oscillator = audioContext.createOscillator();
                    oscillator.type = index === 2 ? 'triangle' : 'sine';
                    oscillator.frequency.value = frequency;
                    oscillator.connect(gainNode);
                    oscillator.start(startAt);
                    oscillator.stop(startAt + 0.18);
                    startAt += 0.12;
                });

                window.setTimeout(() => {
                    if (audioContext.state !== 'closed') {
                        audioContext.close().catch(() => {});
                    }
                }, 1200);
            } catch (error) {
                console.debug('Achievement sound blocked or unavailable:', error);
            }
        },

        /**
         * Process raw sections from API into renderable groups.
         * Groups consecutive MDL items together, and determines section type
         * based on kode_aktivitas or konten tipe.
         */
        processSections(rawSections) {
            return rawSections
                .map((section) => {
                    const renderedItems = [];
                    let learningBuffer = [];
                    let orderIndex = 0;

                    const flushLearningBuffer = () => {
                        if (learningBuffer.length > 0) {
                            renderedItems.push({
                                _renderKey: `mdl-group-${learningBuffer[0].id_materi_det}`,
                                _sectionType: 'MDL',
                                _singleItem: null,
                                _materials: [...learningBuffer],
                                _orderIndex: orderIndex++,
                            });
                            learningBuffer = [];
                        }
                    };

                    section.items.forEach((item) => {
                        const kode = item.kode_aktivitas;

                        if (kode === 'ABS') {
                            return;
                        }

                        const isStandaloneBlock = ['PRT', 'PTT', 'ELS'].includes(kode);

                        if (isStandaloneBlock) {
                            flushLearningBuffer();
                            renderedItems.push({
                                _renderKey: `item-${item.id_materi_det}`,
                                _sectionType: kode,
                                _singleItem: item,
                                _materials: [],
                                _orderIndex: orderIndex++,
                            });
                            return;
                        }

                        learningBuffer.push(item);
                    });

                    flushLearningBuffer();

                    return {
                        ...section,
                        renderedItems,
                    };
                })
                .filter((section) => section.renderedItems.length > 0);
        },

        isItemLocked(sectionIdx, orderIndex) {
            const section = this.sections[sectionIdx];
            if (!section) return false;

            const renderedItem = section.renderedItems.find((ri) => ri._orderIndex === orderIndex);
            if (!renderedItem) return false;

            const prerequisiteIds = this.getRenderedItemPrerequisites(renderedItem);
            if (!prerequisiteIds.length) {
                return false;
            }

            return prerequisiteIds.some((prerequisiteId) => {
                const prerequisiteStatus = this.itemStatuses[prerequisiteId];
                if (prerequisiteStatus === undefined || prerequisiteStatus === null) {
                    return false;
                }
                return prerequisiteStatus !== 'done' && prerequisiteStatus !== 'passed';
            });
        },

        getRenderedItemPrerequisites(renderedItem) {
            if (!renderedItem) return [];

            if (renderedItem._sectionType === 'MDL') {
                return renderedItem._materials
                    .map((material) => material.id_materi_det_syarat)
                    .filter((value) => value !== null && value !== undefined);
            }

            return [renderedItem._singleItem?.id_materi_det_syarat].filter(
                (value) => value !== null && value !== undefined,
            );
        },

        getItemStatus(id) {
            return this.itemStatuses[id] || 'not-started';
        },

        toggleSection(idx) {
            this.collapsedSections = {
                ...this.collapsedSections,
                [idx]: !this.collapsedSections[idx],
            };
        },

        isSectionCompleted(sectionIdx) {
            const section = this.sections[sectionIdx];
            if (!section?.items?.length) return false;

            return section.items.every((item) => {
                const status = this.itemStatuses[item.id_materi_det];
                return status === 'done' || status === 'passed';
            });
        },
        previewList(items, limit = 2) {
            if (!Array.isArray(items) || !items.length) {
                return '-';
            }

            const shown = items.slice(0, limit).join(', ');
            const remain = items.length - limit;
            return remain > 0 ? `${shown} (+${remain} lainnya)` : shown;
        },
        formatTotalDurasi(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            return `${value} menit`;
        },
        formatDateTime(value) {
            if (!value) {
                return '-';
            }

            const date = new Date(value);
            if (isNaN(date)) {
                return value;
            }

            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        getAttendanceStatusIcon(status) {
            if (status === 'completed') return 'bi-check-circle-fill';
            if (status === 'partial') return 'bi-hourglass-split';
            if (status === 'not-started') return 'bi-dash-circle';
            return 'bi-info-circle';
        },
        buildMapsUrl(latitude, longitude) {
            return `https://www.google.com/maps?q=${encodeURIComponent(latitude)},${encodeURIComponent(longitude)}`;
        },
        buildViewerUrl(materialId) {
            const baseUrl = `/api/v1/lms/materi/${this.materiId}/viewer/${materialId}`;
            const params = new URLSearchParams();

            if (this.activePenjadwalanId) {
                params.set('penjadwalan_id', this.activePenjadwalanId);
            }

            if (!this.isAdminMode) {
                const query = params.toString();
                return query ? `${baseUrl}?${query}` : baseUrl;
            }

            params.set('mode', 'admin');
            if (this.targetKodeKaryawan) {
                params.set('target_kode_karyawan', this.targetKodeKaryawan);
            }
            params.set('return_to', window.location.pathname + window.location.search);

            return `${baseUrl}?${params.toString()}`;
        },

        // --- Event Handlers ---
        async recordActivity(item, options = {}) {
            if (this.isAdminMode) {
                return;
            }

            if (!item?.id_penjadwalan || !item?.id_penjadwalan_detail || !item?.id_materi || !item?.id_materi_det) {
                return;
            }

            try {
                await axios.post('/api/v1/lms/absensi/training/activity/record', {
                    Id_Penjadwalan: item.id_penjadwalan,
                    Id_Penjadwalan_Detail: item.id_penjadwalan_detail,
                    Id_Penjadwalan_Det: item.id_penjadwalan_det || null,
                    Id_Materi: item.id_materi,
                    Id_Materi_Det: item.id_materi_det,
                    Urutan: item.urutan_materi || null,
                    Flag_Third_Party: item.aktivitas_third_party || 'T',
                    Status_Progress: options.statusProgress || 'PROGRESS',
                    MarkStarted: options.markStarted ?? true,
                    MarkCompleted: options.markCompleted ?? false,
                });
            } catch (error) {
                console.error('Failed to record LMS activity progress:', error);
            }
        },

        async handleViewTestDetail(idPenjadwalanDet) {
            if (this.isAdminMode && idPenjadwalanDet) {
                this.$inertia.visit(`/lms/admin/monitoring/test-detail/${idPenjadwalanDet}`);
            }
        },

        async handleTestComplete(id, sectionType, item) {
            if (this.isAdminMode) {
                ElMessage.info('Mode admin hanya untuk monitoring. Aktivitas tidak direkam.');
                return;
            }

            const opened = this.openAssessmentLink(item);
            if (!opened) {
                ElMessage.warning('Popup diblokir browser. Izinkan pop-up lalu klik ulang untuk membuka ujian.');
            }

            await this.recordActivity(item, {
                statusProgress: 'PROGRESS',
                markStarted: true,
                markCompleted: false,
            });
            this.itemStatuses = { ...this.itemStatuses, [id]: 'in-progress' };
            const label = sectionType === 'PRT' ? 'Pre-test' : 'Post-test';
            ElMessage.success(`${label} dimulai.`);
        },

        async handleMaterialView(payload) {
            const materialId = payload?.id || payload;
            this.isNavigating = true;

            if (this.isAdminMode) {
                this.$inertia.visit(this.buildViewerUrl(materialId));
                return;
            }

            const materialItem = this.sections
                .flatMap((section) => section.items || [])
                .find((item) => item.id_materi_det === materialId);

            if (materialItem) {
                await this.recordActivity(materialItem, {
                    statusProgress: 'SELESAI',
                    markStarted: true,
                    markCompleted: true,
                });
                this.itemStatuses = { ...this.itemStatuses, [materialId]: 'done' };
            }

            this.$inertia.visit(this.buildViewerUrl(materialId));
        },

        async handleVideoComplete(payload) {
            const id = payload?.id;
            const item = payload?.item;

            if (!id || !item) {
                return;
            }

            this.isNavigating = true;

            if (this.isAdminMode) {
                this.$inertia.visit(this.buildViewerUrl(id));
                return;
            }

            await this.recordActivity(item, {
                statusProgress: 'PROGRESS',
                markStarted: true,
                markCompleted: false,
            });
            this.$inertia.visit(this.buildViewerUrl(id));
        },

        async handleQuizComplete(payload) {
            const id = payload?.id;
            const item = payload?.item;

            if (!id || !item) {
                return;
            }

            if (this.isAdminMode) {
                ElMessage.info('Mode admin hanya untuk monitoring. Aktivitas tidak direkam.');
                return;
            }

            if ((item?.aktivitas_third_party || 'T') === 'Y' || item?.link_ujian) {
                const opened = this.openAssessmentLink(item);
                if (!opened) {
                    ElMessage.warning('Popup diblokir browser. Izinkan pop-up lalu klik ulang untuk membuka quiz.');
                }

                await this.recordActivity(item, {
                    statusProgress: 'PROGRESS',
                    markStarted: true,
                    markCompleted: false,
                });
                this.itemStatuses = { ...this.itemStatuses, [id]: 'in-progress' };
                ElMessage.success('Quiz dimulai.');
                return;
            }

            await this.recordActivity(item, {
                statusProgress: 'SELESAI',
                markStarted: true,
                markCompleted: true,
            });
            this.itemStatuses = { ...this.itemStatuses, [id]: 'passed' };
        },

        // openAssessmentLink(item) {
        //     const rawLinkUjian = item?.link_ujian || item?.konten?.link_ujian || null;
        //     const linkUjian = this.normalizeAssessmentUrl(rawLinkUjian);

        //     if (!linkUjian) {
        //         ElMessage.info('Link ujian belum tersedia untuk aktivitas ini.');
        //         return false;
        //     }

        //     let countdown = 3;

        //     // tampilkan pertama kali
        //     ElMessage.success(`Akan dialihkan dalam ${countdown} detik...`);

        //     const interval = setInterval(() => {
        //         countdown--;

        //         if (countdown > 0) {
        //             ElMessage.success(`Akan dialihkan dalam ${countdown} detik...`);
        //         } else {
        //             clearInterval(interval);
        //             const popup = window.open(linkUjian, '_blank', 'noopener,noreferrer');
        //             if (!popup) {
        //                 ElMessage.info('Popup diblokir browser. Izinkan pop-up untuk membuka ujian di tab baru.');
        //                 return false;
        //             }
        //         }
        //     }, 1000);

        //     return true;
        // },

        openAssessmentLink(item) {
            const rawLinkUjian = item?.link_ujian || item?.konten?.link_ujian || null;
            const linkUjian = this.normalizeAssessmentUrl(rawLinkUjian);

            console.log('Opening assessment link:', { rawLinkUjian, linkUjian });
            if (!linkUjian) {
                ElMessage.error('Link ujian belum tersedia untuk aktivitas ini.');
                return false;
            }

            let countdown = 3;

            // tampilkan pertama kali
            ElMessage.success(`Akan dialihkan dalam ${countdown} detik...`);

            const interval = setInterval(() => {
                countdown--;

                if (countdown > 0) {
                    ElMessage.success(`Akan dialihkan dalam ${countdown} detik...`);
                } else {
                    clearInterval(interval);
                    ElMessage.success('Mengalihkan ke halaman ujian...');
                    window.location.href = linkUjian;
                }
            }, 1000);

            return true;
        },

        normalizeAssessmentUrl(url) {
            if (!url || typeof url !== 'string') {
                return null;
            }

            const trimmed = url.trim();
            if (!trimmed) {
                return null;
            }

            if (/^https?:\/\//i.test(trimmed)) {
                return trimmed;
            }

            if (trimmed.startsWith('/')) {
                return `${window.location.origin}${trimmed}`;
            }

            return `https://${trimmed}`;
        },

        async handleEvalComplete(id, item) {
            if (this.isAdminMode) {
                ElMessage.info('Mode admin hanya untuk monitoring. Aktivitas tidak direkam.');
                return;
            }

            await this.recordActivity(item, {
                statusProgress: 'SELESAI',
                markStarted: true,
                markCompleted: true,
            });
            this.itemStatuses = { ...this.itemStatuses, [id]: 'passed' };
            ElMessage.success('Evaluation completed and submitted.');
        },
    },
};
</script>

<style scoped>
.cuti-app-container {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-inset: inset 0 0px 0.3rem -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-lg-inset: inset 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);

    min-height: 100vh;
    background: var(--surface-soft);
    font-size: 0.9rem;
    color: var(--text);
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

/* ---- App shell ---- */
/* .main-content-wrapper {
    padding: 1.5rem 1rem;
} */

.bi {
    display: inline-table !important;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.detail-surface {
    --detail-primary: var(--primary);
    --detail-primary-dark: var(--primary-dark);
    --detail-primary-soft: var(--hcis-indigo-50);
    --detail-danger: var(--danger);
    --detail-warning: var(--warning);
    background: var(--hcis-white); /* Putih bersih */
    border: 1px solid rgba(0, 0, 0, 0.05); /* Outline sangat tipis */
    border-radius: 20px; /* Sudut lebih membulat ala trainingViewer */
    box-shadow:
        0 10px 25px -5px rgba(0, 0, 0, 0.02),
        0 8px 10px -6px rgba(0, 0, 0, 0.01);
}

/* ---- Pass CSS variables into child components ---- */
.detail-surface :deep(.progress-bar) {
    background: var(--primary) !important;
}

.detail-surface :deep(.pag-num.active) {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--surface);
}

.detail-surface :deep(.vq-chevron),
.detail-surface :deep(.bc-link) {
    color: var(--primary);
}

.detail-surface :deep(.detail-type-badge) {
    color: var(--primary);
    border-color: var(--primary);
}

.detail-surface :deep(.meta-view-more) {
    color: var(--primary);
}

.detail-surface :deep(.avatar-item) {
    background: var(--primary);
}

.detail-surface :deep(.vq-quiz-icon),
.detail-surface :deep(.detail-cover-placeholder),
.detail-surface :deep(.class-thumb) {
    background: var(--detail-primary-soft);
    color: var(--primary);
}

/* ---- Breadcrumb ---- */
.detail-breadcrumb {
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    background: transparent;
    /* padding: 0 0.5rem 0.5rem 0; */
    margin-bottom: 1rem !important;
}

.bc-link {
    color: var(--secondary);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.bc-link:hover {
    color: var(--primary);
}

.bc-sep {
    color: var(--hcis-slate-300);
    font-weight: 400;
}

.bc-current {
    color: var(--primary);
    font-weight: 600;
}

/* ---- Sub-section header (Redesigned as clean headline) ---- */
.sub-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: transparent;
    border: none;
    border-bottom: 2px solid var(--hcis-slate-100);
    border-radius: 0;
    padding: 0.5rem 0.25rem 1rem 0.25rem;
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}

.sub-section-header:hover {
    border-bottom-color: var(--hcis-slate-300);
}

.sub-section-status.is-complete {
    display: inline-flex;
    align-items: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-green-700);
    background: var(--hcis-green-50);
    border: 1px solid var(--hcis-green-200);
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
}

.sub-section-title {
    font-size: 1.15rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--hcis-slate-800);
}

.sub-section-count {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    background-color: var(--surface-soft);
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid var(--border);
}

.training-info-summary {
    border: 1px solid #dbeafe;
    border-radius: 16px;
    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 55%, #fdf7ff 100%);
    padding: 1rem 1.1rem;
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.08);
}

.training-info-head {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 0.7rem;
}

.training-info-eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.training-info-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.training-info-description {
    font-size: 0.83rem;
    line-height: 1.55;
    color: var(--hcis-slate-700);
}

.training-info-table {
    display: grid;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    background: var(--hcis-white);
    overflow: hidden;
}

.training-info-row {
    display: grid;
    grid-template-columns: minmax(130px, 170px) 1fr;
    border-bottom: 1px solid var(--hcis-indigo-50);
}

.training-info-row:last-child {
    border-bottom: none;
}

.training-info-key {
    padding: 0.58rem 0.75rem;
    font-size: 0.77rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    background: #f8fbff;
    border-right: 1px solid var(--hcis-indigo-50);
}

.training-info-value {
    padding: 0.58rem 0.75rem;
    font-size: 0.79rem;
    color: var(--hcis-slate-800);
    line-height: 1.45;
}

.training-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.summary-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: var(--hcis-white);
    border: 1px solid #dbeafe;
    padding: 0.26rem 0.68rem;
    font-size: 0.73rem;
    font-weight: 700;
    color: #1e3a8a;
}

.training-info-note {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    line-height: 1.45;
}

.attendance-summary-section {
    border: 1px solid #dbeafe;
    border-radius: 16px;
    background: var(--hcis-white);
    padding: 1rem 1.1rem;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.08);
}

.attendance-summary-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}

.attendance-eyebrow {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.attendance-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.attendance-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.28rem 0.72rem;
    font-size: 0.73rem;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
}

.attendance-status-pill.status-completed {
    background: var(--hcis-emerald-50);
    color: var(--hcis-green-700);
    border-color: var(--hcis-green-200);
}

.attendance-status-pill.status-partial {
    background: #fff7ed;
    color: #c2410c;
    border-color: var(--hcis-orange-300);
}

.attendance-status-pill.status-not-started {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border-color: var(--hcis-slate-200);
}

.attendance-status-pill.status-not-required {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-color: var(--hcis-indigo-200);
}

.attendance-collapse-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 999px;
    padding: 0.28rem 0.68rem;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    transition: all 0.2s ease;
}

.attendance-collapse-btn:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e40af;
}

.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.2s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.attendance-metric-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
}

.attendance-metric-card {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 0.6rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.metric-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.metric-value {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
}

.attendance-audit-grid {
    display: grid;
    gap: 0.75rem;
}

.attendance-audit-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-white);
    padding: 0.75rem;
}

.attendance-audit-head {
    display: flex;
    justify-content: space-between;
    gap: 0.6rem;
    align-items: flex-start;
    margin-bottom: 0.7rem;
}

.attendance-audit-body {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 0.8rem;
}

.attendance-evidence-photo-wrap {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    overflow: hidden;
    background: var(--hcis-slate-50);
    min-height: 132px;
}

.attendance-evidence-photo-link {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    text-decoration: none;
}

.attendance-evidence-photo {
    width: 100%;
    height: 132px;
    object-fit: cover;
    display: block;
}

.attendance-photo-badge {
    position: absolute;
    right: 0.4rem;
    bottom: 0.4rem;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    border-radius: 999px;
    padding: 0.15rem 0.45rem;
    font-size: 0.67rem;
    font-weight: 700;
}

.attendance-evidence-photo-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    height: 132px;
    color: var(--hcis-slate-400);
    font-size: 0.75rem;
}

.attendance-evidence-photo-empty i {
    font-size: 1.3rem;
}

.attendance-evidence-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    align-content: start;
}

.attendance-meta-item {
    border: 1px solid var(--hcis-indigo-50);
    background: #f8fbff;
    border-radius: 10px;
    padding: 0.45rem 0.55rem;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.meta-key {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.meta-value {
    font-size: 0.78rem;
    color: var(--hcis-slate-800);
    line-height: 1.35;
    font-weight: 600;
}

.attendance-row-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    padding: 0.18rem 0.52rem;
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.attendance-row-pill.is-checked-in {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.attendance-row-pill.is-not-checked-in {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border-color: var(--hcis-slate-200);
}

.attendance-map-link {
    display: inline-flex;
    align-items: center;
    margin-top: 0.1rem;
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--hcis-blue-600);
    text-decoration: none;
}

.attendance-map-link:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.attendance-empty {
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 12px;
    padding: 0.8rem;
    font-size: 0.82rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
}

.training-modal-grid {
    display: grid;
    gap: 0.85rem;
}

.training-modal-table {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    overflow: hidden;
    background: var(--hcis-white);
}

.training-modal-row {
    display: grid;
    grid-template-columns: minmax(130px, 180px) 1fr;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.training-modal-row:last-child {
    border-bottom: none;
}

.training-modal-key {
    padding: 0.72rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    background: var(--hcis-slate-50);
    border-right: 1px solid var(--hcis-slate-200);
}

.training-modal-value {
    padding: 0.72rem 0.85rem;
    font-size: 0.8rem;
    color: var(--hcis-slate-800);
    line-height: 1.5;
}

.training-modal-block {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    border-radius: 12px;
    padding: 0.85rem 0.95rem;
}

.modal-block-title {
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin-bottom: 0.45rem;
}

.modal-list {
    margin: 0;
    padding-left: 1.05rem;
    color: var(--hcis-slate-700);
    font-size: 0.82rem;
    line-height: 1.45;
}

.modal-list.ordered {
    padding-left: 1.2rem;
}

.modal-empty {
    margin: 0;
    color: var(--hcis-slate-400);
    font-size: 0.8rem;
}

.modal-highlight {
    margin-top: 0.55rem;
    border-radius: 10px;
    background: #dbeafe;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    padding: 0.45rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.achievement-modal-body {
    padding: 0;
}

.achievement-shell {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    padding: 1.35rem;
    background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.78)),
        linear-gradient(135deg, rgba(59, 130, 246, 0.14), rgba(99, 102, 241, 0.12) 35%, rgba(16, 185, 129, 0.12));
    border: 1px solid rgba(255, 255, 255, 0.55);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.6),
        0 20px 50px rgba(15, 23, 42, 0.12);
}

.achievement-glow {
    position: absolute;
    width: 240px;
    height: 240px;
    border-radius: 999px;
    filter: blur(24px);
    opacity: 0.5;
    pointer-events: none;
}

.achievement-glow-left {
    top: -120px;
    left: -120px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.55), rgba(59, 130, 246, 0));
}

.achievement-glow-right {
    right: -120px;
    bottom: -120px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.5), rgba(16, 185, 129, 0));
}

.achievement-confetti {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

.achievement-confetti-piece {
    position: absolute;
    top: -18px;
    width: 10px;
    height: 18px;
    border-radius: 3px;
    opacity: 0.95;
    animation: confetti-fall 3.8s linear infinite;
    background: linear-gradient(180deg, #60a5fa, var(--hcis-violet-400));
}

.achievement-confetti-piece:nth-child(3n) {
    background: linear-gradient(180deg, var(--hcis-amber-500), #f97316);
}

.achievement-confetti-piece:nth-child(4n) {
    background: linear-gradient(180deg, var(--hcis-emerald-500), var(--hcis-emerald-400));
}

.achievement-confetti-piece:nth-child(5n) {
    background: linear-gradient(180deg, #f472b6, #fb7185);
}

.achievement-confetti-piece:nth-child(1) {
    left: 6%;
    animation-delay: 0s;
}
.achievement-confetti-piece:nth-child(2) {
    left: 14%;
    animation-delay: 0.45s;
}
.achievement-confetti-piece:nth-child(3) {
    left: 22%;
    animation-delay: 0.2s;
}
.achievement-confetti-piece:nth-child(4) {
    left: 30%;
    animation-delay: 0.7s;
}
.achievement-confetti-piece:nth-child(5) {
    left: 38%;
    animation-delay: 0.3s;
}
.achievement-confetti-piece:nth-child(6) {
    left: 46%;
    animation-delay: 0.9s;
}
.achievement-confetti-piece:nth-child(7) {
    left: 54%;
    animation-delay: 0.15s;
}
.achievement-confetti-piece:nth-child(8) {
    left: 62%;
    animation-delay: 0.6s;
}
.achievement-confetti-piece:nth-child(9) {
    left: 70%;
    animation-delay: 0.35s;
}
.achievement-confetti-piece:nth-child(10) {
    left: 78%;
    animation-delay: 1.05s;
}
.achievement-confetti-piece:nth-child(11) {
    left: 86%;
    animation-delay: 0.1s;
}
.achievement-confetti-piece:nth-child(12) {
    left: 94%;
    animation-delay: 0.8s;
}
.achievement-confetti-piece:nth-child(13) {
    left: 10%;
    animation-delay: 1.2s;
}
.achievement-confetti-piece:nth-child(14) {
    left: 26%;
    animation-delay: 1.45s;
}
.achievement-confetti-piece:nth-child(15) {
    left: 42%;
    animation-delay: 1.7s;
}
.achievement-confetti-piece:nth-child(16) {
    left: 58%;
    animation-delay: 1.9s;
}
.achievement-confetti-piece:nth-child(17) {
    left: 74%;
    animation-delay: 1.35s;
}
.achievement-confetti-piece:nth-child(18) {
    left: 90%;
    animation-delay: 1.6s;
}

.achievement-hero {
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 1rem;
    align-items: center;
    z-index: 1;
}

.achievement-badge-ring {
    width: 96px;
    height: 96px;
    border-radius: 28px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(224, 231, 255, 0.88));
    box-shadow:
        0 18px 40px rgba(79, 70, 229, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.75);
}

.achievement-badge-core {
    width: 74px;
    height: 74px;
    border-radius: 22px;
    display: grid;
    place-items: center;
    color: var(--hcis-white);
    font-size: 2rem;
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-red-500) 55%, #8b5cf6);
    box-shadow: 0 12px 24px rgba(239, 68, 68, 0.25);
    animation: badge-bounce 2.7s ease-in-out infinite;
}

.achievement-artboard {
    position: relative;
    width: 132px;
    height: 132px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
}

.achievement-lottie {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0.95;
    filter: drop-shadow(0 14px 28px rgba(79, 70, 229, 0.2));
}

.achievement-hero-copy {
    min-width: 0;
    position: relative;
}

.achievement-artwork {
    position: absolute;
    inset: -0.15rem 0 auto auto;
    width: min(210px, 38vw);
    height: 110px;
    pointer-events: none;
    opacity: 0.9;
    overflow: hidden;
}

.achievement-artwork-ring,
.achievement-artwork-hearts {
    position: absolute;
    display: block;
    filter: drop-shadow(0 8px 18px rgba(79, 70, 229, 0.14));
}

.achievement-artwork-ring-left {
    top: -8px;
    left: 0;
    width: 46px;
    height: 46px;
    animation: float-soft 4.4s ease-in-out infinite;
}

.achievement-artwork-ring-right {
    right: 0;
    bottom: 2px;
    width: 36px;
    height: 36px;
    opacity: 0.82;
    animation: float-soft 5s ease-in-out infinite reverse;
}

.achievement-artwork-hearts {
    right: 18%;
    top: 14px;
    width: 84px;
    height: 38px;
    animation: float-soft 4.8s ease-in-out infinite;
}

.achievement-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.7);
    color: var(--hcis-indigo-700);
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 0.65rem;
}

.achievement-title {
    margin: 0;
    font-size: clamp(1.35rem, 2.5vw, 2.2rem);
    line-height: 1.1;
    font-weight: 900;
    color: var(--hcis-slate-900);
}

.achievement-copy {
    margin: 0.65rem 0 0;
    font-size: 0.95rem;
    color: var(--hcis-slate-700);
    line-height: 1.6;
    max-width: 52rem;
}

.achievement-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
    margin-top: 1rem;
}

.achievement-grid.achievement-grid-two-cols {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.achievement-stat-card {
    border-radius: 18px;
    padding: 0.95rem 1rem;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.achievement-stat-card-primary {
    background: linear-gradient(135deg, #1d4ed8, var(--hcis-indigo-600));
    color: var(--hcis-white);
    border-color: rgba(255, 255, 255, 0.12);
}

.achievement-stat-label {
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.8;
    margin-bottom: 0.45rem;
}

.achievement-stat-value {
    font-size: 1.35rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.03em;
}

.achievement-stat-value-soft {
    color: var(--hcis-slate-900);
}

.achievement-stat-card-primary .achievement-stat-value,
.achievement-stat-card-primary .achievement-stat-caption,
.achievement-stat-card-primary .achievement-stat-label {
    color: var(--hcis-white);
}

.achievement-stat-caption {
    margin-top: 0.4rem;
    font-size: 0.8rem;
    line-height: 1.45;
    color: var(--hcis-slate-500);
}

.achievement-summary {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    margin-top: 1rem;
}

.achievement-summary-item {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.8);
    color: var(--hcis-slate-800);
    font-size: 0.82rem;
    font-weight: 700;
}

.achievement-note {
    position: relative;
    z-index: 1;
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.82);
    color: var(--hcis-slate-700);
    border: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.88rem;
    line-height: 1.55;
}

.achievement-footer-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.75rem;
    width: 100%;
}

@keyframes confetti-fall {
    0% {
        transform: translateY(-20px) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    100% {
        transform: translateY(560px) rotate(720deg);
        opacity: 0;
    }
}

@keyframes badge-bounce {
    0%,
    100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-4px) scale(1.03);
    }
}

@keyframes float-soft {
    0%,
    100% {
        transform: translateY(0) translateX(0);
    }
    50% {
        transform: translateY(-6px) translateX(2px);
    }
}

@media (max-width: 767px) {
    .detail-surface {
        padding: 1rem !important;
    }

    .training-info-head {
        flex-direction: column;
        align-items: stretch;
    }

    .attendance-summary-head {
        flex-direction: column;
        align-items: stretch;
    }

    .attendance-metric-grid {
        grid-template-columns: 1fr;
    }

    .attendance-audit-head {
        flex-direction: column;
        align-items: stretch;
    }

    .attendance-audit-body {
        grid-template-columns: 1fr;
    }

    .attendance-evidence-meta {
        grid-template-columns: 1fr;
    }

    .training-info-row {
        grid-template-columns: 1fr;
    }

    .training-info-key {
        border-right: none;
        border-bottom: 1px solid var(--hcis-indigo-50);
    }

    .training-modal-row {
        grid-template-columns: 1fr;
    }

    .training-modal-key {
        border-right: none;
        border-bottom: 1px solid var(--hcis-slate-200);
    }

    .sub-section-header {
        margin-top: 1.6rem;
        padding-bottom: 0.75rem;
    }

    .achievement-shell {
        padding: 1rem;
    }

    .achievement-hero {
        grid-template-columns: 1fr;
        text-align: center;
        justify-items: center;
    }

    .achievement-artboard {
        width: 112px;
        height: 112px;
    }

    .achievement-artwork {
        inset: -0.1rem 50% auto auto;
        transform: translateX(50%);
        width: min(190px, 54vw);
    }

    .achievement-grid {
        grid-template-columns: 1fr;
    }

    .achievement-summary {
        justify-content: center;
    }

    .achievement-footer-actions {
        justify-content: stretch;
    }

    .achievement-footer-actions .btn {
        flex: 1 1 100%;
    }
}
</style>
