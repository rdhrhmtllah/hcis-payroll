<template>
    <HcisDialog
        :model-value="modelValue"
        :title="dialogTitle"
        kicker="PENJADWALAN RATER"
        :sentiment="dialogSentiment"
        :icon="dialogIcon"
        size="md"
        variant="glass"
        sticky-footer
        :closable="latar || !loading"
        @update:model-value="onDialogClose"
    >
        <div class="pa-liquid-modal">
            <!-- 1. HERO LIQUID FILL ORB CONTAINER -->
            <div class="pa-liquid-orb-wrap">
                <!-- Concentric Glowing Outer Orbital Ring -->
                <div class="pa-liquid-orbital-ring" :class="`is-${status}`" aria-hidden="true">
                    <svg class="pa-liquid-orbit-svg" viewBox="0 0 240 240">
                        <circle
                            class="pa-liquid-orbit-track"
                            cx="120"
                            cy="120"
                            r="112"
                        />
                        <circle
                            class="pa-liquid-orbit-progress"
                            cx="120"
                            cy="120"
                            r="112"
                            :style="{
                                strokeDashoffset: orbitDashOffset,
                            }"
                        />
                    </svg>
                </div>

                <!-- 3D Glass Liquid Sphere -->
                <div class="pa-liquid-vessel" :class="`is-${status}`">
                    <svg class="pa-liquid-svg" viewBox="0 0 200 200" aria-hidden="true">
                        <defs>
                            <!-- Sphere Clip -->
                            <clipPath id="paLiquidClip">
                                <circle cx="100" cy="100" r="94" />
                            </clipPath>

                            <!-- Water Gradient: Brand / Active -->
                            <linearGradient id="paLiquidGradBrandFront" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#38bdf8" />
                                <stop offset="40%" stop-color="#0284c7" />
                                <stop offset="100%" stop-color="#1e40af" />
                            </linearGradient>
                            <linearGradient id="paLiquidGradBrandBack" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#0369a1" stop-opacity="0.75" />
                            </linearGradient>

                            <!-- Water Gradient: Success / 100% -->
                            <linearGradient id="paLiquidGradSuccessFront" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#34d399" />
                                <stop offset="40%" stop-color="#059669" />
                                <stop offset="100%" stop-color="#065f46" />
                            </linearGradient>
                            <linearGradient id="paLiquidGradSuccessBack" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#6ee7b7" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#047857" stop-opacity="0.75" />
                            </linearGradient>

                            <!-- Water Gradient: Warning / Error -->
                            <linearGradient id="paLiquidGradWarnFront" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#fbbf24" />
                                <stop offset="50%" stop-color="#d97706" />
                                <stop offset="100%" stop-color="#92400e" />
                            </linearGradient>
                            <linearGradient id="paLiquidGradWarnBack" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#fde68a" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#b45309" stop-opacity="0.75" />
                            </linearGradient>

                            <!-- Top-Left 3D Specular Shine -->
                            <radialGradient id="paGlassSpecular" cx="35%" cy="25%" r="60%">
                                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.45" />
                                <stop offset="35%" stop-color="#ffffff" stop-opacity="0.12" />
                                <stop offset="100%" stop-color="#ffffff" stop-opacity="0.2" />
                            </radialGradient>
                        </defs>

                        <!-- Background Ambient Fluid Chamber -->
                        <circle cx="100" cy="100" r="94" class="pa-liquid-chamber-bg" />

                        <!-- Fluid Content (Clipped to Sphere) -->
                        <g clip-path="url(#paLiquidClip)">
                            <!-- Back Wave Layer -->
                            <g :transform="`translate(0, ${waterLevelY})`">
                                <path
                                    class="pa-wave pa-wave--back"
                                    :fill="backGradientUrl"
                                    d="M 0,0 C 50,-12 100,12 150,-6 C 200,-24 250,6 300,-10 C 350,-26 400,4 450,-8 L 450,220 L 0,220 Z"
                                />
                            </g>

                            <!-- Front Wave Layer -->
                            <g :transform="`translate(0, ${waterLevelY})`">
                                <path
                                    class="pa-wave pa-wave--front"
                                    :fill="frontGradientUrl"
                                    d="M 0,0 C 60,14 110,-14 170,8 C 230,26 280,-10 340,12 C 400,30 450,-6 500,10 L 500,220 L 0,220 Z"
                                />
                            </g>

                            <!-- Rising Bubbles in Fluid -->
                            <g v-if="loading" class="pa-liquid-bubbles">
                                <circle class="pa-bubble b1" cx="80" cy="160" r="4" />
                                <circle class="pa-bubble b2" cx="120" cy="180" r="3" />
                                <circle class="pa-bubble b3" cx="60" cy="170" r="2.5" />
                                <circle class="pa-bubble b4" cx="140" cy="150" r="3.5" />
                                <circle class="pa-bubble b5" cx="100" cy="190" r="2" />
                            </g>
                        </g>

                        <!-- 3D Glass Specular Reflection Highlight -->
                        <circle cx="100" cy="100" r="94" fill="url(#paGlassSpecular)" pointer-events="none" />
                        <ellipse cx="70" cy="45" rx="35" ry="16" transform="rotate(-25 70 45)" fill="rgba(255,255,255,0.22)" pointer-events="none" />
                    </svg>

                    <!-- Central Floating Percentage & Status Pill -->
                    <div class="pa-liquid-center-readout">
                        <div class="pa-liquid-percent">
                            <span class="pa-liquid-num">{{ Math.round(persen) }}</span>
                            <span class="pa-liquid-symbol">%</span>
                        </div>
                        <small class="pa-liquid-percent-caption">{{ keteranganPersen }}</small>
                        <div class="pa-liquid-status-badge" :class="`is-${status}`">
                            <HcisIcon
                                :name="statusIcon"
                                :animation="loading || status === 'menunggu' ? 'spin' : null"
                                size="2xs"
                            />
                            <span>{{ statusKicker }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. PHASE TITLE & CURRENT ACTION NOTICE -->
            <div class="pa-liquid-info-section">
                <div class="pa-liquid-headline">
                    <h4>{{ headlineTitle }}</h4>
                    <p class="pa-liquid-desc">{{ headlineSubtitle }}</p>
                </div>

                <!-- 3. LIVE PIPELINE STEP TRACKER -->
                <div class="pa-liquid-pipeline">
                    <div
                        class="pa-pipeline-step"
                        :class="{
                            'is-done': phaseIndex > 1 || status === 'success',
                            'is-active': phaseIndex === 1 && status === 'running',
                            'is-error': phaseIndex === 1 && status === 'error',
                        }"
                    >
                        <div class="pa-pipeline-dot">
                            <HcisIcon
                                :name="phaseIndex > 1 || status === 'success' ? 'check-lg' : (phaseIndex === 1 && status === 'running' ? 'arrow-repeat' : 'lock-fill')"
                                :animation="phaseIndex === 1 && status === 'running' ? 'spin' : null"
                                size="2xs"
                            />
                        </div>
                        <span class="pa-pipeline-label">1. Validasi Parameter</span>
                    </div>

                    <div class="pa-pipeline-connector" :class="{ 'is-done': phaseIndex >= 2 || status === 'success' || status === 'menunggu' }"></div>

                    <div
                        class="pa-pipeline-step"
                        :class="{
                            'is-done': status === 'success' || status === 'menunggu',
                            'is-active': phaseIndex === 2 && status === 'running',
                            'is-error': phaseIndex === 2 && status === 'error',
                        }"
                    >
                        <div class="pa-pipeline-dot">
                            <HcisIcon
                                :name="status === 'success' || status === 'menunggu' ? 'check-lg' : (phaseIndex === 2 && status === 'running' ? 'arrow-repeat' : 'send-fill')"
                                :animation="phaseIndex === 2 && status === 'running' ? 'spin' : null"
                                size="2xs"
                            />
                        </div>
                        <span class="pa-pipeline-label">2. Kirim Jadwal</span>
                    </div>

                    <div class="pa-pipeline-connector" :class="{ 'is-done': status === 'success' }"></div>

                    <div
                        class="pa-pipeline-step"
                        :class="{
                            'is-done': status === 'success',
                            'is-active': status === 'menunggu',
                        }"
                    >
                        <div class="pa-pipeline-dot">
                            <HcisIcon
                                :name="status === 'success' ? 'check-lg' : (status === 'menunggu' ? 'arrow-repeat' : 'hourglass-split')"
                                :animation="status === 'menunggu' ? 'spin' : null"
                                size="2xs"
                            />
                        </div>
                        <span class="pa-pipeline-label">3. Respons HC Learn</span>
                    </div>

                    <div class="pa-pipeline-connector" :class="{ 'is-done': status === 'success' }"></div>

                    <div
                        class="pa-pipeline-step"
                        :class="{ 'is-done': status === 'success' }"
                    >
                        <div class="pa-pipeline-dot">
                            <HcisIcon
                                :name="status === 'success' ? 'check-circle-fill' : 'clock-fill'"
                                size="2xs"
                            />
                        </div>
                        <span class="pa-pipeline-label">4. Siap Pengisian</span>
                    </div>
                </div>

                <!-- 4. REAL-TIME METRICS DUAL CARD -->
                <div class="pa-liquid-metrics-grid">
                    <div class="pa-liquid-stat-card">
                        <span class="pa-liquid-stat-label">
                            <HcisIcon name="send-fill" size="2xs" />
                            Terkirim ke HC Learn
                        </span>
                        <div class="pa-liquid-stat-val" :class="{ 'text-success': total > 0 && sisa === 0 }">
                            <strong>{{ formatAngka(terkirim) }}</strong>
                            <small>/ {{ formatAngka(total) }}</small>
                        </div>
                    </div>

                    <div class="pa-liquid-stat-card">
                        <span class="pa-liquid-stat-label">
                            <HcisIcon name="hourglass-split" size="2xs" />
                            Diproses HC Learn
                        </span>
                        <div class="pa-liquid-stat-val" :class="{ 'text-warning': antre > 0, 'text-success': antre === 0 && sisa === 0 }">
                            <strong>{{ formatAngka(antre) }}</strong>
                            <small>{{ antre > 0 ? 'menunggu jawaban' : 'tidak ada' }}</small>
                        </div>
                    </div>

                    <div class="pa-liquid-stat-card">
                        <span class="pa-liquid-stat-label">
                            <HcisIcon name="ticket-perforated-fill" size="2xs" />
                            Akses Siap
                        </span>
                        <div class="pa-liquid-stat-val" :class="{ 'text-success': total > 0 && terjadwal >= total }">
                            <strong>{{ formatAngka(terjadwal) }}</strong>
                            <small>/ {{ formatAngka(total) }}</small>
                        </div>
                    </div>
                </div>

                <!-- 5. LIVE BATCH TICKER OR ERROR ALERT -->
                <div v-if="errorMessage" class="pa-liquid-alert-wrap">
                    <HcisAlert
                        variant="danger"
                        appearance="glass"
                        size="sm"
                        title="Penjadwalan Terhenti"
                        :description="errorMessage"
                        icon="exclamation-triangle-fill"
                    />
                </div>
                <div v-else-if="status === 'menunggu'" class="pa-liquid-ticker">
                    <span class="pa-ticker-pulse"></span>
                    <span class="pa-ticker-text">
                        Menunggu jawaban HC Learn — dicek otomatis {{ latar ? 'oleh server ' : '' }}tiap {{ intervalCekDetik }} detik<template v-if="terakhirDicek"> · terakhir dicek {{ terakhirDicek }}</template>.
                        <template v-if="latar">Dialog ini boleh ditutup; pantau di panel Tugas &amp; Aktivitas.</template>
                        <template v-else>Dialog ini boleh ditutup; statusnya tetap tampil di halaman.</template>
                    </span>
                </div>
                <div v-else-if="status === 'running'" class="pa-liquid-ticker">
                    <span class="pa-ticker-pulse"></span>
                    <span class="pa-ticker-text">
                        {{ liveBatchText }}
                    </span>
                </div>
                <div v-else-if="status === 'success'" class="pa-liquid-success-box">
                    <HcisIcon name="check-circle-fill" size="xs" color="success" />
                    <span>Semua rater telah memiliki akses dan siap mengisi evaluasi.</span>
                </div>
            </div>
        </div>

        <!-- FOOTER ACTIONS -->
        <template #footer>
            <!--
             | TUGAS LATAR: pengirimannya milik server, bukan halaman ini. Menutup dialog — bahkan
             | menutup tab — tidak menghentikannya; lanjutannya terlihat di Tugas & Aktivitas.
             -->
            <HcisButton
                v-if="latar && status === 'running'"
                variant="secondary"
                label="Tutup — Tetap Berjalan"
                size="sm"
                data-uji="tutup-latar"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                v-if="status === 'error'"
                variant="secondary"
                label="Tutup Sementara"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                v-if="status === 'error'"
                variant="primary"
                label="Lanjutkan Penjadwalan"
                leading-icon="arrow-clockwise"
                size="sm"
                @click="$emit('retry')"
            />

            <!--
             | MENUNGGU PUNYA JALAN KELUARNYA SENDIRI. Tanpa tombol ini, satu-satunya cara
             | keluar dari keadaan "sudah dititipkan" adalah menutup layar dan berharap — dan
             | yang paling mungkin disimpulkan admin adalah pekerjaannya sudah selesai.
             -->
            <HcisButton
                v-if="status === 'menunggu'"
                variant="secondary"
                label="Tutup Sementara"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                v-if="status === 'menunggu'"
                variant="primary"
                label="Cek Sekarang"
                leading-icon="arrow-repeat"
                size="sm"
                :loading="menarik"
                @click="$emit('tarik-hasil')"
            />

            <HcisButton
                v-if="status === 'success'"
                variant="secondary"
                label="Tutup"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                v-if="status === 'success'"
                variant="primary"
                label="Selesai — Kembali ke Cycle"
                trailing-icon="arrow-right"
                size="sm"
                @click="$emit('finish')"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed } from 'vue';
import {
    HcisAlert,
    HcisButton,
    HcisDialog,
    HcisIcon,
} from '@/components/ui';

defineOptions({ name: 'PaLiquidProgressDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    status: {
        type: String,
        default: 'running', // 'running' | 'success' | 'error'
        validator: (v) => ['running', 'menunggu', 'success', 'error'].includes(v),
    },
    phase: {
        type: String,
        default: 'jadwal', // 'kunci' | 'jadwal' | 'selesai'
    },
    terjadwal: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    sisa: { type: Number, default: 0 },
    batchIndex: { type: Number, default: 1 },
    /* Sudah diterima HC Learn (202) tetapi tautannya belum ada. */
    antre: { type: Number, default: 0 },
    menarik: { type: Boolean, default: false },
    terakhirDicek: { type: String, default: '' },
    intervalCekDetik: { type: Number, default: 15 },
    errorMessage: { type: String, default: '' },
    /* Pekerjaannya dikerjakan SERVER sebagai tugas latar: dialog boleh ditutup kapan saja. */
    latar: { type: Boolean, default: false },
    /* Banyak rater per batch pada tugas latar — disebut di keterangan batch. */
    ukuranBatch: { type: Number, default: 200 },
});

const emit = defineEmits(['update:modelValue', 'retry', 'finish', 'tarik-hasil']);

const loading = computed(() => props.status === 'running');

/* Water Level calculation: Y goes from 200 (0% fill) to 0 (100% fill) */
/* Yang SUDAH dikirim ke HC Learn: tertaut, antre, atau ditolak — semuanya bukan "belum". */
const terkirim = computed(() => Math.max(0, props.total - props.sisa));

/*
 | Persen = KEMAJUAN KIRIM JADWAL, bukan banyaknya tautan.
 |
 | Selama dikirim, seluruh baris berstatus ANTRE dan belum bertautan — mengukur tautan membuat
 | orb diam di 8% sepanjang pengiriman (defect UAT 8 Okt). Jawaban HC Learn adalah tahap
 | TERSENDIRI (langkah 3) dengan angka "Akses Siap" sendiri, jadi 100% di sini tidak pernah
 | dibaca sebagai selesai: lencananya tetap MENUNGGU HC LEARN sampai tautannya ada.
 */
const persen = computed(() => {
    if (props.status === 'success') {
        return 100;
    }
    if (props.phase === 'kunci') {
        return 6;
    }
    if (props.total <= 0) return 10;

    return Math.max(4, Math.min(100, Math.round((terkirim.value / props.total) * 100)));
});

const keteranganPersen = computed(() => {
    if (props.status === 'success') return 'akses siap';
    if (props.phase === 'kunci') return 'mengunci';
    return 'jadwal terkirim';
});

const waterLevelY = computed(() => {
    // 200 is empty, 0 is full
    const fillPercent = persen.value / 100;
    return Math.round(195 - fillPercent * 190);
});

const orbitDashOffset = computed(() => {
    const circumference = 2 * Math.PI * 112; // ~703.7
    const offset = circumference * (1 - persen.value / 100);
    return `${offset}px`;
});

const frontGradientUrl = computed(() => {
    if (props.status === 'success') return 'url(#paLiquidGradSuccessFront)';
    if (props.status === 'error') return 'url(#paLiquidGradWarnFront)';
    return 'url(#paLiquidGradBrandFront)';
});

const backGradientUrl = computed(() => {
    if (props.status === 'success') return 'url(#paLiquidGradSuccessBack)';
    if (props.status === 'error') return 'url(#paLiquidGradWarnBack)';
    return 'url(#paLiquidGradBrandBack)';
});

const phaseIndex = computed(() => {
    if (props.status === 'success') return 3;
    if (props.phase === 'kunci') return 1;
    return 2;
});

const dialogTitle = computed(() => {
    if (props.status === 'success') return 'Penjadwalan Akses Rater Selesai';
    if (props.status === 'error') return 'Penjadwalan Akses Rater Terhenti';
    return 'Menjadwalkan Akses Rater…';
});

const dialogSentiment = computed(() => {
    if (props.status === 'success') return 'success';
    if (props.status === 'error') return 'warning';
    return 'brand';
});

const dialogIcon = computed(() => {
    if (props.status === 'success') return 'check-circle-fill';
    if (props.status === 'error') return 'exclamation-diamond-fill';
    return 'lightning-charge-fill';
});

const statusIcon = computed(() => {
    if (props.status === 'success') return 'check-circle-fill';
    if (props.status === 'error') return 'exclamation-circle-fill';
    return 'arrow-repeat';
});

const statusKicker = computed(() => {
    if (props.status === 'success') return 'SELESAI';
    if (props.status === 'menunggu') return 'MENUNGGU HC LEARN';
    if (props.phase === 'jadwal') return 'MENGIRIM';
    if (props.status === 'error') return 'TERTAHAN';
    if (props.phase === 'kunci') return 'MENGUNCI';
    return 'MEMPROSES';
});

const headlineTitle = computed(() => {
    if (props.status === 'success') {
        return 'Penjadwalan Akses Selesai';
    }
    if (props.status === 'menunggu') {
        return 'Jadwal Terkirim — Menunggu HC Learn';
    }
    if (props.status === 'error') {
        return 'Penjadwalan Terhenti Sementara';
    }
    if (props.phase === 'kunci') {
        return 'Mengunci Parameter Cycle';
    }
    return 'Memproses Akses Penilaian Rater';
});

const headlineSubtitle = computed(() => {
    if (props.status === 'success') {
        return `Seluruh ${formatAngka(props.total)} rater telah memiliki akses penilaian.`;
    }
    if (props.status === 'menunggu') {
        /* Angka yang disebut adalah yang BELUM punya tautan, bukan yang sudah diminta. Kalimat
           "sudah dijadwalkan" pada tahap ini yang membuat admin menutup layar lalu membuka
           jendela pengisian bagi orang-orang yang belum punya satu tautan pun. */
        return `Seluruh jadwal sudah diterima HC Learn. ${formatAngka(props.antre)} akses masih `
            + 'dibuatkan di sana; tautan pengisian baru ada setelah HC Learn menjawab. '
            + 'Jangan buka jendela pengisian dulu.';
    }
    if (props.status === 'error') {
        return 'Sebagian rater belum selesai diproses. Silakan coba kembali.';
    }
    if (props.phase === 'kunci') {
        return 'Memvalidasi konfigurasi rater dan paket soal.';
    }
    return 'Menyiapkan akses evaluasi rater secara bertahap.';
});

const liveBatchText = computed(() => {
    if (props.phase === 'kunci') {
        return 'Sedang memvalidasi dan mengunci status cycle…';
    }
    if (props.latar) {
        return `Server mengirim jadwal ke HC Learn di latar — batch #${props.batchIndex} `
            + `(${props.ukuranBatch} rater per batch). Dialog boleh ditutup; pantau di Tugas & Aktivitas.`;
    }
    return `Mengirim jadwal ke HC Learn — putaran #${props.batchIndex} (200 rater per putaran)…`;
});

function onDialogClose(val) {
    if (props.latar || !loading.value) {
        emit('update:modelValue', val);
    }
}

function formatAngka(v) {
    if (v === null || v === undefined) return '0';
    return Number(v).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-liquid-modal {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    padding: 0.5rem 0.25rem;
}

/* Orb Wrap */
.pa-liquid-orb-wrap {
    position: relative;
    width: 240px;
    height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Concentric Orbital Gauge Ring */
.pa-liquid-orbital-ring {
    position: absolute;
    inset: 0;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-liquid-orbit-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.pa-liquid-orbit-track {
    fill: none;
    stroke: rgba(148, 163, 184, 0.15);
    stroke-width: 4;
}

.pa-liquid-orbit-progress {
    fill: none;
    stroke: var(--hcis-brand, #0284c7);
    stroke-width: 5;
    stroke-linecap: round;
    stroke-dasharray: 703.7;
    transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1), stroke 0.4s ease;
}

.pa-liquid-orbital-ring.is-success .pa-liquid-orbit-progress {
    stroke: var(--hcis-success, #10b981);
}

.pa-liquid-orbital-ring.is-error .pa-liquid-orbit-progress {
    stroke: var(--hcis-warning, #f59e0b);
}

/* Glass Vessel Chamber */
.pa-liquid-vessel {
    position: relative;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    box-shadow:
        0 12px 35px -8px rgba(2, 132, 199, 0.35),
        inset 0 0 25px rgba(2, 132, 199, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: box-shadow 0.4s ease;
}

.pa-liquid-vessel.is-success {
    box-shadow:
        0 12px 35px -8px rgba(16, 185, 129, 0.4),
        inset 0 0 25px rgba(16, 185, 129, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.2);
}

.pa-liquid-vessel.is-error {
    box-shadow:
        0 12px 35px -8px rgba(245, 158, 11, 0.35),
        inset 0 0 25px rgba(245, 158, 11, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.15);
}

.pa-liquid-svg {
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 50%;
}

.pa-liquid-chamber-bg {
    fill: rgba(15, 23, 42, 0.7);
}

/* Wave Fluid Animations */
.pa-wave {
    transition: fill 0.4s ease;
}

.pa-wave--front {
    animation: waveFrontAnim 3.2s linear infinite;
}

.pa-wave--back {
    animation: waveBackAnim 4.8s linear infinite;
}

@keyframes waveFrontAnim {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-240px);
    }
}

@keyframes waveBackAnim {
    0% {
        transform: translateX(-240px);
    }
    100% {
        transform: translateX(0);
    }
}

/* Floating Rising Bubbles */
.pa-bubble {
    fill: rgba(255, 255, 255, 0.45);
    animation: bubbleRise 2.4s ease-in infinite;
}

.b1 { animation-delay: 0s; }
.b2 { animation-delay: 0.5s; }
.b3 { animation-delay: 1.1s; }
.b4 { animation-delay: 1.7s; }
.b5 { animation-delay: 0.8s; }

@keyframes bubbleRise {
    0% {
        transform: translateY(0) scale(0.6);
        opacity: 0;
    }
    20% {
        opacity: 0.8;
    }
    80% {
        opacity: 0.7;
    }
    100% {
        transform: translateY(-90px) scale(1.1);
        opacity: 0;
    }
}

/* Central Readout */
.pa-liquid-percent-caption {
    margin-top: -0.15rem;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.5);
}

@media (max-width: 520px) {
    .pa-liquid-metrics-grid {
        grid-template-columns: 1fr;
    }
}

.pa-liquid-center-readout {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 2;
}

.pa-liquid-percent {
    display: inline-flex;
    align-items: baseline;
    gap: 0.15rem;
    color: #ffffff;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5), 0 0 20px rgba(2, 132, 199, 0.6);
}

.pa-liquid-num {
    font-size: 2.75rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.04em;
    font-variant-numeric: tabular-nums;
}

.pa-liquid-symbol {
    font-size: 1.25rem;
    font-weight: 700;
    opacity: 0.9;
}

.pa-liquid-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    backdrop-filter: blur(8px);
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.pa-liquid-status-badge.is-success {
    background: rgba(6, 78, 59, 0.8);
    border-color: rgba(52, 211, 153, 0.4);
    color: #a7f3d0;
}

.pa-liquid-status-badge.is-error {
    background: rgba(120, 53, 15, 0.8);
    border-color: rgba(251, 191, 36, 0.4);
    color: #fde68a;
}

/* Info Section */
.pa-liquid-info-section {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    text-align: center;
}

.pa-liquid-headline h4 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--hcis-text-primary, #0f172a);
}

.pa-liquid-desc {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.4;
}

/* 4-Step Pipeline Tracker */
.pa-liquid-pipeline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    background: rgba(148, 163, 184, 0.06);
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.12);
}

.pa-pipeline-step {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-pipeline-step.is-active {
    color: var(--hcis-brand, #0284c7);
}

.pa-pipeline-step.is-done {
    color: var(--hcis-success, #10b981);
}

.pa-pipeline-step.is-error {
    color: var(--hcis-warning, #f59e0b);
}

.pa-pipeline-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(148, 163, 184, 0.15);
    font-size: 0.65rem;
}

.pa-pipeline-step.is-active .pa-pipeline-dot {
    background: var(--hcis-brand, #0284c7);
    color: #ffffff;
    box-shadow: 0 0 10px rgba(2, 132, 199, 0.5);
}

.pa-pipeline-step.is-done .pa-pipeline-dot {
    background: var(--hcis-success, #10b981);
    color: #ffffff;
}

.pa-pipeline-connector {
    width: 14px;
    height: 2px;
    background: rgba(148, 163, 184, 0.2);
    border-radius: 1px;
    transition: background 0.3s ease;
}

.pa-pipeline-connector.is-done {
    background: var(--hcis-success, #10b981);
}

/* Real-Time Metrics Dual Card */
.pa-liquid-metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.pa-liquid-stat-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.625rem 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(148, 163, 184, 0.15);
    border-radius: 0.625rem;
    gap: 0.25rem;
}

.pa-liquid-stat-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-text-secondary, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-liquid-stat-val {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--hcis-text-primary, #0f172a);
}

.pa-liquid-stat-val small {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #94a3b8);
}

/* Live Ticker Box */
.pa-liquid-ticker {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: rgba(2, 132, 199, 0.08);
    border: 1px solid rgba(2, 132, 199, 0.2);
    border-radius: 0.5rem;
    font-size: 0.75rem;
    color: var(--hcis-brand, #0284c7);
}

.pa-ticker-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--hcis-brand, #0284c7);
    box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7);
    animation: pulseTicker 1.5s infinite;
}

@keyframes pulseTicker {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 8px rgba(2, 132, 199, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(2, 132, 199, 0);
    }
}

.pa-liquid-success-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    color: var(--hcis-success, #10b981);
    text-align: left;
    line-height: 1.4;
}

.pa-liquid-alert-wrap {
    text-align: left;
}
</style>
