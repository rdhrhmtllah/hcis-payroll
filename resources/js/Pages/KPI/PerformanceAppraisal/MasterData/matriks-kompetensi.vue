<template>
    <main class="pa-mk-page">
        <HcisHeader
            title="Matriks Kompetensi"
            kicker="PERFORMANCE APPRAISAL · MASTER DATA"
            description="Tentukan per level jabatan: kompetensi mana yang dinilai, dan tingkat berapa yang dituntut. Expected Behaviour yang dibaca penilai mengikuti tingkat yang dipilih di sini."
            icon="grid-3x3-gap-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-3"
        >
            <template #badge>
                <HcisBadge
                    v-if="levelBerubah.length"
                    :label="`${levelBerubah.length} level belum disimpan`"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    dot
                />
            </template>
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton label="Segarkan" variant="secondary" size="sm" leading-icon="arrow-clockwise" :loading="memuat" @click="muat" />
                    <HcisButton
                        v-if="levelBerubah.length > 1"
                        :label="`Simpan semua (${levelBerubah.length})`"
                        variant="primary"
                        size="sm"
                        leading-icon="floppy"
                        :loading="menyimpanSemua"
                        data-uji="mk-simpan-semua"
                        @click="simpanSemua"
                    />
                </div>
            </template>
            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Master Data Performance Appraisal">
                    <a
                        v-for="item in masterNavList"
                        :key="item.slug"
                        :href="item.href"
                        class="pa-admin-switcher__item"
                        :class="{ 'pa-admin-switcher__item--active': item.slug === 'syarat-kompetensi' }"
                    >
                        <HcisIcon :name="item.icon" size="2xs" />
                        <span>{{ item.label }}</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <HcisResultState
            v-if="galat && !memuat && !data"
            status="danger"
            variant="glass"
            layout="compact"
            title="Matriks kompetensi gagal dimuat"
            :description="galat"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <div v-else-if="memuat && !data" class="pa-mk-kerangka">
            <HcisSkeleton height="28rem" rounded="xl" />
            <HcisSkeleton height="28rem" rounded="xl" />
        </div>

        <div v-else-if="data" class="pa-mk-tata">
            <!-- KIRI: daftar level -->
            <aside class="pa-mk-kiri">
                <HcisField id="mk-cari-level" v-model="cariLevel" size="sm" leading-icon="search" placeholder="Cari level…" clearable />
                <HcisSwitch v-model="tampilNonPa" size="sm" active-text="Tampilkan level non-PA" data-uji="mk-non-pa" />
                <ul class="pa-mk-level" role="listbox" aria-label="Level jabatan">
                    <li v-for="l in levelTampil" :key="l.id">
                        <button
                            type="button"
                            role="option"
                            :aria-selected="l.id === levelAktif"
                            :class="['pa-mk-level__item', { 'is-aktif': l.id === levelAktif, 'is-nonpa': l.dikecualikan }]"
                            data-uji="mk-level"
                            @click="pilihLevel(l.id)"
                        >
                            <div class="pa-mk-level__teks">
                                <strong>{{ l.nama }}</strong>
                                <small>{{ ringkasLevel(l.id).dinilai }} dari {{ data.kompetensi.length }} dinilai<template v-if="l.dikecualikan"> · non-PA</template></small>
                            </div>
                            <span v-if="berubah(l.id)" class="pa-mk-titik is-ubah" title="Belum disimpan" />
                            <span v-else-if="ringkasLevel(l.id).tanpaTingkat" class="pa-mk-titik is-galat" :title="`${ringkasLevel(l.id).tanpaTingkat} kompetensi belum dipilih tingkatnya`" />
                        </button>
                    </li>
                </ul>
                <p v-if="!levelTampil.length" class="pa-mk-catatan">Tidak ada level yang cocok.</p>
            </aside>

            <!-- KANAN: editor level terpilih -->
            <section v-if="levelTerpilih" class="pa-mk-kanan" data-uji="mk-editor">
                <div class="pa-mk-kepala">
                    <div>
                        <h3>{{ levelTerpilih.nama }}</h3>
                        <p>
                            <b>{{ ringkasLevel(levelAktif).dinilai }}</b> kompetensi dinilai
                            <template v-if="ringkasLevel(levelAktif).tanpaTingkat"> · <span class="pa-mk-merah">{{ ringkasLevel(levelAktif).tanpaTingkat }} belum dipilih tingkatnya</span></template>
                            <template v-if="levelTerpilih.dikecualikan"> · level ini dikecualikan dari PA</template>
                        </p>
                    </div>
                    <div class="pa-mk-kepala__aksi d-flex align-items-center gap-3">
                        <HcisButton
                            :label="semuaJenisTerbuka ? 'Tutup semua' : 'Buka semua'"
                            variant="secondary"
                            size="sm"
                            :leading-icon="semuaJenisTerbuka ? 'chevron-contract' : 'chevron-expand'"
                            data-uji="mk-toggle-semua"
                            @click="toggleSemuaJenis"
                        />
                        <HcisButton label="Salin dari level lain" variant="secondary" size="sm" leading-icon="files" data-uji="mk-salin" @click="bukaSalin" />
                    </div>
                </div>

                <HcisAlert v-if="galatSimpan" variant="danger" appearance="soft" size="sm" :title="galatSimpan" class="mb-2" data-uji="mk-galat" />
                <HcisAlert v-for="p in peringatan" :key="p" variant="warning" appearance="soft" size="sm" :title="p" class="mb-2" />

                <section v-for="j in jenisList" :key="j.id" class="pa-mk-jenis" :data-uji="`mk-jenis-${j.kode}`">
                    <header
                        class="pa-mk-jenis__kepala"
                        :class="{ 'is-terbuka': isJenisBuka(j.id) }"
                        @click="toggleJenis(j.id)"
                    >
                        <button
                            type="button"
                            class="pa-mk-jenis__toggle"
                            :aria-expanded="isJenisBuka(j.id)"
                            :aria-label="isJenisBuka(j.id) ? `Tutup accordion ${j.nama}` : `Buka accordion ${j.nama}`"
                            data-uji="mk-jenis-toggle"
                            @click.stop="toggleJenis(j.id)"
                        >
                            <HcisIcon
                                name="chevron-down"
                                size="xs"
                                :class="['pa-mk-chevron', { 'is-tutup': !isJenisBuka(j.id) }]"
                            />
                        </button>
                        <div class="pa-mk-jenis__sakelar" @click.stop>
                            <HcisSwitch
                                :model-value="statusJenis(j) !== 'mati'"
                                :aria-label="`Nilai ${j.nama} di level ini`"
                                size="sm"
                                data-uji="mk-jenis-switch"
                                @update:model-value="(v) => aturJenis(j, v)"
                            />
                        </div>
                        <div class="pa-mk-jenis__judul">
                            <strong>{{ j.nama }}</strong>
                            <small>{{ jumlahDinilaiJenis(j) }} dari {{ j.kompetensi.length }} dinilai</small>
                        </div>
                        <div v-if="nomorTingkatJenis(j).length" class="pa-mk-jenis__set" @click.stop>
                            <span>Set semua ke</span>
                            <HcisSelect
                                :model-value="''"
                                size="sm"
                                :filterable="false"
                                :clearable="false"
                                placeholder="tingkat…"
                                :aria-label="`Set semua ${j.nama} ke tingkat`"
                                class="pa-mk-jenis__pilih"
                                data-uji="mk-set-semua"
                                @update:model-value="(n) => setSemuaTingkat(j, n)"
                            >
                                <HcisSelectOption v-for="n in nomorTingkatJenis(j)" :key="n" :value="n" :label="`Tingkat ${n}`" />
                            </HcisSelect>
                        </div>
                    </header>

                    <div v-show="isJenisBuka(j.id)" class="pa-mk-jenis__badan">
                        <ul class="pa-mk-daftar">
                            <li
                                v-for="k in j.kompetensi"
                                :key="k.id"
                                :class="['pa-mk-baris', { 'is-mati': !selKini(k.id).aktif, 'is-galat': perluTingkat(k) }]"
                                data-uji="mk-kompetensi"
                            >
                                <HcisSwitch
                                    :model-value="selKini(k.id).aktif"
                                    :disabled="!k.tingkat.length && !selKini(k.id).aktif"
                                    :aria-label="`Nilai ${k.kode} ${k.nama} di level ini`"
                                    size="sm"
                                    @update:model-value="(v) => aturKompetensi(k, v)"
                                />
                                <div class="pa-mk-baris__nama">
                                    <strong><span class="pa-mk-kode">{{ k.kode }}</span> {{ k.nama }}</strong>
                                    <small v-if="!selKini(k.id).aktif">Tidak dinilai di level ini</small>
                                    <small v-else-if="perluTingkat(k)" class="pa-mk-merah">Pilih tingkat yang dituntut</small>
                                    <small v-else>{{ namaTingkat(k, selKini(k.id).tingkatId) }}</small>
                                    <small v-if="!k.tingkat.length" class="pa-mk-merah">
                                        Belum punya tingkat — buat dulu di <a href="/performance-appraisal/master/kompetensi">Master Kompetensi</a> (tombol Tingkat).
                                    </small>
                                </div>
                                <div v-if="k.tingkat.length" class="pa-mk-baris__tingkat">
                                    <HcisSelect
                                        :model-value="selKini(k.id).tingkatId ?? ''"
                                        size="sm"
                                        :disabled="!selKini(k.id).aktif"
                                        :clearable="false"
                                        filterable
                                        placeholder="Pilih tingkat…"
                                        :aria-label="`Tingkat ${k.kode}`"
                                        data-uji="mk-tingkat"
                                        @update:model-value="(t) => aturTingkat(k, t)"
                                    >
                                        <HcisSelectOption
                                            v-for="t in k.tingkat"
                                            :key="t.id"
                                            :value="t.id"
                                            :label="`L${t.tingkat} · ${t.nama}`"
                                            :description="t.expected_behaviour ? '' : 'Expected Behaviour belum diisi'"
                                        />
                                    </HcisSelect>
                                </div>
                                <details v-if="selKini(k.id).aktif && ebTerpilih(k)" class="pa-mk-baris__eb">
                                    <summary>Lihat Expected Behaviour tingkat ini</summary>
                                    <PaDaftarPerilaku :teks="ebTerpilih(k)" judul="" ringkas />
                                </details>
                            </li>
                        </ul>
                    </div>
                </section>

                <footer class="pa-mk-kaki">
                    <span :class="['pa-mk-status', { 'is-ubah': berubah(levelAktif) }]" data-uji="mk-status">
                        <HcisIcon :name="berubah(levelAktif) ? 'pencil-fill' : 'check2-circle'" size="xs" />
                        {{ berubah(levelAktif) ? 'Perubahan level ini belum disimpan' : 'Tersimpan' }}
                    </span>
                    <HcisButton label="Batalkan perubahan" variant="secondary" size="sm" :disabled="!berubah(levelAktif) || Boolean(menyimpan)" data-uji="mk-batal" @click="batalkan(levelAktif)" />
                    <HcisButton
                        :label="`Simpan ${levelTerpilih.nama}`"
                        variant="primary"
                        size="sm"
                        leading-icon="check-lg"
                        :loading="menyimpan === levelAktif"
                        :disabled="!berubah(levelAktif) || Boolean(menyimpan)"
                        data-uji="mk-simpan"
                        @click="simpan(levelAktif)"
                    />
                </footer>
            </section>
            <HcisEmptyState v-else title="Pilih level" description="Pilih level jabatan di sebelah kiri untuk mengatur kompetensinya." icon="hand-index" variant="dashed" />
        </div>

        <!-- Salin dari level lain -->
        <HcisDialog v-model="salin.terbuka" title="Salin dari level lain" icon="files" sentiment="brand" variant="glass" size="sm" sticky-footer>
            <p class="pa-mk-catatan">Pengaturan level <b>{{ levelTerpilih?.nama }}</b> diganti dengan milik level sumber. Belum tersimpan sampai Anda menekan Simpan.</p>
            <HcisSelect v-model="salin.sumber" filterable placeholder="Pilih level sumber…" class="w-100" data-uji="mk-salin-sumber">
                <HcisSelectOption
                    v-for="l in data?.level.filter((x) => x.id !== levelAktif && ringkasLevel(x.id).dinilai > 0) || []"
                    :key="l.id"
                    :value="l.id"
                    :label="l.nama"
                    :description="`${ringkasLevel(l.id).dinilai} kompetensi dinilai`"
                />
            </HcisSelect>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="salin.terbuka = false" />
                <HcisButton label="Salin" variant="primary" size="sm" leading-icon="files" :disabled="!salin.sumber" data-uji="mk-salin-jalankan" @click="jalankanSalin" />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisEmptyState, HcisField, HcisHeader, HcisIcon, HcisResultState,
    HcisSelect, HcisSelectOption, HcisSkeleton, HcisSwitch, hcisMessage,
} from '@/components/ui';
import PaDaftarPerilaku from '../components/PaDaftarPerilaku.vue';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalMatriksKompetensiPage' });

/*
 | Matriks Kompetensi per KOMPETENSI (DDL-2026-175), disunting PER LEVEL.
 | Tiap kompetensi (semua jenis, termasuk Qualitative Question): dinilai/tidak dinilai, dan bila
 | dinilai WAJIB memilih tingkat yang dituntut. Tidak ada lagi "dinilai umum"
 | (keputusan user 10 Okt 2026; server menolak juga). Satu level disimpan utuh; server
 | menyelaraskan matriks per jenis.
 */
const masterNavList = [
    { slug: 'jenis-kompetensi', label: 'Jenis Kompetensi', icon: 'diagram-2-fill', href: '/performance-appraisal/master/jenis-kompetensi' },
    { slug: 'kompetensi', label: 'Daftar Kompetensi', icon: 'bullseye', href: '/performance-appraisal/master/kompetensi' },
    { slug: 'syarat-kompetensi', label: 'Matriks Kompetensi', icon: 'grid-3x3-gap-fill', href: '/performance-appraisal/master/syarat-kompetensi' },
    { slug: 'ikatan-paket-soal', label: 'Matriks Soal', icon: 'diagram-3-fill', href: '/performance-appraisal/master/ikatan-paket-soal' },
    { slug: 'kategori-penilai', label: 'Jenis & Komposisi Penilai', icon: 'person-badge-fill', href: '/performance-appraisal/master/kategori-penilai' },
    { slug: 'bobot-pilar', label: 'Bobot KPI & Kompetensi', icon: 'pie-chart-fill', href: '/performance-appraisal/master/bobot-pilar' },
    { slug: 'pita-nilai', label: 'Skala Nilai & Grade', icon: 'bar-chart-steps', href: '/performance-appraisal/master/pita-nilai' },
    { slug: 'grup-kurva', label: 'Grup Level Kurva', icon: 'diagram-2-fill', href: '/performance-appraisal/master/grup-kurva' },
    { slug: 'relasi-organisasi', label: 'Relasi Organisasi', icon: 'people-fill', href: '/system/organisasi/relasi' },
];

const data = ref(null);
const memuat = ref(false);
const galat = ref('');
const galatSimpan = ref('');
const peringatan = ref([]);
const menyimpan = ref(null);
const menyimpanSemua = ref(false);
const cariLevel = ref('');
const tampilNonPa = ref(false);
const levelAktif = ref(null);

// asal/draf[level][kompetensi] = { aktif, tingkatId }
const asal = reactive({});
const draf = reactive({});

const KOSONG = Object.freeze({ aktif: false, tingkatId: null });
const selKini = (kid) => draf[levelAktif.value]?.[kid] || KOSONG;
const kunciSel = (s) => (s?.aktif ? `1:${s.tingkatId ?? ''}` : '0');
const berubah = (lv) => {
    const a = asal[lv] || {};
    const d = draf[lv] || {};
    return [...new Set([...Object.keys(a), ...Object.keys(d)])].some((k) => kunciSel(a[k]) !== kunciSel(d[k]));
};
const levelBerubah = computed(() => (data.value?.level || []).filter((l) => berubah(l.id)).map((l) => l.id));

const peta = computed(() => new Map((data.value?.kompetensi || []).map((k) => [k.id, k])));
function ringkasLevel(lv) {
    const d = draf[lv] || {};
    let dinilai = 0;
    let tanpaTingkat = 0;
    for (const [kid, s] of Object.entries(d)) {
        if (!s.aktif) continue;
        dinilai++;
        const k = peta.value.get(Number(kid));
        if (k && !s.tingkatId) tanpaTingkat++;
    }
    return { dinilai, tanpaTingkat };
}

const levelTampil = computed(() => {
    const q = cariLevel.value.trim().toLowerCase();
    // Level non-PA (dikecualikan) disembunyikan kecuali diminta — matriksnya tidak dipakai menilai.
    return (data.value?.level || []).filter((l) => (tampilNonPa.value || !l.dikecualikan || l.id === levelAktif.value)
        && (!q || l.nama.toLowerCase().includes(q)));
});
const levelTerpilih = computed(() => data.value?.level.find((l) => l.id === levelAktif.value) || null);

const jenisList = computed(() => {
    const grup = new Map();
    for (const k of data.value?.kompetensi || []) {
        if (!grup.has(k.jenis_id)) grup.set(k.jenis_id, { id: k.jenis_id, kode: k.jenis_kode, nama: k.jenis_nama, kompetensi: [] });
        grup.get(k.jenis_id).kompetensi.push(k);
    }
    return [...grup.values()];
});

/* ---- Accordion State per Jenis Kompetensi ---- */
const jenisTerbuka = reactive({});

function isJenisBuka(id) {
    return jenisTerbuka[id] !== false;
}

function toggleJenis(id) {
    jenisTerbuka[id] = !isJenisBuka(id);
}

const semuaJenisTerbuka = computed(() => {
    if (!jenisList.value.length) return true;
    return jenisList.value.every((j) => isJenisBuka(j.id));
});

function toggleSemuaJenis() {
    const buka = !semuaJenisTerbuka.value;
    for (const j of jenisList.value) {
        jenisTerbuka[j.id] = buka;
    }
}

function muatDari(d) {
    data.value = d;
    for (const k of Object.keys(asal)) delete asal[k];
    for (const k of Object.keys(draf)) delete draf[k];
    for (const l of d.level) {
        asal[l.id] = {};
    }
    for (const s of d.sel) {
        (asal[s.id_level] ||= {})[s.kompetensi_id] = { aktif: true, tingkatId: s.tingkat_id };
    }
    for (const l of d.level) draf[l.id] = JSON.parse(JSON.stringify(asal[l.id] || {}));
    if (!levelAktif.value || !d.level.some((l) => l.id === levelAktif.value)) {
        levelAktif.value = (d.level.find((l) => !l.dikecualikan) || d.level[0])?.id ?? null;
    }
}

async function muat() {
    memuat.value = true;
    galat.value = '';
    try {
        muatDari(await paApi.matriksKompetensi());
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat matriks.';
    } finally {
        memuat.value = false;
    }
}

function pilihLevel(id) {
    levelAktif.value = id;
    galatSimpan.value = '';
    peringatan.value = [];
}

/* ---- Penyuntingan ---- */
const tulisSel = (kid, nilai) => {
    draf[levelAktif.value] = { ...(draf[levelAktif.value] || {}), [kid]: nilai };
};
function tingkatBawaan(k) {
    // Satu-satunya tingkat langsung dipakai; lebih dari satu → admin memilih sendiri.
    return k.tingkat.length === 1 ? k.tingkat[0].id : null;
}
function aturKompetensi(k, aktif) {
    if (!aktif) {
        tulisSel(k.id, { aktif: false, tingkatId: null });
        return;
    }
    if (!k.tingkat.length) return;
    const lama = asal[levelAktif.value]?.[k.id];
    tulisSel(k.id, { aktif: true, tingkatId: lama?.tingkatId ?? tingkatBawaan(k) });
}
function aturTingkat(k, tingkatId) {
    tulisSel(k.id, { aktif: true, tingkatId: tingkatId === '' || tingkatId === null ? null : Number(tingkatId) });
}
const perluTingkat = (k) => selKini(k.id).aktif && !selKini(k.id).tingkatId;
const namaTingkat = (k, tid) => {
    const t = k.tingkat.find((x) => x.id === tid);
    return t ? `Tingkat dituntut: L${t.tingkat} · ${t.nama}` : '';
};
const ebTerpilih = (k) => (k.tingkat.find((x) => x.id === selKini(k.id).tingkatId)?.expected_behaviour || '').trim();

const jumlahDinilaiJenis = (j) => j.kompetensi.filter((k) => selKini(k.id).aktif).length;
const statusJenis = (j) => {
    const n = jumlahDinilaiJenis(j);
    return n === 0 ? 'mati' : n === j.kompetensi.length ? 'penuh' : 'sebagian';
};
/* Satu sakelar per jenis: mematikan = seluruh jenis tidak dinilai di level ini; menghidupkan =
   semua kompetensinya dinilai (yang belum punya tingkat dilewati). */
function aturJenis(j, aktif) {
    let terlewat = 0;
    for (const k of j.kompetensi) {
        if (aktif && !k.tingkat.length) {
            terlewat++;
            continue;
        }
        if (aktif && selKini(k.id).aktif) continue;
        aturKompetensi(k, aktif);
    }
    if (terlewat) hcisMessage.warning(`${terlewat} kompetensi ${j.nama} belum punya tingkat, jadi belum bisa dinilai.`, 'Matriks Kompetensi');
}
const nomorTingkatJenis = (j) => [...new Set(j.kompetensi.flatMap((k) => k.tingkat.map((t) => t.tingkat)))].sort((a, b) => a - b);
function setSemuaTingkat(j, nomor) {
    let tanpa = 0;
    for (const k of j.kompetensi) {
        const t = k.tingkat.find((x) => x.tingkat === Number(nomor));
        if (t) tulisSel(k.id, { aktif: true, tingkatId: t.id });
        else tanpa++;
    }
    if (tanpa) hcisMessage.info(`${tanpa} kompetensi tidak punya tingkat ${nomor}; tidak diubah.`, 'Matriks Kompetensi');
}

function batalkan(lv) {
    draf[lv] = JSON.parse(JSON.stringify(asal[lv] || {}));
    galatSimpan.value = '';
}

async function simpan(lv) {
    galatSimpan.value = '';
    const { tanpaTingkat } = ringkasLevel(lv);
    if (tanpaTingkat) {
        galatSimpan.value = `${tanpaTingkat} kompetensi dinilai tanpa tingkat. Pilih tingkatnya (ditandai merah) atau matikan sakelarnya.`;
        levelAktif.value = lv;
        return false;
    }
    menyimpan.value = lv;
    try {
        const isi = {};
        for (const [kid, s] of Object.entries(draf[lv] || {})) {
            if (s.aktif) isi[kid] = s.tingkatId ?? null;
        }
        const hasil = await paApi.simpanMatriksKompetensi(lv, isi);
        asal[lv] = JSON.parse(JSON.stringify(Object.fromEntries(Object.entries(draf[lv] || {}).filter(([, s]) => s.aktif))));
        draf[lv] = JSON.parse(JSON.stringify(asal[lv]));
        peringatan.value = hasil?.peringatan || [];
        hcisMessage.success(`Matriks ${data.value.level.find((l) => l.id === lv)?.nama || ''} tersimpan.`, 'Matriks Kompetensi');
        return true;
    } catch (e) {
        galatSimpan.value = e?.message || 'Gagal menyimpan.';
        levelAktif.value = lv;
        return false;
    } finally {
        menyimpan.value = null;
    }
}
async function simpanSemua() {
    menyimpanSemua.value = true;
    try {
        for (const lv of [...levelBerubah.value]) {
            if (!(await simpan(lv))) break;
        }
    } finally {
        menyimpanSemua.value = false;
    }
}

/* ---- Salin ---- */
const salin = reactive({ terbuka: false, sumber: null });
function bukaSalin() {
    Object.assign(salin, { terbuka: true, sumber: null });
}
function jalankanSalin() {
    draf[levelAktif.value] = JSON.parse(JSON.stringify(draf[salin.sumber] || {}));
    salin.terbuka = false;
    hcisMessage.info('Disalin. Periksa lalu tekan Simpan.', 'Matriks Kompetensi');
}

onMounted(muat);
</script>

<style scoped>
.pa-mk-page { padding-bottom: 2rem; }
/* Navigasi antar master di header (sama dengan halaman master lain). */
.pa-admin-switcher { display: flex; align-items: center; gap: 0.35rem; overflow-x: auto; margin-top: 0.5rem; padding: 0.25rem; border: 1px solid rgba(226, 232, 240, 0.85); border-radius: 12px; background: rgba(241, 245, 249, 0.75); }
.pa-admin-switcher__item { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.4rem 0.75rem; border-radius: 8px; color: #64748b; font-size: 0.76rem; font-weight: 500; text-decoration: none; white-space: nowrap; }
.pa-admin-switcher__item:hover { color: #4338ca; background: rgba(255, 255, 255, 0.8); }
.pa-admin-switcher__item--active { color: #4338ca; background: #fff; font-weight: 700; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); }
.pa-mk-kerangka, .pa-mk-tata { display: grid; grid-template-columns: minmax(0, 280px) minmax(0, 1fr); gap: 1rem; align-items: start; }
.pa-mk-kiri { position: sticky; top: 1rem; display: flex; flex-direction: column; gap: 0.6rem; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; min-width: 0; }
.pa-mk-level { display: flex; flex-direction: column; gap: 0.35rem; margin: 0; padding: 0; list-style: none; max-height: calc(100vh - 16rem); overflow-y: auto; }
.pa-mk-level__item { width: 100%; display: flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; text-align: left; cursor: pointer; }
.pa-mk-level__item:hover { border-color: #c7d2fe; }
.pa-mk-level__item.is-aktif { border-color: #6366f1; background: #f5f7ff; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
.pa-mk-level__item.is-nonpa { opacity: 0.7; }
.pa-mk-level__teks { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.pa-mk-level__teks strong { font-size: 0.84rem; color: #0f172a; }
.pa-mk-level__teks small { font-size: 0.72rem; color: #64748b; }
.pa-mk-titik { flex: 0 0 0.6rem; height: 0.6rem; border-radius: 999px; }
.pa-mk-titik.is-ubah { background: #f59e0b; }
.pa-mk-titik.is-galat { background: #ef4444; }
.pa-mk-kanan { display: flex; flex-direction: column; gap: 0.85rem; min-width: 0; }
.pa-mk-kepala { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 0.75rem; padding: 0.9rem 1rem; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; }
.pa-mk-kepala h3 { margin: 0; font-size: 1.05rem; font-weight: 750; color: #0f172a; }
.pa-mk-kepala p { margin: 0.15rem 0 0; font-size: 0.8rem; color: #475569; }
.pa-mk-jenis { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; overflow: hidden; }
.pa-mk-jenis__kepala {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease;
}
.pa-mk-jenis__kepala:not(.is-terbuka) {
    border-bottom: none;
}
.pa-mk-jenis__kepala:hover {
    background: #f1f5f9;
}
.pa-mk-jenis__toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.pa-mk-jenis__toggle:hover {
    border-color: #6366f1;
    color: #4338ca;
    background: #f5f7ff;
}
.pa-mk-chevron {
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.pa-mk-chevron.is-tutup {
    transform: rotate(-90deg);
}
.pa-mk-jenis__sakelar {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}
.pa-mk-jenis__judul { display: flex; flex-direction: column; flex: 1; min-width: 12rem; }
.pa-mk-jenis__judul strong { font-size: 0.9rem; color: #0f172a; }
.pa-mk-jenis__judul small { font-size: 0.74rem; color: #64748b; }
.pa-mk-jenis__set { display: flex; align-items: center; gap: 0.4rem; font-size: 0.76rem; color: #64748b; }
.pa-mk-jenis__pilih { width: 9.5rem; }

/* Bagian kompetensi: menjorok ke dalam dengan visual hierarchy yang jelas */
.pa-mk-jenis__badan {
    background: #fcfdfe;
    padding: 0.75rem 1rem 0.85rem 1.75rem;
}
.pa-mk-daftar {
    margin: 0;
    padding: 0 0 0 1rem;
    list-style: none;
    border-left: 2px solid #e0e7ff;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.pa-mk-baris {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) minmax(0, 15rem);
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    transition: all 0.15s ease;
}
.pa-mk-baris:hover {
    border-color: #cbd5e1;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.pa-mk-baris.is-mati {
    background: #f8fafc;
    border-color: #f1f5f9;
}
.pa-mk-baris.is-mati .pa-mk-baris__nama strong { color: #94a3b8; }
.pa-mk-baris.is-galat { background: #fef2f2; border-color: #fecaca; }
.pa-mk-baris__nama { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
.pa-mk-baris__nama strong { font-size: 0.84rem; color: #0f172a; }
.pa-mk-baris__nama small { font-size: 0.74rem; color: #64748b; }
.pa-mk-kode { display: inline-block; margin-right: 0.3rem; padding: 0 0.35rem; border-radius: 4px; background: #eef2ff; color: #4f46e5; font-family: ui-monospace, monospace; font-size: 0.72rem; }
.pa-mk-baris__tingkat :deep(.hcis-select) { width: 100%; }
.pa-mk-baris__eb {
    grid-column: 2 / -1;
    font-size: 0.78rem;
    margin-top: 0.25rem;
    padding-top: 0.35rem;
    border-top: 1px dashed #e2e8f0;
}
.pa-mk-baris__eb summary { cursor: pointer; color: #4338ca; font-weight: 600; }
.pa-mk-merah { color: #b91c1c !important; }
.pa-mk-kaki { position: sticky; bottom: 0.75rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 0.5rem; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 16px; background: rgba(255, 255, 255, 0.96); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); z-index: 5; }
.pa-mk-status { margin-right: auto; display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #15803d; }
.pa-mk-status.is-ubah { color: #b45309; }
.pa-mk-catatan { font-size: 0.8rem; color: #64748b; }
@media (max-width: 900px) {
    .pa-mk-kerangka, .pa-mk-tata { grid-template-columns: 1fr; }
    .pa-mk-kiri { position: static; }
    .pa-mk-level { max-height: 14rem; }
    .pa-mk-jenis__badan { padding: 0.5rem 0.5rem 0.65rem 0.75rem; }
    .pa-mk-daftar { padding-left: 0.5rem; }
    .pa-mk-baris { grid-template-columns: auto minmax(0, 1fr); padding: 0.6rem 0.65rem; }
    .pa-mk-baris__tingkat { grid-column: 2; }
}
</style>
