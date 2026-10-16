/* MANUAL ONLY. Never execute this file automatically outside the
   development rebuild exception in AGENTS.md.
   Urutan drop mengikuti ketergantungan FK. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50139, 'Manual reset 067 hanya ditujukan untuk Web_HRIS.', 1;

/* r2: forward.sql tidak lagi membuat tabel ini, jadi ia bukan lagi milik change ini.
   Baris DROP-nya SENGAJA DIPERTAHANKAN — database yang terlanjur memilikinya dari revisi
   r1 (termasuk dev per 1 September 2026) harus tetap bisa dibersihkan lewat jalur yang
   sama. Membuang baris ini akan meninggalkan tabel yatim yang tidak dimiliki artefak mana
   pun, dan yatim seperti itu tidak akan pernah ditemukan lagi. */
IF OBJECT_ID('N_PA_Competency_Kode_Soal', 'U') IS NOT NULL
    DROP TABLE N_PA_Competency_Kode_Soal;

IF OBJECT_ID('N_PA_Competency_Requirement', 'U') IS NOT NULL
    DROP TABLE N_PA_Competency_Requirement;

IF OBJECT_ID('N_PA_Competency', 'U') IS NOT NULL
    DROP TABLE N_PA_Competency;

IF OBJECT_ID('N_PA_Competency_Type', 'U') IS NOT NULL
    DROP TABLE N_PA_Competency_Type;

IF OBJECT_ID('N_PA_Score_Range', 'U') IS NOT NULL
    DROP TABLE N_PA_Score_Range;
