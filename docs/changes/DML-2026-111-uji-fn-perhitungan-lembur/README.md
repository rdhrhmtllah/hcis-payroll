# DML-2026-111 — Data uji `N_HRIS_FN_CalculateOvertime`

Data uji di database **pengembangan** `Web_HRIS` untuk membuktikan setiap cabang
fungsi `dbo.N_HRIS_FN_CalculateOvertime` melalui jalur produksi yang sebenarnya,
yaitu `dbo.HRIS_SP_GET_ABSEN_NEW_V3`. **Bukan** perubahan skema dan **tidak** untuk
dijalankan di produksi.

## Jalur yang diuji

`Transaksi_Lembur` (header, `Status IS NULL`) → `Transaksi_Lembur_Detail`
(`Jenis`, `Tanggal_Lembur_Dari/Sampai` = estimasi) → `Transaksi_Lembur_Det`
(`Urut_Detail` = `Urut_Oto` detail, `Tanggal_Lembur` = tanggal kerja) →
`CHECKINOUT` (`USERID` = `Karyawan.UserID_Absen`, `Hasil` `In`/`Out`,
`Untuk_Tanggal` diisi eksplisit).

SP memanggil fungsi dua kali per hari: sekali untuk `LEMBUR`/`LEMBUR_LIBUR`
(hasil masuk kolom `Overtime`) dan sekali dengan jenis `LEMBUR_AWAL`
(hasil masuk kolom `Overtime_Before`). Keduanya dalam **menit**, mentah, tidak
diubah lagi setelah baris 944 SP.

## Pemilihan tanggal dan karyawan

- **2026-09-14** (Senin) — `StatusHari = WORK`, shift `08:30–16:30` untuk 31
  karyawan aktif perusahaan `001`. Dipakai skenario `LEMBUR` dan `LEMBUR_AWAL`.
- **2026-09-13** (Minggu) — `StatusHari = OFF-Default_Shift`. Dipakai skenario
  `LEMBUR_LIBUR`.
- `CHECKINOUT` di dev praktis kosong pada rentang ini (baris terakhir 2026-09-10),
  dan ke-18 karyawan terpilih tidak punya transaksi lembur pada dua tanggal itu.
- Satu karyawan = satu skenario, supaya tiap baris keluaran SP memetakan persis ke
  satu cabang fungsi.

## Nomor transaksi

`LM09-26-0001` … `LM09-26-0004`. Prefix `LM09-26-` belum ada di `HRIS_Counter`,
jadi `TransactionNumberService` akan menyemai dirinya dari
`MAX(No_Transaksi) = 0004` saat submit lembur nyata pertama bulan ini dan lanjut ke
`0005` — tidak bentrok.

## Cara memverifikasi

Jalankan `database/dml/DML-2026-111-uji-fn-perhitungan-lembur/verify.sql` (read-only).
Skrip itu memanggil SP, menampung hasilnya di variabel tabel, lalu mengeluarkan tiga
tabel: ringkasan lulus/gagal, perbandingan per skenario, dan konteks mentah.

Dua catatan teknis yang membuat `INSERT INTO @Hasil EXEC ...` bisa jalan:

- result set SP punya **95 kolom**, bukan 94 — kolom terakhir tidak punya alias dan
  isinya `@UserID_Cetak` apa adanya, jadi tetap harus dideklarasikan;
- urutan kolom result set **berbeda** dari urutan `#Table_Absen` di dalam SP, dan ada
  kolom tambahan (`nama_divisi`, `nama_sub_divisi`, `nama_level`, `nama_jabatan`) yang
  tidak ada di tabel temporary itu. Bentuk `@Hasil` mengikuti result set, bukan
  `#Table_Absen`.

Untuk sekadar melihat keluaran SP apa adanya, ada blok `EXEC POLOS` di akhir berkas.

## Hasil verifikasi (2026-09-16, `Web_HRIS`)

`EXEC dbo.HRIS_SP_GET_ABSEN_NEW_V3 '001','2026-09-13','2026-09-14', <18 UserID_Absen>, NULL, 'UJI_FN'`
→ **18 skenario, 18 lulus, 0 gagal.**

| # | Karyawan | Tgl | Jenis | Estimasi | CheckIn | CheckOut | Kolom | Harap | Nyata |
|---|---|---|---|---|---|---|---|---|---|
| A1 | D12 | 14 | LEMBUR | 16:30–19:30 | 08:25 | 19:30 | Overtime | 180 | 180 |
| A2 | F20 | 14 | LEMBUR | 16:30–19:30 | 08:25 | 18:45 | Overtime | 135 | 135 |
| A3 | F22 | 14 | LEMBUR | 16:30–19:30 | 08:25 | 21:00 | Overtime | 180 | 180 |
| A4 | F23 | 14 | LEMBUR | 17:30–20:30 | 08:25 | 20:30 | Overtime | 180 | 180 |
| A5 | F40 | 14 | LEMBUR | 16:30–19:30 | 08:25 | 16:00 | Overtime | 0 | 0 |
| A6 | G25 | 14 | LEMBUR | 16:30–19:30 | 08:25 | — | Overtime | 0 | 0 |
| B1 | H33 | 14 | LEMBUR_AWAL | 06:30–08:30 | 06:30 | 16:35 | Overtime_Before | 120 | 120 |
| B2 | I27 | 14 | LEMBUR_AWAL | 06:30–08:30 | 07:10 | 16:35 | Overtime_Before | 80 | 80 |
| B3 | J31 | 14 | LEMBUR_AWAL | 06:30–09:00 | 06:30 | 16:35 | Overtime_Before | 120 | 120 |
| B4 | M37 | 14 | LEMBUR_AWAL | 06:30–08:30 | 08:45 | 16:35 | Overtime_Before | 0 | 0 |
| C1 | N42 | 13 | LEMBUR_LIBUR | 08:00–12:00 | 08:00 | 12:00 | Overtime | 240 | 240 |
| C2 | N43 | 13 | LEMBUR_LIBUR | 08:00–12:00 | 08:30 | 12:00 | Overtime | 210 | 210 |
| C3 | R46 | 13 | LEMBUR_LIBUR | 08:00–12:00 | 08:00 | 14:00 | Overtime | 360 | 360 |
| C4 | R47 | 13 | LEMBUR_LIBUR | 10:00–14:00 | 07:00 | 09:00 | Overtime | 0 | 0 |
| D1 | R48 | 14 | LEMBUR | 16:30–19:30 | 17:00 | 16:50 | Overtime | 0 | 0 |
| D2 | R49 | 14 | LEMBUR | 19:30–16:30 | 08:25 | 20:00 | Overtime | 0 | 0 |
| D3 | R50 | 13 (OFF) | LEMBUR | 08:00–12:00 | 08:00 | 12:00 | Overtime | 0 | 0 |
| D4 | R52 | 14 (WORK) | LEMBUR_LIBUR | 16:30–19:30 | 08:25 | 19:30 | Overtime | 0 | 0 |

### Yang dibuktikan per kelompok

- **A** — `LEMBUR` mulai dari `MAX(Estimasi_Mulai, Temp_JamKeluar)` dan berhenti di
  `MIN(CheckOut, Estimasi_Selesai)`: pulang lebih awal dibayar aktual (A2), pulang
  lebih lama diplafon estimasi (A3), jeda antara jam keluar shift dan estimasi mulai
  tidak dibayar (A4).
- **B** — `LEMBUR_AWAL` mulai dari `MAX(CheckIn, Estimasi_Mulai)` dan berhenti di
  `MIN(Estimasi_Selesai, Temp_JamMasuk)`: datang telat memotong dari depan (B2),
  estimasi yang melewati jam masuk dipotong di jam masuk (B3).
- **C** — `LEMBUR_LIBUR` **full aktual**: C3 membuktikan tidak ada plafon di sisi
  `Estimasi_Selesai` (checkout 14:00 atas estimasi 12:00 tetap dihitung 360 menit).
- **D** — penjaga: `CheckOut <= CheckIn`, estimasi terbalik, dan pasangan
  jenis/status hari yang tidak cocok semuanya jatuh ke `0`.

## Catatan

`Koefisien_OT` **bukan** keluaran fungsi ini. Contoh D12: `Overtime` = 180 menit,
`Overtime_Real` = 180, `Koefisien_OT_Gross` = 3 jam, tapi `Koefisien_OT` = 1 — angka itu
dipotong oleh blok plafon jam lembur per hari di SP (`OVERTIME_MAX_HOUR_DAY` /
`Max_Hours_Workday`), jauh setelah fungsi selesai. Bandingkan `Overtime` /
`Overtime_Before` kalau yang ingin diuji adalah fungsinya.

`PK_CHECKINOUT` adalah `(USERID, CHECKTIME)`, sehingga skenario D1 tidak bisa memakai
dua cap waktu identik. `CheckOut` dibuat lebih awal dari `CheckIn` (16:50 vs 17:00),
yang tetap menguji cabang `@CheckOut <= @CheckIn`.

## Pembersihan

Blok `ROLLBACK / PEMBERSIHAN` berkomentar di akhir
`database/dml/DML-2026-111-uji-fn-perhitungan-lembur/data-change.sql`. Jangkarnya
`CHECKINOUT.Memoinfo = 'UJI-FN-LEMBUR'` dan keempat `No_Transaksi` di atas.
