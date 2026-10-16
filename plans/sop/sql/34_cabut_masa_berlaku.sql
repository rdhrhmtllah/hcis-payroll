/* =====================================================================
   34 — Pencabutan konsep MASA BERLAKU dokumen SOP
   =====================================================================

   KEPUTUSAN PRODUK 15 Agu 2026: sebuah SOP berlaku sampai ada revisi versi
   baru. Tak ada lagi "kadaluarsa" maupun "review berikutnya".

   Yang sudah dicabut di kode:
     * SopRecertificationService + command sop:recert-scan + cabang RECERT
       pada RunSopAutomation — mesin yang membuka ulang kewajiban baca ketika
       tanggal kadaluarsa lewat;
     * jalur buka-ulang berbasis kadaluarsa di SopReconciliationService;
     * lima dari tujuh ember Register Kontrol Dokumen (KADALUARSA,
       AKAN_KADALUARSA, REVIEW_LEWAT, REVIEW_DEKAT, TANPA_MASA_BERLAKU);
       yang tersisa TANPA_PENGESAHAN dan RENDER_GAGAL;
     * seluruh isian & tampilan kedua tanggal itu di Builder, Master Dokumen,
       panel versi, monitoring, dan halaman karyawan;
     * skenario UAT-SOP-046 dan UAT-SOP-048.

   YANG WAJIB DIJALANKAN DI SINI
   -----------------------------
   Satu baris scheduler masih menunjuk command yang sudah TIDAK ADA. Selama ia
   Flag_Aktif = 'Y', penjadwal akan mencoba menjalankannya tiap malam pukul
   01:15 dan gagal dengan galat "command not found" — bising tanpa guna, dan
   menutupi galat lain di log yang sama.

   KOLOMNYA SENGAJA TIDAK DIJATUHKAN. N_SOP_Item_Versi.Tanggal_Kadaluarsa
   (6 baris terisi) dan Tanggal_Review_Berikutnya (1 baris) adalah tanggal yang
   pernah dinyatakan sah oleh manusia. Kode berhenti membaca dan menulisnya;
   menjatuhkan kolomnya menghapus catatan itu selamanya tanpa bisa dikembalikan,
   dan tak ada yang didapat sebagai gantinya. Hal yang sama berlaku untuk kolom
   kembarannya di N_SOP_Paket_Draft_Item_Versi (0 baris terisi).

   AMAN DIJALANKAN BERULANG.
   ===================================================================== */

IF EXISTS (
    SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs
    WHERE Command = 'sop:recert-scan' AND Flag_Aktif = 'Y'
)
BEGIN
    UPDATE dbo.N_HRIS_Scheduler_Configs
       SET Flag_Aktif  = 'T',
           Keterangan  = 'DICABUT 15 Agu 2026 — konsep masa berlaku dokumen dihapus; '
                       + 'command sop:recert-scan sudah tidak ada.'
     WHERE Command = 'sop:recert-scan';

    PRINT 'Jadwal sop:recert-scan dinonaktifkan.';
END
ELSE
    PRINT 'Jadwal sop:recert-scan sudah nonaktif atau tidak ada — dilewati.';
GO
