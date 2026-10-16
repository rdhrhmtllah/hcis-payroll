<template>
    <div class="pcdp">
        <!-- Switcher Tab: Per Dokumen vs Per Paket -->
        <div class="pcdp-head">
            <div class="pcdp-tabs" role="tablist">
                <button
                    type="button"
                    class="pcdp-tab-btn"
                    :class="{ 'is-aktif': tabAktif === 'dokumen' }"
                    @click="tabAktif = 'dokumen'"
                >
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Per Dokumen</span>
                    <span class="pcdp-tab-badge">{{ dataDokumen.Total_Dokumen || 0 }}</span>
                </button>
                <button
                    type="button"
                    class="pcdp-tab-btn"
                    :class="{ 'is-aktif': tabAktif === 'paket' }"
                    @click="tabAktif = 'paket'"
                >
                    <i class="bi bi-collection-fill"></i>
                    <span>Per Paket</span>
                    <span class="pcdp-tab-badge">{{ dataPaket.Total_Paket || 0 }}</span>
                </button>
            </div>

            <!-- Rerata Coverage Aktif -->
            <div class="pcdp-rerata-wrap">
                <span class="pcdp-rerata-num" :class="sentimenCoverage(rerataAktif)">{{ rerataAktif }}%</span>
                <span class="pcdp-rerata-lbl">Rata-rata Coverage</span>
            </div>
        </div>

        <!-- Sebaran Distribusi Coverage -->
        <div class="pcdp-sebaran-card">
            <div class="pcdp-sebaran-item is-tinggi">
                <span class="pcdp-seb-dot is-tinggi"></span>
                <span class="pcdp-seb-lbl">&ge; 80% (Tinggi)</span>
                <strong>{{ sebaranAktif.Tinggi || 0 }}</strong>
            </div>
            <div class="pcdp-sebaran-item is-sedang">
                <span class="pcdp-seb-dot is-sedang"></span>
                <span class="pcdp-seb-lbl">50 – 79% (Sedang)</span>
                <strong>{{ sebaranAktif.Sedang || 0 }}</strong>
            </div>
            <div class="pcdp-sebaran-item is-rendah">
                <span class="pcdp-seb-dot is-rendah"></span>
                <span class="pcdp-seb-lbl">1 – 49% (Rendah)</span>
                <strong>{{ sebaranAktif.Rendah || 0 }}</strong>
            </div>
            <div class="pcdp-sebaran-item is-nol">
                <span class="pcdp-seb-dot is-nol"></span>
                <span class="pcdp-seb-lbl">0% (Belum Mulai)</span>
                <strong>{{ sebaranAktif.Nol || 0 }}</strong>
            </div>
        </div>

        <!-- Dua Kolom: Tertinggi vs Terendah (Perlu Intervensi) -->
        <div class="pcdp-grid-kolom">
            <!-- Kolom 1: Perlu Intervensi (Terendah) -->
            <div class="pcdp-kolom is-intervensi">
                <div class="pcdp-kolom-head">
                    <i class="bi bi-exclamation-octagon-fill text-danger"></i>
                    <span>Coverage Terendah (Perlu Didorong)</span>
                </div>
                <div v-if="terendahAktif.length" class="pcdp-daftar">
                    <div
                        v-for="item in terendahAktif"
                        :key="item.Id_Dokumen || item.Id_Paket || item.Nama || item.Judul"
                        class="pcdp-item"
                    >
                        <div class="pcdp-item-kiri">
                            <span class="pcdp-item-judul" :title="item.Judul || item.Nama">
                                {{ item.Judul || item.Nama }}
                            </span>
                            <div class="pcdp-item-meta">
                                <span>{{ item.Nomor || item.Kode || '—' }}</span>
                                <span>&bull;</span>
                                <span>{{ item.Selesai }}/{{ item.Target }} pembaca</span>
                            </div>
                        </div>
                        <div class="pcdp-item-kanan">
                            <span class="pcdp-persen-tag" :class="sentimenCoverage(item.Persen)">
                                {{ item.Persen }}%
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="pcdp-kosong">
                    <i class="bi bi-check2-circle text-success me-1"></i> Semua memiliki progres coverage baik.
                </div>
            </div>

            <!-- Kolom 2: Tertinggi (Tuntas) -->
            <div class="pcdp-kolom is-sukses">
                <div class="pcdp-kolom-head">
                    <i class="bi bi-trophy-fill text-success"></i>
                    <span>Coverage Tertinggi</span>
                </div>
                <div v-if="tertinggiAktif.length" class="pcdp-daftar">
                    <div
                        v-for="item in tertinggiAktif"
                        :key="item.Id_Dokumen || item.Id_Paket || item.Nama || item.Judul"
                        class="pcdp-item"
                    >
                        <div class="pcdp-item-kiri">
                            <span class="pcdp-item-judul" :title="item.Judul || item.Nama">
                                {{ item.Judul || item.Nama }}
                            </span>
                            <div class="pcdp-item-meta">
                                <span>{{ item.Nomor || item.Kode || '—' }}</span>
                                <span>&bull;</span>
                                <span>{{ item.Selesai }}/{{ item.Target }} pembaca</span>
                            </div>
                        </div>
                        <div class="pcdp-item-kanan">
                            <span class="pcdp-persen-tag" :class="sentimenCoverage(item.Persen)">
                                {{ item.Persen }}%
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="pcdp-kosong">
                    <i class="bi bi-info-circle me-1"></i> Belum ada data penugasan aktif.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PanelCoverageDokumenPaket',
    props: {
        coverageDokumen: {
            type: Object,
            default: () => ({
                Total_Dokumen: 0,
                Dokumen_Ditugaskan: 0,
                Rerata_Coverage: 0,
                Sebaran: {},
                Tertinggi: [],
                Terendah: [],
            }),
        },
        coveragePaket: {
            type: Object,
            default: () => ({
                Total_Paket: 0,
                Paket_Ditugaskan: 0,
                Rerata_Coverage: 0,
                Sebaran: {},
                Tertinggi: [],
                Terendah: [],
            }),
        },
    },
    data() {
        return {
            tabAktif: 'dokumen',
        };
    },
    computed: {
        dataDokumen() {
            return this.coverageDokumen || {};
        },
        dataPaket() {
            return this.coveragePaket || {};
        },
        rerataAktif() {
            return this.tabAktif === 'dokumen'
                ? (this.dataDokumen.Rerata_Coverage || 0)
                : (this.dataPaket.Rerata_Coverage || 0);
        },
        sebaranAktif() {
            return this.tabAktif === 'dokumen'
                ? (this.dataDokumen.Sebaran || {})
                : (this.dataPaket.Sebaran || {});
        },
        tertinggiAktif() {
            return this.tabAktif === 'dokumen'
                ? (this.dataDokumen.Tertinggi || [])
                : (this.dataPaket.Tertinggi || []);
        },
        terendahAktif() {
            return this.tabAktif === 'dokumen'
                ? (this.dataDokumen.Terendah || [])
                : (this.dataPaket.Terendah || []);
        },
    },
    methods: {
        sentimenCoverage(persen) {
            const p = Number(persen) || 0;
            if (p >= 80) return 'is-tinggi';
            if (p >= 50) return 'is-sedang';
            if (p > 0) return 'is-rendah';
            return 'is-nol';
        },
    },
};
</script>

<style scoped>
.pcdp {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

/* ══ HEADER & SWITCHER ══ */
.pcdp-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.625rem;
}

.pcdp-tabs {
    display: inline-flex;
    padding: 0.2rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.pcdp-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pcdp-tab-btn.is-aktif {
    background: var(--hcis-surface, var(--hcis-white));
    color: var(--hcis-brand, var(--hcis-blue-600));
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.pcdp-tab-badge {
    padding: 0.05rem 0.35rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    font-size: 0.65rem;
    font-weight: 700;
    color: inherit;
}

.pcdp-rerata-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pcdp-rerata-num {
    font-size: 1.35rem;
    font-weight: 800;
}

.pcdp-rerata-num.is-tinggi { color: var(--hcis-emerald-600); }
.pcdp-rerata-num.is-sedang { color: var(--hcis-blue-600); }
.pcdp-rerata-num.is-rendah { color: var(--hcis-amber-600); }
.pcdp-rerata-num.is-nol { color: var(--hcis-red-600); }

.pcdp-rerata-lbl {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-weight: 600;
}

/* ══ SEBARAN CARD ══ */
.pcdp-sebaran-card {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    padding: 0.625rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
}

.pcdp-sebaran-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.725rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.pcdp-seb-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    flex-shrink: 0;
}

.pcdp-seb-dot.is-tinggi { background: var(--hcis-emerald-500); }
.pcdp-seb-dot.is-sedang { background: var(--hcis-blue-500); }
.pcdp-seb-dot.is-rendah { background: var(--hcis-amber-500); }
.pcdp-seb-dot.is-nol { background: var(--hcis-red-500); }

.pcdp-seb-lbl {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    flex: 1;
}

/* ══ DUA KOLOM KOMPARASI ══ */
.pcdp-grid-kolom {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 0.75rem;
}

.pcdp-kolom {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.625rem;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border, var(--hcis-slate-200));
}

.pcdp-kolom.is-intervensi { border-top: 2px solid var(--hcis-red-500); }
.pcdp-kolom.is-sukses { border-top: 2px solid var(--hcis-emerald-500); }

.pcdp-kolom-head {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.725rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    padding-bottom: 0.35rem;
    border-bottom: 1px dashed var(--hcis-border-subtle, var(--hcis-slate-200));
}

.pcdp-daftar {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 0.2rem;
}

.pcdp-daftar::-webkit-scrollbar {
    width: 4px;
}

.pcdp-daftar::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300);
    border-radius: 4px;
}

.pcdp-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.45rem;
    border-radius: var(--hcis-radius-xs, 0.25rem);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.pcdp-item-kiri {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 0.1rem;
}

.pcdp-item-judul {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pcdp-item-meta {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.pcdp-persen-tag {
    padding: 0.1rem 0.4rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    font-size: 0.7rem;
    font-weight: 700;
}

.pcdp-persen-tag.is-tinggi { background: var(--hcis-emerald-50); color: var(--hcis-emerald-600); }
.pcdp-persen-tag.is-sedang { background: #eff6ff; color: var(--hcis-blue-600); }
.pcdp-persen-tag.is-rendah { background: var(--hcis-amber-50); color: var(--hcis-amber-600); }
.pcdp-persen-tag.is-nol { background: var(--hcis-red-50); color: var(--hcis-red-600); }

.pcdp-kosong {
    padding: 0.75rem;
    text-align: center;
    font-size: 0.725rem;
    color: var(--hcis-text-subtle, var(--hcis-slate-400));
}

@media (max-width: 640px) {
    .pcdp-sebaran-card {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
