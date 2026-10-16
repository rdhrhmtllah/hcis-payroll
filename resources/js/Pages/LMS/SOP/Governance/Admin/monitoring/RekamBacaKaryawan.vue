<template>
    <div class="rb-view sop-fade-up">
        <!-- ═══════════════════════════════════════════════════════════════
             1. HEADER & RINGKASAN EKSEKUTIF
             ═══════════════════════════════════════════════════════════════ -->
        <div class="rb-hero-card">
            <div class="rb-hero-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="rb-hero-icon">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <div>
                        <div class="rb-eyebrow">AUDIT TINGKAT ITEM</div>
                        <h4 class="rb-title mb-0">Rekam Aktivitas & Jejak Baca Dokumen</h4>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span v-if="data.ringkas && data.ringkas.total_rekam" class="rb-total-badge">
                        <i class="bi bi-layers-fill text-primary me-1"></i>
                        <strong class="tnum">{{ data.ringkas.total_rekam }}</strong> Total Rekam
                    </span>
                    <button
                        v-if="grup.length > 1"
                        type="button"
                        class="rb-toggle-all-btn"
                        @click="toggleSemuaLipatan"
                    >
                        <i class="bi" :class="semuaTerlipat ? 'bi-arrows-expand' : 'bi-arrows-collapse'"></i>
                        <span>{{ semuaTerlipat ? 'Buka Semua Paket' : 'Tutup Semua Paket' }}</span>
                    </button>
                </div>
            </div>

            <!-- 2. LOADING STATE -->
            <div v-if="loading" class="rb-loading py-5">
                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                <span class="small fw-bold text-muted">Memuat data rekam baca granular…</span>
            </div>

            <!-- 3. EMPTY STATE -->
            <div v-else-if="!data.item || !data.item.length" class="rb-kosong py-5">
                <div class="rb-kosong-icon">
                    <i class="bi bi-journal-x"></i>
                </div>
                <h6 class="fw-bold text-dark mt-2 mb-1">Belum Ada Rekam Baca</h6>
                <p class="text-muted small mb-0">Karyawan ini belum membuka atau membaca dokumen pada penugasan aktif.</p>
            </div>

            <!-- 4. KONTEN UTAMA -->
            <div v-else class="rb-content-body">
                <!-- GRID STATISTIK FAKTUAL (4 BENTO CARDS ELEGAN) -->
                <div class="rb-stat-grid">
                    <div class="rb-stat-card brand">
                        <div class="rb-stat-top">
                            <span class="rb-stat-label">Rekam Tersimpan</span>
                            <div class="rb-stat-icon-wrap brand">
                                <i class="bi bi-journal-text"></i>
                            </div>
                        </div>
                        <div class="rb-stat-value tnum">{{ data.ringkas.total_rekam || 0 }}</div>
                        <div class="rb-stat-sub">
                            {{ data.ringkas.item_unik || 0 }} item unik · {{ data.ringkas.versi_unik || 0 }} versi
                        </div>
                    </div>

                    <div class="rb-stat-card indigo">
                        <div class="rb-stat-top">
                            <span class="rb-stat-label">Waktu Baca Aktif</span>
                            <div class="rb-stat-icon-wrap indigo">
                                <i class="bi bi-stopwatch"></i>
                            </div>
                        </div>
                        <div class="rb-stat-value tnum">{{ durasi(data.ringkas.total_detik || 0) }}</div>
                        <div class="rb-stat-sub">Total durasi aktif membaca</div>
                    </div>

                    <div class="rb-stat-card success">
                        <div class="rb-stat-top">
                            <span class="rb-stat-label">Dikonfirmasi</span>
                            <div class="rb-stat-icon-wrap success">
                                <i class="bi bi-check2-all"></i>
                            </div>
                        </div>
                        <div class="rb-stat-value tnum text-success">{{ data.ringkas.dikonfirmasi || 0 }}</div>
                        <div class="rb-stat-sub">Pemahaman terkonfirmasi</div>
                    </div>

                    <div class="rb-stat-card warning">
                        <div class="rb-stat-top">
                            <span class="rb-stat-label">Pernah Dibuka</span>
                            <div class="rb-stat-icon-wrap warning">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                        <div class="rb-stat-value tnum text-amber-600">{{ data.ringkas.pernah_dibuka || 0 }}</div>
                        <div class="rb-stat-sub">
                            {{ (data.ringkas.total_rekam || 0) - (data.ringkas.pernah_dibuka || 0) }} belum dibuka
                        </div>
                    </div>
                </div>

                <!-- COCKPIT PENCARIAN & FILTER KOMPOSISI -->
                <div class="rb-cockpit">
                    <div class="rb-search-wrapper">
                        <i class="bi bi-search rb-search-icon"></i>
                        <input
                            v-model="pencarian"
                            type="text"
                            class="rb-search-input"
                            placeholder="Cari judul dokumen, nomor dokumen resmi, atau nama paket..."
                        />
                        <button
                            v-if="pencarian"
                            type="button"
                            class="rb-search-clear"
                            @click="pencarian = ''"
                        >
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <div class="rb-filter-groups">
                        <!-- Komposisi Status & Tipe -->
                        <div class="rb-komposisi">
                            <div class="rb-filter-row">
                                <span class="rb-filter-row-label">Status</span>
                                <div class="rb-chips">
                                    <button
                                        v-for="c in data.komposisi.status"
                                        :key="'status' + c.label"
                                        type="button"
                                        class="rb-chip"
                                        :class="[`c-${c.label.toLowerCase()}`, { on: saring.status === c.label }]"
                                        @click="toggle('status', c.label)"
                                    >
                                        <span>{{ c.label }}</span>
                                        <span class="rb-chip-n tnum">{{ c.jumlah }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="rb-filter-row">
                                <span class="rb-filter-row-label">Tipe</span>
                                <div class="rb-chips">
                                    <button
                                        v-for="c in data.komposisi.tipe"
                                        :key="'tipe' + c.label"
                                        type="button"
                                        class="rb-chip"
                                        :class="[`c-${c.label.toLowerCase()}`, { on: saring.tipe === c.label }]"
                                        @click="toggle('tipe', c.label)"
                                    >
                                        <span>{{ c.label }}</span>
                                        <span class="rb-chip-n tnum">{{ c.jumlah }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            v-if="saring.status || saring.tipe || pencarian"
                            type="button"
                            class="rb-reset-filter-btn"
                            @click="resetSaring"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </button>
                    </div>
                </div>

                <!-- ALUR REVISI / JEJAK PERGANTIAN VERSI DOKUMEN -->
                <div v-if="data.jejak_versi && data.jejak_versi.length" class="rb-jejak">
                    <div class="rb-jejak-header">
                        <i class="bi bi-clock-history text-indigo-600"></i>
                        <span>Item yang versinya berganti selama ditugaskan</span>
                    </div>
                    <div class="rb-jejak-list">
                        <div v-for="j in data.jejak_versi" :key="j.Judul_Item" class="rb-jejak-item">
                            <div class="rb-jejak-nama">
                                <span class="fw-bold text-dark">{{ j.Judul_Item }}</span>
                                <span class="rb-jejak-paket">{{ j.Nama_Paket }}</span>
                            </div>
                            <div class="rb-jejak-rel">
                                <template v-for="(v, i) in j.versi" :key="v.Versi_Ke">
                                    <span
                                        class="rb-jejak-versi"
                                        :class="`s-${String(v.Status).toLowerCase()}`"
                                        :title="v.Waktu || 'Tanpa catatan waktu'"
                                    >
                                        {{ getVersiLabel(v.Versi_Ke) }}
                                        <span class="rb-jejak-status-tag">{{ v.Status }}</span>
                                    </span>
                                    <i v-if="i < j.versi.length - 1" class="bi bi-arrow-right rb-jejak-panah"></i>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DAFTAR PAKET (AKORDEON PER PAKET DOKUMEN) -->
                <div class="rb-paket-list">
                    <div v-for="g in grup" :key="g.id" class="rb-grup" :class="{ 'is-collapsed': terlipat[g.id] }">
                        <!-- HEADER KELOMPOK PAKET -->
                        <button type="button" class="rb-grup-kepala" @click="lipat(g.id)">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="rb-grup-folder-ico">
                                    <i class="bi bi-folder2-open"></i>
                                </div>
                                <div class="min-w-0">
                                    <h6 class="rb-grup-nama mb-0 text-truncate">{{ g.nama || 'Tanpa paket' }}</h6>
                                    <div class="rb-grup-sub-info">
                                        <span class="tnum">{{ g.baris.length }}</span> item dokumen tercatat
                                    </div>
                                </div>
                            </div>

                            <div class="rb-grup-rekap">
                                <div class="rb-grup-fakta-pill">
                                    <span class="rb-grup-fakta">
                                        <strong class="tnum">{{ g.baris.length }}</strong> rekam
                                    </span>
                                    <span class="rb-grup-divider">·</span>
                                    <span class="rb-grup-fakta text-success">
                                        <strong class="tnum">{{ g.dikonfirmasi }}</strong> dikonfirmasi
                                    </span>
                                    <span class="rb-grup-divider">·</span>
                                    <span class="rb-grup-fakta">
                                        <i class="bi bi-stopwatch me-1 text-muted"></i>
                                        <strong class="tnum">{{ durasi(g.detik) }}</strong>
                                    </span>
                                </div>
                                <i class="bi rb-grup-panah" :class="terlipat[g.id] ? 'bi-chevron-right' : 'bi-chevron-down'"></i>
                            </div>
                        </button>

                        <!-- TABEL GRANULAR PER PAKET -->
                        <div v-if="!terlipat[g.id]" class="rb-tabel-bungkus">
                            <table class="rb-tabel">
                                <thead>
                                    <tr>
                                        <th style="width: 32%">Item</th>
                                        <th style="width: 10%">Tipe</th>
                                        <th style="width: 12%" class="text-center">Versi</th>
                                        <th style="width: 12%" class="text-center">Status</th>
                                        <th style="width: 14%">Dibuka</th>
                                        <th style="width: 14%">Dikonfirmasi</th>
                                        <th style="width: 10%" class="text-center">Durasi aktif</th>
                                        <th style="width: 8%" class="text-center">Halaman</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(r, i) in g.baris" :key="i" class="rb-row">
                                        <td>
                                            <div class="rb-item-title" :title="r.Judul_Item">{{ r.Judul_Item || '—' }}</div>
                                            <div v-if="r.Nomor_SOP_Resmi" class="rb-item-code">
                                                <i class="bi bi-file-earmark-text me-1"></i>{{ r.Nomor_SOP_Resmi }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="rb-tipe" :class="`t-${String(r.Tipe_Item).toLowerCase()}`">
                                                {{ r.Tipe_Item || 'DOKUMEN' }}
                                            </span>
                                        </td>
                                        <td class="text-center tnum">
                                            <span class="rb-versi-pill">{{ getVersiLabel(r.Versi_Ke) }}</span>
                                            <div v-if="r.Nomor_Pengesahan" class="rb-sah" :title="tipSah(r)">
                                                <i class="bi bi-patch-check-fill text-success me-0.5"></i>{{ r.Nomor_Pengesahan }}
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="rb-status" :class="`s-${String(r.Status).toLowerCase()}`">
                                                <i class="bi" :class="getStatusIcon(r.Status)"></i>
                                                {{ r.Status }}
                                            </span>
                                        </td>
                                        <td class="rb-time-cell">
                                            <span v-if="r.Waktu_Buka" class="text-slate-700">
                                                <i class="bi bi-calendar3 me-1 text-muted"></i>{{ r.Waktu_Buka }}
                                            </span>
                                            <span v-else class="text-muted opacity-50">—</span>
                                        </td>
                                        <td class="rb-time-cell">
                                            <span v-if="r.Waktu_Konfirmasi" class="text-emerald-700 fw-semibold">
                                                <i class="bi bi-check-circle me-1 text-emerald-600"></i>{{ r.Waktu_Konfirmasi }}
                                            </span>
                                            <span v-else class="text-muted opacity-50">—</span>
                                        </td>
                                        <td class="text-center tnum fw-bold text-slate-800">
                                            {{ durasi(r.Durasi_Aktif_Detik) }}
                                        </td>
                                        <td class="text-center small tnum">
                                            <template v-if="r.Halaman_Terakhir !== null">
                                                <span class="rb-page-badge">Hal. {{ r.Halaman_Terakhir }}</span>
                                            </template>
                                            <span v-else class="text-muted opacity-50">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- HASIL SARINGAN KOSONG -->
                <div v-if="!grup.length" class="rb-kosong-saring">
                    <i class="bi bi-funnel text-muted mb-2 fs-4"></i>
                    <div class="fw-bold text-dark mb-1">Tidak Ada Rekam yang Cocok</div>
                    <p class="text-muted small mb-2">Tidak ditemukan rekam baca dengan kata kunci atau filter status terpilih.</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" @click="resetSaring">
                        Bersihkan Saringan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * RekamBacaKaryawan — Audit detail faktual rekam baca per item dokumen karyawan.
 * Menampilkan ringkasan statistik bento, cockpit filter komposisi status/tipe, jejak pergantian versi dokumen,
 * serta tabel interaktif per paket penugasan sesuai standar HCIS Enterprise Suite.
 */
export default {
    name: 'RekamBacaKaryawan',
    props: {
        data: {
            type: Object,
            default: () => ({ item: [], komposisi: { status: [], tipe: [] }, ringkas: {}, jejak_versi: [] }),
        },
        loading: { type: Boolean, default: false },
    },
    data() {
        return {
            saring: { status: null, tipe: null },
            pencarian: '',
            terlipat: {},
        };
    },
    computed: {
        tersaring() {
            const q = (this.pencarian || '').trim().toLowerCase();

            return (this.data.item || []).filter((r) => {
                if (this.saring.status && r.Status !== this.saring.status) return false;
                if (this.saring.tipe && r.Tipe_Item !== this.saring.tipe) return false;
                if (q) {
                    const matchJudul = (r.Judul_Item || '').toLowerCase().includes(q);
                    const matchNomor = (r.Nomor_SOP_Resmi || '').toLowerCase().includes(q);
                    const matchPaket = (r.Nama_Paket || '').toLowerCase().includes(q);
                    if (!matchJudul && !matchNomor && !matchPaket) return false;
                }

                return true;
            });
        },
        grup() {
            const peta = new Map();

            for (const r of this.tersaring) {
                const id = r.Id_SOP_Paket || `nama:${r.Nama_Paket || ''}`;
                if (!peta.has(id)) {
                    peta.set(id, { id, nama: r.Nama_Paket, baris: [], detik: 0, dikonfirmasi: 0 });
                }
                const g = peta.get(id);
                g.baris.push(r);
                g.detik += Number(r.Durasi_Aktif_Detik) || 0;
                if (r.Waktu_Konfirmasi) g.dikonfirmasi++;
            }

            return [...peta.values()];
        },
        semuaTerlipat() {
            if (!this.grup.length) return false;
            return this.grup.every((g) => this.terlipat[g.id]);
        },
    },
    methods: {
        toggle(sumbu, label) {
            this.saring = { ...this.saring, [sumbu]: this.saring[sumbu] === label ? null : label };
        },
        resetSaring() {
            this.saring = { status: null, tipe: null };
            this.pencarian = '';
        },
        lipat(id) {
            this.terlipat = { ...this.terlipat, [id]: !this.terlipat[id] };
        },
        toggleSemuaLipatan() {
            const target = !this.semuaTerlipat;
            const update = {};
            this.grup.forEach((g) => {
                update[g.id] = target;
            });
            this.terlipat = update;
        },
        getVersiLabel(v) {
            if (this.$vlabel) return this.$vlabel(v);
            const n = Number(v) || 0;
            return n < 10 ? `v0${n}` : `v${n}`;
        },
        getStatusIcon(status) {
            const s = String(status).toUpperCase();
            if (s === 'DIKONFIRMASI' || s === 'SELESAI') return 'bi-check2-circle';
            if (s === 'DIBUKA') return 'bi-play-circle';
            if (s === 'BELUM') return 'bi-circle';
            if (s === 'DIGANTIKAN') return 'bi-slash-circle';
            return 'bi-info-circle';
        },
        tipSah(r) {
            return [r.Disahkan_Oleh, r.Tanggal_Pengesahan].filter(Boolean).join(' · ') || 'Disahkan';
        },
        durasi(d) {
            const n = Number(d) || 0;
            if (!n) return '—';
            if (n < 60) return `${n} dtk`;
            if (n < 3600) return `${Math.round(n / 60)}m`;
            const jam = Math.floor(n / 3600);
            const menit = Math.round((n % 3600) / 60);

            return menit ? `${jam}j ${menit}m` : `${jam}j`;
        },
    },
};
</script>

<style scoped>
.rb-view {
    width: 100%;
}

/* ══ Hero Main Container Card ══ */
.rb-hero-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 20px;
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.rb-hero-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(180deg, #fbfcfe 0%, var(--hcis-slate-50) 100%);
    border-bottom: 1px solid #edf2f7;
}

.rb-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.rb-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-indigo-500);
    text-transform: uppercase;
}

.rb-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.02em;
}

.rb-total-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.rb-toggle-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    background: transparent;
    border: 1px solid var(--hcis-slate-300);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.15s ease;
}

.rb-toggle-all-btn:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-400);
    color: var(--hcis-slate-900);
}

/* ══ Body & Content ══ */
.rb-content-body {
    padding: 1.35rem 1.5rem;
}

.rb-loading,
.rb-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 1rem;
}

.rb-kosong-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-400);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

/* ══ 4 Bento Stat Cards ══ */
.rb-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
    margin-bottom: 1.35rem;
}

.rb-stat-card {
    padding: 1rem 1.15rem;
    border-radius: 16px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}

.rb-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -4px rgba(15, 23, 42, 0.07);
    border-color: var(--hcis-slate-300);
}

.rb-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
}

.rb-stat-label {
    font-size: 0.7rem;
    font-weight: 750;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.rb-stat-icon-wrap {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}

.rb-stat-icon-wrap.brand { background: var(--hcis-indigo-50); color: var(--hcis-indigo-600); }
.rb-stat-icon-wrap.indigo { background: var(--hcis-violet-50); color: var(--hcis-violet-600); }
.rb-stat-icon-wrap.success { background: var(--hcis-emerald-50); color: var(--hcis-emerald-600); }
.rb-stat-icon-wrap.warning { background: var(--hcis-amber-50); color: var(--hcis-amber-600); }

.rb-stat-value {
    font-size: 1.5rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.rb-stat-sub {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin-top: 0.3rem;
    font-weight: 500;
}

/* ══ Cockpit Filter & Search ══ */
.rb-cockpit {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 1rem 1.15rem;
    margin-bottom: 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.rb-search-wrapper {
    position: relative;
    width: 100%;
}

.rb-search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-400);
    font-size: 0.9rem;
    pointer-events: none;
}

.rb-search-input {
    width: 100%;
    height: 38px;
    padding: 0 2.25rem 0 2.6rem;
    border-radius: 10px;
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    font-size: 0.82rem;
    color: var(--hcis-slate-800);
    outline: none;
    transition: all 0.15s ease;
}

.rb-search-input:focus {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.rb-search-clear {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--hcis-slate-400);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.rb-search-clear:hover {
    color: var(--hcis-slate-700);
}

.rb-filter-groups {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.rb-komposisi {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.rb-filter-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.rb-filter-row-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.rb-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.rb-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    color: var(--hcis-slate-700);
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 0.15s ease;
}

.rb-chip:hover {
    border-color: var(--hcis-indigo-400);
    color: var(--hcis-indigo-700);
    background: var(--hcis-violet-50);
}

.rb-chip.on {
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.rb-chip-n {
    font-size: 0.66rem;
    font-weight: 800;
    background: rgba(15, 23, 42, 0.07);
    border-radius: 999px;
    padding: 0.05rem 0.4rem;
}

.rb-chip.on .rb-chip-n {
    background: rgba(255, 255, 255, 0.25);
    color: var(--hcis-white);
}

.rb-reset-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    cursor: pointer;
    transition: all 0.15s ease;
}

.rb-reset-filter-btn:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-900);
}

/* ══ Jejak Versi Alur ══ */
.rb-jejak {
    border: 1px solid var(--hcis-indigo-100);
    border-radius: 16px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.35rem;
    background: #f8faff;
}

.rb-jejak-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--hcis-indigo-800);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.75rem;
}

.rb-jejak-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.rb-jejak-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.5rem 0;
    border-top: 1px dashed var(--hcis-slate-200);
}

.rb-jejak-item:first-of-type {
    border-top: none;
    padding-top: 0;
}

.rb-jejak-nama {
    font-size: 0.85rem;
}

.rb-jejak-paket {
    display: block;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}

.rb-jejak-rel {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.rb-jejak-versi {
    font-size: 0.72rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.2rem 0.55rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-300);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.rb-jejak-status-tag {
    font-size: 0.62rem;
    font-weight: 750;
    opacity: 0.8;
    text-transform: uppercase;
}

.rb-jejak-versi.s-dikonfirmasi {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-800);
    border-color: var(--hcis-emerald-200);
}

.rb-jejak-versi.s-belum {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

.rb-jejak-versi.s-digantikan {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
    border-color: var(--hcis-slate-200);
}

.rb-jejak-panah {
    color: var(--hcis-slate-400);
    font-size: 0.75rem;
}

/* ══ Daftar Akordeon Paket Dokumen ══ */
.rb-paket-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.rb-grup {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    overflow: hidden;
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.rb-grup:hover {
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.05);
}

.rb-grup-kepala {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    text-align: left;
    border: 0;
    background: var(--hcis-white);
    padding: 0.95rem 1.25rem;
    cursor: pointer;
    transition: background 0.15s ease;
}

.rb-grup-kepala:hover {
    background: var(--hcis-slate-50);
}

.rb-grup-folder-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.rb-grup-nama {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

.rb-grup-sub-info {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-weight: 500;
}

.rb-grup-rekap {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.rb-grup-fakta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.74rem;
}

.rb-grup-divider {
    color: var(--hcis-slate-300);
}

.rb-grup-fakta {
    color: var(--hcis-slate-500);
    font-weight: 600;
    white-space: nowrap;
}

.rb-grup-fakta strong {
    color: var(--hcis-slate-900);
    font-weight: 800;
}

.rb-grup-panah {
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    transition: transform 0.2s ease;
}

/* ══ Tabel Rekam Granular ══ */
.rb-tabel-bungkus {
    overflow-x: auto;
    border-top: 1px solid var(--hcis-slate-100);
}

.rb-tabel {
    width: 100%;
    margin-bottom: 0;
    border-collapse: collapse;
}

.rb-tabel th {
    background: var(--hcis-slate-50);
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-200);
    white-space: nowrap;
}

.rb-tabel td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: middle;
    font-size: 0.82rem;
}

.rb-row {
    transition: background 0.12s ease;
}

.rb-row:hover {
    background: #fbfcfe;
}

.rb-row:last-child td {
    border-bottom: none;
}

.rb-item-title {
    font-weight: 750;
    color: var(--hcis-slate-900);
    max-width: 320px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rb-item-code {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    font-family: var(--hcis-font-mono, monospace);
    margin-top: 0.15rem;
}

.rb-tipe {
    display: inline-block;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    border-radius: 6px;
    padding: 0.15rem 0.5rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
}

.rb-tipe.t-dokumen {
    background: #eff6ff;
    color: #1e40af;
    border-color: #dbeafe;
}

.rb-tipe.t-paragraf {
    background: var(--hcis-violet-50);
    color: #5b21b6;
    border-color: #ede9fe;
}

.rb-tipe.t-video {
    background: #fff1f2;
    color: #9f1239;
    border-color: var(--hcis-rose-100);
}

.rb-tipe.t-kuis {
    background: var(--hcis-yellow-50);
    color: #854d0e;
    border-color: var(--hcis-yellow-200);
}

.rb-versi-pill {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.12rem 0.5rem;
    border-radius: 6px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-800);
}

.rb-status {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    border: 1px solid var(--hcis-slate-200);
}

.rb-status.s-dikonfirmasi,
.rb-status.s-selesai {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-800);
    border-color: var(--hcis-emerald-200);
}

.rb-status.s-dibuka {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-800);
    border-color: var(--hcis-indigo-200);
}

.rb-status.s-belum {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

.rb-status.s-digantikan {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
    border-color: var(--hcis-slate-200);
}

.rb-time-cell {
    font-size: 0.78rem;
    white-space: nowrap;
}

.rb-page-badge {
    display: inline-block;
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-700);
    font-size: 0.72rem;
    font-weight: 700;
}

.rb-sah {
    margin-top: 0.2rem;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-emerald-600);
    white-space: nowrap;
}

.rb-kosong-saring {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 2.5rem 1rem;
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 14px;
    margin-top: 1rem;
}

@media (max-width: 992px) {
    .rb-stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .rb-stat-grid {
        grid-template-columns: 1fr;
    }
    .rb-content-body {
        padding: 1rem;
    }
    .rb-hero-header {
        padding: 1rem;
    }
}
</style>
