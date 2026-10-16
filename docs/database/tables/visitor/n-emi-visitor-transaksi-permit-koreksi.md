# N_EMI_VISITOR_Transaksi_Permit_Koreksi

Riwayat koreksi data permit karyawan oleh verifikator saat review. Satu baris per field yang berubah; satu kali simpan menghasilkan beberapa baris dengan `Kelompok_Koreksi` yang sama.

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_Permit_Koreksi` | int identity | NOT NULL | PK; juga urutan kronologis yang andal |
| `No_Transaksi` | varchar(50) | NOT NULL | nomor permit (`N_EMI_VISITOR_Transaksi_Permit.No_Transaksi`) |
| `Kelompok_Koreksi` | uniqueidentifier | NOT NULL | pengelompok satu kali simpan |
| `Nama_Field` | varchar(50) | NOT NULL | `KATEGORI_PERMIT`, `PLAT_NOMOR`, `MODEL_KENDARAAN`, `WARNA`, `TAHUN`, `KETERANGAN` |
| `Nilai_Lama` / `Nilai_Baru` | nvarchar(500) | NULL | nilai terbaca manusia (nama, bukan id); NULL = kosong |
| `Alasan` | nvarchar(500) | NOT NULL | alasan koreksi, wajib |
| `Created_At` | datetime2 | NOT NULL | default `sysdatetime()` |
| `Created_By` | varchar(100) | NOT NULL | `Kode_Karyawan` verifikator (`VisitorApprovalPolicyService::requesterKode`) |

- Daftar `Nama_Field` divalidasi backend lewat konstanta `PermitKoreksiService::FIELD_*`; tanpa CHECK (AGENTS.md).
- Relasi logis ke header permit lewat `No_Transaksi`, tanpa FK — pola tabel transaksi VISITOR.
- Indeks `IX_VISITOR_Permit_Koreksi_No_Transaksi (No_Transaksi) INCLUDE (Created_At)`: satu-satunya pola baca adalah riwayat per permit.
- Hanya ditulis selama permit PENDING; baris tidak pernah diubah atau dihapus aplikasi.
- Pemakai: `App\Services\VISITOR\Workflow\Verification\PermitKoreksiService` (`koreksi`, `riwayat`), `VerifikatorVisitorController::koreksiPermit|showPermit`; route `POST verifikator-permit/{noTransaksi}/koreksi`, `GET verifikator-permit/{id}`.
- Perubahan: `DDL-2026-138-visitor-koreksi-permit`. SQL: `database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql`.
