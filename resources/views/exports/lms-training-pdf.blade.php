<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 64px 22px 40px 22px; }
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: 7.5px; color: #1e293b; margin: 0; }

        .doc-header {
            position: fixed; top: -50px; left: 0; right: 0; height: 42px;
            border-bottom: 2px solid #4f46e5; padding-bottom: 4px;
        }
        .doc-header h1 { font-size: 13px; margin: 0; color: #312e81; }
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
        .doc-footer .pg:after { content: counter(page) " / " counter(pages); }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td {
            border: 1px solid #b7c3d6; padding: 3px 4px; vertical-align: middle;
            text-align: left;
        }
        table.grid thead th {
            background: #4f46e5; color: #fff; font-size: 7px; font-weight: bold;
            text-transform: uppercase; letter-spacing: .02em; text-align: center;
        }
        table.grid td.idn { background: #eef2ff; }
        table.grid td.c, table.grid th.c { text-align: center; }
        table.grid tbody tr { page-break-inside: avoid; }
        .empty { text-align: center; color: #94a3b8; padding: 18px; font-style: italic; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>Export Data Training LMS</h1>
        <div class="meta">Dibuat: {{ $dataset['generated_at'] }}</div>
        <span class="tag">LMS</span>
    </div>

    <div class="doc-footer">
        <span>Monitoring Training · LMS HRIS</span>
        <span style="float:right;">Hal. <span class="pg"></span></span>
    </div>

    @php
        $rows = $dataset['rows'] ?? [];
    @endphp

    @if (empty($rows))
        <p class="empty">Tidak ada data training untuk di-export.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="c">No</th>
                    <th class="c">Kode Karyawan</th>
                    <th>Nama Karyawan</th>
                    <th>Lokasi Kerja</th>
                    <th>Divisi</th>
                    <th>Departemen</th>
                    <th class="c">Level</th>
                    <th>Jabatan</th>
                    <th>Nama Training</th>
                    <th class="c">Tgl Training</th>
                    <th class="c">Status</th>
                    <th class="c">Progress</th>
                    <th class="c">Completion Date</th>
                    <th class="c">Completion Status</th>
                    <th class="c">Pre-test</th>
                    <th class="c">Post-test</th>
                    <th class="c">Final Score</th>
                    <th class="c">Learning Gain</th>
                    <th class="c">Kelulusan</th>
                    <th class="c">Kategori</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td class="c">{{ $row['kode_karyawan'] }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['lokasi_kerja'] }}</td>
                        <td>{{ $row['divisi'] }}</td>
                        <td>{{ $row['departemen'] }}</td>
                        <td class="c">{{ $row['level'] }}</td>
                        <td>{{ $row['jabatan'] }}</td>
                        <td>{{ $row['judul'] ?? '-' }}</td>
                        <td class="c">{{ $row['tanggal_training'] ?? '-' }}</td>
                        <td class="c">{{ $row['status_label'] ?? '-' }}</td>
                        <td class="c">{{ isset($row['progress']) ? $row['progress'] . '%' : '-' }}</td>
                        <td class="c">{{ $row['completion_date'] ?? '-' }}</td>
                        <td class="c">{{ $row['completion_status'] ?? '-' }}</td>
                        <td class="c">{{ $row['pre_test'] ?? '-' }}</td>
                        <td class="c">{{ $row['post_test'] ?? '-' }}</td>
                        <td class="c">{{ $row['final_score'] ?? '-' }}</td>
                        <td class="c">{{ $row['learning_gain'] ?? '-' }}</td>
                        <td class="c">{{ $row['kelulusan'] ?? '-' }}</td>
                        <td class="c">{{ $row['kategori'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
