<template>
    <div class="sc">
        <div v-if="judul || $slots.aksi" class="sc-head">
            <div class="min-w-0">
                <div v-if="eyebrow" class="sop-eyebrow">{{ eyebrow }}</div>
                <h6 v-if="judul" class="sop-h">{{ judul }}</h6>
                <p v-if="keterangan" class="sc-ket">{{ keterangan }}</p>
            </div>
            <slot name="aksi" />
        </div>

        <div v-if="loading" class="sop-skel" :style="{ height: tinggi + 'px', borderRadius: '14px' }"></div>

        <EmptyState v-else-if="kosong" :title="judulKosong" :subtitle="ketKosong" icon="bi-bar-chart-line" />

        <VueApexCharts v-else :type="tipe" :height="tinggi" :options="opsi" :series="series" />

        <p v-if="catatan && !kosong && !loading" class="sop-callout sop-callout-note mt-3 mb-0">{{ catatan }}</p>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import EmptyState from './EmptyState.vue';
import { dasar } from '../../support/sopChartTheme.js';

/**
 * Pembungkus tipis vue3-apexcharts dengan token DMS + state loading/kosong.
 *
 * Komponen ini sengaja dimuat malas dari halaman (defineAsyncComponent):
 * apexcharts ~130 KB dan sebelumnya belum pernah dipakai di bawah Pages/SOP,
 * jadi mengimpornya di shell akan membebani semua pengunjung monitoring —
 * termasuk yang tak pernah membuka tab Analitik.
 *
 * `kosong` bukan sekadar "tak ada baris": ia juga dipakai saat datanya ada tapi
 * BELUM LAYAK digambar (mis. ledger tren baru berisi 3 peristiwa). Garis nol yang
 * terbaca "coverage 0" jauh lebih menyesatkan daripada empty state yang jujur.
 */
export default {
    name: 'SopChart',
    components: { VueApexCharts, EmptyState },
    props: {
        tipe: { type: String, default: 'line' },
        series: { type: Array, default: () => [] },
        opsiExtra: { type: Object, default: () => ({}) },
        tinggi: { type: Number, default: 300 },
        judul: { type: String, default: '' },
        eyebrow: { type: String, default: '' },
        keterangan: { type: String, default: '' },
        catatan: { type: String, default: '' },
        loading: { type: Boolean, default: false },
        kosong: { type: Boolean, default: false },
        judulKosong: { type: String, default: 'Belum ada data' },
        ketKosong: { type: String, default: '' },
    },
    computed: {
        opsi() {
            return dasar(this.opsiExtra);
        },
    },
};
</script>

<style scoped>
.sc-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.9rem;
}
.sc-ket {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    margin: 0.25rem 0 0;
    line-height: 1.5;
    max-width: 62ch;
}
</style>
