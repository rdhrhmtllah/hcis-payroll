# Kolom jejak penjepitan pada detail Update Performance

Berlaku untuk **dua** tabel yang berbagi bentuk yang sama:

- `KPI_Update_Performance_Detail` — detail final
- `KPI_Update_Performance_Detail_Sementara` — detail draf

Dokumen ini hanya membahas kolom yang ditambahkan `DDL-2026-045`. Tabelnya
sendiri legacy dan tidak didokumentasikan seluruhnya di sini.

## Kosakata yang wajib dibaca lebih dulu

Nama kolom pada tabel ini **tertukar dari artinya**, dan itu warisan yang tidak
diperbaiki karena setiap pembacanya sudah bergantung padanya:

| Kolom | Isi sebenarnya | Rumus |
|---|---|---|
| `Score` | **achievement** — persentase pencapaian parameter | rasio × 100 |
| `Achievement` | **skor berbobot** — sumbangan parameter ke nilai akhir | bobot × rasio |

Total di header dihitung dari kolom `Achievement`, bukan dari `Score`:
`Total_Score = Σ Achievement ÷ Σ Bobot × 100`.

## Kolom

| Kolom | Tipe | Null | Default | Isi |
|---|---|---|---|---|
| `Score_Raw` | `float` | ya | — | achievement **sebelum** dijepit |
| `Achievement_Raw` | `float` | ya | — | skor berbobot **sebelum** dijepit |
| `Flag_Capped` | `char(1)` | tidak | `'T'` | `Y` bila parameter ini dijepit |

Penamaannya mencerminkan `Total_Score_Raw` dan `Total_Score_Achievement_Raw` di
`KPI_Update_Performance`, termasuk mewarisi ketertukaran di atas.

## Kekosongan adalah data

`Score_Raw` dan `Achievement_Raw` **sengaja dibiarkan NULL** pada baris yang
ditulis sebelum penjepitan per parameter berlaku. Kekosongan itu adalah satu-
satunya pembeda antara dua aturan yang pernah berlaku:

| `Score_Raw` | Aturan yang berlaku saat baris ditulis |
|---|---|
| NULL | Penjepitan di **total** — tiap parameter dicatat apa adanya, hanya nilai akhir yang dijepit ke 50–125 |
| terisi | Penjepitan di **parameter** — tiap achievement dijepit ke 50–125 lebih dulu, total mengikuti |

Karena itu baris lama tidak boleh di-backfill dengan nol. Selain menghapus
pembeda, itu menuliskan angka yang tidak pernah dihitung.

Konsekuensinya kolom audit di header juga bermakna ganda: `Flag_Capped='Y'` pada
record lama berarti "totalnya pernah dipangkas", pada record baru berarti "ada
parameternya yang dipangkas". Bacalah bersama `Score_Raw` detailnya.

## Mulai berlaku

Penjepitan per parameter menyala pada **28 Agustus 2026** dengan batas 50%–125%.
Riwayat sebelum tanggal itu tidak dihitung ulang — keputusan sadar, bukan
kelalaian. Laporan yang membentang melewati tanggal ini memuat dua aturan
sekaligus.

## Cara mematikan

Batas dibaca dari `config('services.kpi.param_max_achievement_score')` dan
`...param_min_achievement_score`.

Untuk mematikan satu sisi tanpa rilis ulang, tulis **`null`** secara harfiah:

```dotenv
KPI_PARAM_MAX_ACHIEVEMENT_SCORE=null
```

**Mengosongkannya TIDAK mematikan apa pun.** `KPI_PARAM_MAX_ACHIEVEMENT_SCORE=`
membuat `env()` memulangkan string kosong — bukan null, bukan nilai baku (lihat
`Illuminate\Support\Env::getOption`, hanya literal `null`/`(null)` yang
dipetakan ke null). Sebelum penjaga di `config/services.php` dan
`KpiScoringService::batasConfig()` dipasang, string itu dicor menjadi `0.0` dan
plafon nol menjepit achievement **setiap** parameter menjadi nol, tanpa error
dan tanpa catatan. Sekarang nilai kosong dipulangkan ke baku 125/50, dan isi
yang bukan angka menghentikan boot dengan pesan yang menyebut kuncinya.

Batas TOTAL (`KPI_MAX_ACHIEVEMENT_SCORE` / `KPI_MIN_ACHIEVEMENT_SCORE`) tidak
bisa dimatikan sama sekali: ia jaring pengaman terakhir, dan `null` sekalipun
dipulangkan ke baku.

Kolom jejak tetap sah sebagai riwayat apa pun setelan yang berlaku.

## Batasan yang harus dijaga

Plafon per parameter tidak boleh diturunkan di bawah
`KPI_Master_Grading.Nilai_Maksimum` (saat ini **100**). Keputusan PICA
membandingkan achievement **terjepit** terhadap ambang itu
(`UpdatePerformanceController`, tiga jalur simpan draf), sehingga plafon di
bawah ambang membuat tidak ada satu pun parameter yang bisa lepas dari PICA —
selamanya, untuk semua orang. Selama 125 > 100 jebakan ini laten; ia hidup
begitu salah satu dari kedua angka digeser melewati yang lain.

## Pengisi kolom ini

| Berkas | Jalur |
|---|---|
| `app/Services/KPI/KpiScoringService.php` | `scoreParameter()`, `scoreFromRatio()` — sumber angka terjepit dan mentah |
| `app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php` | draf personal, tim, by-pass; penyalinan draf ke detail final |
| `app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php` | `updateSingleKpiDetail()` saat approver mengubah nilai aktual |
| `app/Services/KPI/KpiEnforcementService.php` | detail berasio nol untuk karyawan yang tidak mengisi |

## Perubahan terkait

- DDL: `database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql`
- Dokumentasi perubahan: `docs/changes/DDL-2026-045-kpi-cap-per-parameter/README.md`
- Rancangan: `docs/superpowers/specs/2026-08-28-kpi-cap-per-parameter-design.md`
