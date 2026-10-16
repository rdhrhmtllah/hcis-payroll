<template>
    <Head title="Panduan UX & Feedback — HCIS Design System" />

    <div class="hcis-page hcis-stack ds-guidelines-root">
        <!-- Top Portal Navigation -->
        <DsPortalNav />

        <!-- Header -->
        <HcisHeader
            title="Panduan Interaksi, Logika & Feedback (UX Recipes)"
            subtitle="Pedoman keputusan UI/UX resmi HCIS: penanganan error backend, matriks umpan balik notifikasi, loading state, dan aksesibilitas."
        />

        <!-- SEKSI 1: MATRIKS FEEDBACK & NOTIFIKASI -->
        <HcisCard title="1. Matriks Keputusan Notifikasi & Umpan Balik (Feedback Matrix)" description="Kapan menggunakan Alert vs Toast Message vs Dialog vs Banner." divider>
            <div class="ds-matrix-grid">
                <!-- Card 1: HcisAlert -->
                <div class="ds-matrix-card">
                    <div class="ds-matrix-card__header">
                        <HcisBadge label="Inline Persistent" variant="info" size="sm" />
                        <h4 class="ds-matrix-card__title">HcisAlert</h4>
                    </div>
                    <p class="ds-matrix-card__desc">
                        Gunakan untuk informasi penting yang <strong>harus terus terlihat</strong> di dalam halaman/formulir sebelum pengguna bertindak.
                    </p>
                    <div class="ds-matrix-card__example">
                        <HcisAlert variant="warning" title="Batas Waktu Pengisian" message="Formulir evaluasi ditutup 25 Agustus 2026." />
                    </div>
                    <div class="ds-matrix-card__rule">
                        <HcisIcon name="check-circle" size="xs" style="color: var(--hcis-success-600);" />
                        <span>Cocok untuk: peringatan aturan bisnis, petunjuk prasyarat, error level formulir.</span>
                    </div>
                </div>

                <!-- Card 2: hcisMessage (Toast) -->
                <div class="ds-matrix-card">
                    <div class="ds-matrix-card__header">
                        <HcisBadge label="Floating Ephemeral" variant="success" size="sm" />
                        <h4 class="ds-matrix-card__title">hcisMessage (Toast)</h4>
                    </div>
                    <p class="ds-matrix-card__desc">
                        Gunakan untuk <strong>konfirmasi cepat</strong> setelah aksi asinkron berhasil/gagal. Otomatis hilang dalam 3-5 detik.
                    </p>
                    <div class="ds-matrix-card__example" style="text-align: center;">
                        <HcisButton label="Uji Toast Success" variant="secondary" size="sm" icon="bell" @click="triggerToast" />
                    </div>
                    <div class="ds-matrix-card__rule">
                        <HcisIcon name="check-circle" size="xs" style="color: var(--hcis-success-600);" />
                        <span>Cocok untuk: "Data berhasil disimpan", "File berhasil diunggah", "Tautan disalin".</span>
                    </div>
                </div>

                <!-- Card 3: HcisDialog (Modal) -->
                <div class="ds-matrix-card">
                    <div class="ds-matrix-card__header">
                        <HcisBadge label="Blocking Modal" variant="danger" size="sm" />
                        <h4 class="ds-matrix-card__title">HcisDialog</h4>
                    </div>
                    <p class="ds-matrix-card__desc">
                        Gunakan saat pengguna <strong>wajib mengambil keputusan</strong> yang tidak bisa dibatalkan atau berisiko tinggi.
                    </p>
                    <div class="ds-matrix-card__example" style="text-align: center;">
                        <HcisButton label="Uji Dialog Konfirmasi" variant="danger" size="sm" icon="exclamation-triangle" @click="showConfirmDialog = true" />
                    </div>
                    <div class="ds-matrix-card__rule">
                        <HcisIcon name="check-circle" size="xs" style="color: var(--hcis-success-600);" />
                        <span>Cocok untuk: konfirmasi hapus data, otorisasi penolakan, perubahan status permanen.</span>
                    </div>
                </div>

                <!-- Card 4: HcisContextBanner -->
                <div class="ds-matrix-card">
                    <div class="ds-matrix-card__header">
                        <HcisBadge label="Page Scope" variant="neutral" size="sm" />
                        <h4 class="ds-matrix-card__title">HcisContextBanner</h4>
                    </div>
                    <p class="ds-matrix-card__desc">
                        Gunakan di bagian paling atas halaman saat pengguna <strong>sedang bertindak dalam konteks khusus</strong>.
                    </p>
                    <div class="ds-matrix-card__example">
                        <HcisContextBanner
                            title="Mode Penilai: Departemen HR"
                            subtitle="Evaluasi Kinerja Semester I-2026"
                            variant="surface"
                            icon="shield-check"
                        />
                    </div>
                    <div class="ds-matrix-card__rule">
                        <HcisIcon name="check-circle" size="xs" style="color: var(--hcis-success-600);" />
                        <span>Cocok untuk: impersonasi akun, cakupan unit kerja khusus, status draft dokumen.</span>
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- SEKSI 2: STANDAR FORM VALIDATION & BACKEND ERROR CODE -->
        <HcisCard title="2. Standar Validasi Formulir & Pemetaan Error Backend" description="Konsistensi respons API dan penanda visual isian formulir." divider>
            <div class="hcis-grid hcis-grid--2" style="gap: var(--hcis-space-6);">
                <div>
                    <h4 class="ds-subhead">Arsitektur Respons API Error (RFC 7807 + error_code)</h4>
                    <p class="hcis-type-body ds-text-muted">
                        Setiap kali backend mengembalikan HTTP 422 (Unprocessable Content), payload wajib menyertakan <code>error_code</code>, <code>request_id</code>, dan objek <code>errors</code>:
                    </p>
                    <div class="ds-code-window">
                        <pre><code>{
  "status": "error",
  "error_code": "VALIDATION_FAILED",
  "request_id": "req-9a8f21bc",
  "message": "Data pengajuan tidak lengkap.",
  "errors": {
    "nik": ["NIK wajib diisi dengan 16 digit angka."],
    "email": ["Email sudah digunakan oleh karyawan lain."]
  }
}</code></pre>
                    </div>
                </div>

                <div>
                    <h4 class="ds-subhead">Visualisasi Komponen (HcisField Error State)</h4>
                    <div class="hcis-stack hcis-stack--sm" style="margin-top: var(--hcis-space-3);">
                        <HcisField
                            label="Nomor Induk Kependudukan (NIK)"
                            required
                            error="NIK wajib diisi dengan 16 digit angka."
                        >
                            <input
                                type="text"
                                class="ds-control-input is-invalid"
                                value="327102"
                            />
                        </HcisField>

                        <HcisField
                            label="Email Perusahaan"
                            required
                            error="Email sudah digunakan oleh karyawan lain."
                        >
                            <input
                                type="email"
                                class="ds-control-input is-invalid"
                                value="aria.pratama@company.co.id"
                            />
                        </HcisField>

                        <HcisAlert
                            variant="danger"
                            title="Validasi Gagal (req-9a8f21bc)"
                            message="Mohon perbaiki 2 isian bertanda merah di atas sebelum menyimpan."
                        />
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- SEKSI 3: STANDAR LOADING & SKELETON -->
        <HcisCard title="3. Standar Indikator Memuat (Loading & Skeleton UX)" description="Hindari layar putih memblokir; gunakan skeleton yang meniru bentuk asli." divider>
            <div class="hcis-grid hcis-grid--3" style="gap: var(--hcis-space-4);">
                <div class="ds-loading-guide-card">
                    <div class="ds-loading-icon-wrap"><HcisIcon name="card-heading" size="md" /></div>
                    <h4 class="ds-loading-title">Data Fetching Halaman</h4>
                    <p class="ds-loading-desc">Gunakan <code>HcisSkeleton</code> berbentuk kartu dan baris tabel saat memuat data awal.</p>
                    <HcisBadge label="WAJIB SKELETON" variant="success" size="sm" />
                </div>

                <div class="ds-loading-guide-card">
                    <div class="ds-loading-icon-wrap"><HcisIcon name="cursor" size="md" /></div>
                    <h4 class="ds-loading-title">Aksi Tombol Simpan / Hapus</h4>
                    <p class="ds-loading-desc">Gunakan prop <code>:loading="true"</code> pada <code>HcisButton</code> agar pengguna tahu proses berjalan.</p>
                    <HcisBadge label="BUTTON SPINNER" variant="primary" size="sm" />
                </div>

                <div class="ds-loading-guide-card">
                    <div class="ds-loading-icon-wrap"><HcisIcon name="cloud-upload" size="md" /></div>
                    <h4 class="ds-loading-title">Proses Batch / Upload File</h4>
                    <p class="ds-loading-desc">Gunakan <code>HcisProgress</code> dengan persentase real-time untuk proses lebih dari 3 detik.</p>
                    <HcisBadge label="PROGRESS BAR" variant="info" size="sm" />
                </div>
            </div>
        </HcisCard>

        <!-- Dialog Test Demo -->
        <HcisDialog
            v-model="showConfirmDialog"
            title="Konfirmasi Penghapusan Karyawan"
            width="520px"
        >
            <div style="display: flex; gap: var(--hcis-space-3); align-items: flex-start;">
                <div style="width: 2.5rem; height: 2.5rem; border-radius: 50%; background: var(--hcis-danger-100); color: var(--hcis-danger-600); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <HcisIcon name="trash" size="sm" />
                </div>
                <div>
                    <h4 style="margin: 0; font-size: var(--hcis-font-size-sm); font-weight: 700; color: var(--hcis-text-strong);">
                        Apakah Anda yakin ingin menonaktifkan akun karyawan ini?
                    </h4>
                    <p style="margin: var(--hcis-space-1) 0 0; font-size: var(--hcis-font-size-xs); color: var(--hcis-text-muted);">
                        Akses sistem, email perusahaan, dan data absensi akan ditangguhkan secara otomatis per hari ini.
                    </p>
                </div>
            </div>

            <template #footer>
                <div style="display: flex; justify-content: flex-end; gap: var(--hcis-space-2);">
                    <HcisButton label="Batal" variant="ghost" @click="showConfirmDialog = false" />
                    <HcisButton label="Ya, Nonaktifkan Akun" variant="danger" @click="confirmDelete" />
                </div>
            </template>
        </HcisDialog>

        <DsMotionBanner />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    HcisHeader,
    HcisCard,
    HcisBadge,
    HcisButton,
    HcisAlert,
    HcisContextBanner,
    HcisField,
    HcisDialog,
    HcisIcon,
    hcisMessage,
} from '@/components/ui';
import DsPortalNav from './partials/DsPortalNav.vue';
import DsMotionBanner from './partials/DsMotionBanner.vue';

const showConfirmDialog = ref(false);

function triggerToast() {
    hcisMessage.success('Perubahan data karyawan berhasil disimpan ke database.');
}

function confirmDelete() {
    showConfirmDialog.value = false;
    hcisMessage.success('Akun karyawan berhasil dinonaktifkan.');
}
</script>

<style scoped>
.ds-guidelines-root {
    padding-bottom: var(--hcis-space-12);
}

.ds-matrix-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--hcis-space-4);
    margin-top: var(--hcis-space-4);
}

@media (max-width: 900px) {
    .ds-matrix-grid {
        grid-template-columns: 1fr;
    }
}

.ds-matrix-card {
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    padding: var(--hcis-space-5);
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3);
}

.ds-matrix-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ds-matrix-card__title {
    margin: 0;
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
}

.ds-matrix-card__desc {
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text);
    line-height: 1.4;
}

.ds-matrix-card__example {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-md);
    padding: var(--hcis-space-3);
}

.ds-matrix-card__rule {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-2);
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-muted);
    font-weight: 600;
}

.ds-subhead {
    margin: 0 0 var(--hcis-space-2);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
    text-transform: uppercase;
    letter-spacing: var(--hcis-tracking-wide);
}

.ds-text-muted {
    color: var(--hcis-text-muted);
    margin-bottom: var(--hcis-space-3);
}

.ds-code-window {
    background: var(--hcis-slate-900);
    border-radius: var(--hcis-radius-lg);
    padding: var(--hcis-space-4);
    overflow-x: auto;
    border: 1px solid var(--hcis-slate-800);
}

.ds-code-window pre {
    margin: 0;
    font-family: var(--hcis-font-mono);
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-sky-400);
    line-height: 1.5;
}

.ds-control-input {
    width: 100%;
    height: 2.25rem;
    padding: 0 var(--hcis-space-3);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-strong);
    background-color: var(--hcis-surface);
    color: var(--hcis-text-strong);
    font-size: var(--hcis-font-size-xs);
    box-shadow: 0 1px 2px 0 rgb(var(--hcis-slate-900-rgb) / 0.05), inset 0 1px 1.5px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
    outline: none;
    transition: border-color 200ms ease, box-shadow 200ms ease;
}

.ds-control-input:focus {
    border-color: var(--hcis-brand-600);
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb) / 0.1);
}

.ds-control-input.is-invalid {
    border-color: var(--hcis-danger-500);
    background-color: var(--hcis-danger-50);
}

.ds-loading-guide-card {
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    border-radius: var(--hcis-radius-lg);
    padding: var(--hcis-space-5);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: var(--hcis-space-2);
}

.ds-loading-icon-wrap {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-brand-600);
    margin-bottom: var(--hcis-space-1);
}

.ds-loading-title {
    margin: 0;
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
}

.ds-loading-desc {
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-muted);
    line-height: 1.4;
    margin-bottom: var(--hcis-space-2);
}
</style>
