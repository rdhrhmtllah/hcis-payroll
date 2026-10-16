# Desain: Tabel Proyeksi "Efektif" untuk Distribusi Visitor Lintas-Server

## Konteks &amp; Masalah

Data visitor (permit kendaraan &amp; kunjungan tamu) perlu didistribusikan ke server gate/security fisik yang terpisah. Server tersebut TIDAK boleh perlu tahu skema tabel master modul Visitor (`N_EMI_VISITOR_Master_*`) — kalau tabel transaksi dipindahkan begitu saja, seluruh tabel master ikut harus dipindahkan juga, dan setiap join/relasi jadi beban integrasi di sisi gate.

Aplikasi terpisah (di luar kode ini) akan melakukan pull data secara berkala **langsung lewat koneksi SQL Server** (bukan API) ke database ini, membaca baris yang `Flag_Sync='T'`, memindahkannya ke database gate, lalu menandai `Flag_Sync='Y'`.

Server gate dipakai untuk **validasi akses fisik** (security) — artinya kebenaran status "boleh akses atau tidak" itu krusial, bukan sekadar laporan/arsip yang boleh delay.

## Prinsip Arsitektur

Pisahkan dua tanggung jawab yang selama ini bercampur:

- **Tabel transaksi sumber** (`N_EMI_VISITOR_Transaksi_Permit`/`_Tamu` beserta Detail-nya) — tetap sebagai **riwayat proses approval**. Tidak pernah disentuh oleh mekanisme revoke/expire yang dijelaskan di sini.
- **Tabel Efektif baru** (`N_VISITOR_Permit_Efektif`, `N_VISITOR_Tamu_Efektif`) — **proyeksi status akses saat ini**. Satu baris per `No_Transaksi` (di-upsert, bukan append-only log), sepenuhnya denormalisasi (semua `Id_Master_*` ditulis sebagai teks literal), dan `Status_Efektif` bisa bertransisi `AKTIF → DIBATALKAN` atau `AKTIF → EXPIRED`. Tabel inilah satu-satunya yang dibaca aplikasi puller.

Pemisahan ini menjawab langsung concern awal user: transaksi sumber "tidak goyah lagi" (riwayat approval tetap utuh), sementara status akses efektif tetap bisa berubah kalau memang perlu dibatalkan.

## Alur Data &amp; Titik Pemicu

1. **Approval selesai** (`VerifikatorVisitorController::approvePermit()`/`approveTamu()`, setelah `isApprovalComplete()` true) → panggil `VisitorEfektifService::syncPermitEfektif($noTransaksi)` / `syncTamuEfektif($noTransaksi)` → upsert 1 baris, `Status_Efektif='AKTIF'`, `Flag_Sync='T'`.
2. **Auto-approve saat submit** (`TransaksiPermitController::store()` / `TransaksiTamuController::store()`, cabang `config('needs_approval') === false`) → **panggil service yang sama** segera setelah `Flag_Approval='Y'` di-set. Ini WAJIB, karena tanpa hook ini permit/tamu yang auto-approve tidak akan pernah masuk ke Efektif sama sekali (tidak pernah lewat aksi approve verifikator).
3. **Karyawan cancel permit/tamu miliknya sendiri** (`destroy()` yang sudah ada di kedua controller, soft-delete `Flag_Aktif='N'`) → hook ke `VisitorEfektifService::revokePermitEfektif()`/`revokeTamuEfektif()` — **hanya berefek kalau baris Efektif untuk `No_Transaksi` itu memang ada** (kalau belum pernah diapprove, tidak ada apa-apa yang perlu direvoke, no-op).
4. **Admin/verifikator revoke milik orang lain** — **endpoint baru**: `POST /verifikator-permit/{noTransaksi}/revoke` dan `POST /verifikator-tamu/{noTransaksi}/revoke` di `VerifikatorVisitorController`, wajib mengisi `alasan`. Dilindungi middleware `visitorVerifikatorPage` yang sama (Fase 3), tidak perlu jadi "approver step tertentu" karena tidak ada lagi step pending di titik ini.
5. **Kadaluarsa** — command terjadwal baru `visitor:expire-visitor-efektif`, jalan harian (mis. 00:05), dijadwalkan lewat Laravel Scheduler. Query: `Status_Efektif='AKTIF' AND Tanggal_Kadaluarsa < CURRENT_DATE` (Permit) / `Status_Efektif='AKTIF' AND Tanggal_Kunjungan < CURRENT_DATE` (Tamu, H+1 dari tanggal kunjungan) → set `EXPIRED`, `Flag_Sync='T'`.
6. **Aplikasi puller** (di luar kode ini) → `SELECT * FROM N_VISITOR_Permit_Efektif WHERE Flag_Sync='T'` → insert/update ke DB gate → `UPDATE ... SET Flag_Sync='Y', Synced_At=GETDATE()`. Tanggung jawab penuh aplikasi eksternal, tidak ada endpoint API yang perlu dibuat dari sisi Laravel ini.

### VisitorEfektifService (baru)

Satu service terpusat yang jadi satu-satunya titik tulis ke kedua tabel Efektif — mengikuti pola konsolidasi `VisitorPermitQueryService` yang sudah ada:

- `syncPermitEfektif(string $noTransaksi): void` — resolve data terbaru dari `basePermitQuery()` (via `VisitorPermitQueryService`), upsert ke `N_VISITOR_Permit_Efektif` dengan `Status_Efektif='AKTIF'`. Insert pertama:
  - `Dibuat_At`/`Dibuat_Oleh_Nama` = `h.Created_At`/`Nama_Pemohon` (kapan & siapa yang **mengajukan** transaksi sumber)
  - `Disetujui_At`/`Disetujui_Oleh_Nama` = waktu & nama approver STEP TERAKHIR dari `HRIS_Approval_Request` (`ORDER BY Request_Order_Flow DESC`, resolve `Approved_By_Kode`/`Approver_Karyawan_Kode` ke `Karyawan.Nama`), atau `"Sistem (Auto-Approve)"` + waktu submit kalau dipanggil dari cabang auto-approve (tidak ada baris `HRIS_Approval_Request` sama sekali)
  - `Riwayat_Approval` = JSON array seluruh baris `HRIS_Approval_Request` utk `No_Transaksi` ini (`urutan`, `nama`, `tanggal`), array kosong `[]` kalau auto-approve
  - `Updated_At`/`Updated_Oleh_Nama` = disamakan dengan `Disetujui_At`/`Disetujui_Oleh_Nama` (perubahan status terakhir saat ini ya proses approval itu sendiri)
- `syncTamuEfektif(string $noTransaksi): void` — sama, dari `baseTamuQuery()`; `Dibuat_Oleh_Nama` = `Nama_PIC` (submitter = PIC pada alur self-service saat ini).
- `revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): void` — kalau baris Efektif ada dan `Status_Efektif='AKTIF'`, set `DIBATALKAN` + `Dibatalkan_Oleh_Nama`/`Dibatalkan_At` + `Updated_At`/`Updated_Oleh_Nama` (nilai sama dengan `Dibatalkan_*`) + reset `Flag_Sync='T'`. No-op kalau baris tidak ada.
- `revokeTamuEfektif(...)` — sama untuk tamu.
- Command expire: saat set `EXPIRED`, isi juga `Updated_At`/`Updated_Oleh_Nama='Sistem (Auto-Expire)'`. `Dibatalkan_Oleh_Nama`/`Dibatalkan_At` tetap `NULL` (tidak ada yang membatalkan, murni lewat tanggal).
- Idempoten: upsert berdasarkan `No_Transaksi` (UNIQUE), aman dipanggil berkali-kali untuk transaksi yang sama. `Dibuat_At`/`Dibuat_Oleh_Nama`/`Riwayat_Approval` HANYA diisi saat INSERT pertama, tidak pernah ditimpa lagi oleh pemanggilan berikutnya (approval-nya sudah final begitu efektif).

## Skema Tabel

### `N_VISITOR_Permit_Efektif`


| Kolom                   | Tipe                  | Keterangan                                                          |
| ----------------------- | --------------------- | ------------------------------------------------------------------- |
| `Id_Permit_Efektif`     | int identity PK       |                                                                     |
| `Kode_Perusahaan`       | varchar(10)           | default `'001'`, konsisten dengan `N_HRIS_Menus`/`HRIS_Page_Access` |
| `No_Transaksi`          | varchar(20) UNIQUE    | kunci korelasi audit ke tabel sumber, bukan FK                      |
| `Kode_Karyawan_Pemohon` | varchar               |                                                                     |
| `Nama_Pemohon`          | varchar               | literal                                                             |
| `No_HP_Pemohon`         | varchar nullable      | literal, kontak pemohon (`k.HP`)                                    |
| `Departemen`            | varchar               | literal                                                             |
| `Jabatan`               | varchar               | literal                                                             |
| `Keterangan_Pengajuan`  | varchar(500) nullable | literal, alasan/catatan pengajuan dari pemohon                      |
| `No_Plat_Kendaraan`     | varchar               | literal (sudah ter-CONCAT)                                          |
| `Nama_Kategori_Permit`  | varchar               | literal                                                             |
| `Deskripsi_Akses`       | varchar               | literal — dipakai gate untuk tentukan area akses                    |
| `Kode_Warna_UI`         | varchar               | literal                                                             |
| `Nama_Vehicle_Type`     | varchar               | literal                                                             |
| `Nama_Model_Kendaraan`  | varchar               | literal                                                             |
| `Warna_Kendaraan`       | varchar               | literal                                                             |
| `Tahun_Kendaraan`       | varchar               |                                                                     |
| `Nama_Durasi`           | varchar               | literal, dari snapshot durasi                                       |
| `Tanggal_Mulai_Berlaku` | date                  |                                                                     |
| `Tanggal_Kadaluarsa`    | date                  | dipakai command expire                                              |
| `Status_Efektif`        | varchar(20)           | `AKTIF` / `DIBATALKAN` / `EXPIRED`                                  |
| `Alasan_Status`         | varchar(500) nullable | diisi saat revoke                                                   |
| `Dibatalkan_Oleh_Nama`  | varchar nullable      | literal; NULL kalau `EXPIRED`                                       |
| `Dibatalkan_At`         | datetime nullable     |                                                                     |
| `Disetujui_Oleh_Nama`   | varchar               | literal, approver step terakhir, atau `"Sistem (Auto-Approve)"`     |
| `Disetujui_At`          | datetime              |                                                                     |
| `Riwayat_Approval`      | nvarchar(max), JSON   | array semua step: `[{"urutan":1,"nama":"...","tanggal":"..."}]`, `[]` kalau auto-approve. Diisi sekali saat insert, tidak pernah diubah lagi |
| `Dibuat_At`             | datetime              | kapan transaksi sumber **disubmit** (`h.Created_At`)                |
| `Dibuat_Oleh_Nama`      | varchar               | literal, nama **pemohon/requester** (sama dengan `Nama_Pemohon`)    |
| `Updated_At`            | datetime              | kapan terakhir baris ini berubah (status apapun)                    |
| `Updated_Oleh_Nama`     | varchar nullable      | literal; aktor perubahan status terakhir (approver/pembatal/`"Sistem (Auto-Expire)"`) |
| `Flag_Sync`             | char(1)               | `Y`/`T`, default `'T'`                                              |
| `Synced_At`             | datetime nullable     | diisi aplikasi puller                                               |


### `N_VISITOR_Tamu_Efektif`


| Kolom                      | Tipe                  | Keterangan                                            |
| -------------------------- | --------------------- | ----------------------------------------------------- |
| `Id_Tamu_Efektif`          | int identity PK       |                                                       |
| `Kode_Perusahaan`          | varchar(10)           | default `'001'`                                       |
| `No_Transaksi`             | varchar(20) UNIQUE    |                                                       |
| `Nama_Tamu`                | varchar               | literal                                               |
| `Instansi_Asal`            | varchar               | literal                                               |
| `No_HP`                    | varchar               | literal                                               |
| `Keperluan`                | varchar               | literal (sudah teks bebas di sumber, bukan FK master) |
| `Keterangan_Tujuan_Detail` | varchar(500) nullable | literal, tujuan spesifik kunjungan (mis. "temui siapa di lantai berapa") |
| `Keterangan_Tambahan`      | varchar(500) nullable | literal, catatan tambahan bebas                       |
| `No_Identitas`             | varchar nullable      | literal, nomor KTP/SIM/Paspor tamu — dipakai gate cocokkan identitas fisik |
| `Jenis_Identitas`          | varchar nullable      | literal, jenis identitas (KTP/SIM/Paspor)             |
| `Kode_Karyawan_PIC`        | varchar               |                                                       |
| `Nama_PIC`                 | varchar               | literal                                               |
| `Divisi_PIC`               | varchar               | literal                                               |
| `Jenis_Kendaraan`          | varchar               |                                                       |
| `No_Plat_Kendaraan`        | varchar nullable      | literal, NULL kalau jalan kaki                        |
| `Tanggal_Kunjungan`        | date                  | dipakai command expire                                |
| `Waktu_Estimasi_Kunjungan` | varchar               |                                                       |
| `Jumlah_Tamu`              | int                   |                                                       |
| `Status_Efektif`           | varchar(20)           | `AKTIF` / `DIBATALKAN` / `EXPIRED`                    |
| `Alasan_Status`            | varchar(500) nullable |                                                       |
| `Dibatalkan_Oleh_Nama`     | varchar nullable      |                                                       |
| `Dibatalkan_At`            | datetime nullable     |                                                       |
| `Disetujui_Oleh_Nama`      | varchar               | literal, approver step terakhir, atau `"Sistem (Auto-Approve)"` |
| `Disetujui_At`             | datetime              |                                                       |
| `Riwayat_Approval`         | nvarchar(max), JSON   | array semua step approval, `[]` kalau auto-approve, diisi sekali saat insert |
| `Dibuat_At`                | datetime              | kapan transaksi sumber disubmit (`h.Created_At`)      |
| `Dibuat_Oleh_Nama`         | varchar               | literal, nama PIC/pemohon                             |
| `Updated_At`               | datetime              | kapan terakhir baris ini berubah (status apapun)      |
| `Updated_Oleh_Nama`        | varchar nullable      | literal; aktor perubahan status terakhir              |
| `Flag_Sync`                | char(1)               | `Y`/`T`, default `'T'`                                |
| `Synced_At`                | datetime nullable     |                                                       |


## Aturan `Flag_Sync` (Kontrak dengan Aplikasi Puller)

Satu kolom `Flag_Sync` (`Y`/`T`) sudah cukup menjawab kebutuhan "beritahu sync kalau ada perubahan" — tidak perlu kolom kedua seperti `Flag_Diproses_Ulang` — SELAMA aturan berikut dipegang konsisten:

- `Flag_Sync` di-reset ke `'T'` oleh kode Laravel **setiap kali `Status_Efektif` berubah** — baik saat baris pertama dibuat (AKTIF), maupun belakangan berubah jadi `DIBATALKAN`/`EXPIRED`. Bukan cuma sekali di titik insert awal.
- `Synced_At` HANYA diisi/diupdate oleh aplikasi puller (bukan oleh Laravel), tiap kali dia berhasil menarik &amp; memproses baris tsb. Nilainya jadi jejak "kapan terakhir kali berhasil ditarik" — membedakan baris yang belum pernah ditarik sama sekali (`Synced_At IS NULL`) dari yang sudah pernah ditarik tapi berubah lagi (`Synced_At` terisi, `Flag_Sync='T'` lagi).
- **Kontrak wajib di sisi aplikasi puller**: upsert berdasarkan `No_Transaksi` — `IF EXISTS (No_Transaksi di DB gate) THEN UPDATE ELSE INSERT`. Puller tidak boleh asumsi setiap baris `Flag_Sync='T'` pasti baris baru; harus selalu cek dulu.

## Aturan Transisi Status

```
(belum ada baris) --approve selesai / auto-approve--> AKTIF
AKTIF --admin revoke / self cancel (destroy)--> DIBATALKAN
AKTIF --Tanggal_Kadaluarsa/Tanggal_Kunjungan terlewati--> EXPIRED
DIBATALKAN --(tidak ada jalur balik)--
EXPIRED --(tidak ada jalur balik)--
```

- Command expire HANYA menyentuh baris yang `Status_Efektif='AKTIF'` — mencegah race condition menimpa baris yang baru saja `DIBATALKAN`.
- Tidak ada mekanisme "un-cancel"/reinstate. Sekali `DIBATALKAN`, transaksi itu tetap begitu selamanya — kebutuhan akses baru berarti submit transaksi baru (No_Transaksi baru, baris Efektif baru).

## Yang Sengaja Di Luar Scope

- **Retensi/cleanup data Efektif** — tabel akan terus bertambah baris seumur sistem berjalan. Command pembersihan (mis. hapus `DIBATALKAN`/`EXPIRED` &gt; N bulan) bisa ditambahkan terpisah nanti kalau volume jadi masalah nyata.
- **Mekanisme transport sinkronisasi** (aplikasi puller, jadwal pull, dsb) — sepenuhnya di luar kode ini, dikerjakan sebagai aplikasi terpisah oleh user.
- **Distingsi status "SELESAI" untuk Tamu yang sudah checked-out** — gate hanya peduli AKTIF/tidak, tidak perlu tahu granularitas checked-in/checked-out.
- **`Waktu_Aktual_Masuk`/`Waktu_Aktual_Keluar`** — ini jejak SETELAH kunjungan terjadi (diisi gate sendiri saat check-in/out), bukan syarat SEBELUM akses diberikan, jadi tidak perlu ada di proyeksi Efektif.
- **`Sisa_Hari`** (permit) — nilai turunan (`DATEDIFF`), tidak perlu disimpan; gate cukup lihat `Status_Efektif` untuk tahu apakah masih berlaku.

## Edge Case yang Tercakup

- Re-run sync untuk `No_Transaksi` yang sama → aman (UNIQUE + upsert, tidak dobel baris).
- Revoke sebelum sempat ke-pull sama sekali (`Flag_Sync` masih `'T'` dari insert awal) → tidak masalah, gate cukup baca state akhir saat pertama kali pull.
- `TransaksiTamuController::update()` bisa mengubah `Tanggal_Kunjungan`/`Status_Kunjungan` tanpa validasi apapun (temuan sampingan, existing bug) — di luar scope perbaikan desain ini, tapi dicatat sebagai risiko: kalau tanggal kunjungan diubah setelah Efektif dibuat, Efektif tidak otomatis ikut berubah (proyeksi diam-diam jadi stale). Rekomendasi: kalau endpoint ini nanti dipakai aktif, panggil ulang `syncTamuEfektif()` di dalamnya juga.

## Verifikasi

- Test lewat tinker langsung ke DB dev (pola yang sama dipakai sepanjang sesi ini): insert/panggil service untuk transaksi uji, cek `Status_Efektif` &amp; `Flag_Sync` bertransisi benar di tiap titik pemicu (approve, auto-approve, self-cancel, admin-revoke, command expire).
- `php artisan route:list` untuk pastikan endpoint revoke baru terpasang dengan middleware yang benar.
- DDL dijalankan manual oleh user di SQL Server (bukan lewat migration Laravel), disimpan di `database/visitor/`.

