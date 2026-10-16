<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Executive Report - Performance Appraisal Distribution &amp; Normal Curve</title>
    <style>
        @page {
            margin: 46px 28px 34px 28px;
        }

        * {
            box-sizing: border-box;
            font-family: 'DejaVu Sans', sans-serif;
        }

        body {
            font-size: 8px;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .page-break {
            page-break-before: always;
        }

        /* ===== Header & Footer Fixed (Tampil di halaman laporan 2, 3, 4) ===== */
        .doc-header {
            position: fixed;
            top: -36px;
            left: 0;
            right: 0;
            height: 30px;
            border-bottom: 2px solid #4338ca;
            padding-bottom: 2px;
        }

        .doc-header table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-header td {
            padding: 0;
            border: none;
            vertical-align: middle;
        }

        .doc-header .brand-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1e1b4b;
            letter-spacing: 0.02em;
        }

        .doc-header .sub-brand {
            font-size: 6.8px;
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 1px;
        }

        .doc-header .doc-tag {
            background: #4338ca;
            color: #ffffff;
            font-size: 6.8px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-block;
        }

        .doc-footer {
            position: fixed;
            bottom: -24px;
            left: 0;
            right: 0;
            height: 18px;
            border-top: 1px solid #cbd5e1;
            padding-top: 2px;
            font-size: 6.5px;
            color: #64748b;
        }

        .doc-footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-footer td {
            padding: 0;
            border: none;
            vertical-align: middle;
        }

        .doc-footer .pg-num:after {
            content: "Halaman " counter(page);
            font-weight: bold;
        }

        /* ===== Cover Page Styling ===== */
        .cover-wrapper {
            padding: 25px 5px 5px 5px;
            position: relative;
        }

        .cover-top-badge {
            background: #e0e7ff;
            color: #3730a3;
            font-size: 8px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 10px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 24px;
        }

        .cover-title-box {
            border-left: 5px solid #4338ca;
            padding-left: 14px;
            margin-bottom: 24px;
        }

        .cover-main-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e1b4b;
            line-height: 1.2;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .cover-sub-title {
            font-size: 10.5px;
            color: #4338ca;
            font-weight: bold;
            margin: 0;
            line-height: 1.35;
        }

        .cover-cycle-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }

        .cover-cycle-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 5px 0;
        }

        .cover-cycle-meta {
            font-size: 8.5px;
            color: #475569;
            line-height: 1.5;
        }

        .cover-meta-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .cover-meta-grid td {
            width: 50%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            vertical-align: top;
        }

        .cover-meta-label {
            font-size: 6.8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .cover-meta-val {
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
        }

        /* ===== Section Titles & Boxes ===== */
        .section-box {
            margin-bottom: 9px;
            page-break-inside: avoid;
        }

        .section-header {
            border-left: 3.5px solid #4338ca;
            padding-left: 6px;
            margin-bottom: 5px;
        }

        .section-title {
            font-size: 8.8px;
            font-weight: bold;
            color: #1e1b4b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 0;
        }

        .section-subtitle {
            font-size: 7px;
            color: #64748b;
            margin: 1px 0 0 0;
        }

        /* ===== Hero Banner Inside Report ===== */
        .hero-banner {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 8px;
            margin-bottom: 7px;
        }

        .hero-banner table {
            width: 100%;
            border-collapse: collapse;
        }

        .hero-banner td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .cycle-name {
            font-size: 10.5px;
            font-weight: bold;
            color: #1e1b4b;
            margin: 0 0 2px 0;
        }

        .cycle-meta {
            font-size: 7px;
            color: #475569;
        }

        /* ===== 4 Metric KPI Stat Grid ===== */
        .stat-grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px 0;
            margin-bottom: 7px;
        }

        .stat-grid-table td {
            width: 25%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 4px 5px;
            vertical-align: middle;
            text-align: center;
        }

        .stat-card-label {
            font-size: 6.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 1px;
            text-align: center;
        }

        .stat-card-value {
            font-size: 11.5px;
            font-weight: bold;
            color: #4338ca;
            line-height: 1.15;
            text-align: center;
        }

        .stat-card-desc {
            font-size: 6.2px;
            color: #475569;
            margin-top: 1.5px;
            line-height: 1.2;
            text-align: center;
        }

        /* ===== Executive Narrative Note ===== */
        .narrative-box {
            background: #f1f5f9;
            border-left: 3px solid #3b82f6;
            border-radius: 0 4px 4px 0;
            padding: 4px 7px;
            margin-bottom: 7px;
            font-size: 7.2px;
            color: #334155;
            line-height: 1.35;
        }

        /* ===== Chart Card & Dual Charts Layout ===== */
        .chart-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 6px 4px 6px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        .chart-card-head {
            margin-bottom: 3px;
        }

        .chart-card-head table {
            width: 100%;
            border-collapse: collapse;
        }

        .chart-card-head td {
            padding: 0;
            border: none;
            vertical-align: middle;
        }

        .chart-head-title {
            font-size: 7.5px;
            font-weight: bold;
            color: #1e1b4b;
        }

        .chart-head-sub {
            font-size: 6.3px;
            color: #64748b;
            text-align: right;
        }

        .dual-chart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .dual-chart-table td {
            padding: 0;
            border: none;
            vertical-align: top;
        }

        .chart-img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .chart-legend-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .chart-legend-table td {
            border: none;
            padding: 2.5px 4px;
            font-size: 6.6px;
            color: #475569;
            vertical-align: middle;
        }

        .legend-bullet {
            display: inline-block;
            width: 6.5px;
            height: 6.5px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }

        /* ===== Tables Styling ===== */
        table.exec-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
        }

        table.exec-table thead {
            display: table-header-group;
        }

        table.exec-table th {
            background: #312e81;
            color: #ffffff;
            font-size: 6.8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 3.5px 4px;
            border: 1px solid #312e81;
            text-align: center;
            vertical-align: middle;
        }

        table.exec-table td {
            font-size: 7.2px;
            color: #1e293b;
            padding: 3px 4px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            text-align: center;
        }

        table.exec-table td.text-left {
            text-align: left;
        }

        table.exec-table th.text-left {
            text-align: left;
        }

        table.exec-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        table.exec-table tfoot td {
            background: #eef2ff;
            font-weight: bold;
            border-top: 1.5px solid #4338ca;
            color: #1e1b4b;
        }

        .font-bold { font-weight: bold; }
        .font-mono { font-family: monospace; font-size: 7px; }

        /* ===== Badges & Pills ===== */
        .grade-badge {
            display: inline-block;
            width: 22px;
            padding: 1px 0;
            text-align: center;
            border-radius: 3px;
            font-weight: bold;
            font-size: 7.2px;
            color: #ffffff;
            margin: 0 auto;
        }

        .grade-A      { background: #059669; }
        .grade-B-plus { background: #4f46e5; }
        .grade-B      { background: #0284c7; }
        .grade-C-plus { background: #d97706; }
        .grade-C      { background: #ea580c; }
        .grade-D      { background: #dc2626; }
        .grade-def    { background: #64748b; }

        .status-pill {
            display: inline-block;
            padding: 1px 4.5px;
            border-radius: 3px;
            font-size: 6.5px;
            font-weight: bold;
            white-space: nowrap;
            text-align: center;
        }

        .status-pill-ok {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-pill-info {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .status-pill-warn {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .diff-plus { color: #059669; font-weight: bold; text-align: center; }
        .diff-minus{ color: #dc2626; font-weight: bold; text-align: center; }
        .diff-zero { color: #64748b; text-align: center; }

        .talent-rank {
            display: block;
            width: 13px;
            height: 13px;
            line-height: 13px;
            text-align: center;
            border-radius: 50%;
            background: #4338ca;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: bold;
            margin: 0 auto;
        }

        .talent-rank--danger {
            background: #dc2626;
        }

        /* ===== Mini Progress Track inside Table Cells ===== */
        .cell-mini-track {
            background: #e2e8f0;
            border-radius: 2px;
            height: 3px;
            width: 100%;
            overflow: hidden;
            margin-top: 1px;
        }

        .cell-mini-fill-kpi {
            height: 100%;
            background: #4f46e5;
            border-radius: 2px;
        }

        .cell-mini-fill-komp {
            height: 100%;
            background: #0d9488;
            border-radius: 2px;
        }

        /* ===== Horizontal Grade Comparison Bar ===== */
        .bar-comparison-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .bar-comparison-tbl td {
            padding: 1.5px 4px;
            border: none;
            vertical-align: middle;
            font-size: 7px;
        }

        .bar-track {
            background: #e2e8f0;
            border-radius: 3px;
            height: 7px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .bar-fill-actual {
            height: 100%;
            border-radius: 3px;
        }

        /* ===== Audit Trail Box ===== */
        .audit-trail-box {
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 7px;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .audit-trail-title {
            font-size: 7.2px;
            font-weight: bold;
            color: #1e1b4b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
        }

        .audit-table td {
            padding: 1.5px 3px;
            border: none;
            vertical-align: top;
            font-size: 7px;
        }

        .audit-label {
            display: block;
            font-size: 6.2px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .audit-val {
            color: #0f172a;
            font-weight: bold;
            font-size: 7.2px;
        }
    </style>
</head>
<body>

    @php
        $k = $distribusi['keseluruhan'] ?? [];
        $totalPeserta = (int) ($k['jumlah'] ?? 0);
        $rerataTotal = $k['rata_rata'] !== null ? number_format((float) $k['rata_rata'], 1) . '%' : '—';
        $sdTotal = $k['standar_deviasi'] !== null ? '± ' . number_format((float) $k['standar_deviasi'], 1) . '%' : '—';
        $rerataKpi = $k['rata_kpi'] !== null ? number_format((float) $k['rata_kpi'], 1) . '%' : '—';
        $rerataKomp = $k['rata_kompetensi'] !== null ? number_format((float) $k['rata_kompetensi'], 1) . '%' : '—';
        $cukupKurva = (bool) ($k['cukup_untuk_kurva'] ?? true);

        $gradeList = $distribusi['grade'] ?? [];
        $levelList = $distribusi['per_level'] ?? [];
        $topList = $distribusi['talent_highlights']['top_performers'] ?? [];
        $hipoList = $distribusi['talent_highlights']['high_potential'] ?? [];
        $needList = $distribusi['talent_highlights']['need_improvement'] ?? [];
        // Dasar tiap daftar dikirim server; kode grade ditulis seperti di layar (B_PLUS → B+).
        $kriteriaTalenta = $distribusi['talent_highlights']['kriteria'] ?? [];
        $labelGrade = fn ($kode) => str_replace('_PLUS', '+', (string) $kode);
        $gradeTeratas = implode(', ', array_map($labelGrade, $kriteriaTalenta['grade_teratas'] ?? []));
        $gradeTerbawah = implode(', ', array_map($labelGrade, $kriteriaTalenta['grade_terbawah'] ?? []));
        $histogramBins = $distribusi['histogram'] ?? [];

        function formatTglIndo($str) {
            if (!$str) return '—';
            try {
                return \Carbon\Carbon::parse($str)->locale('id')->isoFormat('D MMMM Y');
            } catch (\Exception $e) {
                return $str;
            }
        }

        function cleanGrade($kode) {
            return str_replace(['_PLUS', '_MINUS', 'B PLUS', 'C PLUS', 'A PLUS'], ['+', '-', 'B+', 'C+', 'A+'], (string)$kode);
        }

        function getGradeBadgeClass($kode) {
            $c = cleanGrade($kode);
            if ($c === 'A' || $c === 'A+') return 'grade-A';
            if ($c === 'B+') return 'grade-B-plus';
            if ($c === 'B') return 'grade-B';
            if ($c === 'C+') return 'grade-C-plus';
            if ($c === 'C') return 'grade-C';
            if ($c === 'D' || $c === 'E') return 'grade-D';
            return 'grade-def';
        }

        function getGradeHexColor($kode) {
            $c = cleanGrade($kode);
            if ($c === 'A' || $c === 'A+') return '#059669';
            if ($c === 'B+') return '#4f46e5';
            if ($c === 'B') return '#0284c7';
            if ($c === 'C+') return '#d97706';
            if ($c === 'C') return '#ea580c';
            if ($c === 'D' || $c === 'E') return '#dc2626';
            return '#64748b';
        }

        function getDispersionInfo($sdKpi, $sdKomp) {
            $avg = ((float)($sdKpi ?? 0) + (float)($sdKomp ?? 0)) / 2;
            if ($avg <= 7) return ['label' => 'Homogen (Rapat)', 'class' => 'status-pill-ok'];
            if ($avg <= 12) return ['label' => 'Normal (Konsisten)', 'class' => 'status-pill-info'];
            return ['label' => 'Variatif (Menyebar)', 'class' => 'status-pill-warn'];
        }

        function hexToRgb($hex) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) == 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2))
            ];
        }

        function generateBellCurvesDataUri($curves, $width = 300, $height = 110, $minX = 30, $maxX = 110) {
            if (!extension_loaded('gd')) return '';
            $scale = 2;
            $w = $width * $scale;
            $h = $height * $scale;
            
            $im = imagecreatetruecolor($w, $h);
            $bg = imagecolorallocate($im, 250, 250, 250);
            $border = imagecolorallocate($im, 226, 232, 240);
            $gridLine = imagecolorallocate($im, 241, 245, 249);
            $axisLine = imagecolorallocate($im, 203, 213, 225);
            $textColor = imagecolorallocate($im, 100, 116, 139);
            
            imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bg);
            imagerectangle($im, 0, 0, $w - 1, $h - 1, $border);
            
            $plotTop = 15 * $scale;
            $plotBottom = $h - (22 * $scale);
            $plotLeft = 16 * $scale;
            $plotRight = $w - (16 * $scale);
            $plotW = $plotRight - $plotLeft;
            $plotH = $plotBottom - $plotTop;
            
            for ($g = 0; $g <= 2; $g++) {
                $gy = $plotBottom - ($g / 2) * $plotH;
                imageline($im, $plotLeft, (int)$gy, $plotRight, (int)$gy, $g == 0 ? $axisLine : $gridLine);
            }
            
            $ticks = [30, 50, 70, 90, 110];
            foreach ($ticks as $tick) {
                $tx = $plotLeft + (($tick - $minX) / ($maxX - $minX)) * $plotW;
                imageline($im, (int)$tx, (int)$plotTop, (int)$tx, (int)$plotBottom, $gridLine);
                $lbl = $tick . "%";
                imagestring($im, 2, (int)($tx - strlen($lbl) * 3), (int)($plotBottom + 4 * $scale), $lbl, $textColor);
            }
            
            $maxPdf = 4.0;
            foreach ($curves as $c) {
                $sd = (float)($c['sd'] ?? 10);
                if ($sd > 0) {
                    $pk = 1.0 / ($sd * sqrt(2 * M_PI)) * 100.0;
                    if ($pk > $maxPdf) $maxPdf = $pk;
                }
            }
            $maxPdf = max(5.0, ceil($maxPdf * 1.15));
            
            foreach ($curves as $c) {
                $mean = (float)($c['mean'] ?? 75);
                $sd = (float)($c['sd'] ?? 10);
                if ($sd <= 0 || !$mean) continue;
                
                $rgb = hexToRgb($c['color'] ?? '#4338ca');
                $curveColor = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
                
                $lastX = null;
                $lastY = null;
                
                for ($x = $minX; $x <= $maxX; $x += 0.5) {
                    $factor = 1.0 / ($sd * sqrt(2 * M_PI));
                    $exponent = -0.5 * pow(($x - $mean) / $sd, 2);
                    $pdf = $factor * exp($exponent) * 100.0;
                    
                    $px = $plotLeft + (($x - $minX) / ($maxX - $minX)) * $plotW;
                    $py = $plotBottom - ($pdf / $maxPdf) * $plotH;
                    
                    if ($lastX !== null) {
                        for ($th = -1; $th <= 1; $th++) {
                            imageline($im, (int)$lastX, (int)($lastY + $th), (int)$px, (int)($py + $th), $curveColor);
                        }
                    }
                    $lastX = $px;
                    $lastY = $py;
                }
            }
            
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            
            return 'data:image/png;base64,' . base64_encode($data);
        }

        function generateHistogramDataUri($bins, $mean, $sd, $width = 620, $height = 115) {
            if (!extension_loaded('gd')) return '';
            $scale = 2;
            $w = $width * $scale;
            $h = $height * $scale;
            
            $im = imagecreatetruecolor($w, $h);
            $bg = imagecolorallocate($im, 250, 250, 250);
            $border = imagecolorallocate($im, 226, 232, 240);
            $gridLine = imagecolorallocate($im, 241, 245, 249);
            $axisLine = imagecolorallocate($im, 203, 213, 225);
            $textColor = imagecolorallocate($im, 100, 116, 139);
            $barColor = imagecolorallocate($im, 99, 102, 241);
            $countColor = imagecolorallocate($im, 49, 46, 129);
            $curveColor = imagecolorallocate($im, 6, 182, 212);
            
            imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bg);
            imagerectangle($im, 0, 0, $w - 1, $h - 1, $border);
            
            $plotTop = 16 * $scale;
            $plotBottom = $h - (22 * $scale);
            $plotLeft = 16 * $scale;
            $plotRight = $w - (16 * $scale);
            $plotW = $plotRight - $plotLeft;
            $plotH = $plotBottom - $plotTop;
            
            imageline($im, $plotLeft, $plotBottom, $plotRight, $plotBottom, $axisLine);
            imageline($im, $plotLeft, (int)($plotTop + $plotH / 2), $plotRight, (int)($plotTop + $plotH / 2), $gridLine);
            
            $binCount = max(1, count($bins));
            $maxCount = 1;
            foreach ($bins as $b) {
                if ((int)($b['jumlah'] ?? 0) > $maxCount) $maxCount = (int)$b['jumlah'];
            }
            
            $step = $plotW / $binCount;
            $minScore = 1000;
            $maxScore = 0;
            
            foreach ($bins as $idx => $b) {
                $count = (int)($b['jumlah'] ?? 0);
                $dari = (float)($b['dari'] ?? 0);
                $sampai = (float)($b['sampai'] ?? 0);
                if ($dari < $minScore) $minScore = $dari;
                if ($sampai > $maxScore) $maxScore = $sampai;
                
                $bx = $plotLeft + $idx * $step + (3 * $scale);
                $bw = max(4 * $scale, $step - (6 * $scale));
                $bh = $maxCount > 0 ? ($count / $maxCount) * ($plotH - (12 * $scale)) : 0;
                $by = $plotBottom - $bh;
                
                if ($count > 0 && $bh > 0) {
                    imagefilledrectangle($im, (int)$bx, (int)$by, (int)($bx + $bw), (int)$plotBottom, $barColor);
                    $cntStr = (string)$count;
                    imagestring($im, 3, (int)($bx + $bw / 2 - strlen($cntStr) * 4), (int)($by - 12 * $scale), $cntStr, $countColor);
                }
                
                $lbl = ((int)$dari) . "-" . ((int)$sampai);
                imagestring($im, 2, (int)($bx + $bw / 2 - strlen($lbl) * 3), (int)($plotBottom + 4 * $scale), $lbl, $textColor);
            }
            
            if ($sd > 0 && $mean > 0 && $maxScore > $minScore) {
                $lastX = null;
                $lastY = null;
                $factorMax = 1.0 / ($sd * sqrt(2 * M_PI));
                
                for ($x = $minScore; $x <= $maxScore; $x += 0.5) {
                    $factor = 1.0 / ($sd * sqrt(2 * M_PI));
                    $exponent = -0.5 * pow(($x - $mean) / $sd, 2);
                    $pdf = $factor * exp($exponent);
                    $relHeight = $factorMax > 0 ? ($pdf / $factorMax) : 0;
                    
                    $px = $plotLeft + (($x - $minScore) / ($maxScore - $minScore)) * $plotW;
                    $py = $plotBottom - ($relHeight * ($plotH - (12 * $scale)));
                    
                    if ($lastX !== null) {
                        for ($th = -2; $th <= 2; $th++) {
                            imageline($im, (int)$lastX, (int)($lastY + $th), (int)$px, (int)($py + $th), $curveColor);
                        }
                    }
                    $lastX = $px;
                    $lastY = $py;
                }
            }
            
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            
            return 'data:image/png;base64,' . base64_encode($data);
        }

        $levelPalette = ['#4338ca', '#0d9488', '#2563eb', '#7c3aed', '#059669', '#d97706', '#dc2626', '#475569'];

        $kpiCurves = [];
        $kompCurves = [];
        foreach ($levelList as $idx => $lvl) {
            $color = $lvl['color'] ?? $levelPalette[$idx % count($levelPalette)];
            $kpiCurves[] = [
                'color' => $color,
                'mean' => $lvl['rata_kpi'] ?? $lvl['rata_rata'] ?? 75,
                'sd' => $lvl['standar_deviasi_kpi'] ?? $lvl['standar_deviasi'] ?? 10,
            ];
            $kompCurves[] = [
                'color' => $color,
                'mean' => $lvl['rata_kompetensi'] ?? $lvl['rata_rata'] ?? 75,
                'sd' => $lvl['standar_deviasi_kompetensi'] ?? $lvl['standar_deviasi'] ?? 10,
            ];
        }

        $kpiChartUri = generateBellCurvesDataUri($kpiCurves, 300, 105);
        $kompChartUri = generateBellCurvesDataUri($kompCurves, 300, 105);
        $histoChartUri = generateHistogramDataUri($histogramBins, (float)($k['rata_rata'] ?? 75), (float)($k['standar_deviasi'] ?? 10), 620, 110);
    @endphp

    <!-- Header Fixed (Hanya di Halaman 2+) -->
    <div class="doc-header">
        <table>
            <tr>
                <td>
                    <div class="brand-title">HCIS &middot; HUMAN CAPITAL MANAGEMENT</div>
                    <div class="sub-brand">Performance Appraisal &middot; Executive Distribution &amp; Bell Curve Report</div>
                </td>
                <td style="text-align: right;">
                    <span class="doc-tag">EXECUTIVE REPORT</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer Fixed (Hanya di Halaman 2+) -->
    <div class="doc-footer">
        <table>
            <tr>
                <td style="text-align: left;">
                    Laporan Resmi Manajemen &middot; Siklus: {{ $siklus->Nama_Cycle ?? $siklus->Kode_Cycle ?? 'PA' }} &middot; Ditarik: {{ $penarik['nama'] ?? 'Admin' }} ({{ $penarik['waktu'] ?? date('d/m/Y H:i') }} WIB)
                </td>
                <td style="text-align: right;" class="pg-num"></td>
            </tr>
        </table>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1: HALAMAN SAMPUL DEPAN (EXECUTIVE COVER PAGE) -->
    <!-- ========================================================================= -->
    <div class="cover-wrapper">
        <!-- Mask for top header and bottom footer on page 1 -->
        <div style="position: absolute; top: -55px; left: -35px; right: -35px; height: 60px; background: #ffffff;"></div>
        <div style="position: absolute; bottom: -45px; left: -35px; right: -35px; height: 50px; background: #ffffff;"></div>

        <div class="cover-top-badge">
            HUMAN CAPITAL MANAGEMENT SYSTEM &middot; EXECUTIVE REPORT
        </div>

        <div class="cover-title-box">
            <h1 class="cover-main-title">
                LAPORAN EKSEKUTIF<br>
                DISTRIBUSI KINERJA &amp; KURVA NORMAL
            </h1>
            <p class="cover-sub-title">
                Performance Appraisal Distribution, Quota Compliance &amp; Bell Curve Modeling
            </p>
        </div>

        <div class="cover-cycle-card">
            <div class="cover-cycle-title">{{ $siklus->Nama_Cycle ?? 'Assessment Cycle Performance Appraisal' }}</div>
            <div class="cover-cycle-meta">
                Kode Siklus: <strong>{{ $siklus->Kode_Cycle ?? 'PA' }}</strong> &middot; 
                Tahun: <strong>{{ $siklus->Tahun ?? date('Y') }}</strong> &middot; 
                Status Evaluasi: <strong>{{ $siklus->Status_Cycle ?? 'CALCULATED' }}</strong><br>
                Periode Penilaian Dinilai: <strong>{{ formatTglIndo($siklus->Periode_Dinilai_Dari) }} s/d {{ formatTglIndo($siklus->Periode_Dinilai_Sampai) }}</strong>
            </div>
        </div>

        <table class="cover-meta-grid">
            <tr>
                <td>
                    <div class="cover-meta-label">Klasifikasi Dokumen</div>
                    <div class="cover-meta-val" style="color: #dc2626;">CONFIDENTIAL (RAHASIA MANAJEMEN)</div>
                </td>
                <td>
                    <div class="cover-meta-label">Target Audiens</div>
                    <div class="cover-meta-val">Board of Directors &amp; Komite Kalibrasi HR</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="cover-meta-label">Ditarik / Diterbitkan Oleh</div>
                    <div class="cover-meta-val">{{ $penarik['nama'] ?? 'Administrator HCIS' }} ({{ $penarik['nik'] ?? 'ADM' }})</div>
                </td>
                <td>
                    <div class="cover-meta-label">Divisi / Unit Kerja</div>
                    <div class="cover-meta-val">{{ $penarik['divisi'] ?? 'Human Capital Management' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="cover-meta-label">Waktu Ekstraksi Data</div>
                    <div class="cover-meta-val">{{ $penarik['waktu'] ?? now()->format('d F Y, H:i') }} WIB</div>
                </td>
                <td>
                    <div class="cover-meta-label">Total Sampel Dievaluasi</div>
                    <div class="cover-meta-val">{{ number_format($totalPeserta, 0, ',', '.') }} Karyawan ({{ count($levelList) }} Level)</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 2: EXECUTIVE COCKPIT & DUAL KURVA NORMAL MULTI-LEVEL (KPI & KOMPETENSI) -->
    <!-- ========================================================================= -->
    <div class="page-break"></div>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <table>
            <tr>
                <td style="width: 70%;">
                    <h2 class="cycle-name">{{ $siklus->Nama_Cycle ?? 'Assessment Cycle Performance Appraisal' }}</h2>
                    <div class="cycle-meta">
                        Kode Siklus: <strong>{{ $siklus->Kode_Cycle ?? '—' }}</strong> &middot; 
                        Tahun: <strong>{{ $siklus->Tahun ?? date('Y') }}</strong> &middot; 
                        Status: <strong style="color: #4338ca;">{{ $siklus->Status_Cycle ?? 'CALCULATED' }}</strong>
                    </div>
                </td>
                <td style="width: 30%; text-align: right;">
                    <div class="cycle-meta">
                        Periode Dinilai:<br>
                        <strong>{{ formatTglIndo($siklus->Periode_Dinilai_Dari) }} – {{ formatTglIndo($siklus->Periode_Dinilai_Sampai) }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 4 Centered KPI Stat Metric Cards -->
    <table class="stat-grid-table">
        <tr>
            <td>
                <div class="stat-card-label">Total Partisipan</div>
                <div class="stat-card-value">{{ number_format($totalPeserta, 0, ',', '.') }}</div>
                <div class="stat-card-desc">{{ count($levelList) }} Tingkatan Jabatan</div>
            </td>
            <td>
                <div class="stat-card-label">Rata-Rata Skor (Mean)</div>
                <div class="stat-card-value">{{ $rerataTotal }}</div>
                <div class="stat-card-desc">KPI: {{ $rerataKpi }} &middot; Komp: {{ $rerataKomp }}</div>
            </td>
            <td>
                <div class="stat-card-label">Simpangan Baku (Std Dev)</div>
                <div class="stat-card-value">{{ $sdTotal }}</div>
                <div class="stat-card-desc">Min: {{ number_format((float)($k['terendah'] ?? 0), 1) }}% &middot; Maks: {{ number_format((float)($k['tertinggi'] ?? 0), 1) }}%</div>
            </td>
            <td>
                <div class="stat-card-label">Kurva Distribusi Normal</div>
                <div class="stat-card-value" style="font-size: 11px; color: {{ $cukupKurva ? '#059669' : '#d97706' }};">
                    {{ $cukupKurva ? 'Tervalidasi' : 'Sampel Terbatas' }}
                </div>
                <div class="stat-card-desc">{{ $cukupKurva ? 'Syarat N ≥ 30 terpenuhi' : 'Populasi < 30 orang' }}</div>
            </td>
        </tr>
    </table>

    <!-- Executive Narrative Note -->
    <div class="narrative-box">
        <strong>Executive Summary &amp; Management Note:</strong><br>
        Evaluasi kinerja pada siklus <strong>{{ $siklus->Nama_Cycle ?? $siklus->Kode_Cycle }}</strong> mencakup total <strong>{{ number_format($totalPeserta, 0, ',', '.') }} karyawan</strong> dengan rata-rata pencapaian korporasi sebesar <strong>{{ $rerataTotal }}</strong> dan simpangan baku <strong>{{ $sdTotal }}</strong>. Sebaran nilai menunjukkan diferensiasi performa yang 
        @if(($k['standar_deviasi'] ?? 0) <= 7)
            <strong>sangat homogen</strong> (nilai menumpuk di area tengah).
        @elseif(($k['standar_deviasi'] ?? 0) <= 12)
            <strong>sehat dan konsisten</strong> (talenta unggul dan talenta bimbingan terbedakan secara wajar).
        @else
            <strong>sangat variatif</strong> (gap performa antar unit kerja cukup signifikan).
        @endif
    </div>

    <!-- Section: Analisis Kurva Normal per Tingkat Jabatan (Dual Charts KPI & Kompetensi) -->
    <div class="section-box">
        <div class="section-header">
            <h3 class="section-title">Pemodelan Kurva Distribusi Normal per Tingkat Jabatan (Multi-Level Gaussian PDF)</h3>
            <p class="section-subtitle">Perbandingan pemodelan matematis sebaran nilai KPI dan Kompetensi untuk mengidentifikasi konsistensi dispersi antar level struktural.</p>
        </div>

        <table class="dual-chart-table">
            <tr>
                <!-- Chart 1A: Pilar KPI -->
                <td style="width: 49%; vertical-align: top;">
                    <div class="chart-card">
                        <div class="chart-card-head">
                            <table>
                                <tr>
                                    <td><span class="chart-head-title">[PILAR KPI] Key Performance</span></td>
                                    <td><span class="chart-head-sub">Rentang: 30% – 110%</span></td>
                                </tr>
                            </table>
                        </div>
                        @if($kpiChartUri)
                            <img src="{{ $kpiChartUri }}" class="chart-img" alt="Kurva KPI" />
                        @endif
                    </div>
                </td>

                <td style="width: 2%;"></td>

                <!-- Chart 1B: Pilar Kompetensi 360° -->
                <td style="width: 49%; vertical-align: top;">
                    <div class="chart-card">
                        <div class="chart-card-head">
                            <table>
                                <tr>
                                    <td><span class="chart-head-title">[PILAR KOMPETENSI] 360&deg; Behavioral</span></td>
                                    <td><span class="chart-head-sub">Rentang: 30% – 110%</span></td>
                                </tr>
                            </table>
                        </div>
                        @if($kompChartUri)
                            <img src="{{ $kompChartUri }}" class="chart-img" alt="Kurva Kompetensi" />
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Level Legend Chips Table -->
        <table class="chart-legend-table">
            <tr>
                @foreach($levelList as $idx => $lvl)
                    @if($idx > 0 && $idx % 3 === 0)
                        </tr><tr>
                    @endif
                    @php
                        $cColor = $lvl['color'] ?? $levelPalette[$idx % count($levelPalette)];
                        $cKpi = $lvl['rata_kpi'] ?? $lvl['rata_rata'] ?? 0;
                        $cKomp = $lvl['rata_kompetensi'] ?? $lvl['rata_rata'] ?? 0;
                    @endphp
                    <td style="width: 33.3%;">
                        <span class="legend-bullet" style="background: {{ $cColor }};"></span>
                        <strong>{{ $lvl['nama_level'] ?? 'Level' }}</strong>: KPI {{ number_format((float)$cKpi, 1) }}% &middot; Komp {{ number_format((float)$cKomp, 1) }}%
                    </td>
                @endforeach
                @php
                    $remainder = count($levelList) % 3;
                    if ($remainder > 0) {
                        for ($i = $remainder; $i < 3; $i++) {
                            echo '<td style="width: 33.3%;"></td>';
                        }
                    }
                @endphp
            </tr>
        </table>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 3: RINGKASAN STATISTIK LEVEL & KURVA LONCENG KORPORASI -->
    <!-- ========================================================================= -->
    <div class="page-break"></div>

    <!-- Section 1: Ringkasan Statistik Parametrik per Tingkat Jabatan -->
    <div class="section-box">
        <div class="section-header">
            <h3 class="section-title">1. Ringkasan Statistik &amp; Dispersi Tingkat Jabatan (Job Level Breakdown)</h3>
            <p class="section-subtitle">Analisis rata-rata nilai, standar deviasi, dan perbandingan pilar KPI vs Kompetensi per jenjang organisasi.</p>
        </div>

        <table class="exec-table">
            <thead>
                <tr>
                    <th style="width: 22%;" class="text-left">Tingkat Jabatan</th>
                    <th style="width: 10%;">Headcount</th>
                    <th style="width: 10%;">Proporsi</th>
                    <th style="width: 11%;">Mean Total</th>
                    <th style="width: 11%;">Std Dev (σ)</th>
                    <th style="width: 15%;">Mean KPI (%)</th>
                    <th style="width: 15%;">Mean Komp (%)</th>
                    <th style="width: 16%;">Dispersi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($levelList as $idx => $lvl)
                    @php
                        $lvlJumlah = (int)($lvl['jumlah'] ?? 0);
                        $lvlPct = $totalPeserta > 0 ? number_format(($lvlJumlah / $totalPeserta) * 100, 1) . '%' : '0.0%';
                        $lvlKpi = (float)($lvl['rata_kpi'] ?? 0);
                        $lvlKomp = (float)($lvl['rata_kompetensi'] ?? 0);
                        $disp = getDispersionInfo($lvl['standar_deviasi_kpi'] ?? null, $lvl['standar_deviasi_kompetensi'] ?? null);
                        $cColor = $lvl['color'] ?? $levelPalette[$idx % count($levelPalette)];
                    @endphp
                    <tr>
                        <td class="text-left font-bold">
                            <span class="legend-bullet" style="background: {{ $cColor }};"></span>
                            {{ $lvl['nama_level'] ?? '—' }}
                        </td>
                        <td class="font-bold">{{ number_format($lvlJumlah, 0, ',', '.') }}</td>
                        <td>{{ $lvlPct }}</td>
                        <td class="font-bold" style="color: #4338ca;">
                            {{ $lvl['rata_rata'] !== null ? number_format((float)$lvl['rata_rata'], 1) . '%' : '—' }}
                        </td>
                        <td>
                            {{ $lvl['standar_deviasi'] !== null ? '± ' . number_format((float)$lvl['standar_deviasi'], 1) . '%' : '—' }}
                        </td>
                        <td>
                            <div class="font-bold" style="color: #4338ca;">{{ $lvl['rata_kpi'] !== null ? number_format($lvlKpi, 1) . '%' : '—' }}</div>
                            @if($lvl['rata_kpi'] !== null)
                                <div class="cell-mini-track">
                                    <div class="cell-mini-fill-kpi" style="width: {{ min(100, $lvlKpi) }}%;"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="font-bold" style="color: #0d9488;">{{ $lvl['rata_kompetensi'] !== null ? number_format($lvlKomp, 1) . '%' : '—' }}</div>
                            @if($lvl['rata_kompetensi'] !== null)
                                <div class="cell-mini-track">
                                    <div class="cell-mini-fill-komp" style="width: {{ min(100, $lvlKomp) }}%;"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="status-pill {{ $disp['class'] }}">{{ $disp['label'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="color: #64748b;">Belum ada data tingkat jabatan pada siklus ini.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-left">TOTAL ORGANISASI / RERATA</td>
                    <td>{{ number_format($totalPeserta, 0, ',', '.') }}</td>
                    <td>100.0%</td>
                    <td style="color: #4338ca;">{{ $rerataTotal }}</td>
                    <td>{{ $sdTotal }}</td>
                    <td style="color: #4338ca;">{{ $rerataKpi }}</td>
                    <td style="color: #0d9488;">{{ $rerataKomp }}</td>
                    <td>
                        <span class="status-pill status-pill-info">Populasi Lengkap</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Section 2: Kurva Lonceng Distribusi Korporasi & Histogram Frekuensi Aktual (Chart 2) -->
    <div class="section-box" style="margin-top: 10px;">
        <div class="section-header">
            <h3 class="section-title">2. Sebaran Frekuensi Nilai Akhir &amp; Kurva Lonceng Korporasi</h3>
            <p class="section-subtitle">Histogram distribusi frekuensi nilai aktual (balok indigo) disandingkan dengan kurva lonceng normal ideal (garis cyan).</p>
        </div>

        <div class="chart-card">
            <div class="chart-card-head">
                <table>
                    <tr>
                        <td><span class="chart-head-title">[HISTOGRAM &amp; BELL CURVE] Frekuensi Nilai Aktual vs Kurva Lonceng Normal</span></td>
                        <td><span class="chart-head-sub">Balok: Headcount &middot; Garis Cyan: Normal Bell Curve</span></td>
                    </tr>
                </table>
            </div>
            @if($histoChartUri)
                <img src="{{ $histoChartUri }}" class="chart-img" alt="Histogram dan Kurva Lonceng" />
            @endif
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 4: KEPATUHAN KUOTA GRADE, SOROTAN TALENTA & JEJAK AUDIT -->
    <!-- ========================================================================= -->
    <div class="page-break"></div>

    <!-- Section 3: Kepatuhan Kuota Grade (Tabel & Visual Bar) -->
    <div class="section-box">
        <div class="section-header">
            <h3 class="section-title">3. Distribusi Grade &amp; Kepatuhan Kuota Korporasi (Grade Quota Compliance)</h3>
            <p class="section-subtitle">Evaluasi keselarasan persentase aktual terhadap target kuota baku korporasi (*Forced Distribution Policy*).</p>
        </div>

        <!-- Horizontal Visual Bar Comparison -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 6px; margin-bottom: 5px;">
            <table class="bar-comparison-tbl">
                @foreach($gradeList as $g)
                    @php
                        $clean = cleanGrade($g['kode_grade'] ?? '');
                        $hex = getGradeHexColor($clean);
                        $actPct = (float)($g['persen'] ?? 0);
                        $tgtPct = (float)($g['target_persen'] ?? 0);
                    @endphp
                    <tr>
                        <td style="width: 8%; font-weight: bold; color: {{ $hex }}; text-align: center;">{{ $clean }}</td>
                        <td style="width: 26%;">{{ $g['nama_grade'] ?? ('Grade ' . $clean) }}</td>
                        <td style="width: 48%;">
                            <div class="bar-track">
                                <div class="bar-fill-actual" style="width: {{ min(100, $actPct) }}%; background: {{ $hex }};"></div>
                            </div>
                        </td>
                        <td style="width: 18%; text-align: right; font-weight: bold;">
                            {{ number_format($actPct, 1) }}% <span style="font-size: 6.2px; color: #64748b; font-weight: normal;">(Tgt: {{ number_format($tgtPct, 1) }}%)</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

        @if (!empty($distribusi['grade_sementara']))
            <p style="font-size: 7px; color: #b45309; margin: 4px 0;">Grade SEMENTARA: kurva belum dikunci, sebaran masih bisa bergeser saat hasil lain masuk.</p>
        @endif
        <!-- Grade Table -->
        <table class="exec-table">
            <thead>
                <tr>
                    <th style="width: 9%;">Grade</th>
                    <th style="width: 31%;" class="text-left">Kategori Performa (Performance Category)</th>
                    <th style="width: 12%;">Jumlah (Orang)</th>
                    <th style="width: 12%;">Aktual %</th>
                    {{-- Siklus KURVA: pembandingnya proporsi teoretis kurva normal, bukan kuota master. --}}
                    <th style="width: 12%;">{{ ($distribusi['metode_grade'] ?? 'PITA') === 'KURVA' ? 'Teoretis %' : 'Target Kuota %' }}</th>
                    <th style="width: 10%;">Selisih</th>
                    <th style="width: 14%;">Status Kuota</th>
                </tr>
            </thead>
            <tbody>
                @forelse($gradeList as $g)
                    @php
                        $clean = cleanGrade($g['kode_grade'] ?? '');
                        $selisih = $g['selisih'] ?? null;
                        $isSignificant = $selisih !== null && abs((float)$selisih) >= 5.0;
                    @endphp
                    <tr>
                        <td>
                            <span class="grade-badge {{ getGradeBadgeClass($g['kode_grade'] ?? '') }}">{{ $clean }}</span>
                        </td>
                        <td class="text-left font-bold">{{ $g['nama_grade'] ?? ('Grade ' . $clean) }}</td>
                        <td class="font-bold">{{ number_format((int)($g['jumlah'] ?? 0), 0, ',', '.') }}</td>
                        <td class="font-bold" style="color: #4338ca;">{{ number_format((float)($g['persen'] ?? 0), 1) }}%</td>
                        <td style="color: #64748b;">
                            {{ $g['target_persen'] !== null ? number_format((float)$g['target_persen'], 1) . '%' : '—' }}
                        </td>
                        <td>
                            @if($selisih !== null)
                                <span class="{{ (float)$selisih > 0 ? 'diff-plus' : ((float)$selisih < 0 ? 'diff-minus' : 'diff-zero') }}">
                                    {{ (float)$selisih > 0 ? '+' : '' }}{{ number_format((float)$selisih, 1) }}%
                                </span>
                            @else
                                <span style="color: #94a3b8;">—</span>
                            @endif
                        </td>
                        <td>
                            @if($isSignificant)
                                <span class="status-pill status-pill-warn">Deviasi Signifikan</span>
                            @else
                                <span class="status-pill status-pill-ok">Sesuai Kuota</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="color: #64748b;">Belum ada sebaran grade untuk siklus ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Section 4: Sorotan Talenta Utama (Key Talent Highlights) -->
    <div class="section-box" style="margin-top: 8px;">
        <div class="section-header">
            <h3 class="section-title">4. Sorotan Talenta Utama (Key Talent Highlights)</h3>
            <p class="section-subtitle">Peringkat kinerja mengikuti grade (kurva/pita siklus); potensi mengikuti kuadran HAV.</p>
        </div>

        <!-- 4.1 Top Performers -->
        <div style="margin-bottom: 6px;">
            <div style="font-size: 7.5px; font-weight: bold; color: #059669; margin-bottom: 2px;">
                [GRADE TERTINGGI] Grade {{ $gradeTeratas ?: '—' }}
            </div>
            <table class="exec-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 28%;" class="text-left">Nama Karyawan</th>
                        <th style="width: 14%;">NIK</th>
                        <th style="width: 18%;" class="text-left">Jabatan / Level</th>
                        <th style="width: 18%;" class="text-left">Divisi / Unit</th>
                        <th style="width: 8%;">Grade</th>
                        <th style="width: 8%;">Skor Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topList as $idx => $t)
                        <tr>
                            <td>
                                <span class="talent-rank">{{ $idx + 1 }}</span>
                            </td>
                            <td class="text-left font-bold">{{ $t['nama'] ?? '—' }}</td>
                            <td class="font-mono">{{ $t['kode_karyawan'] ?? '—' }}</td>
                            <td class="text-left">{{ $t['level'] ?? '—' }}</td>
                            <td class="text-left">{{ $t['divisi'] ?? '—' }}</td>
                            <td>
                                <span class="grade-badge {{ getGradeBadgeClass($t['grade'] ?? 'A') }}">{{ cleanGrade($t['grade'] ?? 'A') }}</span>
                            </td>
                            <td class="font-bold" style="color: #059669;">
                                {{ number_format((float)($t['skor'] ?? 0), 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="color: #64748b;">Tidak ada data top performer yang memenuhi kriteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 4.2 High Potential -->
        <div style="margin-bottom: 6px;">
            <div style="font-size: 7.5px; font-weight: bold; color: #4338ca; margin-bottom: 2px;">
                [POTENSI TINGGI] Kuadran HAV berkompetensi tinggi
            </div>
            <table class="exec-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 28%;" class="text-left">Nama Karyawan</th>
                        <th style="width: 14%;">NIK</th>
                        <th style="width: 18%;" class="text-left">Jabatan / Level</th>
                        <th style="width: 18%;" class="text-left">Divisi / Unit</th>
                        <th style="width: 8%;">Kompetensi</th>
                        <th style="width: 8%;">Skor Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hipoList as $idx => $h)
                        <tr>
                            <td>
                                <span class="talent-rank" style="background: #4338ca;">{{ $idx + 1 }}</span>
                            </td>
                            <td class="text-left font-bold">{{ $h['nama'] ?? '—' }}</td>
                            <td class="font-mono">{{ $h['kode_karyawan'] ?? '—' }}</td>
                            <td class="text-left">{{ $h['level'] ?? '—' }}</td>
                            <td class="text-left">{{ $h['divisi'] ?? '—' }}</td>
                            <td class="font-bold" style="color: #4338ca;">
                                {{ $h['nilai_kompetensi'] !== null ? number_format((float)$h['nilai_kompetensi'], 1) . '%' : '—' }}
                            </td>
                            <td class="font-bold">
                                {{ number_format((float)($h['skor'] ?? 0), 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="color: #64748b;">Tidak ada data high potential yang memenuhi kriteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 4.3 Need Improvement -->
        <div style="margin-bottom: 6px;">
            <div style="font-size: 7.5px; font-weight: bold; color: #dc2626; margin-bottom: 2px;">
                [GRADE TERENDAH] Grade {{ $gradeTerbawah ?: '—' }}
            </div>
            <table class="exec-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">#</th>
                        <th style="width: 28%;" class="text-left">Nama Karyawan</th>
                        <th style="width: 14%;">NIK</th>
                        <th style="width: 18%;" class="text-left">Jabatan / Level</th>
                        <th style="width: 18%;" class="text-left">Divisi / Unit</th>
                        <th style="width: 8%;">Grade</th>
                        <th style="width: 8%;">Skor Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($needList as $idx => $n)
                        <tr>
                            <td>
                                <span class="talent-rank talent-rank--danger">{{ $idx + 1 }}</span>
                            </td>
                            <td class="text-left font-bold">{{ $n['nama'] ?? '—' }}</td>
                            <td class="font-mono">{{ $n['kode_karyawan'] ?? '—' }}</td>
                            <td class="text-left">{{ $n['level'] ?? '—' }}</td>
                            <td class="text-left">{{ $n['divisi'] ?? '—' }}</td>
                            <td>
                                <span class="grade-badge {{ getGradeBadgeClass($n['grade'] ?? 'D') }}">{{ cleanGrade($n['grade'] ?? 'D') }}</span>
                            </td>
                            <td class="font-bold" style="color: #dc2626;">
                                {{ number_format((float)($n['skor'] ?? 0), 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="color: #64748b;">Tidak ada data karyawan yang membutuhkan program PIP khusus.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 5: Informasi Penarikan Data & Jejak Audit Sistem (System Audit Trail) -->
    <div class="audit-trail-box">
        <div class="audit-trail-title">INFORMASI PENARIKAN DATA &amp; JEJAK AUDIT SISTEM (SYSTEM AUDIT TRAIL)</div>
        <table class="audit-table">
            <tr>
                <td style="width: 25%;">
                    <span class="audit-label">Ditarik Oleh:</span>
                    <strong class="audit-val">{{ $penarik['nama'] ?? 'Administrator HCIS' }} ({{ $penarik['nik'] ?? 'ADM' }})</strong>
                </td>
                <td style="width: 25%;">
                    <span class="audit-label">Divisi / Unit Kerja:</span>
                    <strong class="audit-val">{{ $penarik['divisi'] ?? 'Human Capital Management' }}</strong>
                </td>
                <td style="width: 25%;">
                    <span class="audit-label">Waktu Penarikan Data:</span>
                    <strong class="audit-val">{{ $penarik['waktu'] ?? now()->format('d F Y, H:i') }} WIB</strong>
                </td>
                <td style="width: 25%;">
                    <span class="audit-label">Klasifikasi Dokumen:</span>
                    <strong class="audit-val" style="color: #dc2626;">CONFIDENTIAL (Rahasia)</strong>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
