# DDL-2026-130-pa-rilis-hasil-ke-leader

## Metadata

- **Change ID**: `DDL-2026-130-pa-rilis-hasil-ke-leader`
- **Target Database**: `Web_HRIS`
- **Type**: DDL (tanpa pasangan DML)
- **Revision**: `r2`
- **Forward Checksum (SHA-256)**: `6c0aeca6a0da53454cdeac5c0effa107d3ef8e960c8daedcc688e41b2f17c0a7`
- **Checksum r1 (DIGANTIKAN, jangan dicatat)**: `576940f391106d7d12ca56aaf503ea169c951d8af3a3fbe4874ef1b1085b81ac`
- **Affected Objects**: `N_PA_Result (Published_Leader_At, Published_Leader_By,
  Unpublished_Leader_At, Unpublished_Leader_By, Alasan_Cabut_Leader,
  IX_PA_Result_Published_Leader)`, `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`

## Amandemen r2 — 22 September 2026

Dinomori ulang dari **DDL-2026-121** menjadi **DDL-2026-130**. Sebabnya bukan isi perubahannya,
melainkan penomorannya: `master` sudah memakai `DDL-2026-121` untuk
`visitor-pisah-tamu-regular-b2b`, sehingga sesudah cabang ini digabung ada dua artefak berbeda
bernomor sama — dan `Kode_Change` yang didaftarkan ke `N_SYSTEM_Database_Change` (`'DDL-2026-121'`)
akan menunjuk dua perubahan sekaligus.

Perubahan ini belum `TRACKER_RECORDED`, jadi diamandemen di tempat sesuai alur DDL. Checksum r1
di atas **tidak boleh dicatat** di SQL Tracker. Struktur di development tidak dibangun ulang —
kolom dan indeksnya sudah benar sejak r1; yang diperbarui hanya `Kode_Change`, `Artifact_Path`,
dan `Documentation_Path` pada satu baris pendaftaran.

## Deskripsi

Memisahkan dua hal yang selama ini dibuka satu tombol: **leader boleh melihat hasil timnya**
dan **karyawan boleh melihat hasilnya sendiri**. Sebelum perubahan ini, keduanya dijaga
penanda yang sama, `Status_Hasil = 'TERBIT'` — `PaLeaderService` memakainya untuk halaman PA
Tim, `PaPesertaService` untuk PA Saya. Akibatnya tidak ada cara memberi leader kesempatan
meninjau lebih dulu tanpa sekaligus memberitahu orang yang dinilai.

Sesudah perubahan ini, `Status_Hasil = 'TERBIT'` berarti satu hal saja: karyawan boleh
melihat. Rilis ke leader ditandai `Published_Leader_At`.

## Keputusan fisik

- **Kolom, bukan status baru.** `'TERBIT'` dibaca 35 tempat di 19 berkas (kurva, `pa:verify`,
  distribusi, kalibrasi, penerbitan). Menyisipkan `TERBIT_LEADER` di antara `DIHITUNG` dan
  `TERBIT` menuntut seluruh pembaca itu ditinjau ulang — pekerjaan besar dengan risiko regresi
  besar, untuk menjawab satu pertanyaan tambahan yang bisa dijawab satu kolom.
- **Aturan tampil leader: `Published_Leader_At IS NOT NULL` ATAU `Status_Hasil = 'TERBIT'`.**
  Bentuk "atau" inilah yang membuat siklus lama tidak perlu DML sama sekali: hasil yang sudah
  terbit tetap terlihat leader persis seperti kemarin.
- **Pencabutan bersebab.** `Unpublished_Leader_At/By` dan `Alasan_Cabut_Leader` ada karena
  mencabut akses yang sudah diberikan adalah tindakan yang kelak ditanyakan orang. Tanpa
  ketiganya, "kenapa leader tidak lagi melihat hasil ini" hanya bisa dijawab dengan menebak.
- **Tidak ada urutan yang dipaksakan.** Keputusan pemilik proses (21 September 2026): dua
  tombol bebas, admin boleh menerbitkan ke karyawan tanpa pernah merilis ke leader. Karena itu
  tidak ada CHECK yang menuntut penanda leader terisi lebih dulu.
- **Indeks tersaring** `IX_PA_Result_Published_Leader` — halaman PA Tim hanya membaca hasil
  aktif yang sudah dirilis, dan saringan `WHERE` membuat indeksnya sebesar populasi itu saja.

## Dampak aplikasi

- Gerbang baru `RILIS_LEADER` (`OPEN`, `CLOSED`, sama seperti `TERBITKAN`); untuk siklus
  bermetode `KURVA` tetap menuntut kurva terkunci, karena yang dibaca leader adalah grade.
- `PaResultPublishService`: `rilisKeLeader()`, `rilisKeLeaderPeserta()`, `cabutRilisLeader()`.
- `PaLeaderService`: dua tempat yang membandingkan status dengan `TERBIT` kini memakai aturan
  "atau" di atas.
- API: `POST /cycles/{id}/rilis-leader`, `POST /participants/{id}/rilis-leader`,
  `POST /participants/{id}/cabut-rilis-leader`.

## Pekerjaan agen

- [x] `forward.sql` dan `drop.sql` ditulis
- [x] Dijalankan di development `Web_HRIS`
- [x] Entri `database/ddl/status.json`

## Sisa pekerjaan pengguna

- [ ] Catat forward DDL apa adanya di SQL Tracker beserta checksum di atas
- [ ] Jalankan di production lewat SQL Tracker
- [ ] Verifikasi production
