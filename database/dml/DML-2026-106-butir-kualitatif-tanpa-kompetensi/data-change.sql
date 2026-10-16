/* Change ID: DML-2026-106-butir-kualitatif-tanpa-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-088 (catatan hasil berpangkal kompetensi). WAJIB sesudahnya.
   Scope: N_PA_Cycle_Question_Package_Item, N_PA_Assignment_Score — mengisi kolom yang
          tertinggal kosong pada baris yang sudah ada. Tidak ada baris baru, tidak ada
          baris yang dibuang.

   YANG DISELESAIKAN

   Enam belas butir uraian pada siklus percobaan lahir tanpa `PA_Competency_Id`. Butir
   yang tidak menunjuk kompetensi tidak bisa ditempatkan di panel mana pun: jawabannya
   tersimpan lengkap di `N_PA_Assignment_Score`, tetapi tidak pernah sampai ke report.

   Sampai DDL-2026-088, kekosongan itu ditutupi jalan pintas — perannya dicari lewat
   `PA_Result_Note_Type_Id` pada butirnya. Dan jalan pintas itu MENARUHNYA DI TEMPAT YANG
   SALAH, terbukti pada data ini sendiri:

       QL03 Initiative      -> jenis catatan 1 (Strength)
       QL04 Recommendation  -> jenis catatan 2 (Development Area)

   Jawaban tentang inisiatif seseorang tampil di report sebagai "Kekuatan", dan usulan
   tindak lanjut tampil sebagai "Area Pengembangan". Tidak ada galat; hanya kalimat yang
   benar di bawah judul yang salah.

   DICOCOKKAN LEWAT NAMA YANG TERSIMPAN, BUKAN AWALAN KODE

   `Kode_Soal` memang berbentuk `QL03-01-PEER`, dan memotong empat huruf pertamanya akan
   memberi jawaban yang benar untuk keenam belas baris ini. Itu tetap tidak dilakukan:
   bentuk kode itu kebiasaan katalog HC Learn, bukan aturan yang dijamin siapa pun, dan
   penambang awalan akan diam-diam salah pada katalog yang menomori soalnya dengan cara
   lain.

   Yang dipakai `Nama_Soal_Sumber` — nilai yang benar-benar tersimpan di barisnya, dan
   pada data ini sama persis dengan `Nama_Competency` ("Strength", "Development Area",
   "Initiative", "Recommendation"). Baris yang namanya tidak menemukan padanan TETAP
   KOSONG dan dilaporkan penjaga di akhir, bukan ditebak.

   PENUNJUK LAMANYA DIKOSONGKAN, DAN DI SINI ITU MEMANG BENAR

   Berbeda dari `N_PA_Result_Note` — yang penunjuk lamanya sengaja dibiarkan terisi sebagai
   jejak — di tabel ini nilainya DIKOSONGKAN bersamaan. Dua alasan. Pertama, nilainya
   terbukti salah: Initiative menunjuk "Strength". Menyimpan penunjuk yang salah sebagai
   "jejak" hanya menyimpan kekeliruan. Kedua, `CK_PA_Cycle_Question_Item_Peran` melarang
   satu butir memegang keduanya sekaligus, jadi mengisi kompetensinya tanpa mengosongkan
   yang lama akan ditolak basis data.

   URUTAN DUA LANGKAH

   Butirnya lebih dulu, baru jawabannya: langkah kedua membaca kompetensi dari butir yang
   baru diisi langkah pertama.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50540, 'DML-2026-106 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ------------------------------------------------------------------ *
 * 1. Butir paket                                                      *
 * ------------------------------------------------------------------ *
 * Hanya kompetensi KUALITATIF yang dipertimbangkan. Butir uraian tidak pernah menunjuk
 * kompetensi berskor, dan membuka padanan ke seluruh master berarti satu nama yang
 * kebetulan sama membuat butir uraian ikut dirata-ratakan sebagai angka.                */

UPDATE it
SET it.PA_Competency_Id = k.Id_PA_Competency,

    /* Dikosongkan bersamaan: nilainya keliru, dan `CK_PA_Cycle_Question_Item_Peran`
       melarang satu butir memegang kompetensi dan jenis catatan sekaligus. */
    it.PA_Result_Note_Type_Id = NULL
FROM N_PA_Cycle_Question_Package_Item it
JOIN N_PA_Competency k ON k.Nama_Competency = it.Nama_Soal_Sumber AND k.Flag_Aktif = 'Y'
JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = k.PA_Competency_Type_Id
WHERE it.PA_Competency_Id IS NULL
  AND it.Flag_Aktif = 'Y'
  AND t.Kode_Type = 'KUALITATIF'

  /* Nama yang dipakai LEBIH DARI SATU kompetensi aktif tidak dipetakan sama sekali:
     memilih salah satunya berarti menebak, dan tebakan yang salah tidak meninggalkan
     jejak apa pun di layar. */
  AND (
        SELECT COUNT(*)
        FROM N_PA_Competency k2
        JOIN N_PA_Competency_Type t2 ON t2.Id_PA_Competency_Type = k2.PA_Competency_Type_Id
        WHERE k2.Nama_Competency = it.Nama_Soal_Sumber
          AND k2.Flag_Aktif = 'Y'
          AND t2.Kode_Type = 'KUALITATIF'
      ) = 1;

/* ------------------------------------------------------------------ *
 * 2. Jawaban yang sudah masuk                                         *
 * ------------------------------------------------------------------ *
 * Dijangkarkan pada PAKET yang dipakai pintunya, bukan pada kode soal saja: kode soal
 * yang sama bisa hidup di beberapa paket dengan kompetensi berbeda, dan menjodohkannya
 * lintas paket akan memindahkan jawaban orang ke dimensi milik level lain.             */

UPDATE s
SET s.PA_Competency_Id = it.PA_Competency_Id
FROM N_PA_Assignment_Score s
JOIN N_PA_Assignment_Payload pl
    ON pl.Id_PA_Assignment_Payload = s.PA_Assignment_Payload_Id
JOIN N_PA_Assignment_Integration i
    ON i.Id_PA_Assignment_Integration = pl.PA_Assignment_Integration_Id
JOIN N_PA_Cycle_Question_Package_Item it
    ON it.PA_Cycle_Question_Package_Id = i.PA_Cycle_Question_Package_Id
   AND it.Kode_Soal = s.Kode_Soal_Sumber
   AND it.Flag_Aktif = 'Y'
WHERE s.PA_Competency_Id IS NULL
  AND s.Flag_Aktif = 'Y'
  AND it.PA_Competency_Id IS NOT NULL;

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ *
 * Yang tersisa kosong dilaporkan, TIDAK menggagalkan skrip. Butir berskala yang memang
 * belum dipetakan admin juga masuk hitungan itu, dan itu keadaan sah — pemetaannya
 * pekerjaan admin di layar master, bukan urusan skrip ini.                             */

DECLARE @butirKosong int = (
    SELECT COUNT(*) FROM N_PA_Cycle_Question_Package_Item
    WHERE Flag_Aktif = 'Y' AND PA_Competency_Id IS NULL
);

DECLARE @jawabanKosong int = (
    SELECT COUNT(*) FROM N_PA_Assignment_Score
    WHERE Flag_Aktif = 'Y' AND PA_Competency_Id IS NULL
);

PRINT CONCAT('butir aktif tanpa kompetensi tersisa: ', @butirKosong);
PRINT CONCAT('jawaban aktif tanpa kompetensi tersisa: ', @jawabanKosong);

/* VERIFIKASI — butir uraian beserta kompetensi yang kini ditunjuknya. */
SELECT p.PA_Cycle_Id, it.Kode_Soal, it.Nama_Soal_Sumber, k.Kode_Competency, k.Nama_Competency
FROM N_PA_Cycle_Question_Package_Item it
JOIN N_PA_Cycle_Question_Package p
    ON p.Id_PA_Cycle_Question_Package = it.PA_Cycle_Question_Package_Id
LEFT JOIN N_PA_Competency k ON k.Id_PA_Competency = it.PA_Competency_Id
WHERE it.Flag_Aktif = 'Y'
  AND it.Jenis_Soal = 'uraian'
ORDER BY p.PA_Cycle_Id, it.Kode_Soal;
