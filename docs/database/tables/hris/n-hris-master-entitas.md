# N_HRIS_Master_Entitas

Master badan hukum (entitas perusahaan) dalam grup.

| Bidang | Nilai |
|---|---|
| Modul | SYSTEM / MasterData / Entitas — master lintas modul |
| Change ID | `DDL-2026-019-hris-master-entitas` |
| Forward SQL | `database/ddl/DDL-2026-019-hris-master-entitas/forward.sql` |
| Seed | `database/dml/DML-2026-020-seed-hris-entitas/data-change.sql` |

## Tujuan

Menjawab "badan hukum apa saja yang ada di grup ini" — pertanyaan yang sebelumnya
tak bisa dijawab meski `Kode_Perusahaan` hadir di 141 tabel tanpa satu pun master.

**Bukan daftar lokasi.** Cabang dan site sudah punya `N_HRIS_Master_Lokasi`
(14 baris terisi: HO Palembang dan cabang-cabangnya). Entitas menjawab pertanyaan
yang berbeda.

**Tidak berhubungan dengan divisi/departemen.** `HRIS_Divisi` dan
`HRIS_Sub_Divisi` tidak diatribusikan ke entitas mana pun, tidak ada penyaringan
bertingkat, dan tidak ada tabel pemetaan. Itu keputusan sadar.

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_Entitas` | `int IDENTITY` | NO | — | PK |
| `Kode_Entitas` | `varchar(10)` | NO | — | `ENB`, `EMI`, `GMN`, `EHG`. Unik, huruf besar, rujukan tetap. |
| `Nama_Entitas` | `nvarchar(150)` | NO | — | Nama resmi badan hukum. |
| `Nama_Singkat` | `nvarchar(50)` | YES | — | Untuk badge sempit; jatuh ke `Kode_Entitas` bila kosong. |
| `Warna` | `varchar(20)` | YES | — | Hex `#RRGGBB`. Kosong → frontend jatuh ke `kategoriTheme(Kode_Entitas)`. |
| `Logo_Path` | `varchar(255)` | YES | — | DDL-2026-022. Menampung path GCS hasil unggah **atau** URL/path publik; `EntitasService::logoUrl()` meneruskan yang sudah berupa URL dan menandatangani sisanya. Kosong → badge memakai singkatan di atas `Warna`. |
| `Induk_Entitas_Id` | `int` | YES | — | FK ke tabel ini sendiri. Payung → NULL; anak → id payungnya. |
| `Urutan` | `int` | NO | 0 | Urutan tampil. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | `Y`/`T`. Nonaktif ditolak dipakai dokumen baru. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `KPI_Users.Id_Users`, mengikuti preseden master SOP. |
| `Updated_At` | `datetime2(0)` | YES | — | |
| `Updated_By` | `int` | YES | — | |

## Indeks dan constraint

| Nama | Bentuk | Alasan |
|---|---|---|
| `PK_HRIS_Entitas` | PK (`Id_Entitas`) | |
| `UX_HRIS_Entitas_Kode` | UNIQUE (`Kode_Entitas`) | Kode adalah rujukan seed dan integrasi; kembar membuat pemetaan ambigu. |
| `IX_HRIS_Entitas_Urut` | (`Flag_Aktif`, `Urutan`) INCLUDE (`Kode_Entitas`, `Nama_Entitas`) | Bentuk baca yang selalu dipakai dropdown. |
| `FK_HRIS_Entitas_Induk` | FK ke tabel sendiri | Hirarki payung; tanpa cascade. |

Tanpa CHECK constraint pada `Kode_Entitas`: daftar badan hukum berkembang, jadi
validasinya di backend (`docs/database/table-design-rules.md`).

## Relasi

```text
N_HRIS_Master_Entitas 1 --- n N_HRIS_Master_Entitas.Induk_Entitas_Id  (payung)
N_HRIS_Master_Entitas 1 --- n N_SOP_Dokumen.Entitas_Pemilik_Id
N_HRIS_Master_Entitas 1 --- n N_SOP_Dokumen.Entitas_Lingkup_Id
```

## Pemakai di aplikasi

| Lapis | Berkas |
|---|---|
| Service | `app/Services/SYSTEM/MasterData/Entitas/EntitasService.php` |
| Penjaga pemakaian | `app/Services/LMS/SOP/SopDokumenService.php` (`pastikanEntitasSah`) |
| Controller pengelolaan | `app/Http/Controllers/SYSTEM/MasterData/Entitas/EntitasController.php` |
| Route pengelolaan | `routes/SYSTEM/MasterData/Entitas.php` — gerbang `auth` + `system.admin` |
| Halaman pengelolaan | `resources/js/Pages/SYSTEM/MasterData/Entitas/EntitasPage.vue` |
| Org chart | `HcisOrgChart` diberi `EntitasService::pohon()` — pohon dari `Induk_Entitas_Id`, sumber yang sama dengan `cakupan()` |
| Endpoint baca (SOP) | `SopJenisDokumenController::opsiEntitas` → `GET /api/v1/sop/admin/master-data/entitas` |
| Layar konsumen | `SopMasterDokumen.vue`, `ItemFields.vue` (blok "Dokumen Baru" di Builder) |

Service-nya milik SYSTEM karena masternya general; yang tinggal di SOP hanya
permukaan HTTP-nya, karena gerbang aksesnya memang milik layar SOP. Modul lain
yang butuh entitas menambah endpoint bergerbang sendiri yang memanggil service
yang sama — bukan menyalin daftarnya.

## Alur data

```text
Seed DML-2026-020  ->  EHG lebih dulu, lalu ENB/EMI/GMN menunjuknya

Admin mendeklarasikan dokumen (Master Dokumen ATAU Builder)
  -> GET /master-data/entitas  -> opsi(), menandai Cakup_Semua bagi payung
  -> POST/PUT dokumen
       -> controller menegakkan KEWAJIBAN isi (pemilik + ruang lingkup)
       -> SopDokumenService::pastikanEntitasSah() menegakkan KESAHIHAN
       -> N_SOP_Dokumen.Entitas_Pemilik_Id / Entitas_Lingkup_Id terisi

Membaca
  -> daftar & detail menerjemahkan id jadi kode, nama, dan WARNA badge
  -> jejak audit (N_SOP_Log) menyimpan NAMA entitas, bukan id
  -> cakupan payung dihitung EntitasService::cakupan() dari Induk_Entitas_Id
```

## Aturan lifecycle

Entitas nonaktif (`Flag_Aktif = 'T'`) hilang dari dropdown DAN ditolak dipakai
dokumen baru. Menyembunyikannya dari layar saja tidak cukup: pemanggil yang
mengirim id lama akan tetap lolos.

Menonaktifkan ditolak selama masih ada **dokumen** yang menunjuknya (sebagai
pemilik maupun ruang lingkup) atau **entitas anak** yang bernaung di bawahnya.
Yang kedua bukan kehati-hatian berlebih: payung yang dinonaktifkan sementara
anaknya masih menunjuknya membuat anak itu berinduk pada sesuatu yang tak muncul
di mana pun, dan `cakupan()`-nya menyusut diam-diam.

`Kode_Entitas` **terkunci setelah dibuat**. Ia rujukan tetap bagi seed dan
integrasi; membiarkannya berpindah berarti membiarkan rujukan menunjuk entitas
lain tanpa satu pun jejak.

`Induk_Entitas_Id` menolak lingkaran: entitas tak boleh menjadi induk dirinya
sendiri maupun induk salah satu leluhurnya. Tanpa penjaga ini `cakupan()` akan
berputar dan permintaannya menggantung sampai timeout — kegagalan yang muncul
jauh dari tempat kesalahannya dibuat.

## Logo

`Logo_Path` sengaja menampung dua bentuk supaya entitas tetap bisa berlogo di
environment yang kredensial GCS-nya belum siap. Unggahan dikirim sebagai data URL
base64 dan disimpan ke `system/entitas-logo/YYYY/MM/<kode>-<uuid>.<ext>`,
mengikuti pola `SopAdminBuilderController::resolveCover()`.

Kegagalan menandatangani URL **tidak dilempar**: satu logo yang tak bisa
ditampilkan tak boleh menjatuhkan seluruh halaman master, jadi `logoUrl()`
mengembalikan `null` dan layar jatuh ke badge singkatan.

Batas verifikasi: jalur unggah belum terbukti end-to-end di development karena
kredensial GCS di server itu rusak. Lihat `docs/changes/DDL-2026-022-*`.

## Otorisasi halaman pengelolaan

Gerbangnya middleware `system.admin` (`KPI_Users.Is_System_Admin = 'Y'`), BUKAN
`check.jabatan`. Alasannya terukur: grup fitur SYSTEM tak dipegang satu pun role
di `N_HRIS_Role_Functional`, dan halaman SYSTEM yang sudah ada pun nol baris di
`HRIS_Page_Access`. Lihat `docs/changes/DML-2026-021-*`.
