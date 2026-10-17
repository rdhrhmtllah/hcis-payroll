<template>
    <div class="hcis-page hcis-stack hcis-stack--lg dashboard-container">
        <!-- 1. Header Halaman -->
        <HcisHeader
            title="Dashboard Penggajian"
            subtitle="Sistem Manajemen Kompensasi & Benefit Terintegrasi"
            kicker="HCIS PAYROLL"
            variant="surface"
            sentiment="neutral"
        >
            <template #actions>
                <div class="header-action-group">
                    <HcisButton variant="secondary" icon="bi-file-earmark-spreadsheet">
                        Ekspor Laporan
                    </HcisButton>
                    <HcisButton variant="primary" icon="bi-plus-circle">
                        Proses Payroll Baru
                    </HcisButton>
                </div>
            </template>
        </HcisHeader>

        <!-- 2. Banner Notifikasi -->
        <HcisAlert variant="info" title="Selamat Datang di HCIS Payroll">
            Struktur proyek baru telah disiapkan dengan standar HCIS Design System. Modul dan fitur payroll siap dikembangkan.
        </HcisAlert>

        <!-- 3. Ringkasan Statistik -->
        <HcisStatGroup cols="4" gap="md" layout="compact" appearance="crystal">
            <HcisStatCard
                label="Periode Berjalan"
                :value="stats.periode_aktif || 'Oktober 2026'"
                caption="Bulan penggajian aktif"
                icon="bi-calendar3"
                layout="compact"
                appearance="crystal"
            />
            <HcisStatCard
                label="Total Karyawan"
                :value="stats.total_karyawan || 0"
                caption="Karyawan aktif masuk payroll"
                icon="bi-people"
                layout="compact"
                appearance="crystal"
            />
            <HcisStatCard
                label="Total Pengeluaran"
                :value="formatRupiah(stats.total_payroll_berjalan)"
                caption="Gross payroll periode ini"
                icon="bi-cash-stack"
                layout="compact"
                appearance="crystal"
            />
            <HcisStatCard
                label="Status Siklus"
                value="DRAFT"
                caption="Menunggu kalkulasi final"
                icon="bi-clock-history"
                sentiment="warning"
                layout="compact"
                appearance="crystal"
            />
        </HcisStatGroup>

        <!-- 4. Grid Konten Utama -->
        <div class="dashboard-content-grid">
            <!-- Kolom Kiri: Riwayat / Batch Payroll -->
            <div class="dashboard-main-col">
                <HcisCard title="Batch Penggajian Terakhir" variant="default" :divider="true">
                    <template #actions>
                        <HcisButton size="sm" variant="ghost">Lihat Semua</HcisButton>
                    </template>

                    <div class="empty-state-wrap">
                        <HcisEmptyState
                            title="Belum Ada Data Penggajian"
                            description="Mulai hitung payroll untuk periode aktif melalui tombol 'Proses Payroll Baru'."
                            icon="bi-receipt"
                        >
                            <template #action>
                                <HcisButton size="sm" variant="primary" icon="bi-plus-lg">
                                    Mulai Batch Pertama
                                </HcisButton>
                            </template>
                        </HcisEmptyState>
                    </div>
                </HcisCard>
            </div>

            <!-- Kolom Kanan: Pintasan & Info -->
            <div class="dashboard-side-col">
                <!-- Aksi Cepat -->
                <HcisCard title="Aksi Cepat" variant="default" :divider="true">
                    <div class="action-card-list">
                        <button type="button" class="action-item-btn">
                            <div class="action-item-left">
                                <div class="action-item-icon action-item-icon--indigo">
                                    <i class="bi bi-sliders"></i>
                                </div>
                                <div>
                                    <div class="action-item-title">Komponen Gaji</div>
                                    <div class="action-item-desc">Atur tunjangan & potongan</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-slate-400"></i>
                        </button>

                        <button type="button" class="action-item-btn">
                            <div class="action-item-left">
                                <div class="action-item-icon action-item-icon--emerald">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <div class="action-item-title">Tarif PPh 21 (TER)</div>
                                    <div class="action-item-desc">Konfigurasi rumus perpajakan</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-slate-400"></i>
                        </button>

                        <button type="button" class="action-item-btn">
                            <div class="action-item-left">
                                <div class="action-item-icon action-item-icon--amber">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div>
                                    <div class="action-item-title">Akun Bank Perusahaan</div>
                                    <div class="action-item-desc">Disbursement & transfer gaji</div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-slate-400"></i>
                        </button>
                    </div>
                </HcisCard>

                <!-- Design System Card -->
                <HcisCard title="Katalog Design System" variant="default" :divider="true">
                    <p class="ds-card-desc">
                        Seluruh komponen antarmuka resmi HCIS siap digunakan untuk membangun fitur-fitur payroll baru.
                    </p>
                    <HcisButton
                        href="/design-system"
                        variant="secondary"
                        block
                        icon="bi-palette"
                    >
                        Buka /design-system
                    </HcisButton>
                </HcisCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    HcisHeader,
    HcisCard,
    HcisStatCard,
    HcisStatGroup,
    HcisButton,
    HcisEmptyState,
    HcisAlert,
} from '@/components/ui';

defineProps({
    title: { type: String, default: 'Dashboard Payroll' },
    stats: {
        type: Object,
        default: () => ({
            total_karyawan: 0,
            total_payroll_berjalan: 0,
            status_periode: 'DRAFT',
            periode_aktif: '',
        }),
    },
});

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka || 0);
}
</script>

<style scoped>
.dashboard-container {
    padding-bottom: 2rem;
}

.header-action-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.dashboard-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 992px) {
    .dashboard-content-grid {
        grid-template-columns: 1fr;
    }
}

.dashboard-side-col {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.empty-state-wrap {
    padding: 2.5rem 1rem;
}

.action-card-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-item-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}

.action-item-btn:hover {
    background: var(--hcis-surface-subtle, #f8fafc);
    border-color: var(--hcis-border, #cbd5e1);
}

.action-item-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.action-item-icon {
    width: 38px;
    height: 38px;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.action-item-icon--indigo {
    background: rgb(var(--hcis-indigo-50-rgb, 238 242 255));
    color: var(--hcis-brand-600, #4f46e5);
}

.action-item-icon--emerald {
    background: #ecfdf5;
    color: #059669;
}

.action-item-icon--amber {
    background: #fffbeb;
    color: #d97706;
}

.action-item-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-text, #0f172a);
    line-height: 1.25;
}

.action-item-desc {
    font-size: 0.75rem;
    color: var(--hcis-text-secondary, #64748b);
    margin-top: 2px;
}

.ds-card-desc {
    font-size: 0.875rem;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.5;
    margin-bottom: 1rem;
}
</style>
