<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 28px 48px 28px; }
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { font-size: 9px; color: #1e293b; margin: 0; }

        /* ===== Header & footer (fixed di tiap halaman) ===== */
        .doc-header {
            position: fixed; top: -56px; left: 0; right: 0; height: 48px;
            border-bottom: 2px solid #4f46e5; padding-bottom: 4px;
        }
        .doc-header h1 { font-size: 14px; margin: 0; color: #312e81; }
        .doc-header .meta { font-size: 8.5px; color: #64748b; margin-top: 2px; }
        .doc-header .tag {
            position: absolute; right: 0; top: 4px;
            background: #4f46e5; color: #fff; font-size: 8px; font-weight: bold;
            padding: 3px 9px; border-radius: 10px; letter-spacing: .04em;
        }
        .doc-footer {
            position: fixed; bottom: -34px; left: 0; right: 0; height: 24px;
            border-top: 1px solid #e2e8f0; padding-top: 4px;
            font-size: 7.5px; color: #94a3b8;
        }
        .doc-footer .pg:after { content: counter(page) " / " counter(pages); }
        .doc-footer .foot-tbl { width: 100%; border-collapse: collapse; }
        .doc-footer .foot-tbl td { border: none; padding: 0; }
        .doc-footer .foot-l { text-align: left; }
        .doc-footer .foot-r { text-align: right; }

        /* ===== Kartu karyawan ===== */
        /* PENTING (DomPDF): JANGAN bungkus konten kartu yg panjang dengan div
           ber-border. Div ber-border + tabel besar di dalamnya membuat mesin
           paginasi DomPDF salah hitung tinggi -> baris melompat ke halaman
           sendiri & banyak halaman kosong. Kartu dibiarkan mengalir bebas;
           pemisah antar-karyawan pakai page-break-before + garis di header. */
        .emp { margin-bottom: 14px; }
        .emp + .emp { page-break-before: auto; }
        /* Header kartu punya border sendiri agar identitas tetap menonjol; dijaga
           utuh & tak berdiri sendiri di kaki halaman. */
        .emp-head { background: #eef2ff; padding: 7px 10px; border: 1px solid #c7d2fe; border-radius: 6px 6px 0 0; page-break-inside: avoid; }
        .emp-head table { width: 100%; border-collapse: collapse; }
        .emp-head td { border: none; padding: 0; vertical-align: top; }
        .emp-no { display: inline-block; width: 18px; height: 18px; line-height: 18px; text-align: center;
                  background: #4f46e5; color: #fff; border-radius: 50%; font-size: 8px; font-weight: bold; margin-right: 5px; }
        .emp-name { font-weight: bold; font-size: 11.5px; color: #1e1b4b; }
        .emp-code { color: #6366f1; font-weight: bold; font-size: 9px; }
        .emp-meta { color: #475569; font-size: 8px; line-height: 1.5; margin-top: 3px; }
        .emp-meta b { color: #334155; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 10px; font-weight: bold; font-size: 8px; white-space: nowrap; }
        .b-ok   { background: #dcfce7; color: #15803d; }
        .b-rej  { background: #fee2e2; color: #b91c1c; }
        .b-wait { background: #fef3c7; color: #b45309; }
        .b-draft{ background: #f1f5f9; color: #64748b; }

        /* ===== Tabel parameter (semua isi kartu di satu tabel) ===== */
        table.params { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.params thead { display: table-header-group; }
        table.params th, table.params td { border: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.params th { background: #4f46e5; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .02em; }
        table.params td.num, table.params th.num { text-align: center; white-space: nowrap; }
        .param-name { font-weight: bold; color: #1e293b; }
        .param-desc { color: #64748b; font-size: 7.5px; font-style: italic; }
        .target-row b { color: #4338ca; }

        /* Baris judul section di dalam tabel (Parameter / Alur Persetujuan).
           height eksplisit -> cegah DomPDF memberi tinggi sebesar sisa halaman
           saat baris ini jadi konten pertama di halaman baru. */
        td.sec-row { background: #eef2ff; font-weight: bold; font-size: 8px; color: #4338ca;
                     text-transform: uppercase; letter-spacing: .03em; padding: 5px 8px; }
        /* height eksplisit pada <tr> mencegah DomPDF memberi baris pendek tinggi
           sebesar sisa halaman ketika ia jadi baris pertama di halaman baru. */
        /* ===== Alur approval (baris di tabel yg sama) ===== */
        td.flow-cell { font-size: 8px; line-height: 1.5; }
        /* CATATAN DomPDF: .flow-lvl TIDAK boleh display:inline-block. Elemen
           inline-block di dalam baris tabel yang jadi konten pertama di halaman
           baru memicu bug -> baris diberi tinggi sebesar sisa halaman (halaman
           kosong). Cukup beri warna latar inline biasa. */
        .flow-lvl { background: #e0e7ff; color: #4338ca; font-weight: bold;
                    padding: 1px 5px; font-size: 7.5px; }
        .flow-name { font-weight: bold; color: #334155; }
        .flow-mark { font-weight: bold; }
        .ok { color: #15803d; } .rej { color: #b91c1c; } .wait { color: #b45309; }
        .flow-date { color: #94a3b8; font-size: 7.5px; }
        .flow-note { color: #6b7280; font-style: italic; font-size: 7.5px; }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>Monitoring KPI Parameter</h1>
        <div class="meta">{{ $dataset['scope_label'] }} &middot; {{ count($dataset['rows']) }} karyawan &middot; dibuat {{ now()->format('d M Y H:i') }}</div>
        <span class="tag">KPI PARAMETER</span>
    </div>
    {{-- Footer pakai tabel, BUKAN float. float di dalam elemen fixed memicu
         bug DomPDF: halaman pertama jadi kosong & konten terdorong ke hal. 2. --}}
    <div class="doc-footer">
        <table class="foot-tbl">
            <tr>
                <td class="foot-l">HCIS &middot; Export Monitoring KPI Parameter</td>
                <td class="foot-r pg">Halaman </td>
            </tr>
        </table>
    </div>

    @foreach ($dataset['rows'] as $i => $row)
        <div class="emp">
            <div class="emp-head">
                <table>
                    <tr>
                        <td style="width: 70%">
                            <span class="emp-no">{{ $i + 1 }}</span>
                            <span class="emp-name">{{ $row['nama'] }}</span>
                            <span class="emp-code">({{ $row['kode_karyawan'] }})</span>
                            <div class="emp-meta">
                                Tgl Masuk: <b>{{ $row['tgl_masuk'] ?? '-' }}</b><br>
                                <b>{{ $row['divisi'] ?? '-' }}</b> / {{ $row['departemen'] ?? '-' }}
                                &middot; {{ $row['jabatan'] ?? '-' }} ({{ $row['level'] ?? '-' }})
                                &middot; {{ $row['lokasi'] ?? '-' }}<br>
                                No. Transaksi: <b>{{ $row['no_transaksi'] ?? '-' }}</b>
                                @if($row['jadwal']) &middot; Penjadwalan: {{ $row['jadwal'] }} @endif
                            </div>
                        </td>
                        <td style="width: 30%; text-align: right">
                            <span class="badge {{ \Illuminate\Support\Str::contains($row['status'], 'Disetujui') ? 'b-ok' : (\Illuminate\Support\Str::contains($row['status'], 'Ditolak') ? 'b-rej' : (\Illuminate\Support\Str::contains($row['status'], 'Draft') ? 'b-draft' : 'b-wait')) }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Parameter + Alur Persetujuan digabung dalam SATU tabel. DomPDF
                 andal mempaginasi satu tabel, tapi rusak (baris melompat ke
                 halaman kosong) bila ada tabel kedua tepat setelah tabel pertama
                 yang baru saja menyeberang halaman. Maka alur jadi baris penuh
                 di tabel yang sama. --}}
            <table class="params">
                <thead>
                    <tr>
                        <th style="width: 26%">Parameter</th>
                        <th style="width: 13%">BSC</th>
                        <th class="num" style="width: 6%">Bobot</th>
                        <th class="num" style="width: 6%">Satuan</th>
                        <th style="width: 16%">Rumus</th>
                        <th style="width: 12%">Tipe Target</th>
                        <th style="width: 21%">Periode &amp; Nilai Target</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="sec-row">Parameter KPI Diajukan ({{ count($row['parameters']) }})</td></tr>
                    @forelse ($row['parameters'] as $p)
                        <tr>
                            <td>
                                <span class="param-name">{{ $p['nama_parameter'] ?? '-' }}</span>
                                @if($p['deskripsi'])<br><span class="param-desc">{{ $p['deskripsi'] }}</span>@endif
                            </td>
                            <td>{{ $p['bsc'] ?? '-' }}</td>
                            <td class="num">{{ $p['bobot'] }}</td>
                            <td class="num">{{ $p['satuan'] ?? '-' }}</td>
                            <td>{{ $p['rumus'] ?? '-' }}</td>
                            <td>{{ $p['tipe_target'] ?? '-' }}@if($p['kode_target']) ({{ $p['kode_target'] }})@endif</td>
                            <td>
                                @forelse ($p['targets'] as $t)
                                    <span class="target-row">{{ $t['periode'] ?? '-' }} : <b>{{ $t['nilai_target'] }}</b></span>@if(!$loop->last)<br>@endif
                                @empty - @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8">Tidak ada parameter.</td></tr>
                    @endforelse

                    <tr><td class="sec-row">Alur</td><td colspan="6" class="sec-row">Persetujuan</td></tr>
                    {{-- Baris flow memakai 7 sel nyata (tanpa colspan tunggal/lebar):
                         baris ber-colspan yg jadi konten pertama di halaman baru
                         memicu bug DomPDF (tinggi sebesar sisa halaman). Baris
                         dgn 7 sel terisi mempaginasi normal seperti baris param. --}}
                    @forelse ($row['approval_flow'] as $s)
                        @php
                            $cls = $s['status'] === 'Disetujui' ? 'ok' : ($s['status'] === 'Ditolak' ? 'rej' : 'wait');
                            $mark = $s['status'] === 'Disetujui' ? '&#10004;' : ($s['status'] === 'Ditolak' ? '&#10008;' : '&#9203;');
                        @endphp
                        <tr>
                            <td class="flow-cell"><span class="flow-lvl">Level {{ $s['order'] }}</span></td>
                            <td class="flow-cell"><span class="flow-name">{{ $s['approver'] }}</span></td>
                            <td class="flow-cell" colspan="2"><span class="flow-mark {{ $cls }}">{!! $mark !!} {{ $s['status'] }}</span></td>
                            <td class="flow-cell" colspan="2">@if(!empty($s['tanggal']) && $s['status'] !== 'Menunggu')<span class="flow-date">{{ \Carbon\Carbon::parse($s['tanggal'])->isoFormat('D MMM YYYY') }}</span>@else <span style="color:#cbd5e1">&mdash;</span>@endif</td>
                            <td class="flow-cell">@if($s['catatan'])<span class="flow-note">&ldquo;{{ $s['catatan'] }}&rdquo;</span>@else <span style="color:#cbd5e1">&mdash;</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td class="flow-cell" colspan="7" style="color:#94a3b8">Belum ada alur approval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
