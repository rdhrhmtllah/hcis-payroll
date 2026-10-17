<template>
    <div class="ap-payroll-page">
        <!-- ══ 1. HEADER HALAMAN UTAMA (HCIS HEADER) ══ -->
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="md"
            icon="cash-stack"
            kicker="HCIS PAYROLL · SISTEM PENGGAJIAN"
            title="Dashboard Penggajian"
            subtitle="Ringkasan siklus payroll aktif, pemantauan pembayaran gaji, dan tata kelola kompensasi karyawan."
            class="mb-3"
        >
            <template #badge>
                <HcisBadge
                    label="Modul Payroll Aktif"
                    variant="brand"
                    appearance="subtle"
                    size="sm"
                    dot
                />
            </template>
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="sm"
                        leading-icon="file-earmark-spreadsheet"
                        label="Ekspor Laporan"
                    />
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="plus-circle"
                        label="Proses Payroll Baru"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. BARIS METRIK RINGKAS (HCIS STAT CARDS) ══ -->
        <div class="ap-stats-grid mb-3">
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="brand"
                icon="calendar3"
                label="Periode Berjalan"
                :value="stats.periode_aktif || 'Oktober 2026'"
                caption="Bulan penggajian aktif"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="teal"
                icon="people-fill"
                label="Total Karyawan"
                :value="stats.total_karyawan || 0"
                caption="Karyawan aktif masuk payroll"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="purple"
                icon="cash-stack"
                label="Total Pengeluaran"
                :value="formatRupiah(stats.total_payroll_berjalan)"
                caption="Gross payroll periode ini"
            />
            <HcisStatCard
                layout="compact"
                appearance="crystal"
                sentiment="warning"
                icon="clock-history"
                label="Status Siklus"
                value="DRAFT"
                caption="Menunggu kalkulasi final"
            />
        </div>

        <!-- ══ 3. KARTU TATA KELOLA & PANDUAN SIKLUS (GOVERNANCE CARD) ══ -->
        <div class="ap-governance-card mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 p-3">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="ap-kicker">Siklus Penggajian · {{ stats.periode_aktif || 'Oktober 2026' }}</span>
                        <HcisBadge
                            label="Status: DRAFT"
                            variant="warning"
                            appearance="subtle"
                            size="xs"
                        />
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Tahapan Operasional Payroll</h5>
                    <p class="ap-kecil text-muted mb-0">
                        Alur pemrosesan gaji bulanan terintegrasi mulai dari verifikasi absensi &amp; lembur, kalkulasi komponen pendapatan, pemotongan pajak PPh 21 TER, hingga penerbitan slip dan transfer bank.
                    </p>
                </div>

                <div class="flex-shrink-0">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="palette"
                        label="Katalog Design System"
                        href="/design-system"
                    />
                </div>
            </div>

            <!-- Grid Langkah Operasional -->
            <div class="ap-guide-grid">
                <div class="ap-guide-item">
                    <div class="ap-guide-icon ap-guide-icon--active">
                        <HcisIcon name="sliders" size="sm" />
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold text-dark ap-kecil">1. Komponen &amp; Tarif</div>
                        <div class="text-muted ap-xs">Atur gaji pokok, tunjangan, &amp; PTKP</div>
                    </div>
                </div>

                <div class="ap-guide-item">
                    <div class="ap-guide-icon ap-guide-icon--pending">
                        <HcisIcon name="calculator" size="sm" />
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold text-dark ap-kecil">2. Kalkulasi Batch</div>
                        <div class="text-muted ap-xs">Hitung take-home pay &amp; BPJS</div>
                    </div>
                </div>

                <div class="ap-guide-item">
                    <div class="ap-guide-icon ap-guide-icon--pending">
                        <HcisIcon name="shield-check" size="sm" />
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold text-dark ap-kecil">3. Validasi Pajak PPh 21</div>
                        <div class="text-muted ap-xs">Penerapan tarif efektif bulanan</div>
                    </div>
                </div>

                <div class="ap-guide-item">
                    <div class="ap-guide-icon ap-guide-icon--pending">
                        <HcisIcon name="bank" size="sm" />
                    </div>
                    <div class="min-w-0">
                        <div class="fw-semibold text-dark ap-kecil">4. Disbursement &amp; Slip</div>
                        <div class="text-muted ap-xs">Transfer bank &amp; rilis e-slip gaji</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ 4. DAFTAR BATCH PENGGAJIAN DENGAN FILTER & TABEL RESMI ══ -->
        <div class="ap-content-card">
            <div class="ap-card-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="ap-kicker">Riwayat Pemrosesan</span>
                    <h6 class="fw-bold text-dark mb-0">Daftar Batch Penggajian</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        label="Lihat Semua Riwayat"
                    />
                </div>
            </div>

            <div class="p-3">
                <!-- HcisFilterBar Mandiri Sesuai Standar Seksi 5 -->
                <HcisFilterBar
                    :search="kataKunci"
                    search-placeholder="Cari nomor batch atau periode…"
                    :quick-chips="chipsStatus"
                    :active-chip-value="statusAktif"
                    variant="flat"
                    size="sm"
                    class="mb-3 ap-filterbar-box"
                    @update:search="(v) => (kataKunci = v)"
                    @chip-click="onChipClick"
                />

                <!-- Tabel Data Terstruktur (Pola Resmi Bagian 4 Standar HCIS) -->
                <div class="ap-kemampuan-table-wrap">
                    <table class="ap-kemampuan-table">
                        <thead>
                            <tr>
                                <th>Nomor Batch</th>
                                <th>Periode Gaji</th>
                                <th>Tanggal Cut-off</th>
                                <th class="text-center">Karyawan</th>
                                <th class="text-end">Total Bruto</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <HcisEmptyState
                                        icon="receipt"
                                        title="Belum Ada Batch Penggajian"
                                        description="Siklus penggajian periode ini belum dijalankan. Mulai hitung payroll melalui tombol di bawah."
                                    >
                                        <template #action>
                                            <HcisButton
                                                size="sm"
                                                variant="primary"
                                                leading-icon="plus-lg"
                                                label="Mulai Batch Pertama"
                                            />
                                        </template>
                                    </HcisEmptyState>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import {
    HcisHeader,
    HcisStatCard,
    HcisButton,
    HcisBadge,
    HcisIcon,
    HcisFilterBar,
    HcisEmptyState,
} from '@/components/ui';

defineProps({
    title: { type: String, default: 'Dashboard Payroll' },
    stats: {
        type: Object,
        default: () => ({
            total_karyawan: 0,
            total_payroll_berjalan: 0,
            status_periode: 'DRAFT',
            periode_aktif: 'Oktober 2026',
        }),
    },
});

const kataKunci = ref('');
const statusAktif = ref('semua');

const chipsStatus = [
    { label: 'Semua Status', value: 'semua' },
    { label: 'Draft', value: 'draft' },
    { label: 'Menunggu Approval', value: 'approval' },
    { label: 'Diproses', value: 'proses' },
    { label: 'Selesai', value: 'selesai' },
];

function onChipClick(chip) {
    statusAktif.value = chip.value;
}

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka || 0);
}
</script>

<style scoped>
.ap-payroll-page {
    width: 100%;
    padding-bottom: 2rem;
}

/* ══ 1. STATISTIK GRID (SESUAI TABEL TELADAN HCIS) ══ */
.ap-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

/* ══ 2. GOVERNANCE BANNER CARD ══ */
.ap-governance-card {
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.ap-guide-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem 1rem 1rem 1rem;
    background: #f8fafc;
    border-top: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

.ap-guide-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #ffffff;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

.ap-guide-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
    flex-shrink: 0;
}

.ap-guide-icon--active {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

.ap-guide-icon--pending {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

/* ══ 3. CONTENT CARD & TABLE ══ */
.ap-content-card {
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.ap-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.7);
}

.ap-filterbar-box {
    background: #ffffff;
    border-radius: var(--hcis-radius-lg, 10px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
}

/* Pola Tabel Standar HCIS (Wajib Bagian 4) */
.ap-kemampuan-table-wrap {
    overflow-x: auto;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.ap-kemampuan-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.ap-kemampuan-table th {
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    color: var(--hcis-text-secondary, #64748b);
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.ap-kemampuan-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    vertical-align: middle;
}

.ap-kemampuan-table tr:last-child td {
    border-bottom: none;
}

.ap-kemampuan-table tr:hover td {
    background-color: var(--hcis-surface-subtle, #f8fafc);
}

/* Tipografi & Spacing Helper */
.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-600, #0284c7);
}

.ap-kecil {
    font-size: 0.75rem;
    line-height: 1.45;
}

.ap-xs {
    font-size: 0.6875rem;
    line-height: 1.35;
}

.min-w-0 {
    min-width: 0;
}
</style>
