<template>
    <div class="ppj">
        <!-- Header Ringkasan & Filter Sorotan Interaktif -->
        <div class="ppj-header">
            <div class="ppj-ringkasan-meta">
                <span class="ppj-stat-utama">
                    <strong>{{ angka(totalDokumen) }}</strong> dokumen aktif
                    <span class="ppj-stat-sub">di {{ baris.length }} tingkat hirarki</span>
                </span>

                <div class="ppj-badges">
                    <span
                        v-if="tingkatKosongCount > 0"
                        class="ppj-badge-sorot is-kosong"
                        title="Ada tingkat hirarki tanpa dokumen (potensi ketimpangan tata kelola)"
                    >
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ tingkatKosongCount }} tingkat kosong
                    </span>
                    <span
                        v-if="totalBelumBeredar > 0"
                        class="ppj-badge-sorot is-belum"
                        title="Dokumen sah di master yang belum dimasukkan ke paket mana pun"
                    >
                        <i class="bi bi-clock-history"></i>
                        {{ totalBelumBeredar }} belum beredar
                    </span>
                </div>
            </div>

            <!-- Legenda Interaktif (Berfungsi sebagai filter sorotan) -->
            <div class="ppj-legenda" role="toolbar" aria-label="Saring sorotan jenis dokumen">
                <button
                    type="button"
                    class="ppj-lg-btn"
                    :class="{ 'is-aktif': sorot === null }"
                    title="Tampilkan semua kelompok dokumen"
                    @click="setSorot(null)"
                >
                    <i class="bi bi-grid-fill ppj-lg-icon"></i>
                    <span>Semua</span>
                    <span class="ppj-lg-count">{{ angka(totalDokumen) }}</span>
                </button>

                <button
                    v-for="l in legenda"
                    :key="l.kunci"
                    type="button"
                    class="ppj-lg-btn"
                    :class="[`is-${l.kunci}`, { 'is-aktif': sorot === l.kunci }]"
                    :title="`Sorot kelompok ${l.label}: ${angka(hitungTotalKelompok(l.medan))} dokumen`"
                    @click="setSorot(l.kunci)"
                >
                    <i class="ppj-dot" :class="`is-${l.kunci}`"></i>
                    <span>{{ l.label }}</span>
                    <span class="ppj-lg-count">{{ angka(hitungTotalKelompok(l.medan)) }}</span>
                </button>
            </div>
        </div>

        <!-- Daftar Baris Piramid Hirarki -->
        <div class="ppj-daftar" role="list">
            <div
                v-for="j in baris"
                :key="j.Id ?? 'tanpa'"
                class="ppj-item-wrapper"
            >
                <div
                    class="ppj-baris"
                    :class="{
                        'is-kosong': !j.Total,
                        'is-terbuka': dibuka === (j.Id ?? 'tanpa'),
                        'has-data': j.Total > 0
                    }"
                    tabindex="0"
                    role="button"
                    :aria-expanded="dibuka === (j.Id ?? 'tanpa')"
                    @click="toggleDetail(j)"
                    @keydown.enter.prevent="toggleDetail(j)"
                    @keydown.space.prevent="toggleDetail(j)"
                >
                    <!-- Label Tingkat, Kode & Nama -->
                    <div class="ppj-label">
                        <span
                            class="ppj-level-badge"
                            :title="`Tingkat Hirarki ${j.Level && j.Level < 99 ? j.Level : 'Lainnya'}`"
                        >
                            {{ j.Level && j.Level < 99 ? `Tk. ${j.Level}` : 'Lain' }}
                        </span>
                        <span class="ppj-kode" :title="`Kode: ${j.Kode || '—'}`">{{ j.Kode || '—' }}</span>
                        <span class="ppj-nama" :title="j.Nama">{{ j.Nama }}</span>
                    </div>

                    <!-- Batang Bertumpuk Proporsional -->
                    <div class="ppj-bar-wrapper">
                        <div class="ppj-bar" :aria-label="`${j.Nama}: ${j.Total} dokumen`">
                            <span
                                v-for="s in segmen(j)"
                                :key="s.kunci"
                                class="ppj-seg"
                                :class="[
                                    `is-${s.kunci}`,
                                    {
                                        'is-sorot': sorot === s.kunci,
                                        'is-redup': sorot !== null && sorot !== s.kunci
                                    }
                                ]"
                                :style="{ width: `${s.lebar}%` }"
                                :title="`${s.label}: ${s.nilai} dokumen (${persen(s.nilai, j.Total)}%)`"
                            >
                                <span v-if="s.lebar >= 14" class="ppj-seg-label">
                                    {{ s.nilai }}
                                </span>
                            </span>

                            <!-- Keterangan jika tingkat KOSONG -->
                            <span v-if="!j.Total" class="ppj-nihil">
                                <i class="bi bi-dash-circle me-1"></i>belum ada dokumen
                            </span>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Angka Total & Tombol Rincian -->
                    <div class="ppj-kanan">
                        <div class="ppj-total-wrap">
                            <span class="ppj-total">{{ j.Total }}</span>
                            <span v-if="totalDokumen > 0 && j.Total > 0" class="ppj-proporsi">
                                {{ persen(j.Total, totalDokumen) }}%
                            </span>
                        </div>

                        <button
                            v-if="j.Total > 0"
                            type="button"
                            class="ppj-toggle-btn"
                            :class="{ 'is-terbuka': dibuka === (j.Id ?? 'tanpa') }"
                            :title="dibuka === (j.Id ?? 'tanpa') ? 'Tutup rincian' : 'Buka rincian kelompok'"
                            @click.stop="toggleDetail(j)"
                        >
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <!-- Panel Rincian Kelompok Dokumen (Expandable) -->
                <Transition name="ppj-expand">
                    <div v-if="dibuka === (j.Id ?? 'tanpa') && j.Total > 0" class="ppj-detail-panel">
                        <div class="ppj-detail-grid">
                            <div class="ppj-detail-col is-fungsional">
                                <div class="ppj-detail-head">
                                    <i class="ppj-dot is-fungsional"></i>
                                    <span>Ditugaskan</span>
                                </div>
                                <div class="ppj-detail-val">
                                    <strong>{{ j.Fungsional || 0 }}</strong>
                                    <small>{{ persen(j.Fungsional || 0, j.Total) }}%</small>
                                </div>
                                <span class="ppj-detail-sub">Wajib & kepatuhan diukur</span>
                            </div>

                            <div class="ppj-detail-col is-publik">
                                <div class="ppj-detail-head">
                                    <i class="ppj-dot is-publik"></i>
                                    <span>Beredar</span>
                                </div>
                                <div class="ppj-detail-val">
                                    <strong>{{ j.Publik || 0 }}</strong>
                                    <small>{{ persen(j.Publik || 0, j.Total) }}%</small>
                                </div>
                                <span class="ppj-detail-sub">Beredar, dibaca sukarela</span>
                            </div>

                            <div class="ppj-detail-col is-belum">
                                <div class="ppj-detail-head">
                                    <i class="ppj-dot is-belum"></i>
                                    <span>Belum Beredar</span>
                                </div>
                                <div class="ppj-detail-val">
                                    <strong>{{ j.Belum_Beredar || 0 }}</strong>
                                    <small>{{ persen(j.Belum_Beredar || 0, j.Total) }}%</small>
                                </div>
                                <span class="ppj-detail-sub">Sah, belum masuk paket</span>
                            </div>
                        </div>

                        <div class="ppj-detail-footer">
                            <span class="ppj-detail-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Tingkat <strong>{{ j.Nama }}</strong> menyumbang <strong>{{ persen(j.Total, totalDokumen) }}%</strong> dari seluruh dokumen master.
                            </span>
                            <button
                                type="button"
                                class="ppj-detail-link"
                                @click.stop="keMasterDokumen(j)"
                            >
                                Buka di Master Dokumen <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Footer Bantuan Wawasan Piramid -->
        <div class="ppj-footer-info">
            <i class="bi bi-lightbulb-fill ppj-info-icon"></i>
            <span>
                <strong>Panduan Tata Kelola:</strong> Piramid ideal berbentuk segitiga (kebijakan sedikit di puncak, prosedur & instruksi kerja lebih banyak di dasar). Klik baris untuk melihat rincian sebaran.
            </span>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { angka } from '../agregat.js';

const LEGENDA = [
    { kunci: 'fungsional', label: 'Ditugaskan', medan: 'Fungsional' },
    { kunci: 'publik', label: 'Beredar', medan: 'Publik' },
    { kunci: 'belum', label: 'Belum beredar', medan: 'Belum_Beredar' },
];

/**
 * Piramid jenis dokumen, bertumpuk per kelompok hirarki.
 *
 * BERTUMPUK, bukan tiga grafik terpisah. Pertanyaannya bukan "berapa Prosedur"
 * melainkan "dari 21 Prosedur, berapa yang benar-benar sampai ke orang" — dan
 * itu hanya terbaca kalau ketiganya berdiri di batang yang sama.
 *
 * Lebar batang diukur terhadap tingkat TERBANYAK, bukan terhadap total seluruh
 * dokumen: yang dibandingkan di sini adalah antar-tingkat, dan menskalakan ke
 * total membuat semua tingkat tampak sama-sama pendek.
 */
export default {
    name: 'PanelPiramidJenis',
    props: {
        piramid: { type: Array, default: () => [] },
    },
    data() {
        return {
            legenda: LEGENDA,
            sorot: null,
            dibuka: null,
        };
    },
    computed: {
        baris() {
            return [...this.piramid].sort((a, b) => (a.Level || 99) - (b.Level || 99));
        },
        puncak() {
            return Math.max(1, ...this.piramid.map((j) => j.Total || 0));
        },
        totalDokumen() {
            return this.piramid.reduce((sum, j) => sum + (j.Total || 0), 0);
        },
        totalBelumBeredar() {
            return this.piramid.reduce((sum, j) => sum + (j.Belum_Beredar || 0), 0);
        },
        tingkatKosongCount() {
            return this.piramid.filter((j) => !j.Total).length;
        },
    },
    methods: {
        angka,
        persen(nilai, total) {
            if (!total || total <= 0) return 0;
            return Math.round(((nilai || 0) * 100) / total);
        },
        segmen(j) {
            return LEGENDA.map((l) => ({
                kunci: l.kunci,
                label: l.label,
                nilai: j[l.medan] || 0,
                lebar: ((j[l.medan] || 0) * 100) / this.puncak,
            })).filter((s) => s.nilai > 0);
        },
        hitungTotalKelompok(medan) {
            return this.piramid.reduce((sum, j) => sum + (j[medan] || 0), 0);
        },
        setSorot(kunci) {
            this.sorot = this.sorot === kunci ? null : kunci;
        },
        toggleDetail(j) {
            if (!j.Total) return;
            const key = j.Id ?? 'tanpa';
            this.dibuka = this.dibuka === key ? null : key;
        },
        keMasterDokumen(j) {
            if (j && j.Id) {
                router.visit(`/sop/admin/dokumen?jenis=${j.Id}`);
            } else {
                router.visit('/sop/admin/dokumen');
            }
        },
    },
};
</script>

<style scoped>
.ppj {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* ══ HEADER & STATS ══ */
.ppj-header {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px dashed var(--hcis-border, var(--hcis-slate-200));
}

.ppj-ringkasan-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.ppj-stat-utama {
    font-size: 0.85rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.ppj-stat-sub {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.8rem;
    margin-left: 0.25rem;
}

.ppj-badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.ppj-badge-sorot {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.7rem;
    font-weight: 600;
}

.ppj-badge-sorot.is-kosong {
    background: var(--hcis-danger-50, var(--hcis-red-50));
    color: var(--hcis-danger-600, var(--hcis-red-600));
    border: 1px solid var(--hcis-danger-200, var(--hcis-red-200));
}

.ppj-badge-sorot.is-belum {
    background: var(--hcis-amber-50, var(--hcis-amber-50));
    color: var(--hcis-amber-700, var(--hcis-amber-700));
    border: 1px solid var(--hcis-amber-200, var(--hcis-amber-200));
}

/* ══ LEGENDA INTERAKTIF ══ */
.ppj-legenda {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.ppj-lg-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    background: var(--hcis-surface, var(--hcis-white));
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.725rem;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--hcis-transition, 0.2s ease);
}

.ppj-lg-btn:hover {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    border-color: var(--hcis-border-strong, var(--hcis-slate-300));
}

.ppj-lg-btn.is-aktif {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-color: var(--hcis-text-muted, var(--hcis-slate-500));
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.ppj-lg-btn.is-fungsional.is-aktif {
    background: #eff6ff;
    border-color: var(--hcis-brand, var(--hcis-blue-600));
    color: var(--hcis-brand, var(--hcis-blue-600));
}

.ppj-lg-btn.is-publik.is-aktif {
    background: var(--hcis-emerald-50);
    border-color: var(--hcis-success-600, var(--hcis-emerald-600));
    color: var(--hcis-success-600, var(--hcis-emerald-600));
}

.ppj-lg-btn.is-belum.is-aktif {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-600, var(--hcis-amber-600));
    color: var(--hcis-amber-700, var(--hcis-amber-700));
}

.ppj-lg-icon {
    font-size: 0.7rem;
    opacity: 0.8;
}

.ppj-lg-count {
    padding: 0.05rem 0.3rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    font-size: 0.65rem;
    font-weight: 700;
    color: inherit;
}

.ppj-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    flex-shrink: 0;
}

.ppj-dot.is-fungsional { background: var(--hcis-brand, var(--hcis-blue-600)); }
.ppj-dot.is-publik { background: var(--hcis-success-500, var(--hcis-emerald-500)); }
.ppj-dot.is-belum { background: var(--hcis-amber-500, var(--hcis-amber-500)); }

/* ══ DAFTAR BARIS PIRAMID ══ */
.ppj-daftar {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.ppj-item-wrapper {
    display: flex;
    flex-direction: column;
}

.ppj-baris {
    display: grid;
    grid-template-columns: 11.5rem 1fr 3.75rem;
    align-items: center;
    gap: 0.625rem;
    padding: 0.35rem 0.5rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    transition: background 0.15s ease, border-color 0.15s ease;
    border: 1px solid transparent;
    cursor: default;
}

.ppj-baris.has-data {
    cursor: pointer;
}

.ppj-baris.has-data:hover {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-color: var(--hcis-border-subtle, var(--hcis-slate-200));
}

.ppj-baris.is-terbuka {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-color: var(--hcis-border, var(--hcis-slate-300));
}

.ppj-baris.is-kosong {
    opacity: 0.75;
}

/* ══ LABEL HIRARKI & JENIS ══ */
.ppj-label {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
}

.ppj-level-badge {
    flex: 0 0 auto;
    padding: 0.1rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    background: var(--hcis-surface-alt, var(--hcis-slate-200));
    color: var(--hcis-text-muted, var(--hcis-slate-600));
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.ppj-kode {
    flex: 0 0 auto;
    padding: 0.1rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    color: var(--hcis-brand, var(--hcis-blue-600));
    font-size: 0.65rem;
    font-weight: 700;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.ppj-nama {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ══ PROGRESS BAR BERTUMPUK ══ */
.ppj-bar-wrapper {
    min-width: 0;
    width: 100%;
}

.ppj-bar {
    display: flex;
    align-items: center;
    height: 1.35rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    overflow: hidden;
    position: relative;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.ppj-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transition: width 0.3s ease, opacity 0.2s ease, filter 0.2s ease;
    position: relative;
}

.ppj-seg.is-fungsional {
    background: var(--hcis-brand, var(--hcis-blue-600));
}

.ppj-seg.is-publik {
    background: var(--hcis-success-500, var(--hcis-emerald-500));
}

.ppj-seg.is-belum {
    background: var(--hcis-amber-500, var(--hcis-amber-500));
}

.ppj-seg.is-sorot {
    opacity: 1;
    filter: brightness(1.08);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
}

.ppj-seg.is-redup {
    opacity: 0.25;
    filter: grayscale(40%);
}

.ppj-seg-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-white);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    pointer-events: none;
    line-height: 1;
}

.ppj-nihil {
    display: flex;
    align-items: center;
    padding-left: 0.6rem;
    font-size: 0.7rem;
    color: var(--hcis-text-subtle, var(--hcis-slate-400));
    font-style: italic;
}

/* ══ SISI KANAN & ACTIONS ══ */
.ppj-kanan {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.375rem;
}

.ppj-total-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: 1.1;
}

.ppj-total {
    font-size: 0.825rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.ppj-proporsi {
    font-size: 0.65rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ppj-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.75rem;
    cursor: pointer;
    transition: transform 0.2s ease, background 0.15s ease;
}

.ppj-toggle-btn:hover {
    background: var(--hcis-surface-alt, var(--hcis-slate-200));
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.ppj-toggle-btn.is-terbuka {
    transform: rotate(180deg);
}

/* ══ PANEL RINCIAN (EXPANDABLE) ══ */
.ppj-detail-panel {
    margin: 0.25rem 0 0.5rem 0;
    padding: 0.75rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
}

.ppj-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.625rem;
}

.ppj-detail-col {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.5rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ppj-detail-col.is-fungsional {
    border-top: 2px solid var(--hcis-brand, var(--hcis-blue-600));
}

.ppj-detail-col.is-publik {
    border-top: 2px solid var(--hcis-success-500, var(--hcis-emerald-500));
}

.ppj-detail-col.is-belum {
    border-top: 2px solid var(--hcis-amber-500, var(--hcis-amber-500));
}

.ppj-detail-head {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ppj-detail-val {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.ppj-detail-val strong {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.ppj-detail-val small {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ppj-detail-sub {
    font-size: 0.65rem;
    color: var(--hcis-text-subtle, var(--hcis-slate-400));
}

.ppj-detail-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed var(--hcis-border-subtle, var(--hcis-slate-200));
    font-size: 0.725rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ppj-detail-info strong {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.ppj-detail-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
    background: var(--hcis-surface, var(--hcis-white));
    color: var(--hcis-brand, var(--hcis-blue-600));
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.ppj-detail-link:hover {
    background: var(--hcis-brand, var(--hcis-blue-600));
    color: var(--hcis-white);
    border-color: var(--hcis-brand, var(--hcis-blue-600));
}

/* ══ FOOTER WAWASAN ══ */
.ppj-footer-info {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    padding: 0.5rem 0.625rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    font-size: 0.7rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.4;
}

.ppj-info-icon {
    font-size: 0.8rem;
    color: var(--hcis-amber-500, var(--hcis-amber-500));
    flex-shrink: 0;
    margin-top: 0.05rem;
}

/* ══ TRANSISI ANIMASI ══ */
.ppj-expand-enter-active,
.ppj-expand-leave-active {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 200px;
    overflow: hidden;
    opacity: 1;
}

.ppj-expand-enter-from,
.ppj-expand-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
    margin-bottom: 0;
}

/* ══ RESPONSIVE MOBILE ══ */
@media (max-width: 640px) {
    .ppj-baris {
        grid-template-columns: 8.5rem 1fr 3rem;
        gap: 0.375rem;
        padding: 0.25rem;
    }

    .ppj-level-badge {
        display: none;
    }

    .ppj-nama {
        font-size: 0.75rem;
    }

    .ppj-bar {
        height: 1.15rem;
    }

    .ppj-detail-grid {
        grid-template-columns: 1fr;
    }

    .ppj-detail-footer {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
