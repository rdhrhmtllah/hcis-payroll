<template>
    <div
        class="hcis-pipeline-visualizer"
        :class="[
            `hcis-pipeline-visualizer--${variant}`,
            `hcis-pipeline-visualizer--${direction}`,
            { 'is-interactive': interactive },
        ]"
    >
        <!-- Canvas Header Controls -->
        <div v-if="title || $slots.header || allowZoom" class="hcis-pipeline-visualizer__header">
            <slot name="header">
                <div class="hcis-pipeline-visualizer__header-left">
                    <div class="hcis-pipeline-visualizer__icon-glow">
                        <HcisIcon v-if="icon" :name="icon" size="sm" />
                    </div>
                    <div>
                        <h6 class="hcis-pipeline-visualizer__title">{{ title }}</h6>
                        <small v-if="subtitle" class="hcis-pipeline-visualizer__subtitle">{{ subtitle }}</small>
                    </div>
                </div>

                <div class="hcis-pipeline-visualizer__header-right">
                    <!-- Zoom & Pan Canvas Toolbar Controls -->
                    <div v-if="allowZoom" class="hcis-pipeline-canvas-controls">
                        <button type="button" class="hcis-canvas-btn" title="Zoom Out (-)" @click="zoomBy(0.85)">
                            <HcisIcon name="dash" size="2xs" />
                        </button>
                        <span class="hcis-canvas-zoom-text">{{ Math.round(view.zoom * 100) }}%</span>
                        <button type="button" class="hcis-canvas-btn" title="Zoom In (+)" @click="zoomBy(1.18)">
                            <HcisIcon name="plus" size="2xs" />
                        </button>
                        <button type="button" class="hcis-canvas-btn hcis-canvas-btn--fit" title="Paskan Seluruh Alur" @click="fitGraph">
                            Fit
                        </button>
                    </div>

                    <span v-if="normalizedSteps.length" class="hcis-pipeline-visualizer__node-count">
                        {{ normalizedSteps.length }} Step
                    </span>
                    <HcisButton
                        v-if="allowAdd"
                        label="Tambah Step"
                        leading-icon="plus-lg"
                        size="sm"
                        variant="secondary"
                        @click="$emit('add-node', { order: normalizedSteps.length + 1 })"
                    />
                </div>
            </slot>
        </div>

        <!-- SVG Bezier Node Canvas Viewport -->
        <div
            ref="viewport"
            class="hcis-pipeline-viewport"
            @wheel="onWheel"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
            <div class="hcis-pipeline-canvas" :style="canvasTransform">
                <!-- SVG Curved Bezier Connecting Edges -->
                <svg
                    class="hcis-pipeline-svg-edges"
                    :width="graphLayout.width"
                    :height="graphLayout.height"
                    :viewBox="`0 0 ${graphLayout.width} ${graphLayout.height}`"
                    aria-hidden="true"
                >
                    <defs>
                        <marker id="hcis-pipeline-arrow-default" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 1.5 L 9 5 L 0 8.5 z" fill="#94a3b8" />
                        </marker>
                        <marker id="hcis-pipeline-arrow-active" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 1.5 L 9 5 L 0 8.5 z" fill="#4f46e5" />
                        </marker>
                    </defs>

                    <template v-for="edge in graphLayout.edges" :key="edge.key">
                        <!-- Static Background Line -->
                        <path
                            :d="edgePath(edge)"
                            class="hcis-pipeline-edge-base"
                            :class="{ 'is-active': edge.isActive }"
                        />
                        <!-- Animated Flowing Energy Particle Line -->
                        <path
                            :d="edgePath(edge)"
                            class="hcis-pipeline-edge-flow"
                            :class="{ 'is-active': edge.isActive }"
                            :marker-end="edge.isActive ? 'url(#hcis-pipeline-arrow-active)' : 'url(#hcis-pipeline-arrow-default)'"
                        />
                    </template>
                </svg>

                <!-- Rendered Flow Nodes -->
                <template v-for="node in graphLayout.nodes" :key="node.key">
                    <!-- Sleek Start / End Terminal Pill Node -->
                    <div
                        v-if="node.type !== 'step'"
                        class="hcis-pipeline-terminal"
                        :class="[
                            `hcis-pipeline-terminal--${node.type}`,
                            { 'is-active': isNodeActive(node) }
                        ]"
                        :style="nodeStyle(node)"
                    >
                        <span v-if="node.type === 'start'" class="hcis-pipeline-node__port hcis-pipeline-node__port--out" />
                        <span v-if="node.type === 'end'" class="hcis-pipeline-node__port hcis-pipeline-node__port--in" />

                        <div class="hcis-pipeline-terminal__badge">
                            <HcisIcon :name="node.type === 'start' ? 'play-fill' : 'check-lg'" size="2xs" />
                        </div>
                        <div class="hcis-pipeline-terminal__content">
                            <span class="hcis-pipeline-terminal__caption">{{ node.caption }}</span>
                            <strong class="hcis-pipeline-terminal__title" :title="node.label">{{ node.label }}</strong>
                        </div>
                    </div>

                    <!-- Interactive Step Node Card -->
                    <div
                        v-else
                        class="hcis-pipeline-node"
                        :class="[
                            `hcis-pipeline-node--${getNodeStatus(node)}`,
                            {
                                'is-active': isNodeActive(node),
                                'is-current': isNodeCurrent(node),
                                'is-selected': selectedNodeKey === node.key || activePopoverId === node.key,
                                'is-disabled': node.active === false,
                                'is-editable': node.editable,
                                'is-shared': node.shared,
                            }
                        ]"
                        :style="nodeStyle(node)"
                        tabindex="0"
                        @click.stop="toggleNodePopover(node)"
                        @keydown.enter.stop="toggleNodePopover(node)"
                    >
                        <!-- Port Dots Positioned Exactly at Edge Center -->
                        <span class="hcis-pipeline-node__port hcis-pipeline-node__port--in" />
                        <span class="hcis-pipeline-node__port hcis-pipeline-node__port--out" />

                        <!-- Strict Inner Container with zero overflow bleed -->
                        <div class="hcis-pipeline-node__inner">
                            <!-- Node Top Bar: Type Tag (Left) & Order Badge + Action Btn (Right) -->
                            <div class="hcis-pipeline-node__header">
                                <span class="hcis-pipeline-node__type-tag" :title="getNodeTypeLabel(node)">
                                    <HcisIcon :name="getNodeTypeIcon(node)" size="2xs" />
                                    <span class="hcis-pipeline-node__type-text">{{ getNodeTypeLabel(node) }}</span>
                                </span>
                                <div class="hcis-pipeline-node__header-right">
                                    <span class="hcis-pipeline-node__order">
                                        {{ node.shared ? `${node.flowCount} alur` : `#${node.order}` }}
                                    </span>
                                    <button
                                        type="button"
                                        class="hcis-pipeline-node__opt-btn"
                                        title="Opsi Node"
                                        @click.stop="toggleNodePopover(node)"
                                    >
                                        <HcisIcon name="three-dots-vertical" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <!-- Node Middle Content: Icon + Title & Subtitle -->
                            <div class="hcis-pipeline-node__body">
                                <div class="hcis-pipeline-node__icon-box">
                                    <HcisIcon :name="node.icon || getNodeIcon(node)" size="sm" />
                                </div>
                                <div class="hcis-pipeline-node__meta">
                                    <strong class="hcis-pipeline-node__label" :title="node.label">{{ node.label }}</strong>
                                    <span class="hcis-pipeline-node__sub" :title="getNodeSubtext(node)">{{ getNodeSubtext(node) }}</span>
                                </div>
                            </div>

                            <!-- Node Footer Badges Row -->
                            <div class="hcis-pipeline-node__badges">
                                <span v-if="node.mode" class="hcis-pipeline-chip hcis-pipeline-chip--mode" :title="node.mode">
                                    {{ formatModeChip(node.mode) }}
                                </span>
                                <span v-if="node.mandatory" class="hcis-pipeline-chip hcis-pipeline-chip--warning">Wajib</span>
                                <span v-if="node.editable" class="hcis-pipeline-chip hcis-pipeline-chip--success">Editable</span>
                                <span v-if="node.stopOnReject" class="hcis-pipeline-chip hcis-pipeline-chip--danger">Stop Reject</span>
                            </div>
                        </div>

                        <!-- Glassmorphic n8n Node Popover Panel -->
                        <Transition name="hcis-node-popover">
                            <div
                                v-if="activePopoverId === node.key"
                                class="hcis-node-popover"
                                @click.stop
                            >
                                <!-- Popover Caret Arrow -->
                                <div class="hcis-node-popover__caret" />

                                <div class="hcis-node-popover__header">
                                    <div class="hcis-node-popover__badge-wrap">
                                        <div class="hcis-node-popover__icon">
                                            <HcisIcon name="gear-wide-connected" size="xs" />
                                        </div>
                                        <div>
                                            <span class="hcis-node-popover__title">Step #{{ node.order }}</span>
                                            <small class="hcis-node-popover__subtitle">{{ getNodeTypeLabel(node) }}</small>
                                        </div>
                                    </div>

                                    <button type="button" class="hcis-node-popover__close" @click.stop="activePopoverId = null">
                                        <HcisIcon name="x" size="xs" />
                                    </button>
                                </div>

                                <div class="hcis-node-popover__body">
                                    <div class="hcis-node-popover__field">
                                        <span class="hcis-node-popover__label">Nama Approval</span>
                                        <strong class="hcis-node-popover__val">{{ node.approverName || node.label }}</strong>
                                    </div>
                                    <div v-if="node.sourceLabel" class="hcis-node-popover__field">
                                        <span class="hcis-node-popover__label">Source Reference</span>
                                        <span class="hcis-node-popover__val-chip" :title="node.sourceLabel">{{ node.sourceLabel }}</span>
                                    </div>
                                    <div v-if="node.mode" class="hcis-node-popover__field">
                                        <span class="hcis-node-popover__label">Aturan Penentuan</span>
                                        <span class="hcis-node-popover__val" :title="node.mode">{{ node.mode }}</span>
                                    </div>
                                </div>

                                <div class="hcis-node-popover__footer">
                                    <HcisButton
                                        label="Rincian"
                                        size="sm"
                                        variant="secondary"
                                        leading-icon="info-circle"
                                        @click.stop="onNodeAction('detail', node)"
                                    />
                                    <HcisButton
                                        v-if="node.editable"
                                        label="Ubah Pejabat"
                                        size="sm"
                                        variant="primary"
                                        leading-icon="pencil"
                                        @click.stop="onNodeAction('edit', node)"
                                    />
                                    <button
                                        v-if="allowDelete"
                                        type="button"
                                        class="hcis-node-popover__del-btn"
                                        title="Hapus Step"
                                        @click.stop="onNodeAction('delete', node)"
                                    >
                                        <HcisIcon name="trash" size="xs" />
                                    </button>
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
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisButton from './HcisButton.vue';

defineOptions({ name: 'HcisPipelineVisualizer' });

const props = defineProps({
    title: { type: String, default: 'Visualisasi Alur Approval' },
    subtitle: { type: String, default: 'n8n Interactive Flow Canvas Engine' },
    icon: { type: String, default: 'diagram-3' },
    steps: { type: Array, default: () => [] },
    activeStep: { type: [Number, String], default: null },
    direction: {
        type: String,
        default: 'auto',
        validator: (v) => ['auto', 'horizontal', 'vertical'].includes(v),
    },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'flat', 'glass'].includes(v),
    },
    interactive: { type: Boolean, default: true },
    allowZoom: { type: Boolean, default: true },
    allowAdd: { type: Boolean, default: false },
    allowInsert: { type: Boolean, default: true },
    allowDelete: { type: Boolean, default: true },
});

const emit = defineEmits(['node-click', 'node-action', 'add-node', 'insert-node', 'manage-step']);

const viewport = ref(null);
const activePopoverId = ref(null);
const selectedNodeKey = ref(null);
const view = ref({ x: 24, y: 24, zoom: 1 });
const pointers = new Map();
let panOrigin = null;

const NODE_WIDTH = 220;
const NODE_HEIGHT = 126;
const TERMINAL_WIDTH = 180;
const TERMINAL_HEIGHT = 48;
const COLUMN_GAP = 72;
const CANVAS_PADDING = 24;
const MIN_ZOOM = 0.4;
const MAX_ZOOM = 1.8;

const normalizedSteps = computed(() => {
    if (props.steps && props.steps.length) {
        return props.steps.map((s, idx) => ({
            ...s,
            order: s.order || idx + 1,
            label: s.label || s.Nama_Approval || `Approval ${idx + 1}`,
        }));
    }
    return [];
});

const graphLayout = computed(() => {
    const rawSteps = normalizedSteps.value;
    const nodeMap = new Map();
    const edges = [];

    const startKey = 'start:0';
    const endKey = 'end:0';

    nodeMap.set(startKey, {
        key: startKey,
        type: 'start',
        label: props.title || 'Pengajuan',
        caption: 'Mulai alur',
        order: 0,
        width: TERMINAL_WIDTH,
        height: TERMINAL_HEIGHT,
    });

    let prevKey = startKey;
    rawSteps.forEach((step, idx) => {
        const key = step.id ? `step:${step.id}` : `step:${idx}`;
        const nodeObj = {
            key,
            type: 'step',
            order: step.order || idx + 1,
            label: step.label || step.Nama_Approval || `Approval ${idx + 1}`,
            sourceLabel: step.sourceLabel || step.Source_Reference || step.Custom_Approver_Kode || null,
            attributeLabel: step.attributeLabel || step.approver_mode || null,
            approverName: step.approverName || step.label,
            mode: step.mode || step.approver_mode,
            editable: step.editable !== false,
            mandatory: step.mandatory || step.Is_Mandatory === 'Y',
            stopOnReject: step.stopOnReject || step.Stop_If_Rejected === 'Y',
            active: step.active !== false,
            icon: step.icon,
            width: NODE_WIDTH,
            height: NODE_HEIGHT,
        };
        nodeMap.set(key, nodeObj);
        edges.push({ key: `${prevKey}->${key}`, from: prevKey, to: key });
        prevKey = key;
    });

    nodeMap.set(endKey, {
        key: endKey,
        type: 'end',
        label: 'Approval Selesai',
        caption: 'Akhir alur',
        order: rawSteps.length + 1,
        width: TERMINAL_WIDTH,
        height: TERMINAL_HEIGHT,
    });
    edges.push({ key: `${prevKey}->${endKey}`, from: prevKey, to: endKey });

    const positionedNodes = [];
    const positionMap = new Map();
    let currentX = CANVAS_PADDING;
    const baseCenterY = CANVAS_PADDING + 63;

    Array.from(nodeMap.values()).forEach((node) => {
        const nodeY = baseCenterY - node.height / 2;
        const positioned = {
            ...node,
            x: currentX,
            y: nodeY,
        };
        positionedNodes.push(positioned);
        positionMap.set(node.key, positioned);
        currentX += node.width + COLUMN_GAP;
    });

    const activeIndex = Number(props.activeStep || rawSteps.length);

    const positionedEdges = edges.map((edge, idx) => {
        const from = positionMap.get(edge.from);
        const to = positionMap.get(edge.to);
        const isActive = idx < activeIndex;

        return {
            ...edge,
            isActive,
            sx: from.x + from.width,
            sy: baseCenterY,
            tx: to.x - 2,
            ty: baseCenterY,
        };
    });

    return {
        nodes: positionedNodes,
        edges: positionedEdges,
        width: Math.max(currentX + CANVAS_PADDING, 600),
        height: 240,
    };
});

function edgePath(edge) {
    const distance = Math.max((edge.tx - edge.sx) * 0.5, 36);
    return `M ${edge.sx} ${edge.sy} C ${edge.sx + distance} ${edge.sy}, ${edge.tx - distance} ${edge.ty}, ${edge.tx} ${edge.ty}`;
}

function nodeStyle(node) {
    return {
        left: `${node.x}px`,
        top: `${node.y}px`,
        width: `${node.width}px`,
        height: `${node.height}px`,
    };
}

function getNodeSubtext(node) {
    const raw = node.sourceLabel || node.attributeLabel || node.mode || 'Approval Step';
    if (raw.includes('Kode_Karyawan') || raw.includes('→')) {
        return 'Tabel Flow';
    }
    return raw;
}

function formatModeChip(mode) {
    if (!mode) return '';
    if (mode.includes('Kode_Karyawan') || mode.includes('→')) {
        return 'Tabel Flow';
    }
    return mode;
}

function isNodeActive(node) {
    if (props.activeStep === null) return true;
    if (node.type === 'start') return true;
    if (node.type === 'end') return Number(props.activeStep) >= normalizedSteps.value.length;
    return Number(node.order) <= Number(props.activeStep);
}

function isNodeCurrent(node) {
    if (props.activeStep === null) return false;
    return Number(node.order) === Number(props.activeStep);
}

function toggleNodePopover(node) {
    if (node.type !== 'step') return;
    if (activePopoverId.value === node.key) {
        activePopoverId.value = null;
    } else {
        activePopoverId.value = node.key;
        selectedNodeKey.value = node.key;
    }
    emit('node-click', { step: node, index: node.order - 1 });
}

function onNodeAction(actionKey, node) {
    activePopoverId.value = null;
    emit('node-action', { action: actionKey, step: node, index: node.order - 1 });
    if (actionKey === 'edit') {
        emit('manage-step', node);
    }
}

function getNodeStatus(step) {
    if (step.active === false) return 'disabled';
    if (step.stopOnReject) return 'warning';
    if (step.mandatory) return 'primary';
    return 'default';
}

function getNodeTypeLabel(step) {
    if (step.mode === 'Direct Manager') return 'Manager Approval';
    if (step.mode === 'Role Based') return 'Role Approver';
    if (step.order === 1) return 'Trigger Step';
    return 'Approver Step';
}

function getNodeTypeIcon(step) {
    if (step.mode === 'Direct Manager') return 'person-workspace';
    if (step.mode === 'Role Based') return 'shield-check';
    if (step.order === 1) return 'lightning-charge-fill';
    return 'check-circle-fill';
}

function getNodeIcon(step) {
    if (step.icon) return step.icon;
    if (step.stopOnReject) return 'shield-exclamation';
    if (step.mandatory) return 'shield-lock';
    return 'person-badge';
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
    if (e.target.closest('.hcis-pipeline-node') || e.target.closest('.hcis-canvas-btn')) return;
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

function handleGlobalClick(e) {
    if (!e.target.closest('.hcis-pipeline-node')) {
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
.hcis-pipeline-visualizer {
    width: 100%;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-xl);
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

.hcis-pipeline-visualizer--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 2px 8px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-pipeline-visualizer--glass {
    background: rgb(var(--hcis-white-rgb) / 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.8);
}

.hcis-pipeline-visualizer--flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

/* Header & Controls */
.hcis-pipeline-visualizer__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-pipeline-visualizer__header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hcis-pipeline-visualizer__icon-glow {
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

.hcis-pipeline-visualizer__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
}

.hcis-pipeline-visualizer__subtitle {
    font-size: 0.725rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-pipeline-visualizer__header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hcis-pipeline-canvas-controls {
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

.hcis-pipeline-visualizer__node-count {
    font-size: 0.725rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

/* Viewport Canvas */
.hcis-pipeline-viewport {
    position: relative;
    width: 100%;
    height: 15rem;
    overflow: hidden;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    cursor: grab;
    touch-action: none;
}

.hcis-pipeline-viewport:active {
    cursor: grabbing;
}

.hcis-pipeline-canvas {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    user-select: none;
}

/* SVG Flowing Edges & Particles */
.hcis-pipeline-svg-edges {
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}

.hcis-pipeline-edge-base {
    fill: none;
    stroke: var(--hcis-slate-300);
    stroke-width: 2px;
    transition: stroke 200ms ease;
}

.hcis-pipeline-edge-base.is-active {
    stroke: var(--hcis-brand-400);
    stroke-width: 2.5px;
}

.hcis-pipeline-edge-flow {
    fill: none;
    stroke: rgb(var(--hcis-slate-400-rgb) / 0.5);
    stroke-width: 2px;
    stroke-dasharray: 8 8;
    animation: hcis-flow-dash 15s linear infinite;
}

.hcis-pipeline-edge-flow.is-active {
    stroke: var(--hcis-brand-600, var(--hcis-brand-600));
    stroke-width: 3px;
    filter: drop-shadow(0 0 5px rgb(var(--hcis-brand-600-rgb) / 0.4));
    animation: hcis-flow-dash 8s linear infinite;
}

@keyframes hcis-flow-dash {
    to { stroke-dashoffset: -120; }
}

/* Sleek Capsule Terminal Node (n8n Style 180px x 48px) */
.hcis-pipeline-terminal {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.375rem 0.875rem 0.375rem 0.5rem;
    border-radius: 9999px;
    background: var(--hcis-white);
    border: 1.5px solid var(--hcis-slate-300);
    box-shadow: 0 4px 12px rgb(var(--hcis-slate-900-rgb) / 0.05);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
    box-sizing: border-box;
}

.hcis-pipeline-terminal--start {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-violet-50) 100%);
    border-color: var(--hcis-brand-300);
}

.hcis-pipeline-terminal--start.is-active {
    border-color: var(--hcis-brand-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.18), 0 6px 16px rgb(var(--hcis-brand-600-rgb) / 0.15);
}

.hcis-pipeline-terminal--end {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-success-50) 100%);
    border-color: var(--hcis-success-300);
}

.hcis-pipeline-terminal--end.is-active {
    border-color: var(--hcis-success-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-success-500-rgb) / 0.18), 0 6px 16px rgb(var(--hcis-success-500-rgb) / 0.15);
}

.hcis-pipeline-terminal__badge {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-brand-500) 0%, var(--hcis-brand-600) 100%);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgb(var(--hcis-brand-600-rgb) / 0.3);
}

.hcis-pipeline-terminal--end .hcis-pipeline-terminal__badge {
    background: linear-gradient(135deg, var(--hcis-success-500) 0%, var(--hcis-success-600) 100%);
    box-shadow: 0 2px 6px rgb(var(--hcis-success-500-rgb) / 0.3);
}

.hcis-pipeline-terminal__content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.hcis-pipeline-terminal__caption {
    font-size: 0.575rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-500);
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-pipeline-terminal--end .hcis-pipeline-terminal__caption {
    color: var(--hcis-success-600);
}

.hcis-pipeline-terminal__title {
    font-size: 0.775rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 120px;
}

/* Interactive Step Node Outer Box */
.hcis-pipeline-node {
    position: absolute;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    padding: 0;
    cursor: pointer;
    outline: none;
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.04);
    transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
    box-sizing: border-box;
}

/* Strict Inner Container with zero overflow bleed */
.hcis-pipeline-node__inner {
    width: 100%;
    height: 100%;
    padding: 0.625rem 0.75rem;
    overflow: hidden !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
    pointer-events: auto;
}

.hcis-pipeline-node.is-active {
    border-color: var(--hcis-brand-400, var(--hcis-brand-400));
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-600-rgb) / 0.12);
}

.hcis-pipeline-node.is-current {
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-600-rgb) / 0.2), 0 8px 24px rgb(var(--hcis-brand-600-rgb) / 0.2);
    animation: hcis-node-pulse 2s infinite ease-in-out;
}

@keyframes hcis-node-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

.hcis-pipeline-node:hover,
.hcis-pipeline-node.is-selected {
    border-color: var(--hcis-brand-500, var(--hcis-brand-500));
    box-shadow: 0 8px 20px -4px rgb(var(--hcis-brand-600-rgb) / 0.18);
}

/* Port Dots Exactly Centered Vertically and Horizontally */
.hcis-pipeline-node__port {
    position: absolute;
    top: 50%;
    margin-top: -4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-white);
    border: 2.5px solid var(--hcis-slate-400);
    box-sizing: border-box;
    transition: border-color 200ms ease, box-shadow 200ms ease;
    z-index: 10;
}

.hcis-pipeline-node.is-active .hcis-pipeline-node__port,
.hcis-pipeline-terminal.is-active .hcis-pipeline-node__port {
    border-color: var(--hcis-brand-600, var(--hcis-brand-600));
    box-shadow: 0 0 0 2px rgb(var(--hcis-brand-600-rgb) / 0.2);
}

.hcis-pipeline-node__port--in { left: -4px; }
.hcis-pipeline-node__port--out { right: -4px; }

/* Header Row */
.hcis-pipeline-node__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 0;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.hcis-pipeline-node__type-tag {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    letter-spacing: 0.02em;
    min-width: 0;
    max-width: 110px;
    overflow: hidden;
}

.hcis-pipeline-node__type-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
    max-width: 90px;
}

.hcis-pipeline-node__header-right {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.hcis-pipeline-node__order {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-slate-400);
}

.hcis-pipeline-node__opt-btn {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: var(--hcis-slate-400);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
    flex-shrink: 0;
}

.hcis-pipeline-node__opt-btn:hover { background: var(--hcis-slate-100); color: var(--hcis-slate-900); }

/* Middle Content Body */
.hcis-pipeline-node__body {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.hcis-pipeline-node__icon-box {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hcis-pipeline-node__meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    width: 130px;
    max-width: 130px;
    overflow: hidden;
}

.hcis-pipeline-node__label {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
    display: block;
    max-width: 130px;
}

.hcis-pipeline-node__sub {
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 130px;
}

/* Badges Row */
.hcis-pipeline-node__badges {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    width: 100%;
    overflow: hidden;
    white-space: nowrap;
}

.hcis-pipeline-chip {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90px;
    display: inline-block;
    vertical-align: middle;
    flex-shrink: 0;
}

.hcis-pipeline-chip--mode { background: var(--hcis-slate-100); color: var(--hcis-slate-600); }
.hcis-pipeline-chip--warning { background: var(--hcis-warning-100); color: var(--hcis-warning-700); }
.hcis-pipeline-chip--success { background: var(--hcis-green-100); color: var(--hcis-green-700); }
.hcis-pipeline-chip--danger { background: var(--hcis-danger-100); color: var(--hcis-danger-700); }

/* Frosted Glassmorphic n8n Node Popover Panel */
.hcis-node-popover {
    position: absolute;
    top: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%);
    width: 15rem;
    background: rgb(var(--hcis-white-rgb) / 0.94);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
    border-radius: 16px;
    padding: 0.875rem;
    box-shadow: 0 16px 36px -8px rgb(var(--hcis-slate-900-rgb) / 0.22), 0 0 0 1px rgb(var(--hcis-brand-500-rgb) / 0.1);
    z-index: 100;
}

/* Upward Caret Pointer Arrow */
.hcis-node-popover__caret {
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

.hcis-node-popover__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.625rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.hcis-node-popover__badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hcis-node-popover__icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 8px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-node-popover__title {
    display: block;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.1;
}

.hcis-node-popover__subtitle {
    font-size: 0.675rem;
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    font-weight: 600;
}

.hcis-node-popover__close {
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

.hcis-node-popover__close:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
}

.hcis-node-popover__body {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin-bottom: 0.75rem;
}

.hcis-node-popover__field {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.hcis-node-popover__label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-500);
}

.hcis-node-popover__val {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    word-break: break-all;
}

.hcis-node-popover__val-chip {
    font-size: 0.675rem;
    font-weight: 700;
    font-family: monospace;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
    width: max-content;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hcis-node-popover__footer {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.hcis-node-popover__del-btn {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-danger-100);
    background: var(--hcis-danger-50);
    color: var(--hcis-danger-500);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: ease;
    margin-left: auto;
}

.hcis-node-popover__del-btn:hover {
    background: var(--hcis-danger-500);
    color: var(--hcis-white);
    border-color: var(--hcis-danger-500);
    box-shadow: 0 4px 12px rgb(var(--hcis-danger-500-rgb) / 0.25);
}

/* Transition for Node Popover */
.hcis-node-popover-enter-active,
.hcis-node-popover-leave-active {
    transition: opacity 200ms var(--hcis-ease), transform 200ms var(--hcis-ease);
}

.hcis-node-popover-enter-from,
.hcis-node-popover-leave-to {
    opacity: 0;
    transform: translate(-50%, 8px) scale(0.95);
}
</style>
