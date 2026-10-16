<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisStatusBadge Auto-Mapping Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 d-flex flex-column align-items-center gap-3">
                    <HcisStatusBadge
                        :status="studioStatus"
                        :size="studioSize"
                        :dot="studioDot"
                        :pulse="studioPulse"
                        :label="studioCustomLabel || undefined"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Kode Status Domain</label>
                    <select v-model="studioStatus" class="form-select form-select-sm">
                        <optgroup label="Disetujui & Aktif">
                            <option value="APPROVED">APPROVED (Disetujui)</option>
                            <option value="COMPLETED">COMPLETED (Selesai)</option>
                            <option value="ACTIVE">ACTIVE (Aktif)</option>
                        </optgroup>
                        <optgroup label="Menunggu & Proses">
                            <option value="PENDING">PENDING (Menunggu)</option>
                            <option value="PENDING_MANAGER">PENDING_MANAGER (Menunggu Manajer)</option>
                            <option value="PENDING_HR">PENDING_HR (Menunggu HR)</option>
                            <option value="IN_PROGRESS">IN_PROGRESS (Sedang Diproses)</option>
                        </optgroup>
                        <optgroup label="Ditolak & Gagal">
                            <option value="REJECTED">REJECTED (Ditolak)</option>
                            <option value="EXPIRED">EXPIRED (Kedaluwarsa)</option>
                            <option value="FAILED">FAILED (Gagal)</option>
                        </optgroup>
                        <optgroup label="Draf & Netral">
                            <option value="DRAFT">DRAFT (Draf)</option>
                            <option value="ARCHIVED">ARCHIVED (Diarsipkan)</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">xs</option>
                        <option value="sm">sm</option>
                        <option value="md">md</option>
                        <option value="lg">lg</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Kustom (Opsional)</label>
                    <input v-model="studioCustomLabel" type="text" placeholder="Gunakan label default status..." class="form-control form-control-sm" />
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
            <span class="ds-group-label">1. Matriks Status Transaksi Otorisasi Terpadu:</span>
            <div class="d-flex flex-wrap gap-2 p-3 bg-white rounded-xl border border-slate-200 shadow-sm align-items-center">
                <HcisStatusBadge status="APPROVED" />
                <HcisStatusBadge status="PENDING_MANAGER" />
                <HcisStatusBadge status="PENDING_HR" />
                <HcisStatusBadge status="IN_PROGRESS" />
                <HcisStatusBadge status="REJECTED" />
                <HcisStatusBadge status="DRAFT" />
                <HcisStatusBadge status="ACTIVE" />
                <HcisStatusBadge status="EXPIRED" />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Mode Dot & Pulse:</span>
            <div class="d-flex flex-wrap gap-3 p-3 bg-white rounded-xl border border-slate-200 align-items-center">
                <HcisStatusBadge status="ACTIVE" dot label="Sistem Online (Dot Statis)" />
                <HcisStatusBadge status="PENDING_MANAGER" pulse label="Menunggu Otorisasi (Pulse Glowing)" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisStatusBadge, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioStatus = ref('APPROVED');
const studioSize = ref('md');
const studioCustomLabel = ref('');
const studioDot = ref(false);
const studioPulse = ref(false);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`status="${studioStatus.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioCustomLabel.value) props.push(`label="${studioCustomLabel.value}"`);
    if (studioDot.value) props.push(`dot`);
    if (studioPulse.value) props.push(`pulse`);

    return `<HcisStatusBadge ${props.join(' ')} />`;
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
