# Pemisahan Tamu Regular & Tamu B2B — Desain

Tanggal: 2026-09-18 · Branch: `feat/vms/tamu-b2b-page` · Modul: VISITOR › Guest › Visit (+ Workflow Verification/Reporting)

## Latar

Registrasi tamu hari ini satu halaman (`/transaksitamu`) dengan dropdown tipe
REGULAR/B2B. Aturan isian kedua tipe identik; bedanya hanya teks. Verifikator
tamu satu antrean untuk keduanya. Tim ingin keduanya berdiri sendiri karena
B2B punya isian wajib yang tidak berlaku untuk Regular, dan pengaju serta
verifikatornya orang yang berbeda. Ini juga prasyarat halaman jadwal inspeksi
untuk tim QC (ditunda, lihat "Di luar cakupan").

Basis database terverifikasi 2026-09-18 di `Web_HRIS` (development): grup
fitur `Id_Grup_Fitur=4`, `Kode_Grup_Fitur='VISITOR'`. Halaman tamu memakai
`Flag_Hibah_Role='T'` (kunci per orang lewat `HRIS_Page_Access`, DDL-2026-110)
kecuali `visitorTamuPage` (menu global, `Flag_Hibah_Role='Y'`).

## Keputusan yang sudah disepakati user

1. Halaman pengajuan, verifikator, dan monitoring **dipisah per tipe** (pendekatan A):
   tiap tipe punya `Jenis_page` sendiri; kode Vue/controller dipakai bersama dengan
   parameter tipe yang **ditetapkan rute**, bukan dikirim klien.
2. Estimasi Masuk & Estimasi Keluar untuk **Regular dan B2B**, wajib.
3. `Tamu_Kategori` (VISITOR/MITRA) dihitung sistem: **MITRA bila rentang estimasi
   > 24 jam** (tepat 24 jam = VISITOR). Tidak tampil di form pengajuan.
4. Status daur hidup berbahasa Inggris, lengkap & terpisah (tabel di bawah).
5. Aplikasi palang **tidak diubah**. HCIS hanya membaca `Waktu_Aktual_Masuk/Keluar`
   yang ditulis palang ke `N_VISITOR_Tamu_Efektif`. MITRA boleh keluar-masuk;
   HCIS menampilkan apa yang terakhir ditulis palang.
6. Pengajuan Regular: semua karyawan. Pengajuan B2B: hanya orang yang diberi kunci.
7. Approval B2B dua meja: **verifikator tamu B2B (role baru `ADMIN_TAMU_B2B`)** →
   permit coklat + inspeksi oleh `ADMIN_PERMIT`. Regular tetap `ADMIN_TAMU`.
8. API partner B2B ikut aturan baru **dengan masa transisi**.
9. Form: Regular plat opsional; B2B wajib berkendaraan + plat wajib + foto kendaraan opsional.

## 1. Model data

Semua di `N_EMI_VISITOR_Transaksi_Tamu_Detail` kecuali disebut lain.

| Kolom | Perubahan |
|---|---|
| `Waktu_Mulai_Kunjungan` / `Waktu_Selesai_Kunjungan` | sudah ada (`datetime2(0) NULL`); dipakai kembali sebagai **Estimasi Masuk/Keluar**. Wajib untuk pengajuan baru; keluar > masuk. |
| `Tanggal_Kunjungan` (header) | tetap; **selalu diturunkan** dari tanggal Estimasi Masuk. |
| `Tamu_Kategori` | **baru** `varchar(10) NULL`. Ditulis hanya oleh `RegistrasiTamuService`. |
| `Path_Foto_Kendaraan` | **baru** `varchar(500) NULL`. Path objek GCS. |
| `Status_Kunjungan` (header, `varchar(20)`) | nilai diganti (lihat §2). |

Sesuai `AGENTS.md`, daftar nilai bisnis yang bisa berkembang (`Tamu_Kategori`,
`Status_Kunjungan`) **tidak** dijaga CHECK constraint; validasinya di backend lewat
satu kelas konstanta. (Ini mengoreksi usulan CHECK di sesi brainstorm.)

Batas rentang estimasi: maksimal **30 hari** (penjaga salah ketik; mudah diubah
di satu konstanta). Estimasi Masuk tidak boleh sebelum hari ini (setara aturan
tanggal kunjungan sekarang).

## 2. Status

**Tersimpan** di `Status_Kunjungan` — hanya status yang diputuskan manusia:

| Kode | Ditulis oleh |
|---|---|
| `PENDING_APPROVAL` | registrasi (bila butuh approval) |
| `SCHEDULED` | registrasi tanpa approval, atau approve akhir |
| `REJECTED` | approver menolak |
| `WITHDRAWN` | pemohon membatalkan |
| `REVOKED` | verifikator mencabut kunjungan yang sudah disetujui (kini juga menulis header) |

**Tampil** — dihitung saat dibaca oleh satu fungsi SQL di `VisitorKehadiranQueryService`
(dipakai monitoring, papan, dashboard, export, homepage, riwayat pemohon):

1. `PENDING_APPROVAL`/`REJECTED`/`WITHDRAWN`/`REVOKED` → apa adanya
2. `SCHEDULED` + ada jam keluar (proyeksi, fallback Detail) → `CHECKED_OUT`
3. `SCHEDULED` + ada jam masuk → `CHECKED_IN`
4. `SCHEDULED` tanpa jam masuk dan Estimasi Keluar lewat → `CANCELLED`
5. selain itu → `SCHEDULED`

Estimasi Keluar efektif = `COALESCE(Waktu_Selesai_Kunjungan, Tanggal_Kunjungan + 1 hari)`
— sama dengan aturan expiry sekarang, sehingga baris lama tanpa estimasi tetap benar.
Papan harian "hari ini" = kunjungan yang **rentang efektifnya beririsan dengan hari ini**.

Label UI: Pending Approval, Scheduled, Checked In, Checked Out, Rejected,
Withdrawn, Revoked, Cancelled.

API partner **tidak berubah**: status di API diterjemahkan dari `Flag_Approval`
+ `Status_Efektif`, bukan dari kolom ini.

## 3. Form pengajuan per tipe

Satu sumber aturan server `AturanFormTamu` (per tipe) dipakai FormRequest halaman
Regular, halaman B2B, dan API partner. `TamuForm.vue` dipakai kedua halaman dengan
prop `tipe`; dropdown tipe dihapus.

| Field | Regular | B2B |
|---|---|---|
| identitas, instansi, keperluan, PIC, rombongan | seperti sekarang | seperti sekarang |
| Estimasi Masuk/Keluar | wajib | wajib |
| Jenis kendaraan | opsional, bawaan "Tidak Berkendara" | **wajib**; "Tidak Berkendara" disembunyikan **dan ditolak server** |
| Plat nomor | opsional | **wajib** — wilayah + angka wajib, huruf belakang opsional; wilayah dicek master |
| Foto kendaraan | — | opsional, 1 berkas jpg/png/webp ≤ 5 MB |

Foto disimpan ke disk `gcs` di `visitor/tamu/{No_Transaksi}/foto-kendaraan.<ext>`
(pola yang sama dengan dokumen permit). Diunggah setelah baris tamu tersimpan;
kegagalan unggah tidak membatalkan pengajuan tetapi dilaporkan ke pengguna.
Verifikator tamu B2B dan meja permit (`PermitReviewModal`) menampilkannya sebagai
rujukan (tautan bertanda tangan), tidak disalin ke tabel dokumen permit.

Pengajuan B2B lama (tanpa kendaraan/plat) tidak diubah; aturan hanya untuk pengajuan baru.

## 4. Halaman, rute, dan akses

| Peran | Regular (dipakai ulang) | B2B (baru) |
|---|---|---|
| Pengajuan | `/transaksitamu` · `visitorTamuPage` (global) | `/transaksi-tamu-b2b` · `visitorTamuB2BPage` |
| Verifikator | `/verifikator-visitor/tamu` · `visitorVerifikatorTamuPage` | `/verifikator-visitor/b2b` · `visitorVerifikatorTamuB2BPage` |
| Monitoring | `/monitoring-tamu` · `visitorMonitoringTamuPage` | `/monitoring-b2b` · `visitorMonitoringTamuB2BPage` |

- URL lama tetap (bookmark & tautan notifikasi tidak putus) dan menjadi **Regular saja**.
- Tipe ditentukan rute lewat middleware `visitor.tipe-tamu:<TIPE>` (atribut permintaan,
  bukan route default yang diteruskan Laravel sebagai argumen posisi), dan setiap endpoint data/aksi
  (daftar, detail, approve/reject/revoke, statistik) menyaring & menolak baris tipe lain
  (404). Menyembunyikan tab di Vue tidak menutup endpoint.
- Tiga `Jenis_page` baru: baris `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`
  (grup 4, `Flag_Hibah_Role='T'`), dan `HRIS_Page_Access` per orang — pola DML-2026-105/109/110.
  Development: kunci diberikan ke anggota aktif `ADMIN_TAMU` saat ini (penguji);
  production: daftar orang diputuskan user.
- Role baru `ADMIN_TAMU_B2B` + `N_HRIS_Jenis_Approval` + `N_HRIS_Approval_Config`
  `TAMU_B2B` (pola DML-2026-108). `VisitorApprovalPolicyService` modul `tamu_b2b`
  → `feature_code = TAMU_B2B`. Anggota awal disalin dari anggota aktif `ADMIN_TAMU`
  supaya antrean tidak yatim; pemangkasan per orang dilakukan user.
- Pengajuan B2B yang sedang menggantung di pool `ADMIN_TAMU` dipindahkan ke
  `ADMIN_TAMU_B2B` (pola DML-2026-108 langkah 5).
- Notifikasi pengajuan baru B2B → anggota `ADMIN_TAMU_B2B`, tautan ke halaman B2B.
- Export Studio & Master Data tamu tidak dipecah (di luar cakupan); dataset
  tamu mendapat kolom status baru + `Tamu_Kategori`.

## 5. Permit coklat mengikuti estimasi

`PermitCoklatTamuService::detail()` memakai rentang Estimasi Masuk–Keluar bila ada
(fallback: satu hari kalender seperti sekarang). MITRA berhari-hari mendapat permit
selama rentangnya, sehingga gerbang tidak menolaknya di hari kedua. Verifikator permit
tetap boleh mengubah jendela di meja permit (perilaku `selaraskan()` dipertahankan).
Label durasi snapshot mengikuti rentang. Teks "satu hari kalender" di UI dan OpenAPI
disesuaikan.

## 6. API partner — transisi

`POST /api/v1/hcis/visitor/tamu-b2b` menerima field baru `estimasi_masuk`,
`estimasi_keluar`, `jenis_kendaraan`, `no_plat_kendaraan` (sudah ada).

- Config `services.visitor.tamu_b2b_api.wajib_lengkap` (env `VISITOR_TAMU_B2B_API_WAJIB_LENGKAP`, bawaan `false`).
- `false` (transisi): request tanpa estimasi diberi estimasi seharian dari
  `tanggal_kunjungan`; tanpa kendaraan/plat tetap diterima. Respons membawa
  `peringatan: [...]` berisi field yang nanti wajib.
- `true`: aturan B2B penuh; request tidak lengkap dibalas 422.
- Foto kendaraan tidak lewat API pada fase ini.
- `resources/openapi/hcis-third-party.yaml` diperbarui (field, `peringatan`, teks durasi).

## 7. Migrasi data (DML, satu change ID dengan DDL)

- `MENUNGGU_APPROVAL`→`PENDING_APPROVAL`, `TERJADWAL`→`SCHEDULED`.
- `DIBATALKAN`: bercatatan approval "Dibatalkan oleh pengaju." → `WITHDRAWN`; sisanya `REJECTED`.
- `SCHEDULED` yang proyeksinya `DIBATALKAN` dengan `Alasan_Status` → `REVOKED`.
- `Tamu_Kategori` baris lama: dihitung dari estimasi bila ada, selain itu `VISITOR`. Estimasi lama tidak dikarang.
- Development saat ini: 15 baris, semuanya `TERJADWAL`.

Change ID: `DDL-2026-121-visitor-pisah-tamu-regular-b2b`
(`database/ddl/…/forward.sql`, `drop.sql`, `database/dml/…/data-change.sql`,
`docs/changes/…/README.md`, entri `status.json`).

## 8. Perbaikan yang ikut

- DITOLAK tidak pernah tampil (kalah oleh DIBATALKAN) — tertutup oleh model status baru.
- Tombol check-out di `TamuHistory.vue` memanggil rute yang sengaja dicabut
  (HCIS tidak menulis kehadiran) — tombolnya dibuang.

## 9. Pengujian

Uji ditulis bersama kode tetapi **baru dijalankan setelah ACC user** (kebiasaan proyek).

- Unit: kategori (24 jam tepat = VISITOR, 24j+1dtk = MITRA, lintas tengah malam < 24 j = VISITOR); aturan form per tipe; pemetaan status tampil.
- Integration: registrasi Regular/B2B (penolakan B2B tanpa kendaraan/plat, "Tidak Berkendara" ditolak); approval B2B masuk pool `ADMIN_TAMU_B2B`; status tersimpan pada approve/reject/withdraw/revoke; CANCELLED setelah Estimasi Keluar; papan harian MITRA hari kedua; permit coklat mengikuti estimasi.
- Feature: isolasi rute — endpoint Regular menolak nomor B2B dan sebaliknya; halaman baru dijaga `check.jabatan`; `VisitorRouteContractTest` diperbarui; API transisi (peringatan) dan mode wajib (422).
- Vitest: `TamuForm` per tipe; halaman B2B.
- Uji yang ada yang menegaskan nilai status lama diperbarui.

## Di luar cakupan

- Halaman jadwal inspeksi tim QC (menyusul, membaca Estimasi Masuk/Keluar B2B).
- Perubahan aplikasi palang / riwayat multi-masuk.
- Pemecahan Export Studio dan Master Data per tipe.
- Resolver sidebar yang belum menghormati `Flag_Hibah_Role` (temuan terpisah 2026-09-18).
