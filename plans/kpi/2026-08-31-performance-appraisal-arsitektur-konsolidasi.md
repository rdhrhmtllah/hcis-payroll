# Konsolidasi arsitektur Performance Appraisal — 31 Agustus 2026

Fondasi database PA sudah berdiri utuh. Dokumen ini menakar apa yang sebenarnya sudah
solid, mana sambungan yang masih rapuh, dan urutan kerja berikutnya. Semua angka di sini
**diukur dari development**, bukan diingat.

## 1. Keadaan terukur hari ini

| Hal | Angka | Catatan |
|---|---:|---|
| Tabel `N_PA_` di `Web_HRIS` | 33 | dari 39 yang direncanakan |
| Tabel yang sudah berisi | 11 | seluruhnya kamus/master |
| Tabel yang masih kosong | 22 | termasuk **7 tabel aturan** yang seharusnya berisi |
| Dokumen tabel di `docs/database/tables/kpi/` | 33 | cocok 1:1 dengan database |
| Entitas di ERD (versi 71) | 37 | 63 relasi, terverifikasi langsung |
| Baris kode aplikasi PA | **0** | tidak ada controller, service, route, atau halaman |
| Change DDL menunggu SQL Tracker | 064–076 | semuanya `TRACKER_PENDING` |

Sebelas tabel yang berisi: `N_PA_Assignment_Source` (2), `N_PA_Assignment_Status` (4),
`N_PA_Competency` (24), `N_PA_Competency_Type` (4), `N_PA_Cycle_Status` (7),
`N_PA_Hav_Quadrant` (9), `N_PA_Participant_Status` (3), `N_PA_Rater_Category` (4),
`N_PA_Rater_Policy_Status` (3), `N_PA_Score_Usage` (3), `N_PA_Score_Type` (3).

Artinya: **seluruh kamus sudah lengkap, seluruh aturan masih kosong.**

## 2. Empat sambungan yang masih rapuh

Diurutkan dari yang paling berbahaya, bukan dari yang paling mudah.

### R1 — Kontrak resolver belum punya penegak — **selesai ditulis, belum dijalankan**

K32 menyatakan `PaParticipantRuleResolver` satu-satunya yang boleh menggabungkan salinan
siklus dengan override, dan **tidak ada kode lain yang boleh membaca tabel salinan siklus
untuk keperluan per orang**. Service itu belum ada.

Ini risiko arsitektural terbesar justru karena tidak kelihatan: selama resolvernya belum
ada, orang pertama yang menulis kode PA akan membaca
`N_PA_Cycle_Rater_Policy_Detail_Snapshot` langsung, dan itu terlihat benar sampai peserta
pertama yang punya override muncul. Bentuk jarang memindahkan risiko ke jalur baca dengan
syarat jalur bacanya cuma satu — kalau syaratnya tidak ditegakkan, rancangannya kehilangan
keunggulan utamanya dan yang tersisa hanya kerugiannya.

**Tindakan:** bangun resolver **sebelum** kode PA apa pun ditulis, bukan sesudah. Tiga
method: `raterRules`, `pillarWeight`, `competencyRequirements`.

### R2 — Tujuh tabel aturan masih kosong — **sebagian selesai (DML-2026-077)**

`N_PA_Rater_Policy`, `N_PA_Rater_Policy_Detail`, `N_PA_Pillar_Weight`,
`N_PA_Competency_Requirement`, `N_PA_Score_Range`, `N_PA_Competency_Kode_Soal`, dan
seluruh keluarga `N_PA_Cycle_*_Snapshot`.

Tanpa isinya, **tidak ada satu siklus pun yang bisa disiapkan** — persiapan siklus bekerja
dengan menyalin cetak biru, dan menyalin dari yang kosong menghasilkan yang kosong. Angka
untuk lima yang pertama sudah ada di deck PIC; yang keenam (`Kode_Soal` → kompetensi) baru
bisa diisi setelah HC Learn memberi daftar kodenya.

**Sudah dikerjakan** lewat DML-2026-077: `N_PA_Rater_Policy` (13), `_Detail` (45),
`N_PA_Pillar_Weight` (13), `N_PA_Competency_Requirement` (33). Cakupan terukur 860 dari 958
karyawan aktif — selisih 98 persis FREELANCE yang memang dikecualikan.

**Masih kosong dan memang belum bisa:** `N_PA_Score_Range` (deck sama sekali tidak memuat
ambang grade — bagian T05) dan `N_PA_Competency_Kode_Soal` (menunggu kode soal HC Learn).

**Masih sementara dan perlu diganti lewat halaman master:** bobot pilar, batas min/maks
penilai, dan batas kelompok "Leaders".

### R3 — Jalur `USULAN` belum bisa diuji sama sekali

`N_HRIS_Organization_Relationship` **0 baris**. FK-nya sudah terpasang dan teruji, tetapi
tidak ada satu pun relasi nyata, sehingga resolver kandidat penilai belum pernah
menghasilkan apa pun.

Konverter dari `HRIS_Approval_Flow` dan `KPI_Approval_Flow` (K21) adalah pekerjaan yang
Anda rencanakan sendiri. Satu hal yang wajib diingat saat menulisnya: dari 923 baris
`KPI_Approval_Flow_Performance` di development, **900 adalah puing uji beban**
(`UJI0001`–`UJI0009`, masing-masing tepat 100 bawahan); data asli hanya 16 baris dari 4
approver. Konverter wajib menyaringnya, dan cakupan hasilnya jangan diukur dari angka
development apa adanya.

### R4 — Halaman PA belum punya baris di peta menu

Terukur: grup `KPI` (`Kode_Grup_Fitur = 'KPI'`, namanya *Key Performance Indicator*) punya
20 halaman terdaftar di `N_HRIS_Grup_Fitur_Map`. Belum satu pun untuk PA.

Halaman baru tanpa baris map plus `flushMenuCache` menghasilkan **403 untuk semua orang**,
termasuk pembuatnya. Ini bukan risiko besar, tapi selalu terlupa sampai halaman pertama
tidak bisa dibuka.

## 3. Yang bukan penghalang, meski terlihat begitu

**T05 (skala kanonik dan batas pita) hanya memblokir `N_PA_Result`, bukan sisanya.**
Bentuk penyimpanannya sudah dirancang tahan terhadap jawabannya: `Skor_Maksimal` disimpan
per butir dan dibaca dari data, dan satu payload berisi butir skala 5 dan skala 100
sekaligus sudah diuji diterima. Jadi kalau skalanya ternyata berbeda dari dugaan, yang
berubah **data**, bukan skema.

Yang benar-benar tertahan hanya tipe kolom nilai di `N_PA_Result` dan makna `Final_Score`.

## 4. Enam tabel yang tersisa

| Tabel | Menunggu |
|---|---|
| `N_PA_Result_Status` | tidak menunggu apa pun — master, bisa dibuat kapan saja |
| `N_PA_Result` | T05 |
| `N_PA_Result_Rater_Category` | bentuk `N_PA_Result` |
| `N_PA_Result_Competency` | bentuk `N_PA_Result` |
| `N_PA_Calibration_History` | bentuk `N_PA_Result` |
| `N_PA_Result_Note` | tidak menunggu apa pun secara teknis |

## 5. Urutan kerja yang disarankan

**Fase A — memadatkan yang sudah ada (bisa jalan sekarang).**
1. `PaParticipantRuleResolver` beserta ujinya. Dibangun lebih dulu supaya kontrak K32 punya
   penegak sejak baris kode pertama.
2. DML pengisian data acuan untuk lima tabel aturan yang angkanya sudah ada di deck.
3. Perintah verifikasi `pa:verify` yang membaca ulang keadaan: aturan yang kosong, peserta
   yang levelnya gagal resolusi, bobot yang tidak berjumlah 100.

**Fase B — lapisan hasil.** `N_PA_Result_Status` bisa didahulukan; lima sisanya menunggu
T05. Draf lengkapnya sudah ada di
`plans/kpi/2026-08-30-performance-appraisal-result-draft.md`.

**Fase C — aplikasi.** Rute, baris `N_HRIS_Grup_Fitur_Map` + `flushMenuCache`, lalu halaman
per mockup. Bagian 4 dan 5 dokumen desain masih kosong dan perlu diisi sebelum fase ini
dimulai.

## 6. Temuan sampingan: catatan modul di CLAUDE.md sudah usang

`CLAUDE.md` menyebut lima grup aktif — `ESS`, `KPI`, `LMS`, `VISITOR`, `SYSTEM` — dengan
catatan bahwa SOP adalah submodul `LMS`. Pembacaan langsung hari ini menunjukkan
**tujuh grup aktif**: bertambah `BUILDER` (1 halaman) dan `DMS` (14 halaman, landing
`menuSopSayaPage`).

Jadi SOP tampaknya sudah pindah dari `LMS` ke grup `DMS` tersendiri. `CLAUDE.md` sendiri
menyuruh memverifikasi ke database alih-alih memercayai snapshot bertanggalnya, dan inilah
kejadiannya. Tidak berdampak pada PA — grup `KPI` tidak berubah — tetapi catatan itu perlu
diperbarui agar sesi berikutnya tidak salah menempatkan berkas SOP.
