# DML-2026-099 — Dua jadwal aktif yang perintahnya tidak ada di mana pun

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.

## Temuan

Jadwal HCIS hidup di `N_HRIS_Scheduler_Configs`, dan `Console\Kernel` mendaftarkannya apa adanya:

```php
$schedule->command($config->Command)->cron($config->Cron_Expression)
```

Laravel **tidak** memeriksa keberadaan perintahnya saat mendaftar. Kegagalannya baru muncul ketika cron menyala, sebagai `Command ... is not defined` yang mendarat di `storage/logs/scheduler.log` — dan tidak ke mana-mana lagi. Sebuah baris bisa berdiri berbulan-bulan dengan `Flag_Aktif = 'Y'`, terbaca oleh siapa pun yang membuka tabel itu sebagai *"pekerjaan ini berjalan tiap malam"*, padahal tidak pernah berjalan.

Dari **20 entri aktif** pada `Web_HRIS` (2026-09-08), tiga menunjuk perintah yang tidak ada. Dua di antaranya tidak ada di branch **mana pun**:

| entri | cron | dibuat | keadaan |
|---|---|---|---|
| `sop:recert-scan` | `15 1 * * *` | 2026-07-24 | perintahnya **dihapus** commit `179cf07a` bersama pencabutan masa berlaku dokumen SOP |
| `talent-pool:kedaluwarsa` | `0 1 * * *` | 2026-07-28 | **tidak pernah ada** di riwayat `app/Console` pada branch mana pun |

`sop:recert-scan` yang paling menyesatkan: tabel penjadwal menyatakan resertifikasi SOP berjalan tiap malam 01:15, sementara mesinnya sudah dicabut.

## Yang sengaja TIDAK disentuh

| entri | alasan |
|---|---|
| `hcis:kalender` | baru **tiga hari** (2026-09-05), keterangannya menyebut sinkron kalender operasional HO. Ini tampak **mendahului** kodenya, bukan tertinggal darinya. Menonaktifkannya berarti mematikan sesuatu yang sebentar lagi hidup — yang benar adalah menanyakannya. |
| `pa:ingatkan-penilai`, `pa:sapu-tertahan` | keduanya **ada** di `feat/performance-apprisal`. Wajar tidak ditemukan dari checkout `master`; keduanya hidup begitu PA rilis. |

## Satu hal yang sudah diperiksa dan ternyata aman

Seluruh perulangan pendaftaran di `Console\Kernel` dibungkus **satu** `try/catch`. Kalau pendaftaran sebuah entri melempar, seluruh jadwal **sesudahnya** ikut hilang diam-diam — itu akan jauh lebih gawat daripada temuan di atas.

Sudah diuji: `php artisan schedule:list` menampilkan **kedua puluh** entri, jadi nama yang tidak dikenal **tidak** melempar saat mendaftar. Kegagalannya terisolasi pada entri itu sendiri.

## Kenapa dinonaktifkan, bukan dihapus

`Flag_Aktif = 'T'` menghentikan pendaftarannya tanpa membuang catatannya. Cron dan keterangannya tetap terbaca, dan bila ternyata perintahnya memang akan ditulis, menghidupkannya kembali cukup satu `UPDATE`. Menghapus baris membuang informasi yang tidak bisa dikembalikan.

## Alat pemeriksanya

Perubahan ini datang bersama perintah **hanya-baca**:

```bash
php artisan hris:penjadwal-periksa --abaikan=pa:
```

Ia melaporkan entri aktif yang menunjuk perintah tidak ada, memaklumi prefix fitur yang ditahan, dan **tidak menulis apa pun** — menonaktifkan entri tetap keputusan manusia lewat perubahan DML seperti ini.

## Yang harus dilakukan di production lebih dulu

Isi tabel ini berbeda antar lingkungan: entri yang yatim di pengembangan bisa sah di produksi, dan sebaliknya. Jalankan pemeriksanya **di sana** dulu, lalu terapkan skrip ini hanya untuk entri yang di production pun dilaporkan yatim. BAGIAN 4 `data-change.sql` memuat langkahnya.

## Risiko

Rendah dan dapat dibatalkan. Tidak ada `DELETE`, tidak ada perubahan skema; yang berubah hanya kolom `Flag_Aktif` pada dua baris, dan hanya bila nilainya masih `'Y'` — sehingga menjalankannya dua kali tidak menyentuh apa pun.

Yang hilang bila keputusan ini keliru: dua pekerjaan terjadwal berhenti didaftarkan — padahal keduanya memang **sudah** tidak pernah berjalan, karena perintahnya tidak ada.
