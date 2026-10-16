# N_PA_Calibration_History

Riwayat setiap kali seseorang menimpa hasil mesin. **Hanya sisip** — tidak bisa
dinonaktifkan, tidak bisa disunting.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-081-performance-appraisal-result-detail |
| Forward SQL | `database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Calibration_History` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Result_Id` | `int` | T | | Hasil yang dikalibrasi |
| `Kode_Grade_Sebelum` | `varchar(40)` | Y | | Kosong bila belum bergrade sebelumnya |
| `Kode_Grade_Sesudah` | `varchar(40)` | T | | |
| `PA_Hav_Quadrant_Sebelum_Id` | `int` | Y | | |
| `PA_Hav_Quadrant_Sesudah_Id` | `int` | Y | | |
| `Alasan` | `nvarchar(500)` | T | | Wajib, tidak boleh spasi saja |
| `Performed_By_Nama` | `nvarchar(150)` | Y | | Nama pelaku, DIBEKUKAN. Pola yang sama dengan `Nama_Penilai_Saat_Cycle`: pembaca riwayat setahun kemudian belum tentu punya akses ke daftar user, dan user yang keluar membuat join-nya kosong persis pada baris yang paling perlu dijelaskan (DDL-2026-081 r3) |
| `Performed_By` | `int` | T | | Wajib — kalibrasi adalah perbuatan manusia |
| `Performed_At` | `datetime2(0)` | T | `SYSDATETIME()` | |

Sembilan kolom. Tidak ada yang lain, dan itu disengaja.

## Tidak ada `Flag_Aktif`, tidak ada `Updated_At`

Riwayat yang bisa dinonaktifkan atau disunting bukan riwayat. Tabel ini satu-satunya di
seluruh modul PA yang menolak kedua pola itu.

Ada **penjaga skema** di artefaknya: `forward.sql` melempar bila `Flag_Aktif`,
`Updated_At`, atau `Updated_By` kelak ditemukan di tabel ini. Kalau suatu hari ada yang
menambahkannya karena kebiasaan, jalan ulang artefaknya akan mengatakannya — bukan
diam-diam menerima.

## Tiga hal yang ditegakkan, bukan dipercayakan

**`Alasan` wajib dan tidak boleh spasi saja.** Baris ini hanya lahir ketika seseorang
menimpa hasil mesin, dan penimpaan tanpa alasan tidak bisa dipertanggungjawabkan saat
karyawan bertanya kenapa grade-nya berubah.

**`Performed_By` NOT NULL.** Kalibrasi tanpa pelaku tidak bisa diaudit. Ini berbeda dari
`Created_By` di tabel lain yang boleh kosong untuk pekerjaan sistem — kalibrasi tidak
pernah dilakukan sistem.

**Kalibrasi yang tidak mengubah apa pun ditolak.** `CK_PA_Calibration_Ada_Perubahan`
menuntut minimal grade atau kuadrannya bergeser. Tanpa itu, riwayatnya akan terisi baris
yang tidak merekam keputusan apa pun dan hanya menyulitkan pembacanya.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Calibration_History` | Primary key | |
| `FK_PA_Calibration_Result` | Foreign key | → `N_PA_Result` |
| `FK_PA_Calibration_Kuadran_Sebelum` / `_Sesudah` | Foreign key | → `N_PA_Hav_Quadrant`, keduanya nullable |
| `IX_PA_Calibration_Result` | Index | `(hasil, waktu)` — jalur baca riwayat |
| `CK_PA_Calibration_Ada_Perubahan` | Check | Grade atau kuadran wajib bergeser |
| `CK_PA_Calibration_Alasan` | Check | Alasan tidak boleh kosong atau spasi |

## Yang sengaja tidak dijaga database

Bahwa `Kode_Grade_Sebelum` benar-benar sama dengan `Kode_Grade_Final` hasil saat itu.
Memeriksanya butuh membaca tabel lain, dan menjadikannya trigger akan menyembunyikan
logika di tempat yang tak terduga.

## Kedudukan

Anak dari `N_PA_Result`. Bertambah tiap kalibrasi, tidak pernah berkurang.

## Pemakaian aplikasi

Belum ada per 1 September 2026.

## Keadaan data

Kosong.
