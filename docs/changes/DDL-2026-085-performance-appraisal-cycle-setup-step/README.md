# DDL-2026-085 — Keadaan penyiapan siklus PA, per langkah

| | |
|---|---|
| Change ID | `DDL-2026-085-performance-appraisal-cycle-setup-step` |
| Jenis | DDL |
| Forward | `database/ddl/DDL-2026-085-performance-appraisal-cycle-setup-step/forward.sql` |
| Rollback | `.../drop.sql` |
| Bergantung pada | DDL-2026-066 (`N_PA_Cycle`) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Menyiapkan siklus sebenarnya empat pekerjaan: bobot pilar, paket soal, aturan penilai,
lalu siapa ikut. Sampai sekarang ketiga yang pertama tinggal di halaman **master global**
yang sama untuk semua siklus, dan yang keempat satu tombol tanpa persiapan.

Akibatnya admin tidak pernah bisa menjawab *"apa setelan siklus A, dan apa setelan siklus
B"* — tidak ada yang menyimpan jawabannya per siklus, dan tidak ada yang menyimpan sudah
sampai mana ia menyiapkannya.

## Keputusan bentuk

**Posisi disimpan, bukan disimpulkan.** `N_PA_Cycle.Langkah_Setup_Aktif` menyimpan posisi
admin sebagai fakta. Layar yang menebak langkah aktif dari kelengkapan isi akan melempar
admin kembali ke langkah kosong paling belakang setiap kali ia menyimpan — termasuk ketika
ia sengaja melewati satu langkah untuk kembali nanti.

Isi tabel langkah menjelaskan **apa** yang sudah dikerjakan. Kolom di siklus menjelaskan
**di mana** admin berada. Keduanya perlu, dan tidak saling menggantikan.

**`DRAFT` berdiri sendiri** dari `BELUM` dan `SELESAI`. Tanpa keadaan ketiga, pekerjaan
setengah jadi terpaksa dilaporkan sebagai salah satunya: yang pertama menghapus jejak
kerjanya, yang kedua berbohong kepada penjaga yang memeriksa kesiapan.

**`Kode_Langkah` tanpa CHECK constraint.** Daftar langkah masih akan bertambah, dan
menambah langkah lewat perubahan skema membuat setiap penambahan butuh DDL produksi —
biaya yang tidak sebanding untuk daftar yang dijaga di kode.

## Penanda BUKAN penjaga

`siapkan()` **sengaja tidak memeriksa** penanda ini. Penanda mencatat sejauh mana admin
berjalan di layar, bukan apakah datanya lengkap. Siklus yang disusun lewat jalur lain —
pemulihan, uji, penyalinan — punya setelan sah tanpa satu pun penanda, dan menolaknya
berarti menolak pekerjaan yang benar karena layarnya tidak dilewati.

Yang menjaga kebenaran tetap di tempatnya: bobot wajib 100 diperiksa saat disimpan, level
tanpa aturan mendaratkan pesertanya di `TERTUNDA`, dan `kunci()` menolak selama keranjang
itu belum bersih.

## Cacat yang tertangkap saat dibangun

Penanda langkah `PESERTA` semula membaca **selisih** yang dipulangkan `siapkan()`.
Dijalankan ulang atas siklus yang pesertanya sudah lengkap, ia memulangkan nol untuk
semuanya — dan penandanya menulis *"0 diikutkan"* pada siklus berisi 958 orang, lalu
menandainya `SELESAI`.

Sekarang dibaca dari `N_PA_Participant` langsung. Verifikasi pada siklus `CONTOHSAJA`:
**859 diikutkan, 99 dikecualikan, 0 tertunda** — cocok dengan 958 baris di tabel.

Nol peserta diikutkan kini **bukan** langkah selesai.

## Endpoint

| Alamat | Guna |
|---|---|
| `GET /cycles/{id}/setup` | keadaan seluruh langkah, posisi, penghalang |
| `POST /cycles/{id}/setup/pindah` | memindahkan posisi admin |
| `POST /cycles/{id}/setup/draft` | menandai satu langkah sebagai draft |
| `GET /cycles/{id}/aturan-penilai` | aturan penilai milik siklus |
| `POST /cycles/{id}/aturan-penilai/salin` | menyalin dari master |
| `PUT /cycles/{id}/aturan-penilai/{levelId}` | menyunting rincian satu level |

## Rollback

`drop.sql` membuang tabel dan kolomnya. **Setelan siklusnya sendiri tidak ikut hilang** —
bobot pilar, paket soal, dan aturan penilai tersimpan di tabel snapshot masing-masing. Yang
hilang hanya jejak proses penyiapannya; siklus yang sedang setengah disiapkan akan tampak
seperti belum pernah disentuh.
