# DDL-2026-016 — Master jenis dokumen SOP dan kepemilikan unit kerja

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-016-sop-jenis-dokumen-kepemilikan` |
| Jenis | DDL |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/drop.sql` |
| Desain | `plans/sop/2026-08-22-master-jenis-dokumen-design.md` |
| Dokumentasi tabel | `docs/database/tables/sop/n-sop-master-jenis-dokumen.md`, `docs/database/tables/sop/n-sop-master-jenis-dokumen-tipe-item.md` |

## Kenapa

Dokumen SOP hanya punya satu sumbu klasifikasi teknis (`N_SOP_Dokumen.Tipe`:
DOKUMEN/FORM/VIDEO/KUIS) yang menjawab "berkasnya apa". Tidak ada yang menjawab
dua pertanyaan tata kelola mutu: dokumen ini tingkat apa dalam piramid
Kebijakan → Prosedur → Instruksi Kerja → Formulir, dan unit kerja mana yang
bertanggung jawab atasnya.

`N_SOP_Kategori` bukan jawabannya: itu kategori topik dan melekat pada **paket**,
bukan pada dokumen.

## Objek yang terpengaruh

```text
N_SOP_Master_Jenis_Dokumen                   (tabel baru, 11 kolom)
N_SOP_Master_Jenis_Dokumen_Tipe_Item         (tabel baru, 6 kolom)
N_SOP_Dokumen.Jenis_Dokumen_Id               (kolom baru, int NULL)
N_SOP_Dokumen.Divisi_Id                      (kolom baru, bigint NULL)
N_SOP_Dokumen.Sub_Divisi_Id                  (kolom baru, bigint NULL)

UX_SOP_Jenis_Dokumen_Kode                    (indeks unik)
IX_SOP_Jenis_Dokumen_Urut                    (indeks)
UX_SOP_Jenis_Tipe_Pasangan                   (indeks unik)
IX_SOP_Jenis_Tipe_Cari                       (indeks)
IX_SOP_Dokumen_Jenis                         (indeks)
IX_SOP_Dokumen_Pemilik                       (indeks)

FK_SOP_Jenis_Tipe_Jenis                      (foreign key)
FK_SOP_Dokumen_Jenis                         (foreign key)
```

## Keputusan yang perlu dicatat

**Ketiga kolom baru nullable.** 41 dokumen sudah hidup sebelum kolomnya ada.
`NOT NULL` akan menolak setiap tulisan atas baris lama, termasuk yang tak
menyentuh klasifikasi sama sekali. Kewajiban mengisi ditegakkan di pintu manusia
(controller), bukan di skema.

**Tanpa FK ke `HRIS_Divisi` / `HRIS_Sub_Divisi`.** Keduanya master legacy lintas
sistem yang tidak dikelola modul ini; presedennya sama dengan
`N_SOP_Kepatuhan_Riwayat.Aktor_Id` yang sengaja tanpa FK ke `KPI_Users`.
Kesahihan nilainya dijaga backend (`SopUnitKerja`) saat menulis.

**FK ke `N_SOP_Master_Jenis_Dokumen` dipasang**, karena tabel itu milik domain
SOP dan sudah ada preseden FK dalam domain (`FK_N_SOP_Paket_Item_Dokumen`,
`FK_N_SOP_Item_Versi_Dokumen`). Tanpa cascade — baris pemetaan dicabut lewat
aplikasi, bukan sebagai efek samping.

**Audit actor bertipe `int`, bukan `varchar(100)`.** Sumbernya
`KPI_Users.Id_Users` — identifier yang sama dengan `N_SOP_Kategori.Created_By`
dan `N_SOP_Dokumen.Created_By`. Konsistensi dalam domain SOP menang atas default
lintas-modul di `docs/database/table-design-rules.md`.

**Tanpa CHECK constraint pada daftar nilai bisnis.** Daftar tingkat mutu dan
daftar tipe item sah adalah hal yang diubah bagian mutu; mengunci keduanya di
skema menuntut DDL untuk setiap perubahan kebijakan. Validasinya di backend,
sesuai `docs/database/table-design-rules.md`.

## Hasil eksekusi development

Dijalankan 2026-08-22 terhadap `Web_HRIS`. 12 batch OK.

Verifikasi lewat `INFORMATION_SCHEMA`, `sys.indexes`, dan `sys.foreign_keys`:

```text
N_SOP_Master_Jenis_Dokumen              11 kolom, 0 baris (sebelum seed)
N_SOP_Master_Jenis_Dokumen_Tipe_Item     6 kolom, 0 baris (sebelum seed)
N_SOP_Dokumen                            3 kolom baru, 41 baris TIDAK berubah
Indeks                                   6 dari 6 terbentuk
Foreign key                              2 dari 2 terbentuk
```

Tidak ada baris data yang tersentuh.

## Pengujian

`tests/Feature/LMS/SOP/SopJenisDokumenSkemaTest.php` adalah gerbang skema: ia
satu-satunya bukti otomatis bahwa DDL manual ini sudah dijalankan di basis data
yang sedang dipakai.

## Sisa pekerjaan pengguna

1. Review forward DDL.
2. Catat forward DDL yang sama persis ke SQL Tracker.
3. Jalankan dari SQL Tracker ke production.
4. Verifikasi production, lalu perbarui status di `database/ddl/status.json`.
