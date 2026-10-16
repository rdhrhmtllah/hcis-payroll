<template>
    <div class="pa-step-root">
        <PaStepHeader
            kicker="STEP 1 OF 5 · PILLAR WEIGHTS"
            title="Bobot Penilaian Pilar (KPI vs Kompetensi)"
            description="Tetapkan proporsi nilai antara Pilar KPI dan Pilar Kompetensi untuk setiap level jabatan. Total bobot tiap level wajib tepat 100%."
            icon="sliders2"
        >
            <template #actions>
                <HcisButton
                    v-if="dapatDisunting && baris.length"
                    label="Terapkan Preset ke Semua"
                    variant="secondary"
                    size="sm"
                    leading-icon="lightning-charge-fill"
                    @click="bukaBatchDialog"
                />
                <HcisButton
                    v-if="dapatDisunting && baris.length && adaPerubahan"
                    label="Simpan Semua Level"
                    variant="primary"
                    size="sm"
                    leading-icon="check-all"
                    :loading="menyimpanSemua"
                    @click="simpanSemua"
                />
                <!--
                 | SATU BENTUK DI SEMUA LANGKAH: "Lihat Master ...".
                 |
                 | Sebelum 6 September 2026 hanya sebagian langkah punya jalan ke
                 | masternya, dan namanya berbeda-beda. Nama yang berganti tiap langkah
                 | memaksa admin membaca ulang untuk memastikan ia tidak sedang menekan
                 | sesuatu yang MENGUBAH data — padahal semuanya cuma membuka halaman.
                 -->
                <HcisButton
                    href="/performance-appraisal/master/bobot-pilar"
                    label="Lihat Master Bobot KPI &amp; Kompetensi"
                    variant="secondary"
                    size="sm"
                    leading-icon="database"
                />

                <HcisButton
                    label="Muat Ulang"
                    variant="ghost"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    :loading="memuat"
                    @click="muat"
                />
            </template>
        </PaStepHeader>

        <div class="pa-step-body">

            <!-- Lock Warning -->
            <HcisAlert
                v-if="!dapatDisunting"
                variant="warning"
                appearance="subtle"
                :title="alasanTerkunci || 'Langkah ini sedang terkunci untuk penyuntingan.'"
                icon="lock-fill"
            />

            <!-- Error State -->
            <HcisResultState
                v-else-if="error && !memuat"
                status="danger"
                variant="glass"
                layout="compact"
                title="Bobot pilar tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton -->
            <div v-else-if="memuat" class="pa-bobot-skeleton-list">
                <div v-for="n in 4" :key="`skel-bobot-${n}`" class="pa-bobot-skeleton-item">
                    <HcisSkeleton width="200px" height="1.4rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="3rem" rounded="md" />
                </div>
            </div>

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!baris.length"
                title="Belum ada salinan bobot pilar untuk cycle ini"
                description="Cetak biru master disalin otomatis saat cycle dibuat, jadi keadaan ini berarti master bobot pilarnya belum terisi untuk level yang dihuni karyawan aktif. Lengkapi master bobot pilar, lalu muat ulang cycle ini."
                icon="sliders"
            />

            <!-- Loaded Content -->
            <div v-else class="pa-bobot-content">
                <!-- Summary Metrics Cockpit Banner -->
                <div class="pa-bobot-summary-banner">
                    <div class="pa-summary-stat-item">
                        <span class="pa-stat-lbl">TOTAL JOB LEVELS</span>
                        <strong class="pa-stat-val">{{ baris.length }} Level</strong>
                    </div>
                    <div class="pa-summary-stat-divider" />
                    <div class="pa-summary-stat-item">
                        <span class="pa-stat-lbl">WEIGHT BALANCE STATUS</span>
                        <HcisBadge
                            :label="semuaGenap ? 'Semua 100% Seimbang' : `${cacahBelumGenap} Level Belum 100%`"
                            :variant="semuaGenap ? 'success' : 'danger'"
                            appearance="subtle"
                            size="sm"
                            dot
                        />
                    </div>
                    <div class="pa-summary-stat-divider" />
                    <div class="pa-summary-stat-item">
                        <span class="pa-stat-lbl">AVERAGE PROPORTION</span>
                        <span class="pa-stat-ratio">
                            <span class="text-indigo-600 font-bold">KPI {{ rataRataKpi }}%</span>
                            <span class="text-slate-400"> : </span>
                            <span class="text-purple-600 font-bold">Komp {{ rataRataKomp }}%</span>
                        </span>
                    </div>
                </div>

                <!-- List of Level Weight Cards -->
                <div class="pa-bobot-list">
                    <div
                        v-for="row in baris"
                        :key="row.ID_Level"
                        class="pa-bobot-card"
                        :class="{
                            'pa-bobot-card--locked': !row.dapat_disunting || !dapatDisunting,
                            'pa-bobot-card--unbalanced': !genap(row.ID_Level),
                            'pa-bobot-card--changed': berubah(row),
                        }"
                    >
                        <!-- Card Top: Level Info & Badges -->
                        <div class="pa-bobot-card__head">
                            <div class="pa-bobot-card__level-info">
                                <h4>{{ row.Nama_Level || `Level ${row.ID_Level}` }}</h4>
                                <span class="pa-level-code">ID: {{ row.ID_Level }}</span>
                                <span v-if="berubah(row)" class="pa-changed-pill">Ada Perubahan Belum Disimpan</span>
                            </div>

                            <div class="pa-bobot-card__status-badges">
                                <HcisBadge
                                    v-if="genap(row.ID_Level)"
                                    label="100% Pas"
                                    variant="success"
                                    appearance="subtle"
                                    size="sm"
                                    dot
                                />
                                <HcisBadge
                                    v-else
                                    :label="`Total: ${total(row.ID_Level)}% (Wajib 100%)`"
                                    variant="danger"
                                    appearance="glass"
                                    size="sm"
                                    dot
                                />
                            </div>
                        </div>

                        <!-- Dual Proportion Visual Bar -->
                        <div class="pa-bobot-progress-wrapper" aria-hidden="true">
                            <div class="pa-bobot-progress-track">
                                <div
                                    class="pa-bobot-bar pa-bobot-bar--kpi"
                                    :style="{ width: `${Math.min(100, Math.max(0, draf[row.ID_Level]?.kpi || 0))}%` }"
                                >
                                    <span v-if="draf[row.ID_Level]?.kpi >= 16" class="pa-bar-label">
                                        KPI {{ draf[row.ID_Level]?.kpi }}%
                                    </span>
                                    <span v-else-if="draf[row.ID_Level]?.kpi >= 5" class="pa-bar-label pa-bar-label--compact">
                                        {{ draf[row.ID_Level]?.kpi }}%
                                    </span>
                                </div>
                                <div
                                    class="pa-bobot-bar pa-bobot-bar--komp"
                                    :style="{ width: `${Math.min(100, Math.max(0, draf[row.ID_Level]?.kompetensi || 0))}%` }"
                                >
                                    <span v-if="draf[row.ID_Level]?.kompetensi >= 16" class="pa-bar-label">
                                        Kompetensi {{ draf[row.ID_Level]?.kompetensi }}%
                                    </span>
                                    <span v-else-if="draf[row.ID_Level]?.kompetensi >= 5" class="pa-bar-label pa-bar-label--compact">
                                        {{ draf[row.ID_Level]?.kompetensi }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Input Controls & Preset Chips Row -->
                        <div class="pa-bobot-controls-row">
                            <div class="pa-bobot-inputs">
                                <div class="pa-bobot-field">
                                    <label class="pa-field-label">Bobot KPI (%)</label>
                                    <HcisInputNumber
                                        v-model="draf[row.ID_Level].kpi"
                                        size="sm"
                                        :min="0"
                                        :max="100"
                                        :disabled="!row.dapat_disunting || !dapatDisunting"
                                        @update:model-value="seimbangkan(row.ID_Level, 'kpi')"
                                    />
                                </div>

                                <span class="pa-bobot-operator">+</span>

                                <div class="pa-bobot-field">
                                    <label class="pa-field-label">Bobot Kompetensi (%)</label>
                                    <HcisInputNumber
                                        v-model="draf[row.ID_Level].kompetensi"
                                        size="sm"
                                        :min="0"
                                        :max="100"
                                        :disabled="!row.dapat_disunting || !dapatDisunting"
                                        @update:model-value="seimbangkan(row.ID_Level, 'kompetensi')"
                                    />
                                </div>
                            </div>

                            <!-- Preset Quick Buttons -->
                            <div v-if="row.dapat_disunting && dapatDisunting" class="pa-bobot-presets">
                                <span class="pa-preset-caption">Preset:</span>
                                <button
                                    v-for="preset in presetList"
                                    :key="`${row.ID_Level}-${preset.kpi}-${preset.komp}`"
                                    type="button"
                                    class="pa-preset-chip"
                                    :class="{ active: isPresetActive(row.ID_Level, preset) }"
                                    @click="applyPreset(row.ID_Level, preset)"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>

                            <!-- Save Action for Single Level -->
                            <div class="pa-bobot-actions">
                                <HcisButton
                                    label="Simpan Level Ini"
                                    variant="primary"
                                    size="sm"
                                    leading-icon="check-lg"
                                    :disabled="!row.dapat_disunting || !dapatDisunting || !genap(row.ID_Level) || !berubah(row)"
                                    :loading="sibuk === row.ID_Level"
                                    @click="simpan(row)"
                                />
                            </div>
                        </div>

                        <!-- Footnote Warnings for this level -->
                        <div v-if="row.hasil_terbit > 0" class="pa-bobot-card__warn pa-warn--danger">
                            <HcisIcon name="lock-fill" size="2xs" />
                            <span>{{ row.hasil_terbit }} hasil sudah terbit untuk level ini — bobot terkunci permanen.</span>
                        </div>
                        <div v-else-if="row.hasil_dihitung > 0" class="pa-bobot-card__warn pa-warn--warning">
                            <HcisIcon name="info-circle-fill" size="2xs" />
                            <span>{{ row.hasil_dihitung }} hasil sudah dihitung. Mengubah bobot akan membuat hasil perlu dihitung ulang.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Navigation -->
            <div class="pa-step-footer">
                <span v-if="pesan" class="pa-step-message" :class="{ 'is-error': pesanError }">{{ pesan }}</span>
                <div class="pa-step-nav-buttons ms-auto">
                    <HcisButton
                        label="Lanjut ke Langkah 2: Question Packages"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        @click="lanjut"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog: Terapkan Preset Serentak ke Semua Level -->
        <HcisDialog
            v-model="batchDialogOpen"
            title="Terapkan Preset ke Seluruh Level"
            kicker="DISTRIBUSI SERAGAM"
            icon="lightning-charge-fill"
            sentiment="brand"
            variant="glass"
            size="md"
        >
            <div class="pa-batch-body">
                <p class="pa-batch-instruction">
                    Pilih rasio bobot KPI lawan Kompetensi yang akan diterapkan secara seragam ke seluruh level yang masih dapat disunting:
                </p>

                <div class="pa-batch-presets-grid">
                    <div
                        v-for="preset in presetList"
                        :key="`batch-${preset.label}`"
                        class="pa-batch-preset-card"
                        :class="{ active: batchSelectedPreset === preset.label }"
                        @click="batchSelectedPreset = preset.label"
                    >
                        <div class="pa-batch-preset-head">
                            <strong>{{ preset.label }}</strong>
                            <span>{{ preset.deskripsi }}</span>
                        </div>
                        <div class="pa-batch-preset-bar">
                            <div class="bar-kpi" :style="{ width: `${preset.kpi}%` }">KPI {{ preset.kpi }}%</div>
                            <div class="bar-komp" :style="{ width: `${preset.komp}%` }">Komp {{ preset.komp }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" @click="batchDialogOpen = false" />
                <HcisButton
                    variant="primary"
                    label="Terapkan ke Seluruh Draf"
                    size="sm"
                    leading-icon="check-all"
                    @click="applyBatchToAll"
                />
            </template>
        </HcisDialog>
    </div>

    <HcisDialog
        v-model="dialogBelumTersimpan"
        title="Ada bobot yang belum disimpan"
        icon="exclamation-triangle-fill"
        sentiment="warning"
        size="md"
        variant="glass"
        animated
        :loading="menyimpanSemua"
    >
        <div class="pa-unsaved-dialog-body">
            <p class="pa-unsaved-dialog-desc">
                <strong>{{ belumTersimpan.length }} level jabatan</strong> masih memiliki perubahan bobot yang belum disimpan. Jika Anda melanjutkan tanpa menyimpan, perubahan tersebut akan hilang.
            </p>

            <div class="pa-unsaved-level-card">
                <div class="pa-unsaved-level-header">
                    <HcisIcon name="list-task" size="2xs" />
                    <span>Daftar Level Belum Disimpan:</span>
                </div>
                <div class="pa-unsaved-level-chips">
                    <HcisBadge
                        v-for="row in belumTersimpan"
                        :key="row.ID_Level"
                        :label="row.Nama_Level || `Level ${row.ID_Level}`"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                </div>
            </div>
        </div>

        <template #footer>
            <div class="pa-unsaved-dialog-actions">
                <HcisButton
                    variant="ghost"
                    label="Batal"
                    size="sm"
                    :disabled="menyimpanSemua"
                    @click="dialogBelumTersimpan = false"
                />
                <HcisButton
                    variant="secondary"
                    label="Lanjut Tanpa Menyimpan"
                    size="sm"
                    :disabled="menyimpanSemua"
                    @click="lanjutkanTanpaMenyimpan"
                />
                <HcisButton
                    variant="primary"
                    label="Simpan Semua &amp; Lanjut"
                    size="sm"
                    leading-icon="check-all"
                    :loading="menyimpanSemua"
                    :disabled="menyimpanSemua"
                    @click="simpanSemuaLaluLanjut"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisInputNumber,
    HcisResultState,
    HcisSkeleton,
} from '@/components/ui';
import PaStepHeader from './PaStepHeader.vue';
import paApi from '../../api/paApi.js';
import { pesanGagalSebagian } from '../../utils/simpanBertahap.js';

defineOptions({ name: 'PaStepBobotPilar' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },
});

const emit = defineEmits(['tersimpan', 'lanjut']);

const baris = ref([]);
const draf = reactive({});
const dialogBelumTersimpan = ref(false);
const memuat = ref(false);
const error = ref('');
const pesan = ref('');
const pesanError = ref(false);
const sibuk = ref(null);
const menyimpanSemua = ref(false);

const batchDialogOpen = ref(false);
const batchSelectedPreset = ref('80:20');

const presetList = [
    { label: '80:20', kpi: 80, komp: 20, deskripsi: 'Standar Operasional / Staff (Dominan KPI)' },
    { label: '70:30', kpi: 70, komp: 30, deskripsi: 'Standar Officer & Senior Staff' },
    { label: '60:40', kpi: 60, komp: 40, deskripsi: 'Standar Managerial & Leader' },
    { label: '50:50', kpi: 50, komp: 50, deskripsi: 'Proporsi Seimbang KPI & Kompetensi' },
];

const semuaGenap = computed(() => {
    return baris.value.every((b) => genap(b.ID_Level));
});

const cacahBelumGenap = computed(() => {
    return baris.value.filter((b) => !genap(b.ID_Level)).length;
});

const adaPerubahan = computed(() => {
    return baris.value.some((b) => berubah(b));
});

const rataRataKpi = computed(() => {
    if (!baris.value.length) return 0;
    const sum = baris.value.reduce((acc, b) => acc + (Number(draf[b.ID_Level]?.kpi) || 0), 0);
    return Math.round(sum / baris.value.length);
});

const rataRataKomp = computed(() => {
    return Math.max(0, 100 - rataRataKpi.value);
});

function total(idLevel) {
    const d = draf[idLevel];
    if (!d) return 0;
    return Math.round((Number(d.kpi) || 0) + (Number(d.kompetensi) || 0));
}

function genap(idLevel) {
    return total(idLevel) === 100;
}

function seimbangkan(idLevel, sumber) {
    const d = draf[idLevel];
    if (!d) return;

    if (sumber === 'kpi') {
        const kpiVal = Math.min(100, Math.max(0, Number(d.kpi) || 0));
        d.kompetensi = 100 - kpiVal;
    } else {
        const kompVal = Math.min(100, Math.max(0, Number(d.kompetensi) || 0));
        d.kpi = 100 - kompVal;
    }
}

function isPresetActive(idLevel, preset) {
    const d = draf[idLevel];
    if (!d) return false;
    return Number(d.kpi) === preset.kpi && Number(d.kompetensi) === preset.komp;
}

function applyPreset(idLevel, preset) {
    if (!draf[idLevel]) return;
    draf[idLevel].kpi = preset.kpi;
    draf[idLevel].kompetensi = preset.komp;
}

function bukaBatchDialog() {
    batchSelectedPreset.value = '80:20';
    batchDialogOpen.value = true;
}

function applyBatchToAll() {
    const p = presetList.find((x) => x.label === batchSelectedPreset.value);
    if (!p) return;

    for (const b of baris.value) {
        if (b.dapat_disunting && draf[b.ID_Level]) {
            draf[b.ID_Level].kpi = p.kpi;
            draf[b.ID_Level].kompetensi = p.komp;
        }
    }
    batchDialogOpen.value = false;
    pesan.value = `Preset ${p.label} berhasil diterapkan ke semua draf level. Klik "Simpan Semua Level" untuk menyimpan permanen.`;
    pesanError.value = false;
}

/**
 * Baris yang suntingannya BELUM tersimpan.
 *
 * Dipakai penjaga perpindahan langkah. Tanpa itu, "Lanjut" berpindah begitu saja dan
 * seluruh suntingan yang belum ditekan Simpan hilang tanpa satu pun pesan — dan admin baru
 * menyadarinya ketika kembali dan menemukan angkanya seperti semula.
 */
const belumTersimpan = computed(() => baris.value.filter((row) => berubah(row)));

/**
 * Berpindah ke langkah berikutnya, dengan penjaga.
 *
 * Suntingan yang belum tersimpan MENAHAN perpindahan, bukan disimpan diam-diam. Menyimpan
 * otomatis terasa membantu sampai suatu kali admin sedang mencoba-coba angka dan tidak
 * bermaksud menyimpannya — dan tidak ada jalan mengurungkannya.
 */
function lanjut() {
    if (belumTersimpan.value.length === 0) {
        emit('lanjut');

        return;
    }

    dialogBelumTersimpan.value = true;
}

async function simpanSemuaLaluLanjut() {
    await simpanSemua();

    if (belumTersimpan.value.length === 0) {
        dialogBelumTersimpan.value = false;
        emit('lanjut');
    }
}

function lanjutkanTanpaMenyimpan() {
    dialogBelumTersimpan.value = false;
    emit('lanjut');
}

function berubah(row) {
    const d = draf[row.ID_Level];
    if (!d) return false;
    return (
        Number(d.kpi) !== Math.round(Number(row.Bobot_KPI)) ||
        Number(d.kompetensi) !== Math.round(Number(row.Bobot_Kompetensi))
    );
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const response = await paApi.bobotPilarSiklus(props.cycleId);
        const data = response?.result ?? response?.data ?? response;
        baris.value = data?.bobot ?? [];

        for (const b of baris.value) {
            draf[b.ID_Level] = {
                kpi: Math.round(Number(b.Bobot_KPI) || 0),
                kompetensi: Math.round(Number(b.Bobot_Kompetensi) || 0),
            };
        }
    } catch (e) {
        error.value = e?.message || 'Gagal memuat bobot pilar cycle.';
    } finally {
        memuat.value = false;
    }
}

async function simpan(row) {
    sibuk.value = row.ID_Level;
    pesan.value = '';
    pesanError.value = false;

    const d = draf[row.ID_Level];
    try {
        await paApi.simpanBobotPilar(props.cycleId, row.ID_Level, {
            bobot_kpi: Number(d.kpi),
            bobot_kompetensi: Number(d.kompetensi),
        });

        row.Bobot_KPI = Number(d.kpi);
        row.Bobot_Kompetensi = Number(d.kompetensi);
        pesan.value = `Bobot untuk ${row.Nama_Level || 'Level ' + row.ID_Level} berhasil disimpan.`;
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menyimpan bobot.';
        pesanError.value = true;
    } finally {
        sibuk.value = null;
    }
}

async function simpanSemua() {
    menyimpanSemua.value = true;
    pesan.value = '';
    pesanError.value = false;

    const target = baris.value.filter((b) => b.dapat_disunting && berubah(b) && genap(b.ID_Level));
    if (!target.length) {
        pesan.value = 'Tidak ada perubahan bobot yang perlu disimpan.';
        menyimpanSemua.value = false;
        return;
    }

    let tersimpan = 0;
    let sedang = null;
    try {
        for (const row of target) {
            sedang = row;
            const d = draf[row.ID_Level];
            await paApi.simpanBobotPilar(props.cycleId, row.ID_Level, {
                bobot_kpi: Number(d.kpi),
                bobot_kompetensi: Number(d.kompetensi),
            });
            row.Bobot_KPI = Number(d.kpi);
            row.Bobot_Kompetensi = Number(d.kompetensi);
            tersimpan++;
        }

        pesan.value = `Seluruh ${target.length} level berhasil disimpan dengan rasio baru.`;
        emit('tersimpan');
    } catch (e) {
        pesan.value = pesanGagalSebagian({ tersimpan, total: target.length, gagalDi: sedang?.Nama_Level || sedang?.nama_level || '', galat: e });
        pesanError.value = true;
        if (tersimpan > 0) emit('tersimpan');
    } finally {
        menyimpanSemua.value = false;
    }
}

onMounted(muat);
</script>

<style scoped>
/*
 | Badan langkah tetap berwadah.
 |
 | Sebelum kepala langkah dipisah jadi `PaStepHeader`, seluruh isi langkah ini duduk di
 | dalam satu `HcisCard`. Memindahkan kepalanya keluar ikut membawa wadahnya pergi, dan
 | isinya jatuh langsung ke latar halaman — kelihatan seperti kartu yang hilang, bukan
 | seperti keputusan. Wadahnya dikembalikan di sini, disamakan dengan kartu kepalanya.
 */
.pa-step-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.9rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    box-sizing: border-box;
    min-width: 0;
}

.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-bobot-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-bobot-skeleton-item {
    padding: 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: rgba(248, 250, 252, 0.7);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-bobot-content {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

/* Summary Banner */
.pa-bobot-summary-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 0.85rem 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #f8fafc;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex-wrap: wrap;
}

.pa-summary-stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-stat-lbl {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-stat-val {
    font-size: 0.95rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-stat-ratio {
    font-size: 0.88rem;
    font-weight: 700;
}

.pa-summary-stat-divider {
    width: 1px;
    height: 2rem;
    background: #e2e8f0;
}

.pa-bobot-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.pa-bobot-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.15rem 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 200ms ease;
}

.pa-bobot-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-bobot-card--changed {
    border-color: rgba(99, 102, 241, 0.6);
    background: #fafbff;
}

.pa-bobot-card--unbalanced {
    border-color: #fca5a5;
    background: #fffafa;
}

.pa-bobot-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-bobot-card__level-info {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-bobot-card__level-info h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-code {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    background: #f1f5f9;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.pa-changed-pill {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
}

.pa-bobot-progress-wrapper {
    width: 100%;
}

.pa-bobot-progress-track {
    display: flex;
    height: 1.5rem;
    border-radius: 8px;
    overflow: hidden;
    background: #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06);
}

.pa-bobot-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-bobot-bar--kpi {
    background: linear-gradient(90deg, #4f46e5, #6366f1);
}

.pa-bobot-bar--komp {
    background: linear-gradient(90deg, #9333ea, #a855f7);
}

.pa-bar-label {
    font-size: 0.72rem;
    font-weight: 750;
    color: #ffffff;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.pa-bar-label--compact {
    font-size: 0.66rem;
    font-weight: 800;
}

.pa-bobot-controls-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 0.25rem;
}

.pa-bobot-inputs {
    display: flex;
    align-items: flex-end;
    gap: 0.6rem;
}

.pa-bobot-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    width: 8.5rem;
}

.pa-field-label {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bobot-operator {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #94a3b8);
    margin-bottom: 0.4rem;
}

.pa-bobot-presets {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-preset-caption {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.pa-preset-chip {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-preset-chip:hover {
    border-color: #6366f1;
    color: #4f46e5;
    background: #eef2ff;
}

.pa-preset-chip.active {
    border-color: #4f46e5;
    background: #4f46e5;
    color: #ffffff;
}

.pa-bobot-actions {
    margin-left: auto;
}

.pa-bobot-card__warn {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    padding: 0.4rem 0.65rem;
    border-radius: 6px;
}

.pa-warn--warning {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}

.pa-warn--danger {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.pa-step-footer {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.pa-step-nav-buttons {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-step-message {
    font-size: 0.8rem;
    color: var(--hcis-success, #059669);
    font-weight: 550;
}

.pa-step-message.is-error {
    color: var(--hcis-danger, #dc2626);
}

/* Modal */
.pa-batch-body {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.pa-batch-instruction {
    margin: 0;
    font-size: 0.82rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-batch-presets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.75rem;
}

.pa-batch-preset-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    cursor: pointer;
    transition: all 160ms ease;
}

.pa-batch-preset-card:hover {
    border-color: #818cf8;
    background: #f8faff;
}

.pa-batch-preset-card.active {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 1px #4f46e5;
}

.pa-batch-preset-head {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-batch-preset-head strong {
    font-size: 0.88rem;
    color: #1e1b4b;
}

.pa-batch-preset-head span {
    font-size: 0.7rem;
    color: #64748b;
}

.pa-batch-preset-bar {
    display: flex;
    height: 1.25rem;
    border-radius: 6px;
    overflow: hidden;
    font-size: 0.65rem;
    font-weight: 700;
    color: #ffffff;
    text-align: center;
    line-height: 1.25rem;
}

.pa-batch-preset-bar .bar-kpi {
    background: #4f46e5;
}

.pa-batch-preset-bar .bar-komp {
    background: #9333ea;
}
.pa-unsaved-dialog-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-unsaved-dialog-desc {
    margin: 0;
    font-size: 0.86rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #334155);
}

.pa-unsaved-level-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(254, 243, 199, 0.45);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.pa-unsaved-level-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #92400e;
}

.pa-unsaved-level-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.pa-unsaved-dialog-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
    width: 100%;
}

@media (max-width: 640px) {
    .pa-unsaved-dialog-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-unsaved-dialog-actions :deep(button) {
        width: 100%;
        justify-content: center;
    }
}
</style>
