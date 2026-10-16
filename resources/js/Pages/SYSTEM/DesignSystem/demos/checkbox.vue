<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisCheckbox Configurator</h3>
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
                    <HcisCheckbox
                        v-model="studioChecked"
                        :label="studioLabel"
                        :description="studioDescription || undefined"
                        :size="studioSize"
                        :variant="studioVariant"
                        :color="studioColor"
                        :indeterminate="studioIndeterminate"
                        :disabled="studioDisabled"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 15px)</option>
                        <option value="md">Sedang (md - 18px)</option>
                        <option value="lg">Besar (lg - 22px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Tampilan (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="plain">Polos Standar (Plain)</option>
                        <option value="card">Kartu Interaktif (Card)</option>
                        <option value="button">Tombol Pill (Button)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Warna Sentimen (Color)</label>
                    <select v-model="studioColor" class="form-select form-select-sm">
                        <option value="brand">Brand Safir (Indigo)</option>
                        <option value="success">Success Zamrud (Emerald)</option>
                        <option value="warning">Warning Amber (Kuning)</option>
                        <option value="danger">Danger Koral (Merah)</option>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Label Checkbox</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Deskripsi Tambahan (Hint)</label>
                    <input v-model="studioDescription" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Status & Keadaan Khusus</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="cb-chk" v-model="studioChecked" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="cb-chk">Tercentang (Checked)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="cb-ind" v-model="studioIndeterminate" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="cb-ind">Sebagian (Indeterminate)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="cb-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="cb-dis">Nonaktif (Disabled)</label>
                        </div>
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
        <!-- Group 1: Permission Delegation & Form Agreements -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Persetujuan Syarat & Dokumen Pengajuan (Plain Variant):</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisCard title="Persetujuan Potong Saldo Cuti" icon="calendar-check" size="sm">
                    <HcisCheckbox
                        v-model="demoCutiAgree"
                        label="Saya menyetujui pemotongan kuota cuti tahunan"
                        description="Permohonan yang telah disetujui atasan langsung tidak dapat dibatalkan mandiri."
                        color="brand"
                    />
                </HcisCard>

                <HcisCard title="Deklarasi Kebijakan Kerahasiaan (NDA)" icon="shield-shaded" size="sm">
                    <HcisCheckbox
                        v-model="demoNdaAgree"
                        label="Saya mematuhi pakta integritas data HCIS"
                        description="Dilarang menyebarluaskan data kompensasi dan informasi pribadi pegawai."
                        color="success"
                    />
                </HcisCard>
            </div>
        </div>

        <!-- Group 2: Interactive Card Variant for Modules -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Pilihan Modul & Wewenang (Card Variant):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCheckbox
                    v-model="demoModules"
                    value="mod-payroll"
                    variant="card"
                    label="Modul Payroll & Kompensasi"
                    description="Otorisasi slip gaji, insentif lembur, dan potongan PPh 21."
                />
                <HcisCheckbox
                    v-model="demoModules"
                    value="mod-absen"
                    variant="card"
                    label="Modul Presensi & Dinas Luar"
                    description="Persetujuan izin sakit, cuti dinas, dan koreksi log absen."
                />
                <HcisCheckbox
                    v-model="demoModules"
                    value="mod-talent"
                    variant="card"
                    label="Modul Evaluasi KPI & Talenta"
                    description="Penilaian kinerja semesteran dan rencana suksesi kepemimpinan."
                />
            </div>
        </div>

        <!-- Group 3: Button Pill Filter Variant -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Filter Cepat Tag (Button Pill Variant):</span>
            <div class="d-flex flex-wrap gap-2">
                <HcisCheckbox v-model="demoTags" value="tetap" variant="button" label="Pegawai Tetap (PKWTT)" />
                <HcisCheckbox v-model="demoTags" value="kontrak" variant="button" label="Pegawai Kontrak (PKWT)" />
                <HcisCheckbox v-model="demoTags" value="magang" variant="button" label="Peserta Magang" />
                <HcisCheckbox v-model="demoTags" value="direksi" variant="button" label="Jajaran Direksi" />
            </div>
        </div>

        <!-- Group 4: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">4. Perbandingan Hierarki Ukuran (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Kecil (sm - 15px)" size="sm">
                    <HcisCheckbox :model-value="true" size="sm" label="Opsi Ringkas Kompak" />
                </HcisCard>
                <HcisCard title="Standar (md - 18px)" size="sm">
                    <HcisCheckbox :model-value="true" size="md" label="Opsi Standar Default" />
                </HcisCard>
                <HcisCard title="Besar (lg - 22px)" size="sm">
                    <HcisCheckbox :model-value="true" size="lg" label="Opsi Penekanan Besar" />
                </HcisCard>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup Checkbox:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Indeterminate (Pilih Sebagian)" size="sm">
                    <HcisCheckbox :model-value="false" indeterminate label="Pilih Semua (3 dari 8 Terpilih)" />
                </HcisCard>
                <HcisCard title="Tercentang Aktif" size="sm">
                    <HcisCheckbox :model-value="true" label="Opsi Terpilih Standar" />
                </HcisCard>
                <HcisCard title="Terkunci (Disabled)" size="sm">
                    <HcisCheckbox :model-value="true" disabled label="Centang Terkunci Kebijakan" description="Tidak dapat diubah tanpa izin SuperAdmin" />
                </HcisCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisCard, HcisCheckbox, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioChecked = ref(true);
const studioLabel = ref('Saya menyetujui pakta integritas dan ketentuan sistem');
const studioDescription = ref('Persetujuan ini mengikat secara hukum sesuai ketentuan perusahaan.');
const studioSize = ref('md');
const studioVariant = ref('plain');
const studioColor = ref('brand');
const studioIndeterminate = ref(false);
const studioDisabled = ref(false);
const copied = ref(false);

// Scenarios State
const demoCutiAgree = ref(true);
const demoNdaAgree = ref(true);
const demoModules = ref(['mod-payroll', 'mod-absen']);
const demoTags = ref(['tetap', 'kontrak']);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="isChecked"`);
    if (studioLabel.value) props.push(`label="${studioLabel.value}"`);
    if (studioDescription.value) props.push(`description="${studioDescription.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioVariant.value !== 'plain') props.push(`variant="${studioVariant.value}"`);
    if (studioColor.value !== 'brand') props.push(`color="${studioColor.value}"`);
    if (studioIndeterminate.value) props.push(`indeterminate`);
    if (studioDisabled.value) props.push(`disabled`);

    return `<HcisCheckbox\n    ${props.join('\n    ')}\n/>`;
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
