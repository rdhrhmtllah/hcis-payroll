/* =====================================================================
   DDL-2026-118-rekap-skema-production -- DML

   TIDAK ADA DML UNTUK PERUBAHAN INI.

   Dua puluh dua dari dua puluh tiga kolom nullable tanpa default, dan NULL
   adalah keadaan awal yang benar: baris lama memang tidak punya nilainya,
   dan menebaknya akan memalsukan data.

   Satu kolom terisi otomatis oleh DEFAULT-nya:
     N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role  NOT NULL DEFAULT 'Y'
     -> 75 baris lama di production otomatis 'Y', artinya perilaku hibah role
        yang berlaku sekarang dipertahankan apa adanya. Menandai pengecualian
        dengan 'T' adalah keputusan konfigurasi tersendiri.

   Tipe_Tamu berdefault 'REGULAR' tetapi nullable, jadi baris lama tetap NULL
   dan hanya baris baru yang mengambil default. Kedua tabelnya bagaimanapun
   berisi 0 baris di production per 2026-09-16.

   Seed data yang memang dibutuhkan ada di perubahan terpisah:
     DML-2026-115-seed-sop-prioritas  (3 baris master prioritas SOP)

   Berkas ini sengaja tidak berisi pernyataan apa pun.
   ===================================================================== */

-- (tanpa pernyataan)
