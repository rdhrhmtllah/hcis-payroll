<template>
    <div class="sop-stack zona-tren">
        <!-- 1. Tren Kepatuhan Dokumen (Dari Waktu ke Waktu) -->
        <HcisChart
            type="area"
            kicker="Dari Waktu ke Waktu"
            title="Tren Kepatuhan Dokumen"
            description="Tingkat penyelesaian kumulatif (%) dengan volume peristiwa harian sebagai latar."
            icon="graph-up-arrow"
            sentiment="brand"
            variant="glass"
            pattern="grid"
            :height="360"
            :time-filters="rentang"
            :model-value-filter="hari"
            allow-table-toggle
            allow-export
            :loading="loading.tren"
            :empty-text="tren.cukup_data ? '' : 'Riwayat kepatuhan belum cukup untuk digambar'"
            :insight="catatanTren"
            :categories="kategoriTren"
            :series="seriTren"
            :options="opsiTren"
            :tooltip-formatter="formatTooltipTren"
            @filter-change="pilihRentang"
        />

        <!-- 2. Distribusi Waktu Selesai (Perilaku Baca) -->
        <HcisChart
            type="bar-horizontal"
            kicker="Perilaku Baca"
            title="Distribusi Waktu Selesai"
            description="Jarak durasi antara dokumen/materi dibuka hingga dikonfirmasi oleh karyawan."
            icon="hourglass-split"
            sentiment="violet"
            variant="glass"
            pattern="dots"
            :height="300"
            allow-table-toggle
            allow-export
            :loading="loading.waktu"
            :metric-value="waktu.total"
            metric-unit="Penugasan"
            metric-label="Total Konfirmasi Tuntas"
            :insight="catatanWaktu"
            :categories="kategoriWaktu"
            :series="seriWaktu"
            :tooltip-formatter="formatTooltipWaktu"
        />

        <!-- 3. Penyelesaian per Departemen × Kategori (Sebaran Kepatuhan) -->
        <HcisCard
            title="Penyelesaian per Departemen × Kategori"
            kicker="Sebaran Kepatuhan"
            description="Matriks kepatuhan dokumen per departemen dan kategori materi penugasan (sel kosong = belum ada penugasan)."
            icon="grid-3x3-gap-fill"
            sentiment="teal"
            variant="glass"
            pattern="crosshair"
            :loading="loading.heat"
        >
            <!-- Slot Actions: Search, View Switcher & Page Controls -->
            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Department Live Search Filter -->
                    <div class="rk-heat-search">
                        <HcisField
                            id="rk-heat-dept-search"
                            v-model="cariDept"
                            placeholder="Cari Departemen..."
                            size="xs"
                            clearable
                            @update:model-value="halamanDept = 1"
                        >
                            <template #prefix>
                                <HcisIcon name="search" size="2xs" class="text-muted" />
                            </template>
                        </HcisField>
                    </div>

                    <!-- View Switcher (Heatmap vs Table) -->
                    <div class="hcis-chart__view-toggle">
                        <button
                            type="button"
                            class="hcis-chart__tool-btn"
                            :class="{ 'hcis-chart__tool-btn--active': tampilanHeat === 'chart' }"
                            title="Tampilan Matriks Visual (Heatmap)"
                            @click="tampilanHeat = 'chart'"
                        >
                            <HcisIcon name="grid-3x3-gap-fill" size="xs" />
                        </button>
                        <button
                            type="button"
                            class="hcis-chart__tool-btn"
                            :class="{ 'hcis-chart__tool-btn--active': tampilanHeat === 'table' }"
                            title="Tampilan Tabel Matriks"
                            @click="tampilanHeat = 'table'"
                        >
                            <HcisIcon name="table" size="xs" />
                        </button>
                    </div>
                </div>
            </template>

            <!-- State 1: Kosong karena pencarian -->
            <div v-if="filteredDepts.length === 0" class="rk-heat-empty">
                <HcisIcon name="search" size="md" class="text-muted mb-2" />
                <p class="mb-0 text-muted fw-semibold">
                    Tidak ada departemen yang sesuai dengan kata kunci "<strong>{{ cariDept }}</strong>"
                </p>
            </div>

            <!-- State 2: Visual Heatmap Mode -->
            <div v-else-if="tampilanHeat === 'chart'" class="rk-heat-chart-wrap">
                <HcisChart
                    :card="false"
                    type="heatmap"
                    :height="tinggiHeatmap"
                    :categories="heat.kategori || []"
                    :series="[{ categories: departemenTerpilih }]"
                    :data="dataHeatmap"
                    :tooltip-formatter="formatTooltipHeatmap"
                    :options="opsiHeatmap"
                />
            </div>

            <!-- State 3: Detail Matrix Table Mode -->
            <div v-else class="table-responsive rk-heat-table-wrap">
                <table class="table table-sm table-hover align-middle rk-heat-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="min-width: 180px;">Departemen</th>
                            <th
                                v-for="k in heat.kategori || []"
                                :key="k"
                                class="text-center"
                                style="min-width: 110px;"
                            >
                                {{ k }}
                            </th>
                            <th class="text-center pe-3" style="min-width: 100px;">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dept in departemenTerpilih" :key="dept">
                            <td class="ps-3 fw-bold text-dark">{{ dept }}</td>
                            <td
                                v-for="k in heat.kategori || []"
                                :key="k"
                                class="text-center"
                            >
                                <span
                                    class="rk-heat-pill"
                                    :class="kelasSentimen(ambilNilaiHeat(dept, k))"
                                >
                                    {{ ambilTeksHeat(dept, k) }}
                                </span>
                            </td>
                            <td class="text-center pe-3">
                                <span
                                    class="rk-heat-pill fw-bold"
                                    :class="kelasSentimen(hitungRataRataDept(dept))"
                                >
                                    {{ hitungRataRataDept(dept) !== null ? hitungRataRataDept(dept) + '%' : '—' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer: Pagination & Sub-summary Info -->
            <template #footer>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                    <span class="rk-heat-info">
                        Menampilkan <strong>{{ (halamanDept - 1) * perHalamanDept + 1 }}–{{ Math.min(halamanDept * perHalamanDept, filteredDepts.length) }}</strong> dari <strong>{{ filteredDepts.length }}</strong> departemen
                        <span v-if="cariDept" class="text-muted">(difilter dari {{ (heat.dept || []).length }})</span>
                    </span>

                    <HcisPagination
                        v-if="filteredDepts.length > perHalamanDept"
                        :total="filteredDepts.length"
                        :page-size="perHalamanDept"
                        :current-page="halamanDept"
                        size="sm"
                        @update:current-page="halamanDept = $event"
                    />
                </div>
            </template>
        </HcisCard>
    </div>
</template>

<script>
import axios from 'axios';
import { HcisCard, HcisChart, HcisField, HcisPagination, HcisIcon } from '@/components/ui';
import {
    dataHeatmap,
    kategoriTren,
    kategoriWaktu,
    opsiTren,
    seriTren,
    seriWaktu,
    tinggiHeatmap,
} from './grafikSeri.js';

const API = '/api/v1/sop/admin/monitoring';

/**
 * Zona Tren — Analisis Kepatuhan & Tren Historis Dokumen
 * Menggunakan HcisChart (Apache ECharts) untuk visualisasi yang tajam, rapi, dan responsif.
 */
export default {
    name: 'ZonaTren',
    components: { HcisCard, HcisChart, HcisField, HcisPagination, HcisIcon },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            hari: 90,
            rentang: [
                { label: '30 Hari', value: 30 },
                { label: '90 Hari', value: 90 },
                { label: '1 Tahun', value: 365 },
            ],
            tren: { titik: [], cukup_data: false, jumlah_peristiwa: 0, minimum: 10, mulai_ledger: null },
            waktu: { kelompok: [], total: 0 },
            heat: { dept: [], kategori: [], seri: [], kosong: true },
            loading: { tren: false, waktu: false, heat: false },

            // Heatmap Controls: Search, Pagination & View Mode
            cariDept: '',
            halamanDept: 1,
            perHalamanDept: 8,
            tampilanHeat: 'chart',
        };
    },
    computed: {
        /* ---------- 1. TREN KEPATUHAN ---------- */
        /* SERI GRAFIK DIBENTUK DI grafikSeri.js — satu berkas yang juga
           dibaca perakit laporan PDF. Dulu keduanya membentuk seri sendiri, dan
           laporan pelan-pelan menggambarkan hal yang berbeda dari layarnya. */
        kategoriTren() {
            return kategoriTren(this.tren);
        },
        seriTren() {
            return seriTren(this.tren);
        },
        opsiTren() {
            return opsiTren(this.tren);
        },

        catatanTren() {
            if (!this.tren.cukup_data) {
                const n = this.tren.jumlah_peristiwa || 0;
                return `Baru ${n} peristiwa tercatat di rentang ini; minimal ${this.tren.minimum || 10} agar garis tren bermakna.`;
            }
            if (this.tren.mulai_ledger) {
                return `Riwayat kepatuhan dihitung berdasarkan log aktivitas sejak ${this.tren.mulai_ledger}.`;
            }
            return '';
        },

        /* ---------- 2. PERILAKU BACA (DISTRIBUSI WAKTU) ---------- */
        kategoriWaktu() {
            return kategoriWaktu(this.waktu);
        },
        seriWaktu() {
            return seriWaktu(this.waktu);
        },

        catatanWaktu() {
            const cepat = (this.waktu.kelompok || []).find((k) => k.kilat);
            if (!cepat || !cepat.jumlah) return '';
            return `${cepat.jumlah} penugasan diselesaikan sangat cepat (< 1 jam sejak dibuka).`;
        },

        /* ---------- 3. HEATMAP DEPARTEMEN X KATEGORI ---------- */
        filteredDepts() {
            const all = this.heat.dept || [];
            if (!this.cariDept) return all;
            const q = this.cariDept.toLowerCase().trim();
            return all.filter((d) => d.toLowerCase().includes(q));
        },
        departemenTerpilih() {
            if (this.perHalamanDept === 'all') return this.filteredDepts;
            const start = (this.halamanDept - 1) * this.perHalamanDept;
            return this.filteredDepts.slice(start, start + this.perHalamanDept);
        },
        tinggiHeatmap() {
            return tinggiHeatmap(this.departemenTerpilih.length);
        },
        dataHeatmap() {
            return dataHeatmap(this.heat, this.departemenTerpilih);
        },
        opsiHeatmap() {
            const kats = this.heat.kategori || [];
            return {
                grid: {
                    top: 20,
                    right: 20,
                    bottom: 56,
                    left: 20,
                    containLabel: true,
                },
                dataZoom: kats.length > 7 ? [
                    {
                        type: 'inside',
                        xAxisIndex: 0,
                        zoomOnMouseWheel: false,
                        moveOnMouseMove: true,
                        moveOnMouseWheel: true,
                    },
                    {
                        type: 'slider',
                        xAxisIndex: 0,
                        bottom: 30,
                        height: 12,
                        show: true,
                        borderColor: 'transparent',
                        backgroundColor: 'rgba(241, 245, 249, 0.6)',
                        fillerColor: 'rgba(20, 184, 166, 0.2)',
                    },
                ] : undefined,
                visualMap: {
                    min: 0,
                    max: 100,
                    calculable: false,
                    orient: 'horizontal',
                    left: 'center',
                    bottom: 4,
                    itemWidth: 12,
                    itemHeight: 120,
                    inRange: {
                        color: ['#fee2e2', '#fef3c7', '#d1fae5', '#10b981'],
                    },
                    outOfRange: {
                        color: ['#f8fafc'],
                    },
                    text: ['100% Selesai', '0%'],
                    textStyle: {
                        color: '#64748b',
                        fontSize: 11,
                        fontWeight: 600,
                    },
                },
            };
        },
    },
    mounted() {
        this.muatSemua();
    },
    watch: {
        saringan: {
            deep: true,
            handler() {
                this.muatSemua();
            },
        },
    },
    methods: {
        async muatSemua() {
            await Promise.allSettled([this.fetchTren(), this.fetchWaktu(), this.fetchHeat()]);
        },
        pilihRentang(h) {
            this.hari = h;
            this.fetchTren();
        },
        async fetchTren() {
            this.loading.tren = true;
            try {
                const s = this.saringan || {};
                const dari = s.date_start || s.dari || new Date(Date.now() - this.hari * 86400000).toISOString().slice(0, 10);
                const sampai = s.date_end || s.sampai || undefined;
                const r = await axios.get(`${API}/analitik/tren`, {
                    params: { dari, sampai, granular: this.hari > 120 ? 'week' : 'day', ...this.saringan },
                });
                if (r.data.success) this.tren = r.data.result;
            } catch (e) {
                /* silent */
            } finally {
                this.loading.tren = false;
            }
        },
        async fetchWaktu() {
            this.loading.waktu = true;
            try {
                const r = await axios.get(`${API}/analitik/waktu-selesai`, { params: { ...this.saringan } });
                if (r.data.success) this.waktu = r.data.result;
            } catch (e) {
                /* silent */
            } finally {
                this.loading.waktu = false;
            }
        },
        async fetchHeat() {
            this.loading.heat = true;
            try {
                const r = await axios.get(`${API}/analitik/heatmap`, { params: { ...this.saringan } });
                if (r.data.success) this.heat = r.data.result;
            } catch (e) {
                /* silent */
            } finally {
                this.loading.heat = false;
            }
        },
        formatTooltipTren(params) {
            if (!Array.isArray(params) || params.length === 0) return '';
            const tgl = params[0].axisValueLabel || params[0].name;
            let html = `<div style="font-weight: 750; margin-bottom: 6px; color: #0f172a;">📅 ${tgl}</div>`;
            params.forEach((item) => {
                const isPersen = item.seriesName.includes('%') || item.seriesName.includes('Kepatuhan');
                const unit = isPersen ? '%' : ' Log';
                const val = typeof item.value === 'number' ? item.value.toLocaleString('id-ID') : item.value;
                const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${item.color};"></span>`;
                html += `<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 3px; font-size: 11.5px;">
                    <span style="color: #64748b;">${dot}${item.seriesName}</span>
                    <strong style="color: #0f172a; font-family: monospace;">${val}${unit}</strong>
                </div>`;
            });
            return html;
        },
        formatTooltipWaktu(params) {
            if (!Array.isArray(params) || params.length === 0) return '';
            const item = params[0];
            const k = (this.waktu.kelompok || []).find((x) => x.label === item.name);
            const isKilat = k?.kilat;
            const badge = isKilat
                ? '<span style="display: inline-block; padding: 1px 6px; border-radius: 999px; background: #ffe4e6; color: #e11d48; font-size: 10px; font-weight: 700; margin-left: 6px;">Konfirmasi Kilat</span>'
                : '';

            return `<div style="font-weight: 750; margin-bottom: 4px; color: #0f172a;">
                ⏱️ Rentang Durasi: <strong>${item.name}</strong> ${badge}
            </div>
            <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 16px;">
                <span style="color: #64748b;">Penugasan Selesai:</span>
                <strong style="color: #0f172a; font-family: monospace;">${item.value.toLocaleString('id-ID')} Penugasan</strong>
            </div>`;
        },
        formatTooltipHeatmap(p) {
            const d = p.data;
            if (!d || !Array.isArray(d)) return '';
            const xLabel = (this.heat.kategori || [])[d[0]] || d[0];
            const yLabel = (this.departemenTerpilih || [])[d[1]] || d[1];
            const val = d[2];
            const target = d[3] || 0;
            const isKosong = val === '-' || val === null || val === undefined;

            return `<div style="font-weight: 750; margin-bottom: 5px; color: #0f172a;">
                🏢 ${yLabel} <span style="color: #94a3b8; font-weight: 400;">×</span> 📁 ${xLabel}
            </div>
            <div style="font-size: 11.5px; display: flex; flex-direction: column; gap: 4px;">
                <div style="display: flex; justify-content: space-between; gap: 16px;">
                    <span style="color: #64748b;">Tingkat Kepatuhan:</span>
                    <strong style="color: ${isKosong ? '#94a3b8' : '#059669'}; font-family: monospace;">${isKosong ? 'Belum Ada Penugasan' : val + '%'}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; gap: 16px;">
                    <span style="color: #64748b;">Total Penugasan:</span>
                    <strong style="color: #0f172a; font-family: monospace;">${target} Penugasan</strong>
                </div>
            </div>`;
        },
        ambilNilaiHeat(deptName, katName) {
            const s = (this.heat.seri || []).find((x) => x.name === deptName);
            if (!s) return null;
            const item = (s.data || []).find((x) => x.x === katName);
            return item?.y !== null && item?.y !== undefined ? Number(item.y) : null;
        },
        ambilTeksHeat(deptName, katName) {
            const val = this.ambilNilaiHeat(deptName, katName);
            return val !== null ? `${val}%` : '—';
        },
        hitungRataRataDept(deptName) {
            const s = (this.heat.seri || []).find((x) => x.name === deptName);
            if (!s || !s.data || s.data.length === 0) return null;
            const nilaiValid = s.data.filter((x) => x.y !== null && x.y !== undefined).map((x) => Number(x.y));
            if (nilaiValid.length === 0) return null;
            const total = nilaiValid.reduce((a, b) => a + b, 0);
            return Math.round(total / nilaiValid.length);
        },
        kelasSentimen(val) {
            if (val === null || val === undefined) return 'rk-heat-pill--neutral';
            if (val >= 80) return 'rk-heat-pill--success';
            if (val >= 50) return 'rk-heat-pill--warning';
            return 'rk-heat-pill--danger';
        },
    },
};
</script>

<style scoped>
.zona-tren {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

/* ---------- Heatmap Card Extensions ---------- */
.rk-heat-search {
    width: 190px;
}
@media (max-width: 576px) {
    .rk-heat-search {
        width: 100%;
    }
}

.hcis-chart__view-toggle {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 2px;
    gap: 2px;
}

.hcis-chart__tool-btn {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 140ms ease;
}

.hcis-chart__tool-btn:hover {
    background: rgba(255, 255, 255, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-chart__tool-btn--active {
    background: var(--hcis-white) !important;
    color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.rk-heat-chart-wrap {
    width: 100%;
}

.rk-heat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--hcis-space-6) var(--hcis-space-4);
    text-align: center;
    min-height: 180px;
}

.rk-heat-info {
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
}

/* Matrix Table View */
.rk-heat-table-wrap {
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-subtle);
    background: var(--hcis-surface);
}

.rk-heat-table th {
    font-size: var(--hcis-font-size-2xs);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-subtle);
    background: var(--hcis-slate-50);
    padding: var(--hcis-space-2) var(--hcis-space-3);
    border-bottom: 1px solid var(--hcis-border-medium);
}

.rk-heat-table td {
    padding: var(--hcis-space-2) var(--hcis-space-3);
    font-size: var(--hcis-font-size-xs);
    border-bottom: 1px solid var(--hcis-border-subtle);
}

.rk-heat-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    padding: 0.18rem 0.5rem;
    border-radius: var(--hcis-radius-sm);
    font-size: 0.71875rem;
    font-weight: 750;
    line-height: 1.2;
}

.rk-heat-pill--success {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-800);
}

.rk-heat-pill--warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}

.rk-heat-pill--danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.rk-heat-pill--neutral {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
}
</style>
