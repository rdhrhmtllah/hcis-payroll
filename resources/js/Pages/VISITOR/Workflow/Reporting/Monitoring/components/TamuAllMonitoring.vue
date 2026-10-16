<template>
    <div class="all-monitoring">
        <HcisAlert v-if="galat" variant="danger" :title="galat" closable class="mb-3" @close="galat = ''" />

        <!-- Saringan Lengkap Server-Side -->
        <HcisFilterBar
            :search="saring.cari"
            search-placeholder="Cari nama tamu, instansi, plat, PIC, keperluan, atau no transaksi…"
            :filters="saringanList"
            :filter-values="nilaiSaringan"
            :results-count="paginasi.total"
            :collapsible="true"
            show-active-tags
            sentiment="brand"
            variant="glass"
            class="mb-3"
            @update:search="gantiCari"
            @filter-change="gantiSaringan"
            @reset="reset"
        />

        <!-- Ringkasan Statistik 3 Metrik Kunci -->
        <div class="all-monitoring__stats mb-3">
            <HcisStatGroup :cols="3" gap="sm" appearance="crystal">
                <HcisStatCard
                    label="Total kunjungan"
                    :value="stats.total_tamu ?? 0"
                    suffix="Tamu"
                    icon="people-fill"
                    sentiment="brand"
                    :loading="memuat"
                    layout="compact"
                    appearance="crystal"
                />
                <HcisStatCard
                    label="Check-Out"
                    :value="stats.sudah_keluar ?? 0"
                    suffix="Tamu"
                    icon="box-arrow-right"
                    sentiment="success"
                    :loading="memuat"
                    layout="compact"
                    appearance="crystal"
                />
                <HcisStatCard
                    label="Cancelled"
                    :value="stats.expired ?? 0"
                    suffix="Tamu"
                    icon="x-circle"
                    sentiment="danger"
                    :loading="memuat"
                    layout="compact"
                    appearance="crystal"
                />
            </HcisStatGroup>
        </div>

        <!-- Toolbar Bilah Tampilan & Kontrol Hasil -->
        <div class="all-monitoring__toolbar mb-2">
            <div class="d-flex align-items-center gap-2">
                <HcisIcon name="list-columns-reverse" size="xs" class="text-indigo-600" />
                <span class="all-monitoring__toolbar-title">Daftar Arsip & Riwayat Tamu</span>
                <HcisBadge
                    :label="`${paginasi.total} Data`"
                    variant="brand"
                    size="xs"
                    appearance="subtle"
                />
            </div>

            <!-- View Mode Switcher -->
            <div class="all-monitoring__view-toggle">
                <button
                    type="button"
                    class="all-monitoring__toggle-btn"
                    :class="{ 'is-active': viewMode === 'table' }"
                    title="Tampilan Tabel"
                    @click="viewMode = 'table'"
                >
                    <HcisIcon name="table" size="xs" />
                </button>
                <button
                    type="button"
                    class="all-monitoring__toggle-btn"
                    :class="{ 'is-active': viewMode === 'grid' }"
                    title="Tampilan Kartu"
                    @click="viewMode = 'grid'"
                >
                    <HcisIcon name="grid-fill" size="xs" />
                </button>
            </div>
        </div>

        <!-- TAMPILAN 1: Mode Tabel (Desktop & Tablet Lebar) -->
        <div v-if="viewMode === 'table'" class="all-monitoring__table-wrapper">
            <HcisTable
                :columns="KOLOM"
                :rows="baris"
                row-key="No_Transaksi"
                :loading="memuat"
                sentiment="brand"
                remote-sort
                :sort-key="urutKolom"
                :sort-order="saring.arah"
                :empty-title="saring.cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada kunjungan pada saringan ini'"
                :empty-description="saring.cari ? 'Silakan sesuaikan kata kunci pencarian Anda.' : 'Data histori kunjungan tamu akan muncul di sini.'"
                dense
                interactive
                @sort="gantiUrutan"
                @row-click="bukaDetail"
            >
                <template #cell-No_Transaksi="{ row }">
                    <button
                        type="button"
                        class="all-monitoring__link font-tabular"
                        title="Lihat detail kunjungan"
                        @click.stop="bukaDetail(row)"
                    >
                        <HcisIcon name="receipt" size="xs" class="me-1 opacity-70" />
                        <span>{{ row.No_Transaksi }}</span>
                    </button>
                </template>

                <template #cell-Tanggal_Kunjungan="{ row }">
                    <span class="all-monitoring__waktu">{{ tanggal(row.Tanggal_Kunjungan) }}</span>
                </template>

                <template #cell-Nama_Tamu="{ row }">
                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                        <HcisAvatar
                            :name="row.Nama_Tamu || row.Nama_Lengkap_Tamu"
                            size="xs"
                            shape="circle"
                            :variant="warnaStatusTamu(row.Status_Kunjungan)"
                            class="flex-shrink-0"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <strong class="text-truncate d-block text-slate-900 fw-bold">{{ row.Nama_Tamu || row.Nama_Lengkap_Tamu || '-' }}</strong>
                            <div class="d-flex align-items-center gap-1 flex-wrap mt-0.5">
                                <!-- Dua dimensi yang menentukan perjalanan tamu.
                                     Tanpa keduanya, tamu berjalan kaki dan tamu
                                     mitra berkendara tampil persis sama. -->
                                <span class="am-tag" :class="'am-tag--' + tipeVarian(row.Tipe_Tamu)">
                                    {{ labelTipe(row.Tipe_Tamu, row.B2B_Unit) }}
                                </span>
                                <span
                                    v-if="row.Sumber_Kunjungan === 'MITRA'"
                                    class="am-tag am-tag--mitra"
                                    :title="row.Nama_Mitra || 'Aplikasi mitra'"
                                >
                                    Mitra
                                </span>
                            </div>
                            <span v-if="row.Jumlah_Tamu > 1" class="ph-mini-pill font-tabular mt-0.5">
                                {{ row.Jumlah_Tamu }} org
                            </span>
                        </div>
                    </div>
                </template>

                <template #cell-Instansi_Asal="{ row }">
                    <div class="d-flex align-items-center gap-1.5 min-w-0 text-slate-700" :title="row.Instansi_Asal || row.Instansi_Atau_Asal">
                        <HcisIcon name="building" size="2xs" class="text-slate-400 flex-shrink-0" />
                        <span class="text-truncate text-xs fw-medium">{{ row.Instansi_Asal || row.Instansi_Atau_Asal || 'Umum / Pribadi' }}</span>
                    </div>
                </template>

                <template #cell-Status_Kunjungan="{ row }">
                    <HcisBadge
                        :label="labelStatusTamu(row.Status_Kunjungan)"
                        :variant="warnaStatusTamu(row.Status_Kunjungan)"
                        :pulse="sedangDiDalam(row.Status_Kunjungan)"
                        size="xs"
                        appearance="subtle"
                    />
                </template>

                <template #cell-Status_Permit_Coklat="{ row }">
                    <!-- Tamu reguler memang tidak berpermit; itu bukan
                         kekurangan data, jadi tidak ditampilkan sebagai '-'
                         yang menggantung tetapi sebagai keterangan. -->
                    <span
                        v-if="row.Status_Permit_Coklat"
                        class="am-tag"
                        :class="'am-tag--izin-' + varianIzin(row.Status_Permit_Coklat)"
                    >
                        {{ labelIzin(row.Status_Permit_Coklat) }}
                    </span>
                    <span v-else class="text-2xs text-muted">Tanpa permit</span>
                </template>

                <template #cell-masuk="{ row }">
                    <span class="all-monitoring__waktu">{{ jam(row.Waktu_Aktual_Masuk) }}</span>
                </template>

                <template #cell-keluar="{ row }">
                    <span class="all-monitoring__waktu">{{ jam(row.Waktu_Aktual_Keluar) }}</span>
                </template>

                <template #cell-aksi="{ row }">
                    <HcisButton
                        label="Detail"
                        variant="secondary"
                        size="xs"
                        leading-icon="eye"
                        @click.stop="bukaDetail(row)"
                    />
                </template>

                <template #footer>
                    <HcisPagination
                        v-if="paginasi.total > 10"
                        :model-value="paginasi.current_page"
                        :page-size="saring.limit"
                        :page-sizes="[10, 20, 50, 100]"
                        :total="paginasi.total"
                        @update:model-value="gantiHalaman"
                        @update:page-size="gantiUkuranHalaman"
                    />
                </template>
            </HcisTable>
        </div>

        <!-- TAMPILAN 2: Mode Kartu Grid Responsif (Mobile & Tablet) -->
        <div v-else class="all-tamu-grid-wrap">
            <!-- Skeleton Loading Cards -->
            <div v-if="memuat && !baris.length" class="all-tamu-grid">
                <div v-for="n in 4" :key="`skel-all-${n}`" class="all-tamu-card all-tamu-card--skeleton">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <HcisSkeleton width="60%" height="1.1rem" rounded="sm" />
                        <HcisSkeleton width="25%" height="1.1rem" rounded="full" />
                    </div>
                    <div class="all-tamu-card__specs-grid mb-2">
                        <HcisSkeleton height="2.2rem" rounded="sm" />
                        <HcisSkeleton height="2.2rem" rounded="sm" />
                    </div>
                    <HcisSkeleton height="2rem" rounded="md" />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!memuat && !baris.length" class="py-4">
                <HcisEmptyState
                    :title="saring.cari ? 'Tidak ada yang cocok dengan pencarian' : 'Tidak ada kunjungan pada saringan ini'"
                    description="Coba sesuaikan tanggal, status filter, atau kata kunci pencarian Anda."
                    icon="search"
                />
            </div>

            <!-- Live Card Grid Items -->
            <div v-else class="all-tamu-grid">
                <div
                    v-for="row in baris"
                    :key="row.No_Transaksi"
                    class="all-tamu-card"
                    :class="[
                        'all-tamu-card--' + warnaStatusTamu(row.Status_Kunjungan),
                        { 'is-active-presence': sedangDiDalam(row.Status_Kunjungan) }
                    ]"
                    @click="bukaDetail(row)"
                >
                    <!-- Header: Avatar + Nama + Status Badge -->
                    <div class="all-tamu-card__header">
                        <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                            <HcisAvatar
                                :name="row.Nama_Tamu || row.Nama_Lengkap_Tamu"
                                size="sm"
                                shape="circle"
                                :variant="warnaStatusTamu(row.Status_Kunjungan)"
                                class="flex-shrink-0"
                            />
                            <div class="min-w-0 flex-grow-1">
                                <strong class="all-tamu-card__name text-truncate d-block">
                                    {{ row.Nama_Tamu || row.Nama_Lengkap_Tamu || '-' }}
                                </strong>
                                <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                    <span class="all-tamu-card__trx font-tabular">#{{ row.No_Transaksi }}</span>
                                    <span v-if="row.Jumlah_Tamu > 1" class="ph-mini-pill font-tabular">
                                        {{ row.Jumlah_Tamu }} org
                                    </span>
                                </div>
                            </div>
                        </div>

                        <HcisBadge
                            :label="labelStatusTamu(row.Status_Kunjungan)"
                            :variant="warnaStatusTamu(row.Status_Kunjungan)"
                            :pulse="sedangDiDalam(row.Status_Kunjungan)"
                            size="xs"
                            appearance="subtle"
                            class="flex-shrink-0"
                        />
                    </div>

                    <!-- Live Presence Banner (Emerald if Active/Inside) -->
                    <div v-if="sedangDiDalam(row.Status_Kunjungan)" class="all-tamu-card__presence-strip">
                        <span class="all-tamu-card__pulse-dot" />
                        <span class="all-tamu-card__presence-text">Status Check-In (Aktif di area)</span>
                    </div>

                    <!-- 2x2 Specs Grid -->
                    <div class="all-tamu-card__specs-grid">
                        <div class="all-tamu-card__spec-item">
                            <span class="all-tamu-card__spec-label">
                                <HcisIcon name="building" size="2xs" class="text-slate-400" />
                                <span>Instansi / Asal</span>
                            </span>
                            <span class="all-tamu-card__spec-val text-truncate" :title="row.Instansi_Asal || row.Instansi_Atau_Asal">
                                {{ row.Instansi_Asal || row.Instansi_Atau_Asal || 'Umum / Pribadi' }}
                            </span>
                        </div>

                        <div class="all-tamu-card__spec-item">
                            <span class="all-tamu-card__spec-label">
                                <HcisIcon name="calendar-event" size="2xs" class="text-slate-400" />
                                <span>Tgl Kunjungan</span>
                            </span>
                            <span class="all-tamu-card__spec-val font-tabular">
                                {{ tanggal(row.Tanggal_Kunjungan) }}
                            </span>
                        </div>

                        <div class="all-tamu-card__spec-item">
                            <span class="all-tamu-card__spec-label">
                                <HcisIcon name="box-arrow-in-right" size="2xs" class="text-indigo-500" />
                                <span>Waktu Masuk</span>
                            </span>
                            <span class="all-tamu-card__spec-val font-tabular">
                                {{ jam(row.Waktu_Aktual_Masuk) }}
                            </span>
                        </div>

                        <div class="all-tamu-card__spec-item">
                            <span class="all-tamu-card__spec-label">
                                <HcisIcon name="box-arrow-right" size="2xs" class="text-rose-500" />
                                <span>Waktu Keluar</span>
                            </span>
                            <span class="all-tamu-card__spec-val font-tabular">
                                {{ jam(row.Waktu_Aktual_Keluar) }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Action Footer -->
                    <div class="all-tamu-card__footer">
                        <button
                            type="button"
                            class="all-tamu-card__action-btn"
                            @click.stop="bukaDetail(row)"
                        >
                            <HcisIcon name="eye" size="xs" />
                            <span>Lihat Detail Kunjungan</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="paginasi.total > 10" class="mt-3">
                <HcisPagination
                    :model-value="paginasi.current_page"
                    :page-size="saring.limit"
                        :page-sizes="[10, 20, 50, 100]"
                    :total="paginasi.total"
                    @update:model-value="gantiHalaman"
                    @update:page-size="gantiUkuranHalaman"
                />
            </div>
        </div>

        <!-- Modal Review & Detail Kunjungan Tamu (Dual-Pane Dialog) -->
        <TamuReviewModal
            v-if="dialogDetail"
            :show="dialogDetail"
            :tamu-id="idDetail"
            :detail-endpoint="endpointTamu.detail"
            read-only
            :boleh-buka-permit="bolehBukaPermit"
            @close="dialogDetail = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import TamuReviewModal from '@/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisEmptyState,
    HcisStatCard,
    HcisStatGroup,
    HcisTable,
} from '@/components/ui';

/** Nama kolom HARUS sama persis dengan alias yang dikirim getLaporanTamu. */
const KOLOM = [
    { key: 'No_Transaksi', label: 'No transaksi', width: '155px', sortable: true },
    { key: 'Tanggal_Kunjungan', label: 'Tanggal', width: '120px', sortable: true },
    { key: 'Nama_Tamu', label: 'Nama tamu', sortable: true, width: '220px' },
    { key: 'Instansi_Asal', label: 'Instansi / Asal', sortable: true },
    { key: 'Status_Kunjungan', label: 'Status', width: '150px', sortable: true },
    { key: 'Status_Permit_Coklat', label: 'Permit', width: '120px' },
    { key: 'masuk', label: 'Masuk', width: '90px', sortable: true },
    { key: 'keluar', label: 'Keluar', width: '90px', sortable: true },
    { key: 'aksi', label: 'Aksi', width: '90px', align: 'right' },
];

/** Kolom tabel -> kunci urut yang dikenal server (URUT_TAMU di controller). */
const PETA_URUT = {
    No_Transaksi: 'no_transaksi',
    Tanggal_Kunjungan: 'tanggal',
    Nama_Tamu: 'nama',
    Instansi_Asal: 'instansi',
    Status_Kunjungan: 'status',
    masuk: 'masuk',
    keluar: 'keluar',
};

const SARINGAN = [
    { key: 'date_from', label: 'Dari tanggal', type: 'date', icon: 'calendar-range' },
    { key: 'date_to', label: 'Sampai tanggal', type: 'date', icon: 'calendar-range' },
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'Semua',
        icon: 'funnel',
        // Kosakata PERSIS sama dengan kolomnya. Penyaring yang memakai
        // kosakata lain memulangkan baris yang lencananya bertuliskan hal
        // berbeda — dan tidak ada galat yang menjelaskannya.
        options: [
            { value: 'SCHEDULED', label: 'Scheduled' },
            { value: 'PENDING_APPROVAL', label: 'Menunggu Approval' },
            { value: 'CHECKED_IN', label: 'Check-In' },
            { value: 'CHECKED_OUT', label: 'Check-Out' },
            { value: 'KELUAR_TAK_TERCATAT', label: 'Keluar Tak Tercatat' },
            { value: 'CANCELLED', label: 'Cancelled' },
            { value: 'REJECTED', label: 'Ditolak' },
            { value: 'WITHDRAWN', label: 'Ditarik Pemohon' },
            { value: 'REVOKED', label: 'Dicabut' },
        ],
    },
    {
        // Tipe ditetapkan halaman; yang bisa disaring adalah kategorinya.
        key: 'kategori',
        label: 'Kategori',
        type: 'select',
        placeholder: 'Semua kategori',
        icon: 'person-vcard',
        options: [
            { value: 'VISITOR', label: 'Visitor (maks. 24 jam)' },
            { value: 'MITRA', label: 'Mitra (lebih dari 24 jam)' },
        ],
    },
    {
        key: 'sumber',
        label: 'Asal pendaftaran',
        type: 'select',
        placeholder: 'Semua asal',
        icon: 'box-arrow-in-down',
        options: [
            { value: 'HCIS', label: 'HCIS' },
            { value: 'MITRA', label: 'Procurment B2B' },
        ],
    },
];

export default {
    name: 'TamuAllMonitoring',
    components: {
        TamuReviewModal,
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisFilterBar,
        HcisIcon,
        HcisPagination,
        HcisSkeleton,
        HcisEmptyState,
        HcisStatCard,
        HcisStatGroup,
        HcisTable,
    },
    props: {
        bolehBukaPermit: { type: Boolean, default: false },
        needsApproval: { type: Boolean, default: false },
    },
    inject: {
        // Disediakan halaman Monitoring Tamu (Regular / B2B). Bawaannya
        // endpoint Regular, sehingga pemakai lain (halaman permit, uji) tidak
        // perlu menyediakannya.
        endpointTamu: {
            from: 'visitorTamuEndpoints',
            default: () => ({
                laporan: '/laporanvisitor-tamu',
                detail: '/laporanvisitor-detail-tamu',
                papan_harian: '/laporanvisitor-papan-harian',
                papan_tamu: '/laporanvisitor-papan-tamu',
                antrian: '/laporanvisitor-antrian',
                verifikator: '/verifikator-visitor/tamu',
            }),
        },
    },
    data() {
        return {
            KOLOM,
            SARINGAN,
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            memuat: true,
            galat: '',
            baris: [],
            stats: {},
            paginasi: { current_page: 1, total_pages: 1, total: 0 },
            // Bawaan server: pengajuan terbaru di atas — tidak mewakili satu
            // kolom tabel, jadi tak ada kolom yang disorot.
            urutKolom: null,
            dialogDetail: false,
            idDetail: null,
            saring: this.saringanBaku(),
        };
    },
    computed: {
        saringanList() {
            // Unit pengaju (DDL-2026-122) hanya bermakna di halaman B2B.
            const dasar = this.endpointTamu.laporan.includes('-b2b-')
                ? [
                      ...SARINGAN,
                      {
                          key: 'unit',
                          label: 'Unit pengaju',
                          type: 'select',
                          placeholder: 'Semua unit',
                          icon: 'truck',
                          options: [
                              { value: 'PROCUREMENT', label: 'B2B Procurement' },
                              { value: 'LOGISTIC', label: 'B2B Logistic' },
                          ],
                      },
                      {
                          // Keadaan izin gerbang (permit COKLAT) — kosakata
                          // yang sama dengan kolom Permit di tabel.
                          key: 'status_permit',
                          label: 'Status permit',
                          type: 'select',
                          placeholder: 'Semua status permit',
                          icon: 'shield-check',
                          options: [
                              { value: 'MENUNGGU', label: 'Menunggu verifikator' },
                              { value: 'AKTIF', label: 'Aktif' },
                              { value: 'KEDALUWARSA', label: 'Kedaluwarsa' },
                              { value: 'DITOLAK', label: 'Ditolak' },
                              { value: 'DICABUT', label: 'Dicabut' },
                              { value: 'TANPA_PERMIT', label: 'Tanpa permit' },
                          ],
                      },
                  ]
                : SARINGAN;
            if (this.needsApproval) {
                return dasar;
            }
            return dasar.map((s) => {
                if (s.key === 'status') {
                    return {
                        ...s,
                        options: (s.options || []).filter((opt) => opt.value !== 'PENDING_APPROVAL'),
                    };
                }
                return s;
            });
        },
        nilaiSaringan() {
            return {
                date_from: this.saring.date_from,
                date_to: this.saring.date_to,
                status: this.saring.status,
                kategori: this.saring.kategori,
                sumber: this.saring.sumber,
                unit: this.saring.unit,
                status_permit: this.saring.status_permit,
            };
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        saringanBaku() {
            // Tanggal LOKAL, bukan toISOString(): yang terakhir memakai UTC,
            // sehingga pukul 00:00–07:00 WIB "hari ini" terbaca kemarin.
            const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const geser = (hari) => {
                const d = new Date();
                d.setDate(d.getDate() + hari);
                return d;
            };

            return {
                cari: '',
                // 30 hari ke belakang s.d. 30 hari ke DEPAN: kunjungan yang sudah
                // dijadwalkan besok dst. ikut terlihat (30 Sep 2026).
                date_from: ymd(geser(-30)),
                date_to: ymd(geser(30)),
                status: '',
                kategori: '',
                sumber: '',
                unit: '',
                status_permit: '',
                // Pengajuan TERBARU di atas, sama dengan monitoring permit.
                urut: 'diajukan',
                arah: 'desc',
                page: 1,
                limit: 20,
            };
        },
        bukaDetail(sumber) {
            // HcisTable memancarkan { event, row, index }; tombol Detail memanggil
            // dengan barisnya LANGSUNG. Menerima satu bentuk saja membuat klik
            // pada BARIS membuka modal kosong tanpa satu pun galat — null adalah
            // nilai yang sah bagi prop-nya, jadi tidak ada yang mengeluh.
            const baris = sumber?.row ?? sumber;
            if (!baris) return;
            this.idDetail = baris.Id ?? baris.Id_Transaksi_Tamu ?? null;
            this.dialogDetail = true;
        },
        labelTipe(tipe, unit = null) {
            if (String(tipe || 'REGULAR').toUpperCase() !== 'B2B') return 'Reguler';
            return { PROCUREMENT: 'B2B Procurement', LOGISTIC: 'B2B Logistic' }[unit] || 'B2B';
        },
        tipeVarian(tipe) {
            return String(tipe || 'REGULAR').toUpperCase() === 'B2B' ? 'b2b' : 'reguler';
        },
        /**
         * Kosakata izin gerbang — SATU sumber untuk label, tooltip, dan kelas
         * gaya. Dua terjemahan yang berbeda tipis terbaca sebagai dua keadaan
         * yang berbeda.
         */
        labelIzin(status) {
            return {
                AKTIF: 'Aktif',
                MENUNGGU: 'Menunggu',
                DITOLAK: 'Ditolak',
                DICABUT: 'Dicabut',
                KEDALUWARSA: 'Kedaluwarsa',
            }[status] || 'Tidak ada';
        },
        varianIzin(status) {
            if (status === 'AKTIF') return 'success';
            if (status === 'MENUNGGU') return 'warning';
            if (status === 'DITOLAK' || status === 'DICABUT') return 'danger';

            return 'secondary';
        },
        /**
         * Kosakata SIKLUS kunjungan — sejalan dengan siklusKunjunganSql().
         *
         * Menggantikan label lama yang memetakan 'AKTIF' menjadi
         * "Aktif / Di Dalam". Dua hal itu bukan hal yang sama: AKTIF berarti
         * barisnya hidup di gerbang, bukan bahwa orangnya ada di dalam — dan
         * setiap kunjungan yang disetujui karena itu berlencana hijau
         * "Di Dalam", termasuk yang tamunya tidak pernah datang.
         *
         * Nilai lama tetap dikenali supaya baris warisan tidak tampil sebagai
         * kode mentah bila ada jalur yang belum ikut diperbarui.
         */
        labelStatusTamu(status) {
            return {
                SCHEDULED: 'Scheduled',
                PENDING_APPROVAL: 'Menunggu Approval',
                CHECKED_IN: 'Check-In',
                CHECKED_OUT: 'Check-Out',
                KELUAR_TAK_TERCATAT: 'Keluar Tak Tercatat',
                CANCELLED: 'Cancelled',
                REJECTED: 'Ditolak',
                WITHDRAWN: 'Ditarik Pemohon',
                REVOKED: 'Dicabut',
            }[status] || status || '-';
        },
        warnaStatusTamu(status) {
            return {
                SCHEDULED: 'brand',
                PENDING_APPROVAL: 'warning',
                CHECKED_IN: 'success',
                CHECKED_OUT: 'neutral',
                KELUAR_TAK_TERCATAT: 'warning',
                CANCELLED: 'danger',
                REJECTED: 'danger',
                WITHDRAWN: 'neutral',
                REVOKED: 'danger',
            }[status] || 'neutral';
        },
        /**
         * Hanya yang BENAR-BENAR di dalam yang berdenyut.
         *
         * Denyut hijau adalah isyarat kehadiran nyata; memasangnya pada setiap
         * kunjungan yang disetujui membuat isyarat itu berhenti berarti apa pun.
         */
        sedangDiDalam(status) {
            return status === 'CHECKED_IN';
        },
        tanggal(nilai) {
            return nilai ? String(nilai).slice(0, 10) : '-';
        },
        jam(waktu) {
            return waktu ? waktu.slice(11, 16) : '-';
        },
        gantiCari(nilai) {
            this.saring.cari = nilai;
            this.muatDariAwal();
        },
        gantiSaringan({ key, value }) {
            this.saring[key] = value ?? '';
            this.muatDariAwal();
        },
        gantiUrutan({ key, order }) {
            const kunci = PETA_URUT[key];
            if (! kunci) return;

            this.urutKolom = key;
            this.saring.urut = kunci;
            this.saring.arah = order;
            this.muatDariAwal();
        },
        gantiUkuranHalaman(ukuran) {
            this.saring.limit = ukuran;
            this.muatDariAwal();
        },
        muatDariAwal() {
            this.saring.page = 1;
            this.muat();
        },
        gantiHalaman(halaman) {
            this.saring.page = halaman;
            this.muat();
        },
        reset() {
            this.saring = this.saringanBaku();
            this.urutKolom = null;
            this.muat();
        },
        async muat() {
            this.memuat = true;
            this.galat = '';
            try {
                const { data } = await axios.get(this.endpointTamu.laporan, { params: this.saring });
                this.baris = data?.result ?? [];
                this.stats = data?.stats ?? {};
                this.paginasi = data?.pagination ?? { current_page: 1, total_pages: 1, total: 0 };
            } catch (error) {
                this.galat = error?.response?.data?.message || 'Gagal memuat daftar kunjungan.';
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
/* Bentuknya sengaja sama persis dengan penanda di Papan Harian: satu admin
   berpindah antar dua tab dalam satu halaman, dan penanda yang berbeda bentuk
   membuatnya mengira sedang membaca hal yang berbeda. */
.am-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.am-tag--b2b {
    color: #3730a3;
    background: #e0e7ff;
    border: 1px solid #c7d2fe;
}

.am-tag--reguler {
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.am-tag--mitra {
    color: #9a3412;
    background: #ffedd5;
    border: 1px solid #fed7aa;
}

.am-tag--izin-success {
    color: #065f46;
    background: #d1fae5;
    border: 1px solid #a7f3d0;
}

.am-tag--izin-warning {
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.am-tag--izin-danger {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

.am-tag--izin-secondary {
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.all-monitoring {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.all-monitoring__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.25rem 0;
}

.all-monitoring__toolbar-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    letter-spacing: -0.01em;
}

.all-monitoring__view-toggle {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 2px;
    gap: 2px;
}

.all-monitoring__toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    color: var(--hcis-slate-500, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.all-monitoring__toggle-btn:hover {
    color: var(--hcis-slate-800, #1e293b);
}

.all-monitoring__toggle-btn.is-active {
    background: var(--hcis-white, #ffffff);
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.all-monitoring__table-wrapper {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
}

.all-monitoring__waktu {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    font-size: 0.8125rem;
    color: var(--hcis-slate-700, #334155);
}

.all-monitoring__link {
    display: inline-flex;
    align-items: center;
    background: transparent;
    border: none;
    padding: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
    cursor: pointer;
    text-align: left;
    transition: color 0.15s ease;
}

.all-monitoring__link:hover {
    color: var(--hcis-brand-700, #3730a3);
    text-decoration: underline;
}

.ph-mini-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.5rem;
    height: 1.35rem;
    padding: 0 0.4rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 700;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-700, #334155);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

/* Responsive Card Grid */
.all-tamu-grid-wrap {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.all-tamu-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .all-tamu-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1200px) {
    .all-tamu-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.all-tamu-card {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-width: 0;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.all-tamu-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -3px rgba(0, 0, 0, 0.08);
}

/* Emerald Active Presence */
.all-tamu-card.is-active-presence {
    border-left: 4.5px solid var(--hcis-success-500, #10b981) !important;
    background: linear-gradient(225deg, rgba(236, 253, 245, 0.96) 0%, rgba(255, 255, 255, 0.98) 45%, rgba(209, 250, 229, 0.75) 100%) !important;
    border-color: rgba(16, 185, 129, 0.4) !important;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.16) !important;
}

.all-tamu-card--danger {
    border-left: 4.5px solid var(--hcis-danger-500, #ef4444);
}

.all-tamu-card--warning {
    border-left: 4.5px solid var(--hcis-warning-500, #f59e0b);
}

.all-tamu-card--brand {
    border-left: 4.5px solid var(--hcis-brand-500, #6366f1);
}

.all-tamu-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
}

.all-tamu-card__name {
    font-size: 0.84375rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
}

.all-tamu-card__trx {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, #64748b);
}

.all-tamu-card__presence-strip {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(236, 253, 245, 0.85);
    border: 1px solid rgba(167, 243, 208, 0.8);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
}

.all-tamu-card__pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-success-500, #10b981);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    animation: livePulse 1.8s infinite;
}

.all-tamu-card__presence-text {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-success-700, #047857);
}

.all-tamu-card__specs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.45rem;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.5rem 0.6rem;
    min-width: 0;
}

.all-tamu-card__spec-item {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.all-tamu-card__spec-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.625rem;
    font-weight: 650;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.all-tamu-card__spec-val {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
}

.all-tamu-card__footer {
    display: flex;
    align-items: center;
    width: 100%;
}

.all-tamu-card__action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
    padding: 0.425rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-700, #4338ca);
    cursor: pointer;
    transition: all 0.15s ease;
    min-height: 2.25rem;
    box-sizing: border-box;
}

.all-tamu-card__action-btn:hover {
    background: var(--hcis-brand-100, #e0e7ff);
    color: var(--hcis-brand-800, #3730a3);
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}

@keyframes livePulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.6;
        transform: scale(0.9);
    }
}

/* Responsive Stat Cards: Clean 3-Pod layout */
@media (max-width: 640px) {
    .all-monitoring__stats :deep(.hcis-stat-group) {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
    }

    .all-monitoring__stats :deep(.hcis-stat) {
        min-height: 4.25rem !important;
        padding: 0.55rem 0.65rem !important;
        border-radius: 12px !important;
    }
}

@media (min-width: 641px) and (max-width: 991.98px) {
    .all-monitoring__stats :deep(.hcis-stat-group) {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.5rem !important;
    }
}
</style>
