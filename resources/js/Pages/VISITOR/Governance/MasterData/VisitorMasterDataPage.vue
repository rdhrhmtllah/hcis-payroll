<template>
    <VisitorWorkspace
        v-model="activeMaster"
        :title="judulHalaman"
        :subtitle="subjudulHalaman"
        kicker="Administrator Visitor · Governance"
        icon="database-fill-gear"
        sentiment="purple"
        :tabs="tabs"
        section-title="Referensi operasional Visitor"
        section-description="Perubahan master langsung memengaruhi pilihan pada transaksi baru. Data historis tidak dihapus."
        section-icon="sliders"
        tabs-aria-label="Kelompok master data Visitor"
    >
        <template v-if="domainExport" #actions>
            <HcisButton variant="outline" icon="file-earmark-arrow-down" @click="exportOpen = true">
                Unduh Template
            </HcisButton>
            <HcisButton variant="outline" icon="file-earmark-arrow-up" @click="importOpen = true">
                Import Excel
            </HcisButton>
        </template>

        <template v-for="master in masterCatalog" :key="master.key" #[`panel-${master.key}`]>
            <VisitorMasterDataPanel
                v-if="activeMaster === master.key"
                ref="panelRef"
                :definition="master"
                :items="state.items"
                :relations="state.relations"
                :loading="state.loading"
                :saving="state.saving"
                :status-changing-id="state.statusChangingId"
                :error="state.error"
                :field-errors="state.fieldErrors"
                :stats="state.stats"
                :pagination="state.pagination"
                @retry="loadMaster"
                @save="saveMaster"
                @change-status="changeStatus"
                @query-change="changeQuery"
            />
        </template>
    </VisitorWorkspace>

    <!--
        Di LUAR VisitorWorkspace: slot bawaannya hanya dipakai sebagai cadangan
        ketika slot panel tidak ada, sehingga dialog yang diletakkan di dalamnya
        tidak pernah ikut dirender selama halaman punya panel — tombolnya hidup,
        tapi tidak ada yang terbuka.
    -->
    <VisitorMasterDataExportDialog
        v-if="domainExport"
        v-model="exportOpen"
        :base-url="apiBaseUrl"
        :domain="domainExport"
        :master-aktif="activeMaster"
        :filters="state.query"
    />

    <VisitorMasterDataImportDialog
        v-if="domainExport"
        v-model="importOpen"
        :base-url="apiBaseUrl"
        :domain="domainExport"
        @selesai="loadMaster"
        @minta-template="bukaTemplate"
    />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import { HcisButton, hcisConfirm, hcisMessage } from '@/components/ui';
import VisitorMasterDataExportDialog from './components/VisitorMasterDataExportDialog.vue';
import VisitorMasterDataImportDialog from './components/VisitorMasterDataImportDialog.vue';
import VisitorMasterDataPanel from './components/VisitorMasterDataPanel.vue';
import { visitorMasterDataApi } from './api/visitorMasterDataApi';

const props = defineProps({
    masterCatalog: { type: Array, required: true },
    apiBaseUrl: { type: String, default: '/api/v1/visitor/master-data' },
    /*
     * Judul mengikuti domain halamannya (DML-2026-107). Katalognya sendiri
     * sudah disaring di server: mengirimnya utuh lalu menyembunyikan sebagian
     * di layar tetap memberi tahu halaman tamu seluruh bentuk master permit,
     * dan tab yang terlewat disembunyikan akan memanggil endpoint yang
     * menolaknya.
     */
    judulHalaman: { type: String, default: 'Master Data Visitor' },
    subjudulHalaman: {
        type: String,
        default: 'Kelola referensi operasional yang digunakan oleh Permit, Tamu, dan Verifikator Visitor.',
    },
    /*
     * Domain export datang dari server; kosong berarti halaman ini belum punya
     * endpoint export. Menyimpulkannya di layar hanya akan memunculkan tombol
     * yang berakhir 404 di halaman berikutnya yang memakai komponen ini.
     */
    domainExport: { type: String, default: '' },
});

const exportOpen = ref(false);
const importOpen = ref(false);

/*
 * Templat import = berkas export. Satu tombol menutup yang lain supaya dua
 * dialog tidak bertumpuk dan fokus keyboard tidak tertinggal di dialog yang
 * tertutup di belakangnya.
 */
function bukaTemplate() {
    importOpen.value = false;
    exportOpen.value = true;
}

// Master pertama pada katalog yang SUDAH tersaring. Nilai cadangan yang
// dipaku ('approval-question') akan membuka tab milik domain lain pada halaman
// tamu — tab yang tidak punya panel dan endpointnya menolak.
const activeMaster = ref(props.masterCatalog[0]?.key || '');
const panelRef = ref(null);
let requestSequence = 0;

const state = reactive({
    loading: false,
    saving: false,
    statusChangingId: null,
    items: [],
    relations: {},
    summary: { total: 0, active: 0, inactive: 0 },
    stats: [],
    query: { page: 1, limit: 10, search: '' },
    pagination: { page: 1, pageSize: 10, total: 0, totalPages: 1 },
    error: null,
    fieldErrors: {},
});

const activeDefinition = computed(() => props.masterCatalog.find((item) => item.key === activeMaster.value) || props.masterCatalog[0]);
const tabs = computed(() => props.masterCatalog.map((item) => ({ key: item.key, label: item.short_label, icon: item.icon })));
watch(activeMaster, () => {
    state.query = { page: 1, limit: 10, search: '' };
    loadMaster();
}, { immediate: true });

async function loadMaster() {
    const sequence = ++requestSequence;
    state.loading = true;
    state.error = null;
    state.fieldErrors = {};
    state.stats = [];

    try {
        const response = await visitorMasterDataApi.list(props.apiBaseUrl, activeMaster.value, state.query);
        if (sequence !== requestSequence) return;
        state.items = response.data?.items || [];
        state.relations = response.data?.relations || {};
        state.summary = response.data?.summary || { total: 0, active: 0, inactive: 0 };
        state.stats = response.data?.stats || [];
        const pagination = response.data?.pagination || {};
        state.pagination = {
            page: pagination.current_page || state.query.page,
            pageSize: pagination.per_page || state.query.limit,
            total: pagination.total || 0,
            totalPages: pagination.total_pages || 1,
        };
    } catch (error) {
        if (sequence !== requestSequence) return;
        state.error = error;
        state.items = [];
    } finally {
        if (sequence === requestSequence) state.loading = false;
    }
}

function changeQuery(query) {
    state.query = { ...query };
    loadMaster();
}

async function saveMaster({ id, payload }) {
    if (state.saving) return;
    state.saving = true;
    state.fieldErrors = {};

    try {
        const response = id
            ? await visitorMasterDataApi.update(props.apiBaseUrl, activeMaster.value, id, payload)
            : await visitorMasterDataApi.create(props.apiBaseUrl, activeMaster.value, payload);
        closeActiveEditor();
        hcisMessage.success(response.message || 'Master data berhasil disimpan.');
        await loadMaster();
    } catch (error) {
        state.fieldErrors = error.errors || {};
        hcisMessage.error(error.message);
    } finally {
        state.saving = false;
    }
}

function closeActiveEditor() {
    const panel = Array.isArray(panelRef.value)
        ? panelRef.value.find(Boolean)
        : panelRef.value;

    panel?.closeEditor?.();
}

async function changeStatus(item) {
    if (state.saving) return;
    const nextStatus = item.Flag_Aktif === 'Y' ? 'T' : 'Y';
    const itemId = item[activeDefinition.value.id_key];
    const confirmed = await hcisConfirm({
        title: nextStatus === 'Y' ? 'Aktifkan master data?' : 'Nonaktifkan master data?',
        description: nextStatus === 'Y'
            ? 'Data akan kembali tersedia pada transaksi baru.'
            : 'Data historis tetap tersimpan, tetapi pilihan ini tidak tersedia pada transaksi baru.',
        kicker: activeDefinition.value?.label || 'Master Data Visitor',
        sentiment: nextStatus === 'Y' ? 'success' : 'warning',
        confirmText: nextStatus === 'Y' ? 'Aktifkan' : 'Nonaktifkan',
        cancelText: 'Batal',
    });
    if (!confirmed) return;

    state.saving = true;
    state.statusChangingId = itemId;
    try {
        const response = await visitorMasterDataApi.changeStatus(
            props.apiBaseUrl,
            activeMaster.value,
            itemId,
            nextStatus,
        );
        hcisMessage.success(response.message);
        await loadMaster();
    } catch (error) {
        hcisMessage.error(error.message);
    } finally {
        state.saving = false;
        state.statusChangingId = null;
    }
}
</script>
