<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Tautan Tidak Berlaku — Spesifikasi Integrasi HCIS</title>

    {{--
        Dibaca developer vendor dari jaringan kantor mereka, jadi aturannya sama
        dengan halaman viewer: nol aset dari luar.
    --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/hcis-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tailwind/outbound-contracts.css') }}">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans antialiased flex items-center justify-center px-4">

    <main class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-sm p-8 text-center">
        <div class="mx-auto h-12 w-12 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center mb-5">
            <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.1-1.1M10.172 13.828a4 4 0 005.656 0l3-3a4 4 0 10-5.656-5.656l-1.1 1.1" />
            </svg>
        </div>

        <h1 class="text-lg font-bold text-slate-900">Tautan Ini Tidak Berlaku</h1>

        <p class="mt-3 text-sm text-slate-600 leading-relaxed">
            Spesifikasi integrasi hanya bisa dibuka lewat tautan resmi yang diterbitkan tim HCIS.
            Tautan yang Anda buka tidak dikenali — kemungkinan besar terpotong saat disalin,
            atau memang sudah tidak berlaku.
        </p>

        <p class="mt-4 text-sm text-slate-600 leading-relaxed">
            Silakan minta tautan baru kepada tim HCIS yang menghubungi Anda.
        </p>

        <p class="mt-6 text-xs text-slate-400">
            Kode: 403 · Tanda tangan tautan tidak sah
        </p>
    </main>

</body>
</html>
