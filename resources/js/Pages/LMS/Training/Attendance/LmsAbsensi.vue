<template>
    <!-- We use the same Modal component as LeaderShift.vue -->
    <Modal
        :show="show"
        :title="`Absensi ${type === 'In' ? 'Check-In' : 'Check-Out'} Training`"
        size="medium"
        :closeOnOverlay="false"
        :showCancel="false"
        :showConfirm="false"
        @close="handleClose"
    >
        <template #body>
            <div class="absensi-orchestrator-container">
                <div v-if="state === 'precheck'" class="state-container">
                    <div class="precheck-card">
                        <div class="precheck-header">
                            <div>
                                <h4 class="precheck-title mb-2">Persiapan Sebelum Absensi</h4>
                                <p class="precheck-subtitle mb-0">
                                    Pastikan izin dan kondisi berikut sudah siap agar proses check-{{
                                        type === 'In' ? 'in' : 'out'
                                    }}
                                    berjalan lancar.
                                </p>
                            </div>
                            <span class="journey-badge">Langkah 1 dari 3</span>
                        </div>

                        <div class="precheck-section">
                            <h5 class="section-title">Checklist Izin Perangkat</h5>

                            <div class="permission-item">
                                <div class="permission-icon camera">
                                    <i class="bi bi-camera-video"></i>
                                </div>
                                <div class="permission-content">
                                    <div class="permission-row">
                                        <span class="permission-name">Akses Kamera</span>
                                        <span
                                            class="permission-badge"
                                            :class="permissionStatusClass(permissionStatus.camera)"
                                        >
                                            {{ permissionStatusLabel(permissionStatus.camera) }}
                                        </span>
                                    </div>
                                    <p class="permission-desc mb-0">Digunakan untuk verifikasi wajah saat absensi.</p>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="permission-icon location">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <div class="permission-content">
                                    <div class="permission-row">
                                        <span class="permission-name">Akses Lokasi</span>
                                        <span
                                            class="permission-badge"
                                            :class="permissionStatusClass(permissionStatus.geolocation)"
                                        >
                                            {{ permissionStatusLabel(permissionStatus.geolocation) }}
                                        </span>
                                    </div>
                                    <p class="permission-desc mb-0">
                                        Digunakan untuk memastikan absensi dilakukan dari lokasi pengguna saat ini.
                                    </p>
                                </div>
                            </div>

                            <div class="permission-item">
                                <div class="permission-icon browser">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                                <div class="permission-content">
                                    <div class="permission-row">
                                        <span class="permission-name">Koneksi Aman</span>
                                        <span
                                            class="permission-badge"
                                            :class="permissionStatusClass(permissionStatus.secureContext)"
                                        >
                                            {{ permissionStatusLabel(permissionStatus.secureContext) }}
                                        </span>
                                    </div>
                                    <p class="permission-desc mb-0">
                                        Kamera biasanya hanya bisa diakses pada halaman HTTPS atau `localhost`.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="precheck-section">
                            <h5 class="section-title">Ketentuan Absensi</h5>
                            <div class="guideline-list">
                                <div class="guideline-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Izinkan akses kamera dan lokasi saat browser meminta permission.</span>
                                </div>
                                <div class="guideline-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span
                                        >Pastikan wajah terlihat jelas, tidak gelap, dan berada di dalam panduan
                                        oval.</span
                                    >
                                </div>
                                <div class="guideline-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span
                                        >Gunakan jaringan yang stabil dan jangan menutup modal selama proses absensi
                                        berjalan.</span
                                    >
                                </div>
                                <div class="guideline-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span
                                        >Jika izin sebelumnya ditolak, aktifkan kembali dari pengaturan browser lalu
                                        tekan cek ulang.</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div v-if="precheckMessage" class="alert" :class="precheckAlertClass">
                            <i class="bi" :class="precheckAlertIcon"></i>
                            <span>{{ precheckMessage }}</span>
                        </div>

                        <div class="precheck-actions">
                            <button
                                class="btn btn-sm btn-light border rounded-pill px-3 fw-medium transition-all"
                                @click="checkPermissions"
                            >
                                <i class="bi bi-arrow-repeat me-2"></i> Cek Ulang Izin
                            </button>
                            <button
                                class="btn btn-sm btn-primary rounded-pill px-3 fw-medium transition-all shadow-sm"
                                :disabled="isStartDisabled"
                                @click="startAbsensi"
                            >
                                <i class="bi bi-play-circle me-2"></i> Mulai Absensi
                            </button>
                        </div>
                    </div>
                </div>

                <!-- State: GPS Wait / Check -->
                <div v-if="state === 'gps_check'" class="state-container text-center py-5">
                    <div class="mb-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <h4 class="mb-2">Mendapatkan Lokasi GPS...</h4>
                    <p class="text-muted mb-4">Akses lokasi diperlukan untuk melakukan absensi.</p>
                    <p v-if="gpsError" class="text-danger small mb-3">
                        <i class="bi bi-exclamation-triangle"></i> {{ gpsError }}
                    </p>
                    <button
                        v-if="gpsError"
                        class="btn btn-sm btn-primary rounded-pill px-3 fw-medium transition-all shadow-sm"
                        @click="requestGps"
                    >
                        <i class="bi bi-geo-alt"></i> Coba Lagi
                    </button>
                </div>

                <!-- State: Camera Live Face Detection -->
                <div v-if="state === 'camera'" class="state-container">
                    <div class="camera-toolbar">
                        <button
                            class="btn btn-sm btn-light border rounded-pill px-3 fw-medium transition-all"
                            @click="handleClose"
                        >
                            <i class="bi bi-x-lg me-2"></i> Tutup
                        </button>
                    </div>

                    <AbsensiFaceCamera
                        :key="cameraSessionKey"
                        @ready="handleCameraReady"
                        @captured="handleCaptured"
                        @error="handleCameraError"
                    />

                    <div v-if="cameraError" class="alert alert-danger mt-3 mb-0 text-sm">
                        <i class="bi bi-camera-video-off me-2"></i>
                        {{ cameraError }}
                    </div>
                    <button
                        v-if="cameraError"
                        class="btn btn-sm btn-primary rounded-pill px-3 fw-medium transition-all shadow-sm mt-3 align-self-center"
                        @click="retryCamera"
                    >
                        <i class="bi bi-camera me-2"></i> Coba Aktifkan Kamera Lagi
                    </button>
                </div>

                <!-- State: Processing -->
                <div v-if="state === 'processing'" class="state-container">
                    <AbsensiResultScreen status="loading" :type="type" @close="handleClose" />
                </div>

                <!-- State: Result -->
                <div v-if="state === 'result'" class="state-container">
                    <AbsensiResultScreen
                        :status="resultProps.status"
                        :type="type"
                        :timestamp="resultProps.timestamp"
                        :message="resultProps.message"
                        :photoUrl="resultProps.photoUrl"
                        @retry="handleRetry"
                        @close="handleClose"
                    />
                </div>
            </div>
        </template>
    </Modal>
</template>

<script>
// Import the modal used across KPI/LeaderShift
import Modal from '@/components/legacy/modalV2-noir.vue';
import AbsensiFaceCamera from './components/AbsensiFaceCamera.vue';
import AbsensiResultScreen from './components/AbsensiResultScreen.vue';
import axios from 'axios';

export default {
    name: 'LmsAbsensi',
    components: {
        Modal,
        AbsensiFaceCamera,
        AbsensiResultScreen,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        type: {
            type: String,
            required: true,
            validator: (val) => ['In', 'Out'].includes(val),
        },
        activityItem: {
            type: Object,
            required: true,
        },
    },
    emits: ['update:show', 'status-change', 'absensi-complete'],
    data() {
        return {
            // States: idle, precheck, gps_check, camera, processing, result
            state: 'idle',

            permissionStatus: {
                camera: 'checking',
                geolocation: 'checking',
                secureContext: 'checking',
            },
            precheckMessage: '',

            // GPS Data
            lat: null,
            long: null,
            gpsError: null,

            // Camera Data
            cameraError: null,
            cameraSessionKey: 0,

            // Result Screen Props
            resultProps: {
                status: 'loading', // loading, success, failed
                message: '',
                photoUrl: null,
                timestamp: null,
            },
        };
    },
    computed: {
        isStartDisabled() {
            return (
                this.permissionStatus.secureContext === 'denied' ||
                this.permissionStatus.camera === 'unsupported' ||
                this.permissionStatus.geolocation === 'unsupported'
            );
        },
        precheckAlertClass() {
            return this.isStartDisabled ? 'alert-danger' : 'alert-info';
        },
        precheckAlertIcon() {
            return this.isStartDisabled ? 'bi-exclamation-triangle-fill me-2' : 'bi-info-circle-fill me-2';
        },
    },
    watch: {
        show(newVal) {
            if (newVal) {
                // Reset state when modal opens
                this.resetState();
                this.checkPermissions();
            } else {
                this.resetState();
            }
        },
    },
    methods: {
        resetState() {
            this.state = 'precheck';
            this.permissionStatus = {
                camera: 'checking',
                geolocation: 'checking',
                secureContext: 'checking',
            };
            this.precheckMessage = '';
            this.lat = null;
            this.long = null;
            this.gpsError = null;
            this.cameraError = null;
            this.cameraSessionKey = 0;

            this.resultProps = {
                status: 'loading',
                message: '',
                photoUrl: null,
                timestamp: null,
            };
        },

        async checkPermissions() {
            this.state = 'precheck';
            this.precheckMessage = 'Memeriksa kesiapan izin perangkat...';

            const permissionStatus = {
                camera: this.getCameraSupportStatus(),
                geolocation: this.getGeolocationSupportStatus(),
                secureContext: window.isSecureContext ? 'granted' : 'denied',
            };

            if (navigator.permissions && navigator.permissions.query) {
                permissionStatus.geolocation = await this.queryPermissionStatus(
                    'geolocation',
                    permissionStatus.geolocation,
                );
                permissionStatus.camera = await this.queryPermissionStatus('camera', permissionStatus.camera);
            } else {
                permissionStatus.camera = permissionStatus.camera === 'granted' ? 'prompt' : permissionStatus.camera;
                permissionStatus.geolocation =
                    permissionStatus.geolocation === 'granted' ? 'prompt' : permissionStatus.geolocation;
            }

            this.permissionStatus = permissionStatus;
            this.precheckMessage = this.buildPrecheckMessage();
        },

        getCameraSupportStatus() {
            return navigator.mediaDevices && navigator.mediaDevices.getUserMedia ? 'prompt' : 'unsupported';
        },

        getGeolocationSupportStatus() {
            return navigator.geolocation ? 'prompt' : 'unsupported';
        },

        async queryPermissionStatus(name, fallback = 'prompt') {
            try {
                const result = await navigator.permissions.query({ name });
                return result?.state || fallback;
            } catch (error) {
                return fallback;
            }
        },

        buildPrecheckMessage() {
            if (this.permissionStatus.secureContext === 'denied') {
                return 'Halaman ini belum berada di koneksi aman. Kamera biasanya tidak bisa dibuka kecuali melalui HTTPS atau localhost.';
            }

            if (this.permissionStatus.camera === 'denied' || this.permissionStatus.geolocation === 'denied') {
                return 'Ada izin yang ditolak. Aktifkan kembali permission di browser, lalu tekan "Cek Ulang Izin".';
            }

            if (this.permissionStatus.camera === 'unsupported' || this.permissionStatus.geolocation === 'unsupported') {
                return 'Browser atau device ini belum mendukung seluruh kebutuhan absensi.';
            }

            if (this.permissionStatus.camera === 'granted' && this.permissionStatus.geolocation === 'granted') {
                return 'Semua izin utama sudah siap. Anda bisa langsung memulai absensi.';
            }

            return 'Izin akan diminta bertahap saat Anda menekan tombol "Mulai Absensi".';
        },

        permissionStatusLabel(status) {
            switch (status) {
                case 'granted':
                    return 'Diizinkan';
                case 'denied':
                    return 'Ditolak';
                case 'prompt':
                    return 'Belum Dipilih';
                case 'unsupported':
                    return 'Tidak Didukung';
                case 'checking':
                    return 'Memeriksa';
                default:
                    return 'Tidak Diketahui';
            }
        },

        permissionStatusClass(status) {
            switch (status) {
                case 'granted':
                    return 'is-granted';
                case 'denied':
                    return 'is-denied';
                case 'prompt':
                    return 'is-prompt';
                case 'unsupported':
                    return 'is-unsupported';
                default:
                    return 'is-checking';
            }
        },

        startAbsensi() {
            this.cameraError = null;
            this.gpsError = null;
            this.requestGps();
        },

        handleClose() {
            this.$emit('update:show', false);
        },

        requestGps() {
            this.state = 'gps_check';
            this.gpsError = null;

            if (!navigator.geolocation) {
                this.gpsError = 'Browser Anda tidak mendukung Geolocation.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                // Success
                (position) => {
                    this.lat = position.coords.latitude;
                    this.long = position.coords.longitude;
                    this.permissionStatus.geolocation = 'granted';

                    // GPS acquired, move to camera
                    this.state = 'camera';
                },
                // Error
                (error) => {
                    console.error('GPS Error:', error);
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            this.permissionStatus.geolocation = 'denied';
                            this.gpsError = 'Mohon izinkan akses lokasi (GPS) pada browser Anda.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            this.gpsError = 'Informasi lokasi tidak tersedia saat ini.';
                            break;
                        case error.TIMEOUT:
                            this.gpsError = 'Waktu permintaan lokasi habis.';
                            break;
                        default:
                            this.gpsError = 'Terjadi kesalahan yang tidak diketahui saat mengambil lokasi.';
                            break;
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            );
        },

        handleCameraError(error) {
            if (error?.type === 'camera_access_denied') {
                this.permissionStatus.camera = 'denied';
            }
            this.cameraError = error.message;
        },

        handleCameraReady() {
            this.permissionStatus.camera = 'granted';
            this.cameraError = null;
        },

        retryCamera() {
            this.cameraError = null;
            this.permissionStatus.camera = 'prompt';
            this.cameraSessionKey += 1;
            this.state = 'camera';
        },

        async handleCaptured(captureData) {
            // Move to processing state
            this.state = 'processing';
            this.resultProps.status = 'loading';

            try {
                // Hit the Laravel endpoint
                await axios.post('/api/v1/lms/absensi', {
                    Id_Penjadwalan: this.activityItem?.id_penjadwalan,
                    Id_Penjadwalan_Detail: this.activityItem?.id_penjadwalan_detail,
                    Id_Penjadwalan_Det: this.activityItem?.id_penjadwalan_det ?? null,
                    Id_Materi: this.activityItem?.id_materi,
                    Id_Materi_Det: this.activityItem?.id_materi_det,
                    Urutan: this.activityItem?.urutan_materi ?? null,
                    Flag_Third_Party: this.activityItem?.aktivitas_third_party || 'T',
                    Output: this.type,
                    photo: captureData.imageBase64,
                    Latitude: this.lat,
                    Longitude: this.long,
                });

                // Success!
                this.state = 'result';
                this.resultProps.status = 'success';
                this.resultProps.timestamp = captureData.timestamp;
                this.resultProps.photoUrl = captureData.imageBase64; // Show local base64 instantly for better UX

                // Emit to parent that attendance is complete
                this.$emit('status-change', 'sudah_absensi');
                this.$emit('absensi-complete', {
                    type: this.type,
                    timestamp: captureData.timestamp,
                    status: 'success',
                });
            } catch (error) {
                console.error('API Upload Error:', error);

                this.state = 'result';
                this.resultProps.status = 'failed';

                if (error.response && error.response.data && error.response.data.message) {
                    this.resultProps.message = this.sanitizeApiErrorMessage(error.response.data.message);
                } else {
                    this.resultProps.message = 'Gagal menghubungi server. Periksa koneksi internet Anda.';
                }
            }
        },
        sanitizeApiErrorMessage(message) {
            const rawMessage = String(message || '').trim();

            if (!rawMessage) {
                return 'Terjadi kesalahan saat memproses absensi Anda. Silakan coba lagi.';
            }

            const lowerMessage = rawMessage.toLowerCase();
            const hasSqlDetail =
                lowerMessage.includes('sqlstate') ||
                lowerMessage.includes('odbc') ||
                lowerMessage.includes('sql server') ||
                lowerMessage.includes('deadlock') ||
                lowerMessage.includes('queryexception');

            if (hasSqlDetail) {
                return 'Absensi sedang diproses bersamaan. Silakan coba lagi beberapa detik lagi.';
            }

            return rawMessage;
        },

        handleRetry() {
            // Depending on what failed, we might go to camera directly if we have GPS
            if (this.lat && this.long) {
                this.retryCamera();
            } else {
                this.requestGps();
            }
        },
    },
};
</script>

<style scoped>
.absensi-orchestrator-container {
    width: 100%;
    min-height: 480px;
    display: flex;
    flex-direction: column;
}

.bi {
    display: inline-table;
}

.state-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.camera-toolbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.75rem;
}

.precheck-card {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0.5rem 0;
}

.precheck-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.precheck-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--dark, var(--hcis-slate-800));
}

.precheck-subtitle {
    color: var(--text-muted, var(--hcis-slate-500));
    line-height: 1.6;
}

.journey-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--primary, var(--hcis-indigo-500));
    font-size: 0.82rem;
    font-weight: 700;
    white-space: nowrap;
}

.precheck-section {
    padding: 1rem;
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.95) 0%, rgba(255, 255, 255, 1) 100%);
}

.section-title {
    margin: 0 0 0.9rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--dark, var(--hcis-slate-800));
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.9rem 0;
}

.permission-item + .permission-item {
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.permission-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.permission-icon.camera {
    background: rgba(99, 102, 241, 0.12);
    color: var(--primary, var(--hcis-indigo-500));
}

.permission-icon.location {
    background: rgba(16, 185, 129, 0.12);
    color: var(--success, var(--hcis-emerald-500));
}

.permission-icon.browser {
    background: rgba(245, 158, 11, 0.12);
    color: var(--warning, var(--hcis-amber-500));
}

.permission-content {
    flex: 1;
}

.permission-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.permission-name {
    font-weight: 700;
    color: var(--dark, var(--hcis-slate-800));
}

.permission-desc {
    font-size: 0.92rem;
    line-height: 1.5;
    color: var(--text-muted, var(--hcis-slate-500));
}

.permission-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.permission-badge.is-granted {
    background: rgba(16, 185, 129, 0.14);
    color: var(--success, var(--hcis-emerald-500));
}

.permission-badge.is-denied {
    background: rgba(239, 68, 68, 0.14);
    color: var(--danger, var(--hcis-red-500));
}

.permission-badge.is-prompt,
.permission-badge.is-checking {
    background: rgba(59, 130, 246, 0.12);
    color: var(--hcis-blue-600);
}

.permission-badge.is-unsupported {
    background: rgba(100, 116, 139, 0.16);
    color: var(--hcis-slate-600);
}

.guideline-list {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.guideline-item {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    color: var(--dark, var(--hcis-slate-700));
    line-height: 1.55;
}

.guideline-item i {
    color: var(--success, var(--hcis-emerald-500));
    margin-top: 0.15rem;
    flex-shrink: 0;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin: 0;
    padding: 0.95rem 1rem;
    border-radius: 12px;
    border: 1px solid transparent;
    line-height: 1.5;
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.18);
    color: #1d4ed8;
}

.precheck-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
}

/* Base override for spinner utility */
.spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 767px) {
    .precheck-header {
        flex-direction: column;
    }

    .permission-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .precheck-actions {
        flex-direction: column;
    }

    .precheck-actions .btn {
        width: 100%;
    }
}
</style>
