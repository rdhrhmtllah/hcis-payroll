# Ketentuan tak tertulis yang kini tertulis

`AGENTS.md` mengatur **mekanika**: alur DDL, gerbang keselamatan, konvensi penamaan,
status change. Berkas ini mengatur **pertimbangan** — hal yang berulang kali diputuskan
pemilik proyek di tengah pekerjaan, tidak pernah tercatat, lalu ditanyakan lagi oleh
agent berikutnya.

Setiap butir di bawah menyertakan **kutipan aslinya**. Itu disengaja: yang berasal dari
pemilik proyek harus bisa dibedakan dari yang merupakan simpulan agent. Butir tanpa
kutipan ditandai *(simpulan agent)* dan boleh dibantah.

Dikumpulkan dari sesi Agustus–September 2026, sebagian besar dari pembangunan modul
Performance Appraisal.

---

## A. Cara kerja

### A1. Jawab dalam tiga daftar pendek

> "to the poin saja jangan terlalu panjang, berikan apa saja yang beresiko dan mesti di
> perbaiki, apa yang sudah selesai, dan apa yang mesti dikerjakan selanjutnya"

Format wajib: **beresiko/harus diperbaiki**, **selesai**, **selanjutnya**. Satu baris per
butir. Penjelasan panjang hanya bila diminta. Ini sudah dikoreksi tiga kali
("terlalu berbelit", "jawab dengan singkat saja"), jadi bukan preferensi ringan.

### A2. Keputusan teknis kecil diputuskan agent, bukan dilempar balik

> "jadi sbnarnya apa yang mesti saya putuskan ini??"

Muncul ketika agent menaikkan pilihan sepele (master vs varchar) ke pemilik proyek.
Aturannya: kalau proyek sudah punya aturan yang bisa dipakai memutuskan, **putuskan
sendiri**, sebutkan pilihannya dalam satu kalimat, lanjut. Yang dinaikkan hanya keputusan
bisnis yang datanya tidak bisa menjawab.

### A3. Satu topik sampai tuntas

> "satu persatu dulu, nanti kita bahas terkait integrasi ny, ini kita bahas benaran
> arsitektur ny dulu saja sudah solid atau belum"

> "no kita bahas dulu untuk resiko ny, kita mulai dari no 1"

Jangan mencampur arsitektur, integrasi, dan implementasi dalam satu jawaban. Kalau ada
daftar bernomor, bahas nomor yang diminta saja.

### A4. Pekerjaan pencatatan didelegasikan

> "untuk pencatatan ny pakai saja sub agent yang cepat, dan kita bisa lanjutkan ke next
> step"

Dokumentasi, README change, dan pembaruan ledger boleh dikerjakan sub-agent supaya jalur
utama tidak berhenti. Yang **tidak** boleh didelegasikan: keputusan desain dan verifikasi
hasil eksekusi.

### A5. Dikerjakan bertahap per lapisan, per fitur — bukan mengikuti urutan permintaan

> "kita kan awalnya sedang mendesign dari Arsitektur database dulu, lalu tiba-tiba kita
> melompat-lompat, nah harusnya kita bertahap dulu dari arsitektur database, baru buat
> variabel, service, helper, dll, lalu baru buat controller — dan itu pun harus per
> fitur atau tertata dan rapi, biar tidak ada satu pun yang terlewat."

Urutannya: **arsitektur database → katalog & konstanta → service, helper, penjaga → uji
service → controller, rute, validasi → kontrak API, fixture → akses & menu → uji
kontrak.** Satu fitur diselesaikan sampai habis sebelum fitur berikutnya dimulai.

Yang boleh mendahului hanya lapisan database: ia diselesaikan untuk seluruh modul
sekaligus, karena tabelnya saling merujuk.

**Kenapa ini bukan formalitas.** Modul PA dikerjakan mengikuti urutan permintaan, dan
akibatnya terukur: **empat dari sepuluh** operasi yang sudah punya gerbang di
`PaCycleGate` ternyata tidak punya satu baris pun jalur kode — termasuk `TERBITKAN`,
tanpa mana modul penilaian tidak menghasilkan apa pun bagi karyawan. Tak satu pun
ketahuan sampai peta kerja per lapisan dibuat. Tidak ada langkah yang salah; yang tidak
ada adalah yang memeriksa apa yang belum dikerjakan.

Permintaan yang datang di tengah **ditulis di antrean**, bukan langsung dikerjakan —
kecuali pemilik proyek menyatakan itu mendahului. Peta kerjanya:
`plans/kpi/pa-peta-kerja.md`.

### A6. Aturan yang cuma prosa langsung dijadikan penjaga

> "ya mungkin kita langsung saja implementasikan yang memang perlu dijaga dan diperiksa
> ketat tersebut sekarang agar tidak terlewat ataupun ter-skip"

Begitu sebuah aturan teridentifikasi tidak bisa ditegakkan skema, penjaganya dibuat saat
itu juga — bukan dicatat sebagai utang. Register-nya:
`docs/architecture/pa-aturan-yang-tidak-dijaga-database.md`.

---

## B. Keputusan desain database

### B1. Kolom yang nilainya selalu sama TIDAK dibuat

> "kenapa itu mesti di declare kan? itu akumulasi YTD untuk KPI aja kan ya?"

Membatalkan `N_PA_Metode_Nilai`. Kolom yang isinya tidak pernah berbeda tidak menyimpan
informasi apa pun. Alasan "seandainya kelak berubah" tidak cukup — perubahan metode
sudah terjawab oleh `Dikalkulasi_At` plus catatan change-nya. Master baru dibenarkan
hanya bila dua nilai benar-benar hidup berdampingan.

Ini adalah **batas K31**: K31 mewajibkan master + FK untuk kolom yang isinya daftar tetap
**dan berbeda-beda**. Kalau tidak pernah berbeda, tidak ada daftarnya.

### B2. Nilai bisnis TIDAK PERNAH dipotong demi kerapian skala

> "ngak gini loh mksd ny bukan kita dipotong, tapi kayak kamu ada patokan nilai 0–100,
> nah kalau misal > 100 tetap A juga kan. butuh potong itu menyalahi"

Ketika skala KPI (0–125) bertemu skala kompetensi (0–100), tiga jalan keluar diajukan
agent — salah satunya memotong KPI di 100. Ditolak. Yang dipilih: **batas atas pitanya
yang dibuka** (`999.99`), bukan nilainya yang dipangkas.

Berlaku umum: kalau sebuah nilai tidak muat di skala, yang salah skalanya. Memotong nilai
membuang pencapaian nyata seseorang supaya angkanya rapi.

### B3. Ketentuan bisnis ditegakkan, bukan diakali

> "ngak tetap 100 lah kan itu sudah ketentuan kita"

`Total_Bobot` KPI ditetapkan selalu 100. Ketika ditemukan baris pengembangan berbobot 50,
jawabannya bukan "toh perhitungannya sudah menormalkan" — melainkan **menahan hasilnya
dan melaporkan**. Data yang menyimpang dari ketentuan harus terlihat, bukan diserap
diam-diam oleh rumus yang kebetulan tahan banting.

### B4. Nama kolom yang membingungkan diperbaiki sebelum dipakai

> "ya mungkin di perjelas dari nama kolom ny biar tidak membingungkan"

Kalau nama kolom perlu dijelaskan lisan supaya dipahami, namanya yang diganti — selagi
tabelnya masih `TRACKER_PENDING` dan belum masuk production.

### B5. Sesuatu yang "per level" tetap per level

> "ITU TETAP HARUS PER LEVEL KARENA MEMANG INI DIDESAIN PER LEVEL"

Jangan menyederhanakan granularitas yang sudah ditetapkan hanya karena datanya
kebetulan seragam sekarang.

### B6. Mockup bukan sumber kebenaran

> "YA MEMANG TIDAK ADA PAKAI AJA YANG ADA, SOALNYA DI MOCKUP ITU HANYA MOCKUP"

Kalau mockup menyebut master yang tidak ada di database, **pakai master yang ada**.
Jangan membuat tabel baru untuk menyamai gambar.

### B7. Field integrasi pihak ketiga tinggal di tabel integrasinya sendiri

> "terkait dengan id ujian token, kalau misal di LMS dan juga di SOP kan itu benaran
> dipisahkan dalam satu table integrasi sendiri, nah itu apakah kita tidak lebih baik
> memisahkannya di table integrasi sendiri juga??"

Diajukan pemilik proyek, bukan agent. Token, payload mentah, dan penanda pihak ketiga
tidak menempel di tabel bisnis. Preseden: `N_LMS_Penjadwalan_Integrasi`,
`N_SOP_Penjadwalan_Integrasi`, `N_PA_Assignment_Integration`.

### B8. Pengelompokan di mockup dan deck adalah PENYAJIAN, bukan penyimpanan

> "itu yang poin 2 itu hanya mockup saja, tetap saja itu per level"
> "no 1 itu gpp karena itu hanya mockup saja"

Mockup dan slide 18 mengelompokkan level menjadi tiga band — Managerial, Specialist,
Staff. Sistem **tidak** menyimpannya begitu: aturan penilai dan bobot pilar disimpan
**per level**, tiga belas baris.

Ini menutup dua pertanyaan yang sempat menggantung:

- **Konflik slide 4 vs slide 18.** Slide 4 (per level, dua belas baris) yang berlaku;
  slide 18 adalah band penyajian.
- **"Officer (Specialist)" yang hilang.** Bukan jalur yang hilang — Specialist adalah
  nama band, bukan level tersendiri. Tidak ada yang perlu ditambahkan.

Berlaku juga pada kosakata: mockup memakai Departemen → Divisi, sedangkan skema memakai
Divisi → Sub_Divisi. Yang dipakai adalah **nama kolom skema**; layar bebas melabelinya.

### B9. Notifikasi PA hanya in-app

> "notifikasi memang harus ada di in-app saja"

Lewat `N_HRIS_Notification`. Tanpa email, tanpa WhatsApp.

Satu jebakan yang sudah pernah memakan korban di modul lain: notifikasi yang `source`-nya
bukan `MAIN_NOTIF` **tersimpan tetapi tidak pernah muncul** — topbar dan modalnya
menyaringnya, dan uji tabel tetap hijau.

**Dibangun 1 September 2026** (`PaNotificationService`), dan ada jebakan KEDUA yang baru
ketahuan saat membangunnya: `NotificationService::normalizeModule()` memetakan nilai yang
tak dikenalnya menjadi `'HCIS'` **tanpa berkata apa-apa**. `Module` bernilai `'PA'` akan
diam-diam berubah. Yang benar `'KPI'` — dan itu juga benar secara isi, karena PA submodul
KPI.

Aturan ketiga yang menyertainya: pemberitahuan dikirim **sesudah commit**, tidak pernah
di dalam transaksi. `N_HRIS_Notification` di-polling topbar setiap menit oleh setiap orang
yang sedang membuka HCIS; menulisinya sambil memegang kunci pernah menghabiskan kolam
koneksi sampai seluruh sistem menjawab "Login timeout expired".

Pengingat harian berjalan lewat `pa:ingatkan-penilai`, dijadwalkan di
`N_HRIS_Scheduler_Configs` (DML-2026-087) — penjadwal proyek ini digerakkan tabel, bukan
kode.

### B10. "Leaders" di deck adalah BAND PENYAJIAN, bukan kategori penilai

Diputuskan 1 September 2026, mengikuti pola B8.

Kategori penilai yang ada hanya empat: `ATASAN`, `BAWAHAN`, `PEER`, `SELF`. Tidak ada
"Leaders", dan tidak perlu ada. Kata itu di deck menamai **kumpulan orang** — Manager ke
atas, yaitu `Level_Hierarchy >= 8`, sekitar 275 karyawan aktif per 1 September 2026 — bukan
sebuah peran dalam penilaian.

Konsekuensinya: **tidak ada perubahan skema**. Layar bebas melabeli kelompok itu "Leaders";
sistem tetap menyimpan per level, sama seperti B8.

### B11. Bobot pilar tiga belas level masih SEMENTARA

Seluruh tiga belas baris `N_PA_Pillar_Weight` bertanda "SEMENTARA — deck hanya memberi
empat pita". Yang mendesak hanya **enam**, karena tujuh level lainnya tidak ditempati satu
orang pun:

| Level | Karyawan aktif | KPI / Kompetensi |
|---|---|---|
| STAFF | 391 | 80 / 20 |
| OFFICER | 188 | 75 / 25 |
| GENERAL MANAGER | 183 | 55 / 45 |
| MANAGER | 92 | 65 / 35 |
| SENIOR STAFF | 4 | 80 / 20 |
| SENIOR OFFICER | 2 | 75 / 25 |

Empat teratas mencakup **854 dari 858** peserta. Angka-angka itu belum pernah ditinjau
pemilik proses, dan **mengubahnya kelak akan menggeser nilai akhir semua orang pada siklus
berikutnya** — jadi ia layak ditinjau sebelum siklus sungguhan pertama dijalankan, bukan
sesudahnya.

---

## C. Data contoh dan seed

### C1. Acak boleh, tetapi harus natural

> "untuk score range itu kamu random dulu aja tapi tetap natural"

> "kalau kamu mau test, set saja random tapi tetap natural"

Data contoh harus masuk akal secara bisnis: pita nilai yang bersinambung, sebaran skor
yang wajar, nama yang terbaca. Angka asal seperti 999 atau "test test" membuat layar dan
grafik tampak benar padahal belum diuji apa pun.

### C2. *(simpulan agent)* Data contoh harus bisa dibuang kembali

Setiap perintah pembuat data contoh menyediakan `--hapus` dan hanya menyentuh baris
bertanda khusus. Contoh: `php artisan pa:demo-cycle` hanya menyentuh siklus ber-kode
`PA-DEMO-FE`.

---

## D. Integrasi pihak ketiga

### D1. Permintaan ke pihak luar dikirim SEKALI, saat lengkap

> "kita jalan dulu dengan konsep sekarang, anggap itu memang yang kita pakai, sehingga
> nanti kalau kita masih ngerasa ngak sesuai kita bisa tambahin lagi respon permintaan
> kita, sehingga nanti saat pengiriman itu akan hanya satu kali request"

Kontrak ke HC Learn ditahan sampai kebutuhannya berhenti tumbuh, bukan dikirim bertahap.
Konsekuensinya: dokumen kontrak yang belum dikirim **wajib bertanda jelas** dan punya
pemicu kapan dikirim, supaya tidak berubah jadi tidak pernah.

### D2. Satu URL callback, bukan endpoint per modul

Diputuskan lebih dulu di modul SOP dan diikuti PA. HC Learn memanggil satu alamat;
`LmsApiGuard` yang mengenali pemilik tokennya lalu mendelegasikan. Alasan yang tercatat
di kode: dua pintu masuk harus dijaga sama persis, dan pasti berselisih.

---

## E. Uji, build, dan kecepatan

### E1. Jangan jalankan uji di tengah pengerjaan

> "jangan uji sebelum ACC"

> "test-nya itu akhiran saja biar development lebih cepat"

`php artisan test` menembak database development yang sama dengan yang dipakai layar, dan
RCSI di `Web_HRIS` mati — pembaca antre di belakang penulis, sehingga UI pemilik proyek
ikut membeku. Tulis kode **dan** ujinya sampai selesai, jalankan sekali di akhir.

### E2. Jangan build saat frontend sedang bekerja

> "nanti saja build-nya itu karena saya sekarang bisa hidupkan dari HMR"

`npx vite build` dan `npm run dev` tidak dijalankan agent backend.

### E3. Hitung kueri sebelum menulis loop *(simpulan agent, dari kejadian nyata)*

Database development ada di `team311.dyndns.info:1433`, bukan lokal. Satu round-trip
±72 ms. Perulangan 25 kueri × 900 baris = ±30 menit, dan itu benar-benar terjadi pada
`hitungSiklus()`. Sebelum menulis perulangan atas data karyawan, hitung kuerinya per
iterasi.

### E4. Jangan mengulang peringatan yang sudah diketahui

Pemilik proyek sudah tahu konsekuensi queue worker mati. Jangan menempelkannya sebagai
peringatan pencegahan tiap kali menyentuh antrean.

---

## F. Yang BELUM diputuskan — jangan dikarang

Jujur mencatat yang belum ada jawabannya lebih berguna daripada menebak lalu
membangunnya.

| Pertanyaan | Keadaan |
|---|---|
| Kolom mana yang menentukan kelayakan peserta dari sisi **jenis kontrak** | Empat kolom saling bertentangan (`Status_Karyawan` NULL pada 901 dari 958, `status_kontrak` NULL pada 949, `Flag_PKWT` NULL pada 954, `Status_Kepegawaian` bilang 904 PKWT). Belum diputuskan. Saat ini penyaringnya hanya `Aktif='Y'`, minus FREELANCE, minus resign sebelum periode berakhir. |
| Apakah 57 karyawan aktif dengan `Tgl_PKWT_Selesai` yang sudah lewat termasuk dinilai | Belum diputuskan |
| Siapa yang berhak membuka halaman PA di production | Belum diputuskan; akses sekarang hanya dua user pengembang |
| Apakah tiga baris KPI `Total_Bobot=50` yang berstatus disetujui itu sisa uji coba | Belum diperiksa pemilik proyek |

---

## Cara memakai berkas ini

- Agent baru membacanya **sesudah** `AGENTS.md` dan **sebelum** menulis kode.
- Menambah butir baru: sertakan kutipan aslinya kalau berasal dari pemilik proyek, atau
  tandai *(simpulan agent)* kalau tidak.
- Butir yang ternyata keliru **dihapus**, bukan dibiarkan dengan catatan. Aturan yang
  salah lebih berbahaya daripada aturan yang tidak ada.
