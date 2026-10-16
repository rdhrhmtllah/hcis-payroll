# Master Kode Departemen — desain

Tanggal: 2026-08-22
Change ID: `DDL-2026-027`, `DML-2026-028`, `DML-2026-029`
Status: terlaksana di `Web_HRIS` (development)

## Masalah

Nomor dokumen SOP bukan satu angka, melainkan gabungan:

```text
Jenis / Entitas / Departemen / RuangLingkup / NoAPQC / NoDokumen / Revisi
```

Segmen ketiga memakai **kode departemen**, bukan namanya. Perusahaan sudah
punya daftar 52 kode yang dipakai sehari-hari, tetapi tak ada satu pun tempat di
basis data yang menyimpannya — yang ada hanya `HRIS_Sub_Divisi` (32 baris) yang
menyimpan struktur organisasi, bukan kode.

## Keputusan: master sendiri, binding opsional

Kode disimpan di tabel baru `N_HRIS_Master_Departemen`, dengan kolom
`Sub_Divisi_Id` **nullable** yang mengikat sebuah kode ke sub-divisi HRIS bila
memang ada pasangannya.

Dua alternatif yang ditolak:

| Alternatif | Kenapa ditolak |
|---|---|
| Kolom `Kode` di `HRIS_Sub_Divisi` | 35 dari 52 kode tak punya baris sendiri (`WFP`/`DFP` di bawah Production, `RWH`/`PFWH`/`CSWH`/`GWH` di bawah Warehouse, `MTP`/`MTA` di bawah Modern Trade). Mereka jadi mustahil dipakai sampai skema legacy diubah — tugas terpisah dengan review sendiri. |
| Master tanpa binding sama sekali | Jalur pelaporan menyambung karyawan ke dokumen lewat `ID_Divisi_Sub_Divisi` (`SopMonitoringPeopleService`, `SopPenjadwalanTargetService`). Tanpa jembatan itu, kepemilikan dokumen berhenti bisa dihubungkan ke orang. |

## Binding tidak pernah ditebak

Seed hanya memasangkan nama yang cocok **persis**: 17 dari 52. Pencocokan mirip
diukur lebih dulu dan hasilnya menutup opsi itu:

| Kode | Nama | Kandidat terdekat | Kemiripan |
|---|---|---|---|
| `KEA` | Key Account | ACCOUNTING | 70% |
| `TLD` | Learning and Development | MARKETING DEVELOPMENT | 76% |

Binding yang salah akan **tercetak di nomor dokumen**, dan nomor yang sudah
beredar tidak ikut berubah saat kesalahannya ditemukan.

`PPIC` sengaja dibiarkan lepas: `HRIS_Sub_Divisi` memuat dua baris yang
sama-sama cocok (id 38 `PRODUCTION PLANNING INVENTORY CONTROL`, id 41 `PPIC`).
Keputusannya milik admin, lewat halaman Master Departemen.

## Dampak ke dokumen

`N_SOP_Dokumen.Departemen_Id` menjadi acuan kepemilikan. `Sub_Divisi_Id` dan
`Divisi_Id` menjadi **turunan** — dihitung ulang setiap kali departemennya
berganti, dan ditolak bila dikirim klien.

**Lingkup deret nomor ikut berpindah** dari `Sub_Divisi_Id` ke `Departemen_Id`.
Ini bagian yang paling mudah terlewat: kalau tidak dipindahkan, arti "kombinasi
yang sama" berubah diam-diam begitu kepemilikan pindah acuan — dua departemen
berbeda yang sama-sama tak punya binding akan berbagi satu deret hanya karena
turunannya sama-sama `NULL`, dan nomor mereka saling menggeser.

## Penempatan halaman

Pengelolaannya di `SYSTEM` dengan gerbang `system.admin`, sejajar Master
Entitas — master ini dipakai lintas modul, dan perubahannya berdampak ke seluruh
dokumen yang menunjuknya.

Modul lain hanya **membaca**, lewat endpoint bergerbang sendiri
(`GET /api/v1/sop/admin/master-data/departemen`, gerbang `check.jabatan`).
Menggabungkan keduanya di satu gerbang akan membuat admin SOP tak bisa memilih
departemen sama sekali.

## Kode terkunci setelah dibuat

`update()` sengaja tidak menulis `Kode_Departemen`, meski controller tetap
memvalidasinya supaya keunikannya diperiksa. Kode sudah tercetak sebagai segmen
nomor dokumen; memindahkannya berarti memindahkan arti nomor yang sudah beredar
tanpa satu pun jejak.

## Bukti otomatis

| Berkas | Cakupan |
|---|---|
| `tests/Feature/SYSTEM/MasterData/DepartemenTest.php` | skema, indeks unik, seed 52, binding persis, perilaku CRUD |
| `tests/Feature/LMS/SOP/SopDokumenDepartemenTest.php` | turunan sub-divisi/divisi, penolakan departemen asing dan nonaktif, lingkup deret nomor, jejak audit berkode |
