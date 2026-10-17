/**
 * Shared Logout Transition Utility for HCIS EVO
 * Concept: "Cinematic Blur-First" — Premium Glassmorphism Edition
 *
 * Design philosophy:
 *   - Backdrop blur is the HERO — it intensifies FIRST, establishing the frosted glass canvas
 *   - Every subsequent element enters in a carefully choreographed sequence
 *   - Nothing appears abruptly; everything fades, scales, or draws into view
 *   - Matches Login.vue light frosted-glass design language:
 *     Indigo (#6366f1) + Cyan (#06b6d4) + Violet (#a855f7) accents
 *
 * Animation Timeline (total ~3.2s):
 *   Phase 0 (0.0–0.45s): Overlay veil fades in (opacity 0→1) — soft white curtain descends
 *   Phase 1 (0.12–0.7s): Dashboard deconstruction — sidebar/topbar/content slide out with blur
 *   Phase 2 (0.2–1.2s): Backdrop blur intensifies (HERO transition, 1.6s ease-out)
 *   Phase 3 (0.6–1.4s): Aura blobs & particles emerge
 *   Phase 4 (1.0–1.6s): Glass card spring entrance (scale 0.88→1.03→0.99→1.0)
 *   Phase 5 (1.2–2.2s): Shield SVG sequential draw (outline→lock→checkmark)
 *   Phase 6 (1.6–2.4s): Text sequence — label → name → subtitle (staggered 0.2–0.25s)
 *   Phase 7 (2.2–2.8s): Progress bar fill with shimmer
 *   Phase 8 (3.1s):     Callback fires (Inertia navigates to /logout behind overlay)
 */

let logoutInjected = false;

/* ═══════════════════════════════════════════════════════════════
   FAILSAFE STATE

   The overlay is a full-screen, click-blocking layer. Everything below exists
   so that it can NEVER become a permanent trap: the handoff to /logout may
   simply never complete (expired session redirect chain, 419, 5xx, offline,
   a wedged SPA), and without a way out the user has to kill the tab.

   Rules:
   - every setTimeout is tracked, so cleanup can cancel a pending handoff
     (an untracked handoff fired AFTER cleanup and navigated out of nowhere);
   - if the handoff has not taken the page away, force a plain browser
     navigation — that path does not depend on Inertia being healthy;
   - if even that does nothing, tear the overlay down and give the page back.
   ═══════════════════════════════════════════════════════════════ */
let logoutTimers = [];
let escapeKeyHandler = null;

/** Handoff to /logout (snappy & seamless transition). */
export const LOGOUT_HANDOFF_MS = 250;
/** Show the manual escape link inside the card. */
export const LOGOUT_ESCAPE_HINT_MS = 5000;
/** Handoff appears stuck -> force a hard browser navigation. */
export const LOGOUT_NAV_FAILSAFE_MS = 8000;
/** Nothing worked -> abort, restore the page, let the user act. */
export const LOGOUT_ABORT_MS = 15000;
/** Public page that clears client storage when all else fails. */
export const LOGOUT_RECOVERY_URL = '/pulihkan';

function trackTimeout(fn, delay) {
    const id = setTimeout(fn, delay);
    logoutTimers.push(id);
    return id;
}

function clearLogoutTimers() {
    logoutTimers.forEach(clearTimeout);
    logoutTimers = [];
}

/** Undo deconstructDashboard() so an aborted logout leaves a usable page. */
function restoreDashboard() {
    try {
        document
            .querySelectorAll('.logout-exit-sidebar, .logout-exit-topbar, .logout-exit-content')
            .forEach((el) => {
                el.classList.remove('logout-exit-sidebar', 'logout-exit-topbar', 'logout-exit-content');
            });
    } catch (e) {
        // ignore
    }
}

function detachEscapeKey() {
    if (escapeKeyHandler) {
        window.removeEventListener('keydown', escapeKeyHandler);
        escapeKeyHandler = null;
    }
}

/* ═══════════════════════════════════════════════════════════════
   PERSISTENCE KEYS + TTL

   The logout transition persists a flag in localStorage so the anti-flash
   bridge (app.blade.php boot overlay + Login.vue entrance overlay) can pick
   up where the dashboard left off across the /logout page reload.

   CRITICAL: this flag must be SELF-EXPIRING. It used to be a permanent latch
   that was only ever cleared on the Login page happy-path — so any interruption
   (landing on a non-login page, a fast re-login, a closed tab, a JS error, the
   entrance overlay element not being found) left it stuck `true` forever, and
   app.blade.php then re-showed the frosted boot overlay on EVERY page load,
   trapping the user ("nyangkut"). A logout animation is never relevant more
   than a few seconds after it started, so we stamp it with a timestamp and
   treat anything older than LOGOUT_TRANSITION_TTL_MS as stale.
   ═══════════════════════════════════════════════════════════════ */
export const LOGOUT_TRANSITION_FLAG_KEY = 'evo_logout_transition_active';
export const LOGOUT_TRANSITION_USER_KEY = 'evo_logout_username';
export const LOGOUT_TRANSITION_TS_KEY = 'evo_logout_transition_ts';
export const LOGOUT_TRANSITION_TTL_MS = 15000;

/**
 * Whether a logout transition flag is present AND still fresh (within TTL).
 * If the flag exists but is stale, it is proactively cleared so subsequent
 * reads (and other tabs) don't get trapped by it.
 *
 * @returns {boolean}
 */
export function isLogoutTransitionFresh() {
    try {
        if (localStorage.getItem(LOGOUT_TRANSITION_FLAG_KEY) !== 'true') return false;
        const ts = parseInt(localStorage.getItem(LOGOUT_TRANSITION_TS_KEY) || '0', 10);
        const fresh = Number.isFinite(ts) && ts > 0 && Date.now() - ts < LOGOUT_TRANSITION_TTL_MS;
        if (!fresh) {
            cleanupLogoutTransition();
        }
        return fresh;
    } catch (e) {
        return false;
    }
}

/* ═══════════════════════════════════════════════════════════════
   STYLES — injected once, removed on cleanup
   ═══════════════════════════════════════════════════════════════ */
function injectLogoutStyles() {
    if (document.getElementById('evo-logout-styles')) return;
    const style = document.createElement('style');
    style.id = 'evo-logout-styles';
    style.textContent = `
        /* ── Base overlay ── */
        .evo-logout-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: all;
            overflow: hidden;
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            transform: translateZ(0);
            will-change: opacity;
            /* ── CRITICAL: overlay itself fades in first, masking the deconstruction ── */
            opacity: 0;
            transition: opacity 0.45s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .evo-logout-overlay.is-active {
            opacity: 1;
        }

        /* ═══════════════════════════════════════════════════════
           BACKDROP — The HERO element
           Starts clear, intensifies to frosted glass over 1.6s
           ═══════════════════════════════════════════════════════ */
        .evo-logout-backdrop {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.0) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.0) 0%, transparent 45%),
                linear-gradient(180deg,
                    rgba(248, 250, 252, 0.0) 0%,
                    rgba(238, 242, 255, 0.0) 50%,
                    rgba(255, 255, 255, 0.0) 100%
                );
            backdrop-filter: blur(0px) saturate(1);
            -webkit-backdrop-filter: blur(0px) saturate(1);
            transition:
                background 1.6s cubic-bezier(0.22, 1, 0.36, 1),
                backdrop-filter 1.6s cubic-bezier(0.22, 1, 0.36, 1),
                -webkit-backdrop-filter 1.6s cubic-bezier(0.22, 1, 0.36, 1);
            will-change: backdrop-filter, -webkit-backdrop-filter;
        }

        /* Backdrop ACTIVE state — fully frosted */
        .evo-logout-overlay.is-active .evo-logout-backdrop {
            background:
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.14) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.10) 0%, transparent 45%),
                radial-gradient(circle at 50% 70%, rgba(168, 85, 247, 0.06) 0%, transparent 50%),
                linear-gradient(180deg,
                    rgba(248, 250, 252, 0.94) 0%,
                    rgba(238, 242, 255, 0.96) 50%,
                    rgba(255, 255, 255, 0.97) 100%
                );
            backdrop-filter: blur(40px) saturate(1.4);
            -webkit-backdrop-filter: blur(40px) saturate(1.4);
        }

        /* ── Subtle vignette overlay (appears with backdrop) ── */
        .evo-logout-vignette {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 55%, rgba(15, 23, 42, 0.07) 100%);
            opacity: 0;
            transition: opacity 1.8s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: none;
        }
        .evo-logout-overlay.is-active .evo-logout-vignette {
            opacity: 1;
        }

        /* ═══════════════════════════════════════════════════════
           AURA BLOBS — breathing ambient glows
           ═══════════════════════════════════════════════════════ */

        /* Primary indigo aura */
        .evo-logout-aura {
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            background: radial-gradient(circle,
                rgba(99, 102, 241, 0.16) 0%,
                rgba(168, 85, 247, 0.07) 40%,
                transparent 70%
            );
            filter: blur(60px);
            opacity: 0;
            transform: scale(0.5);
            animation:
                auraAppear 1.4s cubic-bezier(0.16, 1, 0.3, 1) 0.8s forwards,
                auraBreathe 5s ease-in-out 2.2s infinite alternate;
            pointer-events: none;
        }

        /* Secondary cyan accent aura */
        .evo-logout-aura-cyan {
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle,
                rgba(6, 182, 212, 0.14) 0%,
                rgba(99, 102, 241, 0.04) 50%,
                transparent 70%
            );
            filter: blur(50px);
            opacity: 0;
            transform: scale(0.5);
            animation:
                auraCyanAppear 1.4s cubic-bezier(0.16, 1, 0.3, 1) 1.0s forwards,
                auraCyanDrift 6s ease-in-out 2.4s infinite alternate;
            pointer-events: none;
        }

        @keyframes auraAppear {
            0%   { opacity: 0; transform: scale(0.5); }
            60%  { opacity: 0.9; transform: scale(1.05); }
            100% { opacity: 1;   transform: scale(1); }
        }
        @keyframes auraCyanAppear {
            0%   { opacity: 0; transform: scale(0.5); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes auraBreathe {
            0%   { transform: scale(1) rotate(0deg); }
            100% { transform: scale(1.10) rotate(10deg); }
        }
        @keyframes auraCyanDrift {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -24px) scale(1.07); }
        }

        /* ═══════════════════════════════════════════════════════
           GLASSMORPHISM CARD — spring entrance
           ═══════════════════════════════════════════════════════ */
        .evo-logout-glass-card {
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
                linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.52)),
                rgba(255, 255, 255, 0.64);
            border: 1px solid rgba(199, 210, 254, 0.72);
            box-shadow:
                0 24px 60px rgba(15, 23, 42, 0.10),
                0 4px 16px rgba(15, 23, 42, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.88),
                0 0 0 1px rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(24px) saturate(1.16);
            -webkit-backdrop-filter: blur(24px) saturate(1.16);
            opacity: 0;
            transform: scale(0.88) translateY(24px);
            animation: cardSpringIn 0.85s cubic-bezier(0.16, 1, 0.3, 1) 1.0s forwards;
            will-change: transform, opacity;
            backface-visibility: hidden;
        }

        /* Diagonal shine accent */
        .evo-logout-glass-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.60) 0%,
                transparent 35%,
                transparent 100%
            );
            pointer-events: none;
        }

        /* Spring-like entrance: overshoots slightly then settles */
        @keyframes cardSpringIn {
            0%   { opacity: 0; transform: scale(0.88) translateY(24px); }
            55%  { opacity: 1; transform: scale(1.025) translateY(-2px); }
            80%  { opacity: 1; transform: scale(0.992) translateY(1px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* ═══════════════════════════════════════════════════════
           SHIELD ICON — SVG sequential draw
           ═══════════════════════════════════════════════════════ */
        .evo-logout-shield-wrap {
            position: relative;
            width: 108px;
            height: 108px;
            margin-bottom: 28px;
        }

        /* Outer orbit ring — appears first */
        .evo-logout-orbit-ring {
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px solid transparent;
            border-top-color: rgba(99, 102, 241, 0.50);
            border-right-color: rgba(6, 182, 212, 0.28);
            opacity: 0;
            animation:
                ringAppear 0.5s ease 1.2s forwards,
                ringOrbit 3.5s linear 1.2s infinite;
        }

        /* Inner orbit ring — appears slightly after, spins opposite */
        .evo-logout-orbit-ring-2 {
            position: absolute;
            inset: -20px;
            border-radius: 50%;
            border: 1.5px solid transparent;
            border-bottom-color: rgba(168, 85, 247, 0.30);
            border-left-color: rgba(99, 102, 241, 0.14);
            opacity: 0;
            animation:
                ringAppear 0.5s ease 1.35s forwards,
                ringOrbit 5.5s linear 1.35s infinite reverse;
        }

        @keyframes ringAppear {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes ringOrbit {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* Shield SVG container */
        .evo-logout-shield-svg {
            width: 108px;
            height: 108px;
            filter: drop-shadow(0 8px 28px rgba(99, 102, 241, 0.22));
            opacity: 0;
            animation: shieldReveal 0.6s cubic-bezier(0.16, 1, 0.3, 1) 1.3s forwards,
                       shieldGlow 3s ease-in-out 2.5s infinite alternate;
        }

        /* ── SVG path elements ── */
        .evo-logout-svg-shield {
            fill: none;
            stroke: rgba(99, 102, 241, 0.55);
            stroke-width: 1;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 200;
            stroke-dashoffset: 200;
            animation: drawPath 1.6s cubic-bezier(0.22, 1, 0.36, 1) 1.3s forwards;
        }

        .evo-logout-svg-shield-fill {
            fill: rgba(99, 102, 241, 0.07);
            stroke: none;
            opacity: 0;
            animation: fadeInSoft 0.8s ease 2.3s forwards;
        }

        .evo-logout-svg-lock-shackle {
            fill: none;
            stroke: #4f46e5;
            stroke-width: 1.2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: drawPath 1.0s cubic-bezier(0.22, 1, 0.36, 1) 1.55s forwards;
        }

        .evo-logout-svg-lock-body {
            fill: none;
            stroke: #4f46e5;
            stroke-width: 1.2;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: drawPath 1.2s cubic-bezier(0.22, 1, 0.36, 1) 1.75s forwards;
        }


        /* SVG animation keyframes */
        @keyframes drawPath {
            to { stroke-dashoffset: 0; }
        }
        @keyframes shieldReveal {
            to { opacity: 1; }
        }
        @keyframes fadeInSoft {
            to { opacity: 1; }
        }
        @keyframes shieldGlow {
            0%   { filter: drop-shadow(0 8px 24px rgba(99, 102, 241, 0.18)); }
            100% { filter: drop-shadow(0 8px 36px rgba(99, 102, 241, 0.36))
                          drop-shadow(0 0 70px rgba(168, 85, 247, 0.10)); }
        }

        /* ═══════════════════════════════════════════════════════
           FLOATING PARTICLES
           ═══════════════════════════════════════════════════════ */
        .evo-logout-particles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .evo-logout-particle {
            position: absolute;
            border-radius: 50%;
            opacity: 0;
            will-change: transform, opacity;
        }

        /* ═══════════════════════════════════════════════════════
           TEXT ELEMENTS — staggered slide-up entrance
           ═══════════════════════════════════════════════════════ */
        .evo-logout-label {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.38rem 0.78rem;
            border-radius: 999px;
            border: 1px solid rgba(199, 210, 254, 0.65);
            background: rgba(238, 242, 255, 0.55);
            color: #312e81;
            font-size: 0.62rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            margin-bottom: 18px;
            opacity: 0;
            transform: translateY(12px);
            animation: textSlideUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) 1.6s forwards;
            will-change: transform, opacity;
        }

        .evo-logout-name {
            position: relative;
            z-index: 1;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin-bottom: 6px;
            text-align: center;
            line-height: 1.15;
            background: linear-gradient(135deg, #0f172a 40%, #4f46e5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0;
            transform: translateY(12px);
            animation: textSlideUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) 1.85s forwards;
            will-change: transform, opacity;
        }

        .evo-logout-subtitle {
            position: relative;
            z-index: 1;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.02em;
            margin-bottom: 28px;
            opacity: 0;
            transform: translateY(12px);
            animation: textSlideUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) 2.05s forwards;
            will-change: transform, opacity;
        }

        @keyframes textSlideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .evo-logout-label-text,
        .evo-logout-subtitle {
            transition: opacity 0.25s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .evo-logout-overlay.text-transitioning .evo-logout-label-text,
        .evo-logout-overlay.text-transitioning .evo-logout-subtitle {
            opacity: 0 !important;
        }

        /* ═══════════════════════════════════════════════════════
           PROGRESS BAR — appears last, fills smoothly
           ═══════════════════════════════════════════════════════ */
        .evo-logout-progress-track {
            position: relative;
            z-index: 1;
            width: 220px;
            height: 3px;
            border-radius: 4px;
            background: rgba(99, 102, 241, 0.08);
            overflow: hidden;
            opacity: 0;
            animation: textSlideUp 0.5s ease 2.25s forwards;
        }

        .evo-logout-progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            background: linear-gradient(90deg,
                #6366f1,
                #06b6d4,
                #a855f7,
                #6366f1
            );
            background-size: 300% 100%;
            animation:
                progressFill 0.7s cubic-bezier(0.22, 1, 0.36, 1) 2.35s forwards,
                progressShimmer 1.8s ease-in-out 2.35s infinite;
        }

        @keyframes progressFill {
            to { width: 100%; }
        }
        @keyframes progressShimmer {
            0%   { background-position: 300% 0; }
            100% { background-position: -300% 0; }
        }

        /* ═══════════════════════════════════════════════════════
           DASHBOARD DECONSTRUCTION — exit animations
           ═══════════════════════════════════════════════════════ */
        .logout-exit-sidebar {
            transition: transform 1.1s cubic-bezier(0.22, 1, 0.36, 1),
                        opacity 0.9s ease,
                        filter 0.9s ease !important;
            transform: translateX(-80px) !important;
            opacity: 0 !important;
            filter: blur(12px) !important;
        }

        .logout-exit-topbar {
            transition: transform 1.0s cubic-bezier(0.22, 1, 0.36, 1),
                        opacity 0.85s ease,
                        filter 0.85s ease !important;
            transform: translateY(-55px) !important;
            opacity: 0 !important;
            filter: blur(10px) !important;
        }

        .logout-exit-content {
            transition: transform 1.2s cubic-bezier(0.22, 1, 0.36, 1),
                        opacity 1.0s ease,
                        filter 1.0s ease !important;
            transform: translateY(48px) scale(0.96) !important;
            opacity: 0 !important;
            filter: blur(14px) !important;
        }

        /* ═══════════════════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════════════════ */
        @media (max-width: 640px) {
            .evo-logout-glass-card {
                padding: 40px 28px 36px;
                margin: 0 20px;
                border-radius: 1.2rem;
            }
            .evo-logout-shield-wrap {
                width: 84px;
                height: 84px;
                margin-bottom: 22px;
            }
            .evo-logout-shield-svg {
                width: 84px;
                height: 84px;
            }
            .evo-logout-orbit-ring {
                inset: -8px;
            }
            .evo-logout-orbit-ring-2 {
                inset: -16px;
            }
            .evo-logout-name {
                font-size: 22px;
            }
            .evo-logout-progress-track {
                width: 170px;
            }
            .evo-logout-aura {
                width: 320px;
                height: 320px;
            }
            .evo-logout-aura-cyan {
                width: 240px;
                height: 240px;
            }
        }

        /* ── Escape hatch — hidden until the handoff looks stuck ── */
        .evo-logout-escape {
            margin-top: 18px;
            opacity: 0;
            transform: translateY(6px);
            pointer-events: none;
            transition: opacity 0.4s ease, transform 0.4s ease;
            font-size: 12px;
            line-height: 1.5;
            color: #64748b;
            text-align: center;
        }

        .evo-logout-overlay.show-escape .evo-logout-escape {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .evo-logout-escape button {
            margin-top: 6px;
            display: inline-block;
            border: 1px solid rgba(99, 102, 241, 0.35);
            background: rgba(99, 102, 241, 0.08);
            color: #4f46e5;
            font: inherit;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 999px;
            cursor: pointer;
        }

        .evo-logout-escape button:hover {
            background: rgba(99, 102, 241, 0.16);
        }

        .evo-logout-escape-alt {
            margin-top: 8px;
            font-size: 11px;
            color: #94a3b8;
        }

        .evo-logout-escape-alt a {
            color: #6366f1;
            text-decoration: underline;
        }
    `;
    document.head.appendChild(style);
}

/* ═══════════════════════════════════════════════════════════════
   PARTICLES — floating light dots with staggered spawn
   ═══════════════════════════════════════════════════════════════ */
function createParticles(container) {
    const particlesEl = document.createElement('div');
    particlesEl.className = 'evo-logout-particles';

    const colors = [
        'rgba(99, 102, 241, 0.40)',
        'rgba(6, 182, 212, 0.35)',
        'rgba(168, 85, 247, 0.30)',
        'rgba(79, 70, 229, 0.25)',
        'rgba(16, 185, 129, 0.28)',
    ];

    // 20 particles with staggered spawn times (0.6s → 1.4s)
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'evo-logout-particle';
        const size = 2 + Math.random() * 5;
        const x = 5 + Math.random() * 90;
        const y = 5 + Math.random() * 90;
        const delay = 0.6 + Math.random() * 0.8; // staggered spawn
        const dur = 3.5 + Math.random() * 5;
        const color = colors[Math.floor(Math.random() * colors.length)];

        p.style.cssText = `
            width: ${size}px; height: ${size}px;
            left: ${x}%; top: ${y}%;
            background: ${color};
            box-shadow: 0 0 ${size * 3}px ${color};
            animation: logoutParticle${i % 5} ${dur}s ease-in-out ${delay}s infinite alternate;
        `;
        particlesEl.appendChild(p);
    }

    // Inject particle keyframes (5 unique float paths)
    if (!document.getElementById('evo-logout-particles-kf')) {
        const particleStyle = document.createElement('style');
        particleStyle.id = 'evo-logout-particles-kf';
        particleStyle.textContent = `
            @keyframes logoutParticle0 {
                0%   { opacity: 0; transform: translate(0, 0) scale(0.3); }
                12%  { opacity: 0.8; }
                88%  { opacity: 0.4; }
                100% { opacity: 0; transform: translate(18px, -40px) scale(1.2); }
            }
            @keyframes logoutParticle1 {
                0%   { opacity: 0; transform: translate(0, 0) scale(0.4); }
                15%  { opacity: 0.7; }
                85%  { opacity: 0.3; }
                100% { opacity: 0; transform: translate(-22px, -35px) scale(1.0); }
            }
            @keyframes logoutParticle2 {
                0%   { opacity: 0; transform: translate(0, 0) scale(0.35); }
                18%  { opacity: 0.75; }
                82%  { opacity: 0.35; }
                100% { opacity: 0; transform: translate(10px, -48px) scale(1.3); }
            }
            @keyframes logoutParticle3 {
                0%   { opacity: 0; transform: translate(0, 0) scale(0.5); }
                10%  { opacity: 0.65; }
                90%  { opacity: 0.25; }
                100% { opacity: 0; transform: translate(-14px, -32px) scale(0.9); }
            }
            @keyframes logoutParticle4 {
                0%   { opacity: 0; transform: translate(0, 0) scale(0.3) rotate(0deg); }
                20%  { opacity: 0.7; }
                80%  { opacity: 0.3; }
                100% { opacity: 0; transform: translate(24px, -44px) scale(1.15) rotate(25deg); }
            }
        `;
        document.head.appendChild(particleStyle);
    }

    container.appendChild(particlesEl);
}

/* ═══════════════════════════════════════════════════════════════
   DASHBOARD DECONSTRUCTION — phased exit
   ═══════════════════════════════════════════════════════════════ */
function deconstructDashboard() {
    // Deconstruction is delayed so the overlay opacity transition (0.45s)
    // has time to begin — the user sees a soft white veil descend FIRST,
    // then the dashboard elements start their graceful exit.
    const sidebar = document.querySelector('.shell-sidebar, #sidebar');
    const topbar = document.querySelector('.shell-topbar, #topbar, nav.topbar');
    const content = document.querySelector('.shell-content, .shell-main, main, .stage-grid');

    // Sidebar exits first — dramatic slide-left
    setTimeout(() => {
        if (sidebar) sidebar.classList.add('logout-exit-sidebar');
    }, 120);

    // Topbar exits next
    setTimeout(() => {
        if (topbar) topbar.classList.add('logout-exit-topbar');
    }, 180);

    // Content area exits last — sinks down with blur
    setTimeout(() => {
        if (content) content.classList.add('logout-exit-content');
    }, 240);
}

/* ═══════════════════════════════════════════════════════════════
   BUILD OVERLAY DOM
   ═══════════════════════════════════════════════════════════════ */
function buildOverlay(username) {
    const name = username || 'User';
    const overlay = document.createElement('div');
    overlay.className = 'evo-logout-overlay';
    overlay.id = 'evo-logout-overlay';

    // ── Backdrop (HERO — transitions first) ──
    const backdrop = document.createElement('div');
    backdrop.className = 'evo-logout-backdrop';
    overlay.appendChild(backdrop);

    // ── Vignette (subtle edge darkening) ──
    const vignette = document.createElement('div');
    vignette.className = 'evo-logout-vignette';
    overlay.appendChild(vignette);

    // ── Aura blobs ──
    const aura = document.createElement('div');
    aura.className = 'evo-logout-aura';
    overlay.appendChild(aura);

    const auraCyan = document.createElement('div');
    auraCyan.className = 'evo-logout-aura-cyan';
    overlay.appendChild(auraCyan);

    // ── Glass card ──
    const card = document.createElement('div');
    card.className = 'evo-logout-glass-card';

    card.innerHTML = `
        <!-- Shield with orbiting rings -->
        <div class="evo-logout-shield-wrap">
            <div class="evo-logout-orbit-ring"></div>
            <div class="evo-logout-orbit-ring-2"></div>
            <svg class="evo-logout-shield-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="evo-logout-svg-shield-fill" d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/>
                <path class="evo-logout-svg-shield" d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/>
                <path class="evo-logout-svg-lock-shackle" d="M10.5 12V10.25C10.5 9.42 11.17 8.75 12 8.75C12.83 8.75 13.5 9.42 13.5 10.25V12"/>
                <rect class="evo-logout-svg-lock-body" x="9.5" y="12" width="5" height="4.5" rx="0.75"/>
            </svg>
        </div>

        <div class="evo-logout-label">
            <i class="bi bi-shield-lock" style="font-size: 0.72rem; color: #6366f1;"></i>
            <span class="evo-logout-label-text">Securing Session</span>
        </div>
        <div class="evo-logout-name">Goodbye, ${escapeHTML(name)}</div>
        <div class="evo-logout-subtitle">Clearing credentials & encrypting session data</div>

        <div class="evo-logout-progress-track">
            <div class="evo-logout-progress-fill"></div>
        </div>

        <div class="evo-logout-escape">
            Masih di halaman ini?
            <br />
            <button type="button" data-evo-logout-escape>Keluar sekarang</button>
            <div class="evo-logout-escape-alt">
                atau <a href="${LOGOUT_RECOVERY_URL}">pulihkan tampilan</a>
            </div>
        </div>
    `;

    overlay.appendChild(card);

    // ── Floating particles ──
    createParticles(overlay);

    return overlay;
}

/** Minimal XSS guard for user-provided name */
function escapeHTML(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

/* ═══════════════════════════════════════════════════════════════
   PUBLIC API
   ═══════════════════════════════════════════════════════════════ */

/**
 * Run the cinematic logout transition overlay.
 *
 * The overlay covers the dashboard with a gradually-intensifying frosted glass
 * effect while a glassmorphism card animates in with a choreographed sequence
 * of icon draws, text reveals, and a progress bar. After ~3s the onComplete
 * callback fires — use it to navigate to /logout via Inertia.
 *
 * @param {string}   username   Display name for the "Goodbye, {name}" text
 * @param {Function} onComplete Called after ~3.1s when the overlay is fully opaque
 *                              (Inertia should navigate to /logout here)
 */
export function runLogoutTransition(username, onComplete) {
    if (logoutInjected) return;
    logoutInjected = true;
    clearLogoutTimers();

    // 1. Inject styles
    injectLogoutStyles();

    // 2. Build & mount overlay
    const overlay = buildOverlay(username);
    document.body.appendChild(overlay);

    // 3. Dashboard deconstruction (exit animations)
    deconstructDashboard();

    // 4. Force synchronous layout/paint so the browser registers the initial
    //    (inactive) state before we toggle is-active
    overlay.getBoundingClientRect();

    // 5. Fade in overlay smoothly
    requestAnimationFrame(() => {
        overlay.classList.add('is-active');
    });

    // 6. Fast, seamless handoff to /logout
    trackTimeout(() => {
        if (typeof onComplete === 'function') {
            try {
                onComplete();
            } catch (e) {
                console.warn('Logout handoff failed:', e);
            }
        }
    }, LOGOUT_HANDOFF_MS);

    /* ── FAILSAFE CHAIN ──────────────────────────────────────────
       Everything past this point only ever runs if the page is STILL HERE,
       i.e. the handoff did not navigate away. On a healthy logout the
       document is torn down long before any of these fire.
       ──────────────────────────────────────────────────────────── */

    // Esc always gets the user out, from the first frame.
    escapeKeyHandler = (event) => {
        if (event.key === 'Escape' || event.key === 'Esc') {
            abortLogoutTransition('escape-key');
        }
    };
    window.addEventListener('keydown', escapeKeyHandler);

    // Manual escape button inside the card (revealed later, wired now).
    const escapeButton = overlay.querySelector('[data-evo-logout-escape]');
    if (escapeButton) {
        escapeButton.addEventListener('click', () => {
            forceHardLogout();
        });
    }

    // Stage 1 — reveal the escape hatch so the user is never without an exit.
    trackTimeout(() => {
        overlay.classList.add('show-escape');
    }, LOGOUT_ESCAPE_HINT_MS);

    // Stage 2 — the handoff (Inertia visit / form submit) has not taken us
    // away. Fall back to a plain browser navigation, which does not depend on
    // the SPA being healthy.
    trackTimeout(() => {
        forceHardLogout();
    }, LOGOUT_NAV_FAILSAFE_MS);

    // Stage 3 — even the hard navigation did nothing (offline, server down).
    // Give the page back rather than holding the user behind an opaque veil.
    trackTimeout(() => {
        abortLogoutTransition('nav-failsafe-exhausted');
    }, LOGOUT_ABORT_MS);
}

/**
 * Plain browser navigation to /logout. Deliberately NOT an Inertia visit:
 * this is the path taken when the SPA is the thing that failed.
 */
function forceHardLogout() {
    try {
        window.location.assign('/logout');
    } catch (e) {
        abortLogoutTransition('hard-nav-threw');
    }
}

/**
 * Abandon the logout transition and hand the page back to the user.
 *
 * Used by the failsafes and by the Esc key. The localStorage flag is cleared
 * too, so the next page load cannot inherit a logout that never happened.
 *
 * @param {string} reason Logged for diagnosis; not shown to the user.
 */
export function abortLogoutTransition(reason = 'aborted') {
    console.warn('[logout] transition aborted:', reason);
    cleanupLogoutTransition();
}

/**
 * Clean up the logout overlay and injected styles.
 * Called by Login.vue after the entrance bridge fades out.
 */
export function cleanupLogoutTransition() {
    logoutInjected = false;

    // Cancel any pending choreography. Without this a handoff scheduled before
    // cleanup still fired afterwards and navigated the user out of a page they
    // had already been rescued onto.
    clearLogoutTimers();
    detachEscapeKey();
    restoreDashboard();

    const overlay = document.getElementById('evo-logout-overlay');
    if (overlay) {
        overlay.remove();
    }

    const style = document.getElementById('evo-logout-styles');
    if (style) {
        style.remove();
    }

    const particleStyle = document.getElementById('evo-logout-particles-kf');
    if (particleStyle) {
        particleStyle.remove();
    }

    try {
        localStorage.removeItem(LOGOUT_TRANSITION_FLAG_KEY);
        localStorage.removeItem(LOGOUT_TRANSITION_USER_KEY);
        localStorage.removeItem(LOGOUT_TRANSITION_TS_KEY);
    } catch (e) {
        // ignore
    }
}
