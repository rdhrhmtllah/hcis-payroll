# KPI-Unreal Module Structure Inventory

Dokumen ini adalah baseline hasil pemindaian seluruh file source yang relevan. Tujuannya memetakan kondisi existing ke struktur target tanpa memindahkan file pada fase ini. Klasifikasi berbasis lokasi/nama file adalah indikasi awal dan harus divalidasi saat setiap fitur disentuh.

**Penting:** inventory ini adalah snapshot dokumentasi, bukan registry module/feature aktif. Angka, kandidat, dan target konseptual di bawah dapat kedaluwarsa. Untuk kondisi terbaru, agent wajib membaca `N_HRIS_Master_Grup_Fitur`, `N_HRIS_Grup_Fitur_Map`, dan relasi role yang relevan pada environment yang sedang digunakan. Jangan menggunakan inventory ini untuk menyimpulkan bahwa module, submodule, atau page masih aktif tanpa verifikasi database.

## Target struktur lintas layer

```text
app/Http/Controllers/<MODUL>/<Submodul>/<Fitur>/
app/Http/Requests/<MODUL>/<Submodul>/<Fitur>/
app/Models/<MODUL>/<Submodul>/<Fitur>/
app/Services/<MODUL>/<Submodul>/<Fitur>/
app/Jobs/<MODUL>/<Submodul>/<Fitur>/
routes/<MODUL>/<Submodul>/<Fitur>/
resources/js/Pages/<MODUL>/<Submodul>/<Fitur>/
tests/<jenis>/<MODUL>/<Submodul>/<Fitur>/
docs/modules/<modul>/<submodul>/<fitur>.md
docs/database/tables/<modul>/
```

Folder layer hanya dibuat bila digunakan. Base folder Laravel tetap dipertahankan; modul, submodul, dan fitur menjadi pengelompokan di dalam base folder tersebut. Tidak ada target `app/Modules`.

## Hasil scan

| Area | File terdeteksi | Keterangan |
|---|---:|---|
| `app/Http/Controllers` | 132 | 64 file langsung di root; sisanya tersebar di folder feature/legacy |
| `app/Models` | 37 | Semua masih berada dalam satu folder root model |
| `app/Services` | 150 | SOP paling besar; terdapat service lintas modul di root |
| `app/Jobs` | 23 | 16 langsung di root; sebagian sudah dikelompokkan |
| `app/Console/Commands` | 54 | 51 langsung di root; sebagian besar belum dikelompokkan |
| `routes` | 35 | `web.php` masih menjadi loader/aggregator besar; route module belum seragam |
| `resources/js/Pages` | 445 | Modul KPI, LMS, SOP, dan VISITOR sudah terlihat, tetapi ada banyak file root/legacy |
| `resources/js/components` | 9 | Komponen global dan domain masih perlu dipisahkan |
| `resources/js/composables` | 9 | Mayoritas bersifat global, perlu validasi pemilik domain |
| `tests` | 125 | Sudah ada Feature/Integration/Unit/e2e, tetapi belum konsisten per modul |

## Pemetaan existing ke modul target

### HRIS

Kandidat existing yang terlihat:

```text
app/Http/Controllers/
├── AbsensiController.php
├── EmployeeController.php
├── DivisionController.php
├── LevelController.php
├── LemburController.php
├── OvertimeController.php
├── ShiftController.php
├── AdminShiftController.php
├── ApprovalFlowShiftController.php
└── controller di HCAbsensi/

app/Services/
├── Absensi/
├── HCIS/
├── Cuti/
├── Lembur/
└── snapshot/shift/

app/Models/
├── Karyawan.php
├── Division.php
├── Level.php
├── CHECKINOUT.php
├── Lembur*.php
├── Shift_*.php
└── Waktu_Kerja.php
```

Struktur terlaksana (2026-08-18). Modul terdaftar di master sebagai `ESS`,
bukan `HRIS`:

```text
ESS/Attendance/{Leave,Overtime,Shift,Calendar,Correction,Processing,Legacy}
ESS/Employee/Profile
ESS/Organization/{Division,Level,Team}
ESS/Dashboard/{Personal,Leadership,Homepage}
ESS/Activity/Center
ESS/Workflow/{Case,CrossDivision,Evaluation,Project,Review,Task,Ticket}
```

### KPI

Kandidat existing yang terlihat:

```text
app/Http/Controllers/
├── ApprovalKPIController.php
├── BscKategoriKpiController.php
├── FormKpiController.php
├── FormulaKpiController.php
├── KpiApprovalController.php
├── KpiEnforcementController.php
├── KpiKaryawanTeamController.php
├── KpiVerifikasiController.php
└── PenjadwalanKpiController.php

app/Services/KPI/
resources/js/Pages/KPI/
routes/kpi/
tests/Feature/KPI/
```

Target konseptual:

```text
KPI/Dashboard
KPI/Parameter
KPI/Formula
KPI/Target
KPI/Performance
KPI/Approval
KPI/Penjadwalan
KPI/Akses
```

Mapping note 2026-08-30: page database aktif `MasterPerformance` pada modul KPI
dan route `/performance-kpi` mengarah ke legacy
`KPI_Master_Performance`/Performance Range. Ia bukan boundary Performance
Appraisal Policy. Fitur PA yang baru tetap berada di modul KPI, tetapi perlu
`Jenis_page` dan route khusus yang disetujui sebelum file aplikasi dibuat.

### LMS

Kandidat existing:

```text
app/Http/Controllers/Lms*/
app/Http/Controllers/TrainingCatalog/
app/Services/LMS/
routes/Lms*/
routes/TrainingCatalog/
resources/js/Pages/LMS/
```

Target konseptual:

```text
LMS/Training
LMS/Materi
LMS/Kategori
LMS/Absensi
LMS/Monitoring
LMS/Penjadwalan
LMS/ThirdParty
```

### SOP — submodul LMS, bukan modul

Master grup fitur tidak memiliki grup `SOP`. Kesembilan page SOP ter-resolve
ke grup `LMS` melalui `N_HRIS_Grup_Fitur_Map`, sehingga seluruh source SOP
berada di bawah `LMS/SOP`.

Struktur terlaksana (2026-08-18):

```text
app/Http/Controllers/LMS/SOP/{Employee,Governance,Integration}/
app/Http/Controllers/LMS/SOP/Governance/{SopCategory,SopJenisDokumen,SopApqc}/
app/Services/SYSTEM/MasterData/Entitas/            # master lintas modul (2026-08-22)
app/Http/Controllers/SYSTEM/MasterData/Entitas/    # pengelolaan, gerbang system.admin
routes/SYSTEM/MasterData/Entitas.php
resources/js/Pages/SYSTEM/MasterData/Entitas/
app/Services/SYSTEM/MasterData/Departemen/         # master lintas modul (2026-08-22)
app/Http/Controllers/SYSTEM/MasterData/Departemen/ # pengelolaan, gerbang system.admin
routes/SYSTEM/MasterData/Departemen.php
resources/js/Pages/SYSTEM/MasterData/Departemen/
app/Services/LMS/SOP/{,Support,Uat}/
app/Jobs/LMS/SOP/{Automation,Export}/
app/Console/Commands/LMS/SOP/Operations/
routes/LMS/SOP/{Employee,Governance,Integration}/
resources/js/Pages/LMS/SOP/
tests/Unit/LMS/SOP/
tests/Feature/LMS/SOP/Uat/
```

Target konseptual:

```text
SOP/Dokumen
SOP/Repository
SOP/Approval
SOP/Export
SOP/Monitoring
SOP/UAT
SOP/Settings
```

`SOP/Export` menjadi kandidat pilot struktur baru karena sedang dikembangkan dalam branch fitur terkait.

### VISITOR

Kandidat existing:

```text
app/Http/Controllers/
├── ApprovalVisitor/
├── LaporanVisitor/
├── TransaksiPermit/
├── TransaksiTamu/
└── VerifikatorVisitor/

app/Services/Visitor/
routes/VISITOR/Workflow/Approval/
routes/VISITOR/Workflow/Reporting/
routes/TransaksiPermit/
routes/TransaksiTamu/
routes/VISITOR/Workflow/Verification/
resources/js/Pages/VISITOR/
```

Target konseptual:

```text
VISITOR/Tamu
VISITOR/Permit
VISITOR/Approval
VISITOR/Verifikator
VISITOR/Laporan
```

Mapping terverifikasi 2026-08-24: page `visitorLaporanPage` (`/laporanvisitor`)
tetap berada pada `VISITOR/Workflow/Reporting`. Export Studio memiliki dataset
terpisah `tamu` dan `permit`; detail registry dan compatibility boundary dicatat
di `feature-mapping-visitor-reporting-export-studio.md`.

#### Sumber kebenaran kehadiran tamu (2026-08-25)

**HCIS TIDAK MENCATAT KEHADIRAN.** Aplikasi palang di gerbang menulis
`Waktu_Aktual_Masuk` / `Waktu_Aktual_Keluar` langsung ke `N_VISITOR_Tamu_Efektif`.
`VisitorEfektifService::syncTamuEfektif()` mendorong ~30 kolom booking HCIS ke
baris yang sama tetapi **sengaja tidak menyentuh kedua kolom waktu itu**; tabel
Efektif adalah permukaan integrasi dua arah, ditarik pihak ketiga lewat
`Flag_Sync`.

Konsekuensinya, dua kekeliruan berikut membuat pembaca buta terhadap orang yang
benar-benar melewati gerbang — keduanya pernah terjadi di lebih dari satu tempat:

1. **Membaca kolom `Detail` saja.** Kolom itu hanya jalur cadangan catatan manual.
2. **Memercayai `Status_Kunjungan = 'CHECKED_IN'`.** Palang tidak menulis kolom
   status. `Status_Kunjungan` adalah status DAUR HIDUP pengajuan
   (`MENUNGGU_APPROVAL` / `TERJADWAL` / `DIBATALKAN`), **bukan** status kehadiran.

Kehadiran karena itu selalu **diturunkan** dari dua kolom waktu, tidak pernah
disimpan terpisah: keadaan turunan tidak bisa drift, duplikat yang disimpan
selalu bisa.

`VisitorKehadiranQueryService` (`app/Services/VISITOR/Guest/Visit/`) adalah
SATU-SATUNYA definisi. Semua pembaca memakainya — papan harian, analitik
dashboard, keadaan area, stats halaman tamu, dan kartu Visitor di homepage
(`ESS/Dashboard/Homepage/HomepageVisitorService`). Jangan menurunkan aturannya
sendiri per query; sebelum kelas ini ada, 4 dari 10 titik baca salah sumber.

Penyaring ditulis **sargable** (`e.X IS NOT NULL OR d.X IS NOT NULL`), bukan
`COALESCE(...) IS NOT NULL`: bentuk COALESCE membungkus kolom dalam fungsi
sehingga indeks `DDL-2026-027` tidak akan terpakai. `COALESCE` tetap dipakai
untuk mengambil NILAI-nya.

**HCIS tidak boleh punya jalur tulis kehadiran.** Route
`transaksitamu/check-in|check-out` dicabut sengaja pada commit `d641d350` dan
ketiadaannya dijaga `VisitorRouteContractTest`. Method `checkIn()`/`checkOut()`
masih ada di controller tetapi tidak terjangkau dari HTTP; jangan mendaftarkan
route untuknya tanpa mengubah keputusan itu lebih dulu. Dua penulis untuk satu
fakta melahirkan drift tanpa wasit.

Indeks pendukung: `DDL-2026-027-index-visitor-kehadiran` (5 indeks nonclustered;
tidak menyentuh data).

**Alat pengembangan** (keduanya menolak jalan di luar `Web_HRIS` dan di
production; keduanya ALAT, bukan fitur — jangan dijadikan endpoint):

| Perintah | Guna |
|---|---|
| `visitor:seed-papan-harian` | isi data contoh; `--bersihkan` untuk membuangnya |
| `visitor:simulasi-palang` | meniru palang menandai masuk/keluar |

`visitor:simulasi-palang --daftar` menampilkan kandidat, `--masuk=<No_Transaksi>`
dan `--keluar=<No_Transaksi>` menandainya, `--acak` menandai satu tamu hari ini.
Keduanya menulis HANYA ke `N_VISITOR_Tamu_Efektif` dan membuang cache dashboard
sesudahnya — tanpa itu perubahannya tidak terlihat sampai cache kedaluwarsa dan
simulasinya terasa tidak jalan.

Seeder memakai urutan yang sama dengan produksi — buat transaksi, sinkronkan,
baru palang menandai — dan **mendorong `HRIS_Counter`** sesudahnya. Menyisipkan
baris transaksi langsung melewati counter atomik `No_Transaksi`, sehingga
pengajuan berikutnya menabrak nomor yang sudah dipakai dan SELURUH pengajuan
tamu maupun permit gagal dengan galat yang tidak menyebut sebabnya. Counter
hanya pernah dinaikkan, tidak pernah diturunkan.

#### Konsolidasi Dashboard (2026-08-25)

Tab Dashboard sempat merender **9 kartu, 7 tabel, 2 chart, 4 metrik** per domain.
Empat kartu dan satu daftar di antaranya duplikat PERSIS, bukan sekadar mirip:

| Konsep | Dulu muncul di |
|---|---|
| Di dalam area | Kartu Keadaan + Papan "Sedang di dalam" |
| Terjadwal belum datang | Kartu Keadaan + Papan "Harus datang" |
| Perlu ditutup | Kartu Keadaan + Daftar Tindakan + Papan "Menginap" (tiga kali) |
| Menunggu approval | Kartu Keadaan + Papan "Tertahan approval" |

`daftar_perlu_ditutup` di `agregasiDiDalamArea` = masih di dalam DAN
`Tanggal_Kunjungan < hari ini` — definisi identik dengan "menginap".

Yang dicabut: `DashboardKartuKeadaan` (dihapus, sudah yatim), daftar
"Perlu ditutup", kelompok `sudah_keluar_hari_ini` dan `permit_berlaku_hari_ini`.
Dua kelompok terakhir adalah catatan retrospektif dan direktori — tempatnya di
All Monitoring, bukan di papan yang memuat hal yang perlu ditindak.

**Dua hal yang WAJIB tidak ikut hilang saat mencabut Kartu Keadaan:**

1. **Hitungan orang.** Papan menghitung transaksi; Kartu Keadaan menghitung
   `Jumlah_Tamu`. Untuk lobi keduanya berbeda arti — pada data pengembangan
   10 kunjungan di dalam = 23 orang. Ringkasan papan kini membawa
   `*_orang` untuk keempat kelompok tamu.
2. **Antrean approval PENUH.** Papan hanya memuat yang terjadwal hari ini;
   `DashboardDaftarTindakan "Menunggu keputusan"` memuat seluruh antrean, termasuk
   pengajuan minggu lalu yang tertahan. Blok itu dipertahankan.

**Satu jendela kedaluwarsa, 30 hari.** Sebelumnya papan memakai 7 hari sementara
blok permit memakai 30, sehingga permit yang sama tampil dua kali di layar yang
sama. Ambangnya di `VisitorPapanHarianService::HARI_SEGERA`.

Urutan render: **yang perlu ditindak lebih dulu, analisis menyusul** di balik
pemisah "Analisis 14 hari".

#### Monitoring per domain (2026-08-25)

Halaman Monitoring dipisah menurut **domain**, bukan menurut jenis tampilan:

```
Monitoring Visitor
├── Tamu ────────┬── Dashboard        chart & tabel tamu
│                └── All Monitoring   daftar paginasi + filter + cari + urut
└── Permit ──────┬── Dashboard        chart & tabel permit
                 └── All Monitoring   daftar paginasi + filter + cari + urut
```

Tab luar memakai `VisitorWorkspace`; sub-tab memakai `HcisTabs` di dalam panel.
`VisitorDashboardPanel` dan `PapanHarian` menerima prop `domain` dan menyaring
sendiri — penyaringan ditaruh di komponen pemilik definisinya, bukan di pemanggil,
supaya kelompoknya tetap satu definisi.

**Antrian approval disaring per domain.**
`VisitorDashboardOperasionalService::queryAntrianApproval(?string $domain)` memilih
satu cabang `EXISTS`; tanpa argumen perilakunya persis seperti sebelumnya sehingga
pemanggil lama tidak berubah. Endpoint `sekarang` menerima `?domain=` dan
**domainnya ikut ke dalam kunci cache** — tanpa itu hasil tamu dan permit saling
menimpa di kunci yang sama dan tab yang dibuka belakangan menampilkan angka milik
tab sebelumnya.

**All Monitoring memakai endpoint yang sudah ada**, bukan endpoint baru:
`laporanvisitor-tamu` dan `laporanvisitor-permit` — keduanya sudah paginasi dan
berfilter, tinggal ditambah `cari`, `urut`, dan `arah`.

Pengurutan memakai **daftar putih kolom** (`URUT_TAMU` / `URUT_PERMIT` di
`LaporanVisitorController`). Nilai dari permintaan tidak pernah menyentuh
`ORDER BY`: menyambungnya langsung adalah lubang injeksi, dan kolom tak dikenal
jatuh ke urutan baku, bukan melahirkan galat SQL di layar. Urutan selalu diikuti
kunci kedua yang unik (`Id_Transaksi_*`) — tanpa itu baris berperingkat sama bisa
berpindah antar halaman dan orang hilang dari paginasi.

Balasan kedua endpoint itu menaruh `result`, `stats`, dan `pagination` di **akar**,
bukan dibungkus `ResponseHelper::success`. Membacanya seperti amplop `result`
menghasilkan daftar kosong tanpa galat.

Desain lengkap: `docs/superpowers/specs/2026-08-25-monitoring-visitor-per-domain-design.md`.

#### Papan Harian & pencatatan kehadiran (2026-08-25)

Tab "Aktivitas harian" pada Monitoring dinaikkan menjadi **Papan Harian**:
`Reporting/Monitoring/components/PapanHarian.vue`, disuplai
`VisitorPapanHarianService` lewat `GET /laporanvisitor-papan-harian`.
`LaporanHarian.vue` dihapus — ia menampilkan kolom Jam Masuk dan Status Keluar
yang mustahil terisi, dan menyisakannya berarti dua tampilan harian kembar.

**Check-in/check-out tamu akhirnya terjangkau.**
`GuestRegistrationController::checkIn()`/`checkOut()` sudah lama ada tetapi
tidak pernah punya route; akibatnya `Waktu_Aktual_Masuk` nol persen terisi dan
status `CHECKED_IN` tak pernah sekali pun dipakai sejak modul ini hidup. Route
`POST transaksitamu/check-in|check-out/{id}` digerbangi `visitorLaporanPage` —
sama dengan hak melihat papan tempat tombolnya berada.

Tiga hal yang wajib dipertahankan:

- **Kelompok "menginap" TIDAK disaring ke `Tanggal_Kunjungan` hari ini.** Tamu
  yang belum keluar sejak hari sebelumnya adalah baris terpenting di papan;
  penyaringan tanggal menyembunyikannya justru saat paling dibutuhkan.
- **Check-out menuntut sudah check-in, dan keduanya tak boleh berulang.** Tanpa
  itu tersimpan jam keluar tanpa jam masuk, atau jam masuk asli tertimpa tanpa
  jejak apa pun.
- **Angka ringkas diturunkan dari daftar yang sama**, bukan query terpisah. Dua
  hitungan yang berbeda tipis membuat kartu dan tabel berselisih diam-diam.

Analitik lanjutan (rata-rata lama kunjungan, tren, jam tersibuk) sengaja TIDAK
dibuat: sumbernya kolom yang masih nol persen terisi, sehingga angkanya akan
tampak meyakinkan padahal karangan. Tunggu datanya nyata.

Desain lengkap: `docs/superpowers/specs/2026-08-25-papan-harian-visitor-design.md`.

#### Pemisahan Monitoring dan Export Studio (2026-08-25)

Submodul `Reporting` kini memuat dua fitur berhalaman sendiri:

| Halaman | URL | Kunci | Fitur frontend |
|---|---|---|---|
| Monitoring | `/laporanvisitor` | `visitorLaporanPage` | `Reporting/Monitoring/` |
| Export Studio | `/visitor/export-studio` | `visitorExportStudioPage` | `Reporting/ExportStudio/` |

Monitoring **mewarisi** URL dan kunci lama; pendaftaran halaman baru
(`DML-2026-026`) murni penambahan sehingga tidak seorang pun kehilangan akses.
Sisi server sudah terpisah sejak awal — `app/…/Reporting/Export/` terhadap
`app/…/Reporting/Dashboard/` — jadi yang dipisah hanyalah shell frontend dan
pendaftaran halamannya.

Tiga hal yang wajib dipertahankan:

- **Gerbang API memakai kunci yang sama dengan gerbang halaman.** Menggerbangi
  halaman saja hanya menyembunyikan tombolnya; datanya tetap bisa ditarik
  langsung dari `api/v1/visitor/export-studio/...`.
- **Satu kunci, bukan `check.jabatan:A,B`.** Bentuk OR yang dipakai SOP adalah
  jembatan sementara sebelum DML menunya jalan; memakainya di sini membatalkan
  pemisahan yang jadi tujuan pekerjaan ini.
- **Prop `baseUrl` halaman Studio TANPA ruas dataset.** `VisitorExportStudioPanel`
  menyusunnya sendiri jadi `${baseUrl}/${datasetAktif}`; menambahkan dataset di
  shell membuat URL berdataset ganda dan seluruh permintaan Studio meleset.

Batas yang diketahui: baris `N_HRIS_Grup_Fitur_Map` wajib ada agar menunya
muncul, dan baris itu memberi akses kepada siapa pun pemegang role grup VISITOR.
Pemisahan yang benar-benar mengikat hanya ada di jalur `HRIS_Page_Access`
eksplisit — lihat [[rbac-role-keanggotaan-grup]] pada `module-registry.md`.

Dibuang pada pemisahan yang sama: `LaporanTamu.vue` dan `LaporanPermit.vue`
(834 baris, nol referensi sejak commit `ccc4d967` menggantinya dengan Export
Studio; `LaporanTamu.vue` bahkan memanggil endpoint `-permit` yang salah).
Endpoint `laporanvisitor-tamu` dan `laporanvisitor-permit` kini tanpa pemakai
dan sengaja dibiarkan.

Navigation mapping dikoreksi 2026-08-24: `visitorVerifikatorPage` dan
`visitorLaporanPage` dikelompokkan pada header existing `administrator_visitor`
/ `Administrator Visitor` melalui `DML-2026-025`. DML-2026-024 salah target dan
tidak boleh dipromosikan. Koreksi tidak mengubah ownership module VISITOR,
route, mapping grup fitur, atau page access.

Mapping terverifikasi 2026-08-24: page `visitorMasterDataPage`
(`/admin/visitor/master-data`) dimiliki modul `VISITOR`, submodul `Governance`,
fitur `MasterData`, dengan akses awal role operasional `SHE_ADMIN`. Basis live
dan delapan master terkait dicatat di `feature-mapping-visitor-master-data.md`.

Fitur baru 2026-08-24: `app/Services/VISITOR/Workflow/Notification/` (submodul
`Workflow`, fitur `Notification`) menerbitkan notifikasi in-app modul VISITOR.
Tidak ada perubahan skema: memakai `N_HRIS_Notification` lewat
`SYSTEM\Notification\NotificationService`, `Module = 'VISITOR'`.

Dua peristiwa dilayani:

1. **Pengajuan masuk antrian** → seluruh pemegang role `SHE_ADMIN` aktif,
   diresolusi lewat `N_HRIS_Role_User` × `N_HRIS_Role_Functional` →
   `Karyawan.UserID_Web` (wajib `DISTINCT`; keanggotaan role bisa berbaris kembar).
2. **Keputusan verifikator** (`DISETUJUI` final / `DITOLAK` / `DIBATALKAN`) →
   pemohon transaksi, dibaca dari `Kode_Karyawan_Pemohon` / `Kode_Karyawan_PIC`,
   bukan dari request. Approval bertahap sengaja tidak memberi notif.

`source` WAJIB `'MAIN_NOTIF'`: `AppTopbar.vue` dan `AppNotificationModal.vue`
menyaring kolom itu, sehingga nilai lain tersimpan tetapi tidak pernah terlihat.

Pemisahan Tamu Regular / B2B, diverifikasi 2026-09-18 di `Web_HRIS`
(development), grup `VISITOR` `Id_Grup_Fitur=4` (DDL-2026-121). Tiga page baru
milik modul `VISITOR`: `visitorTamuB2BPage` (`/transaksi-tamu-b2b`, submodul
`Guest`, fitur `Visit`), `visitorVerifikatorTamuB2BPage` (`/verifikator-visitor/b2b`,
`Workflow/Verification`), `visitorMonitoringTamuB2BPage` (`/monitoring-b2b`,
`Workflow/Reporting`), semuanya `Flag_Hibah_Role='T'`. Tidak ada controller atau
halaman Vue baru: tipe ditetapkan rute lewat middleware
`app/Http/Middleware/VISITOR/Guest/Visit/TetapkanTipeTamu.php` dan dibaca
controller yang sama. Berkas baru `Guest/Visit`: `TipeTamu`, `StatusKunjunganTamu`,
`AturanFormTamu`, `FotoKendaraanTamuService`.

`action_url` menautkan langsung ke detail transaksi — `?detail=<token>` untuk
halaman pemohon, `?detail=<token>&jenis=<permit|tamu>` untuk halaman verifikator
yang melayani dua domain. Pembacaannya lewat
`resources/js/components/VISITOR/tautanDetail.js` (`bacaRujukanDetail`), yang
meneruskan nilainya apa adanya ke endpoint `show()`.

Token dibuat `VisitorDetailToken` (`Crypt::encryptString('<domain>:<id>')`),
bukan Id berurutan. Tiga hal yang wajib dipertahankan saat menyentuhnya:

- **Ciphertext Crypt harus di-base64url-kan.** Keluaran mentahnya base64 biasa
  dan memuat `+`, `/`, `=`; satu `/` saja memecah ruas path dan permintaannya
  nyasar. Sudah terukur: dari 1.500 token lintas panjang payload, 600 memuat
  karakter itu — jadi tanpa transformasi ini kegagalannya kadang-kadang, bukan
  selalu, dan lolos dari uji contoh tunggal.
- **Domain ikut dikunci di dalam token.** Tanpa itu token permit membuka baris
  tamu ber-Id sama.
- **Keempat `show()` mendekode dulu dan membalas 404 saat gagal.** Meneruskan
  nilai non-numerik ke kolom `int` SQL Server melempar `QueryException` → 500.

Angka polos tetap diterima demi notifikasi lama yang sudah tersimpan dan klik
dari tabel di layar. Penyamaran ini BUKAN penjaga akses — yang menjaga tetap
penyaringan pemilik di `show()` kedua controller pemohon, dan itu tidak boleh
dicabut dengan alasan tautannya sudah tersamar.

#### Kuota pengajuan permit (2026-08-25)

Batas jumlah permit per karyawan tinggal di slot config yang sudah ada, bukan
di tabel baru dan bukan `const`: `services.visitor.permit.max_pengajuan_aktif`,
env `VISITOR_PERMIT_MAX_PENGAJUAN_AKTIF`, baku `2` di
`VisitorApprovalPolicyService::config('permit')`. `0` = tanpa batas. Kunci ini
ikut tujuh knob permit lain dan otomatis sampai ke Vue lewat prop `config`.

Baku sengaja `2`, bukan `0`: default nol membuat penjaganya tak pernah berbunyi
kalau satu env terlupa, tanpa gejala apa pun.

`VisitorPermitQuotaService` adalah SATU-SATUNYA tempat kuota dihitung —
`store()` dan `getStats()` sama-sama memanggilnya. Dua definisi yang berbeda
tipis membuat layar berkata "2/2 terpakai" sementara penjaganya menghitung 1.
Perhatikan `getStats()` masih menandai `permit_aktif` dengan `Flag_Approval='Y'`
saja, yang IKUT menghitung permit kedaluwarsa; angka kuota sengaja tidak
memakai definisi itu.

Satu jatah menutupi pengajuan yang masih diproses DAN permit yang hidup.
Statusnya diturunkan dari `permitStatusSql()`:

| Status | Jatah |
|---|---|
| `PENDING`, `NO_FLOW`, `VALIDITY_REQUIRED`, `APPROVED` | tertahan |
| `REJECTED`, `EXPIRED` | kembali |

Dua hal yang wajib dipertahankan:

- **Penjaga dipanggil SESUDAH pemeriksaan replay idempoten dan SEBELUM unggah
  berkas.** Di atas replay, pengiriman ulang yang sah ikut ditolak padahal tak
  menambah apa pun; di bawah unggahan, tiga berkas terlanjur naik untuk
  pengajuan yang ditolak.
- **Setiap status baru di `permitStatusSql()` wajib diputuskan** masuk daftar
  yang mana. `VisitorPermitQuotaServiceTest` mengenumerasi status dari SQL-nya
  dan akan MERAH kalau ada yang tak diputuskan — tanpa itu status baru diam-diam
  membebaskan jatah.

Lubang yang diketahui dan sengaja dibiarkan: dua pengiriman serentak bisa
sama-sama lolos pemeriksaan. Menutupnya butuh hint kunci rentang
(`UPDLOCK, HOLDLOCK`) pada hitungan itu; ditunda karena volumenya masih kecil.

### SYSTEM

Kandidat existing lintas modul:

```text
app/Http/Controllers/AuthController.php
app/Http/Controllers/MenuApprovalMatrixController.php
app/Http/Controllers/PageAccessController.php
app/Services/InternalConfig/
app/Services/MenuAccessService.php
app/Services/MenuBindingService.php
app/Services/MenuDelegationPolicyService.php
app/Services/Notification/
resources/js/Pages/Modules/Administrator/
resources/js/components/
resources/js/composables/
```

Target konseptual:

```text
SYSTEM/Authentication
SYSTEM/Governance/Access
SYSTEM/Governance/Menu
SYSTEM/Governance/ApprovalMatrix
SYSTEM/Notification
SYSTEM/Application
SYSTEM/UserPreferences
```

## Hotspot legacy yang perlu dipetakan manual

1. `app/Http/Controllers/` memiliki 64 file langsung di root.
2. `app/Models/` memiliki 37 model datar tanpa pemilik modul eksplisit.
3. `app/Services/` memiliki 18 service langsung di root dan service lintas domain bercampur.
4. `app/Jobs/` memiliki 16 job langsung di root.
5. `app/Console/Commands/` memiliki 51 command langsung di root.
6. `routes/web.php` masih menjadi file agregator besar.
7. `resources/js/Pages/` memiliki 47 file langsung di root, termasuk variasi lama dan file bernama `copy`, `stable`, atau eksperimen.
8. `resources/js/Pages/Modules/Administrator/` mencampur akses, approval, KPI, dan komponen role manager.
9. `tests/` sudah memiliki kategori, tetapi sebagian besar belum mengikuti `tests/Modules/<MODUL>`.
10. `vite.config.js` mengarah ke alias `@` pada `./src`, sedangkan folder `src/` tidak terdeteksi; ini harus menjadi task konfigurasi terpisah.

## Status pemetaan

- `EXISTING_LEGACY`: dibiarkan di lokasi saat ini.
- `TARGET_NEW`: fitur baru wajib dibuat mengikuti struktur target.
- `REVIEW_REQUIRED`: nama/konteks ambigu; agent harus memeriksa isi file dan meminta keputusan bila tetap ambigu.
- `MIGRATION_LATER`: kandidat dipindahkan hanya melalui task refactor terpisah dengan test dan review.

Tidak ada file source yang dipindahkan oleh pembuatan inventory ini.
