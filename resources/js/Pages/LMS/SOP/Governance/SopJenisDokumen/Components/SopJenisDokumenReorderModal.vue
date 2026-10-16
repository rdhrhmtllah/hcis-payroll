<template>
    <HcisDialog
        :model-value="show"
        title="Susun Urutan Hirarki Mutu"
        kicker="Metode Penyusunan Piramid"
        icon="arrow-down-up"
        size="md"
        sentiment="brand"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div class="sop-reorder-body">
            <!-- Executive Banner Info -->
            <div class="reorder-info-banner mb-3">
                <div class="d-flex align-items-start gap-2.5">
                    <i class="bi bi-info-circle-fill text-primary mt-0.5"></i>
                    <div class="small">
                        <span class="fw-semibold text-dark">Penyusunan Sekuensial:</span>
                        Gunakan tombol panah atau seret untuk menata posisi lapis hirarki.
                        Sistem akan menyelaraskan nomor tingkat (<strong>L1, L2, L3...</strong>) secara otomatis dan rapi tanpa duplikat.
                    </div>
                </div>
            </div>

            <!-- Apex Indicator -->
            <div class="pyramid-edge-label top mb-2">
                <i class="bi bi-shield-fill-check text-primary me-1"></i>
                PUNCAK PIRAMID (LEVEL 1 / KEBIJAKAN STRATEGIS)
            </div>

            <!-- Reorderable List Container -->
            <div class="reorder-list-container">
                <div
                    v-for="(item, index) in items"
                    :key="item.Id_SOP_Jenis_Dokumen || index"
                    class="reorder-item-row"
                    :class="{
                        'is-dragging': draggedIndex === index,
                        'is-dragover': dragOverIndex === index,
                    }"
                    draggable="true"
                    @dragstart="handleDragStart(index, $event)"
                    @dragover.prevent="handleDragOver(index, $event)"
                    @dragleave="handleDragLeave(index)"
                    @drop="handleDrop(index, $event)"
                    @dragend="handleDragEnd"
                >
                    <!-- Drag Handle Grip -->
                    <div class="reorder-grip" title="Seret untuk memindahkan posisi">
                        <i class="bi bi-grip-vertical"></i>
                    </div>

                    <!-- Dynamic Level Badge -->
                    <div class="reorder-level-badge" :style="{ '--jc': getLevelColor(index + 1) }">
                        L{{ index + 1 }}
                    </div>

                    <!-- Main Info -->
                    <div class="reorder-info min-w-0 flex-grow-1">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="reorder-nama fw-bold text-dark text-truncate">
                                {{ item.Nama_Jenis }}
                            </span>
                            <span v-if="item.Kode_Jenis_Dokumen" class="reorder-prefix-badge">
                                #{{ item.Kode_Jenis_Dokumen }}
                            </span>
                        </div>
                        <div class="reorder-meta small text-muted font-monospace">
                            {{ item.Kode_Jenis }} · {{ item.Jumlah_Dokumen || 0 }} dokumen
                        </div>
                    </div>

                    <!-- Move Up / Down Quick Buttons -->
                    <div class="reorder-actions d-flex align-items-center gap-1">
                        <button
                            type="button"
                            class="reorder-btn"
                            :disabled="index === 0 || saving"
                            title="Pindah ke Atas"
                            aria-label="Pindah ke Atas"
                            @click="moveItem(index, index - 1)"
                        >
                            <i class="bi bi-arrow-up"></i>
                        </button>
                        <button
                            type="button"
                            class="reorder-btn"
                            :disabled="index === items.length - 1 || saving"
                            title="Pindah ke Bawah"
                            aria-label="Pindah ke Bawah"
                            @click="moveItem(index, index + 1)"
                        >
                            <i class="bi bi-arrow-down"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Base Indicator -->
            <div class="pyramid-edge-label bottom mt-2">
                <i class="bi bi-layers-fill text-success me-1"></i>
                DASAR PIRAMID (FORMULIR &amp; REKAMAN OPERASIONAL)
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-between align-items-center w-100">
                <span class="text-muted small">
                    {{ items.length }} Tingkat Terdaftar
                </span>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton variant="secondary" size="md" :disabled="saving" label="Batal" @click="close" />
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="check-lg"
                        :loading="saving"
                        label="Simpan Susunan Urutan"
                        @click="submit"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { HcisDialog, HcisButton } from '@/components/ui';

defineOptions({ name: 'SopJenisDokumenReorderModal' });

const props = defineProps({
    show: { type: Boolean, default: false },
    list: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const items = ref([]);
const draggedIndex = ref(null);
const dragOverIndex = ref(null);

watch(
    () => [props.show, props.list],
    () => {
        if (!props.show) return;
        items.value = [...(props.list || [])].map((item) => ({ ...item }));
    },
    { immediate: true },
);

function getLevelColor(level) {
    const colors = {
        1: '#2563eb',
        2: '#3b82f6',
        3: '#0ea5e9',
        4: '#06b6d4',
        5: '#14b8a6',
        6: '#10b981',
        7: '#6366f1',
    };
    return colors[level] || '#64748b';
}

function moveItem(fromIdx, toIdx) {
    if (toIdx < 0 || toIdx >= items.value.length) return;
    const item = items.value.splice(fromIdx, 1)[0];
    items.value.splice(toIdx, 0, item);
}

function handleDragStart(index, event) {
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function handleDragOver(index) {
    dragOverIndex.value = index;
}

function handleDragLeave(index) {
    if (dragOverIndex.value === index) {
        dragOverIndex.value = null;
    }
}

function handleDrop(index) {
    if (draggedIndex.value !== null && draggedIndex.value !== index) {
        moveItem(draggedIndex.value, index);
    }
    draggedIndex.value = null;
    dragOverIndex.value = null;
}

function handleDragEnd() {
    draggedIndex.value = null;
    dragOverIndex.value = null;
}

function close() {
    emit('close');
}

function onDialogUpdate(v) {
    if (!v) close();
}

function submit() {
    const ids = items.value.map((i) => i.Id_SOP_Jenis_Dokumen);
    emit('submit', ids);
}
</script>

<style scoped>
.sop-reorder-body {
    padding: 0.25rem 0;
}

.reorder-info-banner {
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-surface-muted, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
}

.pyramid-edge-label {
    display: flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    padding: 0.25rem 0.5rem;
}

.pyramid-edge-label.top {
    color: #1d4ed8;
}

.pyramid-edge-label.bottom {
    color: var(--hcis-emerald-600);
}

.reorder-list-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 52vh;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.reorder-item-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    cursor: grab;
    user-select: none;
    transition: all 0.15s ease;
}

.reorder-item-row:hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
}

.reorder-item-row.is-dragging {
    opacity: 0.4;
    background: #eff6ff;
    border-style: dashed;
    border-color: var(--hcis-blue-500);
}

.reorder-item-row.is-dragover {
    border-color: var(--hcis-blue-600);
    background: #eff6ff;
    transform: scale(1.01);
}

.reorder-grip {
    flex: 0 0 auto;
    color: var(--hcis-slate-400);
    font-size: 1.1rem;
    cursor: grab;
    display: flex;
    align-items: center;
}

.reorder-level-badge {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--jc, var(--hcis-blue-600)) 0%, color-mix(in srgb, var(--jc, var(--hcis-blue-600)) 80%, #000) 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

.reorder-prefix-badge {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    border-radius: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-white);
    background: var(--hcis-slate-700);
}

.reorder-meta {
    font-size: 0.72rem;
    line-height: 1.2;
}

.reorder-actions {
    flex: 0 0 auto;
}

.reorder-btn {
    display: inline-grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.375rem;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.12s ease;
}

.reorder-btn:hover:not(:disabled) {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-300);
}

.reorder-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
