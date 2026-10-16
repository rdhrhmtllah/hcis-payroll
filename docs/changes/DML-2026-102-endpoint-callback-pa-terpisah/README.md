# DML-2026-102 — Callback PA memakai alamat sendiri

| | |
|---|---|
| Change ID | `DML-2026-102-endpoint-callback-pa-terpisah` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-102-endpoint-callback-pa-terpisah/data-change.sql` |
| Bergantung pada | tidak ada |
| Perubahan kode penyerta | `PaApiGuard`, `PaThirdPartyCallbackController`, `routes/KPI/PerformanceAppraisal/Integration/PaThirdPartyApi.php`; pencabutan cabang PA dari `LmsApiGuard` dan `LmsThirdPartyCallbackController`; `resources/openapi/hcis-third-party.yaml` |
| Revisi | `r2` — registri kedua ikut diisi |
| Status | `TRACKER_PENDING` — dev **DONE**, production `PENDING` |

## Yang diselesaikan

Hasil penilaian 360 dari HC Learn kini masuk lewat alamatnya sendiri:

```
POST /api/v1/pa/third-party/penilaian/complete
```

Sebelumnya ia menumpang `/api/v1/lms/third-party/exam/complete` bersama Training dan SOP
Kuis. Karena satu alamat melayani tiga modul, asal sebuah kiriman tidak bisa dibaca dari
alamatnya, dan `LmsApiGuard` harus **menebaknya dari token**: tiga kueri ke tiga tabel
integrasi pada setiap callback, ditambah satu cabang `CALLBACK_TOKEN_AMBIGU` untuk token
yang cocok di lebih dari satu modul — dimenangkan LMS "demi kompatibilitas". Cabang itu ada
persis karena penulisnya tahu tebakan ini bisa salah.

Di atasnya masih ada satu lagi: penyaring bersama menuntut `Score`, dan penilaian 360 tidak
pernah punya satu angka akhir. Cabang PA sampai harus diperiksa **sebelum** validasi, kalau
tidak kiriman yang sah ditolak dengan pesan tentang field yang memang tak pernah diminta
darinya. Bentuk seperti itu bekerja, tetapi ia menaruh aturan satu modul di dalam alur modul
lain: siapa pun yang kelak merapikan validasi LMS akan mematahkan PA tanpa pernah membuka
satu berkas PA pun.

Alamat sendiri menghapus seluruh tebakan itu.

## DUA registri, bukan satu

HCIS punya dua daftar endpoint pihak ketiga yang hidup berdampingan, dan **keduanya**
harus diisi:

| Registri | Dibaca oleh | Akibat bila kosong |
|---|---|---|
| `N_LMS_Endpoint_Api` + `N_LMS_Akses_Endpoint` | `LmsApiGuard`, `PaApiGuard` | kiriman dijawab **403** walau tanda tangannya sah |
| `N_HRIS_Api_Endpoint` + `N_HRIS_Api_Akses_Endpoint` | `OpenApiRegistryFilter` | endpoint tak pernah muncul di spesifikasi yang dibaca HC Learn |

Revisi pertama hanya mengisi yang pertama. Yang menangkapnya adalah penjaga
`ApiDocsTest` — *"dokumen tidak menjanjikan endpoint di luar registry"* — karena YAML
sudah menjanjikan sesuatu yang registry-nya tidak kenal. Nama aplikasinya pun berbeda
di kedua tabel: `HUMAN CAPITAL LEARNING` di `N_LMS_`, `HC Learn` di `N_HRIS_`. Keduanya
**dicari lewat nama**, tidak ditulis sebagai angka — `Id_Api_Aplikasi` bisa berbeda
antara dev dan production, dan angka yang salah memberi akses kepada aplikasi yang
keliru tanpa satu pun yang gagal.

## `Id_Grup_Fitur` diisi KPI, bukan 0

`N_HRIS_Api_Endpoint.Id_Grup_Fitur` wajib dan tanpa nilai bawaan; percobaan kedua gagal
karenanya dan ter-rollback utuh berkat `XACT_ABORT ON` — tidak ada yang setengah jadi.

Diisi **Key Performance Indicator**, karena Performance Appraisal memang submodul KPI.
Tetangganya (callback SOP) memakai `0`, yang tidak menunjuk grup mana pun di
`N_HRIS_Master_Grup_Fitur` — penampung, bukan keputusan. Kolomnya deskriptif belaka:
tidak ada foreign key, dan tidak satu pun kode membacanya (penyaringan dokumen berjalan
lewat tabel akses, bukan lewat grup). Karena itu memilih yang benar di sini tidak
berisiko, sedangkan meninggalkan `0` hanya menambah satu baris lagi yang harus ditebak
artinya oleh orang berikutnya.

## Kenapa perlu baris database sama sekali

`pa.api.guard` memeriksa daftar putih endpoint yang **sama** dengan gerbang LMS. Tanpa dua
baris dari script ini, seluruh kiriman PA dijawab:

```
403 Endpoint not authorized for this application
```

Tanda tangannya sah, aplikasinya dikenal — hanya alamatnya yang belum terdaftar. Gejala itu
mudah disalahartikan sebagai masalah kredensial di sisi vendor, jadi jalankan script ini
**sebelum** meminta HC Learn mengarahkan callback-nya ke sini.

## Kenapa menulis ke tabel ber-prefix `N_LMS_`

Ini pengecualian sadar terhadap aturan "perubahan PA hanya menyentuh `N_PA_*`", dan
alasannya bukan kemudahan.

`N_LMS_Endpoint_Api` dan `N_LMS_Akses_Endpoint` bukan data bisnis LMS melainkan **registri
gerbang API** — daftar alamat yang boleh dipanggil aplikasi luar. Nonce anti-replay punya
alasan yang lebih keras lagi: bila PA memakai tabel nonce sendiri, permintaan bertanda
tangan sah yang sudah dipakai di gerbang LMS masih bisa **diputar ulang** ke gerbang PA, dan
sebaliknya. Dua ruang nonce membuat perlindungan replay lebih **lemah** daripada satu.

Kredensial aplikasinya juga satu (`HUMAN CAPITAL LEARNING`). Memaksa HC Learn memegang kunci
kedua hanya karena alamatnya bertambah satu adalah beban tanpa hasil.

Tidak ada baris LMS atau SOP yang diubah, dinonaktifkan, atau dihapus — hanya dua baris
ditambahkan.

## Yang tidak disentuh

- `/api/v1/lms/third-party/exam/complete` tetap hidup dan tetap melayani Training dan SOP.
- Tidak ada tabel `N_PA_*` yang berubah; ini murni pendaftaran alamat.
- Kredensial, kunci, dan mekanisme tanda tangan tidak berubah sedikit pun.

## Urutan pemakaian

1. ~~Jalankan script ini di development.~~ **Sudah dijalankan**, dan idempotensinya
   dibuktikan dengan menjalankannya dua kali: tetap satu baris di masing-masing registri.
2. Verifikasi dengan dua kueri di bagian akhir script — masing-masing harus tepat satu
   baris, kedua `Flag_Aktif` bernilai `Y`.
3. Minta HC Learn mengarahkan callback hasil PA ke alamat baru. Selama belum diarahkan,
   token PA yang dikirim ke alamat lama akan dijawab **404** — dan itu memang yang benar:
   alamat itu bukan miliknya lagi.
4. Catat ke SQL Tracker, lalu jalankan ke production.

## Cara membatalkan

Nonaktifkan, jangan hapus — baris akses yang dihapus tidak meninggalkan jejak bahwa ia
pernah ada:

```sql
UPDATE ae SET Flag_Aktif = 'T'
FROM N_LMS_Akses_Endpoint ae
JOIN N_LMS_Endpoint_Api ep ON ep.Id_Endpoint_Api = ae.Id_Endpoint_Api
WHERE ep.Url_Route = '/api/v1/pa/third-party/penilaian/complete';
```
