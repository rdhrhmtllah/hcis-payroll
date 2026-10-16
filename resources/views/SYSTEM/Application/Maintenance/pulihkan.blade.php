<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pulihkan Tampilan - HCIS EVO</title>
    {{--
        Tidak ada @vite dan tidak ada aset hasil build. Halaman ini dipakai justru
        saat aplikasi utamanya tidak bisa dipakai, jadi ia harus berdiri sendiri.
        Font Google dimuat TIDAK memblokir render (media=print lalu ditukar), agar
        jaringan yang bermasalah tidak menunda tampilnya halaman penyelamat ini.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" media="print" onload="this.media='all'">

    <style>
        * { box-sizing: border-box; }

        html, body {
            background: #eef0ff;
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        .shell {
            --bg-0: #eef0ff;
            --ink: #0b1033;
            --indigo: var(--hcis-indigo-600);
            --text: #0f1235;
            --text-soft: #5b5f86;
            --line: rgba(11, 16, 51, 0.1);

            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--text);
            background: var(--bg-0);
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* BACKDROP */
        .stage { position: absolute; inset: 0; overflow: hidden; z-index: 0; }
        .blob-svg {
            position: absolute; inset: 0; width: 100%; height: 100%;
            animation: ambientFade 2s ease-out forwards; opacity: 0;
            will-change: transform, opacity;
        }
        .grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(79, 70, 229, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(79, 70, 229, 0.05) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse at 50% 50%, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 50%, #000 30%, transparent 75%);
        }

        /* TOP NAV */
        .topbar {
            position: relative; z-index: 3; padding: 24px 56px;
            display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
        }
        .mark-login { display: flex; align-items: center; gap: 14px; }
        .brand-logo {
            height: 48px; width: auto; object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(11, 16, 51, 0.15));
        }
        .mark-login-text { display: flex; flex-direction: column; }
        .mark-login b { font-weight: 700; font-size: 16px; letter-spacing: -0.01em; }
        .mark-login small {
            display: block; font-size: 11px; color: var(--text-soft);
            letter-spacing: 0.18em; text-transform: uppercase; margin-top: 2px;
        }
        .v-pill {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 5px 14px; border-radius: 999px;
            background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(255, 255, 255, 0.65);
            font-size: 10px; color: var(--hcis-slate-600); font-weight: 500;
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 4px 12px rgba(11, 16, 51, 0.03); letter-spacing: 0.02em;
        }
        .status-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--hcis-emerald-500); box-shadow: 0 0 8px var(--hcis-emerald-500);
            animation: statusPulse 2s infinite ease-in-out;
        }
        .mobile-only { display: none; }

        /* MAIN */
        .stage-content {
            position: relative; z-index: 2; flex: 1 0 auto; padding: 30px 56px;
            display: flex; justify-content: center; align-items: center; width: 100%;
        }
        .err-card {
            position: relative;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.88) 0%, rgba(255, 255, 255, 0.55) 100%);
            backdrop-filter: blur(24px) saturate(200%);
            -webkit-backdrop-filter: blur(24px) saturate(200%);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 40px; padding: 40px 50px 36px;
            max-width: 580px; width: 100%; text-align: center;
            box-shadow:
                inset 0 2px 4px rgba(255, 255, 255, 0.8),
                inset 0 -2px 10px rgba(255, 255, 255, 0.3),
                0 40px 80px -20px rgba(11, 16, 51, 0.18),
                0 16px 40px -10px rgba(79, 70, 229, 0.1);
            animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            z-index: 2;
        }
        .card-glow {
            position: absolute; inset: -60px; border-radius: 50%;
            filter: blur(80px); pointer-events: none; z-index: 0; opacity: 0.85;
            transition: background 0.5s ease;
        }
        .error-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border: 1px solid transparent; border-radius: 999px;
            font-size: 10.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
            margin-bottom: 16px;
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 1; position: relative;
            transition: color 0.4s ease, border-color 0.4s ease, background 0.4s ease;
        }
        .status-chip-dot {
            width: 6px; height: 6px; border-radius: 50%;
            animation: statusPulse 1.5s infinite ease-in-out;
            transition: background 0.4s ease, box-shadow 0.4s ease;
        }

        .icon-orbit-wrap { position: relative; width: 90px; height: 90px; margin: 14px auto 18px; z-index: 2; }
        .orbit-ring {
            position: absolute; inset: -8px; border-radius: 50%;
            border: 2px solid transparent; animation: entranceRingOrbit 4.5s linear infinite; opacity: 0.75;
        }
        .orbit-ring-2 {
            position: absolute; inset: -16px; border-radius: 50%;
            border: 1.5px solid transparent; animation: entranceRingOrbit 6.5s linear infinite reverse; opacity: 0.55;
        }
        .icon-inner-glass {
            position: absolute; inset: 0; border-radius: 50%;
            border: 1px solid transparent;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(12px) saturate(1.1); -webkit-backdrop-filter: blur(12px) saturate(1.1);
            box-shadow: inset 0 2px 6px rgba(255, 255, 255, 0.4), 0 6px 20px rgba(11, 16, 51, 0.08);
            animation: floatIcon 3.5s ease-in-out infinite alternate;
            transition: background 0.4s ease, border-color 0.4s ease, color 0.4s ease;
        }
        .icon-inner-glass svg { width: 100%; height: 100%; }
        .icon-inner-glass .spin { animation: spin 2.5s linear infinite; }

        .headline {
            font-size: 26px; font-weight: 800; letter-spacing: -0.02em;
            margin: 0 0 10px; color: var(--ink); position: relative; z-index: 1;
        }
        .description {
            font-size: 15px; font-weight: 500; color: var(--text-soft); line-height: 1.55;
            margin: 0 auto 24px; max-width: 460px; position: relative; z-index: 1;
        }

        .action-buttons {
            display: flex; flex-direction: column; gap: 12px;
            max-width: 400px; margin: 0 auto; position: relative; z-index: 1;
        }
        .btn {
            width: 100%; padding: 14px 20px; border: 0; cursor: pointer; border-radius: 16px;
            font: 700 14px 'Plus Jakarta Sans', system-ui, sans-serif; letter-spacing: 0.02em;
            display: inline-flex; align-items: center; justify-content: center; text-decoration: none;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, background 0.3s ease;
        }
        .btn-primary { color: #fff; box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25); }
        .btn-primary:hover { transform: translateY(-3px) scale(1.01); }
        .btn-primary:active { transform: translateY(1px) scale(0.99); }
        .btn-secondary {
            background: rgba(11, 16, 51, 0.04); color: var(--ink);
            border: 1px solid rgba(11, 16, 51, 0.07);
        }
        .btn-secondary:hover { background: rgba(11, 16, 51, 0.08); transform: translateY(-2px); }
        .btn-secondary:active { transform: translateY(0); }

        /* SUBSIDIARIES */
        .subs {
            position: relative; z-index: 3;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 28px; margin: 0 56px 20px;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 24px; box-shadow: 0 12px 36px -12px rgba(11, 16, 51, 0.08);
            animation: revealUp 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            animation-delay: 0.2s; opacity: 0; flex-shrink: 0;
        }
        .subs .lbl {
            font-size: 10px; color: var(--text-soft); letter-spacing: 0.22em;
            text-transform: uppercase; font-weight: 600;
        }
        .subs .logos { display: flex; align-items: center; gap: 32px; }
        .subs .logos img { height: 28px; opacity: 0.88; }
        .subs .logos .dv { width: 1px; height: 20px; background: var(--line); }

        /* ANIMATIONS */
        @keyframes ambientFade {
            0% { opacity: 0; transform: scale(0.95) translateY(10px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes revealUp {
            0% { opacity: 0; transform: translateY(30px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes statusPulse {
            0%, 100% { opacity: 0.6; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        @keyframes entranceRingOrbit { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes floatIcon { 0% { transform: translateY(0); } 100% { transform: translateY(-4px); } }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* TABLET */
        @media (max-width: 1099px) {
            .topbar { padding: 22px 36px; }
            .stage-content { padding: 10px 36px; }
            .err-card { padding: 34px 40px 30px; max-width: 500px; }
            .subs { margin: 0 36px 20px; padding: 12px 22px; }
            .subs .lbl { font-size: 9px; letter-spacing: 0.18em; }
            .subs .logos { gap: 22px; }
            .subs .logos img { height: 22px; }
        }

        /* MOBILE */
        @media (max-width: 767px) {
            .topbar { padding: 12px 18px; gap: 8px; }
            .mark-login .brand-logo { height: 28px; }
            .mark-login b { font-size: 13px; }
            .mark-login small { font-size: 8.5px; letter-spacing: 0.1em; }
            .v-pill { padding: 3px 8px; font-size: 8px; gap: 4px; }
            .desktop-only { display: none !important; }
            .mobile-only { display: inline-flex; align-items: center; gap: 6px; }
            .stage-content { padding: 10px 16px; }
            .err-card { padding: 24px 20px 20px; border-radius: 28px; }
            .error-chip { font-size: 9.5px; padding: 4px 10px; margin-bottom: 12px; }
            .icon-orbit-wrap { width: 76px; height: 76px; margin: 8px auto 12px; }
            .orbit-ring { inset: -6px; }
            .orbit-ring-2 { inset: -12px; }
            .icon-inner-glass { padding: 16px; }
            .headline { font-size: 20px; }
            .description { font-size: 13.5px; margin-bottom: 18px; }
            .btn { padding: 12px 16px; font-size: 13px; }
            .subs {
                margin: 0 16px 12px; padding: 10px 14px;
                flex-direction: column; gap: 6px; text-align: center; border-radius: 16px;
            }
            .subs .lbl { font-size: 8px; letter-spacing: 0.14em; }
            .subs .logos { gap: 16px; justify-content: center; }
            .subs .logos img { height: 18px; }
            .subs .logos .dv { display: none; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <!-- BACKDROP -->
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
                <img src="/logo/EVOGROUP.png" alt="EVO Group" class="brand-logo">
                <div class="mark-login-text">
                    <b>HCIS</b>
                    <small>Sistem Terintegrasi</small>
                </div>
            </div>
            <div class="top-right">
                <span class="v-pill">
                    <span class="desktop-only"><span class="status-dot"></span> Pemeliharaan Perangkat</span>
                    <span class="mobile-only"><span class="status-dot"></span> Pemeliharaan</span>
                </span>
            </div>
        </nav>

        <!-- MAIN -->
        <main class="stage-content">
            <div class="err-card">
                <div class="card-glow" id="cardGlow"></div>

                <div class="error-chip" id="chip">
                    <span class="status-chip-dot" id="chipDot"></span>
                    <span id="chipText">Sedang Membersihkan</span>
                </div>

                <div class="icon-orbit-wrap">
                    <div class="orbit-ring" id="ring1"></div>
                    <div class="orbit-ring-2" id="ring2"></div>
                    <div class="icon-inner-glass" id="iconWrap">
                        <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                            <polyline points="21 3 21 9 15 9" />
                        </svg>
                    </div>
                </div>

                <h1 class="headline" id="headline">Sedang Membersihkan</h1>
                <p class="description" id="description">Mohon tunggu sebentar.</p>

                <div class="action-buttons">
                    <a class="btn btn-primary" id="btnLogin" href="{{ $loginUrl }}">
                        Masuk Kembali
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px;">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <button class="btn btn-secondary" type="button" id="btnUlangi">Bersihkan Lagi</button>
                </div>
            </div>
        </main>

        <!-- SUBSIDIARIES -->
        <div class="subs">
            <span class="lbl">Supported By</span>
            <div class="logos">
                <img src="/logo/EMI.png" alt="PT EVO Manufacturing Indonesia" loading="lazy" decoding="async">
                <span class="dv"></span>
                <img src="/logo/ENB.png" alt="PT EVO Nusa Bersaudara" loading="lazy" decoding="async">
                <span class="dv"></span>
                <img src="/logo/GMN.png" alt="PT Graha Maju Nusantara" loading="lazy" decoding="async">
            </div>
            <span class="lbl">Group of Companies</span>
        </div>
    </div>

    <script>
        (function () {
            var ICON_SUKSES =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" ' +
                'stroke-linecap="round" stroke-linejoin="round">' +
                '<path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z"/>' +
                '<polyline points="9 11.5 11.2 13.7 15 9.9"/></svg>';

            var ICON_GAGAL =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" ' +
                'stroke-linecap="round" stroke-linejoin="round">' +
                '<path d="M12 9v4M12 17h.01"/>' +
                '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';

            function terapkanTampilan(opsi) {
                var a = opsi.aksen[0];
                var b = opsi.aksen[1];

                document.getElementById('cardGlow').style.background =
                    'radial-gradient(circle, ' + a + '25 0%, transparent 70%)';

                var chip = document.getElementById('chip');
                chip.style.color = a;
                chip.style.borderColor = a + '40';
                chip.style.background = a + '08';

                var dot = document.getElementById('chipDot');
                dot.style.background = a;
                dot.style.boxShadow = '0 0 10px ' + a;

                var ring1 = document.getElementById('ring1');
                ring1.style.borderTopColor = a;
                ring1.style.borderRightColor = b;

                var ring2 = document.getElementById('ring2');
                ring2.style.borderBottomColor = b;
                ring2.style.borderLeftColor = a;

                var iconWrap = document.getElementById('iconWrap');
                iconWrap.style.background = a + '08';
                iconWrap.style.borderColor = a + '30';
                iconWrap.style.color = a;
                iconWrap.innerHTML = opsi.ikon;

                var btn = document.getElementById('btnLogin');
                btn.style.backgroundImage = 'linear-gradient(135deg, ' + a + ', ' + b + ')';
                btn.style.boxShadow = '0 12px 30px -8px ' + a + '60';

                document.getElementById('chipText').textContent = opsi.chip;
                document.getElementById('headline').textContent = opsi.judul;
                document.getElementById('description').textContent = opsi.deskripsi;
            }

            function bersihkan() {
                var adaYangGagal = false;

                // Sisa lapisan logout dibongkar lebih dulu, supaya kalau halaman ini
                // pun sempat terkena, ia langsung terbuka.
                try {
                    document.documentElement.classList.remove('evo-boot-logout-active');
                    ['evo-raw-boot-overlay', 'evo-logout-overlay', 'evo-logout-entrance-overlay',
                     'evo-logout-styles', 'evo-logout-particles-kf'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.remove();
                    });
                } catch (e) { adaYangGagal = true; }

                try { localStorage.clear(); } catch (e) { adaYangGagal = true; }
                try { sessionStorage.clear(); } catch (e) { adaYangGagal = true; }

                // Yang dilaporkan ke orang HANYA berhasil / belum berhasil. Nama
                // penyimpanan dan jumlah kuncinya tidak berarti apa-apa bagi mereka,
                // dan menyebutkannya justru membuat halaman penyelamat terasa rusak.
                if (adaYangGagal) {
                    terapkanTampilan({
                        aksen: ['#d97706', '#f59e0b'],
                        ikon: ICON_GAGAL,
                        chip: 'Belum Berhasil',
                        judul: 'Belum Bisa Dibersihkan',
                        deskripsi: 'Browser ini sedang menolak menghapus data situs. Coba buka halaman ' +
                                   'ini di jendela biasa, bukan mode penyamaran, lalu tekan Bersihkan Lagi.',
                    });
                    return;
                }

                terapkanTampilan({
                    aksen: ['#10b981', '#059669'],
                    ikon: ICON_SUKSES,
                    chip: 'Selesai',
                    judul: 'Tampilan Sudah Dipulihkan',
                    deskripsi: 'Sisa data sementara di perangkat ini sudah dibersihkan. ' +
                               'Silakan masuk kembali seperti biasa.',
                });
            }

            bersihkan();
            document.getElementById('btnUlangi').addEventListener('click', bersihkan);
        })();
    </script>
</body>
</html>
