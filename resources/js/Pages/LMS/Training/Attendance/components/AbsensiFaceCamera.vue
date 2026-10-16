<template>
    <div class="camera-container">
        <!-- Main Camera View -->
        <div class="video-wrapper">
            <video ref="videoRef" class="camera-video" autoplay playsinline muted></video>

            <!-- Guide Overlay Canvas -->
            <canvas ref="canvasRef" class="overlay-canvas"></canvas>

            <!-- Guide Frame overlay (CSS driven for smoother animation) -->
            <div
                class="guide-frame"
                :class="[frameState, { 'is-capturing': isCapturing }, { 'is-countdown': countdown > 0 }]"
            >
                <!-- Countdown Overlay -->
                <transition name="fade-scale">
                    <div v-if="countdown > 0" class="countdown-display">
                        {{ countdown }}
                    </div>
                </transition>
            </div>

            <!-- Flash effect -->
            <transition name="flash">
                <div v-if="showFlash" class="flash-overlay"></div>
            </transition>

            <div class="camera-status-pill" :class="frameState">
                <i class="bi" :class="statusIcon"></i>
                <span>{{ statusText }}</span>
            </div>
        </div>

        <!-- Instructions Panel -->
        <div class="instructions-panel">
            <div class="instruction-icon" :class="frameState">
                <i v-if="frameState === 'no_face'" class="bi bi-person-bounding-box"></i>
                <i v-else-if="frameState === 'adjusting'" class="bi bi-arrow-down-up"></i>
                <i v-else-if="frameState === 'valid'" class="bi bi-check-circle-fill"></i>
            </div>

            <div class="instruction-text-wrapper">
                <h4 class="instruction-title" :class="`text-${frameState}`">
                    {{ instructionTitle }}
                </h4>
                <p class="instruction-desc">
                    {{ instructionDesc }}
                </p>
            </div>
        </div>

        <!-- Loading State during initialization -->
        <div v-if="isInitializing" class="init-overlay">
            <span class="loading-spinner"></span>
            <p>Mempersiapkan Kamera...</p>
        </div>
    </div>
</template>

<script>
import { markRaw } from 'vue';
import * as mpFaceDetection from '@mediapipe/face_detection';

const MEDIAPIPE_FACE_DETECTION_VERSION = '0.4.1646425229';

export default {
    name: 'AbsensiFaceCamera',
    props: {
        autoCapture: {
            type: Boolean,
            default: true,
        },
        stableSeconds: {
            type: Number,
            default: 2, // Seconds required to hold still before capture
        },
        minConfidence: {
            type: Number,
            default: 0.75,
        },
    },
    data() {
        return {
            isInitializing: true,
            mediaStream: null,
            faceDetection: null,

            // State
            frameState: 'no_face', // no_face, adjusting, valid
            confidence: 0,

            // Capture logic
            stableStartTime: null,
            countdown: 0,
            isCapturing: false,
            showFlash: false,
            animationFrameId: null,

            // Text
            instructionTitle: 'Posisikan Wajah Anda',
            instructionDesc: 'Arahkan wajah Anda ke dalam oval hingga garis berwarna hijau.',

            // Canvas context
            ctx: null,
            frameProcessorId: null,
            isProcessingFrame: false,
        };
    },
    computed: {
        statusIcon() {
            if (this.frameState === 'valid') {
                return 'bi-check-circle-fill';
            }

            if (this.frameState === 'adjusting') {
                return 'bi-arrows-move';
            }

            return 'bi-person-bounding-box';
        },
        statusText() {
            if (this.frameState === 'valid') {
                return `Wajah terdeteksi ${this.confidence ? `(${this.confidence}%)` : ''}`.trim();
            }

            if (this.frameState === 'adjusting') {
                return 'Wajah terdeteksi, sesuaikan posisi';
            }

            return 'Mencari wajah...';
        },
    },
    mounted() {
        this.initCamera();
    },
    beforeUnmount() {
        this.stopCamera();
    },
    methods: {
        resolveFaceDetectionConstructor() {
            const candidates = [
                mpFaceDetection?.FaceDetection,
                mpFaceDetection?.default?.FaceDetection,
                mpFaceDetection?.default,
                window?.FaceDetection,
            ];

            const ctor = candidates.find((candidate) => typeof candidate === 'function');

            if (!ctor) {
                console.error('MediaPipe face_detection export shape:', mpFaceDetection);
                throw new Error('FaceDetection constructor tidak ditemukan dari package MediaPipe.');
            }

            return ctor;
        },

        async initCamera() {
            this.isInitializing = true;
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Browser Anda tidak mendukung akses kamera.');
                }

                const videoElement = this.$refs.videoRef;
                const canvasElement = this.$refs.canvasRef;
                this.ctx = canvasElement.getContext('2d');

                // Initialize Face Detection
                const FaceDetectionCtor = this.resolveFaceDetectionConstructor();
                this.faceDetection = markRaw(
                    new FaceDetectionCtor({
                    locateFile: (file) => {
                        return `https://cdn.jsdelivr.net/npm/@mediapipe/face_detection@${MEDIAPIPE_FACE_DETECTION_VERSION}/${file}`;
                    },
                    }),
                );

                this.faceDetection.setOptions({
                    model: 'full',
                    selfieMode: true,
                    minDetectionConfidence: 0.35,
                });

                this.faceDetection.onResults((results) => this.onFaceDetected(results));
                if (typeof this.faceDetection.initialize === 'function') {
                    await this.faceDetection.initialize();
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                    },
                });

                this.mediaStream = markRaw(stream);
                videoElement.srcObject = stream;
                videoElement.setAttribute('playsinline', 'true');
                videoElement.muted = true;

                await videoElement.play();
                await new Promise((resolve) => {
                    if (videoElement.readyState >= 2) {
                        resolve();
                        return;
                    }

                    const onLoadedMetadata = () => {
                        videoElement.removeEventListener('loadedmetadata', onLoadedMetadata);
                        resolve();
                    };

                    videoElement.addEventListener('loadedmetadata', onLoadedMetadata);
                });

                this.startFrameProcessing();
                this.isInitializing = false;
                this.$emit('ready');
            } catch (error) {
                console.error('Camera Initialization Error:', error);
                this.stopCamera();
                this.isCapturing = false;
                this.isInitializing = false;
                this.$emit('error', {
                    type: 'camera_access_denied',
                    message: this.getCameraErrorMessage(error),
                });
            }
        },

        startFrameProcessing() {
            const videoElement = this.$refs.videoRef;
            const canvasElement = this.$refs.canvasRef;

            const processFrame = async () => {
                if (this.isCapturing || !this.faceDetection || !videoElement) {
                    return;
                }

                if (
                    !this.isProcessingFrame &&
                    videoElement.readyState >= 2 &&
                    videoElement.videoWidth > 0 &&
                    videoElement.videoHeight > 0
                ) {
                    if (canvasElement.width !== videoElement.videoWidth) {
                        canvasElement.width = videoElement.videoWidth;
                        canvasElement.height = videoElement.videoHeight;
                    }

                    this.isProcessingFrame = true;

                    try {
                        await this.faceDetection.send({ image: videoElement });
                    } catch (error) {
                        console.error('Face detection frame error:', error);
                    } finally {
                        this.isProcessingFrame = false;
                    }
                }

                this.frameProcessorId = window.setTimeout(() => {
                    requestAnimationFrame(processFrame);
                }, 120);
            };

            this.frameProcessorId = requestAnimationFrame(processFrame);
        },

        getCameraErrorMessage(error) {
            if (!window.isSecureContext) {
                return 'Kamera hanya bisa diakses pada halaman HTTPS atau localhost.';
            }

            switch (error?.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                    return 'Akses kamera ditolak. Mohon izinkan kamera pada browser lalu coba lagi.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'Perangkat kamera tidak ditemukan di device ini.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi lain lalu coba lagi.';
                case 'OverconstrainedError':
                case 'ConstraintNotSatisfiedError':
                    return 'Konfigurasi kamera tidak didukung oleh device ini.';
                default:
                    return error?.message || 'Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.';
            }
        },

        stopCamera() {
            this.isCapturing = true; // Stop processing frames
            if (this.frameProcessorId) {
                clearTimeout(this.frameProcessorId);
                cancelAnimationFrame(this.frameProcessorId);
                this.frameProcessorId = null;
            }
            this.isProcessingFrame = false;
            if (this.faceDetection) {
                try {
                    this.faceDetection.close();
                } catch (error) {
                    console.error('FaceDetection close error:', error);
                }
                this.faceDetection = null;
            }
            if (this.mediaStream) {
                this.mediaStream.getTracks().forEach((track) => track.stop());
                this.mediaStream = null;
            }

            const videoElement = this.$refs.videoRef;
            if (videoElement && videoElement.srcObject) {
                videoElement.srcObject = null;
            }
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
        },

        onFaceDetected(results) {
            if (this.isCapturing) return;
            const canvas = this.$refs.canvasRef;

            if (results?.detections?.length > 0) {
                const detection = results.detections[0];
                const bbox = detection.boundingBox;
                const rawScore = Array.isArray(detection?.score) ? detection.score[0] : detection?.score;
                const score = typeof rawScore === 'number' ? rawScore : 1;

                this.confidence = Math.round(score * 100);

                if (!bbox) {
                    this.setFrameState('adjusting', 'Wajah Terdeteksi', 'Wajah terlihat, tetapi posisi belum stabil.');
                    this.resetCaptureTimer();
                    return;
                }

                const normalizedCenterX =
                    typeof bbox.xCenter === 'number' ? bbox.xCenter : (bbox.xMin || 0) + (bbox.width || 0) / 2;
                const normalizedCenterY =
                    typeof bbox.yCenter === 'number' ? bbox.yCenter : (bbox.yMin || 0) + (bbox.height || 0) / 2;
                const normalizedWidth = bbox.width || 0;
                const normalizedHeight = bbox.height || 0;

                const isCenteredX = normalizedCenterX > 0.2 && normalizedCenterX < 0.8;
                const isCenteredY = normalizedCenterY > 0.18 && normalizedCenterY < 0.82;
                const isLargeEnough = normalizedWidth > 0.16 || normalizedHeight > 0.22;
                const isConfidentEnough = score >= 0.35;

                if (isConfidentEnough && isCenteredX && isCenteredY && isLargeEnough) {
                    this.setFrameState('valid', 'Wajah Terdeteksi', 'Tahan posisi ini...');
                    this.handleStableFace();
                } else {
                    this.setFrameState(
                        'adjusting',
                        'Sedikit Lagi',
                        'Wajah sudah terlihat, posisikan lebih ke tengah dan dekatkan sedikit.',
                    );
                    this.resetCaptureTimer();
                }
            } else {
                this.setFrameState('no_face', 'Wajah Tidak Ditemukan', 'Posisikan wajah Anda di depan kamera.');
                this.resetCaptureTimer();
            }
        },

        setFrameState(state, title, desc) {
            if (this.frameState !== state || this.instructionTitle !== title || this.instructionDesc !== desc) {
                this.frameState = state;
                this.instructionTitle = title;
                this.instructionDesc = desc;
            }
        },

        handleStableFace() {
            if (!this.autoCapture) return;

            if (!this.stableStartTime) {
                this.stableStartTime = Date.now();
                this.animationFrameId = requestAnimationFrame(this.checkCaptureReady.bind(this));
            }
        },

        checkCaptureReady() {
            if (this.frameState !== 'valid' || this.isCapturing) {
                this.resetCaptureTimer();
                return;
            }

            const elapsed = Date.now() - this.stableStartTime;
            const remaining = this.stableSeconds * 1000 - elapsed;

            if (remaining <= 0) {
                this.countdown = 0;
                this.captureImage();
            } else {
                // Determine countdown string (3, 2, 1)
                this.countdown = Math.ceil(remaining / 1000);
                this.animationFrameId = requestAnimationFrame(this.checkCaptureReady.bind(this));
            }
        },

        resetCaptureTimer() {
            this.stableStartTime = null;
            this.countdown = 0;
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
        },

        async captureImage() {
            this.isCapturing = true;
            this.showFlash = true;

            // Play shutter sound if needed

            setTimeout(() => {
                this.showFlash = false;
            }, 100);

            // Draw current video frame to canvas to get base64
            const video = this.$refs.videoRef;
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');

            // Draw
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Get base64 Data URL (JPEG for smaller size)
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.85);

            // Emit success
            this.$emit('captured', {
                imageBase64,
                confidence: this.confidence,
                timestamp: new Date().toISOString(),
            });

            this.stopCamera();
        },
    },
};
</script>

<style scoped>
.camera-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
    margin: 0 auto;
}

.video-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 3/4;
    border-radius: 16px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

@media (min-width: 768px) {
    .video-wrapper {
        aspect-ratio: 4/3;
    }
}

.camera-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1); /* Mirror effect */
}

.overlay-canvas {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    transform: scaleX(-1); /* Keep aligned with video */
}

/* Guide Frame using CSS for smoother performance than canvas drawing */
.guide-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 65%;
    height: 70%;
    border-radius: 50%; /* Oval */
    border: 4px dashed rgba(255, 255, 255, 0.6);
    box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.5); /* Dim background outside oval */
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;

    display: flex;
    align-items: center;
    justify-content: center;
}

@media (min-width: 768px) {
    .guide-frame {
        width: 45%;
        height: 75%;
    }
}

/* State Colors based on CSS Vars */
.guide-frame.no_face {
    border-color: var(--danger, var(--hcis-red-500));
    box-shadow:
        0 0 0 4000px rgba(239, 68, 68, 0.15),
        0 0 30px rgba(239, 68, 68, 0.4) inset;
}

.guide-frame.adjusting {
    border-color: var(--warning, var(--hcis-amber-500));
    border-style: solid;
    box-shadow:
        0 0 0 4000px rgba(245, 158, 11, 0.15),
        0 0 30px rgba(245, 158, 11, 0.4) inset;
}

.guide-frame.valid {
    border-color: var(--success, var(--hcis-emerald-500));
    border-style: solid;
    background: rgba(16, 185, 129, 0.1);
    box-shadow:
        0 0 0 4000px rgba(0, 0, 0, 0.6),
        0 0 40px rgba(16, 185, 129, 0.5) inset;
    animation: pulse-green 2s infinite;
}

.guide-frame.is-capturing {
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.8) !important;
    transform: translate(-50%, -50%) scale(1.1);
}

@keyframes pulse-green {
    0% {
        box-shadow:
            0 0 0 4000px rgba(0, 0, 0, 0.6),
            0 0 0px rgba(16, 185, 129, 0.6) inset;
    }
    50% {
        box-shadow:
            0 0 0 4000px rgba(0, 0, 0, 0.6),
            0 0 40px rgba(16, 185, 129, 0.6) inset;
    }
    100% {
        box-shadow:
            0 0 0 4000px rgba(0, 0, 0, 0.6),
            0 0 0px rgba(16, 185, 129, 0.6) inset;
    }
}

/* Countdown */
.countdown-display {
    font-size: 5rem;
    font-weight: 800;
    color: white;
    text-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    z-index: 10;
}

.fade-scale-enter-active,
.fade-scale-leave-active {
    transition: all 0.2s ease;
}
.fade-scale-enter-from {
    opacity: 0;
    transform: scale(1.5);
}
.fade-scale-leave-to {
    opacity: 0;
    transform: scale(0.8);
}

/* Flash */
.flash-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: white;
    z-index: 20;
}

.flash-enter-active {
    transition: opacity 0.05s ease;
}
.flash-leave-active {
    transition: opacity 0.5s ease;
}
.flash-enter-from,
.flash-leave-to {
    opacity: 0;
}

.camera-status-pill {
    position: absolute;
    right: 16px;
    bottom: 16px;
    z-index: 12;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.8rem;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    backdrop-filter: blur(8px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18);
}

.camera-status-pill.no_face {
    background: rgba(127, 29, 29, 0.72);
    color: #fff;
}

.camera-status-pill.adjusting {
    background: rgba(146, 64, 14, 0.76);
    color: #fff;
}

.camera-status-pill.valid {
    background: rgba(6, 95, 70, 0.78);
    color: var(--hcis-emerald-50);
}

/* Instructions */
.instructions-panel {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: var(--surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
}

.instruction-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.instruction-icon.no_face {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger, var(--hcis-red-500));
}

.instruction-icon.adjusting {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning, var(--hcis-amber-500));
}

.instruction-icon.valid {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success, var(--hcis-emerald-500));
}

.instruction-text-wrapper {
    flex: 1;
}

.instruction-title {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text, var(--hcis-slate-700));
    transition: color 0.3s ease;
}

.text-no_face {
    color: var(--danger, var(--hcis-red-500));
}
.text-adjusting {
    color: var(--warning, var(--hcis-amber-500));
}
.text-valid {
    color: var(--success, var(--hcis-emerald-500));
}

.instruction-desc {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-muted, var(--hcis-slate-500));
    line-height: 1.4;
}

/* Loader */
.init-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--surface, var(--hcis-white));
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 30;
    gap: 1rem;
    color: var(--text, var(--hcis-slate-700));
    font-weight: 500;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border, var(--hcis-slate-200));
    border-top: 3px solid var(--primary, var(--hcis-indigo-500));
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>
