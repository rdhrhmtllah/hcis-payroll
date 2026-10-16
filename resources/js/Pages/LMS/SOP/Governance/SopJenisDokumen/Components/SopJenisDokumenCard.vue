<template>
    <div
        class="sop-jd-card sop-jd-card--compact"
        :style="{ '--jc': levelColor }"
        tabindex="0"
        role="button"
        :aria-label="`Jenis Dokumen ${item.Nama_Jenis}, Tingkat L${item.Level_Hirarki}. Klik untuk melihat detail.`"
        @click="$emit('click', item)"
        @keyup.enter="$emit('click', item)"
    >
        <!-- Luminous Dual-Corner Radial Glow Aura -->
        <div class="sop-jd-card__glow" aria-hidden="true"></div>

        <!-- Top Row: Jewel Level Box + Name + Status Pill -->
        <div class="sop-jd-card__head">
            <div class="sop-jd-card__head-left">
                <div class="sop-jd-card__level-jewel">
                    <span class="sop-jd-card__level-txt">L{{ item.Level_Hirarki }}</span>
                </div>
                <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                    <h3 class="sop-jd-card__title" :title="item.Nama_Jenis">
                        {{ item.Nama_Jenis }}
                    </h3>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span v-if="item.Kode_Jenis_Dokumen" class="sop-jd-card__prefix-pill" title="Prefix Kode Cetak Dokumen">
                            #{{ item.Kode_Jenis_Dokumen }}
                        </span>
                        <div
                            class="sop-jd-card__status-text"
                            :class="(item.Jumlah_Dokumen || 0) > 0 ? 'is-used' : 'is-empty'"
                        >
                            <i class="bi" :class="(item.Jumlah_Dokumen || 0) > 0 ? 'bi-file-earmark-check-fill' : 'bi-dash-circle'"></i>
                            <span class="text-truncate">{{ (item.Jumlah_Dokumen || 0) > 0 ? `${item.Jumlah_Dokumen} Dokumen Terdaftar` : 'Belum Ada Dokumen' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description: Compact 2-line clamp -->
        <p class="sop-jd-card__desc" :class="{ 'is-empty': !item.Deskripsi }">
            {{ item.Deskripsi || 'Tanpa deskripsi tambahan.' }}
        </p>

        <!-- Footer: Status Dot & Compact Actions -->
        <div class="sop-jd-card__footer">
            <div class="sop-jd-card__status-dot">
                <span class="dot-indicator"></span>
                <span class="dot-label">Aktif</span>
            </div>

            <div class="sop-jd-card__btn-group">
                <HcisButton
                    variant="ghost"
                    size="xs"
                    label="Detail"
                    leading-icon="eye"
                    aria-label="Lihat Detail Jenis"
                    title="Lihat Detail Jenis"
                    @click.stop="$emit('click', item)"
                />

                <HcisButton
                    variant="ghost"
                    size="xs"
                    label="Edit"
                    leading-icon="pencil-square"
                    aria-label="Edit Jenis"
                    title="Edit Jenis"
                    @click.stop="$emit('edit', item)"
                />

                <HcisButton
                    variant="danger-quiet"
                    size="xs"
                    label="Hapus"
                    leading-icon="trash3"
                    :disabled="(item.Jumlah_Dokumen || 0) > 0"
                    :title="(item.Jumlah_Dokumen || 0) > 0 ? `Masih dipakai ${item.Jumlah_Dokumen} dokumen` : 'Nonaktifkan jenis ini'"
                    aria-label="Nonaktifkan Jenis"
                    @click.stop="$emit('delete', item)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisButton } from '@/components/ui';

defineOptions({ name: 'SopJenisDokumenCard' });

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

defineEmits(['click', 'edit', 'delete']);

const levelColor = computed(() => {
    const colors = {
        1: '#2563eb', // Blue - Kebijakan
        2: '#3b82f6', // Sky - SK
        3: '#0ea5e9', // Cyan - Prosedur/SOP
        4: '#06b6d4', // Teal - IK
        5: '#14b8a6', // Emerald - Standar
        6: '#10b981', // Green - Formulir
        7: '#6366f1', // Indigo - Manual
    };
    return colors[props.item?.Level_Hirarki] || '#64748b';
});
</script>

<style scoped>
.sop-jd-card--compact {
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

.sop-jd-card--compact:hover {
    transform: translateY(-2px);
    border-color: color-mix(in srgb, var(--jc) 38%, rgba(203, 213, 225, 0.8));
    box-shadow:
        0 8px 18px -4px color-mix(in srgb, var(--jc) 16%, rgba(0, 0, 0, 0.06)),
        0 2px 6px -1px color-mix(in srgb, var(--jc) 10%, rgba(0, 0, 0, 0.03)),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

.sop-jd-card--compact:focus-visible {
    outline: none;
    border-color: var(--jc);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--jc) 25%, transparent);
}

/* Luminous Glow */
.sop-jd-card__glow {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background: radial-gradient(130px 100px at 95% 5%, color-mix(in srgb, var(--jc) 14%, transparent), transparent 70%);
    opacity: 0.85;
}

.sop-jd-card--compact > *:not(.sop-jd-card__glow) {
    position: relative;
    z-index: 1;
}

/* Head */
.sop-jd-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    min-width: 0;
    width: 100%;
}

.sop-jd-card__head-left {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
    width: 100%;
    flex: 1;
}

.sop-jd-card__level-jewel {
    width: 36px;
    height: 36px;
    border-radius: var(--hcis-radius-md, 8px);
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, color-mix(in srgb, var(--jc) 15%, #fff) 0%, color-mix(in srgb, var(--jc) 8%, #fff) 100%);
    border: 1px solid color-mix(in srgb, var(--jc) 30%, transparent);
    color: var(--jc);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 1),
        0 2px 6px -1px color-mix(in srgb, var(--jc) 20%, transparent);
    flex-shrink: 0;
    margin-top: 0.1rem;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.sop-jd-card--compact:hover .sop-jd-card__level-jewel {
    transform: scale(1.06);
}

.sop-jd-card__level-txt {
    font-size: 0.925rem;
    font-weight: 850;
    letter-spacing: -0.02em;
    line-height: 1;
}

.sop-jd-card__title {
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

.sop-jd-card--compact:hover .sop-jd-card__title {
    color: color-mix(in srgb, var(--jc) 65%, var(--hcis-slate-900));
}

.sop-jd-card__prefix-pill {
    display: inline-flex;
    align-items: center;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.08rem 0.45rem;
    line-height: 1.2;
}

.sop-jd-card__status-text {
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

.sop-jd-card__status-text > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sop-jd-card__status-text.is-used {
    color: var(--hcis-brand-700, var(--hcis-sky-700));
    background: color-mix(in srgb, var(--hcis-brand-500, var(--hcis-sky-600)) 12%, rgba(255, 255, 255, 0.9));
    border: 1px solid color-mix(in srgb, var(--hcis-brand-500, var(--hcis-sky-600)) 25%, transparent);
}

.sop-jd-card__status-text.is-empty {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.8);
}

/* Description */
.sop-jd-card__desc {
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

.sop-jd-card__desc.is-empty {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-style: italic;
}

/* Footer */
.sop-jd-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    min-width: 0;
}

/* Status Dot */
.sop-jd-card__status-dot {
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

.sop-jd-card__btn-group {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}
</style>
