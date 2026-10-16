# Dokumentasi OpenAPI API pihak ketiga

Halaman Swagger UI yang isinya disaring menurut hak akses konsumen.

| | |
|---|---|
| Halaman | `GET /api/docs` — publik, tetapi tidak memuat satu pun nama endpoint |
| Dokumen | `GET /api/docs/openapi.json` — wajib header `X-HC-Public` |
| Sumber skema | `resources/openapi/hcis-third-party.yaml` |
| Penyaring | `app/Services/SYSTEM/Integration/OpenApiRegistryFilter.php` |
| Uji | `tests/Unit/SYSTEM/Integration/OpenApiRegistryFilterTest.php`, `tests/Feature/SYSTEM/Integration/ApiDocsTest.php` |

## Cara kerjanya

YAML memuat **seluruh** endpoint pihak ketiga dan sengaja tidak tahu siapa boleh
apa. Saat diminta, path-nya disaring memakai
`N_HRIS_Api_Akses_Endpoint` × `N_HRIS_Api_Endpoint` — **relasi yang sama persis
dengan yang dipakai `HrisApiGuard`**. Kesamaan itu yang membuat dokumen tak
mungkin menjanjikan endpoint yang ternyata dijawab 403.

Kunci tidak pernah diletakkan di URL. Halaman meminta kunci, lalu mengambil
dokumen lewat header — sehingga tidak tercatat di riwayat peramban, log akses,
maupun `Referer`.

## Menambah endpoint

Tiga langkah, dan yang ketiga dipaksakan oleh uji:

1. Daftarkan di `N_HRIS_Api_Endpoint` + `N_HRIS_Api_Akses_Endpoint` lewat DML
2. Tulis entrinya di `resources/openapi/hcis-third-party.yaml`
3. Jalankan `RUN_API_DOCS_TEST=true php artisan test --filter ApiDocs`

Uji `setiap_endpoint_aktif_di_registry_terdokumentasi` menjadi **merah** bila ada
endpoint di registry yang belum ditulis di YAML, dan
`dokumen_tidak_menjanjikan_endpoint_di_luar_registry` merah bila sebaliknya.
Endpoint yang sengaja belum didokumentasikan harus disebut satu per satu di
larik `$dikecualikan` beserta alasannya — bukan dibiarkan lolos diam-diam.

**Kunci `paths` wajib memakai POLA rute**, lengkap dengan prefix `/api` dan
kurung kurawal (`.../tahun/{tahun}`), persis seperti nilai `Url_Route` di
registry. Salah satu huruf saja membuat endpoint hilang dari dokumen.

`servers` sengaja tidak ditulis di YAML — diisi saat penyajian dari host yang
sedang dibuka, supaya dokumen yang sama benar di dev, staging, dan production.

## Batas yang perlu diketahui

**`Api_Public` bukan rahasia.** Ia terkirim polos di setiap permintaan.
Menyaring dokumen dengannya mencegah satu konsumen melihat permukaan API
konsumen lain; ia **bukan** kendali kerahasiaan. Karena itu dokumen tidak boleh
memuat data sungguhan, nama orang, atau catatan internal.

**Tag ikut disaring.** Tag yang seluruh endpoint-nya tersaring akan dibuang;
tanpa itu Swagger UI tetap menampilkan judul dan keterangannya, sehingga satu
konsumen bisa membaca nama modul milik konsumen lain.

**Try it out butuh konteks aman.** Web Crypto hanya tersedia di HTTPS atau
`localhost`. Di luar itu halaman menolak dengan pesan yang jelas, bukan gagal
diam-diam. `Api_Secret` hanya hidup di memori halaman: tidak ke `localStorage`,
tidak pernah dikirim ke server, hilang saat tab ditutup.

**Aset Swagger UI di-commit** ke `public/vendor/swagger-ui/` (versi 5.17.14),
bukan diambil dari CDN dan bukan lewat Vite. CDN bisa diblokir atau berganti
versi diam-diam di Cloud Run, dan memasukkan Swagger UI ke graf Vite berisiko
menggemukkan chunk bersama. Memperbarui versinya berarti `npm i -D
swagger-ui-dist@<versi>` lalu menyalin ulang `swagger-ui-bundle.js` dan
`swagger-ui.css`.

**Nama view peka huruf besar-kecil.** `SYSTEM.Integration.api-docs` — Windows
tidak peduli, Cloud Run peduli.

## Yang belum ada

Uji validasi kontrak — memvalidasi respons nyata terhadap skema OpenAPI ini —
belum dibuat. Itu yang akan menangkap perubahan kontrak tak sengaja sebelum
sampai ke konsumen. Biayanya satu paket `require-dev`
(`league/openapi-psr7-validator`).
