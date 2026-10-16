<template>
    <div
        class="hcis-transfer"
        :class="{
            'hcis-transfer--disabled': disabled,
        }"
    >
        <!-- Left Source Panel -->
        <div class="hcis-transfer-panel">
            <div class="hcis-transfer-panel__header">
                <HcisCheckbox
                    :model-value="isLeftAllChecked"
                    :indeterminate="isLeftIndeterminate"
                    :disabled="disabled || !leftFilteredData.length"
                    @update:model-value="toggleCheckAllLeft"
                >
                    <span class="hcis-transfer-panel__title">{{ titles[0] || 'Daftar Tersedia' }}</span>
                </HcisCheckbox>
                <span class="hcis-transfer-panel__count">
                    {{ leftCheckedKeys.length }}/{{ leftFilteredData.length }}
                </span>
            </div>

            <!-- Filter Search Bar -->
            <div v-if="filterable" class="hcis-transfer-panel__filter">
                <HcisIcon name="search" size="2xs" class="hcis-filter-icon" />
                <input
                    v-model="leftSearchQuery"
                    type="text"
                    class="hcis-filter-input"
                    placeholder="Cari item..."
                    :disabled="disabled"
                />
            </div>

            <!-- Items List -->
            <div class="hcis-transfer-panel__body">
                <template v-if="leftFilteredData.length">
                    <label
                        v-for="item in leftFilteredData"
                        :key="item.key"
                        class="hcis-transfer-item"
                        :class="{ 'is-disabled': disabled || item.disabled }"
                    >
                        <HcisCheckbox
                            :model-value="leftCheckedKeys.includes(item.key)"
                            :disabled="disabled || item.disabled"
                            @update:model-value="toggleLeftItem(item.key)"
                        />
                        <span class="hcis-transfer-item__label">{{ item.label }}</span>
                    </label>
                </template>
                <div v-else class="hcis-transfer-empty">
                    Tidak ada data
                </div>
            </div>
        </div>

        <!-- Transfer Action Buttons -->
        <div class="hcis-transfer-actions">
            <button
                type="button"
                class="hcis-transfer-btn"
                :disabled="disabled || !leftCheckedKeys.length"
                title="Pindahkan ke Terpilih"
                @click="addToTarget"
            >
                <HcisIcon name="chevron-right" size="xs" />
            </button>
            <button
                type="button"
                class="hcis-transfer-btn"
                :disabled="disabled || !rightCheckedKeys.length"
                title="Pindahkan ke Tersedia"
                @click="removeFromTarget"
            >
                <HcisIcon name="chevron-left" size="xs" />
            </button>
        </div>

        <!-- Right Target Panel -->
        <div class="hcis-transfer-panel">
            <div class="hcis-transfer-panel__header">
                <HcisCheckbox
                    :model-value="isRightAllChecked"
                    :indeterminate="isRightIndeterminate"
                    :disabled="disabled || !rightFilteredData.length"
                    @update:model-value="toggleCheckAllRight"
                >
                    <span class="hcis-transfer-panel__title">{{ titles[1] || 'Daftar Terpilih' }}</span>
                </HcisCheckbox>
                <span class="hcis-transfer-panel__count">
                    {{ rightCheckedKeys.length }}/{{ rightFilteredData.length }}
                </span>
            </div>

            <!-- Filter Search Bar -->
            <div v-if="filterable" class="hcis-transfer-panel__filter">
                <HcisIcon name="search" size="2xs" class="hcis-filter-icon" />
                <input
                    v-model="rightSearchQuery"
                    type="text"
                    class="hcis-filter-input"
                    placeholder="Cari item..."
                    :disabled="disabled"
                />
            </div>

            <!-- Items List (Right Target Panel with Direct Drag-and-Drop) -->
            <div class="hcis-transfer-panel__body">
                <template v-if="rightFilteredData.length">
                    <div
                        v-for="(item, index) in rightFilteredData"
                        :key="item.key"
                        :draggable="draggable && !disabled && !item.disabled"
                        class="hcis-transfer-item hcis-transfer-item--target"
                        :class="{
                            'is-disabled': disabled || item.disabled,
                            'is-draggable': draggable && !disabled && !item.disabled,
                            'is-dragging': draggedTargetIndex === index,
                            'is-drag-over': dragOverTargetIndex === index,
                        }"
                        @dragstart="onTargetDragStart($event, item, index)"
                        @dragover.prevent="onTargetDragOver($event, item, index)"
                        @dragenter.prevent="dragOverTargetIndex = index"
                        @dragleave="onTargetDragLeave(index)"
                        @drop="onTargetDrop($event, item, index)"
                        @dragend="onTargetDragEnd"
                    >
                        <span
                            v-if="draggable && !disabled"
                            class="hcis-transfer-drag-handle"
                            title="Tarik untuk memindahkan urutan"
                        >
                            <HcisIcon name="grip-vertical" size="2xs" />
                        </span>

                        <span v-if="showOrderBadge" class="hcis-transfer-order-badge">
                            {{ index + 1 }}
                        </span>

                        <HcisCheckbox
                            :model-value="rightCheckedKeys.includes(item.key)"
                            :disabled="disabled || item.disabled"
                            @update:model-value="toggleRightItem(item.key)"
                        />

                        <span class="hcis-transfer-item__label" :title="item.label">{{ item.label }}</span>

                        <!-- Quick Mini Move Buttons on Hover -->
                        <div v-if="draggable && !disabled" class="hcis-transfer-item__actions">
                            <button
                                type="button"
                                class="hcis-transfer-mini-btn"
                                :disabled="index === 0"
                                title="Geser ke atas"
                                @click.stop="moveItem(index, -1)"
                            >
                                <HcisIcon name="chevron-up" size="2xs" />
                            </button>
                            <button
                                type="button"
                                class="hcis-transfer-mini-btn"
                                :disabled="index === rightFilteredData.length - 1"
                                title="Geser ke bawah"
                                @click.stop="moveItem(index, 1)"
                            >
                                <HcisIcon name="chevron-down" size="2xs" />
                            </button>
                        </div>
                    </div>
                </template>
                <div v-else class="hcis-transfer-empty">
                    Tidak ada data
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisCheckbox from './HcisCheckbox.vue';

defineOptions({ name: 'HcisTransfer' });

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    titles: { type: Array, default: () => ['Daftar Tersedia', 'Daftar Terpilih'] },
    filterable: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    draggable: { type: Boolean, default: true },
    showOrderBadge: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'change']);

const leftSearchQuery = ref('');
const rightSearchQuery = ref('');
const leftCheckedKeys = ref([]);
const rightCheckedKeys = ref([]);

const draggedTargetIndex = ref(null);
const dragOverTargetIndex = ref(null);

const targetKeysSet = computed(() => new Set(props.modelValue));

const leftData = computed(() => props.data.filter((item) => !targetKeysSet.value.has(item.key)));

// Preserves the EXACT custom order of modelValue!
const rightData = computed(() => {
    const dataMap = new Map(props.data.map((item) => [item.key, item]));
    return props.modelValue
        .map((key) => dataMap.get(key) || { key, label: key })
        .filter(Boolean);
});

const leftFilteredData = computed(() => {
    if (!leftSearchQuery.value.trim()) return leftData.value;
    const q = leftSearchQuery.value.toLowerCase();
    return leftData.value.filter((item) => item.label.toLowerCase().includes(q));
});

const rightFilteredData = computed(() => {
    if (!rightSearchQuery.value.trim()) return rightData.value;
    const q = rightSearchQuery.value.toLowerCase();
    return rightData.value.filter((item) => item.label.toLowerCase().includes(q));
});

const isLeftAllChecked = computed(() => {
    const valid = leftFilteredData.value.filter((i) => !i.disabled);
    return valid.length > 0 && valid.every((i) => leftCheckedKeys.value.includes(i.key));
});

const isLeftIndeterminate = computed(() => {
    const valid = leftFilteredData.value.filter((i) => !i.disabled);
    const checkedCount = valid.filter((i) => leftCheckedKeys.value.includes(i.key)).length;
    return checkedCount > 0 && checkedCount < valid.length;
});

const isRightAllChecked = computed(() => {
    const valid = rightFilteredData.value.filter((i) => !i.disabled);
    return valid.length > 0 && valid.every((i) => rightCheckedKeys.value.includes(i.key));
});

const isRightIndeterminate = computed(() => {
    const valid = rightFilteredData.value.filter((i) => !i.disabled);
    const checkedCount = valid.filter((i) => rightCheckedKeys.value.includes(i.key)).length;
    return checkedCount > 0 && checkedCount < valid.length;
});

function toggleLeftItem(key) {
    const idx = leftCheckedKeys.value.indexOf(key);
    if (idx >= 0) leftCheckedKeys.value.splice(idx, 1);
    else leftCheckedKeys.value.push(key);
}

function toggleRightItem(key) {
    const idx = rightCheckedKeys.value.indexOf(key);
    if (idx >= 0) rightCheckedKeys.value.splice(idx, 1);
    else rightCheckedKeys.value.push(key);
}

function toggleCheckAllLeft(val) {
    const validKeys = leftFilteredData.value.filter((i) => !i.disabled).map((i) => i.key);
    if (val) {
        leftCheckedKeys.value = Array.from(new Set([...leftCheckedKeys.value, ...validKeys]));
    } else {
        leftCheckedKeys.value = leftCheckedKeys.value.filter((k) => !validKeys.includes(k));
    }
}

function toggleCheckAllRight(val) {
    const validKeys = rightFilteredData.value.filter((i) => !i.disabled).map((i) => i.key);
    if (val) {
        rightCheckedKeys.value = Array.from(new Set([...rightCheckedKeys.value, ...validKeys]));
    } else {
        rightCheckedKeys.value = rightCheckedKeys.value.filter((k) => !validKeys.includes(k));
    }
}

function addToTarget() {
    const nextTarget = [...props.modelValue, ...leftCheckedKeys.value];
    leftCheckedKeys.value = [];
    emit('update:modelValue', nextTarget);
    emit('change', nextTarget);
}

function removeFromTarget() {
    const removeSet = new Set(rightCheckedKeys.value);
    const nextTarget = props.modelValue.filter((k) => !removeSet.has(k));
    rightCheckedKeys.value = [];
    emit('update:modelValue', nextTarget);
    emit('change', nextTarget);
}

function onTargetDragStart(e, item, index) {
    if (!props.draggable || props.disabled || item.disabled) return;
    draggedTargetIndex.value = index;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(index));
    }
}

function onTargetDragOver(e, item, index) {
    if (!props.draggable || draggedTargetIndex.value === null) return;
    dragOverTargetIndex.value = index;
}

function onTargetDragLeave(index) {
    if (dragOverTargetIndex.value === index) {
        dragOverTargetIndex.value = null;
    }
}

function onTargetDrop(e, targetItem, targetIndex) {
    e.preventDefault();
    if (draggedTargetIndex.value === null || draggedTargetIndex.value === targetIndex) {
        draggedTargetIndex.value = null;
        dragOverTargetIndex.value = null;
        return;
    }

    const newModelValue = [...props.modelValue];
    const fromIdx = draggedTargetIndex.value;
    const toIdx = targetIndex;
    const [movedKey] = newModelValue.splice(fromIdx, 1);
    newModelValue.splice(toIdx, 0, movedKey);

    emit('update:modelValue', newModelValue);
    emit('change', newModelValue);

    draggedTargetIndex.value = null;
    dragOverTargetIndex.value = null;
}

function onTargetDragEnd() {
    draggedTargetIndex.value = null;
    dragOverTargetIndex.value = null;
}

function moveItem(index, direction) {
    const targetIdx = index + direction;
    if (targetIdx < 0 || targetIdx >= props.modelValue.length) return;
    const newModelValue = [...props.modelValue];
    const [movedKey] = newModelValue.splice(index, 1);
    newModelValue.splice(targetIdx, 0, movedKey);
    emit('update:modelValue', newModelValue);
    emit('change', newModelValue);
}
</script>

<style scoped>
.hcis-transfer {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

.hcis-transfer-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 320px;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
    background-color: var(--hcis-surface, var(--hcis-white));
    box-shadow: 0 4px 16px -2px rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.05), 0 2px 4px -1px rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.03);
    overflow: hidden;
}

.hcis-transfer--disabled .hcis-transfer-panel {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.hcis-transfer-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 0.875rem;
    background-color: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
}

.hcis-transfer-panel__title {
    font-size: 0.825rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-800));
}

.hcis-transfer-panel__count {
    font-size: 0.725rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-transfer-panel__filter {
    position: relative;
    display: flex;
    align-items: center;
    padding: 0.5rem 0.875rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.hcis-filter-icon {
    position: absolute;
    left: 1.35rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-filter-input {
    width: 100%;
    height: 2rem;
    padding: 0.35rem 0.5rem 0.35rem 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    background-color: var(--hcis-surface, var(--hcis-white));
    font-size: 0.775rem;
    color: var(--hcis-text-strong, var(--hcis-slate-800));
    box-shadow: 0 1px 2px 0 rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.04);
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}

.hcis-filter-input:focus {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow: 0 0 0 2px var(--hcis-focus-ring);
}

.hcis-transfer-panel__body {
    flex: 1;
    overflow-y: auto;
    padding: 0.375rem;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    scrollbar-width: thin;
}

.hcis-transfer-item {
    display: flex;
    align-items: center;
    padding: 0.45rem 0.625rem;
    border-radius: var(--hcis-radius-md, 8px);
    cursor: pointer;
    user-select: none;
    transition: background-color 150ms ease, color 150ms ease;
}

.hcis-transfer-item:hover:not(.is-disabled) {
    background: rgba(99, 102, 241, 0.08);
}

.hcis-transfer-item.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.hcis-transfer-item--target {
    display: flex;
    align-items: center;
    position: relative;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
    padding: 0.4rem 0.55rem;
}

.hcis-transfer-item--target.is-draggable {
    cursor: grab;
}

.hcis-transfer-item--target.is-draggable:active {
    cursor: grabbing;
}

.hcis-transfer-drag-handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    cursor: grab;
    opacity: 0.45;
    margin-right: 0.35rem;
    transition: opacity 140ms ease, color 140ms ease;
    flex-shrink: 0;
}

.hcis-transfer-item--target:hover .hcis-transfer-drag-handle {
    opacity: 1;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-transfer-order-badge {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    background: var(--hcis-surface-brand, var(--hcis-indigo-50));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    border: 1px solid rgba(99, 102, 241, 0.25);
    font-size: 0.65rem;
    font-weight: 750;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.35rem;
    flex-shrink: 0;
}

.hcis-transfer-item--target.is-dragging {
    opacity: 0.35;
    background: var(--hcis-surface-soft, var(--hcis-slate-50)) !important;
    border: 1px dashed var(--hcis-brand-500, var(--hcis-indigo-500));
}

.hcis-transfer-item--target.is-drag-over {
    border-top: 2px solid var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    background: var(--hcis-surface-brand, var(--hcis-indigo-50)) !important;
}

.hcis-transfer-item__actions {
    margin-left: auto;
    display: none;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}

.hcis-transfer-item--target:hover .hcis-transfer-item__actions {
    display: inline-flex;
}

.hcis-transfer-mini-btn {
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: var(--hcis-surface, var(--hcis-white));
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    border-radius: 4px;
    padding: 0.1rem 0.25rem;
    font-size: 0.65rem;
    cursor: pointer;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
}

.hcis-transfer-mini-btn:hover:not(:disabled) {
    border-color: var(--hcis-brand-500, var(--hcis-indigo-500));
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: var(--hcis-surface-brand, var(--hcis-indigo-50));
}

.hcis-transfer-mini-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.hcis-transfer-item__label {
    font-size: 0.8rem;
    color: var(--hcis-text-strong, var(--hcis-slate-800));
    margin-left: 0.35rem;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-transfer-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 0.8rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-transfer-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.hcis-transfer-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid var(--hcis-brand-600, var(--hcis-indigo-600));
    background: linear-gradient(180deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-600, var(--hcis-indigo-600)) 100%);
    color: var(--hcis-white);
    cursor: pointer;
    box-shadow: 0 2px 4px 0 rgba(79, 70, 229, 0.2);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-transfer-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px 0 rgba(79, 70, 229, 0.3);
}

.hcis-transfer-btn:disabled {
    background: rgba(241, 245, 249, 0.85) !important;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    box-shadow: none !important;
    cursor: not-allowed;
}
</style>
