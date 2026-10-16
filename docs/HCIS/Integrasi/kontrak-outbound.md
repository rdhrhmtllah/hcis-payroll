# Kontrak Integrasi Outbound

Spesifikasi endpoint yang **HCIS minta disediakan** oleh sistem pihak ketiga — kebalikan arah dari [dokumentasi OpenAPI](dokumentasi-openapi.md), yang mendokumentasikan endpoint HCIS untuk dipanggil pihak luar.

| | |
|---|---|
| Portal internal | `GET /system/integrasi/kontrak-outbound` — halaman Inertia, gerbang `['auth','system.admin']` |
| Viewer publik | `GET /api/contracts/{slug}` — Blade mandiri, **sengaja tanpa auth** |
| JSON kontrak | `GET /api/contracts/{slug}/json` — yang dibaca otomasi/CI klien |
| Alamat lama | `/api/contracts` dan `/api/docs/requests` → pengalihan 302 ke portal |
| Sumber data | `app/Services/SYSTEM/Integration/OutboundContractService.php` (array keras, belum di DB) |
| Halaman | `resources/js/Pages/SYSTEM/Integration/OutboundContracts/OutboundContractsPage.vue` |
| Uji | `tests/Feature/SYSTEM/Integration/OutboundContractsTest.php`, `OutboundContractIsiAmanTest.php` |

## Dua permukaan, dua bentuk

Pembelahan ini adalah inti fiturnya, bukan warisan yang belum sempat diseragamkan.

**Portal manajemen** dibaca orang dalam, jadi ia halaman Inertia di dalam shell. Bentuk itu bukan selera: halaman non-Inertia di balik `auth` di dalam aplikasi Inertia akan tampil **di dalam modal galat** setelah login, karena klien Inertia mengikuti `redirect()->intended()` sebagai XHR ber-header `X-Inertia` lalu menerima HTML polos.

Gerbangnya `system.admin`, bukan `check.jabatan` — grup fitur SYSTEM tidak dipegang satu pun role di `N_HRIS_Role_Functional`, sehingga `check.jabatan` justru gerbang yang tak seorang pun bisa lewati. Polanya mengikuti `routes/SYSTEM/MasterData/Entitas.php`. Konsekuensinya halaman ini **tidak muncul di menu** dan **tidak butuh baris `HRIS_Page_Access`**; ia dibuka lewat alamatnya oleh akun ber-`KPI_Users.Is_System_Admin = 'Y'`.

**Viewer kontrak** dibaca developer vendor yang tidak punya akun HCIS, jadi ia dokumen Blade mandiri tanpa auth. Dua akibat yang mudah terlupakan:

- Halaman viewer **tidak boleh menautkan apa pun ke halaman internal**. Pembacanya hanya akan menemukan halaman login di ujung sana.
- Halaman viewer **tidak boleh menarik aset dari CDN**. Ia dibuka dari jaringan kantor vendor yang tak kita kendalikan; satu CDN terblokir berarti HTML telanjang tanpa gaya, dan kita tak akan pernah melihat gejalanya. Tailwind-nya dibangun sekali lalu di-commit — lihat `tailwind.outbound-contracts.config.js`.

## Bentuk kontrak

`endpoints[]` adalah **satu-satunya** sumber daftar endpoint. Satu kontrak = satu permintaan integrasi ke satu sistem, dan boleh memuat berapa pun endpoint.

Field berikut **dilarang** di tingkat kontrak karena milik endpoint: `method`, `endpoint_path`, `trigger_event`, `headers`, `sample_payload`, `expected_response`, `retry_policy`.

Field-field itu dulu ada di sana dan nilainya selalu menyalin endpoint **pertama**. Untuk kontrak berisi dua endpoint, JSON yang dikirim ke klien punya dua sumber kebenaran dan yang satu diam-diam hanya menggambarkan separuhnya — undangan bagi vendor untuk mengerjakan setengah kontrak lalu mengira selesai.

Yang tinggal di tingkat kontrak hanyalah yang berlaku kontrak-lebar: `slug`, `title`, `target_system`, `category`, `version`, `status`, `last_updated`, `summary`, `description`, `security`.

## Menambah kontrak baru

1. Tambahkan entri di `OutboundContractService::definisi()`. `slug` harus cocok `[a-z0-9][a-z0-9-]*` — pembatasnya ada di lapis routing, jadi slug di luar bentuk ditolak 404 sebelum menyentuh controller.
2. Isi `endpoints[]`. Minimal satu; tidak ada batas atas.
3. Jalankan penjaganya:

   ```
   php artisan test tests/Feature/SYSTEM/Integration/OutboundContractsTest.php
   RUN_KONTRAK_PII_TEST=true php artisan test tests/Feature/SYSTEM/Integration/OutboundContractIsiAmanTest.php
   ```

## Aturan isi — halaman ini terbit ke internet

Setiap nilai contoh yang ditulis di dalam kontrak ikut terbaca siapa saja. Yang dijaga otomatis oleh `OutboundContractIsiAmanTest`:

- **Email contoh wajib berdomain dokumentasi** RFC 2606 (`example.com`/`.net`/`.org`). Domain sungguhan berarti kotak surat sungguhan.
- **Tidak boleh ada nilai rahasia** — baik lewat nama kunci (`secret`, `api_secret`, `password`, `private_key`, `access_token`, …) maupun lewat bentuk nilai kredensial sungguhan (PEM, `AKIA…`, `sk_live_…`, `ghp_…`, `xox*-…`, JWT).
- **Tidak boleh cocok dengan karyawan sungguhan** — `Kode_Karyawan`, `NIK`, `Nama`, `HP`, `Email`. Ini satu-satunya yang tak bisa disimpulkan dari bentuk: NIK contoh terlihat persis sama dengan NIK sungguhan. Digerbangi `RUN_KONTRAK_PII_TEST=true` karena butuh DB.

Yang justru **dianjurkan**: menyebut NAMA variabel lingkungan tempat rahasia disimpan (`'secret_env_name' => 'HC_LEARN_SHARED_SECRET'`). Itu menunjukkan vendor di mana kuncinya tanpa memuat kuncinya. Contoh tanda tangan HMAC juga bukan rahasia — tanda tangan memang dikirim polos di setiap permintaan.

## Kalau daftar ini pindah ke database

`OutboundContractService::all()` memoisasi hasil `definisi()` karena satu permintaan halaman daftar memanggilnya tiga kali (`all` + `targetSystems` + `categories`) dan `findBySlug()` memindai linear. Untuk tiga kontrak keras biayanya sepele; polanya yang tidak sepele. Saat pindah ke DB, ganti pemindaian linear itu lebih dulu.
