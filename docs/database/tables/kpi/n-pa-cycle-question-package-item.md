# N_PA_Cycle_Question_Package_Item

Salinan daftar kode soal yang dimuat sebuah paket HC Learn pada saat paket itu dipilih.
Inilah kontrak harapan yang dibandingkan dengan hasil callback.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Cycle |
| Change ID | DDL-2026-073-performance-appraisal-cycle-question-package, diperluas DDL-2026-083 r2 |
| Forward SQL | `database/ddl/DDL-2026-073-performance-appraisal-cycle-question-package/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Cycle_Question_Package_Item` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Cycle_Question_Package_Id` | `int` | T | | FK ke header paket |
| `Kode_Soal` | `varchar(100)` | T | | Kode dari HC Learn; kunci sambung ke kamus pemetaan |
| `Nama_Soal_Sumber` | `nvarchar(200)` | Y | | Nama versi HC Learn |
| `Jenis_Soal` | `varchar(30)` | Y | | Jenis dari HC Learn (`skala`, `teks`, …). Sengaja TANPA CHECK: daftarnya milik pihak lain dan bisa tumbuh (DDL-2026-083 r2) |
| `PA_Result_Note_Type_Id` | `int` | Y | | Terisi berarti jawaban teksnya menjadi catatan hasil dengan jenis ini (DDL-2026-083 r2) |
| `PA_Competency_Id` | `int` | Y | | Hasil terjemahan; **NULL berarti kodenya belum dikenali** |
| `Bobot_Penilaian` | `decimal(5,2)` | Y | | Bobot antar butir milik HC Learn |
| `Skor_Maksimal` | `decimal(6,2)` | Y | | Skala maksimum butir itu |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Snapshot_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Snapshot_By` | `int` | Y | | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Skor_Maksimal dibaca dari data, bukan diasumsikan

Contoh payload HC Learn mengembalikan `Skor_Maksimal: 100`, sementara deck PIC
menampilkan skala 1–5. Selama T05 belum dijawab, satu-satunya cara aman adalah
menyimpan skalanya per butir dan membacanya kembali saat menghitung.

Terbukti pada uji change ini: satu paket bisa memuat butir berskala 5 dan butir
berskala 100 sekaligus, dan keduanya diterima. Jadi paket bercampur skala pun tidak
bisa diam-diam merusak perhitungan.

## Bobot_Penilaian adalah alat uji silang

Pada contoh payload, `(41×20 + 60×30 + 80×50) ÷ 100` menghasilkan tepat `66,2` — sama
persis dengan `Total_Nilai` yang dilaporkan HC Learn. Menyimpan bobot mereka membuat
pemeta kita bisa **membuktikan** menghitung hal yang sama, bukan sekadar
mempercayainya.

## PA_Competency_Id boleh NULL, dan itu disengaja

Kode soal yang belum ada di `N_PA_Competency_Kode_Soal` tetap disimpan dengan
kompetensi kosong. Butir yang tidak dikenali harus **terlihat** dan bisa dilaporkan,
bukan hilang diam-diam. Setelah admin memetakan kodenya sekali, seluruh paket
berikutnya yang memakai kode itu langsung dikenali.

## Yang sengaja tidak disimpan

`Id_Nilai_Jenis_Soal` dari payload. Isinya token 64 karakter yang tampak per hasil,
bukan pengenal tetap jenis soal, jadi menyimpannya sebagai kunci sambung akan
menyesatkan pembacanya. `Kode_Soal` yang jadi kunci sambung.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Cycle_Question_Package_Item` | Primary key | |
| `FK_PA_Cycle_Question_Item_Header` | Foreign key | → `N_PA_Cycle_Question_Package` |
| `FK_PA_Cycle_Question_Item_Competency` | Foreign key | → `N_PA_Competency`, nullable |
| `UX_PA_Cycle_Question_Item_Aktif` | Unique index tersaring | `(PA_Cycle_Question_Package_Id, Kode_Soal)` saat aktif |
| `IX_PA_Cycle_Question_Item_Cakupan` | Index | Jalur pemeriksaan cakupan, dengan `INCLUDE` kode, kompetensi, bobot, dan skala |
| `CK_PA_Cycle_Question_Item_Bobot` | Check | `Bobot_Penilaian` NULL atau 0–100 |
| `CK_PA_Cycle_Question_Item_Maksimal` | Check | `Skor_Maksimal` NULL atau lebih dari 0 |
| `CK_PA_Cycle_Question_Item_Urutan` | Check | `Urutan_Tampil >= 1` |

`Skor_Maksimal` dilarang nol karena skala nol membuat normalisasi membagi dengan nol
— nilai yang tidak diketahui ditulis NULL, bukan 0.

## Kedudukan

Lapisan kedua, anak dari `N_PA_Cycle_Question_Package`.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.

## Kenapa tabel ini satu-satunya pemetaan kode soal

DDL-2026-083 r1 sempat membuat tabel kedua, `N_PA_Question_Competency_Map`, yang
melakukan hal yang sama persis — per paket, dengan unique index tersaring yang sama.
Kembarnya tidak sekadar mubazir; ia **memutus rantai**: `PaPayloadMapperService::petaSoal()`
membaca peta dari tabel baru sementara `butirDiharapkan()` membaca tabel ini yang kosong,
sehingga payload terpetakan tetapi tidak pernah dianggap lengkap, penugasan tidak pernah
ditandai `SELESAI`, seluruh penilai dilewati saat perhitungan, dan pilar kompetensi
menjadi kosong **tanpa satu pun pesan galat**.

Terukur saat ditemukan: 1.358 baris `N_PA_Result_Rater_Category` dengan
`Jumlah_Rater_Mengisi = 0`, `N_PA_Result_Competency` nol baris, dan seluruh peserta
`TERTAHAN`. Delapan puluh baris skor tersimpan rapi selama itu.

DDL-2026-083 r2 membuang tabel kembarnya dan memindahkan dua kolom yang memang baru —
`Jenis_Soal` dan `PA_Result_Note_Type_Id` — ke sini.

**Aturannya sekarang: satu paket, satu tabel butir.** Pemeta payload dan pemeriksa
kelengkapan WAJIB membaca tabel yang sama. Uji `ServiceFlowTest` sempat menyisipkan ke
dua tabel sekaligus dan karena itu tetap hijau sementara jalur sungguhan putus; fixture
seperti itu tidak boleh muncul lagi.

## Peran butir: nilai kompetensi ATAU catatan, tidak keduanya

`CK_PA_Cycle_Question_Item_Peran` melarang satu butir menunjuk `PA_Competency_Id` dan
`PA_Result_Note_Type_Id` sekaligus. Nilai kompetensi dirata-ratakan, catatan dibaca apa
adanya; butir yang menjadi keduanya akan dihitung dua kali dengan arti yang berbeda.

Butir uraian karena itu **tidak** menunjuk kompetensi. Memaksanya menunjuk kompetensi
membuat jawaban teks ikut dihitung sebagai angka nol.
