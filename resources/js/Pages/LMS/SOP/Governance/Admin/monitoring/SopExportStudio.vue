<template>
    <SopModal
        :show="show"
        title="Export Studio"
        subtitle="Saring, pilih orangnya, tentukan kolomnya, lalu antrekan."
        icon="bi-database-down"
        size="full"
        @close="$emit('close')"
    >
        <div class="xs-wrap">
            <!-- ══ SARINGAN ══ -->
            <section class="xs-saring">
                <div class="xs-saring-head">
                    <span class="xs-saring-judul"><i class="bi bi-funnel-fill"></i> Saringan</span>
                    <div class="xs-saring-aksi">
                        <button class="xs-mini" @click="lanjutan = !lanjutan">
                            <i class="bi" :class="lanjutan ? 'bi-chevron-up' : 'bi-sliders'"></i>
                            {{ lanjutan ? 'Sembunyikan saringan lanjutan' : 'Saringan lanjutan' }}
                        </button>
                        <button v-if="adaSaringan" class="xs-mini bahaya" @click="resetSaringan">
                            <i class="bi bi-x-circle"></i> Bersihkan
                        </button>
                    </div>
                </div>

                <!-- Saringan cepat: bukan saringan baru, hanya jalan pintas ke
                     nilai yang memang sudah ada. Yang dicari admin hampir selalu
                     salah satu dari empat ini. -->
                <div class="xs-cepat">
                    <button
                        v-for="c in cepat"
                        :key="c.key"
                        class="xs-cepat-b"
                        :class="{ on: cepatAktif === c.key }"
                        @click="pakaiCepat(c)"
                    >
                        <i class="bi" :class="c.icon"></i> {{ c.label }}
                    </button>
                </div>

                <FilterPanel
                    :config="filterConfig"
                    :filters="saringan"
                    :results-count="pagOrang.total"
                    @filters-update="onSaringan"
                    @search="onCari"
                />
            </section>

            <!-- ══ CAKUPAN ══ -->
            <section class="xs-cakupan">
                <div class="xs-cakupan-kiri">
                    <span class="xs-chip" :class="modeSemua ? 'netral' : 'pilih'">
                        <i class="bi" :class="modeSemua ? 'bi-people-fill' : 'bi-check2-square'"></i>
                        {{
                            modeSemua
                                ? `Semua ${pagOrang.total} karyawan hasil saringan`
                                : `${terpilih.length} karyawan dicentang`
                        }}
                    </span>
                    <span class="xs-chip kecil">
                        <i class="bi bi-list-ol"></i>
                        <span v-if="memuatEstimasi" class="spinner-border spinner-border-sm"></span>
                        <template v-else>{{ (estimasi || 0).toLocaleString('id-ID') }} baris</template>
                    </span>
                </div>
                <div class="xs-cakupan-kanan">
                    <button class="xs-mini" :disabled="!orang.length" @click="pilihHalaman">
                        <i class="bi bi-check-all"></i> Centang halaman ini
                    </button>
                    <button class="xs-mini" :disabled="modeSemua" @click="terpilih = []">
                        <i class="bi bi-people"></i> Kembali ke semua
                    </button>
                </div>
            </section>

            <!-- ══ DAFTAR ORANG ══ -->
            <section class="xs-daftar">
                <div v-if="memuatOrang" class="xs-kosong">
                    <span class="spinner-border spinner-border-sm"></span> Memuat karyawan…
                </div>
                <div v-else-if="!orang.length" class="xs-kosong">
                    <i class="bi bi-inbox"></i> Tidak ada karyawan yang cocok dengan saringan ini.
                </div>

                <div v-else class="xs-grid">
                    <article
                        v-for="o in orang"
                        :key="o.Kode_Karyawan"
                        class="xs-org"
                        :class="{ on: terpilih.includes(o.Kode_Karyawan) }"
                    >
                        <div class="xs-org-atas">
                            <label class="xs-check" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="terpilih.includes(o.Kode_Karyawan)"
                                    @change="togglePilih(o.Kode_Karyawan)"
                                />
                            </label>
                            <span class="xs-ava">{{ inisial(o.Nama || o.Kode_Karyawan) }}</span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="xs-org-nama">{{ o.Nama || o.Kode_Karyawan }}</div>
                                <div class="xs-org-sub">
                                    {{ o.Kode_Karyawan }}<template v-if="o.Jabatan"> · {{ o.Jabatan }}</template>
                                </div>
                                <div v-if="o.Departemen" class="xs-org-sub">
                                    <i class="bi bi-building"></i> {{ o.Departemen }}
                                </div>
                            </div>
                            <button class="xs-exp" :class="{ on: buka[o.Kode_Karyawan] }" @click="toggleBuka(o)">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>

                        <div class="xs-org-angka">
                            <span class="xs-mini-stat"
                                ><strong>{{ o.total_sop || 0 }}</strong> dokumen</span
                            >
                            <span class="xs-mini-stat ok"
                                ><strong>{{ o.selesai || 0 }}</strong> selesai</span
                            >
                            <span v-if="o.terlambat > 0" class="xs-mini-stat bad"
                                ><strong>{{ o.terlambat }}</strong> telat</span
                            >
                            <span class="xs-mini-stat" :class="pctKelas(o.persen)">{{ o.persen || 0 }}%</span>
                        </div>

                        <transition name="xs-slide">
                            <div v-if="buka[o.Kode_Karyawan]" class="xs-paket">
                                <div v-if="paketMemuat[o.Kode_Karyawan]" class="xs-kosong kecil">
                                    <span class="spinner-border spinner-border-sm"></span> Memuat paket…
                                </div>
                                <div v-else-if="!(paketCache[o.Kode_Karyawan] || []).length" class="xs-kosong kecil">
                                    Tidak ada paket aktif.
                                </div>
                                <!-- Daftar harus jadi cabang v-else dari rantai di atas.
                                     Sebagai elemen sepupu, ia tetap dirender berdampingan
                                     dengan pesan "tidak ada paket" — dua pernyataan yang
                                     saling menyangkal di layar yang sama. -->
                                <template v-else>
                                    <div
                                        v-for="p in paketCache[o.Kode_Karyawan]"
                                        :key="p.Id_SOP_Paket"
                                        class="xs-paket-baris"
                                    >
                                        <div class="min-w-0">
                                            <div class="xs-paket-nama">{{ p.Nama_Paket }}</div>
                                            <div class="xs-paket-sub">
                                                {{ p.Item_Selesai || 0 }}/{{ p.Total_Item || 0 }} item
                                                <template v-if="p.Item_Perlu_Ulang > 0">
                                                    · {{ p.Item_Perlu_Ulang }} perlu ulang</template
                                                >
                                            </div>
                                        </div>
                                        <span class="xs-paket-pct" :class="pctKelas(p.Persen)"
                                            >{{ p.Persen || 0 }}%</span
                                        >
                                    </div>
                                </template>
                            </div>
                        </transition>
                    </article>
                </div>

                <div v-if="pagOrang.total_page > 1" class="xs-pager">
                    <button :disabled="pagOrang.page <= 1" @click="muatOrang(pagOrang.page - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span>Halaman {{ pagOrang.page }} / {{ pagOrang.total_page }}</span>
                    <button :disabled="pagOrang.page >= pagOrang.total_page" @click="muatOrang(pagOrang.page + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </section>
        </div>

        <template #footer>
            <div class="xs-foot">
                <div class="xs-foot-baris">
                    <div class="xs-jenis">
                        <button
                            v-for="j in jenisList"
                            :key="j.key"
                            class="xs-j"
                            :class="{ on: jenis === j.key }"
                            :title="j.ket"
                            @click="pilihJenis(j.key)"
                        >
                            <i class="bi" :class="j.icon"></i> {{ j.label }}
                        </button>
                    </div>

                    <!-- Kolom ditegakkan server: mematikan kelompok benar-benar
                         mengecilkan berkas, bukan sekadar menyembunyikannya. -->
                    <div v-if="jenis === 'rinci'" class="xs-kolom">
                        <span class="xs-kolom-lbl"><i class="bi bi-layout-three-columns"></i> Kolom</span>
                        <button
                            v-for="(label, key) in grupKolom"
                            :key="key"
                            class="xs-kol-b"
                            :class="{ on: kolomAktif.includes(key) }"
                            @click="toggleKolom(key)"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div class="xs-foot-baris">
                    <label class="xs-field">
                        <span>Nama berkas</span>
                        <input v-model="nama" type="text" maxlength="120" placeholder="opsional" />
                    </label>
                    <div class="xs-fmt">
                        <button
                            v-for="f in formats"
                            :key="f.key"
                            class="xs-fmt-b"
                            :class="{ on: format === f.key }"
                            @click="format = f.key"
                        >
                            <i class="bi" :class="f.icon"></i> {{ f.label }}
                        </button>
                    </div>
                    <div class="flex-grow-1"></div>
                    <span v-if="estimasi > 50000" class="xs-peringatan">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Melebihi 50.000 baris — berkas dipotong dan pemotongannya ditulis di judul.
                    </span>
                    <button class="stitch-btn secondary" @click="$emit('close')">Batal</button>
                    <button class="stitch-btn primary" :disabled="mengirim || !estimasi" @click="kirim">
                        <span v-if="mengirim" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-download"></i>
                        Export {{ (estimasi || 0).toLocaleString('id-ID') }} baris
                    </button>
                </div>
            </div>
        </template>
    </SopModal>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import SopModal from '../../../Shared/components/ui/SopModal.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';
import { useActivityCenter } from '../../../../../../composables/useActivityCenter.js';

const API = '/api/v1/sop/admin/monitoring';

const SARINGAN_KOSONG = {
    search: '',
    status: 'all',
    dept: [],
    divisi: [],
    level: [],
    lokasi: [],
    jabatan: [],
    paket: [],
    prioritas: '',
    date_mode: 'assign',
    date_range: [],
};

/**
 * Export Studio — permukaan export yang bisa disaring dan ditelusuri.
 *
 * Tiga hal yang sengaja berbeda dari modal export LMS:
 *
 *  1. "Semua" tidak memateri­alisasi apa pun. Di LMS, mencentang semua berarti
 *     server mengenkripsi hingga 25.000 id lalu mengirimnya bolak-balik, lengkap
 *     dengan mode gagal "terlalu banyak, persempit saringan". Di sini keadaan
 *     default SUDAH berarti "semua yang lolos saringan"; mencentang orang justru
 *     MENYEMPITKAN, dan yang dikirim hanya kode yang dicentang.
 *
 *  2. Kolom bisa dipilih per kelompok. Berkas rincian punya 38 kolom; yang cuma
 *     butuh "siapa belum baca apa" tak perlu IP dan user agent. Pilihan itu
 *     ditegakkan di server, jadi berkasnya benar-benar mengecil.
 *
 *  3. Saringan cepat memetakan pertanyaan yang benar-benar ditanyakan admin ke
 *     nilai saringan yang memang sudah ada — bukan menambah semantik baru.
 */
export default {
    name: 'SopExportStudio',
    components: { SopModal, FilterPanel },
    props: {
        show: { type: Boolean, default: false },
        filterOptions: { type: Object, default: () => ({}) },
        /** Saringan yang sedang tampil di tab Karyawan; jadi titik awal studio. */
        filterAwal: { type: Object, default: () => ({}) },
        jenisAwal: { type: String, default: 'rinci' },
    },
    emits: ['close'],
    data() {
        return {
            jenis: 'rinci',
            format: 'xlsx',
            nama: '',
            lanjutan: false,

            saringan: { ...SARINGAN_KOSONG },
            kolomAktif: [],

            orang: [],
            pagOrang: { page: 1, total_page: 1, total: 0 },
            memuatOrang: false,
            terpilih: [],
            buka: {},
            paketCache: {},
            paketMemuat: {},

            estimasi: 0,
            memuatEstimasi: false,
            mengirim: false,
            _tEstimasi: null,

            cepat: [
                {
                    key: 'telat',
                    label: 'Ada yang terlambat',
                    icon: 'bi-exclamation-octagon-fill',
                    set: { status: 'terlambat' },
                },
                { key: 'belum', label: 'Belum dibuka', icon: 'bi-circle', set: { status: 'belum' } },
                { key: 'jalan', label: 'Sedang berjalan', icon: 'bi-hourglass-split', set: { status: 'progress' } },
                { key: 'kritis', label: 'Prioritas kritis', icon: 'bi-fire', set: { prioritas: 'KRITIS' } },
            ],
            jenisList: [
                {
                    key: 'rinci',
                    label: 'Rincian per Item',
                    icon: 'bi-list-columns-reverse',
                    ket: 'Satu baris per karyawan × paket × item',
                },
                {
                    key: 'karyawan',
                    label: 'Ringkasan Karyawan',
                    icon: 'bi-people-fill',
                    ket: 'Satu baris per karyawan',
                },
                {
                    key: 'coverage',
                    label: 'Penyelesaian per Paket',
                    icon: 'bi-collection-fill',
                    ket: 'Target & tingkat penyelesaian tiap paket',
                },
                {
                    key: 'dokumen',
                    label: 'Register Dokumen',
                    icon: 'bi-shield-check',
                    ket: 'Masa berlaku, review, pengesahan',
                },
            ],
            formats: [
                { key: 'xlsx', label: 'Excel', icon: 'bi-file-earmark-excel' },
                { key: 'csv', label: 'CSV', icon: 'bi-filetype-csv' },
                { key: 'pdf', label: 'PDF', icon: 'bi-file-earmark-pdf' },
            ],
        };
    },
    computed: {
        grupKolom() {
            return this.filterOptions?.grup_kolom || {};
        },
        modeSemua() {
            return this.terpilih.length === 0;
        },
        adaSaringan() {
            const s = this.saringan;

            return (
                !!s.search ||
                (s.status && s.status !== 'all') ||
                !!s.prioritas ||
                ['dept', 'divisi', 'level', 'lokasi', 'jabatan', 'paket'].some(
                    (k) => (s[k] || []).length,
                ) ||
                (s.date_range || []).length > 0
            );
        },
        cepatAktif() {
            const c = this.cepat.find((x) => Object.entries(x.set).every(([k, v]) => this.saringan[k] === v));

            return c ? c.key : null;
        },
        /** Saringan → parameter server. Satu tempat, dipakai semua panggilan. */
        params() {
            const s = this.saringan;
            const [mulai, akhir] = Array.isArray(s.date_range) ? s.date_range : [null, null];

            return {
                q: s.search || undefined,
                status: s.status || 'all',
                dept: s.dept?.length ? s.dept : undefined,
                divisi: s.divisi?.length ? s.divisi : undefined,
                level: s.level?.length ? s.level : undefined,
                lokasi: s.lokasi?.length ? s.lokasi : undefined,
                jabatan: s.jabatan?.length ? s.jabatan : undefined,
                paket: s.paket?.length ? s.paket : undefined,
                prioritas: s.prioritas || undefined,
                date_mode: s.date_mode || 'assign',
                date_start: mulai || undefined,
                date_end: akhir || undefined,
            };
        },
        /** Saringan + penyempit centang. Kosongnya `kodes` berarti "semua". */
        filterEfektif() {
            const f = { ...this.params };
            if (this.terpilih.length) f.kodes = this.terpilih;

            return f;
        },
        filterConfig() {
            const o = this.filterOptions || {};
            const opsi = (arr) => (arr || []).map((v) => ({ label: v, value: v }));

            const dasar = [
                {
                    key: 'status',
                    label: 'Status',
                    type: 'select',
                    clearable: false,
                    col: 3,
                    options: [
                        { label: 'Semua', value: 'all' },
                        { label: 'Ada yang belum dibuka', value: 'belum' },
                        { label: 'Sedang berjalan', value: 'progress' },
                        { label: 'Tuntas semua', value: 'selesai' },
                        { label: 'Terlambat', value: 'terlambat' },
                    ],
                },
                {
                    key: 'paket',
                    label: 'Paket Dokumen',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 5,
                    placeholder: 'Semua Paket Dokumen',
                    options: opsi(o.paket),
                },
            ];

            const lanjut = [
                ...dasar,
                {
                    key: 'dept',
                    label: 'Departemen',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 3,
                    placeholder: 'Semua Departemen',
                    options: opsi(o.departemen),
                },
                {
                    key: 'divisi',
                    label: 'Divisi',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 3,
                    placeholder: 'Semua Divisi',
                    options: opsi(o.divisi),
                },
                {
                    key: 'lokasi',
                    label: 'Lokasi Kerja',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 3,
                    placeholder: 'Semua Lokasi',
                    options: opsi(o.lokasi),
                },
                {
                    key: 'jabatan',
                    label: 'Jabatan',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 3,
                    placeholder: 'Semua Jabatan',
                    options: opsi(o.jabatan),
                },
                {
                    key: 'level',
                    label: 'Level',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    maxCollapseTags: 1,
                    col: 3,
                    placeholder: 'Semua Level',
                    options: opsi(o.level),
                },
                {
                    key: 'prioritas',
                    label: 'Prioritas',
                    type: 'select',
                    clearable: true,
                    col: 3,
                    placeholder: 'Semua Prioritas',
                    options: opsi(o.prioritas),
                },
                {
                    key: 'date_mode',
                    label: 'Basis Tanggal',
                    type: 'select',
                    clearable: false,
                    col: 3,
                    options: [
                        { label: 'Tanggal Assign', value: 'assign' },
                        { label: 'Tenggat', value: 'deadline' },
                        { label: 'Tanggal Selesai', value: 'selesai' },
                    ],
                },
                {
                    key: 'date_range',
                    label: 'Rentang Tanggal',
                    type: 'daterange',
                    col: 3,
                    valueFormat: 'YYYY-MM-DD',
                    format: 'DD MMM YYYY',
                },
            ];

            return {
                searchable: true,
                searchPlaceholder: 'Cari nama / kode / no. induk…',
                showResultsCount: true,
                showActiveFilters: false,
                showActions: false,
                showReset: false,
                showClearAll: false,
                mobileTrigger: false,
                filters: this.lanjutan ? lanjut : dasar,
            };
        },
    },
    watch: {
        show(v) {
            if (!v) return;
            this.jenis = this.jenisAwal || 'rinci';
            this.terpilih = [];
            this.buka = {};
            this.paketCache = {};
            this.lanjutan = false;
            this.kolomAktif = Object.keys(this.grupKolom);
            this.saringan = { ...SARINGAN_KOSONG, ...this.dariLayar() };
            this.muatOrang(1);
            this.muatEstimasi();
        },
        terpilih() {
            this.jadwalkanEstimasi();
        },
    },
    methods: {
        /** Terjemahkan saringan tab Karyawan (bentuk params) ke bentuk saringan studio. */
        dariLayar() {
            const a = this.filterAwal || {};
            const arr = (v) => (Array.isArray(v) ? v : v ? [v] : []);

            return {
                search: a.q || '',
                status: a.status || 'all',
                dept: arr(a.dept),
                divisi: arr(a.divisi),
                level: arr(a.level),
                lokasi: arr(a.lokasi),
                jabatan: arr(a.jabatan),
                paket: arr(a.paket),
                prioritas: a.prioritas || '',
                date_mode: a.date_mode || 'assign',
                date_range: a.date_start && a.date_end ? [a.date_start, a.date_end] : [],
            };
        },
        inisial(n) {
            return String(n || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        pctKelas(p) {
            return (p || 0) >= 100 ? 'ok' : (p || 0) >= 40 ? 'mid' : 'bad';
        },
        onSaringan(v) {
            this.saringan = { ...this.saringan, ...v };
            this.segarkan();
        },
        onCari(q) {
            this.saringan = { ...this.saringan, search: q };
            this.segarkan();
        },
        resetSaringan() {
            this.saringan = { ...SARINGAN_KOSONG };
            this.segarkan();
        },
        /** Menekan tombol yang sedang aktif mematikannya lagi. */
        pakaiCepat(c) {
            const mati = this.cepatAktif === c.key;
            const patch = {};
            Object.keys(c.set).forEach((k) => {
                patch[k] = mati ? SARINGAN_KOSONG[k] : c.set[k];
            });
            this.saringan = { ...this.saringan, ...patch };
            this.segarkan();
        },
        segarkan() {
            // Centang lama tak dihapus: ia di-key oleh kode karyawan, jadi tetap
            // sah walau daftar yang tampil berubah.
            //
            // Simpanan paket JUSTRU harus dibuang: isinya dihitung menurut
            // saringan lama, jadi setelah saringan berubah ia akan menampilkan
            // lingkup yang sudah tak berlaku.
            this.paketCache = {};
            this.buka = {};
            this.muatOrang(1);
            this.jadwalkanEstimasi();
        },
        toggleKolom(key) {
            const ada = this.kolomAktif.includes(key);
            // Satu kelompok terakhir tak boleh dimatikan — berkas tanpa kolom
            // apa pun selain nomor urut tak ada gunanya.
            if (ada && this.kolomAktif.length === 1) return;
            this.kolomAktif = ada ? this.kolomAktif.filter((k) => k !== key) : [...this.kolomAktif, key];
        },
        pilihJenis(k) {
            this.jenis = k;
            this.muatEstimasi();
        },
        togglePilih(kode) {
            this.terpilih = this.terpilih.includes(kode)
                ? this.terpilih.filter((x) => x !== kode)
                : [...this.terpilih, kode];
        },
        pilihHalaman() {
            const tambah = this.orang.map((o) => o.Kode_Karyawan).filter((k) => !this.terpilih.includes(k));
            this.terpilih = [...this.terpilih, ...tambah];
        },
        jadwalkanEstimasi() {
            clearTimeout(this._tEstimasi);
            this._tEstimasi = setTimeout(this.muatEstimasi, 250);
        },
        async toggleBuka(o) {
            const k = o.Kode_Karyawan;
            const membuka = !this.buka[k];
            this.buka = { ...this.buka, [k]: membuka };
            if (membuka && !this.paketCache[k]) await this.muatPaket(k);
        },
        async muatPaket(kode) {
            this.paketMemuat = { ...this.paketMemuat, [kode]: true };
            try {
                // Endpoint khusus export: paket orang ini DI DALAM saringan yang
                // sedang berlaku. Memakai /karyawan/{kode}/paket di sini membuat
                // kepala kartu (yang dihitung dengan saringan) menyebut angka lain
                // daripada daftar di bawahnya.
                const r = await axios.get(`${API}/export/karyawan/${encodeURIComponent(kode)}/paket`, {
                    params: this.params,
                });
                this.paketCache = { ...this.paketCache, [kode]: r.data.result || [] };
            } catch (e) {
                this.paketCache = { ...this.paketCache, [kode]: [] };
            } finally {
                this.paketMemuat = { ...this.paketMemuat, [kode]: false };
            }
        },
        async muatOrang(page = 1) {
            if (page < 1) return;
            this.memuatOrang = true;
            try {
                // Sengaja TANPA kodes: daftar ini tempat memilih, jadi ia harus
                // tetap memperlihatkan seluruh kandidat hasil saringan.
                const r = await axios.get(`${API}/karyawan`, { params: { ...this.params, page, limit: 12 } });
                this.orang = r.data.result || [];
                this.pagOrang = {
                    page: r.data.page || page,
                    total_page: r.data.total_page || 1,
                    total: r.data.total_data ?? r.data.total ?? this.orang.length,
                };
            } catch (e) {
                this.orang = [];
                ElMessage.error('Gagal memuat daftar karyawan');
            } finally {
                this.memuatOrang = false;
            }
        },
        async muatEstimasi() {
            this.memuatEstimasi = true;
            try {
                const r = await axios.get(`${API}/export/estimasi`, {
                    params: { jenis: this.jenis, ...this.filterEfektif },
                });
                this.estimasi = r.data.success ? r.data.result.total : 0;
            } catch (e) {
                this.estimasi = 0;
            } finally {
                this.memuatEstimasi = false;
            }
        },
        async kirim() {
            this.mengirim = true;
            try {
                const r = await axios.post(`${API}/export/request`, {
                    jenis: this.jenis,
                    format: this.format,
                    nama: this.nama || null,
                    filters: this.filterEfektif,
                    kolom: this.jenis === 'rinci' ? this.kolomAktif : [],
                });
                ElMessage.success(r.data.message || 'Export diproses.');
                useActivityCenter().notifyNewTask();
                this.$emit('close');
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memulai export');
            } finally {
                this.mengirim = false;
            }
        },
    },
};
</script>

<style scoped>
.xs-wrap {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

/* ── saringan ── */
.xs-saring {
    padding: 0.75rem 1.2rem 0.4rem;
    border-bottom: 1px solid #eef2f7;
    background: #fff;
}
.xs-saring-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
}
.xs-saring-judul {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.xs-saring-judul i {
    color: var(--hcis-indigo-600);
}
.xs-saring-aksi {
    display: flex;
    gap: 0.4rem;
}

.xs-cepat {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.55rem;
}
.xs-cepat-b {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    font-size: 0.71rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    padding: 0.26rem 0.7rem;
    cursor: pointer;
    transition: all 0.16s ease;
}
.xs-cepat-b:hover {
    border-color: var(--hcis-indigo-300);
    color: var(--hcis-indigo-600);
}
.xs-cepat-b.on {
    background: rgba(99, 102, 241, 0.1);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-700);
}

.xs-mini {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: #fff;
    border: 1px solid #dbe1ea;
    border-radius: 9px;
    padding: 0.3rem 0.6rem;
    cursor: pointer;
}
.xs-mini:hover:not(:disabled) {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
}
.xs-mini:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.xs-mini.bahaya {
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}

.xs-saring :deep(.filter-panel) {
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
    margin-bottom: 0 !important;
    background: transparent !important;
}

/* ── cakupan ── */
.xs-cakupan {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.55rem 1.2rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid #eef2f7;
}
.xs-cakupan-kiri,
.xs-cakupan-kanan {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.xs-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    font-size: 0.73rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.24rem 0.7rem;
}
.xs-chip.netral {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.22);
}
.xs-chip.pilih {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.24);
}
.xs-chip.kecil {
    font-size: 0.7rem;
    color: var(--hcis-slate-600);
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
}

/* ── daftar ── */
.xs-daftar {
    flex: 1;
    overflow-y: auto;
    padding: 0.8rem 1.2rem 1rem;
    background: #fbfcfe;
}
.xs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.65rem;
    align-items: start;
}
.xs-kosong {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 2.4rem 0.5rem;
    color: var(--hcis-slate-400);
    font-size: 0.82rem;
    text-align: center;
}
.xs-kosong.kecil {
    padding: 0.8rem 0.5rem;
    font-size: 0.74rem;
}

.xs-org {
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    background: #fff;
    padding: 0.7rem 0.75rem;
    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}
.xs-org:hover {
    border-color: var(--hcis-indigo-200);
    transform: translateY(-1px);
}
.xs-org.on {
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.xs-org-atas {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
}
.xs-check {
    display: inline-flex;
    padding-top: 0.35rem;
}
.xs-check input {
    width: 15px;
    height: 15px;
    cursor: pointer;
    accent-color: var(--hcis-indigo-500);
}
.xs-ava {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
}
.xs-org-nama {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.25;
}
.xs-org-sub {
    font-size: 0.69rem;
    color: var(--hcis-slate-500);
    margin-top: 1px;
}
.xs-exp {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    border: 1px solid var(--hcis-slate-200);
    background: #fff;
    color: var(--hcis-slate-500);
    display: grid;
    place-items: center;
    cursor: pointer;
    flex-shrink: 0;
    font-size: 0.72rem;
}
.xs-exp.on i {
    transform: rotate(180deg);
}
.xs-exp i {
    transition: transform 0.22s ease;
}

.xs-org-angka {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.5rem;
    padding-left: 2.3rem;
}
.xs-mini-stat {
    font-size: 0.67rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border-radius: 6px;
    padding: 0.12rem 0.45rem;
}
.xs-mini-stat.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.1);
}
.xs-mini-stat.mid {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.12);
}
.xs-mini-stat.bad {
    color: var(--hcis-red-700);
    background: rgba(239, 68, 68, 0.1);
}

.xs-paket {
    margin-top: 0.55rem;
    padding-left: 2.3rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.xs-paket-baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.35rem 0.55rem;
    border-radius: 9px;
    background: var(--hcis-slate-50);
    border: 1px solid #eef2f7;
}
.xs-paket-nama {
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}
.xs-paket-sub {
    font-size: 0.65rem;
    color: var(--hcis-slate-500);
}
.xs-paket-pct {
    font-size: 0.73rem;
    font-weight: 800;
}
.xs-paket-pct.ok {
    color: var(--hcis-emerald-600);
}
.xs-paket-pct.mid {
    color: var(--hcis-amber-600);
}
.xs-paket-pct.bad {
    color: var(--hcis-red-600);
}

.xs-pager {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    margin-top: 0.9rem;
    padding-top: 0.7rem;
    border-top: 1px solid #eef2f7;
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
}
.xs-pager button {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    border: 1px solid #dbe1ea;
    background: #fff;
    color: var(--hcis-slate-600);
    cursor: pointer;
}
.xs-pager button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ── kaki ── */
.xs-foot {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    width: 100%;
}
.xs-foot-baris {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: wrap;
}

.xs-jenis {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.xs-j {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    padding: 0.33rem 0.65rem;
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    border: 1px solid #dbe1ea;
    border-radius: 9px;
    background: #fff;
    cursor: pointer;
}
.xs-j.on {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
}

.xs-kolom {
    display: flex;
    align-items: center;
    gap: 0.28rem;
    flex-wrap: wrap;
}
.xs-kolom-lbl {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.xs-kol-b {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    background: var(--hcis-slate-50);
    border: 1px dashed #dbe1ea;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    cursor: pointer;
}
.xs-kol-b.on {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.1);
    border-style: solid;
    border-color: var(--hcis-indigo-300);
}

.xs-field {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
}
.xs-field input {
    width: 180px;
    padding: 0.32rem 0.55rem;
    font-size: 0.75rem;
    font-weight: 500;
    border: 1px solid #dbe1ea;
    border-radius: 9px;
    outline: none;
}
.xs-field input:focus {
    border-color: var(--hcis-indigo-500);
}
.xs-fmt {
    display: flex;
    gap: 0.3rem;
}
.xs-fmt-b {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.32rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    border: 1px solid #dbe1ea;
    border-radius: 9px;
    background: #fff;
    cursor: pointer;
}
.xs-fmt-b.on {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
}

.xs-peringatan {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 9px;
    padding: 0.28rem 0.6rem;
}

.xs-slide-enter-active,
.xs-slide-leave-active {
    transition:
        opacity 0.2s ease,
        max-height 0.24s ease;
    overflow: hidden;
}
.xs-slide-enter-from,
.xs-slide-leave-to {
    opacity: 0;
    max-height: 0;
}
.xs-slide-enter-to,
.xs-slide-leave-from {
    max-height: 460px;
}
</style>
