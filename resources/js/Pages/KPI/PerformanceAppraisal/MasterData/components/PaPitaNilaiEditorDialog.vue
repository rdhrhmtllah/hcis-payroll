<template>
    <HcisDialog
        :model-value="modelValue"
        :title="record ? 'Ubah Rentang Nilai' : 'Tambah Rentang Nilai Baru'"
        kicker="MASTER DATA · RENTANG NILAI"
        description="Kelola batas rentang skor dengan batas atas eksklusif dan target distribusi."
        icon="bar-chart-steps"
        sentiment="warning"
        variant="glass"
        :size="isMobile ? 'full' : 'lg'"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <form ref="formRef" class="pa-range-editor" @submit.prevent="handleSubmit">
            <!-- Galat dari server DI ATAS, supaya terlihat tanpa menggulir (dulu di dasar form). -->
            <div ref="galatRef">
                <HcisResultState
                    v-if="error"
                    status="danger"
                    variant="flat"
                    layout="inline"
                    title="Perubahan ditolak server"
                    :description="error"
                    :show-retry="false"
                />
            </div>

            <!-- Sebab tombol simpan belum bisa ditekan — dulu tombolnya mati tanpa penjelasan. -->
            <div v-if="alasanTidakValid.length" class="pa-range-editor__invalid" role="alert">
                <HcisIcon name="exclamation-triangle-fill" size="xs" />
                <div>
                    <strong>Belum bisa disimpan:</strong>
                    <ul>
                        <li v-for="alasan in alasanTidakValid" :key="alasan">{{ alasan }}</li>
                    </ul>
                </div>
            </div>

            <!-- Contextual Rules Notice -->
            <div class="pa-range-editor__notice">
                <HcisIcon name="info-circle-fill" size="xs" />
                <div>
                    <strong>Ketentuan Batas Rentang &amp; Kesinambungan</strong>
                    <span>
                        Batas atas bersifat <strong>EKSKLUSIF</strong> (<code>Nilai_Dari &le; x &lt; Nilai_Sampai</code>).
                        Rentang dalam satu kelompok wajib bersinambung penuh tanpa lubang dari 0.00 hingga 999.99 (terbuka
                        ke atas).
                    </span>
                </div>
            </div>

            <!-- Group Selection (Score Type & Score Usage) -->
            <div class="pa-range-editor__grid-two">
                <div class="pa-select-field">
                    <label for="pa-range-score-type">Tipe Penilaian (Score_Type) <em>*</em></label>
                    <select
                        id="pa-range-score-type"
                        v-model="form.sumbu"
                        :disabled="isTypeUsageLocked"
                        required
                    >
                        <option value="">Pilih tipe penilaian...</option>
                        <option
                            v-for="s in sumbuPilihan"
                            :key="s.Kode_Type"
                            :value="s.Kode_Type"
                        >
                            {{ s.Nama_Type }} ({{ s.Kode_Type }})
                        </option>
                    </select>
                </div>

                <div class="pa-select-field">
                    <label for="pa-range-score-usage">Kegunaan Rentang (Score_Usage) <em>*</em></label>
                    <select
                        id="pa-range-score-usage"
                        v-model="form.kegunaan"
                        disabled
                        required
                    >
                        <option
                            v-for="u in kegunaanPilihan"
                            :key="u.Kode_Usage"
                            :value="u.Kode_Usage"
                        >
                            {{ u.Nama_Usage }} ({{ u.Kode_Usage }})
                        </option>
                    </select>
                </div>
            </div>

            <!-- Code and Name -->
            <div class="pa-range-editor__grid-two">
                <HcisField
                    id="pa-range-code"
                    v-model="form.kode_range"
                    label="Kode Grade / Rentang"
                    placeholder="Contoh: A, B_PLUS, RENDAH"
                    leading-icon="hash"
                    required
                    hint="Huruf kapital dan garis bawah. Contoh: A, B_PLUS, RENDAH."
                    @update:model-value="form.kode_range = ($event || '').toUpperCase()"
                />
                <HcisField
                    id="pa-range-name"
                    v-model="form.nama_range"
                    label="Nama Grade / Rentang"
                    placeholder="Contoh: Istimewa, Sangat Baik"
                    leading-icon="card-heading"
                    required
                />
            </div>

            <!-- Adjacent Range Continuity Guide -->
            <div v-if="adjacentInfo" class="pa-range-editor__adjacent-guide">
                <HcisIcon name="link-45deg" size="sm" color="brand" />
                <div class="pa-range-editor__adjacent-content">
                    <span class="pa-eyebrow">PANDUAN KESINAMBUNGAN</span>
                    <div class="pa-range-editor__adjacent-items">
                        <span v-if="adjacentInfo.prev">
                            Rentang sebelumnya (<strong>{{ adjacentInfo.prev.Kode_Range }}</strong>) berakhir di:
                            <code>{{ adjacentInfo.prev.Nilai_Sampai }}</code>
                        </span>
                        <span v-if="adjacentInfo.next">
                            Rentang berikutnya (<strong>{{ adjacentInfo.next.Kode_Range }}</strong>) mulai di:
                            <code>{{ adjacentInfo.next.Nilai_Dari }}</code>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Boundary Number Inputs -->
            <section class="pa-range-editor__boundaries">
                <div class="pa-range-editor__boundaries-head">
                    <span class="pa-eyebrow">BATAS RENTANG NILAI</span>
                    <small>Batas bawah inklusif, batas atas eksklusif</small>
                </div>

                <div class="pa-range-editor__grid-two">
                    <div class="pa-range-editor__boundary-input">
                        <label for="pa-range-nilai-dari">
                            <span>Nilai Dari (Batas Bawah) <em>*</em></span>
                            <small class="text-success-600 font-semibold">[Inklusif &ge;]</small>
                        </label>
                        <input
                            id="pa-range-nilai-dari"
                            v-model.number="form.nilai_dari"
                            type="number"
                            step="0.01"
                            min="0"
                            max="999.99"
                            required
                            class="pa-range-input-number"
                            placeholder="0.00"
                        />
                    </div>

                    <div class="pa-range-editor__boundary-input">
                        <div class="pa-range-editor__boundary-top-header">
                            <label for="pa-range-nilai-sampai">
                                <span>Nilai Sampai (Batas Atas) <em>*</em></span>
                                <small class="text-amber-600 font-semibold">[Eksklusif &lt;]</small>
                            </label>
                            <button
                                type="button"
                                class="pa-range-max-toggle"
                                @click="form.nilai_sampai = 999.99"
                            >
                                Set 999.99 (&infin;)
                            </button>
                        </div>
                        <input
                            id="pa-range-nilai-sampai"
                            v-model.number="form.nilai_sampai"
                            type="number"
                            step="0.01"
                            min="0.01"
                            max="999.99"
                            required
                            class="pa-range-input-number"
                            placeholder="999.99"
                        />
                    </div>
                </div>

                <!-- Dynamic Human Interpretation Preview -->
                <div class="pa-range-editor__interpretation">
                    <HcisIcon name="info-circle" size="2xs" />
                    <span>
                        Rentang ini mencakup nilai <strong>{{ formattedDari }}</strong> hingga di bawah
                        <strong>{{ formattedSampai }}</strong>
                        <template v-if="Number(form.nilai_sampai) >= 999.99"> (Terbuka ke atas hingga nilai tertinggi)</template>
                        <template v-else> (yaitu {{ formattedDari }} s/d {{ (Number(form.nilai_sampai) - 0.01).toFixed(2) }})</template>.
                    </span>
                </div>
            </section>

            <!-- Target Distribution (Only for GRADE usage) -->
            <section v-if="isGradeUsage" class="pa-range-editor__target-section">
                <div class="pa-range-editor__target-head">
                    <div>
                        <span class="pa-eyebrow">TARGET FORCED DISTRIBUTION · METODE PITA</span>
                        <h4>Target Kuota Distribusi (%)</h4>
                        <small class="pa-range-editor__catatan-metode" data-uji="target-tak-dipakai-kurva">
                            Tidak dipakai metode kurva — siklus kurva membandingkan sebarannya dengan
                            proporsi teoretis dari batas z.
                        </small>
                    </div>
                    <HcisBadge
                        :label="`Total Semua Rentang: ${otherTargetsSum + (Number(form.target_persen) || 0)}%`"
                        :variant="isTargetBudgetExact100 ? 'success' : 'warning'"
                        size="xs"
                    />
                </div>

                <div class="pa-range-editor__grid-two">
                    <HcisField
                        id="pa-range-target-persen"
                        v-model="form.target_persen"
                        label="Target Persentase (%)"
                        type="number"
                        placeholder="Contoh: 20"
                        leading-icon="percent"
                        hint="Porsi target forced distribution untuk grade ini (total semua grade harus 100%)."
                    />
                    <div class="pa-range-editor__target-budget-info">
                        <small>Alokasi Rentang Lainnya: <strong>{{ otherTargetsSum }}%</strong></small>
                        <small>Sisa Kuota Ideal: <strong>{{ Math.max(0, 100 - otherTargetsSum) }}%</strong></small>
                    </div>
                </div>
            </section>

            <!-- Batas z (grade kurva) — hanya untuk grade nilai akhir -->
            <section v-if="isGradeUsage && form.sumbu === 'FINAL'" class="pa-range-editor__target-section">
                <div class="pa-range-editor__target-head">
                    <div>
                        <span class="pa-eyebrow">KURVA NORMAL</span>
                        <h4>Batas Bawah z</h4>
                    </div>
                </div>
                <div class="pa-range-editor__grid-two">
                    <HcisField
                        id="pa-range-batas-z"
                        v-model="form.batas_z"
                        label="Batas z (≥)"
                        type="number"
                        placeholder="Kosong = grade terendah"
                        hint="Batas bawah grade dalam simpangan baku dari rata-rata grup. Contoh: 0 = mulai dari rata-rata, 1 = rata-rata + 1 SD."
                        data-uji="batas-z"
                    />
                    <div class="pa-range-editor__target-budget-info">
                        <small v-if="form.batas_z === null || form.batas_z === ''">Grade terendah: menampung semua nilai di bawah grade berikutnya.</small>
                        <small v-else>
                            Contoh grup dengan rata-rata 70 dan SD 15 → grade ini mulai dari
                            <strong>{{ (70 + Number(form.batas_z) * 15).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}</strong>.
                        </small>
                    </div>
                </div>
            </section>

            <!-- Color Palette Picker -->
            <section class="pa-range-editor__color-section">
                <div class="pa-range-editor__color-head">
                    <span class="pa-eyebrow">WARNA &amp; IDENTITAS VISUAL</span>
                    <small>Pilih palet tema untuk kartu, badge, dan grafik</small>
                </div>

                <div class="pa-range-editor__color-palette">
                    <button
                        v-for="swatch in colorSwatches"
                        :key="swatch.color"
                        type="button"
                        class="pa-color-swatch-btn"
                        :class="{ active: form.warna?.toLowerCase() === swatch.color.toLowerCase() }"
                        :style="{ backgroundColor: swatch.color }"
                        :title="swatch.name"
                        @click="form.warna = swatch.color"
                    >
                        <HcisIcon
                            v-if="form.warna?.toLowerCase() === swatch.color.toLowerCase()"
                            name="check-lg"
                            size="2xs"
                            color="white"
                        />
                    </button>
                </div>

                <div class="pa-range-editor__color-input-wrap">
                    <div class="pa-color-preview-box" :style="{ backgroundColor: form.warna || '#4f46e5' }" />
                    <HcisField
                        id="pa-range-color-hex"
                        v-model="form.warna"
                        label="Kode Warna Hex"
                        placeholder="#4F46E5"
                        leading-icon="palette"
                        size="sm"
                    />
                    <HcisField
                        id="pa-range-order"
                        v-model="form.urutan_tampil"
                        label="Urutan Tampil"
                        type="number"
                        min="1"
                        placeholder="1"
                        leading-icon="sort-numeric-down"
                        size="sm"
                    />
                </div>
            </section>

            <!-- Live Card Preview -->
            <div class="pa-range-editor__preview-card">
                <span class="pa-eyebrow">PREVIEW TAMPILAN RENTANG NILAI</span>
                <div class="pa-range-preview-badge" :style="{ borderColor: form.warna || '#4f46e5' }">
                    <div class="pa-range-preview-dot" :style="{ backgroundColor: form.warna || '#4f46e5' }" />
                    <strong>{{ form.kode_range || 'KODE' }} · {{ form.nama_range || 'Nama Rentang Nilai' }}</strong>
                    <span class="pa-range-preview-interval">
                        [{{ formattedDari }} &mdash; {{ Number(form.nilai_sampai) >= 999.99 ? '&infin;' : formattedSampai }})
                    </span>
                    <HcisBadge
                        v-if="isGradeUsage && form.target_persen"
                        :label="`Target: ${form.target_persen}%`"
                        variant="brand"
                        appearance="subtle"
                        size="xs"
                    />
                </div>
            </div>

        </form>

        <template #footer>
            <HcisButton
                variant="secondary"
                label="Batal"
                size="sm"
                :disabled="saving"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                variant="primary"
                label="Simpan Rentang Nilai"
                size="sm"
                leading-icon="check2-circle"
                :loading="saving"
                :disabled="saving || !isValidForm"
                @click="handleSubmit"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisResultState,
} from '@/components/ui';

defineOptions({ name: 'PaPitaNilaiEditorDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    record: { type: Object, default: null },
    existingRows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    defaultType: { type: String, default: 'FINAL' },
    defaultUsage: { type: String, default: 'GRADE' },
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

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});

const form = reactive({
    sumbu: 'FINAL',
    kegunaan: 'GRADE',
    kode_range: '',
    nama_range: '',
    nilai_dari: 0,
    // Pita teratas terbuka (999,99): nilai bisa melewati 100; server menolak ujung atas yang lebih rendah.
    nilai_sampai: 999.99,
    target_persen: null,
    batas_z: null,
    warna: '#16a34a',
    urutan_tampil: 1,
});

const colorSwatches = [
    { name: 'Hijau Tua', color: '#16a34a' },
    { name: 'Hijau Muda', color: '#4ade80' },
    { name: 'Lime', color: '#a3e635' },
    { name: 'Kuning', color: '#facc15' },
    { name: 'Amber', color: '#f59e0b' },
    { name: 'Oranye', color: '#fb923c' },
    { name: 'Merah', color: '#ef4444' },
    { name: 'Indigo', color: '#4f46e5' },
    { name: 'Ungu', color: '#8b5cf6' },
    { name: 'Teal', color: '#14b8a6' },
    { name: 'Sky Blue', color: '#0ea5e9' },
    { name: 'Slate', color: '#64748b' },
];

const isTypeUsageLocked = computed(() => Boolean(props.record?.Id_PA_Score_Range));

const isGradeUsage = computed(() => String(form.kegunaan).toUpperCase() === 'GRADE');

/* Editor ini kini HANYA untuk pita HAV Map (UAT 8 Okt 2026): grade nilai akhir ditentukan kurva
   dan kategorinya disembunyikan. "Nilai Akhir" tidak ditawarkan sebagai tipe, dan kegunaan
   dikunci HAVMAP. Baris lama yang memang FINAL/GRADE tetap menampilkan nilainya sendiri. */
const sumbuPilihan = computed(() => (props.references?.sumbu || [])
    .filter((s) => s.Kode_Type !== 'FINAL' || props.record?.Score_Type === 'FINAL'));
const kegunaanPilihan = computed(() => (props.references?.kegunaan || [])
    .filter((u) => u.Kode_Usage === 'HAVMAP' || u.Kode_Usage === props.record?.Score_Usage));

const formattedDari = computed(() => {
    const val = Number(form.nilai_dari);
    return isNaN(val) ? '0.00' : val.toFixed(2);
});

const formattedSampai = computed(() => {
    const val = Number(form.nilai_sampai);
    return isNaN(val) ? '100.00' : val.toFixed(2);
});

const siblingBands = computed(() => {
    const sumbu = form.sumbu;
    const kegunaan = form.kegunaan;
    if (!sumbu || !kegunaan) return [];
    return props.existingRows
        .filter(
            (r) =>
                r.Score_Type === sumbu &&
                r.Score_Usage === kegunaan &&
                (!props.record || r.Id_PA_Score_Range !== props.record.Id_PA_Score_Range),
        )
        .sort((a, b) => Number(a.Nilai_Dari) - Number(b.Nilai_Dari));
});

const adjacentInfo = computed(() => {
    const currentFrom = Number(form.nilai_dari) || 0;
    const currentTo = Number(form.nilai_sampai) || 100;
    const prev = siblingBands.value
        .filter((b) => Number(b.Nilai_Sampai) <= currentFrom + 0.05)
        .slice(-1)[0] || null;
    const next = siblingBands.value.find((b) => Number(b.Nilai_Dari) >= currentTo - 0.05) || null;
    if (!prev && !next) return null;
    return { prev, next };
});

const otherTargetsSum = computed(() => {
    if (!isGradeUsage.value) return 0;
    const sum = siblingBands.value.reduce((acc, b) => acc + (Number(b.Target_Persen) || 0), 0);
    return Math.round(sum * 100) / 100;
});

const isTargetBudgetExact100 = computed(() => {
    const current = Number(form.target_persen) || 0;
    return Math.round((otherTargetsSum.value + current) * 100) / 100 === 100;
});

const alasanTidakValid = computed(() => {
    const alasan = [];
    if (!form.sumbu || !form.kegunaan) alasan.push('Pilih tipe skor dan kegunaannya.');
    if (!String(form.kode_range || '').trim()) alasan.push('Kode wajib diisi.');
    if (!String(form.nama_range || '').trim()) alasan.push('Nama wajib diisi.');
    const dari = Number(form.nilai_dari);
    const sampai = Number(form.nilai_sampai);
    if (form.nilai_dari === '' || form.nilai_dari === null || isNaN(dari)) alasan.push('Nilai "Dari" harus berupa angka.');
    if (form.nilai_sampai === '' || form.nilai_sampai === null || isNaN(sampai)) alasan.push('Nilai "Sampai" harus berupa angka.');
    if (!isNaN(dari) && !isNaN(sampai) && sampai <= dari) {
        alasan.push(`Nilai "Sampai" (${sampai}) harus lebih besar dari nilai "Dari" (${dari}).`);
    }
    return alasan;
});

const isValidForm = computed(() => alasanTidakValid.value.length === 0);

const formRef = ref(null);
const galatRef = ref(null);

/* Galat server baru muncul → gulirkan ke atas supaya terbaca. */
watch(() => props.error, async (galat) => {
    if (!galat) return;
    await nextTick();
    galatRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' });
});

function syncData() {
    if (props.record) {
        form.sumbu = props.record.Score_Type || props.defaultType;
        form.kegunaan = props.record.Score_Usage || props.defaultUsage;
        form.kode_range = props.record.Kode_Range || '';
        form.nama_range = props.record.Nama_Range || '';
        form.nilai_dari = Number(props.record.Nilai_Dari ?? 0);
        form.nilai_sampai = Number(props.record.Nilai_Sampai ?? 100);
        form.target_persen = props.record.Target_Persen !== null ? Number(props.record.Target_Persen) : null;
        form.batas_z = props.record.Batas_Z === null || props.record.Batas_Z === undefined ? null : Number(props.record.Batas_Z);
        form.warna = props.record.Warna || '#16a34a';
        form.urutan_tampil = Number(props.record.Urutan_Tampil ?? 1);
    } else {
        form.sumbu = props.defaultType && props.defaultType !== 'FINAL' ? props.defaultType : 'KPI';
        form.kegunaan = 'HAVMAP';
        form.kode_range = '';
        form.nama_range = '';
        form.nilai_dari = 0;
        form.nilai_sampai = 999.99;
        form.target_persen = null;
        form.batas_z = null;
        form.warna = '#16a34a';
        form.urutan_tampil = (siblingBands.value.length || 0) + 1;
    }
}

function handleSubmit() {
    if (!isValidForm.value) return;
    emit('save', {
        sumbu: form.sumbu,
        kegunaan: form.kegunaan,
        kode_range: form.kode_range.trim().toUpperCase(),
        nama_range: form.nama_range.trim(),
        nilai_dari: Number(form.nilai_dari),
        nilai_sampai: Number(form.nilai_sampai),
        nilai_hasil: null,
        target_persen: form.target_persen !== null && form.target_persen !== '' ? Number(form.target_persen) : null,
        // Batas z hanya bermakna untuk grade nilai akhir; baris lain selalu mengirim null.
        batas_z:
            isGradeUsage.value && form.sumbu === 'FINAL' && form.batas_z !== null && form.batas_z !== ''
                ? Number(form.batas_z)
                : null,
        warna: form.warna ? form.warna.trim() : null,
        urutan_tampil: Number(form.urutan_tampil) || 1,
    });
}

watch(() => [props.modelValue, props.record], syncData, { immediate: true, deep: true });
</script>

<style scoped>
.pa-range-editor__invalid {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    padding: 0.6rem 0.75rem;
    border: 1px solid rgba(217, 119, 6, 0.35);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 251, 235, 0.94);
    color: #92400e;
    font-size: 0.78rem;
}
.pa-range-editor__invalid ul {
    margin: 0.2rem 0 0;
    padding-left: 1rem;
}
.pa-range-editor {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
}

.pa-range-editor__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(254, 243, 199, 0.55);
    color: #92400e;
    font-size: 0.75rem;
    line-height: 1.45;
}

.pa-range-editor__notice strong {
    display: block;
    font-size: 0.78rem;
    margin-bottom: 0.15rem;
    color: #78350f;
}

.pa-range-editor__grid-two {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.8rem;
}

.pa-select-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-select-field label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
}

.pa-select-field label em {
    color: #ef4444;
    font-style: normal;
    margin-left: 0.15rem;
}

.pa-select-field select {
    min-height: 2.375rem;
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    font: inherit;
    font-size: 0.8rem;
}

.pa-select-field select:disabled {
    opacity: 0.65;
    background: #f1f5f9;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Adjacent Continuity Guide */
.pa-range-editor__adjacent-guide {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(238, 242, 255, 0.6);
}

.pa-range-editor__adjacent-content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.72rem;
}

.pa-range-editor__adjacent-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-range-editor__adjacent-items code {
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.12);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
    color: #4338ca;
}

/* Boundaries Section */
.pa-range-editor__boundaries {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.pa-range-editor__boundaries-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-range-editor__boundaries-head small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}

.pa-range-editor__boundary-input {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-range-editor__boundary-input label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
}

.pa-range-editor__boundary-top-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-range-max-toggle {
    border: 0;
    background: transparent;
    color: #4f46e5;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.pa-range-input-number {
    height: 2.375rem;
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.88rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-range-editor__interpretation {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    color: var(--hcis-text-muted, #475569);
    font-size: 0.72rem;
    line-height: 1.4;
}

/* Target Section */
.pa-range-editor__target-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.45), rgba(255, 255, 255, 0.85));
}

.pa-range-editor__target-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-range-editor__catatan-metode {
    display: block;
    margin-top: var(--hcis-space-1);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
}

.pa-range-editor__target-head h4 {
    margin: 0.15rem 0 0;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.88rem;
}

.pa-range-editor__target-budget-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.25rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-range-editor__target-budget-info strong {
    color: var(--hcis-text-strong, #0f172a);
}

/* Color Palette Section */
.pa-range-editor__color-section {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.95rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.pa-range-editor__color-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-range-editor__color-head small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}

.pa-range-editor__color-palette {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.pa-color-swatch-btn {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border: 2px solid #ffffff;
    border-radius: var(--hcis-radius-sm, 6px);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
    transition: transform 150ms ease, box-shadow 150ms ease;
}

.pa-color-swatch-btn:hover {
    transform: scale(1.15);
}

.pa-color-swatch-btn.active {
    transform: scale(1.15);
    box-shadow: 0 0 0 2.5px #4f46e5, 0 2px 5px rgba(15, 23, 42, 0.2);
}

.pa-range-editor__color-input-wrap {
    display: grid;
    grid-template-columns: 2.375rem 1fr 1fr;
    align-items: flex-end;
    gap: 0.65rem;
    margin-top: 0.3rem;
}

.pa-color-preview-box {
    width: 2.375rem;
    height: 2.375rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(203, 213, 225, 0.8);
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.1);
}

/* Preview Card */
.pa-range-editor__preview-card {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.75rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-range-preview-badge {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.8rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border-left: 4px solid;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    flex-wrap: wrap;
    word-break: break-word;
}

.pa-range-preview-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 999px;
}

.pa-range-preview-badge strong {
    font-size: 0.84rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-range-preview-interval {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.76rem;
    font-weight: 700;
    color: #4338ca;
}

@media (max-width: 640px) {
    .pa-range-editor {
        gap: 0.75rem;
    }

    .pa-range-editor__notice {
        padding: 0.55rem 0.65rem;
        font-size: 0.7rem;
    }

    .pa-range-editor__grid-two {
        grid-template-columns: 1fr;
        gap: 0.6rem;
    }

    .pa-range-editor__boundaries {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }

    .pa-range-editor__target-section {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }

    .pa-range-editor__color-section {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }

    .pa-color-swatch-btn {
        width: 2.25rem;
        height: 2.25rem;
    }

    .pa-range-editor__color-input-wrap {
        grid-template-columns: 2.25rem 1fr;
        gap: 0.5rem;
    }

    .pa-range-editor__preview-card {
        padding: 0.55rem 0.65rem;
    }

    .pa-range-preview-badge {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
        padding: 0.5rem 0.65rem;
    }
}
</style>
