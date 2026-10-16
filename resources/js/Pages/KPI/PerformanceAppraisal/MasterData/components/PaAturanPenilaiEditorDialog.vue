<template>
    <HcisDialog
        :model-value="modelValue"
        :title="isNewVersion ? 'Buat Versi Baru Komposisi' : record ? 'Ubah Komposisi Penilai' : 'Buat Komposisi Penilai Baru'"
        kicker="MASTER DATA · KOMPOSISI & BOBOT PENILAI"
        description="Atur metadata kebijakan evaluasi dan susun komposisi bobot penilai per level jabatan."
        icon="diagram-3"
        sentiment="purple"
        variant="glass"
        :size="isMobile ? 'full' : 'xl'"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <form class="pa-policy-form" @submit.prevent="handleSubmit(false)">
            <!-- Stepper / Tab Nav Switcher -->
            <div class="pa-dialog-tabs-bar" role="tablist">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'info'"
                    class="pa-dialog-tab-btn"
                    :class="{ active: activeTab === 'info' }"
                    @click="activeTab = 'info'"
                >
                    <HcisIcon name="card-heading" size="xs" />
                    <span>1. Informasi Komposisi</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'composition'"
                    class="pa-dialog-tab-btn"
                    :class="{ active: activeTab === 'composition' }"
                    @click="activeTab = 'composition'"
                >
                    <HcisIcon name="pie-chart-fill" size="xs" />
                    <span>2. Komposisi &amp; Bobot</span>
                    <HcisBadge
                        :label="isSumExact100 ? '100%' : `${totalBobot}%`"
                        :variant="isSumExact100 ? 'success' : 'warning'"
                        size="xs"
                    />
                </button>
            </div>

            <!-- Tab 1: Policy Info & Level -->
            <div v-show="activeTab === 'info'" class="pa-tab-pane">
                <!-- Notice bar -->
                <div class="pa-policy-form__notice">
                    <HcisIcon name="info-circle-fill" size="xs" class="shrink-0 mt-0.5" />
                    <div>
                        <strong>Lifecycle Komposisi Penilai</strong>
                        <span>
                            Komposisi baru dapat disimpan sebagai <strong>DRAFT</strong> atau langsung diaktifkan bila
                            komposisi bobot penilai sudah mencapai tepat <strong>100.00%</strong>.
                        </span>
                    </div>
                </div>

                <!-- Level Selection -->
                <div class="pa-policy-form__level-box">
                    <div class="pa-select-field">
                        <label for="pa-policy-level">Level Jabatan <em>*</em></label>
                        <select
                            id="pa-policy-level"
                            v-model="form.id_level"
                            :disabled="isEditLocked"
                            required
                            @change="onLevelChange"
                        >
                            <option value="">Pilih level jabatan...</option>
                            <option
                                v-for="lvl in references.level || []"
                                :key="lvl.ID_Level"
                                :value="lvl.ID_Level"
                            >
                                {{ lvl.Nama_Level }} (Level {{ lvl.ID_Level }})
                            </option>
                        </select>
                    </div>

                    <div v-if="selectedLevelObj" class="pa-policy-form__level-badge">
                        <span class="pa-eyebrow">HIERARKI</span>
                        <strong>Tingkat {{ selectedLevelObj.Level_Hierarchy || selectedLevelObj.ID_Level }}</strong>
                    </div>
                </div>

                <!-- Code and Name -->
                <div class="pa-policy-form__grid-two">
                    <div class="pa-field-with-action">
                        <HcisField
                            id="pa-policy-code"
                            v-model="form.kode_policy"
                            label="Kode Komposisi Penilai"
                            placeholder="Pilih level jabatan untuk auto-generate kode..."
                            leading-icon="hash"
                            :disabled="isEditLocked || isNewVersion || !allowCustomCode"
                            required
                            :hint="allowCustomCode ? 'Mode kustom aktif. Gunakan format kapital (contoh: PA-RTR-STAFF).' : 'Dibuat otomatis dari level jabatan.'"
                            @update:model-value="form.kode_policy = ($event || '').toUpperCase()"
                        />
                        <button
                            v-if="!isEditLocked && !isNewVersion"
                            type="button"
                            class="pa-code-toggle-btn"
                            :class="{ active: allowCustomCode }"
                            @click="toggleCustomCode"
                            :title="allowCustomCode ? 'Kunci kembali ke mode otomatis' : 'Ubah kode secara manual'"
                        >
                            <HcisIcon :name="allowCustomCode ? 'lock-fill' : 'pencil-square'" size="2xs" />
                            <span>{{ allowCustomCode ? 'Kunci' : 'Manual' }}</span>
                        </button>
                    </div>
                    <HcisField
                        id="pa-policy-name"
                        v-model="form.nama_policy"
                        label="Nama Komposisi Penilai"
                        placeholder="Contoh: Komposisi Penilai Staff 2026"
                        leading-icon="card-heading"
                        required
                    />
                </div>

                <!-- Date Validity Range -->
                <div class="pa-policy-form__grid-two">
                    <HcisField
                        id="pa-policy-from"
                        v-model="form.berlaku_dari"
                        label="Berlaku Dari"
                        type="date"
                        leading-icon="calendar-event"
                        hint="Tanggal awal kebijakan mulai berlaku."
                    />
                    <HcisField
                        id="pa-policy-to"
                        v-model="form.berlaku_sampai"
                        label="Berlaku Sampai"
                        type="date"
                        leading-icon="calendar-check"
                        hint="Kosongkan jika berlaku permanen."
                    />
                </div>

                <!-- Description -->
                <HcisTextarea
                    id="pa-policy-desc"
                    v-model="form.deskripsi"
                    label="Deskripsi Aturan"
                    placeholder="Contoh: Kebijakan evaluasi kinerja multi-rater untuk level staff dan pelaksana operasional."
                    :rows="2"
                    :maxlength="500"
                />

                <!-- Version Changelog Note if editing or new version -->
                <HcisField
                    id="pa-policy-version-note"
                    v-model="form.catatan_versi"
                    label="Catatan Versi"
                    placeholder="Contoh: Penyesuaian bobot evaluasi atasan dan rekan kerja untuk tahun 2026."
                    leading-icon="file-text"
                    hint="Alasan perubahan atau riwayat revisi aturan ini."
                />
            </div>

            <!-- Tab 2: Rater Composition & Weight Matrix -->
            <div v-show="activeTab === 'composition'" class="pa-tab-pane">
                <!-- Weight Balance Cockpit & Live Multi-Segment Bar -->
                <section class="pa-policy-cockpit" aria-label="Ringkasan Bobot">
                    <div class="pa-policy-cockpit__header">
                        <div class="pa-policy-cockpit__title">
                            <span class="pa-eyebrow">DISTRIBUSI BOBOT KATEGORI</span>
                            <h4>Keseimbangan Porsi Penilai</h4>
                        </div>
                        <div class="pa-policy-cockpit__status">
                            <HcisBadge
                                :label="isSumExact100 ? 'Total 100% (Sempurna & Valid)' : `Total: ${totalBobot}% (${weightDiffLabel})`"
                                :variant="isSumExact100 ? 'success' : 'danger'"
                                :appearance="isSumExact100 ? 'glass' : 'subtle'"
                                size="sm"
                                :dot="true"
                            />
                        </div>
                    </div>

                    <!-- Live Stacked Spectrum Multi-Bar -->
                    <div class="pa-policy-spectrum-track">
                        <div
                            v-for="(row, idx) in rincianRows"
                            :key="`track-seg-${row.pa_rater_category_id || idx}`"
                            class="pa-policy-spectrum-seg"
                            :style="{
                                width: `${Math.max(0, Math.min(100, Number(row.bobot_persen) || 0))}%`,
                                backgroundColor: getCategoryColor(row, idx),
                            }"
                            :title="`${getCategoryName(row)}: ${row.bobot_persen}%`"
                        >
                            <span v-if="Number(row.bobot_persen) >= (isMobile ? 12 : 8)" class="pa-policy-spectrum-label">
                                {{ getCategoryName(row) }} {{ Number(row.bobot_persen) }}%
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
                    </div>
                </section>

                <!-- Category Rows List -->
                <section class="pa-policy-rows-section" aria-label="Daftar Kategori Penilai">
                    <p v-if="memuatRincian" class="pa-rincian-status" data-uji="rincian-memuat">Memuat rincian aturan…</p>
                    <p v-else-if="galatRincian" class="pa-rincian-status is-galat" data-uji="rincian-galat">
                        {{ galatRincian }} Rincian tidak akan diubah saat menyimpan.
                    </p>
                    <div class="pa-policy-rows-section__header">
                        <span class="pa-eyebrow">RINCIAN KATEGORI PENILAI ({{ rincianRows.length }} KATEGORI)</span>
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

                    <div class="pa-policy-cards-stack">
                        <div
                            v-for="(row, index) in rincianRows"
                            :key="`row-${row.pa_rater_category_id || index}`"
                            class="pa-category-editor-card"
                            :style="{ borderLeftColor: getCategoryColor(row, index) }"
                        >
                            <div class="pa-category-editor-card__head">
                                <div class="pa-cat-title-block">
                                    <span class="pa-cat-num">{{ index + 1 }}</span>
                                    <div class="pa-cat-select-wrap">
                                        <select
                                            v-model="row.pa_rater_category_id"
                                            class="pa-category-select"
                                            required
                                        >
                                            <option
                                                v-for="cat in references.kategori_penilai || []"
                                                :key="cat.Id_PA_Rater_Category"
                                                :value="Number(cat.Id_PA_Rater_Category)"
                                            >
                                                {{ cat.Nama_Kategori }} ({{ cat.Kode_Kategori }})
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pa-cat-right-head">
                                    <div class="pa-weight-stepper">
                                        <button
                                            type="button"
                                            class="pa-stepper-btn"
                                            :disabled="Number(row.bobot_persen) <= 0"
                                            @click="adjustWeight(row, -5)"
                                        >
                                            −5
                                        </button>
                                        <div class="pa-weight-input-wrap">
                                            <input
                                                v-model.number="row.bobot_persen"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                required
                                                class="pa-weight-input"
                                            />
                                            <span class="pa-weight-unit">%</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="pa-stepper-btn"
                                            :disabled="Number(row.bobot_persen) >= 100"
                                            @click="adjustWeight(row, 5)"
                                        >
                                            +5
                                        </button>
                                    </div>

                                    <button
                                        v-if="rincianRows.length > 1"
                                        type="button"
                                        class="pa-remove-btn"
                                        title="Hapus Kategori Ini"
                                        @click="removeCategoryRow(index)"
                                    >
                                        <HcisIcon name="trash" size="xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="pa-category-editor-card__fields">
                                <div class="pa-field-quota-group">
                                    <div class="pa-mini-field">
                                        <label>Min Penilai</label>
                                        <input v-model.number="row.min_rater" type="number" min="1" required />
                                    </div>
                                    <div class="pa-mini-field">
                                        <label>Maks Penilai</label>
                                        <input v-model.number="row.maks_rater" type="number" min="1" placeholder="Bebas" />
                                    </div>
                                    <div class="pa-mini-field">
                                        <label>Min Selesai</label>
                                        <input v-model.number="row.min_rater_selesai" type="number" min="1" placeholder="Opsional" />
                                    </div>
                                </div>

                                <div class="pa-field-rel-group">
                                    <div class="pa-mini-field pa-mini-field--grow">
                                        <label>Relasi Organisasi</label>
                                        <select v-model="row.organization_relationship_type_id">
                                            <option :value="null">Semua Relasi / Otomatis</option>
                                            <option
                                                v-for="rel in references.tipe_relasi_organisasi || []"
                                                :key="rel.Id_Organization_Relationship_Type || rel.id"
                                                :value="rel.Id_Organization_Relationship_Type || rel.id"
                                            >
                                                {{ rel.Nama_Relasi || rel.nama }}
                                            </option>
                                        </select>
                                    </div>
                                    <label class="pa-toggle-manual">
                                        <input
                                            type="checkbox"
                                            :checked="row.flag_izinkan_manual === 'Y'"
                                            @change="row.flag_izinkan_manual = $event.target.checked ? 'Y' : 'T'"
                                        />
                                        <span>Manual</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Error alert -->
            <HcisResultState
                v-if="error"
                status="danger"
                variant="flat"
                layout="inline"
                title="Gagal menyimpan aturan"
                :description="error"
                :show-retry="false"
            />
        </form>

        <template #footer>
            <div class="pa-editor-footer-actions" :class="{ 'pa-editor-footer-actions--mobile': isMobile }">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    :class="{ 'flex-1': isMobile }"
                    :disabled="saving"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    variant="secondary"
                    label="Simpan sebagai DRAFT"
                    size="sm"
                    leading-icon="clock-history"
                    :class="{ 'flex-1': isMobile }"
                    :loading="saving"
                    :disabled="saving || !isHeaderValid"
                    @click="handleSubmit(false)"
                />
                <HcisButton
                    variant="primary"
                    label="Simpan &amp; Aktifkan"
                    size="sm"
                    leading-icon="check-circle-fill"
                    :class="{ 'w-100 mt-1': isMobile }"
                    :loading="saving"
                    :disabled="saving || !isHeaderValid || !isSumExact100"
                    @click="handleSubmit(true)"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import paApi from '../../api/paApi.js';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisTextarea,
} from '@/components/ui';

defineOptions({ name: 'PaAturanPenilaiEditorDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    record: { type: Object, default: null },
    isNewVersion: { type: Boolean, default: false },
    defaultLevelId: { type: [String, Number], default: '' },
    defaultLevel: { type: [String, Number], default: '' },
    references: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'save']);

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

const activeTab = ref('info');
const selectedCategoryToAdd = ref('');
const allowCustomCode = ref(false);

const form = reactive({
    id_level: '',
    kode_policy: '',
    nama_policy: '',
    berlaku_dari: '',
    berlaku_sampai: '',
    deskripsi: '',
    catatan_versi: '',
});

const rincianRows = ref([]);

/* Rincian aturan yang SUDAH ada dimuat dari server setiap dialog dibuka untuk sebuah aturan.
   Sampai 8 Oktober 2026 dialog tidak pernah memuatnya: rincian kosong diisi template "180°"
   (Atasan 70 / Rekan 30, tanpa relasi), lalu tersimpan saat admin sekadar mengganti nama —
   komposisi aturan berubah tanpa disadari. Rincian juga tidak dikosongkan antar-aturan,
   sehingga membuka aturan lain bisa membawa rincian aturan sebelumnya. */
const memuatRincian = ref(false);
const galatRincian = ref('');
const rincianBerubah = ref(false);
let nomorMuat = 0;

async function muatRincianAturan(policyId) {
    const nomor = ++nomorMuat;
    memuatRincian.value = true;
    galatRincian.value = '';
    try {
        const r = await paApi.masterDetail('aturan-penilai', policyId);
        if (nomor !== nomorMuat) return;
        const detail = Array.isArray(r?.detail) ? r.detail : [];
        rincianRows.value = detail.map((d) => ({
            pa_rater_category_id: Number(d.PA_Rater_Category_Id),
            bobot_persen: Number(d.Bobot_Persen),
            min_rater: d.Min_Rater != null ? Number(d.Min_Rater) : 1,
            maks_rater: d.Maks_Rater != null ? Number(d.Maks_Rater) : null,
            min_rater_selesai: d.Min_Rater_Selesai != null ? Number(d.Min_Rater_Selesai) : null,
            flag_izinkan_manual: d.Flag_Izinkan_Manual === 'Y' ? 'Y' : 'T',
            organization_relationship_type_id: d.Organization_Relationship_Type_Id != null
                ? Number(d.Organization_Relationship_Type_Id)
                : null,
            catatan_aturan: d.Catatan_Aturan || '',
        }));
        rincianBerubah.value = false;
    } catch (e) {
        if (nomor === nomorMuat) galatRincian.value = e?.message || 'Rincian aturan gagal dimuat.';
    } finally {
        if (nomor === nomorMuat) memuatRincian.value = false;
    }
}

// Perubahan apa pun pada rincian sesudah dimuat menandainya perlu disimpan.
watch(rincianRows, () => {
    if (!memuatRincian.value) rincianBerubah.value = true;
}, { deep: true });

const selectedLevelObj = computed(() => {
    if (!form.id_level) return null;
    return (props.references.level || []).find((lvl) => Number(lvl.ID_Level) === Number(form.id_level)) || null;
});

const isEditLocked = computed(() => Boolean(props.record) && !props.isNewVersion);

function generatePolicyCode(lvl) {
    if (!lvl) return '';
    const cleanLevel = String(lvl.Nama_Level || `LEVEL_${lvl.ID_Level}`)
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    return `PA-RTR-${cleanLevel}`;
}

function generatePolicyName(lvl) {
    if (!lvl) return '';
    return `Komposisi Penilai ${lvl.Nama_Level || `Level ${lvl.ID_Level}`}`;
}

function toggleCustomCode() {
    allowCustomCode.value = !allowCustomCode.value;
    if (!allowCustomCode.value && selectedLevelObj.value) {
        form.kode_policy = generatePolicyCode(selectedLevelObj.value);
    }
}

function onLevelChange() {
    if (form.id_level && !props.record) {
        const lvl = selectedLevelObj.value;
        if (!allowCustomCode.value || !form.kode_policy) {
            form.kode_policy = generatePolicyCode(lvl);
        }
        if (!form.nama_policy) {
            form.nama_policy = generatePolicyName(lvl);
        }
    }
}

function syncForm() {
    activeTab.value = 'info';
    selectedCategoryToAdd.value = '';
    allowCustomCode.value = false;

    if (props.record) {
        form.id_level = props.record.ID_Level ? Number(props.record.ID_Level) : '';
        form.kode_policy = props.record.Kode_Policy || '';
        form.nama_policy = props.isNewVersion
            ? `${props.record.Nama_Policy || ''} (Versi ${Number(props.record.Versi || 1) + 1})`
            : props.record.Nama_Policy || '';
        form.berlaku_dari = props.record.Berlaku_Dari ? props.record.Berlaku_Dari.substring(0, 10) : '';
        form.berlaku_sampai = props.record.Berlaku_Sampai ? props.record.Berlaku_Sampai.substring(0, 10) : '';
        form.deskripsi = props.record.Deskripsi || '';
        form.catatan_versi = props.isNewVersion
            ? `Revisi dari versi ${props.record.Versi || 1}`
            : props.record.Catatan_Versi || '';

        // Rincian aturan ini (atau versi asal untuk versi baru) — bukan template.
        rincianRows.value = [];
        const policyId = props.record.Id_PA_Rater_Policy ?? props.record.id;
        if (policyId) {
            muatRincianAturan(policyId);
        }
    } else {
        const today = new Date().toISOString().substring(0, 10);
        const effectiveLevelId = props.defaultLevelId || props.defaultLevel;
        form.id_level = effectiveLevelId ? Number(effectiveLevelId) : '';
        form.kode_policy = '';
        form.nama_policy = '';
        form.berlaku_dari = today;
        form.berlaku_sampai = '';
        form.deskripsi = '';
        form.catatan_versi = '';

        if (form.id_level) {
            const lvl = (props.references.level || []).find((l) => Number(l.ID_Level) === Number(form.id_level));
            if (lvl) {
                form.kode_policy = generatePolicyCode(lvl);
                form.nama_policy = generatePolicyName(lvl);
            }
        }
        rincianRows.value = [];
        applyAssessmentModel(assessmentModels[1]); // Titik awal untuk aturan BARU saja.
        rincianBerubah.value = true;
    }
}

const totalBobot = computed(() => {
    const sum = rincianRows.value.reduce((acc, row) => acc + (Number(row.bobot_persen) || 0), 0);
    return Math.round(sum * 100) / 100;
});

const isSumExact100 = computed(() => totalBobot.value === 100);

const weightDiffLabel = computed(() => {
    const diff = 100 - totalBobot.value;
    if (diff > 0) return `Kurang ${diff.toFixed(1)}%`;
    if (diff < 0) return `Lebih ${Math.abs(diff).toFixed(1)}%`;
    return 'Pas 100%';
});

const unaddedCategories = computed(() => {
    const allCategories = props.references?.kategori_penilai || [];
    const usedIds = new Set(rincianRows.value.map((r) => Number(r.pa_rater_category_id)));
    return allCategories.filter((c) => !usedIds.has(Number(c.Id_PA_Rater_Category)));
});

const isHeaderValid = computed(() => {
    return Boolean(form.id_level) && Boolean(form.kode_policy?.trim()) && Boolean(form.nama_policy?.trim());
});

const categoryColorPalette = ['#4f46e5', '#7c3aed', '#059669', '#d97706', '#0284c7', '#db2777'];

const assessmentModels = [
    {
        name: 'Top-Down',
        caption: '100% Atasan Langsung',
        distribution: [{ codeMatch: 'ATASAN', weight: 100, min: 1, maks: 1 }],
    },
    {
        name: '180° Evaluasi',
        caption: '70% Atasan / 30% Rekan',
        distribution: [
            { codeMatch: 'ATASAN', weight: 70, min: 1, maks: 1 },
            { codeMatch: 'PEER', weight: 30, min: 2, maks: 4 },
        ],
    },
    {
        name: '360° Multi-Rater',
        caption: '50% Atasan / 25% Peer / 25% Bawahan',
        distribution: [
            { codeMatch: 'ATASAN', weight: 50, min: 1, maks: 1 },
            { codeMatch: 'PEER', weight: 25, min: 2, maks: 4 },
            { codeMatch: 'BAWAHAN', weight: 25, min: 2, maks: 4 },
        ],
    },
    {
        name: '360° + Self',
        caption: '40% Atasan / 20% Peer / 20% Sub / 20% Diri',
        distribution: [
            { codeMatch: 'ATASAN', weight: 40, min: 1, maks: 1 },
            { codeMatch: 'PEER', weight: 20, min: 2, maks: 3 },
            { codeMatch: 'BAWAHAN', weight: 20, min: 2, maks: 3 },
            { codeMatch: 'SELF', weight: 20, min: 1, maks: 1 },
        ],
    },
];

function getCategoryColor(row, index) {
    const allCategories = props.references?.kategori_penilai || [];
    const cat = allCategories.find((c) => Number(c.Id_PA_Rater_Category) === Number(row.pa_rater_category_id));
    const code = (cat?.Kode_Kategori || '').toUpperCase();
    if (code.includes('ATASAN') || code.includes('SUPERIOR')) return '#4f46e5';
    if (code.includes('PEER') || code.includes('REKAN')) return '#7c3aed';
    if (code.includes('BAWAHAN') || code.includes('SUBORDINAT')) return '#059669';
    if (code.includes('SELF') || code.includes('DIRI')) return '#d97706';
    return categoryColorPalette[index % categoryColorPalette.length];
}

function getCategoryName(row) {
    const allCategories = props.references?.kategori_penilai || [];
    const cat = allCategories.find((c) => Number(c.Id_PA_Rater_Category) === Number(row.pa_rater_category_id));
    return cat ? cat.Nama_Kategori : `Kategori ${row.pa_rater_category_id}`;
}

function adjustWeight(row, delta) {
    row.bobot_persen = Math.max(0, Math.min(100, (Number(row.bobot_persen) || 0) + delta));
}

function addCategoryRow() {
    if (!selectedCategoryToAdd.value) return;
    const catId = Number(selectedCategoryToAdd.value);
    rincianRows.value.push({
        pa_rater_category_id: catId,
        bobot_persen: 0,
        min_rater: 1,
        maks_rater: null,
        min_rater_selesai: 1,
        flag_izinkan_manual: 'T',
        organization_relationship_type_id: null,
        catatan_aturan: '',
    });
    selectedCategoryToAdd.value = '';
}

function removeCategoryRow(index) {
    rincianRows.value.splice(index, 1);
}

function applyAssessmentModel(model) {
    const allCategories = props.references?.kategori_penilai || [];
    const newRows = [];

    model.distribution.forEach((dist) => {
        const found = allCategories.find(
            (c) =>
                (c.Kode_Kategori || '').toUpperCase().includes(dist.codeMatch) ||
                (c.Nama_Kategori || '').toUpperCase().includes(dist.codeMatch),
        );
        if (found) {
            newRows.push({
                pa_rater_category_id: Number(found.Id_PA_Rater_Category),
                bobot_persen: dist.weight,
                min_rater: dist.min,
                maks_rater: dist.maks,
                min_rater_selesai: dist.min,
                flag_izinkan_manual: dist.codeMatch === 'PEER' ? 'Y' : 'T',
                organization_relationship_type_id: null,
                catatan_aturan: '',
            });
        }
    });

    if (newRows.length) {
        rincianRows.value = newRows;
    }
}

function handleSubmit(activateNow = false) {
    if (!isHeaderValid.value) {
        activeTab.value = 'info';
        return;
    }

    emit('save', {
        id_level: Number(form.id_level),
        kode_policy: String(form.kode_policy).trim().toUpperCase(),
        nama_policy: String(form.nama_policy).trim(),
        berlaku_dari: form.berlaku_dari || null,
        berlaku_sampai: form.berlaku_sampai || null,
        deskripsi: form.deskripsi ? String(form.deskripsi).trim() : null,
        catatan_versi: form.catatan_versi ? String(form.catatan_versi).trim() : null,
        /* Aturan yang disunting tanpa menyentuh rincian TIDAK mengirim rincian, supaya
           mengganti nama tidak pernah menulis ulang komposisinya. Versi baru selalu
           mengirim (barisnya milik aturan baru). */
        rincian: (rincianBerubah.value || props.isNewVersion || !props.record) && !memuatRincian.value && !galatRincian.value
            ? rincianRows.value.map((r, idx) => ({
            pa_rater_category_id: Number(r.pa_rater_category_id),
            bobot_persen: Number(r.bobot_persen),
            min_rater: Number(r.min_rater || 1),
            maks_rater: r.maks_rater ? Number(r.maks_rater) : null,
            min_rater_selesai: r.min_rater_selesai ? Number(r.min_rater_selesai) : null,
            flag_izinkan_manual: r.flag_izinkan_manual === 'Y' ? 'Y' : 'T',
            organization_relationship_type_id: r.organization_relationship_type_id || null,
            catatan_aturan: r.catatan_aturan || null,
            urutan_tampil: idx + 1,
        }))
            : [],
        aktifkan_sekarang: Boolean(activateNow),
    });
}

watch(() => [props.modelValue, props.record, props.isNewVersion], syncForm, { immediate: true });
</script>

<style scoped>
.pa-rincian-status {
    margin: 0 0 0.5rem;
    font-size: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-rincian-status.is-galat {
    color: #b91c1c;
}

.pa-policy-form {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
}

/* Tab Switcher */
.pa-dialog-tabs-bar {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.9);
}

.pa-dialog-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1;
    justify-content: center;
    padding: 0.35rem 0.65rem;
    border: 0;
    border-radius: var(--hcis-radius-sm, 6px);
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font: inherit;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-dialog-tab-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-tab-pane {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Notice bar */
.pa-policy-form__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.6rem 0.8rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(238, 242, 255, 0.65);
    color: #4338ca;
    font-size: 0.72rem;
    line-height: 1.4;
}

.pa-policy-form__notice strong {
    display: block;
    font-size: 0.76rem;
    margin-bottom: 0.1rem;
    color: #3730a3;
}

/* Level Box */
.pa-policy-form__level-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.8));
}

.pa-select-field {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.pa-select-field label {
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #334155);
}

.pa-select-field label em {
    color: #ef4444;
    font-style: normal;
}

.pa-select-field select {
    min-height: 2.1rem;
    padding: 0.35rem 0.55rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 6px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    font: inherit;
    font-size: 0.78rem;
    width: 100%;
}

.pa-policy-form__level-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.1rem;
    flex-shrink: 0;
}

.pa-policy-form__level-badge strong {
    font-size: 0.78rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-policy-form__grid-two {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.pa-field-with-action {
    position: relative;
    display: flex;
    flex-direction: column;
}

.pa-code-toggle-btn {
    position: absolute;
    top: 0;
    right: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.12rem 0.4rem;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 4px;
    background: rgba(238, 242, 255, 0.7);
    color: #4338ca;
    font-size: 0.62rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-code-toggle-btn:hover {
    background: rgba(99, 102, 241, 0.15);
}

.pa-code-toggle-btn.active {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Cockpit in Tab 2 */
.pa-policy-cockpit {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.6), rgba(255, 255, 255, 0.95));
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

.pa-policy-spectrum-track {
    display: flex;
    height: 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    background: #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
}

.pa-policy-spectrum-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: width 200ms ease;
    overflow: hidden;
    white-space: nowrap;
}

.pa-policy-spectrum-label {
    font-size: 0.68rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    color: #ffffff;
    padding: 0 0.35rem;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

.pa-policy-presets {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    padding-top: 0.4rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.pa-policy-presets__label {
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
    padding: 0.22rem 0.5rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    background: #ffffff;
    cursor: pointer;
    transition: all 120ms ease;
    text-align: left;
    white-space: nowrap;
    flex-shrink: 0;
}

.pa-preset-chip:hover {
    border-color: #4f46e5;
    background: #f5f3ff;
}

.pa-preset-chip strong {
    font-size: 0.68rem;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-preset-chip small {
    font-size: 0.6rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Category Rows */
.pa-policy-rows-section {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-policy-rows-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}

.pa-add-category-select {
    padding: 0.25rem 0.5rem;
    border: 1px dashed rgba(99, 102, 241, 0.5);
    border-radius: var(--hcis-radius-xs, 6px);
    background: #ffffff;
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 750;
    cursor: pointer;
}

.pa-policy-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-category-editor-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-left-width: 4px;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-category-editor-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-cat-title-block {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
    min-width: 0;
}

.pa-cat-num {
    display: grid;
    place-items: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.66rem;
    font-weight: 800;
    flex-shrink: 0;
}

.pa-cat-select-wrap {
    flex: 1;
    min-width: 0;
}

.pa-category-select {
    width: 100%;
    padding: 0.25rem 0.45rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 5px);
    background: #ffffff;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-cat-right-head {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-weight-stepper {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-stepper-btn {
    padding: 0.15rem 0.35rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 4px);
    background: #f8fafc;
    font-size: 0.66rem;
    font-weight: 800;
    cursor: pointer;
}

.pa-weight-input-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-weight-input {
    width: 3.2rem;
    height: 1.85rem;
    text-align: center;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 4px);
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.84rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-weight-unit {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-text-muted, #64748b);
}

.pa-remove-btn {
    padding: 0.25rem;
    border: 0;
    border-radius: var(--hcis-radius-xs, 4px);
    background: transparent;
    color: #ef4444;
    cursor: pointer;
}

.pa-remove-btn:hover {
    background: #fee2e2;
}

.pa-category-editor-card__fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
    padding-top: 0.4rem;
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

.pa-field-quota-group {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem;
}

.pa-field-rel-group {
    display: flex;
    align-items: center;
    gap: 0.45rem;
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
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-mini-field input,
.pa-mini-field select {
    height: 1.8rem;
    padding: 0 0.35rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 4px);
    font-size: 0.72rem;
    width: 100%;
}

.pa-mini-field input[type="number"] {
    text-align: center;
}

.pa-mini-field--grow {
    flex: 1;
    min-width: 0;
}

.pa-toggle-manual {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    color: var(--hcis-text-strong, #334155);
    cursor: pointer;
    margin-top: 0.6rem;
    white-space: nowrap;
}

.pa-editor-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-editor-footer-actions--mobile {
    width: 100%;
}

@media (max-width: 768px) {
    .pa-policy-form__grid-two {
        grid-template-columns: 1fr;
    }

    .pa-category-editor-card__fields {
        grid-template-columns: 1fr;
    }

    .pa-policy-form__level-box {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-policy-form__level-badge {
        align-items: flex-start;
    }
}
</style>
