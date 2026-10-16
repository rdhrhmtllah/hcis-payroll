<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisEmptyState Configurator</h3>
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
                <div style="width: 100%; max-width: 540px;">
                    <HcisEmptyState
                        :title="studioTitle"
                        :description="studioDescription"
                        :icon="studioIcon"
                        :variant="studioVariant"
                        :size="studioSize"
                        :sentiment="studioSentiment"
                    >
                        <template v-if="studioShowActions" #actions>
                            <HcisButton label="Buat Pengajuan Baru" variant="primary" size="sm">
                                <template #leading><HcisIcon name="plus-lg" size="xs" /></template>
                            </HcisButton>
                            <HcisButton label="Pelajari Panduan" variant="secondary" size="sm" />
                        </template>
                    </HcisEmptyState>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Wadah (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="default">Default (Tanpa Border Luar)</option>
                        <option value="dashed">Dashed Border (Garis Putus-putus)</option>
                        <option value="card">Card (Kartu Surface Solid)</option>
                        <option value="flat">Flat (Rata Minimalis)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Sentiment Nada</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="neutral">Neutral (Netral)</option>
                        <option value="info">Info (Informasi)</option>
                        <option value="success">Success (Sukses/Bersih)</option>
                        <option value="warning">Warning (Peringatan)</option>
                        <option value="danger">Danger (Kendala)</option>
                    </select>
                </div>

                <div class="col-12 col-md-8">
                    <label class="ds-control-label">Deskripsi Lengkap</label>
                    <input v-model="studioDescription" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-2">
                    <label class="ds-control-label">Ikon</label>
                    <select v-model="studioIcon" class="form-select form-select-sm">
                        <option value="inbox">inbox</option>
                        <option value="folder2-open">folder2-open</option>
                        <option value="search">search</option>
                        <option value="check2-circle">check2-circle</option>
                        <option value="cloud-slash">cloud-slash</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm)</option>
                        <option value="md">Standar (md)</option>
                        <option value="lg">Besar (lg)</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch m-0 pt-1">
                        <input id="es-act" v-model="studioShowActions" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="es-act">Tampilkan Tombol Aksi (#actions Slot)</label>
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
            <span class="ds-group-label">1. Skenario Keadaan Kosong (Empty States) Alur Operasional:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisEmptyState
                    icon="folder2-open"
                    title="Belum Ada SOP Terbit"
                    description="Unit kerja ini belum memiliki Standard Operating Procedure yang telah dipublikasikan secara resmi."
                    variant="card"
                    sentiment="info"
                >
                    <template #actions>
                        <HcisButton label="Buat Draf SOP Baru" variant="primary" size="sm" />
                    </template>
                </HcisEmptyState>

                <HcisEmptyState
                    icon="search"
                    title="Pencarian Tidak Ditemukan"
                    description="Tidak ada data pegawai yang sesuai dengan kombinasi kata kunci filter."
                    variant="dashed"
                    sentiment="warning"
                >
                    <template #actions>
                        <HcisButton label="Reset Filter Pencarian" variant="secondary" size="sm" />
                    </template>
                </HcisEmptyState>

                <HcisEmptyState
                    icon="check2-circle"
                    title="Semua Persetujuan Selesai!"
                    description="Seluruh pengajuan cuti dan lembur bawahan telah Anda tinjau tuntas."
                    variant="card"
                    sentiment="success"
                />

                <HcisEmptyState
                    icon="cloud-slash"
                    title="Koneksi Server Terputus"
                    description="Gagal memuat rekam riwayat kehadiran karena gangguan jaringan lokal."
                    variant="dashed"
                    sentiment="danger"
                >
                    <template #actions>
                        <HcisButton label="Muat Ulang" variant="secondary" size="sm" />
                    </template>
                </HcisEmptyState>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Perbandingan Hierarki Ukuran (sm / lg):</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisEmptyState
                    icon="file-earmark-text"
                    title="Ukuran Rapat (sm)"
                    description="Cocok untuk widget samping dashboard atau panel popover."
                    size="sm"
                    variant="dashed"
                />
                <HcisEmptyState
                    icon="rocket-takeoff"
                    title="Ukuran Hero (lg)"
                    description="Tampilan penuh untuk modul baru pertama kali diakses."
                    size="lg"
                    variant="card"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisEmptyState, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Belum ada pengajuan cuti');
const studioDescription = ref('Pengajuan cuti yang dibuat akan tercatat di sini beserta status persetujuan secara langsung.');
const studioIcon = ref('inbox');
const studioVariant = ref('dashed');
const studioSize = ref('md');
const studioSentiment = ref('neutral');
const studioShowActions = ref(true);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`title="${studioTitle.value}"`);
    if (studioDescription.value) props.push(`description="${studioDescription.value}"`);
    if (studioIcon.value !== 'inbox') props.push(`icon="${studioIcon.value}"`);
    if (studioVariant.value !== 'default') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioSentiment.value !== 'neutral') props.push(`sentiment="${studioSentiment.value}"`);

    if (studioShowActions.value) {
        return `<HcisEmptyState\n    ${props.join('\n    ')}\n>\n    <template #actions>\n        <HcisButton label="Buat Pengajuan Baru" variant="primary" size="sm" />\n    </template>\n</HcisEmptyState>`;
    }

    return `<HcisEmptyState\n    ${props.join('\n    ')}\n/>`;
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
