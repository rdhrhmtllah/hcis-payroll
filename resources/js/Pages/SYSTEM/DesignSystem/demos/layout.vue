<template>
    <!-- Section: Anatomy -->
    <div v-if="section === 'anatomy'" class="ds-layout-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <i class="bi bi-grid-3x3-gap-fill text-indigo-600" /> HcisRow & HcisCol (Sistem Tata Letak 24-Kolom Terpadu)
                </span>
                <span class="ds-card__badge">24-Column Grid, Gutter Spacing & Responsive Breakpoints</span>
            </div>

            <div class="ds-card__body">
                <!-- Interactive Layout Config Controls -->
                <div class="ds-layout-preset-bar mb-4">
                    <span class="ds-demo-label mb-2 me-3">Pilih Presets Tata Letak Grid:</span>
                    <div class="d-flex flex-wrap gap-2">
                        <button
                            v-for="preset in layoutPresets"
                            :key="preset.name"
                            type="button"
                            class="ds-preset-btn"
                            :class="{ 'is-active': activePreset.name === preset.name }"
                            @click="activePreset = preset"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <!-- Live Grid Rendering -->
                <div class="ds-grid-canvas">
                    <HcisRow :gutter="gutterValue">
                        <HcisCol
                            v-for="(spanVal, idx) in activePreset.spans"
                            :key="idx"
                            :span="spanVal"
                        >
                            <div class="ds-grid-cell">
                                <span class="ds-grid-cell-title">Kolom {{ idx + 1 }}</span>
                                <small class="ds-grid-cell-span">Span: {{ spanVal }} / 24 ({{ Math.round((spanVal / 24) * 100) }}%)</small>
                            </div>
                        </HcisCol>
                    </HcisRow>
                </div>

                <!-- Gutter Control Slider -->
                <div class="ds-gutter-control mt-4 pt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ds-demo-label">Atur Jarak Sela Inter-Kolom (Gutter x & y):</span>
                        <strong class="text-indigo-600 font-mono">{{ gutterValue }}px</strong>
                    </div>
                    <input
                        v-model.number="gutterValue"
                        type="range"
                        min="0"
                        max="48"
                        step="8"
                        class="form-range"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <!-- Card 1: Responsive Breakpoints (Mobile, Tablet, Desktop) -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-display" /> Penataan Responsif Lintas Ukuran Layar</span>
                <span class="ds-card__tag">props: :xs="24" :sm="12" :md="8" :lg="6"</span>
            </div>
            <div class="ds-card__body">
                <p class="ds-demo-desc">
                    Kolom otomatis menyesuaikan lebar di <strong>HP (1 Kolom 100%)</strong>, <strong>Tablet (2 Kolom 50%)</strong>, <strong>Desktop (3 Kolom 33%)</strong>, dan <strong>Laptop Lebar (4 Kolom 25%)</strong>:
                </p>

                <HcisRow :gutter="16">
                    <HcisCol
                        v-for="n in 4"
                        :key="n"
                        :span="24"
                        :sm="12"
                        :md="8"
                        :lg="6"
                    >
                        <div class="ds-responsive-card">
                            <div class="ds-responsive-icon">
                                <i class="bi bi-box-seam" />
                            </div>
                            <div>
                                <strong class="d-block text-slate-800 fs-7">Kartu Modul {{ n }}</strong>
                                <small class="text-slate-500 fs-8">24 (xs) → 12 (sm) → 8 (md) → 6 (lg)</small>
                            </div>
                        </div>
                    </HcisCol>
                </HcisRow>
            </div>
        </div>

        <!-- Card 2: Nested Grid Layout -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-diagram-3-fill" /> Grid Bersarang (Nested Grid Layout)</span>
                <span class="ds-card__tag">HcisRow di dalam HcisCol</span>
            </div>
            <div class="ds-card__body">
                <HcisRow :gutter="24">
                    <!-- Left Sidebar (8 cols) -->
                    <HcisCol :span="24" :md="8">
                        <div class="ds-demo-panel ds-demo-panel--sidebar">
                            <i class="bi bi-layout-sidebar-inset me-1" /> Panel Navigasi Samping (Span: 8)
                        </div>
                    </HcisCol>

                    <!-- Right Main Area (16 cols with nested 2-col row inside) -->
                    <HcisCol :span="24" :md="16">
                        <div class="ds-demo-panel ds-demo-panel--main">
                            <span class="ds-demo-label mb-2">Area Utama (Span: 16) — Berisi Nested Grid Row:</span>
                            <HcisRow :gutter="12">
                                <HcisCol :span="12">
                                    <div class="ds-nested-box">Sub-Kartu 1 (Span: 12)</div>
                                </HcisCol>
                                <HcisCol :span="12">
                                    <div class="ds-nested-box">Sub-Kartu 2 (Span: 12)</div>
                                </HcisCol>
                            </HcisRow>
                        </div>
                    </HcisCol>
                </HcisRow>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-stack-demo">
        <!-- Card 1: Flex Alignment & Justification -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-arrows-collapse" /> Perataan Flexbox (Align & Justify)</span>
                <span class="ds-card__tag">align="middle" · justify="space-between"</span>
            </div>
            <div class="ds-card__body">
                <div class="mb-3">
                    <span class="ds-demo-label">Perataan Vertikal Tengah (align="middle"):</span>
                    <HcisRow :gutter="16" align="middle" class="ds-align-row">
                        <HcisCol :span="8">
                            <div class="ds-demo-box" style="height: 4.5rem">Kolom Tinggi (72px)</div>
                        </HcisCol>
                        <HcisCol :span="8">
                            <div class="ds-demo-box" style="height: 2.5rem">Kolom Sedang (40px)</div>
                        </HcisCol>
                        <HcisCol :span="8">
                            <div class="ds-demo-box" style="height: 3.5rem">Kolom Pendek (56px)</div>
                        </HcisCol>
                    </HcisRow>
                </div>
            </div>
        </div>

        <!-- Card 2: Inden Geser (Offset) -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-arrow-right-square" /> Inden Geser Kolom (Offset)</span>
                <span class="ds-card__tag">offset="4 | 8"</span>
            </div>
            <div class="ds-card__body">
                <HcisRow :gutter="16">
                    <HcisCol :span="8" :offset="4">
                        <div class="ds-demo-box">Span: 8 (Geser Inden Offset: 4)</div>
                    </HcisCol>
                    <HcisCol :span="8" :offset="4">
                        <div class="ds-demo-box">Span: 8 (Geser Inden Offset: 4)</div>
                    </HcisCol>
                </HcisRow>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
// Sengaja memakai alias "@" dan bukan jalur relatif: berkas inilah yang
// membuktikan alias itu benar-benar diselesaikan Vite saat build, sehingga
// potongan "Salin Import" di /design-system tidak bisa diam-diam patah lagi.
import { HcisRow, HcisCol } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const gutterValue = ref(16);

const layoutPresets = [
    { name: 'halves', label: '2 Kolom Seimbang (12-12)', spans: [12, 12] },
    { name: 'thirds', label: '3 Kolom Seimbang (8-8-8)', spans: [8, 8, 8] },
    { name: 'quarters', label: '4 Kolom Seimbang (6-6-6-6)', spans: [6, 6, 6, 6] },
    { name: 'sidebar', label: 'Sidebar + Main (8-16)', spans: [8, 16] },
    { name: 'dashboard', label: 'Dashboard Asimetris (16-8)', spans: [16, 8] },
    { name: 'hero', label: 'Pahlawan Utama (18-6)', spans: [18, 6] },
];

const activePreset = ref(layoutPresets[1]); // Default 8-8-8
</script>

<style scoped>
.ds-layout-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-card__badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 600;
    font-family: monospace;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ds-card__body {
    padding: 1.25rem;
}

.ds-demo-label {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: block;
}

.ds-demo-desc {
    font-size: 0.8rem;
    color: var(--hcis-text, var(--hcis-slate-700));
    margin-bottom: 1rem;
}

/* Preset Buttons */
.ds-preset-btn {
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    background: var(--hcis-white);
    color: var(--hcis-text, var(--hcis-slate-700));
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.ds-preset-btn:hover {
    border-color: var(--hcis-brand-300, var(--hcis-brand-300));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-preset-btn.is-active {
    background: var(--hcis-brand-500, var(--hcis-brand-500));
    border-color: var(--hcis-brand-500, var(--hcis-brand-500));
    color: var(--hcis-white);
    box-shadow: 0 2px 6px rgb(var(--hcis-brand-500-rgb) / 0.25);
}

/* Grid Canvas Cell */
.ds-grid-cell {
    padding: 1.25rem 1rem;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--hcis-brand-50) 0%, var(--hcis-brand-100) 100%);
    border: 1px dashed var(--hcis-brand-300, var(--hcis-brand-300));
    text-align: center;
    transition-property: var(--hcis-transition-props);
    transition-duration: 200ms;
    transition-timing-function: ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.ds-grid-cell-title {
    font-size: 0.825rem;
    font-weight: 800;
    color: var(--hcis-brand-900, var(--hcis-brand-900));
}

.ds-grid-cell-span {
    font-size: 0.7rem;
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    font-family: monospace;
}

/* Responsive Demo Card */
.ds-responsive-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem;
    border-radius: 10px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-responsive-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* Nested Panels */
.ds-demo-panel {
    padding: 1.25rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
}

.ds-demo-panel--sidebar {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    height: 100%;
    display: flex;
    align-items: center;
}

.ds-demo-panel--main {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-nested-box {
    padding: 0.875rem;
    border-radius: 8px;
    background: var(--hcis-success-50);
    border: 1px dashed var(--hcis-success-200);
    color: var(--hcis-success-700);
    text-align: center;
    font-size: 0.775rem;
    font-weight: 700;
}

.ds-align-row {
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 10px;
    padding: 0.5rem;
}

.ds-demo-box {
    padding: 0.75rem;
    border-radius: 8px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    border: 1px dashed var(--hcis-brand-300, var(--hcis-brand-300));
    color: var(--hcis-brand-800, var(--hcis-brand-800));
    font-size: 0.8rem;
    font-weight: 700;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
