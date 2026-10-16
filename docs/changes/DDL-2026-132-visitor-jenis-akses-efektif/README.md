# DDL-2026-132-visitor-jenis-akses-efektif

## Ringkas

Baris di `N_VISITOR_Permit_Efektif` **tidak lagi berarti "sudah disetujui"**.
Kolom `Jenis_Akses` menggantikan pembeda berbasis teks.

| | |
|---|---|
| Change ID | `DDL-2026-132-visitor-jenis-akses-efektif` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DDL-2026-123` (jadwal inspeksi), `DML-2026-130` (kontrak bendera) |
| Status | `DEV_EXECUTED` |

## Sebab

Sejak akses palang sementara untuk menemui SHE, pengajuan yang **belum diputus**
pun punya baris di proyeksi gerbang. Pembedanya selama ini hanya kalimat:
`Nama_Durasi = 'Akses Inspeksi'` dan `Nama_Kategori_Permit = 'AKSES INSPEKSI -
BELUM DISETUJUI'`.

Kalimat bukan kontrak. Satu orang merapikan label itu memutus aplikasi palang
**dan** penyapu kita sendiri sekaligus, tanpa satu pun galat. Lebih buruk lagi,
`Nama_Kategori_Permit` adalah kolom yang sama dipakai kategori asli
(`HIJAU`/`MERAH`/`COKLAT`), sehingga menitipkan kalimat status ke sana membuat
setiap laporan yang mengelompok per kategori mendapat ember hantu.

## Pembaca yang ternyata sudah salah

Audit atas 14 pembaca tabel ini menemukan **tiga** yang masih memegang invarian
lama, dan satu di antaranya merusak data:

| Pembaca | Akibat sebelum perbaikan |
|---|---|
| `VerifikatorVisitorController::bulkRevokePermit` | "Pilih semua yang cocok" merangkai targetnya dari `JOIN … pe.Status_Efektif='AKTIF'`. Pengajuan yang belum diputus ikut tercabut dan ditandai `DIBATALKAN` — **membatalkan keputusan yang belum pernah diambil**. |
| `VisitorDashboardAnalitikService::permitPerKategori` | Kategori hantu berisi kalimat status di grafik; angka kategori asli terencerkan. |
| `VisitorPermitDataset::baseQuery` | Seluruh Export Studio permit ikut memuat baris percobaan. |

Sepuluh sisanya aman: mereka hanya membaca `Status_Efektif = 'DIBATALKAN'`
(deteksi pencabutan), membaca tanggal, atau lingkupnya permit coklat tamu.

## Objek

**DDL (`forward.sql`)**

| Objek | Keterangan |
|---|---|
| `Jenis_Akses varchar(20) NOT NULL DEFAULT 'PERMIT'` | Nilai `PERMIT` \| `INSPEKSI`. Aditif dan berdefault, jadi pembaca lama — termasuk aplikasi pihak ketiga yang belum tahu kolom ini — tidak terganggu. |
| `IX_VISITOR_Permit_Efektif_Jenis_Akses` | `(Jenis_Akses, Status_Efektif) INCLUDE (No_Transaksi)`. Penyaring "hanya permit sungguhan" dipakai laporan, dashboard, export, dan pencabutan massal. |

**DML (`data-change.sql`)**: menandai baris yang sudah ada dari penanda teks
yang dipakai sampai sekarang. Idempoten.

## Kode

- `VisitorEfektifService`: konstanta `JENIS_PERMIT` / `JENIS_INSPEKSI`;
  `terbitkanAksesInspeksi()` menulis `INSPEKSI` **dan mengembalikan
  `Nama_Kategori_Permit` berisi kategori asli** (palang tetap tahu zona
  sebenarnya); `syncPermitEfektif()` mengubahnya menjadi `PERMIT` saat
  disetujui; `akhiriAksesInspeksi()` menyaring kolom, bukan teks.
- Tiga pembaca di tabel atas menyaring `Jenis_Akses = 'PERMIT'`.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `forward.sql` | OK — kolom + indeks terbentuk. |
| 2026-09-22 | `data-change.sql` | OK — baris bertanda teks ditandai `INSPEKSI`. |

Uji: `PermitEfektifFlagBatalTest` 19 lulus (tiga penjaga baru: penandaan lewat
kolom, persetujuan mengubahnya jadi `PERMIT`, dan pencabutan massal tidak
menyeret pengajuan yang belum diputus).

## Pekerjaan user

- Rekam di SQL Tracker; urutan `forward.sql` lalu `data-change.sql`.
- **Beri tahu tim aplikasi palang.** Mereka belum wajib memakainya — defaultnya
  menjaga perilaku lama — tetapi begitu dipakai, mereka bisa berhenti
  mencocokkan kalimat `'Akses Inspeksi'` di kodenya sendiri.
