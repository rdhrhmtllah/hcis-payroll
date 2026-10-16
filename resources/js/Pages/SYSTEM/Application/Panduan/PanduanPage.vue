<template>
    <div class="container-fluid px-0 panduan-page">
        <!-- Page Header -->
        <HcisHeader
            title="Manajemen Panduan"
            kicker="System · Aplikasi"
            description="Unggah manual (PDF), pilih halaman yang memakainya, dan bila perlu batasi ke role tertentu. Pengguna membukanya dari Pusat Bantuan (ikon ?) di halaman tersebut."
            icon="book-half"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge v-if="!memuat" :label="`${daftar.length} Panduan`" variant="brand" appearance="glass" size="sm" dot />
            </template>
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton variant="glass" size="md" leading-icon="arrow-clockwise" :loading="memuat" label="Segarkan" @click="muat" />
                    <HcisButton variant="primary" size="md" leading-icon="plus-lg" label="Tambah Panduan" @click="bukaForm()" />
                </div>
            </template>
        </HcisHeader>

        <!-- Executive Stat Group / Metric Pods -->
        <div class="mb-4">
            <HcisStatGroup :stats="statCards" :cols="4" gap="md" appearance="crystal" @item-click="onStatCardClick" />
        </div>

        <!-- Explanatory Guideline Alert Banner -->
        <HcisAlert
            variant="info"
            appearance="subtle"
            class="mb-4"
            title="Siapa melihat panduan apa?"
            description="Panduan tampil di halaman yang dipilih, bagi pengguna yang BISA MEMBUKA halaman itu. Bila role diisi, hanya pemegang salah satu role tersebut yang melihatnya. Tiap halaman boleh punya halaman mulai PDF sendiri."
        />

        <!-- Main Workspace: HcisDataTable & Card Grid -->
        <HcisDataTable
            title="Daftar Panduan"
            subtitle="Panduan nonaktif tidak tampil di halaman mana pun."
            icon="journal-bookmark-fill"
            sentiment="brand"
            variant="glass"
            table-variant="flat"
            :columns="kolom"
            :data="paginatedData"
            :loading="memuat"
            :search="cari"
            search-placeholder="Cari judul, versi, berkas, atau deskripsi panduan…"
            :quick-chips="chipsFilter"
            :active-chip-value="activeChip"
            :show-view-toggle="true"
            :view-mode="viewMode"
            :show-pagination="daftarTersaring.length > 0"
            :total="daftarTersaring.length"
            :page="currentPage"
            :page-size="itemsPerPage"
            :hoverable="true"
            class="mb-5"
            @update:search="onSearchUpdate"
            @chip-click="onChipClick"
            @update:view-mode="onViewModeChange"
            @page-change="onPageChange"
            @size-change="onSizeChange"
        >
            <!-- Header Actions -->
            <template #headerActions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        label="Segarkan"
                        @click="muat"
                    />
                </div>
            </template>

            <!-- Table View: Custom Cells -->
            <template #cell-judul="{ row }">
                <div class="panduan-title-cell py-1">
                    <div class="panduan-jewel-pdf flex-shrink-0" :class="{ 'is-inactive': !row.aktif }">
                        <i class="bi bi-file-earmark-pdf-fill" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold text-slate-800 text-truncate font-heading" :title="row.judul">
                            {{ row.judul }}
                        </div>
                        <div class="panduan-sub text-truncate" :title="row.deskripsi || row.nama_file">
                            {{ row.deskripsi || row.nama_file }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-versi="{ row }">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <span class="panduan-versi-pill">
                        <span class="fw-semibold text-slate-800 font-tabular">{{ formatLabelVersi(row) }}</span>
                    </span>
                    <HcisBadge
                        v-if="row.jumlah_draf"
                        :label="`${row.jumlah_draf} draf`"
                        variant="warning"
                        appearance="subtle"
                        size="xs"
                    />
                </div>
            </template>

            <template #cell-halaman="{ row }">
                <div class="d-flex align-items-center gap-1">
                    <HcisBadge
                        :label="labelSasaran(row)"
                        :variant="row.jumlah_halaman_terpetakan || row.jumlah_role_terpetakan ? 'info' : 'warning'"
                        appearance="subtle"
                        size="xs"
                        :icon="row.jumlah_halaman_terpetakan ? 'diagram-3' : 'exclamation-circle'"
                    />
                </div>
            </template>

            <template #cell-status="{ row }">
                <HcisBadge
                    :label="row.aktif ? 'Aktif' : 'Nonaktif'"
                    :variant="row.aktif ? 'success' : 'neutral'"
                    appearance="glass"
                    size="xs"
                    dot
                />
            </template>

            <template #cell-aksi="{ row }">
                <div class="panduan-actions-wrap">
                    <HcisButton
                        variant="primary-subtle"
                        size="xs"
                        label="Lihat"
                        leading-icon="eye"
                        class="panduan-btn-action"
                        title="Pratinjau berkas PDF"
                        @click.stop="pratinjau(row)"
                    />
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        label="Versi"
                        leading-icon="clock-history"
                        class="panduan-btn-action"
                        title="Kelola riwayat versi dokumen"
                        @click.stop="bukaVersi(row)"
                    />
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        label="Ubah"
                        leading-icon="pencil-square"
                        class="panduan-btn-action"
                        title="Ubah judul, pemetaan halaman, dan role"
                        @click.stop="bukaForm(row)"
                    />
                    <HcisButton
                        :variant="row.aktif ? 'danger-quiet' : 'ghost'"
                        size="xs"
                        :label="row.aktif ? 'Nonaktifkan' : 'Aktifkan'"
                        :leading-icon="row.aktif ? 'slash-circle' : 'check-circle'"
                        class="panduan-btn-action"
                        :title="row.aktif ? 'Nonaktifkan panduan dari aplikasi' : 'Aktifkan panduan'"
                        @click.stop="ubahStatus(row)"
                    />
                </div>
            </template>

            <!-- Grid View: Card Mode -->
            <template #grid>
                <div v-if="memuat" class="panduan-cards-grid">
                    <HcisCard v-for="n in 6" :key="n" variant="glass" size="md" class="panduan-card-item">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <HcisSkeleton width="2.5rem" height="2.5rem" rounded="md" />
                            <div class="flex-grow-1">
                                <HcisSkeleton width="65%" height="1.1rem" rounded="sm" class="mb-1" />
                                <HcisSkeleton width="45%" height="0.8rem" rounded="sm" />
                            </div>
                        </div>
                        <div class="panduan-card-specs mb-3">
                            <div v-for="s in 4" :key="s" class="panduan-spec-item">
                                <HcisSkeleton width="50px" height="0.65rem" rounded="sm" class="mb-1" />
                                <HcisSkeleton width="80px" height="0.85rem" rounded="sm" />
                            </div>
                        </div>
                        <HcisSkeleton width="100%" height="2rem" rounded="md" />
                    </HcisCard>
                </div>

                <div v-else-if="paginatedData.length" class="panduan-cards-grid">
                    <HcisCard
                        v-for="item in paginatedData"
                        :key="item.id"
                        variant="glass"
                        :sentiment="item.aktif ? 'brand' : 'neutral'"
                        size="md"
                        divider
                        hoverable
                        interactive
                        class="panduan-card-item"
                        :class="{ 'is-active-card': item.aktif }"
                        @click="pratinjau(item)"
                    >
                        <template #badge>
                            <HcisBadge
                                :label="item.aktif ? 'Aktif' : 'Nonaktif'"
                                :variant="item.aktif ? 'success' : 'neutral'"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </template>

                        <!-- Card Header -->
                        <div class="d-flex align-items-start gap-2.5 mb-3">
                            <div class="panduan-jewel-pdf flex-shrink-0" :class="{ 'is-inactive': !item.aktif }">
                                <i class="bi bi-file-earmark-pdf-fill" aria-hidden="true" />
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-slate-800 text-truncate fs-6" :title="item.judul">
                                    {{ item.judul }}
                                </div>
                                <div class="panduan-sub text-truncate" :title="item.deskripsi || item.nama_file">
                                    {{ item.deskripsi || item.nama_file }}
                                </div>
                            </div>
                        </div>

                        <!-- Card Specifications Matrix -->
                        <div class="panduan-card-specs mb-3">
                            <div class="panduan-spec-item">
                                <span class="panduan-spec-label">Versi Aktif</span>
                                <div class="d-flex align-items-center gap-1 font-tabular">
                                    <strong class="text-slate-800">{{ formatLabelVersi(item) }}</strong>
                                    <HcisBadge v-if="item.jumlah_draf" :label="`${item.jumlah_draf} draf`" variant="warning" appearance="subtle" size="xs" />
                                </div>
                            </div>
                            <div class="panduan-spec-item">
                                <span class="panduan-spec-label">Cakupan Menu</span>
                                <span class="fw-semibold text-slate-700 text-truncate">{{ labelSasaran(item) }}</span>
                            </div>
                            <div class="panduan-spec-item">
                                <span class="panduan-spec-label">Batasan Akses</span>
                                <span class="text-slate-700 text-truncate">
                                    {{ item.jumlah_role ? `${item.jumlah_role} Role Tertentu` : 'Semua Role Pengguna' }}
                                </span>
                            </div>
                            <div class="panduan-spec-item">
                                <span class="panduan-spec-label">Berkas PDF</span>
                                <span class="text-slate-600 text-truncate font-tabular" :title="item.nama_file">
                                    {{ item.nama_file || 'Tersedia' }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Actions Footer -->
                        <template #footer>
                            <div class="panduan-card-actions" @click.stop>
                                <HcisButton
                                    variant="primary-subtle"
                                    size="xs"
                                    leading-icon="eye"
                                    label="Lihat"
                                    class="flex-fill panduan-btn-action"
                                    @click="pratinjau(item)"
                                />
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="clock-history"
                                    label="Versi"
                                    class="flex-fill panduan-btn-action"
                                    @click="bukaVersi(item)"
                                />
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="pencil-square"
                                    label="Ubah"
                                    class="flex-fill panduan-btn-action"
                                    @click="bukaForm(item)"
                                />
                                <HcisButton
                                    :variant="item.aktif ? 'danger-quiet' : 'ghost'"
                                    size="xs"
                                    :label="item.aktif ? 'Nonaktifkan' : 'Aktifkan'"
                                    :leading-icon="item.aktif ? 'slash-circle' : 'check-circle'"
                                    class="panduan-btn-action"
                                    @click="ubahStatus(item)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </template>

            <!-- Empty State Slot -->
            <template #empty>
                <div class="p-4">
                    <HcisEmptyState
                        icon="book"
                        :title="cari || activeChip !== 'all' ? 'Tidak ada panduan yang cocok' : 'Belum ada panduan'"
                        :description="cari || activeChip !== 'all' ? 'Coba ubah kata kunci pencarian atau bersihkan filter status.' : 'Unggah manual PDF, lalu pilih halaman aplikasi yang memakainya.'"
                        variant="flat"
                        sentiment="neutral"
                    >
                        <template #actions>
                            <HcisButton
                                v-if="cari || activeChip !== 'all'"
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-counterclockwise"
                                label="Reset Filter"
                                @click="resetFilters"
                            />
                            <HcisButton
                                v-else
                                variant="primary"
                                size="md"
                                leading-icon="plus-lg"
                                label="Tambah Panduan Pertama"
                                @click="bukaForm()"
                            />
                        </template>
                    </HcisEmptyState>
                </div>
            </template>
        </HcisDataTable>

        <!-- Form Modal: Tambah & Ubah Panduan -->
        <HcisDialog
            v-model="form.terbuka"
            :title="form.id ? 'Ubah Panduan' : 'Tambah Panduan'"
            :description="`Berkas PDF maksimal ${maksMb} MB, lalu pilih halaman yang memakainya.`"
            icon="book-half"
            sentiment="brand"
            size="lg"
        >
            <div class="panduan-form-grid">
                <!-- Kolom Kiri: Metadata & Berkas -->
                <div class="panduan-form-column">
                    <div class="panduan-form-section">
                        <div class="panduan-section-header">
                            <i class="bi bi-file-earmark-text text-primary" aria-hidden="true" />
                            <span>Informasi Dokumen</span>
                        </div>

                        <div class="panduan-form__judul" :class="{ 'panduan-form__penuh': form.id }">
                            <HcisField
                                id="panduan-judul"
                                v-model="form.judul"
                                label="Judul Panduan"
                                required
                                placeholder="Contoh: Manual VISITOR — Permit Kendaraan"
                                :error="galat.judul"
                            />
                        </div>

                        <div v-if="!form.id" class="panduan-form__versi">
                            <HcisField
                                id="panduan-versi"
                                v-model="form.versi"
                                label="Label Versi"
                                placeholder="v1 - Sep 2026"
                                :error="galat.versi"
                            />
                        </div>

                        <div class="panduan-form__penuh">
                            <HcisField
                                id="panduan-deskripsi"
                                v-model="form.deskripsi"
                                type="textarea"
                                rows="2"
                                label="Deskripsi Singkat"
                                placeholder="Tampil di bawah judul pada Pusat Bantuan..."
                                :error="galat.deskripsi"
                            />
                        </div>

                        <div v-if="!form.id" class="panduan-form__penuh">
                            <label class="panduan-label">Berkas PDF <span class="panduan-wajib">*</span></label>
                            <HcisUpload v-model="form.berkas" accept=".pdf" :limit="1" :max-size="maksMb" layout="bar" />
                            <p v-if="galat.berkas" class="panduan-galat">{{ galat.berkas }}</p>
                        </div>
                        <div v-else class="panduan-form__penuh">
                            <div class="panduan-active-file-box">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="panduan-jewel-pdf-sm">
                                        <i class="bi bi-file-earmark-pdf-fill" />
                                    </div>
                                    <div class="min-w-0">
                                        <span class="fw-semibold text-slate-800 text-xs d-block">Berkas PDF Tersimpan</span>
                                        <span class="panduan-sub font-tabular">{{ form.versi ? `Versi ${form.versi}` : 'Versi Aktif' }}</span>
                                    </div>
                                </div>
                                <p class="panduan-sub mb-2" data-testid="panduan-arah-versi">
                                    Berkas PDF diganti lewat tombol <strong>Versi</strong> di daftar — versi lama tetap tersimpan dan bisa dikembalikan.
                                </p>
                                <div class="d-flex align-items-center gap-2">
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="clock-history"
                                        label="Kelola Versi"
                                        @click="bukaVersiDariEdit()"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pembatasan Role -->
                    <div class="panduan-form-section" data-testid="panduan-role">
                        <div class="panduan-section-header">
                            <i class="bi bi-shield-lock text-primary" aria-hidden="true" />
                            <span>Hak Akses & Role <span class="panduan-sub fw-normal">(Opsional)</span></span>
                        </div>
                        <p class="panduan-sub mb-2">
                            Kosongkan agar panduan tampil untuk semua pengguna yang berhak membuka halaman terkait.
                        </p>
                        <HcisSelect
                            v-model="form.role"
                            multiple
                            :max-collapse-tags="3"
                            filterable
                            clearable
                            :disabled="!opsi.roleTersedia"
                            :placeholder="opsi.roleTersedia ? 'Kosongkan = semua yang bisa membuka halamannya' : 'Belum tersedia (DDL-2026-148)'"
                        >
                            <HcisSelectOption
                                v-for="r in opsi.role"
                                :key="r.value"
                                :value="r.value"
                                :label="r.label"
                                :description="r.kode"
                            />
                        </HcisSelect>
                        <p v-if="galat.role" class="panduan-galat">{{ galat.role }}</p>
                    </div>
                </div>

                <!-- Kolom Kanan: Pemetaan Halaman & Hal Mulai -->
                <div class="panduan-form-column">
                    <div class="panduan-form-section">
                        <div class="panduan-bagian">
                            <div class="panduan-section-header mb-0">
                                <i class="bi bi-diagram-3 text-primary" aria-hidden="true" />
                                <span>Dipakai di halaman <span class="panduan-wajib">*</span></span>
                            </div>
                            <span class="panduan-counter-pill">{{ form.halaman.length }} dipilih</span>
                        </div>
                        <p class="panduan-sub mb-2">
                            Centang halaman menu aplikasi tempat tombol Bantuan (?) akan menampilkan dokumen panduan ini.
                        </p>
                        <PanduanPohonHalaman
                            v-model="halamanDipilih"
                            :halaman="opsi.halaman"
                            :loading="form.memuatSasaran || opsi.memuat"
                        />
                        <p v-if="galat.halaman" class="panduan-galat">{{ galat.halaman }}</p>
                    </div>

                    <!-- Konfigurasi Halaman Mulai PDF -->
                    <div v-if="form.halaman.length" class="panduan-form-section">
                        <div class="panduan-section-header">
                            <i class="bi bi-bookmark-star text-primary" aria-hidden="true" />
                            <span>Halaman Mulai PDF Per Halaman</span>
                        </div>
                        <p class="panduan-sub mb-2">
                            "Hal." = nomor halaman PDF yang otomatis langsung dibuka saat tombol Pusat Bantuan ditekan dari menu tersebut.
                        </p>

                        <div class="panduan-pilihan" data-testid="panduan-pilihan">
                            <div class="panduan-pilihan__kepala">
                                <span>Halaman Aplikasi</span>
                                <span>Buka di hal. PDF</span>
                                <span />
                            </div>
                            <div class="panduan-pilihan__daftar">
                                <div v-for="(h, i) in form.halaman" :key="h.jenis_page" class="panduan-pilihan__baris">
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate text-slate-800 text-xs">
                                            {{ infoHalaman(h.jenis_page).label }}
                                        </div>
                                        <div class="panduan-sub text-truncate">
                                            {{ infoHalaman(h.jenis_page).jejak }}
                                        </div>
                                    </div>
                                    <HcisField
                                        :id="`panduan-mulai-${i}`"
                                        v-model="h.halaman_mulai"
                                        type="text"
                                        numeric-only
                                        prefix="Hal."
                                        placeholder="1"
                                        size="sm"
                                        :aria-label="`Buka ${infoHalaman(h.jenis_page).label} di halaman PDF`"
                                    />
                                    <HcisButton
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="x-lg"
                                        :aria-label="`Lepas ${infoHalaman(h.jenis_page).label}`"
                                        @click="lepas(h.jenis_page)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisButton variant="secondary" size="md" label="Batal" @click="form.terbuka = false" />
                <HcisButton
                    variant="primary"
                    size="md"
                    :loading="form.menyimpan"
                    :disabled="form.memuatSasaran"
                    :label="form.id ? 'Simpan' : 'Unggah'"
                    leading-icon="cloud-arrow-up"
                    @click="simpan"
                />
            </template>
        </HcisDialog>

        <!-- Versi Dialog -->
        <PanduanVersiDialog
            v-model="versi.terbuka"
            :panduan="versi.panduan"
            :maks-mb="maksMb"
            @berubah="muat"
            @lihat="lihatBerkas"
            @ubah-halaman="cekHalamanSetelahVersi"
        />

        <!-- In-App PDF File Viewer -->
        <HcisFileViewer v-if="viewer.terbuka" v-model="viewer.terbuka" :files="viewer.berkas" />
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDataTable,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFileViewer,
    HcisHeader,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatGroup,
    HcisUpload,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import PanduanPohonHalaman from './Components/PanduanPohonHalaman.vue';
import PanduanVersiDialog from './Components/PanduanVersiDialog.vue';

defineOptions({ name: 'PanduanPage' });

const props = defineProps({
    maksKb: { type: Number, default: 30720 },
});

const maksMb = computed(() => Math.floor(props.maksKb / 1024));
const DASAR = '/system/panduan';

const memuat = ref(true);
const daftar = ref([]);
const cari = ref('');
const activeChip = ref('all');
const viewMode = ref(typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table');
const currentPage = ref(1);
const itemsPerPage = ref(10);
const opsi = reactive({ halaman: [], role: [], roleTersedia: false, memuat: false });

const kolom = [
    { key: 'judul', label: 'Panduan' },
    { key: 'versi', label: 'Versi aktif', width: '170px' },
    { key: 'halaman', label: 'Dipakai di', width: '170px' },
    { key: 'status', label: 'Status', width: '110px' },
    { key: 'aksi', label: 'Aksi', width: '310px', align: 'right' },
];

function formatLabelVersi(row) {
    if (!row) return '—';
    const no = row.no_versi ? `v${row.no_versi}` : '';
    const label = (row.versi || '').trim();
    if (!no && !label) return '—';
    if (!no) return label;
    if (!label) return no;

    const noNorm = no.toLowerCase();
    const labelNorm = label.toLowerCase();

    if (labelNorm === noNorm) return no;

    if (
        labelNorm.startsWith(`${noNorm} -`) ||
        labelNorm.startsWith(`${noNorm} –`) ||
        labelNorm.startsWith(`${noNorm}:`) ||
        labelNorm.startsWith(`${noNorm} `)
    ) {
        return label;
    }

    return `${no} · ${label}`;
}

// Metric calculations for Stat Cards
const totalAktif = computed(() => daftar.value.filter((p) => p.aktif).length);
const totalNonaktif = computed(() => daftar.value.filter((p) => !p.aktif).length);
const totalPanduanTerpetakan = computed(() => daftar.value.filter((p) => (p.jumlah_halaman_terpetakan || 0) > 0).length);
const totalHalamanTerpetakan = computed(() =>
    daftar.value.reduce((acc, p) => acc + (p.jumlah_halaman_terpetakan || 0), 0)
);
const totalDraf = computed(() => daftar.value.reduce((acc, p) => acc + (p.jumlah_draf || 0), 0));
const totalPanduanDenganDraf = computed(() => daftar.value.filter((p) => (p.jumlah_draf || 0) > 0).length);
const totalBelumTerpetakan = computed(() => daftar.value.filter((p) => !p.jumlah_halaman_terpetakan).length);

const statCards = computed(() => [
    {
        id: 'total',
        label: 'Total Panduan',
        value: daftar.value.length,
        icon: 'book-half',
        sentiment: 'brand',
        caption: 'Dokumen pedoman sistem',
        interactive: true,
    },
    {
        id: 'aktif',
        label: 'Panduan Aktif',
        value: totalAktif.value,
        icon: 'check-circle-fill',
        sentiment: 'success',
        caption: 'Dapat diakses pengguna',
        interactive: true,
    },
    {
        id: 'terpetakan',
        label: 'Cakupan Menu',
        value: `${totalHalamanTerpetakan.value} Titik`,
        icon: 'diagram-3-fill',
        sentiment: 'info',
        caption: `${totalPanduanTerpetakan.value} panduan terhubung`,
        interactive: true,
    },
    {
        id: 'draf',
        label: 'Draf Versi Baru',
        value: totalDraf.value,
        icon: 'clock-history',
        sentiment: totalDraf.value > 0 ? 'warning' : 'neutral',
        caption: totalDraf.value > 0 ? 'Menunggu penerbitan' : 'Semua versi terbit',
        interactive: true,
    },
]);

const chipsFilter = computed(() => [
    {
        id: 'all',
        value: 'all',
        label: 'Semua',
        icon: 'grid-fill',
        badge: daftar.value.length,
    },
    {
        id: 'aktif',
        value: 'aktif',
        label: 'Aktif',
        icon: 'check-circle-fill',
        color: 'success',
        badge: totalAktif.value,
    },
    {
        id: 'nonaktif',
        value: 'nonaktif',
        label: 'Nonaktif',
        icon: 'slash-circle',
        color: 'neutral',
        badge: totalNonaktif.value,
    },
    {
        id: 'draf',
        value: 'draf',
        label: 'Ada Draf',
        icon: 'clock-history',
        color: 'warning',
        badge: totalPanduanDenganDraf.value,
        ping: totalDraf.value > 0,
    },
    {
        id: 'belum_terpetakan',
        value: 'belum_terpetakan',
        label: 'Belum Dipakai',
        icon: 'exclamation-circle',
        color: 'danger',
        badge: totalBelumTerpetakan.value,
    },
]);

const daftarTersaring = computed(() => {
    let list = daftar.value;
    const q = cari.value.trim().toLowerCase();
    if (q) {
        list = list.filter((p) =>
            `${p.judul} ${p.deskripsi || ''} ${p.versi || ''} ${p.nama_file || ''}`.toLowerCase().includes(q)
        );
    }
    if (activeChip.value === 'aktif') {
        list = list.filter((p) => p.aktif);
    } else if (activeChip.value === 'nonaktif') {
        list = list.filter((p) => !p.aktif);
    } else if (activeChip.value === 'draf') {
        list = list.filter((p) => (p.jumlah_draf || 0) > 0);
    } else if (activeChip.value === 'belum_terpetakan') {
        list = list.filter((p) => !p.jumlah_halaman_terpetakan);
    }
    return list;
});

const paginatedData = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return daftarTersaring.value.slice(start, start + itemsPerPage.value);
});

function onStatCardClick(item) {
    if (item.id === 'total') activeChip.value = 'all';
    else if (item.id === 'aktif') activeChip.value = 'aktif';
    else if (item.id === 'terpetakan') activeChip.value = 'terpetakan';
    else if (item.id === 'draf') activeChip.value = 'draf';
    currentPage.value = 1;
    muat();
}

let cariTimer = null;
function onSearchUpdate(val) {
    cari.value = val;
    currentPage.value = 1;
    clearTimeout(cariTimer);
    cariTimer = setTimeout(() => {
        muat();
    }, 350);
}

function onChipClick(chip) {
    activeChip.value = typeof chip === 'object' && chip !== null ? chip.value || chip.id : chip;
    currentPage.value = 1;
    muat();
}

function onViewModeChange(mode) {
    viewMode.value = mode;
}

function onPageChange(p) {
    currentPage.value = p;
}

function onSizeChange(sz) {
    itemsPerPage.value = sz;
    currentPage.value = 1;
}

function resetFilters() {
    cari.value = '';
    activeChip.value = 'all';
    currentPage.value = 1;
    muat();
}

function pesanGalat(e, cadangan) {
    return e?.response?.data?.message || cadangan;
}

async function muat() {
    memuat.value = true;
    try {
        let endpoint = `${DASAR}/data`;
        const qp = new URLSearchParams();
        if (cari.value.trim()) qp.set('q', cari.value.trim());
        if (activeChip.value === 'aktif' || activeChip.value === 'nonaktif') {
            qp.set('status', activeChip.value);
        } else if (['draf', 'terpetakan', 'belum_terpetakan'].includes(activeChip.value)) {
            qp.set('kondisi', activeChip.value);
        }
        const qs = qp.toString();
        if (qs) endpoint += `?${qs}`;

        const { data } = await axios.get(endpoint);
        daftar.value = data?.result || [];
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Daftar panduan gagal dimuat.'));
    } finally {
        memuat.value = false;
    }
}

async function muatOpsi() {
    if (opsi.halaman.length || opsi.memuat) return;
    opsi.memuat = true;
    try {
        const { data } = await axios.get(`${DASAR}/opsi`);
        opsi.halaman = data?.result?.halaman || [];
        opsi.role = data?.result?.role || [];
        opsi.roleTersedia = !!data?.result?.role_tersedia;
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Daftar halaman dan role gagal dimuat.'));
    } finally {
        opsi.memuat = false;
    }
}

const petaHalaman = computed(() => new Map(opsi.halaman.map((h) => [h.value, h])));

function infoHalaman(jenisPage) {
    const h = petaHalaman.value.get(jenisPage);
    if (!h) return { label: jenisPage, jejak: 'Tidak ada di daftar menu' };
    const modul = h.modul && h.modul !== '-' ? h.modul : 'Tanpa modul';
    return { label: h.label, jejak: h.fitur ? `${modul} · ${h.fitur}` : modul };
}

function labelSasaran(row) {
    if (!row.jumlah_halaman_terpetakan) return 'Belum dipakai';
    const halaman = `${row.jumlah_halaman_terpetakan} halaman`;
    return row.jumlah_role ? `${halaman} · ${row.jumlah_role} role` : halaman;
}

// ---------------- Form panduan ----------------
const form = reactive({
    terbuka: false,
    menyimpan: false,
    memuatSasaran: false,
    sasaranTermuat: false,
    id: null,
    judul: '',
    versi: '',
    deskripsi: '',
    berkas: [],
    halaman: [], // [{ jenis_page, halaman_mulai }]
    role: [], // [Id_Role] — pembatas opsional untuk seluruh panduan
});
const galat = reactive({ judul: '', versi: '', deskripsi: '', berkas: '', halaman: '', role: '' });

/** Pohon hanya mengurus pilihan; halaman mulai tetap di baris masing-masing. */
const halamanDipilih = computed({
    get: () => form.halaman.map((h) => h.jenis_page),
    set: (daftarBaru) => {
        const lama = new Map(form.halaman.map((h) => [h.jenis_page, h]));
        form.halaman = daftarBaru.map((jp) => lama.get(jp) || { jenis_page: jp, halaman_mulai: '' });
    },
});

function lepas(jenisPage) {
    form.halaman = form.halaman.filter((h) => h.jenis_page !== jenisPage);
}

async function bukaForm(row = null) {
    Object.assign(form, {
        terbuka: true,
        menyimpan: false,
        memuatSasaran: !!row,
        sasaranTermuat: !row,
        id: row?.id || null,
        judul: row?.judul || '',
        versi: row?.versi || '',
        deskripsi: row?.deskripsi || '',
        berkas: [],
        halaman: [],
        role: [],
    });
    Object.keys(galat).forEach((k) => (galat[k] = ''));
    muatOpsi();

    if (!row) return;
    try {
        const { data } = await axios.get(`${DASAR}/${row.id}/sasaran`);
        if (form.id !== row.id) return;
        const hasil = data?.result || {};
        form.halaman = (hasil.halaman || []).map((h) => ({
            jenis_page: h.jenis_page,
            halaman_mulai: h.halaman_mulai ? String(h.halaman_mulai) : '',
        }));
        form.role = (hasil.role || []).map((r) => r.id_role);
        form.sasaranTermuat = true;
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Halaman & role panduan ini gagal dimuat; keduanya tidak akan diubah.'));
    } finally {
        form.memuatSasaran = false;
    }
}

function bukaVersiDariEdit() {
    const row = daftar.value.find((p) => p.id === form.id);
    form.terbuka = false;
    if (row) bukaVersi(row);
}

async function simpan() {
    Object.keys(galat).forEach((k) => (galat[k] = ''));
    const berkas = form.berkas?.[0]?.raw || null;
    if (!form.id && !berkas) galat.berkas = 'Berkas PDF wajib diunggah.';
    if (form.sasaranTermuat && !form.halaman.length) galat.halaman = 'Pilih minimal satu halaman yang memakai panduan ini.';
    if (galat.berkas || galat.halaman) return;

    const isi = new FormData();
    isi.append('judul', form.judul || '');
    if (!form.id) isi.append('versi', form.versi || '');
    isi.append('deskripsi', form.deskripsi || '');
    if (berkas) isi.append('berkas', berkas);
    // Bila halaman & role lama gagal dimuat, jangan menimpanya dengan daftar kosong.
    if (form.sasaranTermuat) {
        isi.append('atur_sasaran', '1');
        form.halaman.forEach((h, i) => {
            isi.append(`halaman[${i}][jenis_page]`, h.jenis_page);
            if (h.halaman_mulai) isi.append(`halaman[${i}][halaman_mulai]`, h.halaman_mulai);
        });
        form.role.forEach((idRole, i) => isi.append(`role[${i}]`, idRole));
    }

    form.menyimpan = true;
    try {
        await axios.post(form.id ? `${DASAR}/${form.id}` : DASAR, isi);
        hcisMessage.success(form.id ? 'Panduan diperbarui.' : 'Panduan ditambahkan.');
        form.terbuka = false;
        await muat();
    } catch (e) {
        const errors = e?.response?.data?.errors || {};
        ['judul', 'versi', 'deskripsi', 'berkas'].forEach((k) => (galat[k] = errors[k]?.[0] || ''));
        for (const k of ['halaman', 'role']) {
            const kunci = Object.keys(errors).find((x) => x === k || x.startsWith(`${k}.`));
            if (kunci) galat[k] = errors[kunci][0];
        }
        if (!Object.keys(errors).length) {
            const pesan = pesanGalat(e, 'Panduan gagal disimpan.');
            if (e?.response?.status === 422) galat.halaman = pesan;
            else hcisMessage.error(pesan);
        }
    } finally {
        form.menyimpan = false;
    }
}

async function ubahStatus(row) {
    const aktif = !row.aktif;
    if (!aktif) {
        const ok = await hcisConfirm({
            title: 'Nonaktifkan panduan?',
            description: `"${row.judul}" tidak akan tampil di halaman mana pun sampai diaktifkan lagi.`,
            confirmText: 'Nonaktifkan',
            sentiment: 'danger',
        }).catch(() => false);
        if (!ok) return;
    }
    try {
        await axios.patch(`${DASAR}/${row.id}/status`, { aktif });
        await muat();
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Status gagal diubah.'));
    }
}

// ---------------- Pratinjau ----------------
const viewer = reactive({ terbuka: false, berkas: [] });

function pratinjau(row) {
    lihatBerkas({
        url: `${DASAR}/${row.id}/berkas`,
        name: row.nama_file || `${row.judul}.pdf`,
        title: row.judul,
        ext: 'pdf',
    });
}

function lihatBerkas(berkas) {
    viewer.berkas = [berkas];
    viewer.terbuka = true;
}

// ---------------- Versi berkas ----------------
const versi = reactive({ terbuka: false, panduan: null });

function bukaVersi(row) {
    versi.panduan = row;
    versi.terbuka = true;
}

/** Setelah versi aktif berganti: buka form Ubah untuk mengecek halaman mulai PDF. */
function cekHalamanSetelahVersi() {
    const row = versi.panduan;
    versi.terbuka = false;
    if (row) bukaForm(row);
}

onMounted(muat);
</script>

<style scoped>
.panduan-page {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.panduan-sub {
    font-size: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

.panduan-label {
    display: block;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.panduan-galat {
    margin: 0.35rem 0 0;
    font-size: 0.8rem;
    color: var(--hcis-danger-600, #dc2626);
}

.panduan-wajib {
    color: var(--hcis-danger-600, #dc2626);
}

/* Jewel PDF Icon Box */
.panduan-jewel-pdf {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.9) 0%, rgba(224, 231, 255, 0.7) 100%);
    border: 1px solid rgba(99, 102, 241, 0.22);
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 1.15rem;
    box-shadow: inset 0 1px 0 #ffffff;
    transition: transform 0.2s ease;
}

.panduan-jewel-pdf.is-inactive {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    border-color: #cbd5e1;
    color: #94a3b8;
}

.panduan-jewel-pdf-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.9rem;
}

.panduan-title-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.panduan-actions-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    padding: 0.15rem 0.3rem;
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.panduan-btn-action {
    white-space: nowrap !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    height: 1.85rem !important;
    padding: 0 0.55rem !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.panduan-versi-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.55rem;
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 6px;
    font-size: 0.78rem;
}

/* Grid Cards View */
.panduan-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    padding: 0.25rem;
}

.panduan-card-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.panduan-card-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -4px rgba(99, 102, 241, 0.1);
}

.panduan-card-item.is-active-card {
    border-left: 4.5px solid var(--hcis-success-500, #10b981) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.14);
}

.panduan-card-specs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem 0.75rem;
    padding: 0.65rem 0.8rem;
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid var(--hcis-slate-100, #f1f5f9);
}

.panduan-spec-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.panduan-spec-label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-text-muted, #64748b);
    margin-bottom: 0.15rem;
}

.panduan-card-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
}

/* Modal Form Layout: 2 Columns on Desktop */
.panduan-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
    gap: 1.25rem;
    align-items: start;
}

.panduan-form-column {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-width: 0;
}

.panduan-form-section {
    padding: 1rem;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.panduan-section-header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    margin-bottom: 0.75rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid var(--hcis-slate-100, #f1f5f9);
}

.panduan-bagian {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.panduan-counter-pill {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-brand-700, #4338ca);
}

.panduan-active-file-box {
    padding: 0.75rem;
    border-radius: 10px;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.panduan-pilihan {
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 10px;
    overflow: hidden;
}

.panduan-pilihan__daftar {
    max-height: 12rem;
    overflow-y: auto;
}

.panduan-pilihan__kepala,
.panduan-pilihan__baris {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 7.5rem 2rem;
    gap: 0.6rem;
    align-items: center;
    padding: 0.45rem 0.65rem;
}

.panduan-pilihan__baris > * {
    min-width: 0;
}

.panduan-pilihan__kepala {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-text-muted, #64748b);
    background: var(--hcis-slate-50, #f8fafc);
    border-bottom: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.panduan-pilihan__baris + .panduan-pilihan__baris {
    border-top: 1px solid var(--hcis-slate-100, #f1f5f9);
}

@media (max-width: 900px) {
    .panduan-form-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 768px) {
    .panduan-cards-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .panduan-actions-wrap {
        justify-content: flex-start;
    }
}
</style>
