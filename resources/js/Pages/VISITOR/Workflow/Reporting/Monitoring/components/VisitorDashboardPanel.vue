<template>
    <div class="visitor-dashboard">
        <HcisAlert v-if="galat" variant="danger" :title="galat" closable class="mb-3" @close="galat = ''" />

        <!-- Seksi Antrean Keputusan Approval -->
        <div v-if="isNeedsApproval" class="visitor-dashboard__tindakan">
            <DashboardAntrianKeputusan ref="antrianRef" :domain="domain" />
        </div>

        <!-- Pemisah Seksi Analisis Tren -->
        <div class="visitor-dashboard__pemisah">
            <div class="d-flex align-items-center gap-2">
                <div class="visitor-dashboard__pemisah-icon">
                    <HcisIcon name="graph-up-arrow" size="xs" />
                </div>
                <span class="visitor-dashboard__pemisah-title">Analisis Tren & Pola Aktivitas Kunjungan</span>
            </div>
            <span class="visitor-dashboard__pemisah-line" />
        </div>

        <!-- Rentang Periode Analitik dengan Quick Preset Chips -->
        <div class="visitor-dashboard__rentang-wrap">
            <div class="visitor-dashboard__rentang">
                <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                    <div class="d-flex align-items-center gap-1.5 text-slate-700 fw-bold text-xs">
                        <HcisIcon name="calendar-range" size="xs" class="text-indigo-600 flex-shrink-0" />
                        <span>Periode Analisis:</span>
                    </div>

                    <!-- Preset Chips: 7d, 14d, 30d -->
                    <div class="visitor-dashboard__preset-chips">
                        <button
                            type="button"
                            class="visitor-dashboard__preset-chip"
                            :class="{ 'is-active': activePreset === 7 }"
                            @click="pilihPreset(7)"
                        >
                            7 Hari
                        </button>
                        <button
                            type="button"
                            class="visitor-dashboard__preset-chip"
                            :class="{ 'is-active': activePreset === 14 }"
                            @click="pilihPreset(14)"
                        >
                            14 Hari
                        </button>
                        <button
                            type="button"
                            class="visitor-dashboard__preset-chip"
                            :class="{ 'is-active': activePreset === 30 }"
                            @click="pilihPreset(30)"
                        >
                            30 Hari
                        </button>
                    </div>
                </div>

                <HcisDateRangePicker
                    v-model="rentang"
                    size="sm"
                    class="visitor-dashboard__picker"
                    @update:model-value="onManualDateChange"
                />
            </div>
        </div>

        <!-- Blok Grafik Visual: Tren Kunjungan & Heatmap Jam Sibuk -->
        <DashboardBlokTren
            v-if="domain !== 'permit'"
            :tren="analitik?.tren ?? []"
            :sebaran-jam="analitik?.sebaran_jam ?? []"
            :data-tipis="dataTipis"
            :loading="memuatAnalitik"
        />

        <DashboardBlokPermit
            v-if="domain !== 'tamu'"
            :kategori="analitik?.permit_kategori ?? []"
            :tren="analitik?.permit_tren ?? []"
            :loading="memuatAnalitik"
        />
    </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue';
import { HcisAlert, HcisDateRangePicker, HcisIcon } from '@/components/ui';
import { ambilAnalitik } from '../api/visitorDashboardApi';
import DashboardBlokPermit from './dashboard/DashboardBlokPermit.vue';
import DashboardBlokTren from './dashboard/DashboardBlokTren.vue';
import DashboardAntrianKeputusan from './dashboard/DashboardAntrianKeputusan.vue';
import { AMBANG_DATA_TIPIS } from './dashboard/tetapan';

defineOptions({ name: 'VisitorDashboardPanel' });

const props = defineProps({
    /** 'tamu' | 'permit' | null (kedua domain). */
    domain: { type: String, default: null },
    needsApproval: { type: Boolean, default: null },
});

// Halaman B2B menyuntikkan endpoint ber-awalan -b2b-. Grafik tamu ikut tipe
// halamannya; tanpa ini halaman Regular dan B2B menampilkan grafik gabungan
// yang berselisih dengan daftarnya sendiri.
const endpointTamu = inject('visitorTamuEndpoints', null);
const tipeTamu = computed(() => {
    if (props.domain !== 'tamu') return null;
    return String(endpointTamu?.laporan || '').includes('-b2b-') ? 'B2B' : 'REGULAR';
});

const isNeedsApproval = computed(() => {
    if (props.needsApproval !== null) {
        return props.needsApproval;
    }
    return props.domain === 'permit';
});

const antrianRef = ref(null);
const galat = ref('');

const analitik = ref(null);
const memuatAnalitik = ref(false);
const activePreset = ref(14);
const rentang = ref([tanggalMundur(13), tanggalMundur(0)]);

const dataTipis = computed(
    () => (analitik.value?.kesehatan_data?.persen_tanpa_waktu_keluar ?? 0) >= AMBANG_DATA_TIPIS,
);

function tanggalMundur(hari) {
    const d = new Date();
    d.setDate(d.getDate() - hari);
    return d.toISOString().split('T')[0];
}

function pilihPreset(hari) {
    activePreset.value = hari;
    rentang.value = [tanggalMundur(hari - 1), tanggalMundur(0)];
    muatAnalitik();
}

function onManualDateChange() {
    activePreset.value = null;
    muatAnalitik();
}

async function muatAnalitik(segarkan = false) {
    memuatAnalitik.value = true;
    try {
        analitik.value = await ambilAnalitik({
            dari: rentang.value?.[0],
            sampai: rentang.value?.[1],
            segarkan,
            domain: props.domain,
            tipe: tipeTamu.value,
        });
    } catch (e) {
        galat.value = e?.response?.data?.message || 'Gagal memuat analitik.';
    } finally {
        memuatAnalitik.value = false;
    }
}

/**
 * Dipanggil wrapper: satu tombol Segarkan untuk seluruh tab.
 */
async function segarkan() {
    const tasks = [muatAnalitik(true)];
    if (isNeedsApproval.value && antrianRef.value?.muat) {
        tasks.push(antrianRef.value.muat());
    }
    await Promise.all(tasks);
}

defineExpose({ segarkan, muatAnalitik });

onMounted(() => {
    muatAnalitik();
});
</script>

<style scoped>
.visitor-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.visitor-dashboard__tindakan {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
}

.visitor-dashboard__pemisah {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0.75rem 0 0.15rem;
    width: 100%;
    min-width: 0;
}

.visitor-dashboard__pemisah-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 6px;
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    flex-shrink: 0;
}

.visitor-dashboard__pemisah-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    letter-spacing: -0.01em;
    white-space: nowrap;
}

.visitor-dashboard__pemisah-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, var(--hcis-slate-300, #cbd5e1) 0%, rgba(203, 213, 225, 0.2) 100%);
    min-width: 2rem;
}

.visitor-dashboard__rentang-wrap {
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.65rem 0.85rem !important;
    border-radius: var(--hcis-radius-lg, 12px) !important;
    width: 100%;
    box-sizing: border-box;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.visitor-dashboard__rentang {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
    min-width: 0;
}

.visitor-dashboard__preset-chips {
    display: inline-flex;
    align-items: center;
    background: rgba(226, 232, 240, 0.6);
    border-radius: 9999px;
    padding: 2px;
    gap: 2px;
}

.visitor-dashboard__preset-chip {
    border: none;
    background: transparent;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.visitor-dashboard__preset-chip:hover {
    color: var(--hcis-slate-900, #0f172a);
}

.visitor-dashboard__preset-chip.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

@media (max-width: 640px) {
    .visitor-dashboard__rentang {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }

    .visitor-dashboard__picker {
        width: 100%;
    }

    .visitor-dashboard__pemisah-title {
        white-space: normal;
    }
}
</style>
