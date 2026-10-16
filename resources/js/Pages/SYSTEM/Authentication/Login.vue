<script>
// Login is a standalone page: opt out of the global AppShell layout.
export default { layout: null };
</script>

<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { cleanupLogoutTransition, isLogoutTransitionFresh } from '@/utils/logout-transition.js';

const props = defineProps({
    captchaEnabled: { type: Boolean, default: false },
    turnstileSiteKey: { type: String, default: '' },
    version: { type: String, default: '' },
});

const page = usePage();

const form = useForm({
    username: '',
    password: '',
    'cf-turnstile-response': '',
});

const showPassword = ref(false);

const showLogoutEntranceOverlay = ref(false);
const logoutUsername = ref('');
const startEntranceAnimations = ref(true);

try {
    // Only honour a FRESH logout flag; a stale one (self-expired past TTL) is
    // ignored & cleared by isLogoutTransitionFresh() so users never land on a
    // login page stuck behind the entrance bridge.
    if (typeof localStorage !== 'undefined' && isLogoutTransitionFresh()) {
        showLogoutEntranceOverlay.value = true;
        logoutUsername.value = localStorage.getItem('evo_logout_username') || '';
        startEntranceAnimations.value = false;
    }
} catch (e) {
    console.warn('Error reading logout state:', e);
}

/* ---------- Flash / error notice ---------- */
const notice = reactive({ visible: false, type: 'error', message: '' });
let noticeTimer = null;

function flashNotice(type, message) {
    if (!message) return;
    notice.type = type;
    notice.message = message;
    notice.visible = true;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => (notice.visible = false), 6000);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.error) flashNotice('error', flash.error);
        else if (flash?.status) flashNotice('info', flash.status);
    },
    { immediate: true, deep: true },
);

const captchaError = computed(() => form.errors['cf-turnstile-response'] || page.props.errors?.captcha || '');

/* ---------- Cloudflare Turnstile (production only) ---------- */
const turnstileEl = ref(null);
let widgetId = null;
// True bila script/render Turnstile gagal — memunculkan pesan + tombol coba lagi.
const turnstileFailed = ref(false);

function loadTurnstileScript() {
    return new Promise((resolve, reject) => {
        if (window.turnstile) return resolve();
        const existing = document.getElementById('cf-turnstile-script');
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', reject);
            return;
        }
        const s = document.createElement('script');
        s.id = 'cf-turnstile-script';
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.async = true;
        s.defer = true;
        s.onload = () => resolve();
        s.onerror = reject;
        document.head.appendChild(s);
    });
}

async function renderTurnstile() {
    // captchaEnabled yang menentukan, bukan ada-tidaknya sitekey: sitekey yang
    // kebetulan terisi di .env lokal tidak boleh menyalakan widget di sana.
    if (!props.captchaEnabled || !props.turnstileSiteKey) return;
    try {
        await loadTurnstileScript();
        await nextTick();
        if (!window.turnstile || !turnstileEl.value) return;
        widgetId = window.turnstile.render(turnstileEl.value, {
            sitekey: props.turnstileSiteKey,
            theme: 'light',
            size: 'normal',
            // Perbarui token secara otomatis saat kadaluarsa agar token yang
            // dipakai saat submit selalu masih valid (token Turnstile sekali pakai
            // dan hanya hidup ~300 detik).
            'refresh-expired': 'auto',
            callback: (token) => {
                form['cf-turnstile-response'] = token;
            },
            'expired-callback': () => {
                // Token expired — langsung minta token baru supaya user tidak
                // submit dengan token kosong (penyebab utama "sering gagal").
                resetTurnstile();
            },
            'error-callback': () => {
                form['cf-turnstile-response'] = '';
            },
        });
    } catch {
        // Script/jaringan Turnstile gagal — tampilkan pesan & sediakan retry,
        // karena tanpa widget token selalu kosong dan login mustahil.
        turnstileFailed.value = true;
    }
}

function resetTurnstile() {
    if (window.turnstile && widgetId !== null) {
        window.turnstile.reset(widgetId);
    }
    form['cf-turnstile-response'] = '';
}

// Render ulang widget setelah kegagalan load (mis. blip jaringan ke Cloudflare).
function retryTurnstile() {
    turnstileFailed.value = false;
    widgetId = null;
    renderTurnstile();
}

/* ---------- Submit ---------- */
// Turnstile hanya tampil di environment yang memang memverifikasinya. prosesLogin()
// melewati verifikasi di luar produksi, jadi widget di sana tak ada gunanya.
const showTurnstile = computed(() => props.captchaEnabled && !!props.turnstileSiteKey);

// Kotak username & password, dipegang langsung supaya isinya bisa dibaca dari
// DOM saat submit. Lihat sinkronkanDariKotak() di bawah.
const kotakUsername = ref(null);
const kotakPassword = ref(null);

/**
 * Menyalin isi KOTAK ke dalam form.
 *
 * v-model hanya mendengar event 'input'. Pengelola kata sandi (Bitwarden,
 * LastPass, 1Password, dsb.) dan sebagian jalur autofill menulis `input.value`
 * langsung tanpa membangkitkan event apa pun — jadi kotaknya terlihat terisi
 * sementara form-nya masih kosong. Tanpa penyalinan ini, kredensial hasil
 * autofill tidak pernah ikut terkirim.
 */
function sinkronkanDariKotak() {
    const dariKotak = (el, sekarang) => {
        const nilai = el?.value ?? '';

        return nilai !== sekarang ? nilai : sekarang;
    };

    form.username = dariKotak(kotakUsername.value, form.username);
    form.password = dariKotak(kotakPassword.value, form.password);
}

/**
 * Tombol hanya terkunci selama pengiriman berjalan.
 *
 * Sampai 4 Sep 2026 ia juga terkunci saat form.username/password kosong, dan
 * itu membuat orang terjebak: pengelola kata sandi mengisi kotaknya tanpa
 * event, form tetap kosong, tombol mati — dan tidak ada satu pun di layar yang
 * menjelaskan kenapa, sebab kotaknya JELAS terisi. Tidak ada jalan keluar
 * kecuali kebetulan mengetik di kotak itu.
 *
 * Yang berlaku sekarang prinsip yang sudah dipakai untuk Turnstile: jangan
 * mengunci tombol berdasarkan sesuatu yang tidak bisa kita lihat dengan andal.
 * Isian yang benar-benar kosong ditolak saat submit dengan kalimat yang bisa
 * dibaca — pintu yang bisa diketuk, bukan pintu yang dilas.
 */
const canSubmit = computed(() => !form.processing);

/* ---------- Splash Transition (entrance to HCIS) ---------- */
let splashInjected = false;

function injectSplashStyles() {
    if (document.getElementById('evo-splash-styles')) return;
    const style = document.createElement('style');
    style.id = 'evo-splash-styles';
    style.textContent = `
        .evo-premium-splash {
            position: fixed; inset: 0; z-index: 99999;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none;
            background: transparent;
        }

        /* ── Solid background covers everything behind text ── */
        .evo-premium-splash::before {
            content: '';
            position: absolute; inset: 0; z-index: 0;
            background: #f8fafc;
            opacity: 0;
            animation: bgSolidify 0.6s ease-in-out 0.8s forwards;
        }

        /* ── Cloned shell: holds the per-component exit animations ── */
        .splash-cloned-shell {
            position: absolute; inset: 0; z-index: 1;
            background: #eef0ff;
            /* Blur the clone FIRST to mask any DOM state drops (0.4s) */
            animation: 
                blurClone 0.4s ease-in-out forwards,
                fadeOutClone 0.6s ease-in-out 1.2s forwards;
        }

        /* ── Per-component EXIT animations (from last position) ── */
        /* They start AFTER the blur is mostly complete (+0.4s delay) */
        .splash-cloned-shell .topbar {
            animation: exitUp 0.7s cubic-bezier(0.55, 0, 1, 0.45) 0.45s forwards;
        }
        .splash-cloned-shell .statement {
            animation: exitLeft 0.75s cubic-bezier(0.55, 0, 1, 0.45) 0.52s forwards;
        }
        .splash-cloned-shell .stage-side {
            animation: exitRight 0.8s cubic-bezier(0.55, 0, 1, 0.45) 0.58s forwards;
        }
        .splash-cloned-shell .subs {
            animation: exitDown 0.7s cubic-bezier(0.55, 0, 1, 0.45) 0.65s forwards;
        }
        .splash-cloned-shell .stage {
            animation: backdropFade 1.0s ease-in-out 0.7s forwards;
        }

        /* ── Text sequence (starts after exit completes) ── */
        .splash-text-intro {
            position: absolute; z-index: 10;
            color: #64748b; font-size: 13px; font-weight: 500; letter-spacing: 6px;
            text-transform: uppercase;
            opacity: 0;
            will-change: transform, opacity;
            animation: seqIntro 1.4s ease-in-out 1.7s forwards;
        }
        .splash-text-auth {
            position: absolute; z-index: 10;
            color: #059669; font-size: 14px; font-weight: 600; letter-spacing: 10px;
            text-transform: uppercase;
            opacity: 0;
            will-change: transform, opacity;
            animation: seqAuth 1.4s ease-in-out 2.9s forwards;
        }
        .splash-logo {
            position: absolute; z-index: 10;
            height: 80px;
            opacity: 0;
            will-change: transform, opacity;
            animation: seqLogo 1.6s ease-in-out 4.1s forwards;
        }

        /* ── Splash fade-out to reveal new page ── */
        .evo-premium-splash.splash--ending {
            animation: splashEnd 0.8s ease-in-out 5.0s forwards;
        }

        /* ═══════ KEYFRAMES ═══════ */

        /* Components exit from their natural position */
        @keyframes exitUp {
            0%   { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-40px); }
        }
        @keyframes exitLeft {
            0%   { opacity: 1; transform: translateX(0); }
            100% { opacity: 0; transform: translateX(-60px); }
        }
        @keyframes exitRight {
            0%   { opacity: 1; transform: translateX(0) scale(1); }
            100% { opacity: 0; transform: translateX(50px) scale(0.96); }
        }
        @keyframes exitDown {
            0%   { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(30px); }
        }
        @keyframes backdropFade {
            0%   { opacity: 1; }
            100% { opacity: 0; }
        }
        
        /* Fade in the solid background */
        @keyframes bgSolidify {
            0%   { opacity: 0; }
            100% { opacity: 1; }
        }

        /* Blur the entire cloned shell first! */
        @keyframes blurClone {
            0%   { filter: blur(0px); opacity: 1; }
            100% { filter: blur(12px); opacity: 1; }
        }
        
        /* Fade out the cloned shell (and its scrollbar) */
        @keyframes fadeOutClone {
            0%   { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }

        /* Text sequence */
        @keyframes seqIntro {
            0%   { transform: translate3d(0, 12px, 0); opacity: 0; }
            20%  { transform: translate3d(0, 0, 0);    opacity: 1; }
            75%  { transform: translate3d(0, 0, 0);    opacity: 1; }
            100% { transform: translate3d(0, -12px, 0); opacity: 0; }
        }
        @keyframes seqAuth {
            0%   { transform: translate3d(0, 12px, 0) scale(0.98); opacity: 0; }
            20%  { transform: translate3d(0, 0, 0) scale(1);       opacity: 1; }
            75%  { transform: translate3d(0, 0, 0) scale(1);       opacity: 1; }
            100% { transform: translate3d(0, -12px, 0) scale(1.02); opacity: 0; }
        }
        @keyframes seqLogo {
            0%   { transform: translate3d(0, 15px, 0) scale(0.95); opacity: 0; filter: drop-shadow(0 0 0 rgba(0,0,0,0)); }
            30%  { transform: translate3d(0, 0, 0) scale(1);       opacity: 1; filter: drop-shadow(0 15px 35px rgba(0,0,0,0.08)); }
            75%  { transform: translate3d(0, 0, 0) scale(1.02);    opacity: 1; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.05)); }
            100% { transform: translate3d(0, -10px, 0) scale(1.05); opacity: 0; filter: blur(4px); }
        }
        @keyframes splashEnd {
            0%   { opacity: 1; }
            100% { opacity: 0; visibility: hidden; pointer-events: none; }
        }
    `;
    document.head.appendChild(style);
}

function preserveScopedStyles(shell) {
    // 1. Prevent Vite Dev Server from removing the <style scoped> tags of Login.vue.
    // By temporarily intercepting document.head.removeChild, we ensure the original DOM nodes
    // (along with their CSS Object Model rules that bypass CORS issues) remain intact!
    if (!window.evoSplashRemoveChild) {
        window.evoSplashRemoveChild = document.head.removeChild;
        document.head.removeChild = function (child) {
            if (child && child.tagName === 'STYLE' && child.getAttribute('data-vite-dev-id')?.includes('Login.vue')) {
                // Return immediately to prevent Vite from stripping out our beautiful styles!
                return child;
            }
            return window.evoSplashRemoveChild.call(this, child);
        };
    }

    // Also intercept style.remove() just in case Vite changes its internal DOM manipulation logic
    if (!window.evoSplashStyleRemove) {
        window.evoSplashStyleRemove = HTMLStyleElement.prototype.remove;
        HTMLStyleElement.prototype.remove = function () {
            if (this.getAttribute && this.getAttribute('data-vite-dev-id')?.includes('Login.vue')) {
                return;
            }
            if (window.evoSplashStyleRemove) {
                return window.evoSplashStyleRemove.call(this);
            }
        };
    }

    // 2. Preserve Google Fonts from Inertia's <Head> cleanup
    document.querySelectorAll('head > link[rel="stylesheet"][href*="fonts.googleapis"]').forEach((link, i) => {
        const preservedLink = link.cloneNode(true);
        preservedLink.id = `evo-splash-preserved-font-${i}`;
        preservedLink.classList.add('evo-splash-preserved');
        document.head.appendChild(preservedLink);
    });
}

function createSplashOverlay() {
    if (splashInjected) return;
    splashInjected = true;

    const shell = document.querySelector('.shell');
    if (!shell) return;

    // Capture the current scroll position before anything changes
    const currentScrollTop = shell.scrollTop;

    injectSplashStyles();
    preserveScopedStyles(shell);

    const container = document.createElement('div');
    container.className = 'evo-premium-splash';
    container.id = 'evo-splash-overlay';

    // Clone the login shell as a static snapshot while DOM is still intact
    const clone = shell.cloneNode(true);
    clone.classList.add('splash-cloned-shell');

    // Ensure the clone maintains the exact scroll position as the original
    clone.scrollTop = currentScrollTop;

    // Manually copy live DOM properties that cloneNode(true) drops (like typed input values)
    const originalInputs = shell.querySelectorAll('input');
    const clonedInputs = clone.querySelectorAll('input');
    originalInputs.forEach((input, index) => {
        if (clonedInputs[index]) {
            clonedInputs[index].value = input.value;
            if (input.type === 'checkbox' || input.type === 'radio') {
                clonedInputs[index].checked = input.checked;
            }
        }
    });

    // Strip entrance animation classes so elements start at their FINAL position
    // (the exit animations in our injected stylesheet will take over)
    const animClasses = ['animate-fade-down', 'animate-fade-up', 'animate-fade-right', 'animate-fade-left'];
    clone.querySelectorAll(animClasses.map((c) => '.' + c).join(',')).forEach((el) => {
        animClasses.forEach((c) => el.classList.remove(c));
        el.style.opacity = '1';
        el.style.transform = 'none';
    });

    // Freeze blob SVG animations at current frame
    clone.querySelectorAll('.blob-svg, .blob-svg *').forEach((el) => {
        el.style.animationPlayState = 'paused';
    });

    // Disable interactive elements
    clone.querySelectorAll('input, button, form, a').forEach((el) => {
        el.setAttribute('tabindex', '-1');
        el.setAttribute('aria-hidden', 'true');
        el.style.pointerEvents = 'none';
    });

    const textIntro = document.createElement('div');
    textIntro.className = 'splash-text-intro';
    textIntro.textContent = 'WELCOME TO HCIS';

    const textAuth = document.createElement('div');
    textAuth.className = 'splash-text-auth';
    textAuth.textContent = 'AUTHENTICATED';

    const logo = document.createElement('img');
    logo.src = '/logo/EVOGROUP.png';
    logo.className = 'splash-logo';

    container.appendChild(clone);
    container.appendChild(textIntro);
    container.appendChild(textAuth);
    container.appendChild(logo);
    document.body.appendChild(container);

    // Force synchronous paint before Vue unmount proceeds
    container.getBoundingClientRect();

    // Start splash-end fade after full animation sequence
    requestAnimationFrame(() => {
        container.classList.add('splash--ending');
    });

    // Clean up after animation completes
    setTimeout(() => {
        const overlay = document.getElementById('evo-splash-overlay');
        if (overlay?.parentNode) overlay.parentNode.removeChild(overlay);
        const styles = document.getElementById('evo-splash-styles');
        if (styles?.parentNode) styles.parentNode.removeChild(styles);
        document.querySelectorAll('.evo-splash-preserved').forEach((s) => s.remove());

        // Restore DOM manipulation functions
        if (window.evoSplashRemoveChild) {
            document.head.removeChild = window.evoSplashRemoveChild;
            window.evoSplashRemoveChild = null;
        }
        if (window.evoSplashStyleRemove) {
            HTMLStyleElement.prototype.remove = window.evoSplashStyleRemove;
            window.evoSplashStyleRemove = null;
        }

        // Finally remove the Vite dev styles that we forcefully kept alive
        document.querySelectorAll('head > style[data-vite-dev-id*="Login.vue"]').forEach((s) => s.remove());

        splashInjected = false;
    }, 6200);
}

// Flag untuk trigger splash di onBeforeUnmount.
// Digunakan sebagai pengganti form.processing karena timing form.processing
// bisa tidak akurat di production yang koneksinya sangat cepat.
let loginSubmitted = false;

function submit() {
    // Dibaca dari kotaknya lebih dulu: isian hasil autofill belum tentu pernah
    // sampai ke form (v-model hanya mendengar event 'input').
    sinkronkanDariKotak();

    if (!form.username || !form.password) {
        flashNotice('error', 'Username dan password wajib diisi.');

        return;
    }

    loginSubmitted = true;
    form.post('/prosesLogin', {
        preserveScroll: true,
        onError: () => {
            loginSubmitted = false;
            form.reset('password');
            resetTurnstile();
        },
    });
}

// Timers for the entrance-bridge fade-out chain — tracked so they can be
// cleared if the component unmounts (e.g. fast re-login) before they fire.
let entranceTimers = [];

onMounted(() => {
    // Remove the raw boot overlay instantly since Vue has now mounted its own identical overlay
    const rawBootOverlay = document.getElementById('evo-raw-boot-overlay');
    if (rawBootOverlay) {
        rawBootOverlay.remove();
    }
    document.documentElement.classList.remove('evo-boot-logout-active');

    // Clear the localStorage flag UNCONDITIONALLY here. Its only job — bridging
    // the dashboard→login page reload — is done the moment this login page has
    // mounted; setup() already captured what it needed into local refs, so the
    // entrance animation keeps running from those refs. Clearing it now (instead
    // of at the end of the fragile setTimeout chain, inside `if (overlay)`) means
    // no interruption can ever leave the flag stuck `true`.
    cleanupLogoutTransition();

    renderTurnstile();
    if (showLogoutEntranceOverlay.value) {
        // Bridge is visible (covers the login form). Fade it out gracefully,
        // then start login entrance animations.
        entranceTimers.push(setTimeout(() => {
            const overlay = document.getElementById('evo-logout-entrance-overlay');
            if (overlay) {
                // Phase A: begin fade-out (0.85s CSS transition to opacity:0)
                overlay.classList.add('fade-out');
            }

            // Phase B: once bridge is nearly invisible (~94% through fade),
            // reveal login form with entrance animations
            entranceTimers.push(setTimeout(() => {
                // Reveal the shell (anti-flash CSS sets it to opacity:0)
                const shell = document.querySelector('.shell');
                if (shell) shell.style.opacity = '';
                startEntranceAnimations.value = true;
            }, 800));

            // Phase C: bridge fully gone — hide it
            entranceTimers.push(setTimeout(() => {
                showLogoutEntranceOverlay.value = false;
            }, 1000));
        }, 100));
    } else {
        // Normal login — reveal shell immediately (anti-flash CSS hides it)
        const shell = document.querySelector('.shell');
        if (shell) shell.style.opacity = '';
    }
});
onBeforeUnmount(() => {
    clearTimeout(noticeTimer);
    entranceTimers.forEach(clearTimeout);
    entranceTimers = [];
    // Belt-and-suspenders: ensure the flag is gone even if we unmount before
    // onMounted's cleanup ran (very fast navigations).
    cleanupLogoutTransition();

    // Hanya tampilkan splash ketika benar-benar submit login (bukan navigasi lain).
    // Pakai flag loginSubmitted karena form.processing bisa tidak akurat
    // di production — timing Inertia yang sangat cepat kadang membuat
    // form.processing sudah false saat onBeforeUnmount dipanggil.
    if (loginSubmitted) {
        createSplashOverlay();
    }
});

const subsidiaries = [
    { src: '/logo/EMI.png', alt: 'PT EVO Manufacturing Indonesia' },
    { src: '/logo/ENB.png', alt: 'PT EVO Nusa Bersaudara' },
    { src: '/logo/GMN.png', alt: 'PT Graha Maju Nusantara' },
];
</script>

<template>
    <Head title="Login - HCIS EVO">
        <meta name="robots" content="noindex, nofollow" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <!-- Seamless Logout Entrance Overlay (matches dashboard exit exactly) -->
    <div v-if="showLogoutEntranceOverlay" id="evo-logout-entrance-overlay" class="logout-entrance-overlay">
        <!-- Gradient backdrop (IDENTICAL to logout overlay) -->
        <div class="logout-entrance-backdrop"></div>
        <!-- Subtle vignette -->
        <div class="logout-entrance-vignette"></div>

        <!-- Aura blobs -->
        <div class="logout-entrance-aura"></div>
        <div class="logout-entrance-aura-gold"></div>

        <!-- Glass card -->
        <div class="logout-entrance-glass-card">
            <div class="logout-entrance-shield-wrap">
                <div class="logout-entrance-orbit-ring"></div>
                <div class="logout-entrance-orbit-ring-2"></div>
                <svg
                    class="logout-entrance-shield-svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        class="logout-entrance-svg-fill"
                        d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"
                    />
                    <path
                        class="logout-entrance-svg-stroke"
                        d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"
                    />
                    <path
                        class="logout-entrance-svg-stroke-gold"
                        d="M10.5 12V10.25C10.5 9.42 11.17 8.75 12 8.75C12.83 8.75 13.5 9.42 13.5 10.25V12"
                    />
                    <rect class="logout-entrance-svg-stroke-gold" x="9.5" y="12" width="5" height="4.5" rx="0.75" />
                </svg>
            </div>

            <div class="logout-entrance-label">
                <i class="bi bi-shield-lock" style="font-size: 0.72rem; color: var(--hcis-indigo-500)"></i>
                Session Secured
            </div>
            <div class="logout-entrance-name">Goodbye, {{ logoutUsername }}</div>
            <div class="logout-entrance-subtitle">Returning to secure portal...</div>

            <div class="logout-entrance-progress-track">
                <div class="logout-entrance-progress-fill"></div>
            </div>
        </div>
    </div>

    <div class="shell">
        <!-- BACKDROP — flowing morph blobs -->
        <div class="stage" aria-hidden="true">
            <svg class="blob-svg" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <radialGradient id="v3g1" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="var(--hcis-violet-600)" stop-opacity="0.65" />
                        <stop offset="100%" stop-color="var(--hcis-violet-600)" stop-opacity="0" />
                    </radialGradient>
                    <radialGradient id="v3g2" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="var(--hcis-blue-500)" stop-opacity="0.55" />
                        <stop offset="100%" stop-color="var(--hcis-blue-500)" stop-opacity="0" />
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

        <!-- Floating flash notice -->
        <transition name="v3-toast">
            <div v-if="notice.visible" class="v3-toast" :class="`v3-toast--${notice.type}`" role="alert">
                <i :class="notice.type === 'error' ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle-fill'"></i>
                <span>{{ notice.message }}</span>
                <button class="v3-toast__close" type="button" aria-label="Tutup" @click="notice.visible = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </transition>

        <!-- TOP -->
        <nav class="topbar" :class="startEntranceAnimations ? 'animate-fade-down' : 'entrance-hidden'">
            <div class="mark-login">
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo" />
                <div class="mark-login-text">
                    <b>HCIS</b>
                    <small>Sistem Terintegrasi</small>
                </div>
            </div>
            <div class="top-right">
                <!-- <a class="top-link" href="#">Bantuan</a>
                <a class="top-link" href="#">Status sistem</a> -->
                <span class="v-pill">
                    <span class="desktop-only">
                        <i
                            class="bi bi-shield-fill-check"
                            style="color: var(--indigo); font-size: 11px; margin-right: 5px; vertical-align: middle"
                        ></i>
                        Secure Portal Connection <span style="color: rgba(11, 16, 51, 0.15)">|</span>
                        <b style="color: var(--indigo); font-weight: 700">SSL Encrypted</b>
                    </span>
                    <span class="mobile-only"><span class="status-dot"></span> All Systems Operational</span>
                </span>
            </div>
        </nav>

        <!-- MAIN -->
        <main class="stage-grid">
            <section class="statement" :class="startEntranceAnimations ? 'animate-fade-right' : 'entrance-hidden'">
                <span class="eyebrow"><span class="dot"></span>Portal Karyawan</span>
                <h1 class="display">
                    Human Capital<br />
                    <span>Information System.</span>
                    <!-- <span class="stamp">Dikenal sebagai HCIS</span> -->
                </h1>
                <p class="lede">
                    Pusat layanan terintegrasi untuk seluruh kebutuhan kepegawaian Anda — mulai dari ESS, KPI, hingga
                    LMS. Silakan masuk untuk melanjutkan.
                </p>
                <p class="sub-mobile">Satu portal terintegrasi untuk ESS, KPI, &amp; LMS</p>
            </section>

            <aside
                class="stage-side"
                :class="startEntranceAnimations ? 'animate-fade-left' : 'entrance-hidden'"
                style="animation-delay: 0.1s"
            >
                <div class="float-card">
                    <div class="card-head">
                        <h3>Selamat Datang</h3>
                        <span class="tag">Autentikasi</span>
                    </div>

                    <form @submit.prevent="submit" novalidate>
                        <div class="field">
                            <label for="u">
                                <span class="lbl-text"><i class="bi bi-person"></i> Username</span>
                            </label>
                            <div class="input-wrap" :class="{ 'is-error': form.errors.username }">
                                <input
                                    id="u"
                                    ref="kotakUsername"
                                    v-model="form.username"
                                    name="username"
                                    type="text"
                                    placeholder="Masukan Username"
                                    autocomplete="username"
                                    autofocus
                                />
                            </div>
                            <p v-if="form.errors.username" class="help">{{ form.errors.username }}</p>
                        </div>
                        <div class="field">
                            <label for="p">
                                <span class="lbl-text"><i class="bi bi-key"></i> Password</span>
                            </label>
                            <div class="input-wrap" :class="{ 'is-error': form.errors.password }">
                                <input
                                    id="p"
                                    ref="kotakPassword"
                                    v-model="form.password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Masukan Password"
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    class="toggle-eye"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="help">{{ form.errors.password }}</p>
                        </div>

                        <!-- Cloudflare Turnstile -->
                        <div v-if="showTurnstile" class="field turnstile-field">
                            <label>
                                <span class="lbl-text"><i class="bi bi-shield-check"></i> Verifikasi Keamanan</span>
                            </label>
                            <div class="turnstile-host">
                                <div ref="turnstileEl" class="cf-turnstile-wrapper"></div>
                            </div>
                            <div v-if="turnstileFailed" class="turnstile-failed">
                                <p class="help">Verifikasi keamanan gagal dimuat. Periksa koneksi Anda.</p>
                                <button type="button" class="turnstile-retry" @click="retryTurnstile">
                                    <i class="bi bi-arrow-clockwise"></i> Coba lagi
                                </button>
                            </div>
                            <p v-if="captchaError" class="help">{{ captchaError }}</p>
                        </div>

                        <button type="submit" class="btn-login" :disabled="!canSubmit">
                            <span v-if="form.processing" class="spinner" aria-hidden="true"></span>
                            {{ form.processing ? 'Memverifikasi…' : 'Login Akses' }}
                            <i v-if="!form.processing" class="bi bi-arrow-right"></i>
                        </button>

                        <div class="ver-row">
                            <span
                                >Versi <b>{{ version }}</b></span
                            >
                            <span>© {{ new Date().getFullYear() }} EVO Group</span>
                        </div>
                    </form>
                </div>

                <!-- Security Connection Notice Badge -->
                <div class="security-badge-wrap">
                    <span class="security-badge">
                        <i class="bi bi-shield-fill-check"></i>
                        <span>Secure Portal Connection | <b>SSL Encrypted</b></span>
                    </span>
                </div>
            </aside>
        </main>

        <!-- SUBS -->
        <div class="subs" :class="startEntranceAnimations ? 'animate-fade-up' : 'entrance-hidden'">
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

<style scoped>
* {
    box-sizing: border-box;
}

/* ══════════════════════════════════════════════════════════════
   Premium Logout Entrance Overlay — Glassmorphism Edition
   (This is what users see on Login.vue after /logout redirect
    to ensure no white-flash between the dashboard exit animation
    and the login page paint.)
   ══════════════════════════════════════════════════════════════ */

.logout-entrance-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    overflow: hidden;
    font-family:
        'Plus Jakarta Sans',
        'Inter',
        system-ui,
        -apple-system,
        sans-serif;
    -webkit-font-smoothing: antialiased;
    transition: opacity 0.85s cubic-bezier(0.22, 1, 0.36, 1);
}

.bi {
    display: contents;
}

.logout-entrance-overlay.fade-out {
    opacity: 0 !important;
}

/* Vignette — subtle edge darkening, matches logout overlay */
.logout-entrance-vignette {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 55%, rgba(15, 23, 42, 0.07) 100%);
    pointer-events: none;
}

.logout-entrance-backdrop {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.14) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.1) 0%, transparent 45%),
        radial-gradient(circle at 50% 70%, rgba(168, 85, 247, 0.06) 0%, transparent 50%),
        linear-gradient(
            180deg,
            rgba(248, 250, 252, 0.94) 0%,
            rgba(238, 242, 255, 0.96) 50%,
            rgba(255, 255, 255, 0.97) 100%
        );
    backdrop-filter: blur(40px) saturate(1.4);
    -webkit-backdrop-filter: blur(40px) saturate(1.4);
}

.logout-entrance-aura {
    position: absolute;
    width: 520px;
    height: 520px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.16) 0%, rgba(168, 85, 247, 0.07) 40%, transparent 70%);
    filter: blur(60px);
    animation: entranceAuraBreathe 5s ease-in-out infinite alternate;
}

.logout-entrance-aura-gold {
    position: absolute;
    width: 360px;
    height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.14) 0%, rgba(99, 102, 241, 0.04) 50%, transparent 70%);
    filter: blur(50px);
    animation: entranceAuraGold 6s ease-in-out infinite alternate;
}

@keyframes entranceAuraBreathe {
    0% {
        transform: scale(1) rotate(0deg);
    }
    100% {
        transform: scale(1.1) rotate(10deg);
    }
}

@keyframes entranceAuraGold {
    0% {
        transform: translate(0, 0) scale(1);
    }
    100% {
        transform: translate(30px, -24px) scale(1.07);
    }
}

.logout-entrance-glass-card {
    box-sizing: border-box;
    width: 480px;
    max-width: calc(100% - 40px);
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 52px 60px 48px;
    border-radius: 1.5rem;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.52)), rgba(255, 255, 255, 0.64);
    border: 1px solid rgba(199, 210, 254, 0.72);
    box-shadow:
        0 24px 60px rgba(15, 23, 42, 0.1),
        0 4px 16px rgba(15, 23, 42, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.88),
        0 0 0 1px rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(24px) saturate(1.16);
    -webkit-backdrop-filter: blur(24px) saturate(1.16);
}

.logout-entrance-glass-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.6) 0%, transparent 35%, transparent 100%);
    pointer-events: none;
}

.logout-entrance-shield-wrap {
    position: relative;
    width: 108px;
    height: 108px;
    margin-bottom: 28px;
}

.logout-entrance-orbit-ring {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    border: 2px solid transparent;
    border-top-color: rgba(99, 102, 241, 0.5);
    border-right-color: rgba(6, 182, 212, 0.28);
    animation: entranceRingOrbit 3.5s linear infinite;
}

.logout-entrance-orbit-ring-2 {
    position: absolute;
    inset: -20px;
    border-radius: 50%;
    border: 1.5px solid transparent;
    border-bottom-color: rgba(168, 85, 247, 0.3);
    border-left-color: rgba(99, 102, 241, 0.14);
    animation: entranceRingOrbit 5.5s linear infinite reverse;
}

@keyframes entranceRingOrbit {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.logout-entrance-shield-svg {
    width: 108px;
    height: 108px;
    filter: drop-shadow(0 8px 28px rgba(99, 102, 241, 0.22));
    animation: entranceShieldGlow 3s ease-in-out infinite alternate;
}

@keyframes entranceShieldGlow {
    0% {
        filter: drop-shadow(0 8px 24px rgba(99, 102, 241, 0.18));
    }
    100% {
        filter: drop-shadow(0 8px 36px rgba(99, 102, 241, 0.36)) drop-shadow(0 0 70px rgba(168, 85, 247, 0.1));
    }
}

.logout-entrance-svg-fill {
    fill: rgba(99, 102, 241, 0.07);
    stroke: none;
}

.logout-entrance-svg-stroke {
    fill: none;
    stroke: rgba(99, 102, 241, 0.55);
    stroke-width: 1;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.logout-entrance-svg-stroke-gold {
    fill: none;
    stroke: var(--hcis-indigo-600);
    stroke-width: 1.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.logout-entrance-label {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.78rem;
    border-radius: 999px;
    border: 1px solid rgba(199, 210, 254, 0.65);
    background: rgba(238, 242, 255, 0.55);
    color: var(--hcis-indigo-900);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    margin-bottom: 18px;
}

.logout-entrance-name {
    position: relative;
    z-index: 1;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--hcis-slate-900);
    margin-bottom: 6px;
    text-align: center;
    line-height: 1.15;
    background: linear-gradient(135deg, var(--hcis-slate-900) 40%, var(--hcis-indigo-600));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logout-entrance-subtitle {
    position: relative;
    z-index: 1;
    font-size: 13px;
    font-weight: 600;
    color: var(--hcis-slate-500);
    letter-spacing: 0.02em;
    margin-bottom: 28px;
}

.logout-entrance-progress-track {
    position: relative;
    z-index: 1;
    width: 220px;
    height: 3px;
    border-radius: 4px;
    background: rgba(99, 102, 241, 0.08);
    overflow: hidden;
}

.logout-entrance-progress-fill {
    height: 100%;
    width: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-cyan-500), var(--hcis-purple-500), var(--hcis-indigo-500));
    background-size: 300% 100%;
    animation: entranceProgressShimmer 1.8s ease-in-out infinite;
}

@keyframes entranceProgressShimmer {
    0% {
        background-position: 300% 0;
    }
    100% {
        background-position: -300% 0;
    }
}

.sub-mobile {
    display: none;
}

.security-badge-wrap {
    display: none;
}

.desktop-only {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.mobile-only {
    display: none;
}

.entrance-hidden {
    opacity: 0 !important;
}

@media (max-width: 640px) {
    .logout-entrance-glass-card {
        padding: 40px 28px 36px;
        margin: 0 20px;
        border-radius: 1.2rem;
    }
    .logout-entrance-shield-wrap {
        width: 84px;
        height: 84px;
        margin-bottom: 22px;
    }
    .logout-entrance-shield-svg {
        width: 84px;
        height: 84px;
    }
    .logout-entrance-orbit-ring {
        inset: -8px;
    }
    .logout-entrance-orbit-ring-2 {
        inset: -16px;
    }
    .logout-entrance-name {
        font-size: 22px;
    }
    .logout-entrance-progress-track {
        width: 170px;
    }
    .logout-entrance-aura {
        width: 320px;
        height: 320px;
    }
    .logout-entrance-aura-gold {
        width: 240px;
        height: 240px;
    }
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
    mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse at 70% 50%, #000 30%, transparent 75%);
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
.top-link {
    font-size: 13px;
    color: var(--text-soft);
    text-decoration: none;
    font-weight: 500;
    transition:
        color 0.3s ease,
        transform 0.3s ease;
}
.top-link:hover {
    color: var(--ink);
    transform: translateY(-1px);
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

/* MAIN STAGE */
.stage-grid {
    position: relative;
    z-index: 2;
    flex: 1 0 auto; /* Prevent shrinking below content height */
    padding: 0 56px 24px;
    display: grid;
    grid-template-columns: 1fr 460px;
    align-items: center;
    gap: 40px;
    max-width: 1360px;
    margin: 0 auto;
    width: 100%;
}

/* HUGE typographic statement (left) */
.statement {
    position: relative;
}
.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--indigo);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    margin-bottom: 24px;
}
.eyebrow .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--indigo);
    box-shadow: 0 0 12px var(--indigo);
}

.display {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(48px, 5.6vw, 84px);
    line-height: 1.02;
    font-weight: 700;
    letter-spacing: -0.035em;
    color: var(--ink);
}
.display span {
    display: inline-block;
    font-family: 'Fraunces', serif;
    font-style: italic;
    font-weight: 400;
    background: linear-gradient(120deg, var(--violet) 0%, var(--indigo) 45%, #2a6fdb 75%, var(--gold) 110%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    padding-right: 0.04em;
}
.display .stamp {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font: 600 11px 'Plus Jakarta Sans';
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: var(--indigo);
    margin-top: 18px;
}
.display .stamp::before {
    content: '';
    width: 36px;
    height: 1.5px;
    background: linear-gradient(90deg, var(--gold), var(--indigo));
    border-radius: 2px;
}

.lede {
    margin-top: 24px;
    max-width: 460px;
    color: var(--text-soft);
    font-size: 15px;
    line-height: 1.55;
}

/* FLOATING GLASS CARD (right, offset) */
.float-card {
    position: relative;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.45) 100%);
    backdrop-filter: blur(24px) saturate(200%);
    -webkit-backdrop-filter: blur(24px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 36px;
    padding: 38px 44px 32px;
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 40px 80px -20px rgba(11, 16, 51, 0.25),
        0 16px 40px -10px rgba(79, 70, 229, 0.15);
    transform: translateY(-12px);
    will-change: transform;
    transition:
        transform 0.4s ease,
        box-shadow 0.4s ease;
}
.float-card:hover {
    transform: translateY(-18px);
    box-shadow:
        inset 0 2px 4px rgba(255, 255, 255, 0.8),
        inset 0 -2px 10px rgba(255, 255, 255, 0.3),
        0 50px 100px -20px rgba(11, 16, 51, 0.3),
        0 20px 50px -10px rgba(79, 70, 229, 0.2);
}
.float-card::before {
    content: '';
    position: absolute;
    top: -1px;
    left: -1px;
    right: -1px;
    bottom: -1px;
    border-radius: 37px;
    pointer-events: none;
}

.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
}
.card-head h3 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, var(--ink) 20%, var(--indigo) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}
.card-head .tag {
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--indigo);
    background: rgba(79, 70, 229, 0.1);
    padding: 6px 12px;
    border-radius: 8px;
}

.field {
    margin-bottom: 14px;
}
.field label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}
.field label .lbl-text {
    display: flex;
    align-items: center;
    gap: 6px;
}
.field label .lbl-text i {
    font-size: 13px;
    color: var(--indigo);
}
.field label .hint {
    color: var(--text-soft);
    font-weight: 400;
    font-size: 11px;
}
.field label .forgot {
    color: var(--indigo);
    text-decoration: none;
    transition:
        color 0.3s ease,
        text-shadow 0.3s ease;
}
.field label .forgot:hover {
    color: var(--violet);
    text-shadow: 0 0 8px rgba(124, 58, 237, 0.3);
}
.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    background: var(--hcis-white);
    border: 1.5px solid rgba(11, 16, 51, 0.15);
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(11, 16, 51, 0.03);
    transition:
        border-color 0.3s ease,
        background-color 0.3s ease,
        box-shadow 0.3s ease;
    transform: translateZ(0); /* Hardware isolation to stop typing lag */
    overflow: hidden; /* Clip browser autofill sharp corners */
}
.input-wrap:focus-within {
    border-color: var(--indigo);
    box-shadow:
        0 0 0 4px rgba(79, 70, 229, 0.12),
        0 4px 12px rgba(11, 16, 51, 0.05);
}
.input-wrap.is-error {
    border-color: var(--hcis-red-500);
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
}
.input-wrap input {
    flex: 1;
    border: 0;
    background: transparent;
    padding: 16px 20px;
    font: 500 15px 'Plus Jakarta Sans';
    color: var(--ink);
    outline: none;
    transition: background 0.2s;
    border-radius: inherit; /* Align autofill background shape */
}
.input-wrap input::placeholder {
    color: #9ca0c6;
    font-weight: 400;
}

/* ── Browser autofill override — prevent Chrome/Edge from
       injecting its own background when autofilling saved passwords ── */
.input-wrap input:-webkit-autofill,
.input-wrap input:-webkit-autofill:hover,
.input-wrap input:-webkit-autofill:focus,
.input-wrap input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 1000px var(--hcis-white) inset !important;
    -webkit-text-fill-color: var(--ink) !important;
    caret-color: var(--ink);
    /* The 50000s transition tricks Chrome into never reapplying
       the yellow/blue autofill background after our override */
    transition: background-color 50000s ease-in-out 0s;
}

.toggle-eye {
    background: none;
    border: 0;
    padding: 0 14px;
    color: #8a8fb8;
    cursor: pointer;
    font-size: 15px;
    transition:
        color 0.3s ease,
        transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toggle-eye:hover {
    color: var(--indigo);
    transform: scale(1.15);
}
.help {
    margin: 6px 2px 0;
    font-size: 12px;
    color: var(--hcis-red-600);
}

.turnstile-field {
    margin-top: 14px;
    margin-bottom: 20px;
}
.turnstile-host {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center;
    margin-top: 8px;
    min-height: 65px;
}
.cf-turnstile-wrapper {
    display: block;
    position: relative;
    z-index: 1;
    /* Drop shadow directly on the widget to make it float elegantly */
    filter: drop-shadow(0 12px 24px rgba(11, 16, 51, 0.12));
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    border-radius: 8px; /* Turnstile's native radius */
}
.cf-turnstile-wrapper:hover {
    transform: translateY(-2px);
}
.cf-turnstile-wrapper :deep(iframe) {
    /* Ensure the widget stays intact */
    border-radius: 8px;
}
.turnstile-host::after {
    /* Beautiful radial glow behind the centered widget */
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 280px;
    height: 70px;
    transform: translate(-50%, -50%);
    background: radial-gradient(ellipse, rgba(79, 70, 229, 0.15) 0%, transparent 70%);
    z-index: 0;
    filter: blur(12px);
    pointer-events: none;
}
.turnstile-host .help {
    margin-top: 12px;
    position: relative;
    z-index: 2;
    text-align: center;
    width: 100%;
}

.turnstile-failed {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}
.turnstile-failed .help {
    margin: 0;
    text-align: center;
}
.turnstile-retry {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1.5px solid rgba(79, 70, 229, 0.3);
    border-radius: 12px;
    background: rgba(79, 70, 229, 0.06);
    color: var(--indigo);
    font: 600 13px 'Plus Jakarta Sans';
    cursor: pointer;
    transition:
        background 0.3s ease,
        border-color 0.3s ease,
        transform 0.2s ease;
}
.turnstile-retry:hover {
    background: rgba(79, 70, 229, 0.12);
    border-color: var(--indigo);
    transform: translateY(-1px);
}

.btn-login {
    width: 100%;
    padding: 16px 20px;
    border: 0;
    cursor: pointer;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--ink) 0%, var(--indigo) 100%);
    color: #fff;
    font: 700 15px 'Plus Jakarta Sans';
    letter-spacing: 0.03em;
    box-shadow:
        0 16px 36px -10px rgba(79, 70, 229, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition:
        transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 0.3s ease,
        opacity 0.6s ease;
    position: relative;
    overflow: hidden;
}
.btn-login::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        105deg,
        transparent 20%,
        rgba(255, 255, 255, 0.05) 30%,
        rgba(255, 255, 255, 0.4) 50%,
        rgba(255, 255, 255, 0.05) 70%,
        transparent 80%
    );
    transform: translateX(-100%);
    transition: none; /* Mencegah slide mundur saat hover dilepas */
}
.btn-login:not(:disabled) {
    animation: premium-glow 2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-login:not(:disabled)::after {
    animation: btn-shine 1.5s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes premium-glow {
    0% {
        filter: brightness(1);
        box-shadow:
            0 16px 36px -10px rgba(79, 70, 229, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.25);
    }
    50% {
        filter: brightness(1.1);
        box-shadow:
            0 20px 48px -5px rgba(79, 70, 229, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.35);
    }
    100% {
        filter: brightness(1);
        box-shadow:
            0 16px 36px -10px rgba(79, 70, 229, 0.5),
            inset 0 1px 0 rgba(255, 255, 255, 0.25);
    }
}
@keyframes btn-shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
.btn-login:hover:not(:disabled) {
    transform: translateY(-3px) scale(1.015);
    box-shadow: 0 24px 48px -10px rgba(79, 70, 229, 0.6);
}
.btn-login:hover:not(:disabled)::after {
    transform: translateX(100%);
    transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-login:active:not(:disabled) {
    transform: translateY(1px) scale(0.98);
}
.btn-login:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
    /* Hapus warna abu-abu flat, biarkan warna asli memudar */
}
.spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.ver-row {
    margin-top: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11.5px;
    color: var(--text-soft);
}
.ver-row b {
    color: var(--ink);
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* BOTTOM SUBSIDIARIES */
.subs {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 28px;
    margin: 0 56px 24px;
    background: rgba(255, 255, 255, 0.65);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    box-shadow: 0 12px 36px -12px rgba(11, 16, 51, 0.1);
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}
.subs:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 42px -12px rgba(11, 16, 51, 0.15);
}
.subs .lbl {
    font-size: 10.5px;
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
    height: 30px;
    opacity: 0.88;
    transition:
        opacity 0.3s ease,
        transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.subs .logos img:hover {
    opacity: 1;
    transform: scale(1.1) translateY(-2px);
}
.subs .logos .dv {
    width: 1px;
    height: 22px;
    background: var(--line);
}

/* Toast */
.v3-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: min(92vw, 380px);
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #fff;
    box-shadow: 0 18px 40px -12px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(10px);
}
.v3-toast--error {
    background: linear-gradient(120deg, var(--hcis-red-500), var(--hcis-red-600));
}
.v3-toast--info {
    background: linear-gradient(120deg, var(--indigo), var(--violet));
}
.v3-toast span {
    flex: 1;
}
.v3-toast__close {
    border: none;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    cursor: pointer;
    display: grid;
    place-items: center;
}
.v3-toast-enter-active,
.v3-toast-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

/* PREMIUM ANIMATION CLASSES (Hardware Accelerated) */
@keyframes ambientFade {
    0% {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
@keyframes revealDown {
    0% {
        opacity: 0;
        transform: translateY(-20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes revealUp {
    0% {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@keyframes revealRight {
    0% {
        opacity: 0;
        transform: translateX(-50px);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes revealPop {
    0% {
        opacity: 0;
        transform: translateX(60px) scale(0.95);
    }
    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

.animate-fade-down {
    animation: revealDown 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-up {
    animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.5s;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-right {
    animation: revealRight 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.15s;
    opacity: 0;
    will-change: transform, opacity;
}
.animate-fade-left {
    animation: revealPop 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: 0.25s;
    opacity: 0;
    will-change: transform, opacity;
}

/* ---------- Cinematic Entrance (Authenticating State) ---------- */
.is-authenticating .topbar {
    animation: exitUp 0.6s cubic-bezier(0.55, 0, 1, 0.45) forwards;
}
.is-authenticating .statement {
    animation: exitLeft 0.7s cubic-bezier(0.55, 0, 1, 0.45) 0.05s forwards;
}
.is-authenticating .stage-side {
    animation: exitRight 0.75s cubic-bezier(0.55, 0, 1, 0.45) 0.1s forwards;
}
.is-authenticating .subs {
    animation: exitDown 0.6s cubic-bezier(0.55, 0, 1, 0.45) 0.15s forwards;
}
.is-authenticating .stage {
    animation: backdropFadeIn 0.8s ease-in-out forwards;
}

@keyframes exitUp {
    100% {
        opacity: 0;
        transform: translateY(-40px);
        pointer-events: none;
    }
}
@keyframes exitLeft {
    100% {
        opacity: 0;
        transform: translateX(-60px);
        pointer-events: none;
    }
}
@keyframes exitRight {
    100% {
        opacity: 0;
        transform: translateX(50px) scale(0.96);
        pointer-events: none;
    }
}
@keyframes exitDown {
    100% {
        opacity: 0;
        transform: translateY(30px);
        pointer-events: none;
    }
}
@keyframes backdropFadeIn {
    100% {
        opacity: 0.8;
        filter: blur(4px);
    }
}

.v3-toast-enter-from,
.v3-toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}

/* TABLET (≤1099) */
@media (max-width: 1099px) {
    .shell {
        overflow-y: auto;
    }
    .topbar {
        padding: 22px 36px;
    }
    .top-link {
        font-size: 12px;
    }
    .stage-grid {
        grid-template-columns: 1fr;
        gap: 32px;
        padding: 20px 36px 24px;
        height: auto;
        align-items: center;
    }
    .display {
        font-size: clamp(48px, 6.5vw, 68px);
        line-height: 1.05;
    }
    .display .stamp {
        margin-top: 14px;
        font-size: 10px;
    }
    .lede {
        margin-top: 18px;
        font-size: 14.5px;
        max-width: 100%;
    }
    .float-card {
        transform: none;
        max-width: 440px;
        margin: 0 auto;
        padding: 32px 30px 24px;
    }
    .subs {
        margin: 0 36px 24px;
        padding: 14px 22px;
    }
    .subs .lbl {
        font-size: 9.5px;
        letter-spacing: 0.18em;
    }
    .subs .logos {
        gap: 22px;
    }
    .subs .logos img {
        height: 24px;
    }
}

/* MOBILE (<768) — compact single-screen layout, no scroll needed */
@media (max-width: 767px) {
    .shell {
        position: absolute;
        inset: 0;
        height: 100vh;
        height: 100dvh;
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }
    .stage {
        position: fixed;
    }
    .topbar {
        padding: 12px 18px;
        flex-wrap: wrap;
        gap: 8px;
        flex-shrink: 0;
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
    .top-right {
        gap: 8px;
    }
    .top-link {
        display: none;
    }
    .v-pill {
        padding: 3px 8px;
        font-size: 8px;
        gap: 4px;
    }

    /* ── Only keep the "Human Capital Information System" heading ── */
    .statement {
        margin-bottom: 0;
        text-align: center;
    }
    .eyebrow {
        display: none !important;
    }
    .display {
        font-size: clamp(26px, 7.5vw, 32px);
        line-height: 1.1;
    }
    .display br {
        display: inline;
    }
    .lede {
        display: none !important;
    }

    /* ── Statement on top, login card follows inside centered flex container ── */
    .stage-grid {
        display: flex;
        flex-direction: column;
        justify-content: center;
        flex: 1 1 auto;
        padding: 8px 18px 12px;
        gap: 16px;
    }
    .stage-side {
        width: 100%;
    }
    .float-card {
        transform: none;
        max-width: 100%;
        padding: 20px 18px 16px;
        border-radius: 20px;
    }
    .card-head {
        margin-bottom: 12px;
    }
    .card-head h3 {
        font-size: 16px;
    }
    .card-head .tag {
        font-size: 8.5px;
        padding: 3px 7px;
        letter-spacing: 0.1em;
    }
    .field {
        margin-bottom: 8px;
    }
    .field label {
        font-size: 10.5px;
        margin-bottom: 6px;
    }
    .input-wrap input {
        font-size: 13.5px;
        padding: 11px 14px;
    }
    .captcha {
        padding: 8px 10px;
    }
    .captcha .label {
        font-size: 11px;
    }
    .btn-login {
        padding: 11px 16px;
        font-size: 13px;
        margin-top: 4px;
    }

    /* ── Subsidiaries compact at bottom ── */
    .subs {
        position: static;
        margin: 0 18px 12px;
        padding: 8px 14px;
        flex-direction: column;
        gap: 4px;
        text-align: center;
        border-radius: 14px;
        flex-shrink: 0;
    }
    .subs .lbl {
        font-size: 8px;
        letter-spacing: 0.18em;
    }
    .subs .logos {
        gap: 12px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .subs .logos img {
        height: 18px;
    }
    .subs .logos .dv {
        display: none;
    }

    .sub-mobile {
        display: block;
        font-size: 13px;
        color: var(--text-soft);
        font-weight: 500;
        margin-top: 8px;
        letter-spacing: 0.01em;
    }
    .desktop-only {
        display: none !important;
    }
    .mobile-only {
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        0%,
        100% {
            opacity: 0.6;
            transform: scale(0.9);
        }
        50% {
            opacity: 1;
            transform: scale(1.1);
        }
    }
    .security-badge-wrap {
        display: flex;
        justify-content: center;
        margin-top: 14px;
        opacity: 0.95;
    }
    .security-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.65);
        color: var(--hcis-slate-600);
        font-size: 11px;
        font-weight: 500;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 4px 12px rgba(11, 16, 51, 0.03);
    }
    .security-badge i {
        color: var(--indigo);
        font-size: 13px;
    }
    .security-badge b {
        color: var(--indigo);
        font-weight: 700;
    }
}
</style>

<!-- Non-scoped anti-flash styles: prevents white flash during login page load.
     Target is intentionally global so it applies before Vue/Inertia mounts.
     The #app is the Inertia mount point which is empty until Vue renders. -->
<style scoped>
html,
body,
#app {
    background: var(--hcis-slate-50) !important;
}
</style>
