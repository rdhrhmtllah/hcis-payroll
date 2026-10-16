<template>
    <div class="th-layout">
        <!-- ══ 1. HEADER RINGKASAN & METRIK KONTROL (KPI / STATS) ══ -->
        <div class="th-stat-row">
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="brand"
                icon="window-stack"
                label="Total Halaman Terdaftar"
                :value="totalHalaman"
                caption="Halaman sistem dalam cakupan"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="primary"
                icon="diagram-3"
                label="Grup / Header Menu"
                :value="totalGrup"
                caption="Kategori induk menu aplikasi"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                :sentiment="totalBanyakPeran > 0 ? 'warning' : 'neutral'"
                icon="shield-exclamation"
                label="Halaman Multirole (≥3)"
                :value="totalBanyakPeran"
                caption="Halaman dengan 3 atau lebih peran"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                :sentiment="totalAdaGrant > 0 ? 'warning' : 'neutral'"
                icon="key-fill"
                label="Akses Langsung / Grant"
                :value="totalAdaGrant"
                caption="Halaman dibuka di luar peran resmi"
            />
        </div>

        <!-- ══ 2. OFFICIAL HCIS FILTER BAR DENGAN QUICK CHIPS & ADVANCED SELECTS ══ -->
        <HcisFilterBar
            :search="cari"
            search-placeholder="Cari nama menu, header, atau kode jenis_page…"
            :quick-chips="quickChips"
            :active-chip-value="chipAktif"
            :filters="daftarFilterField"
            :filter-values="filterValues"
            collapsible
            variant="flat"
            size="sm"
            sentiment="brand"
            class="th-filterbar"
            @update:search="onPencarian"
            @chip-click="onChipClick"
            @filter-change="onFilterChange"
            @reset="resetSemuaFilter"
        />

        <!-- ══ 3. KONTEN TABEL DATA DENGAN HCIS TABLE LENGKAP ══ -->
        <div class="th-tabel-card">
            <!-- Loading Skeleton -->
            <HcisSkeleton v-if="memuat && !data.length" :lines="7" />

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!barisTersaring.length"
                icon="window-x"
                title="Tidak Ada Halaman yang Cocok"
                description="Tidak ditemukan halaman yang sesuai dengan kriteria saringan atau kata kunci pencarian Anda."
                size="sm"
                class="py-5"
            >
                <template #actions>
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        label="Bersihkan Saringan"
                        @click="resetSemuaFilter"
                    />
                </template>
            </HcisEmptyState>

            <!-- Table View -->
            <template v-else>
                <HcisTable
                    :columns="kolom"
                    :rows="barisTampil"
                    row-key="jenis_page"
                    responsive="auto"
                    variant="flat"
                    sentiment="brand"
                    size="sm"
                    dense
                    hoverable
                >
                    <!-- Kolom 1: Nama Menu & Identitas Halaman -->
                    <template #cell-halaman="{ row }">
                        <div class="d-flex align-items-center gap-2.5 py-1">
                            <div class="th-icon-box flex-shrink-0" :class="iconBoxClass(row)">
                                <HcisIcon :name="iconHalaman(row)" size="xs" />
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark text-truncate th-nama-menu" :title="row.nama_menu">
                                    {{ row.nama_menu }}
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap mt-0.5">
                                    <span v-if="row.header" class="th-badge-header">
                                        {{ row.header }}
                                    </span>
                                    <span v-else class="th-badge-header th-badge-header--tanpa"> Tanpa Grup </span>
                                    <span class="text-muted small">·</span>
                                    <span class="font-monospace small text-muted text-truncate" :title="row.jenis_page">
                                        {{ row.jenis_page }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom 2: Peran Pembuka -->
                    <template #cell-peran="{ row }">
                        <div v-if="row.peran?.length" class="d-flex flex-wrap gap-1 align-items-center py-1">
                            <HcisBadge
                                v-for="p in batasiPeran(row.peran, row.jenis_page)"
                                :key="p.id"
                                :label="`${p.nama} (${p.pemegang})`"
                                :title="titlePeran(p)"
                                :variant="varianBadgePeran(p)"
                                appearance="subtle"
                                size="xs"
                            />
                            <!-- Tombol ekspansi jika peran > 3 -->
                            <button
                                v-if="row.peran.length > 3"
                                type="button"
                                class="th-badge-toggle"
                                @click="toggleEkspansiPeran(row.jenis_page)"
                            >
                                {{
                                    peranDiekspansi[row.jenis_page] ? 'Sembunyikan' : `+${row.peran.length - 3} lainnya`
                                }}
                            </button>
                        </div>
                        <span v-else class="text-muted small italic">Belum ada peran</span>
                    </template>

                    <!-- Kolom 3: Banyaknya Peran -->
                    <template #cell-jumlah_peran="{ row }">
                        <HcisBadge
                            :label="`${row.peran?.length || 0} peran`"
                            :variant="row.peran?.length >= 3 ? 'warning' : 'neutral'"
                            appearance="subtle"
                            size="xs"
                        />
                    </template>

                    <!-- Kolom 4: Pengguna via Peran -->
                    <template #cell-orang="{ row }">
                        <HcisButton
                            v-if="row.orang > 0"
                            variant="ghost"
                            size="xs"
                            leading-icon="people-fill"
                            :label="`${row.orang} orang`"
                            class="th-link-action"
                            title="Klik untuk membuka rincian nama karyawan"
                            @click="bukaRincian(row, 'peran')"
                        />
                        <span v-else class="text-muted small ps-2">0</span>
                    </template>

                    <!-- Kolom 5: Akses Langsung / Grant -->
                    <template #cell-grant_langsung="{ row }">
                        <HcisButton
                            v-if="row.grant_langsung > 0"
                            variant="ghost"
                            size="xs"
                            leading-icon="key-fill"
                            :label="`${row.grant_langsung} orang`"
                            class="th-link-action th-link-action--warning"
                            title="Klik untuk melihat penerima akses langsung khusus"
                            @click="bukaRincian(row, 'grant')"
                        />
                        <span v-else class="text-muted small ps-2">0</span>
                    </template>

                    <!-- Kolom 6: Aksi Detail -->
                    <template #cell-aksi="{ row }">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="eye"
                            label="Lihat Rincian"
                            @click="bukaRincian(row, 'peran')"
                        />
                    </template>

                    <!-- Footer: Pagination -->
                    <template #footer>
                        <div class="th-table-footer">
                            <HcisPagination
                                v-model:current-page="halamanAktif"
                                v-model:page-size="barisPerHalaman"
                                :total="barisTersaring.length"
                                :page-sizes="[15, 25, 50, 100]"
                                size="sm"
                                background
                            />
                        </div>
                    </template>
                </HcisTable>
            </template>
        </div>

        <!-- ══ 4. MODAL RINCIAN PENGGUNA HALAMAN (HCIS DIALOG RESMI) ══ -->
        <HcisDialog
            v-model="modalRincian.buka"
            :title="modalRincian.baris?.nama_menu || 'Rincian Pengguna Halaman'"
            size="lg"
            icon="shield-lock"
            header-divider
        >
            <template v-if="modalRincian.baris">
                <!-- Header Meta Info Box -->
                <div class="th-modal-header-info">
                    <div class="min-w-0">
                        <span class="ap-kicker">{{ modalRincian.baris.header || 'MENU SISTEM UMUM' }}</span>
                        <div class="font-monospace small text-muted mt-0.5">{{ modalRincian.baris.jenis_page }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <HcisBadge
                            :label="`${modalRincian.data?.orang_peran?.length || 0} Pengguna via Peran`"
                            variant="brand"
                            appearance="subtle"
                            size="sm"
                        />
                        <HcisBadge
                            v-if="modalRincian.data?.orang_grant?.length"
                            :label="`${modalRincian.data?.orang_grant?.length} Akses Langsung`"
                            variant="warning"
                            appearance="subtle"
                            size="sm"
                        />
                    </div>
                </div>

                <!-- Sub-tabs Navigasi: Lewat Peran vs Akses Langsung -->
                <HcisTabs
                    v-model="modalRincian.subtab"
                    :tabs="daftarSubtabModal"
                    variant="segmented"
                    size="sm"
                    class="mb-3"
                />

                <!-- Loading State Modal -->
                <HcisSkeleton v-if="modalRincian.memuat" :lines="5" />

                <!-- TAB 1: ORANG VIA PERAN -->
                <div v-else-if="modalRincian.subtab === 'peran'">
                    <HcisEmptyState
                        v-if="!modalRincian.data?.orang_peran?.length"
                        icon="people"
                        title="Tidak Ada Pengguna via Peran"
                        description="Belum ada karyawan aktif yang memegang peran pembuka halaman ini."
                        size="sm"
                    />
                    <div v-else class="th-user-list">
                        <div v-for="u in modalRincian.data.orang_peran" :key="u.id_user" class="th-user-item">
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar :name="u.nama" size="sm" class="flex-shrink-0" />
                                <div class="min-w-0">
                                    <div class="fw-semibold small text-dark text-truncate">{{ u.nama }}</div>
                                    <div class="text-muted ap-kecil font-monospace">{{ u.kode_karyawan || '—' }}</div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 justify-content-end max-w-50">
                                <HcisBadge
                                    v-for="pr in u.peran"
                                    :key="pr.id"
                                    :label="pr.nama"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: ORANG VIA AKSES LANGSUNG (GRANT) -->
                <div v-else-if="modalRincian.subtab === 'grant'">
                    <HcisEmptyState
                        v-if="!modalRincian.data?.orang_grant?.length"
                        icon="shield-check"
                        title="Tidak Ada Akses Langsung"
                        description="Halaman ini hanya dibuka lewat paket peran resmi. Tidak ada izin perorangan (grant khusus)."
                        size="sm"
                    />
                    <div v-else class="th-user-list">
                        <div
                            v-for="u in modalRincian.data.orang_grant"
                            :key="u.id_user"
                            class="th-user-item th-user-item--grant"
                        >
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar :name="u.nama" size="sm" class="flex-shrink-0" />
                                <div class="min-w-0">
                                    <div class="fw-semibold small text-dark text-truncate">{{ u.nama }}</div>
                                    <div class="text-muted ap-kecil font-monospace">{{ u.kode_karyawan || '—' }}</div>
                                    <div v-if="u.alasan" class="text-muted ap-kecil mt-0.5">
                                        Alasan: <em>{{ u.alasan }}</em>
                                    </div>
                                    <div v-if="u.berlaku_sampai" class="text-muted ap-kecil">
                                        Berlaku s.d. <strong>{{ u.berlaku_sampai.slice(0, 10) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div v-if="u.bisa_cabut" class="flex-shrink-0">
                                <HcisButton
                                    variant="danger"
                                    size="xs"
                                    leading-icon="trash"
                                    label="Cabut Akses"
                                    @click="bukaCabutGrant(u)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex justify-content-end w-100">
                    <HcisButton variant="secondary" size="sm" label="Tutup" @click="modalRincian.buka = false" />
                </div>
            </template>
        </HcisDialog>

        <!-- ══ 5. DIALOG ALASAN CABUT GRANT LANGSUNG ══ -->
        <DialogAlasan
            v-model="cabutGrantModal.buka"
            :judul="cabutGrantModal.judul"
            :keterangan="cabutGrantModal.keterangan"
            label="Cabut Akses Langsung"
            :aksi="cabutGrantModal.aksi"
            @selesai="setelahCabutGrant"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisStatCard,
    HcisTable,
    HcisTabs,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, pesanGalat } from '../api/aksesPeranApi.js';
import DialogAlasan from './DialogAlasan.vue';

// ── State Utama ──
const data = ref([]);
const memuat = ref(false);
const cari = ref('');
const chipAktif = ref('semua');
const filterGrup = ref('');
const filterKategori = ref('');

// Pagination lokal untuk tabel
const halamanAktif = ref(1);
const barisPerHalaman = ref(25);

// State ekspansi badge peran per halaman (+N lainnya)
const peranDiekspansi = reactive({});

let penunda = null;
let urutan = 0;

// ── Kolom Tabel ──
const kolom = [
    { key: 'halaman', label: 'Nama Halaman & Menu', width: '32%' },
    { key: 'peran', label: 'Dibuka oleh Peran (Jumlah Pemegang)' },
    { key: 'jumlah_peran', label: 'Peran', width: '90px' },
    { key: 'orang', label: 'Pengguna Peran', width: '130px' },
    { key: 'grant_langsung', label: 'Akses Langsung', width: '135px' },
    { key: 'aksi', label: '', width: '110px' },
];

// ── Perhitungan Metrik / Stat Cards ──
const totalHalaman = computed(() => data.value.length);
const totalGrup = computed(() => {
    const list = new Set(data.value.map((h) => h.header || 'Tanpa Grup').filter(Boolean));
    return list.size;
});
const totalBanyakPeran = computed(() => data.value.filter((h) => h.peran && h.peran.length >= 3).length);
const totalAdaGrant = computed(() => data.value.filter((h) => h.grant_langsung > 0).length);

// ── Quick Chips untuk HcisFilterBar ──
const quickChips = computed(() => [
    {
        id: 'semua',
        value: 'semua',
        label: 'Semua Halaman',
        icon: 'window-stack',
        badge: totalHalaman.value,
    },
    {
        id: 'banyak',
        value: 'banyak',
        label: '3+ Peran (Multirole)',
        icon: 'layers-half',
        color: 'warning',
        badge: totalBanyakPeran.value,
    },
    {
        id: 'langsung',
        value: 'langsung',
        label: 'Akses Langsung (Grant)',
        icon: 'key',
        color: 'danger',
        badge: totalAdaGrant.value,
    },
]);

// ── Opsi Dropdown Filter Grup Menu ──
const opsiGrup = computed(() => {
    const grupSet = new Set(data.value.map((h) => h.header || 'Tanpa Grup'));
    const sorted = Array.from(grupSet).sort((a, b) => {
        if (a === 'Tanpa Grup') return 1;
        if (b === 'Tanpa Grup') return -1;
        return a.localeCompare(b);
    });
    return sorted.map((g) => ({
        label: g,
        value: g,
    }));
});

// ── Opsi Dropdown Sifat Halaman ──
const opsiKategori = [
    { label: 'Semua Sifat', value: '' },
    { label: 'Halaman Biasa (< 3 peran)', value: 'tunggal' },
    { label: 'Multirole (≥ 3 peran)', value: 'banyak' },
    { label: 'Memiliki Akses Langsung', value: 'grant' },
    { label: 'Murni Paket Peran (Tanpa Grant)', value: 'murni' },
];

// ── Konfigurasi Filter Field untuk HcisFilterBar ──
const daftarFilterField = computed(() => [
    {
        key: 'grup',
        label: 'Grup / Header Menu',
        type: 'select',
        placeholder: 'Semua grup menu',
        clearable: true,
        options: opsiGrup.value,
    },
    {
        key: 'kategori',
        label: 'Sifat Otorisasi',
        type: 'select',
        placeholder: 'Semua sifat',
        clearable: true,
        options: opsiKategori,
    },
]);

const filterValues = computed(() => ({
    grup: filterGrup.value,
    kategori: filterKategori.value,
}));

// ── Handler Event Filter ──
function onPencarian(v) {
    cari.value = v;
    jadwalkan();
}

function onChipClick(chip) {
    chipAktif.value = chip.value;
    halamanAktif.value = 1;
}

function onFilterChange({ key, value }) {
    if (key === 'grup') filterGrup.value = value || '';
    if (key === 'kategori') filterKategori.value = value || '';
    halamanAktif.value = 1;
}

function resetSemuaFilter() {
    cari.value = '';
    chipAktif.value = 'semua';
    filterGrup.value = '';
    filterKategori.value = '';
    halamanAktif.value = 1;
    muat();
}

// ── Penyaringan Baris Data ──
const barisTersaring = computed(() => {
    let hasil = data.value;

    // 1. Filter Quick Chip
    if (chipAktif.value === 'banyak') {
        hasil = hasil.filter((h) => h.peran && h.peran.length >= 3);
    } else if (chipAktif.value === 'langsung') {
        hasil = hasil.filter((h) => h.grant_langsung > 0);
    }

    // 2. Filter Grup Menu
    if (filterGrup.value) {
        if (filterGrup.value === 'Tanpa Grup') {
            hasil = hasil.filter((h) => !h.header);
        } else {
            hasil = hasil.filter((h) => h.header === filterGrup.value);
        }
    }

    // 3. Filter Sifat Otorisasi
    if (filterKategori.value === 'tunggal') {
        hasil = hasil.filter((h) => !h.peran || h.peran.length < 3);
    } else if (filterKategori.value === 'banyak') {
        hasil = hasil.filter((h) => h.peran && h.peran.length >= 3);
    } else if (filterKategori.value === 'grant') {
        hasil = hasil.filter((h) => h.grant_langsung > 0);
    } else if (filterKategori.value === 'murni') {
        hasil = hasil.filter((h) => !h.grant_langsung || h.grant_langsung === 0);
    }

    // 4. Pencarian teks lokal tambahan (jika tidak via API langsung)
    const q = cari.value.trim().toLowerCase();
    if (q) {
        hasil = hasil.filter((h) => {
            const nama = (h.nama_menu || '').toLowerCase();
            const header = (h.header || '').toLowerCase();
            const page = (h.jenis_page || '').toLowerCase();
            const peran = (h.peran || [])
                .map((p) => `${p.nama} ${p.kode}`)
                .join(' ')
                .toLowerCase();
            return nama.includes(q) || header.includes(q) || page.includes(q) || peran.includes(q);
        });
    }

    return hasil;
});

// Reset halaman aktif jika hasil saringan berubah
watch([() => barisTersaring.value.length, barisPerHalaman], () => {
    if (halamanAktif.value > Math.ceil(barisTersaring.value.length / barisPerHalaman.value)) {
        halamanAktif.value = 1;
    }
});

// Paginasi Baris Tampil
const barisTampil = computed(() => {
    const awal = (halamanAktif.value - 1) * barisPerHalaman.value;
    return barisTersaring.value.slice(awal, awal + barisPerHalaman.value);
});

// ── Logika Tampilan & Visual Peran ──
function batasiPeran(listPeran = [], key) {
    if (peranDiekspansi[key]) return listPeran;
    return listPeran.slice(0, 3);
}

function toggleEkspansiPeran(key) {
    peranDiekspansi[key] = !peranDiekspansi[key];
}

function varianBadgePeran(p) {
    if (p.otomatis || p.tingkat === 'SUPER_ADMIN') return 'purple';
    if (p.tingkat === 'ADMIN') return 'brand';
    if (p.jenis === 'POOL_APPROVAL') return 'warning';
    return 'neutral';
}

function titlePeran(p) {
    const aksi = p.aksi?.length ? p.aksi.join(', ') : 'VIEW';
    const tag = p.jenis === 'POOL_APPROVAL' ? ' (pool approval)' : '';
    return `${p.kode}${tag}: ${aksi}`;
}

function iconHalaman(row) {
    if (row.header?.toLowerCase().includes('admin')) return 'shield-lock';
    if (row.header?.toLowerCase().includes('dashboard')) return 'speedometer2';
    if (row.header?.toLowerCase().includes('kpi')) return 'trophy';
    if (row.header?.toLowerCase().includes('lms')) return 'mortarboard';
    return 'window';
}

function iconBoxClass(row) {
    if (row.header?.toLowerCase().includes('admin')) return 'th-icon-box--purple';
    if (row.grant_langsung > 0) return 'th-icon-box--warning';
    return 'th-icon-box--brand';
}

// ── Ambil Data API ──
async function muat() {
    const u = ++urutan;
    memuat.value = true;
    try {
        const r = await aksesPeranApi.petaHalaman(cari.value.trim());
        if (u === urutan) data.value = r || [];
    } catch (e) {
        if (u === urutan) hcisMessage.error(pesanGalat(e, 'Gagal memuat peta halaman.'));
    } finally {
        if (u === urutan) memuat.value = false;
    }
}

function jadwalkan() {
    clearTimeout(penunda);
    penunda = setTimeout(muat, 300);
}

onMounted(muat);

// ── MODAL RINCIAN PENGGUNA HALAMAN ──
const modalRincian = reactive({
    buka: false,
    baris: null,
    subtab: 'peran',
    memuat: false,
    data: null,
});

const daftarSubtabModal = computed(() => [
    {
        key: 'peran',
        label: `Pengguna via Peran (${modalRincian.data?.orang_peran?.length || 0})`,
        icon: 'people',
    },
    {
        key: 'grant',
        label: `Akses Langsung / Grant (${modalRincian.data?.orang_grant?.length || 0})`,
        icon: 'key',
    },
]);

async function bukaRincian(row, subtabDefault = 'peran') {
    modalRincian.baris = row;
    modalRincian.subtab = subtabDefault;
    modalRincian.data = null;
    modalRincian.memuat = true;
    modalRincian.buka = true;

    try {
        const res = await aksesPeranApi.orangHalaman(row.jenis_page);
        modalRincian.data = res;
        if (subtabDefault === 'grant' && !res.orang_grant?.length && res.orang_peran?.length) {
            modalRincian.subtab = 'peran';
        }
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal memuat rincian pengguna halaman.'));
    } finally {
        modalRincian.memuat = false;
    }
}

// ── CABUT GRANT LANGSUNG DARI MODAL ──
const cabutGrantModal = reactive({
    buka: false,
    judul: '',
    keterangan: '',
    aksi: async () => {},
});

function bukaCabutGrant(u) {
    Object.assign(cabutGrantModal, {
        buka: true,
        judul: `Cabut akses langsung ${modalRincian.baris.nama_menu}`,
        keterangan: `Akses langsung ke halaman ini akan dicabut dari ${u.nama}.`,
        aksi: (alasan) =>
            aksesPeranApi.cabutGrant({
                id_user: u.id_user,
                jenis_page: modalRincian.baris.jenis_page,
                alasan,
            }),
    });
}

async function setelahCabutGrant() {
    hcisMessage.success('Akses langsung berhasil dicabut.');
    if (modalRincian.baris) {
        await bukaRincian(modalRincian.baris, 'grant');
    }
    muat();
}
</script>

<style scoped>
.th-layout {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

/* ══ 1. KPI STAT ROW ══ */
.th-stat-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--hcis-space-2, 0.5rem);
}

/* ══ 2. FILTERBAR ══ */
.th-filterbar {
    background: #ffffff;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* ══ 3. TABEL WRAPPER CARD ══ */
.th-tabel-card {
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.th-nama-menu {
    font-size: 0.875rem;
    font-weight: 600;
}

.th-icon-box {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.th-icon-box--brand {
    background: rgb(var(--hcis-brand-50-rgb, 240 249 255));
    color: var(--hcis-brand-700, #0369a1);
    border: 1px solid rgb(var(--hcis-brand-200-rgb, 186 230 253));
}

.th-icon-box--purple {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.th-icon-box--warning {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.th-badge-header {
    display: inline-flex;
    align-items: center;
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    background: var(--hcis-surface-subtle, #f1f5f9);
    color: var(--hcis-text-secondary, #475569);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.th-badge-header--tanpa {
    background: #f8fafc;
    color: #94a3b8;
    font-style: italic;
}

.th-badge-toggle {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: var(--hcis-brand-700, #0369a1);
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.th-badge-toggle:hover {
    background: #f0f9ff;
    border-color: #7dd3fc;
}

.th-link-action {
    font-weight: 600;
}

.th-link-action--warning {
    color: #b45309 !important;
}

.th-table-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0.65rem 1rem;
    background: #f8fafc;
    border-top: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

/* ══ 4. MODAL RINCIAN ══ */
.th-modal-header-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-600, #0284c7);
}

.ap-kecil {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.th-user-list {
    max-height: 28rem;
    overflow-y: auto;
    padding-bottom: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.th-user-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid #e2e8f0;
    background: #ffffff;
    transition: background-color 0.15s ease;
}

.th-user-item:hover {
    background-color: var(--hcis-surface-subtle, #f8fafc);
}

.th-user-item--grant {
    border-color: #fef08a;
    background: #fffdf5;
}

.max-w-50 {
    max-width: 50%;
}

.min-w-0 {
    min-width: 0;
}
</style>
