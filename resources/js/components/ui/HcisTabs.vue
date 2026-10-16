<template>
    <div
        class="hcis-tabs-wrapper"
        :class="[
            `hcis-tabs--variant-${variant}`,
            `hcis-tabs--size-${size}`,
            `hcis-tabs--${orientation}`,
            {
                'hcis-tabs--block': block,
                'is-collapsed': isVisuallyCollapsed,
                'has-scroll-left': canScrollLeft,
                'has-scroll-right': canScrollRight,
            }
        ]"
    >
        <!-- Vertical navigation becomes a compact picker on mobile. -->
        <div
            v-if="useMobilePicker"
            class="hcis-tabs__mobile-picker"
            @keydown.esc="mobilePickerOpen = false"
        >
            <button
                type="button"
                class="hcis-tabs__picker-trigger"
                :class="{ 'is-open': mobilePickerOpen }"
                :aria-expanded="mobilePickerOpen"
                :aria-controls="pickerListId"
                aria-haspopup="listbox"
                @click="mobilePickerOpen = !mobilePickerOpen"
            >
                <span class="hcis-tabs__picker-icon" aria-hidden="true">
                    <HcisIcon v-if="activeTab?.icon" :name="activeTab.icon" size="md" />
                    <span v-else>{{ inisial(activeTab?.label) }}</span>
                </span>
                <span class="hcis-tabs__picker-copy">
                    <span class="hcis-tabs__picker-eyebrow">Menu aktif</span>
                    <strong class="hcis-tabs__picker-label">{{ activeTab?.label }}</strong>
                </span>
                <HcisBadge
                    v-if="activeTab?.badge !== undefined && activeTab?.badge !== null"
                    :label="String(activeTab.badge)"
                    :variant="activeTab.badgeVariant || 'primary'"
                    size="sm"
                    class="hcis-tabs__picker-badge"
                />
                <span class="hcis-tabs__picker-action">
                    <span>Pilih</span>
                    <HcisIcon name="chevron-down" size="xs" class="hcis-tabs__picker-chevron" />
                </span>
            </button>

            <Transition name="hcis-picker-menu">
                <div
                    v-if="mobilePickerOpen"
                    :id="pickerListId"
                    class="hcis-tabs__picker-menu"
                    role="listbox"
                    :aria-label="ariaLabel"
                >
                    <button
                        v-for="tab in tabs"
                        :key="`picker-${tab.key}`"
                        type="button"
                        role="option"
                        class="hcis-tabs__picker-option"
                        :class="{ 'is-active': tab.key === modelValue }"
                        :aria-selected="tab.key === modelValue"
                        :disabled="tab.disabled"
                        @click="selectFromPicker(tab)"
                    >
                        <span class="hcis-tabs__picker-option-icon" aria-hidden="true">
                            <HcisIcon v-if="tab.icon" :name="tab.icon" size="sm" />
                            <span v-else>{{ inisial(tab.label) }}</span>
                        </span>
                        <span class="hcis-tabs__picker-option-label">{{ tab.label }}</span>
                        <HcisBadge
                            v-if="tab.badge !== undefined && tab.badge !== null"
                            :label="String(tab.badge)"
                            :variant="tab.badgeVariant || 'primary'"
                            size="sm"
                        />
                        <HcisIcon
                            v-if="tab.key === modelValue"
                            name="check-circle"
                            size="sm"
                            class="hcis-tabs__picker-check"
                        />
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Horizontal Scroll Left Button -->
        <button
            v-if="orientation === 'horizontal' && canScrollLeft"
            type="button"
            class="hcis-tabs__scroll-btn hcis-tabs__scroll-btn--left"
            aria-label="Gulir tab ke kiri"
            @click="scrollTrack('left')"
        >
            <HcisIcon name="chevron-left" size="xs" />
        </button>

        <!-- Tab Header Track (Horizontal or Vertical) -->
        <div
            v-if="!useMobilePicker"
            ref="scrollTrackRef"
            class="hcis-tabs__track"
            @scroll="checkScrollState"
            @wheel.passive="onWheelScroll"
        >
            <div
                ref="trackRef"
                class="hcis-tabs"
                role="tablist"
                :aria-label="ariaLabel"
                :aria-orientation="visualOrientation"
                @keydown="onKeydown"
            >
                <!-- Animated Sliding Active Indicator -->
                <div
                    v-if="hasIndicator"
                    class="hcis-tabs__indicator"
                    :style="indicatorStyle"
                    aria-hidden="true"
                />

                <!-- Tab Buttons -->
                <template v-for="tab in tabs" :key="tab.key">
                    <!-- Tooltip Wrapper when Vertical & Collapsed -->
                    <HcisTooltip
                        v-if="isVisuallyCollapsed"
                        :content="tab.label"
                        placement="right"
                        theme="dark"
                    >
                        <button
                            :ref="el => setTabRef(tab.key, el)"
                            type="button"
                            role="tab"
                            :id="`tab-${tab.key}`"
                            :aria-controls="`panel-${tab.key}`"
                            class="hcis-tabs__tab"
                            :class="[
                                tab.class,
                                {
                                    'is-active': tab.key === modelValue,
                                    'is-disabled': tab.disabled,
                                }
                            ]"
                            :aria-selected="tab.key === modelValue"
                            :tabindex="tab.key === modelValue ? 0 : -1"
                            :disabled="tab.disabled"
                            @click="onSelect(tab)"
                        >
                            <!-- Leading Icon -->
                            <slot :name="`icon-${tab.key}`" :tab="tab">
                                <HcisIcon v-if="tab.icon" :name="tab.icon" size="md" class="hcis-tabs__icon" />
                                <span
                                    v-else-if="isVisuallyCollapsed"
                                    class="hcis-tabs__inisial"
                                    aria-hidden="true"
                                >{{ inisial(tab.label) }}</span>
                            </slot>

                            <!-- Label -->
                            <slot :name="`tab-${tab.key}`" :tab="tab">
                                <span class="hcis-tabs__label-text">{{ tab.label }}</span>
                            </slot>

                            <!-- Badge -->
                            <HcisBadge
                                v-if="tab.badge !== undefined && tab.badge !== null"
                                :label="String(tab.badge)"
                                :variant="tab.badgeVariant || (tab.key === modelValue && (variant === 'pills' || orientation === 'vertical') ? 'neutral' : 'primary')"
                                size="sm"
                                class="hcis-tabs__badge"
                            />
                        </button>
                    </HcisTooltip>

                    <!-- Normal Button when not collapsed -->
                    <button
                        v-else
                        :ref="el => setTabRef(tab.key, el)"
                        type="button"
                        role="tab"
                        :id="`tab-${tab.key}`"
                        :aria-controls="`panel-${tab.key}`"
                        class="hcis-tabs__tab"
                        :class="[
                            tab.class,
                            {
                                'is-active': tab.key === modelValue,
                                'is-disabled': tab.disabled,
                            }
                        ]"
                        :aria-selected="tab.key === modelValue"
                        :tabindex="tab.key === modelValue ? 0 : -1"
                        :disabled="tab.disabled"
                        @click="onSelect(tab)"
                    >
                        <!-- Leading Icon -->
                        <slot :name="`icon-${tab.key}`" :tab="tab">
                            <HcisIcon v-if="tab.icon" :name="tab.icon" size="md" class="hcis-tabs__icon" />
                            <span
                                v-else-if="isVisuallyCollapsed"
                                class="hcis-tabs__inisial"
                                aria-hidden="true"
                            >{{ inisial(tab.label) }}</span>
                        </slot>

                        <!-- Label -->
                        <slot :name="`tab-${tab.key}`" :tab="tab">
                            <span class="hcis-tabs__label-text">{{ tab.label }}</span>
                        </slot>

                        <!-- Badge -->
                        <HcisBadge
                            v-if="tab.badge !== undefined && tab.badge !== null"
                            :label="String(tab.badge)"
                            :variant="tab.badgeVariant || (tab.key === modelValue && (variant === 'pills' || orientation === 'vertical') ? 'neutral' : 'primary')"
                            size="sm"
                            class="hcis-tabs__badge"
                        />
                    </button>
                </template>
            </div>

            <!-- Collapse Toggle Button for Vertical Orientation -->
            <div v-if="orientation === 'vertical' && collapsible" class="hcis-tabs__collapse-bar">
                <button
                    type="button"
                    class="hcis-tabs__collapse-btn"
                    :class="{ 'is-collapsed': internalCollapsed }"
                    :title="internalCollapsed ? 'Perluas Navigasi' : 'Kecilkan Navigasi'"
                    @click="toggleCollapse"
                >
                    <HcisIcon name="chevron-left" size="xs" class="hcis-collapse-icon" />
                    <span class="hcis-tabs__collapse-text">Kecilkan</span>
                </button>
            </div>
        </div>

        <!-- Horizontal Scroll Right Button -->
        <button
            v-if="orientation === 'horizontal' && canScrollRight"
            type="button"
            class="hcis-tabs__scroll-btn hcis-tabs__scroll-btn--right"
            aria-label="Gulir tab ke kanan"
            @click="scrollTrack('right')"
        >
            <HcisIcon name="chevron-right" size="xs" />
        </button>

        <!-- Tab Panels / Content Area -->
        <div v-if="$slots.default || hasPanelSlots" class="hcis-tabs__content">
            <Transition name="hcis-tab-panel" mode="out-in">
                <div
                    v-if="activeTab"
                    :key="activeTab.key"
                    :id="`panel-${activeTab.key}`"
                    class="hcis-tabs__panel"
                    role="tabpanel"
                    :aria-labelledby="`tab-${activeTab.key}`"
                >
                    <slot :name="`panel-${activeTab.key}`" :tab="activeTab" />
                </div>
            </Transition>
            <slot />
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, useSlots, useId, onMounted, onBeforeUnmount, nextTick } from 'vue';
import HcisBadge from './HcisBadge.vue';
import HcisIcon from './HcisIcon.vue';
import HcisTooltip from './HcisTooltip.vue';

defineOptions({ name: 'HcisTabs' });

const props = defineProps({
    modelValue: { type: String, required: true },
    tabs: { type: Array, required: true },
    variant: {
        type: String,
        default: 'underline',
        validator: (v) => ['underline', 'segmented', 'enclosed', 'pills', 'glow', 'header'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    orientation: {
        type: String,
        default: 'horizontal',
        validator: (v) => ['horizontal', 'vertical'].includes(v),
    },
    block: { type: Boolean, default: false },
    ariaLabel: { type: String, default: 'Navigasi Tab' },
    collapsible: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:collapsed', 'change', 'toggle-collapse']);

const internalCollapsed = ref(props.collapsed);
const isCompactViewport = ref(
    typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(max-width: 768px)').matches
);
const mobilePickerOpen = ref(false);
const pickerListId = `hcis-tabs-picker-${useId()}`;
const activeTab = computed(() => props.tabs.find((tab) => tab.key === props.modelValue) || props.tabs[0] || null);
const useMobilePicker = computed(() => props.orientation === 'vertical' && isCompactViewport.value);
const isVisuallyCollapsed = computed(
    () => props.orientation === 'vertical' && internalCollapsed.value && !isCompactViewport.value
);
const visualOrientation = computed(() => (
    props.orientation === 'vertical' && isCompactViewport.value ? 'horizontal' : props.orientation
));

watch(
    () => props.collapsed,
    (val) => {
        internalCollapsed.value = val;
    }
);

function toggleCollapse() {
    internalCollapsed.value = !internalCollapsed.value;
    emit('update:collapsed', internalCollapsed.value);
    emit('toggle-collapse', internalCollapsed.value);
}

function selectFromPicker(tab) {
    if (tab.disabled) return;
    onSelect(tab);
    mobilePickerOpen.value = false;
}

const slots = useSlots();

const hasPanelSlots = computed(() => {
    return props.tabs.some((t) => Boolean(slots[`panel-${t.key}`]));
});

/* Tab Element References & Dynamic Indicator Positioning */
const trackRef = ref(null);
const scrollTrackRef = ref(null);
const tabRefs = ref({});

function setTabRef(key, el) {
    if (el) {
        tabRefs.value[key] = el;
    } else {
        delete tabRefs.value[key];
    }
}

const hasIndicator = ref(true);
const indicatorStyle = ref({
    transform: 'translate3d(0, 0, 0)',
    width: '0px',
    height: '0px',
    opacity: 0,
});

function updateIndicator() {
    if (!trackRef.value) return;
    const activeKey = props.modelValue;
    const activeEl = tabRefs.value[activeKey];
    const trackEl = trackRef.value;

    if (!activeEl) {
        indicatorStyle.value = { ...indicatorStyle.value, opacity: 0 };
        return;
    }

    const trackRect = trackEl.getBoundingClientRect();
    const activeRect = activeEl.getBoundingClientRect();

    if (activeRect.width === 0 && activeRect.height === 0) {
        indicatorStyle.value = { ...indicatorStyle.value, opacity: 0 };
        return;
    }

    let left = activeRect.left - trackRect.left + trackEl.scrollLeft;
    let top = activeRect.top - trackRect.top + trackEl.scrollTop;
    let width = activeRect.width;
    let height = activeRect.height;

    const isHorizontalLayout = isTrackHorizontal();

    if (props.variant === 'underline' || props.variant === 'header') {
        if (isHorizontalLayout) {
            top = activeRect.top - trackRect.top + activeRect.height - 2.5;
            height = 2.5;
        } else {
            left = activeRect.left - trackRect.left;
            width = 3.5;
        }
    }

    indicatorStyle.value = {
        transform: `translate3d(${Math.round(left)}px, ${Math.round(top)}px, 0)`,
        width: `${Math.round(width)}px`,
        height: `${Math.round(height)}px`,
        opacity: 1,
    };
}

/* Horizontal Scroll state & helpers */
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

function checkScrollState() {
    if (!scrollTrackRef.value || !isTrackHorizontal()) {
        canScrollLeft.value = false;
        canScrollRight.value = false;
        return;
    }
    const el = scrollTrackRef.value;
    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft < (el.scrollWidth - el.clientWidth - 4);
    updateIndicator();
}

function onWheelScroll(event) {
    const el = scrollTrackRef.value;
    if (!el || el.scrollWidth <= el.clientWidth) return;

    if (!isTrackHorizontal()) return;

    // Keep mouse-wheel navigation useful for overflowed tab strips. The
    // listener is passive, so the page keeps its native scroll behavior at
    // either edge of the strip.
    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
    if (delta) el.scrollLeft += delta;
}

function scrollTrack(direction) {
    if (!scrollTrackRef.value) return;
    const distance = scrollTrackRef.value.clientWidth * 0.6;
    scrollTrackRef.value.scrollBy({
        left: direction === 'left' ? -distance : distance,
        behavior: 'smooth',
    });
}

function scrollToActiveTab() {
    const activeEl = tabRefs.value[props.modelValue];
    if (activeEl && scrollTrackRef.value && isTrackHorizontal()) {
        if (typeof activeEl.scrollIntoView === 'function') {
            activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }
    }
}

function onSelect(tab) {
    if (tab.disabled) return;
    emit('update:modelValue', tab.key);
    emit('change', tab);
    nextTick(() => {
        scrollToActiveTab();
        updateIndicator();
    });
}

/* Keyboard Navigation (ARIA) */
function onKeydown(e) {
    const enabledTabs = props.tabs.filter((t) => !t.disabled);
    if (!enabledTabs.length) return;

    const currentIndex = enabledTabs.findIndex((t) => t.key === props.modelValue);
    let targetTab = null;

    if (isTrackHorizontal()) {
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            const nextIndex = (currentIndex + 1) % enabledTabs.length;
            targetTab = enabledTabs[nextIndex];
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            const prevIndex = (currentIndex - 1 + enabledTabs.length) % enabledTabs.length;
            targetTab = enabledTabs[prevIndex];
        }
    } else {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const nextIndex = (currentIndex + 1) % enabledTabs.length;
            targetTab = enabledTabs[nextIndex];
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = (currentIndex - 1 + enabledTabs.length) % enabledTabs.length;
            targetTab = enabledTabs[prevIndex];
        }
    }

    if (e.key === 'Home') {
        e.preventDefault();
        targetTab = enabledTabs[0];
    } else if (e.key === 'End') {
        e.preventDefault();
        targetTab = enabledTabs[enabledTabs.length - 1];
    }

    if (targetTab) {
        onSelect(targetTab);
        nextTick(() => {
            const el = tabRefs.value[targetTab.key];
            if (el && typeof el.focus === 'function') el.focus();
        });
    }
}

/* Lifecycle & Watchers */
let resizeObserver = null;

function isTrackHorizontal() {
    if (!trackRef.value || typeof window === 'undefined') {
        return visualOrientation.value === 'horizontal';
    }
    const flexDirection = getComputedStyle(trackRef.value).flexDirection;
    return flexDirection ? flexDirection.includes('row') : visualOrientation.value === 'horizontal';
}

function syncResponsiveLayout() {
    isCompactViewport.value = typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(max-width: 768px)').matches;
    if (!isCompactViewport.value) mobilePickerOpen.value = false;
    updateIndicator();
    checkScrollState();
}

watch(
    () => [props.modelValue, props.tabs, props.variant, props.orientation, internalCollapsed.value],
    () => {
        nextTick(() => {
            updateIndicator();
            checkScrollState();
        });
    },
    { deep: true }
);

onMounted(() => {
    nextTick(() => {
        syncResponsiveLayout();
        if (window.ResizeObserver && trackRef.value) {
            resizeObserver = new ResizeObserver(() => {
                updateIndicator();
                checkScrollState();
            });
            resizeObserver.observe(trackRef.value);
            if (scrollTrackRef.value) {
                resizeObserver.observe(scrollTrackRef.value);
            }
        }
        window.addEventListener('resize', syncResponsiveLayout);
    });
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
    }
    window.removeEventListener('resize', syncResponsiveLayout);
});

/**
 * Dua huruf pertama dari dua kata pertama — "Umpan balik" jadi "UB".
 * Dipakai hanya sebagai cadangan saat rel dilipat dan tabnya tidak berikon.
 */
function inisial(label) {
    return String(label ?? '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((kata) => kata[0] ?? '')
        .join('')
        .toUpperCase();
}
</script>

<style>
.hcis-tabs-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-tabs-wrapper.hcis-tabs--vertical {
    flex-direction: row;
    align-items: flex-start;
    gap: 1.5rem;
    max-width: 100%;
    min-width: 0;
}

/* Scroll Buttons Styling */
.hcis-tabs__scroll-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    box-shadow: var(--hcis-shadow-sm);
    color: var(--hcis-text-strong);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs__scroll-btn:hover {
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    border-color: var(--hcis-brand-200);
    transform: translateY(-50%) scale(1.08);
}

.hcis-tabs__scroll-btn--left {
    left: -14px;
}

.hcis-tabs__scroll-btn--right {
    right: -14px;
}

/* Track Scroll Container */
.hcis-tabs__track {
    position: relative;
    width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.hcis-tabs__track::-webkit-scrollbar {
    display: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__track {
    width: 15rem;
    flex-shrink: 0;
    overflow-y: auto;
    max-height: calc(100vh - var(--hcis-topbar-height, 4.5rem) - 2rem);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.625rem;
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    box-shadow: var(--hcis-shadow-xs);
    transition: width 250ms var(--hcis-ease), padding 250ms var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-sticky {
    position: sticky;
    top: calc(var(--hcis-topbar-height, 4rem) + 1.25rem);
    align-self: start;
    z-index: var(--hcis-z-raised, 10);
}

/* Collapsed State for Vertical Tabs */
.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__track {
    width: 3.75rem;
    padding: 0.625rem 0.375rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__label-text,
.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__collapse-text {
    white-space: nowrap;
    opacity: 1;
    max-width: 200px;
    transition: opacity 180ms var(--hcis-ease), max-width 250ms var(--hcis-ease);
    overflow: hidden;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__label-text,
.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__collapse-text {
    opacity: 0;
    max-width: 0;
    margin: 0;
    padding: 0;
    pointer-events: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tooltip-wrapper {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__tab {
    justify-content: center;
    align-items: center;
    padding: 0.625rem 0;
    gap: 0;
    width: 100%;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__inisial {
    margin: 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__label-text {
    display: none;
}

.hcis-tabs__inisial {
    display: inline-grid;
    place-items: center;
    min-width: 1.25rem;
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-black);
    letter-spacing: 0.02em;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__badge {
    position: absolute;
    top: 4px;
    right: 4px;
    font-size: 0.6rem;
    padding: 1px 4px;
}

/* Tab List Container */
.hcis-tabs {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--hcis-space-1, 0.25rem);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs {
    flex-direction: column;
    width: 100%;
    gap: 0.375rem;
}

/* Dynamic Animated Active Indicator */
.hcis-tabs__indicator {
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
    transition: transform 320ms var(--hcis-ease),
                width 320ms var(--hcis-ease),
                height 320ms var(--hcis-ease),
                opacity 180ms ease;
    will-change: transform, width, height;
}

/* Indicator styles per Variant */
.hcis-tabs--variant-underline .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    border-radius: var(--hcis-radius-pill);
    box-shadow: 0 2px 8px rgb(var(--hcis-brand-600-rgb) / 0.4);
}

.hcis-tabs--variant-segmented .hcis-tabs__indicator {
    background: var(--hcis-surface);
    border-radius: calc(var(--hcis-radius-lg) - 3px);
    box-shadow: 0 3px 10px rgb(var(--hcis-slate-900-rgb) / 0.08), 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
    border: 1px solid rgb(var(--hcis-slate-900-rgb) / 0.05);
}

.hcis-tabs--variant-pills .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    border-radius: var(--hcis-radius-pill);
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-600-rgb) / 0.35);
}

.hcis-tabs--variant-glow .hcis-tabs__indicator {
    background: var(--hcis-brand-50);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-brand-200);
}

.hcis-tabs--variant-enclosed .hcis-tabs__indicator {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    border-bottom: 1px solid var(--hcis-surface);
    border-radius: var(--hcis-radius-md) var(--hcis-radius-md) 0 0;
}

.hcis-tabs--variant-header .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    border-radius: var(--hcis-radius-pill);
    box-shadow: none !important;
}

/* Vertical Tabs Specific Indicator Overrides */
.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__indicator {
    border-radius: var(--hcis-radius-md);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__indicator {
    background: var(--hcis-brand-50);
    border-left: 3.5px solid var(--hcis-brand-600);
    border-radius: 0 var(--hcis-radius-md) var(--hcis-radius-md) 0;
    box-shadow: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    box-shadow: 0 4px 12px rgb(var(--hcis-brand-600-rgb) / 0.25);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__indicator {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    box-shadow: var(--hcis-shadow-xs);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-glow .hcis-tabs__indicator {
    background: var(--hcis-brand-50);
    border: 1px solid var(--hcis-brand-200);
}

/* Individual Tab Button */
.hcis-tabs__tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border: 0;
    background: transparent;
    color: var(--hcis-text-muted);
    cursor: pointer;
    font: inherit;
    font-weight: 600;
    user-select: none;
    outline: none;
    /* Rail tab MENCIUT lewat .is-collapsed di leluhurnya, jadi padding/gap/width
       memang dianimasikan dengan sengaja di sini — bukan efek samping "all". */
    transition-property: var(--hcis-transition-props), font-weight, gap, outline-offset, padding, width;
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs__tab:focus-visible {
    box-shadow: 0 0 0 2px var(--hcis-brand-400);
}

.hcis-tabs__tab:active:not(.is-disabled) {
    transform: scale(0.97);
}

.hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-700);
    transform: none;
}

.hcis-tabs--variant-segmented .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-text-strong);
    background: transparent;
    transform: none;
}

.hcis-tabs--variant-pills .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-700);
    background: transparent;
    transform: none;
}

.hcis-tabs--variant-glow .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-700);
    background: transparent;
    transform: none;
}

.hcis-tabs--variant-underline .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-700);
    border-bottom-color: transparent;
    transform: none;
}

.hcis-tabs-wrapper.hcis-tabs--variant-header,
.hcis-tabs--variant-header .hcis-tabs__track,
.hcis-tabs--variant-header .hcis-tabs {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.hcis-tabs--variant-header .hcis-tabs__tab::before,
.hcis-tabs--variant-header .hcis-tabs__tab:hover::before {
    display: none !important;
    content: none !important;
    opacity: 0 !important;
}

.hcis-tabs--variant-header .hcis-tabs__tab {
    padding: 0.7rem 1.2rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--hcis-slate-600);
    border: none !important;
    border-radius: var(--hcis-radius-md) var(--hcis-radius-md) 0 0;
    background: transparent !important;
    min-height: 2.6rem;
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: none !important;
    transition: color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-tabs--variant-header .hcis-tabs__tab .hcis-tabs__icon {
    color: var(--hcis-slate-400);
    transition: color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-600) !important;
    background: transparent !important;
    box-shadow: none !important;
    transform: none !important;
}

.hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) .hcis-tabs__icon {
    color: var(--hcis-brand-600);
}

.hcis-tabs--variant-header .hcis-tabs__tab,
.hcis-tabs--variant-header .hcis-tabs__tab:hover,
.hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    box-shadow: none !important;
    text-shadow: none !important;
}

.hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    color: var(--hcis-brand-700);
    font-weight: 700;
    background-color: transparent !important;
}

.hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon {
    color: var(--hcis-brand-600);
}

.hcis-tabs--variant-header .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    height: 2.5px;
    border-radius: var(--hcis-radius-pill);
    box-shadow: none !important;
}

.hcis-tabs__tab.is-active:hover {
    transform: none;
    cursor: default;
}

.hcis-tabs__tab.is-active {
    color: var(--hcis-brand-700);
    font-weight: 700;
}

.hcis-tabs--variant-pills .hcis-tabs__tab.is-active,
.hcis-tabs--variant-pills.hcis-tabs--vertical .hcis-tabs__tab.is-active,
.hcis-tabs--variant-segmented.hcis-tabs--vertical .hcis-tabs__tab.is-active {
    color: var(--hcis-white);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab {
    position: relative;
    width: 100%;
    justify-content: flex-start;
    text-align: left;
    padding: 0.625rem 0.875rem;
    border-radius: var(--hcis-radius-md);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__tab:hover:not(.is-disabled),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__tab:hover:not(.is-disabled),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__tab:hover:not(.is-disabled),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-glow .hcis-tabs__tab:hover:not(.is-disabled),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-enclosed .hcis-tabs__tab:hover:not(.is-disabled) {
    border-bottom-width: 1px;
    border-bottom-style: solid;
    border-bottom-color: transparent;
    transform: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-glow .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-enclosed .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    background: transparent;
    color: var(--hcis-brand-700);
    border-color: transparent;
    border-bottom-width: 1px;
    border-bottom-style: solid;
    border-bottom-color: transparent;
    transform: none;
    box-shadow: 0 8px 20px -16px rgb(var(--hcis-brand-700-rgb) / 0.7);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab.is-active:hover {
    background: transparent;
    border-bottom-width: 1px;
    border-bottom-style: solid;
    border-bottom-color: transparent;
    transform: none;
    cursor: default;
}

.hcis-tabs__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-right: 0.25rem;
    font-size: 1.05em;
    line-height: 1;
    transition: margin 250ms var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__icon {
    margin-right: 0;
}

.hcis-tabs__badge {
    pointer-events: none;
}

/* Collapse Toggle Button Styling */
.hcis-tabs__collapse-bar {
    width: 100%;
    padding-top: 0.5rem;
    margin-top: auto;
    border-top: 1px solid var(--hcis-border-subtle);
}

.hcis-tabs__collapse-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    height: 2.25rem;
    padding: 0 0.5rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    color: var(--hcis-text-strong);
    font-size: 0.775rem;
    font-weight: 700;
    cursor: pointer;
    transition-property: var(--hcis-transition-props), padding;
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__collapse-btn {
    gap: 0;
    padding: 0;
    justify-content: center;
}

.hcis-tabs__collapse-btn:hover {
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    border-color: var(--hcis-brand-200);
}

.hcis-collapse-icon {
    transition: transform 250ms var(--hcis-ease);
}

.hcis-tabs__collapse-btn.is-collapsed .hcis-collapse-icon {
    transform: rotate(180deg);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__content {
    flex: 1 1 0%;
    min-width: 0;
    width: 100%;
    margin-top: 0;
    transition: flex 250ms var(--hcis-ease);
}

.hcis-tabs__panel {
    width: 100%;
}

.hcis-tabs__content {
    position: relative;
    margin-top: 1rem;
    width: 100%;
}

/* Tab Panel Fluid Fade & Slide Animations */
.hcis-tab-panel-enter-active {
    transition: opacity 220ms var(--hcis-ease), transform 220ms var(--hcis-ease);
}

.hcis-tab-panel-leave-active {
    transition: opacity 90ms ease-out;
}

.hcis-tab-panel-enter-from {
    opacity: 0;
    transform: translateY(4px);
}

.hcis-tab-panel-leave-to {
    opacity: 0;
    transform: none;
}

/* Vertical Tab Panel Slide Transition */
.hcis-tabs--vertical .hcis-tab-panel-enter-from {
    opacity: 0;
    transform: translateX(6px);
}

.hcis-tabs--vertical .hcis-tab-panel-leave-to {
    opacity: 0;
    transform: none;
}

/* ==========================================================================
   Responsive Mobile & Tablet Adjustments (<=768px & <=640px)
   ========================================================================== */
@media (max-width: 768px) {
    /* Transform vertical tabs to full-width top bar on mobile */
    .hcis-tabs-wrapper.hcis-tabs--vertical {
        flex-direction: column;
        width: 100%;
        gap: 1rem;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__track {
        width: 100%;
        max-height: none;
        overflow-x: auto;
        overflow-y: hidden;
        flex-direction: row;
        padding: 0.375rem;
        border-radius: var(--hcis-radius-lg);
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs {
        flex-direction: row;
        width: max-content;
        gap: 0.375rem;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab {
        width: auto;
        white-space: nowrap;
        padding: 0.5rem 0.875rem;
        min-height: 40px;
        flex-shrink: 0;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__collapse-bar {
        display: none;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__label-text {
        opacity: 1;
        max-width: none;
    }

    /* Underline vertical variant on mobile turns into horizontal bottom accent */
    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__indicator {
        border-left: none;
        background: var(--hcis-brand-600);
        border-radius: var(--hcis-radius-pill);
    }
}

@media (max-width: 640px) {
    /* Hide manual scroll buttons on small touch screens where thumb swipe is native */
    .hcis-tabs__scroll-btn {
        display: none;
    }

    .hcis-tabs__tab {
        padding-left: 0.875rem;
        padding-right: 0.875rem;
        min-height: 42px;
    }

    /* Edge gradient masks for mobile horizontal overflow */
    .hcis-tabs-wrapper.has-scroll-left::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 1.5rem;
        background: linear-gradient(90deg, var(--hcis-surface), transparent);
        z-index: var(--hcis-z-raised);
        pointer-events: none;
    }

    .hcis-tabs-wrapper.has-scroll-right::after {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 1.5rem;
        background: linear-gradient(-90deg, var(--hcis-surface), transparent);
        z-index: var(--hcis-z-raised);
        pointer-events: none;
    }
}
</style>

<style>
/* --------------------------------------------------------------------------
   Visual refinement layer
   -------------------------------------------------------------------------- */
.hcis-tabs-wrapper {
    isolation: isolate;
}

.hcis-tabs-wrapper .hcis-tabs__track {
    min-width: 0;
    overscroll-behavior-inline: contain;
    scroll-behavior: smooth;
}

.hcis-tabs-wrapper .hcis-tabs {
    isolation: isolate;
}

.hcis-tabs-wrapper .hcis-tabs__indicator {
    transition-duration: var(--hcis-duration-panel);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs-wrapper .hcis-tabs__tab {
    min-width: 0;
    min-height: 2.625rem;
    padding: 0.5rem 0.875rem;
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-md);
    line-height: 1.25;
    letter-spacing: -0.005em;
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-tabs-wrapper .hcis-tabs__tab::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(
        115deg,
        rgb(var(--hcis-brand-500-rgb) / 0.095),
        rgb(var(--hcis-brand-500-rgb) / 0.025) 72%,
        transparent
    );
    opacity: 0;
    pointer-events: none;
    transition: opacity 240ms var(--hcis-ease);
}

.hcis-tabs-wrapper .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active)::before {
    opacity: 1;
}

.hcis-tabs--size-sm .hcis-tabs__tab {
    min-height: 2.25rem;
    padding: 0.375rem 0.75rem;
    font-size: var(--hcis-font-size-xs);
}

.hcis-tabs--size-lg .hcis-tabs__tab {
    min-height: 3rem;
    padding: 0.625rem 1rem;
    font-size: var(--hcis-font-size-md);
}

.hcis-tabs-wrapper .hcis-tabs__tab:focus-visible {
    outline: 2px solid var(--hcis-brand-400);
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgb(var(--hcis-brand-500-rgb) / 0.14);
}

/* Premium interactions keep the control geometry completely still. */
.hcis-tabs-wrapper .hcis-tabs__tab:active:not(.is-disabled),
.hcis-tabs-wrapper .hcis-tabs__tab:hover:not(.is-disabled) {
    transform: none;
}

.hcis-tabs-wrapper .hcis-tabs__tab.is-disabled {
    color: var(--hcis-text-subtle);
    cursor: not-allowed;
    opacity: 0.48;
}

.hcis-tabs-wrapper .hcis-tabs__icon {
    margin-right: 0;
    color: currentColor;
}

.hcis-tabs-wrapper .hcis-tabs__badge {
    flex: none;
    margin-left: 0.125rem;
}

.hcis-tabs-wrapper .hcis-tabs__label-text {
    min-width: 0;
}

/* Horizontal navigation ---------------------------------------------------- */
.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__track {
    padding: 0.375rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    background:
        linear-gradient(135deg, rgb(var(--hcis-brand-500-rgb) / 0.045), transparent 44%),
        var(--hcis-surface-soft);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs {
    width: max-content;
    min-width: 100%;
    gap: 0.25rem;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__tab {
    gap: 0.5rem;
    color: var(--hcis-text-muted);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    border-color: transparent;
    background: transparent;
    color: var(--hcis-brand-700);
    box-shadow: 0 8px 18px -16px rgb(var(--hcis-brand-700-rgb) / 0.72);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__tab.is-active {
    color: var(--hcis-brand-800);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__icon {
    display: inline-grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    flex: 0 0 1.75rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-sm);
    background: rgb(var(--hcis-white-rgb) / 0.72);
    color: var(--hcis-text-muted);
    box-shadow: var(--hcis-shadow-xs);
    transition: color 180ms var(--hcis-ease),
                background-color 180ms var(--hcis-ease),
                border-color 180ms var(--hcis-ease),
                box-shadow 180ms var(--hcis-ease),
                transform 180ms var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) .hcis-tabs__icon {
    border-color: var(--hcis-border-subtle);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    transform: none;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal .hcis-tabs__tab.is-active .hcis-tabs__icon {
    border-color: transparent;
    background: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
    box-shadow: 0 4px 10px rgb(var(--hcis-brand-600-rgb) / 0.2);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--block .hcis-tabs {
    width: 100%;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--block .hcis-tabs__tab {
    flex: 1 1 0;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-underline .hcis-tabs__track {
    padding: 0;
    border-bottom: 1px solid var(--hcis-border-subtle);
    border-top: 0;
    border-right: 0;
    border-left: 0;
    border-radius: 0;
    background: transparent;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-underline .hcis-tabs__tab {
    min-height: 3rem;
    padding-inline: 0.875rem;
    border-radius: var(--hcis-radius-md) var(--hcis-radius-md) 0 0;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-underline .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    background: transparent;
    box-shadow: none;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-underline .hcis-tabs__tab.is-active .hcis-tabs__icon {
    border-color: var(--hcis-brand-200);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    box-shadow: none;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-segmented .hcis-tabs__track,
.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-glow .hcis-tabs__track {
    box-shadow: inset 0 1px 2px rgb(var(--hcis-slate-900-rgb) / 0.025);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-segmented .hcis-tabs__indicator {
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.11);
    background: var(--hcis-surface);
    box-shadow: 0 5px 14px rgb(var(--hcis-slate-900-rgb) / 0.08), var(--hcis-shadow-xs);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-glow .hcis-tabs__indicator {
    border-color: var(--hcis-brand-200);
    background: linear-gradient(135deg, var(--hcis-brand-50), rgb(var(--hcis-brand-100-rgb) / 0.72));
    box-shadow: 0 5px 18px rgb(var(--hcis-brand-500-rgb) / 0.13);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs {
    gap: 0.375rem;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs__tab {
    border-radius: var(--hcis-radius-pill);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs__track {
    border-color: transparent;
    background: transparent;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs__indicator {
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.14);
    background: linear-gradient(135deg, var(--hcis-brand-600), var(--hcis-brand-700));
    box-shadow: 0 7px 16px rgb(var(--hcis-brand-600-rgb) / 0.22);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs__tab.is-active .hcis-tabs__icon {
    border-color: rgb(var(--hcis-white-rgb) / 0.2);
    background: rgb(var(--hcis-white-rgb) / 0.14);
    color: var(--hcis-text-on-brand);
    box-shadow: none;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-enclosed .hcis-tabs__track {
    padding-bottom: 0;
    border-bottom: 1px solid var(--hcis-border);
    border-top: 0;
    border-right: 0;
    border-left: 0;
    border-radius: 0;
    background: transparent;
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-enclosed .hcis-tabs__indicator {
    border-color: var(--hcis-border);
    border-bottom-color: var(--hcis-surface);
    background: var(--hcis-surface);
    box-shadow: 0 -3px 10px rgb(var(--hcis-slate-900-rgb) / 0.035);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-segmented .hcis-tabs__tab.is-active,
.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-enclosed .hcis-tabs__tab.is-active,
.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-glow .hcis-tabs__tab.is-active {
    color: var(--hcis-brand-700);
}

.hcis-tabs-wrapper.hcis-tabs--horizontal.hcis-tabs--variant-pills .hcis-tabs__tab.is-active {
    color: var(--hcis-text-on-brand);
}

/* Scroll affordances stay inside the component, so parent cards never clip. */
.hcis-tabs-wrapper .hcis-tabs__scroll-btn {
    z-index: var(--hcis-z-raised);
    top: 1.5rem;
    width: 2rem;
    height: 2rem;
    border-color: var(--hcis-border);
    box-shadow: var(--hcis-shadow-md);
}

.hcis-tabs-wrapper.hcis-tabs--size-sm .hcis-tabs__scroll-btn {
    top: 1.3125rem;
}

.hcis-tabs-wrapper.hcis-tabs--size-lg .hcis-tabs__scroll-btn {
    top: 1.6875rem;
}

.hcis-tabs-wrapper .hcis-tabs__scroll-btn--left {
    left: 0.375rem;
}

.hcis-tabs-wrapper .hcis-tabs__scroll-btn--right {
    right: 0.375rem;
}

.hcis-tabs-wrapper.has-scroll-left .hcis-tabs__track {
    padding-left: 2.625rem;
}

.hcis-tabs-wrapper.has-scroll-right .hcis-tabs__track {
    padding-right: 2.625rem;
}

/* Vertical navigation ------------------------------------------------------ */
.hcis-tabs-wrapper.hcis-tabs--vertical {
    gap: var(--hcis-space-6);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__track {
    width: 16rem;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-2);
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.14);
    border-radius: var(--hcis-radius-xl);
    background:
        linear-gradient(145deg, rgb(var(--hcis-brand-500-rgb) / 0.065), transparent 42%),
        var(--hcis-surface);
    box-shadow: var(--hcis-shadow-card);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs {
    gap: 0.25rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab {
    min-height: 3rem;
    gap: 0.75rem;
    padding: 0.4375rem 0.625rem;
    border-color: transparent;
    border-radius: var(--hcis-radius-lg);
    color: var(--hcis-text-muted);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--size-sm .hcis-tabs__tab {
    min-height: 2.625rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--size-lg .hcis-tabs__tab {
    min-height: 3.375rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__inisial {
    width: 2.125rem;
    height: 2.125rem;
    flex: 0 0 2.125rem;
    margin: 0;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-md);
    background: rgb(var(--hcis-white-rgb) / 0.72);
    color: var(--hcis-text-muted);
    box-shadow: var(--hcis-shadow-xs);
    transition: color 180ms var(--hcis-ease),
                background-color 180ms var(--hcis-ease),
                border-color 180ms var(--hcis-ease),
                box-shadow 180ms var(--hcis-ease),
                transform 180ms var(--hcis-ease);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__label-text {
    flex: 1 1 auto;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__badge {
    margin-left: auto;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    border-width: 1px;
    border-style: solid;
    border-color: transparent;
    background: transparent;
    color: var(--hcis-brand-700);
    box-shadow: 0 8px 20px -16px rgb(var(--hcis-brand-700-rgb) / 0.7);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) .hcis-tabs__inisial {
    border-color: var(--hcis-border-subtle);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
    transform: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab.is-active .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab.is-active .hcis-tabs__inisial {
    border-color: transparent;
    background: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
    box-shadow: 0 5px 12px rgb(var(--hcis-brand-600-rgb) / 0.24);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__tab.is-active,
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-glow .hcis-tabs__tab.is-active,
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-enclosed .hcis-tabs__tab.is-active {
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.12);
    background: rgb(var(--hcis-brand-500-rgb) / 0.075);
    color: var(--hcis-brand-800);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__indicator {
    border: 0;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-brand-600);
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.1);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__indicator {
    border-color: var(--hcis-brand-200);
    background: var(--hcis-brand-50);
    box-shadow: var(--hcis-shadow-sm);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__tab.is-active {
    color: var(--hcis-brand-800);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__indicator {
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.12);
    background: linear-gradient(135deg, var(--hcis-brand-600), var(--hcis-brand-700));
    box-shadow: 0 8px 18px rgb(var(--hcis-brand-600-rgb) / 0.24);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__tab.is-active {
    color: var(--hcis-text-on-brand);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__tab.is-active .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-pills .hcis-tabs__tab.is-active .hcis-tabs__inisial {
    border-color: rgb(var(--hcis-white-rgb) / 0.2);
    background: rgb(var(--hcis-white-rgb) / 0.14);
    box-shadow: none;
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__collapse-bar {
    padding-top: var(--hcis-space-2);
    border-top-color: rgb(var(--hcis-brand-500-rgb) / 0.12);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__collapse-btn {
    min-height: 2.375rem;
    border-color: transparent;
    background: rgb(var(--hcis-brand-500-rgb) / 0.065);
    color: var(--hcis-text-muted);
}

.hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__collapse-btn:hover {
    border-color: var(--hcis-brand-200);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-700);
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__track {
    width: 4.5rem;
    padding-inline: 0.5rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__tab {
    padding-inline: 0.625rem;
}

.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__icon,
.hcis-tabs-wrapper.hcis-tabs--vertical.is-collapsed .hcis-tabs__inisial {
    margin-inline: auto;
}

.hcis-tabs-wrapper .hcis-tabs__content {
    min-width: 0;
}

.hcis-tabs-wrapper .hcis-tabs__panel {
    min-width: 0;
}

/* Mobile picker for vertical navigation ------------------------------------ */
.hcis-tabs__mobile-picker {
    position: relative;
    width: 100%;
    min-width: 0;
}

.hcis-tabs__picker-trigger {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 3.75rem;
    gap: 0.75rem;
    padding: 0.5rem 0.625rem;
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.18);
    border-radius: var(--hcis-radius-xl);
    background:
        linear-gradient(135deg, rgb(var(--hcis-brand-500-rgb) / 0.09), transparent 52%),
        var(--hcis-surface);
    color: var(--hcis-text);
    box-shadow: var(--hcis-shadow-sm);
    cursor: pointer;
    text-align: left;
    transition: border-color 180ms var(--hcis-ease),
                box-shadow 180ms var(--hcis-ease),
                background-color 180ms var(--hcis-ease);
}

.hcis-tabs__picker-trigger:hover,
.hcis-tabs__picker-trigger.is-open {
    border-color: var(--hcis-brand-300);
    box-shadow: 0 5px 14px rgb(var(--hcis-brand-600-rgb) / 0.12);
}

.hcis-tabs__picker-trigger:focus-visible {
    outline: 2px solid var(--hcis-brand-400);
    outline-offset: 2px;
}

.hcis-tabs__picker-icon,
.hcis-tabs__picker-option-icon {
    display: inline-grid;
    place-items: center;
    flex: none;
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.18);
    background: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
    box-shadow: 0 5px 12px rgb(var(--hcis-brand-600-rgb) / 0.22);
}

.hcis-tabs__picker-icon {
    width: 2.625rem;
    height: 2.625rem;
    border-radius: var(--hcis-radius-lg);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-black);
}

.hcis-tabs__picker-copy {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    flex-direction: column;
    gap: 0.125rem;
}

.hcis-tabs__picker-eyebrow {
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-2xs);
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.2;
    text-transform: uppercase;
}

.hcis-tabs__picker-label {
    overflow: hidden;
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-sm);
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-tabs__picker-badge {
    flex: none;
}

.hcis-tabs__picker-action {
    display: inline-flex;
    align-items: center;
    flex: none;
    gap: 0.375rem;
    padding: 0.375rem 0.5rem;
    border-radius: var(--hcis-radius-md);
    background: rgb(var(--hcis-brand-500-rgb) / 0.08);
    color: var(--hcis-brand-700);
    font-size: var(--hcis-font-size-2xs);
    font-weight: 800;
}

.hcis-tabs__picker-chevron {
    transition: transform 220ms var(--hcis-ease);
}

.hcis-tabs__picker-trigger.is-open .hcis-tabs__picker-chevron {
    transform: rotate(180deg);
}

.hcis-tabs__picker-menu {
    display: grid;
    gap: 0.25rem;
    width: 100%;
    max-height: min(22rem, 52vh);
    margin-top: 0.5rem;
    padding: 0.375rem;
    overflow-y: auto;
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    box-shadow: var(--hcis-shadow-lg);
}

.hcis-tabs__picker-option {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 3rem;
    gap: 0.625rem;
    padding: 0.4375rem 0.625rem;
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-lg);
    background: transparent;
    color: var(--hcis-text-muted);
    cursor: pointer;
    text-align: left;
    transition: color 160ms var(--hcis-ease),
                background-color 160ms var(--hcis-ease),
                border-color 160ms var(--hcis-ease);
}

.hcis-tabs__picker-option:hover:not(:disabled) {
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.12);
    background: rgb(var(--hcis-brand-500-rgb) / 0.065);
    color: var(--hcis-brand-700);
}

.hcis-tabs__picker-option:focus-visible {
    outline: 2px solid var(--hcis-brand-400);
    outline-offset: -2px;
}

.hcis-tabs__picker-option.is-active {
    border-color: var(--hcis-brand-200);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-800);
}

.hcis-tabs__picker-option:disabled {
    cursor: not-allowed;
    opacity: 0.46;
}

.hcis-tabs__picker-option-icon {
    width: 2rem;
    height: 2rem;
    border-color: var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    color: var(--hcis-text-muted);
    box-shadow: none;
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-black);
}

.hcis-tabs__picker-option.is-active .hcis-tabs__picker-option-icon {
    border-color: transparent;
    background: var(--hcis-brand-600);
    color: var(--hcis-text-on-brand);
}

.hcis-tabs__picker-option-label {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    font-size: var(--hcis-font-size-sm);
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-tabs__picker-check {
    flex: none;
    color: var(--hcis-brand-600);
}

.hcis-picker-menu-enter-active,
.hcis-picker-menu-leave-active {
    transition: opacity 180ms var(--hcis-ease),
                transform 180ms var(--hcis-ease);
    transform-origin: top center;
}

.hcis-picker-menu-enter-from,
.hcis-picker-menu-leave-to {
    opacity: 0;
    transform: translateY(-0.375rem) scale(0.985);
}

/* Responsive behavior ------------------------------------------------------ */
@media (max-width: 768px) {
    .hcis-tabs-wrapper.hcis-tabs--vertical {
        gap: var(--hcis-space-3);
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__track {
        display: none !important;
    }

    .hcis-tabs-wrapper:not(.hcis-tabs--vertical) .hcis-tabs__track {
        width: 100%;
        padding: 3px;
        border-color: var(--hcis-border-subtle);
        border-radius: var(--hcis-radius-lg);
        background: var(--hcis-surface-soft);
        box-shadow: none;
        gap: 2px;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs {
        min-width: max-content;
        gap: 2px;
    }

    .hcis-tabs-wrapper .hcis-tabs__tab,
    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab {
        min-height: 2.125rem;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: var(--hcis-font-size-xs);
        border-radius: calc(var(--hcis-radius-lg) - 3px);
        scroll-snap-align: start;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__icon,
    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__inisial {
        width: 1.25rem;
        height: 1.25rem;
        flex-basis: 1.25rem;
        font-size: 0.875rem;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__label-text {
        max-width: 8.5rem;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__indicator {
        border: 0;
        border-radius: var(--hcis-radius-pill);
        background: var(--hcis-brand-600);
        box-shadow: 0 2px 7px rgb(var(--hcis-brand-600-rgb) / 0.28);
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-underline .hcis-tabs__tab.is-active,
    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-segmented .hcis-tabs__tab.is-active,
    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-glow .hcis-tabs__tab.is-active,
    .hcis-tabs-wrapper.hcis-tabs--vertical.hcis-tabs--variant-enclosed .hcis-tabs__tab.is-active {
        background: transparent;
    }

    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__content {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .hcis-tabs-wrapper .hcis-tabs__track,
    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__track {
        scroll-snap-type: x proximity;
    }

    .hcis-tabs-wrapper .hcis-tabs__tab,
    .hcis-tabs-wrapper.hcis-tabs--vertical .hcis-tabs__tab {
        min-height: 2rem;
        padding: 0.25rem 0.5rem;
    }

    .hcis-tabs-wrapper.has-scroll-left .hcis-tabs__track,
    .hcis-tabs-wrapper.has-scroll-right .hcis-tabs__track {
        padding-inline: 0.25rem;
    }

    .hcis-tabs-wrapper.has-scroll-left .hcis-tabs__track {
        -webkit-mask-image: linear-gradient(90deg, transparent, var(--hcis-surface) 1rem, var(--hcis-surface));
        mask-image: linear-gradient(90deg, transparent, var(--hcis-surface) 1rem, var(--hcis-surface));
    }

    .hcis-tabs-wrapper.has-scroll-right .hcis-tabs__track {
        -webkit-mask-image: linear-gradient(90deg, var(--hcis-surface), var(--hcis-surface) calc(100% - 1rem), transparent);
        mask-image: linear-gradient(90deg, var(--hcis-surface), var(--hcis-surface) calc(100% - 1rem), transparent);
    }

    .hcis-tabs-wrapper.has-scroll-left.has-scroll-right .hcis-tabs__track {
        -webkit-mask-image: linear-gradient(90deg, transparent, var(--hcis-surface) 1rem, var(--hcis-surface) calc(100% - 1rem), transparent);
        mask-image: linear-gradient(90deg, transparent, var(--hcis-surface) 1rem, var(--hcis-surface) calc(100% - 1rem), transparent);
    }

    .hcis-tabs-wrapper.has-scroll-left::before,
    .hcis-tabs-wrapper.has-scroll-right::after {
        display: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hcis-tabs-wrapper *,
    .hcis-tabs-wrapper *::before,
    .hcis-tabs-wrapper *::after {
        scroll-behavior: auto;
        transition-duration: 1ms;
        animation-duration: 1ms;
    }
}
</style>
