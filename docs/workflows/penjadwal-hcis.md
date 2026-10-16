# Penjadwal (Scheduler) HCIS

Panduan operasional: apa saja yang harus dijalankan terjadwal, bagaimana penjadwalnya dipicu,
dan apa yang perlu diisi di tabel `N_HRIS_Scheduler_Configs`. Keadaan per **27 Sep 2026**
(dev `Web_HRIS`) — verifikasi ulang sebelum mengeksekusi apa pun.

## 1. Cara kerjanya

```
pemicu tiap menit ──► php artisan schedule:run ──► Console\Kernel::schedule()
                                                     └─ SELECT * FROM N_HRIS_Scheduler_Configs WHERE Flag_Aktif='Y'
                                                        └─ tiap baris: ->command(Command)->cron(Cron_Expression)
                                                           ->timezone(Timezone) ->withoutOverlapping(15) ->onOneServer()
```

- **Jadwal tidak ditulis di kode**, melainkan di tabel. Menambah/mematikan jadwal = mengubah baris
  tabel (lewat DML bernomor, lihat `workflows/ddl-sql-tracker`).
- Output tiap perintah ditambahkan ke `storage/logs/scheduler.log`.
- **Pemicu wajib ada.** Tanpa sesuatu yang memanggil `schedule:run` tiap menit, SELURUH tabel
  diam — tidak ada galat, tidak ada peringatan.

### Pemicu per lingkungan

| Lingkungan | Pemicu | Status 27 Sep 2026 |
|---|---|---|
| Production (Cloud Run) | HTTP `POST /scheduler-T4sk-RuNn3r-sEcr3t` dengan `Authorization: Bearer <SCHEDULER_BEARER_TOKEN>` (`routes/web.php`), dipanggil **tiap menit** oleh Cloud Scheduler | **Perlu dipastikan** oleh pemegang infrastruktur: job Cloud Scheduler ada, tiap menit, token cocok. Log: channel `worker_schedular`. |
| Dev VPS (`/home/hermes-admin/projects/KPI-Unreal`) | **Tidak ada** (tanpa crontab, systemd timer, maupun `schedule:work`) | `visitor:expire-efektif` terakhir jalan 22 Sep (manual). Lihat §5. |

Cek cepat production: di `N_VISITOR_Permit_Efektif`, `MAX(Updated_At)` untuk
`Updated_Oleh_Nama = 'Sistem (Auto-Expire)'` harus < 10 menit yang lalu.

## 2. Entri yang terdaftar (21 baris, 20 aktif)

Kolom **Efek** penting untuk memutuskan apakah aman dinyalakan di dev (DB dev dipakai bersama).

| Id | Command | Cron | Fungsi | Efek |
|---|---|---|---|---|
| 1 | `kpi:notif-update` | `30 23 * * *` | Notifikasi update KPI | kirim notifikasi |
| 2 | `izin:expiredIzin` | `59 23 * * *` | Tandai izin kedaluwarsa | ubah data izin, WA |
| 3 | `cuti:tambahCuti` | `50 23 * * *` | Penambahan saldo cuti | **ubah saldo cuti** |
| 4 | `cuti:hakCutiTambahan` | `55 23 * * *` | Hak cuti tambahan | **ubah saldo cuti** |
| 5 | `cuti:checkMaturedBalances` | `45 23 * * *` | Saldo cuti matang | ubah saldo cuti |
| 6 | `cuti:checkKartab` | `0 * * * *` | Cek karyawan tetap | ubah data cuti |
| 7 | `cuti:tutupBukuCuti` | `0 1 1 1 *` | Tutup buku cuti tahunan | **ubah saldo cuti** |
| 8 | `hris:absen-process-queue …` | `* * * * *` | Proses antrean hitung ulang absensi | ubah data absensi |
| 9 | `hris:absen-plan-rolling …` | `0 12,22 * * *` | Refresh plan absensi 7 hari | ubah data absensi |
| 10 | `hris:absen-plan-custom --tgl_awal=2026-05-01 --tgl_akhir=2026-05-31 …` | `30 23 * * *` | "Cutoff harian" — **tanggalnya dipaku Mei 2026** | ⚠️ lihat §3 (DML-098) |
| 11 | `hris:absen-plan-cutoff …` | `* * * * *` | Cutoff yang menghitung periodenya sendiri | **nonaktif** (lihat §3) |
| 12 | `hris:absen-maintenance …` | `*/15 * * * *` | Pemulihan antrean/snapshot absensi | ubah data absensi |
| 13 | `sop:reminder-scan` | `30 0 * * *` | Pengingat tenggat SOP | kirim notifikasi |
| 14 | `sop:recert-scan` | `15 1 * * *` | Resertifikasi SOP | ⚠️ perintah tidak ada (§3) |
| 15 | `talent-pool:kedaluwarsa` | `0 1 * * *` | Kedaluwarsa Talent Pool | ⚠️ perintah tidak ada (§3) |
| 16 | `hris:api-nonce-prune` | `15 * * * *` | Bersihkan nonce API kedaluwarsa | hapus baris teknis |
| 17 | `visitor:expire-efektif` | `*/5 * * * *` | Penyapu VISITOR (§4) | ubah proyeksi gerbang |
| 19 | `sop:rule-scan` | `10 0 * * *` | Pemindai aturan SOP | ubah data SOP |
| 20 | `pa:ingatkan-penilai` | `45 1 * * *` | Pengingat penilai PA | ⚠️ perintah tidak ada di `master` (§3) |
| 24 | `hcis:kalender` | `15 2 * * *` | Sinkron kalender operasional HO | ⚠️ perintah tidak ada di `master` (§3) |
| 25 | `pa:sapu-tertahan` | `20 7-19 * * *` | Hitung ulang PA tertahan | ⚠️ perintah tidak ada di `master` (§3) |

## 3. Yang perlu dibereskan di tabel

Periksa dengan perintah bawaan (hanya baca), di **setiap** lingkungan:

```bash
php artisan hris:penjadwal-periksa
```

Hasil dev 27 Sep: **20 aktif, 5 yatim** (menunjuk perintah yang tidak ada → menyala sesuai cron
lalu gagal "Command is not defined", jejaknya hanya di `scheduler.log`):
`hcis:kalender`, `pa:ingatkan-penilai`, `pa:sapu-tertahan`, `sop:recert-scan`, `talent-pool:kedaluwarsa`.
Tiga yang pertama kemungkinan milik branch/fitur yang belum masuk `master`; konfirmasi ke pemilik
modul sebelum menonaktifkan.

DML yang sudah disiapkan tetapi **belum dieksekusi** (status `REVIEW_REQUIRED`):

| DML | Isi | Keputusan yang dibutuhkan |
|---|---|---|
| `DML-2026-076-jadwalkan-potret-kesehatan-db` | Daftarkan `db:health-snapshot` tiap menit (hanya menulis saat ada blocking) | setujui & jalankan |
| `DML-2026-098-jadwal-cutoff-tanggal-dipaku` | Matikan Id 10 (tanggal dipaku Mei 2026), nyalakan `hris:absen-plan-cutoff` yang menghitung periodenya | pemilik modul absensi |
| `DML-2026-099-penjadwal-perintah-hilang` | Nonaktifkan `sop:recert-scan` & `talent-pool:kedaluwarsa` | pastikan perintahnya memang tidak akan kembali |

## 4. VISITOR: `visitor:expire-efektif` (Id 17, tiap 5 menit)

Satu-satunya jadwal VISITOR, dan **wajib jalan**: tanpanya palang gerbang tetap membuka izin
yang sudah habis. Tiap jalan ia:

1. Permit karyawan (masa berlaku per hari) yang kedaluwarsa → proyeksi gerbang `EXPIRED`.
2. Permit berjam (COKLAT tamu, mitra API) yang jamnya lewat → `EXPIRED`.
3. Permit COKLAT satu hari yang tamunya sudah tercatat keluar → `EXPIRED`.
4. Kunjungan tamu yang estimasi keluarnya lewat → `EXPIRED`.
5. Booking inspeksi `TERJADWAL` milik permit yang tak berjalan lagi → `DIBATALKAN` (kursi slot lepas).
6. *(PR #466)* Permit COKLAT menunggu yang kunjungannya berlalu → ditolak otomatis.

Setiap baris yang berubah diberi `Flag_Sync='T'` agar aplikasi palang menariknya. Tidak menghapus
data, tidak mengirim notifikasi, idempoten. Entrinya sudah ada (`DML-2026-078`); **yang kurang
hanya pemicunya**.

## 5. Kandidat yang belum terdaftar

Perintah yang ada di kode dan dirancang terjadwal (menurut docblock-nya) tetapi belum ada di tabel.
Semuanya pembersih/pemanas teknis — tidak mengubah data bisnis.

| Command | Saran dari kode | Keterangan |
|---|---|---|
| `export:cleanup` | tiap 15 menit | hapus berkas export yang sudah diunduh/kedaluwarsa |
| `hris:cache-prune` | tiap jam | hapus baris cache kedaluwarsa (cache store = tabel DB) |
| `hris:diagnostik-prune` | harian | pangkas `N_HRIS_Request_Diagnostics` & `N_HRIS_DB_Health_Snapshot` |
| `db:health-snapshot` | tiap menit | lihat DML-076 |
| `leader-dashboard:warm-cache` | `*/2 6-9 * * *` (jam sibuk pagi) | pemanasan cache dashboard leader; opsional |

Perintah lain yang namanya terdengar periodik (`activity:expire-idle`, `activity:prune`,
`activity:rollup`, `leader-dashboard:prewarm`, `izin:wa`, `absen:flag`) **tidak** menyebut jadwal
di kodenya — tanyakan pemilik modul sebelum mendaftarkan.

Templat pendaftaran (tulis sebagai DML bernomor di `database/dml/<id>/data-change.sql`, jangan
dieksekusi langsung di production):

```sql
IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs WHERE Command = 'export:cleanup')
    INSERT INTO dbo.N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES ('export:cleanup', '*/15 * * * *', 'Asia/Jakarta', 'Y', 'Hapus berkas export kedaluwarsa (tiap 15 mnt)', SYSDATETIME());

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs WHERE Command = 'hris:cache-prune')
    INSERT INTO dbo.N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES ('hris:cache-prune', '5 * * * *', 'Asia/Jakarta', 'Y', 'Hapus cache DB kedaluwarsa (tiap jam)', SYSDATETIME());

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs WHERE Command = 'hris:diagnostik-prune')
    INSERT INTO dbo.N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES ('hris:diagnostik-prune', '40 2 * * *', 'Asia/Jakarta', 'Y', 'Pangkas diagnostik & snapshot kesehatan DB (harian)', SYSDATETIME());
```

## 6. Menyalakan pemicu di dev VPS

**Jangan** memasang `schedule:run` penuh di dev tanpa keputusan: tabelnya ikut menjalankan
pengubah saldo cuti, pemroses absensi tiap menit, dan pengirim notifikasi terhadap DB bersama.

Pilihan yang aman — penyapu VISITOR saja (crontab user `hermes-admin`):

```cron
*/5 * * * * cd /home/hermes-admin/projects/KPI-Unreal && php artisan visitor:expire-efektif >> storage/logs/scheduler.log 2>&1
```

Bila suatu saat dev memang harus meniru production penuh:

```cron
* * * * * cd /home/hermes-admin/projects/KPI-Unreal && php artisan schedule:run >> /dev/null 2>&1
```

Worktree lain (`hcis-w-6`, `hcis-w-7`) memakai DB yang sama — cukup **satu** pemicu per DB.

## 7. Checklist

- [ ] Production: pastikan job Cloud Scheduler ke endpoint penjadwal ada, tiap menit, token valid.
- [ ] Production & dev: `php artisan hris:penjadwal-periksa` → tangani entri yatim.
- [ ] Putuskan DML-076, DML-098, DML-099.
- [ ] Putuskan kandidat §5 → tulis DML bernomor.
- [ ] Dev: pasang pemicu (§6).
- [ ] Pantau: `Updated_At` terakhir oleh `Sistem (Auto-Expire)` < 10 menit.
