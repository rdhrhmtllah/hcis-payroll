<template>
    <Teleport :to="teleportTo" :disabled="!teleportTo">
        <Transition name="hcis-drawer-fade" type="animation" @after-enter="onAfterEnter" @after-leave="onAfterLeave">
            <div
                v-if="modelValue"
                class="hcis-drawer-overlay"
                :class="[
                    `hcis-drawer-overlay--${effectiveDirection}`,
                    overlayClass,
                ]"
                role="presentation"
                @click.self="handleOverlayClick"
            >
                <div
                    ref="drawerRef"
                    class="hcis-drawer"
                    :class="[
                        `hcis-drawer--${effectiveDirection}`,
                        sizePresetClass,
                        `hcis-drawer--sentiment-${sentiment}`,
                        customClass,
                    ]"
                    :style="customSizeStyle"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    tabindex="-1"
                >
                    <!-- Handle Indikator Mobile / Bottom Sheet Drag-to-Close -->
                    <div
                        v-if="effectiveDirection === 'btt' || isMobile"
                        class="hcis-drawer__mobile-handle"
                        aria-hidden="true"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    />

                    <!-- Header -->
                    <header
                        v-if="$slots.header || title || showClose"
                        class="hcis-drawer__header"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                    >
                        <!-- Executive Pattern Overlay -->
                        <div class="hcis-drawer__header-pattern" aria-hidden="true" />

                        <slot name="header">
                            <div class="hcis-drawer__header-main">
                                <div class="hcis-drawer__title-row">
                                    <div v-if="$slots.leading || icon || sentiment !== 'neutral'" class="hcis-drawer__icon" :class="`hcis-drawer__icon--${sentiment}`">
                                        <slot name="leading">
                                            <HcisIcon v-if="icon" :name="icon" size="xs" />
                                            <HcisIcon v-else-if="sentiment === 'danger'" name="exclamation-triangle-fill" size="xs" />
                                            <HcisIcon v-else-if="sentiment === 'warning'" name="exclamation-circle-fill" size="xs" />
                                            <HcisIcon v-else-if="sentiment === 'success'" name="check-circle-fill" size="xs" />
                                            <HcisIcon v-else-if="sentiment === 'info'" name="info-circle-fill" size="xs" />
                                            <HcisIcon v-else-if="sentiment === 'primary'" name="layers-fill" size="xs" />
                                        </slot>
                                    </div>

                                    <h3 :id="titleId" class="hcis-drawer__title">
                                        <slot name="title">{{ title }}</slot>
                                    </h3>
                                </div>

                                <p v-if="description" class="hcis-drawer__description">
                                    {{ description }}
                                </p>
                            </div>
                        </slot>

                        <!-- Tombol Tutup Presisi & Identik HcisDialog -->
                        <button
                            v-if="showClose"
                            type="button"
                            class="hcis-drawer__close"
                            aria-label="Tutup laci"
                            @click="close"
                        >
                            <HcisIcon name="x-lg" size="xs" />
                        </button>
                    </header>

                    <!-- Body -->
                    <main
                        class="hcis-drawer__body"
                        :class="{ 'hcis-drawer__body--scrollable': scrollable }"
                    >
                        <slot />
                    </main>

                    <!-- Footer -->
                    <footer v-if="$slots.footer" class="hcis-drawer__footer">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, onUnmounted, computed, nextTick, onMounted } from 'vue';
import { useScrollLock } from './useScrollLock';
import { useModalFocus } from './useModalFocus';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisDrawer' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    direction: {
        type: String,
        default: 'rtl',
        validator: (val) => ['rtl', 'ltr', 'btt', 'ttb'].includes(val),
    },
    size: {
        type: String,
        default: '30rem',
    },
    sentiment: {
        type: String,
        default: 'neutral',
        validator: (val) => ['neutral', 'primary', 'danger', 'warning', 'success', 'info'].includes(val),
    },
    icon: { type: String, default: '' },
    closeOnOverlay: { type: Boolean, default: true },
    closeOnEsc: { type: Boolean, default: true },
    showClose: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
    scrollable: { type: Boolean, default: true },
    customClass: { type: String, default: '' },
    overlayClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'close', 'open', 'after-enter', 'after-leave']);

const drawerRef = ref(null);
const titleId = computed(() => `hcis-drawer-title-${Math.random().toString(36).substring(2, 9)}`);
const isMobile = ref(false);

const checkMobile = () => {
    isMobile.value = window.innerWidth < 768;
};

// Di Mobile (<640px), SELALU beroperasi sebagai Bottom Sheet (btt) dari bawah
const effectiveDirection = computed(() => {
    if (isMobile.value) return 'btt';
    return props.direction;
});

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

// Penanganan Ukuran (Presol / Custom CSS Value seperti '32rem' atau '540px')
const sizePresets = ['sm', 'md', 'lg', 'xl', 'full'];
const sizePresetClass = computed(() => {
    return sizePresets.includes(props.size) ? `hcis-drawer--${props.size}` : '';
});

const customSizeStyle = computed(() => {
    if (sizePresets.includes(props.size) || isMobile.value) return {};
    const isHorizontal = effectiveDirection.value === 'rtl' || effectiveDirection.value === 'ltr';
    return isHorizontal ? { width: props.size, maxWidth: '90vw' } : { height: props.size, maxHeight: '90vh' };
});

// Kunci gulir dipusatkan dan DIHITUNG — lihat useScrollLock.js untuk alasannya.
const { kunci: lockScroll, buka: unlockScroll } = useScrollLock();

// Fokus ditangkap, ditahan, lalu dikembalikan — lihat useModalFocus.js.
const fokus = useModalFocus();

// Penangan Kunci ESC
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

// Penangan Gestur Tarik Tutup Touch Mobile / Bottom Sheet Mulus Tanpa Glitch
const isDragging = ref(false);
const startY = ref(0);
const startX = ref(0);
const currentDragY = ref(0);
const currentDragX = ref(0);

const onTouchStart = (e) => {
    const touch = e.touches[0];
    startY.value = touch.clientY;
    startX.value = touch.clientX;
    currentDragY.value = 0;
    currentDragX.value = 0;
    isDragging.value = true;
};

const onTouchMove = (e) => {
    if (!isDragging.value || !drawerRef.value) return;
    const touch = e.touches[0];
    const deltaY = touch.clientY - startY.value;
    const deltaX = touch.clientX - startX.value;

    if (effectiveDirection.value === 'btt' || isMobile.value) {
        if (deltaY > 0) {
            currentDragY.value = deltaY;
            drawerRef.value.style.transform = `translate3d(0, ${deltaY}px, 0)`;
            drawerRef.value.style.transition = 'none';
        }
    } else if (effectiveDirection.value === 'rtl' && deltaX > 0) {
        currentDragX.value = deltaX;
        drawerRef.value.style.transform = `translate3d(${deltaX}px, 0, 0)`;
        drawerRef.value.style.transition = 'none';
    } else if (effectiveDirection.value === 'ltr' && deltaX < 0) {
        currentDragX.value = deltaX;
        drawerRef.value.style.transform = `translate3d(${deltaX}px, 0, 0)`;
        drawerRef.value.style.transition = 'none';
    }
};

const onTouchEnd = () => {
    if (!isDragging.value) return;
    isDragging.value = false;

    const threshold = 70;
    const shouldClose = currentDragY.value > threshold || Math.abs(currentDragX.value) > threshold;

    if (shouldClose) {
        if (drawerRef.value) {
            drawerRef.value.style.transition = 'transform 180ms cubic-bezier(0.4, 0, 1, 1)';
            if (effectiveDirection.value === 'btt' || isMobile.value) {
                drawerRef.value.style.transform = 'translate3d(0, 100%, 0)';
            } else if (effectiveDirection.value === 'rtl') {
                drawerRef.value.style.transform = 'translate3d(100%, 0, 0)';
            } else if (effectiveDirection.value === 'ltr') {
                drawerRef.value.style.transform = 'translate3d(-100%, 0, 0)';
            }
        }
        setTimeout(() => {
            if (drawerRef.value) {
                drawerRef.value.style.transform = '';
                drawerRef.value.style.transition = '';
            }
            close();
        }, 180);
    } else {
        if (drawerRef.value) {
            drawerRef.value.style.transition = 'transform 200ms var(--hcis-ease)';
            drawerRef.value.style.transform = 'translate3d(0, 0, 0)';
            setTimeout(() => {
                if (drawerRef.value) {
                    drawerRef.value.style.transform = '';
                    drawerRef.value.style.transition = '';
                }
            }, 200);
        }
    }
    currentDragY.value = 0;
    currentDragX.value = 0;
};

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            fokus.tangkap();
            lockScroll();
            window.addEventListener('keydown', handleKeyDown);
            emit('open');
            nextTick(() => fokus.tahan(drawerRef.value));
        } else {
            unlockScroll();
            window.removeEventListener('keydown', handleKeyDown);
            fokus.lepas();
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    unlockScroll();
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('resize', checkMobile);
});

const onAfterEnter = () => emit('after-enter');
const onAfterLeave = () => emit('after-leave');
</script>

<style>
/* Transisi Overlay Backdrop: Fade Warna Background Murni (Tanpa Transparansi Opacity pada Anak) */
.hcis-drawer-fade-enter-active {
    transition: background-color 240ms var(--hcis-ease) !important;
}

.hcis-drawer-fade-leave-active {
    transition: background-color 180ms cubic-bezier(0.4, 0, 1, 1) !important;
}

.hcis-drawer-fade-enter-from,
.hcis-drawer-fade-leave-to {
    background-color: rgb(var(--hcis-slate-900-rgb) / 0) !important;
}

/* Transisi 100% Solid Slide per Arah Presisi dari Tepi Layar (100% Lebar/Tinggi Laci) */
.hcis-drawer-fade-enter-active .hcis-drawer--rtl {
    animation: hcis-drawer-rtl-in 240ms var(--hcis-ease) forwards !important;
}
.hcis-drawer-fade-leave-active .hcis-drawer--rtl {
    animation: hcis-drawer-rtl-out 180ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
}

.hcis-drawer-fade-enter-active .hcis-drawer--ltr {
    animation: hcis-drawer-ltr-in 240ms var(--hcis-ease) forwards !important;
}
.hcis-drawer-fade-leave-active .hcis-drawer--ltr {
    animation: hcis-drawer-ltr-out 180ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
}

.hcis-drawer-fade-enter-active .hcis-drawer--btt {
    animation: hcis-drawer-btt-in 240ms var(--hcis-ease) forwards !important;
}
.hcis-drawer-fade-leave-active .hcis-drawer--btt {
    animation: hcis-drawer-btt-out 180ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
}

.hcis-drawer-fade-enter-active .hcis-drawer--ttb {
    animation: hcis-drawer-ttb-in 240ms var(--hcis-ease) forwards !important;
}
.hcis-drawer-fade-leave-active .hcis-drawer--ttb {
    animation: hcis-drawer-ttb-out 180ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
}

@keyframes hcis-drawer-rtl-in {
    0% { transform: translate3d(100%, 0, 0); }
    100% { transform: translate3d(0, 0, 0); }
}
@keyframes hcis-drawer-rtl-out {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(100%, 0, 0); }
}

@keyframes hcis-drawer-ltr-in {
    0% { transform: translate3d(-100%, 0, 0); }
    100% { transform: translate3d(0, 0, 0); }
}
@keyframes hcis-drawer-ltr-out {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(-100%, 0, 0); }
}

@keyframes hcis-drawer-btt-in {
    0% { transform: translate3d(0, 100%, 0); }
    100% { transform: translate3d(0, 0, 0); }
}
@keyframes hcis-drawer-btt-out {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(0, 100%, 0); }
}

@keyframes hcis-drawer-ttb-in {
    0% { transform: translate3d(0, -100%, 0); }
    100% { transform: translate3d(0, 0, 0); }
}
@keyframes hcis-drawer-ttb-out {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(0, -100%, 0); }
}

/* Container Overlay Backdrop */
.hcis-drawer-overlay {
    position: fixed;
    inset: 0;
    z-index: var(--hcis-z-overlay);
    display: flex;
    background-color: rgb(var(--hcis-slate-900-rgb) / 0.58);
}

/* Placement Alignment */
.hcis-drawer-overlay--rtl { justify-content: flex-end; align-items: stretch; }
.hcis-drawer-overlay--ltr { justify-content: flex-start; align-items: stretch; }
.hcis-drawer-overlay--btt { justify-content: center; align-items: flex-end; }
.hcis-drawer-overlay--ttb { justify-content: center; align-items: flex-start; }

/* Modal Drawer Window - GPU Layer Containment 120fps */
.hcis-drawer {
    position: relative;
    background-color: var(--hcis-surface);
    box-shadow: var(--hcis-shadow-xl), 0 0 0 1px var(--hcis-border);
    overflow: hidden;
    outline: none;
    display: flex;
    flex-direction: column;
    opacity: 1 !important;
    contain: layout paint;
    isolation: isolate;
    will-change: transform;
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* Direction Corner Radii (Desktop side drawers are sleek & flush 0px radius) */
.hcis-drawer--rtl {
    height: 100vh;
    border-radius: 0 !important;
}
.hcis-drawer--ltr {
    height: 100vh;
    border-radius: 0 !important;
}
.hcis-drawer--btt {
    width: 100vw;
    max-width: 54rem;
    max-height: 88vh;
    border-top-left-radius: var(--hcis-radius-2xl);
    border-top-right-radius: var(--hcis-radius-2xl);
}
.hcis-drawer--ttb {
    width: 100vw;
    max-width: 54rem;
    max-height: 88vh;
    border-bottom-left-radius: var(--hcis-radius-2xl);
    border-bottom-right-radius: var(--hcis-radius-2xl);
}

/* Size Presets */
.hcis-drawer--sm { width: 22rem; }
.hcis-drawer--md { width: 30rem; }
.hcis-drawer--lg { width: 40rem; }
.hcis-drawer--xl { width: 52rem; }
.hcis-drawer--full { width: 100vw; height: 100vh; border-radius: 0 !important; }

/* Header Slim Luxury Layout with Signature HCIS Executive Gradient & Pattern */
.hcis-drawer__header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.8125rem 1.25rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    gap: 0.75rem;
    flex-shrink: 0;
    overflow: hidden;
    background: var(--hcis-white);
}

/* Executive Sentiment-Adaptive Header Gradients */
.hcis-drawer--sentiment-primary .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(99, 102, 241, 0.14), transparent 70%),
        radial-gradient(ellipse 300px 160px at 0% 110%, rgba(79, 70, 229, 0.06), transparent 70%),
        linear-gradient(180deg, #fbfcfe 0%, #f0f4ff 100%);
    border-bottom: 1px solid rgba(199, 210, 254, 0.7);
}

.hcis-drawer--sentiment-danger .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(239, 68, 68, 0.12), transparent 70%),
        radial-gradient(ellipse 300px 160px at 0% 110%, rgba(220, 38, 38, 0.05), transparent 70%),
        linear-gradient(180deg, #fffcfc 0%, var(--hcis-red-50) 100%);
    border-bottom: 1px solid rgba(254, 202, 202, 0.7);
}

.hcis-drawer--sentiment-warning .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(245, 158, 11, 0.12), transparent 70%),
        radial-gradient(ellipse 300px 160px at 0% 110%, rgba(217, 119, 6, 0.05), transparent 70%),
        linear-gradient(180deg, #fffdfa 0%, var(--hcis-amber-100) 100%);
    border-bottom: 1px solid rgba(253, 230, 138, 0.7);
}

.hcis-drawer--sentiment-success .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(16, 185, 129, 0.12), transparent 70%),
        radial-gradient(ellipse 300px 160px at 0% 110%, rgba(5, 150, 105, 0.05), transparent 70%),
        linear-gradient(180deg, #fcfdfd 0%, var(--hcis-green-50) 100%);
    border-bottom: 1px solid rgba(187, 247, 208, 0.7);
}

.hcis-drawer--sentiment-info .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(56, 189, 248, 0.12), transparent 70%),
        radial-gradient(ellipse 300px 160px at 0% 110%, rgba(2, 132, 199, 0.05), transparent 70%),
        linear-gradient(180deg, #fcfefe 0%, var(--hcis-sky-50) 100%);
    border-bottom: 1px solid rgba(186, 230, 253, 0.7);
}

.hcis-drawer--sentiment-neutral .hcis-drawer__header {
    background:
        radial-gradient(ellipse 350px 180px at 100% -10%, rgba(148, 163, 184, 0.1), transparent 70%),
        linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

/* Header Architectural Blueprint Pattern Overlay */
.hcis-drawer__header-pattern {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    opacity: 0.35;
    -webkit-mask-image: linear-gradient(225deg, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.3) 50%, transparent 90%);
    mask-image: linear-gradient(225deg, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.3) 50%, transparent 90%);
    background-image:
        radial-gradient(rgba(15, 23, 42, 0.12) 1px, transparent 1px),
        linear-gradient(to right, rgba(15, 23, 42, 0.03) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 23, 42, 0.03) 1px, transparent 1px);
    background-size: 16px 16px, 16px 16px, 16px 16px;
}

.hcis-drawer__header-main,
.hcis-drawer__close,
.hcis-drawer__header > *:not(.hcis-drawer__header-pattern) {
    position: relative;
    z-index: 1;
}

.hcis-drawer__header-main {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    flex: 1;
    gap: 2px;
}

.hcis-drawer__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
}

.hcis-drawer__icon {
    width: 1.625rem;
    height: 1.625rem;
    border-radius: var(--hcis-radius-md, 6px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    flex-shrink: 0;
}

.hcis-drawer__icon--neutral {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.hcis-drawer__icon--primary {
    background: #f5f7ff;
    color: var(--hcis-indigo-600);
    border: 1px solid var(--hcis-indigo-100);
    box-shadow: 0 2px 4px -1px rgba(79, 70, 229, 0.12);
}
.hcis-drawer__icon--danger {
    background: var(--hcis-red-50);
    color: var(--hcis-red-600);
    border: 1px solid var(--hcis-red-100);
    box-shadow: 0 2px 4px -1px rgba(220, 38, 38, 0.12);
}
.hcis-drawer__icon--warning {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
    border: 1px solid var(--hcis-amber-100);
    box-shadow: 0 2px 4px -1px rgba(217, 119, 6, 0.12);
}
.hcis-drawer__icon--success {
    background: var(--hcis-green-50);
    color: var(--hcis-green-600);
    border: 1px solid var(--hcis-green-100);
    box-shadow: 0 2px 4px -1px rgba(22, 163, 74, 0.12);
}
.hcis-drawer__icon--info {
    background: var(--hcis-sky-50);
    color: var(--hcis-sky-600);
    border: 1px solid var(--hcis-sky-100);
    box-shadow: 0 2px 4px -1px rgba(2, 132, 199, 0.12);
}

.hcis-drawer__title {
    font-family: var(--hcis-font-heading, inherit);
    font-size: 0.975rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
    line-height: 1.3;
    letter-spacing: -0.015em;
}

.hcis-drawer__description {
    font-size: 0.775rem;
    font-weight: 400;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin: 2px 0 0 0;
    line-height: 1.4;
    width: 100%;
    word-break: break-word;
}

/* Close Button Identik HcisDialog */
.hcis-drawer__close {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 160ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    flex-shrink: 0;
}

.hcis-drawer__close:hover {
    background-color: var(--hcis-surface-soft, var(--hcis-slate-100));
    border-color: var(--hcis-border-subtle, var(--hcis-slate-200));
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    transform: scale(1.05);
}

.hcis-drawer__close:active {
    transform: scale(0.95);
}

/* Body Maximum Space */
.hcis-drawer__body {
    padding: 1.25rem 1.375rem;
    color: var(--hcis-text, var(--hcis-slate-700));
    font-size: var(--hcis-font-size-sm, 0.875rem);
    line-height: 1.55;
    flex: 1;
}

.hcis-drawer__body--scrollable {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.8) transparent;
}

/* Footer Ringkas Identik HcisDialog */
.hcis-drawer__footer {
    padding: 0.875rem 1.375rem;
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50));
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.625rem;
    flex-shrink: 0;
}

/* Handle Mobile Drag */
.hcis-drawer__mobile-handle {
    width: 42px;
    height: 5px;
    background-color: var(--hcis-border-subtle, var(--hcis-slate-300));
    border-radius: 999px;
    margin: 10px auto 2px auto;
    flex-shrink: 0;
    opacity: 0.9;
}

/* Responsive Mobile Bottom Sheet Layout */
@media (max-width: 640px) {
    .hcis-drawer-overlay {
        align-items: flex-end !important;
        justify-content: center !important;
    }

    .hcis-drawer {
        width: 100vw !important;
        max-width: 100vw !important;
        height: auto !important;
        max-height: 88vh !important;
        border-radius: 24px 24px 0 0 !important;
        margin: 0 !important;
    }
}
</style>
