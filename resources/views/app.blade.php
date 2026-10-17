<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.header')
    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    @vite('resources/js/app.js')
    @inertiaHead

    <!-- Anti-flash script for cinematic logout transition -->
    <script>
        // Keep in sync with LOGOUT_TRANSITION_TTL_MS in resources/js/utils/logout-transition.js.
        // The flag is self-expiring: a logout animation is never relevant more than a few
        // seconds after it started, so a stale flag is cleared here instead of trapping the
        // user behind the boot overlay on every page load ("nyangkut").
        window.EVO_LOGOUT_TRANSITION_TTL_MS = 15000;
        window.evoLogoutTransitionFresh = function() {
            return false;
        };
        (function() {
            try {
                if (!window.evoLogoutTransitionFresh()) return;
                document.documentElement.classList.add('evo-boot-logout-active');

                // GAGAL-TERBUKA: lapisan boot ini hanya dibongkar oleh Vue
                // (Login.vue atau AppShell). Kalau Vue TIDAK PERNAH mount —
                // halaman blade lama, halaman error, bundle gagal termuat —
                // tidak ada yang membongkarnya dan orang melihat kartu
                // "Goodbye" menempel di halaman mana pun. Bongkar sendiri
                // setelah jendela yang jelas lebih lama dari boot normal.
                setTimeout(function() {
                    try {
                        document.documentElement.classList.remove('evo-boot-logout-active');
                        var o = document.getElementById('evo-raw-boot-overlay');
                        if (o) o.remove();
                        localStorage.removeItem('evo_logout_transition_active');
                        localStorage.removeItem('evo_logout_username');
                        localStorage.removeItem('evo_logout_transition_ts');
                    } catch(e) {}
                }, 8000);
            } catch(e) {}
        })();
    </script>
    <style>
        /* Boot Overlay Styles — EXACTLY matches logout/login overlay style to bridge the first-paint load gap */
        .evo-boot-logout-active #evo-raw-boot-overlay {
            display: flex !important;
        }

        #evo-raw-boot-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999999;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            background: transparent;
        }

        #evo-raw-boot-overlay .boot-backdrop {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.14) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.1) 0%, transparent 45%),
                radial-gradient(circle at 50% 70%, rgba(168, 85, 247, 0.06) 0%, transparent 50%),
                linear-gradient(180deg,
                    rgba(248, 250, 252, 0.94) 0%,
                    rgba(238, 242, 255, 0.96) 50%,
                    rgba(255, 255, 255, 0.97) 100%
                );
            backdrop-filter: blur(40px) saturate(1.4);
            -webkit-backdrop-filter: blur(40px) saturate(1.4);
        }

        #evo-raw-boot-overlay .boot-vignette {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 55%, rgba(15, 23, 42, 0.07) 100%);
            pointer-events: none;
        }

        #evo-raw-boot-overlay .boot-aura {
            position: absolute;
            width: 520px;
            height: 520px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.16) 0%, rgba(168, 85, 247, 0.07) 40%, transparent 70%);
            filter: blur(60px);
        }

        #evo-raw-boot-overlay .boot-aura-cyan {
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.14) 0%, rgba(99, 102, 241, 0.04) 50%, transparent 70%);
            filter: blur(50px);
        }

        #evo-raw-boot-overlay .boot-glass-card {
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
                0 24px 60px rgba(15, 23, 42, 0.1),
                0 4px 16px rgba(15, 23, 42, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.88),
                0 0 0 1px rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(24px) saturate(1.16);
            -webkit-backdrop-filter: blur(24px) saturate(1.16);
        }

        #evo-raw-boot-overlay .boot-shield-wrap {
            position: relative;
            width: 108px;
            height: 108px;
            margin-bottom: 28px;
        }

        #evo-raw-boot-overlay .boot-shield-svg {
            width: 108px;
            height: 108px;
            filter: drop-shadow(0 8px 28px rgba(99, 102, 241, 0.22));
        }

        #evo-raw-boot-overlay .boot-svg-fill {
            fill: rgba(99, 102, 241, 0.07);
        }

        #evo-raw-boot-overlay .boot-svg-stroke {
            fill: none;
            stroke: rgba(99, 102, 241, 0.55);
            stroke-width: 1;
        }

        #evo-raw-boot-overlay .boot-svg-lock {
            fill: none;
            stroke: var(--hcis-indigo-600);
            stroke-width: 1.2;
        }

        #evo-raw-boot-overlay .boot-label {
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

        #evo-raw-boot-overlay .boot-name {
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

        #evo-raw-boot-overlay .boot-subtitle {
            position: relative;
            z-index: 1;
            font-size: 13px;
            font-weight: 600;
            color: var(--hcis-slate-500);
            letter-spacing: 0.02em;
            margin-bottom: 28px;
        }

        #evo-raw-boot-overlay .boot-progress-track {
            position: relative;
            z-index: 1;
            width: 220px;
            height: 3px;
            border-radius: 4px;
            background: rgba(99, 102, 241, 0.08);
            overflow: hidden;
        }

        #evo-raw-boot-overlay .boot-progress-fill {
            height: 100%;
            width: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-cyan-500), var(--hcis-purple-500), var(--hcis-indigo-500));
            background-size: 300% 100%;
        }

        @media (max-width: 640px) {
            #evo-raw-boot-overlay .boot-glass-card {
                padding: 40px 28px 36px;
                margin: 0 20px;
                border-radius: 1.2rem;
            }
            #evo-raw-boot-overlay .boot-shield-wrap {
                width: 84px;
                height: 84px;
                margin-bottom: 22px;
            }
            #evo-raw-boot-overlay .boot-shield-svg {
                width: 84px;
                height: 84px;
            }
            #evo-raw-boot-overlay .boot-name {
                font-size: 22px;
            }
            #evo-raw-boot-overlay .boot-progress-track {
                width: 170px;
            }
            #evo-raw-boot-overlay .boot-aura {
                width: 320px;
                height: 320px;
            }
            #evo-raw-boot-overlay .boot-aura-cyan {
                width: 240px;
                height: 240px;
            }
        }
    </style>
</head>
<body>
    @inertia

    <!-- Raw HTML Overlay to prevent white-flash on logout redirect -->
    <div id="evo-raw-boot-overlay">
        <div class="boot-backdrop"></div>
        <div class="boot-vignette"></div>
        <div class="boot-aura"></div>
        <div class="boot-aura-cyan"></div>
        <div class="boot-glass-card">
            <div class="boot-shield-wrap">
                <svg class="boot-shield-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path class="boot-svg-fill" d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/>
                    <path class="boot-svg-stroke" d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/>
                    <path class="boot-svg-lock" d="M10.5 12V10.25C10.5 9.42 11.17 8.75 12 8.75C12.83 8.75 13.5 9.42 13.5 10.25V12"/>
                    <rect class="boot-svg-lock" x="9.5" y="12" width="5" height="4.5" rx="0.75"/>
                </svg>
            </div>
            <div class="boot-label">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="#6366f1" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4H4.5A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7H11z"/>
                </svg>
                Session Secured
            </div>
            <div class="boot-name" id="evo-boot-username-target">Goodbye, User</div>
            <div class="boot-subtitle">Returning to secure portal...</div>
            <div class="boot-progress-track">
                <div class="boot-progress-fill"></div>
            </div>
        </div>
    </div>

    <!-- Inline script to set the username and cleanup raw overlay -->
    <script>
        (function() {
            try {
                var fresh = typeof window.evoLogoutTransitionFresh === 'function'
                    ? window.evoLogoutTransitionFresh()
                    : localStorage.getItem('evo_logout_transition_active') === 'true';
                if (fresh) {
                    const username = localStorage.getItem('evo_logout_username') || 'User';
                    const target = document.getElementById('evo-boot-username-target');
                    if (target) {
                        target.textContent = 'Goodbye, ' + username;
                    }
                }
            } catch(e) {}
        })();
    </script>
</body>
</html>
