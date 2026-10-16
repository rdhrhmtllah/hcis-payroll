<template>
    <section class="pa-tk-wrapper" :class="`is-${keadaan.toLowerCase()}`" :data-uji="`kepala-${kode}`">
        <HcisContextBanner
            :title="judul"
            :kicker="kickerText"
            :subtitle="tahap?.ringkas || deskripsi"
            :icon="iconName"
            :sentiment="sentimentName"
            variant="glass"
            pattern="grid"
            :glow="true"
            :bordered="true"
            class="pa-tk-banner"
            :class="`is-${keadaan.toLowerCase()}`"
        >
            <template #icon>
                <div class="pa-tk-icon-box" :class="`is-${keadaan.toLowerCase()}`">
                    <span class="pa-tk-step-num">{{ nomor }}</span>
                    <HcisIcon :name="iconName" size="sm" />
                </div>
            </template>

            <template #scope>
                <div class="pa-tk-scope-group">
                    <HcisBadge
                        :label="LABEL_KEADAAN[keadaan] || keadaan"
                        :variant="keadaanVariant"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                </div>
            </template>

            <template #capabilities>
                <div v-if="tahap?.aktivitas" class="pa-tk-aktivitas-pill">
                    <HcisIcon name="clock-history" size="2xs" />
                    <span>
                        Terakhir: <b>{{ tahap.aktivitas.aksi }}</b>
                        <template v-if="tahap.aktivitas.oleh"> oleh <b>{{ tahap.aktivitas.oleh }}</b></template>
                        · {{ waktu(tahap.aktivitas.pada) }}
                    </span>
                </div>
            </template>

            <template v-if="$slots.aksi" #actions>
                <div class="pa-tk__aksi">
                    <slot name="aksi" />
                </div>
            </template>
        </HcisContextBanner>

        <!-- Warning / Blocker list if any issues exist -->
        <HcisAlert
            v-if="tahap?.penghambat?.length"
            variant="warning"
            sentiment="warning"
            title="Hal yang Perlu Diselesaikan Sebelum Melanjutkan:"
            class="pa-tk-blocker-alert"
        >
            <ul class="pa-tk__hambat" role="list">
                <li v-for="h in tahap.penghambat" :key="h">
                    <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                    <span>{{ h }}</span>
                </li>
            </ul>
        </HcisAlert>

        <!-- Catatan informatif dari server: tidak menahan apa pun, jadi tetap tampil pada tahap SELESAI
             (mis. siklus ditutup dengan sebagian besar penugasan belum diisi). -->
        <HcisAlert
            v-if="tahap?.catatan"
            variant="info"
            size="sm"
            :description="tahap.catatan"
            class="pa-tk-blocker-alert"
            data-uji="catatan-tahap"
        />
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { HcisAlert, HcisBadge, HcisContextBanner, HcisIcon } from '@/components/ui';
import { LABEL_KEADAAN, TAHAP_PENUTUPAN } from './tahapPenutupan.js';

defineOptions({ name: 'PaTahapKepala' });

const props = defineProps({
    kode: { type: String, required: true },
    tahap: { type: Object, default: null },
    deskripsi: { type: String, default: '' },
});

const nomor = computed(() => TAHAP_PENUTUPAN.findIndex((t) => t.kode === props.kode) + 1);
const judul = computed(() => props.tahap?.nama || TAHAP_PENUTUPAN.find((t) => t.kode === props.kode)?.nama || props.kode);
const keadaan = computed(() => props.tahap?.keadaan || 'MENUNGGU');

const kickerText = computed(() => `TAHAP ${nomor.value} · ALUR PENUTUPAN SIKLUS`);

const iconName = computed(() => {
    switch (props.kode) {
        case 'pengisian': return 'people-fill';
        case 'verifikasi': return 'calculator-fill';
        case 'kualitatif': return 'chat-left-quote-fill';
        case 'kurva': return 'graph-up';
        case 'balancing': return 'sliders';
        case 'terbit': return 'send-check-fill';
        default: return 'speedometer2';
    }
});

const sentimentName = computed(() => {
    if (keadaan.value === 'SELESAI') return 'success';
    if (keadaan.value === 'BERJALAN') return 'brand';
    if (keadaan.value === 'DILEWATI') return 'neutral';
    if (props.tahap?.penghambat?.length) return 'warning';
    return 'brand';
});

const keadaanVariant = computed(() => {
    switch (keadaan.value) {
        case 'SELESAI': return 'success';
        case 'BERJALAN': return 'brand';
        case 'DILEWATI': return 'neutral';
        default: return 'warning';
    }
});

function waktu(v) {
    if (!v) return '–';
    const d = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.pa-tk-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
    margin-bottom: 0.85rem;
}

.pa-tk-banner {
    width: 100%;
}

.pa-tk-icon-box {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(59, 130, 246, 0.12);
    color: var(--hcis-brand, #3b82f6);
    flex-shrink: 0;
}

.pa-tk-icon-box.is-berjalan {
    background: rgba(99, 102, 241, 0.15);
    color: #4f46e5;
}

.pa-tk-icon-box.is-selesai {
    background: rgba(34, 197, 94, 0.15);
    color: #15803d;
}

.pa-tk-icon-box.is-dilewati {
    background: rgba(148, 163, 184, 0.15);
    color: #64748b;
}

.pa-tk-step-num {
    position: absolute;
    top: -5px;
    left: -5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.pa-tk-scope-group {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-tk-aktivitas-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
    flex-wrap: wrap;
}

.pa-tk-aktivitas-pill b {
    color: var(--hcis-text-main, #1e293b);
}

.pa-tk__aksi {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-tk-blocker-alert {
    width: 100%;
}

.pa-tk__hambat {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin: 0.35rem 0 0;
    padding: 0;
    list-style: none;
}

.pa-tk__hambat li {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: #92400e;
    line-height: 1.45;
}

@media (max-width: 640px) {
    .pa-tk-icon-box {
        width: 2.4rem;
        height: 2.4rem;
    }
    .pa-tk-step-num {
        width: 16px;
        height: 16px;
        font-size: 0.6rem;
    }
}
</style>
