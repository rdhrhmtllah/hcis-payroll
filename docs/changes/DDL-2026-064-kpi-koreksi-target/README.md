# DDL-2026-064 — Jejak koreksi target KPI

| Bidang | Nilai |
|---|---|
| Tipe | DDL |
| Objek | `N_KPI_Target_Koreksi` (tabel baru) |
| Modul | `KPI` / Monitoring / Enforcement |
| Rancangan | `docs/superpowers/specs/2026-09-03-kpi-koreksi-target-enforcement-design.md` |
| Rencana | `docs/superpowers/plans/2026-09-03-kpi-koreksi-target-enforcement.md` |

## Kenapa

`Nilai_Target` hanya bisa ditulis satu kali, saat parameter diajukan
(`FormKpiController`, dua `insert`). Tidak ada satu pun jalur `update` ke kolom
itu di seluruh `app/`, sehingga salah ketik bersifat permanen.

Kesalahan seperti itu bukan hipotesis: `kpi:cek-target-menyimpang` dibuat
setelah ditemukan satu parameter dengan target bulanan **100 di sebelas bulan
dan 100100 di satu bulan**. Angka itu ikut masuk penyebut YTD, jadi satu digit
kelebihan menjatuhkan achievement satu tahun penuh tanpa gejala apa pun di
layar.

Fitur koreksi target membuka jalur pengubahan untuk admin. Tabel ini yang
membuat pengubahan itu bisa dipertanggungjawabkan.

## Isi tabel

| Kolom | Tipe | Isi |
|---|---|---|
| `Id_Target_Koreksi` | int identity | PK |
| `No_Batch` | varchar(40) | mengikat satu simpan yang menyentuh beberapa periode |
| `Id_Pengajuan_Det` | int | target yang dikoreksi (satu parameter, satu periode) |
| `Nilai_Target_Lama` | float | nilai sebelum koreksi |
| `Nilai_Target_Baru` | float | nilai sesudah koreksi |
| `Sebab` | nvarchar(500) | wajib; satu sebab per simpan, disalin ke tiap baris batch |
| `Id_User_Aktor` | int | pelaku |
| `Waktu` | datetime | default `GETDATE()` |

## Kenapa tabel, bukan kolom pada `KPI_Pengajuan_Det`

Dua alasan.

**Koreksi bisa terjadi lebih dari sekali.** Kolom hanya mengingat yang terakhir,
dan itu justru menghapus riwayat yang jadi alasan fitur ini ada.

**`KPI_Pengajuan_Det` tidak punya kolom jejak sama sekali** — isinya hanya
`Id_Pengajuan_Det, Id_Pengajuan_Detail, Nama_Target, Nilai_Target, Periode,
Id_Target_Breakdown`. Menambahkan kolom audit ke tabel legacy yang dibaca 14
berkas menambah risiko tanpa menambah kemampuan.

Setelah koreksi, tabel ini **satu-satunya tempat** nilai target asli tersimpan.

## Kenapa tanpa foreign key

`kpi:rapikan-target-sebelum-mulai --terapkan` memang menghapus baris
`KPI_Pengajuan_Det` (target untuk bulan sebelum karyawannya mulai). Foreign key
akan ikut menghapus atau menghalangi jejaknya. Jejak harus bertahan melampaui
baris yang dijejakinya — itu justru gunanya.

## Dampak

Tidak ada. Tabel baru, belum dibaca siapa pun sampai
`KpiTargetKoreksiService` dipasang. Tidak ada objek lama yang diubah.

## Pembatalan

`drop.sql` menghapus tabelnya. Aman selama fitur koreksi belum dipakai; setelah
dipakai, menjalankannya berarti membuang riwayat koreksi yang sudah terjadi.
