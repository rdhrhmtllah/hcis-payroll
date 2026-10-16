# DDL-2026-121-visitor-pisah-tamu-regular-b2b

## Ringkas

Tamu Regular dan Tamu B2B dipisah menjadi halaman sendiri (pengajuan,
verifikator, monitoring), dengan aturan isian, pool approval, dan kunci halaman
masing-masing. Desain: `docs/superpowers/specs/2026-09-18-visitor-pisah-tamu-regular-b2b-design.md`.

| | |
|---|---|
| Change ID | `DDL-2026-121-visitor-pisah-tamu-regular-b2b` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Basis registry | grup `VISITOR` `Id_Grup_Fitur=4`, diverifikasi 2026-09-18 di `Web_HRIS` |
| Status | `DEV_EXECUTED` (artifact r2) |

## Objek

**DDL (`forward.sql`)** — `N_EMI_VISITOR_Transaksi_Tamu_Detail`:

| Kolom | Tipe | Isi |
|---|---|---|
| `Tamu_Kategori` | `varchar(10) NULL` | `VISITOR` / `MITRA`. Dihitung `RegistrasiTamuService`: MITRA bila Estimasi Keluar − Estimasi Masuk **> 24 jam**. Tidak tampil di form. |
| `Path_Foto_Kendaraan` | `varchar(500) NULL` | Path objek disk `gcs` foto kendaraan tamu B2B (opsional). |

Tanpa CHECK constraint: `AGENTS.md` melarang CHECK untuk daftar nilai bisnis yang
bisa berkembang; validasinya di `StatusKunjunganTamu` / `TipeTamu`.

Kolom yang sudah ada dipakai ulang: `Waktu_Mulai_Kunjungan` / `Waktu_Selesai_Kunjungan`
= Estimasi Masuk / Keluar (wajib untuk pengajuan baru); `Tanggal_Kunjungan` header
diturunkan dari Estimasi Masuk.

**DML (`data-change.sql`)**, satu transaksi:

1. `Status_Kunjungan`: `MENUNGGU_APPROVAL→PENDING_APPROVAL`, `TERJADWAL→SCHEDULED`,
   `DIBATALKAN` bercatatan approval "Dibatalkan oleh pengaju." → `WITHDRAWN`, sisanya
   → `REJECTED`; `SCHEDULED` yang proyeksinya `DIBATALKAN` → `REVOKED`.
2. `Tamu_Kategori` baris lama: dari estimasi bila ada, selain itu `VISITOR`.
3. Role `ADMIN_TAMU_B2B`, jenis approval, config `TAMU_B2B` (+ detail satu langkah).
4. Anggota awal `ADMIN_TAMU_B2B` = anggota aktif `ADMIN_TAMU` (DISTINCT, kedua flag).
5. Pengajuan B2B yang menggantung di pool `ADMIN_TAMU` dipindah ke `ADMIN_TAMU_B2B`
   (dev 2026-09-18: 0 baris).
6. Menu + peta grup (`Flag_Hibah_Role='T'`) + kunci halaman untuk
   `visitorTamuB2BPage` (`/transaksi-tamu-b2b`), `visitorVerifikatorTamuB2BPage`
   (`/verifikator-visitor/b2b`), `visitorMonitoringTamuB2BPage` (`/monitoring-b2b`).
   Kunci diturunkan dari pemegang aktif `visitorVerifikatorTamuPage`.
7. Pendaftaran ke `N_SYSTEM_Database_Change`.

URL B2B sengaja tidak berawalan sama dengan URL Regular (`/transaksitamu*`,
`/monitoring-tamu*`, `/verifikator-visitor/tamu*`): pola menu aktif Regular akan
ikut menyala di halaman B2B.

## Status tersimpan vs tampil

Tersimpan (`Status_Kunjungan`): `PENDING_APPROVAL`, `SCHEDULED`, `REJECTED`,
`WITHDRAWN`, `REVOKED` — keputusan manusia. Tampil (dihitung
`VisitorKehadiranQueryService::siklusKunjunganSql`): ditambah `CHECKED_IN`,
`CHECKED_OUT` (jam palang di `N_VISITOR_Tamu_Efektif`, cadangan Detail) dan
`CANCELLED` (tak pernah masuk sampai Estimasi Keluar lewat). HCIS tidak menerima
peristiwa palang, jadi kehadiran tidak disimpan.

## Kode yang memakai

- Tipe dari rute: middleware `visitor.tipe-tamu` (`App\Http\Middleware\VISITOR\Guest\Visit\TetapkanTipeTamu`), `TipeTamu`.
- Aturan: `AturanFormTamu` (FormRequest `SimpanTamuRequest` + penulis `RegistrasiTamuService`).
- Foto: `FotoKendaraanTamuService`.
- Approval: `VisitorApprovalPolicyService` modul `tamu_b2b`, `config/services.php` `visitor.tamu_b2b` (approval menyala bawaan) dan `visitor.tamu_b2b_api.wajib_lengkap`.
- Notifikasi: `VisitorNotificationService::DOMAIN_TAMU_B2B`.
- Controller: `GuestRegistrationController`, `VerifikatorVisitorController`, `LaporanVisitorController`, `TamuB2BApiController`.
- Permit COKLAT mengikuti rentang estimasi: `PermitCoklatTamuService::detail()`.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-18 | `forward.sql` | OK — `Tamu_Kategori varchar(10) NULL`, `Path_Foto_Kendaraan varchar(500) NULL` terbentuk (INFORMATION_SCHEMA). |
| 2026-09-18 | `data-change.sql` r1 | GAGAL, rollback penuh (`@@TRANCOUNT` 0, data tak berubah): `HRIS_Approval_Request.Catatan` bertipe `text`, dan `text = varchar` ditolak SQL Server. |
| 2026-09-18 | `data-change.sql` r2 | OK setelah ACC user: 15 header `TERJADWAL` → `SCHEDULED`, 15 detail `Tamu_Kategori` = `VISITOR`, `ADMIN_TAMU_B2B` 3 anggota, `TAMU_B2B` → `ADMIN_TAMU_B2B`, 3 menu + 3 peta (`Flag_Hibah_Role='T'`) + 9 kunci. Rerun: no-op. |

## Revisi artifact

| Revisi | Perubahan |
|---|---|
| r1 | Versi awal. |
| r2 | Pencocokan catatan pembatalan pemohon memakai `CAST(ar.Catatan AS nvarchar(200)) = N'…'` karena kolomnya `text`. Diamandemen di tempat (belum `TRACKER_RECORDED`). |

## Checksum

- `forward.sql`: `92b738cee3021a0dd99c80e3970f0313a39690c6d8beb38a8a0cb28357a3fa61`
- `data-change.sql` r2 (LF): `00f27b09dbaf3b82e0b6c44c03c85f7ef9113a4e1104e4021b9eeff56e937d6a`

## Pekerjaan user

- Rekam di SQL Tracker. **Urutan wajib**: `forward.sql` lalu `data-change.sql`,
  keduanya bersamaan dengan deploy kode ini (kode baru tidak mengenali status lama).
- Tentukan penerima kunci halaman B2B dan anggota `ADMIN_TAMU_B2B` di production
  (pemangkasan dari salinan `ADMIN_TAMU`).
- Kabari mitra API B2B tentang field `estimasi_masuk`/`estimasi_keluar` dan kewajiban
  kendaraan; setelah siap, set `VISITOR_TAMU_B2B_API_WAJIB_LENGKAP=true`.
