# Prefix Tabel Performance Appraisal — Keputusan dan Pelaksanaan

> **Status: SELESAI, 2026-08-30.** Dokumen ini semula adalah rencana yang
> mengusulkan database change rename terpisah. Usulan itu **tidak jadi dipakai**.
> Keputusan yang diambil dan sudah dilaksanakan adalah amandemen DDL-2026-064 di
> tempat. Bagian 1–3 mencatat hasil akhir; bagian 4 mencatat mengapa rencana lama
> dibatalkan, supaya keputusan yang sama tidak digali ulang.

## 1. Keputusan final

- Prefix tabel domain Performance Appraisal: **`N_PA_*`**.
- Prefix nama metadata (PK, FK, CHECK, DEFAULT, index): **`*_PA_*`**, bukan
  `*_HRIS_PA_*`.
- Istilah entitas tunggal: `Rater Policy` (sejak r3; sebelumnya `Policy`).
- Tabel PA berikutnya (Cycle, salinan beku, participant, assignment, hasil)
  memakai prefix yang sama.
- Tabel organisasi lintas modul **tetap** `N_HRIS_Organization_*`. Tabel itu bukan
  milik domain PA dan tidak ikut berubah.

Tidak ada `DDL-2026-065` rename. Tidak ada tabel lama yang tersisa, tidak ada
synonym, tidak ada view compatibility, dan tidak ada masa transisi.

## 2. Cara keputusan ini dilaksanakan

DDL-2026-064 berstatus `TRACKER_PENDING`: sudah jalan di development, tetapi belum
tercatat di SQL Tracker dan belum pernah menyentuh production. Karena itu berlaku
aturan **amend-before-tracker** di `AGENTS.md` — artifact-nya diperbaiki langsung,
bukan ditimpa oleh change korektif.

| Revisi | Isi |
|---|---|
| r1 | `N_HRIS_PA_Policy`, `N_HRIS_PA_Rater_Category`, `N_HRIS_PA_Policy_Detail` beserta nama metadata `*_HRIS_PA_*` |
| r2 | `N_PA_Policy`, `N_PA_Rater_Category`, `N_PA_Policy_Detail` beserta nama metadata `*_PA_*` |
| r3 | `N_PA_Rater_Policy`, `N_PA_Rater_Category`, `N_PA_Rater_Policy_Detail` — kata `Rater` ditambahkan setelah bobot pilar dipindah keluar dari Policy |

`forward.sql`, `drop.sql`, dan `data-change.sql` ditulis ulang setiap revisi,
checksum dihitung ulang, dan `docs/changes/DDL-2026-064-.../README.md` mencatat
riwayatnya. Production hanya akan menjalankan revisi terakhir, sehingga production
**tidak pernah** membuat nama r1 maupun r2.

Alasan r3: bobot KPI berbanding kompetensi diputuskan **tidak** disimpan di Policy,
melainkan di tabel siklus tersendiri, karena keduanya membeku di waktu yang berbeda
dan bobot pilar harus tetap bisa diubah lalu dihitung ulang setelah pengisian
selesai. Setelah bobot pilar keluar, isi tabel itu murni aturan penilai, sehingga
namanya diperjelas.

Development sudah terlanjur menjalankan r1, jadi nama fisiknya diselaraskan dengan
`sp_rename` saja — 36 objek dalam satu transaksi, tanpa `DROP`, tanpa `TRUNCATE`,
tanpa perubahan data:

| Jenis objek | Jumlah |
|---|---|
| Tabel | 3 |
| Primary key | 3 |
| Foreign key | 5 |
| Check constraint | 8 |
| Default constraint | 9 |
| Index non-clustered | 8 |

Penyelarasan itu **koreksi development, bukan artifact**. Ia sengaja tidak
disimpan di `database/ddl/` maupun `database/dml/`.

## 3. Bukti verifikasi

- Nol objek yang cocok `%HRIS[_]PA[_]%` tersisa di `sys.objects` dan `sys.indexes`.
- 5 foreign key dan 8 check constraint tetap `enabled` dan `trusted`.
- Jumlah kolom tetap 18 / 9 / 16.
- Empat baris seed kategori utuh, `Updated_At` masih `NULL` — bukti tidak ada baris
  yang tersentuh.
- Nol view/procedure/function yang definisinya menyebut nama lama.
- Nol berkas repository yang menyebut nama lama, selain catatan riwayat yang memang
  disengaja.
- **Bukti terkuat:** menjalankan ulang r2 apa adanya (7 batch DDL + 1 batch DML)
  berakhir no-op. Artinya development sekarang persis sama dengan yang akan
  dibangun r2 dari database kosong.

## 4. Mengapa rencana lama dibatalkan

Rencana semula mengusulkan database change rename terpisah, dengan alasan
"artifact 064 dan checksumnya adalah histori immutable". Alasan itu keliru untuk
kasus ini.

Immutability artifact baru bermakna **sejak artifact tercatat di SQL Tracker**.
Sebelum titik itu, artifact belum menjadi janji kepada siapa pun: production belum
melihatnya, tidak ada environment lain yang bergantung padanya, dan tidak ada
checksum yang sudah diserahkan untuk direview.

Menerapkan immutability terlalu dini justru merugikan:

- riwayat production dipaksa memuat langkah membuat nama yang salah lalu
  menggantinya, padahal production tidak pernah memakai nama itu;
- rantai change jadi berbelok — 064 membuat, 065 mengganti nama — untuk sesuatu
  yang bisa selesai dengan satu artifact yang benar;
- setiap change korektif menambah artifact, checksum, dan urutan eksekusi
  production yang harus dijaga selamanya.

Opsi B (tabel baru + salin data) dan Opsi C (synonym/view alias) tetap ditolak
dengan alasan yang sama seperti pada rencana semula: keduanya membuat dua sumber
kebenaran padahal belum ada satu pun consumer aplikasi.

## 5. Konsekuensi untuk pekerjaan berikutnya

- Model, service, controller, dan query PA menulis langsung ke `N_PA_*`. Tidak ada
  pemetaan transisi dan tidak ada nama lama yang perlu didukung.
- Setiap tabel PA baru wajib lahir dengan prefix `N_PA_*` dan nama metadata
  `*_PA_*` sejak artifact pertamanya.
- Selama 061–064 masih `TRACKER_PENDING`, koreksi desain lain pada keempat change
  itu juga boleh dilakukan lewat amandemen di tempat, bukan change korektif baru.
