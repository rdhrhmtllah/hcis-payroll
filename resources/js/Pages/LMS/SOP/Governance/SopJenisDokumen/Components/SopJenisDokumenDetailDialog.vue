<template>
    <HcisDialog
        :model-value="show"
        title="Detail Jenis Dokumen"
        kicker="Tata Kelola · Hirarki Mutu"
        icon="diagram-3-fill"
        size="md"
        sentiment="brand"
        glow
        pattern="grid"
        header-divider
        sticky-footer
        @update:model-value="onDialogUpdate"
    >
        <div v-if="item" class="sop-jd-detail-body">
            <!-- 1. Hero Identity Banner -->
            <div class="jd-detail-hero">
                <div class="jd-detail-hero__level-box">
                    <span class="jd-detail-hero__level-label">TINGKAT</span>
                    <span class="jd-detail-hero__level-num">L{{ item.Level_Hirarki }}</span>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <h2 class="jd-detail-hero__title" :title="item.Nama_Jenis">
                        {{ item.Nama_Jenis }}
                    </h2>
                    <div class="d-flex flex-wrap align-items-center gap-1.5 mt-1.5">
                        <HcisBadge
                            v-if="item.Kode_Jenis_Dokumen"
                            :label="`Prefix Cetak: ${item.Kode_Jenis_Dokumen}`"
                            variant="brand"
                            appearance="subtle"
                            size="xs"
                            leading-icon="hash"
                        />
                        <HcisBadge
                            v-else
                            label="Belum Ada Kode Cetak"
                            variant="warning"
                            appearance="subtle"
                            size="xs"
                            leading-icon="exclamation-circle"
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

            <!-- 2. Deskripsi & Peran Hirarki -->
            <div class="jd-detail-section mb-3">
                <div class="jd-detail-section__header">
                    <i class="bi bi-card-text text-primary me-1.5"></i>
                    <span>Peran &amp; Ruang Lingkup Mutu</span>
                </div>
                <div class="jd-detail-desc-box">
                    <p v-if="item.Deskripsi && item.Deskripsi.trim().length > 0" class="jd-detail-desc-text">
                        {{ item.Deskripsi }}
                    </p>
                    <p v-else class="jd-detail-desc-empty">
                        <i class="bi bi-info-circle me-1"></i>
                        Tidak ada penjelasan deskripsi untuk jenis dokumen ini.
                    </p>
                </div>
            </div>

            <!-- 3. Tipe Item Sah yang Diizinkan -->
            <div class="jd-detail-section mb-3">
                <div class="jd-detail-section__header">
                    <i class="bi bi-check2-all text-primary me-1.5"></i>
                    <span>Tipe Item Sah yang Diizinkan</span>
                </div>
                <div class="jd-detail-content-box">
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
                        Belum ada tipe item sah yang dikaitkan.
                    </span>
                </div>
            </div>

            <!-- 4. Informasi Teknis & Metadata -->
            <div class="jd-detail-section mb-2">
                <div class="jd-detail-section__header">
                    <i class="bi bi-clock-history text-primary me-1.5"></i>
                    <span>Informasi Teknis &amp; Audit</span>
                </div>
                <div class="jd-detail-meta-grid">
                    <div class="jd-detail-meta-item">
                        <span class="jd-detail-meta-label">Kode Internal (Mesin)</span>
                        <span class="jd-detail-meta-value font-monospace fw-bold text-dark">
                            {{ item.Kode_Jenis || '-' }}
                        </span>
                    </div>

                    <div class="jd-detail-meta-item">
                        <span class="jd-detail-meta-label">Contoh Format Nomor</span>
                        <span class="jd-detail-meta-value font-monospace small text-primary">
                            {{ item.Kode_Jenis_Dokumen || 'KODE' }}/…/…/…
                        </span>
                    </div>

                    <div class="jd-detail-meta-item">
                        <span class="jd-detail-meta-label">Urutan Tampilan</span>
                        <span class="jd-detail-meta-value">Urutan #{{ item.Urutan ?? '-' }}</span>
                    </div>

                    <div class="jd-detail-meta-item">
                        <span class="jd-detail-meta-label">Dibuat Pada</span>
                        <span class="jd-detail-meta-value">{{ formatTimestamp(item.Created_At) }}</span>
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
                    :title="(item?.Jumlah_Dokumen || 0) > 0 ? `Masih dipakai ${item.Jumlah_Dokumen} dokumen` : 'Nonaktifkan jenis ini'"
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
                        label="Ubah Jenis"
                        @click="onEditClick"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { HcisDialog, HcisButton, HcisBadge } from '@/components/ui';

defineOptions({ name: 'SopJenisDokumenDetailDialog' });

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
.sop-jd-detail-body {
    padding: 0.25rem 0;
}

/* Hero Banner */
.jd-detail-hero {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(124, 58, 237, 0.06) 100%);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 2px 8px -2px rgba(37, 99, 235, 0.12);
    margin-bottom: 1.15rem;
    overflow: hidden;
}

.jd-detail-hero__level-box {
    width: 48px;
    height: 48px;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-blue-600), var(--hcis-violet-600));
    color: #fff;
    box-shadow: 0 2px 6px -1px rgba(37, 99, 235, 0.35);
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.jd-detail-hero__level-label {
    font-size: 0.55rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    opacity: 0.85;
    line-height: 1;
}

.jd-detail-hero__level-num {
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.1;
}

.jd-detail-hero__title {
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.35;
    margin: 0;
    word-break: break-word;
    overflow-wrap: anywhere;
}

/* Sections */
.jd-detail-section {
    width: 100%;
}

.jd-detail-section__header {
    display: flex;
    align-items: center;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin-bottom: 0.45rem;
    letter-spacing: 0.01em;
}

.jd-detail-desc-box,
.jd-detail-content-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.85rem 1rem;
}

.jd-detail-desc-text {
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    margin: 0;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.jd-detail-desc-empty {
    font-size: 0.825rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-style: italic;
    margin: 0;
}

/* Metadata Grid */
.jd-detail-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
}

@media (max-width: 576px) {
    .jd-detail-meta-grid {
        grid-template-columns: 1fr;
    }
}

.jd-detail-meta-item {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.65rem 0.85rem;
    display: flex;
    flex-direction: column;
}

.jd-detail-meta-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.jd-detail-meta-value {
    font-size: 0.835rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    margin-top: 0.15rem;
    word-break: break-word;
}
</style>
