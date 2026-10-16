# DDL-2026-170-pa-salinan-kompetensi-siklus

## Metadata

- **Change ID**: `DDL-2026-170-pa-salinan-kompetensi-siklus`
- **Target Database**: `Web_HRIS`
- **Type**: DDL
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `8fee8af6be49795dbd93af04b9ff381f36a69bef34d2021a3936c1f1cf04d269`
- **Affected Objects**: `N_PA_Cycle_Competency_Snapshot` (tabel baru), `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/ddl/DDL-2026-170-pa-salinan-kompetensi-siklus/forward.sql`, `drop.sql`; dokumentasi tabel `docs/database/tables/kpi/n-pa-cycle-competency-snapshot.md`

## Deskripsi

`Flag_Berskor`, `Flag_Radar`, dan `Nilai_Ambang` (KKM) hanya hidup di master
`N_PA_Competency`, yang boleh disunting HC kapan saja. Akibatnya mengubah KKM menggeser
jaring KKM hasil yang sudah terbit, mengubah radar mengubah gambar hasil siklus lama, dan
mengubah status berskor mengubah cara catatan kualitatif siklus berjalan dihitung ulang.

Tabel baru `N_PA_Cycle_Competency_Snapshot` membekukan ketiga atribut per siklus. Satu baris
per (siklus, kompetensi), unik. Diisi `PaKompetensiSiklus::bekukan()` saat siklus DIKUNCI
(`PaCyclePreparationService::kunci()`), untuk semua kompetensi aktif. Aman diulang: salinan
yang sudah ada tidak ditimpa.

Pembaca (hasil admin, PA Saya, leader radar dan kompetensi tim, catatan kualitatif di
perhitungan) memakai salinan bila ada, master bila belum. Pembedanya keberadaan baris salinan,
bukan COALESCE nilai: ambang NULL di salinan berarti "tanpa KKM".

Tanpa DML: siklus yang dikunci sebelum perubahan ini tidak bersalinan dan tetap membaca
master, sama seperti sebelumnya.

## Eksekusi development

- 2026-10-08, `Web_HRIS @ instance-20240809-031237` (diverifikasi lewat `DB_NAME()` + `@@SERVERNAME`).
- Sebelum: tabel tidak ada, ledger kosong.
- `forward.sql` 3 batch OK; sesudah: 8 kolom, unique index `(PA_Cycle_Id, PA_Competency_Id)`, ledger 1 baris.
- Dijalankan ulang: no-op.

## Agent completed

- Artefak forward + drop (drop tidak dijalankan), eksekusi dan verifikasi di development, entri `database/ddl/status.json`, dokumentasi tabel.
- Aplikasi: `PaKompetensiSiklus` (bekukan + pembaca), `kunci()` membekukan, lima pembaca memakai salinan.
- Uji: `KompetensiSiklusTest`, `RantaiPenuhTest` (kunci membekukan tepat sekali).

## User tasks

1. Tinjau `forward.sql` dan catat **persis** isinya di SQL Tracker (checksum di atas).
2. Jalankan ke production lewat SQL Tracker **sebelum** kode ini dirilis (kode membaca dan menulis tabel ini), lalu verifikasi tabel dan index ada.
