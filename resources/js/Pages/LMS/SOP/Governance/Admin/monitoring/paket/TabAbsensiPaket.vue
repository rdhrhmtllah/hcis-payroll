<template>
    <div class="sop-scope tap-root">
        <!-- ══ 1. HCIS COMPACT STAT GROUP WIDGET (PRESENSI & ABSENSI) ══ -->
        <div class="sop-fade-up mb-3">
            <HcisStatGroup
                :stats="statAbsensi"
                :cols="4"
                gap="sm"
                layout="compact"
                appearance="crystal"
            />
        </div>

        <!-- ══ 2. HCIS FILTER BAR (SEARCH, CHIPS, ADVANCED FILTERS, BULK EXPAND & EXPORT) ══ -->
        <div class="sop-fade-up mb-3">
            <HcisFilterBar
                :search="filterState.search"
                :filters="filterBarConfig"
                :filter-values="filterState"
                :quick-chips="quickChipsConfig"
                :active-chip-value="filterState.jenis"
                :results-count="filteredGroupedAbsensi.length"
                search-placeholder="Cari nama karyawan, NIK, departemen, atau lokasi..."
                variant="glass"
                sentiment="brand"
                collapsible
                allow-export
                show-active-tags
                @update:search="onSearchChange"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
                @export="exportCsv"
            >
                <template #actions>
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="chevron-expand"
                            label="Buka Semua"
                            title="Buka seluruh accordion karyawan"
                            @click="bukaSemuaAccordion"
                        />
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="chevron-contract"
                            label="Tutup Semua"
                            title="Tutup seluruh accordion karyawan"
                            @click="tutupSemuaAccordion"
                        />
                    </div>
                </template>
            </HcisFilterBar>
        </div>

        <!-- ══ 3. ACCORDION DAFTAR KARYAWAN & BUKTI PRESENSI ══ -->
        <div class="sop-fade-up">
            <!-- State: Kosong / Tidak Ada Data -->
            <div v-if="!filteredGroupedAbsensi.length" class="text-center py-5 bg-white rounded-3 border">
                <HcisEmptyState
                    :icon="groupedAbsensi.length ? 'search' : 'camera-video-off'"
                    :title="groupedAbsensi.length ? 'Tidak Ada Data Presensi yang Cocok' : 'Belum Ada Catatan Presensi'"
                    :description="groupedAbsensi.length ? 'Tidak ditemukan data presensi yang sesuai dengan saringan atau kata kunci saat ini.' : 'Presensi tercatat otomatis saat karyawan melakukan check-in foto selfie saat mempelajari paket dokumen.'"
                >
                    <template #actions>
                        <HcisButton
                            v-if="groupedAbsensi.length"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            label="Reset Saringan"
                            @click="resetFilters"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <!-- List Accordion Per Karyawan (HCIS Collapse & HcisCard) -->
            <div v-else class="sop-stack gap-3">
                <HcisCollapse
                    :model-value="openAccordionKeys"
                    variant="card"
                    @update:model-value="openAccordionKeys = $event"
                >
                    <HcisCollapseItem
                        v-for="grp in paginatedGroupedAbsensi"
                        :key="grp.Kode_Karyawan"
                        :name="grp.Kode_Karyawan"
                    >
                        <!-- Header Accordion Karyawan (Slot Resmi HcisCollapseItem) -->
                        <template #icon>
                            <HcisAvatar
                                :name="grp.Nama || grp.Kode_Karyawan"
                                size="sm"
                                shape="squircle"
                                variant="brand"
                            />
                        </template>

                        <template #title>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-bold text-dark font-scale-xs text-truncate">
                                    {{ grp.Nama || grp.Kode_Karyawan }}
                                </span>
                                <HcisBadge
                                    :label="grp.No_Induk_Karyawan || grp.Kode_Karyawan"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="2xs"
                                    class="font-monospace"
                                />
                                <HcisBadge
                                    v-if="grp.ada_lokasi_meragukan"
                                    label="GPS Rendah"
                                    variant="warning"
                                    appearance="subtle"
                                    size="2xs"
                                    icon="exclamation-triangle-fill"
                                />
                            </div>
                        </template>

                        <template #description>
                            <div class="d-flex align-items-center gap-1.5 font-scale-2xs text-muted text-truncate mt-0.5 flex-wrap">
                                <span v-if="grp.Departemen || grp.Divisi">
                                    {{ [grp.Departemen, grp.Divisi].filter(Boolean).join(' · ') }}
                                </span>
                                <span v-if="grp.Jabatan" class="text-slate-300">|</span>
                                <span v-if="grp.Jabatan" class="text-slate-600">{{ grp.Jabatan }}</span>
                                <span v-if="grp.Lokasi" class="text-slate-400">({{ grp.Lokasi }})</span>
                            </div>
                        </template>

                        <template #badge>
                            <HcisBadge
                                :label="`${grp.total_presensi} Presensi`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                                icon="camera-fill"
                            />
                        </template>

                        <!-- Body Accordion: Grid Bukti Presensi Berbasis HcisCard Resmi -->
                        <div class="tap-collapse-body">
                            <!-- 1. Header Banner Konteks Kehadiran Karyawan -->
                            <HcisCard size="xs" variant="flat" sentiment="neutral" class="tap-context-card">
                                <template #icon>
                                    <HcisIcon name="journal-check" size="xs" class="text-brand" />
                                </template>
                                <template #title>
                                    <span class="font-scale-xs fw-semibold text-slate-800">
                                        Riwayat Presensi Karyawan ({{ grp.riwayat.length }} Catatan)
                                    </span>
                                </template>
                                <template #badge>
                                    <div class="d-flex align-items-center gap-2">
                                        <HcisBadge
                                            :label="`${grp.riwayat.filter(r => Boolean(r.Foto_Url)).length} Foto Valid`"
                                            variant="success"
                                            appearance="subtle"
                                            size="xs"
                                            icon="patch-check-fill"
                                        />
                                        <HcisBadge
                                            v-if="grp.ada_lokasi_meragukan"
                                            label="Deviasi GPS > 50m"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                            icon="exclamation-triangle-fill"
                                        />
                                    </div>
                                </template>
                            </HcisCard>

                            <!-- 2. Grid Kartu Presensi menggunakan HcisCard -->
                            <div class="row g-3">
                                <div
                                    v-for="(abs, absIdx) in grp.riwayat"
                                    :key="abs.Id_SOP_Absensi || absIdx"
                                    class="col-12 col-sm-6 col-lg-4 col-xl-3"
                                >
                                    <HcisCard
                                        size="sm"
                                        variant="default"
                                        sentiment="brand"
                                        :interactive="true"
                                        class="tap-item-card"
                                        @click="bukaLightboxModal(abs)"
                                    >
                                        <!-- Header: Waktu & Status -->
                                        <template #title>
                                            <span class="font-scale-xs fw-bold font-monospace text-dark">
                                                {{ abs.Checktime || '-' }}
                                            </span>
                                        </template>

                                        <template #badge>
                                            <HcisBadge
                                                :label="abs.Output || 'IN'"
                                                :variant="abs.Output === 'OUT' ? 'warning' : 'success'"
                                                appearance="subtle"
                                                size="2xs"
                                            />
                                        </template>

                                        <template #description>
                                            <span v-if="abs.IP_Address" class="font-monospace text-muted font-scale-2xs">
                                                IP: {{ abs.IP_Address }}
                                            </span>
                                        </template>

                                        <!-- Body: Foto & Telemetri -->
                                        <div class="d-flex flex-column gap-2 mt-1">
                                            <!-- Thumbnail Frame -->
                                            <div class="tap-thumb-box">
                                                <img
                                                    v-if="abs.Foto_Url"
                                                    :src="abs.Foto_Url"
                                                    :alt="abs.Nama || abs.Kode_Karyawan"
                                                    class="tap-thumb-img"
                                                    loading="lazy"
                                                />
                                                <div v-else class="tap-thumb-empty">
                                                    <HcisIcon name="person-badge" size="md" class="text-slate-400" />
                                                    <span class="font-scale-2xs text-muted">Tanpa Foto Selfie</span>
                                                </div>
                                            </div>

                                            <!-- Telemetry Pill -->
                                            <div v-if="abs.Latitude && abs.Longitude" class="tap-telemetry-pill">
                                                <div class="tap-telemetry-pill__left">
                                                    <HcisIcon name="geo-alt-fill" size="2xs" class="text-danger flex-shrink-0" />
                                                    <span class="tap-telemetry-coord">{{ abs.Latitude }}, {{ abs.Longitude }}</span>
                                                </div>
                                                <div class="tap-telemetry-pill__right">
                                                    <HcisBadge
                                                        v-if="abs.Akurasi_Meter"
                                                        :label="`±${Math.round(abs.Akurasi_Meter)}m`"
                                                        :variant="abs.Lokasi_Meragukan ? 'warning' : 'success'"
                                                        appearance="subtle"
                                                        size="2xs"
                                                        class="font-monospace"
                                                    />
                                                    <a
                                                        :href="`https://www.google.com/maps?q=${abs.Latitude},${abs.Longitude}`"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="tap-maps-btn"
                                                        title="Buka lokasi di Google Maps"
                                                        @click.stop
                                                    >
                                                        <HcisIcon name="box-arrow-up-right" size="2xs" />
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Warning Alert -->
                                            <div v-if="abs.Lokasi_Meragukan" class="tap-alert-box warning">
                                                <HcisIcon name="exclamation-triangle-fill" size="2xs" class="flex-shrink-0" />
                                                <span>Deviasi GPS melebihi 50 meter</span>
                                            </div>
                                        </div>

                                        <!-- Footer Action Button -->
                                        <template #footer>
                                            <HcisButton
                                                variant="secondary"
                                                size="xs"
                                                leading-icon="eye"
                                                label="Verifikasi Detail"
                                                class="w-100"
                                                @click.stop="bukaLightboxModal(abs)"
                                            />
                                        </template>
                                    </HcisCard>
                                </div>
                            </div>
                        </div>
                    </HcisCollapseItem>
                </HcisCollapse>

                <!-- Footer Pagination -->
                <div
                    v-if="filteredGroupedAbsensi.length > 0"
                    class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 px-3 pb-2 bg-white rounded-3 border"
                >
                    <span class="text-muted small">
                        Menampilkan <strong>{{ paginationInfo.start }}–{{ paginationInfo.end }}</strong> dari <strong>{{ filteredGroupedAbsensi.length }}</strong> karyawan
                    </span>
                    <HcisPagination
                        :total="filteredGroupedAbsensi.length"
                        :page-size="pageSize"
                        :current-page="currentPage"
                        :page-sizes="[5, 10, 20, 50]"
                        layout="sizes, prev, pager, next"
                        background
                        size="sm"
                        @update:current-page="currentPage = $event"
                        @update:page-size="pageSize = $event"
                    />
                </div>
            </div>
        </div>

        <!-- ══ 4. MODAL LIGHTBOX FOTO PRESENSI ══ -->
        <HcisDialog
            :model-value="fotoDialog.show"
            :title="`Bukti Presensi · ${fotoDialog.item?.Nama || fotoDialog.item?.Kode_Karyawan || 'Karyawan'}`"
            description="Verifikasi bukti kehadiran foto selfie dan data telemetri lokasi"
            kicker="PRESENSI & ABSENSI"
            icon="camera-fill"
            sentiment="brand"
            variant="glass"
            size="lg"
            @update:model-value="fotoDialog.show = $event"
        >
            <div v-if="fotoDialog.item" class="row g-3 align-items-stretch">
                <!-- Sisi Kiri: Foto Selfie -->
                <div class="col-12 col-md-6 text-center">
                    <div class="tap-lightbox-img-wrapper">
                        <img
                            v-if="fotoDialog.item.Foto_Url"
                            :src="fotoDialog.item.Foto_Url"
                            :alt="fotoDialog.item.Nama"
                            class="img-fluid rounded-3 tap-lightbox-img"
                        />
                        <div v-else class="tap-lightbox-empty-img">
                            <HcisIcon name="person-badge" size="xl" class="opacity-40 mb-2" />
                            <div class="font-scale-sm text-muted">Tidak ada lampiran foto selfie untuk presensi ini.</div>
                        </div>
                    </div>
                </div>

                <!-- Sisi Kanan: Metadata & Telemetri -->
                <div class="col-12 col-md-6 d-flex flex-column gap-2">
                    <HcisCard size="sm" variant="flat" sentiment="brand" class="mb-0 flex-grow-1">
                        <div class="font-scale-2xs fw-bold text-uppercase text-brand tracking-wider mb-2">Informasi Karyawan</div>
                        <div class="d-flex align-items-center gap-2.5 mb-2">
                            <HcisAvatar
                                :name="fotoDialog.item.Nama || fotoDialog.item.Kode_Karyawan"
                                size="sm"
                                shape="squircle"
                                variant="brand"
                            />
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate">{{ fotoDialog.item.Nama || fotoDialog.item.Kode_Karyawan }}</div>
                                <div class="font-scale-2xs text-muted font-monospace">{{ fotoDialog.item.No_Induk_Karyawan || fotoDialog.item.Kode_Karyawan }}</div>
                            </div>
                        </div>
                        <div v-if="fotoDialog.item.Departemen || fotoDialog.item.Divisi" class="font-scale-xs text-muted mb-1">
                            <strong>Unit:</strong> {{ [fotoDialog.item.Departemen, fotoDialog.item.Divisi].filter(Boolean).join(' · ') }}
                        </div>
                        <div v-if="fotoDialog.item.Jabatan" class="font-scale-xs text-muted">
                            <strong>Jabatan:</strong> {{ fotoDialog.item.Jabatan }}
                        </div>
                    </HcisCard>

                    <HcisCard size="sm" variant="flat" sentiment="neutral" class="mb-0">
                        <div class="font-scale-2xs fw-bold text-uppercase text-secondary tracking-wider mb-2">Telemetri Presensi</div>
                        <div class="d-flex flex-column gap-1.5 font-scale-xs">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Waktu Check-in:</span>
                                <strong class="font-monospace text-dark">{{ fotoDialog.item.Checktime || '-' }}</strong>
                            </div>
                            <div v-if="fotoDialog.item.Output" class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Status Output:</span>
                                <HcisBadge :label="fotoDialog.item.Output" variant="success" appearance="subtle" size="2xs" />
                            </div>
                            <div v-if="fotoDialog.item.Latitude && fotoDialog.item.Longitude" class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Koordinat GPS:</span>
                                <span class="font-monospace fw-semibold">{{ fotoDialog.item.Latitude }}, {{ fotoDialog.item.Longitude }}</span>
                            </div>
                            <div v-if="fotoDialog.item.Akurasi_Meter" class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Akurasi GPS:</span>
                                <HcisBadge
                                    :label="`±${Math.round(fotoDialog.item.Akurasi_Meter)} meter`"
                                    :variant="fotoDialog.item.Lokasi_Meragukan ? 'warning' : 'success'"
                                    appearance="subtle"
                                    size="2xs"
                                />
                            </div>
                            <div v-if="fotoDialog.item.IP_Address" class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Alamat IP:</span>
                                <span class="font-monospace text-muted">{{ fotoDialog.item.IP_Address }}</span>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-between w-100 gap-2 flex-wrap">
                    <a
                        v-if="fotoDialog.item?.Latitude && fotoDialog.item?.Longitude"
                        :href="`https://www.google.com/maps?q=${fotoDialog.item.Latitude},${fotoDialog.item.Longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-sm btn-outline-primary"
                    >
                        <i class="bi bi-geo-alt-fill me-1 text-danger"></i>
                        Buka di Google Maps
                    </a>
                    <div v-else></div>
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Tutup"
                        @click="fotoDialog.show = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import {
    HcisButton,
    HcisBadge,
    HcisFilterBar,
    HcisPagination,
    HcisCard,
    HcisIcon,
    HcisAvatar,
    HcisDialog,
    HcisEmptyState,
    HcisStatGroup,
    HcisCollapse,
    HcisCollapseItem,
} from '@/components/ui';

export default {
    name: 'TabAbsensiPaket',
    components: {
        HcisButton,
        HcisBadge,
        HcisFilterBar,
        HcisPagination,
        HcisCard,
        HcisIcon,
        HcisAvatar,
        HcisDialog,
        HcisEmptyState,
        HcisStatGroup,
        HcisCollapse,
        HcisCollapseItem,
    },
    props: {
        absensiList: {
            type: Array,
            default: () => [],
        },
        paket: {
            type: Object,
            default: () => ({}),
        },
        idPaket: {
            type: String,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            currentPage: 1,
            pageSize: 10,
            openAccordionKeys: [],
            fotoDialog: {
                show: false,
                item: null,
            },
            filterState: {
                search: '',
                jenis: 'all',
                departemen: [],
                status_gps: 'all',
                sort_by: 'terbaru',
            },
        };
    },
    computed: {
        statAbsensi() {
            return [
                {
                    label: 'Total Presensi',
                    value: this.totalCatatanPresensi,
                    suffix: 'Catatan',
                    sentiment: 'brand',
                    icon: 'camera-fill',
                    caption: 'Log check-in kehadiran',
                },
                {
                    label: 'Karyawan Terdata',
                    value: this.groupedAbsensi.length,
                    suffix: 'Karyawan',
                    sentiment: 'info',
                    icon: 'people-fill',
                    caption: 'Melakukan presensi',
                },
                {
                    label: 'Foto Selfie Valid',
                    value: this.totalFotoSelfie,
                    suffix: 'Foto',
                    sentiment: 'success',
                    icon: 'patch-check-fill',
                    caption: 'Bukti selfie terverifikasi',
                },
                {
                    label: 'GPS Akurasi Rendah',
                    value: this.totalLokasiMeragukan,
                    suffix: 'Log',
                    sentiment: this.totalLokasiMeragukan > 0 ? 'warning' : 'neutral',
                    icon: this.totalLokasiMeragukan > 0 ? 'exclamation-triangle-fill' : 'geo-alt-fill',
                    caption: this.totalLokasiMeragukan > 0 ? 'Deviasi GPS > 50m' : 'Semua lokasi akurat',
                },
            ];
        },
        totalCatatanPresensi() {
            return (this.absensiList || []).length;
        },
        totalFotoSelfie() {
            return (this.absensiList || []).filter((a) => Boolean(a.Foto_Url)).length;
        },
        totalLokasiMeragukan() {
            return (this.absensiList || []).filter((a) => Boolean(a.Lokasi_Meragukan)).length;
        },
        daftarDepartemenUnik() {
            const list = this.absensiList || [];
            const set = new Set(list.map((a) => a.Departemen || a.Divisi).filter(Boolean));
            return Array.from(set);
        },
        groupedAbsensi() {
            const list = this.absensiList || [];
            const map = new Map();

            list.forEach((a) => {
                const kode = a.Kode_Karyawan || 'UNKNOWN';
                if (!map.has(kode)) {
                    map.set(kode, {
                        Kode_Karyawan: kode,
                        Nama: a.Nama || kode,
                        No_Induk_Karyawan: a.No_Induk_Karyawan || '',
                        Departemen: a.Departemen || '',
                        Divisi: a.Divisi || '',
                        Jabatan: a.Jabatan || '',
                        Lokasi: a.Lokasi || '',
                        total_presensi: 0,
                        terakhir_checktime: a.Checktime,
                        ada_foto: false,
                        ada_lokasi_meragukan: false,
                        riwayat: [],
                    });
                }
                const grp = map.get(kode);
                grp.total_presensi += 1;
                if (a.Foto_Url) grp.ada_foto = true;
                if (a.Lokasi_Meragukan) grp.ada_lokasi_meragukan = true;
                grp.riwayat.push(a);
            });

            return Array.from(map.values());
        },
        quickChipsConfig() {
            const list = this.groupedAbsensi;
            return [
                { label: 'Semua Karyawan', value: 'all', badge: list.length, icon: 'people-fill' },
                { label: 'Ada Foto Selfie', value: 'foto', badge: list.filter((g) => g.ada_foto).length, color: 'success', icon: 'camera-fill' },
                { label: 'GPS Akurasi Rendah', value: 'meragukan', badge: list.filter((g) => g.ada_lokasi_meragukan).length, color: 'warning', icon: 'exclamation-triangle-fill' },
            ];
        },
        filterBarConfig() {
            return [
                {
                    key: 'departemen',
                    label: 'Departemen / Unit',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Departemen / Unit',
                    icon: 'building',
                    col: 4,
                    options: this.daftarDepartemenUnik.map((d) => ({ label: d, value: d })),
                },
                {
                    key: 'status_gps',
                    label: 'Status Akurasi GPS',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Status GPS',
                    icon: 'geo-alt',
                    col: 4,
                    options: [
                        { label: 'Semua Status GPS', value: 'all' },
                        { label: 'Akurat Sempurna (≤ 50m)', value: 'akurat' },
                        { label: 'Akurasi Rendah (> 50m)', value: 'rendah' },
                    ],
                },
                {
                    key: 'sort_by',
                    label: 'Urutan Tampilan',
                    type: 'select',
                    clearable: false,
                    placeholder: 'Urutan Tampilan',
                    icon: 'sort-down',
                    col: 4,
                    options: [
                        { label: 'Presensi Terbaru', value: 'terbaru' },
                        { label: 'Nama Karyawan (A - Z)', value: 'nama_asc' },
                        { label: 'Presensi Terbanyak', value: 'presensi_desc' },
                    ],
                },
            ];
        },
        filteredGroupedAbsensi() {
            let list = [...this.groupedAbsensi];

            // 1. Search filter
            if (this.filterState.search) {
                const q = this.filterState.search.toLowerCase().trim();
                list = list.filter(
                    (g) =>
                        (g.Nama || '').toLowerCase().includes(q) ||
                        (g.Kode_Karyawan || '').toLowerCase().includes(q) ||
                        (g.No_Induk_Karyawan || '').toLowerCase().includes(q) ||
                        (g.Departemen || '').toLowerCase().includes(q) ||
                        (g.Divisi || '').toLowerCase().includes(q) ||
                        (g.Jabatan || '').toLowerCase().includes(q) ||
                        (g.Lokasi || '').toLowerCase().includes(q),
                );
            }

            // 2. Chip jenis filter
            if (this.filterState.jenis === 'foto') {
                list = list.filter((g) => g.ada_foto);
            } else if (this.filterState.jenis === 'meragukan') {
                list = list.filter((g) => g.ada_lokasi_meragukan);
            }

            // 3. Departemen filter
            if (Array.isArray(this.filterState.departemen) && this.filterState.departemen.length > 0) {
                list = list.filter((g) => {
                    const unit = g.Departemen || g.Divisi || '';
                    return this.filterState.departemen.includes(unit);
                });
            }

            // 4. Status GPS filter
            if (this.filterState.status_gps === 'akurat') {
                list = list.filter((g) => !g.ada_lokasi_meragukan);
            } else if (this.filterState.status_gps === 'rendah') {
                list = list.filter((g) => g.ada_lokasi_meragukan);
            }

            // 5. Sorting
            const sortBy = this.filterState.sort_by;
            if (sortBy === 'nama_asc') {
                list.sort((a, b) => (a.Nama || '').localeCompare(b.Nama || ''));
            } else if (sortBy === 'presensi_desc') {
                list.sort((a, b) => (b.total_presensi || 0) - (a.total_presensi || 0));
            }

            return list;
        },
        paginatedGroupedAbsensi() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredGroupedAbsensi.slice(start, start + this.pageSize);
        },
        paginationInfo() {
            const start = (this.currentPage - 1) * this.pageSize + 1;
            const end = Math.min(this.currentPage * this.pageSize, this.filteredGroupedAbsensi.length);
            return { start, end };
        },
    },
    watch: {
        'filterState.search'() {
            this.currentPage = 1;
        },
        'filterState.jenis'() {
            this.currentPage = 1;
        },
    },
    methods: {
        isOpen(kode) {
            return this.openAccordionKeys.includes(kode);
        },
        toggleAccordion(kode) {
            if (this.isOpen(kode)) {
                this.openAccordionKeys = this.openAccordionKeys.filter((k) => k !== kode);
            } else {
                this.openAccordionKeys.push(kode);
            }
        },
        bukaSemuaAccordion() {
            this.openAccordionKeys = this.filteredGroupedAbsensi.map((g) => g.Kode_Karyawan);
        },
        tutupSemuaAccordion() {
            this.openAccordionKeys = [];
        },
        bukaLightboxModal(abs) {
            this.fotoDialog = {
                show: true,
                item: abs,
            };
        },
        onSearchChange(val) {
            this.filterState.search = val;
            this.currentPage = 1;
        },
        onChipClick(chip) {
            this.filterState.jenis = chip.value || chip.id || 'all';
            this.currentPage = 1;
        },
        onFilterChange(payload) {
            if (payload && payload.key) {
                this.filterState[payload.key] = payload.value;
            } else if (payload && payload.values) {
                this.filterState = { ...this.filterState, ...payload.values };
            } else if (typeof payload === 'object') {
                this.filterState = { ...this.filterState, ...payload };
            }
            this.currentPage = 1;
        },
        resetFilters() {
            this.filterState = {
                search: '',
                jenis: 'all',
                departemen: [],
                status_gps: 'all',
                sort_by: 'terbaru',
            };
            this.currentPage = 1;
        },
        exportCsv() {
            const list = this.absensiList || [];
            if (!list.length) return;

            const headers = ['Kode Karyawan', 'Nama', 'NIK', 'Departemen', 'Jabatan', 'Waktu Checktime', 'Output', 'Latitude', 'Longitude', 'Akurasi (m)', 'IP Address'];
            const rows = list.map((a) => [
                `"${a.Kode_Karyawan || ''}"`,
                `"${a.Nama || ''}"`,
                `"${a.No_Induk_Karyawan || ''}"`,
                `"${a.Departemen || a.Divisi || ''}"`,
                `"${a.Jabatan || ''}"`,
                `"${a.Checktime || ''}"`,
                `"${a.Output || ''}"`,
                `"${a.Latitude || ''}"`,
                `"${a.Longitude || ''}"`,
                `"${a.Akurasi_Meter || ''}"`,
                `"${a.IP_Address || ''}"`,
            ]);

            const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `Presensi_SOP_${this.idPaket}_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
    },
};
</script>

<style scoped>
.tap-root {
    width: 100%;
}

/* ══ Inner Accordion Content ══ */
.tap-collapse-body {
    padding-top: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

:deep(.tap-context-card) {
    margin-bottom: 0 !important;
}

:deep(.tap-context-card .hcis-card__header) {
    padding: 0.6rem 0.85rem !important;
}

:deep(.tap-item-card) {
    display: flex;
    flex-direction: column;
    height: 100%;
}

:deep(.tap-item-card .hcis-card__header) {
    padding: 0.65rem 0.85rem 0.4rem !important;
}

:deep(.tap-item-card .hcis-card__body) {
    padding: 0.75rem 0.85rem !important;
    flex-grow: 1 !important;
}

:deep(.tap-item-card .hcis-card__footer) {
    padding: 0.6rem 0.85rem !important;
    background: var(--hcis-surface-soft, var(--hcis-slate-50)) !important;
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100)) !important;
}

/* ══ Thumbnail Frame ══ */
.tap-thumb-box {
    position: relative;
    width: 100%;
    height: 135px;
    background-color: var(--hcis-surface-soft, var(--hcis-slate-100));
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tap-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

:deep(.tap-item-card:hover) .tap-thumb-img {
    transform: scale(1.05);
}

.tap-thumb-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    font-size: 0.725rem;
    font-weight: 500;
}

/* ══ Telemetry Pill ══ */
.tap-telemetry-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.35rem 0.55rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    border-radius: var(--hcis-radius-md, 6px);
    gap: 0.5rem;
}

.tap-telemetry-pill__left {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.tap-telemetry-coord {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.725rem;
    color: var(--hcis-text-strong, var(--hcis-slate-700));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tap-telemetry-pill__right {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.tap-maps-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    background: var(--hcis-border-subtle, var(--hcis-slate-200));
    color: var(--hcis-text-muted, var(--hcis-slate-600));
    text-decoration: none;
    transition: all 0.15s ease;
}

.tap-maps-btn:hover {
    background: var(--hcis-brand-500, var(--hcis-blue-600));
    color: var(--hcis-white);
}

.tap-alert-box {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.55rem;
    border-radius: var(--hcis-radius-md, 6px);
    font-size: 0.7rem;
    font-weight: 500;
}

.tap-alert-box.warning {
    background: var(--hcis-warning-50, var(--hcis-amber-50));
    border: 1px solid var(--hcis-warning-200, var(--hcis-amber-100));
    color: var(--hcis-warning-700, var(--hcis-amber-700));
}

/* ══ Lightbox Modal ══ */
.tap-lightbox-img-wrapper {
    background: var(--hcis-slate-900);
    border-radius: 12px;
    padding: 0.5rem;
    min-height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tap-lightbox-img {
    max-height: 380px;
    object-fit: contain;
}

.tap-lightbox-empty-img {
    color: var(--hcis-slate-400);
    padding: 3rem 1rem;
    text-align: center;
}

.tracking-wider {
    letter-spacing: 0.04em;
}
</style>
