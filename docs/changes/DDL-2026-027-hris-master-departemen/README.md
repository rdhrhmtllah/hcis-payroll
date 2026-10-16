# DDL-2026-027 — Master kode departemen dan kepemilikan dokumen

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-027-hris-master-departemen` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-027-hris-master-departemen/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-027-hris-master-departemen/drop.sql` |

## Isi

```text
N_HRIS_Master_Departemen
    Id_Departemen     int IDENTITY  PK
    Kode_Departemen   varchar(10)   NOT NULL   -- UX_HRIS_Departemen_Kode
    Nama_Departemen   nvarchar(150) NOT NULL
    Sub_Divisi_Id     bigint        NULL       -- binding opsional ke HRIS_Sub_Divisi
    Urutan            int           NOT NULL DEFAULT 0
    Flag_Aktif        char(1)       NOT NULL DEFAULT 'Y'
    Created_At/By, Updated_At/By

N_SOP_Dokumen.Departemen_Id  int NULL   -- FK_SOP_Dokumen_Departemen, IX_SOP_Dokumen_Departemen
```

## Kenapa master sendiri, bukan kolom di `HRIS_Sub_Divisi`

Daftar kode perusahaan lebih rinci daripada struktur yang tersimpan: **52 kode
versus 32 sub-divisi**. Sebagian kode adalah tingkat di bawahnya yang memang tak
punya baris sendiri — `WFP`/`DFP` di bawah Production, `RWH`/`PFWH`/`CSWH`/`GWH`
di bawah Warehouse, `MTP`/`MTA` di bawah Modern Trade.

Menempelkan kode ke `HRIS_Sub_Divisi` akan membuat **35 dari 52 kode mustahil
dipakai** sampai seseorang menambah baris ke tabel legacy. Mengubah skema legacy
adalah tugas terpisah dengan review sendiri, dan bukan prasyarat yang pantas
dipasang di depan penomoran dokumen.

## Binding ke sub-divisi opsional, dan itu bukan kelalaian

`Sub_Divisi_Id` hanya dipakai untuk **menurunkan** `Sub_Divisi_Id`/`Divisi_Id`
pada dokumen, supaya jalur pelaporan yang menyambung karyawan lewat
`ID_Divisi_Sub_Divisi` — `SopMonitoringPeopleService` dan
`SopPenjadwalanTargetService` — tetap punya sesuatu untuk di-join.

Departemen tanpa binding meninggalkan turunannya **kosong**, bukan tebakan.
Pencocokan mirip pernah diukur dan hasilnya berbahaya: "Key Account" 70% mirip
"ACCOUNTING", "Learning and Development" 76% mirip "MARKETING DEVELOPMENT".
Pemetaan yang salah akan **tercetak di nomor dokumen**, dan nomor yang sudah
beredar tak ikut berubah saat kesalahannya ditemukan.

## Kolom turunan pada dokumen

Sejak perubahan ini `N_SOP_Dokumen.Departemen_Id` adalah **acuan kepemilikan**;
`Sub_Divisi_Id` dan `Divisi_Id` menjadi cerminannya. Keduanya dihitung ulang
setiap kali departemennya berganti — membiarkan nilai lama bertahan akan
menyimpan pasangan divisi-lama/sub-baru yang tak pernah ada di struktur, dan
penjaga `pasanganAda()` justru akan menolak penyuntingan yang sebenarnya sah.

Klien **tidak boleh** mengirim `Sub_Divisi_Id` maupun `Divisi_Id`; keduanya
ditolak di pintu dan dihitung server (`SopDokumenService::turunkanUnitDariDepartemen`).

## Lingkup deret nomor dokumen ikut berpindah

`SopDokumenService::KOLOM_GRUP_NOMOR` berpindah dari `Sub_Divisi_Id` ke
`Departemen_Id`. Tanpa itu, arti "kombinasi yang sama" berubah diam-diam begitu
kepemilikan pindah acuan: dua departemen berbeda yang sama-sama tak punya
binding akan berbagi satu deret hanya karena turunannya sama-sama `NULL`.

## Kolom nullable, dan tetap nullable

Empat puluh dua dokumen lahir sebelum kolom ini ada. `NOT NULL` akan memaksa
backfill massal tanpa seorang pun memutuskan siapa pemiliknya. Kewajiban
mengisi ditegakkan di **pintu manusia** (`SopAdminDokumenController::validasi`
dan `SopAdminBuilderController`), bukan di skema — itulah jalur backfill
terpandu.

## Eksekusi

Dijalankan di `Web_HRIS` (development): **6 batch OK**. Tabel, kolom, dua indeks,
dan satu foreign key terbentuk. Baris dokumen yang ada tidak tersentuh.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Ia ada untuk operator, dan
menjalankannya akan menghapus kolom kepemilikan beserta seluruh isinya.
