<template>
    <div class="container-fluid px-0 sop-scope">
        <!-- ══ 1. HERO HEADER KEPATUHAN DOKUMEN (Menggunakan HcisHeader Signature) ══ -->
        <HcisHeader
            title="Functional Document"
            kicker="DMS COMPLIANCE CENTER"
            subtitle="Daftar prosedur kerja wajib dan kepatuhan operasional Anda."
            icon="clipboard2-check-fill"
            sentiment="purple"
            variant="glass"
            size="md"
            :loading="loading"
            class="mb-4"
        >
            <!-- Aksi Header Kanan -->
            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        href="/sop/repository"
                        leading-icon="journal-bookmark-fill"
                        pill
                        title="Lihat seluruh katalog dokumen publik dan rujukan operasional"
                        class="shero-action-public-btn"
                    >
                        <span class="d-none d-md-inline text-truncate">Public Document</span>
                        <span class="d-inline d-md-none text-truncate">Katalog</span>
                    </HcisButton>
                    <div class="hcis-date-pill d-none d-md-inline-flex">
                        <HcisIcon name="calendar3" size="2xs" class="flex-shrink-0" />
                        <span class="text-truncate">{{ formattedDate }}</span>
                    </div>
                </div>
            </template>

            <!-- Konsol Metrik Kepatuhan (3 Lensa Utama: Ringkas, Terpadu, Responsif) -->
            <template #console>
                <div class="shero-console-grid">
                    <!-- 1. Lensa Skor Kepatuhan (Semua Tugas) -->
                    <div
                        class="shero-metric-card clickable min-w-0"
                        :class="{
                            active: statusAktif === '',
                            'is-optimal': allDone
                        }"
                        role="button"
                        tabindex="0"
                        title="Tampilkan seluruh tugas kepatuhan"
                        @click="statusAktif = ''"
                        @keydown.enter="statusAktif = ''"
                    >
                        <div class="shero-metric-icon compliance" :class="{ 'is-optimal': allDone }">
                            <SopRing
                                :value="compliancePct"
                                :size="isMobile ? 28 : 34"
                                :stroke="isMobile ? 3.5 : 4"
                                :color="allDone ? '#10b981' : '#7c3aed'"
                                :color2="allDone ? '#34d399' : '#a78bfa'"
                            />
                        </div>
                        <div class="shero-metric-info min-w-0">
                            <div class="d-flex align-items-baseline gap-1 min-w-0">
                                <span class="shero-metric-num tnum text-truncate">{{ compliancePct }}%</span>
                                <span class="shero-metric-sub tnum d-none d-md-inline text-truncate text-muted">
                                    ({{ counts.selesai || 0 }}/{{ totalTugas }})
                                </span>
                            </div>
                            <span class="shero-metric-lbl text-truncate">
                                <span class="d-none d-sm-inline">Skor Kepatuhan</span>
                                <span class="d-inline d-sm-none">Kepatuhan</span>
                            </span>
                        </div>
                        <span v-if="statusAktif === ''" class="shero-active-dot" />
                    </div>

                    <!-- 2. Lensa Tugas Perlu Dibaca -->
                    <div
                        class="shero-metric-card clickable min-w-0"
                        :class="{
                            active: statusAktif === 'perlu_dibaca',
                            disabled: !counts.perlu_dibaca && !counts.diperbarui
                        }"
                        role="button"
                        tabindex="0"
                        title="Saring tugas yang menunggu atau sedang dipelajari"
                        @click="toggleStatus('perlu_dibaca')"
                        @keydown.enter="toggleStatus('perlu_dibaca')"
                    >
                        <div class="shero-metric-icon reading">
                            <HcisIcon name="journal-bookmark-fill" :size="isMobile ? 'xs' : 'sm'" />
                        </div>
                        <div class="shero-metric-info min-w-0">
                            <span class="shero-metric-num tnum text-truncate">
                                {{ (counts.perlu_dibaca || 0) + (counts.diperbarui || 0) }}
                            </span>
                            <span class="shero-metric-lbl text-truncate">
                                <span class="d-none d-sm-inline">Perlu Dibaca</span>
                                <span class="d-inline d-sm-none">Perlu Baca</span>
                            </span>
                        </div>
                        <span v-if="statusAktif === 'perlu_dibaca'" class="shero-active-dot" />
                    </div>

                    <!-- 3. Lensa Terlambat / Tenggat Waktu -->
                    <div
                        class="shero-metric-card clickable min-w-0"
                        :class="{
                            active: statusAktif === 'terlambat',
                            'has-alert': (counts.terlambat || 0) > 0,
                            disabled: !counts.terlambat
                        }"
                        role="button"
                        tabindex="0"
                        :title="(counts.terlambat || 0) > 0 ? `${counts.terlambat} dokumen lewat tenggat kepatuhan!` : 'Semua dokumen tepat waktu'"
                        @click="toggleStatus('terlambat')"
                        @keydown.enter="toggleStatus('terlambat')"
                    >
                        <div class="shero-metric-icon" :class="(counts.terlambat || 0) > 0 ? 'urgent' : 'on-time'">
                            <HcisIcon
                                :name="(counts.terlambat || 0) > 0 ? 'exclamation-octagon-fill' : 'check2-circle'"
                                :size="isMobile ? 'xs' : 'sm'"
                            />
                        </div>
                        <div class="shero-metric-info min-w-0">
                            <span class="shero-metric-num tnum text-truncate" :class="{ 'text-danger': (counts.terlambat || 0) > 0 }">
                                {{ counts.terlambat || 0 }}
                            </span>
                            <span class="shero-metric-lbl text-truncate">
                                <span class="d-none d-sm-inline">{{ (counts.terlambat || 0) > 0 ? 'Lewat Tenggat' : 'Terlambat' }}</span>
                                <span class="d-inline d-sm-none">Terlambat</span>
                            </span>
                        </div>
                        <span v-if="statusAktif === 'terlambat'" class="shero-active-dot" />
                    </div>
                </div>
            </template>
        </HcisHeader>

        <!--
            ══ 2. BILAH SARINGAN RESMI (HcisFilterBar Suite) ══
            Menyeragamkan bilah pencarian, alih tampilan (Kartu · Tabel · Dokumen),
            saringan cepat (quick chips), dan saringan multi-parameter selaras
            dengan arsitektur Public Document (SopRepository).
        -->
        <HcisFilterBar
            :search="listFilters.search"
            :search-placeholder="searchPlaceholder"
            :command-key="true"
            :show-view-toggle="true"
            :view-mode="tampilan"
            :view-options="daftarTampilan"
            :filters="filterBarFilters"
            :filter-values="filterBarValues"
            :quick-chips="filterQuickChips"
            :active-chip-value="statusAktif"
            :results-count="itemsTampil ? itemsTampil.length : 0"
            sentiment="purple"
            variant="glass"
            class="mb-4"
            @update:search="onSearchUpdate"
            @update:view-mode="pilihTampilan"
            @filter-change="onFilterBarChange"
            @chip-click="onChipFilterClick"
            @reset="onFilterBarReset"
            @command="fokusCari"
        />

        <!--
            ══ 3. DAFTAR TUGAS KEPATUHAN ══

            Seluruh bentuknya — rail pengelompokan, toolbar, tiga bentuk
            tampilan — hidup di SopListingDokumen, dan Public Document memanggil
            komponen yang SAMA. Perubahan cara daftar tampil dikerjakan di sana
            sekali; menyalinnya kembali ke halaman ini akan diam-diam memutus
            ikatan itu.

            Halaman ini mengelompokkan DI MEMORI (bangunGrup), karena ia memang
            sudah memegang seluruh tugas si karyawan. Karena itu `paginasi`
            tidak dikirim dan bilah halaman tak pernah lahir.
        -->
        <div class="mb-5">
            <SopListingDokumen
                :rail-mode="railMode"
                :tree="treeData"
                :active-tree-dept="listFilters.departemen"
                :active-tree-jenis="listFilters.jenis"
                :sumbu-tersedia="sumbuTersedia"
                :sumbu-aktif="sumbuAktif"
                :sumbu-meta="sumbuMeta"
                :grup="grupSumbu"
                :grup-terpilih="grupTerpilih"
                :penempatan="penempatan"
                :total-semua="visibleItems.length"
                :persen-semua="persenSemua"
                :items="itemsTampil"
                :dokumen="itemsTampil"
                :tampilan="tampilan"
                :daftar-tampilan="daftarTampilan"
                :loading="loading"
                :busy-id="busyId"
                :sub="mkSub"
                :satuan="satuanTampil"
                :judul-semua="judulSemua"
                :ikon-semua="ikonSemua"
                :saringan-aktif="saringanAktif"
                :kosong="kosongListing"
                :tampilkan-view-toggle="false"
                :tampilkan-info-jumlah="false"
                @update:rail-mode="setRailMode"
                @pilih-tree-dept="onPilihTreeDept"
                @pilih-tree-sub="onPilihTreeSub"
                @pilih-tree-semua="onPilihTreeSemua"
                @update:sumbu="pilihSumbu"
                @update:grup="pilihGrup"
                @update:tampilan="pilihTampilan"
                @hapus-saringan="hapusSaringan"
                @reset-saringan="onFilterBarReset"
                @open="openDetail"
            >
                <template #kosong-aksi>
                    <HcisButton
                        v-if="hasActiveFilter"
                        label="Reset Saringan"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-counterclockwise"
                        @click="onFilterBarReset"
                    />
                    <HcisButton
                        label="Buka Public Document"
                        variant="primary"
                        size="sm"
                        href="/sop/repository"
                        leading-icon="journal-bookmark-fill"
                    />
                </template>
            </SopListingDokumen>
        </div>

        <!-- Command Palette -->
        <CommandPalette
            :show="palet"
            :sources="sumberPalet"
            placeholder="Cari Dokumen atau perintah…"
            recent-key="sop_saya_cp_terakhir"
            :recent-valid="riwayatMasihSah"
            @close="palet = false"
            @jalankan="jalankanPalet"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import HcisHeader from '@/components/ui/HcisHeader.vue';
import HcisFilterBar from '@/components/ui/HcisFilterBar.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import SopRing from '../../Shared/components/ui/SopRing.vue';
import SopListingDokumen from '../../Shared/components/listing/SopListingDokumen.vue';
import CommandPalette from '../../../components/ui/CommandPalette.vue';
import { sumberSopSaya } from './sumberPalet.js';
import StatusPill from '../../Shared/components/ui/StatusPill.vue';
import { SOP_URL, STATUS_PAKET, temaAksen } from '../../Shared/support/constants.js';
import {
    SUMBU,
    sumbuDari,
    bangunGrup,
    bangunPohonFolder,
    opsiSumbu,
    terapkanSaringan,
    EMBER_TENGGAT,
    EMBER_ESTIMASI,
    OPSI_SUMBER,
} from '../../Shared/components/listing/sumbu.js';
import { metaPrioritas, setPrioritas } from '../../Shared/support/prioritas.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import { useActivityCenter } from '../../../../../composables/useActivityCenter.js';
import '../../Shared/support/sopTheme.css';

const PRIO_RANK = { KRITIS: 0, TINGGI: 1, NORMAL: 2 };
const STATUS_ORDER = Object.keys(STATUS_PAKET);
const SORT_LABELS = {
    default: 'terbaru ditugaskan',
    status: 'tahapan',
    urgensi: 'paling mendesak',
    tenggat: 'tenggat terdekat',
    progres_tinggi: 'progres tertinggi',
    progres_rendah: 'progres terendah',
    nama: 'nama A–Z',
    prioritas: 'prioritas',
};

function sortMultiTierDefault(arr) {
    const getTier = (item) => {
        const status = item.Status_Paket || item.Status_Progress || (item.Persen >= 100 ? 'SELESAI' : (item.Persen > 0 ? 'PROGRESS' : 'BELUM'));
        if (status === 'SELESAI' || status === 'DIKONFIRMASI' || (item.Persen !== undefined && item.Persen >= 100)) {
            return 3;
        }
        if (status === 'PROGRESS' || status === 'DIBACA' || (item.Persen !== undefined && item.Persen > 0) || item.Waktu_Buka || item.Terakhir_Akses) {
            return 2;
        }
        return 1;
    };

    const getTimestamp = (item, tier) => {
        if (tier === 1) {
            const t = item.Tanggal_Assign || item.Waktu_Assign || item.Tanggal_Publish || item.Created_At;
            return t ? new Date(t).getTime() : 0;
        }
        if (tier === 2) {
            const t = item.Terakhir_Akses || item.Waktu_Buka || item.Updated_At || item.Tanggal_Assign;
            return t ? new Date(t).getTime() : 0;
        }
        const t = item.Tanggal_Selesai || item.Waktu_Konfirmasi || item.Terakhir_Konfirmasi || item.Updated_At;
        return t ? new Date(t).getTime() : 0;
    };

    arr.sort((a, b) => {
        const tierA = getTier(a);
        const tierB = getTier(b);
        if (tierA !== tierB) {
            return tierA - tierB;
        }
        const timeA = getTimestamp(a, tierA);
        const timeB = getTimestamp(b, tierB);
        return timeB - timeA;
    });
}

export default {
    name: 'SopSaya',
    props: { prioritas: { type: Array, default: () => [] } },
    components: {
        HcisHeader,
        HcisFilterBar,
        HcisButton,
        HcisIcon,
        SopRing,
        SopListingDokumen,
        CommandPalette,
        StatusPill,
    },
    mixins: [pageMeta],
    data() {
        return {
            isMobile: typeof window !== 'undefined' ? window.innerWidth < 768 : false,
            mintaKartu: false,
            statusAktif: '',
            grupTerpilih: '',
            railMode: 'sumbu',
            palet: false,
            tabs: [
                { key: 'terlambat', label: 'Terlambat', icon: 'exclamation-octagon' },
                { key: 'perlu_dibaca', label: 'Perlu Dibaca', icon: 'journal-bookmark' },
                /* Ember tersendiri, dan sengaja BUKAN digabung ke 'Perlu Dibaca'.
                   "Belum pernah dibaca" dan "berubah sesudah Anda menyelesaikannya"
                   adalah dua pekerjaan berbeda; menyatukannya membuat yang kedua
                   tak pernah kelihatan. `sorot` menyalakan gaya menonjol HANYA
                   ketika embernya benar-benar berisi. */
                { key: 'perlu_ulang', label: 'Perlu Baca Ulang', icon: 'arrow-repeat', sorot: true },
                { key: 'diperbarui', label: 'Sedang Dibaca', icon: 'arrow-repeat' },
                { key: 'selesai', label: 'Tuntas', icon: 'check2-circle' },
            ],
            counts: {},
            allItems: [],
            allDokumen: [],
            isLeader: false,
            loading: true,
            busyId: null,
            viewMode: 'grid',
            /*
             * Bentuk daftar. TIGA nilai, bukan dua: 'kartu' | 'tabel' | 'dokumen'.
             * viewMode di atas dipertahankan karena masih dipakai menyimpan
             * preferensi lama dan dibaca beberapa tempat; 'tampilan' yang kini
             * menentukan apa yang dirender.
             */
            tampilan: 'kartu',
            /*
             * Sumbu pengelompokan rail. Departemen bakunya. Pilihannya
             * diingat di localStorage seperti mode tampilan: memilih ulang
             * sumbu setiap kali membuka halaman adalah pekerjaan yang tak
             * pernah diminta siapa pun.
             */
            sumbuAktif: 'departemen',
            listFilters: {
                search: '',
                viewMode: 'grid',
                prioritas: '',
                sort: 'default',
                // Bernilai jamak: kepemilikan melekat pada dokumen, dan satu
                // paket boleh menyentuh beberapa departemen sekaligus.
                departemen: [],
                jenis: [],
                apqc: [],
                tenggat: '',
                sumber: '',
                estimasi: '',
                perlu_ulang: false,
            },
        };
    },
    computed: {
        urlTim() {
            return SOP_URL.tim();
        },
        totalTugas() {
            const c = this.counts || {};
            return (c.perlu_dibaca || 0) + (c.terlambat || 0) + (c.diperbarui || 0) + (c.selesai || 0);
        },
        compliancePct() {
            if (!this.totalTugas) return 0;
            return Math.round(((this.counts.selesai || 0) / this.totalTugas) * 100);
        },
        allDone() {
            return this.totalTugas > 0 && this.compliancePct === 100;
        },
        ringSize() {
            return this.isMobile ? 62 : 84;
        },
        ringStroke() {
            return this.isMobile ? 6 : 8;
        },
        formattedDateMobile() {
            try {
                return new Intl.DateTimeFormat('id-ID', {
                    day: 'numeric',
                    month: 'short',
                }).format(new Date());
            } catch (e) {
                return '';
            }
        },
        searchPlaceholder() {
            return this.isMobile
                ? 'Cari dokumen / nomor SOP…'
                : 'Cari nama dokumen, nomor prosedur, atau kata kunci…';
        },
        filterQuickChips() {
            const chips = [
                {
                    value: '',
                    label: 'Semua Tugas',
                    badge: this.totalTugas,
                    icon: 'grid-1x2-fill',
                },
                {
                    value: 'terlambat',
                    label: 'Terlambat',
                    badge: this.counts.terlambat || 0,
                    icon: 'exclamation-triangle-fill',
                    color: 'danger',
                    ping: (this.counts.terlambat || 0) > 0,
                },
                {
                    value: 'perlu_dibaca',
                    label: 'Perlu Dibaca',
                    badge: this.counts.perlu_dibaca || 0,
                    icon: 'journal-text',
                    color: 'brand',
                },
            ];

            if (this.counts.perlu_ulang > 0) {
                chips.push({
                    value: 'perlu_ulang',
                    label: 'Perlu Baca Ulang',
                    badge: this.counts.perlu_ulang,
                    icon: 'arrow-repeat',
                    color: 'warning',
                });
            }

            chips.push(
                {
                    value: 'diperbarui',
                    label: 'Sedang Dibaca',
                    badge: this.counts.diperbarui || 0,
                    icon: 'arrow-repeat',
                    color: 'warning',
                },
                {
                    value: 'selesai',
                    label: 'Tuntas',
                    badge: this.counts.selesai || 0,
                    icon: 'check-circle-fill',
                    color: 'success',
                }
            );

            return chips;
        },
        filterBarFilters() {
            return [
                /*
                 * SARINGAN IDENTITAS DOKUMEN — departemen, jenis, proses.
                 *
                 * Pilihannya dirakit dari tugas yang benar-benar dimiliki orang
                 * ini, bukan dari master. Menawarkan seluruh master membuat
                 * dropdown penuh pilihan yang semuanya menghasilkan nol baris,
                 * dan yang memilihnya akan menyimpulkan tugasnya hilang alih-alih
                 * bahwa pilihan itu memang tak berlaku baginya.
                 */
                {
                    key: 'departemen',
                    label: 'Departemen Pemilik',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Departemen',
                    options: this.opsiDepartemen,
                    clearable: true,
                },
                {
                    key: 'jenis',
                    label: 'Jenis Dokumen',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Jenis',
                    options: this.opsiJenis,
                    clearable: true,
                },
                {
                    key: 'tenggat',
                    label: 'Tenggat',
                    type: 'select',
                    placeholder: 'Semua Tenggat',
                    // Ember, bukan tanggal. Yang ditanyakan orang yang membuka
                    // daftar tugas adalah "mana yang mepet", bukan "mana yang
                    // jatuh pada 14 September".
                    options: EMBER_TENGGAT.map((e) => ({ label: e.label, value: e.value })),
                    clearable: true,
                },
                {
                    key: 'prioritas',
                    label: 'Tingkat Prioritas',
                    type: 'select',
                    placeholder: 'Semua Prioritas',
                    options: [
                        { label: 'Sangat Penting (Kritis)', value: 'KRITIS' },
                        { label: 'Tinggi', value: 'TINGGI' },
                        { label: 'Normal', value: 'NORMAL' },
                    ],
                    clearable: true,
                },
                {
                    key: 'perlu_ulang',
                    label: 'Perlu Dibaca Ulang',
                    type: 'switch',
                    activeText: 'Hanya yang berubah versi',
                    inactiveText: '',
                },
                {
                    key: 'apqc',
                    label: 'Proses APQC',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Proses',
                    options: this.opsiApqc,
                    clearable: true,
                },
                {
                    key: 'sumber',
                    label: 'Sumber Penugasan',
                    type: 'select',
                    // Menjawab "kenapa ini jadi tugas saya" — pertanyaan yang
                    // sebelumnya tak punya jawaban di layar mana pun.
                    placeholder: 'Semua Sumber',
                    options: OPSI_SUMBER.map((o) => ({ label: o.label, value: o.value })),
                    clearable: true,
                },
                {
                    key: 'estimasi',
                    label: 'Perkiraan Waktu Baca',
                    type: 'select',
                    placeholder: 'Semua Durasi',
                    options: EMBER_ESTIMASI.map((e) => ({ label: e.label, value: e.value })),
                    clearable: true,
                },
                {
                    key: 'sort',
                    label: 'Urutkan Berdasarkan',
                    type: 'select',
                    placeholder: 'Terbaru Ditugaskan',
                    options: [
                        { label: 'Terbaru Ditugaskan → Progres → Selesai (Default)', value: 'default' },
                        { label: 'Paling mendesak (Urgensi Tenggat)', value: 'urgensi' },
                        { label: 'Tahapan (Baru → Proses → Selesai)', value: 'status' },
                        { label: 'Tenggat terdekat', value: 'tenggat' },
                        { label: 'Progres tertinggi', value: 'progres_tinggi' },
                        { label: 'Progres terendah', value: 'progres_rendah' },
                        { label: 'Nama Dokumen (A–Z)', value: 'nama' },
                        { label: 'Prioritas Tertinggi', value: 'prioritas' },
                    ],
                    clearable: false,
                },
            ];
        },
        filterBarValues() {
            const f = this.listFilters;

            return {
                departemen: f.departemen || [],
                jenis: f.jenis || [],
                apqc: f.apqc || [],
                tenggat: f.tenggat || '',
                sumber: f.sumber || '',
                estimasi: f.estimasi || '',
                perlu_ulang: !!f.perlu_ulang,
                prioritas: f.prioritas || '',
                sort: f.sort || 'default',
            };
        },

        /*
         * Pilihan saringan identitas, dirakit dari SELURUH tugas — bukan dari
         * tugas yang sedang tersaring. Menyusutkan pilihan mengikuti hasil
         * membuat saringan tak bisa dilonggarkan lagi: memilih "HSE" membuat
         * "Produksi" lenyap dari dropdown, dan satu-satunya jalan keluar adalah
         * mereset semuanya.
         */
        opsiDepartemen() {
            return opsiSumbu(this.allItems, 'departemen');
        },
        opsiJenis() {
            return opsiSumbu(this.allItems, 'jenis');
        },
        opsiApqc() {
            return opsiSumbu(this.allItems, 'apqc');
        },

        /**
         * Baris yang sedang jadi satuan halaman.
         *
         * Di tampilan Dokumen satuannya ITEM, di dua tampilan lain PAKET. Server
         * sudah membentuk baris dokumen menyerupai kartu — daftar departemen,
         * jenis, dan atribut paket induk semuanya ada — sehingga seluruh
         * penyaringan, pengelompokan, dan pengurutan di bawah bekerja tanpa satu
         * pun cabang khusus.
         */
        sumberBaris() {
            return this.tampilan === 'dokumen' ? this.allDokumen : this.allItems;
        },
        visibleItems() {
            const f = this.listFilters;
            const dasar = this.statusAktif
                ? this.sumberBaris.filter((x) => x.Grup === this.statusAktif)
                : this.sumberBaris.slice();

            // Seluruh penyaringan hidup di sumbu.js supaya bisa diuji tanpa
            // merender halaman ini beserta permintaan HTTP-nya.
            const arr = terapkanSaringan(dasar, f, (x) => this.daysLeft(x));

            const rank = (p) => PRIO_RANK[p] ?? 3;
            switch (f.sort) {
                case 'status':
                    arr.sort((a, b) => {
                        const d = STATUS_ORDER.indexOf(a.Status_Paket) - STATUS_ORDER.indexOf(b.Status_Paket);
                        return d !== 0 ? d : this.daysLeft(a) - this.daysLeft(b);
                    });
                    break;
                case 'tenggat':
                    arr.sort((a, b) => this.daysLeft(a) - this.daysLeft(b));
                    break;
                case 'progres_tinggi':
                    arr.sort((a, b) => b.Persen - a.Persen);
                    break;
                case 'progres_rendah':
                    arr.sort((a, b) => a.Persen - b.Persen);
                    break;
                case 'nama':
                    arr.sort((a, b) => (a.Judul || a.Nama_Paket || '').localeCompare(b.Judul || b.Nama_Paket || ''));
                    break;
                case 'prioritas':
                    arr.sort((a, b) => rank(a.Prioritas) - rank(b.Prioritas));
                    break;
                case 'urgensi':
                    arr.sort((a, b) => {
                        const d = this.daysLeft(a) - this.daysLeft(b);
                        return d !== 0 ? d : rank(a.Prioritas) - rank(b.Prioritas);
                    });
                    break;
                case 'default':
                default:
                    sortMultiTierDefault(arr);
                    break;
            }
            return arr;
        },
        daftarTampilan() {
            return [
                { key: 'kartu', label: 'Kartu', ikon: 'grid-fill', judul: 'Satu kartu per penugasan paket' },
                { key: 'tabel', label: 'Tabel', ikon: 'list-ul', judul: 'Satu baris per penugasan paket' },
                { key: 'dokumen', label: 'Dokumen', ikon: 'file-earmark-text-fill', judul: 'Satu baris per dokumen yang harus dibaca' },
            ];
        },
        /* Satuannya ikut berubah, dan kata itu penting: "27 Dokumen" dan
           "6 Prosedur" pada halaman yang sama tanpa keterangan akan dibaca
           sebagai dua angka yang bertentangan. */
        satuanTampil() {
            return this.tampilan === 'dokumen' ? 'Dokumen' : 'Prosedur';
        },

        sumbuTersedia() {
            return SUMBU;
        },
        sumbuMeta() {
            return sumbuDari(this.sumbuAktif);
        },

        /**
         * Kelompok rail pada sumbu yang sedang aktif.
         *
         * Pada sumbu bernilai jamak, satu tugas menempati beberapa kelompok —
         * karena itu `penempatan` dipisahkan dari jumlah tugas, dan keduanya
         * dikirim ke rail. Menampilkan salah satunya saja membuat pembaca
         * menyimpulkan yang lain, dan kesimpulannya akan salah.
         */
        rakitanGrup() {
            return bangunGrup(this.visibleItems, this.sumbuAktif, temaAksen);
        },
        grupSumbu() {
            return this.rakitanGrup.grup;
        },
        penempatan() {
            return this.rakitanGrup.penempatan;
        },

        sumberPalet() {
            return sumberSopSaya(() => ({
                items: this.allItems,
                grup: this.grupSumbu,
                counts: this.counts,
                isLeader: this.isLeader,
            }));
        },
        grupAktif() {
            return this.grupSumbu.find((g) => g.nama === this.grupTerpilih) || null;
        },
        itemsTampil() {
            return this.grupAktif ? this.grupAktif.items : this.visibleItems;
        },
        persenSemua() {
            const t = this.visibleItems.reduce((a, k) => a + (k.Total_Item || 0), 0);
            const d = this.visibleItems.reduce((a, k) => a + (k.Item_Selesai || 0), 0);
            return t ? Math.round((d / t) * 100) : 0;
        },
        judulSemua() {
            if (this.listFilters.search && this.listFilters.search.trim().length >= 2) {
                return `Pencarian: "${this.listFilters.search.trim()}"`;
            }
            if (this.listFilters.departemen?.length && this.listFilters.jenis?.length) {
                const d = this.listFilters.departemen.join(', ');
                const j = this.listFilters.jenis.join(', ');
                return `${d} · ${j}`;
            }
            if (this.listFilters.departemen?.length) {
                return this.listFilters.departemen.length === 1
                    ? `Departemen ${this.listFilters.departemen[0]}`
                    : `Departemen (${this.listFilters.departemen.join(', ')})`;
            }
            if (this.listFilters.jenis?.length) {
                return this.listFilters.jenis.length === 1
                    ? `Jenis: ${this.listFilters.jenis[0]}`
                    : `Jenis Dokumen (${this.listFilters.jenis.join(', ')})`;
            }
            if (this.listFilters.prioritas) {
                return `Prioritas: ${this.listFilters.prioritas}`;
            }
            if (this.listFilters.apqc) {
                return `Proses: ${this.listFilters.apqc}`;
            }
            if (this.statusAktif) {
                const map = {
                    MENUNGGU: 'Menunggu Dipelajari',
                    DIBACA: 'Sedang Dipelajari',
                    LEWAT: 'Lewat Tenggat Waktu',
                    SELESAI: 'Selesai Kepatuhan',
                    WAJIB_ULANG: 'Wajib Baca Ulang',
                    perlu_ulang: 'Perlu Baca Ulang',
                };
                return map[this.statusAktif] || `Status: ${this.statusAktif}`;
            }

            return this.tampilan === 'dokumen' ? 'Semua Dokumen Kepatuhan' : 'Semua Tugas Kepatuhan';
        },
        mkSub() {
            const g = this.grupAktif;
            if (g) {
                const bagian = [`${g.selesai}/${g.total} dokumen tuntas`, `${g.item_selesai}/${g.item_total} item`];
                if (g.terlambat) bagian.push(`${g.terlambat} terlambat`);
                return bagian.join(' · ');
            }

            if (this.listFilters.search && this.listFilters.search.trim().length >= 2) {
                return `Menampilkan tugas kepatuhan yang cocok dengan kata kunci “${this.listFilters.search.trim()}”.`;
            }
            if (this.listFilters.departemen?.length && this.listFilters.jenis?.length) {
                return `Daftar tugas kepatuhan kategori ${this.listFilters.jenis.join(', ')} untuk departemen ${this.listFilters.departemen.join(', ')}.`;
            }
            if (this.listFilters.departemen?.length) {
                return `Daftar tugas kepatuhan prosedur operasional pada departemen ${this.listFilters.departemen.join(', ')}.`;
            }
            if (this.listFilters.jenis?.length) {
                return `Daftar tugas kepatuhan untuk klasifikasi jenis ${this.listFilters.jenis.join(', ')}.`;
            }
            if (this.listFilters.prioritas) {
                return `Daftar tugas kepatuhan dengan tingkat prioritas ${this.listFilters.prioritas}.`;
            }
            if (this.listFilters.apqc) {
                return `Daftar tugas kepatuhan pada rumpun proses bisnis ${this.listFilters.apqc}.`;
            }
            if (this.statusAktif) {
                const map = {
                    MENUNGGU: 'Daftar prosedur kerja wajib yang belum Anda buka dan selesaikan.',
                    DIBACA: 'Daftar prosedur yang sedang dalam proses pembacaan materi atau pengerjaan kuis.',
                    LEWAT: 'Daftar prosedur kerja yang telah melampaui batas waktu kepatuhan penyelesaian.',
                    SELESAI: 'Daftar prosedur kerja yang telah berhasil Anda selesaikan dan tercatat patuh.',
                    WAJIB_ULANG: 'Daftar prosedur yang mengalami revisi versi mayor dan perlu Anda pelajari ulang.',
                    perlu_ulang: 'Dokumen yang sudah pernah Anda tuntaskan, lalu direvisi — versi terbarunya belum Anda baca.',
                };
                if (map[this.statusAktif]) return map[this.statusAktif];
            }

            const satuan = this.tampilan === 'dokumen' ? 'dokumen' : 'prosedur';
            const label = SORT_LABELS[this.listFilters.sort] || 'paling mendesak';
            return `Daftar seluruh ${satuan} wajib yang harus Anda baca dan selesaikan · terurut ${label}.`;
        },
        ikonSemua() {
            if (this.listFilters.search && this.listFilters.search.trim().length >= 2) return 'search';
            if (this.listFilters.departemen?.length && this.listFilters.jenis?.length) return 'folder2-open';
            if (this.listFilters.departemen?.length) return 'building';
            if (this.listFilters.jenis?.length) return 'journal-text';
            if (this.listFilters.prioritas) return 'flag-fill';
            if (this.listFilters.apqc) return 'diagram-3';
            if (this.statusAktif === 'LEWAT') return 'exclamation-octagon-fill';
            if (this.statusAktif === 'SELESAI') return 'check-circle-fill';
            if (this.statusAktif === 'MENUNGGU') return 'clock-history';
            if (this.statusAktif === 'DIBACA') return 'book-half';
            if (this.statusAktif === 'WAJIB_ULANG' || this.statusAktif === 'perlu_ulang') return 'arrow-repeat';
            return 'clipboard2-check-fill';
        },
        sisaMenit() {
            return this.allItems
                .filter((k) => k.Grup !== 'selesai' && k.Estimasi)
                .reduce((t, k) => t + Math.round(k.Estimasi * (1 - (k.Persen || 0) / 100)), 0);
        },
        sisaMenitTeks() {
            const m = this.sisaMenit;
            if (m < 60) return `${m} menit`;
            const j = Math.floor(m / 60);
            const sisa = m % 60;
            return sisa ? `${j} jam ${sisa} menit` : `${j} jam`;
        },
        focusTask() {
            const pend = this.allItems.filter((x) => x.Grup !== 'selesai');
            if (!pend.length) return null;
            return pend.slice().sort((a, b) => {
                if (a.Is_Terlambat !== b.Is_Terlambat) return a.Is_Terlambat ? -1 : 1;
                return this.daysLeft(a) - this.daysLeft(b);
            })[0];
        },
        nearestDeadline() {
            const withDl = this.allItems.filter((x) => x.Grup !== 'selesai' && x.Tenggat);
            if (!withDl.length) return null;
            return Math.min(...withDl.map((x) => this.daysLeft(x)));
        },
        segments() {
            const t = this.totalTugas;
            if (!t) return [];
            return this.tabs
                .map((tb) => ({
                    key: tb.key,
                    label: tb.label,
                    count: this.counts[tb.key] || 0,
                    pct: ((this.counts[tb.key] || 0) / t) * 100,
                }))
                .filter((s) => s.pct > 0);
        },
        nearestDeadlineText() {
            const d = this.nearestDeadline;
            if (d === null) return '';
            if (d < 0) return `lewat ${Math.abs(d)} hari`;
            if (d === 0) return 'hari ini';
            return `${d} hari lagi`;
        },
        /**
         * Pil saringan aktif di toolbar daftar.
         *
         */
        treeData() {
            return bangunPohonFolder(this.sumberBaris);
        },
        saringanAktif() {
            const out = [];
            const f = this.listFilters;

            if (this.statusAktif) {
                out.push({
                    kunci: 'status',
                    label: 'Status',
                    nilai: this.labelStatus(this.statusAktif),
                    ikon: 'check2-circle',
                });
            }
            if (this.grupTerpilih) {
                out.push({
                    kunci: 'grup',
                    label: this.sumbuMeta.label,
                    nilai: this.grupTerpilih,
                    ikon: 'diagram-3',
                    kelas: 'kat',
                });
            }
            if (f.search && String(f.search).trim()) {
                out.push({
                    kunci: 'search',
                    label: 'Cari',
                    nilai: `"${String(f.search).trim()}"`,
                    ikon: 'search',
                });
            }
            if (f.departemen && f.departemen.length) {
                out.push({
                    kunci: 'departemen',
                    label: 'Departemen',
                    nilai: f.departemen.join(', '),
                    ikon: 'building',
                });
            }
            if (f.jenis && f.jenis.length) {
                out.push({
                    kunci: 'jenis',
                    label: 'Jenis Dokumen',
                    nilai: f.jenis.join(', '),
                    ikon: 'journal-text',
                });
            }
            if (f.prioritas) {
                out.push({
                    kunci: 'prioritas',
                    label: 'Prioritas',
                    nilai: metaPrioritas(f.prioritas).label || f.prioritas,
                    ikon: 'flag-fill',
                });
            }
            if (f.apqc && f.apqc.length) {
                out.push({
                    kunci: 'apqc',
                    label: 'APQC',
                    nilai: f.apqc.join(', '),
                    ikon: 'diagram-2',
                });
            }
            if (f.tenggat) {
                const tg = EMBER_TENGGAT.find((e) => e.value === f.tenggat);
                out.push({
                    kunci: 'tenggat',
                    label: 'Tenggat',
                    nilai: tg ? tg.label : f.tenggat,
                    ikon: 'calendar-event',
                });
            }
            if (f.sumber) {
                const sb = OPSI_SUMBER.find((o) => o.value === f.sumber);
                out.push({
                    kunci: 'sumber',
                    label: 'Sumber',
                    nilai: sb ? sb.label : f.sumber,
                    ikon: 'box-arrow-in-down-right',
                });
            }
            if (f.estimasi) {
                const es = EMBER_ESTIMASI.find((e) => e.value === f.estimasi);
                out.push({
                    kunci: 'estimasi',
                    label: 'Estimasi',
                    nilai: es ? es.label : f.estimasi,
                    ikon: 'clock-history',
                });
            }
            if (f.perlu_ulang) {
                out.push({
                    kunci: 'perlu_ulang',
                    label: 'Pembaruan',
                    nilai: 'Perlu Dibaca Ulang',
                    ikon: 'stars',
                });
            }

            return out;
        },
        kosongListing() {
            const c = this.emptyStateConfig;

            return { icon: c.icon, title: c.title, description: c.subtitle };
        },
        emptyStateConfig() {
            if (this.hasActiveFilter) {
                return {
                    icon: 'search',
                    title: 'Tidak Ada Hasil yang Cocok',
                    subtitle: 'Coba ubah kata kunci pencarian, saringan status, atau tingkat prioritas Anda.',
                };
            }
            const map = {
                perlu_dibaca: {
                    icon: 'check2-circle',
                    title: 'Tidak Ada yang Perlu Dibaca',
                    subtitle: 'Semua tugas aktif sudah Anda tangani dengan baik 🎉',
                },
                terlambat: {
                    icon: 'emoji-smile',
                    title: 'Tidak Ada Tugas yang Terlambat',
                    subtitle: 'Luar biasa, kepatuhan pengerjaan dokumen Anda tepat waktu.',
                },
                diperbarui: {
                    icon: 'stars',
                    title: 'Tidak Ada Pembaruan',
                    subtitle: 'Belum ada dokumen yang perlu dibaca ulang saat ini.',
                },
                selesai: {
                    icon: 'hourglass-split',
                    title: 'Belum Ada yang Selesai',
                    subtitle: 'Mulai pelajari dan baca dokumen untuk menuntaskan kepatuhan Anda.',
                },
            };
            return (
                map[this.statusAktif] || {
                    icon: 'inboxes',
                    title: 'Belum Ada Dokumen Ditugaskan',
                    subtitle: 'Tugas kepatuhan operasional akan muncul di sini saat dijadwalkan.',
                }
            );
        },
        hasActiveFilter() {
            const f = this.listFilters;

            return !!(
                f.search ||
                f.prioritas ||
                f.tenggat ||
                f.sumber ||
                f.estimasi ||
                f.perlu_ulang ||
                (f.departemen && f.departemen.length) ||
                (f.jenis && f.jenis.length) ||
                (f.apqc && f.apqc.length) ||
                this.statusAktif ||
                this.grupTerpilih
            );
        },
    },
    watch: {
        /*
         * Kelompok yang sedang dipilih bisa lenyap karena saringan digeser ATAU
         * karena sumbunya diganti — "HSE" tak punya arti di sumbu Prioritas.
         * Membiarkannya terpilih menghasilkan panel kosong tanpa satu pun
         * petunjuk kenapa, jadi pilihannya dilepas begitu tak lagi ada.
         */
        grupSumbu(baru) {
            if (this.grupTerpilih && !baru.some((g) => g.nama === this.grupTerpilih)) this.grupTerpilih = '';
        },
    },
    created() {
        setPrioritas(this.prioritas);
    },
    mounted() {
        try {
            const mode = localStorage.getItem('sop_saya_rail_mode');
            if (mode && ['sumbu', 'tree'].includes(mode)) this.railMode = mode;

            const s = localStorage.getItem('sop_saya_sumbu');
            if (s && SUMBU.some((x) => x.key === s)) this.sumbuAktif = s;

            const t = localStorage.getItem('sop_saya_tampilan');
            if (['kartu', 'tabel', 'dokumen'].includes(t)) this.tampilan = t;
            else this.tampilan = this.viewMode === 'table' ? 'tabel' : 'kartu';
        } catch (e) {
            /* noop */
        }
        this.handleResize = () => {
            this.isMobile = window.innerWidth < 768;
        };
        window.addEventListener('resize', this.handleResize, { passive: true });
        this.fetchData();
    },
    beforeUnmount() {
        if (this.handleResize) {
            window.removeEventListener('resize', this.handleResize);
        }
    },
    methods: {
        /** Cabut satu pil saringan dari toolbar daftar. */
        hapusSaringan(kunci) {
            if (kunci === 'status') this.statusAktif = '';
            else if (kunci === 'grup') this.grupTerpilih = '';
            else if (kunci === 'search') this.listFilters.search = '';
            else if (kunci === 'prioritas') this.listFilters.prioritas = '';
            else if (kunci === 'departemen') this.listFilters.departemen = [];
            else if (kunci === 'jenis') this.listFilters.jenis = [];
            else if (kunci === 'apqc') this.listFilters.apqc = [];
            else if (kunci === 'tenggat') this.listFilters.tenggat = '';
            else if (kunci === 'sumber') this.listFilters.sumber = '';
            else if (kunci === 'estimasi') this.listFilters.estimasi = '';
            else if (kunci === 'perlu_ulang') this.listFilters.perlu_ulang = false;
        },
        onPilihTreeDept(payload) {
            const nama = typeof payload === 'string'
                ? payload
                : (payload?.nama || payload?.dept || '');
            this.grupTerpilih = '';
            this.listFilters.departemen = nama ? [nama] : [];
            this.listFilters.jenis = [];
        },
        onPilihTreeSub(payload) {
            let deptNama = '';
            let jenisNama = '';
            if (payload) {
                deptNama = payload.dept || payload.parent?.nama || payload.parent || '';
                jenisNama = payload.jenis || payload.node?.nama || payload.node || '';
            }
            this.grupTerpilih = '';
            this.listFilters.departemen = deptNama ? [deptNama] : [];
            this.listFilters.jenis = jenisNama ? [jenisNama] : [];
        },
        onPilihTreeSemua() {
            this.grupTerpilih = '';
            this.listFilters.departemen = [];
            this.listFilters.jenis = [];
        },
        setRailMode(mode) {
            if (this.railMode === mode) return;
            this.railMode = mode;
            try {
                localStorage.setItem('sop_saya_rail_mode', mode);
            } catch (e) {}
            // Reset filter kelompokan saat pindah mode
            this.grupTerpilih = '';
            this.listFilters.departemen = [];
            this.listFilters.jenis = [];
        },
        jalankanPalet(item) {
            const a = (item && item.aksi) || {};
            if (a.url) {
                window.location.href = a.url;
                return;
            }
            if (a.grup !== undefined) this.grupTerpilih = a.grup;
            else if (a.status !== undefined) this.statusAktif = a.status;
            else if (a.kartu) this.unduhKartu();
            this.palet = false;
        },
        riwayatMasihSah(x) {
            if (!String(x.id).startsWith('s:')) return true;
            return this.allItems.some((k) => `s:${k.Id_SOP_Paket}` === x.id);
        },
        async unduhKartu() {
            if (this.mintaKartu) return;
            this.mintaKartu = true;
            try {
                const r = await axios.post('/api/v1/sop/saya/kartu-kepatuhan');
                if (r.data.success) {
                    ElMessage.success(r.data.message);
                    useActivityCenter().notifyNewTask();
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memproses kartu');
            } finally {
                this.mintaKartu = false;
            }
        },
        urlDetail(id) {
            return SOP_URL.detail(id);
        },
        prioLabel(p) {
            return metaPrioritas(p).label;
        },
        prioWarna(p) {
            return metaPrioritas(p).warna;
        },
        daysLeft(k) {
            if (!k.Tenggat) return 9999;
            const d = new Date(k.Tenggat);
            if (isNaN(d.getTime())) return 9998;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return Math.ceil((d.getTime() - today.getTime()) / 86400000);
        },
        deadlineInfo(k) {
            if (!k.Tenggat) return { text: 'Tanpa tenggat', icon: 'infinity', cls: 'idle' };
            const d = new Date(k.Tenggat);
            if (isNaN(d.getTime())) return { text: 'Tenggat ' + k.Tenggat, icon: 'calendar-event', cls: 'idle' };
            const days = this.daysLeft(k);
            if (days < 0) return { text: `Lewat ${Math.abs(days)} hari`, icon: 'exclamation-octagon', cls: 'bad' };
            if (days === 0) return { text: 'Jatuh tempo hari ini', icon: 'clock-fill', cls: 'bad' };
            if (days <= 3) return { text: `${days} hari lagi`, icon: 'clock-history', cls: 'warn' };
            return { text: `${days} hari lagi`, icon: 'clock', cls: 'idle' };
        },
        toggleStatus(key) {
            this.statusAktif = this.statusAktif === key ? '' : key;
        },
        labelStatus(key) {
            return (this.tabs.find((t) => t.key === key) || {}).label || key;
        },
        pilihGrup(nama) {
            this.grupTerpilih = nama;
        },
        /**
         * Ganti sumbu pengelompokan.
         *
         * Kelompok yang sedang dipilih SELALU dilepas, tanpa mencoba
         * memetakannya ke sumbu baru. Nama kelompok pada dua sumbu berbeda bisa
         * kebetulan sama — sebuah departemen bernama sama dengan sebuah jenis
         * dokumen — dan mempertahankannya akan menampilkan isi yang sama sekali
         * lain sambil tampak seperti pilihan yang sama.
         */
        /**
         * Ganti bentuk daftar.
         *
         * Kelompok yang sedang dipilih DIPERTAHANKAN, berbeda dari pergantian
         * sumbu: "Finance" berarti hal yang sama di tampilan kartu maupun
         * dokumen, hanya butiran barisnya yang berubah. Melepasnya akan
         * membuang penyaringan yang baru saja dilakukan orang hanya karena ia
         * ingin melihatnya dalam bentuk lain.
         */
        pilihTampilan(key) {
            if (key === this.tampilan) return;
            this.tampilan = key;
            this.viewMode = key === 'tabel' ? 'table' : 'grid';
            try {
                localStorage.setItem('sop_saya_tampilan', key);
                localStorage.setItem('sop_saya_view', this.viewMode);
            } catch (e) {
                /* noop */
            }
        },
        pilihSumbu(key) {
            if (key === this.sumbuAktif) return;
            this.sumbuAktif = key;
            this.grupTerpilih = '';
            try {
                localStorage.setItem('sop_saya_sumbu', key);
            } catch (e) {
                /* noop */
            }
        },
        onViewModeUpdate(mode) {
            this.viewMode = mode === 'table' ? 'table' : 'grid';
            this.tampilan = mode === 'table' ? 'tabel' : 'kartu';
            try {
                localStorage.setItem('sop_saya_view', this.viewMode);
                localStorage.setItem('sop_saya_tampilan', this.tampilan);
            } catch (e) {
                /* noop */
            }
        },
        onSearchUpdate(v) {
            this.listFilters.search = v;
        },
        fokusCari() {
            if (this.palet !== undefined && !this.isMobile) {
                this.palet = true;
            } else {
                this.$nextTick(() => {
                    const el = document.getElementById('hcis-filter-search-input');
                    if (el) el.focus();
                });
            }
        },
        onFilterBarChange({ key, value }) {
            this.listFilters = { ...this.listFilters, [key]: value };
        },
        onChipFilterClick(chip) {
            this.statusAktif = this.statusAktif === chip.value ? '' : chip.value;
        },
        onFilterBarReset() {
            this.statusAktif = '';
            this.grupTerpilih = '';
            this.listFilters = {
                search: '',
                prioritas: '',
                sort: 'default',
                viewMode: this.viewMode,
                departemen: [],
                jenis: [],
                apqc: [],
                tenggat: '',
                sumber: '',
                estimasi: '',
                perlu_ulang: false,
            };
            // Sumbu SENGAJA tidak ikut direset: ia cara melihat, bukan saringan.
            // Mengembalikannya ke sumbu baku setiap kali orang menekan "reset"
            // membatalkan pilihan yang justru dibuat supaya bertahan.
        },
        openDetail(k) {
            const id = k && k.Id_SOP_Paket ? k.Id_SOP_Paket : k;
            this.busyId = id;
            window.location.href = SOP_URL.detail(id);
        },
        async fetchData() {
            this.loading = true;
            try {
                const res = await axios.get('/api/v1/sop/saya', { params: { tab: 'all' } });
                if (res.data.success) {
                    this.counts = res.data.result.counts || {};
                    this.allItems = res.data.result.items || [];
                    this.allDokumen = res.data.result.dokumen || [];
                    this.isLeader = !!res.data.result.is_leader;
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Terjadi kesalahan sistem');
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>

<style scoped>
/* Date Pill di Header */
.hcis-date-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.38rem 0.85rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

/* ════ KONSOL INSTRUMEN KEPATUHAN (3-CARD GRID) ════ */
.shero-console-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    width: 100%;
}

.shero-metric-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 14px;
    padding: 0.65rem 0.95rem;
    color: var(--hcis-slate-800);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 2px 5px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.shero-metric-card.clickable {
    cursor: pointer;
    outline: none;
}

.shero-metric-card.clickable:hover {
    background: var(--hcis-white);
    transform: translateY(-2px);
    border-color: var(--hcis-violet-400);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 6px 18px -2px rgba(124, 58, 237, 0.16);
}

.shero-metric-card.active {
    background: var(--hcis-white);
    border-color: var(--hcis-violet-600);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 8px 22px -4px rgba(124, 58, 237, 0.24),
        0 0 0 1px var(--hcis-violet-600);
}

.shero-metric-card.disabled {
    opacity: 0.6;
    cursor: default;
}

.shero-metric-card.has-alert {
    border-color: rgba(244, 63, 94, 0.4);
    background: linear-gradient(135deg, rgba(255, 241, 242, 0.8) 0%, rgba(255, 255, 255, 0.95) 100%);
}

.shero-metric-card.has-alert.active {
    border-color: var(--hcis-red-600);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 8px 22px -4px rgba(225, 29, 72, 0.25),
        0 0 0 1px var(--hcis-red-600);
}

.shero-active-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-violet-600);
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.25);
}

.shero-metric-card.has-alert .shero-active-dot {
    background: var(--hcis-red-600);
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.25);
}

.shero-metric-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-violet-50) 0%, #ede9fe 100%);
    border: 1px solid rgba(221, 214, 254, 0.8);
    color: var(--hcis-violet-600);
    flex-shrink: 0;
}

.shero-metric-icon.compliance {
    background: transparent;
    border: none;
    padding: 0;
}

.shero-metric-icon.compliance :deep(.ring-center) {
    display: none !important;
}

.shero-metric-icon.reading {
    background: linear-gradient(135deg, var(--hcis-violet-50) 0%, #ede9fe 100%);
    border-color: rgba(221, 214, 254, 0.8);
    color: var(--hcis-violet-600);
}

.shero-metric-icon.urgent {
    background: linear-gradient(135deg, #ffe4e6 0%, #fecdd3 100%);
    border-color: rgba(253, 164, 175, 0.8);
    color: var(--hcis-red-600);
}

.shero-metric-icon.on-time {
    background: linear-gradient(135deg, var(--hcis-emerald-50) 0%, #d1fae5 100%);
    border-color: rgba(167, 243, 208, 0.8);
    color: var(--hcis-emerald-600);
}

.shero-metric-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.15;
}

.shero-metric-num {
    font-size: 1.25rem;
    font-weight: 850;
    color: var(--hcis-slate-900);
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.shero-metric-sub {
    font-size: 0.72rem;
    font-weight: 600;
}

.shero-metric-lbl {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 0.15rem;
}


/* ===== Responsive Mobile Header & Compact Unified Pod ===== */
@media (max-width: 767.98px) {
    /* Header Container Compact Spacing */
    .sop-scope :deep(.hcis-header) {
        margin-bottom: 0.75rem !important;
        border-radius: 14px !important;
    }

    /* Header Top: Single horizontal row (Identity on left, Compact Action on right) */
    :deep(.hcis-header__top) {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        flex-wrap: nowrap !important;
    }

    :deep(.hcis-header__identity) {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    :deep(.hcis-header__icon-badge) {
        width: 2rem !important;
        height: 2rem !important;
        font-size: 1rem !important;
        border-radius: var(--hcis-radius-md) !important;
        flex-shrink: 0 !important;
    }

    :deep(.hcis-header__title-row) {
        min-width: 0 !important;
        flex: 1 1 auto !important;
        overflow: hidden !important;
    }

    :deep(.hcis-header__kicker) {
        display: none !important;
    }

    :deep(.hcis-header__title),
    :deep(.hcis-header h1.hcis-header__title) {
        font-size: 0.95rem !important;
        font-weight: 800 !important;
        letter-spacing: -0.01em !important;
        line-height: 1.25 !important;
        margin: 0 !important;
        white-space: normal !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        overflow: hidden !important;
    }

    :deep(.hcis-header__subtitle) {
        display: none !important;
    }

    :deep(.hcis-header__actions) {
        width: auto !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top: none !important;
        flex-shrink: 0 !important;
        display: flex !important;
        align-items: center !important;
    }

    :deep(.hcis-header__actions .hcis-btn) {
        flex: 0 0 auto !important;
        width: auto !important;
    }

    .hcis-date-pill {
        display: none !important;
    }

    .shero-action-public-btn :deep(.hcis-btn) {
        padding: 0.28rem 0.6rem !important;
        font-size: 0.72rem !important;
        border-radius: 999px !important;
        white-space: nowrap !important;
        gap: 0.3rem !important;
        min-height: auto !important;
        height: auto !important;
    }

    :deep(.hcis-header__console) {
        margin-top: 0.5rem !important;
        padding-top: 0.5rem !important;
        border-top: 1px solid rgba(124, 58, 237, 0.12) !important;
    }

    .shero-console-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.45rem !important;
    }

    .shero-metric-card {
        padding: 0.45rem 0.45rem !important;
        gap: 0.4rem !important;
        border-radius: 11px !important;
    }

    .shero-metric-icon {
        width: 30px !important;
        height: 30px !important;
        border-radius: 8px !important;
    }

    .shero-metric-num {
        font-size: 1.05rem !important;
    }

    .shero-metric-sub {
        font-size: 0.65rem !important;
    }

    .shero-metric-lbl {
        font-size: 0.65rem !important;
        margin-top: 0.05rem !important;
    }

    .shero-active-dot {
        top: 5px !important;
        right: 5px !important;
        width: 5px !important;
        height: 5px !important;
    }

    :deep(.hcis-header--size-md .hcis-header__inner) {
        padding: 0.65rem 0.75rem !important;
    }
}

/* Bentuk daftar (rail · toolbar · tiga tampilan) hidup di
   Shared/components/listing/SopListingDokumen.vue — dipakai bersama
   Public Document. Jangan tulis ulang gayanya di sini. */
</style>
