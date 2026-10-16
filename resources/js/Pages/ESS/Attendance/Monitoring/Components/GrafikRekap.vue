<template>
    <div class="mk-grafik-rekap">
        <!-- Eksplorasi Hierarki Organisasi (Drill-down: Batang / Treemap / Sunburst) -->
        <GrafikDrilldown
            :hierarki="hasil.hierarki"
            :memuat="memuat"
            @pilih-karyawan="$emit('buka-rincian', $event)"
        />

        <!-- Row 2: Tren Harian & Peta Panas Berdampingan (Cockpit Grid) -->
        <div class="mk-grafik-row-2">
            <HcisChart
                type="custom"
                title="Tren Kehadiran Harian"
                :description="`Komposisi kehadiran per tanggal · ${labelPeriode}`"
                icon="bar-chart-line"
                sentiment="brand"
                variant="glass"
                :options="opsiTren"
                :height="360"
                :metric-value="ringkasanTren ? ringkasanTren.persen : ''"
                metric-label="Tingkat Kehadiran"
                :insight="ringkasanTren ? `${ringkasanTren.hadir.toLocaleString('id-ID')} hadir dari ${ringkasanTren.terjadwal.toLocaleString('id-ID')} terjadwal` : ''"
                insight-label="Capaian Periode"
                :allow-export="true"
                :loading="memuat"
                empty-text="Tidak ada data pada periode ini"
                data-uji="grafik-tren"
            />

            <HcisCard
                title="Peta Panas Unit × Tanggal"
                :description="deskripsiHeatmap"
                icon="grid-3x3-gap"
                sentiment="brand"
                variant="glass"
                divider
            >
                <div class="mk-kendali">
                    <HcisTabs v-model="dimensi" :tabs="TAB_DIMENSI" variant="pills" size="sm" aria-label="Kelompokkan per" />
                    <HcisTabs v-model="metrik" :tabs="TAB_METRIK" variant="pills" size="sm" aria-label="Metrik peta panas" />
                </div>
                <!-- Di layar sempit peta panas digeser horizontal; dipadatkan, selnya hilang. -->
                <div class="mk-heatmap-gulir">
                    <HcisChart
                        class="mk-heatmap"
                        type="heatmap"
                        :card="false"
                        :categories="heatmap.tanggal.map(labelTanggalPendek)"
                        :series="[{ categories: heatmap.unit }]"
                        :data="heatmap.data"
                        :sentiment="METRIK_HEATMAP[metrik].sentiment"
                        :value-label="METRIK_HEATMAP[metrik].label"
                        :value-suffix="METRIK_HEATMAP[metrik].suffix"
                        :metric-unit="metrik === 'tidak_hadir' ? '%' : ''"
                        :options="opsiHeatmap"
                        :height="300"
                        :loading="memuat"
                        empty-text="Tidak ada data pada periode ini"
                        data-uji="grafik-heatmap"
                        @chart-click="klikSel"
                    />
                </div>
            </HcisCard>
        </div>

        <div class="mk-peringkat">
            <HcisChart
                v-for="(m, kunci) in METRIK_PERINGKAT"
                :key="kunci"
                type="bar-horizontal"
                :title="`${m.label} tertinggi`"
                :description="`10 ${LABEL_DIMENSI[dimensi].toLowerCase()} teratas`"
                icon="sort-down"
                :sentiment="m.sentiment"
                variant="glass"
                :categories="daftarPeringkat[kunci].kategori"
                :data="daftarPeringkat[kunci].nilai"
                :metric-unit="m.satuan"
                :options="{ yAxis: { inverse: false } }"
                :allow-export="true"
                :height="300"
                :loading="memuat"
                empty-text="Tidak ada nilai di atas nol"
            />
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { HcisCard, HcisChart, HcisTabs, hcisMessage } from '@/components/ui';
import GrafikDrilldown from './GrafikDrilldown.vue';
import {
    METRIK_HEATMAP,
    METRIK_PERINGKAT,
    dataHeatmap,
    labelTanggalPendek,
    opsiTren,
    peringkat,
} from './grafikMonitoring.js';

const BASE = '/admin/monitoring-kehadiran';

const LABEL_DIMENSI = { departemen: 'Departemen', divisi: 'Divisi', lokasi: 'Lokasi' };
const TAB_DIMENSI = Object.entries(LABEL_DIMENSI).map(([key, label]) => ({ key, label }));
const TAB_METRIK = Object.entries(METRIK_HEATMAP).map(([key, m]) => ({ key, label: m.label }));
export const MAKS_UNIT_HEATMAP = 25;

export default {
    name: 'GrafikRekap',
    components: { GrafikDrilldown, HcisCard, HcisChart, HcisTabs },
    props: {
        /** Rentang + saringan organisasi, sama dengan tabel rekap. */
        parameter: { type: Object, required: true },
        labelPeriode: { type: String, default: '' },
    },
    emits: ['buka-hari', 'buka-rincian', 'update:diperbarui'],
    data() {
        return {
            METRIK_HEATMAP,
            METRIK_PERINGKAT,
            LABEL_DIMENSI,
            TAB_DIMENSI,
            TAB_METRIK,
            dimensi: 'departemen',
            metrik: 'tidak_hadir',
            memuat: false,
            hasil: { tren: [], sel: [], kelompok: [] },
            permintaan: 0,
        };
    },
    computed: {
        ringkasanTren() {
            const list = this.hasil?.tren || [];
            if (!list.length) return null;
            const terjadwal = list.reduce((a, t) => a + (Number(t.terjadwal) || 0), 0);
            const hadir = list.reduce((a, t) => a + (Number(t.hadir) || 0), 0);
            const terlambat = list.reduce((a, t) => a + (Number(t.hadir_terlambat) || 0), 0);
            const persen = terjadwal > 0 ? ((hadir / terjadwal) * 100).toFixed(1) : '0';
            return {
                persen: `${persen}%`,
                terjadwal,
                hadir,
                terlambat,
            };
        },
        opsiTren() {
            return opsiTren(this.hasil.tren);
        },
        heatmap() {
            return dataHeatmap(this.hasil.sel, this.metrik, MAKS_UNIT_HEATMAP);
        },
        tinggiHeatmap() {
            return Math.max(240, 75 + this.heatmap.unit.length * 30);
        },
        opsiHeatmap() {
            return {
                grid: { top: 24, right: 20, bottom: 60, left: 14, containLabel: true },
                xAxis: {
                    axisLabel: {
                        interval: this.heatmap.tanggal.length > 20 ? 'auto' : 0,
                        fontSize: 11,
                        color: '#64748b',
                        fontWeight: 500,
                    },
                },
                yAxis: {
                    axisLabel: {
                        interval: 0,
                        width: 140,
                        overflow: 'truncate',
                        fontSize: 11,
                        color: '#334155',
                        fontWeight: 600,
                    },
                },
                series: [
                    {
                        cursor: 'pointer',
                        itemStyle: {
                            borderRadius: 4,
                            borderColor: '#ffffff',
                            borderWidth: 2,
                        },
                        emphasis: {
                            itemStyle: {
                                borderColor: '#0f172a',
                                borderWidth: 2,
                                shadowBlur: 10,
                                shadowColor: 'rgba(0, 0, 0, 0.25)',
                            },
                        },
                    },
                ],
            };
        },
        deskripsiHeatmap() {
            const sisa = this.heatmap.sisa ? ` · ${this.heatmap.sisa} unit lain tidak ditampilkan` : '';
            return `Makin gelap makin kritis. Klik sel untuk memfilter kehadiran tanggal tersebut.${sisa}`;
        },
        daftarPeringkat() {
            return Object.fromEntries(Object.keys(METRIK_PERINGKAT).map((k) => [k, peringkat(this.hasil.kelompok, k)]));
        },
    },
    watch: {
        parameter: { deep: true, handler() { this.muat(); } },
        dimensi() { this.muat(); },
    },
    mounted() {
        this.muat();
    },
    methods: {
        labelTanggalPendek,
        async muat() {
            const nomor = ++this.permintaan;
            this.memuat = true;
            try {
                const { data } = await axios.get(`${BASE}/data/grafik`, { params: { ...this.parameter, dimensi: this.dimensi } });
                if (nomor === this.permintaan && data?.success) {
                    this.hasil = data.result;
                    this.$emit('update:diperbarui', data.result.diperbarui || 0);
                }
            } catch (e) {
                if (nomor === this.permintaan) hcisMessage.error(e.response?.data?.message || 'Data grafik gagal dimuat.');
            } finally {
                if (nomor === this.permintaan) this.memuat = false;
            }
        },
        klikSel(p) {
            const d = Array.isArray(p?.data) ? p.data : p?.value;
            if (!Array.isArray(d)) return;
            const tanggal = this.heatmap.tanggal[d[0]];
            const kelompok = this.heatmap.unit[d[1]];
            // "(Tanpa data)" bukan nilai yang bisa disaring.
            if (!tanggal || !kelompok || kelompok === '(Tanpa data)') return;
            this.$emit('buka-hari', { tanggal, dimensi: this.dimensi, kelompok });
        },
    },
};
</script>

<style scoped>
.mk-grafik-rekap {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.mk-kendali {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.mk-grafik-row-2 {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: var(--hcis-space-4, 1rem);
}

.mk-heatmap-gulir {
    overflow-x: auto;
}

.mk-heatmap {
    min-width: 600px;
}

.mk-peringkat {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

@media (max-width: 1100px) {
    .mk-grafik-row-2 {
        grid-template-columns: minmax(0, 1fr);
    }

    .mk-peringkat {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
