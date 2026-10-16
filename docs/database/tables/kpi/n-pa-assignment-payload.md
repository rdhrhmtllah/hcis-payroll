# N_PA_Assignment_Payload

Bundel jawaban dari HC Learn, disimpan apa adanya. Inilah bukti mentah yang tidak boleh
disentuh; angka yang dipakai menghitung lahir dari proyeksinya, bukan dari sini
langsung.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-076-performance-appraisal-payload-and-score |
| Forward SQL | `database/ddl/DDL-2026-076-performance-appraisal-payload-and-score/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment_Payload` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Assignment_Integration_Id` | `int` | T | | Baris integrasi/token yang benar-benar mengirim hasil; penugasan dan paket soal diturunkan dari sini |
| `Payload_Json` | `nvarchar(max)` | T | | Balasan callback apa adanya |
| `Payload_Hash` | `varchar(64)` | T | | SHA-256 isinya; tepat 64 karakter |
| `Id_Ujian_Nilai_Akhir` | `varchar(255)` | Y | | Kunci idempotensi dari HC Learn; unik saat terisi |
| `Api_Version` | `varchar(20)` | Y | | Bentuk kontrak saat itu |
| `Diterima_At` | `datetime2(0)` | T | `SYSDATETIME()` | Kapan callback masuk |
| `Flag_Terpeta` | `char(1)` | T | `'T'` | Sudah diproyeksikan jadi skor atau belum |
| `Mapper_Version` | `int` | Y | | Pemeta versi berapa yang memproyeksikannya |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | Hanya untuk kedua kolom di atas |

## "Sisip saja" berlaku pada isinya, bukan seluruh barisnya

Rancangan selalu menyebut tabel ini sisip saja. Kalau diartikan harfiah itu mustahil:
keadaan "sudah diproyeksikan" memang berubah **setelah** barisnya lahir, dan harus
tinggal di suatu tempat.

Jadi aturannya dinyatakan tepat. `Payload_Json`, `Payload_Hash`,
`Id_Ujian_Nilai_Akhir`, `Api_Version`, dan `Diterima_At` tidak pernah di-`UPDATE` — bukti
apa yang benar-benar dikirim HC Learn tetap utuh. `Flag_Terpeta` dan `Mapper_Version`
satu-satunya yang boleh berubah, dan keduanya menggambarkan **pemrosesan kita**, bukan
data mereka.

`Diterima_At` menggantikan `Created_At`, bukan mendampinginya. Keduanya menamai kejadian
yang sama, dan DDL-073 sudah membuang pasangan serupa dengan alasan itu.

## Menunjuk integrasi asal, bukan penugasan langsung

Satu penugasan dapat mengganti paket soal. Token lama sengaja tetap boleh menyetor
hasil, sehingga payload harus mengingat baris integrasi/token yang benar-benar
mengirimnya. Kalau payload hanya menunjuk penugasan, pemeta tidak dapat membedakan
paket lama dari paket aktif yang baru dan berisiko menerapkan pemetaan soal yang salah.

`PA_Assignment_Id` tidak diduplikasi. Ia sudah dapat diturunkan dari
`N_PA_Assignment_Integration`, sama seperti skor menurunkan penugasannya melalui
payload. Ini menjaga satu sumber kebenaran.

## Idempotensi bergantung pada Id_Ujian_Nilai_Akhir, bukan hash

Unique index tersaring pada `Id_Ujian_Nilai_Akhir` mencegah satu hasil diproyeksikan
dua kali. `Payload_Hash` sengaja hanya diberi index biasa: menjadikannya unik berisiko
menolak pengerjaan ulang yang sah yang kebetulan menghasilkan bundel identik — itu
membuang data nyata demi mencegah duplikat yang sudah dicegah kunci yang benar. Hash-nya
tetap berguna untuk mengenali kiriman identik tanpa memuat JSON-nya.

## Dua payload untuk satu penugasan itu wajar

Kirim ulang membutuhkannya. Baris dengan kunci NULL juga boleh banyak, karena NULL tidak
saling bentrok pada index tersaring — jadi bundel yang datang sebelum kuncinya ada tetap
bisa disimpan. Keduanya sudah diuji.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment_Payload` | Primary key | |
| `FK_PA_Assignment_Payload_Integration` | Foreign key | → `N_PA_Assignment_Integration` |
| `UX_PA_Assignment_Payload_Nilai_Akhir` | Unique index tersaring | `WHERE Id_Ujian_Nilai_Akhir IS NOT NULL` |
| `IX_PA_Assignment_Payload_Integration` | Index | `(integrasi, Flag_Terpeta)` — jalur "apa yang belum dipeta" |
| `IX_PA_Assignment_Payload_Hash` | Index | Pengenalan kiriman identik |
| `CK_PA_Assignment_Payload_Hash` | Check | Panjang hash tepat 64 |
| `CK_PA_Assignment_Payload_Terpeta` | Check | Terpeta `Y` wajib membawa versi pemeta |
| `CK_PA_Assignment_Payload_Mapper` | Check | Versi pemeta minimal 1 |

## Yang sengaja tidak dijaga database

Apakah seluruh butir yang dijanjikan kontrak paket soal benar-benar datang; melibatkan
`N_PA_Cycle_Question_Package_Item` dan sifatnya dihitung. Butir yang kurang mengirim
pesertanya ke keranjang tertahan.

Payload yang datang setelah siklus ditutup juga tidak dicegah di sini — bergantung pada
`N_PA_Cycle.Status_Cycle` yang tak terlihat dari baris ini.

## Kedudukan

Lapisan pelaksanaan, anak dari `N_PA_Assignment`, induk dari `N_PA_Assignment_Score`.

## Pemakaian aplikasi

`App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService` menerima callback,
menjaga idempotensi, dan memetakan butir memakai paket dari integrasi asal.

## Keadaan data

Kosong.
