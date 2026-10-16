<template>
    <Teleport :to="teleportTo" :disabled="!teleportTo">
        <Transition name="hcis-viewer-fade">
            <div
                v-if="modelValue && activeFile"
                class="hcis-viewer-overlay"
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                @click.self="handleClose"
                @keydown.esc="handleClose"
                @keydown.left="prevFile"
                @keydown.right="nextFile"
            >
                <!-- Container Modal Window Viewer -->
                <div class="hcis-viewer" :class="{ 'is-fullscreen': isFullscreen }">
                    <!-- Glassmorphic Header Toolbar -->
                    <header class="hcis-viewer__header">
                        <div class="hcis-viewer__title-group">
                            <div class="hcis-viewer__icon-badge" :class="fileTypeClass">
                                <HcisIcon :name="fileTypeIcon" size="sm" />
                            </div>
                            <div class="hcis-viewer__meta">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h3 class="hcis-viewer__filename" :title="fileName">
                                        {{ fileName }}
                                    </h3>
                                    <span v-if="fileList.length > 1" class="hcis-viewer__index-badge">
                                        {{ activeIndex + 1 }} / {{ fileList.length }}
                                    </span>
                                    <!-- View Only / Confidential Security Tag -->
                                    <span v-if="!canDownload || isConfidential" class="hcis-viewer__view-only-badge">
                                        <HcisIcon name="lock-fill" size="2xs" class="me-1" />
                                        Hanya Pratinjau (View Only)
                                    </span>
                                </div>
                                <div class="hcis-viewer__submeta">
                                    <span v-if="fileSizeText" class="hcis-viewer__filesize">{{ fileSizeText }}</span>
                                    <span v-if="fileExt" class="hcis-viewer__ext-tag">.{{ fileExt.toUpperCase() }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Toolbar -->
                        <div class="hcis-viewer__actions">
                            <!-- Desktop-Only Image Zoom & Rotate Controls -->
                            <template v-if="isImage">
                                <div class="hcis-viewer__zoom-group d-none d-md-inline-flex">
                                    <button
                                        type="button"
                                        class="hcis-viewer__btn"
                                        title="Zoom Out (-)"
                                        @click="zoomOut"
                                    >
                                        <HcisIcon name="dash-lg" size="xs" />
                                    </button>
                                    <span class="hcis-viewer__zoom-text">{{ Math.round(zoomScale * 100) }}%</span>
                                    <button
                                        type="button"
                                        class="hcis-viewer__btn"
                                        title="Zoom In (+)"
                                        @click="zoomIn"
                                    >
                                        <HcisIcon name="plus-lg" size="xs" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="hcis-viewer__btn d-none d-md-inline-flex"
                                    title="Putar 90° (R)"
                                    @click="rotate"
                                >
                                    <HcisIcon name="arrow-clockwise" size="xs" />
                                </button>
                                <button
                                    type="button"
                                    class="hcis-viewer__btn d-none d-lg-inline-flex"
                                    title="Reset Tampilan"
                                    @click="resetTransform"
                                >
                                    <HcisIcon name="aspect-ratio" size="xs" />
                                </button>
                                <div class="hcis-viewer__divider d-none d-md-block" />
                            </template>

                            <!-- Print Document (Only if allowed & desktop) -->
                            <button
                                v-if="(isPdf || isImage) && canPrint"
                                type="button"
                                class="hcis-viewer__btn d-none d-md-inline-flex"
                                :disabled="isPrinting"
                                :title="isPrinting ? 'Menyiapkan cetak…' : 'Cetak Dokumen'"
                                @click="printCurrentFile"
                            >
                                <!-- Berkas besar diambil dulu sebagai blob sebelum
                                     bisa dicetak; tanpa penanda ini tombolnya
                                     tampak tak bereaksi dan ditekan berkali-kali. -->
                                <HcisIcon :name="isPrinting ? 'hourglass-split' : 'printer'" size="xs" />
                            </button>

                            <!-- Toggle Fullscreen (Desktop only) -->
                            <button
                                type="button"
                                class="hcis-viewer__btn d-none d-md-inline-flex"
                                :title="isFullscreen ? 'Kecilkan Layar' : 'Layar Penuh'"
                                @click="isFullscreen = !isFullscreen"
                            >
                                <HcisIcon :name="isFullscreen ? 'fullscreen-exit' : 'fullscreen'" size="xs" />
                            </button>

                            <!-- Open New Tab (Only if allowed) -->
                            <a
                                v-if="currentFileUrl && canOpenTab"
                                :href="currentFileUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="hcis-viewer__btn d-none d-sm-inline-flex"
                                title="Buka di Tab Baru"
                            >
                                <HcisIcon name="box-arrow-up-right" size="xs" />
                            </a>

                            <!-- Download File (Only if allowed) -->
                            <button
                                v-if="currentFileUrl && canDownload"
                                type="button"
                                class="hcis-viewer__btn hcis-viewer__btn--primary"
                                :disabled="isDownloading"
                                title="Unduh Berkas"
                                @click="downloadCurrentFile"
                            >
                                <HcisIcon :name="isDownloading ? 'hourglass-split' : 'download'" size="xs" class="me-1" />
                                <span class="d-none d-sm-inline">{{ isDownloading ? 'Mengunduh...' : 'Unduh' }}</span>
                            </button>

                            <!-- Close Modal Button (Always Visible & Prioritized!) -->
                            <button
                                type="button"
                                class="hcis-viewer__btn hcis-viewer__btn--close"
                                aria-label="Tutup pratinjau"
                                title="Tutup (ESC)"
                                @click="handleClose"
                            >
                                <HcisIcon name="x-lg" size="xs" />
                            </button>
                        </div>
                    </header>

                    <!-- Main Viewer Stage -->
                    <main class="hcis-viewer__stage">
                        <!-- Stage Loading Animation Overlay on File Request/Switch -->
                        <Transition name="hcis-viewer-fade">
                            <div v-if="isLoadingMedia" class="hcis-viewer__stage-loader">
                                <div class="hcis-viewer__spinner-box">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Memuat berkas...</span>
                                    </div>
                                    <span class="hcis-viewer__loader-text">Memuat berkas...</span>
                                </div>
                            </div>
                        </Transition>

                        <!-- Watermark Security Layer -->
                        <div v-if="activeWatermark" class="hcis-viewer__watermark" aria-hidden="true">
                            <span>{{ activeWatermark }}</span>
                        </div>

                        <!-- Navigation Chevron Left -->
                        <button
                            v-if="fileList.length > 1"
                            type="button"
                            class="hcis-viewer__nav-btn hcis-viewer__nav-btn--prev"
                            :disabled="activeIndex <= 0"
                            title="Berkas Sebelumnya (←)"
                            @click="prevFile"
                        >
                            <HcisIcon name="chevron-left" size="md" />
                        </button>

                        <!-- Mode 1: Image Viewer (with Smooth Touch/Mouse Pan & Drag) -->
                        <div
                            v-if="isImage && currentFileUrl"
                            class="hcis-viewer__image-wrapper"
                            @mousedown="onMouseDown"
                            @mousemove="onMouseMove"
                            @mouseup="onMouseUp"
                            @mouseleave="onMouseUp"
                            @touchstart="onTouchStart"
                            @touchmove="onTouchMove"
                            @touchend="onTouchEnd"
                            @touchcancel="onTouchEnd"
                            @wheel.prevent="onWheelZoom"
                            @dblclick="toggleQuickZoom"
                        >
                            <img
                                :src="currentFileUrl"
                                :alt="fileName"
                                class="hcis-viewer__img"
                                :class="{ 'is-dragging': isDragging }"
                                :style="{
                                    transform: `translate3d(${translateX}px, ${translateY}px, 0) scale(${zoomScale}) rotate(${rotationDeg}deg)`,
                                    cursor: zoomScale > 1 ? (isDragging ? 'grabbing' : 'grab') : 'default',
                                    userSelect: 'none',
                                    touchAction: 'none',
                                }"
                                draggable="false"
                                @load="onMediaLoaded"
                                @error="onMediaError"
                            />

                            <!-- Mobile Floating Zoom & Rotate Pill -->
                            <div class="hcis-viewer__mobile-fab d-flex d-md-none" @touchstart.stop @mousedown.stop>
                                <button type="button" class="hcis-viewer__mobile-fab-btn" title="Zoom Out" @click.stop="zoomOut">
                                    <HcisIcon name="dash-lg" size="xs" />
                                </button>
                                <span class="hcis-viewer__mobile-fab-zoom">{{ Math.round(zoomScale * 100) }}%</span>
                                <button type="button" class="hcis-viewer__mobile-fab-btn" title="Zoom In" @click.stop="zoomIn">
                                    <HcisIcon name="plus-lg" size="xs" />
                                </button>
                                <div class="hcis-viewer__mobile-fab-divider" />
                                <button type="button" class="hcis-viewer__mobile-fab-btn" title="Rotate" @click.stop="rotate">
                                    <HcisIcon name="arrow-clockwise" size="xs" />
                                </button>
                                <button type="button" class="hcis-viewer__mobile-fab-btn" title="Reset" @click.stop="resetTransform">
                                    <HcisIcon name="aspect-ratio" size="xs" />
                                </button>
                            </div>
                        </div>

                        <!-- Mode 2: Video Player -->
                        <div v-else-if="isVideo && currentFileUrl" class="hcis-viewer__video-wrapper">
                            <video
                                controls
                                autoplay
                                class="hcis-viewer__video"
                                :src="currentFileUrl"
                                :controlslist="!canDownload ? 'nodownload' : undefined"
                                @loadeddata="onMediaLoaded"
                                @canplay="onMediaLoaded"
                                @error="onMediaError"
                            >
                                Peramban Anda tidak mendukung pemutar video.
                            </video>
                        </div>

                        <!-- Mode 3: Audio Player -->
                        <div v-else-if="isAudio && currentFileUrl" class="hcis-viewer__audio-wrapper">
                            <div class="hcis-viewer__audio-card">
                                <div class="hcis-viewer__audio-icon-box">
                                    <HcisIcon name="music-note-beaming" size="xl" class="hcis-viewer__audio-icon" />
                                </div>
                                <h4 class="hcis-viewer__audio-title">{{ fileName }}</h4>
                                <span class="hcis-viewer__audio-sub">{{ fileSizeText || 'Pemutar Suara HCIS' }}</span>
                                <audio
                                    controls
                                    class="hcis-viewer__audio mt-3"
                                    :src="currentFileUrl"
                                    :controlslist="!canDownload ? 'nodownload' : undefined"
                                    @loadeddata="onMediaLoaded"
                                    @canplay="onMediaLoaded"
                                    @error="onMediaError"
                                />
                            </div>
                        </div>

                        <!-- Mode 4: PDF Document Viewer -->
                        <div v-else-if="isPdf && currentFileUrl" class="hcis-viewer__pdf-wrapper">
                            <!-- HP / peramban tanpa penampil PDF bawaan: digambar sendiri (PDF.js). -->
                            <HcisPdfCanvas
                                v-if="pakaiPdfCanvas"
                                :url="currentFileUrl"
                                :halaman-mulai="halamanMulai"
                                @loaded="onMediaLoaded"
                                @error="onMediaError"
                            />
                            <iframe
                                v-else
                                ref="pdfIframe"
                                :src="urlPdfIframe"
                                class="hcis-viewer__pdf-iframe"
                                title="Pratinjau Dokumen PDF"
                                @load="onMediaLoaded"
                                @error="onMediaError"
                            />
                        </div>

                        <!-- Mode 5: Fallback Untyped File / Office Document -->
                        <div v-else class="hcis-viewer__fallback">
                            <div class="hcis-viewer__fallback-box">
                                <div class="hcis-viewer__fallback-icon-wrap" :class="fileTypeClass">
                                    <HcisIcon :name="fileTypeIcon" size="xl" />
                                </div>
                                <h4 class="hcis-viewer__fallback-title">
                                    {{ currentFileUrl ? 'Pratinjau Tidak Tersedia Langsung' : 'Pratinjau Belum Tersedia' }}
                                </h4>
                                <p class="hcis-viewer__fallback-sub">
                                    <template v-if="currentFileUrl && canDownload">
                                        Dokumen <code>.{{ fileExt }}</code> dapat diunduh untuk dibuka pada aplikasi perangkat Anda.
                                    </template>
                                    <template v-else-if="currentFileUrl && !canDownload">
                                        Dokumen <code>.{{ fileExt }}</code> bersifat rahasia dan dibatasi hanya untuk ditinjau oleh Admin.
                                    </template>
                                    <template v-else>
                                        Tautan berkas ini belum diunggah secara penuh.
                                    </template>
                                </p>
                                <button
                                    v-if="currentFileUrl && canDownload"
                                    type="button"
                                    class="hcis-viewer__fallback-btn"
                                    :disabled="isDownloading"
                                    @click="downloadCurrentFile"
                                >
                                    <HcisIcon :name="isDownloading ? 'hourglass-split' : 'download'" size="xs" class="me-1" />
                                    {{ isDownloading ? 'Mengunduh Berkas...' : `Unduh Berkas ${fileName}` }}
                                </button>
                            </div>
                        </div>

                        <!-- Navigation Chevron Right -->
                        <button
                            v-if="fileList.length > 1"
                            type="button"
                            class="hcis-viewer__nav-btn hcis-viewer__nav-btn--next"
                            :disabled="activeIndex >= fileList.length - 1"
                            title="Berkas Berikutnya (→)"
                            @click="nextFile"
                        >
                            <HcisIcon name="chevron-right" size="md" />
                        </button>
                    </main>

                    <!-- Filmstrip Thumbnail Dock (When multiple files provided) -->
                    <footer v-if="fileList.length > 1" class="hcis-viewer__filmstrip">
                        <div class="hcis-viewer__filmstrip-track">
                            <button
                                v-for="(fItem, fIdx) in fileList"
                                :key="fIdx"
                                type="button"
                                class="hcis-viewer__thumb-item"
                                :class="{ 'is-active': fIdx === activeIndex }"
                                @click="activeIndex = fIdx"
                            >
                                <span class="hcis-viewer__thumb-icon">
                                    <HcisIcon :name="getFileIconFromItem(fItem)" size="2xs" />
                                </span>
                                <span class="hcis-viewer__thumb-name">{{ getFileNameFromItem(fItem) }}</span>
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';
import { useScrollLock } from './useScrollLock';
import { urlAman } from './urlAman';
import HcisPdfCanvas from './HcisPdfCanvas.vue';

defineOptions({ name: 'HcisFileViewer' });

const { kunci: lockScroll, buka: unlockScroll } = useScrollLock();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    file: { type: [Object, String], default: null },
    files: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    teleportTo: { type: [String, Boolean], default: 'body' },

    // Security, Permissions & Governance Props
    allowDownload: { type: Boolean, default: true },
    allowPrint: { type: Boolean, default: true },
    allowExternalLink: { type: Boolean, default: true },
    confidential: { type: Boolean, default: false },
    watermark: { type: String, default: '' },
    initialIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'close', 'open', 'change']);

// Multi-file support
const activeIndex = ref(props.initialIndex || 0);
const isFullscreen = ref(false);
const pdfIframe = ref(null);

const fileList = computed(() => {
    if (props.files && props.files.length) {
        return props.files;
    }
    if (props.file) {
        return [props.file];
    }
    return [];
});

const activeFile = computed(() => {
    if (!fileList.value.length) return null;
    return fileList.value[activeIndex.value] || fileList.value[0];
});

// Permission checks per-file or global
const canDownload = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.allowDownload !== undefined) {
        return Boolean(activeFile.value.allowDownload);
    }
    return Boolean(props.allowDownload);
});

const canPrint = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.allowPrint !== undefined) {
        return Boolean(activeFile.value.allowPrint);
    }
    return Boolean(props.allowPrint);
});

const canOpenTab = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.allowExternalLink !== undefined) {
        return Boolean(activeFile.value.allowExternalLink);
    }
    return Boolean(props.allowExternalLink);
});

const isConfidential = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.confidential !== undefined) {
        return Boolean(activeFile.value.confidential);
    }
    return Boolean(props.confidential);
});

const activeWatermark = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.watermark !== undefined) {
        return activeFile.value.watermark;
    }
    if (props.watermark) return props.watermark;
    if (isConfidential.value || !canDownload.value) return 'HCIS CONFIDENTIAL • VIEW ONLY';
    return '';
});

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            activeIndex.value = props.initialIndex || 0;
            lockScroll();
            resetTransform();
            emit('open');
        } else {
            unlockScroll();
            emit('close');
        }
    }
);

watch(
    () => props.initialIndex,
    (newIdx) => {
        if (typeof newIdx === 'number' && newIdx >= 0) {
            activeIndex.value = newIdx;
            resetTransform();
        }
    }
);

watch(
    () => props.file,
    () => {
        activeIndex.value = 0;
        resetTransform();
    }
);

function getFileNameFromItem(item) {
    if (!item) return 'Berkas';
    if (typeof item === 'string') {
        const clean = item.split('?')[0].split('#')[0];
        const parts = clean.split('/');
        return parts[parts.length - 1] || 'Berkas';
    }
    if (item.name) {
        const clean = String(item.name).split('?')[0].split('#')[0];
        return clean;
    }
    if (item.title) return item.title;
    if (item.url) {
        const clean = String(item.url).split('?')[0].split('#')[0];
        const parts = clean.split('/');
        return parts[parts.length - 1] || 'Berkas';
    }
    return 'Berkas';
}

function getFileIconFromItem(item) {
    const name = getFileNameFromItem(item).toLowerCase();
    if (name.endsWith('.pdf')) return 'file-earmark-pdf-fill';
    if (name.match(/\.(jpg|jpeg|jfif|pjpeg|pjp|png|gif|webp|svg|bmp|ico|avif|tiff|tif)$/)) return 'file-earmark-image-fill';
    if (name.match(/\.(mp4|webm|mov)$/)) return 'file-earmark-play-fill';
    if (name.match(/\.(mp3|wav|m4a)$/)) return 'file-earmark-music-fill';
    if (name.match(/\.(xls|xlsx|csv)$/)) return 'file-earmark-excel-fill';
    if (name.match(/\.(doc|docx)$/)) return 'file-earmark-word-fill';
    return 'file-earmark-text-fill';
}

const fileName = computed(() => {
    if (!activeFile.value) return 'Pratinjau Berkas';
    if (props.title) return props.title;
    return getFileNameFromItem(activeFile.value);
});

const currentFileUrl = computed(() => {
    if (!activeFile.value) return '';
    if (typeof activeFile.value === 'string') return urlAman(activeFile.value);
    if (activeFile.value.url) return urlAman(activeFile.value.url);
    if (typeof window !== 'undefined' && activeFile.value.raw && (activeFile.value.raw instanceof Blob || activeFile.value.raw instanceof File)) {
        try {
            return URL.createObjectURL(activeFile.value.raw);
        } catch {
            return '';
        }
    }
    return '';
});

/**
 * Halaman PDF yang langsung dibuka (mis. panduan per halaman aplikasi).
 * Dari item berkas: `halamanMulai` (atau `page`); bawaan halaman 1.
 */
const halamanMulai = computed(() => {
    const f = activeFile.value;
    const n = typeof f === 'object' && f !== null ? Number(f.halamanMulai ?? f.page ?? 1) : 1;
    return Number.isFinite(n) && n >= 1 ? Math.floor(n) : 1;
});

/** iframe: penampil bawaan peramban memahami fragmen #page=N. */
const urlPdfIframe = computed(() => {
    if (!currentFileUrl.value) return '';
    return halamanMulai.value > 1 ? `${currentFileUrl.value}#page=${halamanMulai.value}` : currentFileUrl.value;
});

const isMobileViewport = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);

function onWindowResize() {
    if (typeof window !== 'undefined') {
        isMobileViewport.value = window.innerWidth <= 768;
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', onWindowResize);
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', onWindowResize);
    }
});

/**
 * Pakai penggambar PDF sendiri (PDF.js) bila peramban tidak bisa menampilkan PDF
 * di <iframe> (Chrome Android, Safari iOS) atau pada tampilan layar mobile (<= 768px).
 * Desktop lebar tetap memakai iframe bawaan peramban.
 */
const pakaiPdfCanvas = computed(() => {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') return false;
    if (navigator.pdfViewerEnabled === false) return true;
    const ua = navigator.userAgent || '';
    const perangkatSentuh = /Android|iPhone|iPad|iPod|Mobile/i.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    return perangkatSentuh || isMobileViewport.value;
});

const fileExt = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null) {
        if (activeFile.value.ext) {
            const cleanExt = String(activeFile.value.ext).split('?')[0].split('#')[0].toLowerCase().replace(/^\./, '');
            if (cleanExt.length <= 5) return cleanExt;
        }
        if (activeFile.value.type) {
            const t = String(activeFile.value.type).toLowerCase();
            if (t.includes('pdf')) return 'pdf';
            if (t.includes('png')) return 'png';
            if (t.includes('jpeg') || t.includes('jpg') || t.includes('jfif') || t.includes('pjpeg')) return 'jpg';
            if (t.includes('webp')) return 'webp';
            if (t.includes('svg')) return 'svg';
            if (t.includes('bmp')) return 'bmp';
            if (t.includes('gif')) return 'gif';
            if (t.includes('avif')) return 'avif';
            if (t.includes('mp4') || t.includes('webm') || t.includes('quicktime') || t.includes('video/')) return 'mp4';
            if (t.includes('mp3') || t.includes('wav') || t.includes('audio/')) return 'mp3';
        }
    }
    const name = typeof activeFile.value === 'object' && activeFile.value?.name ? activeFile.value.name : fileName.value;
    if (name && name.includes('.')) {
        const cleanName = name.split('?')[0].split('#')[0];
        const ext = cleanName.split('.').pop().toLowerCase();
        if (ext && ext.length <= 5) return ext;
    }
    const url = currentFileUrl.value;
    if (url) {
        const cleanUrl = url.split('?')[0].split('#')[0];
        if (cleanUrl.includes('.')) {
            const ext = cleanUrl.split('.').pop().toLowerCase();
            if (ext && ext.length <= 5) return ext;
        }
    }
    return '';
});

const fileSizeText = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value !== null && activeFile.value.size) {
        const bytes = activeFile.value.size;
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    return '';
});

const isImage = computed(() => {
    if (typeof activeFile.value === 'object' && activeFile.value?.type && String(activeFile.value.type).startsWith('image/')) {
        return true;
    }
    return ['jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif', 'tiff', 'tif'].includes(fileExt.value);
});
const isVideo = computed(() => ['mp4', 'webm', 'ogg', 'mov'].includes(fileExt.value));
const isAudio = computed(() => ['mp3', 'wav', 'aac', 'm4a'].includes(fileExt.value));
const isPdf = computed(() => fileExt.value === 'pdf');

const fileTypeIcon = computed(() => {
    if (isImage.value) return 'file-earmark-image-fill';
    if (isVideo.value) return 'file-earmark-play-fill';
    if (isAudio.value) return 'file-earmark-music-fill';
    if (isPdf.value) return 'file-earmark-pdf-fill';
    if (['xls', 'xlsx', 'csv'].includes(fileExt.value)) return 'file-earmark-excel-fill';
    if (['doc', 'docx'].includes(fileExt.value)) return 'file-earmark-word-fill';
    return 'file-earmark-text-fill';
});

const fileTypeClass = computed(() => {
    if (isImage.value) return 'is-image';
    if (isVideo.value) return 'is-video';
    if (isAudio.value) return 'is-audio';
    if (isPdf.value) return 'is-pdf';
    return 'is-generic';
});

// Image Transforms & Pan/Drag State
const zoomScale = ref(1);
const rotationDeg = ref(0);
const translateX = ref(0);
const translateY = ref(0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);
const initialTranslateX = ref(0);
const initialTranslateY = ref(0);
const initialPinchDistance = ref(0);
const initialPinchScale = ref(1);

const zoomIn = () => {
    zoomScale.value = Math.min(4, Number((zoomScale.value + 0.35).toFixed(2)));
};

const zoomOut = () => {
    const nextZoom = Math.max(0.5, Number((zoomScale.value - 0.35).toFixed(2)));
    zoomScale.value = nextZoom;
    if (nextZoom <= 1) {
        translateX.value = 0;
        translateY.value = 0;
    }
};

const rotate = () => {
    rotationDeg.value = (rotationDeg.value + 90) % 360;
};

const resetTransform = () => {
    zoomScale.value = 1;
    rotationDeg.value = 0;
    translateX.value = 0;
    translateY.value = 0;
    isDragging.value = false;
};

const toggleQuickZoom = () => {
    if (zoomScale.value > 1.05) {
        resetTransform();
    } else {
        zoomScale.value = 2.25;
    }
};

const onWheelZoom = (e) => {
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
};

// Mouse Drag Handlers
const onMouseDown = (e) => {
    if (e.button !== 0) return;
    startX.value = e.clientX;
    startY.value = e.clientY;
    initialTranslateX.value = translateX.value;
    initialTranslateY.value = translateY.value;
    isDragging.value = true;
};

const onMouseMove = (e) => {
    if (!isDragging.value) return;
    e.preventDefault();
    const deltaX = e.clientX - startX.value;
    const deltaY = e.clientY - startY.value;
    translateX.value = initialTranslateX.value + deltaX;
    translateY.value = initialTranslateY.value + deltaY;
};

const onMouseUp = () => {
    isDragging.value = false;
};

// Touch Pan & Pinch-to-Zoom Handlers (Mobile)
let lastImageTapTime = 0;
let lastImageTapX = 0;
let lastImageTapY = 0;

const onTouchStart = (e) => {
    if (e.touches.length === 1) {
        const t = e.touches[0];
        const now = Date.now();
        const dist = Math.hypot(t.clientX - lastImageTapX, t.clientY - lastImageTapY);
        if (now - lastImageTapTime < 350 && dist < 30) {
            e.preventDefault();
            toggleQuickZoom();
            lastImageTapTime = 0;
            return;
        }
        lastImageTapTime = now;
        lastImageTapX = t.clientX;
        lastImageTapY = t.clientY;

        startX.value = t.clientX;
        startY.value = t.clientY;
        initialTranslateX.value = translateX.value;
        initialTranslateY.value = translateY.value;
        isDragging.value = true;
    } else if (e.touches.length === 2) {
        isDragging.value = false;
        initialPinchDistance.value = Math.hypot(
            e.touches[1].clientX - e.touches[0].clientX,
            e.touches[1].clientY - e.touches[0].clientY,
        );
        initialPinchScale.value = zoomScale.value;
    }
};

const onTouchMove = (e) => {
    if (e.touches.length === 2 && initialPinchDistance.value > 0) {
        e.preventDefault();
        const currentDist = Math.hypot(
            e.touches[1].clientX - e.touches[0].clientX,
            e.touches[1].clientY - e.touches[0].clientY,
        );
        const ratio = currentDist / initialPinchDistance.value;
        zoomScale.value = Math.min(4, Math.max(0.5, Number((initialPinchScale.value * ratio).toFixed(2))));
    } else if (e.touches.length === 1 && isDragging.value) {
        const deltaX = e.touches[0].clientX - startX.value;
        const deltaY = e.touches[0].clientY - startY.value;
        if (Math.hypot(deltaX, deltaY) > 10) {
            lastImageTapTime = 0;
        }
        if (zoomScale.value > 1.05) {
            e.preventDefault();
            translateX.value = initialTranslateX.value + deltaX;
            translateY.value = initialTranslateY.value + deltaY;
        }
    }
};

const onTouchEnd = (e) => {
    if (e.touches.length === 0) {
        isDragging.value = false;
        initialPinchDistance.value = 0;
    } else if (e.touches.length === 1) {
        startX.value = e.touches[0].clientX;
        startY.value = e.touches[0].clientY;
        initialTranslateX.value = translateX.value;
        initialTranslateY.value = translateY.value;
        isDragging.value = true;
    }
};

const isLoadingMedia = ref(true);

function onMediaLoaded() {
    isLoadingMedia.value = false;
}

function onMediaError() {
    isLoadingMedia.value = false;
}

watch(
    () => currentFileUrl.value,
    (newUrl) => {
        if (newUrl) {
            isLoadingMedia.value = true;
            setTimeout(() => {
                if (isLoadingMedia.value) {
                    isLoadingMedia.value = false;
                }
            }, 3000);
        } else {
            isLoadingMedia.value = false;
        }
    },
    { immediate: true }
);

function prevFile() {
    if (activeIndex.value > 0) {
        activeIndex.value--;
        isLoadingMedia.value = true;
        resetTransform();
        emit('change', { index: activeIndex.value, file: activeFile.value });
    }
}

function nextFile() {
    if (activeIndex.value < fileList.value.length - 1) {
        activeIndex.value++;
        isLoadingMedia.value = true;
        resetTransform();
        emit('change', { index: activeIndex.value, file: activeFile.value });
    }
}

function handleClose() {
    emit('update:modelValue', false);
}

/**
 * Cetak berkas yang sedang tampil.
 *
 * MASALAHNYA: `iframe.contentWindow.print()` HANYA boleh dipanggil pada bingkai
 * se-origin. Berkas kita dilayani dari penyimpanan objek lewat URL bertanda
 * tangan, jadi bingkainya SELALU lintas-origin dan panggilan itu SELALU
 * dilempar — apa pun aturan CORS-nya, sebab CORS mengatur `fetch`, bukan akses
 * DOM antar-bingkai.
 *
 * Cadangan lamanya membuka tab baru berisi URL mentahnya. Itu bukan mencetak:
 * pengguna menekan "Cetak" lalu mendapat tab berisi tautan penyimpanan, dan
 * dialog cetaknya tak pernah muncul karena `win.onload` tak menyala untuk
 * dokumen yang dirender penampil PDF bawaan peramban.
 *
 * OBATNYA: ambil berkasnya sebagai blob, lalu cetak dari bingkai `blob:` —
 * yang se-origin, sehingga `print()` diizinkan.
 */
async function printCurrentFile() {
    if (!canPrint.value || isPrinting.value) return;

    /* 0. Mode kanvas (HP): tidak ada bingkai PDF untuk dicetak. Buka berkasnya
          di tab baru — penampil/aplikasi PDF perangkat yang menyediakan cetak. */
    if (isPdf.value && pakaiPdfCanvas.value && currentFileUrl.value) {
        window.open(currentFileUrl.value, '_blank', 'noopener');
        return;
    }

    /* 1. Bingkai yang sudah tampil, bila kebetulan se-origin (berkas lokal). */
    if (isPdf.value && pdfIframe.value) {
        try {
            pdfIframe.value.contentWindow.print();
            return;
        } catch {
            /* Lintas-origin — jalur normal untuk berkas dari penyimpanan objek. */
        }
    }

    if (!currentFileUrl.value) return;

    /* 2. Jalur sebenarnya: unduh ke blob, cetak dari bingkai se-origin. */
    try {
        isPrinting.value = true;
        const res = await fetch(currentFileUrl.value, { mode: 'cors' });
        if (!res.ok) throw new Error('Print request failed');
        await printFromBlob(await res.blob());

        return;
    } catch {
        /* Turun ke cadangan terakhir. */
    } finally {
        isPrinting.value = false;
    }

    /* 3. Cadangan terakhir, dan hanya bila membuka tab memang diizinkan.
       Tab berisi URL penyimpanan setidaknya bisa dicetak pengguna sendiri —
       tapi ia TIDAK ditawarkan pada berkas rahasia atau yang dikunci
       `allowExternalLink`, sebab di sana membuka tautannya justru hal yang
       sengaja dilarang. */
    if (canOpenTab.value) {
        window.open(currentFileUrl.value, '_blank', 'noopener');
    }
}

/**
 * Cetak sebuah blob lewat bingkai tersembunyi.
 *
 * Bingkainya HARUS tetap hidup sampai dialog cetak selesai; membuangnya lebih
 * awal membatalkan pencetakan tanpa satu pun pesan. Karena tak ada peristiwa
 * yang andal untuk "dialog ditutup", ia dibersihkan setelah tenggat yang
 * longgar — bingkai kosong yang tertinggal sebentar jauh lebih murah daripada
 * cetakan yang batal diam-diam.
 */
function printFromBlob(blob) {
    return new Promise((resolve, reject) => {
        const blobUrl = URL.createObjectURL(blob);
        const frame = document.createElement('iframe');
        frame.style.position = 'fixed';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.setAttribute('aria-hidden', 'true');

        const bersihkan = () => {
            URL.revokeObjectURL(blobUrl);
            frame.remove();
        };

        frame.onload = () => {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(bersihkan, 60000);
                resolve();
            } catch (e) {
                bersihkan();
                reject(e);
            }
        };
        frame.onerror = () => {
            bersihkan();
            reject(new Error('Print frame failed'));
        };

        frame.src = blobUrl;
        document.body.appendChild(frame);
    });
}

const isDownloading = ref(false);
const isPrinting = ref(false);

async function downloadCurrentFile() {
    if (!currentFileUrl.value || !canDownload.value || isDownloading.value) return;

    try {
        isDownloading.value = true;
        const targetUrl = currentFileUrl.value;
        const targetName = fileName.value || 'berkas-unduhan';

        // 1. Check if raw File or Blob instance
        if (activeFile.value && activeFile.value.raw && (activeFile.value.raw instanceof Blob || activeFile.value.raw instanceof File)) {
            const blobUrl = URL.createObjectURL(activeFile.value.raw);
            triggerDownload(blobUrl, targetName);
            setTimeout(() => URL.revokeObjectURL(blobUrl), 1500);
            return;
        }

        // 2. Fetch as Blob to force real file download to disk instead of opening in-browser tab
        const response = await fetch(targetUrl, { mode: 'cors' });
        if (!response.ok) throw new Error('Download request failed');
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        triggerDownload(blobUrl, targetName);
        setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1500);
    } catch {
        // Fallback: trigger anchor download
        triggerDownload(currentFileUrl.value, fileName.value);
    } finally {
        isDownloading.value = false;
    }
}

function triggerDownload(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename || 'berkas-unduhan';
    link.setAttribute('download', filename || 'berkas-unduhan');
    link.target = '_self';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - FILE VIEWER LIGHTBOX
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-viewer-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(16px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    box-sizing: border-box;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-viewer {
    position: relative;
    width: 100%;
    max-width: 1100px;
    height: 88vh;
    background: var(--hcis-white);
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 24px 60px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition-property: var(--hcis-transition-props), border-radius, height;
    transition-duration: 250ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-viewer.is-fullscreen {
    max-width: 100vw;
    height: 100vh;
    border-radius: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   HEADER TOOLBAR
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-viewer__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.25rem;
    background: var(--hcis-white);
    border-bottom: 1px solid rgba(226, 232, 240, 0.95);
    z-index: 10;
    gap: 1rem;
}

.hcis-viewer__title-group {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
}

.hcis-viewer__icon-badge {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    flex-shrink: 0;
}

.hcis-viewer__icon-badge.is-pdf { background: var(--hcis-red-50); color: var(--hcis-red-600); }
.hcis-viewer__icon-badge.is-image { background: var(--hcis-emerald-50); color: var(--hcis-emerald-600); }
.hcis-viewer__icon-badge.is-video { background: var(--hcis-violet-50); color: var(--hcis-violet-600); }
.hcis-viewer__icon-badge.is-audio { background: var(--hcis-amber-50); color: var(--hcis-amber-600); }

.hcis-viewer__meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hcis-viewer__filename {
    font-size: 0.9375rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-viewer__submeta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 1px;
}

.hcis-viewer__filesize {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
}

.hcis-viewer__ext-tag {
    font-size: 0.625rem;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 4px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.hcis-viewer__index-badge {
    font-size: 0.6875rem;
    font-weight: 750;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    white-space: nowrap;
}

.hcis-viewer__view-only-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.65625rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(253, 230, 138, 0.8);
    white-space: nowrap;
}

/* Actions */
.hcis-viewer__actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.hcis-viewer__zoom-group {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-100);
    border-radius: 8px;
    padding: 2px;
}

.hcis-viewer__zoom-text {
    font-size: 0.6875rem;
    font-weight: 750;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-700);
    padding: 0 6px;
    min-width: 42px;
    text-align: center;
}

.hcis-viewer__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2rem;
    padding: 0 0.625rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    font-size: 0.75rem;
    font-weight: 650;
    cursor: pointer;
    text-decoration: none;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-viewer__btn:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-900);
}

.hcis-viewer__btn--primary {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white) !important;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
}

.hcis-viewer__btn--primary:hover {
    background: linear-gradient(135deg, var(--hcis-indigo-700) 0%, var(--hcis-indigo-600) 100%);
}

.hcis-viewer__btn--close {
    background: var(--hcis-red-50);
    color: var(--hcis-red-600);
    width: 2rem;
    padding: 0;
}

.hcis-viewer__btn--close:hover {
    background: var(--hcis-red-100);
}

.hcis-viewer__divider {
    width: 1px;
    height: 1.25rem;
    background: var(--hcis-slate-200);
    margin: 0 4px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MAIN VIEWER STAGE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-viewer__stage {
    position: relative;
    flex: 1;
    min-height: 0;
    background: var(--hcis-slate-900);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* Stage Loading Spinner Overlay */
.hcis-viewer__stage-loader {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(10px);
    z-index: 30;
    pointer-events: none;
}

.hcis-viewer__spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    background: rgba(30, 41, 59, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 1.25rem 1.75rem;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
}

.hcis-viewer__spinner-box .spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
    color: var(--hcis-indigo-400) !important;
}

.hcis-viewer__loader-text {
    color: var(--hcis-white);
    font-size: 0.8125rem;
    font-weight: 750;
    letter-spacing: 0.02em;
}

/* Watermark */
.hcis-viewer__watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 15;
    font-size: 2rem;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.08);
    text-transform: uppercase;
    transform: rotate(-25deg);
    letter-spacing: 0.15em;
    user-select: none;
    text-align: center;
    padding: 2rem;
}

/* Navigation Chevrons */
.hcis-viewer__nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 20;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-viewer__nav-btn:hover:not(:disabled) {
    background: rgba(79, 70, 229, 0.85);
    transform: translateY(-50%) scale(1.1);
}

.hcis-viewer__nav-btn:disabled {
    opacity: 0.2;
    cursor: not-allowed;
}

.hcis-viewer__nav-btn--prev { left: 1rem; }
.hcis-viewer__nav-btn--next { right: 1rem; }

/* Image View */
.hcis-viewer__image-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: auto;
    background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 16px 16px;
}

.hcis-viewer__img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    transition: transform 120ms ease-out;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
    user-select: none;
    -webkit-user-drag: none;
    will-change: transform;
}

.hcis-viewer__img.is-dragging {
    transition: none !important;
}

/* PDF View */
.hcis-viewer__pdf-wrapper {
    width: 100%;
    height: 100%;
}

.hcis-viewer__pdf-iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: var(--hcis-white);
}

/* Video View */
.hcis-viewer__video-wrapper {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-viewer__video {
    max-width: 95%;
    max-height: 95%;
    border-radius: 8px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
}

/* Audio View */
.hcis-viewer__audio-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.hcis-viewer__audio-card {
    background: rgba(30, 41, 59, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
}

.hcis-viewer__audio-icon-box {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
}

.hcis-viewer__audio-title {
    color: var(--hcis-white);
    font-size: 1.05rem;
    font-weight: 800;
    margin: 0;
}

.hcis-viewer__audio-sub {
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
    margin-top: 4px;
}

.hcis-viewer__audio {
    width: 100%;
}

/* Fallback Document View */
.hcis-viewer__fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 2rem;
}

.hcis-viewer__fallback-box {
    background: var(--hcis-white);
    border-radius: 16px;
    padding: 2.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    overflow: hidden;
}

.hcis-viewer__fallback-icon-wrap {
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 16px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    flex-shrink: 0;
}

.hcis-viewer__fallback-title {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    margin: 0 0 0.5rem 0;
}

.hcis-viewer__fallback-sub {
    font-size: 0.8125rem;
    color: var(--hcis-slate-500);
    margin: 0 0 1.5rem 0;
    line-height: 1.45;
    word-break: break-word;
    max-width: 100%;
}

.hcis-viewer__fallback-sub code {
    word-break: break-all;
    max-width: 100%;
}

.hcis-viewer__fallback-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    color: var(--hcis-white);
    font-size: 0.8125rem;
    font-weight: 750;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-viewer__fallback-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45);
}

/* ═══════════════════════════════════════════════════════════════════════════
   FILMSTRIP THUMBNAIL DOCK
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-viewer__filmstrip {
    background: var(--hcis-white);
    border-top: 1px solid rgba(226, 232, 240, 0.95);
    padding: 0.625rem 1.25rem;
    z-index: 10;
}

.hcis-viewer__filmstrip-track {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
}

.hcis-viewer__thumb-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 8px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.71875rem;
    font-weight: 650;
    color: var(--hcis-slate-600);
    cursor: pointer;
    white-space: nowrap;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-viewer__thumb-item:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-100);
}

.hcis-viewer__thumb-item.is-active {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-700);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

/* Transitions */
.hcis-viewer-fade-enter-active,
.hcis-viewer-fade-leave-active {
    transition: opacity 200ms ease;
}

.hcis-viewer-fade-enter-from,
.hcis-viewer-fade-leave-to {
    opacity: 0;
}

/* Mobile Floating Controls for Image */
.hcis-viewer__mobile-fab {
    position: absolute;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    padding: 3px 6px;
    align-items: center;
    gap: 4px;
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.5);
    z-index: 25;
}

.hcis-viewer__mobile-fab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: var(--hcis-white);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-viewer__mobile-fab-btn:active {
    background: rgba(79, 70, 229, 0.9);
    transform: scale(0.92);
}

.hcis-viewer__mobile-fab-zoom {
    font-size: 0.6875rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-white);
    padding: 0 4px;
    min-width: 38px;
    text-align: center;
}

.hcis-viewer__mobile-fab-divider {
    width: 1px;
    height: 1.1rem;
    background: rgba(255, 255, 255, 0.2);
    margin: 0 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEWPORT (FULL-SCREEN EXPERIENCE)
   ═══════════════════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
    .hcis-viewer-overlay {
        padding: 0 !important;
    }

    .hcis-viewer {
        width: 100vw !important;
        max-width: 100vw !important;
        min-width: 100vw !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        min-height: 100dvh !important;
        border-radius: 0 !important;
        border: none !important;
        margin: 0 !important;
    }

    .hcis-viewer__header {
        padding: 0.55rem 0.75rem !important;
        gap: 0.5rem !important;
    }

    .hcis-viewer__title-group {
        gap: 0.5rem !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .hcis-viewer__icon-badge {
        width: 1.85rem !important;
        height: 1.85rem !important;
        border-radius: 6px !important;
    }

    .hcis-viewer__filename {
        font-size: 0.8125rem !important;
        max-width: 130px;
    }

    .hcis-viewer__index-badge {
        font-size: 0.59375rem !important;
        padding: 1px 5px !important;
    }

    .hcis-viewer__view-only-badge {
        display: none !important;
    }

    .hcis-viewer__submeta {
        display: none !important;
    }

    .hcis-viewer__actions {
        gap: 0.25rem !important;
        flex-shrink: 0 !important;
    }

    .hcis-viewer__zoom-group {
        padding: 1px !important;
    }

    .hcis-viewer__zoom-text {
        display: none !important;
    }

    .hcis-viewer__divider {
        display: none !important;
    }

    .hcis-viewer__btn {
        height: 1.85rem !important;
        padding: 0 0.45rem !important;
        font-size: 0.6875rem !important;
        border-radius: 6px !important;
    }

    .hcis-viewer__btn span {
        display: none !important;
    }

    .hcis-viewer__btn--close {
        width: 1.85rem !important;
        padding: 0 !important;
    }

    .hcis-viewer__nav-btn {
        width: 2.15rem !important;
        height: 2.15rem !important;
    }

    .hcis-viewer__nav-btn--prev { left: 0.5rem !important; }
    .hcis-viewer__nav-btn--next { right: 0.5rem !important; }

    .hcis-viewer__filmstrip {
        padding: 0.45rem 0.65rem calc(0.45rem + env(safe-area-inset-bottom, 0px)) 0.65rem !important;
    }

    .hcis-viewer__thumb-item {
        padding: 3px 7px !important;
        font-size: 0.65625rem !important;
        border-radius: 6px !important;
    }

    .hcis-viewer__fallback-box {
        padding: 1.5rem 1.25rem !important;
        margin: 1rem !important;
    }

    .hcis-viewer__audio-card {
        padding: 1.5rem 1.25rem !important;
        margin: 1rem !important;
    }
}
</style>
