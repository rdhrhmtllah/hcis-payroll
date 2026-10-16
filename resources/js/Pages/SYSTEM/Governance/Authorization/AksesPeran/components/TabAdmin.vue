<template>
    <div class="ap-admin-layout">
        <!-- ══ 1. STATISTIK RINGKASAN DELEGASI ADMINISTRATOR ══ -->
        <div class="ap-admin-stats">
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="brand"
                icon="shield-lock-fill"
                label="Total Administrator"
                :value="totalAdmin"
                caption="Pengelola wewenang modul aktif"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="purple"
                icon="shield-shaded"
                label="Kepala Bidang (Super Admin)"
                :value="totalSuperAdmin"
                caption="Pemilik modul dengan kuasa penuh"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="primary"
                icon="shield-fill-check"
                label="PIC Admin Modul"
                :value="totalPicAdmin"
                caption="Delegasi penugasan operasional"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                :sentiment="anda?.boleh_angkat ? 'success' : 'neutral'"
                icon="person-badge"
                label="Status Wewenang Anda"
                :value="anda?.label || 'Terbatas'"
                :caption="
                    anda?.boleh_angkat
                        ? 'Berhak mengangkat & mendelegasikan admin'
                        : 'Hanya berhak melihat struktur pengelola'
                "
            />
        </div>

        <!-- ══ 2. HEADER TATA KELOLA & PANDUAN HIRARKI (HCIS CARD) ══ -->
        <div class="ap-admin-governance-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 p-3">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="ap-kicker">Governance &amp; Delegasi Wewenang</span>
                        <HcisBadge
                            :label="`Hak Anda: ${anda?.label || 'Terbatas'}`"
                            :variant="anda?.boleh_angkat ? 'success' : 'neutral'"
                            appearance="subtle"
                            size="xs"
                        />
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Tata Kelola Administrator Modul</h5>
                    <p class="ap-kecil text-muted mb-0">
                        Pengelola hak akses dan penugasan peran. Setiap tingkat wewenang memiliki batasan kuasa yang
                        tegas dan hanya dapat mengatur tingkatan di bawahnya untuk mencegah eskalasi wewenang yang tidak
                        sah.
                    </p>
                </div>

                <div v-if="anda?.boleh_angkat" class="flex-shrink-0">
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="shield-plus"
                        label="Angkat Admin Baru"
                        @click="aksi.tugaskan({ hanyaAdmin: true })"
                    />
                </div>
            </div>

            <!-- Panduan Hirarki Wewenang Grid -->
            <div class="ap-admin-guide-grid">
                <!-- Card 1: Super Admin -->
                <div class="ap-guide-item ap-guide-item--super">
                    <div class="d-flex align-items-center gap-2 mb-1.5">
                        <div class="ap-guide-icon ap-guide-icon--super">
                            <HcisIcon name="shield-shaded" size="xs" />
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold small text-dark">Kepala Bidang / Dept Head</div>
                            <div class="text-muted ap-kecil">Super Admin (Tingkat Tertinggi)</div>
                        </div>
                        <HcisBadge label="Pemilik Modul" variant="purple" appearance="subtle" size="xs" />
                    </div>
                    <p class="ap-kecil text-muted mb-0">
                        Otomatis memiliki akses penuh ke seluruh menu modul, berwenang mengangkat staf PIC Admin, dan
                        mengatur delegasi otorisasi lintas organisasi.
                    </p>
                </div>

                <!-- Card 2: Admin Modul -->
                <div class="ap-guide-item ap-guide-item--admin">
                    <div class="d-flex align-items-center gap-2 mb-1.5">
                        <div class="ap-guide-icon ap-guide-icon--admin">
                            <HcisIcon name="shield-check" size="xs" />
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold small text-dark">PIC Pengelola Modul</div>
                            <div class="text-muted ap-kecil">Admin (Delegasi Operasional)</div>
                        </div>
                        <HcisBadge label="Operasional" variant="brand" appearance="subtle" size="xs" />
                    </div>
                    <p class="ap-kecil text-muted mb-0">
                        Staf operasional yang ditugaskan membantu menugaskan peran dan batas waktu akses pengguna dalam
                        batasan cakupan unit kerjanya.
                    </p>
                </div>
            </div>
        </div>

        <!-- ══ 3. OFFICIAL HCIS FILTER BAR (KONSISTEN DENGAN TAB PERAN & HALAMAN) ══ -->
        <HcisFilterBar
            :search="cari"
            search-placeholder="Cari nama admin, NIK, atau nama peran…"
            :quick-chips="quickChipsAdmin"
            :active-chip-value="saringTingkat"
            :filters="filterDefinitions"
            :filter-values="filterValues"
            collapsible
            variant="flat"
            size="sm"
            sentiment="brand"
            class="ap-admin-filterbar"
            @update:search="(v) => (cari = v)"
            @chip-click="onChipClick"
            @filter-change="onFilterChange"
            @reset="resetSaringan"
        />

        <!-- ══ 4. EMPTY STATE ══ -->
        <HcisEmptyState
            v-if="!adminTersaring.length"
            icon="shield-x"
            title="Tidak Ada Administrator Ditemukan"
            :description="
                cari || saringTingkat !== 'SEMUA' || saringPeran
                    ? 'Tidak ada data administrator yang cocok dengan saringan atau kata kunci pencarian.'
                    : 'Belum ada administrator terdaftar. Angkat administrator pertama untuk mulai mendelegasikan wewenang modul ini.'
            "
            size="sm"
            class="py-5"
        >
            <template #actions>
                <HcisButton
                    v-if="cari || saringTingkat !== 'SEMUA' || saringPeran"
                    variant="secondary"
                    size="xs"
                    leading-icon="arrow-counterclockwise"
                    label="Reset Saringan"
                    @click="resetSaringan"
                />
                <HcisButton
                    v-else-if="anda?.boleh_angkat"
                    variant="primary"
                    size="xs"
                    leading-icon="shield-plus"
                    label="Angkat Admin Sekarang"
                    @click="aksi.tugaskan({ hanyaAdmin: true })"
                />
            </template>
        </HcisEmptyState>

        <!-- ══ 5. DAFTAR ADMINISTRATOR PER TINGKAT (COLLAPSIBLE PANELS) ══ -->
        <div v-else class="d-flex flex-column gap-3">
            <div v-for="g in kelompokTersaring" :key="g.tingkat" class="ap-grup-admin">
                <!-- Header Grup Tingkat -->
                <div class="ap-grup-header">
                    <div class="d-flex align-items-center gap-2.5">
                        <div
                            class="ap-grup-icon"
                            :class="g.tingkat === 'SUPER_ADMIN' ? 'ap-grup-icon--super' : 'ap-grup-icon--admin'"
                        >
                            <HcisIcon
                                :name="g.tingkat === 'SUPER_ADMIN' ? 'shield-shaded' : 'shield-check'"
                                size="sm"
                            />
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark fs-6">{{
                                    infoTingkat[g.tingkat]?.label || g.tingkat
                                }}</span>
                                <HcisBadge
                                    :label="`${g.baris.length} Orang`"
                                    :variant="g.tingkat === 'SUPER_ADMIN' ? 'purple' : 'brand'"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                            <div class="text-muted ap-kecil mt-0.5">
                                {{ artiTingkat[g.tingkat] }}
                            </div>
                        </div>
                    </div>

                    <div v-if="anda?.boleh_angkat" class="d-none d-sm-block">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="plus-circle"
                            :label="`Tambah ${infoTingkat[g.tingkat]?.label || 'Admin'}`"
                            @click="aksi.tugaskan({ hanyaAdmin: true })"
                        />
                    </div>
                </div>

                <!-- Body: Tabel Penugasan Standar -->
                <div class="ap-grup-body">
                    <TabelPenugasan :rows="g.baris" tampil-orang bisa-aksi @cabut="bukaCabut" @batas="bukaBatas" />
                </div>
            </div>
        </div>

        <!-- ══ 6. DIALOG ALASAN & BATAS WAKTU ══ -->
        <DialogAlasan
            v-model="cabut.buka"
            :judul="cabut.judul"
            :keterangan="cabut.keterangan"
            :aksi="cabut.aksi"
            @selesai="$emit('berubah')"
        />
        <DialogBatas v-model="batas.buka" :baris="batas.baris" @selesai="$emit('berubah')" />
    </div>
</template>

<script setup>
import { computed, inject, reactive, ref } from 'vue';
import { HcisBadge, HcisButton, HcisEmptyState, HcisFilterBar, HcisIcon, HcisStatCard } from '@/components/ui';
import TabelPenugasan from './TabelPenugasan.vue';
import DialogAlasan from './DialogAlasan.vue';
import DialogBatas from './DialogBatas.vue';
import { aksesPeranApi, infoTingkat } from '../api/aksesPeranApi.js';

/** Admin per tingkat wewenang, dengan Angkat & Cabut langsung di sini. */
const props = defineProps({
    admin: { type: Array, required: true },
    anda: { type: Object, default: null },
});
const emit = defineEmits(['berubah']);
const aksi = inject('aksiAkses');

// ── Saringan & Filter Lokal ──
const cari = ref('');
const saringTingkat = ref('SEMUA');
const saringPeran = ref('');

const artiTingkat = {
    SUPER_ADMIN: 'Kepala Bidang / Dept Head: otomatis berhak penuh atas konfigurasi modul & delegasi wewenang staf.',
    ADMIN: 'PIC Pengelola: staf operasional yang membantu menugaskan peran pengguna dalam cakupannya.',
};

// ── Perhitungan Metrik / Stat Card ──
const totalAdmin = computed(() => props.admin.length);
const totalSuperAdmin = computed(() => props.admin.filter((a) => (a.tingkat || 'ADMIN') === 'SUPER_ADMIN').length);
const totalPicAdmin = computed(() => props.admin.filter((a) => (a.tingkat || 'ADMIN') === 'ADMIN').length);

// ── Quick Chips untuk HcisFilterBar ──
const quickChipsAdmin = computed(() => [
    {
        id: 'semua',
        value: 'SEMUA',
        label: 'Semua Admin',
        icon: 'people-fill',
        badge: totalAdmin.value,
    },
    {
        id: 'super',
        value: 'SUPER_ADMIN',
        label: 'Super Admin',
        icon: 'shield-shaded',
        color: 'purple',
        badge: totalSuperAdmin.value,
    },
    {
        id: 'admin',
        value: 'ADMIN',
        label: 'PIC Admin Modul',
        icon: 'shield-check',
        color: 'primary',
        badge: totalPicAdmin.value,
    },
]);

// ── Dropdown Filters untuk HcisFilterBar ──
const opsiPeran = computed(() => {
    const peranSet = new Set(props.admin.map((a) => a.nama_peran).filter(Boolean));
    return Array.from(peranSet).map((p) => ({
        label: p,
        value: p,
    }));
});

const filterDefinitions = computed(() => {
    const list = [];
    if (opsiPeran.value.length > 1) {
        list.push({
            key: 'peran',
            label: 'Peran Tertentu',
            type: 'select',
            placeholder: 'Semua peran admin',
            clearable: true,
            options: opsiPeran.value,
        });
    }
    return list;
});

const filterValues = computed(() => ({
    peran: saringPeran.value,
}));

function onChipClick(chip) {
    saringTingkat.value = chip.value;
}

function onFilterChange({ key, value }) {
    if (key === 'peran') {
        saringPeran.value = value || '';
    }
}

function resetSaringan() {
    cari.value = '';
    saringTingkat.value = 'SEMUA';
    saringPeran.value = '';
}

// ── Penyaringan Baris Data ──
const adminTersaring = computed(() => {
    let list = props.admin;

    // Filter Quick Chip Tingkat
    if (saringTingkat.value !== 'SEMUA') {
        list = list.filter((a) => (a.tingkat || 'ADMIN') === saringTingkat.value);
    }

    // Filter Peran Tertentu
    if (saringPeran.value) {
        list = list.filter((a) => a.nama_peran === saringPeran.value);
    }

    // Filter Pencarian Teks
    const q = cari.value.trim().toLowerCase();
    if (q) {
        list = list.filter((a) => {
            const nama = (a.nama || '').toLowerCase();
            const nik = (a.kode_karyawan || '').toLowerCase();
            const peran = (a.nama_peran || '').toLowerCase();
            const kode = (a.kode || '').toLowerCase();
            return nama.includes(q) || nik.includes(q) || peran.includes(q) || kode.includes(q);
        });
    }

    return list;
});

const kelompokTersaring = computed(() =>
    ['SUPER_ADMIN', 'ADMIN']
        .map((t) => ({
            tingkat: t,
            baris: adminTersaring.value.filter((a) => (a.tingkat || 'ADMIN') === t),
        }))
        .filter((g) => g.baris.length),
);

// ── Dialog Cabut & Batas Waktu ──
const cabut = reactive({ buka: false, judul: '', keterangan: '', aksi: async () => {} });
const batas = reactive({ buka: false, baris: null });

function bukaCabut(row) {
    Object.assign(cabut, {
        buka: true,
        judul: `Cabut ${row.nama_peran} dari ${row.nama}`,
        keterangan:
            'Wewenang administrator ini akan dicabut. Akses menu biasa lainnya milik karyawan tidak akan terpengaruh.',
        aksi: (alasan) => aksesPeranApi.cabutPenugasan(row.id_penugasan, alasan),
    });
}

function bukaBatas(row) {
    Object.assign(batas, { buka: true, baris: row });
}
</script>

<style scoped>
.ap-admin-layout {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

/* ══ 1. STAT CARDS GRID ══ */
.ap-admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: var(--hcis-space-2, 0.5rem);
}

/* ══ 2. GOVERNANCE BANNER CARD ══ */
.ap-admin-governance-card {
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.ap-admin-guide-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem 1rem 1rem 1rem;
    background: #f8fafc;
    border-top: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

.ap-guide-item {
    padding: 0.75rem 0.85rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 10px);
}

.ap-guide-item--super {
    border-left: 3px solid #8b5cf6;
}

.ap-guide-item--admin {
    border-left: 3px solid var(--hcis-brand-500, #0284c7);
}

.ap-guide-icon {
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ap-guide-icon--super {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.ap-guide-icon--admin {
    background: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
}

/* ══ 3. FILTER BAR STYLING (KONSISTEN) ══ */
.ap-admin-filterbar {
    background: #ffffff;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

/* ══ 4. GRUP ADMIN CARDS ══ */
.ap-grup-admin {
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
}

.ap-grup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

.ap-grup-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.ap-grup-icon--super {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.ap-grup-icon--admin {
    background: #f0f9ff;
    color: #0369a1;
    border: 1px solid #bae6fd;
}

.ap-grup-body {
    padding: 0.75rem 1rem;
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
    line-height: 1.45;
}

.min-w-0 {
    min-width: 0;
}
</style>
