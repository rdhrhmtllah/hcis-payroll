# N_HRIS_Report_Template

Definisi laporan yang dirakit pengguna lewat Export Studio. Satu baris = satu
template.

**Change ID:** `DDL-2026-009-create-report-template-tables`
**SQL:** `database/ddl/DDL-2026-009-create-report-template-tables/forward.sql`

## Tujuan

Menyimpan definisi laporan sebagai JSON berisi **kunci katalog**, bukan SQL.
Tabel ini sengaja lintas modul: kolom `Module` membedakan pemiliknya, mengikuti
pola `N_HRIS_Async_Tasks` yang sudah dibagi tiga modul export. Modul baru
bergabung tanpa perubahan skema.

Template resmi ("Template Sistem") **tidak** menempati baris di sini — ia hidup
di kode (`App\Services\LMS\SOP\Export\SopTemplateSistem`) supaya tak ada drift
antara development dan production dan isinya ikut code review.

## Kolom

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_Report_Template` | `int IDENTITY(1,1)` | tidak | Primary key. |
| `Module` | `varchar(30)` | tidak | Pemilik modul: `sop`, kelak `lms`, `kpi`. **Selalu diisi dari route, tak pernah dari badan permintaan.** |
| `Kode` | `varchar(60)` | tidak | Identitas template di URL dan payload job. Dibangkitkan server: `<module>-<slug nama>-<4 hex>`. |
| `Nama` | `nvarchar(150)` | tidak | Nama yang dilihat pengguna. |
| `Deskripsi` | `nvarchar(500)` | ya | Keterangan bebas. |
| `Dataset_Key` | `varchar(40)` | tidak | Kunci dataset di registry modul, mis. `kepatuhan`. Ditafsirkan **dalam** `Module`. |
| `Mode` | `varchar(20)` | tidak | `RINGKASAN` (agregasi) atau `RINCI` (baris mentah). |
| `Definisi_Json` | `nvarchar(max)` | tidak | Definisi lengkap. Seluruh nilainya kunci katalog; tak ada SQL dari pengguna. |
| `Versi_Definisi` | `int` | tidak | Default `1`. Ditulis `ReportTemplateService` dari `definisi.versi` saat simpan/perbarui, sehingga migrasi bentuk definisi kelak bisa menyaring per versi tanpa membuka JSON-nya. |
| `Tingkat_Bagi` | `varchar(20)` | tidak | `PRIBADI`, `DIBAGIKAN`, atau `ORGANISASI`. **`DIBAGIKAN` dan `ORGANISASI` saat ini berperilaku identik** — keduanya berarti "terlihat semua orang yang berhak membuka Studio modul ini", karena penyasaran per sasaran (`N_HRIS_Report_Template_Akses`) belum punya jalur tulis. `ReportTemplateService::TINGKAT_TERBUKA` adalah satu-satunya tempat daftar ini didefinisikan. |
| `Kode_Karyawan_Pemilik` | `varchar(50)` | tidak | Pemilik template. |
| `Kode_Template_Asal` | `varchar(60)` | ya | Jejak duplikat. Boleh menunjuk kode template sistem yang hidup di kode. |
| `Flag_Aktif` | `char(1)` | tidak | `Y` aktif, `T` dinonaktifkan. Default `Y`. |
| `Created_At` | `datetime2(0)` | tidak | Default `SYSDATETIME()`. |
| `Created_By` | `varchar(100)` | ya | Aktor audit. |
| `Updated_At` | `datetime2(0)` | ya | |
| `Updated_By` | `varchar(100)` | ya | Aktor audit. |

## Flag

`Flag_Aktif` default `Y`. Menghapus template **menonaktifkan** barisnya
(`'T'`), tidak menghapusnya: `Kode_Template_Asal` pada template turunannya
harus tetap bisa ditelusuri.

## Sumber aktor audit

`Created_By` dan `Updated_By` berisi `Kode_Karyawan` dari `HRIS_Karyawan`,
dibaca lewat `Auth::user()->karyawan`. Sama dengan `Requested_By` pada
`N_HRIS_Async_Tasks`.

## Relasi

`N_HRIS_Report_Template_Akses.Report_Template_Id` → `Id_Report_Template`.
**Tanpa FOREIGN KEY**, mengikuti pola tabel `N_HRIS_*` lain di repo ini.

## Constraint dan index

| Objek | Alasan |
|---|---|
| `CK_Report_Template_DefinisiJson` | Integritas struktur, bukan daftar nilai bisnis. `Mode`, `Tingkat_Bagi`, dan `Dataset_Key` **tidak** diberi CHECK karena daftar nilainya berkembang; validasinya di backend. |
| `UX_Report_Template_Kode (Module, Kode)` | Tiap modul punya ruang nama kode sendiri. `RINCI_ITEM` boleh ada di SOP maupun LMS. |
| `IX_Report_Template_Pemilik (Module, Kode_Karyawan_Pemilik, Flag_Aktif)` | Melayani galeri "Milik Saya". |
| `IX_Report_Template_Bagi (Module, Tingkat_Bagi, Flag_Aktif) INCLUDE (Dataset_Key)` | Melayani galeri "Dibagikan" tanpa lookup. |

## Dipakai oleh

| Lapisan | Berkas |
|---|---|
| Layanan | `App\Services\SYSTEM\Application\Reports\ReportTemplateService` — satu-satunya penulis tabel ini |
| Layanan | `App\Services\SYSTEM\Application\Reports\ReportExportService` — membaca definisi dari payload job, bukan dari tabel ini |
| Controller | `App\Http\Controllers\LMS\SOP\Export\SopExportStudioController` |
| Job | `App\Jobs\LMS\SOP\Export\GenerateSopReportExportJob` |

Endpoint (semua di-gate `check.jabatan:menuSopMonitoringPage`):

```text
GET    api/v1/sop/export-studio/katalog/{dataset}
GET    api/v1/sop/export-studio/template
POST   api/v1/sop/export-studio/template
POST   api/v1/sop/export-studio/template/{kode}/duplikat
DELETE api/v1/sop/export-studio/template/{kode}
POST   api/v1/sop/export-studio/pratinjau
POST   api/v1/sop/export-studio/jalankan
```

Berkas hasil dilacak di `N_HRIS_Async_Tasks` dengan `Module = 'sop-studio'`.
