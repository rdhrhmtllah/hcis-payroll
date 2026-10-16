<template>
    <HcisDialog
        :model-value="modelValue"
        :title="policy?.Nama_Policy || 'Rincian Aturan Penilai'"
        kicker="MASTER DATA · KOMPOSISI PENILAI"
        :description="policy ? `${policy.Kode_Policy} · Level ${policy.ID_Level} · Versi ${policy.Versi}` : ''"
        icon="diagram-3"
        sentiment="purple"
        variant="glass"
        :size="isMobile ? 'full' : 'xl'"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-policy-detail">
            <!-- Loading state -->
            <div v-if="loading" class="pa-policy-detail__loading">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="purple" />
                <span>Memuat rincian aturan penilai...</span>
            </div>

            <!-- Error state -->
            <HcisResultState
                v-else-if="error"
                status="danger"
                variant="flat"
                layout="inline"
                title="Rincian aturan gagal dimuat"
                :description="error"
                primary-text="Coba lagi"
                primary-icon="arrow-clockwise"
                @primary-click="$emit('retry')"
            />

            <!-- Loaded content -->
            <template v-else>
                <!-- Notice Banner -->
                <div class="pa-policy-detail__notice">
                    <HcisIcon name="info-circle-fill" size="xs" class="shrink-0 mt-0.5" />
                    <div>
                        <strong>Ketentuan Komposisi Bobot Penilai</strong>
                        <span>
                            Total bobot persentase dari seluruh kategori penilai wajib <strong>tepat 100.00%</strong>.
                            Rincian ini disimpan dalam satu transaksi atomik.
                        </span>
                    </div>
                </div>

                <!-- Weight Balance Cockpit & Live Multi-Segment Bar -->
                <section class="pa-policy-cockpit" aria-label="Ringkasan Bobot">
                    <div class="pa-policy-cockpit__header">
                        <div class="pa-policy-cockpit__title">
                            <span class="pa-eyebrow">DISTRIBUSI BOBOT KATEGORI</span>
                            <h4>Keseimbangan Porsi Penilai</h4>
                        </div>
                        <div class="pa-policy-cockpit__status">
                            <HcisBadge
                                :label="isTotalExact100 ? 'Total 100% (Sempurna & Valid)' : `Total: ${totalBobot.toFixed(2)}% (${weightDiffLabel})`"
                                :variant="isTotalExact100 ? 'success' : 'danger'"
                                :appearance="isTotalExact100 ? 'glass' : 'subtle'"
                                size="sm"
                                :dot="true"
                            />
                        </div>
                    </div>

                    <!-- Live Stacked Spectrum Multi-Bar -->
                    <div class="pa-policy-spectrum-track">
                        <div
                            v-for="(row, idx) in activeRows"
                            :key="`track-seg-${row.PA_Rater_Category_Id || idx}`"
                            class="pa-policy-spectrum-seg"
                            :style="{
                                width: `${Math.max(0, Math.min(100, Number(row.Bobot_Persen) || 0))}%`,
                                backgroundColor: getCategoryColor(row, idx),
                            }"
                            :title="`${getCategoryName(row)}: ${row.Bobot_Persen}%`"
                        >
                            <span v-if="Number(row.Bobot_Persen) >= (isMobile ? 12 : 8)" class="pa-policy-spectrum-label">
                                {{ getCategoryName(row) }} {{ Number(row.Bobot_Persen) }}%
                            </span>
                        </div>
                    </div>

                    <!-- Model Presets (1-Click Template Setup) -->
                    <div class="pa-policy-presets">
                        <span class="pa-policy-presets__label">
                            <HcisIcon name="lightning-charge-fill" size="2xs" /> Template Preset:
                        </span>
                        <div class="pa-policy-presets__list">
                            <button
                                v-for="model in assessmentModels"
                                :key="model.name"
                                type="button"
                                class="pa-preset-chip"
                                @click="applyAssessmentModel(model)"
                            >
                                <strong>{{ model.name }}</strong>
                                <small>{{ model.caption }}</small>
                            </button>
                        </div>
                        <HcisAlert
                            v-if="pesanPreset"
                            variant="warning"
                            appearance="soft"
                            size="sm"
                            :title="pesanPreset"
                            data-uji="pesan-preset"
                        />
                    </div>
                </section>

                <!-- Category Rows List -->
                <section class="pa-policy-rows-section" aria-label="Daftar Kategori Penilai">
                    <div class="pa-policy-rows-section__header">
                        <span class="pa-eyebrow">KOMPOSISI PENILAI ({{ rows.length }} KATEGORI)</span>
                        <div class="pa-policy-add-dropdown" v-if="unaddedCategories.length">
                            <select
                                v-model="selectedCategoryToAdd"
                                class="pa-add-category-select"
                                @change="addCategoryRow"
                            >
                                <option value="">+ Tambah Kategori Penilai...</option>
                                <option
                                    v-for="cat in unaddedCategories"
                                    :key="cat.Id_PA_Rater_Category"
                                    :value="cat.Id_PA_Rater_Category"
                                >
                                    + {{ cat.Nama_Kategori }} ({{ cat.Kode_Kategori }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Category Cards -->
                    <div v-if="rows.length" class="pa-policy-cards-list">
                        <article
                            v-for="(row, index) in rows"
                            :key="row.Id_PA_Rater_Policy_Detail || row.PA_Rater_Category_Id || index"
                            class="pa-policy-category-card"
                            :style="{ borderLeftColor: getCategoryColor(row, index) }"
                        >
                            <!-- Card Topline -->
                            <div class="pa-policy-category-card__top">
                                <div class="pa-policy-category-card__title">
                                    <div
                                        class="pa-category-dot"
                                        :style="{ backgroundColor: getCategoryColor(row, index) }"
                                    />
                                    <div>
                                        <strong>{{ getCategoryName(row) }}</strong>
                                        <span class="pa-category-code">{{ getCategoryCode(row) }}</span>
                                    </div>
                                </div>

                                <div class="pa-policy-category-card__weight-control">
                                    <div class="pa-weight-stepper">
                                        <button
                                            type="button"
                                            class="pa-stepper-btn"
                                            :disabled="Number(row.Bobot_Persen) <= 0"
                                            @click="adjustWeight(index, -5)"
                                        >
                                            −5%
                                        </button>
                                        <div class="pa-weight-input-wrap">
                                            <input
                                                :id="`bobot-policy-${index}`"
                                                v-model.number="row.Bobot_Persen"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                class="pa-weight-number-input"
                                            />
                                            <span class="pa-weight-unit">%</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="pa-stepper-btn"
                                            :disabled="Number(row.Bobot_Persen) >= 100"
                                            @click="adjustWeight(index, 5)"
                                        >
                                            +5%
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        class="pa-remove-row-btn"
                                        title="Hapus kategori dari aturan"
                                        @click="removeCategoryRow(index)"
                                    >
                                        <HcisIcon name="trash" size="xs" />
                                    </button>
                                </div>
                            </div>

                            <!-- Card Config Specs Grid -->
                            <div class="pa-policy-category-card__specs">
                                <!-- Quota Counts -->
                                <div class="pa-spec-box">
                                    <span class="pa-spec-label">
                                        <HcisIcon name="people" size="2xs" /> Ambang Jumlah Penilai
                                    </span>
                                    <div class="pa-spec-grid-three">
                                        <div class="pa-mini-field">
                                            <label :for="`min-policy-${index}`">Min Rater <em>*</em></label>
                                            <input
                                                :id="`min-policy-${index}`"
                                                v-model.number="row.Min_Rater"
                                                type="number"
                                                min="1"
                                                placeholder="1"
                                            />
                                        </div>
                                        <div class="pa-mini-field">
                                            <label :for="`maks-policy-${index}`">Maks Rater</label>
                                            <input
                                                :id="`maks-policy-${index}`"
                                                v-model.number="row.Maks_Rater"
                                                type="number"
                                                :min="Number(row.Min_Rater) || 1"
                                                placeholder="Bebas"
                                            />
                                        </div>
                                        <div class="pa-mini-field">
                                            <label :for="`selesai-policy-${index}`">Min Selesai</label>
                                            <input
                                                :id="`selesai-policy-${index}`"
                                                v-model.number="row.Min_Rater_Selesai"
                                                type="number"
                                                min="1"
                                                placeholder="1"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Organization Binding & Manual Permission -->
                                <div class="pa-spec-box">
                                    <span class="pa-spec-label">
                                        <HcisIcon name="diagram-2" size="2xs" /> Relasi &amp; Penugasan
                                    </span>
                                    <div class="pa-spec-grid-two">
                                        <div class="pa-mini-field">
                                            <label :for="`relasi-policy-${index}`">Relasi Organisasi</label>
                                            <select
                                                :id="`relasi-policy-${index}`"
                                                v-model="row.Organization_Relationship_Type_Id"
                                                class="pa-mini-select"
                                            >
                                                <option value="">Tidak terikat relasi</option>
                                                <option
                                                    v-for="rel in references.jenis_relasi || []"
                                                    :key="rel.Id_Organization_Relationship_Type"
                                                    :value="String(rel.Id_Organization_Relationship_Type)"
                                                >
                                                    {{ rel.Nama_Relasi }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="pa-mini-field">
                                            <label :for="`manual-policy-${index}`">Izinkan Manual</label>
                                            <select
                                                :id="`manual-policy-${index}`"
                                                v-model="row.Flag_Izinkan_Manual"
                                                class="pa-mini-select"
                                            >
                                                <option value="Y">Ya (Manual)</option>
                                                <option value="T">Tidak (Otomatis)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes & Order -->
                            <div class="pa-policy-category-card__bottom">
                                <div class="pa-mini-field pa-mini-field--grow">
                                    <input
                                        v-model="row.Catatan_Aturan"
                                        type="text"
                                        placeholder="Catatan aturan kategori (opsional)..."
                                        class="pa-note-input"
                                    />
                                </div>
                                <div class="pa-mini-field pa-mini-field--order">
                                    <input
                                        :id="`urutan-policy-${index}`"
                                        v-model.number="row.Urutan_Tampil"
                                        type="number"
                                        min="1"
                                        title="Urutan Tampil"
                                        class="pa-order-input"
                                        placeholder="Urutan"
                                    />
                                </div>
                            </div>
                        </article>
                    </div>

                    <HcisEmptyState
                        v-else
                        status="info"
                        variant="flat"
                        title="Belum ada kategori penilai"
                        description="Pilih template model penilaian di atas atau tambahkan kategori penilai secara manual."
                        :show-retry="false"
                    />
                </section>

                <!-- Action Error alert -->
                <HcisResultState
                    v-if="actionError"
                    status="danger"
                    variant="flat"
                    layout="inline"
                    title="Perubahan ditolak server"
                    :description="actionError"
                    :show-retry="false"
                />
            </template>
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint">
                <HcisIcon name="shield-check" size="2xs" />
                <span class="d-none d-sm-inline">Mengaktifkan versi baru akan mengarsipkan versi sebelumnya untuk level ini.</span>
                <span class="d-inline d-sm-none">Mengarsipkan versi lama saat diaktifkan.</span>
            </span>
        </template>

        <template #footer>
            <div class="pa-dialog-footer-actions" :class="{ 'pa-dialog-footer-actions--mobile': isMobile }">
                <HcisButton
                    variant="secondary"
                    label="Tutup"
                    size="sm"
                    :class="{ 'flex-1': isMobile }"
                    :disabled="saving || activating"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    variant="primary"
                    label="Simpan Rincian"
                    size="sm"
                    leading-icon="check2-circle"
                    :class="{ 'flex-1': isMobile }"
                    :loading="saving"
                    :disabled="loading || !rows.length || saving || activating || !isTotalExact100"
                    @click="handleSave"
                />
                <HcisButton
                    v-if="policy?.Status_Policy !== 'ACTIVE'"
                    variant="success"
                    label="Aktifkan Versi Ini"
                    size="sm"
                    leading-icon="check-circle"
                    :class="{ 'w-100 mt-1': isMobile }"
                    :loading="activating"
                    :disabled="loading || !rows.length || saving || activating || !isTotalExact100"
                    @click="$emit('activate')"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
} from '@/components/ui';

defineOptions({ name: 'PaPolicyDetailDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    policy: { type: Object, default: null },
    detail: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    activating: { type: Boolean, default: false },
    error: { type: String, default: '' },
    actionError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'retry', 'save', 'activate']);

const isMobile = ref(false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', cekUkuranLayar);
});

const rows = ref([]);
const selectedCategoryToAdd = ref('');

const categoryColorPalette = [
    '#4f46e5', // Indigo (Atasan)
    '#7c3aed', // Purple (Peer)
    '#0d9488', // Teal (Bawahan)
    '#0284c7', // Sky (Self)
    '#ea580c', // Orange (Customer / Eksternal)
    '#e11d48', // Rose
    '#16a34a', // Emerald
    '#64748b', // Slate
];

/* Kode preset = `Kode_Kategori` master (ATASAN, PEER, BAWAHAN, SELF), dicocokkan PERSIS.
   Sampai 14 Okt 2026 kodenya ATASAN_LANGSUNG/REKAN_KERJA/BAWAHAN_LANGSUNG/DIRI_SENDIRI yang tidak
   pernah cocok, lalu jatuh ke "kategori ke-n" menurut urutan: preset 50/25/25 memberi 25% ke Diri
   Sendiri, bukan ke Rekan Kerja. */
const assessmentModels = [
    {
        name: 'Top-Down Murni (100% Atasan)',
        caption: 'Penilaian standar atasan langsung',
        allocations: [{ code: 'ATASAN', weight: 100 }],
    },
    {
        name: '180° Evaluasi (70% Atasan : 30% Peer)',
        caption: 'Kombinasi atasan dan rekan kerja selevel',
        allocations: [
            { code: 'ATASAN', weight: 70 },
            { code: 'PEER', weight: 30 },
        ],
    },
    {
        name: '360° Multi-Rater (50% Atasan : 25% Peer : 25% Subordinat)',
        caption: 'Umpan balik komprehensif 3 arah',
        allocations: [
            { code: 'ATASAN', weight: 50 },
            { code: 'PEER', weight: 25 },
            { code: 'BAWAHAN', weight: 25 },
        ],
    },
    {
        name: '360° + Self (40% Atasan : 20% Peer : 20% Subordinat : 20% Diri Sendiri)',
        caption: 'Evaluasi lengkap dengan refleksi mandiri',
        allocations: [
            { code: 'ATASAN', weight: 40 },
            { code: 'PEER', weight: 20 },
            { code: 'BAWAHAN', weight: 20 },
            { code: 'SELF', weight: 20 },
        ],
    },
];

const activeRows = computed(() => rows.value.filter((r) => Number(r.Bobot_Persen) > 0));

const totalBobot = computed(() => {
    const sum = rows.value.reduce((acc, row) => acc + (Number(row.Bobot_Persen) || 0), 0);
    return Math.round(sum * 100) / 100;
});

const isTotalExact100 = computed(() => totalBobot.value === 100);

const weightDiffLabel = computed(() => {
    const diff = 100 - totalBobot.value;
    if (diff > 0) return `Kurang ${diff.toFixed(2)}%`;
    return `Kelebihan ${Math.abs(diff).toFixed(2)}%`;
});

const unaddedCategories = computed(() => {
    const allCategories = props.references?.kategori_penilai || [];
    return allCategories.filter((c) => !rows.value.some((r) => Number(r.PA_Rater_Category_Id) === Number(c.Id_PA_Rater_Category)));
});

function getCategoryName(row) {
    if (row.Nama_Kategori) return row.Nama_Kategori;
    const found = (props.references?.kategori_penilai || []).find(
        (c) => Number(c.Id_PA_Rater_Category) === Number(row.PA_Rater_Category_Id),
    );
    return found?.Nama_Kategori || row.Kode_Kategori || `Kategori #${row.PA_Rater_Category_Id}`;
}

function getCategoryCode(row) {
    if (row.Kode_Kategori) return row.Kode_Kategori;
    const found = (props.references?.kategori_penilai || []).find(
        (c) => Number(c.Id_PA_Rater_Category) === Number(row.PA_Rater_Category_Id),
    );
    return found?.Kode_Kategori || '';
}

function getCategoryColor(row, index) {
    const code = getCategoryCode(row).toUpperCase();
    if (code.includes('ATASAN')) return '#4f46e5';
    if (code.includes('REKAN') || code.includes('PEER')) return '#7c3aed';
    if (code.includes('BAWAHAN') || code.includes('SUBORDINAT')) return '#0d9488';
    if (code.includes('DIRI') || code.includes('SELF')) return '#0284c7';
    if (code.includes('CUSTOMER') || code.includes('KLIEN')) return '#ea580c';
    return categoryColorPalette[index % categoryColorPalette.length];
}

function adjustWeight(index, delta) {
    const current = Number(rows.value[index].Bobot_Persen) || 0;
    const next = Math.max(0, Math.min(100, current + delta));
    rows.value[index].Bobot_Persen = next;
}

function addCategoryRow() {
    if (!selectedCategoryToAdd.value) return;
    const catId = Number(selectedCategoryToAdd.value);
    const catObj = (props.references?.kategori_penilai || []).find((c) => Number(c.Id_PA_Rater_Category) === catId);
    if (!catObj) return;

    rows.value.push({
        PA_Rater_Category_Id: catId,
        Kode_Kategori: catObj.Kode_Kategori,
        Nama_Kategori: catObj.Nama_Kategori,
        Bobot_Persen: Math.max(0, 100 - totalBobot.value),
        Min_Rater: 1,
        Maks_Rater: null,
        Min_Rater_Selesai: 1,
        Organization_Relationship_Type_Id: '',
        Flag_Izinkan_Manual: 'T',
        Urutan_Tampil: rows.value.length + 1,
        Catatan_Aturan: '',
    });

    selectedCategoryToAdd.value = '';
}

function removeCategoryRow(index) {
    rows.value.splice(index, 1);
}

const pesanPreset = ref('');

function applyAssessmentModel(model) {
    pesanPreset.value = '';
    const allCategories = props.references?.kategori_penilai || [];
    const newRows = [];
    const tidakAda = [];

    model.allocations.forEach((alloc, idx) => {
        // Cocok PERSIS dengan kode kategori master; tanpa cadangan menurut urutan.
        const found = allCategories.find((c) => String(c.Kode_Kategori || '').toUpperCase() === alloc.code);

        if (!found) {
            tidakAda.push(alloc.code);
            return;
        }

        // Kategori yang sudah ada di komposisi mempertahankan setelannya; yang berganti hanya bobot.
        const ada = rows.value.find((r) => Number(r.PA_Rater_Category_Id) === Number(found.Id_PA_Rater_Category));
        newRows.push(ada ? { ...ada, Bobot_Persen: alloc.weight, Urutan_Tampil: idx + 1 } : {
            PA_Rater_Category_Id: found.Id_PA_Rater_Category,
            Kode_Kategori: found.Kode_Kategori,
            Nama_Kategori: found.Nama_Kategori,
            Bobot_Persen: alloc.weight,
            Min_Rater: 1,
            Maks_Rater: alloc.weight >= 50 ? 1 : 3,
            Min_Rater_Selesai: 1,
            Organization_Relationship_Type_Id: '',
            Flag_Izinkan_Manual: alloc.code === 'PEER' ? 'Y' : 'T',
            Urutan_Tampil: idx + 1,
            Catatan_Aturan: `Model ${model.name}`,
        });
    });

    /* Preset yang kategorinya tidak lengkap di master TIDAK diterapkan setengah: bobotnya tidak
       lagi berjumlah 100 dan admin mengira presetnya berhasil. */
    if (tidakAda.length) {
        pesanPreset.value = `Preset tidak diterapkan: kategori ${tidakAda.join(', ')} tidak ada di master Jenis Penilai.`;
        return;
    }

    if (newRows.length) {
        rows.value = newRows;
    }
}

function handleSave() {
    if (!isTotalExact100.value) return;
    const payload = rows.value.map((r, idx) => ({
        pa_rater_category_id: Number(r.PA_Rater_Category_Id),
        bobot_persen: Number(r.Bobot_Persen),
        min_rater: Number(r.Min_Rater) || 1,
        maks_rater: r.Maks_Rater !== null && r.Maks_Rater !== '' && !isNaN(Number(r.Maks_Rater)) ? Number(r.Maks_Rater) : null,
        min_rater_selesai: r.Min_Rater_Selesai !== null && r.Min_Rater_Selesai !== '' && !isNaN(Number(r.Min_Rater_Selesai)) ? Number(r.Min_Rater_Selesai) : null,
        organization_relationship_type_id: r.Organization_Relationship_Type_Id ? Number(r.Organization_Relationship_Type_Id) : null,
        flag_izinkan_manual: r.Flag_Izinkan_Manual === 'Y' ? 'Y' : 'T',
        urutan_tampil: Number(r.Urutan_Tampil) || idx + 1,
        catatan_aturan: r.Catatan_Aturan ? String(r.Catatan_Aturan).trim() : null,
    }));

    emit('save', payload);
}

function syncData() {
    if (!props.detail || !props.detail.length) {
        // If empty, auto populate default categories from references
        const defaultCategories = (props.references?.kategori_penilai || []).slice(0, 2);
        if (defaultCategories.length) {
            rows.value = defaultCategories.map((c, idx) => ({
                PA_Rater_Category_Id: c.Id_PA_Rater_Category,
                Kode_Kategori: c.Kode_Kategori,
                Nama_Kategori: c.Nama_Kategori,
                Bobot_Persen: idx === 0 ? 100 : 0,
                Min_Rater: 1,
                Maks_Rater: null,
                Min_Rater_Selesai: 1,
                Organization_Relationship_Type_Id: '',
                Flag_Izinkan_Manual: 'T',
                Urutan_Tampil: idx + 1,
                Catatan_Aturan: '',
            }));
            return;
        }
    }

    rows.value = (props.detail || []).map((row, idx) => ({
        Id_PA_Rater_Policy_Detail: row.Id_PA_Rater_Policy_Detail,
        PA_Rater_Category_Id: row.PA_Rater_Category_Id,
        Kode_Kategori: row.Kode_Kategori,
        Nama_Kategori: row.Nama_Kategori,
        Bobot_Persen: Number(row.Bobot_Persen ?? 0),
        Min_Rater: Number(row.Min_Rater ?? 1),
        Maks_Rater: row.Maks_Rater !== null && row.Maks_Rater !== undefined ? Number(row.Maks_Rater) : null,
        Min_Rater_Selesai: row.Min_Rater_Selesai !== null && row.Min_Rater_Selesai !== undefined ? Number(row.Min_Rater_Selesai) : null,
        Organization_Relationship_Type_Id: row.Organization_Relationship_Type_Id ? String(row.Organization_Relationship_Type_Id) : '',
        Flag_Izinkan_Manual: row.Flag_Izinkan_Manual === 'Y' ? 'Y' : 'T',
        Urutan_Tampil: Number(row.Urutan_Tampil ?? idx + 1),
        Catatan_Aturan: row.Catatan_Aturan ?? '',
    }));
}

watch(() => [props.modelValue, props.detail], syncData, { deep: true, immediate: true });
</script>

<style scoped>
.pa-policy-detail {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
}

.pa-policy-detail__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 12rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.82rem;
}

.pa-policy-detail__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.6rem 0.8rem;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(245, 243, 255, 0.65);
    color: #6d28d9;
    font-size: 0.72rem;
    line-height: 1.4;
}

.pa-policy-detail__notice strong {
    display: block;
    font-size: 0.76rem;
    margin-bottom: 0.1rem;
    color: #5b21b6;
}

/* Cockpit & Live Spectrum */
.pa-policy-cockpit {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(245, 243, 255, 0.5));
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03);
}

.pa-policy-cockpit__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-policy-cockpit__title h4 {
    margin: 0.1rem 0 0;
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Spectrum Multi-Bar */
.pa-policy-spectrum-track {
    display: flex;
    height: 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    background: #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.1);
    border: 1px solid rgba(203, 213, 225, 0.8);
}

.pa-policy-spectrum-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    transition: width 220ms ease;
    border-right: 1.5px solid rgba(255, 255, 255, 0.4);
    overflow: hidden;
    padding: 0 0.3rem;
}

.pa-policy-spectrum-label {
    font-size: 0.68rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    white-space: nowrap;
}

/* Presets List */
.pa-policy-presets {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding-top: 0.5rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.pa-policy-presets__label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.64rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-policy-presets__list {
    display: flex;
    gap: 0.35rem;
    overflow-x: auto;
    padding-bottom: 0.2rem;
    -webkit-overflow-scrolling: touch;
}

.pa-preset-chip {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 0.25rem 0.5rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-sm, 6px);
    background: #ffffff;
    cursor: pointer;
    transition: all 140ms ease;
    text-align: left;
    white-space: nowrap;
    flex-shrink: 0;
}

.pa-preset-chip strong {
    font-size: 0.68rem;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-preset-chip small {
    font-size: 0.6rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-preset-chip:hover {
    border-color: #7c3aed;
    background: #f5f3ff;
}

/* Category Rows Section */
.pa-policy-rows-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-policy-rows-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.pa-add-category-select {
    padding: 0.3rem 0.6rem;
    border: 1px dashed #7c3aed;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #f5f3ff;
    color: #6d28d9;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
}

/* Category Card */
.pa-policy-cards-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-policy-category-card {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-left: 4px solid;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-policy-category-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-policy-category-card__title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-category-dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 999px;
    flex-shrink: 0;
}

.pa-policy-category-card__title strong {
    font-size: 0.86rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-category-code {
    display: block;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-policy-category-card__weight-control {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-weight-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-stepper-btn {
    min-width: 1.9rem;
    height: 1.9rem;
    padding: 0 0.35rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    background: #f8fafc;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.68rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-stepper-btn:hover:not(:disabled) {
    background: #e2e8f0;
}

.pa-stepper-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pa-weight-input-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.pa-weight-number-input {
    width: 4.2rem;
    height: 1.9rem;
    padding: 0 1.1rem 0 0.4rem;
    text-align: center;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    font-size: 0.86rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-strong, #0f172a);
}

.pa-weight-unit {
    position: absolute;
    right: 0.35rem;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-text-muted, #64748b);
    pointer-events: none;
}

.pa-remove-row-btn {
    display: grid;
    place-items: center;
    width: 1.9rem;
    height: 1.9rem;
    border: 0;
    border-radius: var(--hcis-radius-xs, 5px);
    background: transparent;
    color: #ef4444;
    cursor: pointer;
    transition: background 120ms ease;
}

.pa-remove-row-btn:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* Specs Grid */
.pa-policy-category-card__specs {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 0.6rem;
}

.pa-spec-box {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 7px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-spec-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.64rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-spec-grid-three {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
}

.pa-spec-grid-two {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.35rem;
}

.pa-mini-field {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-mini-field label {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #475569);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-mini-field label em {
    color: #ef4444;
    font-style: normal;
}

.pa-mini-field input,
.pa-mini-select {
    height: 1.85rem;
    padding: 0 0.4rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    background: #ffffff;
    font: inherit;
    font-size: 0.72rem;
    color: var(--hcis-text-strong, #0f172a);
    width: 100%;
}

.pa-mini-field input[type="number"] {
    text-align: center;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 750;
}

.pa-policy-category-card__bottom {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-mini-field--grow {
    flex: 1;
}

.pa-mini-field--order {
    width: 4.8rem;
    flex-shrink: 0;
}

.pa-note-input {
    width: 100%;
    height: 1.85rem;
    padding: 0 0.45rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    font: inherit;
    font-size: 0.7rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-order-input {
    width: 100%;
    height: 1.85rem;
    text-align: center;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    font-size: 0.72rem;
    font-weight: 700;
}

.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}

.pa-dialog-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-dialog-footer-actions--mobile {
    width: 100%;
}

@media (max-width: 768px) {
    .pa-policy-category-card__specs {
        grid-template-columns: 1fr;
    }

    .pa-policy-category-card__top {
        flex-direction: row;
        align-items: center;
    }

    .pa-policy-category-card__weight-control {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
