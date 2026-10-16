<template>
    <div
        ref="containerRef"
        class="hcis-cascader"
        :class="{
            'hcis-cascader--active': isOpen || isFocused,
            'hcis-cascader--disabled': disabled,
            'hcis-cascader--loading': loading,
            'hcis-cascader--has-value': hasValue,
        }"
    >
        <!-- Trigger Shell -->
        <div
            ref="triggerRef"
            class="hcis-cascader__trigger"
            :class="{
                'hcis-cascader__trigger--focused': isFocused || isOpen,
                'hcis-cascader__trigger--disabled': disabled,
                'hcis-cascader__trigger--loading': loading,
            }"
            @click="handleTriggerClick"
        >
            <span class="hcis-cascader__icon">
                <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon text-brand-600" />
                <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" class="hcis-lock-icon text-muted" />
                <HcisIcon v-else name="diagram-3-fill" size="xs" class="text-brand-600" />
            </span>

            <div class="hcis-cascader__display" :class="{ 'hcis-shimmer-pulse': loading }">
                <span v-if="selectedPathLabel" class="hcis-display-text">
                    {{ selectedPathLabel }}
                </span>
                <span v-else class="hcis-display-placeholder">
                    {{ placeholder }}
                </span>
            </div>

            <button
                v-if="clearable && hasValue && !disabled && !loading"
                type="button"
                class="hcis-cascader__clear"
                title="Bersihkan pilihan bertingkat"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span v-else class="hcis-cascader__arrow" @click.stop="togglePopover">
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- Desktop Mode: Multi-Column Cascading Popover Shell -->
        <Teleport v-if="!isMobile" to="body" :disabled="teleportTo === false">
            <Transition name="hcis-date-popover">
                <div
                    v-if="isOpen && !disabled && !loading"
                    ref="popoverRef"
                    class="hcis-cascader__popover"
                    :style="popoverStyle"
                    role="dialog"
                    aria-label="Pemilih Bertingkat (Cascader)"
                >
                    <div class="hcis-cascader-columns">
                        <!-- Column Level 1 -->
                        <div class="hcis-cascader-col">
                            <div
                                v-for="item in options"
                                :key="item.value"
                                class="hcis-cascader-item"
                                :class="{
                                    'is-active': activeLevel1 === item.value,
                                    'is-selected': isItemSelected(item, 1),
                                }"
                                @click="selectLevel1(item)"
                            >
                                <span class="hcis-cascader-item__label">{{ item.label }}</span>
                                <HcisIcon v-if="item.children && item.children.length" name="chevron-right" size="2xs" class="hcis-item-arrow" />
                            </div>
                        </div>

                        <!-- Column Level 2 (if Level 1 has children) -->
                        <div v-if="level2Options.length" class="hcis-cascader-col">
                            <div
                                v-for="item in level2Options"
                                :key="item.value"
                                class="hcis-cascader-item"
                                :class="{
                                    'is-active': activeLevel2 === item.value,
                                    'is-selected': isItemSelected(item, 2),
                                }"
                                @click="selectLevel2(item)"
                            >
                                <span class="hcis-cascader-item__label">{{ item.label }}</span>
                                <HcisIcon v-if="item.children && item.children.length" name="chevron-right" size="2xs" class="hcis-item-arrow" />
                            </div>
                        </div>

                        <!-- Column Level 3 (if Level 2 has children) -->
                        <div v-if="level3Options.length" class="hcis-cascader-col">
                            <div
                                v-for="item in level3Options"
                                :key="item.value"
                                class="hcis-cascader-item"
                                :class="{
                                    'is-selected': isItemSelected(item, 3),
                                }"
                                @click="selectLevel3(item)"
                            >
                                <span class="hcis-cascader-item__label">{{ item.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Mobile Mode: Native HcisDrawer Bottom Sheet -->
        <HcisDrawer
            v-else
            v-model="isOpen"
            direction="btt"
            :title="placeholder"
            :show-close="true"
        >
            <div class="hcis-mobile-cascader-content">
                <div class="hcis-cascader-columns hcis-cascader-columns--mobile">
                    <div class="hcis-cascader-col">
                        <div
                            v-for="item in options"
                            :key="item.value"
                            class="hcis-cascader-item"
                            :class="{ 'is-active': activeLevel1 === item.value }"
                            @click="selectLevel1(item)"
                        >
                            <span class="hcis-cascader-item__label">{{ item.label }}</span>
                            <HcisIcon v-if="item.children && item.children.length" name="chevron-right" size="2xs" />
                        </div>
                    </div>

                    <div v-if="level2Options.length" class="hcis-cascader-col">
                        <div
                            v-for="item in level2Options"
                            :key="item.value"
                            class="hcis-cascader-item"
                            :class="{ 'is-active': activeLevel2 === item.value }"
                            @click="selectLevel2(item)"
                        >
                            <span class="hcis-cascader-item__label">{{ item.label }}</span>
                            <HcisIcon v-if="item.children && item.children.length" name="chevron-right" size="2xs" />
                        </div>
                    </div>

                    <div v-if="level3Options.length" class="hcis-cascader-col">
                        <div
                            v-for="item in level3Options"
                            :key="item.value"
                            class="hcis-cascader-item"
                            @click="selectLevel3(item)"
                        >
                            <span class="hcis-cascader-item__label">{{ item.label }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </HcisDrawer>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisDrawer from './HcisDrawer.vue';

defineOptions({ name: 'HcisCascader' });

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih bertingkat...' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear']);

const containerRef = ref(null);
const triggerRef = ref(null);
const popoverRef = ref(null);

const isOpen = ref(false);
const isFocused = ref(false);
const isMobile = ref(false);

const activeLevel1 = ref('');
const activeLevel2 = ref('');

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '28rem',
    zIndex: '99999',
});

const hasValue = computed(() => Array.isArray(props.modelValue) && props.modelValue.length > 0);

const level2Options = computed(() => {
    if (!activeLevel1.value) return [];
    const parent = props.options.find((o) => o.value === activeLevel1.value);
    return parent && parent.children ? parent.children : [];
});

const level3Options = computed(() => {
    if (!activeLevel2.value || !level2Options.value.length) return [];
    const parent = level2Options.value.find((o) => o.value === activeLevel2.value);
    return parent && parent.children ? parent.children : [];
});

const selectedPathLabel = computed(() => {
    if (!hasValue.value) return '';
    const labels = [];
    let currentOptions = props.options;

    for (const val of props.modelValue) {
        const found = currentOptions.find((o) => o.value === val);
        if (found) {
            labels.push(found.label);
            currentOptions = found.children || [];
        } else {
            labels.push(val);
        }
    }
    return labels.join(' / ');
});

function isItemSelected(item, level) {
    if (!hasValue.value) return false;
    return props.modelValue[level - 1] === item.value;
}

function selectLevel1(item) {
    activeLevel1.value = item.value;
    activeLevel2.value = '';
    if (!item.children || !item.children.length) {
        emit('update:modelValue', [item.value]);
        emit('change', [item.value]);
        closePopover();
    }
}

function selectLevel2(item) {
    activeLevel2.value = item.value;
    if (!item.children || !item.children.length) {
        emit('update:modelValue', [activeLevel1.value, item.value]);
        emit('change', [activeLevel1.value, item.value]);
        closePopover();
    }
}

function selectLevel3(item) {
    emit('update:modelValue', [activeLevel1.value, activeLevel2.value, item.value]);
    emit('change', [activeLevel1.value, activeLevel2.value, item.value]);
    closePopover();
}

function checkMobile() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

function syncActiveFromProps() {
    if (hasValue.value) {
        activeLevel1.value = props.modelValue[0] || '';
        activeLevel2.value = props.modelValue[1] || '';
    }
}

watch(
    () => props.modelValue,
    () => {
        if (!isOpen.value) {
            syncActiveFromProps();
        }
    },
    { immediate: true, deep: true }
);

function updatePopoverPosition() {
    if (!isOpen.value) return;
    checkMobile();
    if (isMobile.value || !triggerRef.value) return;

    const rect = triggerRef.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const popoverHeight = 260;

    let top = rect.bottom + 6;
    if (spaceBelow < popoverHeight && rect.top > popoverHeight) {
        top = rect.top - popoverHeight - 6;
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${Math.max(8, top)}px`,
        left: `${Math.max(8, rect.left)}px`,
        width: '28rem',
        zIndex: '99999',
    };
}

function togglePopover() {
    if (props.disabled || props.loading) return;
    if (isOpen.value) closePopover();
    else openPopover();
}

function openPopover() {
    if (props.disabled || props.loading) return;
    syncActiveFromProps();
    isOpen.value = true;
    updatePopoverPosition();
    nextTick(() => updatePopoverPosition());
}

function closePopover() {
    isOpen.value = false;
}

function handleTriggerClick() {
    if (!isOpen.value && !props.disabled && !props.loading) openPopover();
}

function handleClear() {
    if (props.disabled || props.loading) return;
    activeLevel1.value = '';
    activeLevel2.value = '';
    const emptyVal = [];
    emit('update:modelValue', emptyVal);
    emit('change', emptyVal);
    emit('clear');
}

function handleGlobalClick(e) {
    if (
        containerRef.value &&
        !containerRef.value.contains(e.target) &&
        popoverRef.value &&
        !popoverRef.value.contains(e.target)
    ) {
        closePopover();
    }
}

onMounted(() => {
    checkMobile();
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleGlobalClick, true);
        window.addEventListener('resize', updatePopoverPosition);
        window.addEventListener('scroll', updatePopoverPosition, true);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleGlobalClick, true);
        window.removeEventListener('resize', updatePopoverPosition);
        window.removeEventListener('scroll', updatePopoverPosition, true);
    }
});
</script>

<style scoped>
.hcis-cascader {
    position: relative;
    display: inline-block;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

/* Trigger Shell */
.hcis-cascader__trigger {
    display: flex;
    align-items: center;
    width: 100%;
    height: 2.625rem;
    padding: 0 0.75rem;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background-color: var(--hcis-surface, var(--hcis-white));
    box-shadow: 0 1px 2px 0 rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.04);
    transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
    cursor: pointer;
}

.hcis-cascader__trigger:hover:not(.hcis-cascader__trigger--disabled):not(.hcis-cascader__trigger--loading) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 2px 6px -1px rgba(99, 102, 241, 0.1), inset 0 1px 1px 0 rgba(255, 255, 255, 0.8);
}

.hcis-cascader__trigger--focused {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb, 79 70 229) / 0.1) !important;
    background-color: var(--hcis-surface, var(--hcis-white));
}

/* Signature Frosted Disabled State */
.hcis-cascader__trigger--disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: not-allowed !important;
    box-shadow: none !important;
}

.hcis-cascader__trigger--disabled .hcis-display-text,
.hcis-cascader__trigger--disabled .hcis-display-placeholder {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
}

.hcis-cascader__trigger--loading {
    cursor: wait !important;
    border-color: var(--hcis-brand-300);
}

.hcis-spin-icon {
    animation: hcis-spin 1s linear infinite;
}

@keyframes hcis-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.hcis-shimmer-pulse {
    animation: hcis-pulse-glow 1.5s ease-in-out infinite;
}

@keyframes hcis-pulse-glow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

.hcis-cascader__icon {
    display: flex;
    align-items: center;
    margin-right: 0.5rem;
}

.hcis-cascader__display {
    flex: 1;
    min-width: 0;
}

.hcis-display-text {
    font-size: 0.825rem;
    font-weight: 600;
    color: var(--hcis-text-strong);
}

.hcis-display-placeholder {
    font-size: 0.825rem;
    color: var(--hcis-text-muted);
    font-weight: 500;
}

.hcis-cascader__clear,
.hcis-cascader__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0 0.125rem;
}

.hcis-cascader__clear:hover {
    color: var(--hcis-danger-600);
}

/* Popover Panel (Luxury Floating Glass) */
.hcis-cascader__popover {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.14), 0 4px 12px -2px rgba(15, 23, 42, 0.06), 0 0 0 1px rgba(255, 255, 255, 0.8) inset;
    padding: 0.5rem;
    box-sizing: border-box;
}

.hcis-cascader-columns {
    display: flex;
    gap: 0.5rem;
    max-height: 240px;
}

.hcis-cascader-columns--mobile {
    max-height: 340px;
    flex-direction: column;
}

.hcis-cascader-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    overflow-y: auto;
    border-right: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    padding-right: 0.35rem;
    scrollbar-width: thin;
}

.hcis-cascader-col:last-child {
    border-right: none;
    padding-right: 0;
}

.hcis-cascader-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.625rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--hcis-text-strong);
    cursor: pointer;
    transition: background-color 150ms ease, color 150ms ease;
}

.hcis-cascader-item:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.hcis-cascader-item.is-active,
.hcis-cascader-item.is-selected {
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-weight: 700;
}

.hcis-item-arrow {
    color: var(--hcis-text-muted);
}
</style>
