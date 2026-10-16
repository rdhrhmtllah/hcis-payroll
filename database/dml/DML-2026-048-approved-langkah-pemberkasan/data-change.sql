/*  DML-2026-048 — Tahap "Disahkan" kembali hidup, kali ini di langkah 4
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Urutan : WAJIB sesudah DML-2026-047. Skrip menolak jalan bila masih ada
             versi — lihat penjaga di bawah.

    MASALAH YANG DIPECAHKAN
    Wizard versi yang baru mengunci langkah di depan tahap berjalan. Dengan
    susunan sekarang, langkah 4 (Pemberkasan) tak akan PERNAH terbuka: satu-
    satunya jalan maju dari APPROVAL adalah FILING_REGISTERED (menerbitkan),
    sedangkan berkas final yang jadi syarat terbit justru diisi DI langkah 4.
    Buntu.

    KENAPA BUKAN SEKADAR MEMBATALKAN DML-2026-042
    042 mematikan APPROVED dengan alasan yang benar: APPROVAL dan APPROVED
    sama-sama duduk di LANGKAH 3, sehingga admin menekan "Simpan & Lanjut"
    dua kali untuk satu langkah. Change ini TIDAK mengembalikan bentuk itu.
    APPROVED dipetakan ke LANGKAH 4 (Kelompok TERBIT), sehingga tetap berlaku
    satu tombol lanjut per langkah:

        Pengajuan  -> Review  -> Pengesahan -> Pemberkasan -> (terbit)
        DRAFTING      REVIEW     APPROVAL      APPROVED       FILING_REGISTERED
        langkah 1     langkah 2  langkah 3     langkah 4      langkah 4

    Keputusan 042 yang lain — HOLD dibuang, syarat CATATAN_REVIEW dibuang,
    satu gerbang di Filing — TETAP BERLAKU dan tidak disentuh change ini.

    DUA BAGIAN KODE SUDAH MENANTIKAN SUSUNAN INI
      SopTahapVersiService: `if ($kini->Kode_Tahap === 'APPROVED') return 4;`
      SopDokumenService   : "realisasi pengesah sudah dituntut untuk masuk Approved"

    BARIS TERDUGA
      N_SOP_Master_Tahap_Versi           1 UPDATE
      N_SOP_Master_Tahap_Versi_Transisi  4 UPDATE + 1 INSERT
      N_SOP_Master_Tahap_Versi_Syarat    1 INSERT
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

/* ── Penjaga urutan ───────────────────────────────────────────────────────
   Memindahkan tahap ke langkah lain saat masih ada versi hidup berarti
   memigrasi keadaan tanpa rencana migrasi: versi yang berdiri di APPROVED
   akan mendadak berpindah langkah tanpa ada yang menyatakannya. Dijalankan
   sesudah 047, jumlah versi nol dan seluruh persoalan itu lenyap. */
IF (SELECT COUNT(*) FROM N_SOP_Item_Versi) > 0
BEGIN
    ROLLBACK TRAN;
    THROW 50048, 'Masih ada baris di N_SOP_Item_Versi. Jalankan DML-2026-047 lebih dulu.', 1;
END

DECLARE @approval int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'APPROVAL');
DECLARE @approved int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'APPROVED');
DECLARE @filing   int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'FILING_REGISTERED');
DECLARE @cancel   int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'CANCEL');

IF @approval IS NULL OR @approved IS NULL OR @filing IS NULL OR @cancel IS NULL
BEGIN
    ROLLBACK TRAN;
    THROW 50048, 'Salah satu tahap acuan tak ditemukan di master.', 1;
END

/* ── 1. Tahap APPROVED: hidup, di langkah 4, bernama Pemberkasan ──────────
   Butuh_Pengesahan tetap 'Y' (DML-2026-041): tak ada versi yang bisa disebut
   disahkan tanpa pengesah. Butuh_Berkas tetap 'T': berkas final justru diisi
   SESUDAH masuk langkah ini.

   Cap_Tanggal dikosongkan. Sebelumnya ia mencap Tanggal_Akhir_Approval —
   kolom yang sudah dicap APPROVAL saat masuk Pengesahan, sehingga pencapan
   kedua tak pernah menulis apa pun (capTanggal hanya mengisi yang NULL).
   Dengan NULL, tanggal langkah 4 jatuh ke Tanggal_Publish, dan itulah yang
   memang ingin dibaca orang dari langkah Pemberkasan. */
UPDATE N_SOP_Master_Tahap_Versi
   SET Flag_Aktif  = 'Y',
       Langkah     = 4,
       Kelompok    = 'TERBIT',
       Nama_Tahap  = N'Pemberkasan',
       Deskripsi   = N'Sudah disahkan. Berkas final disiapkan, lalu versi diterbitkan.',
       Cap_Tanggal = NULL,
       Updated_At  = SYSDATETIME()
 WHERE Kode_Tahap = 'APPROVED';

/* ── 2. Transisi yang barisnya sudah ada, tinggal dihidupkan ────────────── */
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif = 'Y'
 WHERE Dari_Tahap_Id = @approval AND Ke_Tahap_Id = @approved;   -- maju ke Pemberkasan
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif = 'Y'
 WHERE Dari_Tahap_Id = @approved AND Ke_Tahap_Id = @filing;     -- terbit
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif = 'Y'
 WHERE Dari_Tahap_Id = @approved AND Ke_Tahap_Id = @cancel;     -- batal di titik keputusan

/* ── 3. Terbit kini HANYA dari Pemberkasan ────────────────────────────────
   Membiarkan APPROVAL -> FILING_REGISTERED hidup berarti ada dua jalan
   menerbitkan, dan yang satu melewati langkah tempat berkas finalnya
   dipasang. */
UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif = 'T'
 WHERE Dari_Tahap_Id = @approval AND Ke_Tahap_Id = @filing;

/* ── 4. Mundur satu langkah: barisnya belum pernah ada ────────────────────
   APPROVED -> REVIEW sengaja DIBIARKAN MATI: mundur dua langkah sekaligus
   melewati Pengesahan tanpa meninggalkan sebab kenapa pengesahannya batal. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi
               WHERE Dari_Tahap_Id = @approved AND Ke_Tahap_Id = @approval)
    INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif)
    VALUES (@approved, @approval, 'Y');

/* ── 5. Syarat masuk Pemberkasan ──────────────────────────────────────────
   Baris generik menggantikan jalur cadangan Butuh_Pengesahan. Begitu barisnya
   ada, ia sepenuhnya berwenang — dan checklist yang dibaca layar menjadi
   daftar yang sama persis dengan yang menegakkan transisi.

   FILING_REGISTERED tetap menuntut BERKAS + REALISASI_PENGESAH; tak disentuh. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat
               WHERE Tahap_Id = @approved AND Kode_Syarat = 'REALISASI_PENGESAH')
    INSERT INTO N_SOP_Master_Tahap_Versi_Syarat (Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif)
    VALUES (@approved, 'REALISASI_PENGESAH', N'Realisasi pengesah', 1, 'Y');

/* ── Verifikasi ───────────────────────────────────────────────────────── */
SELECT Kode_Tahap, Nama_Tahap, Flag_Aktif, Langkah, Kelompok, Cap_Tanggal
  FROM N_SOP_Master_Tahap_Versi ORDER BY Urutan;

SELECT d.Kode_Tahap AS Dari, k.Kode_Tahap AS Ke, t.Flag_Aktif
  FROM N_SOP_Master_Tahap_Versi_Transisi t
  JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = t.Dari_Tahap_Id
  JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = t.Ke_Tahap_Id
 ORDER BY d.Urutan, k.Urutan;

COMMIT TRAN;
