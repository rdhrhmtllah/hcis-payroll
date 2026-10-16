<template>
    <div>
        <transition name="fade">
            <div class="details-overlay" v-if="show && item" @click="$emit('close')"></div>
        </transition>

        <transition name="slide-panel">
            <div class="details-panel" v-if="show && item">
                <!-- Header -->
                <div class="panel-header">
                    <div class="pe-3">
                        <h3 class="fw-bold mb-2" style="font-size: 1.5rem; letter-spacing: -0.02em">Detail</h3>
                        <!-- <div class="d-flex gap-2 align-items-center">
                            <span class="badge-live">{{
                                item.status || "LIVE"
                            }}</span>
                            <span class="badge-version">Published v2.4</span>
                        </div> -->
                    </div>
                    <button
                        class="btn p-0 border-0"
                        @click="$emit('close')"
                        style="
                            width: 44px;
                            height: 44px;
                            background: #f8fafc;
                            border-radius: 14px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        "
                    >
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.5">
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body Content -->
                <div class="panel-body">
                    <div class="top-meta-chips" v-if="summaryChips.length">
                        <span v-for="chip in summaryChips" :key="chip" class="meta-chip">
                            {{ chip }}
                        </span>
                    </div>

                    <!-- Preview Area -->
                    <div class="panel-preview" :class="{ 'is-clickable': canOpenViewer }" @click="openViewer">
                        <img
                            v-if="item.previewUrl || item.thumbnailUrl"
                            :src="item.previewUrl || item.thumbnailUrl"
                            :alt="item.name"
                        />
                        <div v-else-if="item.type === 'folder'" style="transform: scale(1.5)">
                            <svg width="100" height="80" viewBox="0 0 140 110" fill="none">
                                <path
                                    d="M125 105H15C9.47715 105 5 100.523 5 95V35C5 29.4772 9.47715 25 15 25H45L55 10H125C130.523 10 135 14.4772 135 20V95C135 100.523 130.523 105 125 105Z"
                                    fill="#dce6f2"
                                />
                                <path
                                    d="M125 105H15C9.47715 105 5 100.523 5 95V45C5 39.4772 9.47715 35 15 35H125C130.523 35 135 39.4772 135 45V95C135 100.523 130.523 105 125 105Z"
                                    fill="#e8eff7"
                                />
                            </svg>
                        </div>
                        <div v-if="item.type === 'video'" class="play-overlay-mini">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#94a3b8">
                                <path d="M8 5V19L19 12L8 5Z" />
                            </svg>
                        </div>
                        <div v-if="canOpenViewer" class="preview-hint">Klik untuk lihat media</div>
                        <div v-else-if="hanyaUnduh" class="preview-hint is-muted">Hanya bisa diunduh</div>
                    </div>

                    <!-- Judul Item -->
                    <div class="mb-4">
                        <h4 class="fw-bold mb-1" style="font-size: 1.25rem">
                            {{ item.name }}
                        </h4>
                        <p class="text-muted small fw-bold mb-0 text-uppercase">
                            {{ contentTypeLabel }}
                        </p>
                    </div>

                    <!--
                        Status verifikasi berkas — mendahului kotak render.
                        Selama berkas belum lolos verifikasi ia masih di karantina,
                        jadi bicara soal render pada saat itu menyesatkan.
                    -->
                    <div v-if="verifikasiTertunda" class="render-status-box mb-4" :class="`is-${verifikasiKelas}`">
                        <div class="render-status-info">
                            <i
                                class="bi"
                                :class="
                                    statusVerifikasi === 'DITOLAK' ? 'bi-shield-fill-exclamation' : 'bi-shield-check'
                                "
                            ></i>
                            <div>
                                <p class="render-status-title mb-0">{{ verifikasiJudul }}</p>
                                <small class="text-muted">{{ verifikasiKeterangan }}</small>
                            </div>
                        </div>
                        <button
                            v-if="statusVerifikasi !== 'DITOLAK'"
                            class="btn-render-ulang"
                            :disabled="isRetryingVerifikasi"
                            @click="verifikasiUlang"
                        >
                            <i class="bi bi-arrow-repeat" :class="{ 'spin-render': isRetryingVerifikasi }"></i>
                            {{ isRetryingVerifikasi ? 'Mengantrikan...' : 'Periksa Ulang' }}
                        </button>
                    </div>

                    <!--
                        Format tanpa penyaji. Ditampilkan sebagai keterangan, bukan
                        sebagai kegagalan: admin perlu tahu sejak awal bahwa konten
                        ini memang untuk diunduh, bukan mengira pratinjaunya rusak.
                    -->
                    <div v-if="hanyaUnduh && !verifikasiTertunda" class="render-status-box mb-4 is-unduh">
                        <div class="render-status-info">
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <div>
                                <p class="render-status-title mb-0">Hanya bisa diunduh</p>
                                <small class="text-muted">
                                    {{ contentTypeLabel }} tidak bisa dibuka di aplikasi — pembaca dokumen di sini
                                    hanya mengerti PDF.
                                </small>
                            </div>
                        </div>
                        <button class="btn-render-ulang" :disabled="mengunduh" @click="unduh">
                            <i class="bi" :class="mengunduh ? 'bi-hourglass-split' : 'bi-download'"></i>
                            {{ mengunduh ? 'Menyiapkan...' : 'Unduh' }}
                        </button>
                    </div>

                    <!-- Status Render PDF + Render Ulang -->
                    <div
                        v-if="needsRender && !verifikasiTertunda"
                        class="render-status-box mb-4"
                        :class="`is-${renderStatus.toLowerCase()}`"
                    >
                        <div class="render-status-info">
                            <i
                                class="bi"
                                :class="
                                    renderStatus === 'FAILED'
                                        ? 'bi-exclamation-octagon-fill'
                                        : 'bi-exclamation-triangle-fill'
                                "
                            ></i>
                            <div>
                                <p class="render-status-title mb-0">
                                    {{
                                        renderStatus === 'FAILED'
                                            ? 'Render gagal'
                                            : renderStatus === 'PROCESSING'
                                              ? 'Sedang dirender...'
                                              : renderStatus === 'PENDING'
                                                ? 'Menunggu antrean render'
                                                : 'Konten belum dirender'
                                    }}
                                </p>
                                <small class="text-muted">
                                    {{
                                        renderStatus === 'FAILED'
                                            ? item.metadata?.renderError || 'Terjadi kendala saat memproses PDF.'
                                            : 'Preview per-halaman belum siap. Anda bisa render ulang.'
                                    }}
                                </small>
                            </div>
                        </div>
                        <button
                            class="btn-render-ulang"
                            :disabled="isRetryingRender || renderStatus === 'PROCESSING' || renderStatus === 'PENDING'"
                            @click="renderUlang"
                        >
                            <i class="bi bi-arrow-repeat" :class="{ 'spin-render': isRetryingRender }"></i>
                            {{ isRetryingRender ? 'Mengantrikan...' : 'Render Ulang' }}
                        </button>
                    </div>

                    <!-- Metadata Dinamis Berdasarkan Tipe -->
                    <div class="meta-grid">
                        <div class="meta-item" v-for="(val, key) in item.metadata" :key="key">
                            <label>{{ key.replace(/([A-Z])/g, ' $1').toUpperCase() }}</label>
                            <p>{{ val }}</p>
                        </div>
                    </div>

                    <!-- Description: Truncate Text -->
                    <div class="mb-4" v-if="hasDescription">
                        <label
                            style="
                                display: block;
                                color: var(--text-muted);
                                font-size: 11px;
                                font-weight: 700;
                                text-transform: uppercase;
                                margin-bottom: 8px;
                                letter-spacing: 0.05em;
                            "
                            >DESKRIPSI</label
                        >
                        <p class="desc-text" :class="{ truncated: !isExpanded }">
                            {{ item.description }}
                        </p>
                        <button class="btn-toggle-desc" @click="isExpanded = !isExpanded">
                            {{ isExpanded ? 'Tutup' : 'Selengkapnya' }}
                        </button>
                    </div>

                    <!-- Linked Content (Bisa Courses, Files, atau Folder) -->
                    <div class="mt-5" v-if="item.linkedItems && item.linkedItems.length">
                        <div
                            class="d-flex align-items-center gap-2 mb-3"
                            style="font-weight: 800; font-size: 1rem; color: var(--text-main)"
                        >
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                            </svg>
                            Terhubung ke {{ item.linkedItems.length }}
                            {{ item.linkedLabel }}
                        </div>
                        <div v-for="link in item.linkedItems" :key="link.title" class="linked-card">
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    style="
                                        background: #f1f5f9;
                                        width: 44px;
                                        height: 44px;
                                        border-radius: 12px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                    "
                                >
                                    <svg
                                        v-if="link.type === 'video'"
                                        width="20"
                                        height="20"
                                        fill="none"
                                        stroke="#1d8cf8"
                                        stroke-width="2.2"
                                    >
                                        <path d="M22 10v6M2 10l10-5 10 5-10 5-10-5zM6 12v5c3 3 9 3 12 0v-5" />
                                    </svg>
                                    <svg v-else width="20" height="20" fill="none" stroke="#1d8cf8" stroke-width="2.2">
                                        <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                        <polyline points="13 2 13 9 20 9"></polyline>
                                    </svg>
                                </div>
                                <span style="font-size: 14px; font-weight: 700; color: #475569">{{ link.title }}</span>
                            </div>
                            <svg width="18" height="18" fill="none" stroke="#cbd5e1" stroke-width="2.5">
                                <path
                                    d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Owner Info -->
                    <div class="mt-5 pt-4 border-top d-flex align-items-center gap-3" v-if="item.owner">
                        <img
                            :src="item.owner.avatar"
                            style="
                                width: 44px;
                                height: 44px;
                                border-radius: 50%;
                                object-fit: cover;
                                border: 2px solid white;
                                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                            "
                        />
                        <div>
                            <p
                                class="mb-0"
                                style="
                                    font-size: 11px;
                                    font-weight: 800;
                                    color: var(--text-muted);
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                "
                            >
                                OWNER
                            </p>
                            <p class="mb-0" style="font-size: 15px; font-weight: 700; color: var(--text-main)">
                                {{ item.owner.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Content Usage Tracking Section -->
                    <div v-if="item.type !== 'category' && item.type !== 'folder'" class="mt-5 pt-4 border-top">
                        <!-- Training Section -->
                        <div class="usage-accordion-item mb-3">
                            <button class="usage-accordion-header" @click="toggleSection('training')">
                                <div class="usage-accordion-title">
                                    <span class="usage-icon">📚</span>
                                    <span>Digunakan dalam Training</span>
                                </div>
                                <div class="usage-accordion-badge">
                                    <span class="badge-count" v-if="!isLoadingUsage">{{
                                        usageData.trainings.length
                                    }}</span>
                                    <i
                                        class="bi transition-icon"
                                        :class="expandedSections.training ? 'bi-chevron-up' : 'bi-chevron-down'"
                                    ></i>
                                </div>
                            </button>

                            <transition name="accordion-collapse">
                                <div v-if="expandedSections.training" class="usage-accordion-content">
                                    <!-- Loading State -->
                                    <div v-if="isLoadingUsage" class="usage-loading">
                                        <i class="bi bi-arrow-repeat spin"></i>
                                        <span>Memuat data training...</span>
                                    </div>

                                    <!-- Error State -->
                                    <div v-else-if="usageError" class="usage-error">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span>{{ usageError }}</span>
                                    </div>

                                    <!-- Content State -->
                                    <div v-else-if="hasTrainings" class="usage-list">
                                        <div
                                            v-for="training in usageData.trainings"
                                            :key="training.id"
                                            class="training-item is-clickable"
                                            @click="openTrainingLink(training)"
                                        >
                                            <div class="training-header">
                                                <span class="training-name">{{ training.name }}</span>
                                                <span class="training-badge" v-if="training.scheduling_count > 0">
                                                    {{ training.scheduling_count }} penugasan
                                                </span>
                                            </div>
                                            <div class="modules-list">
                                                <div
                                                    v-for="module in training.modules"
                                                    :key="module.id"
                                                    class="module-item"
                                                >
                                                    <span class="module-name">{{ module.name }}</span>
                                                    <div class="activities-list">
                                                        <span
                                                            v-for="activity in module.activities"
                                                            :key="activity.id"
                                                            class="activity-badge"
                                                        >
                                                            <span class="activity-type">[{{ activity.type }}]</span>
                                                            <span class="activity-name">{{ activity.name }}</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-else class="usage-empty">
                                        <i class="bi bi-inbox"></i>
                                        <span>Konten tidak digunakan dalam training apapun</span>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Scheduling Section -->
                        <div class="usage-accordion-item">
                            <button class="usage-accordion-header" @click="toggleSection('scheduling')">
                                <div class="usage-accordion-title">
                                    <span class="usage-icon">📅</span>
                                    <span>Penugasan Training</span>
                                </div>
                                <div class="usage-accordion-badge">
                                    <span class="badge-count" v-if="!isLoadingUsage">{{
                                        usageData.penjadwalan.length
                                    }}</span>
                                    <i
                                        class="bi transition-icon"
                                        :class="expandedSections.scheduling ? 'bi-chevron-up' : 'bi-chevron-down'"
                                    ></i>
                                </div>
                            </button>

                            <transition name="accordion-collapse">
                                <div v-if="expandedSections.scheduling" class="usage-accordion-content">
                                    <!-- Loading State -->
                                    <div v-if="isLoadingUsage" class="usage-loading">
                                        <i class="bi bi-arrow-repeat spin"></i>
                                        <span>Memuat data penugasan training...</span>
                                    </div>

                                    <!-- Error State -->
                                    <div v-else-if="usageError" class="usage-error">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span>{{ usageError }}</span>
                                    </div>

                                    <!-- Content State -->
                                    <div v-else-if="hasPenjadwalan" class="usage-list">
                                        <div
                                            v-for="pj in usageData.penjadwalan"
                                            :key="pj.id"
                                            class="penjadwalan-item is-clickable"
                                            @click="openAssignmentLink(pj)"
                                        >
                                            <div class="penjadwalan-header">
                                                <div>
                                                    <span class="penjadwalan-training">{{ pj.training_name }}</span>
                                                    <span
                                                        class="penjadwalan-status"
                                                        :class="`status-${pj.status.toLowerCase()}`"
                                                    >
                                                        {{ pj.status }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="penjadwalan-details">
                                                <span class="detail-item">
                                                    <i class="bi bi-people-fill"></i>
                                                    {{ pj.peserta_count }} peserta
                                                </span>
                                                <span class="detail-item">
                                                    <i class="bi bi-calendar-event"></i>
                                                    Ditugaskan: {{ formatDate(pj.assigned_at || pj.jadwal_mulai) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-else class="usage-empty">
                                        <i class="bi bi-inbox"></i>
                                        <span>Tidak ada penugasan training yang menggunakan konten ini</span>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'DetailPanel',
    props: {
        show: { type: Boolean, default: false },
        item: { type: Object, default: null },
        mengunduh: { type: Boolean, default: false },
    },
    emits: ['close', 'open-viewer', 'render-ulang', 'verifikasi-ulang', 'unduh'],
    data() {
        return {
            isExpanded: false,
            isRetryingRender: false,
            isRetryingVerifikasi: false,
            // Usage tracking data
            usageData: {
                trainings: [],
                penjadwalan: [],
            },
            isLoadingUsage: false,
            usageError: null,
            expandedSections: {
                training: false,
                scheduling: false,
            },
        };
    },
    computed: {
        normalizedType() {
            const raw = (this.item?.fileType || this.item?.type || '').toLowerCase();
            if (['video', 'audio', 'image', 'pdf', 'archive', 'document'].includes(raw)) {
                return raw;
            }

            const mime = (this.item?.metadata?.typeFile || '').toLowerCase();
            if (mime.startsWith('image/')) return 'image';
            if (mime.startsWith('video/')) return 'video';
            if (mime.startsWith('audio/')) return 'audio';
            if (mime.includes('pdf')) return 'pdf';
            return 'document';
        },
        /*
         * Cara penyajian menurut server: viewer | inline | putar | unduh.
         * Tanpa nilai itu (baris lama) turunkan dari tipe, dan yang tak dikenali
         * jatuh ke 'unduh' — bukan diberi izin tampil hanya karena tak diketahui.
         */
        tampilan() {
            const t = (this.item?.tampilan || '').toLowerCase();
            if (['viewer', 'inline', 'putar', 'unduh'].includes(t)) return t;

            if (this.normalizedType === 'pdf') return 'viewer';
            if (this.normalizedType === 'image') return 'inline';
            if (this.normalizedType === 'video' || this.normalizedType === 'audio') return 'putar';
            return this.item?.type === 'folder' ? 'inline' : 'unduh';
        },
        hanyaUnduh() {
            return this.tampilan === 'unduh' && this.item?.type !== 'folder';
        },
        /*
         * "DOCUMENT" sama untuk pdf, docx, xlsx, dan pptx — persis perbedaan
         * yang paling perlu terlihat di sini. extLabel dari server yang membawa
         * nama format sebenarnya.
         */
        contentTypeLabel() {
            if (this.item?.extLabel) return String(this.item.extLabel).toUpperCase();

            const labels = {
                video: 'VIDEO',
                audio: 'AUDIO',
                image: 'IMAGE',
                pdf: 'PDF',
                archive: 'ARCHIVE',
                document: 'DOCUMENT',
            };
            return labels[this.normalizedType] || 'FILE';
        },
        // Status render PDF (DONE bila siap; selain itu perlu/boleh render ulang).
        renderStatus() {
            return (
                this.item?.renderStatus ||
                (this.item?.metadata && this.item.metadata.renderStatus) ||
                ''
            ).toUpperCase();
        },
        // True jika konten PDF dan belum siap (PENDING/PROCESSING/FAILED/UNKNOWN/kosong).
        needsRender() {
            if (this.normalizedType !== 'pdf') return false;
            return this.renderStatus !== 'DONE';
        },
        statusVerifikasi() {
            return (this.item?.statusVerifikasi || '').toUpperCase();
        },
        verifikasiTertunda() {
            return ['MENUNGGU', 'DITOLAK', 'TERTUNDA_PEMINDAIAN'].includes(this.statusVerifikasi);
        },
        verifikasiKelas() {
            return this.statusVerifikasi === 'DITOLAK' ? 'failed' : 'processing';
        },
        verifikasiJudul() {
            return (
                {
                    MENUNGGU: 'Isi berkas sedang diperiksa',
                    TERTUNDA_PEMINDAIAN: 'Menunggu pemindaian antivirus',
                    DITOLAK: 'Berkas ditolak saat verifikasi',
                }[this.statusVerifikasi] || ''
            );
        },
        verifikasiKeterangan() {
            if (this.statusVerifikasi === 'DITOLAK') {
                // Alasan dari server lebih spesifik daripada kalimat umum apa pun
                // yang bisa ditulis di sini — mis. "isi tidak sesuai ekstensi .docx".
                return this.item?.alasanTolak || 'Berkas tidak lolos pemeriksaan dan objeknya sudah dihapus.';
            }
            return 'Konten ini belum bisa dipilih di Builder sampai pemeriksaannya selesai.';
        },
        summaryChips() {
            const metadata = this.item?.metadata || {};
            const chips = [this.contentTypeLabel];

            if (this.normalizedType === 'video') {
                if (metadata.durationSeconds) chips.push(`${metadata.durationSeconds}s`);
                if (metadata.resolution) chips.push(metadata.resolution);
                if (metadata.encodeProgress !== undefined && metadata.encodeProgress !== null) {
                    chips.push(`${metadata.encodeProgress}%`);
                }
            } else {
                const size = this.getSizeLabel();
                if (size !== '-') chips.push(size);
            }

            if (metadata.uploadedAt) {
                chips.push(`${metadata.uploadedAt}`);
            }

            return chips;
        },
        hasDescription() {
            return !!(this.item?.description && `${this.item.description}`.trim());
        },
        /*
         * Punya URL bukan berarti boleh dilihat. Sebelumnya syaratnya hanya
         * "ada url", sehingga docx pun membuka penampil — yang lalu jatuh ke
         * penampil pihak ketiga. Format tanpa penyaji tidak dibuka sama sekali.
         */
        canOpenViewer() {
            if (this.hanyaUnduh) return false;

            return !!(
                this.item?.embedUrl ||
                this.item?.playbackUrl ||
                this.item?.fileUrl ||
                this.item?.previewUrl ||
                this.item?.thumbnailUrl
            );
        },
        hasTrainings() {
            return this.usageData.trainings && this.usageData.trainings.length > 0;
        },
        hasPenjadwalan() {
            return this.usageData.penjadwalan && this.usageData.penjadwalan.length > 0;
        },
    },
    methods: {
        getSizeLabel() {
            const metadata = this.item?.metadata || {};
            if (metadata.sizeHuman) return metadata.sizeHuman;
            if (typeof metadata.sizeBytes === 'number') return this.formatBytes(metadata.sizeBytes);
            if (metadata.storageSizeBytes) return this.formatBytes(Number(metadata.storageSizeBytes));
            return '-';
        },
        formatBytes(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '-';
            if (bytes < 1024) return `${bytes} B`;
            const units = ['KB', 'MB', 'GB', 'TB'];
            let value = bytes;
            let unitIndex = -1;
            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024;
                unitIndex++;
            }
            return `${value.toFixed(2)} ${units[unitIndex]}`;
        },
        openViewer() {
            if (!this.canOpenViewer) return;
            this.$emit('open-viewer', this.item);
        },
        unduh() {
            if (this.mengunduh) return;
            this.$emit('unduh', this.item);
        },
        // Minta dashboard men-trigger render ulang untuk konten ini (PDF).
        renderUlang() {
            if (!this.item || !this.item.id || this.isRetryingRender) return;
            this.isRetryingRender = true;
            this.$emit('render-ulang', this.item);
            // Optimistic: tampilkan sebagai PENDING; dashboard akan refresh status.
            setTimeout(() => {
                this.isRetryingRender = false;
            }, 1500);
        },
        // Antrikan ulang verifikasi untuk berkas yang tersangkut di karantina.
        verifikasiUlang() {
            if (!this.item || !this.item.id || this.isRetryingVerifikasi) return;
            this.isRetryingVerifikasi = true;
            this.$emit('verifikasi-ulang', this.item);
            setTimeout(() => {
                this.isRetryingVerifikasi = false;
            }, 1500);
        },
        async fetchContentUsage() {
            if (!this.item || !this.item.id) return;

            // Only fetch for files, not folders
            if (this.item.type === 'category' || this.item.type === 'folder') {
                return;
            }

            this.isLoadingUsage = true;
            this.usageError = null;

            try {
                const response = await axios.get(`/lms/api/content/${this.item.id}/usage-tracking`);

                if (response.data.success) {
                    this.usageData = {
                        trainings: response.data.result.trainings || [],
                        penjadwalan: response.data.result.penjadwalan || [],
                    };
                } else {
                    this.usageError = response.data.message || 'Gagal mengambil data';
                }
            } catch (error) {
                console.error('Error fetching content usage:', error);
                this.usageError = 'Gagal mengambil data penggunaan konten';
                this.usageData = {
                    trainings: [],
                    penjadwalan: [],
                };
            } finally {
                this.isLoadingUsage = false;
            }
        },
        toggleSection(sectionName) {
            this.expandedSections[sectionName] = !this.expandedSections[sectionName];
        },
        openTrainingLink(training) {
            const trainingId = training?.id_hash || training?.id;
            if (!trainingId) return;
            window.open(`/lms/admin/training/edit/${trainingId}`, '_blank');
        },
        openAssignmentLink(assignment) {
            const penugasanId = assignment?.id_hash || assignment?.id;
            if (!penugasanId) {
                window.open('/lms/penjadwalan', '_blank');
                return;
            }
            window.open(`/lms/penjadwalan?penugasan=${penugasanId}`, '_blank');
        },
        formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
    watch: {
        show(newVal) {
            if (!newVal) {
                this.isExpanded = false;
                this.expandedSections = {
                    training: false,
                    scheduling: false,
                };
            }
            document.body.style.overflow = newVal ? 'hidden' : 'auto';
        },
        item(newVal) {
            if (newVal && this.show) {
                this.fetchContentUsage();
            }
        },
    },
};
</script>
<style scoped>
* {
    --bg-body: var(--hcis-slate-50);
    --white: var(--hcis-white);
    --primary-blue: #1d8cf8;
    --text-main: var(--hcis-slate-800);
    --text-muted: var(--hcis-slate-400);
    --border-soft: var(--hcis-slate-100);
    --radius-lg: 28px;
    --radius-md: 18px;
}

body {
    background-color: var(--bg-body);
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
    overflow-x: hidden;
}

/* --- ANIMASI TRANSISI (GLOBAL) --- */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.4s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: transform 0.5s cubic-bezier(0.32, 0.72, 0, 1);
}

@media (min-width: 992px) {
    .slide-panel-enter-from,
    .slide-panel-leave-to {
        transform: translateX(100%);
    }
}
@media (max-width: 991px) {
    .slide-panel-enter-from,
    .slide-panel-leave-to {
        transform: translateY(100%);
    }
}

/* --- DETAIL PANEL STYLES --- */
.details-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.3);
    backdrop-filter: blur(4px);
    z-index: 1040;
}

.details-panel {
    position: fixed;
    background: var(--white);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.08);
}

@media (min-width: 992px) {
    .details-panel {
        top: 0;
        right: 0;
        width: 480px;
        height: 100vh;
        border-left: 1px solid var(--border-soft);
    }
}
@media (max-width: 991px) {
    .details-panel {
        bottom: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }
}

/* Elements Inside */
.panel-header {
    padding: 32px 24px 0px 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.panel-body {
    padding: 0 24px 24px 24px;
    overflow-y: auto;
    flex-grow: 1;
}

.top-meta-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.meta-chip {
    background: var(--hcis-indigo-50);
    color: var(--hcis-slate-700);
    border: 1px solid #dbeafe;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
}
.panel-preview {
    background: var(--hcis-slate-100);
    border-radius: var(--radius-md);
    aspect-ratio: 16/10;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid var(--border-soft);
    position: relative;
}
.panel-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Metadata Grid Selaras dengan File Stats */
.meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}
.meta-item label {
    display: block;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 6px;
    letter-spacing: 0.05em;
}
.meta-item p {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--hcis-slate-700);
}

/* Linked Cards Selaras dengan FolderCard */
.linked-card {
    background: var(--hcis-white);
    border: 1px solid var(--border-soft);
    padding: 18px;
    border-radius: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    transition: all 0.2s;
}
.linked-card:hover {
    border-color: var(--primary-blue);
    background-color: #f0f7ff;
}

/* Footer Actions */
.panel-preview.is-clickable {
    cursor: pointer;
}

.preview-hint {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(15, 23, 42, 0.65);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    border-radius: 8px;
    padding: 6px 10px;
}
.preview-hint.is-muted {
    background: rgba(100, 116, 139, 0.55);
    font-weight: 600;
}

/* Badge & Utility */
.badge-live {
    background: var(--hcis-green-100);
    color: #166534;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 8px;
}
.badge-version {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 8px;
}
.desc-text {
    font-size: 14px;
    color: var(--hcis-slate-500);
    line-height: 1.6;
    font-weight: 500;
    margin-bottom: 4px;
}
.desc-text.truncated {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.btn-toggle-desc {
    color: var(--primary-blue);
    font-size: 13px;
    font-weight: 800;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
}

.play-overlay-mini {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
}

/* ─── CONTENT USAGE ACCORDION STYLES ─── */
.usage-accordion-item {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: var(--hcis-slate-50);
    overflow: hidden;
    transition: all 0.2s ease;
}

.usage-accordion-item:hover {
    border-color: var(--hcis-slate-300);
}

.usage-accordion-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
    transition: all 0.2s ease;
}

.usage-accordion-header:hover {
    background: rgba(0, 0, 0, 0.02);
}

.usage-accordion-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.usage-icon {
    font-size: 18px;
}

.usage-accordion-badge {
    display: flex;
    align-items: center;
    gap: 8px;
}

.badge-count {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    font-weight: 700;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 28px;
    text-align: center;
}

.transition-icon {
    font-size: 16px;
    color: var(--hcis-slate-400);
    transition: transform 0.3s ease;
}

.usage-accordion-content {
    padding: 0 16px 16px 16px;
    background: var(--hcis-white);
}

/* Accordion Collapse Animation */
.accordion-collapse-enter-active,
.accordion-collapse-leave-active {
    transition: all 0.3s ease;
    max-height: 2000px;
}

.accordion-collapse-enter-from,
.accordion-collapse-leave-to {
    opacity: 0;
    max-height: 0;
}

/* Usage List Styles */
.usage-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.usage-loading,
.usage-error,
.usage-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    text-align: center;
    font-size: 13px;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border-radius: 10px;
    min-height: 60px;
}

.usage-loading i,
.usage-error i,
.usage-empty i {
    font-size: 18px;
}

.usage-loading i {
    animation: spin 1s linear infinite;
}

.usage-error {
    background: var(--hcis-red-50);
    color: var(--hcis-red-800);
}

.usage-error i {
    color: var(--hcis-red-600);
}

.usage-empty {
    background: var(--hcis-green-50);
    color: #166534;
}

.usage-empty i {
    color: #22c55e;
}

/* Training Item Styles */
.training-item {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 12px;
}

.is-clickable {
    cursor: pointer;
    transition: all 0.2s ease;
}

.training-item.is-clickable:hover,
.penjadwalan-item.is-clickable:hover {
    border-color: #93c5fd;
    background: #eff6ff;
}

.training-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.training-name {
    font-weight: 700;
    font-size: 14px;
    color: var(--text-main);
}

.training-badge {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 8px;
    white-space: nowrap;
}

.module-item {
    background: var(--hcis-white);
    border-left: 3px solid var(--hcis-indigo-500);
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 8px;
}

.module-name {
    display: block;
    font-weight: 700;
    font-size: 13px;
    color: var(--text-main);
    margin-bottom: 8px;
}

.activities-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.activity-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    background: var(--hcis-slate-100);
    padding: 4px 8px;
    border-radius: 6px;
    width: fit-content;
}

.activity-type {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-600);
    font-weight: 700;
    padding: 2px 4px;
    border-radius: 4px;
}

.activity-name {
    color: var(--hcis-slate-500);
}

/* Penjadwalan Item Styles */
.penjadwalan-item {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
}

.penjadwalan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.penjadwalan-training {
    display: block;
    font-weight: 700;
    font-size: 14px;
    color: var(--text-main);
    margin-bottom: 4px;
}

.penjadwalan-status {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 8px;
}

.penjadwalan-status.status-aktif {
    background: var(--hcis-green-100);
    color: #166534;
}

.penjadwalan-status.status-selesai {
    background: #dbeafe;
    color: #1e40af;
}

.penjadwalan-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--hcis-slate-500);
}

.detail-item i {
    font-size: 14px;
    color: var(--hcis-slate-400);
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* ─── Status Render PDF + Render Ulang ─── */
.render-status-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--hcis-amber-200);
    background: var(--hcis-amber-50);
    flex-wrap: wrap;
}
.render-status-box.is-failed {
    border-color: var(--hcis-red-200);
    background: var(--hcis-red-50);
}
/* Keterangan, bukan peringatan — biru netral, bukan kuning/merah. */
.render-status-box.is-unduh {
    border-color: #bfdbfe;
    background: #eff6ff;
}
.render-status-box.is-unduh .render-status-info > i {
    color: var(--hcis-blue-600);
}
.render-status-info {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}
.render-status-info > i {
    font-size: 1.1rem;
    margin-top: 2px;
    color: var(--hcis-amber-700);
    flex-shrink: 0;
}
.render-status-box.is-failed .render-status-info > i {
    color: var(--hcis-red-600);
}
.render-status-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--hcis-slate-800);
}
.btn-render-ulang {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: #fff;
    border: none;
    border-radius: 50px;
    padding: 8px 16px;
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-render-ulang:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
}
.btn-render-ulang:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.spin-render {
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
</style>
