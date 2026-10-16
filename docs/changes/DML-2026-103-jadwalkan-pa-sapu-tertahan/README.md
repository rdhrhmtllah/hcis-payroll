# DML-2026-103 — Jadwalkan `pa:sapu-tertahan`

| | |
|---|---|
| Change ID | `DML-2026-103-jadwalkan-pa-sapu-tertahan` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-103-jadwalkan-pa-sapu-tertahan/data-change.sql` |
| Bergantung pada | tidak ada |
| Perubahan kode penyerta | tidak ada — perintahnya sudah lama ada, hanya belum pernah dijadwalkan |
| Status | `TRACKER_PENDING` — dev **DONE**, production `PENDING` |

## Yang diselesaikan

`pa:sapu-tertahan` sudah ada dan sudah bekerja, tetapi tidak pernah masuk
`N_HRIS_Scheduler_Configs`. Akibatnya ia hanya berjalan bila seseorang menekan tombol
**Hitung Ulang yang Tertahan** di layar admin — dan tidak ada apa pun yang memberi tahu
admin bahwa ada yang perlu ditekan.

Yang disapunya: hasil PA yang **TERTAHAN** karena KPI pesertanya belum disetujui saat
penilaian 360-nya selesai. Urutan itu memang sering terbalik di lapangan — penilaian
selesai lebih dulu, persetujuan KPI menyusul beberapa hari kemudian. Tanpa penyapu
berkala, hasil peserta itu menggantung sampai ada yang kebetulan menekan tombolnya, dan
yang paling mungkin menyadarinya adalah **pesertanya sendiri**, saat bertanya kenapa
nilainya belum keluar.

## Kenapa jam kerja, bukan dini hari

```
20 7-19 * * *     tiap jam pada menit ke-20, pukul 07.00–19.00 WIB
```

Hampir semua penyapu lain di tabel ini berjalan tengah malam, dan itu benar untuk
pekerjaan yang sumber datanya juga berubah tengah malam. Yang ini berbeda: pemicunya
adalah **persetujuan KPI**, dan persetujuan hanya terjadi saat orang bekerja.
Menjalankannya pukul 02.00 berarti setiap KPI yang disetujui pukul 09.00 menunggu tujuh
belas jam untuk perhitungan yang memakan hitungan detik.

Menit ke-20 dipilih supaya tidak berimpit dengan `hris:api-nonce-prune` (menit 15) dan
`hris:absen-maintenance` (menit 0/15/30/45).

## Bebannya

Kecil, dan pada hari biasa nol: hanya siklus berstatus `OPEN`/`CLOSED` yang dilihat, dan
hanya peserta bertanda `TERTAHAN`. `--batas=50` membatasi peserta per siklus sekali
jalan, jadi lonjakan pun terpotong menjadi beberapa putaran alih-alih satu proses
panjang. Peserta yang sebabnya belum hilang akan tertahan lagi — tanpa efek samping
selain nomor versi yang naik. Yang sudah **TERBIT** tidak tersentuh sama sekali.

Scheduler membungkus setiap baris tabel ini dengan `withoutOverlapping(15)` dan
`onOneServer()` di `app/Console/Kernel.php`, jadi jadwal per jam tidak bisa menumpuk.

## Verifikasi

Bukan hanya "barisnya masuk" — baris di tabel tidak berguna kalau `Kernel` tidak pernah
merangkainya. Yang diperiksa: `Schedule::events()` benar-benar memuat perintahnya.

```
id=25  pa:sapu-tertahan   cron=20 7-19 * * *   tz=Asia/Jakarta   aktif=Y
perintah PA yang dirangkai scheduler:
   … pa:ingatkan-penilai  @  45 1 * * *
   … pa:sapu-tertahan     @  20 7-19 * * *
```

Idempotensinya dibuktikan dengan menjalankan script dua kali: tetap satu baris.

## Cara membatalkan

```sql
UPDATE N_HRIS_Scheduler_Configs
SET Flag_Aktif = 'T'
WHERE Command = 'pa:sapu-tertahan';
```

Menonaktifkan, bukan menghapus: barisnya menyimpan cron yang sudah disepakati, dan
menghapusnya membuang keputusan itu bersama datanya.
