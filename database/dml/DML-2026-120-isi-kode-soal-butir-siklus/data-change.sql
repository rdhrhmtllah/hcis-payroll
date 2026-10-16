/* Change ID: DML-2026-120-isi-kode-soal-butir-siklus
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-095 (kolom Kode_Soal_Sumber pada butir paket siklus).
   Scope: mengisi kode soal pada butir paket siklus yang SUDAH terlanjur tersalin tanpa
          kode, memakai master binding sebagai sumbernya.

   ── KENAPA PERLU ──────────────────────────────────────────────────────────────
   Kolomnya baru lahir di DDL-2026-095. Seluruh butir paket siklus yang tersalin sebelum
   itu berisi NULL, dan selama NULL kiriman callback HC Learn tidak bisa dipetakan: kunci
   yang mereka kembalikan (`K01`..`K13`) tidak punya pasangan di sisi kita. Siklus yang
   sedang berjalan tidak bisa menunggu paketnya disusun ulang.

   ── KUNCI PENGISIANNYA ────────────────────────────────────────────────────────
   Master binding menyimpan pasangan (ujian, Id_Jenis_Soal) -> Kode_Soal_Sumber. Butir
   siklus menyimpan `Id_Jenis_Soal` dan paketnya menyimpan `Id_Master_Ujian`, jadi
   pasangannya bisa ditemukan tanpa menebak. Yang diambil hanya baris master yang AKTIF.

   ── YANG SENGAJA TIDAK DILAKUKAN ──────────────────────────────────────────────
   Baris yang tidak menemukan pasangan DIBIARKAN NULL. Mengarangnya — misalnya menomori
   ulang K01, K02 menurut urutan tampil — akan memetakan nilai orang ke kompetensi yang
   belum tentu benar, dan kekeliruan itu tidak akan pernah memunculkan galat. Butir yang
   tetap kosong harus terlihat apa adanya di layar peninjauan kiriman.

   ── SIFATNYA ──────────────────────────────────────────────────────────────────
   Aman diulang: hanya menyentuh baris yang kodenya masih NULL.
*/

UPDATE i
SET i.Kode_Soal_Sumber = d.Kode_Soal_Sumber,
    i.Updated_At = SYSDATETIME()
FROM N_PA_Cycle_Question_Package_Item AS i
INNER JOIN N_PA_Cycle_Question_Package AS k
        ON k.Id_PA_Cycle_Question_Package = i.PA_Cycle_Question_Package_Id
INNER JOIN N_PA_Question_Binding_Master AS m
        ON m.Id_Master_Ujian = k.Id_Master_Ujian
       AND m.Flag_Aktif = 'Y'
INNER JOIN N_PA_Question_Binding_Master_Detail AS d
        ON d.PA_Question_Binding_Master_Id = m.Id_PA_Question_Binding_Master
       AND d.Id_Jenis_Soal = i.Id_Jenis_Soal
       AND d.Flag_Aktif = 'Y'
       AND d.Kode_Soal_Sumber IS NOT NULL
WHERE i.Kode_Soal_Sumber IS NULL;
GO

/* Laporan sesudah pengisian: berapa butir yang masih kosong, dan pada paket mana.
   Angka nol berarti seluruh siklus berjalan siap menerima callback. */
SELECT
    k.PA_Cycle_Id,
    k.Id_PA_Cycle_Question_Package,
    k.Id_Master_Ujian,
    COUNT(*) AS butir_tanpa_kode
FROM N_PA_Cycle_Question_Package_Item AS i
INNER JOIN N_PA_Cycle_Question_Package AS k
        ON k.Id_PA_Cycle_Question_Package = i.PA_Cycle_Question_Package_Id
WHERE i.Flag_Aktif = 'Y'
  AND k.Flag_Aktif = 'Y'
  AND i.Kode_Soal_Sumber IS NULL
GROUP BY k.PA_Cycle_Id, k.Id_PA_Cycle_Question_Package, k.Id_Master_Ujian
ORDER BY k.PA_Cycle_Id;
GO
