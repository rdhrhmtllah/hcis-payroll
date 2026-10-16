<template>
    <div class="container-fluid px-0 sop-scope mp-page">
        <!-- ══ 1. HEADER EKSEKUTIF MONITORING PAKET DOKUMEN ══ -->
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="lg"
            :title="detail?.paket?.Nama_Paket || 'Detail Monitoring Paket Dokumen'"
            :subtitle="barisSubjudul"
            kicker="MONITORING DISTRIBUSI & AUDIT DOKUMEN"
            icon="collection-play-fill"
            class="mb-3 mp-header"
        >
            <template #breadcrumb>
                <HcisBreadcrumb :items="breadcrumb" size="sm" />
            </template>

            <template #meta>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span v-if="detail?.paket?.Nama_Kategori" class="badge bg-light text-secondary border px-2.5 py-1 rounded-pill small">
                        <i class="bi bi-folder2-open text-primary me-1"></i>
                        {{ detail.paket.Nama_Kategori }}
                    </span>
                    <span
                        v-if="detail?.paket?.Prioritas"
                        class="badge px-2.5 py-1 rounded-pill small fw-bold"
                        :class="{
                            'bg-danger-subtle text-danger border border-danger-subtle': detail.paket.Prioritas === 'KRITIS',
                            'bg-warning-subtle text-warning-emphasis border border-warning-subtle': detail.paket.Prioritas === 'TINGGI',
                            'bg-light text-secondary border': !['KRITIS', 'TINGGI'].includes(detail.paket.Prioritas),
                        }"
                    >
                        {{ detail.paket.Prioritas }}
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill small fw-semibold">
                        {{ versiLabel }}
                    </span>
                    <span v-if="detail?.paket?.Flag_Absensi === 'Y'" class="badge bg-purple-subtle text-purple border border-purple-subtle px-2.5 py-1 rounded-pill small fw-semibold">
                        <i class="bi bi-camera-fill me-1"></i>
                        Wajib Presensi
                    </span>
                </div>
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        href="/sop/admin/monitoring?tab=paket"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        label="Kembali"
                    />
                    <HcisButton
                        v-if="kpiSummary.terlambat > 0 || kpiSummary.progress > 0"
                        variant="warning"
                        size="sm"
                        leading-icon="bell-fill"
                        :label="`Ingatkan (${kpiSummary.terlambat + kpiSummary.progress})`"
                        :loading="mengingatkan"
                        @click="bukaDialogPengingat(false)"
                    />
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        icon-only
                        leading-icon="arrow-clockwise"
                        :loading="loading || isRefreshing"
                        title="Muat ulang seluruh data"
                        aria-label="Muat ulang"
                        @click="fetchData"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. CONTEXT BANNER (PERLU TINDAKAN JIKA ADA OVERDUE) ══ -->
        <HcisContextBanner
            v-if="kpiSummary.terlambat > 0"
            class="mb-3"
            variant="glass"
            sentiment="danger"
            title="Terdapat Karyawan Terlambat Menyelesaikan Dokumen"
            :subtitle="`Ada ${kpiSummary.terlambat} karyawan yang telah melewati batas akhir tenggat waktu. Kirimkan pengingat untuk mempercepat kepatuhan.`"
            kicker="PERHATIAN KEPATUHAN"
            icon="exclamation-octagon-fill"
            scope-variant="danger"
            :scope-label="`${kpiSummary.terlambat} Terlambat`"
            scope-radar
        >
            <template #actions>
                <HcisButton
                    variant="danger"
                    size="xs"
                    leading-icon="bell-fill"
                    label="Ingatkan Karyawan Terlambat"
                    :loading="mengingatkan"
                    @click="bukaDialogPengingat(true)"
                />
            </template>
        </HcisContextBanner>

        <!-- ══ 3. LOADING SKELETON ══ -->
        <div v-if="loading && !detail" class="my-4">
            <div class="row g-3 mb-4">
                <div v-for="n in 4" :key="n" class="col-12 col-sm-6 col-lg-3">
                    <HcisSkeleton type="card" height="108px" rounded="xl" />
                </div>
            </div>
            <HcisSkeleton type="card" height="360px" rounded="xl" />
        </div>

        <!-- ══ 4. EMPTY STATE ══ -->
        <HcisEmptyState
            v-else-if="!detail"
            title="Paket Dokumen Tidak Ditemukan"
            description="Paket Dokumen ini mungkin telah dihapus atau ID yang dimasukkan tidak valid."
            icon="exclamation-triangle"
            variant="dashed"
            sentiment="warning"
            class="my-5"
        >
            <template #actions>
                <HcisButton
                    href="/sop/admin/monitoring?tab=paket"
                    variant="primary"
                    size="sm"
                    leading-icon="arrow-left"
                    label="Kembali ke Daftar Paket"
                />
            </template>
        </HcisEmptyState>

        <!-- ══ 5. MAIN CONTENT AREA ══ -->
        <template v-else>
            <!-- ══ KPI Strip Eksekutif ══ -->
            <HcisStatGroup :stats="statistik" :cols="4" gap="md" appearance="crystal" class="mb-3 mp-stats-group" />

            <!-- ══ Tab Bar Navigasi ══ -->
            <div class="mp-tabs-wrap mb-3">
                <HcisTabs v-model="tab" :tabs="tabs" variant="segmented" class="mp-tabs-bar" />
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 1: RINGKASAN & TREN KEPATUHAN
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'ringkasan'" class="mp-zona">
                <!-- Milestone Funnel Kepatuhan -->
                <HcisCard
                    size="sm"
                    variant="glass"
                    sentiment="brand"
                    icon="diagram-3"
                    title="Alur Penyelesaian & Milestone Kepatuhan"
                    description="Perjalanan penugasan karyawan mulai dari distribusi hingga konfirmasi akhir"
                    class="mb-3"
                >
                    <div class="mp-funnel-grid">
                        <div
                            v-for="(step, idx) in funnelSteps"
                            :key="idx"
                            class="mp-funnel-col"
                            :class="{ 'is-completed': step.persen >= 100, 'is-active': step.persen > 0 && step.persen < 100 }"
                        >
                            <div class="mp-funnel-card">
                                <div class="mp-funnel-icon-wrap">
                                    <HcisIcon :name="step.icon" size="sm" />
                                </div>
                                <div class="mp-funnel-body">
                                    <div class="mp-funnel-step-num">TAHAP 0{{ idx + 1 }}</div>
                                    <div class="mp-funnel-title">{{ step.label }}</div>
                                    <div class="mp-funnel-val-row">
                                        <span class="mp-funnel-count">{{ step.count }} Orang</span>
                                        <span class="mp-funnel-pct fw-bold" :class="persenTextClass(step.persen)">{{ step.persen }}%</span>
                                    </div>
                                    <HcisProgress
                                        :percentage="step.persen"
                                        :card="false"
                                        :show-text="false"
                                        size="xs"
                                        :sentiment="progressSentiment(step.persen)"
                                        animated
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <div class="row g-3">
                    <!-- Gauge Tingkat Penyelesaian (Col 4) -->
                    <div class="col-12 col-lg-4">
                        <HcisCard
                            size="sm"
                            title="Tingkat Kepatuhan Selesai"
                            description="Persentase karyawan yang telah 100% tuntas"
                            icon="speedometer2"
                            sentiment="brand"
                            variant="glass"
                            :badge="`${kpiSummary.persen}%`"
                            :badge-variant="kpiSummary.persen >= 80 ? 'success' : (kpiSummary.persen >= 40 ? 'warning' : 'danger')"
                            class="h-100"
                        >
                            <HcisChart
                                type="gauge"
                                :card="false"
                                sentiment="brand"
                                :data="kpiSummary.persen || 0"
                                :height="260"
                                :loading="loading"
                                allow-export
                            />
                        </HcisCard>
                    </div>

                    <!-- Tren Kepatuhan Chart (Col 8) -->
                    <div class="col-12 col-lg-8">
                        <HcisCard
                            size="sm"
                            variant="glass"
                            sentiment="brand"
                            icon="graph-up-arrow"
                            title="Tren Penyelesaian Kepatuhan"
                            description="Perkembangan konfirmasi dan penyelesaian paket dokumen dari waktu ke waktu"
                            class="h-100"
                        >
                            <div class="mp-chart-box">
                                <SopChart
                                    tipe="area"
                                    :series="chartTrenSeries"
                                    :opsi-extra="chartTrenOptions"
                                    :tinggi="260"
                                    :loading="loadingTren"
                                    :kosong="!chartTrenSeries[0]?.data?.length"
                                    judul-kosong="Belum ada data tren"
                                    ket-kosong="Aktivitas penyelesaian akan tergambar seiring karyawan menyelesaikan dokumen."
                                />
                            </div>
                        </HcisCard>
                    </div>

                    <!-- Distribusi Kepatuhan per Unit Kerja (Col 12) -->
                    <div class="col-12">
                        <HcisCard
                            size="sm"
                            variant="glass"
                            sentiment="brand"
                            icon="building"
                            title="Distribusi Kepatuhan per Unit Kerja"
                            description="Tingkat kepatuhan karyawan di tiap unit / departemen"
                            :badge="`${detail?.distribusi_unit?.length || 0} Unit`"
                        >
                            <div v-if="!detail?.distribusi_unit?.length" class="text-center py-4 text-muted">
                                <HcisIcon name="info-circle" size="md" class="mb-2 opacity-50" />
                                <div>Belum ada unit kerja yang terdaftar.</div>
                            </div>
                            <div v-else class="row g-3">
                                <div
                                    v-for="(u, uIdx) in detail.distribusi_unit"
                                    :key="uIdx"
                                    class="col-12 col-md-6 col-lg-4"
                                >
                                    <div class="mp-unit-item">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="mp-unit-name fw-semibold text-truncate" :title="u.unit">
                                                {{ u.unit }}
                                            </span>
                                            <span class="mp-unit-pct fw-bold" :class="persenTextClass(u.persen)">
                                                {{ u.persen }}%
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between small text-muted mb-2">
                                            <span>{{ u.selesai }} dari {{ u.total }} Selesai</span>
                                            <span v-if="u.terlambat > 0" class="text-danger fw-semibold">
                                                {{ u.terlambat }} Telat
                                            </span>
                                        </div>
                                        <HcisProgress
                                            :percentage="u.persen"
                                            :card="false"
                                            :show-text="false"
                                            size="xs"
                                            :sentiment="progressSentiment(u.persen)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </HcisCard>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 2: DAFTAR KARYAWAN & STATUS KEPATUHAN
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'karyawan'" class="mp-zona">
                <TabKaryawanPaket
                    :karyawan-list="detail?.karyawan || []"
                    :paket="detail?.paket || {}"
                    :kpi="detail?.kpi || {}"
                    :id-paket="idPaket"
                    :loading="loading"
                    @open-drawer="bukaDrawerKaryawan"
                    @refresh="fetchData"
                />
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 3: STRUKTUR DOKUMEN & SECTION
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'dokumen'" class="mp-zona">
                <TabDokumenPaket
                    :items-list="detail?.items || []"
                    :paket="detail?.paket || {}"
                    :id-paket="idPaket"
                    :loading="loading"
                />
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 4: PRESENSI & ABSENSI KARYAWAN (MODULAR HCIS DESIGN SYSTEM)
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'absensi'" class="mp-zona">
                <TabAbsensiPaket
                    :absensi-list="detail?.absensi || []"
                    :paket="detail?.paket || {}"
                    :id-paket="idPaket"
                    :loading="loading"
                />
            </section>

            <!-- ═══════════════════════════════════════════════════════════════
                 TAB 5: LOG AKTIVITAS & JEJAK AUDIT (MODULAR HCIS DESIGN SYSTEM)
                 ═══════════════════════════════════════════════════════════════ -->
            <section v-if="tab === 'aktivitas'" class="mp-zona">
                <TabAuditPaket
                    :aktivitas-list="detail?.aktivitas || []"
                    :paket="detail?.paket || {}"
                    :id-paket="idPaket"
                    :loading="loading"
                />
            </section>
        </template>

        <!-- ══ 6. DRAWER AUDIT KARYAWAN ══ -->
        <KaryawanDrawer
            :show="drawerKaryawan.show"
            :kode="drawerKaryawan.kode"
            :token="drawerKaryawan.token"
            :nama="drawerKaryawan.nama"
            @close="drawerKaryawan.show = false"
        />

        <!-- ══ 7. MODAL KONFIRMASI PENGINGAT (HCIS DIALOG RESMI) ══ -->
        <HcisDialog
            v-model="dialogPengingat.show"
            :title="dialogPengingat.tipe === 'terlambat' ? 'Ingatkan Karyawan Terlambat' : 'Kirim Pengingat Dokumen'"
            kicker="KONFIRMASI PENGINGAT"
            icon="bell-fill"
            :sentiment="dialogPengingat.tipe === 'terlambat' ? 'danger' : 'warning'"
            variant="glass"
            :size="dialogPengingat.daftarKaryawan.length > 1 ? 'md' : 'sm'"
            header-divider
        >
            <!-- Skenario 1 Karyawan Tunggal -->
            <div v-if="dialogPengingat.daftarKaryawan.length === 1" class="d-flex flex-column gap-3 py-1">
                <HcisCard
                    size="xs"
                    variant="flat"
                    :sentiment="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'brand'"
                >
                    <div class="d-flex align-items-center gap-3">
                        <HcisAvatar
                            :name="dialogPengingat.daftarKaryawan[0].Nama || dialogPengingat.daftarKaryawan[0].Kode_Karyawan"
                            size="md"
                            shape="squircle"
                            :variant="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'brand'"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-0.5">
                                <h6 class="mb-0 fw-bold font-scale-xs text-dark text-truncate">
                                    {{ dialogPengingat.daftarKaryawan[0].Nama || dialogPengingat.daftarKaryawan[0].Kode_Karyawan }}
                                </h6>
                                <HcisBadge
                                    :label="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'Terlambat' : 'Belum Selesai'"
                                    :variant="dialogPengingat.daftarKaryawan[0].Is_Terlambat ? 'danger' : 'warning'"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                            <div class="font-scale-2xs text-muted font-monospace mb-1">
                                NIK: {{ dialogPengingat.daftarKaryawan[0].No_Induk_Karyawan || dialogPengingat.daftarKaryawan[0].Kode_Karyawan }}
                            </div>
                            <div class="font-scale-2xs text-muted">
                                {{ dialogPengingat.daftarKaryawan[0].Departemen || dialogPengingat.daftarKaryawan[0].Divisi || 'Semua Unit' }}
                                <span v-if="dialogPengingat.daftarKaryawan[0].Jabatan">· {{ dialogPengingat.daftarKaryawan[0].Jabatan }}</span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <p class="font-scale-xs text-muted mb-0">
                    Kirimkan notifikasi pengingat penugasan paket dokumen <strong>"{{ detail?.paket?.Nama_Paket }}"</strong> kepada karyawan di atas?
                </p>
            </div>

            <!-- Skenario Banyak Karyawan (Massal) -->
            <div v-else class="d-flex flex-column gap-3 py-1">
                <p class="font-scale-xs text-muted mb-0">
                    Kirimkan notifikasi pengingat penugasan paket dokumen <strong>"{{ detail?.paket?.Nama_Paket }}"</strong> kepada <strong>{{ dialogPengingat.daftarKaryawan.length }}</strong> karyawan yang belum selesai?
                </p>

                <HcisCard
                    size="xs"
                    variant="default"
                    sentiment="neutral"
                    :title="`Daftar Penerima (${dialogPengingat.daftarKaryawan.length} Karyawan)`"
                    :badge="dialogPengingat.daftarKaryawan.filter(k => k.Is_Terlambat).length > 0 ? `${dialogPengingat.daftarKaryawan.filter(k => k.Is_Terlambat).length} Terlambat` : null"
                    badge-variant="danger"
                >
                    <div class="d-flex flex-column gap-1.5 overflow-y-auto" style="max-height: 200px;">
                        <div
                            v-for="k in dialogPengingat.daftarKaryawan"
                            :key="k.Kode_Karyawan"
                            class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-slate-50 border border-slate-100"
                        >
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar
                                    :name="k.Nama || k.Kode_Karyawan"
                                    size="xs"
                                    shape="squircle"
                                    :variant="k.Is_Terlambat ? 'danger' : 'brand'"
                                />
                                <div class="min-w-0">
                                    <div class="font-scale-xs fw-bold text-dark text-truncate">{{ k.Nama || k.Kode_Karyawan }}</div>
                                    <div class="font-scale-2xs text-muted">{{ k.Departemen || k.Divisi || k.No_Induk_Karyawan }}</div>
                                </div>
                            </div>
                            <HcisBadge
                                :label="k.Is_Terlambat ? 'Terlambat' : 'Belum Selesai'"
                                :variant="k.Is_Terlambat ? 'danger' : 'warning'"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                    </div>
                </HcisCard>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end gap-2 w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Batal"
                        :disabled="mengingatkan"
                        @click="dialogPengingat.show = false"
                    />
                    <HcisButton
                        :variant="dialogPengingat.tipe === 'terlambat' ? 'danger' : 'warning'"
                        size="sm"
                        leading-icon="bell-fill"
                        label="Kirim Notifikasi"
                        :loading="mengingatkan"
                        @click="eksekusiKirimPengingat"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisHeader,
    HcisBreadcrumb,
    HcisButton,
    HcisStatGroup,
    HcisTabs,
    HcisCard,
    HcisBadge,
    HcisAvatar,
    HcisField,
    HcisIcon,
    HcisEmptyState,
    HcisSkeleton,
    HcisDialog,
    HcisContextBanner,
    HcisProgress,
    HcisPagination,
    HcisTable,
    HcisChart,
} from '@/components/ui';
import SopChart from '../../Shared/components/ui/SopChart.vue';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import ItemTypeIcon from '../../Shared/components/ui/ItemTypeIcon.vue';
import KaryawanDrawer from './monitoring/KaryawanDrawer.vue';
import TabKaryawanPaket from './monitoring/paket/TabKaryawanPaket.vue';
import TabDokumenPaket from './monitoring/paket/TabDokumenPaket.vue';
import TabAbsensiPaket from './monitoring/paket/TabAbsensiPaket.vue';
import TabAuditPaket from './monitoring/paket/TabAuditPaket.vue';
import { STATUS_PAKET } from '../../Shared/support/constants.js';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'SopMonitoringPaketDetail',
    components: {
        HcisHeader,
        HcisBreadcrumb,
        HcisButton,
        HcisStatGroup,
        HcisTabs,
        HcisCard,
        HcisBadge,
        HcisAvatar,
        HcisField,
        HcisIcon,
        HcisEmptyState,
        HcisSkeleton,
        HcisDialog,
        HcisContextBanner,
        HcisProgress,
        HcisPagination,
        HcisTable,
        HcisChart,
        SopChart,
        StatusPill,
        ItemTypeIcon,
        KaryawanDrawer,
        TabKaryawanPaket,
        TabDokumenPaket,
        TabAbsensiPaket,
        TabAuditPaket,
    },
    props: {
        idPaket: { type: String, required: true },
        idPaketRaw: { type: Number, default: null },
    },
    data() {
        return {
            loading: false,
            isRefreshing: false,
            mengingatkan: false,
            loadingTren: false,
            detail: null,
            tab: 'ringkasan',
            tabs: [
                { key: 'ringkasan', label: 'Ringkasan & Tren', icon: 'speedometer2' },
                { key: 'karyawan', label: 'Daftar Karyawan', icon: 'people-fill' },
                { key: 'dokumen', label: 'Struktur Dokumen', icon: 'folder2-open' },
                { key: 'absensi', label: 'Presensi & Absensi', icon: 'camera-fill' },
                { key: 'aktivitas', label: 'Log Audit & Aktivitas', icon: 'journal-text' },
            ],
            chartTrenSeries: [
                { name: 'Penyelesaian Kumulatif', data: [] },
            ],
            chartTrenOptions: {
                chart: { type: 'area', toolbar: { show: false } },
                colors: ['#10b981'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2.5 },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95, 100] },
                },
                xaxis: { categories: [] },
                yaxis: { title: { text: 'Orang Selesai' } },
            },
            drawerKaryawan: {
                show: false,
                kode: '',
                token: '',
                nama: '',
            },
            filterAbsensi: {
                q: '',
                jenis: 'all',
            },
            openAbsensiKeys: [],
            fotoDialog: {
                show: false,
                item: null,
            },
            dialogPengingat: {
                show: false,
                daftarKaryawan: [],
                tipe: 'massal',
            },
        };
    },
    computed: {
        breadcrumb() {
            return [
                { label: 'Beranda', href: '/' },
                { label: 'Monitoring Dokumen', href: '/sop/admin/monitoring?tab=paket' },
                { label: this.detail?.paket?.Nama_Paket || 'Detail Paket' },
            ];
        },
        barisSubjudul() {
            const p = this.detail?.paket;
            if (!p) return 'Memuat rincian paket...';

            return `Kategori ${p.Nama_Kategori || 'Umum'} · ${this.kpiSummary.total_target} Karyawan Ditugaskan`;
        },
        versiLabel() {
            const p = this.detail?.paket;
            if (!p) return 'v1.0';

            return `v${p.Versi_Mayor || 1}.${p.Versi_Minor || 0}`;
        },
        kpiSummary() {
            return this.detail?.kpi || {
                total_target: 0,
                selesai: 0,
                progress: 0,
                terlambat: 0,
                persen: 0,
            };
        },
        statistik() {
            const k = this.kpiSummary;
            return [
                {
                    label: 'Target Karyawan',
                    value: k.total_target,
                    suffix: 'Penerima',
                    sentiment: 'brand',
                    icon: 'people-fill',
                    pattern: 'grid',
                    caption: 'Total karyawan yang mendapatkan penugasan',
                },
                {
                    label: 'Kepatuhan Selesai',
                    value: k.selesai,
                    suffix: `(${k.persen}%)`,
                    sentiment: 'success',
                    icon: 'check-circle-fill',
                    pattern: 'dots',
                    caption: 'Karyawan yang telah 100% menyelesaikan seluruh item',
                },
                {
                    label: 'Sedang Berjalan',
                    value: k.progress,
                    suffix: 'Karyawan',
                    sentiment: 'warning',
                    icon: 'hourglass-split',
                    pattern: 'stripes',
                    caption: 'Masih dalam proses membaca atau menyelesaikan kuis',
                },
                {
                    label: 'Terlambat (Overdue)',
                    value: k.terlambat,
                    suffix: 'Karyawan',
                    sentiment: k.terlambat > 0 ? 'danger' : 'neutral',
                    icon: 'exclamation-octagon-fill',
                    pattern: 'crosshair',
                    caption: 'Melewati batas akhir tanggal tenggat waktu',
                },
            ];
        },
        funnelSteps() {
            const f = this.detail?.funnel || {};
            const tot = f.total_target || this.kpiSummary.total_target || 1;
            const dibuka = f.total_dibuka || (this.detail?.karyawan || []).filter((k) => k.Status_Paket !== 'BELUM').length;
            const selesai = f.total_selesai || this.kpiSummary.selesai || 0;

            return [
                {
                    label: 'Distribusi Penugasan',
                    icon: 'send-check-fill',
                    count: tot,
                    persen: 100,
                },
                {
                    label: 'Membuka / Membaca dokumen',
                    icon: 'book-half',
                    count: dibuka,
                    persen: tot > 0 ? Math.round((dibuka / tot) * 100) : 0,
                },
                {
                    label: 'Kepatuhan Selesai 100%',
                    icon: 'patch-check-fill',
                    count: selesai,
                    persen: tot > 0 ? Math.round((selesai / tot) * 100) : 0,
                },
            ];
        },
        groupedAbsensi() {
            const list = this.detail?.absensi || [];
            const map = new Map();

            list.forEach((a) => {
                const kode = a.Kode_Karyawan || 'UNKNOWN';
                if (!map.has(kode)) {
                    map.set(kode, {
                        Kode_Karyawan: kode,
                        Nama: a.Nama || kode,
                        No_Induk_Karyawan: a.No_Induk_Karyawan || '',
                        Departemen: a.Departemen || '',
                        Divisi: a.Divisi || '',
                        Jabatan: a.Jabatan || '',
                        Lokasi: a.Lokasi || '',
                        total_presensi: 0,
                        terakhir_checktime: a.Checktime,
                        ada_foto: false,
                        ada_lokasi_meragukan: false,
                        riwayat: [],
                    });
                }
                const grp = map.get(kode);
                grp.total_presensi += 1;
                if (a.Foto_Url) grp.ada_foto = true;
                if (a.Lokasi_Meragukan) grp.ada_lokasi_meragukan = true;
                grp.riwayat.push(a);
            });

            return Array.from(map.values());
        },
        filteredGroupedAbsensi() {
            let list = this.groupedAbsensi;
            if (this.filterAbsensi.q) {
                const q = this.filterAbsensi.q.toLowerCase();
                list = list.filter(
                    (g) =>
                        (g.Nama || '').toLowerCase().includes(q) ||
                        (g.Kode_Karyawan || '').toLowerCase().includes(q) ||
                        (g.No_Induk_Karyawan || '').toLowerCase().includes(q) ||
                        (g.Departemen || '').toLowerCase().includes(q) ||
                        (g.Divisi || '').toLowerCase().includes(q) ||
                        (g.Jabatan || '').toLowerCase().includes(q) ||
                        (g.Lokasi || '').toLowerCase().includes(q),
                );
            }
            if (this.filterAbsensi.jenis === 'foto') {
                list = list.filter((g) => g.ada_foto);
            } else if (this.filterAbsensi.jenis === 'meragukan') {
                list = list.filter((g) => g.ada_lokasi_meragukan);
            }
            return list;
        },
        chipFilterAbsensi() {
            const list = this.groupedAbsensi;
            return [
                { label: 'Semua Karyawan', value: 'all', count: list.length },
                { label: 'Ada Foto Selfie', value: 'foto', count: list.filter((g) => g.ada_foto).length },
                { label: 'Akurasi GPS Rendah', value: 'meragukan', count: list.filter((g) => g.ada_lokasi_meragukan).length },
            ];
        },
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        async fetchData() {
            this.loading = true;
            try {
                const r = await axios.get(`${API}/paket/${this.idPaket}`);
                if (r.data.success) {
                    this.detail = r.data.result || {};
                    this.buildTrenChart();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat rincian monitoring paket');
            } finally {
                this.loading = false;
                this.isRefreshing = false;
            }
        },
        buildTrenChart() {
            const kList = this.detail?.karyawan || [];
            const datesMap = {};

            kList.forEach((k) => {
                if (k.Tanggal_Selesai) {
                    const d = k.Tanggal_Selesai.split(' ')[0];
                    datesMap[d] = (datesMap[d] || 0) + 1;
                }
            });

            const sortedDates = Object.keys(datesMap).sort();
            if (!sortedDates.length) {
                this.chartTrenSeries = [{ name: 'Penyelesaian', data: [] }];
                this.chartTrenOptions = { ...this.chartTrenOptions, xaxis: { categories: [] } };
                return;
            }

            let kumulatif = 0;
            const values = [];
            sortedDates.forEach((d) => {
                kumulatif += datesMap[d];
                values.push(kumulatif);
            });

            this.chartTrenSeries = [{ name: 'Karyawan Selesai', data: values }];
            this.chartTrenOptions = { ...this.chartTrenOptions, xaxis: { categories: sortedDates } };
        },
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s || 'Belum Selesai';
        },
        statusPillVariant(s) {
            const raw = String(s || '').toUpperCase();
            if (raw === 'SELESAI' || raw === 'LULUS') return 'ok';
            if (raw === 'PROGRESS' || raw === 'PROGRES' || raw === 'PARTIAL') return 'warn';
            if (raw === 'TERLAMBAT') return 'bad';
            return 'idle';
        },
        persenTextClass(p) {
            const val = Number(p) || 0;
            return val >= 80 ? 'text-success' : val >= 40 ? 'text-warning' : 'text-danger';
        },
        progressSentiment(persen) {
            const p = Number(persen) || 0;
            return p >= 80 ? 'success' : p >= 40 ? 'warning' : 'danger';
        },
        formatDurasi(detik) {
            if (!detik || detik <= 0) return '-';
            const m = Math.floor(detik / 60);
            const s = detik % 60;
            return m > 0 ? `${m}m ${s}s` : `${s}s`;
        },
        bukaDrawerKaryawan(k) {
            this.drawerKaryawan = {
                show: true,
                kode: k.Kode_Karyawan,
                token: k.Token || '',
                nama: k.Nama || k.Kode_Karyawan,
            };
        },
        bukaFoto(abs) {
            this.fotoDialog = {
                show: true,
                item: abs,
            };
        },
        bukaDialogPengingat(hanyaTerlambat = false) {
            const list = (this.detail?.karyawan || []).filter((k) => {
                if (hanyaTerlambat) return k.Is_Terlambat;
                return (k.Status_Penugasan || '').toUpperCase() !== 'SELESAI' || k.Is_Terlambat;
            });

            if (!list.length) {
                ElMessage.info(hanyaTerlambat ? 'Tidak ada karyawan yang terlambat untuk paket ini.' : 'Seluruh karyawan telah menyelesaikan penugasan dokumen ini.');
                return;
            }

            this.dialogPengingat = {
                show: true,
                daftarKaryawan: list,
                tipe: hanyaTerlambat ? 'terlambat' : 'massal',
            };
        },
        async eksekusiKirimPengingat() {
            const kodes = this.dialogPengingat.daftarKaryawan.map((k) => k.Kode_Karyawan);
            if (!kodes.length) return;

            this.mengingatkan = true;
            try {
                const r = await axios.post(`${API}/ingatkan`, {
                    kodes,
                    id_paket: this.idPaket,
                    pesan: `Mohon segera menyelesaikan pembacaan paket dokumen "${this.detail?.paket?.Nama_Paket}".`,
                });
                ElMessage.success(r.data?.message || 'Pengingat berhasil dikirim.');
                this.dialogPengingat.show = false;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengirimkan pengingat.');
            } finally {
                this.mengingatkan = false;
            }
        },
        toggleAbsensiAccordion(kode) {
            const idx = this.openAbsensiKeys.indexOf(kode);
            if (idx > -1) {
                this.openAbsensiKeys.splice(idx, 1);
            } else {
                this.openAbsensiKeys.push(kode);
            }
        },
        isAbsensiOpen(kode) {
            return this.openAbsensiKeys.includes(kode);
        },
        bukaSemuaAbsensi() {
            this.openAbsensiKeys = this.filteredGroupedAbsensi.map((g) => g.Kode_Karyawan);
        },
        tutupSemuaAbsensi() {
            this.openAbsensiKeys = [];
        },
        urlMonitoringKaryawanPaket(k) {
            const token = k?.Token || k?.Kode_Karyawan || '';
            return `/sop/admin/monitoring/karyawan/${token}/paket/${this.idPaket}`;
        },
    },
};
</script>

<style scoped>
.mp-page {
    padding-bottom: 3rem;
}

.mp-stats-group {
    border-radius: 1rem;
}

.mp-tabs-wrap {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 0.875rem;
    padding: 0.375rem;
}

/* Funnel Milestone */
.mp-funnel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
}

.mp-funnel-card {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.75rem;
    padding: 1rem 1.125rem;
    transition: all 0.2s ease;
}

.mp-funnel-col.is-completed .mp-funnel-card {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.mp-funnel-col.is-active .mp-funnel-card {
    background: var(--hcis-sky-50);
    border-color: var(--hcis-sky-200);
}

.mp-funnel-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    flex-shrink: 0;
}

.mp-funnel-col.is-completed .mp-funnel-icon-wrap {
    background: var(--hcis-emerald-500);
    color: var(--hcis-white);
}

.mp-funnel-col.is-active .mp-funnel-icon-wrap {
    background: var(--hcis-sky-500);
    color: var(--hcis-white);
}

.mp-funnel-body {
    flex: 1;
    min-width: 0;
}

.mp-funnel-step-num {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--hcis-slate-400);
    margin-bottom: 0.125rem;
}

.mp-funnel-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    margin-bottom: 0.375rem;
}

.mp-funnel-val-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8125rem;
    margin-bottom: 0.375rem;
}

.mp-funnel-count {
    font-weight: 600;
    color: var(--hcis-slate-700);
}

.mp-unit-item {
    padding: 0.875rem 1rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.625rem;
}

/* Absensi Accordion */
.mp-accordion-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.mp-accordion-item {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.75rem;
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.mp-accordion-item:hover {
    border-color: var(--hcis-slate-300);
}

.mp-accordion-item.is-open {
    border-color: #93c5fd;
    box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.08);
}

.mp-accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.125rem;
    cursor: pointer;
    background: var(--hcis-slate-50);
    transition: background-color 0.15s ease;
    user-select: none;
}

.mp-accordion-header:hover {
    background: var(--hcis-slate-100);
}

.mp-accordion-item.is-open .mp-accordion-header {
    background: #eff6ff;
    border-bottom: 1px solid #dbeafe;
}

.mp-acc-name {
    font-size: 0.9375rem;
}

.mp-accordion-chevron {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
}

.mp-accordion-body {
    padding: 1.125rem;
    background: var(--hcis-white);
}

.mp-absensi-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.75rem;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.mp-absensi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.08);
}

.mp-absensi-img-wrap {
    height: 180px;
    background: var(--hcis-slate-100);
    position: relative;
    cursor: pointer;
    overflow: hidden;
}

.mp-absensi-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.mp-absensi-img-wrap:hover .mp-absensi-img {
    transform: scale(1.05);
}

.mp-absensi-img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
}

.mp-absensi-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.mp-absensi-img-wrap:hover .mp-absensi-overlay {
    opacity: 1;
}

.mp-absensi-body {
    padding: 0.875rem 1rem;
}

.mp-lightbox-img {
    max-height: 480px;
    width: 100%;
    object-fit: contain;
    background: var(--hcis-slate-900);
}
</style>
