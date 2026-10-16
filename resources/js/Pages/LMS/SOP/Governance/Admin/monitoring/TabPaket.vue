<template>
    <div class="sop-stack">
        <!-- ══ 1. KARTU STATISTIK RINGKAS INTERAKTIF (HCIS Design System) ══ -->
        <div class="sop-fade-up tp-stats-box">
            <HcisStatGroup :stats="hcisStats" :cols="4" gap="md" appearance="crystal" />
        </div>

        <!-- ══ 2. DAFTAR PAKET DOKUMEN & PENYELESAIAN ══ -->
        <div class="sop-fade-up">
            <!-- Filter Bar Modern HCIS -->
            <div class="tp-filter-box">
                <HcisFilterBar
                    :search="filterState.search"
                    :filters="filterBarConfig"
                    :filter-values="filterState"
                    :quick-chips="quickChipsConfig"
                    :active-chip-value="filterState.status"
                    :results-count="pagination.total"
                    search-placeholder="Cari nama paket dokumen..."
                    variant="glass"
                    sentiment="brand"
                    collapsible
                    @update:search="onSearchChange"
                    @filter-change="onFilterChange"
                    @chip-click="onChipClick"
                    @reset="onResetFilters"
                />
            </div>

            <!-- Tabel Monitoring Paket Dokumen HCIS -->
            <div class="tp-table-card">
                <HcisTable
                    :columns="columns"
                    :rows="rows"
                    :loading="loading.cov"
                    interactive
                    empty-title="Belum Ada Penugasan Paket"
                    empty-description="Belum ada distribusi paket dokumen. Assign paket terlebih dahulu di menu Penjadwalan."
                    row-key="Id_SOP_Paket"
                    class="tp-hcis-table"
                    @row-click="openDrill"
                >
                    <!-- Column: Nama Paket -->
                    <template #cell-Nama_Paket="{ row }">
                        <div class="py-1">
                            <div class="fw-bold text-dark font-scale-sm text-truncate" :title="row.Nama_Paket">
                                {{ row.Nama_Paket }}
                            </div>
                            <div class="d-flex align-items-center mt-1 flex-wrap">
                                <span v-if="row.Jumlah_Item" class="small text-muted font-scale-xs">
                                    <HcisIcon name="file-earmark-text" size="2xs" class="me-1 opacity-70" />
                                    {{ row.Jumlah_Item }} Dokumen
                                </span>
                            </div>
                        </div>
                    </template>

                    <!-- Column: Target Karyawan -->
                    <template #cell-Total_Target="{ row }">
                        <div class="text-center">
                            <span class="tp-target-count">{{ row.Total_Target }}</span>
                            <span class="small text-muted d-block font-scale-xs">Karyawan</span>
                        </div>
                    </template>

                    <!-- Column: Selesai -->
                    <template #cell-Selesai="{ row }">
                        <div class="text-center">
                            <span class="tp-selesai-pill">
                                <HcisIcon name="check-circle-fill" size="2xs" class="me-1" />
                                {{ row.Selesai }}
                            </span>
                        </div>
                    </template>

                    <!-- Column: Terlambat -->
                    <template #cell-Terlambat="{ row }">
                        <div class="text-center">
                            <span v-if="row.Terlambat > 0" class="tp-late-pill">
                                <HcisIcon name="exclamation-triangle-fill" size="2xs" class="me-1" />
                                {{ row.Terlambat }} telat
                            </span>
                            <span v-else class="tp-zero-pill">
                                <HcisIcon name="check2" size="2xs" class="me-1" /> 0
                            </span>
                        </div>
                    </template>

                    <!-- Column: Persentase Penyelesaian -->
                    <template #cell-Persen="{ row }">
                        <div class="tp-persen-col">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span
                                    class="tp-pct-text"
                                    :class="row.Persen >= 80 ? 'is-emerald' : row.Persen >= 40 ? 'is-amber' : 'is-danger'"
                                >
                                    {{ row.Persen }}%
                                </span>
                                <span v-if="row.Total_Target" class="tp-pct-sub">
                                    {{ row.Selesai }}/{{ row.Total_Target }} target
                                </span>
                            </div>
                            <div class="tp-meter">
                                <div
                                    class="tp-meter-bar"
                                    :class="meterFillClass(row.Persen)"
                                    :style="{ width: `${Math.min(row.Persen || 0, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <!-- Column: Aksi Drilldown -->
                    <template #cell-Aksi="{ row }">
                        <div class="text-end">
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                trailing-icon="chevron-right"
                                label="Rincian"
                                @click.stop="openDrill(row)"
                            />
                        </div>
                    </template>

                    <!-- Custom Footer Pagination -->
                    <template #footer>
                        <div class="d-flex justify-content-between align-items-center flex-wrap py-2 px-3 border-top border-slate-100">
                            <div class="small text-muted font-scale-xs py-1">
                                Menampilkan
                                <strong class="text-dark">{{ rows.length ? (pagination.page - 1) * pagination.per_page + 1 : 0 }}</strong>
                                -
                                <strong class="text-dark">{{ Math.min(pagination.page * pagination.per_page, pagination.total) }}</strong>
                                dari <strong class="text-dark">{{ pagination.total }}</strong> paket
                            </div>
                            <HcisPagination
                                :current-page="pagination.page"
                                :page-size="pagination.per_page"
                                :total="pagination.total"
                                :page-sizes="[10, 25, 50, 100]"
                                size="sm"
                                @update:current-page="onPageChange"
                                @update:page-size="onPageSizeChange"
                                @page-change="onPageChange"
                                @size-change="onPageSizeChange"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>
        </div>

        <!-- ══ 3. KUIS TERTAHAN PERLU TINDAKAN (HCIS Design System) ══ -->
        <div v-if="kuis.rows.length || kuis.loading" class="sop-fade-up mt-4">
            <HcisCard
                variant="glass"
                sentiment="warning"
                size="md"
                icon="question-diamond-fill"
                title="Kuis Tertahan"
                description="Jatah percobaan sudah terpakai tapi belum lulus. Kembalikan jatahnya agar karyawan bisa mengerjakan ulang."
                badge="Perlu Tindakan"
                badge-variant="warning"
            >
                <template #actions>
                    <span class="tp-count-badge warning">{{ kuis.rows.length }} orang</span>
                </template>

                <div v-if="kuis.loading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <div class="small text-muted mt-2">Memuat antrean kuis tertahan…</div>
                </div>
                <div v-else class="tp-table-wrapper">
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Dokumen / Item</th>
                                <th class="text-center">Nilai</th>
                                <th class="text-center">Percobaan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="k in kuis.rows" :key="k.Id_SOP_Progress" class="tp-table-row">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="tp-mini-avatar-box me-2.5">
                                            <HcisAvatar :name="k.Nama || k.Kode_Karyawan" size="xs" shape="squircle" />
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark font-scale-sm">{{ k.Nama || k.Kode_Karyawan }}</div>
                                            <div class="small text-muted font-scale-xs">{{ k.Kode_Karyawan }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark font-scale-sm">{{ k.Judul_Item }}</div>
                                    <div class="small text-muted font-scale-xs">{{ k.Nama_Paket }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-extrabold font-monospace text-danger font-scale-sm">
                                        {{ k.Skor_Quiz ?? '—' }}
                                    </span>
                                    <div v-if="k.Nilai_Minimum != null" class="small text-muted font-scale-xs">
                                        min {{ k.Nilai_Minimum }}
                                    </div>
                                </td>
                                <td class="text-center font-monospace font-scale-sm fw-bold">
                                    {{ k.Jumlah_Percobaan }} / {{ k.Maks_Percobaan }}
                                </td>
                                <td class="text-end">
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        leading-icon="arrow-counterclockwise"
                                        label="Buka ulang"
                                        :loading="kuis.acting === k.Id_SOP_Progress"
                                        :disabled="kuis.acting === k.Id_SOP_Progress"
                                        @click="resetKuis(k)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </HcisCard>
        </div>

        <!-- ══ 4. EXECUTIVE DRILLDOWN DRAWER FOR DOCUMENT PACKAGE ══ -->
        <HcisDrawer
            :model-value="drill.show"
            size="xl"
            sentiment="primary"
            direction="rtl"
            :show-close="false"
            @update:model-value="(v) => { if (!v) drill.show = false; }"
        >
            <!-- Slim Executive Header -->
            <template #header>
                <div class="d-flex align-items-center justify-content-between w-100 py-1">
                    <div class="d-flex align-items-center min-w-0 flex-grow-1 pe-3">
                        <div class="tp-drawer-icon-box me-3">
                            <HcisIcon name="collection-fill" size="md" class="text-indigo-600" />
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap">
                                <span class="tp-drawer-title me-2.5 text-truncate" :title="drill.nama">
                                    {{ drill.nama }}
                                </span>
                                <HcisBadge
                                    v-if="drill.prioritas"
                                    :label="drill.prioritas"
                                    :variant="drill.prioritas === 'KRITIS' ? 'danger' : drill.prioritas === 'TINGGI' ? 'warning' : 'neutral'"
                                    size="xs"
                                    class="me-1.5"
                                />
                            </div>
                            <div class="tp-drawer-sub-meta mt-1">
                                <span class="fw-semibold text-slate-500">
                                    <HcisIcon name="people-fill" size="2xs" class="me-1 text-indigo-500" />
                                    {{ drill.karyawan.length }} Karyawan Ditugaskan
                                </span>
                                <span v-if="drill.funnel" class="mx-2 text-slate-300">•</span>
                                <span v-if="drill.funnel" class="fw-bold text-emerald-600">
                                    <HcisIcon name="check-circle-fill" size="2xs" class="me-1" />
                                    {{ drill.funnel.persen_konfirmasi }}% Kepatuhan
                                </span>
                            </div>
                        </div>
                    </div>

                    <HcisButton
                        variant="ghost"
                        size="sm"
                        icon-only
                        leading-icon="x-lg"
                        aria-label="Tutup"
                        class="tp-close-btn flex-shrink-0"
                        @click="drill.show = false"
                    />
                </div>
            </template>

            <!-- Drawer Body: Spacious Scrollable Funnel & Employee Recipients -->
            <div class="tp-drawer-body-wrap">
                <div v-if="loading.drill" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2">Memuat rincian paket...</div>
                </div>

                <template v-else>
                    <!-- 1. Funnel Milestone Dashboard (Scrolls with Body) -->
                    <div v-if="drill.funnel" class="tp-funnel-dashboard-box mb-4">
                        <div class="tp-funnel-milestones-grid">
                            <!-- Milestone 1: Ditugaskan -->
                            <div class="tp-milestone-cell">
                                <div class="tp-ms-head">
                                    <span class="tp-ms-icon is-primary">
                                        <HcisIcon name="people-fill" size="2xs" />
                                    </span>
                                    <span class="tp-ms-label">Ditugaskan</span>
                                </div>
                                <div class="tp-ms-val">{{ drill.funnel.target }} <small class="text-muted font-scale-xs">Org</small></div>
                            </div>

                            <!-- Milestone 2: Sudah Dibuka -->
                            <div class="tp-milestone-cell">
                                <div class="tp-ms-head">
                                    <span class="tp-ms-icon is-indigo">
                                        <HcisIcon name="book-half" size="2xs" />
                                    </span>
                                    <span class="tp-ms-label">Sudah Dibuka</span>
                                </div>
                                <div class="tp-ms-val text-indigo-600">
                                    {{ drill.funnel.dibuka }}
                                    <span class="tp-ms-badge is-indigo">{{ drill.funnel.persen_dibuka }}%</span>
                                </div>
                            </div>

                            <!-- Milestone 3: Selesai Konfirmasi -->
                            <div class="tp-milestone-cell">
                                <div class="tp-ms-head">
                                    <span class="tp-ms-icon is-emerald">
                                        <HcisIcon name="check-circle-fill" size="2xs" />
                                    </span>
                                    <span class="tp-ms-label">Dikonfirmasi</span>
                                </div>
                                <div class="tp-ms-val text-emerald-600">
                                    {{ drill.funnel.dikonfirmasi }}
                                    <span class="tp-ms-badge is-emerald">{{ drill.funnel.persen_konfirmasi }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3-Segment Conversion Meter -->
                        <div class="tp-conversion-meter-wrap">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="small fw-semibold text-secondary">Rasio Konversi Kepatuhan</span>
                                <span class="small fw-bold text-dark">{{ drill.funnel.persen_konfirmasi }}% Selesai Penuh</span>
                            </div>
                            <div class="tp-conversion-track">
                                <div
                                    class="tp-conv-fill is-confirmed"
                                    :style="{ width: `${drill.funnel.persen_konfirmasi || 0}%` }"
                                    title="Dikonfirmasi Selesai"
                                ></div>
                                <div
                                    class="tp-conv-fill is-opened"
                                    :style="{ width: `${Math.max(0, (drill.funnel.persen_dibuka || 0) - (drill.funnel.persen_konfirmasi || 0))}%` }"
                                    title="Sudah Dibuka (Belum Konfirmasi)"
                                ></div>
                            </div>
                        </div>

                        <!-- Alert Callout if Opened but not Confirmed -->
                        <div v-if="drill.funnel.dibuka_belum_konf > 0" class="tp-funnel-alert">
                            <HcisIcon name="exclamation-triangle-fill" size="xs" class="text-amber-600 me-2 flex-shrink-0" />
                            <span>
                                <strong>{{ drill.funnel.dibuka_belum_konf }} karyawan</strong> telah membuka dokumen tetapi belum menyelesaikan konfirmasi.
                            </span>
                        </div>
                    </div>

                    <!-- 2. Sticky Sub-Filter Box -->
                    <div v-if="drill.karyawan.length > 0" class="tp-drawer-sticky-filter">
                        <div class="tp-filter-search-row mb-2">
                            <HcisField
                                v-model="drillSearchKaryawan"
                                type="search"
                                placeholder="Cari nama, NIK, atau kode..."
                                clearable
                                size="sm"
                                class="w-100"
                            >
                                <template #prefix>
                                    <HcisIcon name="search" size="xs" class="text-muted" />
                                </template>
                            </HcisField>
                        </div>

                        <!-- Filter Multi-Dimensi: Divisi, Jabatan, Level, Lokasi -->
                        <div class="row g-2 mb-2">
                            <div v-if="opsiDivisiDrill.length > 1" class="col-6">
                                <select v-model="drillFilterDivisi" class="form-select form-select-sm rounded-pill text-truncate">
                                    <option value="">Semua Divisi / Unit</option>
                                    <option v-for="d in opsiDivisiDrill" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <div v-if="opsiJabatanDrill.length > 1" class="col-6">
                                <select v-model="drillFilterJabatan" class="form-select form-select-sm rounded-pill text-truncate">
                                    <option value="">Semua Jabatan</option>
                                    <option v-for="j in opsiJabatanDrill" :key="j" :value="j">{{ j }}</option>
                                </select>
                            </div>
                            <div v-if="opsiLevelDrill.length > 1" class="col-6">
                                <select v-model="drillFilterLevel" class="form-select form-select-sm rounded-pill text-truncate">
                                    <option value="">Semua Level</option>
                                    <option v-for="l in opsiLevelDrill" :key="l" :value="l">{{ l }}</option>
                                </select>
                            </div>
                            <div v-if="opsiLokasiDrill.length > 1" class="col-6">
                                <select v-model="drillFilterLokasi" class="form-select form-select-sm rounded-pill text-truncate">
                                    <option value="">Semua Lokasi</option>
                                    <option v-for="loc in opsiLokasiDrill" :key="loc" :value="loc">{{ loc }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="tp-chip-list mb-2">
                            <button
                                v-for="st in drillStatusChips"
                                :key="st.value"
                                type="button"
                                class="tp-mini-chip"
                                :class="{ 'is-active': drillFilterKaryawan === st.value }"
                                @click="drillFilterKaryawan = st.value"
                            >
                                <span>{{ st.label }}</span>
                                <span v-if="st.count !== undefined" class="tp-mini-chip-badge">{{ st.count }}</span>
                            </button>
                        </div>

                        <!-- Bulk Selection Toolbar -->
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <div class="d-flex align-items-center mb-0">
                                <HcisCheckbox
                                    :model-value="isAllDrillSelected"
                                    :label="`Pilih Semua (${filteredKaryawanDrill.length})`"
                                    size="sm"
                                    @update:model-value="toggleSelectAllDrill"
                                />
                            </div>
                            <div v-if="selectedKaryawanDrill.length > 0" class="d-flex align-items-center gap-2">
                                <HcisBadge
                                    :label="`${selectedKaryawanDrill.length} Dipilih`"
                                    variant="brand"
                                    size="xs"
                                />
                                <HcisButton
                                    variant="danger"
                                    size="xs"
                                    leading-icon="trash3-fill"
                                    :label="`Cabut (${selectedKaryawanDrill.length})`"
                                    @click="cabutBulk"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <HcisEmptyState
                        v-if="!filteredKaryawanDrill.length"
                        :title="drill.karyawan.length ? 'Tidak Ada Karyawan yang Cocok' : 'Belum Ada Karyawan Ditugaskan'"
                        :description="drill.karyawan.length ? 'Silakan sesuaikan kata kunci pencarian atau status filter.' : 'Paket ini belum didistribusikan ke karyawan.'"
                        :icon="drill.karyawan.length ? 'search' : 'person-x'"
                        size="sm"
                        sentiment="neutral"
                        class="my-4"
                    />

                    <!-- 3. List of Roomy Employee Cards -->
                    <div v-else class="tp-emp-cards-list">
                        <div
                            v-for="k in filteredKaryawanDrill"
                            :key="k.Kode_Karyawan"
                            class="tp-emp-item-card"
                            :class="{ 'is-selected': selectedKaryawanDrill.includes(k.Kode_Karyawan) }"
                        >
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center min-w-0 flex-grow-1 pe-3">
                                    <div class="me-2.5 mb-0 flex-shrink-0">
                                        <HcisCheckbox
                                            :model-value="selectedKaryawanDrill.includes(k.Kode_Karyawan)"
                                            size="sm"
                                            @update:model-value="toggleSelectKaryawanDrill(k.Kode_Karyawan)"
                                        />
                                    </div>
                                    <div class="tp-avatar-box me-3 flex-shrink-0">
                                        <HcisAvatar
                                            :name="k.Nama || k.Kode_Karyawan"
                                            size="md"
                                            shape="squircle"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="tp-emp-card-name" :title="k.Nama || k.Kode_Karyawan">
                                            {{ k.Nama || k.Kode_Karyawan }}
                                        </div>
                                        <div class="d-flex align-items-center mt-1 flex-wrap gap-1">
                                            <span class="tp-emp-nik-badge">{{ k.No_Induk_Karyawan || k.Kode_Karyawan }}</span>
                                            <HcisBadge
                                                :label="statusLabel(k.Status_Penugasan || k.Status_Paket)"
                                                :variant="statusBadgeVariant(k.Status_Penugasan || k.Status_Paket)"
                                                size="xs"
                                                dot
                                            />
                                            <span v-if="k.Departemen || k.Divisi" class="small text-muted font-scale-xs ms-1 text-truncate" :title="[k.Departemen, k.Divisi].filter(Boolean).join(' · ')">
                                                · {{ [k.Departemen, k.Divisi].filter(Boolean).join(' · ') }}
                                            </span>
                                            <span v-if="k.Jabatan" class="small text-muted font-scale-xs text-truncate" :title="k.Jabatan">
                                                · {{ k.Jabatan }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center flex-shrink-0">
                                    <div class="text-end me-3 d-none d-sm-block">
                                        <div v-if="k.Tanggal_Selesai" class="small fw-bold text-success font-scale-xs">
                                            <HcisIcon name="calendar-check" size="2xs" class="me-1" />
                                            {{ k.Tanggal_Selesai }}
                                        </div>
                                        <div v-else-if="k.Is_Terlambat" class="small fw-bold text-danger font-scale-xs">
                                            <HcisIcon name="alarm" size="2xs" class="me-1" />
                                            Terlambat
                                        </div>
                                        <div v-else class="small text-muted font-scale-xs">
                                            Sedang Berjalan
                                        </div>
                                    </div>

                                    <HcisButton
                                        variant="danger"
                                        size="xs"
                                        leading-icon="person-dash"
                                        label="Cabut"
                                        @click="cabutKaryawan(k)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Drawer Footer -->
            <template #footer>
                <div class="d-flex align-items-center justify-content-between w-100">
                    <HcisButton
                        variant="secondary"
                        size="md"
                        label="Tutup"
                        @click="drill.show = false"
                    />
                    <HcisButton
                        :href="`/sop/admin/paket/${drill.idPaket}`"
                        variant="primary"
                        size="md"
                        leading-icon="box-arrow-up-right"
                        label="Buka Layar Penuh"
                    />
                </div>
            </template>
        </HcisDrawer>

        <!-- Dialog Cabut Akses Karyawan -->
        <SopCabutAksesDialog
            :show="cabut.buka"
            :sibuk="cabut.sibuk"
            :subjudul="subjudulCabut"
            @close="cabut.buka = false"
            @konfirmasi="kirimCabut"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    HcisStatGroup,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisAvatar,
    HcisTable,
    HcisPagination,
    HcisFilterBar,
    HcisDrawer,
    HcisIcon,
    HcisField,
    HcisEmptyState,
    HcisCheckbox,
} from '@/components/ui';
import SopCabutAksesDialog from '../../../Shared/components/ui/SopCabutAksesDialog.vue';
import { STATUS_PAKET } from '../../../Shared/support/constants.js';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'TabPaket',
    components: {
        HcisStatGroup,
        HcisCard,
        HcisButton,
        HcisBadge,
        HcisAvatar,
        HcisTable,
        HcisPagination,
        HcisFilterBar,
        HcisDrawer,
        HcisIcon,
        HcisField,
        HcisEmptyState,
        HcisCheckbox,
        SopCabutAksesDialog,
    },
    props: {
        filterOptions: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            summary: { coverage: 0, paket_aktif: 0, tertunda: 0, jatuh_tempo: 0, terlambat: 0 },
            cabut: { buka: false, karyawan: null, kodes: [], isBulk: false, sibuk: false },
            rows: [],
            q: '',
            filterState: {
                search: '',
                prioritas: '',
                status: 'all',
            },
            searchTimeout: null,
            loading: { cov: false, drill: false },
            pagination: { page: 1, per_page: 10, total_page: 1, total: 0 },
            drill: {
                show: false,
                nama: '',
                idPaket: null,
                prioritas: '',
                items: [],
                karyawan: [],
                sections: [],
                funnel: null,
            },
            drillSearchKaryawan: '',
            drillFilterKaryawan: 'all',
            drillFilterDivisi: '',
            drillFilterJabatan: '',
            drillFilterLevel: '',
            drillFilterLokasi: '',
            selectedKaryawanDrill: [],
            kuis: { rows: [], loading: false, acting: null },
            columns: [
                { key: 'Nama_Paket', label: 'Paket Dokumen', width: '320px' },
                { key: 'Total_Target', label: 'Target', align: 'center', width: '110px' },
                { key: 'Selesai', label: 'Selesai', align: 'center', width: '110px' },
                { key: 'Terlambat', label: 'Telat', align: 'center', width: '110px' },
                { key: 'Persen', label: 'Tingkat Kepatuhan', width: '220px' },
                { key: 'Aksi', label: '', align: 'right', width: '90px' },
            ],
        };
    },
    computed: {
        hcisStats() {
            return [
                {
                    label: 'Paket Dokumen Aktif',
                    value: Number(this.summary.paket_aktif) || 0,
                    suffix: 'Paket',
                    sentiment: 'brand',
                    icon: 'collection-fill',
                    pattern: 'grid',
                    caption: 'Paket Dokumen yang sedang aktif terdistribusi',
                    clickable: true,
                    onClick: () => this.filterByStat('all'),
                },
                {
                    label: 'Karyawan Tertunda',
                    value: Number(this.summary.tertunda) || 0,
                    suffix: 'Penugasan',
                    sentiment: 'warning',
                    icon: 'person-exclamation',
                    pattern: 'stripes',
                    caption: 'Penugasan belum dibuka atau selesai',
                    clickable: true,
                    onClick: () => this.filterByStat('progress'),
                },
                {
                    label: 'Tenggat ≤ 7 Hari',
                    value: Number(this.summary.jatuh_tempo) || 0,
                    suffix: 'Penugasan',
                    sentiment: 'warning',
                    icon: 'alarm-fill',
                    pattern: 'dots',
                    caption: 'Mendekati batas akhir pengerjaan',
                },
                {
                    label: 'Terlambat',
                    value: Number(this.summary.terlambat) || 0,
                    suffix: 'Penugasan',
                    sentiment: Number(this.summary.terlambat) > 0 ? 'danger' : 'neutral',
                    icon: 'exclamation-octagon-fill',
                    pattern: 'crosshair',
                    caption: 'Melewati batas tanggal tenggat waktu',
                    clickable: true,
                    onClick: () => this.filterByStat('terlambat'),
                },
            ];
        },
        subjudulCabut() {
            if (this.cabut.isBulk) {
                return `Mencabut akses ${this.cabut.kodes.length} karyawan terpilih dari paket "${this.drill.nama}"`;
            }
            const k = this.cabut.karyawan;
            if (!k) return '';

            return `${k.Nama || k.Kode_Karyawan} — paket "${this.drill.nama}"`;
        },
        filterBarConfig() {
            const o = this.filterOptions || {};
            const opsi = (arr) => (arr || []).map((v) => ({ label: v, value: v }));

            return [
                {
                    key: 'prioritas',
                    label: 'Tingkat Prioritas',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Prioritas',
                    icon: 'flag',
                    col: 4,
                    options: opsi(o.prioritas || ['KRITIS', 'TINGGI', 'NORMAL', 'RENDAH']),
                },
                {
                    key: 'status',
                    label: 'Status Kepatuhan',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Status',
                    icon: 'check-circle',
                    col: 4,
                    options: [
                        { label: 'Selesai 100%', value: 'selesai' },
                        { label: 'Sedang Berjalan', value: 'progress' },
                        { label: 'Ada Keterlambatan', value: 'terlambat' },
                    ],
                },
            ];
        },
        quickChipsConfig() {
            return [
                { label: 'Semua', value: 'all', icon: 'collection' },
                { label: 'Selesai 100%', value: 'selesai', color: 'success', icon: 'check-circle-fill' },
                { label: 'Sedang Berjalan', value: 'progress', color: 'warning', icon: 'hourglass-split' },
                { label: 'Terlambat', value: 'terlambat', color: 'danger', icon: 'exclamation-triangle-fill' },
            ];
        },
        drillStatusChips() {
            const list = this.drill.karyawan || [];
            return [
                { label: 'Semua', value: 'all', count: list.length },
                { label: 'Selesai', value: 'selesai', count: list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'SELESAI').length },
                { label: 'Sedang Berjalan', value: 'progress', count: list.filter((k) => (k.Status_Penugasan || '').toUpperCase() !== 'SELESAI' && !k.Is_Terlambat).length },
                { label: 'Terlambat', value: 'terlambat', count: list.filter((k) => k.Is_Terlambat).length },
            ];
        },
        opsiDivisiDrill() {
            const set = new Set();
            (this.drill.karyawan || []).forEach(k => {
                if (k.Departemen) set.add(k.Departemen);
                if (k.Divisi) set.add(k.Divisi);
            });
            return Array.from(set).sort();
        },
        opsiJabatanDrill() {
            const set = new Set();
            (this.drill.karyawan || []).forEach(k => {
                if (k.Jabatan) set.add(k.Jabatan);
            });
            return Array.from(set).sort();
        },
        opsiLevelDrill() {
            const set = new Set();
            (this.drill.karyawan || []).forEach(k => {
                if (k.Level) set.add(k.Level);
            });
            return Array.from(set).sort();
        },
        opsiLokasiDrill() {
            const set = new Set();
            (this.drill.karyawan || []).forEach(k => {
                if (k.Lokasi) set.add(k.Lokasi);
            });
            return Array.from(set).sort();
        },
        isAllDrillSelected() {
            const filtered = this.filteredKaryawanDrill;
            if (!filtered.length) return false;
            return filtered.every(k => this.selectedKaryawanDrill.includes(k.Kode_Karyawan));
        },
        filteredKaryawanDrill() {
            let list = this.drill.karyawan || [];
            if (this.drillSearchKaryawan) {
                const q = this.drillSearchKaryawan.toLowerCase();
                list = list.filter(
                    (k) =>
                        (k.Nama || '').toLowerCase().includes(q) ||
                        (k.Kode_Karyawan || '').toLowerCase().includes(q) ||
                        (k.No_Induk_Karyawan || '').toLowerCase().includes(q),
                );
            }
            if (this.drillFilterKaryawan !== 'all') {
                if (this.drillFilterKaryawan === 'selesai') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() === 'SELESAI');
                } else if (this.drillFilterKaryawan === 'terlambat') {
                    list = list.filter((k) => k.Is_Terlambat);
                } else if (this.drillFilterKaryawan === 'progress') {
                    list = list.filter((k) => (k.Status_Penugasan || '').toUpperCase() !== 'SELESAI' && !k.Is_Terlambat);
                }
            }
            if (this.drillFilterDivisi) {
                list = list.filter((k) => k.Departemen === this.drillFilterDivisi || k.Divisi === this.drillFilterDivisi);
            }
            if (this.drillFilterJabatan) {
                list = list.filter((k) => k.Jabatan === this.drillFilterJabatan);
            }
            if (this.drillFilterLevel) {
                list = list.filter((k) => k.Level === this.drillFilterLevel);
            }
            if (this.drillFilterLokasi) {
                list = list.filter((k) => k.Lokasi === this.drillFilterLokasi);
            }
            return list;
        },
    },
    mounted() {
        this.fetchSummary();
        this.fetchCoverage(1);
        this.fetchKuisTertahan();
    },
    methods: {
        filterByStat(status) {
            this.filterState.status = status || 'all';
            this.fetchCoverage(1);
        },
        onSearchChange(val) {
            this.filterState.search = val || '';
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.fetchCoverage(1);
            }, 300);
        },
        onFilterChange(payload) {
            if (payload && payload.values) {
                this.filterState = { ...this.filterState, ...payload.values };
            } else if (payload && payload.key) {
                this.filterState[payload.key] = payload.value;
            }
            this.fetchCoverage(1);
        },
        onChipClick(chip) {
            this.filterState.status = chip.value || 'all';
            this.fetchCoverage(1);
        },
        onResetFilters() {
            this.filterState = {
                search: '',
                prioritas: '',
                status: 'all',
            };
            this.fetchCoverage(1);
        },
        onPageChange(page) {
            this.pagination.page = page;
            this.fetchCoverage(page);
        },
        onPageSizeChange(size) {
            this.pagination.per_page = size;
            this.pagination.page = 1;
            this.fetchCoverage(1);
        },
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s;
        },
        statusBadgeVariant(s) {
            const raw = String(s || '').toUpperCase();
            if (raw === 'SELESAI' || raw === 'LULUS' || raw === 'DONE') return 'success';
            if (raw === 'IN_PROGRESS' || raw === 'PROGRES' || raw === 'PARTIAL') return 'warning';
            if (raw === 'TERLAMBAT' || raw === 'OVERDUE') return 'danger';
            return 'neutral';
        },
        persenClass(p) {
            return p >= 80 ? 'text-success' : p >= 40 ? 'text-warning' : 'text-danger';
        },
        meterFillClass(persen) {
            const p = Number(persen) || 0;
            return p >= 100 ? 'is-emerald' : p >= 80 ? 'is-indigo' : p >= 40 ? 'is-amber' : 'is-rose';
        },
        $vlabel(v) {
            return v ? `v${v}` : 'v1.0';
        },
        async fetchKuisTertahan() {
            this.kuis.loading = true;
            try {
                const r = await axios.get(`${API}/kuis-tertahan`);
                this.kuis.rows = r.data.success ? r.data.result || [] : [];
            } catch (e) {
                this.kuis.rows = [];
            } finally {
                this.kuis.loading = false;
            }
        },
        async resetKuis(k) {
            try {
                await ElMessageBox.confirm(
                    `Kembalikan jatah percobaan kuis "${k.Judul_Item}" untuk ${k.Nama || k.Kode_Karyawan}?`,
                    'Buka ulang kuis',
                    { confirmButtonText: 'Buka ulang', cancelButtonText: 'Batal', type: 'warning' },
                );
            } catch (e) {
                return;
            }
            this.kuis.acting = k.Id_SOP_Progress;
            try {
                const r = await axios.post(`${API}/kuis/${k.Id_SOP_Progress}/reset`);
                ElMessage.success(r.data.message || 'Jatah percobaan dikembalikan.');
                this.fetchKuisTertahan();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal membuka ulang kuis.');
            } finally {
                this.kuis.acting = null;
            }
        },
        async fetchSummary() {
            try {
                const r = await axios.get(`${API}/summary`);
                if (r.data.success) this.summary = r.data.result;
            } catch (e) {
                /* silent */
            }
        },
        async fetchCoverage(page = 1) {
            if (page < 1) return;
            this.loading.cov = true;
            try {
                const f = this.filterState || {};
                const params = {
                    page,
                    limit: this.pagination.per_page,
                    q: f.search || undefined,
                    prioritas: f.prioritas || undefined,
                    status: f.status !== 'all' ? f.status : undefined,
                };

                const r = await axios.get(`${API}/coverage`, { params });
                if (r.data.success) {
                    const pag = r.data.pagination || {};
                    this.rows = r.data.result || [];
                    this.pagination.page = pag.current_page || r.data.page || page;
                    this.pagination.per_page = pag.per_page || this.pagination.per_page;
                    this.pagination.total_page = pag.total_pages || r.data.total_page || 1;
                    this.pagination.total = pag.total ?? r.data.total_data ?? r.data.total ?? this.rows.length;
                } else {
                    this.rows = [];
                    this.pagination.total = 0;
                }
            } catch (e) {
                this.rows = [];
                this.pagination.total = 0;
            } finally {
                this.loading.cov = false;
            }
        },
        async openDrill(payload) {
            const p = payload && payload.row ? payload.row : (payload || {});
            const idPaket = p.Id_SOP_Paket || p.id || p.id_paket;
            if (!idPaket) {
                console.error('ID Paket tidak ditemukan', payload);
                return;
            }

            this.drill = {
                show: true,
                nama: p.Nama_Paket || '',
                idPaket: idPaket,
                prioritas: p.Prioritas || '',
                items: [],
                karyawan: [],
                sections: [],
                funnel: null,
            };
            this.drillSearchKaryawan = '';
            this.drillFilterKaryawan = 'all';
            this.drillFilterDivisi = '';
            this.drillFilterJabatan = '';
            this.drillFilterLevel = '';
            this.drillFilterLokasi = '';
            this.selectedKaryawanDrill = [];
            this.loading.drill = true;
            try {
                const [detail, section, funnel] = await Promise.allSettled([
                    axios.get(`${API}/paket/${idPaket}`),
                    axios.get(`${API}/paket/${idPaket}/section`),
                    axios.get(`${API}/paket/${idPaket}/funnel`),
                ]);
                if (detail.status === 'fulfilled' && detail.value.data.success) {
                    this.drill.items = detail.value.data.result?.items || [];
                    this.drill.karyawan = detail.value.data.result?.karyawan || [];
                }
                if (section.status === 'fulfilled' && section.value.data.success) {
                    this.drill.sections = section.value.data.result || [];
                }
                if (funnel.status === 'fulfilled' && funnel.value.data.success) {
                    this.drill.funnel = funnel.value.data.result;
                }
            } catch (e) {
                ElMessage.error('Gagal memuat detail paket');
            } finally {
                this.loading.drill = false;
            }
        },
        toggleSelectAllDrill() {
            if (this.isAllDrillSelected) {
                const currentCodes = new Set(this.filteredKaryawanDrill.map(k => k.Kode_Karyawan));
                this.selectedKaryawanDrill = this.selectedKaryawanDrill.filter(kode => !currentCodes.has(kode));
            } else {
                const set = new Set(this.selectedKaryawanDrill);
                this.filteredKaryawanDrill.forEach(k => set.add(k.Kode_Karyawan));
                this.selectedKaryawanDrill = Array.from(set);
            }
        },
        cabutKaryawan(k) {
            this.cabut = { buka: true, karyawan: k, kodes: [k.Kode_Karyawan], isBulk: false, sibuk: false };
        },
        cabutBulk() {
            if (!this.selectedKaryawanDrill.length) return;
            this.cabut = {
                buka: true,
                karyawan: null,
                kodes: [...this.selectedKaryawanDrill],
                isBulk: true,
                sibuk: false,
            };
        },
        async kirimCabut({ sebab, catatan }) {
            const isBulk = this.cabut.isBulk;
            const k = this.cabut.karyawan;
            const kodes = isBulk ? this.cabut.kodes : (k ? [k.Kode_Karyawan] : []);
            if (!kodes.length) return;

            this.cabut.sibuk = true;
            try {
                const payload = {
                    id_paket: this.drill.idPaket,
                    sebab,
                    catatan,
                };
                if (isBulk) {
                    payload.kode_karyawan_list = kodes;
                } else {
                    payload.kode_karyawan = k.Kode_Karyawan;
                }

                const r = await axios.post('/api/v1/sop/admin/penjadwalan/cabut', payload);
                if (r.data.success) {
                    ElMessage.success(r.data.message || 'Akses berhasil dicabut');
                    this.cabut = { buka: false, karyawan: null, kodes: [], isBulk: false, sibuk: false };
                    this.selectedKaryawanDrill = [];
                    await this.openDrill({
                        Id_SOP_Paket: this.drill.idPaket,
                        Nama_Paket: this.drill.nama,
                        Prioritas: this.drill.prioritas,
                    });
                    this.fetchCoverage(this.pagination.page);
                    this.fetchSummary();
                    this.$emit('changed');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mencabut akses');
            } finally {
                this.cabut.sibuk = false;
            }
        },
    },
};
</script>

<style scoped>
/* ============ CONTAINERS & SPACING ============ */
.tp-stats-box {
    margin-bottom: 22px;
}

.tp-filter-box {
    margin-bottom: 22px;
}

/* ============ TABLE CARD & WRAPPER ============ */
.tp-table-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.tp-hcis-table {
    border: none !important;
}

.tp-target-count {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-variant-numeric: tabular-nums;
}

.tp-selesai-pill {
    display: inline-flex;
    align-items: center;
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-700);
    border: 1px solid rgba(16, 185, 129, 0.25);
    font-size: 0.74rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
    font-variant-numeric: tabular-nums;
}

.tp-late-pill {
    display: inline-flex;
    align-items: center;
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.28);
    font-size: 0.74rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
    font-variant-numeric: tabular-nums;
}

.tp-zero-pill {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.74rem;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.2rem 0.55rem;
    font-variant-numeric: tabular-nums;
}

/* Progress Meter Column */
.tp-persen-col {
    min-width: 150px;
    padding-right: 0.25rem;
}

.tp-pct-text {
    font-size: 0.85rem;
    font-weight: 800;
    line-height: 1;
}
.tp-pct-text.is-emerald {
    color: var(--hcis-emerald-600);
}
.tp-pct-text.is-amber {
    color: var(--hcis-amber-600);
}
.tp-pct-text.is-danger {
    color: var(--hcis-red-600);
}

.tp-pct-sub {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.tp-meter {
    height: 7px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    width: 100%;
}
.tp-meter-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}
.tp-meter-bar.is-emerald {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
}
.tp-meter-bar.is-indigo {
    background: linear-gradient(90deg, var(--hcis-indigo-400), var(--hcis-indigo-600));
}
.tp-meter-bar.is-amber {
    background: linear-gradient(90deg, var(--hcis-amber-400), var(--hcis-amber-500));
}
.tp-meter-bar.is-rose {
    background: linear-gradient(90deg, var(--hcis-red-400), var(--hcis-red-500));
}

/* Mini Avatar Box */
.tp-mini-avatar-box {
    margin-right: 10px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

.tp-count-badge {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
}
.tp-count-badge.warning {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.25);
}

/* ============ DRILLDOWN DRAWER EXCELLENCE ============ */
.tp-drawer-header-wrap {
    width: 100%;
}

.tp-drawer-top-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    width: 100%;
}

.tp-drawer-identity-col {
    display: flex;
    align-items: center;
    min-width: 0;
    flex-grow: 1;
    padding-right: 12px;
}

.tp-drawer-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-100);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.15);
    flex-shrink: 0;
    margin-right: 14px;
}

.tp-drawer-title {
    font-size: 1.18rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tp-drawer-sub-meta {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
}

.tp-close-btn {
    color: var(--hcis-slate-500) !important;
}
.tp-close-btn:hover {
    color: var(--hcis-slate-900) !important;
    background: var(--hcis-slate-100) !important;
}

/* Funnel Milestone Dashboard */
.tp-funnel-dashboard-box {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
}

.tp-funnel-milestones-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-gap: 12px;
    gap: 12px;
    margin-bottom: 14px;
}

.tp-milestone-cell {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}
.tp-milestone-cell:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.tp-ms-head {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}

.tp-ms-icon {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    flex-shrink: 0;
}
.tp-ms-icon.is-primary {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}
.tp-ms-icon.is-indigo {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-indigo-700);
}
.tp-ms-icon.is-emerald {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-600);
}

.tp-ms-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.tp-ms-val {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.2;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    font-variant-numeric: tabular-nums;
}

.tp-ms-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 999px;
}
.tp-ms-badge.is-indigo {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}
.tp-ms-badge.is-emerald {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-700);
}

.tp-conversion-meter-wrap {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid var(--hcis-slate-100);
}

.tp-conversion-track {
    height: 9px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
    display: flex;
    width: 100%;
}
.tp-conv-fill.is-confirmed {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    height: 100%;
    transition: width 0.4s ease;
}
.tp-conv-fill.is-opened {
    background: linear-gradient(90deg, var(--hcis-indigo-400), var(--hcis-indigo-600));
    height: 100%;
    transition: width 0.4s ease;
}

.tp-funnel-alert {
    display: flex;
    align-items: center;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 12px;
    font-size: 0.76rem;
    color: var(--hcis-amber-800);
}

/* ============ DRAWER BODY CONTENT ============ */
.tp-drawer-body-wrap {
    padding: 0 0 18px;
}

/* Sticky Subfilter in Drawer */
.tp-drawer-sticky-filter {
    position: sticky;
    top: -1.25rem;
    margin: -1.25rem -1.375rem 1.5rem -1.375rem;
    padding: 1.15rem 1.375rem 1.15rem 1.375rem;
    background: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-200);
    box-shadow: 0 6px 18px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    z-index: 25;
}

.tp-filter-search-row {
    margin-bottom: 14px;
}

.tp-chip-list {
    display: flex;
    align-items: center;
    overflow-x: auto;
    padding-bottom: 2px;
}
.tp-chip-list .tp-mini-chip {
    margin-right: 8px;
    flex-shrink: 0;
}
.tp-chip-list .tp-mini-chip:last-child {
    margin-right: 0;
}

.tp-mini-chip {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-size: 0.74rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    transition: all 0.18s ease;
}
.tp-mini-chip:hover {
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-300);
    background: var(--hcis-white);
}
.tp-mini-chip.is-active {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
}
.tp-mini-chip-badge {
    background: rgba(0, 0, 0, 0.08);
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 999px;
    margin-left: 6px;
}
.tp-mini-chip.is-active .tp-mini-chip-badge {
    background: rgba(255, 255, 255, 0.25);
    color: var(--hcis-white);
}

/* Recipient Employee Card Rows */
.tp-emp-cards-list {
    display: flex;
    flex-direction: column;
}
.tp-emp-cards-list .tp-emp-item-card {
    margin-bottom: 12px;
}
.tp-emp-cards-list .tp-emp-item-card:last-child {
    margin-bottom: 0;
}

.tp-emp-item-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}
.tp-emp-item-card:hover {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 6px 18px -2px rgba(99, 102, 241, 0.08);
}
.tp-emp-item-card.is-selected {
    background: #eff6ff;
    border-color: #93c5fd;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.tp-avatar-box {
    margin-right: 12px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

.tp-emp-card-name {
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tp-emp-nik-badge {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    padding: 1px 6px;
    border-radius: 4px;
    font-family: var(--bs-font-monospace);
}

/* Document & Section Blocks */
.tp-section-heading {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    display: flex;
    align-items: center;
}

.tp-table-wrapper {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.tp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.tp-table th {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-weight: 750;
    font-size: 0.74rem;
    padding: 0.65rem 0.95rem;
    border-bottom: 1px solid var(--hcis-slate-200);
    white-space: nowrap;
}
.tp-table td {
    padding: 0.75rem 0.95rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    vertical-align: middle;
}
.tp-table tr:last-child td {
    border-bottom: none;
}
.tp-table-row:hover {
    background: var(--hcis-slate-50);
}

.tp-vbadge {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    padding: 0.05rem 0.35rem;
    display: inline-block;
}

.tp-mini-meter {
    width: 70px;
    height: 5px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
}
.tp-mini-meter-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}
.tp-mini-meter-fill.is-emerald {
    background: var(--hcis-emerald-500);
}
.tp-mini-meter-fill.is-indigo {
    background: var(--hcis-indigo-500);
}
.tp-mini-meter-fill.is-amber {
    background: var(--hcis-amber-500);
}
.tp-mini-meter-fill.is-rose {
    background: var(--hcis-red-500);
}
</style>
