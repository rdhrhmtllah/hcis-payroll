/* Change ID: DDL-2026-059-sop-kategori-boleh-kosong — PENGEMBALIAN
   JANGAN DIJALANKAN OLEH AGEN. Berkas ini alat bantu manual saja.

   Mengembalikan NOT NULL hanya mungkin bila TAK ADA satu pun baris yang
   kolomnya sudah terlanjur NULL. Sesudah paket baru dibuat tanpa kategori,
   pengembalian ini AKAN GAGAL — dan itu memang seharusnya: mengembalikannya
   dengan cara mengisi paksa nilai palsu akan menyatakan kategori yang tak
   pernah dipilih siapa pun.

   Periksa lebih dulu:

     SELECT COUNT(*) FROM dbo.N_SOP_Master_Paket WHERE Id_Kategori_SOP IS NULL;
     SELECT COUNT(*) FROM dbo.N_SOP_Paket_Draft  WHERE Id_Kategori_SOP IS NULL;
*/

ALTER TABLE dbo.N_SOP_Master_Paket
    ALTER COLUMN Id_Kategori_SOP INT NOT NULL;
GO

ALTER TABLE dbo.N_SOP_Paket_Draft
    ALTER COLUMN Id_Kategori_SOP INT NOT NULL;
GO
