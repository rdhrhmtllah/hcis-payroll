<template>
    <div class="pa-step-root">
        <HcisCard
            title="Pemetaan Paket Soal per Level"
            kicker="STEP 2 OF 5 · QUESTION PACKAGES &amp; LEVEL"
            description="Petakan paket soal penilaian kinerja dari HC Learn / Bank Soal untuk setiap level jabatan yang dinilai. Level tanpa paket soal akan membuka formulir penilaian kosong."
            icon="ui-checks-grid"
            sentiment="brand"
            variant="glass"
        >
            <template #actions>
                <div class="pa-step-header-actions">
                    <HcisButton
                        v-if="dapatDisunting && levelKurang.length"
                        label="Buat Paket Otomatis untuk Semua Level"
                        variant="secondary"
                        size="sm"
                        leading-icon="magic"
                        :loading="membuatOtomatis"
                        @click="buatPaketOtomatisSemua"
                    />
                    <HcisButton
                        v-if="dapatDisunting"
                        label="Tambah Paket Soal"
                        variant="primary"
                        size="sm"
                        leading-icon="plus-lg"
                        @click="bukaTambah"
                    />
                    <HcisButton
                        label="Muat Ulang"
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        @click="muat"
                    />
                </div>
            </template>

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
                title="Paket soal tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton -->
            <div v-else-if="memuat" class="pa-paket-skeleton-list">
                <div v-for="n in 3" :key="`skel-paket-${n}`" class="pa-paket-skeleton-item">
                    <HcisSkeleton width="220px" height="1.4rem" rounded="sm" />
                    <HcisSkeleton width="100%" height="3.5rem" rounded="md" />
                </div>
            </div>

            <!-- Loaded Content: Matrix & Cards & Table -->
            <div v-else class="pa-paket-content">
                <!-- Summary Cockpit Banner -->
                <div class="pa-coverage-card">
                    <div class="pa-coverage-card__head">
                        <div>
                            <span class="pa-coverage-eyebrow">CAKUPAN LEVEL JABATAN DINILAI</span>
                            <h4>Status Kelengkapan Pemetaan Paket Soal</h4>
                        </div>
                        <div class="pa-coverage-stats">
                            <HcisBadge
                                :label="`${baris.length} Paket Terpasang`"
                                variant="brand"
                                appearance="glass"
                                size="sm"
                            />
                            <HcisBadge
                                v-if="levelKurang.length"
                                :label="`${levelKurang.length} Level Belum Memiliki Paket`"
                                variant="warning"
                                appearance="subtle"
                                size="sm"
                                dot
                            />
                            <HcisBadge
                                v-else
                                label="Seluruh Level 100% Terpetakan"
                                variant="success"
                                appearance="subtle"
                                size="sm"
                                dot
                            />
                        </div>
                    </div>

                    <!-- Missing Levels Quick Action Banner -->
                    <div v-if="levelKurang.length" class="pa-missing-levels-alert">
                        <HcisIcon name="exclamation-circle-fill" size="xs" color="warning" />
                        <div class="pa-missing-levels-list">
                            <span>Level yang belum memiliki paket soal (klik chip untuk buat cepat):</span>
                            <div class="pa-missing-chips">
                                <button
                                    v-for="l in levelKurang"
                                    :key="`missing-${l.id_level}`"
                                    type="button"
                                    class="pa-missing-chip"
                                    :title="`Klik untuk menambahkan paket soal pada ${l.nama_level}`"
                                    @click="bukaTambahUntukLevel(l.id_level)"
                                >
                                    <span>{{ l.nama_level }}</span>
                                    <span v-if="l.jumlah_karyawan" class="pa-chip-pop">({{ l.jumlah_karyawan }} orang)</span>
                                    <HcisIcon name="plus-lg" size="2xs" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Evaluated Level Cards Grid -->
                <div class="pa-evaluated-levels-grid">
                    <div
                        v-for="lv in levelDinilai"
                        :key="`lv-card-${lv.id_level}`"
                        class="pa-level-pkg-card"
                        :class="{ 'is-mapped': isLevelMapped(lv.id_level) }"
                    >
                        <div class="pa-level-pkg-head">
                            <div class="pa-level-pkg-title">
                                <HcisIcon
                                    :name="isLevelMapped(lv.id_level) ? 'check-circle-fill' : 'exclamation-circle-fill'"
                                    size="xs"
                                    :color="isLevelMapped(lv.id_level) ? 'success' : 'warning'"
                                />
                                <strong>{{ lv.nama_level }}</strong>
                                <span class="pa-level-tag">ID: {{ lv.id_level }}</span>
                            </div>

                            <span v-if="lv.jumlah_karyawan" class="pa-level-karyawan-count">
                                <HcisIcon name="people-fill" size="2xs" /> {{ lv.jumlah_karyawan }} Karyawan
                            </span>
                        </div>

                        <!-- Mapped Package List -->
                        <div v-if="getPackagesForLevel(lv.id_level).length" class="pa-level-pkg-details">
                            <div
                                v-for="pkg in getPackagesForLevel(lv.id_level)"
                                :key="pkg.Id_PA_Cycle_Question_Package"
                                class="pa-pkg-badge-item"
                            >
                                <div class="pa-pkg-meta">
                                    <div class="pa-pkg-code-row">
                                        <span class="pa-pkg-code">{{ pkg.Id_Master_Ujian }}</span>
                                        <HcisBadge
                                            :label="pkg.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif'"
                                            :variant="pkg.Flag_Aktif === 'Y' ? 'success' : 'neutral'"
                                            size="2xs"
                                        />
                                    </div>
                                    <span class="pa-pkg-name">{{ pkg.Nama_Ujian_Sumber }}</span>
                                    <small v-if="pkg.Catatan" class="pa-pkg-notes">{{ pkg.Catatan }}</small>
                                </div>

                                <div class="pa-pkg-actions">
                                    <HcisButton
                                        v-if="dapatDisunting && pkg.Flag_Aktif === 'Y'"
                                        label="Nonaktifkan"
                                        variant="ghost"
                                        size="2xs"
                                        leading-icon="slash-circle"
                                        :loading="sibuk === pkg.Id_PA_Cycle_Question_Package"
                                        @click="nonaktifkan(pkg)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Not Mapped Notice & Quick Add -->
                        <div v-else class="pa-level-pkg-empty">
                            <div class="pa-empty-hint">
                                <HcisIcon name="journal-x" size="sm" color="warning" />
                                <div>
                                    <strong class="text-amber-900 block text-xs">Belum Ada Paket Soal</strong>
                                    <span class="text-amber-700 text-xs">Formulir penilaian level ini masih kosong.</span>
                                </div>
                            </div>
                            <HcisButton
                                v-if="dapatDisunting"
                                label="+ Pasang Paket Soal"
                                variant="secondary"
                                size="xs"
                                leading-icon="plus-lg"
                                @click="bukaTambahUntukLevel(lv.id_level)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Comprehensive Table of All Packages -->
                <div v-if="baris.length" class="pa-paket-table-section">
                    <div class="pa-table-head-row">
                        <h4 class="pa-table-heading">Daftar Seluruh Paket Soal Terpasang ({{ baris.length }})</h4>
                    </div>

                    <div class="pa-paket-table-wrap">
                        <HcisTable
                            :columns="kolom"
                            :rows="baris"
                            row-key="Id_PA_Cycle_Question_Package"
                            responsive="scroll"
                            hoverable
                            variant="glass"
                            sentiment="brand"
                        >
                            <template #cell-level="{ row }">
                                <div class="pa-table-level-cell">
                                    <strong>{{ getLevelName(row.ID_Level) }}</strong>
                                    <small>ID Level: {{ row.ID_Level }}</small>
                                </div>
                            </template>

                            <template #cell-kode_paket="{ row }">
                                <span class="pa-mono-code font-bold">{{ row.Id_Master_Ujian }}</span>
                            </template>

                            <template #cell-nama_paket="{ row }">
                                <div class="pa-table-package-name">
                                    <strong>{{ row.Nama_Ujian_Sumber || '—' }}</strong>
                                    <small v-if="row.Catatan">{{ row.Catatan }}</small>
                                </div>
                            </template>

                            <template #cell-sumber="{ row }">
                                <HcisBadge
                                    :label="row.Sumber_Kontrak || 'HC Learn'"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </template>

                            <template #cell-status="{ row }">
                                <HcisBadge
                                    :label="row.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif'"
                                    :variant="row.Flag_Aktif === 'Y' ? 'success' : 'neutral'"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </template>

                            <template #cell-aksi="{ row }">
                                <div class="pa-row-actions">
                                    <HcisButton
                                        v-if="row.Flag_Aktif === 'Y'"
                                        label="Nonaktifkan"
                                        variant="ghost"
                                        size="xs"
                                        leading-icon="slash-circle"
                                        :disabled="!dapatDisunting"
                                        :loading="sibuk === row.Id_PA_Cycle_Question_Package"
                                        @click="nonaktifkan(row)"
                                    />
                                </div>
                            </template>
                        </HcisTable>
                    </div>
                </div>
            </div>

            <!-- Footer Navigation -->
            <template #footer>
                <div class="pa-step-footer">
                    <span v-if="pesan" class="pa-step-message" :class="{ 'is-error': pesanError }">{{ pesan }}</span>
                    <div class="pa-step-nav-buttons ms-auto">
                        <HcisButton
                            label="Kembali ke Langkah 1"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-left"
                            @click="$emit('kembali')"
                        />
                        <HcisButton
                            label="Lanjut ke Langkah 3: Aturan Penilai"
                            variant="primary"
                            size="sm"
                            trailing-icon="arrow-right"
                            @click="$emit('lanjut')"
                        />
                    </div>
                </div>
            </template>
        </HcisCard>

        <!-- Dialog: Tambah Paket Soal -->
        <HcisDialog
            v-model="dialogTambah"
            title="Tambah Paket Soal Cycle"
            kicker="PEMETAAN BANK SOAL"
            icon="plus-circle-fill"
            sentiment="brand"
            variant="glass"
            size="md"
        >
            <div class="pa-paket-form">
                <div class="pa-form-field">
                    <label class="pa-field-label">Level Jabatan <span class="text-danger">*</span></label>
                    <HcisSelect
                        v-model="form.id_level"
                        placeholder="Pilih level jabatan"
                        size="sm"
                        @update:model-value="onLevelSelectChange"
                    >
                        <HcisSelectOption
                            v-for="l in levelPilihan"
                            :key="l.id_level"
                            :value="l.id_level"
                            :label="`${l.nama_level} (ID: ${l.id_level}) · ${l.jumlah_karyawan || 0} Karyawan`"
                        />
                    </HcisSelect>
                </div>

                <!-- Visual Template Selector -->
                <div class="pa-form-field">
                    <label class="pa-field-label">Pilih Template Bank Soal Cepat</label>
                    <div class="pa-template-grid">
                        <div
                            class="pa-tpl-card"
                            :class="{ active: templateAktif === 'standar' }"
                            @click="isiTemplate('standar')"
                        >
                            <strong>Standar KPI HC Learn</strong>
                            <span>Paket evaluasi kinerja reguler untuk seluruh jenjang.</span>
                        </div>
                        <div
                            class="pa-tpl-card"
                            :class="{ active: templateAktif === 'managerial' }"
                            @click="isiTemplate('managerial')"
                        >
                            <strong>Managerial &amp; Leader</strong>
                            <span>Paket kepemimpinan, strategi, dan pembinaan tim.</span>
                        </div>
                        <div
                            class="pa-tpl-card"
                            :class="{ active: templateAktif === 'operasional' }"
                            @click="isiTemplate('operasional')"
                        >
                            <strong>Kinerja Operasional</strong>
                            <span>Paket evaluasi lapangan, target produksi &amp; SLA.</span>
                        </div>
                        <div
                            class="pa-tpl-card"
                            :class="{ active: templateAktif === 'spesialis' }"
                            @click="isiTemplate('spesialis')"
                        >
                            <strong>Fungsional Spesialis</strong>
                            <span>Paket keahlian teknis &amp; kepakaran profesional.</span>
                        </div>
                    </div>
                </div>

                <div class="pa-form-field">
                    <label class="pa-field-label">Kode Paket Sumber (HC Learn) <span class="text-danger">*</span></label>
                    <HcisField
                        v-model="form.id_master_ujian"
                        size="sm"
                        placeholder="Contoh: PKT-MGR-2026"
                    />
                    <small class="pa-field-hint">Kode acuan paket soal dari modul HC Learn atau Bank Soal.</small>
                </div>

                <div class="pa-form-field">
                    <label class="pa-field-label">Nama Paket Soal <span class="text-danger">*</span></label>
                    <HcisField
                        v-model="form.nama_ujian_sumber"
                        size="sm"
                        placeholder="Contoh: Soal Penilaian Kinerja Managerial 2026"
                    />
                </div>

                <div class="pa-form-field">
                    <label class="pa-field-label">Catatan Tambahan</label>
                    <HcisTextarea
                        v-model="form.catatan"
                        size="sm"
                        :rows="2"
                        placeholder="Catatan penyesuaian atau konteks paket soal..."
                    />
                </div>
            </div>

            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogTambah = false" />
                <HcisButton
                    variant="primary"
                    label="Simpan Paket Soal"
                    size="sm"
                    leading-icon="check-lg"
                    :disabled="!form.id_level || !form.id_master_ujian.trim() || !form.nama_ujian_sumber.trim()"
                    :loading="menyimpan"
                    @click="simpan"
                />
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisTable,
    HcisTextarea,
} from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaStepPaketSoal' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },
});

const emit = defineEmits(['tersimpan', 'kembali', 'lanjut']);

const kolom = [
    { key: 'level', label: 'Level Jabatan', minWidth: '170px' },
    { key: 'kode_paket', label: 'Kode Paket', minWidth: '150px' },
    { key: 'nama_paket', label: 'Nama Paket Soal', minWidth: '220px' },
    { key: 'sumber', label: 'Sumber', width: '120px', align: 'center' },
    { key: 'status', label: 'Status', width: '100px', align: 'center' },
    { key: 'aksi', label: 'Aksi', width: '110px', align: 'right' },
];

const baris = ref([]);
const levelDinilai = ref([]);
const memuat = ref(false);
const error = ref('');
const pesan = ref('');
const pesanError = ref(false);
const sibuk = ref(null);
const membuatOtomatis = ref(false);
const templateAktif = ref('');

const dialogTambah = ref(false);
const menyimpan = ref(false);
const form = reactive({
    id_level: null,
    id_master_ujian: '',
    nama_ujian_sumber: '',
    catatan: '',
});

const levelKurang = computed(() => {
    const berpaket = new Set(baris.value.filter((b) => b.Flag_Aktif === 'Y').map((b) => Number(b.ID_Level)));
    return levelDinilai.value.filter((l) => !berpaket.has(Number(l.id_level)));
});

const levelPilihan = computed(() => levelDinilai.value);

function isLevelMapped(idLevel) {
    return baris.value.some((b) => Number(b.ID_Level) === Number(idLevel) && b.Flag_Aktif === 'Y');
}

function getPackagesForLevel(idLevel) {
    return baris.value.filter((b) => Number(b.ID_Level) === Number(idLevel));
}

function getLevelName(idLevel) {
    const found = levelDinilai.value.find((l) => Number(l.id_level) === Number(idLevel));
    return found ? found.nama_level : `Level ${idLevel}`;
}

function onLevelSelectChange() {
    if (templateAktif.value) {
        isiTemplate(templateAktif.value);
    }
}

function isiTemplate(jenis) {
    templateAktif.value = jenis;
    const l = levelDinilai.value.find((x) => Number(x.id_level) === Number(form.id_level));
    const lvlName = l ? l.nama_level : 'LEVEL';
    const codeSlug = String(lvlName).toUpperCase().replace(/\s+/g, '-');

    if (jenis === 'standar') {
        form.id_master_ujian = `PKT-${codeSlug}-STD`;
        form.nama_ujian_sumber = `Paket Soal Penilaian Kinerja ${lvlName}`;
    } else if (jenis === 'managerial') {
        form.id_master_ujian = `PKT-${codeSlug}-MGR`;
        form.nama_ujian_sumber = `Paket Soal Kepemimpinan & Kinerja ${lvlName}`;
    } else if (jenis === 'operasional') {
        form.id_master_ujian = `PKT-${codeSlug}-OPS`;
        form.nama_ujian_sumber = `Paket Soal Kinerja Operasional ${lvlName}`;
    } else if (jenis === 'spesialis') {
        form.id_master_ujian = `PKT-${codeSlug}-SPES`;
        form.nama_ujian_sumber = `Paket Soal Evaluasi Fungsional ${lvlName}`;
    }
}

function bukaTambah() {
    form.id_level = levelKurang.value[0]?.id_level ?? levelPilihan.value[0]?.id_level ?? null;
    templateAktif.value = 'standar';
    isiTemplate('standar');
    form.catatan = '';
    dialogTambah.value = true;
}

function bukaTambahUntukLevel(idLevel) {
    form.id_level = idLevel;
    const l = levelDinilai.value.find((x) => Number(x.id_level) === Number(idLevel));
    const slug = l ? String(l.nama_level || '').toUpperCase().replace(/\s+/g, '-') : `LV${idLevel}`;
    templateAktif.value = 'standar';
    form.id_master_ujian = `PKT-${slug}-2026`;
    form.nama_ujian_sumber = l ? `Paket Soal Penilaian Kinerja ${l.nama_level}` : `Paket Soal Level ${idLevel}`;
    form.catatan = 'Dibuat otomatis untuk penyiapan cycle.';
    dialogTambah.value = true;
}

async function buatPaketOtomatisSemua() {
    if (!levelKurang.value.length) return;
    membuatOtomatis.value = true;
    pesan.value = '';
    pesanError.value = false;

    try {
        for (const lv of levelKurang.value) {
            const slug = String(lv.nama_level || '').toUpperCase().replace(/\s+/g, '-');
            await paApi.masterSimpan('paket-soal', {
                pa_cycle_id: Number(props.cycleId),
                id_level: Number(lv.id_level),
                id_master_ujian: `PKT-${slug}-2026`,
                nama_ujian_sumber: `Paket Soal Penilaian Kinerja ${lv.nama_level}`,
                catatan: 'Paket standar otomatisasi cycle.',
            });
        }

        pesan.value = `Berhasil membuat paket soal otomatis untuk ${levelKurang.value.length} level jabatan.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal membuat paket soal otomatis.';
        pesanError.value = true;
    } finally {
        membuatOtomatis.value = false;
    }
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const [paketRes, levelSiklusRes, aturanRes, bobotRes] = await Promise.allSettled([
            paApi.masterDaftar('paket-soal', { cycle_id: props.cycleId, per_halaman: 200 }),
            paApi.levelSiklus(props.cycleId),
            paApi.daftarAturanPenilai(props.cycleId),
            paApi.bobotPilarSiklus(props.cycleId),
        ]);

        if (paketRes.status === 'fulfilled') {
            const pData = paketRes.value?.result ?? paketRes.value?.data ?? paketRes.value;
            baris.value = pData?.baris ?? [];
        }

        let lvList = [];
        if (levelSiklusRes.status === 'fulfilled') {
            const lData = levelSiklusRes.value?.result ?? levelSiklusRes.value?.data ?? levelSiklusRes.value;
            lvList = (lData?.level ?? []).map((l) => ({
                id_level: Number(l.id_level),
                nama_level: l.nama_level || `Level ${l.id_level}`,
                jumlah_karyawan: l.jumlah_karyawan || 0,
            }));
        }

        if (!lvList.length && aturanRes.status === 'fulfilled') {
            const aData = aturanRes.value?.result ?? aturanRes.value?.data ?? aturanRes.value;
            lvList = (aData?.level ?? []).map((l) => ({
                id_level: Number(l.id_level),
                nama_level: l.nama_level || `Level ${l.id_level}`,
            }));
        }

        if (!lvList.length && bobotRes.status === 'fulfilled') {
            const bData = bobotRes.value?.result ?? bobotRes.value?.data ?? bobotRes.value;
            lvList = (bData?.bobot ?? []).map((b) => ({
                id_level: Number(b.ID_Level),
                nama_level: b.Nama_Level || `Level ${b.ID_Level}`,
            }));
        }

        levelDinilai.value = lvList;
    } catch (e) {
        error.value = e?.message || 'Gagal memuat paket soal.';
    } finally {
        memuat.value = false;
    }
}

async function simpan() {
    menyimpan.value = true;
    pesan.value = '';
    pesanError.value = false;

    try {
        await paApi.masterSimpan('paket-soal', {
            pa_cycle_id: Number(props.cycleId),
            id_level: Number(form.id_level),
            id_master_ujian: form.id_master_ujian.trim().toUpperCase(),
            nama_ujian_sumber: form.nama_ujian_sumber.trim(),
            catatan: form.catatan.trim() || null,
        });

        pesan.value = 'Paket soal berhasil ditambahkan untuk level ini.';
        dialogTambah.value = false;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menambahkan paket soal.';
        pesanError.value = true;
    } finally {
        menyimpan.value = false;
    }
}

async function nonaktifkan(row) {
    sibuk.value = row.Id_PA_Cycle_Question_Package;
    pesan.value = '';
    pesanError.value = false;

    try {
        await paApi.masterNonaktif('paket-soal', row.Id_PA_Cycle_Question_Package);
        pesan.value = 'Paket soal berhasil dinonaktifkan.';
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menonaktifkan paket soal.';
        pesanError.value = true;
    } finally {
        sibuk.value = null;
    }
}

onMounted(muat);
</script>

<style scoped>
.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-paket-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-paket-skeleton-item {
    padding: 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.7);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-paket-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-coverage-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #f8fafc;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-coverage-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-coverage-eyebrow {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-coverage-card__head h4 {
    margin: 0.15rem 0 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-coverage-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-missing-levels-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-sm, 8px);
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.pa-missing-levels-list {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    font-weight: 550;
}

.pa-missing-chips {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-missing-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    background: #ffffff;
    border: 1px solid #f59e0b;
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-missing-chip:hover {
    background: #fef3c7;
    color: #78350f;
    border-color: #d97706;
}

.pa-chip-pop {
    font-weight: normal;
    color: #92400e;
    font-size: 0.68rem;
}

.pa-evaluated-levels-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.pa-level-pkg-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 180ms ease;
}

.pa-level-pkg-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-level-pkg-card.is-mapped {
    border-color: rgba(16, 185, 129, 0.4);
    background: #fcfdfd;
}

.pa-level-pkg-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-level-pkg-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-level-pkg-title strong {
    font-size: 0.92rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-tag {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.pa-level-karyawan-count {
    font-size: 0.74rem;
    color: #4338ca;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #eef2ff;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
}

.pa-level-pkg-details {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-pkg-badge-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pa-pkg-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-pkg-code-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-pkg-code {
    font-size: 0.75rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #4f46e5;
}

.pa-pkg-name {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-pkg-notes {
    font-size: 0.68rem;
    color: #64748b;
    font-style: italic;
}

.pa-pkg-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.pa-level-pkg-empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: #fffbeb;
    border-radius: 8px;
    border: 1px dashed #fcd34d;
}

.pa-empty-hint {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-table-head-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0.5rem 0 0.35rem;
}

.pa-table-heading {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-paket-table-wrap {
    border-radius: var(--hcis-radius-md, 10px);
    overflow: hidden;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-table-level-cell {
    display: flex;
    flex-direction: column;
}

.pa-table-level-cell strong {
    font-size: 0.86rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-level-cell small {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-mono-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.8rem;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pa-table-package-name {
    display: flex;
    flex-direction: column;
}

.pa-table-package-name strong {
    font-size: 0.84rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-package-name small {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
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

/* Modal Form */
.pa-paket-form {
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
}

.pa-form-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-field-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-field-hint {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-template-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.6rem;
}

.pa-tpl-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    cursor: pointer;
    transition: all 160ms ease;
}

.pa-tpl-card:hover {
    border-color: #818cf8;
    background: #f8faff;
}

.pa-tpl-card.active {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 1px #4f46e5;
}

.pa-tpl-card strong {
    font-size: 0.8rem;
    color: #1e1b4b;
}

.pa-tpl-card span {
    font-size: 0.68rem;
    color: #64748b;
    line-height: 1.35;
}

@media (max-width: 640px) {
    .pa-evaluated-levels-grid {
        grid-template-columns: 1fr;
    }
    .pa-template-grid {
        grid-template-columns: 1fr;
    }
}
</style>
