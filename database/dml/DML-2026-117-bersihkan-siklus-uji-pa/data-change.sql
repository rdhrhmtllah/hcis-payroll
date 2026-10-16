/*
| DML-2026-117 — Bersihkan siklus uji PA sebelum DDL-2026-090
|
| KENAPA
|
| Kontrak paket soal HC Learn berubah: bagian soal kini dilempar tanpa label kompetensi,
| dan pemetaannya berpindah menjadi milik HCIS. DDL-2026-090 menuntut dua indeks unik baru
| pada N_PA_Cycle_Question_Package_Item — satu bagian tepat satu kompetensi.
|
| Data siklus yang ada TIDAK BISA memenuhi invarian itu, dan memang tidak seharusnya bisa:
| pada kontrak lama satu kompetensi diukur BEBERAPA soal. Terukur di development pada
| 9 September 2026: 160 grup (360 baris) dengan PA_Competency_Id rangkap dalam satu paket.
|
| KENAPA SEKALIAN SIKLUSNYA, BUKAN PAKETNYA SAJA
|
| Menghapus paketnya saja ditolak FK_PA_Assignment_Integration_Package — seluruh 7.308 baris
| penjadwalan menunjuk salah satu paket itu. Dan mengosongkan butirnya saja menyisakan 49
| paket kosong yang JUSTRU menghalangi perbaikannya sendiri: salinKeSiklus() melewati sel
| yang "sudah terisi", sehingga siklus itu tak akan pernah bisa diisi ulang lewat layar mana
| pun.
|
| Kelima siklusnya dibuat 7 September 2026 oleh perintah semaian, dua hari sebelum perubahan
| ini. Tidak ada penilaian sungguhan di dalamnya.
|
| BISA DIBANGUN ULANG
|
| `php artisan pa:seed-skenario` dan `php artisan pa:demo-cycle` membangun kembali siklus uji
| beserta peserta, penugasan, dan hasilnya — dengan bentuk yang SUDAH mengikuti kontrak baru.
|
| HANYA UNTUK DEVELOPMENT. Di production, periksa lebih dulu apakah tabel-tabel ini benar
| berisi data uji. Bila production sudah memuat siklus sungguhan, perubahan ini TIDAK BOLEH
| dijalankan apa adanya — datanya harus dimigrasikan, bukan dihapus.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ---------------------------------------------------------------
 | Urutan anak → induk. Dibalik, tiap langkah ditolak FK-nya sendiri.
 --------------------------------------------------------------- */

/* Hasil dan turunannya */
DELETE FROM N_PA_Calibration_History;          -- 3
DELETE FROM N_PA_Result_Competency;            -- 940
DELETE FROM N_PA_Result_Note;                  -- 296
DELETE FROM N_PA_Result_Rater_Category;        -- 34
DELETE FROM N_PA_Result;                       -- 4.790

/* Penugasan dan turunannya. Integration memegang token HC Learn; tokennya menunjuk jadwal
   yang paketnya ikut dihapus di bawah, jadi ia tidak bisa ditinggalkan sendirian. */
DELETE FROM N_PA_Assignment_Score;             -- 2.401
DELETE FROM N_PA_Assignment_Payload;           -- 105
DELETE FROM N_PA_Assignment_Integration;       -- 7.308
DELETE FROM N_PA_Assignment;                   -- 7.308

/* Paket soal siklus — inilah yang benar-benar dituntut DDL-2026-090 */
DELETE FROM N_PA_Cycle_Question_Package_Item;  -- 1.160
DELETE FROM N_PA_Cycle_Question_Package;       -- 49

/* Peserta beserta penyimpangannya */
DELETE FROM N_PA_Participant_Pillar_Override;       -- 0
DELETE FROM N_PA_Participant_Rater_Override;        -- 0
DELETE FROM N_PA_Participant_Requirement_Override;  -- 0
DELETE FROM N_PA_Participant;                       -- 4.790

/* Salinan aturan milik siklus. Ini SNAPSHOT, bukan master — masternya
   (N_PA_Competency_Requirement, N_PA_Rater_Policy, N_PA_Score_Range, N_PA_Pillar_Weight)
   TIDAK disentuh sama sekali. */
DELETE FROM N_PA_Cycle_Competency_Requirement_Snapshot;  -- 70
DELETE FROM N_PA_Cycle_Pillar_Weight_Snapshot;           -- 30
DELETE FROM N_PA_Cycle_Rater_Policy_Detail_Snapshot;     -- 100
DELETE FROM N_PA_Cycle_Rater_Policy_Snapshot;            -- 30
DELETE FROM N_PA_Cycle_Score_Range_Snapshot;             -- 60

/* Penyiapan siklus */
DELETE FROM N_PA_Cycle_Pre_Exclusion;          -- 2.847
DELETE FROM N_PA_Cycle_Setup_Step;             -- 13
DELETE FROM N_PA_Cycle;                        -- 5

/* Ikatan paket soal MASTER. Seluruh 45 barisnya berkode SIM-* dan mengacu kontrak
   kode-soal yang sudah tidak berlaku; dibiarkan, mereka tampak seperti ikatan sah yang
   kebetulan tak punya detail. Masternya diikat ulang lewat layar setelah DDL-2026-090. */
DELETE FROM N_PA_Question_Binding_Master;      -- 45

COMMIT TRANSACTION;
