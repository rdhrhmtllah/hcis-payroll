<template>
    <HcisDrawer
        v-model="buka"
        direction="rtl"
        size="34rem"
        sentiment="neutral"
        :show-close="true"
        custom-class="itp-drawer"
    >
        <!-- ══ 1. DRAWER HEADER ══ -->
        <template #header>
            <div class="itp-hdr-wrap">
                <div class="itp-hdr-ico">
                    <HcisIcon name="journal-bookmark-fill" size="xs" />
                </div>
                <div class="itp-hdr-main min-w-0">
                    <div class="itp-hdr-title-row">
                        <h3 class="itp-title" :title="data ? data.nama : ''">
                            {{ data ? data.nama : 'Memuat Rincian Dokumen…' }}
                        </h3>
                        <span
                            v-if="data?.prioritas && data.prioritas !== 'NORMAL'"
                            class="itp-prio-badge"
                            :class="data.prioritas.toLowerCase()"
                        >
                            {{ data.prioritas }}
                        </span>
                    </div>

                    <div v-if="data?.kode || (data?.versi_paket && data.versi_paket > 1)" class="itp-header-chips">
                        <span v-if="data?.kode" class="itp-kode-pill" title="Klik untuk salin kode dokumen" @click="salinKode">
                            <HcisIcon name="file-earmark-code" size="2xs" />
                            <span class="font-mono">{{ data.kode }}</span>
                            <HcisIcon :name="sudahSalin ? 'check2' : 'copy'" size="2xs" class="ms-0.5 text-muted" />
                        </span>
                        <span v-if="data?.versi_paket && data.versi_paket > 1" class="itp-ver-pill" :title="`Versi Paket: v${data.versi_paket}.0`">
                            v{{ data.versi_paket }}.0
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ 2. DRAWER BODY ══ -->
        <div class="itp-content">
            <!-- Loading Skeletons -->
            <div v-if="memuat" class="itp-loading">
                <HcisSkeleton width="100%" height="70px" rounded="lg" class="mb-3" />
                <HcisSkeleton width="100%" height="45px" rounded="md" class="mb-3" />
                <div class="d-flex gap-2 mb-4">
                    <HcisSkeleton width="33%" height="52px" rounded="md" />
                    <HcisSkeleton width="33%" height="52px" rounded="md" />
                    <HcisSkeleton width="34%" height="52px" rounded="md" />
                </div>
                <HcisSkeleton v-for="n in 4" :key="n" width="100%" height="68px" rounded="lg" class="mb-2" />
            </div>

            <!-- Loaded Content -->
            <template v-else-if="data">
                <!-- Tujuan & Ruang Lingkup Prosedur -->
                <div v-if="data.tujuan" class="itp-tujuan-box">
                    <div class="itp-tujuan-head">
                        <HcisIcon name="info-circle-fill" size="2xs" />
                        <span>Tujuan &amp; Ruang Lingkup</span>
                    </div>
                    <p class="itp-tujuan-text">{{ data.tujuan }}</p>
                </div>

                <!-- Ringkasan Progres & Metrik Pembacaan -->
                <div class="itp-stats-card">
                    <template v-if="totalItem > 0">
                        <div class="itp-progress-header">
                            <div class="d-flex align-items-center gap-2">
                                <HcisIcon name="award-fill" size="xs" :class="persenTuntas >= 100 ? 'text-success' : 'text-primary'" />
                                <span class="itp-progress-title">Progres Pembacaan</span>
                            </div>
                            <span class="itp-progress-pct tnum"><strong>{{ tuntas }}</strong> / {{ totalItem }} Bagian ({{ persenTuntas }}%)</span>
                        </div>

                        <HcisProgress
                            :value="persenTuntas"
                            :sentiment="persenTuntas >= 100 ? 'success' : persenTuntas > 0 ? 'brand' : 'neutral'"
                            size="sm"
                            :show-value="false"
                            class="mb-3"
                        />
                    </template>

                    <div class="itp-metrics-grid" :class="{ 'no-border': totalItem === 0 }">
                        <div class="itp-metric-item">
                            <span class="itp-metric-val tnum">{{ totalItem }}</span>
                            <span class="itp-metric-lbl">{{ totalItem > 0 ? 'Total Bab' : 'Jumlah Bab' }}</span>
                        </div>
                        <div class="itp-metric-item">
                            <span class="itp-metric-val tnum">~{{ data.estimasi || 10 }}m</span>
                            <span class="itp-metric-lbl">Estimasi Waktu</span>
                        </div>
                        <div class="itp-metric-item">
                            <span class="itp-metric-val tnum" :class="{ 'text-success': tuntas === totalItem && totalItem > 0 }">
                                {{ totalItem > 0 ? tuntas : (data.tipe || 'Publik') }}
                            </span>
                            <span class="itp-metric-lbl">{{ totalItem > 0 ? 'Telah Tuntas' : 'Akses Dokumen' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Section: Filter & Daftar Isi Bab -->
                <div class="itp-section-top">
                    <div class="itp-section-header">
                        <div class="d-flex align-items-center gap-2">
                            <HcisIcon name="list-nested" size="xs" />
                            <span class="itp-section-title">Daftar Isi Prosedur</span>
                        </div>
                        <span class="itp-section-sub">{{ totalItem > 0 ? `${filteredItems.length} bab ditampilkan` : '0 bab' }}</span>
                    </div>

                    <!-- Mini Search / Filter Bab jika item > 4 -->
                    <div v-if="data.item && data.item.length > 4" class="itp-item-search">
                        <HcisIcon name="search" size="2xs" />
                        <input
                            v-model="filterTeks"
                            type="search"
                            placeholder="Cari bagian dalam dokumen ini…"
                            class="itp-search-input"
                        />
                        <button v-if="filterTeks" type="button" class="itp-clear-btn" @click="filterTeks = ''">
                            <HcisIcon name="x" size="2xs" />
                        </button>
                    </div>
                </div>

                <!-- Daftar Item Prosedur -->
                <template v-if="data.item && data.item.length > 0">
                    <div v-if="filteredItems.length" class="itp-list">
                        <div
                            v-for="(it, i) in filteredItems"
                            :key="i"
                            class="itp-item-card"
                            :class="statusKelas(it.status)"
                        >
                            <!-- Index / Status Badge -->
                            <div class="itp-item-badge">
                                <template v-if="['SELESAI', 'DIKONFIRMASI'].includes(it.status)">
                                    <HcisIcon name="check-circle-fill" size="sm" class="text-success" />
                                </template>
                                <template v-else-if="it.status === 'DIBUKA'">
                                    <span class="itp-open-ring">
                                        <HcisIcon name="hourglass-split" size="2xs" class="text-warning" />
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="itp-no tnum">{{ i + 1 }}</span>
                                </template>
                            </div>

                            <!-- Media Type Icon -->
                            <div class="itp-item-icon" :title="label(it.tipe)">
                                <HcisIcon :name="ikon(it.tipe)" size="xs" />
                            </div>

                            <!-- Content Details -->
                            <div class="itp-item-details">
                                <div class="itp-item-title-row">
                                    <span class="itp-item-title">{{ it.judul }}</span>
                                    <span v-if="!it.wajib" class="itp-opsional-pill">Opsional</span>
                                </div>

                                <div class="itp-item-meta">
                                    <span class="itp-type-pill">{{ label(it.tipe) }}</span>
                                    <span v-if="it.nomor" class="itp-meta-code font-mono">{{ it.nomor }}</span>
                                    <span v-if="it.versi" class="itp-meta-ver">v{{ it.versi }}</span>
                                    <span v-if="it.berlaku" class="itp-meta-date">· Berlaku: {{ it.berlaku }}</span>
                                </div>
                            </div>

                            <!-- Status Indicator Label -->
                            <div class="itp-item-status-col">
                                <span class="itp-status-chip" :class="it.status ? it.status.toLowerCase() : 'belum'">
                                    {{ statusChipLabel(it.status) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="itp-empty-box py-3 text-center text-muted">
                        <HcisIcon name="search" size="sm" class="mb-1 text-slate-400" />
                        <p class="mb-0 font-sm">Tidak ada bagian yang cocok dengan kata kunci "{{ filterTeks }}"</p>
                    </div>
                </template>

                <div v-else class="itp-empty-box py-3 text-center text-muted">
                    <HcisIcon name="file-earmark-text" size="sm" class="mb-1 text-indigo-400" />
                    <p class="mb-0 font-sm">Dokumen ini merupakan prosedur tunggal (belum ada rincian sub-bab).</p>
                </div>
            </template>

            <!-- Error / Not Found State -->
            <div v-else class="py-4">
                <HcisEmptyState
                    title="Dokumen Tidak Dapat Ditampilkan"
                    description="Rincian prosedur kerja publik ini tidak dapat dimuat atau belum tersedia."
                    icon="file-earmark-x"
                    variant="dashed"
                    sentiment="warning"
                >
                    <template #actions>
                        <HcisButton label="Tutup Laci" variant="secondary" size="sm" @click="buka = false" />
                    </template>
                </HcisEmptyState>
            </div>
        </div>

        <!-- ══ 3. DRAWER FOOTER ══ -->
        <template #footer v-if="data">
            <div class="itp-footer-actions">
                <HcisButton
                    label="Tutup"
                    variant="secondary"
                    size="sm"
                    class="itp-btn-tutup"
                    @click="buka = false"
                />
                <HcisButton
                    :label="labelBukaSop"
                    variant="primary"
                    size="sm"
                    :href="`/sop/${data.id}`"
                    trailing-icon="arrow-right"
                    class="itp-btn-buka"
                />
            </div>
        </template>
    </HcisDrawer>
</template>

<script>
import axios from 'axios';
import HcisDrawer from '@/components/ui/HcisDrawer.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisProgress from '@/components/ui/HcisProgress.vue';
import HcisSkeleton from '@/components/ui/HcisSkeleton.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';

const IKON = {
    DOKUMEN: 'file-earmark-pdf-fill',
    VIDEO: 'play-circle-fill',
    KUIS: 'patch-question-fill',
    FORM: 'ui-checks',
    PARAGRAF: 'text-paragraph',
    TAUTAN: 'link-45deg',
    ABSENSI: 'qr-code-scan',
};
const LABEL = {
    DOKUMEN: 'Dokumen PDF',
    VIDEO: 'Video Panduan',
    KUIS: 'Kuis Evaluasi',
    FORM: 'Formulir Digital',
    PARAGRAF: 'Teks Prosedur',
    TAUTAN: 'Tautan Luar',
    ABSENSI: 'Absensi Hadir',
};

export default {
    name: 'IntipDrawer',
    components: {
        HcisDrawer,
        HcisIcon,
        HcisButton,
        HcisProgress,
        HcisSkeleton,
        HcisEmptyState,
    },
    props: {
        modelValue: { type: Boolean, default: false },
        idPaket: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            data: null,
            memuat: false,
            filterTeks: '',
            sudahSalin: false,
            isMobile: typeof window !== 'undefined' ? window.innerWidth < 640 : false,
        };
    },
    computed: {
        buka: {
            get() {
                return this.modelValue;
            },
            set(v) {
                this.$emit('update:modelValue', v);
            },
        },
        totalItem() {
            return this.data?.item ? this.data.item.length : 0;
        },
        tuntas() {
            return (this.data?.item || []).filter((i) => ['SELESAI', 'DIKONFIRMASI'].includes(i.status)).length;
        },
        persenTuntas() {
            if (!this.totalItem) return 0;
            return Math.round((this.tuntas / this.totalItem) * 100);
        },
        labelBukaSop() {
            if (this.tuntas > 0 && this.tuntas < this.totalItem) return 'Lanjut Baca';
            if (this.tuntas > 0 && this.tuntas >= this.totalItem) return 'Baca Ulang';
            return 'Buka SOP';
        },
        filteredItems() {
            if (!this.data?.item) return [];
            if (!this.filterTeks.trim()) return this.data.item;
            const q = this.filterTeks.toLowerCase();
            return this.data.item.filter(
                (it) =>
                    (it.judul || '').toLowerCase().includes(q) ||
                    (it.nomor || '').toLowerCase().includes(q) ||
                    (it.tipe || '').toLowerCase().includes(q)
            );
        },
    },
    mounted() {
        this.handleResize = () => {
            this.isMobile = typeof window !== 'undefined' ? window.innerWidth < 640 : false;
        };
        window.addEventListener('resize', this.handleResize, { passive: true });
    },
    beforeUnmount() {
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
    },
    watch: {
        modelValue(v) {
            if (v && this.idPaket) {
                this.filterTeks = '';
                this.muat();
            }
        },
        idPaket(v) {
            if (v && this.modelValue) {
                this.filterTeks = '';
                this.muat();
            }
        },
    },
    methods: {
        ikon(t) {
            return IKON[t] || 'file-earmark-text';
        },
        label(t) {
            return LABEL[t] || t;
        },
        statusKelas(s) {
            if (['SELESAI', 'DIKONFIRMASI'].includes(s)) return 'is-done';
            if (s === 'DIBUKA') return 'is-open';
            return 'is-pending';
        },
        statusChipLabel(s) {
            if (['SELESAI', 'DIKONFIRMASI'].includes(s)) return 'Tuntas';
            if (s === 'DIBUKA') return 'Sedang Dibaca';
            return 'Belum Dibuka';
        },
        salinKode() {
            if (!this.data?.kode) return;
            navigator.clipboard.writeText(this.data.kode);
            this.sudahSalin = true;
            setTimeout(() => {
                this.sudahSalin = false;
            }, 2000);
        },
        async muat() {
            this.memuat = true;
            this.data = null;
            try {
                const r = await axios.get(`/api/v1/sop/repository/intip/${this.idPaket}`);
                if (r.data.success) {
                    this.data = r.data.result;
                }
            } catch (e) {
                this.data = null;
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
/* ══ Drawer Custom Header ══ */
.itp-hdr-wrap {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
    padding-right: 0.5rem;
}

.itp-hdr-ico {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(79, 70, 229, 0.22) 100%);
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
    border: 1px solid rgba(99, 102, 241, 0.25);
}

.itp-hdr-main {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.itp-hdr-title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    min-width: 0;
}

.itp-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin: 0;
    line-height: 1.3;
    letter-spacing: -0.015em;
    word-break: break-word;
}

.itp-prio-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.65rem;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
    line-height: 1.2;
}

.itp-prio-badge.kritis {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.itp-prio-badge.tinggi {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.itp-header-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-top: 0.1rem;
}

.itp-kode-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 750;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200);
    cursor: pointer;
    transition: all 0.15s ease;
}

.itp-kode-pill:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-900);
}

.itp-ver-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
}

/* ══ Drawer Body Content ══ */
.itp-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0 !important;
}

/* Tujuan Box */
.itp-tujuan-box {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    padding: 0.65rem 0.8rem;
}

.itp-tujuan-head {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-indigo-600);
    margin-bottom: 0.25rem;
}

.itp-tujuan-text {
    font-size: 0.8rem;
    color: var(--hcis-slate-600);
    line-height: 1.5;
    margin: 0;
}

/* Stats & Progress Card */
.itp-stats-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
}

.itp-progress-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.45rem;
}

.itp-progress-title {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
}

.itp-progress-pct {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}

.itp-progress-pct strong {
    color: var(--hcis-slate-900);
    font-weight: 800;
}

.itp-metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.45rem;
    border-top: 1px solid var(--hcis-slate-100);
    padding-top: 0.6rem;
}

.itp-metrics-grid.no-border {
    border-top: 0;
    padding-top: 0;
}

.itp-metric-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0.35rem 0.2rem;
    background: var(--hcis-slate-50);
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-100);
}

.itp-metric-val {
    font-size: 1rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.15;
}

.itp-metric-lbl {
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    margin-top: 0.15rem;
}

/* Section Header & Item Search */
.itp-section-top {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-top: 1px solid var(--hcis-slate-100);
    padding-top: 0.5rem;
}

.itp-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.itp-section-title {
    font-size: 0.75rem;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-slate-700);
}

.itp-section-sub {
    font-size: 0.7rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
}

.itp-item-search {
    position: relative;
    display: flex;
    align-items: center;
}

.itp-item-search i {
    position: absolute;
    left: 0.75rem;
    color: var(--hcis-slate-400);
    pointer-events: none;
}

.itp-search-input {
    width: 100%;
    height: 34px;
    padding: 0 1.8rem 0 2rem;
    font-size: 0.78rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-800);
    outline: none;
    transition: all 0.15s ease;
}

.itp-search-input:focus {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.itp-clear-btn {
    position: absolute;
    right: 0.5rem;
    border: 0;
    background: transparent;
    color: var(--hcis-slate-400);
    cursor: pointer;
    display: grid;
    place-items: center;
}

/* ══ Chapter Items List ══ */
.itp-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.itp-item-card {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.75rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 0.15s ease;
}

.itp-item-card:hover {
    border-color: var(--hcis-indigo-300);
    background: var(--hcis-slate-50);
    transform: translateX(2px);
}

.itp-item-card.is-done {
    background: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
}

.itp-item-card.is-open {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}

.itp-item-badge {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.itp-no {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 0.7rem;
    font-weight: 800;
    display: grid;
    place-items: center;
}

.itp-open-ring {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--hcis-amber-100);
    display: grid;
    place-items: center;
}

.itp-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(199, 210, 254, 0.7);
    flex-shrink: 0;
}

.itp-item-card.is-done .itp-item-icon {
    background: var(--hcis-white);
    color: var(--hcis-green-600);
    border-color: var(--hcis-green-200);
}

.itp-item-details {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.itp-item-title-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.itp-item-title {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    line-height: 1.3;
    word-break: break-word;
}

.itp-opsional-pill {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}

.itp-item-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
    flex-wrap: wrap;
}

.itp-type-pill {
    font-weight: 700;
    color: var(--hcis-indigo-600);
}

.itp-meta-code {
    color: var(--hcis-slate-600);
}

.itp-meta-ver {
    background: var(--hcis-slate-100);
    padding: 0.05rem 0.25rem;
    border-radius: 4px;
    font-weight: 700;
}

.itp-meta-date {
    color: var(--hcis-slate-400);
}

.itp-item-status-col {
    flex-shrink: 0;
    margin-left: auto;
}

.itp-status-chip {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    white-space: nowrap;
}

.itp-status-chip.selesai,
.itp-status-chip.dikonfirmasi {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
}

.itp-status-chip.dibuka {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.itp-status-chip.belum {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
}

/* ══ Empty Box ══ */
.itp-empty-box {
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-200);
    border-radius: 10px;
    padding: 1.1rem 0.85rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}

.itp-empty-box p {
    font-size: 0.78rem;
    color: var(--hcis-slate-500);
    margin: 0;
}

/* ══ Drawer Footer Actions ══ */
.itp-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
}

.itp-btn-tutup {
    min-width: 90px;
    height: 38px;
    justify-content: center;
}

.itp-btn-buka {
    flex: 1;
    height: 38px;
    justify-content: center;
}

@media (max-width: 640px) {
    .itp-content {
        gap: 0.6rem !important;
    }

    .itp-hdr-wrap {
        gap: 0.5rem;
    }

    .itp-hdr-ico {
        width: 30px;
        height: 30px;
        border-radius: 8px;
    }

    .itp-title {
        font-size: 0.95rem;
    }

    .itp-stats-card {
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
    }

    .itp-metrics-grid {
        gap: 0.35rem;
    }

    .itp-metric-item {
        padding: 0.3rem 0.15rem;
    }

    .itp-metric-val {
        font-size: 0.95rem;
    }

    .itp-metric-lbl {
        font-size: 0.62rem;
    }

    .itp-item-card {
        padding: 0.55rem 0.65rem;
        gap: 0.5rem;
        border-radius: 10px;
    }

    .itp-item-icon {
        width: 28px;
        height: 28px;
        border-radius: 7px;
    }

    .itp-item-title {
        font-size: 0.8rem;
    }

    .itp-footer-actions {
        gap: 0.5rem;
    }
}
</style>

<!-- ══ Unscoped Global Overrides untuk Teleported Drawer (.itp-drawer) ══ -->
<style scoped>
:global(.itp-drawer.hcis-drawer) {
    background-color: var(--hcis-white) !important;
}

:global(.itp-drawer .hcis-drawer__header) {
    background: var(--hcis-white) !important;
    border-bottom: 1px solid var(--hcis-slate-100) !important;
    padding: 0.75rem 1rem !important;
}

:global(.itp-drawer .hcis-drawer__header-pattern) {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    background-image: none !important;
}

:global(.itp-drawer .hcis-drawer__mobile-handle) {
    margin: 10px auto 4px auto !important;
    background-color: var(--hcis-slate-300) !important;
}

:global(.itp-drawer .hcis-drawer__body) {
    padding: 0.75rem 1rem !important;
    background: var(--hcis-white) !important;
}

:global(.itp-drawer .hcis-drawer__footer) {
    padding: 0.75rem 1rem !important;
    background: var(--hcis-white) !important;
    border-top: 1px solid var(--hcis-slate-200) !important;
}

@media (max-width: 640px) {
    :global(.itp-drawer.hcis-drawer) {
        border-radius: 20px 20px 0 0 !important;
        background: var(--hcis-white) !important;
    }

    :global(.itp-drawer .hcis-drawer__mobile-handle) {
        margin: 10px auto 2px auto !important;
        background-color: var(--hcis-slate-300) !important;
    }

    :global(.itp-drawer .hcis-drawer__header) {
        background: var(--hcis-white) !important;
        border-bottom: 1px solid var(--hcis-slate-100) !important;
        padding: 0.45rem 0.85rem 0.65rem !important;
    }

    :global(.itp-drawer .hcis-drawer__header-pattern) {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
        background-image: none !important;
    }

    :global(.itp-drawer .hcis-drawer__body) {
        padding: 0.65rem 0.85rem 1rem !important;
        background: var(--hcis-white) !important;
    }

    :global(.itp-drawer .hcis-drawer__footer) {
        padding: 0.65rem 0.85rem !important;
        background: var(--hcis-white) !important;
        border-top: 1px solid var(--hcis-slate-200) !important;
    }

    :global(.itp-drawer .itp-footer-actions .hcis-btn) {
        flex: 1 1 0 !important;
        width: 0 !important;
        min-width: 0 !important;
        height: 42px !important;
        min-height: 42px !important;
        padding: 0 0.5rem !important;
        font-size: 0.82rem !important;
        border-radius: 10px !important;
        justify-content: center !important;
        box-sizing: border-box !important;
    }

    :global(.itp-drawer .itp-footer-actions .hcis-btn__text) {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
        font-weight: 700 !important;
    }
}
</style>
