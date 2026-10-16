<template>
    <main class="pa-bal-page">
        <!-- 1. Unified Executive Glass Header (Design System HcisHeader) -->
        <HcisHeader
            title="Balancing &amp; Forum Kalibrasi"
            kicker="PERFORMANCE APPRAISAL · BALANCING"
            description="Sesi forum penyeimbangan skor kompetensi pasca-kalkulasi sebelum penerbitan grade resmi. Setiap sesi FINAL dihitung ulang sebelum publikasi."
            icon="sliders"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="pa-bal-header"
        >
            <template #badge>
                <HcisBadge
                    v-if="siklusAktif"
                    :label="`Cycle: ${siklusAktif.Nama_Cycle || siklusAktif.Kode_Cycle} (${siklusAktif.Status_Cycle})`"
                    :variant="getStatusSentiment(siklusAktif.Status_Cycle)"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
            </template>

            <template #actions>
                <div class="pa-bal-header-actions">
                    <!-- Premium Period Selector Trigger Button (sama seperti di Monitoring) -->
                    <button
                        type="button"
                        class="pa-period-trigger"
                        :disabled="memuat"
                        :class="{ 'pa-period-trigger--loading': memuat }"
                        aria-haspopup="dialog"
                        :aria-expanded="bukaDialogPeriode"
                        title="Klik untuk memilih siklus &amp; periode penilaian"
                        @click="bukaDialogPeriode = true"
                    >
                        <div class="pa-period-trigger__jewel">
                            <HcisIcon name="calendar2-range-fill" size="xs" />
                        </div>
                        <div class="pa-period-trigger__body">
                            <span class="pa-period-trigger__label">
                                PERIODE AKTIF
                                <span v-if="siklusAktif?.Tahun" class="pa-period-trigger__year">{{ siklusAktif.Tahun }}</span>
                            </span>
                            <span class="pa-period-trigger__value">
                                {{ siklusAktif ? (siklusAktif.Nama_Cycle || siklusAktif.Kode_Cycle) : (memuat ? 'Memuat siklus...' : 'Pilih Siklus') }}
                            </span>
                        </div>
                        <span v-if="siklusAktif" class="pa-period-trigger__badge-wrap">
                            <HcisBadge
                                :label="getStatusCycleLabel(siklusAktif.Status_Cycle)"
                                :variant="getStatusSentiment(siklusAktif.Status_Cycle)"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </span>
                        <div class="pa-period-trigger__chevron">
                            <HcisIcon name="chevron-down" size="2xs" />
                        </div>
                    </button>

                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        aria-label="Segarkan data sesi balancing"
                        @click="muatSesi"
                    />

                    <HcisButton
                        label="Buat sesi"
                        variant="primary"
                        size="sm"
                        leading-icon="plus-lg"
                        :disabled="!cycleId || !ringkas.boleh_buat"
                        :title="ringkas.alasan_tidak_boleh_buat || ''"
                        data-uji="buat-sesi-halaman"
                        @click="dialogBuat = true"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- 2. Executive Stat Group & Stat Cards (Design System HcisStatGroup & HcisStatCard) -->
        <HcisStatGroup :cols="4" gap="sm" class="pa-bal-stats">
            <HcisStatCard
                layout="compact"
                label="Total Sesi"
                :value="formatAngka(sesi.length)"
                caption="Seluruh sesi di siklus ini"
                icon="collection-fill"
                sentiment="brand"
            />
            <HcisStatCard
                layout="compact"
                label="Sesi Terbuka"
                :value="formatAngka(ringkas.terbuka)"
                caption="Masih dalam pembahasan"
                icon="hourglass-split"
                :sentiment="ringkas.terbuka > 0 ? 'info' : 'neutral'"
            />
            <HcisStatCard
                layout="compact"
                label="Sesi Final"
                :value="formatAngka(totalFinal)"
                caption="Skor telah dikunci"
                icon="check-circle-fill"
                sentiment="success"
            />
            <HcisStatCard
                layout="compact"
                label="Perlu Hitung Ulang"
                :value="formatAngka(ringkas.menunggu_hitung_ulang)"
                caption="Menahan publikasi hasil"
                icon="arrow-repeat"
                :sentiment="ringkas.menunggu_hitung_ulang > 0 ? 'warning' : 'neutral'"
                :interactive="ringkas.menunggu_hitung_ulang > 0 && ringkas.terbuka === 0"
                @click="ringkas.menunggu_hitung_ulang > 0 && ringkas.terbuka === 0 ? (dialogHitung = true) : null"
            />
        </HcisStatGroup>

        <!-- 3. Alerts (Design System HcisAlert) -->
        <HcisAlert v-if="pesanHasil" variant="success" :title="pesanHasil" class="mb-1" />
        <HcisAlert v-if="galat" variant="danger" :title="galat" class="mb-1" />

        <HcisAlert
            v-if="ringkas.menunggu_hitung_ulang > 0"
            variant="warning"
            :title="`${ringkas.menunggu_hitung_ulang} sesi FINAL belum dihitung ulang`"
            description="Publish ditahan sampai hitung ulang dijalankan. Hasil penyeimbangan sesi final akan dikalkulasi ulang ke kurva grup."
            class="pa-bal-hitung-alert"
        >
            <div class="pa-bal-hitung-alert__body">
                <HcisButton
                    label="Hitung ulang"
                    variant="warning"
                    size="sm"
                    leading-icon="arrow-repeat"
                    :disabled="ringkas.terbuka > 0"
                    @click="dialogHitung = true"
                />
                <small v-if="ringkas.terbuka > 0" class="pa-bal-hitung-alert__hint">
                    Finalkan atau batalkan {{ ringkas.terbuka }} sesi terbuka lebih dulu.
                </small>
            </div>
        </HcisAlert>

        <!-- 4. Filter Bar & Search Toolbar (Design System HcisFilterBar) -->
        <HcisFilterBar
            :search="kataKunci"
            search-placeholder="Cari nama sesi atau PIC..."
            :quick-chips="filterChips"
            :active-chip-value="filterStatus"
            variant="card"
            sentiment="brand"
            size="sm"
            class="pa-bal-filterbar"
            @update:search="(v) => kataKunci = v"
            @chip-click="(c) => filterStatus = c.value"
            @reset="resetFilter"
        />

        <!-- 5. Content List (Design System HcisSkeleton, HcisEmptyState, HcisCard) -->
        <HcisSkeleton v-if="memuat" :rows="5" />

        <HcisEmptyState
            v-else-if="!sesi.length"
            variant="card"
            sentiment="neutral"
            size="md"
            title="Belum ada sesi Balancing di siklus ini"
            description="Buat sesi baru untuk memulai penyeimbangan skor kompetensi bersama tim forum penilai."
            icon="sliders"
        >
            <template #actions>
                <HcisButton
                    label="Buat Sesi Pertama"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    :disabled="!cycleId || !ringkas.boleh_buat"
                    :title="ringkas.alasan_tidak_boleh_buat || ''"
                    @click="dialogBuat = true"
                />
            </template>
        </HcisEmptyState>

        <HcisEmptyState
            v-else-if="!sesiTersaring.length"
            variant="card"
            sentiment="neutral"
            size="md"
            title="Tidak ada sesi yang cocok"
            description="Coba ubah kata kunci pencarian atau ganti filter status di atas."
            icon="search"
        >
            <template #actions>
                <HcisButton
                    label="Reset Filter"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="resetFilter"
                />
            </template>
        </HcisEmptyState>

        <!-- Main Session Container (Enclosed Card with Table and Card view modes) -->
        <HcisCard
            v-else
            variant="glass"
            sentiment="brand"
            class="pa-bal-sesi-card-container"
        >
            <template #header>
                <div class="pa-bal-card-header-bar">
                    <div class="pa-bal-card-header-left">
                        <div class="pa-bal-card-header-icon">
                            <HcisIcon name="collection-fill" size="xs" />
                        </div>
                        <div class="pa-bal-card-header-titles">
                            <h2 class="pa-bal-card-header-title">Daftar Sesi Balancing</h2>
                            <span class="pa-bal-card-header-subtitle">
                                {{ sesiTersaring.length }} sesi ditemukan &bull; Pilih sesi untuk membuka forum penyesuaian nilai
                            </span>
                        </div>
                    </div>

                    <!-- View Switcher Controls -->
                    <div class="pa-bal-view-switch-group">
                        <button
                            type="button"
                            class="pa-bal-view-switch-btn"
                            :class="{ 'is-active': modeTampilan === 'table' }"
                            title="Tampilan Tabel"
                            @click="modeTampilan = 'table'"
                        >
                            <HcisIcon name="table" size="2xs" />
                            <span>Tabel</span>
                        </button>
                        <button
                            type="button"
                            class="pa-bal-view-switch-btn"
                            :class="{ 'is-active': modeTampilan === 'card' }"
                            title="Tampilan Kartu"
                            @click="modeTampilan = 'card'"
                        >
                            <HcisIcon name="grid-fill" size="2xs" />
                            <span>Kartu</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Mode 1: Table View (Clean, High-density, Structured) -->
            <div v-if="modeTampilan === 'table'" class="pa-bal-table-container">
                <table class="pa-bal-table">
                    <thead>
                        <tr>
                            <th scope="col" style="min-width: 220px;">Sesi Balancing</th>
                            <th scope="col" style="width: 150px;">Status</th>
                            <th scope="col" style="min-width: 170px;">PIC Sesi</th>
                            <th scope="col" style="text-align: center; width: 100px;">Peserta</th>
                            <th scope="col" style="text-align: center; width: 100px;">Forum</th>
                            <th scope="col" style="width: 140px;">Dibuat Pada</th>
                            <th scope="col" style="text-align: right; width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in sesiTersaring"
                            :key="s.id"
                            class="pa-bal-table-row"
                            @click="buka(s.id)"
                        >
                            <td>
                                <div class="pa-bal-table-title-box">
                                    <strong class="pa-bal-table-name">{{ s.nama }}</strong>
                                    <small class="text-muted">ID: #{{ s.id }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="pa-bal-table-status-box">
                                    <HcisBadge
                                        :label="LABEL_STATUS[s.status] || s.status"
                                        :variant="VARIAN_STATUS[s.status]"
                                        size="xs"
                                        appearance="subtle"
                                        dot
                                    />
                                    <HcisBadge
                                        v-if="s.status === 'FINAL' && !s.hitung_ulang_at"
                                        label="Perlu hitung ulang"
                                        variant="warning"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                </div>
                            </td>
                            <td>
                                <div class="pa-bal-table-pic">
                                    <HcisAvatar :name="s.pic_nama || 'PIC'" size="xs" shape="circle" />
                                    <span class="pa-bal-table-pic-name">{{ s.pic_nama || '–' }}</span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="pa-bal-table-chip">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>{{ s.jumlah_peserta }}</span>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="pa-bal-table-chip">
                                    <HcisIcon name="person-badge" size="2xs" />
                                    <span>{{ s.jumlah_forum }}</span>
                                </span>
                            </td>
                            <td>
                                <span class="pa-bal-table-date">{{ tanggal(s.created_at) }}</span>
                            </td>
                            <td style="text-align: right;">
                                <HcisButton
                                    label="Buka"
                                    variant="ghost"
                                    size="xs"
                                    trailing-icon="arrow-right"
                                    @click.stop="buka(s.id)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mode 2: Card View (Clean, Enclosed Cards with NO border-left) -->
            <ul v-else class="pa-bal-daftar__kartu">
                <li v-for="s in sesiTersaring" :key="s.id">
                    <HcisCard
                        variant="flat"
                        sentiment="brand"
                        interactive
                        class="pa-bal-sesi-hcis-card"
                        @click="buka(s.id)"
                    >
                        <button
                            type="button"
                            class="pa-bal-daftar__sesi"
                            @click.stop="buka(s.id)"
                        >
                            <div class="pa-bal-sesi__avatar-wrap">
                                <HcisAvatar
                                    :name="s.nama"
                                    size="md"
                                    shape="rounded"
                                />
                            </div>

                            <div class="pa-bal-daftar__judul">
                                <div class="pa-bal-sesi__header-row">
                                    <strong class="pa-bal-sesi__nama">{{ s.nama }}</strong>
                                    <div class="pa-bal-sesi__badges">
                                        <HcisBadge
                                            :label="LABEL_STATUS[s.status] || s.status"
                                            :variant="VARIAN_STATUS[s.status]"
                                            size="xs"
                                            appearance="subtle"
                                            dot
                                        />
                                        <HcisBadge
                                            v-if="s.status === 'FINAL'"
                                            :label="s.hitung_ulang_at ? `Dihitung ulang #${s.nomor_hitung_ulang}` : 'Menunggu hitung ulang'"
                                            :variant="s.hitung_ulang_at ? 'success' : 'warning'"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>
                                </div>

                                <div class="pa-bal-sesi__info-row">
                                    <span class="pa-bal-sesi__pic">
                                        <HcisIcon name="person-circle" size="2xs" />
                                        PIC: <strong>{{ s.pic_nama || '–' }}</strong>
                                    </span>
                                    <span class="pa-bal-sesi__sep">&bull;</span>
                                    <span class="pa-bal-sesi__tgl">
                                        <HcisIcon name="calendar3" size="2xs" />
                                        {{ tanggal(s.created_at) }}
                                    </span>
                                </div>
                            </div>

                            <div class="pa-bal-daftar__meta">
                                <span class="pa-bal-meta-pill" title="Jumlah peserta di sesi ini">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <strong>{{ s.jumlah_peserta }}</strong> peserta
                                </span>
                                <span class="pa-bal-meta-pill" title="Jumlah anggota forum penilai">
                                    <HcisIcon name="person-badge" size="2xs" />
                                    <strong>{{ s.jumlah_forum }}</strong> forum
                                </span>
                            </div>

                            <div class="pa-bal-sesi__panah" aria-hidden="true">
                                <HcisIcon name="chevron-right" size="xs" />
                            </div>
                        </button>
                    </HcisCard>
                </li>
            </ul>
        </HcisCard>

        <!-- Buat Sesi Dialog (Design System Component) -->
        <PaBalancingBuatSesiDialog v-model="dialogBuat" :cycle-id="cycleId" @dibuat="(d) => buka(d.id)" />

        <!-- Hitung Ulang Dialog (Design System HcisDialog) -->
        <HcisDialog v-model="dialogHitung" title="Hitung ulang Balancing?" size="sm" sentiment="warning">
            <p>
                Skor kompetensi dari sesi FINAL diterapkan, kurva grade dihitung ulang dan dikunci lagi.
                Grade orang di luar sesi bisa ikut bergeser; semuanya tercatat di jejak.
            </p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogHitung = false" />
                <HcisButton label="Ya, hitung ulang" variant="warning" size="sm" :loading="menghitung" @click="hitungUlang" />
            </template>
        </HcisDialog>

        <!-- Dialog Pemilih Siklus & Periode Penilaian (Rich Period Selector) -->
        <HcisDialog
            v-model="bukaDialogPeriode"
            title="Pilih Siklus &amp; Periode Penilaian"
            subtitle="Pilih assessment cycle untuk mengelola sesi balancing dan forum kalibrasi."
            icon="calendar2-range-fill"
            sentiment="brand"
            variant="glass"
            size="lg"
            pattern="grid"
        >
            <div class="pa-period-dialog">
                <!-- Search & Filter Controls -->
                <div class="pa-period-dialog__controls">
                    <div class="pa-period-dialog__search-wrap">
                        <HcisIcon name="search" size="xs" class="pa-period-dialog__search-icon" />
                        <input
                            v-model="kataCariSiklus"
                            type="text"
                            class="pa-period-dialog__search-input"
                            placeholder="Cari nama siklus, kode, atau tahun (contoh: 2025, Semester 1)..."
                            aria-label="Cari siklus penilaian"
                        >
                        <button
                            v-if="kataCariSiklus"
                            type="button"
                            class="pa-period-dialog__clear-btn"
                            title="Hapus pencarian"
                            @click="kataCariSiklus = ''"
                        >
                            <HcisIcon name="x-circle-fill" size="2xs" />
                        </button>
                    </div>

                    <div class="pa-period-dialog__filter-tabs" role="tablist" aria-label="Filter status siklus">
                        <button
                            type="button"
                            class="pa-filter-tab"
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'SEMUA' }"
                            @click="tabStatusSiklus = 'SEMUA'"
                        >
                            Semua
                            <span class="pa-filter-tab__count">{{ siklus.length }}</span>
                        </button>
                        <button
                            type="button"
                            class="pa-filter-tab"
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'ACTIVE' }"
                            @click="tabStatusSiklus = 'ACTIVE'"
                        >
                            Sedang Berjalan
                            <span class="pa-filter-tab__count">{{ cacahSiklusActive }}</span>
                        </button>
                        <button
                            type="button"
                            class="pa-filter-tab"
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'READY' }"
                            @click="tabStatusSiklus = 'READY'"
                        >
                            Siap Balancing
                            <span class="pa-filter-tab__count">{{ cacahSiklusReady }}</span>
                        </button>
                        <button
                            type="button"
                            class="pa-filter-tab"
                            :class="{ 'pa-filter-tab--active': tabStatusSiklus === 'OTHER' }"
                            @click="tabStatusSiklus = 'OTHER'"
                        >
                            Terbit / Lainnya
                            <span class="pa-filter-tab__count">{{ cacahSiklusOther }}</span>
                        </button>
                    </div>
                </div>

                <!-- Cycle Grid List -->
                <div v-if="filteredSiklusList.length" class="pa-period-dialog__grid">
                    <div
                        v-for="c in filteredSiklusList"
                        :key="c.Id_PA_Cycle"
                        class="pa-cycle-card"
                        :class="{
                            'pa-cycle-card--selected': String(c.Id_PA_Cycle) === String(cycleId),
                        }"
                        role="button"
                        tabindex="0"
                        @click="pilihSiklus(c)"
                        @keydown.enter="pilihSiklus(c)"
                        @keydown.space.prevent="pilihSiklus(c)"
                    >
                        <div class="pa-cycle-card__header">
                            <div class="pa-cycle-card__badges">
                                <span class="pa-cycle-code-chip">{{ c.Kode_Cycle || 'NO-CODE' }}</span>
                                <span v-if="c.Tahun" class="pa-cycle-year-chip">Tahun {{ c.Tahun }}</span>
                            </div>
                            <HcisBadge
                                :label="getStatusCycleLabel(c.Status_Cycle)"
                                :variant="getStatusSentiment(c.Status_Cycle)"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>

                        <div class="pa-cycle-card__body">
                            <h4 class="pa-cycle-card__title">
                                {{ c.Nama_Cycle || c.Kode_Cycle || 'Siklus Penilaian Kinerja' }}
                            </h4>

                            <div class="pa-cycle-card__meta-list">
                                <div v-if="c.Periode_Dinilai_Dari || c.Periode_Dinilai_Sampai" class="pa-cycle-meta-item">
                                    <HcisIcon name="calendar-event" size="2xs" class="text-slate-400" />
                                    <span>Periode: <strong>{{ formatTanggalPeriode(c.Periode_Dinilai_Dari, c.Periode_Dinilai_Sampai) }}</strong></span>
                                </div>
                                <div v-if="c.Pengisian_Dari || c.Pengisian_Sampai" class="pa-cycle-meta-item">
                                    <HcisIcon name="pencil-square" size="2xs" class="text-slate-400" />
                                    <span>Pengisian: <strong>{{ formatTanggalPeriode(c.Pengisian_Dari, c.Pengisian_Sampai) }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="pa-cycle-card__footer">
                            <div v-if="String(c.Id_PA_Cycle) === String(cycleId)" class="pa-cycle-card__selected-pill">
                                <HcisIcon name="check-circle-fill" size="xs" />
                                <span>Siklus Terpilih</span>
                            </div>
                            <div v-else class="pa-cycle-card__select-action">
                                <span>Pilih Siklus</span>
                                <HcisIcon name="arrow-right-short" size="sm" />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="pa-period-dialog__empty">
                    <HcisIcon name="calendar-x" size="md" class="text-slate-400" />
                    <p>Tidak ada siklus yang cocok dengan filter ini.</p>
                </div>
            </div>

            <template #footer>
                <HcisButton
                    label="Tutup"
                    variant="glass"
                    size="sm"
                    @click="bukaDialogPeriode = false"
                />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
} from '@/components/ui';
import paApi from '../api/paApi.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';
import PaBalancingBuatSesiDialog from './components/PaBalancingBuatSesiDialog.vue';
import { LABEL_STATUS, VARIAN_STATUS, pesanKualitatifDilewati } from './utils/balancing.js';

defineOptions({ name: 'PerformanceAppraisalBalancingPage' });

const siklus = ref([]);
const cycleId = ref('');
const sesi = ref([]);
const ringkas = ref({ menunggu_hitung_ulang: 0, terbuka: 0, boleh_buat: true, alasan_tidak_boleh_buat: null });
const memuat = ref(false);
const galat = ref('');
const pesanHasil = ref('');
const dialogBuat = ref(false);
const dialogHitung = ref(false);
const menghitung = ref(false);

const kataKunci = ref('');
const filterStatus = ref('SEMUA');
const modeTampilan = ref('table');

const bukaDialogPeriode = ref(false);
const kataCariSiklus = ref('');
const tabStatusSiklus = ref('SEMUA');

function getStatusSentiment(status) {
    if (status === 'PUBLISHED') return 'success';
    if (status === 'CALCULATED') return 'purple';
    if (status === 'OPEN') return 'brand';
    if (status === 'LOCKED') return 'warning';
    if (status === 'CLOSED') return 'neutral';
    if (status === 'PREPARED') return 'brand';
    return 'neutral';
}

function getStatusCycleLabel(status) {
    const map = {
        PUBLISHED: 'Terbit & Final',
        CALCULATED: 'Terkalkulasi',
        LOCKED: 'Terkunci (Siap Hitung)',
        OPEN: 'Pengisian Berjalan',
        PREPARED: 'Siap / Penyiapan',
        DRAFT: 'Draft',
        CLOSED: 'Ditutup (Siap Balancing)',
    };
    return map[status] || status || 'Cycle';
}

function formatTanggalPeriode(tglDari, tglSampai) {
    if (!tglDari && !tglSampai) return 'Periode dinilai belum ditentukan';
    if (tglDari && !tglSampai) return `Mulai ${tanggal(tglDari)}`;
    if (!tglDari && tglSampai) return `Hingga ${tanggal(tglSampai)}`;
    return `${tanggal(tglDari)} – ${tanggal(tglSampai)}`;
}

/* Gerbang BALANCING hanya membuka CLOSED, jadi hanya CLOSED yang masuk tab yang bisa dikerjakan.
   Dulu tab ini berisi LOCKED/CALCULATED/PUBLISHED — CALCULATED tidak pernah ada, dan CLOSED
   (satu-satunya status yang bisa di-Balancing) tersembunyi di "Ditutup / Arsip". */
const STATUS_SIAP_BALANCING = ['CLOSED'];
const cacahSiklusActive = computed(() => (siklus.value || []).filter((c) => c.Status_Cycle === 'OPEN').length);
const cacahSiklusReady = computed(() => (siklus.value || []).filter((c) => STATUS_SIAP_BALANCING.includes(c.Status_Cycle)).length);
const cacahSiklusOther = computed(() => (siklus.value || []).filter((c) => !['OPEN', ...STATUS_SIAP_BALANCING].includes(c.Status_Cycle)).length);

const filteredSiklusList = computed(() => {
    let list = siklus.value || [];

    if (tabStatusSiklus.value === 'ACTIVE') {
        list = list.filter((c) => c.Status_Cycle === 'OPEN');
    } else if (tabStatusSiklus.value === 'READY') {
        list = list.filter((c) => STATUS_SIAP_BALANCING.includes(c.Status_Cycle));
    } else if (tabStatusSiklus.value === 'OTHER') {
        list = list.filter((c) => !['OPEN', ...STATUS_SIAP_BALANCING].includes(c.Status_Cycle));
    }

    if (kataCariSiklus.value.trim()) {
        const q = kataCariSiklus.value.toLowerCase().trim();
        list = list.filter((c) =>
            (c.Nama_Cycle || '').toLowerCase().includes(q)
            || (c.Kode_Cycle || '').toLowerCase().includes(q)
            || String(c.Tahun || '').includes(q)
            || (c.Status_Cycle || '').toLowerCase().includes(q)
        );
    }

    return list;
});

function pilihSiklus(c) {
    cycleId.value = String(c.Id_PA_Cycle);
    bukaDialogPeriode.value = false;
    muatSesi();
}

const siklusAktif = computed(() => siklus.value.find((c) => String(c.Id_PA_Cycle) === String(cycleId.value)) || null);
const totalFinal = computed(() => sesi.value.filter((s) => s.status === 'FINAL').length);

const filterChips = computed(() => [
    { id: 'semua', label: 'Semua', value: 'SEMUA', badge: sesi.value.length },
    { id: 'terbuka', label: 'Terbuka', value: 'TERBUKA', badge: ringkas.value.terbuka, color: 'blue' },
    { id: 'final', label: 'Final', value: 'FINAL', badge: totalFinal.value, color: 'emerald' },
    ...(ringkas.value.menunggu_hitung_ulang > 0 ? [{ id: 'hitung', label: 'Perlu Hitung Ulang', value: 'PERLU_HITUNG', badge: ringkas.value.menunggu_hitung_ulang, color: 'amber' }] : []),
]);

const sesiTersaring = computed(() => {
    let hasil = sesi.value || [];

    if (filterStatus.value === 'TERBUKA') {
        hasil = hasil.filter((s) => s.status === 'TERBUKA' || s.status === 'DRAFT');
    } else if (filterStatus.value === 'FINAL') {
        hasil = hasil.filter((s) => s.status === 'FINAL');
    } else if (filterStatus.value === 'PERLU_HITUNG') {
        hasil = hasil.filter((s) => s.status === 'FINAL' && !s.hitung_ulang_at);
    }

    if (kataKunci.value.trim()) {
        const cari = kataKunci.value.toLowerCase().trim();
        hasil = hasil.filter((s) => (s.nama || '').toLowerCase().includes(cari) || (s.pic_nama || '').toLowerCase().includes(cari));
    }

    return hasil;
});

function formatAngka(v) {
    return Number(v || 0).toLocaleString('id-ID');
}

function resetFilter() {
    kataKunci.value = '';
    filterStatus.value = 'SEMUA';
}

async function muatSiklus() {
    try {
        const r = await paApi.daftarSiklus();
        siklus.value = r?.siklus || [];
        const pilihan = cycleDariUrl(siklus.value)
            || String((siklus.value.find((c) => c.Status_Cycle === 'CLOSED') || siklus.value[0])?.Id_PA_Cycle || '');
        cycleId.value = pilihan;
        await muatSesi();
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat siklus.';
    }
}

async function muatSesi() {
    if (!cycleId.value) return;
    memuat.value = true;
    galat.value = '';
    try {
        const r = await paApi.daftarSesiBalancing(Number(cycleId.value));
        sesi.value = r?.sesi || [];
        ringkas.value = {
            menunggu_hitung_ulang: r?.menunggu_hitung_ulang || 0,
            terbuka: r?.terbuka || 0,
            boleh_buat: r?.boleh_buat !== false,
            alasan_tidak_boleh_buat: r?.alasan_tidak_boleh_buat || null,
        };
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat sesi.';
    } finally {
        memuat.value = false;
    }
}

async function hitungUlang() {
    menghitung.value = true;
    galat.value = '';
    try {
        const r = await paApi.hitungUlangBalancing(Number(cycleId.value));
        pesanHasil.value = `Hitung ulang #${r.nomor} selesai: ${r.dihitung} peserta dihitung, ${r.berubah} baris jejak berubah.`
            + (pesanKualitatifDilewati(r) ? ` ${pesanKualitatifDilewati(r)}` : '');
        await muatSesi();
    } catch (e) {
        galat.value = e?.message || 'Hitung ulang ditolak.';
    } finally {
        menghitung.value = false;
        dialogHitung.value = false;
    }
}

function buka(id) {
    router.visit(`/performance-appraisal/balancing/sesi/${id}`);
}

function tanggal(v) {
    return v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–';
}

onMounted(muatSiklus);
</script>

<style scoped>
.pa-bal-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 1.5rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
}

.pa-bal-header {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-bal-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Premium Period Selector Trigger Button (sama seperti di Monitoring) */
.pa-period-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.35rem 0.75rem 0.35rem 0.45rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    text-align: left;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    font: inherit;
    color: var(--hcis-text-strong, #0f172a);
    max-width: 380px;
    user-select: none;
}

.pa-period-trigger:hover:not(:disabled) {
    background: #ffffff;
    border-color: #818cf8;
    box-shadow: 0 3px 8px rgba(99, 102, 241, 0.14), 0 1px 3px rgba(15, 23, 42, 0.08);
    transform: translateY(-1px);
}

.pa-period-trigger:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.pa-period-trigger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.pa-period-trigger__jewel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
    color: #ffffff;
    box-shadow: 0 2px 4px rgba(124, 58, 237, 0.3);
    flex-shrink: 0;
}

.pa-period-trigger__body {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
    flex: 1;
}

.pa-period-trigger__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1;
}

.pa-period-trigger__year {
    display: inline-flex;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
    font-size: 0.6rem;
    font-weight: 800;
}

.pa-period-trigger__value {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.pa-period-trigger__badge-wrap {
    flex-shrink: 0;
}

.pa-period-trigger__chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, #94a3b8);
    transition: transform 180ms ease;
    flex-shrink: 0;
    margin-left: -0.15rem;
}

.pa-period-trigger:hover .pa-period-trigger__chevron {
    color: #7c3aed;
    transform: translateY(1px);
}

/* Rich Period Selector Dialog Components */
.pa-period-dialog {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0.25rem 0;
}

.pa-period-dialog__controls {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-period-dialog__search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.pa-period-dialog__search-icon {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    pointer-events: none;
}

.pa-period-dialog__search-input {
    width: 100%;
    height: 2.75rem;
    padding: 0 2.5rem 0 2.75rem;
    font: inherit;
    font-size: 0.86rem;
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    outline: none;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 180ms ease;
    box-sizing: border-box;
}

.pa-period-dialog__search-input:focus {
    border-color: #7c3aed;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}

.pa-period-dialog__clear-btn {
    position: absolute;
    right: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: color 150ms ease;
}

.pa-period-dialog__clear-btn:hover {
    color: #475569;
}

.pa-period-dialog__filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.pa-filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    font: inherit;
    font-size: 0.76rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-full, 9999px);
    cursor: pointer;
    white-space: nowrap;
    transition: all 150ms ease;
}

.pa-filter-tab:hover {
    color: #7c3aed;
    background: rgba(255, 255, 255, 0.8);
}

.pa-filter-tab--active {
    background: #ffffff;
    color: #7c3aed;
    border-color: rgba(124, 58, 237, 0.3);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-filter-tab__count {
    display: inline-flex;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 750;
    background: rgba(148, 163, 184, 0.2);
    color: inherit;
}

.pa-filter-tab--active .pa-filter-tab__count {
    background: rgba(124, 58, 237, 0.15);
    color: #6d28d9;
}

.pa-period-dialog__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.85rem;
    max-height: 52vh;
    overflow-y: auto;
    padding: 0.25rem 0.25rem 0.5rem 0.25rem;
}

.pa-cycle-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.pa-cycle-card:hover {
    background: #ffffff;
    border-color: #c4b5fd;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1), 0 1px 3px rgba(15, 23, 42, 0.06);
    transform: translateY(-2px);
}

.pa-cycle-card--selected {
    background: linear-gradient(180deg, #ffffff 0%, #faf5ff 100%);
    border-color: #7c3aed;
    box-shadow: 0 0 0 1.5px #7c3aed, 0 4px 14px rgba(124, 58, 237, 0.16);
}

.pa-cycle-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-cycle-card__badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-cycle-code-chip {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
    background: rgba(15, 23, 42, 0.06);
    color: var(--hcis-text-strong, #0f172a);
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.pa-cycle-year-chip {
    display: inline-flex;
    padding: 0.15rem 0.45rem;
    border-radius: 5px;
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
    font-size: 0.68rem;
    font-weight: 750;
}

.pa-cycle-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.pa-cycle-card__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
}

.pa-cycle-card__meta-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-cycle-meta-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-cycle-meta-item strong {
    color: var(--hcis-text-strong, #334155);
}

.pa-cycle-card__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
    margin-top: auto;
}

.pa-cycle-card__selected-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: #f3e8ff;
    color: #6b21a8;
    font-size: 0.72rem;
    font-weight: 750;
}

.pa-cycle-card__select-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: #7c3aed;
    font-size: 0.74rem;
    font-weight: 700;
    transition: transform 150ms ease;
}

.pa-cycle-card:hover .pa-cycle-card__select-action {
    transform: translateX(3px);
}

.pa-period-dialog__empty {
    padding: 2.5rem 1rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Stat Cards */
.pa-bal-stats {
    margin-bottom: 0.25rem;
}

/* Hitung Ulang Banner */
.pa-bal-hitung-alert__body {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.65rem;
    flex-wrap: wrap;
}

.pa-bal-hitung-alert__hint {
    color: #92400e;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Filter Bar */
.pa-bal-filterbar {
    border-radius: var(--hcis-radius-xl, 16px);
}

/* Sesi List Cards */
.pa-bal-daftar__kartu {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 0.75rem;
}

.pa-bal-sesi-card-container {
    padding: 0 !important;
    overflow: hidden;
}

.pa-bal-card-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.pa-bal-card-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-bal-card-header-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(79, 70, 229, 0.1);
    color: var(--hcis-brand-600, #4f46e5);
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.pa-bal-card-header-titles {
    display: flex;
    flex-direction: column;
}

.pa-bal-card-header-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    margin: 0;
}

.pa-bal-card-header-subtitle {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

/* View Switcher */
.pa-bal-view-switch-group {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 2px;
    gap: 2px;
}

.pa-bal-view-switch-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border: 0;
    background: none;
    border-radius: var(--hcis-radius-md, 7px);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-bal-view-switch-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-view-switch-btn.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* Table View Styles */
.pa-bal-table-container {
    overflow-x: auto;
    width: 100%;
}

.pa-bal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.pa-bal-table th {
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-text-muted, #64748b);
    font-weight: 700;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1.15rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    white-space: nowrap;
}

.pa-bal-table td {
    padding: 0.85rem 1.15rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
    vertical-align: middle;
}

.pa-bal-table-row {
    cursor: pointer;
    transition: background-color 150ms ease;
}

.pa-bal-table-row:hover {
    background-color: rgba(248, 250, 252, 0.8);
}

.pa-bal-table-row:last-child td {
    border-bottom: 0;
}

.pa-bal-table-title-box {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-bal-table-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-table-id {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-table-status-box {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-bal-table-pic {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.pa-bal-table-pic-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-table-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: rgba(241, 245, 249, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-table-date {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
}

/* Card View Item Styles (Clean, NO border-left) */
.pa-bal-sesi-hcis-card {
    padding: 0 !important;
    border-radius: var(--hcis-radius-xl, 14px);
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.85);
    transition: all 150ms ease;
}

.pa-bal-sesi-hcis-card:hover {
    border-color: rgba(79, 70, 229, 0.35);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.pa-bal-daftar__sesi {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    padding: 1.1rem 1.35rem;
    background: none;
    border: 0;
    text-align: left;
    cursor: pointer;
}

.pa-bal-sesi__avatar-wrap {
    flex-shrink: 0;
}

.pa-bal-daftar__judul {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1 1 240px;
    min-width: 0;
}

.pa-bal-sesi__header-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.pa-bal-sesi__nama {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.3;
}

.pa-bal-sesi__badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-bal-sesi__info-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-bal-sesi__pic, .pa-bal-sesi__tgl {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-bal-sesi__sep {
    color: #cbd5e1;
}

.pa-bal-daftar__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pa-bal-meta-pill {
    padding: 0.3rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-meta-pill strong {
    color: var(--hcis-text-strong, #0f172a);
    margin: 0 0.15rem;
}

.pa-bal-sesi__panah {
    color: #94a3b8;
    margin-left: 0.25rem;
    transition: transform 0.15s ease, color 0.15s ease;
    display: grid;
    place-items: center;
}

.pa-bal-sesi-hcis-card:hover .pa-bal-sesi__panah {
    color: var(--hcis-brand-600, #4f46e5);
    transform: translateX(3px);
}

@media (max-width: 768px) {
    .pa-bal-page {
        padding: 0.85rem;
        gap: 1rem;
    }

    .pa-bal-header-actions {
        width: 100%;
        justify-content: space-between;
    }

    .pa-bal-daftar__sesi {
        flex-wrap: wrap;
        gap: 0.85rem;
        padding: 0.95rem 1.15rem;
    }

    .pa-bal-daftar__meta {
        width: 100%;
        padding-top: 0.5rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.85);
    }

    .pa-bal-sesi__panah {
        display: none;
    }
}
</style>
