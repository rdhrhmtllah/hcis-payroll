# DML-2026-050 — Baku masa berlaku dan syarat tanggal terbit

| | |
|---|---|
| Change ID | `DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal` |
| Jenis | DML |
| Target development | `Web_HRIS` (host team311) |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi masih `PENDING` |
| Checksum (sha256) | `22569f1a63e4609a71600737eda8da8087679899e3d4f781fb57feb7b987706b` |
| Dieksekusi ke development | 2026-08-27 |

## Path

- DML: `database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql`
- DDL pendahulu (wajib lebih dulu): `database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql`
- Desain: `plans/sop/2026-08-27-masa-berlaku-versi-design.md`

## Objek terdampak & cakupan

| Tabel | Operasi | Predikat | Baris |
|---|---|---|---|
| `N_SOP_Master_Jenis_Dokumen` | UPDATE | `Masa_Berlaku_Bulan IS NULL` → `24` | 7 |
| `N_SOP_Master_Jenis_Dokumen` | UPDATE | `Siklus_Review_Bulan IS NULL` → `12` | 7 |
| `N_SOP_Master_Tahap_Versi_Syarat` | INSERT | tiga syarat `FILING_REGISTERED` | 3 |
| `N_SYSTEM_Database_Change` | INSERT | pencatatan | 1 |

Cakupan diverifikasi terhadap `Web_HRIS` development sebelum eksekusi; angka di
atas adalah hitungan nyata, bukan perkiraan.

## Isi

### (a) Baku per jenis dokumen

Ketujuh jenis aktif — `KEBIJAKAN`, `SURAT_KEPUTUSAN`, `PROSEDUR`,
`INSTRUKSI_KERJA`, `STANDAR`, `FORMULIR`, `MANUAL` — diisi `24` bulan masa
berlaku dan `12` bulan siklus review.

Predikatnya `IS NULL`, bukan tanpa syarat: menjalankan berkas ini dua kali tidak
menimpa penyetelan yang sudah diubah admin. Angka per jenis memang dimaksudkan
untuk berbeda nanti (Kebijakan 3 tahun, Instruksi Kerja 1 tahun); seragam di
sini hanya titik awal.

### (b) Tiga syarat tanggal untuk `FILING_REGISTERED`

| Urutan | Kode_Syarat | Nama_Syarat |
|---|---|---|
| 3 | `TANGGAL_BERLAKU` | Tanggal mulai berlaku |
| 4 | `TANGGAL_KADALUARSA` | Tanggal kadaluarsa |
| 5 | `TANGGAL_REVIEW_BERIKUTNYA` | Jadwal review berikutnya |

Urutan 1–2 sudah ditempati `BERKAS` dan `REALISASI_PENGESAH` sejak DML-2026-040.

Gerbangnya ada di `FILING_REGISTERED` — saat versi **meninggalkan** Pemberkasan
menuju terbit — bukan saat simpan. Draft boleh setengah jadi; yang tidak boleh
setengah jadi adalah dokumen yang mulai mengikat orang.

`TANGGAL_BERLAKU` ikut dijaga meski selama ini hanya bertanda wajib di form dan
tidak pernah menjadi gerbang. Dua tanggal lainnya dihitung darinya; membiarkan
akarnya tidak terjaga sementara turunannya wajib adalah aturan yang tidak
konsisten dengan dirinya sendiri.

## Hubungan dengan keputusan sebelumnya

**DML-2026-042** mencabut syarat `CATATAN_REVIEW`. Perubahan ini **tidak
mengembalikannya** dan tidak menambah syarat apa pun pada tahap Review. Review
yang berjalan mulus tetap boleh tanpa catatan.

**Keputusan 2026-08-15** mencabut masa berlaku SOP dengan alasan "tak ada satu
pun aturan yang bertindak atasnya". Alasan itu benar saat itu. Yang berubah
bukan pendapat melainkan fakta: slice B dan C dari
`plans/sop/2026-08-27-masa-berlaku-versi-design.md` §7 memberi kolom itu status
keberlakuan `KEDALUWARSA`, spanduk pembaca, dan notifikasi hitung mundur.
Kadaluarsa **tidak pernah** meng-obsolete versi — `OBSOLETE` tetap milik satu
sebab saja, yaitu terbitnya versi pengganti.

## Pekerjaan yang diselesaikan agen

- Menulis `data-change.sql`.
- Menghitung dan melaporkan cakupan (7/7/3/1 baris) sebelum eksekusi.
- Mengeksekusi ke `Web_HRIS` development dan memverifikasi hasilnya: ketujuh
  jenis bernilai `24/12`, dan `FILING_REGISTERED` kini punya lima syarat.
- Mencatat perubahan ke `N_SYSTEM_Database_Change` dan `database/ddl/status.json`.

## Pekerjaan yang tersisa untuk pengguna

- Meninjau dan menyetujui DML.
- Merekam DML yang sama persis ke SQL Tracker (checksum di atas).
- Mengeksekusi ke produksi lewat SQL Tracker, **sesudah** DDL-2026-049.
- Memverifikasi hasil produksi, lalu menaikkan status ke `COMPLETED`.
