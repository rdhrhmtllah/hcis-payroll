# DDL-2026-140-pa-esai-per-soal

## Metadata

- **Change ID**: `DDL-2026-140-pa-esai-per-soal`
- **Target Database**: `Web_HRIS`
- **Type**: DDL (dipasangkan dengan amandemen `DDL-2026-119-pa-kalibrasi-penilai` r3)
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `a948cfc8bc3e1ba6f7cca7697f8c6e341d95f437d4c463204bbaa9a198fa7dae`
- **Affected Objects**: `N_PA_Assignment_Score_Esai`, `PK_PA_Assignment_Score_Esai`,
  `FK_PA_Assignment_Score_Esai_Score`, `DF_PA_Assignment_Score_Esai_Created_At`,
  `CK_PA_Assignment_Score_Esai_Urutan`, `UX_PA_Assignment_Score_Esai_Urutan`,
  `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Rancangan**: `docs/superpowers/specs/2026-09-26-pa-esai-per-soal-design.md`
- **Dokumentasi tabel**: `docs/database/tables/kpi/n-pa-assignment-score-esai.md`

## Deskripsi

Callback hasil HC Learn (`POST /api/v1/pa/third-party/penilaian/complete`) kini mengirim
`Detail[].Esai[]`: setiap soal esai dalam satu bagian datang dengan `Id_Master_Soal`,
`Pertanyaan`, dan `Jawaban_Peserta`. Sebelumnya satu bagian hanya membawa satu
`Jawaban_Teks`. Tabel baru menyimpan tiap soal sebagai anak baris skor, sehingga layar
Kalibrasi Penilai menampilkan pertanyaan di atas jawabannya dan esai dikalibrasi per soal
(`N_PA_Kalibrasi_Penilai_Jawaban.Id_Master_Soal`, DDL-2026-119 r3).

## Dasar

- Kontrak baru dibaca langsung dari dokumentasi HC Learn staging (`/docs/api`), 26 Sep 2026.
- Registry: halaman `PerformanceAppraisalSayaPage` → `N_HRIS_Grup_Fitur_Map.Id_Grup_Map = 245`,
  grup `KPI`, aktif. Diverifikasi di `Web_HRIS` development, 26 Sep 2026.

## Keputusan fisik

- **Tabel anak, bukan baris skor per soal.** Baris skor tetap satu per bagian, sehingga
  pemetaan kompetensi, `UX_PA_Assignment_Score_Butir`, dan pemeriksaan `butir_hilang` tidak
  berubah.
- **Tanpa `Flag_Aktif`.** Anak mengikuti baris skornya; pemetaan ulang menyisipkan anak baru
  untuk baris skor baru.
- **`Id_Master_Soal` boleh NULL.** Dokumentasi HC Learn menandainya tidak wajib.
- **Penilaian mesin tidak disimpan.** Skor esai tidak dipakai (keputusan pemilik proyek
  26 Sep 2026); angkanya tetap di `Payload_Json`.

## Artefak

- `database/ddl/DDL-2026-140-pa-esai-per-soal/forward.sql`
- `database/ddl/DDL-2026-140-pa-esai-per-soal/drop.sql` (manual; tidak dijalankan agen)

## Verifikasi development

**Remote (Web_HRIS @ team311, instance `instance-20240809-031237`) — 26 September 2026.**
Sasaran diverifikasi lewat `SELECT DB_NAME()` + `@@SERVERNAME` sebelum batch pertama.

- **Sebelum:** `N_PA_Assignment_Score_Esai` tidak ada; ledger `DDL-2026-140` kosong.
- **`forward.sql`:** 3 batch OK.
- **Sesudah:** tabel 8 kolom, 0 baris; `UX_PA_Assignment_Score_Esai_Urutan`, 1 FK, 1 CHECK;
  ledger 1 baris.
- **Dijalankan ulang:** no-op.

## Revisi

| Revisi | Tanggal | Perubahan | Pemicu |
| --- | --- | --- | --- |
| r1 | 2026-09-26 | Rancangan awal. | Kontrak callback HC Learn menambah `Esai[]`. |

## Agent completed

- Artefak forward/drop, dokumentasi tabel dan change, entri ledger.
- Dijalankan dan diverifikasi di `Web_HRIS` development (termasuk putaran ulang no-op).

## User tasks

- Tinjau dan setujui artefak ini bersama `DDL-2026-119` r3.
- Rekam keduanya di SQL Tracker dengan checksum di atas, lalu jalankan ke production:
  `DDL-2026-119` r3 lebih dulu (bila belum pernah), kemudian `DDL-2026-140`.
- Verifikasi production.
