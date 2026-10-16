{{--
    PDF export modul SOP. Generik: seluruh bentuk kolom datang dari $dataset['columns'],
    jadi satu berkas ini melayani coverage / karyawan / atestasi / dokumen / risiko
    tanpa perlu template per jenis.

    Warna mengikuti token design system SOP (indigo --primary #6366f1 / #4f46e5)
    supaya berkas cetak terbaca satu keluarga dengan layarnya.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset['title'] ?? 'Export SOP' }}</title>
    <style>
        @page { margin: 64px 22px 40px 22px; }
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: 7.5px; color: #1e293b; margin: 0; }

        .doc-header {
            position: fixed; top: -50px; left: 0; right: 0; height: 42px;
            border-bottom: 2px solid #4f46e5; padding-bottom: 4px;
        }
        .doc-header h1 { font-size: 13px; margin: 0; color: #312e81; letter-spacing: -.01em; }
        .doc-header .meta { font-size: 8px; color: #64748b; margin-top: 2px; }
        .doc-header .tag {
            position: absolute; right: 0; top: 4px;
            background: #4f46e5; color: #fff; font-size: 8px; font-weight: bold;
            padding: 3px 9px; border-radius: 10px; letter-spacing: .04em;
        }

        .doc-footer {
            position: fixed; bottom: -30px; left: 0; right: 0; height: 22px;
            border-top: 1px solid #e2e8f0; padding-top: 4px;
            font-size: 7px; color: #94a3b8;
        }
        .doc-footer .pg { float: right; }
        .doc-footer .pg:after { content: counter(page) " / " counter(pages); }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td {
            border: 1px solid #b7c3d6; padding: 3px 4px; vertical-align: middle; text-align: left;
        }
        table.grid thead th {
            background: #4f46e5; color: #fff; font-size: 7px; font-weight: bold;
            text-transform: uppercase; letter-spacing: .02em; text-align: center;
        }
        table.grid tbody tr:nth-child(even) td { background: #f4f6fb; }
        table.grid td.c, table.grid th.c { text-align: center; }
        table.grid tbody tr { page-break-inside: avoid; }
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

    @php $columns = $dataset['columns'] ?? []; @endphp

    @if (empty($dataset['rows']))
        <div class="empty">Tidak ada data untuk kriteria yang dipilih.</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    @foreach ($columns as $c)
                        <th class="{{ !empty($c['center']) ? 'c' : '' }}" style="width: {{ ($c['width'] ?? 12) * 6 }}px">
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
</body>
</html>
