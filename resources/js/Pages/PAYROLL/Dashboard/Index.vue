<template>
    <AppShell>
        <div class="space-y-6">
            <!-- Header Halaman -->
            <HcisHeader
                title="Dashboard Penggajian (Payroll)"
                subtitle="Sistem Manajemen Kompensasi & Benefit Terintegrasi"
                eyebrow="HCIS PAYROLL"
            >
                <template #actions>
                    <HcisButton variant="secondary" icon="bi-file-earmark-spreadsheet">
                        Ekspor Laporan
                    </HcisButton>
                    <HcisButton variant="primary" icon="bi-plus-circle">
                        Proses Payroll Baru
                    </HcisButton>
                </template>
            </HcisHeader>

            <!-- Banner Notifikasi -->
            <HcisAlert variant="info" title="Selamat Datang di HCIS Payroll">
                Struktur proyek baru telah disiapkan dengan standar HCIS Design System. Modul dan fitur payroll siap dikembangkan.
            </HcisAlert>

            <!-- Ringkasan Statistik -->
            <HcisStatGroup columns="4">
                <HcisStatCard
                    title="Periode Berjalan"
                    :value="stats.periode_aktif || '-'"
                    hint="Bulan penggajian saat ini"
                    icon="bi-calendar3"
                />
                <HcisStatCard
                    title="Total Karyawan"
                    :value="stats.total_karyawan"
                    hint="Karyawan aktif masuk payroll"
                    icon="bi-people"
                />
                <HcisStatCard
                    title="Total Pengeluaran"
                    :value="formatRupiah(stats.total_payroll_berjalan)"
                    hint="Gross payroll periode ini"
                    icon="bi-cash-stack"
                />
                <HcisStatCard
                    title="Status Penggajian"
                    value="DRAFT"
                    hint="Menunggu kalkulasi final"
                    icon="bi-clock-history"
                >
                    <template #badge>
                        <HcisBadge variant="warning">Draft</HcisBadge>
                    </template>
                </HcisStatCard>
            </HcisStatGroup>

            <!-- Card Konten Utama -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kolom Kiri: Riwayat / Batch Payroll -->
                <div class="lg:col-span-2">
                    <HcisCard title="Batch Penggajian Terakhir">
                        <template #actions>
                            <HcisButton size="sm" variant="ghost">Lihat Semua</HcisButton>
                        </template>

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
                    </HcisCard>
                </div>

                <!-- Kolom Kanan: Pintasan & Agenda -->
                <div class="space-y-6">
                    <HcisCard title="Aksi Cepat">
                        <div class="space-y-2">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition text-left"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                        <i class="bi bi-sliders"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-800">Komponen Gaji</div>
                                        <div class="text-xs text-slate-500">Atur tunjangan & potongan</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-slate-400 text-sm"></i>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition text-left"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-800">Tarif PPh 21 (TER)</div>
                                        <div class="text-xs text-slate-500">Konfigurasi rumus perpajakan</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-slate-400 text-sm"></i>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition text-left"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                        <i class="bi bi-bank"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-800">Akun Bank Perusahaan</div>
                                        <div class="text-xs text-slate-500">Disbursement & transfer gaji</div>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-slate-400 text-sm"></i>
                            </button>
                        </div>
                    </HcisCard>

                    <HcisCard title="Katalog Design System">
                        <p class="text-sm text-slate-600 mb-3">
                            Semua komponen UI HCIS siap dipakai untuk membangun fitur baru payroll.
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
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import {
    HcisHeader,
    HcisCard,
    HcisStatCard,
    HcisStatGroup,
    HcisButton,
    HcisBadge,
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
