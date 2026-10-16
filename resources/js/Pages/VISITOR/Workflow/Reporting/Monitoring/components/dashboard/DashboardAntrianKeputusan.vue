<template>
    <HcisCard
        title="Menunggu keputusan approval"
        description="Daftar pengajuan tamu dan permit yang belum diverifikasi oleh approver."
        icon="hourglass-split"
        sentiment="warning"
        variant="glass"
        size="md"
        divider
        class="ak-card"
    >
        <template #actions>
            <div class="ak-header-tools">
                <!-- View Mode Toggle -->
                <div v-if="baris.length || cari" class="ak-view-toggle">
                    <button
                        type="button"
                        class="ak-toggle-btn"
                        :class="{ 'is-active': viewMode === 'table' }"
                        title="Tampilan Tabel"
                        @click="viewMode = 'table'"
                    >
                        <HcisIcon name="table" size="xs" />
                    </button>
                    <button
                        type="button"
                        class="ak-toggle-btn"
                        :class="{ 'is-active': viewMode === 'grid' }"
                        title="Tampilan Kartu"
                        @click="viewMode = 'grid'"
                    >
                        <HcisIcon name="grid-fill" size="xs" />
                    </button>
                </div>

                <HcisBadge
                    :label="`${paginasi.total} Antrean`"
                    :variant="paginasi.total > 0 ? 'warning' : 'neutral'"
                    size="sm"
                    appearance="subtle"
                />
            </div>
        </template>

        <!-- Search Toolbar inside Card Body (Anti Cramped Header) -->
        <div v-if="baris.length || cari" class="ak-body-toolbar mb-3">
            <HcisField
                :id="`ak-cari-${domain || 'semua'}`"
                v-model="cari"
                placeholder="Cari nomor transaksi, nama pemohon, PIC, atau nama approver…"
                leading-icon="search"
                size="sm"
                clearable
                class="ak-cari"
            />
        </div>

        <!-- TAMPILAN 1: Tabel Desktop -->
        <div v-if="viewMode === 'table'" class="ak-table-wrapper">
            <HcisTable
                :columns="KOLOM"
                :rows="baris"
                row-key="no_transaksi"
                :loading="memuat"
                sentiment="warning"
                remote-sort
                :sort-key="urut"
                :sort-order="arah"
                :empty-title="cari ? 'Tidak ada yang cocok dengan pencarian' : 'Antrean approval bersih'"
                :empty-description="cari ? 'Silakan sesuaikan kata kunci pencarian Anda.' : 'Semua pengajuan telah diverifikasi dan diproses.'"
                dense
                @sort="gantiUrutan"
            >
                <template #cell-judul="{ row }">
                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                        <HcisAvatar
                            :name="row.judul || row.no_transaksi"
                            size="xs"
                            shape="circle"
                            variant="warning"
                            class="flex-shrink-0"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <strong class="text-truncate d-block text-slate-900 fw-bold">{{ row.judul || '-' }}</strong>
                            <span class="text-2xs text-muted font-tabular">#{{ row.no_transaksi }}</span>
                        </div>
                    </div>
                </template>

                <template #cell-jenis="{ row }">
                    <HcisBadge
                        :label="row.jenis"
                        :variant="row.jenis === 'PERMIT' ? 'info' : 'brand'"
                        size="xs"
                        appearance="subtle"
                    />
                </template>

                <template #cell-pemohon="{ row }">
                    <div class="d-flex align-items-center gap-1.5 min-w-0 text-slate-700" :title="row.pemohon">
                        <HcisIcon name="person" size="2xs" class="text-slate-400 flex-shrink-0" />
                        <span class="text-truncate text-xs fw-medium">{{ row.pemohon || '-' }}</span>
                    </div>
                </template>

                <template #cell-approver="{ row }">
                    <div class="d-flex align-items-center gap-1.5 min-w-0 text-amber-800" :title="row.approver">
                        <HcisIcon name="person-check" size="2xs" class="text-amber-500 flex-shrink-0" />
                        <span class="text-truncate text-xs fw-bold">{{ row.approver || '-' }}</span>
                    </div>
                </template>

                <template #cell-menunggu="{ row }">
                    <span class="ak-durasi font-tabular" :class="kelasDurasi(row.umur_menit)">
                        {{ durasi(row.umur_menit) }}
                    </span>
                </template>

                <template #cell-aksi="{ row }">
                    <a
                        :href="tautanAksi(row)"
                        class="ak-aksi"
                        :class="{ 'is-menuju': menuju === row.no_transaksi }"
                        :aria-busy="menuju === row.no_transaksi ? 'true' : undefined"
                        title="Buka di meja verifikasi"
                        @click="bukaReview($event, row)"
                    >
                        <span>{{ menuju === row.no_transaksi ? 'Membuka…' : 'Review' }}</span>
                        <HcisIcon
                            v-if="menuju === row.no_transaksi"
                            name="arrow-clockwise"
                            size="xs"
                            spin
                        />
                        <HcisIcon v-else name="arrow-right-short" size="sm" />
                    </a>
                </template>
            </HcisTable>
        </div>

        <!-- TAMPILAN 2: Kartu Grid Responsif Mobile -->
        <div v-else class="ak-grid-wrap">
            <!-- Skeleton Cards -->
            <div v-if="memuat && !baris.length" class="ak-grid">
                <div v-for="n in 3" :key="`ak-skel-${n}`" class="ak-mobile-card ak-mobile-card--skeleton">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <HcisSkeleton width="60%" height="1.1rem" rounded="sm" />
                        <HcisSkeleton width="20%" height="1.1rem" rounded="full" />
                    </div>
                    <div class="ak-mobile-card__specs mb-2">
                        <HcisSkeleton height="2rem" rounded="sm" />
                        <HcisSkeleton height="2rem" rounded="sm" />
                    </div>
                    <HcisSkeleton height="2rem" rounded="md" />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="!memuat && !baris.length" class="py-4">
                <HcisEmptyState
                    :title="cari ? 'Tidak ada yang cocok dengan pencarian' : 'Antrean approval bersih'"
                    :description="cari ? 'Silakan periksa kata kunci pencarian Anda.' : 'Semua pengajuan telah diverifikasi dan diproses.'"
                    icon="check2-circle"
                />
            </div>

            <!-- Live Card Grid Items -->
            <div v-else class="ak-grid">
                <div
                    v-for="row in baris"
                    :key="row.no_transaksi"
                    class="ak-mobile-card"
                >
                    <!-- Header: Avatar + Title + Waiting Pill -->
                    <div class="ak-mobile-card__header">
                        <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                            <HcisAvatar
                                :name="row.judul || row.no_transaksi"
                                size="sm"
                                shape="circle"
                                variant="warning"
                                class="flex-shrink-0"
                            />
                            <div class="min-w-0 flex-grow-1">
                                <strong class="ak-mobile-card__name text-truncate d-block">
                                    {{ row.judul || '-' }}
                                </strong>
                                <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                    <span class="ak-mobile-card__trx font-tabular">#{{ row.no_transaksi }}</span>
                                    <HcisBadge
                                        :label="row.jenis"
                                        :variant="row.jenis === 'PERMIT' ? 'info' : 'brand'"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                </div>
                            </div>
                        </div>

                        <span class="ak-durasi font-tabular flex-shrink-0" :class="kelasDurasi(row.umur_menit)">
                            <HcisIcon name="clock-history" size="2xs" class="me-1" />
                            {{ durasi(row.umur_menit) }}
                        </span>
                    </div>

                    <!-- Specs Body -->
                    <div class="ak-mobile-card__specs">
                        <div class="ak-mobile-card__spec-item">
                            <span class="ak-mobile-card__spec-label">
                                <HcisIcon name="person" size="2xs" class="text-slate-400" />
                                <span>PIC / Pemohon</span>
                            </span>
                            <span class="ak-mobile-card__spec-val text-truncate" :title="row.pemohon">
                                {{ row.pemohon || '-' }}
                            </span>
                        </div>

                        <div class="ak-mobile-card__spec-item">
                            <span class="ak-mobile-card__spec-label">
                                <HcisIcon name="person-check" size="2xs" class="text-amber-500" />
                                <span>Menunggu Approver</span>
                            </span>
                            <span class="ak-mobile-card__spec-val text-truncate text-amber-700 fw-bold" :title="row.approver">
                                {{ row.approver || '-' }}
                            </span>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="ak-mobile-card__footer">
                        <a
                            :href="tautanAksi(row)"
                            class="ak-mobile-card__action-btn"
                            :class="{ 'is-menuju': menuju === row.no_transaksi }"
                            :aria-busy="menuju === row.no_transaksi ? 'true' : undefined"
                            @click="bukaReview($event, row)"
                        >
                            <HcisIcon
                                v-if="menuju === row.no_transaksi"
                                name="arrow-clockwise"
                                size="xs"
                                spin
                            />
                            <HcisIcon v-else name="shield-check" size="xs" />
                            <span>{{ menuju === row.no_transaksi ? 'Membuka Review…' : 'Review Pengajuan' }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <HcisPagination
                v-if="paginasi.total > paginasi.per_page"
                :model-value="paginasi.current_page"
                :page-size="paginasi.per_page"
                :total="paginasi.total"
                @update:model-value="gantiHalaman"
                @update:page-size="gantiUkuranHalaman"
            />
        </template>
    </HcisCard>
</template>

<script>
import axios from 'axios';
import {
    HcisAvatar,
    HcisBadge,
    HcisCard,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    HcisTable,
} from '@/components/ui';

export default {
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
    name: 'DashboardAntrianKeputusan',
    components: {
        HcisAvatar,
        HcisBadge,
        HcisCard,
        HcisEmptyState,
        HcisField,
        HcisIcon,
        HcisPagination,
        HcisSkeleton,
        HcisTable,
    },
    props: {
        /** 'tamu' | 'permit' | null (kedua domain). Diteruskan ke server. */
        domain: { type: String, default: null },
    },
    data() {
        return {
            viewMode: typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'table',
            // `key` adalah KUNCI URUT yang dikenal server (URUT_ANTRIAN di
            // VisitorDashboardOperasionalService), bukan nama kolom database.
            KOLOM: [
                { key: 'judul', label: 'Pengajuan', sortable: true, width: '220px' },
                { key: 'jenis', label: 'Jenis', width: '96px', sortable: true },
                { key: 'pemohon', label: 'PIC / Pemohon', sortable: true },
                { key: 'approver', label: 'Menunggu approver', sortable: true, width: '180px' },
                { key: 'menunggu', label: 'Sudah', width: '96px', align: 'right', sortable: true },
                { key: 'aksi', label: '', width: '96px', align: 'right' },
            ],
            baris: [],
            memuat: true,
            cari: '',
            urut: 'menunggu',
            arah: 'asc',
            paginasi: { total: 0, current_page: 1, per_page: 10, total_pages: 1 },
            jedaCari: null,
            menuju: '',
        };
    },
    watch: {
        cari() {
            clearTimeout(this.jedaCari);
            this.jedaCari = setTimeout(() => {
                this.paginasi.current_page = 1;
                this.muat();
            }, 350);
        },
    },
    beforeUnmount() {
        clearTimeout(this.jedaCari);
    },
    mounted() {
        this.muat();
    },
    methods: {
        async muat() {
            this.memuat = true;
            try {
                const { data } = await axios.get(this.endpointTamu.antrian, {
                    params: {
                        page: this.paginasi.current_page,
                        limit: this.paginasi.per_page,
                        cari: this.cari || undefined,
                        urut: this.urut,
                        arah: this.arah,
                        domain: this.domain || undefined,
                    },
                });
                if (data && data.success) {
                    this.baris = data.result?.baris ?? [];
                    this.paginasi = data.result?.paginasi ?? this.paginasi;
                }
            } finally {
                this.memuat = false;
            }
        },
        gantiUrutan({ key, order }) {
            this.urut = key;
            this.arah = order;
            this.paginasi.current_page = 1;
            this.muat();
        },
        gantiHalaman(halaman) {
            this.paginasi.current_page = halaman;
            this.muat();
        },
        gantiUkuranHalaman(ukuran) {
            this.paginasi.per_page = ukuran;
            this.paginasi.current_page = 1;
            this.muat();
        },
        durasi(menit) {
            const n = Number(menit) || 0;
            if (n < 60) return `${n} mnt`;
            const jam = Math.floor(n / 60);

            return jam < 24 ? `${jam} jam` : `${Math.floor(jam / 24)} hari`;
        },
        kelasDurasi(menit) {
            const n = Number(menit) || 0;
            if (n >= 1440) return 'ak-durasi--danger';
            if (n >= 240) return 'ak-durasi--warning';

            return 'ak-durasi--neutral';
        },
        bukaReview(event, row) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            this.menuju = row.no_transaksi;
            window.location.href = this.tautanAksi(row);
        },
        tautanAksi(row) {
            // Baris tamu ke meja bertipe halaman ini (Regular / B2B); permit
            // tetap lewat pengalihan meja gabungan lama.
            if (row.jenis !== 'PERMIT') {
                return `${this.endpointTamu.verifikator}?search=${encodeURIComponent(row.no_transaksi)}`;
            }

            return `/verifikator-visitor?jenis=permit&search=${encodeURIComponent(row.no_transaksi)}`;
        },
    },
};
</script>

<style scoped>
.ak-card {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.ak-header-tools {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ak-view-toggle {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 2px;
    gap: 2px;
}

.ak-toggle-btn {
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

.ak-toggle-btn:hover {
    color: var(--hcis-slate-800, #1e293b);
}

.ak-toggle-btn.is-active {
    background: var(--hcis-white, #ffffff);
    color: var(--hcis-warning-600, #d97706);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.ak-body-toolbar {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.ak-cari {
    width: 100%;
    min-width: 0;
}

.ak-table-wrapper {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    overflow-x: auto;
}

/* Mobile Card Grid View */
.ak-grid-wrap {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.ak-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .ak-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.ak-mobile-card {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-left: 4.5px solid var(--hcis-warning-500, #f59e0b);
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-width: 0;
    box-sizing: border-box;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.ak-mobile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -3px rgba(0, 0, 0, 0.08);
}

.ak-mobile-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-width: 0;
}

.ak-mobile-card__name {
    font-size: 0.84375rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
    letter-spacing: -0.01em;
}

.ak-mobile-card__trx {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, #64748b);
}

.ak-mobile-card__specs {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.45rem;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.5rem 0.6rem;
    min-width: 0;
}

.ak-mobile-card__spec-item {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.ak-mobile-card__spec-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.625rem;
    font-weight: 650;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ak-mobile-card__spec-val {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-800, #1e293b);
}

.ak-mobile-card__footer {
    display: flex;
    align-items: center;
    width: 100%;
}

.ak-mobile-card__action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
    padding: 0.425rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-warning-50, #fffbeb);
    border: 1px solid var(--hcis-warning-200, #fde68a);
    color: var(--hcis-warning-800, #92400e);
    text-decoration: none;
    transition: all 0.15s ease;
    min-height: 2.25rem;
    box-sizing: border-box;
}

.ak-mobile-card__action-btn:hover {
    background: var(--hcis-warning-100, #fef3c7);
    color: var(--hcis-warning-900, #78350f);
    text-decoration: none;
}

.ak-mobile-card__action-btn.is-menuju {
    opacity: 0.6;
    pointer-events: none;
}

.ak-durasi {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.2;
    white-space: nowrap;
}

.ak-durasi--neutral {
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
}

.ak-durasi--warning {
    background: var(--hcis-warning-50, var(--hcis-amber-50));
    color: var(--hcis-warning-700, var(--hcis-amber-700));
    border: 1px solid var(--hcis-warning-200, var(--hcis-amber-200));
}

.ak-durasi--danger {
    background: var(--hcis-danger-50, var(--hcis-red-50));
    color: var(--hcis-danger-700, var(--hcis-red-700));
    border: 1px solid var(--hcis-danger-200, var(--hcis-red-200));
}

.ak-aksi {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 700;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    text-decoration: none;
}

.ak-aksi:hover {
    text-decoration: underline;
}

.ak-aksi.is-menuju {
    color: var(--hcis-color-text-muted, var(--hcis-slate-500));
    pointer-events: none;
}

.font-tabular {
    font-variant-numeric: tabular-nums;
}
</style>
