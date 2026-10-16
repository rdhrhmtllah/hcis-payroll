# DDL-2026-100-visitor-daftar-anggota-tamu

## Ringkas

Menambahkan kolom JSON `Daftar_Anggota_Json` ke tabel transaksi detail tamu dan proyeksi tamu efektif untuk mencatat **seluruh rombongan yang datang**, termasuk tamu utama.

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | + `Daftar_Anggota_Json` (`nvarchar(max) NULL`) + `CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json` (`ISJSON`) |
| `N_VISITOR_Tamu_Efektif` | + `Daftar_Anggota_Json` (`nvarchar(max) NULL`) + `CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json` (`ISJSON`) |

## Kenapa muncul sekarang

Registrasi tamu sebelumnya hanya mencatat 1 nama (`Nama_Lengkap_Tamu`) dan 1 angka (`Jumlah_Tamu`). Dari 83 registrasi di `Web_HRIS` development, 62 (75%) membawa lebih dari 1 orang (rombongan sampai 5 orang), sehingga mayoritas orang yang melewati gerbang tidak tercatat namanya di mana pun.

Change ini menutup penelusuran identitas fisik rombongan tamu saat berada di dalam area perusahaan.

## Keputusan desain

1. **Kolom JSON, bukan tabel anak:** Rombongan diperlakukan sebagai satu manifes kedatangan, tanpa status kehadiran per-orang mandiri di gerbang.
2. **Semua orang tercatat:** Elemen pertama manifes adalah tamu utama (`Nama_Lengkap_Tamu`).
3. **Penyertaan identitas opsional:** `nama` wajib, `jenis_identitas` dan `no_identitas` opsional.
4. **`Jumlah_Tamu` diturunkan:** Dikalkulasi otomatis dari panjang daftar untuk registrasi baru.
5. **Data lama tidak di-backfill:** Baris lama bernilai `NULL` (berarti "daftar tidak pernah dikumpulkan").

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/drop.sql` |
| DML (tanpa mutasi data) | `database/dml/DDL-2026-100-visitor-daftar-anggota-tamu/data-change.sql` |
| Desain | `docs/superpowers/specs/2026-09-11-daftar-anggota-rombongan-tamu-design.md` |
| Rencana | `docs/superpowers/plans/2026-09-11-daftar-anggota-rombongan-tamu.md` |

Checksum forward DDL (SHA-256):
`94f3a947747b7e86ed0a63e77870300d9eee4321dec5ae066b2911763f15a6a2`

## Status

Development: **DEV_EXECUTED** (2026-09-11, `Web_HRIS`).

Diverifikasi dari katalog sistem:
- `INFORMATION_SCHEMA.COLUMNS`: 2 kolom `Daftar_Anggota_Json` `nvarchar(-1)` `nullable=YES` terbentuk pada `N_EMI_VISITOR_Transaksi_Tamu_Detail` dan `N_VISITOR_Tamu_Efektif`.
- `sys.check_constraints`: `CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json` dan `CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json` terbentuk. Teks bukan-JSON terbukti ditolak database.
- Baris lama terisi: 0 (data lama tidak di-backfill).

## Pekerjaan agent

- Menulis `forward.sql`, `drop.sql`, dan `data-change.sql`.
- Mengeksekusi `forward.sql` ke `Web_HRIS` development setelah persetujuan pengguna.
- Memverifikasi skema katalog sistem dan menguji penolakan `CHECK ISJSON`.
- Menulis unit/integration/Vue test suite dan dokumentasi ini.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu eksekusi ke production dan verifikasi.
- **Kabari pemilik aplikasi gerbang:** `Daftar_Anggota_Json` aditif dan nullable (gerbang lama tidak patah), tetapi parse JSON diperlukan bila gerbang ingin merender manifes rombongan.
