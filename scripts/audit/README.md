# Skrip audit performa

Dipakai bersama `docs/audit/audit-performa-modul.md`. Semuanya **read-only** —
tak satu pun menulis ke basis data.

Jalankan dari akar proyek:

```bash
python scripts/audit/peta-modul.py       # inventaris fitur + cakupan rute
python scripts/audit/sisir-statis.py     # pola berbahaya per modul
python scripts/audit/rute-saja.py        # rekaman, HANYA baris rute HTTP
python scripts/audit/telan-validasi.py   # validasi yang tertelan catch generik
python scripts/audit/peta-lock.py app      # peta lockForUpdate (daftar pendek, banyak positif palsu)
python scripts/audit/verifikasi-lock.py app  # saring dgn BATAS METODE — jauh lebih sedikit hantu

# daftar rute satu fitur, dipisah GET vs yang mengubah data
php artisan route:list --json | python scripts/audit/rute-fitur.py ESS Leave

# menyapu rute GET satu fitur lewat kernel, sebagai pengguna login
php scripts/audit/sapu-rute.php ESS Leave 4
```

## Peringatan yang harus dibaca lebih dulu

- **`sisir-statis.py` membuang komentar lebih dulu, dan itu bukan hiasan.**
  Versi pertama skrip ini membaca komentar sebagai kode: 8 dari 8 temuan
  non-sargable di `ShiftApprovalController` ternyata blok yang sudah
  dimatikan, dan 5 dari 5 "jaringan dalam transaksi" ternyata panggilan
  sesudah `commit`. Jangan pernah kembali ke pencocokan mentah.

- **`rute-saja.py` sengaja MEMBUANG baris `uji:`.** Rekaman dari suite
  mengatribusi kueri ke nama uji; uji yang melooping 30 karyawan tampak
  seperti N+1 80× padahal bukan cacat produk. Untuk mencari cacat produk,
  hanya baris ber-rute HTTP yang sah.

- **`sapu-rute.php` HANYA menyentuh GET, dan itu pagar, bukan kemalasan.**
  Basis data dev dipakai bersama; menyapu POST/PUT/DELETE di sana berarti
  mengubah data orang yang sedang bekerja. Rute berparameter dilewati dan
  dilaporkan apa adanya sebagai "belum tersentuh" — lubang yang diakui lebih
  baik daripada cakupan yang dilebih-lebihkan.

- **`telan-validasi.py` sudah menghasilkan nol, dan penjaganya ada di uji.**
  Aturannya kini dijaga permanen oleh
  `tests/Feature/SYSTEM/DisiplinValidasiTertelanTest.php`. Skrip ini tetap
  berguna untuk menyisir cepat saat menulis kode, tetapi yang mengikat adalah
  ujinya.

- **Menyapu rute berurutan mengukur URUTAN CACHE, bukan biaya rutenya.**
  Cache di sini berbasis berkas dan bertahan lintas permintaan, jadi rute yang
  kebetulan disapu duluan membayar pengisian cache untuk semua yang
  mengikutinya. Satu rute notifikasi terbaca 116 kueri karena ini; sesungguhnya
  7. Panaskan tiap rute dulu, ukur pada jalan kedua.

- **`DB::listen` MENUMPUK.** Mendaftarkannya di dalam perulangan rute tanpa
  melepasnya membuat rute ke-50 punya 50 pendengar, dan setiap kueri tercatat
  50 kali. Angkanya tetap terlihat masuk akal — hampir semua rute "mengulang
  ~50×" — dan itulah yang membuatnya berbahaya. Daftarkan sekali di luar
  perulangan, dan beri perkakasnya penjaga: satu kueri yang pasti jalan sekali
  harus tercatat sekali.

- **Angka `dm_exec_query_stats` MENCAMPUR uji dengan produk.** DMV tidak punya
  atribusi rute sama sekali. Temuan #1 audit ini — yang berhari-hari tercatat
  sebagai temuan produk terbesar — ternyata uji parity-nya sendiri. Sebelum
  menyebutnya temuan produk, buktikan ada kode yang menjalankannya:
  `grep -rn "<objek>" app/` **dan** `sys.sql_expression_dependencies`.

- **`peta-lock.py` menghasilkan DAFTAR PENDEK, bukan temuan.** `->exists()` juga
  metode Builder/Collection, jadi sebagian besar baris ber-"exists" bukan
  jaringan; kolom "jarak" dihitung sampai `DB::commit` berikutnya di berkas yang
  belum tentu sepasang; dan panggilan jaringan yang bersarang dua lapis TIDAK
  terlihat sama sekali. Temuan #18 hanya bisa dipastikan dengan membaca rantai
  panggilannya.

- **Untuk "jaringan di dalam transaksi", pemindai statis adalah ALAT YANG SALAH.**
  Dibuktikan: dari 70 titik kunci, `peta-lock.py` melaporkan 38 kandidat;
  `verifikasi-lock.py` (dengan batas metode) menyisakan 3; ketiganya positif
  palsu. Sementara satu-satunya cacat nyata — temuan #18 — **tak muncul di
  keduanya**, karena panggilannya bersarang dua lapis. Yang bekerja adalah
  penjaga RUNTIME: `PeringatanJaringanDalamTransaksi`, dipasang di
  `CloudTaskService::dispatch()`.

- **Angka dari skrip ini adalah PETUNJUK, bukan bukti.** Bukti hanya datang
  dari logical reads dan CPU — waktu dinding di dev didominasi latensi
  (`SELECT 1` = 126–142 ms), dan tabel dev sering terlalu kecil untuk
  memperlihatkan bedanya sama sekali.
