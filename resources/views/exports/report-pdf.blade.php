{{--
    PDF Export Studio. Sepenuhnya digerakkan $dataset['columns'], jadi satu berkas
    ini melayani setiap definisi laporan dari modul mana pun -- bukan hanya SOP.

    Disalin dari exports/sop-compliance-pdf.blade.php dan ditambah blok catatan
    kaki. Blade lama sengaja TIDAK diubah: selama masa dua jalur, jalur lama harus
    tetap menghasilkan berkas yang persis sama seperti sebelumnya.

    Warna mengikuti token design system SOP (indigo --primary #6366f1 / #4f46e5)
    supaya berkas cetak terbaca satu keluarga dengan layarnya.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset['title'] ?? 'Export SOP' }}</title>
    <style>
        @page { margin: 64px 20px 40px 20px; }
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: {{ $fontSize ?? '7.5px' }}; color: #1e293b; margin: 0; }

        .doc-header {
            position: fixed; top: -50px; left: 0; right: 0; height: 42px;
            border-bottom: 2px solid #2563eb; padding-bottom: 4px;
        }
        .doc-header h1 { font-size: 13px; margin: 0; color: #1e3a8a; letter-spacing: -.01em; }
        .doc-header .meta { font-size: 8px; color: #64748b; margin-top: 2px; }
        .doc-header .tag {
            position: absolute; right: 0; top: 4px;
            background: #2563eb; color: #fff; font-size: 8px; font-weight: bold;
            padding: 3px 9px; border-radius: 10px; letter-spacing: .04em;
        }

        .doc-footer {
            position: fixed; bottom: -30px; left: 0; right: 0; height: 22px;
            border-top: 1px solid #e2e8f0; padding-top: 4px;
            font-size: 7px; color: #94a3b8;
        }
        .doc-footer .pg { float: right; }
        .doc-footer .pg:after { content: counter(page) " / " counter(pages); }

        /* Catatan kaki: pemotongan dan kolom yang di-mask WAJIB terlihat di
           berkasnya. Berkas yang terpotong tanpa jejak adalah mode gagal
           terburuk -- angkanya tetap terbaca masuk akal. */
        .catatan {
            margin-top: 10px; padding: 6px 8px;
            border-left: 3px solid #2563eb; background: #f0f7ff;
            font-size: 7.5px; color: #1e3a8a;
        }
        .catatan div { margin: 1px 0; }

        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th, table.grid td {
            border: 1px solid #cbd5e1; padding: {{ $cellPadding ?? '3px 4px' }}; vertical-align: middle; text-align: left;
            word-wrap: break-word; overflow: hidden;
        }
        table.grid thead th {
            background: #2563eb; color: #fff; font-size: {{ $headerFontSize ?? '7px' }}; font-weight: bold;
            text-transform: uppercase; letter-spacing: .02em; text-align: center;
        }
        table.grid tbody tr:nth-child(even) td { background: #f8fafc; }
        table.grid td.c, table.grid th.c { text-align: center; }
        .empty { text-align: center; color: #94a3b8; padding: 18px; font-style: italic; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>{{ $dataset['title'] ?? 'Export SOP' }}</h1>
        <div class="meta">Dibuat: {{ $dataset['generated_at'] ?? now()->format('Y-m-d H:i') }}</div>
        <div class="tag">HCIS · SOP</div>
    </div>

    <div class="doc-footer">
        Dokumen ini dibuat otomatis oleh HCIS — Modul Kepatuhan SOP.
        <span class="pg">Halaman </span>
    </div>

    @php
        $columns = $dataset['columns'] ?? [];
        $totalWeight = array_sum(array_map(fn($c) => max(6, (float)($c['width'] ?? 12)), $columns)) ?: 1;
    @endphp

    @if (empty($dataset['rows']))
        <div class="empty">Tidak ada data untuk kriteria yang dipilih.</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    @foreach ($columns as $c)
                        @php
                            $wPct = round((max(6, (float)($c['width'] ?? 12)) / $totalWeight) * 100, 2);
                        @endphp
                        <th class="{{ !empty($c['center']) ? 'c' : '' }}" style="width: {{ $wPct }}%">
                            {{ $c['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($dataset['rows'] as $row)
                    <tr>
                        @foreach ($columns as $c)
                            <td class="{{ !empty($c['center']) ? 'c' : '' }}">{{ $row[$c['key']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

@if (! empty($dataset['catatan']))
    <div class="catatan">
        @foreach ($dataset['catatan'] as $c)
            <div>{{ $c }}</div>
        @endforeach
    </div>
@endif
</body>
</html>
