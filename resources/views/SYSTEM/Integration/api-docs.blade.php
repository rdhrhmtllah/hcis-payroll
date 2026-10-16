<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="robots" content="noindex, nofollow">
    <title>API Documentation & Integration Contract — HCIS</title>
    
    {{--
        Huruf di-host sendiri, BUKAN dari Google Fonts. Halaman ini dibuka oleh
        developer pihak ketiga dari jaringan kantor mereka; CDN yang terblokir
        di sana menjatuhkan hurufnya tanpa pesan apa pun.
    --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/hcis-fonts.css') }}">
    
    <style>
        :root {
            /* ── Neutral & Slate Tokens ──────────────────────────────────── */
            --hcis-white: #ffffff;
            --hcis-black: #000000;
            --hcis-slate-50: #f8fafc;
            --hcis-slate-100: #f1f5f9;
            --hcis-slate-200: #e2e8f0;
            --hcis-slate-300: #cbd5e1;
            --hcis-slate-400: #94a3b8;
            --hcis-slate-500: #64748b;
            --hcis-slate-600: #475569;
            --hcis-slate-700: #334155;
            --hcis-slate-800: #1e293b;
            --hcis-slate-900: #0f172a;
            --hcis-slate-950: #020617;

            /* ── Brand Primary (Indigo) ──────────────────────────────────── */
            --hcis-brand-50: #eef2ff;
            --hcis-brand-100: #e0e7ff;
            --hcis-brand-200: #c7d2fe;
            --hcis-brand-300: #a5b4fc;
            --hcis-brand-400: #818cf8;
            --hcis-brand-500: #6366f1;
            --hcis-brand-600: #4f46e5;
            --hcis-brand-700: #4338ca;
            --hcis-brand-800: #3730a3;
            --hcis-brand-900: #312e81;

            /* ── Method Colors ───────────────────────────────────────────── */
            --method-get: #0284c7;
            --method-get-bg: #e0f2fe;
            --method-post: #059669;
            --method-post-bg: #d1fae5;
            --method-put: #d97706;
            --method-put-bg: #fef3c7;
            --method-patch: #0891b2;
            --method-patch-bg: #cffafe;
            --method-delete: #dc2626;
            --method-delete-bg: #fee2e2;

            /* ── Radius & Shadows ────────────────────────────────────────── */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --radius-pill: 9999px;

            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.05);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 10px 25px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.04);
            --shadow-card: 0 12px 36px -4px rgba(15, 23, 42, 0.07), 0 4px 12px -2px rgba(15, 23, 42, 0.03);

            --font-sans: 'Nunito', system-ui, -apple-system, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 100vw;
            overflow-x: clip;
            font-family: var(--font-sans);
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Background */
        .ambient-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
            background:
                radial-gradient(55rem 35rem at 12% 10%, rgba(99, 102, 241, 0.12), transparent 70%),
                radial-gradient(50rem 30rem at 88% 20%, rgba(14, 165, 233, 0.10), transparent 65%),
                radial-gradient(60rem 40rem at 50% 85%, rgba(16, 185, 129, 0.08), transparent 70%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .ambient-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(99, 102, 241, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.05) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(ellipse at 50% 45%, #000 50%, transparent 90%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 45%, #000 50%, transparent 90%);
        }

        /* ── Top Navbar ──────────────────────────────────────────────── */
        .hcis-navbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 24px;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1.5px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            transition: all 0.2s ease;
        }

        .hcis-navbar::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5 0%, #06b6d4 50%, #10b981 100%);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .brand-logo-img {
            height: 36px;
            width: auto;
            max-width: 48px;
            object-fit: contain;
            display: block;
        }

        .brand-logo-badge {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: #ffffff;
            font-weight: 900;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 8px rgba(79, 70, 229, 0.3);
        }

        .brand-title {
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 10.5px;
            font-weight: 700;
            color: #4f46e5;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* Top Nav Segmented Tabs (Endpoint vs Dokumentasi) */
        .top-nav-tabs {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            padding: 3px;
            border-radius: var(--radius-pill);
            border: 1px solid #cbd5e1;
            gap: 2px;
        }

        .top-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            background: transparent;
            border: none;
            border-radius: var(--radius-pill);
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            line-height: 1.2;
            text-decoration: none;
        }

        .top-tab-btn:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }

        .top-tab-btn.active {
            background: #ffffff;
            color: #4338ca;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1);
        }

        .top-tab-badge {
            font-size: 10.5px;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: var(--radius-pill);
            background: #e0e7ff;
            color: #3730a3;
        }

        .top-tab-btn.active .top-tab-badge {
            background: #4f46e5;
            color: #ffffff;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .key-info-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .dot-status {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
        }

        .key-tag-code {
            padding: 1px 6px;
            border-radius: 4px;
            background: #fef3c7;
            color: #92400e;
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 800;
            border: 1px solid #fde68a;
        }

        .key-tag-public {
            font-family: var(--font-mono);
            font-size: 11px;
            color: #0f172a;
        }

        .btn-change-key {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: var(--radius-md);
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-change-key:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
            transform: translateY(-1px);
        }

        /* ── Gate View (Auth Screen) ─────────────────────────────────── */
        .gerbang-wrap {
            position: relative;
            z-index: 10;
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .gerbang-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .gerbang-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            background: #e0e7ff;
            border: 1.5px solid #a5b4fc;
            color: #3730a3;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
        }

        .gerbang-header h1 {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 8px;
            line-height: 1.25;
            background: linear-gradient(135deg, #0f172a 0%, #312e81 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gerbang-header p {
            font-size: 13.5px;
            color: #475569;
            margin: 0 auto;
            line-height: 1.55;
            max-width: 500px;
        }

        .kartu-gerbang {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-top: 4.5px solid #4f46e5;
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            box-shadow: 0 20px 35px -5px rgba(15, 23, 42, 0.08);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pill-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .pill-required { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .pill-optional { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            color: #64748b;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            height: 44px;
            padding: 8px 14px 8px 38px;
            font-size: 13px;
            font-family: var(--font-mono);
            color: #0f172a;
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .input-toggle-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: #64748b;
            padding: 6px;
            cursor: pointer;
            border-radius: var(--radius-sm);
        }

        .form-help {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
            line-height: 1.45;
        }

        .btn-submit {
            width: 100%;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 800;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-1.5px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45);
        }

        .galat-banner {
            margin-top: 16px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            color: #b91c1c;
            font-size: 12.5px;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        /* ── Main App Layout (2-Column Endpoint View) ────────────────── */
        .app-container {
            position: relative;
            z-index: 10;
            max-width: 1560px;
            margin: 0 auto;
            padding: 20px 24px 60px;
        }

        .layout-split {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        /* ── Left Sidebar (Endpoint List & Search) ────────────────────── */
        .endpoint-sidebar {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-xl);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            position: sticky;
            top: 72px;
            align-self: flex-start;
            max-height: calc(100vh - 88px);
            display: flex;
            flex-direction: column;
            z-index: 20;
        }

        .sidebar-header {
            padding: 16px 18px 12px;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
            flex-shrink: 0;
        }

        .sidebar-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .sidebar-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-count-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }

        .search-box-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            color: #94a3b8;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            height: 36px;
            padding: 6px 30px 6px 32px;
            font-size: 12.5px;
            font-family: var(--font-sans);
            color: #0f172a;
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.15s ease;
        }

        .search-input:focus {
            background: #ffffff;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .search-clear-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 16px;
            cursor: pointer;
            padding: 2px;
        }

        .search-clear-btn:hover { color: #0f172a; }

        .sidebar-scroll {
            padding: 10px 10px 18px;
            overflow-y: auto;
            flex: 1;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* Category Accordion */
        .category-group {
            margin-bottom: 14px;
        }

        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            cursor: pointer;
            border-radius: var(--radius-sm);
            user-select: none;
            transition: all 0.15s ease;
        }

        .category-header:hover {
            background: #f8fafc;
        }

        .category-title-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .category-chevron {
            color: #64748b;
            transition: transform 0.2s ease;
        }

        .category-group.is-collapsed .category-chevron {
            transform: rotate(-90deg);
        }

        .category-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .category-count {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: var(--radius-pill);
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .category-items {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 4px;
            padding-left: 6px;
        }

        .category-group.is-collapsed .category-items {
            display: none;
        }

        /* Sidebar Endpoint Item */
        .endpoint-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-decoration: none;
            color: #334155;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            font-size: 12.5px;
            line-height: 1.35;
        }

        .endpoint-nav-item:hover {
            background: #f8fafc;
            color: #0f172a;
        }

        .endpoint-nav-item.active {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #312e81;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(99, 102, 241, 0.1);
        }

        .method-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            padding: 2px 6px;
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 800;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            flex-shrink: 0;
            color: #ffffff;
        }

        .method-badge.get { background: var(--method-get); }
        .method-badge.post { background: var(--method-post); }
        .method-badge.put { background: var(--method-put); }
        .method-badge.patch { background: var(--method-patch); }
        .method-badge.delete { background: var(--method-delete); }

        .endpoint-nav-summary {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Right Column (Workspace / Detail) ────────────────────────── */
        .endpoint-workspace {
            min-height: 600px;
        }

        /* Empty State */
        .workspace-empty-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-xl);
            padding: 80px 30px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .empty-icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 1.5px solid #e2e8f0;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px;
        }

        .empty-sub {
            font-size: 13px;
            color: #64748b;
            max-width: 440px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Selected Endpoint Card */
        .endpoint-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-xl);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .endpoint-card-header {
            padding: 22px 24px 18px;
            border-bottom: 1.5px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .endpoint-path-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .path-display {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .path-text {
            font-family: var(--font-mono);
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .btn-action:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-action.copied {
            background: #ecfdf5;
            border-color: #10b981;
            color: #047857;
        }

        .endpoint-main-summary {
            font-size: 19px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 6px;
            letter-spacing: -0.015em;
        }

        /* Protocol & Security Chips Bar */
        .protocol-chips-bar {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .protocol-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .protocol-chip.chip-indigo { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
        .protocol-chip.chip-emerald { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .protocol-chip.chip-amber { background: #fffbeb; color: #92400e; border-color: #fde68a; }

        /* Workspace Segmented Sub-Tabs (4 Subtabs) */
        .workspace-subtabs-nav {
            display: flex;
            align-items: center;
            background: #f1f5f9;
            padding: 4px;
            margin: 18px 24px 0;
            border-radius: var(--radius-lg);
            border: 1px solid #cbd5e1;
            gap: 4px;
            overflow-x: auto;
        }

        .subtab-btn {
            flex: 1;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 14px;
            font-family: var(--font-sans);
            font-size: 12.5px;
            font-weight: 700;
            color: #64748b;
            background: transparent;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .subtab-btn:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }

        .subtab-btn.active {
            background: #ffffff;
            color: #4338ca;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
        }

        .workspace-content {
            padding: 24px;
        }

        /* ── VISUAL FLOWCHART STEPPER ────────────────────────────────── */
        .visual-stepper-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%);
            border: 1.5px solid #c7d2fe;
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .stepper-step {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .stepper-node {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #4f46e5;
            color: #ffffff;
            font-size: 11px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(79, 70, 229, 0.3);
        }

        .stepper-info {
            display: flex;
            flex-direction: column;
        }

        .stepper-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #1e1b4b;
            line-height: 1.2;
        }

        .stepper-sub {
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }

        .stepper-arrow {
            color: #818cf8;
            font-size: 12px;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        /* ── VISUAL HIGHLIGHTS CARDS (GRID) ─────────────────────────── */
        .highlights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .highlight-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            transition: all 0.15s ease;
        }

        .highlight-card:hover {
            border-color: #c7d2fe;
            background: #f8faff;
            transform: translateY(-1px);
        }

        .highlight-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .highlight-icon.indigo { background: #e0e7ff; }
        .highlight-icon.emerald { background: #d1fae5; }
        .highlight-icon.amber { background: #fef3c7; }
        .highlight-icon.sky { background: #e0f2fe; }

        .highlight-content {
            flex: 1;
        }

        .highlight-title {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .highlight-desc {
            font-size: 11.5px;
            color: #64748b;
            line-height: 1.45;
        }

        /* ── SECTION BLOCKS & SPECIFICATION HEADERS ──────────────────── */
        .section-block {
            margin-bottom: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }

        .section-header-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header-icon {
            width: 30px;
            height: 30px;
            border-radius: var(--radius-sm);
            background: var(--hcis-brand-50);
            color: var(--hcis-brand-600);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .section-header-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
            margin: 0;
            line-height: 1.25;
        }

        .section-header-subtitle {
            font-size: 11.5px;
            color: #64748b;
            margin: 2px 0 0 0;
            line-height: 1.35;
        }

        .spec-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 2.5px 9px;
            border-radius: var(--radius-pill);
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .spec-content-type-badge {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-xs);
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }

        /* ── VISUAL FIELD CARDS LIST (Parameters & Body) ─────────────── */
        .field-cards-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .field-card-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .field-card-item.is-required {
            border-left-color: #f43f5e;
        }

        .field-card-item.is-optional {
            border-left-color: #94a3b8;
        }

        .field-card-item:hover {
            border-color: #cbd5e1;
            background: #fafbfc;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
            transform: translateY(-1px);
        }

        .field-card-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .field-name-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .field-name-mono {
            font-family: var(--font-mono);
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: var(--radius-xs);
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            user-select: all;
            transition: all 0.15s ease;
        }

        .field-name-mono:hover {
            background: #e2e8f0;
            color: #0284c7;
            border-color: #94a3b8;
        }

        .field-name-copy-icon {
            opacity: 0.45;
            transition: opacity 0.15s ease;
            flex-shrink: 0;
        }

        .field-name-mono:hover .field-name-copy-icon {
            opacity: 1;
        }

        .spec-req-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: var(--radius-xs);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .spec-req-badge.wajib {
            background: #fff1f2;
            color: #e11d48;
            border: 1px solid #ffe4e6;
        }

        .spec-req-badge.opsional {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .spec-in-badge {
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .spec-type-badge {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .spec-type-constraint {
            color: #0284c7;
            font-weight: 500;
            opacity: 0.85;
        }

        .spec-default-badge {
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .spec-enum-chip {
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .field-card-middle {
            font-size: 12.5px;
            color: #334155;
            line-height: 1.5;
        }

        .field-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 8px;
            border-top: 1px dashed #f1f5f9;
            flex-wrap: wrap;
        }

        .field-example-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            max-width: 100%;
        }

        .field-example-label {
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 10px;
            white-space: nowrap;
        }

        .field-example-val {
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 5px;
            background: #f8fafc;
            color: #047857;
            border: 1px solid #cbd5e1;
            max-width: 360px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }

        .field-copy-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .field-copy-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* ── BODY PAYLOAD DARK CARD (application/json) ───────────────── */
        .body-payload-card {
            background: #0b1120;
            border: 1px solid #1e293b;
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-top: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.1);
        }

        .body-payload-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 14px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
            gap: 10px;
            flex-wrap: wrap;
        }

        .body-payload-title-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .body-payload-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
        }

        .body-payload-title {
            font-size: 12px;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: -0.01em;
        }

        .body-payload-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .body-payload-lines-badge {
            font-family: var(--font-mono);
            font-size: 10.5px;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .body-payload-copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            font-size: 11.5px;
            font-weight: 700;
            background: #1e293b;
            color: #cbd5e1;
            border: 1px solid #334155;
            border-radius: var(--radius-xs);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .body-payload-copy-btn:hover {
            background: #334155;
            color: #ffffff;
            border-color: #475569;
        }

        .body-payload-copy-btn.copied {
            background: #064e3b;
            color: #34d399;
            border-color: #059669;
        }

        .body-payload-pre {
            margin: 0;
            padding: 14px 16px;
            background: #0b1120;
            color: #e2e8f0;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.55;
            overflow-x: auto;
            max-height: 380px;
        }

        /* ── SPEC EMPTY STATE NOTICE ─────────────────────────────────── */
        .spec-empty-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 20px;
            background: #ffffff;
            border: 1.5px dashed #cbd5e1;
            border-radius: var(--radius-lg);
            color: #475569;
            margin-bottom: 20px;
        }

        .spec-empty-icon {
            width: 22px;
            height: 22px;
            color: #64748b;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .spec-empty-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .spec-empty-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.45;
        }

        /* Legacy helper pill support (for tryout compatibility) */
        .field-type-pill {
            font-family: var(--font-mono);
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .field-in-pill {
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            background: #e0e7ff;
            color: #3730a3;
        }

        .example-chip {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f8fafc;
            color: #047857;
            border: 1px solid #cbd5e1;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── CONTOH RESPONSE INTERACTIVE VIEWER (Screenshot-matched) ──── */
        .response-viewer-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .res-viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .res-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .res-header-caret-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: none;
            border: none;
            padding: 0;
            font-family: var(--font-sans);
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            cursor: pointer;
            text-align: left;
        }

        .res-caret-icon {
            color: #64748b;
            transition: transform 0.2s ease;
        }

        .response-viewer-card.is-collapsed .res-caret-icon {
            transform: rotate(-90deg);
        }

        .res-count-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
        }

        .res-header-toggle-link {
            background: none;
            border: none;
            font-family: var(--font-sans);
            font-size: 12.5px;
            font-weight: 700;
            color: #d97706;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            transition: all 0.15s ease;
        }

        .res-header-toggle-link:hover {
            background: #fef3c7;
        }

        .res-viewer-content {
            transition: all 0.2s ease;
        }

        .response-viewer-card.is-collapsed .res-viewer-content {
            display: none;
        }

        /* Horizontal Pills Row */
        .res-pills-row {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 2px 14px;
            margin-bottom: 8px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .res-pills-row::-webkit-scrollbar {
            height: 4px;
        }

        .res-pills-row::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .res-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            border: 1.5px solid transparent;
            background: #ffffff;
            transition: all 0.15s ease;
            user-select: none;
            flex-shrink: 0;
        }

        .res-pill-code {
            font-family: var(--font-mono);
            font-weight: 800;
        }

        .res-pill-2xx {
            background: #f0fdf4;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .res-pill-2xx:hover {
            background: #dcfce7;
            border-color: #6ee7b7;
        }
        .res-pill-2xx.active {
            background: #ffffff;
            border-color: #059669;
            color: #047857;
            box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.25), 0 2px 4px rgba(5, 150, 105, 0.1);
        }

        .res-pill-4xx {
            background: #fffbeb;
            border-color: #fed7aa;
            color: #c2410c;
        }
        .res-pill-4xx:hover {
            background: #fef3c7;
            border-color: #fdba74;
        }
        .res-pill-4xx.active {
            background: #ffffff;
            border-color: #ea580c;
            color: #c2410c;
            box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.25), 0 2px 4px rgba(234, 88, 12, 0.1);
        }

        .res-pill-5xx {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
        .res-pill-5xx:hover {
            background: #fee2e2;
            border-color: #f87171;
        }
        .res-pill-5xx.active {
            background: #ffffff;
            border-color: #dc2626;
            color: #991b1b;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25), 0 2px 4px rgba(220, 38, 38, 0.1);
        }

        .res-active-desc {
            font-size: 13px;
            color: #475569;
            line-height: 1.55;
            margin-bottom: 14px;
            padding-left: 2px;
        }

        /* Dark Interactive Card */
        .res-dark-card {
            background: #0b1120;
            border: 1px solid #1e293b;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .res-dark-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
        }

        .res-dark-title {
            font-size: 13px;
            font-weight: 800;
            color: #f8fafc;
            letter-spacing: -0.01em;
        }

        .res-dark-copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            font-size: 11.5px;
            font-weight: 700;
            background: #1e293b;
            color: #cbd5e1;
            border: 1px solid #334155;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .res-dark-copy-btn:hover {
            background: #334155;
            color: #ffffff;
            border-color: #475569;
        }

        .res-dark-copy-btn.copied {
            background: #064e3b;
            color: #34d399;
            border-color: #059669;
        }

        .res-dark-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: #0b1120;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            gap: 12px;
            flex-wrap: wrap;
        }

        .res-toolbar-left, .res-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .res-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 600;
            background: transparent;
            color: #94a3b8;
            border: 1px solid #334155;
            border-radius: var(--radius-xs);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .res-tool-btn:hover {
            background: #1e293b;
            color: #ffffff;
            border-color: #475569;
        }

        .res-tool-btn.active {
            background: #1e293b;
            color: #38bdf8;
            border-color: #0284c7;
        }

        .res-depth-group {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-left: 2px;
        }

        .res-depth-btn {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            background: transparent;
            border: 1px solid #334155;
            border-radius: var(--radius-xs);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .res-depth-btn:hover {
            background: #1e293b;
            color: #ffffff;
        }

        .res-depth-btn.active {
            background: #38bdf8;
            color: #0f172a;
            border-color: #38bdf8;
            font-weight: 900;
        }

        .res-search-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .res-search-icon {
            position: absolute;
            left: 8px;
            color: #64748b;
            pointer-events: none;
        }

        .res-search-mini {
            height: 26px;
            width: 110px;
            padding: 2px 8px 2px 24px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            color: #f8fafc;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: var(--radius-xs);
            outline: none;
            transition: all 0.15s ease;
        }

        .res-search-mini:focus {
            border-color: #38bdf8;
            width: 150px;
        }

        .res-stats-text {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: #64748b;
            white-space: nowrap;
        }

        /* Code Viewer Scroll & Line Table */
        .res-code-scroll {
            padding: 14px 16px;
            overflow-x: auto;
            max-height: 520px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.6;
            color: #f8fafc;
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }

        .res-code-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .res-code-scroll::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }

        .res-code-scroll {
            padding: 12px 16px;
            max-height: 480px;
            overflow: auto;
            font-family: var(--font-mono);
            background: #0b1120;
        }

        .res-code-scroll.is-wrapped .res-line-text {
            white-space: pre-wrap !important;
            word-break: break-word !important;
        }

        .res-code-scroll:not(.is-wrapped) .res-line-text {
            white-space: pre !important;
        }

        .res-code-viewer {
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.65;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            tab-size: 2;
        }

        .res-code-line {
            display: flex;
            align-items: baseline;
            min-height: 22px;
            font-family: var(--font-mono);
        }

        .res-code-line.is-hidden {
            display: none;
        }

        .res-code-line.is-match-highlight {
            background: rgba(56, 189, 248, 0.18);
            border-radius: 2px;
        }

        .res-line-gutter {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            width: 46px;
            min-width: 46px;
            flex-shrink: 0;
            user-select: none;
            padding-right: 10px;
            margin-right: 8px;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        .res-line-num {
            color: #475569;
            font-size: 11px;
            font-family: var(--font-mono);
            text-align: right;
            min-width: 22px;
        }

        .res-fold-caret {
            width: 14px;
            height: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            padding: 0;
            color: #64748b;
            font-size: 10px;
            cursor: pointer;
            border-radius: 2px;
            line-height: 1;
            transition: all 0.15s ease;
        }

        .res-fold-caret:hover {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.12);
        }

        .res-fold-caret-placeholder {
            width: 14px;
            height: 14px;
            display: inline-block;
        }

        .res-line-text {
            flex: 1;
            color: #e2e8f0;
            white-space: pre !important;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.65;
        }

        .json-indent {
            white-space: pre !important;
            display: inline-block;
            user-select: none;
        }

        .res-collapsed-ellipsis {
            display: inline-flex;
            align-items: center;
            padding: 0 6px;
            margin: 0 4px;
            font-size: 10.5px;
            font-weight: 700;
            border-radius: 4px;
            background: #1e293b;
            color: #38bdf8;
            border: 1px solid #334155;
            cursor: pointer;
            user-select: none;
        }

        .res-collapsed-ellipsis:hover {
            background: #334155;
            color: #ffffff;
        }

        .json-bracket { color: #94a3b8; font-weight: 600; }
        .json-key { color: #38bdf8; font-weight: 700; }
        .json-string { color: #4ade80; }
        .json-number { color: #fb923c; }
        .json-boolean { color: #c084fc; font-weight: 700; }
        .json-null { color: #94a3b8; font-style: italic; }
        .json-search-match { background: #fef08a; color: #000000; border-radius: 2px; padding: 0 2px; font-weight: 700; }

        /* ── Ruang Coba (Try It Out) Interactive Section ─────────────── */
        .tryout-card {
            background: #ffffff;
            border: 1.5px solid #e0e7ff;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(79, 70, 229, 0.07);
            margin-bottom: 24px;
        }

        /* Inline Auth Secret Helper Card */
        .tryout-auth-card {
            background: #fffdf5;
            border: 1.5px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.06);
        }

        .tryout-auth-icon {
            color: #d97706;
            flex-shrink: 0;
            margin-top: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #fef3c7;
            border-radius: var(--radius-md);
        }

        .tryout-auth-content {
            flex: 1;
        }

        .tryout-auth-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #92400e;
            margin-bottom: 3px;
        }

        .tryout-auth-desc {
            font-size: 12px;
            color: #78350f;
            line-height: 1.55;
            margin-bottom: 12px;
        }

        .tryout-auth-form {
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 540px;
        }

        .tryout-auth-input-wrap {
            position: relative;
            flex: 1;
        }

        .tryout-inline-secret {
            width: 100%;
            height: 36px;
            padding: 4px 34px 4px 12px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: #0f172a;
            background: #ffffff;
            border: 1.5px solid #f59e0b;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.15s ease;
        }

        .tryout-inline-secret:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        .btn-icon-soft {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-xs);
        }

        .btn-icon-soft:hover {
            color: #475569;
        }

        .btn-save-inline-secret {
            height: 36px;
            padding: 0 16px;
            background: #d97706;
            color: #ffffff;
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 800;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(217, 119, 6, 0.25);
        }

        .btn-save-inline-secret:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        /* Tryout Header Bar & Quick Actions */
        .tryout-header-bar {
            padding: 12px 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border-bottom: 1.5px solid #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tryout-indicator-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }

        .live-pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
            animation: pulseDot 2s infinite ease-in-out;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.85); }
        }

        .tryout-header-path {
            color: #334155;
            font-weight: 700;
        }

        .tryout-header-tools {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tryout-tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 32px;
            padding: 0 12px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .tryout-tool-btn:hover {
            color: #4338ca;
            border-color: #818cf8;
            background: #f5f3ff;
            transform: translateY(-1px);
        }

        .tryout-mode-pill-group {
            display: inline-flex;
            background: #e2e8f0;
            padding: 2.5px;
            border-radius: var(--radius-md);
            gap: 2px;
        }

        .tryout-mode-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 27px;
            padding: 0 10px;
            font-family: var(--font-sans);
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: transparent;
            border: none;
            border-radius: var(--radius-xs);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .tryout-mode-btn.active {
            color: #4338ca;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        .tryout-body {
            padding: 22px;
        }

        .tryout-section-heading {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
        }

        .tryout-field-item {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 10px 12px;
            transition: all 0.15s ease;
        }

        .tryout-field-item:focus-within {
            border-color: #818cf8;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.10);
        }

        .tryout-field-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .tryout-field-title {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .tryout-field-meta {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .field-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .field-input {
            width: 100%;
            height: 38px;
            padding: 6px 12px;
            font-family: var(--font-sans);
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.15s ease;
        }

        .field-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }

        textarea.field-input {
            height: auto;
            min-height: 68px;
            resize: vertical;
        }

        .tryout-raw-editor {
            width: 100%;
            min-height: 240px;
            padding: 14px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.55;
            color: #0f172a;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            outline: none;
            resize: vertical;
            transition: all 0.15s ease;
        }

        .tryout-raw-editor:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        /* Collapsible 5-Header HMAC Live Security Preview */
        .tryout-hmac-drawer {
            margin-top: 22px;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            background: #f8fafc;
            overflow: hidden;
        }

        .tryout-hmac-toggle {
            width: 100%;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 700;
            color: #4338ca;
            text-align: left;
            transition: background 0.15s ease;
        }

        .tryout-hmac-toggle:hover {
            background: #eef2ff;
        }

        .tryout-hmac-content {
            padding: 12px 14px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .tryout-hmac-table-wrap {
            overflow-x: auto;
        }

        .hmac-preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        .hmac-preview-table th {
            text-align: left;
            padding: 6px 10px;
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
        }

        .hmac-preview-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-family: var(--font-mono);
            color: #334155;
            word-break: break-all;
        }

        .mono-val {
            font-family: var(--font-mono);
            color: #0f172a;
        }

        .text-brand {
            color: #4f46e5;
            font-weight: 700;
        }

        /* Tryout Execute Footer Bar */
        .tryout-execute-bar {
            background: #f8faff;
            border-top: 1.5px dashed #c7d2fe;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tryout-footer-note {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: #64748b;
        }

        .tryout-footer-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-copy-curl-soft {
            height: 38px;
            padding: 0 14px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            color: #475569;
            font-family: var(--font-sans);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .btn-copy-curl-soft:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-execute {
            height: 38px;
            padding: 0 22px;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 800;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(79, 70, 229, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .btn-execute:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(79, 70, 229, 0.45);
        }

        .btn-execute:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Live Response Result Frame */
        .live-response-frame {
            border: 2px solid #0284c7;
            border-radius: var(--radius-xl);
            background: #020617;
            color: #ffffff;
            overflow: hidden;
            margin-top: 24px;
            box-shadow: 0 8px 28px rgba(2, 132, 199, 0.20);
        }

        .live-response-header {
            padding: 12px 18px;
            background: #0b1120;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .live-res-title {
            font-size: 12.5px;
            font-weight: 800;
            color: #38bdf8;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .live-res-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .res-status-tag {
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
        }

        .res-status-2xx { background: #064e3b; color: #34d399; border: 1px solid #059669; }
        .res-status-err { background: #7f1d1d; color: #f87171; border: 1px solid #dc2626; }

        .res-latency-tag {
            font-family: var(--font-mono);
            font-size: 11px;
            color: #94a3b8;
        }

        .live-response-body {
            padding: 16px 20px;
            max-height: 550px;
            overflow-x: auto;
            overflow-y: auto;
        }

        /* ── Endpoint Description Rich Formatting ────────────────────────── */
        .desc-lead-box {
            padding: 16px 20px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            color: #334155;
            font-size: 13.5px;
            line-height: 1.65;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
        }

        .desc-lead-box p {
            margin: 0 0 8px;
        }

        .desc-lead-box p:last-child {
            margin-bottom: 0;
        }

        .desc-feature-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 14px;
        }

        .desc-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-left: 4px solid #4f46e5;
            border-radius: var(--radius-md);
            padding: 12px 18px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
            transition: all 0.18s ease;
        }

        .desc-feature-item:hover {
            border-color: #cbd5e1;
            border-left-color: #3730a3;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }

        .desc-feature-head {
            flex-shrink: 0;
            margin-top: 1px;
        }

        .desc-feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .desc-feature-badge::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4f46e5;
        }

        .desc-bullet-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6366f1;
            margin-top: 6px;
            flex-shrink: 0;
        }

        .desc-feature-body {
            flex: 1;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }

        .desc-feature-body strong {
            color: #0f172a;
            font-weight: 700;
        }

        .desc-inline-code {
            font-family: var(--font-mono);
            font-size: 11.5px;
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            font-weight: 700;
        }

        .inline-method-tag {
            display: inline-flex;
            align-items: center;
            padding: 1px 6px;
            border-radius: 4px;
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #ffffff;
            vertical-align: middle;
        }

        .inline-method-tag.GET { background: var(--method-get); }
        .inline-method-tag.POST { background: var(--method-post); }
        .inline-method-tag.PUT { background: var(--method-put); }
        .inline-method-tag.PATCH { background: var(--method-patch); }
        .inline-method-tag.DELETE { background: var(--method-delete); }

        .desc-link {
            color: #4f46e5;
            text-decoration: underline;
            font-weight: 600;
        }
        .desc-link:hover {
            color: #3730a3;
        }

        /* ── Rich Dokumentasi Tab Styles ─────────────────────────────── */
        .docs-full-container {
            max-width: 1040px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Hero Card */
        .doc-hero-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            border: 1.5px solid #334155;
            border-radius: var(--radius-xl);
            padding: 36px 40px;
            color: #ffffff;
            box-shadow: 0 12px 36px -4px rgba(15, 23, 42, 0.15);
            position: relative;
            overflow: hidden;
        }

        .doc-hero-card::after {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .doc-hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            background: rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(165, 180, 252, 0.4);
            color: #c7d2fe;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }

        .doc-hero-title {
            font-size: 24px;
            font-weight: 900;
            color: #ffffff;
            margin: 0 0 10px;
            line-height: 1.3;
        }

        .doc-hero-desc {
            font-size: 14px;
            color: #cbd5e1;
            line-height: 1.6;
            margin: 0 0 20px;
            max-width: 800px;
        }

        .doc-protocol-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .doc-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
            font-size: 11.5px;
            font-weight: 700;
        }

        .badge-dot-green {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
        }

        /* Section Card */
        .doc-section-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-xl);
            padding: 28px 32px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        }

        .doc-section-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
            border-bottom: 1.5px solid #f1f5f9;
            padding-bottom: 16px;
        }

        .doc-section-num {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: #ffffff;
            font-size: 14px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(79, 70, 229, 0.3);
        }

        .doc-section-title {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px;
        }

        .doc-section-sub {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }

        /* Table */
        .doc-table-wrap {
            overflow-x: auto;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-lg);
        }

        .doc-spec-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
        }

        .doc-spec-table thead {
            background: #f8fafc;
            border-bottom: 1.5px solid #cbd5e1;
        }

        .doc-spec-table th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }

        .doc-spec-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            line-height: 1.55;
            vertical-align: top;
        }

        .doc-spec-table tr:last-child td {
            border-bottom: none;
        }

        .doc-spec-table tr:hover td {
            background: #f8fafc;
        }

        .doc-header-code {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 800;
            color: #4338ca;
            background: #eef2ff;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #c7d2fe;
            display: inline-block;
        }

        .doc-type-badge {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 700;
            color: #047857;
            background: #d1fae5;
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }

        .doc-warn-chip {
            display: inline-block;
            margin-top: 4px;
            font-size: 10.5px;
            font-weight: 700;
            color: #b91c1c;
            background: #fee2e2;
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid #fca5a5;
        }

        /* Canonical Grid */
        .canonical-visual-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 18px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .canonical-visual-grid {
                grid-template-columns: 1fr;
            }
        }

        .canonical-step-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .canonical-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            font-size: 12.5px;
        }

        .can-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .can-key {
            font-family: var(--font-mono);
            font-weight: 800;
            color: #0f172a;
            font-size: 11.5px;
            min-width: 95px;
            flex-shrink: 0;
        }

        .can-desc {
            color: #64748b;
            font-size: 12px;
            flex: 1;
        }

        .canonical-formula-card {
            background: #020617;
            border: 1.5px solid #1e293b;
            border-radius: var(--radius-lg);
            padding: 18px;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        }

        .formula-card-title {
            font-size: 12px;
            font-weight: 800;
            color: #38bdf8;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .formula-pre {
            margin: 0;
            font-family: var(--font-mono);
            font-size: 11.5px;
            line-height: 1.6;
            color: #a5f3fc;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* SDK Multi-Language Switcher */
        .sdk-tabs-container {
            border: 1.5px solid #1e293b;
            border-radius: var(--radius-lg);
            background: #020617;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .sdk-tab-buttons {
            display: flex;
            background: #0b1120;
            border-bottom: 1px solid #1e293b;
            padding: 6px 8px 0;
            gap: 4px;
            overflow-x: auto;
        }

        .sdk-tab-btn {
            padding: 8px 16px;
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            background: transparent;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .sdk-tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .sdk-tab-btn.active {
            color: #38bdf8;
            background: #020617;
            border-color: #1e293b;
            border-top: 2px solid #38bdf8;
            font-weight: 800;
        }

        .sdk-code-panel {
            display: none;
        }

        .sdk-code-panel.active {
            display: block;
        }

        .sdk-code-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #0b1120;
            border-bottom: 1px solid #1e293b;
        }

        .sdk-lang-label {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: #64748b;
            font-weight: 700;
        }

        .btn-sdk-copy {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            font-family: var(--font-sans);
            font-size: 11.5px;
            font-weight: 700;
            border-radius: var(--radius-xs);
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-sdk-copy:hover {
            background: #334155;
            color: #ffffff;
        }

        .btn-sdk-copy.copied {
            background: #064e3b;
            color: #34d399;
            border-color: #059669;
        }

        .sdk-pre {
            margin: 0;
            padding: 16px;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.6;
            color: #f1f5f9;
            overflow-x: auto;
            max-height: 480px;
        }

        /* Endpoint Specific SDK Code Tab */
        .ep-code-card {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        }

        .ep-code-header {
            margin-bottom: 16px;
        }

        .ep-code-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px;
            letter-spacing: -0.01em;
        }

        .ep-code-sub {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }

        /* ── LIVE RESPONSE TERMINAL CARD ────────────────────────────── */
        .live-response-container {
            border: 1.5px solid #0284c7;
            border-radius: var(--radius-xl);
            background: #0b1120;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(2, 132, 199, 0.15), 0 4px 12px rgba(15, 23, 42, 0.08);
            animation: slideUpFade 0.25s ease-out;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .live-res-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-bottom: 1px solid #334155;
            gap: 12px;
            flex-wrap: wrap;
        }

        .live-res-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .live-res-status-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-res-label {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .live-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .live-status-pill.pill-2xx {
            background: #064e3b;
            color: #34d399;
            border: 1px solid #059669;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.25);
        }

        .live-status-pill.pill-4xx {
            background: #451a03;
            color: #fb923c;
            border: 1px solid #ea580c;
            box-shadow: 0 0 12px rgba(234, 88, 12, 0.25);
        }

        .live-status-pill.pill-5xx {
            background: #450a0a;
            color: #f87171;
            border: 1px solid #dc2626;
            box-shadow: 0 0 12px rgba(220, 38, 38, 0.25);
        }

        .live-res-metrics {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .live-metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            font-size: 11.5px;
            font-weight: 600;
            font-family: var(--font-mono);
        }

        .live-metric-pill svg {
            color: #38bdf8;
        }

        .live-metric-pill .metric-val {
            color: #f1f5f9;
            font-weight: 700;
        }

        .btn-copy-live-res {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 13px;
            border-radius: var(--radius-sm);
            font-family: var(--font-sans);
            font-size: 12px;
            font-weight: 700;
            background: #0284c7;
            color: #ffffff;
            border: 1px solid #38bdf8;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
        }

        .btn-copy-live-res:hover {
            background: #0369a1;
            transform: translateY(-1px);
        }

        .btn-copy-live-res.copied {
            background: #059669;
            border-color: #34d399;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }

        .live-terminal-box {
            background: #020617;
        }

        .live-terminal-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: #0b1120;
            border-bottom: 1px solid #1e293b;
            gap: 10px;
            flex-wrap: wrap;
        }

        .terminal-tool-left, .terminal-tool-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-code-scroll {
            background: #020617;
            padding: 10px 0;
            max-height: 480px;
            min-height: 60px;
            overflow-y: auto;
            overflow-x: auto;
        }

        /* Standard JSON Response Grid */
        .doc-response-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 800px) {
            .doc-response-grid {
                grid-template-columns: 1fr;
            }
        }

        .doc-res-box {
            border-radius: var(--radius-lg);
            background: #020617;
            border: 1.5px solid #1e293b;
            overflow: hidden;
        }

        .doc-res-box-header {
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 800;
            border-bottom: 1px solid #1e293b;
        }

        .doc-res-box-header.success {
            background: #064e3b;
            color: #34d399;
        }

        .doc-res-box-header.error {
            background: #7f1d1d;
            color: #fca5a5;
        }

        .doc-res-pre {
            margin: 0;
            padding: 14px;
            font-family: var(--font-mono);
            font-size: 12px;
            line-height: 1.55;
            color: #e2e8f0;
            overflow-x: auto;
        }

        /* Security Grid */
        .doc-security-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        @media (max-width: 700px) {
            .doc-security-grid {
                grid-template-columns: 1fr;
            }
        }

        .security-card {
            padding: 16px 18px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-lg);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .security-card-icon {
            font-size: 20px;
            margin-bottom: 2px;
        }

        .security-card-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
        }

        .security-card-desc {
            font-size: 12.5px;
            color: #475569;
            line-height: 1.55;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }

        .toast-msg {
            background: #0f172a;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            border: 1px solid #334155;
            animation: slideUp 0.25s ease-out;
            pointer-events: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive Mobile Layout */
        @media (max-width: 960px) {
            .layout-split {
                grid-template-columns: 1fr;
            }
            .endpoint-sidebar {
                position: static;
                max-height: none;
            }
            .hcis-navbar {
                padding: 10px 16px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .app-container {
                padding: 14px 14px 40px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="ambient-bg">
    <div class="ambient-grid"></div>
</div>

<!-- Global Top Navbar -->
<header class="hcis-navbar">
    <div class="nav-left">
        <a href="#" class="navbar-brand">
            <img src="{{ asset('logo/logo.png') }}" alt="HCIS Logo" class="brand-logo-img">
            <div>
                <div class="brand-title">HCIS Integration Hub</div>
                <div class="brand-subtitle">OpenAPI Documentation & Sandbox</div>
            </div>
        </a>

        <!-- Top Nav Switcher (Active after Key validation) -->
        <nav id="topNavTabs" class="top-nav-tabs" style="display: none;">
            <button type="button" id="btnNavEndpoints" class="top-tab-btn active">
                <span>Endpoint</span>
                <span id="navEndpointCount" class="top-tab-badge">0</span>
            </button>
            <button type="button" id="btnNavDocumentation" class="top-tab-btn">
                <span>Dokumentasi</span>
            </button>
        </nav>
    </div>

    <div class="nav-right">
        <div id="keyInfoPill" class="key-info-pill" style="display: none;">
            <span class="dot-status"></span>
            <span id="labelKeyCode" class="key-tag-code">B2B</span>
            <span id="labelKeyMasked" class="key-tag-public">••••••••</span>
        </div>
        <button type="button" id="btnGantiKunci" class="btn-change-key" style="display: none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
            <span>Ganti Kunci</span>
        </button>
    </div>
</header>

<!-- GATE VIEW: Masukkan Kunci API -->
<main id="viewGate" class="gerbang-wrap">
    <div class="gerbang-header">
        <span class="gerbang-tag">Portal Integrasi</span>
        <h1>Dokumentasi & Uji Coba API</h1>
        <p>Masukkan kunci publik (<strong>Api_Public</strong>) yang diberikan oleh tim HCIS untuk memuat spesifikasi endpoint yang terotorisasi untuk aplikasi Anda.</p>
    </div>

    <div class="kartu-gerbang">
        <form id="formGerbang" onsubmit="event.preventDefault(); muatDokumentasi();">
            <div class="form-group">
                <div class="form-label-wrap">
                    <label for="inputApiPublic" class="form-label">
                        <span>Api_Public (Kunci Publik Aplikasi)</span>
                    </label>
                    <span class="pill-badge pill-required">Wajib</span>
                </div>
                <div class="input-group">
                    <span class="input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input type="text" id="inputApiPublic" class="form-input" placeholder="contoh: WEBCAREERS_PROD_..." autocomplete="off" spellcheck="false" required>
                </div>
                <span class="form-help">Kunci publik digunakan server HCIS untuk menyaring daftar endpoint yang diizinkan untuk Anda.</span>
            </div>

            <div class="form-group">
                <div class="form-label-wrap">
                    <label for="inputApiSecret" class="form-label">
                        <span>Api_Secret (Kunci Rahasia Penandatangan)</span>
                    </label>
                    <span class="pill-badge pill-optional">Opsional (Uji Coba)</span>
                </div>
                <div class="input-group">
                    <span class="input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </span>
                    <input type="password" id="inputApiSecret" class="form-input" placeholder="Masukkan jika ingin mengirim request uji coba nyata" autocomplete="off">
                    <button type="button" id="btnToggleSecret" class="input-toggle-btn" title="Lihat/Sembunyikan Secret">
                        <svg id="iconEye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg id="iconEyeOff" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>
                <span class="form-help">Diperlukan hanya jika ingin melakukan pengujian request langsung (Try It Out). Boleh dikosongkan untuk sekadar membaca spesifikasi.</span>
            </div>

            <button type="submit" id="btnSubmitGate" class="btn-submit">
                <span id="btnGateLabel">Buka Dokumentasi & Endpoint</span>
                <span id="btnGateSpinner" class="spinner" style="display: none;"></span>
            </button>

            <div id="gateErrorBanner" class="galat-banner" style="display: none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span id="gateErrorText"></span>
            </div>
        </form>
    </div>
</main>

<!-- MAIN APP VIEW (Active after loading) -->
<main id="viewDocsMain" class="app-container" style="display: none;">

    <!-- TAB 1: ENDPOINT 2-COLUMN VIEW -->
    <div id="tabContentEndpoints" class="layout-split">
        
        <!-- Left Column: Sidebar Endpoints -->
        <aside class="endpoint-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title-row">
                    <h2 class="sidebar-title">
                        <span>Endpoint</span>
                        <span id="sidebarTotalCount" class="sidebar-count-badge">0</span>
                    </h2>
                </div>
                <div class="search-box-wrap">
                    <span class="search-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" id="sidebarSearchInput" class="search-input" placeholder="Cari endpoint..." autocomplete="off">
                    <button type="button" id="btnSearchClear" class="search-clear-btn" style="display: none;">&times;</button>
                </div>
            </div>

            <div id="sidebarAccordionList" class="sidebar-scroll">
                <!-- Di-render dinamis oleh JavaScript -->
            </div>
        </aside>

        <!-- Right Column: Workspace / Detail -->
        <section class="endpoint-workspace">
            
            <!-- Empty State (Saat belum ada endpoint yang dipilih) -->
            <div id="workspaceEmptyState" class="workspace-empty-card">
                <div class="empty-icon-wrap">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <h3 class="empty-title">Pilih satu endpoint</h3>
                <p class="empty-sub">Rinciannya — parameter, contoh permintaan, skema jawaban, dan ruang coba — akan tampil di sini.</p>
            </div>

            <!-- Detail Endpoint (Saat endpoint dipilih) -->
            <div id="workspaceDetailCard" class="endpoint-card" style="display: none;">
                
                <!-- Endpoint Header -->
                <div class="endpoint-card-header">
                    <div class="endpoint-path-row">
                        <div class="path-display">
                            <span id="detailMethodBadge" class="method-badge post">POST</span>
                            <span id="detailPathText" class="path-text">/api/v1/...</span>
                        </div>
                        <div class="header-actions">
                            <button type="button" id="btnCopyEndpointUrl" class="btn-action">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                <span>Salin URL</span>
                            </button>
                            <button type="button" id="btnCopyCurl" class="btn-action">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                <span>Salin cURL</span>
                            </button>
                        </div>
                    </div>

                    <h1 id="detailSummaryText" class="endpoint-main-summary">-</h1>
                    <div id="detailProtocolChips" class="protocol-chips-bar"></div>
                </div>

                <!-- Segmented Subtabs: 4 Subtabs -->
                <div class="workspace-subtabs-nav">
                    <button type="button" id="subtabBtnDesc" class="subtab-btn active">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span>Deskripsi & Alur</span>
                    </button>
                    <button type="button" id="subtabBtnParams" class="subtab-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                        <span>Struktur & Parameter</span>
                    </button>
                    <button type="button" id="subtabBtnCode" class="subtab-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                        <span>Contoh Kode Integrasi</span>
                    </button>
                    <button type="button" id="subtabBtnTryout" class="subtab-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                        <span>Ruang Coba (Try It Out)</span>
                    </button>
                </div>

                <!-- Subtab 1: Deskripsi & Alur Bisnis -->
                <div id="subtabContentDesc" class="workspace-content">
                    <!-- Visual Integration Flow Stepper -->
                    <div id="detailVisualStepper" class="visual-stepper-wrap"></div>

                    <!-- Visual Highlights Grid (Alur & Aturan Bisnis) -->
                    <div id="detailHighlightsGrid" class="highlights-grid"></div>

                    <!-- Concise Description Text -->
                    <div id="detailDescriptionBox" class="description-box"></div>
                </div>

                <!-- Subtab 2: Struktur & Parameter Request Body & Skema Response -->
                <div id="subtabContentParams" class="workspace-content" style="display: none;">
                    
                    <!-- Notice when no parameters and no request body are needed -->
                    <div id="detailNoParamsNotice" class="spec-empty-notice" style="display: none;">
                        <svg class="spec-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        <div>
                            <div class="spec-empty-title">Tidak Memerlukan Parameter URL / Request Body</div>
                            <div class="spec-empty-desc">Endpoint ini dapat dipanggil langsung ke URL target tanpa memerlukan query parameter, path parameter, maupun JSON payload body.</div>
                        </div>
                    </div>

                    <!-- Visual Field Cards: Parameters Section (Query / Path) -->
                    <div id="detailParametersSection" class="section-block" style="display: none;">
                        <div class="section-header-wrap">
                            <div class="section-header-left">
                                <span class="section-header-icon">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                                </span>
                                <div>
                                    <h4 class="section-header-title">Parameter Permintaan (Query & Path)</h4>
                                    <p class="section-header-subtitle">Parameter yang disertakan pada URL query string atau path variable.</p>
                                </div>
                            </div>
                            <div class="section-header-right">
                                <span id="detailParamsCountBadge" class="spec-count-badge">0 parameter</span>
                            </div>
                        </div>
                        <div id="detailParametersList" class="field-cards-list"></div>
                    </div>

                    <!-- Visual Field Cards: Request Body Properties & JSON Specification -->
                    <div id="detailRequestBodySection" class="section-block" style="display: none;">
                        <div class="section-header-wrap">
                            <div class="section-header-left">
                                <span class="section-header-icon">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                </span>
                                <div>
                                    <h4 class="section-header-title">Struktur & Parameter Request Body</h4>
                                    <p class="section-header-subtitle">Skema atribut data JSON yang wajib/opsional dikirimkan dalam HTTP body.</p>
                                </div>
                            </div>
                            <div class="section-header-right" style="display: flex; align-items: center; gap: 6px;">
                                <span class="spec-content-type-badge">application/json</span>
                                <span id="detailBodyCountBadge" class="spec-count-badge">0 field</span>
                            </div>
                        </div>

                        <div id="detailBodyFieldsList" class="field-cards-list" style="margin-bottom: 16px;"></div>

                        <!-- Dark Code Card for JSON Payload Baku -->
                        <div class="body-payload-card" id="detailBodyPayloadCard">
                            <div class="body-payload-topbar">
                                <div class="body-payload-title-group">
                                    <span class="body-payload-dot"></span>
                                    <span class="body-payload-title">application/json · Contoh Payload Baku</span>
                                </div>
                                <div class="body-payload-actions">
                                    <span id="detailBodyLineCount" class="body-payload-lines-badge">0 baris</span>
                                    <button type="button" id="btnCopyRequestBodyExample" class="body-payload-copy-btn">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        <span>Salin JSON Payload</span>
                                    </button>
                                </div>
                            </div>
                            <pre id="detailRequestBodyCode" class="body-payload-pre"></pre>
                        </div>
                    </div>

                    <!-- Contoh Response Interactive Viewer (Matched with Screenshot) -->
                    <div class="response-viewer-card" id="resViewerCard">
                        <!-- Header Bar -->
                        <div class="res-viewer-header" id="resViewerHeader">
                            <div class="res-header-left">
                                <button type="button" class="res-header-caret-btn" id="btnToggleResCollapse">
                                    <svg class="res-caret-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    <span class="res-header-title">Contoh Response / Skema Jawaban</span>
                                </button>
                                <span class="res-count-badge" id="resTotalCountBadge">8 kode</span>
                            </div>
                            <button type="button" class="res-header-toggle-link" id="btnToggleResText">Sembunyikan</button>
                        </div>

                        <!-- Collapsible Viewer Body -->
                        <div class="res-viewer-content" id="resViewerContent">
                            <!-- Horizontal Pills Row -->
                            <div class="res-pills-row" id="resPillsRow"></div>

                            <!-- Active Description Text -->
                            <div class="res-active-desc" id="resActiveDesc"></div>

                            <!-- Dark Code Card -->
                            <div class="res-dark-card" id="resDarkCard">
                                <!-- Topbar with title and copy -->
                                <div class="res-dark-topbar">
                                    <div class="res-dark-title" id="resDarkTitle">Contoh jawaban · 200</div>
                                    <button type="button" class="res-dark-copy-btn" id="btnCopyResponseJson">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        <span id="btnCopyResponseJsonLabel">Salin</span>
                                    </button>
                                </div>

                                <!-- Toolbar -->
                                <div class="res-dark-toolbar">
                                    <div class="res-toolbar-left">
                                        <button type="button" class="res-tool-btn" id="btnResExpandAll" title="Buka seluruh node">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M9 3v18M15 3v18M3 9h18M3 15h18"></path></svg>
                                            <span>Buka semua</span>
                                        </button>
                                        <button type="button" class="res-tool-btn" id="btnResCollapseAll" title="Tutup semua node">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                            <span>Tutup semua</span>
                                        </button>
                                        <div class="res-depth-group">
                                            <button type="button" class="res-depth-btn" data-depth="1" title="Buka level 1">1</button>
                                            <button type="button" class="res-depth-btn" data-depth="2" title="Buka level 2">2</button>
                                            <button type="button" class="res-depth-btn" data-depth="3" title="Buka level 3">3</button>
                                        </div>
                                    </div>

                                    <div class="res-toolbar-right">
                                        <div class="res-search-input-wrap">
                                            <span class="res-search-icon">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                            </span>
                                            <input type="text" id="inputResFilter" class="res-search-mini" placeholder="Cari..." autocomplete="off">
                                        </div>
                                        <button type="button" class="res-tool-btn" id="btnResWrap" title="Bungkus baris">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 10 4 15 9 20"></polyline><path d="M20 4v7a4 4 0 0 1-4 4H4"></path></svg>
                                            <span>Bungkus</span>
                                        </button>
                                        <span class="res-stats-text" id="resStatsText">0 baris · 0 B</span>
                                    </div>
                                </div>

                                <!-- Foldable Code Area with Line Numbers -->
                                <div class="res-code-scroll" id="resCodeScroll">
                                    <div class="res-code-viewer" id="resCodeViewer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subtab 3: Contoh Kode Pemanggilan (PHP, JS, Python, cURL, Go) -->
                <div id="subtabContentCode" class="workspace-content" style="display: none;">
                    <div class="ep-code-card">
                        <div class="ep-code-header">
                            <h3 class="ep-code-title">💻 Contoh Kode Integrasi SDK & API</h3>
                            <p class="ep-code-sub">Contoh implementasi lengkap pemanggilan endpoint ini beserta perhitungan 5 Header Keamanan HMAC-SHA512 otomatis.</p>
                        </div>

                        <!-- Language Switcher Tabs Container -->
                        <div class="sdk-tabs-container">
                            <div class="sdk-tab-buttons" id="epCodeLanguageButtons">
                                <button type="button" class="sdk-tab-btn active" data-lang="php_curl">🐘 PHP (cURL)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="php_guzzle">🐘 PHP (Laravel Http)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="js_fetch">🟨 JS (Fetch)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="js_axios">🟨 JS (Axios)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="python">🐍 Python (Requests)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="curl">🐚 cURL (Terminal)</button>
                                <button type="button" class="sdk-tab-btn" data-lang="go">🔵 Go (net/http)</button>
                            </div>

                            <div class="sdk-code-topbar">
                                <div class="sdk-lang-label" id="epCodeActiveLangLabel">PHP (cURL Native)</div>
                                <button type="button" class="btn-sdk-copy" id="btnCopyEpCodeSnippet">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <span id="btnCopyEpCodeSnippetLabel">Salin Kode</span>
                                </button>
                            </div>

                            <div class="res-code-scroll" style="max-height: 520px; background: #020617;">
                                <pre id="epCodeSnippetDisplay" class="sdk-pre" style="padding: 18px 22px; font-family: var(--font-mono); font-size: 12.5px; line-height: 1.65; color: #f8fafc; margin: 0;"></pre>
                            </div>
                        </div>

                        <!-- Security Notice Box -->
                        <div style="margin-top: 18px; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: flex-start; gap: 12px;">
                            <div style="font-size: 20px; line-height: 1;">🛡️</div>
                            <div style="font-size: 12.5px; color: #475569; line-height: 1.55;">
                                <strong style="color: #0f172a;">Catatan Keamanan & Otentikasi HMAC:</strong>
                                Kode di atas otomatis diisi dengan <code>Api_Public</code> Anda. Pastikan string kanonikal 7 baris dihitung secara presisi sesuai urutan standar: <code>METHOD \n PATH \n QUERY \n TIMESTAMP \n NONCE \n BODY_HASH \n API_PUBLIC</code>.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subtab Content: Ruang Coba (Try It Out) -->
                <div id="subtabContentTryout" class="workspace-content" style="display: none;">
                    
                    <!-- Auth Notice jika Api_Secret belum diisi / Kredensial Status -->
                    <div id="tryoutAuthNotice" class="tryout-auth-card" style="display: none;">
                        <div class="tryout-auth-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 2l-2 2m-1.5 1.5L16 7l-1.5-1.5M16 7l4 4-1.5 1.5M13 10l-2.5 2.5a5.5 5.5 0 1 1-7.78 0 5.5 5.5 0 0 1 7.78 0z"></path></svg>
                        </div>
                        <div class="tryout-auth-content">
                            <div class="tryout-auth-title">Mode Read-Only Aktif (Api_Secret Belum Disetel)</div>
                            <div class="tryout-auth-desc">Untuk menandatangani request nyata ke server backend HCIS dengan validasi <code>HMAC-SHA512</code>, masukkan <strong>Api_Secret</strong> Anda di bawah ini:</div>
                            <div class="tryout-auth-form">
                                <div class="tryout-auth-input-wrap">
                                    <input type="password" id="tryoutInlineSecretInput" class="tryout-inline-secret" placeholder="Masukkan Api_Secret Anda..." autocomplete="off">
                                    <button type="button" id="btnTryoutToggleSecretVisibility" class="btn-icon-soft" title="Lihat/Sembunyikan Secret">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                                <button type="button" id="btnTryoutSaveSecret" class="btn-save-inline-secret">
                                    <span>Simpan & Aktifkan HMAC</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="tryout-card">
                        <!-- Top Toolbar / Header -->
                        <div class="tryout-header-bar">
                            <div class="tryout-header-left">
                                <div class="tryout-indicator-badge">
                                    <span class="live-pulse-dot"></span>
                                    <span id="tryoutHeaderMethodPill" class="method-tag tag-post">POST</span>
                                    <span id="tryoutHeaderPathText" class="tryout-header-path">/api/v1/hcis/visitor/permit-tamu</span>
                                </div>
                            </div>
                            <div class="tryout-header-tools">
                                <button type="button" id="btnTryoutFillExample" class="tryout-tool-btn" title="Isi form dengan contoh data OpenAPI">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3z"></path></svg>
                                    <span>Isi Contoh Data</span>
                                </button>
                                <button type="button" id="btnTryoutClearForm" class="tryout-tool-btn" title="Kosongkan form untuk menguji validasi wajib">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Kosongkan Form</span>
                                </button>
                                <div class="tryout-mode-pill-group">
                                    <button type="button" id="btnTryoutModeForm" class="tryout-mode-btn active" title="Tampilan Form Visual">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                                        <span>Form UI</span>
                                    </button>
                                    <button type="button" id="btnTryoutModeRaw" class="tryout-mode-btn" title="Tampilan Raw JSON Editor">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                                        <span>Raw JSON</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="tryout-body">
                            <!-- Dynamic Parameter Inputs (Path/Query) -->
                            <div id="tryoutParamsFormGroup" style="display: none; margin-bottom: 20px;">
                                <div class="tryout-section-heading">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    <span>Parameter URL / Query String</span>
                                </div>
                                <div id="tryoutParamsGrid" class="form-grid"></div>
                            </div>

                            <!-- Form UI Mode: Dynamic Request Body Form Fields -->
                            <div id="tryoutBodyFormGroup" style="display: none;">
                                <div class="tryout-section-heading">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><line x1="1" y1="3" x2="23" y2="3"></line><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                    <span>Payload Permintaan (Request Body)</span>
                                    <span id="tryoutBodyCountBadge" class="sidebar-count-badge">0</span>
                                </div>
                                <div id="tryoutBodyGrid" class="form-grid"></div>
                            </div>

                            <!-- Raw JSON Mode: Direct JSON Code Editor -->
                            <div id="tryoutRawJsonContainer" style="display: none;">
                                <div class="tryout-section-heading" style="justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                                        <span>Editor JSON Langsung</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span id="tryoutJsonStatusPill" class="res-status-tag res-status-2xx" style="font-size: 10.5px; padding: 1px 6px;">✓ JSON Valid</span>
                                        <button type="button" id="btnTryoutFormatJson" class="btn-code-copy" style="font-size: 11px; padding: 2px 8px;">
                                            <span>Format JSON</span>
                                        </button>
                                    </div>
                                </div>
                                <textarea id="tryoutRawJsonTextarea" class="tryout-raw-editor" spellcheck="false" placeholder="{\n  ...\n}"></textarea>
                            </div>

                            <!-- Collapsible 5-Header HMAC Live Security Preview -->
                            <div class="tryout-hmac-drawer">
                                <button type="button" id="btnToggleHmacPreview" class="tryout-hmac-toggle">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                        <span>Lihat 5 Header Keamanan HMAC yang Otomatis Dihitung</span>
                                    </div>
                                    <svg id="iconHmacCaret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition: transform 0.2s ease;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </button>
                                <div id="tryoutHmacDrawerContent" class="tryout-hmac-content" style="display: none;">
                                    <div class="tryout-hmac-table-wrap">
                                        <table class="hmac-preview-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 140px;">Header HTTP</th>
                                                    <th>Nilai Kalkulasi Real-Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><code>X-HC-Public</code></td>
                                                    <td><span id="previewHmacPublic" class="mono-val">-</span></td>
                                                </tr>
                                                <tr>
                                                    <td><code>X-HC-Timestamp</code></td>
                                                    <td><span id="previewHmacTimestamp" class="mono-val">-</span></td>
                                                </tr>
                                                <tr>
                                                    <td><code>X-HC-Nonce</code></td>
                                                    <td><span id="previewHmacNonce" class="mono-val">-</span></td>
                                                </tr>
                                                <tr>
                                                    <td><code>X-HC-Body-Hash</code></td>
                                                    <td><span id="previewHmacBodyHash" class="mono-val">-</span></td>
                                                </tr>
                                                <tr>
                                                    <td><code>X-HC-Signature</code></td>
                                                    <td><span id="previewHmacSignature" class="mono-val text-brand">-</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer / Execute Bar -->
                        <div class="tryout-execute-bar">
                            <div class="tryout-footer-note">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                <span>Request dieksekusi langsung ke backend HCIS dengan validasi middleware HMAC live.</span>
                            </div>
                            <div class="tryout-footer-actions">
                                <button type="button" id="btnTryoutCopyCurl" class="btn-copy-curl-soft" title="Salin cURL dengan nilai form saat ini">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                    <span>Salin cURL Form Ini</span>
                                </button>
                                <button type="button" id="btnExecuteRequest" class="btn-execute">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                    <span>Kirim Permintaan</span>
                                    <span id="executeSpinner" class="spinner" style="display: none;"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Server Response Frame: Single Unified Premium Card -->
                    <div id="liveResponseFrame" class="live-response-container" style="display: none; margin-top: 24px;">
                        
                        <!-- Top Status Banner -->
                        <div class="live-res-header">
                            <div class="live-res-header-left">
                                <div class="live-res-status-group">
                                    <span class="live-pulse-dot"></span>
                                    <span class="live-res-label">RESPON SERVER</span>
                                    <span id="liveStatusBadge" class="live-status-pill pill-2xx">200 OK</span>
                                </div>
                                <div class="live-res-metrics">
                                    <span id="liveLatencyTag" class="live-metric-pill" title="Waktu Latensi Server">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <span class="metric-val">84 ms</span>
                                    </span>
                                    <span id="liveSizeTag" class="live-metric-pill" title="Ukuran Payload">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                                        <span class="metric-val">250 B</span>
                                    </span>
                                    <span id="liveTimestampTag" class="live-metric-pill live-metric-time" title="Waktu Eksekusi">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <span class="metric-val">Just now</span>
                                    </span>
                                </div>
                            </div>

                            <div class="live-res-header-actions">
                                <button type="button" class="btn-copy-live-res" id="btnCopyLiveResponse">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <span id="btnCopyLiveResponseLabel">Salin Respon</span>
                                </button>
                            </div>
                        </div>

                        <!-- Terminal Code Box -->
                        <div class="live-terminal-box">
                            <!-- Interactive Toolbar -->
                            <div class="live-terminal-toolbar">
                                <div class="terminal-tool-left">
                                    <button type="button" class="res-tool-btn" id="liveBtnResExpandAll" title="Buka seluruh node">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M9 3v18M15 3v18M3 9h18M3 15h18"></path></svg>
                                        <span>Buka semua</span>
                                    </button>
                                    <button type="button" class="res-tool-btn" id="liveBtnResCollapseAll" title="Tutup semua node">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                        <span>Tutup semua</span>
                                    </button>
                                    <div class="res-depth-group">
                                        <button type="button" class="res-depth-btn live-depth-btn" data-depth="1" title="Buka level 1">1</button>
                                        <button type="button" class="res-depth-btn live-depth-btn" data-depth="2" title="Buka level 2">2</button>
                                        <button type="button" class="res-depth-btn live-depth-btn" data-depth="3" title="Buka level 3">3</button>
                                    </div>
                                </div>

                                <div class="terminal-tool-right">
                                    <div class="res-search-input-wrap">
                                        <span class="res-search-icon">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                        </span>
                                        <input type="text" id="liveInputResFilter" class="res-search-mini" placeholder="Cari respon..." autocomplete="off">
                                    </div>
                                    <button type="button" class="res-tool-btn" id="liveBtnResWrap" title="Bungkus baris">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 10 4 15 9 20"></polyline><path d="M20 4v7a4 4 0 0 1-4 4H4"></path></svg>
                                        <span>Bungkus</span>
                                    </button>
                                    <span class="res-stats-text" id="liveResStatsText">0 baris · 0 B</span>
                                </div>
                            </div>

                            <!-- Foldable Code Scroll with auto-fit height (min 80px, max 500px) -->
                            <div class="live-code-scroll" id="liveResCodeScroll">
                                <div class="res-code-viewer" id="liveResCodeViewer"></div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </section>

    </div>

    <!-- TAB 2: DOKUMENTASI LENGKAP VIEW -->
    <div id="tabContentDocumentation" class="docs-full-container" style="display: none;">
        <div id="docMarkdownRenderer" class="doc-card">
            <!-- Di-render dinamis dari spec.info.description -->
        </div>
    </div>

</main>

<div id="toastContainer" class="toast-container"></div>

<script>
(function () {
    'use strict';

    var BODY_HASH_KOSONG = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    // State Variables
    var apiPublic = '';
    var apiSecret = '';
    var openApiSpec = null;
    var currentEndpointKey = null; // format "POST /api/v1/..."
    var currentActiveTab = 'endpoints'; // 'endpoints' | 'docs'
    var currentActiveSubtab = 'docs'; // 'docs' | 'tryout'
    var sidebarItems = []; // list of parsed endpoints

    // DOM Elements
    var elViewGate = document.getElementById('viewGate');
    var elViewDocsMain = document.getElementById('viewDocsMain');
    var elTopNavTabs = document.getElementById('topNavTabs');
    var elBtnNavEndpoints = document.getElementById('btnNavEndpoints');
    var elBtnNavDocs = document.getElementById('btnNavDocumentation');
    var elNavEndpointCount = document.getElementById('navEndpointCount');
    var elKeyInfoPill = document.getElementById('keyInfoPill');
    var elLabelKeyCode = document.getElementById('labelKeyCode');
    var elLabelKeyMasked = document.getElementById('labelKeyMasked');
    var elBtnGantiKunci = document.getElementById('btnGantiKunci');

    var elInputApiPublic = document.getElementById('inputApiPublic');
    var elInputApiSecret = document.getElementById('inputApiSecret');
    var elBtnSubmitGate = document.getElementById('btnSubmitGate');
    var elBtnGateLabel = document.getElementById('btnGateLabel');
    var elBtnGateSpinner = document.getElementById('btnGateSpinner');
    var elGateErrorBanner = document.getElementById('gateErrorBanner');
    var elGateErrorText = document.getElementById('gateErrorText');

    var elSidebarAccordionList = document.getElementById('sidebarAccordionList');
    var elSidebarSearchInput = document.getElementById('sidebarSearchInput');
    var elBtnSearchClear = document.getElementById('btnSearchClear');
    var elSidebarTotalCount = document.getElementById('sidebarTotalCount');

    var elTabContentEndpoints = document.getElementById('tabContentEndpoints');
    var elTabContentDocs = document.getElementById('tabContentDocumentation');
    var elDocMarkdownRenderer = document.getElementById('docMarkdownRenderer');

    var elWorkspaceEmptyState = document.getElementById('workspaceEmptyState');
    var elWorkspaceDetailCard = document.getElementById('workspaceDetailCard');

    var elDetailMethodBadge = document.getElementById('detailMethodBadge');
    var elDetailPathText = document.getElementById('detailPathText');
    var elDetailSummaryText = document.getElementById('detailSummaryText');
    var elDetailProtocolChips = document.getElementById('detailProtocolChips');
    var elBtnCopyEndpointUrl = document.getElementById('btnCopyEndpointUrl');
    var elBtnCopyCurl = document.getElementById('btnCopyCurl');

    var elSubtabBtnDesc = document.getElementById('subtabBtnDesc');
    var elSubtabBtnParams = document.getElementById('subtabBtnParams');
    var elSubtabBtnCode = document.getElementById('subtabBtnCode');
    var elSubtabBtnTryout = document.getElementById('subtabBtnTryout');
    var elSubtabContentDesc = document.getElementById('subtabContentDesc');
    var elSubtabContentParams = document.getElementById('subtabContentParams');
    var elSubtabContentCode = document.getElementById('subtabContentCode');
    var elSubtabContentTryout = document.getElementById('subtabContentTryout');

    var elEpCodeLanguageButtons = document.getElementById('epCodeLanguageButtons');
    var elEpCodeActiveLangLabel = document.getElementById('epCodeActiveLangLabel');
    var elEpCodeSnippetDisplay = document.getElementById('epCodeSnippetDisplay');
    var elBtnCopyEpCodeSnippet = document.getElementById('btnCopyEpCodeSnippet');
    var elBtnCopyEpCodeSnippetLabel = document.getElementById('btnCopyEpCodeSnippetLabel');
    var activeEpCodeLang = 'php_curl';
    var currentSelectedEndpoint = null;

    var elDetailVisualStepper = document.getElementById('detailVisualStepper');
    var elDetailHighlightsGrid = document.getElementById('detailHighlightsGrid');
    var elDetailDescriptionBox = document.getElementById('detailDescriptionBox');
    var elDetailParametersSection = document.getElementById('detailParametersSection');
    var elDetailParametersList = document.getElementById('detailParametersList');
    var elDetailParamsCountBadge = document.getElementById('detailParamsCountBadge');
    var elDetailRequestBodySection = document.getElementById('detailRequestBodySection');
    var elDetailBodyFieldsList = document.getElementById('detailBodyFieldsList');
    var elDetailBodyCountBadge = document.getElementById('detailBodyCountBadge');
    var elDetailRequestBodyCode = document.getElementById('detailRequestBodyCode');
    var elDetailBodyLineCount = document.getElementById('detailBodyLineCount');
    var elBtnCopyRequestBodyExample = document.getElementById('btnCopyRequestBodyExample');
    var elDetailNoParamsNotice = document.getElementById('detailNoParamsNotice');
    
    // Response Viewer DOM Elements
    var elResViewerCard = document.getElementById('resViewerCard');
    var elBtnToggleResCollapse = document.getElementById('btnToggleResCollapse');
    var elResTotalCountBadge = document.getElementById('resTotalCountBadge');
    var elBtnToggleResText = document.getElementById('btnToggleResText');
    var elResViewerContent = document.getElementById('resViewerContent');
    var elResPillsRow = document.getElementById('resPillsRow');
    var elResActiveDesc = document.getElementById('resActiveDesc');
    var elResDarkCard = document.getElementById('resDarkCard');
    var elResDarkTitle = document.getElementById('resDarkTitle');
    var elBtnCopyResponseJson = document.getElementById('btnCopyResponseJson');
    var elBtnCopyResponseJsonLabel = document.getElementById('btnCopyResponseJsonLabel');
    var elBtnResExpandAll = document.getElementById('btnResExpandAll');
    var elBtnResCollapseAll = document.getElementById('btnResCollapseAll');
    var elInputResFilter = document.getElementById('inputResFilter');
    var elBtnResWrap = document.getElementById('btnResWrap');
    var elResStatsText = document.getElementById('resStatsText');
    var elResCodeScroll = document.getElementById('resCodeScroll');
    var elResCodeViewer = document.getElementById('resCodeViewer');

    // Tryout DOM Elements
    var elTryoutAuthNotice = document.getElementById('tryoutAuthNotice');
    var elTryoutInlineSecretInput = document.getElementById('tryoutInlineSecretInput');
    var elBtnTryoutToggleSecretVisibility = document.getElementById('btnTryoutToggleSecretVisibility');
    var elBtnTryoutSaveSecret = document.getElementById('btnTryoutSaveSecret');
    var elTryoutHeaderMethodPill = document.getElementById('tryoutHeaderMethodPill');
    var elTryoutHeaderPathText = document.getElementById('tryoutHeaderPathText');
    var elBtnTryoutFillExample = document.getElementById('btnTryoutFillExample');
    var elBtnTryoutClearForm = document.getElementById('btnTryoutClearForm');
    var elBtnTryoutModeForm = document.getElementById('btnTryoutModeForm');
    var elBtnTryoutModeRaw = document.getElementById('btnTryoutModeRaw');
    var elTryoutParamsFormGroup = document.getElementById('tryoutParamsFormGroup');
    var elTryoutParamsGrid = document.getElementById('tryoutParamsGrid');
    var elTryoutBodyFormGroup = document.getElementById('tryoutBodyFormGroup');
    var elTryoutBodyCountBadge = document.getElementById('tryoutBodyCountBadge');
    var elTryoutBodyGrid = document.getElementById('tryoutBodyGrid');
    var elTryoutRawJsonContainer = document.getElementById('tryoutRawJsonContainer');
    var elTryoutRawJsonTextarea = document.getElementById('tryoutRawJsonTextarea');
    var elTryoutJsonStatusPill = document.getElementById('tryoutJsonStatusPill');
    var elBtnTryoutFormatJson = document.getElementById('btnTryoutFormatJson');
    var elBtnToggleHmacPreview = document.getElementById('btnToggleHmacPreview');
    var elIconHmacCaret = document.getElementById('iconHmacCaret');
    var elTryoutHmacDrawerContent = document.getElementById('tryoutHmacDrawerContent');
    var elPreviewHmacPublic = document.getElementById('previewHmacPublic');
    var elPreviewHmacTimestamp = document.getElementById('previewHmacTimestamp');
    var elPreviewHmacNonce = document.getElementById('previewHmacNonce');
    var elPreviewHmacBodyHash = document.getElementById('previewHmacBodyHash');
    var elPreviewHmacSignature = document.getElementById('previewHmacSignature');
    var elBtnTryoutCopyCurl = document.getElementById('btnTryoutCopyCurl');
    var elBtnExecuteRequest = document.getElementById('btnExecuteRequest');
    var elExecuteSpinner = document.getElementById('executeSpinner');

    var elLiveResponseFrame = document.getElementById('liveResponseFrame');
    var elLiveStatusBadge = document.getElementById('liveStatusBadge');
    var elLiveLatencyTag = document.getElementById('liveLatencyTag');
    var elLiveSizeTag = document.getElementById('liveSizeTag');
    var elLiveTimestampTag = document.getElementById('liveTimestampTag');
    var elBtnCopyLiveResponse = document.getElementById('btnCopyLiveResponse');
    var elBtnCopyLiveResponseLabel = document.getElementById('btnCopyLiveResponseLabel');
    var elLiveBtnResExpandAll = document.getElementById('liveBtnResExpandAll');
    var elLiveBtnResCollapseAll = document.getElementById('liveBtnResCollapseAll');
    var elLiveInputResFilter = document.getElementById('liveInputResFilter');
    var elLiveBtnResWrap = document.getElementById('liveBtnResWrap');
    var elLiveResStatsText = document.getElementById('liveResStatsText');
    var elLiveResCodeScroll = document.getElementById('liveResCodeScroll');
    var elLiveResCodeViewer = document.getElementById('liveResCodeViewer');

    // Response Viewer State (Contoh Jawaban)
    var activeResponseVariants = [];
    var activeVariantIndex = 0;
    var activeJsonTreeLines = [];
    var isResponseCodeWrapped = false;
    var responseSearchFilter = '';

    // Live Response Viewer State (Respon Aktual)
    var liveJsonTreeLines = [];
    var isLiveCodeWrapped = false;
    var liveSearchFilter = '';
    var liveRawResponseString = '';

    // Tryout State
    var tryoutActiveMode = 'form'; // 'form' | 'raw'
    var isHmacDrawerExpanded = false;

    // ── Helper Utilities ──────────────────────────────────────────────
    function resolveSchema(rawSchema) {
        if (!rawSchema) return null;
        if (rawSchema.$ref && openApiSpec) {
            var ref = String(rawSchema.$ref);
            if (ref.startsWith('#/')) {
                var parts = ref.substring(2).split('/');
                var curr = openApiSpec;
                for (var i = 0; i < parts.length; i++) {
                    if (curr && curr[parts[i]] !== undefined) {
                        curr = curr[parts[i]];
                    } else {
                        return rawSchema;
                    }
                }
                return resolveSchema(curr);
            }
        }
        return rawSchema;
    }

    function extractRequestBodyInfo(reqBody) {
        if (!reqBody || !reqBody.content) return null;
        var jsonContent = reqBody.content['application/json'];
        if (!jsonContent) return null;

        var rawSchema = jsonContent.schema;
        var schema = resolveSchema(rawSchema) || {};
        
        var exampleObj = jsonContent.example || schema.example || null;
        
        if (!exampleObj && schema.properties) {
            exampleObj = {};
            for (var k in schema.properties) {
                var p = resolveSchema(schema.properties[k]);
                if (p && p.example !== undefined) {
                    exampleObj[k] = p.example;
                } else if (p && p.default !== undefined) {
                    exampleObj[k] = p.default;
                } else if (p && p.type === 'string') {
                    exampleObj[k] = '';
                } else if (p && (p.type === 'number' || p.type === 'integer')) {
                    exampleObj[k] = 0;
                } else if (p && p.type === 'boolean') {
                    exampleObj[k] = true;
                } else {
                    exampleObj[k] = null;
                }
            }
        }

        return {
            description: reqBody.description || schema.description || '',
            required: reqBody.required === true,
            schema: schema,
            exampleObj: exampleObj || {}
        };
    }

    function showToast(msg) {
        var container = document.getElementById('toastContainer');
        var toast = document.createElement('div');
        toast.className = 'toast-msg';
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJsString(str) {
        return String(str || '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '&quot;')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r');
    }

    function copySnippetText(text, successMsg) {
        if (!text && text !== '0') return;
        navigator.clipboard.writeText(String(text)).then(function () {
            showToast(successMsg || 'Berhasil disalin ke clipboard!');
        }).catch(function () {
            showToast('Gagal menyalin text.');
        });
    }
    window.copySnippetText = copySnippetText;

    function acakHex(panjang) {
        var byte = new Uint8Array(Math.ceil(panjang / 2));
        window.crypto.getRandomValues(byte);
        return Array.from(byte, function (b) { return b.toString(16).padStart(2, '0'); }).join('').slice(0, panjang);
    }

    async function sha256(teks) {
        var enc = new TextEncoder().encode(teks);
        var hash = await window.crypto.subtle.digest('SHA-256', enc);
        return Array.from(new Uint8Array(hash)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }

    async function hmacSha512(secret, message) {
        var enc = new TextEncoder();
        var keyData = enc.encode(secret);
        var msgData = enc.encode(message);
        var cryptoKey = await window.crypto.subtle.importKey(
            'raw', keyData, { name: 'HMAC', hash: { name: 'SHA-512' } }, false, ['sign']
        );
        var signature = await window.crypto.subtle.sign('HMAC', cryptoKey, msgData);
        return Array.from(new Uint8Array(signature)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }

    function highlightJson(data) {
        var json = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        if (!json) return '';
        var escaped = escapeHtml(json);
        return escaped.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'json-key';
                } else {
                    cls = 'json-string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'json-boolean';
            } else if (/null/.test(match)) {
                cls = 'json-null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        if (bytes < 1024) return bytes + ' B';
        return (bytes / 1024).toFixed(1) + ' KB';
    }

    function buildJsonTree(val) {
        var lines = [];
        var nodeIdCounter = 0;

        function build(item, key, indent, depth, parentNodeId, isLast) {
            var comma = isLast ? '' : ',';
            var keyHtml = key !== null ? '<span class="json-key">"' + escapeHtml(key) + '"</span>: ' : '';
            var rawKey = key !== null ? '"' + key + '": ' : '';
            var indentHtml = indent ? '<span class="json-indent">' + escapeHtml(indent) + '</span>' : '';

            if (item === null) {
                lines.push({
                    type: 'primitive',
                    depth: depth,
                    parentNodeId: parentNodeId,
                    html: indentHtml + keyHtml + '<span class="json-null">null</span>' + comma,
                    raw: indent + rawKey + 'null' + comma
                });
            } else if (typeof item === 'boolean') {
                lines.push({
                    type: 'primitive',
                    depth: depth,
                    parentNodeId: parentNodeId,
                    html: indentHtml + keyHtml + '<span class="json-boolean">' + item + '</span>' + comma,
                    raw: indent + rawKey + item + comma
                });
            } else if (typeof item === 'number') {
                lines.push({
                    type: 'primitive',
                    depth: depth,
                    parentNodeId: parentNodeId,
                    html: indentHtml + keyHtml + '<span class="json-number">' + item + '</span>' + comma,
                    raw: indent + rawKey + item + comma
                });
            } else if (typeof item === 'string') {
                lines.push({
                    type: 'primitive',
                    depth: depth,
                    parentNodeId: parentNodeId,
                    html: indentHtml + keyHtml + '<span class="json-string">"' + escapeHtml(item) + '"</span>' + comma,
                    raw: indent + rawKey + '"' + item + '"' + comma
                });
            } else if (Array.isArray(item)) {
                if (item.length === 0) {
                    lines.push({
                        type: 'primitive',
                        depth: depth,
                        parentNodeId: parentNodeId,
                        html: indentHtml + keyHtml + '<span class="json-bracket">[]</span>' + comma,
                        raw: indent + rawKey + '[]' + comma
                    });
                } else {
                    var currentId = 'n_' + (++nodeIdCounter);
                    lines.push({
                        type: 'opener',
                        nodeId: currentId,
                        bracketType: 'array',
                        count: item.length,
                        depth: depth,
                        parentNodeId: parentNodeId,
                        collapsed: false,
                        comma: comma,
                        keyHtml: keyHtml,
                        rawKey: rawKey,
                        indent: indent,
                        indentHtml: indentHtml,
                        html: indentHtml + keyHtml + '<span class="json-bracket">[</span>',
                        raw: indent + rawKey + '['
                    });
                    for (var i = 0; i < item.length; i++) {
                        build(item[i], null, indent + '  ', depth + 1, currentId, i === item.length - 1);
                    }
                    lines.push({
                        type: 'closer',
                        forNodeId: currentId,
                        depth: depth,
                        parentNodeId: parentNodeId,
                        html: indentHtml + '<span class="json-bracket">]</span>' + comma,
                        raw: indent + ']' + comma
                    });
                }
            } else if (typeof item === 'object') {
                var keys = Object.keys(item);
                if (keys.length === 0) {
                    lines.push({
                        type: 'primitive',
                        depth: depth,
                        parentNodeId: parentNodeId,
                        html: indentHtml + keyHtml + '<span class="json-bracket">{}</span>' + comma,
                        raw: indent + rawKey + '{}' + comma
                    });
                } else {
                    var currentId = 'n_' + (++nodeIdCounter);
                    lines.push({
                        type: 'opener',
                        nodeId: currentId,
                        bracketType: 'object',
                        count: keys.length,
                        depth: depth,
                        parentNodeId: parentNodeId,
                        collapsed: false,
                        comma: comma,
                        keyHtml: keyHtml,
                        rawKey: rawKey,
                        indent: indent,
                        indentHtml: indentHtml,
                        html: indentHtml + keyHtml + '<span class="json-bracket">{</span>',
                        raw: indent + rawKey + '{'
                    });
                    for (var j = 0; j < keys.length; j++) {
                        var k = keys[j];
                        build(item[k], k, indent + '  ', depth + 1, currentId, j === keys.length - 1);
                    }
                    lines.push({
                        type: 'closer',
                        forNodeId: currentId,
                        depth: depth,
                        parentNodeId: parentNodeId,
                        html: indentHtml + '<span class="json-bracket">}</span>' + comma,
                        raw: indent + '}' + comma
                    });
                }
            }
        }

        build(val, null, '', 0, null, true);
        return lines;
    }

    function renderTreeDOM() {
        if (!elResCodeViewer) return;
        elResCodeViewer.innerHTML = '';
        var collapsedSet = {};
        var q = (responseSearchFilter || '').trim().toLowerCase();

        activeJsonTreeLines.forEach(function (line, idx) {
            if (line.type === 'opener' && line.collapsed) {
                collapsedSet[line.nodeId] = true;
            }

            var isHidden = false;
            if (line.parentNodeId && collapsedSet[line.parentNodeId]) {
                isHidden = true;
                if (line.nodeId) { collapsedSet[line.nodeId] = true; }
            }
            if (line.type === 'closer' && collapsedSet[line.forNodeId]) {
                isHidden = true;
            }

            var lineDiv = document.createElement('div');
            lineDiv.className = 'res-code-line' + (isHidden ? ' is-hidden' : '');

            var isMatch = false;
            if (q && !isHidden && line.raw.toLowerCase().indexOf(q) !== -1) {
                isMatch = true;
                lineDiv.classList.add('is-match-highlight');
            }

            var gutter = document.createElement('div');
            gutter.className = 'res-line-gutter';

            var numSpan = document.createElement('span');
            numSpan.className = 'res-line-num';
            numSpan.textContent = idx + 1;
            gutter.appendChild(numSpan);

            if (line.type === 'opener') {
                var caretBtn = document.createElement('button');
                caretBtn.type = 'button';
                caretBtn.className = 'res-fold-caret' + (line.collapsed ? ' collapsed' : '');
                caretBtn.textContent = line.collapsed ? '▸' : '▾';
                caretBtn.title = line.collapsed ? 'Buka node' : 'Tutup node';
                caretBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    line.collapsed = !line.collapsed;
                    renderTreeDOM();
                });
                gutter.appendChild(caretBtn);
            } else {
                var placeholder = document.createElement('span');
                placeholder.className = 'res-fold-caret-placeholder';
                gutter.appendChild(placeholder);
            }

            var textSpan = document.createElement('span');
            textSpan.className = 'res-line-text';

            if (line.type === 'opener' && line.collapsed) {
                var openBracket = line.bracketType === 'array' ? '[' : '{';
                var closeBracket = line.bracketType === 'array' ? ']' : '}';
                var labelText = line.bracketType === 'array' ? (line.count + ' items') : (line.count + ' keys');
                textSpan.innerHTML = (line.indentHtml || '') + line.keyHtml + '<span class="json-bracket">' + openBracket + '</span>' +
                    '<span class="res-collapsed-ellipsis" data-node="' + line.nodeId + '"> ' + labelText + ' </span>' +
                    '<span class="json-bracket">' + closeBracket + '</span>' + line.comma;

                var ellipsis = textSpan.querySelector('.res-collapsed-ellipsis');
                if (ellipsis) {
                    ellipsis.addEventListener('click', function (e) {
                        e.stopPropagation();
                        line.collapsed = false;
                        renderTreeDOM();
                    });
                }
            } else {
                textSpan.innerHTML = line.html;
            }

            lineDiv.appendChild(gutter);
            lineDiv.appendChild(textSpan);
            elResCodeViewer.appendChild(lineDiv);
        });
    }

    function generateResponseVariants(ep) {
        var list = [];
        var responses = ep.responses || {};

        // 1. Success 200 or 201 from OpenAPI
        if (responses['200'] || responses['201']) {
            var code = responses['200'] ? '200' : '201';
            var r = responses[code];
            var example = null;
            if (r.content && r.content['application/json']) {
                var jsonContent = r.content['application/json'];
                var resSchema = resolveSchema(jsonContent.schema);
                example = jsonContent.example || (resSchema ? resSchema.example : null);
            }
            if (!example) {
                example = { sukses: true, message: r.description || "Permintaan berhasil diproses.", result: {} };
            }
            list.push({
                code: code,
                title: code + (code === '200' ? ' OK' : ' Created'),
                pillClass: 'res-pill-2xx',
                description: r.description || 'Permintaan berhasil diproses dan data dikembalikan sesuai kontrak.',
                example: example
            });
        }

        // 2. 200 OK · kosong (untuk GET / list)
        if (ep.method === 'GET' || (ep.responses && ep.responses['200_empty'])) {
            list.push({
                code: '200',
                title: '200 OK · kosong',
                pillClass: 'res-pill-2xx',
                description: 'Daftar berhasil diambil. Daftar kosong juga memakai kode ini — akun sah, isinya yang tidak ada.',
                example: {
                    sukses: true,
                    message: "Daftar data kosong.",
                    result: []
                }
            });
        }

        // 3. 401 Unauthorized · tanda tangan
        list.push({
            code: '401',
            title: '401 Unauthorized · tanda tangan',
            pillClass: 'res-pill-4xx',
            description: 'Tanda tangan HMAC-SHA512 tidak cocok, jam client berselisih >60 detik, atau nonce telah digunakan.',
            example: {
                sukses: false,
                message: "Tanda tangan HMAC-SHA512 tidak valid atau timestamp telah kedaluwarsa.",
                result: null
            }
        });

        // 4. 401 Unauthorized · aplikasi
        list.push({
            code: '401',
            title: '401 Unauthorized · aplikasi',
            pillClass: 'res-pill-4xx',
            description: 'Api_Public tidak terdaftar di sistem HCIS atau status aplikasi sedang dinonaktifkan.',
            example: {
                sukses: false,
                message: "Kunci publik tidak dikenal. Masukkan Api_Public yang diberikan tim HCIS.",
                result: null
            }
        });

        // 5. 401 Unauthorized (umum)
        list.push({
            code: '401',
            title: '401 Unauthorized',
            pillClass: 'res-pill-4xx',
            description: 'Satu atau lebih dari 5 Header Keamanan Wajib (X-HC-Public, X-HC-Timestamp, X-HC-Nonce, X-HC-Body-Hash, X-HC-Signature) tidak disertakan.',
            example: {
                sukses: false,
                message: "Header keamanan wajib tidak lengkap.",
                result: null
            }
        });

        // 6. 403 Forbidden
        list.push({
            code: '403',
            title: '403 Forbidden',
            pillClass: 'res-pill-4xx',
            description: 'Aplikasi Anda tidak memiliki izin untuk memanggil endpoint ini (tidak diotorisasi di registry).',
            example: {
                sukses: false,
                message: "Aplikasi tidak memiliki hak akses ke endpoint ini.",
                result: null
            }
        });

        // 7. 404 Not Found (jika ada param ID / path / batal)
        if (ep.path.includes('{') || ep.path.includes('batal') || responses['404']) {
            list.push({
                code: '404',
                title: '404 Not Found',
                pillClass: 'res-pill-4xx',
                description: 'Nomor transaksi/identitas tidak ditemukan di database HCIS, atau milik aplikasi mitra lain (terisolasi).',
                example: {
                    sukses: false,
                    message: "Data transaksi tidak ditemukan.",
                    result: null
                }
            });
        }

        // 8. 422 Unprocessable Entity (jika POST / PUT / PATCH)
        if (ep.method !== 'GET' || responses['422']) {
            var errFields = {};
            var reqInfo = extractRequestBodyInfo(ep.requestBody);
            if (reqInfo && reqInfo.schema && reqInfo.schema.properties) {
                var props = reqInfo.schema.properties;
                var reqs = reqInfo.schema.required || Object.keys(props).slice(0, 2);
                reqs.slice(0, 2).forEach(function (k) {
                    errFields[k] = ['Kolom ' + k + ' wajib diisi.'];
                });
            }
            if (Object.keys(errFields).length === 0) {
                errFields['parameter'] = ['Nilai parameter tidak valid.'];
            }
            list.push({
                code: '422',
                title: '422 Unprocessable Entity',
                pillClass: 'res-pill-4xx',
                description: 'Payload permintaan gagal melewati aturan validasi bisnis atau field wajib tidak diisi.',
                example: {
                    sukses: false,
                    message: "Data yang dikirimkan tidak valid.",
                    errors: errFields,
                    result: null
                }
            });
        }

        // 9. 429 Too Many Requests
        list.push({
            code: '429',
            title: '429 Too Many Requests',
            pillClass: 'res-pill-4xx',
            description: 'Batas kuota pemanggilan per menit telah terlampaui. Terapkan backoff sebelum mencoba kembali.',
            example: {
                sukses: false,
                message: "Terlalu banyak permintaan. Silakan tunggu beberapa saat.",
                result: null
            }
        });

        // 10. 500 Server Error
        list.push({
            code: '500',
            title: '500 Server Error',
            pillClass: 'res-pill-5xx',
            description: 'Terjadi kesalahan tidak terduga pada server backend HCIS saat memproses transaksi.',
            example: {
                sukses: false,
                message: "Terjadi kesalahan internal server. Hubungi tim integrasi HCIS jika kendala berlanjut.",
                result: null
            }
        });

        return list;
    }

    function selectResponseVariant(idx) {
        activeVariantIndex = idx;
        var v = activeResponseVariants[idx];
        if (!v) return;

        if (elResPillsRow) {
            elResPillsRow.querySelectorAll('.res-pill').forEach(function (pill, pIdx) {
                pill.classList.toggle('active', pIdx === idx);
            });
        }

        if (elResActiveDesc) {
            elResActiveDesc.textContent = v.description;
        }

        if (elResDarkTitle) {
            elResDarkTitle.textContent = 'Contoh jawaban · ' + v.code;
        }

        activeJsonTreeLines = buildJsonTree(v.example);

        var rawPretty = JSON.stringify(v.example, null, 2);
        var lineCount = rawPretty.split('\n').length;
        var byteSize = new TextEncoder().encode(rawPretty).length;
        if (elResStatsText) {
            elResStatsText.textContent = lineCount + ' baris · ' + formatBytes(byteSize);
        }

        renderTreeDOM();
    }

    function renderContohResponseViewer(ep) {
        activeResponseVariants = generateResponseVariants(ep);
        activeVariantIndex = 0;
        responseSearchFilter = '';
        if (elInputResFilter) elInputResFilter.value = '';

        if (elResTotalCountBadge) {
            elResTotalCountBadge.textContent = activeResponseVariants.length + ' kode';
        }

        if (elResPillsRow) {
            elResPillsRow.innerHTML = activeResponseVariants.map(function (v, idx) {
                return '<button type="button" class="res-pill ' + v.pillClass + (idx === 0 ? ' active' : '') + '" data-idx="' + idx + '">' +
                    '<span class="res-pill-code">' + escapeHtml(v.code) + '</span>' +
                    '<span class="res-pill-name">' + escapeHtml(v.title.replace(/^[0-9]+\s*/, '')) + '</span>' +
                '</button>';
            }).join('');

            elResPillsRow.querySelectorAll('.res-pill').forEach(function (pill) {
                pill.addEventListener('click', function () {
                    var idx = parseInt(pill.getAttribute('data-idx'), 10);
                    selectResponseVariant(idx);
                });
            });
        }

        selectResponseVariant(0);
    }

    function initResponseViewerControls() {
        function toggleViewer() {
            if (!elResViewerCard) return;
            var isCollapsed = elResViewerCard.classList.toggle('is-collapsed');
            if (elBtnToggleResText) {
                elBtnToggleResText.textContent = isCollapsed ? 'Tampilkan' : 'Sembunyikan';
            }
        }

        if (elBtnToggleResCollapse) elBtnToggleResCollapse.addEventListener('click', toggleViewer);
        if (elBtnToggleResText) elBtnToggleResText.addEventListener('click', toggleViewer);

        if (elBtnCopyResponseJson) {
            elBtnCopyResponseJson.addEventListener('click', function () {
                var v = activeResponseVariants[activeVariantIndex];
                if (!v) return;
                var rawPretty = JSON.stringify(v.example, null, 2);
                navigator.clipboard.writeText(rawPretty).then(function () {
                    elBtnCopyResponseJson.classList.add('copied');
                    if (elBtnCopyResponseJsonLabel) elBtnCopyResponseJsonLabel.textContent = 'Tersalin!';
                    showToast('Contoh respons ' + v.code + ' berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyResponseJson.classList.remove('copied');
                        if (elBtnCopyResponseJsonLabel) elBtnCopyResponseJsonLabel.textContent = 'Salin';
                    }, 2000);
                });
            });
        }

        if (elBtnResExpandAll) {
            elBtnResExpandAll.addEventListener('click', function () {
                activeJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener') l.collapsed = false;
                });
                renderTreeDOM();
            });
        }

        if (elBtnResCollapseAll) {
            elBtnResCollapseAll.addEventListener('click', function () {
                activeJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener' && l.depth >= 1) l.collapsed = true;
                });
                renderTreeDOM();
            });
        }

        document.querySelectorAll('.res-depth-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var depth = parseInt(btn.getAttribute('data-depth'), 10);
                activeJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener') {
                        l.collapsed = (l.depth >= depth);
                    }
                });
                document.querySelectorAll('.res-depth-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                renderTreeDOM();
                setTimeout(function () { btn.classList.remove('active'); }, 1500);
            });
        });

        if (elInputResFilter) {
            elInputResFilter.addEventListener('input', function () {
                responseSearchFilter = elInputResFilter.value;
                renderTreeDOM();
            });
        }

        if (elBtnResWrap) {
            elBtnResWrap.addEventListener('click', function () {
                isResponseCodeWrapped = !isResponseCodeWrapped;
                if (elResCodeScroll) elResCodeScroll.classList.toggle('is-wrapped', isResponseCodeWrapped);
                elBtnResWrap.classList.toggle('active', isResponseCodeWrapped);
            });
        }
    }

    function renderLiveTreeDOM() {
        if (!elLiveResCodeViewer) return;
        elLiveResCodeViewer.innerHTML = '';
        var collapsedSet = {};
        var q = (liveSearchFilter || '').trim().toLowerCase();

        liveJsonTreeLines.forEach(function (line, idx) {
            if (line.type === 'opener' && line.collapsed) {
                collapsedSet[line.nodeId] = true;
            }

            var isHidden = false;
            if (line.parentNodeId && collapsedSet[line.parentNodeId]) {
                isHidden = true;
                if (line.nodeId) { collapsedSet[line.nodeId] = true; }
            }
            if (line.type === 'closer' && collapsedSet[line.forNodeId]) {
                isHidden = true;
            }

            var lineDiv = document.createElement('div');
            lineDiv.className = 'res-code-line' + (isHidden ? ' is-hidden' : '');

            var isMatch = false;
            if (q && !isHidden && line.raw.toLowerCase().indexOf(q) !== -1) {
                isMatch = true;
                lineDiv.classList.add('is-match-highlight');
            }

            var gutter = document.createElement('div');
            gutter.className = 'res-line-gutter';

            var numSpan = document.createElement('span');
            numSpan.className = 'res-line-num';
            numSpan.textContent = idx + 1;
            gutter.appendChild(numSpan);

            if (line.type === 'opener') {
                var caretBtn = document.createElement('button');
                caretBtn.type = 'button';
                caretBtn.className = 'res-fold-caret' + (line.collapsed ? ' collapsed' : '');
                caretBtn.textContent = line.collapsed ? '▸' : '▾';
                caretBtn.title = line.collapsed ? 'Buka node' : 'Tutup node';
                caretBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    line.collapsed = !line.collapsed;
                    renderLiveTreeDOM();
                });
                gutter.appendChild(caretBtn);
            } else {
                var placeholder = document.createElement('span');
                placeholder.className = 'res-fold-caret-placeholder';
                gutter.appendChild(placeholder);
            }

            var textSpan = document.createElement('span');
            textSpan.className = 'res-line-text';

            if (line.type === 'opener' && line.collapsed) {
                var openBracket = line.bracketType === 'array' ? '[' : '{';
                var closeBracket = line.bracketType === 'array' ? ']' : '}';
                var labelText = line.bracketType === 'array' ? (line.count + ' items') : (line.count + ' keys');
                textSpan.innerHTML = (line.indentHtml || '') + line.keyHtml + '<span class="json-bracket">' + openBracket + '</span>' +
                    '<span class="res-collapsed-ellipsis" data-node="' + line.nodeId + '"> ' + labelText + ' </span>' +
                    '<span class="json-bracket">' + closeBracket + '</span>' + line.comma;

                var ellipsis = textSpan.querySelector('.res-collapsed-ellipsis');
                if (ellipsis) {
                    ellipsis.addEventListener('click', function (e) {
                        e.stopPropagation();
                        line.collapsed = false;
                        renderLiveTreeDOM();
                    });
                }
            } else {
                textSpan.innerHTML = line.html;
            }

            lineDiv.appendChild(gutter);
            lineDiv.appendChild(textSpan);
            elLiveResCodeViewer.appendChild(lineDiv);
        });
    }

    function initLiveResponseViewerControls() {
        if (elBtnCopyLiveResponse) {
            elBtnCopyLiveResponse.addEventListener('click', function () {
                if (!liveRawResponseString) return;
                navigator.clipboard.writeText(liveRawResponseString).then(function () {
                    elBtnCopyLiveResponse.classList.add('copied');
                    if (elBtnCopyLiveResponseLabel) elBtnCopyLiveResponseLabel.textContent = 'Tersalin!';
                    showToast('Respon aktual berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyLiveResponse.classList.remove('copied');
                        if (elBtnCopyLiveResponseLabel) elBtnCopyLiveResponseLabel.textContent = 'Salin Respon';
                    }, 2000);
                });
            });
        }

        if (elLiveBtnResExpandAll) {
            elLiveBtnResExpandAll.addEventListener('click', function () {
                liveJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener') l.collapsed = false;
                });
                renderLiveTreeDOM();
            });
        }

        if (elLiveBtnResCollapseAll) {
            elLiveBtnResCollapseAll.addEventListener('click', function () {
                liveJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener' && l.depth >= 1) l.collapsed = true;
                });
                renderLiveTreeDOM();
            });
        }

        document.querySelectorAll('.live-depth-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var depth = parseInt(btn.getAttribute('data-depth'), 10);
                liveJsonTreeLines.forEach(function (l) {
                    if (l.type === 'opener') {
                        l.collapsed = (l.depth >= depth);
                    }
                });
                document.querySelectorAll('.live-depth-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                renderLiveTreeDOM();
                setTimeout(function () { btn.classList.remove('active'); }, 1500);
            });
        });

        if (elLiveInputResFilter) {
            elLiveInputResFilter.addEventListener('input', function () {
                liveSearchFilter = elLiveInputResFilter.value;
                renderLiveTreeDOM();
            });
        }

        if (elLiveBtnResWrap) {
            elLiveBtnResWrap.addEventListener('click', function () {
                isLiveCodeWrapped = !isLiveCodeWrapped;
                if (elLiveResCodeScroll) elLiveResCodeScroll.classList.toggle('is-wrapped', isLiveCodeWrapped);
                elLiveBtnResWrap.classList.toggle('active', isLiveCodeWrapped);
            });
        }
    }

    // ── Rich Text & Markdown Parser ──────────────────────────────────
    function formatRichText(text) {
        if (!text) return '';
        var escaped = escapeHtml(text);
        return escaped
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code class="desc-inline-code">$1</code>')
            .replace(/\b(GET|POST|PUT|PATCH|DELETE)\s+(\/[a-zA-Z0-9_\-\/{}\.]+)/g, '<span class="inline-method-tag $1">$1</span> <code class="desc-inline-code">$2</code>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="desc-link">$1</a>');
    }

    function renderEndpointDescription(md) {
        if (!md) return '<div class="desc-lead-box"><p>Tidak ada deskripsi rinci untuk endpoint ini.</p></div>';

        var lines = md.split('\n');
        var introParagraphs = [];
        var featureItems = [];
        var inFeatureMode = false;

        for (var i = 0; i < lines.length; i++) {
            var rawLine = lines[i].trim();
            if (!rawLine) continue;

            // Abaikan delimiter tabel atau markdown header duplikat
            if (rawLine.startsWith('|') || rawLine.startsWith('#') || rawLine === '---') {
                continue;
            }

            // Periksa apakah baris adalah item bullet (*, -, •, 1., dsb)
            var bulletMatch = rawLine.match(/^[-*•\d\.]+\s+(.*)$/);
            if (bulletMatch) {
                inFeatureMode = true;
                var text = bulletMatch[1].trim();
                // Deteksi jika bullet diawali judul tebal: **Judul**: Isi atau Judul: Isi
                var titleMatch = text.match(/^(\*\*(.*?)\*\*|\*(.*?)\*|\[(.*?)\]|([A-Za-z0-9\s&/_()-]+))\s*[:\-–]\s*(.*)$/);
                if (titleMatch) {
                    var title = titleMatch[2] || titleMatch[3] || titleMatch[4] || titleMatch[5];
                    var body = titleMatch[6];
                    featureItems.push({
                        title: title.trim(),
                        body: body.trim()
                    });
                } else {
                    featureItems.push({
                        title: '',
                        body: text
                    });
                }
            } else {
                if (!inFeatureMode) {
                    introParagraphs.push(rawLine);
                } else {
                    if (featureItems.length > 0) {
                        featureItems[featureItems.length - 1].body += ' ' + rawLine;
                    } else {
                        introParagraphs.push(rawLine);
                    }
                }
            }
        }

        var html = [];

        if (introParagraphs.length > 0) {
            html.push('<div class="desc-lead-box">');
            introParagraphs.forEach(function (p) {
                html.push('<p>' + formatRichText(p) + '</p>');
            });
            html.push('</div>');
        }

        if (featureItems.length > 0) {
            html.push('<div class="desc-feature-list">');
            featureItems.forEach(function (item) {
                html.push('<div class="desc-feature-item">');
                if (item.title) {
                    html.push('<div class="desc-feature-head"><span class="desc-feature-badge">' + escapeHtml(item.title) + '</span></div>');
                } else {
                    html.push('<span class="desc-bullet-dot"></span>');
                }
                html.push('<div class="desc-feature-body">' + formatRichText(item.body) + '</div>');
                html.push('</div>');
            });
            html.push('</div>');
        }

        return html.join('');
    }

    function renderMarkdown(md) {
        return renderEndpointDescription(md);
    }

    // ── Multi-Language Snippet Generator for Documentation Tab ────────
    function generateDocumentationSnippets() {
        var host = window.location.origin;
        if (openApiSpec && openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
            host = openApiSpec.servers[0].url.replace(/\/$/, '');
        }

        var pubKey = apiPublic || 'YOUR_API_PUBLIC';
        var secretKey = apiSecret || 'YOUR_API_SECRET';

        var samplePath = '/api/v1/hcis/visitor/permit-tamu';
        if (sidebarItems.length > 0) {
            samplePath = sidebarItems[0].path;
        }

        var phpCode = '<' + '?php\n' +
            '// 1. Kredensial & Parameter Permintaan\n' +
            '$apiPublic  = \'' + pubKey + '\';\n' +
            '$apiSecret  = \'' + secretKey + '\';\n' +
            '$host       = \'' + host + '\';\n' +
            '$method     = \'POST\';\n' +
            '$path       = \'' + samplePath + '\';\n' +
            '$query      = \'\'; // tanpa tanda tanya \'?\', string kosong jika tidak ada\n\n' +
            '$payload = [\n' +
            '    \'nama_tamu\'         => \'Budi Pratama\',\n' +
            '    \'instansi\'          => \'PT Mitra Sejahtera\',\n' +
            '    \'keperluan\'         => \'Koordinasi Integrasi Sistem\',\n' +
            '    \'tanggal_kunjungan\' => date(\'Y-m-d\'),\n' +
            '    \'referensi_mitra\'   => \'REF-\' . bin2hex(random_bytes(4))\n' +
            '];\n\n' +
            '// 2. Hitung Body Hash (SHA-256)\n' +
            '$bodyJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : \'\';\n' +
            '$bodyHash = !empty($bodyJson)\n' +
            '    ? hash(\'sha256\', $bodyJson)\n' +
            '    : \'' + BODY_HASH_KOSONG + '\';\n\n' +
            '// 3. Buat Nilai Header Keamanan\n' +
            '$timestamp = gmdate(\'Y-m-d\\TH:i:s.000\\Z\'); // Format ISO-8601 UTC\n' +
            '$nonce     = bin2hex(random_bytes(16));       // Minimal 16 karakter acak\n\n' +
            '// 4. Susun Canonical String 7 Baris\n' +
            '$canonicalString = implode("\\n", [\n' +
            '    strtoupper($method),\n' +
            '    $path,\n' +
            '    $query,\n' +
            '    $timestamp,\n' +
            '    $nonce,\n' +
            '    $bodyHash,\n' +
            '    $apiPublic\n' +
            ']);\n\n' +
            '// 5. Hitung Tanda Tangan HMAC-SHA512\n' +
            '$signature = hash_hmac(\'sha512\', $canonicalString, $apiSecret);\n\n' +
            '// 6. Kirim Request via cURL\n' +
            '$ch = curl_init($host . $path . ($query ? \'?\' . $query : \'\'));\n' +
            '$headers = [\n' +
            '    \'Accept: application/json\',\n' +
            '    \'X-HC-Public: \' . $apiPublic,\n' +
            '    \'X-HC-Timestamp: \' . $timestamp,\n' +
            '    \'X-HC-Nonce: \' . $nonce,\n' +
            '    \'X-HC-Body-Hash: \' . $bodyHash,\n' +
            '    \'X-HC-Signature: \' . $signature,\n' +
            '];\n\n' +
            'if (!empty($bodyJson)) {\n' +
            '    $headers[] = \'Content-Type: application/json\';\n' +
            '    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);\n' +
            '}\n\n' +
            'curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);\n' +
            'curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);\n' +
            'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n\n' +
            '$response = curl_exec($ch);\n' +
            '$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
            'curl_close($ch);\n\n' +
            '$result = json_decode($response, true);\n' +
            'print_r($result);';

        var nodeCode = 'const crypto = require(\'crypto\');\n\n' +
            '// 1. Kredensial & Parameter Permintaan\n' +
            'const apiPublic  = \'' + pubKey + '\';\n' +
            'const apiSecret  = \'' + secretKey + '\';\n' +
            'const host       = \'' + host + '\';\n' +
            'const method     = \'POST\';\n' +
            'const path       = \'' + samplePath + '\';\n' +
            'const query      = \'\'; // tanpa tanda tanya \'?\', string kosong jika tidak ada\n\n' +
            'const payload = {\n' +
            '  nama_tamu: \'Budi Pratama\',\n' +
            '  instansi: \'PT Mitra Sejahtera\',\n' +
            '  keperluan: \'Koordinasi Integrasi Sistem\',\n' +
            '  tanggal_kunjungan: new Date().toISOString().slice(0, 10),\n' +
            '  referensi_mitra: \'REF-\' + Date.now()\n' +
            '};\n\n' +
            '// 2. Hitung Body Hash (SHA-256)\n' +
            'const bodyJson = payload ? JSON.stringify(payload) : \'\';\n' +
            'const bodyHash = bodyJson\n' +
            '  ? crypto.createHash(\'sha256\').update(bodyJson, \'utf8\').digest(\'hex\')\n' +
            '  : \'' + BODY_HASH_KOSONG + '\';\n\n' +
            '// 3. Buat Nilai Header Keamanan\n' +
            'const timestamp = new Date().toISOString(); // ISO-8601 UTC\n' +
            'const nonce     = crypto.randomBytes(16).toString(\'hex\');\n\n' +
            '// 4. Susun Canonical String 7 Baris\n' +
            'const canonicalString = [\n' +
            '  method.toUpperCase(),\n' +
            '  path,\n' +
            '  query,\n' +
            '  timestamp,\n' +
            '  nonce,\n' +
            '  bodyHash,\n' +
            '  apiPublic\n' +
            '].join(\'\\n\');\n\n' +
            '// 5. Hitung Tanda Tangan HMAC-SHA512\n' +
            'const signature = crypto.createHmac(\'sha512\', apiSecret)\n' +
            '  .update(canonicalString, \'utf8\')\n' +
            '  .digest(\'hex\');\n\n' +
            '// 6. Kirim Request via fetch (Node.js 18+)\n' +
            'async function sendRequest() {\n' +
            '  const url = host + path + (query ? \'?\' + query : \'\');\n' +
            '  const headers = {\n' +
            '    \'Accept\': \'application/json\',\n' +
            '    \'X-HC-Public\': apiPublic,\n' +
            '    \'X-HC-Timestamp\': timestamp,\n' +
            '    \'X-HC-Nonce\': nonce,\n' +
            '    \'X-HC-Body-Hash\': bodyHash,\n' +
            '    \'X-HC-Signature\': signature\n' +
            '  };\n' +
            '  if (bodyJson) headers[\'Content-Type\'] = \'application/json\';\n\n' +
            '  const response = await fetch(url, {\n' +
            '    method: method,\n' +
            '    headers: headers,\n' +
            '    body: [\'POST\', \'PUT\', \'PATCH\'].includes(method) ? bodyJson : undefined\n' +
            '  });\n\n' +
            '  const data = await response.json();\n' +
            '  console.log(\'HTTP Status:\', response.status, data);\n' +
            '}\n\n' +
            'sendRequest();';

        var pythonCode = 'import hashlib\n' +
            'import hmac\n' +
            'import json\n' +
            'import secrets\n' +
            'from datetime import datetime, timezone\n' +
            'import requests\n\n' +
            '# 1. Kredensial & Parameter Permintaan\n' +
            'api_public  = \'' + pubKey + '\'\n' +
            'api_secret  = \'' + secretKey + '\'\n' +
            'host        = \'' + host + '\'\n' +
            'method      = \'POST\'\n' +
            'path        = \'' + samplePath + '\'\n' +
            'query       = \'\'  # tanpa tanda tanya \'?\', string kosong jika tidak ada\n\n' +
            'payload = {\n' +
            '    \'nama_tamu\': \'Budi Pratama\',\n' +
            '    \'instansi\': \'PT Mitra Sejahtera\',\n' +
            '    \'keperluan\': \'Koordinasi Integrasi Sistem\',\n' +
            '    \'tanggal_kunjungan\': datetime.now().strftime(\'%Y-%m-%d\'),\n' +
            '    \'referensi_mitra\': f\'REF-{secrets.token_hex(4)}\'\n' +
            '}\n\n' +
            '# 2. Hitung Body Hash (SHA-256)\n' +
            'body_json = json.dumps(payload, separators=(\',\', \':\')) if payload else \'\'\n' +
            'if body_json:\n' +
            '    body_hash = hashlib.sha256(body_json.encode(\'utf-8\')).hexdigest()\n' +
            'else:\n' +
            '    body_hash = \'' + BODY_HASH_KOSONG + '\'\n\n' +
            '# 3. Buat Nilai Header Keamanan\n' +
            'timestamp = datetime.now(timezone.utc).strftime(\'%Y-%m-%dT%H:%M:%S.%f\')[:-3] + \'Z\'\n' +
            'nonce     = secrets.token_hex(16)\n\n' +
            '# 4. Susun Canonical String 7 Baris\n' +
            'canonical_string = \'\\n\'.join([\n' +
            '    method.upper(),\n' +
            '    path,\n' +
            '    query,\n' +
            '    timestamp,\n' +
            '    nonce,\n' +
            '    body_hash,\n' +
            '    api_public\n' +
            '])\n\n' +
            '# 5. Hitung Tanda Tangan HMAC-SHA512\n' +
            'signature = hmac.new(\n' +
            '    api_secret.encode(\'utf-8\'),\n' +
            '    canonical_string.encode(\'utf-8\'),\n' +
            '    hashlib.sha512\n' +
            ').hexdigest()\n\n' +
            '# 6. Kirim Request\n' +
            'headers = {\n' +
            '    \'Accept\': \'application/json\',\n' +
            '    \'X-HC-Public\': api_public,\n' +
            '    \'X-HC-Timestamp\': timestamp,\n' +
            '    \'X-HC-Nonce\': nonce,\n' +
            '    \'X-HC-Body-Hash\': body_hash,\n' +
            '    \'X-HC-Signature\': signature\n' +
            '}\n' +
            'if body_json:\n' +
            '    headers[\'Content-Type\'] = \'application/json\'\n\n' +
            'url = host + path + (f\'?{query}\' if query else \'\')\n' +
            'resp = requests.request(method, url, headers=headers, data=body_json if body_json else None)\n' +
            'print(\'HTTP Status:\', resp.status_code, resp.json())';

        var curlCode = 'API_PUBLIC="' + pubKey + '"\n' +
            'API_SECRET="' + secretKey + '"\n' +
            'HOST="' + host + '"\n' +
            'METHOD="POST"\n' +
            'PATH="' + samplePath + '"\n' +
            'QUERY=""\n' +
            'TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")\n' +
            'NONCE=$(openssl rand -hex 16)\n' +
            'BODY=\'{"nama_tamu":"Budi Pratama","instansi":"PT Mitra Sejahtera","keperluan":"Koordinasi","tanggal_kunjungan":"2026-09-15"}\'\n\n' +
            '# Hitung Body Hash\n' +
            'BODY_HASH=$(echo -n "$BODY" | openssl dgst -sha256 | awk \'{print $2}\')\n\n' +
            '# Hitung Tanda Tangan HMAC-SHA512\n' +
            'CANONICAL=$(printf "%s\\n%s\\n%s\\n%s\\n%s\\n%s\\n%s" "$METHOD" "$PATH" "$QUERY" "$TIMESTAMP" "$NONCE" "$BODY_HASH" "$API_PUBLIC")\n' +
            'SIGNATURE=$(printf "%s" "$CANONICAL" | openssl dgst -sha512 -hmac "$API_SECRET" | awk \'{print $2}\')\n\n' +
            '# Eksekusi Permintaan\n' +
            'curl -X POST "$HOST$PATH" \\\n' +
            '  -H "Accept: application/json" \\\n' +
            '  -H "Content-Type: application/json" \\\n' +
            '  -H "X-HC-Public: $API_PUBLIC" \\\n' +
            '  -H "X-HC-Timestamp: $TIMESTAMP" \\\n' +
            '  -H "X-HC-Nonce: $NONCE" \\\n' +
            '  -H "X-HC-Body-Hash: $BODY_HASH" \\\n' +
            '  -H "X-HC-Signature: $SIGNATURE" \\\n' +
            '  --data-raw "$BODY"';

        return {
            php: phpCode,
            nodejs: nodeCode,
            python: pythonCode,
            curl: curlCode
        };
    }

    function phpArrayFormat(obj, depth) {
        if (obj === null) return 'null';
        if (typeof obj === 'boolean') return obj ? 'true' : 'false';
        if (typeof obj === 'number') return String(obj);
        if (typeof obj === 'string') return "'" + String(obj).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
        var pad = '  '.repeat(depth + 1);
        var endPad = '  '.repeat(depth);
        if (Array.isArray(obj)) {
            if (obj.length === 0) return '[]';
            var items = obj.map(function (item) {
                return pad + phpArrayFormat(item, depth + 1);
            });
            return '[\n' + items.join(',\n') + '\n' + endPad + ']';
        }
        if (typeof obj === 'object') {
            var keys = Object.keys(obj);
            if (keys.length === 0) return '[]';
            var entries = keys.map(function (k) {
                return pad + "'" + k.replace(/'/g, "\\'") + "' => " + phpArrayFormat(obj[k], depth + 1);
            });
            return '[\n' + entries.join(',\n') + '\n' + endPad + ']';
        }
        return "'" + String(obj) + "'";
    }

    function generateEndpointSnippets(ep) {
        var host = window.location.origin;
        if (openApiSpec && openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
            host = openApiSpec.servers[0].url.replace(/\/$/, '');
        }

        var pubKey = apiPublic || 'YOUR_API_PUBLIC';
        var secretKey = apiSecret || 'YOUR_API_SECRET';
        var method = ep.method.toUpperCase();
        var samplePath = ep.path;

        if (ep.parameters) {
            ep.parameters.forEach(function (p) {
                if (p.in === 'path') {
                    var pSchema = resolveSchema(p.schema);
                    var val = (pSchema && pSchema.example !== undefined) ? pSchema.example : (p.example !== undefined ? p.example : '1');
                    samplePath = samplePath.replace('{' + p.name + '}', encodeURIComponent(val));
                }
            });
        }

        var reqInfo = extractRequestBodyInfo(ep.requestBody);
        var examplePayload = (reqInfo && reqInfo.exampleObj && Object.keys(reqInfo.exampleObj).length > 0) ? reqInfo.exampleObj : null;

        // 1. PHP Native cURL
        var phpCurl = '<' + '?php\n' +
            '// 1. Kredensial & Parameter Endpoint\n' +
            '$apiPublic  = \'' + pubKey + '\';\n' +
            '$apiSecret  = \'' + secretKey + '\';\n' +
            '$host       = \'' + host + '\';\n' +
            '$method     = \'' + method + '\';\n' +
            '$path       = \'' + samplePath + '\';\n' +
            '$query      = \'\'; // format "k1=v1&k2=v2" tanpa \'?\', string kosong jika tidak ada\n\n';

        if (examplePayload) {
            phpCurl += '$payload = ' + phpArrayFormat(examplePayload, 0) + ';\n\n' +
                '// 2. Hitung Body Hash (SHA-256)\n' +
                '$bodyJson = json_encode($payload, JSON_UNESCAPED_SLASHES);\n' +
                '$bodyHash = hash(\'sha256\', $bodyJson);\n\n';
        } else {
            phpCurl += '// 2. Hitung Body Hash (SHA-256) - Body Kosong\n' +
                '$bodyJson = \'\';\n' +
                '$bodyHash = \'' + BODY_HASH_KOSONG + '\';\n\n';
        }

        phpCurl += '// 3. Buat Nilai Header Keamanan Wajib\n' +
            '$timestamp = gmdate(\'Y-m-d\\TH:i:s.000\\Z\'); // UTC ISO-8601\n' +
            '$nonce     = bin2hex(random_bytes(16));       // 32 hex chars\n\n' +
            '// 4. Susun Canonical String 7 Baris Presisi\n' +
            '$canonicalString = implode("\\n", [\n' +
            '    strtoupper($method),\n' +
            '    $path,\n' +
            '    $query,\n' +
            '    $timestamp,\n' +
            '    $nonce,\n' +
            '    $bodyHash,\n' +
            '    $apiPublic\n' +
            ']);\n\n' +
            '// 5. Hitung Tanda Tangan HMAC-SHA512\n' +
            '$signature = hash_hmac(\'sha512\', $canonicalString, $apiSecret);\n\n' +
            '// 6. Eksekusi Request via cURL\n' +
            '$url = $host . $path . ($query ? \'?\' . $query : \'\');\n' +
            '$ch = curl_init($url);\n' +
            '$headers = [\n' +
            '    \'Accept: application/json\',\n' +
            '    \'X-HC-Public: \' . $apiPublic,\n' +
            '    \'X-HC-Timestamp: \' . $timestamp,\n' +
            '    \'X-HC-Nonce: \' . $nonce,\n' +
            '    \'X-HC-Body-Hash: \' . $bodyHash,\n' +
            '    \'X-HC-Signature: \' . $signature,\n' +
            '];\n' +
            (examplePayload ? 'if (!empty($bodyJson)) {\n    $headers[] = \'Content-Type: application/json\';\n    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);\n}\n' : '') +
            'curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);\n' +
            'curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);\n' +
            'curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);\n' +
            'curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);\n\n' +
            '$response = curl_exec($ch);\n' +
            '$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n' +
            'curl_close($ch);\n\n' +
            '$result = json_decode($response, true);\n' +
            'echo "HTTP Status: " . $httpCode . "\\n";\n' +
            'print_r($result);';

        // 2. PHP Laravel Http Client
        var phpGuzzle = '<' + '?php\n' +
            'namespace App\\Services;\n\n' +
            'use Illuminate\\Support\\Facades\\Http;\n' +
            'use Illuminate\\Support\\Str;\n\n' +
            '// 1. Kredensial & Parameter Endpoint\n' +
            '$apiPublic  = config(\'services.hcis.public_key\', \'' + pubKey + '\');\n' +
            '$apiSecret  = config(\'services.hcis.secret_key\', \'' + secretKey + '\');\n' +
            '$host       = config(\'services.hcis.base_url\', \'' + host + '\');\n' +
            '$method     = \'' + method + '\';\n' +
            '$path       = \'' + samplePath + '\';\n' +
            '$query      = \'\';\n\n';

        if (examplePayload) {
            phpGuzzle += '$payload = ' + phpArrayFormat(examplePayload, 0) + ';\n' +
                '$bodyJson = json_encode($payload, JSON_UNESCAPED_SLASHES);\n' +
                '$bodyHash = hash(\'sha256\', $bodyJson);\n\n';
        } else {
            phpGuzzle += '$bodyJson = \'\';\n' +
                '$bodyHash = \'' + BODY_HASH_KOSONG + '\';\n\n';
        }

        phpGuzzle += '$timestamp = now()->setTimezone(\'UTC\')->format(\'Y-m-d\\TH:i:s.000\\Z\');\n' +
            '$nonce     = Str::random(32);\n\n' +
            '$canonical = implode("\\n", [\n' +
            '    strtoupper($method),\n' +
            '    $path,\n' +
            '    $query,\n' +
            '    $timestamp,\n' +
            '    $nonce,\n' +
            '    $bodyHash,\n' +
            '    $apiPublic\n' +
            ']);\n\n' +
            '$signature = hash_hmac(\'sha512\', $canonical, $apiSecret);\n\n' +
            '$response = Http::withHeaders([\n' +
            '    \'Accept\'          => \'application/json\',\n' +
            '    \'Content-Type\'    => \'application/json\',\n' +
            '    \'X-HC-Public\'     => $apiPublic,\n' +
            '    \'X-HC-Timestamp\'  => $timestamp,\n' +
            '    \'X-HC-Nonce\'      => $nonce,\n' +
            '    \'X-HC-Body-Hash\'  => $bodyHash,\n' +
            '    \'X-HC-Signature\'  => $signature,\n' +
            '])->send($method, $host . $path . ($query ? \'?\' . $query : \'\')' + (examplePayload ? ', [\'body\' => $bodyJson]' : '') + ');\n\n' +
            'if ($response->successful()) {\n' +
            '    $data = $response->json();\n' +
            '    // Proses data respon di sini\n' +
            '} else {\n' +
            '    logger()->error(\'HCIS API Error: \' . $response->body());\n' +
            '}';

        // 3. JavaScript Fetch
        var jsFetch = 'const crypto = require(\'crypto\');\n\n' +
            '// 1. Kredensial & Parameter Endpoint\n' +
            'const API_PUBLIC  = \'' + pubKey + '\';\n' +
            'const API_SECRET  = \'' + secretKey + '\';\n' +
            'const BASE_URL    = \'' + host + '\';\n' +
            'const METHOD      = \'' + method + '\';\n' +
            'const PATH        = \'' + samplePath + '\';\n' +
            'const QUERY       = \'\';\n\n';

        if (examplePayload) {
            jsFetch += 'const payload = ' + JSON.stringify(examplePayload, null, 2) + ';\n\n' +
                'const bodyJson = JSON.stringify(payload);\n' +
                'const bodyHash = crypto.createHash(\'sha256\').update(bodyJson, \'utf8\').digest(\'hex\');\n\n';
        } else {
            jsFetch += 'const bodyJson = \'\';\n' +
                'const bodyHash = \'' + BODY_HASH_KOSONG + '\';\n\n';
        }

        jsFetch += 'const timestamp = new Date().toISOString();\n' +
            'const nonce     = crypto.randomBytes(16).toString(\'hex\');\n\n' +
            'const canonicalString = [\n' +
            '  METHOD,\n' +
            '  PATH,\n' +
            '  QUERY,\n' +
            '  timestamp,\n' +
            '  nonce,\n' +
            '  bodyHash,\n' +
            '  API_PUBLIC\n' +
            '].join(\'\\n\');\n\n' +
            'const signature = crypto.createHmac(\'sha512\', API_SECRET)\n' +
            '  .update(canonicalString, \'utf8\')\n' +
            '  .digest(\'hex\');\n\n' +
            'async function callHcisApi() {\n' +
            '  const url = BASE_URL + PATH + (QUERY ? \'?\' + QUERY : \'\');\n' +
            '  const headers = {\n' +
            '    \'Accept\': \'application/json\',\n' +
            '    \'X-HC-Public\': API_PUBLIC,\n' +
            '    \'X-HC-Timestamp\': timestamp,\n' +
            '    \'X-HC-Nonce\': nonce,\n' +
            '    \'X-HC-Body-Hash\': bodyHash,\n' +
            '    \'X-HC-Signature\': signature\n' +
            '  };\n' +
            (examplePayload ? '  headers[\'Content-Type\'] = \'application/json\';\n\n' : '\n') +
            '  const response = await fetch(url, {\n' +
            '    method: METHOD,\n' +
            '    headers: headers,\n' +
            (examplePayload ? '    body: bodyJson\n' : '') +
            '  });\n\n' +
            '  const result = await response.json();\n' +
            '  console.log(\'Status:\', response.status, result);\n' +
            '}\n\n' +
            'callHcisApi();';

        // 4. JavaScript Axios
        var jsAxios = 'const axios = require(\'axios\');\n' +
            'const crypto = require(\'crypto\');\n\n' +
            'const API_PUBLIC = \'' + pubKey + '\';\n' +
            'const API_SECRET = \'' + secretKey + '\';\n' +
            'const BASE_URL   = \'' + host + '\';\n' +
            'const PATH       = \'' + samplePath + '\';\n' +
            'const METHOD     = \'' + method.toLowerCase() + '\';\n\n';

        if (examplePayload) {
            jsAxios += 'const payload = ' + JSON.stringify(examplePayload, null, 2) + ';\n' +
                'const bodyJson = JSON.stringify(payload);\n' +
                'const bodyHash = crypto.createHash(\'sha256\').update(bodyJson, \'utf8\').digest(\'hex\');\n\n';
        } else {
            jsAxios += 'const bodyJson = \'\';\n' +
                'const bodyHash = \'' + BODY_HASH_KOSONG + '\';\n\n';
        }

        jsAxios += 'const timestamp = new Date().toISOString();\n' +
            'const nonce = crypto.randomBytes(16).toString(\'hex\');\n\n' +
            'const canonical = [\n' +
            '  METHOD.toUpperCase(),\n' +
            '  PATH,\n' +
            '  \'\',\n' +
            '  timestamp,\n' +
            '  nonce,\n' +
            '  bodyHash,\n' +
            '  API_PUBLIC\n' +
            '].join(\'\\n\');\n\n' +
            'const signature = crypto.createHmac(\'sha512\', API_SECRET).update(canonical).digest(\'hex\');\n\n' +
            'axios({\n' +
            '  method: METHOD,\n' +
            '  url: BASE_URL + PATH,\n' +
            '  headers: {\n' +
            '    \'Accept\': \'application/json\',\n' +
            (examplePayload ? '    \'Content-Type\': \'application/json\',\n' : '') +
            '    \'X-HC-Public\': API_PUBLIC,\n' +
            '    \'X-HC-Timestamp\': timestamp,\n' +
            '    \'X-HC-Nonce\': nonce,\n' +
            '    \'X-HC-Body-Hash\': bodyHash,\n' +
            '    \'X-HC-Signature\': signature\n' +
            '  },\n' +
            (examplePayload ? '  data: payload\n' : '') +
            '}).then(res => console.log(res.data))\n' +
            '.catch(err => console.error(err.response ? err.response.data : err.message));';

        // 5. Python Requests
        var pythonCode = 'import hashlib\n' +
            'import hmac\n' +
            'import json\n' +
            'import secrets\n' +
            'from datetime import datetime, timezone\n' +
            'import requests\n\n' +
            '# 1. Kredensial & Parameter Endpoint\n' +
            'API_PUBLIC = \'' + pubKey + '\'\n' +
            'API_SECRET = \'' + secretKey + '\'\n' +
            'HOST       = \'' + host + '\'\n' +
            'METHOD     = \'' + method + '\'\n' +
            'PATH       = \'' + samplePath + '\'\n' +
            'QUERY      = \'\'\n\n';

        if (examplePayload) {
            pythonCode += 'payload = ' + JSON.stringify(examplePayload, null, 4).replace(/true/g, 'True').replace(/false/g, 'False').replace(/null/g, 'None') + '\n\n' +
                '# 2. Hitung Body Hash\n' +
                'body_json = json.dumps(payload, separators=(\',\', \':\'))\n' +
                'body_hash = hashlib.sha256(body_json.encode(\'utf-8\')).hexdigest()\n\n';
        } else {
            pythonCode += '# 2. Hitung Body Hash (Body Kosong)\n' +
                'body_json = \'\'\n' +
                'body_hash = \'' + BODY_HASH_KOSONG + '\'\n\n';
        }

        pythonCode += '# 3. Nilai Header Keamanan\n' +
            'timestamp = datetime.now(timezone.utc).strftime(\'%Y-%m-%dT%H:%M:%S.%f\')[:-3] + \'Z\'\n' +
            'nonce     = secrets.token_hex(16)\n\n' +
            '# 4. Susun Canonical String 7 Baris\n' +
            'canonical = \'\\n\'.join([\n' +
            '    METHOD,\n' +
            '    PATH,\n' +
            '    QUERY,\n' +
            '    timestamp,\n' +
            '    nonce,\n' +
            '    body_hash,\n' +
            '    API_PUBLIC\n' +
            '])\n\n' +
            '# 5. Hitung Tanda Tangan HMAC-SHA512\n' +
            'signature = hmac.new(API_SECRET.encode(\'utf-8\'), canonical.encode(\'utf-8\'), hashlib.sha512).hexdigest()\n\n' +
            '# 6. Kirim Request\n' +
            'headers = {\n' +
            '    \'Accept\': \'application/json\',\n' +
            '    \'X-HC-Public\': API_PUBLIC,\n' +
            '    \'X-HC-Timestamp\': timestamp,\n' +
            '    \'X-HC-Nonce\': nonce,\n' +
            '    \'X-HC-Body-Hash\': body_hash,\n' +
            '    \'X-HC-Signature\': signature\n' +
            '}\n' +
            (examplePayload ? 'headers[\'Content-Type\'] = \'application/json\'\n\n' : '\n') +
            'url = HOST + PATH + (f\'?{QUERY}\' if QUERY else \'\')\n' +
            'response = requests.request(METHOD, url, headers=headers' + (examplePayload ? ', data=body_json' : '') + ')\n' +
            'print(\'Status:\', response.status_code)\n' +
            'print(response.json())';

        // 6. cURL Terminal
        var curlCode = 'API_PUBLIC="' + pubKey + '"\n' +
            'API_SECRET="' + secretKey + '"\n' +
            'HOST="' + host + '"\n' +
            'METHOD="' + method + '"\n' +
            'PATH="' + samplePath + '"\n' +
            'QUERY=""\n' +
            'TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")\n' +
            'NONCE=$(openssl rand -hex 16)\n\n';

        if (examplePayload) {
            curlCode += 'BODY=\'' + JSON.stringify(examplePayload) + '\'\n' +
                'BODY_HASH=$(echo -n "$BODY" | openssl dgst -sha256 | awk \'{print $2}\')\n\n';
        } else {
            curlCode += 'BODY=""\n' +
                'BODY_HASH="' + BODY_HASH_KOSONG + '"\n\n';
        }

        curlCode += 'CANONICAL=$(printf "%s\\n%s\\n%s\\n%s\\n%s\\n%s\\n%s" "$METHOD" "$PATH" "$QUERY" "$TIMESTAMP" "$NONCE" "$BODY_HASH" "$API_PUBLIC")\n' +
            'SIGNATURE=$(printf "%s" "$CANONICAL" | openssl dgst -sha512 -hmac "$API_SECRET" | awk \'{print $2}\')\n\n' +
            'curl -X ' + method + ' "$HOST$PATH" \\\n' +
            '  -H "Accept: application/json" \\\n' +
            (examplePayload ? '  -H "Content-Type: application/json" \\\n' : '') +
            '  -H "X-HC-Public: $API_PUBLIC" \\\n' +
            '  -H "X-HC-Timestamp: $TIMESTAMP" \\\n' +
            '  -H "X-HC-Nonce: $NONCE" \\\n' +
            '  -H "X-HC-Body-Hash: $BODY_HASH" \\\n' +
            '  -H "X-HC-Signature: $SIGNATURE"' +
            (examplePayload ? ' \\\n  --data-raw "$BODY"' : '');

        // 7. Go net/http
        var goCode = 'package main\n\n' +
            'import (\n' +
            '    "bytes"\n' +
            '    "crypto/hmac"\n' +
            '    "crypto/rand"\n' +
            '    "crypto/sha256"\n' +
            '    "crypto/sha512"\n' +
            '    "encoding/hex"\n' +
            '    "fmt"\n' +
            '    "io"\n' +
            '    "net/http"\n' +
            '    "strings"\n' +
            '    "time"\n' +
            ')\n\n' +
            'func main() {\n' +
            '    apiPublic := "' + pubKey + '"\n' +
            '    apiSecret := "' + secretKey + '"\n' +
            '    host := "' + host + '"\n' +
            '    method := "' + method + '"\n' +
            '    path := "' + samplePath + '"\n' +
            '    query := ""\n\n';

        if (examplePayload) {
            goCode += '    bodyJson := `' + JSON.stringify(examplePayload) + '`\n' +
                '    bHash := sha256.Sum256([]byte(bodyJson))\n' +
                '    bodyHash := hex.EncodeToString(bHash[:])\n\n';
        } else {
            goCode += '    bodyJson := ""\n' +
                '    bodyHash := "' + BODY_HASH_KOSONG + '"\n\n';
        }

        goCode += '    timestamp := time.Now().UTC().Format("2006-01-02T15:04:05.000Z")\n' +
            '    nonceBytes := make([]byte, 16)\n' +
            '    rand.Read(nonceBytes)\n' +
            '    nonce := hex.EncodeToString(nonceBytes)\n\n' +
            '    canonical := strings.Join([]string{method, path, query, timestamp, nonce, bodyHash, apiPublic}, "\\n")\n' +
            '    h := hmac.New(sha512.New, []byte(apiSecret))\n' +
            '    h.Write([]byte(canonical))\n' +
            '    signature := hex.EncodeToString(h.Sum(nil))\n\n' +
            '    url := host + path\n' +
            (examplePayload ? '    req, _ := http.NewRequest(method, url, bytes.NewBuffer([]byte(bodyJson)))\n' : '    req, _ := http.NewRequest(method, url, nil)\n') +
            '    req.Header.Set("Accept", "application/json")\n' +
            (examplePayload ? '    req.Header.Set("Content-Type", "application/json")\n' : '') +
            '    req.Header.Set("X-HC-Public", apiPublic)\n' +
            '    req.Header.Set("X-HC-Timestamp", timestamp)\n' +
            '    req.Header.Set("X-HC-Nonce", nonce)\n' +
            '    req.Header.Set("X-HC-Body-Hash", bodyHash)\n' +
            '    req.Header.Set("X-HC-Signature", signature)\n\n' +
            '    client := &http.Client{}\n' +
            '    resp, err := client.Do(req)\n' +
            '    if err != nil {\n' +
            '        panic(err)\n' +
            '    }\n' +
            '    defer resp.Body.Close()\n' +
            '    body, _ := io.ReadAll(resp.Body)\n' +
            '    fmt.Println("Status:", resp.StatusCode)\n' +
            '    fmt.Println(string(body))\n' +
            '}';

        return {
            php_curl: { title: 'PHP (cURL Native)', code: phpCurl },
            php_guzzle: { title: 'PHP (Laravel Http Client)', code: phpGuzzle },
            js_fetch: { title: 'JavaScript (Fetch API)', code: jsFetch },
            js_axios: { title: 'JavaScript (Axios)', code: jsAxios },
            python: { title: 'Python (Requests)', code: pythonCode },
            curl: { title: 'cURL (Bash / Terminal)', code: curlCode },
            go: { title: 'Go (net/http)', code: goCode }
        };
    }

    function renderEndpointCodeSnippets(ep) {
        if (!ep || !elEpCodeSnippetDisplay) return;
        var snippets = generateEndpointSnippets(ep);
        var currentData = snippets[activeEpCodeLang] || snippets['php_curl'];

        if (elEpCodeActiveLangLabel) {
            elEpCodeActiveLangLabel.textContent = currentData.title;
        }

        elEpCodeSnippetDisplay.textContent = currentData.code;

        if (elBtnCopyEpCodeSnippet) {
            elBtnCopyEpCodeSnippet.onclick = function () {
                navigator.clipboard.writeText(currentData.code).then(function () {
                    elBtnCopyEpCodeSnippet.classList.add('copied');
                    if (elBtnCopyEpCodeSnippetLabel) elBtnCopyEpCodeSnippetLabel.textContent = 'Tersalin!';
                    showToast('Contoh kode ' + currentData.title + ' berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyEpCodeSnippet.classList.remove('copied');
                        if (elBtnCopyEpCodeSnippetLabel) elBtnCopyEpCodeSnippetLabel.textContent = 'Salin Kode';
                    }, 2000);
                });
            };
        }
    }

    function initEndpointCodeViewerControls() {
        if (elEpCodeLanguageButtons) {
            elEpCodeLanguageButtons.querySelectorAll('.sdk-tab-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var lang = btn.getAttribute('data-lang');
                    if (!lang) return;
                    activeEpCodeLang = lang;
                    elEpCodeLanguageButtons.querySelectorAll('.sdk-tab-btn').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    btn.classList.add('active');
                    if (currentSelectedEndpoint) {
                        renderEndpointCodeSnippets(currentSelectedEndpoint);
                    }
                });
            });
        }
    }

    // ── Render Full Rich Documentation Tab ────────────────────────────
    function renderDocumentationTab(spec) {
        if (!elTabContentDocs) return;
        var snippets = generateDocumentationSnippets();

        var html = '<div class="doc-hero-card">' +
            '<div class="doc-hero-tag">Panduan Integrasi B2B</div>' +
            '<h1 class="doc-hero-title">Kontrak Integrasi & Protokol API HCIS</h1>' +
            '<p class="doc-hero-desc">' +
                'Panduan lengkap arsitektur keamanan HMAC-SHA512, 5 Header Wajib, format string kanonikal 7 baris, penanganan kesalahan, serta contoh implementasi kode siap pakai untuk sistem mitra.' +
            '</p>' +
            '<div class="doc-protocol-tags">' +
                '<span class="doc-badge-pill"><span class="badge-dot-green"></span> 🔐 HMAC-SHA512 Auth</span>' +
                '<span class="doc-badge-pill">⏱️ Timestamp Window (±60 Detik)</span>' +
                '<span class="doc-badge-pill">🛡️ Single-Use Nonce</span>' +
                '<span class="doc-badge-pill">📦 Standard JSON Envelope</span>' +
            '</div>' +
        '</div>' +

        '<!-- Section 1: 5 Header Keamanan Wajib -->' +
        '<div class="doc-section-card">' +
            '<div class="doc-section-header">' +
                '<div class="doc-section-num">1</div>' +
                '<div>' +
                    '<h3 class="doc-section-title">5 Header Keamanan Wajib (HrisApiGuard)</h3>' +
                    '<p class="doc-section-sub">Setiap permintaan HTTP dari aplikasi mitra wajib menyertakan 5 header keamanan berikut.</p>' +
                '</div>' +
            '</div>' +

            '<div class="doc-table-wrap">' +
                '<table class="doc-spec-table">' +
                    '<thead>' +
                        '<tr>' +
                            '<th style="width: 22%;">Header HTTP</th>' +
                            '<th style="width: 18%;">Tipe & Format</th>' +
                            '<th style="width: 60%;">Deskripsi & Aturan Validasi</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>' +
                        '<tr>' +
                            '<td><code class="doc-header-code">X-HC-Public</code></td>' +
                            '<td><span class="doc-type-badge">String</span></td>' +
                            '<td>Kunci publik aplikasi mitra yang terdaftar di HCIS (contoh: <code>WEBCAREERS_PROD_...</code>). Menentukan izin endpoint yang diotorisasi.</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td><code class="doc-header-code">X-HC-Timestamp</code></td>' +
                            '<td><span class="doc-type-badge">ISO-8601 UTC</span></td>' +
                            '<td>Waktu permintaan dibuat dalam format <code>YYYY-MM-DDTHH:mm:ss.sssZ</code> (contoh: <code>2026-09-12T07:30:00.000Z</code>). Jam client tidak boleh berselisih lebih dari <strong>60 detik</strong> dari server HCIS. <span class="doc-warn-chip">Dilarang kirim Unix epoch integer</span></td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td><code class="doc-header-code">X-HC-Nonce</code></td>' +
                            '<td><span class="doc-type-badge">Hex / String</span></td>' +
                            '<td>Nilai acak unik sekali pakai (minimal 16 karakter). Nonce yang telah digunakan sebelumnya akan ditolak dengan <code>401 Unauthorized</code> guna mencegah serangan replay.</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td><code class="doc-header-code">X-HC-Body-Hash</code></td>' +
                            '<td><span class="doc-type-badge">SHA-256 Hex</span></td>' +
                            '<td>Hash <code>sha256(raw_body)</code> dalam huruf kecil (lowercase hex). Untuk request tanpa body (misal GET atau POST kosong), gunakan hash string kosong: <code>' + BODY_HASH_KOSONG + '</code>.</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td><code class="doc-header-code">X-HC-Signature</code></td>' +
                            '<td><span class="doc-type-badge">HMAC-SHA512 Hex</span></td>' +
                            '<td>Tanda tangan digital HMAC-SHA512 lowercase hex yang dihitung dari String Kanonikal 7 Baris menggunakan <code>Api_Secret</code> aplikasi Anda sebagai kunci rahasia.</td>' +
                        '</tr>' +
                    '</tbody>' +
                '</table>' +
            '</div>' +
        '</div>' +

        '<!-- Section 2: Format String Kanonikal 7 Baris -->' +
        '<div class="doc-section-card">' +
            '<div class="doc-section-header">' +
                '<div class="doc-section-num">2</div>' +
                '<div>' +
                    '<h3 class="doc-section-title">Format String Kanonikal 7 Baris</h3>' +
                    '<p class="doc-section-sub">String kanonikal dibentuk dengan menggabungkan 7 baris parameter berikut yang dipisahkan oleh karakter baris baru (<code>\\n</code>).</p>' +
                '</div>' +
            '</div>' +

            '<div class="canonical-visual-grid">' +
                '<div class="canonical-step-list">' +
                    '<div class="canonical-row"><span class="can-num">1</span><span class="can-key">METHOD</span><span class="can-desc">Huruf kapital metode HTTP (contoh: <code>GET</code>, <code>POST</code>, <code>PUT</code>)</span></div>' +
                    '<div class="canonical-row"><span class="can-num">2</span><span class="can-key">PATH</span><span class="can-desc">URI yang dipanggil, diawali <code>/</code> tanpa query (contoh: <code>/api/v1/hcis/visitor/permit-tamu</code>)</span></div>' +
                    '<div class="canonical-row"><span class="can-num">3</span><span class="can-key">QUERY</span><span class="can-desc">Query string mentah tanpa <code>?</code> (contoh: <code>dari=2026-01-01&sampai=2026-12-31</code>), atau string kosong</span></div>' +
                    '<div class="canonical-row"><span class="can-num">4</span><span class="can-key">TIMESTAMP</span><span class="can-desc">Nilai string yang sama persis dengan header <code>X-HC-Timestamp</code></span></div>' +
                    '<div class="canonical-row"><span class="can-num">5</span><span class="can-key">NONCE</span><span class="can-desc">Nilai string yang sama persis dengan header <code>X-HC-Nonce</code></span></div>' +
                    '<div class="canonical-row"><span class="can-num">6</span><span class="can-key">BODY_HASH</span><span class="can-desc">Nilai string yang sama persis dengan header <code>X-HC-Body-Hash</code></span></div>' +
                    '<div class="canonical-row"><span class="can-num">7</span><span class="can-key">PUBLIC_KEY</span><span class="can-desc">Nilai string yang sama persis dengan header <code>X-HC-Public</code></span></div>' +
                '</div>' +

                '<div class="canonical-formula-card">' +
                    '<div class="formula-card-title">📐 Rumus Pembentukan Tanda Tangan:</div>' +
                    '<pre class="formula-pre"><code>canonical = [METHOD, PATH, QUERY, TIMESTAMP, NONCE, BODY_HASH, PUBLIC_KEY].join("\\n");\nsignature = hash_hmac("sha512", canonical, API_SECRET);</code></pre>' +
                '</div>' +
            '</div>' +
        '</div>' +

        '<!-- Section 3: Contoh Kode Multi-Bahasa -->' +
        '<div class="doc-section-card">' +
            '<div class="doc-section-header">' +
                '<div class="doc-section-num">3</div>' +
                '<div>' +
                    '<h3 class="doc-section-title">Contoh Implementasi Kode (Multi-Language Snippets)</h3>' +
                    '<p class="doc-section-sub">Contoh fungsi penandatanganan dan pengiriman request siap pakai untuk berbagai bahasa backend mitra.</p>' +
                '</div>' +
            '</div>' +

            '<div class="sdk-tabs-container">' +
                '<div class="sdk-tab-buttons">' +
                    '<button type="button" class="sdk-tab-btn active" data-lang="php">PHP (cURL / Guzzle)</button>' +
                    '<button type="button" class="sdk-tab-btn" data-lang="nodejs">Node.js (Fetch / Axios)</button>' +
                    '<button type="button" class="sdk-tab-btn" data-lang="python">Python (Requests)</button>' +
                    '<button type="button" class="sdk-tab-btn" data-lang="curl">cURL (CLI)</button>' +
                '</div>' +

                '<!-- PHP Panel -->' +
                '<div class="sdk-code-panel active" id="sdkPanelPhp">' +
                    '<div class="sdk-code-topbar">' +
                        '<span class="sdk-lang-label">PHP 8.x / cURL</span>' +
                        '<button type="button" class="btn-sdk-copy" id="btnCopySdkPhp">' +
                            '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '<span>Salin Kode</span>' +
                        '</button>' +
                    '</div>' +
                    '<pre class="sdk-pre"><code id="codeSnippetPhp">' + escapeHtml(snippets.php) + '</code></pre>' +
                '</div>' +

                '<!-- Node.js Panel -->' +
                '<div class="sdk-code-panel" id="sdkPanelNodejs">' +
                    '<div class="sdk-code-topbar">' +
                        '<span class="sdk-lang-label">Node.js 18+ (Crypto & Fetch)</span>' +
                        '<button type="button" class="btn-sdk-copy" id="btnCopySdkNodejs">' +
                            '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '<span>Salin Kode</span>' +
                        '</button>' +
                    '</div>' +
                    '<pre class="sdk-pre"><code id="codeSnippetNodejs">' + escapeHtml(snippets.nodejs) + '</code></pre>' +
                '</div>' +

                '<!-- Python Panel -->' +
                '<div class="sdk-code-panel" id="sdkPanelPython">' +
                    '<div class="sdk-code-topbar">' +
                        '<span class="sdk-lang-label">Python 3.x (Requests & HMAC)</span>' +
                        '<button type="button" class="btn-sdk-copy" id="btnCopySdkPython">' +
                            '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '<span>Salin Kode</span>' +
                        '</button>' +
                    '</div>' +
                    '<pre class="sdk-pre"><code id="codeSnippetPython">' + escapeHtml(snippets.python) + '</code></pre>' +
                '</div>' +

                '<!-- cURL Panel -->' +
                '<div class="sdk-code-panel" id="sdkPanelCurl">' +
                    '<div class="sdk-code-topbar">' +
                        '<span class="sdk-lang-label">Bash / cURL</span>' +
                        '<button type="button" class="btn-sdk-copy" id="btnCopySdkCurl">' +
                            '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '<span>Salin Perintah</span>' +
                        '</button>' +
                    '</div>' +
                    '<pre class="sdk-pre"><code id="codeSnippetCurl">' + escapeHtml(snippets.curl) + '</code></pre>' +
                '</div>' +
            '</div>' +
        '</div>' +

        '<!-- Section 4: Standar Format Respons JSON -->' +
        '<div class="doc-section-card">' +
            '<div class="doc-section-header">' +
                '<div class="doc-section-num">4</div>' +
                '<div>' +
                    '<h3 class="doc-section-title">Standar Format Respons API</h3>' +
                    '<p class="doc-section-sub">Seluruh endpoint HCIS mengembalikan pembungkus JSON baku yang seragam.</p>' +
                '</div>' +
            '</div>' +

            '<div class="doc-response-grid">' +
                '<div class="doc-res-box">' +
                    '<div class="doc-res-box-header success">' +
                        '<span>🟢 Respons Sukses (HTTP 200 / 201)</span>' +
                    '</div>' +
                    '<pre class="doc-res-pre"><code>{\n  "sukses": true,\n  "message": "Deskripsi keberhasilan operasi.",\n  "result": {\n    "no_transaksi": "PRM-202609-0001",\n    "status": "PENDING"\n  }\n}</code></pre>' +
                '</div>' +

                '<div class="doc-res-box">' +
                    '<div class="doc-res-box-header error">' +
                        '<span>🔴 Respons Galat (HTTP 400 / 401 / 403 / 404 / 422 / 500)</span>' +
                    '</div>' +
                    '<pre class="doc-res-pre"><code>{\n  "sukses": false,\n  "message": "Penjelasan penyebab kegagalan.",\n  "errors": {\n    "parameter": ["Penjelasan kesalahan validasi."]\n  },\n  "result": null\n}</code></pre>' +
                '</div>' +
            '</div>' +
        '</div>' +

        '<!-- Section 5: Kebijakan Keamanan & Praktik Terbaik -->' +
        '<div class="doc-section-card">' +
            '<div class="doc-section-header">' +
                '<div class="doc-section-num">5</div>' +
                '<div>' +
                    '<h3 class="doc-section-title">Kebijakan Keamanan & Praktik Terbaik</h3>' +
                    '<p class="doc-section-sub">Pedoman teknis untuk menjamin stabilitas dan keamanan komunikasi sistem mitra dengan HCIS.</p>' +
                '</div>' +
            '</div>' +

            '<div class="doc-security-grid">' +
                '<div class="security-card">' +
                    '<div class="security-card-icon">🔒</div>' +
                    '<div class="security-card-title">Enkripsi HTTPS / TLS Wajib</div>' +
                    '<div class="security-card-desc">Semua panggilan di staging & production wajib menggunakan HTTPS. Request via HTTP polos akan ditolak langsung.</div>' +
                '</div>' +
                '<div class="security-card">' +
                    '<div class="security-card-icon">🛡️</div>' +
                    '<div class="security-card-title">Kerahasiaan Api_Secret</div>' +
                    '<div class="security-card-desc"><code>Api_Secret</code> hanya boleh disimpan di server backend Anda (environment variables). Jangan pernah menaruhnya di aplikasi mobile frontend atau client-side JS.</div>' +
                '</div>' +
                '<div class="security-card">' +
                    '<div class="security-card-icon">🔁</div>' +
                    '<div class="security-card-title">Idempotensi Transaksi</div>' +
                    '<div class="security-card-desc">Pada endpoint <code>POST</code> pembuatan data, sertakan <code>Idempotency-Key</code> atau <code>referensi_mitra</code> unik agar request aman diulang saat jaringan timeout.</div>' +
                '</div>' +
                '<div class="security-card">' +
                    '<div class="security-card-icon">⏱️</div>' +
                    '<div class="security-card-title">Sinkronisasi Jam (NTP)</div>' +
                    '<div class="security-card-desc">Pastikan server mitra menggunakan layanan sinkronisasi waktu NTP. Deviasi jam lebih dari 60 detik akan ditolak dengan <code>401 Unauthorized</code>.</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        elTabContentDocs.innerHTML = html;
        initSdkTabs(snippets);
    }

    function initSdkTabs(snippets) {
        if (!elTabContentDocs) return;
        var tabBtns = elTabContentDocs.querySelectorAll('.sdk-tab-btn');
        var panels = {
            php: elTabContentDocs.querySelector('#sdkPanelPhp'),
            nodejs: elTabContentDocs.querySelector('#sdkPanelNodejs'),
            python: elTabContentDocs.querySelector('#sdkPanelPython'),
            curl: elTabContentDocs.querySelector('#sdkPanelCurl')
        };

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var lang = btn.getAttribute('data-lang');
                tabBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                for (var k in panels) {
                    if (panels[k]) {
                        panels[k].style.display = (k === lang) ? 'block' : 'none';
                    }
                }
            });
        });

        function setupSdkCopy(btnId, codeText, label) {
            var btn = elTabContentDocs.querySelector(btnId);
            if (!btn) return;
            btn.addEventListener('click', function () {
                navigator.clipboard.writeText(codeText).then(function () {
                    btn.classList.add('copied');
                    btn.querySelector('span').textContent = 'Tersalin!';
                    showToast('Snippet kode ' + label + ' berhasil disalin!');
                    setTimeout(function () {
                        btn.classList.remove('copied');
                        btn.querySelector('span').textContent = label === 'cURL' ? 'Salin Perintah' : 'Salin Kode';
                    }, 2000);
                });
            });
        }

        setupSdkCopy('#btnCopySdkPhp', snippets.php, 'PHP');
        setupSdkCopy('#btnCopySdkNodejs', snippets.nodejs, 'Node.js');
        setupSdkCopy('#btnCopySdkPython', snippets.python, 'Python');
        setupSdkCopy('#btnCopySdkCurl', snippets.curl, 'cURL');
    }

    // ── Generate Visual Stepper Workflow ──────────────────────────────
    function generateWorkflow(method, path) {
        if (path.includes('permit-tamu') && method === 'POST' && !path.includes('batal')) {
            return [
                { num: '1', title: 'Kirim Request', sub: 'Client B2B (HMAC)' },
                { num: '2', title: 'Validasi & Guard', sub: 'HrisApiGuard (5 Header)' },
                { num: '3', title: 'Antrean Verifikator', sub: 'Status PENDING' },
                { num: '4', title: 'Izin Aktif Gerbang', sub: 'Status APPROVED' }
            ];
        } else if (path.includes('batal')) {
            return [
                { num: '1', title: 'Kirim Batal', sub: 'No Transaksi Permit' },
                { num: '2', title: 'Validasi Kepemilikan', sub: 'Isolasi Akun Mitra' },
                { num: '3', title: 'Cabut Izin Gerbang', sub: 'Status REVOKED' },
                { num: '4', title: 'Pemulihan Kuota', sub: 'Jatah Permit Bebas' }
            ];
        } else if (method === 'GET') {
            return [
                { num: '1', title: 'Kirim Query', sub: 'Parameter URL' },
                { num: '2', title: 'Cek Hak Akses', sub: 'Otorisasi Mitra' },
                { num: '3', title: 'Query Database', sub: 'Data Terisolasi' },
                { num: '4', title: 'Respon JSON', sub: 'Envelope 200 OK' }
            ];
        } else {
            return [
                { num: '1', title: 'Kirim Request', sub: 'Client B2B' },
                { num: '2', title: 'Verifikasi HMAC', sub: 'HrisApiGuard' },
                { num: '3', title: 'Eksekusi Backend', sub: 'Layanan Bisnis' },
                { num: '4', title: 'Respon Standar', sub: 'JSON Baku' }
            ];
        }
    }

    // ── Generate Contextual Highlight Cards ───────────────────────────
    function generateHighlights(ep) {
        var cards = [];
        if (ep.path.includes('permit-tamu') && ep.method === 'POST') {
            cards.push({ icon: '📋', color: 'indigo', title: 'Alur Persetujuan', desc: 'Permit masuk antrean verifikator meja HCIS (status PENDING) sebelum aktif.' });
            cards.push({ icon: '🛡️', color: 'emerald', title: 'Idempotensi Aman', desc: 'Header Idempotency-Key / referensi_mitra menjamin keamanan pengiriman ulang.' });
            cards.push({ icon: '⏱️', color: 'amber', title: 'Masa Berlaku', desc: 'Izin berlaku selama 1 hari kalender pada tanggal yang disetujui verifikator.' });
            cards.push({ icon: '👤', color: 'sky', title: 'Penanggung Jawab', desc: 'PIC izin otomatis terikat pada akun aplikasi mitra terdaftar di HCIS.' });
        } else if (ep.path.includes('permit-tamu') && ep.method === 'GET') {
            cards.push({ icon: '🔄', color: 'indigo', title: 'Polling Status', desc: 'Disarankan melakukan pemanggilan berkala hingga status berubah dari PENDING.' });
            cards.push({ icon: '🔒', color: 'emerald', title: 'Isolasi Akun', desc: 'Permit aplikasi mitra lain dibalas 404 Not Found demi kerahasiaan data.' });
            cards.push({ icon: '📊', color: 'sky', title: 'Status Siklus Hidup', desc: 'Nilai status: PENDING, APPROVED, REJECTED, REVOKED, atau EXPIRED.' });
        } else if (ep.path.includes('batal')) {
            cards.push({ icon: '🚫', color: 'amber', title: 'Pencabutan Izin', desc: 'Menghentikan pengajuan PENDING atau langsung mencabut permit APPROVED.' });
            cards.push({ icon: '⚡', color: 'emerald', title: 'Pemulihan Kuota', desc: 'Jatah permit aktif aplikasi mitra langsung dipulihkan seketika.' });
        } else if (ep.path.includes('kalender')) {
            cards.push({ icon: '📅', color: 'indigo', title: 'Kalender Operasional', desc: 'Data hari kerja efektif dan hari libur resmi Head Office HCIS.' });
            cards.push({ icon: '⚡', color: 'emerald', title: 'Perhitungan SLA', desc: 'Membantu sistem mitra menghitung durasi proses dalam hari kerja nyata.' });
        } else {
            cards.push({ icon: '🔐', color: 'indigo', title: 'Autentikasi Aman', desc: 'Setiap request wajib ditandatangani dengan HMAC-SHA512 5 Header.' });
            cards.push({ icon: '📦', color: 'emerald', title: 'Format Konsisten', desc: 'Format envelope JSON baku (sukses, message, result) di semua endpoint.' });
        }
        return cards;
    }

    // ── Parse OpenAPI Document ────────────────────────────────────────
    function parseOpenApi(spec) {
        var endpoints = [];
        var paths = spec.paths || {};

        for (var pathKey in paths) {
            var pathItem = paths[pathKey];
            var methods = ['get', 'post', 'put', 'patch', 'delete'];
            methods.forEach(function (method) {
                if (pathItem[method]) {
                    var op = pathItem[method];
                    var tags = op.tags && op.tags.length ? op.tags : ['General'];
                    endpoints.push({
                        key: method.toUpperCase() + ' ' + pathKey,
                        method: method.toUpperCase(),
                        path: pathKey,
                        tags: tags,
                        primaryTag: tags[0],
                        summary: op.summary || pathKey,
                        description: op.description || '',
                        operationId: op.operationId || '',
                        parameters: op.parameters || [],
                        requestBody: op.requestBody || null,
                        responses: op.responses || {}
                    });
                }
            });
        }
        return endpoints;
    }

    // ── Render Sidebar Navigation ─────────────────────────────────────
    function renderSidebar(endpoints, filterQuery) {
        elSidebarAccordionList.innerHTML = '';
        var query = (filterQuery || '').toLowerCase().trim();

        var filtered = endpoints.filter(function (ep) {
            if (!query) return true;
            return ep.path.toLowerCase().indexOf(query) !== -1 ||
                   ep.summary.toLowerCase().indexOf(query) !== -1 ||
                   ep.method.toLowerCase().indexOf(query) !== -1 ||
                   ep.primaryTag.toLowerCase().indexOf(query) !== -1;
        });

        // Group by primaryTag
        var groups = {};
        filtered.forEach(function (ep) {
            if (!groups[ep.primaryTag]) { groups[ep.primaryTag] = []; }
            groups[ep.primaryTag].push(ep);
        });

        elSidebarTotalCount.textContent = endpoints.length;
        if (elNavEndpointCount) { elNavEndpointCount.textContent = endpoints.length; }

        if (Object.keys(groups).length === 0) {
            elSidebarAccordionList.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8; font-size:12.5px;">Tidak ada endpoint yang cocok dengan pencarian.</div>';
            return;
        }

        for (var tag in groups) {
            var items = groups[tag];
            var groupDiv = document.createElement('div');
            groupDiv.className = 'category-group';

            var header = document.createElement('div');
            header.className = 'category-header';
            header.innerHTML =
                '<div class="category-title-wrap">' +
                    '<svg class="category-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
                    '<span class="category-title">' + escapeHtml(tag) + '</span>' +
                '</div>' +
                '<span class="category-count">' + items.length + '</span>';

            header.addEventListener('click', (function (g) {
                return function () { g.classList.toggle('is-collapsed'); };
            })(groupDiv));

            var itemsContainer = document.createElement('div');
            itemsContainer.className = 'category-items';

            items.forEach(function (ep) {
                var itemLink = document.createElement('a');
                itemLink.className = 'endpoint-nav-item' + (currentEndpointKey === ep.key ? ' active' : '');
                itemLink.setAttribute('data-endpoint-key', ep.key);
                itemLink.innerHTML =
                    '<span class="method-badge ' + ep.method.toLowerCase() + '">' + ep.method + '</span>' +
                    '<span class="endpoint-nav-summary" title="' + escapeHtml(ep.summary) + '">' + escapeHtml(ep.summary) + '</span>';

                itemLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    pilihEndpoint(ep);
                });

                itemsContainer.appendChild(itemLink);
            });

            groupDiv.appendChild(header);
            groupDiv.appendChild(itemsContainer);
            elSidebarAccordionList.appendChild(groupDiv);
        }
    }

    // ── Select & Display Endpoint Workspace ───────────────────────────
    function pilihEndpoint(ep) {
        currentEndpointKey = ep.key;
        currentSelectedEndpoint = ep;

        // Update active class in sidebar
        document.querySelectorAll('.endpoint-nav-item').forEach(function (item) {
            item.classList.toggle('active', item.getAttribute('data-endpoint-key') === ep.key);
        });

        // Hide Empty State & Show Detail Card
        elWorkspaceEmptyState.style.display = 'none';
        elWorkspaceDetailCard.style.display = 'block';

        // Switch to active subtab (default: desc)
        setSubtab(currentActiveSubtab && ['desc', 'params', 'code', 'tryout'].includes(currentActiveSubtab) ? currentActiveSubtab : 'desc');

        // Populate Header
        elDetailMethodBadge.className = 'method-badge ' + ep.method.toLowerCase();
        elDetailMethodBadge.textContent = ep.method;
        elDetailPathText.textContent = ep.path;
        elDetailSummaryText.textContent = ep.summary;

        // Protocol & Security Chips
        var chips = [
            '<span class="protocol-chip chip-indigo">🔐 HMAC-SHA512</span>',
            '<span class="protocol-chip chip-emerald">📦 JSON Body</span>',
            '<span class="protocol-chip">⚡ SLA Cepat</span>'
        ];
        if (ep.method === 'POST') {
            chips.push('<span class="protocol-chip chip-amber">🔁 Idempotensi Aman</span>');
        }
        elDetailProtocolChips.innerHTML = chips.join('');

        // 1. Render Visual Integration Flowchart Stepper
        var steps = generateWorkflow(ep.method, ep.path);
        elDetailVisualStepper.innerHTML = steps.map(function (s, idx) {
            return '<div class="stepper-step">' +
                '<div class="stepper-node">' + s.num + '</div>' +
                '<div class="stepper-info">' +
                    '<div class="stepper-title">' + escapeHtml(s.title) + '</div>' +
                    '<div class="stepper-sub">' + escapeHtml(s.sub) + '</div>' +
                '</div>' +
            '</div>' + (idx < steps.length - 1 ? '<div class="stepper-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg></div>' : '');
        }).join('');

        // 2. Render Visual Highlight Cards (Alur & Aturan Bisnis)
        var highlights = generateHighlights(ep);
        elDetailHighlightsGrid.innerHTML = highlights.map(function (h) {
            return '<div class="highlight-card">' +
                '<div class="highlight-icon ' + h.color + '">' + h.icon + '</div>' +
                '<div class="highlight-content">' +
                    '<div class="highlight-title">' + escapeHtml(h.title) + '</div>' +
                    '<div class="highlight-desc">' + escapeHtml(h.desc) + '</div>' +
                '</div>' +
            '</div>';
        }).join('');

        // 3. Concise Description Text
        elDetailDescriptionBox.innerHTML = renderEndpointDescription(ep.description || 'Tidak ada deskripsi rinci untuk endpoint ini.');

        // 4. Parameters Section (Query / Path) as Visual Field Cards
        var params = ep.parameters || [];
        var hasParams = params.length > 0;
        if (hasParams) {
            elDetailParametersSection.style.display = 'block';
            var reqCount = 0;
            var optCount = 0;

            elDetailParametersList.innerHTML = params.map(function (rawP) {
                var p = resolveSchema(rawP);
                var isReq = p.required === true;
                if (isReq) reqCount++; else optCount++;

                var pSchema = resolveSchema(p.schema);
                var pType = (pSchema && pSchema.type) ? pSchema.type : (p.type || 'string');
                var pIn = p.in || 'query';
                var pExample = (pSchema && pSchema.example !== undefined) ? pSchema.example : (p.example !== undefined ? p.example : '');
                var pFormat = (pSchema && pSchema.format) ? pSchema.format : (p.format || '');
                var pEnum = (pSchema && pSchema.enum) ? pSchema.enum : (p.enum || null);
                var pDefault = (pSchema && pSchema.default !== undefined) ? pSchema.default : (p.default !== undefined ? p.default : null);

                var constraints = [];
                if (pFormat) constraints.push(pFormat);
                if (pSchema && pSchema.maxLength) constraints.push('max ' + pSchema.maxLength);
                if (pSchema && pSchema.minLength) constraints.push('min ' + pSchema.minLength);
                var constraintText = constraints.length > 0 ? ' · ' + constraints.join(', ') : '';

                var enumHtml = '';
                if (pEnum && Array.isArray(pEnum) && pEnum.length > 0) {
                    enumHtml = '<div style="margin-top: 6px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">' +
                        '<span style="font-size: 10.5px; font-weight: 700; color: #64748b;">Pilihan Nilai:</span> ' +
                        pEnum.map(function (val) {
                            return '<span class="spec-enum-chip">' + escapeHtml(val) + '</span>';
                        }).join('') +
                    '</div>';
                }

                var exampleHtml = '';
                if (pExample !== '' && pExample !== null && pExample !== undefined) {
                    var exStr = String(pExample);
                    exampleHtml = '<div class="field-card-bottom">' +
                        '<div class="field-example-row">' +
                            '<span class="field-example-label">Contoh Nilai:</span>' +
                            '<code class="field-example-val" title="' + escapeHtml(exStr) + '">' + escapeHtml(exStr) + '</code>' +
                            '<button type="button" class="field-copy-btn" title="Salin contoh nilai" onclick="copySnippetText(\'' + escapeJsString(exStr) + '\', \'Contoh nilai disalin!\')">' +
                                '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                }

                return '<div class="field-card-item ' + (isReq ? 'is-required' : 'is-optional') + '">' +
                    '<div class="field-card-top-row">' +
                        '<div class="field-name-group">' +
                            '<span class="field-name-mono" title="Klik untuk salin nama parameter" onclick="copySnippetText(\'' + escapeJsString(p.name) + '\', \'Nama parameter disalin!\')">' +
                                escapeHtml(p.name) +
                                '<svg class="field-name-copy-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '</span>' +
                            (isReq ? '<span class="spec-req-badge wajib">● Wajib</span>' : '<span class="spec-req-badge opsional">○ Opsional</span>') +
                            '<span class="spec-in-badge">in: ' + escapeHtml(pIn) + '</span>' +
                            '<span class="spec-type-badge">' + escapeHtml(pType) + (constraintText ? '<span class="spec-type-constraint">' + escapeHtml(constraintText) + '</span>' : '') + '</span>' +
                            (pDefault !== null ? '<span class="spec-default-badge">default: ' + escapeHtml(String(pDefault)) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="field-card-middle">' +
                        escapeHtml(p.description || 'Tidak ada keterangan rinci.') +
                        enumHtml +
                    '</div>' +
                    exampleHtml +
                '</div>';
            }).join('');

            if (elDetailParamsCountBadge) {
                elDetailParamsCountBadge.textContent = params.length + ' parameter (' + reqCount + ' wajib, ' + optCount + ' opsional)';
            }
        } else {
            elDetailParametersSection.style.display = 'none';
            elDetailParametersList.innerHTML = '';
            if (elDetailParamsCountBadge) elDetailParamsCountBadge.textContent = '0 parameter';
        }

        // 5. Request Body Section: Visual Field Cards & Sample JSON
        var reqInfo = extractRequestBodyInfo(ep.requestBody);
        var hasBody = false;

        if (reqInfo && reqInfo.schema && reqInfo.schema.properties) {
            hasBody = true;
            elDetailRequestBodySection.style.display = 'block';
            var requiredList = reqInfo.schema.required || [];
            var bodyFieldsHtml = [];
            var reqBodyCount = 0;
            var optBodyCount = 0;

            for (var propKey in reqInfo.schema.properties) {
                var prop = resolveSchema(reqInfo.schema.properties[propKey]);
                var isReq = requiredList.indexOf(propKey) !== -1;
                if (isReq) reqBodyCount++; else optBodyCount++;

                var pType = prop.type || 'string';
                var pDesc = prop.description || '';
                var pExample = (prop.example !== undefined) ? prop.example : (reqInfo.exampleObj && reqInfo.exampleObj[propKey] !== undefined ? reqInfo.exampleObj[propKey] : '');
                var pFormat = prop.format || '';
                var pEnum = prop.enum || null;
                var pDefault = prop.default !== undefined ? prop.default : null;

                var constraints = [];
                if (pFormat) constraints.push(pFormat);
                if (prop.maxLength) constraints.push('max ' + prop.maxLength);
                if (prop.minLength) constraints.push('min ' + prop.minLength);
                if (prop.minimum !== undefined) constraints.push('min val ' + prop.minimum);
                if (prop.maximum !== undefined) constraints.push('max val ' + prop.maximum);
                var constraintText = constraints.length > 0 ? ' · ' + constraints.join(', ') : '';

                var enumHtml = '';
                if (pEnum && Array.isArray(pEnum) && pEnum.length > 0) {
                    enumHtml = '<div style="margin-top: 6px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">' +
                        '<span style="font-size: 10.5px; font-weight: 700; color: #64748b;">Pilihan Nilai:</span> ' +
                        pEnum.map(function (val) {
                            return '<span class="spec-enum-chip">' + escapeHtml(val) + '</span>';
                        }).join('') +
                    '</div>';
                }

                var exampleHtml = '';
                if (pExample !== '' && pExample !== null && pExample !== undefined) {
                    var exStr = typeof pExample === 'object' ? JSON.stringify(pExample) : String(pExample);
                    exampleHtml = '<div class="field-card-bottom">' +
                        '<div class="field-example-row">' +
                            '<span class="field-example-label">Contoh Nilai:</span>' +
                            '<code class="field-example-val" title="' + escapeHtml(exStr) + '">' + escapeHtml(exStr) + '</code>' +
                            '<button type="button" class="field-copy-btn" title="Salin contoh nilai" onclick="copySnippetText(\'' + escapeJsString(exStr) + '\', \'Contoh nilai disalin!\')">' +
                                '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                }

                bodyFieldsHtml.push(
                    '<div class="field-card-item ' + (isReq ? 'is-required' : 'is-optional') + '">' +
                        '<div class="field-card-top-row">' +
                            '<div class="field-name-group">' +
                                '<span class="field-name-mono" title="Klik untuk salin nama field" onclick="copySnippetText(\'' + escapeJsString(propKey) + '\', \'Nama field disalin!\')">' +
                                    escapeHtml(propKey) +
                                    '<svg class="field-name-copy-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>' +
                                '</span>' +
                                (isReq ? '<span class="spec-req-badge wajib">● Wajib</span>' : '<span class="spec-req-badge opsional">○ Opsional</span>') +
                                '<span class="spec-in-badge">in: body</span>' +
                                '<span class="spec-type-badge">' + escapeHtml(pType) + (constraintText ? '<span class="spec-type-constraint">' + escapeHtml(constraintText) + '</span>' : '') + '</span>' +
                                (pDefault !== null ? '<span class="spec-default-badge">default: ' + escapeHtml(String(pDefault)) + '</span>' : '') +
                            '</div>' +
                        '</div>' +
                        '<div class="field-card-middle">' +
                            escapeHtml(pDesc || 'Tidak ada keterangan rinci.') +
                            enumHtml +
                        '</div>' +
                        exampleHtml +
                    '</div>'
                );
            }

            elDetailBodyFieldsList.innerHTML = bodyFieldsHtml.join('');
            if (elDetailBodyCountBadge) {
                var totalProps = Object.keys(reqInfo.schema.properties).length;
                elDetailBodyCountBadge.textContent = totalProps + ' field (' + reqBodyCount + ' wajib, ' + optBodyCount + ' opsional)';
            }

            var jsonPretty = JSON.stringify(reqInfo.exampleObj, null, 2);
            elDetailRequestBodyCode.innerHTML = highlightJson(jsonPretty);
            if (elDetailBodyLineCount) {
                var lineCount = jsonPretty.split('\n').length;
                elDetailBodyLineCount.textContent = lineCount + ' baris';
            }

            elBtnCopyRequestBodyExample.onclick = function () {
                navigator.clipboard.writeText(jsonPretty).then(function () {
                    elBtnCopyRequestBodyExample.classList.add('copied');
                    elBtnCopyRequestBodyExample.querySelector('span').textContent = 'Tersalin!';
                    showToast('Contoh Request Body berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyRequestBodyExample.classList.remove('copied');
                        elBtnCopyRequestBodyExample.querySelector('span').textContent = 'Salin JSON Payload';
                    }, 2000);
                });
            };
        } else if (reqInfo && reqInfo.exampleObj && Object.keys(reqInfo.exampleObj).length > 0) {
            hasBody = true;
            elDetailRequestBodySection.style.display = 'block';
            elDetailBodyFieldsList.innerHTML = '';
            if (elDetailBodyCountBadge) {
                elDetailBodyCountBadge.textContent = 'JSON Payload';
            }
            var jsonPretty = JSON.stringify(reqInfo.exampleObj, null, 2);
            elDetailRequestBodyCode.innerHTML = highlightJson(jsonPretty);
            if (elDetailBodyLineCount) {
                var lineCount = jsonPretty.split('\n').length;
                elDetailBodyLineCount.textContent = lineCount + ' baris';
            }

            elBtnCopyRequestBodyExample.onclick = function () {
                navigator.clipboard.writeText(jsonPretty).then(function () {
                    elBtnCopyRequestBodyExample.classList.add('copied');
                    elBtnCopyRequestBodyExample.querySelector('span').textContent = 'Tersalin!';
                    showToast('Contoh Request Body berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyRequestBodyExample.classList.remove('copied');
                        elBtnCopyRequestBodyExample.querySelector('span').textContent = 'Salin JSON Payload';
                    }, 2000);
                });
            };
        } else {
            elDetailRequestBodySection.style.display = 'none';
            elDetailBodyFieldsList.innerHTML = '';
            elDetailRequestBodyCode.innerHTML = '';
            if (elDetailBodyCountBadge) elDetailBodyCountBadge.textContent = '0 field';
        }

        // Notice when neither params nor body are required
        if (elDetailNoParamsNotice) {
            if (!hasParams && !hasBody) {
                elDetailNoParamsNotice.style.display = 'flex';
            } else {
                elDetailNoParamsNotice.style.display = 'none';
            }
        }

        // 6. Interactive Contoh Response Viewer (Screenshot-matched)
        renderContohResponseViewer(ep);

        // 7. Render Multi-Language SDK Snippets for this endpoint
        renderEndpointCodeSnippets(ep);

        // 8. Populate Tryout Form Fields
        setupTryoutForm(ep);

        // 8. Copy URL & cURL Handlers
        elBtnCopyEndpointUrl.onclick = function () {
            var host = window.location.origin;
            if (openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
                host = openApiSpec.servers[0].url.replace(/\/$/, '');
            }
            var fullUrl = host + ep.path;
            navigator.clipboard.writeText(fullUrl).then(function () {
                elBtnCopyEndpointUrl.classList.add('copied');
                elBtnCopyEndpointUrl.querySelector('span').textContent = 'Tersalin!';
                showToast('URL Endpoint berhasil disalin: ' + ep.path);
                setTimeout(function () {
                    elBtnCopyEndpointUrl.classList.remove('copied');
                    elBtnCopyEndpointUrl.querySelector('span').textContent = 'Salin URL';
                }, 2000);
            });
        };

        elBtnCopyCurl.onclick = async function () {
            try {
                var host = window.location.origin;
                if (openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
                    host = openApiSpec.servers[0].url.replace(/\/$/, '');
                }

                var path = ep.path || '';
                var queryParts = [];
                var paramsList = ep.parameters || [];

                paramsList.forEach(function (rawP) {
                    var p = resolveSchema(rawP);
                    if (!p) return;
                    var pSchema = resolveSchema(p.schema);
                    var val = (pSchema && pSchema.example !== undefined) ? pSchema.example : (p.example !== undefined ? p.example : (p.in === 'path' ? '1' : ''));
                    if (p.in === 'path') {
                        path = path.replace('{' + p.name + '}', encodeURIComponent(val || '1'));
                    } else if (p.in === 'query' && val !== '' && val !== null && val !== undefined) {
                        queryParts.push(encodeURIComponent(p.name) + '=' + encodeURIComponent(val));
                    }
                });

                var queryString = queryParts.length > 0 ? queryParts.join('&') : '';
                var fullPath = path + (queryString ? '?' + queryString : '');

                var bodyJson = '';
                if (ep.method !== 'GET' && ep.method !== 'HEAD') {
                    var reqInfo = extractRequestBodyInfo(ep.requestBody);
                    if (reqInfo && reqInfo.exampleObj && Object.keys(reqInfo.exampleObj).length > 0) {
                        bodyJson = JSON.stringify(reqInfo.exampleObj);
                    }
                }

                var bodyHash = bodyJson ? await sha256(bodyJson) : BODY_HASH_KOSONG;
                var timestamp = new Date().toISOString();
                var nonce = acakHex(16);
                var pubKey = (typeof apiPublic !== 'undefined' && apiPublic) ? apiPublic : 'YOUR_API_PUBLIC';
                var signature = '<HMAC_SHA512_SIGNATURE>';

                if (typeof apiSecret !== 'undefined' && apiSecret) {
                    var canonical = [ep.method, path, queryString, timestamp, nonce, bodyHash, pubKey].join('\n');
                    signature = await hmacSha512(apiSecret, canonical);
                }

                var curlLines = [
                    'curl -X ' + ep.method + " '" + host + fullPath + "'",
                    "-H 'Accept: application/json'",
                    "-H 'X-HC-Public: " + pubKey + "'",
                    "-H 'X-HC-Timestamp: " + timestamp + "'",
                    "-H 'X-HC-Nonce: " + nonce + "'",
                    "-H 'X-HC-Body-Hash: " + bodyHash + "'",
                    "-H 'X-HC-Signature: " + signature + "'"
                ];

                if (bodyJson) {
                    curlLines.splice(2, 0, "-H 'Content-Type: application/json'");
                    curlLines.push("--data-raw '" + bodyJson.replace(/'/g, "'\\''") + "'");
                }

                var fullCurlCmd = curlLines.join(' \\\n  ');
                navigator.clipboard.writeText(fullCurlCmd).then(function () {
                    elBtnCopyCurl.classList.add('copied');
                    var span = elBtnCopyCurl.querySelector('span');
                    if (span) span.textContent = 'Tersalin!';
                    showToast('Perintah cURL berhasil disalin!');
                    setTimeout(function () {
                        elBtnCopyCurl.classList.remove('copied');
                        if (span) span.textContent = 'Salin cURL';
                    }, 2000);
                }).catch(function (err) {
                    showToast('Gagal menyalin perintah cURL.');
                });
            } catch (err) {
                console.error('Error generating/copying cURL:', err);
                showToast('Gagal menyalin cURL: ' + (err.message || 'terjadi kesalahan'));
            }
        };
    }

    // ── Setup Tryout Form ─────────────────────────────────────────────
    function setupTryoutForm(ep) {
        elLiveResponseFrame.style.display = 'none';

        // 1. Header Information
        elTryoutHeaderMethodPill.textContent = ep.method;
        elTryoutHeaderMethodPill.className = 'method-tag tag-' + ep.method.toLowerCase();
        elTryoutHeaderPathText.textContent = ep.path;

        // Reset Mode to 'form'
        tryoutActiveMode = 'form';
        elBtnTryoutModeForm.classList.add('active');
        elBtnTryoutModeRaw.classList.remove('active');
        elTryoutRawJsonContainer.style.display = 'none';

        // 2. Auth Status & Inline Secret Handling
        if (!apiSecret) {
            elTryoutAuthNotice.style.display = 'flex';
            elTryoutInlineSecretInput.value = '';
        } else {
            elTryoutAuthNotice.style.display = 'none';
        }

        elBtnTryoutToggleSecretVisibility.onclick = function () {
            var isPass = elTryoutInlineSecretInput.type === 'password';
            elTryoutInlineSecretInput.type = isPass ? 'text' : 'password';
        };

        elBtnTryoutSaveSecret.onclick = function () {
            var secretVal = elTryoutInlineSecretInput.value.trim();
            if (!secretVal) {
                showToast('Masukkan Api_Secret Anda terlebih dahulu.');
                return;
            }
            apiSecret = secretVal;
            localStorage.setItem('hcis_api_secret', secretVal);
            if (elInputApiSecret) elInputApiSecret.value = secretVal;
            if (elLabelKeyMasked) elLabelKeyMasked.textContent = '••••••••' + (secretVal.length > 4 ? secretVal.slice(-4) : '');
            elTryoutAuthNotice.style.display = 'none';
            showToast('Api_Secret berhasil disimpan dan diaktifkan!');
            updateHmacLivePreview();
        };

        // 3. URL & Query Parameters Form
        var params = ep.parameters || [];
        if (params.length > 0) {
            elTryoutParamsFormGroup.style.display = 'block';
            elTryoutParamsGrid.innerHTML = params.map(function (rawP) {
                var p = resolveSchema(rawP);
                var pSchema = resolveSchema(p.schema);
                var isReq = p.required === true;
                var pType = (pSchema && pSchema.type) ? pSchema.type : (p.type || 'string');
                var val = (pSchema && pSchema.example !== undefined) ? pSchema.example : (p.example !== undefined ? p.example : (isReq && p.in === 'path' ? '1' : ''));
                
                return '<div class="tryout-field-item">' +
                    '<div class="tryout-field-label-row">' +
                        '<span class="tryout-field-title">' + escapeHtml(p.name) + '</span>' +
                        '<div class="tryout-field-meta">' +
                            (isReq ? '<span class="pill-badge pill-required">Wajib</span>' : '<span class="pill-badge pill-optional">Opsional</span>') +
                            '<span class="field-in-pill">in: ' + escapeHtml(p.in || 'query') + '</span>' +
                            '<span class="field-type-pill">' + escapeHtml(pType) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<input type="text" class="field-input tryout-param-field" data-param-name="' + escapeHtml(p.name) + '" data-param-in="' + escapeHtml(p.in || 'query') + '" value="' + escapeHtml(String(val)) + '" placeholder="Masukkan ' + escapeHtml(p.name) + '...">' +
                '</div>';
            }).join('');
        } else {
            elTryoutParamsFormGroup.style.display = 'none';
            elTryoutParamsGrid.innerHTML = '';
        }

        // 4. Request Body Form Fields
        var reqInfo = extractRequestBodyInfo(ep.requestBody);
        var bodyProperties = (reqInfo && reqInfo.schema && reqInfo.schema.properties) ? reqInfo.schema.properties : null;
        
        if (bodyProperties && Object.keys(bodyProperties).length > 0) {
            elTryoutBodyFormGroup.style.display = 'block';
            var requiredList = reqInfo.schema.required || [];
            var propKeys = Object.keys(bodyProperties);
            elTryoutBodyCountBadge.textContent = propKeys.length;
            var fieldsHtml = [];

            for (var i = 0; i < propKeys.length; i++) {
                var propKey = propKeys[i];
                var prop = resolveSchema(bodyProperties[propKey]);
                var isReq = requiredList.indexOf(propKey) !== -1;
                var pType = prop.type || 'string';
                var val = (reqInfo.exampleObj && reqInfo.exampleObj[propKey] !== undefined) ? reqInfo.exampleObj[propKey] : (prop.example !== undefined ? prop.example : '');
                var isLong = propKey === 'keperluan' || (prop.maxLength && prop.maxLength > 150);

                // Tipe deklarasi IKUT disimpan di DOM. Tanpa itu setiap isian
                // terkirim sebagai string, dan properti bertipe array/object
                // ditolak server dengan "must be an array" — galat yang terlihat
                // seperti salah payload padahal formulirnyalah yang kehilangan tipe.
                var isJamak = pType === 'array' || pType === 'object';
                var tampil = nilaiTampilanField(val);

                var inputHtml = (isLong || isJamak)
                    ? '<textarea class="field-input tryout-body-field" data-field="' + escapeHtml(propKey) + '" data-field-type="' + escapeHtml(pType) + '" placeholder="Masukkan ' + escapeHtml(propKey) + '...">' + escapeHtml(tampil) + '</textarea>'
                    : '<input type="text" class="field-input tryout-body-field" data-field="' + escapeHtml(propKey) + '" data-field-type="' + escapeHtml(pType) + '" value="' + escapeHtml(tampil) + '" placeholder="Masukkan ' + escapeHtml(propKey) + '...">';

                fieldsHtml.push(
                    '<div class="tryout-field-item"' + (isLong ? ' style="grid-column: 1 / -1;"' : '') + '>' +
                        '<div class="tryout-field-label-row">' +
                            '<span class="tryout-field-title">' + escapeHtml(propKey) + '</span>' +
                            '<div class="tryout-field-meta">' +
                                (isReq ? '<span class="pill-badge pill-required">Wajib</span>' : '<span class="pill-badge pill-optional">Opsional</span>') +
                                '<span class="field-type-pill">' + escapeHtml(pType) + (prop.maxLength ? ' (max ' + prop.maxLength + ')' : '') + '</span>' +
                            '</div>' +
                        '</div>' +
                        inputHtml +
                    '</div>'
                );
            }

            elTryoutBodyGrid.innerHTML = fieldsHtml.join('');
            elTryoutRawJsonTextarea.value = JSON.stringify(reqInfo.exampleObj || {}, null, 2);
        } else {
            elTryoutBodyFormGroup.style.display = 'none';
            elTryoutBodyCountBadge.textContent = '0';
            elTryoutBodyGrid.innerHTML = '';
            elTryoutRawJsonTextarea.value = '';
        }

        // 5. Live HMAC Calculator Function
        async function updateHmacLivePreview() {
            var host = window.location.origin;
            if (openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
                host = openApiSpec.servers[0].url.replace(/\/$/, '');
            }

            var path = ep.path;
            var queryParams = [];

            document.querySelectorAll('.tryout-param-field').forEach(function (inp) {
                var pName = inp.getAttribute('data-param-name');
                var pIn = inp.getAttribute('data-param-in');
                var val = inp.value.trim();
                if (pIn === 'path') {
                    path = path.replace('{' + pName + '}', encodeURIComponent(val || '1'));
                } else if (pIn === 'query' && val !== '') {
                    queryParams.push(encodeURIComponent(pName) + '=' + encodeURIComponent(val));
                }
            });

            var queryString = queryParams.join('&');

            var payload = null;
            var bodyString = '';

            if (tryoutActiveMode === 'raw') {
                bodyString = elTryoutRawJsonTextarea.value.trim();
            } else {
                try {
                    payload = kumpulkanBodyForm();
                    bodyString = payload ? JSON.stringify(payload) : '';
                } catch (e) {
                    payload = null;
                    bodyString = '';
                }
            }

            var bodyHash = bodyString ? await sha256(bodyString) : BODY_HASH_KOSONG;
            var timestamp = new Date().toISOString();
            var nonce = acakHex(16);
            var pubKey = apiPublic || 'YOUR_API_PUBLIC';
            var signature = '[Menunggu Api_Secret]';

            if (apiSecret) {
                var canonical = [ep.method, path, queryString, timestamp, nonce, bodyHash, pubKey].join('\n');
                signature = await hmacSha512(apiSecret, canonical);
            }

            elPreviewHmacPublic.textContent = pubKey;
            elPreviewHmacTimestamp.textContent = timestamp;
            elPreviewHmacNonce.textContent = nonce;
            elPreviewHmacBodyHash.textContent = bodyHash;
            elPreviewHmacSignature.textContent = signature;
        }

        // 6. Toolbar & Control Handlers
        elBtnTryoutFillExample.onclick = function () {
            if (params.length > 0) {
                document.querySelectorAll('.tryout-param-field').forEach(function (inp) {
                    var pName = inp.getAttribute('data-param-name');
                    var targetP = params.find(function (x) { return x.name === pName; });
                    if (targetP) {
                        var pSchema = resolveSchema(targetP.schema);
                        var val = (pSchema && pSchema.example !== undefined) ? pSchema.example : (targetP.example !== undefined ? targetP.example : (targetP.in === 'path' ? '1' : ''));
                        inp.value = String(val);
                    }
                });
            }

            if (reqInfo && reqInfo.exampleObj) {
                document.querySelectorAll('.tryout-body-field').forEach(function (inp) {
                    var field = inp.getAttribute('data-field');
                    if (reqInfo.exampleObj[field] !== undefined) {
                        inp.value = nilaiTampilanField(reqInfo.exampleObj[field]);
                    }
                });
                elTryoutRawJsonTextarea.value = JSON.stringify(reqInfo.exampleObj, null, 2);
                elTryoutJsonStatusPill.textContent = '✓ JSON Valid';
                elTryoutJsonStatusPill.className = 'res-status-tag res-status-2xx';
            }

            updateHmacLivePreview();
            showToast('Data contoh berhasil dimuat!');
        };

        elBtnTryoutClearForm.onclick = function () {
            document.querySelectorAll('.tryout-param-field').forEach(function (inp) {
                if (inp.getAttribute('data-param-in') === 'path') {
                    inp.value = '1';
                } else {
                    inp.value = '';
                }
            });
            document.querySelectorAll('.tryout-body-field').forEach(function (inp) {
                inp.value = '';
            });
            elTryoutRawJsonTextarea.value = '{}';
            updateHmacLivePreview();
            showToast('Form telah dikosongkan.');
        };

        elBtnTryoutModeForm.onclick = function () {
            if (tryoutActiveMode === 'raw') {
                try {
                    var parsed = JSON.parse(elTryoutRawJsonTextarea.value.trim() || '{}');
                    document.querySelectorAll('.tryout-body-field').forEach(function (inp) {
                        var field = inp.getAttribute('data-field');
                        if (parsed[field] !== undefined) {
                            inp.value = nilaiTampilanField(parsed[field]);
                        }
                    });
                } catch (err) {
                    // Ignore parse error on switch
                }
            }
            tryoutActiveMode = 'form';
            elBtnTryoutModeForm.classList.add('active');
            elBtnTryoutModeRaw.classList.remove('active');
            if (bodyProperties && Object.keys(bodyProperties).length > 0) {
                elTryoutBodyFormGroup.style.display = 'block';
            }
            elTryoutRawJsonContainer.style.display = 'none';
            updateHmacLivePreview();
        };

        elBtnTryoutModeRaw.onclick = function () {
            if (tryoutActiveMode === 'form') {
                var payload;

                try {
                    payload = kumpulkanBodyForm() || {};
                } catch (e) {
                    showToast(e.message);

                    return;
                }

                elTryoutRawJsonTextarea.value = JSON.stringify(payload, null, 2);
                elTryoutJsonStatusPill.textContent = '✓ JSON Valid';
                elTryoutJsonStatusPill.className = 'res-status-tag res-status-2xx';
            }
            tryoutActiveMode = 'raw';
            elBtnTryoutModeRaw.classList.add('active');
            elBtnTryoutModeForm.classList.remove('active');
            elTryoutBodyFormGroup.style.display = 'none';
            elTryoutRawJsonContainer.style.display = 'block';
            updateHmacLivePreview();
        };

        elTryoutRawJsonTextarea.oninput = function () {
            try {
                JSON.parse(elTryoutRawJsonTextarea.value);
                elTryoutJsonStatusPill.textContent = '✓ JSON Valid';
                elTryoutJsonStatusPill.className = 'res-status-tag res-status-2xx';
            } catch (e) {
                elTryoutJsonStatusPill.textContent = '⚠️ Format JSON Tidak Valid';
                elTryoutJsonStatusPill.className = 'res-status-tag res-status-err';
            }
            updateHmacLivePreview();
        };

        elBtnTryoutFormatJson.onclick = function () {
            try {
                var parsed = JSON.parse(elTryoutRawJsonTextarea.value);
                elTryoutRawJsonTextarea.value = JSON.stringify(parsed, null, 2);
                elTryoutJsonStatusPill.textContent = '✓ JSON Valid';
                elTryoutJsonStatusPill.className = 'res-status-tag res-status-2xx';
                showToast('JSON berhasil diformat!');
            } catch (e) {
                showToast('Format JSON belum valid, tidak dapat diformat.');
            }
        };

        elBtnToggleHmacPreview.onclick = function () {
            isHmacDrawerExpanded = !isHmacDrawerExpanded;
            elTryoutHmacDrawerContent.style.display = isHmacDrawerExpanded ? 'block' : 'none';
            elIconHmacCaret.style.transform = isHmacDrawerExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
            if (isHmacDrawerExpanded) {
                updateHmacLivePreview();
            }
        };

        // Attach change listeners to fields
        document.querySelectorAll('.tryout-param-field, .tryout-body-field').forEach(function (inp) {
            inp.oninput = function () {
                updateHmacLivePreview();
            };
        });

        // 7. Copy Current cURL Form Handler
        elBtnTryoutCopyCurl.onclick = async function () {
            var host = window.location.origin;
            if (openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
                host = openApiSpec.servers[0].url.replace(/\/$/, '');
            }

            var path = ep.path;
            var queryParams = [];

            document.querySelectorAll('.tryout-param-field').forEach(function (inp) {
                var pName = inp.getAttribute('data-param-name');
                var pIn = inp.getAttribute('data-param-in');
                var val = inp.value.trim();
                if (pIn === 'path') {
                    path = path.replace('{' + pName + '}', encodeURIComponent(val || '1'));
                } else if (pIn === 'query' && val !== '') {
                    queryParams.push(encodeURIComponent(pName) + '=' + encodeURIComponent(val));
                }
            });

            var queryString = queryParams.join('&');

            var bodyJson = '';
            if (tryoutActiveMode === 'raw') {
                bodyJson = elTryoutRawJsonTextarea.value.trim();
            } else {
                var payloadCurl;

                try {
                    payloadCurl = kumpulkanBodyForm();
                } catch (e) {
                    showToast(e.message);

                    return;
                }

                if (payloadCurl) {
                    bodyJson = JSON.stringify(payloadCurl, null, 2);
                }
            }

            var bodyHash = bodyJson ? await sha256(bodyJson) : BODY_HASH_KOSONG;
            var timestamp = new Date().toISOString();
            var nonce = acakHex(16);
            var pubKey = apiPublic || 'YOUR_API_PUBLIC';
            var signature = '<HMAC_SHA512_SIGNATURE>';

            if (apiSecret) {
                var canonical = [ep.method, path, queryString, timestamp, nonce, bodyHash, pubKey].join('\n');
                signature = await hmacSha512(apiSecret, canonical);
            }

            var fullPath = path + (queryString ? '?' + queryString : '');
            var curlLines = [
                'curl -X ' + ep.method + " '" + host + fullPath + "'",
                "-H 'Accept: application/json'",
                "-H 'X-HC-Public: " + pubKey + "'",
                "-H 'X-HC-Timestamp: " + timestamp + "'",
                "-H 'X-HC-Nonce: " + nonce + "'",
                "-H 'X-HC-Body-Hash: " + bodyHash + "'",
                "-H 'X-HC-Signature: " + signature + "'"
            ];

            if (bodyJson) {
                curlLines.splice(2, 0, "-H 'Content-Type: application/json'");
                curlLines.push("--data-raw '" + bodyJson.replace(/'/g, "'\\''") + "'");
            }

            navigator.clipboard.writeText(curlLines.join(' \\\n  ')).then(function () {
                elBtnTryoutCopyCurl.classList.add('copied');
                elBtnTryoutCopyCurl.querySelector('span').textContent = 'Tersalin!';
                showToast('cURL dengan nilai form berhasil disalin!');
                setTimeout(function () {
                    elBtnTryoutCopyCurl.classList.remove('copied');
                    elBtnTryoutCopyCurl.querySelector('span').textContent = 'Salin cURL Form Ini';
                }, 2000);
            });
        };

        // Initial preview calculation
        updateHmacLivePreview();

        // 8. Execute Button Handler
        elBtnExecuteRequest.onclick = function () {
            // Promise-nya WAJIB ditangkap. Tanpa .catch(), satu galat di dalam
            // jalankanPermintaanLive() menjadi unhandled rejection: spinner terus
            // berputar, tombol terkunci, dan tidak ada pesan apa pun di layar —
            // gejala yang menyamar sebagai "server tidak merespons".
            jalankanPermintaanLive(ep).catch(function (e) {
                bebaskanTombolExecute();
                showToast('Gagal menjalankan permintaan: ' + (e && e.message ? e.message : e));
            });
        };
    }

    // ── Perakit Body Formulir ─────────────────────────────────────────
    /**
     * Nilai untuk DITAMPILKAN di kotak isian.
     *
     * String(nilai) pada array/objek menghasilkan "[object Object]" — isian yang
     * mustahil dibetulkan pengguna dan pasti ditolak server.
     */
    function nilaiTampilanField(val) {
        if (val === null || val === undefined) {
            return '';
        }

        return typeof val === 'object' ? JSON.stringify(val) : String(val);
    }

    /**
     * Nilai untuk DIKIRIM, dikembalikan ke tipe aslinya menurut skema.
     *
     * Formulir HTML hanya tahu string; skema yang tahu bedanya array, angka, dan
     * boolean. Tanpa pemulihan tipe di sini, satu-satunya cara mitra mengirim
     * rombongan adalah lewat mode JSON mentah.
     */
    function bacaNilaiField(inp) {
        var teks = inp.value.trim();
        var tipe = inp.getAttribute('data-field-type') || 'string';

        if (teks === '') {
            return null;
        }

        if (tipe === 'array' || tipe === 'object') {
            // Sengaja dibiarkan melempar: JSON separuh jadi harus terbaca sebagai
            // galat yang menyebut field-nya, bukan terkirim diam-diam sebagai string.
            return JSON.parse(teks);
        }

        if (tipe === 'integer' || tipe === 'number') {
            var angka = Number(teks);

            return isNaN(angka) ? teks : angka;
        }

        if (tipe === 'boolean') {
            return teks === 'true' || teks === '1';
        }

        return inp.value;
    }

    /** @return {Object|null} payload formulir, atau null bila endpoint tanpa body. */
    function kumpulkanBodyForm() {
        var bodyFields = document.querySelectorAll('.tryout-body-field');

        if (bodyFields.length === 0) {
            return null;
        }

        var payload = {};

        bodyFields.forEach(function (inp) {
            var field = inp.getAttribute('data-field');

            try {
                payload[field] = bacaNilaiField(inp);
            } catch (e) {
                throw new Error('Isian "' + field + '" bukan JSON yang sah: ' + e.message);
            }
        });

        return payload;
    }

    // ── Execute Live Request ──────────────────────────────────────────
    function bebaskanTombolExecute() {
        if (elBtnExecuteRequest) {
            elBtnExecuteRequest.disabled = false;
        }

        if (elExecuteSpinner) {
            elExecuteSpinner.style.display = 'none';
        }
    }

    async function jalankanPermintaanLive(ep) {
        if (!apiPublic) {
            showToast('Api_Public wajib diisi.');
            return;
        }

        if (!apiSecret) {
            showToast('Api_Secret diperlukan untuk menandatangani request HMAC-SHA512. Masukkan secret pada form di atas.');
            return;
        }

        var host = window.location.origin;
        if (openApiSpec.servers && openApiSpec.servers[0] && openApiSpec.servers[0].url) {
            host = openApiSpec.servers[0].url.replace(/\/$/, '');
        }

        var path = ep.path;
        var queryParams = [];

        // Collect parameters
        document.querySelectorAll('.tryout-param-field').forEach(function (inp) {
            var pName = inp.getAttribute('data-param-name');
            var pIn = inp.getAttribute('data-param-in');
            var val = inp.value.trim();
            if (pIn === 'path') {
                path = path.replace('{' + pName + '}', encodeURIComponent(val || '1'));
            } else if (pIn === 'query' && val !== '') {
                queryParams.push(encodeURIComponent(pName) + '=' + encodeURIComponent(val));
            }
        });

        var queryString = queryParams.join('&');

        // Collect request body
        var bodyString = '';
        if (tryoutActiveMode === 'raw') {
            bodyString = elTryoutRawJsonTextarea.value.trim();
            if (bodyString) {
                try {
                    JSON.parse(bodyString);
                } catch (err) {
                    showToast('Format JSON pada editor tidak valid: ' + err.message);
                    return;
                }
            }
        } else {
            var payloadKirim;

            try {
                payloadKirim = kumpulkanBodyForm();
            } catch (e) {
                showToast(e.message);

                return;
            }

            if (payloadKirim) {
                bodyString = JSON.stringify(payloadKirim);
            }
        }

        elBtnExecuteRequest.disabled = true;
        elExecuteSpinner.style.display = 'inline-block';
        elLiveResponseFrame.style.display = 'none';

        // Penandatanganan memakai Web Crypto, yang hanya tersedia pada konteks
        // aman (HTTPS atau localhost). Di luar itu window.crypto.subtle bernilai
        // undefined dan baris di bawah melempar — dulu galat itu hilang tanpa
        // jejak karena tidak ada yang menangkapnya.
        var bodyHash, timestamp, nonce, canonical, signature;

        try {
            if (!window.crypto || !window.crypto.subtle) {
                throw new Error('Web Crypto tidak tersedia di konteks ini; halaman harus dibuka lewat HTTPS atau localhost.');
            }

            bodyHash = bodyString ? await sha256(bodyString) : BODY_HASH_KOSONG;
            timestamp = new Date().toISOString();
            nonce = acakHex(16);

            canonical = [ep.method, path, queryString, timestamp, nonce, bodyHash, apiPublic].join('\n');
            signature = await hmacSha512(apiSecret, canonical);
        } catch (e) {
            bebaskanTombolExecute();
            showToast('Gagal menandatangani permintaan: ' + (e && e.message ? e.message : e));
            return;
        }

        var requestUrl = host + path + (queryString ? '?' + queryString : '');

        var headers = {
            'Accept': 'application/json',
            'X-HC-Public': apiPublic,
            'X-HC-Timestamp': timestamp,
            'X-HC-Nonce': nonce,
            'X-HC-Body-Hash': bodyHash,
            'X-HC-Signature': signature
        };

        if (bodyString) {
            headers['Content-Type'] = 'application/json';
        }

        var tStart = performance.now();
        var res = null;
        var resData = null;
        var rawText = '';
        var tEnd = 0;

        // Batas waktu keras. Permintaan yang tidak pernah dijawab — koneksi
        // menggantung, pekerja web habis, jaringan putus di tengah — harus
        // menyerah dengan pesan, bukan memutar spinner tanpa akhir.
        var pembatal = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var pewaktu = pembatal ? setTimeout(function () { pembatal.abort(); }, 60000) : null;

        try {
            res = await fetch(requestUrl, {
                method: ep.method,
                headers: headers,
                signal: pembatal ? pembatal.signal : undefined,
                body: ['POST', 'PUT', 'PATCH'].includes(ep.method) && bodyString ? bodyString : undefined
            });
            tEnd = performance.now();
            rawText = await res.text();
            try {
                resData = JSON.parse(rawText);
            } catch (e) {
                resData = rawText;
            }
        } catch (e) {
            tEnd = performance.now();
            bebaskanTombolExecute();
            showToast(
                e && e.name === 'AbortError'
                    ? 'Permintaan dibatalkan setelah 60 detik tanpa jawaban dari server.'
                    : 'Gagal mengirim request: ' + (e && e.message ? e.message : e)
            );
            return;
        } finally {
            if (pewaktu) {
                clearTimeout(pewaktu);
            }
        }

        bebaskanTombolExecute();

        // Render Live Response Frame
        elLiveResponseFrame.style.display = 'block';
        var latency = Math.round(tEnd - tStart);
        if (elLiveLatencyTag) {
            var valSpan = elLiveLatencyTag.querySelector('.metric-val');
            if (valSpan) valSpan.textContent = latency + ' ms';
        }
        if (elLiveSizeTag) {
            var valSpan = elLiveSizeTag.querySelector('.metric-val');
            if (valSpan) valSpan.textContent = formatBytes(new Blob([rawText]).size);
        }
        if (elLiveTimestampTag) {
            var now = new Date();
            var timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0') + ' WIB';
            var valSpan = elLiveTimestampTag.querySelector('.metric-val');
            if (valSpan) valSpan.textContent = timeStr;
        }

        var statusText = res.status + ' ' + (res.statusText || (res.ok ? 'OK' : 'Error'));
        if (elLiveStatusBadge) {
            elLiveStatusBadge.textContent = statusText;
            var pillClass = res.status >= 200 && res.status < 300 ? 'pill-2xx' : (res.status >= 500 ? 'pill-5xx' : 'pill-4xx');
            elLiveStatusBadge.className = 'live-status-pill ' + pillClass;
        }

        if (typeof resData === 'object' && resData !== null) {
            liveJsonTreeLines = buildJsonTree(resData);
            liveRawResponseString = JSON.stringify(resData, null, 2);
        } else {
            liveJsonTreeLines = [{
                type: 'primitive',
                depth: 0,
                parentNodeId: null,
                html: '<span class="json-string">' + escapeHtml(String(resData)) + '</span>',
                raw: String(resData)
            }];
            liveRawResponseString = String(resData);
        }

        liveSearchFilter = '';
        if (elLiveInputResFilter) elLiveInputResFilter.value = '';
        if (elLiveResStatsText) {
            var lineCount = liveRawResponseString.split('\n').length;
            var byteSize = new Blob([rawText]).size;
            elLiveResStatsText.textContent = lineCount + ' baris · ' + formatBytes(byteSize);
        }

        renderLiveTreeDOM();

        // Smooth scroll to live response
        elLiveResponseFrame.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Subtab Switcher (4 Subtabs) ──────────────────────────────────
    function setSubtab(tab) {
        currentActiveSubtab = tab;
        if (elSubtabBtnDesc) elSubtabBtnDesc.classList.toggle('active', tab === 'desc');
        if (elSubtabBtnParams) elSubtabBtnParams.classList.toggle('active', tab === 'params');
        if (elSubtabBtnCode) elSubtabBtnCode.classList.toggle('active', tab === 'code');
        if (elSubtabBtnTryout) elSubtabBtnTryout.classList.toggle('active', tab === 'tryout');

        if (elSubtabContentDesc) elSubtabContentDesc.style.display = tab === 'desc' ? 'block' : 'none';
        if (elSubtabContentParams) elSubtabContentParams.style.display = tab === 'params' ? 'block' : 'none';
        if (elSubtabContentCode) elSubtabContentCode.style.display = tab === 'code' ? 'block' : 'none';
        if (elSubtabContentTryout) elSubtabContentTryout.style.display = tab === 'tryout' ? 'block' : 'none';

        if (tab === 'code' && currentSelectedEndpoint) {
            renderEndpointCodeSnippets(currentSelectedEndpoint);
        }
    }

    if (elSubtabBtnDesc) elSubtabBtnDesc.addEventListener('click', function () { setSubtab('desc'); });
    if (elSubtabBtnParams) elSubtabBtnParams.addEventListener('click', function () { setSubtab('params'); });
    if (elSubtabBtnCode) elSubtabBtnCode.addEventListener('click', function () { setSubtab('code'); });
    if (elSubtabBtnTryout) elSubtabBtnTryout.addEventListener('click', function () { setSubtab('tryout'); });

    // ── Main Top Tab Switcher (Endpoint vs Dokumentasi) ───────────────
    function setTopTab(tab) {
        currentActiveTab = tab;
        elBtnNavEndpoints.classList.toggle('active', tab === 'endpoints');
        elBtnNavDocs.classList.toggle('active', tab === 'docs');
        elTabContentEndpoints.style.display = tab === 'endpoints' ? 'grid' : 'none';
        elTabContentDocs.style.display = tab === 'docs' ? 'flex' : 'none';
        if (tab === 'docs' && openApiSpec) {
            renderDocumentationTab(openApiSpec);
        }
    }

    elBtnNavEndpoints.addEventListener('click', function () { setTopTab('endpoints'); });
    elBtnNavDocs.addEventListener('click', function () { setTopTab('docs'); });

    // ── Sidebar Search ────────────────────────────────────────────────
    elSidebarSearchInput.addEventListener('input', function () {
        var val = elSidebarSearchInput.value;
        elBtnSearchClear.style.display = val ? 'block' : 'none';
        renderSidebar(sidebarItems, val);
    });

    elBtnSearchClear.addEventListener('click', function () {
        elSidebarSearchInput.value = '';
        elBtnSearchClear.style.display = 'none';
        renderSidebar(sidebarItems, '');
    });

    // ── Load Documentation from Server ────────────────────────────────
    async function muatDokumentasi() {
        apiPublic = elInputApiPublic.value.trim();
        apiSecret = elInputApiSecret.value;

        elGateErrorBanner.style.display = 'none';

        if (!apiPublic) {
            tampilkanGalatGate('Api_Public wajib diisi.');
            return;
        }

        elBtnSubmitGate.disabled = true;
        elBtnGateLabel.textContent = 'Memverifikasi Kunci...';
        elBtnGateSpinner.style.display = 'inline-block';

        var respons;
        try {
            respons = await fetch('/api/docs/openapi.json', {
                headers: { 'X-HC-Public': apiPublic, 'Accept': 'application/json' }
            });
        } catch (e) {
            elBtnSubmitGate.disabled = false;
            elBtnGateLabel.textContent = 'Buka Dokumentasi & Endpoint';
            elBtnGateSpinner.style.display = 'none';
            tampilkanGalatGate('Gagal terhubung ke server: ' + e.message);
            return;
        }

        if (respons.status === 401) {
            elBtnSubmitGate.disabled = false;
            elBtnGateLabel.textContent = 'Buka Dokumentasi & Endpoint';
            elBtnGateSpinner.style.display = 'none';
            tampilkanGalatGate('Kunci publik tidak dikenal. Masukkan Api_Public resmi yang diberikan oleh tim HCIS.');
            return;
        }

        if (!respons.ok) {
            elBtnSubmitGate.disabled = false;
            elBtnGateLabel.textContent = 'Buka Dokumentasi & Endpoint';
            elBtnGateSpinner.style.display = 'none';
            tampilkanGalatGate('Server mengembalikan kode status ' + respons.status + '.');
            return;
        }

        var spec = await respons.json();
        openApiSpec = spec;
        sidebarItems = parseOpenApi(spec);

        if (sidebarItems.length === 0) {
            elBtnSubmitGate.disabled = false;
            elBtnGateLabel.textContent = 'Buka Dokumentasi & Endpoint';
            elBtnGateSpinner.style.display = 'none';
            tampilkanGalatGate('Kunci publik terdaftar, tetapi belum ada endpoint yang diotorisasi untuk akun Anda.');
            return;
        }

        // Tampilkan Tampilan Utama
        elViewGate.style.display = 'none';
        elViewDocsMain.style.display = 'block';
        elTopNavTabs.style.display = 'inline-flex';
        elKeyInfoPill.style.display = 'inline-flex';
        elBtnGantiKunci.style.display = 'inline-flex';

        // Set Info Key
        var masked = apiPublic.length > 10 ? apiPublic.slice(0, 6) + '...' + apiPublic.slice(-4) : apiPublic;
        elLabelKeyMasked.textContent = masked;
        var tagCode = apiPublic.split('_')[0] || 'B2B';
        elLabelKeyCode.textContent = tagCode;

        // Render Documentation Tab
        renderDocumentationTab(spec);

        // Render Sidebar
        renderSidebar(sidebarItems, '');

        // Auto select first endpoint
        if (sidebarItems.length > 0) {
            pilihEndpoint(sidebarItems[0]);
        }
    }

    function tampilkanGalatGate(pesan) {
        elGateErrorText.textContent = pesan;
        elGateErrorBanner.style.display = 'flex';
    }

    function gantiKunci() {
        apiPublic = '';
        apiSecret = '';
        openApiSpec = null;
        sidebarItems = [];
        currentEndpointKey = null;

        elInputApiSecret.value = '';
        elViewDocsMain.style.display = 'none';
        elTopNavTabs.style.display = 'none';
        elKeyInfoPill.style.display = 'none';
        elBtnGantiKunci.style.display = 'none';
        elViewGate.style.display = 'block';

        elBtnSubmitGate.disabled = false;
        elBtnGateLabel.textContent = 'Buka Dokumentasi & Endpoint';
        elBtnGateSpinner.style.display = 'none';
    }

    elBtnGantiKunci.addEventListener('click', gantiKunci);

    // Toggle Secret Visibility
    var btnToggleSecret = document.getElementById('btnToggleSecret');
    var iconEye = document.getElementById('iconEye');
    var iconEyeOff = document.getElementById('iconEyeOff');
    btnToggleSecret.addEventListener('click', function () {
        if (elInputApiSecret.type === 'password') {
            elInputApiSecret.type = 'text';
            iconEye.style.display = 'none';
            iconEyeOff.style.display = 'block';
        } else {
            elInputApiSecret.type = 'password';
            iconEye.style.display = 'block';
            iconEyeOff.style.display = 'none';
        }
    });

    // Initialize Response Viewer Interactive Controls
    initResponseViewerControls();
    initLiveResponseViewerControls();
    initEndpointCodeViewerControls();

    window.muatDokumentasi = muatDokumentasi;

})();
</script>

</body>
</html>
