<template>
    <div class="container-fluid px-0 departemen-page">
        <HcisHeader
            title="Master Kode Departemen"
            kicker="System · Master Data"
            description="Kode departemen pemilik dokumen. Kodenya menjadi salah satu segmen nomor dokumen SOP."
            icon="diagram-2-fill"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge v-if="!loading" :label="`${pagination.total} Departemen`" variant="brand" appearance="glass" size="sm" dot />
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
                    <HcisButton variant="primary" size="md" leading-icon="plus-lg" label="Tambah Departemen" @click="openModal()" />
                </div>
            </template>
        </HcisHeader>

        <HcisAlert
            variant="info"
            appearance="subtle"
            class="mb-4"
            title="Halaman khusus System Admin"
            description="Kode di sini tercetak sebagai segmen nomor dokumen (Jenis/Entitas/Departemen/Lingkup/APQC/Nomor/Revisi), jadi kode terkunci setelah dibuat. Binding ke sub-divisi HRIS bersifat opsional — ia hanya menyambungkan dokumen ke data karyawan."
        />

        <HcisCard variant="glass" sentiment="brand" size="lg" class="mb-5">
            <template #header>
                <div class="d-flex justify-content-between align-items-center w-100 gap-3">
                    <div>
                        <h2 class="hcis-card__title">Daftar Kode Departemen</h2>
                        <p class="hcis-card__description">
                            Daftar kode perusahaan lebih rinci daripada struktur HRIS yang tersimpan, jadi tidak semua kode punya pasangan.
                        </p>
                    </div>
                    <div style="max-width: 18rem; width: 100%">
                        <HcisField
                            id="dep-cari"
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
                title="Gagal Memuat Departemen"
                :description="errorMessage || 'Terjadi gangguan saat memuat data.'"
            >
                <template #actions>
                    <HcisButton variant="primary" size="md" leading-icon="arrow-clockwise" label="Coba Lagi" @click="fetchData(pagination.current_page)" />
                </template>
            </HcisResultState>

            <div v-else-if="loading" class="p-3">
                <div v-for="n in 5" :key="`skel-${n}`" class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <HcisSkeleton width="56px" height="28px" rounded="md" />
                    <HcisSkeleton width="28%" height="1rem" rounded="sm" />
                    <HcisSkeleton width="22%" height="0.85rem" rounded="sm" />
                    <HcisSkeleton width="80px" height="22px" rounded="pill" />
                </div>
            </div>

            <div v-else-if="listData.length > 0" class="dep-table-wrap">
                <HcisTable :columns="tableColumns" :rows="listData" hoverable effective-size="sm" sentiment="brand">
                    <template #cell-Nama_Departemen="{ row }">
                        <div class="d-flex align-items-center gap-2 py-1">
                            <span class="dep-kode font-monospace">{{ row.Kode_Departemen }}</span>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem">{{ row.Nama_Departemen }}</div>
                            </div>
                        </div>
                    </template>

                    <!-- Binding kosong ditulis apa adanya, bukan disamarkan jadi
                         tanda hubung: ia keadaan yang butuh keputusan manusia,
                         dan menyembunyikannya membuatnya tak pernah dikerjakan. -->
                    <template #cell-Sub_Divisi_Nama="{ row }">
                        <span v-if="row.Sub_Divisi_Nama" class="text-secondary" style="font-size: 0.8125rem">
                            <i class="bi bi-link-45deg me-1"></i>{{ row.Sub_Divisi_Nama }}
                        </span>
                        <HcisBadge v-else label="Belum terikat" variant="warning" appearance="subtle" size="xs" />
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
                                :title="row.Jumlah_Dokumen > 0 ? `Masih dipakai ${row.Jumlah_Dokumen} dokumen` : 'Nonaktifkan departemen ini'"
                                @click.stop="confirmHapus(row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>

            <div v-else class="p-4">
                <HcisEmptyState
                    icon="diagram-2"
                    title="Belum Ada Kode Departemen"
                    description="Tanpa satu pun kode, nomor dokumen tak punya segmen departemen dan dokumen tak bisa menyatakan siapa penanggung jawabnya."
                    variant="flat"
                    sentiment="neutral"
                >
                    <template #actions>
                        <HcisButton variant="primary" size="md" leading-icon="plus-lg" label="Tambah Departemen Pertama" @click="openModal()" />
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

        <DepartemenModal
            :show="showModal"
            :item="activeItem"
            :saving="saving"
            :opsi="opsiSubDivisi"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
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
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import DepartemenModal from './Components/DepartemenModal.vue';
import { departemenApi } from './api/departemenApi.js';

defineOptions({ name: 'DepartemenPage' });

const listData = ref([]);
const opsiSubDivisi = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const saving = ref(false);
const errorState = ref(false);
const errorMessage = ref('');
const showModal = ref(false);
const activeItem = ref(null);

const pagination = reactive({ current_page: 1, per_page: 25, total: 0, total_pages: 1 });

let searchTimeout = null;

const tableColumns = [
    { key: 'Nama_Departemen', label: 'Departemen', width: '38%' },
    { key: 'Sub_Divisi_Nama', label: 'Terikat Sub-Divisi', width: '26%' },
    { key: 'Jumlah_Dokumen', label: 'Dipakai', align: 'center', width: '12%' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '24%' },
];

const fetchData = async (page = 1) => {
    if (page < 1) return;
    loading.value = true;
    errorState.value = false;

    try {
        const res = await departemenApi.list({ page, limit: pagination.per_page, q: searchQuery.value });
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

/* Kandidat binding gagal dimuat TIDAK menjatuhkan halaman: daftar kode tetap
   bisa dibaca dan disunting namanya tanpa daftar sub-divisi. */
const muatOpsi = async () => {
    try {
        opsiSubDivisi.value = await departemenApi.subDivisi();
    } catch (err) {
        opsiSubDivisi.value = [];
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
            Kode_Departemen: payload.Kode_Departemen,
            Nama_Departemen: payload.Nama_Departemen,
            Sub_Divisi_Id: payload.Sub_Divisi_Id,
            Urutan: payload.Urutan,
        };

        const res = payload.isEditing
            ? await departemenApi.update(payload.Id, body)
            : await departemenApi.store(body);

        if (res.success) {
            hcisMessage.success(res.message);
            closeModal();
            await fetchData(payload.isEditing ? pagination.current_page : 1);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menyimpan departemen');
    } finally {
        saving.value = false;
    }
};

const confirmHapus = async (item) => {
    const ok = await hcisConfirm({
        title: 'Nonaktifkan departemen?',
        description: `"${item.Kode_Departemen} — ${item.Nama_Departemen}" tidak akan lagi bisa dipilih sebagai pemilik dokumen baru. Dokumen yang sudah memakainya tidak berubah, termasuk nomor yang sudah tercetak.`,
        confirmText: 'Nonaktifkan',
        sentiment: 'danger',
    });
    if (!ok) return;

    try {
        const res = await departemenApi.destroy(item.Id_Departemen);
        if (res.success) {
            hcisMessage.success(res.message);
            await fetchData(pagination.current_page);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menonaktifkan departemen');
    }
};

onMounted(() => {
    fetchData(1);
    muatOpsi();
});
</script>

<style scoped>
.dep-kode {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 3.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #fff;
    background: var(--hcis-slate-700);
}

.dep-table-wrap {
    overflow-x: auto;
}
</style>
