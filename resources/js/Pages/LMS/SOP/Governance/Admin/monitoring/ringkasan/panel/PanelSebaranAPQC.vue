<template>
    <div class="psa">
        <!-- Header Ringkasan & Pencarian -->
        <div class="psa-head">
            <div class="psa-stat-meta">
                <span class="psa-stat-main">
                    <strong>{{ totalSopTerpetakan }}</strong> dokumen terpetakan ke APQC
                </span>
                <div class="psa-chips">
                    <span class="psa-chip is-terisi">
                        <i class="bi bi-check2-circle"></i>
                        {{ prosesTerisiCount }} Proses Terisi
                    </span>
                    <span v-if="prosesKosongCount > 0" class="psa-chip is-kosong" title="Kategori proses bisnis tanpa satupun dokumen aktif">
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ prosesKosongCount }} Proses Kosong
                    </span>
                    <span v-if="tanpaProsesCount > 0" class="psa-chip is-belum" title="Dokumen yang belum dipetakan ke proses APQC">
                        <i class="bi bi-question-circle"></i>
                        {{ tanpaProsesCount }} Belum Ber-APQC
                    </span>
                </div>
            </div>

            <!-- Input Cari Proses APQC -->
            <div class="psa-cari-wrap">
                <i class="bi bi-search psa-cari-ico"></i>
                <input
                    v-model="kataKunci"
                    type="text"
                    class="psa-cari-input"
                    placeholder="Cari proses APQC..."
                />
            </div>
        </div>

        <!-- Daftar Baris Proses APQC -->
        <div class="psa-daftar" role="list">
            <div
                v-for="p in daftarTersaring"
                :key="p.Id || p.Kode"
                class="psa-baris"
                :class="{ 'is-kosong': !p.Total }"
            >
                <div class="psa-label">
                    <span class="psa-kode">{{ p.Kode || '—' }}</span>
                    <span class="psa-nama" :title="p.Nama">{{ p.Nama }}</span>
                </div>

                <div class="psa-bar-wrap">
                    <div class="psa-bar">
                        <span
                            v-for="s in segmen(p)"
                            :key="s.kunci"
                            class="psa-seg"
                            :class="`is-${s.kunci}`"
                            :style="{ width: `${s.lebar}%` }"
                            :title="`${s.label}: ${s.nilai} dokumen`"
                        >
                            <span v-if="s.lebar >= 15" class="psa-seg-label">{{ s.nilai }}</span>
                        </span>
                        <span v-if="!p.Total" class="psa-nihil">
                            <i class="bi bi-dash-circle me-1"></i>belum ada dokumen
                        </span>
                    </div>
                </div>

                <div class="psa-total-wrap">
                    <span class="psa-total">{{ p.Total || 0 }}</span>
                    <small class="psa-total-lbl">dok</small>
                </div>
            </div>

            <div v-if="!daftarTersaring.length" class="psa-kosong">
                <i class="bi bi-search me-1"></i> Tidak ditemukan proses APQC dengan kata kunci "{{ kataKunci }}".
            </div>
        </div>

        <!-- Legenda Bawah -->
        <div class="psa-legenda">
            <span class="psa-lg-item"><i class="psa-dot is-fungsional"></i> Ditugaskan</span>
            <span class="psa-lg-item"><i class="psa-dot is-publik"></i> Beredar</span>
            <span class="psa-lg-item"><i class="psa-dot is-belum"></i> Belum Beredar</span>
        </div>
    </div>
</template>

<script>
const LEGENDA = [
    { kunci: 'fungsional', label: 'Ditugaskan', medan: 'Fungsional' },
    { kunci: 'publik', label: 'Beredar', medan: 'Publik' },
    { kunci: 'belum', label: 'Belum beredar', medan: 'Belum_Beredar' },
];

export default {
    name: 'PanelSebaranAPQC',
    props: {
        apqc: {
            type: Object,
            default: () => ({
                Baris: [],
                Total_Proses: 0,
                Proses_Kosong: 0,
                Tanpa_Proses: 0,
            }),
        },
    },
    data() {
        return {
            kataKunci: '',
        };
    },
    computed: {
        baris() {
            return this.apqc?.Baris || [];
        },
        prosesKosongCount() {
            return this.apqc?.Proses_Kosong || 0;
        },
        tanpaProsesCount() {
            return this.apqc?.Tanpa_Proses || 0;
        },
        prosesTerisiCount() {
            return Math.max(0, (this.apqc?.Total_Proses || 0) - this.prosesKosongCount);
        },
        totalSopTerpetakan() {
            return this.baris.reduce((sum, p) => sum + (p.Total || 0), 0);
        },
        puncak() {
            return Math.max(1, ...this.baris.map((p) => p.Total || 0));
        },
        daftarTersaring() {
            if (!this.kataKunci.trim()) {
                return this.baris;
            }
            const q = this.kataKunci.toLowerCase();
            return this.baris.filter(
                (p) => (p.Kode && p.Kode.toLowerCase().includes(q)) || (p.Nama && p.Nama.toLowerCase().includes(q))
            );
        },
    },
    methods: {
        segmen(p) {
            return LEGENDA.map((l) => ({
                kunci: l.kunci,
                label: l.label,
                nilai: p[l.medan] || 0,
                lebar: ((p[l.medan] || 0) * 100) / this.puncak,
            })).filter((s) => s.nilai > 0);
        },
    },
};
</script>

<style scoped>
.psa {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* ══ HEAD & CARI ══ */
.psa-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.625rem;
    padding-bottom: 0.625rem;
    border-bottom: 1px dashed var(--hcis-border, var(--hcis-slate-200));
}

.psa-stat-meta {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.psa-stat-main {
    font-size: 0.825rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.psa-chips {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.psa-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.68rem;
    font-weight: 600;
}

.psa-chip.is-terisi { background: var(--hcis-emerald-50); color: var(--hcis-emerald-800); border: 1px solid var(--hcis-emerald-200); }
.psa-chip.is-kosong { background: var(--hcis-red-50); color: var(--hcis-red-600); border: 1px solid var(--hcis-red-200); }
.psa-chip.is-belum { background: var(--hcis-amber-50); color: var(--hcis-amber-700); border: 1px solid var(--hcis-amber-200); }

.psa-cari-wrap {
    position: relative;
    width: 12rem;
}

.psa-cari-ico {
    position: absolute;
    left: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.psa-cari-input {
    width: 100%;
    padding: 0.25rem 0.5rem 0.25rem 1.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid var(--hcis-border, var(--hcis-slate-300));
    background: var(--hcis-surface, var(--hcis-white));
    font-size: 0.75rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    outline: none;
    transition: border-color 0.15s ease;
}

.psa-cari-input:focus {
    border-color: var(--hcis-brand, var(--hcis-blue-600));
}

/* ══ DAFTAR BARIS ══ */
.psa-daftar {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 320px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.psa-daftar::-webkit-scrollbar {
    width: 4px;
}

.psa-daftar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 4px;
}

.psa-baris {
    display: grid;
    grid-template-columns: 12rem 1fr 3rem;
    align-items: center;
    gap: 0.625rem;
    padding: 0.35rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    transition: background 0.15s ease;
}

.psa-baris:hover {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.psa-baris.is-kosong {
    opacity: 0.75;
}

.psa-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.psa-kode {
    flex: 0 0 auto;
    padding: 0.1rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    color: var(--hcis-brand, var(--hcis-blue-600));
    font-size: 0.65rem;
    font-weight: 700;
}

.psa-nama {
    font-size: 0.775rem;
    font-weight: 500;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.psa-bar-wrap {
    min-width: 0;
    width: 100%;
}

.psa-bar {
    display: flex;
    align-items: center;
    height: 1.25rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    overflow: hidden;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.psa-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transition: width 0.3s ease;
}

.psa-seg.is-fungsional { background: var(--hcis-brand, var(--hcis-blue-600)); }
.psa-seg.is-publik { background: var(--hcis-success-500, var(--hcis-emerald-500)); }
.psa-seg.is-belum { background: var(--hcis-amber-500, var(--hcis-amber-500)); }

.psa-seg-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-white);
    line-height: 1;
}

.psa-nihil {
    display: flex;
    align-items: center;
    padding-left: 0.5rem;
    font-size: 0.68rem;
    color: var(--hcis-text-subtle, var(--hcis-slate-400));
    font-style: italic;
}

.psa-total-wrap {
    display: flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: 0.15rem;
}

.psa-total {
    font-size: 0.825rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.psa-total-lbl {
    font-size: 0.65rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.psa-legenda {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    padding-top: 0.25rem;
}

.psa-lg-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.psa-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
}

.psa-dot.is-fungsional { background: var(--hcis-brand, var(--hcis-blue-600)); }
.psa-dot.is-publik { background: var(--hcis-success-500, var(--hcis-emerald-500)); }
.psa-dot.is-belum { background: var(--hcis-amber-500, var(--hcis-amber-500)); }

.psa-kosong {
    padding: 1rem;
    text-align: center;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

@media (max-width: 640px) {
    .psa-baris {
        grid-template-columns: 8.5rem 1fr 2.5rem;
    }
}
</style>
