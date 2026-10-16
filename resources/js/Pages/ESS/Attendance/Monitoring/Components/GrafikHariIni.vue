<template>
    <div class="mk-hari-visual-grid">
        <!-- 1. Tren Check-in Jam ke Jam (Hourly Check-in Curve) -->
        <HcisChart
            type="bar"
            title="Tren Check-in Jam ke Jam"
            :description="deskripsiJam"
            icon="clock-history"
            sentiment="brand"
            variant="glass"
            :categories="dataJam.categories"
            :data="dataJam.values"
            :options="opsiJam"
            :height="290"
            value-label="Check-in"
            metric-unit="orang"
            :metric-value="totalCheckIn ? totalCheckIn.toLocaleString('id-ID') : '0'"
            metric-label="Total Masuk"
            :insight="puncakJam ? `Puncak kedatangan: pukul ${puncakJam} WIB` : ''"
            insight-label="Puncak Masuk"
            :allow-export="true"
            :loading="memuat"
            empty-text="Belum ada rekaman check-in pada tanggal ini"
        />

        <!-- 2. Komposisi Status & Pengajuan Hari Ini (Donut) -->
        <HcisChart
            type="donut"
            title="Komposisi Status & Transaksi"
            description="Sebaran kehadiran dan pengajuan yang berlaku hari ini"
            icon="pie-chart"
            sentiment="multi"
            variant="glass"
            :data="potonganStatus"
            :options="opsiDonut"
            :height="290"
            :metric-value="totalKaryawan ? totalKaryawan.toLocaleString('id-ID') : '0'"
            metric-label="Total Karyawan"
            :allow-export="true"
            :loading="memuat"
            empty-text="Belum ada data pada tanggal ini"
        />

        <!-- 3. Sebaran Kehadiran per Lokasi / Cabang -->
        <div v-if="sebaranLokasi.length > 0" class="mk-lokasi-summary-card">
            <HcisCard
                title="Kehadiran per Lokasi Kerja Hari Ini"
                description="Perbandingan tingkat kehadiran dan pengajuan per cabang"
                icon="geo-alt"
                sentiment="brand"
                variant="glass"
                divider
            >
                <div class="mk-lokasi-grid">
                    <div
                        v-for="lok in sebaranLokasi"
                        :key="lok.lokasi"
                        class="mk-lokasi-chip"
                    >
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-strong" style="font-size: 0.88rem;">{{ lok.lokasi }}</span>
                            <span class="font-tabular fw-bold" :class="`text-${sentimentPersen(lok.persen_hadir)}`">
                                {{ lok.persen_hadir }}%
                            </span>
                        </div>
                        <HcisProgress
                            :value="lok.persen_hadir"
                            :sentiment="sentimentPersen(lok.persen_hadir)"
                            size="sm"
                            :show-value="false"
                            class="mb-2"
                        />
                        <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.72rem;">
                            <span>Hadir: <b>{{ lok.hadir }} / {{ lok.terjadwal }}</b></span>
                            <span v-if="lok.izin_cuti_sakit > 0" class="text-warning">
                                <b>{{ lok.izin_cuti_sakit }}</b> izin/cuti
                            </span>
                            <span v-else><b>{{ lok.libur }}</b> off</span>
                        </div>
                    </div>
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import { HcisCard, HcisChart, HcisProgress } from '@/components/ui';

export default {
    name: 'GrafikHariIni',
    components: { HcisCard, HcisChart, HcisProgress },
    props: {
        ringkasan: { type: Object, default: () => ({ kategori: {} }) },
        analitik: { type: Object, default: () => ({}) },
        memuat: { type: Boolean, default: false },
    },
    computed: {
        totalKaryawan() {
            return Object.values(this.ringkasan?.kategori || {}).reduce((a, b) => a + (Number(b) || 0), 0);
        },
        totalCheckIn() {
            return (this.dataJam?.values || []).reduce((a, b) => a + (Number(b) || 0), 0);
        },
        dataJam() {
            const list = this.analitik?.tren_jam || [];
            if (!list.length) {
                return { categories: [], values: [], kumulatif: [] };
            }
            const values = list.map((item) => item.jumlah);
            const total = values.reduce((a, b) => a + (Number(b) || 0), 0);
            if (total === 0) {
                return { categories: [], values: [], kumulatif: [] };
            }
            return {
                categories: list.map((item) => item.jam),
                values,
                kumulatif: list.map((item) => item.kumulatif),
            };
        },
        deskripsiJam() {
            return 'Distribusi volume tap check-in karyawan per jam (04:00 – 23:00 WIB)';
        },
        puncakJam() {
            const list = this.analitik?.tren_jam || [];
            if (!list.length) return null;
            let max = 0;
            let jam = null;
            list.forEach((item) => {
                if (item.jumlah > max) {
                    max = item.jumlah;
                    jam = item.jam;
                }
            });
            return max > 0 ? jam : null;
        },
        potonganStatus() {
            const kat = this.ringkasan?.kategori || {};
            const tx = this.analitik?.transaksi || {};

            const item = [];
            const tambah = (name, value, color) => {
                if (value > 0) item.push({ name, value, itemStyle: { color } });
            };

            tambah('Hadir Tepat Waktu', kat.tepat_waktu ?? 0, '#10b981');
            tambah('Hadir Terlambat', kat.terlambat ?? 0, '#f59e0b');
            tambah('Day Off / Libur', kat.libur ?? 0, '#64748b');
            tambah('Cuti Tahunan', tx.cuti_tahunan ?? 0, '#8b5cf6');
            tambah('Cuti Khusus', tx.cuti_khusus ?? 0, '#a855f7');
            tambah('Sakit', tx.sakit ?? 0, '#ef4444');
            tambah('Izin', tx.izin ?? 0, '#0ea5e9');
            tambah('Belum Hadir / Absen', kat.belum_absen ?? 0, '#cbd5e1');

            return item;
        },
        sebaranLokasi() {
            return this.analitik?.sebaran_lokasi || [];
        },
        opsiJam() {
            return {
                grid: { top: 24, right: 16, bottom: 28, left: 14, containLabel: true },
                xAxis: {
                    axisLabel: {
                        interval: 1,
                        fontSize: 10.5,
                    },
                },
            };
        },
        opsiDonut() {
            return {
                legend: {
                    orient: 'horizontal',
                    left: 'center',
                    bottom: 4,
                    type: 'scroll',
                    itemWidth: 8,
                    itemHeight: 8,
                    icon: 'circle',
                    textStyle: { fontSize: 11, fontWeight: 500, color: '#64748b' },
                },
                series: [
                    {
                        cursor: 'default',
                        avoidLabelOverlap: true,
                        itemStyle: {
                            borderRadius: 6,
                            borderWidth: 2,
                            borderColor: '#ffffff',
                        },
                    },
                ],
            };
        },
    },
    methods: {
        sentimentPersen(persen) {
            if (persen >= 90) return 'success';
            if (persen >= 75) return 'brand';
            if (persen >= 50) return 'warning';
            return 'danger';
        },
    },
};
</script>

<style scoped>
.mk-hari-visual-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: var(--hcis-space-4, 1rem);
}

.mk-lokasi-summary-card {
    grid-column: 1 / -1;
}

.mk-lokasi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.75rem;
}

.mk-lokasi-chip {
    display: flex;
    flex-direction: column;
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-ground, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
}

@media (max-width: 992px) {
    .mk-hari-visual-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
