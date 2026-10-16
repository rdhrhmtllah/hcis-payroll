<template>
    <div class="ds-playground">
        <!-- Playground Header -->
        <div class="ds-playground__header">
            <div class="ds-playground__header-left">
                <div class="ds-playground__badge-icon">
                    <HcisIcon name="sliders" size="sm" />
                </div>
                <div>
                    <h3 class="ds-playground__title">Interactive Component Workbench</h3>
                    <p class="ds-playground__desc">
                        Ubah props & parameter secara langsung untuk melihat reaksi visual komponen dan mendapatkan kode siap pakai.
                    </p>
                </div>
            </div>

            <!-- Canvas Background Selector -->
            <div class="ds-playground__bg-controls">
                <span class="ds-playground__bg-label">Latar Canvas:</span>
                <div class="ds-playground__bg-dots">
                    <button
                        type="button"
                        class="ds-playground__bg-dot ds-playground__bg-dot--white"
                        :class="{ 'is-active': canvasBg === 'white' }"
                        title="Putih Bersih"
                        @click="canvasBg = 'white'"
                    ></button>
                    <button
                        type="button"
                        class="ds-playground__bg-dot ds-playground__bg-dot--slate"
                        :class="{ 'is-active': canvasBg === 'slate' }"
                        title="Soft Slate"
                        @click="canvasBg = 'slate'"
                    ></button>
                    <button
                        type="button"
                        class="ds-playground__bg-dot ds-playground__bg-dot--indigo"
                        :class="{ 'is-active': canvasBg === 'indigo' }"
                        title="Indigo Tint"
                        @click="canvasBg = 'indigo'"
                    ></button>
                    <button
                        type="button"
                        class="ds-playground__bg-dot ds-playground__bg-dot--dark"
                        :class="{ 'is-active': canvasBg === 'dark' }"
                        title="Dark Surface"
                        @click="canvasBg = 'dark'"
                    ></button>
                </div>
            </div>
        </div>

        <!-- Workbench Body: Split between Controls & Live Preview -->
        <div class="ds-playground__body">
            <!-- Left: Props Controls -->
            <div class="ds-playground__controls">
                <div class="ds-playground__controls-title">
                    <HcisIcon name="toggles" size="xs" />
                    <span>Konfigurasi Props</span>
                </div>

                <div class="ds-playground__form-stack">
                    <!-- Text / Label Control -->
                    <div v-if="hasProp('label') || hasProp('title') || entry.slug === 'button' || entry.slug === 'badge'" class="ds-control-item">
                        <label class="ds-control-label">Teks / Label</label>
                        <input
                            v-model="modelLabel"
                            type="text"
                            class="ds-control-input"
                            placeholder="Ketik teks label…"
                        />
                    </div>

                    <!-- Description Control -->
                    <div v-if="hasProp('description') || hasProp('subtitle') || entry.slug === 'card' || entry.slug === 'empty-state'" class="ds-control-item">
                        <label class="ds-control-label">Deskripsi / Subtitle</label>
                        <input
                            v-model="modelDescription"
                            type="text"
                            class="ds-control-input"
                            placeholder="Ketik keterangan…"
                        />
                    </div>

                    <!-- Variant Control -->
                    <div v-if="hasProp('variant') || availableVariants.length > 0" class="ds-control-item">
                        <label class="ds-control-label">Variant</label>
                        <div class="ds-pill-group">
                            <button
                                v-for="v in availableVariants"
                                :key="v"
                                type="button"
                                class="ds-pill-btn"
                                :class="{ 'is-active': modelVariant === v }"
                                @click="modelVariant = v"
                            >
                                {{ v }}
                            </button>
                        </div>
                    </div>

                    <!-- Size Control -->
                    <div v-if="hasProp('size') || availableSizes.length > 0" class="ds-control-item">
                        <label class="ds-control-label">Size</label>
                        <div class="ds-pill-group">
                            <button
                                v-for="s in availableSizes"
                                :key="s"
                                type="button"
                                class="ds-pill-btn"
                                :class="{ 'is-active': modelSize === s }"
                                @click="modelSize = s"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <!-- Icon Control -->
                    <div v-if="hasProp('icon') || hasProp('leading') || entry.slug === 'button' || entry.slug === 'badge'" class="ds-control-item">
                        <label class="ds-control-label">Ikon (Bootstrap Icon Name)</label>
                        <div class="ds-icon-picker-row">
                            <input
                                v-model="modelIcon"
                                type="text"
                                class="ds-control-input"
                                placeholder="mis. check-lg, search, bell"
                            />
                            <div class="ds-icon-preview">
                                <HcisIcon :name="modelIcon || 'star'" size="sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Boolean States Switches -->
                    <div class="ds-control-item">
                        <label class="ds-control-label">State & Perilaku</label>
                        <div class="ds-toggles-grid">
                            <label v-if="hasProp('disabled') || entry.slug === 'button' || entry.slug === 'field'" class="ds-toggle-row">
                                <input v-model="modelDisabled" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Disabled</span>
                            </label>
                            <label v-if="hasProp('loading') || entry.slug === 'button'" class="ds-toggle-row">
                                <input v-model="modelLoading" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Loading</span>
                            </label>
                            <label v-if="hasProp('block') || entry.slug === 'button'" class="ds-toggle-row">
                                <input v-model="modelBlock" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Block (Full Width)</span>
                            </label>
                            <label v-if="hasProp('dot') || entry.slug === 'badge'" class="ds-toggle-row">
                                <input v-model="modelDot" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Dot Indicator</span>
                            </label>
                            <label v-if="hasProp('pulse') || entry.slug === 'badge'" class="ds-toggle-row">
                                <input v-model="modelPulse" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Pulse Animation</span>
                            </label>
                            <label v-if="hasProp('interactive') || entry.slug === 'card'" class="ds-toggle-row">
                                <input v-model="modelInteractive" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Interactive (Hover Effect)</span>
                            </label>
                            <label v-if="hasProp('divider') || entry.slug === 'card'" class="ds-toggle-row">
                                <input v-model="modelDivider" type="checkbox" class="ds-toggle-checkbox" />
                                <span class="ds-toggle-text">Divider Lines</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Live Canvas & Code Snippet -->
            <div class="ds-playground__stage">
                <!-- Canvas Area -->
                <div class="ds-playground__canvas" :style="canvasStyle">
                    <div class="ds-playground__canvas-inner" :class="{ 'is-block': modelBlock }">
                        <!-- Dynamic rendering based on component slug -->
                        <!-- 1. HcisButton -->
                        <HcisButton
                            v-if="entry.slug === 'button'"
                            :variant="modelVariant"
                            :size="modelSize"
                            :disabled="modelDisabled"
                            :loading="modelLoading"
                            :block="modelBlock"
                            :icon="modelIcon"
                            :label="modelLabel"
                        />

                        <!-- 2. HcisBadge -->
                        <HcisBadge
                            v-else-if="entry.slug === 'badge' || entry.slug === 'status-badge'"
                            :variant="modelVariant"
                            :size="modelSize"
                            :dot="modelDot"
                            :pulse="modelPulse"
                            :icon="modelIcon"
                            :label="modelLabel"
                        />

                        <!-- 3. HcisCard -->
                        <div v-else-if="entry.slug === 'card'" style="width: 100%; max-width: 480px;">
                            <HcisCard
                                :title="modelLabel || 'Judul Kartu Informasi'"
                                :description="modelDescription || 'Keterangan ringkas isi kartu dan metrik terkait.'"
                                :variant="modelVariant"
                                :size="modelSize"
                                :interactive="modelInteractive"
                                :divider="modelDivider"
                            >
                                <p style="margin: 0; font-size: var(--hcis-font-size-sm); color: var(--hcis-text);">
                                    Ini adalah konten di dalam slot utama kartu. Anda dapat menaruh formulir, metrik, atau daftar item di sini.
                                </p>
                                <template #footer>
                                    <div style="display: flex; justify-content: flex-end; gap: var(--hcis-space-2);">
                                        <HcisButton label="Aksi Sekunder" variant="ghost" size="sm" />
                                        <HcisButton label="Aksi Utama" variant="primary" size="sm" />
                                    </div>
                                </template>
                            </HcisCard>
                        </div>

                        <!-- 4. HcisAlert -->
                        <div v-else-if="entry.slug === 'alert'" style="width: 100%; max-width: 540px;">
                            <HcisAlert
                                :variant="modelVariant"
                                :title="modelLabel || 'Pemberitahuan Sistem'"
                                :message="modelDescription || 'Pastikan semua kelengkapan data karyawan telah diunggah sebelum batas akhir periode evaluasi.'"
                            />
                        </div>

                        <!-- 5. HcisStatCard -->
                        <div v-else-if="entry.slug === 'stat-card'" style="width: 100%; max-width: 320px;">
                            <HcisStatCard
                                :label="modelLabel || 'Karyawan Aktif'"
                                value="1,248"
                                :sentiment="modelVariant === 'success' || modelVariant === 'good' ? 'good' : (modelVariant === 'danger' || modelVariant === 'bad' ? 'bad' : 'neutral')"
                                :caption="modelDescription || 'Meningkat +12% dibanding bulan lalu'"
                            />
                        </div>

                        <!-- 6. HcisEmptyState -->
                        <div v-else-if="entry.slug === 'empty-state'" style="width: 100%; max-width: 450px;">
                            <HcisEmptyState
                                :icon="modelIcon || 'inbox'"
                                :title="modelLabel || 'Belum Ada Data Ditemukan'"
                                :description="modelDescription || 'Mulai dengan menambahkan data baru atau ubah kriteria filter pencarian Anda.'"
                            >
                                <template #actions>
                                    <HcisButton label="Tambah Data Baru" variant="primary" size="sm" icon="plus-lg" />
                                </template>
                            </HcisEmptyState>
                        </div>

                        <!-- 7. HcisProgress -->
                        <div v-else-if="entry.slug === 'progress'" style="width: 100%; max-width: 400px;">
                            <HcisProgress
                                :percentage="65"
                                :status="modelVariant"
                                :stroke-width="modelSize === 'lg' ? 16 : (modelSize === 'sm' ? 8 : 12)"
                            />
                        </div>

                        <!-- 8. HcisSwitch -->
                        <div v-else-if="entry.slug === 'switch'" style="display: flex; align-items: center; gap: var(--hcis-space-3);">
                            <HcisSwitch
                                v-model="dummySwitch"
                                :disabled="modelDisabled"
                                :size="modelSize"
                            />
                            <span style="font-size: var(--hcis-font-size-sm); font-weight: 600;">
                                {{ dummySwitch ? 'Status Aktif' : 'Status Nonaktif' }}
                            </span>
                        </div>

                        <!-- 9. HcisCheckbox -->
                        <div v-else-if="entry.slug === 'checkbox'">
                            <HcisCheckbox
                                v-model="dummyCheckbox"
                                :disabled="modelDisabled"
                                :label="modelLabel || 'Saya menyetujui seluruh ketentuan dan kebijakan privasi'"
                            />
                        </div>

                        <!-- 10. HcisField -->
                        <div v-else-if="entry.slug === 'field'" style="width: 100%; max-width: 420px;">
                            <HcisField
                                :label="modelLabel || 'Nama Lengkap'"
                                :hint="modelDescription || 'Sesuai dengan KTP'"
                                :variant="modelVariant"
                                :size="modelSize"
                                :disabled="modelDisabled"
                                :leading-icon="modelIcon"
                                :clearable="true"
                                placeholder="Ketik isian..."
                            />
                        </div>

                        <!-- 11. HcisSelect -->
                        <div v-else-if="entry.slug === 'select'" style="width: 100%; max-width: 360px;">
                            <HcisSelect
                                v-model="dummySelect"
                                :disabled="modelDisabled"
                                :size="modelSize"
                                placeholder="Pilih opsi..."
                            >
                                <HcisSelectOption value="1" label="Opsi Pertama (ID)" />
                                <HcisSelectOption value="2" label="Opsi Kedua (SG)" />
                                <HcisSelectOption value="3" label="Opsi Ketiga (MY)" />
                            </HcisSelect>
                        </div>

                        <!-- 12. HcisDatePicker -->
                        <div v-else-if="entry.slug === 'date-picker'" style="width: 100%; max-width: 340px;">
                            <HcisDatePicker
                                v-model="dummyDate"
                                :disabled="modelDisabled"
                            />
                        </div>

                        <!-- 13. HcisInputNumber -->
                        <div v-else-if="entry.slug === 'input-number'" style="width: 100%; max-width: 260px;">
                            <HcisInputNumber
                                v-model="dummyNumber"
                                :disabled="modelDisabled"
                                :size="modelSize"
                                :min="0"
                                :max="100"
                                :step="1"
                            />
                        </div>

                        <!-- 14. HcisAutocomplete -->
                        <div v-else-if="entry.slug === 'autocomplete'" style="width: 100%; max-width: 360px;">
                            <HcisAutocomplete
                                v-model="dummyAutocomplete"
                                :disabled="modelDisabled"
                                :suggestions="['Ayu Rahmawati', 'Budi Santoso', 'Citra Dewi', 'Doni Pratama']"
                                placeholder="Ketik nama karyawan..."
                            />
                        </div>

                        <!-- 15. HcisContainer -->
                        <div v-else-if="entry.slug === 'container'" style="width: 100%; max-width: 520px;">
                            <HcisContainer
                                :title="modelLabel || 'Wadah Kontainer Data'"
                                :description="modelDescription || 'Wadah struktural dengan pembatas visual yang tegas.'"
                                :variant="modelVariant"
                                :icon="modelIcon || 'folder'"
                            >
                                <p style="margin: 0; font-size: var(--hcis-font-size-sm); color: var(--hcis-text);">
                                    Konten utama wadah kontainer HCIS.
                                </p>
                            </HcisContainer>
                        </div>

                        <!-- Fallback / Generic Component Preview note -->
                        <div v-else class="ds-playground__generic-box">
                            <HcisIcon name="box-seam" size="lg" style="color: var(--hcis-brand-500); margin-bottom: var(--hcis-space-2);" />
                            <h4 style="margin: 0; font-size: var(--hcis-font-size-md); font-weight: 700; color: var(--hcis-text-strong);">
                                {{ entry.name }} Workbench
                            </h4>
                            <p style="margin: var(--hcis-space-1) 0 var(--hcis-space-3); font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">
                                Komponen kompleks ini dirender dengan konfigurasi dinamis di bawah.
                            </p>
                            <HcisBadge
                                :label="`Status: ${entry.status.toUpperCase()}`"
                                :variant="entry.status === 'ready' ? 'success' : 'warning'"
                                dot
                            />
                        </div>
                    </div>
                </div>

                <!-- Generated Vue 3 Code Snippet Box -->
                <div class="ds-playground__code-box">
                    <div class="ds-playground__code-header">
                        <div class="ds-playground__code-title">
                            <HcisIcon name="code-slash" size="xs" />
                            <span>Generated Vue 3 Template</span>
                        </div>
                        <button type="button" class="ds-copy-code-btn" @click="copyGeneratedCode">
                            <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                            <span>{{ copied ? 'Tersalin!' : 'Salin Kode Template' }}</span>
                        </button>
                    </div>
                    <pre class="ds-code-block"><code>{{ generatedCode }}</code></pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import {
    HcisButton,
    HcisBadge,
    HcisCard,
    HcisAlert,
    HcisStatCard,
    HcisEmptyState,
    HcisProgress,
    HcisSwitch,
    HcisCheckbox,
    HcisField,
    HcisSelect,
    HcisSelectOption,
    HcisDatePicker,
    HcisInputNumber,
    HcisAutocomplete,
    HcisContainer,
    HcisIcon,
} from '@/components/ui';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
});

const canvasBg = ref('slate');
const copied = ref(false);

const dummySwitch = ref(true);
const dummyCheckbox = ref(true);
const dummySelect = ref('1');
const dummyDate = ref('2026-08-19');
const dummyNumber = ref(24);
const dummyAutocomplete = ref('');

// State models
const modelLabel = ref('');
const modelDescription = ref('');
const modelVariant = ref('primary');
const modelSize = ref('md');
const modelIcon = ref('');
const modelDisabled = ref(false);
const modelLoading = ref(false);
const modelBlock = ref(false);
const modelDot = ref(true);
const modelPulse = ref(false);
const modelInteractive = ref(true);
const modelDivider = ref(true);

// Initialize defaults based on entry slug
watch(
    () => props.entry.slug,
    (slug) => {
        resetDefaults(slug);
    },
    { immediate: true }
);

function resetDefaults(slug) {
    if (slug === 'button') {
        modelLabel.value = 'Simpan Data';
        modelVariant.value = 'primary';
        modelSize.value = 'md';
        modelIcon.value = 'check-lg';
        modelDisabled.value = false;
        modelLoading.value = false;
        modelBlock.value = false;
    } else if (slug === 'badge' || slug === 'status-badge') {
        modelLabel.value = 'APPROVED';
        modelVariant.value = 'success';
        modelSize.value = 'md';
        modelDot.value = true;
        modelPulse.value = false;
        modelIcon.value = '';
    } else if (slug === 'card') {
        modelLabel.value = 'Informasi Kontrak Kerja';
        modelDescription.value = 'Detail masa berlaku Perjanjian Kerja Waktu Tertentu (PKWT).';
        modelVariant.value = 'default';
        modelSize.value = 'md';
        modelInteractive.value = false;
        modelDivider.value = true;
    } else if (slug === 'container') {
        modelLabel.value = 'Wadah Kontainer Data';
        modelDescription.value = 'Wadah struktural dengan pembatas visual yang tegas.';
        modelVariant.value = 'card';
        modelIcon.value = 'folder';
    } else if (slug === 'field') {
        modelLabel.value = 'Nama Lengkap Karyawan';
        modelDescription.value = 'Sesuai dengan identitas KTP yang berlaku';
        modelVariant.value = 'outline';
        modelSize.value = 'md';
        modelIcon.value = 'person';
        modelDisabled.value = false;
    } else if (slug === 'alert') {
        modelLabel.value = 'Pembaruan Data Diperlukan';
        modelDescription.value = 'Harap perbarui nomor rekening bank sebelum tanggal 25 bulan berjalan.';
        modelVariant.value = 'warning';
    } else if (slug === 'stat-card') {
        modelLabel.value = 'Total Pengajuan Cuti';
        modelDescription.value = '18 berkas menunggu otorisasi';
        modelVariant.value = 'primary';
    } else if (slug === 'empty-state') {
        modelLabel.value = 'Belum Ada Riwayat Pelatihan';
        modelDescription.value = 'Karyawan ini belum terdaftar pada modul pelatihan LMS.';
        modelIcon.value = 'mortarboard';
    } else {
        modelLabel.value = props.entry.name;
        modelDescription.value = props.entry.summary || '';
        modelVariant.value = 'primary';
        modelSize.value = 'md';
    }
}

function hasProp(propName) {
    if (!props.entry.props) return false;
    return props.entry.props.some((p) => p.name === propName);
}

const availableVariants = computed(() => {
    const slug = props.entry.slug;
    if (slug === 'button') return ['primary', 'secondary', 'ghost', 'danger', 'danger-quiet'];
    if (slug === 'badge' || slug === 'status-badge') return ['success', 'primary', 'warning', 'danger', 'info', 'neutral', 'brand'];
    if (slug === 'card') return ['default', 'glass', 'flat', 'raised'];
    if (slug === 'container') return ['card', 'hero', 'glass', 'flat', 'bordered'];
    if (slug === 'field') return ['outline', 'filled', 'flushed'];
    if (slug === 'alert') return ['info', 'success', 'warning', 'danger'];
    if (slug === 'progress') return ['primary', 'success', 'warning', 'danger'];
    if (slug === 'stat-card') return ['neutral', 'good', 'bad'];
    return ['primary', 'secondary', 'neutral'];
});

const availableSizes = computed(() => {
    const slug = props.entry.slug;
    if (slug === 'badge') return ['xs', 'sm', 'md', 'lg'];
    if (slug === 'button') return ['sm', 'md'];
    if (slug === 'card') return ['sm', 'md', 'lg'];
    return ['sm', 'md', 'lg'];
});

const canvasStyle = computed(() => {
    switch (canvasBg.value) {
        case 'white':
            return { background: 'var(--hcis-white)', color: 'var(--hcis-slate-800)' };
        case 'slate':
            return { background: 'var(--hcis-slate-50)', color: 'var(--hcis-slate-800)' };
        case 'indigo':
            return { background: 'var(--hcis-indigo-50)', color: 'var(--hcis-indigo-900)' };
        case 'dark':
            return { background: 'var(--hcis-slate-900)', color: 'var(--hcis-slate-50)' };
        default:
            return { background: 'var(--hcis-slate-50)' };
    }
});

const generatedCode = computed(() => {
    const name = props.entry.name;
    const slug = props.entry.slug;

    if (slug === 'button') {
        const propsArr = [];
        if (modelVariant.value !== 'primary') propsArr.push(`variant="${modelVariant.value}"`);
        if (modelSize.value !== 'md') propsArr.push(`size="${modelSize.value}"`);
        if (modelIcon.value) propsArr.push(`icon="${modelIcon.value}"`);
        if (modelDisabled.value) propsArr.push(`:disabled="true"`);
        if (modelLoading.value) propsArr.push(`:loading="true"`);
        if (modelBlock.value) propsArr.push(`:block="true"`);
        const propStr = propsArr.length > 0 ? ' ' + propsArr.join(' ') : '';
        return `<${name}${propStr}>${modelLabel.value || 'Tombol'}</${name}>`;
    }

    if (slug === 'badge' || slug === 'status-badge') {
        const propsArr = [];
        if (modelVariant.value) propsArr.push(`variant="${modelVariant.value}"`);
        if (modelSize.value !== 'md') propsArr.push(`size="${modelSize.value}"`);
        if (modelDot.value) propsArr.push(`dot`);
        if (modelPulse.value) propsArr.push(`pulse`);
        if (modelIcon.value) propsArr.push(`icon="${modelIcon.value}"`);
        propsArr.push(`label="${modelLabel.value || 'LABEL'}"`);
        return `<${name} ${propsArr.join(' ')} />`;
    }

    if (slug === 'card') {
        const propsArr = [];
        propsArr.push(`title="${modelLabel.value || 'Judul Kartu'}"`);
        if (modelDescription.value) propsArr.push(`description="${modelDescription.value}"`);
        if (modelVariant.value !== 'default') propsArr.push(`variant="${modelVariant.value}"`);
        if (modelInteractive.value) propsArr.push(`:interactive="true"`);
        if (modelDivider.value) propsArr.push(`divider`);
        const propStr = propsArr.join('\n    ');
        return `<${name}\n    ${propStr}\n>\n    <p>Konten isi kartu disini...</p>\n</${name}>`;
    }

    if (slug === 'container') {
        return `<${name}\n    title="${modelLabel.value || 'Judul Wadah'}"\n    variant="${modelVariant.value}"\n>\n    <p>Konten kontainer disini...</p>\n</${name}>`;
    }

    if (slug === 'field') {
        return `<${name}\n    label="${modelLabel.value || 'Label Isian'}"\n    placeholder="Ketik isian..."\n    variant="${modelVariant.value}"\n    :clearable="true"\n/>`;
    }

    if (slug === 'select') {
        return `<${name} v-model="selectedValue" placeholder="Pilih opsi...">\n    <HcisSelectOption value="1" label="Pilihan 1" />\n    <HcisSelectOption value="2" label="Pilihan 2" />\n</${name}>`;
    }

    if (slug === 'date-picker') {
        return `<${name} v-model="selectedDate" />`;
    }

    if (slug === 'input-number') {
        return `<${name} v-model="total" :min="0" :max="100" />`;
    }

    if (slug === 'autocomplete') {
        return `<${name} v-model="query" :suggestions="list" placeholder="Cari..." />`;
    }

    if (slug === 'alert') {
        return `<${name}\n    variant="${modelVariant.value}"\n    title="${modelLabel.value || 'Judul Alert'}"\n    message="${modelDescription.value || 'Pesan penjelasan alert...'}"\n/>`;
    }

    return `<${name} />`;
});

function copyGeneratedCode() {
    navigator.clipboard.writeText(generatedCode.value).then(() => {
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    });
}
</script>

<style scoped>
.ds-playground {
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    overflow: hidden;
    box-shadow: 0 4px 20px -2px rgb(var(--hcis-shadow-rgb) / 0.06);
    margin-bottom: var(--hcis-space-8);
}

.ds-playground__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-4) var(--hcis-space-6);
    background: linear-gradient(180deg, var(--hcis-surface-soft) 0%, var(--hcis-surface) 100%);
    border-bottom: 1px solid var(--hcis-border);
    flex-wrap: wrap;
}

.ds-playground__header-left {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
}

.ds-playground__badge-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-brand-50);
    border: 1px solid var(--hcis-brand-200);
    color: var(--hcis-brand-600);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ds-playground__title {
    margin: 0;
    font-size: var(--hcis-font-size-md);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
}

.ds-playground__desc {
    margin: 2px 0 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
}

.ds-playground__bg-controls {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.ds-playground__bg-label {
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-text-muted);
    text-transform: uppercase;
    letter-spacing: var(--hcis-tracking-wide);
}

.ds-playground__bg-dots {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--hcis-surface-soft);
    padding: 3px;
    border-radius: var(--hcis-radius-pill);
    border: 1px solid var(--hcis-border);
}

.ds-playground__bg-dot {
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-playground__bg-dot--white { background: var(--hcis-white); border-color: var(--hcis-slate-300); }
.ds-playground__bg-dot--slate { background: var(--hcis-slate-100); }
.ds-playground__bg-dot--indigo { background: var(--hcis-indigo-100); }
.ds-playground__bg-dot--dark { background: var(--hcis-slate-900); }

.ds-playground__bg-dot.is-active {
    border-color: var(--hcis-brand-600);
    transform: scale(1.2);
    box-shadow: 0 0 0 2px rgb(var(--hcis-brand-500-rgb) / 0.3);
}

.ds-playground__body {
    display: grid;
    grid-template-columns: 340px 1fr;
    min-height: 420px;
}

@media (max-width: 900px) {
    .ds-playground__body {
        grid-template-columns: 1fr;
    }
}

.ds-playground__controls {
    padding: var(--hcis-space-5);
    background: var(--hcis-surface-soft);
    border-right: 1px solid var(--hcis-border);
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.ds-playground__controls-title {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
    text-transform: uppercase;
    letter-spacing: var(--hcis-tracking-wide);
    padding-bottom: var(--hcis-space-2);
    border-bottom: 1px solid var(--hcis-border);
}

.ds-playground__form-stack {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.ds-control-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.ds-control-label {
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-text-strong);
}

.ds-control-input {
    width: 100%;
    height: 2.15rem;
    padding: 0 var(--hcis-space-3);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-strong);
    background: var(--hcis-surface);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-xs);
    outline: none;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-control-input:focus {
    border-color: var(--hcis-brand-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.15);
}

.ds-pill-group {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.ds-pill-btn {
    padding: 3px 10px;
    border-radius: var(--hcis-radius-pill);
    border: 1px solid var(--hcis-border);
    background: var(--hcis-surface);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-pill-btn:hover {
    border-color: var(--hcis-brand-300);
    color: var(--hcis-brand-600);
}

.ds-pill-btn.is-active {
    background: var(--hcis-brand-600);
    border-color: var(--hcis-brand-600);
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgb(var(--hcis-brand-500-rgb) / 0.25);
}

.ds-icon-picker-row {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.ds-icon-preview {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-brand-600);
    flex-shrink: 0;
}

.ds-toggles-grid {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.ds-toggle-row {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text);
    cursor: pointer;
    font-weight: 500;
}

.ds-toggle-checkbox {
    width: 1rem;
    height: 1rem;
    accent-color: var(--hcis-brand-600);
    cursor: pointer;
}

.ds-playground__stage {
    display: flex;
    flex-direction: column;
}

.ds-playground__canvas {
    flex: 1;
    min-height: 260px;
    padding: var(--hcis-space-8);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
}

.ds-playground__canvas-inner {
    display: flex;
    align-items: center;
    justify-content: center;
}

.ds-playground__canvas-inner.is-block {
    width: 100%;
}

.ds-playground__generic-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: var(--hcis-space-6);
    background: var(--hcis-surface);
    border-radius: var(--hcis-radius-lg);
    border: 1px dashed var(--hcis-border-strong);
}

.ds-playground__code-box {
    background: var(--hcis-slate-900);
    border-top: 1px solid var(--hcis-slate-800);
    padding: var(--hcis-space-4) var(--hcis-space-6);
}

.ds-playground__code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--hcis-space-2);
}

.ds-playground__code-title {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    color: var(--hcis-slate-400);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    text-transform: uppercase;
    letter-spacing: var(--hcis-tracking-wide);
}

.ds-copy-code-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    background: var(--hcis-slate-800);
    border: 1px solid var(--hcis-slate-700);
    color: var(--hcis-slate-200);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    padding: 4px 10px;
    border-radius: var(--hcis-radius-md);
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-copy-code-btn:hover {
    background: var(--hcis-brand-600);
    border-color: var(--hcis-brand-500);
    color: var(--hcis-white);
}

.ds-code-block {
    margin: 0;
    padding: var(--hcis-space-2) 0;
    overflow-x: auto;
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-sky-400);
    line-height: var(--hcis-line-relaxed);
}
</style>
