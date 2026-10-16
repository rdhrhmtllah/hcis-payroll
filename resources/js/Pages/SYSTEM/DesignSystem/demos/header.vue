<template>
    <!-- Section: Anatomy -->
    <div v-if="section === 'anatomy'" class="ds-header-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <i class="bi bi-window-sidebar" /> HcisHeader (Signature Enterprise Suite & Full Flexible Slots)
                </span>
                <span class="ds-card__badge">Full Flexible Slots</span>
            </div>

            <div class="ds-card__body">
                <HcisHeader
                    kicker="LMS · GOVERNANCE SOP"
                    title="Library Konten & Pedoman SOP"
                    subtitle="Pusat pustaka berkas, video, dan dokumen rujukan resmi untuk penyusunan prosedur operasional."
                    icon="folder-symlink-fill"
                    badge="Modul Resmi"
                    badge-variant="brand"
                >
                    <template #breadcrumb>
                        <HcisBreadcrumb
                            :items="[
                                { label: 'Modul SOP', href: '#' },
                                { label: 'Library Konten' }
                            ]"
                        />
                    </template>

                    <template #actions>
                        <HcisButton
                            label="+ Buat Draf SOP"
                            variant="primary"
                            size="sm"
                        />
                        <HcisButton
                            label="Builder SOP"
                            variant="secondary"
                            size="sm"
                            leading-icon="tools"
                        />
                        <HcisButton
                            label="Kebijakan SOP"
                            variant="secondary"
                            size="sm"
                            leading-icon="shield-check"
                        />
                    </template>

                    <template #console>
                        <div class="ds-header-stats-grid">
                            <HcisStatCard
                                label="Total Kategori SOP"
                                value="14 Kategori"
                                icon="folder2-open"
                                sentiment="info"
                                caption="Termasuk modul operasional"
                            />
                            <HcisStatCard
                                label="Dokumen SOP Terbit"
                                value="128 Dokumen"
                                icon="file-earmark-check-fill"
                                sentiment="success"
                                caption="Diakses 1,420 karyawan"
                            />
                            <HcisStatCard
                                label="Tinjauan Evaluasi"
                                value="3 Dokumen"
                                icon="hourglass-split"
                                sentiment="warning"
                                caption="Dalam persetujuan manajer"
                            />
                        </div>
                    </template>
                </HcisHeader>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <!-- Part A: Sentiment Harmonies -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-palette-fill" /> Rona Sentimen Modul (Sentiment-Adaptive Aurora)</span>
                <span class="ds-card__tag">sentiment="brand | purple | teal | warning | success"</span>
            </div>
            <div class="ds-card__body">
                <div class="ds-stack-col" style="gap: 1.75rem">
                    <!-- Brand -->
                    <div>
                        <span class="ds-demo-label">1. Sentiment Brand (Signature Indigo — Modul Inti & Ringkasan Eksekutif):</span>
                        <HcisHeader
                            kicker="EMPLOYEE SELF SERVICE"
                            title="Portal Layanan Mandiri Karyawan (ESS)"
                            subtitle="Akses cepat pengajuan cuti, presensi digital, riwayat pelatihan, dan slip gaji elektronik."
                            icon="shield-shaded"
                            badge="Aktif"
                            sentiment="brand"
                        >
                            <template #actions>
                                <HcisButton label="Ajukan Cuti" variant="primary" size="sm" leading-icon="calendar2-plus" />
                                <HcisButton label="Riwayat Presensi" variant="secondary" size="sm" leading-icon="clock-history" />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Purple -->
                    <div>
                        <span class="ds-demo-label">2. Sentiment Purple (Amethyst Violet — Kuis SOP & Ujian Sertifikasi):</span>
                        <HcisHeader
                            kicker="LMS LEARNING & COMPLIANCE"
                            title="Kuis Evaluasi Pemahaman Standar Operasional"
                            subtitle="Uji pemahaman prosedur kerja berkala untuk menjaga kepatuhan dan keselamatan kerja seluruh staf."
                            icon="patch-question"
                            badge="Kuis Wajib"
                            sentiment="purple"
                        >
                            <template #actions>
                                <HcisButton label="Mulai Kuis Sekarang" variant="primary" size="sm" leading-icon="play-fill" />
                            </template>
                            <template #tabs>
                                <HcisTabs
                                    v-model="activeTab"
                                    variant="header"
                                    :tabs="[
                                        { key: 'soal', label: 'Daftar Soal', icon: 'list-check' },
                                        { key: 'hasil', label: 'Hasil Kelulusan', icon: 'award' },
                                        { key: 'riwayat', label: 'Riwayat Ujian', icon: 'clock-history' },
                                    ]"
                                />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Teal -->
                    <div>
                        <span class="ds-demo-label">3. Sentiment Teal (Mint Toska — Pustaka Dokumen & Regulasi):</span>
                        <HcisHeader
                            kicker="GOVERNANCE & POLICY"
                            title="Pustaka Kebijakan & Regulasi Resmi Perusahaan"
                            subtitle="Koleksi instruksi kerja, surat keputusan direksi, serta matriks kepatuhan operasional."
                            icon="journal-bookmark"
                            badge="Pustaka Resmi"
                            sentiment="teal"
                        >
                            <template #actions>
                                <HcisButton label="Unduh PDF" variant="secondary" size="sm" leading-icon="download" />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Warning -->
                    <div>
                        <span class="ds-demo-label">4. Sentiment Warning (Amber Gold — Gate Absensi & Antrean Approval):</span>
                        <HcisHeader
                            kicker="AUDIT & VERIFIKASI"
                            title="Pemeriksaan Gate Absensi & Izin Tertunda"
                            subtitle="Ditemukan 5 pengajuan lembur dan 2 anomali presensi yang membutuhkan konfirmasi atasan."
                            icon="exclamation-triangle"
                            badge="Perlu Konfirmasi"
                            sentiment="warning"
                        >
                            <template #actions>
                                <HcisButton label="Tinjau Sekarang" variant="primary" size="sm" leading-icon="eye" />
                            </template>
                        </HcisHeader>
                    </div>
                </div>
            </div>
        </div>

        <!-- Part B: Layouts -->
        <div class="ds-card" style="margin-top: 1.5rem;">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-layout-wtf" /> Ragam Layout Header</span>
                <span class="ds-card__tag">layout="compact | centered | stacked | inline | minimal"</span>
            </div>
            <div class="ds-card__body">
                <div class="ds-stack-col" style="gap: 1.75rem">
                    <!-- Layout: Compact -->
                    <div>
                        <span class="ds-demo-label">1. Layout Compact (Padat Ringkas untuk Tabel & Sub-Halaman):</span>
                        <HcisHeader
                            kicker="AUDIT TRAIL"
                            title="Daftar Log Riwayat Kehadiran Pegawai"
                            subtitle="Menampilkan data absensi real-time seluruh cabang."
                            icon="card-checklist"
                            layout="compact"
                            badge="1,420 Baris"
                        >
                            <template #actions>
                                <HcisButton label="Filter" variant="secondary" size="xs" leading-icon="funnel" />
                                <HcisButton label="Unduh CSV" variant="secondary" size="xs" leading-icon="download" />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Layout: Centered -->
                    <div>
                        <span class="ds-demo-label">2. Layout Centered (Pusat Sorotan Onboarding / Portal Hero):</span>
                        <HcisHeader
                            kicker="HCIS ENTERPRISE SUITE"
                            title="Selamat Datang di HCIS Enterprise Suite"
                            subtitle="Sistem manajemen sumber daya manusia terintegrasi untuk produktivitas, transparansi, dan efisiensi organisasi."
                            icon="stars"
                            layout="centered"
                            size="lg"
                            badge="Versi 2.0"
                        >
                            <template #actions>
                                <HcisButton label="Mulai Tur Panduan" variant="primary" size="md" leading-icon="compass" />
                                <HcisButton label="Dokumentasi Sistem" variant="secondary" size="md" leading-icon="book" />
                            </template>
                        </HcisHeader>
                    </div>

                    <!-- Layout: Minimal -->
                    <div>
                        <span class="ds-demo-label">3. Layout Minimal (Tanpa Frame Card — Menyatu Mulus dengan Halaman):</span>
                        <HcisHeader
                            kicker="PREFERENSI"
                            title="Pengaturan Notifikasi & Akun"
                            subtitle="Sesuaikan alamat email pengingat dan saluran push notification WhatsApp."
                            icon="gear-fill"
                            layout="minimal"
                        >
                            <template #actions>
                                <HcisButton label="Reset Default" variant="secondary" size="sm" leading-icon="arrow-counterclockwise" />
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
                <span class="ds-card__title"><i class="bi bi-hourglass-split" /> State Memuat & Truncate Teks</span>
                <span class="ds-card__tag">loading · truncate · truncate-subtitle</span>
            </div>
            <div class="ds-card__body">
                <div class="ds-stack-col" style="gap: 1.5rem">
                    <div>
                        <span class="ds-demo-label">1. Skeleton Header Otomatis Saat Fetching Data (loading="true"):</span>
                        <HcisHeader
                            loading
                            title="Memuat data..."
                            subtitle="Sedang mengunduh konfigurasi modul..."
                            icon="folder-symlink"
                        />
                    </div>

                    <div>
                        <span class="ds-demo-label">2. Truncate Judul Panjang & Line-Clamp Subjudul (truncate="true" + lines="2"):</span>
                        <HcisHeader
                            kicker="MANAJEMEN KINERJA PERUSAHAAN 2026"
                            title="Laporan Evaluasi Kinerja Eksekutif Tahunan Seluruh Divisi dan Entitas Bisnis Regional Nusantara Barat dan Timur Terpadu"
                            subtitle="Berikut adalah laporan rekapitulasi penilaian kinerja tahunan periode 2025/2026 yang mencakup seluruh departemen operasional, sumber daya manusia, keuangan, logistik, pengadaan barang, serta evaluasi kepatuhan SOP di bawah pengawasan direksi eksekutif."
                            icon="graph-up"
                            badge="Over-Length Demo"
                            truncate
                            :lines="2"
                        >
                            <template #actions>
                                <HcisButton label="Lihat Rincian" variant="primary" size="sm" />
                            </template>
                        </HcisHeader>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import {
    HcisHeader,
    HcisButton,
    HcisBreadcrumb,
    HcisStatCard,
    HcisTabs
} from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const activeTab = ref('dokumen');
</script>

<style scoped>
.ds-header-demo,
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

.ds-card__title i {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__badge,
.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
}

.ds-card__badge {
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__tag {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-family: monospace;
}

.ds-card__body {
    padding: 1.25rem;
}

.ds-demo-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin-bottom: 0.5rem;
    display: block;
}

.ds-header-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.875rem;
    width: 100%;
}

.ds-stack-col {
    display: flex;
    flex-direction: column;
}
</style>
