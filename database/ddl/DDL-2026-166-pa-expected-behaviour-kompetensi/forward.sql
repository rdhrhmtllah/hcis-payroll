/* Change ID: DDL-2026-166-pa-expected-behaviour-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-067 (N_PA_Competency).
   Scope: satu kolom pada `N_PA_Competency` — perilaku yang diharapkan (expected
          behaviour) dari setiap kompetensi.

   ── KENAPA PERUBAHAN INI ADA ──────────────────────────────────────────────────
   `Deskripsi` menjelaskan APA ARTI kompetensinya. Yang belum ada adalah PERILAKU YANG
   TERLIHAT bila kompetensi itu terpenuhi — patokan yang dibaca penilai, karyawan, leader,
   dan forum Balancing supaya "Analytical Thinking bernilai 80" berarti hal yang sama bagi
   semuanya. Tanpa itu setiap pembaca membawa definisi sendiri.

   ── KENAPA KAMUS, BUKAN SNAPSHOT PER SIKLUS (K26) ─────────────────────────────
   Sama dengan `Deskripsi`: kolom ini menyatakan ARTI, tidak menghitung apa pun — tidak
   masuk `N_PA_Result`, tidak menentukan grade, kuadran HAV, atau kelayakan. Menurut K26
   (README DDL-2026-067) ia milik kamus dan dikoreksi global. Bila suatu hari dipakai
   untuk memutuskan sesuatu, ia menjadi aturan dan wajib pindah ke snapshot per siklus
   lewat perubahan baru.

   ── BENTUK ISINYA ─────────────────────────────────────────────────────────────
   Teks biasa, SATU PERILAKU PER BARIS; layar memecahnya menjadi daftar butir. Bukan rich
   text: isinya dibaca di banyak layar (termasuk ponsel), dan butir polos tidak membawa
   gaya yang harus disaring. 2000 karakter cukup untuk ±10 butir yang lazim.

   ── KENAPA NULL-ABLE DAN TANPA INDEKS ─────────────────────────────────────────
   NULL berarti "belum ditulis HC" dan layar tidak menampilkan apa-apa. Kamus ini berisi
   puluhan baris yang selalu dibaca utuh; indeks tidak dimanfaatkan kueri mana pun.
*/

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Expected_Behaviour'
)
    ALTER TABLE N_PA_Competency
        ADD Expected_Behaviour nvarchar(2000) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-166'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-166',
        'DDL',
        'Expected behaviour pada kompetensi Performance Appraisal',
        'Deskripsi menjelaskan arti kompetensi; Expected_Behaviour mencatat perilaku yang terlihat bila kompetensi itu terpenuhi, satu perilaku per baris, supaya penilai, karyawan, leader, dan forum Balancing membaca patokan yang sama.',
        'database/ddl/DDL-2026-166-pa-expected-behaviour-kompetensi/forward.sql',
        'docs/changes/DDL-2026-166-pa-expected-behaviour-kompetensi/README.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'Kamus (N_PA_Competency), bukan snapshot per siklus, karena kolom ini menyatakan arti dan tidak menghitung apa pun. NULL berarti belum ditulis HC.',
        NULL
    );
END;
GO
