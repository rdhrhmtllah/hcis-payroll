/* Change ID: DDL-2026-127-visitor-kode-kartu-permit */
/*
    Wajib dijalankan SETELAH forward.sql.

    1. Isi Kode_Kartu untuk permit yang sudah ada. CRYPT_GEN_RANDOM adalah
       pembangkit acak kriptografis dan dievaluasi PER BARIS — sudah diperiksa
       di development; NEWID() sengaja tidak dipakai karena bukan CSPRNG.
       32 karakter heksadesimal = 16 byte = 128 bit, bentuk yang sama dengan
       yang dibangkitkan aplikasi (random_bytes).
    2. Pendaftaran ke Database Change Dashboard.

    Permit nonaktif dilewati: kartunya tidak pernah dicetak lagi.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Kode kartu untuk permit yang sudah ada ──────────────────────── */
UPDATE N_EMI_VISITOR_Transaksi_Permit
   SET Kode_Kartu = CONVERT(varchar(32), CRYPT_GEN_RANDOM(16), 2)
 WHERE Kode_Kartu IS NULL
   AND Flag_Aktif = 'Y';

/* ── 2. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-127-visitor-kode-kartu-permit')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-127-visitor-kode-kartu-permit', 'DDL', 'Kode acak kartu permit untuk QR keaslian', 'Kolom Kode_Kartu + indeks unik tersaring; QR tidak lagi memuat nomor permit dan tidak bergantung APP_KEY.', 'database/ddl/DDL-2026-127-visitor-kode-kartu-permit/forward.sql', 'docs/changes/DDL-2026-127-visitor-kode-kartu-permit/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-kode-kartu');
END;

COMMIT TRANSACTION;
