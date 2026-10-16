<template>
    <div class="pa-range-view">
        <!-- Executive Range Readiness Stats (Ultra-Compact on Mobile) -->
        <HcisStatGroup
            :stats="rangeStats"
            :cols="isMobile ? 2 : 4"
            gap="xs"
            :layout="isMobile ? 'dense' : 'compact'"
            appearance="crystal"
            class="pa-range-stats mb-2"
        />

        <!-- Continuity Alert Banner if gap or invalid -->
        <div v-if="!continuityStatus.valid && filteredBands.length > 1" class="pa-range-alert-banner mb-2" role="alert">
            <div class="pa-range-alert-banner__body">
                <HcisIcon name="exclamation-triangle-fill" size="xs" color="danger" class="mt-0.5 shrink-0" />
                <div class="pa-range-alert-banner__content">
                    <strong>Kesinambungan Rentang Nilai Belum Valid</strong>
                    <p>{{ continuityStatus.message }}</p>
                </div>
            </div>
            <HcisButton
                v-if="bolehTambah"
                :label="isMobile ? 'Tambah' : 'Tambah Penambal Celah'"
                variant="primary"
                size="xs"
                leading-icon="plus-lg"
                class="shrink-0"
                @click="$emit('add', currentTypeDefault, currentUsageDefault)"
            />
        </div>

        <!-- Sequential Continuous Spectrum Runway -->
        <section v-if="sortedActiveBands.length" class="pa-range-spectrum-card mb-2" aria-label="Kontinum Spektrum Rentang Nilai">
            <div class="pa-range-spectrum-card__header">
                <div class="pa-range-spectrum-card__title">
                    <span class="pa-eyebrow">SEKUENS KONTINUM SKALA</span>
                    <h4>Spektrum Distribusi &amp; Rentang Skor</h4>
                </div>
                <div class="pa-range-spectrum-card__controls">
                    <HcisBadge
                        :label="continuityStatus.valid ? (isMobile ? 'Valid (0–∞)' : 'Kontinum Sempurna (0.00 – ∞)') : 'Perlu Penyesuaian'"
                        :variant="continuityStatus.valid ? 'success' : 'danger'"
                        size="xs"
                        dot
                    />
                </div>
            </div>

            <div class="pa-range-spectrum-track">
                <div
                    v-for="band in sortedActiveBands"
                    :key="`spectrum-seg-${band.Id_PA_Score_Range}`"
                    class="pa-range-spectrum-seg"
                    :style="{
                        backgroundColor: band.Warna || '#6366f1',
                        flexGrow: getSpectrumWeight(band),
                    }"
                    :title="`${band.Kode_Range} (${band.Nama_Range}): ${formatRangeInterval(band)}`"
                    @click="$emit('detail', band)"
                >
                    <div class="pa-range-spectrum-label">
                        <strong>{{ band.Kode_Range }}</strong>
                        <small v-if="!isMobile">{{ formatRangeIntervalShort(band) }}</small>
                        <span v-if="band.Target_Persen" class="pa-range-spectrum-pct">{{ band.Target_Persen }}%</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Standard Filter Bar Component -->
        <HcisFilterBar
            :search="search"
            :search-placeholder="isMobile ? 'Cari grade/skala...' : 'Cari kode grade (mis. A, B), nama rentang nilai, atau tipe penilaian...'"
            :search-debounce="200"
            :quick-chips="categoryChips"
            :active-chip-value="activeCategory"
            :show-view-toggle="true"
            :view-mode="viewMode"
            variant="card"
            sentiment="brand"
            size="sm"
            glow
            :results-count="filteredBands.length"
            class="pa-range-filter-bar mb-2"
            @update:search="search = $event"
            @chip-click="(c) => activeCategory = (c?.value ?? c)"
            @update:view-mode="viewMode = $event"
            @reset="resetFilters"
        >
            <template #actions>
                <span v-if="!bolehTambah" class="pa-range-hint-tetap" :title="petunjukTanpaTambah">
                    <HcisIcon name="info-circle" size="2xs" /> {{ isMobile ? 'Sunting batas pita' : petunjukTanpaTambah }}
                </span>
                <HcisButton
                    v-if="bolehTambah"
                    :label="isMobile ? 'Tambah' : 'Tambah Rentang Nilai'"
                    variant="primary"
                    size="xs"
                    leading-icon="plus-lg"
                    @click="$emit('add', currentTypeDefault, currentUsageDefault)"
                />
            </template>
        </HcisFilterBar>

        <!-- Error State -->
        <HcisResultState
            v-if="error"
            status="danger"
            variant="glass"
            layout="compact"
            title="Gagal memuat rentang nilai"
            :description="error"
            :show-retry="false"
        />

        <!-- Loading Skeletons -->
        <div v-else-if="loading && !rows.length" class="pa-range-loading">
            <div v-if="viewMode === 'grid'" class="pa-range-skeleton-grid">
                <div v-for="n in 6" :key="`range-skel-${n}`" class="pa-range-skeleton-card">
                    <div class="pa-range-skel-top">
                        <HcisSkeleton width="60px" height="1.25rem" rounded="sm" />
                        <HcisSkeleton width="50px" height="1.25rem" rounded="pill" />
                    </div>
                    <HcisSkeleton width="80%" height="1.35rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="3.5rem" rounded="md" />
                    <div class="pa-range-skel-footer">
                        <HcisSkeleton width="60px" height="1.5rem" rounded="sm" />
                        <HcisSkeleton width="100px" height="1.5rem" rounded="sm" />
                    </div>
                </div>
            </div>
            <div v-else class="pa-range-skeleton-table">
                <div v-for="n in 5" :key="`table-skel-${n}`" class="pa-range-skel-row">
                    <HcisSkeleton width="28px" height="1rem" rounded="sm" />
                    <HcisSkeleton width="60px" height="1.25rem" rounded="sm" />
                    <HcisSkeleton width="40%" height="1.2rem" rounded="sm" />
                    <HcisSkeleton width="120px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="80px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="80px" height="1.6rem" rounded="sm" />
                </div>
            </div>
        </div>

        <!-- Initial Empty State (When master has 0 records) -->
        <HcisEmptyState
            v-else-if="!rows.length"
            variant="card"
            sentiment="info"
            size="md"
            animated
            title="Belum Ada Data Rentang Nilai"
            description="Belum ada konfigurasi rentang skor atau grade nilai yang terdaftar. Tambahkan rentang nilai pertama untuk menyusun skala evaluasi hasil penilaian."
            icon="bar-chart-steps"
            class="pa-range-empty-state"
        >
            <template #actions>
                <HcisButton
                    v-if="bolehTambah"
                    label="Tambah Rentang Nilai Pertama"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    @click="$emit('add', currentTypeDefault, currentUsageDefault)"
                />
            </template>
        </HcisEmptyState>

        <!-- Filtered Empty State (Search / Filter yields 0 results) -->
        <HcisEmptyState
            v-else-if="!filteredBands.length"
            variant="card"
            sentiment="neutral"
            size="sm"
            animated
            title="Tidak Ada Rentang Nilai yang Cocok"
            :description="search ? `Tidak ditemukan rentang nilai dengan kata kunci '${search}'.` : 'Tidak ditemukan rentang nilai yang sesuai dengan kategori filter yang aktif.'"
            icon="search"
            class="pa-range-empty-state"
        >
            <template #actions>
                <div class="pa-empty-actions-row">
                    <HcisButton
                        label="Reset Filter"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilters"
                    />
                    <HcisButton
                        v-if="bolehTambah"
                        label="Tambah Rentang Nilai"
                        variant="ghost"
                        size="xs"
                        leading-icon="plus-lg"
                        @click="$emit('add', currentTypeDefault, currentUsageDefault)"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Main Content -->
        <div v-else class="pa-range-content">
            <!-- Mode 1: Component Card Grid -->
            <div v-if="viewMode === 'grid'" class="pa-range-grid">
                <HcisCard
                    v-for="band in filteredBands"
                    :key="`band-card-${band.Id_PA_Score_Range}`"
                    variant="default"
                    :sentiment="getBandSentiment(band)"
                    interactive
                    class="pa-range-card"
                    :class="{ 'is-inactive': band.Flag_Aktif === 'T' }"
                    @click="$emit('detail', band)"
                >
                    <template #header>
                        <div class="pa-range-card__header-top">
                            <div class="pa-range-card__identity">
                                <span class="pa-range-swatch-dot" :style="{ backgroundColor: band.Warna || '#6366f1' }" />
                                <strong class="pa-range-code">{{ band.Kode_Range }}</strong>
                                <span class="pa-range-type-pill">{{ band.Score_Type }} &bull; {{ band.Score_Usage }}</span>
                            </div>

                            <HcisStatusBadge
                                :status="band.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                :label="band.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif'"
                                size="xs"
                            />
                        </div>
                    </template>

                    <div class="pa-range-card__body">
                        <h4 class="pa-range-card__title">{{ band.Nama_Range }}</h4>

                        <!-- Range Interval Callout Box -->
                        <div class="pa-range-box" :style="{ borderLeftColor: band.Warna || '#6366f1' }">
                            <div class="pa-range-box__interval">
                                <HcisIcon name="arrows-expand" size="xs" />
                                <code>{{ formatRangeInterval(band) }}</code>
                            </div>
                            <span class="pa-range-box__desc">
                                <template v-if="Number(band.Nilai_Sampai) >= 999.99">
                                    Skor <strong>{{ formatDecimal(band.Nilai_Dari) }}</strong> ke atas (terbuka hingga batas tertinggi)
                                </template>
                                <template v-else>
                                    Mencakup skor <strong>{{ formatDecimal(band.Nilai_Dari) }}</strong> s/d <strong>{{ (Number(band.Nilai_Sampai) - 0.01).toFixed(2) }}</strong>
                                </template>
                            </span>
                        </div>

                        <!-- Target Distribution if configured -->
                        <div v-if="band.Target_Persen !== null && band.Target_Persen !== undefined" class="pa-range-target-box">
                            <div class="pa-range-target-meta">
                                <span class="pa-range-target-label">
                                    <HcisIcon name="pie-chart-fill" size="2xs" /> Target Kuota:
                                </span>
                                <strong class="pa-range-target-value">{{ band.Target_Persen }}%</strong>
                            </div>
                            <div class="pa-range-target-bar">
                                <div
                                    class="pa-range-target-bar-fill"
                                    :style="{
                                        width: `${Math.min(100, Number(band.Target_Persen))}%`,
                                        backgroundColor: band.Warna || '#6366f1'
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <template #footer>
                        <div class="pa-range-card__footer" @click.stop>
                            <span class="pa-range-order-tag">
                                <HcisIcon name="list-ol" size="2xs" /> Urutan {{ band.Urutan_Tampil || 1 }}
                            </span>

                            <div class="pa-range-card__actions">
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    label="Detail"
                                    leading-icon="eye"
                                    @click="$emit('detail', band)"
                                />
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    label="Ubah"
                                    leading-icon="pencil-square"
                                    @click="$emit('edit', band)"
                                />
                                <HcisButton
                                    v-if="canDeactivate && band.Flag_Aktif === 'Y'"
                                    variant="danger-quiet"
                                    size="xs"
                                    :label="isMobile ? 'Nonaktif' : 'Nonaktifkan'"
                                    leading-icon="slash-circle"
                                    @click="$emit('deactivate', band)"
                                />
                            </div>
                        </div>
                    </template>
                </HcisCard>
            </div>

            <!-- Mode 2: Table View -->
            <div v-else class="pa-range-table-wrap">
                <HcisTable
                    :columns="tableColumns"
                    :rows="filteredBands"
                    row-key="Id_PA_Score_Range"
                    responsive="scroll"
                    hoverable
                    variant="glass"
                    sentiment="brand"
                    @row-click="({ row }) => $emit('detail', row)"
                >
                    <template #cell-Urutan_Tampil="{ row }">
                        <span class="pa-mono-text">#{{ row.Urutan_Tampil || 1 }}</span>
                    </template>

                    <template #cell-Kode_Range="{ row }">
                        <div class="pa-table-code-cell">
                            <span class="pa-range-swatch-dot" :style="{ backgroundColor: row.Warna || '#64748b' }" />
                            <strong>{{ row.Kode_Range }}</strong>
                        </div>
                    </template>

                    <template #cell-Nama_Range="{ row }">
                        <div class="pa-table-name-cell">
                            <strong>{{ row.Nama_Range }}</strong>
                            <small>{{ row.Score_Type }} &bull; {{ row.Score_Usage }}</small>
                        </div>
                    </template>

                    <template #cell-Rentang="{ row }">
                        <div class="pa-table-rentang-cell">
                            <code>{{ formatRangeInterval(row) }}</code>
                            <small v-if="Number(row.Nilai_Sampai) >= 999.99">Terbuka ke atas (&ge; {{ formatDecimal(row.Nilai_Dari) }})</small>
                            <small v-else>{{ formatDecimal(row.Nilai_Dari) }} s/d {{ (Number(row.Nilai_Sampai) - 0.01).toFixed(2) }}</small>
                            <small v-if="row.Score_Type === 'FINAL' && row.Score_Usage === 'GRADE' && (row.Batas_Z !== undefined)">
                                Kurva: {{ row.Batas_Z === null ? 'grade terendah' : `z ≥ ${formatDecimal(row.Batas_Z)}` }}
                            </small>
                        </div>
                    </template>

                    <template #cell-Target_Persen="{ row }">
                        <span v-if="row.Target_Persen !== null && row.Target_Persen !== undefined" class="pa-mono-text font-bold">
                            {{ row.Target_Persen }}%
                        </span>
                        <span v-else class="pa-text-muted-dash">&mdash;</span>
                    </template>

                    <template #cell-Warna="{ row }">
                        <div class="pa-table-color-cell">
                            <span class="pa-color-preview-pill" :style="{ backgroundColor: row.Warna || '#64748b' }" />
                            <code>{{ row.Warna || '—' }}</code>
                        </div>
                    </template>

                    <template #cell-Flag_Aktif="{ row }">
                        <HcisStatusBadge
                            :status="row.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                            :label="row.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif'"
                            size="xs"
                        />
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="pa-row-actions" @click.stop>
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                label="Detail"
                                leading-icon="eye"
                                @click.stop="$emit('detail', row)"
                            />
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                label="Ubah"
                                leading-icon="pencil-square"
                                @click.stop="$emit('edit', row)"
                            />
                            <HcisButton
                                v-if="canDeactivate && row.Flag_Aktif === 'Y'"
                                variant="danger-quiet"
                                size="xs"
                                :label="isMobile ? '' : 'Nonaktifkan'"
                                :title="'Nonaktifkan'"
                                leading-icon="slash-circle"
                                @click.stop="$emit('deactivate', row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaPitaNilaiView' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    canDeactivate: { type: Boolean, default: false },
});

defineEmits(['edit', 'deactivate', 'detail', 'add']);

const isMobile = ref(false);
const isTablet = ref(false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    const w = window.innerWidth;
    isMobile.value = w < 768;
    isTablet.value = w >= 768 && w < 1024;
}

onMounted(() => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar, { passive: true });
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});

/* Kategori Grade (FINAL) DISEMBUNYIKAN sejak UAT 8 Okt 2026: siklus metode KURVA menentukan
   grade dari μ + z × σ per grup, sehingga batas angka tetap di sini mudah disalahpahami sebagai
   penentu grade. Datanya TETAP ADA dan tetap disalin saat siklus dibuat — hanya tampilannya yang
   disembunyikan. Ubah ke true untuk menampilkannya lagi. */
const TAMPILKAN_GRADE = false;
const KATEGORI_AWAL = TAMPILKAN_GRADE ? 'GRADE' : 'HAVMAP_KPI';
const barisGrade = (r) => r.Score_Type === 'FINAL' && r.Score_Usage === 'GRADE';

const activeCategory = ref(KATEGORI_AWAL);
const viewMode = ref('table');
const search = ref(props.searchQuery || '');

watch(() => props.searchQuery, (newVal) => {
    if (newVal !== undefined) {
        search.value = newVal || '';
    }
});

function resetFilters() {
    search.value = '';
    activeCategory.value = KATEGORI_AWAL;
}

const categoryChips = computed(() => [
    ...(TAMPILKAN_GRADE ? [{ id: 'GRADE', label: isMobile.value ? 'Grade FINAL' : 'Grade Nilai Akhir (FINAL)', value: 'GRADE', icon: 'award-fill' }] : []),
    { id: 'HAVMAP_KPI', label: isMobile.value ? 'HAV KPI' : 'HAV Map · Capaian KPI', value: 'HAVMAP_KPI', icon: 'graph-up-arrow' },
    { id: 'HAVMAP_KOMP', label: isMobile.value ? 'HAV Komp' : 'HAV Map · Kompetensi', value: 'HAVMAP_KOMP', icon: 'bullseye' },
    { id: 'ALL', label: isMobile.value ? 'Semua' : 'Semua Tipe Penilaian', value: 'ALL', icon: 'collection-fill' },
]);

const tableColumns = computed(() => [
    { key: 'Urutan_Tampil', label: 'Urutan', width: '70px', align: 'center' },
    { key: 'Kode_Range', label: 'Kode Grade', minWidth: isMobile.value ? '100px' : '130px' },
    { key: 'Nama_Range', label: 'Nama Grade / Rentang', minWidth: isMobile.value ? '150px' : '200px' },
    { key: 'Rentang', label: 'Rentang Nilai [Dari - Sampai)', minWidth: isMobile.value ? '180px' : '240px' },
    { key: 'Target_Persen', label: 'Target Kuota', width: '120px', align: 'center' },
    { key: 'Warna', label: 'Warna', width: '110px' },
    { key: 'Flag_Aktif', label: 'Status', width: '100px', align: 'center' },
    { key: 'aksi', label: 'Aksi', align: 'right', minWidth: isMobile.value ? '140px' : '210px' },
]);

const isGradeCategory = computed(() => activeCategory.value === 'GRADE');

/* HAV Map wajib TEPAT tiga pita per sumbu (matriks 3×3), jadi menambah pita tidak pernah sah:
   rentangnya diubah dengan menyunting batas, dan tetangganya ikut bergeser di server. Tab
   "Semua" menambah pita grade, yang kini disembunyikan. */
const bolehTambah = computed(() => {
    if (currentUsageDefault.value === 'HAVMAP') {
        return filteredBands.value.filter((b) => b.Flag_Aktif === 'Y').length < 3;
    }
    return TAMPILKAN_GRADE;
});
const petunjukTanpaTambah = computed(() => currentUsageDefault.value === 'HAVMAP'
    ? 'HAV Map tetap 3 pita. Ubah rentang dengan menyunting batas pita — pita tetangga ikut menyesuaikan.'
    : 'Pilih tab HAV Map untuk menyunting pitanya.');

const currentTypeDefault = computed(() => {
    if (activeCategory.value === 'HAVMAP_KPI') return 'KPI';
    if (activeCategory.value === 'HAVMAP_KOMP') return 'KOMPETENSI';
    return 'FINAL';
});

const currentUsageDefault = computed(() => {
    if (activeCategory.value === 'HAVMAP_KPI' || activeCategory.value === 'HAVMAP_KOMP') return 'HAVMAP';
    return 'GRADE';
});

const filteredBands = computed(() => {
    let list = TAMPILKAN_GRADE ? props.rows : props.rows.filter((r) => !barisGrade(r));

    if (activeCategory.value === 'GRADE') {
        list = list.filter((r) => r.Score_Type === 'FINAL' && r.Score_Usage === 'GRADE');
    } else if (activeCategory.value === 'HAVMAP_KPI') {
        list = list.filter((r) => r.Score_Type === 'KPI' && r.Score_Usage === 'HAVMAP');
    } else if (activeCategory.value === 'HAVMAP_KOMP') {
        list = list.filter((r) => r.Score_Type === 'KOMPETENSI' && r.Score_Usage === 'HAVMAP');
    }

    const q = (search.value || props.searchQuery || '').toLowerCase().trim();
    if (q) {
        list = list.filter(
            (r) =>
                String(r.Kode_Range || '').toLowerCase().includes(q) ||
                String(r.Nama_Range || '').toLowerCase().includes(q) ||
                String(r.Score_Type || '').toLowerCase().includes(q) ||
                String(r.Score_Usage || '').toLowerCase().includes(q),
        );
    }

    return [...list].sort((a, b) => {
        const orderA = Number(a.Urutan_Tampil) || 0;
        const orderB = Number(b.Urutan_Tampil) || 0;
        if (orderA !== orderB) return orderA - orderB;
        return Number(b.Nilai_Dari) - Number(a.Nilai_Dari);
    });
});

const sortedActiveBands = computed(() => {
    return [...filteredBands.value]
        .filter((b) => b.Flag_Aktif === 'Y')
        .sort((a, b) => Number(a.Nilai_Dari) - Number(b.Nilai_Dari));
});

const activeBandsCount = computed(() => filteredBands.value.filter((b) => b.Flag_Aktif === 'Y').length);
const inactiveBandsCount = computed(() => filteredBands.value.filter((b) => b.Flag_Aktif !== 'Y').length);

const totalTargetPersen = computed(() => {
    const list = filteredBands.value;
    const sum = list.reduce((acc, r) => acc + (Number(r.Target_Persen) || 0), 0);
    return Math.round(sum * 100) / 100;
});

const isTarget100 = computed(() => totalTargetPersen.value === 100);

const continuityStatus = computed(() => {
    const bands = [...filteredBands.value]
        .filter((r) => r.Flag_Aktif === 'Y')
        .sort((a, b) => Number(a.Nilai_Dari) - Number(b.Nilai_Dari));

    if (bands.length < 2) {
        return { valid: true, message: 'Jumlah pita < 2' };
    }

    const first = Number(bands[0].Nilai_Dari);
    if (first !== 0) {
        return { valid: false, message: `Pita terbawah tidak mulai dari 0.00 (mulai ${first})` };
    }

    for (let i = 0; i < bands.length - 1; i++) {
        const upper = Number(bands[i].Nilai_Sampai);
        const lowerNext = Number(bands[i + 1].Nilai_Dari);
        if (Math.abs(upper - lowerNext) > 0.001) {
            return {
                valid: false,
                message: `Ada celah antara ${bands[i].Kode_Range} (${upper}) dan ${bands[i + 1].Kode_Range} (${lowerNext})`,
            };
        }
    }

    const last = Number(bands[bands.length - 1].Nilai_Sampai);
    if (last < 999.99) {
        return { valid: false, message: `Pita teratas (${bands[bands.length - 1].Kode_Range}) belum terbuka ke 999.99` };
    }

    return { valid: true, message: 'Semua batas bertemu sempurna (0.00 s/d 999.99)' };
});

const rangeStats = computed(() => [
    {
        id: 'total-bands',
        label: 'Total Rentang Skor',
        value: formatAngka(filteredBands.value.length),
        caption: isMobile.value ? 'Kategori aktif' : 'Rentang nilai terdaftar',
        icon: 'award-fill',
        sentiment: 'brand',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'continuity-status',
        label: 'Kesinambungan Rentang',
        value: continuityStatus.value.valid ? 'Valid (0–∞)' : 'Celah Nilai',
        caption: continuityStatus.value.valid
            ? (isMobile.value ? '0.00 – ∞ Utuh' : '0.00 – ∞ tanpa celah')
            : (isMobile.value ? 'Ada celah skor' : continuityStatus.value.message),
        icon: continuityStatus.value.valid ? 'shield-check' : 'exclamation-triangle-fill',
        sentiment: continuityStatus.value.valid ? 'success' : 'danger',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'target-distribution',
        label: 'Target Distribusi',
        value: isGradeCategory.value ? `${totalTargetPersen.value}%` : '—',
        caption: isGradeCategory.value
            ? (isTarget100.value
                ? (isMobile.value ? 'Pas 100%' : '100% Sesuai')
                : (isMobile.value ? 'Perlu 100%' : `Sisa ${Math.abs(100 - totalTargetPersen.value)}%`))
            : 'Khusus grade FINAL',
        icon: 'pie-chart-fill',
        sentiment: isGradeCategory.value ? (isTarget100.value ? 'success' : 'warning') : 'neutral',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'active-bands',
        label: 'Status Aktif',
        value: `${activeBandsCount.value} / ${filteredBands.value.length}`,
        caption: inactiveBandsCount.value > 0 ? `${inactiveBandsCount.value} Nonaktif` : 'Semua aktif',
        icon: 'patch-check-fill',
        sentiment: inactiveBandsCount.value > 0 ? 'warning' : 'teal',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
]);

function getSpectrumWeight(band) {
    if (band.Target_Persen) return Number(band.Target_Persen);
    const dari = Number(band.Nilai_Dari) || 0;
    const sampai = Math.min(100, Number(band.Nilai_Sampai) || 100);
    const span = Math.max(10, sampai - dari);
    return span;
}

function getBandSentiment(band) {
    if (band.Score_Type === 'FINAL') return 'brand';
    if (band.Score_Type === 'KPI') return 'purple';
    if (band.Score_Type === 'KOMPETENSI') return 'teal';
    return 'brand';
}

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function formatDecimal(val) {
    const num = Number(val);
    return isNaN(num) ? '0.00' : num.toFixed(2);
}

function formatRangeInterval(band) {
    const dari = formatDecimal(band.Nilai_Dari);
    if (Number(band.Nilai_Sampai) >= 999.99) {
        return `≥ ${dari}`;
    }
    return `${dari} - < ${formatDecimal(band.Nilai_Sampai)}`;
}

function formatRangeIntervalShort(band) {
    const dari = formatDecimal(band.Nilai_Dari);
    if (Number(band.Nilai_Sampai) >= 999.99) {
        return `≥ ${dari}`;
    }
    return `${dari}–${formatDecimal(band.Nilai_Sampai)}`;
}
</script>

<style scoped>
.pa-range-hint-tetap {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}
.pa-range-view {
    display: flex;
    flex-direction: column;
    min-width: 0;
    width: 100%;
}

/* Executive Range Stats */
.pa-range-stats {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.6rem !important;
    width: 100% !important;
    box-sizing: border-box !important;
    margin: 0 0 0.5rem 0 !important;
}

.pa-range-stats :deep(.hcis-stat),
.pa-range-stats .hcis-stat {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    min-height: unset !important;
    height: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    padding: 0.65rem 0.85rem !important;
    border-radius: var(--hcis-radius-md, 10px) !important;
}

.pa-range-stats :deep(.hcis-stat__compact-wrap) {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-width: 0;
    width: 100%;
}

.pa-range-stats :deep(.hcis-stat__value) {
    font-size: 1.35rem !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
}

.pa-range-stats :deep(.hcis-stat__label) {
    font-size: 0.72rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.02em !important;
}

.pa-range-stats :deep(.hcis-stat__caption) {
    font-size: 0.68rem !important;
    margin-top: 0.15rem !important;
    color: var(--hcis-slate-500, #64748b) !important;
}

.pa-range-stats :deep(.hcis-stat__icon-box) {
    width: 1.75rem !important;
    height: 1.75rem !important;
}

/* Continuity Alert Banner */
.pa-range-alert-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.55rem 0.8rem;
    border: 1px solid rgba(239, 68, 68, 0.35);
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.75), rgba(255, 255, 255, 0.95));
}

.pa-range-alert-banner__body {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    min-width: 0;
}

.pa-range-alert-banner__content {
    min-width: 0;
}

.pa-range-alert-banner__content strong {
    display: block;
    color: #991b1b;
    font-size: 0.76rem;
    margin-bottom: 0.05rem;
}

.pa-range-alert-banner__content p {
    margin: 0;
    color: #b91c1c;
    font-size: 0.7rem;
    line-height: 1.35;
}

/* Sequential Spectrum Runway */
.pa-range-spectrum-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(99, 102, 241, 0.22);
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(245, 243, 255, 0.45));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-range-spectrum-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-range-spectrum-card__title h4 {
    margin: 0.05rem 0 0;
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-eyebrow {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-range-spectrum-track {
    display: flex;
    width: 100%;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
    background: #e2e8f0;
}

.pa-range-spectrum-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.2rem;
    cursor: pointer;
    transition: opacity 150ms ease, transform 150ms ease;
    border-right: 1.5px solid rgba(255, 255, 255, 0.5);
    color: #ffffff;
    padding: 0 0.35rem;
}

.pa-range-spectrum-seg:last-child {
    border-right: none;
}

.pa-range-spectrum-seg:hover {
    opacity: 0.92;
    filter: brightness(1.08);
}

.pa-range-spectrum-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 800;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-range-spectrum-pct {
    padding: 0.05rem 0.25rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.25);
    font-size: 0.6rem;
    font-weight: 700;
}

.pa-range-filter-bar {
    width: 100%;
}

/* Card Grid */
.pa-range-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 0.75rem;
}

.pa-range-card {
    height: 100%;
}

.pa-range-card.is-inactive {
    opacity: 0.65;
}

.pa-range-card__header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-range-card__identity {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-range-swatch-dot {
    width: 0.85rem;
    height: 0.85rem;
    border-radius: var(--hcis-radius-xs, 4px);
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

.pa-range-code {
    font-size: 0.86rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-strong, #0f172a);
}

.pa-range-type-pill {
    padding: 0.1rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(100, 116, 139, 0.08);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.64rem;
    font-weight: 700;
}

.pa-range-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-range-card__title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.3;
}

/* Range Box */
.pa-range-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-left-width: 4px;
}

.pa-range-box__interval {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-range-box__interval code {
    font-size: 0.82rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-range-box__desc {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

/* Target Box */
.pa-range-target-box {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.4rem 0.55rem;
    border-radius: var(--hcis-radius-md, 7px);
    background: rgba(248, 250, 252, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-range-target-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.66rem;
}

.pa-range-target-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 650;
}

.pa-range-target-value {
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-range-target-bar {
    height: 0.35rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-range-target-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 200ms ease;
}

/* Card Footer */
.pa-range-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    width: 100%;
}

.pa-range-order-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 650;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-range-card__actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Table View */
.pa-range-table-wrap {
    width: 100%;
    min-width: 0;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 14px);
    overflow: hidden;
    background: rgba(255, 255, 255, 0.85);
}

.pa-table-code-cell {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-table-code-cell strong {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-name-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    white-space: normal;
}

.pa-table-name-cell strong {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-name-cell small {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-table-rentang-cell {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    white-space: normal;
}

.pa-table-rentang-cell code {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-rentang-cell small {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.35;
}

.pa-table-color-cell {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-color-preview-pill {
    width: 1rem;
    height: 1rem;
    border-radius: var(--hcis-radius-xs, 4px);
    border: 1px solid rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.pa-table-color-cell code {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-mono-text {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.78rem;
}

.pa-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex-wrap: nowrap;
    white-space: nowrap;
}

/* Skeletons */
.pa-range-loading {
    padding: 0.75rem 0;
}

.pa-range-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 0.75rem;
}

.pa-range-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.pa-range-skel-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-range-skel-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 0.45rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
}

.pa-range-skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-range-skel-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
}

.pa-empty-actions-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

@media (max-width: 640px) {
    .pa-range-stats {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.4rem !important;
    }

    .pa-range-stats :deep(.hcis-stat),
    .pa-range-stats .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .pa-range-stats :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem;
    }

    .pa-range-stats :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
        line-height: 1.15 !important;
        font-weight: 800 !important;
    }

    .pa-range-stats :deep(.hcis-stat__label) {
        font-size: 0.64rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.02em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-range-stats :deep(.hcis-stat__caption) {
        font-size: 0.58rem !important;
        margin-top: 0.05rem !important;
        color: var(--hcis-slate-500, #64748b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-range-stats :deep(.hcis-stat__icon-box) {
        width: 1.35rem !important;
        height: 1.35rem !important;
        border-radius: 5px !important;
    }

    .pa-range-spectrum-card {
        padding: 0.55rem 0.65rem;
    }

    .pa-range-spectrum-track {
        height: 1.85rem;
    }

    .pa-range-spectrum-label {
        font-size: 0.66rem;
    }

    .pa-range-grid,
    .pa-range-skeleton-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .pa-range-card {
        padding: 0.55rem 0.65rem !important;
    }

    .pa-range-card__footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }

    .pa-range-card__actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(65px, 1fr));
        gap: 0.25rem;
        width: 100%;
    }

    .pa-range-card__actions :deep(button) {
        width: 100%;
        justify-content: center;
        height: 1.85rem !important;
        font-size: 0.68rem !important;
    }
}
</style>
