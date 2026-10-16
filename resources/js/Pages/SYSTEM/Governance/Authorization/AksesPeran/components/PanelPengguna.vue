<template>
    <!-- ══ 1. EMBEDDED MODE (COCKPIT WORKSPACE) ══ -->
    <div v-if="embedded" class="ap-panel-embedded">
        <HcisSkeleton v-if="memuat" :lines="8" class="p-3" />
        <div v-else-if="akses" class="ap-workspace">
            <!-- Profil Karyawan & Aksi Cepat -->
            <div class="ap-profil-card p-3 mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <HcisAvatar :name="akses.nama" size="lg" />
                        <div class="min-w-0">
                            <div class="fw-bold fs-6 text-truncate text-dark">{{ akses.nama }}</div>
                            <div class="text-muted small">
                                NIK: {{ akses.kode_karyawan || '—' }} ·
                                {{ akses.system_admin ? 'System Admin' : 'Anggota Modul' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="akses.bisa_kelola" class="d-flex align-items-center gap-2 flex-wrap">
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="person-plus"
                            label="Tugaskan Peran"
                            @click="aksi.tugaskan({ idUser: akses.id_user, setelah: muat })"
                        />
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="key"
                            label="Beri Akses Halaman"
                            @click="dialogHalaman = true"
                        />
                    </div>
                </div>

                <HcisAlert
                    v-if="!akses.bisa_kelola"
                    variant="neutral"
                    appearance="subtle"
                    class="mt-3 mb-0"
                    title="Akses Baca-Saja"
                    description="Akses karyawan ini tidak dapat Anda ubah (di luar modul/cakupan Anda, setingkat atau di atas wewenang Anda, atau diri sendiri)."
                />
            </div>

            <!-- Segmented Sub-Tabs -->
            <HcisTabs v-model="sub" :tabs="daftarSub" variant="segmented" size="sm" class="mb-3" />

            <!-- Sub 1: Peran yang Dipegang -->
            <div v-if="sub === 'peran'" class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Daftar peran modul yang aktif dipegang</span>
                    <HcisButton
                        v-if="akses.bisa_kelola"
                        variant="ghost"
                        size="xs"
                        leading-icon="plus-lg"
                        label="Tambah Peran"
                        @click="aksi.tugaskan({ idUser: akses.id_user, setelah: muat })"
                    />
                </div>
                <TabelPenugasan
                    :rows="akses.peran"
                    :bisa-aksi="akses.bisa_kelola"
                    @cabut="bukaCabutPeran"
                    @batas="bukaBatas"
                />
            </div>

            <!-- Sub 2: Akses Langsung (Grant) -->
            <div v-else-if="sub === 'grant'" class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold small">Akses Langsung Halaman (Dispensasi)</div>
                        <div class="text-muted ap-kecil">
                            Memberi izin buka halaman tertentu di luar paket peran. Sebaiknya disertai batas waktu.
                        </div>
                    </div>
                    <HcisButton
                        v-if="akses.bisa_kelola"
                        variant="ghost"
                        size="xs"
                        leading-icon="key"
                        label="Beri Akses"
                        @click="dialogHalaman = true"
                    />
                </div>

                <HcisEmptyState
                    v-if="!akses.grant_langsung.length"
                    icon="key"
                    title="Tidak Ada Akses Langsung"
                    description="Pengguna ini belum memiliki akses langsung ke halaman khusus di luar perannya."
                    size="sm"
                />
                <HcisTable v-else :columns="kolomGrant" :rows="akses.grant_langsung" row-key="jenis_page" size="sm">
                    <template #cell-nama_menu="{ row }">
                        <div class="fw-semibold small">{{ row.nama_menu }}</div>
                        <div class="text-muted font-monospace ap-kecil">{{ row.jenis_page }}</div>
                    </template>
                    <template #cell-alasan="{ row }">
                        <span class="small">{{ row.alasan || '—' }}</span>
                    </template>
                    <template #cell-berlaku_sampai="{ row }">
                        <span class="small">{{
                            row.berlaku_sampai ? tanggal(row.berlaku_sampai) : 'Tanpa batas'
                        }}</span>
                        <HcisBadge
                            v-if="row.kedaluwarsa"
                            label="Kedaluwarsa"
                            variant="danger"
                            appearance="subtle"
                            size="xs"
                            class="ms-1"
                        />
                    </template>
                    <template #cell-aksi="{ row }">
                        <HcisButton
                            v-if="akses.bisa_kelola"
                            variant="danger-ghost"
                            size="sm"
                            leading-icon="trash"
                            label="Cabut"
                            @click="bukaCabutGrant(row)"
                        />
                    </template>
                </HcisTable>
            </div>

            <!-- Sub 3: Halaman yang Bisa Dibuka (Efektif + Penelusuran Izin) -->
            <div v-else class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold small">Halaman yang Efektif Bisa Dibuka</div>
                        <div class="text-muted ap-kecil">
                            Hasil gabungan seluruh peran dan akses langsung. Klik halaman untuk menelusuri izinnya.
                        </div>
                    </div>
                    <HcisField
                        id="cari-halaman-bisa"
                        v-model="cariHalaman"
                        placeholder="Saring halaman…"
                        leading-icon="search"
                        clearable
                        size="sm"
                    />
                </div>

                <div class="ap-halaman-list">
                    <div
                        v-for="h in halamanTersaring"
                        :key="h.jenis_page"
                        class="ap-halaman-item mb-2"
                        :class="{ 'is-active': telusur?.halaman === h.jenis_page }"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between p-2 rounded cursor-pointer"
                            @click="telusuri(h.jenis_page)"
                        >
                            <div class="min-w-0">
                                <div class="fw-semibold small text-truncate">{{ h.nama_menu }}</div>
                                <div class="text-muted ap-kecil">{{ h.header || h.jenis_page }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="d-flex flex-wrap gap-1">
                                    <HcisBadge
                                        v-for="a in h.aksi"
                                        :key="a"
                                        :label="a"
                                        variant="brand"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    :leading-icon="telusur?.halaman === h.jenis_page ? 'chevron-up' : 'info-circle'"
                                    :label="telusur?.halaman === h.jenis_page ? 'Tutup' : 'Telusuri'"
                                />
                            </div>
                        </div>

                        <!-- Kartu Penelusuran Izin Visual -->
                        <div v-if="telusur?.halaman === h.jenis_page" class="ap-telusur-box p-3 my-2">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisBadge
                                        :label="telusur.boleh ? 'Dapat Dibuka' : 'Tidak Memiliki Akses'"
                                        :variant="telusur.boleh ? 'success' : 'danger'"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                    <HcisBadge
                                        v-if="infoMode[telusur.mode]"
                                        :label="`Aturan: ${infoMode[telusur.mode].label}`"
                                        :variant="infoMode[telusur.mode].varian"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <span class="text-muted ap-kecil font-monospace">{{ h.jenis_page }}</span>
                            </div>

                            <div class="ap-telusur-content">
                                <div class="small fw-semibold mb-1">Jalur Pemberian Izin:</div>
                                <div v-if="telusur.jalur && telusur.jalur.length" class="d-flex flex-wrap gap-1 mb-2">
                                    <HcisBadge
                                        v-for="j in telusur.jalur"
                                        :key="j"
                                        :label="teksJalur(j)"
                                        variant="brand"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <div v-else class="text-muted ap-kecil mb-2">
                                    {{
                                        telusur.boleh
                                            ? 'Dibuka lewat jalur di luar modul Anda.'
                                            : 'Tidak ada peran atau grant yang membuka halaman ini.'
                                    }}
                                </div>

                                <div v-if="telusur.jalur_baru !== undefined" class="border-top pt-2 mt-2">
                                    <div class="small fw-semibold mb-1">Status Aturan Baru (Masa Depan):</div>
                                    <div v-if="telusur.jalur_baru.length" class="d-flex flex-wrap gap-1">
                                        <HcisBadge
                                            v-for="jb in telusur.jalur_baru"
                                            :key="jb"
                                            :label="teksJalur(jb)"
                                            variant="success"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                    <div v-else class="text-muted ap-kecil">
                                        Tertutup (Halaman ini tidak terbuka di bawah aturan baru).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <HcisEmptyState
                        v-if="!halamanTersaring.length"
                        icon="search"
                        title="Tidak Ada Halaman yang Cocok"
                        size="sm"
                    />
                </div>
            </div>
        </div>

        <DialogBeriHalaman v-if="akses" v-model="dialogHalaman" :akses="akses" @selesai="muatDanKabari" />
        <DialogBatas v-model="batas.buka" :baris="batas.baris" @selesai="muatDanKabari" />
        <DialogAlasan
            v-model="cabut.buka"
            :judul="cabut.judul"
            :keterangan="cabut.keterangan"
            :aksi="cabut.aksi"
            @selesai="muatDanKabari"
        />
    </div>

    <!-- ══ 2. DRAWER MODE (FALLBACK) ══ -->
    <HcisDrawer
        v-else
        :model-value="modelValue"
        direction="rtl"
        :size="lebarLaci"
        :title="akses?.nama || 'Rincian Akses Karyawan'"
        :description="akses ? `${akses.kode_karyawan || '—'}${akses.system_admin ? ' · System Admin' : ''}` : ''"
        icon="bi bi-person-badge"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <HcisSkeleton v-if="memuat" :lines="8" />
        <div v-else-if="akses">
            <!-- Profil Karyawan & Aksi Cepat -->
            <div class="ap-profil-card p-3 mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <HcisAvatar :name="akses.nama" size="lg" />
                        <div class="min-w-0">
                            <div class="fw-bold fs-6 text-truncate text-dark">{{ akses.nama }}</div>
                            <div class="text-muted small">
                                NIK: {{ akses.kode_karyawan || '—' }} ·
                                {{ akses.system_admin ? 'System Admin' : 'Anggota Modul' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="akses.bisa_kelola" class="d-flex align-items-center gap-2 flex-wrap">
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="person-plus"
                            label="Tugaskan Peran"
                            @click="aksi.tugaskan({ idUser: akses.id_user, setelah: muat })"
                        />
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="key"
                            label="Beri Akses Halaman"
                            @click="dialogHalaman = true"
                        />
                    </div>
                </div>

                <HcisAlert
                    v-if="!akses.bisa_kelola"
                    variant="neutral"
                    appearance="subtle"
                    class="mt-3 mb-0"
                    title="Akses Baca-Saja"
                    description="Akses karyawan ini tidak dapat Anda ubah (di luar modul/cakupan Anda, setingkat atau di atas wewenang Anda, atau diri sendiri)."
                />
            </div>

            <HcisTabs v-model="sub" :tabs="daftarSub" variant="segmented" size="sm" class="mb-3" />

            <div v-if="sub === 'peran'" class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Daftar peran modul yang aktif dipegang</span>
                    <HcisButton
                        v-if="akses.bisa_kelola"
                        variant="ghost"
                        size="xs"
                        leading-icon="plus-lg"
                        label="Tambah Peran"
                        @click="aksi.tugaskan({ idUser: akses.id_user, setelah: muat })"
                    />
                </div>
                <TabelPenugasan
                    :rows="akses.peran"
                    :bisa-aksi="akses.bisa_kelola"
                    @cabut="bukaCabutPeran"
                    @batas="bukaBatas"
                />
            </div>

            <div v-else-if="sub === 'grant'" class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold small">Akses Langsung Halaman (Dispensasi)</div>
                        <div class="text-muted ap-kecil">
                            Memberi izin buka halaman tertentu di luar paket peran. Sebaiknya disertai batas waktu.
                        </div>
                    </div>
                    <HcisButton
                        v-if="akses.bisa_kelola"
                        variant="ghost"
                        size="xs"
                        leading-icon="key"
                        label="Beri Akses"
                        @click="dialogHalaman = true"
                    />
                </div>

                <HcisEmptyState
                    v-if="!akses.grant_langsung.length"
                    icon="key"
                    title="Tidak Ada Akses Langsung"
                    size="sm"
                />
                <HcisTable v-else :columns="kolomGrant" :rows="akses.grant_langsung" row-key="jenis_page" size="sm">
                    <template #cell-nama_menu="{ row }">
                        <div class="fw-semibold small">{{ row.nama_menu }}</div>
                        <div class="text-muted font-monospace ap-kecil">{{ row.jenis_page }}</div>
                    </template>
                    <template #cell-alasan="{ row }">
                        <span class="small">{{ row.alasan || '—' }}</span>
                    </template>
                    <template #cell-berlaku_sampai="{ row }">
                        <span class="small">{{
                            row.berlaku_sampai ? tanggal(row.berlaku_sampai) : 'Tanpa batas'
                        }}</span>
                    </template>
                    <template #cell-aksi="{ row }">
                        <HcisButton
                            v-if="akses.bisa_kelola"
                            variant="danger-ghost"
                            size="sm"
                            leading-icon="trash"
                            label="Cabut"
                            @click="bukaCabutGrant(row)"
                        />
                    </template>
                </HcisTable>
            </div>

            <div v-else class="ap-tab-content">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold small">Halaman yang Efektif Bisa Dibuka</div>
                        <div class="text-muted ap-kecil">Hasil gabungan seluruh peran dan akses langsung.</div>
                    </div>
                    <HcisField
                        id="cari-halaman-bisa-drawer"
                        v-model="cariHalaman"
                        placeholder="Saring halaman…"
                        leading-icon="search"
                        clearable
                        size="sm"
                    />
                </div>
                <div class="ap-halaman-list">
                    <div
                        v-for="h in halamanTersaring"
                        :key="h.jenis_page"
                        class="ap-halaman-item mb-2"
                        :class="{ 'is-active': telusur?.halaman === h.jenis_page }"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between p-2 rounded cursor-pointer"
                            @click="telusuri(h.jenis_page)"
                        >
                            <div class="min-w-0">
                                <div class="fw-semibold small text-truncate">{{ h.nama_menu }}</div>
                                <div class="text-muted ap-kecil">{{ h.header || h.jenis_page }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <HcisBadge
                                    v-for="a in h.aksi"
                                    :key="a"
                                    :label="a"
                                    variant="brand"
                                    appearance="subtle"
                                    size="xs"
                                />
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    :leading-icon="telusur?.halaman === h.jenis_page ? 'chevron-up' : 'info-circle'"
                                    :label="telusur?.halaman === h.jenis_page ? 'Tutup' : 'Telusuri'"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DialogBeriHalaman v-if="akses" v-model="dialogHalaman" :akses="akses" @selesai="muatDanKabari" />
        <DialogBatas v-model="batas.buka" :baris="batas.baris" @selesai="muatDanKabari" />
        <DialogAlasan
            v-model="cabut.buka"
            :judul="cabut.judul"
            :keterangan="cabut.keterangan"
            :aksi="cabut.aksi"
            @selesai="muatDanKabari"
        />
    </HcisDrawer>
</template>

<script setup>
import { computed, inject, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDrawer,
    HcisEmptyState,
    HcisField,
    HcisSkeleton,
    HcisTable,
    HcisTabs,
    hcisMessage,
} from '@/components/ui';
import HcisAvatar from '@/components/ui/HcisAvatar.vue';
import TabelPenugasan from './TabelPenugasan.vue';
import DialogBeriHalaman from './DialogBeriHalaman.vue';
import DialogAlasan from './DialogAlasan.vue';
import DialogBatas from './DialogBatas.vue';
import { aksesPeranApi, infoMode, pesanGalat, tanggal } from '../api/aksesPeranApi.js';
import { useLebarLaci } from '../api/useLebarLaci.js';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    idUser: { type: Number, default: null },
    embedded: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'berubah']);
const aksi = inject('aksiAkses');
const lebarLaci = useLebarLaci('46rem');

const akses = ref(null);
const memuat = ref(false);
const sub = ref('peran');
const telusur = ref(null);
const cariHalaman = ref('');
const dialogHalaman = ref(false);
const batas = reactive({ buka: false, baris: null });
const cabut = reactive({ buka: false, judul: '', keterangan: '', aksi: async () => {} });

const daftarSub = computed(() => [
    { key: 'peran', label: 'Peran yang Dipegang', icon: 'person-badge', badge: akses.value?.peran.length || 0 },
    { key: 'grant', label: 'Akses Langsung', icon: 'key', badge: akses.value?.grant_langsung.length || 0 },
    {
        key: 'halaman',
        label: 'Halaman Efektif',
        icon: 'window-stack',
        badge: akses.value?.halaman_efektif.length || 0,
    },
]);

const halamanTersaring = computed(() => {
    const q = cariHalaman.value.trim().toLowerCase();
    return (akses.value?.halaman_efektif || []).filter(
        (h) => !q || `${h.nama_menu} ${h.jenis_page} ${h.header}`.toLowerCase().includes(q),
    );
});

const kolomGrant = [
    { key: 'nama_menu', label: 'Halaman' },
    { key: 'alasan', label: 'Alasan' },
    { key: 'berlaku_sampai', label: 'Berlaku sampai' },
    { key: 'aksi', label: '', align: 'right' },
];

watch(
    () => [props.modelValue, props.idUser, props.embedded],
    ([buka, id, emb]) => {
        if ((buka || emb) && id) {
            sub.value = 'peran';
            cariHalaman.value = '';
            muat();
        }
    },
    { immediate: true },
);

async function muat() {
    if (!props.idUser) return;
    memuat.value = !akses.value || akses.value.id_user !== props.idUser;
    telusur.value = null;
    try {
        akses.value = await aksesPeranApi.detailPengguna(props.idUser);
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal memuat akses karyawan.'));
    } finally {
        memuat.value = false;
    }
}

async function muatDanKabari() {
    await muat();
    emit('berubah');
}

function bukaCabutPeran(row) {
    Object.assign(cabut, {
        buka: true,
        judul: `Cabut peran ${row.nama_peran}`,
        keterangan: `Hak peran ini akan dihapus dari ${akses.value.nama}.`,
        aksi: (alasan) => aksesPeranApi.cabutPenugasan(row.id_penugasan, alasan),
    });
}

function bukaCabutGrant(row) {
    Object.assign(cabut, {
        buka: true,
        judul: `Cabut akses ${row.nama_menu}`,
        keterangan: `Akses langsung ini akan dihapus dari ${akses.value.nama}.`,
        aksi: (alasan) =>
            aksesPeranApi.cabutGrant({ id_user: akses.value.id_user, jenis_page: row.jenis_page, alasan }),
    });
}

function bukaBatas(row) {
    Object.assign(batas, { buka: true, baris: row });
}

async function telusuri(halaman) {
    if (telusur.value?.halaman === halaman) {
        telusur.value = null;
        return;
    }
    try {
        const r = await aksesPeranApi.telusuri(akses.value.id_user, halaman);
        telusur.value = { halaman, ...r };
    } catch {
        hcisMessage.error('Gagal menelusuri izin.');
    }
}

function teksJalur(j) {
    if (j.tipe === 'GRANT')
        return `Akses langsung (dispensasi)${j.berlaku_sampai ? ` s.d. ${tanggal(j.berlaku_sampai)}` : ''}`;
    if (j.tipe === 'ROLE') return `Peran: ${j.kode} (${j.nama_peran || ''})`;
    return j.kode || j.tipe;
}
</script>

<style scoped>
.ap-kecil {
    font-size: var(--hcis-font-size-xs, 0.75rem);
}

.ap-panel-embedded {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.ap-workspace {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.ap-profil-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.98));
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 0.75rem);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.ap-halaman-item {
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-surface, #ffffff);
    transition: all 0.15s ease;
}

.ap-halaman-item:hover {
    border-color: var(--hcis-brand-300, #7dd3fc);
    background: #f8fafc;
}

.ap-halaman-item.is-active {
    border-color: var(--hcis-brand-500, #0284c7);
}

.ap-telusur-box {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: var(--hcis-radius-md, 0.5rem);
}

.cursor-pointer {
    cursor: pointer;
}

.min-w-0 {
    min-width: 0;
}
</style>
