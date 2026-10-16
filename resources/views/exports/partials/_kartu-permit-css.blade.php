{{-- Gaya kartu permit. Dipakai kartu tunggal (t2) MAUPUN lembar batch,
     jadi satu-satunya sumber tata letak. Aturan yang bergantung warna
     kategori SENGAJA netral di sini dan ditimpa style inline per kartu:
     satu lembar batch bisa memuat kartu MERAH dan HIJAU sekaligus, dan
     stylesheet bersama tidak bisa berbeda per kartu. --}}
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
            border: 2mm solid #334155;
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
            background: #334155;
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

        /* ─── 2. MIDDLE ROW: ZONE BADGE (COL-3) & LICENSE PLATE (COL-9) ─── */
        .middle-hero-table {
            width: 100%;
            margin-bottom: 3mm;
            border-collapse: separate;
            border-spacing: 0;
        }

        .zone-hero-cell {
            width: 22%;
            background: #334155;
            border: 1mm solid #334155;
            text-align: center;
            vertical-align: middle;
            padding: 2.5mm 2mm;
        }

        .hero-col-gap {
            width: 3mm;
            min-width: 3mm;
            max-width: 3mm;
            background: transparent;
            border: none;
            padding: 0;
        }

        .plate-hero-cell {
            width: 78%;
            background: #070a12;
            border: 1mm solid #334155;
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
            border-left: 5mm solid #334155;
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
            color: #334155;
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
