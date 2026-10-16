<template>
    <HcisDialog
        v-model="open"
        :title="`Detail ${definition?.label || 'Master PA'}`"
        :kicker="definition?.label ? `MASTER DATA · ${definition.label.toUpperCase()}` : 'MASTER DATA'"
        :icon="definition?.icon || 'database'"
        :sentiment="definition?.sentiment || 'brand'"
        variant="glass"
        :size="isMobile ? 'full' : 'lg'"
        animated
        @update:model-value="onDialogUpdate"
    >
        <div v-if="record" class="pa-master-detail-dialog">
            <!-- Header Identity Card -->
            <section class="pa-dialog-identity" :class="`pa-dialog-identity--${definition?.sentiment || 'brand'}`">
                <div class="pa-dialog-identity__icon">
                    <HcisIcon :name="definition?.icon || 'database'" size="lg" />
                </div>
                <div class="pa-dialog-identity__body">
                    <div class="pa-dialog-identity__badges">
                        <HcisBadge
                            :label="scopeBadgeLabel"
                            :variant="definition?.sentiment || 'brand'"
                            appearance="subtle"
                            size="xs"
                        />
                        <HcisStatusBadge v-if="hasStatus" :status="status" :label="statusLabel" size="xs" />
                        <HcisBadge
                            v-if="record.Urutan_Tampil !== undefined && record.Urutan_Tampil !== null"
                            :label="`#${record.Urutan_Tampil}`"
                            variant="neutral"
                            appearance="subtle"
                            size="xs"
                        />
                        <HcisBadge
                            v-if="record.Flag_Berskor && master !== 'kompetensi'"
                            :label="record.Flag_Berskor === 'Y' ? 'Scored (1–5)' : 'Qualitative'"
                            :variant="record.Flag_Berskor === 'Y' ? 'brand' : 'teal'"
                            appearance="subtle"
                            size="xs"
                        />
                    </div>
                    <h2>{{ title }}</h2>
                    <div class="pa-dialog-identity__meta">
                        <span class="pa-dialog-identity__code">
                            <HcisIcon name="hash" size="2xs" /> {{ code }}
                        </span>
                        <span v-if="subSubtitle" class="pa-dialog-identity__sub">
                            • {{ subSubtitle }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Action Bar -->
            <div class="pa-dialog-actions">
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="pencil-square"
                    label="Edit Data"
                    @click="emit('edit', record)"
                />
                <HcisButton
                    v-if="canDeactivate && record.Flag_Aktif === 'Y'"
                    variant="danger-quiet"
                    size="sm"
                    leading-icon="slash-circle"
                    label="Nonaktifkan"
                    @click="emit('deactivate', record)"
                />
            </div>

            <!-- Contextual Quick Stats Group -->
            <HcisStatGroup :cols="3" gap="sm" class="pa-dialog-stats">
                <HcisStatCard
                    label="Status Master"
                    :value="statusLabel"
                    :caption="statusCaption"
                    icon="shield-check"
                    :sentiment="statusSentiment"
                    layout="compact"
                />
                <HcisStatCard
                    :label="stat2Label"
                    :value="stat2Value"
                    :caption="stat2Caption"
                    :icon="stat2Icon"
                    :sentiment="stat2Sentiment"
                    layout="compact"
                />
                <HcisStatCard
                    :label="stat3Label"
                    :value="stat3Value"
                    :caption="stat3Caption"
                    :icon="stat3Icon"
                    :sentiment="stat3Sentiment"
                    layout="compact"
                />
            </HcisStatGroup>

            <!-- Configuration Fields Grid -->
            <section class="pa-dialog-section">
                <div class="pa-dialog-section__heading">
                    <div>
                        <span class="pa-dialog-eyebrow">DETAIL KONFIGURASI</span>
                        <h3>Informasi Parameter</h3>
                    </div>
                    <HcisIcon name="sliders" size="sm" color="brand" variant="boxed" />
                </div>

                <div class="pa-dialog-fields">
                    <div v-for="field in visibleFields" :key="field.key" class="pa-dialog-field">
                        <span class="pa-dialog-field__label">{{ field.label }}</span>
                        <div class="pa-dialog-field__value">
                            <!-- Status field -->
                            <HcisStatusBadge
                                v-if="field.key === 'Flag_Aktif' || field.key === 'Status_Policy'"
                                :status="status"
                                :label="statusLabel"
                                size="xs"
                            />
                            <!-- Flag Berskor -->
                            <HcisBadge
                                v-else-if="field.key === 'Flag_Berskor'"
                                :label="record[field.key] === 'Y' ? 'Scored (Rating 1–5)' : 'Qualitative (Esai / Catatan)'"
                                :variant="record[field.key] === 'Y' ? 'brand' : 'teal'"
                                size="xs"
                            />
                            <!-- Flag Radar -->
                            <HcisBadge
                                v-else-if="field.key === 'Flag_Radar'"
                                :label="(record[field.key] ?? 'Y') === 'Y' ? 'Menjadi sumbu radar' : 'Hanya di rincian'"
                                :variant="(record[field.key] ?? 'Y') === 'Y' ? 'purple' : 'neutral'"
                                size="xs"
                            />
                            <!-- Ambang nilai (KKM). Kosong pada kompetensi berskor adalah
                                 pekerjaan yang belum selesai, jadi dikatakan; pada butir
                                 kualitatif ia memang tidak berlaku, jadi dibedakan. -->
                            <template v-else-if="field.key === 'Nilai_Ambang'">
                                <strong v-if="record[field.key] !== null && record[field.key] !== undefined">
                                    {{ Number(record[field.key]).toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}
                                    <span class="pa-dialog-field__unit">/ 100</span>
                                </strong>
                                <HcisBadge
                                    v-else-if="record.Flag_Berskor === 'T'"
                                    label="Tidak berlaku"
                                    variant="neutral"
                                    size="xs"
                                />
                                <HcisBadge v-else label="Belum ditetapkan" variant="warning" size="xs" />
                            </template>
                            <!-- Flag Izinkan Manual -->
                            <HcisBadge
                                v-else-if="field.key === 'Flag_Izinkan_Manual'"
                                :label="record[field.key] === 'Y' ? 'Diizinkan' : 'Tidak Diizinkan'"
                                :variant="record[field.key] === 'Y' ? 'success' : 'neutral'"
                                size="xs"
                            />
                            <!-- Urutan Tampil -->
                            <span v-else-if="field.key === 'Urutan_Tampil'" class="pa-dialog-field__order">
                                #{{ record[field.key] }}
                            </span>
                            <!-- Color preview -->
                            <span v-else-if="field.key === 'Warna'" class="pa-color-preview">
                                <span class="pa-color-dot" :style="{ backgroundColor: record[field.key] }" />
                                <code>{{ record[field.key] }}</code>
                            </span>
                            <!-- Lookup resolved fields -->
                            <strong v-else-if="isLookupField(field.key)">
                                {{ resolveLookupValue(field.key, record[field.key]) }}
                            </strong>
                            <!-- Plain value -->
                            <strong v-else>{{ displayValue(record[field.key]) }}</strong>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dedicated Description / Notes Card -->
            <section v-if="hasDescription" class="pa-dialog-section">
                <div class="pa-dialog-desc-card">
                    <div class="pa-dialog-desc-card__head">
                        <HcisIcon name="card-text" size="xs" color="purple" />
                        <strong>{{ descriptionTitle }}</strong>
                    </div>
                    <p class="pa-dialog-desc-card__body">{{ descriptionContent }}</p>
                </div>
            </section>

            <!-- Callout for policy/package navigation -->
            <section v-if="master === 'aturan-penilai' || master === 'paket-soal'" class="pa-dialog-callout">
                <div>
                    <HcisIcon
                        :name="master === 'aturan-penilai' ? 'list-check' : 'collection'"
                        size="sm"
                        color="purple"
                    />
                    <div>
                        <strong>{{
                            master === 'aturan-penilai' ? 'Rincian Komposisi Aturan Penilai' : 'Daftar Butir Soal Paket'
                        }}</strong>
                        <span>{{
                            master === 'aturan-penilai'
                                ? 'Buka editor aturan untuk menyusun komposisi pembobotan multi-rater per level jabatan.'
                                : 'Buka pemetaan daftar butir pertanyaan evaluasi yang melekat pada paket cycle ini.'
                        }}</span>
                    </div>
                </div>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    :label="master === 'aturan-penilai' ? 'Buka Rincian' : 'Buka Butir Soal'"
                    trailing-icon="arrow-right"
                    @click="emit(master === 'aturan-penilai' ? 'policy' : 'package', record)"
                />
            </section>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="pa-dialog-footer">
                <span class="pa-dialog-footer__hint">Master data menjadi acuan dasar pembuatan cycle penilaian.</span>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton variant="secondary" size="sm" label="Tutup" @click="open = false" />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="pencil-square"
                        label="Edit Data"
                        @click="emit('edit', record)"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisIcon,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PaMasterDetailDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    record: { type: Object, default: null },
    definition: { type: Object, default: null },
    fields: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    scopeLabel: { type: String, default: 'master global' },
    master: { type: String, default: '' },
    canDeactivate: { type: Boolean, default: false },
});

const isMobile = ref(false);

function cekUkuran() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    cekUkuran();
    window.addEventListener('resize', cekUkuran, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('resize', cekUkuran);
});

const emit = defineEmits(['update:modelValue', 'edit', 'deactivate', 'policy', 'package']);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const title = computed(() => {
    const row = props.record || {};
    return (
        row.Nama_Competency ||
        row.Nama_Type ||
        row.Nama_Kategori ||
        row.Nama_Policy ||
        row.Nama_Range ||
        row.Nama_Kuadran ||
        row.Nama_Ujian_Sumber ||
        row.Nama_Bagian_Sumber ||
        row.Kode_Range ||
        'Data Master'
    );
});

const code = computed(() => {
    const row = props.record || {};
    return (
        row.Kode_Competency ||
        row.Kode_Type ||
        row.Kode_Kategori ||
        row.Kode_Policy ||
        row.Kode_Range ||
        row.Kode_Kuadran ||
        row.Id_Master_Ujian ||
        row.Id_Jenis_Soal ||
        'Tanpa kode'
    );
});

const status = computed(() => {
    if (props.record?.Status_Policy) return props.record.Status_Policy;
    if (props.record?.Flag_Aktif === 'Y') return 'ACTIVE';
    if (props.record?.Flag_Aktif === 'T') return 'INACTIVE';
    return 'MASTER';
});

const hasStatus = computed(() => Boolean(props.record?.Flag_Aktif || props.record?.Status_Policy));

const statusLabel = computed(() => {
    if (props.record?.Flag_Aktif === 'Y') return 'Aktif';
    if (props.record?.Flag_Aktif === 'T') return 'Nonaktif';
    return props.record?.Status_Policy || 'Master';
});

const statusCaption = computed(() => {
    if (props.record?.Flag_Aktif === 'Y') return 'Aktif & siap digunakan';
    if (props.record?.Flag_Aktif === 'T') return 'Dinonaktifkan';
    return 'Status master kebijakan';
});

const statusSentiment = computed(() => {
    if (props.record?.Flag_Aktif === 'Y' || props.record?.Status_Policy === 'ACTIVE') return 'success';
    if (props.record?.Flag_Aktif === 'T' || props.record?.Status_Policy === 'INACTIVE') return 'neutral';
    return 'brand';
});

const scopeBadgeLabel = computed(() => {
    if (props.master === 'jenis-kompetensi') return 'DIMENSI EVALUASI';
    if (props.master === 'kompetensi') return 'KAMUS KOMPETENSI';
    if (props.master === 'aturan-penilai') return 'ATURAN PENILAI';
    if (props.master === 'bobot-pilar') return 'BOBOT KPI & KOMPETENSI';
    if (props.master === 'pita-nilai') return 'SKALA NILAI & GRADE';
    return props.definition?.label?.toUpperCase() || 'MASTER DATA';
});

const resolvedTypeLabel = computed(() => {
    if (props.record?.Nama_Type) return props.record.Nama_Type;
    if (props.record?.PA_Competency_Type_Id && props.references?.jenis_kompetensi) {
        const found = props.references.jenis_kompetensi.find(
            (item) => String(item.Id_PA_Competency_Type) === String(props.record.PA_Competency_Type_Id),
        );
        if (found) return found.Nama_Type;
    }
    return 'Dimensi Global';
});

const resolvedLevelLabel = computed(() => {
    if (props.record?.Nama_Level) return props.record.Nama_Level;
    if (props.record?.ID_Level && props.references?.level) {
        const found = props.references.level.find(
            (item) => String(item.ID_Level) === String(props.record.ID_Level),
        );
        if (found) return found.Nama_Level;
    }
    return props.record?.ID_Level ? `Level ${props.record.ID_Level}` : 'Semua Level';
});

const subSubtitle = computed(() => {
    if (props.master === 'kompetensi') return resolvedTypeLabel.value;
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return resolvedLevelLabel.value;
    if (props.master === 'pita-nilai') return `${props.record?.Score_Type || 'FINAL'} · ${props.record?.Score_Usage || 'GRADE'}`;
    if (props.master === 'jenis-kompetensi') return props.record?.Urutan_Tampil ? `Urutan Ke-${props.record.Urutan_Tampil}` : '';
    return '';
});

// Dynamic Stat 2
const stat2Label = computed(() => {
    if (props.master === 'jenis-kompetensi' || props.master === 'kompetensi') return 'Urutan Tampil';
    if (props.master === 'aturan-penilai') return 'Versi Kebijakan';
    if (props.master === 'bobot-pilar') return 'Proporsi Bobot';
    if (props.master === 'pita-nilai') return 'Rentang Skor';
    return 'Urutan';
});

const stat2Value = computed(() => {
    const row = props.record || {};
    if (props.master === 'jenis-kompetensi' || props.master === 'kompetensi') return `#${row.Urutan_Tampil || 1}`;
    if (props.master === 'aturan-penilai') return `v${row.Versi || 1}`;
    if (props.master === 'bobot-pilar') return `${row.Bobot_KPI ?? 0}% : ${row.Bobot_Kompetensi ?? 0}%`;
    if (props.master === 'pita-nilai') return `${row.Nilai_Dari ?? 0} – ${row.Nilai_Sampai ?? 0}`;
    return `#${row.Urutan_Tampil || 1}`;
});

const stat2Caption = computed(() => {
    const row = props.record || {};
    if (props.master === 'jenis-kompetensi') return 'Prioritas radar & form';
    if (props.master === 'kompetensi') return 'Posisi urutan tampil';
    if (props.master === 'aturan-penilai') return 'Versi aturan aktif';
    if (props.master === 'bobot-pilar') return 'KPI vs Kompetensi';
    if (props.master === 'pita-nilai') return row.Nilai_Hasil ? `Hasil Grade: ${row.Nilai_Hasil}` : 'Batas atas eksklusif';
    return 'Posisi urutan tampil';
});

const stat2Icon = computed(() => {
    if (props.master === 'jenis-kompetensi' || props.master === 'kompetensi') return 'sort-numeric-down';
    if (props.master === 'aturan-penilai') return 'diagram-3-fill';
    if (props.master === 'bobot-pilar') return 'pie-chart-fill';
    if (props.master === 'pita-nilai') return 'bar-chart-steps';
    return 'sort-numeric-down';
});

const stat2Sentiment = computed(() => {
    if (props.master === 'jenis-kompetensi' || props.master === 'kompetensi') return 'purple';
    if (props.master === 'aturan-penilai') return 'purple';
    if (props.master === 'bobot-pilar') return 'brand';
    if (props.master === 'pita-nilai') return 'warning';
    return 'brand';
});

// Dynamic Stat 3
const stat3Label = computed(() => {
    if (props.master === 'kompetensi') return 'Dimensi Induk';
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return 'Level Jabatan';
    if (props.master === 'pita-nilai') return 'Klasifikasi';
    return 'Cakupan Master';
});

const stat3Value = computed(() => {
    if (props.master === 'kompetensi') return resolvedTypeLabel.value;
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return resolvedLevelLabel.value;
    if (props.master === 'pita-nilai') return `${props.record?.Score_Type || 'FINAL'} · ${props.record?.Score_Usage || 'GRADE'}`;
    return 'Global Matrix';
});

const stat3Caption = computed(() => {
    if (props.master === 'kompetensi') return 'Dimensi kelompok kompetensi';
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return 'Target level jabatan';
    if (props.master === 'pita-nilai') return 'Tipe & kegunaan skala';
    return 'Berlaku lintas level & cycle';
});

const stat3Icon = computed(() => {
    if (props.master === 'kompetensi') return 'diagram-2-fill';
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return 'person-badge';
    if (props.master === 'pita-nilai') return 'tags-fill';
    return 'globe2';
});

const stat3Sentiment = computed(() => {
    if (props.master === 'kompetensi') return 'purple';
    if (props.master === 'bobot-pilar' || props.master === 'aturan-penilai') return 'teal';
    if (props.master === 'pita-nilai') return 'brand';
    return 'teal';
});

// Visible Fields
const visibleFields = computed(() => {
    const hiddenKeys = ['Deskripsi', 'Catatan', 'Catatan_Versi'];
    if (props.master === 'kompetensi') {
        hiddenKeys.push('Flag_Berskor');
    }
    return props.fields.filter(
        (field) => !hiddenKeys.includes(field.key) && !/^Id_PA_/i.test(field.key),
    );
});

// Description & Notes
const hasDescription = computed(() => {
    const row = props.record || {};
    return Boolean(row.Deskripsi || row.Catatan || row.Catatan_Versi);
});

const descriptionTitle = computed(() => {
    const row = props.record || {};
    if (row.Deskripsi) return 'Deskripsi & Panduan Penilaian';
    if (row.Catatan_Versi) return 'Catatan Versi Perubahan';
    return 'Catatan Tambahan';
});

const descriptionContent = computed(() => {
    const row = props.record || {};
    return row.Deskripsi || row.Catatan_Versi || row.Catatan || '';
});

function isLookupField(key) {
    return ['PA_Competency_Type_Id', 'ID_Level', 'PA_Cycle_Id', 'PA_Cycle_Question_Package_Id', 'PA_Competency_Id'].includes(key);
}

function resolveLookupValue(key, val) {
    if (key === 'PA_Competency_Type_Id') return resolvedTypeLabel.value;
    if (key === 'ID_Level') return resolvedLevelLabel.value;
    if (key === 'PA_Cycle_Id') return props.record?.Nama_Cycle || `Cycle ID ${val}`;
    if (key === 'PA_Cycle_Question_Package_Id') return props.record?.Nama_Ujian_Sumber || `Paket ID ${val}`;
    if (key === 'PA_Competency_Id') return props.record?.Nama_Competency || `Kompetensi ID ${val}`;
    return displayValue(val);
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function onDialogUpdate(value) {
    if (!value) emit('update:modelValue', false);
}
</script>

<style scoped>
.pa-master-detail-dialog {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

/* Identity Card */
.pa-dialog-identity {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.15rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.85), rgba(255, 255, 255, 0.95));
}

.pa-dialog-identity--purple {
    border-color: rgba(168, 85, 247, 0.25);
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.9), rgba(255, 255, 255, 0.95));
}
.pa-dialog-identity--warning {
    border-color: rgba(245, 158, 11, 0.25);
    background: linear-gradient(135deg, rgba(254, 252, 232, 0.9), rgba(255, 255, 255, 0.95));
}

.pa-dialog-identity__icon {
    display: grid;
    width: 3.25rem;
    height: 3.25rem;
    flex: 0 0 auto;
    place-items: center;
    color: #4f46e5;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(99, 102, 241, 0.12);
}
.pa-dialog-identity--purple .pa-dialog-identity__icon {
    color: #9333ea;
    background: rgba(168, 85, 247, 0.12);
}
.pa-dialog-identity--warning .pa-dialog-identity__icon {
    color: #d97706;
    background: rgba(245, 158, 11, 0.12);
}

.pa-dialog-identity__body {
    min-width: 0;
    flex: 1 1 auto;
}

.pa-dialog-identity__badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-dialog-identity h2 {
    margin: 0.35rem 0 0.15rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 1.25rem;
    font-weight: 750;
    line-height: 1.3;
}

.pa-dialog-identity__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-dialog-identity__code {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.12rem 0.45rem;
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 4px;
    background: rgba(238, 242, 255, 0.7);
    color: #4338ca;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.75rem;
    font-weight: 700;
}

.pa-dialog-identity__sub {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.75rem;
    font-weight: 600;
}

/* Actions */
.pa-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Stats */
.pa-dialog-stats {
    margin: 0;
}

/* Section */
.pa-dialog-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-dialog-section__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.pa-dialog-eyebrow {
    color: #4f46e5;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.pa-dialog-section h3 {
    margin: 0.1rem 0 0;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.92rem;
    font-weight: 700;
}

/* Fields Grid */
.pa-dialog-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.pa-dialog-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
    padding: 0.7rem 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.75);
}

.pa-dialog-field__label {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
    font-weight: 700;
}

.pa-dialog-field__value {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-dialog-field__value strong {
    overflow-wrap: anywhere;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.82rem;
    font-weight: 650;
}

/* Satuan ditulis kecil dan redup di samping angkanya: "75" sendirian tidak mengatakan
   skalanya, dan pembacanya bisa saja mengiranya 1–5 seperti skala pengisian. */
.pa-dialog-field__unit {
    font-weight: 600;
    font-size: 0.72rem;
    color: #94a3b8;
}

.pa-dialog-field__order {
    display: inline-flex;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.75rem;
    font-weight: 700;
}

.pa-color-preview {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
}

.pa-color-dot {
    width: 0.9rem;
    height: 0.9rem;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
}

/* Description Card */
.pa-dialog-desc-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(168, 85, 247, 0.22);
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.75), rgba(255, 255, 255, 0.85));
}

.pa-dialog-desc-card__head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: #7e22ce;
    font-size: 0.76rem;
    font-weight: 750;
}

.pa-dialog-desc-card__body {
    margin: 0;
    color: var(--hcis-text-strong, #1e293b);
    font-size: 0.8rem;
    line-height: 1.55;
    white-space: pre-line;
}

/* Callout */
.pa-dialog-callout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(245, 243, 255, 0.75);
}

.pa-dialog-callout > div {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    min-width: 0;
}

.pa-dialog-callout > div > div {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
}

.pa-dialog-callout strong {
    color: #5b21b6;
    font-size: 0.8rem;
}

.pa-dialog-callout span {
    color: #6d28d9;
    font-size: 0.72rem;
    line-height: 1.4;
}

/* Footer */
.pa-dialog-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    width: 100%;
}

.pa-dialog-footer__hint {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

@media (max-width: 600px) {
    .pa-dialog-fields {
        grid-template-columns: 1fr;
    }
    .pa-dialog-callout,
    .pa-dialog-footer {
        align-items: stretch;
        flex-direction: column;
    }
    .pa-dialog-footer > div {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }
    .pa-dialog-footer .hcis-btn {
        width: 100%;
        justify-content: center;
    }
    .pa-dialog-footer__hint {
        text-align: center;
    }
}
</style>

