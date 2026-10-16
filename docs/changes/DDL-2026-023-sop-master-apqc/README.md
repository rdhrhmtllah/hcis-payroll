# DDL-2026-023 — Master APQC dan pemetaan proses dokumen SOP

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-023-sop-master-apqc` |
| Jenis | DDL |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-023-sop-master-apqc/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-023-sop-master-apqc/drop.sql` |
| Dokumentasi tabel | `docs/database/tables/sop/n-sop-master-apqc.md` |

## Kenapa

Identitas dokumen SOP sudah punya tiga sumbu, dan tak satu pun menjawab
"dokumen ini memetakan proses bisnis yang mana":

```text
N_SOP_Dokumen.Tipe          -> berkasnya apa (DOKUMEN/FORM/VIDEO/KUIS)
N_SOP_Master_Jenis_Dokumen  -> tingkat hirarki mutu (Kebijakan…Formulir)
N_SOP_Kategori              -> topiknya apa, dan itu melekat di PAKET
```

APQC Process Classification Framework adalah sumbu keempat, dan sengaja berdiri
sendiri.

## Objek yang terpengaruh

```text
N_SOP_Master_APQC              (tabel baru, 10 kolom)
N_SOP_Master_APQC_Tipe_Item    (tabel baru, 6 kolom)
N_SOP_Dokumen.APQC_Id          (kolom baru, int NULL)

UX_SOP_APQC_Kode, IX_SOP_APQC_Urut,
UX_SOP_APQC_Tipe_Pasangan, IX_SOP_APQC_Tipe_Cari, IX_SOP_Dokumen_APQC
FK_SOP_APQC_Tipe_APQC, FK_SOP_Dokumen_APQC
```

## Keputusan yang perlu dicatat

**Pemetaan APQC↔tipe item hidup sebagai BARIS, bukan enum di kode**, meniru
`N_SOP_Master_Jenis_Dokumen_Tipe_Item`. Konsekuensinya yang paling berguna:
**kewajiban mengisi APQC pada dokumen DITURUNKAN dari pemetaan itu**. Bila admin
mencabut seluruh tipe dari setiap proses, APQC berhenti diminta — tanpa satu
baris kode pun berubah, tanpa rilis. Itu terbukti di layar: mengganti tipe
dokumen menjadi `KUIS` membuat field APQC hilang sendiri.

**Kode disimpan sebagai teks `varchar(10)`, bukan angka.** Nol di depan adalah
bagian dari kodenya ('01', bukan 1), dan sub-level kelak ditulis '01.1' pada
kolom yang sama.

**Datar, tanpa kolom induk.** APQC PCF memang berjenjang, tetapi daftar yang
dipakai sekarang hanya 13 kategori tingkat pertama. Menambah hirarki nanti
adalah satu `ALTER TABLE` nullable yang tak menyentuh data lama; menyiapkannya
sekarang hanya menambah kolom kosong dan pilihan yang membingungkan di form.

**`APQC_Id` nullable.** Dokumen lama lahir sebelum kolomnya ada. Kewajiban
ditegakkan di pintu manusia, bukan di skema.

## Hasil eksekusi development

Dijalankan 2026-08-22 terhadap `Web_HRIS`, 9 batch OK. Dua tabel, satu kolom,
lima indeks, dan dua foreign key terbentuk; baris dokumen tidak tersentuh.

## Pengujian

`tests/Feature/LMS/SOP/SopApqcTest.php` — 11 tes, termasuk gerbang skema dan
pembuktian bahwa mencabut seluruh tipe membuat APQC berhenti ditawarkan.

## Sisa pekerjaan pengguna

1. Review forward DDL.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
