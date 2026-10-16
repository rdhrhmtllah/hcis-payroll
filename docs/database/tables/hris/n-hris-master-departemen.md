# N_HRIS_Master_Departemen

Master kode departemen — acuan kepemilikan dokumen dan segmen nomor dokumen.

| Bidang | Nilai |
|---|---|
| Modul | SYSTEM / MasterData / Departemen — master lintas modul |
| Change ID | `DDL-2026-027-hris-master-departemen` |
| Forward SQL | `database/ddl/DDL-2026-027-hris-master-departemen/forward.sql` |
| Seed | `database/dml/DML-2026-028-seed-hris-departemen/data-change.sql` |
| Menu | `database/dml/DML-2026-029-register-system-departemen-menu/data-change.sql` |

## Tujuan

Menjawab "departemen apa saja yang bisa memiliki dokumen, dan apa kodenya" —
pertanyaan yang `HRIS_Sub_Divisi` tidak bisa jawab karena ia menyimpan struktur
organisasi, bukan daftar kode.

Kodenya adalah **segmen ketiga** nomor dokumen SOP:

```text
Jenis / Entitas / Departemen / RuangLingkup / NoAPQC / NoDokumen / Revisi
                  ▲
                  Kode_Departemen
```

## Kenapa master sendiri, bukan kolom di `HRIS_Sub_Divisi`

Daftar kode perusahaan lebih rinci daripada struktur yang tersimpan: **52 kode
versus 32 sub-divisi**. Sebagian kode adalah tingkat di bawahnya yang memang tak
punya baris sendiri — `WFP`/`DFP` di bawah Production, `RWH`/`PFWH`/`CSWH`/`GWH`
di bawah Warehouse, `MTP`/`MTA` di bawah Modern Trade. Menempelkannya ke tabel
legacy akan membuat 35 dari 52 kode mustahil dipakai sampai skema legacy diubah.

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_Departemen` | `int IDENTITY` | NO | — | PK |
| `Kode_Departemen` | `varchar(10)` | NO | — | Unik (`UX_HRIS_Departemen_Kode`), huruf besar. **Tercetak di nomor dokumen** — tak pernah diubah setelah dibuat. |
| `Nama_Departemen` | `nvarchar(150)` | NO | — | Nama tampil. Boleh disunting. |
| `Sub_Divisi_Id` | `bigint` | YES | — | Binding **opsional** ke `HRIS_Sub_Divisi.ID_Sub_Divisi`. Kosong pada 35 dari 52 baris seed. |
| `Urutan` | `int` | NO | 0 | Urutan tampil. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | `Y`/`T`. Nonaktif ditolak dipakai dokumen baru. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `KPI_Users.Id_Users` |
| `Updated_At` | `datetime2(0)` | YES | — | |
| `Updated_By` | `int` | YES | — | |

## Indeks

| Nama | Kolom | Keterangan |
|---|---|---|
| `UX_HRIS_Departemen_Kode` | `Kode_Departemen` | Unik. Penjaga aplikasi bisa dilewati seed dan skrip; indeks tidak. Satu kode yang menunjuk dua departemen berarti satu nomor dokumen menunjuk dua penanggung jawab. |
| `IX_HRIS_Departemen_Urut` | `Urutan, Kode_Departemen` | Urutan dropdown. |

## Binding tidak pernah ditebak

`Sub_Divisi_Id` hanya dipakai untuk menurunkan `Sub_Divisi_Id`/`Divisi_Id` pada
`N_SOP_Dokumen`, supaya jalur pelaporan yang menyambung karyawan lewat
`ID_Divisi_Sub_Divisi` (`SopMonitoringPeopleService`,
`SopPenjadwalanTargetService`) tetap punya sesuatu untuk di-join.

Seed hanya memasangkan nama yang cocok **persis** (17 dari 52). Pencocokan mirip
pernah diukur dan hasilnya berbahaya: "Key Account" 70% mirip "ACCOUNTING",
"Learning and Development" 76% mirip "MARKETING DEVELOPMENT". `PPIC` dibiarkan
lepas karena `HRIS_Sub_Divisi` memuat dua baris yang sama-sama cocok (id 38
`PRODUCTION PLANNING INVENTORY CONTROL` dan id 41 `PPIC`); admin yang memutuskan.

## Hubungan dengan `N_SOP_Dokumen`

| Kolom dokumen | Peran |
|---|---|
| `Departemen_Id` | **Acuan kepemilikan.** FK `FK_SOP_Dokumen_Departemen`. |
| `Sub_Divisi_Id` | Turunan dari binding. Dihitung server, ditolak dari klien. |
| `Divisi_Id` | Turunan dari sub-divisi (`SopUnitKerja::divisiTunggal`). Ditolak dari klien. |

Keduanya dihitung **ulang** setiap kali departemennya berganti — membiarkan
nilai lama bertahan akan menyimpan pasangan yang tak pernah ada di struktur.

`Departemen_Id` juga salah satu dari lima kolom pembentuk deret nomor dokumen
(`SopDokumenService::KOLOM_GRUP_NOMOR`).

## Siapa yang boleh mengelola

Halaman `/system/master-data/departemen`, gerbang **`system.admin`**
(`KPI_Users.Is_System_Admin = 'Y'`). Modul lain hanya **membaca**, lewat
`GET /api/v1/sop/admin/master-data/departemen` yang bergerbang `check.jabatan`
sendiri.

## Berkas terkait

| Lapis | Berkas |
|---|---|
| Service | `app/Services/SYSTEM/MasterData/Departemen/DepartemenService.php` |
| Controller | `app/Http/Controllers/SYSTEM/MasterData/Departemen/DepartemenController.php` |
| Route | `routes/SYSTEM/MasterData/Departemen.php` |
| Halaman | `resources/js/Pages/SYSTEM/MasterData/Departemen/DepartemenPage.vue` |
| Tes | `tests/Feature/SYSTEM/MasterData/DepartemenTest.php`, `tests/Feature/LMS/SOP/SopDokumenDepartemenTest.php` |
