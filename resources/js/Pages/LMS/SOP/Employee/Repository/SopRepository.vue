<template>
    <div class="container-fluid px-0 sop-scope">
        <!-- ══ 1. HERO HEADER PUBLIC DOCUMENT (Menggunakan HcisHeader Signature) ══ -->
        <HcisHeader
            title="Public Document"
            kicker="DMS PUBLIC DOCUMENT"
            subtitle="Pusat rujukan & prosedur kerja resmi — bebas dibaca dan dipelajari kapan saja."
            icon="journal-bookmark-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            :loading="loading"
            class="mb-4"
        >
            <!-- Aksi Header Kanan -->
            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        variant="primary"
                        size="sm"
                        href="/sop/saya"
                        leading-icon="clipboard2-check-fill"
                        pill
                        title="Buka Dokumen yang wajib Anda kerjakan"
                    >
                        <span class="d-none d-sm-inline">Functional Document</span>
                        <span class="d-inline d-sm-none">Functional Doc</span>
                    </HcisButton>
                    <div class="hcis-date-pill d-none d-sm-inline-flex">
                        <HcisIcon name="calendar3" size="2xs" />
                        <span>{{ formattedDate }}</span>
                    </div>
                </div>
            </template>

            <!-- Konsol Metrik Perpustakaan (3 Lensa Interaktif) -->
            <template #console>
                <div class="repo-console-grid">
                    <!-- 1. Lensa Seluruh Prosedur Publik -->
                    <div
                        class="repo-metric-card clickable"
                        :class="{ active: lensa === 'semua' && !q }"
                        role="button"
                        tabindex="0"
                        title="Tampilkan seluruh prosedur publik"
                        @click="pilihLensa('semua')"
                        @keydown.enter="pilihLensa('semua')"
                    >
                        <div class="repo-metric-icon">
                            <HcisIcon name="journals" size="sm" />
                        </div>
                        <div class="repo-metric-info">
                            <span class="repo-metric-num tnum">{{ d.total || 0 }}</span>
                            <span class="repo-metric-lbl">
                                <span class="d-none d-sm-inline">Prosedur Publik</span>
                                <span class="d-inline d-sm-none">Publik</span>
                            </span>
                        </div>
                        <span v-if="lensa === 'semua' && !q" class="repo-active-dot" />
                    </div>

                    <!-- 2. Lensa Baru & Diperbarui -->
                    <div
                        class="repo-metric-card clickable"
                        :class="{ active: lensa === 'baru' && !q }"
                        role="button"
                        tabindex="0"
                        title="Tampilkan dokumen yang baru diterbitkan atau diperbarui"
                        @click="pilihLensa('baru')"
                        @keydown.enter="pilihLensa('baru')"
                    >
                        <div class="repo-metric-icon stars">
                            <HcisIcon name="stars" size="sm" />
                        </div>
                        <div class="repo-metric-info">
                            <span class="repo-metric-num tnum">{{ d.jumlah_baru || 0 }}</span>
                            <span class="repo-metric-lbl">
                                <span class="d-none d-sm-inline">Baru &amp; Diperbarui</span>
                                <span class="d-inline d-sm-none">Baru</span>
                            </span>
                        </div>
                        <span v-if="lensa === 'baru' && !q" class="repo-active-dot" />
                    </div>

                    <!-- 3. Lensa Rak Saya / Favorit -->
                    <div
                        class="repo-metric-card clickable"
                        :class="{ active: lensa === 'favorit' && !q }"
                        role="button"
                        tabindex="0"
                        title="Tampilkan dokumen yang Anda sematkan ke rak pribadi"
                        @click="pilihLensa('favorit')"
                        @keydown.enter="pilihLensa('favorit')"
                    >
                        <div class="repo-metric-icon bookmark">
                            <HcisIcon name="bookmark-fill" size="sm" />
                        </div>
                        <div class="repo-metric-info">
                            <span class="repo-metric-num tnum">{{ d.jumlah_favorit || 0 }}</span>
                            <span class="repo-metric-lbl">
                                <span class="d-none d-sm-inline">Rak Saya (Favorit)</span>
                                <span class="d-inline d-sm-none">Rak Saya</span>
                            </span>
                        </div>
                        <span v-if="lensa === 'favorit' && !q" class="repo-active-dot" />
                    </div>
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. BILAH SARINGAN RESMI (HcisFilterBar Suite) ══ -->
        <HcisFilterBar
            :search="q"
            :search-placeholder="searchPlaceholder"
            :command-key="true"
            :show-view-toggle="true"
            :view-mode="tampilan"
            :view-options="daftarTampilan"
            :filters="filterBarFilters"
            :filter-values="filterBarValues"
            :quick-chips="filterQuickChips"
            :active-chip-value="lensa"
            :results-count="paginasi ? paginasi.total : (daftar ? daftar.length : null)"
            sentiment="brand"
            variant="glass"
            class="mb-4"
            @update:search="onSearchUpdate"
            @update:view-mode="pilihTampilan"
            @filter-change="onFilterBarChange"
            @chip-click="onChipFilterClick"
            @reset="onFilterBarReset"
            @command="fokusCari"
        />

        <!-- ══ 3. AREA KONTEN UTAMA ══ -->
        <div class="mb-5">
            <!-- Ajakan Tugas Kepatuhan Dokumen (Jika ada yang menunggu / lewat tenggat) -->
            <div v-if="loading" class="mb-3">
                <HcisSkeleton width="100%" height="60px" rounded="lg" />
            </div>
            <BilahTugas v-else :t="d.tugas" />

            <!-- Jejak: Baru saja dibuka -->
            <div v-if="!q && d.terakhir && d.terakhir.length" class="pu-jejak">
                <span class="pu-jejak-l">
                    <HcisIcon name="clock-history" size="2xs" /> Baru saja dibuka:
                </span>
                <a
                    v-for="k in d.terakhir.slice(0, 6)"
                    :key="k.Id_SOP_Paket"
                    :href="urlPaket(k.Id_SOP_Paket)"
                    class="pu-jejak-p"
                    :title="k.Nama_Paket"
                >
                    {{ k.Nama_Paket }}
                </a>
            </div>

            <!--
                ══ DAFTAR ══

                Komponen yang SAMA dengan Functional Document. Bedanya cuma dari
                mana isinya datang: halaman ini memotong di server dan meminta
                rekap rail lewat satu permintaan terpisah, sedangkan Functional
                Document memegang seluruh tugasnya di memori. Perbedaan itu
                selesai di `grupTampil` dan `paginasi` — tak satu pun sampai ke
                dalam komponennya.
            -->
            <SopListingDokumen
                :rail-mode="railMode"
                :tree="treeData"
                :active-tree-dept="filters.departemen"
                :active-tree-jenis="filters.jenis"
                :sumbu-tersedia="sumbuTersedia"
                :sumbu-aktif="sumbuAktif"
                :sumbu-meta="sumbuMeta"
                :grup="grupTampil"
                :grup-terpilih="grupTerpilih"
                :penempatan="penempatan"
                :total-semua="totalSemua"
                :bar="false"
                label-semua="Semua dokumen"
                :items="daftar"
                :dokumen="dokumenTampil"
                :tampilan="tampilan"
                :daftar-tampilan="daftarTampilan"
                :loading="memuatIsi"
                rujukan
                sentiment="brand"
                :sub="subDaftar"
                :satuan="satuanTampil"
                :judul-semua="judulSemua"
                :ikon-semua="ikonSemua"
                :saringan-aktif="saringanAktif"
                :kosong="kosongDaftar"
                :paginasi="paginasi"
                :tampilkan-view-toggle="false"
                :tampilkan-info-jumlah="false"
                @update:rail-mode="setRailMode"
                @pilih-tree-dept="onPilihTreeDept"
                @pilih-tree-sub="onPilihTreeSub"
                @pilih-tree-semua="onPilihTreeSemua"
                @update:sumbu="pilihSumbu"
                @update:grup="pilihGrup"
                @update:tampilan="pilihTampilan"
                @update:halaman="gantiHalaman"
                @hapus-saringan="hapusSaringan"
                @reset-saringan="onFilterBarReset"
            >
                <!--
                    Favorit & intip adalah aksi milik HALAMAN INI, bukan milik
                    kartu. Lewat slot, Functional Document dan Monitoring tak
                    perlu ikut memikirkan tombol yang tak pernah mereka pasang.
                -->
                <template #aksi-kartu="{ tugas }">
                    <button
                        type="button"
                        class="pu-kartu-aksi pu-kartu-favorit"
                        :class="{ on: tugas.Favorit }"
                        :aria-label="tugas.Favorit ? 'Hapus dari rak favorit' : 'Sematkan ke rak favorit'"
                        :title="tugas.Favorit ? 'Hapus dari rak favorit' : 'Sematkan ke rak favorit'"
                        @click.stop="toggleFavorit(tugas)"
                    >
                        <HcisIcon :name="tugas.Favorit ? 'bookmark-fill' : 'bookmark'" size="xs" />
                    </button>
                    <button
                        type="button"
                        class="pu-kartu-aksi pu-kartu-intip"
                        aria-label="Intip isi paket"
                        title="Intip isi rincian paket"
                        @click.stop="bukaIntip(tugas)"
                    >
                        <HcisIcon name="eye-fill" size="xs" />
                    </button>
                </template>

                <template #kosong-aksi>
                    <div class="pu-kosong-actions">
                        <!-- 1. Tab Favorit Kosong Murni (Belum ada bookmark) -->
                        <template v-if="lensa === 'favorit' && !adaFilterNonLensa">
                            <HcisButton
                                label="Jelajahi Semua Dokumen"
                                variant="primary"
                                size="sm"
                                leading-icon="compass"
                                class="pu-kosong-btn"
                                @click="pilihLensa('semua')"
                            />
                        </template>

                        <!-- 2. Tab Baru Kosong Murni -->
                        <template v-else-if="lensa === 'baru' && !adaFilterNonLensa">
                            <HcisButton
                                label="Lihat Semua Dokumen"
                                variant="primary"
                                size="sm"
                                leading-icon="journals"
                                class="pu-kosong-btn"
                                @click="pilihLensa('semua')"
                            />
                        </template>

                        <!-- 3. Ada filter / pencarian aktif yang membuat hasil kosong -->
                        <template v-else-if="adaFilterNonLensa">
                            <HcisButton
                                label="Reset Saringan"
                                variant="secondary"
                                size="sm"
                                leading-icon="arrow-counterclockwise"
                                class="pu-kosong-btn"
                                @click="onFilterBarReset"
                            />
                            <HcisButton
                                v-if="lensa !== 'semua'"
                                label="Cari di Semua Dokumen"
                                variant="primary"
                                size="sm"
                                leading-icon="journals"
                                class="pu-kosong-btn"
                                @click="pilihLensa('semua')"
                            />
                            <HcisButton
                                v-else-if="diTugas"
                                label="Cari di Functional Document"
                                variant="primary"
                                size="sm"
                                href="/sop/saya"
                                leading-icon="clipboard2-check-fill"
                                class="pu-kosong-btn"
                            />
                        </template>

                        <!-- 4. Default / Belum ada dokumen publik sama sekali -->
                        <template v-else>
                            <HcisButton
                                label="Buka Functional Document"
                                variant="primary"
                                size="sm"
                                href="/sop/saya"
                                leading-icon="clipboard2-check-fill"
                                class="pu-kosong-btn"
                            />
                        </template>
                    </div>
                </template>
            </SopListingDokumen>

            <!-- Jembatan ke Functional Document saat mencari -->
            <a v-if="q.trim().length >= 2 && diTugas" href="/sop/saya" class="pu-jembatan">
                <HcisIcon name="person-check-fill" size="sm" />
                <span>
                    <strong>{{ diTugas }}</strong> hasil lain ada di tugas Anda — dokumen yang ditugaskan dikelola di <strong>Functional Document</strong>
                </span>
                <HcisIcon name="arrow-right" size="xs" />
            </a>
        </div>

        <!-- Drawer Intip Rincian Dokumen -->
        <IntipDrawer v-model="intipBuka" :id-paket="intipId" />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import BilahTugas from './BilahTugas.vue';
import IntipDrawer from './IntipDrawer.vue';
import HcisHeader from '@/components/ui/HcisHeader.vue';
import HcisFilterBar from '@/components/ui/HcisFilterBar.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisSkeleton from '@/components/ui/HcisSkeleton.vue';
import SopListingDokumen from '../../Shared/components/listing/SopListingDokumen.vue';
import {
    SUMBU_PUSTAKA,
    sumbuDari,
    bangunGrup,
    bangunPohonFolder,
    nilaiSumbu,
    cocokSumbu,
    opsiSumbu,
    terapkanSaringan,
} from '../../Shared/components/listing/sumbu.js';
import { SOP_URL, temaAksen } from '../../Shared/support/constants.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';
import { setPrioritas } from '../../Shared/support/prioritas.js';

const API = '/api/v1/sop/repository';

/**
 * DUA SUMBER ISI, SATU TAMPILAN.
 *
 * Bentuk "Kartu" dan "Tabel" menampilkan PAKET: dipotong per halaman di server,
 * dan rail-nya diisi rekap terpisah yang dihitung atas seluruh himpunan.
 * Bentuk "Dokumen" menampilkan DOKUMEN: katalognya memang tak berpaginasi, jadi
 * pengelompokannya dikerjakan di memori seperti di Functional Document.
 *
 * Perbedaan itu berhenti di berkas ini. SopListingDokumen menerima `grup` yang
 * sudah jadi dan `paginasi` yang boleh null, sehingga bentuk daftarnya tetap
 * satu untuk kedua halaman.
 */
export default {
    name: 'SopRepository',
    props: { prioritas: { type: Array, default: () => [] } },
    components: {
        BilahTugas,
        IntipDrawer,
        HcisHeader,
        HcisFilterBar,
        HcisIcon,
        HcisButton,
        HcisSkeleton,
        SopListingDokumen,
    },
    mixins: [pageMeta],
    data() {
        return {
            isMobile: typeof window !== 'undefined' ? window.innerWidth < 768 : false,
            d: {
                tugas: { total: 0, terlambat: 0, segera: 0, terdekat: null },
                favorit: [],
                terakhir: [],
                baru: [],
                total: 0,
                jumlah_baru: 0,
                jumlah_favorit: 0,
            },
            loading: true,

            /* Lensa rak: semua | baru | favorit. Disaring di SERVER — lihat
               catatan pada terapkanSaringan() di SopRepositoryReadService. */
            lensa: 'semua',

            /* Bentuk daftar: kartu | tabel | dokumen. */
            tampilan: 'kartu',
            railMode: 'sumbu',
            sumbuAktif: 'departemen',
            grupTerpilih: '',

            q: '',
            filters: {
                prioritas: '',
                wajib: '',
                departemen: [],
                jenis: [],
                apqc: [],
            },
            diTugas: 0,
            timer: null,

            /* Paket — berpaginasi di server. */
            daftar: [],
            daftarTotal: 0,
            halaman: 1,
            perHalaman: 12,
            memuatDaftar: false,
            rekap: { grup: [], tugas: 0, penempatan: 0, tree: [] },

            /* Dokumen — katalog utuh, dikelompokkan di memori. */
            dokumen: [],
            memuatDokumen: false,
            dokumenTerambil: false,

            intipBuka: false,
            intipId: '',
        };
    },
    computed: {
        searchPlaceholder() {
            return this.isMobile
                ? 'Cari dokumen / nomor SOP…'
                : 'Cari nama dokumen, nomor prosedur, atau kata kunci…';
        },
        sumbuTersedia() {
            return SUMBU_PUSTAKA;
        },
        sumbuMeta() {
            return sumbuDari(this.sumbuAktif);
        },
        daftarTampilan() {
            return [
                { key: 'kartu', label: 'Kartu', ikon: 'grid-fill', judul: 'Satu kartu per paket dokumen' },
                { key: 'tabel', label: 'Tabel', ikon: 'list-ul', judul: 'Satu baris per paket dokumen' },
                { key: 'dokumen', label: 'Dokumen', ikon: 'file-earmark-text-fill', judul: 'Satu baris per dokumen di dalam paket' },
            ];
        },
        perDokumen() {
            return this.tampilan === 'dokumen';
        },
        filterQuickChips() {
            return [
                {
                    value: 'semua',
                    label: 'Semua Dokumen',
                    badge: this.d.total || 0,
                    icon: 'journals',
                },
                {
                    value: 'baru',
                    label: 'Baru & Diperbarui',
                    badge: this.d.jumlah_baru || 0,
                    icon: 'stars',
                    color: 'warning',
                },
                {
                    value: 'favorit',
                    label: 'Rak Saya',
                    badge: this.d.jumlah_favorit || 0,
                    icon: 'bookmark-fill',
                    color: 'brand',
                },
            ];
        },
        opsiDepartemen() {
            return opsiSumbu(this.dokumen, 'departemen');
        },
        opsiJenis() {
            return opsiSumbu(this.dokumen, 'jenis');
        },
        opsiApqc() {
            return opsiSumbu(this.dokumen, 'apqc');
        },
        filterBarFilters() {
            return [
                {
                    key: 'departemen',
                    label: 'Departemen',
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
                    key: 'apqc',
                    label: 'Proses APQC',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Proses',
                    options: this.opsiApqc,
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
                    key: 'wajib',
                    label: 'Status Penugasan',
                    type: 'select',
                    placeholder: 'Semua Dokumen Publik',
                    options: [
                        { label: 'Semua Dokumen Publik', value: '' },
                        { label: 'Hanya yang Ditugaskan ke Saya', value: '1' },
                    ],
                    clearable: true,
                },
            ];
        },
        filterBarValues() {
            return {
                departemen: this.filters.departemen || [],
                jenis: this.filters.jenis || [],
                apqc: this.filters.apqc || [],
                prioritas: this.filters.prioritas || '',
                wajib: this.filters.wajib || '',
            };
        },
        hasActiveFilter() {
            return !!(
                this.q ||
                this.filters.prioritas ||
                this.filters.wajib ||
                (this.filters.departemen && this.filters.departemen.length) ||
                (this.filters.jenis && this.filters.jenis.length) ||
                (this.filters.apqc && this.filters.apqc.length) ||
                this.lensa !== 'semua' ||
                this.grupTerpilih
            );
        },
        adaFilterNonLensa() {
            return !!(
                (this.q && this.q.trim().length >= 2) ||
                this.filters.prioritas ||
                this.filters.wajib ||
                (this.filters.departemen && this.filters.departemen.length) ||
                (this.filters.jenis && this.filters.jenis.length) ||
                (this.filters.apqc && this.filters.apqc.length) ||
                this.grupTerpilih
            );
        },
        lensaOpsi() {
            return [
                {
                    k: 'semua',
                    label: 'Semua Dokumen',
                    ikon: 'journals',
                    n: this.d.total,
                    kosong: false,
                    tip: 'Seluruh prosedur kerja publik',
                },
                {
                    k: 'baru',
                    label: 'Baru & Diperbarui',
                    ikon: 'stars',
                    n: this.d.jumlah_baru,
                    kosong: !this.d.jumlah_baru,
                    tip: 'Diterbitkan atau diperbarui dalam 30 hari terakhir',
                },
                {
                    k: 'favorit',
                    label: 'Rak Saya',
                    ikon: 'bookmark-fill',
                    n: this.d.jumlah_favorit,
                    kosong: !this.d.jumlah_favorit,
                    tip: 'Dokumen yang Anda sematkan ke rak pribadi',
                },
            ];
        },

        /* ── Kelompok rail ── */

        /**
         * Rekap dari SERVER, dilengkapi warna & ikon.
         *
         * Server sengaja tidak mengirim keduanya: warna kelompok adalah
         * keputusan tampilan yang diturunkan dari namanya lewat temaAksen(),
         * dan menghitungnya di sana berarti dua tempat memutuskan rupa yang
         * sama. Bentuk akhirnya identik dengan keluaran bangunGrup().
         */
        grupPaket() {
            return (this.rekap.grup || []).map((g) => {
                const t = temaAksen(g.nama);

                return {
                    nama: g.nama,
                    warna: t.color,
                    ikon: t.icon,
                    kosong: !!g.kosong,
                    total: g.total,
                    persen: 0,
                };
            });
        },
        dokumenTersaring() {
            const f = {
                ...this.filters,
                q: this.q.trim(),
            };
            let arr = terapkanSaringan(this.dokumen || [], f, () => 999);

            if (this.filters.wajib === '1') {
                arr = arr.filter((d) => d.Wajib);
            }
            if (this.lensa === 'favorit') {
                const ids = (this.d.favorit || []).map((f) => f.Id_SOP_Paket);
                arr = arr.filter((d) => d.Favorit || ids.includes(d.Id_SOP_Paket));
            }
            if (this.lensa === 'baru') {
                const ids = (this.d.baru || []).map((b) => b.Id_SOP_Paket);
                arr = arr.filter((d) => d.Baru || ids.includes(d.Id_SOP_Paket));
            }

            // Default Sort 3-Tier:
            // 1. Terbaru diassign (Wajib & Belum Selesai / Belum Buka) -> Tanggal & Jam Assign DESC
            // 2. Ada progress (Sedang Berjalan / Pernah Buka) -> Tanggal & Jam Akses DESC
            // 3. Selesai -> Tanggal & Jam Selesai DESC
            // 4. Dokumen publik umum lainnya -> Tanggal Publish / Tanggal Berlaku DESC
            arr.sort((a, b) => {
                const getTier = (k) => {
                    const persen = k.Persen ?? 0;
                    const wajib = !!k.Wajib;
                    const pernahBuka = !!(k.Waktu_Buka || k.Terakhir_Buka);
                    if (persen >= 100) return 3;
                    if (persen > 0 || (pernahBuka && persen < 100)) return 2;
                    if (wajib) return 1;
                    return 4;
                };

                const tierA = getTier(a);
                const tierB = getTier(b);
                if (tierA !== tierB) return tierA - tierB;

                const getTimestamp = (k, tier) => {
                    if (tier === 1) {
                        const t = k.Tanggal_Assign || k.Created_At;
                        return t ? new Date(t).getTime() : 0;
                    }
                    if (tier === 2) {
                        const t = k.Waktu_Buka || k.Terakhir_Buka || k.Tanggal_Assign;
                        return t ? new Date(t).getTime() : 0;
                    }
                    if (tier === 3) {
                        const t = k.Tanggal_Selesai || k.Waktu_Konfirmasi || k.Terakhir_Konfirmasi;
                        return t ? new Date(t).getTime() : 0;
                    }
                    const t = k.Tanggal_Publish || k.Tanggal_Berlaku || k.Created_At;
                    return t ? new Date(t).getTime() : 0;
                };

                const timeA = getTimestamp(a, tierA);
                const timeB = getTimestamp(b, tierB);
                return timeB - timeA;
            });

            return arr;
        },
        rakitanDokumen() {
            return bangunGrup(this.dokumenTersaring, this.sumbuAktif, temaAksen);
        },
        grupDokumen() {
            return this.rakitanDokumen.grup;
        },
        dokumenTampil() {
            if (!this.grupTerpilih) return this.dokumenTersaring;
            const kelompok = this.rakitanDokumen.grup.find((g) => g.nama === this.grupTerpilih);
            return kelompok ? kelompok.items : [];
        },
        grupTampil() {
            return this.perDokumen ? this.grupDokumen : this.grupPaket;
        },
        penempatan() {
            return this.perDokumen ? this.rakitanDokumen.penempatan : (this.rekap.penempatan || 0);
        },
        totalSemua() {
            return this.perDokumen ? this.dokumenTersaring.length : this.daftarTotal;
        },

        /* ── Keterangan ── */

        paginasi() {
            if (this.perDokumen) return null;
            return {
                halaman: this.halaman,
                perHalaman: this.perHalaman,
                total: this.daftarTotal,
                halamanTotal: Math.ceil(this.daftarTotal / this.perHalaman) || 1,
            };
        },
        memuatIsi() {
            return this.loading || (this.perDokumen ? this.memuatDokumen : this.memuatDaftar);
        },
        satuanTampil() {
            return this.perDokumen ? 'dokumen' : 'paket';
        },
        judulSemua() {
            if (this.q && this.q.trim().length >= 2) {
                return `Pencarian: "${this.q.trim()}"`;
            }
            if (this.filters.departemen?.length && this.filters.jenis?.length) {
                const d = this.filters.departemen.join(', ');
                const j = this.filters.jenis.join(', ');
                return `${d} · ${j}`;
            }
            if (this.filters.departemen?.length) {
                return this.filters.departemen.length === 1
                    ? `Departemen ${this.filters.departemen[0]}`
                    : `Departemen (${this.filters.departemen.join(', ')})`;
            }
            if (this.filters.jenis?.length) {
                return this.filters.jenis.length === 1
                    ? `Jenis: ${this.filters.jenis[0]}`
                    : `Jenis Dokumen (${this.filters.jenis.join(', ')})`;
            }
            if (this.filters.apqc?.length) {
                return this.filters.apqc.length === 1
                    ? `Proses APQC: ${this.filters.apqc[0]}`
                    : `Proses APQC (${this.filters.apqc.join(', ')})`;
            }
            if (this.filters.prioritas) {
                return `Prioritas: ${this.filters.prioritas}`;
            }
            if (this.filters.wajib === '1') {
                return 'Ditugaskan ke Saya';
            }
            if (this.lensa === 'baru') return 'Dokumen Baru & Diperbarui';
            if (this.lensa === 'favorit') return 'Rak Saya (Dokumen Favorit)';

            return this.perDokumen ? 'Semua Dokumen Publik' : 'Seluruh Prosedur Publik';
        },
        subDaftar() {
            if (this.q && this.q.trim().length >= 2) {
                return `Menampilkan hasil pencarian yang cocok dengan kata kunci “${this.q.trim()}”.`;
            }
            if (this.filters.departemen?.length && this.filters.jenis?.length) {
                return `Katalog dokumen kategori ${this.filters.jenis.join(', ')} untuk departemen ${this.filters.departemen.join(', ')}.`;
            }
            if (this.filters.departemen?.length) {
                return `Daftar seluruh prosedur kerja dan dokumen operasional resmi milik departemen ${this.filters.departemen.join(', ')}.`;
            }
            if (this.filters.jenis?.length) {
                return `Daftar seluruh prosedur kerja dengan klasifikasi jenis ${this.filters.jenis.join(', ')}.`;
            }
            if (this.filters.apqc?.length) {
                return `Koleksi dokumen SOP berdasarkan proses bisnis APQC ${this.filters.apqc.join(', ')}.`;
            }
            if (this.filters.prioritas) {
                return `Koleksi prosedur kerja publik dengan tingkat prioritas ${this.filters.prioritas}.`;
            }
            if (this.filters.wajib === '1') {
                return 'Daftar prosedur kerja publik yang juga ditugaskan ke akun Anda sebagai tugas kepatuhan.';
            }
            if (this.lensa === 'baru') {
                return 'Prosedur kerja publik yang baru diterbitkan atau diperbarui dalam 30 hari terakhir.';
            }
            if (this.lensa === 'favorit') {
                return 'Prosedur kerja yang Anda sematkan ke rak pribadi.';
            }
            if (this.perDokumen) {
                return 'Dokumen di dalam paket publik, dikelompokkan menurut departemen pemilik, jenis, atau proses bisnis.';
            }

            return 'Seluruh rujukan prosedur kerja resmi perusahaan yang terbuka untuk dibaca.';
        },
        ikonSemua() {
            if (this.q && this.q.trim().length >= 2) return 'search';
            if (this.filters.departemen?.length && this.filters.jenis?.length) return 'folder2-open';
            if (this.filters.departemen?.length) return 'building';
            if (this.filters.jenis?.length) return 'journal-text';
            if (this.filters.apqc?.length) return 'diagram-2';
            if (this.filters.prioritas) return 'flag-fill';
            if (this.filters.wajib === '1') return 'check2-circle';
            if (this.lensa === 'baru') return 'stars';
            if (this.lensa === 'favorit') return 'bookmark-fill';
            return 'journals';
        },
        treeData() {
            return this.perDokumen ? bangunPohonFolder(this.dokumen) : (this.rekap.tree || []);
        },
        saringanAktif() {
            const out = [];
            if (this.lensa !== 'semua') {
                const l = this.filterQuickChips.find((x) => x.value === this.lensa);
                out.push({ kunci: 'lensa', label: 'Lensa', nilai: l ? l.label : this.lensa, ikon: 'bookmark' });
            }
            if (this.q.trim().length >= 2) {
                out.push({ kunci: 'q', label: 'Pencarian', nilai: `"${this.q.trim()}"`, ikon: 'search' });
            }
            if (this.filters.departemen && this.filters.departemen.length) {
                out.push({
                    kunci: 'departemen',
                    label: 'Departemen',
                    nilai: this.filters.departemen.join(', '),
                    ikon: 'building',
                });
            }
            if (this.filters.jenis && this.filters.jenis.length) {
                out.push({
                    kunci: 'jenis',
                    label: 'Jenis Dokumen',
                    nilai: this.filters.jenis.join(', '),
                    ikon: 'journal-text',
                });
            }
            if (this.filters.apqc && this.filters.apqc.length) {
                out.push({
                    kunci: 'apqc',
                    label: 'Proses APQC',
                    nilai: this.filters.apqc.join(', '),
                    ikon: 'diagram-2',
                });
            }
            if (this.filters.prioritas) {
                out.push({ kunci: 'prioritas', label: 'Prioritas', nilai: this.filters.prioritas, ikon: 'flag-fill' });
            }
            if (this.filters.wajib === '1') {
                out.push({ kunci: 'wajib', label: 'Status', nilai: 'Ditugaskan ke Saya', ikon: 'check2-circle' });
            }
            if (this.grupTerpilih) {
                out.push({ kunci: 'grup', label: this.sumbuMeta.label, nilai: this.grupTerpilih, ikon: 'diagram-3', kelas: 'kat' });
            }

            return out;
        },
        adaSaringan() {
            return this.hasActiveFilter;
        },
        kosongDaftar() {
            if (this.q.trim().length >= 2) {
                return {
                    icon: 'search',
                    title: `Tak ada dokumen yang cocok dengan “${this.q.trim()}”`,
                    description: 'Coba periksa ejaan, gunakan kata kunci yang lebih umum, atau bersihkan saringan pencarian Anda.',
                };
            }
            if (this.lensa === 'favorit') {
                if (this.adaFilterNonLensa) {
                    return {
                        icon: 'bookmark-x',
                        title: 'Tidak Ada Dokumen Favorit yang Cocok',
                        description: 'Tidak ada dokumen di rak favorit Anda yang cocok dengan saringan yang sedang aktif.',
                    };
                }
                return {
                    icon: 'bookmark-heart',
                    title: 'Rak Favorit Masih Kosong',
                    description: 'Sematkan dokumen yang sering Anda baca dengan mengeklik ikon bookmark pada kartu dokumen agar tersimpan di rak pribadi ini.',
                };
            }
            if (this.lensa === 'baru') {
                if (this.adaFilterNonLensa) {
                    return {
                        icon: 'stars',
                        title: 'Tidak Ada Dokumen Baru yang Cocok',
                        description: 'Tidak ada dokumen baru atau diperbarui yang cocok dengan saringan yang sedang aktif.',
                    };
                }
                return {
                    icon: 'stars',
                    title: 'Belum Ada Dokumen Baru',
                    description: 'Belum ada prosedur kerja yang diterbitkan atau diperbarui dalam 30 hari terakhir.',
                };
            }
            if (this.grupTerpilih) {
                return {
                    icon: 'folder2-open',
                    title: `Tak ada yang cocok pada ${this.grupTerpilih}`,
                    description: 'Pilih kelompok lain di bilah samping, atau tampilkan seluruh dokumen.',
                };
            }
            if (this.adaFilterNonLensa) {
                return {
                    icon: 'funnel',
                    title: 'Tidak Ada Dokumen yang Cocok',
                    description: 'Tidak ditemukan dokumen publik yang sesuai dengan kombinasi saringan yang Anda pilih.',
                };
            }

            return {
                icon: 'journals',
                title: 'Belum Ada Dokumen Publik yang Diterbitkan',
                description: 'Begitu ada standar operasional prosedur yang diterbitkan, daftarnya akan muncul otomatis di sini.',
            };
        },
    },
    created() {
        setPrioritas(this.prioritas);
    },
    mounted() {
        this.checkMobile();
        try {
            const mode = localStorage.getItem('sop_repo_rail_mode');
            if (mode && ['sumbu', 'tree'].includes(mode)) this.railMode = mode;
        } catch (e) {}
        this.bacaUrl();
        this.muat();
        window.addEventListener('popstate', this.bacaUrl);
        window.addEventListener('keydown', this.pintasan);
        window.addEventListener('resize', this.checkMobile);
    },
    beforeUnmount() {
        clearTimeout(this.timer);
        window.removeEventListener('popstate', this.bacaUrl);
        window.removeEventListener('keydown', this.pintasan);
        window.removeEventListener('resize', this.checkMobile);
    },
    methods: {
        checkMobile() {
            this.isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
        },
        urlPaket(id) {
            return SOP_URL.detail(id);
        },
        pintasan(e) {
            const diKolom = /^(input|textarea|select)$/i.test(e.target?.tagName) || e.target?.isContentEditable;
            if (e.key === '/' && !diKolom) {
                e.preventDefault();
                this.fokusCari();
            }
        },
        fokusCari() {
            const el = document.getElementById('hcis-filter-search-input');
            if (el) el.focus();
        },

        /* ── Alamat ── */

        bacaUrl() {
            const p = new URLSearchParams(window.location.search);
            /* Bentuk tampilan & lensa ikut alamat supaya tautan yang dibagikan
               membuka layar yang dimaksud pengirimnya, dan tombol Kembali
               peramban benar-benar kembali — bukan sekadar memuat ulang layar
               yang sama. */
            const t = p.get('tampilan');
            this.tampilan = ['kartu', 'tabel', 'dokumen'].includes(t) ? t : 'kartu';
            const l = p.get('lensa');
            this.lensa = ['semua', 'baru', 'favorit'].includes(l) ? l : 'semua';
            const prio = p.get('prioritas');
            if (prio) this.filters.prioritas = prio;
            const w = p.get('wajib');
            if (w) this.filters.wajib = w;
        },
        tulisUrl() {
            const p = new URLSearchParams();
            if (this.tampilan !== 'kartu') p.set('tampilan', this.tampilan);
            if (this.lensa !== 'semua') p.set('lensa', this.lensa);
            if (this.filters.prioritas) p.set('prioritas', this.filters.prioritas);
            if (this.filters.wajib) p.set('wajib', this.filters.wajib);
            const s = p.toString();
            window.history.pushState({}, '', `${window.location.pathname}${s ? `?${s}` : ''}`);
        },

        /* ── Kontrol filter bar ── */

        onSearchUpdate(val) {
            this.q = val || '';
            this.cariDebounce();
        },
        onFilterBarChange({ key, value }) {
            this.filters = { ...this.filters, [key]: value };
            this.grupTerpilih = '';
            this.tulisUrl();
            this.muatIsi({ reset: true });
        },
        onChipFilterClick(chip) {
            const val = chip && chip.value ? chip.value : 'semua';
            this.pilihLensa(val);
        },
        onFilterBarReset() {
            this.q = '';
            this.filters = { prioritas: '', wajib: '', departemen: [], jenis: [], apqc: [] };
            this.lensa = 'semua';
            this.grupTerpilih = '';
            this.diTugas = 0;
            this.tulisUrl();
            this.muatIsi({ reset: true });
        },

        /* ── Kontrol daftar ── */

        pilihSumbu(key) {
            if (this.sumbuAktif === key) return;
            this.sumbuAktif = key;
            /* Kelompok terpilih dilepas: namanya milik sumbu yang lama dan
               tak akan cocok dengan satu pun kelompok yang baru, sehingga
               daftarnya tersaring habis tanpa sebab yang terlihat. */
            this.grupTerpilih = '';
            this.muatIsi({ reset: true });
        },
        pilihGrup(nama) {
            if (this.grupTerpilih === nama) return;
            this.grupTerpilih = nama;
            if (!this.perDokumen) this.muatDaftar(1);
        },
        onPilihTreeDept(payload) {
            const nama = typeof payload === 'string'
                ? payload
                : (payload?.nama || payload?.dept || '');
            this.grupTerpilih = '';
            this.filters.departemen = nama ? [nama] : [];
            this.filters.jenis = [];
            this.filters.apqc = [];
            this.muatIsi({ reset: true });
        },
        onPilihTreeSub(payload) {
            let deptNama = '';
            let jenisNama = '';
            if (payload) {
                deptNama = payload.dept || payload.parent?.nama || payload.parent || '';
                jenisNama = payload.jenis || payload.node?.nama || payload.node || '';
            }
            this.grupTerpilih = '';
            this.filters.departemen = deptNama ? [deptNama] : [];
            this.filters.jenis = jenisNama ? [jenisNama] : [];
            this.filters.apqc = [];
            this.muatIsi({ reset: true });
        },
        onPilihTreeSemua() {
            this.grupTerpilih = '';
            this.filters.departemen = [];
            this.filters.jenis = [];
            this.filters.apqc = [];
            this.muatIsi({ reset: true });
        },
        setRailMode(mode) {
            if (this.railMode === mode) return;
            this.railMode = mode;
            try {
                localStorage.setItem('sop_repo_rail_mode', mode);
            } catch (e) {}
            // Reset filter kelompokan saat pindah mode
            this.grupTerpilih = '';
            this.filters.departemen = [];
            this.filters.jenis = [];
            this.filters.apqc = [];
            this.muatIsi({ reset: true });
        },
        pilihTampilan(key) {
            if (this.tampilan === key) return;
            const dariDokumen = this.perDokumen;
            this.tampilan = key;
            this.tulisUrl();

            /* Satuan kelompoknya berganti (paket ⇄ dokumen), jadi nama kelompok
               yang sedang dipilih belum tentu ada di sisi seberang. */
            if (dariDokumen !== this.perDokumen) this.grupTerpilih = '';
            this.muatIsi({ reset: false });
        },
        pilihLensa(k) {
            if (this.lensa === k) return;
            this.lensa = k;
            this.grupTerpilih = '';
            this.tulisUrl();
            this.muatIsi({ reset: true });
        },
        gantiHalaman(n) {
            const total = this.paginasi ? this.paginasi.halamanTotal : 1;
            if (n < 1 || n > total || n === this.halaman) return;
            this.muatDaftar(n);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        hapusSaringan(kunci) {
            if (kunci === 'lensa') {
                this.pilihLensa('semua');

                return;
            }
            if (kunci === 'q') {
                this.bersihkanCari();

                return;
            }
            if (kunci === 'departemen') {
                this.filters.departemen = [];
                this.muatIsi({ reset: true });

                return;
            }
            if (kunci === 'jenis') {
                this.filters.jenis = [];
                this.muatIsi({ reset: true });

                return;
            }
            if (kunci === 'apqc') {
                this.filters.apqc = [];
                this.muatIsi({ reset: true });

                return;
            }
            if (kunci === 'prioritas') {
                this.filters.prioritas = '';
                this.tulisUrl();
                this.muatIsi({ reset: true });

                return;
            }
            if (kunci === 'wajib') {
                this.filters.wajib = '';
                this.tulisUrl();
                this.muatIsi({ reset: true });

                return;
            }
            if (kunci === 'grup') {
                this.grupTerpilih = '';
                if (!this.perDokumen) this.muatDaftar(1);
            }
        },
        bersihkanSemua() {
            this.onFilterBarReset();
        },

        /* ── Pencarian ── */

        cariDebounce() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                /* Kelompok terpilih dilepas saat kata kunci berubah: kelompok
                   yang sudah tak ada di hasil baru akan menyaring habis
                   daftarnya, dan layar kosong itu terbaca sebagai "tak ada
                   dokumen" padahal sebenarnya "kelompok yang Anda pilih sudah
                   tidak ada". */
                this.grupTerpilih = '';
                this.muatIsi({ reset: true });
            }, 350);
        },
        bersihkanCari() {
            clearTimeout(this.timer);
            this.q = '';
            this.diTugas = 0;
            this.grupTerpilih = '';
            this.muatIsi({ reset: true });
            this.fokusCari();
        },

        /* ── Pemuatan ── */

        saringanKirim() {
            const p = { lensa: this.lensa, sumbu: this.sumbuAktif };
            const q = this.q.trim();
            if (q.length >= 2) p.q = q;
            if (this.filters.prioritas) p.prioritas = this.filters.prioritas;
            if (this.filters.wajib) p.wajib = this.filters.wajib;
            if (this.filters.departemen && this.filters.departemen.length) p.departemen = this.filters.departemen;
            if (this.filters.jenis && this.filters.jenis.length) p.jenis = this.filters.jenis;
            if (this.filters.apqc && this.filters.apqc.length) p.apqc = this.filters.apqc;
            if (this.grupTerpilih) p.grup = this.grupTerpilih;

            return p;
        },
        async muat() {
            this.loading = true;
            try {
                const r = await axios.get(`${API}/beranda`);
                if (r.data.success) this.d = { ...this.d, ...r.data.result };
            } catch (e) {
                ElMessage.error('Gagal memuat Public Document');
            } finally {
                this.loading = false;
            }

            // Muat katalog dokumen untuk dropdown saringan & pohon folder
            this.muatDokumen();
            await this.muatIsi({ reset: true });
        },
        /** Muat apa pun yang bentuk tampilan sekarang butuhkan. */
        async muatIsi({ reset }) {
            if (this.perDokumen) {
                await this.muatDokumen();

                return;
            }
            await Promise.all([this.muatRekap(), this.muatDaftar(reset ? 1 : this.halaman)]);
        },
        async muatRekap() {
            try {
                const r = await axios.get(`${API}/rekap`, { params: this.saringanKirim() });
                if (r.data.success) {
                    this.rekap = r.data.result || { grup: [], tugas: 0, penempatan: 0 };
                }
            } catch (e) {
                /* Rail yang gagal dimuat dikosongkan, bukan dibiarkan memegang
                   angka dari saringan sebelumnya: rail yang berbohong lebih
                   buruk daripada rail yang kosong. */
                this.rekap = { grup: [], tugas: 0, penempatan: 0 };
            }
        },
        async muatDaftar(page = 1) {
            this.memuatDaftar = true;
            try {
                const r = await axios.get(`${API}/paket`, {
                    params: { ...this.saringanKirim(), page, limit: this.perHalaman },
                });
                this.daftar = r.data.success ? r.data.result || [] : [];
                this.daftarTotal = r.data.pagination?.total ?? this.daftar.length;
                this.diTugas = r.data.di_tugas || 0;
                this.halaman = page;
            } catch (e) {
                this.daftar = [];
                this.daftarTotal = 0;
                ElMessage.error('Gagal memuat daftar dokumen');
            } finally {
                this.memuatDaftar = false;
            }
        },
        async muatDokumen() {
            /* Katalog dokumen tak bergantung pada lensa/pencarian — keduanya
               disaring di memori — jadi ia cukup diambil sekali. */
            if (this.dokumenTerambil) return;
            this.memuatDokumen = true;
            try {
                const r = await axios.get(`${API}/dokumen`);
                if (r.data.success) {
                    this.dokumen = r.data.result || [];
                    this.dokumenTerambil = true;
                }
            } catch (e) {
                ElMessage.error('Gagal memuat daftar dokumen');
            } finally {
                this.memuatDokumen = false;
            }
        },

        /* ── Aksi kartu ── */

        bukaIntip(k) {
            this.intipId = k.Id_SOP_Paket;
            this.intipBuka = true;
        },
        async toggleFavorit(k) {
            const sebelum = k.Favorit;
            k.Favorit = !sebelum;
            try {
                const r = await axios.post(`${API}/favorit/${k.Id_SOP_Paket}`);
                if (!r.data.success) throw new Error();
                k.Favorit = r.data.result.favorit;

                /* Angka "Rak Saya" di kepala halaman ikut bergerak; membiarkannya
                   basi membuat kartu metrik berselisih dengan bookmark yang
                   barusan diklik orangnya sendiri. */
                this.d.jumlah_favorit = Math.max(0, (this.d.jumlah_favorit || 0) + (k.Favorit ? 1 : -1));

                /* Saat rak favorit yang sedang ditampilkan, baris yang baru
                   dicabut harus benar-benar pergi dari daftar. */
                if (this.lensa === 'favorit') this.muatIsi({ reset: false });
            } catch (e) {
                k.Favorit = sebelum;
                ElMessage.error('Gagal menyimpan penanda favorit');
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

/* ════ Konsol Metrik Header ════ */
.repo-console-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}
.repo-metric-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 14px;
    padding: 0.7rem 0.95rem;
    color: var(--hcis-slate-800);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 2px 5px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.repo-metric-card.clickable {
    cursor: pointer;
    outline: none;
}
.repo-metric-card.clickable:hover {
    background: var(--hcis-white);
    transform: translateY(-2px);
    border-color: var(--hcis-indigo-400);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 6px 18px -2px rgba(99, 102, 241, 0.16);
}
.repo-metric-card.active {
    background: var(--hcis-white);
    border-color: var(--hcis-indigo-500);
    box-shadow:
        inset 0 1px 0 var(--hcis-white),
        0 8px 22px -4px rgba(99, 102, 241, 0.28),
        0 0 0 1px var(--hcis-indigo-500);
}
.repo-active-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-indigo-600);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.repo-metric-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    border: 1px solid rgba(199, 210, 254, 0.8);
    color: var(--hcis-indigo-600);
    flex-shrink: 0;
}
.repo-metric-icon.stars {
    background: linear-gradient(135deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
    border-color: rgba(253, 230, 138, 0.8);
    color: var(--hcis-amber-600);
}
.repo-metric-icon.bookmark {
    background: linear-gradient(135deg, var(--hcis-violet-50) 0%, #ede9fe 100%);
    border-color: rgba(221, 214, 254, 0.8);
    color: var(--hcis-violet-600);
}

.repo-metric-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.repo-metric-num {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--hcis-slate-900);
}
.repo-metric-lbl {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

@media (max-width: 767.98px) {
    .repo-console-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.45rem;
    }
    .repo-metric-card {
        padding: 0.45rem 0.45rem;
        gap: 0.45rem;
        border-radius: 11px;
    }
    .repo-metric-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
    }
    .repo-metric-num {
        font-size: 1.05rem;
    }
    .repo-metric-lbl {
        font-size: 0.65rem;
    }
    .repo-active-dot {
        top: 5px;
        right: 5px;
        width: 5px;
        height: 5px;
    }
}

/* Jejak */
.pu-jejak {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: -0.5rem;
    margin-bottom: 1.5rem;
    padding: 0 0.25rem;
}
.pu-jejak-l {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.pu-jejak-p {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    text-decoration: none;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    background: var(--hcis-white);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease;
}
.pu-jejak-p:hover {
    border-color: var(--hcis-indigo-400);
    color: var(--hcis-indigo-700);
    background: var(--hcis-slate-50);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px -1px rgba(99, 102, 241, 0.15);
}

@media (max-width: 768px) {
    .pu-jejak {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 0.35rem;
        margin-top: -0.25rem;
        margin-bottom: 1rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .pu-jejak::-webkit-scrollbar {
        display: none;
    }
    .pu-jejak-l {
        flex-shrink: 0;
        font-size: 0.72rem;
    }
    .pu-jejak-p {
        flex-shrink: 0;
        max-width: 170px;
        font-size: 0.72rem;
        padding: 0.2rem 0.65rem;
    }
}

.pu-jembatan {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.25rem;
    padding: 0.85rem 1.1rem;
    border-radius: 14px;
    text-decoration: none;
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(99, 102, 241, 0.02) 100%);
    border: 1px dashed var(--hcis-indigo-400);
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.06);
    transition: all 0.18s ease;
}
.pu-jembatan:hover {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-700);
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(99, 102, 241, 0.12);
}
.pu-jembatan > i:first-child {
    color: var(--hcis-indigo-500);
    font-size: 1.1rem;
}
.pu-jembatan > i:last-child {
    margin-left: auto;
    color: var(--hcis-indigo-500);
}
.pu-jembatan strong {
    color: var(--hcis-indigo-700);
}

@media (max-width: 575px) {
    .pu-jembatan {
        padding: 0.65rem 0.85rem;
        gap: 0.55rem;
        font-size: 0.75rem;
        border-radius: 11px;
    }
}

/* ════ Aksi Kartu: Favorit & Intip (Crystal Glass Cockpit Pill) ════ */
:deep(.pu-kartu-aksi),
.pu-kartu-aksi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    border: 1px solid rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: var(--hcis-slate-600);
    padding: 0;
    cursor: pointer;
    outline: none;
    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

:deep(.pu-kartu-aksi:hover),
.pu-kartu-aksi:hover {
    background: var(--hcis-white);
    color: var(--hcis-indigo-700);
    border-color: var(--hcis-indigo-400);
    transform: translateY(-2px) scale(1.08);
    box-shadow:
        0 6px 16px -2px rgba(99, 102, 241, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

:deep(.pu-kartu-aksi:active),
.pu-kartu-aksi:active {
    transform: translateY(0) scale(0.95);
}

/* Tombol Favorit Aktif / Tersemat */
:deep(.pu-kartu-favorit.on),
.pu-kartu-favorit.on {
    background: linear-gradient(135deg, var(--hcis-amber-400) 0%, var(--hcis-amber-500) 100%);
    border-color: var(--hcis-amber-600);
    color: var(--hcis-white);
    box-shadow:
        0 3px 12px rgba(245, 158, 11, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

:deep(.pu-kartu-favorit.on:hover),
.pu-kartu-favorit.on:hover {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
    color: var(--hcis-white);
    border-color: var(--hcis-amber-700);
    box-shadow:
        0 6px 18px rgba(245, 158, 11, 0.55),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

/* Tombol Intip Spesifik */
:deep(.pu-kartu-intip:hover),
.pu-kartu-intip:hover {
    color: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-500);
    box-shadow:
        0 6px 16px -2px rgba(79, 70, 229, 0.32),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

/* ══ Aksi Empty State Responsif ══ */
.pu-kosong-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    width: 100%;
}

@media (max-width: 640px) {
    .pu-kosong-actions {
        flex-direction: column;
        width: 100%;
        max-width: 17rem;
        gap: 0.5rem;
        margin: 0 auto;
    }

    .pu-kosong-actions :deep(.pu-kosong-btn),
    .pu-kosong-actions .pu-kosong-btn {
        width: 100% !important;
        justify-content: center !important;
        min-height: 40px !important;
    }
}
</style>
