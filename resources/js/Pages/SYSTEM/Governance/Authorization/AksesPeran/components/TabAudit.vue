<template>
    <div class="ap-audit-tab">
        <!-- ══ 1. HEADER BANNER KEPATUHAN & AUDIT (MODE LENGKAP) ══ -->
        <template v-if="!ringkas">
            <!-- Metrik Ringkasan Audit -->
            <div class="ap-audit-stats-grid mb-3">
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="brand"
                    icon="clock-history"
                    label="Total Jejak Audit"
                    :value="total ? `${total} Entri` : '0 Entri'"
                    caption="Log mutasi wewenang permanen"
                    class="cursor-pointer"
                    @click="pilihKategori('SEMUA')"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="success"
                    icon="person-plus"
                    label="Penugasan & Izin"
                    :value="hitungAksi('tambah')"
                    caption="Pemberian peran & grant halaman"
                    class="cursor-pointer"
                    @click="pilihKategori('tambah')"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="danger"
                    icon="shield-x"
                    label="Pencabutan Akses"
                    :value="hitungAksi('cabut')"
                    caption="Penghapusan penugasan & wewenang"
                    class="cursor-pointer"
                    @click="pilihKategori('cabut')"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="purple"
                    icon="sliders"
                    label="Modifikasi Konfigurasi"
                    :value="hitungAksi('ubah')"
                    caption="Perubahan matriks, batas, & template"
                    class="cursor-pointer"
                    @click="pilihKategori('ubah')"
                />
            </div>

            <!-- Banner Tata Kelola & Integritas Data -->
            <div class="ap-audit-banner mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="ap-kicker">Kepatuhan &amp; Integritas Akses</span>
                            <HcisBadge
                                :label="`${total} Catatan Tersimpan`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                            <HcisBadge label="Immutable Log" variant="purple" appearance="subtle" size="xs" />
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Jejak Audit Perubahan Akses &amp; Wewenang</h5>
                        <p class="ap-kecil text-muted mb-0">
                            Catatan kronologis mutasi wewenang, penugasan peran, pencabutan hak, dan konfigurasi izin.
                            Seluruh rekaman disimpan permanen untuk kepatuhan tata kelola data (audit trail).
                        </p>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            :loading="memuat"
                            label="Segarkan Log"
                            @click="muat(1)"
                        />
                    </div>
                </div>
            </div>

            <!-- ══ 2. HCIS FILTER BAR RESMI ══ -->
            <div class="mb-4">
                <HcisFilterBar
                    v-model:search="saring.cari"
                    search-placeholder="Cari nama pelaku, karyawan sasaran, peran, atau kata kunci…"
                    :quick-chips="quickChipsAudit"
                    :active-chip-value="kategoriAktif"
                    :filters="filterDefinitions"
                    :filter-values="filterValues"
                    collapsible
                    variant="flat"
                    size="sm"
                    sentiment="brand"
                    class="ap-audit-filterbar"
                    @update:search="onSearchUpdate"
                    @chip-click="onChipClick"
                    @filter-change="onFilterChange"
                    @reset="resetFilter"
                >
                    <template #actions>
                        <!-- Rentang Tanggal Ekstra jika di layar luas -->
                        <div class="d-none d-lg-flex align-items-center">
                            <HcisDateRangePicker
                                v-model="saring.rentang"
                                placeholder="Rentang tanggal peristiwa"
                                size="sm"
                                @change="muat(1)"
                                @clear="muat(1)"
                            />
                        </div>
                    </template>
                </HcisFilterBar>
            </div>
        </template>

        <!-- Mode Ringkas (Di dalam Drawer/Laci Peran) -->
        <template v-else>
            <div
                class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom border-light-subtle"
            >
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold small text-dark">Riwayat Peran Ini</span>
                    <HcisBadge :label="`${total} entri`" variant="brand" appearance="subtle" size="xs" />
                </div>
                <HcisButton
                    variant="ghost"
                    size="xs"
                    leading-icon="arrow-clockwise"
                    :loading="memuat"
                    label="Segarkan"
                    @click="muat(1)"
                />
            </div>
        </template>

        <!-- ══ 3. EMPTY STATE JIKA TIDAK ADA LOG ══ -->
        <HcisEmptyState
            v-if="!memuat && !baris.length"
            icon="clock-history"
            title="Tidak Ada Catatan Audit yang Cocok"
            description="Belum ada catatan mutasi wewenang yang sesuai dengan filter atau kata kunci yang dipilih."
            size="sm"
            class="my-4"
        >
            <template #actions>
                <HcisButton
                    v-if="adaFilterAktif"
                    variant="secondary"
                    size="xs"
                    leading-icon="arrow-counterclockwise"
                    label="Hapus Filter &amp; Tampilkan Semua"
                    @click="resetFilter"
                />
            </template>
        </HcisEmptyState>

        <!-- Loading Skeleton -->
        <div v-else-if="memuat && !baris.length" class="p-3">
            <HcisSkeleton :lines="6" />
        </div>

        <!-- ══ 4. LINIMASA AUDIT INTERAKTIF ══ -->
        <div v-else class="ap-linimasa-wrap">
            <div class="ap-linimasa">
                <div v-for="b in tampil" :key="b.id" class="ap-jejak">
                    <!-- Icon Titik Linimasa -->
                    <div class="ap-jejak__node">
                        <div class="ap-jejak__titik" :class="`ap-jejak__titik--${warna(b.aksi)}`">
                            <HcisIcon :name="ikonAksi(b.aksi)" size="xs" />
                        </div>
                        <div class="ap-jejak__line"></div>
                    </div>

                    <!-- Kartu Peristiwa Audit -->
                    <div class="ap-jejak__konten flex-grow-1 min-w-0">
                        <div class="ap-jejak__card">
                            <!-- Baris Judul & Tanggal -->
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1.5">
                                <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                    <span class="fw-semibold text-dark ap-teks-peristiwa">{{ b.f.kalimat }}</span>
                                    <HcisBadge
                                        :label="labelAksi[b.aksi] || b.aksi"
                                        :variant="badgeVariant(b.aksi)"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <div class="text-muted ap-kecil d-flex align-items-center gap-1 flex-shrink-0">
                                    <HcisIcon name="calendar3" size="2xs" />
                                    <span>{{ tanggal(b.waktu) }}</span>
                                </div>
                            </div>

                            <!-- Chip Perubahan (+ Ditambah / - Dibuang / Modifikasi) -->
                            <div v-if="b.f.chip.length" class="d-flex flex-wrap gap-1.5 my-2">
                                <HcisBadge
                                    v-for="c in b.f.chip"
                                    :key="c.label"
                                    :label="(c.jenis === 'tambah' ? '+ ' : c.jenis === 'buang' ? '− ' : '') + c.label"
                                    :variant="
                                        c.jenis === 'tambah' ? 'success' : c.jenis === 'buang' ? 'danger' : 'neutral'
                                    "
                                    appearance="subtle"
                                    size="xs"
                                />
                                <span v-if="b.f.lebih" class="text-muted ap-kecil align-self-center"
                                    >+{{ b.f.lebih }} lagi</span
                                >
                            </div>

                            <!-- Alasan Jika Ada -->
                            <div v-if="b.alasan" class="ap-alasan-box my-2">
                                <HcisIcon
                                    name="chat-quote-fill"
                                    size="xs"
                                    color="muted"
                                    class="me-1.5 align-text-bottom"
                                />
                                <span class="fw-semibold text-muted">Justifikasi:</span>
                                <span class="fst-italic text-dark ms-1">{{ b.alasan }}</span>
                            </div>

                            <!-- Pelaku & Aksi Toggle Rincian -->
                            <div
                                class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 pt-2 border-top border-light-subtle"
                            >
                                <div class="text-muted ap-kecil d-flex align-items-center gap-2">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <HcisIcon name="person" size="2xs" />
                                        Pelaku: <strong class="text-dark">{{ b.oleh || 'Sistem' }}</strong>
                                    </span>
                                    <span v-if="b.target" class="d-inline-flex align-items-center gap-1 ms-2">
                                        <HcisIcon name="person-check" size="2xs" />
                                        Sasaran: <strong class="text-dark">{{ b.target }}</strong>
                                    </span>
                                </div>

                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    :trailing-icon="terbuka[b.id] ? 'chevron-up' : 'chevron-down'"
                                    :label="terbuka[b.id] ? 'Tutup Rincian State' : 'Lihat Rincian State'"
                                    @click="terbuka[b.id] = !terbuka[b.id]"
                                />
                            </div>

                            <!-- Format Diff Terbuka (Split View Rapi) -->
                            <div v-if="terbuka[b.id]" class="ap-mentah-container mt-2">
                                <div class="row g-2">
                                    <div class="col-12 col-md-6">
                                        <div class="ap-mentah-box ap-mentah-box--sebelum">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <div class="ap-mentah-label text-danger">
                                                    <HcisIcon name="dash-circle" size="2xs" class="me-1" />
                                                    Keadaan Sebelumnya
                                                </div>
                                                <button
                                                    type="button"
                                                    class="ap-copy-btn"
                                                    title="Salin JSON Sebelumnya"
                                                    @click="salinTeks(formatJson(b.sebelum))"
                                                >
                                                    <HcisIcon name="copy" size="2xs" />
                                                </button>
                                            </div>
                                            <pre class="ap-mentah">{{ formatJson(b.sebelum) }}</pre>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="ap-mentah-box ap-mentah-box--sesudah">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <div class="ap-mentah-label text-success">
                                                    <HcisIcon name="check-circle" size="2xs" class="me-1" />
                                                    Keadaan Sesudahnya
                                                </div>
                                                <button
                                                    type="button"
                                                    class="ap-copy-btn"
                                                    title="Salin JSON Sesudahnya"
                                                    @click="salinTeks(formatJson(b.sesudah))"
                                                >
                                                    <HcisIcon name="copy" size="2xs" />
                                                </button>
                                            </div>
                                            <pre class="ap-mentah">{{ formatJson(b.sesudah) }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 5. PAGINASI ══ -->
        <div
            v-if="total > 0"
            class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-2 border-top border-light-subtle"
        >
            <span class="text-muted ap-kecil">
                Menampilkan <strong>{{ baris.length }}</strong> dari <strong>{{ total }}</strong> log peristiwa audit.
            </span>
            <HcisPagination
                :total="total"
                :page-size="perHalaman"
                :current-page="halaman"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next"
                @current-change="muat"
                @size-change="onPageSizeChange"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDateRangePicker,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisStatCard,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, tanggal } from '../api/aksesPeranApi.js';
import { formatAudit, labelAksi } from '../api/formatAudit.js';

/** Linimasa audit yang bisa dibaca; dipakai juga sebagai "Riwayat" sebuah peran (prop peran, ringkas). */
const props = defineProps({
    /** id peran → riwayat peran itu saja */
    idPeran: { type: Number, default: null },
    /** tanpa baris saringan (dipakai di laci peran) */
    ringkas: { type: Boolean, default: false },
    /** daftar peran (id → nama) untuk kalimat */
    peran: { type: Array, default: () => [] },
});

const baris = ref([]);
const total = ref(0);
const halaman = ref(1);
const perHalaman = ref(20);
const memuat = ref(false);
const terbuka = reactive({});
const kategoriAktif = ref('SEMUA');
const saring = reactive({ cari: '', aksi: '', rentang: ['', ''] });
let penunda = null;

const namaPeran = computed(() => Object.fromEntries(props.peran.map((p) => [String(p.id), p.nama])));
const tampil = computed(() => baris.value.map((b) => ({ ...b, f: formatAudit(b, namaPeran.value) })));

const adaFilterAktif = computed(() => {
    return Boolean(
        saring.cari ||
        saring.aksi ||
        (saring.rentang?.[0] && saring.rentang?.[1]) ||
        (kategoriAktif.value && kategoriAktif.value !== 'SEMUA'),
    );
});

/* ── Filter Bar Definitions ── */
const quickChipsAudit = computed(() => [
    { value: 'SEMUA', label: 'Semua Peristiwa', icon: 'clock-history' },
    { value: 'tambah', label: 'Penugasan & Izin', icon: 'plus-circle-fill', color: 'success' },
    { value: 'cabut', label: 'Pencabutan Akses', icon: 'dash-circle-fill', color: 'danger' },
    { value: 'ubah', label: 'Modifikasi Konfigurasi', icon: 'sliders', color: 'brand' },
]);

const filterDefinitions = computed(() => [
    {
        key: 'aksi',
        label: 'Aksi Spesifik',
        placeholder: 'Semua aksi spesifik',
        type: 'select',
        options: [
            { value: '', label: 'Semua jenis aksi spesifik' },
            ...Object.entries(labelAksi).map(([key, label]) => ({ value: key, label })),
        ],
        col: 6,
    },
    {
        key: 'rentang',
        label: 'Rentang Tanggal',
        placeholder: 'Pilih rentang tanggal',
        type: 'daterange',
        col: 6,
    },
]);

const filterValues = computed(() => ({
    aksi: saring.aksi,
    rentang: saring.rentang,
}));

function onSearchUpdate(val) {
    saring.cari = val;
    jadwalkan();
}

function onChipClick(chip) {
    pilihKategori(chip.value);
}

function onFilterChange({ key, value }) {
    if (key === 'aksi') {
        saring.aksi = value || '';
        // Selaraskan chip aktif
        if (!value) {
            kategoriAktif.value = 'SEMUA';
        } else if (/^(TUGASKAN|BERI|TAMBAH)/.test(value)) {
            kategoriAktif.value = 'tambah';
        } else if (/^CABUT/.test(value)) {
            kategoriAktif.value = 'cabut';
        } else {
            kategoriAktif.value = 'ubah';
        }
    } else if (key === 'rentang') {
        saring.rentang = value || ['', ''];
    }
    muat(1);
}

function pilihKategori(kat) {
    kategoriAktif.value = kat;
    if (!kat || kat === 'SEMUA') {
        saring.aksi = '';
    } else if (kat === 'tambah') {
        saring.aksi = 'TUGASKAN_PERAN';
    } else if (kat === 'cabut') {
        saring.aksi = 'CABUT_PERAN';
    } else if (kat === 'ubah') {
        saring.aksi = 'UBAH_HAK';
    }
    muat(1);
}

function resetFilter() {
    kategoriAktif.value = 'SEMUA';
    saring.cari = '';
    saring.aksi = '';
    saring.rentang = ['', ''];
    muat(1);
}

function onPageSizeChange(newSize) {
    perHalaman.value = newSize;
    muat(1);
}

function hitungAksi(jenis) {
    if (!baris.value.length) return '—';
    const c = baris.value.filter((b) => warna(b.aksi) === jenis).length;
    return `${c} di halaman ini`;
}

function warna(aksi) {
    if (/^CABUT/.test(aksi)) return 'buang';
    if (/^(TUGASKAN|BERI|TAMBAH)/.test(aksi)) return 'tambah';
    return 'ubah';
}

function ikonAksi(aksi) {
    if (/^CABUT/.test(aksi)) return 'dash-circle';
    if (/^(TUGASKAN|BERI|TAMBAH)/.test(aksi)) return 'check-circle';
    return 'arrow-repeat';
}

function badgeVariant(aksi) {
    if (/^CABUT/.test(aksi)) return 'danger';
    if (/^(TUGASKAN|BERI|TAMBAH)/.test(aksi)) return 'success';
    if (/^UBAH/.test(aksi)) return 'brand';
    return 'purple';
}

function formatJson(val) {
    if (!val || val === '—') return '—';
    if (typeof val === 'object') return JSON.stringify(val, null, 2);
    try {
        const parsed = JSON.parse(val);
        return JSON.stringify(parsed, null, 2);
    } catch {
        return String(val);
    }
}

async function salinTeks(teks) {
    try {
        await navigator.clipboard.writeText(teks);
        hcisMessage.success('JSON state disalin ke papan klip.');
    } catch {
        hcisMessage.info('Gagal menyalin teks.');
    }
}

async function muat(h = 1) {
    memuat.value = true;
    try {
        const r = await aksesPeranApi.audit(h, {
            peran: props.idPeran || undefined,
            aksi: saring.aksi || undefined,
            cari: saring.cari.trim() || undefined,
            dari: saring.rentang?.[0] || undefined,
            sampai: saring.rentang?.[1] || undefined,
        });
        baris.value = r?.data || [];
        total.value = r?.total || 0;
        halaman.value = r?.halaman || h;
        perHalaman.value = r?.per_halaman || perHalaman.value;
    } catch {
        hcisMessage.error('Gagal memuat jejak audit.');
    } finally {
        memuat.value = false;
    }
}

function jadwalkan() {
    clearTimeout(penunda);
    penunda = setTimeout(() => muat(1), 300);
}

onMounted(() => muat(1));
</script>

<style scoped>
.ap-kecil {
    font-size: var(--hcis-font-size-xs, 0.75rem);
}

.ap-audit-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

.ap-audit-banner {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 0.75rem);
    padding: var(--hcis-space-3, 0.75rem) var(--hcis-space-4, 1rem);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.ap-kicker {
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-600, #0284c7);
}

.ap-linimasa-wrap {
    padding: 0.5rem 0;
}

.ap-linimasa {
    position: relative;
}

.ap-jejak {
    display: flex;
    gap: var(--hcis-space-3, 0.75rem);
    position: relative;
}

.ap-jejak__node {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 0 0 2rem;
}

.ap-jejak__titik {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    background: var(--hcis-surface, #ffffff);
    border: 2px solid var(--hcis-border-subtle, #cbd5e1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.ap-jejak__titik--tambah {
    border-color: #10b981;
    color: #059669;
    background: #ecfdf5;
}

.ap-jejak__titik--buang {
    border-color: #ef4444;
    color: #dc2626;
    background: #fef2f2;
}

.ap-jejak__titik--ubah {
    border-color: #0284c7;
    color: #0284c7;
    background: #f0f9ff;
}

.ap-jejak__line {
    flex-grow: 1;
    width: 2px;
    background: var(--hcis-border-subtle, #e2e8f0);
    margin: 4px 0;
}

.ap-jejak:last-child .ap-jejak__line {
    display: none;
}

.ap-jejak__konten {
    padding-bottom: var(--hcis-space-3, 0.75rem);
}

.ap-jejak__card {
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: var(--hcis-space-3, 0.75rem);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition:
        box-shadow 0.15s ease,
        border-color 0.15s ease;
}

.ap-jejak__card:hover {
    border-color: var(--hcis-border-medium, #cbd5e1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}

.ap-teks-peristiwa {
    font-size: var(--hcis-font-size-sm, 0.875rem);
    line-height: 1.4;
}

.ap-alasan-box {
    background: var(--hcis-surface-subtle, #f8fafc);
    border-left: 3px solid var(--hcis-brand-500, #0284c7);
    padding: 0.4rem 0.75rem;
    font-size: var(--hcis-font-size-xs, 0.75rem);
    border-radius: 0 var(--hcis-radius-sm, 0.25rem) var(--hcis-radius-sm, 0.25rem) 0;
}

.ap-mentah-container {
    background: #0f172a;
    border-radius: var(--hcis-radius-md, 0.5rem);
    padding: var(--hcis-space-3, 0.75rem);
    border: 1px solid #1e293b;
}

.ap-mentah-box {
    background: #1e293b;
    border-radius: var(--hcis-radius-sm, 0.25rem);
    padding: var(--hcis-space-2, 0.5rem) var(--hcis-space-3, 0.75rem);
    border-top: 2px solid transparent;
}

.ap-mentah-box--sebelum {
    border-top-color: #ef4444;
}

.ap-mentah-box--sesudah {
    border-top-color: #10b981;
}

.ap-mentah-label {
    font-size: var(--hcis-font-size-2xs, 0.6875rem);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ap-copy-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    transition: color 0.15s ease;
}

.ap-copy-btn:hover {
    color: #f8fafc;
}

.ap-mentah {
    font-family: var(--hcis-font-mono, monospace);
    font-size: var(--hcis-font-size-2xs, 0.6875rem);
    white-space: pre-wrap;
    word-break: break-word;
    color: #e2e8f0;
    margin: 0;
    max-height: 220px;
    overflow-y: auto;
}

.cursor-pointer {
    cursor: pointer;
}

.min-w-0 {
    min-width: 0;
}
</style>
