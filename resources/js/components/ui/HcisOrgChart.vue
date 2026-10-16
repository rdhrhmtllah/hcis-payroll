<template>
    <div
        class="hcis-org-chart"
        :class="[
            `hcis-org-chart--${variant}`,
            { 'is-interactive': interactive },
        ]"
    >
        <!-- Canvas Header Controls -->
        <div v-if="title || $slots.header || allowZoom" class="hcis-org-chart__header">
            <slot name="header">
                <div class="hcis-org-chart__header-left">
                    <div class="hcis-org-chart__icon-glow">
                        <HcisIcon v-if="icon" :name="icon" size="sm" />
                    </div>
                    <div>
                        <h6 class="hcis-org-chart__title">{{ title }}</h6>
                        <small v-if="subtitle" class="hcis-org-chart__subtitle">{{ subtitle }}</small>
                    </div>
                </div>

                <div class="hcis-org-chart__header-right">
                    <!-- Zoom & Pan Canvas Toolbar Controls -->
                    <div v-if="allowZoom" class="hcis-org-canvas-controls">
                        <button type="button" class="hcis-canvas-btn" title="Zoom Out (-)" @click="zoomBy(0.85)">
                            <HcisIcon name="dash" size="2xs" />
                        </button>
                        <span class="hcis-canvas-zoom-text">{{ Math.round(view.zoom * 100) }}%</span>
                        <button type="button" class="hcis-canvas-btn" title="Zoom In (+)" @click="zoomBy(1.18)">
                            <HcisIcon name="plus" size="2xs" />
                        </button>
                        <button type="button" class="hcis-canvas-btn hcis-canvas-btn--fit" title="Paskan Tampilan Bagan Organisasi" @click="fitGraph">
                            Fit
                        </button>
                    </div>

                    <span v-if="flatNodes.length" class="hcis-org-chart__node-count">
                        {{ flatNodes.length }} Anggota Organisasi
                    </span>
                </div>
            </slot>
        </div>

        <!-- SVG Tree Viewport Canvas -->
        <div
            ref="viewport"
            class="hcis-org-viewport"
            :style="height ? { height } : undefined"
            @wheel="onWheel"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
            <div class="hcis-org-canvas" :style="canvasTransform">
                <!-- SVG Orthogonal Branching Connecting Lines -->
                <svg
                    class="hcis-org-svg-edges"
                    :width="graphLayout.width"
                    :height="graphLayout.height"
                    :viewBox="`0 0 ${graphLayout.width} ${graphLayout.height}`"
                    aria-hidden="true"
                >
                    <template v-for="edge in graphLayout.edges" :key="edge.key">
                        <path
                            :d="edgePath(edge)"
                            class="hcis-org-edge-line"
                            :class="{ 'is-active': edge.isActive }"
                        />
                    </template>
                </svg>

                <!-- Rendered Org Tree Nodes -->
                <template v-for="node in graphLayout.nodes" :key="node.id">
                    <div
                        class="hcis-org-node"
                        :class="[
                            {
                                'is-root': node.isRoot,
                                'is-selected': selectedNodeId === node.id || activePopoverId === node.id,
                                'has-children': node.children && node.children.length,
                                'is-collapsed': collapsedMap.has(node.id),
                            }
                        ]"
                        :style="nodeStyle(node)"
                        tabindex="0"
                        @click.stop="toggleNodePopover(node)"
                        @keydown.enter.stop="toggleNodePopover(node)"
                    >
                        <!-- Port Dots -->
                        <span v-if="!node.isRoot" class="hcis-org-node__port hcis-org-node__port--top" />
                        <span v-if="node.children && node.children.length && !collapsedMap.has(node.id)" class="hcis-org-node__port hcis-org-node__port--bottom" />

                        <!-- Strict Inner Container -->
                        <div class="hcis-org-node__inner">
                            <!-- Node Header: Dept / Level Tag & Expand Toggle -->
                            <div class="hcis-org-node__header">
                                <span class="hcis-org-node__dept-tag" :title="node.department || 'Unit Organisasi'">
                                    <HcisIcon name="building" size="2xs" />
                                    <span>{{ node.department || 'Unit Organisasi' }}</span>
                                </span>

                                <button
                                    v-if="node.children && node.children.length"
                                    type="button"
                                    class="hcis-org-node__toggle-btn"
                                    :title="collapsedMap.has(node.id) ? 'Buka Cabang' : 'Tutup Cabang'"
                                    @click.stop="toggleCollapse(node.id)"
                                >
                                    <HcisIcon :name="collapsedMap.has(node.id) ? 'plus' : 'dash'" size="2xs" />
                                    <span class="hcis-org-node__child-count">{{ node.children.length }}</span>
                                </button>
                            </div>

                            <!-- Node Body: Avatar + Name & Position Title -->
                            <div class="hcis-org-node__body">
                                <div class="hcis-org-node__avatar" :class="{ 'is-dept-avatar': node.is_department_root }">
                                    <img v-if="node.avatar" :src="node.avatar" :alt="node.name" />
                                    <HcisIcon v-else-if="node.is_department_root" name="building" size="xs" />
                                    <span v-else class="hcis-org-node__avatar-placeholder">
                                        {{ getInitials(node.name) }}
                                    </span>
                                </div>

                                <div class="hcis-org-node__meta">
                                    <strong class="hcis-org-node__name" :title="node.name">{{ node.name }}</strong>
                                    <span class="hcis-org-node__title" :title="node.title">{{ node.title }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Glassmorphic Node Popover Panel -->
                        <Transition name="hcis-org-popover">
                            <div
                                v-if="activePopoverId === node.id"
                                class="hcis-org-popover"
                                @click.stop
                            >
                                <div class="hcis-org-popover__caret" />

                                <div class="hcis-org-popover__header">
                                    <div class="hcis-org-popover__avatar-wrap">
                                        <div class="hcis-org-popover__avatar">
                                            <img v-if="node.avatar" :src="node.avatar" :alt="node.name" />
                                            <HcisIcon v-else-if="node.is_department_root" name="building" size="xs" />
                                            <span v-else>{{ getInitials(node.name) }}</span>
                                        </div>
                                        <div>
                                            <strong class="hcis-org-popover__name">{{ node.name }}</strong>
                                            <small class="hcis-org-popover__title">{{ node.title }}</small>
                                        </div>
                                    </div>

                                    <button type="button" class="hcis-org-popover__close" @click.stop="activePopoverId = null">
                                        <HcisIcon name="x" size="xs" />
                                    </button>
                                </div>

                                <div class="hcis-org-popover__body">
                                    <div class="hcis-org-popover__field">
                                        <span class="hcis-org-popover__label">Unit / Departemen</span>
                                        <span class="hcis-org-popover__val">{{ node.department || '-' }}</span>
                                    </div>
                                    <div v-if="node.code || node.nik" class="hcis-org-popover__field">
                                        <span class="hcis-org-popover__label">{{ node.is_department_root ? 'Kode Unit' : 'NIK / Kode Karyawan' }}</span>
                                        <span class="hcis-org-popover__val-chip">{{ node.nik || node.code }}</span>
                                    </div>
                                    <div v-if="node.children && node.children.length" class="hcis-org-popover__field">
                                        <span class="hcis-org-popover__label">{{ node.is_department_root ? 'Pimpinan / Cabang Utama' : 'Bawahan Langsung' }}</span>
                                        <span class="hcis-org-popover__val">{{ node.children.length }} {{ node.is_department_root ? 'Pimpinan Unit' : 'Orang' }}</span>
                                    </div>
                                </div>

                                <div v-if="!node.is_department_root && nodeActions.length" class="hcis-org-popover__footer">
                                    <HcisButton
                                        v-for="aksi in nodeActions"
                                        :key="aksi.key"
                                        :label="aksi.label"
                                        size="sm"
                                        :variant="aksi.variant || 'secondary'"
                                        :leading-icon="aksi.icon || ''"
                                        @click.stop="onNodeAction(aksi.key, node)"
                                    />
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisButton from './HcisButton.vue';

defineOptions({ name: 'HcisOrgChart' });

const props = defineProps({
    title: { type: String, default: 'Struktur Organisasi & Hirarki Perusahaan' },
    subtitle: { type: String, default: 'Interactive Multi-Level Organization Tree Chart' },
    icon: { type: String, default: 'diagram-2' },
    data: { type: Object, default: () => ({}) },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'flat', 'glass'].includes(v),
    },
    interactive: { type: Boolean, default: true },
    allowZoom: { type: Boolean, default: true },
    height: { type: String, default: '' },
    initialCollapseLevel: { type: Number, default: 0 },
    /**
     * Tombol di kaki popover simpul: [{ key, label, variant?, icon? }].
     *
     * Default-nya dua tombol yang dulu hardcode, supaya pemakai lama tidak
     * berubah perilakunya. Kirim array kosong untuk menyembunyikan kakinya —
     * tombol yang tidak melakukan apa pun lebih buruk daripada tidak ada
     * tombol sama sekali.
     */
    nodeActions: {
        type: Array,
        default: () => [
            { key: 'profile', label: 'Rincian Profil', variant: 'secondary', icon: 'person-badge' },
            { key: 'structure', label: 'Struktur Sub-Unit', variant: 'primary', icon: 'diagram-3' },
        ],
    },
});

const emit = defineEmits(['node-click', 'node-action']);

const viewport = ref(null);
const activePopoverId = ref(null);
const selectedNodeId = ref(null);
const collapsedMap = ref(new Map());
const view = ref({ x: 24, y: 24, zoom: 1 });
const pointers = new Map();
let panOrigin = null;

const NODE_WIDTH = 210;
const NODE_HEIGHT = 100;
const LEVEL_GAP_Y = 64;
const SIBLING_GAP_X = 28;
const CANVAS_PADDING = 36;
const MIN_ZOOM = 0.4;
const MAX_ZOOM = 1.8;

const flatNodes = computed(() => {
    const list = [];
    function traverse(item) {
        if (!item || !item.id) return;
        list.push(item);
        if (item.children && Array.isArray(item.children)) {
            item.children.forEach(traverse);
        }
    }
    traverse(props.data);
    return list;
});

const graphLayout = computed(() => {
    if (!props.data || !props.data.id) {
        return { nodes: [], edges: [], width: 600, height: 300 };
    }

    const nodes = [];
    const edges = [];

    function calculateSubtreeWidth(node) {
        if (!node.children || !node.children.length || collapsedMap.value.has(node.id)) {
            return NODE_WIDTH;
        }
        let total = 0;
        node.children.forEach((child, idx) => {
            total += calculateSubtreeWidth(child);
            if (idx < node.children.length - 1) {
                total += SIBLING_GAP_X;
            }
        });
        return Math.max(NODE_WIDTH, total);
    }

    function layoutNode(node, currentX, currentY, isRoot = false) {
        const subtreeW = calculateSubtreeWidth(node);
        const nodeX = currentX + (subtreeW - NODE_WIDTH) / 2;

        const nodeObj = {
            ...node,
            isRoot,
            x: nodeX,
            y: currentY,
            width: NODE_WIDTH,
            height: NODE_HEIGHT,
        };
        nodes.push(nodeObj);

        if (node.children && node.children.length && !collapsedMap.value.has(node.id)) {
            let childX = currentX;
            const childY = currentY + NODE_HEIGHT + LEVEL_GAP_Y;

            node.children.forEach((child) => {
                const childSubtreeW = calculateSubtreeWidth(child);
                layoutNode(child, childX, childY, false);

                const childCenterX = childX + childSubtreeW / 2;
                const parentCenterX = nodeX + NODE_WIDTH / 2;

                edges.push({
                    key: `${node.id}->${child.id}`,
                    sx: parentCenterX,
                    sy: currentY + NODE_HEIGHT,
                    tx: childCenterX,
                    ty: childY,
                });

                childX += childSubtreeW + SIBLING_GAP_X;
            });
        }
    }

    layoutNode(props.data, CANVAS_PADDING, CANVAS_PADDING, true);

    let maxX = 600;
    let maxY = 300;

    nodes.forEach((n) => {
        if (n.x + n.width > maxX) maxX = n.x + n.width;
        if (n.y + n.height > maxY) maxY = n.y + n.height;
    });

    return {
        nodes,
        edges,
        width: maxX + CANVAS_PADDING,
        height: maxY + CANVAS_PADDING,
    };
});

function edgePath(edge) {
    const midY = edge.sy + (edge.ty - edge.sy) / 2;
    return `M ${edge.sx} ${edge.sy} V ${midY} H ${edge.tx} V ${edge.ty}`;
}

function nodeStyle(node) {
    return {
        left: `${node.x}px`,
        top: `${node.y}px`,
        width: `${node.width}px`,
        height: `${node.height}px`,
    };
}

function getInitials(name) {
    if (!name) return 'HC';
    const parts = name.split(' ').filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
}

function toggleCollapse(nodeId) {
    if (collapsedMap.value.has(nodeId)) {
        collapsedMap.value.delete(nodeId);
    } else {
        collapsedMap.value.set(nodeId, true);
    }
}

function toggleNodePopover(node) {
    if (activePopoverId.value === node.id) {
        activePopoverId.value = null;
    } else {
        activePopoverId.value = node.id;
        selectedNodeId.value = node.id;
    }
    emit('node-click', node);
}

function onNodeAction(actionKey, node) {
    activePopoverId.value = null;
    emit('node-action', { action: actionKey, node });
}

/* Canvas Pan & Zoom Logic */
const canvasTransform = computed(() => ({
    width: `${graphLayout.value.width}px`,
    height: `${graphLayout.value.height}px`,
    transform: `translate(${view.value.x}px, ${view.value.y}px) scale(${view.value.zoom})`,
}));

function clampZoom(z) {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, z));
}

function zoomAt(nextZoom, clientX, clientY) {
    const bounds = viewport.value?.getBoundingClientRect();
    if (!bounds) return;
    const zoom = clampZoom(nextZoom);
    const localX = clientX - bounds.left;
    const localY = clientY - bounds.top;
    const graphX = (localX - view.value.x) / view.value.zoom;
    const graphY = (localY - view.value.y) / view.value.zoom;
    view.value = {
        zoom,
        x: localX - graphX * zoom,
        y: localY - graphY * zoom,
    };
}

function zoomBy(factor) {
    const bounds = viewport.value?.getBoundingClientRect();
    if (!bounds) return;
    zoomAt(view.value.zoom * factor, bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
}

function fitGraph() {
    const bounds = viewport.value?.getBoundingClientRect();
    if (!bounds || !graphLayout.value.width) return;
    const zoom = clampZoom(Math.min(1, (bounds.width - 48) / graphLayout.value.width));
    view.value = {
        zoom,
        x: Math.max(16, (bounds.width - graphLayout.value.width * zoom) / 2),
        y: 16,
    };
}

function onWheel(e) {
    e.preventDefault();
    if (e.ctrlKey || e.metaKey || props.interactive) {
        zoomAt(view.value.zoom * Math.exp(-e.deltaY * 0.005), e.clientX, e.clientY);
    }
}

function onPointerDown(e) {
    if (e.target.closest('.hcis-org-node') || e.target.closest('.hcis-canvas-btn')) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 1) {
        panOrigin = { x: view.value.x, y: view.value.y, pointerX: e.clientX, pointerY: e.clientY };
    }
}

function onPointerMove(e) {
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (panOrigin && pointers.size === 1) {
        view.value = {
            ...view.value,
            x: panOrigin.x + (e.clientX - panOrigin.pointerX),
            y: panOrigin.y + (e.clientY - panOrigin.pointerY),
        };
    }
}

function onPointerUp(e) {
    pointers.delete(e.pointerId);
    if (!pointers.size) panOrigin = null;
}

function initCollapsedState(data, depth = 1) {
    if (!data || !data.id) return;
    if (props.initialCollapseLevel > 0 && depth >= props.initialCollapseLevel && data.children && data.children.length) {
        collapsedMap.value.set(data.id, true);
    }
    if (data.children && Array.isArray(data.children)) {
        data.children.forEach((child) => initCollapsedState(child, depth + 1));
    }
}

watch(
    () => props.data,
    (newData) => {
        collapsedMap.value = new Map();
        if (newData) {
            initCollapsedState(newData, 1);
            nextTick(fitGraph);
        }
    },
    { immediate: true },
);

function handleGlobalClick(e) {
    if (!e.target.closest('.hcis-org-node')) {
        activePopoverId.value = null;
    }
}

onMounted(() => {
    document.addEventListener('click', handleGlobalClick);
    nextTick(fitGraph);
});

onUnmounted(() => {
    document.removeEventListener('click', handleGlobalClick);
});
</script>

<style scoped>
.hcis-org-chart {
    width: 100%;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-xl);
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

.hcis-org-chart--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 2px 8px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-org-chart--glass {
    background: rgb(var(--hcis-white-rgb) / 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.8);
}

.hcis-org-chart--flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

/* Header & Controls */
.hcis-org-chart__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-org-chart__header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hcis-org-chart__icon-glow {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgb(var(--hcis-brand-600-rgb) / 0.15);
}

.hcis-org-chart__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
}

.hcis-org-chart__subtitle {
    font-size: 0.725rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-org-chart__header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hcis-org-canvas-controls {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 8px;
}

.hcis-canvas-btn {
    border: none;
    background: transparent;
    color: var(--hcis-text, var(--hcis-slate-700));
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-canvas-btn:hover {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    box-shadow: 0 1px 3px rgb(var(--hcis-black-rgb) / 0.08);
}

.hcis-canvas-btn--fit {
    width: auto;
    padding: 0 8px;
    font-size: 0.7rem;
    font-weight: 700;
}

.hcis-canvas-zoom-text {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0 6px;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-org-chart__node-count {
    font-size: 0.725rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

/* Viewport Canvas */
.hcis-org-viewport {
    position: relative;
    width: 100%;
    height: 22rem;
    overflow: hidden;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    cursor: grab;
    touch-action: none;
}

.hcis-org-viewport:active {
    cursor: grabbing;
}

.hcis-org-canvas {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    user-select: none;
}

/* SVG Branching Lines */
.hcis-org-svg-edges {
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}

.hcis-org-edge-line {
    fill: none;
    stroke: var(--hcis-slate-300);
    stroke-width: 2px;
    transition: stroke 200ms ease;
}

/* Org Tree Node Card */
.hcis-org-node {
    position: absolute;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 0;
    cursor: pointer;
    outline: none;
    box-shadow: 0 2px 8px rgb(var(--hcis-slate-900-rgb) / 0.05);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
    box-sizing: border-box;
}

.hcis-org-node.is-root {
    border-color: var(--hcis-brand-400, var(--hcis-brand-400));
    box-shadow: 0 4px 16px rgb(var(--hcis-brand-600-rgb) / 0.15);
}

.hcis-org-node:hover,
.hcis-org-node.is-selected {
    border-color: var(--hcis-brand-500, var(--hcis-brand-500));
    box-shadow: 0 8px 20px -4px rgb(var(--hcis-brand-600-rgb) / 0.18);
    transform: translateY(-2px);
}

/* Port Dots Top and Bottom */
.hcis-org-node__port {
    position: absolute;
    left: 50%;
    margin-left: -4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-white);
    border: 2px solid var(--hcis-brand-500, var(--hcis-brand-500));
    box-sizing: border-box;
    z-index: 10;
}

.hcis-org-node__port--top { top: -4px; }
.hcis-org-node__port--bottom { bottom: -4px; }

/* Inner Container */
.hcis-org-node__inner {
    width: 100%;
    height: 100%;
    padding: 0.625rem 0.75rem;
    overflow: hidden !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
}

.hcis-org-node__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 0;
    overflow: hidden;
}

.hcis-org-node__dept-tag {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 130px;
}

.hcis-org-node__toggle-btn {
    border: 1px solid var(--hcis-brand-200, var(--hcis-brand-200));
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    border-radius: 9999px;
    padding: 1px 6px;
    font-size: 0.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 2px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
    flex-shrink: 0;
}

.hcis-org-node__toggle-btn:hover {
    background: var(--hcis-brand-600, var(--hcis-brand-600));
    color: var(--hcis-white);
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-org-node__body {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    overflow: hidden;
}

.hcis-org-node__avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, var(--hcis-brand-500) 0%, var(--hcis-brand-600) 100%);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgb(var(--hcis-brand-600-rgb) / 0.25);
}

.hcis-org-node__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hcis-org-node__meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.hcis-org-node__name {
    font-size: 0.775rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
    display: block;
}

.hcis-org-node__title {
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

/* Glassmorphic Popover Panel */
.hcis-org-popover {
    position: absolute;
    top: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%);
    width: 15.5rem;
    background: rgb(var(--hcis-white-rgb) / 0.95);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
    border-radius: 16px;
    padding: 0.875rem;
    box-shadow: 0 16px 36px -8px rgb(var(--hcis-slate-900-rgb) / 0.22);
    z-index: 100;
}

.hcis-org-popover__caret {
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 10px;
    height: 10px;
    background: var(--hcis-white);
    border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
    border-left: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
}

.hcis-org-popover__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.625rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-org-popover__avatar-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.hcis-org-popover__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-brand-500) 0%, var(--hcis-brand-600) 100%);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    flex-shrink: 0;
}

.hcis-org-popover__name {
    display: block;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-org-popover__title {
    font-size: 0.675rem;
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    font-weight: 600;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-org-popover__close {
    border: none;
    background: transparent;
    color: var(--hcis-slate-400);
    cursor: pointer;
    padding: 2px;
    border-radius: 4px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.hcis-org-popover__close:hover { background: var(--hcis-slate-100); color: var(--hcis-slate-900); }

.hcis-org-popover__body {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin-bottom: 0.75rem;
}

.hcis-org-popover__field {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.hcis-org-popover__label {
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
}

.hcis-org-popover__val {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}

.hcis-org-popover__val-chip {
    font-size: 0.675rem;
    font-weight: 700;
    font-family: monospace;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    width: max-content;
}

.hcis-org-popover__footer {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.hcis-org-popover-enter-active,
.hcis-org-popover-leave-active {
    transition: opacity 200ms var(--hcis-ease), transform 200ms var(--hcis-ease);
}

.hcis-org-popover-enter-from,
.hcis-org-popover-leave-to {
    opacity: 0;
    transform: translate(-50%, 8px) scale(0.95);
}
</style>
