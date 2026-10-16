# N_PA_Participant_Status

Master status peserta siklus. Tiga baris.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Participant |
| Change ID | DDL-2026-072-performance-appraisal-participant |
| Forward SQL | `database/ddl/DDL-2026-072-performance-appraisal-participant/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Participant_Status` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Status` | `varchar(20)` | T | | Kode bisnis, **UNIQUE**; dirujuk `N_PA_Participant.Status_Peserta` |
| `Nama_Status` | `nvarchar(100)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | Apa yang digerbangi status ini |
| `Urutan_Alur` | `smallint` | T | | Posisi dalam alur |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| # | Kode | Nama | Arti |
|---|---|---|---|
| 1 | `TERTUNDA` | Tertunda | Baris peserta sudah ada tetapi level atau aturan penilainya belum teresolusi |
| 2 | `DIIKUTKAN` | Diikutkan | Teresolusi dan ikut dinilai pada siklus ini |
| 3 | `DIKECUALIKAN` | Dikecualikan | Sengaja tidak dinilai, dengan alasan tertulis |

### TERTUNDA adalah isi keranjang

`TERTUNDA` bukan sekadar keadaan antara. Itulah keranjang kasus nyangkut yang menurut
K17 **memblokir penguncian siklus**: menghitung baris `TERTUNDA` per siklus adalah
satu kueri, dan selama angkanya bukan nol siklus tidak boleh dikunci.

Karena itu pula `TERTUNDA` dijadikan nilai baku pada `N_PA_Participant.Status_Peserta`.
Baris yang belum sempat diklasifikasi siapa pun tidak boleh diam-diam terhitung ikut
dinilai — kalau bakunya `DIIKUTKAN`, peserta yang gagal diresolusi akan lolos ke
pengisian tanpa aturan penilai.

## Kenapa ini tabel

Alasannya sama dengan `N_PA_Cycle_Status` dan `N_PA_Rater_Policy_Status`, tercatat
sebagai K31: kolom berisi daftar nilai tetap wajib punya master ber-foreign key, meski
daftarnya tidak akan tumbuh. Risikonya bukan pertumbuhan melainkan salah eja antar
sesi, dan `varchar` bebas menerima setiap ejaan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Participant_Status` | Primary key | |
| `UQ_PA_Participant_Status_Kode` | Unique constraint | `Kode_Status` unik; inilah yang membuatnya sah jadi sasaran foreign key |
| `CK_PA_Participant_Status_Urutan` | Check | `Urutan_Alur >= 1` |
| `DF_PA_Participant_Status_Aktif` / `_Created` | Default | `'Y'`, `SYSDATETIME()` |

## Kedudukan

**Kamus** menurut K26 — tidak disnapshot per siklus. Arti sebuah status tidak boleh
berbeda antar siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Tiga baris seed aktif.
