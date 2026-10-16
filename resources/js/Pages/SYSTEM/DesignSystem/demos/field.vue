<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisField & Input Configurator</h3>
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
                <div style="width: 100%; max-width: 480px;">
                    <HcisField
                        id="studio-field"
                        v-model="studioValue"
                        :label="studioLabel"
                        :type="studioType"
                        :placeholder="studioPlaceholder"
                        :hint="studioHint"
                        :error="studioError"
                        :success="studioSuccess"
                        :variant="studioVariant"
                        :size="studioSize"
                        :leading-icon="studioLeadingIcon || undefined"
                        :trailing-icon="studioTrailingIcon || undefined"
                        :prefix="studioPrefix || undefined"
                        :suffix="studioSuffix || undefined"
                        :prepend="studioPrependMode === 'text' ? studioPrependText : undefined"
                        :append="studioAppendMode === 'text' ? studioAppendText : undefined"
                        :required="studioRequired"
                        :disabled="studioDisabled"
                        :readonly="studioReadonly"
                        :clearable="studioClearable"
                        :show-password-toggle="studioShowPasswordToggle"
                        :show-count="studioShowCount"
                        :max-length="studioShowCount ? studioMaxLength : undefined"
                        :rows="studioRows"
                    >
                        <!-- Dynamic Prepend Dropdown Slot -->
                        <template v-if="studioPrependMode === 'select'" #prepend>
                            <HcisDropdown
                                :label="studioPrependSelect"
                                :items="countryCodeItems"
                                :size="studioSize"
                                variant="secondary"
                                @select="item => studioPrependSelect = item.value"
                            />
                        </template>

                        <!-- Dynamic Append Dropdown or Button Slot -->
                        <template v-if="studioAppendMode === 'select'" #append>
                            <HcisDropdown
                                :label="studioAppendSelect"
                                :items="periodUnitItems"
                                :size="studioSize"
                                variant="secondary"
                                @select="item => studioAppendSelect = item.value"
                            />
                        </template>
                        <template v-else-if="studioAppendMode === 'button'" #append>
                            <HcisButton
                                label="Kirim OTP"
                                variant="primary"
                                :size="studioSize"
                                leading-icon="send-fill"
                            />
                        </template>

                        <!-- Corner Link Slot -->
                        <template v-if="studioShowCorner" #corner>
                            <a href="javascript:void(0)" class="text-xs text-indigo-600 text-decoration-none fw-semibold">
                                Butuh Bantuan?
                            </a>
                        </template>
                    </HcisField>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Tipe Input (Type)</label>
                    <select v-model="studioType" class="form-select form-select-sm">
                        <option value="text">Teks Standar (text)</option>
                        <option value="password">Kata Sandi (password)</option>
                        <option value="email">Email (email)</option>
                        <option value="number">Angka (number)</option>
                        <option value="search">Pencarian (search)</option>
                        <option value="textarea">Multi-Baris (textarea)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Tampilan</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="outline">Outline (Standar Kristal)</option>
                        <option value="filled">Filled (Latar Lembut Soft)</option>
                        <option value="flushed">Flushed (Garis Bawah Minimalis)</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 32px)</option>
                        <option value="md">Sedang (md - 38px)</option>
                        <option value="lg">Besar (lg - 44px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ikon Sisi Kiri (Leading)</label>
                    <select v-model="studioLeadingIcon" class="form-select form-select-sm">
                        <option value="">Tanpa Ikon</option>
                        <option value="person-vcard">person-vcard (NIK)</option>
                        <option value="search">search (Cari)</option>
                        <option value="envelope">envelope (Email)</option>
                        <option value="lock">lock (Gembok)</option>
                        <option value="building">building (Kantor)</option>
                        <option value="calendar-event">calendar-event (Tanggal)</option>
                        <option value="telephone">telephone (Telepon)</option>
                    </select>
                </div>

                <!-- Prepend & Append Segment Controls -->
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Mode Prepend (Kiri Luar)</label>
                    <select v-model="studioPrependMode" class="form-select form-select-sm">
                        <option value="none">Tanpa Prepend</option>
                        <option value="text">Teks Statis (mis. https://)</option>
                        <option value="select">Dropdown Select (HcisDropdown)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Mode Append (Kanan Luar)</label>
                    <select v-model="studioAppendMode" class="form-select form-select-sm">
                        <option value="none">Tanpa Append</option>
                        <option value="text">Teks Statis (mis. .com)</option>
                        <option value="select">Dropdown Select (HcisDropdown)</option>
                        <option value="button">Tombol Aksi (HcisButton)</option>
                    </select>
                </div>

                <div v-if="studioPrependMode === 'text'" class="col-6 col-md-3">
                    <label class="ds-control-label">Teks Prepend</label>
                    <input v-model="studioPrependText" type="text" class="form-control form-control-sm" placeholder="https://" />
                </div>
                <div v-if="studioAppendMode === 'text'" class="col-6 col-md-3">
                    <label class="ds-control-label">Teks Append</label>
                    <input v-model="studioAppendText" type="text" class="form-control form-control-sm" placeholder=".hcis.id" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Inline Prefix (Dalam)</label>
                    <input v-model="studioPrefix" type="text" class="form-control form-control-sm" placeholder="mis. Rp, $" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Inline Suffix (Dalam)</label>
                    <input v-model="studioSuffix" type="text" class="form-control form-control-sm" placeholder="mis. %, kg" />
                </div>

                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Placeholder</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Bantuan Teks (Hint)</label>
                    <input v-model="studioHint" type="text" class="form-control form-control-sm" />
                </div>

                <!-- Status Validasi Switcher -->
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Pesan Galat (Error State)</label>
                    <input v-model="studioError" type="text" class="form-control form-control-sm" placeholder="Ketik pesan error..." />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Pesan Sukses (Success State)</label>
                    <input v-model="studioSuccess" type="text" class="form-control form-control-sm" placeholder="Ketik pesan sukses..." />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Fitur & Toggle</label>
                    <div class="d-flex flex-wrap gap-2 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-req" v-model="studioRequired" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-req">Wajib (*)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-clear" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-clear">Clear ✕</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-pw" v-model="studioShowPasswordToggle" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-pw">Toggle PW 👁</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-count" v-model="studioShowCount" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-count">Counter</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-corner" v-model="studioShowCorner" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-corner">Corner</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs" for="sw-dis">Disabled</label>
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
        <!-- Group 1: Segmented Prepend & Append (Select / Static / Button Addons) -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Segmented Prepend & Append (Dropdown Select & Tombol Aksi):</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Phone with Country Code Select Prepend -->
                <HcisField
                    id="inp-phone"
                    v-model="demoPhone"
                    label="Nomor WhatsApp Karyawan"
                    placeholder="812-3456-7890"
                    hint="Pilih kode negara melalui komponen HcisDropdown"
                    clearable
                    required
                >
                    <template #prepend>
                        <HcisDropdown
                            :label="demoCountryCode"
                            :items="countryCodeItems"
                            size="md"
                            variant="secondary"
                            @select="item => demoCountryCode = item.value"
                        />
                    </template>
                </HcisField>

                <!-- Project Budget with Currency Select Prepend -->
                <HcisField
                    id="inp-currency"
                    v-model="demoCurrencyAmount"
                    label="Plafon Anggaran Reimbursement"
                    placeholder="15.000.000"
                    hint="Mata uang internasional dinamis dengan HcisDropdown"
                    clearable
                >
                    <template #prepend>
                        <HcisDropdown
                            :label="demoCurrencyType"
                            :items="currencyItems"
                            size="md"
                            variant="secondary"
                            @select="item => demoCurrencyType = item.value"
                        />
                    </template>
                </HcisField>

                <!-- Duration with Unit Select Append -->
                <HcisField
                    id="inp-period"
                    v-model="demoPeriodVal"
                    label="Durasi Kontrak Kerja PKWT"
                    placeholder="12"
                    hint="Satuan waktu via HcisDropdown di sisi append kanan"
                >
                    <template #append>
                        <HcisDropdown
                            :label="demoPeriodUnit"
                            :items="periodUnitItems"
                            size="md"
                            variant="secondary"
                            @select="item => demoPeriodUnit = item.value"
                        />
                    </template>
                </HcisField>

                <!-- Subdomain with Protocol Prepend & Domain Append -->
                <HcisField
                    id="inp-domain"
                    v-model="demoSubdomain"
                    label="Alamat Subdomain Portal Cabang"
                    placeholder="hq-jakarta"
                    prepend="https://"
                    append=".hcis-cloud.id"
                    hint="Format URL terproteksi HTTPS"
                    clearable
                />

                <!-- OTP Verification with Action Button Append -->
                <HcisField
                    id="inp-otp"
                    v-model="demoOtp"
                    label="Kode Verifikasi OTP (Otentikasi 2FA)"
                    placeholder="6 digit angka..."
                    hint="Kode OTP telah dikirimkan ke email terdaftar"
                    max-length="6"
                    show-count
                >
                    <template #append>
                        <HcisButton
                            label="Kirim Ulang (45s)"
                            variant="primary"
                            size="md"
                            leading-icon="arrow-clockwise"
                        />
                    </template>
                </HcisField>

                <!-- Referral Link with 1-Click Copy Button Append -->
                <HcisField
                    id="inp-invite"
                    v-model="demoInviteLink"
                    label="Tautan Undangan Formulir Onboarding"
                    readonly
                    hint="Tautan unik untuk calon pegawai baru"
                >
                    <template #append>
                        <HcisButton
                            :label="demoLinkCopied ? 'Tersalin!' : 'Salin Tautan'"
                            :variant="demoLinkCopied ? 'primary' : 'secondary'"
                            size="md"
                            :leading-icon="demoLinkCopied ? 'check2' : 'clipboard'"
                            @click="copyInviteLink"
                        />
                    </template>
                </HcisField>
            </div>
        </div>

        <!-- Group 2: Standard & Identity Inputs -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Input Identitas & Operasional Karyawan:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisField
                    id="inp-nik"
                    v-model="demoNik"
                    label="Nomor Induk Karyawan (NIK)"
                    placeholder="Ketik NIK 8 digit..."
                    hint="Format resmi terdaftar di database HRIS"
                    leading-icon="person-vcard"
                    clearable
                    show-count
                    max-length="16"
                    required
                />
                <HcisField
                    id="inp-email"
                    v-model="demoEmail"
                    type="email"
                    label="Email Perusahaan"
                    placeholder="nama.depan"
                    suffix="@hcis-corp.id"
                    hint="Digunakan untuk notifikasi SSO & approval sistem"
                    leading-icon="envelope"
                    clearable
                    required
                />
            </div>
        </div>

        <!-- Group 3: Secure Password & Authentication -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Input Keamanan & Kredensial Akses:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisField
                    id="inp-password"
                    v-model="demoPassword"
                    type="password"
                    label="Kata Sandi Akun"
                    placeholder="Masukkan kata sandi..."
                    hint="Minimal 8 karakter, kombinasi huruf besar & angka"
                    leading-icon="lock"
                    show-password-toggle
                    required
                >
                    <template #corner>
                        <a href="javascript:void(0)" class="text-xs text-indigo-600 text-decoration-none fw-semibold">
                            Lupa Sandi?
                        </a>
                    </template>
                </HcisField>
                <HcisField
                    id="inp-pin"
                    v-model="demoPin"
                    type="password"
                    label="PIN Otorisasi Transaksi (6-Digit)"
                    placeholder="• • • • • •"
                    hint="Diperlukan saat menyetujui mutasi atau payroll"
                    leading-icon="shield-lock"
                    show-password-toggle
                    max-length="6"
                    show-count
                />
            </div>
        </div>

        <!-- Group 4: Financial & Numeric Form Inputs -->
        <div class="ds-demo-group">
            <span class="ds-group-label">4. Input Finansial & Parameter Metrik (Inline Prefix / Suffix):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField
                    id="inp-gaji"
                    v-model="demoGaji"
                    label="Nominal Penggantian Medis"
                    placeholder="1.450.000"
                    prefix="Rp"
                    hint="Sesuai kuitansi terlampir"
                />
                <HcisField
                    id="inp-kpi"
                    v-model="demoKpi"
                    label="Target Capaian KPI"
                    placeholder="98"
                    suffix="%"
                    hint="Target kuartal III"
                />
                <HcisField
                    id="inp-cuti"
                    v-model="demoCuti"
                    label="Durasi Pengajuan Izin"
                    placeholder="4"
                    suffix="Hari"
                    hint="Sisa saldo: 8 hari"
                />
            </div>
        </div>

        <!-- Group 5: Multi-line Textarea -->
        <div class="ds-demo-group">
            <span class="ds-group-label">5. Formulir Multi-Baris (Textarea):</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisField
                    id="inp-catatan"
                    v-model="demoCatatan"
                    type="textarea"
                    label="Catatan Pertimbangan Persetujuan"
                    placeholder="Tuliskan catatan atau rekomendasi untuk direksi..."
                    hint="Dapat dibaca oleh seluruh level approval"
                    rows="3"
                    max-length="300"
                    show-count
                />
                <HcisField
                    id="inp-tugas"
                    v-model="demoTugas"
                    type="textarea"
                    label="Uraian Tanggung Jawab Jabatan Baru"
                    placeholder="Rincian poin tugas pokok dan fungsi..."
                    variant="filled"
                    rows="3"
                    max-length="500"
                    show-count
                />
            </div>
        </div>

        <!-- Group 6: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">6. Perbandingan Ukuran Input (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField
                    id="size-sm"
                    v-model="demoSizeSm"
                    size="sm"
                    label="Ukuran Kecil (sm - 32px)"
                    placeholder="Compact input 32px..."
                    leading-icon="sliders"
                />
                <HcisField
                    id="size-md"
                    v-model="demoSizeMd"
                    size="md"
                    label="Ukuran Standar (md - 38px)"
                    placeholder="Standard input 38px..."
                    leading-icon="sliders"
                />
                <HcisField
                    id="size-lg"
                    v-model="demoSizeLg"
                    size="lg"
                    label="Ukuran Besar (lg - 44px)"
                    placeholder="Hero CTA input 44px..."
                    leading-icon="stars"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Validasi & Aksesibilitas Formulir:</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- 1. Default / Pristine -->
                <HcisField
                    id="st-default"
                    v-model="stateDefault"
                    label="Status Default (Pristine)"
                    placeholder="Ketik nilai..."
                    hint="Bidang formulir normal sebelum disentuh"
                    leading-icon="pencil"
                />

                <!-- 2. Valid / Verified Success -->
                <HcisField
                    id="st-success"
                    v-model="stateSuccess"
                    label="Status Sukses (Valid Verified)"
                    success="Nomor WhatsApp aktif dan terverifikasi di server."
                    leading-icon="whatsapp"
                />

                <!-- 3. Invalid / Error Alert -->
                <HcisField
                    id="st-error"
                    v-model="stateError"
                    label="Status Galat (Validation Error)"
                    error="Tanggal pengajuan tidak boleh berada di masa lalu."
                    leading-icon="calendar-event"
                    required
                />

                <!-- 4. Disabled / Readonly -->
                <HcisField
                    id="st-disabled"
                    v-model="stateDisabled"
                    label="Status Non-Aktif (Disabled)"
                    hint="Hak akses diatur otomatis oleh sistem induk"
                    leading-icon="building"
                    disabled
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisField, HcisDropdown, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Dropdown Items
const countryCodeItems = [
    { label: '+62 Indonesia', value: '+62', icon: 'globe' },
    { label: '+65 Singapura', value: '+65', icon: 'globe' },
    { label: '+60 Malaysia', value: '+60', icon: 'globe' },
    { label: '+1 Amerika Serikat', value: '+1', icon: 'globe' },
];

const currencyItems = [
    { label: 'IDR (Rp) - Rupiah', value: 'IDR', icon: 'cash-stack' },
    { label: 'USD ($) - Dolar AS', value: 'USD', icon: 'currency-dollar' },
    { label: 'EUR (€) - Euro', value: 'EUR', icon: 'currency-euro' },
    { label: 'SGD (S$) - Dolar Singapura', value: 'SGD', icon: 'cash' },
];

const periodUnitItems = [
    { label: 'Bulan (Standar)', value: 'Bulan', icon: 'calendar-month' },
    { label: 'Tahun (Tahunan)', value: 'Tahun', icon: 'calendar3' },
    { label: 'Hari (Harian)', value: 'Hari', icon: 'calendar-day' },
];

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('812-3456-7890');
const studioLabel = ref('Nomor WhatsApp Karyawan');
const studioType = ref('text');
const studioPlaceholder = ref('Ketik nomor...');
const studioHint = ref('Kode negara terintegrasi otomatis dalam segmen prepend');
const studioError = ref('');
const studioSuccess = ref('');
const studioVariant = ref('outline');
const studioSize = ref('md');
const studioLeadingIcon = ref('');
const studioTrailingIcon = ref('');
const studioPrefix = ref('');
const studioSuffix = ref('');
const studioPrependMode = ref('select');
const studioPrependText = ref('https://');
const studioPrependSelect = ref('+62');
const studioAppendMode = ref('none');
const studioAppendText = ref('.hcis.id');
const studioAppendSelect = ref('Hari');
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioReadonly = ref(false);
const studioClearable = ref(true);
const studioShowPasswordToggle = ref(false);
const studioShowCount = ref(false);
const studioMaxLength = ref(50);
const studioShowCorner = ref(true);
const studioRows = ref(3);
const copied = ref(false);

// Showcase Demo State
const demoPhone = ref('812-3456-7890');
const demoCountryCode = ref('+62');
const demoCurrencyAmount = ref('15.000.000');
const demoCurrencyType = ref('IDR');
const demoPeriodVal = ref('12');
const demoPeriodUnit = ref('Bulan');
const demoSubdomain = ref('hq-jakarta');
const demoOtp = ref('940211');
const demoInviteLink = ref('https://hcis.cloud.id/onboard/EMP-2026-X99');
const demoLinkCopied = ref(false);

const demoNik = ref('2019-0431');
const demoEmail = ref('ayu.rahmawati');
const demoPassword = ref('S4ngatR4hasia#2026');
const demoPin = ref('889211');
const demoGaji = ref('1.450.000');
const demoKpi = ref('98.4');
const demoCuti = ref('4');
const demoCatatan = ref('Pengajuan telah diverifikasi oleh tim HR Operasional. Dokumen pendukung lengkap dan memenuhi syarat promosi.');
const demoTugas = ref('1. Menyusun strategi retensi talenta kunci.\n2. Melakukan evaluasi kepatuhan SOP divisi.');
const demoSizeSm = ref('Nilai kompak 32px');
const demoSizeMd = ref('Nilai standar 38px');
const demoSizeLg = ref('Nilai hero 44px');

// State Matrix
const stateDefault = ref('');
const stateSuccess = ref('0812-3456-7890');
const stateError = ref('12/08/2026');
const stateDisabled = ref('Divisi Human Capital Management');

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`id="user-field"`);
    props.push(`v-model="fieldValue"`);
    if (studioLabel.value) props.push(`label="${studioLabel.value}"`);
    if (studioType.value !== 'text') props.push(`type="${studioType.value}"`);
    if (studioPlaceholder.value) props.push(`placeholder="${studioPlaceholder.value}"`);
    if (studioHint.value) props.push(`hint="${studioHint.value}"`);
    if (studioError.value) props.push(`error="${studioError.value}"`);
    if (studioSuccess.value) props.push(`success="${studioSuccess.value}"`);
    if (studioVariant.value !== 'outline') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioLeadingIcon.value) props.push(`leading-icon="${studioLeadingIcon.value}"`);
    if (studioPrefix.value) props.push(`prefix="${studioPrefix.value}"`);
    if (studioSuffix.value) props.push(`suffix="${studioSuffix.value}"`);
    if (studioPrependMode.value === 'text') props.push(`prepend="${studioPrependText.value}"`);
    if (studioAppendMode.value === 'text') props.push(`append="${studioAppendText.value}"`);
    if (studioRequired.value) props.push(`required`);
    if (studioClearable.value) props.push(`clearable`);
    if (studioShowPasswordToggle.value) props.push(`show-password-toggle`);
    if (studioShowCount.value) {
        props.push(`show-count`);
        props.push(`:max-length="${studioMaxLength.value}"`);
    }
    if (studioDisabled.value) props.push(`disabled`);

    let templateSlots = '';
    if (studioPrependMode.value === 'select') {
        templateSlots += `\n    <template #prepend>\n        <HcisDropdown :label="countryCode" :items="countryItems" size="${studioSize.value}" @select="onSelectCountry" />\n    </template>`;
    }
    if (studioAppendMode.value === 'select') {
        templateSlots += `\n    <template #append>\n        <HcisDropdown :label="periodUnit" :items="unitItems" size="${studioSize.value}" @select="onSelectUnit" />\n    </template>`;
    } else if (studioAppendMode.value === 'button') {
        templateSlots += `\n    <template #append>\n        <HcisButton label="Kirim OTP" variant="primary" size="${studioSize.value}" leading-icon="send-fill" />\n    </template>`;
    }

    if (templateSlots) {
        return `<HcisField\n    ${props.join('\n    ')}\n>${templateSlots}\n</HcisField>`;
    }

    return `<HcisField\n    ${props.join('\n    ')}\n/>`;
});

function copySnippet() {
    navigator.clipboard.writeText(generatedSnippet.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function copyInviteLink() {
    navigator.clipboard.writeText(demoInviteLink.value);
    demoLinkCopied.value = true;
    setTimeout(() => {
        demoLinkCopied.value = false;
    }, 2500);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }

/* Studio Specific Classes */
.ds-studio-panel {
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: var(--hcis-surface, var(--hcis-white));
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.ds-studio-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.35rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: linear-gradient(135deg, var(--hcis-surface, var(--hcis-white)) 0%, var(--hcis-surface-soft, var(--hcis-slate-50)) 100%);
    flex-wrap: wrap;
    gap: 0.75rem;
}

.ds-studio-badge {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%);
    color: var(--hcis-white);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.ds-studio-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.ds-stage-switcher {
    display: flex;
    align-items: center;
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    padding: 3px;
    border-radius: 8px;
    gap: 2px;
}

.ds-stage-btn {
    border: none;
    background: transparent;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.ds-stage-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.ds-studio-body {
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
}

.ds-preview-stage {
    min-height: 240px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    transition: background 300ms ease;
}

.ds-stage-bg--light {
    background: radial-gradient(circle at center, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
    border: 1px dashed var(--hcis-slate-300);
}

.ds-stage-bg--dark {
    background: radial-gradient(circle at center, var(--hcis-slate-900) 0%, #020617 100%);
    border: 1px dashed var(--hcis-slate-700);
}

.ds-stage-bg--mesh {
    background:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.22) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.18) 0%, transparent 50%),
        var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
}

.ds-control-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin-bottom: 4px;
}

.ds-code-generator {
    background: var(--hcis-slate-900);
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--hcis-slate-800);
}

.ds-code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: var(--hcis-slate-800);
    border-bottom: 1px solid var(--hcis-slate-700);
}

.ds-code-lang {
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ds-copy-btn {
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: var(--hcis-slate-50);
    font-size: 0.72rem;
    font-weight: 650;
    padding: 3px 10px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.ds-copy-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.ds-code-block {
    margin: 0;
    padding: 1rem 1.25rem;
    color: var(--hcis-sky-400);
    font-family: ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas, monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}

@media (max-width: 640px) {
    .ds-preview-stage {
        padding: 1rem 0.5rem;
        min-height: auto;
    }
    .ds-studio-header {
        padding: 0.75rem 1rem;
    }
    .ds-studio-body {
        padding: 1rem;
        gap: 1rem;
    }
}
</style>
