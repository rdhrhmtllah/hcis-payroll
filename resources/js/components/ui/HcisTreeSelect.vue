<template>
    <div
        ref="containerRef"
        class="hcis-tree-select"
        :class="[
            `hcis-tree-select--size-${size}`,
            {
                'hcis-tree-select--active': isOpen || isFocused,
                'hcis-tree-select--disabled': disabled,
                'hcis-tree-select--loading': loading,
                'hcis-tree-select--has-value': hasValue,
            },
        ]"
    >
        <!-- Trigger Shell -->
        <div
            ref="triggerRef"
            class="hcis-tree-select__trigger"
            :id="id || undefined"
            :class="[
                `hcis-tree-select__trigger--size-${size}`,
                {
                    'hcis-tree-select__trigger--focused': isFocused || isOpen,
                    'hcis-tree-select__trigger--disabled': disabled,
                    'hcis-tree-select__trigger--loading': loading,
                    'hcis-tree-select__trigger--open': isOpen,
                },
            ]"
            role="combobox"
            :tabindex="disabled ? -1 : 0"
            :aria-label="resolvedAriaLabel"
            aria-haspopup="tree"
            :aria-expanded="isOpen"
            :aria-disabled="disabled || undefined"
            @click="handleTriggerClick"
            @keydown.enter.prevent="togglePopover"
            @keydown.space.prevent="togglePopover"
            @keydown.down.prevent="openPopover"
            @keydown.escape.prevent="closePopover"
        >
            <span class="hcis-tree-select__icon">
                <slot name="leading-icon">
                    <HcisIcon v-if="loading" name="arrow-repeat" size="xs" class="hcis-spin-icon text-brand-600" />
                    <HcisIcon v-else-if="disabled" name="lock-fill" size="xs" class="hcis-lock-icon text-muted" />
                    <HcisIcon v-else-if="selectedNode && selectedNode.icon" :name="selectedNode.icon" :color="selectedNode.iconColor || 'brand'" size="xs" class="text-brand-600" />
                    <HcisIcon v-else-if="leadingIcon" :name="leadingIcon" size="xs" class="text-slate-500" />
                    <HcisIcon v-else name="diagram-2-fill" size="xs" class="text-brand-600" />
                </slot>
            </span>

            <div class="hcis-tree-select__display" :class="{ 'hcis-shimmer-pulse': loading }">
                <span v-if="selectedLabel" class="hcis-display-text" :title="String(selectedLabel)">
                    {{ selectedLabel }}
                </span>
                <span v-else class="hcis-display-placeholder">
                    {{ placeholder }}
                </span>
            </div>

            <button
                v-if="clearable && hasValue && !disabled && !loading"
                type="button"
                class="hcis-tree-select__clear"
                title="Bersihkan pilihan"
                @click.stop="handleClear"
            >
                <HcisIcon name="x-circle-fill" size="xs" />
            </button>

            <span
                v-else
                class="hcis-tree-select__arrow"
                :class="{ 'is-open': isOpen }"
                @click.stop="togglePopover"
            >
                <HcisIcon name="chevron-down" size="2xs" />
            </span>
        </div>

        <!-- Desktop Mode: Teleported Popover Shell -->
        <Teleport v-if="!isMobile" to="body" :disabled="teleportTo === false">
            <Transition name="hcis-date-popover">
                <div
                    v-if="isOpen && !disabled && !loading"
                    ref="popoverRef"
                    class="hcis-tree-select__popover"
                    :style="popoverStyle"
                    role="tree"
                    :aria-label="resolvedAriaLabel"
                >
                    <!-- Search Filter Bar -->
                    <div v-if="filterable" class="hcis-tree-search">
                        <HcisIcon name="search" size="xs" class="hcis-search-icon" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            class="hcis-search-input"
                            :placeholder="searchPlaceholder"
                            @keydown.escape.prevent="closePopover"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="hcis-search-clear"
                            title="Hapus pencarian"
                            @click="searchQuery = ''"
                        >
                            <HcisIcon name="x-circle-fill" size="2xs" />
                        </button>
                    </div>

                    <!-- Tree Nodes Container -->
                    <div class="hcis-tree-nodes">
                        <!-- Empty State -->
                        <div v-if="filteredData.length === 0" class="hcis-tree-empty-state">
                            <div class="hcis-tree-empty-icon">
                                <HcisIcon name="search" size="sm" class="text-slate-400" />
                            </div>
                            <div class="hcis-tree-empty-text">
                                <span class="hcis-tree-empty-title">Tidak ada simpul ditemukan</span>
                                <span class="hcis-tree-empty-sub">Coba kata kunci pencarian yang lain</span>
                            </div>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="hcis-tree-empty-reset"
                                @click="searchQuery = ''"
                            >
                                <HcisIcon name="arrow-clockwise" size="2xs" class="me-1" />
                                <span>Bersihkan Pencarian</span>
                            </button>
                        </div>

                        <!-- Tree Nodes List -->
                        <template v-else>
                            <TreeNodeItem
                                v-for="node in filteredData"
                                :key="node.value"
                                :node="node"
                                :depth="0"
                                :selected-value="modelValue"
                                :expanded-keys="expandedKeys"
                                @select="selectNode"
                                @toggle-expand="toggleExpand"
                            />
                        </template>
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
            <div class="hcis-mobile-tree-content">
                <div v-if="filterable" class="hcis-tree-search mb-3">
                    <HcisIcon name="search" size="xs" class="hcis-search-icon" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="hcis-search-input"
                        :placeholder="searchPlaceholder"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="hcis-search-clear"
                        title="Hapus pencarian"
                        @click="searchQuery = ''"
                    >
                        <HcisIcon name="x-circle-fill" size="2xs" />
                    </button>
                </div>

                <div class="hcis-tree-nodes hcis-tree-nodes--mobile" role="tree">
                    <div v-if="filteredData.length === 0" class="hcis-tree-empty-state">
                        <div class="hcis-tree-empty-icon">
                            <HcisIcon name="search" size="sm" class="text-slate-400" />
                        </div>
                        <div class="hcis-tree-empty-text">
                            <span class="hcis-tree-empty-title">Tidak ada simpul ditemukan</span>
                            <span class="hcis-tree-empty-sub">Coba kata kunci pencarian yang lain</span>
                        </div>
                    </div>

                    <template v-else>
                        <TreeNodeItem
                            v-for="node in filteredData"
                            :key="node.value"
                            :node="node"
                            :depth="0"
                            :selected-value="modelValue"
                            :expanded-keys="expandedKeys"
                            @select="selectNode"
                            @toggle-expand="toggleExpand"
                        />
                    </template>
                </div>
            </div>
        </HcisDrawer>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, useAttrs, h } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisDrawer from './HcisDrawer.vue';

defineOptions({ name: 'HcisTreeSelect' });

const props = defineProps({
    id: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    modelValue: { type: [String, Number, Boolean, Object], default: '' },
    data: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih simpul pohon...' },
    searchPlaceholder: { type: String, default: 'Cari simpul pohon...' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    filterable: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
    /* Bawaan TERTUTUP, sama dengan perilaku komponen ini sebelum prop ini ada.
       Sempat dijadikan `true`, dan itu diam-diam mengubah setiap pemakai lama:
       grup yang diklik pertama kali justru MENUTUP, sehingga `selectable: false`
       milik Visitor tak pernah bisa sampai ke anaknya. Pemakai yang ingin terbuka
       semua memintanya sendiri — seperti PaLabelBagianDialog. */
    defaultExpandAll: { type: Boolean, default: false },
    leadingIcon: { type: String, default: '' },
    popoverMinWidth: { type: [Number, String], default: 280 },
});

const emit = defineEmits(['update:modelValue', 'change', 'clear', 'search']);
const attrs = useAttrs();

const containerRef = ref(null);
const triggerRef = ref(null);
const popoverRef = ref(null);
const searchInputRef = ref(null);

const isOpen = ref(false);
const isFocused = ref(false);
const isMobile = ref(false);
const searchQuery = ref('');
const expandedKeys = ref(new Set());

const popoverStyle = ref({
    position: 'fixed',
    top: '0px',
    left: '0px',
    width: '20rem',
    zIndex: '999999',
});

function expandAllKeys(nodes) {
    if (!Array.isArray(nodes)) return;
    for (const n of nodes) {
        if (n && n.children && n.children.length) {
            expandedKeys.value.add(n.value);
            expandAllKeys(n.children);
        }
    }
}

watch(
    () => props.data,
    (newData) => {
        if (props.defaultExpandAll && Array.isArray(newData)) {
            expandAllKeys(newData);
        }
    },
    { immediate: true, deep: true },
);

const hasValue = computed(() => props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined);

const resolvedAriaLabel = computed(() => props.ariaLabel || attrs['aria-label'] || props.placeholder || 'Pilih simpul pohon');

function findNode(nodes, val) {
    if (!Array.isArray(nodes) || val === '' || val === null || val === undefined) return null;
    const strVal = String(val);
    for (const n of nodes) {
        if (!n) continue;
        if (String(n.value) === strVal) {
            return n;
        }
        if (n.children && n.children.length) {
            const found = findNode(n.children, val);
            if (found) return found;
        }
    }
    return null;
}

const selectedNode = computed(() => {
    if (!hasValue.value) return null;
    return findNode(props.data, props.modelValue);
});

function findNodeLabel(nodes, val) {
    const node = findNode(nodes, val);
    if (!node) return '';
    if (node.code && node.label && !String(node.label).startsWith(String(node.code))) {
        return `[${node.code}] ${node.label}`;
    }
    return node.label || node.name || String(val);
}

const selectedLabel = computed(() => {
    if (!hasValue.value) return '';
    return findNodeLabel(props.data, props.modelValue) || String(props.modelValue);
});

function filterTree(nodes, query) {
    if (!Array.isArray(nodes)) return [];
    return nodes.reduce((acc, node) => {
        if (!node) return acc;
        const q = query.toLowerCase();
        const matchesLabel = String(node.label || '').toLowerCase().includes(q);
        const matchesCode = String(node.code || '').toLowerCase().includes(q);
        const matchesDesc = String(node.description || '').toLowerCase().includes(q);
        const matchesVal = String(node.value || '').toLowerCase().includes(q);
        const isMatch = matchesLabel || matchesCode || matchesDesc || matchesVal;

        const filteredChildren = node.children && node.children.length ? filterTree(node.children, query) : [];

        if (isMatch || filteredChildren.length > 0) {
            acc.push({
                ...node,
                children: filteredChildren,
            });
            if (filteredChildren.length > 0) {
                expandedKeys.value.add(node.value);
            }
        }
        return acc;
    }, []);
}

const filteredData = computed(() => {
    if (!searchQuery.value.trim()) return props.data;
    return filterTree(props.data, searchQuery.value.trim());
});

function checkMobile() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
}

function updatePopoverPosition() {
    checkMobile();
    if (isMobile.value || !triggerRef.value) return;

    const rect = triggerRef.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const measuredHeight = popoverRef.value ? popoverRef.value.getBoundingClientRect().height : 0;
    const popoverHeight = measuredHeight && measuredHeight > 20 ? measuredHeight : 280;
    const minW = props.popoverMinWidth !== '' && props.popoverMinWidth !== null ? Number(props.popoverMinWidth) : 280;
    const width = Math.max(rect.width, isNaN(minW) ? 280 : minW);

    let left = rect.left;
    if (left + width > viewportWidth - 16) {
        left = Math.max(16, viewportWidth - width - 16);
    }

    const spaceBelow = viewportHeight - rect.bottom;
    const spaceAbove = rect.top;
    let top = rect.bottom + 6;

    if (spaceBelow < popoverHeight && (spaceAbove > spaceBelow || spaceAbove > popoverHeight)) {
        top = Math.max(8, rect.top - popoverHeight - 6);
    } else if (top + popoverHeight > viewportHeight - 16) {
        top = Math.max(8, viewportHeight - popoverHeight - 16);
    }

    popoverStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(Math.max(8, left))}px`,
        width: `${Math.round(Math.min(width, viewportWidth - 32))}px`,
        maxWidth: 'calc(100vw - 32px)',
        zIndex: '999999',
    };
}

function togglePopover() {
    if (props.disabled || props.loading) return;
    if (isOpen.value) closePopover();
    else openPopover();
}

function openPopover() {
    if (props.disabled || props.loading) return;
    searchQuery.value = '';
    isOpen.value = true;
    if (props.defaultExpandAll) {
        expandAllKeys(props.data || []);
    }
    updatePopoverPosition();
    nextTick(() => {
        updatePopoverPosition();
        if (props.filterable && searchInputRef.value) {
            searchInputRef.value.focus();
        }
    });
}

function closePopover() {
    isOpen.value = false;
}

function handleTriggerClick() {
    if (!isOpen.value && !props.disabled && !props.loading) openPopover();
}

function handleClear() {
    if (props.disabled || props.loading) return;
    const emptyVal = '';
    emit('update:modelValue', emptyVal);
    emit('change', emptyVal);
    emit('clear');
}

function selectNode(node) {
    if (node.disabled || node.selectable === false) {
        if (node.children && node.children.length > 0) {
            toggleExpand(node.value);
        }
        return;
    }
    emit('update:modelValue', node.value);
    emit('change', node.value);
    closePopover();
}

function toggleExpand(key) {
    if (expandedKeys.value.has(key)) expandedKeys.value.delete(key);
    else expandedKeys.value.add(key);
}

// Inline Tree Node Component with Luxury Styling
const TreeNodeItem = {
    name: 'TreeNodeItem',
    props: ['node', 'selectedValue', 'expandedKeys', 'depth'],
    emits: ['select', 'toggle-expand'],
    setup(p, { emit: itemEmit }) {
        return () => {
            const node = p.node;
            if (!node) return null;

            const depth = p.depth || 0;
            const hasChildren = Boolean(node.children && node.children.length > 0);
            const isSelectable = !node.disabled && node.selectable !== false;
            const isExpanded = p.expandedKeys.has(node.value);
            const isSelected = String(node.value) === String(p.selectedValue);

            return h('div', { class: ['hcis-tree-node-wrapper', { 'is-root': depth === 0 }] }, [
                h(
                    'div',
                    {
                        class: [
                            'hcis-tree-node',
                            {
                                'is-selected': isSelected,
                                'is-parent': hasChildren,
                                'is-leaf': !hasChildren,
                                'is-unselectable': !isSelectable,
                                'is-disabled': node.disabled,
                                'is-expanded': isExpanded,
                            },
                        ],
                        role: 'treeitem',
                        'aria-expanded': hasChildren ? isExpanded : undefined,
                        'aria-selected': isSelected,
                        'aria-disabled': node.disabled || undefined,
                        tabindex: node.disabled ? -1 : 0,
                        onClick: () => {
                            if (!isSelectable && hasChildren) {
                                itemEmit('toggle-expand', node.value);
                            } else {
                                itemEmit('select', node);
                            }
                        },
                        onKeydown: (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                if (!isSelectable && hasChildren) {
                                    itemEmit('toggle-expand', node.value);
                                } else {
                                    itemEmit('select', node);
                                }
                            }
                        },
                    },
                    [
                        // Expand/Collapse Chevron or Leaf Spacer
                        hasChildren
                            ? h(
                                  'button',
                                  {
                                      type: 'button',
                                      class: ['hcis-tree-toggle', { 'is-expanded': isExpanded }],
                                      'aria-label': isExpanded ? 'Tutup cabang' : 'Buka cabang',
                                      onClick: (e) => {
                                          e.stopPropagation();
                                          itemEmit('toggle-expand', node.value);
                                      },
                                  },
                                  [
                                      h(HcisIcon, {
                                          name: 'chevron-right',
                                          size: '2xs',
                                          class: 'hcis-toggle-chevron',
                                      }),
                                  ]
                              )
                            : h('span', { class: 'hcis-tree-leaf-spacer' }),

                        // Node Icon
                        h(
                            'span',
                            { class: 'hcis-tree-node-icon' },
                            [
                                h(HcisIcon, {
                                    name: node.icon
                                        ? node.icon
                                        : hasChildren
                                        ? isExpanded
                                            ? 'folder2-open'
                                            : 'folder-fill'
                                        : isSelected
                                        ? 'check-circle-fill'
                                        : 'card-checklist',
                                    size: '2xs',
                                    color: node.iconColor
                                        ? node.iconColor
                                        : hasChildren
                                        ? 'brand'
                                        : isSelected
                                        ? 'success'
                                        : 'muted',
                                    class: hasChildren
                                        ? 'text-indigo-600'
                                        : isSelected
                                        ? 'text-emerald-600'
                                        : 'text-slate-400',
                                }),
                            ]
                        ),

                        // Node Content (Label + Code + Description)
                        h('div', { class: 'hcis-tree-node-content' }, [
                            h('div', { class: 'hcis-tree-node-main' }, [
                                node.code
                                    ? h('span', { class: 'hcis-tree-code-chip' }, node.code)
                                    : null,
                                h('span', { class: 'hcis-tree-label' }, node.label || node.name || ''),
                            ]),
                            node.description
                                ? h('span', { class: 'hcis-tree-desc' }, node.description)
                                : null,
                        ]),

                        // Node Meta (Children Count Badge, Custom Badge, Checkmark)
                        h('div', { class: 'hcis-tree-node-meta' }, [
                            hasChildren
                                ? h('span', { class: 'hcis-tree-count-badge' }, `${node.children.length}`)
                                : null,
                            node.badge
                                ? h(
                                      'span',
                                      {
                                          class: [
                                              'hcis-tree-badge',
                                              `is-${node.badgeVariant || 'neutral'}`,
                                          ],
                                      },
                                      node.badge
                                  )
                                : null,
                            isSelected && !hasChildren
                                ? h('span', { class: 'hcis-tree-check' }, [
                                      h(HcisIcon, { name: 'check2', size: 'xs' }),
                                  ])
                                : null,
                        ]),
                    ]
                ),

                // Nested Children
                hasChildren && isExpanded
                    ? h(
                          'div',
                          { class: 'hcis-tree-children' },
                          node.children.map((child) =>
                              h(TreeNodeItem, {
                                  key: child.value,
                                  node: child,
                                  depth: depth + 1,
                                  selectedValue: p.selectedValue,
                                  expandedKeys: p.expandedKeys,
                                  onSelect: (n) => itemEmit('select', n),
                                  onToggleExpand: (k) => itemEmit('toggle-expand', k),
                              })
                          )
                      )
                    : null,
            ]);
        };
    },
};

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
.hcis-tree-select {
    position: relative;
    display: inline-block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

/* Sizes */
.hcis-tree-select--size-sm .hcis-tree-select__trigger {
    min-height: 2.125rem;
    height: 2.125rem;
    padding: 0.15rem 0.6rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.hcis-tree-select--size-md .hcis-tree-select__trigger {
    min-height: 2.375rem;
    height: 2.375rem;
    padding: 0.35rem 0.75rem;
    font-size: 0.875rem;
    border-radius: var(--hcis-radius-md, 9px);
}

.hcis-tree-select--size-lg .hcis-tree-select__trigger {
    min-height: 2.75rem;
    height: 2.75rem;
    padding: 0.45rem 1rem;
    font-size: 0.9375rem;
    border-radius: var(--hcis-radius-xl, 12px);
}

/* Trigger Shell (Luxury Elevation & Gradients) */
.hcis-tree-select__trigger {
    display: flex;
    align-items: center;
    width: 100%;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300, #cbd5e1));
    background: linear-gradient(180deg, var(--hcis-white, #ffffff) 0%, #fcfdfe 100%);
    box-shadow:
        0 1px 2px 0 rgba(15, 23, 42, 0.04),
        inset 0 1px 1px 0 rgba(15, 23, 42, 0.02);
    transition-property: var(--hcis-transition-props, all);
    transition-duration: 180ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
    user-select: none;
    box-sizing: border-box;
}

.hcis-tree-select__trigger:hover:not(.hcis-tree-select__trigger--disabled):not(.hcis-tree-select__trigger--loading) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400, #818cf8));
    box-shadow:
        0 2px 6px -1px rgba(99, 102, 241, 0.1),
        inset 0 1px 1px 0 rgba(255, 255, 255, 0.8);
}

.hcis-tree-select__trigger--focused,
.hcis-tree-select__trigger--open {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600, #4f46e5)) !important;
    box-shadow:
        0 0 0 3px var(--hcis-focus-ring, rgba(99, 102, 241, 0.2)),
        0 1px 2px 0 rgba(79, 70, 229, 0.1) !important;
    background-color: var(--hcis-surface, var(--hcis-white, #ffffff)) !important;
}

/* Signature Frosted Disabled State */
.hcis-tree-select__trigger--disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300, #cbd5e1)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-tree-select__trigger--disabled .hcis-display-text,
.hcis-tree-select__trigger--disabled .hcis-display-placeholder {
    color: var(--hcis-slate-500, #64748b) !important;
    font-weight: 500;
}

.hcis-tree-select__trigger--loading {
    cursor: wait !important;
    border-color: var(--hcis-brand-300, #a5b4fc);
}

.hcis-spin-icon {
    animation: hcis-spin 1s linear infinite;
}

@keyframes hcis-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.hcis-shimmer-pulse {
    animation: hcis-pulse-glow 1.5s ease-in-out infinite;
}

@keyframes hcis-pulse-glow {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.45;
    }
}

.hcis-tree-select__icon {
    display: flex;
    align-items: center;
    margin-right: 0.5rem;
    flex-shrink: 0;
}

.hcis-tree-select__display {
    flex: 1;
    min-width: 0;
}

.hcis-display-text {
    font-size: inherit;
    font-weight: 600;
    color: var(--hcis-slate-900, #0f172a);
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-display-placeholder {
    font-size: inherit;
    color: var(--hcis-slate-400, #94a3b8);
    font-weight: 500;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-tree-select__clear,
.hcis-tree-select__arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400, #94a3b8);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0 0.125rem;
    transition:
        transform 200ms cubic-bezier(0.16, 1, 0.3, 1),
        color 150ms ease;
    flex-shrink: 0;
}

.hcis-tree-select__arrow.is-open {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, var(--hcis-indigo-600, #4f46e5));
}

.hcis-tree-select__clear:hover {
    color: var(--hcis-danger-600, var(--hcis-red-600, #e11d48));
}

/* Popover Panel (Luxury Floating Glass) */
.hcis-tree-select__popover {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300, #cbd5e1));
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow:
        0 16px 36px -4px rgba(15, 23, 42, 0.14),
        0 4px 12px -2px rgba(15, 23, 42, 0.06),
        0 0 0 1px rgba(255, 255, 255, 0.8) inset;
    padding: 0.5rem;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

/* Search Bar */
.hcis-tree-search {
    position: relative;
    display: flex;
    align-items: center;
}

.hcis-search-icon {
    position: absolute;
    left: 0.625rem;
    color: var(--hcis-slate-400, #94a3b8);
    pointer-events: none;
}

.hcis-search-input {
    width: 100%;
    padding: 0.375rem 1.85rem 0.375rem 2rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300, #cbd5e1));
    background: var(--hcis-surface-soft, var(--hcis-slate-50, #f8fafc));
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
    box-sizing: border-box;
}

.hcis-search-input:focus {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600, #4f46e5));
    box-shadow: 0 0 0 2px var(--hcis-focus-ring, rgba(99, 102, 241, 0.2));
    background: var(--hcis-white, #ffffff);
}

.hcis-search-clear {
    position: absolute;
    right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--hcis-slate-400, #94a3b8);
    cursor: pointer;
    padding: 0.125rem;
    transition: color 120ms ease;
}

.hcis-search-clear:hover {
    color: var(--hcis-slate-600, #475569);
}

/* Tree Nodes Container */
.hcis-tree-nodes {
    max-height: 270px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    scrollbar-width: thin;
    padding-right: 0.15rem;
}

.hcis-tree-nodes--mobile {
    max-height: 50vh;
}

.hcis-tree-nodes::-webkit-scrollbar {
    width: 5px;
}

.hcis-tree-nodes::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.hcis-tree-nodes::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Empty State */
.hcis-tree-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 0.75rem;
    text-align: center;
    gap: 0.4rem;
}

.hcis-tree-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: var(--hcis-slate-100, #f1f5f9);
    margin-bottom: 0.2rem;
}

.hcis-tree-empty-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.hcis-tree-empty-title {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
}

.hcis-tree-empty-sub {
    font-size: 0.725rem;
    color: var(--hcis-slate-400, #94a3b8);
}

.hcis-tree-empty-reset {
    display: inline-flex;
    align-items: center;
    margin-top: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-300, #cbd5e1);
    background: var(--hcis-white, #ffffff);
    font-size: 0.725rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
    cursor: pointer;
    transition: background-color 120ms ease, border-color 120ms ease;
}

.hcis-tree-empty-reset:hover {
    background: var(--hcis-slate-50, #f8fafc);
    border-color: var(--hcis-brand-400, #818cf8);
    color: var(--hcis-brand-700, #4338ca);
}
</style>

<style>
/* Global Unscoped Styles for Recursive Tree Nodes */
.hcis-tree-node-wrapper {
    display: flex;
    flex-direction: column;
}

.hcis-tree-node {
    display: flex;
    align-items: center;
    padding: 0.38rem 0.55rem;
    border-radius: var(--hcis-radius-md, 8px);
    cursor: pointer;
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, #1e293b);
    font-weight: 550;
    transition: background-color 140ms ease, color 140ms ease;
    gap: 0.35rem;
    user-select: none;
    outline: none;
}

.hcis-tree-node:hover:not(.is-disabled) {
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-brand-700, #4338ca);
}

.hcis-tree-node.is-parent {
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    background: rgba(248, 250, 252, 0.75);
    margin-top: 2px;
}

.hcis-tree-node.is-parent:hover {
    background: rgba(241, 245, 249, 0.95);
    color: var(--hcis-indigo-800, #3730a3);
}

.hcis-tree-node.is-selected {
    background: rgba(238, 242, 255, 0.95) !important;
    color: var(--hcis-brand-700, #4338ca) !important;
    font-weight: 700;
}

.hcis-tree-node.is-disabled {
    opacity: 0.45;
    cursor: not-allowed !important;
    background: transparent !important;
}

/* Expansion Chevron Button */
.hcis-tree-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border: none;
    background: transparent;
    border-radius: 4px;
    color: var(--hcis-slate-400, #94a3b8);
    cursor: pointer;
    flex-shrink: 0;
    padding: 0;
    transition: background-color 140ms ease, color 140ms ease;
}

.hcis-tree-toggle:hover {
    background: rgba(0, 0, 0, 0.06);
    color: var(--hcis-slate-700, #334155);
}

.hcis-toggle-chevron {
    transition: transform 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-tree-toggle.is-expanded .hcis-toggle-chevron {
    transform: rotate(90deg);
}

.hcis-tree-leaf-spacer {
    width: 1.35rem;
    height: 1.35rem;
    flex-shrink: 0;
}

.hcis-tree-node-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-right: 0.15rem;
}

.hcis-tree-node-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.hcis-tree-node-main {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.hcis-tree-code-chip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.675rem;
    font-weight: 750;
    padding: 0.05rem 0.32rem;
    border-radius: 4px;
    background: rgba(238, 242, 255, 0.95);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid rgba(199, 210, 254, 0.8);
    flex-shrink: 0;
    line-height: 1.2;
}

.hcis-tree-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.35;
    flex: 1;
}

.hcis-tree-desc {
    font-size: 0.7rem;
    font-weight: 450;
    color: var(--hcis-slate-400, #94a3b8);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.25;
    margin-top: 1px;
}

.hcis-tree-node.is-selected .hcis-tree-desc {
    color: var(--hcis-brand-600, #4f46e5);
}

.hcis-tree-node-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
    margin-left: auto;
}

.hcis-tree-count-badge {
    font-size: 0.675rem;
    font-weight: 700;
    padding: 0.06rem 0.35rem;
    border-radius: 9999px;
    background: rgba(226, 232, 240, 0.9);
    color: var(--hcis-slate-600, #475569);
}

.hcis-tree-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.06rem 0.35rem;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 9999px;
    letter-spacing: 0.02em;
}

.hcis-tree-badge.is-neutral {
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-600, #475569);
}
.hcis-tree-badge.is-brand,
.hcis-tree-badge.is-primary {
    background: #e0e7ff;
    color: #4338ca;
}
.hcis-tree-badge.is-success {
    background: #d1fae5;
    color: #065f46;
}
.hcis-tree-badge.is-warning {
    background: #fef3c7;
    color: #b45309;
}
.hcis-tree-badge.is-danger {
    background: #ffe4e6;
    color: #be123c;
}
.hcis-tree-badge.is-purple {
    background: #f3e8ff;
    color: #6b21a8;
}
.hcis-tree-badge.is-cyan {
    background: #cffafe;
    color: #0e7490;
}

.hcis-tree-check {
    display: flex;
    align-items: center;
    color: var(--hcis-brand-600, #4f46e5);
}

/* Children Branch Container with Elegant Left Guide Line */
.hcis-tree-children {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    position: relative;
    margin-left: 0.65rem;
    padding-left: 0.65rem;
    border-left: 1.5px dashed rgba(203, 213, 225, 0.85);
    margin-top: 1px;
    margin-bottom: 2px;
}
</style>
