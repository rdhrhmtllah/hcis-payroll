<template>
    <HcisDialog
        :model-value="show"
        title="Detail Proses APQC"
        kicker="Tata Kelola · Kerangka Proses PCF"
        icon="bounding-box"
        size="md"
        sentiment="brand"
        glow
        pattern="grid"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div v-if="item" class="sop-apqc-detail-body">
            <!-- 1. Hero Identity Banner -->
            <div class="apqc-detail-hero" :style="{ '--apqc-accent': processColor }">
                <div class="apqc-detail-hero__jewel">
                    <span class="apqc-detail-hero__jewel-lbl">PROSES</span>
                    <span class="apqc-detail-hero__jewel-num">{{ item.Kode_Proses }}</span>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <h2 class="apqc-detail-hero__title" :title="item.Nama_Proses">
                        {{ item.Nama_Proses }}
                    </h2>
                    <div class="d-flex flex-wrap align-items-center gap-1.5 mt-1.5">
                        <HcisBadge
                            :label="isOperating ? 'Domain: Operasional (Core)' : 'Domain: Manajemen & Support'"
                            :variant="isOperating ? 'brand' : 'purple'"
                            appearance="subtle"
                            size="xs"
                            :leading-icon="isOperating ? 'gear-fill' : 'building-fill'"
                        />
                        <HcisBadge
                            :label="`${item.Jumlah_Dokumen || 0} Dokumen Terdaftar`"
                            :variant="(item.Jumlah_Dokumen || 0) > 0 ? 'success' : 'neutral'"
                            appearance="subtle"
                            size="xs"
                            :leading-icon="(item.Jumlah_Dokumen || 0) > 0 ? 'file-earmark-check-fill' : 'file-earmark'"
                        />
                    </div>
                </div>
            </div>

            <!-- 2. Deskripsi & Ruang Lingkup -->
            <div class="apqc-detail-section mb-3">
                <div class="apqc-detail-section__header">
                    <i class="bi bi-card-text text-primary me-1.5"></i>
                    <span>Ruang Lingkup &amp; Definisi Proses Bisnis</span>
                </div>
                <div class="apqc-detail-desc-box">
                    <p v-if="item.Deskripsi && item.Deskripsi.trim().length > 0" class="apqc-detail-desc-text">
                        {{ item.Deskripsi }}
                    </p>
                    <p v-else class="apqc-detail-desc-empty">
                        <i class="bi bi-info-circle me-1"></i>
                        Tidak ada penjelasan deskripsi untuk kategori proses APQC ini.
                    </p>
                </div>
            </div>

            <!-- 3. Tipe Item Sah yang Diizinkan -->
            <div class="apqc-detail-section mb-3">
                <div class="apqc-detail-section__header">
                    <i class="bi bi-check2-all text-primary me-1.5"></i>
                    <span>Tipe Item Sah yang Diizinkan</span>
                </div>
                <div class="apqc-detail-content-box">
                    <div v-if="item.Tipe_Item && item.Tipe_Item.length > 0" class="d-flex flex-wrap gap-1.5">
                        <HcisBadge
                            v-for="tipe in item.Tipe_Item"
                            :key="tipe"
                            :label="tipe"
                            variant="neutral"
                            appearance="subtle"
                            size="sm"
                            leading-icon="file-earmark-code"
                        />
                    </div>
                    <span v-else class="text-danger small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Belum ada tipe item sah yang dikaitkan pada proses ini.
                    </span>
                </div>
            </div>

            <!-- 4. Informasi Teknis & Audit -->
            <div class="apqc-detail-section mb-2">
                <div class="apqc-detail-section__header">
                    <i class="bi bi-clock-history text-primary me-1.5"></i>
                    <span>Informasi Teknis &amp; Audit</span>
                </div>
                <div class="apqc-detail-meta-grid">
                    <div class="apqc-detail-meta-item">
                        <span class="apqc-detail-meta-label">Nomor / Kode APQC</span>
                        <span class="apqc-detail-meta-value font-monospace fw-bold text-dark">
                            PCF #{{ item.Kode_Proses || '-' }}
                        </span>
                    </div>

                    <div class="apqc-detail-meta-item">
                        <span class="apqc-detail-meta-label">Urutan Tampilan</span>
                        <span class="apqc-detail-meta-value">Urutan #{{ item.Urutan ?? '-' }}</span>
                    </div>

                    <div class="apqc-detail-meta-item">
                        <span class="apqc-detail-meta-label">Dibuat Pada</span>
                        <span class="apqc-detail-meta-value">{{ formatTimestamp(item.Created_At) }}</span>
                    </div>

                    <div class="apqc-detail-meta-item">
                        <span class="apqc-detail-meta-label">Pembaruan Terakhir</span>
                        <span class="apqc-detail-meta-value">{{ formatTimestamp(item.Updated_At) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="d-flex justify-content-between align-items-center w-100">
                <HcisButton
                    variant="danger-quiet"
                    size="md"
                    leading-icon="slash-circle"
                    label="Nonaktifkan"
                    :disabled="(item?.Jumlah_Dokumen || 0) > 0"
                    :title="(item?.Jumlah_Dokumen || 0) > 0 ? `Masih dipakai ${item.Jumlah_Dokumen} dokumen` : 'Nonaktifkan proses ini'"
                    @click="onDeleteClick"
                />

                <div class="d-flex align-items-center gap-2">
                    <HcisButton variant="secondary" size="md" @click="close">
                        Tutup
                    </HcisButton>
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="pencil-square"
                        label="Ubah Proses"
                        @click="onEditClick"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed } from 'vue';
import { HcisDialog, HcisButton, HcisBadge } from '@/components/ui';

defineOptions({ name: 'SopApqcDetailDialog' });

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    item: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'edit', 'delete']);

const close = () => {
    emit('close');
};

const onDialogUpdate = (val) => {
    if (!val) close();
};

const onEditClick = () => {
    emit('edit', props.item);
};

const onDeleteClick = () => {
    emit('delete', props.item);
};

const processNum = computed(() => {
    const raw = String(props.item?.Kode_Proses || '').replace(/\D/g, '');
    return parseInt(raw, 10) || 0;
});

const isOperating = computed(() => {
    return processNum.value >= 1 && processNum.value <= 6;
});

const processColor = computed(() => {
    const colors = {
        1: '#2563eb',
        2: '#0284c7',
        3: '#0ea5e9',
        4: '#06b6d4',
        5: '#0d9488',
        6: '#10b981',
        7: '#8b5cf6',
        8: '#6366f1',
        9: '#f59e0b',
        10: '#d97706',
        11: '#dc2626',
        12: '#ec4899',
        13: '#7c3aed',
    };
    return colors[processNum.value] || '#0284c7';
});

const formatTimestamp = (raw) => {
    if (!raw) return '—';
    try {
        const d = new Date(raw);
        if (isNaN(d.getTime())) return String(raw);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(raw);
    }
};
</script>

<style scoped>
.sop-apqc-detail-body {
    padding: 0.25rem 0;
}

/* Hero Banner */
.apqc-detail-hero {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, color-mix(in srgb, var(--apqc-accent, var(--hcis-sky-600)) 8%, #fff) 0%, color-mix(in srgb, var(--apqc-accent, var(--hcis-sky-600)) 4%, #fff) 100%);
    border: 1px solid color-mix(in srgb, var(--apqc-accent, var(--hcis-sky-600)) 25%, transparent);
    box-shadow: 0 2px 8px -2px color-mix(in srgb, var(--apqc-accent, var(--hcis-sky-600)) 12%, transparent);
    margin-bottom: 1.15rem;
    overflow: hidden;
}

.apqc-detail-hero__jewel {
    width: 48px;
    height: 48px;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--apqc-accent, var(--hcis-sky-600)) 0%, color-mix(in srgb, var(--apqc-accent, var(--hcis-sky-600)) 80%, #000) 100%);
    color: #fff;
    box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.2);
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.apqc-detail-hero__jewel-lbl {
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    opacity: 0.85;
    line-height: 1;
}

.apqc-detail-hero__jewel-num {
    font-size: 1.15rem;
    font-weight: 850;
    line-height: 1.1;
    font-family: var(--hcis-font-mono, monospace);
}

.apqc-detail-hero__title {
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.35;
    margin: 0;
    word-break: break-word;
    overflow-wrap: anywhere;
}

/* Sections */
.apqc-detail-section {
    width: 100%;
}

.apqc-detail-section__header {
    display: flex;
    align-items: center;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin-bottom: 0.45rem;
    letter-spacing: 0.01em;
}

.apqc-detail-desc-box,
.apqc-detail-content-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.85rem 1rem;
}

.apqc-detail-desc-text {
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin: 0;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.apqc-detail-desc-empty {
    font-size: 0.825rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-style: italic;
    margin: 0;
}

/* Metadata Grid */
.apqc-detail-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
}

@media (max-width: 576px) {
    .apqc-detail-meta-grid {
        grid-template-columns: 1fr;
    }
}

.apqc-detail-meta-item {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.65rem 0.85rem;
    display: flex;
    flex-direction: column;
}

.apqc-detail-meta-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.apqc-detail-meta-value {
    font-size: 0.835rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    margin-top: 0.15rem;
    word-break: break-word;
}
</style>
