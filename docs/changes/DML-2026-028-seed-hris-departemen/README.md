# DML-2026-028 — Seed 52 kode departemen dan binding ke sub-divisi

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-028-seed-hris-departemen` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-028-seed-hris-departemen/data-change.sql` |

## Hasil eksekusi

**52 kode dimuat. 17 terikat ke sub-divisi, 35 sengaja dibiarkan lepas.**

## Empat typo diperbaiki

Daftar yang diterima memuat empat nama yang jelas keliru ketik. Diperbaiki di
seed, bukan dibiarkan lalu ditambal belakangan — nama master ini muncul di
dropdown dan di dokumen resmi.

| Kode | Diterima | Disimpan |
|---|---|---|
| `IT` | Informartion Technology | Information Technology |
| `TPM` | Talent & Performace Management | Talent & Performance Management |
| `SHE` | Safety, Health & Enviroment | Safety, Health & Environment |
| `FSCI` | Food Safety & Continous Improvement | Food Safety & Continuous Improvement |

## Binding lewat JOIN nama literal, bukan id keras

```sql
UPDATE d SET d.Sub_Divisi_Id = s.ID_Sub_Divisi
FROM N_HRIS_Master_Departemen d
JOIN HRIS_Sub_Divisi s ON UPPER(LTRIM(RTRIM(s.Keterangan))) = UPPER(LTRIM(RTRIM(d.Nama_Departemen)))
WHERE d.Sub_Divisi_Id IS NULL;
```

Id sub-divisi berbeda antar environment. Menuliskannya sebagai angka di skrip
seed berarti seed yang sama memasang binding yang **salah** saat dijalankan di
UAT atau produksi — dan salahnya diam, karena angkanya tetap menunjuk baris yang
ada. JOIN nama menghasilkan nol binding bila namanya tak cocok, dan nol binding
adalah keadaan yang sudah didukung.

Pencocokannya **persis**, bukan mirip. Pencocokan mirip sempat diukur:
"Key Account" 70% mirip "ACCOUNTING", "Learning and Development" 76% mirip
"MARKETING DEVELOPMENT". Binding yang salah akan tercetak di nomor dokumen.

## `PPIC` sengaja dibiarkan lepas

`HRIS_Sub_Divisi` memuat **dua** baris yang sama-sama masuk akal:
id 38 `PRODUCTION PLANNING INVENTORY CONTROL` dan id 41 `PPIC`. Tidak ada di
dalam data yang menyatakan mana yang dimaksud, jadi keputusannya diserahkan ke
admin lewat halaman Master Departemen — bukan diputuskan diam-diam oleh seed.

## 35 kode tanpa binding bukan pekerjaan yang tertinggal

Tidak semua kode perusahaan punya baris sendiri di struktur HRIS yang tersimpan;
lihat `docs/changes/DDL-2026-027-*` untuk alasannya. Dokumen yang memakainya
tetap sah — turunannya kosong, dan itu memang jawabannya.

## Isi lengkap

| Kode | Nama | Binding |
|---|---|---|
| `PPIC` | Production Planning & Inventory Control | — |
| `IA` | Internal Audit | terikat |
| `FIN` | Finance | terikat |
| `ACC` | Accounting | terikat |
| `TAX` | Tax | terikat |
| `PROC` | Procurement | terikat |
| `IT` | Information Technology | — |
| `LGL` | Legal | terikat |
| `CQM` | Compliance & Quality Management | — |
| `SAL` | Sales | — |
| `DIS` | Distribution | — |
| `MT` | Modern Trade | terikat |
| `GT` | General Trade | terikat |
| `EXT` | Export Trade | terikat |
| `COPL` | Corporate Planning | — |
| `MDEV` | Marketing Development | terikat |
| `MCOM` | Marketing Communication | terikat |
| `TDEV` | Technical Development | — |
| `FOR` | Formulator | — |
| `RAD` | Research and Development | — |
| `QAL` | Lab & Quality Assurance | — |
| `KEA` | Key Account | — |
| `MTP` | Modern Trade Sales Promotion | — |
| `ECO` | E-Commerce Operation | — |
| `MTA` | Modern Trade Administration | — |
| `HC` | Human Capital | terikat |
| `HCS` | Human Capital Services | — |
| `TPM` | Talent & Performance Management | — |
| `TLD` | Training & Learning Development | — |
| `GA` | General Affairs | terikat |
| `IER` | Industrial & Employee Relation | terikat |
| `PROD` | Production | terikat |
| `WFP` | Wet Food Production | terikat |
| `DFP` | Dry Food Production | — |
| `WH` | Warehouse | — |
| `RWH` | Raw Material Warehouse | — |
| `PFWH` | Packaging & Finished Good | — |
| `ENM` | Engineering & Maintenance | terikat |
| `SHE` | Safety, Health & Environment | — |
| `CI` | Continuous Improvement | — |
| `LOG` | Logistik | — |
| `GWH` | General Warehouse | — |
| `DC` | Distribution Center | — |
| `BP` | Business Process | — |
| `LND` | Learning and Development | — |
| `CC` | Customer Care | — |
| `MKT` | Marketing | — |
| `CSWH` | Cold Storage Warehouse | — |
| `FSCI` | Food Safety & Continuous Improvement | — |
| `WHL` | Warehouse & Logistic | — |
| `FM` | Facility Management | — |
| `EXM` | Exim | — |

## Rollback

Tidak ada `drop.sql`. Menghapus baris master yang sudah ditunjuk dokumen akan
memutus foreign key `FK_SOP_Dokumen_Departemen`; yang tersedia untuk menarik
sebuah kode dari peredaran adalah menonaktifkannya (`Flag_Aktif = 'T'`) lewat
halaman Master Departemen, dan itu ditolak selama masih ada dokumen aktif yang
memakainya.
