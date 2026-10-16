<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisRadio Configurator</h3>
            </div>
            <!-- Stage Background Switcher -->
            <div class="ds-stage-switcher" role="group" aria-label="Latar Panggung">
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'light' }"
                    @click="studioStageBg = 'light'"
                >
                    Light
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'dark' }"
                    @click="studioStageBg = 'dark'"
                >
                    Dark 🌑
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'mesh' }"
                    @click="studioStageBg = 'mesh'"
                >
                    Mesh 🔮
                </button>
            </div>
        </div>

        <div class="ds-studio-body">
            <!-- Preview Stage Canvas -->
            <div
                class="ds-preview-stage"
                :class="[
                    `ds-stage-bg--${studioStageBg}`,
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' }
                ]"
            >
                <div style="width: 100%; max-width: 440px;">
                    <HcisRadio
                        v-model="studioValue"
                        name="studio-radio"
                        :options="sampleRadioOptions"
                        :size="studioSize"
                        :variant="studioVariant"
                        :color="studioColor"
                        :direction="studioDirection"
                        :disabled="studioDisabled"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 15px)</option>
                        <option value="md">Sedang (md - 18px)</option>
                        <option value="lg">Besar (lg - 22px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Varian Tampilan</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="plain">Polos Standar (Plain)</option>
                        <option value="card">Kartu Interaktif (Card)</option>
                        <option value="button">Tombol Pill (Button)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Arah Tata Letak</label>
                    <select v-model="studioDirection" class="form-select form-select-sm">
                        <option value="vertical">Vertikal (Vertical)</option>
                        <option value="horizontal">Horizontal (Mendatar)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Warna Sentimen</label>
                    <select v-model="studioColor" class="form-select form-select-sm">
                        <option value="brand">Brand Safir (Indigo)</option>
                        <option value="success">Success Zamrud (Emerald)</option>
                        <option value="warning">Warning Amber (Kuning)</option>
                        <option value="danger">Danger Koral (Merah)</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch m-0">
                        <input id="rd-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="rd-dis">Nonaktifkan Semua Opsi (Disabled)</label>
                    </div>
                </div>
            </div>

            <!-- Code Generator -->
            <div class="ds-code-generator">
                <div class="ds-code-header">
                    <span class="ds-code-lang">Vue 3 SFC Template</span>
                    <button type="button" class="ds-copy-btn" @click="copySnippet">
                        <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                        <span>{{ copied ? 'Tersalin!' : 'Salin Kode' }}</span>
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ generatedSnippet }}</code></pre>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Group 1: Jenis Pengajuan Cuti (Plain Variant) -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Pemilihan Jenis Pengajuan Cuti & Izin (Plain Variant):</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisCard title="Kategori Cuti Dinas" icon="calendar-event" size="sm">
                    <HcisRadio
                        v-model="demoCutiVal"
                        name="demo-cuti"
                        :options="cutiOptions"
                    />
                </HcisCard>

                <HcisCard title="Konfirmasi Tindakan Cepat (Horizontal)" icon="check2-circle" size="sm">
                    <HcisRadio
                        v-model="demoConfirmVal"
                        name="demo-confirm"
                        direction="horizontal"
                        :options="[
                            { value: 'approve', label: 'Setujui Pengajuan', color: 'success' },
                            { value: 'revision', label: 'Minta Revisi', color: 'warning' },
                            { value: 'reject', label: 'Tolak Berkas', color: 'danger' }
                        ]"
                    />
                </HcisCard>
            </div>
        </div>

        <!-- Group 2: Opsi Paket Skema (Card Variant) -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skema & Kebijakan Hubungan Kerja (Card Variant):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisRadio
                    v-model="demoSchemeVal"
                    name="demo-scheme"
                    variant="card"
                    :options="schemeOptions"
                />
            </div>
        </div>

        <!-- Group 3: Periode Pelaporan (Button Pill Variant) -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Segmentasi Periode Payroll (Button Pill Variant):</span>
            <HcisRadio
                v-model="demoPeriodeVal"
                name="demo-periode"
                variant="button"
                direction="horizontal"
                :options="[
                    { value: 'bulanan', label: 'Gaji Bulanan (Reguler)' },
                    { value: 'thr', label: 'Tunjangan Hari Raya (THR)' },
                    { value: 'bonus', label: 'Bonus Kinerja Tahunan' },
                    { value: 'pesangon', label: 'Kompensasi PHK / Pensiun' }
                ]"
            />
        </div>

        <!-- Group 4: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">4. Perbandingan Hierarki Ukuran Radio (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Kecil (sm - 15px)" size="sm">
                    <HcisRadio :model-value="'1'" name="sz-sm" size="sm" :options="[{ value: '1', label: 'Opsi Ringkas 1' }, { value: '2', label: 'Opsi Ringkas 2' }]" />
                </HcisCard>
                <HcisCard title="Standar (md - 18px)" size="sm">
                    <HcisRadio :model-value="'1'" name="sz-md" size="md" :options="[{ value: '1', label: 'Opsi Standar 1' }, { value: '2', label: 'Opsi Standar 2' }]" />
                </HcisCard>
                <HcisCard title="Besar (lg - 22px)" size="sm">
                    <HcisRadio :model-value="'1'" name="sz-lg" size="lg" :options="[{ value: '1', label: 'Opsi Penekanan 1' }, { value: '2', label: 'Opsi Penekanan 2' }]" />
                </HcisCard>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup Radio:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisCard title="Opsi Terpilih (Checked)" size="sm">
                    <HcisRadio :model-value="'opt-1'" name="st-1" :options="[{ value: 'opt-1', label: 'Pilihan Aktif Terpilih' }, { value: 'opt-2', label: 'Pilihan Alternatif' }]" />
                </HcisCard>
                <HcisCard title="Opsi Terkunci (Disabled)" size="sm">
                    <HcisRadio :model-value="'opt-1'" name="st-2" disabled :options="[{ value: 'opt-1', label: 'Pilihan Terkunci Otoritas' }, { value: 'opt-2', label: 'Pilihan Nonaktif' }]" />
                </HcisCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisCard, HcisRadio, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('tahunan');
const studioSize = ref('md');
const studioVariant = ref('plain');
const studioColor = ref('brand');
const studioDirection = ref('vertical');
const studioDisabled = ref(false);
const copied = ref(false);

const sampleRadioOptions = [
    { value: 'tahunan', label: 'Cuti Tahunan (12 Hari)', description: 'Hak cuti reguler tahunan karyawan aktif.' },
    { value: 'sakit', label: 'Cuti Sakit Resmi', description: 'Memerlukan lampiran surat keterangan dokter terdaftar.' },
    { value: 'khusus', label: 'Cuti Khusus & Pernikahan', description: 'Peristiwa penting keluarga atau ikatan pernikahan.' },
];

// Scenarios State
const demoCutiVal = ref('tahunan');
const cutiOptions = [
    { value: 'tahunan', label: 'Cuti Tahunan Reguler', description: 'Sisa saldo: 8 hari' },
    { value: 'sakit', label: 'Cuti Sakit Rawat Inap', description: 'Surat dokter wajib diunggah' },
    { value: 'ibadah', label: 'Cuti Ibadah Keagamaan', description: 'Umroh / Haji / Ziarah rohani' },
];

const demoConfirmVal = ref('approve');
const demoSchemeVal = ref('pkwtt');
const schemeOptions = [
    { value: 'pkwtt', label: 'Pegawai Tetap (PKWTT)', description: 'Hak jaminan pensiun, asuransi penuh, dan jenjang karier struktural.' },
    { value: 'pkwt', label: 'Pegawai Kontrak (PKWT)', description: 'Masa ikatan kerja maksimal 2 tahun dengan kompensasi akhir kontrak.' },
    { value: 'magang', label: 'Program Magang Industri', description: 'Uang saku bulanan dan sertifikat kompetensi keahlian.' },
];

const demoPeriodeVal = ref('bulanan');

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="radioValue"`);
    props.push(`name="nama-grup"`);
    props.push(`:options="options"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioVariant.value !== 'plain') props.push(`variant="${studioVariant.value}"`);
    if (studioColor.value !== 'brand') props.push(`color="${studioColor.value}"`);
    if (studioDirection.value !== 'vertical') props.push(`direction="${studioDirection.value}"`);
    if (studioDisabled.value) props.push(`disabled`);

    return `<HcisRadio\n    ${props.join('\n    ')}\n/>`;
});

function copySnippet() {
    navigator.clipboard.writeText(generatedSnippet.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }
</style>
