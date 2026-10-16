<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contract['title'] }} — Spesifikasi Endpoint Integrasi HCIS</title>
    <meta name="robots" content="noindex, nofollow">

    {{--
        Halaman ini dibuka developer vendor dari jaringan kantor mereka, dan
        kita tidak pernah tahu apa yang diblokir di sana. Karena itu tidak ada
        satu pun aset dari luar: huruf dan Tailwind di-host sendiri.

        Yang digantikan bukan sekadar berkas CSS — <script src="cdn.tailwindcss.com">
        adalah kompiler JIT yang berjalan di peramban setiap kali halaman
        dibuka. Kalau ia terblokir, yang dilihat vendor adalah HTML telanjang
        tanpa gaya sama sekali.

        Menambah kelas utilitas baru WAJIB diikuti membangun ulang berkas
        hasilnya — lihat tailwind.outbound-contracts.config.js.
    --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/hcis-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tailwind/outbound-contracts.css') }}">
    <style>
        pre code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: var(--hcis-slate-100); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--hcis-slate-300); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--hcis-slate-400); }
        html { scroll-behavior: smooth; }

        /* Flowchart Stepper & Connector Styles */
        .flow-stepper-track {
            position: relative;
            padding-left: 2.25rem;
        }
        .flow-stepper-track::before {
            content: '';
            position: absolute;
            left: 0.95rem;
            top: 1.5rem;
            bottom: 1.5rem;
            width: 2px;
            background: linear-gradient(180deg, var(--hcis-indigo-500) 0%, var(--hcis-emerald-500) 50%, var(--hcis-indigo-500) 100%);
            border-radius: 999px;
        }
        .flow-step-node {
            position: absolute;
            left: -2.25rem;
            top: 0.85rem;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--hcis-white);
            box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.1), 0 0 0 4px var(--hcis-white);
            z-index: 10;
            transition: transform 0.2s ease;
        }
        .flow-step-card:hover .flow-step-node {
            transform: scale(1.1);
        }
        .flow-step-node.is-hcis {
            background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-700));
        }
        .flow-step-node.is-hclearn {
            background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-700));
        }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    </style>

    {{--
        Rincian endpoint lahir TERTUTUP, dan tertutupnya ditulis di markup — bukan
        dikatupkan oleh skrip setelah halaman tampil, yang membuat pembaca melihat
        dokumen terbentang sekejap lalu terlipat sendiri di depan matanya.

        Tetapi "tertutup" itu hanya benar selama skripnya jalan. Halaman ini dibuka
        developer vendor dari jaringan yang tidak kita kendalikan, dan tanpa penjaga
        di bawah, satu skrip yang gagal dimuat mengubah dokumen setebal ini menjadi
        deretan judul tanpa isi — tanpa galat, tanpa cara membukanya. Karena itu
        ketiadaan JavaScript membentangkan semuanya kembali: dokumen yang terlalu
        panjang masih bisa dibaca; dokumen yang terkunci tidak.
    --}}
    <noscript>
        <style>
            .accordion-content { display: block !important; }
        </style>
    </noscript>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans antialiased flex flex-col selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation -->
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-30 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                {{--
                    Lambang, bukan tautan. Sebelumnya ini menunjuk /api/contracts
                    yaitu portal internal — pembaca halaman ini adalah developer
                    vendor tanpa akun HCIS, jadi satu-satunya yang mereka temukan
                    di ujung sana adalah halaman login. Tautan yang bermuara di
                    tempat yang tak bisa dimasuki pembacanya lebih buruk daripada
                    tidak ada tautan.
                --}}
                <span class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-md shadow-indigo-500/20" aria-hidden="true">
                    H
                </span>
                <div class="flex items-center space-x-2">
                    <span class="font-bold text-slate-900 tracking-tight text-sm sm:text-base">HCIS Integration Hub</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold">Spesifikasi Request</span>
                </div>
            </div>

            <div class="flex items-center space-x-2.5">
                <button
                    onclick="copyToClipboard(window.location.href, 'Tautan dokumen spesifikasi berhasil disalin!')"
                    class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-sm transition active:scale-95"
                >
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <span>Salin Tautan</span>
                </button>

                <a
                    {{-- Bertanda tangan, dibuat controller. Merangkainya di sini
                         dari slug menghasilkan tautan yang tampak benar tetapi
                         selalu dijawab 403. --}}
                    href="{{ $tautanJson }}"
                    target="_blank"
                    class="inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition active:scale-95"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Raw JSON</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Hero Header -->
        <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    Target: {{ $contract['target_system'] }}
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                    Versi {{ $contract['version'] }}
                </span>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    {{ $contract['status'] }}
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                    📌 {{ count($contract['endpoints'] ?? [$contract]) }} Endpoint Harapan Wajib Disediakan
                </span>
                {{-- Dihitung terpisah, dan sengaja tidak dijumlahkan ke lencana di sebelahnya:
                     callback tidak dibangun pihak ketiga, hanya dipanggil. Menjumlahkannya
                     akan melebih-lebihkan pekerjaan yang kami minta. --}}
                @if (! empty($contract['callbacks']))
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">
                        📥 {{ count($contract['callbacks']) }} Callback ke HCIS (tinggal dipanggil)
                    </span>
                @endif
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                {{ $contract['title'] }}
            </h1>

            <p class="mt-2 text-sm sm:text-base text-slate-600 leading-relaxed max-w-3xl">
                {{ $contract['description'] }}
            </p>
        </div>

        @php
            /*
             * `endpoints` adalah SATU-SATUNYA sumber daftar endpoint, dan sebuah
             * kontrak boleh memuat berapa pun.
             *
             * Di sini dulu ada cadangan yang merakit satu endpoint semu dari
             * field method/endpoint_path/headers/sample_payload di tingkat
             * kontrak. Field-field itu sudah dihapus: nilainya selalu menyalin
             * endpoint PERTAMA, sehingga kontrak berisi dua endpoint punya
             * ringkasan tingkat atas yang diam-diam hanya menggambarkan satu.
             * Bagi klien yang membaca /api/contracts/{slug}/json, itu bukan
             * kelebihan data melainkan undangan untuk mengimplementasikan
             * separuh kontrak dan mengira sudah selesai.
             */
            $endpointList = $contract['endpoints'];

            /*
             * `callbacks` adalah arah sebaliknya: endpoint MILIK HCIS yang dipanggil
             * pihak ketiga. Opsional — kontrak lama tidak memilikinya dan tidak berubah.
             *
             * DUA DAFTAR UNTUK DIBACA, SATU DAFTAR UNTUK DIRENDER.
             *
             * Dipisah di data supaya hitungan "berapa endpoint yang harus saya bangun"
             * tetap menjawab yang benar. Disatukan di sini supaya rinciannya memakai
             * SATU jalur render: dua salinan markup spesifikasi berarti perbaikan yang
             * suatu hari hanya sampai di salah satunya, dan yang tertinggal justru
             * bagian yang lebih jarang dilihat.
             *
             * `_masuk` membalik label yang menyebut arah — tanpa itu, bagian callback
             * akan berbunyi "data yang dikirimkan HCIS" untuk kiriman yang justru
             * datang dari pihak ketiga. `_label` menomori keduanya di ruang yang
             * berbeda (#1.. untuk keluar, Callback A.. untuk masuk) supaya "Endpoint
             * nomor 5" tidak pernah berarti dua hal.
             */
            $callbackList = $contract['callbacks'] ?? [];

            $semuaEndpoint = [];
            foreach ($endpointList as $spec) {
                $spec['_masuk'] = false;
                $spec['_label'] = '#'.(count($semuaEndpoint) + 1);
                $semuaEndpoint[] = $spec;
            }
            foreach ($callbackList as $urutanCb => $spec) {
                $spec['_masuk'] = true;
                $spec['_label'] = 'Callback '.chr(65 + $urutanCb);
                $semuaEndpoint[] = $spec;
            }
        @endphp

        <!-- Alur & Diagram Integrasi (Flowchart Accordion — Default Tertutup) -->
        @if (! empty($contract['flow']))
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden transition-all duration-200" id="flowchart-accordion-container">
            <!-- Accordion Trigger Header -->
            <button
                type="button"
                onclick="toggleFlowchart()"
                class="w-full text-left p-5 sm:p-6 bg-white hover:bg-slate-50/80 flex items-center justify-between gap-4 transition select-none cursor-pointer border-b border-slate-100"
                id="flowchart-trigger"
                aria-expanded="false"
            >
                <div class="flex items-center space-x-3.5 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center shrink-0 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                                Diagram &amp; Alur Integrasi Sistem (Flowchart)
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {{ count($contract['flow']) }} Tahapan
                            </span>
                            <span class="hidden sm:inline-flex px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                HCIS ↔ {{ $contract['target_system'] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5 truncate sm:whitespace-normal">
                            Klik untuk membuka visualisasi alur pembagian tugas antara HCIS &amp; {{ $contract['target_system'] }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-3 shrink-0">
                    <span id="flowchart-toggle-label" class="hidden sm:inline-flex px-3 py-1 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition">
                        Buka Diagram
                    </span>
                    <div class="p-1.5 rounded-lg bg-slate-100 text-slate-500 hover:text-slate-800 transition transform">
                        <svg id="flowchart-chevron" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </button>

            <!-- Collapsible Body (Tertutup / Hidden secara default) -->
            <div id="flowchart-content" class="hidden p-6 sm:p-8 space-y-6 bg-slate-50/40 border-t border-slate-100">
                <!-- Role / Actor Legend Box -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                    <div class="flex items-start space-x-3 p-3 rounded-lg bg-indigo-50/50 border border-indigo-100">
                        <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                            🏛️
                        </span>
                        <div class="text-xs">
                            <strong class="text-indigo-950 font-bold block mb-0.5">Peran HCIS (Sistem Internal):</strong>
                            <span class="text-slate-600 leading-relaxed">Menentukan siklus &amp; relasi penilai, membaca paket soal, mengirim penugasan, dan menerbitkan hasil akhir.</span>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 p-3 rounded-lg bg-emerald-50/50 border border-emerald-100">
                        <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                            🎓
                        </span>
                        <div class="text-xs">
                            <strong class="text-emerald-950 font-bold block mb-0.5">Peran {{ $contract['target_system'] }} (Mitra LMS):</strong>
                            <span class="text-slate-600 leading-relaxed">Membuat token ujian per penugasan, menyajikan formulir ke penilai, dan mengirim skor per butir soal ke HCIS.</span>
                        </div>
                    </div>
                </div>

                <!-- Modern Interactive Stepper Flowchart -->
                <div class="flow-stepper-track space-y-4">
                    @foreach ($contract['flow'] as $i => $step)
                        @php
                            $hcLearn = ($step['aktor'] ?? '') === 'HC Learn' || ($step['aktor'] ?? '') === $contract['target_system'];
                            $nodeClass = $hcLearn ? 'is-hclearn' : 'is-hcis';
                            $cardBorder = $hcLearn ? 'border-emerald-200 hover:border-emerald-300' : 'border-indigo-200 hover:border-indigo-300';
                            $cardHeaderBg = $hcLearn ? 'bg-emerald-50/50' : 'bg-indigo-50/50';
                            $badgeActor = $hcLearn ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200';
                            $actorIcon = $hcLearn ? '🎓' : '🏛️';

                            /* Dicari di SELURUH spesifikasi — keluar maupun masuk — supaya langkah
                               yang menunjuk callback tetap bisa dilompati ke rinciannya. Sebelumnya
                               hanya `endpoints` yang dicari, sehingga langkah callback jatuh ke
                               penanda umum dan pembacanya berhenti tanpa tahu bentuk kirimannya. */
                            $nomor = null;
                            $targetEp = null;
                            foreach ($semuaEndpoint as $n => $spec) {
                                if (($spec['id'] ?? null) === ($step['endpoint'] ?? null)) {
                                    $nomor = $n + 1;
                                    $targetEp = $spec;
                                }
                            }
                        @endphp

                        <div class="relative flow-step-card group">
                            <!-- Stepper Node Dot -->
                            <div class="flow-step-node {{ $nodeClass }}">
                                {{ $i + 1 }}
                            </div>

                            <!-- Step Content Card -->
                            <div class="bg-white border {{ $cardBorder }} rounded-xl shadow-2xs overflow-hidden transition-all duration-200 hover:shadow-md ml-2">
                                <div class="px-4 py-3 {{ $cardHeaderBg }} border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badgeActor }} flex items-center gap-1">
                                            <span>{{ $actorIcon }}</span>
                                            <span>{{ $step['aktor'] ?? 'SISTEM' }}</span>
                                        </span>
                                        <span class="text-xs font-bold text-slate-900">
                                            Tahap {{ $i + 1 }}
                                        </span>
                                    </div>

                                    @if ($nomor !== null && $targetEp)
                                        <button
                                            type="button"
                                            onclick="openAndScrollToAccordion('{{ $targetEp['id'] ?? 'ep-'.($nomor-1) }}')"
                                            class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-md text-xs font-bold {{ empty($targetEp['_masuk']) ? 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' : 'bg-sky-50 text-sky-700 border border-sky-200 hover:bg-indigo-50' }} transition shadow-2xs cursor-pointer"
                                            title="Klik untuk membuka rincian spesifikasi endpoint ini di bawah"
                                        >
                                            <span>{{ empty($targetEp['_masuk']) ? '📌 Endpoint' : '📥' }} {{ $targetEp['_label'] }}: {{ $targetEp['method'] }} {{ $targetEp['path'] }}</span>
                                            <span>&darr;</span>
                                        </button>
                                    @elseif (str_contains(strtolower($step['detail'] ?? ''), 'endpoint ini milik hcis'))
                                        <span class="px-2 py-0.5 rounded text-xs font-mono font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                            🔄 Callback ke HCIS (Inbound)
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">
                                            ⚙️ Proses Internal
                                        </span>
                                    @endif
                                </div>

                                <div class="p-4 space-y-1.5">
                                    <h4 class="text-sm font-bold text-slate-900">
                                        {{ $step['judul'] ?? '' }}
                                    </h4>
                                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                        {{ $step['detail'] ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <!-- 1. Ringkasan Cepat: Daftar Endpoint yang Harapan Wajib Disediakan -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>1. Daftar Endpoint Harapan Wajib Disediakan Pihak Ketiga</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Daftar rute API/Webhook yang menjadi harapan wajib untuk dibuka oleh server pihak ketiga:</p>
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        onclick="toggleAllAccordions(true)"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition"
                    >
                        Buka Semua
                    </button>
                    <button
                        onclick="toggleAllAccordions(false)"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition"
                    >
                        Tutup Semua
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                        <tr>
                            <th class="p-3.5 font-bold w-12 text-center">No</th>
                            <th class="p-3.5 font-bold">Method & Target Endpoint</th>
                            <th class="p-3.5 font-bold">Fungsi & Nama Endpoint</th>
                            <th class="p-3.5 font-bold">Respon Harapan Wajib</th>
                            <th class="p-3.5 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($endpointList as $index => $ep)
                        <tr class="hover:bg-slate-50/80 transition cursor-pointer" onclick="openAndScrollToAccordion('{{ $ep['id'] ?? 'ep-'.$index }}')">
                            <td class="p-3.5 text-center font-bold text-slate-400">{{ $index + 1 }}</td>
                            <td class="p-3.5 font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase {{ $ep['method'] === 'POST' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-sky-50 text-sky-700 border border-sky-200' }}">
                                        {{ $ep['method'] }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ $ep['path'] }}</span>
                                </div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-semibold text-slate-800">{{ $ep['name'] }}</div>
                                <div class="text-slate-500 text-xs mt-0.5 line-clamp-1">{{ $ep['purpose'] }}</div>
                            </td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    HTTP {{ $ep['expected_response']['success']['http_status'] ?? ($ep['expected_response']['http_status'] ?? 200) }} OK
                                </span>
                            </td>
                            <td class="p-3.5 text-right">
                                <span class="inline-flex items-center space-x-1 px-3 py-1 rounded-lg text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition">
                                    <span>Buka Rincian</span>
                                    <span>&darr;</span>
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 1B. Ringkasan Callback: yang HCIS terima dari pihak ketiga -->
        @if (! empty($callbackList))
        <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <span>1B. Callback yang HCIS Terima — Anda Memanggil, Bukan Membangun</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Rute berikut sudah berdiri di sisi HCIS. Yang kami butuhkan bukan endpoint baru dari Anda,
                    melainkan bentuk kiriman yang tepat — dan bentuk itulah yang menentukan nilai bisa dihitung atau tidak.
                </p>
            </div>

            <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                        <tr>
                            <th class="p-3.5 font-bold w-12 text-center">No</th>
                            <th class="p-3.5 font-bold">Method &amp; Alamat di HCIS</th>
                            <th class="p-3.5 font-bold">Kapan Dipanggil</th>
                            <th class="p-3.5 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($callbackList as $urutanCb => $cb)
                        <tr class="hover:bg-slate-50/80 transition cursor-pointer" onclick="openAndScrollToAccordion('{{ $cb['id'] ?? 'cb-'.$urutanCb }}')">
                            <td class="p-3.5 text-center font-bold text-slate-400">{{ chr(65 + $urutanCb) }}</td>
                            <td class="p-3.5 font-mono">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase {{ $cb['method'] === 'POST' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-sky-50 text-sky-700 border border-sky-200' }}">
                                        {{ $cb['method'] }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ $cb['path'] }}</span>
                                </div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-semibold text-slate-800">{{ $cb['name'] }}</div>
                                <div class="text-slate-500 text-xs mt-0.5 line-clamp-1">{{ $cb['trigger_event'] ?? '' }}</div>
                            </td>
                            <td class="p-3.5 text-right">
                                <span class="inline-flex items-center space-x-1 px-3 py-1 rounded-lg text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition">
                                    <span>Buka Rincian</span>
                                    <span>&darr;</span>
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        <!-- 2. Rincian Spesifikasi dalam Format Collapsible Accordion -->
        <section class="space-y-4">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider">
                    2. Rincian Spesifikasi & Kebutuhan Endpoint
                </h2>
                <span class="text-xs text-slate-500">Klik header endpoint untuk buka/tutup rincian</span>
            </div>

            @foreach ($semuaEndpoint as $index => $ep)
            @php $masuk = ! empty($ep['_masuk']); @endphp
            <div id="{{ $ep['id'] ?? 'ep-'.$index }}" class="endpoint-accordion bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden transition-all duration-200 scroll-mt-20">
                <!-- Accordion Header (Clickable) -->
                <button
                    type="button"
                    onclick="toggleAccordion('{{ $ep['id'] ?? 'ep-'.$index }}')"
                    class="w-full text-left p-5 sm:p-6 bg-white hover:bg-slate-50/80 border-b border-slate-100 flex items-center justify-between gap-4 transition select-none"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 overflow-hidden">
                        <div class="flex items-center space-x-2 shrink-0">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $masuk ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-700 border border-slate-300' }}">
                                {{ $ep['_label'] ?? '#'.($index + 1) }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded text-xs font-mono font-bold uppercase {{ $ep['method'] === 'POST' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-sky-50 text-sky-700 border border-sky-200' }}">
                                {{ $ep['method'] }}
                            </span>
                        </div>

                        <div class="overflow-hidden">
                            <div class="font-mono text-sm font-bold text-slate-900 truncate">
                                {{ $ep['path'] }}
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5 font-sans font-medium truncate">
                                {{ $ep['name'] }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3 shrink-0">
                        {{-- Hanya bila angkanya memang ada. Cadangan 'Maks 3.0s' yang dulu
                             berdiri di sini membuat endpoint TANPA SLA tetap memampangkan
                             angka yang tak seorang pun pernah menjanjikannya — dan bagi
                             pembaca di seberang itu tidak terbaca sebagai bawaan template,
                             melainkan sebagai komitmen kami. --}}
                        @if (! empty($ep['sla_timeout']))
                            <span class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                ⏱️ SLA: {{ $ep['sla_timeout'] }}
                            </span>
                        @endif
                        <div class="accordion-icon p-1.5 rounded-lg bg-slate-100 text-slate-500 hover:text-slate-800 transition transform">
                            <svg class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </button>

                <!-- Accordion Body (Collapsible Content) — lahir tertutup, lihat <noscript> di kepala -->
                <div class="accordion-content hidden p-6 sm:p-8 space-y-6 bg-white border-t border-slate-100">

                    <!-- Skenario Pemicu -->
                    <div class="bg-indigo-50/50 border border-indigo-100 rounded-xl p-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900 mb-1 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ $masuk ? 'Kapan HC Learn Memanggil Endpoint HCIS Ini?' : 'Kapan Endpoint Ini Dipanggil Oleh HCIS?' }}</span>
                        </h4>
                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                            {{ $ep['purpose'] }}
                        </p>
                    </div>

                    <!-- Bagian A: Data yang Dikirimkan oleh HCIS (Request Data) -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2 border-b border-slate-100 pb-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            <span>{{ $masuk ? 'A. Data yang Anda Kirimkan ke HCIS (Request Body)' : 'A. Data yang Dikirimkan oleh HCIS (Request Data)' }}</span>
                        </h4>

                        <!-- Headers Table -->
                        <div>
                            <h5 class="text-xs font-bold text-slate-600 mb-2">{{ $masuk ? 'HTTP Request Headers yang Wajib Anda Sertakan:' : 'HTTP Request Headers yang Dikirimkan HCIS:' }}</h5>
                            <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-xl">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                                        <tr>
                                            <th class="p-2.5 font-bold">Header</th>
                                            <th class="p-2.5 font-bold">Tipe</th>
                                            <th class="p-2.5 font-bold">Harapan Wajib</th>
                                            <th class="p-2.5 font-bold">Contoh Nilai</th>
                                            <th class="p-2.5 font-bold">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($ep['headers'] as $hdr)
                                        <tr>
                                            <td class="p-2.5 font-mono font-bold text-indigo-700">{{ $hdr['name'] }}</td>
                                            <td class="p-2.5 font-mono text-slate-500">{{ $hdr['type'] }}</td>
                                            <td class="p-2.5">
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $hdr['required'] ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-600' }}">
                                                    {{ $hdr['required'] ? 'Harapan Wajib' : 'Opsional' }}
                                                </span>
                                            </td>
                                            <td class="p-2.5 font-mono text-slate-700 truncate max-w-xs">{{ $hdr['example'] }}</td>
                                            <td class="p-2.5 text-slate-600">{{ $hdr['description'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Parameters Table (Jika ada) -->
                        @if (!empty($ep['payload_fields']))
                        <div>
                            <h5 class="text-xs font-bold text-slate-600 mb-2">Struktur Parameter Payload JSON:</h5>
                            <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-xl">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
                                        <tr>
                                            <th class="p-2.5 font-bold">Field JSON</th>
                                            <th class="p-2.5 font-bold">Tipe Data</th>
                                            <th class="p-2.5 font-bold">Harapan Wajib</th>
                                            <th class="p-2.5 font-bold">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($ep['payload_fields'] as $fld)
                                        <tr>
                                            <td class="p-2.5 font-mono font-bold text-slate-800">{{ $fld['field'] }}</td>
                                            <td class="p-2.5 font-mono text-indigo-600">{{ $fld['type'] }}</td>
                                            <td class="p-2.5">
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $fld['required'] ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-600' }}">
                                                    {{ $fld['required'] ? 'Ya' : 'Tidak' }}
                                                </span>
                                            </td>
                                            <td class="p-2.5 text-slate-600">{{ $fld['description'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Sample JSON Payload -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="text-xs font-bold text-slate-600">Contoh Utuh JSON Payload yang Dikirimkan HCIS:</h5>
                                <button
                                    onclick="copyToClipboard(document.getElementById('payload-code-{{ $index }}').innerText, 'Sample JSON payload berhasil disalin!')"
                                    class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-md text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition"
                                >
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <span>Salin JSON Payload</span>
                                </button>
                            </div>
                            <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-indigo-200 overflow-x-auto custom-scrollbar max-h-64"><code id="payload-code-{{ $index }}">{{ json_encode($ep['sample_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    </div>

                    <!-- Bagian B: Respon Harapan Wajib dari Pihak Ketiga -->
                    <div class="space-y-4 pt-2">
                        <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2 border-b border-slate-100 pb-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                            <span>{{ $masuk ? 'B. Respon yang HCIS Kembalikan kepada Anda' : 'B. Respon Harapan Wajib dari Server Pihak Ketiga (Expected Response)' }}</span>
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Format Respon Sukses -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>1. Format Respon Sukses (Harapan Wajib HTTP {{ $ep['expected_response']['success']['http_status'] ?? ($ep['expected_response']['http_status'] ?? 200) }})</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        200 OK
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600">
                                    {{ $ep['expected_response']['success']['description'] ?? ($masuk ? 'HCIS mengembalikan HTTP 200 dengan format JSON di bawah ini bila kiriman diterima.' : 'Server pihak ketiga diharapkan mengembalikan HTTP 200 dengan format JSON di bawah ini jika data berhasil diproses.') }}
                                </p>
                                <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-emerald-300 overflow-x-auto custom-scrollbar max-h-48"><code>{{ json_encode($ep['expected_response']['success']['sample'] ?? ($ep['expected_response']['sample'] ?? ['status' => 'success']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>

                            <!-- Format Respon Gagal / Validasi Error -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-rose-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span>2. Format Respon Gagal / Validasi Ditolak (HTTP 4xx)</span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        422 / 400
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600">
                                    {{ $ep['expected_response']['error']['description'] ?? ($masuk ? 'HCIS mengembalikan HTTP 422 atau 400 bila bentuk kiriman tidak sah.' : 'Kembalikan HTTP 422 atau 400 jika data tidak valid atau entitas tidak ditemukan di database pihak ketiga.') }}
                                </p>
                                <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-rose-300 overflow-x-auto custom-scrollbar max-h-48"><code>{{ json_encode($ep['expected_response']['error']['sample'] ?? ['status' => 'error', 'code' => 422, 'message' => 'Validation error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian C: Aturan Percobaan Ulang (Retry Policy) -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs space-y-2">
                        <h5 class="font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ $masuk ? 'Percobaan Ulang yang Kami Sarankan untuk HC Learn (Retry Policy):' : 'Kebijakan Percobaan Ulang HCIS (Retry Policy):' }}</span>
                        </h5>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-slate-600">
                            <div>
                                <span class="font-semibold text-slate-800">Maksimal Percobaan Ulang:</span> {{ $ep['retry_policy']['max_attempts'] ?? '5' }} kali (Interval: {{ $ep['retry_policy']['backoff'] ?? 'Exponential backoff' }})
                            </div>
                            <div>
                                <span class="font-semibold text-emerald-700">Dicoba Ulang Jika (Retryable):</span> {{ $ep['retry_policy']['retryable_codes'] ?? 'HTTP 500, 502, 503, 504, Timeout' }}
                            </div>
                            <div class="sm:col-span-2">
                                <span class="font-semibold text-rose-700">Langsung Dibatalkan / Fatal (Non-retryable):</span> {{ $ep['retry_policy']['non_retryable_codes'] ?? 'HTTP 400, 401, 404, 422' }}
                            </div>
                        </div>
                    </div>

                    <!-- Bagian D: cURL Generator & Simulasi Uji Coba Terminal -->
                    <div class="bg-indigo-50/40 border-2 border-indigo-200 rounded-2xl p-5 space-y-3.5 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-100 pb-3">
                            <div class="flex items-center space-x-2">
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase bg-indigo-600 text-white flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>💻 Simulasi Eksekusi cURL (Uji Coba Terminal)</span>
                                </span>
                                <span class="text-xs text-slate-600 hidden sm:inline">{{ $masuk ? '— Simulasi pengiriman request dari HC Learn ke HCIS' : '— Simulasi pengiriman request dari HCIS' }}</span>
                            </div>
                            <button
                                onclick="copyCurlForEndpoint('{{ $ep['method'] }}', '{{ $ep['path'] }}', {{ json_encode($ep['sample_payload']) }})"
                                class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-white hover:bg-indigo-50 border border-indigo-200 shadow-sm transition active:scale-95"
                            >
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span>Salin Perintah cURL</span>
                            </button>
                        </div>
                        <div class="text-xs text-slate-600 leading-relaxed">
                            <strong class="text-slate-800">Petunjuk Uji Coba Terminal:</strong>
                            @if ($masuk)
                                Salin perintah cURL di bawah ini untuk melihat bentuk kiriman yang HCIS harapkan.
                                Ganti alamatnya dengan alamat HCIS yang Anda pakai, dan lengkapi header keamanannya —
                                contoh ini sengaja tidak memuat tanda tangan yang sah, karena tanda tangan hanya bisa
                                dihitung dari body yang benar-benar Anda kirim:
                            @else
                                Salin perintah cURL di bawah ini dan jalankan di terminal lokal atau Postman Anda untuk menguji apakah endpoint yang Anda bangun di server pihak ketiga sudah dapat menerima dan memverifikasi payload dari HCIS:
                            @endif
                        </div>
                        <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-emerald-300 overflow-x-auto custom-scrollbar"><code>curl -X {{ $ep['method'] }} "http://localhost:8000{{ $ep['path'] }}" \
  -H "Content-Type: application/json" \
  -H "X-HC-Timestamp: {{ time() }}" \
  -H "X-HC-Signature: sha256=d58c1a978f6580e6c64b5003c270389d38096c43499fa69b6e82845c48b26f58" \
  -H "User-Agent: HCIS-Outbound-Dispatcher/1.0" \
  -d '{{ str_replace("'", "'\\''", json_encode($ep['sample_payload'])) }}'</code></pre>
                    </div>
                </div>
            </div>
            @endforeach
        </section>

        <!-- 3. Panduan Verifikasi Tanda Tangan Keamanan (HMAC-SHA256) -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-indigo-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider">3. Panduan Verifikasi Keamanan (HMAC-SHA256)</h2>
                </div>
                <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    HMAC-SHA256
                </span>
            </div>

            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Untuk memastikan bahwa request benar-benar berasal dari server HCIS dan tidak diubah di tengah jalan, setiap request menyertakan header <code class="text-indigo-700 font-mono bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">X-HC-Signature</code> dan <code class="text-indigo-700 font-mono bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">X-HC-Timestamp</code>. Gunakan cuplikan kode di bawah ini pada backend pihak ketiga Anda:
            </p>

            <!-- Language Snippet Tabs -->
            <div class="mt-4">
                <div class="flex items-center space-x-2 border-b border-slate-200 pb-2">
                    <button onclick="switchLangTab('php', this)" class="lang-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm transition">PHP / Laravel</button>
                    <button onclick="switchLangTab('node', this)" class="lang-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200 border border-slate-200 transition">Node.js / Express</button>
                    <button onclick="switchLangTab('python', this)" class="lang-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200 border border-slate-200 transition">Python</button>
                </div>

                <!-- PHP Snippet -->
                <div id="snippet-php" class="lang-snippet mt-3">
                    <div class="relative group">
                        <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-indigo-200 overflow-x-auto custom-scrollbar"><code>// Contoh verifikasi HMAC di Laravel Controller / Middleware
$rawPayload = $request->getContent();
$signatureHeader = $request->header('X-HC-Signature'); // Format: "sha256=..."
$timestamp = $request->header('X-HC-Timestamp');
$secret = env('{{ $contract['security']['secret_env_name'] ?? "WEBHOOK_SHARED_SECRET" }}');

// 1. Cek timestamp replay attack (toleransi maksimal 300 detik)
if (abs(time() - (int)$timestamp) > 300) {
    return response()->json(['status' => 'error', 'message' => 'Timestamp expired'], 401);
}

// 2. Hitung expected signature dari raw string body
$expectedSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);

// 3. Bandingkan dengan timing-safe hash comparison
if (!hash_equals($expectedSignature, (string)$signatureHeader)) {
    return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
}

// Validasi sukses -> Lanjutkan proses $request->json()->all()
return response()->json(['status' => 'success', 'code' => 200, 'message' => 'Processed successfully']);</code></pre>
                        <button onclick="copySnippet('snippet-php')" class="absolute top-3 right-3 text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 py-1 rounded-md border border-slate-700 transition">Salin Kode</button>
                    </div>
                </div>

                <!-- Node.js Snippet -->
                <div id="snippet-node" class="lang-snippet mt-3 hidden">
                    <div class="relative group">
                        <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-indigo-200 overflow-x-auto custom-scrollbar"><code>// Contoh verifikasi HMAC di Node.js / Express
const crypto = require('crypto');

function verifyHcisWebhook(req, res, next) {
    const signature = req.headers['x-hc-signature'];
    const timestamp = parseInt(req.headers['x-hc-timestamp'] || '0', 10);
    const secret = process.env.{{ $contract['security']['secret_env_name'] ?? "WEBHOOK_SHARED_SECRET" }};
    const rawBody = req.rawBody || JSON.stringify(req.body);

    // 1. Replay attack check (toleransi 5 menit)
    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - timestamp) > 300) {
        return res.status(401).json({ status: 'error', message: 'Timestamp expired' });
    }

    // 2. Hitung HMAC-SHA256
    const expected = 'sha256=' + crypto.createHmac('sha256', secret).update(rawBody).digest('hex');

    // 3. Safe comparison
    if (signature !== expected) {
        return res.status(401).json({ status: 'error', message: 'Invalid signature' });
    }

    next();
}</code></pre>
                        <button onclick="copySnippet('snippet-node')" class="absolute top-3 right-3 text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 py-1 rounded-md border border-slate-700 transition">Salin Kode</button>
                    </div>
                </div>

                <!-- Python Snippet -->
                <div id="snippet-python" class="lang-snippet mt-3 hidden">
                    <div class="relative group">
                        <pre class="bg-slate-900 p-4 rounded-xl border border-slate-800 font-mono text-xs text-indigo-200 overflow-x-auto custom-scrollbar"><code># Contoh verifikasi HMAC di Python / FastAPI / Flask
import hmac
import hashlib
import time
from fastapi import Request, HTTPException

async def verify_hcis_signature(request: Request):
    signature = request.headers.get("X-HC-Signature", "")
    timestamp = int(request.headers.get("X-HC-Timestamp", 0))
    secret = "{{ $contract['security']['secret_env_name'] ?? "WEBHOOK_SHARED_SECRET" }}".encode("utf-8")
    
    # 1. Replay attack check (5 menit)
    if abs(int(time.time()) - timestamp) > 300:
        raise HTTPException(status_code=401, detail="Timestamp expired")
        
    raw_body = await request.body()
    expected = "sha256=" + hmac.new(secret, raw_body, hashlib.sha256).hexdigest()
    
    if not hmac.compare_digest(signature, expected):
        raise HTTPException(status_code=401, detail="Invalid signature")</code></pre>
                        <button onclick="copySnippet('snippet-python')" class="absolute top-3 right-3 text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-2.5 py-1 rounded-md border border-slate-700 transition">Salin Kode</button>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        <p>HCIS Integration Hub — Outbound Integration Specification Contract</p>
    </footer>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 z-50 hidden transform transition-all duration-300 translate-y-2 opacity-0">
        <div class="bg-slate-900 text-white px-4 py-2.5 rounded-xl shadow-2xl flex items-center space-x-2.5 text-xs font-medium">
            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span id="toastMessage">Copied!</span>
        </div>
    </div>

    <script>
        function toggleFlowchart() {
            const content = document.getElementById('flowchart-content');
            const icon = document.getElementById('flowchart-chevron');
            const label = document.getElementById('flowchart-toggle-label');
            const trigger = document.getElementById('flowchart-trigger');
            if (!content) return;

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                if (icon) icon.classList.add('rotate-180');
                if (label) label.innerText = 'Tutup Diagram';
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
            } else {
                content.classList.add('hidden');
                if (icon) icon.classList.remove('rotate-180');
                if (label) label.innerText = 'Buka Diagram Alur';
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        }

        function toggleAccordion(id) {
            const acc = document.getElementById(id);
            if (!acc) return;

            const content = acc.querySelector('.accordion-content');
            const icon = acc.querySelector('.accordion-icon svg');

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }

        function toggleAllAccordions(open) {
            document.querySelectorAll('.endpoint-accordion').forEach(acc => {
                const content = acc.querySelector('.accordion-content');
                const icon = acc.querySelector('.accordion-icon svg');
                if (open) {
                    content.classList.remove('hidden');
                    icon.classList.add('rotate-180');
                } else {
                    content.classList.add('hidden');
                    icon.classList.remove('rotate-180');
                }
            });
        }

        function openAndScrollToAccordion(id) {
            const acc = document.getElementById(id);
            if (!acc) return;

            const content = acc.querySelector('.accordion-content');
            const icon = acc.querySelector('.accordion-icon svg');
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');

            acc.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function switchLangTab(lang, btn) {
            document.querySelectorAll('.lang-tab-btn').forEach(b => {
                b.classList.remove('bg-indigo-600', 'text-white');
                b.classList.add('bg-slate-100', 'text-slate-600', 'border', 'border-slate-200');
            });
            btn.classList.add('bg-indigo-600', 'text-white');
            btn.classList.remove('bg-slate-100', 'text-slate-600', 'border', 'border-slate-200');

            document.querySelectorAll('.lang-snippet').forEach(s => s.classList.add('hidden'));
            const target = document.getElementById('snippet-' + lang);
            if (target) target.classList.remove('hidden');
        }

        function copySnippet(snippetId) {
            const el = document.querySelector('#' + snippetId + ' code');
            if (el) {
                copyToClipboard(el.innerText, 'Cuplikan kode berhasil disalin!');
            }
        }

        function copyToClipboard(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(message || 'Disalin!');
            }).catch(() => {
                const input = document.createElement('textarea');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showToast(message || 'Disalin!');
            });
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toastMessage');
            toastMsg.innerText = msg;

            toast.classList.remove('hidden', 'translate-y-2', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');

            setTimeout(() => {
                toast.classList.add('translate-y-2', 'opacity-0');
                setTimeout(() => toast.classList.add('hidden'), 300);
            }, 2500);
        }

        function copyCurlForEndpoint(method, path, payload) {
            const timestamp = Math.floor(Date.now() / 1000);
            const dummySig = 'sha256=d58c1a978f6580e6c64b5003c270389d38096c43499fa69b6e82845c48b26f58';
            const payloadStr = JSON.stringify(payload);

            const curl = `curl -X ${method} "http://localhost:8000${path}" \\
  -H "Content-Type: application/json" \\
  -H "X-HC-Timestamp: ${timestamp}" \\
  -H "X-HC-Signature: ${dummySig}" \\
  -H "User-Agent: HCIS-Outbound-Dispatcher/1.0" \\
  -d '${payloadStr.replace(/'/g, "'\\''")}'`;

            copyToClipboard(curl, 'Perintah cURL berhasil disalin ke clipboard!');
        }

        // Initialize: by default all accordions are open so developer can easily read, but can collapse anytime
        // (matching OpenAPI/Swagger UI UX)
    </script>
</body>
</html>
