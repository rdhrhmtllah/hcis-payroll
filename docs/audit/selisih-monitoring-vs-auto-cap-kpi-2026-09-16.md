# Selisih Monitoring Performance Tracking vs Auto Cap (KPI) — 16 September 2026

Basis pengukuran: `34.143.236.80` / `emi_db_web_hr` (**produksi**), seluruhnya
**read-only**. Setiap skrip diagnostik memasang `DB::listen` yang menghentikan
proses begitu ada statement non-SELECT; penjaga itu tidak pernah menyala.

## Keluhan

Pada halaman `/kpi-enforcement`, periode **Juli 2026**:

- Tab **Monitoring › Performance Tracking**, disaring ke *Tidak Update + Belum
  Update + Draft* → **90** orang (pelapor melihat 91; selisih satu draft, tidak
  mengubah sebabnya).
- Tab **Enforcement (Auto Cap)** → **121** orang.

## Temuan

Seluruh selisih 31 orang berasal dari karyawan non-aktif — sisa yang tidak
terjelaskan **nol**. Tetapi itu bukan berarti tidak ada cacat; di dalamnya ada
dua kerusakan yang berlawanan arah dari satu akar.

Akarnya: pertanyaan "siapa yang wajib mengisi KPI bulan X" dijawab di tiga
tempat dengan tiga hasil berbeda.

| Permukaan | Aturan lama | Akibat |
| --- | --- | --- |
| Universe monitoring (`scheduledEmployeesInScope`) | `k.Aktif = 'Y'` — status **hari ini** | Laporan sebuah bulan menyusut sendiri setiap ada orang resign, berbulan-bulan setelah bulan itu ditutup |
| Kandidat Auto Cap (`unfilledBaseQuery`) | tidak menyaring kepegawaian sama sekali | 16 orang yang sudah resign sebelum Juli bisa di-*enforce*; query yang sama dipakai `previewEnforcement` dan penjaga `validId` di `enforceCap` |
| Sasaran notifikasi (`kpi:notif-update`) | tidak menyaring kepegawaian sama sekali | 35 nomor HP mantan karyawan jadi sasaran pengingat WhatsApp Juli 2026, dan 35 lagi pada Agustus 2026 |

Rincian 35 karyawan non-aktif yang jadi kandidat Auto Cap Juli 2026 — semuanya
punya `Tanggal_Resign`, tidak satu pun punya baris `KPI_Update_Performance`
Juli (jadi memang belum mengisi, bukan artefak baris dibatalkan):

| Kelompok | Jumlah | Penilaian |
| --- | --- | --- |
| Resign **sebelum** Juli (Apr–Jun 2026) | 16 | Tidak boleh di-enforce sama sekali |
| Resign **di dalam** Juli | 9 | 8 orang tidak menjalani bulan penuh; 1 orang resign tepat 31 Juli |
| Resign **sesudah** Juli (Agustus 2026) | 10 | Bekerja penuh sepanjang Juli; seharusnya ada di laporan Juli |

Sebagai penguat: 24 dari 35 orang itu tercatat mengisi KPI Mei atau Juni 2026 —
mereka memang subjek penilaian yang sah, bukan data sampah.

Dua kecurigaan lain diperiksa dan **bersih**:

- Jalur `up.Id_Penjadwalan IN (scope) OR up.Periode LIKE 'Y-m%'` pada monitoring
  tidak pernah menandai seseorang "sudah mengisi" dengan baris periode lain
  (0 dari 354).
- Tidak ada pemilik realisasi Juli yang terlempar ke luar universe monitoring (0).

## Keputusan

Pemilik proses, 16 September 2026: **seseorang ditampilkan untuk sebuah periode
hanya bila ia menjalani periode itu penuh.** Yang resign di tengah bulan —
misalnya 15 Juli — tidak perlu lagi muncul di Juli.

Batasnya karena itu adalah **hari terakhir periode**: masih pegawai bila
`Aktif = 'Y'` atau `Tanggal_Resign >= hari terakhir periode`. Yang resign tepat
pada hari terakhir tetap masuk — bulannya ia jalani sampai habis. Non-aktif
tanpa `Tanggal_Resign` sengaja diperlakukan sebagai bukan pegawai: tanpa tanggal
ia tidak bisa ditempatkan di sumbu waktu.

Untuk scope gabungan (submission per tahun, mode YTD atas daftar Jan..N) batas
itu diambil dari periode **terawal** dalam scope. Menuntut orangnya bertahan
sampai akhir rentang akan menghapus justru orang yang angkanya sudah masuk ke
dalam angka gabungan — cacat yang sama yang dijaga `PerformanceTrackingYtdTest`.

## Perbaikan

Aturannya dipusatkan di `app/Services/KPI/KpiKepegawaianPeriode.php`.

| Berkas | Perubahan |
| --- | --- |
| `app/Services/KPI/KpiKepegawaianPeriode.php` | **Baru.** `terapkan()` + `batasDariScope()` (bulan / tahun / daftar YTD) |
| `KpiEnforcementController::scheduledEmployeesInScope()` | Menerima `Carbon $batasKepegawaian`; `k.Aktif='Y'` → aturan pada periode. Menutup `getSubmissionStatus`, `getPerformanceTracking`, `getMonitorFilterOptions` |
| `KpiEnforcementController::unfilledBaseQuery()` | Menyaring kepegawaian pada periode. Menutup `getUnfilledEmployees`, `getFilterOptions`, `previewEnforcement`, penjaga `validId` di `enforceCap` |
| `KpiEnforcementController::exportScheduleMeta()` | Menitipkan `batas_kepegawaian` ke meta job export |
| `KpiEnforcementController::scheduledUniverseQuery()` | Menerima `Carbon $batasKepegawaian` |
| `KpiEnforcementController::scheduledEmployeesQuery()` | **Dihapus** — privat, nol pemanggil, dan memuat aturan lama yang siap disalin orang berikutnya |
| `KpiExportService` | `universeQuery()`, dataset parameter, dataset performance memakai aturan yang sama; `batasKepegawaianUniverse()` punya cadangan untuk payload job lama |
| `KpiNotifUpdatePerformance` | Query sasaran diekstrak jadi `sasaranPengingat()` (agar bisa diuji) dan diberi `k.Aktif='Y'` — **sengaja** memakai status sekarang, bukan status pada periode |

## Hasil terukur sesudah perbaikan (produksi, read-only, Juli 2026)

| | Sebelum | Sesudah |
| --- | --- | --- |
| Auto Cap | 121 | **97** (16 resign sebelum Juli + 8 resign di tengah Juli dibuang) |
| Monitoring (Tidak/Belum Update + Draft) | 90 | **101** (11 orang yang menjalani Juli penuh kembali masuk) |
| Hanya di Auto Cap | 35 | **0** |
| Hanya di Monitoring | 4 | **4** |
| Resign sebelum/di tengah periode yang masih lolos | 25 | **0** di kedua sisi |

Dari 35 karyawan non-aktif, kini tersisa **11** yang masih jadi kandidat: sepuluh
yang resign Agustus 2026, dan satu (`NAWRAH`) yang resign **31 Juli 2026** — hari
terakhir bulan itu, jadi ia menjalani Juli sampai habis. Delapan yang resign
2–28 Juli sudah tidak muncul.

Sisa 4 baris adalah perbedaan definisi yang **disengaja**, bukan cacat: keempatnya
punya draft hidup tetapi pengajuan yang masih hidup tidak memuat target bulan
Juli, sehingga `enforceForEmployee` memang akan menolaknya. Mereka muncul di
monitoring sebagai `draft` dan tidak muncul sebagai kandidat Auto Cap.

## Uji

- `tests/Unit/KPI/Monitoring/KepegawaianPeriodeBatasScopeTest.php` — penurunan
  batas periode (bulan/tahun/daftar YTD/kosong/kabisat) + bentuk penyaringnya.
  Tidak mengeksekusi kueri.
- `tests/Feature/KPI/Monitoring/KepegawaianPadaPeriodeTest.php` — lima karyawan
  yang hanya dibedakan tanggal resign (termasuk pasangan 15 Juli vs 31 Juli yang
  menegaskan letak batasnya), diuji pada tiga permukaan sekaligus (monitoring,
  Auto Cap, sasaran pengingat).

Keduanya **belum dijalankan**: `.env` sedang menunjuk produksi, dan uji Feature
menanam data fixture. Meski `DatabaseTransactions` me-rollback, itu tetap operasi
tulis. Jalankan hanya setelah koneksi diarahkan ke basis data development.

## Skrip diagnostik (read-only, bisa diulang)

- `scripts/audit/diag-kpi-monitoring-vs-autocap.php` — komposisi selisih
- `scripts/audit/diag-kpi-selisih-lanjutan.php` — telusur 4 draft + sebaran `Aktif`
- `scripts/audit/diag-kpi-verifikasi-gap.php` — pembuktian resign & dua kecurigaan lain
- `scripts/audit/diag-kpi-sesudah-perbaikan.php` — angka sesudah, lewat jalur kode sebenarnya

---

# Susulan: label "Belum Update" vs "Tidak Update" — 16 September 2026

## Keluhan

Penjadwalan Juli sudah dinonaktifkan, tetapi layar masih menampilkan
"Belum Update" untuk Juli. Dugaan awal: cache.

## Temuan

Bukan cache — tidak ada `Cache::` di controller ini, dan halaman Vue hanya
menyimpan batch-id di `localStorage`. Tiga sebab sebenarnya:

1. **Menonaktifkan penjadwalan tidak berefek apa pun.** Monitoring sengaja
   mengabaikan `Flag_Aktif` supaya riwayat sebuah periode tidak lenyap
   (`KpiEnforcementController::fetchYears`). Keputusan itu benar untuk
   *apa yang terlihat*, tetapi ikut terbawa ke *label* — padahal label berbicara
   soal apakah orangnya masih punya waktu.
2. **Label dihitung se-scope, bukan per orang.** `isScopeExpired()` menuntut
   seluruh penjadwalan periode itu sudah lewat. Dari 90 baris "belum/tidak
   update" Juli 2026, hanya **3 orang** yang benar-benar peserta jadwal `#32`
   (perorangan, berakhir 15 Sep 21:00); **87 sisanya** hanya dibebani `#26` yang
   tutup 31 Agustus — tiga orang menyeret label delapan puluh tujuh.
3. **Pencabutan peserta juga tidak terbaca.** Ke-37 peserta `#32` sudah
   `Flag_Aktif='T'`, dan tetap terhitung.

## Keputusan & perbaikan

Pemilik proses, 16 September 2026: label dihitung **per orang**, dan penjadwalan
maupun peserta yang dinonaktifkan dianggap **sudah tutup**.

Aturan baru (`app/Services/KPI/KpiJadwalBerjalan.php`): berlabel "Belum" hanya
bila ada penjadwalan yang **mencakup orang itu** (global, atau perorangan dengan
`Flag_Aktif='Y'`), **`Flag_Aktif='Y'`**, dan **`Jadwal_Akhir` belum lewat**.
`Jadwal_Akhir` kosong pada jadwal aktif dianggap masih berjalan — label
"Tidak Update" mengklaim tenggat sudah lewat, dan tanpa tanggal akhir klaim itu
tak bisa dibuktikan; menutupnya dilakukan dengan menonaktifkan jadwalnya.

Universe **tidak** diubah: penjadwalan nonaktif tetap menampilkan orangnya.

| Berkas | Perubahan |
| --- | --- |
| `app/Services/KPI/KpiJadwalBerjalan.php` | **Baru.** Penentu beban per orang + penanda untuk payload job export |
| `KpiEnforcementController::schedulesInScope()` | Ikut membawa `Flag_Aktif` |
| `KpiEnforcementController::getSubmissionStatus()` / `getPerformanceTracking()` | Label per orang, menggantikan boolean `isScopeExpired()` |
| `KpiEnforcementController::exportScheduleMeta()` | Menitipkan `global_berjalan` + `perorangan_berjalan_ids`; `expired` disimpan sebagai cadangan job lama |
| `KpiExportService::labelBelumAtauTidak()` | Label berkas dihitung per orang, sama seperti layar |

## Hasil terukur (produksi, read-only, 16 Sep 2026 16:xx)

| Periode | Belum Update | Tidak Update | Catatan |
| --- | --- | --- | --- |
| 2026-05 | 0 | 44 | 7 jadwal, semuanya nonaktif |
| 2026-06 | 0 | 140 | semuanya nonaktif |
| 2026-07 | 0 | 90 | `#26` & `#32` nonaktif dan lewat |
| 2026-08 | 440 | 0 | `#35` masih `Flag_Aktif='Y'` s/d 30 Sep — benar |

## Uji

`tests/Feature/KPI/Monitoring/LabelJadwalBerjalanTest.php` — tiga karyawan yang
hanya dibedakan oleh jadwal/keanggotaan yang membebani mereka, plus penjaga
bahwa menonaktifkan jadwal benar-benar menutup tenggat dan bahwa penanda export
utuh bolak-balik lewat payload job. Belum dijalankan (alasan sama seperti di
atas: `.env` menunjuk produksi).
