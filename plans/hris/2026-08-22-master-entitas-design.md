# Design: Master Entitas Perusahaan + Kepemilikan & Ruang Lingkup Dokumen SOP

Tanggal: 2026-08-22
Status: `APPROVED_FOR_IMPLEMENTATION`
Basis registry: `Web_HRIS` (development), diverifikasi langsung 2026-08-22.
Modul/submodul/fitur: `SYSTEM` / `MasterData` / `Entitas` (master), dikonsumsi `LMS` / `SOP` / `Governance`.

## Masalah

`Kode_Perusahaan` hadir di **141 tabel** pada basis data ini dan **tidak punya satu
pun tabel master**. Satu-satunya nilai yang hidup adalah `'001'`. Artinya entitas
perusahaan sudah lama "ada" sebagai kolom di mana-mana, tapi tidak pernah punya
daftar — tak ada yang bisa menjawab "entitas apa saja yang ada di grup ini".

Bagi dokumen SOP, ketiadaan itu berakibat langsung: tidak ada cara menyatakan
**dokumen ini milik badan hukum mana** dan **berlaku di badan hukum mana**.

Isi entitasnya, dari pengguna:

```text
ENB   PT Evo Nusa Bersaudara
EMI   PT Evo Manufacturing Indonesia
GMN   PT Graha Maju Nusantara
EHG   Evo Holding / Group     <- payung, mencakup keseluruhan
```

## Keputusan yang mengunci desain

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Entitas itu apa | Badan hukum / PT. Lokasi & cabang tetap milik `N_HRIS_Master_Lokasi` yang sudah ada. |
| 2 | Prefix tabel | `N_HRIS_` — 198 tabel sudah memakainya, termasuk keluarga `N_HRIS_Master_*`. Bukan prefix baru, jadi tidak butuh konfirmasi tambahan. |
| 3 | EHG | Payung yang mencakup keseluruhan, dinyatakan lewat `Induk_Entitas_Id`. |
| 4 | Ruang lingkup | **Satu** entitas per dokumen, bukan banyak. |
| 5 | Hubungan dengan Departemen | **Tidak ada.** Divisi/Sub-Divisi TIDAK diatribusikan ke entitas, tidak ada penyaringan bertingkat, tidak ada tabel pemetaan. Dua sumbu yang berdiri sendiri. |
| 6 | Kolom `Kode_Perusahaan` di master entitas | **Dibatalkan.** Lihat "Yang sengaja tidak dikerjakan". |
| 7 | Warna per entitas | Disimpan, bukan dihitung. Lihat "Warna". |
| 8 | Halaman CRUD entitas | Belum. Fase ini hanya seed + pemakaian di Master Dokumen. |

## Koreksi terhadap pekerjaan sebelumnya

`DDL-2026-016` menambahkan `Divisi_Id` (wajib di form) dan `Sub_Divisi_Id`
(opsional). Itu salah baca: **yang dipakai operasional hanya sub-divisi, dengan
label "Departemen"**. Kewajiban Divisi karena itu dicabut.

Biayanya nol: `N_SOP_Dokumen` punya **0 baris** yang mengisi `Divisi_Id` maupun
`Sub_Divisi_Id`. Tidak ada DDL baru yang diperlukan — kolomnya sudah ada dan
nullable; yang berubah hanya aturan validasi dan bentuk isian di layar.

`Divisi_Id` tidak dibuang, melainkan menjadi **turunan**: diisi otomatis dari
Departemen yang dipilih bila induknya tunggal. Dari 32 sub-divisi, **30 punya
induk tunggal**; sub-divisi `15` dan `42` masing-masing bernaung di dua divisi,
dan untuk keduanya `Divisi_Id` dibiarkan NULL — bukan ditebak.

## Bentuk data

### `N_HRIS_Master_Entitas`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `Id_Entitas` | `int IDENTITY` | NO | PK |
| `Kode_Entitas` | `varchar(10)` | NO | `ENB`, `EMI`, `GMN`, `EHG`. Unik, selalu huruf besar. Rujukan tetap. |
| `Nama_Entitas` | `nvarchar(150)` | NO | Nama resmi badan hukum. |
| `Nama_Singkat` | `nvarchar(50)` | YES | Untuk badge sempit; jatuh ke `Kode_Entitas` bila kosong. |
| `Warna` | `varchar(20)` | YES | Hex `#RRGGBB`. Kosong → frontend jatuh ke `kategoriTheme(Kode_Entitas)`. |
| `Induk_Entitas_Id` | `int` | YES | FK ke tabel ini sendiri. ENB/EMI/GMN → EHG; EHG → NULL. |
| `Urutan` | `int` | NO, default 0 | |
| `Flag_Aktif` | `char(1)` | NO, default `Y` | |
| `Created_At` | `datetime2(0)` | NO, default `SYSDATETIME()` | |
| `Created_By` | `int` | YES | `KPI_Users.Id_Users`, mengikuti preseden master SOP. |
| `Updated_At` | `datetime2(0)` | YES | |
| `Updated_By` | `int` | YES | |

Indeks:
- `UX_HRIS_Entitas_Kode` UNIQUE (`Kode_Entitas`)
- `IX_HRIS_Entitas_Urut` (`Flag_Aktif`, `Urutan`) INCLUDE (`Kode_Entitas`, `Nama_Entitas`)

FK `FK_HRIS_Entitas_Induk` menunjuk tabel ini sendiri, tanpa cascade.

**Kenapa `Induk_Entitas_Id` dan bukan flag.** "EHG mencakup keseluruhan" adalah
relasi induk-anak, bukan sifat. Menyimpannya sebagai kolom induk membuat
pertanyaan "entitas apa saja yang tercakup EHG" **dihitung dari data**; menyimpannya
sebagai flag `Is_Holding` memaksa daftar anaknya dihafal di kode, dan tiap PT baru
menuntut perubahan kode. Ini juga yang membuat masternya berguna bagi modul lain.

**Warna disimpan, bukan dihitung.** `constants.js` sudah punya `kategoriTheme()`
yang menghash nama menjadi indeks palet. Untuk kategori itu benar — warnanya
arbitrer. Untuk entitas salah: warna PT adalah fakta merek yang ditentukan di luar
sistem, dan hash tak akan pernah menebaknya. Kolomnya nullable dan **dipakai sejak
hari pertama** pada badge entitas di Master Dokumen; kolom yang disimpan tapi tak
pernah dirender perlahan berisi nilai salah tanpa ada yang tahu.

### Kolom baru pada `N_SOP_Dokumen`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `Entitas_Pemilik_Id` | `int` | YES | Siapa yang bertanggung jawab. |
| `Entitas_Lingkup_Id` | `int` | YES | Berlaku di mana. |

Keduanya FK ke `N_HRIS_Master_Entitas`, nullable karena 41 dokumen lama lahir
sebelum kolomnya ada. Indeks `IX_SOP_Dokumen_Entitas` (`Flag_Aktif`,
`Entitas_Pemilik_Id`) dan `IX_SOP_Dokumen_Lingkup` (`Flag_Aktif`, `Entitas_Lingkup_Id`).

Dua kolom terpisah karena keduanya memang bisa berbeda: dokumen yang **dimiliki**
ENB bisa **berlaku** se-grup (EHG).

## Aturan validasi

Pembagian sama dengan fitur Jenis Dokumen, dan karena alasan yang sama:

**Di controller (pintu manusia)** — kewajiban mengisi:
- `Entitas_Pemilik_Id` wajib
- `Entitas_Lingkup_Id` wajib
- `Sub_Divisi_Id` (Departemen) wajib — **menggantikan** kewajiban `Divisi_Id`
- `Divisi_Id` tidak lagi diterima dari klien; ia turunan yang dihitung server

**Di service (semantik)**, hanya saat nilainya ada:
- entitas harus ada dan `Flag_Aktif = 'Y'`
- Departemen harus ada di `View_Divisi_Sub_Divisi`
- `Divisi_Id` diisi dari Departemen bila induknya tunggal, NULL bila bercabang

Kewajiban tetap **tidak** di service: `SopDokumenService::buat()` juga melayani
backfill dan penyalinan paket yang tak punya jawaban untuk field-field ini.

## Permukaan aplikasi

### Penempatan berkas

```text
Service   : app/Services/SYSTEM/MasterData/Entitas/EntitasService.php
Controller: app/Http/Controllers/SYSTEM/MasterData/Entitas/EntitasController.php
```

Service-nya milik `SYSTEM` karena masternya general — modul lain harus bisa
memakainya tanpa bergantung pada SOP. Yang tinggal di SOP hanyalah **endpoint
konsumennya**, karena gerbang aksesnya memang milik layar SOP:

```text
GET /api/v1/sop/admin/master-data/entitas
    auth + check.jabatan:menuSopDokumenPage,menuSopBuilderPage
```

Saat modul lain membutuhkan entitas, ia menambah endpoint bergerbang sendiri yang
memanggil service yang sama — bukan menyalin logikanya.

### Master Dokumen — satu blok, tiga kendali

```text
Entitas Pemilik *        Departemen Pemilik *      Berlaku Di *
[ ENB - PT Evo Nusa  v]  [ WAREHOUSE          v]   [ ENB - PT Evo Nusa   v]
                                                    ^ mengikuti pemilik sampai diubah
```

**Berlaku Di terisi otomatis mengikuti Entitas Pemilik.** Mayoritas dokumen berlaku
di tempat ia dimiliki, jadi field ketiga berubah dari keputusan menjadi konfirmasi.

EHG dirender `EHG — Evo Holding / Group (seluruh entitas)` **di titik memilih**,
supaya arti payungnya terbaca saat dibutuhkan, bukan di dokumentasi yang tak dibuka.

Badge entitas memakai `Warna` pada daftar dan kartu detail; bila kosong, jatuh ke
`kategoriTheme(Kode_Entitas)`.

Daftar juga mendapat filter Entitas dan filter **"Belum ada entitas"**, mengikuti
pola filter "Belum diklasifikasi" yang sudah ada.

### Builder

Blok "Dokumen Baru" mendapat tiga kendali yang sama. Builder adalah pintu kedua
tempat dokumen lahir; membiarkannya longgar menjadikannya jalan pintas yang
melahirkan dokumen tanpa entitas.

## Perubahan database

| Change ID | Jenis | Isi |
|---|---|---|
| `DDL-2026-019-hris-master-entitas` | DDL | 1 tabel baru, 2 kolom di `N_SOP_Dokumen`, 4 indeks, 3 FK |
| `DML-2026-020-seed-hris-entitas` | DML | 4 entitas + pendaftaran ke Database Change Dashboard |

Semua idempoten. Tidak ada DROP, tidak ada data lama yang ditimpa.

## Pengujian

| Berkas | Membuktikan |
|---|---|
| `EntitasSkemaTest` | Tabel + kolom + seed benar-benar ada — satu-satunya bukti DDL manual sudah jalan |
| `EntitasMasterTest` | Opsi aktif, resolusi anak EHG dari `Induk_Entitas_Id`, kode unik |
| `SopDokumenEntitasTest` | Entitas tak dikenal/nonaktif ditolak; Departemen wajib; `Divisi_Id` terisi otomatis untuk induk tunggal dan NULL untuk yang bercabang; jalur backfill tetap boleh kosong |
| `SopMasterDokumenPageTest` (ubah) | Kewajiban berpindah dari Divisi ke Departemen + entitas |
| `SopBuilderItemDokumenTest` (ubah) | Pintu Builder menuntut hal yang sama |
| `entitasApi.test.js` | Kontrak endpoint opsi |

## Yang sengaja TIDAK dikerjakan

- **Kolom `Kode_Perusahaan` di master entitas.** Sempat diusulkan sebagai jembatan
  ke kode yatim di 141 tabel, lalu dibatalkan: `HRIS_Divisi` dan `HRIS_Sub_Divisi`
  sama-sama bernilai `'001'`, jadi memetakan `'001'` ke satu entitas akan diam-diam
  berarti "semua divisi milik entitas itu" — atribusi yang justru ditolak.
- **Halaman CRUD entitas.** Fase ini seed + pakai. Layar pengelolaannya menyusul
  bila daftar entitas mulai berubah.
- **Ruang lingkup banyak entitas.** Satu nilai, sesuai keputusan. Konsekuensinya
  disadari: dokumen yang berlaku di ENB dan EMI tapi bukan GMN tak bisa dinyatakan
  dalam satu baris.
- **Atribusi entitas pada divisi/departemen.** Ditolak eksplisit.
- **Membuang kolom `Divisi_Id`.** Ia tetap ada sebagai turunan.
