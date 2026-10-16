<template>
    <div
        ref="wadah"
        class="hcis-pdf-canvas"
        :class="[
            `is-${modeTampilan}-mode`,
            { 'is-zoomed': zoomScale > 1.05, 'is-dragging': isDragging }
        ]"
        data-testid="hcis-pdf-canvas"
        tabindex="0"
        @touchstart="onTouchStart"
        @touchmove="onTouchMove"
        @touchend="onTouchEnd"
        @touchcancel="onTouchEnd"
        @mousedown="onMouseDown"
        @mousemove="onMouseMove"
        @mouseup="onMouseUp"
        @mouseleave="onMouseUp"
        @wheel="onWheel"
        @dblclick="onDoubleClick"
        @keydown="onKeyDown"
    >
        <!-- State 1: Memuat Dokumen -->
        <div v-if="status === 'memuat'" class="hcis-pdf-canvas__info" role="status">
            <span class="hcis-pdf-canvas__spinner" aria-hidden="true" />
            <span>Memuat dokumen…</span>
        </div>

        <!-- State 2: Gagal Memuat Dokumen -->
        <div v-else-if="status === 'gagal'" class="hcis-pdf-canvas__info is-gagal" role="alert">
            <span>Dokumen tidak bisa ditampilkan di sini.</span>
            <a v-if="url" :href="url" target="_blank" rel="noopener noreferrer" class="hcis-pdf-canvas__tautan">
                Buka di tab baru
            </a>
        </div>

        <!-- State 3: Dokumen Siap - Stage Kanvas (Scroll Container di Mode Vertikal) -->
        <div
            v-show="status === 'siap'"
            ref="wadahStage"
            class="hcis-pdf-canvas__stage"
        >
            <div
                ref="wadahDaftar"
                class="hcis-pdf-canvas__halaman-daftar"
                :class="[
                    modeTampilan === 'flip' ? 'is-flip-mode' : 'is-scroll-mode',
                    { 'is-animating': isPageAnimating }
                ]"
                :style="transformStyle"
            >
                <div
                    v-for="n in jumlahHalaman"
                    v-show="modeTampilan === 'scroll' || n === halamanAktif"
                    :key="n"
                    :ref="(el) => daftarkanHalaman(el, n)"
                    class="hcis-pdf-canvas__halaman"
                    :class="{ 'is-active-flip': modeTampilan === 'flip' && n === halamanAktif }"
                    :data-halaman="n"
                    :style="{
                        aspectRatio: rasioHalaman[n] || rasio,
                        width: modeTampilan === 'flip' ? lebarHalamanFlip[n] : undefined,
                    }"
                >
                    <canvas :ref="(el) => daftarkanKanvas(el, n)" />
                    <span class="hcis-pdf-canvas__nomor">{{ n }} / {{ jumlahHalaman }}</span>
                </div>
            </div>

            <!-- Petunjuk Visual Samping Mode Horizon (Flip) dengan Opacity Kecil -->
            <template v-if="modeTampilan === 'flip'">
                <!-- Panah Kiri (Prev) - Opacity kecil samar-samar -->
                <button
                    v-if="halamanAktif > 1"
                    type="button"
                    class="hcis-pdf-side-arrow is-left"
                    title="Halaman Sebelumnya (Geser/Ketuk)"
                    aria-label="Halaman Sebelumnya"
                    @click.stop="keSebelumnya"
                >
                    <HcisIcon name="chevron-left" size="sm" />
                </button>

                <!-- Panah Kanan (Next) - Opacity kecil samar-samar -->
                <button
                    v-if="halamanAktif < jumlahHalaman"
                    type="button"
                    class="hcis-pdf-side-arrow is-right"
                    title="Halaman Selanjutnya (Geser/Ketuk)"
                    aria-label="Halaman Selanjutnya"
                    @click.stop="keBerikutnya"
                >
                    <HcisIcon name="chevron-right" size="sm" />
                </button>

                <!-- Hint singkat saat masuk mode horizon/flip -->
                <Transition name="hcis-pdf-fade">
                    <div v-if="showFlipHint" class="hcis-pdf-flip-hint">
                        <HcisIcon name="arrow-left-right" size="xs" /><span>Geser atau ketuk panah untuk membalik halaman</span>
                    </div>
                </Transition>
            </template>
        </div>

        <!-- Floating Mobile Controls Dock (Selalu FIXED di dasar layar) -->
        <nav
            v-if="status === 'siap'"
            class="hcis-pdf-fab"
            aria-label="Kontrol Navigasi PDF"
            @touchstart.stop
            @mousedown.stop
            @dblclick.stop
        >
            <!-- Quick Jump Popover -->
            <Transition name="hcis-pdf-popover">
                <div v-if="showJumpPopover" class="hcis-pdf-jump-popover" @click.stop>
                    <div class="hcis-pdf-jump-header">
                        <span>Lompat ke Halaman</span>
                        <button
                            type="button"
                            class="hcis-pdf-jump-close"
                            aria-label="Tutup"
                            @click="showJumpPopover = false"
                        >
                            <HcisIcon name="x-lg" size="2xs" />
                        </button>
                    </div>
                    <div class="hcis-pdf-jump-body">
                        <input
                            type="range"
                            :min="1"
                            :max="jumlahHalaman"
                            v-model.number="targetJump"
                            class="hcis-pdf-jump-slider"
                        />
                        <div class="hcis-pdf-jump-footer">
                            <button
                                type="button"
                                class="hcis-pdf-jump-nav-btn"
                                :disabled="targetJump <= 1"
                                @click="targetJump = Math.max(1, targetJump - 1)"
                            >
                                -1
                            </button>
                            <button
                                type="button"
                                class="hcis-pdf-jump-btn"
                                @click="applyJump"
                            >
                                Buka Hal. {{ targetJump }} / {{ jumlahHalaman }}
                            </button>
                            <button
                                type="button"
                                class="hcis-pdf-jump-nav-btn"
                                :disabled="targetJump >= jumlahHalaman"
                                @click="targetJump = Math.min(jumlahHalaman, targetJump + 1)"
                            >
                                +1
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- 1. Flip Previous Page -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :disabled="halamanAktif <= 1"
                :title="modeTampilan === 'flip' ? 'Halaman Sebelumnya (Flip Prev)' : 'Ke Halaman Sebelumnya'"
                aria-label="Halaman Sebelumnya"
                @click="keSebelumnya"
            >
                <HcisIcon name="chevron-left" size="xs" />
            </button>

            <!-- 2. Page Counter Indicator (Click to Open Quick Jump) -->
            <button
                type="button"
                class="hcis-pdf-fab-page"
                title="Klik untuk lompat halaman"
                aria-label="Pilih Halaman"
                @click="toggleJumpPopover"
            >
                {{ halamanAktif }} / {{ jumlahHalaman }}
            </button>

            <!-- 3. Flip Next Page -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :disabled="halamanAktif >= jumlahHalaman"
                :title="modeTampilan === 'flip' ? 'Halaman Selanjutnya (Flip Next)' : 'Ke Halaman Selanjutnya'"
                aria-label="Halaman Selanjutnya"
                @click="keBerikutnya"
            >
                <HcisIcon name="chevron-right" size="xs" />
            </button>

            <div class="hcis-pdf-fab-divider" />

            <!-- 4. Zoom Out (-) -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :disabled="zoomScale <= 0.6"
                title="Perkecil Tampilan (Zoom Out)"
                aria-label="Perkecil Tampilan"
                @click="zoomOut"
            >
                <HcisIcon name="dash-lg" size="xs" />
            </button>

            <!-- 5. Zoom Percentage Indicator -->
            <span class="hcis-pdf-fab-zoom" title="Tingkat Zoom">{{ Math.round(zoomScale * 100) }}%</span>

            <!-- 6. Zoom In (+) -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :disabled="zoomScale >= 3.0"
                title="Perbesar Tampilan (Zoom In)"
                aria-label="Perbesar Tampilan"
                @click="zoomIn"
            >
                <HcisIcon name="plus-lg" size="xs" />
            </button>

            <div class="hcis-pdf-fab-divider" />

            <!-- 7. Fit to Screen (Paskan Layar) -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :class="{ 'is-active': isFitActive }"
                title="Paskan Layar (Fit)"
                aria-label="Paskan Layar"
                @click="fit"
            >
                <HcisIcon name="aspect-ratio" size="xs" />
            </button>

            <!-- 8. Rotate (Flip Orientasi 90°) -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :class="{ 'is-active': rotationDeg > 0 }"
                title="Putar Orientasi 90° (Flip Rotate)"
                aria-label="Putar Orientasi"
                @click="putar"
            >
                <HcisIcon name="arrow-clockwise" size="xs" />
            </button>

            <!-- 9. Mode Switch (Vertikal vs Horizon) -->
            <button
                type="button"
                class="hcis-pdf-fab-btn"
                :class="{ 'is-active': modeTampilan === 'flip' }"
                :title="modeTampilan === 'flip' ? 'Mode Horizon (Flip). Klik untuk beralih ke Mode Vertikal (Scroll)' : 'Mode Vertikal (Scroll). Klik untuk beralih ke Mode Horizon (Flip)'"
                :aria-label="modeTampilan === 'flip' ? 'Ganti ke Mode Vertikal' : 'Ganti ke Mode Horizon'"
                @click="toggleModeTampilan"
            >
                <HcisIcon :name="modeTampilan === 'flip' ? 'book' : 'distribute-vertical'" size="xs" />
            </button>
        </nav>
    </div>
</template>

<script setup>
/**
 * Penampil PDF responsif berbasis kanvas (PDF.js) untuk peramban mobile/touch:
 * - Default: Mode Vertikal (Scroll Kontinu).
 * - Mode Horizon: Single-Page Flip dengan swipe & indikator panah samping halus (low opacity).
 * - Zoom in/out tajam anti-blur (dynamic high-res rendering via buffer offscreen).
 * - Fit to screen & Flip rotasi 90°.
 * - Floating Control Dock yang selalu FIXED di dasar layar (tidak ikut ter-scroll).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';

const props = defineProps({
    url: { type: String, required: true },
    halamanMulai: { type: Number, default: 1 },
});

const emit = defineEmits(['loaded', 'error']);

// DOM Elements & Canvas Maps
const wadah = ref(null);
const wadahStage = ref(null);
const wadahDaftar = ref(null);
const status = ref('memuat');
const jumlahHalaman = ref(0);
const halamanAktif = ref(1);
const rasio = ref('1 / 1.414');
const rasioHalaman = reactive({});
const lebarHalamanFlip = reactive({});
const kanvas = {};
const elemenHalaman = {};
const sudahDigambar = new Set();
const skalaTerakhir = {};
const tugasRender = {};

// Transform & Navigation States
const zoomScale = ref(1);
const panX = ref(0);
const panY = ref(0);
const rotationDeg = ref(0);
// Default: Mode Vertikal (Scroll Kontinu) sesuai permintaan user
const modeTampilan = ref('scroll');
const showJumpPopover = ref(false);
const targetJump = ref(1);
const isPageAnimating = ref(false);
const showFlipHint = ref(false);
let flipHintTimer = null;

// Gesture States
const isDragging = ref(false);
const isPinching = ref(false);
let touchStartDistance = 0;
let touchStartZoom = 1;
let touchStartX = 0;
let touchStartY = 0;
let touchStartPanX = 0;
let touchStartPanY = 0;
let touchStartScrollTop = 0;
let lastTapTime = 0;
let lastTapX = 0;
let lastTapY = 0;

let mouseStartX = 0;
let mouseStartY = 0;
let mouseStartPanX = 0;
let mouseStartPanY = 0;
let mouseStartScrollTop = 0;

let dokumen = null;
let pengamat = null;
let tugasMuat = null;
let resizeObserver = null;
let renderZoomTimer = null;

const isFitActive = computed(() => {
    return zoomScale.value === 1 && panX.value === 0 && panY.value === 0 && rotationDeg.value === 0;
});

const transformStyle = computed(() => {
    const isScroll = modeTampilan.value === 'scroll';
    return {
        transform: `translate3d(${panX.value}px, ${isScroll ? 0 : panY.value}px, 0) scale(${zoomScale.value})`,
        transformOrigin: isScroll ? 'top center' : 'center center',
        transition: isDragging.value || isPinching.value ? 'none' : 'transform 200ms cubic-bezier(0.16, 1, 0.3, 1)',
        willChange: 'transform',
    };
});

function daftarkanKanvas(el, n) {
    if (!el) return;
    kanvas[n] = el;
}

function daftarkanHalaman(el, n) {
    if (!el) return;
    elemenHalaman[n] = el;
    pengamat?.observe(el);
}

async function muatPdfjs() {
    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const worker = await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url');
    pdfjs.GlobalWorkerOptions.workerSrc = worker.default;
    return pdfjs;
}

async function hitungUkuranHalaman(n) {
    if (!dokumen || !wadah.value) return;
    try {
        const halaman = await dokumen.getPage(n);
        const rotasi = (halaman.rotate + rotationDeg.value) % 360;
        const dasar = halaman.getViewport({ scale: 1, rotation: rotasi });
        const wadahLebar = wadah.value.clientWidth || 360;
        const wadahTinggi = wadah.value.clientHeight || 600;

        const maxW = Math.max(120, wadahLebar - 24);
        const maxH = Math.max(120, wadahTinggi - 120);

        let targetW;
        if (dasar.width / dasar.height > maxW / maxH) {
            targetW = maxW;
        } else {
            targetW = maxH * (dasar.width / dasar.height);
        }
        lebarHalamanFlip[n] = `${Math.floor(targetW)}px`;
        rasioHalaman[n] = `${dasar.width} / ${dasar.height}`;
    } catch {
        // Abaikan kegagalan kalkulasi minor
    }
}

/**
 * Menggambar halaman n ke kanvas dengan resolusi tajam (anti-blur).
 * Jika sedang di-zoom atau dirotasi, kanvas di-render secara native
 * pada skala dan sudut rotasi tersebut tanpa distorsi CSS matrix.
 */
async function gambar(n, forceZoom = null) {
    if (!dokumen || !kanvas[n]) return;

    const currentZoom = forceZoom !== null ? forceZoom : (n === halamanAktif.value ? zoomScale.value : 1.0);
    const cacheKey = `${currentZoom}_${rotationDeg.value}`;

    // Hindari render ulang identik jika sudah tajam pada skala zoom dan rotasi yang sama
    if (skalaTerakhir[n] === cacheKey && sudahDigambar.has(n)) {
        return;
    }

    try {
        const halaman = await dokumen.getPage(n);
        const rotasi = (halaman.rotate + rotationDeg.value) % 360;
        const dasar = halaman.getViewport({ scale: 1, rotation: rotasi });
        rasioHalaman[n] = `${dasar.width} / ${dasar.height}`;

        const wadahLebar = wadah.value?.clientWidth || 360;
        const wadahTinggi = wadah.value?.clientHeight || 600;

        let skala;
        if (modeTampilan.value === 'flip') {
            const skalaW = (wadahLebar - 24) / dasar.width;
            const skalaH = (wadahTinggi - 120) / dasar.height;
            skala = Math.min(skalaW, skalaH);
            await hitungUkuranHalaman(n);
        } else {
            const elLebar = elemenHalaman[n]?.clientWidth;
            const lebar = elLebar && elLebar > 0 ? elLebar : wadahLebar;
            skala = (lebar - 24) / dasar.width;
        }

        // Hitung skala render tajam: perhitungkan zoom aktif dan rasio piksel layar
        const rapat = Math.min(window.devicePixelRatio || 1, 2.5);
        let totalSkala = Math.max(skala, 0.3) * currentZoom * rapat;

        // Batasi resolusi maksimal agar tidak melebihi alokasi memori kanvas HP (maksimal 2400px)
        const lebarEstimasi = dasar.width * totalSkala;
        const tinggiEstimasi = dasar.height * totalSkala;
        if (lebarEstimasi > 2400 || tinggiEstimasi > 2400) {
            const faktorBatas = Math.min(2400 / lebarEstimasi, 2400 / tinggiEstimasi);
            totalSkala *= faktorBatas;
        }

        const viewport = halaman.getViewport({ scale: totalSkala, rotation: rotasi });

        const c = kanvas[n];
        if (!c) {
            sudahDigambar.delete(n);
            delete skalaTerakhir[n];
            return;
        }

        // Batalkan tugas render sebelumnya pada halaman ini jika masih berjalan
        if (tugasRender[n]) {
            try {
                tugasRender[n].cancel();
            } catch {
                // Abaikan
            }
            delete tugasRender[n];
        }

        const isReRender = Boolean(skalaTerakhir[n] && skalaTerakhir[n] !== cacheKey);

        if (isReRender) {
            // Gunakan kanvas latar belakang (offscreen) agar layar tidak berkedip kosong saat re-render tajam
            const offscreen = document.createElement('canvas');
            offscreen.width = Math.floor(viewport.width);
            offscreen.height = Math.floor(viewport.height);
            const ctx = offscreen.getContext('2d');
            const renderTask = halaman.render({ canvasContext: ctx, viewport });
            tugasRender[n] = renderTask;

            await renderTask.promise;

            // Salin buffer tajam ke kanvas tampilan
            c.width = offscreen.width;
            c.height = offscreen.height;
            c.style.width = '100%';
            c.style.height = 'auto';
            c.getContext('2d').drawImage(offscreen, 0, 0);
        } else {
            // Render awal langsung pada kanvas
            c.width = Math.floor(viewport.width);
            c.height = Math.floor(viewport.height);
            c.style.width = '100%';
            c.style.height = 'auto';

            const renderTask = halaman.render({ canvasContext: c.getContext('2d'), viewport });
            tugasRender[n] = renderTask;

            await renderTask.promise;
        }

        sudahDigambar.add(n);
        skalaTerakhir[n] = cacheKey;
    } catch (e) {
        if (e?.name !== 'RenderingCancelledException') {
            sudahDigambar.delete(n);
            delete skalaTerakhir[n];
        }
    } finally {
        delete tugasRender[n];
    }
}

/**
 * Re-render halaman aktif saat zoom berubah (anti-blur debounced)
 */
function jadwalkanRenderUlangZoom() {
    if (isPinching.value) return;
    clearTimeout(renderZoomTimer);
    renderZoomTimer = setTimeout(() => {
        if (dokumen && status.value === 'siap' && !isPinching.value) {
            renderUlangHalamanAktif();
        }
    }, 160);
}

async function renderUlangHalamanAktif() {
    if (!dokumen) return;
    if (modeTampilan.value === 'flip') {
        await gambar(halamanAktif.value, zoomScale.value);
    } else {
        await gambar(halamanAktif.value, zoomScale.value);
        if (halamanAktif.value + 1 <= jumlahHalaman.value) {
            gambar(halamanAktif.value + 1, zoomScale.value);
        }
        if (halamanAktif.value - 1 >= 1) {
            gambar(halamanAktif.value - 1, zoomScale.value);
        }
    }
}

// Pantau perubahan zoomScale untuk otomatis re-render tajam
watch(zoomScale, () => {
    jadwalkanRenderUlangZoom();
});

// ─── NAVIGASI FLIP / SCROLL HALAMAN ─────────────────────────────────
async function bukaHalaman(n) {
    if (n < 1 || n > jumlahHalaman.value) return;
    halamanAktif.value = n;
    targetJump.value = n;
    fit();

    if (modeTampilan.value === 'flip') {
        isPageAnimating.value = true;
        setTimeout(() => {
            isPageAnimating.value = false;
        }, 220);
        await nextTick();
        await hitungUkuranHalaman(n);
        await gambar(n, 1.0);
        // Pre-render halaman sekitar pada skala 1.0 untuk navigasi cepat
        if (n + 1 <= jumlahHalaman.value) gambar(n + 1, 1.0);
        if (n - 1 >= 1) gambar(n - 1, 1.0);
    } else {
        await nextTick();
        await gambar(n, 1.0);
        elemenHalaman[n]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function keSebelumnya() {
    if (halamanAktif.value > 1) {
        bukaHalaman(halamanAktif.value - 1);
    }
}

function keBerikutnya() {
    if (halamanAktif.value < jumlahHalaman.value) {
        bukaHalaman(halamanAktif.value + 1);
    }
}

function toggleJumpPopover() {
    showJumpPopover.value = !showJumpPopover.value;
    if (showJumpPopover.value) {
        targetJump.value = halamanAktif.value;
    }
}

function applyJump() {
    showJumpPopover.value = false;
    bukaHalaman(targetJump.value);
}

function tampilkanHintFlip() {
    showFlipHint.value = true;
    clearTimeout(flipHintTimer);
    flipHintTimer = setTimeout(() => {
        showFlipHint.value = false;
    }, 3200);
}

// ─── KONTROL ZOOM, FIT & ROTATE ─────────────────────────────────────
function zoomIn() {
    const cur = zoomScale.value;
    if (cur < 1.0) zoomScale.value = 1.0;
    else if (cur < 1.25) zoomScale.value = 1.25;
    else if (cur < 1.5) zoomScale.value = 1.5;
    else if (cur < 2.0) zoomScale.value = 2.0;
    else if (cur < 2.5) zoomScale.value = 2.5;
    else zoomScale.value = 3.0;
}

function zoomOut() {
    const cur = zoomScale.value;
    if (cur > 2.5) zoomScale.value = 2.5;
    else if (cur > 2.0) zoomScale.value = 2.0;
    else if (cur > 1.5) zoomScale.value = 1.5;
    else if (cur > 1.25) zoomScale.value = 1.25;
    else if (cur > 1.0) {
        zoomScale.value = 1.0;
        panX.value = 0;
        panY.value = 0;
    } else if (cur > 0.75) {
        zoomScale.value = 0.75;
    } else {
        zoomScale.value = 0.6;
    }
}

function fit() {
    const oldZoom = zoomScale.value;
    zoomScale.value = 1.0;
    panX.value = 0;
    panY.value = 0;

    if (modeTampilan.value === 'scroll' && wadahStage.value && oldZoom > 1.05) {
        wadahStage.value.scrollTop = Math.max(0, Math.round(wadahStage.value.scrollTop / oldZoom));
    }
}

async function putar() {
    rotationDeg.value = (rotationDeg.value + 90) % 360;
    panX.value = 0;
    panY.value = 0;

    // Perbarui rasio default dokumen berdasarkan rotasi baru
    if (dokumen) {
        try {
            const pertama = await dokumen.getPage(1);
            const rot = (pertama.rotate + rotationDeg.value) % 360;
            const vp = pertama.getViewport({ scale: 1, rotation: rot });
            rasio.value = `${vp.width} / ${vp.height}`;
        } catch {
            // Abaikan kegagalan kalkulasi rasio dasar
        }
    }

    // Bersihkan penanda render agar digambar ulang pada sudut rotasi baru
    sudahDigambar.clear();
    Object.keys(skalaTerakhir).forEach((k) => delete skalaTerakhir[k]);

    await nextTick();
    if (modeTampilan.value === 'flip') {
        await hitungUkuranHalaman(halamanAktif.value);
        await gambar(halamanAktif.value, zoomScale.value);
        if (halamanAktif.value + 1 <= jumlahHalaman.value) gambar(halamanAktif.value + 1, 1.0);
        if (halamanAktif.value - 1 >= 1) gambar(halamanAktif.value - 1, 1.0);
    } else {
        await gambar(halamanAktif.value, zoomScale.value);
        if (halamanAktif.value + 1 <= jumlahHalaman.value) gambar(halamanAktif.value + 1, zoomScale.value);
        if (halamanAktif.value - 1 >= 1) gambar(halamanAktif.value - 1, zoomScale.value);
    }
}

async function toggleModeTampilan() {
    modeTampilan.value = modeTampilan.value === 'flip' ? 'scroll' : 'flip';
    fit();
    await nextTick();
    if (modeTampilan.value === 'flip') {
        tampilkanHintFlip();
        await hitungUkuranHalaman(halamanAktif.value);
        await gambar(halamanAktif.value, 1.0);
    } else {
        showFlipHint.value = false;
        elemenHalaman[halamanAktif.value]?.scrollIntoView({ block: 'start' });
        await gambar(halamanAktif.value, 1.0);
    }
}

// ─── GESTURE TOUCH (PINCH-TO-ZOOM, PAN & SWIPE FLIP) ────────────────
function getDistance(t1, t2) {
    const dx = t1.clientX - t2.clientX;
    const dy = t1.clientY - t2.clientY;
    return Math.hypot(dx, dy);
}

function handleDoubleAction(clientX = null, clientY = null) {
    if (zoomScale.value > 1.05) {
        // Jika sedang di-zoom (zoom in), double-klik/double-tap mengembalikan ke tampilan fit normal
        fit();
    } else {
        // Jika sedang normal/fit, double-klik/double-tap melakukan zoom in ke 2.0x
        zoomScale.value = 2.0;

        if (modeTampilan.value === 'flip') {
            // Mode Flip / Horizon: pusatkan zoom pada titik yang diketuk
            if (clientX != null && clientY != null && wadah.value) {
                const rect = wadah.value.getBoundingClientRect();
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                panX.value = Math.round((centerX - (clientX - rect.left)) * 0.75);
                panY.value = Math.round((centerY - (clientY - rect.top)) * 0.75);
            }
        } else {
            // Mode Scroll / Vertikal: pusatkan scroll vertikal dan pan horizontal pada area yang diketuk
            panY.value = 0;
            if (clientX != null && clientY != null && wadahStage.value) {
                const rect = wadahStage.value.getBoundingClientRect();
                const centerX = rect.width / 2;
                panX.value = Math.round((centerX - (clientX - rect.left)) * 0.75);

                const currentScrollTop = wadahStage.value.scrollTop;
                const relativeY = clientY - rect.top;
                const targetScrollTop = Math.round((currentScrollTop + relativeY) * 2.0 - relativeY);
                wadahStage.value.scrollTop = Math.max(0, targetScrollTop);
            }
        }
    }
}

function onDoubleClick(e) {
    e.preventDefault();
    const clientX = e.clientX ?? (e.touches && e.touches[0]?.clientX);
    const clientY = e.clientY ?? (e.touches && e.touches[0]?.clientY);
    handleDoubleAction(clientX, clientY);
}

function onTouchStart(e) {
    if (e.touches.length === 2) {
        isPinching.value = true;
        isDragging.value = false;
        touchStartDistance = getDistance(e.touches[0], e.touches[1]);
        touchStartZoom = zoomScale.value;
    } else if (e.touches.length === 1) {
        const t = e.touches[0];
        touchStartX = t.clientX;
        touchStartY = t.clientY;
        touchStartPanX = panX.value;
        touchStartPanY = panY.value;
        touchStartScrollTop = wadahStage.value?.scrollTop || 0;

        // Double tap check (jendela 350ms & jarak ketukan < 30px)
        const now = Date.now();
        const dist = Math.hypot(t.clientX - lastTapX, t.clientY - lastTapY);
        if (now - lastTapTime < 350 && dist < 30) {
            e.preventDefault();
            handleDoubleAction(t.clientX, t.clientY);
            lastTapTime = 0;
            return;
        }
        lastTapTime = now;
        lastTapX = t.clientX;
        lastTapY = t.clientY;

        if (zoomScale.value > 1.05) {
            isDragging.value = true;
        }
    }
}

function onTouchMove(e) {
    if (isPinching.value && e.touches.length === 2) {
        e.preventDefault();
        const dist = getDistance(e.touches[0], e.touches[1]);
        if (touchStartDistance > 0) {
            const ratio = dist / touchStartDistance;
            let targetZoom = touchStartZoom * ratio;
            targetZoom = Math.min(3.0, Math.max(0.6, targetZoom));
            zoomScale.value = Math.round(targetZoom * 100) / 100;
        }
    } else if (isDragging.value && e.touches.length === 1 && zoomScale.value > 1.05) {
        const t = e.touches[0];
        const dx = t.clientX - touchStartX;
        const dy = t.clientY - touchStartY;

        // Batalkan double tap jika jari bergerak lebih dari 10px (indikasi scroll/drag)
        if (Math.hypot(dx, dy) > 10) {
            lastTapTime = 0;
        }

        const wadahLebar = wadah.value?.clientWidth || 360;
        const maxPanX = (wadahLebar * (zoomScale.value - 1)) / 1.5 + 80;
        const targetX = touchStartPanX + dx;
        panX.value = Math.max(-maxPanX, Math.min(maxPanX, targetX));

        if (modeTampilan.value === 'scroll') {
            if (wadahStage.value) {
                wadahStage.value.scrollTop = touchStartScrollTop - dy;
            }
        } else {
            e.preventDefault();
            const wadahTinggi = wadah.value?.clientHeight || 600;
            const maxPanY = (wadahTinggi * (zoomScale.value - 1)) / 1.5 + 80;
            const targetY = touchStartPanY + dy;
            panY.value = Math.max(-maxPanY, Math.min(maxPanY, targetY));
        }
    }
}

function onTouchEnd(e) {
    if (isPinching.value && e.touches.length < 2) {
        isPinching.value = false;
        if (zoomScale.value < 0.95) {
            fit();
        } else {
            jadwalkanRenderUlangZoom();
        }
    }
    if (isDragging.value && e.touches.length === 0) {
        isDragging.value = false;
    }

    // Swipe horizontal untuk flip halaman di mode horizon (hanya saat zoom fit)
    if (modeTampilan.value === 'flip' && zoomScale.value <= 1.05 && e.changedTouches.length === 1) {
        const t = e.changedTouches[0];
        const deltaX = t.clientX - touchStartX;
        const deltaY = t.clientY - touchStartY;
        if (Math.abs(deltaX) > 45 && Math.abs(deltaX) > Math.abs(deltaY) * 1.3) {
            if (deltaX < 0) {
                keBerikutnya();
            } else {
                keSebelumnya();
            }
        }
    }
}

// ─── MOUSE DRAG & WHEEL ZOOM (DESKTOP/EMULASI) ──────────────────────
function onMouseDown(e) {
    if (zoomScale.value > 1.05) {
        isDragging.value = true;
        mouseStartX = e.clientX;
        mouseStartY = e.clientY;
        mouseStartPanX = panX.value;
        mouseStartPanY = panY.value;
        mouseStartScrollTop = wadahStage.value?.scrollTop || 0;
    }
}

function onMouseMove(e) {
    if (isDragging.value && zoomScale.value > 1.05) {
        const dx = e.clientX - mouseStartX;
        const dy = e.clientY - mouseStartY;

        const wadahLebar = wadah.value?.clientWidth || 360;
        const maxPanX = (wadahLebar * (zoomScale.value - 1)) / 1.5 + 80;
        panX.value = Math.max(-maxPanX, Math.min(maxPanX, mouseStartPanX + dx));

        if (modeTampilan.value === 'scroll') {
            if (wadahStage.value) {
                wadahStage.value.scrollTop = mouseStartScrollTop - dy;
            }
        } else {
            const wadahTinggi = wadah.value?.clientHeight || 600;
            const maxPanY = (wadahTinggi * (zoomScale.value - 1)) / 1.5 + 80;
            panY.value = Math.max(-maxPanY, Math.min(maxPanY, mouseStartPanY + dy));
        }
    }
}

function onMouseUp() {
    isDragging.value = false;
}

function onWheel(e) {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        if (e.deltaY < 0) zoomIn();
        else zoomOut();
    } else if (zoomScale.value > 1.05 && modeTampilan.value === 'flip') {
        panX.value -= e.deltaX;
        panY.value -= e.deltaY;
    }
}

function onKeyDown(e) {
    if (status.value !== 'siap') return;
    if (e.key === 'ArrowLeft') keSebelumnya();
    else if (e.key === 'ArrowRight') keBerikutnya();
    else if (e.key === '+' || e.key === '=') zoomIn();
    else if (e.key === '-' || e.key === '_') zoomOut();
    else if (e.key === '0' || e.key.toLowerCase() === 'f') fit();
    else if (e.key.toLowerCase() === 'r') putar();
}

// ─── SIKLUS HIDUP DAN INISIALISASI ──────────────────────────────────
async function muat() {
    status.value = 'memuat';
    sudahDigambar.clear();
    Object.keys(skalaTerakhir).forEach((k) => delete skalaTerakhir[k]);
    Object.values(tugasRender).forEach((t) => {
        try {
            t.cancel();
        } catch {
            // Abaikan
        }
    });
    fit();
    rotationDeg.value = 0;
    try {
        const pdfjs = await muatPdfjs();
        let seOrigin = false;
        try {
            seOrigin = new URL(props.url, window.location.href).origin === window.location.origin;
        } catch {
            seOrigin = false;
        }
        tugasMuat = pdfjs.getDocument({ url: props.url, withCredentials: seOrigin });
        dokumen = await tugasMuat.promise;
        jumlahHalaman.value = dokumen.numPages;

        const pertama = await dokumen.getPage(1);
        const rotasi = (pertama.rotate + rotationDeg.value) % 360;
        const vp = pertama.getViewport({ scale: 1, rotation: rotasi });
        rasio.value = `${vp.width} / ${vp.height}`;

        status.value = 'siap';
        emit('loaded', { jumlahHalaman: dokumen.numPages });

        await nextTick();
        const tujuan = Math.min(Math.max(1, props.halamanMulai || 1), dokumen.numPages);
        halamanAktif.value = tujuan;
        targetJump.value = tujuan;
        await hitungUkuranHalaman(tujuan);
        await gambar(tujuan, 1.0);

        if (modeTampilan.value === 'flip') {
            if (tujuan + 1 <= dokumen.numPages) gambar(tujuan + 1, 1.0);
            if (tujuan - 1 >= 1) gambar(tujuan - 1, 1.0);
        } else {
            elemenHalaman[tujuan]?.scrollIntoView({ block: 'start' });
        }
    } catch (e) {
        status.value = 'gagal';
        emit('error', e);
    }
}

onMounted(() => {
    const rootEl = wadahStage.value || wadah.value;
    pengamat = new IntersectionObserver(
        (entri) => {
            entri.forEach((e) => {
                if (e.isIntersecting) {
                    const n = Number(e.target.dataset.halaman);
                    gambar(n, modeTampilan.value === 'scroll' ? zoomScale.value : 1.0);
                    if (modeTampilan.value === 'scroll') {
                        halamanAktif.value = n;
                        targetJump.value = n;
                    }
                }
            });
        },
        { root: rootEl, rootMargin: '400px 0px', threshold: 0.1 },
    );
    Object.values(elemenHalaman).forEach((el) => pengamat.observe(el));

    if (typeof ResizeObserver !== 'undefined' && wadah.value) {
        resizeObserver = new ResizeObserver(() => {
            if (dokumen && status.value === 'siap') {
                hitungUkuranHalaman(halamanAktif.value);
            }
        });
        resizeObserver.observe(wadah.value);
    }

    muat();
});

watch(() => props.url, () => {
    dokumen?.destroy?.();
    dokumen = null;
    muat();
});

onBeforeUnmount(() => {
    clearTimeout(renderZoomTimer);
    clearTimeout(flipHintTimer);
    pengamat?.disconnect();
    resizeObserver?.disconnect();
    Object.values(tugasRender).forEach((t) => {
        try {
            t.cancel();
        } catch {
            // Abaikan
        }
    });
    tugasMuat?.destroy?.();
    dokumen?.destroy?.();
});
</script>

<style scoped>
.hcis-pdf-canvas {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden !important; /* WAJIB overflow hidden agar dock kontrol tidak ikut ke-scroll */
    background: #090d16;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    outline: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

.hcis-pdf-canvas.is-zoomed {
    cursor: grab;
}

.hcis-pdf-canvas.is-zoomed.is-dragging {
    cursor: grabbing;
}

/* Wadah panggung yang mengontrol scroll di mode vertikal */
.hcis-pdf-canvas__stage {
    position: relative;
    flex: 1;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    touch-action: manipulation;
}

.hcis-pdf-canvas.is-scroll-mode .hcis-pdf-canvas__stage {
    display: block;
    height: 100%;
    width: 100%;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}

.hcis-pdf-canvas__halaman-daftar {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-pdf-canvas__halaman-daftar.is-scroll-mode {
    flex-direction: column;
    gap: 1rem;
    max-width: 900px;
    margin: 0 auto;
    padding: 1rem 0.75rem calc(5.25rem + env(safe-area-inset-bottom, 0px));
    height: auto;
}

.hcis-pdf-canvas__halaman-daftar.is-flip-mode {
    padding: 0.75rem 0.75rem calc(3.5rem + env(safe-area-inset-bottom, 0px));
    box-sizing: border-box;
}

.hcis-pdf-canvas__halaman-daftar.is-animating {
    animation: hcis-pdf-page-flip 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes hcis-pdf-page-flip {
    from {
        opacity: 0.5;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.hcis-pdf-canvas__halaman {
    position: relative;
    background: #ffffff;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45);
    border-radius: 6px;
    overflow: hidden;
    margin: 0 auto;
    transition: box-shadow 0.2s ease;
}

.hcis-pdf-canvas.is-scroll-mode .hcis-pdf-canvas__halaman {
    width: 100%;
}

.hcis-pdf-canvas__halaman canvas {
    display: block;
    width: 100%;
    height: auto;
}

.hcis-pdf-canvas__nomor {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #ffffff;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(var(--hcis-glass-blur-sm));
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

/* ═══════════════════════════════════════════════════════════════════════════
   FLOATING ACTION BAR (MOBILE DOCK: ZOOM, FLIP, FIT, ROTATE) - SELALU FIXED
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-pdf-fab {
    position: absolute;
    bottom: calc(0.85rem + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 9999px;
    padding: 4px 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.08);
    z-index: 50;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    max-width: calc(100vw - 1rem);
    box-sizing: border-box;
}

.hcis-pdf-fab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    cursor: pointer;
    transition: background 150ms ease, transform 120ms ease, color 150ms ease;
    flex-shrink: 0;
    padding: 0;
}

.hcis-pdf-fab-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.22);
}

.hcis-pdf-fab-btn:active:not(:disabled) {
    background: var(--hcis-brand-600, #4f46e5);
    transform: scale(0.9);
}

.hcis-pdf-fab-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.hcis-pdf-fab-btn.is-active {
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.6);
}

.hcis-pdf-fab-page {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, monospace);
    color: #ffffff;
    padding: 0.2rem 0.45rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    cursor: pointer;
    white-space: nowrap;
    transition: background 150ms ease;
}

.hcis-pdf-fab-page:hover {
    background: rgba(255, 255, 255, 0.2);
}

.hcis-pdf-fab-zoom {
    font-size: 0.6875rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    color: #ffffff;
    min-width: 34px;
    text-align: center;
    white-space: nowrap;
}

.hcis-pdf-fab-divider {
    width: 1px;
    height: 1.1rem;
    background: rgba(255, 255, 255, 0.18);
    margin: 0 2px;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   PETUNJUK PANAH SAMPING (MODE HORIZON / FLIP) DENGAN OPACITY KECIL
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-pdf-side-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0.32; /* Opacity kecil sesuai permintaan user agar tidak mengganggu bacaan */
    cursor: pointer;
    backdrop-filter: blur(var(--hcis-glass-blur-sm));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur-sm));
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
    z-index: 25;
    transition: opacity 200ms ease, background 200ms ease, transform 150ms ease;
    -webkit-tap-highlight-color: transparent;
}

.hcis-pdf-side-arrow:hover,
.hcis-pdf-side-arrow:active {
    opacity: 0.85; /* Naikkan kontras saat disentuh */
    background: rgba(79, 70, 229, 0.75);
    transform: translateY(-50%) scale(1.08);
}

.hcis-pdf-side-arrow.is-left {
    left: 0.45rem;
}

.hcis-pdf-side-arrow.is-right {
    right: 0.45rem;
}

.hcis-pdf-flip-hint {
    position: absolute;
    top: 0.75rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 9999px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #e2e8f0;
    font-size: 0.6875rem;
    font-weight: 600;
    pointer-events: none;
    z-index: 28;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
    opacity: 0.75;
}

.hcis-pdf-fade-enter-active,
.hcis-pdf-fade-leave-active {
    transition: opacity 300ms ease;
}

.hcis-pdf-fade-enter-from,
.hcis-pdf-fade-leave-to {
    opacity: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   QUICK JUMP POPOVER
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-pdf-jump-popover {
    position: absolute;
    bottom: calc(100% + 0.6rem);
    left: 50%;
    transform: translateX(-50%);
    width: 250px;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    box-shadow: 0 14px 36px rgba(0, 0, 0, 0.65);
    color: #ffffff;
    z-index: 55;
}

.hcis-pdf-jump-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #e2e8f0;
}

.hcis-pdf-jump-close {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.hcis-pdf-jump-close:hover {
    color: #ffffff;
}

.hcis-pdf-jump-slider {
    width: 100%;
    accent-color: var(--hcis-brand-500, #6366f1);
    cursor: pointer;
}

.hcis-pdf-jump-footer {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.6rem;
}

.hcis-pdf-jump-nav-btn {
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 600;
    cursor: pointer;
}

.hcis-pdf-jump-nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.hcis-pdf-jump-btn {
    flex: 1;
    padding: 0.35rem 0.5rem;
    background: var(--hcis-brand-600, #4f46e5);
    border: none;
    border-radius: 6px;
    color: #ffffff;
    font-size: 0.71875rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-pdf-jump-btn:active {
    background: var(--hcis-brand-700, #4338ca);
}

.hcis-pdf-popover-enter-active,
.hcis-pdf-popover-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.hcis-pdf-popover-enter-from,
.hcis-pdf-popover-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(8px);
}

/* ═══════════════════════════════════════════════════════════════════════════
   SPINNER & FALLBACK INFO
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-pdf-canvas__info {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    min-height: 60%;
    color: #94a3b8;
    font-size: 0.9rem;
    text-align: center;
    margin: auto;
}

.hcis-pdf-canvas__spinner {
    width: 2rem;
    height: 2rem;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top-color: var(--hcis-brand-500, #6366f1);
    border-radius: 50%;
    animation: hcis-pdf-putar 0.8s linear infinite;
}

.hcis-pdf-canvas__tautan {
    font-weight: 600;
    color: var(--hcis-brand-400, #818cf8);
    text-decoration: underline;
}

@keyframes hcis-pdf-putar {
    to {
        transform: rotate(360deg);
    }
}
</style>
