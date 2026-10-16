# N_PA_Cycle_Status

Master status siklus Performance Appraisal. Tujuh baris. Inilah daftar resminya —
dokumen mana pun yang menyebut status siklus mencerminkan tabel ini, bukan sebaliknya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-069-performance-appraisal-cycle-status |
| Forward SQL | `database/ddl/DDL-2026-069-performance-appraisal-cycle-status/forward.sql` |
| DML | `database/dml/DDL-2026-069-performance-appraisal-cycle-status/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Status` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Status` | `varchar(20)` | T | | Kode bisnis, **UNIQUE**; inilah yang dirujuk `N_PA_Cycle` |
| `Nama_Status` | `nvarchar(100)` | T | | Nama tampil berbahasa Indonesia |
| `Deskripsi` | `nvarchar(500)` | Y | | Apa yang digerbangi status ini |
| `Urutan_Alur` | `smallint` | T | | Posisi dalam alur, unik selama aktif |
| `Kode_Badge` | `varchar(20)` | T | | Lencana sederhana untuk tampilan: `DRAFT`/`ACTIVE`/`CLOSED`/`ARCHIVED` |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Kenapa ini tabel

Sebelum tabel ini ada, `N_PA_Cycle.Status_Cycle` cuma `varchar` bebas, dan tiga
dokumen memuat tiga daftar berbeda: sembilan status di dokumen desain, enam di
rancangan siklus, empat di mockup PIC. Database tidak menyanggah satu pun, jadi
dokumen mana yang kebetulan dibaca sebuah sesi menjadi kebenaran bagi apa yang
dibangun berikutnya.

Ini tabel acuan, bukan CHECK enum. Aturan proyek melarang CHECK kaku untuk daftar
nilai bisnis yang berkembang — menambah status di sini berarti menyisipkan baris, dan
uji pada change-nya membuktikan status kedelapan diterima.

## Isi

| # | Kode | Nama | Badge | Yang digerbangi |
|---|---|---|---|---|
| 1 | `DRAFT` | Draft | DRAFT | Belum ada yang berjalan; tanggal boleh kosong |
| 2 | `PREPARED` | Disiapkan | ACTIVE | Peserta dan salinan sudah ada; penetapan penilai berjalan |
| 3 | `LOCKED` | Dikunci | ACTIVE | Salinan beku, penilai tetap; keranjang tertahan wajib bersih dulu |
| 4 | `OPEN` | Pengisian Dibuka | ACTIVE | Penilai boleh mengisi |
| 5 | `CLOSED` | Pengisian Ditutup | CLOSED | Tidak ada nilai baru; perhitungan, kalibrasi, dan review di sini |
| 6 | `PUBLISHED` | Terbit | CLOSED | Karyawan boleh melihat kesimpulannya |
| 7 | `ARCHIVED` | Diarsipkan | ARCHIVED | Hanya-baca; hilang dari daftar aktif |

### Tiga status yang sengaja tidak ada

`KALKULASI`, `KALIBRASI`, dan `REVIEW` pernah tercantum di dokumen desain lalu
dibuang (K28), karena masing-masing sudah punya rumah yang lebih tepat.

Kemajuan perhitungan bersifat per peserta dan sudah ada di
`N_PA_Result.Status_Hasil`. Status siklus yang berbunyi "sedang menghitung" adalah
keadaan sementara yang langsung nyangkut begitu job-nya mati, lalu menyatakan
sesuatu yang tidak benar tentang siklusnya.

Review memakai mesin approval generik HCIS (K16) yang melacak keadaannya sendiri.
Menyalinnya ke sini melahirkan dua jawaban untuk pertanyaan "reviewnya sudah selesai
belum", dan keduanya akan menyimpang.

### Kode_Badge menyimpan empat lencana milik mockup

Mockup hanya menampilkan Draft, Active, Closed, dan Archived. Itu penyederhanaan
tampilan, bukan pertentangan: `ACTIVE` menaungi `PREPARED`, `LOCKED`, dan `OPEN`.
Menyimpan pemetaannya sebagai data menjaga aturan itu tetap terlihat oleh siapa pun
yang membaca skema, bukan terkubur di kode front-end.

### ARCHIVED status, bukan Flag_Aktif

Mengarsipkan siklus yang sudah selesai berbeda dari memensiunkan baris yang salah
buat. Kalau digabung, siklus 2025 yang tuntas jadi tidak bisa dibedakan dari siklus
yang lahir karena salah klik. `N_PA_Cycle.Flag_Aktif` tetap dengan artinya semula.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Status` | Primary key | `Id_PA_Cycle_Status` |
| `UQ_PA_Cycle_Status_Kode` | Unique constraint | `Kode_Status` unik. Bukan sekadar index — inilah yang membuatnya sah jadi sasaran foreign key |
| `UX_PA_Cycle_Status_Urutan_Aktif` | Unique index tersaring | `Urutan_Alur` unik `WHERE Flag_Aktif = 'Y'`, supaya status bisa dipensiunkan lalu diganti di posisi sama |
| `CK_PA_Cycle_Status_Urutan` | Check | `Urutan_Alur >= 1` |
| `DF_PA_Cycle_Status_Aktif` / `DF_PA_Cycle_Status_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Hubungan dengan N_PA_Cycle

`N_PA_Cycle.Status_Cycle` menunjuk ke sini lewat `FK_PA_Cycle_Status`, dan sasarannya
**kolom kode, bukan Id**. Ini menyimpang dari konvensi `<Entitas>_Id` di repositori
ini, dengan sengaja: sebuah default constraint tidak boleh memuat sub-kueri, jadi
kunci berbasis Id memaksa kita mengeraskan nilai identity atau membuang
`DEFAULT ('DRAFT')` sama sekali. Menunjuk kode juga membuat baris `N_PA_Cycle`
terbaca tanpa join.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus. Arti sebuah
status tidak boleh berbeda antar siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Tujuh baris seed aktif seperti tabel di atas.
