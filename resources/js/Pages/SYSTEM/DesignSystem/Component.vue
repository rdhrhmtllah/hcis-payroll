<template>
    <Head :title="`${entry?.name ?? 'Tidak ditemukan'} — HCIS Design System`" />

    <div class="hcis-page hcis-stack ds-page-root">
        <!-- Top Portal Navigation Bar -->
        <DsPortalNav :active-slug="props.slug" />

        <div v-if="!entry" class="hcis-stack hcis-stack--md">
            <HcisEmptyState
                icon="question-circle"
                title="Komponen tidak ditemukan"
                :description="`Tidak ada entri dengan slug &quot;${slug}&quot; di registry.js.`"
            >
                <template #actions>
                    <HcisButton label="Kembali ke Katalog" variant="secondary" href="/design-system" />
                </template>
            </HcisEmptyState>
        </div>

        <div v-else class="hcis-stack hcis-stack--md ds-preview">
            <!-- Breadcrumbs -->
            <HcisBreadcrumb :items="[{ label: 'Design System', href: '/design-system' }, { label: 'Komponen' }, { label: entry.name }]" />

            <!-- Identity Banner -->
            <div class="ds-detail-header">
                <div class="hcis-row hcis-row--between">
                    <div class="hcis-row">
                        <h1 class="hcis-title">{{ entry.name }}</h1>
                        <HcisBadge :label="statusLabel[entry.status]" :variant="statusVariant[entry.status]" dot />
                        <HcisBadge
                            :label="entry.kind === 'owned' ? 'Native (Milik Kita)' : 'Bridge (Element Plus)'"
                            :variant="entry.kind === 'owned' ? 'primary' : 'neutral'"
                        />
                        <span v-if="entry.group" class="ds-detail-group">{{ entry.group }}</span>
                    </div>
                </div>

                <p class="hcis-subtitle ds-gap">{{ entry.summary }}</p>

                <!-- Quick Import Code Snippet -->
                <div v-if="entry.kind === 'owned'" class="ds-snippet-box ds-gap">
                    <span class="ds-snippet-code">import { {{ entry.name }} } from '@/components/ui';</span>
                    <button class="ds-copy-btn" @click="copyCode(`import { ${entry.name} } from '@/components/ui';`)">
                        <HcisIcon name="clipboard" size="sm" /> Salin Import
                    </button>
                </div>

                <div class="ds-detail-notes">
                    <span v-if="entry.usedIn" class="ds-note-pill">
                        <HcisIcon name="bar-chart" size="xs" /> Dipakai di <strong>{{ entry.usedIn }} berkas</strong>
                    </span>
                    <span v-if="entry.kind === 'borrowed'" class="ds-note-pill ds-note-pill--borrowed">
                        <HcisIcon name="plug" size="xs" /> Perilaku milik Element Plus; gaya via <code>element-plus-bridge.css</code>
                    </span>
                </div>
            </div>

            <!-- INTERACTIVE PLAYGROUND / WORKBENCH -->
            <DsPlayground :entry="entry" />

            <!-- Sticky Section Navigation Tabs -->
            <nav class="ds-section-tabs">
                <button
                    v-for="tab in sectionTabs"
                    :key="tab.id"
                    class="ds-section-tab"
                    :class="{ 'is-active': activeSectionTab === tab.id }"
                    @click="scrollToSection(tab.id)"
                >
                    <HcisIcon :name="tab.icon" size="xs" />
                    <span>{{ tab.label }}</span>
                </button>
            </nav>

            <!-- SECTION 1: ANATOMI -->
            <DsSection
                id="sec-anatomi"
                no="1"
                title="Anatomi Komponen"
                description="Dirender langsung dengan Vue live preview. Bila komponen rusak, blok ini ikut terpengaruh."
            >
                <div class="ds-demo-wrapper">
                    <div class="ds-demo-toolbar">
                        <span>Prinjauan Anatomi</span>
                        <div class="ds-bg-picker">
                            <span class="ds-bg-picker__label">Latar Canvas:</span>
                            <button
                                class="ds-bg-dot ds-bg-dot--white"
                                :class="{ 'is-selected': demoBg === 'white' }"
                                title="Putih Bersih"
                                @click="demoBg = 'white'"
                            ></button>
                            <button
                                class="ds-bg-dot ds-bg-dot--slate"
                                :class="{ 'is-selected': demoBg === 'slate' }"
                                title="Soft Slate"
                                @click="demoBg = 'slate'"
                            ></button>
                            <button
                                class="ds-bg-dot ds-bg-dot--indigo"
                                :class="{ 'is-selected': demoBg === 'indigo' }"
                                title="Indigo Tint"
                                @click="demoBg = 'indigo'"
                            ></button>
                            <button
                                class="ds-bg-dot ds-bg-dot--dark"
                                :class="{ 'is-selected': demoBg === 'dark' }"
                                title="Dark Surface"
                                @click="demoBg = 'dark'"
                            ></button>
                        </div>
                    </div>

                    <div class="ds-demo-canvas" :style="demoCanvasStyle">
                        <component :is="demo" v-if="demo" section="anatomy" />
                        <p v-else class="ds-note">Berkas demo <code>demos/{{ slug }}.vue</code> belum tersedia.</p>
                    </div>
                </div>
            </DsSection>

            <!-- SECTION 2: VARIAN -->
            <DsSection id="sec-varian" no="2" title="Varian Komponen" description="Semua varian visual yang didukung komponen.">
                <div class="ds-demo-wrapper">
                    <div class="ds-demo-canvas" :style="demoCanvasStyle">
                        <component :is="demo" v-if="demo" section="variants" />
                    </div>
                </div>
            </DsSection>

            <!-- SECTION 3: STATE -->
            <DsSection id="sec-state" no="3" title="State & Interaksi" description="Keadaan aktif, fokus, tertekan, disabled, atau loading.">
                <div class="ds-demo-wrapper">
                    <div class="ds-demo-canvas" :style="demoCanvasStyle">
                        <component :is="demo" v-if="demo" section="states" />
                    </div>
                </div>
            </DsSection>

            <!-- SECTION 4: API -->
            <DsSection id="sec-api" no="4" title="Spesifikasi API" description="Deklarasi Props, Events, Slots, dan batasan penggunaan.">
                <DsApiTable
                    :prop-list="entry.props || []"
                    :events="entry.events || []"
                    :slot-list="entry.slots || []"
                    :allowed="entry.allowed || []"
                    :forbidden="entry.forbidden || []"
                />
            </DsSection>

            <!-- SECTION 5: ATURAN PAKAI -->
            <DsSection
                id="sec-rules"
                no="5"
                title="Aturan Pakai (Do & Don't)"
                description="Aturan resmi penggunaan komponen untuk menjaga keharmonisan UI/UX seluruh aplikasi."
            >
                <DsRules :do-list="entry.rules?.do || []" :dont-list="entry.rules?.dont || []" />
            </DsSection>

            <!-- SECTION 6: POLA MIGRASI -->
            <DsSection
                id="sec-migration"
                no="6"
                title="Pola Migrasi (Legacy Replacement)"
                description="Pola markup/CSS lama yang wajib digantikan oleh komponen ini."
            >
                <DsMigration :items="entry.replaces || []" />
            </DsSection>

            <!-- Toast Alert -->
            <div v-if="toastText" class="ds-toast">
                <HcisIcon name="check-lg" size="sm" />
                <span>Kode <code>{{ toastText }}</code> berhasil disalin!</span>
            </div>

            <DsMotionBanner />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';
import { Head } from '@inertiajs/vue3';
import { HcisBadge, HcisBreadcrumb, HcisButton, HcisEmptyState, HcisIcon } from '@/components/ui';
import { getEntry } from '@/components/ui/registry';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsPlayground from './partials/DsPlayground.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';
import DsSection from './partials/DsSection.vue';
import DsApiTable from './partials/DsApiTable.vue';
import DsRules from './partials/DsRules.vue';
import DsMigration from './partials/DsMigration.vue';
import '../../../../css/element-plus-bridge.css';

const props = defineProps({ slug: { type: String, required: true } });

const demoBg = ref('slate');
const activeSectionTab = ref('sec-anatomi');
const toastText = ref('');

const statusLabel = { ready: 'Siap', draft: 'Draf', planned: 'Rencana', deprecated: 'Pensiun' };
const statusVariant = { ready: 'success', draft: 'warning', planned: 'neutral', deprecated: 'danger' };

const entry = computed(() => getEntry(props.slug));

const demoModules = import.meta.glob('./demos/*.vue');

const demo = computed(() => {
    const path = `./demos/${props.slug}.vue`;
    return demoModules[path] ? defineAsyncComponent(demoModules[path]) : null;
});

const sectionTabs = [
    { id: 'sec-anatomi', label: 'Anatomi', icon: 'eye' },
    { id: 'sec-varian', label: 'Varian', icon: 'palette' },
    { id: 'sec-state', label: 'State', icon: 'lightning' },
    { id: 'sec-api', label: 'Spesifikasi API', icon: 'card-list' },
    { id: 'sec-rules', label: 'Aturan Pakai', icon: 'check-circle' },
    { id: 'sec-migration', label: 'Pola Migrasi', icon: 'arrow-repeat' },
];

const demoCanvasStyle = computed(() => {
    switch (demoBg.value) {
        case 'white':
            return { background: 'var(--hcis-white)', color: 'var(--hcis-slate-800)' };
        case 'slate':
            return { background: 'var(--hcis-slate-50)', color: 'var(--hcis-slate-800)' };
        case 'indigo':
            return { background: 'var(--hcis-indigo-50)', color: 'var(--hcis-indigo-900)' };
        case 'dark':
            return { background: 'var(--hcis-slate-900)', color: 'var(--hcis-slate-50)' };
        default:
            return { background: 'var(--hcis-slate-50)' };
    }
});

function scrollToSection(id) {
    activeSectionTab.value = id;
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

function copyCode(text) {
    navigator.clipboard.writeText(text).then(() => {
        toastText.value = text;
        setTimeout(() => {
            toastText.value = '';
        }, 2500);
    });
}
</script>

<style scoped>
.ds-page-root {
    padding-bottom: var(--hcis-space-12);
}

.ds-detail-header {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-xl);
    padding: var(--hcis-space-6);
    box-shadow: 0 4px 20px -2px rgb(var(--hcis-shadow-rgb) / 0.05);
}

.ds-detail-group {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-brand-50);
    border: 1px solid var(--hcis-brand-200);
    color: var(--hcis-brand-700);
    font-size: var(--hcis-font-size-xs);
    font-weight: 700;
}

.ds-snippet-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--hcis-space-3) var(--hcis-space-4);
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-slate-900);
    border: 1px solid var(--hcis-slate-800);
}

.ds-snippet-code {
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-sky-400);
}

.ds-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    background: var(--hcis-slate-800);
    border: 1px solid var(--hcis-slate-700);
    color: var(--hcis-slate-200);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    padding: 4px 10px;
    border-radius: var(--hcis-radius-md);
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-copy-btn:hover {
    background: var(--hcis-brand-600);
    border-color: var(--hcis-brand-500);
    color: var(--hcis-white);
}

.ds-detail-notes {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    margin-top: var(--hcis-space-4);
    flex-wrap: wrap;
}

.ds-note-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
}

.ds-note-pill--borrowed {
    background: var(--hcis-warning-50);
    border-color: var(--hcis-warning-200);
    color: var(--hcis-warning-700);
}

.ds-section-tabs {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    overflow-x: auto;
    padding-bottom: var(--hcis-space-2);
    border-bottom: 1px solid var(--hcis-border);
    margin: var(--hcis-space-4) 0;
}

.ds-section-tab {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    padding: var(--hcis-space-2) var(--hcis-space-4);
    border-radius: var(--hcis-radius-pill);
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    cursor: pointer;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
    white-space: nowrap;
}

.ds-section-tab:hover {
    color: var(--hcis-brand-600);
    background: var(--hcis-surface-soft);
}

.ds-section-tab.is-active {
    color: var(--hcis-brand-700);
    background: var(--hcis-brand-50);
    border-color: var(--hcis-brand-200);
}

.ds-demo-wrapper {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    overflow: hidden;
}

.ds-demo-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--hcis-space-2-5) var(--hcis-space-4);
    background: var(--hcis-surface-soft);
    border-bottom: 1px solid var(--hcis-border);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-text-muted);
}

.ds-bg-picker {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
}

.ds-bg-picker__label {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-muted);
    font-weight: 700;
    text-transform: uppercase;
}

.ds-bg-dot {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 1px solid var(--hcis-border-strong);
    cursor: pointer;
}

.ds-bg-dot--white { background: var(--hcis-white); }
.ds-bg-dot--slate { background: var(--hcis-slate-50); }
.ds-bg-dot--indigo { background: var(--hcis-indigo-50); }
.ds-bg-dot--dark { background: var(--hcis-slate-900); }

.ds-bg-dot.is-selected {
    border-color: var(--hcis-brand-600);
    transform: scale(1.2);
}

.ds-demo-canvas {
    padding: var(--hcis-space-6);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 160px;
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
}

.ds-demo-canvas > * {
    min-width: 0;
    max-width: 100%;
    width: 100%;
}

@media (max-width: 768px) {
    .ds-demo-canvas {
        padding: var(--hcis-space-3);
    }
}

.ds-toast {
    position: fixed;
    bottom: var(--hcis-space-6);
    right: var(--hcis-space-6);
    z-index: 999;
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-3) var(--hcis-space-5);
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    border-radius: var(--hcis-radius-lg);
    box-shadow: 0 10px 25px -5px rgb(var(--hcis-black-rgb) / 0.3);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
}

.ds-gap {
    margin-top: var(--hcis-space-3);
}

.ds-note {
    margin: var(--hcis-space-2) 0 0;
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-sm);
}

code {
    background: var(--hcis-surface-soft);
    border-radius: var(--hcis-space-1);
    padding: 1px 5px;
    font-family: var(--hcis-font-mono);
    font-size: 0.85em;
}
</style>
