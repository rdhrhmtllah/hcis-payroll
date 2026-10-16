<template>
    <!-- Card Masuk vs Tuntas per Minggu di-hide -->
    <div v-if="false" class="sop-stack bab-momentum">
        <HcisChart
            type="bar"
            :series="seri"
            :categories="kategori"
            :height="340"
            title="Masuk vs Tuntas per Minggu"
            kicker="Dua Aliran"
            :description="chartDescription"
            icon="bar-chart-steps"
            sentiment="multi"
            variant="glass"
            :loading="memuat"
            :tooltip-formatter="formatTooltip"
            :empty-text="'Belum ada penugasan maupun penyelesaian pada rentang ini'"
            allow-export
            allow-table-toggle
        >
            <template #footer>
                <p class="bm-catatan">
                    <HcisIcon name="info-circle" size="xs" />
                    {{ chartFooter }}
                </p>
            </template>
        </HcisChart>
    </div>
</template>

<script>
import { HcisChart, HcisIcon } from '@/components/ui';
import { ambil, angka, formatLabelRentang, tanggal } from './agregat.js';
import { kategoriMomentum, seriMomentum } from './grafikSeri.js';

/**
 * Bab 2 — Momentum & Proyeksi (Logic-only / Hidden View).
 */
export default {
    name: 'BabMomentum',
    components: { HcisChart, HcisIcon },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['ringkas'],
    data() {
        return {
            data: { sisa: {}, laju: {}, deret: [], arah: {}, proyeksi: {}, basis_ledger: null },
            memuat: true,
        };
    },
    computed: {
        labelRentang() {
            return formatLabelRentang(this.saringan);
        },
        chartDescription() {
            if (this.labelRentang) {
                return `Grafik perbandingan volume penugasan baru masuk versus penugasan selesai pada rentang ${this.labelRentang}.`;
            }
            return 'Grafik perbandingan volume penugasan baru masuk versus penugasan selesai per minggu.';
        },
        chartFooter() {
            const a = this.data.arah || {};
            if (this.labelRentang) {
                return `Data pada rentang ${this.labelRentang}: ${angka(a.masuk_4mgg)} masuk, ${angka(a.tuntas_4mgg)} selesai.`;
            }
            return 'Diperbarui otomatis berdasarkan rekam penugasan.';
        },
        /* Dibentuk grafikSeri.js — berkas yang sama dibaca perakit laporan
           PDF, supaya grafik di kertas tak pelan-pelan menyimpang dari layar. */
        kategori() {
            return kategoriMomentum(this.data);
        },
        seri() {
            return seriMomentum(this.data);
        },
    },
    watch: {
        saringan: {
            deep: true,
            immediate: true,
            handler() {
                this.muat();
            },
        },
    },
    methods: {
        formatTooltip(params) {
            if (!params || !params.length) return '';
            const tgl = params[0].axisValueLabel || '';
            let html = `<div style="font-weight: 700; margin-bottom: 4px;">Minggu ${tgl}</div>`;
            params.forEach((p) => {
                html += `<div style="display: flex; justify-content: space-between; gap: 12px; font-size: 12px;">
                    <span style="color: ${p.color}; font-weight: 600;">● ${p.seriesName}:</span>
                    <strong>${angka(p.value)}</strong>
                </div>`;
            });
            return html;
        },
        async muat() {
            this.memuat = true;
            try {
                const res = await ambil('/analitik/momentum', this.saringan);
                if (res && typeof res === 'object') {
                    this.data = {
                        sisa: res.sisa || {},
                        laju: res.laju || {},
                        deret: Array.isArray(res.deret) ? res.deret : [],
                        arah: res.arah || {},
                        proyeksi: res.proyeksi || {},
                        basis_ledger: res.basis_ledger || null,
                    };
                    this.$emit('ringkas', {
                        sisa: this.data.sisa,
                        laju: this.data.laju,
                        arah: this.data.arah,
                        proyeksi: this.data.proyeksi,
                        orang: this.data.sisa?.orang || 0,
                    });
                }
            } catch (e) {
                this.data = { sisa: {}, laju: {}, deret: [], arah: {}, proyeksi: {}, basis_ledger: null };
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.bab-momentum {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.bm-catatan {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-subtle);
}
</style>
