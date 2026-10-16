<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisSelect & Option Configurator</h3>
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
                    <HcisField
                        id="studio-select-field"
                        :label="studioLabel"
                        :hint="studioHint"
                        :required="studioRequired"
                    >
                        <HcisSelect
                            v-model="studioValue"
                            :placeholder="studioPlaceholder"
                            :size="studioSize"
                            :filterable="studioFilterable"
                            :clearable="studioClearable"
                            :multiple="studioMultiple"
                            :disabled="studioDisabled"
                            :loading="studioLoading"
                            :leading-icon="studioLeadingIcon || undefined"
                        >
                            <HcisSelectOption
                                v-for="opt in sampleDivisiOptions"
                                :key="opt.value"
                                :value="opt.value"
                                :label="opt.label"
                                :description="opt.description"
                                :icon="opt.icon"
                                :badge="opt.badge"
                                :badge-variant="opt.badgeVariant"
                            />
                        </HcisSelect>
                    </HcisField>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field Pembungkus</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Placeholder</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 32px)</option>
                        <option value="md">Sedang (md - 38px)</option>
                        <option value="lg">Besar (lg - 44px)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ikon Utama (Leading Icon)</label>
                    <select v-model="studioLeadingIcon" class="form-select form-select-sm">
                        <option value="">Default (ui-checks-grid)</option>
                        <option value="building">building (Kantor/Divisi)</option>
                        <option value="people-fill">people-fill (Karyawan)</option>
                        <option value="geo-alt">geo-alt (Lokasi Cabang)</option>
                        <option value="shield-lock">shield-lock (Hak Akses)</option>
                        <option value="briefcase">briefcase (Jabatan)</option>
                    </select>
                </div>
                <div class="col-12 col-md-8">
                    <label class="ds-control-label">Petunjuk Form (Hint)</label>
                    <input v-model="studioHint" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur & Mode Interaktif</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-flt" v-model="studioFilterable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-flt">Pencarian (Filterable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-clr">Tombol Bersihkan (Clearable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-mul" v-model="studioMultiple" class="form-check-input" type="checkbox" @change="onMultipleToggle" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-mul">Multi Pilihan (Multiple Tags)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-lod" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-lod">Memuat (Loading)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-dis">Nonaktif (Disabled)</label>
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
        <!-- Group 1: Filterable Search Select -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Pencarian Cerdas & Penyaringan Opsi (Filterable Search):</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Unit Kerja / Divisi -->
                <HcisField
                    id="sel-divisi"
                    label="Unit Kerja / Departemen Induk"
                    hint="Ketik untuk memfilter nama departemen atau deskripsi divisi"
                    required
                >
                    <HcisSelect
                        v-model="demoDivisiVal"
                        placeholder="Cari dan pilih divisi..."
                        filterable
                        clearable
                        leading-icon="building"
                    >
                        <HcisSelectOption
                            v-for="item in divisiList"
                            :key="item.id"
                            :value="item.id"
                            :label="item.name"
                            :description="item.sub"
                            :badge="`${item.count} Pegawai`"
                            badge-variant="primary"
                            icon="diagram-3"
                        />
                    </HcisSelect>
                </HcisField>

                <!-- Lokasi Penempatan Kerja -->
                <HcisField
                    id="sel-lokasi"
                    label="Lokasi Penempatan Kantor Cabang"
                    hint="Wilayah operasional aktif sesuai SK direksi"
                >
                    <HcisSelect
                        v-model="demoLokasiVal"
                        placeholder="Pilih kantor operasional..."
                        filterable
                        clearable
                        leading-icon="geo-alt"
                    >
                        <HcisSelectOption
                            v-for="loc in lokasiList"
                            :key="loc.code"
                            :value="loc.code"
                            :label="loc.name"
                            :description="loc.address"
                            :badge="loc.zone"
                            badge-variant="neutral"
                            icon="pin-map"
                        />
                    </HcisSelect>
                </HcisField>
            </div>
        </div>

        <!-- Group 2: Multi-Select with Interactive Badges -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Mode Multi-Pilihan dengan Tag Badge Interaktif (multiple = true):</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Hak Akses Modul HCIS -->
                <HcisField
                    id="sel-roles"
                    label="Alokasi Hak Akses Modul Sistem"
                    hint="Pilih beberapa modul wewenang untuk akun pengguna ini"
                    required
                >
                    <HcisSelect
                        v-model="demoRolesVal"
                        placeholder="Pilih beberapa modul..."
                        multiple
                        filterable
                        clearable
                        leading-icon="shield-lock"
                    >
                        <HcisSelectOption
                            v-for="role in rolesList"
                            :key="role.id"
                            :value="role.id"
                            :label="role.name"
                            :description="role.scope"
                            icon="shield-check"
                        />
                    </HcisSelect>
                </HcisField>

                <!-- Keahlian & Sertifikasi -->
                <HcisField
                    id="sel-skills"
                    label="Kompetensi & Sertifikasi Profesi"
                    hint="Tag sertifikasi yang telah diverifikasi HCM"
                >
                    <HcisSelect
                        v-model="demoSkillsVal"
                        placeholder="Pilih kompetensi..."
                        multiple
                        filterable
                        clearable
                        leading-icon="stars"
                    >
                        <HcisSelectOption
                            v-for="skill in skillsList"
                            :key="skill.code"
                            :value="skill.code"
                            :label="skill.name"
                            :description="skill.category"
                            icon="award"
                        />
                    </HcisSelect>
                </HcisField>
            </div>
        </div>

        <!-- Group 3: Rich Options with Subtitles & Status Badges -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Opsi Kaya Detail (Subjudul, Ikon Entitas & Badge Status):</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Status Hubungan Kerja PKWT/PKWTT -->
                <HcisField
                    id="sel-status-kerja"
                    label="Status Hubungan Kerja Karyawan"
                    hint="Bentuk ikatan dinas ketenagakerjaan resmi"
                >
                    <HcisSelect
                        v-model="demoStatusKerja"
                        placeholder="Pilih jenis hubungan kerja..."
                        leading-icon="briefcase"
                    >
                        <HcisSelectOption
                            value="pkwtt"
                            label="Pegawai Tetap (PKWTT)"
                            description="Perjanjian Kerja Waktu Tidak Tertentu"
                            badge="Permanen"
                            badge-variant="success"
                            icon="person-check"
                        />
                        <HcisSelectOption
                            value="pkwt"
                            label="Pegawai Kontrak (PKWT)"
                            description="Perjanjian Kerja Waktu Tertentu (Max 2 Tahun)"
                            badge="Kontrak"
                            badge-variant="warning"
                            icon="calendar-week"
                        />
                        <HcisSelectOption
                            value="magang"
                            label="Peserta Magang Industri"
                            description="Program magang bersertifikat kemnaker"
                            badge="Internship"
                            badge-variant="neutral"
                            icon="mortarboard"
                        />
                        <HcisSelectOption
                            value="freelance"
                            label="Tenaga Ahli Lepas / Konsultan"
                            description="Penugasan berbasis project milestone"
                            badge="Project"
                            badge-variant="primary"
                            icon="laptop"
                        />
                    </HcisSelect>
                </HcisField>

                <!-- Tingkat Otorisasi Approval -->
                <HcisField
                    id="sel-approval"
                    label="Jenjang Otorisasi Persetujuan Transaksi"
                    hint="Menentukan batas plafon wewenang tanda tangan digital"
                >
                    <HcisSelect
                        v-model="demoApprovalTier"
                        placeholder="Pilih level otorisasi..."
                        leading-icon="key"
                    >
                        <HcisSelectOption
                            value="tier-1"
                            label="Tier 1 - Direksi & Board"
                            description="Plafon tidak terbatas / Unlimited Approval"
                            badge="Level 1"
                            badge-variant="danger"
                            icon="shield-shaded"
                        />
                        <HcisSelectOption
                            value="tier-2"
                            label="Tier 2 - Kepala Divisi (VP/Kadiv)"
                            description="Plafon approval s.d Rp 500.000.000"
                            badge="Level 2"
                            badge-variant="warning"
                            icon="shield"
                        />
                        <HcisSelectOption
                            value="tier-3"
                            label="Tier 3 - Kepala Departemen (Manager)"
                            description="Plafon approval operasional s.d Rp 50.000.000"
                            badge="Level 3"
                            badge-variant="primary"
                            icon="person-gear"
                        />
                    </HcisSelect>
                </HcisField>
            </div>
        </div>

        <!-- Group 4: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">4. Perbandingan Hierarki Ukuran Select (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Ukuran Kecil (sm - 32px)">
                    <HcisSelect
                        v-model="demoSizeSm"
                        size="sm"
                        placeholder="Pilih opsi..."
                        leading-icon="sliders"
                    >
                        <HcisSelectOption value="opsi-1" label="Opsi Ringkas 1" />
                        <HcisSelectOption value="opsi-2" label="Opsi Ringkas 2" />
                    </HcisSelect>
                </HcisField>

                <HcisField label="Ukuran Standar (md - 38px)">
                    <HcisSelect
                        v-model="demoSizeMd"
                        size="md"
                        placeholder="Pilih opsi..."
                        leading-icon="sliders"
                    >
                        <HcisSelectOption value="opsi-1" label="Opsi Standar 1" />
                        <HcisSelectOption value="opsi-2" label="Opsi Standar 2" />
                    </HcisSelect>
                </HcisField>

                <HcisField label="Ukuran Besar (lg - 44px)">
                    <HcisSelect
                        v-model="demoSizeLg"
                        size="lg"
                        placeholder="Pilih opsi..."
                        leading-icon="stars"
                    >
                        <HcisSelectOption value="opsi-1" label="Opsi Hero 1" />
                        <HcisSelectOption value="opsi-2" label="Opsi Hero 2" />
                    </HcisSelect>
                </HcisField>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup & Validasi Select:</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- 1. Default Selected -->
                <HcisField
                    label="Status Default (Terpilih)"
                    hint="Kondisi normal siap pakai"
                >
                    <HcisSelect v-model="stateDefault" placeholder="Pilih...">
                        <HcisSelectOption value="hcm" label="Divisi Human Capital" icon="people-fill" />
                        <HcisSelectOption value="fin" label="Divisi Keuangan" icon="cash-coin" />
                    </HcisSelect>
                </HcisField>

                <!-- 2. Loading State -->
                <HcisField
                    label="Status Memuat Data (Loading State)"
                    hint="Menampilkan animasi pulse shimmer saat data di-fetch"
                >
                    <HcisSelect v-model="stateLoading" placeholder="Memuat daftar cabang..." loading>
                        <HcisSelectOption value="a" label="Opsi A" />
                    </HcisSelect>
                </HcisField>

                <!-- 3. Disabled State -->
                <HcisField
                    label="Status Non-Aktif (Disabled State)"
                    hint="Terkunci secara sistem dengan border solid & interior frosted"
                >
                    <HcisSelect v-model="stateDisabled" placeholder="Tidak dapat dipilih..." disabled>
                        <HcisSelectOption value="lock" label="PT Semen Padang - Kantor Pusat" icon="building" />
                    </HcisSelect>
                </HcisField>

                <!-- 4. Multi-Select Tags State -->
                <HcisField
                    label="Status Multi-Selection Aktif"
                    hint="Daftar tag dapat dibersihkan satu per satu atau sekaligus"
                >
                    <HcisSelect v-model="stateMulti" multiple clearable placeholder="Pilih peran...">
                        <HcisSelectOption value="adm" label="Administrator" icon="shield-lock" />
                        <HcisSelectOption value="mgr" label="Manager HR" icon="person-gear" />
                        <HcisSelectOption value="aud" label="Auditor Internal" icon="search" />
                    </HcisSelect>
                </HcisField>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisField, HcisSelect, HcisSelectOption, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('hcm');
const studioLabel = ref('Unit Kerja Karyawan');
const studioPlaceholder = ref('Pilih unit kerja...');
const studioHint = ref('Pilih divisi utama tempat karyawan bertugas');
const studioSize = ref('md');
const studioLeadingIcon = ref('building');
const studioFilterable = ref(true);
const studioClearable = ref(true);
const studioMultiple = ref(false);
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioLoading = ref(false);
const copied = ref(false);

function onMultipleToggle() {
    if (studioMultiple.value) {
        studioValue.value = ['hcm', 'it'];
    } else {
        studioValue.value = 'hcm';
    }
}

// Sample Options for Studio
const sampleDivisiOptions = [
    { value: 'hcm', label: 'Human Capital Management', description: 'Talent Acquisition & Employee Relations', icon: 'people-fill', badge: '58 Pegawai', badgeVariant: 'primary' },
    { value: 'it', label: 'Information & Digital Technology', description: 'Enterprise Architecture & DevOps', icon: 'cpu', badge: '42 Pegawai', badgeVariant: 'success' },
    { value: 'fin', label: 'Finance & Corporate Treasury', description: 'Payroll, Tax & Financial Reporting', icon: 'cash-stack', badge: '31 Pegawai', badgeVariant: 'warning' },
    { value: 'ops', label: 'Operations & Plant Maintenance', description: 'Heavy Industry Logistics & Safety', icon: 'gear-wide-connected', badge: '124 Pegawai', badgeVariant: 'neutral' },
];

// Showcase Lists
const demoDivisiVal = ref('hcm');
const divisiList = [
    { id: 'hcm', name: 'Human Capital Management', sub: 'Divisi Pengelolaan SDM & Hubungan Industrial', count: 58 },
    { id: 'it', name: 'Information & Technology', sub: 'Infrastruktur Jaringan, Database & Aplikasi', count: 42 },
    { id: 'fin', name: 'Finance & Treasury', sub: 'Penganggaran, Akuntansi & Penggajian', count: 31 },
    { id: 'log', name: 'Supply Chain & Logistics', sub: 'Pengadaan Barang & Distribusi Material', count: 88 },
];

const demoLokasiVal = ref('hq-padang');
const lokasiList = [
    { code: 'hq-padang', name: 'Kantor Pusat Indarung', address: 'Jl. Raya Indarung, Padang, Sumatera Barat', zone: 'Kantor Pusat' },
    { code: 'br-jakarta', name: 'Kantor Perwakilan Jakarta', address: 'Gedung Graha Irama Lt. 11, Rasuna Said', zone: 'Cabang Utama' },
    { code: 'pl-dumai', name: 'Packing Plant Dumai', address: 'Kawasan Industri Lubuk Gaung, Dumai, Riau', zone: 'Operasional Pabrik' },
    { code: 'pl-lampung', name: 'Packing Plant Lampung', address: 'Pelabuhan Panjang, Bandar Lampung', zone: 'Operasional Pabrik' },
];

const demoRolesVal = ref(['role-payroll', 'role-attendance']);
const rolesList = [
    { id: 'role-payroll', name: 'Manajemen Payroll & Gaji', scope: 'Otorisasi slip gaji, THR, dan tunjangan' },
    { id: 'role-attendance', name: 'Presensi & Absensi Karyawan', scope: 'Monitoring mesin absen biometric & GPS' },
    { id: 'role-recruitment', name: 'Rekrutmen & Onboarding', scope: 'Pelamar kerja, psikotes, dan kontrak PKWT' },
    { id: 'role-performance', name: 'Evaluasi KPI & Talenta', scope: 'Penilaian kinerja tahunan dan promosi' },
];

const demoSkillsVal = ref(['sk-vue', 'sk-laravel']);
const skillsList = [
    { code: 'sk-vue', name: 'Vue 3 & Inertia.js', category: 'Frontend Web Engineering' },
    { code: 'sk-laravel', name: 'Laravel Enterprise API', category: 'Backend Architecture' },
    { code: 'sk-sql', name: 'SQL Server Optimization', category: 'Database Administration' },
    { code: 'sk-iso', name: 'ISO 9001:2015 Audit', category: 'Quality Management' },
];

const demoStatusKerja = ref('pkwtt');
const demoApprovalTier = ref('tier-2');
const demoSizeSm = ref('opsi-1');
const demoSizeMd = ref('opsi-1');
const demoSizeLg = ref('opsi-1');

// States
const stateDefault = ref('hcm');
const stateLoading = ref('');
const stateDisabled = ref('lock');
const stateMulti = ref(['adm', 'mgr']);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="selectedValue"`);
    if (studioPlaceholder.value) props.push(`placeholder="${studioPlaceholder.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioLeadingIcon.value) props.push(`leading-icon="${studioLeadingIcon.value}"`);
    if (studioFilterable.value) props.push(`filterable`);
    if (studioClearable.value) props.push(`clearable`);
    if (studioMultiple.value) props.push(`multiple`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioLoading.value) props.push(`loading`);

    return `<HcisField label="${studioLabel.value}" hint="${studioHint.value}">\n    <HcisSelect\n        ${props.join('\n        ')}\n    >\n        <HcisSelectOption\n            v-for="item in options"\n            :key="item.value"\n            :value="item.value"\n            :label="item.label"\n            :description="item.description"\n            :icon="item.icon"\n            :badge="item.badge"\n        />\n    </HcisSelect>\n</HcisField>`;
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
