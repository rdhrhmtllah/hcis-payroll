# DML-2026-011 — Backfill Database Change Dashboard dari ledger

Mendaftarkan setiap perubahan di `database/ddl/status.json` yang belum ada di
`N_SYSTEM_Database_Change`, sehingga dashboard menampilkan riwayat yang sama
dengan ledger repo.

## Masalah yang diperbaiki

Repo punya dua catatan perubahan database yang tidak saling mengisi:

| Catatan | Dibaca oleh |
|---|---|
| `database/ddl/status.json` | manusia dan agent, lewat repo |
| `N_SYSTEM_Database_Change` | Database Change Dashboard, lewat aplikasi |

Mengisi yang pertama tidak membuat perubahan muncul di yang kedua —
pendaftarannya adalah DML tersendiri. Karena langkah itu manual dan mudah
terlewat, ia memang terlewat: sampai 2026-08-18 dashboard hanya berisi **1**
baris sementara ledger sudah berisi **11**.

Sepuluh perubahan berikut tidak terlihat di dashboard, termasuk tabel yang sudah
berjalan di development:

```text
DDL-2026-001  DML-2026-002  DML-2026-003  DML-2026-004
DDL-2026-005  DDL-2026-006  DDL-2026-007  DML-2026-008
DDL-2026-009  DML-2026-010
```

## Cara isi berkas ini dibuat

Skrip di sesi ini membaca `status.json` dan menghasilkan satu blok `INSERT` per
entri — **tidak ada judul, path, atau status yang dikarang**. Ledger memakai dua
bentuk entri (bersarang untuk perubahan lama, datar untuk yang baru); keduanya
dinormalkan sebelum ditulis.

Setiap blok dijaga `IF NOT EXISTS` pada `Kode_Change`, jadi berkas ini aman
dijalankan ulang dan aman dijalankan setelah entri baru ditambahkan.

## Eksekusi

Di `Web_HRIS` (development), 2026-08-18:

| Jalan | Sebelum | Sesudah | Delta |
|---|---:|---:|---:|
| Sebelas blok pertama | 2 | 11 | 9 |
| Blok pendaftaran diri sendiri | 11 | 12 | 1 |

Jalan kedua sekaligus membuktikan idempotensinya: sebelas blok sebelumnya
dilewati, hanya yang baru tersisip.

Dashboard kini 12 baris, ledger 12 entri — cocok.

## Yang TIDAK diperbaiki di sini

Penyebab aslinya masih ada: sinkronisasi ledger ke dashboard tetap langkah
manual terpisah, jadi ia bisa terlewat lagi. Menjadikannya otomatis — lewat
command yang membaca `status.json`, atau menghapus `status.json` sekalian dan
menjadikan tabel satu-satunya sumber — adalah perubahan arsitektur kecil yang
butuh keputusan pemilik repo, bukan sesuatu yang layak diselipkan ke dalam
perbaikan data.

Status `sql_tracker` seluruh entri tetap `PENDING`. Backfill ini hanya membuat
riwayatnya terlihat; ia tidak mencatat apa pun ke SQL Tracker dan tidak
menyentuh production.
