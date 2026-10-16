<template>
    <div class="fc">
        <!-- Biometric Stage HUD Container -->
        <div class="fc-panggung" :class="keadaan">
            <video ref="video" class="fc-video" autoplay playsinline muted></video>

            <!-- Futuristic Biometric Target Oval Overlay -->
            <svg class="fc-panduan" viewBox="0 0 300 400" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <mask id="fcLubang">
                        <rect width="300" height="400" fill="#fff" />
                        <ellipse cx="150" cy="185" rx="97" ry="127" fill="#000" />
                    </mask>
                    <linearGradient id="laserGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="var(--fc-glow, #3b82f6)" stop-opacity="0" />
                        <stop offset="50%" stop-color="var(--fc-glow, #3b82f6)" stop-opacity="0.8" />
                        <stop offset="100%" stop-color="var(--fc-glow, #3b82f6)" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <!-- Dark Vignette Mask -->
                <rect width="300" height="400" :fill="warnaTirai" mask="url(#fcLubang)" />

                <!-- Target Oval Line -->
                <ellipse cx="150" cy="185" rx="97" ry="127" class="fc-oval" />

                <!-- Animated Stability Progress Arc -->
                <ellipse
                    v-if="hitung > 0"
                    cx="150"
                    cy="185"
                    rx="97"
                    ry="127"
                    class="fc-cincin"
                    :style="{ strokeDasharray: kelilingOval, strokeDashoffset: kelilingOval * (1 - majuStabil) }"
                />
            </svg>

            <!-- Holographic Laser Line Sweeper when active -->
            <div v-if="!menyiapkan && !sedangJepret" class="fc-laser-sweep" :class="keadaan" aria-hidden="true"></div>

            <!-- Central Countdown Overlay -->
            <div v-if="hitung > 0" class="fc-hitung-wrap">
                <div class="fc-hitung">{{ hitung }}</div>
                <div class="fc-hitung-sub">Tahan posisi...</div>
            </div>

            <!-- Shutter Flash Effect -->
            <div v-if="kilat" class="fc-kilat" aria-hidden="true"></div>

            <!-- Loading Spinner State -->
            <div v-if="menyiapkan" class="fc-tunggu">
                <div class="spinner-border text-primary mb-2"></div>
                <p class="fw-bold mb-0">{{ pesanSiap }}</p>
            </div>
        </div>

        <!-- Dynamic Live Feedback Badge Bar -->
        <div class="fc-arah-card" :class="keadaan" role="status" aria-live="polite">
            <div class="fc-arah-icon-box">
                <i class="bi" :class="ikonArah"></i>
            </div>
            <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <strong class="fc-arah-judul">{{ judulArah }}</strong>
                    <span v-if="keadaan === 'pas'" class="fc-badge-live">
                        <span class="fc-live-dot"></span> Sesuai
                    </span>
                </div>
                <span class="fc-arah-sub">{{ subArah }}</span>
            </div>
        </div>

        <!-- Footer Control Bar -->
        <div class="fc-kaki">
            <div class="fc-coba">
                <span v-for="n in maxPercobaan" :key="n" class="fc-titik" :class="{ pakai: n <= percobaan }"></span>
                <span class="fc-coba-t"
                    >Percobaan {{ Math.min(percobaan + 1, maxPercobaan) }} / {{ maxPercobaan }}</span
                >
            </div>

            <button v-if="bolehManual" class="stitch-btn secondary sm fc-btn-manual" @click="jepretManual">
                <i class="bi bi-camera-fill me-1"></i> Ambil Foto Manual
            </button>
        </div>
    </div>
</template>

<script>
import { markRaw } from 'vue';
import * as mpFaceDetection from '@mediapipe/face_detection';

const VERSI_MP = '0.4.1646425229';
const KELILING = 704;

export default {
    name: 'SopFaceCapture',
    props: {
        stabilDetik: { type: Number, default: 2 },
        percobaanDetik: { type: Number, default: 15 },
        maxPercobaan: { type: Number, default: 3 },
        percobaanAwal: { type: Number, default: 0 },
    },
    emits: ['capture', 'error', 'gagal'],
    data() {
        return {
            menyiapkan: true,
            pesanSiap: 'Menyiapkan sensor biometrik...',
            stream: null,
            detektor: null,
            detektorMati: false,
            keadaan: 'cari', // cari | atur | pas
            judulArah: 'Menyiapkan kamera',
            subArah: 'Mohon tunggu sebentar...',
            hitung: 0,
            majuStabil: 0,
            mulaiStabil: null,
            sedangJepret: false,
            kilat: false,
            percobaan: this.percobaanAwal,
            rafId: null,
            loopId: null,
            batasId: null,
            prosesFrame: false,
        };
    },
    computed: {
        kelilingOval() {
            return KELILING;
        },
        bolehManual() {
            return this.detektorMati || this.percobaan >= this.maxPercobaan;
        },
        warnaTirai() {
            return (
                { pas: 'rgba(16,185,129,0.18)', atur: 'rgba(245,158,11,0.18)' }[this.keadaan] || 'rgba(15,23,42,0.48)'
            );
        },
        ikonArah() {
            return { pas: 'bi-check-circle-fill', atur: 'bi-arrows-move' }[this.keadaan] || 'bi-person-bounding-box';
        },
    },
    mounted() {
        this.nyalakan();
    },
    beforeUnmount() {
        this.matikan();
    },
    methods: {
        ctorDeteksi() {
            const kandidat = [
                mpFaceDetection?.FaceDetection,
                mpFaceDetection?.default?.FaceDetection,
                mpFaceDetection?.default,
                window?.FaceDetection,
            ];
            const c = kandidat.find((x) => typeof x === 'function');
            if (!c) throw new Error('Detektor wajah tidak tersedia.');
            return c;
        },
        async nyalakan() {
            try {
                if (!navigator.mediaDevices?.getUserMedia) throw new Error('kamera-tak-didukung');

                this.pesanSiap = 'Meminta izin kamera...';
                this.stream = markRaw(
                    await navigator.mediaDevices.getUserMedia({
                        audio: false,
                        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    }),
                );

                const v = this.$refs.video;
                v.srcObject = this.stream;
                v.setAttribute('playsinline', 'true');
                v.muted = true;
                await v.play();
                if (v.readyState < 2) {
                    await new Promise((r) => v.addEventListener('loadedmetadata', r, { once: true }));
                }
            } catch (e) {
                this.menyiapkan = false;
                this.$emit('error', this.pesanGalatKamera(e));
                return;
            }

            this.pesanSiap = 'Memuat modul AI biometrik...';
            try {
                const Ctor = this.ctorDeteksi();
                this.detektor = markRaw(
                    new Ctor({
                        locateFile: (f) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_detection@${VERSI_MP}/${f}`,
                    }),
                );
                this.detektor.setOptions({ model: 'full', selfieMode: true, minDetectionConfidence: 0.35 });
                this.detektor.onResults((r) => this.onHasil(r));
                if (typeof this.detektor.initialize === 'function') await this.detektor.initialize();

                this.menyiapkan = false;
                this.aturArah('cari', 'Posisikan Wajah Anda', 'Posisikan wajah tepat di dalam oval target.');
                this.mulaiLoop();
                this.mulaiBatas();
            } catch (e) {
                this.detektorMati = true;
                this.menyiapkan = false;
                this.aturArah(
                    'atur',
                    'Deteksi AI Mode Manual',
                    'Posisikan wajah di dalam oval, lalu tekan tombol “Ambil Foto Manual”.',
                );
            }
        },
        pesanGalatKamera(e) {
            if (!window.isSecureContext) return 'Kamera hanya bisa diakses lewat koneksi HTTPS atau localhost.';
            switch (e?.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                    return 'Izin kamera ditolak. Aktifkan izin kamera di peramban, lalu coba lagi.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'Perangkat kamera tidak ditemukan.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut dan coba lagi.';
                default:
                    return 'Kamera tidak dapat diakses pada perangkat ini.';
            }
        },
        mulaiLoop() {
            const v = this.$refs.video;
            const jalan = async () => {
                if (this.sedangJepret || !this.detektor || !v) return;
                if (!this.prosesFrame && v.readyState >= 2 && v.videoWidth > 0) {
                    this.prosesFrame = true;
                    try {
                        await this.detektor.send({ image: v });
                    } catch (e) {
                        /* ignore single frame error */
                    }
                    this.prosesFrame = false;
                }
                this.loopId = window.setTimeout(() => requestAnimationFrame(jalan), 120);
            };
            this.loopId = requestAnimationFrame(jalan);
        },
        mulaiBatas() {
            clearTimeout(this.batasId);
            this.batasId = window.setTimeout(() => {
                if (this.sedangJepret) return;
                this.percobaan += 1;
                this.$emit('gagal', this.percobaan);
                this.resetStabil();
                if (this.percobaan >= this.maxPercobaan) {
                    this.aturArah(
                        'atur',
                        'Mode Pengambilan Manual',
                        'Pencahayaan atau posisi belum optimal. Gunakan tombol manual di bawah.',
                    );
                } else {
                    this.aturArah('cari', 'Deteksi Belum Optimal', 'Dekatkan wajah dan pastikan ruang cukup terang.');
                    this.mulaiBatas();
                }
            }, this.percobaanDetik * 1000);
        },
        onHasil(r) {
            if (this.sedangJepret) return;

            const d = r?.detections?.[0];
            if (!d) {
                this.aturArah('cari', 'Wajah Belum Terdeteksi', 'Lihat lurus ke kamera dan tempatkan wajah di oval.');
                this.resetStabil();
                return;
            }

            const b = d.boundingBox;
            const skorMentah = Array.isArray(d.score) ? d.score[0] : d.score;
            const skor = typeof skorMentah === 'number' ? skorMentah : 1;
            if (!b) {
                this.aturArah('atur', 'Stabilkan Posisi', 'Tahan posisi kepala Anda sejenak.');
                this.resetStabil();
                return;
            }

            const cx = typeof b.xCenter === 'number' ? b.xCenter : (b.xMin || 0) + (b.width || 0) / 2;
            const cy = typeof b.yCenter === 'number' ? b.yCenter : (b.yMin || 0) + (b.height || 0) / 2;
            const w = b.width || 0;
            const h = b.height || 0;

            const diTengahX = cx > 0.2 && cx < 0.8;
            const diTengahY = cy > 0.18 && cy < 0.82;
            const cukupBesar = w > 0.16 || h > 0.22;
            const cukupYakin = skor >= 0.35;

            if (cukupYakin && diTengahX && diTengahY && cukupBesar) {
                this.aturArah('pas', 'Posisi Sempurna — Tahan!', 'Pengambilan foto otomatis dimulai...');
                this.tandaiStabil();
                return;
            }

            let sub = 'Sesuaikan posisi wajah Anda.';
            if (!cukupBesar) sub = 'Wajah terlalu jauh — dekatkan sedikit ke kamera.';
            else if (!diTengahX) sub = cx <= 0.2 ? 'Geser sedikit ke kanan.' : 'Geser sedikit ke kiri.';
            else if (!diTengahY) sub = cy <= 0.18 ? 'Turunkan sedikit posisi kepala.' : 'Angkat sedikit posisi kepala.';
            else if (!cukupYakin) sub = 'Wajah kurang jelas — tingkatkan pencahayaan atau buka penutup wajah.';

            this.aturArah('atur', 'Hampir Pas', sub);
            this.resetStabil();
        },
        aturArah(keadaan, judul, sub) {
            this.keadaan = keadaan;
            this.judulArah = judul;
            this.subArah = sub;
        },
        tandaiStabil() {
            if (this.mulaiStabil) return;
            this.mulaiStabil = Date.now();
            this.rafId = requestAnimationFrame(this.periksaStabil);
        },
        periksaStabil() {
            if (this.keadaan !== 'pas' || this.sedangJepret) {
                this.resetStabil();
                return;
            }

            const lewat = Date.now() - this.mulaiStabil;
            const total = this.stabilDetik * 1000;
            this.majuStabil = Math.min(1, lewat / total);

            if (lewat >= total) {
                this.hitung = 0;
                this.jepret();
                return;
            }
            this.hitung = Math.ceil((total - lewat) / 1000);
            this.rafId = requestAnimationFrame(this.periksaStabil);
        },
        resetStabil() {
            this.mulaiStabil = null;
            this.hitung = 0;
            this.majuStabil = 0;
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }
        },
        jepretManual() {
            this.jepret();
        },
        jepret() {
            const v = this.$refs.video;
            if (!v || !v.videoWidth || this.sedangJepret) return;

            this.sedangJepret = true;
            this.kilat = true;
            setTimeout(() => {
                this.kilat = false;
            }, 120);

            const lebar = Math.min(640, v.videoWidth);
            const skala = lebar / v.videoWidth;
            const c = document.createElement('canvas');
            c.width = lebar;
            c.height = Math.round(v.videoHeight * skala);
            const ctx = c.getContext('2d');
            ctx.translate(c.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(v, 0, 0, c.width, c.height);

            const data = c.toDataURL('image/jpeg', 0.7);
            this.matikan();
            this.$emit('capture', data);
        },
        matikan() {
            this.sedangJepret = true;
            clearTimeout(this.loopId);
            clearTimeout(this.batasId);
            if (this.rafId) cancelAnimationFrame(this.rafId);
            this.rafId = null;
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
            if (this.detektor?.close) {
                try {
                    this.detektor.close();
                } catch (e) {
                    /* ignore */
                }
            }
            this.detektor = null;
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.fc {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
}

/* --fc-sisa = tinggi yang dipakai SELAIN panggung (stepper, arahan, kaki,
   padding kartu, remah). Panggung dibatasi lebar DAN tinggi: dengan rasio 3/4,
   tinggi = lebar x 4/3, jadi agar muat pada sisa layar lebarnya tak boleh
   melebihi sisa x 0,75. Tanpa batas kedua ini, di ponsel arahan dan tombol
   "ambil foto manual" jatuh di bawah lipatan — tepat saat dibutuhkan. */
.fc {
    --fc-sisa: 292px;
    --fc-lebar: min(380px, 90vw, calc((100dvh - var(--fc-sisa)) * 0.75));
}

.fc-panggung {
    position: relative;
    width: var(--fc-lebar);
    aspect-ratio: 3 / 4;
    border-radius: 24px;
    overflow: hidden;
    background: #0b0f19;
    border: 2px solid rgba(219, 225, 234, 0.6);
    box-shadow:
        0 30px 70px -30px rgba(15, 23, 42, 0.65),
        0 0 0 1px rgba(255, 255, 255, 0.05);
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
    --fc-glow: var(--hcis-indigo-500);
}

.fc-panggung.atur {
    border-color: var(--hcis-amber-500);
    box-shadow: 0 30px 70px -25px rgba(245, 158, 11, 0.4);
    --fc-glow: var(--hcis-amber-500);
}

.fc-panggung.pas {
    border-color: var(--hcis-emerald-500);
    box-shadow:
        0 30px 70px -25px rgba(16, 185, 129, 0.55),
        0 0 25px rgba(16, 185, 129, 0.3);
    --fc-glow: var(--hcis-emerald-500);
}

.fc-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}

.fc-panduan {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.fc-oval {
    fill: none;
    stroke: rgba(255, 255, 255, 0.65);
    stroke-width: 2.2;
    stroke-dasharray: 8 6;
    transition:
        stroke 0.3s ease,
        stroke-width 0.3s ease;
}

.fc-panggung.pas .fc-oval {
    stroke: var(--hcis-emerald-400);
    stroke-dasharray: none;
    stroke-width: 3;
    filter: drop-shadow(0 0 8px rgba(52, 211, 153, 0.8));
}

.fc-panggung.atur .fc-oval {
    stroke: var(--hcis-amber-400);
    stroke-width: 2.5;
}

.fc-cincin {
    fill: none;
    stroke: var(--hcis-emerald-400);
    stroke-width: 6;
    stroke-linecap: round;
    transform: rotate(-90deg);
    transform-origin: 150px 185px;
    transition: stroke-dashoffset 0.12s linear;
    filter: drop-shadow(0 0 10px rgba(52, 211, 153, 0.9));
}

/* Laser Scanning Beam Line */
.fc-laser-sweep {
    position: absolute;
    left: 12%;
    width: 76%;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--fc-glow), transparent);
    box-shadow:
        0 0 12px var(--fc-glow),
        0 0 24px var(--fc-glow);
    border-radius: 999px;
    animation: laserScan 2.4s ease-in-out infinite alternate;
    pointer-events: none;
}
@keyframes laserScan {
    0% {
        top: 18%;
        opacity: 0.2;
    }
    50% {
        opacity: 0.9;
    }
    100% {
        top: 74%;
        opacity: 0.2;
    }
}

/* Central Countdown UI */
.fc-hitung-wrap {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.3);
    backdrop-filter: blur(2px);
    pointer-events: none;
}

.fc-hitung {
    font-size: 4.8rem;
    font-weight: 900;
    color: var(--hcis-white);
    line-height: 1;
    text-shadow:
        0 4px 30px rgba(0, 0, 0, 0.8),
        0 0 20px rgba(52, 211, 153, 0.9);
    animation: countPulse 0.9s cubic-bezier(0.34, 1.56, 0.64, 1) infinite;
}
@keyframes countPulse {
    0% {
        transform: scale(0.7);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.1);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 0.9;
    }
}

.fc-hitung-sub {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-200);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
}

.fc-kilat {
    position: absolute;
    inset: 0;
    background: var(--hcis-white);
    animation: fcFlash 0.15s ease-out;
}
@keyframes fcFlash {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

.fc-tunggu {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.82);
    color: var(--hcis-slate-100);
    text-align: center;
    padding: 1.5rem;
    backdrop-filter: blur(8px);
}

/* Feedback Card */
.fc-arah-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: var(--fc-lebar);
    padding: 0.75rem 0.95rem;
    border-radius: 16px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 10px 30px -18px rgba(15, 23, 42, 0.15);
    transition: all 0.25s ease;
}

.fc-arah-card.atur {
    background: #fffbebf5;
    border-color: var(--hcis-amber-200);
}
.fc-arah-card.pas {
    background: #ecfdf5f5;
    border-color: var(--hcis-emerald-200);
}

.fc-arah-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    flex-shrink: 0;
}
.fc-arah-card.atur .fc-arah-icon-box {
    color: var(--hcis-amber-600);
    background: rgba(245, 158, 11, 0.15);
}
.fc-arah-card.pas .fc-arah-icon-box {
    color: var(--hcis-emerald-600);
    background: rgba(16, 185, 129, 0.15);
}

.fc-arah-judul {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
}

.fc-arah-sub {
    display: block;
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    line-height: 1.4;
}

.fc-badge-live {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.15);
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.fc-live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    animation: livePulse 1.2s ease-in-out infinite;
}
@keyframes livePulse {
    50% {
        opacity: 0.3;
    }
}

.fc-kaki {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
    justify-content: center;
}

.fc-coba {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.fc-titik {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-slate-300);
    transition: background 0.25s ease;
}
.fc-titik.pakai {
    background: var(--hcis-amber-500);
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
}

.fc-coba-t {
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-left: 0.2rem;
}

.fc-btn-manual {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

@media (prefers-reduced-motion: reduce) {
    .fc-laser-sweep,
    .fc-hitung,
    .fc-kilat,
    .fc-live-dot {
        animation: none;
    }
}

/* ══════════════ Ponsel ══════════════
   Yang dikecilkan hanya yang memang berlebihan di layar sempit; proporsinya
   dijaga lewat satu variabel lebar, bukan menyetel tiap elemen satu per satu. */
@media (max-width: 700px) {
    .fc {
        gap: 0.6rem;
    }
    .fc-panggung {
        border-radius: 18px;
        border-width: 1.5px;
    }
    .fc-hitung {
        font-size: 2.6rem;
    }
    .fc-arah {
        padding: 0.5rem 0.65rem;
        border-radius: 11px;
    }
    .fc-arah strong {
        font-size: 0.79rem;
    }
    .fc-arah span {
        font-size: 0.71rem;
    }
    .fc-kaki {
        gap: 0.6rem;
    }
    .fc-coba-t {
        font-size: 0.67rem;
    }
}
@media (max-width: 400px) {
    /* Layar sangat pendek (SE): sisakan lebih sedikit agar panggung tak
       menyusut sampai wajah tak terbaca detektor. */
    .fc {
        --fc-sisa: 268px;
    }
    .fc-hitung {
        font-size: 2.2rem;
    }
}
</style>
