<template>
    <Teleport :to="teleportTo" :disabled="!teleportTo">
        <Transition name="hcis-dialog-fade" @after-enter="onAfterEnter" @after-leave="onAfterLeave">
            <div
                v-if="modelValue"
                class="hcis-dialog-overlay"
                :class="[
                    `hcis-dialog-overlay--${placement}`,
                    overlayClass,
                ]"
                :style="lapisan !== null ? { zIndex: lapisan } : null"
                role="presentation"
                @click.self="handleOverlayClick"
            >
                <div
                    ref="dialogRef"
                    v-bind="$attrs"
                    class="hcis-dialog"
                    :class="[
                        `hcis-dialog--${size}`,
                        `hcis-dialog--sentiment-${sentiment}`,
                        `hcis-dialog--variant-${variant}`,
                        `hcis-dialog--placement-${placement}`,
                        {
                            'hcis-dialog--glow': glow,
                            'hcis-dialog--has-header': hasHeader,
                            'hcis-dialog--loading': loading,
                            'hcis-dialog--sticky-footer': stickyFooter,
                        },
                        glowSentiment ? `hcis-dialog--glow-${glowSentiment}` : '',
                        customClass,
                    ]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    tabindex="-1"
                >
                    <!-- Loading Bar -->
                    <div v-if="loading" class="hcis-dialog__loading-bar" role="progressbar" aria-label="Memuat...">
                        <div class="hcis-dialog__loading-indicator" />
                    </div>

                    <!-- Header -->
                    <header
                        v-if="hasHeader"
                        class="hcis-dialog__header"
                        :class="{ 'hcis-dialog__header--divider': headerDivider }"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <!-- Mobile Handle Slide Indicator -->
                        <div class="hcis-dialog__mobile-handle-wrap" aria-hidden="true">
                            <div class="hcis-dialog__mobile-handle" />
                        </div>

                        <!-- Pattern Overlays in Header -->
                        <template v-if="pattern !== 'none' && (patternPlacement === 'header' || patternPlacement === 'both')">
                            <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" aria-hidden="true" />
                            <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots" aria-hidden="true" />
                            <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair" aria-hidden="true" />
                            <div v-else-if="pattern === 'stripes'" class="hcis-pattern-stripes" aria-hidden="true" />
                            <div v-else-if="pattern === 'rings'" class="hcis-pattern-rings" aria-hidden="true" />
                            <div v-else-if="pattern === 'hex'" class="hcis-pattern-hex" aria-hidden="true" />
                        </template>

                        <div class="hcis-dialog__header-row">
                            <slot name="header">
                                <div class="hcis-dialog__header-main">
                                    <!-- Jewel Icon -->
                                    <div
                                        v-if="$slots.leading || resolvedIcon"
                                        class="hcis-dialog__icon"
                                        :class="[
                                            `hcis-dialog__icon--${sentiment}`,
                                            `hcis-dialog__icon--variant-${iconVariant}`
                                        ]"
                                    >
                                        <slot name="leading">
                                            <HcisIcon
                                                :name="resolvedIcon"
                                                :animation="iconAnimation"
                                                :size="iconSize"
                                            />
                                        </slot>
                                    </div>

                                    <!-- Title Stack -->
                                    <div class="hcis-dialog__header-text">
                                        <span v-if="kicker" class="hcis-dialog__kicker">{{ kicker }}</span>

                                        <div class="hcis-dialog__title-row">
                                            <h3 :id="titleId" class="hcis-dialog__title">
                                                <slot name="title">{{ title }}</slot>
                                            </h3>

                                            <slot name="badge">
                                                <HcisBadge
                                                    v-if="badge"
                                                    :label="badge"
                                                    :variant="resolvedBadgeVariant"
                                                    :dot="badgeDot"
                                                    :pulse="badgePulse"
                                                    :radar="badgeRadar"
                                                    size="xs"
                                                />
                                            </slot>
                                        </div>

                                        <p v-if="description" class="hcis-dialog__description">
                                            {{ description }}
                                        </p>
                                    </div>
                                </div>
                            </slot>

                            <!-- Close Button -->
                            <button
                                v-if="showClose"
                                type="button"
                                class="hcis-dialog__close"
                                aria-label="Tutup dialog"
                                @click="close"
                            >
                                <HcisIcon name="x-lg" size="xs" />
                            </button>
                        </div>
                    </header>

                    <!-- Body -->
                    <main
                        class="hcis-dialog__body"
                        :class="{ 'hcis-dialog__body--scrollable': scrollable }"
                    >
                        <slot />
                    </main>

                    <!-- Footer (Premium Bilateral Architecture) -->
                    <footer
                        v-if="$slots.footer || $slots['footer-start'] || $slots['footer-left'] || $slots['footer-end'] || $slots['footer-right'] || escHint"
                        class="hcis-dialog__footer"
                        :class="[
                            `hcis-dialog__footer--align-${footerAlign}`,
                            { 'hcis-dialog__footer--sticky': stickyFooter }
                        ]"
                    >
                        <!-- Pattern Overlays in Footer (Inverted Bottom-Left Soft Mask) -->
                        <template v-if="pattern !== 'none' && (patternPlacement === 'footer' || patternPlacement === 'both')">
                            <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-dialog__footer-pattern" aria-hidden="true" />
                            <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots hcis-dialog__footer-pattern" aria-hidden="true" />
                            <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair hcis-dialog__footer-pattern" aria-hidden="true" />
                            <div v-else-if="pattern === 'stripes'" class="hcis-pattern-stripes hcis-dialog__footer-pattern" aria-hidden="true" />
                            <div v-else-if="pattern === 'rings'" class="hcis-pattern-rings hcis-dialog__footer-pattern" aria-hidden="true" />
                            <div v-else-if="pattern === 'hex'" class="hcis-pattern-hex hcis-dialog__footer-pattern" aria-hidden="true" />
                        </template>
                        <!-- Left Zone: Context / Status / Helper / ESC Shortcut -->
                        <div
                            v-if="$slots['footer-start'] || $slots['footer-left'] || escHint"
                            class="hcis-dialog__footer-start"
                        >
                            <slot name="footer-start">
                                <slot name="footer-left">
                                    <span v-if="escHint" class="hcis-dialog__esc-hint">
                                        <kbd class="hcis-dialog__kbd">ESC</kbd>
                                        <span>{{ typeof escHint === 'string' ? escHint : 'Tutup' }}</span>
                                    </span>
                                </slot>
                            </slot>
                        </div>

                        <!-- Right Zone: Primary & Secondary Action Stack -->
                        <div
                            v-if="$slots.footer || $slots['footer-end'] || $slots['footer-right']"
                            class="hcis-dialog__footer-actions"
                        >
                            <slot name="footer">
                                <slot name="footer-end">
                                    <slot name="footer-right" />
                                </slot>
                            </slot>
                        </div>
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
/*
 * Akar komponen ini <Teleport>, bukan satu elemen.
 *
 * Vue tidak bisa mewariskan class/style secara otomatis ke akar seperti itu:
 * ia memperingatkan "Extraneous non-props attributes" lalu MEMBUANG atributnya.
 * Yang dibuang bukan hiasan — HcisDualPaneDialog mengirim `hcis-dual-pane-dialog`
 * dan variabel `--hcis-dual-left-width` lewat jalur ini, dan keduanya dipakai
 * aturan gaya di bawah. Selama atributnya hilang, aturan-aturan itu tidak pernah
 * cocok dengan apa pun: deskripsi dialog tetap tampil di layar sempit dan lebar
 * panel kirinya selalu memakai nilai bawaan.
 *
 * Karena itu pewarisannya dimatikan dan atributnya ditempelkan sendiri ke
 * elemen .hcis-dialog — satu-satunya elemen yang benar-benar mewakili dialog.
 */
defineOptions({ inheritAttrs: false });

import { ref, watch, onUnmounted, computed, nextTick } from 'vue';
import { useScrollLock } from './useScrollLock';
import { useModalFocus } from './useModalFocus';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';


const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    kicker: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    badgeDot: { type: Boolean, default: false },
    badgePulse: { type: Boolean, default: false },
    badgeRadar: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (val) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl', 'full'].includes(val),
    },
    sentiment: {
        type: String,
        default: 'neutral',
        validator: (val) => [
            'neutral', 'none', 'primary', 'brand', 'purple', 'violet',
            'teal', 'cyan', 'danger', 'warning', 'success', 'info', 'obsidian'
        ].includes(val),
    },
    variant: {
        type: String,
        default: 'default',
        validator: (val) => ['default', 'glass', 'hero', 'raised', 'obsidian', 'flat', 'outlined', 'crystal'].includes(val),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (val) => ['none', 'grid', 'dots', 'crosshair', 'stripes', 'rings', 'hex'].includes(val),
    },
    patternPlacement: {
        type: String,
        default: 'header',
        validator: (val) => ['header', 'body', 'both', 'none'].includes(val),
    },
    icon: { type: String, default: '' },
    iconVariant: {
        type: String,
        default: 'glass',
        validator: (val) => ['solid', 'glass', 'soft', 'bordered'].includes(val),
    },
    iconAnimation: { type: String, default: '' },
    iconSize: { type: String, default: 'sm' },
    headerDivider: { type: Boolean, default: true },
    glow: { type: Boolean, default: false },
    glowSentiment: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    scrollable: { type: Boolean, default: true },
    stickyFooter: { type: Boolean, default: false },
    footerAlign: {
        type: String,
        default: 'end',
        validator: (val) => ['start', 'left', 'center', 'end', 'right', 'between'].includes(val),
    },
    escHint: { type: [Boolean, String], default: false },
    placement: {
        type: String,
        default: 'auto',
        validator: (val) => ['auto', 'center', 'bottom'].includes(val),
    },
    closeOnOverlay: { type: Boolean, default: true },
    closeOnEsc: { type: Boolean, default: true },
    showClose: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
    customClass: { type: String, default: '' },
    overlayClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'close', 'open', 'after-enter', 'after-leave']);

const dialogRef = ref(null);
const titleId = computed(() => `hcis-dialog-title-${Math.random().toString(36).substring(2, 9)}`);

const hasHeader = computed(() => {
    return !!(props.title || props.kicker || props.description || props.badge || props.icon || props.showClose);
});

const resolvedIcon = computed(() => {
    if (props.icon) return props.icon;
    switch (props.sentiment) {
        case 'danger': return 'exclamation-triangle';
        case 'warning': return 'exclamation-circle';
        case 'success': return 'check-circle';
        case 'info': return 'info-circle';
        case 'primary':
        case 'brand': return 'shield-check';
        case 'purple':
        case 'violet': return 'gem';
        case 'teal':
        case 'cyan': return 'stars';
        default: return '';
    }
});

const resolvedBadgeVariant = computed(() => {
    if (props.badgeVariant) return props.badgeVariant;
    if (['primary', 'brand'].includes(props.sentiment)) return 'brand';
    if (['purple', 'violet'].includes(props.sentiment)) return 'purple';
    if (['teal', 'cyan'].includes(props.sentiment)) return 'teal';
    if (props.sentiment === 'danger') return 'danger';
    if (props.sentiment === 'warning') return 'warning';
    if (props.sentiment === 'success') return 'success';
    if (props.sentiment === 'info') return 'info';
    return 'neutral';
});

const { kunci: lockScroll, buka: unlockScroll } = useScrollLock();
const fokus = useModalFocus();

const handleKeyDown = (e) => {
    if (props.modelValue && props.closeOnEsc && e.key === 'Escape') {
        e.preventDefault();
        close();
    }
};

const close = () => {
    emit('update:modelValue', false);
    emit('close');
};

const handleOverlayClick = () => {
    if (props.closeOnOverlay) {
        close();
    }
};

const isDragging = ref(false);
const startY = ref(0);
const currentDragY = ref(0);

const onTouchStart = (e) => {
    const touch = e.touches[0];
    startY.value = touch.clientY;
    currentDragY.value = 0;
    isDragging.value = true;
};

const onTouchMove = (e) => {
    if (!isDragging.value) return;
    const touch = e.touches[0];
    const deltaY = touch.clientY - startY.value;

    if (deltaY > 0) {
        currentDragY.value = deltaY;
        if (dialogRef.value) {
            dialogRef.value.style.transform = `translateY(${deltaY}px)`;
            dialogRef.value.style.transition = 'none';
        }
    }
};

const onTouchEnd = () => {
    if (!isDragging.value) return;
    isDragging.value = false;

    if (currentDragY.value > 80) {
        if (dialogRef.value) {
            dialogRef.value.style.transition = 'transform 180ms cubic-bezier(0.4, 0, 0.2, 1)';
            dialogRef.value.style.transform = 'translateY(100vh)';
        }
        setTimeout(() => {
            close();
            setTimeout(() => {
                if (dialogRef.value) {
                    dialogRef.value.style.transform = '';
                    dialogRef.value.style.transition = '';
                }
            }, 50);
        }, 160);
    } else {
        if (dialogRef.value) {
            dialogRef.value.style.transition = 'transform 200ms var(--hcis-ease)';
            dialogRef.value.style.transform = 'translateY(0)';
            setTimeout(() => {
                if (dialogRef.value) {
                    dialogRef.value.style.transform = '';
                    dialogRef.value.style.transition = '';
                }
            }, 200);
        }
    }
    currentDragY.value = 0;
};

/*
 | Tingkat tumpukan dialog ini. `null` selama tertutup supaya tidak ada gaya sebaris yang
 | menempel pada elemen yang tidak dirender.
 |
 | Naik 10 per tingkat, bukan 1: ruang di antaranya dipakai anak-anak overlay yang punya
 | z-index sendiri (menu melayang, tooltip) supaya mereka tidak jatuh ke belakang dialog
 | yang lebih atas.
 */
const lapisan = ref(null);

/*
 | Lapisan berikutnya: SATU tingkat di atas overlay TERTINGGI yang sedang ada, bukan basis
 | ditambah jumlahnya. Menghitung jumlah membuat dua dialog bisa SERI: A (2000) membuka B
 | (2010), A ditutup, B membuka C — C juga 2010, dan karena C lebih dulu di DOM ia tertutup B
 | (Revisi Penugasan → Daftar Peserta → Kelola penilai, 13 Okt 2026).
 |
 | Dibaca dari DOM, bukan pencacah sendiri (pembukuan seperti itu bocor). Overlay milik
 | dialog ini BELUM ada saat dipanggil: watcher berjalan sebelum render.
 */
function lapisanBerikut() {
    const basis = basisLapisan();
    if (typeof document === 'undefined') return basis;

    let tertinggi = null;
    document.querySelectorAll('.hcis-dialog-overlay').forEach((el) => {
        const z = Number.parseInt(el.style.zIndex || getComputedStyle(el).zIndex, 10);
        if (Number.isFinite(z)) tertinggi = tertinggi === null ? z : Math.max(tertinggi, z);
    });

    return tertinggi === null ? basis : Math.max(basis, tertinggi + 10);
}

/* Basisnya dibaca dari token yang sama dengan CSS-nya, bukan angka kedua yang ditulis di
   sini. Angka yang disalin akan berselisih diam-diam begitu tokennya digeser. */
function basisLapisan() {
    if (typeof window === 'undefined') return 1050;

    const nilai = getComputedStyle(document.documentElement)
        .getPropertyValue('--hcis-z-overlay')
        .trim();

    return Number.parseInt(nilai, 10) || 1050;
}

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            lapisan.value = lapisanBerikut();

            fokus.tangkap();
            lockScroll();
            window.addEventListener('keydown', handleKeyDown);
            emit('open');
            nextTick(() => fokus.tahan(dialogRef.value));
        } else {
            lepaskanLapisan();
            unlockScroll();
            window.removeEventListener('keydown', handleKeyDown);
            fokus.lepas();
        }
    },
    { immediate: true }
);

function lepaskanLapisan() {
    lapisan.value = null;
}

onUnmounted(() => {
    lepaskanLapisan();
    unlockScroll();
    window.removeEventListener('keydown', handleKeyDown);
});

const onAfterEnter = () => emit('after-enter');
const onAfterLeave = () => emit('after-leave');
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   TRANSITIONS (Fluid Pop & Smooth Backdrop)
   ═══════════════════════════════════════════════════════════════════════════ */

/* Backdrop Fade */
.hcis-dialog-fade-enter-active {
    transition: opacity 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-dialog-fade-leave-active {
    transition: opacity 180ms cubic-bezier(0.4, 0, 0.2, 1);
}

.hcis-dialog-fade-enter-from,
.hcis-dialog-fade-leave-to {
    opacity: 0 !important;
}

/* Modal Window Scale & Slide Pop */
.hcis-dialog-fade-enter-active .hcis-dialog {
    transition: transform 240ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
}

.hcis-dialog-fade-leave-active .hcis-dialog {
    transition: transform 180ms cubic-bezier(0.4, 0, 0.2, 1), opacity 180ms ease;
}

.hcis-dialog-fade-enter-from .hcis-dialog {
    opacity: 0 !important;
    transform: scale(0.94) translateY(14px) !important;
}

.hcis-dialog-fade-enter-to .hcis-dialog {
    opacity: 1;
    transform: scale(1) translateY(0);
}

.hcis-dialog-fade-leave-from .hcis-dialog {
    opacity: 1;
    transform: scale(1) translateY(0);
}

.hcis-dialog-fade-leave-to .hcis-dialog {
    opacity: 0 !important;
    transform: scale(0.96) translateY(8px) !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   BACKDROP & CONTAINER
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: var(--hcis-z-overlay, 1050);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--hcis-space-4, 16px);
    background-color: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    will-change: opacity;
}

.hcis-dialog {
    position: relative;
    width: 100%;
    background-color: var(--hcis-surface, var(--hcis-white));
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid rgb(var(--hcis-slate-200-rgb, 203 213 225) / 0.9);
    box-shadow:
        0 10px 25px -5px rgba(15, 23, 42, 0.12),
        0 25px 50px -12px rgba(15, 23, 42, 0.25);
    overflow: hidden;
    outline: none;
    display: flex;
    flex-direction: column;
    max-height: calc(90vh - 32px);
    isolation: isolate;
    will-change: transform, opacity;
    font-family: var(--hcis-font-body, inherit);
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - SURFACE VARIANTS
   ═══════════════════════════════════════════════════════════════════════════ */

/* Variant 1: Glass (Clean Sentiment Adaptive Surface) */
.hcis-dialog--variant-glass {
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.12), 0 25px 50px -12px rgba(15, 23, 42, 0.25);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-brand,
.hcis-dialog--variant-glass.hcis-dialog--sentiment-primary {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgb(var(--hcis-brand-600-rgb, 79 70 229) / 0.08), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb, 238 242 255) / 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-brand-50-rgb, 238 242 255) / 0.5) 100%);
    border-color: rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.25);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-purple,
.hcis-dialog--variant-glass.hcis-dialog--sentiment-violet {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgba(124, 58, 237, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgba(109, 40, 217, 0.08), transparent 55%),
        linear-gradient(225deg, rgba(245, 243, 255, 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgba(237, 233, 254, 0.5) 100%);
    border-color: rgba(124, 58, 237, 0.25);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-teal,
.hcis-dialog--variant-glass.hcis-dialog--sentiment-cyan {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgba(13, 148, 136, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgba(15, 118, 110, 0.08), transparent 55%),
        linear-gradient(225deg, rgba(240, 253, 250, 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgba(204, 251, 241, 0.5) 100%);
    border-color: rgba(13, 148, 136, 0.25);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-danger {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgb(var(--hcis-danger-500-rgb, 239 68 68) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgb(var(--hcis-danger-600-rgb, 220 38 38) / 0.08), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb, 254 242 242) / 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-danger-100-rgb, 254 226 226) / 0.5) 100%);
    border-color: rgb(var(--hcis-danger-500-rgb, 239 68 68) / 0.28);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-warning {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgb(var(--hcis-warning-500-rgb, 245 158 11) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgb(var(--hcis-warning-600-rgb, 217 119, 6) / 0.08), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb, 255 251 235) / 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-warning-100-rgb, 254 243 199) / 0.5) 100%);
    border-color: rgb(var(--hcis-warning-500-rgb, 245 158 11) / 0.28);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-success {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgb(var(--hcis-success-500-rgb, 16 185 129) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgb(var(--hcis-success-600-rgb, 5 150 105) / 0.08), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb, 236 253 245) / 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-success-100-rgb, 209 250 229) / 0.5) 100%);
    border-color: rgb(var(--hcis-success-500-rgb, 16 185 129) / 0.28);
}

.hcis-dialog--variant-glass.hcis-dialog--sentiment-info {
    background:
        radial-gradient(ellipse 550px 280px at 105% -15%, rgb(var(--hcis-info-500-rgb, 14 165 233) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 240px at -5% 115%, rgb(var(--hcis-info-600-rgb, 2 132 199) / 0.08), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-info-50-rgb, 240 249 255) / 0.85) 0%, var(--hcis-white, var(--hcis-white)) 35%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-info-100-rgb, 224 242 254) / 0.5) 100%);
    border-color: rgb(var(--hcis-info-500-rgb, 14 165 233) / 0.28);
}

/* Variant 2: Raised (Dramatic Elevation) */
.hcis-dialog--variant-raised {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid rgb(var(--hcis-slate-200-rgb, 203 213 225) / 0.95);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 1),
        0 15px 35px -5px rgba(15, 23, 42, 0.12),
        0 35px 70px -15px rgba(15, 23, 42, 0.24);
}

/* Variant 3: Flat (Muted Soft) */
.hcis-dialog--variant-flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid rgba(226, 232, 240, 0.7);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
}

/* Variant 4: Outlined (Border Grid) */
.hcis-dialog--variant-outlined {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1.5px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    box-shadow: 0 8px 30px -4px rgba(15, 23, 42, 0.08);
}

/* Variant 5: Hero (Top Accent Band) */
.hcis-dialog--variant-hero {
    border-top: 3.5px solid var(--hcis-brand-600, var(--hcis-indigo-600));
}
.hcis-dialog--variant-hero.hcis-dialog--sentiment-purple { border-top-color: var(--hcis-violet-600); }
.hcis-dialog--variant-hero.hcis-dialog--sentiment-teal { border-top-color: #0d9488; }
.hcis-dialog--variant-hero.hcis-dialog--sentiment-danger { border-top-color: var(--hcis-danger-600, var(--hcis-red-600)); }
.hcis-dialog--variant-hero.hcis-dialog--sentiment-warning { border-top-color: var(--hcis-warning-600, var(--hcis-amber-600)); }
.hcis-dialog--variant-hero.hcis-dialog--sentiment-success { border-top-color: var(--hcis-success-600, var(--hcis-emerald-600)); }
.hcis-dialog--variant-hero.hcis-dialog--sentiment-info { border-top-color: var(--hcis-info-600, var(--hcis-sky-600)); }

/* Variant 6: Obsidian (Dark Crystal Void Mode) */
.hcis-dialog--variant-obsidian {
    background: var(--hcis-slate-900) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: var(--hcis-slate-50) !important;
    box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__header {
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__title {
    color: var(--hcis-white) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__kicker,
.hcis-dialog--variant-obsidian .hcis-dialog__description {
    color: var(--hcis-slate-400) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__close {
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: var(--hcis-slate-400) !important;
}
.hcis-dialog--variant-obsidian .hcis-dialog__close:hover {
    background: rgba(51, 65, 85, 0.9) !important;
    color: var(--hcis-white) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__body {
    color: var(--hcis-slate-300) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__footer {
    background: #0b1120 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ICON BOX VARIANTS
   ═══════════════════════════════════════════════════════════════════════════ */

/* Variant Solid */
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--brand,
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--primary {
    background: var(--hcis-brand-600, var(--hcis-indigo-600));
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--purple,
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--violet {
    background: var(--hcis-violet-600);
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--teal,
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--cyan {
    background: #0d9488;
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--success {
    background: var(--hcis-success-600, var(--hcis-emerald-600));
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--warning {
    background: var(--hcis-warning-600, var(--hcis-amber-600));
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--danger {
    background: var(--hcis-danger-600, var(--hcis-red-600));
    color: var(--hcis-white);
    border: none;
}
.hcis-dialog__icon--variant-solid.hcis-dialog__icon--info {
    background: var(--hcis-info-600, var(--hcis-sky-600));
    color: var(--hcis-white);
    border: none;
}

/* Variant Soft */
.hcis-dialog__icon--variant-soft {
    border: none !important;
    box-shadow: none !important;
}

/* Variant Bordered */
.hcis-dialog__icon--variant-bordered {
    background: transparent !important;
    border-width: 1.5px !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SIZES
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog--xs { max-width: 22rem; }
.hcis-dialog--sm { max-width: 26rem; }
.hcis-dialog--md { max-width: 34rem; }
.hcis-dialog--lg { max-width: 46rem; }
.hcis-dialog--xl { max-width: 60rem; }
.hcis-dialog--2xl { max-width: 74rem; }
.hcis-dialog--full {
    max-width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
}

/* ═══════════════════════════════════════════════════════════════════════════
   LOADING BAR
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__loading-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background-color: var(--hcis-brand-100, var(--hcis-indigo-100));
    overflow: hidden;
    z-index: 20;
}

.hcis-dialog__loading-indicator {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 40%;
    background-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-radius: 999px;
    animation: hcis-dialog-bar-indeterminate 1.4s ease-in-out infinite;
}

@keyframes hcis-dialog-bar-indeterminate {
    0% { left: -40%; width: 40%; }
    50% { left: 40%; width: 60%; }
    100% { left: 100%; width: 20%; }
}

/* ═══════════════════════════════════════════════════════════════════════════
   HEADER & GAUSSIAN DISSOLVE OVERHANG SHADOW
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__header {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    padding: 1rem 1.375rem 0.875rem 1.375rem;
    gap: 0.375rem;
    flex-shrink: 0;
    background: #ffffff;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: box-shadow 240ms ease, background 240ms ease, border-color 240ms ease;
}



.hcis-dialog__header--divider {
    border-bottom: 1px solid rgba(203, 213, 225, 0.7);
}

.hcis-dialog__header-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.875rem;
    width: 100%;
}

.hcis-dialog__header-main {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    flex: 1;
    min-width: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   JEWEL SQUIRCLE ICON BOXES
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__icon {
    width: 2.625rem;
    height: 2.625rem;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 8px -2px rgba(15, 23, 42, 0.08);
    transition: transform var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-dialog__icon--neutral,
.hcis-dialog__icon--none {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid rgba(203, 213, 225, 0.8);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.hcis-dialog__icon--primary,
.hcis-dialog__icon--brand {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid rgba(165, 180, 252, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-dialog__icon--purple,
.hcis-dialog__icon--violet {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ede9fe 100%);
    border: 1px solid rgba(196, 181, 253, 0.8);
    color: var(--hcis-violet-600);
}

.hcis-dialog__icon--teal,
.hcis-dialog__icon--cyan {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #ccfbf1 100%);
    border: 1px solid rgba(153, 246, 228, 0.8);
    color: #0d9488;
}

.hcis-dialog__icon--success {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-emerald-100) 100%);
    border: 1px solid rgba(167, 243, 208, 0.8);
    color: var(--hcis-success-600, var(--hcis-emerald-600));
}

.hcis-dialog__icon--warning {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-amber-100) 100%);
    border: 1px solid rgba(253, 230, 138, 0.8);
    color: var(--hcis-warning-600, var(--hcis-amber-600));
}

.hcis-dialog__icon--danger {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-red-100) 100%);
    border: 1px solid rgba(254, 202, 202, 0.8);
    color: var(--hcis-danger-600, var(--hcis-red-600));
}

.hcis-dialog__icon--info {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-sky-100) 100%);
    border: 1px solid rgba(186, 230, 253, 0.8);
    color: var(--hcis-info-600, var(--hcis-sky-600));
}

.hcis-dialog__icon--obsidian {
    background: linear-gradient(135deg, var(--hcis-slate-700) 0%, var(--hcis-slate-900) 100%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--hcis-indigo-400);
}

/* ═══════════════════════════════════════════════════════════════════════════
   HEADER TEXT STACK & CLOSE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__header-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    flex: 1;
    gap: 2px;
}

.hcis-dialog__kicker {
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1;
    margin-bottom: 2px;
}

.hcis-dialog__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: nowrap;
    width: 100%;
    min-width: 0;
}

.hcis-dialog__title {
    font-family: var(--hcis-font-heading, inherit);
    font-size: 1.0625rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.015em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}

.hcis-dialog__description {
    font-size: 0.8125rem;
    font-weight: 450;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin: 2px 0 0 0;
    line-height: 1.35;
}

.hcis-dialog__close {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 50%;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: rgba(248, 250, 252, 0.8);
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-dialog__close:hover {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    transform: scale(1.08) rotate(90deg);
}

/* ═══════════════════════════════════════════════════════════════════════════
   SENTIMENT HEADER STYLING (Exact HcisCard Glass Gradients)
   ═══════════════════════════════════════════════════════════════════════════ */

/* 1. Primary / Brand (Signature Indigo) */
.hcis-dialog--sentiment-brand .hcis-dialog__header,
.hcis-dialog--sentiment-primary .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-brand-600-rgb, 79 70 229) / 0.09), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb, 238 242 255) / 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-brand-50-rgb, 238 242 255) / 0.5) 100%);
    border-bottom: 1px solid rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.2);
}


/* 2. Purple / Violet (Amethyst SOP Assesmen) */
.hcis-dialog--sentiment-purple .hcis-dialog__header,
.hcis-dialog--sentiment-violet .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(124, 58, 237, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(109, 40, 217, 0.09), transparent 55%),
        linear-gradient(225deg, rgba(245, 243, 255, 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgba(237, 233, 254, 0.5) 100%);
    border-bottom: 1px solid rgba(124, 58, 237, 0.24);
}


/* 3. Teal / Cyan (Mint SOP Referensi) */
.hcis-dialog--sentiment-teal .hcis-dialog__header,
.hcis-dialog--sentiment-cyan .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(13, 148, 136, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(15, 118, 110, 0.09), transparent 55%),
        linear-gradient(225deg, rgba(240, 253, 250, 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgba(204, 251, 241, 0.5) 100%);
    border-bottom: 1px solid rgba(13, 148, 136, 0.24);
}


/* 4. Danger (Rose / Crimson) */
.hcis-dialog--sentiment-danger .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-danger-500-rgb, 239 68 68) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-danger-600-rgb, 220 38 38) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb, 254 242 242) / 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-danger-100-rgb, 254 226 226) / 0.5) 100%);
    border-bottom: 1px solid rgb(var(--hcis-danger-500-rgb, 239 68 68) / 0.28);
}


/* 5. Warning (Amber / Gold) */
.hcis-dialog--sentiment-warning .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-warning-500-rgb, 245 158 11) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-warning-600-rgb, 217 119 6) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb, 255 251 235) / 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-warning-100-rgb, 254 243 199) / 0.5) 100%);
    border-bottom: 1px solid rgb(var(--hcis-warning-500-rgb, 245 158 11) / 0.28);
}


/* 6. Success (Emerald Green) */
.hcis-dialog--sentiment-success .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-success-500-rgb, 16 185 129) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-success-600-rgb, 5 150 105) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb, 236 253 245) / 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-success-100-rgb, 209 250 229) / 0.5) 100%);
    border-bottom: 1px solid rgb(var(--hcis-success-500-rgb, 16 185 129) / 0.28);
}


/* 7. Info (Sky Blue / Cyan) */
.hcis-dialog--sentiment-info .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-info-500-rgb, 14 165 233) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-info-600-rgb, 2 132 199) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-info-50-rgb, 240 249 255) / 0.88) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-info-100-rgb, 224 242 254) / 0.5) 100%);
    border-bottom: 1px solid rgb(var(--hcis-info-500-rgb, 14 165 233) / 0.28);
}


/* 8. Neutral / None (Pure Frosted Clean) */
.hcis-dialog--sentiment-neutral .hcis-dialog__header,
.hcis-dialog--sentiment-none .hcis-dialog__header {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-slate-200-rgb, 226 232 240) / 0.22), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.15), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-white-rgb, 255 255 255) / 0.98) 0%, var(--hcis-white, var(--hcis-white)) 30%, var(--hcis-white, var(--hcis-white)) 70%, rgb(var(--hcis-slate-50-rgb, 248 250 252) / 0.6) 100%);
    border-bottom: 1px solid rgb(var(--hcis-slate-200-rgb, 226 232 240) / 0.85);
}

/* ═══════════════════════════════════════════════════════════════════════════
   PATTERN MASKS (Standard HcisCard)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog .hcis-pattern-grid,
.hcis-dialog .hcis-pattern-dots,
.hcis-dialog .hcis-pattern-crosshair,
.hcis-dialog .hcis-pattern-stripes,
.hcis-dialog .hcis-pattern-rings,
.hcis-dialog .hcis-pattern-hex {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    opacity: var(--hcis-pattern-opacity, 0.15) !important;
    -webkit-mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
    mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
}

.hcis-dialog--sentiment-brand .hcis-pattern-grid,
.hcis-dialog--sentiment-brand .hcis-pattern-dots,
.hcis-dialog--sentiment-brand .hcis-pattern-crosshair,
.hcis-dialog--sentiment-brand .hcis-pattern-stripes,
.hcis-dialog--sentiment-brand .hcis-pattern-rings,
.hcis-dialog--sentiment-brand .hcis-pattern-hex,
.hcis-dialog--sentiment-primary .hcis-pattern-grid,
.hcis-dialog--sentiment-primary .hcis-pattern-dots {
    color: var(--hcis-brand-600);
}

.hcis-dialog--sentiment-purple .hcis-pattern-grid,
.hcis-dialog--sentiment-purple .hcis-pattern-dots,
.hcis-dialog--sentiment-purple .hcis-pattern-crosshair,
.hcis-dialog--sentiment-purple .hcis-pattern-stripes,
.hcis-dialog--sentiment-purple .hcis-pattern-rings,
.hcis-dialog--sentiment-purple .hcis-pattern-hex,
.hcis-dialog--sentiment-violet .hcis-pattern-grid,
.hcis-dialog--sentiment-violet .hcis-pattern-dots {
    color: var(--hcis-violet-600);
}

.hcis-dialog--sentiment-teal .hcis-pattern-grid,
.hcis-dialog--sentiment-teal .hcis-pattern-dots,
.hcis-dialog--sentiment-teal .hcis-pattern-crosshair,
.hcis-dialog--sentiment-teal .hcis-pattern-stripes,
.hcis-dialog--sentiment-teal .hcis-pattern-rings,
.hcis-dialog--sentiment-teal .hcis-pattern-hex,
.hcis-dialog--sentiment-cyan .hcis-pattern-grid,
.hcis-dialog--sentiment-cyan .hcis-pattern-dots {
    color: #0d9488;
}

.hcis-dialog--sentiment-success .hcis-pattern-grid,
.hcis-dialog--sentiment-success .hcis-pattern-dots,
.hcis-dialog--sentiment-success .hcis-pattern-crosshair,
.hcis-dialog--sentiment-success .hcis-pattern-stripes,
.hcis-dialog--sentiment-success .hcis-pattern-rings,
.hcis-dialog--sentiment-success .hcis-pattern-hex {
    color: var(--hcis-success-600);
}

.hcis-dialog--sentiment-warning .hcis-pattern-grid,
.hcis-dialog--sentiment-warning .hcis-pattern-dots,
.hcis-dialog--sentiment-warning .hcis-pattern-crosshair,
.hcis-dialog--sentiment-warning .hcis-pattern-stripes,
.hcis-dialog--sentiment-warning .hcis-pattern-rings,
.hcis-dialog--sentiment-warning .hcis-pattern-hex {
    color: var(--hcis-warning-600);
}

.hcis-dialog--sentiment-danger .hcis-pattern-grid,
.hcis-dialog--sentiment-danger .hcis-pattern-dots,
.hcis-dialog--sentiment-danger .hcis-pattern-crosshair,
.hcis-dialog--sentiment-danger .hcis-pattern-stripes,
.hcis-dialog--sentiment-danger .hcis-pattern-rings,
.hcis-dialog--sentiment-danger .hcis-pattern-hex {
    color: var(--hcis-danger-600);
}

.hcis-dialog--sentiment-info .hcis-pattern-grid,
.hcis-dialog--sentiment-info .hcis-pattern-dots,
.hcis-dialog--sentiment-info .hcis-pattern-crosshair,
.hcis-dialog--sentiment-info .hcis-pattern-stripes,
.hcis-dialog--sentiment-info .hcis-pattern-rings,
.hcis-dialog--sentiment-info .hcis-pattern-hex {
    color: var(--hcis-info-600);
}

.hcis-dialog--sentiment-neutral .hcis-pattern-grid,
.hcis-dialog--sentiment-neutral .hcis-pattern-dots,
.hcis-dialog--sentiment-neutral .hcis-pattern-crosshair,
.hcis-dialog--sentiment-neutral .hcis-pattern-stripes,
.hcis-dialog--sentiment-neutral .hcis-pattern-rings,
.hcis-dialog--sentiment-neutral .hcis-pattern-hex,
.hcis-dialog--sentiment-none .hcis-pattern-grid,
.hcis-dialog--sentiment-none .hcis-pattern-dots {
    color: var(--hcis-slate-500);
}

/* ═══════════════════════════════════════════════════════════════════════════
   BODY & FOOTER
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__body {
    position: relative;
    z-index: 1;

    /* WAJIB. Panelnya `overflow: hidden` dengan `max-height: calc(90vh - 32px)`, dan badan
       ini anak flex kolomnya. Tanpa `min-height: 0`, `min-height` bawaan anak flex adalah
       `auto` - ia menolak menyusut di bawah tinggi isinya, sehingga `overflow-y: auto` di
       bawah tidak pernah menyala dan panel memotong isinya BEGITU SAJA: tidak ada
       gulungan, tidak ada tanda, bagian bawahnya hilang. Gejalanya menyamar jadi
       "kontennya tidak ikut ter-render". */
    min-height: 0;
    padding: 1.125rem 1.375rem;
    color: var(--hcis-text, var(--hcis-slate-700));
    font-size: var(--hcis-font-size-sm, 0.875rem);
    line-height: 1.6;
    letter-spacing: -0.011em;
    flex: 1;
    background-color: var(--hcis-surface, var(--hcis-white));
    -webkit-font-smoothing: antialiased;
}

.hcis-dialog__body--scrollable {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.8) transparent;
}

/* ═══════════════════════════════════════════════════════════════════════════
   PREMIUM EXECUTIVE FOOTER
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__footer {
    position: relative;
    z-index: 10;
    padding: 0.875rem 1.375rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.95);
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.875rem;
    flex-shrink: 0;
}

.hcis-dialog__footer--sticky {
    position: sticky;
    bottom: 0;
    box-shadow:
        0 -8px 24px -4px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 0 rgba(255, 255, 255, 0.95);
}

.hcis-dialog__footer .hcis-dialog__footer-pattern {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    opacity: 0.12 !important;
    -webkit-mask-image: radial-gradient(ellipse 90% 75% at 0% 100%, black 0%, rgba(0, 0, 0, 0.3) 30%, transparent 65%);
    mask-image: radial-gradient(ellipse 90% 75% at 0% 100%, black 0%, rgba(0, 0, 0, 0.3) 30%, transparent 65%);
}

.hcis-dialog__footer-start {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    flex-shrink: 1;
}

.hcis-dialog__footer-actions {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-shrink: 0;
}

/* Alignments */
.hcis-dialog__footer--align-end .hcis-dialog__footer-actions,
.hcis-dialog__footer--align-right .hcis-dialog__footer-actions {
    margin-left: auto;
}

.hcis-dialog__footer--align-start .hcis-dialog__footer-actions,
.hcis-dialog__footer--align-left .hcis-dialog__footer-actions {
    margin-right: auto;
    margin-left: 0;
}

.hcis-dialog__footer--align-center .hcis-dialog__footer-actions {
    margin: 0 auto;
}

.hcis-dialog__footer--align-between {
    justify-content: space-between;
    width: 100%;
}

.hcis-dialog__footer--align-between .hcis-dialog__footer-actions {
    width: 100%;
    justify-content: space-between;
}

/* Keyboard Shortcut Capsule (Linear/Raycast style) */
.hcis-dialog__esc-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
    font-weight: 550;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    user-select: none;
}

.hcis-dialog__kbd {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.125rem 0.375rem;
    font-family: var(--hcis-font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace);
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-white);
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(15, 23, 42, 0.06), inset 0 -1px 0 rgba(203, 213, 225, 0.4);
}

/* Obsidian Dark Mode Footer */
.hcis-dialog--variant-obsidian .hcis-dialog__footer {
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.92) 0%, rgba(11, 17, 32, 0.98) 100%) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.06) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__esc-hint {
    color: var(--hcis-slate-400) !important;
}

.hcis-dialog--variant-obsidian .hcis-dialog__kbd {
    background: var(--hcis-slate-800) !important;
    color: var(--hcis-slate-300) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.3), inset 0 -1px 0 rgba(255, 255, 255, 0.05) !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MOBILE BOTTOM SHEET DOCKING (<= 768px)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-dialog__mobile-handle-wrap {
    display: none;
}

@media (max-width: 768px) {
    .hcis-dialog-overlay {
        padding: 0 !important;
        align-items: flex-end !important;
    }

    .hcis-dialog-overlay:has(.hcis-dialog--full),
    .hcis-dialog-overlay:has(.hcis-dual-pane-dialog),
    .hcis-dialog-overlay:has(.permit-confirm-dialog),
    .hcis-dialog-overlay:has(.tamu-confirm-dialog) {
        align-items: stretch !important;
    }

    .hcis-dialog,
    .hcis-dialog--xs,
    .hcis-dialog--sm,
    .hcis-dialog--md,
    .hcis-dialog--lg,
    .hcis-dialog--xl,
    .hcis-dialog--2xl {
        max-width: 100vw !important;
        width: 100vw !important;
        min-width: 100vw !important;
        border-radius: 24px 24px 0 0 !important;
        margin: 0 !important;
        margin-bottom: 0 !important;
        border: none !important;
        border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
        max-height: 88vh !important;
        box-shadow: 0 -10px 30px -4px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(255, 255, 255, 0.9) inset !important;
    }

    /* True Full-Screen Mobile Dialog (Edge-to-Edge) */
    .hcis-dialog--full,
    /* Class-nya menempel PADA .hcis-dialog, bukan membungkusnya — lihat
       catatan inheritAttrs di atas. Bentuk keturunan ('.a .b') tidak akan
       pernah cocok dan hanya menyesatkan pembaca berikutnya. */
    .hcis-dialog.hcis-dual-pane-dialog,
    .hcis-dialog:has(.hcis-dual-pane__layout),
    .permit-confirm-dialog,
    .tamu-confirm-dialog {
        max-width: 100vw !important;
        width: 100vw !important;
        min-width: 100vw !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        min-height: 100dvh !important;
        border-radius: 0 !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }

    .hcis-dialog-fade-enter-from .hcis-dialog {
        opacity: 0 !important;
        transform: translateY(100%) !important;
    }

    .hcis-dialog-fade-leave-to .hcis-dialog {
        opacity: 0 !important;
        transform: translateY(100%) !important;
    }

    .hcis-dialog__mobile-handle-wrap {
        display: flex !important;
        justify-content: center !important;
        padding: 4px 0 2px 0 !important;
        margin: 0 !important;
    }

    .hcis-dialog__mobile-handle {
        width: 32px !important;
        height: 3.5px !important;
        border-radius: 999px !important;
        background: rgba(148, 163, 184, 0.45) !important;
        box-shadow: inset 0 1px 0.5px rgba(255, 255, 255, 0.7), 0 1px 2px rgba(15, 23, 42, 0.08) !important;
    }

    .hcis-dialog__header {
        position: relative !important;
        z-index: 15 !important;
        padding: 0.5rem 1.125rem 0.75rem 1.125rem !important;
        gap: 0.25rem !important;
    }

    

    .hcis-dialog__icon {
        width: 2.125rem !important;
        height: 2.125rem !important;
        font-size: 1.05rem !important;
        border-radius: 8px !important;
    }

    .hcis-dialog__title {
        font-size: 0.9125rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
    }

    .hcis-dialog__description {
        font-size: 0.725rem !important;
    }

    .permit-confirm-dialog .hcis-dialog__description,
    .tamu-confirm-dialog .hcis-dialog__description,
    .hcis-dialog--full .hcis-dialog__description,
    .hcis-dual-pane-dialog .hcis-dialog__description {
        display: none !important;
    }

    .hcis-dialog__body {
        padding: 0.875rem 1.125rem !important;
    }

    .hcis-dialog__footer {
        padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0px)) 1rem !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.625rem !important;
        width: 100% !important;
        box-sizing: border-box !important;
        border-top: 1px solid rgba(226, 232, 240, 0.85) !important;
        background: #ffffff !important;
    }

    .hcis-dialog__footer-start {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        min-width: 0 !important;
        flex-shrink: 0 !important;
        white-space: nowrap !important;
    }

    .hcis-dialog__footer-start:empty,
    .hcis-dialog__footer-start:has(> .d-none.d-sm-flex:only-child),
    .hcis-dialog__footer-start:has(> .d-none.d-md-flex:only-child) {
        display: none !important;
    }

    .hcis-dialog__footer-actions {
        width: 100% !important;
        flex: 1 1 auto !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .hcis-dialog__footer :deep(.hcis-button),
    .hcis-dialog__footer :deep(button) {
        min-width: 0 !important;
        min-height: 2.5rem !important;
        height: 2.5rem !important;
        justify-content: center !important;
        padding: 0 1rem !important;
        font-size: 0.875rem !important;
        font-weight: 650 !important;
        border-radius: var(--hcis-radius-md, 0.5rem) !important;
        flex: 1 1 0% !important;
        width: 100% !important;
    }

    .hcis-dialog__footer :deep(.hcis-button:first-child),
    .hcis-dialog__footer :deep(button:first-child),
    .hcis-dialog__footer :deep(.hcis-button:last-child),
    .hcis-dialog__footer :deep(button:last-child) {
        flex: 1 1 0% !important;
    }

    /* Dialog sistem (hcisDialog.confirm/alert/prompt): tombolnya dibentang
       sama lebar memenuhi lembar bawah dan setinggi sasaran jempol.
       Aturan :deep() di atas TIDAK pernah berlaku — blok <style> ini tidak
       scoped, jadi peramban membuang selektornya — dan menghidupkannya akan
       ikut mengubah footer setiap dialog kustom. Karena itu yang dibentang
       hanya dialog bawaan layanan, lewat kelas penandanya. */
    .hcis-dialog--sistem .hcis-dialog__footer-actions > .hcis-btn {
        flex: 1 1 0%;
        min-width: 0;
        min-height: 2.75rem;
        justify-content: center;
        font-size: 0.9375rem;
    }
}
</style>
