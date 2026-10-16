<template>
    <div class="pa-picker">
        <!-- Target Destination Context Banner -->
        <div v-if="kompetensiWajib.length || namaSasaran" class="pa-picker-target-banner">
            <div class="pa-target-info">
                <span class="pa-target-kicker">TARGET PEMETAAN DEFAULT</span>
                <div class="pa-target-main">
                    <strong class="pa-target-name">{{ namaSasaran || 'Sasaran Pemetaan' }}</strong>
                    <span v-if="totalWajib > 0" class="pa-target-count-badge">
                        {{ totalWajib }} Kompetensi Wajib
                    </span>
                </div>
            </div>

            <!-- Mandatory Dimensions List -->
            <div v-if="kompetensiWajib.length" class="pa-target-reqs">
                <span class="pa-reqs-label">Dimensi Wajib Level:</span>
                <div class="pa-reqs-chips">
                    <span
                        v-for="g in kompetensiWajib"
                        :key="g.dimensi_kode"
                        class="pa-req-chip"
                        :class="getDimensionChipClass(g.dimensi_nama || g.dimensi_kode)"
                        :title="`Kompetensi: ${(g.kompetensi || []).map((c) => c.kode).join(', ')}`"
                    >
                        {{ g.dimensi_nama || g.dimensi_kode }} ({{ g.kompetensi?.length || 0 }})
                    </span>
                </div>
            </div>
        </div>

        <!-- Search & Filter Tabs Toolbar -->
        <div class="pa-picker-toolbar">
            <div class="pa-picker-filter-tabs">
                <button
                    type="button"
                    class="pa-picker-tab"
                    :class="{ active: filterTab === 'semua' }"
                    @click="filterTab = 'semua'"
                >
                    <span>Semua Paket</span>
                    <span class="pa-tab-badge">{{ paket.length }}</span>
                </button>
                <button
                    type="button"
                    class="pa-picker-tab"
                    :class="{ active: filterTab === 'cocok' }"
                    @click="filterTab = 'cocok'"
                >
                    <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                    <span>Rekomendasi (Sesuai Level)</span>
                    <span class="pa-tab-badge is-success">{{ paketCocokCount }}</span>
                </button>
            </div>

            <div class="pa-picker-search-wrap">
                <HcisField
                    v-model="kataKunci"
                    size="sm"
                    placeholder="Cari nama ujian, paket, kode master, nama bagian..."
                    leading-icon="search"
                />
            </div>
        </div>


        <!-- Loading State Skeleton -->
        <div v-if="memuat" class="pa-picker-skeleton-list">
            <HcisSkeleton v-for="n in 3" :key="`skel-p-${n}`" height="6.5rem" rounded="md" />
        </div>

        <!-- Empty State -->
        <HcisEmptyState
            v-else-if="!paketTersaring.length"
            variant="card"
            sentiment="neutral"
            size="md"
            animated
            title="Tidak Ada Ujian yang Sesuai"
            :description="kataKunci ? `Tidak ditemukan ujian dengan kata kunci '${kataKunci}'.` : 'Tidak ada ujian yang cocok dengan tab yang aktif.'"
            icon="search"
            class="my-3"
        >
            <template #actions>
                <HcisButton
                    label="Reset Filter"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="kataKunci = ''; filterTab = 'semua'"
                />
            </template>
        </HcisEmptyState>

        <!-- Packages Cards Grid -->
        <div v-else class="pa-picker-cards-grid">
            <div
                v-for="p in paketTersaring"
                :key="p.Id_Master_Ujian"
                class="pa-package-card"
                :class="{
                    'is-eligible': bisaDipilih(p),
                    'is-disabled': !bisaDipilih(p),
                    'is-selected': p.Id_Master_Ujian === kodeTerpilih,
                    'is-expanded': terbuka === p.Id_Master_Ujian,
                }"
            >
                <div class="pa-card-head">
                    <div class="pa-card-identity">
                        <div class="pa-card-top-row">
                            <span class="pa-card-code-badge">{{ p.Id_Master_Ujian }}</span>
                            <HcisBadge
                                v-if="p.Id_Master_Ujian === kodeTerpilih"
                                label="Sedang Terpasang"
                                variant="brand"
                                size="2xs"
                            />
                            <HcisBadge
                                v-if="dipakaiSebaris[p.Id_Master_Ujian]"
                                :label="`Dipakai ${dipakaiSebaris[p.Id_Master_Ujian]}`"
                                variant="purple"
                                appearance="subtle"
                                size="2xs"
                            />
                            <HcisBadge
                                v-else-if="bisaDipilih(p)"
                                label="Rekomendasi (Match)"
                                variant="success"
                                appearance="subtle"
                                size="2xs"
                                dot
                            />
                            <HcisBadge
                                v-else
                                label="Belum Sepadan"
                                variant="neutral"
                                appearance="subtle"
                                size="2xs"
                            />
                        </div>
                        <h4 class="pa-card-title">{{ p.Nama_Ujian }}</h4>
                    </div>

                    <div class="pa-card-stat-pill">
                        <strong>{{ p.jumlah_bagian ?? (p.Bagian || []).length }}</strong>
                        <span>Bagian</span>
                        <small>{{ perAlatTes(p).length }} alat tes</small>
                    </div>
                </div>

                <!-- Alat Tes List -->
                <div v-if="perAlatTes(p).length" class="pa-card-dimensions">
                    <span
                        v-for="a in perAlatTes(p)"
                        :key="a.id"
                        class="pa-card-dim-chip dim-indigo"
                        :title="`${a.bagian.length} bagian`"
                    >
                        {{ a.nama }} ({{ a.bagian.length }})
                    </span>
                </div>

                <!-- Identitas paket ujian di HC Learn -->
                <div class="pa-card-breakdown">
                    <span class="pa-breakdown-item">
                        <HcisIcon name="box-seam" size="2xs" />
                        {{ p.Nama_Paket || 'Paket tanpa nama' }}
                    </span>
                    <template v-if="p.Kode_Ujian">
                        <span class="pa-breakdown-dot">•</span>
                        <span class="pa-breakdown-item">
                            <HcisIcon name="upc-scan" size="2xs" />
                            {{ p.Kode_Ujian }}
                        </span>
                    </template>
                </div>

                <!-- Ineligible Reason Box -->
                <div v-if="!bisaDipilih(p)" class="pa-card-ineligible-box">
                    <HcisIcon name="info-circle-fill" size="xs" color="muted" />
                    <span>{{ alasanTakBisa(p) }}</span>
                </div>

                <!--
                    KESEPADANAN CACAH, bukan cakupan kompetensi.

                    Kompetensi bukan lagi milik HC Learn: bagiannya dilempar tanpa label, dan
                    yang memetakannya ke kompetensi adalah admin di langkah berikutnya.
                    Satu-satunya perbandingan yang punya jawaban SEKARANG adalah cacahnya -
                    dan karena satu bagian mewakili tepat satu kompetensi, keduanya harus sama.
                -->
                <div
                    v-else-if="sepadan(p).wajib > 0"
                    class="pa-card-coverage-wrapper"
                >
                    <div class="pa-card-coverage-info" :class="{ 'is-incomplete': !sepadan(p).sepadan }">
                        <HcisIcon
                            :name="sepadan(p).sepadan ? 'check-circle-fill' : 'exclamation-circle-fill'"
                            size="2xs"
                        />
                        <span>
                            {{ sepadan(p).bagian }} bagian untuk {{ sepadan(p).wajib }} kompetensi wajib level
                            <template v-if="sepadan(p).sepadan"> &mdash; siap dilabeli</template>
                            <template v-else> ({{ labelSelisih(p) }})</template>
                        </span>
                    </div>
                    <div class="pa-card-progress-track">
                        <div
                            class="pa-card-progress-bar"
                            :class="{ 'is-full': sepadan(p).sepadan }"
                            :style="{ width: lebarBar(p) }"
                        />
                    </div>
                </div>

                <!-- Expandable Sections Accordion -->
                <div v-if="(p.Bagian || []).length" class="pa-card-details-accordion">
                    <button
                        type="button"
                        class="pa-card-accordion-btn"
                        @click="terbuka = terbuka === p.Id_Master_Ujian ? null : p.Id_Master_Ujian"
                    >
                        <HcisIcon
                            :name="terbuka === p.Id_Master_Ujian ? 'chevron-up' : 'chevron-down'"
                            size="2xs"
                        />
                        <span>
                            {{ terbuka === p.Id_Master_Ujian ? 'Tutup Preview' : 'Preview' }}
                            {{ p.Bagian.length }} Bagian
                            ({{ perAlatTes(p).length }} alat tes)
                        </span>
                    </button>

                    <!--
                        Bagian dikelompokkan per ALAT TES. Nama bagian berulang antar alat tes
                        ("Analytical Thinking" muncul di dua alat), jadi tanpa pengelompokan
                        keduanya terbaca sebagai baris kembar - padahal identitasnya, yaitu
                        pasangan (alat tes, bank soal), memang berbeda.
                    -->
                    <div v-if="terbuka === p.Id_Master_Ujian" class="pa-card-items-list">
                        <template v-for="a in perAlatTes(p)" :key="a.id">
                            <div class="pa-item-group-head">{{ a.nama }}</div>
                            <div
                                v-for="b in a.bagian"
                                :key="kunciBagian(b)"
                                class="pa-card-item-row"
                            >
                                <span class="pa-item-number">&bull;</span>
                                <div class="pa-item-content">
                                    <div class="pa-item-title-row">
                                        <span v-if="b.kode_soal" class="pa-item-code">[{{ b.kode_soal }}]</span>
                                        <strong class="pa-item-name">{{ b.nama || 'Bagian tanpa nama' }}</strong>
                                    </div>
                                    <div class="pa-item-sub">
                                        <!-- Mapping TERSIMPAN untuk sel ini bila ada; bukan teks mati. -->
                                        <span v-if="labelUntuk(p, b)" class="pa-item-type pa-item-label" data-uji="picker-label">
                                            &rarr; <strong>{{ labelUntuk(p, b).kode }}</strong> {{ labelUntuk(p, b).nama }}
                                        </span>
                                        <span v-else-if="punyaLabel(p)" class="pa-item-type">
                                            Belum berlabel untuk sel ini &mdash; lengkapi di Master &middot; Matriks Soal
                                        </span>
                                        <span v-else class="pa-item-type">
                                            Belum berlabel &mdash; kompetensinya ditetapkan pada langkah pelabelan
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Action Button Footer -->
                <div class="pa-card-action-wrap">
                    <HcisButton
                        v-if="bisaDipilih(p)"
                        :label="p.Id_Master_Ujian === kodeTerpilih ? 'Paket Saat Ini Digunakan' : 'Gunakan Paket Ini'"
                        :variant="p.Id_Master_Ujian === kodeTerpilih ? 'secondary' : 'primary'"
                        size="sm"
                        :disabled="p.Id_Master_Ujian === kodeTerpilih"
                        :trailing-icon="p.Id_Master_Ujian === kodeTerpilih ? 'check-lg' : 'arrow-right'"
                        class="pa-card-select-btn"
                        :loading="sibuk === p.Id_Master_Ujian"
                        @click="$emit('pilih', p)"
                    />
                    <HcisButton
                        v-else
                        label="Tidak Memenuhi Syarat"
                        variant="ghost"
                        size="sm"
                        disabled
                        class="pa-card-select-btn"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, shallowRef } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisSkeleton,
} from '@/components/ui';
import {
    alasanTakBisa as alasanTakBisaUtil,
    bisaDipilih as bisaDipilihUtil,
    himpunanKompetensiWajib,
    kesepadanan,
    kunciBagian,
    perAlatTes as perAlatTesUtil,
} from '../utils/kelayakanPaket.js';

defineOptions({ name: 'PaPaketSoalPicker' });

const props = defineProps({
    /** Paket dari katalog HC Learn. */
    paket: { type: Array, default: () => [] },

    /**
     * Kompetensi wajib sasaran, dikelompokkan per dimensi:
     * `[{ dimensi_kode, dimensi_nama, kompetensi: [{ kode, nama }] }]`
     */
    kompetensiWajib: { type: Array, default: () => [] },

    namaSasaran: { type: String, default: '' },
    kodeTerpilih: { type: String, default: '' },

    /**
     * Ujian yang sudah dipakai sel LAIN pada baris (level) yang sama, sebagai
     * `{ [Id_Master_Ujian]: 'Nama Kategori, Nama Kategori' }`.
     *
     * Bukan hiasan: satu baris memakai satu ujian yang sama di beberapa kategori penilai
     * adalah kejadian biasa, dan pemetaan bagiannya sudah ada di sel tetangga — memilih
     * yang sudah terpakai berarti pelabelannya tinggal diperiksa, bukan diketik ulang
     * dua puluh satu baris. Tanpa penanda ini admin tidak punya cara tahu yang mana.
     */
    dipakaiSebaris: { type: Object, default: () => ({}) },
    sumber: { type: String, default: '' },
    memuat: { type: Boolean, default: false },
    sibuk: { type: String, default: '' },

    /**
     * Mapping bagian → kompetensi yang SUDAH tersimpan untuk sel ini (Master · Matriks Soal):
     * `{ id_master_ujian, peta: { 'indikator|soal': { kode, nama } } }`. null bila sel belum
     * punya mapping — pratinjau lalu menulis "Belum berlabel".
     */
    labelBagian: { type: Object, default: null },
});

const punyaLabel = (p) => props.labelBagian != null && String(props.labelBagian.id_master_ujian) === String(p.Id_Master_Ujian);
const labelUntuk = (p, b) => (punyaLabel(p) ? props.labelBagian.peta?.[kunciBagian(b)] ?? null : null);

defineEmits(['pilih']);

const terbuka = ref(null);
const filterTab = ref('semua'); // 'semua' | 'cocok'
const kataKunci = ref('');

const totalWajib = computed(() => himpunanKompetensiWajib(props.kompetensiWajib).size);

// Single-pass memoization for package metadata (eligibility, coverage, competencies)
const metaPaketMap = computed(() => {
    const map = new Map();
    const wajib = props.kompetensiWajib;
    for (const p of props.paket || []) {
        const id = p.Id_Master_Ujian;
        const eligible = bisaDipilihUtil(p, wajib);
        const cocok = kesepadanan(p, wajib);
        const reason = eligible ? '' : alasanTakBisaUtil(p, wajib);
        map.set(id, {
            eligible,
            sepadan: cocok,
            alatTes: perAlatTesUtil(p),
            alasan: reason,
        });
    }
    return map;
});

const META_KOSONG = { eligible: false, sepadan: { bagian: 0, wajib: 0, sepadan: false, selisih: 0 }, alatTes: [], alasan: '' };
const getMeta = (p) => metaPaketMap.value.get(p.Id_Master_Ujian) || META_KOSONG;
const bisaDipilih = (p) => getMeta(p).eligible;
const sepadan = (p) => getMeta(p).sepadan;
const perAlatTes = (p) => getMeta(p).alatTes;
const alasanTakBisa = (p) => getMeta(p).alasan;

/* Selisihnya dikalimatkan di sini, bukan di templat: ternary bersarang di dalam
   interpolasi templat adalah bentuk yang paling sering salah dibaca saat disunting. */
function labelSelisih(p) {
    const { selisih } = sepadan(p);

    return selisih > 0 ? `berlebih ${selisih}` : `kurang ${-selisih}`;
}

function lebarBar(p) {
    const { bagian, wajib } = sepadan(p);

    return `${Math.min(100, Math.round((bagian / (wajib || 1)) * 100))}%`;
}

function getDimensionChipClass(dim) {
    const d = String(dim || '').toUpperCase();
    if (d.includes('MANAJER')) return 'dim-indigo';
    if (d.includes('CORE') || d.includes('NILAI')) return 'dim-emerald';
    if (d.includes('BEHAVIOR') || d.includes('PERILAKU')) return 'dim-cyan';
    if (d.includes('KUALITATIF') || d.includes('QUAL')) return 'dim-purple';
    return 'dim-neutral';
}

const paketCocokCount = computed(() => {
    let count = 0;
    for (const [, m] of metaPaketMap.value) {
        if (m.eligible) count++;
    }
    return count;
});

const paketTersaring = computed(() => {
    let list = props.paket || [];

    if (filterTab.value === 'cocok') {
        list = list.filter((p) => metaPaketMap.value.get(p.Id_Master_Ujian)?.eligible);
    }

    const q = kataKunci.value.trim().toLowerCase();
    if (q) {
        list = list.filter((p) =>
            String(p.Nama_Ujian || '').toLowerCase().includes(q) ||
            String(p.Nama_Paket || '').toLowerCase().includes(q) ||
            String(p.Id_Master_Ujian || '').toLowerCase().includes(q) ||
            String(p.Kode_Ujian || '').toLowerCase().includes(q) ||
            (Array.isArray(p.Bagian) && p.Bagian.some((b) =>
                String(b.nama || '').toLowerCase().includes(q) ||
                String(b.kode_soal || '').toLowerCase().includes(q) ||
                String(b.nama_alat_tes || '').toLowerCase().includes(q)
            ))
        );
    }

    return list;
});
</script>

<style scoped>
.pa-picker {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    width: 100%;
}

/* Target Context Banner */
.pa-picker-target-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #f8faff;
    border: 1px solid #c7d2fe;
    flex-wrap: wrap;
}

.pa-target-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-target-kicker {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #4338ca;
}

.pa-target-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-target-name {
    font-size: 0.96rem;
    color: #1e1b4b;
    font-weight: 750;
}

.pa-target-count-badge {
    font-size: 0.7rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
    background: #ffffff;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    border: 1px solid #c7d2fe;
}

.pa-target-reqs {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-reqs-label {
    font-size: 0.65rem;
    font-weight: 750;
    color: #4338ca;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-reqs-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-req-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.pa-req-chip.dim-indigo {
    background: #e0e7ff;
    color: #3730a3;
}

.pa-req-chip.dim-emerald {
    background: #d1fae5;
    color: #065f46;
}

.pa-req-chip.dim-cyan {
    background: #cffafe;
    color: #155e75;
}

.pa-req-chip.dim-purple {
    background: #f3e8ff;
    color: #6b21a8;
}

.pa-req-chip.dim-neutral {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
}

/* Toolbar */
.pa-picker-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-picker-filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-picker-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-picker-tab:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.pa-picker-tab.active {
    background: #eef2ff;
    color: #4338ca;
    border-color: #818cf8;
}

.pa-tab-badge {
    font-size: 0.68rem;
    background: #f1f5f9;
    padding: 0.1rem 0.35rem;
    border-radius: 9999px;
    font-weight: 750;
}

.pa-tab-badge.is-success {
    background: #dcfce7;
    color: #15803d;
}

.pa-picker-search-wrap {
    flex: 1;
    min-width: 14rem;
}

/* Dimension Filter Row */
.pa-picker-dim-filter-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-dim-label {
    font-size: 0.7rem;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-dim-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-dim-btn {
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-dim-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.pa-dim-btn.active {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
}

.pa-picker-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-picker-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    padding: 3rem 1.5rem;
    color: var(--hcis-text-muted, #64748b);
    text-align: center;
}

.pa-picker-empty strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.95rem;
}

.pa-picker-empty span {
    font-size: 0.78rem;
}

/* Cards Grid */
.pa-picker-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.15rem;
    max-height: calc(74vh - 160px);
    min-height: 400px;
    overflow-y: auto;
    padding: 0.35rem;
}

.pa-picker-cards-grid::-webkit-scrollbar {
    width: 6px;
}

.pa-picker-cards-grid::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.pa-picker-cards-grid::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.pa-package-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1.5px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    box-sizing: border-box;
}

.pa-package-card.is-eligible {
    border-color: #cbd5e1;
}

.pa-package-card.is-eligible:hover {
    border-color: #4f46e5;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.12);
}

.pa-package-card.is-selected {
    border-color: #4f46e5;
    background: #f8faff;
    box-shadow: 0 0 0 1.5px #4f46e5;
}

.pa-package-card.is-disabled {
    background: #f8fafc;
    border-color: #e2e8f0;
    opacity: 0.8;
}

.pa-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}

.pa-card-identity {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
    flex: 1;
}

.pa-card-top-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-card-code-badge {
    font-size: 0.72rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
}

.pa-card-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
}

.pa-card-stat-pill {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    background: #f1f5f9;
    flex-shrink: 0;
}

.pa-card-stat-pill strong {
    font-size: 1.05rem;
    color: #1e1b4b;
    font-variant-numeric: tabular-nums;
}

.pa-card-stat-pill span {
    font-size: 0.62rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 700;
}

.pa-card-stat-pill small {
    display: block;
    font-size: 0.68rem;
    font-weight: 400;
    color: var(--hcis-text-muted, #64748b);
}

.pa-card-dimensions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pa-card-dim-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.pa-card-dim-chip.dim-indigo {
    background: #e0e7ff;
    color: #3730a3;
}

.pa-card-dim-chip.dim-emerald {
    background: #d1fae5;
    color: #065f46;
}

.pa-card-dim-chip.dim-cyan {
    background: #cffafe;
    color: #155e75;
}

.pa-card-dim-chip.dim-purple {
    background: #f3e8ff;
    color: #6b21a8;
}

.pa-card-dim-chip.dim-neutral {
    background: #f1f5f9;
    color: #334155;
}

.pa-card-breakdown {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    color: #64748b;
}

.pa-breakdown-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-breakdown-dot {
    color: #cbd5e1;
}

.pa-card-ineligible-box {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-size: 0.73rem;
    line-height: 1.4;
}

/* Coverage Wrapper */
.pa-card-coverage-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-card-coverage-info {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    font-size: 0.72rem;
    color: #15803d;
    font-weight: 600;
}

.pa-card-coverage-info.is-incomplete {
    color: #b45309;
}

.pa-card-progress-track {
    width: 100%;
    height: 5px;
    border-radius: 9999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-card-progress-bar {
    height: 100%;
    background: #f59e0b;
    border-radius: 9999px;
    transition: width 300ms ease;
}

.pa-card-progress-bar.is-full {
    background: #10b981;
}

/* Accordion */
.pa-card-details-accordion {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-top: 1px dashed #e2e8f0;
    padding-top: 0.45rem;
}

.pa-card-accordion-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: none;
    border: none;
    color: #4f46e5;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    padding: 0.15rem 0;
}

.pa-card-accordion-btn:hover {
    color: #3730a3;
    text-decoration: underline;
}

.pa-card-items-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 16rem;
    overflow-y: auto;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.pa-card-item-row {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.74rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid #f1f5f9;
}

.pa-item-number {
    color: #64748b;
    font-weight: 700;
    min-width: 1.2rem;
}

.pa-item-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
}

.pa-item-title-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-item-code {
    font-size: 0.68rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
}

.pa-item-name {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.76rem;
}

.pa-item-sub {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.68rem;
    color: #64748b;
}

.pa-item-dim {
    color: #4338ca;
    font-weight: 600;
}

.pa-card-action-wrap {
    display: flex;
    width: 100%;
    margin-top: auto;
    padding-top: 0.35rem;
}

.pa-card-select-btn {
    width: 100%;
}

.pa-card-select-btn :deep(.hcis-btn) {
    width: 100%;
    justify-content: center;
}

@media (max-width: 640px) {
    .pa-picker-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-picker-search-wrap {
        width: 100%;
    }
    .pa-picker-cards-grid {
        grid-template-columns: 1fr;
    }
}

.pa-item-label {
    color: #0f172a;
}
</style>
