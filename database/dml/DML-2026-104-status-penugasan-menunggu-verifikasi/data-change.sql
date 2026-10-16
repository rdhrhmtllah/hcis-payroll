/* Change ID: DML-2026-104-status-penugasan-menunggu-verifikasi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada.
   Scope: N_PA_Assignment_Status — SATU baris baru.

   YANG DISELESAIKAN

   `N_PA_Assignment.Status_Penugasan` dijaga FOREIGN KEY ke tabel master ini
   (FK_PA_Assignment_Status_Master), bukan oleh CHECK constraint. Tanpa baris di bawah,
   setiap penulisan status MENUNGGU_VERIFIKASI ditolak database:

       The UPDATE statement conflicted with the FOREIGN KEY constraint
       "FK_PA_Assignment_Status_Master".

   Status itu dipakai callback penilaian saat HC Learn melaporkan Status_Skor =
   MENUNGGU_VERIFIKASI: penilai sudah mengirim, tetapi angkanya belum disahkan admin di
   sisi mereka. Keadaan ini sengaja BUKAN salah satu dari dua yang sudah ada — bukan
   SELESAI, karena nilainya belum boleh ikut dihitung; bukan BELUM_MULAI, karena orangnya
   sudah mengerjakan bagiannya dan menagihnya lagi lewat pengingat membuat pengingat
   kehilangan wibawanya.

   BAGAIMANA INI TERLEWAT

   Saat membangun fiturnya saya memeriksa `sys.check_constraints` pada N_PA_Assignment,
   tidak menemukan pembatas pada kolom status, lalu menyimpulkan status baru tidak butuh
   perubahan basis data. Pemeriksaan itu benar dan kesimpulannya salah: pembatasnya
   memang bukan CHECK melainkan FOREIGN KEY, dan keduanya tidak muncul di katalog yang
   sama. Yang menemukannya adalah uji rantai penuh — kegagalannya tidak pernah muncul di
   uji satuan mana pun karena tak satu pun dari mereka benar-benar menulis status itu ke
   basis data.

   URUTAN_ALUR 5, BUKAN DI ANTARA

   Nomor urut dipakai untuk mengurutkan tampilan, dan menyelipkannya di antara BERJALAN
   dan SELESAI berarti menggeser dua baris yang sudah ada — perubahan yang menyentuh data
   lain demi kerapian saja. Ditaruh di ujung: alurnya memang cabang, bukan tahap ketiga
   yang dilewati semua orang.

   IDEMPOTEN

   Dijaga NOT EXISTS pada Kode_Status. Bila barisnya sudah ada tetapi nonaktif, ia
   diaktifkan kembali.

   CARA MEMBATALKAN

       UPDATE N_PA_Assignment_Status SET Flag_Aktif = 'T'
       WHERE Kode_Status = 'MENUNGGU_VERIFIKASI';

   Jangan dihapus selama masih ada penugasan yang memakainya — foreign key akan
   menolaknya, dan itu memang penjagaan yang benar.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF NOT EXISTS (
    SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'MENUNGGU_VERIFIKASI'
)
BEGIN
    INSERT INTO N_PA_Assignment_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif)
    VALUES (
        'MENUNGGU_VERIFIKASI',
        N'Menunggu Verifikasi',
        N'Penilai sudah menyetorkan hasilnya, tetapi angkanya belum disahkan admin HC Learn. '
        + N'Nilainya BELUM ikut dihitung dan peserta belum terhitung lengkap; yang berhenti '
        + N'hanyalah pengingat, karena orangnya memang sudah mengerjakan bagiannya. Status '
        + N'ini berganti menjadi SELESAI saat kiriman FINAL datang, atau kembali ke '
        + N'BELUM_MULAI bila verifikasinya menolak.',
        5,
        'Y'
    );
END
ELSE
BEGIN
    UPDATE N_PA_Assignment_Status
    SET Flag_Aktif = 'Y'
    WHERE Kode_Status = 'MENUNGGU_VERIFIKASI' AND Flag_Aktif <> 'Y';
END;

COMMIT TRANSACTION;

/* VERIFIKASI — harus mengembalikan tepat satu baris, aktif 'Y'. */
SELECT Id_PA_Assignment_Status, Kode_Status, Nama_Status, Urutan_Alur, Flag_Aktif
FROM N_PA_Assignment_Status
WHERE Kode_Status = 'MENUNGGU_VERIFIKASI';
