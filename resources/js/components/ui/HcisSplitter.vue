<template>
    <div
        ref="containerRef"
        class="hcis-splitter-container"
        :class="[
            `hcis-splitter--${orientation}`,
            `hcis-splitter--${variant}`,
            {
                'is-dragging': isDragging,
                'is-mobile': isMobileView,
                'has-focus-pane': focusedPane !== null,
            }
        ]"
    >
        <!-- Ultra-Sleek Floating Presets & Stats Bar -->
        <div v-if="showPresetToolbar && !isMobileView" class="hcis-splitter-toolbar">
            <div class="hcis-splitter-toolbar__left">
                <span class="hcis-splitter-toolbar__label">
                    <HcisIcon name="layout-split" size="2xs" class="text-indigo-600 me-1" />
                    Rasio Pemisah
                </span>
                <div class="hcis-splitter-presets">
                    <button
                        v-for="preset in ratioPresets"
                        :key="preset.ratio"
                        type="button"
                        class="hcis-preset-pill"
                        :class="{ 'is-active': Math.round(currentRatio) === preset.ratio && focusedPane === null }"
                        :title="preset.label"
                        @click="setRatio(preset.ratio)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <div class="hcis-splitter-toolbar__right">
                <!-- Live Size Percentage Gauge -->
                <div class="hcis-splitter-size-badge">
                    <span>{{ Math.round(currentRatio) }}%</span>
                    <span class="opacity-40 px-0.5">:</span>
                    <span>{{ 100 - Math.round(currentRatio) }}%</span>
                </div>

                <!-- Reset Ratio Button -->
                <button
                    type="button"
                    class="hcis-toolbar-action-btn"
                    title="Reset Rasio Pembatas Ke Awal"
                    @click="resetRatio"
                >
                    <HcisIcon name="arrow-counterclockwise" size="2xs" />
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- Mobile Mode: Touch-Friendly Tab Bar Switcher -->
        <div v-if="isMobileView && showMobileTabs" class="hcis-splitter-mobile-tabs">
            <button
                v-for="(pane, idx) in paneInfos"
                :key="idx"
                type="button"
                class="hcis-mobile-tab-btn"
                :class="{ 'is-active': activeMobilePane === idx }"
                @click="activeMobilePane = idx"
            >
                <HcisIcon :name="pane.icon || (idx === 0 ? 'layout-sidebar' : 'window')" size="2xs" />
                <span>{{ pane.title || `Panel ${idx + 1}` }}</span>
            </button>
        </div>

        <!-- Splitter Main Workspace -->
        <div class="hcis-splitter-body">
            <!-- Pane 1 (Left / Top) -->
            <div
                class="hcis-splitter-pane-wrap hcis-pane-first"
                :class="{ 'is-focused-max': focusedPane === 0 }"
                :style="firstPaneStyle"
            >
                <!-- Focus Maximize Button Overlay -->
                <div v-if="allowMaximize" class="hcis-pane-focus-bar">
                    <button
                        type="button"
                        class="hcis-pane-maximize-btn"
                        :title="focusedPane === 0 ? 'Kembalikan Ukuran Panel' : 'Fokus Layar Penuh Panel Kiri'"
                        @click="toggleFocusPane(0)"
                    >
                        <HcisIcon :name="focusedPane === 0 ? 'fullscreen-exit' : 'arrows-angle-expand'" size="2xs" />
                    </button>
                </div>

                <div class="hcis-pane-inner">
                    <slot name="first">
                        <slot name="left" />
                    </slot>
                </div>
            </div>

            <!-- Sleek Unobtrusive Drag Handle Bar / Resizable Divider -->
            <div
                v-if="(!isMobileView || !showMobileTabs) && focusedPane === null"
                class="hcis-splitter-bar"
                :class="{ 'is-near-snap': isNearSnapPoint }"
                tabindex="0"
                role="separator"
                :aria-orientation="orientation"
                @mousedown="startDrag"
                @touchstart.prevent="startDrag"
                @dblclick="resetRatio"
                @keydown="handleKeyDown"
            >
                <!-- Floating Real-Time Drag Overlay Badge -->
                <div v-if="isDragging" class="hcis-splitter-drag-overlay">
                    <strong>{{ Math.round(currentRatio) }}%</strong>
                </div>

                <!-- Quick Collapse Arrow (First Pane) -->
                <button
                    v-if="collapsible"
                    type="button"
                    class="hcis-splitter-collapse-btn hcis-collapse-prev"
                    :title="isFirstCollapsed ? 'Buka Panel Kiri' : 'Lipat Panel Kiri'"
                    @click.stop="toggleFirstCollapse"
                >
                    <HcisIcon
                        :name="orientation === 'horizontal'
                            ? (isFirstCollapsed ? 'chevron-right' : 'chevron-left')
                            : (isFirstCollapsed ? 'chevron-down' : 'chevron-up')"
                        size="2xs"
                    />
                </button>

                <!-- Handle Grip Indicator -->
                <div class="hcis-splitter-grip">
                    <span class="hcis-grip-line" />
                </div>

                <!-- Quick Collapse Arrow (Second Pane) -->
                <button
                    v-if="collapsible"
                    type="button"
                    class="hcis-splitter-collapse-btn hcis-collapse-next"
                    :title="isSecondCollapsed ? 'Buka Panel Kanan' : 'Lipat Panel Kanan'"
                    @click.stop="toggleSecondCollapse"
                >
                    <HcisIcon
                        :name="orientation === 'horizontal'
                            ? (isSecondCollapsed ? 'chevron-left' : 'chevron-right')
                            : (isSecondCollapsed ? 'chevron-up' : 'chevron-down')"
                        size="2xs"
                    />
                </button>
            </div>

            <!-- Pane 2 (Right / Bottom) -->
            <div
                class="hcis-splitter-pane-wrap hcis-pane-second"
                :class="{ 'is-focused-max': focusedPane === 1 }"
                :style="secondPaneStyle"
            >
                <!-- Focus Maximize Button Overlay -->
                <div v-if="allowMaximize" class="hcis-pane-focus-bar">
                    <button
                        type="button"
                        class="hcis-pane-maximize-btn"
                        :title="focusedPane === 1 ? 'Kembalikan Ukuran Panel' : 'Fokus Layar Penuh Panel Kanan'"
                        @click="toggleFocusPane(1)"
                    >
                        <HcisIcon :name="focusedPane === 1 ? 'fullscreen-exit' : 'arrows-angle-expand'" size="2xs" />
                    </button>
                </div>

                <div class="hcis-pane-inner">
                    <slot name="second">
                        <slot name="right" />
                    </slot>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisSplitter' });

const props = defineProps({
    orientation: {
        type: String,
        default: 'horizontal',
        validator: (v) => ['horizontal', 'vertical'].includes(v),
    },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'flat', 'glass', 'cyber'].includes(v),
    },
    initialRatio: { type: Number, default: 30 },
    minRatio: { type: Number, default: 15 },
    maxRatio: { type: Number, default: 85 },
    collapsible: { type: Boolean, default: true },
    allowMaximize: { type: Boolean, default: true },
    showPresetToolbar: { type: Boolean, default: true },
    showMobileTabs: { type: Boolean, default: true },
    snapPoints: { type: Array, default: () => [25, 33, 50, 67, 75] },
    paneInfos: {
        type: Array,
        default: () => [
            { title: 'Navigasi Panel', icon: 'layout-sidebar' },
            { title: 'Konten Utama', icon: 'window' },
        ],
    },
});

const emit = defineEmits([
    'resize',
    'resize-end',
    'collapse-change',
    'focus-change',
]);

const ratioPresets = [
    { ratio: 25, label: '25:75' },
    { ratio: 33, label: '33:67' },
    { ratio: 50, label: '50:50' },
    { ratio: 67, label: '67:33' },
    { ratio: 75, label: '75:25' },
];

const containerRef = ref(null);
const currentRatio = ref(props.initialRatio);
const isDragging = ref(false);
const isFirstCollapsed = ref(false);
const isSecondCollapsed = ref(false);
const focusedPane = ref(null);
const isNearSnapPoint = ref(false);

/* Mobile View Detection */
const isMobileView = ref(false);
const activeMobilePane = ref(0);

function checkMobileView() {
    if (typeof window !== 'undefined') {
        isMobileView.value = window.innerWidth < 768;
    }
}

/* Computed Styles for Panes */
const firstPaneStyle = computed(() => {
    if (focusedPane.value === 0) {
        return { flex: '1 1 100%', width: '100%', zIndex: 10 };
    }
    if (focusedPane.value === 1) {
        return { flex: '0 0 0px', width: '0px', padding: 0, opacity: 0, pointerEvents: 'none' };
    }
    if (isMobileView.value && props.showMobileTabs) {
        return { display: activeMobilePane.value === 0 ? 'block' : 'none', flex: '1 1 100%', width: '100%' };
    }
    if (isFirstCollapsed.value) {
        return { flex: '0 0 0px', width: '0px', padding: 0, opacity: 0, pointerEvents: 'none' };
    }
    if (isSecondCollapsed.value) {
        return { flex: '1 1 100%', width: '100%' };
    }
    const pct = `${currentRatio.value}%`;
    return props.orientation === 'horizontal'
        ? { flex: `0 0 ${pct}`, width: pct }
        : { flex: `0 0 ${pct}`, height: pct };
});

const secondPaneStyle = computed(() => {
    if (focusedPane.value === 1) {
        return { flex: '1 1 100%', width: '100%', zIndex: 10 };
    }
    if (focusedPane.value === 0) {
        return { flex: '0 0 0px', width: '0px', padding: 0, opacity: 0, pointerEvents: 'none' };
    }
    if (isMobileView.value && props.showMobileTabs) {
        return { display: activeMobilePane.value === 1 ? 'block' : 'none', flex: '1 1 100%', width: '100%' };
    }
    if (isSecondCollapsed.value) {
        return { flex: '0 0 0px', width: '0px', padding: 0, opacity: 0, pointerEvents: 'none' };
    }
    if (isFirstCollapsed.value) {
        return { flex: '1 1 100%', width: '100%' };
    }
    return { flex: '1 1 0%' };
});

/* Drag Resizing & Magnetic Snap Engine */
function startDrag(e) {
    e.preventDefault();
    isDragging.value = true;
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchmove', onDrag, { passive: false });
    document.addEventListener('touchend', stopDrag);
}

function onDrag(e) {
    if (!isDragging.value || !containerRef.value) return;

    const rect = containerRef.value.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    let newPct = 0;
    if (props.orientation === 'horizontal') {
        const offset = clientX - rect.left;
        newPct = (offset / rect.width) * 100;
    } else {
        const offset = clientY - rect.top;
        newPct = (offset / rect.height) * 100;
    }

    newPct = Math.max(props.minRatio, Math.min(props.maxRatio, newPct));

    let snapped = false;
    if (props.snapPoints && props.snapPoints.length) {
        for (const sp of props.snapPoints) {
            if (Math.abs(newPct - sp) < 1.8) {
                newPct = sp;
                snapped = true;
                break;
            }
        }
    }
    isNearSnapPoint.value = snapped;

    currentRatio.value = Math.round(newPct * 10) / 10;
    emit('resize', { ratio: currentRatio.value, sizes: [currentRatio.value, 100 - currentRatio.value] });
}

function stopDrag() {
    if (isDragging.value) {
        isDragging.value = false;
        isNearSnapPoint.value = false;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
        document.removeEventListener('touchmove', onDrag);
        document.removeEventListener('touchend', stopDrag);
        emit('resize-end', { ratio: currentRatio.value, sizes: [currentRatio.value, 100 - currentRatio.value] });
    }
}

function setRatio(ratio) {
    focusedPane.value = null;
    isFirstCollapsed.value = false;
    isSecondCollapsed.value = false;
    currentRatio.value = ratio;
    emit('resize', { ratio: currentRatio.value, sizes: [currentRatio.value, 100 - currentRatio.value] });
}

function resetRatio() {
    focusedPane.value = null;
    currentRatio.value = props.initialRatio;
    isFirstCollapsed.value = false;
    isSecondCollapsed.value = false;
    emit('resize', { ratio: currentRatio.value, sizes: [currentRatio.value, 100 - currentRatio.value] });
}

function toggleFocusPane(index) {
    if (focusedPane.value === index) {
        focusedPane.value = null;
    } else {
        focusedPane.value = index;
    }
    emit('focus-change', { paneIndex: index, isMaximized: focusedPane.value === index });
}

function toggleFirstCollapse() {
    isFirstCollapsed.value = !isFirstCollapsed.value;
    if (isFirstCollapsed.value) isSecondCollapsed.value = false;
    emit('collapse-change', { pane: 'first', collapsed: isFirstCollapsed.value });
}

function toggleSecondCollapse() {
    isSecondCollapsed.value = !isSecondCollapsed.value;
    if (isSecondCollapsed.value) isFirstCollapsed.value = false;
    emit('collapse-change', { pane: 'second', collapsed: isSecondCollapsed.value });
}

function handleKeyDown(e) {
    if (['ArrowLeft', 'ArrowUp'].includes(e.key)) {
        currentRatio.value = Math.max(props.minRatio, currentRatio.value - 2);
        emit('resize', { ratio: currentRatio.value });
    } else if (['ArrowRight', 'ArrowDown'].includes(e.key)) {
        currentRatio.value = Math.min(props.maxRatio, currentRatio.value + 2);
        emit('resize', { ratio: currentRatio.value });
    } else if (e.key === 'Home') {
        toggleFirstCollapse();
    } else if (e.key === 'End') {
        toggleSecondCollapse();
    }
}

onMounted(() => {
    checkMobileView();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', checkMobileView);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', checkMobileView);
    }
});
</script>

<style scoped>
.hcis-splitter-container {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-height: 22rem;
    border-radius: var(--hcis-radius-xl);
    overflow: hidden;
    box-sizing: border-box;
    font-family: var(--hcis-font-body, inherit);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: ease;
}

.hcis-splitter--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 2px 8px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-splitter--glass {
    background: rgb(var(--hcis-white-rgb) / 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.8);
}

.hcis-splitter--flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-splitter--cyber {
    background: var(--hcis-slate-900);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.4);
    color: var(--hcis-slate-50);
}

/* Preset Toolbar */
.hcis-splitter-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.875rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    gap: 0.5rem;
}

.hcis-splitter-toolbar__left,
.hcis-splitter-toolbar__right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hcis-splitter-toolbar__label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.hcis-splitter-presets {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.hcis-preset-pill {
    padding: 2px 8px;
    border-radius: 6px;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: var(--hcis-white);
    font-size: 0.675rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
}

.hcis-preset-pill:hover {
    border-color: var(--hcis-brand-300, var(--hcis-brand-300));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-preset-pill.is-active {
    background: var(--hcis-brand-600, var(--hcis-brand-600));
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
    color: var(--hcis-white);
}

.hcis-splitter-size-badge {
    font-size: 0.7rem;
    font-weight: 800;
    font-family: monospace;
    padding: 2px 8px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

.hcis-toolbar-action-btn {
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: var(--hcis-white);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
}

.hcis-toolbar-action-btn:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
}

/* Mobile Tab Switcher */
.hcis-splitter-mobile-tabs {
    display: flex;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    padding: 0.375rem;
    gap: 0.375rem;
}

.hcis-mobile-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.5rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-mobile-tab-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    box-shadow: 0 1px 4px rgb(var(--hcis-slate-900-rgb) / 0.08);
}

/* Splitter Body & Inner Scroll Containment */
.hcis-splitter-body {
    display: flex;
    width: 100%;
    height: 100%;
    flex: 1;
    min-height: 0;
    position: relative;
}

.hcis-splitter--vertical .hcis-splitter-body {
    flex-direction: column;
}

.hcis-splitter-pane-wrap {
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
    min-width: 0;
    min-height: 0;
    transition: flex 220ms var(--hcis-ease), width 220ms var(--hcis-ease), height 220ms var(--hcis-ease);
}

.hcis-pane-inner {
    width: 100%;
    height: 100%;
    overflow: auto;
    box-sizing: border-box;
}

.hcis-splitter-pane-wrap.is-focused-max {
    border-radius: 12px;
}

.hcis-splitter.is-dragging .hcis-splitter-pane-wrap {
    transition: none;
    user-select: none;
}

/* Pane Maximize Overlay Button */
.hcis-pane-focus-bar {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    z-index: 30;
    opacity: 0;
    transition: opacity 150ms ease;
}

.hcis-splitter-pane-wrap:hover .hcis-pane-focus-bar,
.hcis-splitter-pane-wrap.is-focused-max .hcis-pane-focus-bar {
    opacity: 1;
}

.hcis-pane-maximize-btn {
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    background: rgb(var(--hcis-white-rgb) / 0.92);
    backdrop-filter: blur(var(--hcis-glass-blur-sm));
    color: var(--hcis-slate-600);
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.1);
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-pane-maximize-btn:hover {
    background: var(--hcis-brand-600, var(--hcis-brand-600));
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
    color: var(--hcis-white);
    transform: scale(1.1);
}

/* Premium Sleek Drag Handle Bar (6px) */
.hcis-splitter-bar {
    position: relative;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-border-subtle, var(--hcis-slate-200));
    transition: background 180ms ease, box-shadow 180ms ease, width 180ms ease, height 180ms ease;
    user-select: none;
    outline: none;
    flex-shrink: 0;
}

.hcis-splitter--horizontal .hcis-splitter-bar {
    width: 6px;
    cursor: col-resize;
    flex-direction: column;
}

.hcis-splitter--vertical .hcis-splitter-bar {
    height: 6px;
    cursor: row-resize;
    flex-direction: row;
}

.hcis-splitter-bar:hover,
.hcis-splitter-container.is-dragging .hcis-splitter-bar,
.hcis-splitter-bar:focus-visible {
    background: var(--hcis-brand-500, var(--hcis-brand-500));
    box-shadow: 0 0 14px rgb(var(--hcis-brand-500-rgb) / 0.45);
}

.hcis-splitter-bar.is-near-snap {
    background: var(--hcis-success-500) !important;
    box-shadow: 0 0 14px rgb(var(--hcis-success-500-rgb) / 0.6) !important;
}

/* Drag Overlay Badge */
.hcis-splitter-drag-overlay {
    position: absolute;
    top: -2rem;
    left: 50%;
    transform: translateX(-50%);
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 800;
    pointer-events: none;
    box-shadow: 0 4px 12px rgb(var(--hcis-black-rgb) / 0.2);
    white-space: nowrap;
    z-index: 50;
}

/* Subtle Line Grip */
.hcis-splitter-grip {
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-grip-line {
    border-radius: 9999px;
    background: rgb(var(--hcis-white-rgb) / 0.6);
    transition: background 150ms ease;
}

.hcis-splitter--horizontal .hcis-grip-line {
    width: 2px;
    height: 1.5rem;
}

.hcis-splitter--vertical .hcis-grip-line {
    height: 2px;
    width: 1.5rem;
}

.hcis-splitter-bar:hover .hcis-grip-line {
    background: var(--hcis-white);
}

/* Unobtrusive Collapse Arrow Buttons */
.hcis-splitter-collapse-btn {
    border: none;
    background: var(--hcis-white);
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    width: 1.1rem;
    height: 1.1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    margin: 2px;
    box-shadow: 0 1px 4px rgb(var(--hcis-black-rgb) / 0.15);
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
    opacity: 0;
}

.hcis-splitter-bar:hover .hcis-splitter-collapse-btn,
.hcis-splitter-bar:focus-visible .hcis-splitter-collapse-btn {
    opacity: 1;
}

.hcis-splitter-collapse-btn:hover {
    background: var(--hcis-brand-600, var(--hcis-brand-600));
    color: var(--hcis-white);
    transform: scale(1.25);
}
</style>
