<template>
    <!-- Section: Anatomy -->
    <div v-if="section === 'anatomy'" class="ds-result-demo">
        <!-- Interactive Real-World Workflow Simulation Card (Dribbble/SVGator style) -->
        <div class="ds-card ds-workflow-card mb-4">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="magic" size="sm" class="text-indigo-600 me-2" /> Simulasi Alur Nyata (Form -> Loading -> Animated Success Result)
                </span>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-1 ds-sim-toggle">
                        <HcisSwitch v-model="simulateError" size="sm" />
                        <span class="ds-toggle-text">Simulasikan Error</span>
                    </div>
                    <HcisBadge label="Dribbble SVGator Motion Inspired" variant="brand" dot />
                </div>
            </div>

            <div class="ds-card__body">
                <!-- Step 1: Form View -->
                <div v-if="flowStep === 'form'" class="ds-form-container">
                    <div class="ds-form-header mb-3">
                        <h3 class="ds-form-title">Formulir Pengajuan Cuti Mandiri</h3>
                        <p class="ds-form-subtitle">Lengkapi formulir di bawah ini untuk mengajukan permohonan cuti tahunan ke sistem kepegawaian.</p>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="ds-field-label">Tipe Pengajuan</label>
                            <div class="ds-mock-input">
                                <HcisIcon name="calendar-check" size="xs" class="text-muted me-2" />
                                <span>Cuti Tahunan (Tahap II)</span>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="ds-field-label">Periode Cuti</label>
                            <div class="ds-mock-input">
                                <HcisIcon name="calendar-range" size="xs" class="text-muted me-2" />
                                <span>24 Agu 2026 s/d 26 Agu 2026 (3 Hari)</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="ds-field-label">Keperluan / Keterangan</label>
                            <div class="ds-mock-input">
                                <HcisIcon name="chat-square-text" size="xs" class="text-muted me-2" />
                                <span>Keperluan keluarga dan refreshing tahunan.</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                        <div class="d-flex align-items-center gap-2">
                            <span class="ds-quota-badge">Sisa Kuota: <strong>9 Hari</strong></span>
                        </div>
                        <HcisButton
                            variant="primary"
                            size="md"
                            leading-icon="send-fill"
                            @click="startSubmissionWorkflow"
                        >
                            Kirim Pengajuan Cuti
                        </HcisButton>
                    </div>
                </div>

                <!-- Step 2 & 3: Loading / Result State View -->
                <div v-else class="ds-result-flow-wrap">
                    <HcisResultState
                        v-if="flowStep === 'loading'"
                        status="loading"
                        variant="glass"
                        kicker="PROSES PENGIRIMAN"
                        title="Mengunci Kuota & Mengirim Pengajuan..."
                        description="Mohon tunggu sebentar, sistem sedang memverifikasi sisa kuota dan menghubungkan ke server kepegawaian."
                    />

                    <HcisResultState
                        v-else-if="flowStep === 'success'"
                        status="success"
                        variant="glass"
                        kicker="TRANSAKSI BERHASIL"
                        title="Pengajuan Cuti Berhasil Terkirim!"
                        description="Permohonan cuti tahunan Anda telah tercatat dan menanti verifikasi persetujuan HR Operations."
                        :items="[
                            { label: 'Nomor Transaksi', value: 'TX-2026-9901', copyable: true, mono: true },
                            { label: 'Tipe Transaksi', value: 'Cuti Tahunan Mandiri' },
                            { label: 'Durasi Cuti', value: '3 Hari Kerja' },
                            { label: 'Sisa Kuota Tersedia', value: '6 Hari' }
                        ]"
                        primary-text="Buat Pengajuan Baru"
                        primary-icon="plus-lg"
                        secondary-text="Kembali ke Beranda"
                        secondary-icon="house-fill"
                        @primary-click="resetWorkflow"
                        @secondary-click="resetWorkflow"
                    />

                    <HcisResultState
                        v-else-if="flowStep === 'error'"
                        status="error"
                        variant="glass"
                        kicker="KENDALA SISTEM"
                        title="Gagal Mengirimkan Pengajuan"
                        description="Koneksi ke gateway server otorisasi HRIS terputus atau sesi kerja Anda telah kadaluarsa."
                        error-code="ERR_NETWORK_TIMEOUT"
                        request-id="req-2026-9901-sim"
                        error-detail="ERR_NETWORK_TIMEOUT: Server kepegawaian tidak merespons dalam 3000ms. Token otorisasi mungkin perlu diperbarui."
                        :show-retry="true"
                        primary-text="Kembali ke Form"
                        primary-icon="arrow-left"
                        @retry="retrySubmission"
                        @primary-click="resetWorkflow"
                    />
                </div>
            </div>
        </div>

        <!-- Component Anatomy & Playground -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="check-circle-fill" size="sm" class="text-indigo-600 me-2" /> HcisResultState (Playground Status & Varian)
                </span>
                <HcisBadge label="Multi-Ripple Halo, Aura Glow, Staggered Slide & Replay Controls" variant="brand" dot />
            </div>

            <div class="ds-card__body">
                <!-- Interactive Controls Bar -->
                <div class="ds-status-switcher mb-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <span class="ds-demo-label">Status Simulasi:</span>

                        <!-- Replay Animation Button -->
                        <HcisButton
                            size="xs"
                            variant="secondary"
                            leading-icon="arrow-counterclockwise"
                            @click="replayAnimation"
                        >
                            Putar Ulang Animasi Masuk
                        </HcisButton>
                    </div>

                    <div class="ds-btn-group mb-3">
                        <HcisButton
                            v-for="st in ['success', 'error', 'loading', 'warning', 'info']"
                            :key="st"
                            size="xs"
                            :variant="activeStatus === st ? 'primary' : 'outline'"
                            @click="selectStatus(st)"
                        >
                            {{ st.toUpperCase() }}
                        </HcisButton>
                    </div>

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-1">
                            <span class="ds-demo-label mb-0">Varian:</span>
                            <div class="ds-btn-group">
                                <HcisButton
                                    v-for="v in ['glass', 'card', 'transparent', 'flat']"
                                    :key="v"
                                    size="xs"
                                    :variant="activeVariant === v ? 'primary' : 'ghost'"
                                    @click="activeVariant = v"
                                >
                                    {{ v }}
                                </HcisButton>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-1">
                            <span class="ds-demo-label mb-0">Layout:</span>
                            <div class="ds-btn-group">
                                <HcisButton
                                    v-for="l in ['centered', 'compact', 'inline']"
                                    :key="l"
                                    size="xs"
                                    :variant="activeLayout === l ? 'primary' : 'ghost'"
                                    @click="activeLayout = l"
                                >
                                    {{ l }}
                                </HcisButton>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-1">
                            <span class="ds-demo-label mb-0">Ukuran:</span>
                            <div class="ds-btn-group">
                                <HcisButton
                                    v-for="s in ['sm', 'md', 'lg']"
                                    :key="s"
                                    size="xs"
                                    :variant="activeSize === s ? 'primary' : 'ghost'"
                                    @click="activeSize = s"
                                >
                                    {{ s }}
                                </HcisButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ds-result-wrapper">
                    <HcisResultState
                        :render-key="animationKey"
                        :status="activeStatus"
                        :variant="activeVariant"
                        :size="activeSize"
                        :layout="activeLayout"
                        :kicker="statusData[activeStatus].kicker"
                        :animated="true"
                        :loop-animation="true"
                        :title="statusData[activeStatus].title"
                        :description="statusData[activeStatus].desc"
                        :items="statusData[activeStatus].items"
                        :error-detail="statusData[activeStatus].errorDetail"
                        :error-code="statusData[activeStatus].errorCode"
                        :request-id="statusData[activeStatus].requestId"
                        primary-text="Kembali ke Dashboard"
                        primary-icon="house-fill"
                        secondary-text="Lihat Aktivitas"
                        secondary-icon="clock-history"
                        @primary-click="handlePrimaryClick"
                        @secondary-click="handleSecondaryClick"
                        @retry="handleRetry"
                    />
                </div>

                <div v-if="lastEventLog" class="ds-event-log mt-3">
                    <HcisIcon name="terminal-fill" size="xs" class="text-indigo-600 me-2" />
                    <span><strong>Event Dipicu:</strong> {{ lastEventLog }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="palette" size="sm" class="text-indigo-600 me-2" /> Varian Glassmorphic (Signature HCIS Aurora Glass)
                </span>
                <span class="ds-card__tag">variant="glass"</span>
            </div>
            <div class="ds-card__body">
                <HcisResultState
                    status="success"
                    variant="glass"
                    kicker="PAYROLL SUITE"
                    title="Otorisasi Payroll Disetujui"
                    description="Seluruh slip gaji bulan Agustus 2026 telah terverifikasi dan siap diproses ke rekening karyawan."
                    :items="[
                        { label: 'Total Karyawan', value: '142 Pegawai' },
                        { label: 'Nominal Batch', value: 'Rp 890.450.000', mono: true },
                        { label: 'Batch ID', value: 'PAY-2026-08', copyable: true }
                    ]"
                    primary-text="Lihat Rekapitulasi"
                    primary-icon="file-earmark-spreadsheet"
                    secondary-text="Tutup"
                />
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="square" size="sm" class="text-indigo-600 me-2" /> Varian Card Frame & Flat
                </span>
                <span class="ds-card__tag">variant="card" · variant="flat"</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <HcisResultState
                            status="info"
                            variant="card"
                            kicker="INFO SISTEM"
                            title="Pembaruan Kebijakan Cuti"
                            description="Terdapat penyesuaian aturan cuti bersama tahun 2026 sesuai surat edaran direksi."
                            primary-text="Baca Surat Edaran"
                            size="sm"
                        />
                    </div>
                    <div class="col-12 col-md-6">
                        <HcisResultState
                            status="warning"
                            variant="flat"
                            kicker="REVIEW ATASAN"
                            title="Menunggu Persetujuan"
                            description="Permohonan izin sakit Anda sedang dalam antrean review oleh Head of HR."
                            primary-text="Hubungi HR"
                            size="sm"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-stack-demo">
        <!-- 1. Success State -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="check-circle-fill" size="sm" class="text-success me-2" /> 1. State Sukses (Vector Draw + Digital Receipt)
                </span>
                <span class="ds-card__tag">status="success"</span>
            </div>
            <div class="ds-card__body">
                <HcisResultState
                    status="success"
                    variant="glass"
                    kicker="TRANSAKSI BERHASIL"
                    title="Pengajuan Cuti Berhasil Disetujui!"
                    description="Permohonan cuti tahunan Anda telah terverifikasi dan tercatat dalam sistem kepegawaian."
                    :items="[
                        { label: 'Nomor Transaksi', value: 'TX-2026-9901', copyable: true, mono: true },
                        { label: 'Tipe Transaksi', value: 'Cuti Tahunan Mandiri' },
                        { label: 'Durasi Cuti', value: '3 Hari Kerja' },
                        { label: 'Sisa Kuota Tersedia', value: '6 Hari' }
                    ]"
                    primary-text="Buat Pengajuan Baru"
                    primary-icon="plus-lg"
                    secondary-text="Kembali ke Beranda"
                    secondary-icon="house-fill"
                />
            </div>
        </div>

        <!-- 2. Error State -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="x-circle-fill" size="sm" class="text-danger me-2" /> 2. State Gagal / Error (Enterprise Diagnostics)
                </span>
                <span class="ds-card__tag">status="error"</span>
            </div>
            <div class="ds-card__body">
                <HcisResultState
                    status="error"
                    variant="glass"
                    kicker="KENDALA SISTEM"
                    title="Gagal Mengirimkan Pengajuan"
                    description="Koneksi ke gateway server otorisasi HRIS terputus atau sesi kerja Anda telah kadaluarsa."
                    error-code="ERR_AUTH_SESSION_EXPIRED"
                    request-id="req-2026-9901-ab4"
                    error-detail="ERR_AUTH_SESSION_EXPIRED: Token otorisasi JWT tidak valid atau telah dibatalkan oleh server (HTTP 401 Unauthorized). Sesi kerja pengguna telah melampaui batas TTL 7200 detik."
                    :show-retry="true"
                    primary-text="Kembali ke Form"
                    primary-icon="arrow-left"
                />
            </div>
        </div>

        <!-- 3. Warning State -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="exclamation-triangle-fill" size="sm" class="text-warning me-2" /> 3. State Peringatan / Perlu Verifikasi
                </span>
                <span class="ds-card__tag">status="warning"</span>
            </div>
            <div class="ds-card__body">
                <HcisResultState
                    status="warning"
                    variant="glass"
                    kicker="PERINGATAN SISTEM"
                    title="Pengajuan Membutuhkan Verifikasi Tambahan"
                    description="Dokumen lampiran medis Anda memerlukan persetujuan konfirmasi manual dari Manajer HR."
                    :items="[
                        { label: 'Nomor Pengajuan', value: 'REQ-MED-5502', copyable: true, mono: true },
                        { label: 'Status Verifikasi', value: 'Menunggu Review Manajer' }
                    ]"
                    primary-text="Unggah Dokumen Tambahan"
                    primary-icon="upload"
                    secondary-text="Lihat Panduan"
                />
            </div>
        </div>

        <!-- 4. Loading State -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="arrow-repeat" size="sm" class="text-indigo-600 me-2" /> 4. State Loading & Sedang Diproses (Dual-Ring Laser Spinner)
                </span>
                <span class="ds-card__tag">status="loading" · status="processing"</span>
            </div>
            <div class="ds-card__body">
                <HcisResultState
                    status="loading"
                    variant="glass"
                    kicker="PROSES OTORISASI"
                    title="Menyinkronkan Data Karyawan..."
                    description="Mohon tunggu sebentar, sistem sedang memverifikasi dokumen persyaratan dan memperbarui matriks otorisasi kepegawaian."
                />
            </div>
        </div>

        <!-- 5. Layout Variations (Compact & Inline) -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="layout-split" size="sm" class="text-indigo-600 me-2" /> 5. Tata Letak Fleksibel (Compact & Inline)
                </span>
                <span class="ds-card__tag">layout="compact" · layout="inline"</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-3">
                <div class="d-flex flex-column gap-2">
                    <span class="ds-demo-label">Layout Compact (Horizontal Card — Cocok untuk Modal Dialog & Drawer):</span>
                    <HcisResultState
                        status="success"
                        variant="glass"
                        layout="compact"
                        kicker="DISETUJUI"
                        title="Perubahan Jadwal Shift Berhasil Disimpan"
                        description="Karyawan bersangkutan akan menerima notifikasi push otomatis."
                        primary-text="Selesai"
                        primary-icon="check-lg"
                    />

                    <HcisResultState
                        status="error"
                        variant="glass"
                        layout="compact"
                        kicker="KENDALA VALIDASI"
                        title="Gagal Memverifikasi Lampiran Surat Dokter"
                        description="Format file harus PDF atau JPG dengan ukuran maksimal 2MB."
                        :show-retry="true"
                        primary-text="Tutup"
                    />
                </div>

                <div class="d-flex flex-column gap-2 mt-2">
                    <span class="ds-demo-label">Layout Inline (Slim Banner Bar Berbagai Status):</span>

                    <HcisResultState
                        status="success"
                        variant="glass"
                        layout="inline"
                        kicker="SUKSES"
                        title="Dokumen Kontrak Kerja Karyawan Telah Ditandatangani"
                        description="Salinan digital terenkripsi telah dikirimkan ke email resmi."
                        primary-text="Lihat Dokumen"
                        primary-icon="file-earmark-check"
                    />

                    <HcisResultState
                        status="info"
                        variant="glass"
                        layout="inline"
                        kicker="PENGUMUMAN"
                        title="Fitur Penilaian Kinerja Q3 telah dibuka."
                        description="Batas akhir pengisian evaluasi mandiri adalah 31 Agustus 2026."
                        primary-text="Mulai Evaluasi"
                        primary-icon="arrow-right"
                    />

                    <HcisResultState
                        status="warning"
                        variant="glass"
                        layout="inline"
                        kicker="PERINGATAN"
                        title="Sertifikat Keamanan SSL akan kadaluarsa dalam 3 hari"
                        primary-text="Perbarui Sekarang"
                        primary-icon="arrow-repeat"
                    />

                    <HcisResultState
                        status="error"
                        variant="glass"
                        layout="inline"
                        kicker="KONEKSI TERPUTUS"
                        title="Gagal menyinkronkan data absensi mesin fingerprint"
                        description="Gateway IP 192.168.1.105 tidak merespons ping."
                        primary-text="Coba Sinkron Ulang"
                        primary-icon="arrow-clockwise"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisResultState, HcisButton, HcisIcon, HcisBadge, HcisSwitch } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Interactive Real-World Flow Simulation (Dribbble/SVGator style)
const flowStep = ref('form');
const simulateError = ref(false);

function startSubmissionWorkflow() {
    flowStep.value = 'loading';
    setTimeout(() => {
        if (simulateError.value) {
            flowStep.value = 'error';
        } else {
            flowStep.value = 'success';
        }
    }, 1300);
}

function retrySubmission() {
    flowStep.value = 'loading';
    setTimeout(() => {
        flowStep.value = 'success';
    }, 1100);
}

function resetWorkflow() {
    flowStep.value = 'form';
}

// Playground State
const activeStatus = ref('success');
const activeVariant = ref('glass');
const activeLayout = ref('centered');
const activeSize = ref('md');
const animationKey = ref(0);
const lastEventLog = ref('');

function selectStatus(st) {
    activeStatus.value = st;
    animationKey.value += 1;
}

function replayAnimation() {
    animationKey.value += 1;
    lastEventLog.value = 'Animasi Masuk & Staggered Slide Diputar Ulang!';
}

const statusData = {
    success: {
        kicker: 'TRANSAKSI BERHASIL',
        title: 'Pengajuan Cuti Berhasil Disetujui!',
        desc: 'Permohonan cuti tahunan Anda telah terverifikasi dan dicatat dalam sistem kepegawaian.',
        items: [
            { label: 'Nomor Referensi', value: 'TX-2026-9901', copyable: true },
            { label: 'Tipe Transaksi', value: 'Pengajuan Cuti Tahunan' },
            { label: 'Tanggal & Waktu', value: '11 Agu 2026, 23:18 WIB' },
            { label: 'Durasi Cuti', value: '3 Hari Kerja' },
            { label: 'Sisa Kuota Cuti', value: '9 Hari' }
        ]
    },
    error: {
        kicker: 'AUTENTIKASI GAGAL',
        title: 'Gagal Memproses Transaksi Otorisasi',
        desc: 'Koneksi ke server gateway otorisasi terputus atau hak akses sesi Anda telah kadaluarsa.',
        errorCode: 'ERR_AUTH_SESSION_EXPIRED',
        requestId: 'req-2026-9901-ab4',
        errorDetail: 'ERR_AUTH_SESSION_EXPIRED: Token otorisasi JWT tidak valid atau telah dibatalkan oleh server (HTTP 401 Unauthorized). Sesi kerja pengguna telah melampaui batas TTL 7200 detik.',
        items: [
            { label: 'Nomor Referensi', value: 'TX-ERR-4019', copyable: true },
            { label: 'Waktu Kejadian', value: '11 Agu 2026, 23:19 WIB' }
        ]
    },
    loading: {
        kicker: 'PROSES SISTEM',
        title: 'Menyinkronkan Data Karyawan...',
        desc: 'Sistem sedang memverifikasi dokumen persyaratan dan memperbarui matriks otorisasi.',
        items: []
    },
    warning: {
        kicker: 'PERINGATAN SISTEM',
        title: 'Pengajuan Membutuhkan Verifikasi Tambahan',
        desc: 'Dokumen lampiran medis Anda memerlukan persetujuan konfirmasi manual dari Manajer HR.',
        items: [
            { label: 'Nomor Pengajuan', value: 'REQ-MED-5502', copyable: true },
            { label: 'Status Verifikasi', value: 'Menunggu Review Manajer' }
        ]
    },
    info: {
        kicker: 'PEMBERITAHUAN',
        title: 'Informasi Pembaruan Jadwal Shift Kerja',
        desc: 'Jadwal shift kerja Anda untuk minggu depan telah diperbarui oleh HR Operations.',
        items: [
            { label: 'Shift Baru', value: 'Shift Pagi (08:00 - 17:00)' },
            { label: 'Tanggal Berlaku', value: '17 Agustus 2026' }
        ]
    }
};

function handlePrimaryClick() {
    lastEventLog.value = 'Tombol Utama "Kembali ke Dashboard" Diklik!';
}

function handleSecondaryClick() {
    lastEventLog.value = 'Tombol Sekunder "Lihat Aktivitas" Diklik!';
}

function handleRetry() {
    lastEventLog.value = 'Tombol "Coba Lagi" Diklik!';
}
</script>

<style scoped>
.ds-result-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.ds-card {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl, 0.75rem);
    overflow: hidden;
    box-shadow: var(--hcis-shadow-xs);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft);
    border-bottom: 1px solid var(--hcis-border-subtle);
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
    display: flex;
    align-items: center;
}

.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 600;
    font-family: monospace;
    color: var(--hcis-text-muted);
}

.ds-card__body {
    padding: 1.25rem;
}

/* Workflow Card Elements */
.ds-workflow-card {
    border: 1px solid var(--hcis-border-medium);
    box-shadow: var(--hcis-shadow-sm);
}

.ds-form-container {
    padding: 0.5rem 0.25rem;
}

.ds-form-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
    margin: 0 0 0.25rem 0;
}

.ds-form-subtitle {
    font-size: 0.8125rem;
    color: var(--hcis-text-muted);
    margin: 0;
}

.ds-field-label {
    display: block;
    font-size: 0.775rem;
    font-weight: 600;
    color: var(--hcis-text);
    margin-bottom: 0.35rem;
}

.ds-mock-input {
    display: flex;
    align-items: center;
    padding: 0.625rem 0.75rem;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    font-size: 0.825rem;
    color: var(--hcis-text-strong);
    font-weight: 500;
}

.ds-quota-badge {
    font-size: 0.8rem;
    color: var(--hcis-text-muted);
    background: var(--hcis-surface-soft);
    padding: 0.35rem 0.625rem;
    border-radius: var(--hcis-radius-pill);
    border: 1px solid var(--hcis-border-subtle);
}

.ds-quota-badge strong {
    color: var(--hcis-success-600);
}

.ds-sim-toggle {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
}

.ds-toggle-text {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
}

.ds-btn-group {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.ds-demo-label {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-text-muted);
    display: block;
}

.ds-event-log {
    padding: 0.625rem 0.875rem;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    font-size: 0.8rem;
    color: var(--hcis-text);
    display: flex;
    align-items: center;
}

@media (max-width: 640px) {
    .ds-card__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .ds-card__tag {
        font-size: 0.6rem;
    }

    .ds-quota-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.45rem;
    }

    .ds-card__body {
        padding: 0.75rem 0.5rem;
    }

    .ds-status-switcher {
        padding: 0.25rem 0.25rem 0.75rem 0.25rem;
    }
}
</style>
