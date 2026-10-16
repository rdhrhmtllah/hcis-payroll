{{--
    KARTU AKSES KENDARAAN RESMI (EXECUTIVE HIGH-VISIBILITY SHE INSPECTION PASS)
    Ukuran: A4 PORTRAIT (210mm x 297mm - Setengah Kertas A4 Vertikal / Dashboard Pass)
    Layout: Template 1 — Jenis permit satu bar penuh di atas, plat lebar penuh
    di bawahnya. Tanpa pembagian kolom 3/9: kategori adalah hal pertama yang
    dicari petugas dari jauh, jadi ia mendapat satu baris utuh selebar kartu.
    Render Engine: Dompdf (Pure Table & CSS 2.1 Compatibility, 100% Stable Vector & Tables)
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Permit {{ $kartu['plat'] }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 6mm 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ─── PURE TABLE ARCHITECTURE (100% DOMPDF STABLE & ZERO OVERFLOW) ─── */
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        td {
            vertical-align: top;
            padding: 0;
        }

        /* ─── CARD WRAPPER & BORDERS ─── */
        .card-outer-frame {
            width: 100%;
            border: 2mm solid {{ $kartu['aksen'] }};
            background: #ffffff;
            border-collapse: collapse;
        }

        .card-outer-cell {
            padding: 2.2mm 2.8mm;
            background: #ffffff;
        }

        .card-inner-frame {
            width: 100%;
            border: 0.45mm solid #cbd5e1;
            background: #ffffff;
            border-collapse: collapse;
        }

        .card-inner-cell {
            padding: 3mm 3.5mm;
            background: #ffffff;
        }

        /* ─── 1. TOP HEADER ROW (BRAND & BADGES) ─── */
        .kop-table {
            border-bottom: 0.45mm solid #e2e8f0;
            padding-bottom: 1.8mm;
            margin-bottom: 3mm;
        }

        .kop-brand {
            font-size: 14.5pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .kop-sub {
            font-size: 7.8pt;
            font-weight: bold;
            color: #64748b;
            letter-spacing: 0.6pt;
            text-transform: uppercase;
            margin-top: 0.4mm;
        }

        .kop-badge-cell {
            text-align: right;
            vertical-align: middle;
            white-space: nowrap;
        }

        .kop-notch-pill {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
            padding: 1.1mm 3.2mm;
            border: 0.3mm solid #334155;
            margin-right: 1.5mm;
            vertical-align: middle;
        }

        .kop-shield-badge {
            display: inline-block;
            background: {{ $kartu['aksen'] }};
            border: 0.3mm solid rgba(0, 0, 0, 0.15);
            padding: 1.1mm 3.8mm;
            text-align: center;
            vertical-align: middle;
        }

        .kop-shield-text {
            font-size: 7.2pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.6pt;
            text-transform: uppercase;
        }

        /* ─── 2. BAR JENIS PERMIT (LEBAR PENUH) & PLAT KENDARAAN ─── */
        .zone-bar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 2.5mm;
        }

        /* Satu bar selebar kartu: kategori dibaca lebih dulu dari jauh. */
        .zone-bar-cell {
            background: {{ $kartu['aksen'] }};
            border: 1mm solid {{ $kartu['aksen'] }};
            text-align: center;
            vertical-align: middle;
            padding: 2.2mm 3mm;
        }

        /* Bertumpuk seperti badge zona: kotak inisial, nama kategori, lalu
           keterangan zonanya — hanya saja melebar satu kartu penuh. */
        .zone-bar-inner {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .zone-bar-initial-cell {
            width: 16mm;
            vertical-align: middle;
        }

        .zone-bar-teks-cell {
            vertical-align: middle;
            padding-left: 3.5mm;
            text-align: left;
        }

        .zone-bar-initial {
            background: #070a12;
            color: #ffffff;
            font-size: 22pt;
            font-weight: bold;
            line-height: 1;
            padding: 1.6mm 0;
            width: 14mm;
            border: 0.45mm solid rgba(255, 255, 255, 0.5);
            text-align: center;
        }

        .zone-bar-title {
            font-size: 19pt;
            font-weight: bold;
            letter-spacing: 3pt;
            text-transform: uppercase;
            color: #ffffff;
            line-height: 1.05;
            text-align: left;
        }

        .zone-bar-sub {
            font-size: 7.6pt;
            font-weight: bold;
            letter-spacing: 1.4pt;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.85);
            text-align: left;
            padding-top: 0.8mm;
        }

        .plate-full-table {
            width: 100%;
            /* Jarak ke blok masa berlaku di bawahnya. Tanpa ini kedua blok
               bersentuhan dan terbaca seolah satu kotak. */
            margin-bottom: 3mm;
            border-collapse: separate;
            border-spacing: 0;
        }

        .plate-hero-cell {
            width: 100%;
            background: #070a12;
            border: 1mm solid {{ $kartu['aksen'] }};
            text-align: center;
            vertical-align: middle;
            padding: 2.5mm 2.5mm 2.2mm;
        }

        .zone-badge-initial-box {
            background: #070a12;
            color: #ffffff;
            font-size: 29pt;
            font-weight: bold;
            line-height: 1;
            padding: 2.2mm 0;
            border: 0.45mm solid rgba(255, 255, 255, 0.5);
            text-align: center;
            margin-bottom: 1.8mm;
        }

        .zone-badge-title {
            font-size: 13.5pt;
            font-weight: bold;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
            color: #ffffff;
            line-height: 1.1;
        }

        .plate-top-label {
            font-size: 6.8pt;
            font-weight: bold;
            color: #94a3b8;
            letter-spacing: 2.2pt;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 0.3mm;
        }

        .plate-number {
            font-size: 43pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 3pt;
            line-height: 1.05;
            text-transform: uppercase;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            padding: 0.3mm 0 0.8mm;
            white-space: nowrap;
        }

        .plate-vehicle-tag {
            background: #1e293b;
            border: 0.3mm solid #334155;
            padding: 0.9mm 3.8mm;
            display: inline-block;
            font-size: 9.2pt;
            font-weight: bold;
            color: #f1f5f9;
            letter-spacing: 0.4pt;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 98%;
        }

        /* ─── 3. BOTTOM ROW: MASA BERLAKU & FULL-SIZE QR CODE (SIDE-BY-SIDE) ─── */
        .validity-split-table {
            width: 100%;
        }

        .validity-card-left {
            width: 100%;
            background: #f8fafc;
            border: 0.45mm solid #cbd5e1;
            border-left: 5mm solid {{ $kartu['aksen'] }};
            padding: 3mm 4mm 2.8mm;
            vertical-align: middle;
        }

        .validity-card-gap {
            width: 3mm;
            min-width: 3mm;
            max-width: 3mm;
        }

        .validity-card-qr {
            width: 136px;
            min-width: 136px;
            max-width: 136px;
            background: transparent;
            border: none;
            padding: 0;
            text-align: right;
            vertical-align: middle;
        }

        .validity-label {
            font-size: 7.8pt;
            font-weight: bold;
            color: #475569;
            letter-spacing: 1pt;
            text-transform: uppercase;
        }

        .validity-date {
            font-size: 33pt;
            font-weight: bold;
            color: {{ $kartu['aksen'] }};
            letter-spacing: 0.5pt;
            line-height: 1.05;
            margin: 0.4mm 0 0.3mm 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            white-space: nowrap;
        }

        .validity-sub {
            font-size: 7.8pt;
            color: #475569;
            margin-top: 0.4mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .validity-sub strong {
            color: #0f172a;
        }

        .validity-meta-tag {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            font-size: 6.8pt;
            font-weight: bold;
            padding: 0.4mm 2mm;
            border-radius: 1mm;
            margin-top: 0.8mm;
            letter-spacing: 0.3pt;
        }

        /* ─── 4. DASHBOARD HALF-A4 CUTTING GUIDE ─── */
        .cut-guide-container {
            margin-top: 5mm;
            text-align: center;
            border-top: 0.45mm dashed #94a3b8;
            padding-top: 2.5mm;
        }

        .cut-guide-text {
            font-size: 7pt;
            font-weight: bold;
            color: #94a3b8;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <!-- ══════════════════════════════════════════════════════════
         EXECUTIVE DASHBOARD PASS (UPPER HALF OF A4 PORTRAIT)
         ══════════════════════════════════════════════════════════ -->
    <table class="card-outer-frame">
        <tr>
            <td class="card-outer-cell">
                <table class="card-inner-frame">
                    <tr>
                        <td class="card-inner-cell">
                            <!-- ══════════════════════════════════════════════════════
                                 1. TOP HEADER ROW (BRAND & SHE BADGES)
                                 ══════════════════════════════════════════════════════ -->
                            <table class="kop-table">
                                <tr>
                                    <td>
                                        <div class="kop-brand">HCIS VEHICLE ACCESS PASS</div>
                                        <div class="kop-sub">{{ $kartu['sub_perusahaan'] }}</div>
                                    </td>
                                    <td class="kop-badge-cell">
                                        <span class="kop-notch-pill">OFFICIAL PASS</span>
                                        <div class="kop-shield-badge">
                                            <span class="kop-shield-text">SHE INSPECTION PASS</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══════════════════════════════════════════════════════
                                 2a. BAR JENIS PERMIT — SATU BARIS PENUH
                                 ══════════════════════════════════════════════════════ -->
                            <table class="zone-bar-table">
                                <tr>
                                    <td class="zone-bar-cell">
                                        {{-- Rata KIRI, satu baris: kotak inisial, lalu nama
                                             kategori, lalu keterangan zonanya — urutan baca
                                             yang sama dengan badge zona pada Template 2. --}}
                                        <table class="zone-bar-inner">
                                            <tr>
                                                <td class="zone-bar-initial-cell">
                                                    <div class="zone-bar-initial">{{ $kartu['zona_initial'] }}</div>
                                                </td>
                                                <td class="zone-bar-teks-cell">
                                                    <div class="zone-bar-title">{{ $kartu['kategori'] }}</div>
                                                    <div class="zone-bar-sub">{{ $kartu['zona_badge'] }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══════════════════════════════════════════════════════
                                 2b. PLAT KENDARAAN — LEBAR PENUH
                                 ══════════════════════════════════════════════════════ -->
                            <table class="plate-full-table">
                                <tr>
                                    <td class="plate-hero-cell">
                                        <div class="plate-top-label">&bull; NOMOR REGISTRASI KENDARAAN &bull;</div>
                                        <div class="plate-number">{{ $kartu['plat'] }}</div>
                                        <div>
                                            <span class="plate-vehicle-tag">{{ $kartu['kendaraan'] }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══════════════════════════════════════════════════════
                                 3. BOTTOM ROW: MASA BERLAKU & FULL-SIZE QR CODE
                                 ══════════════════════════════════════════════════════ -->
                            <table class="validity-split-table">
                                <tr>
                                    <!-- LEFT: MASA BERLAKU HINGGA (EXPANDED TO MEET QR) -->
                                    <td class="validity-card-left">
                                        <div class="validity-label">Masa Berlaku Hingga</div>
                                        <div class="validity-date">{{ $kartu['sampai'] }}</div>
                                        <div class="validity-sub">
                                            Berlaku Sejak: <strong>{{ $kartu['sejak'] }}</strong> &bull; {{ $kartu['tipe_kendaraan'] }}
                                        </div>
                                        <div class="validity-meta-tag">
                                            NO. PERMIT: {{ $kartu['no_transaksi'] }} &bull; SERI: {{ $kartu['seri'] }}
                                        </div>
                                    </td>

                                    <!-- GAP -->
                                    <td class="validity-card-gap"></td>

                                    <!-- RIGHT: DEDICATED FULL-SIZE QR CODE (FLUSH RIGHT) -->
                                    <td class="validity-card-qr">
                                        @if(!empty($kartu['qr']))
                                            <img src="{{ $kartu['qr'] }}" width="136" height="136" style="display: block; width: 136px; height: 136px; margin: 0 0 0 auto;">
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ══════════════════════════════════════════════════════════
         4. CUTTING / FOLDING GUIDE FOR PHYSICAL DASHBOARD PASS
         ══════════════════════════════════════════════════════════ -->
    <div class="cut-guide-container">
        <div class="cut-guide-text">&bull; &bull; &bull; &nbsp;&nbsp; GARIS POTONG KARTU DASHBOARD SETENGAH A4 (210mm &times; 148.5mm) &nbsp;&nbsp; &bull; &bull; &bull;</div>
    </div>
</body>
</html>
