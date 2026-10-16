<template>
    <HcisDialog
        :model-value="modelValue"
        :title="record ? `Ubah Bobot KPI & Kompetensi · ${selectedLevelName}` : 'Atur Bobot KPI & Kompetensi Level'"
        kicker="MASTER DATA · BOBOT KPI & KOMPETENSI"
        description="Atur proporsi pembagian bobot antara capaian KPI dan penilaian Kompetensi per level jabatan."
        icon="pie-chart-fill"
        sentiment="brand"
        variant="glass"
        :size="isMobile ? 'full' : 'lg'"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <form class="pa-pillar-editor" @submit.prevent="handleSubmit">
            <!-- Notice bar -->
            <div class="pa-pillar-editor__notice">
                <HcisIcon name="info-circle-fill" size="xs" />
                <div>
                    <strong>Ketentuan Bobot KPI &amp; Kompetensi</strong>
                    <span>
                        Total Bobot KPI dan Kompetensi wajib tepat <strong>100%</strong>. Perubahan pada master ini
                        berlaku untuk penyiapan cycle penilaian berikutnya.
                    </span>
                </div>
            </div>

            <!-- Level Selector / Navigator Card -->
            <div class="pa-pillar-editor__level-card">
                <div class="pa-pillar-editor__level-info">
                    <div class="pa-pillar-editor__level-header-row">
                        <span class="pa-eyebrow">LEVEL JABATAN PERUSAHAAN</span>
                        <div class="pa-level-nav-stepper" v-if="allHierarchyLevels.length > 1">
                            <button
                                type="button"
                                class="pa-nav-level-btn"
                                :disabled="currentLevelIndex <= 0"
                                title="Level Sebelumnya"
                                @click="goToLevelIndex(currentLevelIndex - 1)"
                            >
                                <HcisIcon name="chevron-left" size="2xs" /> Prev
                            </button>
                            <span class="pa-nav-level-counter">
                                {{ currentLevelIndex + 1 }} / {{ allHierarchyLevels.length }}
                            </span>
                            <button
                                type="button"
                                class="pa-nav-level-btn"
                                :disabled="currentLevelIndex >= allHierarchyLevels.length - 1"
                                title="Level Berikutnya"
                                @click="goToLevelIndex(currentLevelIndex + 1)"
                            >
                                Next <HcisIcon name="chevron-right" size="2xs" />
                            </button>
                        </div>
                    </div>

                    <div class="pa-select-field">
                        <label for="pa-pillar-level-select" class="sr-only">Pilih Level Jabatan</label>
                        <select
                            id="pa-pillar-level-select"
                            v-model="form.id_level"
                            required
                            @change="onLevelChange"
                        >
                            <option value="">Pilih level jabatan...</option>
                            <option
                                v-for="lvl in allHierarchyLevels"
                                :key="lvl.ID_Level"
                                :value="lvl.ID_Level"
                            >
                                {{ lvl.Nama_Level }} (Level {{ lvl.ID_Level }})
                                {{ lvl.hasConfig ? '— [Sudah Diatur]' : '— [Belum Diatur]' }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="selectedLevelObj" class="pa-pillar-editor__level-badge">
                    <span class="pa-eyebrow">HIERARKI</span>
                    <strong>Tingkat {{ selectedLevelObj.Level_Hierarchy || selectedLevelObj.ID_Level }}</strong>
                </div>
            </div>

            <!-- Interactive Dual Ratio Controller -->
            <section class="pa-pillar-editor__controller" aria-label="Kontrol Pembagian Bobot">
                <div class="pa-pillar-editor__controller-header">
                    <div>
                        <span class="pa-eyebrow">PEMBAGIAN PROPORSI</span>
                        <h4>Keseimbangan KPI vs Kompetensi</h4>
                    </div>
                    <HcisBadge
                        :label="isSumExact100 ? 'Total 100% (Valid)' : `Total: ${totalWeight}% (Harus 100%)`"
                        :variant="isSumExact100 ? 'success' : 'danger'"
                        :appearance="isSumExact100 ? 'glass' : 'subtle'"
                        size="sm"
                        :dot="true"
                    />
                </div>

                <!-- Live Dual Stacked Progress Bar -->
                <div class="pa-pillar-editor__visual-track">
                    <div
                        class="pa-pillar-editor__track-segment pa-pillar-editor__track-segment--kpi"
                        :style="{ width: `${clampedKpi}%` }"
                        :title="`Bobot KPI: ${clampedKpi}%`"
                    >
                        <span v-if="clampedKpi >= 12" class="pa-pillar-editor__track-label">
                            <HcisIcon name="graph-up-arrow" size="2xs" /> KPI {{ clampedKpi }}%
                        </span>
                    </div>
                    <div
                        class="pa-pillar-editor__track-segment pa-pillar-editor__track-segment--komp"
                        :style="{ width: `${clampedKomp}%` }"
                        :title="`Bobot Kompetensi: ${clampedKomp}%`"
                    >
                        <span v-if="clampedKomp >= 12" class="pa-pillar-editor__track-label">
                            <HcisIcon name="bullseye" size="2xs" /> Kompetensi {{ clampedKomp }}%
                        </span>
                    </div>
                </div>

                <!-- Interactive Dual Slider -->
                <div class="pa-pillar-editor__slider-wrap">
                    <div class="pa-pillar-editor__slider-labels">
                        <span class="pa-pillar-editor__slider-kpi-label">
                            <HcisIcon name="graph-up-arrow" size="xs" /> Bobot KPI: <strong>{{ form.bobot_kpi }}%</strong>
                        </span>
                        <span class="pa-pillar-editor__slider-komp-label">
                            <HcisIcon name="bullseye" size="xs" /> Bobot Kompetensi: <strong>{{ form.bobot_kompetensi }}%</strong>
                        </span>
                    </div>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="5"
                        :value="form.bobot_kpi"
                        class="pa-pillar-editor__slider"
                        aria-label="Slider Pembagian Bobot KPI"
                        @input="onSliderChange($event.target.value)"
                    />
                    <div class="pa-pillar-editor__slider-ticks">
                        <span>0% KPI</span>
                        <span>25%</span>
                        <span>50%</span>
                        <span>75%</span>
                        <span>100% KPI</span>
                    </div>
                </div>

                <!-- Direct Number Inputs with Steppers -->
                <div class="pa-pillar-editor__inputs-grid">
                    <div class="pa-pillar-editor__number-box pa-pillar-editor__number-box--kpi">
                        <label for="pa-input-bobot-kpi">
                            <HcisIcon name="graph-up-arrow" size="xs" />
                            <span>Bobot KPI (%) <em>*</em></span>
                        </label>
                        <div class="pa-pillar-editor__stepper">
                            <button
                                type="button"
                                class="pa-pillar-editor__stepper-btn"
                                :disabled="form.bobot_kpi <= 0"
                                title="Kurangi 5%"
                                @click="adjustKpi(-5)"
                            >
                                −5
                            </button>
                            <input
                                id="pa-input-bobot-kpi"
                                v-model.number="form.bobot_kpi"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                required
                                class="pa-pillar-editor__input-number"
                                @input="onKpiInput"
                            />
                            <button
                                type="button"
                                class="pa-pillar-editor__stepper-btn"
                                :disabled="form.bobot_kpi >= 100"
                                title="Tambah 5%"
                                @click="adjustKpi(5)"
                            >
                                +5
                            </button>
                        </div>
                    </div>

                    <div class="pa-pillar-editor__number-box pa-pillar-editor__number-box--komp">
                        <label for="pa-input-bobot-komp">
                            <HcisIcon name="bullseye" size="xs" />
                            <span>Bobot Kompetensi (%) <em>*</em></span>
                        </label>
                        <div class="pa-pillar-editor__stepper">
                            <button
                                type="button"
                                class="pa-pillar-editor__stepper-btn"
                                :disabled="form.bobot_kompetensi <= 0"
                                title="Kurangi 5%"
                                @click="adjustKomp(-5)"
                            >
                                −5
                            </button>
                            <input
                                id="pa-input-bobot-komp"
                                v-model.number="form.bobot_kompetensi"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                required
                                class="pa-pillar-editor__input-number"
                                @input="onKompInput"
                            />
                            <button
                                type="button"
                                class="pa-pillar-editor__stepper-btn"
                                :disabled="form.bobot_kompetensi >= 100"
                                title="Tambah 5%"
                                @click="adjustKomp(5)"
                            >
                                +5
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Presets -->
                <div class="pa-pillar-editor__presets">
                    <span class="pa-pillar-editor__presets-title">
                        <HcisIcon name="lightning-charge-fill" size="2xs" /> Preset Standar Korporasi:
                    </span>
                    <div class="pa-pillar-editor__presets-list">
                        <button
                            v-for="preset in presets"
                            :key="`${preset.kpi}-${preset.komp}`"
                            type="button"
                            class="pa-pillar-preset-chip"
                            :class="{ active: form.bobot_kpi === preset.kpi && form.bobot_kompetensi === preset.komp }"
                            @click="applyPreset(preset.kpi, preset.komp)"
                        >
                            <strong>{{ preset.kpi }} / {{ preset.komp }}</strong>
                            <small>{{ preset.label }}</small>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Catatan Field & Rationale Helper -->
            <div class="pa-pillar-editor__notes">
                <div class="pa-rationale-header">
                    <label for="pa-pillar-catatan" class="pa-rationale-title">Catatan / Rasionalisasi Bisnis</label>
                    <div class="pa-rationale-chips">
                        <button
                            type="button"
                            class="pa-rationale-chip"
                            @click="form.catatan = 'Fokus pada pencapaian target output operasional harian (KPI dominant).'"
                        >
                            + Target Operasional
                        </button>
                        <button
                            type="button"
                            class="pa-rationale-chip"
                            @click="form.catatan = 'Keseimbangan antara pencapaian target KPI dan kompetensi kepemimpinan/manajerial.'"
                        >
                            + Keseimbangan Manajerial
                        </button>
                        <button
                            type="button"
                            class="pa-rationale-chip"
                            @click="form.catatan = 'Penekanan pada aspek kepemimpinan, integritas, dan nilai budaya korporasi.'"
                        >
                            + Kepemimpinan & Budaya
                        </button>
                    </div>
                </div>
                <HcisTextarea
                    id="pa-pillar-catatan"
                    v-model="form.catatan"
                    placeholder="Contoh: Pembagian bobot 80/20 untuk level operasional sesuai arahan HC Corporate..."
                    :rows="2"
                    :maxlength="500"
                />
            </div>

            <!-- Error alert -->
            <HcisResultState
                v-if="error"
                status="danger"
                variant="flat"
                layout="inline"
                title="Perubahan ditolak"
                :description="error"
                :show-retry="false"
            />
        </form>

        <template #footer>
            <div class="pa-dialog-actions--responsive">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    :disabled="saving"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    variant="primary"
                    label="Simpan Bobot KPI &amp; Kompetensi"
                    size="sm"
                    leading-icon="check2-circle"
                    :loading="saving"
                    :disabled="saving || !isValidForm"
                    @click="handleSubmit"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisIcon,
    HcisResultState,
    HcisTextarea,
} from '@/components/ui';

defineOptions({ name: 'PaBobotPilarEditorDialog' });

const isMobile = ref(false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', cekUkuranLayar);
});

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    record: { type: Object, default: null },
    existingRows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = reactive({
    id_level: '',
    bobot_kpi: 80,
    bobot_kompetensi: 20,
    catatan: '',
});

const presets = [
    { kpi: 80, komp: 20, label: 'Staff / Operasional' },
    { kpi: 70, komp: 30, label: 'Supervisor / Officer' },
    { kpi: 60, komp: 40, label: 'Managerial' },
    { kpi: 50, komp: 50, label: 'Seimbang' },
    { kpi: 0, komp: 100, label: 'Spesialis / Murni Kompetensi' },
    { kpi: 100, komp: 0, label: 'Komersial / Murni KPI' },
];

const allHierarchyLevels = computed(() => {
    const rawLevels = props.references?.level || [];
    return [...rawLevels].sort((a, b) => {
        const rankA = Number(a.Level_Hierarchy) || Number(a.ID_Level) || 999;
        const rankB = Number(b.Level_Hierarchy) || Number(b.ID_Level) || 999;
        return rankA - rankB;
    }).map((lvl) => {
        const id = Number(lvl.ID_Level);
        const hasConfig = props.existingRows.some((r) => Number(r.ID_Level) === id);
        return {
            ...lvl,
            ID_Level: id,
            hasConfig,
        };
    });
});

const currentLevelIndex = computed(() => {
    if (!form.id_level) return -1;
    return allHierarchyLevels.value.findIndex((l) => Number(l.ID_Level) === Number(form.id_level));
});

const selectedLevelObj = computed(() => {
    if (!form.id_level) return null;
    return allHierarchyLevels.value.find((l) => Number(l.ID_Level) === Number(form.id_level)) || null;
});

const selectedLevelName = computed(() => {
    if (selectedLevelObj.value) return selectedLevelObj.value.Nama_Level;
    if (props.record?.ID_Level) {
        const match = allHierarchyLevels.value.find((l) => Number(l.ID_Level) === Number(props.record.ID_Level));
        return match?.Nama_Level || `Level ${props.record.ID_Level}`;
    }
    return 'Pilih Level Jabatan';
});

const totalWeight = computed(() => {
    const kpi = Number(form.bobot_kpi) || 0;
    const komp = Number(form.bobot_kompetensi) || 0;
    return Math.round((kpi + komp) * 100) / 100;
});

const isSumExact100 = computed(() => totalWeight.value === 100);

const clampedKpi = computed(() => Math.max(0, Math.min(100, Number(form.bobot_kpi) || 0)));
const clampedKomp = computed(() => Math.max(0, Math.min(100, Number(form.bobot_kompetensi) || 0)));

const isValidForm = computed(() => {
    return Boolean(form.id_level) && isSumExact100.value && form.bobot_kpi >= 0 && form.bobot_kompetensi >= 0;
});

function goToLevelIndex(idx) {
    if (idx >= 0 && idx < allHierarchyLevels.value.length) {
        form.id_level = allHierarchyLevels.value[idx].ID_Level;
        onLevelChange();
    }
}

function syncData() {
    if (props.record) {
        form.id_level = props.record.ID_Level ?? '';
        form.bobot_kpi = Number(props.record.Bobot_KPI ?? 80);
        form.bobot_kompetensi = Number(props.record.Bobot_Kompetensi ?? 20);
        form.catatan = props.record.Catatan ?? '';
    } else {
        form.id_level = '';
        form.bobot_kpi = 80;
        form.bobot_kompetensi = 20;
        form.catatan = '';
    }
}

function onLevelChange() {
    if (form.id_level) {
        const found = props.existingRows.find((r) => Number(r.ID_Level) === Number(form.id_level));
        if (found) {
            form.bobot_kpi = Number(found.Bobot_KPI ?? 80);
            form.bobot_kompetensi = Number(found.Bobot_Kompetensi ?? 20);
            form.catatan = found.Catatan ?? '';
        } else {
            form.bobot_kpi = 80;
            form.bobot_kompetensi = 20;
            form.catatan = '';
        }
    }
}

function onSliderChange(val) {
    const kpi = Math.max(0, Math.min(100, Number(val) || 0));
    form.bobot_kpi = kpi;
    form.bobot_kompetensi = 100 - kpi;
}

function onKpiInput() {
    let kpi = form.bobot_kpi;
    if (kpi === '' || kpi === null || isNaN(kpi)) {
        return;
    }
    kpi = Math.max(0, Math.min(100, Number(kpi)));
    form.bobot_kpi = kpi;
    form.bobot_kompetensi = Math.max(0, 100 - kpi);
}

function onKompInput() {
    let komp = form.bobot_kompetensi;
    if (komp === '' || komp === null || isNaN(komp)) {
        return;
    }
    komp = Math.max(0, Math.min(100, Number(komp)));
    form.bobot_kompetensi = komp;
    form.bobot_kpi = Math.max(0, 100 - komp);
}

function adjustKpi(delta) {
    const next = Math.max(0, Math.min(100, (Number(form.bobot_kpi) || 0) + delta));
    form.bobot_kpi = next;
    form.bobot_kompetensi = 100 - next;
}

function adjustKomp(delta) {
    const next = Math.max(0, Math.min(100, (Number(form.bobot_kompetensi) || 0) + delta));
    form.bobot_kompetensi = next;
    form.bobot_kpi = 100 - next;
}

function applyPreset(kpi, komp) {
    form.bobot_kpi = kpi;
    form.bobot_kompetensi = komp;
}

function handleSubmit() {
    if (!isValidForm.value) return;
    emit('save', {
        id_level: Number(form.id_level),
        bobot_kpi: Number(form.bobot_kpi),
        bobot_kompetensi: Number(form.bobot_kompetensi),
        catatan: form.catatan ? String(form.catatan).trim() : null,
    });
}

watch(() => [props.modelValue, props.record], syncData, { immediate: true, deep: true });
</script>

<style scoped>
.pa-pillar-editor {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
}

.pa-pillar-editor__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(238, 242, 255, 0.7);
    color: #4338ca;
    font-size: 0.75rem;
    line-height: 1.45;
}

.pa-pillar-editor__notice strong {
    display: block;
    font-size: 0.78rem;
    margin-bottom: 0.15rem;
    color: #3730a3;
}

.pa-pillar-editor__level-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(203, 213, 225, 0.8);
    border-radius: var(--hcis-radius-lg, 14px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.75));
}

.pa-pillar-editor__level-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}

.pa-pillar-editor__level-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-level-nav-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-nav-level-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    padding: 0.15rem 0.45rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 4px);
    background: #ffffff;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.68rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-nav-level-btn:hover:not(:disabled) {
    background: #f1f5f9;
    color: #4f46e5;
}

.pa-nav-level-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pa-nav-level-counter {
    font-size: 0.68rem;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-muted, #64748b);
    font-weight: 700;
}

.pa-pillar-editor__level-badge {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.15rem;
}

.pa-pillar-editor__level-badge strong {
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-select-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-select-field select {
    min-height: 2.375rem;
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    font: inherit;
    font-size: 0.8rem;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.pa-pillar-editor__controller {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    padding: 1rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.45), rgba(255, 255, 255, 0.85));
}

.pa-pillar-editor__controller-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.pa-pillar-editor__controller-header h4 {
    margin: 0.15rem 0 0;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.92rem;
}

/* Dual Stacked Visual Track */
.pa-pillar-editor__visual-track {
    display: flex;
    height: 2.2rem;
    border-radius: var(--hcis-radius-md, 10px);
    overflow: hidden;
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: #e2e8f0;
}

.pa-pillar-editor__track-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: width 220ms ease;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
}

.pa-pillar-editor__track-segment--kpi {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    color: #ffffff;
}

.pa-pillar-editor__track-segment--komp {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    color: #ffffff;
}

.pa-pillar-editor__track-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.76rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
    padding: 0 0.5rem;
}

/* Slider Section */
.pa-pillar-editor__slider-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-pillar-editor__slider-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.74rem;
}

.pa-pillar-editor__slider-kpi-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #4338ca;
    font-weight: 700;
}

.pa-pillar-editor__slider-komp-label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #6d28d9;
    font-weight: 700;
}

.pa-pillar-editor__slider {
    width: 100%;
    height: 0.5rem;
    border-radius: 999px;
    outline: none;
    background: linear-gradient(90deg, #4f46e5, #8b5cf6);
    cursor: pointer;
}

.pa-pillar-editor__slider-ticks {
    display: flex;
    justify-content: space-between;
    color: var(--hcis-text-muted, #94a3b8);
    font-size: 0.65rem;
    font-family: var(--hcis-font-mono, monospace);
}

/* Inputs Grid */
.pa-pillar-editor__inputs-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.8rem;
}

.pa-pillar-editor__number-box {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-pillar-editor__number-box--kpi {
    border-left: 3.5px solid #4f46e5;
}

.pa-pillar-editor__number-box--komp {
    border-left: 3.5px solid #7c3aed;
}

.pa-pillar-editor__number-box label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #334155);
}

.pa-pillar-editor__number-box label em {
    color: #ef4444;
    font-style: normal;
}

.pa-pillar-editor__stepper {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-pillar-editor__stepper-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.2rem;
    height: 2.2rem;
    padding: 0 0.4rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 6px);
    background: #f8fafc;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.72rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-pillar-editor__stepper-btn:hover:not(:disabled) {
    background: #e2e8f0;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-pillar-editor__stepper-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.pa-pillar-editor__input-number {
    flex: 1;
    min-width: 0;
    height: 2.2rem;
    text-align: center;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 6px);
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.92rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
}

/* Presets List */
.pa-pillar-editor__presets {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding-top: 0.6rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.pa-pillar-editor__presets-title {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pa-pillar-editor__presets-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.pa-pillar-preset-chip {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 0.35rem 0.6rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    cursor: pointer;
    transition: all 150ms ease;
    text-align: left;
}

.pa-pillar-preset-chip strong {
    font-size: 0.74rem;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-strong, #1e293b);
}

.pa-pillar-preset-chip small {
    font-size: 0.62rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-pillar-preset-chip:hover {
    border-color: #4f46e5;
    background: #f5f3ff;
}

.pa-pillar-preset-chip.active {
    border-color: #4f46e5;
    background: #4f46e5;
}

.pa-pillar-preset-chip.active strong,
.pa-pillar-preset-chip.active small {
    color: #ffffff;
}

/* Notes & Rationale Helper */
.pa-pillar-editor__notes {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-rationale-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-rationale-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
}

.pa-rationale-chips {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-rationale-chip {
    padding: 0.15rem 0.45rem;
    border: 1px dashed rgba(99, 102, 241, 0.4);
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(238, 242, 255, 0.6);
    color: #4338ca;
    font-size: 0.66rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-rationale-chip:hover {
    background: #e0e7ff;
    border-color: #4f46e5;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Dialog Responsive Actions (50/50 on mobile) */
.pa-dialog-actions--responsive {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

@media (max-width: 767.98px) {
    .pa-dialog-actions--responsive {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }
}
</style>
