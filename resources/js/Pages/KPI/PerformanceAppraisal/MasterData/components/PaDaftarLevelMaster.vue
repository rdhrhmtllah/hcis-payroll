<template>
    <!--
     | DAFTAR LEVEL PA (DDL-2026-183) — level yang dipakai seluruh matriks PA.
     |
     | Level HRIS tampil hanya-baca (nama/urutan ikut HRIS). Level KHUSUS PA (mis. Officer
     | Specialist) ditambah di sini; isi matriks level dasarnya bisa disalin sebagai titik awal,
     | lalu disesuaikan di Bobot Pilar, Komposisi Penilai, Matriks Kompetensi, Matriks Soal, dan
     | Grup Kurva. Orangnya ditetapkan lewat tab Koreksi.
     -->
    <section class="pa-dlm" data-uji="lpa-daftar-level">
        <!-- MODAL FORM TAMBAH / UBAH LEVEL PA -->
        <HcisDialog
            v-model="form.terbuka"
            :title="form.id ? 'Ubah Level PA' : 'Tambah Level PA'"
            kicker="MASTER DATA LEVEL"
            icon="diagram-3"
            sentiment="brand"
            size="md"
            sticky-footer
            data-uji="dlm-dialog"
        >
            <div class="pa-dlm__form">
                <p class="pa-dlm__form-intro">
                    Level khusus PA memungkinkan pembuatan matriks tersendiri (bobot pilar, komposisi rater, kurva) untuk peran spesialis yang belum ada di HRIS.
                </p>

                <HcisField
                    id="dlm-nama"
                    v-model="form.nama"
                    label="Nama level"
                    placeholder="Contoh: OFFICER SPECIALIST"
                    required
                    :error="form.galatNama"
                />

                <div v-if="!form.id" class="pa-dlm__isian">
                    <span class="pa-dlm__label">Level dasar (HRIS) <span class="text-danger">*</span></span>
                    <HcisSelect
                        v-model="form.id_level_dasar"
                        size="sm"
                        placeholder="Pilih level dasar HRIS…"
                        data-uji="dlm-dasar"
                    >
                        <HcisSelectOption v-for="l in levelHris" :key="l.id" :value="l.id" :label="l.nama" />
                    </HcisSelect>
                    <small class="pa-dlm__muted">Menjadi acuan awal urutan hierarki dan salinan matriks.</small>
                </div>

                <div class="pa-dlm__isian">
                    <span class="pa-dlm__label">Urutan Hierarki</span>
                    <HcisInputNumber
                        v-model="form.urutan"
                        :min="0"
                        :max="9999"
                        :step="0.5"
                        :precision="2"
                        size="sm"
                        align="left"
                        data-uji="dlm-urutan"
                    />
                    <small class="pa-dlm__muted">Posisi di daftar level. Contoh: 4,50 = di antara OFFICER (4) dan SENIOR OFFICER (5).</small>
                </div>

                <div v-if="!form.id" class="pa-dlm__copy-box">
                    <HcisCheckbox
                        v-model="form.salin"
                        size="sm"
                        label="Salin isi matriks dari level dasar (bobot pilar, komposisi penilai, kompetensi, soal, grup kurva) sebagai titik awal"
                        data-uji="dlm-salin"
                    />
                </div>

                <HcisAlert
                    v-if="form.galat"
                    variant="danger"
                    appearance="subtle"
                    size="sm"
                    :description="form.galat"
                    icon="exclamation-triangle-fill"
                />
            </div>

            <template #footer>
                <div class="pa-dlm__dialog-actions">
                    <HcisButton
                        label="Batal"
                        variant="secondary"
                        size="sm"
                        :disabled="form.menyimpan"
                        @click="form.terbuka = false"
                    />
                    <HcisButton
                        :label="form.id ? 'Simpan' : 'Tambah Level'"
                        variant="primary"
                        size="sm"
                        leading-icon="check-lg"
                        :loading="form.menyimpan"
                        :disabled="form.nama.trim().length < 3 || (!form.id && !form.id_level_dasar)"
                        data-uji="dlm-simpan"
                        @click="simpan"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- 1. STAT GROUP METRICS WIDGET (DESIGN SYSTEM) -->
        <HcisStatGroup
            :cols="4"
            gap="sm"
            layout="compact"
            appearance="glass"
            class="pa-dlm__stats mb-3"
        >
            <HcisStatCard
                label="Total Level PA"
                :value="level.length"
                icon="diagram-3-fill"
                sentiment="brand"
                caption="Level aktif & terdaftar"
            />
            <HcisStatCard
                label="Level HRIS"
                :value="levelHrisCount"
                icon="building-fill"
                sentiment="neutral"
                caption="Struktur baku organisasi"
            />
            <HcisStatCard
                label="Khusus PA"
                :value="levelPaCount"
                icon="stars"
                sentiment="purple"
                caption="Spesialisasi matriks penilaian"
            />
            <HcisStatCard
                label="Status HRIS"
                :value="periksa?.sinkron ? 'Selaras' : 'Perlu Selaras'"
                :icon="periksa?.sinkron ? 'check-circle-fill' : 'exclamation-circle-fill'"
                :sentiment="periksa?.sinkron ? 'success' : 'warning'"
                :caption="periksa ? `${periksa.tersalin} dari ${periksa.jumlah_hris} tersalin` : 'Memeriksa…'"
            />
        </HcisStatGroup>

        <!-- 2. CARD SINKRONISASI HRIS (DESIGN SYSTEM) -->
        <HcisCard
            variant="glass"
            :sentiment="periksa?.sinkron ? 'success' : 'warning'"
            class="pa-dlm__sync-card mb-3"
        >
            <div class="pa-dlm__sinkron" data-uji="dlm-sinkron">
                <div class="pa-dlm__sinkron-meta">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <HcisBadge
                            :label="periksa?.sinkron ? 'SELARAS DENGAN HRIS' : 'PERBEDAAN TERDETEKSI'"
                            :variant="periksa?.sinkron ? 'success' : 'warning'"
                            size="xs"
                            dot
                        />
                    </div>
                    <p class="pa-dlm__sinkron-teks mb-0">
                        <strong>{{ kalimatSinkron }}</strong>
                        <span v-if="periksa && !periksa.sinkron" class="d-block text-muted">Tekan Periksa &amp; Sinkronkan untuk menyalin dan menyamakannya.</span>
                    </p>
                </div>
                <div class="pa-dlm__sinkron-action">
                    <HcisButton
                        label="Periksa & Sinkronkan HRIS"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-repeat"
                        :loading="menyinkron"
                        data-uji="dlm-sinkron-btn"
                        @click="sinkronkan"
                    />
                </div>
            </div>

            <!-- Rincian Selisih / Perbedaan HRIS -->
            <ul v-if="periksa && (periksa.belum.length || periksa.beda.length)" class="pa-dlm__rincian mt-2 mb-0" data-uji="dlm-sinkron-rincian">
                <li v-for="b in periksa.belum" :key="`b-${b.id}`" class="pa-dlm__rincian-item is-belum">
                    <HcisIcon name="plus-circle-fill" size="2xs" color="amber" />
                    <span>Belum tersalin: <strong>{{ b.nama }}</strong></span>
                </li>
                <li v-for="b in periksa.beda" :key="`d-${b.id}`" class="pa-dlm__rincian-item is-beda">
                    <HcisIcon name="exclamation-circle-fill" size="2xs" color="brand" />
                    <span>
                        Berbeda dari HRIS: <strong>{{ b.nama_pa }}</strong>
                        <template v-if="b.nama_pa !== b.nama_hris"> → {{ b.nama_hris }}</template>
                        <template v-if="b.urutan_pa !== b.urutan_hris"> (urutan {{ angkaUrutan(b.urutan_pa) }} → {{ angkaUrutan(b.urutan_hris) }})</template>
                    </span>
                </li>
            </ul>

            <!-- Peringatan Level Hilang dari HRIS -->
            <HcisAlert
                v-if="periksa?.hilang?.length"
                variant="warning"
                appearance="subtle"
                size="sm"
                :title="`${periksa.hilang.length} level sudah tidak ada di HRIS`"
                :description="periksa.hilang.map((h) => `${h.nama} (dipakai ${h.dipakai} baris)`).join(', ') + '. Tidak dihapus otomatis karena matriks atau siklus masih bisa menunjuknya — pindahkan pemakaiannya ke level lain lebih dulu.'"
                icon="exclamation-triangle-fill"
                data-uji="dlm-sinkron-hilang"
                class="mt-2"
            />
        </HcisCard>

        <!-- 3. FILTER BAR DENGAN QUICK CHIPS & ACTIONS (DESIGN SYSTEM) -->
        <HcisFilterBar
            :search="kataKunciCari"
            search-placeholder="Cari level PA atau level dasar…"
            :quick-chips="daftarQuickChips"
            :active-chip-value="sumberFilter"
            :collapsible="false"
            :results-count="levelTersaring.length"
            variant="card"
            sentiment="brand"
            size="sm"
            class="mb-3"
            @update:search="kataKunciCari = $event"
            @chip-click="(c) => pilihSumber(c.value)"
            @reset="resetFilter"
        >
            <template #actions>
                <HcisButton
                    label="Tambah Level PA"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    data-uji="dlm-tambah"
                    @click="bukaForm(null)"
                />
            </template>
        </HcisFilterBar>

        <!-- 4. MODERN DATA TABLE (HCISTABLE) -->
        <div class="pa-dlm__table-wrap">
            <HcisTable
                :columns="kolom"
                :rows="levelTersaring"
                row-key="id"
                :loading="memuat"
                size="sm"
                hoverable
                bordered
                empty-title="Tidak ada level yang cocok"
                empty-description="Coba ubah kata kunci pencarian atau sesuaikan filter sumber."
            >
                <template #cell-nama="{ row }">
                    <div class="pa-dlm__nama" :class="{ 'is-redup': !row.aktif }">
                        <div class="pa-dlm__nama-row">
                            <div class="pa-dlm__level-avatar" :class="row.sumber === 'PA' ? 'is-pa' : 'is-hris'">
                                {{ (row.nama || '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="pa-dlm__nama-info">
                                <strong>{{ row.nama }}</strong>
                                <small v-if="row.sumber === 'PA'">Dasar: {{ row.nama_level_dasar || '–' }}</small>
                            </div>
                        </div>
                    </div>
                </template>
                <template #cell-sumber="{ row }">
                    <HcisBadge
                        :label="row.sumber === 'PA' ? 'Khusus PA' : 'HRIS'"
                        :variant="row.sumber === 'PA' ? 'brand' : 'neutral'"
                        appearance="subtle"
                        size="xs"
                    />
                </template>
                <template #cell-urutan="{ row }">
                    <span class="pa-dlm__urutan-badge">{{ angkaUrutan(row.urutan) }}</span>
                </template>
                <template #cell-dipakai="{ row }">
                    <span v-if="row.sumber === 'PA'" class="pa-dlm__dikoreksi-tag">
                        {{ row.dipakai }} orang
                    </span>
                    <span v-else class="pa-dlm__muted">–</span>
                </template>
                <template #cell-status="{ row }">
                    <HcisBadge
                        :label="row.aktif ? 'Aktif' : 'Nonaktif'"
                        :variant="row.aktif ? 'success' : 'neutral'"
                        size="xs"
                        dot
                    />
                </template>
                <template #cell-aksi="{ row }">
                    <div v-if="row.sumber === 'PA'" class="pa-dlm__tombol">
                        <HcisButton
                            label="Ubah"
                            variant="ghost"
                            size="xs"
                            leading-icon="pencil"
                            :data-uji="`dlm-ubah-${row.id}`"
                            @click="bukaForm(row)"
                        />
                        <HcisButton
                            :label="row.aktif ? 'Nonaktifkan' : 'Aktifkan'"
                            variant="ghost"
                            size="xs"
                            :leading-icon="row.aktif ? 'slash-circle' : 'check-circle'"
                            :loading="mengubahAktif === row.id"
                            :data-uji="`dlm-aktif-${row.id}`"
                            @click="aturAktif(row)"
                        />
                    </div>
                    <span v-else class="pa-dlm__muted">ikut HRIS</span>
                </template>
            </HcisTable>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert, HcisBadge, HcisButton, HcisCard, HcisCheckbox, HcisDialog, HcisField,
    HcisFilterBar, HcisIcon, HcisInputNumber, HcisSelect, HcisSelectOption,
    HcisStatCard, HcisStatGroup, HcisTable, hcisMessage,
} from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaDaftarLevelMaster' });

const emit = defineEmits(['berubah']);

const kolom = [
    { key: 'nama', label: 'Level PA' },
    { key: 'sumber', label: 'Sumber', width: '120px' },
    { key: 'urutan', label: 'Urutan', width: '100px' },
    { key: 'dipakai', label: 'Dikoreksi', width: '120px' },
    { key: 'status', label: 'Status', width: '110px' },
    { key: 'aksi', label: '', align: 'right', width: '200px' },
];

const level = ref([]);
const periksa = ref(null);
const menyinkron = ref(false);
const memuat = ref(false);
const mengubahAktif = ref(null);

const kataKunciCari = ref('');
const sumberFilter = ref('');

const levelHris = computed(() => level.value.filter((l) => l.sumber === 'HRIS' && l.aktif));
const levelHrisCount = computed(() => level.value.filter((l) => l.sumber === 'HRIS').length);
const levelPaCount = computed(() => level.value.filter((l) => l.sumber === 'PA').length);

const daftarQuickChips = computed(() => [
    { id: 'semua', value: '', label: 'Semua Level', badge: level.value.length },
    { id: 'hris', value: 'HRIS', label: 'Sumber HRIS', badge: levelHrisCount.value, color: 'neutral' },
    { id: 'pa', value: 'PA', label: 'Khusus PA', badge: levelPaCount.value, color: 'brand' },
]);

function pilihSumber(val) {
    sumberFilter.value = val === sumberFilter.value ? '' : val;
}

function resetFilter() {
    kataKunciCari.value = '';
    sumberFilter.value = '';
}

const levelTersaring = computed(() => {
    let rows = level.value;
    if (sumberFilter.value === 'HRIS') rows = rows.filter((l) => l.sumber === 'HRIS');
    else if (sumberFilter.value === 'PA') rows = rows.filter((l) => l.sumber === 'PA');

    const q = (kataKunciCari.value || '').trim().toLowerCase();
    if (!q) return rows;

    return rows.filter((l) => {
        const nama = (l.nama || '').toLowerCase();
        const dasar = (l.nama_level_dasar || '').toLowerCase();
        return nama.includes(q) || dasar.includes(q);
    });
});

const angkaUrutan = (v) => Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });

const kalimatSinkron = computed(() => {
    const p = periksa.value;
    if (!p) return 'Memeriksa keselarasan dengan HRIS…';
    if (p.sinkron) return `Selaras dengan HRIS: ${p.tersalin} dari ${p.jumlah_hris} level HRIS tersalin.`;
    const bagian = [];
    if (p.belum.length) bagian.push(`${p.belum.length} level HRIS belum tersalin`);
    if (p.beda.length) bagian.push(`${p.beda.length} level berbeda nama/urutan`);
    return `Belum selaras dengan HRIS: ${bagian.join(', ')}.`;
});

async function muatPeriksa() {
    try {
        periksa.value = await paApi.periksaLevelMaster();
    } catch {
        /* Status saja — daftar tetap bisa dipakai. */
    }
}

async function sinkronkan() {
    menyinkron.value = true;
    try {
        const r = await paApi.sinkronLevelMaster();
        periksa.value = r?.periksa ?? null;
        const h = r?.hasil ?? {};
        hcisMessage.success(
            h.ditambah || h.diperbarui ? `${h.ditambah || 0} level ditambahkan, ${h.diperbarui || 0} diperbarui dari HRIS.` : 'Sudah selaras dengan HRIS — tidak ada yang berubah.',
            'Daftar Level PA',
        );
        await muat();
        emit('berubah');
    } catch (e) {
        hcisMessage.error(e?.message || 'Sinkron gagal.', 'Daftar Level PA');
    } finally {
        menyinkron.value = false;
    }
}

const form = reactive({
    terbuka: false,
    id: null,
    nama: '',
    id_level_dasar: null,
    urutan: 0,
    salin: true,
    menyimpan: false,
    galat: '',
    galatNama: '',
});

async function muat() {
    memuat.value = true;
    try {
        level.value = (await paApi.daftarLevelMaster(true))?.level ?? [];
    } catch (e) {
        hcisMessage.error(e?.message || 'Daftar level gagal dimuat.', 'Daftar Level PA');
    } finally {
        memuat.value = false;
    }
}

/* Level dasar dipilih → urutan awal di tengah antara level itu dan level berikutnya. */
watch(() => form.id_level_dasar, (id) => {
    if (form.id || id == null) return;
    const dasar = levelHris.value.find((l) => l.id === id);
    if (dasar) form.urutan = Number(dasar.urutan) + 0.5;
});

function bukaForm(baris) {
    Object.assign(form, {
        terbuka: true,
        id: baris?.id ?? null,
        nama: baris?.nama ?? '',
        id_level_dasar: baris?.id_level_dasar ?? null,
        urutan: baris ? Number(baris.urutan) : 0,
        salin: true,
        menyimpan: false,
        galat: '',
        galatNama: '',
    });
}

async function simpan() {
    form.menyimpan = true;
    form.galat = '';
    try {
        if (form.id) {
            await paApi.ubahLevelMaster(form.id, { nama: form.nama.trim(), urutan: form.urutan });
            hcisMessage.success('Level PA diperbarui.', 'Daftar Level PA');
        } else {
            const r = await paApi.tambahLevelMaster({
                nama: form.nama.trim(), id_level_dasar: form.id_level_dasar, urutan: form.urutan, salin_matriks: form.salin,
            });
            const disalin = Object.values(r?.disalin ?? {}).reduce((a, b) => a + Number(b || 0), 0);
            hcisMessage.success(form.salin ? `Level PA ditambahkan; ${disalin} baris matriks disalin dari level dasar.` : 'Level PA ditambahkan.', 'Daftar Level PA');
        }
        form.terbuka = false;
        await muat();
        emit('berubah');
    } catch (e) {
        form.galat = e?.message || 'Level PA gagal disimpan.';
    } finally {
        form.menyimpan = false;
    }
}

async function aturAktif(baris) {
    mengubahAktif.value = baris.id;
    try {
        await paApi.aturAktifLevelMaster(baris.id, !baris.aktif);
        await muat();
        emit('berubah');
    } catch (e) {
        hcisMessage.error(e?.message || 'Status level gagal diubah.', 'Daftar Level PA');
    } finally {
        mengubahAktif.value = null;
    }
}

onMounted(() => {
    muatPeriksa();
    muat();
});

defineExpose({ muat });
</script>

<style scoped>
.pa-dlm {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Sync Panel Card */
.pa-dlm__sync-card {
    border-radius: 12px;
}

.pa-dlm__sinkron {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.pa-dlm__sinkron-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
    min-width: 0;
}

.pa-dlm__sinkron-teks {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    margin: 0;
    font-size: 0.875rem;
    color: #0f172a;
}

.pa-dlm__sinkron-action {
    flex-shrink: 0;
}

.pa-dlm__rincian {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-left: 0;
    list-style: none;
}

.pa-dlm__rincian-item {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    line-height: 1.3;
}

.pa-dlm__rincian-item.is-belum {
    background: #fffbeb;
    border: 1px solid #fef3c7;
    color: #92400e;
}

.pa-dlm__rincian-item.is-beda {
    background: #eff6ff;
    border: 1px solid #dbeafe;
    color: #1e40af;
}

/* Table Enhancements */
.pa-dlm__table-wrap {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pa-dlm__nama-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-dlm__level-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.pa-dlm__level-avatar.is-hris {
    background: #f1f5f9;
    color: #475569;
}

.pa-dlm__level-avatar.is-pa {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #dbeafe;
}

.pa-dlm__nama-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.pa-dlm__nama-info strong {
    color: #0f172a;
    font-weight: 700;
    font-size: 0.8125rem;
}

.pa-dlm__nama-info small {
    color: #64748b;
    font-size: 0.71875rem;
}

.pa-dlm__nama.is-redup {
    opacity: 0.5;
}

.pa-dlm__urutan-badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    background: #f1f5f9;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 600;
    color: #334155;
}

.pa-dlm__dikoreksi-tag {
    font-size: 0.75rem;
    color: #0f172a;
    font-weight: 600;
}

.pa-dlm__muted {
    color: #94a3b8;
    font-size: 0.75rem;
}

.pa-dlm__tombol {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    justify-content: flex-end;
}

/* Dialog Form */
.pa-dlm__form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-dlm__form-intro {
    margin: 0;
    font-size: 0.8125rem;
    color: #64748b;
    line-height: 1.5;
}

.pa-dlm__isian {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.pa-dlm__label {
    font-size: 0.78125rem;
    font-weight: 700;
    color: #334155;
}

.pa-dlm__copy-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
}

.pa-dlm__dialog-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

@media (max-width: 640px) {
    .pa-dlm__sinkron {
        flex-direction: column;
        align-items: flex-start;
    }

    .pa-dlm__sinkron-action {
        width: 100%;
    }
}
</style>


