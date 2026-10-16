# DDL-2026-083 — Peran catatan dan jenis soal pada butir paket soal

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-083-performance-appraisal-question-competency-map |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Cycle |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | DDL-2026-073, DDL-2026-080 |
| Forward DDL | database/ddl/DDL-2026-083-performance-appraisal-question-competency-map/forward.sql |
| Manual reset | database/ddl/DDL-2026-083-performance-appraisal-question-competency-map/drop.sql |
| DML | database/dml/DDL-2026-083-performance-appraisal-question-competency-map/data-change.sql |
| Table documentation | docs/database/tables/kpi/n-pa-cycle-question-package-item.md |
| Forward checksum SHA-256 | 6a0feb6b4207577f7f6b26dc8e313669cb59e22b79eace67750e8982b6cb5256 |
| DML checksum SHA-256 | 8a7ac292357d01a6921e188ab8d975d3e02fed834f9d41d2ff7895c05e99c4cf |

> **Checksum r1 tidak boleh dicatat ke SQL Tracker.** Yang berlaku hanya kedua checksum
> di atas.

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-09-01 | Kebutuhan menerjemahkan `Kode_Soal` HC Learn menjadi kompetensi | Membuat tabel baru `N_PA_Question_Competency_Map`. Revisi ini benar-benar berjalan di development dan tabelnya benar-benar terbentuk. |
| r2 | 2026-09-01 | Audit arsitektur terhadap deck PIC menemukan tabel kembar | Tidak membuat tabel apa pun. Menambahkan `Jenis_Soal` dan `PA_Result_Note_Type_Id` pada `N_PA_Cycle_Question_Package_Item` milik DDL-2026-073, beserta FK dan CHECK peran. |

## Kenapa r1 keliru

`N_PA_Cycle_Question_Package_Item` dari DDL-2026-073 **sudah** memetakan kode soal ke
kompetensi, per paket siklus, dengan unique index tersaring
`UX_PA_Cycle_Question_Item_Aktif (PA_Cycle_Question_Package_Id, Kode_Soal) WHERE Flag_Aktif='Y'`
— sama persis dengan yang dibuat r1. r1 menduplikasinya tanpa memeriksa tabel yang sudah ada.

Kembarnya memutus rantai, bukan sekadar mubazir:

| Yang terjadi | Terukur |
|---|---|
| `petaSoal()` membaca tabel r1 | 40 baris pemetaan, 80 baris skor tertulis |
| `butirDiharapkan()` membaca tabel DDL-073 yang kosong | `kontrak_lengkap = false` |
| Penugasan tidak pernah ditandai `SELESAI` | 8 dari 8 tetap `BELUM_MULAI` |
| Perhitungan melewati seluruh penilai | 1.358 baris jejak kategori, `Jumlah_Rater_Mengisi = 0` |
| Pilar kompetensi hilang | `N_PA_Result_Competency` nol baris |
| Seluruh peserta jatuh `TERTAHAN` | 8 dari 8 |

Tidak ada satu pun pesan galat sepanjang itu.

## Scope r2

Dua kolom pada `N_PA_Cycle_Question_Package_Item`:

- `Jenis_Soal varchar(30) NULL` — jenis dari HC Learn. **Tanpa CHECK**: daftarnya milik
  pihak lain dan bisa tumbuh tanpa memberi tahu kita.
- `PA_Result_Note_Type_Id int NULL` + `FK_PA_Cycle_Question_Item_Note_Type` — jalan
  jawaban kualitatif menjadi catatan hasil.
- `CK_PA_Cycle_Question_Item_Peran` — satu butir tidak boleh menjadi nilai kompetensi
  sekaligus catatan kualitatif.

`forward.sql` r2 juga **menolak berjalan** bila `N_PA_Question_Competency_Map` peninggalan
r1 masih ada, supaya keadaan setengah jalan tidak tersembunyi.

---

# Realignment development — BUKAN bagian dari artifact

Bagian ini merekam koreksi keadaan fisik development. **Production tidak pernah
menjalankannya**; production hanya menjalankan `forward.sql` r2 di atas.

Development sudah menjalankan r1, jadi tabel kembarnya benar-benar ada dan berisi 40
baris data contoh. Urutan yang dijalankan:

1. `php artisan pa:demo-cycle --hapus` — membuang siklus contoh beserta 40 baris
   pemetaannya. Sesudah ini tabelnya nol baris.
2. Skrip realignment menghitung baris lebih dulu dan **menolak melanjutkan** bila bukan
   nol, karena `data-change.sql` DDL-2026-083 tidak menyeed apa pun; baris di luar itu
   berarti ada data yang tidak dibangun artifact.
3. `drop.sql` **r1** dijalankan — drop.sql milik change ini sendiri, bukan `DROP` yang
   ditulis khusus untuk kesempatan itu. Ini pengecualian rebuild development di
   `AGENTS.md`, dan seluruh syaratnya diverifikasi pada run yang sama: target
   `Web_HRIS` development, change belum `TRACKER_RECORDED`, belum pernah ke production.
4. `forward.sql` r2 dan `data-change.sql` dijalankan.

Verifikasi sesudahnya:

- `N_PA_Question_Competency_Map` tidak ada lagi.
- `N_PA_Cycle_Question_Package_Item` berkolom 15, tiga FK trusted, seluruh CHECK trusted,
  unique index tersaring milik DDL-2026-073 utuh.
- Tidak ada satu pun berkas di `app/`, `tests/`, atau `resources/` yang masih merujuk
  nama tabel lama.
- `forward.sql` r2 dijalankan ulang: no-op.

Bukti jalur pulih, dari `pa:demo-cycle` sesudah realignment:

| Ukuran | Sebelum | Sesudah |
|---|---:|---:|
| Penugasan `SELESAI` | 0 / 8 | 8 / 8 |
| Jejak kategori dengan pengisi | 0 | 8 |
| `N_PA_Result_Competency` | 0 baris | 128 baris |
| `N_PA_Result_Note` | 0 baris | 16 baris |
| Peserta dengan nilai akhir | 0 | 2 (sisanya tertahan dengan sebab yang benar) |

## Agent completed

- Menjalankan realignment dan artifact r2 pada development `Web_HRIS`.
- Mengubah `PaPayloadMapperService`, `PaResultCalculationService`, `PaDemoCycleCommand`,
  dan `ServiceFlowTest` agar seluruhnya membaca satu tabel yang sama.
- Membuang `docs/database/tables/kpi/n-pa-question-competency-map.md` dan memindahkan
  isinya ke dokumen tabel yang bertahan.

## User must do

- Mencatatkan change ini ke SQL Tracker memakai **checksum r2**, dan memastikan checksum
  r1 tidak ikut tercatat.
- Menjalankan `forward.sql` r2 ke production sesudah DDL-2026-073 dan DDL-2026-080.
  Production tidak pernah punya tabel kembarnya, jadi tidak ada yang perlu dibuang di sana.
