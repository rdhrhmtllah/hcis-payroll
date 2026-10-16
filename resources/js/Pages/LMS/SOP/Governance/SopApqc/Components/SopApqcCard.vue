<template>
    <div
        class="sop-apqc-card sop-apqc-card--compact"
        :style="{ '--apqc-color': processColor }"
        tabindex="0"
        role="button"
        :aria-label="`Proses APQC ${item.Kode_Proses} - ${item.Nama_Proses}. Klik untuk melihat detail.`"
        @click="$emit('click', item)"
        @keyup.enter="$emit('click', item)"
    >
        <!-- Luminous Dual-Corner Radial Glow Aura -->
        <div class="sop-apqc-card__glow" aria-hidden="true"></div>

        <!-- Top Row: Jewel Box + Process Name + Subtitle Pills -->
        <div class="sop-apqc-card__head">
            <div class="sop-apqc-card__head-left">
                <div class="sop-apqc-card__jewel">
                    <span class="sop-apqc-card__jewel-num">{{ item.Kode_Proses }}</span>
                </div>
                <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                    <h3 class="sop-apqc-card__title" :title="item.Nama_Proses">
                        {{ item.Nama_Proses }}
                    </h3>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span class="sop-apqc-card__domain-pill" :class="isOperating ? 'is-operating' : 'is-support'">
                            {{ isOperating ? 'Operasional (Core)' : 'Manajemen & Support' }}
                        </span>
                        <div
                            class="sop-apqc-card__status-text"
                            :class="(item.Jumlah_Dokumen || 0) > 0 ? 'is-used' : 'is-empty'"
                        >
                            <i class="bi" :class="(item.Jumlah_Dokumen || 0) > 0 ? 'bi-file-earmark-check-fill' : 'bi-dash-circle'"></i>
                            <span class="text-truncate">{{ (item.Jumlah_Dokumen || 0) > 0 ? `${item.Jumlah_Dokumen} Dokumen` : 'Belum Ada Dokumen' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description: Compact 2-line clamp -->
        <p class="sop-apqc-card__desc" :class="{ 'is-empty': !item.Deskripsi }">
            {{ item.Deskripsi || 'Tanpa deskripsi tambahan.' }}
        </p>

        <!-- Footer: Status Dot & Actions -->
        <div class="sop-apqc-card__footer">
            <div class="sop-apqc-card__status-dot">
                <span class="dot-indicator"></span>
                <span class="dot-label">Aktif</span>
            </div>

            <div class="sop-apqc-card__btn-group">
                <HcisButton
                    variant="ghost"
                    size="xs"
                    label="Detail"
                    leading-icon="eye"
                    aria-label="Lihat Detail Proses"
                    title="Lihat Detail Proses"
                    @click.stop="$emit('click', item)"
                />

                <HcisButton
                    variant="ghost"
                    size="xs"
                    label="Edit"
                    leading-icon="pencil-square"
                    aria-label="Edit Proses"
                    title="Edit Proses"
                    @click.stop="$emit('edit', item)"
                />

                <HcisButton
                    variant="danger-quiet"
                    size="xs"
                    label="Hapus"
                    leading-icon="trash3"
                    :disabled="(item.Jumlah_Dokumen || 0) > 0"
                    :title="(item.Jumlah_Dokumen || 0) > 0 ? `Masih dipakai ${item.Jumlah_Dokumen} dokumen` : 'Nonaktifkan proses ini'"
                    aria-label="Nonaktifkan Proses"
                    @click.stop="$emit('delete', item)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisButton } from '@/components/ui';

defineOptions({ name: 'SopApqcCard' });

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

defineEmits(['click', 'edit', 'delete']);

const processNum = computed(() => {
    const raw = String(props.item?.Kode_Proses || '').replace(/\D/g, '');
    return parseInt(raw, 10) || 0;
});

const isOperating = computed(() => {
    return processNum.value >= 1 && processNum.value <= 6;
});

const processColor = computed(() => {
    const colors = {
        1: '#2563eb', // Develop Vision & Strategy
        2: '#0284c7', // Develop & Manage Products
        3: '#0ea5e9', // Market & Sell
        4: '#06b6d4', // Supply Chain
        5: '#0d9488', // Deliver Services
        6: '#10b981', // Customer Service
        7: '#8b5cf6', // Human Capital
        8: '#6366f1', // IT
        9: '#f59e0b', // Finance
        10: '#d97706', // Assets
        11: '#dc2626', // Risk & Compliance
        12: '#ec4899', // External Relationships
        13: '#7c3aed', // Business Capabilities
    };
    return colors[processNum.value] || '#0284c7';
});
</script>

<style scoped>
.sop-apqc-card--compact {
    position: relative;
    height: 100%;
    width: 100%;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.85rem 1rem 0.75rem;
    box-shadow:
        0 1px 2px 0 rgba(0, 0, 0, 0.03),
        0 4px 10px -2px rgba(0, 0, 0, 0.02),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px) saturate(180%);
    transition:
        transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 0.18s ease,
        border-color 0.18s ease;
    overflow: hidden;
    isolation: isolate;
    cursor: pointer;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.sop-apqc-card--compact:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--apqc-color) 38%, rgba(203, 213, 225, 0.8));
    box-shadow:
        0 8px 18px -4px color-mix(in srgb, var(--apqc-color) 16%, rgba(0, 0, 0, 0.06)),
        0 2px 6px -1px color-mix(in srgb, var(--apqc-color) 10%, rgba(0, 0, 0, 0.03)),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

.sop-apqc-card--compact:focus-visible {
    outline: none;
    border-color: var(--apqc-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--apqc-color) 25%, transparent);
}

/* Luminous Glow Aura */
.sop-apqc-card__glow {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background: radial-gradient(130px 100px at 95% 5%, color-mix(in srgb, var(--apqc-color) 14%, transparent), transparent 70%);
    opacity: 0.85;
}

.sop-apqc-card--compact > *:not(.sop-apqc-card__glow) {
    position: relative;
    z-index: 1;
}

/* Head */
.sop-apqc-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    min-width: 0;
    width: 100%;
}

.sop-apqc-card__head-left {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
    width: 100%;
    flex: 1;
}

.sop-apqc-card__jewel {
    width: 36px;
    height: 36px;
    border-radius: var(--hcis-radius-md, 8px);
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, color-mix(in srgb, var(--apqc-color) 15%, #fff) 0%, color-mix(in srgb, var(--apqc-color) 8%, #fff) 100%);
    border: 1px solid color-mix(in srgb, var(--apqc-color) 30%, transparent);
    color: var(--apqc-color);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 1),
        0 2px 6px -1px color-mix(in srgb, var(--apqc-color) 20%, transparent);
    flex-shrink: 0;
    margin-top: 0.1rem;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.sop-apqc-card--compact:hover .sop-apqc-card__jewel {
    transform: scale(1.06);
}

.sop-apqc-card__jewel-num {
    font-size: 0.95rem;
    font-weight: 850;
    letter-spacing: -0.02em;
    line-height: 1;
    font-family: var(--hcis-font-mono, monospace);
}

.sop-apqc-card__title {
    font-size: 0.925rem;
    font-weight: 750;
    line-height: 1.3;
    letter-spacing: -0.015em;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    margin: 0 0 0.25rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
    transition: color 0.15s ease;
}

.sop-apqc-card--compact:hover .sop-apqc-card__title {
    color: color-mix(in srgb, var(--apqc-color) 65%, var(--hcis-slate-900));
}

.sop-apqc-card__domain-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.08rem 0.45rem;
    line-height: 1.2;
}

.sop-apqc-card__domain-pill.is-operating {
    color: var(--hcis-sky-700);
    background: var(--hcis-sky-100);
    border: 1px solid var(--hcis-sky-200);
}

.sop-apqc-card__domain-pill.is-support {
    color: var(--hcis-violet-700);
    background: var(--hcis-purple-100);
    border: 1px solid #ddd6fe;
}

.sop-apqc-card__status-text {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.725rem;
    font-weight: 600;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.12rem 0.45rem;
    line-height: 1.2;
    max-width: 100%;
    min-width: 0;
}

.sop-apqc-card__status-text > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sop-apqc-card__status-text.is-used {
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    background: color-mix(in srgb, var(--hcis-brand-500, var(--hcis-sky-600)) 12%, rgba(255, 255, 255, 0.9));
    border: 1px solid color-mix(in srgb, var(--hcis-brand-500, var(--hcis-sky-600)) 25%, transparent);
}

.sop-apqc-card__status-text.is-empty {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.8);
}

/* Description */
.sop-apqc-card__desc {
    font-size: 0.8125rem;
    line-height: 1.45;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin: 0 0 0.85rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
    min-height: 2.35em;
}

.sop-apqc-card__desc.is-empty {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-style: italic;
}

/* Footer */
.sop-apqc-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    min-width: 0;
}

/* Status Dot */
.sop-apqc-card__status-dot {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    flex-shrink: 0;
}

.dot-indicator {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-emerald-500, var(--hcis-emerald-500));
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--hcis-emerald-500, var(--hcis-emerald-500)) 25%, transparent);
}

.sop-apqc-card__btn-group {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}
</style>
