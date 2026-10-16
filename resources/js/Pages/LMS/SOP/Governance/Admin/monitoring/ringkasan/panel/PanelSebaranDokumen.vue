<template>
    <div class="psd">
        <!-- ══ PEMILIH SUMBU (Crystal Segmented Control) ══ -->
        <div class="psd-sumbu" role="tablist" aria-label="Pilih dasar sebaran dokumen">
            <button
                v-for="s in sumbu"
                :key="s.Kunci"
                type="button"
                role="tab"
                class="psd-sumbu-btn"
                :class="{ 'is-aktif': s.Kunci === aktif }"
                :aria-selected="s.Kunci === aktif"
                :title="s.Keterangan"
                @click="pilihSumbu(s.Kunci)"
            >
                <HcisIcon :name="s.Ikon || 'grid-fill'" size="xs" />
                <span>{{ s.Nama }}</span>
                <span class="psd-sumbu-count">{{ s.Baris }}</span>
            </button>
        </div>

        <!-- ══ HEADER RINGKASAN & LEGENDA ══ -->
        <div class="psd-header">
            <div class="psd-meta">
                <span class="psd-stat">
                    <strong>{{ angka(total) }}</strong> dokumen aktif
                    <span class="psd-stat-sub">di {{ baris.length }} {{ satuan }}</span>
                </span>

                <div class="psd-badges">
                    <span
                        v-if="kosong > 0"
                        class="psd-badge is-kosong"
                        :title="`${kosong} ${satuan} tanpa satu dokumen pun — potensi lubang tata kelola`"
                    >
                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                        {{ kosong }} {{ satuan }} kosong
                    </span>
                    <span
                        v-if="totalBelumBeredar > 0"
                        class="psd-badge is-belum"
                        title="Dokumen sah di master yang belum dimasukkan ke paket mana pun"
                    >
                        <HcisIcon name="clock-history" size="2xs" />
                        {{ totalBelumBeredar }} belum beredar
                    </span>
                </div>
            </div>

            <!-- Legenda Interaktif (berfungsi sebagai filter sorotan) -->
            <div class="psd-legenda" role="toolbar" aria-label="Saring sorotan kelompok dokumen">
                <button
                    type="button"
                    class="psd-lg-btn"
                    :class="{ 'is-aktif': sorot === null }"
                    title="Tampilkan semua kelompok dokumen"
                    @click="setSorot(null)"
                >
                    <HcisIcon name="grid-fill" size="2xs" class="psd-lg-icon" />
                    <span>Semua</span>
                    <span class="psd-lg-count">{{ angka(totalBatang) }}</span>
                </button>

                <button
                    v-for="l in legenda"
                    :key="l.kunci"
                    type="button"
                    class="psd-lg-btn"
                    :class="[`is-${l.kunci}`, { 'is-aktif': sorot === l.kunci }]"
                    :title="`Sorot kelompok ${l.label}: ${angka(totalKelompok(l.medan))} dokumen`"
                    @click="setSorot(l.kunci)"
                >
                    <i class="psd-dot" :class="`is-${l.kunci}`"></i>
                    <span>{{ l.label }}</span>
                    <span class="psd-lg-count">{{ angka(totalKelompok(l.medan)) }}</span>
                </button>
            </div>
        </div>

        <!-- ══ PERINGATAN SUMBU YANG TIDAK MENJUMLAH ══ -->
        <div v-if="catatan" class="psd-catatan">
            <HcisIcon name="info-circle-fill" size="xs" />
            <span>{{ catatan }}</span>
        </div>

        <!-- ══ DAFTAR BARIS ══ -->
        <HcisEmptyState
            v-if="!baris.length"
            icon="inbox"
            title="Belum ada yang bisa disebar"
            :description="`Belum ada ${satuan} yang tercatat untuk dasar sebaran ini.`"
            variant="flat"
            sentiment="neutral"
            size="sm"
        />

        <div v-else class="psd-daftar" role="list">
            <div v-for="b in barisTampil" :key="kunciBaris(b)" class="psd-item">
                <div
                    class="psd-baris"
                    :class="{
                        'is-kosong': !b.Total,
                        'is-tanpa': b.Tanpa,
                        'is-terbuka': dibuka === kunciBaris(b),
                        'has-data': b.Total > 0,
                    }"
                    :tabindex="b.Total > 0 ? 0 : -1"
                    role="button"
                    :aria-expanded="dibuka === kunciBaris(b)"
                    @click="toggleDetail(b)"
                    @keydown.enter.prevent="toggleDetail(b)"
                    @keydown.space.prevent="toggleDetail(b)"
                >
                    <!-- Label: lencana, kode, nama -->
                    <div class="psd-label">
                        <span v-if="b.Label" class="psd-lencana">{{ b.Label }}</span>
                        <span v-if="b.Kode" class="psd-kode" :title="`Kode: ${b.Kode}`">{{ b.Kode }}</span>
                        <span class="psd-nama" :title="b.Nama">{{ b.Nama }}</span>
                    </div>

                    <!-- Batang bertumpuk proporsional -->
                    <div class="psd-bar-wrapper">
                        <div class="psd-bar" :aria-label="`${b.Nama}: ${b.Total} dokumen`">
                            <span
                                v-for="s in segmen(b)"
                                :key="s.kunci"
                                class="psd-seg"
                                :class="[
                                    `is-${s.kunci}`,
                                    {
                                        'is-sorot': sorot === s.kunci,
                                        'is-redup': sorot !== null && sorot !== s.kunci,
                                    },
                                ]"
                                :style="{ width: `${s.lebar}%` }"
                                :title="`${s.label}: ${s.nilai} dokumen (${persen(s.nilai, b.Total)}%)`"
                            >
                                <span v-if="s.lebar >= 14" class="psd-seg-label">{{ s.nilai }}</span>
                            </span>

                            <span v-if="!b.Total" class="psd-nihil">
                                <HcisIcon name="dash-circle" size="2xs" class="me-1" />belum ada dokumen
                            </span>
                        </div>
                    </div>

                    <!-- Angka & pemicu rincian -->
                    <div class="psd-kanan">
                        <div class="psd-total-wrap">
                            <span class="psd-total">{{ b.Total }}</span>
                            <span v-if="total > 0 && b.Total > 0" class="psd-proporsi">
                                {{ persen(b.Total, total) }}%
                            </span>
                        </div>

                        <button
                            v-if="b.Total > 0"
                            type="button"
                            class="psd-toggle"
                            :class="{ 'is-terbuka': dibuka === kunciBaris(b) }"
                            :title="dibuka === kunciBaris(b) ? 'Tutup rincian' : 'Buka rincian kelompok'"
                            @click.stop="toggleDetail(b)"
                        >
                            <HcisIcon name="chevron-down" size="xs" />
                        </button>
                    </div>
                </div>

                <!-- Rincian kelompok -->
                <Transition name="psd-expand">
                    <div v-if="dibuka === kunciBaris(b) && b.Total > 0" class="psd-detail">
                        <div class="psd-detail-grid">
                            <div
                                v-for="l in legenda"
                                :key="l.kunci"
                                class="psd-detail-col"
                                :class="`is-${l.kunci}`"
                            >
                                <div class="psd-detail-head">
                                    <i class="psd-dot" :class="`is-${l.kunci}`"></i>
                                    <span>{{ l.label }}</span>
                                </div>
                                <div class="psd-detail-val">
                                    <strong>{{ b[l.medan] || 0 }}</strong>
                                    <small>{{ persen(b[l.medan] || 0, b.Total) }}%</small>
                                </div>
                                <span class="psd-detail-sub">{{ l.sub }}</span>
                            </div>
                        </div>

                        <div class="psd-detail-footer">
                            <span class="psd-detail-info">
                                Proporsi: <strong>{{ persen(b.Total, total) }}%</strong> dari total master.
                            </span>
                            <button
                                v-if="tautan(b)"
                                type="button"
                                class="psd-detail-link"
                                @click.stop="bukaDaftar(b)"
                            >
                                <span>Buka Daftar</span>
                                <HcisIcon name="arrow-right" size="2xs" />
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Sisa baris yang belum ditampilkan -->
            <button
                v-if="tersisa > 0"
                type="button"
                class="psd-lagi"
                @click="semua = true"
            >
                <HcisIcon name="chevron-down" size="2xs" class="me-1" />
                Tampilkan {{ tersisa }} {{ satuan }} lainnya
            </button>
            <button
                v-else-if="semua && baris.length > BATAS"
                type="button"
                class="psd-lagi"
                @click="semua = false"
            >
                <HcisIcon name="chevron-up" size="2xs" class="me-1" />
                Ringkas kembali
            </button>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { HcisEmptyState, HcisIcon } from '@/components/ui';
import { angka } from '../agregat.js';

const LEGENDA = [
    { kunci: 'fungsional', label: 'Ditugaskan', medan: 'Fungsional', sub: 'Wajib & kepatuhan diukur' },
    { kunci: 'publik', label: 'Beredar', medan: 'Publik', sub: 'Beredar, dibaca sukarela' },
    { kunci: 'belum', label: 'Belum beredar', medan: 'Belum_Beredar', sub: 'Sah, belum masuk paket' },
];

const BATAS = 10;

export default {
    name: 'PanelSebaranDokumen',
    components: { HcisEmptyState, HcisIcon },
    props: {
        sebaran: {
            type: Object,
            default: () => ({ Total: 0, Sumbu: [], Data: {} }),
        },
    },
    data() {
        return {
            aktif: '',
            sorot: null,
            dibuka: null,
            semua: false,
            BATAS,
            legenda: LEGENDA,
        };
    },
    computed: {
        sumbu() {
            return this.sebaran?.Sumbu || [];
        },
        sumbuAktif() {
            return this.sumbu.find((s) => s.Kunci === this.aktif) || this.sumbu[0] || {};
        },
        dataAktif() {
            return this.sebaran?.Data?.[this.aktif] || { Baris: [], Kosong: 0, Menjumlah: true, Catatan: null };
        },
        baris() {
            return this.dataAktif.Baris || [];
        },
        barisTampil() {
            if (this.semua || this.baris.length <= BATAS) return this.baris;
            return this.baris.slice(0, BATAS);
        },
        tersisa() {
            return this.semua ? 0 : Math.max(0, this.baris.length - BATAS);
        },
        kosong() {
            return Number(this.dataAktif.Kosong) || 0;
        },
        catatan() {
            return this.dataAktif.Catatan || null;
        },
        satuan() {
            return this.sumbuAktif.Satuan || 'kelompok';
        },
        total() {
            return Number(this.sebaran?.Total) || 0;
        },
        totalBatang() {
            return this.baris.reduce((n, b) => n + (b.Total || 0), 0);
        },
        totalBelumBeredar() {
            return this.baris.reduce((n, b) => n + (b.Belum_Beredar || 0), 0);
        },
        puncak() {
            const maks = Math.max(1, ...this.baris.map((b) => b.Total || 0));
            return maks;
        },
    },
    watch: {
        sumbu: {
            immediate: true,
            handler(daftar) {
                if (daftar.length && !daftar.some((s) => s.Kunci === this.aktif)) {
                    this.aktif = daftar[0].Kunci;
                }
            },
        },
        aktif() {
            this.dibuka = null;
            this.semua = false;
        },
    },
    methods: {
        angka,
        persen(nilai, dari) {
            if (!dari || !nilai) return 0;
            return Math.round((nilai * 100) / dari);
        },
        kunciBaris(b) {
            return b.Tanpa ? 'tanpa' : `${b.Id ?? b.Kode ?? b.Nama}`;
        },
        pilihSumbu(kunci) {
            this.aktif = kunci;
        },
        segmen(b) {
            return LEGENDA.map((l) => ({
                kunci: l.kunci,
                label: l.label,
                nilai: b[l.medan] || 0,
                lebar: ((b[l.medan] || 0) * 100) / this.puncak,
            })).filter((s) => s.nilai > 0);
        },
        totalKelompok(medan) {
            return this.baris.reduce((n, b) => n + (b[medan] || 0), 0);
        },
        setSorot(kunci) {
            this.sorot = this.sorot === kunci ? null : kunci;
        },
        toggleDetail(b) {
            if (!b.Total) return;
            const k = this.kunciBaris(b);
            this.dibuka = this.dibuka === k ? null : k;
        },
        tautan(b) {
            const p = this.sumbuAktif.Param;
            if (!p || !b.Nilai) return '';
            return `/sop/admin/dokumen?${p}=${encodeURIComponent(b.Nilai)}`;
        },
        bukaDaftar(b) {
            const t = this.tautan(b);
            if (t) router.visit(t);
        },
    },
};
</script>

<style scoped>
.psd {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* ══ PEMILIH SUMBU ══ */
.psd-sumbu {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
}

.psd-sumbu-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.8);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
}

.psd-sumbu-btn:hover {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.15);
}

.psd-sumbu-btn.is-aktif {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 100%);
    border-color: var(--hcis-indigo-700);
    color: var(--hcis-white);
    font-weight: 800;
    box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.35);
}

.psd-sumbu-btn.is-aktif :deep(.hcis-icon) {
    color: var(--hcis-white) !important;
}

.psd-sumbu-count {
    padding: 0.05rem 0.4rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.psd-sumbu-btn.is-aktif .psd-sumbu-count {
    background: rgba(255, 255, 255, 0.25);
    color: var(--hcis-white);
}

/* ══ HEADER RINGKASAN & LEGENDA ══ */
.psd-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.psd-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.psd-stat {
    font-size: 0.8125rem;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.psd-stat strong {
    font-weight: 800;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-variant-numeric: tabular-nums;
}

.psd-stat-sub {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.75rem;
    margin-left: 0.2rem;
}

.psd-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.psd-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.6875rem;
    font-weight: 750;
    line-height: 1.3;
    font-variant-numeric: tabular-nums;
}

.psd-badge.is-kosong {
    background: rgba(254, 242, 242, 0.9);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: var(--hcis-red-700);
}

.psd-badge.is-belum {
    background: rgba(254, 243, 199, 0.9);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: var(--hcis-amber-700);
}

/* Legenda Toolbar */
.psd-legenda {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.9);
    padding: 0.2rem;
    border-radius: var(--hcis-radius-md, 8px);
}

.psd-lg-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.725rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}

.psd-lg-btn:hover {
    background: var(--hcis-white);
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.psd-lg-btn.is-aktif {
    background: var(--hcis-white);
    border-color: rgba(203, 213, 225, 0.9);
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
}

.psd-lg-count {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.05rem 0.3rem;
    border-radius: 9999px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-variant-numeric: tabular-nums;
}

.psd-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

.psd-dot.is-fungsional { background: var(--hcis-indigo-600); }
.psd-dot.is-publik { background: #0d9488; }
.psd-dot.is-belum { background: var(--hcis-amber-500); }

/* Peringatan Catatan */
.psd-catatan {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(238, 242, 255, 0.8);
    border: 1px solid rgba(199, 210, 254, 0.8);
    color: var(--hcis-indigo-800);
    font-size: 0.75rem;
    font-weight: 600;
}

/* ══ DAFTAR BARIS ══ */
.psd-daftar {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.psd-item {
    display: flex;
    flex-direction: column;
}

.psd-baris {
    display: grid;
    grid-template-columns: minmax(130px, 1.3fr) 2fr auto;
    align-items: center;
    gap: 0.75rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.75);
    background: rgba(255, 255, 255, 0.7);
    transition: all 0.16s ease;
    cursor: pointer;
}

.psd-baris.has-data:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.1);
    transform: translateY(-1px);
}

.psd-baris.is-terbuka {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.12);
}

.psd-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.psd-lencana {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    flex-shrink: 0;
}

.psd-kode {
    font-size: 0.7rem;
    font-weight: 750;
    font-family: monospace;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    flex-shrink: 0;
}

.psd-nama {
    font-size: 0.78125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Batang Stacked Bar */
.psd-bar-wrapper {
    width: 100%;
    min-width: 0;
}

.psd-bar {
    height: 1.15rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.9);
    display: flex;
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(226, 232, 240, 0.7);
}

.psd-seg {
    height: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: width 0.3s ease, opacity 0.2s ease;
}

.psd-seg.is-fungsional { background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600)); }
.psd-seg.is-publik { background: linear-gradient(135deg, #14b8a6, #0d9488); }
.psd-seg.is-belum { background: linear-gradient(135deg, var(--hcis-amber-400), var(--hcis-amber-500)); }

.psd-seg.is-redup { opacity: 0.25; }
.psd-seg.is-sorot { filter: brightness(1.1); box-shadow: inset 0 0 0 1px var(--hcis-white); }

.psd-seg-label {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-white);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    font-variant-numeric: tabular-nums;
}

.psd-nihil {
    display: inline-flex;
    align-items: center;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    padding-left: 0.5rem;
}

/* Kolom Kanan */
.psd-kanan {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: flex-end;
}

.psd-total-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    min-width: 3.5rem;
    justify-content: flex-end;
}

.psd-total {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    font-variant-numeric: tabular-nums;
}

.psd-proporsi {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-variant-numeric: tabular-nums;
}

.psd-toggle {
    width: 1.5rem;
    height: 1.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    cursor: pointer;
    transition: all 0.16s ease;
}

.psd-toggle:hover {
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.psd-toggle.is-terbuka {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

/* Rincian Expand Box */
.psd-detail {
    margin-top: 0.35rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.psd-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}

.psd-detail-col {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.psd-detail-head {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.psd-detail-val {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
}

.psd-detail-val strong {
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    font-variant-numeric: tabular-nums;
}

.psd-detail-val small {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-variant-numeric: tabular-nums;
}

.psd-detail-sub {
    font-size: 0.65rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.psd-detail-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.4rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.psd-detail-info {
    font-size: 0.735rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.psd-detail-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid rgba(99, 102, 241, 0.3);
    background: rgba(238, 242, 255, 0.8);
    color: var(--hcis-indigo-700);
    font-size: 0.735rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.16s ease;
}

.psd-detail-link:hover {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-500);
}

.psd-lagi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.45rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px dashed rgba(203, 213, 225, 0.9);
    background: rgba(248, 250, 252, 0.8);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
}

.psd-lagi:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
}

.psd-footer {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.8);
    font-size: 0.75rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.psd-footer-icon {
    color: var(--hcis-amber-500);
    margin-top: 0.1rem;
    flex-shrink: 0;
}

/* Animations */
.psd-expand-enter-active,
.psd-expand-leave-active {
    transition: all 0.22s ease-out;
}
.psd-expand-enter-from,
.psd-expand-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (max-width: 767.98px) {
    .psd-baris {
        grid-template-columns: 1fr auto;
        gap: 0.4rem;
    }
    .psd-bar-wrapper {
        grid-column: 1 / -1;
    }
    .psd-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
