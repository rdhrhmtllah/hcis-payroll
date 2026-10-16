# DDL-2026-067 — Nyalakan READ_COMMITTED_SNAPSHOT pada `Web_HRIS`

**Status: REVIEW_REQUIRED.** Belum dijalankan di mana pun. Berkas ini dibuat oleh
audit performa; keputusan menjalankannya milik developer.

---

## Kenapa

Selama **507 jam** (server hidup sejak 18 Agu 2026), `sys.dm_os_wait_stats`
mencatat penantian kunci berikut di server ini:

| jenis kunci | total tunggu | jumlah | rata-rata |
|---|---:|---:|---:|
| **`LCK_M_S`** — kunci PEMBACA | **12.785 dtk** | **8.194** | **1.560 ms** |
| `LCK_M_U` | 1.000 dtk | 2.247 | 445 ms |
| `LCK_M_RS_U` | 595 dtk | 142 | 4.191 ms |
| `LCK_M_X` — kunci PENULIS | 428 dtk | 968 | 442 ms |

Yang menentukan bukan angka totalnya, melainkan **perbandingannya**: pembaca
menunggu **30 kali lebih lama** daripada penulis. Di bawah `READ COMMITTED`
tanpa snapshot, `SELECT` harus mengambil kunci S dan karena itu **antre di
belakang setiap `UPDATE` yang belum commit**. Delapan ribu kali, rata-rata 1,56
detik.

Sebaran per tabel (`dm_db_index_operational_stats`, jendela yang sama):

| tabel | total tunggu baris | jumlah | rata-rata |
|---|---:|---:|---:|
| `N_PA_Cycle` | 2.160 dtk | 177 | 12.207 ms |
| `N_SOP_Dokumen` | 1.774 dtk | 607 | 2.922 ms |
| `KPI_Pengajuan` | 623 dtk | 325 | 1.917 ms |
| `N_HRIS_Notification` | 469 dtk | 489 | 958 ms |
| `cache` | 453 dtk | 19 | 23.844 ms |

### Kenapa ini BUKAN diperbaiki per-endpoint

Audit ini sudah mencoba jalan itu dan sampai ke batasnya. Endpoint yang lambat
diperiksa satu per satu, dan yang ditemukan justru sebaliknya:

- `/homepage` sudah ramping — **4 kueri**; badge pending approval 173 ms p50.
- `/api/v1/sop/progress/halaman` sudah ramping — **3 kueri**.
- Endpoint teringan berjalan **0–2 ms**, jadi tidak ada ongkos tetap per
  permintaan yang bisa disalahkan.
- Empat tuduhan terhadap kode tertentu **dicabut** setelah diperiksa: unggah GCS
  di dalam transaksi (ternyata pemanggilnya di luar transaksi), JOIN snapshot
  menggantikan TVF lembur (cakupan snapshot 0,8%), koneksi mahal (2 ms), dan
  DNS macet (p50 2,3 ms, nol gagal).

Artinya kelambatan yang tersisa **bukan milik satu kode**. Ia milik sifat
isolasi basis datanya, dan hanya bisa dibereskan di tempat itu.

---

## Prasyarat yang tidak boleh dilewati

RCSI menyimpan versi baris lama di **tempdb**. Keadaan tempdb sekarang:

```
tempdev   ROWS   264 MB   tumbuh 64 MB
templog   LOG      8 MB   tumbuh 64 MB
server    2 inti, 10.236 MB RAM
```

**Satu berkas data.** Panduan baku SQL Server adalah jumlah berkas data tempdb
sebanyak jumlah inti (maksimum 8) untuk meredakan rebutan halaman alokasi
PFS/GAM/SGAM — dan server ini sudah mencatat **PAGELATCH_EX 870.102 kali** pada
audit sebelumnya.

Menyalakan RCSI tanpa membenahi tempdb **memindahkan** tekanan dari kunci ke
alokasi tempdb, bukan menghapusnya. Karena itu:

1. Tambah berkas data tempdb menjadi **2** (sejumlah inti), ukuran awal sama,
   pertumbuhan tetap dalam MB (bukan persen).
2. Setel `max server memory` — sekarang **belum disetel**, artinya SQL Server
   boleh mengambil seluruh 10 GB dan membuat OS kelaparan.
3. Baru setelah itu jalankan `forward.sql`.

Butir 1 dan 2 adalah setelan instance, **bukan** bagian dari berkas DDL ini, dan
tidak bisa dijalankan agent.

---

## Yang akan terjadi saat dijalankan

`ALTER DATABASE ... SET READ_COMMITTED_SNAPSHOT ON` menuntut **akses eksklusif**
ke basis data. `forward.sql` memakai `WITH ROLLBACK IMMEDIATE`, yang berarti:

- setiap transaksi yang sedang berjalan **di-rollback**;
- setiap koneksi **diputus**, termasuk koneksi aplikasi tetangga di host yang sama.

Jalankan hanya pada jendela pemeliharaan yang diumumkan. Bila lebih memilih
gagal-cepat daripada memutus orang, ganti klausanya menjadi `WITH NO_WAIT` —
pernyataan akan gagal bila masih ada koneksi lain, dan tidak ada yang terputus.

---

## Risiko

| risiko | kenyataannya |
|---|---|
| tempdb tumbuh | Nyata. Version store hidup di sana. Pantau `version_store_reserved_page_count`; yang naik tanpa pernah turun menandakan transaksi dibiarkan terbuka lama. |
| galat 3960 (update conflict) | **Tidak berlaku di sini.** Galat itu milik `SNAPSHOT` isolation, bukan RCSI. RCSI tidak mengubah perilaku penulis dan tidak menambah konflik pembaruan. |
| pembaca melihat data "lama" | Sesuai desain, dan justru lebih benar: pembaca melihat potret data **saat kueri mulai**, bukan campuran baris sebelum-dan-sesudah. Ia tidak lagi memblokir dan tidak lagi diblokir. |
| kode bergantung pada pemblokiran | Perlu diperiksa. Pola `SELECT` yang dipakai sebagai kunci antar-proses (menunggu penulis selesai) akan berhenti menunggu. Audit tidak menemukan pola ini, tetapi tidak menyapu 472 rute tulis. |
| aplikasi tetangga di host sama | Setelan ini **per basis data**, jadi `Web_HRIS` saja. Tetapi `ROLLBACK IMMEDIATE` memutus koneksi ke basis data ini, dan tempdb **dipakai bersama** seluruh instance. |

---

## Cara membuktikan berhasil

Ambil garis dasar **sebelum** menjalankan, lalu bandingkan setelah beban normal
sehari. Angka wait bersifat kumulatif sejak server hidup, jadi selisihnya yang
berarti — bukan nilai mutlaknya.

```sql
-- Garis dasar & pembanding
SELECT wait_type, waiting_tasks_count, wait_time_ms
  FROM sys.dm_os_wait_stats
 WHERE wait_type IN ('LCK_M_S','LCK_M_U','LCK_M_X','LCK_M_RS_U');

-- Kesehatan tempdb sesudahnya (jalankan berkala, bukan sekali)
SELECT SUM(version_store_reserved_page_count) * 8 / 1024.0 AS version_store_mb,
       SUM(unallocated_extent_page_count)     * 8 / 1024.0 AS bebas_mb
  FROM tempdb.sys.dm_db_file_space_usage;
```

Berhasil bila **`LCK_M_S` berhenti bertambah** sementara version store naik lalu
**turun kembali** mengikuti siklus transaksi.

Gagal bila version store naik terus tanpa turun. Itu bukan salah RCSI melainkan
transaksi yang dibiarkan terbuka — dan `drop.sql` mengembalikan keadaan.

---

## Cakupan

- **Ini bukan** perbaikan untuk `N_PA_Cycle`, penunggu terbesar (2.160 dtk).
  Fitur Performance Appraisal **tidak ada di master** — nol rujukan
  `N_PA_Cycle` di seluruh `app/`. Ia hidup di cabang `feat/performance-apprisal`
  pada checkout terpisah, jadi kodenya tak bisa diaudit dari master. RCSI akan
  meringankan pembacanya, tetapi sebab transaksi 12 detiknya tetap belum diperiksa.
- **Ini bukan** pengganti perbaikan per-endpoint. Dua perbaikan yang terukur
  sudah masuk terpisah: PR #341 (`cuti/store`) dan PR #342 (Activity Center,
  7 kueri → 4).

## Checklist

- [x] DDL dibuat
- [x] DROP script dibuat sebagai dokumentasi manual
- [ ] Prasyarat tempdb & `max server memory` dibereskan
- [ ] Review user selesai
- [ ] Garis dasar wait stats diambil
- [ ] DDL dijalankan di development
- [ ] Development diverifikasi (LCK_M_S berhenti bertambah, version store sehat)
- [ ] DDL yang sama dicatat ke SQL Tracker
- [ ] User menjalankan DDL dari SQL Tracker ke production
- [ ] Production diverifikasi
