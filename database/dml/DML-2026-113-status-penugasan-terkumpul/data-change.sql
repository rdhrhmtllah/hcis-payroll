/* Change ID: DML-2026-113-status-penugasan-terkumpul
   Target: Web_HRIS development; production hanya lewat SQL Tracker.
   Dependencies: tidak ada.
   Scope: N_PA_Assignment_Status — SATU baris master baru.

   ------------------------------------------------------------------------------------
   KENAPA
   ------------------------------------------------------------------------------------

   Sejak 7 September 2026 penilai mengirim SELURUH tugasnya sekaligus: lembar yang selesai
   diisi berhenti di keadaan antara — terisi dan terpetakan utuh, tetapi belum dianggap
   selesai dan belum ikut dihitung — sampai seluruh tugasnya di siklus itu tuntas dan ia
   menekan kirim. Keadaan itu bernama TERKUMPUL, dan `PaRaterSubmitService` yang melepasnya
   menjadi SELESAI.

   `N_PA_Assignment.Status_Penugasan` BUKAN kolom teks bebas: ia foreign key ke
   `N_PA_Assignment_Status.Kode_Status` (FK_PA_Assignment_Status_Master). Tanpa barisnya di
   master, setiap penulisan TERKUMPUL ditolak basis data.

   ------------------------------------------------------------------------------------
   BENTUK KEGAGALANNYA, SUPAYA TIDAK TERULANG
   ------------------------------------------------------------------------------------

   Penolakannya tidak muncul sebagai galat yang menyebut sebabnya. Kiriman penilai masuk
   dalam dua langkah: payload disimpan lebih dulu, baru dipetakan. Yang gagal langkah kedua
   — jadi payload-nya SUDAH tersimpan dan berhasil, sementara penugasannya tetap
   BELUM_MULAI dan nol butir terpetakan. Di layar penilai lembar itu terbaca belum dinilai;
   ketika ia mengisi ulang, penjaga kiriman kembar menjawab "sudah pernah terkirim". Dua
   pesan yang saling bertentangan, dan tidak satu pun menyebut foreign key.

   Diperiksa saat merancang keadaan ini: CHECK constraint pada tabel penugasan — dan memang
   tidak ada. Yang tidak diperiksa adalah FOREIGN KEY-nya. Untuk kolom berisi kode, kedua
   jenis penjaga itu harus sama-sama ditanyakan sebelum menambah nilai baru.

   ------------------------------------------------------------------------------------
   KENAPA URUTAN 6, BUKAN 3
   ------------------------------------------------------------------------------------

   `Urutan_Alur` dipakai mengurutkan tampilan, bukan menyatakan urutan sebenarnya. Menyelipkan
   TERKUMPUL di antara BERJALAN dan SELESAI berarti menggeser nomor tiga baris yang sudah ada,
   dan nomor yang bergeser bisa dipegang layar atau laporan mana pun yang menyimpannya.
   Ditambahkan di ekor: penambahan yang tidak menyentuh apa pun yang sudah ada.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50593, 'DML-2026-113 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Idempoten: dijaga IF NOT EXISTS, aman dijalankan ulang. */
IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'TERKUMPUL')
    INSERT INTO N_PA_Assignment_Status
        (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif, Created_At)
    VALUES (
        'TERKUMPUL',
        'Terisi, Belum Dikirim',
        'Penilai sudah mengisi lembar ini sampai penuh dan jawabannya tersimpan utuh, '
        + 'tetapi ia belum mengirim seluruh tugasnya di siklus ini. Nilainya BELUM ikut '
        + 'dihitung dan pesertanya masih terbaca belum lengkap; pengingat pengisian berhenti '
        + 'karena pekerjaannya memang sudah dilakukan. Dilepas menjadi SELESAI oleh '
        + 'PaRaterSubmitService - lewat tombol kirim penilai, pelepasan paksa admin, atau '
        + 'penutupan pengisian siklus.',
        6,
        'Y',
        SYSDATETIME()
    );
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ */

IF NOT EXISTS (SELECT 1 FROM N_PA_Assignment_Status WHERE Kode_Status = 'TERKUMPUL' AND Flag_Aktif = 'Y')
    THROW 50594, 'TERKUMPUL tidak ada atau tidak aktif; penulisan status itu akan ditolak foreign key.', 1;

/* Lima status lama WAJIB tetap utuh. Sisipan yang diam-diam menimpa salah satunya baru
   terasa ketika satu jalur penugasan berhenti bisa menulis statusnya. */
IF (SELECT COUNT(*) FROM N_PA_Assignment_Status
    WHERE Kode_Status IN ('BELUM_MULAI','BERJALAN','SELESAI','DIBATALKAN','MENUNGGU_VERIFIKASI')) <> 5
    THROW 50595, 'Status penugasan lama tidak lagi lengkap.', 1;

/* VERIFIKASI */
SELECT Id_PA_Assignment_Status, Kode_Status, Nama_Status, Urutan_Alur, Flag_Aktif
FROM N_PA_Assignment_Status
ORDER BY Urutan_Alur;
