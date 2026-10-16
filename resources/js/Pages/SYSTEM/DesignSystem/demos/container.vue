<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisContainer Layout Studio</h3>
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
                <div class="w-100" style="max-width: 620px;">
                    <HcisContainer
                        :variant="studioVariant"
                        :size="studioSize"
                        :padding="studioPadding"
                        :title="studioTitle"
                        :subtitle="studioSubtitle"
                        :icon="studioIcon"
                        :badge="studioBadge"
                        badge-variant="brand"
                        :loading="studioLoading"
                    >
                        <template #actions>
                            <HcisButton size="xs" variant="secondary" leading-icon="download">
                                Ekspor
                            </HcisButton>
                            <HcisButton size="xs" variant="primary" leading-icon="plus-lg">
                                Tambah
                            </HcisButton>
                        </template>

                        <div class="p-3 text-center bg-slate-50 border border-dashed border-slate-200 rounded-lg">
                            <HcisIcon name="layers-fill" size="md" class="text-indigo-600 mb-1" />
                            <h6 class="text-xs fw-bold text-slate-800 mb-1">Konten Inti Module Area</h6>
                            <p class="text-2xs text-slate-500 mb-0">
                                Container membungkus seluruh modul dengan margin, padding, serta responsif seluler yang konsisten.
                            </p>
                        </div>

                        <template #footer>
                            <span class="text-2xs text-slate-500 me-auto">Terakhir sinkron: Hari ini, 14:00</span>
                            <HcisButton size="xs" variant="ghost">Batal</HcisButton>
                            <HcisButton size="xs" variant="primary">Simpan</HcisButton>
                        </template>
                    </HcisContainer>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Container</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Visual</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">card (Standard Card)</option>
                        <option value="glass">glass (Translucent Glass)</option>
                        <option value="hero">hero (Top Accent Brand)</option>
                        <option value="flat">flat (Soft Surface)</option>
                        <option value="bordered">bordered (Minimal Border)</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="ds-control-label">Padding</label>
                    <select v-model="studioPadding" class="form-select form-select-sm">
                        <option value="sm">sm</option>
                        <option value="md">md</option>
                        <option value="lg">lg</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Opsi State</label>
                    <div class="d-flex align-items-center gap-3 pt-2">
                        <div class="form-check form-switch m-0">
                            <input id="cnt-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="cnt-load">Loading Overlay</label>
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
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Varian Hero Top Accent & Glass:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <HcisContainer
                        variant="hero"
                        title="Modul Kepegawaian"
                        subtitle="Fitur unggulan operasional HR"
                        icon="lightning-charge-fill"
                        badge="Hero"
                    >
                        <div class="p-2 text-xs text-slate-600">Aksen border brand atas dengan gradasi lembut.</div>
                    </HcisContainer>
                </div>
                <div class="col-12 col-md-6">
                    <HcisContainer
                        variant="glass"
                        title="Dashboard Eksekutif"
                        subtitle="Translucent blur kaca"
                        icon="stars"
                        badge="Glass"
                        badge-variant="info"
                    >
                        <div class="p-2 text-xs text-slate-600">Backdrop blur dengan elevasi pendar kaca mewah.</div>
                    </HcisContainer>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Loading Overlay:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200">
                <HcisContainer
                    variant="card"
                    title="Simulasi Loading State"
                    subtitle="Overlay transparan dengan animasi spinner"
                    :loading="true"
                >
                    <div class="p-3 text-center text-xs text-slate-500">Konten sedang dalam proses pemuatan...</div>
                </HcisContainer>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisContainer, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Profil & Struktur Organisasi');
const studioSubtitle = ref('Wadah terstandarisasi untuk mengelola informasi modul dan aksi.');
const studioVariant = ref('card');
const studioSize = ref('xl');
const studioPadding = ref('md');
const studioIcon = ref('box-seam-fill');
const studioBadge = ref('Versi 2.0');
const studioLoading = ref(false);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioTitle.value) props.push(`title="${studioTitle.value}"`);
    if (studioSubtitle.value) props.push(`subtitle="${studioSubtitle.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioIcon.value) props.push(`icon="${studioIcon.value}"`);
    if (studioBadge.value) props.push(`badge="${studioBadge.value}"`);
    if (studioLoading.value) props.push(`:loading="true"`);

    return `<HcisContainer\n    ${props.join('\n    ')}\n>\n    <template #actions>\n        <HcisButton size="xs" variant="primary">Aksi</HcisButton>\n    </template>\n    <p>Konten Modul</p>\n</HcisContainer>`;
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
