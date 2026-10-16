# RBAC "Akses & Peran" — Desain

Tanggal: 9 Oktober 2026 · Modul: `SYSTEM/Governance/Authorization` · Status: menunggu review user

Spec ini payung untuk seluruh pembenahan hak akses HCIS. Tiap fase (bagian 8)
mendapat rencana implementasi sendiri; rencana pertama hanya Fase 0.

## 1. Latar

HCIS sudah di production. Hak akses diputuskan per halaman oleh middleware
`check.jabatan` (`User::memilikiJabatan`, ±990 dari 1.378 route) lewat tiga
jalur yang digabung: grant langsung `HRIS_Page_Access`, hibah role
(`N_HRIS_Role_User` → `N_HRIS_Role_Functional` → `N_HRIS_Grup_Fitur_Map`
`Flag_Hibah_Role='Y'`), dan menu global. Pengelolaan role memakai keluarga
tabel `N_HRIS_Role*`; `KPI_Users.Role` sudah tidak dipakai.

Temuan pemindaian 6–9 Okt 2026 (dev `Web_HRIS`):

1. **Hibah role membuka halaman tata kelola.** Hampir semua halaman admin di
   ESS/KPI/PA/LMS/DMS/SYSTEM ber-`Flag_Hibah_Role='Y'`, sehingga role
   operasional (`HCIS_LEADER`, `ADMIN_HC_CUTI_VERIFIKATOR`, `GA_PHL_ADMIN`,
   role `*_PHL`) membuka Page Access Setup, Super Admin Setup, dan System Auth
   Manager. Delapan user di dev hanya lewat jalur ini.
2. **Page Access Setup** (`PageAccessController::updateAccess`) bisa memberi
   halaman apa pun ke siapa pun, termasuk diri sendiri, tanpa audit.
3. **Route lama hanya login**: `/master-periode` (termasuk simpan/hapus),
   `/divison`, `/employee`, `/level`, `/dashboardSuperUser`,
   `/storeCrossReviewPeriode`, `/destroyCrossPeriode` (`routes/web.php`).
4. **Sidebar ≠ pengunci route**: `AuthorizationScopeResolver` (sidebar) dan
   `User::memilikiAksesBaru` (route) menghitung berbeda; ada user yang bisa
   membuka 18–21 halaman yang tak tampil di menunya.
5. **Hak aksi melekat ke user, bukan role**: `N_HRIS_Role_Menu_Access`
   (2.005 baris) dikunci ke `Id_Page_Access`.
6. **Cakupan data rusak**: `N_HRIS_Scope_Definition.Custom_Condition` berisi
   SQL mentah dengan placeholder `:user_kode`/`:user_divisi` yang tak pernah
   diisi kode; `LEADER_DYNAMIC_TEAM` menunjuk `Karyawan_Team`.
7. **Delegasi & audit belum tersambung**: `canDelegateRole/Page` tak dipanggil;
   log admin hanya nilai baru; Page Access Setup, Akses Divisi/Level, Approval
   Flow Setup tak tercatat.
8. **Role ganda fungsi**: `ADMIN_PERMIT`, `ADMIN_TAMU*`, `TAMU_DEPT_*`,
   `ROLE_KPI_VERIF` juga menjadi pool approver/penerima notifikasi.
9. **System Admin (`KPI_Users.Is_System_Admin`) tidak otomatis lolos**
   `check.jabatan`; ia masuk karena grant langsung.

## 2. Tujuan dan bukan tujuan

Tujuan (keempatnya disepakati user):
- **Kelola mandiri**: akses diatur lewat layar, bukan DB/developer.
- **Keamanan di server**: setiap halaman, aksi, dan API dikunci di server.
- **Delegasi berjenjang** System Admin → Super Admin → Admin modul.
- **Audit lengkap**: siapa, apa, kapan, sebelum/sesudah, alasan.

Bukan tujuan:
- Mengganti mekanisme approval (`ApproverResolverService`, matriks approval).
  Spec ini hanya memisahkan *pool approval* dari *hak akses*.
- Login/SSO/kata sandi.
- Menghapus tabel lama (lihat 3.1).

## 3. Prinsip kompatibilitas (wajib)

1. **Aditif saja.** DDL hanya membuat tabel/kolom baru (nullable atau
   ber-default). Tabel lama tetap dibaca; tidak ada DROP/RENAME. Setiap DDL
   idempoten, lewat alur `database/ddl` + SQL Tracker, dan bisa dirilis
   sendiri.
2. **Toleran DDL belum jalan.** Kode memeriksa keberadaan tabel baru; bila
   belum ada, keputusan jatuh ke jalur lama tanpa galat.
3. **Sakelar per modul** di DB, tanpa deploy: `LAMA` (aturan lama saja),
   `BAYANGAN` (lama memutuskan; baru dihitung dan selisihnya dicatat),
   `BARU` (aturan baru memutuskan). Rollback = mengembalikan sakelar.
4. **Grant lama tetap dihormati** sampai dikonversi dengan persetujuan.
5. **Tak ada yang tiba-tiba kehilangan akses** kecuali penambalan celah
   Fase 0 yang disengaja dan didahului laporan dampak.
6. **Antarmuka lama tetap**: tanda tangan `check.jabatan` tidak berubah; layar
   admin lama tetap jalan sampai dipensiunkan; prop frontend lama tidak dihapus.

## 4. Model konsep

| Konsep | Arti | Sumber |
|---|---|---|
| Modul | Grup fitur (ESS, KPI, LMS, VISITOR, DMS, SYSTEM, BUILDER) | `N_HRIS_Master_Grup_Fitur` |
| Halaman | `Jenis_page` | `N_HRIS_Menus` + `N_HRIS_Grup_Fitur_Map` |
| Aksi | VIEW, CREATE, EDIT, DELETE, APPROVE, PRINT, SUMMARY, ASSIGN (+EXPORT bila perlu) | `N_HRIS_Klasifikasi_Aksi`, `N_HRIS_Menu_Capabilities` |
| Izin | halaman + aksi, atau kode izin bernama (`SHIFT_BYPASS_APPROVAL`) | baru: `N_HRIS_Role_Hak`; lama: `N_HRIS_Role_Permission` |
| Role | kumpulan izin, terikat satu modul, punya jenis | `N_HRIS_Role_Functional` (+ kolom `Jenis_Role`) |
| Penugasan | user + role + cakupan (+ tanggung jawab admin, + kedaluwarsa) | `N_HRIS_Role_User` (+ tabel anak) |
| Cakupan | jenis tetap di kode + nilai di DB | baru: `N_HRIS_Role_User_Cakupan` |
| Pengecualian | grant langsung per user, beralasan, bisa kedaluwarsa | `HRIS_Page_Access` (+ kolom) |

### 4.1 Perubahan data (semua aditif)

| Objek | Isi |
|---|---|
| `N_HRIS_Role_Hak` (baru) | `Id_Role_Hak`, `Id_Role`, `Jenis_page`, `Id_Aksi`, `Flag_Aktif`, `Created_At/By`, `Updated_At/By`; unik (`Id_Role`,`Jenis_page`,`Id_Aksi`) |
| `N_HRIS_Role_Functional` | + `Jenis_Role` varchar(20) default `'AKSES'`: `AKSES` · `ADMIN` · `POOL_APPROVAL` |
| `N_HRIS_Role_User` | + `Berlaku_Sampai` datetime NULL, + `Alasan` varchar(500) NULL |
| `N_HRIS_Role_User_Cakupan` (baru) | `Id`, `Id_Role_User`, `Jenis_Cakupan` (`SEMUA`,`ENTITAS`,`LOKASI`,`DIVISI`,`DEPARTEMEN`,`TIM`,`PHL`), `Nilai` varchar(100) NULL, `Flag_Aktif` |
| `N_HRIS_Role_User_Tanggung_Jawab` (baru) | `Id`, `Id_Role_User`, `Kode` (`AKSES`,`APPROVAL`,`KEANGGOTAAN`) — hanya untuk penugasan role `ADMIN` |
| `HRIS_Page_Access` | + `Alasan` varchar(500) NULL, + `Berlaku_Sampai` datetime NULL |
| `N_HRIS_Akses_Mode_Modul` (baru) | `Id_Grup_Fitur` PK, `Mode` (`LAMA`/`BAYANGAN`/`BARU`) default `LAMA`, `Updated_At/By` |
| `N_HRIS_Akses_Selisih` (baru) | `Id`, `Id_User`, `Jenis_page`, `Aksi`, `Keputusan_Lama`, `Keputusan_Baru`, `Jumlah`, `Pertama_At`, `Terakhir_At`; unik (`Id_User`,`Jenis_page`,`Aksi`) |
| `N_HRIS_Akses_Audit` (baru, DDL-2026-164) | `Id_Akses_Audit`, `Waktu`, `Oleh_Id_User`, `Target_Id_User`, `Subjek_Jenis` (ROLE/PENUGASAN/GRANT/KATALOG/MODE), `Subjek_Id`, `Aksi`, `Nilai_Sebelum` nvarchar(max) JSON, `Nilai_Sesudah` nvarchar(max) JSON, `Alasan`, `Sumber` (UI/MIGRASI/SISTEM) |

Tidak disentuh: `Flag_Hibah_Role`, `N_HRIS_Role_Menu_Access`,
`N_HRIS_Scope_Definition` — tetap dibaca jalur LAMA, tidak ditulis jalur BARU.
Role `SUPER_ADMIN` lintas modul dibuat lewat DML di grup SYSTEM
(`HCIS_SUPER_ADMIN`, `Jenis_Role='ADMIN'`).

### 4.2 Cakupan sebagai kode

Satu registri di `app/Services/SYSTEM/Governance/Authorization/Scope/`:
setiap `Jenis_Cakupan` adalah kelas yang menerapkan filter ke query karyawan
berdasarkan `Nilai`. Tidak ada SQL dari DB atau UI. `PHL` menyalin aturan
`SCOPE_GA_PHL` sekarang; `TIM` memakai `HRIS_Karyawan_Team`.

## 5. Titik keputusan tunggal

Layanan `KeputusanAkses` (`app/Services/SYSTEM/Governance/Authorization/Akses/`):

- `boleh(User $u, string $halaman, string $aksi = 'VIEW'): bool`
- `cakupan(User $u, string $halaman, string $aksi): Cakupan` — untuk query data (dibuat di Fase 2)
- `telusuri(User $u, string $halaman, string $aksi): array` — jalur yang
  menghasilkan ya/tidak, untuk layar "Telusuri akses"

Aturan per modul halaman menurut `N_HRIS_Akses_Mode_Modul`:
- `LAMA`: hasil = `User::memilikiJabatan` sekarang (persis).
- `BAYANGAN`: hasil = LAMA; BARU dihitung, bila beda → upsert `N_HRIS_Akses_Selisih`.
- `BARU`: hasil = System Admin **atau** izin role aktif (`N_HRIS_Role_Hak`,
  belum kedaluwarsa) **atau** grant langsung aktif belum kedaluwarsa **atau**
  menu global. Sejak Fase 5 grant langsung dan menu global memberi semua aksi
  halaman itu (sama dengan perilaku gerbang route hari ini).

Pemakai: `check.jabatan` (aksi VIEW), middleware baru `akses:halaman,aksi`
untuk aksi tulis, `InertiaShellService` (sidebar), prop Inertia `akses`
(tombol di layar), dan pengecekan di service. Cache per user per request +
versi akses global (dinaikkan setiap mutasi) untuk membatalkan cache lintas
request.

## 6. Distribusi

| Level | Bentuk | Boleh | Tidak boleh |
|---|---|---|---|
| System Admin | flag `Is_System_Admin` | katalog, sakelar modul, kebijakan delegasi, angkat Super Admin | — |
| Super Admin | role `HCIS_SUPER_ADMIN` | semua role & penugasan di semua modul, angkat Admin modul | katalog, angkat Super/System Admin |
| Admin modul | role `{MODUL}_ADMIN` (`Jenis_Role='ADMIN'`) + centang tanggung jawab per penugasan | tugaskan role modulnya ke user dalam cakupannya | modul lain, buat/angkat role ADMIN |
| Pengguna | role `AKSES` | memakai | memberi |

Role admin lama (`{MODUL}_MENU/APPROVAL/MEMBERSHIP_ADMINISTRATOR`) dipetakan
ke `{MODUL}_ADMIN` dengan tanggung jawab AKSES/APPROVAL/KEANGGOTAAN.

Aturan anti-eskalasi, dicek di server oleh `AturanDelegasi`:
1. Izin yang diberikan ⊆ izin pemberi.
2. Tak boleh mengubah akses diri sendiri.
3. Cakupan penerima ⊆ cakupan pemberi; target user harus di dalam cakupan pemberi.
4. Halaman `SYSTEM_ONLY` (`N_HRIS_Menu_Delegation_Policy`) hanya System Admin.
5. Role `ADMIN` hanya diangkat level di atasnya.
6. Semua mutasi lewat satu layanan yang menulis `N_HRIS_Akses_Audit` dalam
   transaksi yang sama.

## 7. UI/UX — pusat "Akses & Peran"

Satu halaman Inertia di `resources/js/Pages/SYSTEM/Governance/Authorization/AksesPeran/`,
komponen design system (`Hcis*`), tabel berubah jadi kartu di layar kecil.

| Tab | Isi | Siapa |
|---|---|---|
| Ringkasan | jumlah role/admin per modul, grant hampir kedaluwarsa, status sakelar, jumlah selisih bayangan | semua admin |
| Peran | daftar role per modul; detail = matriks halaman × aksi, pemegang, cakupan | admin (sesuai modul) |
| Pengguna | cari karyawan → role+cakupan, pengecualian, halaman efektif, tombol **Telusuri akses** | admin |
| Admin & Delegasi | admin tiap modul, tanggung jawab, cakupan | Super Admin+ |
| Audit | linimasa sebelum→sesudah, filter user/role/modul | admin (sesuai modul) |
| Katalog | modul, halaman, aksi, kebijakan delegasi, sakelar modul, laporan selisih | System Admin |

Prinsip: pratinjau dampak sebelum simpan (+N mendapat, −M kehilangan), alasan
wajib, kontrol di luar wewenang tampil terkunci dengan keterangan, layar lama
diberi banner "Kini tersedia di Akses & Peran" sampai dipensiunkan.

## 8. Fase

| Fase | Isi | DDL | Dampak user |
|---|---|---|---|
| 0 | Tambal celah: halaman tata kelola tak bisa didapat lewat hibah role; `updateAccess` dibatasi aturan delegasi + diaudit; route lama diberi pengunci | tidak | hanya menutup celah |
| 1 | `KeputusanAkses` (mode LAMA saja) dipakai route + sidebar; `N_HRIS_Akses_Audit` | ya | tidak ada |
| 2 | Tabel role→hak, cakupan, tanggung jawab, kolom tambahan; konversi awal data; layar Akses & Peran baca-saja | ya | tidak ada |
| 3 | Mode BAYANGAN per modul + laporan selisih; layar tulis | ya | tidak ada |
| 4 | BARU per modul, mulai pilot | tidak | bertahap |
| 5 | Pensiunkan jalur lama (berhenti membaca, tanpa DROP) | tidak | tidak ada |

**Fase 0 rinci.** Halaman tata kelola (config `authorization.tata_kelola.halaman`):
`adminAllPagesAccess`, `superAdminManajemenPeranPage`, `systemAuthManagerPage`,
`menuApprovalMatrixPage` (gerbang induk Menu & Matrix), `menuApprovalMatrix{Ess,Kpi,Lms,Visitor}Page`,
`ManajemenAksesDivisi`, `ManajemenAksesLevel`, `ActivityMonitor`. Untuk halaman ini jalur hibah hanya
berlaku bila role-nya admin sah (`authorization.tata_kelola.peran_admin`);
grant langsung dan System Admin tetap. Jalur delegasi kedua (Menu & Matrix,
`MenuDelegationPolicyService`) mengunci halaman yang sama untuk non-System-Admin.
System Admin mengelola seluruh menu di Page Access Setup. Halaman setup operasional
(`addKaryawanTeam`, `adminKPIKaryawanTeamPage`, `adminHCApprovalKPIPage`,
`approvalFlowShift`) ditunda; cukup menambah ke `authorization.tata_kelola.halaman`
bila diputuskan. Sebelum rilis, laporan dampak
(`database/runbook/2026-10-09-rbac-fase0-laporan-dampak.sql`) dijalankan di prod
dan admin sah yang hanya lewat hibah diberi grant langsung atau role-nya
ditambahkan ke daftar admin sah.

**Fase 1 (selesai di branch `feat/system/rbac-fase1`).** `KeputusanAkses::boleh`
(mode LAMA) = System Admin **atau** `User::memilikiJabatan`; dipakai `check.jabatan`
dan sidebar (`InertiaShellService::menuIsVisible`) sehingga menu tampil = halaman
yang bisa dibuka. Keputusan user 9 Okt 2026: sidebar mengikuti gerbang (bukan
sebaliknya) dan System Admin boleh semua halaman — tak ada yang kehilangan akses.
Jejak perubahan role, Page Access, dan `AuthorizationAuditLogger::logMutation`
masuk `N_HRIS_Akses_Audit` (toleran tabel belum ada).

**Fase 2a (selesai di branch `feat/system/rbac-fase2`).** DDL-2026-165 (model) +
DML-2026-166 (konversi: hak role dari hibah tersaring × capability default; Jenis_Role
label saja — semua jenis tetap mendapat hak hasil konversi supaya tak ada yang
kehilangan akses; cakupan dari Role_Scope; tanggung jawab dari pola nama role admin).
`KeputusanAkses::bolehBaru()` terbukti setara `boleh()` untuk semua pemegang role ×
semua menu di dev. Halaman tanpa capability mendapat VIEW saja.

**Fase 3a (selesai di branch `feat/system/rbac-fase3`).** DDL-2026-168 (mode per modul
+ selisih). `rbac:sinkron-hak` (terjadwal tiap jam, DML-2026-169) menyamakan
`N_HRIS_Role_Hak` dengan hibah untuk modul LAMA/BAYANGAN — menambah, mengaktifkan lagi,
menonaktifkan (tak pernah menghapus); modul BARU tidak disentuh (hak role milik admin
sejak itu). `KeputusanAkses::boleh` menerapkan mode modul pemilik halaman (baris map
pertama). Tab Katalog (System Admin): ubah mode (alasan wajib, diaudit), lihat &
tandai selisih, sinkron manual. Semua modul tetap LAMA sampai System Admin mengubahnya.
Catatan: §9 "POOL_APPROVAL tidak memberi halaman di BARU" TIDAK berlaku — konversi
memberi semua jenis role hak yang sama dengan kondisi lama supaya tak ada yang
kehilangan akses; pemisahan pool approval menunggu Fase 3b/4.

**Fase 4 (selesai di branch `feat/system/rbac-fase4`).** Semua keputusan "boleh buka
halaman" lewat `KeputusanAkses::boleh`: layar Visitor (verifikator, laporan, dashboard),
Panduan, Gate `akses-svp` (`User::isSupervisor`), lapis halaman
`AuthorizationScopeResolver::canAccessPage` (dipakai `authorize.resource`,
`ApprovalMatrixService`, `canSeeMenu`), dan SOP Export Studio. Uji penjaga menolak
pemanggil `memilikiJabatan` baru di luar `KeputusanAkses`. `telusuri` mengikuti mode
modul (BARU → `HAK_PERAN:<kode>`; BAYANGAN membawa `jalur_baru`). Mode BARU dibuka
(`RBAC_MODE_BARU_DIIZINKAN`, bawaan `true`, sebagai rem darurat), hanya dari BAYANGAN,
dan didahului sinkron hak terakhir (sinkron sedang berjalan → 409). Lapis resource &
aksi `authorize.resource` serta resolver approval tetap aturan lama (Fase 5).

**Fase 5 (5a–5c selesai di branch `feat/system/rbac-fase5`; 5d ditunda).** 5b: request
non-GET ke rute `check.jabatan` = aksi tulis (`KeputusanAkses::bolehTulis`) — LAMA tak berubah;
BARU butuh aksi non-VIEW peran yang dikenal katalog halaman (halaman tanpa aksi tulis cukup
VIEW); grant langsung & menu global = semua aksi; BAYANGAN mencatat selisih `TULIS`.
Middleware `akses:halaman,aksi` untuk aksi spesifik; lapis aksi `authorize.resource` di modul
BARU lewat `KeputusanAkses`. 5a: banner "Kini dikelola di Akses & Peran" di System Manager,
Role Manager, Page Access, Menu & Matrix (peringatan bila modulnya BARU); simpan binding grup
fitur kini berbasis selisih (dulu hapus-sisip ulang menghidupkan hibah yang dimatikan).
5c: laporan kesiapan per modul di Katalog (sinkron kering). 5d — hapus kode aturan lama —
menunggu semua modul prod BARU; tabel tidak pernah di-DROP.
Sebelum modul yang katalognya punya aksi tulis pindah ke BARU: tandai rute POST yang hanya
membaca dengan `->defaults('rbac_aksi', 'VIEW')` (daftar kandidat: rencana Fase 5). Katalog
halaman modul BARU yang baru mendapat aksi tulis otomatis memberi aksi itu ke peran pemegang
VIEW (diaudit `TAMBAH_HAK_KATALOG`).

**Super Admin & Admin DMS (branch `feat/system/rbac-super-admin`, DML-2026-180).** `HCIS_SUPER_ADMIN`
tanpa modul induk; jangkauan semua modul selain SYSTEM; boleh mengangkat/mencabut admin modul
(bukan Super Admin), tidak mengubah katalog/mode/hak peran admin. Aturan SYSTEM_ONLY tetap berlaku
baginya. `DMS_ADMIN` = admin modul DMS. Rincian: `docs/superpowers/plans/2026-10-10-rbac-super-admin.md`.

**Pilot Fase 4 (dev, 10 Okt 2026).** DMS LAMA → BAYANGAN → BARU, selisih 0. Rekomendasi DMS: 12 halaman admin, nol user yang bergantung
pada hibah di dev, bukan pool approval. Keputusan akhir memakai laporan
selisih Fase 3.

## 9. Risiko dan penanganan

| Risiko | Penanganan |
|---|---|
| Admin sah terkunci (Fase 0) | laporan dampak prod + grant resmi sebelum rilis |
| Data prod beda dari dev (kode grup `VMS`, hibah ditambal manual) | semua keputusan dari SELECT di prod |
| Pool approval berubah diam-diam | `Jenis_Role='POOL_APPROVAL'` tidak memberi halaman di BARU; resolver approval tidak diubah |
| Cakupan yang diperbaiki mengubah data yang terlihat | masuk mode BAYANGAN juga (selisih cakupan dicatat) |
| Kinerja | cache per request + versi akses; satu query gabungan per user |
| Cache menu basi | versi akses ikut dalam kunci cache menu |

## 10. Pengujian

- Unit: `KeputusanAkses` tiap mode; aturan anti-eskalasi 1–5; registri cakupan.
- Feature: Leader tak bisa membuka halaman tata kelola (Fase 0); `updateAccess`
  menolak halaman di luar wewenang dan mencatat audit; route lama butuh izin.
- Kesetaraan: dalam mode LAMA, `KeputusanAkses::boleh` = `memilikiJabatan`
  untuk seluruh pasangan user × halaman di dev.
- Penjaga: sidebar dan route memanggil layanan yang sama;
  `RuteTulisSelaluBergerbangTest` diperluas ke route lama.
- Browser: akun Leader, admin modul, Super Admin, System Admin di dev.
