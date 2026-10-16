<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title> @yield('title')</title>

{{--
    Font dijemput duluan, sebelum satu pun stylesheet.

    Tanpa ini urutannya panjang: HTML diurai → <link> CSS diunduh → CSS diurai →
    baru @font-face-nya ketahuan → font diunduh. Sepanjang rantai itu teks sudah
    tergambar dengan huruf cadangan, lalu berganti begitu Nunito tiba. Itulah
    "masuk pakai font apa dulu, baru berubah".

    Preload memotongnya: unduhan font mulai di baris ini, berbarengan dengan CSS,
    jadi biasanya sudah siap sebelum ada teks yang tergambar.

    crossorigin WAJIB ada meski satu asal — tanpanya browser menganggap ini
    permintaan berbeda dari yang dilakukan @font-face, lalu mengunduh dua kali.

    Hanya bobot yang benar-benar tampak di layar pertama: 400 teks isi, 600 dan
    700 untuk judul dan tombol. Menambah bobot lain di sini justru memperlambat,
    karena tiap berkas ikut merebut antrean unduhan yang sama.
--}}
<link rel="preload" as="font" type="font/ttf" crossorigin
      href="/assets/static/fonts/inter/Inter_18pt-Regular.ttf">
<link rel="preload" as="font" type="font/ttf" crossorigin
      href="/assets/static/fonts/inter/Inter_18pt-SemiBold.ttf">
<link rel="preload" as="font" type="font/ttf" crossorigin
      href="/assets/static/fonts/inter/Inter_18pt-Bold.ttf">
<link rel="icon" href="{{ asset('logo/logo.png') }}" sizes="any" />
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('logo/logo.png') }}" />
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('logo/logo.png') }}" />
<link rel="apple-touch-icon" href="{{ asset('logo/logo.png') }}" />
<link rel="shortcut icon" href="{{ asset('logo/logo.png') }}" type="image/png" />
<link rel="shortcut icon" href="/assets/compiled/png/logo.png" type="image/x-icon">
<link rel="stylesheet" href="/assets/extensions/bootstrap-icons/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/compiled/css/app.css">
{{--
    app-dark.css DIBUANG — 251 KB yang tidak pernah cocok dengan apa pun.

    Seluruh isinya berlingkup html[data-bs-theme=dark]. Atribut itu disetel di
    tiga layout Blade, dan ketiganya menyetelnya ke "light". Tidak ada satu pun
    berkas — Blade maupun Vue — yang pernah menyetelnya ke "dark".

    Jadi berkas ini diunduh dan diurai sebelum piksel pertama di SETIAP
    kunjungan, lalu tidak menghasilkan satu aturan pun yang berlaku. Berkas ini
    di-include app.blade.php, jadi biayanya ditanggung aplikasi Vue juga, bukan
    cuma 27 halaman Blade yang tersisa.

    Kalau nanti tema gelap benar-benar dibuat, jangan hidupkan lagi baris ini:
    design system sudah punya lapis tema sendiri di design-system.css.
--}}
<link rel="stylesheet" href="/assets/compiled/css/iconly.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
@vite(['resources/css/shell.css'])
<style>
    /* Critical shell layout to prevent first-paint flicker */
    #app1 > #main {
        margin-left: 5rem;
    }

    .shell-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 5rem;
        height: 100vh;
        z-index: 1040;
    }

    .shell-topbar {
        position: sticky;
        top: 0;
        z-index: 1020;
        margin-left: 5rem;
        width: calc(100% - 5rem);
    }

    @media (max-width: 991.98px) {
        #app1 > #main {
            margin-left: 0 !important;
        }

        .shell-topbar {
            margin-left: 0 !important;
            width: 100% !important;
        }

        .shell-sidebar {
            width: min(20rem, 86vw) !important;
            transform: translateX(-100%);
        }

        .shell-sidebar.is-mobile-open {
            transform: translateX(0);
        }
    }
</style>
@stack('shellStyles')
