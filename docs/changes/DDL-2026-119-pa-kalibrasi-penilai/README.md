# DDL-2026-119-pa-kalibrasi-penilai

## Metadata

- **Change ID**: `DDL-2026-119-pa-kalibrasi-penilai`
- **Target Database**: `Web_HRIS`
- **Type**: DDL (+ DML pasangan `DML-2026-124-urutan-pengisian-kategori-penilai`)
- **Revision**: `r3`
- **Forward Checksum (SHA-256)**: `9a0ed2c23335b6fb8aa685bb7822cc5822b667c4ab7423fa364b35d5319ab678` (r3)
- **Checksum lama yang TIDAK BOLEH dicatat**: r1 `d59051d1955fc574d7d179828effd2993fe2cc18d13e6cc4286e4bc33b3d0b82`, r2 `c963565b73546f2bf2fae3a0bfa1843fd2c8a6a966014445f95f2c530bef2b15`
- **DML Checksum (SHA-256)**: `ed86a2b8fbd9302d0d7d552bacb64b39ccb0ce2d05db48f83e54cbd4ffcb5e2b`
- **Affected Objects**: `N_PA_Rater_Category (Urutan_Pengisian, Flag_Boleh_Kalibrasi)`,
  `DF_PA_Rater_Category_Boleh_Kalibrasi`, `UX_PA_Rater_Category_Urutan`,
  `N_PA_Kalibrasi_Penilai`, `N_PA_Kalibrasi_Penilai_Detail`, `N_PA_Kalibrasi_Penilai_Jawaban`,
  `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Rancangan**: `docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md`

## Deskripsi

Tab "Tugas Penilaian" di PA Saya menjadi wizard yang berjalan per kategori penilai. Di tiap
transisi kategori, penilai melihat skor yang ia berikan; pada kategori yang diizinkan, ia
boleh mengubahnya per kompetensi — dan sejak r2 juga jawaban esainya — sebelum mengirim
(**Kalibrasi Penilai**).

Dua kolom pada kamus kategori penilai:

| Kolom | Tipe | Arti |
| --- | --- | --- |
| `Urutan_Pengisian` | `smallint NULL` | Posisi kategori di wizard, kecil lebih dulu. `NULL` = belum diurutkan, ditaruh paling akhir. |
| `Flag_Boleh_Kalibrasi` | `char(1) NOT NULL DEFAULT 'T'` | `Y` = penilai kategori ini boleh mengubah skornya di pratinjau; `T` = pratinjau hanya-baca. |

Tiga tabel jejak, **hanya ditambah**:

| Tabel | Isi |
| --- | --- |
| `N_PA_Kalibrasi_Penilai` | Satu baris per tindakan simpan penilai atas satu orang: penugasan, alasan, waktu, pelaku. |
| `N_PA_Kalibrasi_Penilai_Detail` | Satu baris per kompetensi yang diubah: skor sebelum, sesudah, dan skala butirnya. |
| `N_PA_Kalibrasi_Penilai_Jawaban` | (r2) Satu baris per butir esai yang diubah: kode soal, kompetensi bila terpetakan, teks sebelum dan sesudah. (r3) `Id_Master_Soal` menunjuk satu soal di dalam bagian itu; NULL untuk jawaban format lama. |

## Latar belakang

Pengisian lembar terjadi di HC Learn, dan kirimannya bersifat final — kiriman ulang dengan
isi berbeda ditolak. Kalibrasi karena itu tidak bisa dilakukan dengan mengisi ulang lembar;
ia disimpan di HCIS sebagai lapisan di atas skor asli. `N_PA_Assignment_Score` tetap catatan
utuh apa yang dikirim HC Learn, dan perhitungan membaca **skor efektif**: revisi terbaru bila
ada, skor asli bila tidak. Pembacanya satu pintu, `PaSkorEfektif`, dijaga uji pemindai.

## Keputusan fisik

- **`Urutan_Pengisian` boleh NULL.** Nilai urutan adalah data bisnis yang masuk lewat DML.
  Kolom NOT NULL akan menuntut rantai DDL → DML → DDL untuk satu keputusan.
- **Indeks unik tersaring `IS NOT NULL`.** SQL Server menganggap NULL sama di indeks unik;
  tanpa saringan, hanya satu kategori yang boleh belum berurutan. Basis data ini sudah punya
  149 indeks tersaring, jadi prasyarat setelan sesi penulisnya sudah terpenuhi.
- **Kalibrasi berkunci (penugasan, kompetensi), bukan id baris skor.**
  `PaKirimanTinjauService::petakanUlang()` menonaktifkan baris skor lama dan menyisipkan yang
  baru; kalibrasi yang menempel ke id baris akan hilang tanpa jejak. `Kode_Soal_Sumber` juga
  tidak bisa jadi kunci: sejak DDL-2026-090 ia boleh kosong dan berulang lintas alat tes.
- **Hanya ditambah.** Tanpa `Flag_Aktif`, `Updated_*`, maupun `Cancelled_*`: revisi yang
  keliru diperbaiki dengan revisi berikutnya, sehingga rantainya asli → revisi 1 → revisi 2
  tetap terbaca utuh.
- **Esai berkunci (penugasan, `Kode_Soal_Sumber`)** (r2). Berbeda dengan tabel bagian
  DDL-2026-090, di `N_PA_Assignment_Score` kolom itu `NOT NULL` dan unik per payload untuk
  baris aktif (`UX_PA_Assignment_Score_Butir`), jadi aman sebagai kunci dan selamat saat
  pemetaan ulang. `PA_Competency_Id` boleh NULL: esai tak terpetakan pun bisa direvisi.
- **Esai tidak boleh dikosongkan** (`CK_PA_Kalibrasi_Penilai_Jawaban_Terisi`, r2). Butir
  kosong terbaca "tidak dijawab" oleh perhitungan dan bisa menahan hasil orang yang dinilai.
- **`CHECK` rentang pada rincian**, mengikuti preseden `CK_PA_Competency_Ambang`: ia menjaga
  rentang angka, bukan daftar nilai bisnis yang berkembang.
- **`Created_By`** adalah `KPI_Users.Id_Users` dari sesi login, bukan dari parameter.

## Kamus hidup — penyimpangan sadar dari K26

K26 membagi tabel PA menjadi kamus (tidak disalin per siklus) dan aturan (disalin per siklus
saat dikunci). `Flag_Boleh_Kalibrasi` menentukan apakah skor boleh diubah, jadi menurut K26 ia
aturan. Pemilik proyek memutuskan pada 18 September 2026 bahwa **urutan dan penanda ini
berlaku langsung**, termasuk pada siklus yang sedang berjalan. Jangan "diperbaiki" menjadi
salinan siklus tanpa keputusan baru.

Jejak audit tetap utuh: keberadaan baris kalibrasi membuktikan penandanya menyala saat itu.

## Artefak

- `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql`
- `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/drop.sql` (manual; tidak dijalankan agen)
- `database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql`

## Nilai awal urutan

`DML-2026-124` mengisi SELF 1, ATASAN 2, PEER 3, BAWAHAN 4 — dikonfirmasi pemilik proyek
18 September 2026. Menilai diri sendiri lebih dulu memberi penilai titik acuan sebelum menilai
orang lain. `Flag_Boleh_Kalibrasi` tidak diubah: seluruh kategori tetap `T` sampai admin
menyalakannya lewat layar master.

## Verifikasi development

**Remote (Web_HRIS @ team311, instance `instance-20240809-031237`) — 18 September 2026.**
Sasaran diverifikasi lewat `SELECT DB_NAME()` + `@@SERVERNAME` sebelum satu pun batch
dijalankan.

- **Sebelum:** `N_PA_Rater_Category` 9 kolom tanpa `Urutan_Pengisian`/`Flag_Boleh_Kalibrasi`,
  4 baris; kedua tabel kalibrasi belum ada; ledger `DDL-2026-119` kosong.
- **`forward.sql`:** 8 batch OK. **`data-change.sql`:** 2 batch OK.
- **Sesudah:** SELF 1, ATASAN 2, PEER 3, BAWAHAN 4, seluruhnya `Flag_Boleh_Kalibrasi = 'T'`;
  `N_PA_Kalibrasi_Penilai` 5 kolom dan `N_PA_Kalibrasi_Penilai_Detail` 6 kolom, keduanya
  0 baris; `UX_PA_Rater_Category_Urutan`, `IX_PA_Kalibrasi_Penilai_Assignment`,
  `UX_PA_Kalibrasi_Penilai_Detail_Kompetensi`, `CK_PA_Kalibrasi_Penilai_Detail_Rentang`,
  dan tiga FK terpasang; ledger 1 baris.
- **Dijalankan ulang:** kedua artefak berakhir no-op — ledger tetap 1 baris, urutan tidak
  berubah.

## Revisi

| Revisi | Tanggal | Perubahan | Pemicu |
| --- | --- | --- | --- |
| r1 | 2026-09-18 | Dua kolom kategori, indeks unik tersaring, `N_PA_Kalibrasi_Penilai` + `_Detail`. | Rancangan awal wizard. |
| r2 | 2026-09-18 | Menambah `N_PA_Kalibrasi_Penilai_Jawaban` (+ indeks unik, CHECK terisi, 2 FK); `drop.sql` ikut mencabutnya; deskripsi ledger menyebut esai. Checksum forward diganti; checksum r1 **tidak boleh dicatat**. | Pemilik proyek memutuskan jawaban esai juga bisa dikalibrasi: tidak boleh dikosongkan, penanda dan alasan sama dengan skor, versi terbaru dipakai di mana-mana. Diamandemen di tempat karena change belum `TRACKER_RECORDED`. |

| r3 | 2026-09-26 | Menambah `Id_Master_Soal int NULL` pada `N_PA_Kalibrasi_Penilai_Jawaban`; `UX_PA_Kalibrasi_Penilai_Jawaban_Soal` menjadi `(PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal)`; deskripsi ledger menyebut esai per soal. Checksum forward diganti; checksum r1 dan r2 **tidak boleh dicatat**. | Callback HC Learn kini mengirim `Esai[]` per soal (DDL-2026-140). Pemilik proyek menyetujui kalibrasi esai per soal pada 26 Sep 2026. Diamandemen di tempat: status `TRACKER_PENDING`, `sql_tracker` dan `production` PENDING, hanya pernah berjalan di development, tabel kalibrasi 0 baris. |

Yang benar-benar berjalan di development: r1 pada 18 Sep 2026 (8 batch), lalu r2 di atasnya
pada hari yang sama. r1 tidak pernah membangun tabel esai. r3 tidak dijalankan sebagai
pembangun: tabelnya sudah ada, jadi development diselaraskan terpisah (lihat di bawah), lalu
`forward.sql` r3 dijalankan ulang dan berakhir no-op.

## Penyelarasan development (BUKAN bagian artefak)

r2 hanya menambah objek, jadi penyelarasan cukup menjalankan ulang `forward.sql` r2 yang
idempoten — tanpa DROP. Sasaran diverifikasi `Web_HRIS @ instance-20240809-031237`.

- Sebelum: 2 tabel kalibrasi, 3 indeks, 1 CHECK, 3 FK, ledger 1 baris.
- Putaran 1: 10 batch OK → 3 tabel, 4 indeks, 2 CHECK, 5 FK, ledger 1.
- Putaran 2: no-op, angka yang sama.
- Baris ledger dev dibuat r1, sehingga `INSERT ... IF NOT EXISTS` r2 tidak memperbarui
  deskripsinya. Diselaraskan dengan satu pernyataan, 1 baris:
  `UPDATE N_SYSTEM_Database_Change SET Deskripsi_Change = <teks r2> WHERE Kode_Change = 'DDL-2026-119'`.
  Production tidak menjalankan ini — ia langsung menerima teks r2 dari `forward.sql`.

## Sisa pekerjaan user

- Meninjau dan menyetujui `forward.sql` serta `data-change.sql`.
- Mencatat keduanya ke SQL Tracker — `forward.sql` **r2**, checksum `c963565b…2b15`.
- Menjalankan ke production melalui SQL Tracker dan mengonfirmasi hasilnya.
- Menyalakan `Flag_Boleh_Kalibrasi` untuk kategori yang memang boleh dikalibrasi, lewat layar
  master jenis penilai.

## Penyelarasan development r3 (BUKAN bagian artefak)

Dijalankan 26 September 2026 terhadap `Web_HRIS @ instance-20240809-031237`, diverifikasi
lewat `SELECT DB_NAME()` + `@@SERVERNAME`. Penjaga di skrip membatalkan bila
`N_PA_Kalibrasi_Penilai_Jawaban` tidak kosong.

```sql
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Jawaban') AND name = 'Id_Master_Soal')
    ALTER TABLE N_PA_Kalibrasi_Penilai_Jawaban ADD Id_Master_Soal int NULL;
GO
CREATE UNIQUE INDEX UX_PA_Kalibrasi_Penilai_Jawaban_Soal
    ON N_PA_Kalibrasi_Penilai_Jawaban (PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal)
    WITH (DROP_EXISTING = ON);
GO
UPDATE N_SYSTEM_Database_Change SET Deskripsi_Change = '<teks r3>' WHERE Kode_Change = 'DDL-2026-119';
```

`DROP_EXISTING` dipakai atas persetujuan pemilik proyek (26 Sep 2026): indeks dibangun ulang
di tempat tanpa `DROP` terpisah dan tanpa menyentuh data.

- Sebelum: `_Jawaban` 6 kolom, 0 baris; indeks `(PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber)`.
- Sesudah: 7 kolom, 0 baris; indeks `(PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal)`;
  ledger tetap 1 baris, deskripsinya diperbarui (1 baris).
- `forward.sql` r3 dijalankan ulang: 10 batch, no-op.
- **Selisih yang diketahui:** di development `Id_Master_Soal` berada di urutan kolom terakhir
  karena ditambah dengan `ALTER`; artefak r3 menaruhnya setelah `Kode_Soal_Sumber`. Hanya urutan
  fisik; seluruh kode memakai nama kolom.
