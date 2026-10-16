<template>
    <div class="sop-stack">
        <!-- Papan Risiko Dokumen (Redesain Full Custom Modern) -->
        <div v-if="tampil('risiko')" class="pr-card sop-fade-up">
            <!-- Header Utama + Executive Quick Stats -->
            <div class="pr-header">
                <div class="pr-header-info">
                    <div class="pr-hero-icon">
                        <i class="bi bi-shield-exclamation pr-ico-danger"></i>
                        <span class="pr-pulse-dot danger"></span>
                    </div>
                    <div>
                        <div class="pr-eyebrow">Prioritas &amp; Risiko Operasional</div>
                        <h5 class="pr-title">
                            Papan Risiko Dokumen
                            <span v-if="risikoKpi.kritis > 0" class="pr-badge-kritis-pill">
                                {{ risikoKpi.kritis }} Kritis
                            </span>
                        </h5>
                        <p class="pr-subtitle">
                            Paket Dokumen berisiko tinggi terurut otomatis berdasarkan tingkat prioritas, keterlambatan, dan
                            tingkat penyelesaian.
                        </p>
                    </div>
                </div>

                <!-- Quick Summary Stats Chips -->
                <div class="pr-stats-wrap">
                    <div
                        class="pr-stat-chip danger"
                        :class="{ active: filterPrioritas === 'KRITIS' }"
                        title="Filter Prioritas Kritis"
                        role="button"
                        @click="filterPrioritas = filterPrioritas === 'KRITIS' ? 'ALL' : 'KRITIS'"
                    >
                        <div class="chip-label"><i class="bi bi-exclamation-triangle-fill me-1"></i>Kritis</div>
                        <div class="chip-val">{{ risikoKpi.kritis }}</div>
                    </div>
                    <div
                        class="pr-stat-chip warning"
                        :class="{ active: filterPrioritas === 'TINGGI' }"
                        title="Filter Prioritas Tinggi"
                        role="button"
                        @click="filterPrioritas = filterPrioritas === 'TINGGI' ? 'ALL' : 'TINGGI'"
                    >
                        <div class="chip-label"><i class="bi bi-shield-exclamation me-1"></i>Tinggi</div>
                        <div class="chip-val">{{ risikoKpi.tinggi }}</div>
                    </div>
                    <div class="pr-stat-chip info" title="Total Karyawan Terlambat">
                        <div class="chip-label"><i class="bi bi-clock-history me-1"></i>Total Telat</div>
                        <div class="chip-val">{{ risikoKpi.totalTerlambat }} <span class="unit">orang</span></div>
                    </div>
                    <div class="pr-stat-chip primary" title="Rata-rata tingkat penyelesaian dokumen berisiko">
                        <div class="chip-label"><i class="bi bi-pie-chart-fill me-1"></i>Rerata Penyelesaian</div>
                        <div class="chip-val">{{ risikoKpi.avgCoverage }}%</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar Controls (Filter, Sort, View, Search) -->
            <div class="pr-toolbar">
                <!-- Filter Prioritas Segmented Control -->
                <div class="pr-filter-seg">
                    <button
                        type="button"
                        class="pr-filter-btn"
                        :class="{ active: filterPrioritas === 'ALL' }"
                        @click="filterPrioritas = 'ALL'"
                    >
                        Semua ({{ risikoKpi.totalSop }})
                    </button>
                    <button
                        type="button"
                        class="pr-filter-btn btn-kritis"
                        :class="{ active: filterPrioritas === 'KRITIS' }"
                        @click="filterPrioritas = 'KRITIS'"
                    >
                        <span v-if="risikoKpi.kritis > 0" class="pr-dot-kritis"></span>
                        Kritis ({{ risikoKpi.kritis }})
                    </button>
                    <button
                        type="button"
                        class="pr-filter-btn"
                        :class="{ active: filterPrioritas === 'TINGGI' }"
                        @click="filterPrioritas = 'TINGGI'"
                    >
                        Tinggi ({{ risikoKpi.tinggi }})
                    </button>
                    <button
                        type="button"
                        class="pr-filter-btn"
                        :class="{ active: filterPrioritas === 'NORMAL' }"
                        @click="filterPrioritas = 'NORMAL'"
                    >
                        Normal ({{ risikoKpi.normal }})
                    </button>
                </div>

                <!-- Sort, View Mode & Search -->
                <div class="pr-toolbar-actions">
                    <!-- Urutan Selector -->
                    <div class="pr-select-wrap">
                        <i class="bi bi-arrow-down-up pr-select-ico"></i>
                        <select v-model="urutRisiko" class="pr-select" title="Urutkan Papan Risiko">
                            <option value="DEFAULT">Urutan Prioritas &amp; Dampak</option>
                            <option value="TERLAMBAT">Terlambat Terbanyak</option>
                            <option value="TELAT_MAKS">Keterlambatan Terlama (Hari)</option>
                            <option value="COVERAGE_ASC">Penyelesaian Terendah</option>
                            <option value="COVERAGE_DESC">Penyelesaian Tertinggi</option>
                        </select>
                    </div>

                    <!-- View Switcher -->
                    <div class="pr-view-toggle">
                        <button
                            type="button"
                            :class="{ active: tampilanRisiko === 'tabel' }"
                            title="Tampilan Tabel"
                            @click="tampilanRisiko = 'tabel'"
                        >
                            <i class="bi bi-table"></i>
                        </button>
                        <button
                            type="button"
                            :class="{ active: tampilanRisiko === 'grid' }"
                            title="Tampilan Kartu Visual"
                            @click="tampilanRisiko = 'grid'"
                        >
                            <i class="bi bi-grid-fill"></i>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="pr-search-wrap">
                        <i class="bi bi-search pr-search-ico"></i>
                        <input
                            v-model="cariRisiko"
                            type="text"
                            class="pr-search-input"
                            placeholder="Cari Dokumen…"
                        />
                        <button
                            v-if="cariRisiko"
                            type="button"
                            class="pr-btn-clear"
                            title="Bersihkan pencarian"
                            @click="cariRisiko = ''"
                        >
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading.risiko" class="pr-loading-box">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                <span class="pr-loading-text">Memuat data papan risiko dokumen…</span>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-else-if="!risikoTersaring.length"
                title="Tak ada dokumen berisiko ditemukan"
                icon="bi-shield-check"
            >
                <template v-if="filterPrioritas !== 'ALL' || cariRisiko" #action>
                    <button
                        type="button"
                        class="pr-action-btn mt-2"
                        @click="
                            filterPrioritas = 'ALL';
                            cariRisiko = '';
                        "
                    >
                        Bersihkan Saringan
                    </button>
                </template>
            </EmptyState>

            <template v-else>
                <!-- TABEL VIEW -->
                <div v-if="tampilanRisiko === 'tabel'" class="pr-table-wrapper">
                    <table class="pr-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 45px">#</th>
                                <th>Paket Dokumen</th>
                                <th class="text-center" style="width: 120px">Prioritas</th>
                                <th style="min-width: 210px">Selesai / Ditugaskan</th>
                                <th style="min-width: 150px">Tingkat Penyelesaian</th>
                                <th class="text-end" style="width: 110px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(r, i) in risikoTersaring"
                                :key="r.Id_SOP_Paket"
                                class="pr-table-row"
                                :class="'pr-row-' + r.Prioritas"
                            >
                                <td class="text-center">
                                    <span v-if="i < 3" class="pr-rank-badge" :class="'rank-' + (i + 1)">{{
                                        i + 1
                                    }}</span>
                                    <span v-else class="pr-rank-num">{{ i + 1 }}</span>
                                </td>
                                <td>
                                    <div class="pr-sop-title">{{ r.Nama_Paket }}</div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span v-if="r.telat_maks_hari > 0" class="pr-telat-maks-text">
                                            <i class="bi bi-exclamation-circle me-0.5"></i>Max telat
                                            {{ r.telat_maks_hari }}hr
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="pr-pri-pill" :class="'p-' + r.Prioritas">
                                        <i v-if="r.Prioritas === 'KRITIS'" class="bi bi-fire me-1"></i>
                                        <i
                                            v-else-if="r.Prioritas === 'TINGGI'"
                                            class="bi bi-exclamation-triangle-fill me-1"
                                        ></i>
                                        <i v-else class="bi bi-shield-check me-1"></i>
                                        {{ r.Prioritas }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="pr-target-text">
                                            {{ r.target - r.belum }}
                                            <span class="pr-sub-target">/ {{ r.target }} Selesai</span>
                                        </span>
                                        <span v-if="r.terlambat > 0" class="pr-badge-telat">
                                            <i class="bi bi-clock-history me-1"></i>{{ r.terlambat }} telat
                                        </span>
                                        <span v-else-if="r.belum > 0" class="pr-badge-belum">
                                            {{ r.belum }} belum
                                        </span>
                                        <span v-else class="pr-badge-selesai">
                                            <i class="bi bi-check-circle-fill me-1"></i>Selesai
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <ProgressBar :value="r.persen" style="flex: 1" />
                                        <span
                                            class="pr-pct-text"
                                            :class="
                                                r.persen < 50
                                                    ? 'is-danger'
                                                    : r.persen < 80
                                                      ? 'is-warning'
                                                      : 'is-success'
                                            "
                                        >
                                            {{ r.persen }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="people-fill"
                                        label="Karyawan"
                                        title="Filter karyawan dokumen ini"
                                        @click="
                                            $emit('buka-tab', {
                                                tab: 'karyawan',
                                                filters: { search: r.Nama_Paket, q: r.Nama_Paket },
                                            })
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- GRID VIEW -->
                <div v-else class="row g-3">
                    <div v-for="(r, i) in risikoTersaring" :key="r.Id_SOP_Paket" class="col-12 col-md-6 col-xl-4">
                        <div class="pr-grid-card" :class="'pr-card-' + r.Prioritas">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span v-if="i < 3" class="pr-rank-badge-sm" :class="'rank-' + (i + 1)"
                                    >Rank #{{ i + 1 }}</span
                                >
                                <span v-else class="pr-rank-num font-scale-xs">#{{ i + 1 }}</span>

                                <span class="pr-pri-pill" :class="'p-' + r.Prioritas">
                                    <i v-if="r.Prioritas === 'KRITIS'" class="bi bi-fire me-1"></i>
                                    <i
                                        v-else-if="r.Prioritas === 'TINGGI'"
                                        class="bi bi-exclamation-triangle-fill me-1"
                                    ></i>
                                    <i v-else class="bi bi-shield-check me-1"></i>
                                    {{ r.Prioritas }}
                                </span>
                            </div>

                            <h6 class="pr-sop-title text-truncate-2 mb-1" :title="r.Nama_Paket">
                                {{ r.Nama_Paket }}
                            </h6>
                            <div class="pr-card-progress-box">
                                <div class="d-flex align-items-center justify-content-between mb-1 font-scale-sm">
                                    <span class="text-muted fw-semibold">Tingkat Penyelesaian</span>
                                    <span
                                        class="pr-pct-text"
                                        :class="
                                            r.persen < 50 ? 'is-danger' : r.persen < 80 ? 'is-warning' : 'is-success'
                                        "
                                    >
                                        {{ r.persen }}%
                                    </span>
                                </div>
                                <ProgressBar :value="r.persen" />
                            </div>

                            <div class="d-flex align-items-center justify-content-between font-scale-sm mb-3 mt-auto">
                                <div class="text-muted">
                                    Status:
                                    <strong class="text-dark">{{ r.target - r.belum }} / {{ r.target }}</strong> Selesai
                                </div>
                                <div v-if="r.terlambat > 0" class="pr-badge-telat">
                                    <i class="bi bi-clock-history me-1"></i>{{ r.terlambat }} telat
                                </div>
                            </div>

                            <HcisButton
                                variant="primary"
                                size="xs"
                                leading-icon="people-fill"
                                label="Lihat Karyawan"
                                class="w-100 justify-content-center"
                                @click="
                                    $emit('buka-tab', {
                                        tab: 'karyawan',
                                        filters: { search: r.Nama_Paket, q: r.Nama_Paket },
                                    })
                                "
                            />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div v-if="tampil('kilat') || tampil('titik_henti') || tampil('mandek')" class="row g-3">
            <!-- Konfirmasi terlalu cepat -->
            <div v-if="tampil('kilat')" class="col-12" :class="(tampil('titik_henti') || tampil('mandek')) ? 'col-xl-6' : 'col-xl-12'">
                <div class="pr-card sop-fade-up h-100">
                    <div
                        class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3 pb-2.5 border-bottom border-light-subtle"
                    >
                        <div class="d-flex align-items-center gap-3.5">
                            <div class="pr-card-icon kilat">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div>
                                <div class="pr-eyebrow">Perlu Diperiksa</div>
                                <h6 class="pr-title mb-0">Konfirmasi Terlalu Cepat</h6>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="kilat.total" class="pr-badge-kilat-count">
                                <i class="bi bi-lightning-charge-fill me-1"></i> {{ kilat.total }} temuan
                            </span>
                        </div>
                    </div>
                    <p class="small text-muted mb-3 font-scale-sm">
                        Item yang dikonfirmasi jauh lebih cepat dari estimasi waktu baca wajarnya, atau berhenti di
                        halaman awal dokumen.
                    </p>

                    <div v-if="loading.kilat" class="pr-loading-box py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <EmptyState
                        v-else-if="!kilat.rows.length"
                        title="Tidak ada konfirmasi yang mencurigakan"
                        icon="bi-patch-check"
                    />
                    <div v-else class="pr-table-wrapper">
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Item Dokumen</th>
                                    <th class="text-center">Durasi</th>
                                    <th class="text-center">Halaman</th>
                                    <th>Temuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(r, i) in kilat.rows" :key="i" class="pr-table-row">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <HcisAvatar :name="r.Nama || r.Kode_Karyawan" size="xs" />
                                            <div class="min-w-0">
                                                <button
                                                    type="button"
                                                    class="pr-emp-link text-truncate d-block"
                                                    @click="
                                                        $emit('buka-drawer', {
                                                            kode: r.Kode_Karyawan,
                                                            token: r.Token,
                                                            nama: r.Nama,
                                                        })
                                                    "
                                                >
                                                    {{ r.Nama || r.Kode_Karyawan }}
                                                </button>
                                                <div class="small text-muted font-monospace font-scale-xs">
                                                    {{ r.Kode_Karyawan }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small text-dark">{{ r.Judul_Item }}</div>
                                        <div
                                            class="small text-muted text-truncate font-scale-xs"
                                            style="max-width: 180px"
                                        >
                                            {{ r.Nama_Paket }}
                                        </div>
                                    </td>
                                    <td class="text-center fw-bold text-danger font-monospace font-scale-sm">
                                        {{ durasi(r.durasi_detik) }}
                                    </td>
                                    <td class="text-center small font-monospace">
                                        <template v-if="r.Total_Halaman">
                                            <span class="pr-page-badge"
                                                >{{ r.Halaman_Terakhir ?? 0 }}/{{ r.Total_Halaman }}</span
                                            >
                                        </template>
                                        <span v-else class="text-muted">—</span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span v-for="a in r.alasan" :key="a" class="pr-tag-danger">{{
                                                labelAlasan(a)
                                            }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Titik Henti Dokumen -->
            <div v-if="tampil('titik_henti') || tampil('mandek')" class="col-12" :class="tampil('kilat') ? 'col-xl-6' : 'col-xl-12'">
                <PanelTitikHenti :saringan="saringan" />
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { HcisButton, HcisAvatar, HcisBadge } from '@/components/ui';
import ProgressBar from '../../../../Shared/components/ui/ProgressBar.vue';
import EmptyState from '../../../../Shared/components/ui/EmptyState.vue';
import PanelTitikHenti from './panel/PanelTitikHenti.vue';

const API = '/api/v1/sop/admin/monitoring';

const LABEL_ALASAN = {
    JAUH_DI_BAWAH_ESTIMASI: 'Jauh di bawah estimasi',
    BERHENTI_DI_AWAL: 'Berhenti di awal',
    DI_BAWAH_15_DETIK: '< 15 detik',
};

/**
 * Zona 2 — "Apa yang harus saya kerjakan?"
 * Menampilkan Papan Risiko, Konfirmasi Terlalu Cepat, dan Titik Henti Dokumen.
 */
export default {
    name: 'ZonaTindakan',
    components: { HcisButton, HcisAvatar, HcisBadge, ProgressBar, EmptyState, PanelTitikHenti },
    props: {
        saringan: { type: Object, default: () => ({}) },
        /**
         * Panel mana yang ditampilkan komponen ini.
         */
        bagian: {
            type: Array,
            default: () => ['risiko', 'kilat', 'titik_henti'],
        },
    },
    emits: ['total-kilat', 'buka-drawer', 'buka-tab'],
    data() {
        return {
            risiko: [],
            kilat: { rows: [], total: 0 },
            mandek: { rows: [], total: 0 },
            loading: { risiko: false, kilat: false, mandek: false },
            cariRisiko: '',
            filterPrioritas: 'ALL',
            urutRisiko: 'DEFAULT',
            tampilanRisiko: 'tabel',
        };
    },
    computed: {
        risikoKpi() {
            const totalSop = this.risiko.length;
            const kritis = this.risiko.filter((r) => r.Prioritas === 'KRITIS').length;
            const tinggi = this.risiko.filter((r) => r.Prioritas === 'TINGGI').length;
            const normal = this.risiko.filter((r) => r.Prioritas === 'NORMAL').length;
            const totalTerlambat = this.risiko.reduce((sum, r) => sum + (Number(r.terlambat) || 0), 0);
            const avgCoverage =
                totalSop > 0
                    ? Math.round(this.risiko.reduce((sum, r) => sum + (Number(r.persen) || 0), 0) / totalSop)
                    : 0;

            return { totalSop, kritis, tinggi, normal, totalTerlambat, avgCoverage };
        },
        risikoTersaring() {
            let list = [...this.risiko];
            if (this.filterPrioritas && this.filterPrioritas !== 'ALL') {
                list = list.filter((r) => r.Prioritas === this.filterPrioritas);
            }
            if (this.cariRisiko) {
                const q = this.cariRisiko.toLowerCase();
                list = list.filter((r) => (r.Nama_Paket || '').toLowerCase().includes(q));
            }

            if (this.urutRisiko === 'TERLAMBAT') {
                list.sort((a, b) => b.terlambat - a.terlambat);
            } else if (this.urutRisiko === 'TELAT_MAKS') {
                list.sort((a, b) => b.telat_maks_hari - a.telat_maks_hari);
            } else if (this.urutRisiko === 'COVERAGE_ASC') {
                list.sort((a, b) => a.persen - b.persen);
            } else if (this.urutRisiko === 'COVERAGE_DESC') {
                list.sort((a, b) => b.persen - a.persen);
            }

            return list;
        },
    },
    mounted() {
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
        tampil(key) {
            return (this.bagian || []).includes(key);
        },
        muat() {
            if (this.tampil('risiko')) this.fetchRisiko();
            if (this.tampil('kilat')) this.fetchKilat();
            if (this.tampil('mandek')) this.fetchMandek();
        },
        initials(n) {
            return String(n || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        labelAlasan(a) {
            return LABEL_ALASAN[a] || a;
        },
        durasi(d) {
            if (d === null || d === undefined) return '—';
            if (d < 60) return `${d} dtk`;
            if (d < 3600) return `${Math.round(d / 60)} mnt`;

            return `${(d / 3600).toFixed(1)} jam`;
        },
        async fetchRisiko() {
            this.loading.risiko = true;
            try {
                const r = await axios.get(`${API}/risiko`, { params: { limit: 25, ...this.saringan } });
                this.risiko = r.data.success ? r.data.result || [] : [];
            } catch (e) {
                this.risiko = [];
            } finally {
                this.loading.risiko = false;
            }
        },
        async fetchKilat() {
            this.loading.kilat = true;
            try {
                const r = await axios.get(`${API}/kepatuhan/kilat`, { params: { limit: 8, ...this.saringan } });
                this.kilat = { rows: r.data.result || [], total: r.data.total_data ?? (r.data.result || []).length };
            } catch (e) {
                this.kilat = { rows: [], total: 0 };
            } finally {
                this.loading.kilat = false;
                this.$emit('total-kilat', this.kilat.total);
            }
        },
        async fetchMandek() {
            this.loading.mandek = true;
            try {
                const r = await axios.get(`${API}/kepatuhan/mandek`, {
                    params: { limit: 8, hari: 7, ...this.saringan },
                });
                this.mandek = { rows: r.data.result || [], total: r.data.total_data ?? (r.data.result || []).length };
            } catch (e) {
                this.mandek = { rows: [], total: 0 };
            } finally {
                this.loading.mandek = false;
            }
        },
    },
};
</script>

<style scoped>
/* Custom Scoped Component Styles for Papan Risiko Dokumen */
.pr-card {
    position: relative;
    border-radius: 20px;
    background: linear-gradient(180deg, var(--hcis-white) 0%, #fafbfc 100%);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow:
        0 12px 32px -8px rgba(15, 23, 42, 0.07),
        0 4px 12px -4px rgba(15, 23, 42, 0.02);
    padding: 1.4rem 1.6rem;
}

/* Header */
.pr-header {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.2rem;
    padding-bottom: 1.1rem;
    border-bottom: 1px dashed var(--hcis-slate-200);
}

.pr-header-info {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}

.pr-hero-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.14) 0%, rgba(245, 158, 11, 0.08) 100%);
    border: 1px solid rgba(239, 68, 68, 0.25);
    display: grid;
    place-items: center;
    position: relative;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.12);
}

.pr-ico-danger {
    font-size: 1.25rem;
    color: var(--hcis-red-500);
}

.pr-pulse-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--hcis-red-500);
    box-shadow:
        0 0 0 2px var(--hcis-white),
        0 0 10px var(--hcis-red-500);
    animation: pr-pulse-anim 1.8s infinite;
}

@keyframes pr-pulse-anim {
    0% {
        transform: scale(0.9);
        opacity: 1;
    }
    50% {
        transform: scale(1.25);
        opacity: 0.7;
    }
    100% {
        transform: scale(0.9);
        opacity: 1;
    }
}

.pr-eyebrow {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.pr-title {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.02em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    line-height: 1.25;
}

.pr-badge-kritis-pill {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1));
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.15rem 0.55rem;
}

.pr-subtitle {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    margin: 0;
    line-height: 1.35;
}

/* Quick Summary Stat Chips */
.pr-stats-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-left: auto;
}

.pr-stat-chip {
    display: flex;
    flex-direction: column;
    padding: 0.4rem 0.85rem;
    border-radius: 12px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
}

.pr-stat-chip:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
}

.pr-stat-chip.danger {
    background: rgba(239, 68, 68, 0.04);
    border-color: rgba(239, 68, 68, 0.2);
    color: var(--hcis-red-600);
}
.pr-stat-chip.danger.active {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1));
    border-color: var(--hcis-red-600);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
}

.pr-stat-chip.warning {
    background: rgba(245, 158, 11, 0.04);
    border-color: rgba(245, 158, 11, 0.2);
    color: var(--hcis-amber-600);
}
.pr-stat-chip.warning.active {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1));
    border-color: var(--hcis-amber-600);
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.2);
}

.pr-stat-chip.info {
    background: rgba(59, 130, 246, 0.04);
    border-color: rgba(59, 130, 246, 0.2);
    color: var(--hcis-blue-600);
    cursor: default;
}
.pr-stat-chip.primary {
    background: rgba(99, 102, 241, 0.04);
    border-color: rgba(99, 102, 241, 0.2);
    color: var(--hcis-indigo-600);
    cursor: default;
}

.chip-label {
    font-size: 0.63rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.85;
}

.chip-val {
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.chip-val .unit {
    font-size: 0.68rem;
    font-weight: 600;
    opacity: 0.75;
}

/* Toolbar Controls */
.pr-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1.1rem;
}

/* Segmented Control */
.pr-filter-seg {
    display: flex;
    align-items: center;
    gap: 3px;
    background: var(--hcis-slate-100);
    padding: 4px;
    border-radius: 14px;
    border: 1px solid var(--hcis-slate-200);
}

.pr-filter-btn {
    border: none;
    background: transparent;
    border-radius: 10px;
    padding: 0.38rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
}

.pr-filter-btn:hover {
    color: var(--hcis-slate-800);
    background: rgba(255, 255, 255, 0.6);
}

.pr-filter-btn.active {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.pr-filter-btn.btn-kritis.active {
    background: linear-gradient(135deg, var(--hcis-red-500), var(--hcis-red-600));
    color: var(--hcis-white);
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
}

.pr-dot-kritis {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-red-500);
    margin-right: 5px;
    box-shadow: 0 0 6px var(--hcis-red-500);
}
.pr-filter-btn.btn-kritis.active .pr-dot-kritis {
    background: var(--hcis-white);
    box-shadow: 0 0 6px var(--hcis-white);
}

.pr-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-left: auto;
}

/* Custom Select */
.pr-select-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.pr-select-ico {
    position: absolute;
    left: 0.75rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
    pointer-events: none;
}
.pr-select {
    appearance: none;
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 12px;
    padding: 0.42rem 1.8rem 0.42rem 2.2rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.pr-select:hover,
.pr-select:focus {
    border-color: var(--hcis-indigo-500);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Custom View Switcher */
.pr-view-toggle {
    display: flex;
    background: var(--hcis-slate-100);
    padding: 3px;
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
}
.pr-view-toggle button {
    border: none;
    background: transparent;
    border-radius: 9px;
    padding: 0.35rem 0.65rem;
    font-size: 0.82rem;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: all 0.18s ease;
    display: grid;
    place-items: center;
}
.pr-view-toggle button.active {
    background: var(--hcis-white);
    color: var(--hcis-indigo-600);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

/* Custom Search */
.pr-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 190px;
}
.pr-search-ico {
    position: absolute;
    left: 0.75rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-400);
    pointer-events: none;
}
.pr-search-input {
    width: 100%;
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 12px;
    padding: 0.42rem 2rem 0.42rem 2.2rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-800);
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.pr-search-input:focus {
    border-color: var(--hcis-indigo-500);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.pr-btn-clear {
    position: absolute;
    right: 0.5rem;
    border: none;
    background: none;
    color: var(--hcis-slate-400);
    cursor: pointer;
    padding: 0;
    display: grid;
    place-items: center;
}
.pr-btn-clear:hover {
    color: var(--hcis-red-500);
}

/* Custom Modern Table */
.pr-table-wrapper {
    overflow-x: auto;
    border-radius: 14px;
}
.pr-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 6px;
}
.pr-table th {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.75rem 1rem;
    border: none;
}
.pr-table th:first-child {
    border-top-left-radius: 10px;
    border-bottom-left-radius: 10px;
}
.pr-table th:last-child {
    border-top-right-radius: 10px;
    border-bottom-right-radius: 10px;
}

.pr-table-row {
    background: var(--hcis-white);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.pr-table-row td {
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--hcis-slate-100);
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: middle;
}
.pr-table-row td:first-child {
    border-left: 1px solid var(--hcis-slate-100);
    border-top-left-radius: 12px;
    border-bottom-left-radius: 12px;
}
.pr-table-row td:last-child {
    border-right: 1px solid var(--hcis-slate-100);
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
}

.pr-table-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px -6px rgba(15, 23, 42, 0.08);
}
.pr-table-row:hover td {
    background-color: #fafbfc;
    border-color: var(--hcis-slate-200);
}

.pr-row-KRITIS td:first-child {
    border-left: 4px solid var(--hcis-red-500) !important;
}
.pr-row-TINGGI td:first-child {
    border-left: 4px solid var(--hcis-amber-500) !important;
}
.pr-row-NORMAL td:first-child {
    border-left: 4px solid var(--hcis-indigo-500) !important;
}

/* Rank Badges */
.pr-rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-white);
}
.pr-rank-badge.rank-1 {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
    box-shadow: 0 3px 8px rgba(245, 158, 11, 0.4);
}
.pr-rank-badge.rank-2 {
    background: linear-gradient(135deg, var(--hcis-slate-400), var(--hcis-slate-500));
    box-shadow: 0 3px 8px rgba(148, 163, 184, 0.4);
}
.pr-rank-badge.rank-3 {
    background: linear-gradient(135deg, var(--hcis-amber-700), var(--hcis-amber-900));
    box-shadow: 0 3px 8px rgba(180, 83, 9, 0.4);
}

.pr-rank-num {
    font-weight: 800;
    color: var(--hcis-slate-400);
    font-size: 0.8rem;
}

.pr-sop-title {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.pr-telat-maks-text {
    color: var(--hcis-red-600);
    font-size: 0.68rem;
    font-weight: 700;
}

/* Priority Badges */
.pr-pri-pill {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    border-radius: 999px;
    padding: 0.22rem 0.65rem;
    display: inline-flex;
    align-items: center;
}
.pr-pri-pill.p-KRITIS {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.16), rgba(220, 38, 38, 0.1));
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.pr-pri-pill.p-TINGGI {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(217, 119, 6, 0.1));
    color: var(--hcis-amber-800);
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.pr-pri-pill.p-NORMAL {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.14), rgba(79, 70, 229, 0.08));
    color: var(--hcis-indigo-700);
    border: 1px solid rgba(99, 102, 241, 0.25);
}

/* Target & Status Pills */
.pr-target-text {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-variant-numeric: tabular-nums;
}
.pr-sub-target {
    font-weight: 600;
    color: var(--hcis-slate-500);
}

.pr-badge-telat {
    background: rgba(239, 68, 68, 0.1);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.25);
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 6px;
    padding: 0.18rem 0.55rem;
}
.pr-badge-belum {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 6px;
    padding: 0.18rem 0.55rem;
}
.pr-badge-selesai {
    background: rgba(16, 185, 129, 0.1);
    color: var(--hcis-emerald-700);
    border: 1px solid rgba(16, 185, 129, 0.25);
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 6px;
    padding: 0.18rem 0.55rem;
}

.pr-pct-text {
    font-size: 0.82rem;
    font-weight: 800;
}
.pr-pct-text.is-danger {
    color: var(--hcis-red-600);
}
.pr-pct-text.is-warning {
    color: var(--hcis-amber-600);
}
.pr-pct-text.is-success {
    color: var(--hcis-emerald-600);
}

/* Custom Action Button */
.pr-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--hcis-white), var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-700);
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.4rem 0.85rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.pr-action-btn:hover {
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
}

/* Custom Grid Cards */
.pr-grid-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 1.2rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}
.pr-grid-card:hover {
    transform: translateY(-4px);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 16px 32px -12px rgba(15, 23, 42, 0.12);
}

.pr-card-KRITIS {
    border-top: 4px solid var(--hcis-red-500);
}
.pr-card-TINGGI {
    border-top: 4px solid var(--hcis-amber-500);
}
.pr-card-NORMAL {
    border-top: 4px solid var(--hcis-indigo-500);
}

.pr-rank-badge-sm {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
}
.pr-rank-badge-sm.rank-1 {
    background: rgba(245, 158, 11, 0.15);
    color: var(--hcis-amber-700);
}
.pr-rank-badge-sm.rank-2 {
    background: rgba(148, 163, 184, 0.18);
    color: var(--hcis-slate-600);
}
.pr-rank-badge-sm.rank-3 {
    background: rgba(180, 83, 9, 0.15);
    color: var(--hcis-amber-900);
}

.pr-card-progress-box {
    padding: 0.75rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    margin-bottom: 0.85rem;
}

.pr-loading-box {
    text-align: center;
    padding: 3rem 1rem;
}
.pr-loading-text {
    color: var(--hcis-slate-500);
    font-size: 0.82rem;
    font-weight: 600;
}

/* Card Icon Headers */
.pr-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.pr-card-icon.kilat {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(245, 158, 11, 0.25);
}
.pr-card-icon.mandek {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.pr-badge-kilat-count {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 800;
}

.pr-badge-mandek-count {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 800;
}

.pr-avatar {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}
.pr-avatar.warm {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.25);
}

.pr-emp-link {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
    transition: color 0.15s ease;
}
.pr-emp-link:hover {
    color: var(--hcis-indigo-600);
    text-decoration: underline;
}

.pr-page-badge {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 6px;
    padding: 0.15rem 0.5rem;
}

.pr-tag-danger {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.pr-badge-mandek-day {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(245, 158, 11, 0.25);
    font-size: 0.72rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
}

/* Legacy Support for remaining components */
.avatar.sm {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: #fff;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.avatar.sm.warm {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
}

.emp-link {
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: color 0.15s ease;
}
.emp-link:hover {
    color: var(--hcis-indigo-600) !important;
    text-decoration: underline !important;
}

.temuan {
    display: inline-block;
    font-size: 0.64rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.12rem 0.5rem;
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.pri {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    border-radius: 999px;
    padding: 0.12rem 0.55rem;
}
.pri.p-KRITIS {
    background: rgba(239, 68, 68, 0.14);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.25);
}
.pri.p-TINGGI {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-800);
    border: 1px solid rgba(245, 158, 11, 0.25);
}
.pri.p-NORMAL {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.22);
}

.fs-085 {
    font-size: 0.85rem;
}

.tr-hover:hover {
    background-color: var(--hcis-slate-50) !important;
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
