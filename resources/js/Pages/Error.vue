<script>
// Opt-out dari AppShell: halaman error tampil fullscreen tanpa sidebar/topbar.
// app.js hanya menimpa layout bila `undefined`, jadi `null` eksplisit dihormati.
export default { layout: null };
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    status: { type: Number, default: 500 },
    message: { type: String, default: '' },
});

// Konfigurasi tampilan per status (judul, deskripsi, ikon, warna aksen).
const presets = {
    403: {
        title: 'Akses Ditolak',
        accent: ['#ef4444', '#f43f5e'], // Red to Rose
        icon: 'lock',
    },
    404: {
        title: 'Halaman Tidak Ditemukan',
        accent: ['#4f46e5', '#7c3aed'], // Indigo to Violet
        icon: 'frown',
    },
    419: {
        title: 'Sesi Telah Berakhir',
        accent: ['#d97706', '#f59e0b'], // Amber to Gold
        icon: 'clock',
    },
    429: {
        title: 'Terlalu Banyak Permintaan',
        accent: ['#f97316', '#ef4444'], // Orange to Crimson
        icon: 'clock',
    },
    500: {
        title: 'Terjadi Kesalahan Server',
        accent: ['#f43f5e', '#e11d48'], // Rose to Crimson-Pink
        icon: 'alert',
    },
    503: {
        title: 'Sistem Dalam Perbaikan',
        accent: ['#06b6d4', '#3b82f6'], // Cyan to Blue
        icon: 'gear',
    },
};

const preset = computed(() => presets[props.status] ?? {
    title: 'Terjadi Kesalahan',
    accent: ['#4f46e5', '#7c3aed'],
    icon: 'alert',
});

const description = computed(() => props.message || 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.');

const accentGradient = computed(
    () => `linear-gradient(135deg, ${preset.value.accent[0]}, ${preset.value.accent[1]})`,
);

// --- Auto-redirect / auto-refresh per status ---------------------------------
// 419 -> ke /login (countdown). 403 -> ke beranda (countdown). 503 -> refresh.
const redirectConfig = computed(() => {
    if (props.status === 419) return { seconds: 5, action: () => (window.location.href = '/login'), label: 'login' };
    if (props.status === 403) return { seconds: 5, action: () => (window.location.href = '/'), label: 'beranda' };
    if (props.status === 503) return { seconds: 60, action: () => window.location.reload(), label: 'refresh' };
    return null;
});

const countdown = ref(0);
const totalSeconds = ref(0);
let timer = null;

const progressPercent = computed(() => {
    if (totalSeconds.value === 0) return 0;
    return (countdown.value / totalSeconds.value) * 100;
});

onMounted(() => {
    const cfg = redirectConfig.value;
    if (!cfg) return;

    countdown.value = cfg.seconds;
    totalSeconds.value = cfg.seconds;
    timer = setInterval(() => {
        countdown.value -= 1;
        if (countdown.value <= 0) {
            clearInterval(timer);
            timer = null;
            cfg.action();
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
});

const goHome = () => (window.location.href = '/');
const goBack = () => window.history.back();
const goLogin = () => (window.location.href = '/login');
const reloadPage = () => window.location.reload();

const countdownText = computed(() => {
    const cfg = redirectConfig.value;
    if (!cfg) return '';
    if (cfg.label === 'login') return `Diarahkan ke halaman login dalam ${countdown.value} detik`;
    if (cfg.label === 'beranda') return `Diarahkan ke beranda dalam ${countdown.value} detik`;
    if (cfg.label === 'refresh') return `Memuat ulang otomatis dalam ${countdown.value} detik`;
    return '';
});

const subsidiaries = [
    { src: '/logo/EMI.png', alt: 'PT EVO Manufacturing Indonesia' },
    { src: '/logo/ENB.png', alt: 'PT EVO Nusa Bersaudara' },
    { src: '/logo/GMN.png', alt: 'PT Graha Maju Nusantara' },
];
</script>

<template>
    <Head :title="preset.title + ' - HCIS EVO'">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="shell">
        <!-- BACKDROP — flowing morph blobs -->
        <div class="stage" aria-hidden="true">
            <svg class="blob-svg" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <radialGradient id="v3g1" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#7C3AED" stop-opacity="0.65" />
                        <stop offset="100%" stop-color="#7C3AED" stop-opacity="0" />
                    </radialGradient>
                    <radialGradient id="v3g2" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.55" />
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0" />
                    </radialGradient>
                    <radialGradient id="v3g3" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#C9A24A" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="#C9A24A" stop-opacity="0" />
                    </radialGradient>
                    <filter id="v3goo"><feGaussianBlur stdDeviation="60" /></filter>
                </defs>
                <g filter="url(#v3goo)">
                    <circle cx="200" cy="200" r="280" fill="url(#v3g1)">
                        <animate attributeName="cx" values="200;350;200" dur="22s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="200;320;200" dur="18s" repeatCount="indefinite" />
                    </circle>
                    <circle cx="1300" cy="700" r="360" fill="url(#v3g2)">
                        <animate attributeName="cx" values="1300;1150;1300" dur="24s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="700;580;700" dur="20s" repeatCount="indefinite" />
                    </circle>
                    <circle cx="900" cy="200" r="240" fill="url(#v3g3)">
                        <animate attributeName="cx" values="900;1050;900" dur="26s" repeatCount="indefinite" />
                        <animate attributeName="cy" values="200;350;200" dur="22s" repeatCount="indefinite" />
                    </circle>
                </g>
            </svg>
            <div class="grid"></div>
        </div>

        <!-- TOP BAR -->
        <nav class="topbar">
            <div class="mark-login">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" />
                <div class="mark-login-text">
                    <b>HCIS</b>
                    <small>Sistem Terintegrasi</small>
                </div>
            </div>
            <div class="top-right">
                <span class="v-pill">
                    <span class="desktop-only">
                        <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor" style="color: var(--indigo); display: inline-block; margin-right: 5px; vertical-align: middle;">
                            <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 2.97-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 63 63 0 0 0-2.887-.87C9.843.266 8.69 0 8 0m2.146 5.146a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793z"/>
                        </svg>
                        Secure Portal Connection <span style="color: rgba(11, 16, 51, 0.15); margin: 0 4px;">|</span> <b style="color: var(--indigo); font-weight: 700;">SSL Encrypted</b>
                    </span>
                    <span class="mobile-only"><span class="status-dot"></span> System Check</span>
                </span>
            </div>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="stage-content">
            <div class="err-card">
                <!-- Glowing Back Aura -->
                <div class="card-glow" :style="{ background: `radial-gradient(circle, ${preset.accent[0]}25 0%, transparent 70%)` }"></div>

                <!-- Futuristic Status Chip -->
                <div class="error-chip" :style="{ color: preset.accent[0], borderColor: `${preset.accent[0]}40`, background: `${preset.accent[0]}08` }">
                    <span class="status-chip-dot" :style="{ background: preset.accent[0], boxShadow: `0 0 10px ${preset.accent[0]}` }"></span>
                    SYSTEM STATUS: {{ preset.title }}
                </div>

                <div class="error-code-wrapper">
                    <!-- Error Code with Neon Shadow -->
                    <div class="error-code" :style="{ backgroundImage: accentGradient, textShadow: `0 0 45px ${preset.accent[0]}25` }">{{ status }}</div>
                    
                    <!-- Secured Glass Orbit Ring for Icon -->
                    <div class="icon-orbit-wrap">
                        <div class="orbit-ring" :style="{ borderTopColor: preset.accent[0], borderRightColor: preset.accent[1] }"></div>
                        <div class="orbit-ring-2" :style="{ borderBottomColor: preset.accent[1], borderLeftColor: preset.accent[0] }"></div>
                        <div class="icon-inner-glass" :style="{ background: `${preset.accent[0]}08`, borderColor: `${preset.accent[0]}30`, color: preset.accent[0] }">
                            <svg v-if="preset.icon === 'lock'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                            <svg v-else-if="preset.icon === 'frown'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9" /><path d="M8 15c1-1.5 2.5-2 4-2s3 .5 4 2" /><circle cx="9" cy="10" r="1" fill="currentColor" /><circle cx="15" cy="10" r="1" fill="currentColor" /></svg>
                            <svg v-else-if="preset.icon === 'clock'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                            <svg v-else-if="preset.icon === 'gear'" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7.7 1.6 1.6 0 0 0-1 1.5V22a2 2 0 0 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H2a2 2 0 0 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H8a1.6 1.6 0 0 0 1-1.5V2a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V8a1.6 1.6 0 0 0 1.5 1H22a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z" /></svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /></svg>
                        </div>
                    </div>
                </div>

                <p class="description">{{ description }}</p>

                <!-- AUTO REDIRECT / COUNTDOWN -->
                <div v-if="redirectConfig" class="auto-redirect-container">
                    <p class="countdown-text">{{ countdownText }}</p>
                    <div class="progress-track">
                        <div class="progress-fill" :style="{ width: progressPercent + '%', backgroundImage: accentGradient }"></div>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="action-buttons">
                    <template v-if="status === 419">
                        <button class="btn btn-primary" :style="{ backgroundImage: accentGradient, boxShadow: `0 12px 30px -8px ${preset.accent[0]}60` }" @click="goLogin">
                            Login Sekarang
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </template>
                    <template v-else-if="status === 503">
                        <button class="btn btn-primary" :style="{ backgroundImage: accentGradient, boxShadow: `0 12px 30px -8px ${preset.accent[0]}60` }" @click="reloadPage">
                            Coba Lagi Sekarang
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px; animation: spin 4s linear infinite;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                        </button>
                    </template>
                    <template v-else>
                        <button class="btn btn-primary" :style="{ backgroundImage: accentGradient, boxShadow: `0 12px 30px -8px ${preset.accent[0]}60` }" @click="goHome">
                            Kembali ke Beranda
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        </button>
                        <button class="btn btn-secondary" @click="goBack">
                            Halaman Sebelumnya
                        </button>
                    </template>
                </div>
            </div>
        </main>

        <!-- SUBSIDIARIES -->
        <div class="subs">
            <span class="lbl">Supported By</span>
            <div class="logos">
                <template v-for="(co, i) in subsidiaries" :key="co.alt">
                    <img :src="co.src" :alt="co.alt" loading="lazy" decoding="async" />
                    <span v-if="i < subsidiaries.length - 1" class="dv"></span>
                </template>
            </div>
            <span class="lbl">Group of Companies</span>
        </div>
    </div>
</template>

<!-- GLOBAL BODY STYLE (Normalizing margins and heights to absolute fullscreen) -->
<style scoped>
html,
body,
#app {
    background: #eef0ff !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    height: 100dvh !important;
    overflow: hidden !important;
}
</style>

<style scoped>
* {
    box-sizing: border-box;
}

.shell {
    --bg-0: #eef0ff;
    --bg-1: #dde2ff;
    --ink: #0b1033;
    --ink-2: #1a1f4d;
    --indigo: var(--hcis-indigo-600);
    --indigo-2: var(--hcis-indigo-500);
    --violet: var(--hcis-violet-600);
    --gold: #c9a24a;
    --text: #0f1235;
    --text-soft: #5b5f86;
    --line: rgba(11, 16, 51, 0.1);

    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    color: var(--text);
    background: var(--bg-0);
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}

/* BACKDROP — flowing morph blobs */
.stage {
    position: fixed;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}
.blob-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    animation: ambientFade 2s ease-out forwards;
    opacity: 0;
    will-change: transform, opacity;
}
.grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse at 50% 50%, #000 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse at 50% 50%, #000 30%, transparent 75%);
}

/* TOP NAV */
.topbar {
    position: relative;
    z-index: 3;
    padding: 24px 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.mark-login {
    display: flex;
    align-items: center;
    gap: 14px;
}
.brand-logo {
    height: 48px;
    width: auto;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15));
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.brand-logo:hover {
    transform: scale(1.08) rotate(-2deg);
}
.mark-login-text {
    display: flex;
    flex-direction: column;
}
.mark-login b {
    font-weight: 700;
    font-size: 16px;
    letter-spacing: -0.01em;
}
.mark-login small {
    display: block;
    font-size: 11px;
    color: var(--text-soft);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    margin-top: 2px;
}
.top-right {
    display: flex;
    align-items: center;
    gap: 24px;
}
.v-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.65);
    font-size: 10px;
    color: var(--hcis-slate-600);
    font-weight: 500;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(11, 16, 51, 0.03);
    letter-spacing: 0.02em;
}
.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-emerald-500);
    box-shadow: 0 0 8px var(--hcis-emerald-500);
    animation: statusPulse 2s infinite ease-in-out;
}
@keyframes statusPulse {
    0%, 100% { opacity: 0.6; transform: scale(0.9); }
    50% { opacity: 1; transform: scale(1.1); }
}

/* MAIN STAGE CONTENT */
.stage-content {
    position: relative;
    z-index: 2;
    flex: 1 0 auto;
    padding: 30px 56px;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}

/* GLASS CARD */
.err-card {
    position: relative;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.88) 0%, rgba(255, 255, 255, 0.55) 100%);
    backdrop-filter: blur(24px) saturate(200%);
    -webkit-backdrop-filter: blur(24px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.95);
    border-radius: 40px;
    padding: 40px 50px 36px;
    max-width: 580px;
    width: 100%;
    text-align: center;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 40px 80px -20px rgba(11, 16, 51, 0.18),
        0 16px 40px -10px rgba(79, 70, 229, 0.1);
    will-change: transform;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    z-index: 2;
}
.err-card:hover {
    transform: translateY(-5px);
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 50px 100px -20px rgba(11, 16, 51, 0.22),
        0 20px 50px -10px rgba(79, 70, 229, 0.15);
}

/* Glowing Back Aura */
.card-glow {
    position: absolute;
    inset: -60px;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
    opacity: 0.85;
}

/* Futuristic Status Chip */
.error-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 16px;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1;
    position: relative;
}
.status-chip-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    animation: statusPulse 1.5s infinite ease-in-out;
}

.error-code-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 18px;
    position: relative;
    z-index: 1;
}

.error-code {
    font-size: 8rem;
    font-weight: 800;
    line-height: 0.95;
    margin-bottom: 2px;
    letter-spacing: -0.05em;
    background-size: 100%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    filter: drop-shadow(0 4px 10px rgba(11, 16, 51, 0.05));
    position: relative;
    z-index: 2;
}

/* Secured Glass Orbit Ring for Icon */
.icon-orbit-wrap {
    position: relative;
    width: 90px;
    height: 90px;
    margin: 10px auto 14px;
    z-index: 2;
}
.orbit-ring {
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    border: 2px solid transparent;
    animation: entranceRingOrbit 4.5s linear infinite;
    opacity: 0.75;
}
.orbit-ring-2 {
    position: absolute;
    inset: -16px;
    border-radius: 50%;
    border: 1.5px solid transparent;
    animation: entranceRingOrbit 6.5s linear infinite reverse;
    opacity: 0.55;
}
.icon-inner-glass {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(12px) saturate(1.1);
    -webkit-backdrop-filter: blur(12px) saturate(1.1);
    box-shadow: 
        inset 0 2px 6px rgba(255, 255, 255, 0.4),
        0 6px 20px rgba(11, 16, 51, 0.08);
    animation: floatIcon 3.5s ease-in-out infinite alternate;
}

@keyframes floatIcon {
    0% { transform: translateY(0); }
    100% { transform: translateY(-4px); }
}
@keyframes entranceRingOrbit {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.icon-inner-glass svg {
    width: 100%;
    height: 100%;
}
.icon-inner-glass .spin {
    animation: spin 5s linear infinite;
}

.description {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-soft);
    line-height: 1.55;
    margin: 0 auto 20px;
    max-width: 460px;
    position: relative;
    z-index: 1;
}

/* Auto Redirect Progress Bar */
.auto-redirect-container {
    background: rgba(11, 16, 51, 0.02);
    border: 1px solid rgba(11, 16, 51, 0.04);
    border-radius: 16px;
    padding: 12px 18px;
    margin-bottom: 22px;
    text-align: left;
    position: relative;
    z-index: 1;
}
.countdown-text {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 8px 0;
}
.progress-track {
    width: 100%;
    height: 4px;
    border-radius: 4px;
    background: rgba(11, 16, 51, 0.05);
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s linear;
    background-size: 200% 100%;
    animation: shimmer 1.8s ease-in-out infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 400px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.btn {
    width: 100%;
    padding: 14px 20px;
    border: 0;
    cursor: pointer;
    border-radius: 16px;
    font: 700 14px 'Plus Jakarta Sans';
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
}

.btn-primary {
    color: #fff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
}
.btn-primary:hover {
    transform: translateY(-3px) scale(1.01);
}
.btn-primary:active {
    transform: translateY(1px) scale(0.99);
}

.btn-secondary {
    background: rgba(11, 16, 51, 0.04);
    color: var(--ink);
    border: 1px solid rgba(11, 16, 51, 0.07);
}
.btn-secondary:hover {
    background: rgba(11, 16, 51, 0.08);
    transform: translateY(-2px);
}
.btn-secondary:active {
    transform: translateY(0);
}

/* SUBSIDIARIES */
.subs {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 28px;
    margin: 0 56px 20px;
    background: rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    box-shadow: 0 12px 36px -12px rgba(11, 16, 51, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.2s;
    opacity: 0;
    flex-shrink: 0;
}
.subs:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 42px -12px rgba(11, 16, 51, 0.12);
}
.subs .lbl {
    font-size: 10px;
    color: var(--text-soft);
    letter-spacing: 0.22em;
    text-transform: uppercase;
    font-weight: 600;
}
.subs .logos {
    display: flex;
    align-items: center;
    gap: 32px;
}
.subs .logos img {
    height: 28px;
    opacity: 0.88;
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.subs .logos img:hover {
    opacity: 1;
    transform: scale(1.1) translateY(-2px);
}
.subs .logos .dv {
    width: 1px;
    height: 20px;
    background: var(--line);
}

/* ANIMATIONS */
@keyframes ambientFade {
    0% { opacity: 0; transform: scale(0.95) translateY(10px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes revealUp {
    0% { opacity: 0; transform: translateY(30px) scale(0.98); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.06); opacity: 0.85; }
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* MEDIA QUERIES */

/* TABLET (≤1099px) */
@media (max-width: 1099px) {
    .topbar {
        padding: 22px 36px;
    }
    .stage-content {
        padding: 10px 36px;
    }
    .err-card {
        padding: 34px 40px 30px;
        max-width: 500px;
    }
    .subs {
        margin: 0 36px 20px;
        padding: 12px 22px;
    }
    .subs .lbl {
        font-size: 9px;
        letter-spacing: 0.18em;
    }
    .subs .logos {
        gap: 22px;
    }
    .subs .logos img {
        height: 22px;
    }
}

/* MOBILE (<768px) */
@media (max-width: 767px) {
    .shell {
        position: absolute;
        inset: 0;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .topbar {
        padding: 12px 18px;
        gap: 8px;
    }
    .mark-login .brand-logo {
        height: 28px;
    }
    .mark-login b {
        font-size: 13px;
    }
    .mark-login small {
        font-size: 8.5px;
        letter-spacing: 0.1em;
    }
    .v-pill {
        padding: 3px 8px;
        font-size: 8px;
        gap: 4px;
    }
    .desktop-only {
        display: none !important;
    }
    .mobile-only {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .stage-content {
        padding: 10px 16px;
    }
    .err-card {
        padding: 24px 20px 20px;
        border-radius: 28px;
    }
    .error-chip {
        font-size: 9.5px;
        padding: 4px 10px;
        margin-bottom: 12px;
    }
    .error-code {
        font-size: 5.5rem;
    }
    .icon-orbit-wrap {
        width: 76px;
        height: 76px;
        margin: 6px auto 10px;
    }
    .orbit-ring {
        inset: -6px;
    }
    .orbit-ring-2 {
        inset: -12px;
    }
    .icon-inner-glass {
        padding: 16px;
    }
    .description {
        font-size: 13.5px;
        margin-bottom: 16px;
    }
    .auto-redirect-container {
        padding: 10px 14px;
        border-radius: 14px;
        margin-bottom: 16px;
    }
    .countdown-text {
        font-size: 11.5px;
    }
    .btn {
        padding: 12px 16px;
        font-size: 13px;
    }

    .subs {
        margin: 0 16px 12px;
        padding: 10px 14px;
        flex-direction: column;
        gap: 6px;
        text-align: center;
        border-radius: 16px;
    }
    .subs .lbl {
        font-size: 8px;
        letter-spacing: 0.14em;
    }
    .subs .logos {
        gap: 16px;
        justify-content: center;
    }
    .subs .logos img {
        height: 18px;
    }
    .subs .logos .dv {
        display: none;
    }
}
</style>
