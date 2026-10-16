# DDL-2026-027 — Index for Visitor presence and validity reads

| Bidang | Isi |
|---|---|
| Change ID | `DDL-2026-027-index-visitor-kehadiran` |
| Jenis | DDL (indeks saja, tanpa perubahan kolom/data) |
| Target development | `Web_HRIS` @ team311 |
| Forward | `database/ddl/DDL-2026-027-index-visitor-kehadiran/forward.sql` |
| Drop (manual) | `database/ddl/DDL-2026-027-index-visitor-kehadiran/drop.sql` |
| DML pendaftaran | `database/dml/DDL-2026-027-index-visitor-kehadiran/data-change.sql` |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi `PENDING` |

## Deskripsi

Lima indeks nonclustered untuk pola baca kehadiran tamu dan masa berlaku permit.
Tidak ada satu baris data pun yang disentuh.

| Tabel | Indeks | Melayani |
|---|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu` | `IX_VISITOR_Tamu_Tanggal_Kunjungan` | rentang tanggal di papan harian, tren, laporan |
| `N_VISITOR_Tamu_Efektif` | `IX_VISITOR_Tamu_Efektif_Kehadiran` | "sudah masuk dan belum keluar" |
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `IX_VISITOR_Tamu_Detail_Kehadiran` | jalur cadangan catatan manual |
| `N_EMI_VISITOR_Transaksi_Permit_Detail` | `IX_VISITOR_Permit_Detail_Kadaluarsa` | permit kedaluwarsa hari ini / ≤7 hari |
| `N_VISITOR_Permit_Efektif` | `IX_VISITOR_Permit_Efektif_Status_Kadaluarsa` | permit per kategori & akan kedaluwarsa |

## Kenapa dua sisi (efektif dan detail) sama-sama diindeks

Kehadiran dibaca `COALESCE(efektif, detail)` — palang menang, catatan manual
cadangan. Penyaringnya ditulis sargable, `(e.X IS NOT NULL OR d.X IS NOT NULL)`,
sehingga optimizer dapat memakai indeks di kedua sisi. Bentuk
`COALESCE(...) IS NOT NULL` setara secara logika tetapi membungkus kolom dalam
fungsi, dan seluruh indeks di atas akan diabaikan.

## Catatan jujur tentang dampaknya

Pada development saat changeset ini dibuat, tabelnya kecil: tamu 74 baris,
permit 20, efektif 71/11. Pada ukuran segitu SQL Server akan tetap memilih table
scan dan indeks ini **tidak akan terpakai**; di development ia hanya menambah
sedikit beban tulis. Nilainya ada di produksi, yang ukurannya tidak diketahui
saat changeset ini dibuat.

Pengukuran pada development: `sebaranJam` 131 ms, papan harian 295 ms pada 74
baris. Angka itu didominasi latensi bolak-balik ke team311 (~72 ms sekali jalan),
bukan komputasi — jadi indeks ini tidak akan mengubahnya di development.

## Verifikasi pada development

Dijalankan 2026-08-25 terhadap `Web_HRIS`; `SELECT DB_NAME()` diverifikasi lebih
dulu. Kelima indeks dikonfirmasi ada lewat `sys.indexes` sesudah eksekusi.
Seluruh `CREATE INDEX` idempoten (`IF NOT EXISTS`), jadi menjalankan ulang aman.

## Sisa pekerjaan pengguna

1. Review changeset ini.
2. Jalankan `forward.sql` ke SQL Tracker, lalu ke produksi.
3. Verifikasi kelima indeks ada di produksi.
4. `drop.sql` hanya bantuan reset manual — jangan dijalankan sebagai bagian rilis.
