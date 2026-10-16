<template>
    <nav class="pa-compact-stepper" aria-label="Tahapan Alur Evaluasi 360°">
        <!-- Compact Interactive Pipeline Track -->
        <div class="pa-compact-scroll-wrap">
            <div class="pa-compact-track" role="tablist">
                <template v-for="(item, idx) in steps" :key="item.kode">
                    <!-- Compact Step Button -->
                    <button
                        type="button"
                        role="tab"
                        class="pa-compact-item"
                        :class="[
                            `is-${item.state}`,
                            {
                                'is-selected': isTerpilih(item.kode),
                                'is-disabled': item.disabled,
                            }
                        ]"
                        :disabled="item.disabled"
                        :aria-selected="isTerpilih(item.kode) ? 'true' : 'false'"
                        :aria-current="isTerpilih(item.kode) ? 'step' : undefined"
                        :title="item.caption"
                        @click="pilihKode(item.kode)"
                    >
                        <!-- Node Circle Marker -->
                        <div
                            class="pa-compact-marker"
                            :class="[
                                `is-${item.kode.toLowerCase()}`,
                                {
                                    'is-done': item.state === 'done',
                                    'is-current': isTerpilih(item.kode),
                                    'is-locked': item.disabled,
                                }
                            ]"
                        >
                            <HcisIcon
                                v-if="item.state === 'done'"
                                name="check-lg"
                                size="2xs"
                                class="pa-marker-icon"
                            />
                            <HcisIcon
                                v-else-if="item.disabled"
                                name="lock-fill"
                                size="2xs"
                                class="pa-marker-icon"
                            />
                            <HcisIcon
                                v-else-if="item.kode === 'RINGKASAN'"
                                name="send-fill"
                                size="2xs"
                                class="pa-marker-icon"
                            />
                            <span v-else class="pa-marker-num">{{ idx + 1 }}</span>
                        </div>

                        <!-- Content Beside Marker (Horizontal Layout) -->
                        <div class="pa-compact-info">
                            <div class="pa-compact-title-row">
                                <span class="pa-compact-tag" :class="{ 'is-final': item.kode === 'RINGKASAN' }">
                                    {{ item.stepTag }}
                                </span>
                                <strong class="pa-compact-title">{{ item.label }}</strong>
                            </div>

                            <div class="pa-compact-sub-row">
                                <span class="pa-compact-caption">{{ item.caption }}</span>
                                <span v-if="isTerpilih(item.kode)" class="pa-compact-active-pill">
                                    <span class="pa-active-dot" />
                                    <span>Aktif</span>
                                </span>
                                <span v-else-if="item.state === 'done'" class="pa-compact-done-pill">
                                    <span>Tuntas</span>
                                </span>
                            </div>
                        </div>
                    </button>

                    <!-- Connector Line & Arrow between steps -->
                    <div
                        v-if="idx < steps.length - 1"
                        class="pa-compact-connector"
                        :class="{ 'is-completed': item.state === 'done' }"
                        aria-hidden="true"
                    >
                        <div class="pa-connector-line" />
                        <HcisIcon name="chevron-right" size="2xs" class="pa-connector-chevron" />
                        <div class="pa-connector-line" />
                    </div>
                </template>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

import { HcisIcon } from '@/components/ui';

defineOptions({ name: 'PaWizardStepper' });

const props = defineProps({
    langkah: { type: Array, default: () => [] },
    kodeDilihat: { type: String, default: null },
    ringkasanTerbuka: { type: Boolean, default: false },
});

const emit = defineEmits(['pilih']);

const isSemuaTuntas = computed(() => {
    return props.langkah.length > 0 && props.langkah.every((l) => l.status === 'TUNTAS');
});

function isTerpilih(kode) {
    if (kode === 'RINGKASAN') return props.kodeDilihat === null;
    return props.kodeDilihat === kode;
}

const steps = computed(() => {
    const list = props.langkah.map((l, idx) => {
        const selected = isTerpilih(l.kode_kategori);
        const tuntas = l.status === 'TUNTAS';
        const terkunci = l.status === 'TERKUNCI';

        let state = 'todo';
        if (selected) state = 'current';
        else if (tuntas) state = 'done';
        else if (terkunci) state = 'todo';

        return {
            kode: l.kode_kategori,
            stepTag: `0${idx + 1}`,
            label: l.nama_kategori,
            caption: terkunci
                ? (l.sebab_terkunci || 'Terkunci')
                : `${l.jumlah_terisi}/${l.jumlah_lembar} Lembar Terisi`,
            state,
            disabled: terkunci,
        };
    });

    const isRingkasanSelected = isTerpilih('RINGKASAN');
    list.push({
        kode: 'RINGKASAN',
        stepTag: 'AKHIR',
        label: 'Ringkasan & Kirim',
        caption: props.ringkasanTerbuka ? 'Siap kirim resmi' : 'Menunggu tuntas',
        state: isRingkasanSelected ? 'current' : (isSemuaTuntas.value ? 'done' : 'todo'),
        disabled: !props.ringkasanTerbuka,
    });

    return list;
});

function pilihKode(kode) {
    const s = steps.value.find((x) => x.kode === kode);
    if (s && !s.disabled) {
        emit('pilih', s.kode);
    }
}

defineExpose({ pilihKode });
</script>

<style scoped>
.pa-compact-stepper {
    display: flex;
    flex-direction: column;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Scroll Wrap & Pipeline Track */
.pa-compact-scroll-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
    padding: 0.15rem 0.1rem 0.35rem;
}

.pa-compact-scroll-wrap::-webkit-scrollbar {
    height: 4px;
}

.pa-compact-scroll-wrap::-webkit-scrollbar-thumb {
    background-color: rgba(203, 213, 225, 0.8);
    border-radius: 999px;
}

.pa-compact-track {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    min-width: max-content;
}

/* Step Item Button */
.pa-compact-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.95rem;
    border-radius: var(--hcis-radius-md, 11px);
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    cursor: pointer;
    outline: none;
    user-select: none;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
    flex: 1 1 0;
    min-width: 190px;
    text-align: left;
}

.pa-compact-item:hover:not(.is-disabled) {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
    transform: translateY(-1px);
}

.pa-compact-item.is-selected {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.98) 0%, rgba(245, 243, 255, 0.98) 100%);
    border-color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.16), 0 0 0 1px var(--hcis-color-brand-600, #4f46e5);
    transform: translateY(-1px);
}

.pa-compact-item.is-done {
    border-color: rgba(16, 185, 129, 0.3);
    background: linear-gradient(180deg, #ffffff 0%, rgba(16, 185, 129, 0.04) 100%);
}

.pa-compact-item.is-done.is-selected {
    border-color: var(--hcis-success, #10b981);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.16), 0 0 0 1px var(--hcis-success, #10b981);
}

.pa-compact-item.is-disabled {
    opacity: 0.55;
    cursor: not-allowed;
    background: rgba(241, 245, 249, 0.6);
}

/* Marker Circle */
.pa-compact-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #ffffff;
    border: 1.5px solid rgba(203, 213, 225, 0.9);
    color: var(--hcis-color-text-muted, #64748b);
    flex-shrink: 0;
    transition: all 180ms ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pa-marker-num {
    font-size: 0.75rem;
    font-weight: 850;
}

.pa-compact-marker.is-done {
    background: var(--hcis-success, #10b981);
    border-color: var(--hcis-success, #10b981);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.35);
}

.pa-compact-marker.is-current {
    background: var(--hcis-color-brand-600, #4f46e5);
    border-color: var(--hcis-color-brand-600, #4f46e5);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
}

.pa-compact-marker.is-locked {
    background: rgba(241, 245, 249, 0.85);
    border-color: #cbd5e1;
    color: #94a3b8;
}

/* Info Beside Marker */
.pa-compact-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.pa-compact-title-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-compact-tag {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--hcis-color-text-muted, #94a3b8);
    background: rgba(0, 0, 0, 0.05);
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.pa-compact-tag.is-final {
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(79, 70, 229, 0.08);
}

.pa-compact-title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.is-selected .pa-compact-title {
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-compact-sub-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-compact-caption {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-compact-active-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 750;
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(79, 70, 229, 0.08);
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.pa-active-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--hcis-color-brand-600, #4f46e5);
    animation: pa-pulse 1.8s infinite ease-in-out;
}

.pa-compact-done-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-success, #10b981);
    background: rgba(16, 185, 129, 0.08);
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    flex-shrink: 0;
}

/* Connector Line & Arrow */
.pa-compact-connector {
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(203, 213, 225, 0.9);
    flex-shrink: 0;
    padding: 0 0.15rem;
}

.pa-connector-line {
    width: 8px;
    height: 1.5px;
    background: rgba(203, 213, 225, 0.9);
}

.pa-connector-chevron {
    margin: 0 2px;
}

.pa-compact-connector.is-completed {
    color: var(--hcis-success, #10b981);
}

.pa-compact-connector.is-completed .pa-connector-line {
    background: var(--hcis-success, #10b981);
}

@keyframes pa-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

@media (max-width: 768px) {
    .pa-compact-stepper {
        padding: 0.5rem 0.75rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-compact-item {
        min-width: 110px;
        padding: 0.4rem 0.55rem;
        gap: 0.45rem;
    }

    .pa-compact-marker {
        width: 1.75rem;
        height: 1.75rem;
    }

    .pa-marker-num {
        font-size: 0.7rem;
    }

    .pa-compact-title {
        font-size: 0.8rem;
    }

    .pa-compact-caption {
        display: none !important;
    }

    .pa-compact-connector .pa-connector-line {
        width: 4px;
    }
}
</style>
