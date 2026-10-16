-- Target: Web_HRIS (development). Expected affected rows: 1.
UPDATE N_EMI_VISITOR_Master_Durasi_Permit
SET Jumlah_Angka = 6
WHERE Id_Durasi_Permit = 3
  AND Nama_Durasi = '6 Bulan'
  AND Jumlah_Angka = 3
  AND Satuan_Waktu = 'MONTH';
