<template>
    <div class="plt">
        <!-- ══ 1. EXECUTIVE HERO: AVERAGE LEAD TIME & BENCHMARK PILLS ══ -->
        <div class="plt-headline">
            <div class="plt-metric-main">
                <div class="plt-metric-val">
                    <span class="plt-num">{{ leadTime.Rerata_Hari || 0 }}</span>
                    <span class="plt-unit">hari</span>
                </div>
                <div class="plt-metric-sub">
                    <strong>Rata-rata Lead Time</strong>
                    <small>Pengajuan &rarr; Terbit Resmi</small>
                </div>
            </div>

            <div class="plt-chips">
                <div class="plt-chip is-cepat" title="Lead time tercepat penerbitan dokumen">
                    <HcisIcon name="lightning-charge-fill" size="2xs" />
                    <span>Tercepat: <strong>{{ leadTime.Tercepat_Hari ?? 0 }} hari</strong></span>
                </div>
                <div class="plt-chip is-lama" title="Lead time terlama penerbitan dokumen">
                    <HcisIcon name="hourglass-split" size="2xs" />
                    <span>Terlama: <strong>{{ leadTime.Terlama_Hari ?? 0 }} hari</strong></span>
                </div>
                <!-- Bukan "Total Terbit" lagi: angka itu ditanya orang karena
                     terbaca sebagai jumlah dokumen, lalu tak cocok dengan kartu
                     "Dokumen Terupload" di strip atas. Yang diukur di sini
                     hanyalah dokumen yang SUDAH PERNAH TERBIT — sisanya belum
                     punya tanggal terbit untuk dihitung lead time-nya. -->
                <div
                    class="plt-chip is-total"
                    title="Dokumen yang sudah punya versi terbit — hanya inilah yang punya lead time. Sisanya masih dalam proses, jadi belum ada tanggal terbit untuk dihitung."
                >
                    <HcisIcon name="file-earmark-check-fill" size="2xs" />
                    <span>Sudah terbit: <strong>{{ leadTime.Total_Dokumen_Terbit ?? 0 }} dok</strong></span>
                </div>
            </div>
        </div>

        <!-- ══ 2. PROPORTIONAL SPECTRUM BAR ══ -->
        <div v-if="totalDistribusi > 0" class="plt-spectrum-card">
            <div class="plt-spectrum-bar" role="progressbar" aria-label="Proporsi Kecepatan Penerbitan">
                <span
                    v-if="distribusi.kurang_7 > 0"
                    class="plt-spec-seg is-kilat"
                    :style="{ width: `${persen(distribusi.kurang_7, totalDistribusi)}%` }"
                    :title="`< 7 Hari: ${distribusi.kurang_7} dokumen (${persen(distribusi.kurang_7, totalDistribusi)}%)`"
                ></span>
                <span
                    v-if="distribusi['7_sampai_14'] > 0"
                    class="plt-spec-seg is-ideal"
                    :style="{ width: `${persen(distribusi['7_sampai_14'], totalDistribusi)}%` }"
                    :title="`7–14 Hari: ${distribusi['7_sampai_14']} dokumen (${persen(distribusi['7_sampai_14'], totalDistribusi)}%)`"
                ></span>
                <span
                    v-if="distribusi['15_sampai_30'] > 0"
                    class="plt-spec-seg is-standar"
                    :style="{ width: `${persen(distribusi['15_sampai_30'], totalDistribusi)}%` }"
                    :title="`15–30 Hari: ${distribusi['15_sampai_30']} dokumen (${persen(distribusi['15_sampai_30'], totalDistribusi)}%)`"
                ></span>
                <span
                    v-if="distribusi.lebih_30 > 0"
                    class="plt-spec-seg is-lama"
                    :style="{ width: `${persen(distribusi.lebih_30, totalDistribusi)}%` }"
                    :title="`> 30 Hari: ${distribusi.lebih_30} dokumen (${persen(distribusi.lebih_30, totalDistribusi)}%)`"
                ></span>
            </div>

            <!-- ══ 3. 4 SPEED BRACKETS POWERED BY OFFICIAL HCIS STAT GROUP COMPACT ══ -->
            <HcisStatGroup
                :cols="4"
                gap="sm"
                layout="compact"
                appearance="crystal"
                class="plt-dist-group"
            >
                <HcisStatCard
                    v-for="b in statsDistribusi"
                    :key="b.id"
                    :label="b.label"
                    :value="b.value"
                    suffix="dok"
                    :sentiment="b.sentiment"
                    :icon="b.icon"
                    layout="compact"
                    appearance="crystal"
                    :caption="`${b.keterangan} · ${b.pct}%`"
                    :progress="b.pct"
                    :interactive="true"
                    class="plt-stat-card"
                    :class="[`is-${b.id}`, { 'is-active': filterKecepatan === b.id }]"
                    @click="toggleFilterKecepatan(b.id)"
                />
            </HcisStatGroup>
        </div>

        <!-- ══ 4. DAFTAR DOKUMEN & DURASI LEAD TIME ══ -->
        <div v-if="daftar.length" class="plt-daftar-section">
            <div class="plt-section-head">
                <div class="plt-section-title">
                    <HcisIcon name="journal-check" size="xs" class="text-brand-600" />
                    <span>Dokumen Penerbitan Terakhir</span>
                    <span class="plt-count-badge">{{ daftarTersaring.length }}</span>
                </div>

                <div class="plt-section-controls">
                    <!-- Quick Speed Filter Reset / Indicator -->
                    <button
                        v-if="filterKecepatan"
                        type="button"
                        class="plt-filter-reset"
                        @click="filterKecepatan = null"
                    >
                        <span>Filter: {{ labelFilterKecepatan }}</span>
                        <HcisIcon name="x" size="2xs" />
                    </button>

                    <!-- Search Input -->
                    <div class="plt-search-box">
                        <HcisIcon name="search" size="2xs" class="plt-search-icon" />
                        <input
                            v-model="cari"
                            type="text"
                            placeholder="Cari dokumen…"
                            class="plt-search-input"
                        />
                        <button
                            v-if="cari"
                            type="button"
                            class="plt-search-clear"
                            @click="cari = ''"
                        >
                            <HcisIcon name="x" size="3xs" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- List of documents -->
            <div v-if="daftarTersaring.length" class="plt-list" role="list">
                <div
                    v-for="d in daftarTampil"
                    :key="d.Id_Dokumen || d.Nomor"
                    class="plt-list-item"
                >
                    <div class="plt-item-info">
                        <div class="plt-item-top">
                            <span class="plt-item-badge">{{ d.Kode_Jenis || d.Jenis }}</span>
                            <span class="plt-item-no">{{ d.Nomor || '—' }}</span>
                            <span v-if="d.Departemen" class="plt-item-dept">{{ d.Departemen }}</span>
                        </div>
                        <span class="plt-item-judul" :title="d.Judul">{{ d.Judul }}</span>
                        <div class="plt-item-dates">
                            <span>Mulai: <strong>{{ fmtTgl(d.Tanggal_Mulai) }}</strong></span>
                            <span class="plt-arrow">&rarr;</span>
                            <span>Terbit: <strong>{{ fmtTgl(d.Tanggal_Publish) }}</strong></span>
                        </div>
                    </div>

                    <div class="plt-item-durasi">
                        <span
                            class="plt-durasi-tag"
                            :class="sentimenDurasi(d.Hari)"
                        >
                            {{ d.Hari }} hari
                        </span>
                        <button
                            type="button"
                            class="plt-btn-buka"
                            title="Buka dokumen di Master Dokumen"
                            @click="keDokumen(d)"
                        >
                            <HcisIcon name="box-arrow-up-right" size="xs" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="plt-search-empty">
                <HcisIcon name="search" size="xs" class="text-slate-400" />
                <span>Tidak ada dokumen yang cocok dengan filter.</span>
            </div>

            <!-- View More / Collapse Toggle -->
            <div v-if="daftarTersaring.length > BATAS" class="plt-more-wrap">
                <button
                    type="button"
                    class="plt-more-btn"
                    @click="tampilkanSemua = !tampilkanSemua"
                >
                    <HcisIcon :name="tampilkanSemua ? 'chevron-up' : 'chevron-down'" size="2xs" />
                    <span>{{ tampilkanSemua ? 'Ringkas Daftar' : `Tampilkan ${daftarTersaring.length - BATAS} Dokumen Lainnya` }}</span>
                </button>
            </div>
        </div>

        <div v-else class="plt-kosong">
            <HcisIcon name="info-circle" size="xs" class="me-1 text-slate-400" />
            <span>Belum ada dokumen dengan tanggal respon dan penerbitan terbit.</span>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { HcisStatGroup, HcisStatCard, HcisIcon } from '@/components/ui';
import { tanggal } from '../agregat.js';

const BATAS = 6;

export default {
    name: 'PanelLeadTime',
    components: { HcisStatGroup, HcisStatCard, HcisIcon },
    props: {
        leadTime: {
            type: Object,
            default: () => ({
                Total_Dokumen_Terbit: 0,
                Rerata_Hari: 0,
                Tercepat_Hari: 0,
                Terlama_Hari: 0,
                Distribusi: {},
                Daftar: [],
            }),
        },
    },
    data() {
        return {
            filterKecepatan: null,
            cari: '',
            tampilkanSemua: false,
            BATAS,
        };
    },
    computed: {
        distribusi() {
            return this.leadTime?.Distribusi || {};
        },
        daftar() {
            return this.leadTime?.Daftar || [];
        },
        totalDistribusi() {
            const d = this.distribusi;
            return (d.kurang_7 || 0) + (d['7_sampai_14'] || 0) + (d['15_sampai_30'] || 0) + (d.lebih_30 || 0);
        },
        statsDistribusi() {
            const d = this.distribusi;
            const tot = this.totalDistribusi;
            return [
                {
                    id: 'kilat',
                    label: '< 7 Hari',
                    value: d.kurang_7 || 0,
                    pct: this.persen(d.kurang_7, tot),
                    icon: 'lightning-charge-fill',
                    sentiment: 'success',
                    keterangan: 'Sangat Cepat',
                },
                {
                    id: 'ideal',
                    label: '7 – 14 Hari',
                    value: d['7_sampai_14'] || 0,
                    pct: this.persen(d['7_sampai_14'], tot),
                    icon: 'check-circle-fill',
                    sentiment: 'brand',
                    keterangan: 'Ideal',
                },
                {
                    id: 'standar',
                    label: '15 – 30 Hari',
                    value: d['15_sampai_30'] || 0,
                    pct: this.persen(d['15_sampai_30'], tot),
                    icon: 'calendar-check-fill',
                    sentiment: 'warning',
                    keterangan: 'Standar',
                },
                {
                    id: 'lama',
                    label: '> 30 Hari',
                    value: d.lebih_30 || 0,
                    pct: this.persen(d.lebih_30, tot),
                    icon: 'exclamation-triangle-fill',
                    sentiment: 'danger',
                    keterangan: 'Perlu Akselerasi',
                },
            ];
        },
        labelFilterKecepatan() {
            const map = {
                kilat: '< 7 Hari (Sangat Cepat)',
                ideal: '7–14 Hari (Ideal)',
                standar: '15–30 Hari (Standar)',
                lama: '> 30 Hari (Perlu Akselerasi)',
            };
            return map[this.filterKecepatan] || '';
        },
        daftarTersaring() {
            let list = this.daftar;

            if (this.filterKecepatan) {
                list = list.filter((d) => this.sentimenDurasi(d.Hari) === `is-${this.filterKecepatan}`);
            }

            if (this.cari.trim()) {
                const q = this.cari.toLowerCase().trim();
                list = list.filter((d) =>
                    (d.Judul && d.Judul.toLowerCase().includes(q)) ||
                    (d.Nomor && d.Nomor.toLowerCase().includes(q)) ||
                    (d.Departemen && d.Departemen.toLowerCase().includes(q)) ||
                    (d.Kode_Jenis && d.Kode_Jenis.toLowerCase().includes(q))
                );
            }

            return list;
        },
        daftarTampil() {
            if (this.tampilkanSemua || this.daftarTersaring.length <= BATAS) {
                return this.daftarTersaring;
            }
            return this.daftarTersaring.slice(0, BATAS);
        },
    },
    methods: {
        persen(nilai, total) {
            if (!total || !nilai) return 0;
            return Math.round((nilai * 100) / total);
        },
        toggleFilterKecepatan(kategori) {
            this.filterKecepatan = this.filterKecepatan === kategori ? null : kategori;
            this.tampilkanSemua = false;
        },
        fmtTgl(val) {
            return tanggal(val);
        },
        sentimenDurasi(hari) {
            const h = Number(hari) || 0;
            if (h < 7) return 'is-kilat';
            if (h <= 14) return 'is-ideal';
            if (h <= 30) return 'is-standar';
            return 'is-lama';
        },
        keDokumen(d) {
            if (d.Id_Dokumen) {
                router.visit(`/sop/admin/dokumen?dok=${encodeURIComponent(d.Id_Dokumen)}`);
            } else {
                router.visit('/sop/admin/dokumen');
            }
        },
    },
};
</script>

<style scoped>
.plt {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* ══ 1. HEADLINE COCKPIT ══ */
.plt-headline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.85rem 1.125rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.8) 0%, rgba(255, 255, 255, 0.95) 100%);
    border: 1px solid rgba(199, 210, 254, 0.85);
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.08);
}

.plt-metric-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.plt-metric-val {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.plt-num {
    font-size: 2.15rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

.plt-unit {
    font-size: 0.85rem;
    font-weight: 750;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.plt-metric-sub {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}

.plt-metric-sub strong {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.plt-metric-sub small {
    font-size: 0.7125rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.plt-chips {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.plt-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.735rem;
    font-weight: 600;
    border: 1px solid transparent;
    font-variant-numeric: tabular-nums;
}

.plt-chip.is-cepat {
    background: rgba(236, 253, 245, 0.9);
    border-color: rgba(167, 243, 208, 0.9);
    color: var(--hcis-emerald-800);
}

.plt-chip.is-lama {
    background: rgba(254, 243, 199, 0.9);
    border-color: rgba(253, 230, 138, 0.9);
    color: var(--hcis-amber-800);
}

.plt-chip.is-total {
    background: rgba(238, 242, 255, 0.9);
    border-color: rgba(199, 210, 254, 0.9);
    color: var(--hcis-indigo-800);
}

/* ══ 2. PROPORTIONAL SPECTRUM BAR ══ */
.plt-spectrum-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.plt-spectrum-bar {
    height: 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    display: flex;
    overflow: hidden;
}

.plt-spec-seg {
    height: 100%;
    transition: width 0.3s ease;
}

.plt-spec-seg.is-kilat { background: var(--hcis-emerald-500); }
.plt-spec-seg.is-ideal { background: var(--hcis-indigo-600); }
.plt-spec-seg.is-standar { background: var(--hcis-amber-500); }
.plt-spec-seg.is-lama { background: var(--hcis-red-500); }

/* ══ 3. HCIS STAT CARD DISTRIBUSI SPEED BRACKETS ══ */
.plt-dist-group {
    width: 100%;
}

.plt-stat-card {
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
}

.plt-stat-card:hover {
    transform: translateY(-2px);
}

.plt-stat-card.is-active {
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.6), 0 4px 14px rgba(99, 102, 241, 0.15) !important;
    transform: translateY(-1px);
}

/* ══ 4. DAFTAR DOKUMEN ══ */
.plt-daftar-section {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding-top: 0.35rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.plt-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.plt-section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78125rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.plt-count-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.05rem 0.4rem;
    border-radius: 9999px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-variant-numeric: tabular-nums;
}

.plt-section-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.plt-filter-reset {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: rgba(238, 242, 255, 0.8);
    color: var(--hcis-indigo-700);
    font-size: 0.6875rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.16s ease;
}

.plt-filter-reset:hover {
    background: var(--hcis-indigo-100);
}

.plt-search-box {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.plt-search-icon {
    position: absolute;
    left: 0.5rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    pointer-events: none;
}

.plt-search-input {
    padding: 0.25rem 1.4rem 0.25rem 1.6rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: var(--hcis-white);
    font-size: 0.735rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    width: 140px;
    transition: all 0.16s ease;
}

.plt-search-input:focus {
    outline: none;
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    width: 180px;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.plt-search-clear {
    position: absolute;
    right: 0.35rem;
    background: transparent;
    border: none;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
}

.plt-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.plt-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.45rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: rgba(255, 255, 255, 0.85);
    transition: all 0.16s ease;
}

.plt-list-item:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.1);
}

.plt-item-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.plt-item-top {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.plt-item-badge {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.plt-item-no {
    font-size: 0.7rem;
    font-weight: 750;
    font-family: monospace;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.plt-item-dept {
    font-size: 0.7rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.plt-item-judul {
    font-size: 0.78125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.plt-item-dates {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.plt-item-dates strong {
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-weight: 700;
}

.plt-arrow {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-weight: 700;
}

.plt-item-durasi {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.plt-durasi-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.7125rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.plt-durasi-tag.is-kilat { background: var(--hcis-emerald-100); color: var(--hcis-emerald-800); }
.plt-durasi-tag.is-ideal { background: var(--hcis-indigo-100); color: var(--hcis-indigo-800); }
.plt-durasi-tag.is-standar { background: var(--hcis-amber-100); color: var(--hcis-amber-800); }
.plt-durasi-tag.is-lama { background: var(--hcis-red-100); color: var(--hcis-red-800); }

.plt-btn-buka {
    width: 1.65rem;
    height: 1.65rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: var(--hcis-white);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    cursor: pointer;
    transition: all 0.16s ease;
}

.plt-btn-buka:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
}

.plt-search-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 1.25rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.plt-more-wrap {
    display: flex;
    justify-content: center;
    padding-top: 0.25rem;
}

.plt-more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    background: rgba(248, 250, 252, 0.85);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.735rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}

.plt-more-btn:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
}

.plt-kosong {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    font-size: 0.75rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

@media (max-width: 767.98px) {
    .plt-section-controls {
        width: 100%;
        justify-content: space-between;
    }
    .plt-search-input {
        width: 100%;
    }
    .plt-search-input:focus {
        width: 100%;
    }
}
</style>
