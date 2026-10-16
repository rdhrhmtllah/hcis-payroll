# DDL-2026-095 — Anti-replay registry LMS disetarakan dengan HRIS

## Ringkasan

HCIS punya **dua** registry API pihak ketiga yang memakai pola gerbang yang sama
(HMAC-SHA512 + nonce anti-replay + whitelist endpoint), tetapi **jaminan
databasenya tidak setara**. Perubahan ini menutup selisihnya.

## Apa yang ditemukan

Dibaca dari katalog `Web_HRIS` (development) pada 10 September 2026:

| Tabel | Indeks yang ada |
|---|---|
| `N_HRIS_Api_Nonce` | PK, **`UQ_N_HRIS_Api_Nonce (Nonce)` UNIK**, `IX_..._Expired (Expired_At)`, `IX_..._Aplikasi (Id_Api_Aplikasi, Expired_At)` |
| `N_LMS_Api_Nonces` | PK saja — **tidak ada satu pun indeks pada `Nonce`** |

Yang membuat ini penting: justru `N_LMS_Api_Nonces` yang dilalui lalu lintas
nyata. Seluruh callback HCLEARN masuk lewat `LmsApiGuard`.

### Tiga akibatnya

1. **Anti-replay-nya bisa dibalap.** Gerbang memeriksa "nonce ini sudah pernah
   dipakai?" dengan `SELECT`, lalu menyimpannya dengan `INSERT` **terpisah**.
   Tanpa UNIQUE, dua permintaan bernonce sama yang datang bersamaan sama-sama
   melihat "belum ada" lalu sama-sama tersimpan — keduanya diproses. Di
   `N_HRIS_Api_Nonce` hal itu mustahil karena database menolak INSERT kedua.

2. **Pemeriksaannya memindai seluruh tabel**, dan makin lambat karena tabel ini
   hanya bertambah di antara dua kali prune.

3. **Pembersihnya juga memindai seluruh tabel.** `hris:api-nonce-prune` berjalan
   tiap jam (`15 * * * *`, aktif di `N_HRIS_Scheduler_Configs`) dan menghapus
   `WHERE Expired_At < now`; tanpa indeks pada `Expired_At` ia harus membaca
   semuanya.

Sekaligus ditemukan: `N_LMS_Api_Logs` tidak punya CHECK pada `Signature_Valid`,
padahal `N_HRIS_Api_Log` punya `CK_N_HRIS_Api_Log_Sig`. Untuk kolom yang dipakai
sebagai **bukti keabsahan**, nilai salah ketik tersimpan diam-diam di satu
registry dan ditolak di registry lain.

## Yang dibuat

| Objek | Kegunaan |
|---|---|
| `UQ_N_LMS_Api_Nonces_Nonce` | Anti-replay dijamin database, bukan hanya oleh urutan dua pernyataan |
| `IX_N_LMS_Api_Nonces_Expired` | `hris:api-nonce-prune` berhenti memindai seluruh tabel |
| `CK_N_LMS_Api_Logs_Sig` | `Signature_Valid` terkunci ke `Y` / `T` / `NULL`, setara kembarannya |

## Sifat skrip

- **Idempoten**; aman dijalankan berulang. Dibuktikan: dijalankan dua kali ke
  development, 6 batch, 0 gagal, keduanya.
- **Tidak ada DROP, tidak ada DELETE, tidak ada perubahan data.**
- UNIQUE hanya dibuat bila data memang sudah memenuhi syarat (tidak ada `Nonce`
  kembar dan tidak ada `Nonce` NULL). Bila belum, skrip **tidak gagal**: ia
  membuat indeks biasa supaya manfaat kecepatan tetap didapat, lalu mencetak
  jumlah pelanggarnya. Bersihkan dengan `php artisan hris:api-nonce-prune`, lalu
  jalankan ulang skrip ini untuk menaikkannya jadi UNIQUE.
- CHECK sengaja **tidak** memakai `WITH NOCHECK`. Baris lama yang melanggar
  berarti ada penulis yang menyimpan nilai di luar kontrak, dan itu harus
  ditemukan lebih dulu — bukan disembunyikan di belakang constraint.

## Perubahan kode yang menyertainya

Indeks UNIQUE hanya berguna kalau pelanggarannya diterjemahkan menjadi jawaban
yang benar. `LmsApiGuard` dan `HrisApiGuard` kini menerjemahkan galat SQL Server
**2601/2627** (pelanggaran indeks unik) menjadi `401 Duplicate request` — jawaban
yang sama persis dengan pemeriksaan nonce di langkah sebelumnya.

Sebelum ini, `QueryException` lolos ke `catch (\Throwable)` di `handle()` dan
keluar sebagai `500 Security System Failure`. Pihak seberang akan menyimpulkan
HCIS rusak lalu mencoba lagi, padahal itu penolakan anti-replay yang wajar.

Kode galat dicocokkan lewat `errorInfo[1]`, **bukan** lewat teks pesannya: teks
itu berubah mengikuti bahasa dan versi server.

## Verifikasi

Dua blok `SELECT` di akhir `forward.sql` menampilkan indeks `N_LMS_Api_Nonces`
dan seluruh CHECK `N_LMS_Api_Logs`. Hasil di development setelah dijalankan:

```
PK__N_LMS_Ap__D8301A036C6987AE   unik=1  (Id_Api_Nonces)
IX_N_LMS_Api_Nonces_Expired      unik=0  (Expired_At)
UQ_N_LMS_Api_Nonces_Nonce        unik=1  (Nonce)

CK_N_LMS_Api_Logs_Modul          ([Modul] IS NULL OR ([Modul]='SOP' OR [Modul]='LMS'))
CK_N_LMS_Api_Logs_Sumber_Error   ([Sumber_Error] IS NULL OR ([Sumber_Error]='APLIKASI' OR [Sumber_Error]='GERBANG'))
CK_N_LMS_Api_Logs_Sig            ([Signature_Valid] IS NULL OR ([Signature_Valid]='T' OR [Signature_Valid]='Y'))
```

Penjaga regresi: `tests/Feature/SYSTEM/Integration/GerbangApiNonceKembarTest.php`
menyimpan nonce sungguhan dua kali ke tabel nyata dan menuntut jawaban kedua
berupa 401. Bila indeks UNIQUE-nya belum ada di lingkungan itu, uji ditandai
**skip dengan alasan eksplisit** — bukan diam-diam hijau karena tak ada yang
menolak.

## Pekerjaan user

1. Tinjau `forward.sql` dan hasil development.
2. Rekam checksum di SQL Tracker:
   `63bac89b22df008ebce3b3619ee101868426a2eedc0d02379403fd9a91a4806f`
3. Jalankan ke production setelah approval. Bila keluarannya memuat
   `PERINGATAN: UNIQUE dilewati`, jalankan `php artisan hris:api-nonce-prune`
   lalu jalankan ulang `forward.sql`.
4. Verifikasi lewat dua blok SELECT di akhir skrip, lalu naikkan status mengikuti
   alur wajib sampai `COMPLETED`.

## Urutan terhadap kode

Tidak mengikat. Kode yang baru berjalan benar dengan maupun tanpa indeks:
tanpa UNIQUE, cabang penerjemah 2601/2627 sekadar tidak pernah tersentuh.
Menjalankan DDL lebih dulu tetap lebih baik supaya jaminannya langsung berlaku.
