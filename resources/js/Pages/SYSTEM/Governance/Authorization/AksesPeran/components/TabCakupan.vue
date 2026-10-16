<template>
    <div class="tc-layout">
        <!-- ══ JIKA TABEL / FITUR BELUM DIAKTIFKAN DI DATABASE ══ -->
        <HcisResultState
            v-if="dimuat && !daftar.tersedia"
            status="info"
            title="Template Cakupan Belum Tersedia"
            description="Fitur template cakupan otorisasi aktif setelah DDL-2026-184 dijalankan pada database ini."
        />

        <template v-else>
            <!-- ══ 1. STATISTIK / RINGKASAN METRIK TEMPLATE ══ -->
            <div class="tc-stat-row">
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="brand"
                    icon="funnel-fill"
                    label="Total Template Aktif"
                    :value="totalTemplate ? `${totalTemplate} Template` : '0 Template'"
                    caption="Paket kriteria cakupan tersimpan"
                    class="cursor-pointer"
                    @click="onChipClick({ value: 'semua' })"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="primary"
                    icon="people-fill"
                    label="Dipakai Penugasan"
                    :value="`${totalPemakai} Penugasan`"
                    caption="Total penugasan peran memakai template"
                    class="cursor-pointer"
                    @click="onChipClick({ value: 'dipakai' })"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    sentiment="teal"
                    icon="globe2"
                    label="Lintas Modul"
                    :value="`${totalLintasModul} Template`"
                    caption="Dapat dipakai di semua modul"
                    class="cursor-pointer"
                    @click="onChipClick({ value: 'lintas' })"
                />
                <HcisStatCard
                    layout="compact"
                    appearance="crystal"
                    :sentiment="daftar.bisa_buat ? 'success' : 'neutral'"
                    icon="shield-check"
                    label="Hak Kelola Template"
                    :value="daftar.bisa_buat ? 'Diizinkan' : 'Terbatas'"
                    :caption="
                        daftar.bisa_buat ? 'Dapat membuat & mengubah template' : 'Khusus pengelola tanpa batas cakupan'
                    "
                />
            </div>

            <!-- ══ 2. BANNER PANDUAN TATA KELOLA CAKUPAN ══ -->
            <div class="tc-banner-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="ap-kicker">Otorisasi Berbasis Cakupan</span>
                            <HcisBadge
                                :label="daftar.bisa_buat ? 'Akses Penuh Kelola' : 'Hanya Lihat'"
                                :variant="daftar.bisa_buat ? 'success' : 'neutral'"
                                appearance="subtle"
                                size="xs"
                            />
                            <HcisBadge
                                :label="`${totalTemplate} Template Tersedia`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Katalog Template Cakupan Organisasi</h5>
                        <p class="ap-kecil text-muted mb-0">
                            Template cakupan adalah bundel kriteria karyawan (Divisi, Departemen, Lokasi, Level,
                            Jabatan) yang dapat dipakai berulang saat menugaskan peran. Karyawan tercakup bila memenuhi
                            <strong class="text-dark">SEMUA</strong> kategori kriteria terpilih (logika
                            <em class="text-brand fw-semibold">DAN</em>); di dalam satu kategori yang sama cukup
                            memenuhi <strong class="text-dark">SALAH SATU</strong> nilai (logika
                            <em class="text-brand fw-semibold">ATAU</em>).
                        </p>
                    </div>

                    <div v-if="daftar.bisa_buat" class="flex-shrink-0">
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="plus-lg"
                            label="Buat Template Baru"
                            @click="bukaForm(null)"
                        />
                    </div>
                </div>
            </div>

            <!-- ══ 3. OFFICIAL FILTER BAR ══ -->
            <div class="tc-filterbar-wrap">
                <HcisFilterBar
                    :search="cari"
                    search-placeholder="Cari nama template, keterangan, atau syarat kriteria…"
                    :quick-chips="quickChips"
                    :active-chip-value="chipAktif"
                    :filters="daftarFilterField"
                    :filter-values="filterValues"
                    collapsible
                    variant="flat"
                    size="sm"
                    sentiment="brand"
                    class="tc-filterbar"
                    @update:search="(v) => (cari = v)"
                    @chip-click="onChipClick"
                    @filter-change="onFilterChange"
                    @reset="resetSemuaFilter"
                />
            </div>

            <!-- ══ 4. TABEL DATA TEMPLATE ══ -->
            <div class="tc-tabel-card">
                <!-- Loading State Skeleton -->
                <div v-if="memuat && !daftar.data.length" class="p-3">
                    <HcisSkeleton :lines="6" />
                </div>

                <!-- Empty State -->
                <HcisEmptyState
                    v-else-if="!barisTersaring.length"
                    icon="funnel"
                    title="Tidak Ada Template Cakupan Ditemukan"
                    :description="
                        cari || chipAktif !== 'semua' || filterModul
                            ? 'Tidak ada template yang cocok dengan saringan atau kata kunci pencarian yang dimasukkan.'
                            : daftar.bisa_buat
                              ? 'Belum ada template cakupan yang terdaftar. Buat template pertama Anda untuk cakupan yang sering dipakai, misalnya Divisi Operasional di Regional.'
                              : 'Belum ada template cakupan yang terdaftar pada sistem.'
                    "
                    size="sm"
                    class="py-5"
                >
                    <template #actions>
                        <HcisButton
                            v-if="cari || chipAktif !== 'semua' || filterModul"
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-counterclockwise"
                            label="Reset Semua Saringan"
                            @click="resetSemuaFilter"
                        />
                        <HcisButton
                            v-else-if="daftar.bisa_buat"
                            variant="primary"
                            size="xs"
                            leading-icon="plus-lg"
                            label="Buat Template Baru"
                            @click="bukaForm(null)"
                        />
                    </template>
                </HcisEmptyState>

                <!-- Table Content -->
                <template v-else>
                    <HcisTable
                        :columns="kolom"
                        :rows="barisTampil"
                        row-key="id"
                        responsive="auto"
                        variant="flat"
                        sentiment="brand"
                        size="sm"
                        dense
                        hoverable
                    >
                        <!-- Kolom 1: Nama & Keterangan Template -->
                        <template #cell-nama="{ row }">
                            <div class="d-flex align-items-center gap-2.5 py-1.5">
                                <div
                                    class="tc-icon-box flex-shrink-0"
                                    :class="row.modul ? 'tc-icon-box--brand' : 'tc-icon-box--teal'"
                                >
                                    <HcisIcon :name="row.modul ? 'folder2-open' : 'globe2'" size="xs" />
                                </div>
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <span class="fw-semibold text-dark tc-nama-template" :title="row.nama">
                                            {{ row.nama }}
                                        </span>
                                        <HcisBadge
                                            v-if="row.pemakai > 0"
                                            :label="`${row.pemakai} Penugasan`"
                                            variant="brand"
                                            appearance="subtle"
                                            size="2xs"
                                        />
                                    </div>
                                    <div
                                        v-if="row.keterangan"
                                        class="ap-kecil text-muted text-truncate mt-0.5"
                                        :title="row.keterangan"
                                    >
                                        {{ row.keterangan }}
                                    </div>
                                    <div v-else class="ap-kecil text-muted fst-italic mt-0.5">Tanpa keterangan</div>
                                </div>
                            </div>
                        </template>

                        <!-- Kolom 2: Lingkup Modul -->
                        <template #cell-modul="{ row }">
                            <HcisBadge
                                :label="row.modul || 'Lintas Modul'"
                                :variant="row.modul ? 'brand' : 'teal'"
                                appearance="subtle"
                                size="xs"
                            />
                        </template>

                        <!-- Kolom 3: Rincian Syarat Interaktif -->
                        <template #cell-syarat="{ row }">
                            <div class="py-1">
                                <!-- Pill Badge Kategori Syarat -->
                                <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                                    <template v-for="(grup, jenis) in syaratPerGrup(row)" :key="jenis">
                                        <div class="tc-pill-syarat">
                                            <span class="tc-pill-syarat__label">{{ formatJenis(jenis) }}:</span>
                                            <span class="tc-pill-syarat__val">
                                                {{ grup.slice(0, 2).join(', ')
                                                }}{{ grup.length > 2 ? ` (+${grup.length - 2})` : '' }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                                <div class="tc-syarat-formula text-muted" :title="teksSyarat(row)">
                                    <span class="tc-formula-tag">Logika:</span> {{ teksSyarat(row) }}
                                </div>
                            </div>
                        </template>

                        <!-- Kolom 4: Penggunaan Penugasan -->
                        <template #cell-pemakai="{ row }">
                            <div class="d-flex align-items-center gap-1.5 py-1">
                                <HcisBadge
                                    :label="`${row.pemakai} Pemegang`"
                                    :variant="row.pemakai > 0 ? 'brand' : 'neutral'"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                        </template>

                        <!-- Kolom 5: Aksi Kelola -->
                        <template #cell-aksi="{ row }">
                            <div v-if="daftar.bisa_buat" class="d-flex gap-1 justify-content-end py-1">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="pencil"
                                    label="Ubah"
                                    @click="bukaForm(row)"
                                />
                                <HcisButton
                                    variant="danger"
                                    size="xs"
                                    leading-icon="slash-circle"
                                    label="Nonaktifkan"
                                    @click="bukaNonaktif(row)"
                                />
                            </div>
                            <span v-else class="text-muted ap-kecil fst-italic">Hanya Lihat</span>
                        </template>

                        <!-- Footer: Pagination & Ringkasan -->
                        <template #footer>
                            <div class="tc-table-footer">
                                <span class="text-muted ap-kecil">
                                    Menampilkan <strong>{{ barisTampil.length }}</strong> dari
                                    <strong>{{ barisTersaring.length }}</strong> template cakupan.
                                </span>
                                <HcisPagination
                                    v-model:current-page="halamanAktif"
                                    v-model:page-size="barisPerHalaman"
                                    :total="barisTersaring.length"
                                    :page-sizes="[10, 20, 50]"
                                    layout="total, sizes, prev, pager, next"
                                    size="sm"
                                    background
                                />
                            </div>
                        </template>
                    </HcisTable>
                </template>
            </div>
        </template>

        <!-- ══ 5. MODAL DIALOG BUAT / UBAH TEMPLATE CAKUPAN ══ -->
        <HcisDialog
            v-model="form.buka"
            :title="form.id ? 'Ubah Template Cakupan' : 'Buat Template Cakupan Baru'"
            icon="funnel"
            size="xl"
            header-divider
        >
            <div class="tc-form-body">
                <!-- Info Alert Jika Template Sedang Dipakai -->
                <HcisAlert
                    v-if="form.id && form.pemakai"
                    variant="warning"
                    appearance="subtle"
                    class="mb-3"
                    title="Perhatian: Template Sedang Aktif Digunakan"
                    :description="`Template ini saat ini digunakan oleh ${form.pemakai} penugasan peran. Perubahan kriteria di bawah akan langsung berdampak kepada seluruh pemegang peran terkait.`"
                />

                <!-- Section 1: Identitas Template -->
                <div class="tc-form-section mb-3">
                    <span class="ap-kicker mb-1">1. IDENTITAS TEMPLATE</span>
                    <div class="row g-2.5">
                        <div class="col-12 col-md-5">
                            <HcisField
                                v-model="form.nama"
                                label="Nama Template"
                                placeholder="Contoh: Divisi Keuangan Kantor Pusat"
                                size="sm"
                                required
                            />
                        </div>
                        <div class="col-12 col-md-7">
                            <HcisField
                                v-model="form.keterangan"
                                label="Keterangan Penggunaan (Opsional)"
                                placeholder="Jelaskan tujuan atau operasional cakupan ini"
                                size="sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 2: Kriteria Syarat Cakupan -->
                <div class="tc-form-section">
                    <div
                        class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2.5 pb-2 border-bottom border-light-subtle"
                    >
                        <div>
                            <span class="ap-kicker mb-0">2. KRITERIA SYARAT CAKUPAN ORGANISASI</span>
                            <div class="ap-kecil text-muted">
                                Karyawan yang tercakup harus memenuhi <strong>semua</strong> kategori yang dipilih
                                (logika <code>DAN</code>). Di dalam tiap kategori, karyawan cukup cocok dengan
                                <strong>salah satu</strong> nilai (logika <code>ATAU</code>).
                            </div>
                        </div>
                        <div class="tc-phl-toggle">
                            <HcisCheckbox v-model="form.phl" label="Khusus Pegawai Harian Lepas (PHL)" />
                        </div>
                    </div>

                    <!-- Komponen Selector Nilai Cakupan Resmi -->
                    <div class="tc-isian-wrapper mb-3">
                        <IsianNilaiCakupan
                            v-model="form.nilai"
                            :opsi="opsiNilai"
                            :label-karyawan="form.labelKaryawan"
                        />
                    </div>

                    <!-- Ringkasan Pratinjau Kriteria Terpilih -->
                    <div class="tc-pratinjau-kriteria">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1.5">
                            <div class="d-flex align-items-center gap-1.5">
                                <HcisIcon name="info-circle" size="xs" class="text-brand" />
                                <span class="fw-bold small text-dark">Pratinjau Rumus Logika Cakupan:</span>
                            </div>
                            <HcisBadge
                                :label="`${syaratForm.length} Nilai Terpilih`"
                                :variant="syaratForm.length ? 'brand' : 'neutral'"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>

                        <div v-if="syaratForm.length" class="tc-formula-box">
                            <span class="tc-formula-tag me-1">Formula Evaluasi:</span>
                            <span>{{ formulaDraf }}</span>
                        </div>
                        <div v-else class="text-muted small fst-italic p-2 bg-white rounded border border-light-subtle">
                            Belum ada kriteria yang dipilih. Pilih minimal satu kriteria (Divisi, Departemen, Lokasi,
                            Level, Jabatan, Karyawan, atau opsi PHL) untuk dapat menyimpan template.
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100">
                    <span class="ap-kecil text-muted">
                        {{
                            form.id
                                ? 'Perubahan akan dicatat dalam jejak audit kepatuhan sistem.'
                                : 'Template baru akan langsung tersedia di pemilih cakupan peran.'
                        }}
                    </span>
                    <div class="d-flex gap-2">
                        <HcisButton variant="secondary" size="sm" label="Batal" @click="form.buka = false" />
                        <HcisButton
                            variant="primary"
                            size="sm"
                            :leading-icon="form.id ? 'check-lg' : 'plus-lg'"
                            :label="form.id ? 'Simpan Perubahan' : 'Buat Template'"
                            :loading="menyimpan"
                            :disabled="form.nama.trim().length < 3 || !syaratForm.length"
                            @click="simpan"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- ══ 6. DIALOG ALASAN NONAKTIFKAN TEMPLATE ══ -->
        <DialogAlasan
            v-model="nonaktif.buka"
            :judul="`Nonaktifkan Template ${nonaktif.nama}`"
            :keterangan="nonaktif.keterangan"
            label="Nonaktifkan"
            :aksi="nonaktif.aksi"
            @selesai="muat"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisResultState,
    HcisSkeleton,
    HcisStatCard,
    HcisTable,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, pesanGalat } from '../api/aksesPeranApi.js';
import DialogAlasan from './DialogAlasan.vue';
import IsianNilaiCakupan from './IsianNilaiCakupan.vue';

const NILAI = ['DIVISI', 'DEPARTEMEN', 'LOKASI', 'LEVEL', 'JABATAN', 'KARYAWAN'];
const nilaiKosong = () => Object.fromEntries(NILAI.map((k) => [k, []]));

// ── State Utama ──
const daftar = reactive({ tersedia: true, bisa_buat: false, data: [] });
const dimuat = ref(false);
const memuat = ref(false);
const cari = ref('');
const chipAktif = ref('semua');
const filterModul = ref('');
const opsiNilai = ref({});

// Paginasi
const halamanAktif = ref(1);
const barisPerHalaman = ref(20);

// ── Metrik KPI / StatCard ──
const totalTemplate = computed(() => daftar.data.length);
const totalPemakai = computed(() => daftar.data.reduce((acc, t) => acc + (t.pemakai || 0), 0));
const totalLintasModul = computed(() => daftar.data.filter((t) => !t.modul).length);

// ── Konfigurasi Kolom Tabel ──
const kolom = [
    { key: 'nama', label: 'Nama & Keterangan Template', width: '30%' },
    { key: 'modul', label: 'Lingkup Modul', width: '130px' },
    { key: 'syarat', label: 'Kriteria Syarat Cakupan Organisasi' },
    { key: 'pemakai', label: 'Penggunaan', width: '120px' },
    { key: 'aksi', label: '', width: '160px' },
];

// ── Quick Chips untuk FilterBar ──
const quickChips = computed(() => [
    {
        id: 'semua',
        value: 'semua',
        label: 'Semua Template',
        icon: 'funnel-fill',
        badge: totalTemplate.value,
    },
    {
        id: 'dipakai',
        value: 'dipakai',
        label: 'Sedang Digunakan',
        icon: 'people-fill',
        color: 'primary',
        badge: daftar.data.filter((t) => t.pemakai > 0).length,
    },
    {
        id: 'lintas',
        value: 'lintas',
        label: 'Lintas Modul',
        icon: 'globe2',
        color: 'teal',
        badge: totalLintasModul.value,
    },
]);

// ── Opsi Filter Modul ──
const opsiModul = computed(() => {
    const list = Array.from(new Set(daftar.data.map((t) => t.modul).filter(Boolean)));
    const res = [{ label: 'Lintas Modul (Global)', value: 'GLOBAL' }];
    list.forEach((m) => res.push({ label: m, value: m }));
    return res;
});

const daftarFilterField = computed(() => [
    {
        key: 'modul',
        label: 'Lingkup Modul',
        type: 'select',
        placeholder: 'Semua lingkup modul',
        clearable: true,
        options: opsiModul.value,
        col: 6,
    },
]);

const filterValues = computed(() => ({
    modul: filterModul.value,
}));

function onChipClick(chip) {
    chipAktif.value = chip.value;
    halamanAktif.value = 1;
}

function onFilterChange({ key, value }) {
    if (key === 'modul') filterModul.value = value || '';
    halamanAktif.value = 1;
}

function resetSemuaFilter() {
    cari.value = '';
    chipAktif.value = 'semua';
    filterModul.value = '';
    halamanAktif.value = 1;
}

// ── Penyaringan Baris Data ──
const barisTersaring = computed(() => {
    let list = daftar.data;

    // 1. Quick Chip
    if (chipAktif.value === 'dipakai') {
        list = list.filter((t) => t.pemakai > 0);
    } else if (chipAktif.value === 'lintas') {
        list = list.filter((t) => !t.modul);
    }

    // 2. Modul
    if (filterModul.value === 'GLOBAL') {
        list = list.filter((t) => !t.modul);
    } else if (filterModul.value) {
        list = list.filter((t) => t.modul === filterModul.value);
    }

    // 3. Cari
    const q = cari.value.trim().toLowerCase();
    if (q) {
        list = list.filter((t) => {
            const nama = (t.nama || '').toLowerCase();
            const ket = (t.keterangan || '').toLowerCase();
            const syarat = teksSyarat(t).toLowerCase();
            return nama.includes(q) || ket.includes(q) || syarat.includes(q);
        });
    }

    return list;
});

watch([() => barisTersaring.value.length, barisPerHalaman], () => {
    if (halamanAktif.value > Math.ceil(barisTersaring.value.length / barisPerHalaman.value)) {
        halamanAktif.value = 1;
    }
});

const barisTampil = computed(() => {
    const awal = (halamanAktif.value - 1) * barisPerHalaman.value;
    return barisTersaring.value.slice(awal, awal + barisPerHalaman.value);
});

// ── Formatter Teks Syarat ──
const formatJenis = (j) => {
    const peta = {
        DIVISI: 'Divisi',
        DEPARTEMEN: 'Dept',
        LOKASI: 'Lokasi',
        LEVEL: 'Level',
        JABATAN: 'Jabatan',
        KARYAWAN: 'Karyawan',
        PHL: 'Status PHL',
    };
    return peta[j] || j;
};

const syaratPerGrup = (t) => {
    const per = {};
    for (const s of t.syarat || []) {
        (per[s.jenis] ||= []).push(s.label || s.nilai || s.jenis);
    }
    return per;
};

const teksSyarat = (t) => {
    const per = syaratPerGrup(t);
    return Object.entries(per)
        .map(([jenis, vals]) => `[${formatJenis(jenis)}: ${vals.join(' atau ')}]`)
        .join(' DAN ');
};

// ── Muat Data API ──
async function muat() {
    memuat.value = true;
    try {
        Object.assign(daftar, await aksesPeranApi.templateCakupan());
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal memuat template cakupan.'));
    } finally {
        memuat.value = false;
        dimuat.value = true;
    }
}

async function muatOpsi() {
    if (Object.keys(opsiNilai.value).length) return;
    try {
        const o = await aksesPeranApi.opsiSaringKaryawan();
        opsiNilai.value = {
            DIVISI: o.divisi || [],
            DEPARTEMEN: o.departemen || [],
            LOKASI: o.lokasi || [],
            LEVEL: o.level || [],
            JABATAN: o.jabatan || [],
        };
    } catch {
        /* Pilihan dropdown fallback aman */
    }
}

// ── Form Modal Buat / Ubah ──
const form = reactive({
    buka: false,
    id: null,
    nama: '',
    keterangan: '',
    phl: false,
    pemakai: 0,
    nilai: nilaiKosong(),
    labelKaryawan: {},
});

const menyimpan = ref(false);

const syaratForm = computed(() => [
    ...(form.phl ? [{ jenis: 'PHL', nilai: null }] : []),
    ...NILAI.flatMap((k) => (form.nilai[k] || []).map((n) => ({ jenis: k, nilai: String(n) }))),
]);

const formulaDraf = computed(() => {
    const per = {};
    if (form.phl) per['PHL'] = ['Khusus Pegawai Harian Lepas (PHL)'];
    for (const k of NILAI) {
        const arr = form.nilai[k] || [];
        if (arr.length) {
            const listLabel = arr.map((val) => {
                if (k === 'KARYAWAN') return form.labelKaryawan[val] || `Karyawan #${val}`;
                const matched = (opsiNilai.value[k] || []).find((o) => String(o.nilai) === String(val));
                return matched ? matched.label : val;
            });
            per[k] = listLabel;
        }
    }
    return Object.entries(per)
        .map(([jenis, vals]) => `[${formatJenis(jenis)}: ${vals.join(' atau ')}]`)
        .join(' DAN ');
});

function bukaForm(t) {
    muatOpsi();
    const nilai = nilaiKosong();
    const labelKaryawan = {};
    for (const s of t?.syarat || []) {
        if (s.jenis === 'KARYAWAN' && s.nilai) {
            labelKaryawan[s.nilai] = String(s.label || '').replace(/^Karyawan:\s*/, '');
        }
        if (nilai[s.jenis] && s.nilai) nilai[s.jenis].push(String(s.nilai));
    }
    Object.assign(form, {
        buka: true,
        id: t?.id || null,
        nama: t?.nama || '',
        keterangan: t?.keterangan || '',
        phl: (t?.syarat || []).some((s) => s.jenis === 'PHL'),
        pemakai: t?.pemakai || 0,
        nilai,
        labelKaryawan,
    });
}

async function simpan() {
    menyimpan.value = true;
    try {
        await aksesPeranApi.simpanTemplateCakupan(
            { nama: form.nama.trim(), keterangan: form.keterangan.trim() || null, syarat: syaratForm.value },
            form.id,
        );
        hcisMessage.success(form.id ? 'Template berhasil diperbarui.' : 'Template berhasil dibuat.');
        form.buka = false;
        muat();
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal menyimpan template.'));
    } finally {
        menyimpan.value = false;
    }
}

// ── Nonaktifkan Template ──
const nonaktif = reactive({ buka: false, nama: '', keterangan: '', aksi: async () => {} });

function bukaNonaktif(t) {
    Object.assign(nonaktif, {
        buka: true,
        nama: t.nama,
        keterangan: t.pemakai
            ? `Template ini sedang dipakai oleh ${t.pemakai} penugasan peran. Setelah dinonaktifkan, penugasan tersebut tidak akan mencakup siapa pun sampai cakupannya diganti.`
            : 'Template ini belum dipakai oleh penugasan mana pun dan aman untuk dinonaktifkan.',
        aksi: (alasan) => aksesPeranApi.nonaktifkanTemplateCakupan(t.id, alasan),
    });
}

onMounted(muat);
</script>

<style scoped>
.tc-layout {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

/* ══ 1. METRIK KPI STATCARD ══ */
.tc-stat-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

/* ══ 2. BANNER PANDUAN TATA KELOLA ══ */
.tc-banner-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 12px);
    padding: var(--hcis-space-3, 0.75rem) var(--hcis-space-4, 1rem);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* ══ 3. FILTERBAR WRAPPER ══ */
.tc-filterbar-wrap {
    width: 100%;
}

/* ══ 4. TABEL CARD ══ */
.tc-tabel-card {
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.tc-nama-template {
    font-size: 0.875rem;
    font-weight: 600;
}

.tc-icon-box {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.tc-icon-box--brand {
    background: #f0f9ff;
    color: var(--hcis-brand-700, #0369a1);
    border: 1px solid #bae6fd;
}

.tc-icon-box--teal {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #ccfbf1;
}

.tc-pill-syarat {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 0.725rem;
    line-height: 1.3;
}

.tc-pill-syarat__label {
    font-weight: 700;
    color: var(--hcis-brand-700, #0369a1);
}

.tc-pill-syarat__val {
    color: var(--hcis-text-secondary, #475569);
    font-weight: 500;
}

.tc-syarat-formula {
    font-size: 0.725rem;
    line-height: 1.35;
    white-space: normal;
    word-break: break-word;
}

.tc-formula-tag {
    font-weight: 700;
    color: var(--hcis-brand-600, #0284c7);
}

.tc-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: gap-2;
    padding: 0.65rem 1rem;
    background: #f8fafc;
    border-top: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

/* ══ 5. MODAL FORM STYLES ══ */
.tc-form-body {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 0.5rem);
}

.tc-form-section {
    padding: 0.9rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 8px);
}

.tc-phl-toggle {
    padding: 0.35rem 0.75rem;
    background: #fefce8;
    border: 1px solid #fef08a;
    border-radius: var(--hcis-radius-md, 6px);
}

.tc-pratinjau-kriteria {
    padding: 0.75rem 0.85rem;
    background: #f8fafc;
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid #e2e8f0;
}

.tc-formula-box {
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    color: var(--hcis-brand-900, #0c4a6e);
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.75rem;
    line-height: 1.45;
    word-break: break-word;
}

.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-600, #0284c7);
    display: block;
}

.ap-kecil {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.text-brand {
    color: var(--hcis-brand-600, #0284c7);
}

.cursor-pointer {
    cursor: pointer;
}

.min-w-0 {
    min-width: 0;
}
</style>
