<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisPagination Configurator</h3>
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
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200" style="width: 100%; max-width: 820px;">
                    <HcisPagination
                        v-model:current-page="currentPage"
                        v-model:page-size="pageSize"
                        :total="studioTotal"
                        :size="studioSize"
                        :layout="studioLayout"
                        :disabled="studioDisabled"
                        :background="studioBackground"
                        :page-sizes="[10, 25, 50, 100]"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Total Data (Total Items)</label>
                    <input v-model.number="studioTotal" type="number" min="0" step="10" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="md">Standar (md - 36px)</option>
                        <option value="sm">Kecil (sm - 30px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Tata Letak (Layout Segments)</label>
                    <select v-model="studioLayout" class="form-select form-select-sm">
                        <option value="total, sizes, prev, pager, next, jumper">Lengkap (Total + Sizes + Pager + Jumper)</option>
                        <option value="total, prev, pager, next">Standar (Total + Pager)</option>
                        <option value="prev, pager, next">Minimalis (Pager Only)</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pg-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-dis">Disabled State</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pg-bg" v-model="studioBackground" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-bg">Background Pills</label>
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
            <span class="ds-group-label">1. Skenario Tata Letak Lengkap vs Kompak:</span>
            <div class="d-flex flex-column gap-3">
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <span class="text-xs text-slate-500 font-semibold mb-2 d-block">Paginasi Utama Tabel Master (Lengkap):</span>
                    <HcisPagination
                        :current-page="3"
                        :page-size="25"
                        :total="1284"
                        layout="total, sizes, prev, pager, next, jumper"
                        :page-sizes="[10, 25, 50, 100]"
                    />
                </div>
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <span class="text-xs text-slate-500 font-semibold mb-2 d-block">Paginasi Sekunder Widget / Modal (Kompak):</span>
                    <HcisPagination
                        :current-page="1"
                        :page-size="10"
                        :total="86"
                        size="sm"
                        layout="total, prev, pager, next"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Non-Aktif (Disabled):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200">
                <HcisPagination
                    :current-page="2"
                    :page-size="25"
                    :total="450"
                    disabled
                    layout="total, sizes, prev, pager, next"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisPagination, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const currentPage = ref(3);
const pageSize = ref(25);
const studioTotal = ref(1284);
const studioSize = ref('md');
const studioLayout = ref('total, sizes, prev, pager, next, jumper');
const studioDisabled = ref(false);
const studioBackground = ref(true);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model:current-page="page"`);
    props.push(`v-model:page-size="pageSize"`);
    props.push(`:total="${studioTotal.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioLayout.value !== 'total, sizes, prev, pager, next') props.push(`layout="${studioLayout.value}"`);
    if (studioDisabled.value) props.push(`disabled`);

    return `<HcisPagination\n    ${props.join('\n    ')}\n    :page-sizes="[10, 25, 50, 100]"\n    @change="fetchData"\n/>`;
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
