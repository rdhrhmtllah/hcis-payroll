# DDL-2026-169-pa-waktu-kirim-penilai

## Metadata

- **Change ID**: `DDL-2026-169-pa-waktu-kirim-penilai`
- **Target Database**: `Web_HRIS`
- **Type**: DDL
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `a3b6ed251f30d839ba091d90a6c888063612c01c45ba80b927499f9b31a500d1`
- **Affected Objects**: `N_PA_Assignment` (3 kolom baru), `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/ddl/DDL-2026-169-pa-waktu-kirim-penilai/forward.sql`, `drop.sql`; dokumentasi tabel `docs/database/tables/kpi/n-pa-assignment.md`

## Deskripsi

Penilai yang menekan Kirim membuat lembarnya `SELESAI`, tetapi kapan itu terjadi hanya
tercatat di `Updated_At`, yang tertimpa perubahan apa pun sesudahnya. Sengketa "kapan
penilai mengirim" tidak bisa dijawab dari database. Perubahan ini menambah tiga kolom
pada `N_PA_Assignment`:

| Kolom | Tipe | Arti |
|---|---|---|
| `Submitted_At` | `datetime2(0) NULL` | Waktu lembar dilepas menjadi `SELESAI` |
| `Submitted_By` | `int NULL` | `KPI_Users.Id_Users` yang melepas: penilai sendiri, admin, atau pelaku penutupan |
| `Submitted_Via` | `varchar(20) NULL` | `PENILAI` (tombol Kirim), `ADMIN` (lepas paksa), `PENUTUPAN` (penutupan pengisian); divalidasi aplikasi, tanpa CHECK |

Diisi oleh `PaRaterSubmitService::lepaskan()`, satu-satunya jalur yang membuat penugasan
`SELESAI`. Tanpa DML: penugasan yang selesai sebelum perubahan ini tetap NULL karena waktu
kirimnya tidak bisa direkonstruksi dengan jujur dari `Updated_At`.

## Eksekusi development

- 2026-10-07, `Web_HRIS @ instance-20240809-031237` (diverifikasi lewat `DB_NAME()` + `@@SERVERNAME`).
- Sebelum: ketiga kolom tidak ada, ledger kosong, 7587 baris.
- `forward.sql` 4 batch OK; sesudah: 3 kolom NULL-able, ledger 1 baris, 7587 baris tidak berubah.
- Dijalankan ulang: no-op.
- Teks `Deskripsi_Change` sempat menyebut nilai `LEPAS_PAKSA`; sebelum artefak di-commit
  dibetulkan menjadi `ADMIN` (nilai yang dipakai kode), dan baris ledger development
  diperbarui satu baris dengan `REPLACE`. Checksum di atas adalah isi akhir.

### Data development di luar artefak

Tiga lembar Ridho (penugasan 40765, 40781, 40796, siklus #11089) dikirim sebelum kolom ini
ada. Waktunya diambil dari log `PA_PENILAI_KIRIM` (2026-10-07 22:36:20, user 4, `PENILAI`)
dan ditulis langsung di development. Bukan bagian artefak; production tidak memuat baris ini.

## Agent completed

- Artefak forward + drop (drop tidak dijalankan), eksekusi dan verifikasi di development, entri `database/ddl/status.json`, dokumentasi tabel.
- Aplikasi: `PaRaterSubmitService` mengisi ketiga kolom; uji `WizardPenilaianTest::test_kirim_mencatat_waktu_pelaku_dan_jalur_pengiriman`.

## User tasks

1. Tinjau `forward.sql` dan catat **persis** isinya di SQL Tracker (checksum di atas).
2. Jalankan ke production lewat SQL Tracker **sebelum** kode ini dirilis ke production (kode menulis ke kolom ini saat penilai mengirim), lalu verifikasi ketiga kolom ada.
