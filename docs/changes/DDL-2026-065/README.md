# DDL-2026-065 — Indeks mencakup untuk panel Titik Henti

| | |
|---|---|
| Change ID | `DDL-2026-065-sop-indeks-penyerapan-versi` |
| Tipe | DDL (hanya `CREATE INDEX`) |
| Basis data target | `Web_HRIS` |
| Lingkungan eksekusi agen | development, diverifikasi `SELECT DB_NAME()` |
| Tanggal eksekusi dev | 2026-09-01 |
| Forward | `database/ddl/DDL-2026-065-sop-indeks-penyerapan-versi/forward.sql` |
| Drop (alat bantu manual) | `database/ddl/DDL-2026-065-sop-indeks-penyerapan-versi/drop.sql` |
| DML | tidak ada |
| Checksum forward.sql (sha256) | `95645f517657f4b9ecca2cde41cf70e793cee6ef88667bd036930f0ec66f2df5` |

## Mengapa

Audit 2026-09-01 (temuan M-2) menemukan
`SopRingkasanAgregatService::penyerapanVersi()` — sumber panel Titik Henti —
memindai penuh `N_SOP_Progress`: **1.144 logical reads**, dua `Clustered Index
Scan`, tiga `Sort`.

Kuerinya menyaring berdasarkan `Status`, menjoin lewat `Id_SOP_Paket_Item`, lalu
mengelompokkan per `(item, karyawan)` dengan `Id_SOP_Item_Versi` sebagai pembeda.
`IX_N_SOP_Prg_Item` yang sudah ada hanya berkunci `Id_SOP_Paket_Item` **tanpa
mencakup kolom keluarannya**, sehingga pengoptimal memilih pindaian penuh
ketimbang seek diikuti lookup — keputusan yang benar baginya, dan itulah yang
diperbaiki indeks ini.

## Objek yang dibuat

```
IX_N_SOP_Prg_Item_Status
    ON N_SOP_Progress (Id_SOP_Paket_Item, Status)
    INCLUDE (Id_SOP_Item_Versi, Kode_Karyawan)
```

## Hasil terukur (dev, 2026-09-01)

| | Sebelum | Sesudah |
|---|---|---|
| Logical reads | **1.144** | **162** |
| Akses `N_SOP_Progress` | Clustered Index Scan | **Index Seek** |

Scan yang tersisa berada di `N_SOP_Paket_Item` (31 baris) — tak layak diindeks.

## Mengapa panel ini TIDAK diberi jendela waktu

Berbeda dari panel Pemahaman (DDL-2026-064), Titik Henti **sengaja tidak
dibatasi waktu**. Ia menjawab "siapa yang masih tertinggal di versi lama" —
pertanyaan tentang **keadaan sekarang**, bukan aktivitas terkini. Orang yang
mengonfirmasi revisi kedua tiga tahun lalu sementara yang berlaku sudah keempat
memang masih tertinggal; jendela waktu akan menyembunyikan persis orang yang
dicari panel itu.

Karena jendela bukan pilihan, indeks mencakup adalah satu-satunya cara
membatasi biayanya tanpa mengubah jawabannya.

## Yang sengaja TIDAK dihapus

`IX_N_SOP_Prg_Item (Id_SOP_Paket_Item)` menjadi awalan dari indeks baru ini dan
secara teori tergantikan. Tidak dihapus: aturan agen melarang `DROP INDEX`, dan
lebih dari itu — indeks lama lebih ramping dan mungkin masih dipilih pengoptimal
untuk kueri lain. Pernyataan penghapusannya tersedia di `drop.sql` sebagai
catatan, dengan syarat diamati dulu lewat `sys.dm_db_index_usage_stats` setelah
beban nyata berjalan.

## Dikerjakan agen

- Menulis `forward.sql` dan `drop.sql`.
- Menjalankan `forward.sql` terhadap `Web_HRIS` (development) setelah
  memverifikasi target dengan `SELECT DB_NAME()`.
- Memverifikasi indeks terbentuk lewat `sys.indexes`, dan memverifikasi rencana
  eksekusinya benar-benar berubah menjadi `Index Seek`.
- Mencatat perubahan ini di `database/ddl/status.json`.

## Tersisa untuk pemilik project

1. Tinjau dan setujui `forward.sql`.
2. Catat ke SQL Tracker dengan isi dan checksum yang identik.
3. Eksekusi ke produksi, lalu konfirmasikan agar status `production` dapat
   dinaikkan. Status itu tidak boleh disimpulkan dari hasil development.
