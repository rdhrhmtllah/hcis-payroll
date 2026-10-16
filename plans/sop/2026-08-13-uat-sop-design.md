# Dokumen UAT Modul SOP — Rancangan

**Tanggal:** 13 Agustus 2026
**Keluaran:** `docs/uat/IT_BA_UAT SOP.docx` (file baru; `docs/uat/IT_BA_UAT WEBKARIR-MT.docx` tidak disentuh)

---

## Latar Belakang

Modul SOP sudah lengkap dari sisi pengembangan tapi belum pernah diterima secara
formal oleh pengguna bisnis. Yang dibutuhkan adalah dokumen UAT setara dengan
`docs/uat/IT_BA_UAT WEBKARIR-MT.docx` — dokumen Business Analyst yang berakhir pada
Sign-Off, bukan checklist QA internal.

Dokumen WebKarir dipakai sebagai **cetakan**, bukan sebagai file yang ditimpa.
Alasannya sederhana dan tidak bisa dibatalkan: file itu tidak terlacak git dan
tidak di-`gitignore`, jadi menimpanya menghapus satu-satunya salinan dokumen BA
WebKarir yang ada.

## Keputusan yang Sudah Disepakati

1. **File baru.** `docs/uat/IT_BA_UAT SOP.docx`. WEBKARIR-MT.docx tetap utuh.
2. **Kedalaman: alur inti + skenario kontrol.** Bukan sekadar jalur mulus.
   SOP adalah sistem kepatuhan — nilainya justru pada apa yang ia **tolak**.
   UAT yang hanya membuktikan jalur normal tidak membuktikan dokumennya
   terkendali.
3. **Pengelompokan per modul**, memakai nama yang sama persis dengan tabel
   Ruang Lingkup. Satu taksonomi untuk seluruh dokumen; pembaca tidak perlu
   memetakan bab 2 ke bab 4.
4. **Peserta dibawa dari dokumen WebKarir** (10 nama), kolom *Role dalam UAT*
   disesuaikan ke konteks SOP. Fakta organisasi yang tidak bisa diketahui dari
   kode — Doc ID, Tanggal UAT, Requestor — ditulis sebagai penanda `«…»` yang
   mencolok, bukan ditebak.

## Cara Memproduksi

Skrip Python (`python-docx`) yang **membuka `docs/uat/IT_BA_UAT WEBKARIR-MT.docx` sebagai
cetakan**, mengosongkan body, lalu menulis ulang isinya dan menyimpan sebagai
file baru.

Kenapa begitu, bukan menulis dari nol: `styles.xml`, `theme1.xml`, dan berkas
font (Quattrocento Sans, Noto Sans Symbols) tidak disentuh sama sekali, sehingga
identitas visual dokumen BA terbawa persis tanpa perlu direkonstruksi. Menulis
dari nol berarti menebak-nebak ukuran, warna, dan spasi sampai *mirip* — dan
mirip tidak pernah cukup untuk dokumen yang ditandatangani di sebelah dokumen
lain dari seri yang sama.

Data skenario dipisah ke modulnya sendiri, mengikuti pola yang sudah ada di repo
ini (`scripts/uat_v7_business_flow_data.py`). Konsekuensinya: merevisi satu
skenario cukup mengubah data lalu menjalankan ulang skrip — bukan mengedit Word
dan perlahan kehilangan konsistensi antar baris.

```
scripts/uat_sop_data.py     # data: modul, peserta, 48 skenario, severity, kriteria
scripts/build_uat_sop.py    # perakit: baca cetakan → tulis → simpan
```

---

## Arsitektur Dokumen

Delapan bab, penomoran dan urutan persis template.

| Bagian | Isi | Bentuk |
|---|---|---|
| Sampul + Info Dokumen | Judul jadi *Modul SOP — Sistem HCIS*. Doc ID / Tanggal UAT / Requestor pakai `«…»` | Tabel 2 kolom, 9 baris |
| 1. Tujuan UAT | Validasi dari sudut Admin SOP, Karyawan, Leader, kontrol dokumen, dan laporan | Paragraf |
| 2. Ruang Lingkup | 16 modul + paragraf *di luar ruang lingkup* | Tabel 3 kolom |
| 3. Peserta UAT | 10 nama, peran disesuaikan | Tabel 4 kolom |
| 3a. **Prasyarat & Data Uji** | **Bab tambahan** — lihat alasannya di bawah | Tabel 3 kolom |
| 4. Skenario UAT per Modul | 48 skenario | Tabel 9 kolom |
| 5. Acceptance Criteria | Disesuaikan, ditambah syarat kepatuhan | Paragraf berbutir |
| 6. Defect Log + Definisi Severity | Rangka kosong 5 baris | Tabel 7 kolom |
| 7. Hasil & Kesimpulan | Rangka kosong + narasi | Tabel 2 kolom |
| 8. Sign-Off | 10 baris, kolom Tanda Tangan & Tanggal kosong | Tabel 6 kolom |

Kolom **Hasil Aktual / Status / Catatan** pada tabel skenario sengaja dikosongkan
— diisi penguji saat sesi berlangsung, persis seperti dokumen WebKarir.

### Kenapa ada bab Prasyarat, padahal template tidak punya

Skenario SOP saling bergantung berantai: dokumen harus terbit sebelum paket bisa
dirakit, paket harus terjadwal sebelum karyawan punya tugas. Tanpa daftar
prasyarat, penguji berperan Karyawan akan membuka *SOP Saya*, menemukannya
kosong, dan menulis **Fail** untuk sesuatu yang sebenarnya belum disiapkan
penguji lain.

Kegagalan palsu lebih mahal daripada satu bab tambahan: ia memicu investigasi
developer, menunda Sign-Off, dan mengikis kepercayaan pada seluruh dokumen.

Isi babnya:

- **Akun uji per persona** — satu Admin SOP, satu Leader (punya bawahan di data
  struktur), dua Karyawan (satu di bawah Leader tersebut).
- **Urutan wajib antar skenario** — skenario karyawan tidak boleh dijalankan
  sebelum skenario penjadwalan dinyatakan Pass.
- **Hak akses yang harus sudah diberikan** — tujuh page code, dengan catatan
  khusus di bawah.
- **Kondisi lingkungan** — pekerja antrean harus hidup selama sesi, karena
  penyemaian progres penugasan berjalan lewat antrean di atas ambang tertentu.

### Catatan hak akses yang wajib masuk dokumen

Modul SOP memakai **tujuh** page code:

```
menuSopKategoriPage     menuSopKontenPage       menuSopPenjadwalanPage
menuSopDokumenPage      menuSopBuilderPage      menuSopMonitoringPage
                                                menuSopKebijakanPage
```

Skrip pemberian akses yang ada, `plans/sop/sql/24A_sop_menu_akses_dml.sql`,
hanya mendaftar **enam** — `menuSopDokumenPage` tidak ada di dalamnya, karena
halaman Master Dokumen lahir setelah skrip itu ditulis.

Kalau akses UAT disiapkan dengan skrip tersebut apa adanya, penguji akan
menemukan Master Dokumen tertutup dan mencatatnya sebagai cacat sistem —
padahal itu haknya yang belum diberikan. Bab Prasyarat menyebut ketujuhnya
secara eksplisit supaya kekeliruan itu tidak terjadi.

`menuSopSayaPage` dan `menuSopRepositoryPage` sengaja global (semua karyawan),
jadi tidak perlu diberikan.

---

## Ruang Lingkup — 16 Modul

Urutan tabel ini mengikuti alur kerja nyata, dan kolom *Modul* pada tabel
skenario memakai nama yang sama persis.

| # | Modul | Fokus Validasi Bisnis |
|---|---|---|
| 1 | Hak Akses & Menu SOP | Halaman admin hanya terbuka bagi yang berhak; halaman karyawan terbuka untuk semua |
| 2 | Master Kategori SOP | Kategori sebagai rak penyimpanan paket; kategori terpakai terlindungi |
| 3 | Master Konten (Berkas) | Unggah berkas SOP; berkas terpakai terlindungi dari penghapusan |
| 4 | Master Dokumen Terkendali | Identitas legal dokumen: nomor, versi, pengesahan, masa berlaku, terbit, kembalikan |
| 5 | Builder Paket SOP | Merakit paket dari dokumen: section, urutan, wajib, mode akses, terbit paket |
| 6 | Penjadwalan & Penugasan | Menyasar unit/orang, tenggat, menambah orang ke jadwal berjalan |
| 7 | Kebijakan & Otomasi | Aturan penugasan otomatis, setelan modul, kesehatan penjadwal |
| 8 | Repository SOP | Karyawan menjelajah, mencari, memfavoritkan, mengintip |
| 9 | SOP Saya | Daftar tugas, tenggat, progres, kartu kepatuhan pribadi |
| 10 | Membaca & Konfirmasi Dokumen | Pembaca PDF aman, rekam halaman, konfirmasi baca, urutan wajib |
| 11 | Kuis / Checkpoint | Ujian pemahaman dan pengaruhnya pada status selesai |
| 12 | Absensi Check-in | Kehadiran sebagai syarat membuka item |
| 13 | Kepatuhan Tim (Leader) | Atasan memantau kepatuhan bawahannya, dan hanya bawahannya |
| 14 | Monitoring & Laporan | Kepatuhan per paket & per orang, export, register kontrol dokumen |
| 15 | Siklus Hidup Dokumen | Revisi menyusul ke semua paket pemakai; kadaluarsa tertandai |
| 16 | Notifikasi & Rekertifikasi | Pemberitahuan penugasan, pengingat tenggat, baca ulang berkala |

**Di luar ruang lingkup UAT:** performance/load testing, penetration testing,
proses internal sistem kuis eksternal (hanya integrasi callback yang diuji dari
sisi SOP), migrasi data historis, dan modul HCIS lain di luar SOP.

---

## Peta Skenario — 48 Butir

Format kolom mengikuti template: `UAT-ID | Modul | Penguji | Skenario Bisnis |
Steps | Expected Result | Hasil Aktual | Status | Catatan`.

*Skenario Bisnis* ditulis sebagai user story ("Sebagai …, saya ingin …"),
*Steps* sebagai narasi bisnis — bukan langkah teknis per klik. Baris bertanda
**[K]** adalah skenario kontrol: yang dibuktikan adalah sistem **menolak**.

### 1 · Hak Akses & Menu SOP

| ID | Penguji | Inti |
|---|---|---|
| 001 | Admin SOP | Membuka ketujuh halaman admin SOP; semuanya terbuka |
| 002 **[K]** | Karyawan | Menu admin SOP tak muncul, dan mengetik URL-nya langsung tetap ditolak — sementara SOP Saya & Repository tetap terbuka |

### 2 · Master Kategori SOP

| ID | Penguji | Inti |
|---|---|---|
| 003 | Admin SOP | Membuat & mengubah kategori beserta urutan tampilnya |
| 004 **[K]** | Admin SOP | Kategori yang masih dipakai paket tak bisa dihapus |

### 3 · Master Konten (Berkas)

| ID | Penguji | Inti |
|---|---|---|
| 005 | Admin SOP | Mengunggah berkas SOP dan menemukannya kembali |
| 006 **[K]** | Admin SOP | Melihat daftar pemakai sebuah berkas, dan berkas terpakai menolak dihapus |

### 4 · Master Dokumen Terkendali

Bagian terpadat, karena di sinilah identitas legal dokumen ditegakkan.

| ID | Penguji | Inti |
|---|---|---|
| 007 | Admin SOP | Mendaftarkan dokumen terkendali baru: nomor SOP resmi, judul, tipe, kategori |
| 008 | Admin SOP | Menambah versi lewat layar dua-tahap — riwayat dulu, baru formulir; simpan sebagai draft |
| 009 **[K]** | Admin SOP | Meninggalkan formulir yang sudah terisi dikonfirmasi dulu, tidak dibuang diam-diam |
| 010 | Admin SOP | Melengkapi blok pengesahan & masa berlaku, lalu menandai versi siap terbit |
| 011 | Admin SOP | Melihat **dampak** sebelum menerbitkan: paket mana dan siapa saja yang terkena |
| 012 | Admin SOP | Menerbitkan versi — versi lama jadi kadaluarsa, penunjuk versi aktif berpindah |
| 013 **[K]** | Admin SOP | Draft tanpa berkas atau tanpa pengesahan **tidak bisa** diterbitkan |
| 014 | Admin SOP | Mengembalikan ke versi sebelumnya, dan jejak audit menyebut siapa, kapan, apa yang berubah |

### 5 · Builder Paket SOP

| ID | Penguji | Inti |
|---|---|---|
| 015 | Admin SOP | Membuat paket SOP baru beserta kategori & sampulnya |
| 016 | Admin SOP | Menyusun section dan item, mengatur urutan, menandai Wajib & Highlight |
| 017 | Admin SOP | Menyisipkan dokumen terkendali dari Master Dokumen ke dalam paket |
| 018 | Admin SOP | Menyalin item ke paket lain — tautannya hidup, bukan salinan yang lepas sendiri |
| 019 | Admin SOP | Meninjau pratinjau paket, lalu menerbitkannya berikut sifat revisi tiap dokumen |

### 6 · Penjadwalan & Penugasan

| ID | Penguji | Inti |
|---|---|---|
| 020 | Admin SOP | Menjadwalkan paket dengan sasaran per unit/departemen, berikut tenggatnya |
| 021 | Admin SOP | Menyasar orang tertentu dengan menempelkan daftar NIK |
| 022 **[K]** | Admin SOP | Jumlah orang di pratinjau sasaran sama dengan yang benar-benar tersimpan |
| 023 | Admin SOP | Menambah karyawan ke jadwal yang sudah berjalan; tugasnya ikut terbentuk |

### 7 · Kebijakan & Otomasi

| ID | Penguji | Inti |
|---|---|---|
| 024 | Admin SOP | Menyusun aturan penugasan otomatis dan memratinjau siapa yang akan terkena |
| 025 | Admin SOP | Mengaktifkan/menonaktifkan aturan, mengubah setelan, melihat riwayat & kesehatan penjadwal |

### 8 · Repository SOP

| ID | Penguji | Inti |
|---|---|---|
| 026 | Karyawan | Menjelajah repository per kategori, mencari, dan menandai favorit |
| 027 **[K]** | Karyawan | Mengintip dokumen yang tak ditugaskan hanya memperlihatkan keterangannya, bukan isinya |

### 9 · SOP Saya

| ID | Penguji | Inti |
|---|---|---|
| 028 | Karyawan | Melihat daftar tugas SOP, tenggat, dan sejauh mana progresnya |
| 029 | Karyawan | Melihat kartu kepatuhan pribadi |

### 10 · Membaca & Konfirmasi Dokumen

| ID | Penguji | Inti |
|---|---|---|
| 030 | Karyawan | Membuka paket dan menemukan item tersusun sesuai urutan yang dirakit admin |
| 031 | Karyawan | Membaca PDF di pembaca aman; menutup di tengah lalu kembali ke halaman terakhir |
| 032 **[K]** | Karyawan | Konfirmasi baca baru bisa ditekan setelah dokumennya benar-benar dibaca tuntas |
| 033 **[K]** | Karyawan | Item wajib tak bisa dilewati — yang berikutnya terkunci sampai yang wajib dikonfirmasi |

### 11 · Kuis / Checkpoint

| ID | Penguji | Inti |
|---|---|---|
| 034 | Karyawan | Mengerjakan kuis; nilainya kembali dan tercatat pada progres |
| 035 **[K]** | Karyawan | Tidak lulus kuis berarti paket belum dinyatakan selesai |

### 12 · Absensi Check-in

| ID | Penguji | Inti |
|---|---|---|
| 036 | Karyawan | Melakukan check-in kehadiran pada paket yang mensyaratkannya |
| 037 **[K]** | Karyawan | Tanpa check-in, item paket tetap tersegel |

### 13 · Kepatuhan Tim (Leader)

| ID | Penguji | Inti |
|---|---|---|
| 038 | Leader | Melihat ringkasan kepatuhan SOP seluruh anggota timnya |
| 039 **[K]** | Leader | Menelusuri per anggota, dan hanya anggotanya sendiri yang terlihat |

### 14 · Monitoring & Laporan

| ID | Penguji | Inti |
|---|---|---|
| 040 | Admin SOP | Memantau kepatuhan sebuah paket: siapa selesai, siapa belum, siapa lewat tenggat |
| 041 | Admin SOP | Menelusuri satu karyawan: paket apa saja, rekam bacanya, kapan dikonfirmasi |
| 042 | Admin SOP | Mengunduh laporan kepatuhan untuk kebutuhan audit |
| 043 **[K]** | Admin SOP | Register kontrol dokumen melaporkan satu dokumen dengan satu status, tidak saling bertentangan |

### 15 · Siklus Hidup Dokumen

| ID | Penguji | Inti |
|---|---|---|
| 044 | Admin SOP & Karyawan | Revisi yang diterbitkan menyusul otomatis ke **semua** paket yang memakai dokumen itu |
| 045 | Karyawan | Karyawan yang sudah mengkonfirmasi versi lama diminta membaca ulang bila revisinya wajib |
| 046 **[K]** | Admin SOP | Dokumen yang lewat tanggal kadaluarsa tertandai di register dan monitoring |

### 16 · Notifikasi & Rekertifikasi

| ID | Penguji | Inti |
|---|---|---|
| 047 | Karyawan | Pemberitahuan penugasan baru sampai ke karyawan, dan pengingat menjelang tenggat terkirim sesuai kadensnya |
| 048 | Admin SOP & Karyawan | Rekertifikasi berkala menjadwalkan ulang pembacaan dokumen yang masa berlakunya habis, tanpa perlu dijadwalkan manual |

---

## Acceptance Criteria

Disesuaikan dari template, dengan satu tambahan yang khas SOP:

1. Seluruh skenario pada bab 4 berstatus **Pass** — tidak ada Fail atau Blocked
   saat sesi berakhir.
2. Tidak ada issue **Critical** maupun **Major** yang masih terbuka.
3. Issue **Minor** boleh terbuka, dengan kesepakatan tertulis kapan diperbaiki.
4. **Seluruh skenario bertanda [K] berstatus Pass.** Skenario kontrol tidak bisa
   ditawar menjadi Minor — sistem dokumen terkendali yang gagal menolak hal yang
   salah tidak layak Go Live meski seluruh jalur normalnya mulus.
5. Sign-Off ditandatangani seluruh peserta pada bab 8.

## Definisi Severity

| Severity | Batasan |
|---|---|
| Critical | Proses inti terhenti, data kepatuhan salah, atau dokumen tak sah tersaji ke karyawan |
| Major | Fitur penting tak berfungsi, ada jalan memutar yang merepotkan |
| Minor | Tampilan, penulisan, atau kenyamanan; proses bisnis tetap jalan |

---

## Catatan Produksi

Ditulis setelah dokumennya benar-benar dirakit dan dirender. Tiga hal di bawah
tidak terlihat saat merancang, dan akan menggigit lagi siapa pun yang mengubah
isi dokumen ini.

**Jumlah skenario kontrol: 14, bukan 11.** Angka pada rancangan awal adalah
perkiraan; hitungan sebenarnya dari data adalah 14 dari 48 (`002, 004, 006, 009,
013, 022, 027, 032, 033, 035, 037, 039, 043, 046`). Perakitnya mencetak angka ini
tiap kali dijalankan, jadi ia tidak bisa lagi meleset diam-diam.

**Cetakan punya delapan section, dan tabel skenario ada di section LANSKAP.**
Word menyimpan properti section di dalam `w:pPr` paragraf TERAKHIR section itu,
bukan sebagai elemen tersendiri di body — sehingga mengosongkan body ikut
menghapus seluruh pemutusnya dan dokumen runtuh menjadi satu section potret.
Akibatnya tidak sekadar kosmetik: tabel skenario selebar 10,16 inci, sementara
ruang cetak potret hanya 7,5 inci. Tabelnya terpotong tepat pada tiga kolom yang
harus diisi penguji — Hasil Aktual, Status, dan Catatan. Section lanskap
menyediakan 10,5 inci dan tabelnya muat utuh.

Gejalanya menipu: dokumen terbuka normal di Word, semua tabel ada, jumlah
barisnya benar. Cacatnya hanya terlihat kalau halamannya benar-benar dirender
dan dilihat.

**Jumlah paragraf kosong pada sampul harus diukur, bukan disalin.** Cetakan
memakai 21; isi versi SOP lebih tinggi sehingga 21 mendorong baris CONFIDENTIAL
tumpah ke halaman berikutnya. Angka yang benar (15) diukur lewat
`python scripts/build_uat_sop.py --sapu`, yang merender tiap kemungkinan lewat
Word dan melaporkan di halaman mana CONFIDENTIAL mendarat. Jalankan itu lagi
setiap kali isi sampul berubah. Sebagai jaring pengaman, bab 1 dipaksa mulai di
halaman baru, sehingga kesalahan angka ini paling jauh menyisakan ruang kosong
di sampul dan tidak pernah merusak susunan bab sesudahnya.

**Kedua script berada di luar git.** `/scripts` dan `/outputs` di-`.gitignore`
oleh repo ini, dan seluruh script builder UAT yang sudah ada pun tidak terlacak.
Yang di-commit hanya rancangan ini.

## Yang TIDAK Dikerjakan

- **Tidak menyentuh `docs/uat/IT_BA_UAT WEBKARIR-MT.docx`.** Hanya dibaca sebagai cetakan.
- **Tidak menyiapkan data uji di basis data.** Dokumen ini menyebut prasyaratnya;
  penyemaian datanya urusan terpisah.
- **Tidak mengubah kode modul SOP.** Kalau UAT menemukan cacat, perbaikannya
  pekerjaan sendiri di luar lingkup ini.
- **Tidak memperbaiki `24A_sop_menu_akses_dml.sql`.** Kekurangannya dicatat di
  bab Prasyarat sebagai peringatan bagi penyiap lingkungan; menambal skrip DML
  akses adalah perubahan produksi yang butuh keputusannya sendiri.
- **Tidak membuat panduan eksekusi per penguji** seperti
  `outputs/homepage-dashboard-uat/`. Yang diminta satu dokumen BA, bukan satu set
  panduan per orang.
