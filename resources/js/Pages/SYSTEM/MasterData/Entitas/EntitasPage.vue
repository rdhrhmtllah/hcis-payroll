<template>
    <div class="container-fluid px-0 entitas-page">
        <HcisHeader
            title="Master Entitas Perusahaan"
            kicker="System · Master Data"
            description="Daftar badan hukum dalam grup. Dipakai lintas modul sebagai pemilik dan ruang lingkup dokumen."
            icon="buildings-fill"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge v-if="!loading" :label="`${pagination.total} Entitas`" variant="brand" appearance="glass" size="sm" dot />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        label="Segarkan"
                        @click="fetchData(pagination.current_page)"
                    />
                    <HcisButton variant="primary" size="md" leading-icon="plus-lg" label="Tambah Entitas" @click="openModal()" />
                </div>
            </template>
        </HcisHeader>

        <HcisAlert
            variant="info"
            appearance="subtle"
            class="mb-4"
            title="Halaman khusus System Admin"
            description="Master ini dipakai lintas modul, jadi perubahannya berdampak ke seluruh dokumen yang menunjuknya. Kode entitas terkunci setelah dibuat karena ia rujukan tetap bagi seed dan integrasi."
        />

        <HcisTabs v-model="tabAktif" :tabs="daftarTab" variant="underline" class="mb-3" />

        <!-- Struktur grup: gambar dari kolom Induk_Entitas_Id yang sama dengan
             yang dipakai cakupan(), jadi gambar dan angka tak bisa menyimpang.

             `node-actions` sengaja kosong: popover simpul di sini hanya
             menyajikan keterangan, dan tombol yang tak melakukan apa pun saat
             diklik lebih buruk daripada tidak ada tombol sama sekali. -->
        <HcisOrgChart
            v-if="tabAktif === 'struktur' && pohon && !loading"
            :data="pohon"
            :node-actions="[]"
            title="Struktur Grup"
            subtitle="Entitas berinduk dianggap tercakup oleh induknya"
            icon="diagram-3"
            variant="card"
            class="mb-5"
        />

        <HcisEmptyState
            v-else-if="tabAktif === 'struktur' && !loading"
            icon="diagram-3"
            title="Struktur Belum Bisa Digambar"
            description="Struktur grup dibangun dari kolom induk antar entitas. Isi induknya lebih dulu di tab Daftar Entitas."
            variant="flat"
            sentiment="neutral"
            class="mb-5"
        />

        <HcisCard v-if="tabAktif === 'daftar'" variant="glass" sentiment="brand" size="lg" class="mb-5">
            <template #header>
                <div class="d-flex justify-content-between align-items-center w-100 gap-3">
                    <div>
                        <h2 class="hcis-card__title">Daftar Entitas</h2>
                        <p class="hcis-card__description">
                            Entitas berinduk dianggap tercakup oleh induknya — itulah cara "seluruh grup" dihitung.
                        </p>
                    </div>
                    <div style="max-width: 18rem; width: 100%">
                        <HcisField
                            id="ent-cari"
                            :model-value="searchQuery"
                            type="text"
                            placeholder="Cari nama atau kode…"
                            clearable
                            @update:model-value="onSearchChange"
                        />
                    </div>
                </div>
            </template>

            <HcisResultState
                v-if="errorState"
                sentiment="danger"
                size="md"
                title="Gagal Memuat Entitas"
                :description="errorMessage || 'Terjadi gangguan saat memuat data.'"
            >
                <template #actions>
                    <HcisButton variant="primary" size="md" leading-icon="arrow-clockwise" label="Coba Lagi" @click="fetchData(pagination.current_page)" />
                </template>
            </HcisResultState>

            <div v-else-if="loading" class="p-3">
                <div v-for="n in 4" :key="`skel-${n}`" class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <HcisSkeleton width="56px" height="28px" rounded="md" />
                    <HcisSkeleton width="26%" height="1rem" rounded="sm" />
                    <HcisSkeleton width="20%" height="0.85rem" rounded="sm" />
                    <HcisSkeleton width="80px" height="22px" rounded="pill" />
                </div>
            </div>

            <div v-else-if="listData.length > 0" class="ent-table-wrap">
                <HcisTable :columns="tableColumns" :rows="listData" hoverable effective-size="sm" sentiment="brand">
                    <template #cell-Nama_Entitas="{ row }">
                        <div class="d-flex align-items-center gap-2 py-1">
                            <img v-if="row.Logo_Url" :src="row.Logo_Url" class="ent-logo" :alt="row.Nama_Entitas" />
                            <span v-else class="ent-badge" :style="{ '--ec': row.Warna || '#64748b' }">
                                {{ (row.Nama_Singkat || row.Kode_Entitas).toUpperCase() }}
                            </span>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem">{{ row.Nama_Entitas }}</div>
                                <div class="text-muted small font-monospace" style="font-size: 0.72rem">{{ row.Kode_Entitas }}</div>
                            </div>
                        </div>
                    </template>

                    <template #cell-Induk_Kode="{ row }">
                        <span v-if="row.Induk_Kode" class="text-secondary" style="font-size: 0.8125rem">
                            <i class="bi bi-diagram-2 me-1"></i>{{ row.Induk_Kode }}
                        </span>
                        <HcisBadge v-else label="Payung" variant="brand" appearance="subtle" size="xs" />
                    </template>

                    <template #cell-Warna="{ row }">
                        <span v-if="row.Warna" class="font-monospace" style="font-size: 0.75rem">{{ row.Warna }}</span>
                        <span v-else class="text-warning small" title="Badge akan memakai warna hasil hash, bukan warna resmi">
                            <i class="bi bi-exclamation-triangle me-1"></i>belum diisi
                        </span>
                    </template>

                    <template #cell-Jumlah_Dokumen="{ row }">
                        <span class="fw-semibold">{{ row.Jumlah_Dokumen }}</span>
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <HcisButton variant="ghost" size="xs" label="Ubah" leading-icon="pencil-square" @click.stop="openModal(row)" />
                            <HcisButton
                                variant="danger-quiet"
                                size="xs"
                                label="Nonaktifkan"
                                leading-icon="slash-circle"
                                :disabled="row.Jumlah_Dokumen > 0"
                                :title="row.Jumlah_Dokumen > 0 ? `Masih dipakai ${row.Jumlah_Dokumen} dokumen` : 'Nonaktifkan entitas ini'"
                                @click.stop="confirmHapus(row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>

            <div v-else class="p-4">
                <HcisEmptyState
                    icon="buildings"
                    title="Belum Ada Entitas"
                    description="Tanpa satu pun entitas, dokumen tak bisa menyatakan milik badan hukum mana ia berada."
                    variant="flat"
                    sentiment="neutral"
                >
                    <template #actions>
                        <HcisButton variant="primary" size="md" leading-icon="plus-lg" label="Tambah Entitas Pertama" @click="openModal()" />
                    </template>
                </HcisEmptyState>
            </div>

            <template v-if="pagination.total > 0 && !loading && !errorState" #footer>
                <HcisPagination
                    :current-page="pagination.current_page"
                    :total="pagination.total"
                    :page-size="pagination.per_page"
                    :total-pages="pagination.total_pages"
                    :disabled="loading"
                    layout="total, prev, pager, next"
                    @update:current-page="fetchData"
                />
            </template>
        </HcisCard>

        <EntitasModal
            :show="showModal"
            :item="activeItem"
            :saving="saving"
            :opsi="opsi"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisHeader,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisField,
    HcisTable,
    HcisPagination,
    HcisSkeleton,
    HcisEmptyState,
    HcisResultState,
    HcisAlert,
    HcisOrgChart,
    HcisTabs,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import EntitasModal from './Components/EntitasModal.vue';
import { entitasApi } from './api/entitasApi.js';

defineOptions({ name: 'EntitasPage' });

const listData = ref([]);
const opsi = ref([]);
const pohon = ref(null);

/* Dua sumbu yang berbeda dipisah jadi tab, bukan ditumpuk: daftar dipakai untuk
   menyunting satu per satu, struktur untuk membaca hubungannya. Menumpuk
   keduanya mendorong daftar jauh ke bawah tanpa ada yang membutuhkannya di sana. */
const tabAktif = ref('daftar');
const searchQuery = ref('');
const loading = ref(false);
const saving = ref(false);
const errorState = ref(false);
const errorMessage = ref('');
const showModal = ref(false);
const activeItem = ref(null);

const pagination = reactive({ current_page: 1, per_page: 25, total: 0, total_pages: 1 });

let searchTimeout = null;

const daftarTab = computed(() => [
    { key: 'daftar', label: 'Daftar Entitas', icon: 'list-ul', badge: pagination.total || 0 },
    { key: 'struktur', label: 'Struktur Grup', icon: 'diagram-3' },
]);

const tableColumns = [
    { key: 'Nama_Entitas', label: 'Entitas', width: '34%' },
    { key: 'Induk_Kode', label: 'Induk', width: '16%' },
    { key: 'Warna', label: 'Warna', width: '14%' },
    { key: 'Jumlah_Dokumen', label: 'Dipakai', align: 'center', width: '12%' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '24%' },
];

const fetchData = async (page = 1) => {
    if (page < 1) return;
    loading.value = true;
    errorState.value = false;

    try {
        const res = await entitasApi.list({ page, limit: pagination.per_page, q: searchQuery.value });
        listData.value = res.data || [];
        pagination.current_page = res.page || 1;
        pagination.total_pages = res.total_page || 1;
        pagination.total = res.total_data || 0;
    } catch (err) {
        errorState.value = true;
        errorMessage.value = err.message || 'Gagal terhubung ke server';
        hcisMessage.error(errorMessage.value);
    } finally {
        loading.value = false;
    }
};

const muatOpsi = async () => {
    try {
        opsi.value = await entitasApi.options();
    } catch (err) {
        opsi.value = [];
    }
};

/* Pohon gagal dimuat TIDAK menjatuhkan halaman: daftar entitas tetap berguna
   tanpa gambarnya, dan sebaliknya tidak. */
const muatPohon = async () => {
    try {
        pohon.value = await entitasApi.pohon();
    } catch (err) {
        pohon.value = null;
    }
};

const onSearchChange = (val) => {
    searchQuery.value = val;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchData(1), 400);
};

const openModal = (item = null) => {
    activeItem.value = item ? { ...item } : null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    activeItem.value = null;
};

const handleSubmit = async (payload) => {
    saving.value = true;
    try {
        const body = {
            Kode_Entitas: payload.Kode_Entitas,
            Nama_Entitas: payload.Nama_Entitas,
            Nama_Singkat: payload.Nama_Singkat,
            Warna: payload.Warna,
            Induk_Entitas_Id: payload.Induk_Entitas_Id,
            Urutan: payload.Urutan,
        };

        const res = payload.isEditing
            ? await entitasApi.update(payload.Id, body)
            : await entitasApi.store(body);

        if (res.success) {
            hcisMessage.success(res.message);
            closeModal();
            await Promise.all([fetchData(payload.isEditing ? pagination.current_page : 1), muatOpsi(), muatPohon()]);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menyimpan entitas');
    } finally {
        saving.value = false;
    }
};

const confirmHapus = async (item) => {
    const ok = await hcisConfirm({
        title: 'Nonaktifkan entitas?',
        description: `"${item.Nama_Entitas}" tidak akan lagi bisa dipilih sebagai pemilik maupun ruang lingkup dokumen baru. Dokumen yang sudah memakainya tidak berubah.`,
        confirmText: 'Nonaktifkan',
        sentiment: 'danger',
    });
    if (!ok) return;

    try {
        const res = await entitasApi.destroy(item.Id_Entitas);
        if (res.success) {
            hcisMessage.success(res.message);
            await Promise.all([fetchData(pagination.current_page), muatOpsi(), muatPohon()]);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menonaktifkan entitas');
    }
};

onMounted(() => {
    fetchData(1);
    muatOpsi();
    muatPohon();
});
</script>

<style scoped>
.ent-badge {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 3.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #fff;
    background: var(--ec);
}

.ent-logo {
    flex: 0 0 auto;
    width: 2.5rem;
    height: 2.5rem;
    object-fit: contain;
    border-radius: 0.5rem;
    background: #fff;
    border: 1px solid var(--hcis-border, #e5e7eb);
    padding: 0.15rem;
}

.ent-table-wrap {
    overflow-x: auto;
}
</style>
