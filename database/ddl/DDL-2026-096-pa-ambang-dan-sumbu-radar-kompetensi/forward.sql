/* Change ID: DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-067 (N_PA_Competency, Flag_Berskor), DDL-2026-081
                 (N_PA_Result_Competency — nilai agregat yang digambar radar).
   Scope: dua kolom pada `N_PA_Competency` — ambang nilai (KKM) per kompetensi, dan
          penanda kompetensi mana yang menjadi sumbu radar.

   ── KENAPA PERUBAHAN INI ADA ──────────────────────────────────────────────────
   Radar kompetensi selama ini hanya menggambar SATU jaring: nilai orangnya. Jaring itu
   menjawab "berapa", tidak pernah menjawab "cukup atau belum" — pembacanya harus
   membawa sendiri standar yang ada di kepalanya, dan dua pembaca membawa standar yang
   berbeda. `Nilai_Ambang` memberi radar jaring kedua sebagai acuan, sehingga jarak
   antara capaian dan standar terbaca langsung dari bentuknya.

   Kolom kedua menjawab masalah yang berbeda: tidak semua kompetensi pantas jadi sumbu.
   Radar dengan belasan sumbu berubah jadi lingkaran bergerigi yang tidak terbaca, dan
   menambah satu kompetensi ke kamus tidak seharusnya diam-diam merusak setiap radar
   yang sudah ada. `Flag_Radar` membuat pilihan itu eksplisit dan bisa diatur HC.

   ── KENAPA KAMUS, BUKAN SNAPSHOT PER SIKLUS (K26) ─────────────────────────────
   K26 (README DDL-2026-067) membagi tabel PA menjadi kamus dan aturan: kamus menyatakan
   ARTI dan dikoreksi global, aturan MENGUBAH HASIL orang dan wajib punya salinan per
   siklus. `N_PA_Competency` adalah kamus.

   Kedua kolom ini sengaja ditaruh di kamus, dan itu keputusan sadar tanggal 11 September
   2026, bukan kelalaian. `Flag_Radar` murni penyajian — sekelas `Urutan_Tampil`, yang
   juga sudah tinggal di sini. `Nilai_Ambang` lebih dekat ke perbatasan, tetapi pada
   perubahan ini ia TIDAK dipakai menghitung apa pun: tidak masuk `N_PA_Result`, tidak
   menentukan grade, tidak menentukan kuadran HAV. Ia garis acuan yang digambar. Selama
   itu benar, membekukannya per siklus hanya menambah satu tabel dan satu langkah
   penyiapan tanpa melindungi apa pun.

   Batas itu harus dijaga, dan di sinilah ia dicatat: BEGITU ambang dipakai untuk
   memutuskan sesuatu — lulus/tidak, syarat promosi, penyaring kalibrasi — ia berubah
   menjadi aturan menurut K26 dan HARUS pindah ke snapshot per siklus lewat perubahan
   baru. Tanpa itu, mengubah KKM tahun depan diam-diam menulis ulang kesimpulan siklus
   yang sudah ditutup.

   ── KENAPA NULL-ABLE, DAN BUKAN NOL ───────────────────────────────────────────
   `Nilai_Ambang` NULL berarti "belum ditetapkan", dan sumbu itu tidak digambar pada
   jaring KKM. Memberinya default 0 akan membuat jaring KKM penyok ke pusat di sumbu
   yang sebenarnya belum diputuskan — terbaca sebagai standar yang sangat rendah, yaitu
   kebohongan yang tidak akan pernah memunculkan galat.

   ── KENAPA TANPA INDEKS ───────────────────────────────────────────────────────
   `N_PA_Competency` adalah kamus berisi puluhan baris dan selalu dibaca utuh lalu
   disaring di memori. Indeks pada `Flag_Radar` hanya menambah objek yang harus dirawat
   tanpa satu pun kueri yang memanfaatkannya.
*/

/* ---------------------------------------------------------------
 | 1. Ambang nilai (KKM) per kompetensi.
 |    Skalanya 0..100 karena nilai kompetensi dinormalkan ke sana:
 |    PaResultCalculationService::normal() menghitung
 |    Skor / Skor_Maksimal * 100 sebelum dirata-ratakan.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Nilai_Ambang'
)
    ALTER TABLE N_PA_Competency
        ADD Nilai_Ambang decimal(5,2) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'CK_PA_Competency_Ambang'
)
    ALTER TABLE N_PA_Competency WITH CHECK
        ADD CONSTRAINT CK_PA_Competency_Ambang
        CHECK (Nilai_Ambang IS NULL OR (Nilai_Ambang >= 0 AND Nilai_Ambang <= 100));
GO

/* ---------------------------------------------------------------
 | 2. Penanda sumbu radar.
 |    NOT NULL berdefault 'Y': seluruh baris lama masuk radar, yaitu
 |    persis perilaku hari ini, sehingga tidak ada radar yang berubah
 |    bentuk hanya karena kolomnya lahir. Baris kualitatif dikeluarkan
 |    terpisah oleh DML-2026-121.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Competency')
      AND name = 'Flag_Radar'
)
    ALTER TABLE N_PA_Competency
        ADD Flag_Radar char(1) NOT NULL
            CONSTRAINT DF_PA_Competency_Radar DEFAULT ('Y');
GO

/* ---------------------------------------------------------------
 | 3. Pendaftaran perubahan.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-096'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-096',
        'DDL',
        'Ambang nilai (KKM) dan penanda sumbu radar pada kompetensi',
        'Radar kompetensi hanya menggambar satu jaring, yaitu nilai orangnya, sehingga menjawab berapa tanpa pernah menjawab cukup atau belum. Nilai_Ambang memberi radar jaring acuan kedua. Flag_Radar membuat pilihan sumbu eksplisit, supaya menambah kompetensi ke kamus tidak diam-diam merusak setiap radar yang sudah ada.',
        'database/ddl/DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi/forward.sql',
        'docs/changes/DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi/README.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'Ditaruh di kamus (N_PA_Competency), bukan snapshot per siklus, karena pada perubahan ini ambang TIDAK dipakai menghitung apa pun: tidak masuk N_PA_Result, tidak menentukan grade, tidak menentukan kuadran HAV. Begitu ambang dipakai memutuskan sesuatu, ia menjadi aturan menurut K26 dan wajib pindah ke snapshot per siklus lewat perubahan baru. Nilai_Ambang NULL-able karena NULL berarti belum ditetapkan; default 0 akan terbaca sebagai standar sangat rendah tanpa memunculkan galat. Flag_Radar berdefault Y supaya tidak ada radar yang berubah bentuk hanya karena kolomnya lahir.',
        NULL
    );
END;
GO
