<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisMessage & hcisMessage Toast Engine</h3>
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
            <!-- Preview Stage Canvas (Shows Inline Preview & Trigger Button) -->
            <div
                class="ds-preview-stage"
                :class="[
                    `ds-stage-bg--${studioStageBg}`,
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' || studioVariant === 'obsidian' }
                ]"
            >
                <div class="p-4 w-100 d-flex flex-column align-items-center gap-3" style="max-width: 580px;">
                    <!-- Inline Notification Preview -->
                    <HcisMessage
                        :type="studioType"
                        :variant="studioVariant"
                        :size="studioSize"
                        :rounded="studioRounded"
                        :title="studioTitle"
                        :message="studioMessage"
                        :badge="studioBadge"
                        :glow="studioGlow"
                        :pattern="studioPattern === 'none' ? false : studioPattern"
                        :closable="studioClosable"
                        :action-text="studioActionText"
                        @action="handleInlineAction"
                    />

                    <!-- Imperative Trigger CTA -->
                    <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mt-2">
                        <HcisButton
                            label="Picu Toast (Floating)"
                            :variant="studioType === 'error' ? 'danger' : (studioType === 'warning' ? 'warning' : 'primary')"
                            leading-icon="bell-fill"
                            @click="triggerStudioToast"
                        />
                        <HcisButton
                            label="Picu Toast Compact"
                            variant="secondary"
                            leading-icon="chat-square-dots-fill"
                            @click="triggerCompactToast"
                        />
                        <HcisButton
                            label="Tutup Semua Toast"
                            variant="ghost"
                            size="sm"
                            @click="closeAllToasts"
                        />
                    </div>

                    <div v-if="lastActionMessage" class="w-100 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastActionMessage }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Tipe Sentimen</label>
                    <select v-model="studioType" class="form-select form-select-sm">
                        <option value="success">success (Zamrud Berhasil)</option>
                        <option value="warning">warning (Amber Peringatan)</option>
                        <option value="error">error (Coral Galat)</option>
                        <option value="info">info (Sapphire Informasi)</option>
                        <option value="purple">purple (Amethyst Ungu)</option>
                        <option value="teal">teal (Mint Marine)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Wadah</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Solid Crystal)</option>
                        <option value="glass">Glass (Frosted Glass)</option>
                        <option value="hero">Hero (Luminescent Wash)</option>
                        <option value="flat">Flat (Minimalist)</option>
                        <option value="obsidian">Obsidian (Midnight Dark)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Ukuran / Kerapatan (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="compact">compact (Ramping Sebaris ~34px)</option>
                        <option value="md">md (Standar Berisi ~54px)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Kelengkungan Sudut (Rounded)</label>
                    <select v-model="studioRounded" class="form-select form-select-sm">
                        <option value="none">none (0px - Siku Tegas Siku-Siku)</option>
                        <option value="sm">sm (4px - Mikro Bevel)</option>
                        <option value="md">md (6px - Standar Eksekutif)</option>
                        <option value="lg">lg (8px - Soft Crisp)</option>
                        <option value="full">full (9999px - Kapsul)</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Pola Geometris (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="dots">Dot-Matrix (Crystal Stippling)</option>
                        <option value="grid">Micro-Grid (Geometric Grid)</option>
                        <option value="mesh">Diagonal Mesh (Isometric)</option>
                        <option value="none">Polos (Tanpa Pattern)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Posisi Toast</label>
                    <select v-model="studioPosition" class="form-select form-select-sm">
                        <option value="top-center">Top Center (Atas Tengah)</option>
                        <option value="top-right">Top Right (Atas Kanan)</option>
                        <option value="bottom-right">Bottom Right (Bawah Kanan)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Judul (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Pesan Singkat (Message)</label>
                    <input v-model="studioMessage" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Teks Tombol Aksi</label>
                    <input v-model="studioActionText" type="text" class="form-control form-control-sm" placeholder="Contoh: Urungkan" />
                </div>

                <div class="col-12 col-md-8">
                    <label class="ds-control-label">Fitur & Keadaan Notifikasi</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="msg-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="msg-glow">Ambient Halo Glow</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="msg-cls" v-model="studioClosable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="msg-cls">Tombol Tutup (Closable)</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Generator -->
            <div class="ds-code-generator">
                <div class="ds-code-header">
                    <span class="ds-code-lang">JavaScript Imperative & Vue 3 SFC</span>
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
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Tipe Compact Tegas (Crisp Modern Corners ~6px):</span>
            <div class="d-flex flex-column gap-2.5 p-3 bg-white rounded border border-slate-200 shadow-sm">
                <!-- Compact Success Crisp -->
                <HcisMessage
                    compact
                    rounded="md"
                    variant="glass"
                    type="success"
                    message="Perubahan kuota shift berhasil disinkronkan."
                    action-text="Lihat"
                    closable
                />
                <!-- Compact Warning Crisp -->
                <HcisMessage
                    compact
                    rounded="md"
                    variant="card"
                    type="warning"
                    title="Peringatan"
                    message="3 karyawan belum melengkapi presensi hari ini."
                    glow
                />
                <!-- Compact Error Flat Crisp -->
                <HcisMessage
                    compact
                    rounded="sm"
                    variant="flat"
                    type="error"
                    title="Gagal"
                    message="Format NIK tidak sesuai dengan standar perusahaan."
                    closable
                />
                <!-- Compact Obsidian Sharp -->
                <HcisMessage
                    compact
                    rounded="none"
                    variant="obsidian"
                    type="purple"
                    message="Modul AI KPI Unreal siap digunakan (Sudut Tegas 0px)."
                    action-text="Buka"
                />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Toast Floating Aksi Cepat (Undo / Stack):</span>
            <div class="d-flex flex-wrap gap-2 p-3 bg-white rounded border border-slate-200 shadow-sm align-items-center">
                <HcisButton
                    label="Picu Toast Compact (Crisp 6px)"
                    variant="success"
                    size="sm"
                    leading-icon="check-circle-fill"
                    @click="triggerCompactToast"
                />
                <HcisButton
                    label="Toast Dengan Aksi Urungkan (Undo)"
                    variant="primary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="triggerActionToast"
                />
                <HcisButton
                    label="Toast Tema Obsidian Dark"
                    variant="secondary"
                    size="sm"
                    leading-icon="moon-stars-fill"
                    @click="triggerDarkToast"
                />
                <HcisButton
                    label="Picu 3 Toast Berurutan (Queue Stack)"
                    variant="ghost"
                    size="sm"
                    leading-icon="layers-fill"
                    @click="triggerStackToasts"
                />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">3. Skenario Variasi Inline Kristal Lengkap (Hero & Glass):</span>
            <div class="d-flex flex-column gap-3">
                <HcisMessage
                    variant="hero"
                    rounded="md"
                    type="success"
                    title="Otorisasi Payroll Berhasil"
                    message="Total 142 data gaji bulan ini berhasil disahkan dan ditransfer ke rekening bank mitra."
                    badge="Selesai"
                    glow
                />
                <HcisMessage
                    variant="glass"
                    rounded="md"
                    type="purple"
                    title="Fitur Baru: Evaluasi KPI Unreal"
                    message="Sistem telah mengaktifkan modul otomatisasi KPI multi-cabang untuk seluruh tim manajemen."
                    action-text="Pelajari Fitur"
                    closable
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Koleksi Tipe Sentimen Notifikasi Inline (Crisp 6px):</span>
            <div class="p-3 bg-white rounded border border-slate-200 d-flex flex-column gap-2.5">
                <HcisMessage compact rounded="md" type="success" title="Sukses" message="Data permohonan cuti tahunan berhasil disetujui." />
                <HcisMessage compact rounded="md" type="warning" title="Peringatan" message="Sisa kuota lembur bulanan departemen tinggal 5 jam." />
                <HcisMessage compact rounded="md" type="error" title="Gagal" message="Koneksi database terputus saat sinkronisasi." />
                <HcisMessage compact rounded="md" type="info" title="Info" message="Server akan menjalani pemeliharaan malam ini pukul 23:00 WIB." />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisButton, HcisMessage, hcisMessage, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioType = ref('success');
const studioVariant = ref('card');
const studioSize = ref('compact');
const studioRounded = ref('md');
const studioPattern = ref('dots');
const studioPosition = ref('top-center');
const studioTitle = ref('Berhasil Disimpan');
const studioMessage = ref('Profil karyawan telah diperbarui.');
const studioBadge = ref('');
const studioActionText = ref('Lihat');
const studioGlow = ref(true);
const studioClosable = ref(true);
const lastActionMessage = ref('');
const copied = ref(false);

function triggerStudioToast() {
    hcisMessage[studioType.value](studioMessage.value, {
        title: studioTitle.value,
        theme: studioVariant.value === 'obsidian' ? 'dark' : 'light',
        position: studioPosition.value,
        rounded: studioRounded.value,
        actionText: studioActionText.value,
        onAction: () => {
            lastActionMessage.value = `Tombol aksi pada toast "${studioTitle.value}" diklik!`;
        },
    });
}

function triggerCompactToast() {
    hcisMessage.compact(studioMessage.value, {
        title: studioTitle.value,
        type: studioType.value,
        position: studioPosition.value,
        rounded: studioRounded.value,
        actionText: studioActionText.value,
        onAction: () => {
            lastActionMessage.value = `Tombol aksi pada compact toast diklik!`;
        },
    });
}

function handleInlineAction() {
    lastActionMessage.value = `Tombol aksi pada inline message "${studioTitle.value}" diklik!`;
}

function triggerActionToast() {
    hcisMessage.success('1 dokumen telah dipindahkan ke Tempat Sampah.', {
        title: 'Penghapusan Berhasil',
        actionText: 'Urungkan',
        position: studioPosition.value,
        rounded: studioRounded.value,
        onAction: () => {
            hcisMessage.info('Dokumen berhasil dikembalikan!', { title: 'Dibatalkan', position: studioPosition.value, rounded: studioRounded.value });
        },
    });
}

function triggerDarkToast() {
    hcisMessage.info('Sesi login baru terdeteksi di perangkat Windows Chrome.', {
        title: 'Keamanan Akun',
        theme: 'dark',
        position: studioPosition.value,
        rounded: studioRounded.value,
        actionText: 'Lihat Detail',
        onAction: () => {
            hcisMessage.success('Membuka detail perangkat...', { position: studioPosition.value, rounded: studioRounded.value });
        },
    });
}

function triggerStackToasts() {
    hcisMessage.info('Sinkronisasi data tahap 1 selesai.', { title: 'Tahap 1', position: studioPosition.value, rounded: studioRounded.value });
    setTimeout(() => hcisMessage.warning('Tahap 2 membutuhkan verifikasi manual.', { title: 'Verifikasi', position: studioPosition.value, rounded: studioRounded.value }), 150);
    setTimeout(() => hcisMessage.success('Seluruh antrean berhasil diproses!', { title: 'Selesai', position: studioPosition.value, rounded: studioRounded.value }), 300);
}

function closeAllToasts() {
    hcisMessage.closeAll();
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value === 'compact') props.push(`compact`);
    if (studioRounded.value !== 'md') props.push(`rounded="${studioRounded.value}"`);
    props.push(`type="${studioType.value}"`);
    if (studioTitle.value) props.push(`title="${studioTitle.value}"`);
    props.push(`message="${studioMessage.value}"`);
    if (studioPattern.value !== 'dots') props.push(`pattern="${studioPattern.value}"`);
    if (studioGlow.value) props.push(`:glow="true"`);
    if (studioClosable.value) props.push(`closable`);
    if (studioActionText.value) props.push(`action-text="${studioActionText.value}"`);

    return `// JavaScript Imperative Compact Toast:\nimport { hcisMessage } from '@/components/ui';\n\nhcisMessage.compact('${studioMessage.value}', {\n    title: '${studioTitle.value}',\n    type: '${studioType.value}',\n    rounded: '${studioRounded.value}'\n});\n\n// Vue 3 SFC Inline Component:\n<HcisMessage\n    ${props.join('\n    ')}\n    @action="onAction"\n/>`;
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
