<template>
    <div class="rs-galeri">
        <div class="rs-galeri-toolbar">
            <div class="rs-tabs" role="tablist">
                <button
                    v-for="t in tab"
                    :key="t.kunci"
                    type="button"
                    role="tab"
                    :aria-selected="aktif === t.kunci"
                    :class="['rs-tab', { aktif: aktif === t.kunci }]"
                    @click="aktif = t.kunci"
                >
                    <i :class="['bi', t.ikon, 'rs-tab-icon']"></i>
                    <span>{{ t.label }}</span>
                    <span class="rs-badge">{{ (data?.[t.kunci] ?? []).length }}</span>
                </button>
            </div>

            <div class="rs-galeri-controls">
                <div class="rs-galeri-search-wrap">
                    <div class="rs-search-box">
                        <i class="bi bi-search rs-search-icon"></i>
                        <input
                            v-model="kataKunci"
                            type="text"
                            class="rs-search-input"
                            placeholder="Cari template…"
                        />
                        <button
                            v-if="kataKunci"
                            type="button"
                            class="rs-search-clear"
                            title="Hapus pencarian"
                            @click="kataKunci = ''"
                        >
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                </div>

                <!-- Tampilan Switcher (Grid vs List) -->
                <div class="rs-view-switcher" role="group" aria-label="Mode Tampilan Template">
                    <button
                        type="button"
                        class="rs-view-btn"
                        :class="{ 'is-active': tampilan === 'grid' }"
                        title="Tampilan Kartu (Grid)"
                        aria-label="Tampilan Kartu"
                        @click="tampilan = 'grid'"
                    >
                        <i class="bi bi-grid-fill"></i>
                        <span>Grid</span>
                    </button>
                    <button
                        type="button"
                        class="rs-view-btn"
                        :class="{ 'is-active': tampilan === 'list' }"
                        title="Tampilan Daftar (List)"
                        aria-label="Tampilan Daftar"
                        @click="tampilan = 'list'"
                    >
                        <i class="bi bi-list-ul"></i>
                        <span>List</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!data" class="rs-kosong">
            <div class="spinner-border spinner-border-sm text-brand-600 me-2" role="status"></div>
            <span>Memuat galeri template…</span>
        </div>

        <div v-else-if="daftarAktif.length === 0" class="rs-kosong-wrap">
            <HcisEmptyState
                :icon="aktif === 'saya' ? 'bi-folder-plus' : (aktif === 'dibagikan' ? 'bi-share' : 'bi-collection')"
                :title="aktif === 'saya' ? 'Belum Ada Template Pribadi' : (aktif === 'dibagikan' ? 'Belum Ada Template Dibagikan' : 'Belum Ada Template Resmi')"
                :description="pesanKosong"
            />
        </div>

        <div v-else-if="daftarTersaring.length === 0" class="rs-kosong-wrap">
            <HcisEmptyState
                icon="bi-search"
                title="Template tidak ditemukan"
                :description="`Tidak ada template di tab ${tab.find(t => t.kunci === aktif)?.label} yang cocok dengan kata kunci «${kataKunci}».`"
            />
        </div>

        <template v-else>
            <!-- ══ 1. CARD GRID VIEW ══ -->
            <div v-if="tampilan === 'grid'" class="rs-kartu-grid">
                <HcisCard
                    v-for="t in daftarHalaman"
                    :key="kodeDari(t)"
                    variant="default"
                    size="sm"
                    :sentiment="aktif === 'resmi' ? 'brand' : (aktif === 'saya' ? 'success' : 'teal')"
                    :icon="aktif === 'resmi' ? 'patch-check-fill' : (aktif === 'saya' ? 'person-fill' : 'share-fill')"
                    class="rs-kartu"
                >
                    <template #title>
                        <h6 class="rs-kartu-title" :title="namaDari(t)">{{ namaDari(t) }}</h6>
                    </template>

                    <template #badge>
                        <HcisBadge
                            :label="aktif === 'resmi' ? 'Resmi' : (aktif === 'saya' ? 'Milik Saya' : 'Dibagikan')"
                            :variant="aktif === 'resmi' ? 'brand' : (aktif === 'saya' ? 'success' : 'teal')"
                            size="xs"
                        />
                    </template>

                    <template #description>
                        <div class="rs-kartu-meta-row">
                            <span class="rs-meta-tag">{{ datasetDari(t) }}</span>
                            <span class="rs-meta-dot">•</span>
                            <span class="rs-meta-mode">{{ infoTemplate(t).isRingkasan ? 'Ringkasan' : 'Rinci' }}</span>
                        </div>
                    </template>

                    <div class="rs-kartu-content">
                        <p class="rs-kartu-desk" :title="deskripsiDari(t) || '—'">
                            {{ deskripsiDari(t) || '—' }}
                        </p>

                        <!-- Telemetry Blueprint Chips (Compact Inline) -->
                        <div class="rs-spec-chips">
                            <template v-if="infoTemplate(t).isRingkasan">
                                <span class="rs-spec-chip" title="Dimensi pengelompokan (GROUP BY)">
                                    <i class="bi bi-layers text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).dimensiCount }}</strong> Dimensi
                                </span>
                                <span class="rs-spec-chip" title="Ukuran & metrik agregat">
                                    <i class="bi bi-calculator text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).ukuranCount }}</strong> Ukuran
                                </span>
                                <span class="rs-spec-chip" title="Estimasi total kolom tabel">
                                    <i class="bi bi-table text-slate-500"></i>
                                    <strong>{{ infoTemplate(t).totalKolomEstimasi }}</strong> Kolom
                                </span>
                            </template>
                            <template v-else>
                                <span class="rs-spec-chip" title="Kolom data per baris faktual">
                                    <i class="bi bi-layout-three-columns text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).kolomCount }}</strong> Kolom
                                </span>
                            </template>

                            <span
                                class="rs-spec-chip"
                                :class="{ 'has-filter': infoTemplate(t).saringanCount > 0 }"
                                :title="infoTemplate(t).saringanCount ? `${infoTemplate(t).saringanCount} filter aktif` : 'Tanpa filter'"
                            >
                                <i class="bi bi-funnel"></i>
                                <span>{{ infoTemplate(t).saringanCount ? `${infoTemplate(t).saringanCount} Filter` : 'Semua' }}</span>
                            </span>

                            <span v-if="infoTemplate(t).hasGrandTotal" class="rs-spec-chip is-highlight" title="Sertakan baris Grand Total">
                                <i class="bi bi-check2 text-success-600"></i>
                                Total
                            </span>
                        </div>
                    </div>

                    <template #footer>
                        <div class="rs-kartu-footer">
                            <div class="rs-kartu-footer-primary">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    class="rs-btn rs-btn-pratinjau"
                                    icon="bi-eye-fill"
                                    label="Pratinjau"
                                    title="Lihat pratinjau data faktual template ini"
                                    @click="$emit('terapkan', t)"
                                />
                                <HcisButton
                                    v-if="bolehRakit"
                                    variant="primary"
                                    size="xs"
                                    class="rs-btn rs-btn-terapkan flex-fill"
                                    icon="bi-pencil-square"
                                    label="Edit di Studio"
                                    title="Buka template ini di Studio Perakit untuk dimodifikasi"
                                    @click="$emit('edit', t)"
                                />
                            </div>

                            <div class="rs-kartu-footer-actions">
                                <HcisButton
                                    variant="secondary"
                                    size="xs"
                                    :class="['rs-btn', bolehRakit ? 'rs-btn-icon' : '']"
                                    icon="bi-play-fill"
                                    :label="bolehRakit ? undefined : 'Export'"
                                    title="Jalankan export langsung di latar belakang"
                                    aria-label="Jalankan export"
                                    :disabled="Boolean(kodeDuplikat)"
                                    @click="handleJalankan(t)"
                                />
                                <HcisButton
                                    v-if="bolehRakit"
                                    variant="secondary"
                                    size="xs"
                                    class="rs-btn rs-btn-icon"
                                    icon="bi-copy"
                                    :title="aktif === 'resmi' ? 'Duplikat & ubah template ini' : 'Duplikat template ini'"
                                    aria-label="Duplikat template"
                                    :loading="kodeDuplikat === kodeDari(t)"
                                    :disabled="Boolean(kodeDuplikat)"
                                    @click="handleDuplikat(t)"
                                />
                                <HcisButton
                                    v-if="aktif === 'saya'"
                                    variant="ghost"
                                    size="xs"
                                    class="rs-btn rs-btn-icon rs-btn-hapus"
                                    icon="bi-trash3"
                                    title="Nonaktifkan template"
                                    aria-label="Nonaktifkan template"
                                    :disabled="Boolean(kodeDuplikat)"
                                    @click="mintaHapus(t)"
                                />
                            </div>
                        </div>
                    </template>
                </HcisCard>
            </div>

            <!-- ══ 2. LIST VIEW ══ -->
            <div v-else class="rs-list-container">
                <div
                    v-for="t in daftarHalaman"
                    :key="kodeDari(t)"
                    class="rs-list-row"
                >
                    <!-- Left: Icon Box -->
                    <div class="rs-list-icon-box" :class="`is-${aktif}`">
                        <i :class="['bi', aktif === 'resmi' ? 'bi-patch-check-fill' : (aktif === 'saya' ? 'bi-person-fill' : 'bi-share-fill')]"></i>
                    </div>

                    <!-- Center-Left: Main Info (Title, Badges, Meta, Deskripsi) -->
                    <div class="rs-list-info">
                        <div class="rs-list-title-row">
                            <h6 class="rs-list-title" :title="namaDari(t)">{{ namaDari(t) }}</h6>
                            <HcisBadge
                                :label="aktif === 'resmi' ? 'Resmi' : (aktif === 'saya' ? 'Milik Saya' : 'Dibagikan')"
                                :variant="aktif === 'resmi' ? 'brand' : (aktif === 'saya' ? 'success' : 'teal')"
                                size="xs"
                            />
                            <span class="rs-list-meta-text">{{ datasetDari(t) }} • {{ infoTemplate(t).isRingkasan ? 'Ringkasan' : 'Rinci' }}</span>
                        </div>
                        <p class="rs-list-desk" :title="deskripsiDari(t) || '—'">{{ deskripsiDari(t) || '—' }}</p>
                    </div>

                    <!-- Center-Right: Telemetry Chips -->
                    <div class="rs-list-specs">
                        <div class="rs-spec-chips">
                            <template v-if="infoTemplate(t).isRingkasan">
                                <span class="rs-spec-chip" title="Jumlah Dimensi Pengelompokan">
                                    <i class="bi bi-layers text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).dimensiCount }}</strong> Dim
                                </span>
                                <span class="rs-spec-chip" title="Jumlah Ukuran & Metrik">
                                    <i class="bi bi-calculator text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).ukuranCount }}</strong> Ukuran
                                </span>
                                <span class="rs-spec-chip" title="Total Kolom Dihasilkan">
                                    <i class="bi bi-table text-slate-500"></i>
                                    <strong>{{ infoTemplate(t).totalKolomEstimasi }}</strong> Kolom
                                </span>
                            </template>
                            <template v-else>
                                <span class="rs-spec-chip" title="Jumlah Kolom Data">
                                    <i class="bi bi-layout-three-columns text-brand-600"></i>
                                    <strong>{{ infoTemplate(t).kolomCount }}</strong> Kolom
                                </span>
                            </template>

                            <span
                                class="rs-spec-chip"
                                :class="{ 'has-filter': infoTemplate(t).saringanCount > 0 }"
                                :title="infoTemplate(t).saringanCount ? `${infoTemplate(t).saringanCount} filter aktif` : 'Tidak ada filter'"
                            >
                                <i class="bi bi-funnel"></i>
                                <span>{{ infoTemplate(t).saringanCount ? `${infoTemplate(t).saringanCount} Filter` : 'Semua' }}</span>
                            </span>

                            <span v-if="infoTemplate(t).hasGrandTotal" class="rs-spec-chip is-highlight" title="Sertakan Baris Grand Total">
                                <i class="bi bi-check2 text-success-600"></i>
                                Total
                            </span>
                        </div>
                    </div>

                    <!-- Right: Action Buttons -->
                    <div class="rs-list-actions">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            class="rs-btn"
                            icon="bi-eye-fill"
                            label="Pratinjau"
                            title="Lihat pratinjau data faktual template ini"
                            @click="$emit('terapkan', t)"
                        />
                        <HcisButton
                            v-if="bolehRakit"
                            variant="primary"
                            size="xs"
                            class="rs-btn rs-btn-terapkan"
                            icon="bi-pencil-square"
                            label="Edit"
                            title="Buka template ini di Studio Perakit untuk dimodifikasi"
                            @click="$emit('edit', t)"
                        />
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            :class="['rs-btn', bolehRakit ? 'rs-btn-icon' : '']"
                            icon="bi-play-fill"
                            :label="bolehRakit ? undefined : 'Export'"
                            title="Jalankan export langsung di latar belakang"
                            aria-label="Jalankan export"
                            :disabled="Boolean(kodeDuplikat)"
                            @click="handleJalankan(t)"
                        />
                        <HcisButton
                            v-if="bolehRakit"
                            variant="secondary"
                            size="xs"
                            class="rs-btn rs-btn-icon"
                            icon="bi-copy"
                            :title="aktif === 'resmi' ? 'Duplikat & ubah template ini' : 'Duplikat template ini'"
                            aria-label="Duplikat template"
                            :loading="kodeDuplikat === kodeDari(t)"
                            :disabled="Boolean(kodeDuplikat)"
                            @click="handleDuplikat(t)"
                        />
                        <HcisButton
                            v-if="aktif === 'saya'"
                            variant="ghost"
                            size="xs"
                            class="rs-btn rs-btn-icon rs-btn-hapus"
                            icon="bi-trash3"
                            title="Nonaktifkan template"
                            aria-label="Nonaktifkan template"
                            :disabled="Boolean(kodeDuplikat)"
                            @click="mintaHapus(t)"
                        />
                    </div>
                </div>
            </div>

            <!-- Plafon 12 kartu per halaman WAJIB terlihat: pembatasan yang tak
                 dilaporkan terbaca sebagai "cuma segini isinya". -->
            <HcisPagination
                v-if="daftarTersaring.length > KARTU_PER_HALAMAN"
                v-model="halaman"
                :total="daftarTersaring.length"
                :page-size="KARTU_PER_HALAMAN"
                class="mt-3"
            />
        </template>

        <HcisDialog
            :model-value="calonHapus !== null"
            title="Nonaktifkan template?"
            :description="`«${namaDari(calonHapus ?? {}) ?? ''}» tidak akan muncul lagi di galeri Anda.`"
            sentiment="danger"
            size="sm"
            @update:model-value="(v) => { if (!v) calonHapus = null; }"
        >
            <div class="sop-scope">
                <p class="text-muted small mb-0">
                    Template ini akan dinonaktifkan dari daftar template pribadi Anda. Anda dapat membuat template baru kapan saja.
                </p>
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    label="Batal"
                    :disabled="sedangMenghapus"
                    @click="calonHapus = null"
                />
                <HcisButton
                    variant="danger"
                    size="sm"
                    class="rs-hapus-kirim"
                    icon="bi-trash3-fill"
                    label="Nonaktifkan"
                    :loading="sedangMenghapus"
                    :disabled="sedangMenghapus"
                    @click="konfirmasiHapus"
                />
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisDialog from '@/components/ui/HcisDialog.vue';
import HcisPagination from '@/components/ui/HcisPagination.vue';
import HcisEmptyState from '@/components/ui/HcisEmptyState.vue';

const props = defineProps({
    /** { resmi: [], saya: [], dibagikan: [] } — null selama dimuat */
    data: { type: Object, default: null },
    tabAwal: { type: String, default: 'resmi' },
    /**
     * false di ponsel: template tetap bisa dipratinjau & dijalankan, tetapi
     * "Edit di Studio" dan "Duplikat" — keduanya membuka perakit — disembunyikan.
     */
    bolehRakit: { type: Boolean, default: true },
});

const emit = defineEmits(['jalankan', 'duplikat', 'hapus', 'terapkan', 'edit']);

const tab = [
    { kunci: 'resmi', label: 'Resmi', ikon: 'bi-award' },
    { kunci: 'saya', label: 'Milik Saya', ikon: 'bi-person-badge' },
    { kunci: 'dibagikan', label: 'Dibagikan', ikon: 'bi-share' },
];

const KARTU_PER_HALAMAN = 12;

const aktif = ref(props.tabAwal);
const tampilan = ref('grid'); // 'grid' | 'list'
const kataKunci = ref('');
const halaman = ref(1);
const calonHapus = ref(null);
const sedangMenghapus = ref(false);
const kodeDuplikat = ref(null);

watch(() => props.tabAwal, (v) => { aktif.value = v; });

/* Berpindah tab atau mengubah pencarian mengembalikan halaman ke 1. */
watch([aktif, kataKunci], () => { halaman.value = 1; });

/* Diekspos untuk uji komponen; induk mengendalikannya lewat `tabAwal`. */
defineExpose({ aktif, tampilan });

const daftarAktif = computed(() => props.data?.[aktif.value] ?? []);

const daftarTersaring = computed(() => {
    const q = kataKunci.value.trim().toLowerCase();
    if (!q) return daftarAktif.value;

    return daftarAktif.value.filter((t) => {
        const nama = (namaDari(t) || '').toLowerCase();
        const desk = (deskripsiDari(t) || '').toLowerCase();
        const dataset = (datasetDari(t) || '').toLowerCase();
        return nama.includes(q) || desk.includes(q) || dataset.includes(q);
    });
});

const daftarHalaman = computed(() => {
    const awal = (halaman.value - 1) * KARTU_PER_HALAMAN;
    return daftarTersaring.value.slice(awal, awal + KARTU_PER_HALAMAN);
});

const pesanKosong = computed(() =>
    ({
        resmi: 'Belum ada template resmi untuk modul ini.',
        saya: 'Belum ada template buatan Anda. Mulai dengan menduplikat salah satu template resmi.',
        dibagikan: 'Belum ada template yang dibagikan kepada Anda.',
    })[aktif.value]
);

function mintaHapus(t) {
    calonHapus.value = t;
}

async function konfirmasiHapus() {
    if (!calonHapus.value || sedangMenghapus.value) return;
    const kode = kodeDari(calonHapus.value);
    sedangMenghapus.value = true;
    try {
        await new Promise((resolve, reject) => {
            emit('hapus', kode, resolve, reject);
        });
        calonHapus.value = null;
    } catch {
        // Error handled in parent
    } finally {
        sedangMenghapus.value = false;
    }
}

async function handleDuplikat(t) {
    const kode = kodeDari(t);
    if (!kode || kodeDuplikat.value) return;
    kodeDuplikat.value = kode;
    try {
        await new Promise((resolve, reject) => {
            emit('duplikat', kode, resolve, reject);
        });
    } catch {
        // Error handled in parent
    } finally {
        kodeDuplikat.value = null;
    }
}

function handleJalankan(t) {
    const kode = kodeDari(t);
    if (!kode) return;
    emit('jalankan', kode);
}

function definisiDari(t) {
    if (!t) return null;
    if (t.definisi) return t.definisi;
    if (t.Definisi_Json) {
        try {
            return typeof t.Definisi_Json === 'string' ? JSON.parse(t.Definisi_Json) : t.Definisi_Json;
        } catch {
            return null;
        }
    }
    return null;
}

function infoTemplate(t) {
    const d = definisiDari(t);
    const m = (d?.mode ?? t?.mode ?? t?.Mode ?? 'ringkasan').toLowerCase();
    const isRingkasan = m === 'ringkasan';
    const dimensiList = Array.isArray(d?.dimensi) ? d.dimensi : [];
    const ukuranList = Array.isArray(d?.ukuran) ? d.ukuran : [];
    const kolomList = Array.isArray(d?.kolom) ? d.kolom : [];
    const saringanList = Array.isArray(d?.saringan) ? d.saringan : [];
    const hasGrandTotal = !!d?.opsi?.grand_total;

    return {
        mode: m,
        isRingkasan,
        dimensiCount: dimensiList.length,
        dimensiList,
        ukuranCount: ukuranList.length,
        ukuranList,
        kolomCount: kolomList.length,
        kolomList,
        saringanCount: saringanList.length,
        saringanList,
        hasGrandTotal,
        totalKolomEstimasi: isRingkasan ? (dimensiList.length + ukuranList.length) : kolomList.length,
    };
}

function ringkasanStruktur(t) {
    const info = infoTemplate(t);
    if (info.isRingkasan) {
        return `${info.dimensiCount} Dim, ${info.ukuranCount} Ukuran`;
    }
    return `${info.kolomCount} Kolom`;
}

/* Template resmi datang dari kode (kunci huruf kecil), template pengguna dari
   baris database (kolom PascalCase). Dinormalkan di sini alih-alih memaksa
   salah satunya berubah bentuk. */
const kodeDari = (t) => t.kode ?? t.Kode;
const namaDari = (t) => t.nama ?? t.Nama;
const deskripsiDari = (t) => t.deskripsi ?? t.Deskripsi;
const datasetDari = (t) => t.dataset ?? t.Dataset_Key;
const modeDari = (t) => (t.mode ?? t.Mode ?? '').toLowerCase();
</script>

<style scoped>
.rs-galeri {
    display: flex;
    flex-direction: column;
}

.rs-galeri-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.rs-tabs {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
    padding: 0.25rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-pill, 9999px);
    width: fit-content;
}

.rs-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid transparent;
    background: transparent;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.3rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 180ms ease;
}

.rs-tab:hover {
    color: var(--hcis-text-strong, #1e293b);
    background: rgba(var(--hcis-slate-900-rgb, 15 23 42) / 0.04);
}

.rs-tab.aktif {
    background: var(--hcis-surface, #ffffff);
    border-color: var(--hcis-border-medium, #cbd5e1);
    color: var(--hcis-brand-700, #4338ca);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.rs-tab-icon {
    font-size: 0.85rem;
}

.rs-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    background: rgba(var(--hcis-slate-900-rgb, 15 23 42) / 0.06);
    color: currentColor;
}

.rs-tab.aktif .rs-badge {
    background: var(--hcis-surface-brand, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
}

.rs-galeri-search-wrap {
    display: flex;
    align-items: center;
}

.rs-galeri-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.rs-view-switcher {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 2px;
}

.rs-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: none;
    background: transparent;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.25rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 150ms ease;
}

.rs-view-btn:hover {
    color: var(--hcis-text-strong, #1e293b);
}

.rs-view-btn.is-active {
    background: var(--hcis-surface, #ffffff);
    color: var(--hcis-brand-700, #4338ca);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}

.rs-kartu-specs-box {
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.45rem 0.55rem;
}

.rs-spec-chips {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.rs-spec-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
    line-height: 1.3;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.rs-spec-chip strong {
    color: var(--hcis-text-strong, #1e293b);
    font-weight: 700;
}

.rs-spec-chip.is-highlight {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
    font-weight: 600;
}

.rs-spec-chip.has-filter {
    background: #fffbeb;
    border-color: #fcd34d;
    color: #b45309;
}

/* ══ LIST VIEW STYLES ══ */
.rs-list-container {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.rs-list-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 3px 10px -2px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.rs-list-row:hover {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.16), 0 2px 6px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
}

.rs-list-icon-box {
    width: 2.1rem;
    height: 2.1rem;
    min-width: 2.1rem;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.rs-list-icon-box.is-resmi {
    background: var(--hcis-surface-brand, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
    border: 1px solid var(--hcis-border-brand, #c7d2fe);
}

.rs-list-icon-box.is-saya {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

.rs-list-icon-box.is-dibagikan {
    background: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
}

.rs-list-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1.5;
    min-width: 200px;
}

.rs-list-title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.rs-list-title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
}

.rs-list-meta-text {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 500;
    text-transform: capitalize;
}

.rs-list-desk {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.rs-list-specs {
    display: flex;
    align-items: center;
    flex: 1.2;
    min-width: 180px;
    justify-content: flex-start;
}

.rs-list-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    margin-left: auto;
}

.rs-search-box {
    position: relative;
    display: flex;
    align-items: center;
    min-width: 220px;
}

.rs-search-icon {
    position: absolute;
    left: 0.75rem;
    font-size: 0.8rem;
    color: var(--hcis-text-muted, #94a3b8);
    pointer-events: none;
}

.rs-search-input {
    width: 100%;
    height: 2.1rem;
    padding: 0.35rem 2rem 0.35rem 2.1rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #1e293b);
    outline: none;
    transition: all 150ms ease;
}

.rs-search-input:focus {
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 2px var(--hcis-focus-ring, rgba(99, 102, 241, 0.2));
}

.rs-search-clear {
    position: absolute;
    right: 0.65rem;
    border: none;
    background: transparent;
    color: var(--hcis-text-muted, #94a3b8);
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    padding: 0;
}

.rs-search-clear:hover {
    color: var(--hcis-text-strong, #1e293b);
}

.rs-kosong {
    padding: 2rem;
    text-align: center;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rs-kosong-wrap {
    padding: 1.5rem 0.5rem;
}

.rs-kartu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 0.85rem;
    align-items: stretch;
}

.rs-kartu {
    background: var(--hcis-surface, #ffffff) !important;
    border: 1px solid var(--hcis-border-medium, #cbd5e1) !important;
    border-radius: var(--hcis-radius-xl, 14px) !important;
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03) !important;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
}

.rs-kartu:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(99, 102, 241, 0.14), 0 2px 6px rgba(15, 23, 42, 0.04) !important;
    border-color: var(--hcis-brand-500, #6366f1) !important;
}

.rs-kartu :deep(.hcis-card__header) {
    padding: 0.75rem 0.85rem 0.45rem 0.85rem !important;
    align-items: flex-start;
}

.rs-kartu :deep(.hcis-card__icon-box) {
    width: 2rem !important;
    height: 2rem !important;
    min-width: 2rem !important;
    font-size: 0.95rem !important;
    border-radius: 8px !important;
}

.rs-kartu-row :deep(.hcis-card__body) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem !important;
    flex-wrap: wrap;
    width: 100%;
}

.rs-kartu-title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 700;
    line-height: 1.3;
    color: var(--hcis-text-strong, #1e293b);
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.rs-kartu-meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
    margin-top: 0.1rem;
}

.rs-meta-tag {
    font-weight: 600;
    text-transform: capitalize;
}

.rs-meta-dot {
    opacity: 0.4;
}

.rs-meta-mode {
    text-transform: capitalize;
}

.rs-kartu :deep(.hcis-card__body) {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.25rem 0.85rem 0.65rem 0.85rem !important;
    gap: 0.45rem;
}

.rs-kartu-content {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.rs-kartu-desk {
    margin: 0;
    font-size: 0.76rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, #64748b);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 2.15rem;
}

.rs-spec-chips {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.rs-spec-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: 5px;
    padding: 0.1rem 0.35rem;
    line-height: 1.25;
}

.rs-spec-chip strong {
    color: var(--hcis-text-strong, #1e293b);
    font-weight: 700;
}

.rs-spec-chip.is-highlight {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #15803d;
    font-weight: 600;
}

.rs-spec-chip.has-filter {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
}

.rs-kartu :deep(.hcis-card__footer) {
    width: 100%;
    display: flex;
    box-sizing: border-box;
    padding: 0.5rem 0.85rem 0.65rem 0.85rem !important;
    border-top: 1px solid var(--hcis-border-subtle, #f1f5f9) !important;
    background: rgba(248, 250, 252, 0.5);
    border-bottom-left-radius: var(--hcis-radius-xl, 14px);
    border-bottom-right-radius: var(--hcis-radius-xl, 14px);
}

.rs-kartu-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    width: 100%;
}

.rs-kartu-footer-primary {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex: 1;
    min-width: 0;
}

.rs-kartu-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}

.rs-btn-icon {
    width: 1.85rem !important;
    height: 1.85rem !important;
    min-width: 1.85rem !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: var(--hcis-radius-md, 8px) !important;
}

.rs-btn-icon i {
    font-size: 0.82rem;
}

.rs-btn-hapus {
    color: var(--hcis-danger-600, #dc2626) !important;
}

.rs-btn-hapus:hover {
    background: #fef2f2 !important;
    color: var(--hcis-danger-700, #b91c1c) !important;
}

.rs-meta {
    font-size: 0.72rem;
    color: var(--hcis-text-subtle, #94a3b8);
    display: none; /* Disembunyikan di footer karena sudah ada di header meta row, tapi dipertahankan untuk test/fallback */
}

.rs-aksi {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
}

.rs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background: var(--hcis-surface, #ffffff);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.25rem 0.6rem;
    color: var(--hcis-text-strong, #1e293b);
    cursor: pointer;
    transition: all 150ms ease;
}

.rs-btn:hover {
    border-color: var(--hcis-brand-400, #818cf8);
    color: var(--hcis-brand-700, #4338ca);
    background: var(--hcis-surface-soft, #f8fafc);
}

.rs-btn.utama {
    background: var(--hcis-surface-brand, #eef2ff);
    border-color: var(--hcis-border-brand, #c7d2fe);
    color: var(--hcis-brand-700, #4338ca);
}

.rs-btn.utama:hover {
    background: #e0e7ff;
    border-color: var(--hcis-brand-500, #6366f1);
}

.rs-btn-terapkan {
    background: var(--hcis-brand-600, #4f46e5);
    border-color: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.25);
}

.rs-btn-terapkan:hover {
    background: var(--hcis-brand-700, #4338ca);
    border-color: var(--hcis-brand-700, #4338ca);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

.rs-meta-struktur {
    font-weight: 600;
    color: var(--hcis-brand-700, #4338ca);
    background: var(--hcis-surface-brand, #eef2ff);
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-size: 0.68rem;
}

.rs-btn.hapus {
    color: var(--hcis-danger-600, #dc2626) !important;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: var(--hcis-surface, #ffffff);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 150ms ease;
}

.rs-btn.hapus:hover {
    background: #fef2f2 !important;
    border-color: #fca5a5 !important;
    color: var(--hcis-danger-700, #b91c1c) !important;
    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.15);
}
</style>
