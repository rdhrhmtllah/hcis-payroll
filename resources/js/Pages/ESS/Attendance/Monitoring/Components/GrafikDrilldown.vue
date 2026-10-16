<template>
    <div class="mk-drilldown">
        <HcisCard
            title="Eksplorasi Hierarki Kehadiran"
            :description="`Struktur organisasi berjenjang (Lokasi → Divisi → Departemen) · ${labelCakupanAktif}`"
            icon="diagram-3"
            sentiment="brand"
            variant="glass"
            divider
        >
            <template #actions>
                <div class="mk-mode-tabs">
                    <HcisTabs
                        v-model="modeVisual"
                        :tabs="TAB_MODE"
                        variant="pills"
                        size="sm"
                        aria-label="Pilihan visualisasi drill-down"
                    />
                </div>
            </template>

            <!-- Navigasi Breadcrumb Resmi & Metrik Switcher -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        v-if="pathNavigasi.length > 0"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-left"
                        label="Kembali"
                        title="Naik satu tingkat"
                        @click="mundurSatuTingkat"
                    />
                    <HcisBreadcrumb :items="breadcrumbItems" variant="pills" size="sm" separator="chevron">
                        <template v-for="(b, idx) in breadcrumbItems" :key="idx" #[`item-${idx}`]>
                            <button
                                type="button"
                                class="mk-breadcrumb-link"
                                :class="{ 'is-active': idx === breadcrumbItems.length - 1 }"
                                @click="keTingkat(idx)"
                            >
                                <HcisIcon v-if="b.icon" :name="b.icon" size="xs" class="me-1" />
                                {{ b.label }}
                            </button>
                        </template>
                    </HcisBreadcrumb>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <HcisTabs
                        v-model="metrik"
                        :tabs="TAB_METRIK"
                        variant="pills"
                        size="sm"
                        aria-label="Pilihan metrik"
                    />
                </div>
            </div>

            <!-- Signature Executive Stat Cards Group (HcisStatGroup resmi) -->
            <HcisStatGroup
                :stats="statCardsCakupan"
                :cols="4"
                gap="sm"
                appearance="crystal"
                layout="compact"
                class="mb-3"
            />

            <!-- Area Visualisasi (3 Mode) -->
            <div class="mk-chart-area">
                <!-- Mode A: Batang Komparasi + Tabel Sub-unit -->
                <div v-if="modeVisual === 'batang'" class="mk-batang-container">
                    <template v-if="anakUnits.length > 0">
                        <div class="mk-chart-caption mb-2">
                            <span class="mk-muted">
                                <HcisIcon name="info-circle" size="xs" class="me-1" />
                                Menampilkan perbandingan sub-unit di dalam <strong>{{ labelCakupanAktif }}</strong>. Klik batang atau tombol untuk masuk.
                            </span>
                        </div>
                        <HcisChart
                            type="custom"
                            :card="false"
                            :options="opsiBatang"
                            :height="tinggiBatang"
                            :loading="memuat"
                            data-uji="chart-drilldown-batang"
                            @chart-click="klikBatang"
                        />

                        <!-- Kartu Interaktif Sub-unit (Non-Table) -->
                        <div class="mk-sub-units-grid mt-3">
                            <button
                                v-for="unit in anakUnits"
                                :key="unit.id"
                                type="button"
                                class="mk-unit-card"
                                @click="masukKeAnak(unit)"
                            >
                                <div class="mk-unit-card__head">
                                    <span class="mk-unit-card__title">{{ unit.name }}</span>
                                    <HcisIcon name="chevron-right" size="xs" class="mk-unit-card__chevron" />
                                </div>
                                <div class="mk-unit-card__body">
                                    <div class="d-flex align-items-center gap-2">
                                        <HcisProgress
                                            :value="unit.persen_hadir"
                                            :sentiment="sentimentPersen(unit.persen_hadir)"
                                            size="sm"
                                            :show-value="false"
                                            style="width: 50px;"
                                        />
                                        <span class="font-tabular fw-bold" style="font-size: 0.85rem;">{{ unit.persen_hadir }}%</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="mk-muted" style="font-size: 0.72rem;">{{ unit.karyawan_count }} org</span>
                                        <HcisBadge v-if="unit.terlambat_kali > 0" :label="`${unit.terlambat_kali}× telat`" variant="warning" size="2xs" />
                                        <HcisBadge v-if="unit.alpha > 0" :label="`${unit.alpha} alpha`" variant="danger" size="2xs" />
                                    </div>
                                </div>
                            </button>
                        </div>
                    </template>
                    <div v-else class="mk-unit-ujung-note">
                        <HcisIcon name="check2-circle" size="sm" class="text-success me-2" />
                        <span>Tingkat terdalam (departemen) telah dicapai. Roster karyawan tersaji di bawah.</span>
                    </div>
                </div>

                <!-- Mode B: Treemap (Kotak Bersarang) -->
                <div v-else-if="modeVisual === 'treemap'" class="mk-treemap-container">
                    <div class="mk-chart-caption mb-2">
                        <span class="mk-muted">
                            <HcisIcon name="hand-index-thumb" size="xs" class="me-1" />
                            Ukuran kotak mencerminkan jumlah karyawan/terjadwal. Klik kotak untuk zoom masuk per tingkat.
                        </span>
                    </div>
                    <HcisChart
                        type="custom"
                        :card="false"
                        :options="opsiTreemap"
                        :height="360"
                        :loading="memuat"
                        data-uji="chart-drilldown-treemap"
                        @chart-click="klikTreemap"
                    />
                </div>

                <!-- Mode C: Sunburst (Cincin Bertingkat) -->
                <div v-else-if="modeVisual === 'sunburst'" class="mk-sunburst-container">
                    <div class="mk-chart-caption mb-2">
                        <span class="mk-muted">
                            <HcisIcon name="hand-index-thumb" size="xs" class="me-1" />
                            Lingkaran konsentris (Lokasi → Divisi → Departemen). Klik cincin untuk fokus; klik lingkaran tengah untuk kembali.
                        </span>
                    </div>
                    <HcisChart
                        type="custom"
                        :card="false"
                        :options="opsiSunburst"
                        :height="400"
                        :loading="memuat"
                        data-uji="chart-drilldown-sunburst"
                        @chart-click="klikSunburst"
                    />
                </div>
            </div>

            <!-- Roster Karyawan pada Cakupan Aktif Saat Ini (Non-Table) -->
            <div v-if="daftarKaryawanCakupan.length > 0" class="mk-employee-section mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <span class="fw-bold text-strong" style="font-size: 0.88rem;">
                        <HcisIcon name="people-fill" size="xs" class="text-primary me-1" />
                        Roster Karyawan di {{ labelCakupanAktif }}
                        <span class="mk-muted fw-normal">({{ daftarKaryawanCakupan.length }} orang)</span>
                    </span>
                    <span class="mk-muted" style="font-size: 0.75rem;">Klik kartu untuk rincian harian</span>
                </div>

                <div class="mk-employee-grid">
                    <button
                        v-for="row in daftarKaryawanCakupan"
                        :key="row.kode"
                        type="button"
                        class="mk-employee-card"
                        @click="$emit('pilih-karyawan', row)"
                    >
                        <div class="mk-employee-card__main">
                            <div class="mk-employee-card__avatar">
                                <HcisIcon name="person" size="xs" />
                            </div>
                            <div class="mk-employee-card__info">
                                <span class="mk-employee-card__name">{{ row.nama }}</span>
                                <span class="mk-muted" style="font-size: 0.68rem;">{{ row.kode }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></span>
                            </div>
                        </div>
                        <div class="mk-employee-card__badge">
                            <HcisBadge
                                v-if="row.alpha > 0"
                                :label="`${row.alpha} alpha`"
                                variant="danger"
                                size="2xs"
                                dot
                            />
                            <HcisBadge
                                v-else-if="row.terlambat_kali > 0"
                                :label="`${row.terlambat_kali}× telat`"
                                variant="warning"
                                size="2xs"
                            />
                            <HcisBadge
                                v-else
                                :label="`${row.hadir}/${row.terjadwal}`"
                                variant="success"
                                size="2xs"
                                appearance="subtle"
                            />
                        </div>
                    </button>
                </div>
            </div>
        </HcisCard>
    </div>
</template>

<script>
import {
    HcisBadge,
    HcisBreadcrumb,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisIcon,
    HcisProgress,
    HcisStatGroup,
    HcisTabs,
} from '@/components/ui';
import {
    METRIK_DRILLDOWN,
    opsiBatangDrilldown,
    opsiSunburstDrilldown,
    opsiTreemapDrilldown,
    temukanNode,
} from './grafikDrilldown.js';

const TAB_MODE = [
    { key: 'batang', label: 'Batang Komparasi', icon: 'bar-chart-steps' },
    { key: 'treemap', label: 'Treemap (Kotak)', icon: 'grid-fill' },
    { key: 'sunburst', label: 'Sunburst (Cincin)', icon: 'pie-chart-fill' },
];

const TAB_METRIK = Object.values(METRIK_DRILLDOWN).map((m) => ({ key: m.key, label: m.label }));

export default {
    name: 'GrafikDrilldown',
    components: {
        HcisBadge,
        HcisBreadcrumb,
        HcisButton,
        HcisCard,
        HcisChart,
        HcisIcon,
        HcisProgress,
        HcisStatGroup,
        HcisTabs,
    },
    props: {
        hierarki: { type: Object, default: () => ({}) },
        memuat: { type: Boolean, default: false },
    },
    emits: ['pilih-karyawan'],
    data() {
        return {
            TAB_MODE,
            TAB_METRIK,
            modeVisual: 'batang',
            metrik: 'persen_hadir',
            pathNavigasi: [],
        };
    },
    computed: {
        nodeAktif() {
            return temukanNode(this.hierarki, this.pathNavigasi);
        },
        labelCakupanAktif() {
            if (!this.pathNavigasi.length) return 'Semua Lokasi';
            return this.pathNavigasi.join(' › ');
        },
        breadcrumbItems() {
            const items = [{ label: 'Semua Lokasi', key: 'root', icon: 'buildings' }];
            const IKON_TINGKAT = ['geo-alt', 'diagram-3', 'diagram-2'];
            this.pathNavigasi.forEach((nama, i) => {
                items.push({
                    label: nama,
                    key: `step-${i}`,
                    icon: IKON_TINGKAT[Math.min(i, IKON_TINGKAT.length - 1)],
                });
            });
            return items;
        },
        statCardsCakupan() {
            const n = this.nodeAktif || {};
            const persen = n.persen_hadir ?? 0;
            return [
                {
                    label: 'Total Karyawan',
                    value: `${(n.karyawan_count ?? 0).toLocaleString('id-ID')}`,
                    suffix: 'orang',
                    caption: this.labelCakupanAktif,
                    icon: 'people-fill',
                    sentiment: 'brand',
                },
                {
                    label: 'Tingkat Kehadiran',
                    value: `${persen}%`,
                    caption: `${(n.hadir ?? 0).toLocaleString('id-ID')} dari ${(n.terjadwal ?? 0).toLocaleString('id-ID')} terjadwal`,
                    icon: 'check2-circle',
                    sentiment: this.sentimentPersen(persen),
                },
                {
                    label: 'Keterlambatan',
                    value: `${(n.terlambat_kali ?? 0).toLocaleString('id-ID')}×`,
                    caption: `Total ${(n.terlambat_menit ?? 0).toLocaleString('id-ID')} menit`,
                    icon: 'alarm-fill',
                    sentiment: (n.terlambat_kali ?? 0) > 0 ? 'warning' : 'neutral',
                },
                {
                    label: 'Alpha & Lembur',
                    value: `${(n.alpha ?? 0).toLocaleString('id-ID')} hari`,
                    caption: `Lembur ${(n.lembur_jam ?? 0).toLocaleString('id-ID')} jam`,
                    icon: (n.alpha ?? 0) > 0 ? 'exclamation-octagon-fill' : 'clock-history',
                    sentiment: (n.alpha ?? 0) > 0 ? 'danger' : 'neutral',
                },
            ];
        },
        anakUnits() {
            const n = this.nodeAktif;
            return (n && Array.isArray(n.children)) ? n.children : [];
        },
        opsiBatang() {
            return opsiBatangDrilldown(this.nodeAktif, this.metrik);
        },
        tinggiBatang() {
            const count = this.anakUnits.length || 1;
            return Math.max(180, Math.min(480, 50 + count * 36));
        },
        opsiTreemap() {
            return opsiTreemapDrilldown(this.hierarki, this.metrik);
        },
        opsiSunburst() {
            return opsiSunburstDrilldown(this.hierarki, this.metrik);
        },
        daftarKaryawanCakupan() {
            const node = this.nodeAktif;
            if (!node) return [];

            if (Array.isArray(node.karyawan) && node.karyawan.length > 0) {
                return node.karyawan;
            }

            const hasil = [];
            const kumpulkan = (n) => {
                if (Array.isArray(n.karyawan) && n.karyawan.length > 0) {
                    hasil.push(...n.karyawan);
                }
                if (Array.isArray(n.children)) {
                    n.children.forEach(kumpulkan);
                }
            };
            kumpulkan(node);

            return hasil.slice(0, 50);
        },
    },
    methods: {
        sentimentPersen(persen) {
            if (persen >= 90) return 'success';
            if (persen >= 75) return 'brand';
            if (persen >= 50) return 'warning';
            return 'danger';
        },
        mundurSatuTingkat() {
            if (this.pathNavigasi.length > 0) {
                this.pathNavigasi.pop();
            }
        },
        keTingkat(idx) {
            if (idx === 0) {
                this.pathNavigasi = [];
            } else {
                this.pathNavigasi = this.pathNavigasi.slice(0, idx);
            }
        },
        masukKeAnak(anak) {
            if (anak && anak.name) {
                this.pathNavigasi.push(anak.name);
            }
        },
        klikBatang(p) {
            const nodes = this.opsiBatang?._rawNodes;
            const clickedNode = nodes ? nodes[p?.dataIndex] : null;
            if (clickedNode && clickedNode.name) {
                this.pathNavigasi.push(clickedNode.name);
            }
        },
        klikTreemap(p) {
            const rawNode = p?.data?.nodeData;
            if (rawNode) {
                this.sinkronPathDariNode(rawNode);
            }
        },
        klikSunburst(p) {
            const rawNode = p?.data?.nodeData;
            if (rawNode) {
                this.sinkronPathDariNode(rawNode);
            }
        },
        sinkronPathDariNode(node) {
            const path = [];
            if (node.lokasi) path.push(node.lokasi);
            if (node.divisi) path.push(node.divisi);
            if (node.departemen) path.push(node.departemen);
            if (path.length > 0) {
                this.pathNavigasi = path;
            }
        },
    },
};
</script>

<style scoped>
.mk-drilldown {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.mk-breadcrumb-link {
    border: none;
    background: transparent;
    padding: 0;
    color: inherit;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}

.mk-breadcrumb-link:hover {
    color: var(--hcis-brand-600, #4f46e5);
}

.mk-breadcrumb-link.is-active {
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 700;
    cursor: default;
}

.mk-chart-caption {
    font-size: 0.78rem;
    line-height: 1.4;
}

.mk-unit-ujung-note {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-ground, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #0f172a);
}

.mk-muted {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.mk-sub-units-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.65rem;
}

.mk-unit-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 0.85rem;
    background: var(--hcis-surface-card, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.mk-unit-card:hover {
    border-color: var(--hcis-brand-500, #4f46e5);
    background: var(--hcis-surface-ground, #f8fafc);
}

.mk-unit-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.mk-unit-card__title {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mk-unit-card__chevron {
    color: var(--hcis-text-muted, #94a3b8);
    flex-shrink: 0;
}

.mk-unit-card__body {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.mk-employee-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 0.6rem;
}

.mk-employee-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    background: var(--hcis-surface-card, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    text-align: left;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.mk-employee-card:hover {
    border-color: var(--hcis-brand-500, #4f46e5);
    background: var(--hcis-surface-ground, #f8fafc);
}

.mk-employee-card__main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.mk-employee-card__avatar {
    width: 28px;
    height: 28px;
    border-radius: 9999px;
    background: var(--hcis-surface-ground, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, #64748b);
    flex-shrink: 0;
}

.mk-employee-card__info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.mk-employee-card__name {
    font-size: 0.82rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mk-employee-card__badge {
    flex-shrink: 0;
}
</style>
