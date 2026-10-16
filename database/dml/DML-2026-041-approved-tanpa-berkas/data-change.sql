/* Change ID: DML-2026-041-approved-tanpa-berkas */

/* Penyelarasan tahap dengan alur berlangkah yang disetujui:

   1 Pengajuan        — rencana pengesah, identitas versi
   2 Review           — putaran review
   3 Pengesahan       — realisasi tanda tangan
   4 Terbit & Sebar   — unggah dokumen FINAL yang sudah sah

   Karena berkas final baru masuk langkah 4, APPROVED hanya menuntut realisasi
   pengesah. FILING_REGISTERED tetap menuntut berkas + pengesahan.

   Sebelumnya Approved menuntut keduanya, sehingga urutan nyata mustahil:
   admin harus mengunggah dokumen final sebelum pengesahan dinyatakan tuntas. */
UPDATE N_SOP_Master_Tahap_Versi
SET Butuh_Berkas = 'T', Butuh_Pengesahan = 'Y', Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'APPROVED';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-041-approved-tanpa-berkas')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-041-approved-tanpa-berkas', 'DML', 'Approved menuntut pengesahan, bukan berkas final', 'Berkas final baru dituntut pada Filing and Registered agar urutan nyata Pengesahan lalu Terbit & Sebar bisa ditempuh.', 'database/dml/DML-2026-041-approved-tanpa-berkas/data-change.sql', 'docs/changes/DML-2026-041-approved-tanpa-berkas/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'langkah-versi');
