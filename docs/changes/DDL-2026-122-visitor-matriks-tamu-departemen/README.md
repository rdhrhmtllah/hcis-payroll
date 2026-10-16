# DDL-2026-122-visitor-matriks-tamu-departemen

## Ringkas

Tamu B2B dibedakan unit pengajunya (Procurement / Logistic), dan setiap
departemen hanya melihat jenis tamu yang menjadi urusannya lewat page baru
**Jadwal Kedatangan Tamu**. Matriks departemen × jenis tamu disimpan sebagai
data, bukan kode.

| | |
|---|---|
| Change ID | `DDL-2026-122-visitor-matriks-tamu-departemen` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DDL-2026-121-visitor-pisah-tamu-regular-b2b` |
| Basis registry | grup `VISITOR` `Id_Grup_Fitur=4`, diverifikasi 2026-09-18 di `Web_HRIS` |
| Status | `DEV_EXECUTED` |

## Keputusan user (2026-09-18)

- Matriks lihat (gambar user): GA → Regular; Logistic, QC FG → B2B Logistic;
  QC Inc, Warehouse, Procurement → B2B Procurement.
- Departemen dikenali lewat **role HCIS** (bukan divisi HR).
- Hak mengajukan hanya departemen pemilik: Procurement → B2B Procurement,
  Logistic → B2B Logistic. Kunjungan dari API partner selalu Procurement.
- Verifikasi tetap satu pool `ADMIN_TAMU_B2B`.
- Default agen (belum ditanyakan eksplisit): kunjungan B2B lama dari HCIS → `LOGISTIC`.

## Objek

**DDL (`forward.sql`)**

- `N_EMI_VISITOR_Transaksi_Tamu_Detail.B2B_Unit varchar(20) NULL` — `PROCUREMENT` / `LOGISTIC`, hanya untuk B2B.
- Tabel baru `N_VISITOR_Matriks_Tamu`:

| Kolom | Tipe | Arti |
|---|---|---|
| `Id_Matriks_Tamu` | `int IDENTITY` PK | |
| `Role_Id` | `int NOT NULL` FK → `N_HRIS_Role_Functional.Id_Role` | role departemen |
| `Kategori_Tamu` | `varchar(20) NOT NULL` | `REGULAR` / `B2B_PROCUREMENT` / `B2B_LOGISTIC` (divalidasi backend, tanpa CHECK) |
| `Flag_Lihat` | `char(1)` default `T` | boleh melihat di Jadwal Kedatangan |
| `Flag_Ajukan` | `char(1)` default `T` | boleh mengajukan unit B2B ini |
| `Flag_Aktif` | `char(1)` default `Y` | baris berlaku |
| `Created_At/By`, `Updated_At/By` | audit | actor = kode change / agen |

  Indeks unik `UX_VISITOR_Matriks_Tamu_Role_Kategori (Role_Id, Kategori_Tamu)`.

**DML (`data-change.sql`)**: enam role `TAMU_DEPT_*` (grup 4), baris matriks,
backfill `B2B_Unit`, menu + peta grup `visitorJadwalTamuPage`
(`/jadwal-kedatangan-tamu`, `Flag_Hibah_Role='Y'`), keanggotaan penguji
**hanya bila `DB_NAME() = 'Web_HRIS'`**, pendaftaran dashboard change.

## Akses

- Page Jadwal dihibahkan ke role grup VISITOR (termasuk enam role departemen);
  **isinya disaring matriks di server** (`JadwalKedatanganTamuController`,
  `MatriksTamuService::saring`). Role tanpa centang melihat keadaan kosong.
- Pengajuan B2B: kunci halaman `visitorTamuB2BPage` tetap per orang
  (DDL-2026-121) **dan** matriks `Flag_Ajukan`. Tanpa role departemen → 403
  `VISITOR_B2B_UNIT_TIDAK_BERHAK`; satu unit → terkunci; dua unit → wajib memilih.

## Kode

`MatriksTamuService`, `JadwalKedatanganTamuController`
(`routes/VISITOR/Guest/Schedule/JadwalKedatanganTamu.php`),
`resources/js/Pages/VISITOR/Guest/Schedule/JadwalKedatanganPage.vue`,
`GuestRegistrationController` (penguncian unit), `RegistrasiTamuService`
(menulis `B2B_Unit`), `TamuB2BApiController` (unit PROCUREMENT), label & filter
unit di verifikator/monitoring.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-18 | `forward.sql` | OK — kolom `B2B_Unit`, tabel + indeks unik terbentuk. |
| 2026-09-18 | `data-change.sql` | OK — 6 role, 6 baris matriks sesuai gambar, `B2B_Unit` lama: 7 LOGISTIC + 3 PROCUREMENT, menu Jadwal (hibah `Y`), penguji dev: 624 Procurement+Logistic, 4 QC Inc, 1885 QC FG+GA. |

## Pekerjaan user

- Rekam di SQL Tracker **setelah** DDL-2026-121; urutan `forward.sql` lalu `data-change.sql`.
- Isi anggota enam role `TAMU_DEPT_*` di production (skrip tidak mengisinya di luar `Web_HRIS`).
- Pemohon B2B di production butuh DUA hal: kunci `visitorTamuB2BPage` dan role departemen Procurement/Logistic.
