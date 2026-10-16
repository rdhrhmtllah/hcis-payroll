# Audit skema & izin SOP/DMS di production — 2026-09-16

Pemicu: Builder Paket SOP membalas **HTTP 500**. Audit ini read-only; tidak ada
satu pun pernyataan tulis yang dijalankan terhadap production.

| | |
|---|---|
| Host | `34.143.236.80:1433` |
| Database | `emi_db_web_hr` |
| Login yang dipakai audit | `srv_team_web` (dari `.env` lokal) |
| Login aplikasi production | **BERBEDA** — tidak diperiksa di sini |
| Pembanding | `Web_HRIS` @ `team311.dyndns.info` (development) |

> Karena login audit berbeda dari login aplikasi production, temuan di bawah
> dipisah tegas menjadi dua: **(A) yang memang belum ada di production** — berlaku
> untuk siapa pun, termasuk aplikasi; dan **(B) yang hanya tidak bisa kita lihat** —
> murni batas akun audit, bukan kerusakan.

---

## 1. Ringkasan

| | Jumlah |
|---|---:|
| Tabel diprobe (seluruh tabel yang disentuh modul SOP) | 50 |
| **Tabel yang benar-benar TIDAK ADA di production** | **0** |
| Tabel ada tetapi tidak terbaca oleh `srv_team_web` | 13 |
| **Kolom yang benar-benar BELUM ADA di production** | **10** (di 4 tabel) |
| Selisih kolom pada 37 tabel yang terbaca | 0 |

Jadi tidak ada tabel yang hilang. Yang hilang adalah **sepuluh kolom**, dan
semuanya berasal dari satu DDL yang belum pernah dijalankan.

---

## 2. (A) Yang MEMANG belum ada di production — 10 kolom

Diverifikasi lewat pesan galat SQL Server, yang meresolusi nama kolom **sebelum**
memeriksa izin: kolom karangan menjawab `Invalid column name`, kolom nyata menjawab
`The SELECT permission was denied on the object`. Jadi daftar ini sahih meskipun
tabelnya tak terbaca akun audit.

| Tabel | Kolom yang belum ada |
|---|---|
| `N_SOP_Master_Paket` | `Versi_Paket_Aktif`, `Id_SOP_Paket_Versi_Aktif` |
| `N_SOP_Penjadwalan` | `Nama_Batch`, `Keterangan`, `Id_SOP_Assignment_Rule` |
| `N_SOP_Progress` | `Id_Ujian_Token_Hasil`, `Id_Versi_Asal` |
| `N_SOP_Absensi` | `Akurasi_Meter`, `User_Agent`, `IP_Address` |

Sembilan tabel sisanya yang tak terbaca (`N_SOP_Aktivitas_Log`,
`N_SOP_Coverage_Rollup`, `N_SOP_Favorit`, `N_SOP_Item_Versi_Berkas`,
`N_SOP_Kategori`, `N_SOP_Kuis_Integrasi`, `N_SOP_Paket_Section`,
`N_SOP_Penjadwalan_Integrasi`, `N_SOP_Setting`) **lengkap** — setiap kolom dev ada
di production.

### Asalnya: DDL-2026-092, dan kenapa menjalankannya sekarang TIDAK menolong

Kesepuluh kolom itu hanya muncul di
`database/ddl/DDL-2026-092-sinkronisasi-skema-production/forward.sql`, pada
**Bagian 5 — 44 tabel baru**, yaitu di dalam pernyataan `CREATE TABLE`, bukan
sebagai `ALTER TABLE … ADD`. Status change ini masih `REVIEW_REQUIRED` /
production `PENDING`.

Skrip itu disusun dari perbandingan tanggal **2026-09-10**, ketika tabel-tabel SOP
memang belum ada di production. Tabelnya baru dibuat **11–12 September** lewat
jalur lain, dengan definisi yang lebih lama. Akibatnya penjaga idempotennya kini
selalu berbalik:

```sql
IF OBJECT_ID('dbo.[N_SOP_Master_Paket]', 'U') IS NULL   -- sekarang TIDAK NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Master_Paket] (
        ...
        [Versi_Paket_Aktif]        int NULL,      -- <- tak pernah tercipta
        [Id_SOP_Paket_Versi_Aktif] bigint NULL,   -- <- tak pernah tercipta
    );
END;
```

Seluruh blok dilewati, dan kesepuluh kolom tidak akan pernah terpasang.
**Dibutuhkan DDL baru berisi `ALTER TABLE … ADD` untuk kesepuluh kolom itu.**

### Jejak kolom yang hilang di kode

`SkemaTersedia::kolom()` (`app/Services/SYSTEM/Application/Database/SkemaTersedia.php`)
sudah ada persis untuk menangani drift semacam ini, tapi hanya dipakai di sebagian
jalur. Titik sentuh berikut **tanpa penjaga**, jadi di production ia melempar
`Invalid column name` → 500:

`Versi_Paket_Aktif`
- `Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php:669,671`
  — di `reconcileSetelahPublish()`, dipanggil dari `updateStatusPaket()`.
  **Publish paket lewat Builder = 500.**
- `Services/LMS/SOP/SopPenjadwalanListService.php:50,82`
- `Services/LMS/SOP/SopPenjadwalanService.php:71,100,106`
- `Services/LMS/SOP/SopProgressReadService.php:1270,1417`
- `Services/LMS/SOP/SopRepositoryReadService.php:511,548,1133,1243`

`Id_SOP_Paket_Versi_Aktif`
- `Services/LMS/SOP/SopPaketDraftService.php:206` (`ensure()`), `:1123` (`publish()`)

`Nama_Batch`, `Keterangan`, `Id_SOP_Assignment_Rule` (N_SOP_Penjadwalan)
- `SopPenjadwalanService`, `SopPenjadwalanListService`, `SopAdminPenjadwalanController`,
  `SopAssignmentRuleService`, `SopAturanPaketService`, `SopAutomationRunService`
  — seluruh layar Penjadwalan & Aturan Otomatis.

`Id_Ujian_Token_Hasil`, `Id_Versi_Asal` (N_SOP_Progress)
- `SopCompletionService:402,431`, `SopKuisDispatchService:188,249,256`,
  `SopMonitoringService:663`, `SopProgressReadService:189,468`,
  `SopItemVersionService:312,348`

`Akurasi_Meter`, `User_Agent`, `IP_Address` (N_SOP_Absensi)
- `SopAbsensiService:169,170,171,325,333,339,340`, `SopMonitoringService:432,434,435,440,483`

> Catatan: `Keterangan` dan `User_Agent` adalah nama kolom umum yang juga dipakai
> di tabel lain. Daftar di atas hasil pencarian teks; saring dulu ke pemanggil yang
> benar-benar menyentuh `N_SOP_Penjadwalan` / `N_SOP_Absensi` sebelum menyunting.

### Yang TIDAK menjelaskan 500 pada "buat paket"

`SopPaketService::create()` (INSERT paket baru) sama sekali tidak menyentuh
kesepuluh kolom itu, dan `paginate()` sudah dijaga `SkemaTersedia::kolom()`. Jadi
**simpan paket baru** semestinya lolos. Yang jatuh adalah **publish paket**
(`reconcileSetelahPublish`) dan **buat draft dari paket terbit** (`ensure`).
Kalau galat 500 Anda memang terjadi pada aksi simpan-baru, penyebabnya masih di
luar temuan ini dan perlu jejak galat aslinya — lihat bagian 5.

---

## 3. (B) Yang hanya tidak terlihat oleh akun audit — 13 tabel

Ketiga belas tabel ini **ADA** di production. Yang tidak ada adalah `GRANT SELECT`
untuk `srv_team_web`. Karena `sys.tables` / `sys.columns` / `OBJECT_ID()` menyaring
diri menurut izin, tabel-tabel ini lenyap dari katalog dan pada pemeriksaan pertama
tampak seperti "belum dibuat" — itu **salah**.

```
N_SOP_Absensi                N_SOP_Kuis_Integrasi          N_SOP_Penjadwalan
N_SOP_Aktivitas_Log          N_SOP_Master_Paket            N_SOP_Penjadwalan_Integrasi
N_SOP_Coverage_Rollup        N_SOP_Paket_Section           N_SOP_Progress
N_SOP_Favorit                                              N_SOP_Setting
N_SOP_Item_Versi_Berkas      N_SOP_Kategori
```

Tambahan: pada 26 tabel `N_SOP_*` yang **bisa** dibaca, `srv_team_web` juga tidak
punya `INSERT`/`UPDATE`/`DELETE` sama sekali (diperiksa via
`HAS_PERMS_BY_NAME`). Login ini tidak tergabung di database role mana pun;
seluruh izinnya eksplisit per objek (SELECT 319, INSERT 139, UPDATE 126,
DELETE 124, EXECUTE 17).

**Ini hanya membatasi kita saat mengaudit.** Apakah login aplikasi production
punya masalah serupa belum diperiksa dan tidak disimpulkan di sini.

### Aturan untuk audit skema production berikutnya

| Pesan SQL Server | Arti |
|---|---|
| `Invalid object name 'X'` | objek memang tidak ada |
| `The SELECT permission was denied on the object 'X'` | objek **ADA**, izin yang kurang |
| `Invalid column name 'C'` | kolom memang tidak ada (berlaku juga pada tabel tanpa izin) |

Jangan pernah menyimpulkan "tabel/kolom hilang" dari katalog sistem saja. Sentuh
objeknya dan baca teks galatnya. Hal senada sudah tercatat di
`docs/audit/perbandingan-skema-production-2026-09-10.md` baris 33 (login production
tidak punya `VIEW DEFINITION`).

---

## 4. Yang sudah benar di production

- **Skema 37 tabel terbaca**: nol kolom hilang, nol kolom ekstra, nol tipe berbeda
  terhadap dev untuk seluruh tabel `N_SOP_*`. (Selisih pada `Karyawan`, `KPI_Users`,
  dan `N_HRIS_Notification` adalah drift lama yang sudah didokumentasikan, bukan SOP.)
- **Seluruh DDL SOP bernomor sudah mendarat**: 016, 023, 026, 030, 032, 034, 036,
  038, 039, 049, 052, 053, 055, 059 — setiap kolom yang mereka tambahkan ada.
- **Menu & registri lengkap**: grup `DMS` ada (`Id_Grup_Fitur=13`, landing
  `menuSopSayaPage`) dengan 13 halaman terpetakan di `N_HRIS_Grup_Fitur_Map`,
  termasuk `menuSopBuilderPage`.

### Data master

| Tabel | Dev | Prod | Catatan |
|---|---:|---:|---|
| `N_SOP_Master_APQC` | 14 | 14 | sama |
| `N_SOP_Master_Jenis_Dokumen` | 8 | 8 | sama |
| `N_SOP_Master_Tahap_Versi` | 8 | 8 | sama |
| `N_SOP_Master_Tahap_Versi_Syarat` | 7 | 7 | sama |
| `N_SOP_Master_Tahap_Versi_Transisi` | 22 | 22 | sama |
| `N_SOP_Master_APQC_Tipe_Item` | 40 | **52** | prod lebih banyak |
| `N_SOP_Master_Jenis_Dokumen_Tipe_Item` | 9 | **28** | prod lebih banyak |
| `N_SOP_Prioritas` | 3 | **0** | prod kosong |

`N_SOP_Prioritas` kosong tidak menyebabkan 500 — `SopPrioritasService::untukLayar()`
tetap mengembalikan tiga tingkat bawaan (KRITIS / TINGGI / NORMAL) — tapi tingkat
prioritas buatan admin tidak akan ada di production sampai diseed. Dua tabel
Tipe_Item yang lebih banyak di production berarti production mengizinkan pasangan
jenis/APQC × tipe item yang tidak dikenal development; perlu diputuskan arah
penyamaannya.

Catatan: folder `database/ddl/DDL-2026-096-perbaikan-eksekusi-sop-production/`
**kosong** (tanpa `forward.sql` maupun README).

---

## 5. Kenapa galatnya "tidak masuk log"

Untuk Builder Paket sebenarnya **masuk**: `storePaket()` dan `listPaket()`
menangkap `\Throwable` lalu memanggil `Log::error('SOP builder … gagal',
['exception' => $e])` sebelum membalas 500. Kalau galat Anda terjadi di deployment
production (Cloud Run), entrinya ada di Cloud Logging, bukan di
`storage/logs/laravel.log` mesin lokal.

Yang benar-benar menelan galat: modul SOP punya **35 blok `catch` tanpa `Log::` dan
tanpa `throw`** (dari 236 blok catch).

| Berkas | Baris |
|---|---|
| `Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php` | 79, 97, 106, 117, 136, 157, 171, 184, 195, 209, 235 |
| `Http/Controllers/LMS/SOP/Export/SopExportStudioController.php` | 137, 189, 224, 244, 260, 272, 286, 299, 361, 373 |
| `Http/Controllers/LMS/SOP/Governance/SopAdminSetupSystemController.php` | 102, 146 |
| `Services/LMS/SOP/SopDokumenService.php` | 645, 1867, 1894 |
| `Http/Controllers/LMS/SOP/Integration/SopKuisCallbackController.php` | 43 |
| `Services/LMS/SOP/SopAssignmentRuleService.php` | 569 |
| `Services/LMS/SOP/SopBerkasService.php` | 74 |
| `Services/LMS/SOP/SopDokumenKesehatanService.php` | 980 |
| `Services/LMS/SOP/SopMonitoringAnalyticsService.php` | 237 |
| `Services/LMS/SOP/SopPenjadwalanListService.php` | 497 |
| `Services/LMS/SOP/Support/SopKaryawanToken.php` | 81 |
| `Services/LMS/SOP/Support/SopPendaftaranMandiri.php` | 47 |
| `Services/LMS/SOP/Support/SopVersiTanggal.php` | 169 |

Juga: `app/Exceptions/Handler.php:83` mendaftarkan `reportable(function (Throwable
$e) { // })` yang kosong. Ini **tidak** mematikan pelaporan (callback yang tidak
mengembalikan `false` tetap lanjut ke logging bawaan), tapi menyesatkan siapa pun
yang membaca handler sambil mencari galat yang hilang.

---

## 6. Tindak lanjut

1. **Buat DDL baru** berisi `ALTER TABLE … ADD` untuk kesepuluh kolom di bagian 2.
   Jangan mengandalkan DDL-2026-092 — penjaga `IF OBJECT_ID … IS NULL`-nya sudah
   basi dan akan melewati seluruh blok.
2. Tinjau ulang cakupan DDL-2026-092 secara keseluruhan: bagian 5-nya disusun
   dengan asumsi 44 tabel belum ada; sebagian kini sudah ada, jadi kolom pelengkap
   tabel-tabel itu perlu diperiksa dengan metode probe yang sama.
3. Pasang `SkemaTersedia::kolom()` pada titik sentuh tanpa penjaga (bagian 2), atau
   putuskan bahwa kolomnya wajib ada dan biarkan gagal keras setelah DDL dijalankan.
4. Seed `N_SOP_Prioritas` di production.
5. Cocokkan arah `N_SOP_Master_APQC_Tipe_Item` dan
   `N_SOP_Master_Jenis_Dokumen_Tipe_Item`.
6. Isi atau hapus folder kosong `DDL-2026-096-perbaikan-eksekusi-sop-production/`.
7. Opsional, untuk audit: `GRANT SELECT` + `VIEW DEFINITION` pada 13 tabel bagian 3
   untuk `srv_team_web`, supaya audit berikutnya tidak perlu memakai trik pesan galat.

---

## Lampiran — perintah verifikasi

```sql
-- Bedakan "tidak ada" dari "tidak boleh dilihat"
SELECT TOP 0 * FROM dbo.N_SOP_Master_Paket;
--   Invalid object name            -> memang tidak ada
--   SELECT permission was denied   -> ada, izinnya yang kurang

-- Periksa KOLOM meski tabelnya tak terbaca (nama diresolusi sebelum cek izin)
SELECT TOP 0 [Versi_Paket_Aktif] FROM dbo.N_SOP_Master_Paket;
--   Invalid column name            -> kolom memang belum ada
--   SELECT permission was denied   -> kolom ADA

-- Izin efektif per aksi (tidak menulis apa pun)
SELECT HAS_PERMS_BY_NAME('dbo.N_SOP_Master_Paket','OBJECT','SELECT') AS sel,
       HAS_PERMS_BY_NAME('dbo.N_SOP_Master_Paket','OBJECT','INSERT') AS ins,
       HAS_PERMS_BY_NAME('dbo.N_SOP_Master_Paket','OBJECT','UPDATE') AS upd,
       HAS_PERMS_BY_NAME('dbo.N_SOP_Master_Paket','OBJECT','DELETE') AS del;
```
