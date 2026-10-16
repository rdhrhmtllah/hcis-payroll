<template>
    <HcisCard
        :title="`Penyelesaian per Departemen × ${heat.label_x || 'Prioritas Paket'}`"
        :kicker="kickerSatuan"
        :description="keterangan"
        icon="grid-3x3-gap-fill"
        sentiment="teal"
        variant="glass"
        pattern="crosshair"
        :loading="loading"
    >
        <!-- Slot Actions: Search, View Switcher & Page Controls -->
        <template #actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Pemilih sumbu X -->
                <select
                    v-model="sumbuX"
                    class="form-select form-select-sm rk-heat-sumbu"
                    title="Ganti sumbu kolom"
                    @change="halamanDept = 1; muat()"
                >
                    <option v-for="o in sumbuOpsi" :key="o.kunci" :value="o.kunci">
                        {{ o.label }}
                    </option>
                </select>

                <!-- Department Live Search Filter -->
                <div class="rk-heat-search">
                    <HcisField
                        id="rk-heat-dept-search"
                        v-model="cariDept"
                        placeholder="Cari Departemen..."
                        size="sm"
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
                allow-export
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
                    :current-page="halamanDept"
                    :total-items="filteredDepts.length"
                    :per-page="perHalamanDept"
                    variant="compact"
                    size="sm"
                    @page-change="halamanDept = $event"
                />
            </div>
        </template>
    </HcisCard>
</template>

<script>
import { HcisCard, HcisChart, HcisField, HcisIcon, HcisPagination } from '@/components/ui';
import { dataHeatmap, tinggiHeatmap } from '../grafikSeri.js';
import { ambil, formatLabelRentang } from '../agregat.js';

export default {
    name: 'PanelHeatmap',
    components: {
        HcisCard,
        HcisChart,
        HcisField,
        HcisIcon,
        HcisPagination,
    },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            loading: true,
            heat: { dept: [], kategori: [], seri: [], label_x: '', butiran: 'penugasan', total_dept: 0 },
            sumbuX: 'prioritas',
            sumbuOpsi: [{ kunci: 'prioritas', label: 'Prioritas Paket', butiran: 'penugasan' }],
            cariDept: '',
            halamanDept: 1,
            perHalamanDept: 10,
            tampilanHeat: 'chart',
        };
    },
    computed: {
        kickerSatuan() {
            return 'Matriks Kepatuhan';
        },
        keterangan() {
            const x = (this.heat.label_x || 'prioritas').toLowerCase();
            return `Tingkat penyelesaian dokumen per departemen dan ${x}.`;
        },
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
            const rows = this.departemenTerpilih.length;
            if (rows === 0) return 240;

            return Math.min(580, Math.max(220, rows * 44 + 90));
        },
        /* Dibentuk grafikSeri.js — dipakai bersama perakit laporan PDF.
           Daftar departemennya diserahkan dari sini karena layar memakainya
           BERHALAMAN sementara laporan memakai seluruhnya. */
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
        this.muatSumbu();
        this.muat();
    },
    watch: {
        saringan: {
            deep: true,
            handler() {
                this.muat();
            },
        },
    },
    methods: {
        async muatSumbu() {
            try {
                const opsi = await ambil('/analitik/heatmap-sumbu');
                if (Array.isArray(opsi) && opsi.length) this.sumbuOpsi = opsi;
            } catch (e) {
                // biarkan pilihan baku
            }
        },

        async muat() {
            this.loading = true;
            try {
                const res = await ambil('/analitik/heatmap', {
                    ...this.saringan,
                    x: this.sumbuX,
                    halaman: 1,
                    per_halaman: 40,
                });
                if (res && typeof res === 'object') {
                    this.heat = {
                        dept: Array.isArray(res.dept) ? res.dept : [],
                        kategori: Array.isArray(res.kategori) ? res.kategori : [],
                        seri: Array.isArray(res.seri) ? res.seri : [],
                        label_x: res.label_x || '',
                        butiran: res.butiran || 'penugasan',
                        total_dept: res.total_dept || 0,
                    };
                }
            } catch (e) {
                this.heat = { dept: [], kategori: [], seri: [], label_x: '', butiran: 'penugasan', total_dept: 0 };
            } finally {
                this.loading = false;
            }
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
    width: 1.85rem;
    height: 1.85rem;
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
    min-width: 0;
}

.rk-heat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    text-align: center;
    min-height: 180px;
}

.rk-heat-info {
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.rk-heat-table-wrap {
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: var(--hcis-white);
    overflow-x: auto;
}

.rk-heat-table th {
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: rgba(248, 250, 252, 0.95);
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.rk-heat-table td {
    padding: 0.55rem 0.75rem;
    font-size: 0.78125rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
}

.rk-heat-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 48px;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 6px);
    font-size: 0.71875rem;
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
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

.rk-heat-sumbu {
    width: auto;
    min-width: 10rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    padding: 0.35rem 0.65rem;
    background-color: var(--hcis-white);
}
</style>
