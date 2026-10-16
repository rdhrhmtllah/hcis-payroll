# DML-2026-063 — Rapikan lompatan nomor versi peninggalan bug penomoran

| | |
|---|---|
| Change ID | `DML-2026-063-rapikan-lompatan-nomor-versi` |
| Jenis | DML (tanpa DDL) |
| Target | `Web_HRIS` (development) |
| Status | **DEV DONE** — dijalankan 30 Agu 2026. SQL Tracker & produksi `PENDING`. |
| Artefak | `database/dml/DML-2026-063-rapikan-lompatan-nomor-versi/data-change.sql` |
| Audit | `docs/superpowers/specs/2026-08-30-audit-kekokohan-versioning-sop.md` |

## Kenapa

Sampai 30 Agu 2026 nomor versi berikutnya dihitung di **lima tempat**, dan empat
di antaranya tak menyaring versi yang sudah dibatalkan:

| Tempat | Keadaan |
|---|---|
| `SopDokumenService::nomorVersiBerikut` | benar |
| `SopAdminVersiWizardController` | tanpa saringan |
| `SopItemVersionService::create` | tanpa saringan |
| `SopPaketDraftService` (publish paket) | tanpa saringan |
| `SopDokumenService::nomorVersiTerpakai` | tanpa saringan |

Yang paling menggigit adalah muatan wizard: ia mengusulkan nomor tanpa saringan,
layar mengirimkannya balik sebagai nomor **MANUAL**, dan penomoran otomatis yang
sudah benar karena itu tak pernah terpakai. Versi baru pada dokumen yang v01-nya
dibatalkan lahir sebagai **v02**, dan nomor resmi melompat dari `…/00` ke `…/02`
— menyebut "Revisi 01" yang tak pernah diterbitkan kepada siapa pun.

Kelima pintu sudah disatukan ke `SopNomorVersi`. Change ini membereskan baris
yang terlanjur lahir **sebelum** penyatuan itu, supaya sisa bugnya tak terbaca
sebagai perilaku yang disengaja oleh siapa pun yang membacanya nanti.

## Ruang lingkup — dicari lewat POLA, bukan id yang dipatok

Versi yang memenuhi **semuanya**:

- `Flag_Aktif='Y'` dan `Status='PUBLISHED'`
- ada nomor **lebih rendah** yang bebas karena versi pemiliknya dibatalkan, dan
  nomor itu tak dipakai versi hidup mana pun
- **nol rujukan hilir**: tak ada progres karyawan, tak ditunjuk paket item, tak
  ada penjadwalan integrasi, coverage rollup, maupun paket versi item

Syarat terakhir adalah pengamannya, dan ia **mutlak**. Versi yang sudah dibaca
seseorang tidak boleh dinomori ulang: nomor revisi yang sudah beredar adalah
bagian dari catatan kepatuhan, dan mengubahnya membuat bukti bacaan menunjuk
revisi yang namanya sudah lain.

## Hasil (dev, 30 Agu 2026)

| Tabel | Operasi | Terduga | Hasil |
|---|---|---|---|
| `N_SOP_Item_Versi` | UPDATE | 1 (versi `12417`, dok `17054`) | 1 ✔ |

```
SEBELUM   versi hidup [0, 2]   Nomor_SOP_Resmi = SK/HOL/IA/EHG/02/02/02
SESUDAH   versi hidup [0, 1]   Nomor_SOP_Resmi = SK/HOL/IA/EHG/02/02/01
```

```
id=12139  v00  OBSOLETE   flag=Y
id=12140  v01  CANCEL     flag=T   ← dibatalkan, nomornya dilepas
id=12417  v01  PUBLISHED  flag=Y   ← kini memakai nomor itu
usulan berikutnya = v02
```

## Langkah lanjutan yang TIDAK bisa dilakukan SQL

`Nomor_SOP_Resmi` dirakit ulang lewat
`SopDokumenService::segarkanNomorResmi()`, **bukan** ditulis tangan sebagai SQL.
Rumus tujuh segmennya tinggal di `SopNomorDokumen`; menuliskannya lagi di skrip
akan menjadi implementasi kedua — persis yang rancangannya ada untuk dihindari.
Metode itu juga menyebarkan nomornya ke item perujuk, yang SQL polos tak lakukan.

Dijalankan sesudah DML ini:

```
segarkanNomorResmi(17054, 622) -> 'SK/HOL/IA/EHG/02/02/01'
```

## Verifikasi sesudahnya

```
A1 Status != Status_Teknis          : 0
B1 pointer -> bukan PUBLISHED       : 0
B4 pointer -> baris tak ada         : 0
B5 dokumen >1 PUBLISHED             : 0
C1 nomor kembar antar versi HIDUP   : 0
nomor resmi vs revisi berlaku       : 31 dicek, 0 tak cocok
lompatan nomor tersisa              : 0
```

## Sisa pekerjaan user

1. Catat ke SQL Tracker, isi `SQL_Tracker_Reference`, ubah `Status_SQL_Tracker`.
2. **Produksi perlu ditinjau terpisah, jangan diborong.** Di sana lompatan yang
   sama bisa menyentuh versi yang **sudah dibaca karyawan** — skrip ini sengaja
   menolak menyentuhnya. Kasus seperti itu keputusan tata kelola (revisinya
   ditarik, atau dibiarkan dengan catatan), bukan pembersihan data.
3. Perbarui `Status_Production` dan `Status_Verification`.
