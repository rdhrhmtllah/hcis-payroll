# DDL-2026-190-sinkron-prod-non-pa

| | |
|---|---|
| Change ID | `DDL-2026-190-sinkron-prod-non-pa` |
| Jenis | DDL |
| Target | **Production** `emi_db_web_hr`, hanya melalui SQL Tracker |
| Development | Tidak dijalankan (semua objek sudah ada di `Web_HRIS`; seluruh penjaga bernilai "lewati" di dev) |
| Forward | `database/ddl/DDL-2026-190-sinkron-prod-non-pa/forward.sql` |
| Checksum forward (sha256) | `e1b51d5574ca0c3239644cec8235b6543352d1ad82d40861ca7e51eee59a2368` |
| Status | REVIEW_REQUIRED |

## Untuk apa

Menyamakan struktur **non-PA** production dengan development, dalam satu berkas. Performance
Appraisal ada di `DDL-2026-187`. Berkas ini menggantikan sisa DDL sinkron lama yang belum
dijalankan (`DDL-2026-128/129/154/155/162/163`) serta `DDL-2026-167` dan `DDL-2026-181`
untuk production.

## Cara dibandingkan (14 Okt 2026)

Langsung dari katalog kedua database, **bukan** dari berkas forward. Production hanya dibaca
(akun READ ONLY).

- Akun production tidak punya `VIEW DEFINITION`: tabel tanpa izin tidak tampak di katalog, serta
  definisi DEFAULT/CHECK/filter indeks tampil NULL. Karena itu:
  - setiap tabel yang "tidak ada" diuji dengan `SELECT TOP 0`: 529 benar-benar tidak ada (galat 208),
    8 ada tetapi tanpa izin (galat 229);
  - indeks dibandingkan lewat kolom kunci, INCLUDE, UNIQUE, urutan, dan *ada/tidaknya* filter;
  - CHECK bernama otomatis (`CK__...`) dibandingkan per tabel + kolom.
- Perbandingan nama tidak peka huruf: `HRIS_Hak_Cuti_Tambahan.status`, `Karyawan.max_Cuti`,
  `N_HRIS_Approval_Request.created_at` sudah ada di production dengan huruf berbeda.
- Hanya objek yang dipakai kode aplikasi ini (branch master). Dari 460 tabel non-PA yang tidak
  ada di production, hanya `N_KPI_Backfill_Cap_Cadangan` yang dipakai. Sisanya milik aplikasi
  lain yang berbagi database dev (careers `N_WEB_*`, AMS, kandidat, e-sign, desktop/mesin absensi,
  `N_SQL_DIAGNOSTIK_*`, tabel uji/cadangan).
- Data production dicek sebelum menambah FK/CHECK: 0 baris yatim, 0 pelanggaran.

## Isinya

**Tabel baru (1)**: `N_KPI_Backfill_Cap_Cadangan` beserta PK, CHECK, dan 2 indeks (`DDL-2026-167`;
dipakai `KpiCapParameterBackfillService`).

**Kolom baru (4)**: `N_HRIS_Menus.Section_Code/Section_Label/Section_Icon/Sort_Section` (`DDL-2026-181`).
Semua NULL.

**Kolom dilebarkan (4)**: hanya varchar yang lebih lebar di dev; nullability dan collation tetap,
data tidak berubah.

- `Absen_SN.Output` 5 → 10
- `KPI_Pengajuan.Alasan_Reject` 300 → 500
- `N_HCIS_Tabel_Absen.No_Transaksi_Lembur_Awal` 30 → 200
- `N_SOP_Penjadwalan_Integrasi.Id_Master_Ujian` 50 → 200 (validasi aplikasi mengizinkan 200)

**FK (17)**

- `N_SOP_Automation_Run.FK_N_SOP_Run_Rule` ([Id_SOP_Assignment_Rule] → N_SOP_Assignment_Rule)
- `N_SOP_Penjadwalan.FK_N_SOP_Pjd_Rule` ([Id_SOP_Assignment_Rule] → N_SOP_Assignment_Rule)
- `N_SOP_Paket_Draft.FK_N_SOP_Paket_Draft_Paket` ([Id_SOP_Paket] → N_SOP_Master_Paket)
- `N_SOP_Paket_Draft_Section.FK_N_SOP_Paket_Draft_Section_Header` ([Id_SOP_Paket_Draft] → N_SOP_Paket_Draft)
- `N_VISITOR_Inspeksi_Pola.FK_N_VISITOR_Inspeksi_Pola_Pos` ([Id_Pos_Inspeksi] → N_VISITOR_Master_Pos_Inspeksi)
- `N_VISITOR_Inspeksi_Pengecualian.FK_N_VISITOR_Inspeksi_Pengecualian_Pos` ([Id_Pos_Inspeksi] → N_VISITOR_Master_Pos_Inspeksi)
- `N_VISITOR_Inspeksi_Booking.FK_N_VISITOR_Inspeksi_Booking_Pos` ([Id_Pos_Inspeksi] → N_VISITOR_Master_Pos_Inspeksi)
- `N_SOP_Paket_Draft_Item.FK_N_SOP_Paket_Draft_Item_Header` ([Id_SOP_Paket_Draft] → N_SOP_Paket_Draft)
- `N_SOP_Paket_Draft_Item.FK_N_SOP_Paket_Draft_Item_Section` ([Id_SOP_Draft_Section] → N_SOP_Paket_Draft_Section)
- `N_VISITOR_Inspeksi_Pola.FK_N_VISITOR_Inspeksi_Pola_Shift` ([Id_Shift_Inspeksi] → N_VISITOR_Master_Shift_Inspeksi)
- `N_VISITOR_Inspeksi_Pengecualian.FK_N_VISITOR_Inspeksi_Pengecualian_Shift` ([Id_Shift_Inspeksi] → N_VISITOR_Master_Shift_Inspeksi)
- `N_SOP_Paket_Draft_Item_Versi.FK_N_SOP_Paket_Draft_Item_Versi_Item` ([Id_SOP_Draft_Item] → N_SOP_Paket_Draft_Item)
- `N_VISITOR_Inspeksi_Booking.FK_N_VISITOR_Inspeksi_Booking_Shift` ([Id_Shift_Inspeksi] → N_VISITOR_Master_Shift_Inspeksi)
- `N_VISITOR_Inspeksi_Hari_Shift.FK_N_VISITOR_Inspeksi_Hari_Shift_Shift` ([Id_Shift_Inspeksi] → N_VISITOR_Master_Shift_Inspeksi)
- `N_SOP_Assignment_Rule_Paket.FK_SOP_ARP_Rule` ([Id_SOP_Assignment_Rule] → N_SOP_Assignment_Rule)
- `N_SOP_Assignment_Rule_Paket.FK_SOP_ARP_Paket` ([Id_SOP_Paket] → N_SOP_Master_Paket)
- `N_SOP_Progress.FK_N_SOP_Progress_Versi` ([Id_SOP_Item_Versi] → N_SOP_Item_Versi)

**CHECK (12)**

- `N_SOP_Automation_Run.CK_N_SOP_Run_Type`
- `N_SOP_Automation_Run.CK_N_SOP_Run_Trigger`
- `N_SOP_Automation_Run.CK_N_SOP_Run_Status`
- `N_SOP_Automation_Run.CK_N_SOP_Run_Result_JSON`
- `N_SOP_Prioritas.CK_N_SOP_Prioritas_Aktif`
- `N_VISITOR_Tamu_Efektif.CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json`
- `N_EMI_VISITOR_Transaksi_Tamu_Detail.CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu`
- `N_VISITOR_Tamu_Efektif.CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu`
- `N_SOP_Assignment_Rule_Paket.CK_SOP_ARP_Override`
- `N_SOP_Assignment_Rule_Paket.CK_SOP_ARP_FlagAktif`
- `N_SOP_Assignment_Rule_Paket.CK_SOP_ARP_DeadlineMod`
- `N_HRIS_Grup_Fitur_Map.CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role`

**Indeks disusun ulang (8)**

- `N_SOP_Automation_Run.IX_N_SOP_Run_History`: prod `[Created_At],[Tipe_Run],[Status_Run]` → dev `[Created_At] DESC,[Tipe_Run],[Status_Run]`
- `N_KPI_Target_Koreksi.IX_N_KPI_Target_Koreksi_Det`: prod `[Id_Pengajuan_Det],[Waktu]` → dev `[Id_Pengajuan_Det],[Waktu] DESC`
- `N_EMI_VISITOR_Transaksi_Permit.UX_VISITOR_Permit_Requester_Idempotency`: prod `[Kode_Karyawan_Pemohon],[Idempotency_Key]` → dev `[Kode_Karyawan_Pemohon],[Id_Api_Aplikasi],[Idempotency_Key]`
- `Transaksi_Lembur_Det.IX_Transaksi_Lembur_Det_NoTrans_Kode`: prod `[No_Transaksi],[Kode_Karyawan],[Tanggal_Lembur]` → dev `[No_Transaksi],[Kode_Karyawan],[Tanggal_Lembur] DESC`
- `N_EMI_VISITOR_Transaksi_Tamu.UX_VISITOR_Tamu_Requester_Idempotency`: prod `[Kode_Karyawan_PIC],[Idempotency_Key]` → dev `[Kode_Karyawan_PIC],[Id_Api_Aplikasi],[Idempotency_Key]`
- `N_SOP_Paket_Draft.IX_N_SOP_Paket_Draft_Paket_Status`: prod `[Id_SOP_Paket],[Status],[Updated_At]` → dev `[Id_SOP_Paket],[Status],[Updated_At] DESC`
- `N_SOP_Paket_Versi.IX_N_SOP_Paket_Versi_Paket_Tanggal`: prod `[Id_SOP_Paket],[Tanggal_Publish]` → dev `[Id_SOP_Paket],[Tanggal_Publish] DESC`
- `N_SOP_Paket_Draft_Item_Versi.IX_N_SOP_Paket_Draft_Item_Versi_Status`: prod `[Id_SOP_Draft_Item],[Status],[Versi_Ke]` → dev `[Id_SOP_Draft_Item],[Status],[Versi_Ke] DESC`

Indeks disusun ulang memakai `CREATE INDEX ... WITH (DROP_EXISTING = ON)`: satu pernyataan, tanpa
`DROP INDEX` terpisah. Tabel terbesar `Transaksi_Lembur_Det` ±36 ribu baris.

## Sengaja TIDAK dibawa

| Objek | Alasan |
|---|---|
| `FK_MenuCap_Aksi` | Production punya 1 baris yatim: `N_HRIS_Menu_Capabilities` Id 3 (`menuApprovalMatrixKpiPage`, `Id_Aksi` 8) yang aksinya tidak ada di `HRIS_Klasifikasi_Aksi`. Rapikan datanya dulu bila FK ini diinginkan. |
| Kolom visitor `Jenis_Tamu`, `No_Permit_Rutin`, `Jenis_Tamu_Rutin`, tabel `N_EMI_VISITOR_Mitra*`, indeks identitas permit | Milik PR #623 (belum di-merge); ikut DDL-2026-168/169/179 saat PR itu naik. |
| `N_HRIS_Role_Functional.Jenis_Role/Kode_Tingkat`, `N_HRIS_Role_User.Berlaku_Sampai/Alasan`, `HRIS_Page_Access.Alasan/Berlaku_Sampai`, `N_HRIS_Tingkat_Peran` | Dari pekerjaan RBAC yang belum ada di master; kode master tidak memakainya. |
| `HRIS_KANDIDAT_*`, `N_HRIS_KANDIDAT_*` | Aplikasi kandidat; satu-satunya rujukan di repo ini middleware `CheckOfficialApiKey` yang tidak terdaftar. |
| `N_HRIS_Approval_Request` (id, Kode_Karyawan, Flow_Id, angka_wa, update_at), `N_HCIS_Tabel_Absen.Koefisien_OT_Gross` | Tidak dirujuk kode. |
| Beda tipe/nullable lain (mis. `Karyawan.Kode_Karyawan` 15 vs 255, `HRIS_Buku_Cuti.jumlah_terpakai` float vs decimal, NOT NULL di dev) | Bukan pelebaran aman: mengubah tipe, mempersempit, atau bisa gagal pada data production; kolom Karyawan dirujuk FK dan indeks. |
| 8 tabel tanpa izin baca (`N_EMI_Master_NFC`, `N_EMI_Binding_NFC`, `N_HRIS_Mesin_Absensi*`, `CHECKINOUT_Lain`, `N_HRIS_CHECKINOUT_ANOMALI`, `N_WEB_CAREERS_Mpp_Sla_Perpanjangan`, `init`) | Ada di production tetapi strukturnya tidak bisa dibandingkan dengan akun ini. |
| 92 indeks yang hanya ada di production | Indeks tuning production; tidak dihapus. |

## Pengaman

Setiap langkah berpenjaga (`OBJECT_ID` / `COL_LENGTH` / `sys.index_columns`), sehingga aman diulang.
Tidak ada DROP TABLE/COLUMN dan tidak ada penyempitan kolom.

## Verifikasi (agent, 14 Okt 2026)

1. **Kompilasi tanpa eksekusi** (`SET NOEXEC ON`) di development: 50 batch, 0 galat.
2. **Evaluasi penjaga**: ke-48 kondisi `IF` dijalankan sebagai `SELECT`. Di production 48 dari 48
   bernilai "jalankan"; di development 0 dari 48, artinya development sudah dalam keadaan tujuan.
3. Data production: 0 yatim untuk 17 FK, 0 pelanggaran untuk 12 CHECK; kolom yang dilebarkan
   tidak terindeks atau dirujuk FK.

## Urutan di production

Bebas terhadap paket PA (`DDL-2026-187` → `DML-2026-188` → `DML-2026-189`). Disarankan
dijalankan sebelum `DML-2026-182` (menu PMS tiga tingkat), yang membutuhkan kolom `Section_*`.
