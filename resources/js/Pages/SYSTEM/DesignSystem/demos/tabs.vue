<template>
    <!-- Section: Anatomy (Interactive Showcase with Live Controls) -->
    <div v-if="section === 'anatomy'" class="ds-tabs-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="sliders" class="ds-icon-brand" /> Live Playground & Indikator Meluncur
                </span>
                <div class="ds-controls-bar">
                    <span class="ds-card__badge">Interactive Demo</span>
                </div>
            </div>

            <div class="ds-card__body">
                <!-- Interactive Controls Bar -->
                <div class="ds-toolbar">
                    <div class="ds-toolbar__group">
                        <span class="ds-toolbar__label">Varian:</span>
                        <div class="ds-segmented-group">
                            <button
                                v-for="v in ['underline', 'header', 'segmented', 'pills', 'glow', 'enclosed']"
                                :key="v"
                                type="button"
                                class="ds-toolbar__btn"
                                :class="{ 'is-active': variant === v }"
                                @click="variant = v"
                            >
                                {{ v }}
                            </button>
                        </div>
                    </div>

                    <div class="ds-toolbar__group">
                        <span class="ds-toolbar__label">Ukuran:</span>
                        <div class="ds-segmented-group">
                            <button
                                v-for="s in ['sm', 'md', 'lg']"
                                :key="s"
                                type="button"
                                class="ds-toolbar__btn"
                                :class="{ 'is-active': size === s }"
                                @click="size = s"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Component Render -->
                <HcisTabs
                    v-model="aktif"
                    :tabs="tabs"
                    :variant="variant"
                    :size="size"
                >
                    <template #panel-antrian>
                        <div class="ds-panel">
                            <HcisIcon name="bell-fill" class="ds-panel__icon ds-icon-indigo" />
                            <span>Menampilkan <strong>12 pengajuan</strong> yang memerlukan persetujuan segera. Indikator meluncur (*sliding indicator*) otomatis menyesuaikan posisi dan ukuran tab.</span>
                        </div>
                    </template>
                    <template #panel-riwayat>
                        <div class="ds-panel">
                            <HcisIcon name="clock-history" class="ds-panel__icon ds-icon-emerald" />
                            <span>Riwayat seluruh pengajuan bulan ini yang telah diproses. Navigasi keyboard ARIA aktif (Panah Kiri/Kanan, Home, End).</span>
                        </div>
                    </template>
                    <template #panel-ditolak>
                        <div class="ds-panel">
                            <HcisIcon name="exclamation-triangle-fill" class="ds-panel__icon ds-icon-rose" />
                            <span>Ada <strong>3 pengajuan</strong> yang dikembalikan/ditolak untuk diperbaiki.</span>
                        </div>
                    </template>
                </HcisTabs>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <!-- Vertical Tabs Rail with Auto Tooltips -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="layout-sidebar-inset" class="ds-icon-brand" /> Rel Vertikal Samping dengan Tooltip Otomatis
                </span>
                <span class="ds-card__tag">orientation="vertical" collapsible</span>
            </div>
            <div class="ds-card__body">
                <p class="ds-note">
                    Coba lipat rel dengan tombol <strong>Kecilkan</strong> di bawah. Saat dilipat, mengarahkan kursor (*hover*) pada ikon akan menampilkan <strong>HcisTooltip</strong> otomatis dengan nama tab lengkap.
                </p>
                <HcisTabs
                    v-model="verticalTab"
                    orientation="vertical"
                    variant="underline"
                    collapsible
                    :tabs="[
                        { key: 'profil', label: 'Profil Karyawan', icon: 'person-fill' },
                        { key: 'keamanan', label: 'Keamanan & Sandi', icon: 'shield-lock-fill' },
                        { key: 'notifikasi', label: 'Setelan Notifikasi', icon: 'bell-fill' },
                        { key: 'gaji', label: 'Informasi Penggajian', icon: 'wallet2' },
                    ]"
                >
                    <template #panel-profil>
                        <div class="ds-panel">
                            <div>
                                <strong>Kelola Profil Karyawan</strong>
                                <p class="ds-panel__desc">Ubah foto profil, nama lengkap, NIP, dan informasi kontak pribadi.</p>
                            </div>
                        </div>
                    </template>
                    <template #panel-keamanan>
                        <div class="ds-panel">
                            <div>
                                <strong>Pengaturan Kata Sandi & 2FA</strong>
                                <p class="ds-panel__desc">Aktifkan otentikasi dua langkah dan ubah kata sandi secara berkala.</p>
                            </div>
                        </div>
                    </template>
                    <template #panel-notifikasi>
                        <div class="ds-panel">
                            <div>
                                <strong>Saluran Notifikasi Email & WA</strong>
                                <p class="ds-panel__desc">Atur frekuensi pemberitahuan pengajuan dan pesan sistem.</p>
                            </div>
                        </div>
                    </template>
                    <template #panel-gaji>
                        <div class="ds-panel">
                            <div>
                                <strong>Slip Gaji & Rincian Tunjangan</strong>
                                <p class="ds-panel__desc">Unduh slip gaji bulanan versi PDF terenskripsi.</p>
                            </div>
                        </div>
                    </template>
                </HcisTabs>
            </div>
        </div>

        <!-- Scrollable Horizontal Tabs Navigation -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="arrows-expand" class="ds-icon-brand" /> Horizontal Tab Overflow Scroll & Tombol Navigasi
                </span>
                <span class="ds-card__tag">Auto Scroll Control</span>
            </div>
            <div class="ds-card__body">
                <p class="ds-note">
                    Saat jumlah tab melebihi lebar layar, tombol panah scroll otomatis muncul di sisi kiri/kanan. Gulirkan mouse wheel di atas tab untuk menggeser rel tab secara langsung.
                </p>
                <HcisTabs
                    v-model="banyakTab"
                    variant="segmented"
                    :tabs="[
                        { key: 't1', label: 'Ringkasan Umum', icon: 'grid' },
                        { key: 't2', label: 'Manajemen Pegawai', icon: 'people' },
                        { key: 't3', label: 'Struktur Organisasi', icon: 'diagram-3' },
                        { key: 't4', label: 'Kehadiran & Shift', icon: 'calendar-check' },
                        { key: 't5', label: 'Cuti & Izin Kerja', icon: 'briefcase' },
                        { key: 't6', label: 'Penggajian & PPh21', icon: 'cash-stack' },
                        { key: 't7', label: 'Kinerja & KPI Audit', icon: 'graph-up-arrow' },
                        { key: 't8', label: 'Pelatihan & Sertifikasi', icon: 'award' },
                    ]"
                >
                    <template #panel-t1>
                        <div class="ds-panel">Ringkasan modul HCIS terpadu.</div>
                    </template>
                    <template #panel-t2>
                        <div class="ds-panel">Kelola data induk 1.250 pegawai aktif.</div>
                    </template>
                    <template #panel-t3>
                        <div class="ds-panel">Bagan struktur organisasi dan posisi unit kerja.</div>
                    </template>
                    <template #panel-t4>
                        <div class="ds-panel">Rekap log absensi mesin fingerprint dan lokasi GPS mobile.</div>
                    </template>
                </HcisTabs>
            </div>
        </div>

        <!-- Nested 2-Tier Tabs -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="layers-half" class="ds-icon-brand" /> Nested 2-Tier Tabs (Tab Outer Segmented ➔ Tab Inner Vertical Pills)
                </span>
                <span class="ds-card__tag">Nested 2-Tier Architecture</span>
            </div>
            <div class="ds-card__body">
                <!-- Tier 1: Horizontal Outer Tab -->
                <HcisTabs
                    v-model="outerTab"
                    variant="segmented"
                    :tabs="[
                        { key: 'menu', label: 'Kelola Menu', icon: 'list-ul' },
                        { key: 'approval', label: 'Approval Matrix', icon: 'diagram-3' },
                        { key: 'membership', label: 'Keanggotaan', icon: 'people' },
                    ]"
                >
                    <template #panel-menu>
                        <div class="ds-panel">Daftar hirarki menu aplikasi & struktur navigasi utama.</div>
                    </template>

                    <template #panel-approval>
                        <!-- Tier 2: Vertical Inner Tab inside Approval Matrix -->
                        <div class="ds-nested-box">
                            <HcisTabs
                                v-model="innerVerticalTab"
                                orientation="vertical"
                                variant="pills"
                                collapsible
                                :tabs="[
                                    { key: 'config', label: 'Konfigurasi Alur', icon: 'sliders' },
                                    { key: 'rules', label: 'Aturan Batas Nilai', icon: 'currency-dollar' },
                                    { key: 'delegation', label: 'Delegasi Otorisasi', icon: 'person-gear' },
                                ]"
                            >
                                <template #panel-config>
                                    <div class="ds-panel">Tentukan hirarki pejabat penyetuju dan urutan persetujuan.</div>
                                </template>
                                <template #panel-rules>
                                    <div class="ds-panel">Batas nominal maksimal yang memerlukan persetujuan Direksi.</div>
                                </template>
                                <template #panel-delegation>
                                    <div class="ds-panel">Penunjukan pejabat sementara saat pejabat utama sedang cuti.</div>
                                </template>
                            </HcisTabs>
                        </div>
                    </template>

                    <template #panel-membership>
                        <div class="ds-panel">Keanggotaan grup otorisasi & penugasan peran pengelola.</div>
                    </template>
                </HcisTabs>
            </div>
        </div>

        <!-- Header Integrated Tabs Showcase -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="window-sidebar" class="ds-icon-brand" /> Tab Ramping Terintegrasi di Dasar Header (variant="header")
                </span>
                <span class="ds-card__tag">variant="header" inside &lt;template #tabs&gt;</span>
            </div>
            <div class="ds-card__body">
                <p class="ds-note">
                    Tab mode ramping (font 13px, tanpa kotak balok) yang menempel rata pada garis batas bawah header. Warna teks dan garis indikator aktif otomatis menyesuaikan apakah header bertema gelap (<em>shero, brand, slate</em>) atau putih bersih (<em>surface</em>).
                </p>
                <div class="ds-stack-col" style="gap: 1.25rem">
                    <!-- Shero Header with Header Tabs -->
                    <div>
                        <span class="ds-demo-label">1. Pada Header Gradien Hero (Shero):</span>
                        <HcisHeader
                            title="Master SOP & Kebijakan"
                            subtitle="Pusat pustaka dokumen regulasi dan standar prosedur operasional."
                            icon="folder-symlink-fill"
                            variant="shero"
                        >
                            <template #tabs>
                                <HcisTabs
                                    v-model="headerTabHero"
                                    variant="header"
                                    :tabs="[
                                        { key: 'dokumen', label: 'Dokumen Aktif', icon: 'file-earmark-text' },
                                        { key: 'review', label: 'Menunggu Review', icon: 'clock-history', badge: 4, badgeVariant: 'warning' },
                                        { key: 'arsip', label: 'Arsip SOP', icon: 'archive' },
                                        { key: 'pengaturan', label: 'Pengaturan Alur', icon: 'gear' },
                                    ]"
                                />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Surface Header with Header Tabs -->
                    <div>
                        <span class="ds-demo-label">2. Pada Header Surface Putih (Surface):</span>
                        <HcisHeader
                            title="Manajemen Hak Akses Karyawan"
                            subtitle="Konfigurasi izin modul, wewenang persetujuan, dan distribusi peran."
                            icon="shield-lock-fill"
                            variant="surface"
                        >
                            <template #tabs>
                                <HcisTabs
                                    v-model="headerTabSurface"
                                    variant="header"
                                    :tabs="[
                                        { key: 'peran', label: 'Peran & Jabatan', icon: 'person-badge' },
                                        { key: 'matriks', label: 'Matriks Wewenang', icon: 'grid-3x3-gap' },
                                        { key: 'distribusi', label: 'Distribusi Akses', icon: 'diagram-3' },
                                    ]"
                                />
                            </template>
                        </HcisHeader>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-stack-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="arrows-expand" class="ds-icon-brand" /> Lebar Penuh (Block / Justified)
                </span>
                <span class="ds-card__tag">block</span>
            </div>
            <div class="ds-card__body">
                <HcisTabs v-model="aktif" :tabs="tabs" variant="segmented" block />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisTabs, HcisHeader, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const aktif = ref('antrian');
const variant = ref('segmented');
const size = ref('md');
const verticalTab = ref('profil');
const banyakTab = ref('t1');
const outerTab = ref('approval');
const innerVerticalTab = ref('config');
const headerTabHero = ref('dokumen');
const headerTabSurface = ref('peran');

const tabs = [
    { key: 'antrian', label: 'Antrean Persetujuan', icon: 'bell-fill', badge: 12, badgeVariant: 'danger' },
    { key: 'riwayat', label: 'Riwayat Selesai', icon: 'clock-history' },
    { key: 'ditolak', label: 'Dikembalikan', icon: 'exclamation-triangle-fill', badge: 3, badgeVariant: 'warning' },
];
</script>

<style scoped>
.ds-tabs-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-icon-brand { color: var(--hcis-brand-600); }
.ds-icon-indigo { color: var(--hcis-brand-600); }
.ds-icon-emerald { color: var(--hcis-success-500); }
.ds-icon-rose { color: var(--hcis-danger-500); }

.ds-card__badge,
.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
}

.ds-card__badge {
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-600);
}

.ds-card__tag {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-family: monospace;
}

.ds-card__body {
    padding: 1.25rem;
}

.ds-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1.25rem;
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-lg, 10px);
}

.ds-toolbar__group {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.ds-toolbar__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-muted);
}

.ds-segmented-group {
    display: inline-flex;
    padding: 2px;
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-md, 8px);
    gap: 2px;
}

.ds-toolbar__btn {
    border: 0;
    background: transparent;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
    border-radius: calc(var(--hcis-radius-md, 8px) - 2px);
    cursor: pointer;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 150ms;
    transition-timing-function: ease;
}

.ds-toolbar__btn:hover {
    color: var(--hcis-text-strong);
    background: var(--hcis-surface-soft);
}

.ds-toolbar__btn.is-active {
    background: var(--hcis-brand-600);
    color: var(--hcis-white);
    font-weight: 700;
    box-shadow: 0 1px 3px rgb(var(--hcis-brand-600-rgb) / 0.3);
}

.ds-note {
    font-size: 0.775rem;
    color: var(--hcis-text-muted);
    margin-bottom: 0.875rem;
}

.ds-panel {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: 10px;
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    font-size: 0.875rem;
    color: var(--hcis-text);
}

.ds-panel__icon {
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.ds-panel__desc {
    font-size: 0.8125rem;
    color: var(--hcis-text-muted);
    margin-top: 0.25rem;
    margin-bottom: 0;
}

.ds-nested-box {
    padding: 0.875rem;
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl, 12px);
    margin-top: 0.5rem;
}
</style>
