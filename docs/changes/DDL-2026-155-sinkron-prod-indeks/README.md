# DDL-2026-155-sinkron-prod-indeks — Index yang kurang di production

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-155-sinkron-prod-indeks` |
| Jenis | DDL (sinkronisasi index) |
| Target | production `emi_db_web_hr` |
| Artifact | `database/ddl/DDL-2026-155-sinkron-prod-indeks/forward.sql` (sha256 `45f8f2566cb9e8c44278ac09005e42f9c01077bf07195bc8c1bbef7fab5b04fa`) |
| Drop manual | `database/ddl/DDL-2026-155-sinkron-prod-indeks/drop.sql` — jangan dijalankan agen |
| Bergantung pada | `DDL-2026-154-sinkron-prod-tabel-kolom` (index tabel baru) |
| Status | `REVIEW_REQUIRED` |

## Metode

Dibandingkan berdasarkan **definisi** (kolom kunci + urutan/DESC, INCLUDE, UNIQUE,
filter), bukan nama. Index yang definisinya sama tetapi bernama lain dianggap sudah ada.
PK/UNIQUE constraint tabel baru ada di DDL-2026-154-sinkron-prod-tabel-kolom.

Untuk tiap index UNIQUE pada tabel yang sudah berisi, prod dicek (baca saja) apakah
datanya punya duplikat yang akan menggagalkan `CREATE UNIQUE INDEX`: **semuanya 0**.

## Isi — 19 index

| Tabel | Index | Sifat | Keterangan |
|---|---|---|---|
| `N_EMI_VISITOR_Transaksi_Permit` | `UX_VISITOR_Permit_Kode_Kartu` | UNIQUE berfilter | tabel ada |
| `N_EMI_VISITOR_Transaksi_Permit` | `UX_VISITOR_Permit_Transaksi_Tamu` | UNIQUE berfilter | tabel ada |
| `N_EMI_VISITOR_Transaksi_Permit_Koreksi` | `IX_VISITOR_Permit_Koreksi_No_Transaksi` |  | tabel baru |
| `N_HRIS_Master_Kontak_Bantuan` | `IX_HRIS_Kontak_Bantuan_Lingkup` |  | tabel baru |
| `N_HRIS_Panduan_Halaman` | `IX_HRIS_Panduan_Halaman_Jenis_Page` |  | tabel baru |
| `N_HRIS_Panduan_Halaman` | `UX_HRIS_Panduan_Halaman_Unik` | UNIQUE  | tabel baru |
| `N_HRIS_Panduan_Role` | `IX_HRIS_Panduan_Role_Role` |  | tabel baru |
| `N_HRIS_Panduan_Role` | `UX_HRIS_Panduan_Role_Unik` | UNIQUE  | tabel baru |
| `N_HRIS_Panduan_Versi` | `UX_HRIS_Panduan_Versi_No` | UNIQUE  | tabel baru |
| `N_SOP_Dokumen` | `IX_N_SOP_Dokumen_Tipe` |  | tabel ada |
| `N_VISITOR_Inspeksi_Booking` | `IX_VISITOR_Inspeksi_Booking_Permit` |  | tabel ada |
| `N_VISITOR_Inspeksi_Booking` | `IX_VISITOR_Inspeksi_Booking_Slot` |  | tabel ada |
| `N_VISITOR_Inspeksi_Booking` | `UX_VISITOR_Inspeksi_Booking_Aktif` | UNIQUE berfilter | tabel ada |
| `N_VISITOR_Inspeksi_Hari_Shift` | `UX_VISITOR_Inspeksi_Hari_Shift` | UNIQUE  | tabel ada |
| `N_VISITOR_Inspeksi_Pengecualian` | `IX_VISITOR_Inspeksi_Pengecualian_Tanggal` |  | tabel ada |
| `N_VISITOR_Master_Pos_Inspeksi` | `UX_VISITOR_Master_Pos_Inspeksi_Nama` | UNIQUE  | tabel ada |
| `N_VISITOR_Master_Shift_Inspeksi` | `UX_VISITOR_Master_Shift_Inspeksi_Nama` | UNIQUE  | tabel ada |
| `N_VISITOR_Matriks_Tamu` | `UX_VISITOR_Matriks_Tamu_Role_Kategori` | UNIQUE  | tabel ada |
| `N_VISITOR_Permit_Efektif` | `IX_VISITOR_Permit_Efektif_Jenis_Akses` |  | tabel ada |

## Tidak dimasukkan

- 1 index pada tabel yang tak bisa dilihat akun audit (tak dapat diverifikasi): N_WEB_CAREERS_Mpp_Sla_Perpanjangan.UX_NWCSLAP_Urutan.
- Index yang butuh kolom yang tidak disinkronkan: HRIS_KANDIDAT_Users.IX_Users_KodeRole (Kode_Role).
- **53 index bernama sama tetapi definisinya berbeda** — mengubahnya butuh DROP + CREATE,
  di luar cakupan "yang kurang". Sebagian penting untuk ditinjau (mis. index unik
  idempotensi Visitor di dev menyertakan `Id_Api_Aplikasi`, di prod tidak):

| Index | Dev (jenis / unik / kunci / include / filter) | Prod |
|---|---|---|
| `Karyawan.IX_Karyawan_UserID_Web` | `NONCLUSTERED / 0 / UserID_Web /  / ` | `NONCLUSTERED / 0 / UserID_Web / Aktif,HP,ID_Divisi_Sub_Divisi,ID_Level_Jabatan,Kode_Karyawan,Kode_Perusahaan,Nama / ` |
| `KPI_Pengajuan.IX_KPI_Pengajuan_CancelledAt` | `NONCLUSTERED / 0 / Cancelled_At /  / ([cancelled_at] is not null)` | `NONCLUSTERED / 0 / Cancelled_At /  / ` |
| `KPI_Update_Performance.IX_KPI_UP_CancelledAt` | `NONCLUSTERED / 0 / Cancelled_At /  / ([cancelled_at] is not null)` | `NONCLUSTERED / 0 / Cancelled_At /  / ` |
| `KPI_Update_Performance_Sementara.IX_KPI_UPS_CancelledAt` | `NONCLUSTERED / 0 / Cancelled_At /  / ([cancelled_at] is not null)` | `NONCLUSTERED / 0 / Cancelled_At /  / ` |
| `N_EMI_VISITOR_Transaksi_Permit.IX_VISITOR_Permit_Api_Aplikasi` | `NONCLUSTERED / 0 / Id_Api_Aplikasi,Flag_Approval / Flag_Aktif,No_Transaksi / ([id_api_aplikasi] is not null)` | `NONCLUSTERED / 0 / Id_Api_Aplikasi,Flag_Approval / Flag_Aktif,No_Transaksi / ` |
| `N_EMI_VISITOR_Transaksi_Permit.UX_VISITOR_Permit_Requester_Idempotency` | `NONCLUSTERED / 1 / Kode_Karyawan_Pemohon,Id_Api_Aplikasi,Idempotency_Key /  / ([idempotency_key] is not null)` | `NONCLUSTERED / 1 / Kode_Karyawan_Pemohon,Idempotency_Key /  / ` |
| `N_EMI_VISITOR_Transaksi_Tamu.IX_VISITOR_Tamu_Api_Aplikasi` | `NONCLUSTERED / 0 / Id_Api_Aplikasi,Flag_Approval / Flag_Aktif,No_Transaksi / ([id_api_aplikasi] is not null)` | `NONCLUSTERED / 0 / Id_Api_Aplikasi,Flag_Approval / Flag_Aktif,No_Transaksi / ` |
| `N_EMI_VISITOR_Transaksi_Tamu.UX_VISITOR_Tamu_Requester_Idempotency` | `NONCLUSTERED / 1 / Kode_Karyawan_PIC,Id_Api_Aplikasi,Idempotency_Key /  / ([idempotency_key] is not null)` | `NONCLUSTERED / 1 / Kode_Karyawan_PIC,Idempotency_Key /  / ` |
| `N_HRIS_Absen_Recalc_Queue.UX_N_HRIS_Absen_Recalc_Queue_Active_UserDate` | `NONCLUSTERED / 1 / Kode_Perusahaan,UserID,Tanggal /  / ([statu in ('pending', 'processing'))` | `NONCLUSTERED / 1 / Kode_Perusahaan,UserID,Tanggal /  / ` |
| `N_HRIS_Activity_Event.IX_NHRIS_AE_GrupFitur` | `NONCLUSTERED / 0 / Id_Grup_Fitur,Created_At DESC / Action_Code,Event_Type,Id_Menu,Kode_Karyawan / ([id_grup_fitur] is not null)` | `NONCLUSTERED / 0 / Id_Grup_Fitur,Created_At DESC / Action_Code,Event_Type,Id_Menu,Kode_Karyawan / ` |
| `N_HRIS_Activity_Event.IX_NHRIS_AE_Menu` | `NONCLUSTERED / 0 / Id_Menu,Created_At DESC /  / ([id_menu] is not null)` | `NONCLUSTERED / 0 / Id_Menu,Created_At DESC /  / ` |
| `N_HRIS_Activity_Event.IX_NHRIS_AE_Security` | `NONCLUSTERED / 0 / Created_At DESC,Event_Type / Description,Ip_Address,Kode_Karyawan / ([event_type] in ('auth.failed', 'permission_denied', 'auth.login', 'auth.logout'))` | `NONCLUSTERED / 0 / Created_At DESC,Event_Type / Description,Ip_Address,Kode_Karyawan / ` |
| `N_HRIS_Activity_Event.IX_NHRIS_AE_Session` | `NONCLUSTERED / 0 / Session_Id,Created_At /  / ([session_id] is not null)` | `NONCLUSTERED / 0 / Session_Id,Created_At /  / ` |
| `N_HRIS_Activity_Event.IX_NHRIS_AE_UserID_Created` | `NONCLUSTERED / 0 / UserID_Web,Created_At DESC /  / ([userid_web] is not null)` | `NONCLUSTERED / 0 / UserID_Web,Created_At DESC /  / ` |
| `N_HRIS_Activity_Route_Map.IX_NHRIS_ARM_Lookup` | `NONCLUSTERED / 0 / Match_Type,Priority / Default_Action,Id_Grup_Fitur,Id_Menu,Match_Value / ([flag_aktif]='y')` | `NONCLUSTERED / 0 / Match_Type,Priority / Default_Action,Id_Grup_Fitur,Id_Menu,Match_Value / ` |
| `N_HRIS_Activity_Session.IX_NHRIS_AS_Active` | `NONCLUSTERED / 0 / Last_Activity_At DESC / Kode_Karyawan,Logout_At / ([logout_at] is null)` | `NONCLUSTERED / 0 / Last_Activity_At DESC / Kode_Karyawan,Logout_At / ` |
| `N_HRIS_Activity_Session.IX_NHRIS_AS_Fingerprint` | `NONCLUSTERED / 0 / Device_Fingerprint,Kode_Karyawan /  / ([device_fingerprint] is not null)` | `NONCLUSTERED / 0 / Device_Fingerprint,Kode_Karyawan /  / ` |
| `N_HRIS_Activity_Session.IX_NHRIS_AS_UserID_Login` | `NONCLUSTERED / 0 / UserID_Web,Login_At DESC /  / ([userid_web] is not null)` | `NONCLUSTERED / 0 / UserID_Web,Login_At DESC /  / ` |
| `N_HRIS_Cuti_Process_Batch_Detail.IX_N_HRIS_Cuti_Process_Batch_Detail_Cuti_Key` | `NONCLUSTERED / 0 / Cuti_Key /  / ([cuti_key] is not null)` | `NONCLUSTERED / 0 / Cuti_Key /  / ` |
| `N_HRIS_Cuti_Process_Batch_Detail.UX_N_HRIS_Cuti_Process_Batch_Detail_Batch_Cuti_Key` | `NONCLUSTERED / 1 / Id_Batch,Cuti_Key /  / ([cuti_key] is not null)` | `NONCLUSTERED / 1 / Id_Batch,Cuti_Key /  / ` |
| `N_HRIS_Cutoff_Configs.IDX_Active_Config` | `NONCLUSTERED / 0 / Id /  / ([effective_to] is null)` | `NONCLUSTERED / 0 / Id /  / ` |
| `N_HRIS_Konversi_Lembur_Efektif.UX_KonversiLembur_Asal` | `NONCLUSTERED / 1 / Kode_Perusahaan,Kode_Karyawan,Tanggal_Lembur_Asal /  / ([flag_aktif]='y')` | `NONCLUSTERED / 1 / Kode_Perusahaan,Kode_Karyawan,Tanggal_Lembur_Asal /  / ` |
| `N_HRIS_KPI_Notif_Skip.UQ_KPI_Notif_Skip_Periode` | `NONCLUSTERED / 1 / Kode_Karyawan,Periode /  / ([tipe_skip]='periode' and [flag_aktif]='y')` | `NONCLUSTERED / 1 / Kode_Karyawan,Periode /  / ` |
| `N_HRIS_KPI_Notif_Skip.UQ_KPI_Notif_Skip_Permanent` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ([tipe_skip]='permanent' and [flag_aktif]='y')` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ` |
| `N_HRIS_Log_Buku_Cuti.IX_N_HRIS_Log_Buku_Cuti_NoRef` | `NONCLUSTERED / 0 / No_Transaksi_Ref,Id_Log DESC / Created_At,Id_Buku_Cuti,Jenis_Mutasi,Jumlah,Kategori_Transaksi / ([no_transaksi_ref] is not null)` | `NONCLUSTERED / 0 / No_Transaksi_Ref,Id_Log DESC / Created_At,Id_Buku_Cuti,Jenis_Mutasi,Jumlah,Kategori_Transaksi / ` |
| `N_HRIS_Log_Buku_Cuti.UX_N_HRIS_Log_Buku_Cuti_Cuti_Key` | `NONCLUSTERED / 1 / Cuti_Key /  / ([cuti_key] is not null)` | `NONCLUSTERED / 1 / Cuti_Key /  / ` |
| `N_HRIS_Pengumpulan_Data_Submission.UX_Pengumpulan_Submission_Aktif` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ([flag_aktif]='y')` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ` |
| `N_HRIS_Workgroup_Member.UX_WorkgroupMember_Active` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ([flag_aktif]='y')` | `NONCLUSTERED / 1 / Kode_Karyawan /  / ` |
| `N_KPI_Target_Koreksi.IX_N_KPI_Target_Koreksi_Det` | `NONCLUSTERED / 0 / Id_Pengajuan_Det,Waktu DESC /  / ` | `NONCLUSTERED / 0 / Id_Pengajuan_Det,Waktu /  / ` |
| `N_LMS_Api_Logs.IX_N_LMS_Api_Logs_Correlation_Id` | `NONCLUSTERED / 0 / Correlation_Id /  / ([correlation_id] is not null)` | `NONCLUSTERED / 0 / Correlation_Id /  / ` |
| `N_SOP_Assignment_Rule_Paket.UX_SOP_Assignment_Rule_Paket_Paket` | `NONCLUSTERED / 1 / Id_SOP_Paket /  / ([flag_aktif]='y')` | `NONCLUSTERED / 1 / Id_SOP_Paket /  / ` |
| `N_SOP_Automation_Run.IX_N_SOP_Run_History` | `NONCLUSTERED / 0 / Created_At DESC,Tipe_Run,Status_Run /  / ` | `NONCLUSTERED / 0 / Created_At,Tipe_Run,Status_Run /  / ` |
| `N_SOP_Automation_Run.IX_SOP_Automation_Run_Induk` | `NONCLUSTERED / 0 / Id_Run_Induk /  / ([id_run_induk] is not null)` | `NONCLUSTERED / 0 / Id_Run_Induk /  / ` |
| `N_SOP_Dokumen.IX_N_SOP_Dokumen_Nonaktif_At` | `NONCLUSTERED / 0 / Nonaktif_At DESC / Nonaktif_By / ([nonaktif_at] is not null)` | `NONCLUSTERED / 0 / Nonaktif_At DESC / Nonaktif_By / ` |
| `N_SOP_Item_Versi.UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup` | `NONCLUSTERED / 1 / Id_SOP_Dokumen,Versi_Ke /  / ([id_sop_dokumen] is not null and [flag_aktif]='y')` | `NONCLUSTERED / 1 / Id_SOP_Dokumen,Versi_Ke /  / ` |
| `N_SOP_Item_Versi.UX_N_SOP_Item_Versi_Satu_Published` | `NONCLUSTERED / 1 / Id_SOP_Dokumen /  / ([id_sop_dokumen] is not null and [statu='published')` | `NONCLUSTERED / 1 / Id_SOP_Dokumen /  / ` |
| `N_SOP_Item_Versi_Pengesah.IX_N_SOP_Item_Versi_Pengesah_Versi` | `NONCLUSTERED / 0 / Id_SOP_Item_Versi,Urutan /  / ([flag_aktif]='y')` | `NONCLUSTERED / 0 / Id_SOP_Item_Versi,Urutan /  / ` |
| `N_SOP_Log.IX_N_SOP_Log_Dokumen` | `NONCLUSTERED / 0 / Id_SOP_Dokumen,Created_At DESC / Aksi,Id_SOP_Item_Versi,Keterangan,Oleh_Id_Users / ([id_sop_dokumen] is not null)` | `NONCLUSTERED / 0 / Id_SOP_Dokumen,Created_At DESC / Aksi,Id_SOP_Item_Versi,Keterangan,Oleh_Id_Users / ` |
| `N_SOP_Log.IX_N_SOP_Log_Penjadwalan` | `NONCLUSTERED / 0 / Id_SOP_Penjadwalan,Created_At DESC /  / ([id_sop_penjadwalan] is not null)` | `NONCLUSTERED / 0 / Id_SOP_Penjadwalan,Created_At DESC /  / ` |
| `N_SOP_Master_Jenis_Dokumen.UX_SOP_Jenis_Kode_Jenis_Dokumen` | `NONCLUSTERED / 1 / Kode_Jenis_Dokumen /  / ([kode_jenis_dokumen] is not null)` | `NONCLUSTERED / 1 / Kode_Jenis_Dokumen /  / ` |
| `N_SOP_Paket_Draft.IX_N_SOP_Paket_Draft_Paket_Status` | `NONCLUSTERED / 0 / Id_SOP_Paket,Status,Updated_At DESC /  / ` | `NONCLUSTERED / 0 / Id_SOP_Paket,Status,Updated_At /  / ` |
| `N_SOP_Paket_Draft.UX_N_SOP_Paket_Draft_Working` | `NONCLUSTERED / 1 / Id_SOP_Paket /  / ([statu='draft')` | `NONCLUSTERED / 1 / Id_SOP_Paket /  / ` |
| `N_SOP_Paket_Draft_Item.IX_N_SOP_Paket_Draft_Item_Dokumen` | `NONCLUSTERED / 0 / Id_SOP_Dokumen /  / ([id_sop_dokumen] is not null)` | `NONCLUSTERED / 0 / Id_SOP_Dokumen /  / ` |
| `N_SOP_Paket_Draft_Item.IX_N_SOP_Paket_Draft_Item_Target_Versi` | `NONCLUSTERED / 0 / Id_SOP_Item_Versi_Target /  / ([id_sop_item_versi_target] is not null)` | `NONCLUSTERED / 0 / Id_SOP_Item_Versi_Target /  / ` |
| `N_SOP_Paket_Draft_Item.UX_N_SOP_Paket_Draft_Item_Source` | `NONCLUSTERED / 1 / Id_SOP_Paket_Draft,Id_SOP_Paket_Item_Asal /  / ([id_sop_paket_item_asal] is not null)` | `NONCLUSTERED / 1 / Id_SOP_Paket_Draft,Id_SOP_Paket_Item_Asal /  / ` |
| `N_SOP_Paket_Draft_Item_Versi.IX_N_SOP_Paket_Draft_Item_Versi_Status` | `NONCLUSTERED / 0 / Id_SOP_Draft_Item,Status,Versi_Ke DESC /  / ` | `NONCLUSTERED / 0 / Id_SOP_Draft_Item,Status,Versi_Ke /  / ` |
| `N_SOP_Paket_Draft_Section.UX_N_SOP_Paket_Draft_Section_Source` | `NONCLUSTERED / 1 / Id_SOP_Paket_Draft,Id_SOP_Section_Asal /  / ([id_sop_section_asal] is not null)` | `NONCLUSTERED / 1 / Id_SOP_Paket_Draft,Id_SOP_Section_Asal /  / ` |
| `N_SOP_Paket_Versi.IX_N_SOP_Paket_Versi_Paket_Tanggal` | `NONCLUSTERED / 0 / Id_SOP_Paket,Tanggal_Publish DESC /  / ` | `NONCLUSTERED / 0 / Id_SOP_Paket,Tanggal_Publish /  / ` |
| `N_SOP_Penjadwalan_Detail.IX_N_SOP_PjdD_Rule` | `NONCLUSTERED / 0 / Id_SOP_Assignment_Rule,Flag_Cabut_Akses,Kode_Karyawan /  / ([id_sop_assignment_rule] is not null)` | `NONCLUSTERED / 0 / Id_SOP_Assignment_Rule,Flag_Cabut_Akses,Kode_Karyawan /  / ` |
| `N_SOP_Penjadwalan_Detail.UX_N_SOP_PjdD_Aktif` | `NONCLUSTERED / 1 / Id_SOP_Penjadwalan,Kode_Karyawan /  / ([flag_aktif]='y' and [flag_cabut_akse='t')` | `NONCLUSTERED / 1 / Id_SOP_Penjadwalan,Kode_Karyawan /  / ` |
| `N_SOP_Progress.IX_N_SOP_Prg_Waktu_Buka` | `NONCLUSTERED / 0 / Waktu_Buka / Durasi_Aktif_Detik,Id_SOP_Paket_Item,Kode_Karyawan / ([waktu_buka] is not null)` | `NONCLUSTERED / 0 / Waktu_Buka / Durasi_Aktif_Detik,Id_SOP_Paket_Item,Kode_Karyawan / ` |
| `Transaksi_Lembur_Det.IX_Transaksi_Lembur_Det_NoTrans_Kode` | `NONCLUSTERED / 0 / No_Transaksi,Kode_Karyawan,Tanggal_Lembur DESC /  / ` | `NONCLUSTERED / 0 / No_Transaksi,Kode_Karyawan,Tanggal_Lembur /  / ` |
| `Whatsapp_Log.UX_WA_Log_Idempotency` | `NONCLUSTERED / 1 / Idempotency_Key /  / ([idempotency_key] is not null)` | `NONCLUSTERED / 1 / Idempotency_Key /  / ` |

## Tugas user

1. Tinjau dan setujui; jalankan di production SETELAH DDL-2026-154-sinkron-prod-tabel-kolom, di luar jam sibuk.
2. Verifikasi: 19 index ada di `sys.indexes` dengan nama di atas.
