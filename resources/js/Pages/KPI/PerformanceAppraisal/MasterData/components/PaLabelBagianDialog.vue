<script setup>
/**
 * Pelabelan bagian soal ke kompetensi — satu bagian tepat satu kompetensi.
 *
 * KUNCINYA PASANGAN, BUKAN SATU FIELD
 *
 * HC Learn tidak memberi id per-instance untuk bagian. Yang ada hanya `id_jenis_soal`, dan
 * dokumentasi mereka sendiri menyebutnya pengenal BANK SOAL: "Bank yang sama bisa dipakai
 * beberapa alat tes." Kalau baris di sini dikunci dengan itu saja, dua bagian dari alat tes
 * berbeda saling menimpa — dan gejalanya bukan galat, melainkan satu kompetensi yang
 * diam-diam kehilangan labelnya.
 *
 * BIJEKSI DIJAGA SAMBIL DIISI
 *
 * Kompetensi yang sudah terpakai hilang dari pilihan bagian lain. Admin yang baru tahu
 * labelnya rangkap setelah menekan simpan harus menelusuri dua puluh baris untuk menemukan
 * yang mana; mencegahnya di tempat jauh lebih murah daripada melaporkannya belakangan.
 */
import { computed, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisTreeSelect,
} from '@/components/ui';

defineOptions({ name: 'PaLabelBagianDialog' });

const props = defineProps({
    idLevel: { type: Number, required: true },
    namaLevel: { type: String, default: '' },
    raterCategoryId: { type: Number, default: null },
    namaKategori: { type: String, default: '' },
    idMasterUjian: { type: String, required: true },
    namaUjian: { type: String, default: '' },
    namaPaket: { type: String, default: '' },
    bagian: { type: Array, required: true },
    kompetensiWajib: { type: Array, required: true },
    labelAwal: { type: Object, default: () => ({}) },
    menyimpan: { type: Boolean, default: false },
});

const emit = defineEmits(['simpan', 'batal']);

const kunciBagian = (b) => `${b.id_jenis_indikator}|${b.id_jenis_soal}`;

const label = ref({ ...props.labelAwal });
const kataKunci = ref('');
const filterStatus = ref('semua'); // 'semua' | 'belum' | 'sudah'
const notifikasiAksi = ref('');
const showUnmappedHelper = ref(true);

/* Label awal boleh datang belakangan — tombol "salin label dari sel lain" mengisinya
   sesudah dialognya terbuka. Tanpa watch, salinannya tidak pernah terlihat. */
watch(
    () => props.labelAwal,
    (nilai) => {
        label.value = { ...nilai };
    },
    { deep: true },
);

/* Bagian dikelompokkan menurut alat tesnya. Satu paket bisa memuat dua alat tes yang
   sama-sama punya "Bagian 1"; tanpa judul kelompok, admin tidak tahu yang mana yang sedang
   ia labeli. */
const kelompok = computed(() => {
    const peta = new Map();

    for (const b of [...props.bagian].sort(
        (x, y) =>
            (x.urutan_alat_tes ?? 0) - (y.urutan_alat_tes ?? 0) ||
            (x.urutan_bagian ?? 0) - (y.urutan_bagian ?? 0),
    )) {
        const k = b.id_jenis_indikator;

        if (!peta.has(k)) {
            peta.set(k, { id: k, nama: b.nama_alat_tes || 'Alat Tes', bagian: [] });
        }

        peta.get(k).bagian.push(b);
    }

    return [...peta.values()];
});

/* Dimensi → kompetensi, untuk pemilih dua tingkat. */
const dimensi = computed(() => {
    const peta = new Map();

    for (const k of props.kompetensiWajib) {
        if (!peta.has(k.dimensi_kode)) {
            peta.set(k.dimensi_kode, { kode: k.dimensi_kode, nama: k.dimensi_nama, anak: [] });
        }

        peta.get(k.dimensi_kode).anak.push(k);
    }

    return [...peta.values()];
});

const terpakai = computed(() => new Set(Object.values(label.value)));

const mapKompetensi = computed(() => {
    const map = new Map();
    for (const k of props.kompetensiWajib) {
        map.set(k.id, k);
    }
    return map;
});

const kompetensiBelumTerpakai = computed(() => {
    return props.kompetensiWajib.filter((k) => !terpakai.value.has(k.id));
});

const kompetensiBelumTerpakaiPerDimensi = computed(() => {
    const peta = new Map();
    for (const k of kompetensiBelumTerpakai.value) {
        if (!peta.has(k.dimensi_kode)) {
            peta.set(k.dimensi_kode, { kode: k.dimensi_kode, nama: k.dimensi_nama, items: [] });
        }
        peta.get(k.dimensi_kode).items.push(k);
    }
    return [...peta.values()];
});

/**
 * Kompetensi yang boleh dipilih baris ini.
 *
 * Yang sedang DIPEGANG baris ini tetap ikut — kalau tidak, pilihannya sendiri menghilang
 * dari daftar begitu dipilih, dan kotaknya tampak kosong padahal sudah terisi.
 */
function pilihanUntuk(kunci) {
    const milikSendiri = label.value[kunci];

    return props.kompetensiWajib.filter((k) => !terpakai.value.has(k.id) || k.id === milikSendiri);
}

function treeDataUntuk(kunci) {
    const pilihan = pilihanUntuk(kunci);
    const peta = new Map();

    for (const k of pilihan) {
        if (!peta.has(k.dimensi_kode)) {
            peta.set(k.dimensi_kode, {
                value: `dim_${k.dimensi_kode}`,
                label: k.dimensi_nama || k.dimensi_kode,
                selectable: false,
                icon: getDimensiIcon(k.dimensi_kode),
                badge: k.dimensi_kode,
                badgeVariant: getDimensiBadge(k.dimensi_kode),
                children: [],
            });
        }
        peta.get(k.dimensi_kode).children.push({
            value: k.id,
            label: `${k.kode} — ${k.nama}`,
            code: k.kode,
        });
    }

    return [...peta.values()];
}

function setLabel(kunci, kompetensiId) {
    if (kompetensiId === null || kompetensiId === '' || kompetensiId === undefined) {
        const next = { ...label.value };
        delete next[kunci];
        label.value = next;
        return;
    }

    label.value = { ...label.value, [kunci]: Number(kompetensiId) };
}

const jumlahBerlabel = computed(() => Object.keys(label.value).length);
const totalBagian = computed(() => props.bagian.length);
const persenBerlabel = computed(() => {
    if (!totalBagian.value) return 0;
    return Math.min(100, Math.round((jumlahBerlabel.value / totalBagian.value) * 100));
});
const lengkap = computed(() => jumlahBerlabel.value === props.bagian.length && props.bagian.length > 0);
const jumlahBelumBerlabel = computed(() => Math.max(0, totalBagian.value - jumlahBerlabel.value));

/* Kelompok tersaring berdasarkan pencarian dan tab filter */
const kelompokTersaring = computed(() => {
    const q = kataKunci.value.trim().toLowerCase();
    const st = filterStatus.value;

    return kelompok.value
        .map((alat) => {
            const bagianSaring = alat.bagian.filter((b) => {
                const k = kunciBagian(b);
                const terisi = label.value[k] !== undefined && label.value[k] !== null;

                if (st === 'belum' && terisi) return false;
                if (st === 'sudah' && !terisi) return false;

                if (!q) return true;

                const namaBagian = String(b.nama || '').toLowerCase();
                const kodeSoal = String(b.kode_soal || '').toLowerCase();
                const namaAlat = String(alat.nama || '').toLowerCase();
                const kompetensiTerpilih = label.value[k] ? mapKompetensi.value.get(label.value[k]) : null;
                const namaKomp = kompetensiTerpilih ? String(kompetensiTerpilih.nama || '').toLowerCase() : '';
                const kodeKomp = kompetensiTerpilih ? String(kompetensiTerpilih.kode || '').toLowerCase() : '';

                return (
                    namaBagian.includes(q) ||
                    kodeSoal.includes(q) ||
                    namaAlat.includes(q) ||
                    namaKomp.includes(q) ||
                    kodeKomp.includes(q)
                );
            });

            return {
                ...alat,
                bagian: bagianSaring,
            };
        })
        .filter((alat) => alat.bagian.length > 0);
});

/* Normalisasi teks string untuk perbandingan cerdas */
function normText(str) {
    return String(str || '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Smart Auto-Match:
 * Otomatis mencocokkan bagian yang belum berlabel dengan kompetensi wajib yang cocok berdasarkan
 * kesamaan nama atau kode.
 */
function cocokkanOtomatis() {
    let matched = 0;
    const nextLabel = { ...label.value };
    const currentUsed = new Set(Object.values(nextLabel));

    for (const b of props.bagian) {
        const k = kunciBagian(b);
        if (nextLabel[k] !== undefined && nextLabel[k] !== null) {
            continue; // Sudah terisi
        }

        const normBagian = normText(b.nama);
        const normKode = normText(b.kode_soal);

        // Cari kandidat kompetensi yang belum terpakai
        const candidate = props.kompetensiWajib.find((kw) => {
            if (currentUsed.has(kw.id)) return false;

            const normKompNama = normText(kw.nama);
            const normKompKode = normText(kw.kode);

            // Exact match on name or code
            if (normBagian === normKompNama || normKode === normKompKode || normBagian === normKompKode) {
                return true;
            }

            // High confidence substring match (min length 4)
            if (
                (normBagian.length >= 4 && normKompNama.includes(normBagian)) ||
                (normKompNama.length >= 4 && normBagian.includes(normKompNama))
            ) {
                return true;
            }

            return false;
        });

        if (candidate) {
            nextLabel[k] = candidate.id;
            currentUsed.add(candidate.id);
            matched++;
        }
    }

    label.value = nextLabel;

    if (matched > 0) {
        notifikasiAksi.value = `Berhasil mencocokkan otomatis ${matched} bagian dengan kompetensi yang sesuai!`;
    } else {
        notifikasiAksi.value = 'Tidak ada bagian belum terpetakan tambahan yang cocok secara otomatis.';
    }

    setTimeout(() => {
        notifikasiAksi.value = '';
    }, 4500);
}

function resetSemua() {
    label.value = {};
    notifikasiAksi.value = 'Seluruh pemetaan bagian telah dikosongkan.';
    setTimeout(() => {
        notifikasiAksi.value = '';
    }, 3500);
}

function kosongkanBagian(kunci) {
    setLabel(kunci, null);
}

function getDimensiBadge(dimKode) {
    const d = String(dimKode || '').toUpperCase();
    if (d.includes('MANAJER')) return 'brand';
    if (d.includes('CORE') || d.includes('NILAI')) return 'success';
    if (d.includes('BEHAVIOR') || d.includes('PERILAKU')) return 'cyan';
    if (d.includes('KUALITATIF') || d.includes('QUAL')) return 'purple';
    return 'neutral';
}

function getDimensiIcon(dimKode) {
    const d = String(dimKode || '').toUpperCase();
    if (d.includes('MANAJER')) return 'briefcase-fill';
    if (d.includes('CORE') || d.includes('NILAI')) return 'heart-pulse-fill';
    if (d.includes('BEHAVIOR') || d.includes('PERILAKU')) return 'person-hearts';
    if (d.includes('KUALITATIF') || d.includes('QUAL')) return 'star-fill';
    return 'grid-fill';
}

function simpan() {
    if (!lengkap.value) {
        return;
    }

    emit('simpan', { ...label.value });
}

defineExpose({ label, setLabel, pilihanUntuk, treeDataUntuk, lengkap, kunciBagian, cocokkanOtomatis, resetSemua });
</script>

<template>
    <div class="pa-label-bagian-container">
        <!-- 1. Context Hero Card -->
        <div class="pa-lb-hero">
            <div class="pa-lb-hero-main">
                <div class="pa-lb-hero-badges">
                    <HcisBadge
                        v-if="namaLevel"
                        :label="`Level: ${namaLevel}`"
                        variant="brand"
                        size="xs"
                    />
                    <HcisBadge
                        v-if="namaKategori"
                        :label="`Rater: ${namaKategori}`"
                        variant="purple"
                        size="xs"
                    />
                    <span class="pa-lb-code-pill">Ujian #{{ idMasterUjian }}</span>
                </div>
                <h4 class="pa-lb-hero-title">{{ namaUjian || `Ujian ${idMasterUjian}` }}</h4>
                <p v-if="namaPaket" class="pa-lb-hero-sub">
                    <HcisIcon name="box-seam" size="2xs" />
                    <span>Paket: {{ namaPaket }}</span>
                </p>
            </div>

            <!-- Gauge / Progress Card -->
            <div class="pa-lb-progress-card">
                <div class="pa-lb-progress-top">
                    <div class="pa-lb-progress-title">
                        <HcisIcon
                            :name="lengkap ? 'check-circle-fill' : 'clock-history'"
                            :color="lengkap ? 'success' : 'brand'"
                            size="xs"
                        />
                        <strong data-uji="hitung-label">
                            {{ jumlahBerlabel }} dari {{ bagian.length }} bagian berlabel
                        </strong>
                    </div>
                    <span class="pa-lb-progress-percent" :class="{ 'is-complete': lengkap }">
                        {{ persenBerlabel }}%
                    </span>
                </div>

                <div class="pa-lb-progress-bar-track">
                    <div
                        class="pa-lb-progress-bar-fill"
                        :class="{ 'is-complete': lengkap }"
                        :style="{ width: `${persenBerlabel}%` }"
                    />
                </div>

                <div class="pa-lb-progress-status">
                    <span v-if="lengkap" class="pa-lb-status-ok">
                        <HcisIcon name="shield-check" size="2xs" />
                        Siap disimpan &bull; 100% lengkap
                    </span>
                    <span v-else class="pa-lb-status-pending">
                        <HcisIcon name="exclamation-circle" size="2xs" />
                        Tersisa {{ jumlahBelumBerlabel }} bagian belum dipetakan
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Action Notification Banner -->
        <transition name="hcis-fade">
            <HcisAlert
                v-if="notifikasiAksi"
                variant="brand"
                appearance="subtle"
                size="sm"
                icon="info-circle-fill"
                closable
                class="mb-2"
                @close="notifikasiAksi = ''"
            >
                {{ notifikasiAksi }}
            </HcisAlert>
        </transition>

        <!-- 3. Remaining Unassigned Competencies Helper -->
        <div class="pa-lb-unassigned-panel">
            <div class="pa-lb-unassigned-header" @click="showUnmappedHelper = !showUnmappedHelper">
                <div class="pa-lb-unassigned-title">
                    <HcisIcon name="collection-fill" size="2xs" color="brand" />
                    <span>Kompetensi Wajib Level</span>
                    <HcisBadge
                        :label="kompetensiBelumTerpakai.length === 0 ? 'Semua Terpasang' : `${kompetensiBelumTerpakai.length} Belum Terpakai`"
                        :variant="kompetensiBelumTerpakai.length === 0 ? 'success' : 'neutral'"
                        size="xs"
                    />
                </div>
                <button
                    type="button"
                    class="pa-lb-collapse-btn"
                    :aria-label="showUnmappedHelper ? 'Sembunyikan daftar' : 'Tampilkan daftar'"
                >
                    <HcisIcon :name="showUnmappedHelper ? 'chevron-up' : 'chevron-down'" size="2xs" />
                </button>
            </div>

            <div v-if="showUnmappedHelper" class="pa-lb-unassigned-body">
                <div v-if="kompetensiBelumTerpakai.length === 0" class="pa-lb-all-mapped-badge">
                    <HcisIcon name="check-all" size="xs" color="success" />
                    <span>Semua {{ kompetensiWajib.length }} kompetensi wajib level telah dipasangkan ke bagian soal secara bijektif (1-ke-1).</span>
                </div>
                <div v-else class="pa-lb-chips-group">
                    <div
                        v-for="dim in kompetensiBelumTerpakaiPerDimensi"
                        :key="dim.kode"
                        class="pa-lb-dim-group"
                    >
                        <span class="pa-lb-dim-title">
                            <HcisIcon :name="getDimensiIcon(dim.kode)" size="2xs" />
                            {{ dim.nama }}:
                        </span>
                        <div class="pa-lb-chips-list">
                            <span
                                v-for="k in dim.items"
                                :key="k.id"
                                class="pa-lb-chip"
                                :class="`is-${getDimensiBadge(k.dimensi_kode)}`"
                                :title="`${k.kode} - ${k.nama} (${k.dimensi_nama})`"
                            >
                                <strong>{{ k.kode }}</strong> &bull; {{ k.nama }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Filter & Search Toolbar -->
        <div class="pa-lb-toolbar">
            <div class="pa-lb-filter-tabs">
                <button
                    type="button"
                    class="pa-lb-tab"
                    :class="{ active: filterStatus === 'semua' }"
                    @click="filterStatus = 'semua'"
                >
                    <span>Semua Bagian</span>
                    <span class="pa-lb-tab-count">{{ totalBagian }}</span>
                </button>
                <button
                    type="button"
                    class="pa-lb-tab"
                    :class="{ active: filterStatus === 'belum' }"
                    @click="filterStatus = 'belum'"
                >
                    <HcisIcon name="clock-fill" size="2xs" />
                    <span>Belum Dipetakan</span>
                    <span class="pa-lb-tab-count is-amber">{{ jumlahBelumBerlabel }}</span>
                </button>
                <button
                    type="button"
                    class="pa-lb-tab"
                    :class="{ active: filterStatus === 'sudah' }"
                    @click="filterStatus = 'sudah'"
                >
                    <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                    <span>Sudah Terpetakan</span>
                    <span class="pa-lb-tab-count is-green">{{ jumlahBerlabel }}</span>
                </button>
            </div>

            <div class="pa-lb-toolbar-right">
                <div class="pa-lb-search-wrap">
                    <HcisField
                        id="pa-lb-search-input"
                        v-model="kataKunci"
                        size="sm"
                        placeholder="Cari bagian / kode / alat tes..."
                        leading-icon="search"
                    />
                </div>
                <HcisButton
                    v-if="jumlahBelumBerlabel > 0"
                    label="Cocokkan Otomatis"
                    variant="secondary"
                    size="sm"
                    leading-icon="magic"
                    title="Cocokkan nama bagian dengan kompetensi secara otomatis"
                    @click="cocokkanOtomatis"
                />
                <HcisButton
                    v-if="jumlahBerlabel > 0"
                    label="Reset"
                    variant="ghost"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    title="Kosongkan seluruh pemetaan"
                    @click="resetSemua"
                />
            </div>
        </div>

        <!-- 5. Grouped Section Rows -->
        <div class="pa-lb-groups-container">
            <div v-if="!kelompokTersaring.length" class="pa-lb-empty-search">
                <HcisEmptyState
                    variant="card"
                    sentiment="neutral"
                    size="sm"
                    title="Tidak Ada Bagian yang Sesuai"
                    :description="kataKunci ? `Tidak ditemukan bagian soal dengan kata kunci '${kataKunci}'.` : 'Tidak ada bagian yang sesuai dengan filter yang dipilih.'"
                    icon="search"
                >
                    <template #actions>
                        <HcisButton
                            label="Reset Filter"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-counterclockwise"
                            @click="kataKunci = ''; filterStatus = 'semua'"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <div
                v-for="alat in kelompokTersaring"
                :key="alat.id"
                class="pa-lb-tool-card"
            >
                <div class="pa-lb-tool-header">
                    <div class="pa-lb-tool-name">
                        <HcisIcon name="diagram-2-fill" size="xs" color="brand" />
                        <h4>{{ alat.nama }}</h4>
                    </div>
                    <span class="pa-lb-tool-count">{{ alat.bagian.length }} Bagian</span>
                </div>

                <div class="pa-lb-rows-list">
                    <div
                        v-for="(b, idx) in alat.bagian"
                        :key="kunciBagian(b)"
                        class="pa-lb-row-item"
                        :class="{
                            'is-mapped': label[kunciBagian(b)] !== undefined && label[kunciBagian(b)] !== null,
                            'is-unmapped': label[kunciBagian(b)] === undefined || label[kunciBagian(b)] === null,
                        }"
                    >
                        <!-- Section Identification -->
                        <div class="pa-lb-row-info">
                            <div class="pa-lb-row-num-pill">
                                {{ (b.urutan_bagian ?? idx) + 1 }}
                            </div>
                            <div class="pa-lb-row-titles">
                                <div class="pa-lb-row-main-title">
                                    <strong class="pa-lb-row-name">
                                        {{ b.nama || b.kode_soal || kunciBagian(b) }}
                                    </strong>
                                    <span v-if="b.kode_soal" class="pa-lb-code-tag">
                                        [{{ b.kode_soal }}]
                                    </span>
                                </div>
                                <div class="pa-lb-row-submeta">
                                    <span class="pa-lb-meta-tag">
                                        <HcisIcon name="database" size="2xs" />
                                        Bank ID: {{ b.id_jenis_soal }}
                                    </span>
                                    <template v-if="b.jumlah_soal">
                                        <span class="pa-lb-meta-dot">&bull;</span>
                                        <span class="pa-lb-meta-tag">{{ b.jumlah_soal }} Soal</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Dropdown Select Mapping Control using HcisTreeSelect -->
                        <div class="pa-lb-row-control">
                            <div class="pa-lb-select-wrapper" :data-uji="`pilih-${kunciBagian(b)}`">
                                <HcisTreeSelect
                                    :model-value="label[kunciBagian(b)] ?? ''"
                                    :data="treeDataUntuk(kunciBagian(b))"
                                    placeholder="— Pilih Kompetensi Wajib —"
                                    size="sm"
                                    :clearable="true"
                                    :filterable="true"
                                    :default-expand-all="true"
                                    @update:model-value="setLabel(kunciBagian(b), $event)"
                                    @clear="kosongkanBagian(kunciBagian(b))"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Sticky Bilateral Footer Actions -->
        <footer class="pa-lb-footer">
            <div class="pa-lb-footer-left">
                <HcisIcon
                    :name="lengkap ? 'check-circle-fill' : 'info-circle-fill'"
                    :color="lengkap ? 'success' : 'brand'"
                    size="xs"
                />
                <span class="pa-lb-footer-status-text">
                    {{ lengkap ? 'Seluruh bagian telah terpetakan dengan lengkap.' : `Tersisa ${jumlahBelumBerlabel} bagian yang belum terpetakan.` }}
                </span>
            </div>

            <div class="pa-lb-footer-right">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    @click="emit('batal')"
                />
                <HcisButton
                    variant="primary"
                    :label="menyimpan ? 'Menyimpan…' : 'Simpan Pemetaan'"
                    size="sm"
                    leading-icon="check2-circle"
                    data-uji="simpan-label"
                    :disabled="!lengkap || menyimpan"
                    :loading="menyimpan"
                    @click="simpan"
                />
            </div>
        </footer>
    </div>
</template>

<style scoped>
.pa-label-bagian-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    box-sizing: border-box;
}

/* 1. Hero Card */
.pa-lb-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #f8faff;
    border: 1px solid #c7d2fe;
    flex-wrap: wrap;
}

.pa-lb-hero-main {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.pa-lb-hero-badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-lb-code-pill {
    font-size: 0.65rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #4338ca;
    background: #ffffff;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    border: 1px solid #c7d2fe;
}

.pa-lb-hero-title {
    margin: 0;
    font-size: 0.96rem;
    font-weight: 750;
    color: #1e1b4b;
    line-height: 1.35;
}

.pa-lb-hero-sub {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    color: #475569;
}

/* Progress Card */
.pa-lb-progress-card {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    min-width: 16rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.pa-lb-progress-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-lb-progress-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: #1e1b4b;
}

.pa-lb-progress-percent {
    font-size: 0.82rem;
    font-weight: 800;
    color: #4338ca;
    font-variant-numeric: tabular-nums;
}

.pa-lb-progress-percent.is-complete {
    color: #059669;
}

.pa-lb-progress-bar-track {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
}

.pa-lb-progress-bar-fill {
    height: 100%;
    background: #4f46e5;
    border-radius: 9999px;
    transition: width 250ms cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-lb-progress-bar-fill.is-complete {
    background: #10b981;
}

.pa-lb-progress-status {
    display: flex;
    align-items: center;
}

.pa-lb-status-ok {
    font-size: 0.68rem;
    font-weight: 650;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pa-lb-status-pending {
    font-size: 0.68rem;
    font-weight: 600;
    color: #b45309;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* 3. Unassigned Panel */
.pa-lb-unassigned-panel {
    display: flex;
    flex-direction: column;
    border-radius: var(--hcis-radius-md, 10px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.pa-lb-unassigned-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    cursor: pointer;
    user-select: none;
    border-bottom: 1px solid #f1f5f9;
}

.pa-lb-unassigned-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: #334155;
}

.pa-lb-collapse-btn {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 0.15rem;
    display: inline-flex;
}

.pa-lb-unassigned-body {
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
}

.pa-lb-all-mapped-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    color: #065f46;
    background: #d1fae5;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    font-weight: 600;
}

.pa-lb-chips-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-lb-dim-group {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-lb-dim-title {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    min-width: 8.5rem;
    padding-top: 0.15rem;
}

.pa-lb-chips-list {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    flex: 1;
}

.pa-lb-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.66rem;
    padding: 0.18rem 0.45rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
}

.pa-lb-chip strong {
    font-family: ui-monospace, monospace;
}

.pa-lb-chip.is-brand {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #3730a3;
}

.pa-lb-chip.is-success {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.pa-lb-chip.is-cyan {
    background: #ecfeff;
    border-color: #a5f3fc;
    color: #155e75;
}

.pa-lb-chip.is-purple {
    background: #faf5ff;
    border-color: #e9d5ff;
    color: #6b21a8;
}

/* 4. Toolbar */
.pa-lb-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.45rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 10px);
    flex-wrap: wrap;
}

.pa-lb-filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.pa-lb-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.26rem 0.55rem;
    border-radius: 6px;
    border: 1px solid transparent;
    background: #f8fafc;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 650;
    cursor: pointer;
    transition: background 120ms ease, color 120ms ease;
    white-space: nowrap;
}

.pa-lb-tab:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.pa-lb-tab.active {
    background: #eef2ff;
    color: #4338ca;
    border-color: #c7d2fe;
}

.pa-lb-tab-count {
    font-size: 0.65rem;
    background: rgba(0, 0, 0, 0.06);
    padding: 0.08rem 0.3rem;
    border-radius: 9999px;
    font-weight: 750;
}

.pa-lb-tab-count.is-amber {
    background: #fef3c7;
    color: #b45309;
}

.pa-lb-tab-count.is-green {
    background: #d1fae5;
    color: #065f46;
}

.pa-lb-toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
    justify-content: flex-end;
    min-width: 14rem;
    flex-wrap: wrap;
}

.pa-lb-search-wrap {
    min-width: 11rem;
    max-width: 16rem;
    flex: 1;
}

/* 5. Tool Group Cards & Rows */
/* SATU GULUNGAN, bukan dua.
   |
   | Dulu daftar ini dibatasi `max-height: 52vh` dan menggulung sendiri, di dalam badan
   | dialog yang juga menggulung. Dua wilayah gulir bersarang dengan tinggi vh yang
   | ditetapkan terpisah tidak pernah berjumlah tepat: begitu kepala, bilah alat, dan
   | catatan di atasnya melewati sisa tingginya, daftar 52vh itu terdorong ke bawah batas
   | panel — dan panelnya `overflow: hidden`, jadi bagian bawah daftar hilang tanpa
   | scrollbar yang menandainya.
   |
   | Sekarang yang menggulung hanya badan dialognya. Daftar sepanjang apa pun ikut
   | terbawa, dan admin tidak perlu menebak wilayah mana yang sedang ia gulir. */
.pa-lb-groups-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}


.pa-lb-tool-card {
    display: flex;
    flex-direction: column;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.pa-lb-tool-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.pa-lb-tool-name {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-lb-tool-name h4 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 750;
    color: #1e1b4b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-lb-tool-count {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    background: #ffffff;
    padding: 0.12rem 0.45rem;
    border-radius: 9999px;
    border: 1px solid #e2e8f0;
}

.pa-lb-rows-list {
    display: flex;
    flex-direction: column;
}

.pa-lb-row-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 120ms ease;
    box-sizing: border-box;
    flex-wrap: wrap;
}

.pa-lb-row-item:last-child {
    border-bottom: none;
}

.pa-lb-row-item:hover {
    background: #f8faff;
}

.pa-lb-row-item.is-unmapped {
    background: #fffdfa;
}

.pa-lb-row-info {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: 1;
    min-width: 14rem;
}

.pa-lb-row-num-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 9999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.7rem;
    font-weight: 750;
    font-family: ui-monospace, monospace;
    flex-shrink: 0;
}

.pa-lb-row-item.is-mapped .pa-lb-row-num-pill {
    background: #e0e7ff;
    color: #4338ca;
}

.pa-lb-row-titles {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.pa-lb-row-main-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-lb-row-name {
    font-size: 0.84rem;
    color: #0f172a;
    font-weight: 700;
}

.pa-lb-code-tag {
    font-size: 0.66rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.06rem 0.3rem;
    border-radius: 4px;
}

.pa-lb-row-submeta {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    color: #64748b;
}

.pa-lb-meta-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

.pa-lb-meta-dot {
    color: #cbd5e1;
}

/* Row Control (Select + Clear) */
.pa-lb-row-control {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 18rem;
    flex: 1;
    max-width: 24rem;
    justify-content: flex-end;
}

.pa-lb-select-wrapper {
    position: relative;
    width: 100%;
}

.pa-lb-native-select {
    width: 100%;
    appearance: none;
    -webkit-appearance: none;
    padding: 0.45rem 2rem 0.45rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.78rem;
    font-weight: 550;
    color: #1e293b;
    outline: none;
    cursor: pointer;
    transition: border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
    box-sizing: border-box;
}

.pa-lb-native-select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
}

.pa-lb-native-select.is-selected {
    background: #f8faff;
    border-color: #818cf8;
    color: #1e1b4b;
    font-weight: 650;
}

.pa-lb-select-icon {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
}

.pa-lb-clear-btn {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.2rem;
    display: inline-flex;
    transition: color 120ms ease;
}

.pa-lb-clear-btn:hover {
    color: #dc2626;
}

.pa-lb-empty-search {
    padding: 1rem 0;
}

/* 6. Footer */
.pa-lb-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-lb-footer-left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    color: #475569;
}

.pa-lb-footer-status-text {
    font-weight: 600;
}

.pa-lb-footer-right {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: auto;
}

@media (max-width: 640px) {
    .pa-lb-hero {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-lb-progress-card {
        min-width: 100%;
    }
    .pa-lb-row-control {
        min-width: 100%;
        max-width: 100%;
    }
    .pa-lb-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-lb-toolbar-right {
        justify-content: stretch;
    }
    .pa-lb-search-wrap {
        max-width: 100%;
    }
}
</style>
