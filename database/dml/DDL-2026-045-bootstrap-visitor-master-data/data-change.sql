-- Change ID: DDL-2026-045-bootstrap-visitor-master-data
-- Target: Web_HRIS. Development execution only by the agent; production via SQL Tracker.
-- Dependencies: DDL-2026-023-add-visitor-master-soft-status, then
-- DML-2026-021-fix-visitor-durasi-enam-bulan, then this file.
-- Verified development scope on 2026-09-01:
--   inserts: Vehicle_Type 0, Vehicle_Model 104, Vehicle_Color 3,
--            Plat_Kendaraan 4, Purpose 1, Durasi_Permit 0 (112 total);
--   updates: Vehicle_Color 1 (IRENG -> T), Plat_Kendaraan 1 (UII -> T),
--            Durasi_Permit 1 (6 Bulan metadata), with all other statements no-op.
-- Production counts may differ because every mutation is guarded by a natural-key predicate.

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> 'Web_HRIS'
    THROW 50101, 'Wrong database. Expected Web_HRIS.', 1;

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Type', 'U') IS NULL
 OR OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Model', 'U') IS NULL
 OR OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Color', 'U') IS NULL
 OR OBJECT_ID('N_EMI_VISITOR_Master_Plat_Kendaraan', 'U') IS NULL
 OR OBJECT_ID('N_EMI_VISITOR_Master_Purpose', 'U') IS NULL
 OR OBJECT_ID('N_EMI_VISITOR_Master_Durasi_Permit', 'U') IS NULL
    THROW 50102, 'One or more Visitor master tables are missing. Run the reviewed forward DDL first.', 1;

IF COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NULL
 OR COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NULL
    THROW 50103, 'DDL-2026-023-add-visitor-master-soft-status must be applied first.', 1;

BEGIN TRANSACTION;

DECLARE @VehicleTypes TABLE
(
    Nama_Tipe_Kendaraan varchar(50) NOT NULL,
    Akses varchar(20) NOT NULL
);

INSERT INTO @VehicleTypes (Nama_Tipe_Kendaraan, Akses)
VALUES
('Sepeda Motor', 'ALL'),
('Mobil Penumpang', 'ALL'),
('Truk / Kendaraan Berat', 'ALL'),
('Tidak Berkendaraan', 'TAMU');

UPDATE target
SET target.Akses = source.Akses,
    target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Vehicle_Type target
JOIN @VehicleTypes source ON source.Nama_Tipe_Kendaraan = target.Nama_Tipe_Kendaraan
WHERE ISNULL(target.Akses, '') <> source.Akses
   OR ISNULL(target.Flag_Aktif, '') <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Vehicle_Type (Nama_Tipe_Kendaraan, Flag_Aktif, Akses)
SELECT source.Nama_Tipe_Kendaraan, 'Y', source.Akses
FROM @VehicleTypes source
WHERE NOT EXISTS
(
    SELECT 1
    FROM N_EMI_VISITOR_Master_Vehicle_Type target
    WHERE target.Nama_Tipe_Kendaraan = source.Nama_Tipe_Kendaraan
);

UPDATE N_EMI_VISITOR_Master_Vehicle_Type
SET Flag_Aktif = 'T'
WHERE Nama_Tipe_Kendaraan = 'KUDO + REVISI'
  AND ISNULL(Flag_Aktif, '') <> 'T';

DECLARE @VehicleModels TABLE
(
    Nama_Tipe_Kendaraan varchar(50) NOT NULL,
    Nama_Model_Kendaraan varchar(100) NOT NULL
);

INSERT INTO @VehicleModels (Nama_Tipe_Kendaraan, Nama_Model_Kendaraan)
VALUES
('Sepeda Motor', 'Honda Vario'),
('Sepeda Motor', 'Honda BeAT'),
('Sepeda Motor', 'Honda Scoopy'),
('Sepeda Motor', 'Honda PCX'),
('Sepeda Motor', 'Honda ADV'),
('Sepeda Motor', 'Honda Genio'),
('Sepeda Motor', 'Honda Revo'),
('Sepeda Motor', 'Honda Supra X'),
('Sepeda Motor', 'Honda CBR150R'),
('Sepeda Motor', 'Honda CBR250RR'),
('Sepeda Motor', 'Honda CRF150L'),
('Sepeda Motor', 'Honda CB150R'),
('Sepeda Motor', 'Yamaha NMAX'),
('Sepeda Motor', 'Yamaha Aerox'),
('Sepeda Motor', 'Yamaha XMAX'),
('Sepeda Motor', 'Yamaha Lexi'),
('Sepeda Motor', 'Yamaha Fazzio'),
('Sepeda Motor', 'Yamaha Grand Filano'),
('Sepeda Motor', 'Yamaha Gear 125'),
('Sepeda Motor', 'Yamaha FreeGo'),
('Sepeda Motor', 'Yamaha Mio M3'),
('Sepeda Motor', 'Yamaha XSR 155'),
('Sepeda Motor', 'Yamaha R15'),
('Sepeda Motor', 'Yamaha MT-15'),
('Sepeda Motor', 'Yamaha WR155R'),
('Sepeda Motor', 'Suzuki Address'),
('Sepeda Motor', 'Suzuki Burgman Street 125EX'),
('Sepeda Motor', 'Suzuki NEX II'),
('Sepeda Motor', 'Suzuki GSX-R150'),
('Sepeda Motor', 'Suzuki Satria F150'),
('Mobil Penumpang', 'Toyota Avanza'),
('Mobil Penumpang', 'Toyota Innova'),
('Mobil Penumpang', 'Toyota Calya'),
('Mobil Penumpang', 'Toyota Fortuner'),
('Mobil Penumpang', 'Toyota Agya'),
('Mobil Penumpang', 'Toyota Raize'),
('Mobil Penumpang', 'Toyota Rush'),
('Mobil Penumpang', 'Toyota Yaris'),
('Mobil Penumpang', 'Toyota Yaris Cross'),
('Mobil Penumpang', 'Toyota Veloz'),
('Mobil Penumpang', 'Toyota Alphard'),
('Mobil Penumpang', 'Toyota Corolla Cross'),
('Mobil Penumpang', 'Daihatsu Xenia'),
('Mobil Penumpang', 'Daihatsu Terios'),
('Mobil Penumpang', 'Daihatsu Sigra'),
('Mobil Penumpang', 'Daihatsu Ayla'),
('Mobil Penumpang', 'Daihatsu Rocky'),
('Mobil Penumpang', 'Daihatsu Sirion'),
('Mobil Penumpang', 'Daihatsu Luxio'),
('Mobil Penumpang', 'Honda Brio'),
('Mobil Penumpang', 'Honda WR-V'),
('Mobil Penumpang', 'Honda BR-V'),
('Mobil Penumpang', 'Honda HR-V'),
('Mobil Penumpang', 'Honda CR-V'),
('Mobil Penumpang', 'Honda City'),
('Mobil Penumpang', 'Honda City Hatchback'),
('Mobil Penumpang', 'Honda Civic'),
('Mobil Penumpang', 'Honda Accord'),
('Mobil Penumpang', 'Honda Step WGN'),
('Mobil Penumpang', 'Mitsubishi Pajero'),
('Mobil Penumpang', 'Mitsubishi Pajero Sport'),
('Mobil Penumpang', 'Mitsubishi Xpander'),
('Mobil Penumpang', 'Mitsubishi Xpander Cross'),
('Mobil Penumpang', 'Mitsubishi Xforce'),
('Mobil Penumpang', 'Mitsubishi Destinator'),
('Mobil Penumpang', 'Suzuki Ertiga'),
('Mobil Penumpang', 'Suzuki XL7'),
('Mobil Penumpang', 'Suzuki Jimny'),
('Mobil Penumpang', 'Suzuki Grand Vitara'),
('Mobil Penumpang', 'Suzuki Fronx'),
('Mobil Penumpang', 'Suzuki S-Presso'),
('Mobil Penumpang', 'Hyundai Stargazer'),
('Mobil Penumpang', 'Hyundai Creta'),
('Mobil Penumpang', 'Hyundai Palisade'),
('Mobil Penumpang', 'Hyundai Santa Fe'),
('Mobil Penumpang', 'Hyundai Ioniq 5'),
('Mobil Penumpang', 'Hyundai Ioniq 6'),
('Mobil Penumpang', 'Hyundai Kona Electric'),
('Mobil Penumpang', 'Hyundai Staria'),
('Mobil Penumpang', 'Wuling Air ev'),
('Mobil Penumpang', 'Wuling BinguoEV'),
('Mobil Penumpang', 'Wuling Cloud EV'),
('Mobil Penumpang', 'Wuling Almaz'),
('Mobil Penumpang', 'Wuling Alvez'),
('Mobil Penumpang', 'Wuling Cortez'),
('Mobil Penumpang', 'Wuling Confero'),
('Truk / Kendaraan Berat', 'Hino Ranger'),
('Truk / Kendaraan Berat', 'Hino 300'),
('Truk / Kendaraan Berat', 'Hino 500'),
('Truk / Kendaraan Berat', 'Hino 700'),
('Truk / Kendaraan Berat', 'Mitsubishi Fuso'),
('Truk / Kendaraan Berat', 'Mitsubishi Fuso Canter'),
('Truk / Kendaraan Berat', 'Mitsubishi Fuso Fighter X'),
('Truk / Kendaraan Berat', 'Toyota Hilux'),
('Truk / Kendaraan Berat', 'Daihatsu Gran Max'),
('Truk / Kendaraan Berat', 'Mitsubishi Triton'),
('Truk / Kendaraan Berat', 'Mitsubishi L300'),
('Truk / Kendaraan Berat', 'Suzuki Carry'),
('Truk / Kendaraan Berat', 'Isuzu Traga'),
('Truk / Kendaraan Berat', 'Isuzu ELF NLR'),
('Truk / Kendaraan Berat', 'Isuzu ELF NMR'),
('Truk / Kendaraan Berat', 'Isuzu ELF NPS'),
('Truk / Kendaraan Berat', 'Isuzu GIGA FRR'),
('Truk / Kendaraan Berat', 'Isuzu GIGA FTR'),
('Truk / Kendaraan Berat', 'Isuzu GIGA FVR'),
('Truk / Kendaraan Berat', 'Isuzu GIGA FVM'),
('Truk / Kendaraan Berat', 'Isuzu GIGA FVZ'),
('Truk / Kendaraan Berat', 'UD Trucks Quester'),
('Truk / Kendaraan Berat', 'UD Trucks Kuzer'),
('Truk / Kendaraan Berat', 'UD Trucks Croner');

IF EXISTS
(
    SELECT 1
    FROM @VehicleModels model_source
    LEFT JOIN N_EMI_VISITOR_Master_Vehicle_Type type_target
      ON type_target.Nama_Tipe_Kendaraan = model_source.Nama_Tipe_Kendaraan
    WHERE type_target.Id_Vehicle_Type IS NULL
)
    THROW 50104, 'A required vehicle type could not be resolved.', 1;

UPDATE model_target
SET model_target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Vehicle_Model model_target
JOIN N_EMI_VISITOR_Master_Vehicle_Type type_target
  ON type_target.Id_Vehicle_Type = model_target.Id_Master_Tipe
JOIN @VehicleModels model_source
  ON model_source.Nama_Tipe_Kendaraan = type_target.Nama_Tipe_Kendaraan
 AND model_source.Nama_Model_Kendaraan = model_target.Nama_Model_Kendaraan
WHERE ISNULL(model_target.Flag_Aktif, '') <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Vehicle_Model (Id_Master_Tipe, Nama_Model_Kendaraan, Flag_Aktif)
SELECT type_target.Id_Vehicle_Type, model_source.Nama_Model_Kendaraan, 'Y'
FROM @VehicleModels model_source
JOIN N_EMI_VISITOR_Master_Vehicle_Type type_target
  ON type_target.Nama_Tipe_Kendaraan = model_source.Nama_Tipe_Kendaraan
WHERE NOT EXISTS
(
    SELECT 1
    FROM N_EMI_VISITOR_Master_Vehicle_Model model_target
    WHERE model_target.Id_Master_Tipe = type_target.Id_Vehicle_Type
      AND model_target.Nama_Model_Kendaraan = model_source.Nama_Model_Kendaraan
);

UPDATE N_EMI_VISITOR_Master_Vehicle_Model
SET Flag_Aktif = 'T'
WHERE Nama_Model_Kendaraan = 'Range rover + Edit'
  AND ISNULL(Flag_Aktif, '') <> 'T';

DECLARE @VehicleColors TABLE (Nama_Warna varchar(50) NOT NULL);
INSERT INTO @VehicleColors (Nama_Warna)
VALUES ('Hitam'), ('Putih'), ('Perak (Silver)'), ('Abu-abu'), ('Merah'), ('Biru'), ('Cokelat'), ('Hijau'), ('Kuning');

UPDATE color_target
SET color_target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Vehicle_Color color_target
JOIN @VehicleColors color_source ON color_source.Nama_Warna = color_target.Nama_Warna
WHERE color_target.Flag_Aktif <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Vehicle_Color (Nama_Warna, Flag_Aktif)
SELECT color_source.Nama_Warna, 'Y'
FROM @VehicleColors color_source
WHERE NOT EXISTS
(
    SELECT 1 FROM N_EMI_VISITOR_Master_Vehicle_Color color_target
    WHERE color_target.Nama_Warna = color_source.Nama_Warna
);

UPDATE N_EMI_VISITOR_Master_Vehicle_Color
SET Flag_Aktif = 'T'
WHERE Nama_Warna = 'IRENG'
  AND Flag_Aktif <> 'T';

DECLARE @PlateCodes TABLE (Kode_Plat varchar(5) NOT NULL);
INSERT INTO @PlateCodes (Kode_Plat)
VALUES ('B'), ('BG'), ('BD'), ('BH'), ('BA'), ('BP'), ('BM'), ('BK'), ('BN'), ('D');

UPDATE plate_target
SET plate_target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Plat_Kendaraan plate_target
JOIN @PlateCodes plate_source ON plate_source.Kode_Plat = plate_target.Kode_Plat
WHERE ISNULL(plate_target.Flag_Aktif, '') <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Plat_Kendaraan (Kode_Plat, Flag_Aktif)
SELECT plate_source.Kode_Plat, 'Y'
FROM @PlateCodes plate_source
WHERE NOT EXISTS
(
    SELECT 1 FROM N_EMI_VISITOR_Master_Plat_Kendaraan plate_target
    WHERE plate_target.Kode_Plat = plate_source.Kode_Plat
);

UPDATE N_EMI_VISITOR_Master_Plat_Kendaraan
SET Flag_Aktif = 'T'
WHERE Kode_Plat IN ('UII', 'BIOR')
  AND ISNULL(Flag_Aktif, '') <> 'T';

DECLARE @Purposes TABLE (Nama_Keperluan varchar(100) NOT NULL);
INSERT INTO @Purposes (Nama_Keperluan)
VALUES
('Meeting Internal'),
('Pengiriman Barang (Vendor)'),
('Maintenance / Service'),
('Kunjungan Tamu VVIP'),
('Interview'),
('Audit / Inspeksi Lapangan');

UPDATE purpose_target
SET purpose_target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Purpose purpose_target
JOIN @Purposes purpose_source ON purpose_source.Nama_Keperluan = purpose_target.Nama_Keperluan
WHERE purpose_target.Flag_Aktif <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Purpose (Nama_Keperluan, Flag_Aktif)
SELECT purpose_source.Nama_Keperluan, 'Y'
FROM @Purposes purpose_source
WHERE NOT EXISTS
(
    SELECT 1 FROM N_EMI_VISITOR_Master_Purpose purpose_target
    WHERE purpose_target.Nama_Keperluan = purpose_source.Nama_Keperluan
);

DECLARE @Durations TABLE
(
    Nama_Durasi varchar(50) NOT NULL,
    Jumlah_Angka int NOT NULL,
    Satuan_Waktu varchar(20) NOT NULL,
    Keterangan_Durasi varchar(20) NOT NULL,
    Urutan_Tampil int NOT NULL
);

INSERT INTO @Durations (Nama_Durasi, Jumlah_Angka, Satuan_Waktu, Keterangan_Durasi, Urutan_Tampil)
VALUES
('1 Bulan', 1, 'MONTH', 'Berlaku 1 Bulan', 1),
('3 Bulan', 3, 'MONTH', 'Berlaku 3 Bulan', 2),
('6 Bulan', 6, 'MONTH', 'Berlaku 6 Bulan', 3);

UPDATE duration_target
SET duration_target.Jumlah_Angka = duration_source.Jumlah_Angka,
    duration_target.Satuan_Waktu = duration_source.Satuan_Waktu,
    duration_target.Keterangan_Durasi = duration_source.Keterangan_Durasi,
    duration_target.Urutan_Tampil = duration_source.Urutan_Tampil,
    duration_target.Flag_Aktif = 'Y'
FROM N_EMI_VISITOR_Master_Durasi_Permit duration_target
JOIN @Durations duration_source ON duration_source.Nama_Durasi = duration_target.Nama_Durasi
WHERE duration_target.Jumlah_Angka <> duration_source.Jumlah_Angka
   OR duration_target.Satuan_Waktu <> duration_source.Satuan_Waktu
   OR ISNULL(duration_target.Keterangan_Durasi, '') <> duration_source.Keterangan_Durasi
   OR ISNULL(duration_target.Urutan_Tampil, -1) <> duration_source.Urutan_Tampil
   OR ISNULL(duration_target.Flag_Aktif, '') <> 'Y';

INSERT INTO N_EMI_VISITOR_Master_Durasi_Permit
    (Nama_Durasi, Jumlah_Angka, Satuan_Waktu, Keterangan_Durasi, Urutan_Tampil, Flag_Aktif)
SELECT duration_source.Nama_Durasi, duration_source.Jumlah_Angka, duration_source.Satuan_Waktu,
       duration_source.Keterangan_Durasi, duration_source.Urutan_Tampil, 'Y'
FROM @Durations duration_source
WHERE NOT EXISTS
(
    SELECT 1 FROM N_EMI_VISITOR_Master_Durasi_Permit duration_target
    WHERE duration_target.Nama_Durasi = duration_source.Nama_Durasi
);

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Vehicle_Type target JOIN @VehicleTypes source ON source.Nama_Tipe_Kendaraan=target.Nama_Tipe_Kendaraan WHERE target.Flag_Aktif='Y' AND target.Akses=source.Akses) <> 4
    THROW 50105, 'Vehicle type verification failed.', 1;

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Vehicle_Model target JOIN N_EMI_VISITOR_Master_Vehicle_Type type_target ON type_target.Id_Vehicle_Type=target.Id_Master_Tipe JOIN @VehicleModels source ON source.Nama_Tipe_Kendaraan=type_target.Nama_Tipe_Kendaraan AND source.Nama_Model_Kendaraan=target.Nama_Model_Kendaraan WHERE target.Flag_Aktif='Y') <> 110
    THROW 50106, 'Vehicle model verification failed.', 1;

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Vehicle_Color target JOIN @VehicleColors source ON source.Nama_Warna=target.Nama_Warna WHERE target.Flag_Aktif='Y') <> 9
    THROW 50107, 'Vehicle color verification failed.', 1;

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Plat_Kendaraan target JOIN @PlateCodes source ON source.Kode_Plat=target.Kode_Plat WHERE target.Flag_Aktif='Y') <> 10
    THROW 50108, 'Plate code verification failed.', 1;

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Purpose target JOIN @Purposes source ON source.Nama_Keperluan=target.Nama_Keperluan WHERE target.Flag_Aktif='Y') <> 6
    THROW 50109, 'Purpose verification failed.', 1;

IF (SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Durasi_Permit target JOIN @Durations source ON source.Nama_Durasi=target.Nama_Durasi AND source.Jumlah_Angka=target.Jumlah_Angka AND source.Satuan_Waktu=target.Satuan_Waktu AND source.Keterangan_Durasi=target.Keterangan_Durasi AND source.Urutan_Tampil=target.Urutan_Tampil WHERE target.Flag_Aktif='Y') <> 3
    THROW 50110, 'Permit duration verification failed.', 1;

IF EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Vehicle_Type WHERE Nama_Tipe_Kendaraan='KUDO + REVISI' AND ISNULL(Flag_Aktif, '') <> 'T')
 OR EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Vehicle_Model WHERE Nama_Model_Kendaraan='Range rover + Edit' AND ISNULL(Flag_Aktif, '') <> 'T')
 OR EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Vehicle_Color WHERE Nama_Warna='IRENG' AND Flag_Aktif <> 'T')
 OR EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Plat_Kendaraan WHERE Kode_Plat IN ('UII','BIOR') AND ISNULL(Flag_Aktif, '') <> 'T')
    THROW 50111, 'Excluded Visitor test data is still active.', 1;

COMMIT TRANSACTION;
