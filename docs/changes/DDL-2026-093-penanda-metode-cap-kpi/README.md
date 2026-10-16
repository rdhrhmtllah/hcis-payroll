# DDL-2026-093 — Penanda Metode Cap KPI

| | |
| --- | --- |
| Change ID | `DDL-2026-093-penanda-metode-cap-kpi` |
| Status | `REVIEW_REQUIRED` |
| Forward | `database/ddl/DDL-2026-093-penanda-metode-cap-kpi/forward.sql` |
| Drop | `database/ddl/DDL-2026-093-penanda-metode-cap-kpi/drop.sql` |
| DML (opsional) | `database/dml/DDL-2026-093-penanda-metode-cap-kpi/data-change.sql` |
| Checksum forward | `1147a8e19ed96d98770869b3c4435d7f40e44d974717f8b79dc8af27a551ed76` |
| Terkait | `DDL-2026-045-kpi-cap-per-parameter`, `KpiCapBackfillService` |

## Masalah

Sejak **28 Agustus 2026** penjepitan skor KPI pindah dari total akhir ke
achievement **tiap parameter** (50–125), dan jejaknya turun ke baris detail:
`Score_Raw`, `Achievement_Raw`, `Flag_Capped` (DDL-2026-045).

Akibatnya `KPI_Update_Performance.Flag_Capped = 'Y'` **bermakna ganda**:

| Record | Arti `Flag_Capped='Y'` |
| --- | --- |
| Lama | totalnya yang dipangkas |
| Baru | ada parameternya yang dipangkas |

Sampai sekarang keduanya hanya bisa dibedakan **tidak langsung**, dengan
memeriksa apakah baris detailnya punya `Score_Raw`. Itu menyulitkan tepat pada
saat yang paling penting: ketika backfill cap header dijalankan, ia harus tahu
record mana yang sudah memakai metode baru dan tidak boleh disentuh.

## Isi perubahan

**Skema** — satu kolom pada `KPI_Update_Performance`:

| Nilai | Arti |
| --- | --- |
| `PARAMETER` | difinalisasi dengan cap per parameter; detailnya membawa `Score_Raw` |
| `TOTAL` | total header dijepit, baik oleh mekanisme lama maupun `KpiCapBackfillService` |
| `NULL` | belum pernah disentuh cap mana pun — keadaan wajar, bukan "belum diisi" |

Ditambah `CHECK` agar isinya tetap dua nilai yang dikenal, dan satu indeks yang
melayani penyaringan backfill per periode.

`KPI_Update_Performance_Sementara` **tidak** ikut diubah: tabel itu memang tidak
punya kolom cap sama sekali (diperiksa — nol kolom bernama `*cap*`/`*raw*`).

**Kode** — penanda ditulis di **empat** titik finalisasi header:

- `UpdatePerformanceController` baris ~5340, ~5385, ~5425 (tiga jalur `insertGetId`)
- `PerformanceApprovalController` baris ~2360 (jalur `update`)

**Backfill** — `KpiCapBackfillService` sekarang:

1. **menolak** record ber-`Metode_Cap='PARAMETER'` sebelum saringan rentang skor;
2. **menandai** hasil jepitannya sendiri sebagai `TOTAL`.

Sebelumnya pengamanan (1) hanya bersandar pada kenyataan bahwa rata-rata
tertimbang dari nilai-nilai di dalam [50,125] pasti ikut di dalam rentang,
sehingga tak pernah lolos saringan. Itu benar hari ini, tetapi diam-diam
bergantung pada aritmetika yang bisa berubah begitu bobot atau rumusnya berubah.
Sekarang penolakannya dinyatakan langsung.

## Yang perlu diketahui sebelum menjalankan

**Urutannya mengikat.** `forward.sql` harus jalan **sebelum** kode ini aktif di
lingkungan mana pun. Tanpa kolom `Metode_Cap`, ketiga jalur `insertGetId` akan
gagal dengan `Invalid column name 'Metode_Cap'` — artinya finalisasi KPI mati,
bukan sekadar penandanya yang kosong. Ini berlaku juga untuk development.

**Keadaan data saat ini (diperiksa read-only, 10 September 2026).** Belum ada
satu pun record yang memakai metode baru, di kedua server:

| Server | Header aktif | Detail | Detail ber-`Score_Raw` |
| --- | ---: | ---: | ---: |
| Development remote (`Web_HRIS` team311) | 67 | 313 | **0** |
| Development lokal | 67 | — | **0** |

Kolomnya sudah ada (DDL-2026-045 `DEV_EXECUTED`) tetapi belum pernah terisi,
karena sejak 28 Agustus belum ada finalisasi KPI yang lewat jalur aplikasi —
enam record terbaru (`AKPI-*`, 6–7 September) masing-masing hanya punya satu
detail berskor bulat, ciri data semaian lewat INSERT mentah.

Konsekuensinya: **DML opsional hari ini akan menandai nol baris sebagai
`PARAMETER`**. Itu benar, bukan kegagalan. Yang perlu dijaga adalah jangan
sampai kesimpulannya dibalik menjadi "berarti jalur barunya rusak" — jalur
tulisnya ada dan lengkap di tiga tempat `UpdatePerformanceController`.

**Nol kandidat backfill.** Di kedua server tidak ada header aktif dengan
`Total_Score` di luar 50–125, jadi `KpiCapBackfillService` saat ini tidak akan
menyentuh apa pun. Angka 88 dari 305 yang tercatat pada catatan 28 Agustus
adalah baris **detail** di luar rentang, bukan header.

## Pekerjaan agent vs pekerjaan user

**Sudah dikerjakan agent**

- `forward.sql`, `drop.sql`, `data-change.sql`, dokumentasi ini, entri ledger.
- Perubahan kode di empat titik finalisasi + `KpiCapBackfillService`.
- Penjaga regresi `tests/Feature/KPI/PenandaMetodeCapTest.php`, **sudah dilihat
  MERAH** lewat tiga sabotase: menghapus satu penanda di masing-masing controller
  terdeteksi, dan membuang saringan `Metode_Cap` di backfill terdeteksi.
- Seluruh pemeriksaan data di atas dilakukan **read-only**.

**Belum dikerjakan — milik user**

- Meninjau dan menyetujui, khususnya: apakah DML opsional dijalankan atau penanda
  cukup berlaku maju.
- Menjalankan `forward.sql` di development **sebelum** menguji jalur KPI.
- Menjalankan suite (agent belum menjalankannya, menunggu ACC).
- Merekam di SQL Tracker, menjalankan ke production, lalu menaikkan status
  mengikuti alur wajib.
