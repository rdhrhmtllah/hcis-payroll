<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import RoleMembersModal from './RoleMembersModal.vue';

const props = defineProps({
    configs: {
        type: Array,
        default: () => [],
    },
    selectedStepId: {
        type: [Number, String],
        default: null,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    grupId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['manage-step']);

const loading = ref(false);
const flows = ref([]);
const viewport = ref(null);
const roleModalOpen = ref(false);
const selectedRoleDetail = ref(null);
const view = ref({ x: 24, y: 24, zoom: 1 });
const pointers = new Map();
let panOrigin = null;
let pinchOrigin = null;

const NODE_WIDTH = 196;
const COLUMN_GAP = 72;
const ROW_GAP = 18;
const CANVAS_PADDING = 24;
const MIN_ZOOM = 0.3;
const MAX_ZOOM = 1.8;

const stepName = (detail) => detail?.Nama_Approval || detail?.Approval_Name || `Approval ${detail?.Approval_Order ?? '-'}`;
const configName = (config, fallback = 'Alur fitur') => config?.Approval_Config_Name
    || config?.Kode_Config
    || config?.Approval_Config_Code
    || config?.Nama_Grup_Fitur
    || fallback;
const nodeKey = (detail) => detail?.Source_Reference
    && ['TABLE', 'KPI_TABLE'].includes(detail?.Source_Type)
    ? `source:${detail.Source_Reference}`
    : `detail:${detail.Id}`;
const sourceLabel = (detail) => detail?.Source_Reference
    || detail?.Custom_Approver_Kode
    || detail?.Source_Type
    || 'Tanpa source';
const attributeLabel = (detail) => {
    if (detail?.Source_Type === 'KPI_TABLE') return 'Kode_Karyawan_Requester → Kode_Karyawan_Approver • order_flow';
    if (detail?.Source_Type === 'TABLE') return 'Kode_Karyawan_Requester → Kode_Karyawan • order_flow';
    if (detail?.Source_Type === 'ROLE') return 'Role_Code';
    if (detail?.Source_Type === 'DIRECT_EMPLOYEE' || detail?.Custom_Approver_Kode) return 'Kode_Karyawan';
    return detail?.approver_mode || 'Read-only';
};
const estimatedLines = (text, charactersPerLine) => Math.max(1, Math.ceil(String(text || '').length / charactersPerLine));
const nodeHeight = (node) => {
    if (node.type !== 'step') return 45 + estimatedLines(node.label, 24) * 12;
    const usage = node.usages?.[0];
    return 54
        + estimatedLines(node.label, 25) * 13
        + estimatedLines(sourceLabel(usage?.detail), 30) * 10
        + estimatedLines(attributeLabel(usage?.detail), 34) * 10;
};

const totalPositions = computed(() => flows.value.reduce((total, flow) => total + flow.details.length, 0));

const graphLayout = computed(() => {
    const nodeMap = new Map();
    const edgeMap = new Map();

    flows.value.forEach((flow, flowIndex) => {
        const featureName = configName(flow.config, `Fitur ${flowIndex + 1}`);
        const startKey = `start:${flowIndex}`;
        const endKey = `end:${flowIndex}`;
        const sequence = [startKey];

        nodeMap.set(startKey, {
            key: startKey,
            type: 'start',
            label: `Pengajuan ${featureName}`,
            caption: 'Mulai alur',
            usages: [],
        });
        nodeMap.set(endKey, {
            key: endKey,
            type: 'end',
            label: `Selesai ${featureName}`,
            caption: 'Akhir alur',
            usages: [],
        });

        flow.details.forEach((detail) => {
            const key = nodeKey(detail);
            if (!nodeMap.has(key)) {
                nodeMap.set(key, {
                    key,
                    type: 'step',
                    label: stepName(detail),
                    sourceReference: detail.Source_Reference || null,
                    usages: [],
                });
            }

            nodeMap.get(key).usages.push({
                flowIndex,
                config: flow.config,
                detail,
            });
            if (sequence.at(-1) !== key) sequence.push(key);
        });

        sequence.push(endKey);
        sequence.slice(0, -1).forEach((from, index) => {
            const to = sequence[index + 1];
            const key = `${from}->${to}`;

            if (!edgeMap.has(key)) {
                edgeMap.set(key, { key, from, to, flowIndexes: new Set() });
            }
            edgeMap.get(key).flowIndexes.add(flowIndex);
        });
    });

    const nodes = [...nodeMap.values()];
    const edges = [...edgeMap.values()];
    const adjacency = new Map(nodes.map((node) => [node.key, []]));
    const indegree = new Map(nodes.map((node) => [node.key, 0]));

    edges.forEach((edge) => {
        adjacency.get(edge.from)?.push(edge.to);
        indegree.set(edge.to, (indegree.get(edge.to) || 0) + 1);
    });

    const levels = new Map();
    const queue = nodes
        .filter((node) => indegree.get(node.key) === 0)
        .map((node) => node.key);
    let processed = 0;

    while (queue.length) {
        const current = queue.shift();
        processed += 1;
        const currentLevel = levels.get(current) || 0;

        (adjacency.get(current) || []).forEach((target) => {
            levels.set(target, Math.max(levels.get(target) || 0, currentLevel + 1));
            indegree.set(target, indegree.get(target) - 1);
            if (indegree.get(target) === 0) queue.push(target);
        });
    }

    // A circular approval route should not occur, but keep every node visible if its data is malformed.
    if (processed !== nodes.length) {
        nodes.forEach((node) => {
            if (levels.has(node.key)) return;
            const orders = node.usages.map((usage) => Number(usage.detail.Approval_Order) || 1);
            levels.set(node.key, orders.length ? Math.min(...orders) : 0);
        });
    }

    const columns = new Map();
    nodes.forEach((node) => {
        const level = levels.get(node.key) || 0;
        if (!columns.has(level)) columns.set(level, []);
        columns.get(level).push(node);
    });

    columns.forEach((column) => {
        column.sort((a, b) => {
            const usageDifference = b.usages.length - a.usages.length;
            return usageDifference || a.label.localeCompare(b.label);
        });
    });

    const maxLevel = Math.max(...levels.values(), 1);
    const canvasWidth = (maxLevel + 1) * NODE_WIDTH + maxLevel * COLUMN_GAP + CANVAS_PADDING * 2;
    const columnHeights = [...columns.values()].map((column) => column.reduce(
        (height, node, index) => height + nodeHeight(node) + (index ? ROW_GAP : 0),
        0,
    ));
    const canvasHeight = Math.max(...columnHeights, 1) + CANVAS_PADDING * 2;
    const positionedNodes = [];
    const positionMap = new Map();

    [...columns.entries()].sort(([a], [b]) => a - b).forEach(([level, column]) => {
        const columnHeight = column.reduce(
            (height, node, index) => height + nodeHeight(node) + (index ? ROW_GAP : 0),
            0,
        );
        const yOffset = (canvasHeight - columnHeight) / 2;
        let currentY = yOffset;

        column.forEach((node) => {
            const usages = node.usages || [];
            const flowCount = new Set(usages.map((usage) => usage.flowIndex)).size;
            const height = nodeHeight(node);
            const positioned = {
                ...node,
                x: CANVAS_PADDING + level * (NODE_WIDTH + COLUMN_GAP),
                y: currentY,
                height,
                flowCount,
                shared: flowCount > 1,
                editable: props.canEdit && usages.some((usage) => Boolean(usage.detail.editable)),
                inspectable: usages.some((usage) => usage.detail.Source_Type === 'ROLE'),
                selected: usages.some((usage) => String(usage.detail.Id) === String(props.selectedStepId)),
            };
            positionedNodes.push(positioned);
            positionMap.set(node.key, positioned);
            currentY += height + ROW_GAP;
        });
    });

    const positionedEdges = edges.map((edge) => {
        const from = positionMap.get(edge.from);
        const to = positionMap.get(edge.to);
        return {
            ...edge,
            flowCount: edge.flowIndexes.size,
            touchesShared: Boolean(from.shared || to.shared),
            sx: from.x + NODE_WIDTH,
            sy: from.y + from.height / 2,
            tx: to.x,
            ty: to.y + to.height / 2,
        };
    });

    return {
        nodes: positionedNodes,
        edges: positionedEdges,
        width: canvasWidth,
        height: canvasHeight,
    };
});

const sharedNodeCount = computed(() => graphLayout.value.nodes.filter((node) => node.type === 'step' && node.shared).length);

const fetchFlows = async () => {
    loading.value = true;
    try {
        flows.value = props.configs.map((config) => {
            const details = (config.flow_details || [])
                .filter((detail) => detail.Flag_Aktif === 'Y')
                .sort((a, b) => Number(a.Approval_Order) - Number(b.Approval_Order));

            return { config, details };
        });
    } finally {
        loading.value = false;
        await nextTick();
        fitGraph();
    }
};

const nodeStyle = (node) => ({
    left: `${node.x}px`,
    top: `${node.y}px`,
    width: `${NODE_WIDTH}px`,
    height: `${node.height}px`,
});

const edgePath = (edge) => {
    const distance = Math.max((edge.tx - edge.sx) * 0.52, 44);
    return `M ${edge.sx} ${edge.sy} C ${edge.sx + distance} ${edge.sy}, ${edge.tx - distance} ${edge.ty}, ${edge.tx} ${edge.ty}`;
};

const nodeTitle = (node) => node.usages
    .map((usage) => `${configName(usage.config, 'Fitur')} - posisi ${usage.detail.Approval_Order}: ${stepName(usage.detail)}\nSource: ${sourceLabel(usage.detail)}\nAtribut: ${attributeLabel(usage.detail)}${node.editable ? '\nKlik untuk edit assignment' : node.inspectable ? '\nKlik untuk melihat anggota role' : '\nHanya dapat dilihat'}`)
    .join('\n');

const manageNode = (node) => {
    if (node.inspectable) {
        selectedRoleDetail.value = node.usages[0]?.detail || null;
        roleModalOpen.value = Boolean(selectedRoleDetail.value);
        return;
    }
    if (!node.editable) return;
    const usage = node.usages?.find((item) => item.detail.editable);
    if (usage) emit('manage-step', usage);
};

const prepareNode = (node) => {
    if (node.inspectable) {
        selectedRoleDetail.value = node.usages[0]?.detail || null;
    }
};

const closeRoleModal = () => {
    roleModalOpen.value = false;
    selectedRoleDetail.value = null;
};

const canvasTransform = computed(() => ({
    width: `${graphLayout.value.width}px`,
    height: `${graphLayout.value.height}px`,
    transform: `translate(${view.value.x}px, ${view.value.y}px) scale(${view.value.zoom})`,
}));

const clampZoom = (zoom) => Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom));

const zoomAt = (nextZoom, clientX, clientY) => {
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
};

const zoomBy = (factor) => {
    const bounds = viewport.value?.getBoundingClientRect();
    if (!bounds) return;
    zoomAt(view.value.zoom * factor, bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
};

const fitGraph = () => {
    const bounds = viewport.value?.getBoundingClientRect();
    if (!bounds || !graphLayout.value.width || !graphLayout.value.height) return;
    const zoom = clampZoom(Math.min(
        1,
        (bounds.width - 56) / graphLayout.value.width,
        (bounds.height - 56) / graphLayout.value.height,
    ));
    view.value = {
        zoom,
        x: (bounds.width - graphLayout.value.width * zoom) / 2,
        y: (bounds.height - graphLayout.value.height * zoom) / 2,
    };
};

const onWheel = (event) => {
    event.preventDefault();
    if (event.ctrlKey || event.metaKey) {
        zoomAt(view.value.zoom * Math.exp(-event.deltaY * 0.008), event.clientX, event.clientY);
        return;
    }
    view.value = {
        ...view.value,
        x: view.value.x - event.deltaX,
        y: view.value.y - event.deltaY,
    };
};

const pointerDistance = () => {
    const [a, b] = [...pointers.values()];
    return Math.hypot(a.x - b.x, a.y - b.y);
};

const pointerMidpoint = () => {
    const [a, b] = [...pointers.values()];
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
};

const onPointerDown = (event) => {
    if (event.target.closest('.workflow-node, .canvas-controls')) return;
    viewport.value?.setPointerCapture?.(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (pointers.size === 1) {
        panOrigin = { pointerX: event.clientX, pointerY: event.clientY, x: view.value.x, y: view.value.y };
    } else if (pointers.size === 2) {
        pinchOrigin = { distance: pointerDistance(), zoom: view.value.zoom };
    }
};

const onPointerMove = (event) => {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (pointers.size === 2 && pinchOrigin) {
        const midpoint = pointerMidpoint();
        zoomAt(pinchOrigin.zoom * (pointerDistance() / pinchOrigin.distance), midpoint.x, midpoint.y);
    } else if (pointers.size === 1 && panOrigin) {
        view.value = {
            ...view.value,
            x: panOrigin.x + event.clientX - panOrigin.pointerX,
            y: panOrigin.y + event.clientY - panOrigin.pointerY,
        };
    }
};

const onPointerUp = (event) => {
    pointers.delete(event.pointerId);
    if (pointers.size === 1) {
        const pointer = [...pointers.values()][0];
        panOrigin = { pointerX: pointer.x, pointerY: pointer.y, x: view.value.x, y: view.value.y };
    } else {
        panOrigin = null;
    }
    pinchOrigin = null;
};

watch(() => props.configs, fetchFlows);
onMounted(fetchFlows);
</script>

<template>
    <section class="flow-panel">
        <div class="flow-header">
            <div>
                <div class="flow-eyebrow">Peta alur approval</div>
                <h3>Alur approval per config</h3>
                <p>
                    Geser canvas dengan drag atau dua jari. Pinch atau Ctrl + scroll untuk zoom.
                    Jalur hanya bertemu saat memakai tabel assignment yang sama.
                </p>
            </div>
            <div class="flow-summary">
                <span>{{ flows.length }} alur fitur</span>
                <span>{{ totalPositions }} posisi</span>
                <span v-if="sharedNodeCount">{{ sharedNodeCount }} titik bersama</span>
            </div>
        </div>

        <div v-if="loading" class="flow-state">
            Memuat peta alur approval...
        </div>

        <div v-else-if="!flows.length" class="flow-state">
            Belum ada alur approval yang dapat ditampilkan.
        </div>

        <div
            v-else
            ref="viewport"
            class="workflow-viewport"
            @wheel="onWheel"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
        >
            <div class="canvas-controls">
                <button type="button" title="Zoom out" @click="zoomBy(0.85)">−</button>
                <span>{{ Math.round(view.zoom * 100) }}%</span>
                <button type="button" title="Zoom in" @click="zoomBy(1.18)">+</button>
                <button type="button" class="fit-button" title="Tampilkan seluruh alur" @click="fitGraph">Fit</button>
            </div>
            <div
                class="workflow-canvas"
                :style="canvasTransform"
            >
                <svg
                    class="workflow-edges"
                    :width="graphLayout.width"
                    :height="graphLayout.height"
                    :viewBox="`0 0 ${graphLayout.width} ${graphLayout.height}`"
                    aria-hidden="true"
                >
                    <defs>
                        <marker id="approval-flow-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" />
                        </marker>
                        <marker id="approval-flow-arrow-shared" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" />
                        </marker>
                    </defs>
                    <path
                        v-for="edge in graphLayout.edges"
                        :key="edge.key"
                        :d="edgePath(edge)"
                        class="workflow-edge"
                        :class="{ 'workflow-edge-shared': edge.touchesShared || edge.flowCount > 1 }"
                        :marker-end="edge.touchesShared || edge.flowCount > 1 ? 'url(#approval-flow-arrow-shared)' : 'url(#approval-flow-arrow)'"
                    />
                </svg>

                <template v-for="node in graphLayout.nodes" :key="node.key">
                    <div
                        v-if="node.type !== 'step'"
                        class="workflow-terminal"
                        :class="`workflow-terminal-${node.type}`"
                        :style="nodeStyle(node)"
                    >
                        <span class="terminal-icon">{{ node.type === 'start' ? '1' : '✓' }}</span>
                        <span class="terminal-copy">
                            <small>{{ node.caption }}</small>
                            <strong>{{ node.label }}</strong>
                        </span>
                    </div>

                    <button
                        v-else
                        type="button"
                        class="workflow-node"
                        :class="{
                            'workflow-node-shared': node.shared,
                            'workflow-node-selected': node.selected,
                            'workflow-node-editable': node.editable,
                            'workflow-node-inspectable': node.inspectable,
                            'workflow-node-readonly': !node.editable,
                        }"
                        :style="nodeStyle(node)"
                        :title="nodeTitle(node)"
                        :aria-disabled="!node.editable && !node.inspectable"
                        @mouseenter="prepareNode(node)"
                        @click="manageNode(node)"
                    >
                        <span class="node-port node-port-input" />
                        <span class="node-port node-port-output" />
                        <span class="node-order">
                            {{ node.shared ? `${node.flowCount} alur terhubung` : `Posisi ${node.usages[0].detail.Approval_Order}` }}
                        </span>
                        <strong>{{ node.label }}</strong>
                        <span class="node-source" :title="sourceLabel(node.usages[0].detail)">
                            {{ sourceLabel(node.usages[0].detail) }}
                        </span>
                        <small :title="attributeLabel(node.usages[0].detail)">{{ attributeLabel(node.usages[0].detail) }}</small>
                        <span class="node-action">
                            {{ node.editable ? 'Klik untuk edit assignment' : node.inspectable ? 'Lihat anggota role' : 'Hanya dapat dilihat' }}
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <RoleMembersModal
            :show="roleModalOpen"
            :detail="selectedRoleDetail"
            :grupId="grupId"
            @close="closeRoleModal"
        />
    </section>
</template>

<style scoped>
.flow-panel {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
}

.flow-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--hcis-slate-200);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, #eff6ff 100%);
}

.flow-eyebrow {
    margin-bottom: 5px;
    color: var(--hcis-blue-600);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.flow-header h3 {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 15px;
}

.flow-header p {
    max-width: 760px;
    margin: 4px 0 0;
    color: var(--hcis-slate-500);
    font-size: 11px;
    line-height: 1.55;
}

.flow-summary {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    min-width: 210px;
}

.flow-summary span {
    padding: 6px 10px;
    border: 1px solid #dbeafe;
    border-radius: 999px;
    background: #fff;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.flow-state {
    padding: 42px 24px;
    color: var(--hcis-slate-500);
    font-size: 13px;
    text-align: center;
}

.workflow-viewport {
    position: relative;
    height: min(62vh, 600px);
    min-height: 430px;
    overflow: hidden;
    background-color: var(--hcis-slate-50);
    background-image: radial-gradient(var(--hcis-slate-300) 1px, transparent 1px);
    background-size: 18px 18px;
    cursor: grab;
    touch-action: none;
    user-select: none;
}

.workflow-viewport:active {
    cursor: grabbing;
}

.canvas-controls {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border: 1px solid #dbe3ee;
    border-radius: 9px;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 5px 16px rgba(15, 23, 42, .12);
}

.canvas-controls button {
    display: grid;
    width: 28px;
    height: 28px;
    padding: 0;
    place-items: center;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--hcis-slate-700);
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
}

.canvas-controls button:hover {
    background: #eff6ff;
    color: var(--hcis-blue-600);
}

.canvas-controls span {
    min-width: 42px;
    color: var(--hcis-slate-500);
    font-size: 10px;
    font-weight: 700;
    text-align: center;
}

.canvas-controls .fit-button {
    width: auto;
    padding: 0 8px;
    font-size: 10px;
}

.workflow-canvas {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    will-change: transform;
}

.workflow-edges {
    position: absolute;
    inset: 0;
    overflow: visible;
    pointer-events: none;
}

.workflow-edge {
    fill: none;
    stroke: var(--hcis-slate-400);
    stroke-width: 2;
    marker-end: url(#approval-flow-arrow);
}

.workflow-edge-shared {
    stroke: var(--hcis-amber-500);
    stroke-width: 2.6;
    stroke-dasharray: 7 5;
    animation: move-line 1.2s linear infinite;
}

#approval-flow-arrow path {
    fill: var(--hcis-slate-500);
}

#approval-flow-arrow-shared path {
    fill: var(--hcis-amber-600);
}

.workflow-node,
.workflow-terminal {
    position: absolute;
    z-index: 2;
    box-sizing: border-box;
}

.workflow-node {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 3px;
    padding: 8px 11px;
    border: 1.5px solid #bfdbfe;
    border-radius: 9px;
    background: #fff;
    color: var(--hcis-slate-900);
    text-align: left;
    box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
    cursor: default;
    transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
}

.workflow-node-editable,
.workflow-node-inspectable {
    cursor: pointer;
}

.workflow-node-editable:hover,
.workflow-node-inspectable:hover {
    border-color: var(--hcis-blue-500);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .13), 0 8px 18px rgba(37, 99, 235, .16);
    transform: translateY(-1px);
}

.workflow-node-readonly {
    opacity: .82;
}

.workflow-node-inspectable {
    border-color: #c4b5fd;
    background: linear-gradient(145deg, #fff 0%, var(--hcis-violet-50) 100%);
    opacity: 1;
}

.workflow-node-inspectable .node-port {
    background: #8b5cf6;
    box-shadow: 0 0 0 1px #c4b5fd;
}

.workflow-node-inspectable .node-order {
    color: var(--hcis-violet-600);
}

.workflow-node-inspectable .node-action {
    background: var(--hcis-violet-600);
}

.workflow-node-shared {
    border-color: var(--hcis-amber-500);
    background: linear-gradient(145deg, #fffdf5 0%, var(--hcis-amber-50) 100%);
}

.workflow-node-selected {
    border-color: var(--hcis-blue-600);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .15), 0 12px 24px rgba(37, 99, 235, .15);
}

.node-order {
    color: var(--hcis-blue-600);
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.workflow-node-shared .node-order {
    color: var(--hcis-amber-700);
}

.workflow-node strong {
    width: 100%;
    font-size: 11px;
    line-height: 1.2;
    overflow-wrap: anywhere;
    white-space: normal;
}

.workflow-node small {
    width: 100%;
    color: var(--hcis-slate-500);
    font-size: 8px;
    line-height: 1.25;
    overflow-wrap: anywhere;
    white-space: normal;
}

.node-source {
    width: 100%;
    color: var(--hcis-slate-600);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    font-weight: 700;
    line-height: 1.2;
    overflow-wrap: anywhere;
    white-space: normal;
}

.node-action {
    position: absolute;
    top: calc(100% + 5px);
    left: 50%;
    z-index: 5;
    padding: 4px 7px;
    border-radius: 6px;
    background: #1d4ed8;
    color: #fff;
    font-size: 8px;
    font-weight: 700;
    opacity: 0;
    pointer-events: none;
    transform: translate(-50%, -3px);
    transition: opacity .14s ease, transform .14s ease;
    white-space: nowrap;
}

.workflow-node-editable:hover .node-action,
.workflow-node-inspectable:hover .node-action {
    opacity: 1;
    transform: translate(-50%, 0);
}

.workflow-node-readonly .node-action {
    background: var(--hcis-slate-500);
}

.node-port {
    position: absolute;
    top: 50%;
    width: 9px;
    height: 9px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--hcis-blue-500);
    box-shadow: 0 0 0 1px #93c5fd;
    transform: translateY(-50%);
}

.workflow-node-shared .node-port {
    background: var(--hcis-amber-500);
    box-shadow: 0 0 0 1px var(--hcis-amber-400);
}

.node-port-input {
    left: -5px;
}

.node-port-output {
    right: -5px;
}

.workflow-terminal {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 7px;
    padding: 8px 10px;
    border: 1.5px solid var(--hcis-slate-300);
    border-radius: 12px;
    background: #fff;
    color: var(--hcis-slate-700);
    box-shadow: 0 6px 16px rgba(15, 23, 42, .07);
}

.workflow-terminal-end {
    border-color: #86efac;
    background: var(--hcis-green-50);
    color: #166534;
}

.terminal-icon {
    display: grid;
    width: 21px;
    height: 21px;
    place-items: center;
    border-radius: 50%;
    background: var(--hcis-slate-200);
    font-size: 11px;
    font-weight: 800;
}

.workflow-terminal-end .terminal-icon {
    background: #22c55e;
    color: #fff;
}

.workflow-terminal strong {
    display: block;
    font-size: 10px;
    line-height: 1.2;
    overflow-wrap: anywhere;
    white-space: normal;
}

.terminal-copy {
    flex: 1;
    min-width: 0;
}

.terminal-copy small {
    display: block;
    margin-bottom: 2px;
    color: var(--hcis-slate-500);
    font-size: 7px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

@keyframes move-line {
    to {
        stroke-dashoffset: -12;
    }
}

@media (max-width: 768px) {
    .flow-header {
        flex-direction: column;
    }

    .flow-summary {
        justify-content: flex-start;
        min-width: 0;
    }
}
</style>
