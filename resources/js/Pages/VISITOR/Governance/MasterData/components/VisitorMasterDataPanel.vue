<template>
    <section class="visitor-master-panel" :aria-labelledby="`${definition.key}-title`">
        <!-- Error State Banner -->
        <div v-if="error" class="visitor-master-error" role="alert">
            <div class="d-flex align-items-start gap-2.5">
                <HcisIcon name="exclamation-triangle-fill" size="sm" class="text-danger flex-shrink-0 mt-0.5" />
                <div>
                    <strong class="text-sm">Data belum dapat dimuat</strong>
                    <p class="text-xs mb-0 mt-0.5">{{ error.message }}</p>
                    <small v-if="error.requestId" class="text-muted d-block mt-1 font-mono">Referensi: {{ error.requestId }}</small>
                </div>
            </div>
            <HcisButton variant="secondary" size="sm" leading-icon="arrow-clockwise" @click="$emit('retry')">
                Coba lagi
            </HcisButton>
        </div>

        <template v-else>
            <!-- Dedicated Master Data Stat Widgets Row -->
            <div v-if="stats && stats.length" class="visitor-master-stats-grid">
                <HcisStatGroup :cols="Math.min(stats.length, 3)" gap="sm" appearance="crystal">
                    <HcisStatCard
                        v-for="(stat, idx) in stats"
                        :key="stat.label || idx"
                        :label="stat.label"
                        :value="stat.value"
                        :caption="stat.caption"
                        :icon="stat.icon"
                        :sentiment="stat.sentiment || 'purple'"
                        :pattern="idx % 2 === 0 ? 'dots' : 'grid'"
                        layout="compact"
                        appearance="crystal"
                    />
                </HcisStatGroup>
            </div>

            <!-- Main Master Data Table & Grid Surface -->
            <HcisDataTable
                :title="definition.label"
                :subtitle="definition.description"
            :icon="definition.icon"
            :columns="tableColumns"
            :data="items"
            :loading="loading"
            :search="search"
            :search-placeholder="searchPlaceholder"
            :filters="filterConfig"
            :filter-values="filterValues"
            :total="pagination.total"
            :page="pagination.page"
            :page-size="pagination.pageSize"
            :sort-key="sortKey"
            :sort-order="sortOrder"
            remote-sort
            show-view-toggle
            :view-mode="viewMode"
            :show-pagination="true"
            sentiment="purple"
            sticky-first-col
            @update:view-mode="viewMode = $event"
            @update:search="onSearchChange"
            @filter-change="onFilterChange"
            @reset="resetFilters"
            @page-change="onPageChange"
            @size-change="onPageSizeChange"
            @sort="onSort"
            @add="openCreate"
        >
            <!-- Header Toolbar Actions -->
            <template #headerActions>
                <div class="visitor-master-header-actions">
                    <HcisButton
                        size="sm"
                        variant="primary"
                        leading-icon="plus-lg"
                        class="visitor-master-add-btn fw-bold"
                        @click="openCreate"
                    >
                        Tambah {{ definition.short_label }}
                    </HcisButton>
                </div>
            </template>

            <!-- Responsive Grid / Card View Mode (Ideal for Mobile & Compact Tablets) -->
            <template #grid="{ data }">
                <div class="visitor-master-grid">
                    <HcisCard
                        v-for="item in data"
                        :key="item[definition.id_key] || item.Id"
                        :sentiment="item.Flag_Aktif === 'T' || item.Flag_Aktif === 'N' ? 'neutral' : 'purple'"
                        variant="glass"
                        size="sm"
                        divider
                        hoverable
                        class="visitor-master-card-item"
                    >
                        <template #badge>
                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                <HcisBadge
                                    v-if="definition.has_status"
                                    :status="item.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                    size="xs"
                                    appearance="outline"
                                />
                                <HcisBadge
                                    v-if="item.Wajib_Diisi !== undefined"
                                    :status="item.Wajib_Diisi === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                    :label="item.Wajib_Diisi === 'Y' ? 'Wajib' : 'Opsional'"
                                    size="xs"
                                    appearance="outline"
                                />
                            </div>
                        </template>

                        <div class="visitor-master-card-item__content">
                            <!-- Top Identity Row -->
                            <div class="d-flex align-items-start gap-2.5 mb-2.5">
                                <span class="visitor-master-card-item__id font-tabular font-mono">
                                    #{{ item[definition.id_key] }}
                                </span>
                                <div class="overflow-hidden flex-grow-1">
                                    <h6 class="visitor-master-card-item__title text-slate-800 fw-bold mb-0">
                                        {{ getItemTitle(item) }}
                                    </h6>
                                    <p v-if="getItemSubtitle(item)" class="visitor-master-card-item__sub text-xs text-muted mb-0 mt-0.5 text-truncate">
                                        {{ getItemSubtitle(item) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Attributes Grid (Compact Pills) -->
                            <div class="visitor-master-card-item__attrs">
                                <div v-if="item.Kode_Warna_UI" class="visitor-master-attr-pill">
                                    <span class="visitor-master-color__swatch" :style="{ backgroundColor: item.Kode_Warna_UI }" />
                                    <span>{{ item.Kode_Warna_UI }}</span>
                                </div>
                                <div v-if="item.Akses" class="visitor-master-attr-pill">
                                    <HcisIcon name="signpost-split" size="2xs" class="text-slate-400" />
                                    <span>{{ accessLabel(item.Akses) }}</span>
                                </div>
                                <div v-if="item.Urutan_Tampil !== undefined" class="visitor-master-attr-pill font-tabular">
                                    <HcisIcon name="sort-numeric-down" size="2xs" class="text-slate-400" />
                                    <span>Urutan {{ item.Urutan_Tampil }}</span>
                                </div>
                                <div v-if="item.Jumlah_Pertanyaan !== undefined" class="visitor-master-attr-pill font-tabular">
                                    <HcisIcon name="patch-question" size="2xs" class="text-slate-400" />
                                    <span>{{ item.Jumlah_Pertanyaan }} pertanyaan</span>
                                </div>
                                <div v-if="item.Jumlah_Model !== undefined" class="visitor-master-attr-pill font-tabular">
                                    <HcisIcon name="car-front" size="2xs" class="text-slate-400" />
                                    <span>{{ item.Jumlah_Model }} model</span>
                                </div>
                                <div v-if="item.Usage_Count !== undefined" class="visitor-master-attr-pill font-tabular">
                                    <HcisIcon name="diagram-3" size="2xs" class="text-slate-400" />
                                    <span :class="{ 'text-emerald-700 fw-bold': Number(item.Usage_Count) > 0 }">
                                        {{ Number(item.Usage_Count) || 0 }}× dipakai
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer Action Buttons (50/50 on Mobile) -->
                        <template #footer>
                            <div class="visitor-master-card-item__actions">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="pencil-square"
                                    label="Edit"
                                    class="flex-grow-1 justify-content-center"
                                    :aria-label="`Edit ${getItemTitle(item)}`"
                                    @click.stop="openEdit(item)"
                                />
                                <HcisButton
                                    v-if="definition.has_status"
                                    :variant="item.Flag_Aktif === 'Y' ? 'danger-quiet' : 'success'"
                                    size="xs"
                                    :leading-icon="item.Flag_Aktif === 'Y' ? 'pause-circle' : 'play-circle'"
                                    :loading="isStatusChanging(item)"
                                    :disabled="saving && !isStatusChanging(item)"
                                    :label="item.Flag_Aktif === 'Y' ? 'Nonaktifkan' : 'Aktifkan'"
                                    class="flex-grow-1 justify-content-center"
                                    :aria-label="`${item.Flag_Aktif === 'Y' ? 'Nonaktifkan' : 'Aktifkan'} ${getItemTitle(item)}`"
                                    @click.stop="$emit('change-status', item)"
                                />
                            </div>
                        </template>
                    </HcisCard>
                </div>
            </template>

            <!-- Table View Custom Cell Slots -->
            <template v-if="definition.has_status" #cell-Flag_Aktif="{ row }">
                <HcisBadge
                    :status="row.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                    size="sm"
                    appearance="outline"
                />
            </template>

            <template #cell-Wajib_Diisi="{ row }">
                <HcisBadge
                    :status="row.Wajib_Diisi === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                    :label="row.Wajib_Diisi === 'Y' ? 'Wajib' : 'Opsional'"
                    size="sm"
                    appearance="outline"
                />
            </template>

            <!-- Penanda tampil di form pengajuan permit karyawan. NULL dibaca
                 sebagai boleh tampil, sama seperti penyaring di servernya;
                 dua pembacaan yang berbeda membuat layar menjanjikan sesuatu
                 yang tidak dikerjakan servernya. -->
            <template #cell-Flag_Muncul_Pengajuan="{ row }">
                <HcisBadge
                    :status="row.Flag_Muncul_Pengajuan === 'T' ? 'INACTIVE' : 'ACTIVE'"
                    :label="row.Flag_Muncul_Pengajuan === 'T' ? 'Disembunyikan' : 'Muncul'"
                    size="sm"
                    appearance="outline"
                />
            </template>

            <template #cell-Akses="{ row }">
                <span class="visitor-master-value-chip">
                    {{ accessLabel(row.Akses) }}
                </span>
            </template>

            <template #cell-Satuan_Waktu="{ row }">
                {{ row.Satuan_Waktu === 'MONTH' ? 'Bulan' : row.Satuan_Waktu || '—' }}
            </template>

            <template #cell-Usage_Count="{ row }">
                <span
                    class="visitor-master-usage-count"
                    :class="{ 'is-used': Number(row.Usage_Count) > 0 }"
                    :title="Number(row.Usage_Count) > 0 ? 'Jumlah referensi pada transaksi/checklist' : 'Belum digunakan'"
                >
                    {{ Number(row.Usage_Count) || 0 }}×
                </span>
            </template>

            <template #cell-Kode_Warna_UI="{ row }">
                <span class="visitor-master-color">
                    <span class="visitor-master-color__swatch" :style="{ backgroundColor: row.Kode_Warna_UI || '#cbd5e1' }" />
                    <span class="font-mono font-tabular text-xs">{{ row.Kode_Warna_UI || '—' }}</span>
                </span>
            </template>

            <template #cell-actions="{ row }">
                <div class="visitor-master-actions">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        leading-icon="pencil-square"
                        :aria-label="`Edit ${definition.short_label}`"
                        @click.stop="openEdit(row)"
                    >
                        Edit
                    </HcisButton>
                    <HcisButton
                        v-if="definition.has_status"
                        :variant="row.Flag_Aktif === 'Y' ? 'danger-quiet' : 'success'"
                        size="xs"
                        :leading-icon="row.Flag_Aktif === 'Y' ? 'pause-circle' : 'play-circle'"
                        :loading="isStatusChanging(row)"
                        :disabled="saving && !isStatusChanging(row)"
                        :aria-label="`${row.Flag_Aktif === 'Y' ? 'Nonaktifkan' : 'Aktifkan'} ${definition.short_label}`"
                        @click.stop="$emit('change-status', row)"
                    >
                        {{ row.Flag_Aktif === 'Y' ? 'Nonaktifkan' : 'Aktifkan' }}
                    </HcisButton>
                </div>
            </template>
        </HcisDataTable>
        </template>

        <!-- Modal Editor Dialog with HCIS Form Standards -->
        <HcisDialog
            v-model="editorOpen"
            :title="editingId ? `Edit ${definition.short_label}` : `Tambah ${definition.short_label}`"
            :description="definition.description"
            :icon="definition.icon"
            sentiment="purple"
            variant="glass"
            size="lg"
            sticky-footer
            :loading="saving"
            :close-on-overlay="!saving"
            :close-on-esc="!saving"
        >
            <form id="visitor-master-form" class="visitor-master-form" @submit.prevent="submit">
                <div
                    v-for="field in definition.fields"
                    :key="field.key"
                    class="visitor-master-form__field"
                    :class="{ 'visitor-master-form__field--full': definition.fields.length === 1 || field.type === 'textarea' }"
                >
                    <!-- Select / Relation Input via Official HcisSelect -->
                    <div v-if="field.type === 'select' || field.type === 'relation'" class="visitor-master-select-field">
                        <HcisField
                            :id="fieldId(field)"
                            :label="field.label"
                            :required="field.required"
                            :error="fieldError(field.key)"
                            :disabled="saving"
                        >
                            <HcisSelect
                                :id="fieldId(field)"
                                v-model="form[field.key]"
                                :placeholder="field.required ? `Pilih ${field.label.toLowerCase()}...` : 'Semua / tidak dibatasi'"
                                :clearable="!field.required"
                                :filterable="true"
                                size="md"
                                :disabled="saving"
                            >
                                <HcisSelectOption
                                    v-if="!field.required"
                                    :value="null"
                                    label="Semua / tidak dibatasi"
                                />
                                <HcisSelectOption
                                    v-for="option in fieldOptions(field)"
                                    :key="option.value"
                                    :value="option.value"
                                    :label="option.label"
                                    :disabled="option.active === false && String(form[field.key]) !== String(option.value)"
                                    :description="option.active === false ? 'Nonaktif' : ''"
                                />
                            </HcisSelect>
                        </HcisField>
                    </div>

                    <!-- Color Picker Input with Live Swatch -->
                    <div v-else-if="field.type === 'color'" class="visitor-master-color-field">
                        <HcisField
                            :id="fieldId(field)"
                            v-model="form[field.key]"
                            :label="field.label"
                            type="text"
                            :required="field.required"
                            :max-length="10"
                            :disabled="saving"
                            :error="fieldError(field.key)"
                            placeholder="#6366F1"
                            hint="Pilih warna atau masukkan kode hex."
                        >
                            <template #append>
                                <input
                                    v-model="form[field.key]"
                                    type="color"
                                    class="visitor-master-color-picker-input"
                                    :disabled="saving"
                                    title="Pilih warna dari palet"
                                    aria-label="Pilih warna dari palet"
                                />
                            </template>
                        </HcisField>
                    </div>

                    <!-- Standard Text / Textarea / Number Fields -->
                    <HcisField
                        v-else
                        :id="fieldId(field)"
                        v-model="form[field.key]"
                        :label="field.label"
                        :type="field.type === 'number' ? 'number' : (field.type === 'textarea' ? 'textarea' : 'text')"
                        :required="field.required"
                        :max-length="field.max"
                        :min="field.min"
                        :max="field.max"
                        :rows="field.type === 'textarea' ? 4 : undefined"
                        :disabled="saving"
                        :error="fieldError(field.key)"
                        show-count
                    />
                </div>
            </form>

            <template #footer>
                <div class="visitor-master-dialog-actions">
                    <HcisButton
                        variant="secondary"
                        size="md"
                        :disabled="saving"
                        class="visitor-master-dialog-btn"
                        @click="editorOpen = false"
                    >
                        Batal
                    </HcisButton>
                    <HcisButton
                        type="submit"
                        form="visitor-master-form"
                        variant="primary"
                        size="md"
                        :loading="saving"
                        leading-icon="check-lg"
                        class="visitor-master-dialog-btn fw-bold"
                    >
                        Simpan Data
                    </HcisButton>
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDataTable,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisSelect,
    HcisSelectOption,
    HcisStatCard,
    HcisStatGroup,
} from '@/components/ui';

defineOptions({ name: 'VisitorMasterDataPanel' });

const props = defineProps({
    definition: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    relations: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    statusChangingId: { type: [Number, String], default: null },
    error: { type: Object, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    pagination: {
        type: Object,
        default: () => ({ page: 1, pageSize: 10, total: 0, totalPages: 1 }),
    },
});

const emit = defineEmits(['retry', 'save', 'change-status', 'query-change']);

const search = ref('');
const viewMode = ref('table');
const editorOpen = ref(false);
const editingId = ref(null);
const form = reactive({});
const filterValues = reactive({});
const sortKey = ref(props.definition.id_key);
const sortOrder = ref('desc');
let queryEmitTimer = null;

onUnmounted(() => clearTimeout(queryEmitTimer));

const statusFilter = {
    key: 'Flag_Aktif',
    label: 'Status',
    type: 'select',
    icon: 'toggle-on',
    // Bawaannya aktif saja - placeholder harus mengatakannya, sebab "Semua
    // status" yang sebenarnya menyaring adalah label yang berbohong.
    placeholder: 'Aktif saja',
    options: [
        { value: 'Y', label: 'Aktif' },
        { value: 'T', label: 'Nonaktif' },
        { value: 'ALL', label: 'Aktif + Nonaktif' },
    ],
};

const usageFilter = {
    key: 'Usage_Status',
    label: 'Pemakaian',
    type: 'select',
    icon: 'diagram-3',
    placeholder: 'Semua pemakaian',
    options: [
        { value: 'USED', label: 'Sudah digunakan' },
        { value: 'UNUSED', label: 'Belum digunakan' },
    ],
};

const filterConfig = computed(() => {
    const relationOptions = (key) =>
        (props.relations[key] || []).map((option) => ({
            value: option.value,
            label: option.active === false ? `${option.label} (nonaktif)` : option.label,
        }));

    switch (props.definition.key) {
        case 'kategori-permit':
        case 'plat-kendaraan':
            return [statusFilter, usageFilter];
        case 'vehicle-type':
            return [
                statusFilter,
                {
                    key: 'Akses',
                    label: 'Digunakan untuk',
                    type: 'select',
                    icon: 'signpost-split',
                    placeholder: 'Semua akses',
                    options: [
                        { value: 'ALL', label: 'Permit & Tamu' },
                        { value: 'PERMIT', label: 'Permit' },
                        { value: 'TAMU', label: 'Tamu' },
                    ],
                },
            ];
        case 'vehicle-model':
            return [
                statusFilter,
                {
                    key: 'Id_Master_Tipe',
                    label: 'Tipe kendaraan',
                    type: 'select',
                    icon: 'truck-front',
                    placeholder: 'Semua tipe',
                    options: relationOptions('Id_Master_Tipe'),
                },
                usageFilter,
            ];
        case 'vehicle-color':
        case 'purpose':
            return [usageFilter];
        case 'durasi-permit':
            return [statusFilter, usageFilter];
        case 'approval-question':
            return [
                statusFilter,
                {
                    key: 'Wajib_Diisi',
                    label: 'Kewajiban',
                    type: 'select',
                    icon: 'check2-square',
                    placeholder: 'Semua pertanyaan',
                    options: [
                        { value: 'Y', label: 'Wajib diisi' },
                        { value: 'T', label: 'Opsional' },
                    ],
                },
                {
                    key: 'Target_Scope',
                    label: 'Cakupan',
                    type: 'select',
                    icon: 'bullseye',
                    placeholder: 'Semua cakupan',
                    options: [
                        { value: 'GLOBAL', label: 'Global' },
                        { value: 'TARGETED', label: 'Tertarget' },
                    ],
                },
                {
                    key: 'Target_Id_Kategori_Permit',
                    label: 'Kategori',
                    type: 'select',
                    icon: 'tags',
                    placeholder: 'Semua kategori',
                    options: relationOptions('Target_Id_Kategori_Permit'),
                },
                {
                    key: 'Target_Id_Tipe_Kendaraan',
                    label: 'Tipe kendaraan',
                    type: 'select',
                    icon: 'truck-front',
                    placeholder: 'Semua tipe',
                    options: relationOptions('Target_Id_Tipe_Kendaraan'),
                },
            ];
        default:
            return [];
    }
});

const searchPlaceholder = computed(() => `Cari ${props.definition.short_label.toLocaleLowerCase('id-ID')}...`);

const tableColumns = computed(() => [
    ...props.definition.columns.map((column) => ({ ...column, sortable: column.sortable ?? true })),
    ...(props.definition.has_status ? [{ key: 'Flag_Aktif', label: 'Status', width: '120px', sortable: true }] : []),
    { key: 'actions', label: 'Aksi', width: props.definition.has_status ? '220px' : '100px' },
]);

watch(
    () => props.definition.key,
    () => {
        search.value = '';
        editorOpen.value = false;
        sortKey.value = props.definition.id_key;
        sortOrder.value = 'desc';
        resetFilters();
    },
);

function onFilterChange({ key, value }) {
    filterValues[key] = value ?? '';
    scheduleQuery({ page: 1 });
}

function resetFilters() {
    Object.keys(filterValues).forEach((key) => delete filterValues[key]);
    search.value = '';
    scheduleQuery({ page: 1 });
}

function onSearchChange(value) {
    search.value = value || '';
    scheduleQuery({ page: 1 });
}

function onPageChange(page) {
    scheduleQuery({ page: Number(page) || 1 });
}

function onPageSizeChange(limit) {
    scheduleQuery({ page: 1, limit: Number(limit) || 10 });
}

function onSort({ key, order }) {
    sortKey.value = key;
    sortOrder.value = order === 'asc' ? 'asc' : 'desc';
    scheduleQuery({ page: 1 });
}

function scheduleQuery(overrides = {}) {
    clearTimeout(queryEmitTimer);
    queryEmitTimer = setTimeout(() => {
        emit('query-change', {
            page: overrides.page ?? props.pagination.page,
            limit: overrides.limit ?? props.pagination.pageSize,
            search: search.value.trim(),
            sort_by: sortKey.value,
            sort_direction: sortOrder.value,
            ...Object.fromEntries(
                Object.entries(filterValues).filter(([, value]) => value !== '' && value !== null && value !== undefined),
            ),
        });
    }, 0);
}

function initialForm(item = null) {
    const values = {};
    props.definition.fields.forEach((field) => {
        values[field.key] = item ? (item[field.key] ?? null) : (field.default ?? null);
    });
    return values;
}

function setForm(values) {
    Object.keys(form).forEach((key) => delete form[key]);
    Object.assign(form, values);
}

function openCreate() {
    editingId.value = null;
    setForm(initialForm());
    editorOpen.value = true;
}

function openEdit(item) {
    editingId.value = item[props.definition.id_key];
    setForm(initialForm(item));
    editorOpen.value = true;
}

function submit() {
    emit('save', { id: editingId.value, payload: { ...form } });
}

function closeEditor() {
    editorOpen.value = false;
}

function fieldId(field) {
    return `visitor-master-${props.definition.key}-${field.key}`;
}

function fieldError(key) {
    const error = props.fieldErrors[key];
    return Array.isArray(error) ? error[0] : error || '';
}

function fieldOptions(field) {
    if (field.type === 'relation') return props.relations[field.key] || [];
    return Object.entries(field.options || {}).map(([value, label]) => ({ value, label, active: true }));
}

function accessLabel(value) {
    return (
        {
            ALL: 'Permit & Tamu',
            PERMIT: 'Permit',
            TAMU: 'Tamu',
        }[value] ||
        value ||
        '—'
    );
}

function isStatusChanging(item) {
    if (props.statusChangingId === null) return false;
    return String(item[props.definition.id_key]) === String(props.statusChangingId);
}

function getItemTitle(item) {
    return (
        item.Teks_Pertanyaan ||
        item.Nama_Durasi ||
        item.Nama_Kategori ||
        item.Kode_Plat ||
        item.Nama_Keperluan ||
        item.Nama_Warna ||
        item.Nama_Model_Kendaraan ||
        item.Nama_Tipe_Kendaraan ||
        item.Nama ||
        `Item #${item[props.definition.id_key]}`
    );
}

function getItemSubtitle(item) {
    switch (props.definition.key) {
        case 'approval-question':
            return (
                [item.Target_Id_Kategori_Permit_label, item.Target_Id_Tipe_Kendaraan_label]
                    .filter(Boolean)
                    .join(' · ') || 'Cakupan Global'
            );
        case 'durasi-permit':
            return `${item.Jumlah_Angka} ${item.Satuan_Waktu === 'MONTH' ? 'Bulan' : item.Satuan_Waktu || 'Bulan'}${item.Keterangan_Durasi ? ' (' + item.Keterangan_Durasi + ')' : ''}`;
        case 'kategori-permit':
            return item.Deskripsi_Akses || 'Akses Operasional Permit';
        case 'plat-kendaraan':
            return 'Prefix Wilayah Nomor Kendaraan';
        case 'purpose':
            return 'Daftar Keperluan Kunjungan Standar';
        case 'vehicle-color':
            return 'Warna Kendaraan Standar';
        case 'vehicle-model':
            return item.Id_Master_Tipe_label ? `Tipe: ${item.Id_Master_Tipe_label}` : 'Model Kendaraan';
        case 'vehicle-type':
            return `Akses: ${accessLabel(item.Akses)}`;
        default:
            return '';
    }
}

defineExpose({ closeEditor });
</script>

<style scoped>
.visitor-master-panel {
    min-width: 0;
    width: 100%;
}

.visitor-master-error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #fff7f7;
    color: var(--hcis-red-800);
}

.visitor-master-error p {
    margin: 0.25rem 0 0;
    color: var(--hcis-red-700);
}

/* Master Data Stats Grid */
.visitor-master-stats-grid {
    width: 100%;
    margin-bottom: var(--hcis-space-3, 0.75rem);
}

/* Table Toolbar Header Actions */
.visitor-master-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

.visitor-master-add-btn {
    white-space: nowrap;
}

/* Actions in Table Cell */
.visitor-master-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

/* Responsive Card Grid */
.visitor-master-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 0.85rem;
    width: 100%;
    padding: 0.5rem 0;
}

.visitor-master-card-item {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
}

.visitor-master-card-item__content {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}

.visitor-master-card-item__id {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 800;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    border-radius: 6px;
    padding: 0.15rem 0.45rem;
    flex-shrink: 0;
}

.visitor-master-card-item__title {
    font-size: 0.875rem;
    line-height: 1.35;
    word-break: break-word;
}

.visitor-master-card-item__attrs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: auto;
    padding-top: 0.65rem;
}

.visitor-master-attr-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
}

.visitor-master-card-item__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

/* Custom Table Chips & Swatches */
.visitor-master-color {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.visitor-master-color__swatch {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.35rem;
    border: 1px solid rgba(15, 23, 42, 0.15);
    flex-shrink: 0;
}

.visitor-master-color-picker-input {
    width: 2.35rem;
    height: 2.35rem;
    padding: 2px;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
}

.visitor-master-value-chip {
    display: inline-flex;
    align-items: center;
    min-height: 1.75rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}

.visitor-master-usage-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    min-height: 1.75rem;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 0.75rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.visitor-master-usage-count.is-used {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
}

/* Form Styles */
.visitor-master-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.visitor-master-form__field--full {
    grid-column: 1 / -1;
}

.visitor-master-select-field,
.visitor-master-color-field {
    min-width: 0;
}

.visitor-master-dialog-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

.visitor-master-dialog-btn {
    min-width: 90px;
}

/* Tablet & Mobile Media Queries */
@media (max-width: 767.98px) {
    .visitor-master-form {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .visitor-master-error {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 640px) {
    .visitor-master-stats-grid :deep(.hcis-stat-group) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        gap: 0.5rem !important;
        padding: 2px 2px 0.35rem 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x mandatory !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .visitor-master-stats-grid :deep(.hcis-stat-group)::-webkit-scrollbar {
        display: none !important;
    }

    .visitor-master-stats-grid :deep(.hcis-stat) {
        flex: 0 0 142px !important;
        min-width: 142px !important;
        max-width: 142px !important;
        scroll-snap-align: start !important;
        padding: 0.65rem 0.75rem !important;
        min-height: 5rem !important;
        border-radius: 12px !important;
    }

    .visitor-master-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .visitor-master-header-actions {
        width: 100%;
    }

    .visitor-master-add-btn {
        width: 100%;
        min-height: 2.375rem;
        justify-content: center;
    }

    .visitor-master-dialog-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }

    .visitor-master-dialog-btn {
        width: 100%;
        min-height: 2.5rem;
    }
}
</style>
