# Desain: Permit Coklat — Izin Akses Tamu Berbasis Jendela Jam

Tanggal: 2026-09-08
Modul: `VISITOR`
Change ID DDL: `DDL-2026-077-visitor-permit-coklat`

## Konteks & Masalah

Modul Visitor hari ini punya dua alur yang terpisah penuh:

- **Permit kendaraan** — diajukan **karyawan** untuk kendaraannya sendiri. Berkuota per karyawan, wajib tiga foto (kendaraan, STNK, SIM), lewat approval, dan masa berlakunya **dalam bulan** (`N_EMI_VISITOR_Master_Durasi_Permit` hanya berisi 1/3/6 bulan, seluruhnya `Satuan_Waktu='MONTH'`). Kategorinya master data: saat ini hanya `HIJAU` ("Akses hanya sampai Area Parkir") dan `MERAH` ("Akses penuh sampai Area Boiler") yang aktif.
- **Registrasi tamu** — didaftarkan karyawan PIC untuk tamunya. Punya `Tanggal_Kunjungan`, plat dan jenis kendaraan yang **opsional**, dan approval sendiri.

Yang diminta: jenis permit baru, **COKLAT**, yang masa berlakunya dihitung **jam**, diperuntukkan bagi **tamu**, dan terbit **otomatis** untuk setiap tamu yang didaftarkan — bukan diajukan terpisah.

Kesulitannya bukan pada menambah kategori (itu satu baris master data), melainkan pada kata "jam" dan kata "otomatis":

1. Seluruh alur masa berlaku permit mengunci satuan bulan. `VisitorApprovalDecisionService::resolveDuration()` menolak `Satuan_Waktu` selain `MONTH`, dan `PermitValidityPeriodCalculator::calculate()` melempar `InvalidArgumentException` untuk durasi di bawah satu bulan.
2. Kolom `Tanggal_Mulai_Berlaku` dan `Tanggal_Kadaluarsa` bertipe **`date`** di `N_EMI_VISITOR_Transaksi_Permit_Detail` maupun `N_VISITOR_Permit_Efektif`. Jam yang ditulis ke sana hilang tanpa galat.
3. Registrasi tamu **tidak punya jendela jam yang bisa dipakai**. `Waktu_Estimasi_Kunjungan` adalah `varchar(50)` yang diisi dropdown label kasar (`< 30 menit`, `1-2 jam`, `2-4 jam`, `> 4 jam`, `Seharian`) yang di-hardcode di `GuestRegistrationController::getMasterData()`. Datanya pun sudah bercampur — ada baris berisi `13:30`. Kolom ini tidak bisa menjawab "tamu ini berlaku sampai jam berapa".
4. `N_VISITOR_Permit_Efektif` **bukan tabel internal**. Sesuai `docs/superpowers/specs/2026-07-30-visitor-efektif-projection-design.md`, ada aplikasi gate/security di luar repo ini yang menariknya langsung lewat koneksi SQL Server (baca `Flag_Sync='T'` → salin ke DB gate → tandai `'Y'`). Setiap perubahan bentuk kolomnya adalah perubahan kontrak integrasi.
5. Sapuan kedaluwarsa `visitor:expire-efektif` berjalan **sekali sehari** (`5 0 * * *`) dan membandingkan dengan `whereDate(...)`. Untuk permit berjam-jam, ketelitian itu tidak memadai.

## Keputusan Desain

Keputusan berikut sudah disepakati bersama user dan **bukan** untuk ditawar ulang saat implementasi.

| # | Keputusan | Alasan |
|---|-----------|--------|
| 1 | Permit coklat **turunan otomatis** dari registrasi tamu. Tidak ada layar pengajuan permit terpisah, tidak menyentuh alur permit karyawan. | "Setiap tamu langsung ter-regist" jadi jaminan struktural, bukan disiplin manusia. Kuota per karyawan, tiga foto wajib, dan approval permit semuanya tidak masuk akal untuk tamu. |
| 2 | Jendela berlaku **dipilih pemohon** saat mendaftarkan tamu (jam mulai + jam selesai), **boleh lintas hari**. | Diminta user. Kunjungan shift malam dan kunjungan yang menyeberang tengah malam harus terwakili. |
| 3 | **Semua tamu** dapat permit coklat, termasuk pejalan kaki. Plat nomor opsional. | Data tidak bisa dipercaya untuk memutuskan "bawa kendaraan atau tidak": dari 83 baris detail tamu di development, **29 tanpa plat** tetapi hanya 3 yang menyatakan `Tidak Berkendara` dan 6 lagi `Jenis_Kendaraan` kosong. Permit bersyarat kendaraan akan menghasilkan permit yang hilang diam-diam. Lagipula `Deskripsi_Akses` mendefinisikan **area yang boleh dimasuki** — itu sifat orang, bukan sifat kendaraan. |
| 4 | Permit berakhir pada **mana yang lebih dulu**: jam selesai jendela, atau tamu tercatat keluar. | Diminta user; lebih ketat secara keamanan. |
| 5 | **Kolom waktu aditif**, bukan mengubah tipe kolom `date` yang ada. Kolom `date` lama tetap diisi turunan tanggal dari jendela. | Satu-satunya opsi yang tidak menuntut rilis terkoordinasi dengan pemilik aplikasi gate. Gate yang belum diperbarui tetap membaca permit coklat di ketelitian hari; adopsi jam bisa menyusul kapan pun. |
| 6 | Permit coklat memakai **`No_Transaksi` tamunya sendiri** (`VSR-…`) sebagai kunci baris di `N_VISITOR_Permit_Efektif`. | Kolomnya sudah `UNIQUE`, jadi upsert idempoten didapat gratis dan korelasi ke registrasi jadi trivial. Menghindari pemanggilan `generateNoTransaksi()` sekali lagi per tamu — pembangkit nomor itu pernah jadi sumber beban CPU (lihat catatan non-sargable `generate No_Transaksi`). |
| 7 | Permit coklat **tidak memilih baris master durasi**. `Id_Master_Durasi` tetap `NULL`. | Jendelanya datang dari registrasi tamu. Konsekuensinya penjaga `MONTH` di `resolveDuration()` **dibiarkan utuh** — penjaga itu melindungi permit karyawan dan tidak perlu dilonggarkan untuk alur yang tidak melewatinya. Master durasi tidak perlu opsi `HOUR` sama sekali. |
| 8 | Tidak menambah nilai baru pada enum `Status_Efektif`. Tetap `AKTIF` / `DIBATALKAN` / `EXPIRED`. | Enum itu kontrak yang dibaca aplikasi gate. Sebab berakhirnya dibedakan lewat `Alasan_Status`, bukan lewat status baru. |

## Lingkup

**Termasuk** — pengajuan dan monitoring di HCIS:
- Kategori `COKLAT` di master.
- Jendela jam pada registrasi tamu.
- Penerbitan, pencabutan, dan pengakhiran permit coklat di proyeksi Efektif.
- Tampilan jendela di form tamu, status permit di riwayat tamu, dan pemisahan permit karyawan vs permit tamu di monitoring/dashboard/export.

**Tidak termasuk** — diserahkan ke aplikasi lain, ditegaskan user:
- Kartu digital / QR / tautan bukti untuk tamu.
- Bukti cetak permit coklat untuk PIC.
- Apa pun yang terjadi di pos gerbang saat tamu datang.

## Model Data & DDL

Satu change ID: `database/ddl/DDL-2026-077-visitor-permit-coklat/forward.sql` (plus `drop.sql` sebagai alat bantu bersih-bersih manual, tidak dieksekusi agent). Baris master `COKLAT` masuk `database/dml/DML-2026-077-visitor-permit-coklat/data-change.sql` — DDL dan DML tidak dicampur.

### Master

`N_EMI_VISITOR_Master_Kategori_Permit` — satu baris baru (DML):

| Kolom | Nilai |
|-------|-------|
| `Nama_Kategori` | `COKLAT` |
| `Deskripsi_Akses` | ditentukan user sebelum eksekusi — menentukan area yang dibaca gate |
| `Kode_Warna_UI` | `#795548` |
| `Flag_Aktif` | `Y` |

Katalog master data (`VisitorMasterDataCatalog`) **tidak berubah**. Deskripsi entri `durasi-permit` yang berbunyi *"Flow aktif saat ini menggunakan satuan bulan"* tetap benar dan dibiarkan.

### Registrasi tamu — `N_EMI_VISITOR_Transaksi_Tamu_Detail`

| Kolom baru | Tipe | Keterangan |
|-----------|------|------------|
| `Waktu_Mulai_Kunjungan` | `datetime2(0) NULL` | awal jendela |
| `Waktu_Selesai_Kunjungan` | `datetime2(0) NULL` | akhir jendela; boleh jatuh di tanggal berikutnya |

`Tanggal_Kunjungan` di tabel header **tidak berubah maknanya** — tetap tanggal mulai. Papan harian, dashboard, laporan, dan penyaring tanggal yang bergantung padanya tidak goyah. Kunjungan lintas hari ditampung `Waktu_Selesai_Kunjungan`.

`Waktu_Estimasi_Kunjungan` (varchar teks bebas) **dipensiunkan, bukan dihapus**: berhenti dipakai untuk keputusan apa pun, kolomnya dipertahankan sebagai arsip karena sudah berisi data riil.

Nullable, bukan `NOT NULL`: baris tamu lama tidak punya jendela dan tidak boleh dipaksa mengarang satu. Kewajiban mengisi ditegakkan di validasi pengajuan baru, bukan di skema.

### Transaksi permit — `N_EMI_VISITOR_Transaksi_Permit_Detail`

**Tidak diubah.** Permit coklat tidak pernah membuat baris transaksi permit — ia lahir langsung sebagai proyeksi dari registrasi tamu. Menambahkan kolom waktu di sini akan menghasilkan kolom yang tidak pernah ditulis siapa pun.

### Proyeksi — `N_VISITOR_Permit_Efektif`

| Kolom baru | Tipe | Keterangan |
|-----------|------|------------|
| `Waktu_Mulai_Berlaku` | `datetime2(0) NULL` | ketelitian jam; dibaca gate setelah gate siap |
| `Waktu_Berakhir` | `datetime2(0) NULL` | dipakai sapuan kedaluwarsa cabang jam |
| `Sumber_Permit` | `varchar(10) NULL` | `'KARYAWAN'` / `'TAMU'` |

**Invarian yang wajib dijaga di satu tempat penulisan** (`VisitorEfektifService`): selama `Waktu_Mulai_Berlaku`/`Waktu_Berakhir` terisi, `Tanggal_Mulai_Berlaku`/`Tanggal_Kadaluarsa` **wajib** ikut terisi sebagai turunan tanggalnya (`CAST(... AS date)`). Ini yang membuat aplikasi gate lama tetap melihat permit coklat. Aturan ini tidak boleh disalin ke pemanggil mana pun.

Pengisian kolom `NOT NULL` yang sudah ada untuk permit coklat:

| Kolom | Diisi dengan |
|-------|--------------|
| `Kode_Karyawan_Pemohon`, `Nama_Pemohon` | karyawan PIC yang mendaftarkan tamu |
| `No_Plat_Kendaraan` | plat tamu, boleh kosong (pejalan kaki) |
| `Nama_Kategori_Permit`, `Deskripsi_Akses`, `Kode_Warna_UI` | snapshot master `COKLAT` |
| `Nama_Durasi` | label turunan dari jendela, mis. `"6 jam"` |
| `Keterangan_Pengajuan` | keperluan + identitas tamu (nama, instansi) |
| `Disetujui_Oleh_Nama` / `Disetujui_At` / `Riwayat_Approval` | mengikuti pola `syncTamuEfektif` yang ada — approver langkah terakhir, atau `"Sistem (Auto-Approve)"` |
| `Sumber_Permit` | `'TAMU'` |

Permit karyawan yang sudah ada di-backfill `Sumber_Permit='KARYAWAN'` lewat DML change yang sama, dan `syncPermitEfektif()` mulai menulisnya.

## Alur Penerbitan

Penulis tunggal tetap `VisitorEfektifService` — satu-satunya kelas yang boleh menulis tabel Efektif. Tambahannya:

- `syncPermitCoklatEfektif(string $noTransaksiTamu): void` — resolve dari `baseTamuQuery()`, hitung jendela, upsert satu baris `N_VISITOR_Permit_Efektif` ber-`No_Transaksi` sama dengan tamunya, `Status_Efektif='AKTIF'`, `Flag_Sync='T'`. Idempoten. No-op kalau jendelanya kosong (baris tamu lama).
- `revokePermitCoklatEfektif(string $noTransaksiTamu, ?string $alasan, string $olehKode): void` — no-op kalau barisnya tidak ada.

### Kelengkapan pintu

Ini bagian yang paling menentukan benar-tidaknya fitur. Pola bug yang berulang di proyek ini adalah aturan yang hanya dipasang di sebagian jalur setara. Berikut **seluruh** tempat yang menulis proyeksi tamu hari ini, hasil penelusuran menyeluruh, dan semuanya harus ikut:

| # | Lokasi | Aksi sekarang | Tambahan |
|---|--------|---------------|----------|
| 1 | `GuestRegistrationController::store()` (cabang auto-approve, **di dalam** transaksi) | `syncTamuEfektif()` | terbitkan permit coklat, di dalam transaksi yang sama |
| 2 | `VisitorApprovalDecisionService::approveTamu()` (setelah `isApprovalComplete()`) | `syncTamuEfektif()` | terbitkan permit coklat |
| 3 | `GuestRegistrationController::destroy()` | `revokeTamuEfektif()` | cabut permit coklat |
| 4 | `VerifikatorVisitorController` (revoke tamu) | `revokeTamuEfektif()` | cabut permit coklat |
| 5 | `SeedPapanHarianDemo` | `syncTamuEfektif()` | terbitkan permit coklat — kalau dilewat, papan demo berbohong |

`GuestRegistrationController::cancel()` **tidak** perlu hook: ia hanya melayani pengajuan yang masih `MENUNGGU_APPROVAL` dan belum disentuh approver, jadi barisnya belum pernah ada di proyeksi.

`rejectTamu()` juga tidak: tamu yang ditolak tidak pernah jadi efektif.

### Lubang yang ikut ditambal

`GuestRegistrationController::update()` mengubah `Tanggal_Kunjungan` **tanpa menyentuh proyeksi sama sekali**. Ini cacat yang **sudah ada sekarang** — mengubah tanggal kunjungan hari ini pun meninggalkan data basi di gerbang — dan fitur ini mempertajamnya karena jendela kini menentukan masa berlaku permit. Ditambal dalam pekerjaan yang sama: setiap perubahan jendela atau tanggal menyinkronkan ulang proyeksi tamu **dan** permit coklatnya.

## Akhir Masa Berlaku

Command `visitor:expire-efektif`, cadence dinaikkan di `N_HRIS_Scheduler_Configs` dari `5 0 * * *` menjadi `*/5 * * * *`.

Tiga cabang:

1. **Permit karyawan** — tidak berubah: `Status_Efektif='AKTIF' AND Tanggal_Kadaluarsa < hari ini`, dibatasi ke permit non-tamu supaya permit coklat tidak ikut dinilai dengan ketelitian hari. Penyaringnya **wajib** ditulis `ISNULL(Sumber_Permit, 'KARYAWAN') <> 'TAMU'`, bukan `Sumber_Permit <> 'TAMU'`: kolomnya nullable, dan perbandingan terhadap `NULL` menghasilkan `UNKNOWN` sehingga baris yang belum ter-backfill akan diam-diam berhenti dikedaluwarsakan.
2. **Permit coklat, jam habis** — `Sumber_Permit='TAMU' AND Status_Efektif='AKTIF' AND Waktu_Berakhir < sekarang`.
3. **Permit coklat, tamu sudah keluar** — join ke `N_VISITOR_Tamu_Efektif` dan detail tamu lewat `No_Transaksi`, memakai **definisi kehadiran resmi** dari `VisitorKehadiranQueryService`: `COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar) IS NOT NULL`.

Cabang 3 **tidak boleh** memakai `Status_Kunjungan='CHECKED_OUT'`. Kolom itu hanya ditulis tombol manual HCIS; tamu yang lewat palang tetap berstatus `TERJADWAL` selamanya, karena kehadiran ditulis aplikasi palang langsung ke `N_VISITOR_Tamu_Efektif`. Memakai `Status_Kunjungan` berarti permit tamu yang benar-benar sudah pulang tidak pernah mati.

Ketiganya menulis `Status_Efektif='EXPIRED'`, `Alasan_Status` yang membedakan sebab (`"Jendela kunjungan berakhir"` vs `"Tamu tercatat keluar"`), `Updated_Oleh_Nama='Sistem (Auto-Expire)'`, dan reset `Flag_Sync='T'`.

Cabang tamu di sapuan yang sekarang memakai `whereDate('Tanggal_Kunjungan','<',$today)` **harus pindah** ke `Waktu_Selesai_Kunjungan` bila terisi, kalau tidak kunjungan lintas hari kedaluwarsa kepagian.

Karena tombol check-out manual HCIS adalah pintu kedua yang setara, `GuestRegistrationController::checkOut()` ikut memanggil penutupan permit langsung — supaya jalur manual tidak menunggu sapuan.

## Perubahan Tampilan

1. **`TamuForm.vue`** — dropdown `waktu_estimasi` diganti isian jendela: **mulai** (tanggal + jam) dan **selesai** (tanggal + jam), tanggal selesai default mengikuti tanggal mulai tapi bisa digeser. Validasi: selesai harus setelah mulai. Daftar `waktuEstimasi` yang di-hardcode di `getMasterData()` ikut dipensiunkan.
2. **Langkah pratinjau `TamuForm.vue`** — menampilkan jendela jam dan keterangan bahwa permit coklat terbit otomatis.
3. **`TamuHistory.vue`** — menampilkan status permit coklat per tamu: masih berlaku / habis / dicabut.
4. **Monitoring, dashboard, Export Studio** — setiap agregasi permit dipisah berdasarkan `Sumber_Permit`. Tanpa ini angka permit akan didominasi permit coklat: di development sekarang ada 24 transaksi permit karyawan berbanding 83 tamu, jadi angka lama akan terlihat meledak padahal tidak ada yang berubah. Setiap tempat yang menghitung permit ditinjau satu per satu, bukan disaring di satu tempat lalu diasumsikan beres.

## Risiko Integrasi — Perlu Konfirmasi Pemilik Aplikasi Gate

Dua hal di luar jangkauan repo ini yang **wajib dikonfirmasi sebelum rilis**, bukan diasumsikan:

1. **Permit tanpa plat nomor.** `No_Plat_Kendaraan` memang sudah nullable di skema, tapi apakah logika pencocokan di aplikasi palang sanggup memproses baris permit berplat kosong — tidak bisa dijawab dari sini.
2. **Awal jendela tidak dijaga sapuan mana pun.** Permit yang jendelanya baru mulai besok tetap berstatus `AKTIF` sejak disetujui. Aplikasi palang **wajib** membandingkan `Waktu_Mulai_Berlaku` (atau minimal `Tanggal_Mulai_Berlaku`) sebelum meloloskan, bukan hanya membaca `Status_Efektif`. Sengaja tidak dibuat status "belum berlaku" karena enum itu kontrak.

Keterbatasan yang diterima sadar: **permit mati paling lambat 5 menit setelah tamu tercatat keluar**, bukan seketika. Palang sendiri yang mencatat check-out itu, jadi jeda ini umumnya tidak berbahaya, tapi ia nyata dan tidak disembunyikan.

## Rencana Pengujian

Yang diuji bukan "kodenya jalan", melainkan hal yang kalau salah membuat tamu lolos gerbang atau merusak permit karyawan. Setiap penjaga harus pernah dilihat **merah** sebelum dibuat hijau.

| # | Uji | Membuktikan |
|---|-----|-------------|
| 1 | Satu tes per pintu penerbitan (5 pintu di tabel di atas) | aturan tidak hanya terpasang di sebagian jalur |
| 2 | Tamu pejalan kaki (plat kosong) tetap menghasilkan baris permit coklat | keputusan #3 benar-benar berlaku |
| 3 | Permit mati saat `Waktu_Berakhir` lewat | cabang jam |
| 4 | Permit mati saat check-out ditulis **aplikasi palang** ke `N_VISITOR_Tamu_Efektif` | cabang kehadiran memakai definisi resmi, bukan `Status_Kunjungan` |
| 5 | Permit mati saat check-out ditulis **tombol manual HCIS** | pintu kedua yang setara |
| 6 | Kunjungan lintas tengah malam tidak kedaluwarsa kepagian | keputusan #2 |
| 7 | Permit karyawan tidak berubah: durasi bulanan tetap, `resolveDuration()` tetap menolak satuan non-`MONTH` | regresi alur yang tidak diminta berubah |
| 8 | Setiap baris permit coklat punya `Tanggal_*` terisi turunan dari `Waktu_*` | invarian kontrak aplikasi gate |
| 9 | `update()` mengubah jendela → proyeksi tamu dan permit ikut tersinkron | lubang lama yang ditambal |

Penjaga baru menyentuh service bersama, jadi suite penuh dijalankan, bukan hanya berkas yang berubah.
