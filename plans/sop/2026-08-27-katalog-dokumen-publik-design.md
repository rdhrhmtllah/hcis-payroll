# Perpustakaan SOP diproyeksikan per dokumen

**Tanggal:** 27 Agustus 2026
**Halaman:** `/sop/repository` (Public Document)

## Basis database yang diverifikasi

| Hal | Nilai |
|---|---|
| Environment | development |
| Host / database | `team311.dyndns.info:1433` / `Web_HRIS` |
| Grup fitur | `DMS` — Document Management System, `Id_Grup_Fitur` = 7 |
| Pemetaan halaman | `menuSopRepositoryPage`, `Flag_Aktif = Y` pada `N_HRIS_Grup_Fitur_Map` |
| Tanggal verifikasi | 2026-08-27 |

Snapshot pada `CLAUDE.md` yang menyebut lima grup dan SOP sebagai submodul `LMS`
sudah usang; `docs/architecture/module-registry.md` sudah benar. Berkas baru
tetap diletakkan di `LMS/SOP/` bersama saudaranya — pemindahan `LMS/SOP → DMS/SOP`
adalah tugas migrasi legacy tersendiri.

## Masalah

Perpustakaan hanya menampilkan **paket**, ditata lewat satu sumbu: Kategori SOP.
Permintaannya adalah bisa menelusuri per **departemen** dan sumbu lain. Sumbu
itu tidak ada di paket: `Departemen_Id`, `Jenis_Dokumen_Id`, `APQC_Id` semuanya
melekat pada `N_SOP_Dokumen`, satu tingkat di bawah paket.

## Jawaban yang benar sudah ada di layar sebelah

SOP Saya sudah memecahkan persoalan yang sama persis:

| Komponen | Perannya |
|---|---|
| `sumbuTugas.js` → `AKSIS` | kosakata sumbu: kategori, departemen, jenis, apqc, prioritas |
| `RailSumbu.vue` | rail master-detail, sudah sumbu-agnostik |
| `BarisDokumenSaya.vue` | satu baris = satu dokumen di dalam sebuah paket |

Dan yang paling menentukan: di SOP Saya, **daftar dokumen adalah proyeksi dari
paket**, bukan entitas sendiri. Dokumen muncul karena ada paket yang ditugaskan
kepada pembacanya, dan setiap baris menautkan ke *halaman paket* — bukan ke
pembaca dokumen — supaya gerbang presensi, urutan section, dan prasyarat
antar-item tetap dilewati di sana.

## Keputusan

**Perpustakaan-per-dokumen = daftar yang sama, lingkupnya paket PUBLIK.**

1. Satu endpoint: `GET /api/v1/sop/repository/dokumen`, tanpa parameter, tanpa
   paginasi. Dimuat sekali; pengelompokan dan penyaringan di klien — sama
   seperti SOP Saya, dan syarat agar rail bisa menghitung jujur.
2. Server: `SopRepositoryReadService::katalogDokumen()`, berangkat dari
   `basePaket()` seperti seluruh jalur baca lain di kelas itu.
3. Klien: `RailSumbu` + `BarisDokumenSaya`, **dipinjam bukan ditiru**.
4. Sumbu Perpustakaan: departemen, jenis, apqc, kategori. Prioritas dibuang
   (sifat penugasan, bukan sifat dokumen); APQC ditambahkan karena di sini
   satuannya dokumen, sehingga railnya tidak lagi lebih panjang daripada
   daftarnya — alasan yang menahannya di SOP Saya tidak berlaku di halaman ini.

### Yang TIDAK dibuat, dan kenapa

- **Tanpa gerbang publikasi pada dokumen.** Jangkauan dokumen ditentukan
  paketnya. `DDL-2026-052` yang sempat membuat kolom `Mode_Distribusi` pada
  `N_SOP_Dokumen` **dibatalkan** — lihat `docs/changes/DDL-2026-052-.../README.md`.
- **Tanpa keadaan "terkunci" pada baris.** Setiap baris menuju halaman paket
  (`?item=`), dan di sanalah kunci urutan serta presensi menjelaskan dirinya
  sendiri. Menghitungnya ulang di perpustakaan hanya melahirkan penjelasan
  kedua yang bisa melenceng dari gerbang yang benar-benar menegakkannya.
- **Tanpa ubin folder, panel facet, atau paginasi di server.** Rail sudah
  menjawab "ada kelompok apa saja, isinya berapa".
- **Tanpa permukaan admin baru.** Cara mengisi perpustakaan adalah menerbitkan
  paket sebagai publik.

## Berkas

**Backend**
- `app/Services/LMS/SOP/SopRepositoryReadService.php` — `katalogDokumen()`
- `app/Http/Controllers/LMS/SOP/Employee/SopRepositoryController.php` — `apiKatalogDokumen()`
- `routes/LMS/SOP/Employee/SopEmployee.php` — satu route

**Frontend**
- `resources/js/Pages/LMS/SOP/Employee/Repository/LensaDokumen.vue` — baru
- `resources/js/Pages/LMS/SOP/Employee/Repository/SopRepository.vue` — sakelar lensa Paket ↔ Dokumen
- `resources/js/Pages/LMS/SOP/Employee/Saya/RailSumbu.vue` — dua prop opsional (`bar`, `labelSemua`)
- `resources/js/Pages/LMS/SOP/Employee/Saya/BarisDokumenSaya.vue` — satu prop opsional (`rujukan`)

Ketiga prop itu **aditif dengan bawaan yang mempertahankan perilaku lama**, jadi
SOP Saya tak berubah sedikit pun.

**Pengujian**
- `tests/Feature/LMS/SOP/SopRepositoryDokumenTest.php`

## Utang yang disadari

`RailSumbu.vue`, `BarisDokumenSaya.vue`, dan `sumbuTugas.js` kini dipakai **dua
fitur** tetapi masih tinggal di `Employee/Saya/`. Tempat yang benar adalah
`Shared/support/`. Pemindahannya menyentuh `SopSaya.vue` beserta tiga berkas
ujinya, jadi ia tugas tersendiri dan sengaja tidak dibonceng ke sini.
