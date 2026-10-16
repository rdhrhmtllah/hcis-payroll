# Glosarium DMS (Document Management System)

Status: `ACTIVE`
Diputuskan: 2026-08-27
Basis: `DML-2026-044-register-dms-module-and-menus` dan `DML-2026-045-urutan-logis-menu-dms`
(keduanya `DEV_DONE`, label menu sudah tayang di sidebar)

## Kenapa berkas ini ada

Modul ini lahir bernama **SOP** dan pindah nama jadi **DMS**. Perpindahan itu sudah
terjadi di lapisan menu — sidebar sudah berbunyi "Master Dokumen", "Monitoring Dokumen",
"Document Builder" — tetapi isi halamannya masih berbunyi "Master Dokumen SOP",
"Monitoring SOP", "Builder Paket SOP". Berkas ini mengunci kata mana yang menang,
supaya penggantian di seluruh halaman tidak melahirkan kata ketiga.

**Aturan induknya: menu adalah sumber kebenaran.** Kalau sebuah kata sudah muncul di
label menu, halaman menyusul kata itu persis — bukan variasinya.

## Batas penggantian

Yang diganti hanya **teks yang dibaca manusia**:

- node teks di `<template>`, atribut `label` / `title` / `placeholder` / `tooltip`
- literal string di `<script>` yang dipakai sebagai label, judul, atau pesan
- pesan flash, pesan validasi, dan judul laporan di sisi PHP
- judul serta kop dokumen ekspor (docx/PDF)

Yang **tidak** diganti, dan harus aman dari cari-ganti buta:

| Lapisan | Contoh | Alasan |
|---|---|---|
| Kelas CSS | `sop-glass-card`, `sop-scope`, `sop-fade-up` | mengganti = gaya lepas di ratusan tempat |
| Nama komponen & berkas | `SopDetail.vue`, `SopModal.vue` | mengganti = jalur impor putus |
| Nama route & jalur URL | `/sop/admin/dokumen` | mengganti = penanda buku & tautan notifikasi mati |
| Jalur API | `/api/v1/sop/...` | dipakai klien yang sudah tayang |
| Namespace PHP | `App\Services\LMS\SOP\...` | refactor besar, manfaat nol bagi pengguna |
| Nama tabel & kolom | `N_SOP_Dokumen`, `Nomor_SOP_Resmi` | kunci data; hanya **label**-nya yang berubah |
| Kunci page registry | `menuSopDokumenPage` | terdaftar di `N_HRIS_Grup_Fitur_Map` |

Di berkas Vue modul ini kata "sop" muncul lebih dari seribu kali, dan mayoritasnya
adalah kelas CSS serta jalur impor. Penggantian **wajib** berbasis frasa dari tabel di
bawah, bukan penggantian kata "SOP" secara umum.

## Tabel kata

### Sudah tayang di menu — halaman menyusul persis

| Lama (di halaman) | Baru | Sumber |
|---|---|---|
| Modul SOP | Document Management System (DMS) | menu |
| Master Dokumen SOP | Master Dokumen | menu |
| Kategori SOP | Kategori Dokumen | menu |
| Monitoring SOP | Monitoring Dokumen | menu |
| Penjadwalan SOP, Jadwalkan SOP | Penjadwalan Dokumen, Jadwalkan Dokumen | menu |
| Otomasi SOP | Otomasi Dokumen | menu |
| Kebijakan Global SOP | Kebijakan Global Dokumen | menu |
| Builder Paket SOP | Document Builder | menu |
| SOP Saya | Functional Document | menu |
| Perpustakaan SOP, SOP Repository | Public Document | menu |
| Library Konten SOP | Library Konten | menu |
| Jenis Dokumen SOP | Jenis Dokumen | menu |

Catatan: menu memang mencampur Indonesia dan Inggris ("Master Dokumen" bersanding
dengan "Document Builder"). Campuran itu keputusan yang sudah tayang, bukan kelalaian —
jangan diseragamkan sepihak.

### Diputuskan 2026-08-27

| Lama | Baru |
|---|---|
| Paket SOP | Paket Dokumen |
| Item SOP | Item Dokumen |
| Dokumen SOP | Dokumen |
| Nomor SOP Resmi *(label)* | Nomor Dokumen Resmi |
| Kode SOP *(label)* | Kode Dokumen |
| Nama SOP *(label)* | Nama Dokumen |
| Penugasan SOP | Penugasan Dokumen |
| Kepatuhan SOP | Kepatuhan Dokumen |
| Prioritas SOP | Prioritas Dokumen |
| Bukti Konfirmasi SOP | Bukti Konfirmasi Dokumen |
| Legalitas SOP, Legalitas Dokumen SOP | Legalitas Dokumen |
| Berkas SOP Resmi | Berkas Dokumen Resmi |
| Pengingat SOP | Pengingat Dokumen |
| Penerima SOP | Penerima Dokumen |
| Risiko SOP | Risiko Dokumen |
| Gerbang SOP | Gerbang Dokumen |
| Scope SOP, Ruang Lingkup SOP | Ruang Lingkup Dokumen |

### Pola kalimat

"SOP" sebagai kata benda tunggal di tengah kalimat menjadi **"dokumen"**:

| Lama | Baru |
|---|---|
| Belum Ada SOP Ditugaskan | Belum Ada Dokumen Ditugaskan |
| Baca SOP untuk menuntaskan kepatuhan Anda | Baca dokumen untuk menuntaskan kepatuhan Anda |
| Belum ada SOP yang perlu dibaca ulang | Belum ada dokumen yang perlu dibaca ulang |
| Anggota ini belum ditugaskan SOP | Anggota ini belum ditugaskan dokumen |
| Cari SOP | Cari dokumen |
| Daftar SOP | Daftar Dokumen |
| Total SOP | Total Dokumen |
| Lihat SOP | Lihat Dokumen |
| Memuat daftar SOP… | Memuat daftar dokumen… |

Hindari "Dokumen Dokumen". Frasa lama yang sudah memuat kata "Dokumen" cukup
dipotong: `Dokumen SOP Versi Aktif` menjadi `Dokumen Versi Aktif`, bukan
`Dokumen Dokumen Versi Aktif`.

## Jebakan: label yang ternyata menunjuk Paket

Sebagian label lama berbunyi "SOP" tetapi nilainya diambil dari kolom **paket**.
Menggantinya jadi "Dokumen" membuat layar salah sebut, karena Dokumen dan Paket
memang dua hal berbeda di modul ini.

| Label lama | Nilai yang ditampilkan | Label benar |
|---|---|---|
| Kode SOP | `Kode_Paket` / `kode_paket` | **Kode Paket** |
| Nama SOP | `Nama_Paket` | **Nama Paket** |
| Nama SOP | `Judul_Item` / `N_SOP_Dokumen.Judul` | **Nama Dokumen** |

Aturannya: **periksa dulu nilai yang diikat**, jangan menggantinya dari bunyi label.
Ditemukan di `SopDetail.vue` (tiga tempat) saat Tahap 1.

## Jebakan: dua menu yang namanya bertukar

Nama menu untuk dua halaman ini **tidak bisa ditebak** dari alamat maupun nama lamanya:

| Alamat | Label di sidebar |
|---|---|
| `/sop/admin/kebijakan` | **Otomasi Dokumen** |
| `/sop/admin/setup-system` | **Kebijakan Global Dokumen** |

Jadi halaman yang alamatnya "kebijakan" justru bernama Otomasi, dan yang bernama
Kebijakan Global justru ada di alamat "setup-system". Halaman setup-system pun punya
judulnya sendiri ("Kebijakan Global & Otomasi Terjadwal") dan kicker "Set Up System",
yang keduanya bukan nama menunya.

**Aturannya: kalau sebuah pesan menunjuk pengguna ke sebuah menu, sebut namanya persis
seperti yang tertulis di sidebar** — jangan pakai nama internal halaman, judul kartu,
atau terjemahan bebas dari alamatnya. Dua kesalahan seperti ini lolos ke kode pada
27 Agu 2026 dan tertangkap oleh `SopTipeItemSakelarTest`.

## Jebakan: halaman DMS yang tidak tinggal di folder DMS

Menyapu `resources/js/Pages/LMS/SOP/**/*.vue` saja **tidak cukup**. Dua hal lolos, dan
keduanya baru ketahuan lewat uji:

**Halaman berbagi kode dengan modul lain.** Library Konten (`menuSopKontenPage`,
`/sop/admin/konten`) memakai ulang `ContentManagementController` dan komponen Vue LMS,
dibedakan hanya oleh `->defaults('scope', 'SOP')`. Halamannya hidup di
`resources/js/Pages/LMS/Content/ContentManagement/`. Di berkas seperti ini hanya cabang
`isSop` yang boleh diganti — cabang LMS ("Manajemen Konten") harus tetap.

Hal yang sama berlaku di sisi PHP: `ContentManagementController`,
`DepartemenController`, dan `ActivityCenterService` memuat pesan yang menyebut SOP
walau namespace-nya bukan `LMS\SOP`.

**Berkas `.js` pendukung.** `sumberPalet.js` dan `support/constants.js` di dalam folder
SOP memuat label yang tampil di layar. Saringan `--include=*.vue` melewatkannya.

Cara menemukannya: telusuri dari **peta menu**, bukan dari struktur folder.

## Kata yang tetap "SOP"

Empat hal berbeda, dan semuanya tetap:

**Nama kolom & format nomor.** `Nomor_SOP_Resmi` sebagai nama kolom, dan format nomor
yang sudah tercetak pada naskah resmi (`SOP/ENB/…`, `SOP-HSE-014`). Yang berubah hanya
label yang mendampinginya di layar: "Nomor SOP Resmi" menjadi "Nomor Dokumen Resmi".

**"SOP" sebagai jenis dokumen.** Di hirarki mutu — Kebijakan, Prosedur/SOP, Instruksi
Kerja, Formulir — kata ini adalah nama tingkatan, bukan nama modul. `Prosedur
Operasional Standar (SOP)` di halaman Jenis Dokumen tetap berbunyi begitu.

**Catatan log dan keluaran konsol.** `Log::error('SOP ...')`, keterangan perintah
artisan, dan `$this->info()` dibaca pengembang serta operator, bukan pengguna. Awalan
"SOP" di sana justru penanda modul yang berguna saat menyisir log.

**Komentar kode.** Namespace, nama tabel, dan nama berkas modul ini masih `SOP`,
jadi komentar yang menyebut SOP justru akurat. Menggantinya hanya churn.

## Riwayat penerapan

| Tanggal | Cakupan |
|---|---|
| 2026-08-27 | 195 berkas: seluruh halaman DMS (Employee, Governance, Shared, Export), pesan & judul laporan PHP, dan harapan uji yang menegaskan teks tersebut |
| 2026-08-27 | `DML-2026-054` menyusul satu subtitle menu yang tertinggal dari `DML-2026-044` |
